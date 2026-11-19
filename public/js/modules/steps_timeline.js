/**
 * Steps Timeline rendering and interaction module for javiergalvez-ia Console.
 */

export const stepsTimelineMethods = {
  renderStepsTimeline(steps, containerEl, prefix = 'trace') {
    if (!containerEl) return;
    if (!Array.isArray(steps) || steps.length === 0) {
      containerEl.innerHTML = `<div style="font-size: 13px; color: var(--text-muted); padding: 16px; text-align: center;">✓ Grafo cognitivo completado en un solo ciclo o sin saltos intermedios registrados.</div>`;
      return;
    }

    const controlsHtml = `
      <div class="trace-controls-bar">
        <span style="font-size: 11px; color: var(--text-muted); font-weight: 600;">⚡ ${steps.length} SALTOS COGNITIVOS (PULSA PARA DETALLES):</span>
        <div style="display: flex; gap: 8px;">
          <button type="button" class="trace-controls-btn" onclick="window.ConsoleApp.expandAllSteps('${prefix}')">Expandir todos</button>
          <span style="color: var(--text-muted); font-size: 11px;">|</span>
          <button type="button" class="trace-controls-btn" onclick="window.ConsoleApp.collapseAllSteps('${prefix}')">Colapsar todos</button>
        </div>
      </div>
    `;

    const stepsHtml = steps.map((s, idx) => {
      const node = (s.node || '').toLowerCase();
      const isError = s.status === 'error' || s.status === 'failed' || s.status === 'denied';
      const isInProgress = s.status === 'in_progress';
      let badgeClass = '';
      let badgeIcon = '✓';

      if (isError) { badgeClass = 'error'; badgeIcon = '✕'; }
      else if (s.status === 'waiting_approval') { badgeClass = 'waiting-approval'; badgeIcon = '⏸️'; }
      else if (isInProgress) { badgeClass = 'in-progress'; badgeIcon = '⏳'; }
      else if (node === 'observe') { badgeClass = 'node-observe'; badgeIcon = '👁️'; }
      else if (node === 'reason') { badgeClass = 'node-reason'; badgeIcon = '🧠'; }
      else if (node === 'plan') { badgeClass = 'node-plan'; badgeIcon = '📋'; }
      else if (node === 'risk_assess' || node === 'security_eval') { badgeClass = 'node-security_eval'; badgeIcon = '🛡️'; }
      else if (node === 'human_approval') { badgeClass = 'node-approval'; badgeIcon = '🛑'; }
      else if (node === 'execute') { badgeClass = 'node-execute'; badgeIcon = '⚡'; }
      else if (node === 'verify') { badgeClass = 'node-verify'; badgeIcon = '🔬'; }
      else if (node === 'rollback') { badgeClass = 'node-rollback'; badgeIcon = '⏪'; }
      else if (node === 'conclude') { badgeClass = 'node-conclude'; badgeIcon = '📝'; }
      else { badgeIcon = (idx + 1).toString(); }

      const title = s.title || s.name || (s.node ? `Nodo: ${s.node}` : `Paso ${idx + 1}`);
      const desc = s.description || '';
      const duration = s.duration_ms !== undefined && s.duration_ms !== null ? `${s.duration_ms}ms` : '';
      const toolName = s.tool || s.tool_name || (s.details && (s.details.tool || s.details.tool_name || s.details.selected_tool));

      let pillsHtml = '';
      if (s.node) pillsHtml += `<span class="trace-pill trace-pill-node">Nodo: ${this.escapeHtml(s.node)}</span>`;
      if (toolName) pillsHtml += `<span class="trace-pill tool-tag">🔧 ${this.escapeHtml(toolName)}</span>`;
      if (s.details && s.details.risk_level) {
        const rClass = String(s.details.risk_level).toLowerCase() === 'low' ? 'trace-pill-risk-low' : 'trace-pill-risk-high';
        pillsHtml += `<span class="trace-pill ${rClass}">Riesgo: ${this.escapeHtml(String(s.details.risk_level).toUpperCase())}</span>`;
      }
      if (s.details && s.details.decision) {
        pillsHtml += `<span class="trace-pill trace-pill-allow">Decisión: ${this.escapeHtml(String(s.details.decision).toUpperCase())}</span>`;
      }
      if (duration) pillsHtml += `<span class="trace-pill" style="color: var(--text-muted); font-family: var(--font-mono);">⏱️ ${duration}</span>`;

      const cmdHtml = s.command ? `<div class="step-command-box"><code>$ ${this.escapeHtml(s.command)}</code></div>` : '';
      const descHtml = desc ? `<div class="trace-step-desc" style="margin-top: 6px;">${this.escapeHtml(desc)}</div>` : '';

      return `
        <div id="${prefix}-step-node-${idx}" class="trace-step-node ${prefix}-step-node is-clickable" onclick="window.ConsoleApp.toggleStep(${idx}, '${prefix}')" tabindex="0" role="button" aria-expanded="false" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.ConsoleApp.toggleStep(${idx}, '${prefix}');}">
          <div class="trace-node-badge ${badgeClass}">${badgeIcon}</div>
          <div class="trace-node-header" style="flex-direction: column; align-items: flex-start; gap: 4px;">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
              <div style="display: flex; align-items: center; gap: 8px;">
                <span class="trace-node-title" style="font-weight: 600; font-size: 14px;">${this.escapeHtml(title)}</span>
                ${s.node ? `<span class="trace-pill trace-pill-node">${this.escapeHtml(s.node)}</span>` : ''}
              </div>
              <div style="display: flex; align-items: center; gap: 8px;">
                ${duration ? `<span class="trace-pill" style="color: var(--text-muted); font-family: var(--font-mono);">⏱️ ${duration}</span>` : ''}
                <span class="trace-chevron" title="Click para expandir/colapsar detalles">▼</span>
              </div>
            </div>
            ${pillsHtml ? `<div class="trace-step-pills">${pillsHtml}</div>` : ''}
          </div>
          ${descHtml}
          ${cmdHtml}
          <div id="${prefix}-step-details-${idx}" class="trace-step-details-panel" onclick="event.stopPropagation()">
            <div class="step-details-section">
              <div class="step-details-section-title">
                <span>Payload Completo del Nodo (JSON)</span>
                <button type="button" class="btn-step-copy" onclick="window.ConsoleApp.copyStepJson(${idx}, '${prefix}')">📋 Copiar JSON</button>
              </div>
              <pre class="step-json-box">${this.escapeHtml(JSON.stringify(s, null, 2))}</pre>
            </div>
          </div>
        </div>
      `;
    }).join('');

    containerEl.innerHTML = controlsHtml + stepsHtml;
  },

  toggleStep(idx, prefix = 'trace') {
    const el = document.getElementById(`${prefix}-step-node-${idx}`);
    if (!el) return;
    const isExpanded = el.classList.toggle('is-expanded');
    el.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
  },

  expandAllSteps(prefix = 'trace') {
    document.querySelectorAll(`.${prefix}-step-node.is-clickable`).forEach((n) => {
      n.classList.add('is-expanded');
      n.setAttribute('aria-expanded', 'true');
    });
  },

  collapseAllSteps(prefix = 'trace') {
    document.querySelectorAll(`.${prefix}-step-node.is-clickable`).forEach((n) => {
      n.classList.remove('is-expanded');
      n.setAttribute('aria-expanded', 'false');
    });
  },

  copyStepJson(idx, prefix = 'trace') {
    const steps = prefix === 'inspect' ? this.state.inspectCurrentSteps : this.state.currentSteps;
    if (!steps || !steps[idx]) return;
    const step = steps[idx];
    this.copyToClipboard(JSON.stringify(step, null, 2), `JSON del nodo ${step.node || idx + 1} copiado`);
  },
};
