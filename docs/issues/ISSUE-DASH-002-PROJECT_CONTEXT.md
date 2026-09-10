# Contexto de Proyecto: [ISSUE-DASH-002] Emisión de firma criptográfica HMAC alineada con contrato del agente (GLOBAL-001)

## 1. Identificación y Estado
- **Issue ID:** ISSUE-DASH-002
- **Vínculo Global:** GLOBAL-001
- **Fase:** Fase Post-Auditoría (Implementación de Hallazgos Codex)
- **Prioridad:** High / Critical
- **Complejidad Estimada:** M
- **Estado:** Implementado / Verificado

## 2. Alineación Arquitectónica
En la auditoría técnica independiente (GLOBAL-001), se identificó que el cliente HTTP del Dashboard (`AgentHttpClient`) enviaba el secreto del agente en texto plano mediante la cabecera `X-Control-Plane-Secret` y utilizaba un formato ad-hoc de firma HMAC que no validaba el método ni la ruta ni el hash del cuerpo.

Para alinearse estrictamente con el contrato del agente (`jg_agente`):
1. **Contrato Canónico de Firma:**
   El Dashboard construye la cadena canónica:
   ```text
   METHOD\nPATH\nTIMESTAMP\nSHA256(RAW_BODY)
   ```
   donde:
   - `METHOD`: En mayúsculas (`POST`, `GET`).
   - `PATH`: Ruta de la petición (`/executions/trigger`).
   - `TIMESTAMP`: Timestamp Unix actual en segundos.
   - `SHA256(RAW_BODY)`: Hash SHA-256 de los bytes exactos enviados por el cable HTTP (o hash de cadena vacía `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`).
2. **Retirada de Secretos en Cabeceras:**
   Se elimina por completo la cabecera `X-Control-Plane-Secret`, protegiendo la confidencialidad del secreto incluso ante intermediarios o logs de red.
3. **Preservación de Integridad Byte a Byte:**
   Las peticiones con cuerpo codifican el payload a JSON y lo transmiten utilizando `withBody($encodedBody, 'application/json')`, garantizando que el hash calculado coincida byte a byte con lo recibido por el agente.

## 3. Criterios de Aceptación
- `AgentHttpClient::generateSignatureHeaders()` genera `X-Signature`, `X-Timestamp`, `X-Agent-ID` conforme al formato canónico.
- No se emite la cabecera `X-Control-Plane-Secret`.
- La suite completa de pruebas de Pest en `jg_dashboard` supera los tests unitarios y de integración.
