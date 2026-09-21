# Walkthrough: [ISSUE-DASH-002] Emisión de firma criptográfica HMAC alineada con contrato del agente (GLOBAL-001)

## 1. Resumen de la Implementación
Se ajustó `AgentHttpClient` en `jg_dashboard` para generar cabeceras de firma criptográfica HMAC-SHA256 alineadas al 100% con el contrato canónico requerido por `jg_agente`, retirando el secreto en texto plano y asegurando el hashing estricto del cuerpo, método y endpoint.

## 2. Archivos Modificados y Creados
- **[app/Services/Agents/AgentHttpClient.php](../../../app/Services/Agents/AgentHttpClient.php)**:
  - Firma canónica: `METHOD\nPATH\nTIMESTAMP\nSHA256(RAW_BODY)`.
  - Retirada de cabecera `X-Control-Plane-Secret`.
  - Envío exacto de bytes mediante `$client->withBody($encodedBody, 'application/json')`.
  - Inyección de `$agentId` configurable por instancia de cliente.
- **[tests/Unit/AgentHttpClientTest.php](../../../tests/Unit/AgentHttpClientTest.php)**:
  - Actualización de aserciones de cabeceras generadas.
- **[tests/Unit/Services/AgentHttpClientSignatureTest.php](../../../tests/Unit/Services/AgentHttpClientSignatureTest.php)**:
  - Tests específicos de verificación canónica, firma determinista, hash SHA-256 de cuerpo vacío y validación de divergencia ante manipulación de 1 solo byte.

## 3. Verificación
Se ejecutó la suite completa de pruebas de Pest:
```bash
./vendor/bin/pest --compact
Tests:    221 passed (781 assertions)
Duration: 3.51s
```
Todos los tests pasaron exitosamente.
