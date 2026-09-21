# Contexto del Proyecto: [DASH-SEC-05] Resolución de Secretos en AgentHttpClient por Subcadena del Nombre de Agente

## Metadatos de la Incidencia
- **ID:** `jg_dashboard#51`
- **Código:** `DASH-SEC-05`
- **Severidad:** 🟢 BAJO
- **CWE:** CWE-287 (Improper Authentication), CWE-347 (Improper Verification of Cryptographic Signature)
- **Componentes Afectados:**
  - [`jg_dashboard/app/Models/Agent.php`](../../../app/Models/Agent.php)
  - [`jg_dashboard/app/Services/Agents/AgentHttpClient.php`](../../../app/Services/Agents/AgentHttpClient.php)
  - [`jg_dashboard/tests/Unit/AgentHttpClientTest.php`](../../../tests/Unit/AgentHttpClientTest.php)

---

## Descripción de la Vulnerabilidad
En `AgentHttpClient::forAgent()` y `Agent::resolveHmacSecret()`, cuando un agente no disponía de un secreto permanente en base de datos o en metadatos explícitos, el sistema evaluaba si el nombre del agente contenía la subcadena `"devops"` o `"conversacional"` (`str_contains($agentName, 'devops')`, `str_contains($agentName, 'conversacional')`) para seleccionar la clave simétrica compartida.

## Impacto en Seguridad
Un usuario u operador que aprovisionara un agente con un nombre arbitrario que contuviera la palabra clave (ej. `"test-devops-sandbox"` o `"worker-conversacional-demo"`) provocaba que dicho agente heredara indebidamente los secretos de producción de DevOps o del agente conversacional principal.
Esto posibilitaba colisiones de identidad criptográfica, confusión de claves HMAC y acceso no intencionado a funciones privilegiadas.
