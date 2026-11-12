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

