# Comentario de Cierre: [ISSUE-DASH-002] Emisión de firma criptográfica HMAC alineada con contrato del agente (GLOBAL-001)

## Estado: RESUELTO / IMPLEMENTADO

### Resumen de la Solución
Se ha implementado el estándar canónico de firma criptográfica HMAC-SHA256 en `AgentHttpClient`:
1. La firma se calcula a partir de `METHOD\nPATH\nTIMESTAMP\nSHA256(RAW_BODY)`.
2. Se retiró completamente la cabecera `X-Control-Plane-Secret` para evitar la exposición del secreto en texto plano.
3. Se agregaron pruebas unitarias verificando el hashing exacto, la consistencia con peticiones vacías (GET) y el cambio en la firma ante cualquier alteración mínima del cuerpo o parámetros.
4. Toda la suite de Pest en `jg_dashboard` (221 tests) ha pasado satisfactoriamente.
