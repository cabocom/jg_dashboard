## Resolución y Cierre de Issue: [GLOBAL-03] Incompatibilidad de Timeouts HTTP (15s) frente al Ciclo Cognitivo

Se ha resuelto de forma integral la incompatibilidad entre el timeout por defecto del cliente HTTP en el Control Plane (15 segundos) y la duración de ejecución del ciclo cognitivo de 9-10 nodos de `jg_agente`:

### Resumen de Cambios Implementados:

1. **Elevación del Timeout HTTP Síncrono a 120s:**
   - En `jg_dashboard/config/agent.php`, se elevó el valor por defecto de `AGENT_HTTP_TIMEOUT` de 15s a 120s.
   - En `AgentHttpClient.php`, se estableció el fallback seguro a 120s y se añadieron métodos fluidos `withTimeout(int $timeout): self` y `getTimeout(): int`.
   - Propagado a `docker-compose.yml`, `.env.example` y `jg_dashboard/.env.example`.

2. **Gestión y Registro Defensivo de Tareas en el Dashboard:**
   - En `ExecutionController.php`, ante fallos de pasarela o timeouts (HTTP 504/503), se persiste inmediatamente un registro en `AgentTask` con estado `FAILED` y los detalles del error upstream. Esto previene tareas huérfanas, datos perdidos o inconsistencias en los paneles de observabilidad.

3. **Soporte de Ejecución Asíncrona en la Consola Web:**
   - Se añadió un control interactivo (checkbox toggle) en `resources/views/console/index.blade.php` para habilitar el modo asíncrono (`async=true`).
   - En `public/js/console.js`, se implementó el envío de `async: true` hacia el Control Plane y se conectó la respuesta `202 Accepted` (`status: in_progress`) con el mecanismo de polling automático cada 3 segundos hasta la finalización.

4. **Verificación y Pruebas:**
   - Nuevos tests unitarios y de integración en Pest (`AgentHttpClientTest.php`, `ExecutionTriggerApiTest.php`).
   - Suite completa de `jg_dashboard` superada: **254 passed (1213 assertions)**.
   - Suite completa de `jg_agente` superada: **361 passed**, Ruff y MyPy con 0 errores en 104 archivos.
   - Verificación integral con `./check.sh`: **Éxito (código 0)** en ambos proyectos.

---
**Commits Asociados:**
- `feat(client): elevar timeout http a 120s y habilitar modo asincrono con polling (GLOBAL-03)`
