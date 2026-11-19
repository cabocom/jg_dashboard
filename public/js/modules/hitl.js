/**
 * HITL Approvals Cards, Filters, and Submissions module for javiergalvez-ia Console.
 */

export const hitlMethods = {
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
      if (hitlRiskFilter !== 'ALL' && app.risk_level !== hitlRiskFilter) {
        return false;
      }
      if (hitlAgentFilter !== 'ALL' && app.agent_id !== hitlAgentFilter) {
        return false;
      }
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
        const toolsHtml = (app.tools_involved || []).map((t) => `<span class="tool-tag">${this.escapeHtml(t)}</span>`).join(' ');
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

        const cardTitle = hasPrompt ? (prompt.length > 60 ? prompt.substring(0, 57) + '...' : prompt) : (app.action_summary || 'Plan de Alto Riesgo');
        const steps = this.extractPlanSteps(app);
        const mutativeCount = steps.filter((s) => s.isMutative).length;
        const warningBannerHtml = this.renderMutativeWarningBannerHtml(steps);
        const stepsHtml = this.renderActionPlanStepsHtml(steps);
        const compensations = app.compensation_commands || app.proposed_plan?.compensation || [];
        let compensationHtml = '';
        if (Array.isArray(compensations) && compensations.length > 0) {
          const compItems = compensations.map((c) => `<div class="compensation-item">${this.escapeHtml(typeof c === 'string' ? c : (c.command || JSON.stringify(c)))}</div>`).join('');
          compensationHtml = `
            <div class="compensation-box">
              <div class="compensation-header"><span>🛡️ Comandos de Reversión (Rollback Automático)</span></div>
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
            ${promptBannerHtml}
            <div class="hitl-steps-container">
              ${warningBannerHtml}
              <div class="hitl-steps-title" style="display: flex; align-items: center; justify-content: space-between;">
                <span>📋 Plan de Acción Propuesto (${steps.length} etapa${steps.length === 1 ? '' : 's'})</span>
                <span class="step-mutative-badge">🔴 ${mutativeCount} mutativa${mutativeCount === 1 ? '' : 's'}</span>
              </div>
              <div class="hitl-steps-timeline">${stepsHtml}</div>
            </div>
            ${toolsHtml ? `
              <div class="hitl-summary-box">
                <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">Herramientas Críticas Requeridas</div>
                <div class="tools-tag-list">${toolsHtml}</div>
              </div>
            ` : ''}
            ${compensationHtml}
            <div class="hitl-time">⏱️ Pausado: <strong>${app.elapsed_time || 'Recientemente'}</strong></div>
            <div class="hitl-actions">
              <button type="button" class="btn btn-primary" style="flex: 1;" onclick="window.ConsoleApp.openApproveModal(${app.id}, '${this.escapeHtml(app.thread_id)}')">✓ Aprobar</button>
              <button type="button" class="btn btn-danger" style="flex: 1;" onclick="window.ConsoleApp.openRejectModal(${app.id}, '${this.escapeHtml(app.thread_id)}')">✕ Rechazar</button>
              <button type="button" class="btn btn-secondary" style="padding: 10px 14px;" title="Inspeccionar Ejecución" onclick="window.ConsoleApp.openInspectPlanModal(${app.id})">🔍 Inspeccionar</button>
            </div>
          </div>
        `;
      })
      .join('');
  },

  async submitApproval() {
    if (this.state.isSubmittingApproval) return;
    const id = this.state.currentApprovalId;
    if (!id) return;

    const btnSubmit = document.getElementById('btn-submit-approve') || document.querySelector('#form-confirm-approve button[type="submit"]');
    const originalHtml = btnSubmit ? btnSubmit.innerHTML : 'Conceder Aprobación';
    this.state.isSubmittingApproval = true;
    if (btnSubmit) {
      btnSubmit.disabled = true;
      btnSubmit.textContent = 'Aprobando...';
    }

    const justification = document.getElementById('approve-justification')?.value || '';
    try {
      const res = await fetch(`/console/approvals/${id}/approve`, {
        method: 'POST',
        headers: { ...this.getAuthHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify({ justification }),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.message || data.error || 'Error al aprobar');

      const successMsg = data.reconciled || data.data?.reconciled ? '✓ Solicitud HITL reconciliada y aprobada en el Control Plane' : '✓ Solicitud HITL aprobada y ejecución reanudada en el agente';
      this.showToast(successMsg, 'success');
      this.closeAllModals();
      this.fetchApprovals();
      this.fetchExecutions();

      const threadId = data.data?.approval?.thread_id || data.thread_id || this.state.currentExecution?.thread_id;
      const agentId = data.data?.approval?.agent_id || data.agent_id || this.state.currentExecution?.agent_id || document.getElementById('trigger-agent-id')?.value;
      if (threadId && agentId) {
        await this.loadExecutionTrace(agentId, threadId);
      }
    } catch (err) {
      this.showToast(`Error: ${err.message}`, 'error');
    } finally {
      this.state.isSubmittingApproval = false;
      if (btnSubmit) {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = originalHtml;
      }
    }
  },

  async submitRejection() {
    if (this.state.isSubmittingRejection) return;
    const id = this.state.currentApprovalId;
    if (!id) return;

    const justification = document.getElementById('reject-justification')?.value || '';
    if (justification.trim().length < 5) {
      this.showToast('El motivo de rechazo debe contener al menos 5 caracteres', 'error');
      return;
    }

    const btnSubmit = document.getElementById('btn-submit-reject') || document.querySelector('#form-confirm-reject button[type="submit"]');
    const originalHtml = btnSubmit ? btnSubmit.innerHTML : 'Confirmar Rechazo';
    this.state.isSubmittingRejection = true;
    if (btnSubmit) {
      btnSubmit.disabled = true;
      btnSubmit.textContent = 'Rechazando...';
    }

    try {
      const res = await fetch(`/console/approvals/${id}/reject`, {
        method: 'POST',
        headers: { ...this.getAuthHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify({ justification }),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.message || data.error || 'Error al rechazar');

      const successMsg = data.reconciled || data.data?.reconciled ? '✕ Solicitud HITL reconciliada y cerrada en el Control Plane' : '✕ Solicitud HITL rechazada y ejecución cancelada en el agente';
      this.showToast(successMsg, 'success');
      this.closeAllModals();
      this.fetchApprovals();
      this.fetchExecutions();

      const threadId = data.data?.approval?.thread_id || data.thread_id || this.state.currentExecution?.thread_id;
      const agentId = data.data?.approval?.agent_id || data.agent_id || this.state.currentExecution?.agent_id || document.getElementById('trigger-agent-id')?.value;
      if (threadId && agentId) {
        await this.loadExecutionTrace(agentId, threadId);
      }
    } catch (err) {
      this.showToast(`Error: ${err.message}`, 'error');
    } finally {
      this.state.isSubmittingRejection = false;
      if (btnSubmit) {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = originalHtml;
      }
    }
  },
};
