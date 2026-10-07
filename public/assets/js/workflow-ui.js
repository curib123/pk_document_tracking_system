import {
  el,
  Modal,
  button,
  table,
  labelOf
} from './components.js';
import {
  field,
  mountFields
} from './forms.js';

// Mao ni ang only approver sources supported sa simple workflow builder.
const APPROVER_OPTIONS = [
  {
    value: 'user',
    label: 'Specific User'
  },
  {
    value: 'role',
    label: 'Role'
  },
  {
    value: 'leader',
    label: "Requester's Leader"
  },
  {
    value: 'requester',
    label: 'Requester'
  }
];

function approverFields(type) {
  if (type === 'user') {
    return [
      field(
        'approver_value',
        'lookup',
        true,
        'users',
        {
          label: 'Approver'
        }
      )
    ];
  }

  if (type === 'role') {
    return [
      field(
        'approver_value',
        'lookup',
        true,
        'roles',
        {
          label: 'Approver Role'
        }
      )
    ];
  }

  return [];
}

function stepEditor(step, api, save) {
  const modal = new Modal(
    step
      ? 'Edit approval step'
      : 'Add approval step',
    {
      explanation:
        'Choose the step name and who approves it. User and role selections show names only; IDs stay internal.'
    }
  );

  let form;

  let values = step
    ? {
        name: step.name,
        approver_type:
          step.approver?.type ||
          'user',
        approver_value:
          step.approver?.value ||
          null
      }
    : {
        name: '',
        approver_type: 'user',
        approver_value: null
      };

  const draw = async () => {
    form?.dispose();
    modal.body.replaceChildren();

    const fields = [
      field(
        'name',
        'text',
        true,
        null,
        {
          label: 'Step Name'
        }
      ),
      field(
        'approver_type',
        'select',
        true,
        null,
        {
          label: 'Who Approves',
          options: APPROVER_OPTIONS
        }
      ),
      ...approverFields(
        values.approver_type
      )
    ];

    form = await mountFields(
      modal.body,
      fields,
      values,
      api
    );

    const typeControl =
      form.controls.get('approver_type');

    typeControl.addEventListener(
      'change',
      async () => {
        const nextType = typeControl.value;
        const current = await form.read();

        values = {
          ...values,
          ...current,
          approver_type: nextType,
          approver_value: null
        };

        // Prevent a fast Save click from being lost while the lookup field
        // is rebuilt for the newly selected approver type.
        if (modal.submitButton) {
          modal.submitButton.disabled = true;
        }

        try {
          await draw();
        } finally {
          if (modal.submitButton) {
            modal.submitButton.disabled = false;
          }
        }
      }
    );
  };

  modal.setSubmit(
    'Save step',
    async () => {
      const data = await form.read();

      const approver = {
        type: data.approver_type
      };

      if (
        ['user', 'role'].includes(
          data.approver_type
        )
      ) {
        approver.value = Number(
          data.approver_value
        );

        approver.label =
          form.controls
            .get('approver_value')
            ?.selectedOptions?.[0]
            ?.textContent || '';
      }

      save({
        name: data.name,
        approver
      });

      modal.forceCloseAfterSuccess();
    }
  );

  modal
    .run(draw)
    .then(() => modal.focusFirst());

  modal.node.addEventListener(
    'close',
    () => form?.dispose()
  );

  return modal;
}

function approverText(step) {
  const approver = step.approver || {};

  if (approver.label) {
    return approver.label;
  }

  if (approver.type === 'leader') {
    return "Requester's Leader";
  }

  if (approver.type === 'requester') {
    return 'Requester';
  }

  return approver.type === 'role'
    ? 'Selected Role'
    : 'Selected User';
}

function moveStep(steps, from, to) {
  if (
    from < 0 ||
    from >= steps.length ||
    to < 0 ||
    to >= steps.length
  ) {
    return;
  }

  const item = steps.splice(from, 1)[0];
  steps.splice(to, 0, item);
}

// Draft first, then publish, then choose Default. Old requests keep their pinned version.
export function workflowVersionModal(
  workflow,
  version,
  api,
  template,
  after,
  copy = false
) {
  const existing =
    version &&
    !copy &&
    version.status === 'draft';

  let draft = structuredClone(
    version?.graph ||
    template ||
    { steps: [] }
  );

  if (!Array.isArray(draft.steps)) {
    draft = { steps: [] };
  }

  const modal = new Modal(
    existing
      ? 'Edit workflow draft'
      : 'New workflow version',
    {
      explanation:
        'Build an ordered approval sequence. New requests use only the published version marked Default. Published versions are immutable.'
    }
  );

  const draw = () => {
    modal.body.replaceChildren();

    modal.body.append(
      el(
        'p',
        {},
        'Approval steps: ' +
          draft.steps.length
      ),
      button(
        'Add approval step',
        () =>
          stepEditor(
            null,
            api,
            step => {
              draft.steps.push(step);
              draw();
            }
          )
      )
    );

    const rows = draft.steps.map(
      (step, index) => ({
        step: index + 1,
        name: step.name,
        approver_type: labelOf(
          step.approver?.type || ''
        ),
        approver: approverText(step),
        _index: index,
        _step: step
      })
    );

    modal.body.append(
      table(
        [
          'step',
          'name',
          'approver_type',
          'approver'
        ],
        rows,
        row => {
          const index = row._index;

          return [
            button(
              'Edit',
              () =>
                stepEditor(
                  row._step,
                  api,
                  replacement => {
                    draft.steps[index] =
                      replacement;
                    draw();
                  }
                )
            ),
            button(
              'Move up',
              () => {
                moveStep(
                  draft.steps,
                  index,
                  index - 1
                );
                draw();
              },
              {
                disabled: index === 0
              }
            ),
            button(
              'Move down',
              () => {
                moveStep(
                  draft.steps,
                  index,
                  index + 1
                );
                draw();
              },
              {
                disabled:
                  index ===
                  draft.steps.length - 1
              }
            ),
            button(
              'Remove',
              () => {
                draft.steps.splice(
                  index,
                  1
                );
                draw();
              }
            )
          ];
        }
      )
    );

    if (!draft.steps.length) {
      modal.body.append(
        el(
          'p',
          {},
          'This draft has no approval steps yet. Add at least one step before publishing.'
        )
      );
    }
  };

  draw();

  modal.setSubmit(
    'Save draft version',
    async () => {
      const result = await api.request(
        'workflows.version',
        {
          workflow_id: workflow.id,
          ...(existing
            ? {
                id: version.id,
                version: version.version
              }
            : {}),
          graph: draft
        },
        true
      );

      await after?.();

      modal.done({
        ...result,
        message:
          'Draft version saved. Publish it when the approval sequence is ready.'
      });
    }
  );

  return modal;
}
