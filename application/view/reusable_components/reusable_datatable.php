<?php
// Centralized search → filters → table → pagination component.
// Inputs: dt_rows/cells/buttons, dt_columns, dt_filters, dt_path and metadata.
$dt_limit = max(1, (int) $dt_limit);
$dt_pages = max(1, (int) ceil($dt_total / $dt_limit));
$dt_page = max(1, min((int) $dt_page, $dt_pages));
$dt_filter_values = $dt_filter_values ?? ['status' => $dt_filter];
$dt_sort = $dt_sort ?? '';
$dt_dir = $dt_dir ?? 'asc';
$dt_sortable = $dt_sortable ?? [];
$dt_extra_params = $dt_extra_params ?? [];
$dt_link = function ($page, $sortOverride = NULL, $dirOverride = NULL) use (
    $dt_path, $dt_q, $dt_filter_values, $dt_limit, $dt_sort, $dt_dir,
    $dt_extra_params
) {
    $params = array_merge($dt_extra_params, ['q' => $dt_q], $dt_filter_values, [
        'page' => $page, 'limit' => $dt_limit,
        'sort' => $sortOverride ?? $dt_sort, 'dir' => $dirOverride ?? $dt_dir
    ]);
    return site_url($dt_path) . '?' . http_build_query($params);
};
$dt_label = $dt_total ? (($dt_page - 1) * $dt_limit + 1) . '–' . min($dt_page * $dt_limit, $dt_total) : '0';
?>
<div class="workspace-card">
    <div class="table-toolbar">
        <form id="tableFilter" class="table-search" method="get" action="<?= site_url($dt_path) ?>">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input class="form-control" name="q" aria-label="Search records" placeholder="Search records..." value="<?= html_escape($dt_q) ?>">
            <input type="hidden" name="page" value="1">
            <input type="hidden" name="sort" value="<?= html_escape($dt_sort) ?>">
            <input type="hidden" name="dir" value="<?= html_escape($dt_dir) ?>">
            <?php foreach ($dt_extra_params as $key=>$value): ?>
            <input type="hidden" name="<?= html_escape($key) ?>" value="<?= html_escape($value) ?>">
            <?php endforeach; ?>
        </form>
        <?php if (!empty($dt_create)): ?>
            <button type="button" class="btn btn-primary js-edit" data-bs-toggle="modal" data-bs-target="#editModal"
                data-target="#editForm" data-record="{}" data-title="<?= html_escape($dt_create) ?>">
                <i class="fa-solid fa-plus me-1"></i> <?= html_escape($dt_create) ?>
            </button>
        <?php endif; ?>
    </div>
    <div class="table-filters">
        <?php foreach ($dt_filters as $field => $options): ?>
            <select form="tableFilter" class="form-select" name="<?= html_escape($field) ?>" aria-label="<?= html_escape(ucfirst($field)) ?>" data-auto-submit>
                <?php foreach ($options as $value => $label): ?>
                    <option value="<?= html_escape((string) $value) ?>" <?= (string) ($dt_filter_values[$field] ?? '') === (string) $value ? 'selected' : '' ?>><?= html_escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endforeach; ?>
        <button class="btn btn-light" type="submit" form="tableFilter"><i class="fa-solid fa-filter me-1"></i> Apply</button>
        <?php if ($dt_q !== '' || count(array_filter($dt_filter_values, 'strlen'))): ?>
            <a class="btn btn-light" href="<?= site_url($dt_path).($dt_extra_params?'?'.http_build_query($dt_extra_params):'') ?>">Clear</a>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr>
                <?php foreach ($dt_columns as $key => $label): ?>
                    <th scope="col" <?= in_array($key, $dt_sortable, TRUE) && $dt_sort === $key
                        ? 'aria-sort="'.($dt_dir === 'desc' ? 'descending' : 'ascending').'"' : '' ?>>
                        <?php if (in_array($key, $dt_sortable, TRUE)): ?>
                            <?php $nextDir = ($dt_sort === $key && $dt_dir === 'asc') ? 'desc' : 'asc'; ?>
                            <a class="table-sort<?= $dt_sort === $key ? ' is-sorted' : '' ?>"
                               href="<?= html_escape($dt_link(1, $key, $nextDir)) ?>"
                               aria-label="Sort by <?= html_escape($label) ?>">
                                <?= html_escape($label) ?>
                                <i class="fa-solid <?= $dt_sort === $key
                                    ? ($dt_dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort' ?>"
                                   aria-hidden="true"></i>
                            </a>
                        <?php else: ?><?= html_escape($label) ?><?php endif; ?>
                    </th>
                <?php endforeach; ?>
                <th scope="col" class="text-end">Actions</th>
            </tr></thead>
            <tbody>
                <?php if (!$dt_rows): ?>
                    <tr><td colspan="<?= count($dt_columns) + 1 ?>">
                        <div class="empty-state" role="status">
                            <i class="fa-regular fa-folder-open d-block fs-4 mb-2" aria-hidden="true"></i>
                            <strong><?= $dt_q !== '' || count(array_filter($dt_filter_values, 'strlen'))
                                ? 'No matching records' : 'No records yet' ?></strong>
                            <span class="empty-hint"><?= $dt_q !== '' || count(array_filter($dt_filter_values, 'strlen'))
                                ? 'Try another search or clear the filters above.'
                                : 'Records will appear here when they are added.' ?></span>
                        </div>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($dt_rows as $dt_row): ?>
                    <?php $rowCanView=!empty($dt_row['display']);
                          $rowTitle=(string)($dt_row['cells']['title'] ??
                              $dt_row['cells']['name'] ?? $dt_row['cells']['reference'] ?? 'View Details'); ?>
                    <tr <?= $rowCanView ?
                        'data-row-view="'.html_escape(json_encode($dt_row['display'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)).'"'.
                        ' data-row-title="'.html_escape($rowTitle).'"' : '' ?>>
                        <?php foreach ($dt_columns as $key => $label): ?>
                            <td <?= $rowCanView ? 'class="table-cell-view"'.($key === array_key_first($dt_columns)
                                ? ' tabindex="0" role="button" aria-label="View '.html_escape($rowTitle).' details"' : '') : '' ?>>
                                <?php $value = $dt_row['cells'][$key] ?? ''; ?>
                                <?php if (in_array($key, $dt_badges ?? [], TRUE)): ?>
                                    <span class="badge-status status-<?= html_escape(preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) $value))) ?>"><?= html_escape((string) $value === '0' ? 'Inactive' : ((string) $value === '1' ? 'Active' : ucwords(str_replace('_', ' ', (string) $value)))) ?></span>
                                <?php else: ?><?= html_escape((string) ($value === '' || $value === NULL ? '—' : $value)) ?><?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <div class="table-actions">
                            <?php foreach ($dt_row['buttons'] ?? [] as $button): ?>
                                <?php if ($button['type'] === 'view'): ?>
                                    <button type="button" class="btn-icon js-view" title="View" aria-label="View record" data-bs-toggle="modal" data-bs-target="#viewModal"
                                        data-display="<?= html_escape(json_encode($dt_row['display'])) ?>" data-title="<?= html_escape($button['label'] ?? 'View Details') ?>"><i class="fa-regular fa-eye"></i></button>
                                <?php elseif ($button['type'] === 'workflow'): ?>
                                    <a class="btn btn-outline-primary btn-sm js-workflow-jump"
                                       href="#wf-<?= (int) $dt_row['id'] ?>"
                                       data-bs-toggle="collapse"
                                       data-bs-target="#wf-<?= (int) $dt_row['id'] ?>"
                                       data-workflow-target="wf-<?= (int) $dt_row['id'] ?>"
                                       aria-controls="wf-<?= (int) $dt_row['id'] ?>"
                                       aria-expanded="false">
                                       <i class="fa-solid fa-route me-1" aria-hidden="true"></i> Step Actions
                                    </a>
                                <?php elseif ($button['type'] === 'edit'): ?>
                                    <button type="button" class="btn-icon js-edit" title="Edit" aria-label="Edit record" data-bs-toggle="modal" data-bs-target="#editModal" data-target="#editForm"
                                        data-record="<?= html_escape(json_encode($dt_row['record'])) ?>" data-title="Edit Record"><i class="fa-solid fa-pen"></i></button>
                                <?php elseif ($button['type'] === 'grants'): ?>
                                    <button type="button" class="btn-icon" title="Manage Access" aria-label="Manage Access"
                                        data-bs-toggle="modal"
                                        data-bs-target="#grantModal-<?= (int) $dt_row['id'] ?>">
                                        <i class="fa-solid fa-user-shield"></i></button>
                                <?php elseif ($button['type'] === 'history'): ?>
                                    <button type="button" class="btn-icon js-file-history" title="File History" aria-label="File History"
                                        data-bs-toggle="modal" data-bs-target="#fileHistoryModal"
                                        data-files="<?= html_escape(json_encode($button['files'])) ?>">
                                        <i class="fa-solid fa-clock-rotate-left"></i></button>
                                <?php elseif ($button['type'] === 'upload'): ?>
                                    <button type="button" class="btn-icon js-upload" title="Upload File" aria-label="Upload File"
                                        data-bs-toggle="modal" data-bs-target="#uploadModal"
                                        data-document-id="<?= (int) $dt_row['id'] ?>"
                                        data-document-name="<?= html_escape($dt_row['cells']['title'] ?? '') ?>"><i class="fa-solid fa-cloud-arrow-up"></i></button>
                                <?php elseif ($button['type'] === 'review'): ?>
                                    <a class="btn-icon" title="Review Controlled File" aria-label="Review Controlled File"
                                       target="_blank" rel="noopener"
                                       href="<?= site_url($button['url']) ?>"><i class="fa-solid fa-file-circle-check"></i></a>
                                <?php elseif ($button['type'] === 'download'): ?>
                                    <a class="btn-icon" title="Download File" aria-label="Download File" href="<?= site_url($button['url']) ?>"><i class="fa-solid fa-download"></i></a>
                                <?php elseif ($button['type'] === 'action'): ?>
                                    <button type="button" class="btn-icon js-action" title="<?= html_escape($button['label']) ?>" aria-label="<?= html_escape($button['label']) ?>"
                                        data-bs-toggle="modal" data-bs-target="#actionModal" data-url="<?= site_url($button['url']) ?>"
                                        data-id="<?= (int) $dt_row['id'] ?>" data-decision="<?= html_escape($button['decision'] ?? '') ?>"
                                        data-disposal="<?= !empty($button['disposal'])?'yes':'no' ?>"
                                        data-title="<?= html_escape($button['label']) ?>" data-description="<?= html_escape($button['description'] ?? '') ?>"><i class="<?= html_escape($button['icon'] ?? 'fa-solid fa-check') ?>"></i></button>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <small>Showing <?= html_escape($dt_label) ?> of <?= (int) $dt_total ?> records</small>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <label class="text-secondary small" for="rowLimit">Rows per page</label>
            <select class="form-select form-select-sm" id="rowLimit" name="limit" form="tableFilter" style="width:79px" data-auto-submit>
                <?php foreach ([10,25,50,100] as $n): ?><option value="<?= $n ?>" <?= $dt_limit === $n ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?>
            </select>
            <?php if ($dt_page > 1): ?>
                 <a class="btn btn-light btn-sm" href="<?= html_escape($dt_link($dt_page - 1)) ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
             <?php else: ?>
                 <span class="btn btn-light btn-sm disabled" aria-disabled="true" aria-label="Previous page unavailable"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></span>
             <?php endif; ?>
             <span class="small text-secondary" aria-live="polite">Page <?= $dt_page ?> of <?= $dt_pages ?></span>
             <?php if ($dt_page < $dt_pages): ?>
                 <a class="btn btn-light btn-sm" href="<?= html_escape($dt_link($dt_page + 1)) ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
             <?php else: ?>
                 <span class="btn btn-light btn-sm disabled" aria-disabled="true" aria-label="Next page unavailable"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></span>
             <?php endif; ?>
        </div>
    </div>
</div>
