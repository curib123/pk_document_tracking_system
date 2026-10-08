<?php
declare(strict_types=1);

use Pk\Core\{Context, Problem, Rules};

class Request_service
{
    // Kani nga service mao ang request lifecycle: draft -> submit -> approve -> apply.
    public const TYPES = [
        'softcopy_create',
        'softcopy_revise',
        'softcopy_cancel',
        'hardcopy_create',
        'hardcopy_update',
        'transfer',
        'assignment',
        'access',
        'disposal',
    ];

    private Context $ctx;
    private Document_service $documents;
    private Workflow_service $workflow;

    public function __construct(Context|array|null $options = null)
    {
        $this->ctx = Context::fromOptions($options);
        $this->documents = new Document_service($this->ctx);
        $this->workflow = new Workflow_service($this->ctx);
    }

    private function model(): Request_model
    {
        return $this->ctx->model(\Request_model::class);
    }

    private function requireTypePermission(string $type): void
    {
        if (str_starts_with($type, 'softcopy_')) {
            $this->ctx->require('softcopy.request');
            return;
        }

        if (str_starts_with($type, 'hardcopy_')) {
            $this->ctx->require('hardcopy.request');
            return;
        }

        $this->ctx->require($type . '.request');
    }

    private function domainFor(
        string $type,
        ?int $softcopyId,
        ?int $hardcopyId
    ): string {
        if ($softcopyId) {
            return 'softcopy';
        }

        if ($hardcopyId) {
            return 'hardcopy';
        }

        return str_starts_with($type, 'softcopy')
            ? 'softcopy'
            : 'hardcopy';
    }

    public function save(array $input): array
    {
        $id = Rules::id($input, 'id', false);

        $this->ctx->require(
            $id ? 'requests.edit' : 'requests.add'
        );

        $before = $id
            ? $this->model()->lock(
                'requests',
                $id,
                Rules::id($input, 'version')
            )
            : null;

        $this->assertDraftEditable($before);

        $type = Rules::choice(
            $input,
            'type',
            self::TYPES
        );

        $this->requireTypePermission($type);

        $softcopyId = Rules::id(
            $input,
            'softcopy_id',
            false
        );

        $hardcopyId = Rules::id(
            $input,
            'hardcopy_id',
            false
        );

        $this->assertTargetUnchanged(
            $before,
            $type,
            $softcopyId,
            $hardcopyId
        );

        $payload = $this->normalize(
            $type,
            $softcopyId,
            $hardcopyId,
            Rules::json($input['payload'] ?? []),
            $this->ctx->id(),
            true
        );

        $data = [
            'type' => $type,
            'softcopy_id' => $softcopyId,
            'hardcopy_id' => $hardcopyId,
            'payload' => Context::json($payload),
        ];

        if ($id) {
            $this->model()->update(
                'requests',
                $id,
                $data
            );
        } else {
            // Ari ta mag-generate sa reference once para stable na siya throughout the request lifecycle.
            $id = $this->model()->insert(
                'requests',
                array_merge(
                    $data,
                    [
                        'reference' => $this->ctx->sequence(
                            'request_' . date('Y'),
                            'REQ-' . date('Y') . '-'
                        ),
                        'requested_by' => $this->ctx->id(),
                    ]
                )
            );
        }

        $this->workflow->history(
            $id,
            null,
            $before ? 'draft_updated' : 'draft_created',
            $before,
            $data
        );

        return [
            'id' => $id,
            'message' => 'Draft saved. Submit it from the request dialog when ready.',
        ];
    }

    private function assertDraftEditable(?array $request): void
    {
        if (!$request) {
            return;
        }

        $ownedByCurrentUser =
            (int) $request['requested_by'] === $this->ctx->id();

        $editableStatus = in_array(
            $request['status'],
            ['draft', 'returned'],
            true
        );

        if (!$ownedByCurrentUser || !$editableStatus) {
            throw new Problem(
                'Only your own draft or returned requests may be edited.',
                403
            );
        }
    }

