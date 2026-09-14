# Walkthrough Técnico: [GLOBAL-01] Desincronización y Fallbacks Hardcodeados de Secretos
## GitLab Issues: [jg_agente#52](https://gitlab.com/javiergalvez-ia/jg_agente/-/work_items/52) · [jg_dashboard#42](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/42)

---

## 1. Archivos Modificados

### 1.1. Orquestación y Despliegue
- [`docker-compose.yml`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/docker-compose.yml):
  - Líneas 49-53: Se configuran `JG_AGENTE_SECRET`, `JG_DEVOPS_AGENT_SECRET` y `DEVOPS60K_SECRET` mediante interpolación estricta obligatoria `${VAR:?Error...}`.
  - Línea 110: `jg_agente_conversacional` ahora interpola `${JG_CONVERSATIONAL_SECRET:-${JG_AGENTE_SECRET:?...}}` obligatoriamente.
  - Línea 155: `jg_agente_devops` ahora interpola `${JG_DEVOPS_SECRET:-${JG_DEVOPS_AGENT_SECRET:?...}}` obligatoriamente.

### 1.2. Control Plane (`jg_dashboard`)
- [`jg_dashboard/config/agent.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/config/agent.php):
  - Línea 16: `internal_secret` mapea a `JG_CONVERSATIONAL_SECRET`, `JG_AGENTE_SECRET` o `AGENT_INTERNAL_SECRET` con valor por defecto `null`.
  - Línea 18: `devops_secret` mapea a `JG_DEVOPS_SECRET`, `JG_DEVOPS_AGENT_SECRET`, `DEVOPS60K_SECRET` con valor por defecto `null` (se eliminó el fallback `'sec_devops_agent_preprovisioned_key_12345'`).
- [`jg_dashboard/app/Services/Agents/AgentHttpClient.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Services/Agents/AgentHttpClient.php):
  - Líneas 81-86: Eliminados todos los fallbacks estáticos en `forAgent()`.
  - Líneas 117-126: Eliminados todos los fallbacks estáticos en `withAgentId()`.
- [`jg_dashboard/database/seeders/DatabaseSeeder.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/database/seeders/DatabaseSeeder.php):
  - Líneas 38-84: Derivación dinámica de `permanent_secret_hash` calculando `hash('sha256', $conversationalSecret)` y `hash('sha256', $devopsSecret)`.
  - Verificación previa que lanza `RuntimeException` si alguna de las claves obligatorias falta.
- [`jg_dashboard/phpunit.xml`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/phpunit.xml):
  - Se añadieron las variables de prueba `JG_CONVERSATIONAL_SECRET`, `JG_DEVOPS_SECRET` y `JG_DEVOPS_AGENT_SECRET`.
- [`jg_dashboard/tests/Feature/DeploymentSecurityTest.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/tests/Feature/DeploymentSecurityTest.php):
  - Prueba de seguridad ampliada para validar que ni `internal_secret` ni `devops_secret` contienen ningún fallback hardcodeado.

### 1.3. Plantillas de Configuración
- [`.env.example`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/.env.example):
  - Actualización en la raíz para incluir `JG_CONVERSATIONAL_SECRET` y `JG_DEVOPS_SECRET`.
- [`jg_dashboard/.env.example`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/.env.example):
  - Documentación de variables secretas correspondientes.
- [`jg_agente/.env.example`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_agente/.env.example):
  - Documentación de `PERMANENT_AGENT_SECRET`.

---

## 2. Pruebas de Verificación y No Regresión

### 2.1. Suite de Pruebas de `jg_dashboard` (Pest PHP)
- **Comando ejecutado:** `./vendor/bin/pest`
- **Resultado:**
  ```text
  Tests:    251 passed (1202 assertions)
  Duration: 10.37s
  ```
- **Tests clave verificados:**
  - `DeploymentSecurityTest`: confirma que ni `internal_secret` ni `devops_secret` contienen fallbacks hardcodeados en el archivo de configuración.
  - `DatabaseSeederTest`: confirma que el sembrado de usuarios y agentes pre-aprovisionados se ejecuta y actualiza correctamente con contraseñas y hashes derivados.
  - `AgentHttpClient`: firma criptográfica HMAC-SHA256 y control de excepciones cuando falta la credencial.

### 2.2. Suite de Pruebas de `jg_agente` (pytest)
- **Comando ejecutado:** `pytest`
- **Resultado:**
  ```text
  360 passed in 70.91s
  ```
- **Tests clave verificados:**
  - `test_api_executions_auth.py`: validación de firma HMAC con `PERMANENT_AGENT_SECRET`.
  - `test_heartbeat_service.py`: verificación de latido y autenticación de credenciales permanentes.
  - `test_registration_client.py`: ciclo de vida de bootstrap y almacenamiento seguro de credenciales.
