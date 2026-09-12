# Walkthrough: [GLOBAL-02] Ruptura Criptográfica del Registro Dinámico de Agentes (HMAC Asimétrico)
## GitLab Issues: [jg_agente#53](https://gitlab.com/javiergalvez-ia/jg_agente/-/work_items/53) · [jg_dashboard#43](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/43)

---

## Resumen de Cambios Implementados

1. **Migración de Base de Datos:**
   - Creado `database/migrations/2026_09_12_000001_add_permanent_secret_encrypted_to_dashboard_agents_table.php` para añadir la columna `permanent_secret_encrypted` a `dashboard_agents`.

2. **Modelo `Agent` (`jg_dashboard/app/Models/Agent.php`):**
   - Incorporada columna a `$fillable` y a `$hidden`.
   - Cast nativo `'permanent_secret_encrypted' => 'encrypted'`.
   - Actualizado método `markAsActive(string $secretHash, ?string $plainSecret = null): void`.
   - Implementado método `getDecryptedSecret(): ?string`.

3. **Servicio de Registro (`jg_dashboard/app/Services/Agents/AgentRegistrationService.php`):**
   - En `consumeBootstrapTokenAndActivate(...)`, se pasa `$permanentSecret` a `markAsActive($secretHash, $permanentSecret)`.

4. **Cliente HTTP de Control Plane (`jg_dashboard/app/Services/Agents/AgentHttpClient.php`):**
   - En `forAgent(Agent $agent)`, se asigna como prioridad 1 `$agent->getDecryptedSecret()`.

5. **Seeder de Inicialización (`jg_dashboard/database/seeders/DatabaseSeeder.php`):**
   - Los agentes `a0000000-0000-0000-0000-000000000001` y `a0000000-0000-0000-0000-000000000002` se crean con `permanent_secret_encrypted` poblado.

6. **Pruebas Unitarias:**
   - Actualizado `tests/Unit/AgentRegistrationServiceTest.php` comprobando que `getDecryptedSecret()` coincide con el secreto permanente generado.
   - Añadida prueba en `tests/Unit/AgentHttpClientTest.php` comprobando que `forAgent` computa firmas HMAC válidas usando `permanent_secret_encrypted` para agentes dinámicos.

---

## Verificación y Resultados

- **Pest Unit Tests (`jg_dashboard`):** 23/23 tests pasados (118 aserciones).
- **Suite Completa (`jg_dashboard`):** 252/252 tests pasados (1204 aserciones).
- **Suite Completa (`jg_agente`):** 361/361 tests pasados (Ruff, MyPy y Pytest 100% verde).
- **Verificación Global (`./check.sh`):** Exitosa en ambos proyectos.
