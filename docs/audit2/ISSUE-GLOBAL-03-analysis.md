# Análisis Técnico: [GLOBAL-03] Incompatibilidad de Timeouts HTTP (15s) frente al Ciclo Cognitivo
## GitLab Issues: [jg_agente#54](https://gitlab.com/javiergalvez-ia/jg_agente/-/work_items/54) · [jg_dashboard#44](https://gitlab.com/javiergalvez-ia/jg_dashboard/-/work_items/44)

---

## 1. Diagnóstico del Estado Previo

Durante la Auditoría 2 independiente, se detectó una grave incompatibilidad operativa y de latencia entre el cliente HTTP del Control Plane (`jg_dashboard`) y el motor de ejecución cognitiva (`jg_agente`):

1. **Timeout HTTP Artificialmente Restrictivo (15 segundos):**
   - En `jg_dashboard/config/agent.php:22`, el parámetro `'timeout'` estaba configurado con un valor por defecto de `15` segundos:
     ```php
     'timeout' => (int) env('AGENT_HTTP_TIMEOUT', 15),
     ```
   - En `AgentHttpClient.php:53`, el fallback del constructor también utilizaba 15 segundos si no se definía en el contenedor de configuración.
   - El cliente HTTP ejecutaba las peticiones hacia `/executions/trigger` respetando este límite de 15s.

2. **Topología del Grafo Cognitivo y Latencia Intrínseca:**
   - El ciclo cognitivo de `jg_agente` implementa un grafo LangGraph de 9 a 10 nodos secuenciales:
     `observe` ➔ `reason` ➔ `plan` ➔ `risk_assess` ➔ `human_approval` (si procede) ➔ `execute` ➔ `verify` ➔ `evaluate` ➔ `conclude` (o `rollback`).
   - Cada nodo interactúa con proveedores de LLM (Gemini, Claude, Groq, Ollama) y ejecuta herramientas especializadas (Docker SDK, peticiones HTTP, etc.). Cada una de estas invocaciones tarda individualmente entre 2 y 15 segundos.
   - En peticiones complejas (ej. multi-paso, auto-remediación, reinicio e inspección de contenedores), el tiempo total de procesamiento síncrono suele situarse entre 20 y 60 segundos.

3. **Impacto Operacional:**
   - **504 Gateway Timeout Prematuro:** Al superar los 15 segundos, Laravel cortaba la conexión HTTP lanzando `ConnectionException` ("Operation timed out after 15000 milliseconds").
   - **Ejecuciones Fantasmas Huérfanas:** En `jg_agente`, la corrutina continuaba ejecutándose o terminaba con éxito, pero la respuesta jamás era recibida por el Dashboard.
   - **Inconsistencias de Estado y Vacío de Trazabilidad:** El Dashboard no registraba la tarea en `agent_tasks` o quedaba en un estado indeterminado, provocando que los operadores reintentaran impulsivamente peticiones concurrentes ("efecto encasquillado").

---

## 2. Alternativas Evaluadas

1. **Mantener timeout bajo y forzar únicamente modo asíncrono (sync=false):**
   - *Evaluación:* Que todas las ejecuciones cognitivas devuelvan `202 Accepted` de inmediato y se consulten siempre mediante polling.
   - *Decisión:* Descartada como solución única. Aunque el modo asíncrono es óptimo para tareas pesadas, elimina la agilidad de flujos conversacionales interactivos de consulta rápida (ej. "¿qué contenedores están caídos?", "¿qué tiempo hace?"), forzando complejidad de polling innecesaria en la API REST y cli scripts.

2. **Timeout Infinito / Deshabilitado:**
   - *Evaluación:* Quitar el timeout (`timeout = 0`).
   - *Decisión:* Descartada por violar buenas prácticas de resiliencia (CWE-400). Un agente bloqueado o un deadlock de red dejaría hilos de PHP-FPM retenidos indefinidamente agotando el pool de conexiones.

3. **Solución Híbrida Equilibrada (Opción Seleccionada):**
   - **Elevar el timeout síncrono a 120 segundos:** Cobertura holgada para el ciclo cognitivo de 9-10 nodos sin riesgo de corte prematuro, manteniendo un límite de seguridad razonable.
   - **Añadir soporte explícito de ejecución asíncrona en la UI y API:** Permitir a operadores elegir modo asíncrono (`async=true`) con polling automático cada 3 segundos en `/console` para tareas intensivas o multi-paso.
   - **Persistencia garantizada ante fallos upstream:** Si se produce un timeout o error de pasarela (504/503), registrar la tarea en `AgentTask` con estado `FAILED` y la traza técnica del fallo, eliminando ejecuciones huérfanas o vacíos de información.

---

## 3. Especificación de Cambios

### A. Configuración y Cliente HTTP (`jg_dashboard`)
- `config/agent.php`: `AGENT_HTTP_TIMEOUT` elevado por defecto de 15 a 120 segundos.
- `app/Services/Agents/AgentHttpClient.php`:
  - Fallback actualizado: `$this->timeout = $timeout ?? (int) (Config::get('agent.timeout') ?: 120);`.
  - Métodos fluidos incorporados: `withTimeout(int $timeout): self` y `getTimeout(): int`.

### B. Controlador de Ejecuciones (`ExecutionController.php`)
- Captura de fallos en pasarela (`!$response['success']`):
  - Actualización / creación inmediata de `AgentTask` con status `FAILED`, incluyendo el código upstream (`504`/`503`) y el mensaje de error.

### C. Consola Web (`index.blade.php` y `console.js`)
- `index.blade.php`: Checkbox interactivo en el formulario de disparo para "Modo Asíncrono (Recomendado para tareas multi-paso o de alta latencia con polling automático)".
- `console.js`:
  - Captura del estado `trigger-async` y envío de `async: true` en el payload JSON.
  - Feedback visual adaptado (`Encolando en agente...`, toast informativo).
  - Integración nativa con `loadExecutionTrace()` para iniciar polling cada 3s hasta la finalización.

### D. Infraestructura y Docker Compose
- `docker-compose.yml`: Inyección de `AGENT_HTTP_TIMEOUT: ${AGENT_HTTP_TIMEOUT:-120}` en el servicio `jg_dashboard`.
- `.env.example` y `jg_dashboard/.env.example`: Adición de `AGENT_HTTP_TIMEOUT=120`.

---

## 4. Matriz de Compatibilidad y Seguridad

| Requisito | Estado | Justificación |
|---|---|---|
| **Ciclo Cognitivo Completo (9-10 nodos)** | ✅ Soportado | 120s permite holgadamente 12s promedio por nodo en flujos síncronos. |
| **Resiliencia ante Caídas** | ✅ Garantizada | Timeout explícito previene bloqueo indefinido de workers PHP-FPM. |
| **Trazabilidad de Errores** | ✅ Completa | Errores 504 persisten en `agent_tasks` como `FAILED` con detalles del error. |
| **Flujos Asíncronos Pesados** | ✅ Habilitado | Soporte `async=true` vía API y UI con polling automático en segundo plano. |
| **Compatibilidad con Agentes Existentes** | ✅ 100% | Ninguna rotura de API ni cambio destructivo en contratos de comunicación. |
