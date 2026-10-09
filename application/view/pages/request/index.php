<?php
$allowed = function ($action) use ($permissions, $module) {
    return isset($permissions['*']) || !empty($permissions[$module][$action]);
};
$operationNames = [
    'create' => 'Create Document', 'revise' => 'Revise Document',
    'dispose' => 'Dispose Document', 'transfer' => 'Transfer Hardcopy',
    'grant' => 'Grant Document Access', 'assign' => 'Assign Document'
];
$dt_columns = $task
    ? ['subject' => 'Subject', 'requester' => 'Requested By', 'document' => 'Document',
       'step' => 'Current Step', 'status' => 'Status']
    : ['subject' => 'Subject', 'operation' => 'Action', 'document' => 'Document',
       'status' => 'Status', 'created' => 'Created'];
$dt_filters = ['status' => ['' => 'All Statuses', 'draft' => 'Draft', 'pending' => 'Pending',
    'returned' => 'Returned', 'approved' => 'Approved', 'rejected' => 'Rejected']];
$dt_badges = ['status'];
$dt_path = $base_path;
$dt_q = $q;
$dt_filter = $status;
$dt_page = $page;
$dt_limit = $limit;
$dt_total = $total;
$dt_create = !$task && $allowed('create') ? 'New Request' : '';
$dt_rows = [];

foreach ($rows as $row) {
    $doc = $row['document_code'] ? $row['document_code'] . ' · ' . $row['document_title']
        : 'New document (not yet created)';
    $operation = $row['operation'] ?? '';
    $display = [
        'Subject' => $row['subject'],
        'Type' => ucwords(str_replace('-', ' ', $row['request_type'])),
        'Operation' => $operationNames[$operation] ?? 'Pending detail completion',
        'Document' => $doc, 'Proposed Code' => $row['proposed_code'] ?? '',
        'Proposed Title' => $row['proposed_title'] ?? '',
        'Proposed Version' => $row['proposed_version'] ?? '',
        'Destination' => $row['destination_name'] ?? '',
        'Target User' => $row['target_user_name'] ?? '',
        'Access Expires' => $row['access_expires_at'] ?? 'No expiry',
        'Remark' => $row['remark'], 'Status' => $row['status'],
        'Current Step' => $row['step_label'] ?? $row['current_step_label'] ?? 'No active step',
        'Workflow History' => $row['history_text'] ?: 'No recorded decisions',
        'Created' => $row['created_at']
    ];
    $record = [
        'id' => $row['id'], 'subject' => $row['subject'],
        'document_id' => $row['document_id'], 'remark' => $row['remark'],
        'operation' => $operation, 'target_place_id' => $row['target_place_id'] ?? '',
        'target_user_id' => $row['target_user_id'] ?? '',
        'access_expires_at' => substr((string) ($row['access_expires_at'] ?? ''), 0, 10),
        'proposed_code' => $row['proposed_code'] ?? '',
        'proposed_title' => $row['proposed_title'] ?? '',
        'proposed_version' => $row['proposed_version'] ?? '',
        'proposed_description' => $row['proposed_description'] ?? ''
    ];
    $cells = [
        'subject' => $row['subject'],
        'operation' => $operationNames[$operation] ?? '',
        'document' => $doc, 'status' => $row['status'], 'created' => $row['created_at'],
        'requester' => $row['requester_name'] ?? '',
        'step' => $row['step_label'] ?? ''
    ];
    $buttons = [['type' => 'view']];
    if ($task) {
        foreach ([
            'approved' => ['approve', 'Approve', 'fa-solid fa-check'],
            'rejected' => ['reject', 'Reject', 'fa-solid fa-xmark'],
            'returned' => ['return', 'Return', 'fa-solid fa-rotate-left']
        ] as $decision => $action) {
            if ($allowed($action[0])) $buttons[] = [
                'type' => 'action', 'url' => $base_path . '/decide',
                'decision' => $decision, 'label' => $action[1], 'icon' => $action[2],
                'description' => 'Record your decision for this request.'
            ];
        }
    } else {
        if (in_array($row['status'], ['draft', 'returned'], TRUE) && $allowed('edit')) {
            $buttons[] = ['type' => 'edit'];
        }
        if (in_array($row['status'], ['draft', 'returned'], TRUE) && $allowed('submit')) {
            $buttons[] = ['type' => 'action', 'url' => $base_path . '/submit',
                'label' => 'Submit', 'icon' => 'fa-solid fa-paper-plane',
                'description' => 'Submit this request to its active approval workflow.'];
        }
        if ($row['status'] === 'draft' && $allowed('delete')) {
            $buttons[] = ['type' => 'action', 'url' => $base_path . '/delete',
                'label' => 'Delete Draft', 'icon' => 'fa-solid fa-trash-can',
                'description' => 'Delete this unsent draft.'];
        }
    }
    $dt_rows[] = [
        'id' => $row['id'], 'cells' => $cells, 'display' => $display,
        'record' => $record, 'buttons' => $buttons
    ];
}
?>
<div class="page-heading">
    <div><span class="eyebrow">Request Center</span>
        <h1><?= html_escape($task ? 'My Tasks' : 'My Requests') ?></h1>
        <p><?= $task ? 'Review requests routed to you for approval.' :
            'Create drafts, submit requests, and track approval decisions.' ?></p>
    </div>
