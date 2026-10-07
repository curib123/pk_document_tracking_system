import { el, Modal, labelOf } from './components.js';
let fieldSerial = 0;
export const field = (name, type = 'text', required = true, lookup = null, extra = {}) => { const displayName = (type === 'lookup' || type === 'upload' || lookup) ? name.replace(/_id$/, '') : name; return { name, label: labelOf(displayName), type, required, lookup, ...extra }; };
export async function mountFields(container, fields, values, api, { disabled = [] } = {}) {
  const controls = new Map(); const getters = new Map(); const disposers = [];
  for (const definition of fields) {
    const { name, type = 'text', required = true } = definition;
    const displayName = (type === 'lookup' || type === 'upload' || definition.lookup) ? name.replace(/_id$/, '') : name; const label = definition.label || labelOf(displayName); const id = `field-${++fieldSerial}`; const group = el('p');
    let input, read; const value = values[name];
    if (type === 'lookup') {
      const search = el('input', { type: 'search', id: `${id}-search`, placeholder: 'Type to search', 'aria-label': `Search ${label}` });
      input = el('select', { id, name, required });
      const hint = el('small', { role: 'status' }); let aborter; let timer; let selection = value ? String(value) : '';
      const load = async () => {
        aborter?.abort(); aborter = new AbortController();
        try {
          const result = await api.request('lookups', { kind: definition.lookup, q: search.value, selected: selection || undefined }, false, aborter.signal);
          if (!input.isConnected) return;
          input.replaceChildren(el('option', { value: '' }, required ? 'Choose…' : 'None'));
          if (selection && !result.options.some(option => String(option.id) === selection)) input.append(el('option', { value: selection }, 'Current selection'));
          for (const option of result.options) input.append(el('option', { value: String(option.id) }, option.label));
          input.value = selection; hint.textContent = result.message || '';
        } catch (error) { if (error.name !== 'AbortError') hint.textContent = error.message; }
      };
      input.addEventListener('change', () => { selection = input.value; });
      search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 200); });
      group.append(el('label', { htmlFor: id }, label), el('br'), search, el('br'), input, el('br'), hint);
      container.append(group); await load();
      disposers.push(() => { clearTimeout(timer); aborter?.abort(); });
      search.disabled = disabled.includes(name); read = () => input.value ? Number(input.value) : null;
    } else if (type === 'upload') {
      input = el('input', { id, type: 'file', name, required: required && !value, accept: '.pdf,.docx,.xlsx,.txt,.csv,.png,.jpg,.jpeg' });
      const hint = el('small', { role: 'status' }, value ? `Saved private upload #${value}. Select a replacement only when needed.` : 'Select a document file. Upload occurs when this form is saved.');
      let uploadedId = value ? Number(value) : null; let uploadedFile;
      read = async () => {
        const selected = input.files[0];
        if (selected && selected !== uploadedFile) {
          hint.textContent = 'Uploading…'; const form = new FormData(); form.append('file', selected);
          const result = await api.request('files.upload', form, true); uploadedId = result.id; uploadedFile = selected;
          hint.textContent = `Private upload #${uploadedId} saved. It will be linked when the form succeeds.`;
          input.required = false;
        }
        if (required && !uploadedId) throw new Error(`Select a file for ${label}.`);
        return uploadedId;
      };
      group.append(el('label', { htmlFor: id }, label), el('br'), input, el('br'), hint); container.append(group);
    } else {
      const choices = definition.options || ({ request_type: definition.requestTypes || [], disposal_action: ['shred', 'scratch', 'reuse', 'other'] })[type];
      if (choices) {
        input = el('select', { id, name, required }, required ? el('option', { value: '' }, 'Choose…') : el('option', { value: '' }, 'None'));
        for (const option of choices) input.append(el('option', { value: String(typeof option === 'object' ? option.value : option) }, typeof option === 'object' ? option.label : labelOf(option)));
        input.value = value ?? '';
      } else if (['textarea', 'json'].includes(type)) input = el('textarea', { id, name, rows: type === 'json' ? 8 : 3, cols: 36, required, value: type === 'json' && typeof value === 'object' ? JSON.stringify(value, null, 2) : value ?? '' });
      else if (type === 'checkbox') input = el('input', { id, name, type: 'checkbox', checked: value === undefined ? name === 'active' : !!Number(value) });
      else input = el('input', { id, name, type: ['password','date','number','email'].includes(type) ? type : 'text', required, value: value ?? (name === 'page_number' ? 1 : ''), autocomplete: type === 'password' ? (name === 'new_password' || name === 'confirm_password' ? 'new-password' : 'current-password') : 'off', ...(type === 'number' ? { min: 1, step: 1 } : {}) });
      read = () => {
        if (type === 'checkbox') return input.checked ? 1 : 0;
        if (type === 'json') { try { return JSON.parse(input.value); } catch { throw new Error(`${label} must be valid JSON.`); } }
        if (type === 'number') return input.value ? Number(input.value) : null;
        return input.value === '' && !required ? null : input.value;
      };
      group.append(el('label', { htmlFor: id }, label), el('br'), input); container.append(group);
    }
    input.disabled = disabled.includes(name); controls.set(name, input); getters.set(name, read);
    if (definition.description) group.append(el('br'), el('small', {}, definition.description));
  }
  return { controls, async read() { const result = {}; for (const [name, getter] of getters) result[name] = await getter(); return result; }, dispose() { for (const cleanup of disposers) cleanup(); } };
}
export function formModal(title, fields, values, api, save, options = {}) {
  const modal = new Modal(title, options); modal.busy(true, 'Loading form…');
  modal.ready = mountFields(modal.body, fields, values, api, options).then(form => {
    modal.fields = form; modal.node.addEventListener('close', () => form.dispose(), { once: true });
    modal.setSubmit(options.submitLabel || 'Save', async () => {
      const result = await save(await form.read(), modal);
      await options.after?.(result);
      if (options.closeOnSuccess) modal.forceCloseAfterSuccess(); else modal.done(result || { message: 'Saved.' });
    });
    modal.busy(false); modal.focusFirst(); return form;
  }).catch(error => { modal.busy(false); modal.showError(error); });
  return modal;
}
export function actionModal(title, api, operation, fixed, fields = [], options = {}) {
  return formModal(title, fields, {}, api, values => api.request(operation, { ...fixed, ...values }, true), { submitLabel: 'Confirm', ...options });
}
