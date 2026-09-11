# Análisis Técnico: [ISSUE-DASH-006] Endurecimiento de variables por defecto y secretos de despliegue

## 1. Diagnóstico del Estado Previo
1. **`docker-compose.yml` (Dashboard y Raíz)**:
   - `APP_KEY: ${APP_KEY:-base64:3vKjQ+7eZk1A9jH2wP5T6m8V0xY1Z4aB7c8D9E0F1G2=}`: Clave criptográfica fija conocida en repositorios públicos.
   - `APP_DEBUG: ${APP_DEBUG:-true}`: Exposición de debug activada por defecto.
   - `DB_PASSWORD: ${DB_PASSWORD:-postgres_secure_pass}`: Contraseña de base de datos predecible.
   - `ADMIN_DEFAULT_PASSWORD: ${ADMIN_DEFAULT_PASSWORD:-admin1234567890!}`: Contraseña de administración de prueba fijada en compose.
2. **`config/agent.php` y `AgentHttpClient.php`**:
   - `config/agent.php:16` usaba `'secret_internal_control_plane'` como fallback.
   - `AgentHttpClient.php:44` utilizaba el mismo valor `'secret_internal_control_plane'` si no se configuraba un secreto, lo cual permitía que cualquier agente o atacante con conocimiento del código fuente firmara o verificara peticiones HMAC.
3. **`docker/entrypoint.sh`**:
   - Carecía de validaciones para abortar el inicio del contenedor ante el despliegue con credenciales o configuraciones débiles en entornos no locales.
4. **`.env.example`**:
   - Contenía la clave fija `base64:3vKjQ+...` y `APP_DEBUG=true`.

## 2. Solución Diseñada
1. **Interpolación Estricta en Compose**:
   - Se utiliza la sintaxis de variables obligatorias de Docker Compose:
     ```yaml
     APP_KEY: "${APP_KEY:?Error: APP_KEY es obligatoria. Genere una con 'php artisan key:generate' y defínala en su archivo .env}"
     APP_DEBUG: ${APP_DEBUG:-false}
     DB_PASSWORD: "${DB_PASSWORD:?Error: DB_PASSWORD es obligatoria. Defínala en su archivo .env}"
     ADMIN_DEFAULT_PASSWORD: "${ADMIN_DEFAULT_PASSWORD:?Error: ADMIN_DEFAULT_PASSWORD es obligatoria. Defínala en su archivo .env}"
     ```
   - Aplicado de manera idéntica en `jg_dashboard/docker-compose.yml` y en el `docker-compose.yml` de la raíz del monorepositorio.
2. **Desactivación de Secretos Hardcodeados**:
   - En `config/agent.php`:
     ```php
     'internal_secret' => env('JG_AGENTE_SECRET', env('AGENT_INTERNAL_SECRET', null)),
     ```
   - En `AgentHttpClient.php`:
     ```php
     $this->internalSecret = $internalSecret ?? Config::get('agent.internal_secret') ?? Config::get('devops60k.api_key');
     ```
     Y en `generateSignatureHeaders()`:
     ```php
     if (empty($this->internalSecret)) {
         throw new \RuntimeException("Seguridad (CWE-798): El secreto interno para firma HMAC no está configurado (JG_AGENTE_SECRET).");
     }
     ```
3. **Validación Temprana en `docker/entrypoint.sh`**:
   - Si `APP_ENV != 'local'` y `APP_ENV != 'testing'`:
     - Falla con código 1 si `APP_KEY` está vacía o es la clave predeterminada.
     - Falla con código 1 si `DB_PASSWORD` es `postgres_secure_pass` o `postgres`.
     - Falla con código 1 si `ADMIN_DEFAULT_PASSWORD` es el valor por defecto o tiene menos de 16 caracteres.
     - Fuerza `APP_DEBUG=false` para prevenir fugas de traza.
4. **Actualización de `.env.example` y `phpunit.xml`**:
   - `.env.example` sin clave pre-generada y con instrucciones de seguridad.
   - `phpunit.xml` con `<env name="JG_AGENTE_SECRET" value="test_control_plane_secret"/>`.
5. **Pruebas Automatizadas**:
   - Creado `tests/Feature/DeploymentSecurityTest.php` validando la ausencia de secretos hardcodeados y la exigencia de secreto HMAC.
