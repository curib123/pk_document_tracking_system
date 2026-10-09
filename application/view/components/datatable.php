<?php
// Centralized search → filters → table → pagination component.
// Inputs: dt_rows/cells/buttons, dt_columns, dt_filters, dt_path and metadata.
$dt_pages = max(1, (int) ceil($dt_total / $dt_limit));
$dt_page = min($dt_page, $dt_pages);
$dt_link = function ($page) use ($dt_path, $dt_q, $dt_filter, $dt_limit) {
    return site_url($dt_path) . '?' . http_build_query(['q' => $dt_q, 'status' => $dt_filter, 'page' => $page, 'limit' => $dt_limit]);
};
$dt_label = $dt_total ? (($dt_page - 1) * $dt_limit + 1) . '–' . min($dt_page * $dt_limit, $dt_total) : '0';
?>
<div class="workspace-card">
    <div class="table-toolbar">
        <form id="tableFilter" class="table-search" method="get" action="<?= site_url($dt_path) ?>">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input class="form-control" name="q" aria-label="Search records" placeholder="Search records..." value="<?= html_escape($dt_q) ?>">
            <input type="hidden" name="page" value="<?= (int) $dt_page ?>">
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
                    <option value="<?= html_escape((string) $value) ?>" <?= (string) $dt_filter === (string) $value ? 'selected' : '' ?>><?= html_escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endforeach; ?>
        <button class="btn btn-light" type="submit" form="tableFilter"><i class="fa-solid fa-filter me-1"></i> Apply</button>
        <?php if ($dt_q !== '' || $dt_filter !== ''): ?><a class="btn btn-light" href="<?= site_url($dt_path) ?>">Clear</a><?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr>
                <?php foreach ($dt_columns as $label): ?><th scope="col"><?= html_escape($label) ?></th><?php endforeach; ?>
                <th scope="col" class="text-end">Actions</th>
            </tr></thead>
            <tbody>
                <?php if (!$dt_rows): ?>
                    <tr><td colspan="<?= count($dt_columns) + 1 ?>"><div class="empty-state"><i class="fa-regular fa-folder-open d-block fs-4 mb-2"></i>No records found.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($dt_rows as $dt_row): ?>
                    <tr>
                        <?php foreach ($dt_columns as $key => $label): ?>
                            <td>
                                <?php $value = $dt_row['cells'][$key] ?? ''; ?>
                                <?php if (in_array($key, $dt_badges ?? [], TRUE)): ?>
                                    <span class="badge-status status-<?= html_escape(strtolower((string) $value)) ?>"><?= html_escape((string) $value === '0' ? 'Inactive' : ucwords(str_replace('_', ' ', (string) $value))) ?></span>
                                <?php else: ?><?= html_escape((string) ($value === '' || $value === NULL ? '—' : $value)) ?><?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <div class="table-actions">
                            <?php foreach ($dt_row['buttons'] ?? [] as $button): ?>
                                <?php if ($button['type'] === 'view'): ?>
                                    <button type="button" class="btn-icon js-view" title="View" aria-label="View record" data-bs-toggle="modal" data-bs-target="#viewModal"
                                        data-display="<?= html_escape(json_encode($dt_row['display'])) ?>" data-title="<?= html_escape($button['label'] ?? 'View Details') ?>"><i class="fa-regular fa-eye"></i></button>
                                <?php elseif ($button['type'] === 'edit'): ?>
                                    <button type="button" class="btn-icon js-edit" title="Edit" aria-label="Edit record" data-bs-toggle="modal" data-bs-target="#editModal" data-target="#editForm"
                                        data-record="<?= html_escape(json_encode($dt_row['record'])) ?>" data-title="Edit Record"><i class="fa-solid fa-pen"></i></button>
                                <?php elseif ($button['type'] === 'upload'): ?>
                                    <button type="button" class="btn-icon js-upload" title="Upload File" aria-label="Upload File"
                                        data-bs-toggle="modal" data-bs-target="#uploadModal"
                                        data-document-id="<?= (int) $dt_row['id'] ?>"
                                        data-document-name="<?= html_escape($dt_row['cells']['title'] ?? '') ?>"><i class="fa-solid fa-cloud-arrow-up"></i></button>
                                <?php elseif ($button['type'] === 'download'): ?>
                                    <a class="btn-icon" title="Download File" aria-label="Download File" href="<?= site_url($button['url']) ?>"><i class="fa-solid fa-download"></i></a>
                                <?php elseif ($button['type'] === 'action'): ?>
                                    <button type="button" class="btn-icon js-action" title="<?= html_escape($button['label']) ?>" aria-label="<?= html_escape($button['label']) ?>"
                                        data-bs-toggle="modal" data-bs-target="#actionModal" data-url="<?= site_url($button['url']) ?>"
                                        data-id="<?= (int) $dt_row['id'] ?>" data-decision="<?= html_escape($button['decision'] ?? '') ?>"
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
            <a class="btn btn-light btn-sm <?= $dt_page <= 1 ? 'disabled' : '' ?>" href="<?= $dt_link(max(1, $dt_page - 1)) ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
            <span class="small text-secondary"><?= $dt_page ?> / <?= $dt_pages ?></span>
            <a class="btn btn-light btn-sm <?= $dt_page >= $dt_pages ? 'disabled' : '' ?>" href="<?= $dt_link(min($dt_pages, $dt_page + 1)) ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
        </div>
    </div>
</div>
