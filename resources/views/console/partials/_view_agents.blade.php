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

