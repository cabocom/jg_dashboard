# Modelo de Seguridad y RBAC: jg_dashboard

---

## 1. Visión General de Seguridad

La seguridad en `jg_dashboard` descansa en tres pilares:
1. **Autenticación y Autorización de Usuarios (JWT + RBAC):** Control de acceso estricto para administradores, operadores y visores.
2. **Aprovisionamiento Zero-Trust de Agentes:** Registro inicial mediante `bootstrap_token` efímero (TTL 24h, un solo uso) canjeado por un secreto permanente.
3. **Firmado Criptográfico Inter-Servicio (HMAC-SHA256):** Toda llamada saliente hacia `jg_agente` se firma digitalmente para evitar ataques de repetición o falsificación de origen.

---

## 2. Matriz de Control de Acceso Basado en Roles (RBAC)

La jerarquía de roles satisface: `ADMIN > OPERATOR > VIEWER`.

| Funcionalidad / Endpoint | ADMIN | OPERATOR | VIEWER |
| :--- | :---: | :---: | :---: |
| **Login / Logout / Auth Me** | Sí | Sí | Sí |
| **Acceso al Panel Web Filament** | Sí | Sí | No |
| **Aprovisionamiento de Agentes (`/admin/agents`)** | Sí | No | No |
| **Regeneración / Revocación de Tokens de Agente** | Sí | No | No |
| **Listar Agentes y Estados (`GET /agents`)** | Sí | Sí | Sí |
| **Inspección de Capacidades (`GET /agents/{id}/capabilities`)** | Sí | Sí | Sí |
| **Consulta de Estado de Ejecución (`GET /executions/{id}/{th}`)** | Sí | Sí | Sí |
| **Disparo de Ejecuciones (`POST /executions/trigger`)** | Sí | Sí | No |
| **Listar Aprobaciones Pendientes (`GET /approvals/pending`)** | Sí | Sí | No |
| **Aprobar Solicitud HITL (`POST /approvals/{id}/approve`)** | Sí | Sí | No |
| **Rechazar Solicitud HITL (`POST /approvals/{id}/reject`)** | Sí | Sí | No |

---

## 3. Aprovisionamiento Zero-Trust de Agentes

```text
  Administrador                 Dashboard                  jg_agente
       │                            │                          │
       │── POST /admin/agents ─────►│                          │
       │◄─ Retorna bootstrap_token ─│                          │
       │   (TTL 24h, PENDING)       │                          │
       │                            │                          │
       │   (Inyección en Agente)    │                          │
       ─────────────────────────────┼─────────────────────────►│
                                    │                          │
                                    │◄─ POST /agents/register ─│
                                    │   Bearer bootstrap_token │
                                    │                          │
                                    │── Valida token (<24h)    │
                                    │── Invalida token (usado) │
                                    │── Genera permanent_secret│
                                    │── Estado = ACTIVE        │
                                    │                          │
                                    │── Retorna secret ───────►│
                                    │                          │ (Persiste secreto local)
```

1. **Tokens de Un Solo Uso:** Una vez que el agente consume el token, se marca como `used = true` y queda inhabilitado de por vida.
2. **Expiración Estricta (24h):** Si un token no se consume en 24 horas desde su creación, es rechazado con `401 Unauthorized`.
3. **Revocación Administrativa:** Un administrador puede invalidar tokens pendientes en cualquier momento (`/revoke-tokens`).

---

## 4. Firmado Canónico HMAC-SHA256 Inter-Servicios (GLOBAL-001)

Todas las peticiones del cliente `AgentHttpClient` hacia la API interna del agente implementan el contrato canónico estricto de autenticación e integridad:

### 4.1 Cadena Canónica
```text
canonical_string = METHOD\nPATH\nTIMESTAMP\nSHA256(RAW_BODY)
```
- **`METHOD`**: Método HTTP en mayúsculas (`POST`, `GET`).
- **`PATH`**: Ruta del recurso (ej. `/executions/trigger`).
- **`TIMESTAMP`**: Unix timestamp actual en segundos.
- **`SHA256(RAW_BODY)`**: Hash SHA-256 de los bytes exactos enviados por el cable HTTP (o hash de cadena vacía `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855` en peticiones sin cuerpo).

### 4.2 Cabeceras Emitidas
- `X-Signature`: `hash_hmac('sha256', $canonicalString, $internalSecret)` en formato hexadecimal.
- `X-Timestamp`: Unix timestamp utilizado para calcular la firma.
- `X-Agent-ID`: Identificador del agente destino.

> [!IMPORTANT]
> **Eliminación del Secreto en Texto Plano:**
> La cabecera `X-Control-Plane-Secret` ha sido completamente suprimida de todas las comunicaciones de red para prevenir fugas de secretos en tránsito o logs de red.

### 4.3 Ventana Anti-Replay
El agente valida que el `X-Timestamp` se encuentre dentro de un margen de tolerancia estricto de $\pm 300$ segundos (5 minutos) respecto a la hora del sistema, descartando inmediatamente con HTTP `401 Unauthorized` cualquier petición con timestamps fuera de dicha ventana.
