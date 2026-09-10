### Resumen de Implementación — ISSUE-F02-T02

Se ha completado la definición e integración de la capa de persistencia y el modelo Eloquent para la gestión y registro de agentes en la tabla `dashboard_agents` de `jg_dashboard`.

#### 1. Componentes Desarrollados y Verificados
- **`database/migrations/2026_09_05_000001_create_dashboard_agents_table.php`:**
  - Migración nativa de base de datos para la tabla `dashboard_agents`.
  - Definición de clave primaria UUID `agent_id`, nombre, versión, estado indexado `status` (`PENDING`, `ACTIVE`, `OFFLINE`), hash de secreto permanente `permanent_secret_hash`, timestamp indexado de último latido `last_heartbeat_at`, y campos JSON estructurados para capacidades (`capabilities_json`), métricas de telemetría (`metrics_json`) y metadatos (`metadata_json`).
- **`app/Models/Agent.php`:**
  - Modelo Eloquent `Agent` con clave primaria UUID no incremental y autogeneración de UUIDs vía evento `creating`.
  - Ocultamiento de seguridad del hash del secreto permanente en `$hidden` para prevenir exposición en respuestas API o vistas web.
  - Castings automáticos a arrays para todos los campos JSON y casting datetime para `last_heartbeat_at`.
  - Scopes de consulta: `active()`, `pending()`, `offline()`.
  - Helpers de comprobación: `isActive()`, `isPending()`, `isOffline()`, y `isHealthy(int $timeoutSeconds = 90)`.
  - Métodos de transición de estado: `markAsActive(string $secretHash)`, `markAsOffline()`, y `recordHeartbeat(?array $metrics = null)`.
- **`tests/Unit/AgentModelTest.php`:**
  - 5 tests unitarios en Pest PHP validando creación con UUID, serialización segura sin exponer secretos, scopes, detección de salud por tiempo de latido y transiciones de estado.
- **Documentación Técnica:**
  - `PROJECT_CONTEXT.md` (v1.5.0) y `README.md` actualizados.
  - Conjunto documental del issue en `docs/issues/`.

#### 2. Validación y Métricas de Calidad
- **Suite Automatizada (Pest PHP):**
  - **30 pruebas pasando al 100% (113 aserciones)** en 1.33 segundos.

#### 3. Cumplimiento de Criterios de Aceptación
- [x] Tabla `dashboard_agents` creada con soporte para estados `PENDING`, `ACTIVE`, `OFFLINE` y campos de auditoría.
- [x] Modelo Eloquent con clave primaria UUID y atributos sensibles protegidos.
- [x] Scopes y helpers de ciclo de vida verificados.

/close
