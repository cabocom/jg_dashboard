### Implementación y Verificación del Endpoint de Registro `POST /api/v1/agents/register` (`ISSUE-F04-T03`)

Se ha implementado con éxito el endpoint de consumo de aprovisionamiento en `jg_dashboard`:

1. **Servicio y Controlador de Activación:**
   - Implementado `consumeBootstrapTokenAndActivate` en `AgentRegistrationService` y expuesto vía `AgentRegistrationController@register`.
   - Soporta presentación de token mediante encabezado `Authorization: Bearer <bootstrap_token>` o en el cuerpo de la petición JSON `{ "bootstrap_token": "..." }`.
   - Valida la vigencia y no-consumo del token.
   - En una transacción atómica: marca el token como consumido (`consumed = true`), genera la credencial permanente `sec_*` criptográfica (48 caracteres), almacena únicamente su hash SHA-256 en BD, actualiza capacidades/metadatos, transiciona el estado del agente a `ACTIVE` y registra el evento de auditoría `AGENT_ACTIVATED`.

2. **Respuestas HTTP Semánticas:**
   - `200 OK`: Agente activado y provisto de su `permanent_secret`.
   - `401 Unauthorized`: Token no provisto en la petición.
   - `404 Not Found`: Token inexistente o inválido.
   - `409 Conflict`: Token ya consumido previamente (garantía de un solo uso).
   - `410 Gone`: Token expirado (> 24h).

3. **Verificación Automatizada:**
   - 4 nuevas pruebas unitarias en `AgentRegistrationServiceTest`.
   - 7 nuevas pruebas de integración feature en `AgentRegistrationApiTest`.
   - Total de pruebas en `jg_dashboard`: **98 tests pasando (474 aserciones)** al 100%.

/close
