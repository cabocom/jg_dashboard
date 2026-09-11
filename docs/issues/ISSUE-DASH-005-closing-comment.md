# Comentario de Cierre: [ISSUE-DASH-005] Desacoplamiento y retirada de referencias legacy DevOps60kApiClient

## Estado: RESUELTO / IMPLEMENTADO

### Resumen de la Solución
Se ha desacoplado por completo la aplicación del cliente singleton legacy `DevOps60kApiClient`, migrando las interfaces administrativas de Filament hacia `AgentHttpClient` y modelos Eloquent `Agent`:
1. **Retirada del Singleton**:
   - Se eliminó el binding singleton de `DevOps60kApiClient` en `AppServiceProvider.php`.
2. **Migración de Filament**:
   - `AgentHealthWidget`: Ahora utiliza `AgentHttpClient` y evalúa la disponibilidad en tiempo real mediante `isHealthy(90)` y la entidad `Agent`.
   - `TopologyMap`: Consulta el estado de los agentes dinámicamente y utiliza `AgentHttpClient` en lugar del cliente obsoleto.
   - `AgentTaskResource`: Las acciones `trigger`, `approve_hitl` y `reject_hitl` delegan en `AgentHttpClient`, transmitiendo cabeceras canónicas HMAC-SHA256 y resolviendo el agente objetivo mediante Eloquent.
3. **Verificación Automatizada**:
   - Se crearon y extendieron pruebas en `AgentHealthWidgetTest.php` y `AgentTaskResourceTest.php`.
   - La suite completa de Pest en `jg_dashboard` (245 tests, 1170 assertions) pasa al 100%.
