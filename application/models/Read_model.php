<?php
declare(strict_types=1);
use Pk\Core\{Context,Problem,Rules,UiSchema,WorkflowGraph};

/** Permission-scoped list/detail queries. All request values go through Query Builder. */
class Read_model extends Repository_model
{
    public function metadata(): array
    {
        $modules = array_filter(
            UiSchema::modules(),
            fn(array $module): bool => $this->ctx->can($module['permission'])
        );

        foreach ($modules as &$module) {
            unset($module['table']);
        }
        unset($module);

        return [
            'modules' => array_values($modules),
            'document_fields' => [
                'softcopy' => UiSchema::documentFields('softcopy'),
                'hardcopy' => UiSchema::documentFields('hardcopy'),
            ],
            'request_fields' => UiSchema::requestFields(),
            'request_types' => Request_service::TYPES,
            'workflow_template' => WorkflowGraph::defaults(),
            'permissions' => $this->ctx->permissions(),
            'user' => $this->ctx->safeUser(),
        ];
    }

    private function scope(string $module): array
    {
        $definition = UiSchema::modules()[$module]
            ?? throw new Problem('Unknown module.', 404);

        $this->ctx->require($definition['permission']);

        $userId = $this->ctx->id();
        $steps = null;
        $transfers = null;
        $disposalRequests = null;
        // Compile subqueries first: each compilation resets the shared builder before
        // the outer query is started. No unescaped request value becomes SQL text.
        if ($module==='my_tasks' || ($module==='requests' && !$this->ctx->can('requests.view_all'))) {
            $candidate=$this->db->escape(Context::json(['id' => $userId]));
            $this->db->reset_query()->select('s.request_id')->from('workflow_steps s');
            if ($module==='my_tasks') $this->db->where('s.status','pending')->where('JSON_CONTAINS(s.candidates,'.$candidate.') = 1',null,false);
            else $this->db->group_start()->where('s.acting_user_id',$userId)->or_where('JSON_CONTAINS(s.candidates,'.$candidate.') = 1',null,false)->group_end();
            $steps=$this->db->get_compiled_select();
        }
        if ($module==='requests' && !$this->ctx->can('requests.view_all')) {
            $this->db->reset_query()->select('tr.request_id')->from('transfers tr')->group_start()
                ->where('tr.recipient_id',$userId)->or_where('tr.current_holder_id',$userId)->group_end();
            $transfers=$this->db->get_compiled_select();
        }
        if ($module==='disposals' && !$this->ctx->can('documents.access_all') && !$this->ctx->can('disposal.view_all')) {
            $this->db->reset_query()->select('r.id')->from('requests r')->where('r.requested_by',$userId);
            $disposalRequests=$this->db->get_compiled_select();
        }
        $this->db->reset_query()->from($definition['table'].' t');
        if ($module==='my_requests') $this->db->where('t.requested_by',$userId);
        if ($module==='my_tasks') $this->db->where('t.status','pending')->where('t.id IN ('.$steps.')',null,false);
        if ($module==='requests' && !$this->ctx->can('requests.view_all')) {
            $this->db->group_start()->where('t.requested_by',$userId)->or_where('t.id IN ('.$steps.')',null,false)
                ->or_where('t.id IN ('.$transfers.')',null,false)->group_end();
        }
        if ($module==='transfers' && !$this->ctx->can('transfer.manage') && !$this->ctx->can('transfer.view_all')) {
            $this->db->group_start()->where('t.recipient_id',$userId)->or_where('t.current_holder_id',$userId)->group_end();
        }
        if ($module==='access' && !$this->ctx->can('access.manage') && !$this->ctx->can('access.view_all')) $this->db->where('t.user_id',$userId);
        if ($module==='assignments' && !$this->ctx->can('assignment.manage') && !$this->ctx->can('assignment.view_all')) $this->db->where('t.user_id',$userId);
        if ($disposalRequests!==null) $this->db->where('t.request_id IN ('.$disposalRequests.')',null,false);
        if ($module==='files' && !$this->ctx->can('files.approve') && !$this->ctx->can('files.view_all')) $this->db->where('t.uploaded_by',$userId);
        if ($module==='notifications') $this->db->where('t.user_id',$userId);
        return $definition;
    }
    private function search(
        array $definition,
        string $search
    ): void {
        if ($search === '') {
            return;
        }

        $this->db->group_start();

        foreach ($definition['columns'] as $column) {
            $this->db->or_like(
                't.' . $column,
                $search
            );
        }

        $this->db->group_end();
    }

