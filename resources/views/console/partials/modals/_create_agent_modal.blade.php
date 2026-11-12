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

