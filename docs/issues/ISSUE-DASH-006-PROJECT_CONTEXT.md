# Contexto de Proyecto: [ISSUE-DASH-006] Endurecimiento de variables por defecto y secretos de despliegue

## 1. Identificación y Estado
- **Issue ID:** ISSUE-DASH-006
- **GitLab Work Item:** jg_dashboard#41
- **Fase:** Fase Post-Auditoría (Seguridad y Operaciones en Despliegue)
- **Prioridad:** Low (Reclasificada a Medium por impacto de seguridad)
- **Complejidad Estimada:** S
- **Estado:** Implementado / Verificado

## 2. Alineación Arquitectónica
Durante la auditoría técnica independiente (FINDINGS.md, DASH-006), se detectó que la configuración de despliegue y Compose contenía valores por defecto inseguros y predecibles (CWE-215, CWE-521, CWE-798):
1. **Claves y Contraseñas Hardcodeadas en Compose**:
   - `docker-compose.yml` (en `jg_dashboard` y en la raíz) definía `APP_KEY`, `DB_PASSWORD`, `ADMIN_DEFAULT_PASSWORD` y `POSTGRES_PASSWORD` con fallbacks fijos (`base64:3vKjQ+...`, `postgres_secure_pass`, `admin1234567890!`).
   - `APP_DEBUG` venía activado por defecto (`true`), exponiendo trazas detalladas del stack en caso de despliegue inadvertido en entornos de staging o producción.
2. **Secreto Interno Hardcodeado en Configuración**:
   - `config/agent.php` y `AgentHttpClient.php` utilizaban `'secret_internal_control_plane'` como valor fallback por defecto para el secreto de firma HMAC.
3. **Ausencia de Validación en el Arranque del Contenedor**:
   - El script `docker/entrypoint.sh` no validaba la ausencia o debilidad de estas variables críticas en entornos productivos o de staging.

Para mitigar estos riesgos de forma rigurosa:
- **Interpolación Restrictiva en Compose**: Se reemplazaron los fallbacks por expresiones obligatorias (`"${VAR:?Error...}"`), forzando a Docker Compose a detenerse con un mensaje explícito si `APP_KEY`, `DB_PASSWORD` o `ADMIN_DEFAULT_PASSWORD` no están configuradas en `.env` o en el entorno.
- **`APP_DEBUG` Seguro por Defecto**: Se estableció por defecto en `false`.
- **Eliminación del Secreto de Firma Inseguro**: Se retiró `'secret_internal_control_plane'` de `config/agent.php` y de `AgentHttpClient.php`. Si el secreto no está configurado (`JG_AGENTE_SECRET`), `AgentHttpClient` lanza una excepción controlada impidiendo la emisión de firmas vulnerables.
- **Validación Estricta en Entrypoint**: `docker/entrypoint.sh` valida en entornos no locales (`APP_ENV != 'local'`) que `APP_KEY` no sea el valor conocido, que `DB_PASSWORD` no sea el default predecible, que `ADMIN_DEFAULT_PASSWORD` tenga al menos 16 caracteres y no sea un valor de prueba, y fuerza `APP_DEBUG=false` en producción.
- **Aislamiento de Tests**: Se inyectó `JG_AGENTE_SECRET` en `phpunit.xml` para asegurar que las pruebas unitarias y de integración corran de forma autónoma.

## 3. Criterios de Aceptación
- `docker-compose.yml` falla el arranque si no se proporcionan `APP_KEY`, `DB_PASSWORD` o `ADMIN_DEFAULT_PASSWORD`.
- `config/agent.php` no contiene secretos por defecto en texto plano.
- `AgentHttpClient` rehúsa firmar peticiones con secretos vacíos arrojando un error tipado de seguridad.
- `docker/entrypoint.sh` aborta el arranque en producción si se detectan valores predeterminados inseguros.
- La suite completa de pruebas Pest supera todos los tests unitarios y de integración.
