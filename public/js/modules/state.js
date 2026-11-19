/**
 * State module for javiergalvez-ia Console.
 */

export function createInitialState() {
  return {
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
    isSubmittingApproval: false,
    isSubmittingRejection: false,
    isCreatingAgent: false,
    isSavingProfile: false,
    isRefreshingExecutions: false,
  };
}
