<?php
$table = $table ?? [];
$dt_path = 'documents/external';
$dt_columns = [
    'title' => 'Document Title',
    'description' => 'Description',
    'retention_start_date' => 'Retention Start Date',
    'retention_end_date' => 'Retention End Date',
    'retention_period' => 'Retention Period',
    'revision_level' => 'Revision Level',
    'updated_at' => 'Updated',
    'attachment' => 'Attach File'
];
$dt_rows = [];
foreach ($rows as $row) {
    $fileName = !empty($row['file_id']) ? (string) ($row['original_name'] ?: 'Download') : '';
    $display = [
        'Document Title' => $row['title'],
        'Description' => $row['description'] ?? '',
        'Retention Start Date' => $row['retention_start_date'] ?? '',
        'Retention End Date' => $row['retention_end_date'] ?? '',
        'Retention Period' => $row['retention_period'],
        'Revision Level' => $row['revision_level'] ?? '00',
        'Updated' => $row['updated_at'],
        'Attach File' => $fileName ?: 'Unavailable'
    ];
    $buttons = [['type' => 'view']];
    if ($can_download && !empty($row['file_id'])) {
        $buttons[] = [
            'type' => 'download',
            'url' => 'files/download/' . (int) $row['file_id'],
            'label' => $fileName
        ];
    }
    $dt_rows[] = [
        'id' => (int) $row['id'],
        'cells' => [
            'title' => $row['title'],
            'description' => $row['description'] ?? '',
            'retention_start_date' => $row['retention_start_date'] ?? '',
            'retention_end_date' => $row['retention_end_date'] ?? '',
            'retention_period' => $row['retention_period'],
            'revision_level' => $row['revision_level'] ?? '00',
            'updated_at' => $row['updated_at'],
            'attachment' => $fileName ?: 'Unavailable'
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
$dt_sortable = ['title', 'description', 'retention_start_date', 'retention_end_date', 'retention_period', 'revision_level', 'updated_at'];
$dt_filters = [];
$dt_filter_values = [];
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
                            <div class="col-md-6">
                                <label class="form-label" for="externalRetentionStart">Retention Start Date</label>
                                <input class="form-control" type="date" id="externalRetentionStart"
                                    name="retention_start_date" data-external-retention-field required autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="externalRetentionEnd">Retention End Date</label>
                                <input class="form-control" type="date" id="externalRetentionEnd"
                                    name="retention_end_date" data-external-retention-field required autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="externalRetention">Calculated Retention Period</label>
                                <input class="form-control" id="externalRetention" name="retention_period" readonly
                                    data-retention-period autocomplete="off">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="externalAttachment">Attach File</label>
                                <input class="form-control" id="externalAttachment" type="file" name="external_attachment"
                                    accept=".pdf,.txt,.png,.jpg,.jpeg,.docx,.xlsx" required autocomplete="off">
                                <small class="form-text">Maximum file size: 15 MB.</small>
                            </div>
                        </div>
                    </div>
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
