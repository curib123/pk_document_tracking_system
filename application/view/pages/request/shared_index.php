<?php
require_once APPPATH . 'services/presentation/request_title.php';
$tabs = [
  'softcopy' => 'Softcopy Requests',
  'hardcopy' => 'Hardcopy Requests',
  'hardcopy-transfer' => 'Hardcopy Transfer',
  'hardcopy-disposal' => 'Hardcopy Disposal',
  'access-grant' => 'Document Access Grants',
  'document-assign' => 'Document Assignments'
];
$typeMap = [
  'softcopy' => ['softcopy_create' => 'Create', 'softcopy_revise' => 'Revise', 'softcopy_cancel' => 'Cancel'],
  'hardcopy' => ['hardcopy_create' => 'Create', 'hardcopy_update' => 'Update'],
  'hardcopy-transfer' => ['transfer' => 'Transfer'],
  'hardcopy-disposal' => ['disposal' => 'Dispose'],
  'access-grant' => ['access' => 'Grant Access'],
  'document-assign' => ['assignment' => 'Assign']
];
$dt_columns = [
  'reference' => 'Request Reference',
  'title' => 'Document Title',
  'type' => 'Action',
  'subject' => 'Subject',
  'requester' => 'Requester',
  'status' => 'Status',
  'updated' => 'Updated'
];
$canRequest = fn($action) => isset($permissions['*']) || !empty($permissions['requests'][$action]);

