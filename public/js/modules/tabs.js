/**
 * Tab Navigation module for javiergalvez-ia Console.
 */

export const tabsMethods = {
  switchTab(tabName) {
    this.state.activeTab = tabName;

    document.querySelectorAll('.nav-link[data-tab]').forEach((link) => {
      const target = link.getAttribute('data-tab');
      if (target === tabName) {
        link.classList.add('active');
      } else {
        link.classList.remove('active');
      }
    });

    document.querySelectorAll('.view-tab').forEach((view) => {
      view.classList.remove('active');
    });

    const activeView = document.getElementById(`view-${tabName}`);
    if (activeView) {
      activeView.classList.add('active');
    }

    try {
      history.replaceState(null, '', `?tab=${tabName}`);
    } catch (e) {
      // Ignorar restricciones de historial
    }

    this.loadTab(tabName);
  },

  setAgentViewMode(mode) {
    this.state.agentViewMode = mode;

    document.querySelectorAll('.view-btn[data-view]').forEach((b) => {
      if (b.getAttribute('data-view') === mode) {
        b.classList.add('active');
      } else {
        b.classList.remove('active');
      }
    });

    const cardsContainer = document.getElementById('agents-cards-container');
    const tableContainer = document.getElementById('agents-table-container');

    if (mode === 'table') {
      if (cardsContainer) cardsContainer.style.display = 'none';
      if (tableContainer) tableContainer.style.display = 'block';
    } else {
      if (cardsContainer) cardsContainer.style.display = 'grid';
      if (tableContainer) tableContainer.style.display = 'none';
    }

    this.renderFilteredAgents();
  },
};
