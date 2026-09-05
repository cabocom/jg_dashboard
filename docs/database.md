# Esquema y Modelos de Base de Datos: jg_dashboard

---

## 1. Visión General del Almacenamiento

`jg_dashboard` utiliza su propia base de datos relacional PostgreSQL (por defecto en el puerto `5434` en entornos locales aislados). No comparte base de datos con ningún agente `jg_agente`.

---

## 2. Tablas Principales y Modelos Eloquent

### 2.1 `dashboard_users` (Modelo `App\Models\User`)
Almacena las credenciales y perfiles de los usuarios que acceden al Control Plane:
- `id`: BigInteger, clave primaria autoincremental.
- `name`: Varchar(255), nombre del usuario.
- `email`: Varchar(255), único.
- `password`: Varchar(255), hash seguro Bcrypt/Argon2.
- `role`: Varchar(32), valores: `admin`, `operator`, `viewer`.
- `created_at`, `updated_at`: Timestamps.

### 2.2 `dashboard_agents` (Modelo `App\Models\Agent`)
Registro de los agentes cognitivos aprovisionados en el ecosistema:
- `agent_id`: Varchar(64), clave primaria (UUID o slug único).
- `name`: Varchar(255), nombre identificativo del agente.
- `status`: Varchar(32), valores: `PENDING`, `ACTIVE`, `OFFLINE`.
- `version`: Varchar(32), versión del agente reportada en heartbeat.
- `permanent_secret_hash`: Varchar(255), hash SHA-256 de la credencial del agente.
- `last_heartbeat_at`: Timestamp con zona horaria, actualizado periódicamente.
- `metadata_json`: JSON, configuración (`api_url`, entorno, etc.).
- `capabilities_json`: JSON, catálogo de herramientas, engines y plugins activos.
- `metrics_json`: JSON, últimas métricas reportadas (CPU, memoria, latencia).

### 2.3 `dashboard_bootstrap_tokens` (Modelo `App\Models\BootstrapToken`)
Tokens temporales de aprovisionamiento de un solo uso:
- `id`: BigInteger, clave primaria.
- `agent_id`: Varchar(64), clave foránea referenciando `dashboard_agents.agent_id`.
- `token_hash`: Varchar(255), hash SHA-256 del token entregado.
- `expires_at`: Timestamp, caducidad estricta (creación + 24h).
- `used`: Boolean, flag de un solo uso (pasa a `true` al registrarse).
- `used_at`: Timestamp, momento en que fue consumido.
- `revoked`: Boolean, flag de revocación administrativa manual.

### 2.4 `dashboard_approval_requests` (Modelo `App\Models\ApprovalRequest`)
Solicitudes de aprobación humana (HITL) emitidas cuando un agente alcanza un plan de riesgo:
- `id`: BigInteger, clave primaria.
- `thread_id`: Varchar(128), identificador único de la conversación / ejecución.
- `agent_id`: Varchar(64), clave foránea opcional referenciando `dashboard_agents`.
- `task_id`: Varchar(128), identificador de la tarea o ejecución.
- `status`: Varchar(32), valores: `PENDING`, `APPROVED`, `REJECTED`, `TIMEOUT`, `CANCELLED`.
- `risk_level`: Varchar(32), valores: `LOW`, `MEDIUM`, `HIGH`, `CRITICAL`.
- `proposed_plan_json`: JSON, estructura del plan y herramientas involucradas (`tools_involved`).
- `compensation_commands_json`: JSON, comandos de rollback o mitigación propuestos.
- `user_id`: BigInteger, clave foránea referenciando al usuario que resolvió la solicitud.
- `justification`: Text, justificación u observaciones del operador (obligatoria en rechazo).
- `resolved_at`: Timestamp, momento de la resolución.

### 2.5 `dashboard_audit_events` (Modelo `App\Models\AuditEvent`)
Registro inmutable de auditoría forense del plano de control:
- `id`: BigInteger, clave primaria.
- `event_type`: Varchar(64), identificador de acción (`hitl.requested`, `hitl.approved`, `hitl.rejected`, `agent.registered`, etc.).
- `action_summary`: Varchar(255), resumen descriptivo.
- `payload_json`: JSON, metadatos estructurados del evento.
- `user_id`: BigInteger, usuario que ejecutó la acción (nullable).
- `agent_id`: Varchar(64), agente involucrado (nullable).
- `ip_address`: Varchar(45), dirección IP origen.
- `user_agent`: Text, cliente HTTP.
- `created_at`: Timestamp inmutable.
