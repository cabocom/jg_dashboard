# PROJECT_CONTEXT Update: [GLOBAL-02] Ruptura Criptográfica del Registro Dinámico de Agentes
## GitLab Issues: [jg_agente#53](https://gitlab.com/javiergalvez-ia/jg_agente/-/work_items/53) · [jg_dashboard#43](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/43)

---

## Modificaciones en la Arquitectura y Contratos de Seguridad

### 1. Persistencia Reversible Segura en `dashboard_agents` (`jg_dashboard`)
- Se incorpora la columna `permanent_secret_encrypted: text` (nullable) a la tabla `dashboard_agents`.
- El modelo `Agent` define el cast `'permanent_secret_encrypted' => 'encrypted'`, garantizando que la credencial simétrica se almacene cifrada con AES-256-CBC derivado de la `APP_KEY` del Dashboard.
- El atributo se oculta en `$hidden` para prevenir exposición accidental en respuestas JSON de la API.

### 2. Flujo de Activación y Firma HMAC Dinámica
- **Registro de Agentes:** Al invocar `AgentRegistrationService::consumeBootstrapTokenAndActivate(...)`, el Dashboard almacena tanto el hash SHA-256 de verificación (`permanent_secret_hash`) como el secreto cifrado reversible (`permanent_secret_encrypted`).
- **Invocación de Agentes:** `AgentHttpClient::forAgent(Agent $agent)` recupera el secreto mediante `$agent->getDecryptedSecret()`. Esto permite que el Dashboard firme peticiones (`X-Signature = HMAC-SHA256(canonical_string, permanent_secret)`) coincidiendo exactamente con la clave privada local del agente registrada dinámicamente.
- **Pre-provisioning y Seeders:** Los agentes pre-provisionados (Conversacional y DevOps) se inicializan con sus credenciales cifradas en la base de datos, unificando la resolución de secretos y prescindiendo de dependencias heurísticas sobre nombres o IDs.
