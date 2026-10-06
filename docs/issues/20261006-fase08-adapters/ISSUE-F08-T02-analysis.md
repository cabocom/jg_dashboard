# Análisis Técnico: ISSUE-F08-T02 (Adaptador HTTP / Webhook para aplicaciones cliente)

- **Issue ID:** `ISSUE-F08-T02`
- **GitLab Issue:** `jg_dashboard#28`
- **Fase:** FASE 08 (Adaptadores de Estímulos Externos)
- **Prioridad:** High
- **Complejidad:** M
- **Dependencias:** `ISSUE-F08-T01` (Input Normalizer)

---

## 1. Contexto y Problema

Dentro del ecosistema `javiergalvez-ia`, el Control Plane (`jg_dashboard`) es el **único componente expuesto hacia el exterior**. Los agentes cognitivos (`jg_agente`) residen en redes privadas aisladas y carecen de conectores externos directos por principio de seguridad y desacoplamiento arquitectónico.

Con anterioridad a esta tarea, los únicos mecanismos para disparar ejecuciones cognitivas eran:
1. La consola web interactiva (`/console`).
2. Las acciones de Filament en el panel de administración.
3. El endpoint REST interno para operadores autenticados con JWT (`POST /api/v1/executions/trigger`).

Sistemas externos como bots conversacionales (Slack, Telegram), pipelines CI/CD (GitLab CI, GitHub Actions) o sistemas de monitorización no disponían de una vía genérica para interactuar con los agentes ni contaban con un formateo de salida acorde a sus propios protocolos.

---

## 2. Requisitos y Criterios de Aceptación

1. **Recepción genérica de estímulos:**
   - Exponer un endpoint accesible para sistemas externos (`POST /api/v1/adapters/webhook` y `POST /api/v1/adapters/webhook/{agent_id}`).
2. **Normalización a `AgentInput` (ISSUE-F08-T01):**
   - Transformar payloads arbitrarios (JSON genérico, eventos CI/CD, eventos de chat) en el contrato formal canónico `AgentInput`.
   - Garantizar la generación y preservación consistente de `execution_id`, `thread_id` (preservando identificadores de canal/conversación) y `correlation_id` (propagación de `X-Correlation-ID`).
3. **Ejecución y Delegación:**
   - Enrutar la petición hacia el agente adecuado mediante `AgentHttpClient` con firma criptográfica HMAC-SHA256.
   - Soportar modos de ejecución síncrono y asíncrono (`async=true`).
4. **Formateo de Salida Adaptativo:**
   - Transformar `AgentOutput` devuelto por el agente en el formato esperado por el cliente (`generic`, `chatbot`, `ci_cd`).
5. **Seguridad y Auditoría:**
   - Permitir autenticación configurable por token/secreto de webhook (`X-Webhook-Secret`).
   - Registrar eventos inmutables en `dashboard_audit_events` y persistir la tarea en `dashboard_agent_tasks`.

---

## 3. Arquitectura Implementada

```text
[Cliente Externo: CI/CD / Chatbot / REST]
               │
               ▼ HTTP POST (con X-Webhook-Secret, X-Correlation-ID)
[WebhookAdapterController] (/api/v1/adapters/webhook/{agent_id?})
               │
               ▼
      [HttpWebhookAdapter]
       ├── [InputNormalizer] ──► Transforma payload a canonical AgentInput
       ├── [Agent Resolution] ─► Agent::find() o Agent::active()->first()
       ├── [AgentHttpClient] ──► Firma HMAC canónica y POST /executions/trigger
       ├── [AuditEvent & AgentTask] ──► Registro inmutable de auditoría y tarea
       └── [ClientOutputFormatter] ──► Formatea AgentOutput a generic / chatbot / ci_cd
               │
               ▼ 200 OK / 202 Accepted
[Respuesta estructurada para el cliente externo]
```

---

## 4. Archivos Modificados e Implementados

1. `config/adapters.php`: Configuración de secretos, formatos por defecto y comportamiento del adaptador.
2. `app/Adapters/Contracts/InputNormalizerInterface.php`: Contrato para normalización de estímulos externos.
3. `app/Adapters/Contracts/OutputFormatterInterface.php`: Contrato para formateo de respuestas hacia clientes.
4. `app/Adapters/Normalizers/InputNormalizer.php`: Implementación de normalización multi-fuente.
5. `app/Adapters/Formatters/ClientOutputFormatter.php`: Implementación de formateo multi-canal.
6. `app/Adapters/HttpWebhookAdapter.php`: Servicio orquestador central del adaptador.
7. `app/Http/Controllers/Api/Adapters/WebhookAdapterController.php`: Controlador HTTP REST.
8. `app/Providers/AppServiceProvider.php`: Registro de singletons en el contenedor de dependencias.
9. `routes/api.php`: Registro de endpoints `/api/v1/adapters/webhook` y `/api/v1/adapters/webhook/{agent_id}`.
10. `tests/Unit/InputNormalizerTest.php`: Tests unitarios de normalización.
11. `tests/Unit/ClientOutputFormatterTest.php`: Tests unitarios de formateo de salida.
12. `tests/Feature/WebhookAdapterApiTest.php`: Tests de integración y feature del endpoint de webhook.
13. `PROJECT_CONTEXT.md`: Actualización del estado de Fase 08 y diseño arquitectónico.
14. `TODO-PENDIENTES.md`: Cierre de `ITEM-DB-02`.
