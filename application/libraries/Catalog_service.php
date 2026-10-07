<?php
declare(strict_types=1);

use Pk\Core\{Context, Problem, Rules, Seed};

class Catalog_service
{
    // Master-data rules diri; each module has its own small normalizer para easy i-follow.
    public const TABLES = [
        'users',
        'roles',
        'permissions',
        'areas',
        'specifics',
        'assets',
        'locations',
        'categories',
        'settings',
    ];

    private Context $ctx;

    public function __construct(Context|array|null $options = null)
    {
        $this->ctx = Context::fromOptions($options);
    }

    private function model(): Catalog_model
    {
        return $this->ctx->model(\Catalog_model::class);
    }

    public function save(
        string $module,
        array $input
    ): array {
        if (!in_array($module, self::TABLES, true)) {
            throw new Problem(
                'Unsupported module.',
                404
            );
        }

        $id = Rules::id(
            $input,
            'id',
            false
        );

        $this->ctx->require(
            $module . '.' . ($id ? 'edit' : 'add')
        );

        $before = $id
            ? $this->model()->lock(
                $module,
                $id,
                Rules::id($input, 'version')
            )
            : null;

        [$data, $extra] = $this->normalizeModule(
            $module,
            $input,
            $id,
            $before
        );

        if ($id) {
            $this->model()->update(
                $module,
                $id,
                $data
            );
        } else {
            $id = $this->model()->insert(
                $module,
                $data
            );
        }

        $this->ctx->audit(
            $module,
            $before ? 'updated' : 'created',
            $id,
            $before,
            $data
        );

        return array_merge(
            ['id' => $id],
            $extra
        );
    }

    private function normalizeModule(
        string $module,
        array $input,
        ?int $id,
        ?array $before
    ): array {
        return match ($module) {
            'users' => $this->normalizeUser(
                $input,
                $id,
                $before
            ),
            'roles' => [
                $this->normalizeRole(
                    $input,
                    $id
                ),
                [],
            ],
            'permissions' => [
                $this->normalizePermission(
                    $input,
                    $before
                ),
                [],
            ],
            'settings' => [
                $this->normalizeSetting(
                    $input,
                    $id,
                    $before
                ),
                [],
            ],
            default => [
                $this->normalizeCatalogItem(
                    $module,
                    $input,
                    $id,
                    $before
                ),
                [],
            ],
        };
    }

    private function normalizeUser(
        array $input,
        ?int $id,
        ?array $before
    ): array {
        $data = [
            'username' => Rules::text(
                $input,
                'username',
                80
            ),
            'first_name' => Rules::text(
                $input,
                'first_name',
                100
            ),
            'middle_name' => Rules::text(
                $input,
                'middle_name',
                100,
                false
            ),
            'last_name' => Rules::text(
                $input,
                'last_name',
                100
            ),
            'position_title' => Rules::text(
                $input,
                'position_title',
                150
            ),
            'role_id' => Rules::id(
                $input,
                'role_id'
            ),
            'leader_id' => Rules::id(
                $input,
                'leader_id',
                false
            ),
            'active' => Rules::boolean(
                $input['active'] ?? 1
            ),
        ];

        if (
            !preg_match(
                '/^[a-zA-Z0-9_.-]{3,80}$/',
                $data['username']
            )
        ) {
            throw new Problem(
                'Username must be 3–80 letters, numbers, dots, dashes or underscores.'
            );
        }

        $this->ctx->active(
            'roles',
            $data['role_id']
        );

        $this->hierarchy(
            'users',
            $id,
            $data['leader_id'],
            'leader_id'
        );

        if ($id === $this->ctx->id()) {
            $selfChanged =
                !$data['active']
                || $data['role_id'] !== (int) $before['role_id'];

            if ($selfChanged) {
                throw new Problem(
                    'You cannot deactivate yourself or change your own role.'
                );
            }
        }

        $roleChanged =
            $id
            && $data['role_id'] !== (int) $before['role_id'];

        if ($id && (!$data['active'] || $roleChanged)) {
            // Ari ta mag-protect sa last admin para dili ma-lock out ang system.
            $this->ensureAdministrator(
                $id,
                null
            );
        }

        $extra = [];

        if (!$id) {
            $password = bin2hex(
                random_bytes(10)
            );

            $data['password_hash'] =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            $data['require_password_change'] = 1;

            $extra = [
                'initial_password' => $password,
                'warning' => 'Copy this password now. It is shown once and is never stored as plaintext.',
            ];
        } elseif ($roleChanged || !$data['active']) {
            $data['session_version'] =
                (int) $before['session_version'] + 1;
        }

        return [$data, $extra];
    }

