<?php
$may = function ($action) use ($permissions, $module) {
    return isset($permissions['*']) || !empty($permissions[$module][$action]);
};
$dt_columns = $mode === 'documents'
    ? ['code' => 'Code', 'title' => 'Document Title', 'version' => 'Version', 'place' => 'Location', 'status' => 'Status', 'updated' => 'Last Updated']
    : ['name' => 'Name', 'description' => 'Description', 'active' => 'Status'];
$dt_badges = $mode === 'documents' ? ['status'] : ['active'];
$dt_filters = ['status' => $mode === 'documents'
    ? ['' => 'All Statuses','active' => 'Active','archived' => 'Archived','disposed' => 'Disposed']
    : ['' => 'All Statuses','1' => 'Active','0' => 'Inactive']];
$dt_path = $base_path;
$dt_q = $q;
$dt_filter = $status;
$dt_page = $page;
$dt_limit = $limit;
$dt_total = $total;
$dt_create = $may('create') ? ($mode === 'documents' ? 'Add Document' : 'Add Place') : '';
$dt_rows = [];
foreach ($rows as $row) {
    if ($mode === 'documents') {
        $record = ['id' => $row['id'], 'code' => $row['code'], 'title' => $row['title'],
            'description' => $row['description'], 'version' => $row['version'], 'status' => $row['status'],
            'place_id' => $row['place_id'], 'category_id' => $row['category_id']];
        $display = ['Code' => $row['code'], 'Title' => $row['title'], 'Type' => ucfirst($row['kind']),
            'Version' => $row['version'], 'Location' => $row['place_name'],
            'Category' => $row['category_name'], 'Status' => $row['status'],
            'Created By' => $row['creator_name'], 'Description' => $row['description']];
        $cells = ['code' => $row['code'], 'title' => $row['title'], 'version' => $row['version'],
            'place' => $row['place_name'], 'status' => $row['status'], 'updated' => $row['updated_at']];
    } else {
        $record = ['id' => $row['id'], 'name' => $row['name'],
            'description' => $row['description'], 'active' => $row['active']];
        $display = ['Name' => $row['name'], 'Description' => $row['description'],
            'Status' => $row['active'] ? 'Active' : 'Inactive'];
        $cells = ['name' => $row['name'], 'description' => $row['description'],
            'active' => $row['active'] ? 'active' : '0'];
    }
    $buttons = [['type' => 'view']];
    if ($may('edit')) $buttons[] = ['type' => 'edit'];
    if ($may('delete')) $buttons[] = ['type' => 'action', 'url' => $delete_action, 'label' => $mode === 'documents' ? 'Dispose' : 'Deactivate',
        'description' => $mode === 'documents' ? 'This document will be marked as disposed.' : 'This place will be made inactive.',
        'icon' => 'fa-solid fa-trash-can'];
    $dt_rows[] = ['id' => $row['id'], 'cells' => $cells, 'record' => $record, 'display' => $display, 'buttons' => $buttons];
}
?>
<div class="page-heading"><div><span class="eyebrow"><?= $mode === 'documents' ? 'System Documents' : 'Master Data' ?></span>
    <h1><?= html_escape($title) ?></h1><p><?= $mode === 'documents' ? 'Manage controlled documents and their lifecycle.' : 'Maintain reference data used across document workflows.' ?></p></div></div>
<?php $prefix = $mode === 'documents' ? 'documents/' : 'places/'; ?>
<nav class="tab-bar" aria-label="<?= html_escape($title) ?> tabs">
    <?php foreach ($tabs as $slug => $label): if (isset($permissions['*']) || !empty($permissions[$slug]['view'])): ?>
        <a href="<?= site_url($prefix . $slug) ?>" class="tab-link <?= $current === $slug ? 'active' : '' ?>"><?= html_escape($label) ?></a>
    <?php endif; endforeach; ?>
</nav>
<?php $this->load->view('components/datatable', compact(
    'dt_total', 'dt_limit', 'dt_page', 'dt_path', 'dt_q', 'dt_filter',
    'dt_columns', 'dt_badges', 'dt_filters', 'dt_rows', 'dt_create'
)); ?>

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <form method="post" id="editForm" action="<?= site_url($form_action) ?>" data-confirm="Save these changes?">
            <div class="modal-header"><h2 class="modal-title fs-6" id="editTitle">Add Record</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div class="row g-3">
                <input type="hidden" name="id" value="">
                <?php if ($mode === 'documents'): ?>
                    <div class="col-md-5"><label class="form-label" for="code">Document Code</label><input class="form-control" id="code" name="code" maxlength="80" required></div>
                    <div class="col-md-7"><label class="form-label" for="title">Document Title</label><input class="form-control" id="title" name="title" maxlength="255" required></div>
                    <div class="col-md-4"><label class="form-label" for="version">Version</label><input class="form-control" id="version" name="version" value="1" maxlength="30" required></div>
                    <div class="col-md-4"><label class="form-label" for="placeId">Location</label><select class="form-select" id="placeId" name="place_id"><option value="">Not assigned</option><?php foreach ($place_options as $opt): ?><option value="<?= (int) $opt['id'] ?>"><?= html_escape($opt['name']) ?></option><?php endforeach; ?></select></div>
                    <?php if ($current === 'softcopy'): ?><div class="col-md-4"><label class="form-label" for="categoryId">Category</label><select class="form-select" id="categoryId" name="category_id"><option value="">No category</option><?php foreach ($category_options as $opt): ?><option value="<?= (int) $opt['id'] ?>"><?= html_escape($opt['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
                    <div class="col-md-4"><label class="form-label" for="docStatus">Status</label><select class="form-select" id="docStatus" name="status"><option value="active">Active</option><option value="archived">Archived</option><option value="disposed">Disposed</option></select></div>
                <?php else: ?>
                    <div class="col-md-8"><label class="form-label" for="placeName">Name</label><input class="form-control" id="placeName" name="name" maxlength="180" required></div>
                    <div class="col-md-4 d-flex align-items-end"><label class="form-check mb-2"><input class="form-check-input" name="active" type="checkbox" value="1" checked> Active</label></div>
                <?php endif; ?>
                <div class="col-12"><label class="form-label" for="recordDescription">Description <span class="optional-label">(optional)</span></label><textarea class="form-control" id="recordDescription" name="description" rows="3"></textarea></div>
            </div></div>
            <input type="hidden" name="confirmed" value="no"><input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Record</button></div>
        </form>
    </div></div>
</div>
