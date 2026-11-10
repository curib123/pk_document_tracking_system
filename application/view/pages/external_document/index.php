<?php
$table = $table ?? [];
require_once APPPATH . 'services/presentation/document_folder_tree.php';
$folderNodes = $folder_browser['tree']['nodes'] ?? [];
$dt_path = 'documents/external';
$app_base_path = parse_url(base_url(), PHP_URL_PATH);
if ($app_base_path === FALSE || $app_base_path === NULL) {
    throw new RuntimeException('Unable to build external document file paths.');
}
$file_route = static function ($route) use ($app_base_path) {
    return rtrim($app_base_path, '/') . '/' . ltrim($route, '/');
};
$dt_columns = [
    'title' => 'Document Title',
    'document_number' => 'Document No.',
    'subsidiary_name' => 'Subsidiary',
    'category_name' => 'Category',
    'status' => 'Status',
    'revision_level' => 'Revision Level'
];
$dt_rows = [];
foreach ($rows as $row) {
    $attachments = $row['attachments'] ?? [];
    $attachmentFields = [];
    $attachmentLinks = [];
    if ($can_preview || $can_download) {
        foreach ($attachments as $index => $attachment) {
            $attachmentFields[($index + 1) . '. ' . $attachment['original_name']] =
                strtoupper((string) pathinfo($attachment['original_name'], PATHINFO_EXTENSION)) . ' | ' .
                number_format((int) $attachment['size']) . ' bytes | ' . $attachment['created_at'] .
                (!empty($attachment['locked_at']) ? ' | Locked ' . $attachment['locked_at'] . ' by ' .
                    trim(($attachment['locked_first_name'] ?? '') . ' ' . ($attachment['locked_last_name'] ?? '')) : '');
            $attachmentLinks[] = [
                'url' => $can_download ? $file_route('files/download/' . (int) $attachment['id']) : '',
                'preview_url' => $can_preview && ($attachment['mime_type'] === 'application/pdf' || strpos((string) $attachment['mime_type'], 'image/') === 0)
                    ? $file_route('files/preview/' . (int) $attachment['id']) : '',
                'preview_type' => $attachment['mime_type'],
                'label' => $attachment['original_name']
            ];
        }
    }
    $display = [
        'kind' => 'record',
        'header' => [
            'title' => $row['title'],
            'status' => ucfirst($row['status']),
            'eyebrow' => 'EXTERNAL DOCUMENT',
            'number' => $row['document_number'] ?? ''
        ],
        'sections' => [
            [
                'key' => 'overview',
                'title' => 'Document Details',
                'fields' => [
                    'Document No.' => $row['document_number'] ?? '',
                    'Subsidiary' => $row['subsidiary_name'] ?? '',
                    'Category' => $row['category_name'] ?? 'Uncategorized',
                    'Status' => ucfirst($row['status']),
                    'Description' => $row['description'] ?? '',
                    'Revision Level' => $row['revision_level'] ?? '00',
                    'Created Date' => $row['created_at'],
                    'Updated' => $row['updated_at']
                ]
            ],
            [
                'key' => 'files',
                'title' => 'Current Attachments',
                'fields' => $attachmentFields,
                'links' => $attachmentLinks,
                'empty' => ($can_preview || $can_download)
                    ? 'No current attachments are available.'
                    : 'Attachment previews are not available with your current permissions.'
            ]
        ]
    ];
    $buttons = [['type' => 'view']];
    if ($can_download && $row['status'] === 'active' && !empty($row['attachments'])) {
        $buttons[] = [
            'type' => 'download',
            'url' => $file_route('files/download-external/' . (int) $row['id']),
            'label' => 'Download All',
            'same_origin' => TRUE
        ];
    }
    $hasLockedFile = count(array_filter($attachments, static fn($file) => !empty($file['locked_at']))) > 0;
    if ($can_direct && $row['status'] === 'active' && !$hasLockedFile) {
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
            'document_number' => $row['document_number'] ?? '',
            'subsidiary_name' => $row['subsidiary_name'] ?? '',
            'category_name' => $row['category_name'] ?? 'Uncategorized',
            'status' => ucfirst($row['status']),
            'revision_level' => $row['revision_level'] ?? '00'
        ],
        'record' => [
            'type' => 'external_revise',
            'external_document_id' => (int) $row['id'],
            'external_category_id' => (int) $row['category_id'],
            'title' => $row['title'],
            'external_document_number' => $row['document_number'] ?? '',
            'subsidiary_id' => (int) ($row['subsidiary_id'] ?? 0),
            'description' => $row['description'] ?? ''
        ],
        'display' => $display,
        'buttons' => $buttons,
        'record_path' => ['category_id' => (int) $row['category_id']],
        'folder_key' => Document_folder_tree::folder_key(
            ['category_id' => (int) $row['category_id']],
            $folderNodes,
            'external'
        )
    ];
}
$dt_q = $table['q'] ?? '';
$dt_filter = '';
$dt_page = $table['page'] ?? 1;
$dt_limit = $table['limit'] ?? 10;
$dt_total = $total ?? count($rows);
$dt_sort = $table['sort'] ?? 'updated_at';
$dt_dir = strtolower($table['dir'] ?? 'desc');
$dt_sortable = ['title', 'document_number', 'subsidiary_name', 'category_name', 'revision_level'];
$dt_date_filters = TRUE;
$dt_filters = [
    'status' => ['all' => 'All Statuses', 'active' => 'Active', 'disposed' => 'Disposed'],
    'category' => ['' => 'All Categories'] + array_column($external_category_options, 'name', 'id')
];
if (!empty($owner_options)) {
    $dt_filters['owner'] = ['' => 'All Owners'] + array_column($owner_options, 'name', 'id');
}
$dt_filter_values = [
    'status' => $table['status'] ?? 'active',
    'category' => !empty($table['category']) ? $table['category'] : '',
    'owner' => $table['owner'] ?? '',
    'period' => $table['period'] ?? 'all',
    'from' => $table['from'] ?? '',
    'to' => $table['to'] ?? ''
];
$dt_badges = ['status'];
$dt_create = $can_direct ? 'Add New External Document' : '';
$dt_extra_params = ['folder' => $folder_value, 'layout' => $table['layout']];
$dt_folder_browser = $folder_browser;
$dt_folder_domain = 'external';
$activeLayout = $table['layout'] === 'folder' ? 'folder' : 'table';
$layoutLink = static function ($target) use ($folder_params, $folder_value) {
    $params = array_merge($folder_params, [
        'folder' => $folder_value,
        'layout' => $target,
        'page' => 1
    ]);
    return site_url('documents/external') . '?' . http_build_query($params);
};
?>

