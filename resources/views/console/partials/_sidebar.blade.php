<!-- Sidebar Navigation -->
<aside class="sidebar">
  <div class="brand-section">
    <div class="brand-badge">jG</div>
    <div class="brand-info">
      <h1>Control Plane</h1>
      <span>Gobernanza & HITL</span>
    </div>
  </div>

  <nav class="nav-menu">
    <a href="#agents" id="nav-agents" class="nav-link active" data-tab="agents">
      <div class="nav-link-content">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
        </svg>
        <span>Agentes</span>
      </div>
    </a>

    <a href="#approvals" id="nav-approvals" class="nav-link" data-tab="approvals">
      <div class="nav-link-content">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        <span>Aprobaciones HITL</span>
      </div>
      <span id="counter-hitl-pending" class="nav-counter">{{ $pendingApprovalsCount ?? 0 }}</span>
    </a>

    <a href="#launcher" id="nav-launcher" class="nav-link" data-tab="launcher">
      <div class="nav-link-content">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>Lanzar Ejecución</span>
      </div>
    </a>

    <a href="#executions" id="nav-executions" class="nav-link" data-tab="executions">
      <div class="nav-link-content">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
        <span>Historial de Ejecuciones</span>
      </div>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="status-pill">
      <div class="status-dot pulse"></div>
      <span>Red Interna Operativa</span>
    </div>
    @if(auth()->user()?->isAdmin())
    <a href="/admin" id="btn-goto-admin" class="btn btn-secondary" style="font-size: 12px; width: 100%;">
      Ir al admin →
    </a>
    @endif
  </div>
</aside>

