# Cierre de Issue: [DASH-SEC-02] Endpoint de Heartbeat sin Firma HMAC Transmite Secreto en Plaintext

## Resolución
Se ha resuelto satisfactoriamente la vulnerabilidad de transmisión de secretos en texto plano en el endpoint de heartbeat (Work item [#48](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/48)):

1. **Creación del Middleware de Firma HMAC (`VerifyHmacSignature`):**
   - Implementado en `jg_dashboard/app/Http/Middleware/VerifyHmacSignature.php` y registrado bajo el alias `agent.hmac`.
   - Valida cabeceras `X-Signature`, `X-Timestamp`, `X-Agent-ID`.
   - Aplica validación anti-replay con ventana de 300 segundos y comparación de firmas en tiempo constante (`hash_equals`).
   - Protege los endpoints `POST /api/v1/agents/heartbeat` y `POST /api/agents/heartbeat`.

2. **Refactorización de Controladores y Modelos:**
   - En `AgentHeartbeatController`, se eliminó completamente la lectura y aceptación de `X-Agent-Secret` y `Authorization: Bearer`, consumiendo el modelo inyectado por el middleware.
   - En `Agent.php`, se añadió `resolveHmacSecret()` unificando la resolución del secreto en memoria.
   - En `AgentHeartbeatService.php`, se añadió `recordHeartbeat()` para actualizar el estado del agente sin requerir verificación redundante de contraseñas.

3. **Firma Criptográfica en el Emisor (`jg_agente`):**
   - En `HeartbeatService.send_heartbeat()`, se generan y envían las cabeceras canónicas HMAC-SHA256 para `/api/v1/agents/heartbeat` y su fallback `/api/agents/heartbeat`.
   - Se eliminaron las cabeceras en texto plano `Authorization` y `X-Agent-Secret`.

4. **Pruebas y Verificación:**
   - Suite `AgentHeartbeatApiTest` en `jg_dashboard` ampliada con 9 tests cubriendo validación HMAC, rechazo de texto plano (CWE-319), manipulación de payload y replay attacks.
   - Suite `test_heartbeat_service.py` en `jg_agente` actualizada para verificar cabeceras HMAC.
   - Verificación estática con `ruff` y `mypy` superada sin incidencias.
   - Verificación global `check_unit.sh` superada al 100% (713 tests en el workspace).
