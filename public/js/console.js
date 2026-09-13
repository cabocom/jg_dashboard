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
      executionFilterAgentId: 'ALL',
      executionFilterStatus: 'ALL',
      executionSearchQuery: '',
      executionSortBy: 'created_at',
      executionSortOrder: 'desc',
      executionFilterDateFrom: '',
      executionFilterDateTo: '',
      isTriggeringExecution: false,
    },

    init() {
      const urlParams = new URLSearchParams(window.location.search);
      const requestedTab = urlParams.get('tab') || window.location.hash.replace('#', '');
      if (['agents', 'approvals', 'executions', 'launcher'].includes(requestedTab)) {
        this.state.activeTab = requestedTab;
      }

      this.initTheme();
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
              this.applyTheme(theme, true);
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

      // User Profile Modal Open
      const btnUserProfile = document.getElementById('btn-user-profile');
      if (btnUserProfile) {
        btnUserProfile.addEventListener('click', () => {
          this.openUserProfileModal();
        });
      }

      // User Profile Form Submit
      const formProfile = document.getElementById('form-user-profile');
      if (formProfile) {
        formProfile.addEventListener('submit', (e) => {
          e.preventDefault();
          this.submitUserProfile();
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

      // Executions History Filter: Agent ID
      const filterExecAgent = document.getElementById('executions-filter-agent-id');
      if (filterExecAgent) {
        filterExecAgent.addEventListener('change', (e) => {
          this.state.executionFilterAgentId = e.target.value;
          this.renderFilteredExecutions();
        });
      }

      // Executions History Filter: Status
      const filterExecStatus = document.getElementById('executions-filter-status');
      if (filterExecStatus) {
        filterExecStatus.addEventListener('change', (e) => {
          this.state.executionFilterStatus = e.target.value.toUpperCase();
          this.renderFilteredExecutions();
        });
      }

      // Executions History Filter: Text Search
      const searchExecInput = document.getElementById('executions-search');
      if (searchExecInput) {
        searchExecInput.addEventListener('input', (e) => {
          this.state.executionSearchQuery = e.target.value.toLowerCase().trim();
          this.renderFilteredExecutions();
        });
      }

      // Executions History Filter: Date Range (Fecha y Hora)
      const filterDateFrom = document.getElementById('executions-filter-date-from');
      const filterDateTo = document.getElementById('executions-filter-date-to');
      const btnClearDates = document.getElementById('btn-clear-date-filters');

      const handleDateChange = () => {
        this.state.executionFilterDateFrom = filterDateFrom?.value || '';
        this.state.executionFilterDateTo = filterDateTo?.value || '';
        if (btnClearDates) {
          btnClearDates.style.display = (this.state.executionFilterDateFrom || this.state.executionFilterDateTo) ? 'inline-block' : 'none';
        }
        this.renderFilteredExecutions();
      };

      if (filterDateFrom) filterDateFrom.addEventListener('change', handleDateChange);
      if (filterDateTo) filterDateTo.addEventListener('change', handleDateChange);

      if (btnClearDates) {
        btnClearDates.addEventListener('click', () => {
          if (filterDateFrom) filterDateFrom.value = '';
          if (filterDateTo) filterDateTo.value = '';
          this.state.executionFilterDateFrom = '';
          this.state.executionFilterDateTo = '';
          btnClearDates.style.display = 'none';
          this.renderFilteredExecutions();
        });
      }

      // Executions Table: Column Sorting
      document.querySelectorAll('.sortable-th[data-sort]').forEach((th) => {
        th.addEventListener('click', () => {
          const col = th.getAttribute('data-sort');
          if (!col) return;
          if (this.state.executionSortBy === col) {
            this.state.executionSortOrder = this.state.executionSortOrder === 'asc' ? 'desc' : 'asc';
          } else {
            this.state.executionSortBy = col;
            this.state.executionSortOrder = col === 'created_at' ? 'desc' : 'asc';
          }
          this.renderFilteredExecutions();
        });
      });

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

      // Copy Modal Trace Output Button
      const btnCopyModalTraceOutput = document.getElementById('btn-copy-modal-trace-output');
      if (btnCopyModalTraceOutput) {
        btnCopyModalTraceOutput.addEventListener('click', () => {
          const text = document.getElementById('modal-trace-output')?.textContent;
          if (text) {
            this.copyToClipboard(text, 'Resultado de ejecución copiado al portapapeles');
          }
        });
      }

      // Copy Modal Trace JSON Button
      const btnCopyModalTraceJson = document.getElementById('btn-copy-modal-trace-json');
      if (btnCopyModalTraceJson) {
        btnCopyModalTraceJson.addEventListener('click', () => {
          const text = document.getElementById('modal-trace-raw-json')?.textContent;
          if (text) {
            this.copyToClipboard(text, 'JSON de la traza copiado al portapapeles');
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
          launcher: 'Pasarela de Disparo y Orquestación',
          executions: 'Historial Centralizado de Ejecuciones',
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
          const execCount = agent.executions_count != null ? agent.executions_count : 0;

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

              <div style="display: flex; gap: 8px; font-size: 11px; color: var(--text-secondary); flex-wrap: wrap; align-items: center;">
                <span>🛠️ ${toolsCount} Herramientas</span>
                <span>•</span>
                <span>🧠 ${enginesCount} Engines</span>
                <span>•</span>
                <span>v${agent.version || '1.0.0'}</span>
                <span>•</span>
                <a href="javascript:void(0)" class="agent-executions-link" title="Ver historial de ejecuciones de este agente" onclick="window.ConsoleApp.viewAgentHistory('${this.escapeHtml(agent.agent_id)}')">
                  ⚡ ${execCount} Ejecuciones
                </a>
              </div>

              <div class="agent-card-footer" style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                <div class="agent-heartbeat-text" style="display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--text-muted); min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                  <span>${isHealthy ? '🟢 En línea' : '⚪ Desconectado'}</span>
                  <span style="font-size: 10px; color: var(--text-muted);">(${agent.last_heartbeat_at ? new Date(agent.last_heartbeat_at).toLocaleTimeString() : 'Sin reporte'})</span>
                </div>
                <div style="display: flex; gap: 6px; align-items: center; margin-left: auto; flex-shrink: 0;">
                  <button type="button" class="btn btn-primary" style="padding: 5px 9px; font-size: 11px; white-space: nowrap;" title="Lanzar ejecución con este agente" onclick="window.ConsoleApp.startNewExecutionForAgent('${this.escapeHtml(agent.agent_id)}')">
                    Lanzar Ejecución
                  </button>
                  <button type="button" class="btn btn-secondary" style="padding: 5px 9px; font-size: 11px; white-space: nowrap;" title="Ver capacidades del agente" onclick="window.ConsoleApp.inspectCapabilities('${this.escapeHtml(agent.agent_id)}')">
                    Capacidades
                  </button>
                </div>
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
        tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">No se encontraron agentes con los filtros actuales.</td></tr>`;
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
          const execCount = agent.executions_count != null ? agent.executions_count : 0;

          return `
            <tr>
              <td>
                <div style="font-weight: 600; color: var(--text-primary);">${this.escapeHtml(agent.name)}</div>
                <div style="font-family: var(--font-mono); font-size: 11px; color: var(--text-muted); cursor: pointer;" onclick="window.ConsoleApp.copyToClipboard('${this.escapeHtml(agent.agent_id)}', 'ID de agente copiado')">
                  📋 ${this.escapeHtml(agent.agent_id)}
                </div>
              </td>
              <td><span class="badge ${badgeClass}">${agent.status}</span></td>
              <td>
                <a href="javascript:void(0)" class="badge badge-info agent-executions-link" style="font-size: 11px; font-weight: 600; text-decoration: none; cursor: pointer;" title="Ver historial de ejecuciones de este agente" onclick="window.ConsoleApp.viewAgentHistory('${this.escapeHtml(agent.agent_id)}')">
                  ⚡ ${execCount}
                </a>
              </td>
              <td>${agent.version || '1.0.0'}</td>
              <td>${agent.last_heartbeat_at ? new Date(agent.last_heartbeat_at).toLocaleTimeString() : 'Sin reporte'}</td>
              <td>
                <span style="font-size: 12px; color: ${isHealthy ? 'var(--accent-emerald)' : 'var(--accent-rose)'}; font-weight: 600;">
                  ${isHealthy ? '● En Línea' : '○ Desconectado'}
                </span>
              </td>
              <td>
                <div style="display: flex; gap: 4px; align-items: center;">
                  <button type="button" class="btn btn-secondary" style="padding: 4px 8px; font-size: 10px;" title="Historial de Ejecuciones" onclick="window.ConsoleApp.viewAgentHistory('${this.escapeHtml(agent.agent_id)}')">
                    Historial de Ejecuciones
                  </button>
                  <button type="button" class="btn btn-primary" style="padding: 4px 8px; font-size: 10px;" title="Lanzar Ejecución" onclick="window.ConsoleApp.startNewExecutionForAgent('${this.escapeHtml(agent.agent_id)}')">
                    Lanzar Ejecución
                  </button>
                  <button type="button" class="btn btn-secondary" style="padding: 4px 8px; font-size: 10px;" onclick="window.ConsoleApp.inspectCapabilities('${this.escapeHtml(agent.agent_id)}')">
                    Capacidades
                  </button>
                </div>
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

      const statusBadge = document.getElementById('exec-status-badge');
      if (statusBadge) {
        statusBadge.style.display = 'inline-block';
        statusBadge.className = 'badge badge-info';
        statusBadge.textContent = isAsync ? 'ENCOLANDO...' : 'EJECUTANDO...';
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
          await this.loadExecutionTrace(agentId, threadId);
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

        const resultBox = document.getElementById('execution-result-box');
        if (resultBox) {
          resultBox.textContent = JSON.stringify(data, null, 2);
        }

        const s = String(data.status || '').toLowerCase();
        const isTerminal = ['completed', 'success', 'resolved', 'failed', 'error', 'cancelled', 'timeout'].includes(s);
        const isWaiting = ['waiting_approval', 'awaiting_approval'].includes(s);

        if (!isTerminal && !isWaiting) {
          this.state.executionPollingInterval = setInterval(() => {
            this.pollExecution(agentId, threadId);
          }, 2000);
        } else {
          this.fetchExecutions();
          this.fetchApprovals();
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

        const resultBox = document.getElementById('execution-result-box');
        if (resultBox) {
          resultBox.textContent = JSON.stringify(data, null, 2);
        }

        const s = String(data.status || '').toLowerCase();
        const isTerminal = ['completed', 'success', 'resolved', 'failed', 'error', 'cancelled', 'timeout'].includes(s);
        const isWaiting = ['waiting_approval', 'awaiting_approval'].includes(s);

        if (isTerminal || isWaiting) {
          clearInterval(this.state.executionPollingInterval);
          this.state.executionPollingInterval = null;
          if (isWaiting) {
            this.showToast('La ejecución requiere aprobación humana (HITL)', 'warning');
          } else {
            const isErr = s.includes('fail') || s.includes('error') || s.includes('cancel');
            this.showToast(`Ejecución finalizada: ${String(data.status).toUpperCase()}`, isErr ? 'error' : 'success');
          }
          this.fetchExecutions();
          this.fetchApprovals();
        }
      } catch {
        // Silent polling error
      }
    },

    renderExecutionDetail(data) {
      if (!data) return;

      const s = String(data.status || '').toLowerCase();
      const isCompleted = ['completed', 'success', 'resolved'].includes(s);
      const isWaiting = ['waiting_approval', 'awaiting_approval'].includes(s);
      const isFailed = ['failed', 'error', 'timeout'].includes(s);
      const isProgress = !isCompleted && !isWaiting && !isFailed && s !== '';

      const statusBadge = document.getElementById('exec-status-badge');
      if (statusBadge) {
        statusBadge.style.display = 'inline-block';
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
        if (out !== undefined && out !== null && String(out).trim() !== '' && String(out).trim() !== 'null') {
          outputBox.textContent = typeof out === 'string' ? out : JSON.stringify(out, null, 2);
        } else if (isProgress) {
          outputBox.textContent = `⏳ Ejecución en curso en el agente [Fase: ${(data.status || 'PROCESANDO').toUpperCase()}]...`;
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

    updateExecutionFilterOptions(executions) {
      const selectAgent = document.getElementById('executions-filter-agent-id');
      if (!selectAgent) return;

      const currentVal = this.state.executionFilterAgentId || 'ALL';
      const agentsMap = new Map();

      (this.state.agents || []).forEach((a) => {
        if (a.agent_id) {
          agentsMap.set(a.agent_id, a.name || a.agent_id);
        }
      });

      (executions || []).forEach((e) => {
        if (e.agent_id && !agentsMap.has(e.agent_id)) {
          agentsMap.set(e.agent_id, e.agent_name || e.agent_id);
        }
      });

      selectAgent.innerHTML = '<option value="ALL">Todos los agentes</option>';
      Array.from(agentsMap.entries())
        .sort((a, b) => a[1].localeCompare(b[1]))
        .forEach(([agentId, agentName]) => {
          const opt = document.createElement('option');
          opt.value = agentId;
          opt.textContent = agentName;
          if (agentId === currentVal) opt.selected = true;
          selectAgent.appendChild(opt);
        });

      if (currentVal && currentVal !== 'ALL') {
        selectAgent.value = currentVal;
      }
    },

    renderFilteredExecutions() {
      const {
        executionsList,
        executionFilterAgentId,
        executionFilterStatus,
        executionSearchQuery,
        executionFilterDateFrom,
        executionFilterDateTo,
        executionSortBy,
        executionSortOrder,
      } = this.state;
      if (!executionsList) return;

      let filtered = executionsList.filter((item) => {
        if (executionFilterAgentId && executionFilterAgentId !== 'ALL') {
          const itemAgentId = (item.agent_id || '').toLowerCase();
          if (itemAgentId !== executionFilterAgentId.toLowerCase()) {
            return false;
          }
        }

        if (executionFilterStatus && executionFilterStatus !== 'ALL') {
          const itemStatus = (item.status || 'UNKNOWN').toUpperCase();
          if (itemStatus !== executionFilterStatus) {
            return false;
          }
        }

        if (executionSearchQuery) {
          const q = executionSearchQuery;
          const prompt = (item.prompt || '').toLowerCase();
          const thread = (item.thread_id || item.task_id || '').toLowerCase();
          const agentName = (item.agent_name || '').toLowerCase();
          const agentId = (item.agent_id || '').toLowerCase();
          const agentType = (item.agent_type || '').toLowerCase();
          const user = (item.user || '').toLowerCase();
          if (!prompt.includes(q) && !thread.includes(q) && !agentName.includes(q) && !agentId.includes(q) && !agentType.includes(q) && !user.includes(q)) {
            return false;
          }
        }

        if (executionFilterDateFrom) {
          const fromTime = new Date(executionFilterDateFrom).getTime();
          const itemTime = item.created_at ? new Date(item.created_at).getTime() : 0;
          if (itemTime < fromTime) {
            return false;
          }
        }

        if (executionFilterDateTo) {
          const toTime = new Date(executionFilterDateTo).getTime();
          const itemTime = item.created_at ? new Date(item.created_at).getTime() : 0;
          if (itemTime > toTime) {
            return false;
          }
        }

        return true;
      });

      // Sort filtered executions
      const sortBy = executionSortBy || 'created_at';
      const isAsc = (executionSortOrder || 'desc') === 'asc';

      filtered.sort((a, b) => {
        let valA = a[sortBy];
        let valB = b[sortBy];

        if (sortBy === 'created_at') {
          const timeA = valA ? new Date(valA).getTime() : 0;
          const timeB = valB ? new Date(valB).getTime() : 0;
          return isAsc ? timeA - timeB : timeB - timeA;
        }

        valA = String(valA || '').toLowerCase();
        valB = String(valB || '').toLowerCase();
        const cmp = valA.localeCompare(valB, undefined, { numeric: true, sensitivity: 'base' });
        return isAsc ? cmp : -cmp;
      });

      // Update sort icons in table headers
      const sortColumns = ['prompt', 'created_at', 'agent_name', 'user', 'status'];
      sortColumns.forEach((col) => {
        const iconEl = document.getElementById(`sort-icon-${col}`);
        if (iconEl) {
          if (col === sortBy) {
            iconEl.textContent = isAsc ? '▲' : '▼';
            iconEl.style.color = 'var(--accent-cyan)';
          } else {
            iconEl.textContent = '⇅';
            iconEl.style.color = 'var(--text-muted)';
          }
        }
      });

      this.renderExecutionsTable(filtered);
    },

    renderExecutionsTable(executions) {
      const tbody = document.getElementById('executions-history-body');
      if (!tbody) return;

      if (!executions || executions.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 28px;">
              No hay ejecuciones que coincidan con los filtros aplicados.
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

          const agentType = exec.agent_type || 'General';
          let agentTypeBadgeClass = 'badge-info';
          if (agentType.toLowerCase() === 'devops') {
            agentTypeBadgeClass = 'badge-pending';
          } else if (agentType.toLowerCase() === 'conversacional') {
            agentTypeBadgeClass = 'badge-success';
          }

          const promptFull = exec.prompt || '';
          const promptTruncated = exec.prompt_truncated || '(Sin prompt)';
          const dateStr = exec.created_at_formatted || '-';
          const agentId = exec.agent_id || '-';
          const agentName = exec.agent_name || agentId;
          const threadId = exec.thread_id || exec.task_id || '';
          const userName = exec.user || 'Sistema';

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
                <span style="font-size: 12px; color: var(--text-secondary); font-weight: 500;">
                  👤 ${this.escapeHtml(userName)}
                </span>
              </td>
              <td style="padding: 12px 16px; vertical-align: middle;">
                <span class="badge ${statusBadgeClass}">${this.escapeHtml(status)}</span>
              </td>
              <td style="padding: 12px 16px; vertical-align: middle; text-align: right;">
                <button type="button" class="btn btn-secondary btn-view-trace" style="font-size: 11px; padding: 5px 10px;" onclick="window.ConsoleApp.openExecutionTraceModal('${this.escapeHtml(agentId)}', '${this.escapeHtml(threadId)}')">
                  🔍 Ver Traza
                </button>
              </td>
            </tr>
          `;
        })
        .join('');
    },

    async openExecutionTraceModal(agentId, threadId) {
      if (!threadId) {
        this.showToast('Esta ejecución no dispone de Thread ID registrado', 'error');
        return;
      }

      const modal = document.getElementById('modal-execution-trace');
      if (!modal) return;

      const threadBadge = document.getElementById('modal-trace-thread-badge');
      if (threadBadge) threadBadge.textContent = threadId;

      const statusBadge = document.getElementById('modal-trace-status-badge');
      if (statusBadge) {
        statusBadge.className = 'badge badge-pending';
        statusBadge.textContent = 'CARGANDO...';
      }

      const agentNameEl = document.getElementById('modal-trace-agent-name');
      if (agentNameEl) agentNameEl.textContent = agentId || '-';

      const userNameEl = document.getElementById('modal-trace-user-name');
      if (userNameEl) userNameEl.textContent = 'Cargando...';

      const latencyEl = document.getElementById('modal-trace-latency');
      if (latencyEl) latencyEl.textContent = '-';

      const promptEl = document.getElementById('modal-trace-prompt');
      if (promptEl) promptEl.textContent = 'Cargando prompt...';

      const timelineEl = document.getElementById('modal-trace-steps-timeline');
      if (timelineEl) {
        timelineEl.innerHTML = '<div style="font-size: 12px; color: var(--text-muted); padding: 16px; text-align: center;">⏳ Cargando saltos cognitivos...</div>';
      }

      const outputEl = document.getElementById('modal-trace-output');
      if (outputEl) outputEl.textContent = 'Cargando resultado...';

      const jsonEl = document.getElementById('modal-trace-raw-json');
      if (jsonEl) jsonEl.textContent = '';

      const rejectionBanner = document.getElementById('modal-trace-rejection-banner');
      if (rejectionBanner) {
        rejectionBanner.innerHTML = '';
        rejectionBanner.style.display = 'none';
      }

      modal.classList.add('open');

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

        const status = (data.status || 'UNKNOWN').toUpperCase();
        let statusBadgeClass = 'badge-pending';
        if (status === 'COMPLETED' || status === 'SUCCESS') statusBadgeClass = 'badge-success';
        else if (status === 'WAITING_APPROVAL') statusBadgeClass = 'badge-high';
        else if (status === 'FAILED' || status === 'REJECTED') statusBadgeClass = 'badge-danger';
        else if (status === 'PROCESSING' || status === 'RUNNING') statusBadgeClass = 'badge-info';

        if (statusBadge) {
          statusBadge.className = `badge ${statusBadgeClass}`;
          statusBadge.textContent = status;
        }

        if (agentNameEl) {
          agentNameEl.textContent = data.agent_name || agentId;
        }

        if (userNameEl) {
          const u = data.user_context?.name || data.username || data.metadata?.triggered_by || data.user || 'Sistema';
          userNameEl.textContent = u;
        }

        if (latencyEl) {
          latencyEl.textContent = data.latency_ms != null ? `${data.latency_ms}ms` : (data.duration || '-');
        }

        if (promptEl) {
          promptEl.textContent = data.prompt || data.user_prompt || data.data?.prompt || '(Sin prompt)';
        }

        if (rejectionBanner) {
          const hitl = data.hitl_governance;
          const isHitlRejected = hitl && String(hitl.status).toUpperCase() === 'REJECTED';
          const isCancelled = String(data.status).toLowerCase() === 'cancelled';
          const justification = hitl?.justification || data.output || data.response || (isCancelled ? 'Ejecución cancelada por rechazo de operador.' : null);

          if ((isHitlRejected || isCancelled) && justification) {
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
              </div>
            `;
            rejectionBanner.style.display = 'block';
          }
        }

        if (timelineEl) {
          const rawSteps = data.cognitive_steps || data.steps || data.data?.cognitive_steps || data.data?.steps || [];
          let steps = [];
          if (Array.isArray(rawSteps) && rawSteps.length > 0) {
            steps = rawSteps;
          } else if (data.plan && Array.isArray(data.plan.steps)) {
            steps = data.plan.steps;
          }
          this.renderStepsTimeline(steps, timelineEl, 'modal-trace');
        }

        if (outputEl) {
          const out = data.output || data.response || data.final_output || data.data?.output || data.data?.response;
          if (out !== undefined && out !== null && String(out).trim() !== '' && String(out).trim() !== 'null') {
            outputEl.textContent = typeof out === 'string' ? out : JSON.stringify(out, null, 2);
          } else {
            outputEl.textContent = JSON.stringify(data, null, 2);
          }
        }

        if (jsonEl) {
          jsonEl.textContent = JSON.stringify(data, null, 2);
        }
      } catch (err) {
        if (statusBadge) {
          statusBadge.className = 'badge badge-danger';
          statusBadge.textContent = 'ERROR';
        }
        if (timelineEl) {
          timelineEl.innerHTML = `<div style="font-size: 13px; color: var(--accent-red); padding: 16px; text-align: center;">Error al consultar traza: ${this.escapeHtml(err.message)}</div>`;
        }
        this.showToast(`Error al consultar traza: ${err.message}`, 'error');
      }
    },

    viewAgentHistory(agentId) {
      if (!agentId) return;
      this.switchTab('executions');
      this.state.executionFilterAgentId = agentId;
      this.updateExecutionFilterOptions(this.state.executionsList);
      const selectAgent = document.getElementById('executions-filter-agent-id');
      if (selectAgent) {
        if (!Array.from(selectAgent.options).some((o) => o.value === agentId)) {
          const opt = document.createElement('option');
          opt.value = agentId;
          const agentObj = (this.state.agents || []).find((a) => a.agent_id === agentId);
          opt.textContent = agentObj?.name || agentId;
          selectAgent.appendChild(opt);
        }
        selectAgent.value = agentId;
      }
      const searchInput = document.getElementById('executions-search');
      if (searchInput) {
        searchInput.value = '';
      }
      this.state.executionSearchQuery = '';
      this.renderFilteredExecutions();
      this.showToast(`Historial filtrado para el agente ${agentId}`, 'info');
    },

    startNewExecutionForAgent(agentId) {
      if (!agentId) return;
      this.switchTab('launcher');
      const selectAgent = document.getElementById('trigger-agent-id');
      if (selectAgent) {
        selectAgent.value = agentId;
      }
      const promptInput = document.getElementById('trigger-prompt');
      if (promptInput) {
        promptInput.focus();
      }
      this.showToast(`Agente ${agentId} seleccionado para ejecución`, 'info');
    },

    selectAndLoadExecution(agentId, threadId) {
      this.openExecutionTraceModal(agentId, threadId);
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

    initTheme() {
      const savedTheme = localStorage.getItem('console_theme') || 'cyber-dark';
      this.applyTheme(savedTheme, false);
    },

    applyTheme(themeName, showNotification = true) {
      document.documentElement.setAttribute('data-theme', themeName);
      localStorage.setItem('console_theme', themeName);

      document.querySelectorAll('.theme-option-btn').forEach((btn) => {
        btn.classList.toggle('active', btn.getAttribute('data-theme') === themeName);
      });

      const labels = {
        'cyber-dark': 'Cyber Dark',
        'emerald-matrix': 'Matrix Emerald',
        'midnight-slate': 'Midnight Slate',
        'amethyst-purple': 'Amethyst Night',
        'high-contrast': 'High Contrast',
      };
      const labelEl = document.getElementById('current-theme-label');
      if (labelEl) {
        labelEl.textContent = labels[themeName] || 'Tema';
      }

      if (showNotification) {
        this.showToast(`Estilo visual cambiado a: ${labels[themeName] || themeName}`, 'info');
      }
    },

    async openUserProfileModal() {
      const modal = document.getElementById('modal-user-profile');
      if (!modal) return;

      try {
        const res = await fetch('/console/profile', {
          headers: this.getAuthHeaders(),
        });
        if (res.ok) {
          const data = await res.json();
          const u = data.user || {};
          const nameInput = document.getElementById('profile-name');
          if (nameInput && u.name) nameInput.value = u.name;
          const emailInput = document.getElementById('profile-email');
          if (emailInput && u.email) emailInput.value = u.email;
          const roleBadge = document.getElementById('profile-role-badge');
          if (roleBadge && u.role) roleBadge.textContent = u.role.toUpperCase();
        }
      } catch (err) {
        // Fallback
      }

      const currPass = document.getElementById('profile-current-password');
      if (currPass) currPass.value = '';
      const newPass = document.getElementById('profile-new-password');
      if (newPass) newPass.value = '';
      const confirmPass = document.getElementById('profile-new-password-confirmation');
      if (confirmPass) confirmPass.value = '';

      modal.classList.add('open');
    },

    async submitUserProfile() {
      const name = document.getElementById('profile-name')?.value?.trim();
      const email = document.getElementById('profile-email')?.value?.trim();
      const currentPassword = document.getElementById('profile-current-password')?.value;
      const newPassword = document.getElementById('profile-new-password')?.value;
      const newPasswordConfirm = document.getElementById('profile-new-password-confirmation')?.value;

      if (!name || !email) {
        this.showToast('El nombre y el correo electrónico son obligatorios.', 'error');
        return;
      }

      if (newPassword && newPassword.length < 8) {
        this.showToast('La nueva contraseña debe tener al menos 8 caracteres.', 'error');
        return;
      }

      if (newPassword && newPassword !== newPasswordConfirm) {
        this.showToast('La confirmación de la contraseña no coincide.', 'error');
        return;
      }

      const btnSave = document.getElementById('btn-save-profile');
      const originalText = btnSave ? btnSave.innerHTML : 'Guardar Cambios';
      if (btnSave) {
        btnSave.disabled = true;
        btnSave.textContent = 'Guardando...';
      }

      try {
        const payload = {
          name,
          email,
        };
        if (newPassword) {
          payload.current_password = currentPassword;
          payload.new_password = newPassword;
          payload.new_password_confirmation = newPasswordConfirm;
        }

        const res = await fetch('/console/profile', {
          method: 'POST',
          headers: {
            ...this.getAuthHeaders(),
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(payload),
        });

        const data = await res.json();
        if (!res.ok) {
          throw new Error(data.message || data.error || 'Error al guardar cambios de perfil');
        }

        const headerName = document.getElementById('header-user-name');
        if (headerName && data.user?.name) {
          headerName.textContent = data.user.name;
        }

        this.showToast(data.message || 'Perfil actualizado correctamente', 'success');
        this.closeAllModals();
      } catch (err) {
        this.showToast(`Error: ${err.message}`, 'error');
      } finally {
        if (btnSave) {
          btnSave.disabled = false;
          btnSave.innerHTML = originalText;
        }
      }
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
