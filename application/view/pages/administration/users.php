<?php
$may = function ($action) use ($permissions) {
    return isset($permissions['*']) || !empty($permissions['users'][$action]);
};
$dt_columns = ['name' => 'Full Name', 'username' => 'Username', 'email' => 'Email',
    'role' => 'Role', 'leader' => 'Leader', 'active' => 'Status'];
$dt_badges = ['active'];
$dt_filters = ['status' => ['' => 'All Statuses','1' => 'Active','0' => 'Inactive']];
$dt_path = 'admin/users';
$dt_q = trim((string) $this->input->get('q', TRUE));
$dt_filter = (string) $this->input->get('status', TRUE);
$dt_page = max(1, (int) $this->input->get('page'));
$dt_limit = (int) $this->input->get('limit');
if (!in_array($dt_limit, [10,25,50,100], TRUE)) $dt_limit = 10;
$filtered = array_values(array_filter($rows, function ($r) use ($dt_q, $dt_filter) {
    return ($dt_q === '' || stripos($r['name'], $dt_q) !== FALSE ||
        stripos($r['username'], $dt_q) !== FALSE || stripos($r['email'], $dt_q) !== FALSE)
        && ($dt_filter === '' || (string) $r['active'] === $dt_filter);
}));
$dt_total = count($filtered);
$dt_rows = [];
foreach (array_slice($filtered, ($dt_page - 1) * $dt_limit, $dt_limit) as $r) {
    $buttons = [['type' => 'view']];
    if ($may('edit')) $buttons[] = ['type' => 'edit'];
    if ($may('delete') && (int) $r['active'] && $r['id'] != $user['id']) {
        $buttons[] = ['type' => 'action', 'url' => 'admin/users/delete', 'label' => 'Deactivate',
            'description' => 'Disable sign-in for this user without deleting their records.',
            'icon' => 'fa-solid fa-user-slash'];
    }
    $dt_rows[] = [
        'id' => $r['id'], 'cells' => ['name' => $r['name'], 'username' => $r['username'],
            'email' => $r['email'], 'role' => ucwords(str_replace('_', ' ', $r['role_name'])),
            'leader' => $r['leader_name'], 'active' => $r['active'] ? 'active' : '0'],
        'display' => ['Name' => $r['name'], 'Username' => $r['username'], 'Email' => $r['email'],
            'Role' => ucwords(str_replace('_', ' ', $r['role_name'])),
            'Leader' => $r['leader_name'], 'Status' => $r['active'] ? 'Active' : 'Inactive'],
        'record' => ['id' => $r['id'], 'name' => $r['name'], 'username' => $r['username'],
            'email' => $r['email'], 'role_id' => $r['role_id'],
            'leader_id' => $r['leader_id'], 'active' => $r['active']],
        'buttons' => $buttons
    ];
}
$dt_create = $may('create') ? 'Add User' : '';
?>
<div class="page-heading"><div><span class="eyebrow">Administration</span><h1>User Management</h1><p>Manage staff accounts, roles and reporting lines.</p></div></div>
<?php $this->load->view('components/datatable'); ?>
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <form action="<?= site_url('admin/users/save') ?>" method="post" id="editForm" data-confirm="Save this user account?">
            <div class="modal-header"><h2 class="modal-title fs-6" id="editTitle">Add User</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div class="row g-3">
                <input type="hidden" name="id" value="">
                <div class="col-md-6"><label class="form-label" for="userName">Full Name</label><input class="form-control" id="userName" name="name" maxlength="120" required></div>
                <div class="col-md-6"><label class="form-label" for="userLogin">Username</label><input class="form-control" id="userLogin" name="username" minlength="3" maxlength="80" pattern="[a-zA-Z0-9._-]+" required></div>
                <div class="col-md-6"><label class="form-label" for="userEmail">Email</label><input class="form-control" id="userEmail" type="email" name="email" maxlength="180" required></div>
                <div class="col-md-6"><label class="form-label" for="userPassword">Password <span class="optional-label">(leave empty to keep existing)</span></label><input class="form-control" type="password" id="userPassword" name="password" minlength="12" autocomplete="new-password"><small class="text-muted">Required for a new user, 12+ characters.</small></div>
                <div class="col-md-6"><label class="form-label" for="userRole">Role</label><select class="form-select" id="userRole" name="role_id" required><option value="">Select role</option><?php foreach ($roles_list as $role): ?><option value="<?= (int) $role['id'] ?>"><?= html_escape(ucwords(str_replace('_', ' ', $role['name']))) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label" for="userLeader">Leader <span class="optional-label">(optional)</span></label><select class="form-select" id="userLeader" name="leader_id"><option value="">No assigned leader</option><?php foreach ($rows as $manager): if ($manager['active']): ?><option value="<?= (int) $manager['id'] ?>"><?= html_escape($manager['name']) ?></option><?php endif; endforeach; ?></select></div>
                <div class="col-12"><label class="form-check"><input class="form-check-input" name="active" type="checkbox" value="1" checked> Active account</label></div>
            </div></div>
            <input type="hidden" name="confirmed" value="no"><input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save User</button></div>
        </form>
    </div></div>
</div>
