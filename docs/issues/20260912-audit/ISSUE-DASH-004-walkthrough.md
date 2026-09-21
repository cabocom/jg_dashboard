# Walkthrough: [ISSUE-DASH-004] Monitorización de salud basada en timestamp real de heartbeat

## 1. Resumen de la Implementación
Se corrigió la evaluación de disponibilidad y estado de los agentes en la Consola Web de `jg_dashboard`. Se sustituyó la condición permisiva que informaba agentes muertos como saludables por una evaluación rigurosa del timestamp del heartbeat en tiempo real (`isHealthy(90)`), conmutando dinámicamente el estado a `OFFLINE` y recalculando métricas KPI sin esperar a los ciclos de ejecución del scheduler en segundo plano.

## 2. Archivos Modificados y Creados
- **[app/Http/Controllers/WebConsoleController.php](../../../app/Http/Controllers/WebConsoleController.php)**:
  - `listAgents()`: Evalúa `$isHealthy = $agent->status === 'ACTIVE' && $agent->isHealthy(90)` y `$effectiveStatus = ($agent->status === 'ACTIVE' && !$isHealthy) ? 'OFFLINE' : $agent->status`.
  - `index()`: Precarga contadores de vista blade filtrando por salud real del agente.
- **[public/js/console.js](../../../public/js/console.js)**:
  - Eliminación de fallback a `status === 'ACTIVE'` en `renderAgentsCards` y `renderAgentsTable`, forzando `Boolean(agent.is_healthy)`.
- **[tests/Feature/WebConsoleTest.php](../../../tests/Feature/WebConsoleTest.php)**:
  - Actualización de fixture con timestamp de latido y test para agentes expirados en vista blade.
- **[tests/Feature/WebConsoleHealthTest.php](../../../tests/Feature/WebConsoleHealthTest.php)**:
  - 6 feature tests verificando agentes sanos (<= 90s), expirados (> 90s), sin heartbeat (`null`), explícitamente offline y agregación precisa de KPIs.

## 3. Verificación
Ejecución de la suite completa de Pest en `jg_dashboard`:
```bash
./vendor/bin/pest
Tests:    241 passed (1154 assertions)
Duration: 4.34s
```
Todos los tests pasaron satisfactoriamente.
