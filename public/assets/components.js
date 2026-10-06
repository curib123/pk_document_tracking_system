let serial = 0;
export function el(tag, attributes = {}, ...children) {
  const node = document.createElement(tag);
  for (const [key, value] of Object.entries(attributes)) {
    if (value === undefined || value === null || value === false) continue;
    if (key === 'text') node.textContent = String(value);
    else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
    else if (key in node && !key.startsWith('aria-') && key !== 'role') node[key] = value;
    else node.setAttribute(key, String(value));
  }
  for (const child of children.flat(Infinity)) if (child !== undefined && child !== null) node.append(child instanceof Node ? child : document.createTextNode(String(child)));
  return node;
}
export function button(label, action, attributes = {}) {
  return el('button', { type: 'button', ...attributes, onclick: event => {
    Promise.resolve().then(() => action(event)).catch(error => notice('Action failed', error.message));
  } }, label);
}
export function labelOf(value) { return String(value).replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase()); }
export class Modal {
  constructor(title, { explanation = '' } = {}) {
    this.trigger = document.activeElement; this.pending = false; this.completed = false;
    const id = `dialog-title-${++serial}`;
    this.node = el('dialog', { 'aria-labelledby': id });
    this.form = el('form'); this.body = el('fieldset'); this.footer = el('footer');
    this.error = el('p', { role: 'alert', tabindex: '-1' }); this.progress = el('p', { role: 'status', 'aria-live': 'polite' });
    this.closeButton = button('Cancel', () => this.close()); this.footer.append(this.closeButton);
    this.form.append(this.body, this.error, this.progress, this.footer);
    this.node.append(el('h2', { id }, title));
    if (explanation) this.node.append(el('p', {}, explanation));
    this.node.append(this.form); document.body.append(this.node);
    this.node.addEventListener('cancel', event => { if (this.pending) event.preventDefault(); });
    this.node.addEventListener('close', () => { this.node.remove(); if (this.trigger?.isConnected) this.trigger.focus(); });
    this.form.addEventListener('submit', event => {
      event.preventDefault();
      if (this.pending || this.completed || !this.submitHandler || !this.form.reportValidity()) return;
      this.run(this.submitHandler);
    });
    this.node.showModal();
  }
  setSubmit(label, handler) {
    this.submitHandler = handler;
    if (!this.submitButton) { this.submitButton = el('button', { type: 'submit' }, label); this.footer.prepend(this.submitButton); }
    this.submitButton.textContent = label;
  }
  busy(value, message = 'Processing…') {
    this.pending = value; this.body.disabled = value;
    for (const control of this.footer.querySelectorAll('button')) control.disabled = value;
    this.node.setAttribute('aria-busy', String(value)); this.progress.textContent = value ? message : '';
  }
  showError(error) {
    this.error.textContent = error.message || String(error);
    for (const field of this.form.querySelectorAll('[name]')) {
      const message = error.fields?.[field.name];
      if (message) { field.setAttribute('aria-invalid', 'true'); field.title = message; }
    }
    this.error.focus();
  }
  async run(handler) {
    if (this.pending) return;
    this.error.textContent = '';
    for (const field of this.form.querySelectorAll('[aria-invalid]')) field.removeAttribute('aria-invalid');
    this.busy(true);
    try { return await handler(); } catch (error) { this.showError(error); } finally { this.busy(false); }
  }
  done(result = {}) {
    this.completed = true; this.body.replaceChildren(el('h3', {}, 'Result'), el('pre', {}, typeof result === 'string' ? result : JSON.stringify(result, null, 2)));
    if (this.submitButton) this.submitButton.hidden = true;
    this.closeButton.textContent = 'Close'; this.error.textContent = '';
    queueMicrotask(() => this.closeButton.focus());
  }
  close() { if (!this.pending && this.node.open) this.node.close(); }
  forceCloseAfterSuccess() { this.pending = false; this.close(); }
  focusFirst() { this.body.querySelector('input:not([type=hidden]):not([disabled]),select:not([disabled]),textarea:not([disabled]),button:not([disabled])')?.focus(); }
}
export function notice(title, message) { const modal = new Modal(title); modal.done(message); return modal; }
export function inspect(value, heading = 'Details') {
  const details = el('details', { open: true }, el('summary', {}, heading));
  details.append(el('pre', {}, JSON.stringify(value, null, 2))); return details;
}
export function table(columns, rows, actions) {
  const head = el('tr', {}, columns.map(column => el('th', { scope: 'col' }, labelOf(column))));
  if (actions) head.append(el('th', { scope: 'col' }, 'Actions'));
  const body = el('tbody');
  for (const row of rows) {
    const tr = el('tr', {}, columns.map(column => el('td', {}, typeof row[column] === 'object' && row[column] !== null ? JSON.stringify(row[column]) : row[column] ?? '—')));
    if (actions) tr.append(el('td', {}, actions(row))); body.append(tr);
  }
  if (!rows.length) body.append(el('tr', {}, el('td', { colSpan: columns.length + (actions ? 1 : 0) }, 'No records found.')));
  return el('table', {}, el('thead', {}, head), body);
}
