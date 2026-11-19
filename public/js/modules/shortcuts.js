/**
 * Agent Shortcuts and Navigation module for javiergalvez-ia Console.
 */

export const shortcutsMethods = {
  viewAgentHistory(agentId) {
    if (!agentId) return;
    this.state.executionFilterAgentId = agentId;
    this.state.executionSearchQuery = '';
    const searchInput = document.getElementById('executions-search');
    if (searchInput) {
      searchInput.value = '';
    }
    this.switchTab('executions');
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
};
