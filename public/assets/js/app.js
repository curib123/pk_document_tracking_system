import { ApiClient } from './api.js';
import {
  el,
  button,
  Modal,
  notice,
  table,
  labelOf
} from './components.js';
import {
  field,
  formModal,
  actionModal,
  mountFields
} from './forms.js';
import { workflowVersionModal } from './workflow-ui.js';

const api = new ApiClient();

const content = document.querySelector('#content');
const navigation = document.querySelector('#navigation');
const account = document.querySelector('#account');
const accountActions = document.querySelector('#account-actions');
const status = document.querySelector('#global-status');

let metadata;
let user;
let permissions = [];
let currentModule;
let page = 1;
let query = '';
let limit = 25;
let sort = '';
let direction = 'desc';
let listGeneration = 0;
let statusFilter = '';
let layout = 'table';

const SINGULAR_NAMES = {
  users: 'user',
  roles: 'role',
  permissions: 'permission',
  areas: 'area',
  specifics: 'specific',
  assets: 'asset',
  locations: 'location',
  categories: 'category',
  workflows: 'workflow'
};

const can = capability =>
  permissions.includes(capability);

const fieldsFor = fields =>
  fields.map(item =>
    item.type === 'request_type'
      ? {
          ...item,
          requestTypes: metadata.request_types
        }
      : item
  );

const identity = row => ({
  id: Number(row.id),
  version: Number(row.version)
});

const reason = () =>
  field('reason', 'textarea');

const comments = () =>
  field('comments', 'textarea');

const approvalRemarks = () =>
  field(
    'comments',
    'textarea',
    true,
    null,
    {
      label: 'Remarks',
      description:
        'Required. This remark is saved in the workflow history.'
    }
  );

const singular = module =>
  SINGULAR_NAMES[module] || 'record';

function recordLabel(module, row) {
  const labels = {
    softcopy: [row.document_number, row.title]
      .filter(Boolean)
      .join(' — '),
    hardcopy: row.title,
    requests: row.reference,
    my_requests: row.reference,
    my_tasks: row.reference,
    transfers:
      row.document_copy_number ||
      'Hardcopy transfer',
    access: 'Document access',
    assignments: 'Document assignment',
    disposals: 'Disposal record',
    files: row.original_name,
    workflows: row.name,
    users:
      [
        row.first_name,
        row.middle_name,
        row.last_name
      ]
        .filter(Boolean)
        .join(' ') || row.username,
    roles: row.name,
    permissions: row.name,
    areas: row.name,
    specifics: row.name,
    assets: row.asset_number,
    locations: [row.code, row.name]
      .filter(Boolean)
      .join(' — '),
    categories: row.name,
    notifications: row.title,
    audit: [row.module, row.action]
      .filter(Boolean)
      .map(labelOf)
      .join(' — '),
    history: [row.domain, row.action]
      .filter(Boolean)
      .map(labelOf)
      .join(' — '),
    sequences: row.sequence_key,
    settings: row.setting_key
  };

  return labels[module] || labelOf(module);
}

const HIDDEN_DETAIL_KEYS = new Set([
  'id',
  'version',
  'graph',
  'payload',
  'snapshot',
  'result',
  'config',
  'value',
  'candidates',
  'assignment',
  'before_state',
  'after_state',
  'previous_state'
]);

function readableDetails(
  data,
  title = 'Details'
) {
  if (
    !data ||
    typeof data !== 'object' ||
    Array.isArray(data)
  ) {
    return null;
  }

  const entries = Object.entries(data).filter(
    ([key, value]) =>
      !HIDDEN_DETAIL_KEYS.has(key) &&
      !key.endsWith('_id') &&
      value !== null &&
      value !== '' &&
      typeof value !== 'object'
  );

  if (!entries.length) {
    return null;
  }

  const section = el(
    'section',
    {},
    el('h3', {}, title)
  );

  for (const [key, value] of entries) {
    section.append(
      el(
        'p',
        {},
        el(
          'strong',
          {},
          labelOf(key) + ': '
        ),
        String(value)
      )
    );
  }

  return section;
}

function globalError(error) {
  status.textContent = error.message;
}

async function refresh() {
  if (currentModule) {
    await loadTable();
  }
}

function afterChange(parent) {
  return async () => {
    parent?.forceCloseAfterSuccess();
    await refresh();
  };
}

function login() {
  return formModal(
    'Sign in',
    [
      field('username'),
      field('password', 'password')
    ],
    {},
    api,
    values =>
      api.request(
        'auth.login',
        values,
        true
      ),
    {
      submitLabel: 'Sign in',
      closeOnSuccess: true,
      after: boot,
      explanation:
        'Use an administrator-created account. Public registration is not available.'
    }
  );
}

function password() {
  return formModal(
    'Change password',
    [
      field(
        'current_password',
        'password'
      ),
      field(
        'new_password',
        'password'
      ),
      field(
        'confirm_password',
        'password'
      )
    ],
    {},
    api,
    values =>
      api.request(
        'auth.password',
        values,
        true
      ),
    {
      after: boot,
      explanation:
        'Use a new password of 12–72 bytes. Changing it invalidates your other sessions.'
    }
  );
}

