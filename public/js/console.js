/**
 * jg_dashboard Control Plane - Web Console Client Logic (Vanilla JS)
 * Handles tab navigation, agent telemetry, HITL resolutions and live polling.
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
      agentViewMode: 'cards', // 'cards' or 'table'
      agentFilterStatus: 'ALL',
      agentSearchQuery: '',
      hitlRiskFilter: 'ALL',
      hitlSearchQuery: '',
      hitlAgentFilter: 'ALL',
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
          this.showToast('Datos sincronizados desde el Control Plane', 'info');
        });
      }

      // Modals Close
      document.querySelectorAll('.modal-close, [data-modal-dismiss]').forEach((el) => {
        el.addEventListener('click', () => this.closeAllModals());
      });

      // Agent View Switcher (Cards vs Table)
      document.querySelectorAll('.view-btn[data-view]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const mode = btn.getAttribute('data-view');
          this.setAgentViewMode(mode);
        });
      });

      // Agent Search Input
      const searchInput = document.getElementById('agents-search');
      if (searchInput) {
        searchInput.addEventListener('input', (e) => {
          this.state.agentSearchQuery = e.target.value.toLowerCase().trim();
          this.renderFilteredAgents();
        });
      }

      // Agent Status Filter
      const filterSelect = document.getElementById('agents-filter-status');
      if (filterSelect) {
        filterSelect.addEventListener('change', (e) => {
          this.state.agentFilterStatus = e.target.value.toUpperCase();
          this.renderFilteredAgents();
        });
      }

      // Open Create Agent Modal Button
      const createAgentBtn = document.getElementById('btn-create-agent');
      if (createAgentBtn) {
        createAgentBtn.addEventListener('click', () => {
          this.openCreateAgentModal();
        });
      }

      // Form Create Agent Submit
      const formCreateAgent = document.getElementById('form-create-agent');
      if (formCreateAgent) {
        formCreateAgent.addEventListener('submit', (e) => {
          e.preventDefault();
          this.submitCreateAgent();
        });
      }

      // Copy Generated Token Button
      const copyTokenBtn = document.getElementById('btn-copy-token');
      if (copyTokenBtn) {
        copyTokenBtn.addEventListener('click', () => {
          const tokenVal = document.getElementById('created-token-value')?.textContent;
          if (tokenVal) {
            this.copyToClipboard(tokenVal, 'Bootstrap Token copiado al portapapeles');
          }
        });
      }

      // Copy Plan JSON Button in Inspector Modal
      const copyPlanBtn = document.getElementById('btn-copy-plan-json');
      if (copyPlanBtn) {
        copyPlanBtn.addEventListener('click', () => {
          const planJson = document.getElementById('inspect-plan-content')?.textContent;
          if (planJson) {
            this.copyToClipboard(planJson, 'JSON del plan copiado al portapapeles');
          }
        });
      }

      // HITL Risk Filter Pills
      document.querySelectorAll('.hitl-pill-btn[data-risk]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const risk = btn.getAttribute('data-risk');
          this.setHitlRiskFilter(risk);
        });
      });

      // HITL Search Input
      const hitlSearch = document.getElementById('hitl-search');
      if (hitlSearch) {
        hitlSearch.addEventListener('input', (e) => {
          this.state.hitlSearchQuery = e.target.value.toLowerCase().trim();
          this.renderFilteredApprovals();
        });
      }

      // HITL Agent Filter Select
      const hitlAgentSelect = document.getElementById('hitl-filter-agent');
      if (hitlAgentSelect) {
        hitlAgentSelect.addEventListener('change', (e) => {
          this.state.hitlAgentFilter = e.target.value;
          this.renderFilteredApprovals();
        });
      }

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

    setAgentViewMode(mode) {
      this.state.agentViewMode = mode;

      document.querySelectorAll('.view-btn[data-view]').forEach((b) => {
        b.classList.toggle('active', b.getAttribute('data-view') === mode);
      });

      const cardsContainer = document.getElementById('agents-cards-container');
      const tableContainer = document.getElementById('agents-table-container');

      if (cardsContainer && tableContainer) {
        if (mode === 'cards') {
          cardsContainer.style.display = 'grid';
          tableContainer.style.display = 'none';
        } else {
          cardsContainer.style.display = 'none';
          tableContainer.style.display = 'block';
        }
      }
    },

    switchTab(tabName) {
      this.state.activeTab = tabName;

      document.querySelectorAll('.nav-link[data-tab]').forEach((link) => {
        link.classList.toggle('active', link.getAttribute('data-tab') === tabName);
      });

      document.querySelectorAll('.view-section').forEach((section) => {
        section.classList.remove('active');
      });

      const activeView = document.getElementById(`view-${tabName}`);
      if (activeView) {
        activeView.classList.add('active');
      }

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
        this.renderAgentsKPI(data.summary);
        this.renderFilteredAgents();
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

    renderAgentsKPI(summary) {
      if (!summary) return;
      this.setTextContent('kpi-agents-total', summary.total ?? this.state.agents.length);
      this.setTextContent('kpi-agents-active', summary.active ?? 0);
      this.setTextContent('kpi-agents-offline', summary.offline ?? 0);
    },

    renderFilteredAgents() {
      const { agents, agentSearchQuery, agentFilterStatus } = this.state;

      const filtered = agents.filter((agent) => {
        // Status filter
        if (agentFilterStatus !== 'ALL' && agent.status !== agentFilterStatus) {
          return false;
        }
        // Search query filter
        if (agentSearchQuery) {
          const name = (agent.name || '').toLowerCase();
          const id = (agent.agent_id || '').toLowerCase();
          return name.includes(agentSearchQuery) || id.includes(agentSearchQuery);
        }
        return true;
      });

      // Populate Select in Trigger Form
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

      this.renderAgentsCards(filtered);
      this.renderAgentsTable(filtered);
    },

    renderAgentsCards(agents) {
      const container = document.getElementById('agents-cards-container');
      if (!container) return;

      if (agents.length === 0) {
        container.innerHTML = `
          <div class="panel" style="grid-column: 1 / -1; padding: 48px; text-align: center;">
            <div style="font-size: 36px; margin-bottom: 12px;">🔍</div>
            <div style="font-size: 16px; font-weight: 600; color: var(--text-primary);">No se encontraron agentes</div>
            <div style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">Ajusta los filtros de búsqueda o registra un nuevo agente.</div>
          </div>
        `;
        return;
      }

      container.innerHTML = agents
        .map((agent) => {
          const statusClass =
            agent.status === 'ACTIVE'
              ? 'status-active'
              : agent.status === 'PENDING'
              ? 'status-pending'
              : 'status-offline';

          const badgeClass =
            agent.status === 'ACTIVE'
              ? 'badge-active'
              : agent.status === 'PENDING'
              ? 'badge-pending'
              : 'badge-offline';

          const metrics = agent.metrics || {};
          const cpuUsage = metrics.cpu_percent ? `${metrics.cpu_percent}%` : 'N/A';
          const memUsage = metrics.memory_percent ? `${metrics.memory_percent}%` : 'N/A';
          const latency = metrics.latency_ms ? `${metrics.latency_ms}ms` : '<10ms';

          const capabilities = agent.capabilities || {};
          const toolsCount = capabilities.tools ? Object.keys(capabilities.tools).length : 0;
          const enginesCount = capabilities.engines ? Object.keys(capabilities.engines).length : 0;

          return `
            <div class="agent-card ${statusClass}">
              <div class="agent-card-header">
                <div>
                  <div class="agent-card-title">${this.escapeHtml(agent.name)}</div>
                  <div class="agent-card-id" title="Copiar ID" onclick="window.ConsoleApp.copyToClipboard('${this.escapeHtml(agent.agent_id)}', 'ID de agente copiado')">
                    📋 ${this.escapeHtml(agent.agent_id)}
                  </div>
                </div>
                <span class="badge ${badgeClass}">
                  <span class="status-dot ${agent.status === 'ACTIVE' ? 'pulse' : ''}" style="width: 6px; height: 6px;"></span>
                  ${agent.status}
                </span>
              </div>

              <div class="agent-telemetry-row">
                <div class="telemetry-item">
                  <span class="telemetry-label">CPU</span>
                  <span class="telemetry-value">${cpuUsage}</span>
                </div>
                <div class="telemetry-item">
                  <span class="telemetry-label">RAM</span>
                  <span class="telemetry-value">${memUsage}</span>
                </div>
                <div class="telemetry-item">
                  <span class="telemetry-label">Latencia</span>
                  <span class="telemetry-value" style="color: var(--accent-cyan);">${latency}</span>
                </div>
              </div>

              <div style="display: flex; gap: 8px; font-size: 11px; color: var(--text-secondary);">
                <span>🛠️ ${toolsCount} Herramientas</span>
                <span>•</span>
                <span>🧠 ${enginesCount} Engines</span>
                <span>•</span>
                <span>v${agent.version || '1.0.0'}</span>
              </div>

              <div class="agent-card-footer">
                <div class="agent-heartbeat-text">
                  <span>${agent.is_healthy ? '🟢 En línea' : '⚪ Desconectado'}</span>
                  <span>(${agent.last_heartbeat_at ? new Date(agent.last_heartbeat_at).toLocaleTimeString() : 'Sin reporte'})</span>
                </div>
                <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 11px;" onclick="window.ConsoleApp.inspectCapabilities('${this.escapeHtml(agent.agent_id)}')">
                  Capacidades
                </button>
              </div>
            </div>
          `;
        })
        .join('');
    },

    renderAgentsTable(agents) {
      const tbody = document.getElementById('agents-table-body');
      if (!tbody) return;

      if (agents.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 32px;">No se encontraron agentes con los filtros actuales.</td></tr>`;
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
                <div style="font-family: var(--font-mono); font-size: 11px; color: var(--text-muted); cursor: pointer;" onclick="window.ConsoleApp.copyToClipboard('${this.escapeHtml(agent.agent_id)}', 'ID de agente copiado')">
                  📋 ${this.escapeHtml(agent.agent_id)}
                </div>
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
                <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 11px;" onclick="window.ConsoleApp.inspectCapabilities('${this.escapeHtml(agent.agent_id)}')">
                  Capacidades
                </button>
              </td>
            </tr>
          `;
        })
        .join('');
    },

    setHitlRiskFilter(risk) {
      this.state.hitlRiskFilter = risk;
      document.querySelectorAll('.hitl-pill-btn[data-risk]').forEach((btn) => {
        btn.classList.toggle('active', btn.getAttribute('data-risk') === risk);
      });
      this.renderFilteredApprovals();
    },

    populateHitlAgentFilter() {
      const select = document.getElementById('hitl-filter-agent');
      if (!select) return;

      const currentVal = this.state.hitlAgentFilter;
      const agentsMap = new Map();

      this.state.pendingApprovals.forEach((app) => {
        if (app.agent_id) {
          agentsMap.set(app.agent_id, app.agent_name || app.agent_id);
        }
      });

      select.innerHTML = '<option value="ALL">Todos los Agentes</option>';
      agentsMap.forEach((name, id) => {
        const opt = document.createElement('option');
        opt.value = id;
        opt.textContent = `${name} (${id})`;
        if (id === currentVal) opt.selected = true;
        select.appendChild(opt);
      });
    },

    updateHitlSummaryKPI(summary) {
      const total = summary?.total_pending ?? this.state.pendingApprovals.length;
      const critical = summary?.critical_count ?? this.state.pendingApprovals.filter(a => a.risk_level === 'CRITICAL').length;
      const high = summary?.high_count ?? this.state.pendingApprovals.filter(a => a.risk_level === 'HIGH').length;

      this.setTextContent('counter-hitl-pending', total);
      const counterEl = document.getElementById('counter-hitl-pending');
      if (counterEl) {
        counterEl.classList.toggle('alert', total > 0);
      }

      this.setTextContent('kpi-hitl-total', total);
      this.setTextContent('kpi-hitl-critical', critical);
      this.setTextContent('kpi-hitl-high', high);

      this.setTextContent('hitl-count-all', total);
      this.setTextContent('hitl-count-critical', critical);
      this.setTextContent('hitl-count-high', high);
    },

    renderFilteredApprovals() {
      const { pendingApprovals, hitlRiskFilter, hitlAgentFilter, hitlSearchQuery } = this.state;

      const filtered = pendingApprovals.filter((app) => {
        // Risk level filter
        if (hitlRiskFilter !== 'ALL' && app.risk_level !== hitlRiskFilter) {
          return false;
        }
        // Agent filter
        if (hitlAgentFilter !== 'ALL' && app.agent_id !== hitlAgentFilter) {
          return false;
        }
        // Search filter
        if (hitlSearchQuery) {
          const summary = (app.action_summary || '').toLowerCase();
          const thread = (app.thread_id || '').toLowerCase();
          const agent = (app.agent_name || app.agent_id || '').toLowerCase();
          return summary.includes(hitlSearchQuery) || thread.includes(hitlSearchQuery) || agent.includes(hitlSearchQuery);
        }
        return true;
      });

      this.renderApprovalsCards(filtered);
    },

    extractPlanSteps(app) {
      const plan = app.proposed_plan || {};
      let steps = [];

      if (Array.isArray(plan.steps) && plan.steps.length > 0) {
        steps = plan.steps.map((s, idx) => {
          if (typeof s === 'string') {
            return { index: idx + 1, action: s, command: null, tool: null };
          }
          return {
            index: idx + 1,
            action: s.action || s.name || s.step || s.description || `Paso ${idx + 1}`,
            command: s.command || s.cmd || null,
            tool: s.tool || s.tool_name || null,
          };
        });
      } else if (Array.isArray(plan.actions) && plan.actions.length > 0) {
        steps = plan.actions.map((a, idx) => ({
          index: idx + 1,
          action: typeof a === 'string' ? a : (a.action || a.name || `Acción ${idx + 1}`),
          command: a.command || null,
          tool: a.tool || null,
        }));
      } else {
        const action = plan.action || plan.description || plan.summary || 'Ejecución de plan cognitivo';
        const command = plan.command || plan.cmd || null;
        const tool = (app.tools_involved && app.tools_involved.length > 0) ? app.tools_involved.join(', ') : null;
        steps = [{ index: 1, action, command, tool }];
      }
      return steps;
    },

    renderApprovalsCards(approvals) {
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

          const steps = this.extractPlanSteps(app);
          const stepsHtml = steps
            .map(
              (s) => `
              <div class="step-item">
                <span class="step-badge">${s.index}</span>
                <div class="step-header">
                  <span class="step-action-text">${this.escapeHtml(s.action)}</span>
                  ${s.tool ? `<span class="tool-tag">${this.escapeHtml(s.tool)}</span>` : ''}
                </div>
                ${s.command ? `<div class="step-command-box"><code>$ ${this.escapeHtml(s.command)}</code></div>` : ''}
              </div>
            `
            )
            .join('');

          const compensations = app.compensation_commands || app.proposed_plan?.compensation || [];
          let compensationHtml = '';
          if (Array.isArray(compensations) && compensations.length > 0) {
            const compItems = compensations
              .map((c) => `<div class="compensation-item">${this.escapeHtml(typeof c === 'string' ? c : (c.command || JSON.stringify(c)))}</div>`)
              .join('');
            compensationHtml = `
              <div class="compensation-box">
                <div class="compensation-header">
                  <span>🛡️ Comandos de Reversión (Rollback Automático)</span>
                </div>
                <div class="compensation-list">${compItems}</div>
              </div>
            `;
          }

          return `
            <div class="hitl-card ${isCritical ? 'risk-critical' : ''}" id="hitl-card-${app.id}">
              <div class="hitl-card-header">
                <div>
                  <div class="hitl-title">${this.escapeHtml(app.action_summary || 'Plan de Alto Riesgo')}</div>
                  <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                    Agente: <strong style="color: var(--text-primary);">${this.escapeHtml(app.agent_name || app.agent_id)}</strong>
                    <span style="margin: 0 6px;">•</span>
                    <span style="font-family: var(--font-mono); cursor: pointer;" title="Copiar Thread ID" onclick="window.ConsoleApp.copyToClipboard('${this.escapeHtml(app.thread_id)}', 'Thread ID copiado')">
                      🧵 ${this.escapeHtml(app.thread_id)}
                    </span>
                  </div>
                </div>
                <span class="badge ${isCritical ? 'badge-critical' : 'badge-high'}">${app.risk_level}</span>
              </div>

              <!-- Steps Breakdown Timeline -->
              <div class="hitl-steps-container">
                <div class="hitl-steps-title">
                  <span>📋 Desglose de Pasos (${steps.length})</span>
                </div>
                <div class="hitl-steps-timeline">
                  ${stepsHtml}
                </div>
              </div>

              <!-- Critical Tools -->
              ${toolsHtml ? `
                <div class="hitl-summary-box">
                  <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">Herramientas Críticas Requeridas</div>
                  <div class="tools-tag-list">${toolsHtml}</div>
                </div>
              ` : ''}

              <!-- Compensation / Rollback -->
              ${compensationHtml}

              <div class="hitl-time">
                ⏱️ Pausado: <strong>${app.elapsed_time || 'Recientemente'}</strong>
              </div>

              <div class="hitl-actions">
                <button type="button" class="btn btn-primary" style="flex: 1;" onclick="window.ConsoleApp.openApproveModal(${app.id}, '${this.escapeHtml(app.thread_id)}')">
                  ✓ Aprobar
                </button>
                <button type="button" class="btn btn-danger" style="flex: 1;" onclick="window.ConsoleApp.openRejectModal(${app.id}, '${this.escapeHtml(app.thread_id)}')">
                  ✕ Rechazar
                </button>
                <button type="button" class="btn btn-secondary" style="padding: 10px 14px;" title="Inspeccionar JSON del Plan" onclick="window.ConsoleApp.openInspectPlanModal(${app.id})">
                  🔍 Inspeccionar
                </button>
              </div>
            </div>
          `;
        })
        .join('');
    },

    openCreateAgentModal() {
      const nameInput = document.getElementById('create-agent-name');
      if (nameInput) nameInput.value = '';
      const versionInput = document.getElementById('create-agent-version');
      if (versionInput) versionInput.value = '1.0.0';

      const resultBox = document.getElementById('create-agent-result-box');
      if (resultBox) resultBox.style.display = 'none';

      const form = document.getElementById('form-create-agent');
      if (form) form.style.display = 'flex';

      const modal = document.getElementById('modal-create-agent');
      if (modal) modal.classList.add('open');
    },

    async submitCreateAgent() {
      const name = document.getElementById('create-agent-name')?.value?.trim();
      const version = document.getElementById('create-agent-version')?.value?.trim() || '1.0.0';

      if (!name) {
        this.showToast('El nombre del agente es obligatorio', 'error');
        return;
      }

      try {
        const res = await fetch('/console/agents', {
          method: 'POST',
          headers: {
            ...this.getAuthHeaders(),
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ name, version, ttl_hours: 24 }),
        });

        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Error al aprovisionar agente');

        const form = document.getElementById('form-create-agent');
        if (form) form.style.display = 'none';

        const resultBox = document.getElementById('create-agent-result-box');
        if (resultBox) {
          resultBox.style.display = 'flex';
          this.setTextContent('created-agent-id', data.agent_id);
          this.setTextContent('created-token-value', data.bootstrap_token);
        }

        this.showToast(`Agente [${data.name}] aprovisionado exitosamente`, 'success');
        this.fetchAgents();
      } catch (err) {
        this.showToast(`Error: ${err.message}`, 'error');
      }
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

    openInspectPlanModal(id) {
      const app = this.state.pendingApprovals.find((a) => a.id === id);
      if (!app) return;

      this.setTextContent('inspect-modal-thread-id', app.thread_id);
      const contentEl = document.getElementById('inspect-plan-content');
      if (contentEl) {
        contentEl.textContent = JSON.stringify(app.proposed_plan || app, null, 2);
      }

      const modal = document.getElementById('modal-inspect-plan');
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
        let res = await fetch(`/console/approvals/${id}/approve`, {
          method: 'POST',
          headers: { ...this.getAuthHeaders(), 'Content-Type': 'application/json' },
          body: JSON.stringify({ justification }),
        });

        if (!res.ok) {
          res = await fetch(`/api/v1/approvals/${id}/approve`, {
            method: 'POST',
            headers: { ...this.getAuthHeaders(), 'Content-Type': 'application/json' },
            body: JSON.stringify({ justification }),
          });
        }

        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Error al aprobar');

        this.showToast('✓ Solicitud HITL aprobada y ejecución reanudada en el agente', 'success');
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
        let res = await fetch(`/console/approvals/${id}/reject`, {
          method: 'POST',
          headers: { ...this.getAuthHeaders(), 'Content-Type': 'application/json' },
          body: JSON.stringify({ justification }),
        });

        if (!res.ok) {
          res = await fetch(`/api/v1/approvals/${id}/reject`, {
            method: 'POST',
            headers: { ...this.getAuthHeaders(), 'Content-Type': 'application/json' },
            body: JSON.stringify({ justification }),
          });
        }

        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Error al rechazar');

        this.showToast('✕ Solicitud HITL rechazada y ejecución cancelada en el agente', 'success');
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

    copyToClipboard(text, successMsg = 'Copiado al portapapeles') {
      if (!text) return;
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
          this.showToast(successMsg, 'success');
        });
      } else {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        this.showToast(successMsg, 'success');
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
