# Contexto de Proyecto: [ISSUE-DASH-001] Control de Acceso RBAC y eliminación de fallbacks en Consola Web (/console)

## 1. Identificación y Estado
- **Issue ID:** ISSUE-DASH-001
- **GitLab Work Item:** jg_dashboard#36
- **Fase:** Fase Post-Auditoría (Consolidación de Seguridad y RBAC)
- **Prioridad:** High / Critical
- **Complejidad Estimada:** M
- **Estado:** Implementado / Verificado

## 2. Alineación Arquitectónica
En la auditoría técnica de seguridad (FINDINGS.md), se detectó que los endpoints de mutación y operaciones sensibles en la Consola Web (`/console`) de `jg_dashboard` solo contaban con el middleware genérico `auth`, permitiendo que cualquier usuario autenticado (incluyendo el rol `viewer`) disparara ejecuciones o resolviera solicitudes HITL. Adicionalmente, el controlador `WebConsoleController` utilizaba fallbacks inseguros que instanciaban usuarios ficticios (`operator.console@example.com`) o inyectaban resolutores con `User::first()`, eludiendo las restricciones de autorización y alterando la trazabilidad de auditoría.

Para resolver esta vulnerabilidad y consolidar el modelo de control de acceso:
1. **Middleware de Autorización en Rutas Web (`routes/web.php`)**:
   - `role:admin` para aprovisionamiento de agentes (`POST /console/agents`).
   - `role:admin,operator` para resolución de solicitudes HITL (`POST /console/approvals/{id}/approve`, `POST /console/approvals/{id}/reject`) y disparo de tareas (`POST /console/executions/trigger`).
2. **Defensa en Profundidad en `WebConsoleController`**:
   - Eliminación total de fallbacks que creaban o buscaban usuarios dummy (`operator.console@example.com` / `User::first()`).
   - Requerimiento estricto de usuario autenticado (`$request->user()`).
   - Validación programática explícita de roles retornando `401 Unauthorized` si no hay sesión o `403 Forbidden` si el rol es insuficiente.
3. **Preservación de Trazabilidad y Auditoría**:
   - Cada resolución HITL queda vinculada de manera inmutable al `user_id` del usuario autenticado real en `ApprovalRequest` y en `AuditEvent`.

## 3. Criterios de Aceptación
- La provisión de agentes vía `POST /console/agents` solo es accesible por usuarios con rol `admin`.
- Las acciones HITL (`approve`, `reject`) y el disparo de tareas (`POST /console/executions/trigger`) solo son accesibles por usuarios con rol `admin` u `operator`.
- Usuarios con rol `viewer` o no autenticados reciben 403 Forbidden o 401 Unauthorized respectivamente.
- No se generan usuarios dummy en la base de datos bajo ninguna circunstancia.
- La suite completa de pruebas de Pest en `jg_dashboard` supera los tests unitarios y de integración.
