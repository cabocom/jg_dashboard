# Análisis Técnico: [ISSUE-F07-T01] Gestión de solicitudes de aprobación HITL en Dashboard

**Issue ID:** ISSUE-F07-T01  
**Fase:** FASE 07 (Consola y acciones de aprobación Human-In-The-Loop)  
**Prioridad:** Critical  
**Complejidad:** M  
**Dependencias:** ISSUE-F02-T04, ISSUE-F06-T01  

---

## 1. Contexto y Objetivos

En el ecosistema jG, cuando una ejecución cognitiva orquestada por `jg_agente` identifica un plan con impacto potencialmente destructivo o de alto riesgo (`RiskLevel >= HIGH`), el flujo del grafo LangGraph se interrumpe en un checkpoint de pausa condicional (`waiting_approval`).

La responsabilidad de gobernar, auditar y presentar este estado crítico recae de manera exclusiva en el plano de control (`jg_dashboard`). El objetivo de este issue es crear un servicio centralizado de dominio, `HitlApprovalService`, que:
1. Registre las solicitudes de aprobación en `dashboard_approval_requests` estructurando el plan propuesto (`proposed_plan_json`), las herramientas involucradas (`tools_involved`) y los comandos de compensación/rollback (`compensation_commands_json`).
2. Garantice la idempotencia por `thread_id`: si una ejecución reporta múltiples pausas o actualizaciones de checkpoint en el mismo hilo sin resolverse previamente, el registro existente en estado `PENDING` se actualiza sin generar registros huérfanos o duplicados.
3. Genere automáticamente una traza inmutable en `dashboard_audit_events` (`hitl.requested`) para garantizar la trazabilidad forense.
4. Desacople la persistencia y consulta de HITL respecto a los controladores API (`ExecutionController`), habilitando la reutilización para la futura API de aprobaciones (`/api/v1/approvals`) y la interfaz Filament.

---

## 2. Arquitectura de la Solución

### 2.1 Servicio Centralizado: `HitlApprovalService`
Ubicado en `app/Services/Hitl/HitlApprovalService.php`:
- `registerPendingApproval(array $data, ?User $initiator, ?string $ipAddress, ?string $userAgent): ApprovalRequest`:
  - Valida la presencia obligatoria de `thread_id`.
  - Normaliza la estructura del plan propuesto, incorporando la lista explícita de herramientas involucradas (`tools_involved`).
  - Busca si ya existe una solicitud en estado `PENDING` para el `thread_id`.
  - Crea o actualiza el registro en `dashboard_approval_requests`.
  - Emite el evento de auditoría `hitl.requested` vía `AuditEvent::record()`.
- `getPendingApprovals(?string $agentId, int $perPage): LengthAwarePaginator`:
  - Consulta paginada de solicitudes con scope `pending()`, ordenadas cronológicamente descendente y con filtrado opcional por agente.
- `findApproval(int|string $id): ?ApprovalRequest`:
  - Búsqueda por ID con precarga de relaciones (`agent`, `resolver`).
- `findPendingByThread(string $threadId): ?ApprovalRequest`:
  - Búsqueda de solicitud activa pendiente asociada a un hilo de ejecución específico.

### 2.2 Integración en `ExecutionController`
- Se refactorizó `app/Http/Controllers/Api/ExecutionController.php` para delegar la persistencia de checkpoints HITL directamente en `HitlApprovalService::registerPendingApproval()`, manteniendo el controlador enfocado únicamente en la negociación HTTP y validación de entrada.

---

## 3. Criterios de Aceptación Verificados

- [x] Solicitud de aprobación queda guardada con detalles del plan, herramientas involucradas y nivel de riesgo.
- [x] Idempotencia estricta para evitar duplicidades por `thread_id` en estado `PENDING`.
- [x] Registro automático de evento de auditoría (`hitl.requested`) con IP, User-Agent y metadatos de riesgo.
- [x] Desacoplamiento arquitectónico mediante servicio de dominio inyectable.
- [x] 100% de pruebas automáticas superadas en `./scripts/check.sh`.
