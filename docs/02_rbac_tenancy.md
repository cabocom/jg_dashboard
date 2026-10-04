# 02. Control de Acceso Basado en Roles (RBAC) y Seguridad (`jg_dashboard`)

## 1. Jerarquía de Roles

El modelo de autorización implementado en `app/Models/User.php` establece una jerarquía transitiva:

$$\text{ADMIN} \succ \text{OPERATOR} \succ \text{VIEWER}$$

- **ADMIN:** Hereda automáticamente todas las capacidades de `operator` y `viewer`, además de la administración exclusiva de infraestructura, provisionamiento y auditoría.
- **OPERATOR:** Puede disparar ejecuciones cognitivas y autorizar o rechazar solicitudes de aprobación humana (HITL).
- **VIEWER:** Solo tiene permisos de lectura e inspección de telemetría y tareas ejecutadas. Tiene vetado el acceso a disparos, aprobaciones y paneles administrativos.

---

## 2. Matriz Canónica de Permisos

| Permiso Interno | Acción Operativa | Roles Autorizados | Endpoints Asociados |
| :--- | :--- | :--- | :--- |
| `agents:manage` | Crear, editar o eliminar agentes; regenerar o revocar credenciales. | `admin` | `POST /api/v1/admin/agents`<br>`POST /api/v1/admin/agents/{id}/regenerate-token`<br>`POST /api/v1/admin/agents/{id}/revoke-tokens` |
| `agents:register` | Emitir tokens temporales de arranque (*bootstrap tokens*). | `admin` | `POST /api/v1/admin/agents` |
| `tasks:trigger` | Disparar una ejecución cognitiva hacia `jg_agente`. | `admin`, `operator` | `POST /api/v1/executions/trigger`<br>`POST /console/executions/trigger` |
| `hitl:approve` | Aprobar un checkpoint pausado por alto riesgo. | `admin`, `operator` | `POST /api/v1/approvals/{id}/approve`<br>`POST /console/approvals/{id}/approve` |
| `hitl:reject` | Rechazar y cancelar una ejecución pausada. | `admin`, `operator` | `POST /api/v1/approvals/{id}/reject`<br>`POST /console/approvals/{id}/reject` |
| `dashboard:view` | Acceso de lectura a telemetría, estados de agentes y tareas. | `admin`, `operator`, `viewer` | `GET /api/v1/agents`<br>`GET /api/v1/agents/{id}`<br>`GET /api/v1/executions/{agent_id}/{thread_id}` |
| `audit:view` | Consulta de pistas de auditoría inmutables (`AuditEvent`). | `admin` | Panel administrativo de auditoría |

---

## 3. Servicio Criptográfico de Identidad (`JwtService`)

La autenticación de operadores y clientes REST externos se canaliza mediante `App\Services\Auth\JwtService`:
- **Algoritmo:** Firmas HMAC-SHA256 (`HS256`) sobre cabecera y payload codificados en Base64URL sin relleno (*padding*).
- **Resolución de Clave:** Resuelve `config('auth.jwt.secret')` o `config('app.key')`. Si la clave incluye el prefijo `base64:`, la decodifica de forma segura en memoria binaria.
- **Estructura Estándar de Claims:**
  - `iss`: URL base del emisor (`config('app.url')`).
  - `sub`: Identificador numérico del usuario (`$user->id`).
  - `name`, `email`, `role`: Perfil y rol RBAC activo en el momento de la firma.
  - `iat` (Issued At) y `nbf` (Not Before): Timestamp UNIX de emisión.
  - `exp` (Expiration): Caducidad estricta (por defecto `iat + 60 minutos`).
- **Verificación en Tiempo Constante:** Emplea `hash_equals()` sobre la firma esperada y decodificada para prevenir ataques de temporización (*timing attacks*).

---

## 4. Implementación de Middlewares de Seguridad

1. **`jwt.auth` (`JwtAuthenticate.php`):**
   - Extrae el token `Bearer` de la cabecera `Authorization`.
   - Valida la integridad criptográfica y caducidad mediante `JwtService::verifyToken()`.
   - Recupera el modelo `User` asociado al claim `sub` e inyecta la identidad en el contenedor de autenticación (`auth()->setUser($user)`).
