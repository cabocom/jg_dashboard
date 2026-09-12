# Análisis Técnico: [ISSUE-DASH-002] Emisión de firma criptográfica HMAC alineada con contrato del agente (GLOBAL-001)

## 1. Diagnóstico del Estado Previo
En el estado previo de `jg_dashboard`:
- `AgentHttpClient::generateSignatureHeaders()` generaba un hash HMAC concatenando `timestamp . '.' . $body` sin incluir el método HTTP ni la ruta de destino.
- El cliente incluía la cabecera `X-Control-Plane-Secret: $secret`, exponiendo el secreto permanente en texto plano a través de la red en cada llamada.
- Existían valores de fallback hardcodeados para el secreto y el ID del agente.

## 2. Solución Diseñada
- Actualizar `generateSignatureHeaders(string $body = '', string $method = 'POST', string $path = '/executions/trigger')` para implementar la firma canónica acordada:
  ```php
  $normalizedMethod = strtoupper($method);
  $normalizedPath = '/' . ltrim($path, '/');
  $bodyHash = hash('sha256', $body);
  $canonicalString = $normalizedMethod . "\n" . $normalizedPath . "\n" . $timestamp . "\n" . $bodyHash;
  $signature = hash_hmac('sha256', $canonicalString, $this->secret);
  ```
- Enviar únicamente:
  - `X-Signature: $signature`
  - `X-Timestamp: $timestamp`
  - `X-Agent-ID: $agentId`
- Eliminar la emisión de `X-Control-Plane-Secret`.
- En `prepareClient()`, propagar `$method` y `$endpoint` para que la firma coincida exactamente con la petición que sale a la red.

## 3. Impacto en Pruebas
- Actualizar tests existentes en `tests/Unit/AgentHttpClientTest.php` para reflejar la eliminación de `X-Control-Plane-Secret` y el formato canónico.
- Crear `tests/Unit/Services/AgentHttpClientSignatureTest.php` para verificar el determinismo de la firma, el hashing de peticiones GET sin cuerpo y el rechazo ante manipulación de 1 byte.
