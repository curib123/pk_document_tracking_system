import { el, Modal, button, table, labelOf } from './components.js';
import { field, mountFields } from './forms.js';

function stepEditor(step, api, save) {
  const modal = new Modal(step ? 'Edit approval step' : 'Add approval step', {
    explanation: 'Choose the step name and who approves it. User and role selections show names only; IDs stay internal.'
  });
  let form;
  let values = step ? {
    name: step.name,
    approver_type: step.approver?.type || 'user',
    approver_value: step.approver?.value || null
  } : {
    name: '',
    approver_type: 'user',
    approver_value: null
  };

  const draw = async () => {
    form?.dispose();
    modal.body.replaceChildren();

    const approverOptions = [
      { value: 'user', label: 'Specific User' },
      { value: 'role', label: 'Role' },
      { value: 'leader', label: "Requester's Leader" },
      { value: 'requester', label: 'Requester' }
    ];

    const fields = [
      field('name', 'text', true, null, { label: 'Step Name' }),
      field('approver_type', 'select', true, null, { label: 'Who Approves', options: approverOptions })
    ];

    if (values.approver_type === 'user') {
      fields.push(field('approver_value', 'lookup', true, 'users', { label: 'Approver' }));
    } else if (values.approver_type === 'role') {
      fields.push(field('approver_value', 'lookup', true, 'roles', { label: 'Approver Role' }));
    }

    form = await mountFields(modal.body, fields, values, api);
    form.controls.get('approver_type').addEventListener('change', () => modal.run(async () => {
      const current = await form.read();
      values = { ...values, ...current, approver_value: null };
      await draw();
    }));
  };

  modal.setSubmit('Save step', async () => {
    const data = await form.read();
    const approver = { type: data.approver_type };
    if (['user', 'role'].includes(data.approver_type)) {
      approver.value = Number(data.approver_value);
      approver.label = form.controls.get('approver_value')?.selectedOptions?.[0]?.textContent || '';
    }
    save({ name: data.name, approver });
    modal.forceCloseAfterSuccess();
  });

  modal.run(draw).then(() => modal.focusFirst());
  modal.node.addEventListener('close', () => form?.dispose());
  return modal;
}

function approverText(step) {
  const approver = step.approver || {};
  if (approver.label) return approver.label;
  if (approver.type === 'leader') return "Requester's Leader";
  if (approver.type === 'requester') return 'Requester';
  return approver.type === 'role' ? 'Selected Role' : 'Selected User';
}

export function workflowVersionModal(workflow, version, api, template, after, copy = false) {
  const existing = version && !copy && version.status === 'draft';
  let draft = structuredClone(version?.graph || template || { steps: [] });
  if (!Array.isArray(draft.steps)) draft = { steps: [] };

  const modal = new Modal(existing ? 'Edit workflow draft' : 'New workflow version', {
    explanation: 'Build an ordered approval sequence. New requests use only the published version marked Default. Published versions are immutable.'
  });

  const draw = () => {
    modal.body.replaceChildren();

    modal.body.append(
      el('p', {}, 'Approval steps: ' + draft.steps.length),
      button('Add approval step', () => stepEditor(null, api, step => {
        draft.steps.push(step);
        draw();
      }))
    );

    const rows = draft.steps.map((step, index) => ({
      step: index + 1,
      name: step.name,
      approver_type: labelOf(step.approver?.type || ''),
      approver: approverText(step),
      _index: index,
      _step: step
    }));

    modal.body.append(table(
      ['step', 'name', 'approver_type', 'approver'],
      rows,
      row => {
        const index = row._index;
        return [
          button('Edit', () => stepEditor(row._step, api, replacement => {
            draft.steps[index] = replacement;
            draw();
          })),
          button('Move up', () => {
            if (index <= 0) return;
            const item = draft.steps.splice(index, 1)[0];
            draft.steps.splice(index - 1, 0, item);
            draw();
          }, { disabled: index === 0 }),
          button('Move down', () => {
            if (index >= draft.steps.length - 1) return;
            const item = draft.steps.splice(index, 1)[0];
            draft.steps.splice(index + 1, 0, item);
            draw();
          }, { disabled: index === draft.steps.length - 1 }),
          button('Remove', () => {
            draft.steps.splice(index, 1);
            draw();
          })
        ];
      }
    ));

    if (!draft.steps.length) {
      modal.body.append(el('p', {}, 'This draft has no approval steps yet. Add at least one step before publishing.'));
    }
  };

  draw();

  modal.setSubmit('Save draft version', async () => {
    const result = await api.request('workflows.version', {
      workflow_id: workflow.id,
      ...(existing ? { id: version.id, version: version.version } : {}),
      graph: draft
    }, true);
    await after?.();
    modal.done({ ...result, message: 'Draft version saved. Publish it when the approval sequence is ready.' });
  });

  return modal;
}
