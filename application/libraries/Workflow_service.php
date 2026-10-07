<?php
declare(strict_types=1);

use Pk\Core\{
    Context,
    Problem,
    Rules,
    WorkflowGraph
};

class Workflow_service
{
    // Ari ang approval flow: draft -> publish -> default -> pinned request snapshot.
    private Context $ctx;

    public function __construct(Context|array|null $options = null)
    {
        $this->ctx = Context::fromOptions($options);
    }

    public function save(array $input): array
    {
        $this->ctx->require('workflows.edit');

        $db = $this->ctx->model(
            \Workflow_model::class
        );

        $id = Rules::id(
            $input,
            'id',
            false
        );

        $before = $id
            ? $db->lock(
                'workflows',
                $id,
                Rules::id(
                    $input,
                    'version'
                )
            )
            : null;

        $data = [
            'name' => Rules::text(
                $input,
                'name',
                150
            ),
            'description' => Rules::text(
                $input,
                'description',
                4000,
                false
            ),
        ];

        if (!$id || !$before) {
            throw new Problem(
                'Workflow definitions are seeded for each request type. Edit an existing workflow and create a new version instead.',
                409
            );
        }

        $db->update(
            'workflows',
            $id,
            $data
        );

        $this->ctx->audit(
            'workflows',
            'updated',
            $id,
            $before,
            $data
        );

        return [
            'id' => $id,
            'message' => 'Workflow updated.',
        ];
    }

    /**
     * Resolve readable labels before a workflow version is stored.
     *
     * IDs remain internal while the saved snapshot keeps enough text
     * to explain the approval sequence in the frontend and audit history.
     */
    private function hydrateApproverLabels(
        array $workflow
    ): array {
        foreach ($workflow['steps'] as &$step) {
            $approver = $step['approver'];
            $type = $approver['type'];

            if ($type === 'user') {
                $user = $this->ctx->active(
                    'users',
                    (int) $approver['value']
                );

                $step['approver']['label'] =
                    Context::name($user) .
                    ' — ' .
                    $user['position_title'];

                continue;
            }

            if ($type === 'role') {
                $role = $this->ctx->active(
                    'roles',
                    (int) $approver['value']
                );

                $step['approver']['label'] =
                    $role['name'];

                continue;
            }

            if ($type === 'leader') {
                $step['approver']['label'] =
                    "Requester's Leader";

                continue;
            }

            $step['approver']['label'] =
                'Requester';
        }

        unset($step);

        return $workflow;
    }

    public function version(array $input): array
    {
        $this->ctx->require('workflows.edit');

        $db = $this->ctx->model(
            \Workflow_model::class
        );

        $id = Rules::id(
            $input,
            'id',
            false
        );

        $existing = $id
            ? $db->row(
                'workflow_versions',
                $id
            )
            : null;

        $workflowId = $existing
            ? (int) $existing['workflow_id']
            : Rules::id(
                $input,
                'workflow_id'
            );

        $db->lock(
            'workflows',
            $workflowId
        );

        $before = $id
            ? $db->lock(
                'workflow_versions',
                $id,
                Rules::id(
                    $input,
                    'version'
                )
            )
            : null;

        if (
            $before &&
            $before['status'] !== 'draft'
        ) {
            throw new Problem(
                'Published workflow versions are immutable. Create a new draft version.',
                409
            );
        }

        $workflow = WorkflowGraph::validate(
            Rules::json(
                $input['graph'] ?? []
            ),
            false
        );

        $workflow =
            $this->hydrateApproverLabels(
                $workflow
            );

        if ($id) {
            $db->update(
                'workflow_versions',
                $id,
                [
                    'graph' =>
                        Context::json(
                            $workflow
                        ),
                ]
            );
        } else {
            $next =
                $db->next_version_number(
                    [$workflowId]
                );

            $id = $db->insert(
                'workflow_versions',
                [
                    'workflow_id' =>
                        $workflowId,
                    'version_number' =>
                        (int) $next['n'],
                    'graph' =>
                        Context::json(
                            $workflow
                        ),
                    'created_by' =>
                        $this->ctx->id(),
                ]
            );
        }

        $this->ctx->audit(
            'workflows',
            'draft_saved',
            $id,
            $before,
            $workflow
        );

        return [
            'id' => $id,
            'message' =>
                'Draft version saved.',
        ];
    }

