<?php
declare(strict_types=1);

namespace Pk\Core;

final class UiSchema
{
    public static function field(
        string $name,
        string $type = 'text',
        bool $required = true,
        ?string $lookup = null
    ): array {
        $displayName =
            $type === 'lookup' ||
            $type === 'upload' ||
            $lookup !== null
                ? preg_replace('/_id$/', '', $name)
                : $name;

        return [
            'name' => $name,
            'label' => ucwords(
                str_replace('_', ' ', $displayName)
            ),
            'type' => $type,
            'required' => $required,
            'lookup' => $lookup,
        ];
    }

    public static function modules(): array
    {
        $modules = [];

        $add = function (
            string $key,
            string $label,
            string $table,
            array $columns,
            array $fields = [],
            ?string $permission = null
        ) use (&$modules): void {
            $modules[$key] = [
                'key' => $key,
                'label' => $label,
                'table' => $table,
                'columns' => $columns,
                'fields' => $fields,
                'permission' =>
                    $permission ??
                    $key . '.view',
            ];
        };

        $field = static fn(...$args) =>
            self::field(...$args);

        $active = $field(
            'active',
            'checkbox',
            false
        );

        $add(
            'softcopy',
            'Softcopy documents',
            'softcopy_documents',
            [
                'document_number',
                'title',
                'status',
            ]
        );

        $add(
            'hardcopy',
            'Hardcopy documents',
            'hardcopy_documents',
            [
                'title',
                'sequence_number',
                'status',
            ]
        );

        foreach (
            [
                'requests' => 'Requests',
                'my_requests' => 'My requests',
                'my_tasks' => 'My tasks',
            ]
            as $key => $label
        ) {
            $add(
                $key,
                $label,
                'requests',
                [
                    'reference',
                    'type',
                    'status',
                    'created_at',
                ],
                [],
                'requests.view'
            );
        }

        $add(
            'transfers',
            'Hardcopy transfers',
            'transfers',
            [
                'document_copy_number',
                'status',
                'recipient_status',
                'created_at',
            ],
            [],
            'transfer.view'
        );

        $add(
            'access',
            'Access grants',
            'access_grants',
            [
                'domain',
                'status',
                'expires_at',
            ]
        );

        $add(
            'assignments',
            'Assigned documents',
            'assignments',
            [
                'active',
                'assigned_at',
            ],
            [],
            'assignment.view'
        );

        $add(
            'disposals',
            'Disposal records',
            'disposals',
            [
                'domain',
                'disposal_action',
                'disposed_at',
            ],
            [],
            'disposal.view'
        );

        $add(
            'files',
            'Files and attachments',
            'files',
            [
                'original_name',
                'purpose',
                'domain',
                'status',
                'size',
                'created_at',
            ]
        );

        $add(
            'workflows',
            'Workflow builder',
            'workflows',
            [
                'name',
                'request_type',
                'active',
            ],
            [
                $field('name'),
                $field(
                    'description',
                    'textarea',
                    false
                ),
            ]
        );

        $add(
            'users',
            'Users',
            'users',
            [
                'username',
                'first_name',
                'last_name',
                'position_title',
                'active',
                'require_password_change',
            ],
            [
                $field('username'),
                $field('first_name'),
                $field(
                    'middle_name',
                    'text',
                    false
                ),
                $field('last_name'),
                $field('position_title'),
                $field(
                    'role_id',
                    'lookup',
                    true,
                    'roles'
                ),
                $field(
                    'leader_id',
                    'lookup',
                    false,
                    'users'
                ),
                $active,
            ]
        );

        $add(
            'roles',
            'Roles',
            'roles',
            [
                'name',
                'active',
            ],
            [
                $field('name'),
                $active,
            ]
        );

        $add(
            'permissions',
            'Permissions',
            'permissions',
            [
                'name',
                'module_label',
                'action_label',
            ],
            [
                $field('name'),
                $field('module_key'),
                $field('module_label'),
                $field('action_key'),
                $field('action_label'),
                $field(
                    'description',
                    'textarea',
                    false
                ),
            ]
        );

        $add(
            'areas',
            'Areas',
            'areas',
            [
                'name',
                'active',
            ],
            [
                $field('name'),
                $active,
            ]
        );

        $add(
            'specifics',
            'Specifics',
            'specifics',
            [
                'name',
                'active',
            ],
            [
                $field('name'),
                $field(
                    'area_id',
                    'lookup',
                    true,
                    'areas'
                ),
                $active,
            ]
        );

        $add(
            'assets',
            'Assets',
            'assets',
            [
                'asset_number',
                'active',
            ],
            [
                $field('asset_number'),
                $field(
                    'specific_id',
                    'lookup',
                    true,
                    'specifics'
                ),
                $active,
            ]
        );

        $add(
            'locations',
            'Locations',
            'locations',
            [
                'name',
                'code',
                'active',
                'archive_date',
            ],
            [
                $field('name'),
                $field('code'),
                $field(
                    'specific_id',
                    'lookup',
                    true,
                    'specifics'
                ),
                $field(
                    'asset_id',
                    'lookup',
                    true,
                    'assets'
                ),
                $active,
                $field(
                    'archive_date',
                    'date',
                    false
                ),
            ]
        );

        $add(
            'categories',
            'Softcopy categories',
            'categories',
            [
                'name',
                'folder_name',
                'active',
            ],
            [
                $field('name'),
                $field('folder_name'),
                $field(
                    'description',
                    'textarea',
                    false
                ),
                $field(
                    'parent_id',
                    'lookup',
                    false,
                    'categories'
                ),
                $active,
            ]
        );

        $add(
            'notifications',
            'Notifications',
            'notifications',
            [
                'title',
                'message',
                'read_at',
                'created_at',
            ]
        );

        $add(
            'audit',
            'Audit log',
            'audit_logs',
            [
                'username',
                'module',
                'action',
                'created_at',
            ]
        );

        $add(
            'history',
            'Document status history',
            'status_history',
            [
                'domain',
                'previous_status',
                'new_status',
                'action',
                'created_at',
            ],
            [],
            'audit.view'
        );

        $add(
            'sequences',
            'Sequences',
            'sequences',
            [
                'sequence_key',
                'value',
            ]
        );

        $add(
            'settings',
            'System settings',
            'settings',
            [
                'setting_key',
                'value',
            ],
            [
                $field(
                    'value',
                    'json'
                ),
            ]
        );

        return $modules;
    }

