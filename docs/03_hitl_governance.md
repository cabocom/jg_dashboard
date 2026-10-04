# 03. Gobernanza Human-In-The-Loop (HITL) (`jg_dashboard`)

## 1. Ciclo Operativo de Gobernanza Humana

Cuando un agente autónomo planifica una acción que implica mutación de infraestructura, riesgo operacional crítico o comandos destructivos (`RiskLevel >= HIGH`), el grafo LangGraph en `jg_agente` entra en estado de suspensión (`WAITING_APPROVAL`). El plano de control asume la custodia del flujo:

```text
  [jg_agente]                              [jg_dashboard]                         [Operador Humano]
       │                                         │                                        │
       │── Webhook o Polling con status ────────►│                                        │
       │   WAITING_APPROVAL                      │ Registra ApprovalRequest               │
       │                                         │ Emite evento a Consola UI             │
       │                                         │───────────────────────────────────────►│
       │                                         │                                        │ Revisa plan, comandos,
       │                                         │                                        │ riesgo y justificación
       │                                         │                                        │
       │                                         │◄── POST /approvals/{id}/approve ───────│
       │                                         │    (o /reject con motivo)              │
       │                                         │                                        │
       │                                         │ Transacción DB + lockForUpdate         │
       │                                         │ Verifica estado PENDING                │
       │                                         │                                        │
       │◄── POST /executions/{th}/approve ───────│ (Firmado HMAC-SHA256)                  │
       │    con Command(resume=payload)          │                                        │
       │                                         │                                        │
       │ Reanuda grafo y finaliza ejecución      │                                        │
       │────────────────────────────────────────►│ Actualiza AgentTask a COMPLETED        │
       │                                         │ y registra AuditEvent inmutable        │
```

---

## 2. Máquina de Estados y Concurrencia Pesimista (`lockForUpdate`)

En entornos de operaciones multi-usuario, existe el riesgo de que dos operadores intenten autorizar o rechazar simultáneamente la misma solicitud de alto riesgo.

Para neutralizar este vector, `HitlApprovalService` (`app/Services/Hitl/HitlApprovalService.php`) implementa una máquina de estados estricta protegida por transacciones atómicas con bloqueo pesimista exclusivo a nivel de fila (`lockForUpdate()`):

```text
               ┌───────────────────────┐
               │        PENDING        │◄─────────────────────────┐
               └───────────┬───────────┘                          │
                           │                                      │
            (lockForUpdate: DB::transaction)                      │
                           │                                      │
                           ▼                                      │
               ┌───────────────────────┐                          │
               │      PROCESSING       │                          │
               └───────────┬───────────┘                          │
                           │                                      │
               ┌───────────┴───────────┐                          │
               │                       │                          │
         (HTTP 200 Agente)      (Error HTTP / Timeout) ───────────┘
               │                (Reversión atómica a PENDING)
    ┌──────────┴──────────┐
    │                     │
(Aprobación)          (Rechazo)
    ▼                     ▼
┌─────────────────┐ ┌─────────────────┐
│    APPROVED     │ │    REJECTED     │
└─────────────────┘ └─────────────────┘
```

### 2.1 Fase de Reserva Atómica (`PROCESSING`)
Al recibir una petición de resolución (`/approve` o `/reject`):
1. Inicia una micro-transacción de base de datos adquiriendo bloqueo pesimista sobre `dashboard_approval_requests`.
2. Si el registro no está en `PENDING`, aborta inmediatamente retornando `409 Conflict`.
3. Pasa el estado a `PROCESSING` y confirma la transacción de base de datos para liberar el cerrojo de fila **antes** de despachar la petición de red HTTP hacia `jg_agente`.

---

## 3. Tolerancia a Fallos y Reconciliación en Red

La comunicación inter-servicios en redes distribuidas está sujeta a caídas de red, reinicios de contenedores o desfases temporales. `HitlApprovalService` incorpora salvaguardas avanzadas:

### 3.1 Reconciliación Local ante Respuestas HTTP 404 / 409 del Agente
Si el operador aprueba una solicitud y el agente responde con error:
- **HTTP 409 Conflict:** Indica que el hilo en el agente ya continuó o fue completado previamente por otra vía.
- **HTTP 404 Not Found:** Indica que la sesión en el agente ha expirado o el hilo no requiere aprobación activa.
- **Acción del Dashboard:** En lugar de devolver un fallo al operador, el Control Plane **reconcilia localmente** la solicitud marcándola como `APPROVED` con el sufijo `[Reconciliado: <motivo>]`, transiciona la tarea asociada en `agent_tasks` a `PROCESSING`, y registra un evento `hitl.approved` en `dashboard_audit_events` con el flag `'reconciled' => true`.

