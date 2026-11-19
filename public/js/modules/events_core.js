/**
 * Core DOM Event Listeners module for javiergalvez-ia Console.
 */

export function bindCoreEvents(app) {
  // Protección global anti-doble clic en botones de acción
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (!btn || btn.disabled) return;
    if (btn.type === 'submit') return;
    if (btn.classList.contains('tab-btn') || btn.classList.contains('view-btn') || btn.classList.contains('hitl-pill-btn') || btn.classList.contains('theme-option-btn') || btn.id === 'btn-toggle-theme') {
      return;
    }
    btn.disabled = true;
    setTimeout(() => {
      if (document.body.contains(btn)) {
        btn.disabled = false;
      }
    }, 600);
  });

  // Tab Navigation
  document.querySelectorAll('.nav-link[data-tab]').forEach((link) => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const targetTab = link.getAttribute('data-tab');
      app.switchTab(targetTab);
    });
  });

  // Theme Dropdown Toggle & Options
  const btnToggleTheme = document.getElementById('btn-toggle-theme');
  const themeMenu = document.getElementById('theme-dropdown-menu');
  if (btnToggleTheme && themeMenu) {
    btnToggleTheme.addEventListener('click', (e) => {
      e.stopPropagation();
      const isVisible = themeMenu.style.display === 'flex';
      themeMenu.style.display = isVisible ? 'none' : 'flex';
    });

    document.querySelectorAll('.theme-option-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const theme = btn.getAttribute('data-theme');
        if (theme) {
          app.applyTheme(theme, true);
          themeMenu.style.display = 'none';
        }
      });
    });

    document.addEventListener('click', (e) => {
      if (!btnToggleTheme.contains(e.target) && !themeMenu.contains(e.target)) {
        themeMenu.style.display = 'none';
      }
    });
  }

  // User Profile Modal Open & Form
  const btnUserProfile = document.getElementById('btn-user-profile');
  if (btnUserProfile) {
    btnUserProfile.addEventListener('click', () => app.openUserProfileModal());
  }

  const formProfile = document.getElementById('form-user-profile');
  if (formProfile) {
    formProfile.addEventListener('submit', (e) => {
      e.preventDefault();
      app.submitUserProfile();
    });
  }

  // Modals Close
  document.querySelectorAll('.modal-close, [data-modal-dismiss]').forEach((el) => {
    el.addEventListener('click', () => app.closeAllModals());
  });

  // Agent View Switcher (Cards vs Table)
  document.querySelectorAll('.view-btn[data-view]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const mode = btn.getAttribute('data-view');
      app.setAgentViewMode(mode);
    });
  });

  // Agent Search Input & Status Filter
  const searchInput = document.getElementById('agents-search');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      app.state.agentSearchQuery = e.target.value.toLowerCase().trim();
      app.renderFilteredAgents();
    });
  }

  const filterSelect = document.getElementById('agents-filter-status');
  if (filterSelect) {
    filterSelect.addEventListener('change', (e) => {
      app.state.agentFilterStatus = e.target.value.toUpperCase();
      app.renderFilteredAgents();
    });
  }

  // Open Create Agent Modal & Submit
  const createAgentBtn = document.getElementById('btn-create-agent');
  if (createAgentBtn) {
    createAgentBtn.addEventListener('click', () => app.openCreateAgentModal());
  }

  const formCreateAgent = document.getElementById('form-create-agent');
  if (formCreateAgent) {
    formCreateAgent.addEventListener('submit', (e) => {
      e.preventDefault();
      app.submitCreateAgent();
    });
  }

  // Copy Buttons
  const copyTokenBtn = document.getElementById('btn-copy-token');
  if (copyTokenBtn) {
    copyTokenBtn.addEventListener('click', () => {
      const tokenVal = document.getElementById('created-token-value')?.textContent;
      if (tokenVal) app.copyToClipboard(tokenVal, 'Bootstrap Token copiado al portapapeles');
    });
  }

  const copyPlanBtn = document.getElementById('btn-copy-plan-json');
  if (copyPlanBtn) {
    copyPlanBtn.addEventListener('click', () => {
      const planJson = document.getElementById('inspect-plan-content')?.textContent;
      if (planJson) app.copyToClipboard(planJson, 'JSON del plan copiado al portapapeles');
    });
  }
}
