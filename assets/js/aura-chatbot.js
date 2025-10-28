/**
 * Aura Product Chatbot - Frontend (ES module)
 * Built for modern toolchains (ESM) and progressive enhancement.
 *
 * - Use a bundler (esbuild/rollup/webpack) to produce a WordPress-compatible bundle
 *   (iife/umd) if you need to support older browsers without modules.
 * - WordPress should localize script data to `window.AURA_CHATBOT_CONFIG`
 *   with { restUrl, nonce, i18n } before enqueuing.
 *
 * Exported API:
 *   init(container: HTMLElement, options?: object)
 *
 * Accessibility:
 *   - aria-live region for messages
 *   - keyboard support for input and open/close
 *
 * Example usage (after bundling or using as module):
 *   import AuraChatbot from './aura-chatbot.js';
 *   AuraChatbot.init(document.querySelector('#aura-chat'));
 */

const DEFAULT_OPTIONS = {
  restUrl: '',
  nonce: '',
  selectors: {
    form: '[data-aura-form]',
    input: '[data-aura-input]',
    submit: '[data-aura-submit]',
    messages: '[data-aura-messages]',
    openBtn: '[data-aura-open]',
    closeBtn: '[data-aura-close]',
    typingIndicator: '[data-aura-typing]',
  },
  i18n: {
    sending: 'Sending…',
    error: 'An error occurred. Please try again.',
    placeholder: 'Ask about this product...',
  },
  requestTimeout: 30000,
};

function createEl(tag, attrs = {}, text = '') {
  const el = document.createElement(tag);
  Object.entries(attrs).forEach(([k, v]) => {
    if (k === 'class') el.className = v;
    else if (k === 'type' || k === 'placeholder' || k === 'value') el.setAttribute(k, v);
    else if (k.startsWith('aria')) el.setAttribute(k, v);
    else if (k.startsWith('data-')) el.setAttribute(k, v);
  });
  if (text) el.textContent = text;
  return el;
}

function timeoutFetch(resource, options = {}, ms = 30000) {
  const controller = new AbortController();
  const id = setTimeout(() => controller.abort(), ms);
  const signal = controller.signal;
  return fetch(resource, { ...options, signal })
    .finally(() => clearTimeout(id));
}

function safeText(s) {
  const div = document.createElement('div');
  div.textContent = s;
  return div.innerHTML;
}

