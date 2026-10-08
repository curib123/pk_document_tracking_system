import {icon} from './workspace-icons.js';
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
const asset = filename => new URL('../images/' + filename, import.meta.url).href;
const root = () => document.querySelector('#content');
const current = () => document.documentElement.dataset.pkModule || 'account';
const text = (selector, value) => {
  const node = document.querySelector(selector);
  if (node && node.textContent !== String(value)) node.textContent = String(value);
};
function element(tag, className = '', ...children) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  for (const child of children.flat()) if (child != null) node.append(child instanceof Node ? child : document.createTextNode(String(child)));
  return node;
}
function action(title, symbol, callback, className = '') {
  const node = element('button', className, symbol ? icon(symbol) : null, element('span','',title));
  node.type = 'button';
  node.addEventListener('click', callback);
  return node;
}
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
function buttonFor(key, title, symbol, className = '') {
  return (key === 'dashboard' || modules.some(module => module.key === key && !module.navigation_hidden))
    ? action(title, symbol, () => navigate(key), className) : null;
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
  link = document.createElement('link');
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
function ring(summary) {
  const chart = element('div','ws-donut');
  const ns = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(ns,'svg'); svg.setAttribute('viewBox','0 0 120 120'); svg.setAttribute('aria-hidden','true');
  let offset = 0;
  for (const [key,value] of [['track',summary.total || 1],['hardcopy',summary.hardcopy],['softcopy',summary.softcopy],['disposed',summary.disposed]]) {
    const circle = document.createElementNS(ns,'circle');
    circle.setAttribute('cx','60'); circle.setAttribute('cy','60'); circle.setAttribute('r','52'); circle.setAttribute('pathLength','100'); circle.setAttribute('class','ws-ring-' + key);
    if (key !== 'track') {
      const size = summary.total ? value / summary.total * 100 : 0;
      circle.setAttribute('stroke-dasharray',`${size} ${100-size}`); circle.setAttribute('stroke-dashoffset',String(-offset)); offset += size;
    }
    svg.append(circle);
  }
  chart.append(svg, element('div','ws-donut-label',element('strong','',summary.percent + '%'),element('small','','active')));
  chart.setAttribute('role','img'); chart.setAttribute('aria-label',`${summary.percent}% of your visible documents are active. ${summary.hardcopy} hardcopies, ${summary.softcopy} softcopies, ${summary.disposed} disposed.`);
  return chart;
}
function renderDashboard() {
  const container = root();
  if (!container || current() !== 'dashboard' || !isStyled('dashboard',config) || !dashboardData) return;
  // Core app continues to own loading/error/permission behaviour and plain HTML.
  for (const child of [...container.children]) {
    if (child.classList.contains('ws-dashboard')) child.remove();
    else { child.hidden = true; child.dataset.wsNative = ''; }
  }
  const summary = dashboardSummary(dashboardData);
  const view = element('div','ws-dashboard');
  const now = new Date();
  const welcome = element('section','ws-welcome',element('span','ws-welcome-icon',icon('sparkle')),
    element('div','ws-welcome-content',element('span','ws-eyebrow',greeting(now)),
      element('h3','',`Welcome back, ${user?.first_name || 'there'}.`),
      element('p','',`You have ${formatNumber(summary.total)} document records available in your workspace.`),
      element('div','ws-date',icon('calendar'),new Intl.DateTimeFormat('en-US',{weekday:'long',month:'long',day:'numeric'}).format(now))));
  const documentModule = modules.find(item => item.key === 'softcopy') ? 'softcopy' : 'hardcopy';
  const refresh = action('Refresh dashboard','refresh',() => navigate('dashboard'),'ws-refresh');
  refresh.setAttribute('aria-label','Refresh dashboard'); refresh.querySelector('span').hidden = true;
  welcome.append(element('div','ws-welcome-actions',buttonFor(documentModule,'Search documents','search','ws-primary'),refresh));
  const featured = element('section','ws-kpi ws-kpi-featured',element('span','ws-kpi-icon',icon('folder')),element('div','',element('small','','Documents available to you'),element('strong','',formatNumber(summary.total))));
  const active = element('section','ws-kpi',element('small','','Active documents'),element('strong','',formatNumber(summary.active)),element('span','',icon('check'),`${summary.percent}% of your records`));
  const pending = element('section','ws-kpi',element('small','','My requests in workflow'),element('strong','',formatNumber(summary.pending)),element('span','',icon('clock'),'Awaiting approval'));
  const shortcuts = element('nav','ws-quicklinks',buttonFor(documentModule,'Documents','file'),buttonFor('my_requests','My requests','send'),buttonFor('locations','Storage','storage'),buttonFor('users','Users','users'));
  shortcuts.setAttribute('aria-label','Dashboard shortcuts');
  const library = element('section','ws-panel',element('div','ws-panel-heading',element('div','',element('span','ws-eyebrow','Overview'),element('h3','','Document library')),element('small','','Live records')));
  const legend = element('div','ws-legend');
  for (const key of ['hardcopy','softcopy','disposed']) legend.append(element('div','',element('span','ws-dot ws-dot-'+key),key[0].toUpperCase()+key.slice(1),element('b','',formatNumber(summary[key]))));
  library.append(element('div','ws-library',ring(summary),legend));
  const recent = element('section','ws-panel',element('div','ws-panel-heading',element('div','',element('span','ws-eyebrow','Recent'),element('h3','','Latest documents')),buttonFor(documentModule,'View all','arrow','ws-text-link')));
  const latest = element('div','ws-latest-list');
  const rows = Array.isArray(dashboardData.recent_documents) ? dashboardData.recent_documents.slice(0,5) : [];
  if (!rows.length) latest.append(element('div','ws-empty',icon('folder'),'No documents available yet.',element('br'),'Assigned and granted documents will appear here.'));
  for (const row of rows) {
    const kind = row.domain === 'hardcopy' ? 'hardcopy' : 'softcopy';
    const symbol = element('span','ws-document-icon',icon(kind === 'hardcopy' ? 'box' : 'file')); symbol.dataset.domain = kind;
    const date = row.created_at ? new Date(String(row.created_at).replace(' ','T')) : null;
    const dateLabel = date && !Number.isNaN(date.getTime()) ? new Intl.DateTimeFormat('en-US',{month:'short',day:'numeric'}).format(date) : '';
    const item = element('button','ws-document-row',symbol,
      element('span','ws-document-title',element('strong','',row.title || 'Document'),element('small','',row.document_number || (kind === 'hardcopy' ? 'Hardcopy record' : 'Softcopy record'))),
      element('span','ws-document-status',element('b','',String(row.status || '').replaceAll('_',' ')),element('small','',dateLabel)));
    item.type = 'button'; item.setAttribute('aria-label','Find '+(row.title || 'document')+' in '+kind+' documents');
    item.addEventListener('click',() => navigate(kind,row.document_number || row.title || ''));
    latest.append(item);
  }
  recent.append(latest);
  view.append(welcome,element('div','ws-kpis',featured,active,pending),shortcuts,element('div','ws-overview',library,recent));
  container.append(view);
  const unread = Math.max(0,Number(dashboardData.unread_notifications)||0);
  text('#ws-notification-count',unread ? formatNumber(unread) : '');
}
function loginBrand() {
  const image = element('img'); image.src = asset('peanut-kisses.webp'); image.alt = 'Peanut Kisses';
  return element('div','ws-login-brand',image,element('span','',element('small','','Records workspace'),element('b','','DTS')));
}
function decorateLogin(dialog) {
  if (!isStyled('account',config) || dialog.querySelector('[data-dialog-title]')?.textContent.trim() !== 'Sign in') return;
  dialog.dataset.pkModule = 'account';
  if (!dialog.classList.contains('pk-login')) {
    dialog.classList.add('pk-login');
    const form = dialog.querySelector('form');
    const title = dialog.querySelector('[data-dialog-title]');
    const description = dialog.querySelector('[data-dialog-explanation]');
    description.hidden = true;
    const scene = element('div','ws-login-scene');
    scene.append(element('header','ws-login-topbar',loginBrand(),element('span','ws-secure-label',icon('shield'),'Authorized access only')));
    const hero = element('section','ws-login-hero',element('span','ws-login-pill','Document tracking system'),
      element('h2','','Secure access',element('br'),'for your',element('br'),'document',element('br'),'control center.'),
      element('p','','Manage the full document lifecycle from one secure workspace. This portal keeps records organized, routes access by role, and brings documents, storage, users, and permissions together in a clean experience.'));
    const features = element('div','ws-login-features');
    for (const [symbol,name,detail] of [['file','Track records','Softcopy and physical documents'],['pin','Locate faster','Mapped storage and file journeys'],['shield','Work securely','Role-based access and accountability']]) features.append(element('div','ws-login-feature',element('span','',icon(symbol)),element('div','',element('b','',name),element('small','',detail))));
    hero.append(features);
    const logo = element('img'); logo.src = asset('peanut-kisses.webp'); logo.alt = 'Peanut Kisses';
    const card = element('section','ws-login-card',title,element('p','ws-eyebrow','Staff workspace'),
      element('div','ws-login-card-heading',logo,element('div','',element('h3','','Welcome back'),element('p','','Use your username and password to continue.'))),form,
      element('div','ws-login-help',element('p','','Need an account? ',element('strong','','Contact your Document Control Officer.')),element('p','',icon('lock'),'Your activity is protected and recorded for document accountability.')));
    scene.append(element('div','ws-login-grid',hero,card),element('footer','ws-login-footer',element('span','','Document Tracking System (DTS)'),element('span','','Created by John Paul Curib, Full-stack Developer'),element('span','','Secure records · Clear ownership · Faster retrieval')));
    dialog.append(scene);
    dialog.addEventListener('cancel',event => { if (dialog.classList.contains('pk-login') && isStyled('account',config)) event.preventDefault(); });
  }
  const submit = dialog.querySelector('button[type="submit"]');
  if (submit && !submit.querySelector('svg')) submit.prepend(icon('arrow'));
  const username = dialog.querySelector('input[name="username"]');
  const password = dialog.querySelector('input[name="password"]');
  if (!username || !password || username.dataset.wsReady) return;
  username.dataset.wsReady = 'true'; username.autocomplete = 'username'; username.placeholder = 'Enter your username';
  password.autocomplete = 'current-password'; password.placeholder = 'Enter your password';
  const reveal = action('', 'eye', () => {
    const show = password.type === 'password'; password.type = show ? 'text' : 'password';
    reveal.setAttribute('aria-label', show ? 'Hide password' : 'Show password'); reveal.setAttribute('aria-pressed',String(show));
  },'ws-password-toggle');
  reveal.setAttribute('aria-label','Show password'); reveal.setAttribute('aria-pressed','false'); password.parentElement.append(reveal);
  const remember = element('input'); remember.type = 'checkbox'; remember.id = username.id + '-remember';
  const saved = storage('getItem','pk.workspace.username');
  if (saved) { username.value = saved; remember.checked = true; }
  const row = element('label','ws-remember',remember,'Remember username on this device');
  row.htmlFor = remember.id;
  dialog.querySelector('fieldset').append(row);
  dialog.querySelector('form').addEventListener('submit',() => {
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
