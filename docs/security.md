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

## 4. Firmado HMAC-SHA256 Inter-Servicios

Todas las peticiones del cliente `AgentHttpClient` hacia la API interna del agente incluyen:

- `X-Control-Plane`: `jg_dashboard`
- `X-Timestamp`: Unix timestamp actual (ventana de tolerancia para mitigar replay attacks).
- `X-Correlation-ID`: UUID correlacionador de traza distribuida.
- `X-Agent-ID`: Identificador del agente destino.
- `X-Signature`: `hash_hmac('sha256', $timestamp . '.' . $body, $internalSecret)`.
