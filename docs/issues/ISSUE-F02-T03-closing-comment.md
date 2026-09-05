### Resumen de Implementación — ISSUE-F02-T03

Se ha completado la definición de la capa de persistencia y el modelo Eloquent para la gestión de tokens de aprovisionamiento (`bootstrap_tokens`) en la tabla `dashboard_bootstrap_tokens` de `jg_dashboard`.

#### 1. Componentes Desarrollados y Verificados
- **`database/migrations/2026_09_05_000002_create_dashboard_bootstrap_tokens_table.php`:**
  - Migración nativa de base de datos para la tabla `dashboard_bootstrap_tokens`.
  - Definición de clave primaria `id`, índice único para `token_hash` (SHA-256), clave foránea `agent_id` referenciando `dashboard_agents.agent_id` con borrado en cascada (`cascadeOnDelete`), índice para timestamp de expiración `expires_at` (TTL 24h), bandera de consumo `consumed` (booleano indexado, por defecto `false`), `consumed_at` (nullable) y campos de auditoría `created_at`/`updated_at`.
- **`app/Models/BootstrapToken.php`:**
  - Modelo Eloquent `BootstrapToken` con ocultamiento de seguridad de `token_hash` en `$hidden` para prevenir exposiciones accidentales.
  - Castings automáticos de tipos para `expires_at`, `consumed_at` (ambos datetime) y `consumed` (boolean).
  - Relación `agent()` tipo `BelongsTo` hacia `App\Models\Agent`.
  - Helpers de inspección: `isConsumed()`, `isExpired()`, `isValid()`.
  - Transición atómica de un solo uso: `consume()` (valida vigencia e invalida re-consumos posteriores o tokens expirados).
  - Métodos criptográficos y generadores: `hashToken(string $plainToken)` y `createTokenForAgent(Agent|string $agent, int $ttlHours = 24)`.
  - Scopes de consulta: `valid()`, `consumed()`, `expired()`.
- **`app/Models/Agent.php`:**
  - Adición de la relación `bootstrapTokens()` tipo `HasMany` hacia `BootstrapToken`.
- **`tests/Unit/BootstrapTokenModelTest.php`:**
  - 7 pruebas unitarias en Pest PHP verificando: creación y relación con el agente, ocultamiento del hash al serializar, detección de expiración con TTL de 24h, mecánica de consumo de un solo uso, rechazo de consumo en tokens vencidos, scopes de consulta y generación criptográfica de tokens con coincidencia de hash persistido.
- **Entorno de Pruebas (`tests/TestCase.php`):**
  - Registro de la tabla `dashboard_bootstrap_tokens` en `setUpDatabaseSchema()` para SQLite en memoria.
- **Documentación Técnica:**
  - `PROJECT_CONTEXT.md` (v1.6.0) y `README.md` actualizados.
  - Conjunto documental del issue generado en `docs/issues/ISSUE-F02-T03-*`.

#### 2. Validación y Métricas de Calidad
- **Suite Automatizada (Pest PHP):**
  - **37 pruebas pasando al 100% (148 aserciones)** en 1.43 segundos.

#### 3. Cumplimiento de Criterios de Aceptación
- [x] Tabla `dashboard_bootstrap_tokens` creada con restricciones de integridad y clave foránea vinculada a `dashboard_agents`.
- [x] Método de expiración a 24 horas y flag de uso único verificados.
- [x] Suite de tests unitarios Pest PHP ejecutando limpiamente con 100% de éxito.

/close
