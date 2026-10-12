(function () {
  'use strict';
  var messages = window.astridView;

  function initialize(root) {
    if (root.dataset.astridReady) return;
    root.dataset.astridReady = '1';
    var mode = root.dataset.astrid;
    var panel = root.querySelector('.astrid-panel');
    var form = root.querySelector('.astrid-form');
    if (!panel || !form) return;
    var trigger = root.querySelector('.astrid-trigger');
    var select = root.querySelector('select[name="option"]');
    var optionStatus = root.querySelector('.astrid-option-status');
    var status = form.querySelector('.astrid-status');
    var fields = form.querySelector('.astrid-form-fields');
    var success = form.querySelector('.astrid-success');
    var submit = form.querySelector('.astrid-submit');
    var originalLabel = submit.textContent;
    var pending = false;
    var activity = root.dataset.activity;
    form.noValidate = true;

    function updateText() {
      var option = select && select.value !== '' ? select.options[select.selectedIndex].textContent : '';
      root.querySelectorAll('[data-astrid-template]').forEach(function (element) {
        element.textContent = element.dataset.astridTemplate.replace(/\{activity\}/g, function () { return activity; }).replace(/\{option\}/g, function () { return option; });
      });
      root.querySelectorAll('[data-astrid-context="option"]').forEach(function (element) { element.textContent = option; });
    }

    function optionIsValid() {
      if (!select || select.value !== '') return true;
      select.setAttribute('aria-invalid', 'true');
      optionStatus.textContent = messages.optionError;
      select.focus();
      return false;
    }

    function firstField() { return fields.querySelector('input[name="name"]'); }

    function reset() {
      var chosen = select ? select.value : '';
      form.reset();
      if (select) select.value = chosen;
      fields.hidden = false;
      success.hidden = true;
      status.textContent = '';
      status.classList.remove('is-error');
      form.querySelectorAll('[aria-invalid]').forEach(function (input) { input.removeAttribute('aria-invalid'); });
      submit.textContent = originalLabel;
      updateText();
    }

    function open() {
      if (!optionIsValid()) return;
      if (!pending && !success.hidden) reset();
      updateText();
      if (mode === 'popup') {
        if (typeof panel.showModal === 'function') {
          if (!panel.open) panel.showModal();
        } else {
          panel.setAttribute('open', '');
          panel.classList.add('astrid-dialog-fallback');
          panel.setAttribute('role', 'dialog');
          panel.setAttribute('aria-modal', 'true');
        }
      } else {
        panel.hidden = false;
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
      }
      if (!pending) firstField().focus();
    }

    function close() {
      if (mode === 'popup') {
        if (typeof panel.close === 'function') panel.close();
        else panel.removeAttribute('open');
      } else if (mode === 'below') {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
      }
      if (trigger) trigger.focus();
    }

    if (trigger) trigger.addEventListener('click', function () {
      if (mode === 'below' && !panel.hidden) close(); else open();
    });
    if (select) select.addEventListener('change', function () {
      select.removeAttribute('aria-invalid');
      optionStatus.textContent = '';
      if (!success.hidden) reset();
      updateText();
    });
    if (mode === 'popup') {
      panel.querySelector('.astrid-close').addEventListener('click', close);
      panel.addEventListener('cancel', function (event) { event.preventDefault(); close(); });
      panel.addEventListener('click', function (event) {
        if (event.target !== panel) return;
        var rect = panel.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) close();
      });
      // Native dialog traps focus. Also support browsers without showModal().
      panel.addEventListener('keydown', function (event) {
        if (!panel.classList.contains('astrid-dialog-fallback')) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key !== 'Tab') return;
        var focusable = Array.from(panel.querySelectorAll('button, input, textarea, a[href]')).filter(function (element) { return !element.disabled && element.tabIndex !== -1 && element.getClientRects().length; });
        var first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
      });
    }
    form.querySelector('.astrid-restart').addEventListener('click', function () { reset(); firstField().focus(); });
    form.addEventListener('input', function (event) { event.target.removeAttribute('aria-invalid'); });
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (pending || !optionIsValid()) return;
      status.textContent = '';
      status.classList.remove('is-error');
      var firstInvalid = null;
      Array.from(fields.querySelectorAll('[required]')).forEach(function (input) {
        var valid = input.type === 'checkbox' ? input.checked : input.value.trim() !== '' && input.checkValidity();
        input.toggleAttribute('aria-invalid', !valid);
        if (!valid) { input.setAttribute('aria-invalid', 'true'); if (!firstInvalid) firstInvalid = input; }
      });
      if (firstInvalid) {
        status.textContent = messages.validationError;
        status.classList.add('is-error');
        firstInvalid.focus();
        return;
      }
      var data = new FormData(form);
      pending = true;
      submit.disabled = true;
      submit.textContent = messages.sending;
      form.setAttribute('aria-busy', 'true');
      if (select) select.disabled = true;
      if (trigger) trigger.disabled = true;
      var controller = typeof AbortController === 'function' ? new AbortController() : null;
      var timeout = controller ? window.setTimeout(function () { controller.abort(); }, 30000) : null;
      fetch(messages.endpoint, { method: 'POST', credentials: 'same-origin', body: data, signal: controller ? controller.signal : undefined })
        .then(function (response) {
          return response.json().then(function (result) {
            if (!response.ok || !result.success) throw new Error(result.message || messages.error);
            return result;
          });
        })
        .then(function () {
          fields.hidden = true;
          success.hidden = false;
          status.textContent = '';
          if (mode !== 'popup' || panel.open) success.focus();
        })
        .catch(function (error) {
          status.textContent = error instanceof TypeError || error.name === 'AbortError' ? messages.connectionError : error.message || messages.error;
          status.classList.add('is-error');
        })
        .finally(function () {
          if (timeout !== null) window.clearTimeout(timeout);
          pending = false;
          submit.disabled = false;
          submit.textContent = originalLabel;
          form.removeAttribute('aria-busy');
          if (select) select.disabled = false;
          if (trigger) trigger.disabled = false;
        });
    });
    updateText();
  }

  function start() { document.querySelectorAll('[data-astrid]').forEach(initialize); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