    private function normalizeRole(
        array $input,
        ?int $id
    ): array {
        $data = [
            'name' => Rules::text(
                $input,
                'name',
                120
            ),
            'active' => Rules::boolean(
                $input['active'] ?? 1
            ),
        ];

        if ($id && !$data['active']) {
            $this->ensureAdministrator(
                null,
                $id
            );
        }

        return $data;
    }

    private function normalizePermission(
        array $input,
        ?array $before
    ): array {
        $data = [];

        foreach (
            [
                'name',
                'module_key',
                'module_label',
                'action_key',
                'action_label',
            ]
            as $field
        ) {
            $limit = in_array(
                $field,
                ['module_key', 'action_key'],
                true
            )
                ? 60
                : 100;

            $data[$field] = Rules::text(
                $input,
                $field,
                $limit
            );
        }

        foreach (
            ['module_key', 'action_key']
            as $field
        ) {
            if (
                !preg_match(
                    '/^[a-z][a-z0-9_]*$/',
                    $data[$field]
                )
            ) {
                throw new Problem(
                    'Permission keys must be lowercase identifiers.'
                );
            }
        }

        if ($before) {
            $keyChanged =
                $data['module_key'] !== $before['module_key']
                || $data['action_key'] !== $before['action_key'];

            if ($keyChanged) {
                throw new Problem(
                    'Permission keys are immutable. Add a new permission instead.'
                );
            }
        }

        $data['description'] = Rules::text(
            $input,
            'description',
            4000,
            false
        );

        return $data;
    }

    private function normalizeSetting(
        array $input,
        ?int $id,
        ?array $before
    ): array {
        if (!$id || !$before) {
            throw new Problem(
                'Only defined system settings can be edited.'
            );
        }

        $value = Rules::json(
            $input['value'] ?? []
        );

        if ($before['setting_key'] === 'appearance') {
            Rules::choice(
                $value,
                'color_mode',
                ['light', 'dark', 'system']
            );

            Rules::choice(
                $value,
                'theme_scope',
                ['global', 'user']
            );

            Rules::text(
                $value,
                'color_theme',
                50
            );
        }

        return [
            'value' => Context::json($value),
        ];
    }

    private function normalizeCatalogItem(
        string $module,
        array $input,
        ?int $id,
        ?array $before
    ): array {
        $data = [
            'active' => Rules::boolean(
                $input['active'] ?? 1
            ),
        ];

        if ($module === 'assets') {
            $data['asset_number'] = Rules::text(
                $input,
                'asset_number',
                100
            );
        } else {
            $data['name'] = Rules::text(
                $input,
                'name',
                150
            );
        }

        if ($module === 'specifics') {
            $data['area_id'] = Rules::id(
                $input,
                'area_id'
            );

            $this->ctx->active(
                'areas',
                $data['area_id']
            );
        }

        if ($module === 'assets') {
            $data['specific_id'] = Rules::id(
                $input,
                'specific_id'
            );

            $this->ctx->active(
                'specifics',
                $data['specific_id']
            );
        }

        if ($module === 'locations') {
            $data = array_merge(
                $data,
                $this->normalizeLocation(
                    $input
                )
            );
        }

        if ($module === 'categories') {
            $data = array_merge(
                $data,
                $this->normalizeCategory(
                    $input,
                    $id
                )
            );
        }

        if ($id) {
            $this->guardReferences(
                $module,
                $id,
                $before,
                $data
            );
        }

        return $data;
    }

