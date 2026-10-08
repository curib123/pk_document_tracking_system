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
