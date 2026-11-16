# Issue 08: [jg_dashboard] Feat: Servidor nativo MCP Streamable HTTP (POST /api/v1/mcp) con JSON-RPC 2.0 y Anti-DNS Rebinding

- **Título:** `[jg_dashboard] Feat: Servidor nativo MCP Streamable HTTP (POST /api/v1/mcp) con JSON-RPC 2.0 y Anti-DNS Rebinding`
- **Prioridad y Fase:** Crítica | Fase 3: Dashboard Servidor MCP
- **Dependencias:** Issue 05 (`[jg_agente] Feat: Endpoint de ejecución directa`), Issue 07 (`[jg_dashboard] Feat: Migración y modelo para tokens de API`)
- **Contexto Técnico y Archivos Afectados:**
  - Hallazgos del informe: `H-14` (Exposición de capacidades y esquemas), `H-15` (FastCGI y Nginx incompatible con SSE persistente; requiere Streamable HTTP síncrono POST), `H-16` (Límites y rate limiting).
  - Regla estricta 2: Implementación nativa Streamable HTTP (`POST /api/v1/mcp` JSON-RPC 2.0 sobre PHP-FPM/Nginx estándar, sin SSE). Convención de nombres `{agente}__{tool}`. Alcance v1 restringido estrictamente a `risk_level == LOW` y `read_only == true`. Protección Anti-DNS Rebinding (`Host` y `Origin`).
  - Archivos a crear/modificar:
    - `jg_dashboard/app/Http/Controllers/Api/Mcp/McpServerController.php` (Crear)
    - `jg_dashboard/app/Actions/Mcp/ListMcpToolsAction.php` (Crear)
    - `jg_dashboard/app/Actions/Mcp/CallMcpToolAction.php` (Crear)
    - `jg_dashboard/routes/api.php` (Modificar)
    - `jg_dashboard/tests/Feature/McpServerControllerTest.php` (Crear)

---

## Especificación Técnica

### 1. `routes/api.php`
- Registrar endpoint MCP protegido:
  ```php
  Route::prefix('v1')->group(function () {
      Route::post('/mcp', [McpServerController::class, 'handle'])
          ->middleware(['auth.api_token'])
          ->name('api.v1.mcp');
  });
  ```

### 2. `McpServerController.php` (< 300 líneas)
- **Protección Anti-DNS Rebinding:**
  - Inspeccionar cabeceras `Host` y `Origin`.
  - Si `Origin` está presente, verificar que coincida con dominios autorizados en `config('services.mcp.allowed_origins')` o con el `APP_URL`. En caso de discrepancia, devolver HTTP 403 `Forbidden: Invalid Origin`.
- **Despacho JSON-RPC 2.0:**
  - Parsear body: `{"jsonrpc": "2.0", "id": ..., "method": "...", "params": ...}`.
  - Soportar métodos:
    1. `initialize`: Responde con versión de protocolo `"2024-11-05"`, `serverInfo: {"name": "jg_dashboard-mcp", "version": "1.0.0"}`, y `capabilities: {"tools": {}}`.
    2. `notifications/initialized`: Responde HTTP 204 o ack vacío.
    3. `ping`: Responde `{"jsonrpc": "2.0", "id": ..., "result": {}}`.
    4. `tools/list`: Delega en `ListMcpToolsAction`.
    5. `tools/call`: Delega en `CallMcpToolAction`.
  - Gestión de cabecera de sesión opcional: `Mcp-Session-Id` aceptada y propagada en las respuestas.

### 3. `ListMcpToolsAction.php`
- Consulta agentes activos en base de datos (`Agent::active()`).
- Para cada agente, procesa `capabilities_json['tools']`:
  - **Filtro estricto v1:** Omitir cualquier herramienta que tenga `risk_level != 'LOW'` o `read_only != true`.
  - **Namespacing:** Generar identificador `{agent_id}__{tool_name}` (o slug normalizado `{agent_name}__{tool_name}`).
  - **Esquema:** Asignar `inputSchema` recuperado de `capabilities_json` (con fallback a `{"type": "object", "properties": {}}`).
- Retorna respuesta compatible con especificación MCP:
  ```json
  {
    "tools": [
      {
        "name": "devops__docker_inspect_container",
        "description": "Inspecciona la configuración de un contenedor",
        "inputSchema": { "type": "object", "properties": { ... } }
      }
    ]
  }
  ```

### 4. `CallMcpToolAction.php`
- Recibe `params.name` y `params.arguments`.
- Descompone `name` en `{agent_id}` y `{tool_name}`:
  - Si el formato no contiene doble guión bajo o el agente no existe: error JSON-RPC -32601 (*Method not found*).
- Valida que la herramienta requerida cumpla la política de bajo riesgo y lectura.
- Invoca la herramienta deterministamente en `jg_agente` mediante `AgentHttpClient::sendRequest('POST', "/tools/{$toolName}/execute", ...)`:
  - Pasa argumentos y firma con HMAC de control plane.
- Formatea la respuesta devuelta como `CallToolResult`:
  ```json
  {
    "content": [
      {
        "type": "text",
        "text": "..." // Serializado JSON de la salida
      }
    ],
    "isError": false
  }
  ```
- Registra el evento en `AuditEvent::record('mcp_tool_called', ...)`.

---

## Criterios de Aceptación (Checklist Verificable)

- [ ] Test unitario/feature que realiza handshake `initialize` y comprueba la estructura JSON-RPC 2.0 y la versión de protocolo.
- [ ] Test de `tools/list` comprobando que las tools se formatean con el namespace `{agente}__{tool}` y que las herramientas de riesgo `HIGH` quedan excluidas del listado.
- [ ] Test de `tools/call` invocando exitosamente una tool de lectura simulada mediante mock de `AgentHttpClient`.
- [ ] Test de seguridad Anti-DNS Rebinding que envía una cabecera `Origin: http://malicious-site.com` y recibe un código HTTP 403 Forbidden.
- [ ] Test que verifica que llamadas con tokens sin el scope `mcp:execute` son rechazadas con error de autorización.
- [ ] Pruebas ejecutadas con `php artisan test --filter=McpServerControllerTest` pasando al 100%.
