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