    private function normalizeLocation(
        array $input
    ): array {
        // Backward location chain: Location -> Asset -> Specific -> Area.
        // Missing levels are valid; deeper selections fill their known parents.
        $assetId = Rules::id(
            $input,
            'asset_id',
            false
        );

        $specificId = Rules::id(
            $input,
            'specific_id',
            false
        );

        $areaId = Rules::id(
            $input,
            'area_id',
            false
        );

        if ($assetId !== null) {
            $asset = $this->ctx->active(
                'assets',
                $assetId
            );

            $assetSpecificId =
                (int) $asset['specific_id'];

            if (
                $specificId !== null &&
                $specificId !== $assetSpecificId
            ) {
                throw new Problem(
                    'Selected Asset Number does not belong to the selected Specific.'
                );
            }

            $specificId = $assetSpecificId;
        }

        if ($specificId !== null) {
            $specific = $this->ctx->active(
                'specifics',
                $specificId
            );

            $specificAreaId =
                (int) $specific['area_id'];

            if (
                $areaId !== null &&
                $areaId !== $specificAreaId
            ) {
                throw new Problem(
                    'Selected Specific does not belong to the selected Area.'
                );
            }

            $areaId = $specificAreaId;
        }

        if ($areaId !== null) {
            $this->ctx->active(
                'areas',
                $areaId
            );
        }

        return [
            'area_id' => $areaId,
            'specific_id' => $specificId,
            'asset_id' => $assetId,
            'code' => Rules::text(
                $input,
                'code',
                100
            ),
            'archive_date' => Rules::date(
                $input,
                'archive_date',
                false
            ),
        ];
    }

    private function normalizeCategory(
        array $input,
        ?int $id
    ): array {
        $parentId = Rules::id(
            $input,
            'parent_id',
            false
        );

        $this->hierarchy(
            'categories',
            $id,
            $parentId,
            'parent_id'
        );

        $data = [
            'parent_id' => $parentId,
            'folder_name' => Rules::text(
                $input,
                'folder_name',
                150
            ),
            'description' => Rules::text(
                $input,
                'description',
                4000,
                false
            ),
        ];

        if (!$id) {
            $data['created_by'] =
                $this->ctx->id();
        }

        return $data;
    }

    public function resetPassword(array $input): array
    {
        $this->ctx->require('users.edit');

        $id = Rules::id($input);

        if ($id === $this->ctx->id()) {
            throw new Problem(
                'Use Change password for your own account.'
            );
        }

        $reason = Rules::text(
            $input,
            'reason',
            2000
        );

        $user = $this->model()->lock(
            'users',
            $id,
            Rules::id($input, 'version')
        );

        $password = bin2hex(
            random_bytes(10)
        );

        $this->model()->update(
            'users',
            $id,
            [
                'password_hash' =>
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    ),
                'require_password_change' => 1,
                'session_version' =>
                    (int) $user['session_version'] + 1,
            ]
        );

        $this->ctx->audit(
            'users',
            'password_reset',
            $id,
            null,
            null,
            $reason
        );

