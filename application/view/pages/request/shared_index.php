<?php
require_once APPPATH . 'services/presentation/request_title.php';
require_once APPPATH . 'services/documents/scanned_file_presentation.php';
require_once APPPATH . 'services/request/request_policy.php';
$actionLabels = [
  'softcopy_create' => 'Create Softcopy Document',
  'softcopy_revise' => 'Revise Softcopy Document',
  'softcopy_cancel' => 'Cancel Softcopy Document',
  'external_create' => 'Create External Document',
  'external_revise' => 'Revise External Document',
  'scanned_create' => 'Upload Scanned Document',
  'scanned_update' => 'Update Scanned Document',
  'scanned_delete' => 'Delete Scanned Document',
  'scanned_category_create' => 'Create Scanned Category',
  'hardcopy_create' => 'Create Hardcopy Document',
  'hardcopy_update' => 'Update Hardcopy Document',
  'transfer' => 'Transfer Hardcopy Document',
  'disposal' => 'Dispose Document',
  'access' => 'Request Document Access',
  'assignment' => 'Request Document Assignment'
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

$dt_rows = [];
foreach ($rows as $row) {
  if (!empty($row['handoff'])) {
    $dispatch = $row['status'] === 'for_transfer';
    $dt_rows[] = [
      'id' => $row['id'],
      'cells' => [
        'reference' => $row['reference'],
        'title' => $row['document_title'],
        'type' => 'Physical Transfer Handoff',
        'subject' => $row['reason'],
        'requester' => $row['requester_name'],
        'status' => $row['status'],
        'updated' => $row['updated_at']
      ],
      'display' => [
        'kind' => 'record',
        'header' => ['title' => $row['document_title'], 'status' => $row['status']],
        'sections' => [[
          'key' => 'overview',
          'title' => 'Physical Transfer Handoff',
          'fields' => [
            'Request Reference' => $row['reference'],
            'Requester / Dispatcher' => $row['requester_name'],
            'Current Holder' => $row['holder_name'],
            'Recipient' => $row['recipient_name'],
            'Reason' => $row['reason']
          ]
        ]]
      ],
      'buttons' => [
        ['type' => 'view'],
        [
          'type' => 'action',
          'url' => 'my-tasks/hardcopy-transfer/' . ($dispatch ? 'dispatch' : 'accept'),
          'label' => $dispatch ? 'Dispatch' : 'Accept',
          'description' => $dispatch
            ? 'Confirm physical release of this document to the intended recipient.'
            : 'Confirm physical receipt of the document and update its custodian and location.',
          'icon' => $dispatch ? 'fa-solid fa-truck' : 'fa-solid fa-check'
        ]
      ]
    ];
    continue;
  }
  $tab = $type_tabs[$row['type']];
  $decodedPayload = json_decode((string) ($row['payload'] ?? ''), TRUE);
  $payload = is_array($decodedPayload) ? $decodedPayload : [];
  $entries = $history[$row['id']] ?? [];
  $stepLabels = [];
  foreach ($approval_routes[$row['id']] ?? [] as $step)
    if (!empty($step['step_id']))
      $stepLabels[(int) $step['step_id']] = $step['label'];
  // The workflow snapshot names the step whose approval locks the attached file.
  $snapshot = json_decode((string) ($row['snapshot'] ?? ''), TRUE);
  $lockNodeKey = is_array($snapshot) && is_string($snapshot['document_lock_step_key'] ?? NULL)
    && in_array($row['type'], ['softcopy_create', 'softcopy_revise', 'external_create', 'external_revise'], TRUE)
    ? $snapshot['document_lock_step_key'] : NULL;
  $lockStepIds = [];
  foreach ($approval_routes[$row['id']] ?? [] as $step)
    if ($lockNodeKey !== NULL && ($step['node_key'] ?? NULL) === $lockNodeKey)
      $lockStepIds[(int) $step['step_id']] = TRUE;
  // Older requests may lack the key in their snapshot: the locking step is then the
  // approved step whose approval time equals the file's lock time.
  if (!$lockStepIds && !empty($row['locked_attachment']) && !empty($row['attachment_locked_at']))
    foreach ($approval_routes[$row['id']] ?? [] as $step)
      if ($step['status'] === 'approved' && ($step['acted_at'] ?? NULL) === $row['attachment_locked_at'])
        $lockStepIds[(int) $step['step_id']] = TRUE;
  // An approval at the lock step is the event that locked the file.
  $lockedByEvent = static fn($event) => !empty($row['locked_attachment']) &&
    ($event['action'] ?? '') === 'approved' && !empty($lockStepIds[(int) ($event['step_id'] ?? 0)]);
  $decisionEvents = [];
  foreach ($entries as $event) {
    $decisionEvents[] = [
      'step_label' => $stepLabels[(int) ($event['step_id'] ?? 0)] ??
        ucwords(str_replace('_', ' ', $event['action'])),
      'action' => $event['action'],
      'user_name' => $event['user_name'],
      'position_title' => $event['position_title'] ?? '',
      'comments' => $event['comments'] ?? '',
      'created_at' => $event['created_at'],
      'locked' => $lockedByEvent($event)
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
        'created_at' => $event['created_at'],
        'locked' => $lockedByEvent($event)
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
    if (!empty($lockStepIds[(int) ($step['step_id'] ?? 0)]))
      // 'locked' once this step's approval has locked the file; otherwise it is the step that will.
      $tile['lock'] = $step['status'] === 'approved' && !empty($row['locked_attachment']) ? 'locked' : 'locks';
    if (!empty($historyByStep[(int) ($step['step_id'] ?? 0)]))
      $tile['history'] = $historyByStep[(int) $step['step_id']];
    $routeSteps[] = $tile;
    $routeText[] = $step['label'] . ' → ' . $step['approver_label'] .
      ' [' . ucwords(str_replace('_', ' ', $step['status'])) . ']';
  }
  $requestStatus = (string) $row['status'];
  if (!$task && $requestStatus === 'submitted') {
    $requestStatus = 'In Progress';
    foreach ($approval_routes[$row['id']] ?? [] as $step) {
      if ($step['status'] === 'active') {
        $requestStatus = 'Awaiting ' . ($step['approver_label'] ?: $step['label']);
        break;
      }
    }
  }
  // Approver task list: show the approver's own pending step, not the generic request status.
  if ($task && $requestStatus === 'submitted' && !empty($row['step_label']))
    $requestStatus = 'Pending your approval: ' . $row['step_label'];
  // The request viewer uses the same document renderer as System Documents,
  // but presents only submitted proposal metadata and visible approval history.
  // Never pretend an unapproved proposal is already the stored document.
  $isDocumentRequest = in_array($tab, ['softcopy', 'external', 'scanned', 'hardcopy', 'hardcopy-disposal'], TRUE);
  $documentDomain = in_array($tab, ['softcopy', 'scanned'], TRUE) ? $tab : ($tab === 'external' ? 'external' :
    ($tab === 'hardcopy-disposal' && ($payload['document_domain'] ?? '') === 'external'
      ? 'external' : (in_array($tab, ['hardcopy', 'hardcopy-disposal'], TRUE) ? 'hardcopy' : 'record')));
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
  if ($tab === 'scanned') {
    unset($requestOverview['Remarks'], $requestOverview['Subject / Reason']);
    $requestOverview['Subject'] = $payload['subject'] ?? '';
  }
  $requestSections = [
    ['key' => 'overview', 'title' => 'Request & Ownership', 'fields' => $requestOverview],
    [
      'key' => $documentDomain === 'hardcopy' ? 'storage' : 'revision',
      'title' => $documentDomain === 'hardcopy' ? 'Proposed Storage & Custody' :
        ($documentDomain === 'external' ? 'Proposed External Document' : 'Proposed Classification & Revision'),
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
  if (in_array($documentDomain, ['softcopy', 'external'], TRUE)) {
    $filesSection = [
      'key' => 'files',
      'title' => 'Document File',
      'fields' => [
        'Attachment' => !empty($payload['controlled_file_id']) || !empty($payload['revision_file_id'])
          ? (!empty($row['locked_attachment'])
            ? 'Locked by the designated approver; read-only preview only unless you hold Download Locked Files permission'
            : 'Attached to this request for review')
          : 'No attachment'
      ]
    ];
    // Same read-only preview as the document modals. The endpoint re-checks that the
    // viewer is the requester or an active approver, so only offer it to them.
    $canPreviewAttachment = in_array($row['status'], ['draft', 'returned', 'submitted'], TRUE) &&
      ((int) ($row['requested_by'] ?? 0) === (int) ($user['id'] ?? 0) || ($task && $row['status'] === 'submitted'));
    if (!empty($row['attachment_files']) && $canPreviewAttachment) {
      $canBypassLock = isset($permissions['*']) || !empty($permissions['files']['download_locked']);
      $canDownloadFile = isset($permissions['*']) || !empty($permissions['files']['download']);
      $filesSection['fields'] = [];
      $filesSection['links'] = [];
      foreach ($row['attachment_files'] as $index => $attachment) {
        $unlocked = empty($attachment['locked_at']) || $canBypassLock;
        $extension = strtolower(pathinfo((string) $attachment['original_name'], PATHINFO_EXTENSION));
        $previewUrl = site_url('files/review/' . (int) $row['id'] . '?file_id=' . (int) $attachment['id']);
        $filesSection['fields'][($index + 1) . '. ' . $attachment['original_name']] =
          number_format((int) $attachment['size']) . ' bytes' .
          (!empty($attachment['locked_at']) ? ' · Locked' : '');
        $filesSection['links'][] = [
          'label' => $attachment['original_name'],
          'url' => $canDownloadFile && $unlocked ? $previewUrl . '&download=1' : '',
          'pdf_url' => $canDownloadFile && $unlocked && in_array($extension, ['docx', 'xlsx'], TRUE)
            ? $previewUrl . '&download=pdf' : '',
          'preview_url' => $previewUrl,
          'preview_type' => in_array($extension, ['docx', 'xlsx'], TRUE) ? 'application/pdf' : (string) $attachment['mime_type']
        ];
      }
    }
    array_splice($requestSections, 2, 0, [$filesSection]);
  }
  if ($tab === 'scanned' && !empty($payload['scanned_file_ids'])) {
    $scanLinks = []; $scanFields = [];
    foreach ($row['attachment_files'] ?? [] as $index => $attachment) {
      $scanFields[($index + 1) . '. ' . $attachment['original_name']] = number_format((int) $attachment['size']) . ' bytes';
      $scanLinks[] = Scanned_file_presentation::link($attachment, (int) $row['id']);
    }
    $requestSections[] = ['key' => 'files', 'title' => 'Scanned Attachments', 'fields' => $scanFields, 'links' => $scanLinks];
  }
  $display = [
    'review' => $task,
    'kind' => $isDocumentRequest ? 'document' : 'record',
    'domain' => $documentDomain,
    'header' => [
      'title' => $headerTitle,
      'status' => $requestStatus,
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
    'locked_attachment' => !empty($row['locked_attachment']),
    'subject' => $payload['subject'] ?? '',
    'remarks' => $payload['remarks'] ?? '',
    'title' => $payload['title'] ?? '',
    'description' => $payload['description'] ?? '',
    'generated_series_number' => $payload['generated_series_number'] ?? '',
    'series_number_checker' => !empty($payload['series_number_checker']) ? 1 : 0,
    'document_number' => $payload['document_number'] ?? '',
    'category_id' => $payload['category_id'] ?? '',
    'softcopy_id' => $row['softcopy_id'] ?? '',
    'scanned_document_id' => $payload['scanned_document_id'] ?? '',
    'scanned_category_id' => $payload['scanned_category_id'] ?? '',
    'document_version' => $payload['document_version'] ?? '',
    'parent_id' => $payload['parent_id'] ?? '',
    'external_document_id' => $payload['external_document_id'] ?? '',
    'external_document_number' => $payload['external_document_number'] ?? '',
    'external_category_id' => $payload['external_category_id'] ?? '',
    'subsidiary_name' => $payload['subsidiary_name'] ?? ($detail['Proposed Subsidiary'] ?? ''),
    'retention_period' => $payload['retention_period'] ?? '',
    'hardcopy_id' => $row['hardcopy_id'] ?? '',
    'recipient_id' => $payload['recipient_id'] ?? '',
    'expires_at' => $payload['expires_at'] ?? '',
    'document_domain' => $payload['document_domain'] ?? ($tab === 'external'
      ? 'external' : ($row['hardcopy_id'] ? 'hardcopy' : 'softcopy')),
    'disposal_reason' => $payload['disposal_reason'] ?? '',
    'disposal_other' => $payload['disposal_other'] ?? '',
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
  $buttons = [$task
    ? ['type' => 'view', 'label' => 'Review request', 'primary' => TRUE, 'icon' => 'fa-solid fa-file-circle-check']
    : ['type' => 'view']];
  $reviewButtons = [];
  $canEdit = isset($permissions['*']) || !empty($permissions['requests']['edit']);
  if (!$task && in_array($row['status'], ['draft', 'returned'], TRUE) && in_array($row['type'], $allowed_request_types, TRUE)) {
    if ($canEdit)
      $buttons[] = ['type' => 'edit', 'form' => '#request-form-' . ($tab === 'document-assign' ? 'access-grant' : $tab)];
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
  if (!$task && $canRequest('cancel') && Request_policy::deletable($row, (int) $user['id']))
    $buttons[] = [
      'type' => 'action',
      'url' => "my-requests/{$tab}/delete",
      'label' => 'Delete',
      'description' => 'Delete this cancelled draft permanently? This cannot be undone.',
      'icon' => 'fa-solid fa-trash-can'
    ];
  // Rows are already scoped to this user's exact active approval assignment.
  // The DCO's approval at the designated step must carry the edited final file.
  $needsFinalFile = FALSE;
  if ($task && $row['status'] === 'submitted' && in_array($row['type'], ['softcopy_create', 'softcopy_revise'], TRUE)) {
    $routeGraph = json_decode((string) ($row['snapshot'] ?? ''), TRUE) ?: [];
    $needsFinalFile = is_string($routeGraph['finalize_step_key'] ?? NULL) &&
      $routeGraph['finalize_step_key'] === ($row['current_node'] ?? NULL);
  }
  $finalSourceId = 0;
  if ($needsFinalFile)
    $finalSourceId = (int) ($payload['controlled_file_id'] ?? $payload['revision_file_id'] ?? 0);
  $returnDescription = 'Send this request back for review or changes.';
  if ($task && !empty($row['step_id'])) {
    $route = $approval_routes[$row['id']] ?? [];
    $stepKeys = array_column((array) ($snapshot['steps'] ?? []), 'key');
    $stepIndex = array_search($row['current_node'], $stepKeys, TRUE);
    if ($stepIndex === 0) {
      $returnDescription = 'Return this request to the requester for changes.';
    } elseif ($stepIndex !== FALSE) {
      $returnDescription = 'Return this request to the previous reviewer.';
      $routeIndex = array_search((int) $row['step_id'], array_map('intval', array_column($route, 'step_id')), TRUE);
      $previous = $routeIndex ? $route[$routeIndex - 1] : [];
      if (($previous['node_key'] ?? NULL) === $stepKeys[$stepIndex - 1])
        $returnDescription = 'Return this request to ' . ($previous['approver_label'] ?: $previous['label']) . ' for review.';
    }
  }
  if ($task && $row['status'] === 'submitted')
    foreach (['approved' => 'Approve', 'rejected' => 'Reject', 'returned' => 'Return'] as $decision => $label) {
      $reviewButtons[] = [
        'type' => 'action',
        'url' => "my-tasks/{$tab}/decide",
        'decision' => $decision,
        'skip_remarks' => $tab === 'scanned',
        'label' => $label,
        'description' => $decision === 'returned' ? $returnDescription : match ($decision) {
          'approved' => 'Approve this workflow step for ' . $headerTitle . '.',
          'rejected' => 'Reject this request and end its approval workflow.'
        },
        'icon' => $decision === 'approved'
          ? 'fa-solid fa-check'
          : ($decision === 'rejected'
            ? 'fa-solid fa-circle-xmark'
            : 'fa-solid fa-arrow-rotate-left'),
        'show_text' => TRUE,
        'final_upload' => $needsFinalFile && $decision === 'approved',
        'final_source_url' => $needsFinalFile && $decision === 'approved' && ($finalSourceId ?? 0)
          ? site_url('files/review/' . (int) $row['id'] . '?file_id=' . (int) $finalSourceId . '&download=1') : ''
      ];
    }


  $dt_rows[] = [
    'id' => $row['id'],
    'cells' => [
      'reference' => $row['reference'],
      'title' => $headerTitle,
      'type' => $actionLabels[$row['type']],
      'subject' => $payload['subject'] ?? '',
      'requester' => $row['requester_name'],
      'status' => $requestStatus,
      'updated' => $row['updated_at']
    ],
    'display' => $display,
    'record' => $record,
    'buttons' => $buttons,
    'review_buttons' => $reviewButtons
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
  'request_category' => $table['request_category'],
  'type' => $table['type'],
  'period' => $table['period'] ?? 'all',
  'from' => $table['from'],
  'to' => $table['to']
];
$dt_filters = [
  'request_category' => ['' => 'All Categories'] + $categories,
  'type' => ['' => 'All Actions'] + $actionLabels
];
$dt_filters['status'] = $task ? [
  '' => 'All Statuses',
  'submitted' => 'Pending Approval',
  'for_transfer' => 'For Transfer',
  'in_transit' => 'In Transit'
] :
  [
    '' => 'All Statuses',
    'draft' => 'Draft',
    'submitted' => 'In Progress',
    'completed' => 'Completed',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'returned' => 'Returned',
    'cancelled' => 'Cancelled'
  ];
$dt_badges = ['status'];
$dt_create = !$task && $canRequest('add') && $allowed_request_types ? 'New Request' : '';
$dt_path = $path;
$dt_create_form = '';
$dt_filter_labels = ['request_category' => 'Category', 'type' => 'Action'];
?>

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
  'dt_create_form',
  'dt_filter_labels',
  'dt_columns',
  'dt_rows'
)); ?>
<?php if ($show_request_modal) require __DIR__ . '/request_modal.php'; ?>
