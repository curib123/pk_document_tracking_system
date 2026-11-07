<?php
// Shared Places screen: each master-branch module keeps its own index.php.
$permissions = $permissions ?? [];
$can = function ($action) use ($permissions, $cfg) {
    if (!empty($cfg['read_only']) && $action !== 'view')
        return FALSE;
    return isset($permissions['*']) || !empty($permissions[$cfg['table']][$action]);
};
$dt_columns = [];
$labels = [
    'name' => 'Name',
    'asset_number' => 'Asset Number',
    'sequence_number' => 'Sequence',
    'description' => 'Description',
    'folder_name' => 'Folder',
    'code_pattern' => 'Document No. Code Pattern',
    'category_type' => 'Category Type',
    'area_name' => 'Area',
    'specific_name' => 'Specific',
    'asset_name' => 'Asset',
    'parent_name' => 'Parent'
];
foreach ($cfg['fields'] as $field) {
    if (str_ends_with($field, '_id')) {
        $key = str_replace('_id', '_name', $field);
        if ($field === 'parent_id')
            $key = 'parent_name';
        if (isset($labels[$key]))
            $dt_columns[$key] = $labels[$key];
    } else {
        $dt_columns[$field] = $labels[$field] ?? ucwords(str_replace('_', ' ', $field));
    }
}
if ($cfg['active'])
    $dt_columns['active'] = 'Status';
$dt_rows = [];
foreach ($rows as $row) {
    $cells = [];
    foreach ($dt_columns as $field => $label) {
        $value = $row[$field] ?? '';
        $cells[$field] = $field === 'active' ? ($value ? 'active' : '0')
            : ($field === 'category_type'
                ? ($value === 'folder' ? 'Folder' : 'Document Number Pattern')
                : $value);
    }
    $display = [];
    foreach ($dt_columns as $field => $label)
        $display[$label] = $cells[$field];
    $record = [];
    foreach (array_merge(['id', 'version'], $cfg['fields'], ['active']) as $field) {
        if (array_key_exists($field, $row))
            $record[$field] = $row[$field];
    }
    // Area, Specific and Asset are simple reference records: use Edit, not View Details.
    $buttons = in_array($cfg['slug'], ['area', 'specific', 'asset'], TRUE)
        ? [] : [['type' => 'view']];
    if ($can('edit'))
        $buttons[] = ['type' => 'edit'];
    if ($cfg['active'] && $can('delete') && !empty($row['active'])) {
        $buttons[] = [
            'type' => 'action',
            'url' => 'places/' . $cfg['slug'] . '/deactivate',
            'label' => 'Deactivate',
            'description' => 'Deactivate this reference item without deleting historical links.',
            'icon' => 'fa-solid fa-ban'
        ];
    }
    $dt_rows[] = [
        'id' => $row['id'] ?? 0,
        'cells' => $cells,
        'record' => $record,
        'display' => in_array($cfg['slug'], ['area', 'specific', 'asset'], TRUE) ? [] : $display,
        'buttons' => $buttons
    ];
}
$dt_path = 'places/' . $cfg['slug'];
$dt_q = $table['q'];
$dt_filter = $table['status'];
$dt_page = $table['page'];
$dt_limit = $table['limit'];
$dt_total = $total;
$dt_filters = $cfg['active'] ? ['status' => ['' => 'All Statuses', '1' => 'Active', '0' => 'Inactive']] : [];
$parentSources = [
    'specific' => 'areas',
    'asset' => 'specifics',
    'location' => 'areas',
    'softcopy-categories' => 'categories',
    'external-categories' => 'external_categories'
];
if (isset($parentSources[$cfg['slug']])) {
    $dt_filters['parent'] = ['' => 'All Parent Folders'];
    foreach ($options[$parentSources[$cfg['slug']]] as $option)
        $dt_filters['parent'][$option['id']] = $option['name'];
}
$dt_filter_values = ['status' => $dt_filter, 'parent' => $table['parent'] ?: ''];
$dt_badges = ['active'];
$dt_create = $can('add') ? 'Add ' . $cfg['label'] : '';
$dt_sort = $table['sort'];
$dt_dir = strtolower($table['dir']);
$dt_sortable = array_values(array_intersect(
    array_keys($dt_columns),
    ['name', 'sequence_number', 'asset_number', 'folder_name', 'active']
));
?>

<nav class="tab-bar" aria-label="Place categories">
    <?php foreach ([
        'area' => 'Area',
        'specific' => 'Specific',
        'asset' => 'Asset',
        'location' => 'Location',
        'softcopy-categories' => 'Softcopy Categories',
        'external-categories' => 'External Categories',
        'subsidiary' => 'Subsidiary Setup'
    ] as $slug => $label):
        $perm = [
            'area' => 'areas',
            'specific' => 'specifics',
            'asset' => 'assets',
            'location' => 'locations',
            'softcopy-categories' => 'categories',
            'external-categories' => 'external_categories',
            'subsidiary' => 'subsidiaries'
        ][$slug];
        if (isset($permissions['*']) || !empty($permissions[$perm]['view'])): ?>
            <a href="<?= site_url('places/' . $slug) ?>" class="tab-link <?= $cfg['slug'] === $slug ? 'active' : '' ?>">
                <?= html_escape($label) ?></a>
        <?php endif; endforeach; ?>
