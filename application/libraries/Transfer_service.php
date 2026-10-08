<?php
declare(strict_types=1);

use Pk\Core\{Context, Problem, Rules};

class Transfer_service
{
    // Physical handoff ni: dispatch first, recipient confirmation dayon, then update location.
    private Context $ctx;

    public function __construct(Context|array|null $options = null)
    {
        $this->ctx = Context::fromOptions($options);
    }

    private function model(): Transfer_model
    {
        return $this->ctx->model(\Transfer_model::class);
    }

    private function workflow(): Workflow_service
    {
        return new Workflow_service($this->ctx);
    }

    public function direct(array $input): array
    {
        $this->ctx->require(
            'transfer.direct'
        );
        $this->ctx->require(
            'hardcopy.view'
        );

        $hardcopyId = Rules::id(
            $input,
            'hardcopy_id'
        );

        $recipientId = Rules::id(
            $input,
            'recipient_id'
        );

        $document = $this->model()->lock_hardcopy(
            $hardcopyId
        );

        if ($document['status'] !== 'active') {
            throw new Problem(
                'Only active hardcopy documents can be transferred directly.'
            );
        }

        $documents = new Document_service(
            $this->ctx
        );

        $documents->noOpenTransfer(
            $hardcopyId
        );

        $recipient = $this->ctx->active(
            'users',
            $recipientId
        );

        $physical = $documents->physical(
            $input,
            $hardcopyId
        );

        $sequenceNumber = Rules::text(
            $input,
            'sequence_number',
            100,
            false
        );

        $copyNumber = Rules::text(
            $input,
            'document_copy_number',
            100
        );

        $remarks = Rules::text(
            $input,
            'reason',
            4000,
            false
        );

        $sameLocation =
            (int) $physical['location_id'] ===
            (int) $document['location_id'];

        $sameHolder =
            $recipientId ===
            (int) $document['holder_id'];

        if ($sameLocation && $sameHolder) {
            throw new Problem(
                'A direct transfer must change the location or holder.'
            );
        }

        $destination = array_merge(
            $physical,
            [
                'recipient_id' => $recipientId,
                'document_copy_number' =>
                    $copyNumber,
                'sequence_number' =>
                    $sequenceNumber,
                'reason' => $remarks,
            ]
        );

        $transferId = $this->model()->insert(
            'transfers',
            [
                'request_id' => null,
                'hardcopy_id' => $hardcopyId,
                'origin' =>
                    Context::json($document),
                'destination' =>
                    Context::json($destination),
                'current_holder_id' =>
                    $document['holder_id'],
                'recipient_id' => $recipientId,
                'document_copy_number' =>
                    $copyNumber,
                'reason' => $remarks ?? '',
                'comments' => $remarks,
                'status' => 'completed',
                'recipient_status' => 'direct',
                'transferred_by' =>
                    $this->ctx->id(),
                'transferred_at' =>
                    date('Y-m-d H:i:s'),
                'accepted_at' =>
                    date('Y-m-d H:i:s'),
            ]
        );

        $this->model()->update(
            'hardcopy_documents',
            $hardcopyId,
            array_merge(
                $physical,
                [
                    'holder_id' => $recipientId,
                    'sequence_number' =>
                        $sequenceNumber ??
                        $document[
                            'sequence_number'
                        ],
                ]
            )
        );

        $after = $this->model()->lock_hardcopy(
            $hardcopyId
        );

        $this->ctx->status(
            'hardcopy',
            $hardcopyId,
            $document['status'],
            $document['status'],
            'direct_transfer',
            $remarks ?? ''
        );

        $this->ctx->audit(
            'transfer',
            'direct_completed',
            $transferId,
            $document,
            $after,
            $remarks
        );

        $this->ctx->notify(
            $recipientId,
            'Document transferred to you',
            'A hardcopy document was transferred directly to you.'
        );

        if (
            (int) $document['holder_id'] !==
            $recipientId
        ) {
            $this->ctx->notify(
                (int) $document['holder_id'],
                'Document transferred',
                'The hardcopy document is now assigned to ' .
                    Context::name($recipient) .
                    '.'
            );
        }

        return [
            'id' => $transferId,
            'message' =>
                'Hardcopy transferred directly without a workflow request.',
        ];
    }

    public function dispatch(array $input): array
    {
        $this->ctx->require('transfer.view');

        $transfer = $this->model()->lock_transfer(
            Rules::id($input),
            Rules::id($input, 'version')
        );

        $this->assertSenderCanManage($transfer);

        if ($transfer['status'] !== 'for_transfer') {
            throw new Problem(
                'This transfer is not awaiting dispatch.',
                409
            );
        }

        $document = $this->model()->lock_hardcopy(
            (int) $transfer['hardcopy_id']
        );

        $this->assertPhysicalRecordUnchanged(
            $transfer,
            $document
        );

        $comments = Rules::text(
            $input,
            'comments',
            4000,
            false
        );

        $this->model()->update(
            'transfers',
            (int) $transfer['id'],
            [
                'status' => 'pending_recipient_acceptance',
                'transferred_by' => $this->ctx->id(),
                'transferred_at' => date('Y-m-d H:i:s'),
                'comments' => $comments,
            ]
        );

        // Dili pa nato usbon ang recorded location until ang recipient mo-confirm.
        $this->workflow()->history(
            (int) $transfer['request_id'],
            null,
            'physically_transferred',
            $transfer,
            ['status' => 'pending_recipient_acceptance'],
            $comments
        );

        $this->ctx->notify(
            (int) $transfer['recipient_id'],
            'Receipt confirmation required',
            'Confirm acceptance or refusal of the physical document.',
            (int) $transfer['request_id']
        );

        return [
            'message' => 'Delivery recorded. Current location is unchanged until the recipient accepts.',
        ];
    }

