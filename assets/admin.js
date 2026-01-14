document.addEventListener('DOMContentLoaded', function () {
  const colorPickers = document.querySelectorAll('.cmp410-color-picker');
  const colorInputs = document.querySelectorAll('.cmp410-color-value');
  const bindInputs = document.querySelectorAll('[data-preview-bind]');

  function updatePreviewColor(target, value) {
    document.querySelectorAll('[data-preview-root]').forEach(function (root) {
      if (!value) {
        return;
      }
      if (target === 'accept_btn_color') {
        root.style.setProperty('--cmp410-accept-bg', value);
      }
      if (target === 'accept_btn_text_color') {
        root.style.setProperty('--cmp410-accept-fg', value);
      }
      if (target === 'customize_btn_color') {
        root.style.setProperty('--cmp410-customize-bg', value);
      }
      if (target === 'customize_btn_text_color') {
        root.style.setProperty('--cmp410-customize-fg', value);
      }
      if (target === 'background_color') {
        root.style.setProperty('--cmp410-background', value);
        root.style.setProperty('--cmp410-surface', value);
      }
      if (target === 'text_color') {
        root.style.setProperty('--cmp410-foreground', value);
        root.style.setProperty('--cmp410-text', value);
      }

      root.querySelectorAll('[data-preview="' + target + '"]').forEach(function (el) {
        el.style.backgroundColor = value;
      });
    });
  }

  function syncColor(target, value, source) {
    document.querySelectorAll('[data-target="' + target + '"]').forEach(function (el) {
      if (el !== source) {
        el.value = value;
      }
    });
    updatePreviewColor(target, value);
  }

  colorPickers.forEach(function (input) {
    input.addEventListener('input', function () {
      syncColor(this.dataset.target, this.value, this);
    });
  });

  colorInputs.forEach(function (input) {
    input.addEventListener('input', function () {
      if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
        syncColor(this.dataset.target, this.value, this);
      }
    });
  });

  bindInputs.forEach(function (input) {
    input.addEventListener('input', function () {
      const selector = '[data-preview-bind="' + this.dataset.previewBind + '"]';
      document.querySelectorAll(selector).forEach(function (el) {
        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
          return;
        }
        el.textContent = input.value;
      });
    });
  });

  const trackingSelect = document.querySelector('select[name="cmp410gone_settings[tracking_mode]"]');
  const trackingLegends = document.querySelectorAll('.cmp410-tracking-legend');

  function updateTrackingLegend() {
    if (!trackingSelect || !trackingLegends.length) {
      return;
    }
    trackingLegends.forEach(function (legend) {
      legend.classList.toggle('is-active', legend.id === trackingSelect.value);
    });
  }

  if (trackingSelect) {
    trackingSelect.addEventListener('change', updateTrackingLegend);
    updateTrackingLegend();
  }
});
