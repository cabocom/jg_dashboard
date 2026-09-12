# Análisis Técnico: [ISSUE-DASH-001] Control de Acceso RBAC y eliminación de fallbacks en Consola Web (/console)

## 1. Diagnóstico del Estado Previo
En la implementación previa de la Consola Web de `jg_dashboard`:
- En `routes/web.php`, todas las rutas bajo el prefijo `/console` compartían únicamente el middleware `auth`.
- Los endpoints críticos de mutación no requerían roles específicos:
  - `POST /console/agents` (provisión de agentes) podía ser invocado por cualquier usuario autenticado, incluidos `operator` y `viewer`.
  - `POST /console/approvals/{id}/approve` y `POST /console/approvals/{id}/reject` (resolución HITL) carecían de verificación de rol.
  - `POST /console/executions/trigger` no discriminaba permisos ni roles.
- En `app/Http/Controllers/WebConsoleController.php`:
  - `approveApproval` y `rejectApproval` contenían un bloque de fallback que, ante la ausencia de un usuario o sesión válida, buscaba el primer usuario en BD o creaba automáticamente un usuario con rol de operador (`operator.console@example.com`).
  - `triggerExecution` sobrescribía el `userResolver` de la petición inyectando un usuario fallback si `$request->user()` no estaba presente.

## 2. Solución Diseñada
1. **Rutas Web (`routes/web.php`)**:
   - Agrupación por middleware de rol utilizando el alias `role` (`RequireRole` middleware):
     ```php
     Route::middleware(['role:admin'])->group(function () {
         Route::post('/console/agents', [WebConsoleController::class, 'storeAgent'])->name('console.agents.store');
     });

     Route::middleware(['role:admin,operator'])->group(function () {
         Route::post('/console/approvals/{id}/approve', [WebConsoleController::class, 'approveApproval'])->name('console.approvals.approve');
         Route::post('/console/approvals/{id}/reject', [WebConsoleController::class, 'rejectApproval'])->name('console.approvals.reject');
         Route::post('/console/executions/trigger', [WebConsoleController::class, 'triggerExecution'])->name('console.executions.trigger');
     });
     ```
2. **Controlador (`WebConsoleController.php`)**:
   - `storeAgent`: Validar `$request->user()->hasRole('admin')`, retornando 401 si no hay usuario o 403 si el rol no es `admin`.
   - `approveApproval` y `rejectApproval`:
     - Retirar completamente la creación/búsqueda de `operator.console@example.com` y `User::first()`.
     - Requerir estrictamente `$request->user()` y verificar `$user->hasRole(['admin', 'operator'])`.
   - `triggerExecution`:
     - Retirar la inyección de `$request->setUserResolver(...)`.
     - Validar que `$request->user()` tenga rol `admin` u `operator`.

## 3. Impacto en Pruebas
- Actualización de `tests/Feature/AgentPanelWebTest.php`:
  - Asignar rol `admin` para la prueba de provisión de agentes.
  - Añadir aserción de que usuarios con rol `operator` reciben `403 Forbidden` en `POST /console/agents`.
- Creación de `tests/Feature/WebConsoleRbacTest.php`:
  - Aserciones de 401 para peticiones no autenticadas en todas las mutaciones.
  - Aserciones de 403 para usuarios con rol `viewer` en todas las mutaciones.
  - Aserciones de 403 para `operator` en provisión de agentes.
  - Verificación de ejecución exitosa (200/201) para `admin` y `operator` donde corresponde.
  - Verificación de que no se crean usuarios dummy y de que los eventos de auditoría y `ApprovalRequest` quedan asociados al ID del usuario real.
