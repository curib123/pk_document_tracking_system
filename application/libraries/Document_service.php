<?php
declare(strict_types=1);

use Pk\Core\{Context, Problem, Rules};

class Document_service
{
    // Kani nga service nag-manage sa document rules; DB details naa sa model para clean ang flow.
    private Context $ctx;

    public function __construct(Context|array|null $options = null)
    {
        $this->ctx = Context::fromOptions($options);
    }

    private function model(): Document_model
    {
        return $this->ctx->model(\Document_model::class);
    }

    public static function table(string $domain): string
    {
        return match ($domain) {
            'softcopy' => 'softcopy_documents',
            'hardcopy' => 'hardcopy_documents',
            default => throw new Problem('Invalid document domain.'),
        };
    }

    public function direct(string $domain, array $input): array
    {
        $this->ctx->require($domain . '.direct');

        $id = Rules::id(
            $input,
            'id',
            false
        );

        if ($id) {
            // Ari ta mag-lock first para dili ma-overwrite ang newer edit.
            $this->model()->lock(
                self::table($domain),
                $id,
                Rules::id($input, 'version')
            );
        }

        $data = $this->validate(
            $domain,
            $input,
            $id,
            $this->ctx->id()
        );

        return $this->apply(
            $domain,
            $data,
            $id,
            'direct',
            null,
            $this->ctx->id()
        );
    }

    public function validate(
        string $domain,
        array $input,
        ?int $id,
        int $ownerId
    ): array {
        $data = [
            'title' => Rules::text($input, 'title'),
            'reason' => Rules::text(
                $input,
                'reason',
                4000
            ),
        ];

        $old = $id
            ? $this->model()->lock(
                self::table($domain),
                $id
            )
            : null;

        if ($old && $old['status'] !== 'active') {
            throw new Problem(
                'Only active documents can be changed.'
            );
        }

        if ($domain === 'softcopy') {
            return array_merge(
                $data,
                $this->validateSoftcopy(
                    $input,
                    $old,
                    $ownerId
                )
            );
        }

        if ($domain === 'hardcopy') {
            return array_merge(
                $data,
                $this->validateHardcopy(
                    $input,
                    $old,
                    $id,
                    $ownerId
                )
            );
        }

        throw new Problem('Invalid document domain.');
    }

    private function validateSoftcopy(
        array $input,
        ?array $old,
        int $ownerId
    ): array {
        $categoryId = Rules::id(
            $input,
            'category_id'
        );

        $this->ctx->active(
            'categories',
            $categoryId
        );

        $fileId = Rules::id(
            $input,
            'file_id'
        );

        $this->availableFile(
            $fileId,
            $ownerId
        );

        $pageNumber = Rules::id(
            $input,
            'page_number'
        );

        if ($pageNumber > 100000) {
            throw new Problem(
                'Invalid page count.'
            );
        }

        $dateReceived = Rules::date(
            [
                'date_received' =>
                    ($input['date_received'] ?? '')
                    ?: date('Y-m-d'),
            ],
            'date_received'
        );

        $dateReleased = Rules::date(
            [
                'date_released' =>
                    ($input['date_released'] ?? '')
                    ?: date('Y-m-d'),
            ],
            'date_released'
        );

        if ($dateReleased < $dateReceived) {
            throw new Problem(
                'Release date cannot precede receipt date.'
            );
        }

        return [
            'category_id' => $categoryId,
            'document_number' =>
                $old['document_number']
                ?? Rules::text(
                    $input,
                    'document_number',
                    100,
                    false
                ),
            'series_number' => Rules::text(
                $input,
                'series_number',
                100,
                false
            ),
            'file_id' => $fileId,
            'effective_date' => Rules::date(
                $input,
                'effective_date'
            ),
            'page_number' => $pageNumber,
            'new_revision_level' => Rules::text(
                $input,
                'new_revision_level',
                30,
                false
            ),
            'date_received' => $dateReceived,
            'date_released' => $dateReleased,
        ];
    }

