// View markup is owned by application/views. Bind data; never parse HTML here.
export function cloneView(id) {
  const source = document.getElementById(id);
  if (!(source instanceof HTMLTemplateElement) || !source.content.firstElementChild) {
    throw new Error('Missing view template: ' + id + '. Reload the page.');
  }
  return source.content.firstElementChild.cloneNode(true);
}

export function setAttributes(node, attributes = {}) {
  for (const [key, value] of Object.entries(attributes)) {
    if (value === undefined || value === null || value === false) continue;
    if (['innerHTML', 'outerHTML', 'srcdoc'].includes(key)) {
      throw new Error('HTML content must be defined in a PHP view.');
    }
    if (key.startsWith('on') && typeof value !== 'function') {
      throw new Error('Event handlers must be JavaScript functions.');
    }
    if (key === 'text') node.textContent = String(value);
    else if (key.startsWith('on') && typeof value === 'function') {
      node.addEventListener(key.slice(2), value);
    } else if (key in node && !key.startsWith('aria-') && key !== 'role') {
      node[key] = value;
    } else node.setAttribute(key, String(value));
  }
  return node;
}

export function viewText(id, value, attributes = {}) {
  const node = setAttributes(cloneView(id), attributes);
  node.textContent = value == null ? '' : String(value);
  return node;
}

export function bindText(root, name, value) {
  const node = root.querySelector('[data-bind="' + name + '"]');
  if (!node) throw new Error('Missing view binding: ' + name);
  node.textContent = value == null ? '' : String(value);
  return node;
}

export function detailRow(label, value) {
  const row = cloneView('detail-row-template');
  bindText(row, 'label', label);
  bindText(row, 'value', value);
  return row;
}

export function section(title, ...children) {
  const node = cloneView('detail-section-template');
  bindText(node, 'heading', title);
  node.querySelector('[data-section-content]').append(...children.flat(Infinity));
  return node;
}
