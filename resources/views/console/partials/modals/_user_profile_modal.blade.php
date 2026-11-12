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

