# Análisis Técnico: [ISSUE-DASH-003] Transaccionalidad, locking y control de concurrencia en HITL (GLOBAL-003)

## 1. Diagnóstico del Estado Previo
En el estado previo de `jg_dashboard`:
- `HitlApprovalService::approveApproval()` y `rejectApproval()` cargaban la solicitud mediante `$approval = ApprovalRequest::with('agent')->find($approvalId);` sin ninguna transacción ni bloqueo a nivel de base de datos.
- Se comprobaba `$approval->isPending()`. Si dos peticiones concurrían en paralelo, ambas leían el estado `PENDING` antes de que cualquiera de ellas terminara su ciclo.
- Se procedía a llamar a `$this->agentClient->approveExecution()` o `rejectExecution()`. Si ambas peticiones concurrían, se enviaban dos llamadas de reanudación a `jg_agente` con el mismo `thread_id`.
- El modelo `ApprovalRequest` no contemplaba un estado de tránsito o bloqueo `PROCESSING`, permitiendo únicamente transiciones binarias `PENDING` -> `APPROVED` / `REJECTED`.

## 2. Solución Diseñada
1. **Transición a `PROCESSING` bajo `lockForUpdate`:**
   - Se inicia una micro-transacción de base de datos:
     ```php
     $approval = DB::transaction(function () use ($approvalId, &$errorResponse) {
         $record = ApprovalRequest::where('id', $approvalId)
             ->lockForUpdate()
             ->first();

         if (!$record) {
             $errorResponse = ['status' => 404, 'data' => ['message' => 'Solicitud de aprobación no encontrada.']];
             return null;
         }

         if ($record->isProcessing()) {
             $errorResponse = ['status' => 409, 'data' => ['message' => 'La solicitud ya está siendo procesada concurrentemente.']];
             return null;
         }

         if (!$record->isPending()) {
             $errorResponse = ['status' => 409, 'data' => ['message' => 'La solicitud de aprobación ya ha sido resuelta previamente.']];
             return null;
         }

         $record->status = 'PROCESSING';
         $record->save();

         return $record;
     });
     ```
   - Al comitear la micro-transacción, el registro queda guardado en la base de datos como `PROCESSING` y el cerrojo de fila (`lockForUpdate`) se libera para no mantener transacciones abiertas durante llamadas HTTP de red.
2. **Reconciliación en Caso de Fallo de Red o Error del Agente:**
   - La llamada a `AgentHttpClient` se ejecuta fuera del bloqueo de base de datos pero protegida por bloque `try ... catch (\Throwable $e)` y comprobación de código de respuesta `$agentResponse['status'] >= 400`.
   - Ante cualquier falla (desconexión de red, timeout, caída del agente, error 500/503/504), se ejecuta inmediatamente la reconciliación:
     ```php
     $approval->status = 'PENDING';
     $approval->save();
     ```
   - Esto evita que la solicitud quede eternamente congelada en `PROCESSING` y permite al operador reintentar una vez restaurada la conectividad.
3. **Aprobación / Rechazo Terminal:**
   - Si el agente responde satisfactoriamente (200), se invoca `$approval->approve($resolver, $justification)` o `$approval->reject(...)` y se registra el evento en `AuditEvent`.
   - Se modificó `ApprovalRequest::approve()` y `reject()` para permitir la transición terminal desde `PROCESSING` además de `PENDING`.

## 3. Impacto en Pruebas
- Se creó `tests/Unit/Services/HitlApprovalServiceConcurrencyTest.php` verificando:
  - Rechazo con 409 Conflict si la solicitud ya está en `PROCESSING` al intentar aprobar.
  - Rechazo con 409 Conflict si la solicitud ya está en `PROCESSING` al intentar rechazar.
  - Reconciliación automática a `PENDING` si el agente lanza una excepción de transporte/red (503).
  - Reconciliación automática a `PENDING` si el agente agota el tiempo de espera / timeout (504).
  - Reconciliación automática a `PENDING` si el agente retorna un error HTTP 500.
  - Flujo atómico exitoso `PENDING` -> `PROCESSING` -> `APPROVED` (200).
