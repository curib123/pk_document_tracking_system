import { cloneView, setAttributes, viewText, bindText } from './views.js';

let serial = 0;

export function button(label, action, attributes = {}) {
  return setAttributes(
    viewText('action-button-template', label),
    {
      type: 'button',
      ...attributes,
      onclick: event => {
        Promise.resolve()
          .then(() => action(event))
          .catch(error => notice('Action failed', error.message));
      }
    }
  );
}

export function labelOf(value) {
  return String(value)
    .replaceAll('_', ' ')
    .replace(/\b\w/g, letter => letter.toUpperCase());
}

// ID internal ra ni; readable values ang ipakita sa user para dili confusing.
const INTERNAL_DISPLAY_KEYS = new Set([
  'id',
  'version',
  'current_node',
  'snapshot',
  'payload',
  'graph',
  'assignment',
  'candidates',
  'before_state',
  'after_state',
  'previous_state',
  'result',
  'base_document_version'
]);

function hiddenDisplayKey(key) {
  return (
    INTERNAL_DISPLAY_KEYS.has(key) ||
    key.endsWith('_id') ||
    key.endsWith('_by')
  );
}

export function displayValue(value) {
  if (Array.isArray(value)) {
    return value.map(displayValue);
  }

  if (!value || typeof value !== 'object') {
    return value;
  }

  return Object.fromEntries(
    Object.entries(value)
      .filter(([key]) => !hiddenDisplayKey(key))
      .map(([key, item]) => [key, displayValue(item)])
  );
}

export class Modal {
  constructor(title, { explanation = '' } = {}) {
    this.trigger = document.activeElement;
    this.pending = false;
    this.completed = false;

    const id = 'dialog-title-' + (++serial);

    this.node = document
      .querySelector('#modal-shell')
      .content
      .firstElementChild
      .cloneNode(true);

    this.node.setAttribute('aria-labelledby', id);

    this.heading = this.node.querySelector('[data-dialog-title]');
    this.heading.id = id;
    this.heading.textContent = title;

    const description = this.node.querySelector('[data-dialog-explanation]');
    description.textContent = explanation;
    description.hidden = !explanation;

    this.form = this.node.querySelector('form');
    this.body = this.node.querySelector('fieldset');
    this.footer = this.node.querySelector('footer');
    this.error = this.node.querySelector('[data-dialog-error]');
    this.progress = this.node.querySelector('[data-dialog-progress]');

    this.closeButton = button('Cancel', () => this.close());
    this.footer.append(this.closeButton);

    document.body.append(this.node);

    this.node.addEventListener('cancel', event => {
      if (this.pending) {
        event.preventDefault();
      }
    });

    this.node.addEventListener('close', () => {
      this.node.remove();

      if (this.trigger?.isConnected) {
        this.trigger.focus();
      }
    });

    this.form.addEventListener('submit', event => {
      event.preventDefault();

      if (
        this.pending ||
        this.completed ||
        !this.submitHandler ||
        !this.form.reportValidity()
      ) {
        return;
      }

      this.run(this.submitHandler);
    });

    this.node.showModal();
  }

  setTitle(title) {
    this.heading.textContent = title;
  }

  setSubmit(label, handler) {
    this.submitHandler = handler;

    if (!this.submitButton) {
      this.submitButton = viewText('submit-button-template', label);
      this.footer.prepend(this.submitButton);
    }

    this.submitButton.textContent = label;
  }

  busy(value, message = 'Processing…') {
    this.pending = value;
    this.body.disabled = value;

    for (const control of this.footer.querySelectorAll('button')) {
      control.disabled = value;
    }

    this.node.setAttribute('aria-busy', String(value));
    this.progress.textContent = value ? message : '';
  }

  showError(error) {
    this.error.textContent = error.message || String(error);

    for (const field of this.form.querySelectorAll('[name]')) {
      const message = error.fields?.[field.name];

      if (message) {
        field.setAttribute('aria-invalid', 'true');
        field.title = message;
      }
    }

    this.error.focus();
  }

  async run(handler) {
    if (this.pending) {
      return;
    }

    this.error.textContent = '';

    for (const field of this.form.querySelectorAll('[aria-invalid]')) {
      field.removeAttribute('aria-invalid');
    }

    this.busy(true);

    try {
      return await handler();
    } catch (error) {
      this.showError(error);
    } finally {
      this.busy(false);
    }
  }

  done(result = {}) {
    const visible =
      typeof result === 'string'
        ? result
        : displayValue(result);

    const text =
      typeof visible === 'string'
        ? visible
        : Object.keys(visible || {}).length
          ? JSON.stringify(visible, null, 2)
          : 'Completed.';

    this.completed = true;

    const resultView = cloneView('result-template');
    bindText(resultView, 'result', text);
    this.body.replaceChildren(resultView);

    if (this.submitButton) {
      this.submitButton.hidden = true;
    }

    this.closeButton.textContent = 'Close';
    this.error.textContent = '';

    queueMicrotask(() => this.closeButton.focus());
  }

  close() {
    if (!this.pending && this.node.open) {
      this.node.close();
    }
  }

  forceCloseAfterSuccess() {
    this.pending = false;
    this.close();
  }

  focusFirst() {
    this.body
      .querySelector(
        'input:not([type=hidden]):not([disabled]),' +
        'select:not([disabled]),' +
        'textarea:not([disabled]),' +
        'button:not([disabled])'
      )
      ?.focus();
  }
}

export function notice(title, message) {
  const modal = new Modal(title);
  modal.done(message);
  return modal;
}

export function inspect(value, heading = 'Details') {
  const details = cloneView('inspect-template');
  bindText(details, 'heading', heading);
  bindText(details, 'value', JSON.stringify(displayValue(value), null, 2));
  return details;
}

// ID internal ra ni; view templates render only human-readable columns.
export function table(columns, rows, actions) {
  const visibleColumns = columns.filter(column => !hiddenDisplayKey(column));
  const result = cloneView('data-table-template');
  const head = cloneView('table-row-template');
  for (const column of visibleColumns) {
    head.append(viewText('table-header-template', labelOf(column)));
  }
  if (actions) head.append(viewText('table-header-template', 'Actions'));
  result.tHead.append(head);

  for (const row of rows) {
    const tr = cloneView('table-row-template');
    for (const column of visibleColumns) {
      const value = displayValue(row[column]);
      tr.append(viewText('table-cell-template',
        typeof value === 'object' && value !== null ? JSON.stringify(value) : value ?? '—'));
    }
    if (actions) {
      const cell = cloneView('table-cell-template');
      cell.append(...[actions(row)].flat(Infinity));
      tr.append(cell);
    }
    result.tBodies[0].append(tr);
  }
  if (!rows.length) {
    const empty = cloneView('table-empty-template');
    empty.firstElementChild.colSpan = visibleColumns.length + (actions ? 1 : 0);
    result.tBodies[0].append(empty);
  }
  return result;
}
