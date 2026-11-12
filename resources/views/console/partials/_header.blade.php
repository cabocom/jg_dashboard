<!-- Top Header -->
<header class="top-header">
  <div class="page-title">
    <h2 id="page-current-title">Monitoreo y Catálogo de Agentes</h2>
  </div>

  <div class="header-actions" style="display: flex; align-items: center; gap: 8px;">
    <!-- Selector de Estilos / Temas (Solo Dos Temas: Blanco y Negro) -->
    <div class="theme-dropdown-wrapper" style="position: relative;">
      <button id="btn-toggle-theme" type="button" class="btn btn-secondary" title="Cambiar tema visual (Negro / Blanco)" style="display: flex; align-items: center; gap: 6px; font-size: 12px; padding: 7px 12px;">
        <span id="current-theme-icon">🌙</span>
        <span id="current-theme-label">Oscuro</span>
        <span style="font-size: 9px; opacity: 0.7;">▼</span>
      </button>
      <div id="theme-dropdown-menu" class="theme-dropdown-menu" style="display: none;">
        <button type="button" class="theme-option-btn active" data-theme="dark">
          <span class="theme-swatch" style="background: #090a0f; border: 1px solid rgba(255,255,255,0.2);"></span>
          <span>Oscuro (Negro)</span>
        </button>
        <button type="button" class="theme-option-btn" data-theme="light">
          <span class="theme-swatch" style="background: #ffffff; border: 1px solid #cbd5e1;"></span>
          <span>Claro (Blanco)</span>
        </button>
      </div>
    </div>

    <!-- Botón de Perfil de Usuario -->
    <button id="btn-user-profile" type="button" class="btn btn-secondary" title="Modificar mis datos de usuario y contraseña" style="display: flex; align-items: center; gap: 6px; font-size: 12px; padding: 7px 12px;">
      <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
      </svg>
      <span id="header-user-name">{{ auth()->user()->name ?? 'Mi Perfil' }}</span>
    </button>
  </div>
</header>

