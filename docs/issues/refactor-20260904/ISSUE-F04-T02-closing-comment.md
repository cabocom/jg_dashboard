### Implementación y Verificación del Endpoint Administrativo `POST /api/v1/admin/agents` (`ISSUE-F04-T02`)

Se ha implementado el endpoint administrativo protegido para la creación y aprovisionamiento de agentes en `jg_dashboard`:

1. **Controlador `AdminAgentController`:**
   - Inyección de `AgentRegistrationService`.
   - `POST /api/v1/admin/agents`: Valida payload de entrada (`name`, `version`, `capabilities`, `metadata`, `ttl_hours`), registra al agente y emite el `bootstrap_token` criptográfico con código `201 Created`.
   - `GET /api/v1/admin/agents`: Listado de agentes registrados para supervisión administrativa (`200 OK`).
   - `POST /api/v1/admin/agents/{agent_id}/regenerate-token`: Regeneración controlada de tokens para agentes en estado `PENDING` (`200 OK` / `400 Bad Request` si está activo).

2. **Seguridad RBAC:**
   - Protegido por el stack `['jwt.auth', 'role:admin']`.
   - Peticiones anónimas devuelven `401 Unauthorized`.
   - Peticiones de roles `OPERATOR` y `VIEWER` devuelven `403 Forbidden`.

3. **Verificación Automatizada:**
   - 9 nuevas pruebas de Feature en Pest PHP (`tests/Feature/AdminAgentApiTest.php`) con cobertura de autenticación, autorización RBAC, validación de esquemas, persistencia Zero-Trust y auditoría.
   - Total de pruebas en `jg_dashboard`: **87 tests pasando (417 aserciones)** al 100%.

/close
