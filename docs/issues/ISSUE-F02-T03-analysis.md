# Análisis Técnico: ISSUE-F02-T03 — Modelos de base de datos para tokens de aprovisionamiento (bootstrap_tokens)

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F02-T03`  
> **Fase:** FASE 02 — Persistencia y Modelos de Base de Datos  
> **Prioridad:** Critical  
> **Complejidad Estimada:** S  
> **Dependencias:** ISSUE-F02-T02  

---

## 1. Objetivo del Issue

Definir la capa de persistencia y el modelo Eloquent para la gestión de tokens de aprovisionamiento inicial (`bootstrap_tokens`) en el Control Plane (`jg_dashboard`). Diseñar la tabla `dashboard_bootstrap_tokens` con integridad referencial hacia `dashboard_agents`, control de tiempo de vida (TTL 24 horas), hash criptográfico seguro del token, y bandera atómica de uso único (`consumed`) para garantizar el principio Zero-Trust en el registro de nuevos agentes.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Migración de Base de Datos (`database/migrations/2026_09_05_000002_create_dashboard_bootstrap_tokens_table.php`):**
   - Tabla: `dashboard_bootstrap_tokens`.
   - Campos principales:
     - `id`: Clave primaria auto-incremental.
     - `token_hash`: Hash criptográfico SHA-256 único de 64 caracteres (`unique`, `index`).
     - `agent_id`: Clave foránea referenciando `dashboard_agents.agent_id` con borrado en cascada (`cascadeOnDelete`).
     - `expires_at`: Timestamp de caducidad con índice para consultas eficientes (TTL 24h por defecto).
     - `consumed`: Booleano indicando si el token ya ha sido canjeado (default: `false`, indexado).
     - `consumed_at`: Timestamp del momento en que se consumió el token (nullable).
     - `timestamps`: `created_at` y `updated_at`.

2. **Modelo Eloquent (`App\Models\BootstrapToken`):**
   - Tabla asociada: `dashboard_bootstrap_tokens`.
   - `$hidden`: Ocultamiento de `token_hash` para evitar fugas en serialización.
   - `$casts`: `expires_at` y `consumed_at` como `datetime`, `consumed` como `boolean`.
   - Relaciones:
     - `agent()`: BelongsTo hacia `App\Models\Agent`.
     - `Agent::bootstrapTokens()`: HasMany hacia `App\Models\BootstrapToken`.
   - Helpers de ciclo de vida:
     - `isConsumed(): bool`: Evalúa si ya fue utilizado.
     - `isExpired(): bool`: Evalúa si ha vencido la ventana de tiempo.
     - `isValid(): bool`: Verifica que no esté consumido ni expirado.
     - `consume(): bool`: Marca el token como consumido de forma atómica y rechaza consumos duplicados.
   - Generador y funciones criptográficas:
     - `hashToken(string $plainToken): string`: Genera hash SHA-256.
     - `createTokenForAgent(Agent|string $agent, int $ttlHours = 24): array`: Emite un token criptográfico seguro, persiste su hash con TTL y devuelve el token en texto plano y la instancia del modelo.
   - Scopes de consulta:
     - `scopeValid`: Filtra tokens vigentes y sin consumir.
     - `scopeConsumed`: Filtra tokens ya canjeados.
     - `scopeExpired`: Filtra tokens vencidos sin consumir.

3. **Criterios de Aceptación:**
   - Tabla `dashboard_bootstrap_tokens` creada con restricciones de integridad y clave foránea.
   - Verificación de TTL de 24 horas y mecanismo de uso único estricto (no reutilizable).
   - Suite de pruebas unitarias en Pest PHP con 100% de éxito.

---

## 3. Decisiones Técnicas y de Seguridad

- **Almacenamiento Zero-Trust (Nunca en Texto Plano):**
  El token nunca se almacena en texto plano en la base de datos. Solo se persiste el hash SHA-256 (`token_hash`), garantizando que incluso ante una fuga de base de datos no se puedan interceptar registros de agentes en vuelo.
- **Uso Único y Protección contra Replay:**
  El método `consume()` comprueba primero `isValid()`. Si el token ya fue consumido o ha expirado, la operación es abortada retornando `false`, impidiendo ataques de repetición.
- **Integridad Referencial:**
  La clave foránea `agent_id` asegura que no existan tokens huérfanos; si un registro de agente pendiente es eliminado, sus tokens asociados son removidos automáticamente (`cascadeOnDelete`).

---

## 4. Plan de Verificación

- Ejecución de la suite Pest PHP incluyendo `Tests\Unit\BootstrapTokenModelTest`:
  - `bootstrap token creation and relationship with agent`: Valida defaults y relaciones bidireccionales con `Agent`.
  - `bootstrap token hides token_hash when serialized`: Valida protección contra exposición accidental.
  - `bootstrap token expiration detection with 24h TTL`: Valida umbrales de expiración.
  - `bootstrap token one-time consumption mechanics`: Valida transición a consumido y prevención de doble consumo.
  - `expired token cannot be consumed`: Valida rechazo de consumo en tokens vencidos.
  - `bootstrap token query scopes`: Valida scopes `valid()`, `consumed()`, `expired()`.
  - `createTokenForAgent helper`: Valida generación criptográfica de texto plano y coincidencia con hash persistido.
- Ejecución completa de `./scripts/check.sh`.
