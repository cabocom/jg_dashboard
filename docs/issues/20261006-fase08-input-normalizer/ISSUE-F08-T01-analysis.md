# Análisis Técnico: ISSUE-F08-T01 (Módulo base de adaptadores de estímulos externos - Input Normalizer)

- **Issue ID:** `ISSUE-F08-T01`
- **GitLab Issue:** `jg_dashboard#27`
- **Fase:** FASE 08 (Adaptadores de Estímulos Externos)
- **Prioridad:** High
- **Complejidad:** M
- **Dependencias:** `ISSUE-F06-T02`

---

## 1. Contexto y Problema

El diseño arquitectónico del ecosistema jG impone que los agentes (`jg_agente`) residan en entornos de ejecución estrictamente aislados y consuman únicamente el contrato formal canónico `AgentInput`. 

Cualquier estímulo externo proveniente del mundo real llega en formatos profundamente heterogéneos:
- Peticiones REST estándar con campos variables (`prompt`, `query`, `input`, `message`).
- Webhooks de plataformas CI/CD (GitLab, GitHub) con estructuras complejas de eventos (`push`, `pipeline`, `pull_request`).
- Asistentes y bots conversacionales (Slack, Telegram) con cargas anidadas de mensajes y chats.
- Consultas mediante parámetros de consulta URL (`query string`).

Sin una capa normalizadora formal, cada cliente o integración requeriría lógica ad-hoc dentro de los controladores o forzaría al agente a conocer las particularidades de cada cliente externo, violando el principio de desacoplamiento.

---

## 2. Requisitos y Criterios de Aceptación

1. **Ingesta Arbitraria:**
   - Aceptar objetos `Illuminate\Http\Request` o matrices asociativas provenientes de JSON, query string, formularios o webhooks.
2. **Generación Consistente de Identificadores:**
   - `execution_id`: UUIDv4 canónico para cada ciclo de ejecución.
   - `thread_id`: Preservar identificadores de sesión o pipeline (`chat_id`, `conversation_id`, `pipeline_id`) o generar UUIDv4 determinista.
   - `correlation_id`: Propagar `X-Correlation-ID` o generar identificador único de trazabilidad distribuida.
3. **Síntesis Contextual de Instrucciones:**
   - Detectar la fuente y sintetizar automáticamente prompts legibles para los modelos cognitivos ante eventos de control de versiones o integración continua.
4. **Construcción Canónica de `AgentInput`:**
   - Entregar el esquema formal con `execution_id`, `thread_id`, `correlation_id`, `prompt`, `user_prompt`, `parameters`, `user_context` y `metadata`.

---

## 3. Arquitectura y Solución

Se implementó el contrato `InputNormalizerInterface` en `App\Adapters\Contracts\InputNormalizerInterface.php` y su implementación de referencia `App\Adapters\Normalizers\InputNormalizer.php`.

### Esquema de Normalización
```text
  Estímulo Externo (JSON / Webhook CI/CD / Chatbot / Query String)
                                 │
                                 ▼
                     [InputNormalizer::normalize]
                                 │
         ┌───────────────────────┼───────────────────────┐
         ▼                       ▼                       ▼
 [Detección de Fuente]  [Extracción / Síntesis]  [Consistencia IDs]
 (gitlab, github, slack,   de Prompt Contextual    (execution_id, thread_id,
  telegram, chatbot, etc.)                          correlation_id)
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 ▼
                      ┌──────────────────────┐
                      │ Contrato AgentInput  │
                      │  - execution_id      │
                      │  - thread_id         │
                      │  - correlation_id    │
                      │  - prompt            │
                      │  - user_prompt       │
                      │  - parameters        │
                      │  - user_context      │
                      │  - metadata          │
                      └──────────────────────┘
```
