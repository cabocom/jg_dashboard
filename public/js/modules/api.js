/**
 * API Fetching module for javiergalvez-ia Console.
 */

export const apiMethods = {
  getAuthHeaders() {
    const headers = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta && csrfMeta.content) {
      headers['X-CSRF-TOKEN'] = csrfMeta.content;
    }
    if (this.state.token) {
      headers['Authorization'] = `Bearer ${this.state.token}`;
    }
    return headers;
  },

  loadTab(tabName) {
    if (tabName === 'agents') {
      this.fetchAgents();
    } else if (tabName === 'approvals') {
      this.fetchApprovals();
    } else if (tabName === 'launcher') {
      if (!this.state.agents.length) {
        this.fetchAgents();
      }
    } else if (tabName === 'executions') {
      this.fetchExecutions();
    }
  },

  async fetchInitialData() {
    await Promise.all([this.fetchAgents(), this.fetchApprovals(), this.fetchExecutions()]);
  },

  async fetchAgents() {
    try {
      let res = await fetch('/console/agents', {
        headers: this.getAuthHeaders(),
      });
      if (!res.ok) {
        res = await fetch('/api/v1/agents', {
          headers: this.getAuthHeaders(),
        });
      }
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      this.state.agents = data.data || [];
      this.renderAgentsKPI(data.summary);
      this.renderFilteredAgents();
      this.updateExecutionFilterOptions(this.state.executionsList);
    } catch (err) {
      console.warn('Fallo al obtener agentes:', err.message);
    }
  },

  async fetchApprovals() {
    try {
      let res = await fetch('/console/approvals/pending', {
        headers: this.getAuthHeaders(),
      });
      if (!res.ok) {
        res = await fetch('/api/v1/approvals/pending', {
          headers: this.getAuthHeaders(),
        });
      }
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      this.state.pendingApprovals = data.data || [];
      this.updateHitlSummaryKPI(data.summary);
      this.populateHitlAgentFilter();
      this.renderFilteredApprovals();
    } catch (err) {
      console.warn('Fallo al obtener aprobaciones pendientes:', err.message);
    }
  },

  async fetchExecutions() {
    try {
      const res = await fetch('/console/executions?limit=50', {
        headers: this.getAuthHeaders(),
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      const list = data.executions || [];
      this.state.executionsList = list;
      this.updateExecutionFilterOptions(list);
      this.renderFilteredExecutions();
    } catch (err) {
      console.warn('Error al cargar historial de ejecuciones:', err);
      const tbody = document.getElementById('executions-history-body');
      if (tbody) {
        tbody.innerHTML = `
          <tr>
            <td colspan="6" style="text-align: center; color: var(--accent-red); padding: 18px;">
              Error al sincronizar historial: ${this.escapeHtml(err.message)}
            </td>
          </tr>
        `;
      }
    }
  },

  async inspectCapabilities(agentId) {
    const modal = document.getElementById('modal-agent-capabilities');
    const badgeId = document.getElementById('agent-caps-id-badge');
    const nameEl = document.getElementById('agent-caps-name');
    const contentBox = document.getElementById('agent-caps-json-content');

    if (badgeId) badgeId.textContent = agentId;
    if (nameEl) nameEl.textContent = agentId;
    if (contentBox) contentBox.textContent = 'Consultando capacidades técnicas del agente...';

    if (modal) {
      modal.classList.add('open');
    }

    try {
      let res = await fetch(`/console/agents/${agentId}/capabilities`, {
        headers: this.getAuthHeaders(),
      });
      if (!res.ok) {
        res = await fetch(`/api/v1/agents/${agentId}/capabilities`, {
          headers: this.getAuthHeaders(),
        });
      }
      if (!res.ok) {
        const errData = await res.json().catch(() => ({}));
        throw new Error(errData.message || `HTTP ${res.status}`);
      }
      const data = await res.json();
      const caps = data.capabilities || data;
      const formatted = JSON.stringify(caps, null, 2);

      if (contentBox) {
        contentBox.textContent = formatted;
      }
    } catch (err) {
      if (contentBox) {
        contentBox.textContent = `Error al consultar capacidades: ${err.message}`;
      }
      this.showToast(`Error al consultar capacidades: ${err.message}`, 'error');
    }
  },
};
