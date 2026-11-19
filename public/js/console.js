/**
 * jg_dashboard Control Plane - Web Console Orchestrator (ES6 Modules)
 * Modularized architecture: each module < 300 LOC.
 */

import { createInitialState } from './modules/state.js';
import { uiMethods } from './modules/ui.js';
import { tabsMethods } from './modules/tabs.js';
import { telemetryMethods } from './modules/telemetry.js';
import { apiMethods } from './modules/api.js';
import { agentsMethods } from './modules/agents.js';
import { hitlPlanMethods } from './modules/hitl_plan.js';
import { hitlMethods } from './modules/hitl.js';
import { modalsMethods } from './modules/modals.js';
import { traceModalMethods } from './modules/trace_modal.js';
import { executionsMethods } from './modules/executions.js';
import { timelineMethods } from './modules/timeline.js';
import { stepsTimelineMethods } from './modules/steps_timeline.js';
import { shortcutsMethods } from './modules/shortcuts.js';
import { bindEvents } from './modules/events.js';

const App = {
  state: createInitialState(),

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
    bindEvents(this);
  },

  ...uiMethods,
  ...tabsMethods,
  ...telemetryMethods,
  ...apiMethods,
  ...agentsMethods,
  ...hitlPlanMethods,
  ...hitlMethods,
  ...modalsMethods,
  ...traceModalMethods,
  ...executionsMethods,
  ...timelineMethods,
  ...stepsTimelineMethods,
  ...shortcutsMethods,
};

window.ConsoleApp = App;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => App.init());
} else {
  App.init();
}

export default App;
