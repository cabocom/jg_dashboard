# Análisis Técnico: [ISSUE-DASH-004] Monitorización de salud basada en timestamp real de heartbeat

## 1. Diagnóstico del Estado Previo
En `WebConsoleController.php`:
- Línea 50:
  ```php
  'is_healthy' => $agent->status === 'ACTIVE' || $agent->isHealthy(90),
  ```
  La expresión evaluaba a `true` siempre que `$agent->status` fuese `'ACTIVE'`, ignorando por completo el resultado de `$agent->isHealthy(90)`.
- El campo `'status'` devuelto era directamente `$agent->status` sin verificar la validez del heartbeat.
- Los contadores de resumen en `listAgents()` y en `index()` calculaban los activos con `Agent::where('status', 'ACTIVE')->count()`, considerando sanos a agentes inactivos o desconectados cuyo registro no hubiera sido modificado por el scheduler de fondo.
- En `public/js/console.js`:
  ```javascript
  const isHealthy = agent.is_healthy ?? (agent.status === 'ACTIVE');
  ```
  El fallback asumía salud si `agent.status === 'ACTIVE'`.

## 2. Solución Diseñada
1. **Controlador (`WebConsoleController.php`)**:
   - Computar salud estricta:
     ```php
     $isHealthy = $agent->status === 'ACTIVE' && $agent->isHealthy(90);
     ```
   - Determinar estado efectivo para la consola:
     ```php
     $effectiveStatus = ($agent->status === 'ACTIVE' && !$isHealthy) ? 'OFFLINE' : $agent->status;
     ```
   - Calcular contadores de resumen agregados dinámicamente sobre la colección mapeada de ítems con su estado efectivo.
   - En `index()`:
     ```php
     $agents = Agent::all();
     $totalAgents = $agents->count();
     $activeAgents = $agents->filter(fn (Agent $a) => $a->status === 'ACTIVE' && $a->isHealthy(90))->count();
     $offlineAgents = $agents->filter(fn (Agent $a) => $a->status === 'OFFLINE' || ($a->status === 'ACTIVE' && !$a->isHealthy(90)))->count();
     ```
2. **Frontend (`public/js/console.js`)**:
   - En `renderAgentsCards` y `renderAgentsTable`:
     ```javascript
     const isHealthy = Boolean(agent.is_healthy);
     ```
3. **Pruebas Automatizadas**:
   - Crear `tests/Feature/WebConsoleHealthTest.php` cubriendo agentes sanos, expirados (> 90s), sin heartbeat (`null`), explícitamente offline y pendientes.
   - Actualizar `tests/Feature/WebConsoleTest.php` con latidos válidos y prueba de agente expirado en la precarga.
