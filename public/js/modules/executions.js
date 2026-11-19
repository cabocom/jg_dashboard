/**
 * Executions and Launching module for javiergalvez-ia Console.
 */

export const executionsMethods = {
  async submitTriggerExecution() {
    if (this.state.isTriggeringExecution) return;

    const agentId = document.getElementById('trigger-agent-id')?.value;
    const prompt = document.getElementById('trigger-prompt')?.value;

    if (!agentId || !prompt || prompt.trim().length === 0) {
      this.showToast('Selecciona un agente y redacta un prompt', 'error');
      return;
    }

    const isAsync = document.getElementById('trigger-async')?.checked || false;
    const btnSubmit = document.getElementById('btn-trigger-execution') || document.querySelector('#form-trigger-execution button[type="submit"]');
    const originalBtnHtml = btnSubmit ? btnSubmit.innerHTML : '▶ Disparar Ejecución en Agente';

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
      if (isAsync) payload.async = true;

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

      if (resultBox) resultBox.textContent = JSON.stringify(data, null, 2);

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
      if (resultBox) resultBox.textContent = `Error: ${err.message}`;
    } finally {
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
    const container = document.getElementById('recent-executions-list');
    if (!container) return;

    if (this.state.recentExecutions.length === 0) {
      container.innerHTML = '<span style="color: var(--text-muted); font-size: 11px;">Sin ejecuciones recientes en esta sesión</span>';
      return;
    }

    container.innerHTML = this.state.recentExecutions
      .map(
        (item) => `
        <button type="button" class="recent-thread-chip" onclick="window.ConsoleApp.loadExecutionTrace('${this.escapeHtml(item.agent_id)}', '${this.escapeHtml(item.thread_id)}')">
          ⚡ ${this.escapeHtml(item.thread_id)} (${item.timestamp})
        </button>
      `
      )
      .join('');
  },

  updateExecutionFilterOptions(executions) {
    const selectAgent = document.getElementById('executions-filter-agent-id');
    if (!selectAgent) return;

    const currentVal = this.state.executionFilterAgentId || 'ALL';
    const agentsMap = new Map();

    (this.state.agents || []).forEach((a) => {
      if (a.agent_id) agentsMap.set(a.agent_id, a.name || a.agent_id);
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
        if (itemAgentId !== executionFilterAgentId.toLowerCase()) return false;
      }
      if (executionFilterStatus && executionFilterStatus !== 'ALL') {
        const itemStatus = (item.status || 'UNKNOWN').toUpperCase();
        if (itemStatus !== executionFilterStatus) return false;
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
        if (itemTime < fromTime) return false;
      }
      if (executionFilterDateTo) {
        const toTime = new Date(executionFilterDateTo).getTime();
        const itemTime = item.created_at ? new Date(item.created_at).getTime() : 0;
        if (itemTime > toTime) return false;
      }
      return true;
    });

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
      tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 28px;">No hay ejecuciones que coincidan con los filtros aplicados.</td></tr>`;
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
};
