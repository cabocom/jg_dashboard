# Comentario de Cierre: [ISSUE-DASH-006] Endurecimiento de variables por defecto y secretos de despliegue

## Estado: RESUELTO / IMPLEMENTADO

### Resumen de la Solución
Se han eliminado todas las variables por defecto predecibles, contraseñas fijas y secretos hardcodeados en el proyecto:
1. **Docker Compose**:
   - `APP_KEY`, `DB_PASSWORD` y `ADMIN_DEFAULT_PASSWORD` se configuran ahora mediante interpolación obligatoria (`"${VAR:?Error...}"`) en los archivos `docker-compose.yml` de `jg_dashboard` y de la raíz, impidiendo el despliegue con valores por defecto.
   - `APP_DEBUG` se establece por defecto en `false`.
2. **Eliminación de Secretos Inseguros**:
   - Se retiró `'secret_internal_control_plane'` de `config/agent.php` y `AgentHttpClient.php`.
   - `AgentHttpClient::generateSignatureHeaders()` aborta con `RuntimeException` si el secreto `JG_AGENTE_SECRET` no está provisto.
3. **Control en Entrypoint**:
   - `docker/entrypoint.sh` valida que en entornos no locales no se utilicen contraseñas o claves conocidas/débiles, y fuerza `APP_DEBUG=false` en producción.
4. **Verificación**:
   - Se comprobaron ambas configuraciones de Docker Compose y se superaron al 100% las pruebas de Pest (248 tests, 1177 assertions).
