<?php
/**
 * Central server-rendered data table. Controllers supply permission-scoped
 * records from Read_service -> Read_model. Browser JS never queries the DB.
 */
$isCatalog = ($tableKind ?? 'records') === 'catalog';
$tableId = 'pk-table-' . preg_replace('/[^a-z0-9_-]/i', '-', (string) $module);
$tableRoute = ($isCatalog ? 'web/catalog/' : 'web/records/') . $module;
$tableUrl = site_url($tableRoute);
$q = is_string($query['q'] ?? null) ? trim($query['q']) : '';
$columns = $definition['columns'];
$sort = in_array(($query['sort'] ?? ''), $columns, true) ? $query['sort'] : $columns[0];
$direction = ($query['direction'] ?? '') === 'asc' ? 'asc' : 'desc';
$page = max(1, (int) ($records['page'] ?? 1));
$pages = max(1, (int) ($records['pages'] ?? 1));
$limit = (int) ($records['limit'] ?? 25);
$total = max(0, (int) ($records['total'] ?? 0));
$status = is_string($query['status'] ?? null) ? $query['status'] : '';
$active = in_array(($query['active'] ?? ''), ['0', '1'], true) ? $query['active'] : '';
$requestTypeFiltering = in_array($module, ['requests', 'my_requests', 'my_tasks'], true);
$requestType = $requestTypeFiltering && is_string($query['type'] ?? null) ? $query['type'] : '';
$baseQuery = ['q'=>$q,'sort'=>$sort,'direction'=>$direction,'status'=>$status,'active'=>$active,'type'=>$requestType,'limit'=>$limit];
$linkTo = static fn(array $change): string => $tableUrl . '?' . http_build_query(array_replace($baseQuery, $change));
$hasActions = (!$isCatalog && $module !== 'sequences') || !empty($can_edit) || !empty($can_delete);
$filterCount = $isCatalog ? 1 : (!empty($status_options) || $requestTypeFiltering ? 1 : 0);
?>
<section class="pk-data-panel" aria-label="<?= ui_escape($definition['label']) ?> data table" id="<?= ui_escape($tableId) ?>">
    <form method="get" action="<?= ui_escape($tableUrl) ?>" class="pk-data-top" role="search" aria-label="Search and filter records">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-7 col-xl-6">
                <label class="form-label" for="<?= ui_escape($tableId . '-search') ?>">Search records</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><?= pk_web_icon('search') ?></span>
                    <input class="form-control" type="search" id="<?= ui_escape($tableId . '-search') ?>"
                           name="q" maxlength="150" value="<?= ui_escape($q) ?>"
                           placeholder="Search by name, code or reference">
                </div>
            </div>
            <div class="col-6 col-md-auto">
                <button type="submit" class="btn btn-primary w-100">Search</button>
            </div>
            <div class="col-6 col-md-auto">
                <a class="btn btn-outline-secondary w-100" href="<?= ui_escape($tableUrl) ?>"><?= pk_web_icon('reset') ?> Reset</a>
            </div>
        </div>
        <?php if ($filterCount): ?>
            <div class="pk-filter-row">
                <div class="pk-filter-label mb-2"><?= pk_web_icon('filter') ?> Filter results</div>
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-sm-7 col-lg-4">
                        <label class="form-label" for="<?= ui_escape($tableId . '-status') ?>">Record status</label>
                        <?php
                        $selectId = $tableId . '-status';
                        $selectName = $isCatalog ? 'active' : 'status';
                        $selectLabel = 'Status';
                        $selectValue = $isCatalog ? $active : $status;
                        $selectOptions = $isCatalog
                            ? [
                                ['value'=>'1','label'=>'Active'],
                                ['value'=>'0','label'=>'Inactive']
                              ]
                            : array_map(static fn(string $s): array =>
                                ['value'=>$s,'label'=>ucwords(str_replace('_',' ',$s))],
                                $status_options ?? []
                              );
                        $selectRequired = false;
                        $selectError = '';
                        $selectSearchAction = '';
                        $selectSearchTerm = '';
                        $selectAutofocus = false;
                        $selectMore = false;
                        $selectPlaceholder = 'All statuses';
                        require __DIR__ . '/searchable_select.php';
                        ?>
                    </div>
                    <?php if ($requestTypeFiltering): ?>
                        <div class="col-12 col-sm-7 col-lg-4">
                            <label class="form-label" for="<?= ui_escape($tableId . '-type') ?>">Request type</label>
                            <?php
                            $selectId = $tableId . '-type';
                            $selectName = 'type';
                            $selectLabel = 'Request type';
                            $selectValue = $requestType;
                            $selectOptions = array_map(
                                static fn(string $item): array => [
                                    'value' => $item,
                                    'label' => ucwords(str_replace('_', ' ', $item)),
                                ],
                                Request_service::TYPES
                            );
                            $selectRequired = false;
                            $selectError = '';
                            $selectSearchAction = '';
                            $selectSearchTerm = '';
                            $selectAutofocus = false;
                            $selectMore = false;
                            $selectPlaceholder = 'All request types';
                            require __DIR__ . '/searchable_select.php';
                            ?>
                        </div>
                    <?php endif; ?>
                    <div class="col-6 col-sm-auto">
                        <button type="submit" class="btn btn-outline-primary w-100">Apply filters</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <input type="hidden" name="sort" value="<?= ui_escape($sort) ?>">
        <input type="hidden" name="direction" value="<?= ui_escape($direction) ?>">
        <input type="hidden" name="limit" value="<?= $limit ?>">
    </form>

    <div class="pk-table-wrap">
        <table class="table table-hover align-middle pk-data-table mb-0">
            <caption class="visually-hidden"><?= ui_escape($definition['label']) ?>, <?= $total ?> matching records</caption>
            <thead>
                <tr>
                    <?php foreach ($columns as $column): ?>
                        <?php
                        $nextDirection = $sort === $column && $direction === 'asc' ? 'desc' : 'asc';
                        $heading = ucwords(str_replace('_', ' ', $column));
                        ?>
                        <th scope="col" aria-sort="<?= $sort === $column ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' ?>">
                            <a href="<?= ui_escape($linkTo(['sort'=>$column,'direction'=>$nextDirection,'page'=>1])) ?>"
                               class="text-decoration-none text-reset"
                               aria-label="Sort by <?= ui_escape($heading) ?>"><?= ui_escape($heading) ?> <span class="text-body-tertiary" aria-hidden="true">↕</span></a>
                        </th>
                    <?php endforeach; ?>
                    <?php if ($hasActions): ?><th scope="col">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records['rows'] as $row): ?>
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <?php
                            $value = $row[$column . '_label'] ?? ($row[$column] ?? '—');
                            if ($column === 'active') {
                                $value = !empty($row['active']) ? 'Active' : 'Inactive';
                            } elseif ($column === 'require_password_change') {
                                $value = !empty($row[$column]) ? 'Required' : 'Complete';
                            }
                            $display = is_scalar($value) ? (string) $value : '—';
                            $isStatus = in_array($column, ['active','status','recipient_status'], true);
                            $state = strtolower(str_replace(' ', '_', $display));
                            ?>
                            <td>
                                <?php if ($isStatus): ?>
                                    <span class="pk-status" data-state="<?= ui_escape($state) ?>"><?= ui_escape(ucwords(str_replace('_',' ',$display))) ?></span>
                                <?php else: ?>
                                    <?= ui_escape(in_array($column, ['type','disposal_action'], true) ? ucwords(str_replace('_', ' ', $display)) : $display) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <?php if ($hasActions): ?>
                            <td>
                                <div class="d-flex gap-1">
                                    <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('web/records/' . $module . '/' . (int) $row['id']) ?>"
                                       aria-label="View record details"><?= pk_web_icon('view') ?> <span>View</span></a>
                                    <?php if ($isCatalog && !empty($can_edit)): ?>
                                        <a class="btn btn-outline-primary btn-sm" href="<?= site_url('web/catalog/' . $module . '/edit/' . (int) $row['id']) ?>"
                                           aria-label="Edit record"><?= pk_web_icon('edit') ?> Edit</a>
                                    <?php endif; ?>
                                    <?php if ($isCatalog && !empty($can_delete)): ?>
                                        <a class="btn btn-outline-danger btn-sm" href="<?= site_url('web/catalog/' . $module . '/delete/' . (int) $row['id']) ?>"
                                           aria-label="Confirm deletion of record"><?= pk_web_icon('delete') ?> Delete</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($records['rows'])): ?>
                    <tr>
                        <td class="py-5 text-center text-body-secondary" colspan="<?= count($columns) + ($hasActions ? 1 : 0) ?>">
                            <?= pk_web_icon('search') ?> No matching records.
                            <?php if ($q !== '' || $status !== '' || $active !== '' || $requestType !== ''): ?>
                                <a href="<?= ui_escape($tableUrl) ?>">Clear filters</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pk-data-bottom">
        <p class="pk-data-meta mb-0" role="status">
            <?= $total ?> <?= $total === 1 ? 'record' : 'records' ?> · Page <?= $page ?> of <?= $pages ?>
        </p>
        <div class="d-flex align-items-end flex-wrap gap-3">
            <form method="get" action="<?= ui_escape($tableUrl) ?>" class="d-flex gap-2 align-items-end">
                <div>
                    <label class="form-label small mb-1" for="<?= ui_escape($tableId . '-limit') ?>">Rows per page</label>
                    <select class="form-select form-select-sm" name="limit" id="<?= ui_escape($tableId . '-limit') ?>">
                        <?php foreach ([10,25,50,100] as $size): ?>
                            <option value="<?= $size ?>" <?= $limit === $size ? 'selected' : '' ?>><?= $size ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php foreach (['q','status','active','type','sort','direction'] as $name): ?>
                    <input type="hidden" name="<?= $name ?>" value="<?= ui_escape($baseQuery[$name]) ?>">
                <?php endforeach; ?>
                <button type="submit" class="btn btn-outline-secondary btn-sm">Apply</button>
            </form>

            <nav class="btn-group align-self-end" aria-label="Table pagination">
                <?php if ($page > 1): ?>
                    <a rel="prev" class="btn btn-outline-secondary btn-sm" href="<?= ui_escape($linkTo(['page'=>$page - 1])) ?>">Previous</a>
                <?php else: ?>
                    <span class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true">Previous</span>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                    <a rel="next" class="btn btn-outline-secondary btn-sm" href="<?= ui_escape($linkTo(['page'=>$page + 1])) ?>">Next</a>
                <?php else: ?>
                    <span class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true">Next</span>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</section>