    private function assertTargetUnchanged(
        ?array $request,
        string $type,
        ?int $softcopyId,
        ?int $hardcopyId
    ): void {
        if (!$request) {
            return;
        }

        $oldSoftcopyId = $request['softcopy_id']
            ? (int) $request['softcopy_id']
            : null;

        $oldHardcopyId = $request['hardcopy_id']
            ? (int) $request['hardcopy_id']
            : null;

        $changed =
            $type !== $request['type']
            || $softcopyId !== $oldSoftcopyId
            || $hardcopyId !== $oldHardcopyId;

        if ($changed) {
            throw new Problem(
                'Request type and target are immutable; create another draft.'
            );
        }
    }

    private function normalize(
        string $type,
        ?int $softcopyId,
        ?int $hardcopyId,
        array $payload,
        int $ownerId,
        bool $checkRights
    ): array {
        $this->validateTargetShape(
            $type,
            $softcopyId,
            $hardcopyId
        );

        $domain = $this->domainFor(
            $type,
            $softcopyId,
            $hardcopyId
        );

        $targetId = $softcopyId ?? $hardcopyId;

        $document = $targetId
            ? $this->model()->lock(
                Document_service::table($domain),
                $targetId
            )
            : null;

        if ($document && $document['status'] !== 'active') {
            throw new Problem(
                'The target document is not active.'
            );
        }

        if ($checkRights) {
            $this->assertRequestAccess(
                $type,
                $domain,
                $targetId,
                $document,
                $ownerId
            );
        }

        $data = [
            'reason' => Rules::text(
                $payload,
                'reason',
                4000
            ),
        ];

        if (
            in_array(
                $type,
                [
                    'softcopy_create',
                    'softcopy_revise',
                    'hardcopy_create',
                    'hardcopy_update',
                ],
                true
            )
        ) {
            $data = $this->documents->validate(
                $domain,
                $payload,
                $targetId,
                $ownerId
            );
        } elseif ($type === 'transfer') {
            $data = array_merge(
                $data,
                $this->normalizeTransfer(
                    $payload,
                    $hardcopyId,
                    $document
                )
            );
        } elseif ($type === 'assignment') {
            $data['user_id'] = Rules::id(
                $payload,
                'user_id'
            );

            $this->ctx->active(
                'users',
                $data['user_id']
            );
        } elseif ($type === 'access') {
            $data['expiration_date'] =
                $this->normalizeAccessExpiration($payload);
        } elseif ($type === 'disposal') {
            $data = array_merge(
                $data,
                $this->normalizeDisposal(
                    $payload,
                    $domain,
                    $hardcopyId,
                    $document
                )
            );
        }

        if ($document) {
            // Kani nga version mao atong guard against stale request data.
            $data['base_document_version'] =
                (int) $document['version'];
        }

        return $data;
    }

    private function validateTargetShape(
        string $type,
        ?int $softcopyId,
        ?int $hardcopyId
    ): void {
        if ($softcopyId && $hardcopyId) {
            throw new Problem(
                'A request can reference only one document domain.'
            );
        }

        $isCreate = in_array(
            $type,
            ['softcopy_create', 'hardcopy_create'],
            true
        );

        if ($isCreate && ($softcopyId || $hardcopyId)) {
            throw new Problem(
                'Creation requests must not reference an existing document.'
            );
        }

        if (!$isCreate && !$softcopyId && !$hardcopyId) {
            throw new Problem(
                'Choose an existing target document.'
            );
        }

        $needsSoftcopy =
            str_starts_with($type, 'softcopy_')
            || $type === 'assignment';

        if ($needsSoftcopy && $hardcopyId) {
            throw new Problem(
                'This request requires a softcopy document.'
            );
        }

        $needsHardcopy =
            str_starts_with($type, 'hardcopy_')
            || $type === 'transfer';

        if ($needsHardcopy && $softcopyId) {
            throw new Problem(
                'This request requires a hardcopy document.'
            );
        }
    }

