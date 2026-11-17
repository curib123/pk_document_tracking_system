<?php
require_once APPPATH . 'services/documents/scanned_policy.php';
$can = static fn($action) => isset($permissions['*']) || !empty($permissions['scanned_documents'][$action]);
$paths = array_column($categories, 'path', 'id');
$dt_rows = [];
foreach ($rows as $row) {
    $links = []; $fileFields = [];
    foreach ($files[$row['id']] ?? [] as $index => $file) {
        $fileFields[($index + 1) . '. ' . $file['original_name']] = number_format((int) $file['size']) . ' bytes';
        $links[] = ['label' => $file['original_name'], 'url' => site_url('documents/scanned/file/' . (int) $file['id'])];
    }
    $buttons = [['type' => 'view']];
    if ($row['status'] === 'active') foreach (['edit' => ['scanned_update', 'Request Update'], 'delete' => ['scanned_delete', 'Request Deletion']] as $action => [$type, $label])
        if ($can($action) && (isset($permissions['*']) || !empty($permissions['requests']['add'])))
            $buttons[] = ['type' => 'link', 'label' => $label, 'url' => 'my-requests/scanned?' . http_build_query(['action' => $type, 'document' => $row['id']])];
    $dt_rows[] = [
        'id' => $row['id'], 'cells' => [
            'title' => $row['title'], 'category' => $paths[$row['category_id']] ?? $row['category_name'],
            'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'],
            'retention_start_date' => $row['retention_start_date'], 'retention_end_date' => $row['retention_end_date'],
            'remaining' => Scanned_policy::countdown($row['retention_end_date']), 'scan_due_date' => $row['scan_due_date'] ?: 'Not scheduled'
        ],
        'display' => ['kind' => 'document', 'header' => ['title' => $row['title'], 'status' => $row['status']], 'sections' => [
            ['key' => 'overview', 'title' => 'Scanned Document', 'fields' => [
                'Category' => $paths[$row['category_id']] ?? $row['category_name'],
                'Created' => $row['created_at'], 'Updated' => $row['updated_at'], 'Retention Start' => $row['retention_start_date'],
                'Retention End' => $row['retention_end_date'], 'Time Remaining' => Scanned_policy::countdown($row['retention_end_date']),
                'Next Upload Due' => $row['scan_due_date'] ?: 'Not scheduled'
            ]], ['key' => 'files', 'title' => 'Approved Files', 'links' => $links, 'fields' => $fileFields]
        ]], 'buttons' => $buttons
    ];
}
$dt_columns = ['title' => 'Title', 'category' => 'Category / Folder', 'created_at' => 'Created', 'updated_at' => 'Updated',
    'retention_start_date' => 'Retention Start', 'retention_end_date' => 'Retention End', 'remaining' => 'Days / Hours', 'scan_due_date' => 'Next Upload'];
$dt_path = 'documents/scanned'; $dt_q = $table['q']; $dt_filter = $table['status']; $dt_page = $table['page'];
$dt_limit = $table['limit']; $dt_total = $total; $dt_sort = $table['sort']; $dt_dir = strtolower($table['dir']);
$dt_sortable = ['title', 'created_at', 'updated_at', 'retention_end_date']; $dt_create = '';
$dt_filters = ['status' => ['active' => 'Active', 'deleted' => 'Deleted after approval'], 'category' => ['' => 'All Approved Folders'] + $paths];
$dt_filter_values = ['status' => $table['status'], 'category' => $selected_category];
?>
<section class="workspace-card p-3 mb-3">
  <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <h2 class="h5 mb-0 me-auto">Scanned Documents</h2>
    <?php if (isset($permissions['*']) || (!empty($permissions['requests']['add']) && ($can('request') || !empty($permissions['scanned_categories']['request'])))): ?>
      <a class="btn btn-primary" href="<?= site_url('my-requests/scanned?action=scanned_create') ?>">Upload / Request Category</a>
    <?php endif; ?>
    <a class="btn btn-light" href="<?= site_url('my-requests/scanned') ?>">My Scanned Requests</a>
    <button class="btn btn-light" type="button" data-scan-alarm-enable>Enable reminder sound</button>
  </div>
  <p class="text-secondary mb-0">Choose a folder using the category filter. Folder paths show nested categories. Uploads, changes and deletions need DCO approval. Retention includes the full end date; expired records trigger a popup. At 4:00 PM Philippine time, a reminder lists scanned file uploads due while you have the app open.</p>
</section>
<?php $this->load->view('reusable_components/reusable_datatable', compact('dt_path', 'dt_q', 'dt_filter', 'dt_page',
    'dt_limit', 'dt_total', 'dt_sort', 'dt_dir', 'dt_sortable', 'dt_filters', 'dt_filter_values', 'dt_create', 'dt_columns', 'dt_rows')); ?>