    public function listing(
        string $module,
        array $query
    ): array {
        $definition = $this->scope($module);

        $request = Datatable_service::normalize(
            $query,
            $definition['columns']
        );

        $this->db->select(
            'COUNT(*) AS n',
            false
        );

        $unfiltered =
            (int) $this->first()['n'];

        $this->scope($module);
        $this->search(
            $definition,
            $request['q']
        );

        $this->db->select(
            'COUNT(*) AS n',
            false
        );

        $filtered =
            (int) $this->first()['n'];

        $this->scope($module);
        $this->search(
            $definition,
            $request['q']
        );

        $select =
            $module === 'users'
                ? 't.id,t.username,t.first_name,t.middle_name,t.last_name,t.position_title,t.role_id,t.leader_id,t.require_password_change,t.active,t.version,t.created_at,t.updated_at'
                : 't.*';

        $this->db
            ->select($select)
            ->order_by(
                't.' . $request['sort'],
                $request['direction']
            )
            ->limit(
                $request['limit'],
                $request['offset']
            );

        $rows = array_map(
            fn(array $row): array =>
                $this->safe($row),
            $this->results()
        );

        if ($module === 'access') {
            foreach ($rows as &$row) {
                $expired =
                    $row['status'] ===
                        'access_granted' &&
                    strtotime(
                        $row['expires_at']
                    ) < time();

                if ($expired) {
                    $row['status'] =
                        'expired';
                }
            }

            unset($row);
        }

        return Datatable_service::payload(
            $rows,
            $unfiltered,
            $filtered,
            $request
        );
    }

    private function safe(array $row): array
    {
        unset(
            $row['password_hash'],
            $row['session_version'],
            $row['storage_name']
        );

        $jsonFields = [
            'payload',
            'snapshot',
            'result',
            'graph',
            'config',
            'value',
            'origin',
            'destination',
            'candidates',
            'assignment',
            'before_state',
            'after_state',
            'previous_state',
        ];

        foreach ($jsonFields as $key) {
            if (
                !isset($row[$key]) ||
                !is_string($row[$key])
            ) {
                continue;
            }

            $decoded = json_decode(
                $row[$key],
                true
            );

            if (
                json_last_error() ===
                JSON_ERROR_NONE
            ) {
                $row[$key] =
                    $decoded;
            }
        }

        return $row;
    }

    /**
     * Resolve an internal foreign key into a human-readable label.
     */
    private function displayLabel(
        string $kind,
        ?int $id
    ): ?string {
        if (!$id) {
            return null;
        }

        $definitions = [
            'user' => [
                'users',
                "CONCAT(first_name,' ',last_name,' — ',position_title)",
            ],
            'role' => [
                'roles',
                'name',
            ],
            'area' => [
                'areas',
                'name',
            ],
            'specific' => [
                'specifics',
                'name',
            ],
            'asset' => [
                'assets',
                'asset_number',
            ],
            'location' => [
                'locations',
                "CONCAT(code,' — ',name)",
            ],
            'category' => [
                'categories',
                'name',
            ],
            'softcopy' => [
                'softcopy_documents',
                "CONCAT(document_number,' — ',title)",
            ],
            'hardcopy' => [
                'hardcopy_documents',
                'title',
            ],
            'request' => [
                'requests',
                'reference',
            ],
            'file' => [
                'files',
                'original_name',
            ],
        ];

        if (!isset($definitions[$kind])) {
            return null;
        }

        [$table, $expression] =
            $definitions[$kind];

        $this->db
            ->reset_query()
            ->select(
                $expression .
                ' AS label',
                false
            )
            ->from($table)
            ->where('id', $id)
            ->limit(1);

        $row = $this->first();

        return $row
            ? (string) $row['label']
            : null;
    }

