<?php
$allowed = function ($action) use ($permissions, $module) {
    return isset($permissions['*']) || !empty($permissions[$module][$action]);
};
$dt_columns = $task
    ? ['subject' => 'Subject', 'requester' => 'Requested By', 'document' => 'Document', 'step' => 'Current Step', 'status' => 'Status']
    : ['subject' => 'Subject', 'document' => 'Document', 'status' => 'Status', 'created' => 'Created'];
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
    $doc = $row['document_code'] ? $row['document_code'] . ' · ' . $row['document_title'] : 'No document selected';
    $display = ['Subject' => $row['subject'], 'Type' => ucwords(str_replace('-', ' ', $row['request_type'])),
        'Document' => $doc, 'Remark' => $row['remark'], 'Status' => $row['status'],
        'Current Step' => $row['step_label'] ?? $row['current_step_label'] ?? 'No active step',
        'Workflow History' => $row['history_text'] ?: 'No recorded decisions',
        'Submitted' => $row['created_at']];
    $record = ['id' => $row['id'], 'subject' => $row['subject'], 'document_id' => $row['document_id'],
        'remark' => $row['remark']];
    $cells = ['subject' => $row['subject'], 'document' => $doc, 'status' => $row['status'],
        'created' => $row['created_at'], 'requester' => $row['requester_name'] ?? '',
        'step' => $row['step_label'] ?? ''];
    $buttons = [['type' => 'view']];
    if ($task) {
        foreach (['approved' => ['approve','Approve','fa-solid fa-check'],
                'rejected' => ['reject','Reject','fa-solid fa-xmark'],
                'returned' => ['return','Return','fa-solid fa-rotate-left']] as $decision => $action) {
            if ($allowed($action[0])) $buttons[] = [
                'type' => 'action', 'url' => $base_path . '/decide',
                'decision' => $decision, 'label' => $action[1], 'icon' => $action[2],
                'description' => 'Record your decision for this request.'
            ];
        }
    } else {
        if (in_array($row['status'], ['draft','returned'], TRUE) && $allowed('edit')) $buttons[] = ['type' => 'edit'];
        if (in_array($row['status'], ['draft','returned'], TRUE) && $allowed('submit')) $buttons[] = [
            'type' => 'action', 'url' => $base_path . '/submit', 'label' => 'Submit', 'icon' => 'fa-solid fa-paper-plane',
            'description' => 'Send this request to its configured workflow.'
        ];
        if ($row['status'] === 'draft' && $allowed('delete')) $buttons[] = [
            'type' => 'action', 'url' => $base_path . '/delete', 'label' => 'Delete Draft', 'icon' => 'fa-solid fa-trash-can',
            'description' => 'Permanently delete this unsent draft.'
        ];
    }
    $dt_rows[] = ['id' => $row['id'], 'cells' => $cells, 'display' => $display, 'record' => $record, 'buttons' => $buttons];
}
?>
<div class="page-heading"><div><span class="eyebrow">Request Center</span><h1><?= html_escape($task ? 'My Tasks' : 'My Requests') ?></h1>
<p><?= $task ? 'Review documents routed to you for a decision.' : 'Create drafts, submit requests, and track approval status.' ?></p></div></div>
<nav class="tab-bar" aria-label="Request types">
    <?php foreach ($tabs as $slug => $label): ?>
        <a class="tab-link <?= $current === $slug ? 'active' : '' ?>" href="<?= site_url(($task ? 'my-tasks/' : 'my-requests/') . $slug) ?>"><?= html_escape($label) ?></a>
    <?php endforeach; ?>
</nav>
<?php $this->load->view('components/datatable'); ?>
<?php if (!$task): ?>
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form action="<?= site_url($form_action) ?>" method="post" id="editForm" data-confirm="Save this request draft?">
            <div class="modal-header"><h2 class="modal-title fs-6" id="editTitle">New Request</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id" value="">
                <div class="mb-3"><label class="form-label">Request Type</label><input class="form-control" value="<?= html_escape($tabs[$current]) ?>" readonly></div>
                <div class="mb-3"><label class="form-label" for="requestSubject">Subject</label><input class="form-control" id="requestSubject" name="subject" required maxlength="255"></div>
                <div class="mb-3"><label class="form-label" for="requestDoc">Related Document <span class="optional-label">(optional)</span></label>
                    <select class="form-select" name="document_id" id="requestDoc"><option value="">Not specified</option>
                        <?php foreach ($document_options as $doc): ?><option value="<?= (int) $doc['id'] ?>"><?= html_escape($doc['code'] . ' · ' . $doc['title']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="form-label" for="requestRemark">Remark <span class="optional-label">(optional)</span></label><textarea class="form-control" id="requestRemark" rows="3" name="remark"></textarea></div>
            </div>
            <input type="hidden" name="confirmed" value="no"><input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Draft</button></div>
        </form>
    </div></div>
</div>
<?php endif; ?>
