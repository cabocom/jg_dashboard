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

