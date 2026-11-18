<?php
require_once APPPATH . 'services/documents/scanned_policy.php';
require_once APPPATH . 'services/documents/scanned_file_presentation.php';
$can = static fn($action) => isset($permissions['*']) || !empty($permissions['scanned_documents'][$action]);
$paths = array_column($categories, 'path', 'id');
$dt_rows = [];
foreach ($rows as $row) {
    $links = []; $fileFields = [];
    foreach ($files[$row['id']] ?? [] as $index => $file) {
        $fileFields[($index + 1) . '. ' . $file['original_name']] = number_format((int) $file['size']) . ' bytes';
        $links[] = Scanned_file_presentation::link($file);
    }
    $buttons = [['type' => 'view']];
    if ($row['status'] === 'active') {
        foreach ([
            'edit' => ['scanned_update', 'Update', 'fa-solid fa-pen', 'edit'],
            'delete' => ['scanned_delete', 'Remove', 'fa-solid fa-trash', 'danger']
        ] as $action => [$type, $label, $icon, $intent]) {
            if ($can($action) && (isset($permissions['*']) || !empty($permissions['requests']['add']))) {
                $buttons[] = [
                    'type' => 'link', 'label' => $label, 'icon' => $icon, 'intent' => $intent,
                    'url' => 'my-requests?' . http_build_query(['request_category' => 'scanned', 'action' => $type, 'document' => $row['id']])
                ];
            }
        }
    }
    $dt_rows[] = [
        'id' => $row['id'], 'record_path' => ['category_id' => (int) $row['category_id']], 'cells' => [
            'title' => $row['title'], 'status' => $row['status'], 'category' => $paths[$row['category_id']] ?? $row['category_name'],
            'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'],
            'retention_start_date' => $row['retention_start_date'], 'retention_end_date' => $row['retention_end_date'],
            'remaining' => Scanned_policy::countdown($row['retention_end_date'])
        ],
        'display' => ['kind' => 'document', 'header' => ['title' => $row['title'], 'status' => $row['status']], 'sections' => [
            ['key' => 'overview', 'title' => 'Scanned Document', 'fields' => [
                'Document Description' => $row['description'] ?: 'No description',
                'Category' => $paths[$row['category_id']] ?? $row['category_name'],
                'Created' => $row['created_at'], 'Updated' => $row['updated_at'], 'Retention Start' => $row['retention_start_date'],
                'Retention End' => $row['retention_end_date'], 'Time Remaining' => Scanned_policy::countdown($row['retention_end_date'])
            ]], ['key' => 'files', 'title' => 'Approved Files', 'links' => $links, 'fields' => $fileFields]
        ]], 'buttons' => $buttons
    ];
}
$dt_columns = ['title' => 'Title', 'category' => 'Category / Folder', 'created_at' => 'Created', 'updated_at' => 'Updated',
    'retention_start_date' => 'Retention Start', 'retention_end_date' => 'Retention End', 'remaining' => 'Days / Hours'];
$dt_path = 'documents/scanned'; $dt_q = $table['q']; $dt_filter = $table['status']; $dt_page = $table['page'];
$dt_limit = $table['limit']; $dt_total = $total; $dt_sort = $table['sort']; $dt_dir = strtolower($table['dir']);
$dt_sortable = ['title', 'created_at', 'updated_at', 'retention_end_date']; $dt_create = '';
$dt_filters = ['status' => ['active' => 'Active', 'deleted' => 'Deleted after approval'], 'category' => ['' => 'All Approved Folders'] + $paths];
$dt_filter_values = ['status' => $table['status'], 'category' => $selected_category];
$dt_folder_browser = $folder_browser; $dt_folder_domain = 'scanned';
$activeLayout = $table['layout'] === 'folder' ? 'folder' : 'table';
$dt_extra_params = ['folder' => $folder_browser['folder'], 'layout' => $activeLayout];
$layoutLink = static fn($target) => site_url('documents/scanned') . '?' . http_build_query([
    'q' => $dt_q, 'status' => $dt_filter, 'category' => $selected_category, 'limit' => $dt_limit,
    'sort' => $dt_sort, 'dir' => $dt_dir, 'folder' => $folder_browser['folder'], 'layout' => $target, 'page' => 1
]);
?>
<section class="workspace-card p-3 mb-3">
  <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <h2 class="h5 mb-0 me-auto">Scanned Documents</h2>
    <?php if (isset($permissions['*']) || (!empty($permissions['requests']['add']) && ($can('request') || !empty($permissions['scanned_categories']['request'])))): ?>
      <a class="btn btn-primary" href="<?= site_url('my-requests?request_category=scanned&action=scanned_create') ?>">Upload / Request Category</a>
    <?php endif; ?>
    <a class="btn btn-light" href="<?= site_url('my-requests?request_category=scanned') ?>">My Scanned Requests</a>
    <button class="btn btn-light" type="button" data-scan-alarm-enable>Enable reminder sound</button>
  </div>
</section>
<div class="document-layout-bar">
  <div class="document-layout-description"><strong><i class="fa-solid fa-folder-open" aria-hidden="true"></i> Scanned Document Browser</strong></div>
  <?php $this->load->view('reusable_components/document_layout_switcher', compact('activeLayout', 'layoutLink')); ?>
</div>
<div class="document-register-listing" data-doc-domain="scanned" data-layout="<?= html_escape($activeLayout) ?>">
<?php $this->load->view('reusable_components/reusable_datatable', compact('dt_path', 'dt_q', 'dt_filter', 'dt_page',
    'dt_limit', 'dt_total', 'dt_sort', 'dt_dir', 'dt_sortable', 'dt_filters', 'dt_filter_values', 'dt_create', 'dt_columns', 'dt_rows',
    'dt_extra_params', 'dt_folder_browser', 'dt_folder_domain')); ?>
</div>
