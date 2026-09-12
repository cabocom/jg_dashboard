# Walkthrough Técnico: [GLOBAL-03] Incompatibilidad de Timeouts HTTP (15s) frente al Ciclo Cognitivo

## Resumen de la Intervención

Se resolvió la incompatibilidad entre el timeout por defecto del cliente HTTP de Laravel (15s) y la latencia intrínseca del grafo cognitivo de 9-10 nodos de `jg_agente`, permitiendo ejecuciones síncronas de hasta 120s, habilitando la selección de ejecuciones asíncronas con polling automático en la consola web, y garantizando la persistencia de tareas ante incidencias de red o timeouts.

---

## Archivos Modificados y Creados

### 1. Control Plane (`jg_dashboard`)
- **`config/agent.php`:**
  - Se elevó el timeout por defecto de `15` a `120` segundos:
    ```php
    'timeout' => (int) env('AGENT_HTTP_TIMEOUT', 120),
    ```
- **`app/Services/Agents/AgentHttpClient.php`:**
  - Fallback por defecto en constructor actualizado a `120`:
    ```php
    $this->timeout = $timeout ?? (int) (Config::get('agent.timeout') ?: 120);
    ```
  - Incorporación de métodos fluidos `withTimeout(int $timeout): self` y `getTimeout(): int`.
- **`app/Http/Controllers/Api/ExecutionController.php`:**
  - Ante error de pasarela o timeout (`!$response['success']`), se persiste de inmediato un registro en `AgentTask` con estado `FAILED` y la traza del error upstream antes de retornar el HTTP 504/503/502.
- **`resources/views/console/index.blade.php`:**
  - Incorporación de control interactivo (checkbox toggle `#trigger-async`) en el formulario de disparo de ejecuciones.
- **`public/js/console.js`:**
  - Captura del estado `trigger-async`, envío de `async: true` en el payload JSON y ajuste dinámico de textos y spinners.
  - El resultado 202 `in_progress` activa el ciclo de polling cada 3s en `loadExecutionTrace()` hasta su resolución.
- **`tests/Unit/AgentHttpClientTest.php`:**
  - Añadido test validando el timeout por defecto de 120s y el comportamiento del modificador `withTimeout()`.
- **`tests/Feature/ExecutionTriggerApiTest.php`:**
  - Añadido test verificando que ante un timeout de conexión (cURL error 28), se retorna 504 Gateway Timeout y se persiste la tarea en `AgentTask` con estado `FAILED`.

### 2. Infraestructura y Variables de Entorno
- **`docker-compose.yml`:**
  - Inyección de `AGENT_HTTP_TIMEOUT: ${AGENT_HTTP_TIMEOUT:-120}` en el servicio `jg_dashboard`.
- **`.env.example` & `jg_dashboard/.env.example`:**
  - Declaración explícita de `AGENT_HTTP_TIMEOUT=120`.

---

## Validación y Resultados de Pruebas

1. **Pruebas en `jg_dashboard`:**
   - Suite Pest ejecutada exitosamente: **254 passed (1213 assertions)**.
   - Tests específicos de timeout y cliente HTTP superados al 100%.

2. **Pruebas en `jg_agente`:**
   - Verificación de formato y linter con Ruff: 104 archivos analizados, 0 errores.
   - Verificación estática con MyPy estricto: 104 archivos analizados, 0 errores.
   - Suite completa de Pytest (Core + Plugins): **361 passed**.

3. **Verificación Global (`./check.sh`):**
   ```
   ==================================================================
   📊 RESUMEN GLOBAL DE VERIFICACIÓN
   ==================================================================
     ✅ jg_agente:    PASÓ (Ruff, MyPy y Pytest correctos)
     ✅ jg_dashboard: PASÓ (Pest y pruebas PHP correctas)
   ==================================================================
   🎉 ¡AMBOS CHECKS SE HAN EJECUTADO CORRECTAMENTE!
   ==================================================================
   ```
