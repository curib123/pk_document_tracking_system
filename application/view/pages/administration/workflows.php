<?php
$may = function ($action) use ($permissions) {
    return isset($permissions['*']) || !empty($permissions['workflows'][$action]);
};
$typeNames = ['softcopy'=>'Softcopy Request','hardcopy'=>'Hardcopy Request',
    'hardcopy-transfer'=>'Hardcopy Transfer','access-grant'=>'Document Access Grant','document-assign'=>'Document Assign'];
$dt_columns = ['name' => 'Workflow', 'type' => 'Request Type', 'version' => 'Version', 'steps' => 'Steps', 'default' => 'Default', 'active' => 'Status'];
$dt_badges = ['active'];
$dt_filters = [
    'status' => ['' => 'All Statuses', '1' => 'Active', '0' => 'Inactive'],
    'type' => ['' => 'All Request Types'] + $typeNames
];
$dt_path = 'admin/workflows';
$dt_q = $table['q'];
$dt_filter = $table['status'];
$dt_page = $table['page'];
$dt_limit = $table['limit'];
$dt_sort = $table['sort'];
$dt_dir = $table['dir'];
$dt_filter_values = $table['filters'];
$dt_sortable = array_keys($dt_columns);
$dt_total = $total;
$dt_rows = [];
foreach ($rows as $r) {
    $buttons = [['type' => 'view']];
    if ($may('edit')) $buttons[] = ['type' => 'edit'];
    $dt_rows[] = ['id' => $r['id'],
        'cells' => ['name' => $r['name'], 'type' => $typeNames[$r['request_type']],
            'version' => 'v' . $r['version'], 'steps' => count($steps[$r['id']] ?? []),
            'default' => $r['is_default'] ? 'Yes' : 'No',
            'active' => $r['active'] ? 'active' : '0'],
        'display' => ['Workflow' => $r['name'], 'Type' => $typeNames[$r['request_type']],
            'Version' => 'v' . $r['version'], 'Step Names' => implode(' → ', array_column($steps[$r['id']] ?? [], 'label')),
            'Default' => $r['is_default'] ? 'Yes' : 'No', 'Status' => $r['active'] ? 'Active' : 'Inactive'],
        'record' => ['id' => $r['id'], 'name' => $r['name'], 'request_type' => $r['request_type'],
            'active' => $r['active'], 'is_default' => $r['is_default']],
        'buttons' => $buttons];
}
$dt_create = $may('create') ? 'Create Workflow Version' : '';
?>
<div class="page-heading"><div><span class="eyebrow">Administration</span><h1>Workflow Builder</h1>
<p>Build versioned approval sequences. In-use workflow versions cannot have their steps changed.</p></div></div>
<?php $this->load->view('components/datatable', compact(
    'dt_total', 'dt_limit', 'dt_page', 'dt_path', 'dt_q', 'dt_filter',
    'dt_columns', 'dt_badges', 'dt_filters', 'dt_rows', 'dt_create',
    'dt_sort', 'dt_dir', 'dt_filter_values', 'dt_sortable'
)); ?>
<div class="workspace-card mt-4">
    <div class="workspace-card-header"><strong>Approval Steps</strong><p class="text-secondary small mb-0">Configure approvers in order, using a user, role, requester leader or requester.</p></div>
    <div class="accordion accordion-flush" id="workflowAccordion">
        <?php foreach ($rows as $r): ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading-<?= (int) $r['id'] ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#workflow-<?= (int) $r['id'] ?>"
                        aria-expanded="false" aria-controls="workflow-<?= (int) $r['id'] ?>">
                        <?= html_escape($r['name']) ?> · <?= html_escape($typeNames[$r['request_type']]) ?> · v<?= (int) $r['version'] ?>
                        <span class="badge text-bg-light ms-2"><?= count($steps[$r['id']] ?? []) ?> steps</span>
                    </button>
                </h2>
                <div class="accordion-collapse collapse" id="workflow-<?= (int) $r['id'] ?>" data-bs-parent="#workflowAccordion">
                    <div class="accordion-body">
                        <?php foreach ($steps[$r['id']] ?? [] as $step): ?>
                            <div class="workflow-step d-flex align-items-center justify-content-between gap-2">
                                <div><strong><?= (int) $step['step_order'] ?>. <?= html_escape($step['label']) ?></strong>
                                    <small class="text-muted d-block"><?= html_escape(ucwords(str_replace('_', ' ', $step['approver_type']))) ?>
                                        <?php if ($step['user_name'] || $step['role_name']): ?> · <?= html_escape($step['user_name'] ?: $step['role_name']) ?><?php endif; ?>
                                    </small>
                                </div>
                                <div class="d-flex gap-1">
                                    <?php if ($may('edit')): ?>
                                        <button class="btn-icon js-edit" type="button" title="Edit Step" data-bs-toggle="modal" data-bs-target="#stepModal" data-target="#stepForm"
                                            data-record="<?= html_escape(json_encode($step)) ?>" data-title="Edit Workflow Step"><i class="fa-solid fa-pen"></i></button>
                                    <?php endif; ?>
                                    <?php if ($may('delete')): ?>
                                        <button class="btn-icon js-action" type="button" title="Remove Step" data-bs-toggle="modal" data-bs-target="#actionModal"
                                            data-url="<?= site_url('admin/workflows/step/delete') ?>" data-id="<?= (int) $step['id'] ?>"
                                            data-title="Remove Step" data-description="Remove this step from the workflow version."><i class="fa-solid fa-trash"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($steps[$r['id']])): ?><p class="text-secondary small">No approval steps configured yet.</p><?php endif; ?>
                        <?php if ($may('create')): ?>
                            <button class="btn btn-light btn-sm js-action mt-2" type="button"
                                data-bs-toggle="modal" data-bs-target="#actionModal"
                                data-url="<?= site_url('admin/workflows/clone') ?>"
                                data-id="<?= (int) $r['id'] ?>"
                                data-title="Clone Workflow Version"
                                data-description="Create an independent draft version with copies of the existing approval steps.">
                                <i class="fa-solid fa-code-branch me-1"></i> Clone to New Version
                            </button>
                        <?php endif; ?>
                        <?php if ($may('edit')): ?>
                            <button class="btn btn-outline-primary btn-sm js-edit mt-2" type="button" data-bs-toggle="modal" data-bs-target="#stepModal" data-target="#stepForm"
                                data-record="<?= html_escape(json_encode(['workflow_id' => $r['id']])) ?>" data-title="Add Workflow Step">
                                <i class="fa-solid fa-plus me-1"></i> Add Step
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form id="editForm" action="<?= site_url('admin/workflows/save') ?>" method="post" data-confirm="Save this workflow version?">
            <div class="modal-header"><h2 class="modal-title fs-6" id="editTitle">Create Workflow Version</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id" value="">
                <div class="mb-3"><label class="form-label" for="workflowName">Workflow Name</label><input class="form-control" id="workflowName" name="name" maxlength="160" required></div>
                <div class="mb-3"><label class="form-label" for="workflowType">Request Type</label><select class="form-select" id="workflowType" name="request_type" required>
                    <?php foreach ($typeNames as $slug => $label): ?><option value="<?= html_escape($slug) ?>"><?= html_escape($label) ?></option><?php endforeach; ?></select></div>
                <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="active" value="1" checked> Active</label>
                <label class="form-check"><input class="form-check-input" type="checkbox" name="is_default" value="1"> Use as default for this request type</label>
                <p class="small text-secondary mt-3 mb-0">Creating a new workflow automatically assigns its next version number. Configure approval steps before activating it as the default.</p>
            </div>
            <input type="hidden" name="confirmed" value="no"><input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Workflow</button></div>
        </form>
    </div></div>