    private function assertRequestAccess(
        string $type,
        string $domain,
        ?int $targetId,
        ?array $document,
        int $ownerId
    ): void {
        $this->ctx->require($domain . '.view');

        if (
            $document
            && $type !== 'access'
            && !$this->documents->canRead(
                $domain,
                (int) $targetId
            )
        ) {
            throw new Problem(
                'You need document access before requesting this operation.',
                403
            );
        }

        $invalidTransferOwner =
            $type === 'transfer'
            && $document
            && (int) $document['holder_id'] !== $ownerId
            && !$this->ctx->can('transfer.manage');

        if ($invalidTransferOwner) {
            throw new Problem(
                'Only the current holder or transfer manager may request a transfer.',
                403
            );
        }
    }

    private function normalizeTransfer(
        array $payload,
        ?int $hardcopyId,
        ?array $document
    ): array {
        $this->documents->noOpenTransfer(
            (int) $hardcopyId
        );

        $data = array_merge(
            $this->documents->physical(
                $payload,
                $hardcopyId
            ),
            [
                'recipient_id' => Rules::id(
                    $payload,
                    'recipient_id'
                ),
                'document_copy_number' => Rules::text(
                    $payload,
                    'document_copy_number',
                    100
                ),
                'sequence_number' => Rules::text(
                    $payload,
                    'sequence_number',
                    100,
                    false
                ),
            ]
        );

        $this->ctx->active(
            'users',
            $data['recipient_id']
        );

        $sameLocation =
            $data['location_id'] ===
            (int) $document['location_id'];

        $sameHolder =
            $data['recipient_id'] ===
            (int) $document['holder_id'];

        if ($sameLocation && $sameHolder) {
            throw new Problem(
                'A transfer must change the location or holder.'
            );
        }

        return $data;
    }

    private function normalizeAccessExpiration(
        array $payload
    ): string {
        $expiration = Rules::date(
            $payload,
            'expiration_date'
        );

        $today = date('Y-m-d');
        $maximum = date(
            'Y-m-d',
            strtotime('+2 years')
        );

        if (
            $expiration < $today
            || $expiration > $maximum
        ) {
            throw new Problem(
                'Access expiry must be today or within the next two years.'
            );
        }

        return $expiration;
    }

    private function normalizeDisposal(
        array $payload,
        string $domain,
        ?int $hardcopyId,
        ?array $document
    ): array {
        $action = Rules::choice(
            $payload,
            'disposal_action',
            ['shred', 'scratch', 'reuse', 'other']
        );

        if ($domain === 'hardcopy') {
            $this->documents->noOpenTransfer(
                (int) $hardcopyId
            );

            $retentionActive =
                (int) $document['retention_enabled']
                && $document['retention_end_date'] >= date('Y-m-d');

            if ($retentionActive) {
                throw new Problem(
                    'The retention period has not ended; this hardcopy cannot be disposed yet.'
                );
            }
        }

        return [
            'disposal_action' => $action,
        ];
    }

    public function submit(array $input): array
    {
        $this->ctx->require('requests.submit');

        $request = $this->model()->lock(
            'requests',
            Rules::id($input),
            Rules::id($input, 'version')
        );

        $canSubmit =
            (int) $request['requested_by'] === $this->ctx->id()
            && in_array(
                $request['status'],
                ['draft', 'returned'],
                true
            );

        if (!$canSubmit) {
            throw new Problem(
                'Only your draft or corrected returned request can be submitted.',
                409
            );
        }

        $this->requireTypePermission(
            $request['type']
        );

        $payload = Rules::json(
            $request['payload']
        );

        $fresh = $this->normalize(
            $request['type'],
            $request['softcopy_id']
                ? (int) $request['softcopy_id']
                : null,
            $request['hardcopy_id']
                ? (int) $request['hardcopy_id']
                : null,
            $payload,
            $this->ctx->id(),
            true
        );

        $this->assertDocumentUnchanged(
            $payload,
            $fresh
        );

        if (
            $request['type'] === 'transfer'
            && $this->model()->competing_transfer(
                [
                    $request['hardcopy_id'],
                    $request['id'],
                ]
            )
        ) {
            throw new Problem(
                'Another transfer request is already in progress.'
            );
        }

        // Ari ta mag-pin sa workflow version para dili maapektuhan ang old request if ma-change ang default.
        $this->workflow->begin(
            $request,
            fn(array $completedRequest) =>
                $this->complete($completedRequest)
        );

        return [
            'id' => (int) $request['id'],
            'message' => 'Request submitted using its pinned workflow version.',
        ];
    }