    public function publish(array $input): array
    {
        $this->ctx->require('workflows.edit');

        $db = $this->ctx->model(
            \Workflow_model::class
        );

        $id = Rules::id($input);

        $version = $db->lock(
            'workflow_versions',
            $id,
            Rules::id(
                $input,
                'version'
            )
        );

        if ($version['status'] !== 'draft') {
            throw new Problem(
                'Only a draft version can be published.',
                409
            );
        }

        $definition = $db->lock(
            'workflows',
            (int) $version['workflow_id']
        );

        $workflow =
            $this->hydrateApproverLabels(
                WorkflowGraph::validate(
                    Rules::json(
                        $version['graph']
                    ),
                    true
                )
            );

        $db->update(
            'workflow_versions',
            $id,
            [
                'graph' =>
                    Context::json(
                        $workflow
                    ),
                'status' => 'published',
                'published_at' =>
                    date('Y-m-d H:i:s'),
            ]
        );

        $existingDefault =
            $db->default_version_for_workflow(
                [
                    (int) $definition['id'],
                ]
            );

        $makeDefault =
            !(int) $definition['active'] ||
            !$existingDefault;

        if ($makeDefault) {
            $this->makeDefault(
                (int) $definition['id'],
                $id,
                $definition['request_type']
            );
        }

        $this->ctx->audit(
            'workflows',
            'published',
            $id,
            $version,
            [
                'status' => 'published',
                'is_default' =>
                    $makeDefault ? 1 : 0,
            ],
            Rules::text(
                $input,
                'reason',
                2000
            )
        );

        return [
            'message' => $makeDefault
                ? 'Version published and set as the default for new requests.'
                : 'Version published. Set it as default when it is ready for new requests.',
        ];
    }

    public function setDefault(array $input): array
    {
        $this->ctx->require('workflows.edit');

        $db = $this->ctx->model(
            \Workflow_model::class
        );

        $id = Rules::id($input);

        $version = $db->lock(
            'workflow_versions',
            $id,
            Rules::id(
                $input,
                'version'
            )
        );

        if ($version['status'] !== 'published') {
            throw new Problem(
                'Only a published workflow version can be the default.',
                409
            );
        }

        $definition = $db->lock(
            'workflows',
            (int) $version['workflow_id']
        );

        $alreadyDefault =
            (int) $version['is_default'] === 1 &&
            (int) $definition['active'] === 1;

        if ($alreadyDefault) {
            return [
                'message' =>
                    'This version is already the default.',
            ];
        }

        $this->makeDefault(
            (int) $definition['id'],
            $id,
            $definition['request_type']
        );

        $this->ctx->audit(
            'workflows',
            'default_version_changed',
            $id,
            $version,
            [
                'is_default' => 1,
            ],
            Rules::text(
                $input,
                'reason',
                2000
            )
        );

        return [
            'message' =>
                'Default workflow version updated for new requests. Existing requests keep their original version.',
        ];
    }

    private function makeDefault(
        int $workflowId,
        int $versionId,
        string $requestType
    ): void {
        $db = $this->ctx->model(
            \Workflow_model::class
        );

        $this->ctx->sequence(
            'workflow_route_' . $requestType,
            ''
        );

        $db->deactivate_other_definitions(
            [
                $requestType,
                $workflowId,
            ]
        );

        $db->update(
            'workflows',
            $workflowId,
            [
                'active' => 1,
            ]
        );

        $db->clear_default_versions(
            [$workflowId]
        );

        $db->update(
            'workflow_versions',
            $versionId,
            [
                'is_default' => 1,
            ]
        );
    }

