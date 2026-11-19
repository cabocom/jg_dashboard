/**
 * Telemetry and Polling module for javiergalvez-ia Console.
 */

export const telemetryMethods = {
  startPolling() {
    if (this.state.pollingInterval) {
      clearInterval(this.state.pollingInterval);
    }

    this.state.pollingInterval = setInterval(() => {
      if (document.hidden) return;

      if (this.state.activeTab === 'agents') {
        this.fetchAgents(true);
      } else if (this.state.activeTab === 'approvals') {
        this.fetchApprovals(true);
      } else if (this.state.activeTab === 'executions') {
        this.fetchExecutions(true);
      }
    }, 5000);
  },

  stopPolling() {
    if (this.state.pollingInterval) {
      clearInterval(this.state.pollingInterval);
      this.state.pollingInterval = null;
    }
  },
};