    /**
     * Add readable relation names for frontend display.
     *
     * Numeric IDs are still returned internally for actions and persistence,
     * while shared frontend components hide them from the user.
     */
    private function withDisplayLabels(
        string $module,
        array $row
    ): array {
        $add = function (
            string $key,
            string $kind,
            string $source
        ) use (&$row): void {
            $id =
                isset($row[$source]) &&
                $row[$source] !== null
                    ? (int) $row[$source]
                    : null;

            $label =
                $this->displayLabel(
                    $kind,
                    $id
                );

            if (
                $label !== null &&
                $label !== ''
            ) {
                $row[$key] = $label;
            }
        };

        if ($module === 'users') {
            $add(
                'role',
                'role',
                'role_id'
            );

            $add(
                'leader',
                'user',
                'leader_id'
            );
        } elseif ($module === 'specifics') {
            $add(
                'area',
                'area',
                'area_id'
            );
        } elseif ($module === 'assets') {
            $add(
                'specific',
                'specific',
                'specific_id'
            );
        } elseif ($module === 'locations') {
            $add(
                'specific',
                'specific',
                'specific_id'
            );

            $add(
                'asset',
                'asset',
                'asset_id'
            );
        } elseif ($module === 'categories') {
            $add(
                'parent_category',
                'category',
                'parent_id'
            );
        } elseif ($module === 'softcopy') {
            $add(
                'category',
                'category',
                'category_id'
            );

            $add(
                'creator',
                'user',
                'created_by'
            );
        } elseif ($module === 'hardcopy') {
            foreach (
                [
                    ['area', 'area', 'area_id'],
                    ['specific', 'specific', 'specific_id'],
                    ['asset', 'asset', 'asset_id'],
                    ['location', 'location', 'location_id'],
                    ['holder', 'user', 'holder_id'],
                    ['creator', 'user', 'created_by'],
                ]
                as $relation
            ) {
                $add(...$relation);
            }
        } elseif (
            in_array(
                $module,
                [
                    'requests',
                    'my_requests',
                    'my_tasks',
                ],
                true
            )
        ) {
            $add(
                'requester',
                'user',
                'requested_by'
            );

            $add(
                'softcopy_document',
                'softcopy',
                'softcopy_id'
            );

            $add(
                'hardcopy_document',
                'hardcopy',
                'hardcopy_id'
            );

            if (
                is_array(
                    $row['payload'] ??
                    null
                )
            ) {
                $payload =
                    $row['payload'];

                $display = [];

                $relations = [
                    'area_id' => [
                        'area',
                        'area',
                    ],
                    'specific_id' => [
                        'specific',
                        'specific',
                    ],
                    'asset_id' => [
                        'asset',
                        'asset',
                    ],
                    'location_id' => [
                        'location',
                        'location',
                    ],
                    'recipient_id' => [
                        'recipient',
                        'user',
                    ],
                    'user_id' => [
                        'user',
                        'user',
                    ],
                    'holder_id' => [
                        'holder',
                        'user',
                    ],
                    'category_id' => [
                        'category',
                        'category',
                    ],
                    'file_id' => [
                        'file',
                        'file',
                    ],
                ];

                foreach (
                    $relations
                    as $source => $meta
                ) {
                    if (
                        empty(
                            $payload[$source]
                        )
                    ) {
                        continue;
                    }

                    $label =
                        $this->displayLabel(
                            $meta[1],
                            (int) $payload[
                                $source
                            ]
                        );

                    if ($label) {
                        $display[$meta[0]] =
                            $label;
                    }
                }

                foreach (
                    $payload
                    as $key => $value
                ) {
                    if (
                        str_ends_with(
                            (string) $key,
                            '_id'
                        )
                    ) {
                        continue;
                    }

                    $display[$key] =
                        $value;
                }

                $row['request_details'] =
                    $display;
            }
        } elseif ($module === 'transfers') {
            foreach (
                [
                    ['request', 'request', 'request_id'],
                    ['document', 'hardcopy', 'hardcopy_id'],
                    ['current_holder', 'user', 'current_holder_id'],
                    ['recipient', 'user', 'recipient_id'],
                ]
                as $relation
            ) {
                $add(...$relation);
            }
        } elseif ($module === 'access') {
            $add(
                'request',
                'request',
                'request_id'
            );

            $add(
                'user',
                'user',
                'user_id'
            );

            $documentKind =
                ($row['domain'] ?? '') ===
                'softcopy'
                    ? 'softcopy'
                    : 'hardcopy';

            $add(
                'document',
                $documentKind,
                'document_id'
            );
        } elseif ($module === 'assignments') {
            $add(
                'document',
                'softcopy',
                'softcopy_id'
            );

            $add(
                'user',
                'user',
                'user_id'
            );

            $add(
                'assigned_by_name',
                'user',
                'assigned_by'
            );
        } elseif ($module === 'disposals') {
            $add(
                'request',
                'request',
                'request_id'
            );

            $add(
                'disposed_by_name',
                'user',
                'disposed_by'
            );

            $documentKind =
                ($row['domain'] ?? '') ===
                'softcopy'
                    ? 'softcopy'
                    : 'hardcopy';

            $add(
                'document',
                $documentKind,
                'document_id'
            );
        } elseif ($module === 'files') {
            foreach (
                [
                    ['uploaded_by_name', 'user', 'uploaded_by'],
                    ['approved_by_name', 'user', 'approved_by'],
                    ['rejected_by_name', 'user', 'rejected_by'],
                ]
                as $relation
            ) {
                $add(...$relation);
            }

            if (
                ($row['domain'] ?? '') ===
                'softcopy'
            ) {
                $add(
                    'document',
                    'softcopy',
                    'document_id'
                );
            } elseif (
                ($row['domain'] ?? '') ===
                'hardcopy'
            ) {
                $add(
                    'document',
                    'hardcopy',
                    'document_id'
                );
            }
        } elseif ($module === 'history') {
            $add(
                'user_name',
                'user',
                'user_id'
            );
        }

        return $row;
    }

