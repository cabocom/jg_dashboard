<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>jG Control Plane | Consola Operativa y HITL</title>
  <meta name="description" content="Plano de Control y Consola de Gobernanza Human-In-The-Loop para agentes cognitivos del ecosistema jG.">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  
  <!-- CSS Stylesheet -->
  <link rel="stylesheet" href="/css/console.css?v={{ file_exists(public_path('css/console.css')) ? filemtime(public_path('css/console.css')) : time() }}">

  <script>
    (function() {
      const saved = localStorage.getItem('console_theme') || localStorage.getItem('jg_theme') || 'dark';
      const theme = (saved === 'light' || saved === 'blanco') ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', theme);
    })();
  </script>
</head>
<body>
  <div class="bg-mesh"></div>

  <div class="app-container">
    @include('console.partials._sidebar')

    <!-- Main Content Area -->
    <div class="main-wrapper">
      @include('console.partials._header')

      <!-- Content Body -->
      <main class="content-body">
        @include('console.partials._view_agents')
        @include('console.partials._view_approvals')
        @include('console.partials._view_launcher')
        @include('console.partials._view_executions')
      </main>

      @include('console.partials._footer')
    </div>
  </div>

  <!-- Governance and Operation Modals -->
  @include('console.partials.modals._user_profile_modal')
  @include('console.partials.modals._hitl_action_modals')
  @include('console.partials.modals._create_agent_modal')
  @include('console.partials.modals._inspect_plan_modal')
  @include('console.partials.modals._agent_capabilities_modal')
  @include('console.partials.modals._execution_trace_modal')

  <!-- Toast Notifications Container -->
  <div id="toast-container" class="toast-container"></div>

  <!-- Client JavaScript -->
  <script src="/js/console.js?v={{ file_exists(public_path('js/console.js')) ? filemtime(public_path('js/console.js')) : time() }}"></script>
</body>
</html>