async function boot() {
  currentModule = null;
  listGeneration++;

  navigation.replaceChildren();
  content.replaceChildren();
  accountActions.replaceChildren();

  try {
    const session = await api.request('session');

    user = session.user;
    permissions = session.permissions;

    if (!user) {
      account.textContent = 'Not signed in.';
      status.textContent = 'Sign in to continue.';

      accountActions.append(
        button('Sign in', login)
      );

      login();
      return;
    }

    account.textContent =
      user.first_name +
      ' ' +
      user.last_name +
      ' — ' +
      user.position_title;

    accountActions.append(
      button(
        'Change password',
        password
      ),
      button(
        'Sign out',
        () =>
          actionModal(
            'Sign out',
            api,
            'auth.logout',
            {},
            [],
            {
              explanation:
                'End this signed-in session?',
              after: boot,
              closeOnSuccess: true
            }
          )
      )
    );

    if (Number(user.require_password_change)) {
      status.textContent =
        'You must change your initial password before accessing any module.';

      password();
      return;
    }

    metadata = await api.request('metadata');
    permissions = metadata.permissions;

    if (can('dashboard.view')) {
      navigation.append(
        button('Dashboard', dashboard)
      );
    }

    const navigationGroups = new Map();

    for (const module of metadata.modules) {
      if (module.navigation_hidden) {
        continue;
      }

      const groupName =
        module.navigation_group || 'Other';

      if (!navigationGroups.has(groupName)) {
        const group = el(
          'details',
          {},
          el('summary', {}, groupName),
          el('div', { 'data-navigation-items': groupName })
        );

        navigationGroups.set(
          groupName,
          group.querySelector(
            '[data-navigation-items]'
          )
        );

        navigation.append(group);
      }

      navigationGroups
        .get(groupName)
        .append(
          button(
            module.label,
            () => selectModule(module)
          )
        );
    }

    status.textContent = 'Ready.';

    const initialModuleKey = document
      .querySelector(
        'meta[name=initial-module]'
      )
      ?.content;

    const initial = metadata.modules.find(
      module =>
        module.key === initialModuleKey
    );

    if (initial) {
      await selectModule(initial);
    } else if (can('dashboard.view')) {
      await dashboard();
    }
  } catch (error) {
    globalError(error);

    accountActions.append(
      button(
        'Retry connection',
        boot
      )
    );
  }
}

async function dashboard() {
  currentModule = null;
  listGeneration++;

  content.replaceChildren(
    el('h2', {}, 'Dashboard'),
    el('p', {}, 'Loading…')
  );

  try {
    const data = await api.request('dashboard');

    if (currentModule) {
      return;
    }

    content.replaceChildren(
      el('h2', {}, 'Dashboard')
    );

    for (const [name, value] of Object.entries(data)) {
      const block = Array.isArray(value)
        ? el(
            'section',
            {},
            el('h3', {}, labelOf(name)),
            table(
              ['status', 'total'],
              value
            )
          )
        : el(
            'p',
            {},
            labelOf(name) + ': ' + value
          );

      content.append(block);
    }
  } catch (error) {
    globalError(error);
  }
}

// Ari ta mag-build sa actions per module para one place ra ang rules sa buttons.
function addModuleActions(module, controls) {
  const isDocumentModule =
    ['softcopy', 'hardcopy'].includes(
      module.key
    );

  if (isDocumentModule) {
    if (can(module.key + '.direct')) {
      controls.append(
        button(
          'Direct create ' + module.key,
          () => directDocument(module.key)
        )
      );
    }

    if (
      can('requests.add') &&
      can(module.key + '.request')
    ) {
      controls.append(
        button(
          'New document request',
          () =>
            requestForm(
              null,
              {
                type:
                  module.key + '_create'
              }
            )
        )
      );
    }
  } else if (
    module.fields.length &&
    module.key !== 'settings' &&
    can(module.key + '.add')
  ) {
    controls.append(
      button(
        'Add ' + singular(module.key),
        () => editCatalog(module)
      )
    );
  }

  const requestModules = [
    'requests',
    'my_requests',
    'my_tasks',
    'transfers',
    'access',
    'assignments',
    'disposals'
  ];

  if (
    requestModules.includes(module.key) &&
    can('requests.add')
  ) {
    controls.append(
      button(
        'New request',
        () => requestForm()
      )
    );
  }

  if (
    module.key === 'files' &&
    can('files.upload')
  ) {
    controls.append(
      button(
        'Upload file',
        () =>
          formModal(
            'Upload private file',
            [
              field(
                'file_id',
                'upload'
              )
            ],
            {},
            api,
            values => ({
              id: values.file_id,
              message:
                'Private file saved. Use it in a document/revision or attach it from a document dialog.'
            }),
            {
              after: refresh
            }
          )
      )
    );
  }
}

// Search first, filters next, table dayon, then pagination sa ubos para klaro ang flow.
async function selectModule(module) {
  currentModule = module;
  page = 1;
  query = '';
  sort = module.columns[0];
  direction = 'desc';
  statusFilter = '';
  layout = 'table';

  content.replaceChildren(
    el('h2', {}, module.label)
  );

  const controls = el('div');
  addModuleActions(module, controls);

  const search = el('input', {
    type: 'search',
    id: 'table-search',
    placeholder: 'Search records',
    value: query
  });

  const searchForm = el(
    'form',
    {
      onsubmit: event => {
        event.preventDefault();
        query = search.value;
        page = 1;
        loadTable().catch(globalError);
      }
    },
    el(
      'label',
      { htmlFor: 'table-search' },
      'Search'
    ),
    search,
    el(
      'button',
      { type: 'submit' },
      'Search'
    )
  );

  const pageSize = el(
    'select',
    { id: 'page-size' },
    [10, 25, 50, 100].map(size =>
      el(
        'option',
        { value: size },
        size
      )
    )
  );

  pageSize.value = limit;

  pageSize.addEventListener(
    'change',
    () => {
      limit = Number(pageSize.value);
      page = 1;
      loadTable().catch(globalError);
    }
  );

  const sorting = el(
    'select',
    { id: 'table-sort' },
    module.columns.map(column =>
      el(
        'option',
        { value: column },
        labelOf(column)
      )
    )
  );

  sorting.value = sort;

  sorting.addEventListener(
    'change',
    () => {
      sort = sorting.value;
      page = 1;
      loadTable().catch(globalError);
    }
  );

  const order = button(
    'Reverse order',
    () => {
      direction =
        direction === 'asc'
          ? 'desc'
          : 'asc';

      return loadTable();
    }
  );

  const filters = el(
    'div',
    { id: 'table-filters' },
    el(
      'label',
      { htmlFor: 'table-sort' },
      'Sort by'
    ),
    sorting,
    order,
    button(
      'Refresh records',
      loadTable
    )
  );

  if (module.columns.includes('status')) {
    const statusSelect = el(
      'select',
      { id: 'status-filter' },
      [
        '',
        'active',
        'draft',
        'pending',
        'returned',
        'approved',
        'rejected',
        'cancelled',
        'disposed',
        'completed'
      ].map(value =>
        el(
          'option',
          { value },
          value ? labelOf(value) : 'All statuses'
        )
      )
    );

    statusSelect.addEventListener(
      'change',
      () => {
        statusFilter = statusSelect.value;
        page = 1;
        loadTable().catch(globalError);
      }
    );

    filters.append(
      el(
        'label',
        { htmlFor: 'status-filter' },
        'Status'
      ),
      statusSelect
    );
  }

  if (
    ['softcopy', 'hardcopy'].includes(
      module.key
    )
  ) {
    const layoutSelect = el(
      'select',
      { id: 'layout-select' },
      [
        ['table', 'Table'],
        ['grid', 'Grid'],
        ['folder', 'Folder']
      ].map(([value, name]) =>
        el(
          'option',
          { value },
          name
        )
      )
    );

    layoutSelect.addEventListener(
      'change',
      () => {
        layout = layoutSelect.value;
        loadTable().catch(globalError);
      }
    );

    filters.append(
      el(
        'label',
        { htmlFor: 'layout-select' },
        'Layout'
      ),
      layoutSelect
    );
  }

  const tableFooter = el(
    'div',
    { id: 'table-footer' },
    el('span', { id: 'page-summary' }),
    el(
      'label',
      { htmlFor: 'page-size' },
      'Rows per page'
    ),
    pageSize,
    el('span', { id: 'pagination' })
  );

  content.append(
    controls,
    searchForm,
    filters,
    el(
      'p',
      {
        id: 'table-status',
        role: 'status'
      }
    ),
    el(
      'div',
      { id: 'table-container' }
    ),
    tableFooter
  );

  await loadTable();
}

