### Implementación y Verificación de Caducidad Estricta y Revocación de Tokens (`ISSUE-F04-T04`)

Se ha culminado la Fase 04 con la implementación de las validaciones estrictas y revocación de tokens de aprovisionamiento en `jg_dashboard`:

1. **Validación Estricta y Rechazo con 401:**
   - En el modelo `BootstrapToken`, `isExpired()` comprueba el campo `expires_at` y garantiza un tope absoluto de 24 horas a partir de `created_at`.
   - Bloqueo transaccional por fila (`lockForUpdate()`) en `AgentRegistrationService::consumeBootstrapTokenAndActivate` para eliminar condiciones de carrera.
   - Peticiones con token expirado (>24h), ya consumido (anti-replay) o inexistente devuelven de forma estandarizada y segura código HTTP `401 Unauthorized`.

2. **Revocación Administrativa:**
   - Incorporado el método `revokeBootstrapTokens` en `AgentRegistrationService` con auditoría `AGENT_BOOTSTRAP_TOKENS_REVOKED`.
   - Creado el endpoint administrativo `POST /api/v1/admin/agents/{agent_id}/revoke-tokens` protegido con middleware `role:admin`.

3. **Verificación Automatizada:**
   - Pruebas unitarias y de feature actualizadas y añadidas para validar el rechazo 401 en expiración, doble consumo, revocación y autorización de roles.
   - Total de pruebas en `jg_dashboard`: **102 tests pasando (488 aserciones)** al 100%.

/close
