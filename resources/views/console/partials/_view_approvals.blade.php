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