function recordCard(module, row, actions) {
  const card = el(
    'article',
    {},
    el('h3', {}, recordLabel(module.key, row))
  );

  for (const column of module.columns) {
    if (
      column === 'id' ||
      column.endsWith('_id')
    ) {
      continue;
    }

    const value = row[column];

    card.append(
      el(
        'p',
        {},
        el('strong', {}, labelOf(column) + ': '),
        value ?? '—'
      )
    );
  }

  if (actions) {
    card.append(actions(row));
  }

  return card;
}

function folderTree(module, rows, actions) {
  const hierarchy =
    module.key === 'hardcopy'
      ? ['area', 'specific', 'asset', 'location']
      : ['parent_category', 'category'];

  const build = (items, depth) => {
    if (depth >= hierarchy.length) {
      const leaf = el('div');

      for (const row of items) {
        leaf.append(
          recordCard(
            module,
            row,
            actions
          )
        );
      }

      return leaf;
    }

    const key = hierarchy[depth];
    const groups = new Map();

    for (const row of items) {
      const name =
        row[key] ||
        (depth === 0
          ? 'Unassigned'
          : 'Other');

      if (!groups.has(name)) {
        groups.set(name, []);
      }

      groups.get(name).push(row);
    }

    const container = el('div');

    for (const [name, groupRows] of groups) {
      const folder = el(
        'details',
        {},
        el('summary', {}, name),
        build(groupRows, depth + 1)
      );

      container.append(folder);
    }

    return container;
  };

  return build(rows, 0);
}

function renderRows(module, rows, actions) {
  if (layout === 'grid') {
    const grid = el('div');

    for (const row of rows) {
      grid.append(
        recordCard(
          module,
          row,
          actions
        )
      );
    }

    if (!rows.length) {
      grid.append(
        el('p', {}, 'No records found.')
      );
    }

    return grid;
  }

  if (layout === 'folder') {
    return folderTree(
      module,
      rows,
      actions
    );
  }

  return table(
    module.columns,
    rows,
    actions
  );
}

async function loadTable() {
  if (!currentModule) {
    return;
  }

  const module = currentModule;
  const generation = ++listGeneration;
  const message = document.querySelector(
    '#table-status'
  );

  if (!message) {
    return;
  }

  message.textContent = 'Loading records…';

  try {
    const result = await api.request(
      'list',
      {
        module: module.key,
        page,
        limit,
        q: query,
        status: statusFilter || undefined,
        sort,
        direction
      }
    );

    if (
      generation !== listGeneration ||
      currentModule !== module
    ) {
      return;
    }

    if (page > result.pages) {
      page = result.pages;
      return loadTable();
    }

    const actions =
      module.key === 'sequences'
        ? null
        : row =>
            button(
              'View / actions',
              () =>
                details(
                  module.key,
                  row.id
                )
            );

    const tableContainer =
      document.querySelector(
        '#table-container'
      );

    tableContainer.replaceChildren(
      renderRows(
        module,
        result.rows,
        actions
      )
    );

    if (
      module.key === 'hardcopy' &&
      can('requests.view')
    ) {
      const drafts = await api.request(
        'list',
        {
          module: 'my_requests',
          page: 1,
          limit: 25,
          q: 'hardcopy_create',
          status: 'draft',
          sort: 'created_at',
          direction: 'desc'
        }
      );

      if (
        generation === listGeneration &&
        currentModule === module &&
        drafts.rows.length
      ) {
        tableContainer.append(
          el(
            'section',
            {},
            el(
              'h3',
              {},
              'Draft hardcopy requests'
            ),
            el(
              'p',
              {},
              'These requests are not active hardcopy documents yet.'
            ),
            table(
              [
                'reference',
                'type',
                'status',
                'created_at'
              ],
              drafts.rows,
              row =>
                button(
                  'Open draft',
                  () =>
                    details(
                      'my_requests',
                      row.id
                    )
                )
            )
          )
        );
      }
    }

    message.textContent =
      result.total +
      ' matching records.';

    document
      .querySelector('#page-summary')
      .textContent =
        'Page ' +
        result.page +
        ' of ' +
        result.pages +
        '. ';

    document
      .querySelector('#pagination')
      .replaceChildren(
        button(
          'Previous',
          () => {
            page--;
            return loadTable();
          },
          { disabled: page <= 1 }
        ),
        button(
          'Next',
          () => {
            page++;
            return loadTable();
          },
          {
            disabled:
              page >= result.pages
          }
        )
      );
  } catch (error) {
    if (generation !== listGeneration) {
      return;
    }

    message.textContent = error.message;

    document
      .querySelector('#table-container')
      .replaceChildren();

    const summary = document.querySelector(
      '#page-summary'
    );

    if (summary) {
      summary.textContent = '';
    }
  }
}

