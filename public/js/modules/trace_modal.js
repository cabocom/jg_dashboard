/**
 * Execution Trace Modal module for javiergalvez-ia Console.
 */

export const traceModalMethods = {
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
      if (agentNameEl) agentNameEl.textContent = data.agent_name || agentId;
      if (userNameEl) {
        userNameEl.textContent = data.user_context?.name || data.username || data.metadata?.triggered_by || data.user || 'Sistema';
      }
      if (latencyEl) latencyEl.textContent = data.latency_ms != null ? `${data.latency_ms}ms` : (data.duration || '-');
      if (promptEl) promptEl.textContent = data.prompt || data.user_prompt || data.data?.prompt || '(Sin prompt)';

      if (rejectionBanner) {
        const hitl = data.hitl_governance;
        const isHitlRejected = hitl && String(hitl.status).toUpperCase() === 'REJECTED';
        const isCancelled = String(data.status).toLowerCase() === 'cancelled';
        const justification = hitl?.justification || data.output || data.response || (isCancelled ? 'Ejecución cancelada por rechazo de operador.' : null);

        if ((isHitlRejected || isCancelled) && justification) {
          rejectionBanner.innerHTML = `
            <div class="trace-rejection-banner">
              <div class="trace-rejection-header">
                <div class="trace-rejection-title"><span>🛑 Decisión HITL: Operación Denegada / Cancelada</span></div>
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
        let steps = Array.isArray(rawSteps) && rawSteps.length > 0 ? rawSteps : (data.plan && Array.isArray(data.plan.steps) ? data.plan.steps : []);
        this.renderStepsTimeline(steps, timelineEl, 'modal-trace');
      }

      if (outputEl) {
        const out = data.output || data.response || data.final_output || data.data?.output || data.data?.response;
        outputEl.textContent = (out !== undefined && out !== null && String(out).trim() !== '' && String(out).trim() !== 'null')
          ? (typeof out === 'string' ? out : JSON.stringify(out, null, 2))
          : JSON.stringify(data, null, 2);
      }

      if (jsonEl) jsonEl.textContent = JSON.stringify(data, null, 2);
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
};
