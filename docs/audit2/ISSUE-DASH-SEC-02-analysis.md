# Análisis Técnico: [DASH-SEC-02] Endpoint de Heartbeat sin Firma HMAC Transmite Secreto en Plaintext

## 1. Causa Raíz (CWE-319)
En `jg_dashboard/app/Http/Controllers/Api/AgentHeartbeatController.php`, la verificación de identidad del agente se realizaba leyendo el secreto en claro:
```php
$secret = $request->bearerToken()
    ?: ($request->header('X-Agent-Secret') ?: $request->input('permanent_secret'));
```
A su vez, en `jg_agente/app/core/heartbeat.py`, cada ciclo de latido transmitía las cabeceras:
```python
headers = {
    "Authorization": f"Bearer {permanent_secret}",
    "X-Agent-ID": agent_id,
    "X-Agent-Secret": permanent_secret,
    ...
}
```
Esto violaba el principio de defensa en profundidad y el contrato canónico HMAC-SHA256 implementado en el resto del ecosistema para proteger las ejecuciones del Control Plane.

## 2. Puntos Clave de la Arquitectura de Seguridad
1. **Contrato Canónico HMAC-SHA256:**
   ```
   canonical_string = METHOD\nPATH\nTIMESTAMP\nSHA256(RAW_BODY)
   signature = HMAC-SHA256(canonical_string, secret)
   ```
2. **Protección Anti-Replay:**
   Tolerancia temporal máxima de 300 segundos (`MAX_TIMESTAMP_SKEW_SECONDS = 300`), sincronizada entre Python y PHP.
3. **Desacople de Credenciales:**
   El secreto criptográfico reside en la base de datos (encriptado mediante `APP_KEY` con AES-256-CBC) y en la memoria local del agente (`.agent_credentials.json`). Nunca más viaja por el canal de red durante los heartbeats.

## 3. Solución Implementada

### A. Middleware `VerifyHmacSignature` (`jg_dashboard`)
- Nuevo middleware en `app/Http/Middleware/VerifyHmacSignature.php` registrado con el alias `agent.hmac`.
- Valida obligatoriamente `X-Signature`, `X-Timestamp`, y `X-Agent-ID`.
- Valida la ventana temporal anti-replay (máximo 300s).
- Comprueba que el agente exista y no esté en estado `PENDING`.
- Resuelve el secreto del agente mediante `Agent::resolveHmacSecret()`.
- Reconstruye la cadena canónica normalizada y valida la firma con `hash_equals` (tiempo constante).
- Inyecta el modelo `Agent` autenticado en los atributos del request (`authenticated_agent`).

### B. Controlador y Servicio (`jg_dashboard`)
- `AgentHeartbeatController` delega la autenticación en el middleware y consume el agente pre-validado sin recibir ni comprobar secretos en texto plano.
- `AgentHeartbeatService::recordHeartbeat()` permite asentar el latido y métricas directamente en el modelo de agente ya autenticado.

### C. Emisor de Heartbeat (`jg_agente`)
- `HeartbeatService.send_heartbeat()` firma la petición serializada con `compute_canonical_string` y `compute_hmac_signature`.
- Envía las cabeceras `X-Agent-ID`, `X-Timestamp`, `X-Signature`, eliminando completamente `Authorization: Bearer` y `X-Agent-Secret`.
- Incluye soporte de firma adaptativa si se ejecuta fallback a `/api/agents/heartbeat`.