function editCatalog(module, row = {}, parent) {
  const existing = !!row.id;
  const operation =
    module.key === 'workflows'
      ? 'workflows.save'
      : 'catalog.save';

  const title = existing
    ? 'Edit ' + singular(module.key)
    : 'Add ' + singular(module.key);

  const disabled = existing
    ? module.key === 'permissions'
      ? ['module_key', 'action_key']
      : []
    : [];

  const explanation =
    module.key === 'workflows'
      ? 'New workflows start inactive with an empty draft. Add ordered approval steps, publish the version, then choose the published Default version that new requests should use.'
      : module.key === 'settings'
        ? 'Appearance preferences are stored but no styling is applied in this functional build.'
        : '';

  return formModal(
    title,
    fieldsFor(module.fields),
    row,
    api,
    values =>
      api.request(
        operation,
        {
          module: module.key,
          ...(existing
            ? identity(row)
            : {}),
          ...values
        },
        true
      ),
    {
      after: afterChange(parent),
      disabled,
      explanation
    }
  );
}

function directDocument(
  domain,
  row = {},
  parent
) {
  const existing = !!row.id;
  const values = {
    ...row,
    file_id: null,
    reason: ''
  };

  const title = existing
    ? 'Direct ' +
      (domain === 'softcopy'
        ? 'revision'
        : 'update')
    : 'Direct create ' + domain;

  const disabled = existing
    ? domain === 'hardcopy'
      ? [
          'area_id',
          'specific_id',
          'asset_id',
          'location_id',
          'holder_id'
        ]
      : ['document_number']
    : [];

  const explanation =
    domain === 'hardcopy' && existing
      ? 'Update metadata here. Use a transfer request to change physical location or holder.'
      : 'Direct changes require dedicated authorization and an audit reason. Softcopy changes always create a preserved revision.';

  return formModal(
    title,
    metadata.document_fields[domain],
    values,
    api,
    data =>
      api.request(
        'documents.direct',
        {
          domain,
          ...(existing
            ? identity(row)
            : {}),
          ...data
        },
        true
      ),
    {
      after: afterChange(parent),
      disabled,
      explanation
    }
  );
}

