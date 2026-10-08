import { cloneView } from './views.js';

// Presentation only. Walay workflow/permission rules in this module.
const meta = document.querySelector('meta[name=pk-styling]');
let config = {};
try {
  config = JSON.parse(meta?.content || '{}');
} catch {
  // Invalid configuration falls back to the usable native HTML interface.
}

export function isStyled(module, options = config) {
  if (options.enabled !== true) return false;
  const modules = options.modules || {};
  return Object.prototype.hasOwnProperty.call(modules, module)
    ? modules[module] === true
    : options.default_enabled === true;
}

if (!config || typeof config !== 'object' || Array.isArray(config)) config = {};

let currentModule = document.documentElement.dataset.pkModule ||
  document.querySelector('meta[name=initial-module]')?.content || 'account';
let openingModule = currentModule;
const moduleLabels = new Map([['dashboard', 'Dashboard']]);
const roots = ['#pk-header', '#navigation', '#global-status', '#content'];
let frame = 0;
let failedStylesheet = false;

function stylesheet(needed) {
  const existing = document.querySelector('link[data-pk-styles]');
  if (!needed) {
    existing?.remove();
    return;
  }
  if (existing || failedStylesheet || !config.stylesheet) return;
  const url = new URL(config.stylesheet, document.baseURI);
  if (url.origin !== location.origin || !['http:', 'https:', 'blob:'].includes(url.protocol)) return;
  const link = cloneView('stylesheet-link-template');
  link.rel = 'stylesheet';
  link.href = url.href;
  link.dataset.pkStyles = 'red';
  link.addEventListener('error', () => {
    failedStylesheet = true;
    config.enabled = false;
    apply();
  });
  document.head.append(link);
}

function scope(root, module, enabled = isStyled(module)) {
  if (!root) return;
  root.dataset.pkModule = module;
  root.classList.toggle('pk-ui', enabled);
}

function closeMenu(restoreFocus = false) {
  document.body.removeAttribute('data-pk-menu-open');
  const toggle = document.querySelector('#pk-menu-toggle');
  toggle?.setAttribute('aria-expanded', 'false');
  if (restoreFocus && toggle && !toggle.hidden) toggle.focus();
}

function decorate(root) {
  if (!root?.classList.contains('pk-ui')) return;
  for (const button of root.querySelectorAll('button')) {
    if (button.closest('#navigation, #account-actions')) continue;
    const title = button.textContent.trim();
    const destructive = /^(Reject|Delete|Remove|Dispose)/i.test(title);
    button.dataset.pkIntent = destructive ? 'danger' :
      (button.type === 'submit' && button.closest('form')) || /^(New |Add |Save |Submit |Grant access)/i.test(title)
        ? 'primary' : 'secondary';
  }
  for (const cell of root.querySelectorAll('td')) {
    const status = cell.textContent.trim().toLowerCase();
    if (/^(active|approved|completed|pending|returned|rejected|cancelled|disposed|draft|access_granted)$/.test(status)) {
      cell.dataset.pkStatus = status;
    }
  }
  for (const scroll of root.querySelectorAll('#table-container')) {
    if (!scroll.hasAttribute('tabindex')) {
      scroll.tabIndex = 0;
      scroll.setAttribute('role', 'region');
      scroll.setAttribute('aria-label', 'Records table');
    }
  }
}

function apply() {
  frame = 0;
  const styled = isStyled(currentModule);
  const shell = styled && config.shell === true;
  document.body.toggleAttribute('data-pk-shell', shell);
  if (!shell) closeMenu();
  roots.forEach(selector => {
    scope(document.querySelector(selector), currentModule,
      selector === '#content' ? styled : shell);
  });
  const toggle = document.querySelector('#pk-menu-toggle');
  if (toggle) toggle.hidden = !shell;
  document.querySelectorAll('[data-pk-decoration]').forEach(node => {
    node.hidden = !shell;
  });
  const label = moduleLabels.get(currentModule) || currentModule.replaceAll('_', ' ');
  const breadcrumb = document.querySelector('#pk-current-section');
  if (breadcrumb && breadcrumb.textContent !== label) breadcrumb.textContent = label;
  for (const button of document.querySelectorAll('#navigation button')) {
    const key = [...moduleLabels].find(([, title]) => title === button.textContent.trim())?.[0];
    if (key === currentModule) button.setAttribute('aria-current', 'page');
    else button.removeAttribute('aria-current');
  }
  for (const dialog of document.querySelectorAll('dialog[open]')) {
    const owner = dialog.dataset.pkModule || openingModule;
    scope(dialog, owner);
    decorate(dialog);
  }
  roots.forEach(selector => decorate(document.querySelector(selector)));
  stylesheet(styled || !!document.querySelector('dialog[open].pk-ui'));
}

function queueApply() {
  if (!frame) frame = requestAnimationFrame(apply);
}

if (meta) {
  document.addEventListener('pk:screen', event => {
    currentModule = String(event.detail?.module || 'account');
    openingModule = currentModule;
    closeMenu();
    apply();
  });
  document.addEventListener('pk:metadata', event => {
    for (const module of event.detail?.modules || []) {
      moduleLabels.set(module.key, module.label);
    }
    queueApply();
  });
  document.addEventListener('pk:detail', event => {
    const dialog = [...document.querySelectorAll('dialog[open]')].at(-1);
    if (dialog) scope(dialog, String(event.detail?.module || currentModule));
    queueApply();
  });
  document.addEventListener('click', event => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;
    openingModule = target.closest('#account-actions') ? 'account' :
      target.closest('dialog')?.dataset.pkModule || currentModule;
    if (target.closest('#pk-menu-toggle')) {
      const open = !document.body.hasAttribute('data-pk-menu-open');
      document.body.toggleAttribute('data-pk-menu-open', open);
      document.querySelector('#pk-menu-toggle')?.setAttribute('aria-expanded', String(open));
    } else if (target.closest('#navigation button')) {
      closeMenu();
    } else if (!target.closest('#navigation')) {
      closeMenu();
    }
  }, true);
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && document.body.hasAttribute('data-pk-menu-open')) {
      closeMenu(true);
    }
  });
  new MutationObserver(queueApply).observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['open']
  });
  apply();
}