    private function validateHardcopy(
        array $input,
        ?array $old,
        ?int $id,
        int $ownerId
    ): array {
        $data = $this->physical(
            $input,
            $id
        );

        $mayChooseHolder =
            $this->ctx->can('hardcopy.direct')
            || $this->ctx->can('requests.manage');

        $defaultHolder =
            $old['holder_id']
            ?? $ownerId;

        $holderId = Rules::id(
            [
                'holder_id' =>
                    $mayChooseHolder
                        ? (
                            $input['holder_id']
                            ?? $defaultHolder
                        )
                        : $defaultHolder,
            ],
            'holder_id'
        );

        $this->ctx->active(
            'users',
            $holderId
        );

        $retentionEnabled = Rules::boolean(
            $input['retention_enabled'] ?? 0
        );

        $retentionStart = $retentionEnabled
            ? Rules::date(
                $input,
                'retention_start_date'
            )
            : null;

        $retentionEnd = $retentionEnabled
            ? Rules::date(
                $input,
                'retention_end_date'
            )
            : null;

        if (
            $retentionEnabled
            && $retentionEnd < $retentionStart
        ) {
            throw new Problem(
                'Retention end date cannot precede its start.'
            );
        }

        if ($old) {
            $locationChanged =
                (int) $old['location_id']
                !== $data['location_id'];

            $holderChanged =
                (int) $old['holder_id']
                !== $holderId;

            if ($locationChanged || $holderChanged) {
                throw new Problem(
                    'Use a Transfer request to change location or holder; recipient acceptance is required.'
                );
            }
        }

        if ($id) {
            $this->noOpenTransfer($id);
        }

        return array_merge(
            $data,
            [
                'holder_id' => $holderId,
                'sequence_number' => Rules::text(
                    $input,
                    'sequence_number',
                    100,
                    false
                ),
                'retention_enabled' => $retentionEnabled,
                'retention_start_date' => $retentionStart,
                'retention_end_date' => $retentionEnd,
            ]
        );
    }

    public function physical(
        array $input,
        ?int $documentId = null
    ): array {
        $locationId = Rules::id(
            $input,
            'location_id'
        );

        $location = $this->ctx->active(
            'locations',
            $locationId
        );

        // Optional levels may be omitted. The selected predefined location
        // supplies any hierarchy values it already knows.
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

        $locationAssetId =
            $location['asset_id'] !== null
                ? (int) $location['asset_id']
                : null;

        $locationSpecificId =
            $location['specific_id'] !== null
                ? (int) $location['specific_id']
                : null;

        $locationAreaId =
            $location['area_id'] !== null
                ? (int) $location['area_id']
                : null;

        if (
            $assetId !== null &&
            $locationAssetId !== null &&
            $assetId !== $locationAssetId
        ) {
            throw new Problem(
                'Selected Asset Number does not match the predefined Location.'
            );
        }

        if (
            $specificId !== null &&
            $locationSpecificId !== null &&
            $specificId !== $locationSpecificId
        ) {
            throw new Problem(
                'Selected Specific does not match the predefined Location.'
            );
        }

        if (
            $areaId !== null &&
            $locationAreaId !== null &&
            $areaId !== $locationAreaId
        ) {
            throw new Problem(
                'Selected Area does not match the predefined Location.'
            );
        }

        $assetId ??= $locationAssetId;
        $specificId ??= $locationSpecificId;
        $areaId ??= $locationAreaId;

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

        $occupied = $this->model()->location_occupant(
            [$locationId]
        );

        if (
            $occupied &&
            (int) $occupied['id'] !== $documentId
        ) {
            throw new Problem(
                'That dedicated location is already assigned to another hardcopy.'
            );
        }

        return [
            'area_id' => $areaId,
            'specific_id' => $specificId,
            'asset_id' => $assetId,
            'location_id' => $locationId,
        ];
    }

    public function availableFile(
        int $id,
        int $ownerId
    ): array {
        $file = $this->model()->lock(
            'files',
            $id
        );

        $available =
            (int) $file['uploaded_by'] === $ownerId
            && $file['document_id'] === null
            && $file['purpose'] === 'upload'
            && $file['status'] === 'pending';

        if (!$available) {
            throw new Problem(
                'Choose a new, unassigned file uploaded by the requester.'
            );
        }

        return $file;
    }

