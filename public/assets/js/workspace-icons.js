import { cloneView } from './views.js';

// Font Awesome markup lives in PHP views; JavaScript only clones and binds it.
export function icon(name, className = '') {
  const id = 'ws-icon-' + name;
  const node = cloneView(document.getElementById(id) ? id : 'ws-icon-file');
  // Keep the renderer's fa-solid/fa-regular and glyph classes when adding a variant.
  for (const value of className.split(/\s+/).filter(Boolean)) {
    node.classList.add(value);
  }
  return node;
}

export function hydrateIcons(root) {
  for (const target of root.querySelectorAll('[data-icon]')) {
    target.append(icon(target.dataset.icon));
    target.removeAttribute('data-icon');
  }
}
