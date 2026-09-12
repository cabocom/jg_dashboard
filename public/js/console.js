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
      expandedPrompts: {},
      currentSteps: [],
      inspectCurrentSteps: [],
      currentExecution: null,
      executionPollingInterval: null,
      recentExecutions: [],
      executionsList: [],
      isTriggeringExecution: false,
    },

    init() {
      const urlParams = new URLSearchParams(window.location.search);
      const requestedTab = urlParams.get('tab') || window.location.hash.replace('#', '');
      if (['agents', 'approvals', 'executions'].includes(requestedTab)) {
        this.state.activeTab = requestedTab;
      }

      this.bindEvents();
      this.switchTab(this.state.activeTab);
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

      // Direct Approve / Reject inside Inspector Modal
      const btnInspectApprove = document.getElementById('btn-inspect-approve');
      if (btnInspectApprove) {
        btnInspectApprove.addEventListener('click', () => {
          const currentId = this.state.currentApprovalId;
          const app = this.state.pendingApprovals.find((a) => String(a.id) === String(currentId));
          if (app) {
            document.getElementById('modal-inspect-plan')?.classList.remove('open');
            this.openApproveModal(app.id, app.thread_id);
          }
        });
      }

      const btnInspectReject = document.getElementById('btn-inspect-reject');
      if (btnInspectReject) {
        btnInspectReject.addEventListener('click', () => {
          const currentId = this.state.currentApprovalId;
          const app = this.state.pendingApprovals.find((a) => String(a.id) === String(currentId));
          if (app) {
            document.getElementById('modal-inspect-plan')?.classList.remove('open');
            this.openRejectModal(app.id, app.thread_id);
          }
        });
      }

      const inspectThreadEl = document.getElementById('inspect-modal-thread-id');
      if (inspectThreadEl) {
        inspectThreadEl.addEventListener('click', () => {
          const text = inspectThreadEl.textContent?.trim();
          if (text && text !== '-') {
            this.copyToClipboard(text, 'Thread ID copiado al portapapeles');
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

      // Load Execution Button
      const btnLoadExec = document.getElementById('btn-load-execution');
      if (btnLoadExec) {
        btnLoadExec.addEventListener('click', () => {
          const agentId = document.getElementById('execution-query-agent')?.value;
          const threadId = document.getElementById('execution-query-thread')?.value?.trim();
          if (!agentId || !threadId) {
            this.showToast('Selecciona un agente e introduce el Thread ID', 'error');
            return;
          }
          this.loadExecutionTrace(agentId, threadId);
        });
      }

      // Copy Execution Output Button
      const btnCopyExec = document.getElementById('btn-copy-exec-output');
      if (btnCopyExec) {
        btnCopyExec.addEventListener('click', () => {
          const text = document.getElementById('execution-output-box')?.textContent;
          if (text) {
            this.copyToClipboard(text, 'Resultado de ejecución copiado al portapapeles');
          }
        });
      }

      // Refresh Executions History List Button
      const btnRefreshExecList = document.getElementById('btn-refresh-executions-list');
      if (btnRefreshExecList) {
        btnRefreshExecList.addEventListener('click', () => {
          this.fetchExecutions();
          this.showToast('Historial de ejecuciones sincronizado', 'info');
        });
      }

      // Copy Agent Capabilities Button
      const btnCopyCaps = document.getElementById('btn-copy-agent-caps');
      if (btnCopyCaps) {
        btnCopyCaps.addEventListener('click', () => {
          const text = document.getElementById('agent-caps-json-content')?.textContent;
          if (text) {
            this.copyToClipboard(text, 'Capacidades técnicas copiadas al portapapeles');
          }
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

      // Populate Select in Trigger Form and Execution Query Toolbar
      const selectAgent = document.getElementById('trigger-agent-id');
      const queryAgent = document.getElementById('execution-query-agent');
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
      if (queryAgent) {
        const currentVal = queryAgent.value;
        queryAgent.innerHTML = '<option value="">Selecciona Agente</option>';
        agents.forEach((agent) => {
          const opt = document.createElement('option');
          opt.value = agent.agent_id;
          opt.textContent = `${agent.name} (${agent.agent_id})`;
          if (agent.agent_id === currentVal) opt.selected = true;
          queryAgent.appendChild(opt);
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

          const metrics = agent.metrics || agent.metrics_json || {};
          const cpuUsage = metrics.cpu_percent != null ? `${metrics.cpu_percent}%` : (metrics.cpu_load != null ? `${metrics.cpu_load}%` : '< 1%');
          const memUsage = metrics.memory_mb != null ? `${metrics.memory_mb} MB` : (metrics.memory_percent != null ? `${metrics.memory_percent}%` : 'N/A');
          const latency = metrics.latency_ms ? `${metrics.latency_ms}ms` : '< 10ms';

          const capabilities = agent.capabilities || agent.capabilities_json || {};
          const toolsCount = capabilities.tools_count != null ? capabilities.tools_count : (capabilities.tools ? (Array.isArray(capabilities.tools) ? capabilities.tools.length : Object.keys(capabilities.tools).length) : 0);
          const enginesCount = capabilities.engines_count != null ? capabilities.engines_count : (capabilities.engines ? (Array.isArray(capabilities.engines) ? capabilities.engines.length : Object.keys(capabilities.engines).length) : 0);
          const isHealthy = Boolean(agent.is_healthy);

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
                  <span>${isHealthy ? '🟢 En línea' : '⚪ Desconectado'}</span>
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

          const isHealthy = Boolean(agent.is_healthy);

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
                <span style="font-size: 12px; color: ${isHealthy ? 'var(--accent-emerald)' : 'var(--accent-rose)'}; font-weight: 600;">
                  ${isHealthy ? '● En Línea' : '○ Desconectado'}
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
      } else if (Array.isArray(app.cognitive_steps) && app.cognitive_steps.length > 0) {
        steps = app.cognitive_steps.map((s, idx) => ({
          index: idx + 1,
          action: s.title || s.action || s.name || (s.node ? `Nodo: ${s.node}` : `Paso ${idx + 1}`),
          command: s.command || null,
          tool: s.tool || s.tool_name || (s.details?.tool || s.details?.tool_name || null),
        }));
      } else if (Array.isArray(plan.cognitive_steps) && plan.cognitive_steps.length > 0) {
        steps = plan.cognitive_steps.map((s, idx) => ({
          index: idx + 1,
          action: s.title || s.action || s.name || (s.node ? `Nodo: ${s.node}` : `Paso ${idx + 1}`),
          command: s.command || null,
          tool: s.tool || s.tool_name || (s.details?.tool || s.details?.tool_name || null),
        }));
      } else {
        const action = plan.action || plan.description || plan.summary || 'Ejecución de plan cognitivo';
        const command = plan.command || plan.cmd || null;
        const tool = (app.tools_involved && app.tools_involved.length > 0) ? app.tools_involved.join(', ') : null;
        steps = [{ index: 1, action, command, tool }];
      }
      return steps;
    },

    togglePrompt(approvalId) {
      this.state.expandedPrompts[approvalId] = !this.state.expandedPrompts[approvalId];
      const app = this.state.pendingApprovals.find((a) => a.id === approvalId);
      if (!app) return;
      const promptEl = document.getElementById(`hitl-prompt-${approvalId}`);
      const btnEl = document.getElementById(`hitl-prompt-btn-${approvalId}`);
      if (!promptEl || !btnEl) return;
      const isExpanded = Boolean(this.state.expandedPrompts[approvalId]);
      const prompt = app.prompt || app.proposed_plan?.prompt || '';
      if (isExpanded) {
        promptEl.textContent = prompt;
        btnEl.textContent = 'Ver menos ▲';
      } else {
        promptEl.textContent = prompt.substring(0, 120) + '...';
        btnEl.textContent = 'Ver más ▼';
      }
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

          const prompt = app.prompt || app.proposed_plan?.prompt || '';
          const hasPrompt = Boolean(prompt && prompt.trim().length > 0);
          const isLongPrompt = hasPrompt && prompt.length > 120;
          const isExpandedPrompt = Boolean(this.state.expandedPrompts[app.id]);
          const promptDisplayText = isLongPrompt && !isExpandedPrompt ? prompt.substring(0, 120) + '...' : prompt;

          const promptBannerHtml = hasPrompt ? `
            <div class="hitl-prompt-banner">
              <div class="hitl-prompt-label">💬 Instrucción / Prompt:</div>
              <div class="hitl-prompt-text" id="hitl-prompt-${app.id}">${this.escapeHtml(promptDisplayText)}</div>
              ${isLongPrompt ? `
                <button type="button" class="btn-toggle-prompt" id="hitl-prompt-btn-${app.id}" onclick="window.ConsoleApp.togglePrompt(${app.id})">
                  ${isExpandedPrompt ? 'Ver menos ▲' : 'Ver más ▼'}
                </button>
              ` : ''}
            </div>
          ` : '';

          const cardTitle = hasPrompt
            ? (prompt.length > 60 ? prompt.substring(0, 57) + '...' : prompt)
            : (app.action_summary || 'Plan de Alto Riesgo');

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
                  <div class="hitl-title" title="${this.escapeHtml(prompt || app.action_summary || '')}">${this.escapeHtml(cardTitle)}</div>
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

              <!-- Prompt Banner -->
              ${promptBannerHtml}

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
                <button type="button" class="btn btn-secondary" style="padding: 10px 14px;" title="Inspeccionar Ejecución" onclick="window.ConsoleApp.openInspectPlanModal(${app.id})">
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

    switchInspectTab(tab) {
      const stepsTabBtn = document.getElementById('tab-inspect-steps');
      const jsonTabBtn = document.getElementById('tab-inspect-json');
      const stepsContainer = document.getElementById('inspect-steps-container');
      const jsonContainer = document.getElementById('inspect-json-container');

      if (tab === 'steps') {
        stepsTabBtn?.classList.add('active');
        jsonTabBtn?.classList.remove('active');
        if (stepsContainer) stepsContainer.style.display = 'block';
        if (jsonContainer) jsonContainer.style.display = 'none';
      } else {
        jsonTabBtn?.classList.add('active');
        stepsTabBtn?.classList.remove('active');
        if (stepsContainer) stepsContainer.style.display = 'none';
        if (jsonContainer) jsonContainer.style.display = 'block';
      }
    },

    async openInspectPlanModal(id) {
      const app = this.state.pendingApprovals.find((a) => a.id === id);
      if (!app) return;

      this.state.currentApprovalId = id;
      this.setTextContent('inspect-modal-thread-id', app.thread_id || '-');
      this.setTextContent('inspect-modal-agent-name', app.agent_name || app.agent_id || '-');

      const riskBadge = document.getElementById('inspect-modal-risk-badge');
      if (riskBadge) {
        riskBadge.textContent = app.risk_level || 'HIGH';
        riskBadge.className = `badge ${app.risk_level === 'CRITICAL' ? 'badge-critical' : 'badge-high'}`;
      }

      const promptBox = document.getElementById('inspect-modal-prompt-box');
      if (promptBox) {
        promptBox.textContent = app.prompt || app.proposed_plan?.prompt || 'Sin prompt registrado para esta ejecución.';
      }

      // Raw Technical JSON
      const contentEl = document.getElementById('inspect-plan-content');
      if (contentEl) {
        contentEl.textContent = JSON.stringify(app.proposed_plan || app, null, 2);
      }

      // Switch to steps tab by default
      this.switchInspectTab('steps');

      // Determine steps
      let steps = app.cognitive_steps || app.proposed_plan?.cognitive_steps || app.proposed_plan?.steps || [];
      if (!Array.isArray(steps) || steps.length === 0) {
        const fallback = this.extractPlanSteps(app);
        if (fallback && fallback.length > 0) {
          steps = fallback;
        }
      }

      const timelineEl = document.getElementById('inspect-steps-timeline');
      if (timelineEl) {
        this.state.inspectCurrentSteps = steps;
        this.renderStepsTimeline(steps, timelineEl, 'inspect');

        // If no intermediate steps, try querying agent directly in background
        if (steps.length <= 1 && app.agent_id && app.thread_id) {
          try {
            const res = await fetch(`/console/executions/${app.agent_id}/${app.thread_id}`, {
              headers: this.getAuthHeaders(),
            });
            if (res.ok) {
              const liveData = await res.json();
              const fetchedSteps = liveData.cognitive_steps || liveData.steps || liveData.data?.cognitive_steps || [];
              if (Array.isArray(fetchedSteps) && fetchedSteps.length > 0) {
                this.state.inspectCurrentSteps = fetchedSteps;
                app.cognitive_steps = fetchedSteps;
                this.renderStepsTimeline(fetchedSteps, timelineEl, 'inspect');
              }
              if (liveData.prompt && !app.prompt) {
                app.prompt = liveData.prompt;
                if (promptBox) promptBox.textContent = liveData.prompt;
              }
            }
          } catch (e) {
            console.warn('[HITL] Async telemetry fetch skipped:', e);
          }
        }
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
        const res = await fetch(`/console/approvals/${id}/approve`, {
          method: 'POST',
          headers: { ...this.getAuthHeaders(), 'Content-Type': 'application/json' },
          body: JSON.stringify({ justification }),
        });

        const data = await res.json();
        if (!res.ok) throw new Error(data.message || data.error || 'Error al aprobar');

        const successMsg = data.reconciled || data.data?.reconciled
          ? '✓ Solicitud HITL reconciliada y aprobada en el Control Plane'
          : '✓ Solicitud HITL aprobada y ejecución reanudada en el agente';
        this.showToast(successMsg, 'success');
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
        const res = await fetch(`/console/approvals/${id}/reject`, {
          method: 'POST',
          headers: { ...this.getAuthHeaders(), 'Content-Type': 'application/json' },
          body: JSON.stringify({ justification }),
        });

        const data = await res.json();
        if (!res.ok) throw new Error(data.message || data.error || 'Error al rechazar');

        const successMsg = data.reconciled || data.data?.reconciled
          ? '✕ Solicitud HITL reconciliada y cerrada en el Control Plane'
          : '✕ Solicitud HITL rechazada y ejecución cancelada en el agente';
        this.showToast(successMsg, 'success');
        this.closeAllModals();
        this.fetchApprovals();
      } catch (err) {
        this.showToast(`Error: ${err.message}`, 'error');
      }
    },


    async submitTriggerExecution() {
      // Guardarraíl anti-doble clic: Si ya se está procesando una ejecución, abortar
      if (this.state.isTriggeringExecution) {
        return;
      }

      const agentId = document.getElementById('trigger-agent-id')?.value;
      const prompt = document.getElementById('trigger-prompt')?.value;

      if (!agentId || !prompt || prompt.trim().length === 0) {
        this.showToast('Selecciona un agente y redacta un prompt', 'error');
        return;
      }

      const isAsync = document.getElementById('trigger-async')?.checked || false;

      const btnSubmit = document.getElementById('btn-trigger-execution') || document.querySelector('#form-trigger-execution button[type="submit"]');
      const originalBtnHtml = btnSubmit ? btnSubmit.innerHTML : '▶ Disparar Ejecución en Agente';

      // Bloquear botón e indicar estado activo visual inmediatamente
      this.state.isTriggeringExecution = true;
      if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = isAsync
          ? '<span class="spinner-icon"></span> Encolando en agente...'
          : '<span class="spinner-icon"></span> Ejecutando en agente...';
      }

      const resultBox = document.getElementById('execution-result-box');
      if (resultBox) {
        resultBox.textContent = isAsync
          ? 'Enviando directiva AgentInput en modo asíncrono (sync=false) y encolando en background...'
          : 'Enviando directiva AgentInput hacia el Control Plane y esperando resolución cognitiva...';
      }

      try {
        const payload = { agent_id: agentId, prompt: prompt.trim() };
        if (isAsync) {
          payload.async = true;
        }

        const res = await fetch('/console/executions/trigger', {
          method: 'POST',
          headers: { ...this.getAuthHeaders(), 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });

        const data = await res.json();
        if (!res.ok) throw new Error(data.message || data.error || 'Error al disparar ejecución');

        if (isAsync || data.status === 'in_progress') {
          this.showToast('Ejecución encolada en modo asíncrono (monitoreando vía polling)', 'info');
        } else {
          this.showToast('Ejecución enviada exitosamente al agente', 'success');
        }

        if (resultBox) {
          resultBox.textContent = JSON.stringify(data, null, 2);
        }

        // Render detail immediately so cognitive hops show up right away
        data.prompt = data.prompt || prompt.trim();
        this.renderExecutionDetail(data);

        const threadId = data.thread_id || data.execution_id;
        if (threadId) {
          this.addRecentExecution(agentId, threadId);
          this.loadExecutionTrace(agentId, threadId);
        }

        this.fetchApprovals();
        this.fetchExecutions();
      } catch (err) {
        this.showToast(`Error: ${err.message}`, 'error');
        if (resultBox) {
          resultBox.textContent = `Error: ${err.message}`;
        }
      } finally {
        // Desbloquear botón y restaurar estado original
        this.state.isTriggeringExecution = false;
        if (btnSubmit) {
          btnSubmit.disabled = false;
          btnSubmit.innerHTML = originalBtnHtml;
        }
      }
    },

    addRecentExecution(agentId, threadId) {
      if (!threadId) return;
      this.state.recentExecutions = this.state.recentExecutions.filter((item) => item.thread_id !== threadId);
      this.state.recentExecutions.unshift({
        agent_id: agentId,
        thread_id: threadId,
        timestamp: new Date().toLocaleTimeString(),
      });
      if (this.state.recentExecutions.length > 5) {
        this.state.recentExecutions.pop();
      }
      this.renderRecentExecutions();
    },

    renderRecentExecutions() {
      const listEl = document.getElementById('recent-threads-list');
      if (!listEl) return;

      if (this.state.recentExecutions.length === 0) {
        listEl.innerHTML = '<span style="font-size: 11px; color: var(--text-muted);">Sin ejecuciones en sesión</span>';
        return;
      }

      listEl.innerHTML = this.state.recentExecutions
        .map(
          (item) => `
          <button type="button" class="recent-thread-chip" onclick="window.ConsoleApp.loadExecutionTrace('${this.escapeHtml(item.agent_id)}', '${this.escapeHtml(item.thread_id)}')">
            ⚡ ${this.escapeHtml(item.thread_id)} (${item.timestamp})
          </button>
        `
        )
        .join('');
    },

    async loadExecutionTrace(agentId, threadId) {
      if (this.state.executionPollingInterval) {
        clearInterval(this.state.executionPollingInterval);
        this.state.executionPollingInterval = null;
      }

      const queryAgent = document.getElementById('execution-query-agent');
      if (queryAgent && agentId) queryAgent.value = agentId;
      const queryThread = document.getElementById('execution-query-thread');
      if (queryThread && threadId) queryThread.value = threadId;

      try {
        let res = await fetch(`/console/executions/${encodeURIComponent(agentId)}/${encodeURIComponent(threadId)}`, {
          headers: this.getAuthHeaders(),
        });

        if (!res.ok) {
          res = await fetch(`/api/v1/executions/${encodeURIComponent(agentId)}/${encodeURIComponent(threadId)}`, {
            headers: this.getAuthHeaders(),
          });
        }

        if (!res.ok) {
          const errData = await res.json().catch(() => ({}));
          throw new Error(errData.message || `HTTP ${res.status}`);
        }

        const data = await res.json();
        this.state.currentExecution = data;
        this.renderExecutionDetail(data);

        if (data.status === 'in_progress') {
          this.state.executionPollingInterval = setInterval(() => {
            this.pollExecution(agentId, threadId);
          }, 3000);
        }
      } catch (err) {
        this.showToast(`Error al consultar ejecución: ${err.message}`, 'error');
      }
    },

    async pollExecution(agentId, threadId) {
      try {
        let res = await fetch(`/console/executions/${encodeURIComponent(agentId)}/${encodeURIComponent(threadId)}`, {
          headers: this.getAuthHeaders(),
        });
        if (!res.ok) {
          res = await fetch(`/api/v1/executions/${encodeURIComponent(agentId)}/${encodeURIComponent(threadId)}`, {
            headers: this.getAuthHeaders(),
          });
        }
        if (!res.ok) return;

        const data = await res.json();
        this.state.currentExecution = data;
        this.renderExecutionDetail(data);

        if (data.status !== 'in_progress') {
          clearInterval(this.state.executionPollingInterval);
          this.state.executionPollingInterval = null;
          this.showToast(`Ejecución finalizada: ${data.status}`, 'info');
        }
      } catch {
        // Silent polling error
      }
    },

    renderExecutionDetail(data) {
      if (!data) return;

      const statusBadge = document.getElementById('exec-status-badge');
      if (statusBadge) {
        const isProgress = data.status === 'in_progress';
        const isCompleted = data.status === 'completed' || data.status === 'success';
        const isWaiting = data.status === 'waiting_approval';
        const isFailed = data.status === 'failed' || data.status === 'error';

        statusBadge.className = 'badge ' + (
          isCompleted ? 'badge-active' :
          isProgress ? 'badge-active' :
          isWaiting ? 'badge-pending' :
          isFailed ? 'badge-critical' : 'badge-offline'
        );
        statusBadge.textContent = (data.status || 'UNKNOWN').toUpperCase();
      }

      this.setTextContent('exec-latency', data.latency_ms !== undefined ? `${data.latency_ms}ms` : '<10ms');
      this.setTextContent('exec-agent-name', data.agent_name || data.agent_id || '-');
      this.setTextContent('exec-thread-id', data.thread_id || '-');

      const promptBox = document.getElementById('execution-prompt-box');
      if (promptBox) {
        promptBox.textContent = data.prompt || data.data?.prompt || 'Sin prompt registrado para esta ejecución.';
      }

      // Render Rejection Alert Banner if execution was rejected
      const rejectionBanner = document.getElementById('execution-rejection-banner');
      if (rejectionBanner) {
        const hitl = data.hitl_governance;
        const isHitlRejected = hitl && String(hitl.status).toUpperCase() === 'REJECTED';
        const isCancelled = String(data.status).toLowerCase() === 'cancelled';
        const justification = hitl?.justification || data.output || data.response || (isCancelled ? 'Ejecución cancelada por rechazo de operador.' : null);

        if ((isHitlRejected || isCancelled) && justification) {
          const resolverInfo = hitl?.resolver?.name
            ? `${this.escapeHtml(hitl.resolver.name)} (${this.escapeHtml(hitl.resolver.email || '')})`
            : (hitl?.resolver?.email ? this.escapeHtml(hitl.resolver.email) : 'Operador HITL');
          const resolvedDate = hitl?.resolved_at ? new Date(hitl.resolved_at).toLocaleString() : '';

          rejectionBanner.innerHTML = `
            <div class="trace-rejection-banner">
              <div class="trace-rejection-header">
                <div class="trace-rejection-title">
                  <span>🛑 Decisión HITL: Operación Denegada / Cancelada</span>
                </div>
                <span class="badge badge-critical">RECHAZADO</span>
              </div>
              <div class="trace-rejection-text">
                <strong>Motivo del Rechazo:</strong>
                <div style="margin-top: 4px;">${this.escapeHtml(justification)}</div>
              </div>
              <div class="trace-rejection-meta">
                <span>👤 Resuelto por: <strong>${resolverInfo}</strong></span>
                ${resolvedDate ? `<span>🕒 Fecha: <strong>${resolvedDate}</strong></span>` : ''}
                ${hitl?.approval_id ? `<span>🆔 Solicitud: <strong>#${hitl.approval_id}</strong></span>` : ''}
              </div>
            </div>
          `;
          rejectionBanner.style.display = 'block';
        } else {
          rejectionBanner.innerHTML = '';
          rejectionBanner.style.display = 'none';
        }
      }

      // Render Steps Timeline
      const timeline = document.getElementById('execution-steps-timeline');
      if (timeline) {
        const rawSteps = data.cognitive_steps || data.steps || data.data?.cognitive_steps || data.data?.steps || data.data?.data?.cognitive_steps || [];
        let steps = [];

        if (Array.isArray(rawSteps) && rawSteps.length > 0) {
          steps = rawSteps;
        } else if (data.plan && Array.isArray(data.plan.steps)) {
          steps = data.plan.steps;
        } else if (data.plan && Array.isArray(data.plan.actions)) {
          steps = data.plan.actions;
        }

        this.state.currentSteps = steps;
        this.renderStepsTimeline(steps, timeline, 'trace');
      }

      // Render Output Box
      const outputBox = document.getElementById('execution-output-box');
      if (outputBox) {
        const out = data.output || data.response || data.final_output || data.data?.output || data.data?.response;
        if (out !== undefined && out !== null) {
          outputBox.textContent = typeof out === 'string' ? out : JSON.stringify(out, null, 2);
        } else {
          outputBox.textContent = JSON.stringify(data, null, 2);
        }
      }
    },

    renderStepsTimeline(steps, containerEl, prefix = 'trace') {
      if (!containerEl) return;
      if (!Array.isArray(steps) || steps.length === 0) {
        containerEl.innerHTML = `
          <div style="font-size: 13px; color: var(--text-muted); padding: 16px; text-align: center;">
            ✓ Grafo cognitivo completado en un solo ciclo o sin saltos intermedios registrados.
          </div>
        `;
        return;
      }

      const controlsHtml = `
        <div class="trace-controls-bar">
          <span style="font-size: 11px; color: var(--text-muted); font-weight: 600;">
            ⚡ ${steps.length} SALTOS COGNITIVOS (PULSA PARA DETALLES):
          </span>
          <div style="display: flex; gap: 8px;">
            <button type="button" class="trace-controls-btn" onclick="window.ConsoleApp.expandAllSteps('${prefix}')">Expandir todos</button>
            <span style="color: var(--text-muted); font-size: 11px;">|</span>
            <button type="button" class="trace-controls-btn" onclick="window.ConsoleApp.collapseAllSteps('${prefix}')">Colapsar todos</button>
          </div>
        </div>
      `;

      const stepsHtml = steps
        .map((s, idx) => {
          const node = (s.node || '').toLowerCase();
          const isError = s.status === 'error' || s.status === 'failed' || s.status === 'denied';
          const isInProgress = s.status === 'in_progress';

          let badgeClass = '';
          let badgeIcon = '✓';

          if (isError) {
            badgeClass = 'error';
            badgeIcon = '✕';
          } else if (s.status === 'waiting_approval') {
            badgeClass = 'waiting-approval';
            badgeIcon = '⏸️';
          } else if (isInProgress) {
            badgeClass = 'in-progress';
            badgeIcon = '⏳';
          } else if (node === 'observe') {
            badgeClass = 'node-observe';
            badgeIcon = '👁️';
          } else if (node === 'reason') {
            badgeClass = 'node-reason';
            badgeIcon = '🧠';
          } else if (node === 'plan') {
            badgeClass = 'node-plan';
            badgeIcon = '📋';
          } else if (node === 'risk_assess' || node === 'security_eval') {
            badgeClass = 'node-security_eval';
            badgeIcon = '🛡️';
          } else if (node === 'human_approval') {
            badgeClass = 'node-approval';
            badgeIcon = '🛑';
          } else if (node === 'execute') {
            badgeClass = 'node-execute';
            badgeIcon = '⚡';
          } else if (node === 'verify') {
            badgeClass = 'node-verify';
            badgeIcon = '🔬';
          } else if (node === 'rollback') {
            badgeClass = 'node-rollback';
            badgeIcon = '⏪';
          } else if (node === 'conclude') {
            badgeClass = 'node-conclude';
            badgeIcon = '📝';
          } else {
            badgeIcon = (idx + 1).toString();
          }

          const title = s.title || s.name || (s.node ? `Nodo: ${s.node}` : `Paso ${idx + 1}`);
          const desc = s.description || '';
          const duration = s.duration_ms !== undefined && s.duration_ms !== null ? `${s.duration_ms}ms` : '';
          const toolName = s.tool || s.tool_name || (s.details && s.details.tool ? s.details.tool : (s.details && s.details.tool_name ? s.details.tool_name : (s.details && s.details.selected_tool ? s.details.selected_tool : null)));

          let pillsHtml = '';
          if (s.node) {
            pillsHtml += `<span class="trace-pill trace-pill-node">Nodo: ${this.escapeHtml(s.node)}</span>`;
          }
          if (toolName) {
            pillsHtml += `<span class="trace-pill tool-tag">🔧 ${this.escapeHtml(toolName)}</span>`;
          }
          if (s.details && s.details.risk_level) {
            const rLevel = String(s.details.risk_level).toLowerCase();
            const rClass = rLevel === 'low' ? 'trace-pill-risk-low' : 'trace-pill-risk-high';
            pillsHtml += `<span class="trace-pill ${rClass}">Riesgo: ${this.escapeHtml(String(s.details.risk_level).toUpperCase())}</span>`;
          }
          if (s.details && s.details.decision) {
            pillsHtml += `<span class="trace-pill trace-pill-allow">Decisión: ${this.escapeHtml(String(s.details.decision).toUpperCase())}</span>`;
          }
          if (duration) {
            pillsHtml += `<span class="trace-pill" style="color: var(--text-muted); font-family: var(--font-mono);">⏱️ ${duration}</span>`;
          }

          const cmdHtml = s.command ? `<div class="step-command-box"><code>$ ${this.escapeHtml(s.command)}</code></div>` : '';
          const descHtml = desc ? `<div class="trace-step-desc" style="margin-top: 6px;">${this.escapeHtml(desc)}</div>` : '';

          let detailsSections = '';
          if (s.details && typeof s.details === 'object') {
            const det = s.details;

            if (det.tool_args && Object.keys(det.tool_args).length > 0) {
              detailsSections += `
                <div class="step-details-section">
                  <div class="step-details-section-title">Parámetros de Invocación (tool_args)</div>
                  <div style="background: rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.06); padding: 8px 12px; border-radius: 4px; font-family: var(--font-mono); font-size: 12px; color: var(--accent-cyan);">
                    ${this.escapeHtml(JSON.stringify(det.tool_args, null, 2))}
                  </div>
                </div>
              `;
            }

            const outputVal = det.output !== undefined && det.output !== null ? det.output : (s.output !== undefined && s.output !== null ? s.output : null);
            if (outputVal !== null) {
              const outFormatted = typeof outputVal === 'string' ? outputVal : JSON.stringify(outputVal, null, 2);
              detailsSections += `
                <div class="step-details-section">
                  <div class="step-details-section-title">Resultado de la Herramienta (Output)</div>
                  <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); padding: 8px 12px; border-radius: 4px; font-size: 12px; color: #34d399; font-family: var(--font-mono); white-space: pre-wrap;">${this.escapeHtml(outFormatted)}</div>
                </div>
              `;
            }

            if (det.reasoning || det.goal) {
              detailsSections += `
                <div class="step-details-section">
                  <div class="step-details-section-title">Objetivo e Intención Cognitiva</div>
                  <div style="font-size: 12px; color: var(--text-secondary); background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.2); padding: 8px 12px; border-radius: 4px;">
                    ${this.escapeHtml(det.reasoning || det.goal)}
                  </div>
                </div>
              `;
            }

            if (det.steps && Array.isArray(det.steps) && det.steps.length > 0) {
              detailsSections += `
                <div class="step-details-section">
                  <div class="step-details-section-title">Desglose de Pasos Planificados (${det.steps.length})</div>
                  <div style="background: rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.06); padding: 8px 12px; border-radius: 4px; font-family: var(--font-mono); font-size: 12px; color: var(--accent-cyan); white-space: pre-wrap;">
${this.escapeHtml(det.steps.map((st, i) => `${i + 1}. [${st.tool_name || st.name || 'tool'}] - ${st.description || 'Paso ' + (i + 1)} (Riesgo: ${String(st.risk_level || 'LOW').toUpperCase()})`).join('\n'))}
                  </div>
                </div>
              `;
            }

            if (det.snapshot_summary && Object.keys(det.snapshot_summary).length > 0) {
              detailsSections += `
                <div class="step-details-section">
                  <div class="step-details-section-title">Telemetría y Recursos Detectados</div>
                  <div style="background: rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.06); padding: 8px 12px; border-radius: 4px; font-family: var(--font-mono); font-size: 12px; color: var(--accent-cyan); white-space: pre-wrap;">${this.escapeHtml(JSON.stringify(det.snapshot_summary, null, 2))}</div>
                </div>
              `;
            }

            if (det.risk_level || det.decision || det.bypass_approval !== undefined) {
              const decisionStr = String(det.decision || 'ALLOW').toUpperCase();
              const isAllow = decisionStr === 'ALLOW';
              const decClass = isAllow ? 'trace-pill-allow' : 'trace-pill-risk-high';
              detailsSections += `
                <div class="step-details-section">
                  <div class="step-details-section-title">Evaluación de Gobernanza (SecurityGuard)</div>
                  <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <span class="trace-pill ${det.risk_level === 'low' ? 'trace-pill-risk-low' : 'trace-pill-risk-high'}">Riesgo: ${this.escapeHtml(String(det.risk_level || 'N/A').toUpperCase())}</span>
                    <span class="trace-pill ${decClass}">Decisión: ${this.escapeHtml(decisionStr)}</span>
                    ${det.bypass_approval !== undefined ? `<span class="trace-pill trace-pill-node">Bypass: ${det.bypass_approval ? 'SÍ' : 'NO'}</span>` : ''}
                    ${det.reversible !== undefined ? `<span class="trace-pill trace-pill-node">Reversible: ${det.reversible ? 'SÍ' : 'NO'}</span>` : ''}
                  </div>
                </div>
              `;
            }

            if (det.notes || det.justification || det.approver) {
              const noteText = det.notes || det.justification || '';
              const isReject = isError || String(det.status || '').toLowerCase() === 'rejected';
              detailsSections += `
                <div class="step-details-section">
                  <div class="step-details-section-title">Resolución de Intervención Humana (HITL)</div>
                  <div style="background: ${isReject ? 'rgba(244, 63, 94, 0.1)' : 'rgba(16, 185, 129, 0.1)'}; border: 1px solid ${isReject ? 'rgba(244, 63, 94, 0.3)' : 'rgba(16, 185, 129, 0.3)'}; padding: 10px 14px; border-radius: 4px;">
                    ${det.approver ? `<div style="font-size: 11px; color: var(--text-muted); margin-bottom: 4px;">Operador / Resuelve: <strong style="color: var(--text-primary);">${this.escapeHtml(det.approver)}</strong></div>` : ''}
                    ${noteText ? `<div style="font-size: 13px; color: ${isReject ? '#fb7185' : '#34d399'}; font-weight: 500;"><strong>Motivo / Justificación:</strong> ${this.escapeHtml(noteText)}</div>` : ''}
                  </div>
                </div>
              `;
            }
          }

          return `
            <div id="${prefix}-step-node-${idx}" class="trace-step-node ${prefix}-step-node is-clickable" onclick="window.ConsoleApp.toggleStep(${idx}, '${prefix}')" tabindex="0" role="button" aria-expanded="false" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.ConsoleApp.toggleStep(${idx}, '${prefix}');}">
              <div class="trace-node-badge ${badgeClass}">${badgeIcon}</div>
              <div class="trace-node-header" style="flex-direction: column; align-items: flex-start; gap: 4px;">
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="trace-node-title" style="font-weight: 600; font-size: 14px;">${this.escapeHtml(title)}</span>
                    ${s.node ? `<span class="trace-pill trace-pill-node">${this.escapeHtml(s.node)}</span>` : ''}
                  </div>
                  <div style="display: flex; align-items: center; gap: 8px;">
                    ${duration ? `<span class="trace-pill" style="color: var(--text-muted); font-family: var(--font-mono);">⏱️ ${duration}</span>` : ''}
                    <span class="trace-chevron" title="Click para expandir/colapsar detalles">▼</span>
                  </div>
                </div>
                ${pillsHtml ? `<div class="trace-step-pills">${pillsHtml}</div>` : ''}
              </div>
              ${descHtml}
              ${cmdHtml}

              <!-- Expandable Node Details Panel -->
              <div id="${prefix}-step-details-${idx}" class="trace-step-details-panel" onclick="event.stopPropagation()">
                <div class="step-details-grid">
                  <div class="step-detail-card">
                    <span class="step-detail-card-label">Nodo LangGraph</span>
                    <span class="step-detail-card-val" style="color: var(--accent-cyan);">${this.escapeHtml(s.node || '-')}</span>
                  </div>
                  <div class="step-detail-card">
                    <span class="step-detail-card-label">Acción</span>
                    <span class="step-detail-card-val">${this.escapeHtml(s.action || '-')}</span>
                  </div>
                  <div class="step-detail-card">
                    <span class="step-detail-card-label">Estado</span>
                    <span class="step-detail-card-val" style="color: ${isError ? 'var(--accent-rose)' : 'var(--accent-emerald)'};">${this.escapeHtml(String(s.status || 'completed').toUpperCase())}</span>
                  </div>
                  <div class="step-detail-card">
                    <span class="step-detail-card-label">Duración</span>
                    <span class="step-detail-card-val">${duration || '< 1ms'}</span>
                  </div>
                  ${s.timestamp ? `
                  <div class="step-detail-card">
                    <span class="step-detail-card-label">Timestamp</span>
                    <span class="step-detail-card-val" style="font-size: 11px;">${this.escapeHtml(s.timestamp)}</span>
                  </div>
                  ` : ''}
                  ${s.step_id ? `
                  <div class="step-detail-card" style="grid-column: span 2;">
                    <span class="step-detail-card-label">Step ID</span>
                    <span class="step-detail-card-val" style="font-size: 10px; color: var(--text-muted);">${this.escapeHtml(s.step_id)}</span>
                  </div>
                  ` : ''}
                </div>

                ${detailsSections}

                <!-- Full Node JSON Payload -->
                <div class="step-details-section">
                  <div class="step-details-section-title">
                    <span>Payload Completo del Nodo (JSON)</span>
                    <button type="button" class="btn-step-copy" onclick="window.ConsoleApp.copyStepJson(${idx}, '${prefix}')">📋 Copiar JSON</button>
                  </div>
                  <pre class="step-json-box">${this.escapeHtml(JSON.stringify(s, null, 2))}</pre>
                </div>
              </div>
            </div>
          `;
        })
        .join('');

      containerEl.innerHTML = controlsHtml + stepsHtml;
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

    async fetchExecutions() {
      try {
        const res = await fetch('/console/executions?limit=30', {
          headers: this.getAuthHeaders(),
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        const list = data.executions || [];
        this.state.executionsList = list;
        this.renderExecutionsTable(list);
      } catch (err) {
        console.warn('Error al cargar historial de ejecuciones:', err);
        const tbody = document.getElementById('executions-history-body');
        if (tbody) {
          tbody.innerHTML = `
            <tr>
              <td colspan="5" style="text-align: center; color: var(--accent-red); padding: 18px;">
                Error al sincronizar historial: ${this.escapeHtml(err.message)}
              </td>
            </tr>
          `;
        }
      }
    },

    renderExecutionsTable(executions) {
      const tbody = document.getElementById('executions-history-body');
      if (!tbody) return;

      if (!executions || executions.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 28px;">
              No hay ejecuciones registradas en el sistema.
            </td>
          </tr>
        `;
        return;
      }

      tbody.innerHTML = executions
        .map((exec) => {
          const status = (exec.status || 'UNKNOWN').toUpperCase();
          let statusBadgeClass = 'badge-pending';
          if (status === 'COMPLETED' || status === 'SUCCESS') statusBadgeClass = 'badge-success';
          else if (status === 'WAITING_APPROVAL') statusBadgeClass = 'badge-high';
          else if (status === 'FAILED' || status === 'REJECTED') statusBadgeClass = 'badge-danger';
          else if (status === 'PROCESSING' || status === 'RUNNING') statusBadgeClass = 'badge-info';

          const promptFull = exec.prompt || '';
          const promptTruncated = exec.prompt_truncated || '(Sin prompt)';
          const dateStr = exec.created_at_formatted || '-';
          const agentId = exec.agent_id || '-';
          const agentName = exec.agent_name || agentId;
          const threadId = exec.thread_id || exec.task_id || '';

          return `
            <tr style="border-bottom: 1px solid var(--border-color);">
              <td style="padding: 12px 16px; vertical-align: middle;">
                <div style="font-weight: 500; color: var(--text-primary); font-size: 13px;" title="${this.escapeHtml(promptFull)}">
                  💬 ${this.escapeHtml(promptTruncated)}
                </div>
                ${threadId ? `<div style="font-family: var(--font-mono); font-size: 11px; color: var(--text-muted); margin-top: 2px;">ID: ${this.escapeHtml(threadId)}</div>` : ''}
              </td>
              <td style="padding: 12px 16px; vertical-align: middle; font-size: 12px; color: var(--text-secondary); white-space: nowrap;">
                📅 ${this.escapeHtml(dateStr)}
              </td>
              <td style="padding: 12px 16px; vertical-align: middle;">
                <span class="badge" style="background: rgba(255,255,255,0.06); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 11px;" title="ID: ${this.escapeHtml(agentId)}">
                  🤖 ${this.escapeHtml(agentName)}
                </span>
              </td>
              <td style="padding: 12px 16px; vertical-align: middle;">
                <span class="badge ${statusBadgeClass}">${this.escapeHtml(status)}</span>
              </td>
              <td style="padding: 12px 16px; vertical-align: middle; text-align: right;">
                <button type="button" class="btn btn-secondary" style="font-size: 11px; padding: 5px 10px;" onclick="window.ConsoleApp.selectAndLoadExecution('${this.escapeHtml(agentId)}', '${this.escapeHtml(threadId)}')">
                  🔍 Ver Traza
                </button>
              </td>
            </tr>
          `;
        })
        .join('');
    },

    selectAndLoadExecution(agentId, threadId) {
      if (!threadId) {
        this.showToast('Esta ejecución no dispone de Thread ID registrado', 'error');
        return;
      }
      const agentSelect = document.getElementById('execution-query-agent');
      if (agentSelect && agentId) agentSelect.value = agentId;

      const threadInput = document.getElementById('execution-query-thread');
      if (threadInput && threadId) threadInput.value = threadId;

      this.loadExecutionTrace(agentId, threadId);

      const panel = document.getElementById('execution-detail-panel');
      if (panel) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
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

    toggleStep(idx, prefix = 'trace') {
      const el = document.getElementById(`${prefix}-step-node-${idx}`);
      if (!el) return;
      const isExpanded = el.classList.toggle('is-expanded');
      el.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
    },

    expandAllSteps(prefix = 'trace') {
      const nodes = document.querySelectorAll(`.${prefix}-step-node.is-clickable`);
      nodes.forEach((n) => {
        n.classList.add('is-expanded');
        n.setAttribute('aria-expanded', 'true');
      });
    },

    collapseAllSteps(prefix = 'trace') {
      const nodes = document.querySelectorAll(`.${prefix}-step-node.is-clickable`);
      nodes.forEach((n) => {
        n.classList.remove('is-expanded');
        n.setAttribute('aria-expanded', 'false');
      });
    },

    copyStepJson(idx, prefix = 'trace') {
      const steps = prefix === 'inspect' ? this.state.inspectCurrentSteps : this.state.currentSteps;
      if (!steps || !steps[idx]) return;
      const step = steps[idx];
      const jsonStr = JSON.stringify(step, null, 2);
      this.copyToClipboard(jsonStr, `JSON del nodo ${step.node || idx + 1} copiado`);
    },

    startPolling() {
      if (this.state.pollingInterval) clearInterval(this.state.pollingInterval);
      this.state.pollingInterval = setInterval(() => {
        this.loadTab(this.state.activeTab);
      }, 15000);
    },

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