2. **`role:{role_name}` (`RequireRole.php`):**
   - Invoca `$user->hasRole($role)` evaluando la jerarquía de roles transitiva.
   - Devuelve `403 Forbidden` (`{"error": "Forbidden", "message": "Acceso denegado: rol insuficiente"}`) en caso de incumplimiento.
3. **`permission:{perm_name}` (`RequirePermission.php`):**
   - Comprueba el permiso específico contra la matriz canónica mediante `$user->hasPermission($perm)`.
4. **Acceso al Panel Web Filament (`canAccessPanel`):**
   - Restringido en `app/Models/User.php` a los roles `admin` y `operator`. Los usuarios con rol `viewer` son rechazados al intentar ingresar al panel administrativo interactivo (`/admin`).

---

## 5. Gestión de Perfil y Rotación de Credenciales de Operador

Tanto en la consola interactiva (`/console/profile`) como en la API REST protegida, los operadores disponen de endpoints de auto-gestión:
- **`GET /console/profile`:** Recupera el perfil autenticado (`id`, `name`, `email`, `role`, `created_at`).
- **`POST|PUT /console/profile`:** Actualización de nombre, correo (con validación de unicidad en base de datos) y contraseña:
  - Exige verificar la contraseña actual (`current_password`) mediante `Hash::check()`.
  - Exige confirmación de la nueva contraseña (`new_password_confirmation`) con longitud mínima de 8 caracteres.
  - Almacena el hash empleando el algoritmo seguro configurado en Laravel (Bcrypt / Argon2id).

---

## 6. Pistas de Auditoría Inmutables (`AuditEvent`)

Todas las operaciones sensibles del Control Plane se auditan de forma persistente en `dashboard_audit_events`:
- **Inmutabilidad por Diseño:** El modelo `AuditEvent` define explícitamente `const UPDATED_AT = null;` impidiendo cualquier alteración o sobreescritura posterior a su inserción.
- **Contexto Forense Completo:** Cada registro captura:
  - `user_id`: Operador humano que originó la mutación (nullable).
  - `agent_id`: Agente involucrado (nullable).
  - `ip_address`: Dirección IP del cliente emisor.
  - `user_agent`: Cliente HTTP o navegador utilizado.
  - `action_summary`: Resumen legible de la acción ejecutada.
  - `payload_json`: Datos contextuales estructurados de la solicitud.

### Catálogo Canónico de Eventos Auditados
| Tipo de Evento (`event_type`) | Momento de Emisión | Entidad Asociada |
| :--- | :--- | :--- |
| **`AGENT_CREATED`** | Aprovisionamiento inicial de agente en estado `PENDING`. | `Agent`, `User` |
| **`AGENT_ACTIVATED`** | Canje exitoso del bootstrap token por credencial permanente. | `Agent` |
| **`AGENT_BOOTSTRAP_TOKEN_REGENERATED`** | Emisión de nuevo token de aprovisionamiento para agente pendiente. | `Agent`, `User` |
| **`AGENT_BOOTSTRAP_TOKENS_REVOKED`** | Invalidación administrativa de tokens no consumidos. | `Agent`, `User` |
| **`AGENT_OFFLINE`** | Detección de inactividad por el monitor de salud (`>90s`). | `Agent` |
| **`hitl.requested`** | Recepción y registro de checkpoint de alto riesgo. | `ApprovalRequest`, `Agent` |
| **`hitl.approved`** | Autorización concedida por operador (o reconciliada localmente). | `ApprovalRequest`, `User` |
| **`hitl.rejected`** | Rechazo y cancelación de la ejecución con justificación. | `ApprovalRequest`, `User` |
| **`execution.webhook_completed`** | Notificación asíncrona de tarea finalizada en el agente. | `AgentTask`, `Agent` |

---

## 7. Navegación

- [← 01. Arquitectura del Control Plane](01_control_plane.md)
- [03. Gobernanza Human-In-The-Loop (HITL) →](03_hitl_governance.md)