const AuraChatbot = (function () {
  let config = { ...DEFAULT_OPTIONS };
  let state = {
    container: null,
    elements: {},
  };

  function applyConfig(local) {
    config = deepMerge(config, local || {});
  }

  function deepMerge(a, b) {
    if (!b) return a;
    const out = { ...a };
    Object.keys(b).forEach((k) => {
      if (isObject(b[k]) && isObject(a[k])) out[k] = deepMerge(a[k], b[k]);
      else out[k] = b[k];
    });
    return out;
  }

  function isObject(x) {
    return x && typeof x === 'object' && !Array.isArray(x);
  }

  function renderMessage(container, message, type = 'bot') {
    const item = createEl('div', { class: `aura-msg aura-msg--${type}`, role: 'article' });
    const body = createEl('div', { class: 'aura-msg__body' });
    body.innerHTML = safeText(message);
    item.appendChild(body);
    container.appendChild(item);
    // Keep the latest message in view
    container.scrollTop = container.scrollHeight;
  }

  function showTyping(el, show = true) {
    if (!el) return;
    el.style.display = show ? '' : 'none';
  }

  async function sendMessage(payload) {
    const url = config.restUrl;
    if (!url) throw new Error('Missing restUrl in configuration');

    const headers = {
      'Content-Type': 'application/json',
    };
    if (config.nonce) headers['X-WP-Nonce'] = config.nonce;

    const res = await timeoutFetch(url, {
      method: 'POST',
      headers,
      body: JSON.stringify(payload),
    }, config.requestTimeout);

    if (!res.ok) {
      const text = await res.text().catch(() => '');
      throw new Error(`Network error ${res.status}: ${text}`);
    }
    return res.json();
  }

  function attachEvents() {
    const { form, input, submit, messages, typingIndicator, openBtn, closeBtn } = state.elements;

    if (!form) return;

    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const value = input.value.trim();
      if (!value) return;
      const userMsg = value;
      input.value = '';
      renderMessage(messages, userMsg, 'user');
      showTyping(typingIndicator, true);

      try {
        const response = await sendMessage({ prompt: userMsg, context: getContext() });
        showTyping(typingIndicator, false);
        if (response && response.reply) {
          renderMessage(messages, response.reply, 'bot');
        } else {
          renderMessage(messages, config.i18n.error, 'error');
        }
      } catch (err) {
        showTyping(typingIndicator, false);
        renderMessage(messages, config.i18n.error, 'error');
        console.error(err);
      }
    });

    // Accessibility: submit on Enter, allow Shift+Enter for newline
    if (input) {
      input.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter' && !ev.shiftKey) {
          ev.preventDefault();
          if (submit) submit.click();
        }
      });
    }

    if (openBtn) openBtn.addEventListener('click', () => state.container.classList.add('aura--open'));
    if (closeBtn) closeBtn.addEventListener('click', () => state.container.classList.remove('aura--open'));
  }

  function getContext() {
    // Lightweight context passed to the API: page/product metadata from data attributes
    const ctx = {};
    const el = state.container;
    if (!el) return ctx;
    Array.from(el.attributes).forEach((attr) => {
      if (attr.name.startsWith('data-ctx-')) {
        const key = attr.name.replace('data-ctx-', '').replace(/-([a-z])/g, (_, c) => c.toUpperCase());
        ctx[key] = attr.value;
      }
    });
    return ctx;
  }

  function findElements(container) {
    const s = config.selectors;
    return {
      form: container.querySelector(s.form),
      input: container.querySelector(s.input),
      submit: container.querySelector(s.submit),
      messages: container.querySelector(s.messages),
      typingIndicator: container.querySelector(s.typingIndicator),
      openBtn: container.querySelector(s.openBtn),
      closeBtn: container.querySelector(s.closeBtn),
    };
  }

  function renderSkeleton(container) {
    // Only render if missing expected structure (progressive enhancement)
    if (!container.querySelector(config.selectors.messages)) {
      const messages = createEl('div', { class: 'aura-messages', 'data-aura-messages': true, 'aria-live': 'polite', role: 'log' });
      container.appendChild(messages);
    }
    if (!container.querySelector(config.selectors.form)) {
      const form = createEl('form', { class: 'aura-form', 'data-aura-form': true });
      const input = createEl('textarea', { class: 'aura-input', 'data-aura-input': true, placeholder: config.i18n.placeholder });
      const submit = createEl('button', { type: 'submit', class: 'aura-submit', 'data-aura-submit': true }, config.i18n.sending);
      form.appendChild(input);
      form.appendChild(submit);
      container.appendChild(form);
    }
    if (!container.querySelector(config.selectors.typingIndicator)) {
      const tip = createEl('div', { class: 'aura-typing', 'data-aura-typing': true, 'aria-hidden': 'true' }, '');
      container.appendChild(tip);
    }
  }

  function init(containerSelectorOrEl, localConfig = {}) {
    const container = typeof containerSelectorOrEl === 'string'
      ? document.querySelector(containerSelectorOrEl)
      : containerSelectorOrEl;

    if (!container) {
      throw new Error('AuraChatbot: container not found');
    }

    applyConfig(window.AURA_CHATBOT_CONFIG || {});
    applyConfig(localConfig);

    state.container = container;
    renderSkeleton(container);
    state.elements = findElements(container);

    // Ensure messages container exists
    if (!state.elements.messages) {
      state.elements.messages = container.querySelector(config.selectors.messages) || container.appendChild(createEl('div', { class: 'aura-messages', 'data-aura-messages': true }));
    }

    // Set placeholder localized strings if provided
    if (state.elements.input && config.i18n && config.i18n.placeholder) {
      state.elements.input.placeholder = config.i18n.placeholder;
    }

    attachEvents();
    return {
      send: (text) => {
        renderMessage(state.elements.messages, text, 'user');
        return sendMessage({ prompt: text, context: getContext() })
          .then((res) => {
            if (res && res.reply) renderMessage(state.elements.messages, res.reply, 'bot');
            return res;
          });
      },
      container,
      config,
    };
  }

  // Backwards-compatible global attach for classic WP enqueue setups
  function autoInit(selector = '[data-aura-chat]') {
    document.querySelectorAll(selector).forEach((el) => {
      try { init(el); } catch (e) { /* fail silently per-instance */ }
    });
  }

  return {
    init,
    autoInit,
  };
}());

// UMD-ish fallback so the bundled script works without module support.
// If using bundlers, this will be replaced/removed by the bundle config.
if (typeof window !== 'undefined') {
  window.AuraChatbot = window.AuraChatbot || AuraChatbot;
}

export default AuraChatbot;
