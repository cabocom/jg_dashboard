/**
 * Agents Rendering and Provisioning module for javiergalvez-ia Console.
 */

export const agentsMethods = {
  renderAgentsKPI(summary) {
    if (!summary) return;
    this.setTextContent('kpi-agents-total', summary.total ?? this.state.agents.length);
    this.setTextContent('kpi-agents-active', summary.active ?? 0);
    this.setTextContent('kpi-agents-offline', summary.offline ?? 0);
  },

  renderFilteredAgents() {
    const { agents, agentSearchQuery, agentFilterStatus } = this.state;

    const filtered = agents.filter((agent) => {
      if (agentFilterStatus !== 'ALL' && agent.status !== agentFilterStatus) {
        return false;
      }
      if (agentSearchQuery) {
        const name = (agent.name || '').toLowerCase();
        const id = (agent.agent_id || '').toLowerCase();
        return name.includes(agentSearchQuery) || id.includes(agentSearchQuery);
      }
      return true;
    });

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
        const statusClass = agent.status === 'ACTIVE' ? 'status-active' : agent.status === 'PENDING' ? 'status-pending' : 'status-offline';
        const badgeClass = agent.status === 'ACTIVE' ? 'badge-active' : agent.status === 'PENDING' ? 'badge-pending' : 'badge-offline';
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
              <div class="telemetry-item"><span class="telemetry-label">CPU</span><span class="telemetry-value">${cpuUsage}</span></div>
              <div class="telemetry-item"><span class="telemetry-label">RAM</span><span class="telemetry-value">${memUsage}</span></div>
              <div class="telemetry-item"><span class="telemetry-label">Latencia</span><span class="telemetry-value" style="color: var(--accent-cyan);">${latency}</span></div>
            </div>
            <div style="display: flex; gap: 8px; font-size: 11px; color: var(--text-secondary); flex-wrap: wrap; align-items: center;">
              <span>🛠️ ${toolsCount} Herramientas</span><span>•</span>
              <span>🧠 ${enginesCount} Engines</span><span>•</span>
              <span>v${agent.version || '1.0.0'}</span><span>•</span>
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
        const badgeClass = agent.status === 'ACTIVE' ? 'badge-active' : agent.status === 'PENDING' ? 'badge-pending' : 'badge-offline';
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

  async submitCreateAgent() {
    if (this.state.isCreatingAgent) return;
    const name = document.getElementById('create-agent-name')?.value?.trim();
    const version = document.getElementById('create-agent-version')?.value?.trim() || '1.0.0';

    if (!name) {
      this.showToast('El nombre del agente es obligatorio', 'error');
      return;
    }

    const btnSubmit = document.getElementById('btn-submit-create-agent') || document.querySelector('#form-create-agent button[type="submit"]');
    const originalHtml = btnSubmit ? btnSubmit.innerHTML : 'Generar Bootstrap Token';
    this.state.isCreatingAgent = true;
    if (btnSubmit) {
      btnSubmit.disabled = true;
      btnSubmit.textContent = 'Generando token...';
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
    } finally {
      this.state.isCreatingAgent = false;
      if (btnSubmit) {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = originalHtml;
      }
    }
  },
};