</div>
<div class="modal fade" id="stepModal" tabindex="-1" aria-labelledby="stepTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form id="stepForm" action="<?= site_url('admin/workflows/step') ?>" method="post" data-confirm="Save this workflow step?">
            <div class="modal-header"><h2 class="modal-title fs-6" id="stepTitle">Workflow Step</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id" value=""><input type="hidden" name="workflow_id" value="">
                <div class="mb-3"><label class="form-label" for="stepOrder">Step Number</label><input class="form-control" type="number" min="1" id="stepOrder" name="step_order" required></div>
                <div class="mb-3"><label class="form-label" for="stepLabel">Step Name</label><input class="form-control" id="stepLabel" name="label" maxlength="160" required placeholder="e.g. Verify document completeness"></div>
                <div class="mb-3"><label class="form-label" for="approverType">Approver Based On</label>
                    <select class="form-select" id="approverType" name="approver_type" required>
                        <option value="user">Specific User</option><option value="role">Role</option>
                        <option value="requester_leader">Requester Leader</option><option value="requester">Requester Account</option>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label" for="approverUser">Specific User <span class="optional-label">(only for user step)</span></label>
                    <select class="form-select" id="approverUser" name="approver_user_id"><option value="">Select user</option>
                        <?php foreach ($users_list as $u): if ($u['active']): ?><option value="<?= (int) $u['id'] ?>"><?= html_escape($u['name']) ?></option><?php endif; endforeach; ?>
                    </select>
                </div>
                <div><label class="form-label" for="approverRole">Role <span class="optional-label">(only for role step)</span></label>
                    <select class="form-select" id="approverRole" name="approver_role_id"><option value="">Select role</option>
                        <?php foreach ($roles_list as $role): ?><option value="<?= (int) $role['id'] ?>"><?= html_escape(ucwords(str_replace('_', ' ', $role['name']))) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <input type="hidden" name="confirmed" value="no"><input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Step</button></div>
        </form>
    </div></div>
</div>
