<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>jG Control Plane | Consola Operativa y HITL</title>
  <meta name="description" content="Plano de Control y Consola de Gobernanza Human-In-The-Loop para agentes cognitivos del ecosistema jG.">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  
  <!-- CSS Stylesheet -->
  <link rel="stylesheet" href="/css/console.css?v={{ file_exists(public_path('css/console.css')) ? filemtime(public_path('css/console.css')) : time() }}">
</head>
<body>
  <div class="bg-mesh"></div>

  <div class="app-container">
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
      <div class="brand-section">
        <div class="brand-badge">jG</div>
        <div class="brand-info">
          <h1>Control Plane</h1>
          <span>Gobernanza & HITL</span>
        </div>
      </div>

      <nav class="nav-menu">
        <a href="#agents" id="nav-agents" class="nav-link active" data-tab="agents">
          <div class="nav-link-content">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
            </svg>
            <span>Agentes</span>
          </div>
        </a>

        <a href="#approvals" id="nav-approvals" class="nav-link" data-tab="approvals">
          <div class="nav-link-content">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <span>Aprobaciones HITL</span>
          </div>
          <span id="counter-hitl-pending" class="nav-counter">{{ $pendingApprovalsCount ?? 0 }}</span>
        </a>

        <a href="#executions" id="nav-executions" class="nav-link" data-tab="executions">
          <div class="nav-link-content">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            <span>Ejecuciones</span>
          </div>
        </a>
      </nav>

      <div class="sidebar-footer">
        <div class="status-pill">
          <div class="status-dot pulse"></div>
          <span>Red Interna Operativa</span>
        </div>
        <a href="/admin" class="btn btn-secondary" style="font-size: 12px; width: 100%;">
          Ir al Panel Filament →
        </a>
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="main-wrapper">
      <!-- Top Header -->
      <header class="top-header">
        <div class="page-title">
          <h2 id="page-current-title">Monitoreo y Catálogo de Agentes</h2>
        </div>

        <div class="header-actions">
          <button id="btn-refresh" class="btn btn-secondary" title="Sincronizar datos">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Sincronizar
          </button>
        </div>
      </header>

      <!-- Content Body -->
      <main class="content-body">
        <!-- Section 1: AGENTS -->
        <section id="view-agents" class="view-section active">
          <!-- KPI Summary Grid -->
          <div class="kpi-grid">
            <div class="kpi-card">
              <div class="kpi-header">
                <span>Total de Agentes</span>
                <div class="kpi-icon">🤖</div>
              </div>
              <div id="kpi-agents-total" class="kpi-value">{{ $totalAgents ?? 0 }}</div>
              <div class="kpi-footer">Registrados en Control Plane</div>
            </div>

            <div class="kpi-card">
              <div class="kpi-header">
                <span>Agentes Activos</span>
                <div class="kpi-icon" style="color: var(--accent-emerald);">⚡</div>
              </div>
              <div id="kpi-agents-active" class="kpi-value" style="color: var(--accent-emerald);">{{ $activeAgents ?? 0 }}</div>
              <div class="kpi-footer">Emite heartbeats regulares</div>
            </div>

            <div class="kpi-card">
              <div class="kpi-header">
                <span>Agentes Desconectados</span>
                <div class="kpi-icon" style="color: var(--accent-rose);">⚠️</div>
              </div>
              <div id="kpi-agents-offline" class="kpi-value" style="color: var(--accent-rose);">{{ $offlineAgents ?? 0 }}</div>
              <div class="kpi-footer">Sin latido en los últimos 90s</div>
            </div>
          </div>

          <!-- Agents Toolbar: Search, Filters, View Switcher & Provisioning Button -->
          <div class="agents-toolbar">
            <div class="agents-search-filter">
              <div class="search-input-wrapper">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" id="agents-search" class="form-control" placeholder="Buscar por nombre o ID de agente...">
              </div>
              <select id="agents-filter-status" class="form-control" style="width: auto; min-width: 160px;">
                <option value="ALL">Todos los Estados</option>
                <option value="ACTIVE">Activos</option>
                <option value="OFFLINE">Desconectados</option>
                <option value="PENDING">Pendientes</option>
              </select>
            </div>

            <div style="display: flex; align-items: center; gap: 12px;">
              <div class="view-switcher-group">
                <button type="button" class="view-btn active" data-view="cards" title="Vista en Tarjetas">
                  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                  </svg>
                  Tarjetas
                </button>
                <button type="button" class="view-btn" data-view="table" title="Vista en Tabla">
                  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                  </svg>
                  Tabla
                </button>
              </div>
            </div>
          </div>

          <!-- Agents Cards Container (Default View) -->
          <div id="agents-cards-container" class="agents-cards-grid">
            <!-- Cards rendered dynamically by console.js -->
          </div>

          <!-- Agents Table Container (Alternative View) -->
          <div id="agents-table-container" class="panel table-container" style="display: none;">
            <div class="panel-header">
              <h3 class="panel-title">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                Inventario y Salud de Nodos Cognitivos
              </h3>
            </div>
            <div class="panel-body" style="padding: 0;">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Agente</th>
                    <th>Estado</th>
                    <th>Versión</th>
                    <th>Último Heartbeat</th>
                    <th>Salud</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody id="agents-table-body">
                  <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 32px;">
                      Cargando catálogo de agentes...
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- Section 2: HITL APPROVALS -->
        <section id="view-approvals" class="view-section">
          <!-- HITL KPI Grid -->
          <div class="kpi-grid">
            <div class="kpi-card alert">
              <div class="kpi-header">
                <span>Solicitudes en Espera</span>
                <div class="kpi-icon">⏳</div>
              </div>
              <div id="kpi-hitl-total" class="kpi-value" style="color: var(--accent-amber);">{{ $pendingApprovalsCount ?? 0 }}</div>
              <div class="kpi-footer">Requieren decisión humana inmediata</div>
            </div>

            <div class="kpi-card alert">
              <div class="kpi-header">
                <span>Riesgo Crítico</span>
                <div class="kpi-icon" style="color: var(--accent-rose);">🛑</div>
              </div>
              <div id="kpi-hitl-critical" class="kpi-value" style="color: var(--accent-rose);">0</div>
              <div class="kpi-footer">Comandos potencialmente destructivos</div>
            </div>

            <div class="kpi-card">
              <div class="kpi-header">
                <span>Riesgo Alto</span>
                <div class="kpi-icon" style="color: var(--accent-amber);">⚠️</div>
              </div>
              <div id="kpi-hitl-high" class="kpi-value" style="color: var(--accent-amber);">0</div>
              <div class="kpi-footer">Modificación de infraestructura o red</div>
            </div>
          </div>

          <!-- HITL Toolbar: Risk Filters, Search & Agent Dropdown -->
          <div class="hitl-toolbar">
            <div class="hitl-pills-container">
              <button type="button" class="hitl-pill-btn active" data-risk="ALL">
                Todos (<span id="hitl-count-all">{{ $pendingApprovalsCount ?? 0 }}</span>)
              </button>
              <button type="button" class="hitl-pill-btn" data-risk="CRITICAL">
                Crítico (<span id="hitl-count-critical">0</span>)
              </button>
              <button type="button" class="hitl-pill-btn" data-risk="HIGH">
                Alto (<span id="hitl-count-high">0</span>)
              </button>
            </div>

            <div class="hitl-filter-group">
              <div class="search-input-wrapper">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" id="hitl-search" class="form-control" placeholder="Buscar por hilo, plan o acción...">
              </div>

              <select id="hitl-filter-agent" class="form-control" style="width: auto; min-width: 180px;">
                <option value="ALL">Todos los Agentes</option>
              </select>
            </div>
          </div>

          <!-- HITL Cards Container -->
          <div class="panel">
            <div class="panel-header">
              <h3 class="panel-title">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Cola de Decisión Humana (Checkpoint LangGraph)
              </h3>
            </div>
            <div class="panel-body">
              <div id="hitl-cards-container" class="hitl-grid">
                <!-- Tarjetas renderizadas dinámicamente por console.js -->
              </div>
            </div>
          </div>
        </section>

        <!-- Section 3: EXECUTIONS -->
        <section id="view-executions" class="view-section">
          <div class="execution-layout-grid">
            <!-- Left Column: Trigger Gateway -->
            <div class="panel">
              <div class="panel-header">
                <h3 class="panel-title">
                  <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Pasarela de Disparo de Ejecuciones
                </h3>
              </div>
              <div class="panel-body">
                <form id="form-trigger-execution" style="display: flex; flex-direction: column; gap: 20px;">
                  <div class="form-group">
                    <label class="form-label" for="trigger-agent-id">Agente Destino</label>
                    <select id="trigger-agent-id" class="form-control">
                      <option value="">Cargando agentes activos...</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="trigger-prompt">Prompt / Estímulo Inicial (AgentInput)</label>
                    <textarea id="trigger-prompt" class="form-control" placeholder="Ej: Analiza los contenedores con reinicios recientes y reinicia el servicio Nginx de forma segura."></textarea>
                  </div>

                  <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary">
                      ▶ Disparar Ejecución en Agente
                    </button>
                  </div>
                </form>

                <div style="margin-top: 24px;">
                  <details id="execution-raw-details" class="trace-details-card">
                    <summary style="font-weight: 600; color: var(--text-secondary); cursor: pointer; padding: 10px 14px;">
                      🔍 Ver Respuesta JSON Técnica (Control Plane Payload)
                    </summary>
                    <div style="padding: 12px;">
                      <pre id="execution-result-box" style="background: rgba(0,0,0,0.4); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; font-family: var(--font-mono); font-size: 12px; color: var(--accent-cyan); overflow-x: auto; min-height: 60px; margin: 0;">Esperando instrucción...</pre>
                    </div>
                  </details>
                </div>
              </div>
            </div>

            <!-- Right Column: Real-Time Execution Trace Viewer -->
            <div class="panel">
              <div class="panel-header">
                <h3 class="panel-title">
                  <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                  </svg>
                  Explorador de Saltos Cognitivos y Traza del Grafo
                </h3>
              </div>
              <div class="panel-body">
                <!-- Query Toolbar -->
                <div class="execution-query-toolbar">
                  <select id="execution-query-agent" class="form-control" style="width: auto; min-width: 160px;">
                    <option value="">Selecciona Agente</option>
                  </select>
                  <input type="text" id="execution-query-thread" class="form-control" style="flex: 1; min-width: 200px;" placeholder="Thread ID (ej: th-...)">
                  <button type="button" id="btn-load-execution" class="btn btn-secondary">
                    🔍 Cargar Traza
                  </button>
                </div>

                <!-- Recent Executions Quick Chips -->
                <div id="recent-threads-container" class="recent-threads-bar">
                  <span style="font-size: 11px; color: var(--text-muted); font-weight: 600;">RECIENTES:</span>
                  <div id="recent-threads-list" style="display: flex; gap: 6px; flex-wrap: wrap;">
                    <span style="font-size: 11px; color: var(--text-muted);">Sin ejecuciones en sesión</span>
                  </div>
                </div>

                <!-- Execution Detail Container -->
                <div id="execution-detail-panel" class="execution-detail-card">
                  <!-- Metadata Summary Grid -->
                  <div class="execution-meta-grid">
                    <div class="exec-meta-item">
                      <span class="exec-meta-label">Estado</span>
                      <span id="exec-status-badge" class="badge badge-pending">SIN CARGAR</span>
                    </div>
                    <div class="exec-meta-item">
                      <span class="exec-meta-label">Latencia</span>
                      <span id="exec-latency" class="exec-meta-val">-</span>
                    </div>
                    <div class="exec-meta-item">
                      <span class="exec-meta-label">Agente</span>
                      <span id="exec-agent-name" class="exec-meta-val" style="font-size: 13px;">-</span>
                    </div>
                    <div class="exec-meta-item">
                      <span class="exec-meta-label">Thread ID</span>
                      <span id="exec-thread-id" class="exec-meta-val" style="font-family: var(--font-mono); font-size: 12px; color: var(--accent-cyan);">-</span>
                    </div>
                  </div>

                  <!-- Prompt Box -->
                  <div>
                    <div class="form-label" style="margin-bottom: 6px;">Prompt de Entrada (AgentInput):</div>
                    <div id="execution-prompt-box" class="execution-prompt-container">
                      Introduce un Thread ID y pulsa "Cargar Traza", o dispara una nueva ejecución desde la izquierda.
                    </div>
                  </div>

                  <!-- Step Execution Trace Timeline -->
                  <div class="trace-steps-container">
                    <div class="form-label" style="margin-bottom: 6px;">
                      Saltos Cognitivos y Transiciones de Nodos (LangGraph State Traversal):
                    </div>
                    <div id="execution-steps-timeline" class="trace-steps-timeline">
                      <div style="font-size: 12px; color: var(--text-muted); padding: 12px 0;">
                        No hay pasos registrados para mostrar.
                      </div>
                    </div>
                  </div>

                  <!-- Output Box -->
                  <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                      <div class="form-label">Resultado Obtenido (AgentOutput):</div>
                      <button type="button" id="btn-copy-exec-output" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px;">
                        📋 Copiar
                      </button>
                    </div>
                    <pre id="execution-output-box" class="execution-code-box">Esperando datos de ejecución...</pre>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>
  </div>

  <!-- Modal Approve -->
  <div id="modal-approve" class="modal-backdrop">
    <div class="modal-dialog">
      <div class="modal-header">
        <h3>✓ Aprobar Plan de Ejecución</h3>
        <button class="modal-close">&times;</button>
      </div>
      <form id="form-confirm-approve">
        <div class="modal-body">
          <p style="font-size: 13px; color: var(--text-secondary);">
            ¿Confirmas la autorización para reanudar el grafo del hilo <strong id="approve-modal-thread-id" style="color: var(--text-primary); font-family: var(--font-mono);"></strong>?
          </p>
          <div class="form-group">
            <label class="form-label" for="approve-justification">Justificación u Observaciones (Opcional):</label>
            <textarea id="approve-justification" class="form-control" placeholder="Ej: Plan validado y mitigación comprobada por el equipo SRE."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-modal-dismiss>Cancelar</button>
          <button type="submit" class="btn btn-primary">Conceder Aprobación</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Reject -->
  <div id="modal-reject" class="modal-backdrop">
    <div class="modal-dialog">
      <div class="modal-header">
        <h3 style="color: var(--accent-rose);">✕ Rechazar y Cancelar Plan</h3>
        <button class="modal-close">&times;</button>
      </div>
      <form id="form-confirm-reject">
        <div class="modal-body">
          <p style="font-size: 13px; color: var(--text-secondary);">
            Se cancelará inmediatamente la ejecución del hilo <strong id="reject-modal-thread-id" style="color: var(--text-primary); font-family: var(--font-mono);"></strong> en el agente.
          </p>
          <div class="form-group">
            <label class="form-label" for="reject-justification">
              Motivo del Rechazo <span style="color: var(--accent-rose);">* (Obligatorio, mín. 5 caracteres)</span>:
            </label>
            <textarea id="reject-justification" class="form-control" required placeholder="Ej: Riesgo destructivo inasumible en horario comercial según política SRE." style="border-color: rgba(244, 63, 94, 0.4);"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-modal-dismiss>Cancelar</button>
          <button type="submit" class="btn btn-danger">Confirmar Rechazo</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Create Agent (Provisioning & Bootstrap Token) -->
  <div id="modal-create-agent" class="modal-backdrop">
    <div class="modal-dialog">
      <div class="modal-header">
        <h3>+ Aprovisionar Nuevo Agente</h3>
        <button class="modal-close">&times;</button>
      </div>
      <form id="form-create-agent">
        <div class="modal-body">
          <p style="font-size: 13px; color: var(--text-secondary);">
            Registra un nuevo agente en el Control Plane. Se generará un <strong>Bootstrap Token</strong> de un solo uso válido por 24 horas.
          </p>
          <div class="form-group">
            <label class="form-label" for="create-agent-name">Nombre del Agente <span style="color: var(--accent-rose);">*</span></label>
            <input type="text" id="create-agent-name" class="form-control" required placeholder="Ej: Agente SRE Producción">
          </div>
          <div class="form-group">
            <label class="form-label" for="create-agent-version">Versión Inicial</label>
            <input type="text" id="create-agent-version" class="form-control" value="1.0.0" placeholder="1.0.0">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-modal-dismiss>Cancelar</button>
          <button type="submit" class="btn btn-primary">Generar Bootstrap Token</button>
        </div>
      </form>

      <!-- Result Box displayed after creation -->
      <div id="create-agent-result-box" class="modal-body" style="display: none; flex-direction: column; gap: 16px;">
        <div class="token-notice">
          <span>⚠️</span> <strong>Guarda este Bootstrap Token ahora</strong>. No volverá a mostrarse en claro.
        </div>
        <div class="form-group">
          <label class="form-label">ID del Agente Aprovisionado:</label>
          <div id="created-agent-id" style="font-family: var(--font-mono); font-size: 13px; color: var(--accent-cyan);"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Bootstrap Token (TTL: 24h):</label>
          <div id="created-token-value" class="token-value-display"></div>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 8px;">
          <button type="button" id="btn-copy-token" class="btn btn-primary">📋 Copiar Token</button>
          <button type="button" class="btn btn-secondary" data-modal-dismiss>Cerrar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Inspect Plan (Technical JSON Inspection) -->
  <div id="modal-inspect-plan" class="modal-backdrop">
    <div class="modal-dialog" style="max-width: 680px;">
      <div class="modal-header">
        <h3>🔍 Inspección Técnica del Plan Cognitivo</h3>
        <button class="modal-close">&times;</button>
      </div>
      <div class="modal-body">
        <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 8px;">
          Hilo de Ejecución: <strong id="inspect-modal-thread-id" style="color: var(--text-primary); font-family: var(--font-mono);"></strong>
        </div>
        <pre id="inspect-plan-content" class="json-inspector-box"></pre>
      </div>
      <div class="modal-footer">
        <button type="button" id="btn-copy-plan-json" class="btn btn-primary">📋 Copiar JSON</button>
        <button type="button" class="btn btn-secondary" data-modal-dismiss>Cerrar</button>
      </div>
    </div>
  </div>

  <!-- Toast Notifications Container -->
  <div id="toast-container" class="toast-container"></div>

  <!-- Client JavaScript -->
  <script src="/js/console.js?v={{ file_exists(public_path('js/console.js')) ? filemtime(public_path('js/console.js')) : time() }}"></script>
</body>
</html>