// Diri ta mag-sync sa button preset para sakto gyud ang request type pag-open sa modal.
function requestForm(
  existing = null,
  preset = {},
  parent
) {
  const allowedTypes =
    metadata.request_types || [];

  if (
    !existing &&
    preset.type &&
    !allowedTypes.includes(preset.type)
  ) {
    throw new Error(
      'Request type ' +
      preset.type +
      ' is not available. Refresh the page and try again.'
    );
  }

  const presetType =
    !existing && preset.type
      ? preset.type
      : null;

  const initialType =
    existing?.type ||
    presetType ||
    allowedTypes[0] ||
    'softcopy_create';

  let type = allowedTypes.includes(initialType)
    ? initialType
    : allowedTypes[0] ||
      'softcopy_create';

  // Specific action buttons are locked; generic New Request remains editable.
  const lockedPreset = !!presetType;

  const fixedDomainFor = requestType => {
    if (
      requestType.startsWith('softcopy') ||
      requestType === 'assignment'
    ) {
      return 'softcopy';
    }

    if (
      requestType.startsWith('hardcopy') ||
      requestType === 'transfer'
    ) {
      return 'hardcopy';
    }

    return null;
  };

  const initialDomain = existing?.softcopy_id
    ? 'softcopy'
    : existing?.hardcopy_id
      ? 'hardcopy'
      : preset.domain;

  let domain =
    fixedDomainFor(type) ||
    (['softcopy', 'hardcopy'].includes(
      initialDomain
    )
      ? initialDomain
      : 'softcopy');

  let target =
    existing?.softcopy_id ||
    existing?.hardcopy_id ||
    preset.document_id ||
    null;

  let values =
    existing?.payload ||
    preset.values ||
    {};

  let head;
  let payload;

  const modalTitle = existing
    ? 'Edit request draft'
    : preset.type
      ? labelOf(type) + ' request'
      : 'New request';

  const modal = new Modal(
    modalTitle,
    {
      explanation:
        'Save a draft first. Then open it in My requests to submit. Request approval and document creation are separate operations.'
    }
  );

  const heading = el('div');
  const body = el('div');

  modal.body.append(
    heading,
    body
  );

  const syncDomain = (
    requestType,
    preferredDomain = domain
  ) => {
    const fixed = fixedDomainFor(
      requestType
    );

    domain =
      fixed ||
      (['softcopy', 'hardcopy'].includes(
        preferredDomain
      )
        ? preferredDomain
        : 'softcopy');
  };

  const renderPayload = async () => {
    payload?.dispose();
    body.replaceChildren();

    const disabledPayload =
      existing?.type?.startsWith(
        'softcopy'
      ) &&
      type !== 'softcopy_create'
        ? ['document_number']
        : [];

    let requestFields =
      metadata.request_fields[type] || [];

    const automaticHolder =
      type === 'hardcopy_create' &&
      !can('requests.manage') &&
      !can('hardcopy.direct');

    if (automaticHolder) {
      requestFields =
        requestFields.filter(
          definition =>
            definition.name !== 'holder_id'
        );
    }

    payload = await mountFields(
      body,
      requestFields,
      values,
      api,
      {
        disabled: disabledPayload
      }
    );

    if (automaticHolder) {
      body.prepend(
        el(
          'p',
          {},
          el('strong', {}, 'Holder: '),
          [
            user.first_name,
            user.middle_name,
            user.last_name
          ]
            .filter(Boolean)
            .join(' ') +
            ' (requester, automatic)'
        )
      );
    }
  };

  const renderHead = async () => {
    head?.dispose();
    heading.replaceChildren();
    syncDomain(type);

    const creates =
      type.endsWith('_create');

    const descriptors = [
      field(
        'type',
        'select',
        true,
        null,
        {
          label: 'Request Type',
          options: allowedTypes
        }
      )
    ];

    if (
      ['access', 'disposal'].includes(type)
    ) {
      descriptors.push(
        field(
          'domain',
          'select',
          true,
          null,
          {
            options: [
              'softcopy',
              'hardcopy'
            ]
          }
        )
      );
    }

    if (!creates) {
      descriptors.push(
        field(
          'document_id',
          'lookup',
          true,
          domain,
          {
            label:
              domain === 'softcopy'
                ? 'Softcopy Document'
                : 'Hardcopy Document'
          }
        )
      );
    }

    const disabledHead = existing
      ? [
          'type',
          'domain',
          'document_id'
        ]
      : lockedPreset
        ? [
            'type',
            ...(preset.domain
              ? ['domain']
              : []),
            ...(preset.document_id
              ? ['document_id']
              : [])
          ]
        : [];

    head = await mountFields(
      heading,
      descriptors,
      {
        type,
        domain,
        document_id: target
      },
      api,
      {
        disabled: disabledHead
      }
    );

    const typeControl =
      head.controls.get('type');

    typeControl.value = type;

    typeControl.addEventListener(
      'change',
      event => {
        if (lockedPreset || existing) {
          return;
        }

        modal.run(async () => {
          const nextType =
            event.currentTarget.value;

          if (
            !allowedTypes.includes(nextType) ||
            nextType === type
          ) {
            return;
          }

          type = nextType;
          syncDomain(type);
          target = null;
          values = {};

          await renderHead();
          await renderPayload();
        });
      }
    );

    const domainControl =
      head.controls.get('domain');

    domainControl?.addEventListener(
      'change',
      event => {
        if (
          (lockedPreset && preset.domain) ||
          existing
        ) {
          return;
        }

        modal.run(async () => {
          const nextDomain =
            event.currentTarget.value;

          domain =
            ['softcopy', 'hardcopy'].includes(
              nextDomain
            )
              ? nextDomain
              : 'softcopy';

          target = null;
          values = {};

          await renderHead();
          await renderPayload();
        });
      }
    );

    const documentControl =
      head.controls.get('document_id');

    documentControl?.addEventListener(
      'change',
      event => {
        if (
          (lockedPreset && preset.document_id) ||
          existing
        ) {
          return;
        }

        modal.run(async () => {
          target =
            Number(event.currentTarget.value) ||
            null;

          if (
            target &&
            [
              'softcopy_revise',
              'hardcopy_update'
            ].includes(type)
          ) {
            const data = await api.request(
              'detail',
              {
                module: domain,
                id: target
              }
            );

            values = {
              ...data.row,
              file_id: null,
              reason: ''
            };

            await renderPayload();
          }
        });
      }
    );
  };

  modal.setSubmit(
    'Save draft',
    async () => {
      const first = await head.read();
      const data = await payload.read();

      type = first.type || type;

      syncDomain(
        type,
        first.domain || domain
      );

      const selected =
        type.endsWith('_create')
          ? null
          : Number(first.document_id);

      const result = await api.request(
        'requests.save',
        {
          ...(existing
            ? identity(existing)
            : {}),
          type,
          softcopy_id:
            domain === 'softcopy'
              ? selected
              : null,
          hardcopy_id:
            domain === 'hardcopy'
              ? selected
              : null,
          payload: data
        },
        true
      );

      await afterChange(parent)();
      modal.done(result);

      modal.body.append(
        button(
          'Open saved request',
          () => {
            modal.forceCloseAfterSuccess();

            return details(
              'my_requests',
              result.id
            );
          }
        )
      );
    }
  );

  modal
    .run(async () => {
      syncDomain(
        type,
        initialDomain
      );

      await renderHead();
      await renderPayload();
    })
    .then(() => modal.focusFirst());

  modal.node.addEventListener(
    'close',
    () => {
      head?.dispose();
      payload?.dispose();
    }
  );

  return modal;
}