<div class="document-layout-bar">
    <div class="document-layout-description">
        <strong><i class="fa-solid fa-folder-open" aria-hidden="true"></i> External Document Browser</strong>
        <small>Choose a layout to browse external document categories.</small>
    </div>
    <nav class="document-layout-switch" aria-label="Choose external document view">
        <a href="<?= html_escape($layoutLink('table')) ?>"
            class="document-layout-choice <?= $activeLayout === 'table' ? 'is-active' : '' ?>"
            aria-label="Switch to table view" <?= $activeLayout === 'table' ? 'aria-current="page"' : '' ?>>
            <span class="document-layout-choice-icon"><i class="fa-solid fa-table-list" aria-hidden="true"></i></span>
            <span class="document-layout-choice-copy">Table</span>
        </a>
        <a href="<?= html_escape($layoutLink('folder')) ?>"
            class="document-layout-choice <?= $activeLayout === 'folder' ? 'is-active' : '' ?>"
            aria-label="Switch to folder tree view" <?= $activeLayout === 'folder' ? 'aria-current="page"' : '' ?>>
            <span class="document-layout-choice-icon"><i class="fa-solid fa-folder-tree" aria-hidden="true"></i></span>
            <span class="document-layout-choice-copy">Folder</span>
        </a>
    </nav>
</div>
<div class="document-register-listing" data-doc-domain="external" data-layout="<?= html_escape($activeLayout) ?>">
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
    'dt_create',
    'dt_extra_params',
    'dt_folder_browser',
    'dt_folder_domain'
)); ?>
</div>

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
                            <div class="col-md-6">
                                <label class="form-label" for="externalDocumentNumber">Document No. <span class="optional-label">(optional)</span></label>
                                <input class="form-control" id="externalDocumentNumber" name="external_document_number"
                                    maxlength="150" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="externalSubsidiary">Subsidiary <span class="optional-label">(optional)</span></label>
                                <select class="form-select" id="externalSubsidiary" name="subsidiary_id"
                                    data-searchable autocomplete="off">
                                    <option value="">Select a subsidiary</option>
                                    <?php foreach ($subsidiary_options as $subsidiary): ?>
                                        <option value="<?= (int) $subsidiary['id'] ?>"><?= html_escape($subsidiary['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="externalDescription">Description</label>
                                <textarea class="form-control" id="externalDescription" name="description"
                                    maxlength="2000" rows="3" autocomplete="off"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="externalCategory">Category</label>
                                <select class="form-select" id="externalCategory" name="external_category_id" required
                                    data-searchable autocomplete="off">
                                    <?php foreach ($external_category_options as $category): ?>
                                        <option value="<?= (int) $category['id'] ?>" <?= (int) $category['id'] === 1 ? 'selected' : '' ?>>
                                            <?= html_escape($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="externalAttachment">Attach File</label>
                                <input class="form-control" id="externalAttachment" type="file" name="external_attachment[]"
                                    accept=".pdf,image/*" multiple required autocomplete="off">
                                <small class="form-text">Select up to 20 PDF or image files (15 MB each). Image formats are detected from the file content.</small>
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