    public function apply(
        string $domain,
        array $data,
        ?int $id,
        string $source,
        ?int $requestId,
        int $ownerId
    ): array {
        $table = self::table($domain);

        $before = $id
            ? $this->model()->lock(
                $table,
                $id
            )
            : null;

        $values = $this->documentValues(
            $domain,
            $data
        );

        if (!$id) {
            if (
                $domain === 'softcopy'
                && !$values['document_number']
            ) {
                $values['document_number'] =
                    $this->ctx->sequence(
                        'document_' . date('Y'),
                        'DOC-' . date('Y') . '-'
                    );
            }

            $id = $this->model()->insert(
                $table,
                array_merge(
                    $values,
                    [
                        'created_by' => $ownerId,
                        'creation_source' => $source,
                        'creation_reason' => $data['reason'],
                        'source_request_id' => $requestId,
                    ]
                )
            );

            $this->ctx->status(
                $domain,
                $id,
                '',
                'active',
                'created',
                $data['reason']
            );
        } else {
            $this->model()->update(
                $table,
                $id,
                $values
            );
        }

        if ($domain === 'softcopy') {
            $this->revision(
                $id,
                $data,
                $ownerId
            );
        }

        $this->ctx->audit(
            $domain,
            $before ? 'updated' : 'created',
            $id,
            $before,
            $values,
            $data['reason'],
            $requestId
        );

        if ($before) {
            $this->ctx->status(
                $domain,
                $id,
                $before['status'],
                $before['status'],
                $domain === 'softcopy'
                    ? 'revised'
                    : 'updated',
                $data['reason']
            );
        }

        return [
            'id' => $id,
            'domain' => $domain,
        ];
    }

    private function documentValues(
        string $domain,
        array $data
    ): array {
        $keys = $domain === 'softcopy'
            ? [
                'title',
                'category_id',
                'document_number',
                'series_number',
            ]
            : [
                'title',
                'area_id',
                'specific_id',
                'asset_id',
                'location_id',
                'holder_id',
                'sequence_number',
                'retention_enabled',
                'retention_start_date',
                'retention_end_date',
            ];

        return array_intersect_key(
            $data,
            array_flip($keys)
        );
    }

    private function revision(
        int $documentId,
        array $data,
        int $ownerId
    ): void {
        $document = $this->model()->lock(
            'softcopy_documents',
            $documentId
        );

        $previous = $document['current_revision_id']
            ? $this->model()->row(
                'softcopy_revisions',
                (int) $document['current_revision_id']
            )
            : null;

        $revisionNumber = $previous
            ? (int) $previous['revision_number'] + 1
            : 0;

        $file = $this->availableFile(
            $data['file_id'],
            $ownerId
        );

        $revisionId = $this->model()->insert(
            'softcopy_revisions',
            [
                'document_id' => $documentId,
                'revision_number' => $revisionNumber,
                'reason' => $data['reason'],
                'effective_date' => $data['effective_date'],
                'page_number' => $data['page_number'],
                'series_number' => $data['series_number'],
                'document_title' => $data['title'],
                'previous_revision_level' =>
                    $previous['new_revision_level'] ?? null,
                'new_revision_level' =>
                    $data['new_revision_level']
                    ?? (string) $revisionNumber,
                'previous_effective_date' =>
                    $previous['effective_date'] ?? null,
                'new_effective_date' => $data['effective_date'],
                'date_received' => $data['date_received'],
                'date_released' => $data['date_released'],
                'approval_date' => date('Y-m-d'),
                'file_id' => $file['id'],
                'uploaded_by' => $ownerId,
                'approved_by' => $this->ctx->id(),
            ]
        );

        $this->model()->update(
            'files',
            (int) $file['id'],
            [
                'purpose' => 'revision',
                'domain' => 'softcopy',
                'document_id' => $documentId,
                'status' => 'approved',
                'approved_by' => $this->ctx->id(),
                'approved_at' => date('Y-m-d H:i:s'),
            ]
        );

        // Kani nga pointer mao ra ang source of truth sa current revision, para dili double-current.
        $this->model()->update(
            'softcopy_documents',
            $documentId,
            ['current_revision_id' => $revisionId]
        );
    }

    public function canRead(
        string $domain,
        int $id
    ): bool {
        // Same access rules as document lists; no implicit creator bypass.
        return $this->ctx->model(Document_visibility_model::class)
            ->canRead($domain, $id);
    }

    public function noOpenTransfer(int $id): void
    {
        if ($this->model()->open_transfer([$id])) {
            throw new Problem(
                'Finish or cancel the open physical transfer first.'
            );
        }
    }
}