async function details(moduleKey, id) {
  const definition =
    metadata.modules.find(
      module => module.key === moduleKey
    ) ||
    metadata.modules.find(
      module => module.key === 'requests'
    );

  const modal = new Modal(
    definition?.label || labelOf(moduleKey)
  );

  modal.closeButton.textContent = 'Close';

  await modal.run(async () => {
    const result = await api.request(
      'detail',
      {
        module: moduleKey,
        id
      }
    );

    const row = result.row;
    const related = result.related;

    modal.setTitle(
      recordLabel(moduleKey, row)
    );

    const actions = el(
      'section',
      {
        'aria-label': 'Record actions'
      }
    );

    modal.body.append(actions);

    const change = afterChange(modal);

    const action = (
      title,
      operation,
      fixed,
      fields = [],
      options = {}
    ) => {
      actions.append(
        button(
          title,
          () =>
            actionModal(
              title,
              api,
              operation,
              fixed,
              fields,
              {
                after: change,
                ...options
              }
            )
        )
      );
    };

    if (
      definition?.fields.length &&
      (
        moduleKey === 'workflows'
          ? can('workflows.edit')
          : can(moduleKey + '.edit')
      )
    ) {
      actions.append(
        button(
          'Edit',
          () =>
            editCatalog(
              definition,
              row,
              modal
            )
        )
      );
    }

    if (
      can(moduleKey + '.delete') &&
      definition?.fields.length &&
      !['settings', 'workflows'].includes(
        moduleKey
      )
    ) {
      action(
        moduleKey === 'users'
          ? 'Deactivate account'
          : 'Delete unused record',
        'catalog.delete',
        {
          module: moduleKey,
          ...identity(row)
        },
        [reason()],
        {
          explanation:
            moduleKey === 'users'
              ? 'Deactivate this account without deleting its history?'
              : 'Only unused catalogue records can be deleted. Referenced records are protected.'
        }
      );
    }

    if (
      moduleKey === 'users' &&
      can('users.edit') &&
      Number(row.id) !== Number(user.id)
    ) {
      action(
        'Reset initial password',
        'users.reset_password',
        identity(row),
        [reason()]
      );
    }

    if (
      moduleKey === 'roles' &&
      can('roles.edit')
    ) {
      actions.append(
        button(
          'Assign permissions',
          () =>
            permissionsModal(
              row,
              related,
              modal
            )
        )
      );
    }

    if (
      ['softcopy', 'hardcopy'].includes(
        moduleKey
      )
    ) {
      const domain = moduleKey;
      const active =
        row.status === 'active';

      if (
        active &&
        can(domain + '.direct')
      ) {
        actions.append(
          button(
            domain === 'softcopy'
              ? 'Direct revision'
              : 'Direct update',
            () =>
              directDocument(
                domain,
                row,
                modal
              )
          )
        );
      }

      if (
        active &&
        can('requests.add')
      ) {
        if (can('access.request')) {
          actions.append(
            button(
              'Request access',
              () =>
                requestForm(
                  null,
                  {
                    type: 'access',
                    domain,
                    document_id: row.id
                  },
                  modal
                )
            )
          );
        }

        const requestTypes =
          domain === 'softcopy'
            ? [
                'softcopy_revise',
                'softcopy_cancel',
                'assignment',
                'disposal'
              ]
            : [
                'hardcopy_update',
                'transfer',
                'disposal'
              ];

        if (related.can_read_files) {
          for (const requestType of requestTypes) {
            const capability =
              requestType.startsWith(domain)
                ? domain + '.request'
                : requestType + '.request';

            if (!can(capability)) {
              continue;
            }

            const initialValues = [
              'softcopy_revise',
              'hardcopy_update'
            ].includes(requestType)
              ? {
                  ...row,
                  file_id: null,
                  reason: ''
                }
              : {};

            actions.append(
              button(
                labelOf(requestType) +
                  ' request',
                () =>
                  requestForm(
                    null,
                    {
                      type: requestType,
                      domain,
                      document_id: row.id,
                      values: initialValues
                    },
                    modal
                  )
              )
            );
          }
        }
      }

      if (
        active &&
        related.can_read_files &&
        can('files.upload')
      ) {
        action(
          'Add attachment',
          'files.attach',
          {
            domain,
            document_id: row.id
          },
          [
            field('file_id', 'upload'),
            reason()
          ]
        );
      }

      if (related.revisions) {
        modal.body.append(
          el(
            'h3',
            {},
            'Revision history'
          ),
          table(
            [
              'revision_number',
              'revision_status',
              'document_title',
              'effective_date',
              'new_revision_level'
            ],
            related.revisions,
            revision => {
              const revisionActions = [];

              if (related.can_read_files) {
                const sourceFile =
                  related.files?.find(
                    file =>
                      Number(file.id) ===
                      Number(revision.file_id)
                  );

                revisionActions.push(
                  button(
                    'Download revision',
                    () =>
                      downloadModal(
                        revision.file_id,
                        sourceFile?.original_name ||
                          'revision-' +
                            revision.revision_number
                      )
                  )
                );
              }

              if (
                related.can_read_files &&
                can('files.generate')
              ) {
                const artifactOptions =
                  active &&
                  revision.revision_status ===
                    'current'
                    ? [
                        'controlled',
                        'uncontrolled'
                      ]
                    : ['uncontrolled'];

                revisionActions.push(
                  button(
                    'Generate artifact',
                    () =>
                      actionModal(
                        'Generate revision artifact',
                        api,
                        'files.artifact',
                        {
                          revision_id:
                            revision.id
                        },
                        [
                          field(
                            'artifact_type',
                            'select',
                            true,
                            null,
                            {
                              options:
                                artifactOptions
                            }
                          )
                        ],
                        { after: change }
                      )
                  )
                );
              }

              return revisionActions;
            }
          )
        );
      }

      if (related.files) {
        modal.body.append(
          el(
            'h3',
            {},
            'Document files'
          ),
          table(
            [
              'original_name',
              'purpose',
              'status'
            ],
            related.files,
            file =>
              button(
                'File actions',
                () =>
                  fileModal(
                    file,
                    change
                  )
              )
          )
        );
      }
    }

    if (
      ['requests', 'my_requests', 'my_tasks'].includes(
        moduleKey
      )
    ) {
      const own =
        Number(row.requested_by) ===
        Number(user.id);

      const pending =
        related.steps?.find(
          step =>
            step.status === 'pending'
        );

      if (
        own &&
        ['draft', 'returned'].includes(
          row.status
        )
      ) {
        if (can('requests.edit')) {
          actions.append(
            button(
              'Edit draft',
              () =>
                requestForm(
                  row,
                  {},
                  modal
                )
            )
          );
        }

        if (can('requests.submit')) {
          action(
            'Submit request',
            'requests.submit',
            identity(row),
            [],
            {
              explanation:
                'Submit this saved request to its published approval workflow?'
            }
          );
        }
      }

      if (
        can('requests.cancel') &&
        (own || can('requests.manage')) &&
        ['draft', 'returned', 'pending'].includes(
          row.status
        )
      ) {
        action(
          'Cancel request',
          'requests.cancel',
          identity(row),
          [reason()]
        );
      }

      const canDecide =
        pending &&
        pending.candidates?.some(
          candidate =>
            Number(candidate.id) ===
            Number(user.id)
        );

      if (canDecide) {
        const decisionUi = {
          approve: {
            title: 'Approve',
            submitLabel: 'Submit approval',
            explanation:
              'Enter remarks before approving. The request moves to the next approver; if this is the final step, it becomes approved.'
          },
          return: {
            title: 'Return to previous holder',
            submitLabel: 'Submit return',
            explanation:
              'Enter remarks before returning. The request moves backward to the previous holder for correction or another decision.'
          },
          reject: {
            title: 'Reject request',
            submitLabel: 'Submit rejection',
            explanation:
              'Enter remarks before rejecting. Rejection is final and stops the approval workflow.'
          }
        };

        for (const decision of [
          'approve',
          'return',
          'reject'
        ]) {
          const ui = decisionUi[decision];

          action(
            ui.title,
            'requests.decide',
            {
              ...identity(row),
              step_id: pending.id,
              decision
            },
            [approvalRemarks()],
            {
              submitLabel: ui.submitLabel,
              explanation: ui.explanation
            }
          );
        }

        if (row.payload?.file_id) {
          actions.append(
            button(
              'Review submitted file',
              () =>
                downloadModal(
                  row.payload.file_id,
                  'submitted-document'
                )
            )
          );
        }
      }

      if (
        pending &&
        can('workflows.reassign')
      ) {
        action(
          'Reassign approver',
          'workflows.reassign',
          {
            step_id: pending.id,
            version: row.version
          },
          [
            field(
              'user_id',
              'lookup',
              true,
              'users'
            ),
            reason()
          ]
        );
      }

      if (related.transfer) {
        actions.append(
          button(
            'Open transfer',
            () =>
              details(
                'transfers',
                related.transfer.id
              )
          )
        );
      }
    }

    if (moduleKey === 'transfers') {
      const sender =
        Number(row.current_holder_id) ===
          Number(user.id) ||
        can('transfer.manage');

      if (
        row.status === 'for_transfer' &&
        sender
      ) {
        action(
          'Record physical delivery',
          'transfers.dispatch',
          identity(row),
          [comments()],
          {
            explanation:
              'Confirm the document has physically been delivered. This does not change its recorded location until the named recipient accepts.'
          }
        );

        action(
          'Cancel undispatched transfer',
          'transfers.cancel',
          identity(row),
          [reason()]
        );
      }

      if (
        row.status ===
          'pending_recipient_acceptance' &&
        Number(row.recipient_id) ===
          Number(user.id)
      ) {
        for (const decision of [
          'accepted',
          'refused'
        ]) {
          action(
            decision === 'accepted'
              ? 'Accept receipt'
              : 'Refuse receipt',
            'transfers.receive',
            {
              ...identity(row),
              decision
            },
            [comments()]
          );
        }
      }
    }

    if (
      moduleKey === 'access' &&
      row.status === 'access_granted' &&
      (
        Number(row.user_id) ===
          Number(user.id) ||
        can('access.revoke')
      )
    ) {
      action(
        Number(row.user_id) === Number(user.id)
          ? 'Return access'
          : 'Revoke access',
        'access.revoke',
        identity(row),
        [reason()]
      );
    }

    if (
      moduleKey === 'assignments' &&
      Number(row.active) &&
      can('assignment.manage')
    ) {
      action(
        'Remove assignment',
        'assignments.remove',
        identity(row),
        [reason()]
      );
    }

    if (
      moduleKey === 'notifications' &&
      !row.read_at &&
      can('notifications.edit')
    ) {
      action(
        'Mark as read',
        'notifications.read',
        identity(row)
      );
    }

    if (moduleKey === 'files') {
      fileActions(
        actions,
        row,
        change
      );
    }

    if (moduleKey === 'workflows') {
      if (can('workflows.edit')) {
        actions.append(
          button(
            'New draft version',
            () =>
              workflowVersionModal(
                row,
                null,
                api,
                metadata.workflow_template,
                change
              )
          )
        );
      }

      const versions =
        (related.versions || []).map(
          version => ({
            ...version,
            default_version:
              Number(version.is_default)
                ? 'Yes'
                : 'No'
          })
        );

      modal.body.append(
        el(
          'h3',
          {},
          'Workflow versions'
        ),
        table(
          [
            'version_number',
            'status',
            'default_version',
            'published_at'
          ],
          versions,
          version => {
            const steps =
              version.graph?.steps || [];

            const sequence =
              steps
                .map(
                  (step, index) =>
                    (index + 1) +
                    '. ' +
                    step.name +
                    ' — ' +
                    (
                      step.approver?.label ||
                      labelOf(
                        step.approver?.type ||
                          ''
                      )
                    )
                )
                .join('\n') ||
              'No approval steps.';

            const versionActions = [
              button(
                'View approval sequence',
                () =>
                  notice(
                    'Approval sequence',
                    sequence
                  )
              )
            ];

            if (can('workflows.edit')) {
              versionActions.push(
                button(
                  version.status === 'draft'
                    ? 'Edit draft steps'
                    : 'Copy to new draft',
                  () =>
                    workflowVersionModal(
                      row,
                      version,
                      api,
                      metadata.workflow_template,
                      change,
                      version.status !== 'draft'
                    )
                )
              );
            }

            if (
              can('workflows.edit') &&
              version.status === 'draft'
            ) {
              versionActions.push(
                button(
                  'Publish version',
                  () =>
                    actionModal(
                      'Publish workflow version',
                      api,
                      'workflows.publish',
                      identity(version),
                      [reason()],
                      {
                        after: change,
                        explanation:
                          'Publish this immutable version. If it is the first usable version, it becomes default automatically.'
                      }
                    )
                )
              );

              versionActions.push(
                button(
                  'Remove draft version',
                  () =>
                    actionModal(
                      'Remove draft workflow version',
                      api,
                      'workflows.delete_version',
                      identity(version),
                      [reason()],
                      {
                        after: change,
                        explanation:
                          'Permanently remove this unpublished draft version. Published or request-linked versions are protected.'
                      }
                    )
                )
              );
            }

            const canSetDefault =
              can('workflows.edit') &&
              version.status === 'published' &&
              (
                !Number(version.is_default) ||
                !Number(row.active)
              );

            if (canSetDefault) {
              versionActions.push(
                button(
                  'Set as default',
                  () =>
                    actionModal(
                      'Set default workflow version',
                      api,
                      'workflows.default',
                      identity(version),
                      [reason()],
                      {
                        after: change,
                        explanation:
                          'New requests of this type will use this version. Existing submitted requests keep their original version.'
                      }
                    )
                )
              );
            }

            return versionActions;
          }
        )
      );
    }

    if (
      ['requests', 'my_requests', 'my_tasks'].includes(
        moduleKey
      )
    ) {
      if (related.workflow_version) {
        const workflow =
          related.workflow_version;

        modal.body.append(
          el(
            'h3',
            {},
            'Workflow'
          ),
          el(
            'p',
            {},
            workflow.workflow_name +
              ' — Version ' +
              workflow.version_number
          )
        );
      }

      if (related.steps?.length) {
        modal.body.append(
          el(
            'h3',
            {},
            'Approval steps'
          ),
          table(
            [
              'label',
              'assigned_name',
              'assigned_position',
              'status',
              'decision',
              'acting_name',
              'comments'
            ],
            related.steps
          )
        );
      }

      if (related.history?.length) {
        modal.body.append(
          el(
            'h3',
            {},
            'Workflow history'
          ),
          table(
            [
              'step_name',
              'action',
              'user_name',
              'position_title',
              'comments',
              'created_at'
            ],
            related.history
          )
        );
      }
    }

    const recordDetails =
      readableDetails(
        row,
        'Record details'
      );

    if (recordDetails) {
      modal.body.append(recordDetails);
    }

    if (
      row.request_details &&
      typeof row.request_details === 'object'
    ) {
      const requestDetails =
        readableDetails(
          row.request_details,
          'Request details'
        );

      if (requestDetails) {
        modal.body.append(requestDetails);
      }
    }

    const handledRelated = [
      'files',
      'revisions',
      'versions',
      'available_permissions',
      'steps',
      'history',
      'workflow_version'
    ];

    for (const [key, value] of Object.entries(related)) {
      if (
        handledRelated.includes(key) ||
        Array.isArray(value)
      ) {
        continue;
      }

      const relatedDetails =
        readableDetails(
          value,
          labelOf(key)
        );

      if (relatedDetails) {
        modal.body.append(
          relatedDetails
        );
      }
    }
  });

  return modal;
}

