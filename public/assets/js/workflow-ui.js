import { el, Modal, button, table, labelOf } from './components.js';
import { field, formModal, mountFields } from './forms.js';
function nodeEditor(node, api, save) {
  const modal = new Modal(node ? 'Edit workflow node' : 'Add workflow node', { explanation: 'Targets are node keys. User/role assignments use numeric IDs; permission assignments use module.action; document assignments use a configured approver key. The requester cannot approve their own request.' });
  let form; let values = node ? { ...node, assignment_type: node.assignment?.type, assignment_value: node.assignment?.value } : { type: 'approval', assignment_type: 'permission', assignment_value: 'requests.approve' };
  const draw = async () => {
    form?.dispose(); modal.body.replaceChildren();
    const fields = [field('key'), field('type', 'select', true, null, { options: ['start', 'approval', 'condition', 'end'] })];
    if (values.type === 'start') fields.push(field('next'));
    if (values.type === 'approval') fields.push(field('label'), field('assignment_type', 'select', true, null, { options: ['user', 'role', 'permission', 'leader', 'document'] }), field('assignment_value', 'text', false), field('approve'), field('reject'), field('return'));
    if (values.type === 'condition') fields.push(field('field'), field('operator', 'select', true, null, { options: ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'contains'] }), field('value', 'text', false), field('true'), field('false'));
    if (values.type === 'end') fields.push(field('outcome', 'select', true, null, { options: ['approved', 'rejected', 'returned', 'cancelled'] }));
    form = await mountFields(modal.body, fields, values, api);
    form.controls.get('type').addEventListener('change', () => modal.run(async () => { values = await form.read(); await draw(); }));
  };
  modal.setSubmit('Save node', async () => {
    const values = await form.read(); let next = { key: values.key, type: values.type };
    if (values.type === 'start') next.next = values.next;
    if (values.type === 'approval') next = { ...next, label: values.label, assignment: { type: values.assignment_type, ...(values.assignment_type === 'leader' ? {} : { value: ['user', 'role'].includes(values.assignment_type) ? Number(values.assignment_value) : values.assignment_value }) }, approve: values.approve, reject: values.reject, return: values.return };
    if (values.type === 'condition') {
      let value = values.value; try { value = JSON.parse(value); } catch { /* A plain string is a supported scalar. */ }
      next = { ...next, field: values.field, operator: values.operator, value, true: values.true, false: values.false };
    }
    if (values.type === 'end') next.outcome = values.outcome;
    save(next); modal.forceCloseAfterSuccess();
  });
  modal.run(draw).then(() => modal.focusFirst());
  modal.node.addEventListener('close', () => form?.dispose());
  return modal;
}
export function workflowVersionModal(workflow, version, api, template, after, copy = false) {
  const existing = version && !copy && version.status === 'draft';
  let graph = structuredClone(version?.graph || template);
  const modal = new Modal(existing ? 'Edit workflow draft' : 'New workflow version', { explanation: 'Edit nodes and their paths, then save. Only published versions run new requests. Existing requests retain their own immutable snapshots.' });
  const draw = () => {
    modal.body.replaceChildren();
    const start = el('select', { id: 'workflow-start' }, (graph.nodes || []).filter(node => node.type === 'start').map(node => el('option', { value: node.key }, node.key)));
    start.value = graph.start; start.addEventListener('change', () => { graph.start = start.value; });
    modal.body.append(el('p', {}, el('label', { htmlFor: 'workflow-start' }, 'Start node'), start));
    modal.body.append(button('Add node', () => nodeEditor(null, api, node => {
      if (graph.nodes.some(existing => existing.key === node.key)) throw new Error('Node keys must be unique.');
      graph.nodes.push(node); if (node.type === 'start') graph.start = node.key; draw();
    })));
    modal.body.append(button('Edit raw graph', () => formModal('Edit raw workflow graph', [field('graph', 'json')], { graph }, api, values => {
      if (!values.graph || !Array.isArray(values.graph.nodes)) throw new Error('Graph must include a nodes array and start key.');
      graph = values.graph; draw(); return { message: 'Graph updated locally. Save the version to validate and persist it.' };
    })));
    modal.body.append(table(['key', 'type', 'label'], graph.nodes, node => [
      button('Edit node', () => nodeEditor(node, api, replacement => {
        if (replacement.key !== node.key && graph.nodes.some(other => other.key === replacement.key)) throw new Error('Node keys must be unique.');
        graph.nodes = graph.nodes.map(other => other === node ? replacement : other);
        if (graph.start === node.key) graph.start = replacement.key;
        draw();
      })),
      button('Remove node', () => formModal('Remove workflow node', [], {}, api, () => {
        graph.nodes = graph.nodes.filter(other => other !== node); draw();
        return { message: 'Node removed locally. Update references before saving the draft.' };
      }, { submitLabel: 'Remove node', explanation: `Remove ${node.key}? The version will not save with broken paths.` })),
    ]));
  };
  draw(); modal.setSubmit('Save version', async () => {
    const result = await api.request('workflows.version', { workflow_id: workflow.id, ...(existing ? { id: version.id, version: version.version } : {}), graph }, true);
    await after?.(); modal.done({ ...result, message: 'Draft version saved. Publish it separately after checking its paths and assignments.' });
  });
  return modal;
}
