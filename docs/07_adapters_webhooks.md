# 07. Pasarela de Adaptadores y Estímulos Externos (`App\Adapters`)

[← Volver a Observabilidad y Resiliencia (06)](06_observability_resilience.md) | [Volver al README Principal](../README.md)

---

## 1. Visión General y Propósito

El ecosistema jG prohíbe terminantemente que los agentes cognitivos de ejecución (`jg_agente`) expongan interfaces o protocolos hacia clientes externos. Los agentes operan en redes privadas y consumen exclusivamente el contrato canónico `AgentInput`, devolviendo `AgentOutput`.

Para permitir la integración fluida con sistemas heterogéneos del mundo real —tales como **disparadores CI/CD** (GitLab CI, GitHub Actions), **asistentes conversacionales y chatbots** (Slack, Telegram, WhatsApp) o **aplicaciones REST/SaaS externas**—, `jg_dashboard` proporciona una capa modular de adaptadores (`App\Adapters`).

```text
  CLIENTES EXTERNOS (GitLab / GitHub / Slack / Chatbots / Webhooks)
                     │
                     │ HTTP POST (X-Webhook-Secret, X-Correlation-ID)
                     ▼
┌────────────────────────────────────────────────────────────────────────┐
│                      jg_dashboard (Control Plane)                      │
│                                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │     Controlador Webhook: WebhookAdapterController                │  │
│  │     - POST /api/v1/adapters/webhook                              │  │
│  │     - POST /api/v1/adapters/webhook/{agent_id}                   │  │
│  └──────────────────────────────────┬───────────────────────────────┘  │
│                                     │                                  │
│                                     ▼                                  │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │               Orquestador: HttpWebhookAdapter                    │  │
│  │                                                                  │  │
│  │  1. Verificación de Seguridad (X-Webhook-Secret / Bearer Token)  │  │
│  │  2. Normalización de Entrada via InputNormalizer                 │  │
│  │     ┌──────────────────────────────────────────────────────┐     │  │
│  │     │ Payload Heterogéneo ──► Contrato Canónico AgentInput │     │  │
│  │     └──────────────────────────────────────────────────────┘     │  │
│  │  3. Resolución Dinámica de Agente (por URL, payload o fallback) │  │
│  │  4. Auditoría Inmutable (adapter.webhook.received)               │  │
│  │  5. Invocación Criptográfica HMAC via AgentHttpClient            │  │
│  │  6. Persistencia de Tarea en AgentTask (PENDING/COMPLETED)       │  │
│  │  7. Formateo Adaptativo de Salida via ClientOutputFormatter      │  │
│  │     ┌──────────────────────────────────────────────────────┐     │  │
│  │     │ AgentOutput ──► generic | chatbot | ci_cd            │     │  │
│  │     └──────────────────────────────────────────────────────┘     │  │
│  │  8. Auditoría Inmutable (adapter.webhook.completed)              │  │
│  └──────────────────────────────────┬───────────────────────────────┘  │
│                                     │                                  │
│                                     ▼                                  │
│                    ┌─────────────────────────────────┐                 │
│                    │     PostgreSQL del Dashboard    │                 │
│                    │ (dashboard_audit_events / tasks)│                 │
│                    └─────────────────────────────────┘                 │
└──────────────────────────────────────┬─────────────────────────────────┘
                                       │ HTTP / HMAC-SHA256 (Red Privada)
                                       ▼
┌────────────────────────────────────────────────────────────────────────┐
│                      jg_agente (Execution Plane)                       │
│                         (FastAPI / LangGraph)                          │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Normalización Canónica de Estímulos (`InputNormalizer`)

La clase `App\Adapters\Normalizers\InputNormalizer` implementa la interfaz `InputNormalizerInterface` y transforma estímulos heterogéneos en el contrato formal `AgentInput`.

### 2.1 Fuentes Detectadas Automáticamente
El normalizador inspecciona encabezados y campos estructurales para inferir la fuente:
- **`gitlab`:** Detectado ante cabecera `X-Gitlab-Event` o campo `object_kind` (`push`, `pipeline`, `merge_request`).
- **`github`:** Detectado ante cabecera `X-Github-Event` o `X-Github-Delivery`.
- **`slack`:** Detectado ante `command`, `event.type` o cabecera `X-Slack-Signature`.
- **`telegram`:** Detectado ante campos `update_id` y `message`.
- **`chatbot`:** Detectado ante identificadores conversacionales como `chat_id` o `conversation_id`.
- **`ci_cd`:** Detectado ante `pipeline_id`, `build_id` o `ci_event`.
- **`generic`:** Fallback para peticiones JSON estándar.

### 2.2 Síntesis Inteligente del Prompt
Si el payload no contiene un campo explícito `prompt` o `message`, el normalizador sintetiza una instrucción contextual legible para los LLMs del agente:
- **GitLab Push:**
  `"[GitLab CI/CD: Push] {project} ({ref}) por {user}: {commit_message}"`
- **GitLab Pipeline:**
  `"[GitLab CI/CD: Pipeline] {project} ({ref}) estado '{status}' disparado por {user}"`
- **GitHub PR:**
  `"[GitHub CI/CD: PR] {repo} PR '{title}' ({action}) por {sender}"`
- **Chatbots:** Extrae `message.text` o `event.text` anidados.

### 2.3 Garantía de Identificadores y Correlación
- **`execution_id`:** Genera un UUIDv4 criptográfico determinista para cada estímulo si no se especifica.
- **`thread_id`:** Preserva el identificador de conversación o pipeline del cliente (`chat_id`, `conversation_id`, `channel_id`, `pipeline_id`) permitiendo la continuidad de memoria en el grafo de LangGraph.
- **`correlation_id`:** Propaga la cabecera `X-Correlation-ID` entrante hacia `AgentInput`, hacia las firmas salientes del `AgentHttpClient` y la devuelve en la cabecera de la respuesta HTTP del Dashboard.

---

## 3. Formateo Adaptativo de Salida (`ClientOutputFormatter`)

La clase `App\Adapters\Formatters\ClientOutputFormatter` implementa `OutputFormatterInterface` y formatea la respuesta `AgentOutput` devuelta por `jg_agente` según el perfil del consumidor:

### 3.1 Perfil Genérico (`format: generic`)
Ideal para integraciones API y microservicios:
```json
{
  "success": true,
  "status": "COMPLETED",
  "execution_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "thread_id": "thread-1234",
  "output": "Análisis de clúster finalizado sin anomalías.",
  "data": { ... },
  "metadata": {
    "formatted_by": "ClientOutputFormatter",
    "format": "generic"
  }
}
```

### 3.2 Perfil Chatbot (`format: chatbot`)
Estructura ligera orientada a aplicaciones de mensajería (Slack, Telegram, WhatsApp):
```json
{
  "response": "El clima actual en Madrid es de 16°C con lluvias.",
  "text": "El clima actual en Madrid es de 16°C con lluvias.",
  "thread_id": "chat-channel-99",
  "execution_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "status": "completed",
  "success": true,
  "conversation": {
    "chat_id": "chat-channel-99",
    "is_final": true
  }
}
```

### 3.3 Perfil CI/CD (`format: ci_cd`)
Estructura orientada a runners y pipelines automatizados:
```json
{
  "status": "success",
  "job_status": "COMPLETED",
  "summary": "Análisis de seguridad completado. Dependencias íntegras.",
  "execution_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "thread_id": "pipe-pipeline-400",
  "pipeline_status": "PASSED",
  "details": {
    "cognitive_steps_count": 4,
    "requires_approval": false
  }
}
```

---

## 4. Orquestación y Seguridad (`HttpWebhookAdapter`)

El orquestador central `App\Adapters\HttpWebhookAdapter` ejecuta el ciclo de vida del estímulo:

1. **Autenticación Opcional/Obligatoria:**
   - Si `config('adapters.webhook_secret')` está configurado (`ADAPTER_WEBHOOK_SECRET`), valida la firma/clave mediante:
     - Cabecera `X-Webhook-Secret: <secreto>`
     - Cabecera `Authorization: Bearer <secreto>`
     - Parámetro en query `?token=<secreto>`
   - Retorna `401 Unauthorized` si la credencial no coincide.
2. **Resolución del Agente:**
   - Si se especifica en la URL (`POST /api/v1/adapters/webhook/{agent_id}`) o en el payload (`agent_id`), valida que el agente exista (404) y se encuentre en estado `ACTIVE` (400).
   - Si no se especifica, resuelve el primer agente activo registrado en la base de datos o utiliza el fallback `config('adapters.default_agent_id')`.
3. **Modos de Ejecución:**
   - **Síncrono (por defecto):** Espera la resolución del grafo del agente y responde `200 OK` con el payload transformado.
   - **Asíncrono (`async: true` o cabecera `X-Async: true`):** Delega la ejecución en segundo plano al agente y responde de inmediato `202 Accepted` con estado `PENDING`.
4. **Trazabilidad y Auditoría Forense:**
   - Registra eventos inmutables en `dashboard_audit_events`:
     - `adapter.webhook.received`: Carga recibida, IP de origen, User-Agent, prompt sintetizado y agente seleccionado.
     - `adapter.webhook.completed`: Estado final de la ejecución, duración y correlación.
   - Actualiza o crea la tarea en `dashboard_agent_tasks` (`AgentTask`).

---

## 5. Endpoints de la API

| Método | Endpoint | Autenticación | Descripción |
| :--- | :--- | :--- | :--- |
| **POST** | `/api/v1/adapters/webhook` | `X-Webhook-Secret` (Configurable) | Recepción de estímulo genérico con enrutamiento automático. |
| **POST** | `/api/v1/adapters/webhook/{agent_id}` | `X-Webhook-Secret` (Configurable) | Recepción de estímulo dirigido a un agente específico. |

---

## 6. Configuración (`config/adapters.php`)

```php
return [
    // Secreto compartido para autenticar peticiones externas
    'webhook_secret' => env('ADAPTER_WEBHOOK_SECRET', null),

    // Obligatoriedad estricta de autenticación (true = rechaza si no hay secreto)
    'require_auth' => (bool) env('ADAPTER_REQUIRE_AUTH', false),

    // Identificador de agente predeterminado ante consultas no explícitas
    'default_agent_id' => env('ADAPTER_DEFAULT_AGENT_ID', env('DEFAULT_AGENT_ID', 'a0000000-0000-0000-0000-000000000001')),

    // Formato de salida por defecto ('generic', 'chatbot', 'ci_cd')
    'default_format' => env('ADAPTER_DEFAULT_FORMAT', 'generic'),

    // Timeout máximo para peticiones síncronas hacia el agente (segundos)
    'timeout' => (int) env('ADAPTER_TIMEOUT', 120),
];
```

---

## 7. Ejemplos Prácticos de Integración

### 7.1 Petición Genérica REST vía cURL
```bash
curl -X POST http://localhost:8003/api/v1/adapters/webhook \
  -H "Content-Type: application/json" \
  -H "X-Correlation-ID: cid-mon-001" \
  -d '{
    "prompt": "Verifica el estado del clúster de base de datos",
    "parameters": { "environment": "production" },
    "format": "generic"
  }'
```

### 7.2 Webhook desde GitLab CI/CD
Configurar en GitLab (Settings > Webhooks) con URL de destino:
`http://jg-dashboard.internal:8000/api/v1/adapters/webhook/a0000000-0000-0000-0000-000000000002`
- **Secret token:** Configurado en `ADAPTER_WEBHOOK_SECRET`.
- **Trigger:** Push events o Pipeline events.
- **Resultado:** El Dashboard normaliza automáticamente el evento de commit y activa el Agente DevOps.

### 7.3 Asistente Conversacional / Chatbot
```bash
curl -X POST http://localhost:8003/api/v1/adapters/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "chat_id": "telegram-group-ops-88",
    "message": {
      "text": "¿Cuál es la previsión meteorológica para la base de datos?"
    },
    "format": "chatbot"
  }'
```

---

## 8. Navegación

- [← Volver a Observabilidad y Resiliencia (06)](06_observability_resilience.md)
- [Volver al README Principal](../README.md)
