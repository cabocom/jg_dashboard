# 05. Esquema de Base de Datos del Control Plane (`jg_dashboard`)

## 1. Principio de Aislamiento de Almacenamiento

El Control Plane opera sobre su propia base de datos relacional PostgreSQL (`jg_dashboard`), completamente segregada de las bases de datos de ejecución de los agentes.

---

## 2. Tablas Principales del Sistema

### 2.1 `users`
Almacena las identidades y credenciales de los operadores humanos y administradores del sistema:
- `id` (BigInteger, PK autoincremental).
- `name` (String): Nombre descriptivo del usuario.
- `email` (String, Unique): Correo electrónico para login.
- `password` (String): Contraseña hasheada con algoritmo bcrypt / Argon2.
- `role` (String, default `viewer`): Rol RBAC (`admin`, `operator`, `viewer`).
- `created_at`, `updated_at`.

### 2.2 `dashboard_agents` (Modelo `Agent`)
Catálogo oficial de agentes de IA registrados en el ecosistema:
- `agent_id` (String 64, PK): UUID o slug unívoco del agente.
- `name` (String): Nombre descriptivo del agente.
- `version` (String 32, nullable): Versión del agente reportada durante registro o heartbeat.
- `status` (String, default `PENDING`): Estado (`PENDING`, `ACTIVE`, `OFFLINE`).
- `permanent_secret_hash` (String 255, nullable): Hash SHA-256 de la credencial simétrica permanente (uso inbound).
- `permanent_secret_encrypted` (Text, nullable): Secreto simétrico cifrado con `Crypt` (AES-256-CBC) para firmas HMAC salientes (uso outbound).
- `last_heartbeat_at` (DateTime, nullable): Marca temporal del último latido recibido.
- `capabilities_json` (JSON Array): Catálogo de herramientas (`tools`), motores (`engines`) y plugins reportados.
- `metrics_json` (JSON): Estadísticas de telemetría de salud, uso de CPU, memoria y URL interna (`api_url`).
- `metadata_json` (JSON): Configuración de red, entorno y descriptores del agente.
- **Accessor Dinámico `type`:** Infiere si el agente es `'DevOps'`, `'Conversacional'` o `'General'` evaluando metadatos o nombre.

### 2.3 `dashboard_bootstrap_tokens` (Modelo `BootstrapToken`)
Tokens efímeros de aprovisionamiento Zero-Trust:
- `id` (BigInteger, PK autoincremental).
- `agent_id` (String 64, FK con `dashboard_agents`): Agente al que se destina el token.
- `token_hash` (String 64): Hash SHA-256 del token para evitar almacenamiento en texto plano.
- `expires_at` (DateTime): Caducidad estricta fijada a 24 horas desde la emisión.
- `consumed` (Boolean, default `false`): Flag de un solo uso.
- `consumed_at` (DateTime, nullable): Timestamp del canje efectivo.

### 2.4 `dashboard_approval_requests` (Modelo `ApprovalRequest`)
Registro central de operaciones en espera de aprobación humana (HITL):
- `id` (BigInteger, PK autoincremental).
- `thread_id` (String 128, Index): Hilo o sesión conversacional en `jg_agente`.
- `agent_id` (String 64, Index, FK con `dashboard_agents`): Agente responsable de la tarea.
- `task_id` (String 128, nullable): Identificador de la ejecución asociada.
- `status` (String, default `PENDING`): Estado (`PENDING`, `PROCESSING`, `APPROVED`, `REJECTED`, `TIMEOUT`, `CANCELLED`).
- `risk_level` (String, default `HIGH`): Nivel de riesgo (`LOW`, `MEDIUM`, `HIGH`, `CRITICAL`).
- `proposed_plan_json` (JSON): Estructura detallada de pasos, comandos previstos y herramientas (`tools_involved`).
- `compensation_commands_json` (JSON): Acciones de mitigación o rollback planificadas.
- `justification` (Text, nullable): Justificación técnica registrada por el operador.
- `user_id` (BigInteger, FK con `users`, nullable): Operador que resolvió la solicitud.
- `resolved_at` (DateTime, nullable): Timestamp de resolución.

