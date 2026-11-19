/**
 * DOM Event Listeners and Bindings master orchestrator for javiergalvez-ia Console.
 */

import { bindCoreEvents } from './events_core.js';
import { bindExecutionEvents } from './events_executions.js';

export function bindEvents(app) {
  bindCoreEvents(app);
  bindExecutionEvents(app);
}
