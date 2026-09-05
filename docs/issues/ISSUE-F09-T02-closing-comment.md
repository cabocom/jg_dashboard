Se ha completado la implementación de **[ISSUE-F09-T02] Vista UI: Panel de agentes registrados y monitor de salud** en `jg_dashboard`.

### Resumen de Implementación
1. **Panel Visual de Agentes en `/console`:**
   - Barra de herramientas con búsqueda reactiva en vivo por nombre o ID de agente y filtro desplegable por estado operativo (`ALL`, `ACTIVE`, `OFFLINE`, `PENDING`).
   - Conmutador de vistas dual: Vista de **Tarjetas Grid** (con bordes de estado, telemetría de CPU, RAM y latencia) y Vista de **Tabla de Datos**.
2. **Modal de Aprovisionamiento con Bootstrap Token:**
   - Interfaz modal para registrar un nuevo agente en el Control Plane (`name`, `version`, `ttl_hours`).
   - Endpoint web dedicado `POST /console/agents` conectado con `AgentRegistrationService::createAgent()`.
   - Visualización segura del token en un solo uso con advertencia de confidencialidad y botón de copiado rápido al portapapeles.
3. **Control de Auditoría y Persistencia:**
   - Emisión del evento de auditoría `AGENT_CREATED` con registro del usuario y metadatos.
   - Hash SHA-256 en base de datos para el token de un solo uso con TTL estricto de 24 horas.
4. **Verificación y Pruebas:**
   - Pruebas feature automatizadas en `tests/Feature/AgentPanelWebTest.php` validando renderizado visual, aprovisionamiento, hashing y validaciones.
   - 100% de la suite de pruebas del proyecto pasando exitosamente en `./scripts/check.sh`.
5. **Documentación:**
   - Actualización de `README.md`, `PROJECT_CONTEXT.md` (v3.17.0) y cuarteto documental en `docs/issues/ISSUE-F09-T02-*`.

/close
