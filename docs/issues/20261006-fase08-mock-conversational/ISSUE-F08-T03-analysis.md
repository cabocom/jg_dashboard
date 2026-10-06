# Análisis Técnico: ISSUE-F08-T03 (Adaptador simulado para interfaz conversacional - Mock Asterisk/Chat)

- **Issue ID:** `ISSUE-F08-T03`
- **GitLab Issue:** `jg_dashboard#29`
- **Fase:** FASE 08 (Adaptadores de Estímulos Externos)
- **Prioridad:** Medium
- **Complejidad:** S
- **Dependencias:** `ISSUE-F08-T01` (Input Normalizer)

---

## 1. Contexto y Problema

El agente conversacional (`jg_agente_conversacional`) está diseñado para atender interacciones humanas naturales, tanto en interfaces de mensajería (chat) como a través de pasarelas de telefonía VoIP / IP-PBX (Asterisk ARI / AMI / SIP).

Sin embargo, en entornos de desarrollo local, pipelines de CI/CD o pruebas de integración automatizadas, **no existe ni es viable desplegar un servidor Asterisk PBX real con troncales SIP activas**. 

Por ello, el objetivo de `ISSUE-F08-T03` es crear un adaptador simulado capaz de:
1. Recibir estímulos simulando eventos de telefonía Asterisk (con parámetros como `call_id`, `caller_id`, `transcript`, `speech`, `channel`).
2. Recibir estímulos simulando sesiones de chat (`chat_id`, `sender`, `message`).
3. Transformar dichos eventos al contrato formal canónico `AgentInput`.
4. Enrutar y ejecutar la llamada hacia el agente conversacional mediante `AgentHttpClient`.
5. Serializar la respuesta `AgentOutput` en formato de telefonía (`tts_text`, `playback_action: PLAY_TTS`, `hangup: false`, `transcript_turn`) o chat.

---

## 2. Requisitos y Criterios de Aceptación

- [x] Crear adaptador de prueba que simule la recepción de mensajes conversacionales transformándolos en `AgentInput` y serializando `AgentOutput`.
- [x] Permitir disparar pruebas conversacionales simulando clientes de voz o chat sin depender de un servidor Asterisk real.
- [x] Preservar la continuidad de la llamada / chat utilizando `call_id` / `chat_id` como `thread_id` en el grafo LangGraph de `jg_agente`.
- [x] Exponer endpoints dedicados en la API REST de `jg_dashboard`.
- [x] Cobertura de pruebas unitarias y de integración pasando al 100%.
