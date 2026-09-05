# Análisis Técnico: ISSUE-F02-T02 — Modelos de base de datos para agentes registrados

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F02-T02`  
> **Fase:** FASE 02 — Persistencia y Modelos de Base de Datos  
> **Prioridad:** Critical  
> **Complejidad Estimada:** S  
> **Dependencias:** ISSUE-F02-T01  

---

## 1. Objetivo del Issue

Definir la capa de persistencia y el modelo Eloquent para la gestión del registro y ciclo de vida de agentes autónomos (`jg_agente`) en el Control Plane (`jg_dashboard`). Diseñar la tabla `dashboard_agents` con identificador UUID, control de estados (`PENDING`, `ACTIVE`, `OFFLINE`), resguardo seguro del hash del secreto permanente, telemetría de latido (`last_heartbeat_at`) y capacidades en formato JSON.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Migración de Base de Datos (`database/migrations/2026_09_05_000001_create_dashboard_agents_table.php`):**
   - Tabla: `dashboard_agents`.
   - Campos principales:
     - `agent_id`: UUID (clave primaria no incremental).
     - `name`: Nombre descriptivo del agente (ej. "Agente-DevOps-Cluster-A").
     - `version`: Versión declarada por el agente (nullable).
     - `status`: Estado del ciclo de vida (`PENDING`, `ACTIVE`, `OFFLINE`), indexado, por defecto `PENDING`.
     - `permanent_secret_hash`: Hash criptográfico del secreto permanente emitido tras el bootstrap (nullable).
     - `last_heartbeat_at`: Timestamp del último latido de vida recibido, indexado (nullable).
     - `capabilities_json`: Catálogo de herramientas y motores serializado en JSON (nullable).
     - `metrics_json`: Últimas métricas de CPU/memoria reportadas (nullable).
     - `metadata_json`: Metadatos contextuales (entorno, host, etc.).
     - `timestamps`: `created_at` y `updated_at`.
2. **Modelo Eloquent (`App\Models\Agent`):**
   - Clave primaria `agent_id`, tipo `string`, `$incrementing = false`.
   - Generación automática de UUID si no se provee explícitamente en el evento `creating`.
   - `$hidden`: `permanent_secret_hash` protegido para evitar fugas accidentales en serialización JSON/Array.
   - `$casts`: `capabilities_json`, `metrics_json`, `metadata_json` como `array`, y `last_heartbeat_at` como `datetime`.
   - Scopes de consulta: `scopeActive`, `scopePending`, `scopeOffline`.
   - Helpers de estado y salud: `isActive()`, `isPending()`, `isOffline()`, `isHealthy(int $timeoutSeconds = 90)`.
   - Métodos de transición: `markAsActive(string $secretHash)`, `markAsOffline()`, `recordHeartbeat(?array $metrics = null)`.
3. **Criterios de Aceptación:**
   - Tabla `dashboard_agents` creada con soporte completo para los estados requeridos.
   - Tests unitarios en Pest verificando UUID, casts, scopes, transiciones y seguridad de campos ocultos.

---

## 3. Decisiones Técnicas y Diseño

- **Generación de UUID en Boot:** El hook de Eloquent asigna automáticamente un `Str::uuid()` cuando no se suministra un UUID preexistente.
- **Protección de Credenciales (Security First):** `permanent_secret_hash` se declara en `$hidden` para que ninguna consulta que devuelva agentes al frontend exponga el hash del secreto.
- **Detección de Salud Activa (`isHealthy`):** Si un agente en estado `ACTIVE` no emite heartbeat en más de 90 segundos, `isHealthy(90)` retorna `false`, permitiendo al Dashboard alertar visualmente de desconexiones.

---

## 4. Plan de Verificación

- Ejecución de la suite Pest PHP incluyendo `Tests\Unit\AgentModelTest`:
  - `test_agent_creation_and_uuid_generation`: Valida generación de UUID y defaults.
  - `test_agent_hidden_attributes_in_serialization`: Valida exclusión del hash.
  - `test_agent_scopes`: Valida filtrado por estado.
  - `test_agent_health_check_recency`: Valida lógica de timeout de heartbeat.
  - `test_agent_state_transitions`: Valida ciclo de vida y actualización de métricas.
  ```bash
  ./scripts/check.sh
  ```
