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

  <script>
    (function() {
      const saved = localStorage.getItem('console_theme') || localStorage.getItem('jg_theme') || 'dark';
      const theme = (saved === 'light' || saved === 'blanco') ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', theme);
    })();
  </script>
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

        <a href="#launcher" id="nav-launcher" class="nav-link" data-tab="launcher">
          <div class="nav-link-content">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Lanzar Ejecución</span>
          </div>
        </a>

        <a href="#executions" id="nav-executions" class="nav-link" data-tab="executions">
          <div class="nav-link-content">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            <span>Historial de Ejecuciones</span>
          </div>
        </a>
      </nav>

      <div class="sidebar-footer">
        <div class="status-pill">
          <div class="status-dot pulse"></div>
          <span>Red Interna Operativa</span>
        </div>
        @if(auth()->user()?->isAdmin())
        <a href="/admin" id="btn-goto-admin" class="btn btn-secondary" style="font-size: 12px; width: 100%;">
          Ir al admin →
        </a>
        @endif
      </div>
    </aside>

    <!-- Main Content Area -->
    <div class="main-wrapper">
      <!-- Top Header -->
      <header class="top-header">
        <div class="page-title">
          <h2 id="page-current-title">Monitoreo y Catálogo de Agentes</h2>
        </div>

        <div class="header-actions" style="display: flex; align-items: center; gap: 8px;">
          <!-- Selector de Estilos / Temas (Solo Dos Temas: Blanco y Negro) -->
          <div class="theme-dropdown-wrapper" style="position: relative;">
            <button id="btn-toggle-theme" type="button" class="btn btn-secondary" title="Cambiar tema visual (Negro / Blanco)" style="display: flex; align-items: center; gap: 6px; font-size: 12px; padding: 7px 12px;">
              <span id="current-theme-icon">🌙</span>
              <span id="current-theme-label">Oscuro</span>
              <span style="font-size: 9px; opacity: 0.7;">▼</span>
            </button>
            <div id="theme-dropdown-menu" class="theme-dropdown-menu" style="display: none;">
              <button type="button" class="theme-option-btn active" data-theme="dark">
                <span class="theme-swatch" style="background: #090a0f; border: 1px solid rgba(255,255,255,0.2);"></span>
                <span>Oscuro (Negro)</span>
              </button>
              <button type="button" class="theme-option-btn" data-theme="light">
                <span class="theme-swatch" style="background: #ffffff; border: 1px solid #cbd5e1;"></span>
                <span>Claro (Blanco)</span>
              </button>
            </div>
          </div>

          <!-- Botón de Perfil de Usuario -->
          <button id="btn-user-profile" type="button" class="btn btn-secondary" title="Modificar mis datos de usuario y contraseña" style="display: flex; align-items: center; gap: 6px; font-size: 12px; padding: 7px 12px;">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span id="header-user-name">{{ auth()->user()->name ?? 'Mi Perfil' }}</span>
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
                    <th>Ejecuciones</th>
                    <th>Versión</th>
                    <th>Último Heartbeat</th>
                    <th>Salud</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody id="agents-table-body">
                  <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
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
              <div class="kpi-footer">Operaciones con mutación de estado</div>
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

        <!-- Section 3: LAUNCHER (Trigger Gateway & Real-Time Trace) -->
        <section id="view-launcher" class="view-section">
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
                  @csrf
                  <div class="form-group">
                    <label class="form-label" for="trigger-agent-id">Agente Destino</label>
                    <select id="trigger-agent-id" class="form-control">
                      <option value="">Cargando agentes activos...</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="trigger-prompt">Prompt / Estímulo Inicial (AgentInput)</label>
                    <textarea id="trigger-prompt" class="form-control" placeholder="Ej: Analiza el estado actual del entorno y ejecuta el plan solicitado de forma segura."></textarea>
                  </div>

                  <div class="form-group" style="display: flex; align-items: center; gap: 8px; margin-top: -8px;">
                    <input type="checkbox" id="trigger-async" style="cursor: pointer; width: 16px; height: 16px; accent-color: var(--color-primary);">
                    <label for="trigger-async" style="cursor: pointer; font-size: 13px; color: var(--text-secondary); margin: 0; user-select: none;">
                      Modo Asíncrono (Recomendado para tareas multi-paso o de alta latencia con polling automático)
                    </label>
                  </div>

                  <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" id="btn-trigger-execution" class="btn btn-primary">
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
              <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="panel-title">
                  <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                  </svg>
                  Saltos Cognitivos y Transiciones de Nodos (LangGraph)
                </h3>
                <span id="exec-status-badge" class="badge badge-pending" style="display: none;">LISTO</span>
              </div>
              <div class="panel-body">
                <div id="execution-detail-panel">
                  <!-- Rejection Justification Alert Banner (HITL Governance) -->
                  <div id="execution-rejection-banner" style="display: none;"></div>

                  <!-- Step Execution Trace Timeline -->
                  <div class="trace-steps-container">
                    <div id="execution-steps-timeline" class="trace-steps-timeline">
                      <div style="font-size: 13px; color: var(--text-muted); padding: 24px; text-align: center;">
                        🚀 Dispara una ejecución desde el panel izquierdo para observar los saltos cognitivos en tiempo real.
                      </div>
                    </div>
                  </div>

                  <!-- Output Box -->
                  <div style="margin-top: 20px;">
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

        <!-- Section 4: EXECUTIONS HISTORY (Dedicated Full-Width Panel with Agent Type Filter) -->
        <section id="view-executions" class="view-section">
          <div class="panel">
            <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
              <h3 class="panel-title">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Historial de Ejecuciones Recientes
              </h3>
              <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <!-- Filter by Agent -->
                <div style="display: flex; align-items: center; gap: 6px;">
                  <label for="executions-filter-agent-id" style="font-size: 12px; color: var(--text-secondary); margin: 0; white-space: nowrap;">Agente:</label>
                  <select id="executions-filter-agent-id" class="form-control" style="font-size: 12px; padding: 6px 10px; min-width: 160px;">
                    <option value="ALL">Todos los agentes</option>
                  </select>
                </div>

                <!-- Filter by Status -->
                <div style="display: flex; align-items: center; gap: 6px;">
                  <label for="executions-filter-status" style="font-size: 12px; color: var(--text-secondary); margin: 0; white-space: nowrap;">Estado:</label>
                  <select id="executions-filter-status" class="form-control" style="font-size: 12px; padding: 6px 10px; min-width: 130px;">
                    <option value="ALL">Todos los estados</option>
                    <option value="COMPLETED">Completados</option>
                    <option value="PROCESSING">En Proceso</option>
                    <option value="WAITING_APPROVAL">Esperando Aprobación</option>
                    <option value="FAILED">Fallidos</option>
                    <option value="CANCELLED">Cancelados</option>
                  </select>
                </div>

                <!-- Filter by Date / Time Range -->
                <div style="display: flex; align-items: center; gap: 6px;">
                  <label for="executions-filter-date-from" style="font-size: 12px; color: var(--text-secondary); margin: 0; white-space: nowrap;">Desde:</label>
                  <input type="datetime-local" id="executions-filter-date-from" class="form-control" style="font-size: 11px; padding: 5px 8px; width: 175px;" title="Filtrar por fecha y hora inicial">
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                  <label for="executions-filter-date-to" style="font-size: 12px; color: var(--text-secondary); margin: 0; white-space: nowrap;">Hasta:</label>
                  <input type="datetime-local" id="executions-filter-date-to" class="form-control" style="font-size: 11px; padding: 5px 8px; width: 175px;" title="Filtrar por fecha y hora final">
                  <button type="button" id="btn-clear-date-filters" class="btn btn-secondary" style="font-size: 11px; padding: 5px 8px; display: none;" title="Limpiar rango de fechas">✕</button>
                </div>

                <!-- Search Input -->
                <input type="text" id="executions-search" class="form-control" style="font-size: 12px; padding: 6px 10px; width: 180px;" placeholder="Buscar en historial...">

                <button type="button" id="btn-refresh-executions-list" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">
                  🔄 Refrescar
                </button>
              </div>
            </div>
            <div class="panel-body" style="padding: 0;">
              <div class="table-container" style="max-height: 560px; overflow-y: auto;">
                <table class="table" id="table-executions-history" style="width: 100%; margin: 0;">
                  <thead>
                    <tr>
                      <th style="width: 30%; cursor: pointer; user-select: none;" class="sortable-th" data-sort="prompt" title="Ordenar por prompt">
                        Prompt / Instrucción <span class="sort-icon" id="sort-icon-prompt">⇅</span>
                      </th>
                      <th style="width: 15%; cursor: pointer; user-select: none;" class="sortable-th" data-sort="created_at" title="Ordenar por fecha y hora">
                        Fecha y Hora <span class="sort-icon" id="sort-icon-created_at">▼</span>
                      </th>
                      <th style="width: 15%; cursor: pointer; user-select: none;" class="sortable-th" data-sort="agent_name" title="Ordenar por agente">
                        Agente <span class="sort-icon" id="sort-icon-agent_name">⇅</span>
                      </th>
                      <th style="width: 13%; cursor: pointer; user-select: none;" class="sortable-th" data-sort="user" title="Ordenar por usuario">
                        Usuario <span class="sort-icon" id="sort-icon-user">⇅</span>
                      </th>
                      <th style="width: 12%; cursor: pointer; user-select: none;" class="sortable-th" data-sort="status" title="Ordenar por estado">
                        Estado <span class="sort-icon" id="sort-icon-status">⇅</span>
                      </th>
                      <th style="width: 15%; text-align: right;">Acción</th>
                    </tr>
                  </thead>
                  <tbody id="executions-history-body">
                    <tr>
                      <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">
                        Cargando historial de ejecuciones...
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </section>
      </main>

      <!-- Console Footer -->
      <footer class="console-footer">
        <div class="console-footer-inner">
          <div>
            <span>&copy; {{ date('Y') }} • Todos los derechos reservados por</span>
            <a href="https://javiergalvez.com" target="_blank" rel="noopener noreferrer" class="console-footer-link">Javier Gálvez</a>
          </div>
          <div class="console-footer-meta">
            <span>Control Plane</span>
            <span>•</span>
            <span>Ecosistema de Agentes Autónomos</span>
          </div>
        </div>
      </footer>
    </div>
  </div>

  <!-- Modal User Profile -->
  <div id="modal-user-profile" class="modal-backdrop">
    <div class="modal-dialog" style="max-width: 520px;">
      <div class="modal-header">
        <div style="display: flex; align-items: center; gap: 8px;">
          <h3>👤 Mis Datos y Credenciales</h3>
        </div>
        <button class="modal-close">&times;</button>
      </div>
      <form id="form-user-profile">
        @csrf
        <div class="modal-body" style="display: flex; flex-direction: column; gap: 14px;">
          <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
            <span style="font-size: 12px; color: var(--text-muted);">Rol de Usuario:</span>
            <span id="profile-role-badge" class="badge badge-info">{{ strtoupper(auth()->user()->role ?? 'USUARIO') }}</span>
          </div>

          <div class="form-group">
            <label class="form-label" for="profile-name">Nombre Completo <span style="color: var(--accent-rose);">*</span></label>
            <input type="text" id="profile-name" class="form-control" value="{{ auth()->user()->name ?? '' }}" required placeholder="Tu nombre">
          </div>

          <div class="form-group">
            <label class="form-label" for="profile-email">Correo Electrónico <span style="color: var(--accent-rose);">*</span></label>
            <input type="email" id="profile-email" class="form-control" value="{{ auth()->user()->email ?? '' }}" required placeholder="tu@correo.com">
          </div>

          <div style="border-top: 1px solid var(--border-color); padding-top: 12px; margin-top: 6px;">
            <div style="font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 10px;">
              🔑 Actualizar Contraseña (opcional)
            </div>

            <div class="form-group" style="margin-bottom: 10px;">
              <label class="form-label" for="profile-current-password">Contraseña Actual</label>
              <input type="password" id="profile-current-password" class="form-control" placeholder="Necesaria solo si cambias de clave" autocomplete="current-password">
            </div>

            <div class="form-group" style="margin-bottom: 10px;">
              <label class="form-label" for="profile-new-password">Nueva Contraseña (mínimo 8 caracteres)</label>
              <input type="password" id="profile-new-password" class="form-control" placeholder="••••••••" autocomplete="new-password">
            </div>

            <div class="form-group">
              <label class="form-label" for="profile-new-password-confirmation">Confirmar Nueva Contraseña</label>
              <input type="password" id="profile-new-password-confirmation" class="form-control" placeholder="••••••••" autocomplete="new-password">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-modal-dismiss>Cancelar</button>
          <button type="submit" id="btn-save-profile" class="btn btn-primary">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Approve -->
  <div id="modal-approve" class="modal-backdrop">
    <div class="modal-dialog" style="max-width: 640px;">
      <div class="modal-header">
        <h3>✓ Aprobar Plan de Ejecución</h3>
        <button class="modal-close">&times;</button>
      </div>
      <form id="form-confirm-approve">
        @csrf
        <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
          <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">
            ¿Confirmas la autorización para ejecutar el plan de acción del hilo <strong id="approve-modal-thread-id" style="color: var(--text-primary); font-family: var(--font-mono);"></strong>?
          </p>
          <div id="approve-modal-plan-preview" style="margin-bottom: 14px;"></div>
          <div class="form-group">
            <label class="form-label" for="approve-justification">Justificación u Observaciones (Opcional):</label>
            <textarea id="approve-justification" class="form-control" placeholder="Ej: Plan validado y autorizado por el operador de gobernanza."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-modal-dismiss>Cancelar</button>
          <button type="submit" id="btn-submit-approve" class="btn btn-primary">Conceder Aprobación</button>
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
        @csrf
        <div class="modal-body">
          <p style="font-size: 13px; color: var(--text-secondary);">
            Se cancelará inmediatamente la ejecución del hilo <strong id="reject-modal-thread-id" style="color: var(--text-primary); font-family: var(--font-mono);"></strong> en el agente.
          </p>
          <div class="form-group">
            <label class="form-label" for="reject-justification">
              Motivo del Rechazo <span style="color: var(--accent-rose);">* (Obligatorio, mín. 5 caracteres)</span>:
            </label>
            <textarea id="reject-justification" class="form-control" required placeholder="Ej: Operación denegada según política de gobernanza activa." style="border-color: rgba(244, 63, 94, 0.4);"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-modal-dismiss>Cancelar</button>
          <button type="submit" id="btn-submit-reject" class="btn btn-danger">Confirmar Rechazo</button>
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
        @csrf
        <div class="modal-body">
          <p style="font-size: 13px; color: var(--text-secondary);">
            Registra un nuevo agente en el Control Plane. Se generará un <strong>Bootstrap Token</strong> de un solo uso válido por 24 horas.
          </p>
          <div class="form-group">
            <label class="form-label" for="create-agent-name">Nombre del Agente <span style="color: var(--accent-rose);">*</span></label>
            <input type="text" id="create-agent-name" class="form-control" required placeholder="Ej: Agente Cognitivo Producción">
          </div>
          <div class="form-group">
            <label class="form-label" for="create-agent-version">Versión Inicial</label>
            <input type="text" id="create-agent-version" class="form-control" value="1.0.0" placeholder="1.0.0">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-modal-dismiss>Cancelar</button>
          <button type="submit" id="btn-submit-create-agent" class="btn btn-primary">Generar Bootstrap Token</button>
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

  <!-- Modal Inspect Plan (Cognitive Graph & Execution Steps) -->
  <div id="modal-inspect-plan" class="modal-backdrop">
    <div class="modal-dialog" style="max-width: 920px; max-height: 90vh; display: flex; flex-direction: column;">
      <div class="modal-header">
        <div style="display: flex; align-items: center; gap: 12px;">
          <h3>🔍 Inspección de Ejecución HITL</h3>
          <span id="inspect-modal-risk-badge" class="badge badge-high">HIGH</span>
        </div>
        <button class="modal-close">&times;</button>
      </div>
      <div class="modal-body" style="overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 16px;">
        <!-- Thread and Agent meta -->
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color); font-size: 13px;">
          <div>
            <span style="color: var(--text-muted);">Agente:</span>
            <strong id="inspect-modal-agent-name" style="color: var(--text-primary); margin-left: 4px;">-</strong>
          </div>
          <div>
            <span style="color: var(--text-muted);">Hilo:</span>
            <strong id="inspect-modal-thread-id" style="color: var(--text-primary); font-family: var(--font-mono); margin-left: 4px; cursor: pointer;" title="Pulsar para copiar"></strong>
          </div>
        </div>

        <!-- Prompt Section -->
        <div class="hitl-inspect-prompt-box">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
            <span style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">💬 Prompt / Instrucción de Entrada</span>
          </div>
          <div id="inspect-modal-prompt-box" style="font-size: 13px; color: var(--text-primary); line-height: 1.5; white-space: pre-wrap;">-</div>
        </div>

        <!-- Full Action Plan & Mutative Stages Warning -->
        <div id="inspect-modal-action-plan-box"></div>

        <!-- Mode Toggle Tabs -->
        <div class="tabs-nav" style="border-bottom: 1px solid var(--border-color); margin-bottom: 4px;">
          <button type="button" id="tab-inspect-steps" class="tab-btn active" onclick="window.ConsoleApp.switchInspectTab('steps')">
            ⚡ Grafo & Saltos Cognitivos
          </button>
          <button type="button" id="tab-inspect-json" class="tab-btn" onclick="window.ConsoleApp.switchInspectTab('json')">
            { } JSON Técnico
          </button>
        </div>

        <!-- Steps Timeline View -->
        <div id="inspect-steps-container">
          <div id="inspect-steps-timeline" class="trace-steps-timeline inspect-timeline-padded">
            <!-- Rendered dynamically -->
          </div>
        </div>

        <!-- Raw JSON View -->
        <div id="inspect-json-container" style="display: none;">
          <pre id="inspect-plan-content" class="json-inspector-box" style="max-height: 440px;"></pre>
        </div>
      </div>
      <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; gap: 12px;">
        <div style="display: flex; gap: 8px;">
          <button type="button" id="btn-copy-plan-json" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">📋 Copiar JSON</button>
          <button type="button" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;" data-modal-dismiss>Cerrar</button>
        </div>
        <div style="display: flex; gap: 8px;">
          <button type="button" id="btn-inspect-reject" class="btn btn-danger" style="padding: 8px 16px;">
            ✕ Rechazar
          </button>
          <button type="button" id="btn-inspect-approve" class="btn btn-primary" style="padding: 8px 16px;">
            ✓ Aprobar Ejecución
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Agent Capabilities (Dedicated Clean Inspector) -->
  <div id="modal-agent-capabilities" class="modal-backdrop">
    <div class="modal-dialog" style="max-width: 880px; max-height: 88vh; display: flex; flex-direction: column;">
      <div class="modal-header">
        <div style="display: flex; align-items: center; gap: 12px;">
          <h3>🛠️ Capacidades y Herramientas del Agente</h3>
          <span id="agent-caps-id-badge" class="badge badge-info" style="font-family: var(--font-mono);">-</span>
        </div>
        <button class="modal-close">&times;</button>
      </div>
      <div class="modal-body" style="overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 16px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; font-size: 13px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color);">
          <div>
            <span style="color: var(--text-muted);">Agente:</span>
            <strong id="agent-caps-name" style="color: var(--text-primary); margin-left: 4px;">-</strong>
          </div>
          <div id="agent-caps-status-wrapper">
            <span id="agent-caps-status-badge" class="badge badge-success">ONLINE</span>
          </div>
        </div>

        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em;">
              Especificación Técnica de Capacidades (JSON Schema)
            </span>
            <button type="button" id="btn-copy-agent-caps" class="btn btn-secondary" style="font-size: 11px; padding: 4px 10px;">
              📋 Copiar JSON
            </button>
          </div>
          <pre id="agent-caps-json-content" class="json-inspector-box" style="max-height: 480px; margin: 0;">Cargando capacidades...</pre>
        </div>
      </div>
      <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
        <button type="button" class="btn btn-secondary" data-modal-dismiss>Cerrar</button>
      </div>
    </div>
  </div>

  <!-- Modal Execution Trace Viewer (Dedicated Modal) -->
  <div id="modal-execution-trace" class="modal-backdrop">
    <div class="modal-dialog" style="max-width: 900px; max-height: 90vh; display: flex; flex-direction: column;">
      <div class="modal-header">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
          <h3>🔍 Traza de Ejecución</h3>
          <span id="modal-trace-thread-badge" class="badge badge-info" style="font-family: var(--font-mono); font-size: 11px;">-</span>
          <span id="modal-trace-status-badge" class="badge badge-pending">PENDIENTE</span>
        </div>
        <button class="modal-close">&times;</button>
      </div>
      <div class="modal-body" style="overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 16px;">
        <!-- Metadata Header Bar -->
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; font-size: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color);">
          <div>
            <span style="color: var(--text-muted);">Agente:</span>
            <strong id="modal-trace-agent-name" style="color: var(--text-primary); margin-left: 4px;">-</strong>
          </div>
          <div>
            <span style="color: var(--text-muted);">Usuario:</span>
            <strong id="modal-trace-user-name" style="color: var(--text-primary); margin-left: 4px;">-</strong>
          </div>
          <div>
            <span style="color: var(--text-muted);">Latencia / Duración:</span>
            <strong id="modal-trace-latency" style="color: var(--accent-cyan); margin-left: 4px;">-</strong>
          </div>
        </div>

        <!-- Prompt Box -->
        <div>
          <div class="form-label" style="margin-bottom: 6px;">Prompt de Entrada (AgentInput):</div>
          <div id="modal-trace-prompt" class="execution-prompt-container" style="font-size: 13px;">-</div>
        </div>

        <!-- Rejection Banner if any -->
        <div id="modal-trace-rejection-banner" style="display: none;"></div>

        <!-- Cognitive Steps Timeline -->
        <div>
          <div class="form-label" style="margin-bottom: 8px;">Saltos Cognitivos y Transiciones de Nodos (LangGraph):</div>
          <div id="modal-trace-steps-timeline" class="trace-steps-timeline">
            <div style="font-size: 12px; color: var(--text-muted); padding: 12px; text-align: center;">Cargando saltos cognitivos...</div>
          </div>
        </div>

        <!-- Output Box -->
        <div>
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
            <div class="form-label">Resultado Obtenido (AgentOutput):</div>
            <button type="button" id="btn-copy-modal-trace-output" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px;">📋 Copiar Resultado</button>
          </div>
          <pre id="modal-trace-output" class="execution-code-box" style="max-height: 220px; overflow-y: auto;">-</pre>
        </div>

        <!-- Technical Raw JSON Details -->
        <details class="trace-details-card" style="margin-top: 4px;">
          <summary style="font-weight: 600; color: var(--text-secondary); cursor: pointer; padding: 10px 14px;">
            🔍 Ver Respuesta JSON Completa
          </summary>
          <div style="padding: 12px;">
            <pre id="modal-trace-raw-json" class="json-inspector-box" style="max-height: 300px; margin: 0;"></pre>
          </div>
        </details>
      </div>
      <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
        <button type="button" id="btn-copy-modal-trace-json" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">📋 Copiar JSON Completo</button>
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
