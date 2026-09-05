# Análisis Técnico: [ISSUE-F06-T04] Endpoint de inspección de capacidades: GET /api/v1/agents/{agent_id}/capabilities

**Issue ID:** ISSUE-F06-T04  
**Fase:** FASE 06 (Cliente HTTP proxy y orquestación de ejecuciones hacia jg_agente)  
**Prioridad:** Medium  
**Complejidad:** S  
**Dependencias:** ISSUE-F06-T01  

---

## 1. Contexto y Objetivos

Para la adecuada gobernanza y orquestación del ecosistema jG, los operadores humanos y el plano de control (`jg_dashboard`) necesitan conocer con precisión el inventario de herramientas (`tools`), motores cognitivos (`engines`) y extensiones (`plugins`) que cada agente tiene activos y registrados en su arranque.

Dado que los agentes `jg_agente` residen en redes privadas sin exposición externa, el Dashboard debe exponer una ruta autenticada que consulte en tiempo real la API del agente a través del cliente HTTP interno firmado (`AgentHttpClient`) y sincronice el catálogo resultante en el modelo `Agent` (`capabilities_json`).

---

## 2. Arquitectura de la Solución

### 2.1 Endpoint y Seguridad
- **Ruta Principal:** `GET /api/v1/agents/{agent_id}/capabilities`
- **Ruta Alias:** `GET /api/agents/{agent_id}/capabilities`
- **Seguridad:** Middleware `jwt.auth`. Accesible para roles autenticados (`admin`, `operator`, `viewer`).

### 2.2 Validación de Estado
- Si el `agent_id` no existe en la base de datos `dashboard_agents`, se retorna `404 Not Found`.
- Si el agente se encuentra en estado `PENDING` (no ha consumido su token ni generado credenciales permanentes), se retorna `400 Bad Request` indicando que el agente aún no está aprovisionado.

### 2.3 Consulta Delegada y Sincronización
- Se invoca `AgentHttpClient::forAgent($agent)->getCapabilities()`, enviando una petición HTTP firmada con HMAC-SHA256 y cabeceras de control (`X-Control-Plane`, `X-Timestamp`, `X-Signature`) hacia `GET /capabilities` de `jg_agente`.
- Si la llamada es exitosa, se actualiza automáticamente el campo `capabilities_json` en `dashboard_agents`, asegurando que la persistencia del Dashboard mantenga una instantánea fresca.
- Si `jg_agente` responde con error de servidor o es inaccesible, se captura de manera controlada y se retorna código `502 Bad Gateway` o `503 Service Unavailable` con mensaje descriptivo.

---

## 3. Criterios de Aceptación Verificados

- [x] Dashboard recupera el catálogo de capacidades del agente invocando `GET /capabilities` a través de `AgentHttpClient`.
- [x] Expone el inventario consolidado a operadores y usuarios autorizados mediante token JWT.
- [x] Sincroniza localmente `capabilities_json` en el modelo `Agent`.
- [x] Respeta el principio de aislamiento estricto de base de datos.
- [x] Maneja fallos de conectividad o timeouts devolviendo respuestas de error de gateway estructuradas.