$allowed_request_types = $allowed_request_types ?? array_keys($typeMap[$tab]);
$dt_rows = [];
foreach ($rows as $row) {
  $decodedPayload = json_decode((string) ($row['payload'] ?? ''), TRUE);
  $payload = is_array($decodedPayload) ? $decodedPayload : [];
  $entries = $history[$row['id']] ?? [];
  $stepLabels = [];
  foreach ($approval_routes[$row['id']] ?? [] as $step)
    if (!empty($step['step_id']))
      $stepLabels[(int) $step['step_id']] = $step['label'];
  $decisionEvents = [];
  foreach ($entries as $event) {
    $decisionEvents[] = [
      'step_label' => $stepLabels[(int) ($event['step_id'] ?? 0)] ??
        ucwords(str_replace('_', ' ', $event['action'])),
      'action' => $event['action'],
      'user_name' => $event['user_name'],
      'position_title' => $event['position_title'] ?? '',
      'comments' => $event['comments'] ?? '',
      'created_at' => $event['created_at']
    ];
  }
  $historyByStep = [];
  foreach ($entries as $event)
    if (!empty($event['step_id'])) {
      $historyByStep[(int) $event['step_id']][] = [
        'action' => $event['action'],
        'user_name' => $event['user_name'],
        'position_title' => $event['position_title'] ?? '',
        'comments' => $event['comments'] ?? '',
        'created_at' => $event['created_at']
      ];
    }
  $historyText = [];
  foreach ($entries as $h) {
    $historyText[] = ucfirst($h['action']) . ' — ' . $h['user_name'] . ' — ' . $h['created_at'] .
      (!empty($h['comments']) ? ' — ' . $h['comments'] : '');
  }
  $routeText = [];
  $routeSteps = [];
  foreach ($approval_routes[$row['id']] ?? [] as $step) {
    $tile = ['label' => $step['label'], 'approver_label' => $step['approver_label'], 'status' => $step['status']];
    if (!empty($historyByStep[(int) ($step['step_id'] ?? 0)]))
      $tile['history'] = $historyByStep[(int) $step['step_id']];
    $routeSteps[] = $tile;
    $routeText[] = $step['label'] . ' → ' . $step['approver_label'] .
      ' [' . ucwords(str_replace('_', ' ', $step['status'])) . ']';
  }
  // The request viewer uses the same document renderer as System Documents,
  // but presents only submitted proposal metadata and visible approval history.
  // Never pretend an unapproved proposal is already the stored document.
  $isDocumentRequest = in_array($tab, ['softcopy', 'hardcopy', 'hardcopy-disposal'], TRUE);
  $documentDomain = $tab === 'softcopy' ? 'softcopy' : (in_array($tab, ['hardcopy', 'hardcopy-disposal'], TRUE) ? 'hardcopy' : 'record');
  $detail = is_array($row['detail_labels'] ?? NULL) ? $row['detail_labels'] : [];
  $headerTitle = Request_title::resolve(
    (string) $row['type'],
    $payload,
    (string) ($row['source_title'] ?? ''),
    (int) $row['id']
  );
  $requestOverview = [
    'Request Reference' => $row['reference'],
    'Request Action' => ucwords(str_replace('_', ' ', $row['type'])),
    'Subject / Reason' => $payload['subject'] ?? '',
    'Requested By' => $row['requester_name'],
    'Submitted At' => $row['submitted_at'] ?? '',
    'Created At' => $row['created_at'],
    'Last Updated' => $row['updated_at'],
    'Remarks' => $payload['remarks'] ?? '',
    'Request Version' => $row['version']
  ];
  $requestSections = [
    ['key' => 'overview', 'title' => 'Request & Ownership', 'fields' => $requestOverview],
    [
      'key' => $documentDomain === 'hardcopy' ? 'storage' : 'revision',
      'title' => $documentDomain === 'hardcopy' ? 'Proposed Storage & Custody' : 'Proposed Classification & Revision',
      'fields' => $detail,
      'empty' => 'No additional proposed metadata recorded.'
    ],
    [
      'key' => 'approvals',
      'title' => 'Approval Process Timeline',
      'fields' => [
        'Workflow Version' => $row['workflow_version_id'] ? 'Assigned at submission' : 'Awaiting submission',
        'Steps' => implode("\n", $routeText) ?: 'No steps assigned yet'
      ],
      'steps' => $routeSteps
    ],
    [
      'key' => 'audit',
      'title' => 'Workflow Activity Log',
      'events' => $decisionEvents,
      'fields' => [
        'History' => implode("\n", $historyText) ?: 'No approval decisions yet'
      ]
    ]
  ];
  if ($row['type'] === 'disposal')
    $requestSections[1] = [
      'key' => 'disposal',
      'title' => 'Disposal Details',
      'fields' => $detail
    ];
  if ($documentDomain === 'softcopy') {
    array_splice($requestSections, 2, 0, [
      [
        'key' => 'files',
        'title' => 'Document File',
        'fields' => [
          'Attachment' => !empty($payload['controlled_file_id']) || !empty($payload['revision_file_id'])
            ? 'Attached to this request for review' : 'No attachment'
        ]
      ]
    ]);
  }
  $display = [
    'kind' => $isDocumentRequest ? 'document' : 'record',
    'domain' => $documentDomain,
    'header' => [
      'title' => $headerTitle,
      'status' => $row['status'],
      'record_version' => $row['version'],
      'context_label' => 'Requested By',
      'context_value' => $row['requester_name'],
      'updated_at' => $row['updated_at']
    ],
    'sections' => $requestSections
  ];
  $record = [
    'id' => $row['id'],
    'version' => $row['version'],
    'type' => $row['type'],
    'subject' => $payload['subject'] ?? '',
    'remarks' => $payload['remarks'] ?? '',
    'title' => $payload['title'] ?? '',
    'series_number' => $payload['series_number'] ?? '',
    'document_number' => $payload['document_number'] ?? '',
    'category_id' => $payload['category_id'] ?? '',
    'softcopy_id' => $row['softcopy_id'] ?? '',
    'hardcopy_id' => $row['hardcopy_id'] ?? '',
    'recipient_id' => $payload['recipient_id'] ?? '',
    'expires_at' => $payload['expires_at'] ?? '',
    'document_domain' => $payload['document_domain'] ?? ($row['hardcopy_id'] ? 'hardcopy' : 'softcopy'),
    'disposal_reason' => $payload['disposal_reason'] ?? '',
    'disposal_other' => $payload['disposal_other'] ?? '',
    'new_revision_level' => $payload['new_revision_level'] ?? '',
    'effective_date' => $payload['effective_date'] ?? '',
    'date_received' => $payload['date_received'] ?? '',
    'date_released' => $payload['date_released'] ?? '',
    'page_number' => $payload['page_number'] ?? 1,
    'area_id' => $payload['area_id'] ?? '',
    'specific_id' => $payload['specific_id'] ?? '',
    'asset_id' => $payload['asset_id'] ?? '',
    'location_id' => $payload['location_id'] ?? '',
    'sequence_number' => $payload['sequence_number'] ?? '',
    'holder_id' => $payload['holder_id'] ?? '',
    'holder_name' => $user['name'] ?? '',
    'retention_enabled' => $payload['retention_enabled'] ?? 0,
    'retention_start_date' => $payload['retention_start_date'] ?? '',
    'retention_end_date' => $payload['retention_end_date'] ?? '',
    'creation_reason' => $payload['creation_reason'] ?? '',
    'destination_location_id' => $payload['destination_location_id'] ?? '',
    'destination_area_id' => $payload['destination_area_id'] ?? '',
    'destination_specific_id' => $payload['destination_specific_id'] ?? '',
    'destination_asset_id' => $payload['destination_asset_id'] ?? ''
  ];
  $buttons = [['type' => 'view']];
  if (
    in_array($row['type'], ['softcopy_create', 'softcopy_revise'], TRUE) &&
    !empty($payload[$row['type'] === 'softcopy_create' ? 'controlled_file_id' : 'revision_file_id']) &&
    in_array($row['status'], ['draft', 'returned', 'submitted'], TRUE)
  ) {
    $buttons[] = ['type' => 'review', 'url' => 'files/review/' . $row['id']];
  }
  $canEdit = isset($permissions['*']) || !empty($permissions['requests']['edit']);
  if (!$task && in_array($row['status'], ['draft', 'returned'], TRUE) && in_array($row['type'], $allowed_request_types, TRUE)) {
    if ($canEdit)
      $buttons[] = ['type' => 'edit'];
    if ($canRequest('submit'))
      $buttons[] = [
        'type' => 'action',
        'url' => "my-requests/{$tab}/submit",
        'label' => 'Submit',
        'description' => 'Submit this request to the published approval workflow.',
        'icon' => 'fa-solid fa-paper-plane'
      ];
  }
  if (!$task && $canRequest('cancel') && $row['status'] === 'draft')
    $buttons[] = [
      'type' => 'action',
      'url' => "my-requests/{$tab}/cancel",
      'label' => 'Cancel',
      'description' => 'Cancel this draft request.',
      'icon' => 'fa-solid fa-xmark'
    ];
  // Rows are already scoped to this user's exact active approval assignment.
  if ($task && $row['status'] === 'submitted')
    foreach (['approved' => 'Approve', 'rejected' => 'Reject', 'returned' => 'Return'] as $decision => $label) {
      $buttons[] = [
        'type' => 'action',
        'url' => "my-tasks/{$tab}/decide",
        'decision' => $decision,
        'label' => $label,
        'description' => "Record {$record['title']} for this workflow step.",
        'icon' => $decision === 'approved'
          ? 'fa-solid fa-check'
          : ($decision === 'rejected'
            ? 'fa-solid fa-circle-xmark'
            : 'fa-solid fa-arrow-rotate-left'),
        'show_text' => TRUE
      ];
    }


  $dt_rows[] = [
    'id' => $row['id'],
    'cells' => [
      'reference' => $row['reference'],
      'title' => $headerTitle,
      'type' => ucwords(str_replace('_', ' ', $row['type'])),
      'subject' => $payload['subject'] ?? '',
      'requester' => $row['requester_name'],
      'status' => $row['status'],
      'updated' => $row['updated_at']
    ],
    'display' => $display,
    'record' => $record,
    'buttons' => $buttons
  ];
}
$dt_q = $table['q'];
$dt_filter = $table['status'];
$dt_page = $table['page'];
$dt_limit = $table['limit'];
$dt_total = $total;
$dt_sort = $table['sort'];
$dt_dir = strtolower($table['dir']);
$dt_sortable = ['reference', 'type', 'requester', 'status', 'updated'];
$dt_date_filters = TRUE;
$dt_filter_values = [
  'status' => $dt_filter,
  'type' => $table['type'],
  'period' => $table['period'] ?? 'all',
  'from' => $table['from'],
  'to' => $table['to']
];
$dt_filters = [
  'status' => [
    '' => 'All Statuses',
    'draft' => 'Draft',
    'submitted' => 'Submitted',
    'completed' => 'Completed',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'returned' => 'Returned',
    'cancelled' => 'Cancelled'
  ]
];
$dt_filters['type'] = ['' => 'All Actions'] + $typeMap[$tab];
$dt_badges = ['status'];
$dt_create = !$task && $canRequest('add') && $allowed_request_types ? 'New Request' : '';
$dt_path = ($task ? 'my-tasks/' : 'my-requests/') . $tab;
?>