    public function begin(
        array $request,
        callable $complete
    ): void {
        $db = $this->ctx->model(
            \Workflow_model::class
        );

        $requestId =
            (int) $request['id'];

        if ($request['snapshot']) {
            $workflow =
                WorkflowGraph::validate(
                    Rules::json(
                        $request['snapshot']
                    ),
                    true
                );
        } else {
            $version =
                $db->default_version(
                    [$request['type']]
                );

            if (!$version) {
                throw new Problem(
                    'No default published workflow version exists for this request type.',
                    409
                );
            }

            $workflow =
                WorkflowGraph::validate(
                    Rules::json(
                        $version['graph']
                    ),
                    true
                );

            $db->update(
                'requests',
                $requestId,
                [
                    'workflow_version_id' =>
                        $version['id'],
                    'snapshot' =>
                        Context::json(
                            $workflow
                        ),
                ]
            );
        }

        $db->update(
            'requests',
            $requestId,
            [
                'status' => 'pending',
                'submitted_at' =>
                    date('Y-m-d H:i:s'),
                'completed_at' => null,
                'result' => null,
            ]
        );

        $stored = $db->row(
            'requests',
            $requestId
        );

        $this->history(
            $requestId,
            null,
            'submitted',
            null,
            [
                'workflow_version_id' =>
                    $stored[
                        'workflow_version_id'
                    ],
            ]
        );

        $this->advance(
            $stored,
            WorkflowGraph::firstKey(
                $workflow
            ),
            $complete
        );
    }

    public function decide(
        array $request,
        array $input,
        callable $complete
    ): void {
        if (!$this->ctx->id()) {
            throw new Problem(
                'Sign in first.',
                401
            );
        }

        $db = $this->ctx->model(
            \Workflow_model::class
        );

        if ($request['status'] !== 'pending') {
            throw new Problem(
                'This request is no longer awaiting approval.',
                409
            );
        }

        $step = $db->lock(
            'workflow_steps',
            Rules::id(
                $input,
                'step_id'
            )
        );

        $stepChanged =
            (int) $step['request_id'] !==
                (int) $request['id'] ||
            $step['status'] !== 'pending' ||
            $step['node_key'] !==
                $request['current_node'];

        if ($stepChanged) {
            throw new Problem(
                'The approval step has already changed.',
                409
            );
        }

        $candidates =
            Rules::json(
                $step['candidates']
            );

        $candidateIds =
            array_map(
                'intval',
                array_column(
                    $candidates,
                    'id'
                )
            );

        if (
            !in_array(
                $this->ctx->id(),
                $candidateIds,
                true
            )
        ) {
            throw new Problem(
                'Only the assigned approver can decide this step.',
                403
            );
        }

        $decision = Rules::choice(
            $input,
            'decision',
            [
                'approve',
                'reject',
                'return',
            ]
        );

        // Remarks are required for every approval action.
        $comments = Rules::text(
            $input,
            'comments',
            4000
        );

        $db->update(
            'workflow_steps',
            (int) $step['id'],
            [
                'status' => 'completed',
                'decision' => $decision,
                'comments' => $comments,
                'acting_user_id' =>
                    $this->ctx->id(),
                'acting_name' =>
                    Context::name(
                        $this->ctx->user
                    ),
                'acting_position' =>
                    $this->ctx->user[
                        'position_title'
                    ],
                'acted_at' =>
                    date('Y-m-d H:i:s'),
            ]
        );

        if ($decision === 'reject') {
            $after = [
                'status' => 'rejected',
                'current_node' => null,
                'workflow_complete' => true,
            ];

            $db->update(
                'requests',
                (int) $request['id'],
                [
                    'status' => 'rejected',
                    'current_node' => null,
                    'completed_at' =>
                        date('Y-m-d H:i:s'),
                ]
            );

            $this->history(
                (int) $request['id'],
                (int) $step['id'],
                'reject',
                $step,
                $after,
                $comments
            );

            $this->ctx->notify(
                (int) $request['requested_by'],
                'Request rejected',
                $request['reference'] .
                    ' was rejected and its approval workflow has ended.',
                (int) $request['id']
            );

            return;
        }

        if ($decision === 'return') {
            $this->returnToPreviousHolder(
                $request,
                $step,
                $comments
            );

            return;
        }

        $workflow =
            WorkflowGraph::validate(
                Rules::json(
                    $request['snapshot']
                ),
                true
            );

        $next =
            WorkflowGraph::nextKey(
                $workflow,
                $request[
                    'current_node'
                ]
            );

        $this->history(
            (int) $request['id'],
            (int) $step['id'],
            'approve',
            $step,
            [
                'status' => 'pending',
                'next_node' => $next,
                'final_approval' =>
                    $next === null,
            ],
            $comments
        );

        if ($next === null) {
            $complete($request);

            $this->history(
                (int) $request['id'],
                null,
                'approved',
                null,
                [
                    'workflow_complete' =>
                        true,
                ]
            );

            $this->ctx->notify(
                (int) $request[
                    'requested_by'
                ],
                'Request approved',
                $request['reference'] .
                    ' completed its approval workflow.',
                (int) $request['id']
            );

            return;
        }

        $this->advance(
            $db->row(
                'requests',
                (int) $request['id']
            ),
            $next,
            $complete
        );
    }

