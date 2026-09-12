### Implementación y Verificación del Endpoint de Listado de Agentes `GET /api/v1/agents` (`ISSUE-F05-T03`)

Se ha culminado la Fase 05 con la implementación del endpoint de consulta y observabilidad de agentes en `jg_dashboard`:

1. **Controlador `AgentController`:**
   - `GET /api/v1/agents`: Devuelve la lista paginada de agentes registrados (`data`), el resumen de métricas agregadas globales (`summary`: total, activos, offline, pendientes) y los metadatos de paginación (`meta`).
   - Soporta filtros por estado (`?status=ACTIVE|OFFLINE|PENDING`) y búsqueda por nombre o identificador (`?search=...`).
   - Evalúa el estado de salud en tiempo real (`is_healthy`) en función de la recencia del último latido (<90 segundos).
   - `GET /api/v1/agents/{agent_id}`: Consulta individual detallada de un agente con sus metadatos y última telemetría reportada.
   - Protección absoluta contra fugas de credenciales (las contraseñas y hashes de secretos permanecen ocultos).

2. **Seguridad y Acceso RBAC:**
   - Protegido por `jwt.auth`, permitiendo acceso a todos los roles autenticados (`VIEWER`, `OPERATOR`, `ADMIN`).
   - Peticiones anónimas son rechazadas con `401 Unauthorized`.

3. **Verificación Automatizada:**
   - 8 nuevas pruebas de feature en `AgentListApiTest`.
   - Total de pruebas en `jg_dashboard`: **127 tests pasando (608 aserciones)** al 100%.

/close