function downloadModal(id, name) {
  return formModal(
    'Download document',
    [],
    {},
    api,
    () => api.download(id, name),
    {
      submitLabel: 'Download',
      explanation:
        'Access is checked by the server at download time. A downloaded copy cannot be remotely recalled.'
    }
  );
}

function fileActions(container, file, after) {
  container.append(
    button(
      'Download file',
      () =>
        downloadModal(
          file.id,
          file.original_name
        )
    )
  );

  const isPrivateUpload =
    file.purpose === 'upload' &&
    !file.document_id &&
    Number(file.uploaded_by) === Number(user.id) &&
    can('files.upload');

  if (isPrivateUpload) {
    container.append(
      button(
        'Attach to a document',
        () =>
          formModal(
            'Choose attachment domain',
            [
              field(
                'domain',
                'select',
                true,
                null,
                {
                  options: [
                    'softcopy',
                    'hardcopy'
                  ]
                }
              )
            ],
            {},
            api,
            values => {
              formModal(
                'Attach existing private file',
                [
                  field(
                    'document_id',
                    'lookup',
                    true,
                    values.domain
                  ),
                  reason()
                ],
                {},
                api,
                data =>
                  api.request(
                    'files.attach',
                    {
                      domain: values.domain,
                      file_id: file.id,
                      ...data
                    },
                    true
                  ),
                { after }
              );

              return {
                message:
                  'Choose a document in the attachment dialog.'
              };
            },
            {
              closeOnSuccess: true
            }
          )
      )
    );
  }

  if (
    file.purpose === 'attachment' &&
    file.status === 'pending'
  ) {
    for (const decision of [
      'approved',
      'rejected',
      'cancelled'
    ]) {
      const mayDecide =
        can('files.approve') ||
        (
          decision === 'cancelled' &&
          Number(file.uploaded_by) ===
            Number(user.id)
        );

      if (!mayDecide) {
        continue;
      }

      container.append(
        button(
          labelOf(decision) +
            ' attachment',
          () =>
            actionModal(
              labelOf(decision) +
                ' attachment',
              api,
              'files.decide',
              {
                ...identity(file),
                decision
              },
              [reason()],
              { after }
            )
        )
      );
    }
  }
}

