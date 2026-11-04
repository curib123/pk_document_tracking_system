<?php
$table = $table ?? [];
$dt_path = 'documents/external';
$dt_columns = [
    'title' => 'Document Title',
    'status' => 'Status',
    'description' => 'Description',
    'revision_level' => 'Revision Level',
    'created_at' => 'Created Date',
    'updated_at' => 'Updated',
    'attachment' => 'Attach File'
];
$dt_rows = [];
foreach ($rows as $row) {
    $fileName = !empty($row['file_id']) ? (string) ($row['original_name'] ?: 'Download') : '';
    $display = [
        'Document Title' => $row['title'],
        'Status' => ucfirst($row['status']),
        'Description' => $row['description'] ?? '',
        'Revision Level' => $row['revision_level'] ?? '00',
        'Created Date' => $row['created_at'],
        'Updated' => $row['updated_at'],
        'Attach File' => $fileName ?: 'Unavailable'
    ];
    $buttons = [['type' => 'view']];
    if ($can_download && $row['status'] === 'active' && !empty($row['file_id'])) {
        $buttons[] = [
            'type' => 'download',
            'url' => 'files/download/' . (int) $row['file_id'],
            'label' => $fileName
        ];
    }
    if ($can_direct && $row['status'] === 'active') {
        $buttons[] = ['type' => 'edit', 'label' => 'Edit'];
    }
    if ($can_dispose && $row['status'] === 'active') {
        $buttons[] = [
            'type' => 'action',
            'url' => 'documents/external/dispose',
            'label' => 'Dispose',
            'disposal' => TRUE,
            'description' => 'Choose a disposal reason and confirm this action.',
            'icon' => 'fa-solid fa-trash-can'
        ];
    }
    $dt_rows[] = [
        'id' => (int) $row['id'],
        'cells' => [
            'title' => $row['title'],
            'status' => ucfirst($row['status']),
            'description' => $row['description'] ?? '',
            'revision_level' => $row['revision_level'] ?? '00',
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'attachment' => $fileName ?: 'Unavailable'
        ],
        'record' => [
            'type' => 'external_revise',
            'external_document_id' => (int) $row['id'],
            'title' => $row['title'],
            'description' => $row['description'] ?? ''
        ],
        'display' => $display,
        'buttons' => $buttons
    ];
}
$dt_q = $table['q'] ?? '';
$dt_filter = '';
$dt_page = $table['page'] ?? 1;
$dt_limit = $table['limit'] ?? 10;
$dt_total = $total ?? count($rows);
$dt_sort = $table['sort'] ?? 'updated_at';
$dt_dir = strtolower($table['dir'] ?? 'desc');
$dt_sortable = ['title', 'description', 'revision_level', 'created_at', 'updated_at'];
$dt_date_filters = TRUE;
$dt_filters = [
    'status' => ['all' => 'All Statuses', 'active' => 'Active', 'disposed' => 'Disposed']
];
if (!empty($owner_options)) {
    $dt_filters['owner'] = ['' => 'All Owners'] + array_column($owner_options, 'name', 'id');
}
$dt_filter_values = [
    'status' => $table['status'] ?? 'active',
    'owner' => $table['owner'] ?? '',
    'period' => $table['period'] ?? 'all',
    'from' => $table['from'] ?? '',
    'to' => $table['to'] ?? ''
];
$dt_badges = [];
$dt_create = $can_direct ? 'Add New External Document' : '';
?>

<?php $this->load->view('reusable_components/reusable_datatable', compact(
    'dt_path',
    'dt_columns',
    'dt_rows',
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
    'dt_create'
)); ?>

<?php if ($can_direct): ?>
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="editForm" method="post" enctype="multipart/form-data" action="<?= site_url('documents/external/direct') ?>"
                    data-confirm="Save this external document directly?" autocomplete="off">
                    <div class="modal-header">
                        <h2 class="modal-title fs-6" id="editTitle">Direct External</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="externalTitle">Document Title</label>
                                <input class="form-control" id="externalTitle" name="title" maxlength="255" required
                                    autocomplete="off">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="externalDescription">Description</label>
                                <textarea class="form-control" id="externalDescription" name="description"
                                    maxlength="2000" rows="3" autocomplete="off"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="externalAttachment">Attach File</label>
                                <input class="form-control" id="externalAttachment" type="file" name="external_attachment"
                                    accept=".pdf,.txt,.png,.jpg,.jpeg,.docx,.xlsx" required autocomplete="off">
                                <small class="form-text">Maximum file size: 15 MB.</small>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="type" value="external_create">
                    <input type="hidden" name="external_document_id" value="">
                    <input type="hidden" name="confirmed" value="no">
                    <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
                        value="<?= $this->security->get_csrf_hash() ?>">
                    <div class="modal-footer">
                        <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary" type="submit">Save Directly</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
