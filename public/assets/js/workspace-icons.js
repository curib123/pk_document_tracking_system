import { cloneView } from './views.js';

// SVG geometry and markup are maintained in components/workspace/icons.php.
export function icon(name, className = '') {
  const id = 'ws-icon-' + name;
  const node = cloneView(document.getElementById(id) ? id : 'ws-icon-file');
  if (className) node.setAttribute('class', 'ws-icon ' + className);
  return node;
}

export function hydrateIcons(root) {
  for (const target of root.querySelectorAll('[data-icon]')) {
    target.append(icon(target.dataset.icon));
    target.removeAttribute('data-icon');
  }
}
