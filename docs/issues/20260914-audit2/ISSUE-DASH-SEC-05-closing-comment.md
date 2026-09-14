# Cierre de Issue: [DASH-SEC-05] Resolución de Secretos en AgentHttpClient por Subcadena del Nombre de Agente

## Resolución
Se ha resuelto satisfactoriamente la vulnerabilidad de confusión de identidades y claves en la resolución de secretos en `jg_dashboard` (Work item [#51](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/51)):

1. **Eliminación de Heurísticas Basadas en Nombre:**
   - En `Agent::resolveHmacSecret()`, se eliminaron las condiciones de coincidencia por subcadena (`str_contains($agentName, ...)`) y evaluación no determinista de plugins.
   - La resolución de claves HMAC queda vinculada estrictamente al registro persistente en base de datos (`permanent_secret_encrypted`), a los metadatos explícitos, o al `agent_id` canónico exacto.

2. **Centralización y DRY en `AgentHttpClient`:**
   - Se simplificó `AgentHttpClient::forAgent(Agent $agent)` para delegar en `$agent->resolveHmacSecret()`, asegurando total concordancia entre las firmas generadas por el cliente HTTP y las validadas por el middleware perimetral.

3. **Verificación Automatizada:**
   - Se incorporaron pruebas unitarias en `tests/Unit/AgentHttpClientTest.php` comprobando que nombres como `"test-devops-sandbox"` o `"agente-conversacional-ficticio"` no heredan credenciales de producción al poseer identificadores arbitrarios.
   - Las 286 pruebas de `jg_dashboard` y la suite global de 722 tests en `check_unit.sh` finalizaron con 100% de éxito.
