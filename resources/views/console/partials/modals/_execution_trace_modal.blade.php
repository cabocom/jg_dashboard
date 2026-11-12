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

