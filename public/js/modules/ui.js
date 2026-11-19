/**
 * UI Utilities and Theme module for javiergalvez-ia Console.
 */

export const uiMethods = {
  initTheme() {
    const savedTheme = localStorage.getItem('jg_theme') || 'catppuccin-frappe';
    this.applyTheme(savedTheme, false);
  },

  applyTheme(themeName, showNotification = true) {
    const validThemes = [
      'catppuccin-frappe',
      'dark',
      'nord',
      'dracula',
      'tokyo-night',
      'solarized-dark',
      'gruvbox-dark',
      'light',
    ];

    const targetTheme = validThemes.includes(themeName) ? themeName : 'catppuccin-frappe';
    document.documentElement.setAttribute('data-theme', targetTheme);
    localStorage.setItem('jg_theme', targetTheme);

    const themeLabels = {
      'catppuccin-frappe': 'Catppuccin Frappé',
      dark: 'Default Dark',
      nord: 'Nord Oceanic',
      dracula: 'Dracula Dark',
      'tokyo-night': 'Tokyo Night',
      'solarized-dark': 'Solarized Dark',
      'gruvbox-dark': 'Gruvbox Dark',
      light: 'Clean Light',
    };

    const labelEl = document.getElementById('current-theme-label');
    if (labelEl) {
      labelEl.textContent = themeLabels[targetTheme] || targetTheme;
    }

    document.querySelectorAll('.theme-option-btn').forEach((btn) => {
      const btnTheme = btn.getAttribute('data-theme');
      if (btnTheme === targetTheme) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });

    if (showNotification) {
      this.showToast(`Tema visual cambiado a: ${themeLabels[targetTheme]}`, 'info');
    }
  },

  showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    toast.textContent = message;
    toast.className = `toast toast-${type} show`;

    if (this._toastTimer) {
      clearTimeout(this._toastTimer);
    }

    this._toastTimer = setTimeout(() => {
      toast.className = 'toast';
    }, 4000);
  },

  setTextContent(elementId, text) {
    const el = document.getElementById(elementId);
    if (el) el.textContent = text;
  },

  escapeHtml(str) {
    if (!str && str !== 0) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  },

  copyToClipboard(text, successMsg = 'Copiado al portapapeles') {
    if (!text) return;
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text)
        .then(() => this.showToast(successMsg, 'success'))
        .catch(() => this.fallbackCopyToClipboard(text, successMsg));
    } else {
      this.fallbackCopyToClipboard(text, successMsg);
    }
  },

  fallbackCopyToClipboard(text, successMsg) {
    try {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.style.position = 'fixed';
      textarea.style.left = '-9999px';
      document.body.appendChild(textarea);
      textarea.focus();
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
      this.showToast(successMsg, 'success');
    } catch (err) {
      this.showToast('No se pudo copiar automáticamente al portapapeles', 'warning');
    }
  },
};
