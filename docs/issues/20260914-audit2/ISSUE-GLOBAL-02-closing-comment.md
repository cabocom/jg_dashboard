### Resolución del Issue [GLOBAL-02] Ruptura Criptográfica del Registro Dinámico de Agentes (HMAC Asimétrico)
**GitLab Work Items:** [jg_agente#53](https://gitlab.com/javiergalvez-ia/jg_agente/-/work_items/53) · [jg_dashboard#43](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/43)

---

#### 1. Diagnóstico del Problema Resuelto
- Al consumir un bootstrap token, el Dashboard generaba un secreto simétrico aleatorio (`sec_...`), guardaba su hash SHA-256 (`permanent_secret_hash`) y descartaba el secreto original.
- Al no conservarlo de forma reversible, `AgentHttpClient` no podía computar la cabecera `X-Signature: HMAC-SHA256(...)` para ese agente específico y recurría al secreto genérico del `.env`.
- El agente receptor, que validaba contra su clave privada local guardada en `.agent_credentials.json`, rechazaba sistemáticamente toda petición con `401 Unauthorized`.

#### 2. Implementación Aplicada
1. **Migración de Esquema:** Añadida la columna `permanent_secret_encrypted: text` (nullable) a `dashboard_agents` (`2026_09_12_000001_add_permanent_secret_encrypted_to_dashboard_agents_table.php`).
2. **Modelo `Agent`:**
   - Incorporado atributo a `$fillable` y `$hidden` (previene fugas en endpoints JSON).
   - Cast de Eloquent `'permanent_secret_encrypted' => 'encrypted'` (cifrado AES-256-CBC respaldado por `APP_KEY`).
   - Métodos `markAsActive($hash, $plainSecret)` y `getDecryptedSecret()`.
3. **`AgentRegistrationService`:** Persiste el secreto en reposo encriptado durante la activación del agente.
4. **`AgentHttpClient`:** En `forAgent(Agent $agent)`, desencripta y usa `$agent->getDecryptedSecret()` para la firma HMAC de peticiones salientes hacia el agente.
5. **`DatabaseSeeder`:** Actualizado para sembrar también las credenciales cifradas de los agentes pre-provisionados.

#### 3. Validación y Pruebas
- Pruebas unitarias en `AgentRegistrationServiceTest` validando el almacenamiento y desencriptado del secreto dinámico.
- Pruebas unitarias en `AgentHttpClientTest` validando que las firmas HMAC coinciden con el secreto del agente dinámico.
- Verificación completa con `./check.sh`:
  - `jg_dashboard`: 252 tests pasados al 100%.
  - `jg_agente`: 361 tests pasados al 100% (Ruff, MyPy y Pytest).
  - Ambos checks en verde.
