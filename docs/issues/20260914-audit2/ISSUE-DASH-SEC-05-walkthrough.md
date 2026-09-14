# Walkthrough: [DASH-SEC-05] Resolución de Secretos en AgentHttpClient por Subcadena del Nombre de Agente

## Resumen de Cambios

### 1. `jg_dashboard/app/Models/Agent.php`
- En `resolveHmacSecret()`, se eliminaron `str_contains($agentName, 'devops')`, `str_contains($agentName, 'conversacional')` y comprobaciones sueltas de `plugin`.
- La asignación de credenciales queda circunscrita estrictamente al registro del agente en base de datos (`permanent_secret_encrypted`), a su metadato explícito, o a su `agent_id` canónico exacto.

### 2. `jg_dashboard/app/Services/Agents/AgentHttpClient.php`
- En `forAgent(Agent $agent)`, se eliminó el bloque duplicado y se delegó la resolución directamente a `$agent->resolveHmacSecret()`.

### 3. Tests Unitarios en `jg_dashboard/tests/Unit/AgentHttpClientTest.php`
- Agregados tests unitarios de seguridad:
  - `forAgent does not inherit devops_secret simply because name contains devops (DASH-SEC-05)`
  - `forAgent does not inherit conversational secret simply because name contains conversacional (DASH-SEC-05)`
  - `forAgent strictly resolves devops_secret for canonical DevOps UUID a0000000-0000-0000-0000-000000000002`

---

## Verificación de Pruebas

```bash
# Tests unitarios en jg_dashboard
cd jg_dashboard && ./vendor/bin/pest tests/Unit/AgentHttpClientTest.php
# 17 passed (52 assertions)

# Suite completa en jg_dashboard
./vendor/bin/pest
# 286 passed (1333 assertions)

# Verificación global del workspace
bash check_unit.sh
# 722 tests pasados exitosamente
```
