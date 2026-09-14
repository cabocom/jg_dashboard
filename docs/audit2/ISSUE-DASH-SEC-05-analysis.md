# Análisis Técnico: [DASH-SEC-05] Resolución de Secretos en AgentHttpClient por Subcadena del Nombre de Agente

## 1. Causa Raíz
En `jg_dashboard/app/Services/Agents/AgentHttpClient.php` y `jg_dashboard/app/Models/Agent.php`:
```php
if ($agent->agent_id === 'a0000000-0000-0000-0000-000000000002' || $plugin === 'devops' || str_contains($agentName, 'devops')) {
    // Asignar secreto DevOps de producción
} elseif ($agent->agent_id === 'a0000000-0000-0000-0000-000000000001' || $plugin === 'conversational' || str_contains($agentName, 'conversacional')) {
    // Asignar secreto Conversacional
}
```
La lógica recurría a un análisis heurístico no determinista sobre el nombre descriptivo del agente (`name`), un campo mutable y no diseñado para control de acceso ni autenticación criptográfica.

## 2. Implicaciones de Seguridad
1. **Confusión de Claves (Key Confusion):** Un agente de pruebas o sandbox podía autenticarse o enviar comandos firmados con la clave simétrica de mayor privilegio de la infraestructura de producción simplemente incluyendo la subcadena en su nombre.
2. **Duplicación Incoherente de Lógica:** Tanto `AgentHttpClient` como `Agent::resolveHmacSecret()` duplicaban la selección de claves, aumentando el riesgo de desincronización entre la generación de firmas salientes y la verificación perimetral entrante (`VerifyHmacSignature`).

## 3. Solución Implementada
1. **Eliminación de Heurísticas en `Agent::resolveHmacSecret()`:**
   Se retiraron todas las comprobaciones de subcadenas sobre `name` y `plugin`. La resolución sigue un orden determinista estricto:
   - Secreto individual permanente desencriptado de base de datos (`$this->getDecryptedSecret()`).
   - Secreto explícito en metadatos (`$this->metadata_json['secret']`).
   - Identificador unívoco canónico (`agent_id === 'a0000000-0000-0000-0000-000000000002'` para DevOps, `'a0000000-0000-0000-0000-000000000001'` para Conversacional).
   - Variable de entorno unívoca ligada al UUID: `AGENT_SECRET_{AGENT_ID}`.
   - Fallback global seguro: `config('agent.internal_secret')`.
2. **Unificación en `AgentHttpClient::forAgent()`:**
   Se delegó la resolución directamente a `$agent->resolveHmacSecret()`, estableciendo una única fuente de verdad para toda la capa de comunicación y firma HMAC.
3. **Pruebas Automatizadas:**
   Se agregaron tests unitarios en `tests/Unit/AgentHttpClientTest.php` comprobando que nombres como `"test-devops-sandbox"` o `"agente-conversacional-ficticio"` con UUIDs arbitrarios no heredan los secretos de producción de DevOps ni conversacionales.
