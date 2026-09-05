/**
 * jg_dashboard Control Plane - Web Console Client Logic (Vanilla JS)
 * Handles tab navigation, API calls, HITL resolutions and live polling.
 */

document.addEventListener('DOMContentLoaded', () => {
  const App = {
    state: {
      activeTab: 'agents',
      token: localStorage.getItem('jg_jwt_token') || '',
      agents: [],
      pendingApprovals: [],
      currentApprovalId: null,
      pollingInterval: null,
    },

    init() {
      this.bindEvents();
      this.loadTab(this.state.activeTab);
      this.fetchInitialData();
      this.startPolling();
    },

    bindEvents() {
      // Tab Navigation
      document.querySelectorAll('.nav-link[data-tab]').forEach((link) => {
        link.addEventListener('click', (e) => {
          e.preventDefault();
          const targetTab = link.getAttribute('data-tab');
          this.switchTab(targetTab);
        });
      });

      // Quick Refresh Button
      const refreshBtn = document.getElementById('btn-refresh');
      if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
          this.fetchInitialData();
          this.showToast('Datos actualizados desde el Control Plane', 'info');
        });
      }

      // Modals Close
      document.querySelectorAll('.modal-close, [data-modal-dismiss]').forEach((el) => {
        el.addEventListener('click', () => this.closeAllModals());
      });

      // Confirm Approve Form
      const formApprove = document.getElementById('form-confirm-approve');
      if (formApprove) {
        formApprove.addEventListener('submit', (e) => {
          e.preventDefault();
          this.submitApproval();
        });
      }

      // Confirm Reject Form
      const formReject = document.getElementById('form-confirm-reject');
      if (formReject) {
        formReject.addEventListener('submit', (e) => {
          e.preventDefault();
          this.submitRejection();
        });
      }

      // Trigger Execution Form
      const formTrigger = document.getElementById('form-trigger-execution');
      if (formTrigger) {
        formTrigger.addEventListener('submit', (e) => {
          e.preventDefault();
          this.submitTriggerExecution();
        });
      }
    },

    switchTab(tabName) {
      this.state.activeTab = tabName;

      // Update Nav Links
      document.querySelectorAll('.nav-link[data-tab]').forEach((link) => {
        link.classList.toggle('active', link.getAttribute('data-tab') === tabName);
      });

      // Update View Sections
      document.querySelectorAll('.view-section').forEach((section) => {
        section.classList.remove('active');
      });

      const activeView = document.getElementById(`view-${tabName}`);
      if (activeView) {
        activeView.classList.add('active');
      }

      // Update Page Title
      const pageTitleEl = document.getElementById('page-current-title');
      if (pageTitleEl) {
        const titles = {
          agents: 'Monitoreo y Catálogo de Agentes',
          approvals: 'Consola de Gobernanza Human-In-The-Loop (HITL)',
          executions: 'Pasarela de Orquestación y Ejecuciones',
        };
        pageTitleEl.textContent = titles[tabName] || 'Consola de Control';
      }

      this.loadTab(tabName);
    },

    loadTab(tabName) {
      if (tabName === 'agents') {
        this.fetchAgents();
      } else if (tabName === 'approvals') {
        this.fetchApprovals();
      }
    },

    async fetchInitialData() {
      await Promise.all([this.fetchAgents(), this.fetchApprovals()]);
    },

    async fetchAgents() {
      try {
        const res = await fetch('/api/v1/agents', {
          headers: this.getAuthHeaders(),
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        this.state.agents = data.data || [];
        this.renderAgents(this.state.agents, data.summary);
      } catch (err) {
        console.warn('Fallo al obtener agentes (requiere auth o agente activo):', err.message);
      }
    },

    async fetchApprovals() {
      try {
        const res = await fetch('/api/v1/approvals/pending', {
          headers: this.getAuthHeaders(),
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        this.state.pendingApprovals = data.data || [];
        this.renderApprovals(this.state.pendingApprovals, data.summary);
      } catch (err) {
        console.warn('Fallo al obtener aprobaciones pendientes:', err.message);
      }
    },

    renderAgents(agents, summary) {
      // Render KPI Counters
      if (summary) {
        this.setTextContent('kpi-agents-total', summary.total ?? agents.length);
        this.setTextContent('kpi-agents-active', summary.active ?? 0);
        this.setTextContent('kpi-agents-offline', summary.offline ?? 0);
      }

      // Populate Agent Select in Trigger Form
      const selectAgent = document.getElementById('trigger-agent-id');
      if (selectAgent) {
        selectAgent.innerHTML = '';
        agents.forEach((agent) => {
          const opt = document.createElement('option');
          opt.value = agent.agent_id;
          opt.textContent = `${agent.name} (${agent.status})`;
          if (agent.status !== 'ACTIVE') opt.disabled = true;
          selectAgent.appendChild(opt);
        });
      }

      // Render Agents Table
      const tbody = document.getElementById('agents-table-body');
      if (!tbody) return;

      if (agents.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 32px;">No hay agentes registrados en este momento.</td></tr>`;
        return;
      }

      tbody.innerHTML = agents
        .map((agent) => {
          const badgeClass =
            agent.status === 'ACTIVE'
              ? 'badge-active'
              : agent.status === 'PENDING'
              ? 'badge-pending'
              : 'badge-offline';
          return `
            <tr>
              <td>
                <div style="font-weight: 600; color: var(--text-primary);">${this.escapeHtml(agent.name)}</div>
                <div style="font-family: var(--font-mono); font-size: 11px; color: var(--text-muted);">${this.escapeHtml(agent.agent_id)}</div>
              </td>
              <td><span class="badge ${badgeClass}">${agent.status}</span></td>
              <td>${agent.version || '1.0.0'}</td>
              <td>${agent.last_heartbeat_at ? new Date(agent.last_heartbeat_at).toLocaleTimeString() : 'Sin reporte'}</td>
              <td>
                <span style="font-size: 12px; color: ${agent.is_healthy ? 'var(--accent-emerald)' : 'var(--accent-rose)'}; font-weight: 600;">
                  ${agent.is_healthy ? '● En Línea' : '○ Desconectado'}
                </span>
              </td>
              <td>
                <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 11px;" onclick="window.ConsoleApp.inspectCapabilities('${agent.agent_id}')">
                  Capacidades
                </button>
              </td>
            </tr>
          `;
        })
        .join('');
    },

    renderApprovals(approvals, summary) {
      // Update badge counter
      const counterEl = document.getElementById('counter-hitl-pending');
      if (counterEl) {
        counterEl.textContent = approvals.length;
        counterEl.classList.toggle('alert', approvals.length > 0);
      }

      if (summary) {
        this.setTextContent('kpi-hitl-total', summary.total_pending ?? approvals.length);
        this.setTextContent('kpi-hitl-critical', summary.critical_count ?? 0);
        this.setTextContent('kpi-hitl-high', summary.high_count ?? 0);
      }

      const container = document.getElementById('hitl-cards-container');
      if (!container) return;

      if (approvals.length === 0) {
        container.innerHTML = `
          <div class="panel" style="grid-column: 1 / -1; padding: 48px; text-align: center;">
            <div style="font-size: 36px; margin-bottom: 12px;">🛡️</div>
            <div style="font-size: 16px; font-weight: 600; color: var(--text-primary);">No hay solicitudes HITL pendientes</div>
            <div style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">Todas las ejecuciones cognitivas operan de forma autónoma y segura.</div>
          </div>
        `;
        return;
      }

      container.innerHTML = approvals
        .map((app) => {
          const isCritical = app.risk_level === 'CRITICAL';
          const toolsHtml = (app.tools_involved || [])
            .map((t) => `<span class="tool-tag">${this.escapeHtml(t)}</span>`)
            .join(' ');

          return `
            <div class="hitl-card ${isCritical ? 'risk-critical' : ''}">
              <div class="hitl-card-header">
                <div>
                  <div class="hitl-title">${this.escapeHtml(app.action_summary || 'Plan de Alto Riesgo')}</div>
                  <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                    Agente: <strong style="color: var(--text-primary);">${this.escapeHtml(app.agent_name || app.agent_id)}</strong>
                  </div>
                </div>
                <span class="badge ${isCritical ? 'badge-critical' : 'badge-high'}">${app.risk_level}</span>
              </div>

              <div class="hitl-summary-box">
                <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">Herramientas Críticas</div>
                <div class="tools-tag-list">${toolsHtml || '<span style="color: var(--text-muted);">Sin herramientas explícitas</span>'}</div>
              </div>

              <div class="hitl-time">
                ⏱️ Pausado: <strong>${app.elapsed_time || 'Recientemente'}</strong>
              </div>

              <div class="hitl-actions">
                <button class="btn btn-primary" style="flex: 1;" onclick="window.ConsoleApp.openApproveModal(${app.id}, '${this.escapeHtml(app.thread_id)}')">
                  ✓ Aprobar
                </button>
                <button class="btn btn-danger" style="flex: 1;" onclick="window.ConsoleApp.openRejectModal(${app.id}, '${this.escapeHtml(app.thread_id)}')">
                  ✕ Rechazar
                </button>
              </div>
            </div>
          `;
        })
        .join('');
    },

    openApproveModal(id, threadId) {
      this.state.currentApprovalId = id;
      this.setTextContent('approve-modal-thread-id', threadId);
      const input = document.getElementById('approve-justification');
      if (input) input.value = '';
      const modal = document.getElementById('modal-approve');
      if (modal) modal.classList.add('open');
    },

    openRejectModal(id, threadId) {
      this.state.currentApprovalId = id;
      this.setTextContent('reject-modal-thread-id', threadId);
      const input = document.getElementById('reject-justification');
      if (input) input.value = '';
      const modal = document.getElementById('modal-reject');
      if (modal) modal.classList.add('open');
    },

    closeAllModals() {
      document.querySelectorAll('.modal-backdrop').forEach((m) => m.classList.remove('open'));
      this.state.currentApprovalId = null;
    },

    async submitApproval() {
      const id = this.state.currentApprovalId;
      if (!id) return;

      const justification = document.getElementById('approve-justification')?.value || '';
      try {
        const res = await fetch(`/api/v1/approvals/${id}/approve`, {
          method: 'POST',
          headers: { ...this.getAuthHeaders(), 'Content-Type': 'application/json' },
          body: JSON.stringify({ justification }),
        });

        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Error al aprobar');

        this.showToast('Solicitud HITL aprobada y reanudada en el agente', 'success');
        this.closeAllModals();
        this.fetchApprovals();
      } catch (err) {
        this.showToast(`Error: ${err.message}`, 'error');
      }
    },

    async submitRejection() {
      const id = this.state.currentApprovalId;
      if (!id) return;

      const justification = document.getElementById('reject-justification')?.value || '';
      if (justification.trim().length < 5) {
        this.showToast('El motivo de rechazo debe contener al menos 5 caracteres', 'error');
        return;
      }

      try {
        const res = await fetch(`/api/v1/approvals/${id}/reject`, {
          method: 'POST',
          headers: { ...this.getAuthHeaders(), 'Content-Type': 'application/json' },
          body: JSON.stringify({ justification }),
        });

        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Error al rechazar');

        this.showToast('Solicitud rechazada y cancelada en el agente', 'success');
        this.closeAllModals();
        this.fetchApprovals();
      } catch (err) {
        this.showToast(`Error: ${err.message}`, 'error');
      }
    },

    async submitTriggerExecution() {
      const agentId = document.getElementById('trigger-agent-id')?.value;
      const prompt = document.getElementById('trigger-prompt')?.value;

      if (!agentId || !prompt || prompt.trim().length === 0) {
        this.showToast('Selecciona un agente y redacta un prompt', 'error');
        return;
      }

      const resultBox = document.getElementById('execution-result-box');
      if (resultBox) {
        resultBox.textContent = 'Enviando directiva AgentInput hacia el Control Plane...';
      }

      try {
        const res = await fetch('/api/v1/executions/trigger', {
          method: 'POST',
          headers: { ...this.getAuthHeaders(), 'Content-Type': 'application/json' },
          body: JSON.stringify({ agent_id: agentId, prompt: prompt.trim() }),
        });

        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Error al disparar ejecución');

        this.showToast('Ejecución enviada exitosamente al agente', 'success');
        if (resultBox) {
          resultBox.textContent = JSON.stringify(data, null, 2);
        }
        this.fetchApprovals();
      } catch (err) {
        this.showToast(`Error: ${err.message}`, 'error');
        if (resultBox) {
          resultBox.textContent = `Error: ${err.message}`;
        }
      }
    },

    async inspectCapabilities(agentId) {
      try {
        const res = await fetch(`/api/v1/agents/${agentId}/capabilities`, {
          headers: this.getAuthHeaders(),
        });
        const data = await res.json();
        alert(`Capacidades del agente [${agentId}]:\n` + JSON.stringify(data.capabilities || data, null, 2));
      } catch (err) {
        this.showToast('Error al inspeccionar capacidades del agente', 'error');
      }
    },

    startPolling() {
      if (this.state.pollingInterval) clearInterval(this.state.pollingInterval);
      this.state.pollingInterval = setInterval(() => {
        this.loadTab(this.state.activeTab);
      }, 15000);
    },

    getAuthHeaders() {
      const headers = { Accept: 'application/json' };
      if (this.state.token) {
        headers['Authorization'] = `Bearer ${this.state.token}`;
      }
      return headers;
    },

    showToast(message, type = 'info') {
      const container = document.getElementById('toast-container');
      if (!container) return;

      const toast = document.createElement('div');
      toast.className = `toast ${type}`;
      toast.innerHTML = `<span>${type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ'}</span> ${this.escapeHtml(message)}`;
      container.appendChild(toast);

      setTimeout(() => {
        toast.remove();
      }, 4000);
    },

    setTextContent(elementId, text) {
      const el = document.getElementById(elementId);
      if (el) el.textContent = text;
    },

    escapeHtml(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    },
  };

  window.ConsoleApp = App;
  App.init();
});
