# Análisis Técnico: [ISSUE-F06-T01] Cliente HTTP interno para comunicación con jg_agente

**Issue ID:** ISSUE-F06-T01  
**Fase:** FASE 06 (Cliente HTTP proxy y orquestación de ejecuciones hacia jg_agente)  
**Prioridad:** Critical  
**Complejidad:** M  
**Dependencias:** ISSUE-F01-T01  

---

## 1. Contexto y Objetivos

En la arquitectura desacoplada de jG, `jg_dashboard` actúa como el **Control Plane** expuesto externamente, mientras que las instancias de `jg_agente` residen en redes privadas internas aisladas. Para orquestar ejecuciones, inspeccionar trazas de ejecución, consultar el catálogo de herramientas y gestionar aprobaciones/rechazos HITL, el Dashboard requiere un cliente HTTP interno robusto, tipado y altamente resiliente.

El objetivo de este issue es implementar el cliente HTTP de comunicación interna (`AgentHttpClient`) con las siguientes capacidades:
1. **Firmado Criptográfico:** Autenticación bidireccional y cabeceras de control (`X-Control-Plane`, `X-Control-Plane-Secret`, `X-Timestamp`, `X-Signature` calculada con HMAC-SHA256, y `X-Correlation-ID`).
2. **Resiliencia y Tolerancia a Fallos:** Configuración de timeouts de red, reintentos automáticos con backoff exponencial/lineal en fallos transitorios (5xx / caídas de red) y captura controlada de excepciones sin interrumpir la experiencia de usuario.
3. **Mapeo de Rutas del Execution Plane (`jg_agente`):**
   - Chequeo de salud y disponibilidad (`GET /health` con fallback a `GET /`).
   - Inspección de capacidades activas (`GET /capabilities`).
   - Disparo y reanudación de ejecuciones cognitivas (`POST /executions/trigger`).
   - Consulta de estado y pasos (`GET /executions/{thread_id}`).
   - Aprobación de checkpoints HITL (`POST /executions/{thread_id}/approve`).
   - Rechazo y cancelación HITL (`POST /executions/{thread_id}/reject`).
   - Inspección de métricas de telemetría (`GET /metrics`).
4. **Enlace con Entidad Eloquent `Agent`:** Método estático `forAgent(Agent $agent)` para resolver dinámicamente endpoints registrados en `metadata_json['api_url']` e inyectar `X-Agent-ID`.

---

## 2. Arquitectura de la Solución

### 2.1 Configuración Centralizada (`config/agent.php`)
Se desacopla la parametrización de red permitiendo sobreescritura limpia mediante variables de entorno:
- `base_url`: `env('JG_AGENTE_API_URL', env('AGENT_API_URL', 'http://jg_agente:8000'))`
- `internal_secret`: `env('JG_AGENTE_SECRET', env('AGENT_INTERNAL_SECRET', 'secret_internal_control_plane'))`
- `timeout`: `env('AGENT_HTTP_TIMEOUT', 15)`
- `retry_times`: `env('AGENT_HTTP_RETRY_TIMES', 2)`
- `retry_sleep_ms`: `env('AGENT_HTTP_RETRY_SLEEP_MS', 200)`
- `verify_ssl`: `env('AGENT_HTTP_VERIFY_SSL', true)`

### 2.2 Excepciones de Dominio (`app/Exceptions/AgentHttpException.php`)
Se define una jerarquía tipada de excepciones:
- `AgentHttpException`: Excepción base con código de estado HTTP y cuerpo de respuesta.
- `AgentConnectionException`: Fallos de conectividad, resolución DNS o caídas de socket.
- `AgentTimeoutException`: Expiración de tiempo de espera al dialogar con el agente.

### 2.3 Servicio Cliente (`app/Services/Agents/AgentHttpClient.php`)
- Utiliza la fachada `Illuminate\Support\Facades\Http` de Laravel sobre Guzzle.
- Inyecta cabeceras de firma computadas en cada invocación:
  $$\text{Signature} = \text{HMAC-SHA256}(\text{Timestamp} + '.' + \text{Body}, \text{Secret})$$
- Captura `RequestException` y `ConnectionException`, formateando respuestas estructuradas:
  ```php
  [
      'success' => bool,
      'status_code' => int,
      'data' => ?array,
      'error' => ?string,
      'latency_ms' => float,
  ]
  ```

---

## 3. Criterios de Aceptación Verificados

- [x] Cliente HTTP realiza peticiones firmadas hacia `jg_agente` con cabeceras `X-Control-Plane`, `X-Control-Plane-Secret`, `X-Timestamp` y `X-Signature`.
- [x] Manejo de timeouts configurables con mapeo adecuado a código 504.
- [x] Reintentos automáticos en errores 5xx y desconexiones de red (`retry(2, 200, throw: false)`).
- [x] Fallback automático de `/health` a `/` si una versión previa responde 404.
- [x] Soporte para todos los endpoints clave de `jg_agente` (`trigger`, `executions/{thread_id}`, `approve`, `reject`, `capabilities`, `metrics`).
- [x] Pruebas unitarias completas simulando respuestas exitosas, caídas de red, timeouts y reintentos.
