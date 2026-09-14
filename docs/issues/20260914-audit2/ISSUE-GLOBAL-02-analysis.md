# Análisis Técnico: [GLOBAL-02] Ruptura Criptográfica del Registro Dinámico de Agentes (HMAC Asimétrico)
## GitLab Issues: [jg_agente#53](https://gitlab.com/javiergalvez-ia/jg_agente/-/work_items/53) · [jg_dashboard#43](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/43)

---

## 1. Diagnóstico del Estado Previo

Durante la Auditoría 2 independiente (detectado concurrentemente como hallazgo de arquitectura por Claude Opus y como vulnerabilidad crítica de canal HMAC por Gemini 3.8 Flash), se identificó un fallo de diseño que imposibilitaba el aprovisionamiento dinámico de agentes:

1. **Descarte irreversible del secreto permanente:**
   - En `jg_dashboard/app/Services/Agents/AgentRegistrationService.php:239-244`, cuando un nuevo agente consume un bootstrap token (`POST /api/v1/agents/register`), el Dashboard genera una clave criptográfica de 48 caracteres (`$permanentSecret = 'sec_' . Str::random(48)`).
   - El servicio computaba su hash SHA-256 (`hash('sha256', $permanentSecret)`) y lo persistía en `dashboard_agents.permanent_secret_hash`.
   - El secreto en texto plano se entregaba al agente en la respuesta JSON para que este lo almacenara localmente en `.agent_credentials.json`, pero el Dashboard descartaba la cadena original inmediatamente después de la transacción.

2. **Incompatibilidad matemática con HMAC simétrico:**
   - El protocolo de comunicación bidireccional exige que el Dashboard firme todas las peticiones salientes hacia el agente (`POST /executions/trigger`, `/approve`, etc.) mediante una cabecera `X-Signature: HMAC-SHA256(canonical_string, secret)`.
   - Como SHA-256 es una función unidireccional no invertible, el Dashboard no podía reconstruir el secreto original del agente dinámico.
   - En `AgentHttpClient.php:77-95`, el cliente recurría a fallbacks basados en variables de entorno fijas (`Config::get('agent.internal_secret')`).

3. **Rechazo sistemático con 401 Unauthorized:**
   - El agente dinámico recibía la petición y computaba el HMAC esperado utilizando su clave local guardada durante el registro.
   - Dado que el Dashboard había firmado con la clave estática global del `.env`, las firmas jamás coincidían, resultando en un rechazo inmediato por `401 Unauthorized`.
   - **Impacto:** Era absolutamente imposible registrar nuevos agentes dinámicos, imposibilitando el escalado horizontal o la adhesión de agentes efímeros en runtime.

---

## 2. Alternativas Evaluadas

1. **Migración completa a criptografía asimétrica Ed25519 (Par de claves Pública/Privada):**
   - *Evaluación:* El agente generaría su propio par de claves Ed25519 en boot, enviaría su clave pública al Dashboard en el registro y firmaría sus respuestas; el Dashboard mantendría su clave privada para firmar comandos.
   - *Decisión:* Descartada para la fase actual por implicar un rediseño del middleware de autenticación tanto en Python (`auth.py`) como en PHP, además de introducir dependencias criptográficas adicionales. Se reserva como evolución arquitectónica P3.

2. **Reutilizar un único secreto maestro estático para todos los agentes dinámicos:**
   - *Evaluación:* Que el Dashboard asigne la misma clave fija a todos los agentes que se registren.
   - *Decisión:* Descartada por violar el principio de aislamiento criptográfico y mínimos privilegios (si un pod se ve comprometido, compromete la autenticación de todos los demás pods de la flota).

3. **Cifrado Simétrico Reversible en Reposo (Opción Seleccionada):**
   - *Mecanismo:*
     - Añadir la columna `permanent_secret_encrypted` a `dashboard_agents`.
     - Utilizar el cifrado nativo de Laravel (`Crypt::encrypt` / `encrypted` cast) respaldado por la `APP_KEY` (AES-256-CBC con HMAC integrado contra manipulación).
     - En `AgentRegistrationService`, persistir el secreto encriptado junto al hash de verificación.
     - En `AgentHttpClient::forAgent($agent)`, desencriptar en memoria de forma efímera para calcular el HMAC `X-Signature`.
   - *Ventajas:*
     - Cero cambios destructivos en el contrato de API entre Dashboard y Agentes.
     - Plena compatibilidad con la suite de pruebas existente y con los agentes ya desplegados.
     - Protección de datos en reposo: si la base de datos se filtra, los secretos están cifrados con la clave maestra de la aplicación.

---

## 3. Especificación de Cambios

### A. Migración de Esquema (`jg_dashboard`)
- Archivo: `database/migrations/2026_09_12_000001_add_permanent_secret_encrypted_to_dashboard_agents_table.php`.
- Añade columna `text('permanent_secret_encrypted')->nullable()->after('permanent_secret_hash')`.

### B. Modelo `Agent` (`jg_dashboard/app/Models/Agent.php`)
- Incorpora `permanent_secret_encrypted` en `$fillable` y `$hidden`.
- Aplica el cast nativo `'permanent_secret_encrypted' => 'encrypted'`.
- Actualiza el método `markAsActive(string $secretHash, ?string $plainSecret = null): void`.
- Proporciona el método auxiliar `getDecryptedSecret(): ?string` con rescate defensivo ante inconsistencias.

### C. Registro de Agentes (`jg_dashboard/app/Services/Agents/AgentRegistrationService.php`)
- En `consumeBootstrapTokenAndActivate(...)`, suministra `$permanentSecret` a `markAsActive($secretHash, $permanentSecret)`.

### D. Cliente HTTP del Control Plane (`jg_dashboard/app/Services/Agents/AgentHttpClient.php`)
- En el método de factoría `forAgent(Agent $agent)`, prioriza `$agent->getDecryptedSecret()` sobre cualquier variable de entorno o fallback genérico.

### E. Seeder de Entorno (`jg_dashboard/database/seeders/DatabaseSeeder.php`)
- Los agentes pre-provisionados (Conversacional y DevOps) se siembran con su `permanent_secret_encrypted` correspondiente a sus secretos respectivos.
