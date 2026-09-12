# Análisis Técnico: [GLOBAL-01] Desincronización y Fallbacks Hardcodeados de Secretos
## GitLab Issues: [jg_agente#52](https://gitlab.com/javiergalvez-ia/jg_agente/-/work_items/52) · [jg_dashboard#42](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/42)

---

## 1. Diagnóstico del Estado Previo

Durante la Auditoría 2 independiente (detectado concurrentemente como `SEC-001` por Claude Opus y `FINDING-02` por Gemini 3.8 Flash), se descubrió una vulnerabilidad crítica de seguridad y resiliencia operativa:

1. **Secretos quemados en código y repositorios (CWE-798):**
   - En `jg_dashboard/app/Services/Agents/AgentHttpClient.php:83, 86, 119, 122`, el cliente HTTP utilizaba cadenas estáticas como valores por defecto:
     - `'sec_devops_agent_preprovisioned_key_12345'`
     - `'sec_conversational_agent_preprovisioned_key_12345'`
   - En `jg_dashboard/config/agent.php:18`, se asignaba `'sec_devops_agent_preprovisioned_key_12345'` como fallback de `devops_secret`.
   - En `docker-compose.yml:110, 155`, las variables `PERMANENT_AGENT_SECRET` de ambos agentes cognitivos contenían los literales fijos directamente en el YAML.
2. **Inmutabilidad y ceguera de `DatabaseSeeder.php`:**
   - En `jg_dashboard/database/seeders/DatabaseSeeder.php:45, 67`, el seeder calculaba el hash SHA-256 sobre las cadenas literales quemadas, ignorando por completo cualquier variable de entorno configurada por el operador.
3. **Desincronización fatal de firmas HMAC en rotación:**
   - Si un administrador configuraba `JG_AGENTE_SECRET` en el archivo `.env` para producción, el Dashboard firmaba con la nueva clave, pero los agentes conservaban las claves quemadas en Compose y la base de datos conservaba los hashes viejos, provocando rechazos sistemáticos con `401 Unauthorized`.

---

## 2. Alternativas Evaluadas

1. **Mantener fallbacks estáticos pero documentar advertencias:**
   - *Descartada:* Insegura por diseño (CWE-1188 y CWE-798). Un despliegue descuidado o con variables no cargadas usaría credenciales conocidas públicamente en GitHub/GitLab.
2. **Generación aleatoria en tiempo de arranque sin persistencia:**
   - *Descartada:* En una arquitectura desacoplada de contenedores independientes, ambos extremos deben conocer el secreto simétrico antes de iniciar la comunicación, requiriendo un canal seguro de aprovisionamiento o configuración compartida explícita.
3. **Interpolación estricta obligatoria sin fallbacks hardcodeados (Opción Seleccionada):**
   - *Ventajas:*
     - Principio *Fail-Fast*: si falta alguna variable de secreto requerida, Compose (`${VAR:?error}`) o PHP (`throw new RuntimeException`) detienen el proceso inmediatamente con un mensaje de error claro y descriptivo.
     - Coherencia total: ambos servicios reciben exactamente la misma clave inyectada desde el archivo `.env`.
     - `DatabaseSeeder.php` deriva dinámicamente los hashes a partir de `Config::get(...)` o `env(...)`, respetando la rotación de claves en despliegue.

---

## 3. Especificación Técnica de la Solución

### A. Orquestación (`docker-compose.yml`)
- Se reemplazan los literales fijos por interpolación estricta que exige definición en `.env`:
  - `jg_dashboard`:
    - `JG_AGENTE_SECRET`: `${JG_CONVERSATIONAL_SECRET:-${JG_AGENTE_SECRET:?Error: JG_CONVERSATIONAL_SECRET o JG_AGENTE_SECRET es obligatoria}}`
    - `JG_DEVOPS_AGENT_SECRET`: `${JG_DEVOPS_SECRET:-${JG_DEVOPS_AGENT_SECRET:?Error: JG_DEVOPS_SECRET o JG_DEVOPS_AGENT_SECRET es obligatoria}}`
    - `DEVOPS60K_SECRET`: `${JG_DEVOPS_SECRET:-${JG_DEVOPS_AGENT_SECRET:?Error: JG_DEVOPS_SECRET o JG_DEVOPS_AGENT_SECRET es obligatoria}}`
  - `jg_agente_conversacional`:
    - `PERMANENT_AGENT_SECRET`: `${JG_CONVERSATIONAL_SECRET:-${JG_AGENTE_SECRET:?Error: JG_CONVERSATIONAL_SECRET o JG_AGENTE_SECRET es obligatoria}}`
  - `jg_agente_devops`:
    - `PERMANENT_AGENT_SECRET`: `${JG_DEVOPS_SECRET:-${JG_DEVOPS_AGENT_SECRET:?Error: JG_DEVOPS_SECRET o JG_DEVOPS_AGENT_SECRET es obligatoria}}`

### B. Control Plane (`jg_dashboard`)
- **`config/agent.php`:**
  - `internal_secret`: toma `JG_CONVERSATIONAL_SECRET` o `JG_AGENTE_SECRET` con default `null`.
  - `devops_secret`: toma `JG_DEVOPS_SECRET`, `JG_DEVOPS_AGENT_SECRET`, `DEVOPS60K_SECRET` con default `null`.
- **`AgentHttpClient.php`:**
  - Se eliminan todas las cadenas fallback `'sec_*'`.
  - Si falta el secreto al generar la cabecera de firma, se dispara una `RuntimeException` (CWE-798).
- **`DatabaseSeeder.php`:**
  - Valida que `conversationalSecret` y `devopsSecret` existan y no estén vacíos antes de sembrar la base de datos.
  - Genera `permanent_secret_hash` calculando `hash('sha256', $secret)` en caliente.
- **`phpunit.xml` & `DeploymentSecurityTest.php`:**
  - Inyección de variables de test en `phpunit.xml`.
  - Actualización del test de seguridad para validar que tanto `internal_secret` como `devops_secret` quedan nulos cuando no se definen variables de entorno.

### C. Plantillas de Entorno (`.env.example`)
- Se documentan explícitamente `JG_CONVERSATIONAL_SECRET` y `JG_DEVOPS_SECRET` en `.env.example` raíz, `jg_dashboard/.env.example` y `jg_agente/.env.example`.
