# Walkthrough: ISSUE-F08-T03 (Mock Asterisk/Chat Adapter)

- **Issue ID:** `ISSUE-F08-T03`
- **GitLab Issue:** `jg_dashboard#29`
- **Fecha:** 6 de octubre de 2026

---

## 1. Implementación Técnica

### 1.1 Adaptador (`app/Adapters/Conversational/MockAsteriskChatAdapter.php`)
- **Detección de Tipo de Simulación:** Identifica si el estímulo corresponde a una llamada de voz Asterisk (`call_id`, `caller_id`, `transcript`, `speech`, `SIP/*`) o a una sesión de chat (`chat_id`, `message`).
- **Construcción de `AgentInput`:**
  - `prompt`: Extraído del texto o transcript de la llamada.
  - `thread_id`: Mapeado deterministamente a `call_id` o `chat_id`.
  - `user_context`: Identifica al llamante con rol `caller`.
  - `parameters`: Inyecta `is_telephony: true` y el canal telefónico simulado.
- **Resolución de Agente:** Prioriza automáticamente a `jg_agente_conversacional` (`a0000000-0000-0000-0000-000000000001` o por coincidencia de nombre).
- **Serialización Especializada:**
  - Formato Asterisk: Devuelve `tts_text`, `playback_action: PLAY_TTS`, `hangup: false` y el turno de diálogo `transcript_turn`.
  - Formato Chat: Devuelve `response`, `sender`, `chat_id` y bloque `conversation`.
- **Auditoría:** Registra eventos `adapter.mock_conversational.received` y `adapter.mock_conversational.completed` en `dashboard_audit_events`.

### 1.2 Controlador HTTP (`MockConversationalAdapterController.php`)
- Ubicado en `app/Http/Controllers/Api/Adapters/MockConversationalAdapterController.php`.
- Gestiona excepciones y emite respuestas JSON normalizadas.

### 1.3 Rutas Expuestas (`routes/api.php`)
- `POST /api/v1/adapters/mock-asterisk`
- `POST /api/v1/adapters/mock-asterisk/{agent_id}`
- `POST /api/v1/adapters/mock-conversational`
- `POST /api/v1/adapters/mock-conversational/{agent_id}`

---

## 2. Cobertura de Pruebas

### Pruebas Unitarias (`tests/Unit/MockAsteriskChatAdapterTest.php`)
- `transforms simulated Asterisk call into AgentInput and serializes TTS playback output`: Valida la ingesta de parámetros telefónicos, construcción de `AgentInput` y respuesta `PLAY_TTS`.
- `transforms simulated Chat message into AgentInput and serializes conversational chat reply`: Valida la sesión de chat y formato conversacional.

### Pruebas Funcionales (`tests/Feature/MockConversationalAdapterApiTest.php`)
- Simulación completa de llamada telefónica sin PBX real vía `POST /api/v1/adapters/mock-asterisk`.
- Simulación de sesión de chat vía `POST /api/v1/adapters/mock-conversational`.
- Enrutamiento con `agent_id` explícito en URL.
- Retorno 404 ante agente inexistente y 400 ante agente OFFLINE.
- Retorno 502 ante fallo de conectividad upstream.
- Propagación de cabecera `X-Correlation-ID`.
- Verificación de eventos en `dashboard_audit_events`.

```bash
vendor/bin/pest tests/Unit/MockAsteriskChatAdapterTest.php tests/Feature/MockConversationalAdapterApiTest.php
```
**Resultado:** 9 pruebas pasadas al 100% (33 assertions en 0.46s).
Suite global de `jg_dashboard`: 315 pruebas pasadas al 100% (1459 assertions en 5.5s).