    private function returnToPreviousHolder(
        array $request,
        array $currentStep,
        string $comments
    ): void {
        $db = $this->ctx->model(
            \Workflow_model::class
        );

        $requestId = (int) $request['id'];

        $previous =
            $db->previous_approved_step(
                [
                    $requestId,
                    (int) $currentStep['id'],
                ]
            );

        // Walay earlier approver: ang requester mao ang previous holder.
        if (!$previous) {
            $after = [
                'status' => 'returned',
                'current_node' => null,
                'returned_to' => 'requester',
                'returned_to_user_id' =>
                    (int) $request['requested_by'],
            ];

            $db->update(
                'requests',
                $requestId,
                [
                    'status' => 'returned',
                    'current_node' => null,
                    'completed_at' => null,
                ]
            );

            $this->history(
                $requestId,
                (int) $currentStep['id'],
                'return',
                $currentStep,
                $after,
                $comments
            );

            $this->ctx->notify(
                (int) $request['requested_by'],
                'Request returned for correction',
                $request['reference'] .
                    ' was returned to you. Review the remarks, correct the request, then resubmit it.',
                $requestId
            );

            return;
        }

        $previousUserId =
            (int) (
                $previous['acting_user_id']
                ?: $previous['assigned_user_id']
            );

        $holder =
            $db->active_user_for_workflow(
                $previousUserId
            );

        if (!$holder) {
            throw new Problem(
                'The previous request holder is no longer active. Ask an administrator to reassign the workflow before returning it.',
                409
            );
        }

        $candidate = [
            'id' => (int) $holder['id'],
            'name' => Context::name($holder),
            'position' =>
                $holder['position_title'],
        ];

        $assignment = [
            'type' => 'user',
            'value' => (int) $holder['id'],
            'label' =>
                $candidate['name'] .
                ' — ' .
                $candidate['position'],
        ];

        $newStepId = $db->insert(
            'workflow_steps',
            [
                'request_id' => $requestId,
                'node_key' =>
                    $previous['node_key'],
                'label' =>
                    $previous['label'],
                'assignment' =>
                    Context::json($assignment),
                'candidates' =>
                    Context::json(
                        [$candidate]
                    ),
                'assigned_user_id' =>
                    (int) $holder['id'],
                'assigned_name' =>
                    $candidate['name'],
                'assigned_position' =>
                    $candidate['position'],
            ]
        );

        $after = [
            'status' => 'pending',
            'current_node' =>
                $previous['node_key'],
            'returned_to' =>
                $candidate['name'],
            'returned_to_user_id' =>
                (int) $holder['id'],
            'new_step_id' => $newStepId,
        ];

        $db->update(
            'requests',
            $requestId,
            [
                'status' => 'pending',
                'current_node' =>
                    $previous['node_key'],
                'completed_at' => null,
            ]
        );

        $this->history(
            $requestId,
            (int) $currentStep['id'],
            'return',
            $currentStep,
            $after,
            $comments
        );

        $this->history(
            $requestId,
            $newStepId,
            'assigned_after_return',
            null,
            [
                'step_name' =>
                    $previous['label'],
                'assigned_to' =>
                    $candidate,
            ]
        );

        $this->ctx->notify(
            (int) $holder['id'],
            'Request returned to you',
            $request['reference'] .
                ' was returned to your previous approval step. Review the remarks and take action again.',
            $requestId
        );
    }

