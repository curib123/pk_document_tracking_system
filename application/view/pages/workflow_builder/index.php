<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$canEdit = isset($permissions['*']) || !empty($permissions['workflows']['edit']);
$esc = static function ($value) {
  return html_escape((string) $value); };
$dt_columns = ['name' => 'Workflow', 'type' => 'Request Type', 'current' => 'Current Version', 'publication' => 'Publication', 'steps' => 'Steps'];
$dt_rows = [];
foreach ($rows as $workflow) {
  $published = NULL;
  $draft = NULL;
  foreach ($workflow['versions'] ?? [] as $version) {
    if ($version['status'] === 'draft')
      $draft = $version;
    if ($version['status'] === 'published' && (int) $version['is_default'] === 1)
      $published = $version;
  }
  $current = $published ? 'Version ' . $published['version_number'] : 'Not published';
  $graph = $published ? json_decode($published['graph'], TRUE) : [];
  $dt_rows[] = [
    'id' => $workflow['id'],
    'cells' => [
      'name' => $workflow['name'],
      'type' => ucwords(str_replace('_', ' ', $workflow['request_type'])),
      'current' => $current,
      'publication' => $draft ? 'Draft in progress' : ($published ? 'Published' : 'Needs setup'),
      'steps' => count($graph['steps'] ?? [])
    ],
    'display' => [
      'Workflow' => $workflow['name'],
      'Request Type' => ucwords(str_replace('_', ' ', $workflow['request_type'])),
      'Published Default' => $current,
      'Draft' => $draft ? 'Version ' . $draft['version_number'] : 'None',
      'Published Steps' => count($graph['steps'] ?? [])
    ],
    'buttons' => [['type' => 'view'], ['type' => 'steps']]
  ];
}
$dt_path = 'admin/workflows';
$dt_q = $table['q'];
$dt_filter = $table['status'];
$dt_page = $table['page'];
$dt_limit = $table['limit'];
$dt_total = $total;
$dt_sort = $table['sort'];
$dt_dir = strtolower($table['dir']);
$dt_sortable = ['name', 'type'];
$dt_filters = [
  'status' => ['' => 'All Workflows', '1' => 'Active', '0' => 'Inactive'],
  'publication' => ['' => 'All Versions', 'draft' => 'With Draft', 'published' => 'With Published Version']
];
$dt_filter_values = ['status' => $dt_filter, 'publication' => $table['publication']];
$dt_badges = [];
$dt_create = '';
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
  'dt_badges',
  'dt_create',
  'dt_columns',
  'dt_rows'
)); ?>

