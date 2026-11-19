/**
 * Timeline and Execution Trace module for javiergalvez-ia Console.
 */

export const timelineMethods = {
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
      // Polling error silencioso
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

    const outputBox = document.getElementById('execution-output-box');
    if (outputBox) {
      const out = data.output || data.response || data.final_output || data.data?.output || data.data?.response;
      const isQueuedMsg = out === 'Ejecución aceptada y encolada en segundo plano.';
      if (out !== undefined && out !== null && String(out).trim() !== '' && String(out).trim() !== 'null' && (!isProgress || !isQueuedMsg)) {
        outputBox.textContent = typeof out === 'string' ? out : JSON.stringify(out, null, 2);
      } else if (isProgress) {
        outputBox.textContent = `⏳ Ejecución en curso en el agente [Fase: ${(data.status || 'PROCESANDO').toUpperCase()}]...`;
      } else {
        outputBox.textContent = JSON.stringify(data, null, 2);
      }
    }
  },
};
