/**
 * Modals Management module for javiergalvez-ia Console.
 */

export const modalsMethods = {
  openCreateAgentModal() {
    const nameInput = document.getElementById('create-agent-name');
    if (nameInput) nameInput.value = '';
    const versionInput = document.getElementById('create-agent-version');
    if (versionInput) versionInput.value = '1.0.0';
    const resultBox = document.getElementById('create-agent-result-box');
    if (resultBox) resultBox.style.display = 'none';
    const form = document.getElementById('form-create-agent');
    if (form) form.style.display = 'flex';
    const modal = document.getElementById('modal-create-agent');
    if (modal) modal.classList.add('open');
  },

  openApproveModal(id, threadId) {
    this.state.currentApprovalId = id;
    this.setTextContent('approve-modal-thread-id', threadId);
    const input = document.getElementById('approve-justification');
    if (input) input.value = '';

    const previewEl = document.getElementById('approve-modal-plan-preview');
    if (previewEl) {
      const app = this.state.pendingApprovals.find((a) => a.id === id);
      if (app) {
        const steps = this.extractPlanSteps(app);
        const mutativeCount = steps.filter((s) => s.isMutative).length;
        previewEl.innerHTML = `
          ${this.renderMutativeWarningBannerHtml(steps)}
          <div class="hitl-steps-container" style="margin-top: 8px;">
            <div class="hitl-steps-title" style="display: flex; align-items: center; justify-content: space-between;">
              <span>📋 Plan de Acción que se ejecutará (${steps.length} etapa${steps.length === 1 ? '' : 's'})</span>
              <span class="step-mutative-badge">🔴 ${mutativeCount} mutativa${mutativeCount === 1 ? '' : 's'}</span>
            </div>
            <div class="hitl-steps-timeline">
              ${this.renderActionPlanStepsHtml(steps)}
            </div>
          </div>
        `;
      } else {
        previewEl.innerHTML = '';
      }
    }

    const modal = document.getElementById('modal-approve');
    if (modal) modal.classList.add('open');
  },

  openRejectModal(id, threadId) {
    this.state.currentApprovalId = id;
    this.setTextContent('reject-modal-thread-id', threadId);
    const input = document.getElementById('reject-justification');
    if (input) input.value = '';
    const modal = document.getElementById('modal-reject');
    if (modal) modal.classList.add('open');
  },

  switchInspectTab(tab) {
    const stepsTabBtn = document.getElementById('tab-inspect-steps');
    const jsonTabBtn = document.getElementById('tab-inspect-json');
    const stepsContainer = document.getElementById('inspect-steps-container');
    const jsonContainer = document.getElementById('inspect-json-container');

    if (tab === 'steps') {
      stepsTabBtn?.classList.add('active');
      jsonTabBtn?.classList.remove('active');
      if (stepsContainer) stepsContainer.style.display = 'block';
      if (jsonContainer) jsonContainer.style.display = 'none';
    } else {
      jsonTabBtn?.classList.add('active');
      stepsTabBtn?.classList.remove('active');
      if (stepsContainer) stepsContainer.style.display = 'none';
      if (jsonContainer) jsonContainer.style.display = 'block';
    }
  },

  async openInspectPlanModal(id) {
    const app = this.state.pendingApprovals.find((a) => a.id === id);
    if (!app) return;

    this.state.currentApprovalId = id;
    this.setTextContent('inspect-modal-thread-id', app.thread_id || '-');
    this.setTextContent('inspect-modal-agent-name', app.agent_name || app.agent_id || '-');

    const riskBadge = document.getElementById('inspect-modal-risk-badge');
    if (riskBadge) {
      riskBadge.textContent = app.risk_level || 'HIGH';
      riskBadge.className = `badge ${app.risk_level === 'CRITICAL' ? 'badge-critical' : 'badge-high'}`;
    }

    const promptBox = document.getElementById('inspect-modal-prompt-box');
    if (promptBox) {
      promptBox.textContent = app.prompt || app.proposed_plan?.prompt || 'Sin prompt registrado para esta ejecución.';
    }

    const actionPlanBox = document.getElementById('inspect-modal-action-plan-box');
    if (actionPlanBox) {
      const planSteps = this.extractPlanSteps(app);
      const mutativeCount = planSteps.filter((s) => s.isMutative).length;
      actionPlanBox.innerHTML = `
        ${this.renderMutativeWarningBannerHtml(planSteps)}
        <div class="hitl-steps-container">
          <div class="hitl-steps-title" style="display: flex; align-items: center; justify-content: space-between;">
            <span>📋 Plan de Acción Propuesto (${planSteps.length} etapa${planSteps.length === 1 ? '' : 's'})</span>
            <span class="step-mutative-badge">🔴 ${mutativeCount} mutativa${mutativeCount === 1 ? '' : 's'}</span>
          </div>
          <div class="hitl-steps-timeline">
            ${this.renderActionPlanStepsHtml(planSteps)}
          </div>
        </div>
      `;
    }

    const contentEl = document.getElementById('inspect-plan-content');
    if (contentEl) {
      contentEl.textContent = JSON.stringify(app.proposed_plan || app, null, 2);
    }

    this.switchInspectTab('steps');

    let steps = app.cognitive_steps || app.proposed_plan?.cognitive_steps || app.proposed_plan?.steps || [];
    if (!Array.isArray(steps) || steps.length === 0) {
      const fallback = this.extractPlanSteps(app);
      if (fallback && fallback.length > 0) {
        steps = fallback;
      }
    }

    const timelineEl = document.getElementById('inspect-steps-timeline');
    if (timelineEl) {
      this.state.inspectCurrentSteps = steps;
      this.renderStepsTimeline(steps, timelineEl, 'inspect');

      if (steps.length <= 1 && app.agent_id && app.thread_id) {
        try {
          const res = await fetch(`/console/executions/${app.agent_id}/${app.thread_id}`, {
            headers: this.getAuthHeaders(),
          });
          if (res.ok) {
            const liveData = await res.json();
            const fetchedSteps = liveData.cognitive_steps || liveData.steps || liveData.data?.cognitive_steps || [];
            if (Array.isArray(fetchedSteps) && fetchedSteps.length > 0) {
              this.state.inspectCurrentSteps = fetchedSteps;
              app.cognitive_steps = fetchedSteps;
              this.renderStepsTimeline(fetchedSteps, timelineEl, 'inspect');
            }
            if (liveData.prompt && !app.prompt) {
              app.prompt = liveData.prompt;
              if (promptBox) promptBox.textContent = liveData.prompt;
            }
          }
        } catch (e) {
          console.warn('[HITL] Async telemetry fetch skipped:', e);
        }
      }
    }

    const modal = document.getElementById('modal-inspect-plan');
    if (modal) modal.classList.add('open');
  },

  async openUserProfileModal() {
    const modal = document.getElementById('modal-user-profile');
    if (!modal) return;

    try {
      const res = await fetch('/console/profile', { headers: this.getAuthHeaders() });
      if (res.ok) {
        const data = await res.json();
        const u = data.user || {};
        const nameInput = document.getElementById('profile-name');
        if (nameInput && u.name) nameInput.value = u.name;
        const emailInput = document.getElementById('profile-email');
        if (emailInput && u.email) emailInput.value = u.email;
        const roleBadge = document.getElementById('profile-role-badge');
        if (roleBadge && u.role) roleBadge.textContent = u.role.toUpperCase();
      }
    } catch (err) {
      // Fallback silencioso
    }

    const currPass = document.getElementById('profile-current-password');
    if (currPass) currPass.value = '';
    const newPass = document.getElementById('profile-new-password');
    if (newPass) newPass.value = '';
    const confirmPass = document.getElementById('profile-new-password-confirmation');
    if (confirmPass) confirmPass.value = '';

    modal.classList.add('open');
  },

  async submitUserProfile() {
    const name = document.getElementById('profile-name')?.value?.trim();
    const email = document.getElementById('profile-email')?.value?.trim();
    const currentPassword = document.getElementById('profile-current-password')?.value;
    const newPassword = document.getElementById('profile-new-password')?.value;
    const newPasswordConfirm = document.getElementById('profile-new-password-confirmation')?.value;

    if (!name || !email) {
      this.showToast('El nombre y el correo electrónico son obligatorios.', 'error');
      return;
    }
    if (newPassword && newPassword.length < 8) {
      this.showToast('La nueva contraseña debe tener al menos 8 caracteres.', 'error');
      return;
    }
    if (newPassword && newPassword !== newPasswordConfirm) {
      this.showToast('La confirmación de la contraseña no coincide.', 'error');
      return;
    }

    const btnSave = document.getElementById('btn-save-profile');
    const originalText = btnSave ? btnSave.innerHTML : 'Guardar Cambios';
    if (btnSave) {
      btnSave.disabled = true;
      btnSave.textContent = 'Guardando...';
    }

    try {
      const payload = { name, email };
      if (newPassword) {
        payload.current_password = currentPassword;
        payload.new_password = newPassword;
        payload.new_password_confirmation = newPasswordConfirm;
      }

      const res = await fetch('/console/profile', {
        method: 'POST',
        headers: { ...this.getAuthHeaders(), 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.message || data.error || 'Error al guardar cambios de perfil');

      const headerName = document.getElementById('header-user-name');
      if (headerName && data.user?.name) headerName.textContent = data.user.name;

      this.showToast(data.message || 'Perfil actualizado correctamente', 'success');
      this.closeAllModals();
    } catch (err) {
      this.showToast(`Error: ${err.message}`, 'error');
    } finally {
      if (btnSave) {
        btnSave.disabled = false;
        btnSave.innerHTML = originalText;
      }
    }
  },

  closeAllModals() {
    document.querySelectorAll('.modal-backdrop').forEach((m) => m.classList.remove('open'));
  },
};