    public static function documentFields(
        string $domain
    ): array {
        $field = static fn(...$args) =>
            self::field(...$args);

        if ($domain === 'softcopy') {
            return [
                $field(
                    'document_number',
                    'text',
                    false
                ),
                $field(
                    'series_number',
                    'text',
                    false
                ),
                $field('title'),
                $field(
                    'category_id',
                    'lookup',
                    true,
                    'categories'
                ),
                $field(
                    'file_id',
                    'upload'
                ),
                $field(
                    'effective_date',
                    'date'
                ),
                $field(
                    'page_number',
                    'number'
                ),
                $field(
                    'new_revision_level',
                    'text',
                    false
                ),
                $field(
                    'date_received',
                    'date',
                    false
                ),
                $field(
                    'date_released',
                    'date',
                    false
                ),
                $field(
                    'reason',
                    'textarea'
                ),
            ];
        }

        return [
            $field('title'),
            ...self::physicalFields(),
            $field(
                'holder_id',
                'lookup',
                true,
                'users'
            ),
            $field(
                'sequence_number',
                'text',
                false
            ),
            $field(
                'retention_enabled',
                'checkbox',
                false
            ),
            $field(
                'retention_start_date',
                'date',
                false
            ),
            $field(
                'retention_end_date',
                'date',
                false
            ),
            $field(
                'reason',
                'textarea'
            ),
        ];
    }

    public static function physicalFields(): array
    {
        $field = static fn(...$args) =>
            self::field(...$args);

        return [
            $field(
                'area_id',
                'lookup',
                true,
                'areas'
            ),
            $field(
                'specific_id',
                'lookup',
                true,
                'specifics'
            ),
            $field(
                'asset_id',
                'lookup',
                true,
                'assets'
            ),
            $field(
                'location_id',
                'lookup',
                true,
                'locations'
            ),
        ];
    }

    public static function requestFields(): array
    {
        $field = static fn(...$args) =>
            self::field(...$args);

        $reason = $field(
            'reason',
            'textarea'
        );

        return [
            'softcopy_create' =>
                self::documentFields(
                    'softcopy'
                ),
            'softcopy_revise' =>
                self::documentFields(
                    'softcopy'
                ),
            'softcopy_cancel' => [
                $reason,
            ],
            'hardcopy_create' =>
                self::documentFields(
                    'hardcopy'
                ),
            'hardcopy_update' =>
                self::documentFields(
                    'hardcopy'
                ),
            'transfer' => [
                ...self::physicalFields(),
                $field(
                    'recipient_id',
                    'lookup',
                    true,
                    'users'
                ),
                $field(
                    'document_copy_number'
                ),
                $field(
                    'sequence_number',
                    'text',
                    false
                ),
                $reason,
            ],
            'assignment' => [
                $field(
                    'user_id',
                    'lookup',
                    true,
                    'users'
                ),
                $reason,
            ],
            'access' => [
                $field(
                    'expiration_date',
                    'date'
                ),
                $reason,
            ],
            'disposal' => [
                $field(
                    'disposal_action',
                    'disposal_action'
                ),
                $reason,
            ],
        ];
    }
}
