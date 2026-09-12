# Contexto de Proyecto: [GLOBAL-01] Desincronización y Fallbacks Hardcodeados de Secretos
## GitLab Issues: [jg_agente#52](https://gitlab.com/javiergalvez-ia/jg_agente/-/work_items/52) · [jg_dashboard#42](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/42)

---

## 1. Identificación y Estado
- **Vínculo Global:** GLOBAL-01
- **Issues en GitLab:**
  - `jg_agente`: Issue #52
  - `jg_dashboard`: Issue #42
- **Fase:** Fase Post-Auditoría 2 (Resolución de Hallazgos Críticos)
- **Severidad:** 🔴 CRÍTICO
- **Complejidad Estimada:** M
- **Estado:** Implementado / Verificado al 100%

---

## 2. Alineación Arquitectónica
En la Auditoría 2 independiente, tanto Claude Opus (`SEC-001`) como Gemini 3.8 Flash (`FINDING-02`) identificaron que existían secretos HMAC pre-aprovisionados hardcodeados en el repositorio git (`docker-compose.yml`, `AgentHttpClient.php`, `config/agent.php` y `DatabaseSeeder.php`).

Para solventar esta debilidad sistémica:
1. **Principio de Fallo Temprano (Fail-Fast):**
   Ningún componente del sistema debe arrancar con credenciales predecibles si falta la configuración de entorno.
2. **Interpolación Estricta de Entorno:**
   `docker-compose.yml` delega obligatoriamente la provisión de secretos a las variables de entorno `${JG_CONVERSATIONAL_SECRET:?...}` y `${JG_DEVOPS_SECRET:?...}`, asegurando que tanto `jg_dashboard` como cada instancia de `jg_agente` compartan simétricamente la misma clave.
3. **Derivación de Hash en Base de Datos:**
   `DatabaseSeeder.php` deriva en tiempo de ejecución los hashes SHA-256 de las credenciales de entorno en lugar de usar constantes fijas.
4. **Respeto a CWE-798 y CWE-1188:**
   Se prohíbe taxativamente cualquier fallback con cadenas estáticas de producción en el código fuente.

---

## 3. Criterios de Aceptación Cumplidos
- [x] Ausencia de literales de claves secretas pre-aprovisionadas en `AgentHttpClient.php`, `config/agent.php` y `docker-compose.yml`.
- [x] Si no se proveen variables en Compose o entorno, el despliegue aborta con error explícito.
- [x] `DatabaseSeeder.php` calcula `permanent_secret_hash` leyendo `Config::get('agent.*')` o variables de entorno.
- [x] `AgentHttpClient` lanza excepción inmediata (`RuntimeException`) si no se dispone de un secreto configurado al firmar peticiones.
- [x] Verificación de la suite de pruebas unitarias y de integración pasando al 100% (251 tests en Dashboard, 360 en Agente).
