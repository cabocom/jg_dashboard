# Project Context: [GLOBAL-03] Incompatibilidad de Timeouts HTTP (15s) frente al Ciclo Cognitivo

## Componentes y Capas Afectadas

- **Control Plane Configuration (`jg_dashboard`):**
  - `config/agent.php`: Definición centralizada del parámetro de timeout con valor por defecto de 120 segundos.
  - `app/Services/Agents/AgentHttpClient.php`: Cliente HTTP firmado con fallback a 120s y métodos fluidos de modificación (`withTimeout`, `getTimeout`).
  - `app/Http/Controllers/Api/ExecutionController.php`: Orquestación de peticiones, soporte de parámetros de asincronía (`async`) y registro defensivo de tareas en `AgentTask` ante errores y timeouts de pasarela.
- **Web Console UI (`jg_dashboard`):**
  - `resources/views/console/index.blade.php`: Control interactivo para seleccionar modo asíncrono.
  - `public/js/console.js`: Envío de flags de asincronía, adaptación del spinner y polling automático cada 3 segundos tras `202 Accepted`.
- **Execution Plane (`jg_agente`):**
  - `app/api/executions.py`: Endpoint `/executions/trigger` con soporte para ejecuciones síncronas (200 OK) y asíncronas vía BackgroundTasks (202 Accepted).
  - `app/nodes/*`: Grafo cognitivo de 9-10 nodos garantizado contra cortes de timeout prematuros.
- **Entorno y Docker:**
  - `docker-compose.yml`, `.env.example`, `jg_dashboard/.env.example`: Exposición de `AGENT_HTTP_TIMEOUT=120`.

---

## Directrices de Desarrollo y Gobernanza

1. **Latencia Estimada del Grafo:**
   - Todo flujo síncrono que involucre múltiples nodos con llamadas externas a LLM debe asumir latencias entre 15s y 90s en condiciones normales.
   - El timeout por defecto de 120s proporciona margen de seguridad sin llegar a valores desproporcionados.
2. **Buenas Prácticas para Tareas Complejas:**
   - Tareas pesadas o de mantenimiento (backups, diagnósticos exhaustivos de red, auto-remediaciones multi-servicio) deben invocarse preferentemente con `async: true`.
   - El Control Plane responde inmediatamente con HTTP 202 y el cliente debe monitorear el hilo vía `GET /executions/{agent_id}/{thread_id}`.
3. **Manejo de Errores de Pasarela (504):**
   - Siempre registrar la tarea como `FAILED` con el detalle de timeout en la base de datos local para que los dashboards de observabilidad y las vistas de Filament reflejen la realidad operativa de forma inmediata.
