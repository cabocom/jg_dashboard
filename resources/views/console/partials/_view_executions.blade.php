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

