# Contexto del Proyecto: [DASH-SEC-02] Endpoint de Heartbeat sin Firma HMAC Transmite Secreto en Plaintext

## Metadatos de la Incidencia
- **ID:** `jg_dashboard#48`
- **Código:** `DASH-SEC-02`
- **Severidad:** 🟠 ALTO
- **CWE:** CWE-319 (Cleartext Transmission of Sensitive Information)
- **Componentes Afectados:**
  - [`jg_dashboard/routes/api.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/routes/api.php)
  - [`jg_dashboard/app/Http/Controllers/Api/AgentHeartbeatController.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Http/Controllers/Api/AgentHeartbeatController.php)
  - [`jg_dashboard/app/Http/Middleware/VerifyHmacSignature.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Http/Middleware/VerifyHmacSignature.php)
  - [`jg_dashboard/app/Services/Agents/AgentHeartbeatService.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Services/Agents/AgentHeartbeatService.php)
  - [`jg_dashboard/app/Models/Agent.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/app/Models/Agent.php)
  - [`jg_dashboard/bootstrap/app.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/bootstrap/app.php)
  - [`jg_dashboard/tests/Feature/AgentHeartbeatApiTest.php`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_dashboard/tests/Feature/AgentHeartbeatApiTest.php)
  - [`jg_agente/app/core/heartbeat.py`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_agente/app/core/heartbeat.py)
  - [`jg_agente/tests/test_heartbeat_service.py`](file:///Users/javiergalvez/Documents/Code/javiergalvez-ia/jg_agente/tests/test_heartbeat_service.py)

---

## Descripción de la Vulnerabilidad
El endpoint `POST /api/v1/agents/heartbeat` (y su alias raíz `/api/agents/heartbeat`) carecía de protección bajo el esquema de firma criptográfica HMAC-SHA256 establecido entre el Control Plane y los agentes.
En su lugar, la autenticación del agente dependía del envío directo de su credencial permanente en texto plano (`permanent_secret`) mediante la cabecera `Authorization: Bearer <secret>` o `X-Agent-Secret: <secret>`, exponiéndola en el tráfico de red.

## Impacto en Seguridad
La transmisión de secretos permanentes en texto plano a través de peticiones HTTP periódicas (cada pocos segundos) crea un vector crítico de interceptación ante proxies reversos, inspectores de tráfico, balanceadores de carga o ataques Man-In-The-Middle (MitM) en entornos sin TLS estricto o con terminación SSL intermedia. La captura de dicho secreto permitiría a un atacante suplantar al agente, falsificar estados operativos y participar fraudulentamente en flujos de orquestación y aprobaciones HITL.
