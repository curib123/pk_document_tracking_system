<?php
$may = function ($action) use ($permissions) {
    return isset($permissions['*']) || !empty($permissions['roles'][$action]);
};
$roleGrants = [];
foreach ($granted as $g) $roleGrants[$g['role_id']][] = (int) $g['permission_id'];
$dt_columns = ['name' => 'Role Name', 'description' => 'Description', 'category' => 'Category', 'access' => 'Permissions'];
$dt_badges = [];
$dt_filters = [];
$dt_path = 'admin/roles';
$dt_q = trim((string) $this->input->get('q', TRUE));
$dt_filter = '';
$dt_limit = (int) $this->input->get('limit');
if (!in_array($dt_limit, [10,25,50,100], TRUE)) $dt_limit = 10;
$dt_page = max(1, (int) $this->input->get('page'));
$filtered = array_values(array_filter($rows, function ($r) use ($dt_q) {
    return $dt_q === '' || stripos($r['name'], $dt_q) !== FALSE;
}));
$dt_total = count($filtered);
$dt_rows = [];
$allPermissions = [];
foreach ($permission_rows as $p) $allPermissions[$p['id']] = $p['module'] . ' · ' . $p['action'];
foreach (array_slice($filtered, ($dt_page - 1) * $dt_limit, $dt_limit) as $r) {
    $names = [];
    foreach ($roleGrants[$r['id']] ?? [] as $pid) if (isset($allPermissions[$pid])) $names[] = $allPermissions[$pid];
    $buttons = [['type' => 'view']];
    if ($may('edit') && $r['name'] !== 'super_admin') $buttons[] = ['type' => 'edit'];
    $dt_rows[] = ['id' => $r['id'],
        'cells' => ['name' => ucwords(str_replace('_', ' ', $r['name'])), 'description' => $r['description'],
            'category' => $r['is_system'] ? 'System' : 'Custom', 'access' => count($names) . ' actions'],
        'display' => ['Role' => ucwords(str_replace('_', ' ', $r['name'])), 'Description' => $r['description'],
            'Permissions' => $r['name'] === 'super_admin' ? 'All actions (system administrator)' : implode(', ', $names)],
        'record' => ['id' => $r['id'], 'name' => $r['name'], 'description' => $r['description'],
            'permission_ids' => $roleGrants[$r['id']] ?? []],
        'buttons' => $buttons];
}
$dt_create = $may('create') ? 'Create Role' : '';
?>
<div class="page-heading"><div><span class="eyebrow">Access Control</span><h1>Roles & Permissions</h1>
<p>Assign module-level permissions for every page and action. Server-side checks are authoritative.</p></div></div>
<?php $this->load->view('components/datatable', compact(
    'dt_total', 'dt_limit', 'dt_page', 'dt_path', 'dt_q', 'dt_filter',
    'dt_columns', 'dt_badges', 'dt_filters', 'dt_rows', 'dt_create'
)); ?>
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content">
        <form action="<?= site_url('admin/roles/save') ?>" method="post" id="editForm" data-confirm="Save role permissions?">
            <div class="modal-header"><h2 class="modal-title fs-6" id="editTitle">Create Role</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id" value="">
                <div class="row g-3 mb-4">
                    <div class="col-md-4"><label class="form-label" for="roleName">Role Name</label><input class="form-control" id="roleName" name="name" required pattern="[a-z][a-z0-9_]{2,79}" placeholder="e.g. quality_officer"></div>
                    <div class="col-md-8"><label class="form-label" for="roleDescription">Description <span class="optional-label">(optional)</span></label><input class="form-control" id="roleDescription" name="description" maxlength="255"></div>
                </div>
                <h3 class="fs-6 fw-bold mb-3">Module Permissions</h3>
                <?php $groups = []; foreach ($permission_rows as $p) $groups[$p['module']][] = $p; ?>
                <div class="row g-3">
                    <?php foreach ($groups as $name => $group): ?>
                    <div class="col-md-6 col-xl-4"><div class="border rounded-3 p-3 h-100">
                        <strong class="d-block mb-2"><?= html_escape(ucwords(str_replace(['-','_'], ' ', $name))) ?></strong>
                        <div class="role-matrix">
                            <?php foreach ($group as $p): ?>
                            <label class="permission-item"><input type="checkbox" class="form-check-input me-1"
                                name="permissions[]" value="<?= (int) $p['id'] ?>"> <?= html_escape(ucfirst($p['action'])) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <input type="hidden" name="confirmed" value="no"><input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Role</button></div>
        </form>
    </div></div>
</div>
