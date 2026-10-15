<?php
$dt_path = 'admin/audit';
$dt_columns = ['timestamp' => 'Recorded At', 'actor' => 'User', 'action' => 'Action', 'subject' => 'Subject', 'status' => 'Result'];
$dt_rows = [];
foreach ($rows as $row) {
    $dt_rows[] = [
        'id' => $row['id'],
        'cells' => [
            'timestamp' => $row['timestamp'],
            'actor' => $row['actor_name'],
            'action' => $row['action'],
            'subject' => $row['subject'],
            'status' => $row['status']
        ],
        'display' => [
            'kind' => 'record',
            'header' => ['title' => 'Audit Event', 'reference' => $row['action'], 'status' => $row['status']],
            'sections' => [
                [
                    'key' => 'overview',
                    'title' => 'Recorded Activity',
                    'fields' => [
                        'Recorded At' => $row['timestamp'],
                        'User' => $row['actor_name'],
                        'User ID' => $row['actor_id'],
                        'Action' => $row['action'],
                        'Subject' => $row['subject'],
                        'Result' => ucfirst($row['status']),
                        'Details' => $row['metadata'] ? json_encode($row['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'No additional details'
                    ]
                ]
            ]
        ],
        'buttons' => [['type' => 'view']]
    ];
}
$dt_q = $table['q'];
$dt_filter = $table['status'];
$dt_filter_values = ['status' => $table['status'], 'from' => $table['from'], 'to' => $table['to']];
$dt_filters = ['status' => ['' => 'All Results', 'success' => 'Success', 'failed' => 'Failed']];
$dt_page = $table['page'];
$dt_limit = $table['limit'];
$dt_total = $total;
$dt_sort = 'timestamp';
$dt_dir = strtolower($table['dir']);
$dt_sortable = ['timestamp'];
$dt_date_filters = TRUE;
$dt_badges = ['status'];
$dt_create = '';
?>
<section class="workspace-card mb-3">
    <?php foreach ($audit_warnings as $warning): ?>
        <p class="small text-danger px-3 mb-2" role="status"><?= html_escape($warning) ?></p>
    <?php endforeach; ?>
</section>
<?php $this->load->view('reusable_components/reusable_datatable', compact(
    'dt_path',
    'dt_columns',
    'dt_rows',
    'dt_q',
    'dt_filter',
    'dt_filter_values',
    'dt_filters',
    'dt_page',
    'dt_limit',
    'dt_total',
    'dt_sort',
    'dt_dir',
    'dt_sortable',
    'dt_date_filters',
    'dt_badges',
    'dt_create'
)); ?>