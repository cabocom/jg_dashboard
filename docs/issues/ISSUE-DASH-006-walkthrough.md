# Walkthrough: [ISSUE-DASH-006] Endurecimiento de variables por defecto y secretos de despliegue

## 1. Resumen de la Implementación
Se eliminaron por completo las variables y credenciales por defecto predecibles en los archivos de despliegue `docker-compose.yml` (tanto de `jg_dashboard` como de la raíz del proyecto), en las configuraciones de la aplicación (`config/agent.php`), en el cliente HTTP (`AgentHttpClient`) y en las plantillas de entorno (`.env.example`). Asimismo, se incorporó una comprobación activa en `docker/entrypoint.sh` para abortar el arranque de contenedores en entornos productivos o de staging si faltan las variables obligatorias o se utilizan secretos de prueba conocidos.

## 2. Archivos Modificados y Creados
- **[jg_dashboard/docker-compose.yml](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/docker-compose.yml)**:
  - Sustitución de valores por defecto por variables obligatorias (`"${VAR:?Error...}"`) para `APP_KEY`, `DB_PASSWORD`, `ADMIN_DEFAULT_PASSWORD` y `POSTGRES_PASSWORD`.
  - Configuración por defecto de `APP_DEBUG: ${APP_DEBUG:-false}`.
  - Inclusión de `JG_AGENTE_SECRET: ${JG_AGENTE_SECRET:-}`.
- **[docker-compose.yml](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/docker-compose.yml)**:
  - Alineación de los servicios `jg_dashboard` y `jg_dashboard_postgres` con la interpolación obligatoria y `APP_DEBUG: ${APP_DEBUG:-false}`.
- **[jg_dashboard/docker/entrypoint.sh](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/docker/entrypoint.sh)**:
  - Añadido bloque de validación para entornos no locales (`APP_ENV != 'local'` y `APP_ENV != 'testing'`) con aborto inmediato si `APP_KEY`, `DB_PASSWORD` o `ADMIN_DEFAULT_PASSWORD` son débiles/predecibles, y forzado de `APP_DEBUG="false"`.
- **[jg_dashboard/config/agent.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/config/agent.php)**:
  - Retirada del fallback `'secret_internal_control_plane'`.
- **[jg_dashboard/app/Services/Agents/AgentHttpClient.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Services/Agents/AgentHttpClient.php)**:
  - Eliminación del secreto hardcodeado en constructor.
  - Lanzamiento de `RuntimeException` en `generateSignatureHeaders()` si el secreto está vacío.
- **[jg_dashboard/.env.example](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/.env.example)** y **[.env.example](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/.env.example)**:
  - Retirada de claves fijas predecibles y documentación de los requerimientos de seguridad.
- **[jg_dashboard/phpunit.xml](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/phpunit.xml)**:
  - Declaración de `JG_AGENTE_SECRET="test_control_plane_secret"`.
- **[jg_dashboard/database/seeders/DatabaseSeeder.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/database/seeders/DatabaseSeeder.php)** y **[jg_dashboard/tests/Feature/InitAdminCommandTest.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/tests/Feature/InitAdminCommandTest.php)**:
  - Robustecimiento ante strings vacíos en variables de entorno y aislamiento de tests con `afterEach`.
- **[jg_dashboard/tests/Feature/DeploymentSecurityTest.php](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/tests/Feature/DeploymentSecurityTest.php)**:
  - Nueva suite de pruebas verificando configuración limpia y firma segura.

## 3. Verificación
1. **Validación de Docker Compose**:
   - `docker compose -f docker-compose.yml config` en la raíz sin variables falla inmediatamente notificando la ausencia de `DB_PASSWORD`.
   - `docker compose config` con variables seteadas procesa y valida la configuración correctamente.
2. **Pruebas Automatizadas de Pest**:
   ```bash
   vendor/bin/pest
   Tests:    248 passed (1177 assertions)
   Duration: 4.65s
   ```
   100% de los tests superados con éxito.