    public function receive(array $input): array
    {
        $this->ctx->require('transfer.view');

        $transfer = $this->model()->lock_transfer(
            Rules::id($input),
            Rules::id($input, 'version')
        );

        if ((int) $transfer['recipient_id'] !== $this->ctx->id()) {
            throw new Problem(
                'Only the named recipient can accept or refuse this transfer.',
                403
            );
        }

        $waiting =
            $transfer['status'] === 'pending_recipient_acceptance'
            && $transfer['recipient_status'] === 'pending';

        if (!$waiting) {
            throw new Problem(
                'The transfer is no longer awaiting acceptance.',
                409
            );
        }

        $decision = Rules::choice(
            $input,
            'decision',
            ['accepted', 'refused']
        );

        $comments = Rules::text(
            $input,
            'comments',
            4000,
            false
        );

        $document = $this->model()->lock_hardcopy(
            (int) $transfer['hardcopy_id']
        );

        $this->assertPhysicalRecordUnchanged(
            $transfer,
            $document
        );

        if ($decision === 'accepted') {
            $this->applyAcceptedDestination(
                $transfer,
                $document,
                $comments
            );
        }

        $status = $decision === 'accepted'
            ? 'completed'
            : 'returned';

        $this->model()->update(
            'transfers',
            (int) $transfer['id'],
            [
                'status' => $status,
                'recipient_status' => $decision,
                'accepted_by' => $this->ctx->id(),
                'accepted_at' => date('Y-m-d H:i:s'),
                'comments' => $comments,
            ]
        );

        $this->model()->update(
            'requests',
            (int) $transfer['request_id'],
            [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
                'result' => Context::json(
                    [
                        'transfer_id' => (int) $transfer['id'],
                        'receipt' => $decision,
                    ]
                ),
            ]
        );

        $this->workflow()->history(
            (int) $transfer['request_id'],
            null,
            'recipient_' . $decision,
            $transfer,
            ['status' => $status],
            $comments
        );

        $this->ctx->notify(
            (int) $transfer['current_holder_id'],
            'Transfer ' . $status,
            $comments ?? '',
            (int) $transfer['request_id']
        );

        return [
            'message' => $decision === 'accepted'
                ? 'Receipt accepted. The current holder and location have been updated.'
                : 'Receipt refused. The original holder and location remain unchanged; arrange the physical return.',
        ];
    }

    private function applyAcceptedDestination(
        array $transfer,
        array $document,
        ?string $comments
    ): void {
        $destination = Rules::json(
            $transfer['destination']
        );

        $physical = (new Document_service($this->ctx))
            ->physical(
                $destination,
                (int) $document['id']
            );

        $this->model()->update(
            'hardcopy_documents',
            (int) $document['id'],
            array_merge(
                $physical,
                [
                    'holder_id' => $this->ctx->id(),
                    'sequence_number' =>
                        $destination['sequence_number']
                        ?? $document['sequence_number'],
                ]
            )
        );

        $this->ctx->status(
            'hardcopy',
            (int) $document['id'],
            $document['status'],
            $document['status'],
            'transfer_received',
            $comments ?? ''
        );

        $this->ctx->audit(
            'hardcopy',
            'location_changed',
            (int) $document['id'],
            $document,
            $physical,
            $comments ?? '',
            (int) $transfer['request_id']
        );
    }

    public function cancel(array $input): array
    {
        $this->ctx->require('transfer.view');

        $transfer = $this->model()->lock_transfer(
            Rules::id($input),
            Rules::id($input, 'version')
        );

        $this->assertSenderCanManage($transfer);

        if ($transfer['status'] !== 'for_transfer') {
            throw new Problem(
                'Only an undispatched transfer can be cancelled. A delivered copy requires recipient confirmation.',
                409
            );
        }

        $reason = Rules::text(
            $input,
            'reason',
            4000,
            false
        );

        $this->model()->update(
            'transfers',
            (int) $transfer['id'],
            [
                'status' => 'cancelled',
                'comments' => $reason,
            ]
        );

        $this->model()->update(
            'requests',
            (int) $transfer['request_id'],
            [
                'status' => 'cancelled',
                'completed_at' => date('Y-m-d H:i:s'),
            ]
        );

        $this->workflow()->history(
            (int) $transfer['request_id'],
            null,
            'transfer_cancelled',
            $transfer,
            null,
            $reason
        );

        $this->ctx->notify(
            (int) $transfer['recipient_id'],
            'Transfer cancelled',
            $reason ?? '',
            (int) $transfer['request_id']
        );

        return [
            'message' => 'Undispatched transfer cancelled.',
        ];
    }

    private function assertSenderCanManage(array $transfer): void
    {
        $isHolder =
            (int) $transfer['current_holder_id']
            === $this->ctx->id();

        if (!$isHolder && !$this->ctx->can('transfer.manage')) {
            throw new Problem(
                'Only the current holder or transfer manager can manage this transfer.',
                403
            );
        }
    }

    private function assertPhysicalRecordUnchanged(
        array $transfer,
        array $document
    ): void {
        $origin = Rules::json(
            $transfer['origin']
        );

        $unchanged =
            $document['status'] === 'active'
            && (int) $document['version']
                === (int) $origin['version']
            && (int) $document['location_id']
                === (int) $origin['location_id']
            && (int) $document['holder_id']
                === (int) $origin['holder_id'];

        if (!$unchanged) {
            throw new Problem(
                'The physical record changed after approval. Resolve this transfer before moving the document.',
                409
            );
        }
    }
}