    private function assertDocumentUnchanged(
        array $old,
        array $fresh
    ): void {
        if (!isset($old['base_document_version'])) {
            return;
        }

        $sameVersion =
            $old['base_document_version'] ===
            ($fresh['base_document_version'] ?? null);

        if (!$sameVersion) {
            throw new Problem(
                'The document changed after this request was prepared. Return it for correction, then edit and resubmit.',
                409
            );
        }
    }

    public function decide(array $input): array
    {
        if (!$this->ctx->id()) {
            throw new Problem(
                'Sign in first.',
                401
            );
        }

        $request = $this->model()->lock(
            'requests',
            Rules::id($input),
            Rules::id($input, 'version')
        );

        $this->workflow->decide(
            $request,
            $input,
            fn(array $completedRequest) =>
                $this->complete($completedRequest)
        );

        return [
            'message' => 'Decision recorded.',
        ];
    }

    private function complete(array $request): void
    {
        $payload = Rules::json(
            $request['payload']
        );

        $type = $request['type'];
        $requestId = (int) $request['id'];
        $ownerId = (int) $request['requested_by'];

        $softcopyId = $request['softcopy_id']
            ? (int) $request['softcopy_id']
            : null;

        $hardcopyId = $request['hardcopy_id']
            ? (int) $request['hardcopy_id']
            : null;

        $fresh = $this->normalize(
            $type,
            $softcopyId,
            $hardcopyId,
            $payload,
            $ownerId,
            false
        );

        $this->assertDocumentUnchanged(
            $payload,
            $fresh
        );

        $domain = $this->domainFor(
            $type,
            $softcopyId,
            $hardcopyId
        );

        $targetId = $softcopyId ?? $hardcopyId;

        [$result, $status, $targetId] =
            $this->applyCompletion(
                $type,
                $domain,
                $targetId,
                $softcopyId,
                $hardcopyId,
                $fresh,
                $payload,
                $requestId,
                $ownerId
            );

        $this->model()->update(
            'requests',
            $requestId,
            [
                'status' => $status,
                'current_node' => null,
                'result' => Context::json($result),
                'completed_at' => $status === 'completed'
                    ? date('Y-m-d H:i:s')
                    : null,
            ]
        );

        $this->ctx->audit(
            $type,
            'applied',
            $targetId,
            null,
            $result,
            $payload['reason'],
            $requestId
        );
    }

    private function applyCompletion(
        string $type,
        string $domain,
        ?int $targetId,
        ?int $softcopyId,
        ?int $hardcopyId,
        array $fresh,
        array $payload,
        int $requestId,
        int $ownerId
    ): array {
        if (
            in_array(
                $type,
                [
                    'softcopy_create',
                    'softcopy_revise',
                    'hardcopy_create',
                    'hardcopy_update',
                ],
                true
            )
        ) {
            return $this->completeDocumentChange(
                $domain,
                $targetId,
                $fresh,
                $requestId,
                $ownerId
            );
        }

        if ($type === 'softcopy_cancel') {
            $this->completeSoftcopyCancel(
                (int) $softcopyId,
                $payload
            );

            return [[], 'completed', $targetId];
        }

        if ($type === 'transfer') {
            $result = $this->completeTransfer(
                (int) $hardcopyId,
                $fresh,
                $requestId
            );

            return [$result, 'approved', $targetId];
        }

        if ($type === 'assignment') {
            $this->completeAssignment(
                (int) $softcopyId,
                $fresh,
                $requestId
            );

            return [[], 'completed', $targetId];
        }

        if ($type === 'access') {
            $result = $this->completeAccess(
                $domain,
                (int) $targetId,
                $fresh,
                $requestId,
                $ownerId
            );

            return [$result, 'completed', $targetId];
        }

        if ($type === 'disposal') {
            $result = $this->completeDisposal(
                $domain,
                (int) $targetId,
                $fresh,
                $requestId
            );

            return [$result, 'completed', $targetId];
        }

        throw new Problem(
            'Unsupported request completion type.',
            409
        );
    }

