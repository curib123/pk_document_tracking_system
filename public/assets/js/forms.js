import { Modal, labelOf } from './components.js';
import { cloneView, setAttributes, viewText } from './views.js';

let fieldSerial = 0;

export const field = (
  name,
  type = 'text',
  required = true,
  lookup = null,
  extra = {}
) => {
  const displayName =
    type === 'lookup' || type === 'upload' || lookup
      ? name.replace(/_id$/, '')
      : name;

  return {
    name,
    label: labelOf(displayName),
    type,
    required,
    lookup,
    ...extra
  };
};

export async function mountFields(
  container,
  fields,
  values,
  api,
  { disabled = [] } = {}
) {
  const controls = new Map();
  const getters = new Map();
  const disposers = [];
  const lookupOptions = new Map();

  const setLookupValue = (
    name,
    value,
    label = ''
  ) => {
    const control = controls.get(name);

    if (!control) {
      return;
    }

    const next =
      value === null ||
      value === undefined ||
      value === ''
        ? ''
        : String(value);

    if (
      next &&
      !Array.from(control.options).some(
        option => option.value === next
      )
    ) {
      control.append(viewText('text-option-template', label || 'Selected value', { value: next }));
    }

    if (control.value === next) {
      return;
    }

    control.value = next;
    control.dispatchEvent(
      new Event('change')
    );
  };

  const applyLookupHierarchy = name => {
    const control = controls.get(name);
    const options = lookupOptions.get(name);

    if (
      !control ||
      !options ||
      !control.value
    ) {
      return;
    }

    const selected = options.get(
      control.value
    );

    if (!selected) {
      return;
    }

    const populate = (
      key,
      target,
      labelKey
    ) => {
      if (
        !Object.prototype.hasOwnProperty.call(
          selected,
          key
        )
      ) {
        return;
      }

      setLookupValue(
        target,
        selected[key],
        selected[labelKey] || ''
      );
    };

    if (name === 'location_id') {
      populate(
        'asset_id',
        'asset_id',
        'asset_label'
      );
      populate(
        'specific_id',
        'specific_id',
        'specific_label'
      );
      populate(
        'area_id',
        'area_id',
        'area_label'
      );
      return;
    }

    if (name === 'asset_id') {
      populate(
        'specific_id',
        'specific_id',
        'specific_label'
      );
      populate(
        'area_id',
        'area_id',
        'area_label'
      );
      return;
    }

    if (name === 'specific_id') {
      populate(
        'area_id',
        'area_id',
        'area_label'
      );
    }
  };

  for (const definition of fields) {
    const { name, type = 'text', required = true } = definition;
    const displayName = type === 'lookup' || type === 'upload' || definition.lookup
      ? name.replace(/_id$/, '') : name;
    const label = definition.label || labelOf(displayName);
    const id = 'field-' + (++fieldSerial);
    const value = values[name];
    const choices = definition.options || {
      request_type: definition.requestTypes || [],
      disposal_action: ['shred', 'scratch', 'reuse', 'other']
    }[type];
    const template = type === 'lookup' ? 'field-lookup-template'
      : type === 'upload' ? 'field-upload-template'
      : choices ? 'field-select-template'
      : ['textarea', 'json'].includes(type) ? 'field-textarea-template'
      : 'field-input-template';
    const group = cloneView(template);
    const input = group.querySelector('[data-control]');
    const labelNode = group.querySelector('[data-field-label]');
    labelNode.htmlFor = id;
    labelNode.textContent = label;
    setAttributes(input, { id, name, required });
    input.disabled = disabled.includes(name);
    container.append(group);
    let read;

    if (type === 'lookup') {
      const search = group.querySelector('[data-lookup-search]');
      setAttributes(search, { id: id + '-search', 'aria-label': 'Search ' + label });
      search.disabled = input.disabled;
      const hint = group.querySelector('[data-field-hint]');
      const optionMap = new Map();
      lookupOptions.set(name, optionMap);
      let aborter;
      let timer;
      let selection = value ? String(value) : '';

      const load = async () => {
        aborter?.abort();
        aborter = new AbortController();
        // Scope comes from this request form, never the global page or a direct action.
        const typeControl = container.closest('dialog')?.querySelector('select[name="type"]');
        const requestType = ['softcopy', 'hardcopy'].includes(definition.lookup)
          && ['access', 'assignment'].includes(typeControl?.value) ? typeControl.value : undefined;
        try {
          const result = await api.request('lookups', {
            kind: definition.lookup,
            q: search.value,
            selected: selection || undefined,
            request_type: requestType
          }, false, aborter.signal);
          if (!input.isConnected) return;
          input.replaceChildren(viewText('text-option-template', required ? 'Choose…' : 'None', { value: '' }));
          optionMap.clear();
          for (const option of result.options) optionMap.set(String(option.id), option);
          const selectedExists = result.options.some(option => String(option.id) === selection);
          if (selection && !selectedExists && requestType) selection = '';
          if (selection && !selectedExists) {
            input.append(viewText('text-option-template', 'Current selection', { value: selection }));
          }
          for (const option of result.options) {
            input.append(viewText('text-option-template', option.label, { value: String(option.id) }));
          }
          input.value = selection;
          hint.textContent = result.message || '';
        } catch (error) {
          if (error.name !== 'AbortError') hint.textContent = error.message;
        }
      };
      input.addEventListener('change', () => {
        selection = input.value;
        applyLookupHierarchy(name);
      });
      search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(load, 200);
      });
      await load();
      disposers.push(() => { clearTimeout(timer); aborter?.abort(); });
      read = () => input.value ? Number(input.value) : null;
    } else if (type === 'upload') {
      input.required = required && !value;
      const hint = group.querySelector('[data-field-hint]');
      hint.textContent = value
        ? 'A private upload is already saved. Select a replacement only when needed.'
        : 'Select a document file. Upload occurs when this form is saved.';
      let uploadedId = value ? Number(value) : null;
      let uploadedFile;
      read = async () => {
        const selected = input.files[0];
        if (selected && selected !== uploadedFile) {
          hint.textContent = 'Uploading…';
          const form = new FormData();
          form.append('file', selected);
          const result = await api.request('files.upload', form, true);
          uploadedId = result.id;
          uploadedFile = selected;
          hint.textContent = 'Private upload saved. It will be linked when the form succeeds.';
          input.required = false;
        }
        if (required && !uploadedId) throw new Error('Select a file for ' + label + '.');
        return uploadedId;
      };
    } else {
      if (choices) {
        input.append(viewText('text-option-template', required ? 'Choose…' : 'None', { value: '' }));
        for (const option of choices) {
          const object = typeof option === 'object';
          input.append(viewText('text-option-template', object ? option.label : labelOf(option), {
            value: String(object ? option.value : option)
          }));
        }
        input.value = value ?? '';
      } else if (['textarea', 'json'].includes(type)) {
        input.rows = type === 'json' ? 8 : 3;
        input.value = type === 'json' && typeof value === 'object' ? JSON.stringify(value, null, 2) : value ?? '';
      } else if (type === 'checkbox') {
        input.type = 'checkbox';
        input.required = false;
        input.checked = value === undefined ? name === 'active' : !!Number(value);
      } else {
        input.type = ['password', 'date', 'number', 'email'].includes(type) ? type : 'text';
        input.value = value ?? (name === 'page_number' ? 1 : '');
        input.autocomplete = type === 'password'
          ? ['new_password', 'confirm_password'].includes(name) ? 'new-password' : 'current-password'
          : 'off';
        if (type === 'number') { input.min = 1; input.step = 1; }
      }
      read = () => {
        if (type === 'checkbox') return input.checked ? 1 : 0;
        if (type === 'json') {
          try { return JSON.parse(input.value); }
          catch { throw new Error(label + ' must be valid JSON.'); }
        }
        if (type === 'number') return input.value ? Number(input.value) : null;
        return input.value === '' && !required ? null : input.value;
      };
    }
    controls.set(name, input);
    getters.set(name, read);
    if (definition.description) {
      const description = group.querySelector('[data-description]');
      description.textContent = definition.description;
      description.hidden = false;
      group.querySelector('[data-description-break]').hidden = false;
    }
  }

  // Apply predefined parent values after every lookup exists.
  for (const name of [
    'location_id',
    'asset_id',
    'specific_id'
  ]) {
    applyLookupHierarchy(name);
  }

  return {
    controls,

    async read() {
      const result = {};

      for (const [name, getter] of getters) {
        result[name] = await getter();
      }

      return result;
    },

    dispose() {
      for (const cleanup of disposers) {
        cleanup();
      }
    }
  };
}

export function formModal(
  title,
  fields,
  values,
  api,
  save,
  options = {}
) {
  const modal = new Modal(title, options);
  modal.busy(true, 'Loading form…');

  modal.ready = mountFields(
    modal.body,
    fields,
    values,
    api,
    options
  )
    .then(form => {
      modal.fields = form;

      modal.node.addEventListener(
        'close',
        () => form.dispose(),
        { once: true }
      );

      modal.setSubmit(
        options.submitLabel || 'Save',
        async () => {
          const result = await save(
            await form.read(),
            modal
          );

          await options.after?.(result);

          if (options.closeOnSuccess) {
            modal.forceCloseAfterSuccess();
          } else {
            modal.done(
              result || { message: 'Saved.' }
            );
          }
        }
      );

      modal.busy(false);
      modal.focusFirst();

      return form;
    })
    .catch(error => {
      modal.busy(false);
      modal.showError(error);
    });

  return modal;
}

export function actionModal(
  title,
  api,
  operation,
  fixed,
  fields = [],
  options = {}
) {
  return formModal(
    title,
    fields,
    {},
    api,
    values =>
      api.request(
        operation,
        { ...fixed, ...values },
        true
      ),
    {
      submitLabel: 'Confirm',
      ...options
    }
  );
}
