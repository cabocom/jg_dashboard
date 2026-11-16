# Issue 09: [jg_dashboard] Feat: Telemetría y renderizado de sidecars MCP en Mapa de Topología

- **Título:** `[jg_dashboard] Feat: Telemetría y renderizado de sidecars MCP en Mapa de Topología`
- **Prioridad y Fase:** Media | Fase 3: Dashboard Servidor MCP
- **Dependencias:** Issue 08 (`[jg_dashboard] Feat: Servidor nativo MCP Streamable HTTP`)
- **Contexto Técnico y Archivos Afectados:**
  - Hallazgos del informe: Visualización y observabilidad de sidecars MCP a partir de los datos recibidos en el heartbeat.
  - Archivos a modificar:
    - `jg_dashboard/app/Filament/Pages/TopologyMap.php`
    - `jg_dashboard/app/Services/Agents/AgentHeartbeatService.php`
    - `jg_dashboard/resources/views/filament/pages/topology-map.blade.php`
    - `jg_dashboard/tests/Feature/TopologyMapMcpTest.php` (Crear)

---

## Especificación Técnica

### 1. `TopologyMap.php`
- En el método `refreshTopology()`:
  - Al recorrer los agentes registrados (`Agent::all()`):
    - Inspeccionar `$agent->metrics_json['mcp_sidecars']` o `$agent->capabilities_json['mcp_servers']`.
    - Si el agente tiene configurados servidores o clientes MCP asociados:
      - Generar nodo hijo:
        ```php
        $agentNodes[] = [
            'id' => "mcp_{$agent->agent_id}_{$mcpName}",
            'label' => "MCP: {$mcpName}",
            'type' => 'mcp_server',
            'status' => $mcpStatus === 'connected' ? 'healthy' : 'down',
            'metadata' => [
                'transport' => 'Streamable HTTP',
                'url' => $mcpUrl,
                'tools_count' => $mcpToolsCount,
                'schema_hash' => $mcpSchemaHash,
            ],
        ];
        ```
      - Generar arista dirigida:
        ```php
        $agentEdges[] = [
            'from' => $agent->agent_id,
            'to' => "mcp_{$agent->agent_id}_{$mcpName}",
            'relation' => 'mcp_streamable_http',
        ];
        ```

### 2. Vista Blade y Panel de Detalles
- En `resources/views/filament/pages/topology-map.blade.php`, añadir soporte para el badge y color de nodo `'mcp_server'` (color morado/azul índigo característico de MCP) y mostrar en el modal lateral los detalles del schema hash y cantidad de herramientas sincronizadas.

---

## Criterios de Aceptación (Checklist Verificable)

- [ ] Test feature en `TopologyMapMcpTest.php` que crea un agente con métricas simuladas de sidecar MCP en `metrics_json` y comprueba que `refreshTopology()` incorpora los nodos `mcp_server` y sus relaciones.
- [ ] Verificación de que el conteo de nodos saludables y caídos se actualiza reflejando el estado de los componentes MCP.
- [ ] Linters y pruebas limpias en `jg_dashboard`.