    private function completeDocumentChange(
        string $domain,
        ?int $targetId,
        array $fresh,
        int $requestId,
        int $ownerId
    ): array {
        $result = $this->documents->apply(
            $domain,
            $fresh,
            $targetId,
            'request',
            $requestId,
            $ownerId
        );

        $targetId = (int) $result['id'];

        $this->model()->update(
            'requests',
            $requestId,
            [
                $domain . '_id' => $targetId,
            ]
        );

        return [
            $result,
            'completed',
            $targetId,
        ];
    }

    private function completeSoftcopyCancel(
        int $softcopyId,
        array $payload
    ): void {
        $document = $this->model()->lock(
            'softcopy_documents',
            $softcopyId
        );

        $this->model()->update(
            'softcopy_documents',
            $softcopyId,
            [
                'status' => 'cancelled',
                'previous_status' => $document['status'],
            ]
        );

        $this->ctx->status(
            'softcopy',
            $softcopyId,
            $document['status'],
            'cancelled',
            'cancelled',
            $payload['reason']
        );
    }

    private function completeTransfer(
        int $hardcopyId,
        array $fresh,
        int $requestId
    ): array {
        $document = $this->model()->lock(
            'hardcopy_documents',
            $hardcopyId
        );

        $transferId = $this->model()->insert(
            'transfers',
            [
                'request_id' => $requestId,
                'hardcopy_id' => $hardcopyId,
                'origin' => Context::json($document),
                'destination' => Context::json($fresh),
                'current_holder_id' => $document['holder_id'],
                'recipient_id' => $fresh['recipient_id'],
                'document_copy_number' => $fresh['document_copy_number'],
                'reason' => $fresh['reason'],
            ]
        );

        $this->ctx->notify(
            (int) $document['holder_id'],
            'Transfer approved',
            'Record the physical delivery before the recipient accepts.',
            $requestId
        );

        return [
            'transfer_id' => $transferId,
        ];
    }

    private function completeAssignment(
        int $softcopyId,
        array $fresh,
        int $requestId
    ): void {
        $this->upsertAssignment(
            $softcopyId,
            $fresh['user_id']
        );

        $this->ctx->notify(
            $fresh['user_id'],
            'Document assigned',
            'A controlled softcopy document has been assigned to you.',
            $requestId
        );
    }

    private function upsertAssignment(
        int $softcopyId,
        int $userId
    ): int {
        $existing =
            $this->model()->assignment_for_update(
                [
                    $softcopyId,
                    $userId,
                ]
            );

        if ($existing) {
            $this->model()->update(
                'assignments',
                (int) $existing['id'],
                [
                    'active' => 1,
                    'assigned_by' =>
                        $this->ctx->id(),
                    'assigned_at' =>
                        date('Y-m-d H:i:s'),
                ]
            );

            return (int) $existing['id'];
        }

        return $this->model()->insert(
            'assignments',
            [
                'softcopy_id' =>
                    $softcopyId,
                'user_id' =>
                    $userId,
                'assigned_by' =>
                    $this->ctx->id(),
            ]
        );
    }

