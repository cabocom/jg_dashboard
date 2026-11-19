/**
 * Execution and HITL DOM Event Listeners module for javiergalvez-ia Console.
 */

export function bindExecutionEvents(app) {
  // Direct Approve / Reject inside Inspector Modal
  const btnInspectApprove = document.getElementById('btn-inspect-approve');
  if (btnInspectApprove) {
    btnInspectApprove.addEventListener('click', () => {
      const currentId = app.state.currentApprovalId;
      const approval = app.state.pendingApprovals.find((a) => String(a.id) === String(currentId));
      if (approval) {
        document.getElementById('modal-inspect-plan')?.classList.remove('open');
        app.openApproveModal(approval.id, approval.thread_id);
      }
    });
  }

  const btnInspectReject = document.getElementById('btn-inspect-reject');
  if (btnInspectReject) {
    btnInspectReject.addEventListener('click', () => {
      const currentId = app.state.currentApprovalId;
      const approval = app.state.pendingApprovals.find((a) => String(a.id) === String(currentId));
      if (approval) {
        document.getElementById('modal-inspect-plan')?.classList.remove('open');
        app.openRejectModal(approval.id, approval.thread_id);
      }
    });
  }

  const inspectThreadEl = document.getElementById('inspect-modal-thread-id');
  if (inspectThreadEl) {
    inspectThreadEl.addEventListener('click', () => {
      const text = inspectThreadEl.textContent?.trim();
      if (text && text !== '-') app.copyToClipboard(text, 'Thread ID copiado al portapapeles');
    });
  }

  // HITL Risk Pills, Search, Agent Filter
  document.querySelectorAll('.hitl-pill-btn[data-risk]').forEach((btn) => {
    btn.addEventListener('click', () => app.setHitlRiskFilter(btn.getAttribute('data-risk')));
  });

  const hitlSearch = document.getElementById('hitl-search');
  if (hitlSearch) {
    hitlSearch.addEventListener('input', (e) => {
      app.state.hitlSearchQuery = e.target.value.toLowerCase().trim();
      app.renderFilteredApprovals();
    });
  }

  const hitlAgentSelect = document.getElementById('hitl-filter-agent');
  if (hitlAgentSelect) {
    hitlAgentSelect.addEventListener('change', (e) => {
      app.state.hitlAgentFilter = e.target.value;
      app.renderFilteredApprovals();
    });
  }

  // HITL Confirm Approve/Reject Forms
  const formApprove = document.getElementById('form-confirm-approve');
  if (formApprove) {
    formApprove.addEventListener('submit', (e) => {
      e.preventDefault();
      app.submitApproval();
    });
  }

  const formReject = document.getElementById('form-confirm-reject');
  if (formReject) {
    formReject.addEventListener('submit', (e) => {
      e.preventDefault();
      app.submitRejection();
    });
  }

  // Trigger Execution Form
  const formTrigger = document.getElementById('form-trigger-execution');
  if (formTrigger) {
    formTrigger.addEventListener('submit', (e) => {
      e.preventDefault();
      app.submitTriggerExecution();
    });
  }

  // Load Execution Trace Button
  const btnLoadExec = document.getElementById('btn-load-execution');
  if (btnLoadExec) {
    btnLoadExec.addEventListener('click', () => {
      const agentId = document.getElementById('execution-query-agent')?.value;
      const threadId = document.getElementById('execution-query-thread')?.value?.trim();
      if (!agentId || !threadId) {
        app.showToast('Selecciona un agente e introduce el Thread ID', 'error');
        return;
      }
      app.loadExecutionTrace(agentId, threadId);
    });
  }

  // Copy Execution Output
  const btnCopyExec = document.getElementById('btn-copy-exec-output');
  if (btnCopyExec) {
    btnCopyExec.addEventListener('click', () => {
      const text = document.getElementById('execution-output-box')?.textContent;
      if (text) app.copyToClipboard(text, 'Resultado de ejecución copiado al portapapeles');
    });
  }

  // Refresh Executions Button
  const btnRefreshExecList = document.getElementById('btn-refresh-executions-list');
  if (btnRefreshExecList) {
    btnRefreshExecList.addEventListener('click', async () => {
      if (app.state.isRefreshingExecutions) return;
      app.state.isRefreshingExecutions = true;
      btnRefreshExecList.disabled = true;
      try {
        await app.fetchExecutions();
        app.showToast('Historial de ejecuciones sincronizado', 'info');
      } finally {
        app.state.isRefreshingExecutions = false;
        btnRefreshExecList.disabled = false;
      }
    });
  }

  // Execution History Filters
  const filterExecAgent = document.getElementById('executions-filter-agent-id');
  if (filterExecAgent) {
    filterExecAgent.addEventListener('change', (e) => {
      app.state.executionFilterAgentId = e.target.value;
      app.renderFilteredExecutions();
    });
  }

  const filterExecStatus = document.getElementById('executions-filter-status');
  if (filterExecStatus) {
    filterExecStatus.addEventListener('change', (e) => {
      app.state.executionFilterStatus = e.target.value.toUpperCase();
      app.renderFilteredExecutions();
    });
  }

  const searchExecInput = document.getElementById('executions-search');
  if (searchExecInput) {
    searchExecInput.addEventListener('input', (e) => {
      app.state.executionSearchQuery = e.target.value.toLowerCase().trim();
      app.renderFilteredExecutions();
    });
  }

  // Date Range Filters
  const filterDateFrom = document.getElementById('executions-filter-date-from');
  const filterDateTo = document.getElementById('executions-filter-date-to');
  const btnClearDates = document.getElementById('btn-clear-date-filters');

  const handleDateChange = () => {
    app.state.executionFilterDateFrom = filterDateFrom?.value || '';
    app.state.executionFilterDateTo = filterDateTo?.value || '';
    if (btnClearDates) {
      btnClearDates.style.display = (app.state.executionFilterDateFrom || app.state.executionFilterDateTo) ? 'inline-block' : 'none';
    }
    app.renderFilteredExecutions();
  };

  if (filterDateFrom) filterDateFrom.addEventListener('change', handleDateChange);
  if (filterDateTo) filterDateTo.addEventListener('change', handleDateChange);

  if (btnClearDates) {
    btnClearDates.addEventListener('click', () => {
      if (filterDateFrom) filterDateFrom.value = '';
      if (filterDateTo) filterDateTo.value = '';
      app.state.executionFilterDateFrom = '';
      app.state.executionFilterDateTo = '';
      btnClearDates.style.display = 'none';
      app.renderFilteredExecutions();
    });
  }

  // Sortable Headers
  document.querySelectorAll('.sortable-th[data-sort]').forEach((th) => {
    th.addEventListener('click', () => {
      const col = th.getAttribute('data-sort');
      if (!col) return;
      if (app.state.executionSortBy === col) {
        app.state.executionSortOrder = app.state.executionSortOrder === 'asc' ? 'desc' : 'asc';
      } else {
        app.state.executionSortBy = col;
        app.state.executionSortOrder = col === 'created_at' ? 'desc' : 'asc';
      }
      app.renderFilteredExecutions();
    });
  });

  // Modal Copy Buttons
  const btnCopyCaps = document.getElementById('btn-copy-agent-caps');
  if (btnCopyCaps) {
    btnCopyCaps.addEventListener('click', () => {
      const text = document.getElementById('agent-caps-json-content')?.textContent;
      if (text) app.copyToClipboard(text, 'Capacidades técnicas copiadas al portapapeles');
    });
  }

  const btnCopyModalTraceOutput = document.getElementById('btn-copy-modal-trace-output');
  if (btnCopyModalTraceOutput) {
    btnCopyModalTraceOutput.addEventListener('click', () => {
      const text = document.getElementById('modal-trace-output')?.textContent;
      if (text) app.copyToClipboard(text, 'Resultado de ejecución copiado al portapapeles');
    });
  }

  const btnCopyModalTraceJson = document.getElementById('btn-copy-modal-trace-json');
  if (btnCopyModalTraceJson) {
    btnCopyModalTraceJson.addEventListener('click', () => {
      const text = document.getElementById('modal-trace-raw-json')?.textContent;
      if (text) app.copyToClipboard(text, 'JSON de la traza copiado al portapapeles');
    });
  }
}