</div>
<nav class="tab-bar" aria-label="Request types">
    <?php foreach ($tabs as $slug => $label): ?>
        <a class="tab-link <?= $current === $slug ? 'active' : '' ?>"
           href="<?= site_url(($task ? 'my-tasks/' : 'my-requests/') . $slug) ?>">
            <?= html_escape($label) ?></a>
    <?php endforeach; ?>
</nav>
<?php $this->load->view('components/datatable', compact(
    'dt_total', 'dt_limit', 'dt_page', 'dt_path', 'dt_q', 'dt_filter',
    'dt_columns', 'dt_badges', 'dt_filters', 'dt_rows', 'dt_create'
)); ?>

<?php if (!$task): ?>
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <form id="editForm" method="post" action="<?= site_url($form_action) ?>"
              data-request-type="<?= html_escape($current) ?>"
              data-confirm="Save this request draft?">
            <div class="modal-header"><h2 class="modal-title fs-6" id="editTitle">New Request</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id" value="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Request Type</label>
                        <input class="form-control" value="<?= html_escape($tabs[$current]) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="requestSubject">Subject</label>
                        <input class="form-control" id="requestSubject" name="subject" required maxlength="255">
                    </div>
                    <?php if (in_array($current, ['softcopy', 'hardcopy'], TRUE)): ?>
                        <div class="col-md-6">
                            <label class="form-label" for="requestOperation">Document Action</label>
                            <select class="form-select" id="requestOperation" name="operation">
                                <option value="create">Create New Document</option>
                                <option value="revise">Revise Existing Document</option>
                                <option value="dispose">Dispose Existing Document</option>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" id="requestOperation" name="operation"
                            value="<?= html_escape([
                                'hardcopy-transfer' => 'transfer',
                                'access-grant' => 'grant',
                                'document-assign' => 'assign'
                            ][$current]) ?>">
                    <?php endif; ?>
                    <div class="col-md-6" data-request-section="document">
                        <label class="form-label" for="requestDoc">Related Document</label>
                        <select class="form-select" id="requestDoc" name="document_id">
                            <option value="">Select document</option>
                            <?php foreach ($document_options as $doc): ?>
                                <option value="<?= (int) $doc['id'] ?>">
                                    <?= html_escape($doc['code'] . ' · ' . $doc['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12" data-request-section="proposal">
                        <div class="border rounded-3 p-3">
                            <strong class="d-block mb-3">Proposed Document Details</strong>
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label" for="proposedCode">Document Code</label>
                                    <input class="form-control" id="proposedCode" name="proposed_code" maxlength="80">
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label" for="proposedTitle">Document Title</label>
                                    <input class="form-control" id="proposedTitle" name="proposed_title" maxlength="255">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="proposedVersion">Proposed Version</label>
                                    <input class="form-control" id="proposedVersion" name="proposed_version" maxlength="30" value="1">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="proposedDescription">
                                        Description <span class="optional-label">(optional)</span></label>
                                    <textarea class="form-control" id="proposedDescription" name="proposed_description" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6" data-request-section="location">
                        <label class="form-label" for="targetLocation">Destination Location</label>
                        <select class="form-select" id="targetLocation" name="target_place_id">
                            <option value="">Select destination</option>
                            <?php foreach ($location_options as $loc): ?>
                                <option value="<?= (int) $loc['id'] ?>"><?= html_escape($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6" data-request-section="user">
                        <label class="form-label" for="targetUser">Target User</label>
                        <select class="form-select" id="targetUser" name="target_user_id">
                            <option value="">Select user</option>
                            <?php foreach ($user_options as $option): ?>
                                <option value="<?= (int) $option['id'] ?>"><?= html_escape($option['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6" data-request-section="expiry">
                        <label class="form-label" for="accessExpiresAt">
                            Access Expiration <span class="optional-label">(optional)</span></label>
                        <input class="form-control" type="date" id="accessExpiresAt" name="access_expires_at">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="requestRemark">
                            Remark <span class="optional-label">(optional)</span></label>
                        <textarea class="form-control" id="requestRemark" rows="3" name="remark"></textarea>
                    </div>
                </div>
            </div>
            <input type="hidden" name="confirmed" value="no">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
                   value="<?= $this->security->get_csrf_hash() ?>">
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Draft</button></div>
        </form>
    </div></div>
</div>
<?php endif; ?>
