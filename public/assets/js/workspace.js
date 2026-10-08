import {icon, hydrateIcons} from './workspace-icons.js';
import {cloneView, bindText} from './views.js';
import {dashboardSummary, formatNumber, greeting} from './workspace-data.js';
import {isStyled} from './styling.js';

// This adapter changes presentation, never roles, request payloads or file access.
const meta = document.querySelector('meta[name="pk-styling"]');
let config = {};
try { config = JSON.parse(meta?.content || '{}'); } catch { /* Native HTML fallback. */ }
let user = null;
let modules = [];
let dashboardData = null;
let scheduled = false;
let menuInitialized = false;
let theme = 'light';
try { theme = localStorage.getItem('pk.workspace.mode') === 'dark' ? 'dark' : 'light'; } catch { /* Storage may be blocked. */ }
const root = () => document.querySelector('#content');
const current = () => document.documentElement.dataset.pkModule || 'account';
const text = (selector, value) => {
  const node = document.querySelector(selector);
  if (node && node.textContent !== String(value)) node.textContent = String(value);
};
function storage(action, key, value) {
  try { return localStorage[action](key, value); } catch { return null; }
}
function navigate(key, searchValue = '') {
  const label = key === 'dashboard' ? 'Dashboard' : modules.find(module => module.key === key)?.label;
  const target = [...document.querySelectorAll('#navigation button')].find(button => button.textContent.trim() === label);
  if (!target) return;
  const accountMenu = document.querySelector('#pk-account-menu');
  if (accountMenu) accountMenu.open = false;
  target.click();
  if (searchValue) {
    // Wait for the existing list UI, then use its real search submission.
    const applySearch = () => {
      if (current() !== key) return;
      const search = root()?.querySelector('input[type="search"]');
      if (search?.form) {
        search.value = searchValue;
        search.form.requestSubmit();
        return true;
      }
    };
    requestAnimationFrame(() => {
      if (applySearch()) return;
      const observer = new MutationObserver(() => { if (applySearch() || current() !== key) observer.disconnect(); });
      if (root()) observer.observe(root(), {childList:true, subtree:true});
      setTimeout(() => observer.disconnect(), 3000);
    });
  }
}
function ensureStyles() {
  const needed = isStyled(current(), config) || !!document.querySelector('dialog.pk-ui[open]');
  let link = document.querySelector('link[data-pk-reference]');
  if (!needed) { link?.remove(); return; }
  if (link) return;
  const value = config.reference_stylesheet;
  if (!value) return;
  const url = new URL(value, document.baseURI);
  if (url.origin !== location.origin) return;
  link = cloneView('stylesheet-link-template');
  link.rel = 'stylesheet'; link.href = url.href; link.dataset.pkReference = '';
  document.head.append(link);
}
function updateIdentity() {
  const name = user ? [user.first_name,user.last_name].filter(Boolean).join(' ') || user.username : 'Account';
  const role = user?.position_title || '';
  text('#ws-account-name', name); text('#ws-sidebar-name', name);
  text('#ws-account-role', role); text('#ws-sidebar-role', role);
  document.body.dataset.wsMode = theme;
  const mode = document.querySelector('#ws-mode-toggle');
  mode?.setAttribute('aria-pressed', String(theme === 'dark'));
  text('#ws-mode-label', theme === 'dark' ? 'Light mode' : 'Dark mode');
  if (mode && mode.dataset.mode !== theme) {
    mode.querySelector('svg')?.remove(); mode.prepend(icon(theme === 'dark' ? 'sun' : 'moon')); mode.dataset.mode = theme;
  }
  const module = current();
  const label = module === 'dashboard' ? 'Dashboard' : modules.find(item => item.key === module)?.label || 'Document workspace';
  if (document.body.hasAttribute('data-pk-shell')) {
    text('#ws-page-title', label);
    text('#ws-page-description', module === 'dashboard' ? 'Track the system summary, recent activity, and key counts at a glance.' : 'Manage your records and actions in one secure workspace.');
    if (!menuInitialized && user) {
      const menu = document.querySelector('#pk-account-menu'); if (menu) menu.open = false;
      menuInitialized = true;
    }
  }
  const globalStatus = document.querySelector('#global-status');
  globalStatus?.toggleAttribute('data-ws-ready', globalStatus.textContent.trim() === 'Ready.');
}
function decorateNavigation() {
  if (!document.body.hasAttribute('data-pk-shell')) return;
  for (const node of document.querySelectorAll('#navigation > button, #navigation summary')) {
    if (node.dataset.wsIcon) continue;
    const title = node.textContent.trim();
    const symbol = title === 'Dashboard' ? 'home' : /Admin/i.test(title) ? 'grid' : /System/i.test(title) ? 'grid' : /Document|Workspace/i.test(title) ? 'folder' : 'grid';
    node.prepend(icon(symbol)); node.dataset.wsIcon = symbol;
  }
  for (const group of document.querySelectorAll('#navigation > details')) {
    if (group.dataset.wsInitialized) continue;
    group.open = false;
    group.dataset.wsInitialized = 'true';
  }
  const selected = document.querySelector('#navigation button[aria-current="page"]');
  if (selected?.closest('details') && !selected.closest('details').open) selected.closest('details').open = true;
}
function renderDashboard() {
  const container = root();
  if (!container || current() !== 'dashboard' || !isStyled('dashboard',config) || !dashboardData) return;
  // Native content stays available when this module's design is switched off.
  for (const child of [...container.children]) {
    if (child.classList.contains('ws-dashboard')) child.remove();
    else { child.hidden = true; child.dataset.wsNative = ''; }
  }
  const summary = dashboardSummary(dashboardData);
  const view = cloneView('dashboard-workspace-template');
  hydrateIcons(view);
  const now = new Date();
  bindText(view, 'greeting', greeting(now));
  bindText(view, 'welcome', `Welcome back, ${user?.first_name || 'there'}.`);
  bindText(view, 'date', new Intl.DateTimeFormat('en-US', {weekday:'long',month:'long',day:'numeric'}).format(now));
  for (const key of ['total', 'active', 'pending', 'hardcopy', 'softcopy', 'disposed']) {
    bindText(view, key, formatNumber(summary[key]));
  }
  bindText(view, 'total-copy', formatNumber(summary.total));
  bindText(view, 'percent-copy', `${summary.percent}% of your records`);
  bindText(view, 'percent', summary.percent + '%');

  const documentModule = modules.find(item => item.key === 'softcopy') ? 'softcopy' : 'hardcopy';
  for (const button of view.querySelectorAll('[data-nav]')) {
    const key = button.dataset.nav === 'documents' ? documentModule : button.dataset.nav;
    if (!modules.some(module => module.key === key && !module.navigation_hidden)) {
      button.remove();
    } else button.addEventListener('click', () => navigate(key));
  }
  view.querySelector('[data-dashboard-refresh]').addEventListener('click', () => navigate('dashboard'));
  const chart = view.querySelector('[data-library-chart]');
  chart.setAttribute('aria-label', `${summary.percent}% of your visible documents are active. ${summary.hardcopy} hardcopies, ${summary.softcopy} softcopies, ${summary.disposed} disposed.`);
  let offset = 0;
  for (const key of ['hardcopy', 'softcopy', 'disposed']) {
    const size = summary.total ? summary[key] / summary.total * 100 : 0;
    const circle = chart.querySelector('.ws-ring-' + key);
    circle.setAttribute('stroke-dasharray', `${size} ${100-size}`);
    circle.setAttribute('stroke-dashoffset', String(-offset));
    offset += size;
  }

  const latest = view.querySelector('[data-recent-documents]');
  const rows = Array.isArray(dashboardData.recent_documents) ? dashboardData.recent_documents.slice(0,5) : [];
  if (!rows.length) {
    const empty = cloneView('workspace-empty-template');
    hydrateIcons(empty);
    latest.append(empty);
  }
  for (const row of rows) {
    const kind = row.domain === 'hardcopy' ? 'hardcopy' : 'softcopy';
    const item = cloneView('workspace-document-template');
    const symbol = item.querySelector('[data-document-icon]');
    symbol.dataset.domain = kind;
    symbol.append(icon(kind === 'hardcopy' ? 'box' : 'file'));
    bindText(item, 'title', row.title || 'Document');
    bindText(item, 'reference', row.document_number || (kind === 'hardcopy' ? 'Hardcopy record' : 'Softcopy record'));
    bindText(item, 'status', String(row.status || '').replaceAll('_', ' '));
    const date = row.created_at ? new Date(String(row.created_at).replace(' ', 'T')) : null;
    bindText(item, 'date', date && !Number.isNaN(date.getTime())
      ? new Intl.DateTimeFormat('en-US', {month:'short',day:'numeric'}).format(date) : '');
    item.setAttribute('aria-label', 'Find ' + (row.title || 'document') + ' in ' + kind + ' documents');
    item.addEventListener('click', () => navigate(kind, row.document_number || row.title || ''));
    latest.append(item);
  }
  container.append(view);
  const unread = Math.max(0, Number(dashboardData.unread_notifications) || 0);
  text('#ws-notification-count', unread ? formatNumber(unread) : '');
}

