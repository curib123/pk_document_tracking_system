<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= ui_escape($definition['label']) ?></h1>
        <p class="text-body-secondary mb-0">Manage records with server-rendered forms.</p>
    </div>
    <?php if ($can_add): ?>
        <a class="btn btn-primary" href="<?= site_url('web/catalog/' . $module . '/new') ?>">Add record</a>
    <?php endif; ?>
</div>
<form action="<?= site_url('web/catalog/' . $module) ?>" method="get" class="row g-2 align-items-end mb-3">
    <div class="col-12 col-md-6">
        <label for="catalog_search" class="form-label">Search</label>
        <input class="form-control" id="catalog_search" name="q" maxlength="150" value="<?= ui_escape($query['q'] ?? '') ?>" placeholder="Search records">
    </div>
    <div class="col-6 col-md-2">
        <label for="catalog_limit" class="form-label">Rows per page</label>
        <select class="form-select" id="catalog_limit" name="limit">
            <?php foreach ([10, 25, 50, 100] as $size): ?>
                <option value="<?= $size ?>" <?= (int) ($records['limit'] ?? 25) === $size ? 'selected' : '' ?>><?= $size ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-2"><button type="submit" class="btn btn-outline-primary w-100">Apply</button></div>
</form>
<div class="table-responsive bg-white border rounded">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <?php foreach ($definition['columns'] as $column): ?>
                    <th scope="col"><?= ui_escape(ucwords(str_replace('_', ' ', $column))) ?></th>
                <?php endforeach; ?>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($records['rows'] as $row): ?>
            <tr>
                <?php foreach ($definition['columns'] as $column): ?>
                    <?php
                        $value = $row[$column . '_label'] ?? ($row[$column] ?? '—');
                        if ($column === 'active') {
                            $value = (int) ($row['active'] ?? 0) ? 'Active' : 'Inactive';
                        }
                    ?>
                    <td><?= ui_escape(is_scalar($value) ? $value : '—') ?></td>
                <?php endforeach; ?>
                <td class="text-nowrap">
                    <?php if ($can_edit): ?>
                        <a class="btn btn-outline-primary btn-sm" href="<?= site_url('web/catalog/' . $module . '/edit/' . (int) $row['id']) ?>">Edit</a>
                    <?php endif; ?>
                    <?php if ($can_delete): ?>
                        <a class="btn btn-outline-danger btn-sm" href="<?= site_url('web/catalog/' . $module . '/delete/' . (int) $row['id']) ?>">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$records['rows']): ?>
            <tr><td colspan="<?= count($definition['columns']) + 1 ?>" class="text-center py-4 text-body-secondary">No records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$page = (int) ($records['page'] ?? 1);
$pages = (int) ($records['pages'] ?? 1);
$params = ['q' => $query['q'] ?? '', 'limit' => $records['limit'] ?? 25];
?>
<div class="d-flex justify-content-between align-items-center mt-3">
    <small class="text-body-secondary"><?= (int) ($records['total'] ?? 0) ?> matching records · Page <?= $page ?> of <?= $pages ?></small>
    <div class="btn-group">
        <a class="btn btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= site_url('web/catalog/' . $module) . '?' . http_build_query($params + ['page' => max(1, $page - 1)]) ?>">Previous</a>
        <a class="btn btn-outline-secondary <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= site_url('web/catalog/' . $module) . '?' . http_build_query($params + ['page' => min($pages, $page + 1)]) ?>">Next</a>
    </div>
</div>