    private function completeAccess(
        string $domain,
        int $targetId,
        array $fresh,
        int $requestId,
        int $ownerId
    ): array {
        $grantId = $this->model()->insert(
            'access_grants',
            [
                'request_id' => $requestId,
                'domain' => $domain,
                'document_id' => $targetId,
                'user_id' => $ownerId,
                'granted_by' => $this->ctx->id(),
                'expires_at' =>
                    $fresh['expiration_date'] . ' 23:59:59',
                'reason' => $fresh['reason'],
            ]
        );

        $this->ctx->notify(
            $ownerId,
            'Access granted',
            'Access expires ' .
                $fresh['expiration_date'] .
                '.',
            $requestId
        );

        return [
            'grant_id' => $grantId,
        ];
    }

    private function completeDisposal(
        string $domain,
        int $targetId,
        array $fresh,
        int $requestId
    ): array {
        $table = Document_service::table($domain);

        $document = $this->model()->lock(
            $table,
            $targetId
        );

        $disposalId = $this->model()->insert(
            'disposals',
            [
                'request_id' => $requestId,
                'domain' => $domain,
                'document_id' => $targetId,
                'previous_status' => $document['status'],
                'previous_state' => Context::json($document),
                'disposal_action' => $fresh['disposal_action'],
                'remarks' => $fresh['reason'],
                'disposed_by' => $this->ctx->id(),
            ]
        );

        $documentUpdate = [
            'previous_status' => $document['status'],
            'status' => 'disposed',
        ];

        if ($domain === 'hardcopy') {
            $documentUpdate['location_id'] = null;
        }

        $this->model()->update(
            $table,
            $targetId,
            $documentUpdate
        );

        $this->ctx->status(
            $domain,
            $targetId,
            $document['status'],
            'disposed',
            'disposed',
            $fresh['reason']
        );

        foreach (
            $this->model()->grants_for_update(
                [$domain, $targetId]
            )
            as $grant
        ) {
            $this->revokeGrantAfterDisposal(
                $grant,
                $requestId
            );
        }

        return [
            'disposal_id' => $disposalId,
        ];
    }

    private function revokeGrantAfterDisposal(
        array $grant,
        int $requestId
    ): void {
        $this->model()->update(
            'access_grants',
            (int) $grant['id'],
            [
                'status' => 'revoked',
                'revoked_at' => date('Y-m-d H:i:s'),
                'revoked_by' => $this->ctx->id(),
            ]
        );

        $this->ctx->notify(
            (int) $grant['user_id'],
            'Access revoked',
            'The document has been disposed.',
            (int) $grant['request_id']
        );

        $this->ctx->audit(
            'access',
            'revoked',
            (int) $grant['id'],
            $grant,
            null,
            'Document disposed',
            $requestId
        );
    }

    public function cancel(array $input): array
    {
        $this->ctx->require('requests.cancel');

        $request = $this->model()->lock(
            'requests',
            Rules::id($input),
            Rules::id($input, 'version')
        );

        $canCancel =
            (int) $request['requested_by'] === $this->ctx->id()
            || $this->ctx->can('requests.manage');

        if (!$canCancel) {
            throw new Problem(
                'Only the requester or request manager can cancel.',
                403
            );
        }

        if (
            !in_array(
                $request['status'],
                ['draft', 'pending', 'returned'],
                true
            )
        ) {
            throw new Problem(
                'This request cannot be cancelled in its current state.',
                409
            );
        }

        $reason = Rules::text(
            $input,
            'reason',
            4000
        );

        foreach (
            $this->model()->pending_candidates(
                [$request['id']]
            )
            as $step
        ) {
            foreach (
                Rules::json($step['candidates'])
                as $user
            ) {
                $this->ctx->notify(
                    (int) $user['id'],
                    'Request cancelled',
                    $request['reference'],
                    (int) $request['id']
                );
            }
        }

        $this->model()->cancel_pending_steps(
            [$request['id']]
        );

        $this->model()->update(
            'requests',
            (int) $request['id'],
            [
                'status' => 'cancelled',
                'current_node' => null,
                'completed_at' => date('Y-m-d H:i:s'),
            ]
        );

        $this->workflow->history(
            (int) $request['id'],
            null,
            'cancelled',
            $request,
            null,
            $reason
        );

        return [
            'message' => 'Request cancelled. History was retained.',
        ];
    }