### 3.2 Reversión Atómica ante Fallos de Transporte o Timeouts (504 / 503)
Si la llamada HTTP al agente falla por timeout (`504 Gateway Timeout`), desconexión de socket (`503 Service Unavailable`) o excepción de red, el Dashboard **revierte automáticamente el estado de la solicitud de `PROCESSING` a `PENDING`**. Esto asegura que la solicitud no quede "congelada" en un estado intermedio y permite que los operadores reintenten la autorización tan pronto como se restablezca la conectividad.

---

## 4. Auto-Descubrimiento de Aprobaciones Huérfanas y Sincronización en Background

### 4.1 Rescate de Solicitudes Huérfanas (`WebConsoleController::pendingApprovals`)
Al consultar las aprobaciones pendientes desde la consola web, el sistema ejecuta un barrido proactivo de reconciliación:
- Localiza registros en `agent_tasks` con estado `WAITING_APPROVAL` que carezcan de registro activo en `dashboard_approval_requests`.
- Si la tarea previa ya fue rechazada o cancelada, sincroniza su estado local a `CANCELLED`.
- Si la tarea no tiene solicitud previa, invoca `HitlApprovalService::registerPendingApproval()` al vuelo extrayendo el plan propuesto, pasos cognitivos y comandos de compensación desde `result_json` o `payload_json`.

### 4.2 Sincronización Proactiva de Tareas en Background (`WebConsoleController::listExecutions`)
Al solicitar el listado de ejecuciones, para cualquier tarea local que figure en estado `PROCESSING` o `QUEUED`, el Dashboard interroga en segundo plano a `jg_agente` vía `AgentHttpClient::getExecutionStatus()`. Si el grafo remoto finalizó o entró en `WAITING_APPROVAL`, el Dashboard actualiza automáticamente los registros locales y registra la solicitud HITL sin requerir intervención manual.

---

## 5. Parámetro `modified_arguments` y Alcance Actual

> [!IMPORTANT]
> **Aclaración de Paridad Técnica de Auditoría:**
> En la base de código actual de `jg_dashboard` y `jg_agente`, el endpoint de aprobación (`POST /api/v1/approvals/{id}/approve` y `POST /console/approvals/{id}/approve`) **no admite modificación de argumentos en caliente** (`modified_arguments`).
> 
> El modelo de seguridad actual es determinista y binario: el operador evalúa el plan técnico formulado por el modelo y **autoriza su ejecución íntegra o la rechaza**. Si los parámetros son inadecuados, la directiva operativa es rechazar la solicitud proporcionando la justificación técnica en el campo `justification` (mínimo 5 caracteres); el agente aborta la ejecución con estado `CANCELLED` y el usuario puede solicitar una nueva tarea con las restricciones corregidas.

---

## 6. Reconciliación Asíncrona vía Webhook (`InternalExecutionController`)

Cuando `jg_agente` finaliza una tarea que fue delegada en segundo plano (`sync=false`), invoca el webhook del Control Plane:
```http
POST /api/v1/internal/executions/{execution_id}/complete
X-Agent-ID: a0000000-0000-0000-0000-000000000002
Authorization: Bearer <permanent_secret>
```

1. **Autenticación del Agente:** Valida la credencial mediante `Agent::verifyPermanentSecret()`.
2. **Actualización de Tarea (`AgentTask`):** Mapea el estado (`COMPLETED`, `WAITING_APPROVAL`, `CANCELLED`, `FAILED`), persiste el resultado completo y calcula con precisión milimétrica la duración transcurrida (`$completedAt->diffInMilliseconds($task->started_at) / 1000`).
3. **Registro Automático HITL:** Si el payload reporta `requires_approval = true` o `WAITING_APPROVAL`, registra inmediatamente la solicitud con su plan y pasos cognitivos.
4. **Pista de Auditoría:** Genera un evento `execution.webhook_completed` inmutable en `dashboard_audit_events`.

---

## 7. Navegación

- [← 02. Control de Acceso y Roles (RBAC)](02_rbac_tenancy.md)
- [04. Protocolo Criptográfico Inter-Servicio →](04_hmac_handshake.md)