<?php foreach ($rows as $workflow): ?>
  <?php
  $versions = $workflow['versions'] ?? [];
  $hasDraft = FALSE;
  $selectedVersionId = 0;
  foreach ($versions as $v)
    if ($v['status'] === 'draft') {
      $hasDraft = TRUE;
      $selectedVersionId = (int) $v['id'];
    }
  if (!$selectedVersionId && $versions)
    $selectedVersionId = (int) $versions[0]['id'];
  ?>
  <div class="modal fade workflow-steps-modal" data-bs-backdrop="static" data-bs-keyboard="false"
    id="workflowStepsModal-<?= (int) $workflow['id'] ?>" data-workflow-id="<?= (int) $workflow['id'] ?>" tabindex="-1"
    aria-hidden="true" aria-labelledby="workflowTitle-<?= (int) $workflow['id'] ?>">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h2 class="modal-title h6 mb-1" id="workflowTitle-<?= (int) $workflow['id'] ?>"><?= $esc($workflow['name']) ?>
            </h2>
            <p class="small text-secondary mb-0">Approval route for
              <?= $esc(ucwords(str_replace('_', ' ', $workflow['request_type']))) ?> requests</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap mb-3">
            <div class="flex-grow-1" style="max-width:360px">
              <label class="form-label" for="workflowVersion-<?= (int) $workflow['id'] ?>">Workflow Version</label>
              <select class="form-select" id="workflowVersion-<?= (int) $workflow['id'] ?>" data-workflow-version autocomplete="off">
                <?php foreach ($versions as $v): ?>
                  <option value="<?= (int) $v['id'] ?>" <?= (int) $v['id'] === $selectedVersionId ? 'selected' : '' ?>>Version
                    <?= (int) $v['version_number'] ?> ·
                    <?= $esc(ucfirst($v['status'])) ?>    <?= $v['is_default'] ? ' · Default' : '' ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (!$versions): ?><span class="small text-secondary">No version exists yet.</span><?php endif; ?>
            </div>
            <?php if ($canEdit && !$hasDraft): ?>
              <form method="post" action="<?= site_url('admin/workflows/clone') ?>"
                data-confirm="Create a new editable workflow draft?" autocomplete="off">
                <input type="hidden" name="id" value="<?= (int) $workflow['id'] ?>">
                <input type="hidden" name="workflow_id" value="<?= (int) $workflow['id'] ?>">
                <input type="hidden" name="confirmed" value="no">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
                  value="<?= $this->security->get_csrf_hash() ?>">
                <button type="submit" class="btn btn-primary"><i
                    class="fa-solid fa-code-branch me-1"></i><?= $versions ? 'Create Next Draft' : 'Start First Draft' ?></button>
              </form>
            <?php endif; ?>
          </div>
          <?php foreach ($versions as $version):
            $graph = json_decode($version['graph'], TRUE);
            $steps = is_array($graph['steps'] ?? NULL) ? $graph['steps'] : [];
            $isDraft = $version['status'] === 'draft';
            $graphHash = hash('sha256', (string) $version['graph']);
            ?>
            <div data-workflow-version-panel="<?= (int) $version['id'] ?>" data-draft="<?= $isDraft ? 'yes' : 'no' ?>"
              <?= (int) $version['id'] !== $selectedVersionId ? 'hidden' : '' ?>>
              <div class="workflow-stage-overview mb-3">
                <div><small class="text-secondary">Setup
                    stage</small><strong><?= $isDraft ? ($steps ? 'Ready for review' : 'Add your first approver') : 'Published version' ?></strong>
                </div>
                <div><small class="text-secondary">Approval steps</small><strong><?= count($steps) ?> / 30</strong></div>
                <div><small class="text-secondary">Next
                    action</small><strong><?= $isDraft ? 'Configure, then publish' : 'Create a draft to revise' ?></strong>
                </div>
              </div>
              <div class="d-flex gap-2 flex-wrap align-items-center mb-3">
                <span
                  class="badge <?= $isDraft ? 'text-bg-warning' : 'text-bg-success' ?>"><?= $esc(ucfirst($version['status'])) ?></span>
                <?php if ($version['is_default']): ?><span class="badge text-bg-primary">Published
                    default</span><?php endif; ?>
                <small class="text-secondary"><?= count($steps) ?> approval <?= count($steps) === 1 ? 'step' : 'steps' ?></small>
              </div>
              <?php if (!$steps): ?>
                <div class="empty-state"><strong>No approval steps defined.</strong>
                  <span
                    class="empty-hint"><?= $isDraft ? 'Add an approval step before publishing.' : 'This version has no route.' ?></span>
                </div>
              <?php else: ?>
                <ol class="workflow-step-list mb-3">
                  <?php foreach ($steps as $i => $step):
                    $approver = $step['approver'] ?? [];
                    $type = $approver['type'] ?? '';
                    $record = [
                      'workflow_version_id' => $version['id'],
                      'step_key' => $step['key'] ?? '',
                      'name' => $step['name'] ?? '',
                      'approver_type' => $type,
                      'workflow_id' => $workflow['id'],
                      'approver_user_id' => $type === 'user' ? ($approver['value'] ?? '') : '',
                      'approver_role_id' => $type === 'role' ? ($approver['value'] ?? '') : '',
                      'graph_hash' => $graphHash
                    ];
                    ?>
                    <li class="workflow-step-row">
                      <div class="flex-grow-1">
                        <strong class="d-block"><?= $esc($step['name'] ?? 'Unnamed Step') ?></strong>
                        <span class="small text-secondary"><?= $esc(ucwords(str_replace('_', ' ', $type))) ?> ·
                          <?= $esc($approver['label'] ?? ($type === 'requester_leader' ? "Requester's leader" : 'Requester')) ?></span>
                      </div>
                      <?php if ($isDraft && $canEdit): ?>
                        <div class="d-flex gap-1 flex-wrap">
                          <button type="button" class="btn btn-sm btn-outline-primary js-workflow-step"
                            data-step-record="<?= $esc(json_encode($record, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
                            <i class="fa-solid fa-pen me-1" aria-hidden="true"></i> Edit
                          </button>
                          <?php foreach (['up' => 'fa-arrow-up', 'down' => 'fa-arrow-down'] as $direction => $icon): ?>
                            <form method="post" action="<?= site_url('admin/workflows/step/move') ?>"
                              data-confirm="Move this approval step?" autocomplete="off">
                              <input type="hidden" name="id" value="<?= (int) $version['id'] ?>">
                              <input type="hidden" name="workflow_id" value="<?= (int) $workflow['id'] ?>">
                              <input type="hidden" name="step_key" value="<?= $esc($step['key']) ?>">
                              <input type="hidden" name="direction" value="<?= $direction ?>">
                              <input type="hidden" name="graph_hash" value="<?= $graphHash ?>">
                              <input type="hidden" name="confirmed" value="no">
                              <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
                                value="<?= $this->security->get_csrf_hash() ?>">
                              <button class="btn btn-sm btn-light" type="submit" aria-label="Move step <?= $direction ?>"
                                <?= ($direction === 'up' && $i === 0) || ($direction === 'down' && $i === count($steps) - 1) ? 'disabled' : '' ?>><i
                                  class="fa-solid <?= $icon ?> me-1" aria-hidden="true"></i>
                                <?= $direction === 'up' ? 'Move Up' : 'Move Down' ?></button>
                            </form>
                          <?php endforeach; ?>
                          <form method="post" action="<?= site_url('admin/workflows/step/remove') ?>"
                            data-confirm="Remove this approval step?" autocomplete="off">
                            <input type="hidden" name="id" value="<?= (int) $version['id'] ?>">
                            <input type="hidden" name="workflow_id" value="<?= (int) $workflow['id'] ?>">
                            <input type="hidden" name="step_key" value="<?= $esc($step['key']) ?>">
                            <input type="hidden" name="graph_hash" value="<?= $graphHash ?>">
                            <input type="hidden" name="confirmed" value="no">
                            <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
                              value="<?= $this->security->get_csrf_hash() ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Remove step"><i
                                class="fa-solid fa-trash-can me-1" aria-hidden="true"></i> Remove</button>
                          </form>
                        </div>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ol>
              <?php endif; ?>
              <?php if ($isDraft && $canEdit): ?>
                <div class="d-flex flex-wrap gap-2 justify-content-between border-top pt-3">
                  <button type="button" class="btn btn-outline-primary js-workflow-step"
                    data-step-record="<?= $esc(json_encode(['workflow_version_id' => $version['id'], 'workflow_id' => $workflow['id'], 'graph_hash' => $graphHash], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
                    <i class="fa-solid fa-plus me-1"></i>Add Approval Step
                  </button>
                  <form method="post" action="<?= site_url('admin/workflows/publish') ?>"
                    data-confirm="Publish this approval version as the default for all future requests? Existing requests keep their original approval route." autocomplete="off">
                    <input type="hidden" name="id" value="<?= (int) $version['id'] ?>">
                    <input type="hidden" name="workflow_id" value="<?= (int) $workflow['id'] ?>">
                    <input type="hidden" name="graph_hash" value="<?= $graphHash ?>">
                    <input type="hidden" name="confirmed" value="no">
                    <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
                      value="<?= $this->security->get_csrf_hash() ?>">
                    <button class="btn btn-primary" type="submit" <?= !$steps ? 'disabled' : '' ?>>
                      <i class="fa-solid fa-check-double me-1"></i>Validate & Publish
                    </button>
                  </form>
                </div>
              <?php else: ?>
                <p class="small text-secondary mb-0">Published versions are read-only. Create a new draft to change future
                  requests.</p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Close</button></div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php if ($canEdit): ?>
  <div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="stepModal" tabindex="-1"
    aria-labelledby="stepTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="stepForm" action="<?= site_url('admin/workflows/step/save') ?>" method="post"
          data-confirm="Save this approval step?" autocomplete="off">
          <div class="modal-header">
            <h2 id="stepTitle" class="modal-title h6">Approval Step</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="workflow_version_id" value="">
            <input type="hidden" name="workflow_id" value="">
            <input type="hidden" name="step_key" value="">
            <input type="hidden" name="graph_hash" value="">
            <div class="mb-3"><label for="stepName" class="form-label">Approval Step Name</label>
              <input class="form-control" id="stepName" name="name" maxlength="120" placeholder="e.g. Supervisor Review"
                required autocomplete="off">
            </div>
            <div class="mb-3"><label class="form-label" for="stepApproverType">Approver Type</label>
              <select class="form-select" id="stepApproverType" name="approver_type" required autocomplete="off">
                <option value="role">Any active member of role</option>
                <option value="user">Specific user</option>
                <option value="requester_leader">Requester's leader</option>
                <option value="requester">Requester account</option>
              </select>
            </div>
            <div class="mb-3" id="stepRoleField"><label class="form-label" for="stepRole">Approver Role</label>
              <select class="form-select" id="stepRole" name="approver_role_id" data-searchable autocomplete="off">
                <option value="">Select role</option>
                <?php foreach ($roles as $role): ?>
                  <option value="<?= (int) $role['id'] ?>"><?= $esc($role['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3" id="stepUserField" hidden><label class="form-label" for="stepUser">Specific Approver</label>
              <select class="form-select" id="stepUser" name="approver_user_id" data-searchable disabled autocomplete="off">
                <option value="">Select user</option>
                <?php foreach ($users as $account): ?>
                  <option value="<?= (int) $account['id'] ?>"><?= $esc($account['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <p class="small text-secondary mb-0">Requester and requester's leader are resolved when the request is
              submitted.</p>
          </div>
          <input type="hidden" name="confirmed" value="no">
          <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
            value="<?= $this->security->get_csrf_hash() ?>">
          <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Step</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>