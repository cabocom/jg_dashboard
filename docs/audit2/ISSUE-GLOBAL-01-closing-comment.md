# Comentario de Cierre: [GLOBAL-01] Desincronización y Fallbacks Hardcodeados de Secretos
## GitLab Issues: [jg_agente#52](https://gitlab.com/javiergalvez-ia/jg_agente/-/work_items/52) · [jg_dashboard#42](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/42)

---

## Estado: RESUELTO / IMPLEMENTADO

### Resumen de la Solución
Se ha erradicado de raíz la vulnerabilidad de credenciales hardcodeadas (CWE-798 / CWE-1188) y se ha garantizado la sincronización determinista de secretos HMAC entre el plano de control (`jg_dashboard`) y el plano de ejecución (`jg_agente`):

1. **Eliminación Total de Fallbacks Hardcodeados:**
   - Se suprimieron todas las cadenas fijas (`'sec_conversational_agent_preprovisioned_key_12345'` y `'sec_devops_agent_preprovisioned_key_12345'`) de `AgentHttpClient.php` y `config/agent.php`.
   - Si no se suministra un secreto válido, el cliente detiene la petición inmediatamente con una excepción descriptiva `RuntimeException`.
2. **Orquestación Segura con Interpolación Obligatoria:**
   - `docker-compose.yml` ahora utiliza la sintaxis obligatoria de Docker `${VAR:?Error...}` para `JG_CONVERSATIONAL_SECRET` y `JG_DEVOPS_SECRET`, impidiendo que los contenedores arranquen con secretos por defecto o vacíos.
3. **Persistencia Dinámica en `DatabaseSeeder.php`:**
   - El seeder calcula en caliente los hashes SHA-256 a partir de las variables de entorno / configuración reales, asegurando que ante cualquier rotación de claves en producción, la base de datos se mantenga perfectamente alineada con los agentes.
4. **Validación Automatizada:**
   - 251 tests pasados (1,202 aserciones) en `jg_dashboard` (Pest PHP).
   - 360 tests pasados en `jg_agente` (pytest).