    private function advance(
        array $request,
        ?string $key,
        callable $complete
    ): void {
        if ($key === null) {
            $complete($request);
            return;
        }

        $db = $this->ctx->model(
            \Workflow_model::class
        );

        $workflow =
            WorkflowGraph::validate(
                Rules::json(
                    $request['snapshot']
                ),
                true
            );

        $stepDefinition =
            WorkflowGraph::step(
                $workflow,
                $key
            );

        $candidates =
            $this->resolve(
                $stepDefinition[
                    'approver'
                ],
                $request
            );

        if (!$candidates) {
            throw new Problem(
                'No eligible active approver for “' .
                $stepDefinition['name'] .
                '”. Update the workflow or related user/role before submitting.',
                409
            );
        }

        $single =
            count($candidates) === 1
                ? $candidates[0]
                : null;

        $assignedName =
            $single['name'] ??
            (
                $stepDefinition[
                    'approver'
                ]['label'] ??
                null
            );

        $assignedPosition =
            $single['position'] ??
            (
                $stepDefinition[
                    'approver'
                ]['type'] === 'role'
                    ? 'Role'
                    : null
            );

        $stepId = $db->insert(
            'workflow_steps',
            [
                'request_id' =>
                    $request['id'],
                'node_key' => $key,
                'label' =>
                    $stepDefinition[
                        'name'
                    ],
                'assignment' =>
                    Context::json(
                        $stepDefinition[
                            'approver'
                        ]
                    ),
                'candidates' =>
                    Context::json(
                        $candidates
                    ),
                'assigned_user_id' =>
                    $single['id'] ??
                    null,
                'assigned_name' =>
                    $assignedName,
                'assigned_position' =>
                    $assignedPosition,
            ]
        );

        $db->update(
            'requests',
            (int) $request['id'],
            [
                'current_node' => $key,
                'status' => 'pending',
            ]
        );

        $this->history(
            (int) $request['id'],
            $stepId,
            'assigned',
            null,
            [
                'step_name' =>
                    $stepDefinition[
                        'name'
                    ],
                'approver' =>
                    $stepDefinition[
                        'approver'
                    ],
                'candidates' =>
                    $candidates,
            ]
        );

        foreach ($candidates as $user) {
            $this->ctx->notify(
                (int) $user['id'],
                'Approval task',
                $request['reference'] .
                    ' — ' .
                    $stepDefinition['name'],
                (int) $request['id']
            );
        }
    }

    private function resolve(
        array $approver,
        array $request
    ): array {
        $db = $this->ctx->model(
            \Workflow_model::class
        );

        $type = $approver['type'];
        $requester =
            (int) $request['requested_by'];

        if ($type === 'requester') {
            $user =
                $db->active_user_for_workflow(
                    $requester
                );

            if (!$user) {
                return [];
            }

            return [
                [
                    'id' =>
                        (int) $user['id'],
                    'name' =>
                        Context::name(
                            $user
                        ),
                    'position' =>
                        $user[
                            'position_title'
                        ],
                ],
            ];
        }

        if ($type === 'leader') {
            $requesterRow =
                $db->active_user_for_workflow(
                    $requester
                );

            if (
                !$requesterRow ||
                !$requesterRow[
                    'leader_id'
                ]
            ) {
                return [];
            }

            $leader =
                $db->active_user_for_workflow(
                    (int) $requesterRow[
                        'leader_id'
                    ]
                );

            if (!$leader) {
                return [];
            }

            return [
                [
                    'id' =>
                        (int) $leader['id'],
                    'name' =>
                        Context::name(
                            $leader
                        ),
                    'position' =>
                        $leader[
                            'position_title'
                        ],
                ],
            ];
        }

        return $db->eligible_approvers(
            $type,
            $approver['value'] ?? null,
            $requester
        );
    }