### 2.5 `dashboard_audit_events` (Modelo `AuditEvent`)
Pista inmutable de auditoría para trazabilidad de gobernanza y accesos (`const UPDATED_AT = null;`):
- `id` (BigInteger, PK autoincremental).
- `event_type` (String 64): Categoría del evento (`AGENT_CREATED`, `AGENT_ACTIVATED`, `hitl.requested`, `hitl.approved`, etc.).
- `action_summary` (Text): Descripción legible de la acción.
- `payload_json` (JSON): Datos contextuales completos de la solicitud.
- `user_id` (BigInteger, nullable): Identificador del operador humano ejecutor.
- `agent_id` (String 64, nullable): Agente relacionado.
- `ip_address` (String 45, nullable): Dirección IP de origen.
- `user_agent` (Text, nullable): Cliente HTTP o navegador emisor.
- `created_at` (DateTime): Marca temporal inmutable.

### 2.6 `agent_tasks` (Modelo `AgentTask`)
Historial de ejecuciones y tareas disparadas desde el plano de control:
- `task_id` (String 128, PK): Identificador único de la tarea / ejecución.
- `thread_id` (String 128, Index, nullable): Identificador de la sesión de ejecución.
- `agent_id` (String 64, Index, nullable): Agente asignado.
- `status` (String): Estado de ejecución (`QUEUED`, `PROCESSING`, `WAITING_APPROVAL`, `COMPLETED`, `FAILED`, `CANCELLED`).
- `payload_json` (JSON): Prompt de entrada, parámetros y metadatos del usuario emisor.
- `result_json` (JSON): Salida sintetizada, artefactos devueltos y evaluación de riesgo.
- `trace_history_json` (JSON, nullable): Historial completo de pasos cognitivos intermedios.
- `created_at`, `started_at`, `completed_at` (DateTime, nullable).
- **Accessors:**
  - `duration`: Formateado legible (ej. `2h 15m 30s` o `1.45s`).
  - `prompt`: Extracción directa de la instrucción desde `payload_json`.
  - `user_name`: Nombre o correo del usuario que ordenó la tarea.

### 2.7 `circuit_breaker_state` (Modelo `CircuitBreakerState`)
Gestión del estado de aislamiento de fallos para servicios dependientes:
- `target` (String, PK): Clave del servicio supervisado (ej. `k8s_api`, `agent_devops`).
- `is_tripped` (Boolean, default `false`): `true` si el interruptor está abierto (corte de tráfico).
- `tripped_reason` (Text, nullable): Causa diagnóstica de la apertura del circuito.
- `failure_history_json` (JSON Array): Historial cronológico de errores y latencias observadas.
- `updated_at` (DateTime): Momento del último cambio de estado.

### 2.8 `infra_topology` (Modelo `InfraTopology`)
Snapshots del mapa topológico de infraestructura para visualización en tiempo real:
- `target` (String, PK): Identificador del snapshot (ej. `'current'`).
- `nodes_json` (JSON Array): Nodos del grafo (host SRE, contenedores, bases de datos y agentes).
- `edges_json` (JSON Array): Conexiones dirigidas (`database_connection`, `contains`, `rest_api`).
- `updated_at` (DateTime).
- **Accessors Calculados:** `nodes_count`, `edges_count`, `healthy_count`, `down_count`, `warning_count`.

### 2.9 `notified_incidents` (Modelo `NotifiedIncident`)
Deduplicación de alertas operacionales hacia operadores y canales de guardia:
- `incident_id` (String, PK): Hash o identificador unívoco del incidente de observabilidad.
- `notified_at` (DateTime): Timestamp en que se envió la notificación inicial para evitar spam de alertas.

---

## 3. Navegación

- [← 04. Protocolo Criptográfico Inter-Servicio](04_hmac_handshake.md)
- [06. Observabilidad y Resiliencia →](06_observability_resilience.md)