function fileModal(file, after) {
  const modal = new Modal(
    file.original_name || 'File'
  );

  modal.closeButton.textContent = 'Close';

  fileActions(
    modal.body,
    file,
    async () => {
      modal.forceCloseAfterSuccess();
      await after?.();
    }
  );

  const details = readableDetails(
    file,
    'File details'
  );

  if (details) {
    modal.body.append(details);
  }

  return modal;
}

function permissionsModal(
  role,
  related,
  parent
) {
  const modal = new Modal(
    'Assign role permissions',
    {
      explanation:
        'Permissions are checked server-side. Your own recovery capabilities and the last active administrator are protected.'
    }
  );

  const selected = new Set(
    (related.permission_ids || []).map(Number)
  );

  const boxes = [];

  const search = el('input', {
    type: 'search',
    'aria-label': 'Filter permissions',
    placeholder: 'Filter permissions'
  });

  modal.body.append(search);

  for (const permission of related.available_permissions || []) {
    const input = el('input', {
      type: 'checkbox',
      checked: selected.has(
        Number(permission.id)
      )
    });

    const label = el(
      'label',
      {},
      input,
      permission.module_label +
        ' → ' +
        permission.action_label
    );

    const row = el('p', {}, label);

    boxes.push({
      input,
      row,
      permission
    });

    modal.body.append(row);
  }

  search.addEventListener(
    'input',
    () => {
      const filter =
        search.value.toLowerCase();

      for (const item of boxes) {
        const text =
          (
            item.permission.module_label +
            ' ' +
            item.permission.action_label
          ).toLowerCase();

        item.row.hidden =
          !text.includes(filter);
      }
    }
  );

  const reasonInput = el('textarea', {
    id: 'permission-change-reason',
    name: 'reason',
    required: true,
    rows: 3,
    cols: 36
  });

  modal.body.append(
    el(
      'p',
      {},
      el(
        'label',
        {
          htmlFor:
            'permission-change-reason'
        },
        'Reason'
      ),
      el('br'),
      reasonInput
    )
  );

  modal.setSubmit(
    'Save permissions',
    async () => {
      const permissionIds = boxes
        .filter(item => item.input.checked)
        .map(item =>
          Number(item.permission.id)
        );

      const result = await api.request(
        'roles.permissions',
        {
          ...identity(role),
          permission_ids: permissionIds,
          reason: reasonInput.value
        },
        true
      );

      await afterChange(parent)();
      modal.done(result);
    }
  );

  return modal;
}

boot();
