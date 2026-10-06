# Project Context: [DASH-CODE-02] Dependencia Exclusiva de Polling sin Endpoints de Webhook para Tareas Asíncronas

## Resumen del Mecanismo de Notificación Asíncrona (`jg_dashboard`)
El plano de control de `jg_dashboard` gestiona el seguimiento del ciclo de vida de ejecuciones cognitivas iniciadas en modo asíncrono.

### Arquitectura de Notificación Proactiva:
- **Endpoint Callback:** `POST /api/v1/internal/executions/{execution_id}/status-update`.
- **Autenticación:** Firmas HMAC-SHA256 (`agent.hmac` con cabeceras `X-Signature`, `X-Timestamp`, `X-Agent-ID`).
- **Estados Sincronizados:** `PROCESSING`, `WAITING_APPROVAL`, `COMPLETED`, `FAILED`, `CANCELLED`.
- **Orquestación HITL:** Detección instantánea de puntos de control que requieran aprobación humana, creando la entrada en `ApprovalRequest` en tiempo real.
- **Inyección de URL:** `ExecutionController@trigger` provee automáticamente `status_callback_url` en el contrato de entrada hacia el agente.