        return [
            'initial_password' => $password,
            'warning' => 'Show once. The user must change it at their next login.',
        ];
    }

    public function permissions(array $input): array
    {
        $this->ctx->require('roles.edit');

        $roleId = Rules::id($input);

        $before = $this->model()->lock(
            'roles',
            $roleId,
            Rules::id($input, 'version')
        );

        $permissionIds = array_values(
            array_unique(
                array_map(
                    'intval',
                    Rules::json(
                        $input['permission_ids'] ?? []
                    )
                )
            )
        );

        if (count($permissionIds) > 500) {
            throw new Problem(
                'Too many permissions.'
            );
        }

        foreach ($permissionIds as $permissionId) {
            $this->model()->row(
                'permissions',
                $permissionId
            );
        }

        $this->protectAdministratorPermissions(
            $roleId,
            $permissionIds
        );

        $oldPermissionIds = array_column(
            $this->model()->role_permission_ids(
                [$roleId]
            ),
            'permission_id'
        );

        $this->model()->clear_role_permissions(
            [$roleId]
        );

        foreach ($permissionIds as $permissionId) {
            $this->model()->insert(
                'role_permissions',
                [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]
            );
        }

        // Touch role version para stale role dialogs ma-detect gihapon.
        $this->model()->update(
            'roles',
            $roleId,
            ['name' => $before['name']]
        );

        $this->ctx->audit(
            'roles',
            'permissions_changed',
            $roleId,
            $oldPermissionIds,
            $permissionIds,
            Rules::text(
                $input,
                'reason',
                2000
            )
        );

        return [
            'message' => 'Permissions updated. Changes apply to the next request.',
        ];
    }

    private function protectAdministratorPermissions(
        int $roleId,
        array $permissionIds
    ): void {
        $adminPermission =
            $this->model()->permission_administrator([]);

        if (
            !in_array(
                (int) $adminPermission['id'],
                $permissionIds,
                true
            )
        ) {
            $this->ensureAdministrator(
                null,
                $roleId
            );
        }

        if (
            $roleId !==
            (int) $this->ctx->user['role_id']
        ) {
            return;
        }

        // Kani nga minimum set para naa gihapon recovery path ang current admin.
        $requiredCapabilities = [
            'roles.edit',
            'users.edit',
            'users.view',
            'roles.view',
            'permissions.view',
        ];

        foreach ($requiredCapabilities as $capability) {
            [$module, $action] = explode(
                '.',
                $capability
            );

            $permission =
                $this->model()->permission_by_key(
                    [$module, $action]
                );

            if (
                !in_array(
                    (int) $permission['id'],
                    $permissionIds,
                    true
                )
            ) {
                throw new Problem(
                    'Do not remove your own administrative recovery permissions.'
                );
            }
        }
    }

    public function delete(
        string $module,
        array $input
    ): array {
        $allowed =
            in_array(
                $module,
                self::TABLES,
                true
            )
            && $module !== 'settings';

        if (!$allowed) {
            throw new Problem(
                'This module does not allow deletion.',
                403
            );
        }

        $this->ctx->require(
            $module . '.delete'
        );

        $id = Rules::id($input);

        $row = $this->model()->lock(
            $module,
            $id,
            Rules::id($input, 'version')
        );

        $reason = Rules::text(
            $input,
            'reason',
            2000
        );

        if ($module === 'users') {
            return $this->deactivateUser(
                $id,
                $row,
                $reason
            );
        }

        if ($module === 'roles') {
            $this->prepareRoleDeletion($id);
        }

        if ($module === 'permissions') {
            $this->preparePermissionDeletion(
                $id,
                $row
            );
        }

        // FK guards keep referenced catalogue records safe; no cascade-delete shortcuts.
        $this->model()->delete_catalog_record(
            $module,
            [$id]
        );

        $this->ctx->audit(
            $module,
            'deleted',
            $id,
            $row,
            null,
            $reason
        );

        return [
            'message' => 'Unused catalogue record deleted. Audit history was retained.',
        ];
    }

    private function deactivateUser(
        int $id,
        array $row,
        string $reason
    ): array {
        if ($id === $this->ctx->id()) {
            throw new Problem(
                'You cannot deactivate yourself.'
            );
        }

        $this->ensureAdministrator(
            $id,
            null
        );

        $this->model()->update(
            'users',
            $id,
            [
                'active' => 0,
                'session_version' =>
                    (int) $row['session_version'] + 1,
            ]
        );

        $this->ctx->audit(
            'users',
            'deactivated',
            $id,
            $row,
            ['active' => 0],
            $reason
        );

        return [
            'message' => 'Account deactivated. Historical references and audit records were retained.',
        ];
    }

    private function prepareRoleDeletion(int $id): void
    {
        if ($this->model()->assigned_user([$id])) {
            throw new Problem(
                'This role is assigned to users. Reassign them or deactivate the role instead.'
            );
        }

        $this->model()->clear_role_permissions(
            [$id]
        );
    }

    private function preparePermissionDeletion(
        int $id,
        array $row
    ): void {
        $builtInActions =
            Seed::capabilities()[$row['module_key']]
            ?? [];

        if (
            in_array(
                $row['action_key'],
                $builtInActions,
                true
            )
        ) {
            throw new Problem(
                'Built-in capability definitions cannot be deleted. Change role assignments instead.'
            );
        }

        if ($this->model()->assigned_role([$id])) {
            throw new Problem(
                'This permission is assigned to a role. Remove its assignments first.'
            );
        }
    }

    private function hierarchy(
        string $table,
        ?int $id,
        ?int $parentId,
        string $parentColumn
    ): void {
        $seen = [];

        while ($parentId) {
            if (
                $parentId === $id
                || isset($seen[$parentId])
            ) {
                throw new Problem(
                    'This hierarchy would create a cycle.'
                );
            }

            $seen[$parentId] = true;

            $row = $this->ctx->active(
                $table,
                $parentId
            );

            $parentId = $row[$parentColumn]
                ? (int) $row[$parentColumn]
                : null;

            if (count($seen) > 100) {
                throw new Problem(
                    'Hierarchy is too deep.'
                );
            }
        }
    }

    private function ensureAdministrator(
        ?int $excludedUserId,
        ?int $excludedRoleId
    ): void {
        $administrators =
            $this->model()->active_administrators([]);

        foreach ($administrators as $administrator) {
            $userAllowed =
                $excludedUserId === null
                || (int) $administrator['id'] !== $excludedUserId;

            $roleAllowed =
                $excludedRoleId === null
                || (int) $administrator['role_id'] !== $excludedRoleId;

            if ($userAllowed && $roleAllowed) {
                return;
            }
        }

        throw new Problem(
            'At least one active permission administrator must remain.'
        );
    }

    private function guardReferences(
        string $module,
        int $id,
        array $before,
        array $after
    ): void {
        $references = match ($module) {
            'areas' => [
                ['specifics', 'area_id'],
            ],
            'specifics' => [
                ['assets', 'specific_id'],
                ['locations', 'specific_id'],
                ['hardcopy_documents', 'specific_id'],
            ],
            'assets' => [
                ['locations', 'asset_id'],
                ['hardcopy_documents', 'asset_id'],
            ],
            'locations' => [
                ['hardcopy_documents', 'location_id'],
            ],
            'categories' => [
                ['categories', 'parent_id'],
                ['softcopy_documents', 'category_id'],
            ],
            default => [],
        };

        $structuralKeys = array_intersect(
            array_keys($after),
            [
                'area_id',
                'specific_id',
                'asset_id',
                'parent_id',
            ]
        );

        $changed = !$after['active'];

        foreach ($structuralKeys as $key) {
            if (($before[$key] ?? null) != $after[$key]) {
                $changed = true;
                break;
            }
        }

        if (!$changed) {
            return;
        }

        foreach ($references as [$table, $column]) {
            $referenced =
                $this->model()->referenced_record(
                    $table,
                    $column,
                    [$id]
                );

            if ($referenced) {
                throw new Problem(
                    'This record is in use. Keep its hierarchy and active status; move dependent records through their proper workflow first.'
                );
            }
        }
    }
}
