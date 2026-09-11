# Análisis Técnico: [ISSUE-DASH-005] Desacoplamiento y retirada de referencias legacy DevOps60kApiClient

## 1. Diagnóstico del Estado Previo
1. **Binding en `AppServiceProvider.php`**:
   - Se registraba un singleton para `DevOps60kApiClient` que obtenía `config('services.devops60k.url')` y `config('services.devops60k.token')`, apuntando de forma fija al backend `devops60k_app:8000`.
2. **Widget `AgentHealthWidget.php`**:
   - Inyectaba `DevOps60kApiClient $apiClient` en `getViewData()`, invocaba `$apiClient->checkHealth()` legacy y construía métricas sin evaluar el modelo `Agent` de base de datos ni los latidos `isHealthy(90)`.
3. **Página `TopologyMap.php`**:
   - En `refreshTopology(DevOps60kApiClient $apiClient)`, dependía del cliente legacy para verificar salud y poblar nodos mock de servicios.
4. **Recurso `AgentTaskResource.php`**:
   - En las acciones `trigger`, `approve_hitl` y `reject_hitl`, recibía `DevOps60kApiClient $apiClient` mediante inyección de dependencias en las closures de acción de Filament, ejecutando métodos legacy como `$apiClient->approveExecution(...)`.

## 2. Solución Diseñada
1. **Eliminación del Singleton (`app/Providers/AppServiceProvider.php`)**:
   - Se removió el registro de `DevOps60kApiClient` del método `register()`.
2. **Modernización de `AgentHealthWidget.php`**:
   - Se inyecta `AgentHttpClient`.
   - Se consulta el agente activo prioritario (`Agent::active()->first() ?? Agent::first()`) o se verifica el estado agregando todos los agentes de la base de datos.
   - Si no hay agentes o el agente no responde, se reporta de forma segura el estado offline/inaccesible sin arrojar excepciones no controladas.
   - Se calculan contadores de salud usando `isHealthy(90)`.
3. **Modernización de `TopologyMap.php`**:
   - Se inyecta `AgentHttpClient` en lugar de `DevOps60kApiClient`.
   - En caso de no existir topología previa en BD, se cargan dinámicamente todos los agentes registrados en Eloquent (`Agent::all()`), evaluando su salud con `isHealthy(90)` e integrándolos con sus respectivos nodos de infraestructura.
4. **Modernización de `AgentTaskResource.php`**:
   - En las acciones `trigger`, `approve_hitl` y `reject_hitl`, se inyecta `AgentHttpClient $httpClient`.
   - Se resuelve el agente destino a partir de la tarea o mediante `Agent::active()->first()`.
   - Se invocan los métodos tipados `triggerExecution()`, `approveExecution()` y `rejectExecution()` de `AgentHttpClient`, garantizando firmas criptográficas HMAC.
5. **Pruebas Automatizadas**:
   - Creación de `tests/Feature/AgentHealthWidgetTest.php` validando los estados online, inaccesible y agregación de métricas de salud.
   - Adición de test específico en `tests/Feature/AgentTaskResourceTest.php` verificando la acción `approve_hitl` delegando en `AgentHttpClient`.