function decorateLogin(dialog) {
  if (!isStyled('account',config) || dialog.querySelector('[data-dialog-title]')?.textContent.trim() !== 'Sign in') return;
  dialog.dataset.pkModule = 'account';
  if (!dialog.classList.contains('pk-login')) {
    dialog.classList.add('pk-login');
    const form = dialog.querySelector('form');
    const title = dialog.querySelector('[data-dialog-title]');
    dialog.querySelector('[data-dialog-explanation]').hidden = true;
    const scene = cloneView('login-workspace-template');
    hydrateIcons(scene);
    scene.querySelector('[data-login-title-slot]').replaceWith(title);
    scene.querySelector('[data-login-form-slot]').replaceWith(form);
    dialog.append(scene);
    dialog.addEventListener('cancel', event => {
      if (dialog.classList.contains('pk-login') && isStyled('account',config)) event.preventDefault();
    });
  }
  const submit = dialog.querySelector('button[type="submit"]');
  if (submit && !submit.querySelector('svg')) submit.prepend(icon('arrow'));
  const username = dialog.querySelector('input[name="username"]');
  const password = dialog.querySelector('input[name="password"]');
  if (!username || !password || username.dataset.wsReady) return;
  username.dataset.wsReady = 'true';
  username.autocomplete = 'username'; username.placeholder = 'Enter your username';
  password.autocomplete = 'current-password'; password.placeholder = 'Enter your password';
  const reveal = cloneView('password-visibility-template');
  hydrateIcons(reveal);
  reveal.addEventListener('click', () => {
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    reveal.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    reveal.setAttribute('aria-pressed', String(show));
  });
  password.parentElement.append(reveal);
  const row = cloneView('remember-username-template');
  const remember = row.querySelector('input');
  remember.id = username.id + '-remember'; row.htmlFor = remember.id;
  const saved = storage('getItem','pk.workspace.username');
  if (saved) { username.value = saved; remember.checked = true; }
  dialog.querySelector('fieldset').append(row);
  dialog.querySelector('form').addEventListener('submit', () => {
    if (remember.checked) storage('setItem','pk.workspace.username',username.value.trim());
    else storage('removeItem','pk.workspace.username');
  });
}