<nav class="tab-bar" aria-label="Request category tabs">
  <?php foreach ($tabs as $slug => $name): ?>
    <a class="tab-link <?= $tab === $slug ? 'active' : '' ?>"
      href="<?= site_url(($task ? 'my-tasks/' : 'my-requests/') . $slug) ?>" <?= $tab === $slug ? 'aria-current="page"' : '' ?>><?= html_escape($name) ?><?php $tabCount = (int) ($navigation_counts[$task ? 'tasks' : 'requests'][$slug] ?? 0); ?>
      <span class="tab-count<?= $tabCount === 0 ? ' is-zero' : '' ?>"
        aria-label="<?= $tabCount ?> <?= $task ? 'pending tasks' : 'draft or returned requests' ?>"><?= $tabCount ?></span></a>
  <?php endforeach; ?>
</nav>
<?php $this->load->view('reusable_components/reusable_datatable', compact(
  'dt_path',
  'dt_q',
  'dt_filter',
  'dt_page',
  'dt_limit',
  'dt_total',
  'dt_sort',
  'dt_dir',
  'dt_sortable',
  'dt_filters',
  'dt_filter_values',
  'dt_date_filters',
  'dt_badges',
  'dt_create',
  'dt_columns',
  'dt_rows'
)); ?>
<?php if (!$task): ?>
  <div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="editModal" tabindex="-1"
    aria-labelledby="editTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <form id="editForm" method="post" enctype="multipart/form-data"
          action="<?= site_url("my-requests/{$tab}/save") ?>"
          data-allowed-request-types="<?= html_escape(json_encode($allowed_request_types)) ?>" <?= $tab === 'hardcopy' ? ' data-hardcopy-upsert="yes" ' : ($tab === 'hardcopy-transfer' ? ' data-hardcopy-transfer="yes" ' : '') ?>
          data-confirm="Save request draft?" autocomplete="off">
          <div class="modal-header">
            <h2 class="modal-title fs-6" id="editTitle">New Request</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <input type="hidden" name="id" value=""><input type="hidden" name="version" value="">
              <?php if ($tab === 'softcopy'): ?>
                <?php $this->load->view('pages/request/softcopy_fields', [
                  'softcopy_options' => $softcopy_options,
                  'category_options' => $category_options,
                  'softcopyDirect' => FALSE
                ]); ?>
              <?php elseif ($tab === 'hardcopy'): ?>
                <div class="col-md-6">
                  <label class="form-label" for="reqType">Request Action</label>
                  <select class="form-select" name="type" id="reqType" required autocomplete="off">
                    <option value="hardcopy_create">Create Hardcopy</option>
                    <option value="hardcopy_update">Update Hardcopy</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="reqSubject">Subject / Reason</label>
                  <input class="form-control" id="reqSubject" name="subject" maxlength="255" required autocomplete="off">
                </div>
                <div class="col-12" data-hardcopy-existing>
                  <label class="form-label" for="reqHardcopy">Existing Hardcopy Document</label>
                  <select class="form-select" name="hardcopy_id" id="reqHardcopy" data-searchable autocomplete="off">
                    <option value="">Choose a document</option>
                    <?php foreach ($hardcopy_options as $item): ?>
                      <option value="<?= (int) $item['id'] ?>"
                        data-hardcopy="<?= html_escape(json_encode($item, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR)) ?>">
                        <?= html_escape($item['title']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12" data-hardcopy-details>
                  <div class="row g-3">
                    <?php $this->load->view('pages/hardcopy_document/modal_action/upsert', [
                      'options' => $hardcopy_form_options,
                      'user' => $user,
                      'is_administrator' => $is_administrator
                    ]); ?>
                  </div>
                </div>
              <?php elseif ($tab === 'hardcopy-transfer'): ?>
                <?php $this->load->view('pages/request/hardcopy_transfer_fields', [
                  'transfer_sources' => $transfer_sources,
                  'transfer_options' => $transfer_options
                ]); ?>
              <?php elseif ($tab === 'hardcopy-disposal'): ?>
                <div class="col-md-6">
                  <label class="form-label" for="reqType">Request Action</label>
                  <select class="form-select" name="type" id="reqType" required autocomplete="off">
                    <option value="disposal">Dispose Hardcopy</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="reqSubject">Subject / Reason</label>
                  <input class="form-control" id="reqSubject" name="subject" maxlength="255" required autocomplete="off">
                </div>
                <div class="col-12">
                  <label class="form-label" for="reqHardcopy">Hardcopy Document</label>
                  <select class="form-select" name="hardcopy_id" id="reqHardcopy" data-searchable required
                    autocomplete="off">
                    <option value="">Choose an active document</option>
                    <?php foreach ($hardcopy_options as $item): ?>
                      <option value="<?= (int) $item['id'] ?>">
                        <?= html_escape($item['title']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <?php $this->load->view('pages/request/disposal_fields', ['disposal_field_prefix' => 'requestDisposal']); ?>
                <div class="col-12">
                  <label class="form-label" for="reqRemark">Remarks <span class="optional-label">(optional)</span></label>
                  <textarea class="form-control" id="reqRemark" name="remarks" rows="3" autocomplete="off"></textarea>
                  <p class="form-text mb-0">The document is disposed only after the approval workflow is completed.</p>
                </div>
              <?php else: ?>

                <div class="col-md-6"><label class="form-label" for="reqType">Request Action</label>
                  <select class="form-select" name="type" id="reqType" required autocomplete="off">
                    <?php foreach ($typeMap[$tab] as $key => $name): ?>
                      <option value="<?= html_escape($key) ?>"><?= html_escape($name) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6"><label class="form-label" for="reqSubject">Subject</label>
                  <input class="form-control" name="subject" id="reqSubject" maxlength="255" required autocomplete="off">
                </div>
                <?php if (in_array($tab, ['access-grant', 'document-assign'], TRUE)): ?>
                  <div class="col-md-6" data-domain-picker>
                    <label class="form-label" for="requestDocumentDomain">Document Type</label>
                    <select class="form-select" id="requestDocumentDomain" name="document_domain" required autocomplete="off">
                      <option value="softcopy">Softcopy</option>
                      <option value="hardcopy">Hardcopy</option>
                    </select>
                  </div>
                <?php endif; ?>
                <div class="col-md-6" data-document-domain="softcopy"><label class="form-label" for="reqSoftcopy">Softcopy
                    Document</label>
                  <select class="form-select" name="softcopy_id" id="reqSoftcopy" data-searchable autocomplete="off">
                    <option value="">Not selected</option>
                    <?php foreach ($softcopy_options as $item): ?>
                      <option value="<?= (int) $item['id'] ?>">
                        <?= html_escape($item['document_number'] . ' · ' . $item['title']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6" data-document-domain="hardcopy"><label class="form-label" for="reqHardcopy">Hardcopy
                    Document</label>
                  <select class="form-select" name="hardcopy_id" id="reqHardcopy" data-searchable autocomplete="off">
                    <option value="">Not selected</option>
                    <?php foreach ($hardcopy_options as $item): ?>
                      <option value="<?= (int) $item['id'] ?>"><?= html_escape($item['title']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6"><label class="form-label" for="reqTitle">Proposed Document Title</label>
                  <input class="form-control" name="title" id="reqTitle" maxlength="255" autocomplete="off">
                </div>
                <div class="col-md-6"><label class="form-label" for="reqNumber">Document Number</label>
                  <input class="form-control" name="document_number" id="reqNumber" maxlength="100" autocomplete="off">
                </div>
                <div class="col-md-6"><label class="form-label" for="reqCategory">Category</label>
                  <select class="form-select" name="category_id" id="reqCategory" data-searchable autocomplete="off">
                    <option value="">No category</option><?php foreach ($category_options as $opt): ?>
                      <option value="<?= (int) $opt['id'] ?>"><?= html_escape($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <?php if (in_array($tab, ['access-grant', 'document-assign'], TRUE)): ?>
                  <div class="col-md-6"><label class="form-label" for="reqUser">Recipient / Assigned User</label>
                    <input class="form-control" id="reqUser" type="text" value="<?= html_escape($user['name']) ?>" readonly
                      autocomplete="off">
                    <input type="hidden" name="recipient_id" value="<?= (int) $user['id'] ?>">
                  </div>
                <?php else: ?>
                  <div class="col-md-6"><label class="form-label" for="reqUser">Recipient / Assigned User</label>
                    <select class="form-select" name="recipient_id" id="reqUser" data-searchable autocomplete="off">
                      <option value="">Not selected</option><?php foreach ($users as $opt): ?>
                        <option value="<?= (int) $opt['id'] ?>"><?= html_escape($opt['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                <?php endif; ?>
                <div class="col-md-6"><label class="form-label" for="reqExpires">Access Expires At</label>
                  <input class="form-control" name="expires_at" type="date" id="reqExpires" autocomplete="off">
                </div>
                <div class="col-md-6"><label class="form-label" for="reqDest">Destination Location</label>
                  <select class="form-select" name="destination_location_id" id="reqDest" data-searchable
                    autocomplete="off">
                    <option value="">Not selected</option><?php foreach ($location_options as $opt): ?>
                      <option value="<?= (int) $opt['id'] ?>"><?= html_escape($opt['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-12"><label class="form-label" for="reqRemark">Remarks <span
                      class="optional-label">(optional)</span></label>
                  <textarea class="form-control" id="reqRemark" name="remarks" rows="3" autocomplete="off"></textarea>
                </div>

              <?php endif; ?>
            </div>
          </div>
          <input type="hidden" name="confirmed" value="no">
          <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
            value="<?= $this->security->get_csrf_hash() ?>">
          <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" type="submit">Save Draft</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($task && $tab === 'hardcopy-transfer'): ?>
  <section class="workspace-card mt-4">
    <div class="workspace-card-header">
      <strong>Physical Transfer Handoffs</strong>
      <p class="small text-secondary mb-0">Only the transfer requester can dispatch; only the destination recipient can
        accept.
      </p>
    </div>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th scope="col">Hardcopy</th>
            <th scope="col">Requester / Dispatcher</th>
            <th scope="col">Current Holder</th>
            <th scope="col">Recipient</th>
            <th scope="col">Stage</th>
            <th scope="col" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$handoffs): ?>
            <tr>
              <td colspan="6" class="empty-state">No physical handoffs assigned to you.</td>
            </tr><?php endif; ?>
          <?php foreach ($handoffs as $handoff): ?>
            <tr>
              <td><?= html_escape($handoff['document_title']) ?></td>
              <td><?= html_escape($handoff['requester_name']) ?></td>
              <td><?= html_escape($handoff['holder_name']) ?></td>
              <td><?= html_escape($handoff['recipient_name']) ?></td>
              <td><span
                  class="badge-status status-pending"><?= html_escape(ucwords(str_replace('_', ' ', $handoff['status']))) ?></span>
              </td>
              <td class="text-end">
                <?php $isRequester = (int) $handoff['requester_id'] === (int) $user['id'];
                $isRecipient = (int) $handoff['recipient_id'] === (int) $user['id']; ?>
                <?php if ($isRequester && $handoff['status'] === 'for_transfer'): ?>
                  <button class="btn btn-outline-primary btn-sm js-action" type="button"
                    data-url="<?= site_url('my-tasks/hardcopy-transfer/dispatch') ?>" data-id="<?= (int) $handoff['id'] ?>"
                    data-title="Dispatch Hardcopy"
                    data-description="Confirm physical release of this document to the intended recipient.">Dispatch</button>
                <?php elseif ($isRecipient && $handoff['status'] === 'in_transit'): ?>
                  <button class="btn btn-primary btn-sm js-action" type="button"
                    data-url="<?= site_url('my-tasks/hardcopy-transfer/accept') ?>" data-id="<?= (int) $handoff['id'] ?>"
                    data-title="Accept Hardcopy"
                    data-description="Confirm physical receipt of the document and update its custodian and location.">Accept</button>
                <?php else: ?><span class="text-secondary small">Awaiting next party</span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php $handoffPages = max(1, (int) ceil($handoff_total / 10)); ?>
    <div class="table-footer"><small>Assigned handoffs: <?= (int) $handoff_total ?></small>
      <div class="d-flex align-items-center gap-2">
        <a class="btn btn-light btn-sm <?= $handoff_page <= 1 ? 'disabled' : '' ?>"
          href="<?= site_url('my-tasks/hardcopy-transfer') . '?handoff_page=' . max(1, $handoff_page - 1) ?>">Previous</a>
        <span class="small"><?= (int) $handoff_page ?> / <?= $handoffPages ?></span>
        <a class="btn btn-light btn-sm <?= $handoff_page >= $handoffPages ? 'disabled' : '' ?>"
          href="<?= site_url('my-tasks/hardcopy-transfer') . '?handoff_page=' . min($handoffPages, $handoff_page + 1) ?>">Next</a>
      </div>
    </div>
  </section>
<?php endif; ?>