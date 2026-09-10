# Contexto de Proyecto: [ISSUE-DASH-003] Transaccionalidad, locking y control de concurrencia en HITL (GLOBAL-003)

## 1. Identificación y Estado
- **Issue ID:** ISSUE-DASH-003
- **GitLab Item:** #38 (jg_dashboard)
- **Vínculo Global:** GLOBAL-003
- **Fase:** Fase Post-Auditoría (Implementación de Hallazgos Codex)
- **Prioridad:** High / Critical
- **Complejidad Estimada:** M
- **Estado:** Implementado / Verificado

## 2. Alineación Arquitectónica
En la auditoría técnica independiente (GLOBAL-003), se detectó que la resolución de solicitudes de aprobación humana (HITL) en `HitlApprovalService` no implementaba bloqueo pesimista en base de datos ni una máquina de estados intermedia antes de despachar la petición de reanudación al agente.

Esto presentaba dos riesgos críticos de concurrencia y resiliencia:
1. **Carreras de doble aprobación (Double Approval Race):** Dos peticiones concurrentes de operadores sobre la misma solicitud pendiente podían superar simultáneamente la comprobación `$approval->isPending()` y enviar dos llamadas concurrentes de aprobación a `jg_agente`.
2. **Desincronización por fallo de red:** Si la llamada HTTP a `jg_agente` fallaba o agotaba el tiempo de espera (timeout), el estado de la solicitud en el Dashboard podía quedar en un estado inconsistente o bloqueado sin posibilidad de reintento.

Para solucionar estos problemas de forma atómica y robusta:
1. **Bloqueo Pesimista (`lockForUpdate`):**
   Tanto en `approveApproval()` como en `rejectApproval()`, se ejecuta una micro-transacción de base de datos (`DB::transaction`) que adquiere un bloqueo pesimista de fila (`lockForUpdate()`) sobre el registro de `ApprovalRequest`.
2. **Estado Intermedio `PROCESSING`:**
   Dentro de la transacción con bloqueo exclusivo, se valida que el registro esté en estado `PENDING`. Si ya está en `PROCESSING` u otro estado resuelto, se aborta inmediatamente con HTTP 409 Conflict. Si está en `PENDING`, se transiciona atómicamente a `PROCESSING` y se libera el bloqueo antes de la llamada de red.
3. **Reconciliación Automática ante Fallos de Red:**
   La invocación HTTP a `AgentHttpClient::approveExecution()` o `rejectExecution()` se encapsula con manejo de excepciones y validación de respuesta. Si ocurre un fallo de transporte, timeout o error de servidor del agente (HTTP >= 400), el estado de la solicitud se reconcilia automáticamente de vuelta a `PENDING` para permitir la remediación y posteriores intentos por parte de los operadores.
4. **Finalización Segura:**
   Únicamente tras recibir una confirmación exitosa (HTTP 200) del agente, se transiciona la solicitud a su estado terminal (`APPROVED` o `REJECTED`), se asocia el usuario resolutor y justificación, y se emite el correspondiente evento inmutable de auditoría (`AuditEvent`).

## 3. Criterios de Aceptación
- `HitlApprovalService::approveApproval` y `rejectApproval` adquieren `lockForUpdate` dentro de `DB::transaction`.
- La solicitud pasa al estado `PROCESSING` antes de emitir la llamada HTTP al agente.
- Solicitudes concurrentes o peticiones cuando la solicitud está en `PROCESSING` son rechazadas con HTTP 409 Conflict.
- Fallos de red o errores HTTP del agente revierten atómicamente el estado a `PENDING`.
- Confirmación exitosa del agente finaliza la solicitud en `APPROVED` o `REJECTED` y registra la auditoría.
- La suite completa de pruebas de Pest en `jg_dashboard` supera los tests unitarios y de concurrencia al 100%.