    public function detail(string $module, int $id): array
    {
        $this->scope($module);
        if ($module==='sequences') { $this->db->reset_query(); throw new Problem('Sequences are read-only counters.'); }
        $this->db->select('t.*')->where('t.id', $id)->limit(1);
        $row=$this->first() ?? throw new Problem('Record not found or unavailable to this account.',404);
        $related=[];
        if (in_array($module,['softcopy','hardcopy'],true)) {
            $content=(new Document_service($this->ctx))->canRead($module, $id);
            if ($content) {
                $this->db->reset_query()->from('files')->where('domain',$module)->where('document_id', $id)->order_by('id','DESC');
                $related['files']=array_map(fn(array $row)=>$this->safe($row),$this->results());
            }
            if ($module==='softcopy') {
                $this->db->reset_query()->select('r.*')->from('softcopy_revisions r')->where('r.document_id', $id)->order_by('r.revision_number','DESC');
                $related['revisions']=$this->results();
                foreach($related['revisions'] as &$revision) $revision['revision_status']=(int)$revision['id']===(int)$row['current_revision_id']?'current':'historical'; unset($revision);
                if ($content) {
                    $this->db->reset_query()->select('a.*')->from('revision_artifacts a')->join('softcopy_revisions r','r.id = a.revision_id')->where('r.document_id', $id)->order_by('a.id','DESC');
                    $related['artifacts']=$this->results();
                }
            }
            $this->db->reset_query()->from('disposals')->where('domain',$module)->where('document_id', $id)->order_by('id','DESC');
            $related['disposals']=array_map(fn(array $row)=>$this->safe($row),$this->results());
            $this->db->reset_query()->from('status_history')->where('domain',$module)->where('document_id', $id)->order_by('id','DESC');
            $related['status_history']=$this->results();
            $related['can_read_files']=$content;
        }
        if (in_array($module,['requests','my_requests','my_tasks'],true)) {
            $this->db->reset_query()->from('workflow_steps')->where('request_id', $id)->order_by('id');
            $related['steps']=array_map(fn(array $row)=>$this->safe($row),$this->results());
            $this->db->reset_query()->select('h.*, s.label AS step_name')->from('workflow_history h')->join('workflow_steps s','s.id = h.step_id','left')->where('h.request_id', $id)->order_by('h.id');
            $related['history']=array_map(fn(array $row)=>$this->safe($row),$this->results());
            if ($row['workflow_version_id']) {
                $this->db->reset_query()->select('v.version_number,v.status,v.is_default,w.name AS workflow_name,w.request_type')->from('workflow_versions v')->join('workflows w','w.id = v.workflow_id')->where('v.id',$row['workflow_version_id'])->limit(1);
                $related['workflow_version']=$this->first();
            } else $related['workflow_version']=null;
            $this->db->reset_query()->select('id')->from('transfers')->where('request_id', $id)->limit(1);
            $related['transfer']=$this->first();
        }
        if ($module==='workflows') {
            $this->db->reset_query()->from('workflow_versions')->where('workflow_id', $id)->order_by('version_number','DESC');
            $related['versions']=array_map(fn(array $row)=>$this->safe($row),$this->results());
        }
        if ($module==='roles') {
            $this->db->reset_query()->select('permission_id')->from('role_permissions')->where('role_id', $id);
            $related['permission_ids']=array_map('intval',array_column($this->results(),'permission_id'));
            if ($this->ctx->can('roles.edit')) {
                $this->db->reset_query()->select('id,name,module_label,action_label')->from('permissions')->order_by('module_key')->order_by('action_key');
                $related['available_permissions']=$this->results();
            }
        }
        return ['row'=>$this->withDisplayLabels($module,$this->safe($row)),'related'=>$related];
    }
    public function lookups(array $query): array
    {
        $kind = Rules::choice(
            $query,
            'kind',
            [
                'users',
                'roles',
                'permissions',
                'areas',
                'specifics',
                'assets',
                'locations',
                'categories',
                'softcopy',
                'hardcopy',
            ]
        );

        $table = match ($kind) {
            'softcopy' => 'softcopy_documents',
            'hardcopy' => 'hardcopy_documents',
            default => $kind,
        };

        if (in_array($kind, ['softcopy', 'hardcopy'], true)) {
            $this->ctx->require($kind . '.view');
        }

        if ($kind === 'permissions') {
            $this->ctx->require('permissions.view');
        }

        $label = match ($kind) {
            'users' => "CONCAT(first_name,' ',last_name,' — ',position_title)",
            'assets' => 'asset_number',
            'softcopy' => "CONCAT(document_number,' — ',title)",
            'hardcopy' => 'title',
            'locations' => "CONCAT(code,' — ',name)",
            default => 'name',
        };

        $search = Rules::text($query, 'q', 100, false) ?? '';

        $this->db
            ->reset_query()
            ->select('id')
            ->select($label . ' AS label', false)
            ->from($table)
            ->group_start();

        if (in_array($kind, ['softcopy', 'hardcopy'], true)) {
            $this->db->where('status', 'active');
        } elseif ($kind !== 'permissions') {
            $this->db->where('active', 1);
        }

        $this->db
            ->like($label, $search)
            ->group_end();

        $selected = Rules::id($query, 'selected', false);

        if ($selected !== null) {
            $this->db->or_where('id', $selected);
        }

        $this->db
            ->order_by('label')
            ->limit(101);

        $rows = $this->results();
        $more = count($rows) > 100;

        return [
            'options' => array_slice($rows, 0, 100),
            'more' => $more,
            'message' => $more
                ? 'More results exist. Type a narrower search.'
                : '',
        ];
    }
    public function dashboard(): array
    {
        $this->ctx->require('dashboard.view');

        $userId = $this->ctx->id();
        $result = [];
        foreach (
            [
                'softcopy' => 'softcopy_documents',
                'hardcopy' => 'hardcopy_documents',
            ] as $module => $table
        ) {
            if (!$this->ctx->can($module . '.view')) {
                continue;
            }

            $this->db
                ->reset_query()
                ->select('status')
                ->select('COUNT(*) AS total', false)
                ->from($table)
                ->group_by('status');

            $result[$module] = $this->results();
        }

        $this->db
            ->reset_query()
            ->select('status')
            ->select('COUNT(*) AS total', false)
            ->from('requests')
            ->where('requested_by', $userId)
            ->group_by('status');

        $result['my_requests'] = $this->results();

        $this->db
            ->reset_query()
            ->select('COUNT(*) AS n', false)
            ->from('notifications')
            ->where('user_id', $userId)
            ->where('read_at', null);

        $result['unread_notifications'] = (int) $this->first()['n'];

        $this->db
            ->reset_query()
            ->select('COUNT(*) AS n', false)
            ->from('transfers')
            ->where('recipient_id', $userId)
            ->where('status', 'pending_recipient_acceptance');

        $result['pending_receipts'] = (int) $this->first()['n'];

        return $result;
    }
    public function readNotification(array $input): array
    {
        $this->ctx->require('notifications.edit');

        $row = $this->lock(
            'notifications',
            Rules::id($input),
            Rules::id($input, 'version')
        );

        if ((int) $row['user_id'] !== $this->ctx->id()) {
            throw new Problem(
                'This notification belongs to another user.',
                403
            );
        }

        if ($row['read_at'] === null) {
            $this->update(
                'notifications',
                (int) $row['id'],
                ['read_at' => date('Y-m-d H:i:s')]
            );
        }

        return [
            'message' => 'Notification marked as read.',
        ];
    }
}
