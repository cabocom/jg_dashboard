/**
 * HITL Plan Steps Parsing and Rendering module for javiergalvez-ia Console.
 */

export const hitlPlanMethods = {
  extractPlanSteps(app) {
    const plan = app.proposed_plan || {};
    let rawSteps = [];

    if (Array.isArray(plan.steps) && plan.steps.length > 0) {
      rawSteps = plan.steps;
    } else if (Array.isArray(plan.actions) && plan.actions.length > 0) {
      rawSteps = plan.actions;
    } else {
      const cogList = Array.isArray(app.cognitive_steps) && app.cognitive_steps.length > 0
        ? app.cognitive_steps
        : (Array.isArray(plan.cognitive_steps) ? plan.cognitive_steps : []);

      for (const cs of cogList) {
        const raPlanSteps = cs?.details?.risk_assessment?.plan_steps || cs?.details?.plan?.steps || cs?.details?.plan_steps;
        if (Array.isArray(raPlanSteps) && raPlanSteps.length > 0) {
          rawSteps = raPlanSteps;
          break;
        }
      }

      if (rawSteps.length === 0) {
        for (const cs of cogList) {
          const tcs = cs?.details?.tool_calls;
          if (Array.isArray(tcs) && tcs.length > 0) {
            rawSteps = tcs.map((tc, idx) => {
              const tName = tc.name || tc.tool_name || `tool_${idx + 1}`;
              const tArgs = tc.args || tc.tool_args || {};
              const cmd = tArgs.command || tArgs.target_url || tArgs.url || tArgs.target || null;
              return {
                index: idx + 1,
                tool: tName,
                command: cmd,
                action: cmd ? `Ejecutar ${tName}: ${cmd}` : `Ejecutar ${tName}`,
              };
            });
            break;
          }
        }
      }
    }

    if (rawSteps.length > 0) {
      return rawSteps.map((s, idx) => {
        if (typeof s === 'string') {
          const isMut = /(mutate|restart|stop|rm|delete|destroy|create|run|kill|write|update)/i.test(s);
          return {
            index: idx + 1,
            action: s,
            command: null,
            tool: null,
            riskLevel: isMut ? 'HIGH' : 'LOW',
            isMutative: isMut,
          };
        }
        const tool = s.tool || s.tool_name || null;
        const argsObj = s.tool_args || s.args || {};
        const command = s.command || s.cmd || argsObj.command || argsObj.target_url || argsObj.url || argsObj.target || null;
        const action = s.action || s.description || s.name || s.step || (command ? `Ejecutar ${tool || 'comando'}: ${command}` : `Etapa ${idx + 1}`);
        const riskLevel = String(s.risk_level || (s.mutative ? 'HIGH' : (app.risk_level || 'HIGH'))).toUpperCase();
        const isMutative =
          s.mutative === true ||
          s.read_only === false ||
          ['HIGH', 'CRITICAL', 'MEDIUM'].includes(riskLevel) ||
          /(mutate|restart|stop|rm|delete|destroy|create|run|kill|write|update)/i.test(`${tool || ''} ${command || ''}`);

        return {
          index: idx + 1,
          action,
          command,
          tool,
          riskLevel: isMutative ? (riskLevel === 'LOW' ? 'HIGH' : riskLevel) : 'LOW',
          isMutative,
        };
      });
    }

    const action = plan.action || plan.description || plan.summary || app.action_summary || 'Ejecución de plan cognitivo';
    const command = plan.command || plan.cmd || null;
    const tool = (app.tools_involved && app.tools_involved.length > 0) ? app.tools_involved.join(', ') : null;
    return [{
      index: 1,
      action,
      command,
      tool,
      riskLevel: String(app.risk_level || 'HIGH').toUpperCase(),
      isMutative: true,
    }];
  },

  renderActionPlanStepsHtml(steps) {
    return steps
      .map(
        (s) => `
        <div class="step-item ${s.isMutative ? 'step-mutative' : ''}">
          <span class="step-badge">${s.index}</span>
          <div class="step-header" style="flex-wrap: wrap;">
            <span class="step-action-text">${this.escapeHtml(s.action)}</span>
            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
              ${
                s.isMutative
                  ? `<span class="step-mutative-badge">🔴 ETAPA MUTATIVA · ${this.escapeHtml(s.riskLevel || 'HIGH')}</span>`
                  : `<span class="step-readonly-badge">🟢 SOLO LECTURA</span>`
              }
              ${s.tool ? `<span class="tool-tag">${this.escapeHtml(s.tool)}</span>` : ''}
            </div>
          </div>
          ${s.command ? `<div class="step-command-box"><code>$ ${this.escapeHtml(s.command)}</code></div>` : ''}
        </div>
      `
      )
      .join('');
  },

  renderMutativeWarningBannerHtml(steps) {
    const mutativeCount = steps.filter((s) => s.isMutative).length;
    const totalCount = steps.length;
    return `
      <div class="hitl-mutative-warning">
        <span style="font-size: 16px; line-height: 1;">⚠️</span>
        <div>
          <strong>AVISO DE GOBERNANZA HITL:</strong> Con una única aprobación se autorizan todas las etapas de este plan de acción
          (<strong>${totalCount} etapa${totalCount === 1 ? '' : 's'}</strong> en total, de las cuales
          <strong>${mutativeCount} ${mutativeCount === 1 ? 'es ETAPA MUTATIVA marcada' : 'son ETAPAS MUTATIVAS marcadas'} en rojo</strong>).
          Revisa detalladamente las acciones rojas antes de conceder autorización.
        </div>
      </div>
    `;
  },
};
