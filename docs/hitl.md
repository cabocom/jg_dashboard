# Gobernanza Human-In-The-Loop (HITL): jg_dashboard

---

## 1. Concepto y Flujo de Vida

Cuando una ejecución orquestada por `jg_agente` determina que su plan de acción implica un riesgo considerable (`RiskLevel >= HIGH`), el agente suspende la ejecución en un checkpoint (`waiting_approval`) y no prosigue hasta recibir confirmación explícita del operador humano.

```text
    [jg_agente]                              [jg_dashboard]                     [Operador Humano]
         │                                          │                                   │
         │── Pausa en checkpoint (waiting_approval)─►│                                   │
         │   (Plan de alto riesgo)                  │── Persiste ApprovalRequest (PENDING)
         │                                          │── Emite auditoría hitl.requested  │
         │                                          │                                   │
         │                                          │◄── GET /api/v1/approvals/pending ─│
         │                                          │─── Retorna lista con tiempo ─────►│
         │                                          │                                   │
         │                                          │◄── POST /approvals/{id}/approve ──│
         │                                          │    (o /reject con justificación)  │
         │◄── POST /executions/{th}/approve ────────│                                   │
         │    (o /reject)                           │── Actualiza estado en BD          │
         │                                          │── Emite auditoría hitl.approved   │
         │── Reanuda grafo (o cancela) ────────────►│── Retorna 200 OK ────────────────►│
```

---

## 2. Máquina de Estados de una Solicitud

```text
               ┌───────────────────────┐
               │        PENDING        │
               └───────────┬───────────┘
                           │
             ┌─────────────┴─────────────┐
             │                           │
    (Operador aprueba)          (Operador rechaza)
             ▼                           ▼
    ┌─────────────────┐         ┌─────────────────┐
    │    APPROVED     │         │    REJECTED     │
    └─────────────────┘         └─────────────────┘
```

- **Idempotencia:** Si un agente emite actualizaciones de un mismo plan sobre el mismo `thread_id` mientras se encuentra en estado `PENDING`, el Dashboard actualiza el registro existente en lugar de generar duplicados.
- **Inmutabilidad tras Resolución:** Una solicitud con estado `APPROVED` o `REJECTED` no puede volver a modificarse. Cualquier intento posterior de resolución retorna `409 Conflict`.
- **Garantía de Consistencia Estado-Agente:** El cambio a `APPROVED` o `REJECTED` en la base de datos del Dashboard solo se consolida si `jg_agente` responde satisfactoriamente (200 OK / 202 Accepted). Si la llamada sufre un error de red o timeout, la solicitud se preserva en `PENDING` para permitir reintentos.

---

## 3. Endpoints del Módulo HITL

1. **`GET /api/v1/approvals/pending`:**
   - Consulta paginada de solicitudes en espera.
   - Retorna: resumen de acciones (`action_summary`), nivel de riesgo, catálogo de herramientas (`tools_involved`), comandos de mitigación (`compensation_commands`) y métricas de tiempo transcurrido (`elapsed_time` legible y `elapsed_seconds` absoluto).
   - Filtros: por `agent_id` y por `risk_level`.

2. **`POST /api/v1/approvals/{approval_id}/approve`:**
   - Registra la aprobación humana.
   - Justificación opcional.
   - Delega `POST /executions/{thread_id}/approve` hacia `jg_agente`.
   - Genera auditoría inmutable `hitl.approved`.

3. **`POST /api/v1/approvals/{approval_id}/reject`:**
   - Registra el rechazo humano.
   - **Justificación obligatoria** (`min: 5 caracteres`).
   - Delega `POST /executions/{thread_id}/reject` hacia `jg_agente`.
   - Genera auditoría inmutable `hitl.rejected`.
