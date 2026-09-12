### Implementación y Verificación del Health Monitor de Agentes (`ISSUE-F05-T02`)

Se ha implementado el servicio en segundo plano y la tarea de supervisión de salud de agentes en `jg_dashboard`:

1. **Servicio y Scope de Inactividad:**
   - Incorporado el scope `scopeTimedOut` en el modelo `Agent` para identificar agentes activos sin reporte en más de N segundos (por defecto 90s).
   - Implementado `AgentHealthMonitorService::checkAndMarkOfflineAgents()`: transiciona automáticamente los agentes afectados a `OFFLINE` y emite eventos de auditoría `AGENT_OFFLINE`.

2. **Comando Artisan y Tarea Programada:**
   - Creado el comando `php artisan agents:check-health {--timeout=90}`.
   - Programada la ejecución automática cada minuto (`everyMinute()`) en `routes/console.php`.
   - Creado el script ejecutable `scripts/check_health.sh`.

3. **Verificación Automatizada:**
   - 3 nuevas pruebas unitarias en `AgentHealthMonitorServiceTest`.
   - 2 nuevas pruebas de feature en `CheckAgentHealthCommandTest`.
   - Total de pruebas en `jg_dashboard`: **119 tests pasando (541 aserciones)** al 100%.

/close
