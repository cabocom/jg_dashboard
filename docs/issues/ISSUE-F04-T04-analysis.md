# Análisis Técnico: ISSUE-F04-T04 — Validación estricta de caducidad (TTL 24h) y revocación de tokens

> **Estado del Documento:** FINALIZADO  
> **Issue ID:** `ISSUE-F04-T04`  
> **Fase:** FASE 04 — Sistema de Registro de Agentes  
> **Prioridad:** High  
> **Complejidad Estimada:** S  
> **Dependencias:** ISSUE-F04-T03  

---

## 1. Objetivo del Issue

Implementar la validación estricta de caducidad y revocación de tokens de aprovisionamiento (`bootstrap_tokens`) en `jg_dashboard`. Asegurar que cualquier intento de registro que utilice un token caducado (>24h desde su creación), ya consumido o explícitamente revocado sea rechazado inmediatamente con código HTTP `401 Unauthorized`, bloqueando el aprovisionamiento del agente.

---

## 2. Requerimientos y Criterios de Aceptación

1. **Validación Estricta de TTL (24 Horas):**
   - Comprobación del campo `expires_at` y límite absoluto estricto de 24 horas desde `created_at`.
   - Si la petición supera dicha ventana temporal, el token se clasifica como `TOKEN_EXPIRED`.
   - La respuesta del endpoint `POST /api/v1/agents/register` debe ser `401 Unauthorized` (`Bootstrap token expirado (>24h)`).

2. **Garantía de Uso Único (Anti-Replay):**
   - Comprobación del flag `consumed`.
   - Bloqueo por fila a nivel de base de datos (`lockForUpdate()`) para evitar carreras de doble consumo concurrente.
   - Si el token ya fue consumido, la respuesta del endpoint `POST /api/v1/agents/register` debe ser `401 Unauthorized` (`Bootstrap token ya consumido`).

3. **Capacidad de Revocación Administrativa:**
   - Método `revokeBootstrapTokens` en `AgentRegistrationService`.
   - Endpoint administrativo `POST /api/v1/admin/agents/{agent_id}/revoke-tokens` protegido por `role:admin`.
   - Registro de auditoría `AGENT_BOOTSTRAP_TOKENS_REVOKED` en `dashboard_audit_events`.

4. **Criterios de Aceptación:**
   - Petición con token expirado (>24h) o ya consumido devuelve 401 Unauthorized impidiendo el registro del agente.
   - Suite de pruebas completa pasando al 100% en Pest PHP.

---

## 3. Decisiones Técnicas y de Seguridad

- **Consistencia de Seguridad (RFC 6750 / Bearer Spec):** Cualquier fallo de credencial temporal (expiración, consumo previo o token no reconocido) genera una respuesta uniforme `401 Unauthorized`, impidiendo que atacantes obtengan pistas sobre la existencia o estado previo de un token de aprovisionamiento.
- **Protección contra Carreras (Race Conditions):** El uso de `lockForUpdate()` dentro de la transacción de consumo garantiza que peticiones paralelas con el mismo token no puedan resultar en múltiples activaciones.
- **Revocación Inmediata:** Los administradores pueden invalidar proactivamente tokens pendientes antes de que transcurran las 24 horas.