    public function reassign(array $input): array
    {
        $this->ctx->require(
            'workflows.reassign'
        );

        $db = $this->ctx->model(
            \Workflow_model::class
        );

        $step = $db->row(
            'workflow_steps',
            Rules::id(
                $input,
                'step_id'
            )
        );

        $request = $db->lock(
            'requests',
            (int) $step['request_id'],
            Rules::id(
                $input,
                'version'
            )
        );

        $step = $db->lock(
            'workflow_steps',
            (int) $step['id']
        );

        if (
            $request['status'] !==
                'pending' ||
            $step['status'] !==
                'pending'
        ) {
            throw new Problem(
                'Only active approval steps can be reassigned.',
                409
            );
        }

        $userId = Rules::id(
            $input,
            'user_id'
        );

        $reason = Rules::text(
            $input,
            'reason',
            4000
        );

        $users =
            $db->eligible_approvers(
                'user',
                $userId,
                (int) $request[
                    'requested_by'
                ]
            );

        if (!$users) {
            throw new Problem(
                'The replacement must be an active user other than the requester.'
            );
        }

        $user = $users[0];

        $assignment = [
            'type' => 'user',
            'value' => $userId,
            'label' =>
                $user['name'] .
                ' — ' .
                $user['position'],
        ];

        $db->update(
            'workflow_steps',
            (int) $step['id'],
            [
                'candidates' =>
                    Context::json(
                        $users
                    ),
                'assigned_user_id' =>
                    $userId,
                'assigned_name' =>
                    $user['name'],
                'assigned_position' =>
                    $user['position'],
                'assignment' =>
                    Context::json(
                        $assignment
                    ),
            ]
        );

        $beforeCandidates =
            Rules::json(
                $step['candidates']
            );

        $this->history(
            (int) $request['id'],
            (int) $step['id'],
            'reassigned',
            $beforeCandidates,
            $users,
            $reason
        );

        $this->ctx->audit(
            'workflows',
            'approver_reassigned',
            (int) $step['id'],
            $beforeCandidates,
            $users,
            $reason,
            (int) $request['id']
        );

        $this->ctx->notify(
            $userId,
            'Task reassigned',
            $request['reference'] .
                ' — ' .
                $step['label'],
            (int) $request['id']
        );

        return [
            'message' =>
                'Approval step reassigned with history preserved.',
        ];
    }

    public function history(
        int $request,
        ?int $step,
        string $action,
        mixed $before = null,
        mixed $after = null,
        ?string $comments = null
    ): void {
        $this->ctx
            ->model(
                \Workflow_model::class
            )
            ->insert(
                'workflow_history',
                [
                    'request_id' =>
                        $request,
                    'step_id' => $step,
                    'action' => $action,
                    'user_id' =>
                        $this->ctx->id(),
                    'user_name' =>
                        Context::name(
                            $this->ctx->user
                        ),
                    'position_title' =>
                        $this->ctx->user[
                            'position_title'
                        ],
                    'before_state' =>
                        Context::json(
                            $before
                        ),
                    'after_state' =>
                        Context::json(
                            $after
                        ),
                    'comments' =>
                        $comments,
                ]
            );

        $this->ctx->audit(
            'requests',
            $action,
            $request,
            $before,
            $after,
            $comments,
            $request
        );
    }
}