</nav>
<?php $this->load->view('reusable_components/reusable_datatable', compact(
    'dt_path',
    'dt_q',
    'dt_filter',
    'dt_page',
    'dt_limit',
    'dt_total',
    'dt_filters',
    'dt_filter_values',
    'dt_badges',
    'dt_rows',
    'dt_columns',
    'dt_create',
    'dt_sort',
    'dt_dir',
    'dt_sortable'
)); ?>
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="editModal" tabindex="-1"
    aria-labelledby="editTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="editForm" method="post" action="<?= site_url('places/' . $cfg['slug'] . '/save') ?>"
                data-confirm="Save these changes?" <?= $cfg['slug'] === 'location' ? 'data-location-upsert' : '' ?> <?= $cfg['slug'] === 'softcopy-categories' ? 'data-softcopy-category-setup' : '' ?> autocomplete="off">
                <div class="modal-header">
                    <h2 class="modal-title fs-6" id="editTitle">Add Place</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <input type="hidden" name="id" value=""><input type="hidden" name="version" value="">
                        <?php if ($cfg['slug'] === 'location'): ?>
                            <?php $this->load->view('pages/places/location/modal_action/upsert', ['options' => $options]); ?>
                        <?php else: ?>
                            <?php foreach ($cfg['fields'] as $field):
                                $references = [
                                    'area_id' => 'areas',
                                    'specific_id' => 'specifics',
                                    'asset_id' => 'assets',
                                    'parent_id' => $cfg['slug'] === 'external-categories'
                                        ? 'external_categories'
                                        : 'categories'
                                ];
                                if (isset($references[$field])):
                                    $optName = $references[$field]; ?>
                                    <div class="col-md-6">
                                        <label class="form-label"
                                            for="f-<?= html_escape($field) ?>"><?= $field === 'parent_id' && $cfg['slug'] === 'softcopy-categories' ? 'Parent Folder (optional)' : html_escape(ucwords(str_replace('_', ' ', $field))) ?></label>
                                        <select class="form-select" id="f-<?= html_escape($field) ?>"
                                            name="<?= html_escape($field) ?>" data-searchable autocomplete="off">
                                            <option value="">No parent (root)</option>
                                            <?php foreach ($options[$optName] as $option): ?>
                                                <option value="<?= (int) $option['id'] ?>" <?= $field === 'parent_id' && $cfg['slug'] === 'softcopy-categories' ? 'data-category-parent-kind="' . html_escape($option['category_type'] ?? 'pattern') . '"' : '' ?>><?= html_escape($field === 'parent_id' && $cfg['slug'] === 'softcopy-categories' ? ($option['display_path'] ?? $option['name']) : $option['name']) ?><?= $field === 'parent_id' && $cfg['slug'] === 'softcopy-categories' ? html_escape(($option['category_type'] ?? 'pattern') === 'folder' ? ' (Folder)' : ' (Legacy pattern category)') : '' ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($field === 'parent_id' && $cfg['slug'] === 'softcopy-categories'): ?>
                                            <small class="form-text">Choose a Folder category to nest this category beneath. Folder categories can be nested; numbered categories create documents within the chosen folder.</small>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="col-md-6" <?= $field === 'code_pattern' && $cfg['slug'] === 'softcopy-categories' ? 'data-category-number-pattern' : '' ?>>
                                        <label class="form-label"
                                            for="f-<?= html_escape($field) ?>"><?= html_escape($labels[$field] ?? ucwords(str_replace('_', ' ', $field))) ?></label>
                                        <?php if ($field === 'category_type' && $cfg['slug'] === 'softcopy-categories'): ?>
                                            <select class="form-select" id="f-category_type" name="category_type" data-softcopy-category-type required autocomplete="off">
                                                <option value="pattern">Document Number Pattern</option>
                                                <option value="folder">Folder (parent / nested folder)</option>
                                            </select>
                                            <small class="form-text">Folder: organize nested categories without numbering. Document Number Pattern: generate document numbers within a folder or at root.</small>
                                        <?php elseif ($field === 'description'): ?>
                                            <textarea class="form-control" id="f-<?= html_escape($field) ?>"
                                                name="<?= html_escape($field) ?>" rows="2" autocomplete="off"></textarea>
                                        <?php else: ?>
                                            <input class="form-control" id="f-<?= html_escape($field) ?>"
                                                name="<?= html_escape($field) ?>" <?= $field === $cfg['key'] || $field === 'folder_name' || $field === 'code_pattern' ? 'required' : '' ?> maxlength="<?= $field === 'code_pattern' ? '100' : '150' ?>" autocomplete="off"
                                                <?= $field === 'code_pattern' ? 'placeholder="For example PPM-001"' : '' ?>>
                                            <?php if ($field === 'code_pattern'): ?>
                                                <small class="form-text">Enter the initial document number; only the numeric segment after the final dash increments, for example PPM-001, PPM-002, PPM-003. This is not required for Folder categories.</small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; endforeach; ?>
                        <?php endif; ?>
                        <?php if ($cfg['active']): ?>
                            <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox"
                                        name="active" value="1" checked autocomplete="off"> Active</label></div>
                        <?php endif; ?>
                    </div>
                </div>
                <input type="hidden" name="confirmed" value="no">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>"
                    value="<?= $this->security->get_csrf_hash() ?>">
                <div class="modal-footer"><button class="btn btn-light" type="button"
                        data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit">Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>