function synchronize() {
  scheduled = false;
  if (!meta) return;
  ensureStyles(); updateIdentity(); decorateNavigation();
  for (const dialog of document.querySelectorAll('dialog[open]')) decorateLogin(dialog);
  if (!isStyled(current(),config)) {
    root()?.querySelector('.ws-dashboard')?.remove();
    for (const native of root()?.querySelectorAll('[data-ws-native]') || []) { native.hidden = false; delete native.dataset.wsNative; }
  }
}
function schedule() { if (!scheduled) { scheduled = true; requestAnimationFrame(synchronize); } }
if (meta) {
  document.addEventListener('pk:session',event => {
    user = event.detail?.user || null;
    if (!user) { dashboardData = null; menuInitialized = false; }
    schedule();
  });
  document.addEventListener('pk:metadata',event => {
    modules = event.detail?.modules || [];
    user = event.detail?.user || user; schedule();
  });
  document.addEventListener('pk:dashboard',event => {
    dashboardData = event.detail;
    requestAnimationFrame(() => { renderDashboard(); synchronize(); });
  });
  document.addEventListener('pk:screen',schedule);
  document.querySelector('#ws-mode-toggle')?.addEventListener('click',() => {
    theme = theme === 'dark' ? 'light' : 'dark'; storage('setItem','pk.workspace.mode',theme); updateIdentity();
  });
  document.querySelector('#ws-notifications')?.addEventListener('click',() => navigate('notifications'));
  document.querySelector('#ws-sign-out')?.addEventListener('click',() => {
    [...document.querySelectorAll('#account-actions button')].find(button => button.textContent.trim() === 'Sign out')?.click();
  });
  document.addEventListener('click',event => {
    if (!event.target.closest?.('#pk-account-menu')) {
      const menu = document.querySelector('#pk-account-menu');
      if (menu && document.body.hasAttribute('data-pk-shell')) menu.open = false;
    }
  });
  new MutationObserver(schedule).observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['open','data-pk-shell']});
  schedule();
}
