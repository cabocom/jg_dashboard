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