    public function revoke(array $input): array
    {
        $grant = $this->model()->lock(
            'access_grants',
            Rules::id($input),
            Rules::id($input, 'version')
        );

        $isOwner =
            (int) $grant['user_id'] === $this->ctx->id();

        if (!$isOwner) {
            $this->ctx->require('access.revoke');
        }

        if (!$isOwner && !$this->ctx->id()) {
            throw new Problem(
                'Sign in first.',
                401
            );
        }

        if ($grant['status'] !== 'access_granted') {
            throw new Problem(
                'Access is no longer granted.',
                409
            );
        }

        $reason = Rules::text(
            $input,
            'reason',
            4000
        );

        $status = $isOwner
            ? 'returned'
            : 'revoked';

        $this->model()->update(
            'access_grants',
            (int) $grant['id'],
            [
                'status' => $status,
                'revoked_at' => date('Y-m-d H:i:s'),
                'revoked_by' => $this->ctx->id(),
            ]
        );

        $this->workflow->history(
            (int) $grant['request_id'],
            null,
            'access_' . $status,
            $grant,
            null,
            $reason
        );

        $this->ctx->notify(
            (int) $grant['user_id'],
            'Access ' . $status,
            $reason,
            (int) $grant['request_id']
        );

        return [
            'message' => 'Access ' . $status . '.',
        ];
    }

    public function directAssign(array $input): array
    {
        $this->ctx->require(
            'assignment.manage'
        );
        $this->ctx->require(
            'softcopy.view'
        );

        $softcopyId = Rules::id(
            $input,
            'softcopy_id'
        );

        $userId = Rules::id(
            $input,
            'user_id'
        );

        $reason = Rules::text(
            $input,
            'reason',
            4000,
            false
        );

        $document = $this->model()->lock(
            'softcopy_documents',
            $softcopyId
        );

        if ($document['status'] !== 'active') {
            throw new Problem(
                'Only active softcopy documents can be assigned.'
            );
        }

        $this->ctx->active(
            'users',
            $userId
        );

        $before =
            $this->model()->assignment_for_update(
                [
                    $softcopyId,
                    $userId,
                ]
            );

        $assignmentId =
            $this->upsertAssignment(
                $softcopyId,
                $userId
            );

        $after = $this->model()->lock(
            'assignments',
            $assignmentId
        );

        $this->ctx->audit(
            'assignment',
            'direct_assigned',
            $assignmentId,
            $before,
            $after,
            $reason
        );

        $this->ctx->notify(
            $userId,
            'Document assigned',
            'A controlled softcopy document has been assigned to you.'
        );

        return [
            'id' => $assignmentId,
            'message' =>
                'Document assigned directly without a workflow request.',
        ];
    }

    public function unassign(array $input): array
    {
        $this->ctx->require('assignment.manage');

        $assignment = $this->model()->lock(
            'assignments',
            Rules::id($input),
            Rules::id($input, 'version')
        );

        if (!(int) $assignment['active']) {
            throw new Problem(
                'Assignment is already inactive.',
                409
            );
        }

        $reason = Rules::text(
            $input,
            'reason',
            4000
        );

        $this->model()->update(
            'assignments',
            (int) $assignment['id'],
            ['active' => 0]
        );

        $this->ctx->audit(
            'assignment',
            'removed',
            (int) $assignment['id'],
            $assignment,
            null,
            $reason
        );

        $this->ctx->notify(
            (int) $assignment['user_id'],
            'Assignment removed',
            $reason
        );

        return [
            'message' => 'Assignment removed; its history was retained.',
        ];
    }
}
