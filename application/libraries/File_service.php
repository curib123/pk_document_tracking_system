<?php
declare(strict_types=1);

use Pk\Core\{Context, Problem, Rules};

class File_service
{
    // File rules diri: validate -> save privately -> authorize -> audit.
    private const MAX_UPLOAD_BYTES = 20 * 1024 * 1024;
    private const MAX_OFFICE_UNPACKED_BYTES = 100 * 1024 * 1024;

    private Context $ctx;

    public function __construct(Context|array|null $options = null)
    {
        $this->ctx = Context::fromOptions($options);
    }

    private function model(): File_model
    {
        return $this->ctx->model(\File_model::class);
    }

    private function directory(): string
    {
        $path = PK_ROOT . '/storage/files';

        if (
            !is_dir($path)
            && !mkdir($path, 0700, true)
            && !is_dir($path)
        ) {
            throw new Problem(
                'Private storage is not writable.',
                503
            );
        }

        return $path;
    }

    public function upload(array $upload): array
    {
        $this->ctx->require('files.upload');

        [
            $name,
            $size,
            $mime,
            $extension,
        ] = $this->validateUpload($upload);

        if (in_array($extension, ['docx', 'xlsx'], true)) {
            $this->validateOfficeArchive(
                $upload['tmp_name'],
                $extension
            );
        }

        $storageName =
            bin2hex(random_bytes(24))
            . '.'
            . $extension;

        $path =
            $this->directory()
            . '/'
            . $storageName;

        if (!move_uploaded_file($upload['tmp_name'], $path)) {
            throw new Problem(
                'Could not save the uploaded file.',
                503
            );
        }

        chmod($path, 0600);

        try {
            $id = $this->model()->insert(
                'files',
                [
                    'original_name' => $name,
                    'storage_name' => $storageName,
                    'size' => $size,
                    'mime_type' => $mime,
                    'fingerprint' => hash_file('sha256', $path),
                    'extension' => $extension,
                    'uploaded_by' => $this->ctx->id(),
                ]
            );

            $this->ctx->audit(
                'files',
                'uploaded',
                $id,
                null,
                [
                    'name' => $name,
                    'size' => $size,
                    'mime' => $mime,
                ]
            );

            return [
                'id' => $id,
                'name' => $name,
            ];
        } catch (\Throwable $e) {
            if (is_file($path)) {
                unlink($path);
            }

            throw $e;
        }
    }

    private function validateUpload(array $upload): array
    {
        $validUpload =
            ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && is_uploaded_file($upload['tmp_name'] ?? '');

        if (!$validUpload) {
            throw new Problem(
                'Upload failed. Check the selected file and server upload limit.'
            );
        }

        $size = filesize($upload['tmp_name']);

        if (
            !$size
            || $size > self::MAX_UPLOAD_BYTES
        ) {
            throw new Problem(
                'The file is empty or exceeds the configured upload limit.'
            );
        }

        $name = basename(
            str_replace(
                '\\',
                '/',
                (string) $upload['name']
            )
        );

        $invalidName =
            strlen($name) > 240
            || preg_match(
                '/[\x00-\x1F\x7F]/',
                $name
            );

        if ($invalidName) {
            throw new Problem(
                'Invalid file name.'
            );
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))
            ->file($upload['tmp_name']);

        $extension = Rules::fileType(
            $name,
            $mime
        );

        return [
            $name,
            (int) $size,
            $mime,
            $extension,
        ];
    }

    private function validateOfficeArchive(
        string $path,
        string $extension
    ): void {
        if (!class_exists(\ZipArchive::class)) {
            throw new Problem(
                'The PHP ZIP extension is required for Office documents.',
                503
            );
        }

        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            throw new Problem(
                'Invalid Office file.'
            );
        }

        try {
            $requiredEntry = $extension === 'docx'
                ? 'word/document.xml'
                : 'xl/workbook.xml';

            $validStructure =
                $zip->numFiles <= 4096
                && $zip->locateName('[Content_Types].xml') !== false
                && $zip->locateName($requiredEntry) !== false;

            if (!$validStructure) {
                throw new Problem(
                    'The archive is not a valid supported Office document.'
                );
            }

            $totalBytes = 0;

            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $entry = $zip->statIndex($index);
                $totalBytes += (int) $entry['size'];

                $unsafe =
                    $totalBytes > self::MAX_OFFICE_UNPACKED_BYTES
                    || stripos($entry['name'], 'vbaProject') !== false
                    || str_contains($entry['name'], '../');

                if ($unsafe) {
                    throw new Problem(
                        'Unsafe or oversized Office archive.'
                    );
                }
            }
        } finally {
            $zip->close();
        }
    }

    public function attach(array $input): array
    {
        $this->ctx->require('files.upload');

        $domain = Rules::choice(
            $input,
            'domain',
            ['softcopy', 'hardcopy']
        );

        $documentId = Rules::id(
            $input,
            'document_id'
        );

        $document = $this->model()->lock(
            Document_service::table($domain),
            $documentId
        );

        $documents = new Document_service(
            $this->ctx
        );

        if (
            $document['status'] !== 'active'
            || !$documents->canRead(
                $domain,
                $documentId
            )
        ) {
            throw new Problem(
                'You cannot attach files to this document.',
                403
            );
        }

        $file = $documents->availableFile(
            Rules::id($input, 'file_id'),
            $this->ctx->id()
        );

        $this->model()->update(
            'files',
            (int) $file['id'],
            [
                'purpose' => 'attachment',
                'domain' => $domain,
                'document_id' => $documentId,
            ]
        );

        $this->ctx->audit(
            'files',
            'attachment_submitted',
            (int) $file['id'],
            null,
            [
                'domain' => $domain,
                'document_id' => $documentId,
            ],
            Rules::text(
                $input,
                'reason',
                2000
            )
        );

        foreach (
            $this->model()->attachment_approvers([])
            as $approver
        ) {
            $this->ctx->notify(
                (int) $approver['id'],
                'Attachment approval required',
                $file['original_name']
            );
        }

        return [
            'message' => 'Attachment submitted for approval.',
        ];
    }

    public function decide(array $input): array
    {
        $file = $this->model()->lock(
            'files',
            Rules::id($input),
            Rules::id($input, 'version')
        );

        if (
            $file['purpose'] !== 'attachment'
            || $file['status'] !== 'pending'
        ) {
            throw new Problem(
                'Only pending attachments can be decided.',
                409
            );
        }

        $decision = Rules::choice(
            $input,
            'decision',
            ['approved', 'rejected', 'cancelled']
        );

        $this->requireAttachmentDecisionPermission(
            $file,
            $decision
        );

        $reason = Rules::text(
            $input,
            'reason',
            4000
        );

        $data = [
            'status' => $decision,
        ];

        if ($decision === 'approved') {
            $data['approved_by'] = $this->ctx->id();
            $data['approved_at'] = date('Y-m-d H:i:s');
        } elseif ($decision === 'rejected') {
            $data['rejected_by'] = $this->ctx->id();
            $data['rejected_at'] = date('Y-m-d H:i:s');
            $data['rejection_reason'] = $reason;
        }

        $this->model()->update(
            'files',
            (int) $file['id'],
            $data
        );

        $this->ctx->audit(
            'files',
            'attachment_' . $decision,
            (int) $file['id'],
            $file,
            $data,
            $reason
        );

        $this->ctx->notify(
            (int) $file['uploaded_by'],
            'Attachment ' . $decision,
            $file['original_name'] . ' — ' . $reason
        );

        return [
            'message' => 'Attachment ' . $decision . '.',
        ];
    }

    private function requireAttachmentDecisionPermission(
        array $file,
        string $decision
    ): void {
        $ownCancellation =
            $decision === 'cancelled'
            && (int) $file['uploaded_by'] === $this->ctx->id();

        if (!$ownCancellation) {
            $this->ctx->require('files.approve');
        }
    }

    public function download(int $id): array
    {
        $file = $this->model()->row(
            'files',
            $id
        );

        if (!$this->canDownload($file, $id)) {
            throw new Problem(
                'Document access is required or has expired/revoked.',
                403
            );
        }

        $this->assertArtifactStillValid(
            $file,
            $id
        );

        $path = $this->path($file);

        $this->ctx->audit(
            'files',
            'downloaded',
            $id,
            null,
            [
                'name' => $file['original_name'],
                'fingerprint' => $file['fingerprint'],
            ]
        );

        return [
            'path' => $path,
            'name' => $file['original_name'],
            'mime' => $file['mime_type'],
            'size' => (int) $file['size'],
        ];
    }

    private function canDownload(
        array $file,
        int $id
    ): bool {
        if (!$file['document_id']) {
            return
                (int) $file['uploaded_by'] === $this->ctx->id()
                || $this->canReview($id);
        }

        if ($file['status'] === 'approved') {
            return (new Document_service($this->ctx))
                ->canRead(
                    $file['domain'],
                    (int) $file['document_id']
                );
        }

        return
            (int) $file['uploaded_by'] === $this->ctx->id()
            || $this->ctx->can('files.approve');
    }

    private function assertArtifactStillValid(
        array $file,
        int $id
    ): void {
        if ($file['purpose'] !== 'artifact') {
            return;
        }

        $artifact = $this->model()->artifact_revision_state(
            [$id]
        );

        $invalidControlledArtifact =
            $artifact
            && $artifact['artifact_type'] === 'controlled'
            && (
                (int) $artifact['revision_id']
                    !== (int) $artifact['current_revision_id']
                || $artifact['status'] !== 'active'
            );

        if ($invalidControlledArtifact) {
            throw new Problem(
                'This controlled artifact is superseded or inactive. Generate an uncontrolled historical copy instead.',
                409
            );
        }
    }

    private function canReview(int $fileId): bool
    {
        // Workflow assignment na ang authority diri; wala nay old requests.approve gate.
        foreach (
            $this->model()->pending_file_reviewers([$fileId])
            as $row
        ) {
            foreach (
                Rules::json($row['candidates'])
                as $candidate
            ) {
                if (
                    (int) $candidate['id']
                    === $this->ctx->id()
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function path(array $file): string
    {
        if (
            !preg_match(
                '/^[a-f0-9]{48}\.[a-z0-9]+$/',
                $file['storage_name']
            )
        ) {
            throw new Problem(
                'Invalid private storage record.',
                500
            );
        }

        $path =
            $this->directory()
            . '/'
            . $file['storage_name'];

        if (!is_file($path)) {
            throw new Problem(
                'The stored file is missing. Contact the administrator.',
                404
            );
        }

        if (
            !hash_equals(
                $file['fingerprint'],
                hash_file('sha256', $path)
            )
        ) {
            throw new Problem(
                'Stored file integrity check failed. Download blocked.',
                409
            );
        }

        return $path;
    }

    public function artifact(array $input): array
    {
        $this->ctx->require('files.generate');

        $revision = $this->model()->row(
            'softcopy_revisions',
            Rules::id($input, 'revision_id')
        );

        $document = $this->model()->lock(
            'softcopy_documents',
            (int) $revision['document_id']
        );

        if (
            !(new Document_service($this->ctx))
                ->canRead(
                    'softcopy',
                    (int) $document['id']
                )
        ) {
            throw new Problem(
                'You do not have access to this revision.',
                403
            );
        }

        $type = Rules::choice(
            $input,
            'artifact_type',
            ['controlled', 'uncontrolled']
        );

        $this->assertArtifactTypeAllowed(
            $document,
            $revision,
            $type
        );

        $source = $this->model()->row(
            'files',
            (int) $revision['file_id']
        );

        $sourcePath = $this->path($source);

        $existing = $this->model()->matching_artifact(
            [
                $revision['id'],
                $type,
                $source['fingerprint'],
            ]
        );

        if ($existing) {
            return [
                'id' => (int) $existing['id'],
                'file_id' => (int) $existing['file_id'],
                'message' => 'Existing artifact returned; source fingerprint is unchanged.',
            ];
        }

        $this->assertArtifactDependencies(
            $source
        );

        return $this->generateArtifact(
            $document,
            $revision,
            $source,
            $sourcePath,
            $type
        );
    }

    private function assertArtifactTypeAllowed(
        array $document,
        array $revision,
        string $type
    ): void {
        $current =
            (int) $document['current_revision_id']
            === (int) $revision['id'];

        if (
            $type === 'controlled'
            && (!$current || $document['status'] !== 'active')
        ) {
            throw new Problem(
                'Only the active current revision may produce a controlled copy.'
            );
        }
    }

    private function assertArtifactDependencies(array $source): void
    {
        if (!class_exists(\setasign\Fpdi\Fpdi::class)) {
            throw new Problem(
                'Install Composer dependencies to generate PDF artifacts.',
                503
            );
        }

        if (
            !in_array(
                $source['extension'],
                ['pdf', 'docx', 'xlsx'],
                true
            )
        ) {
            throw new Problem(
                'Artifact generation supports PDF, DOCX and XLSX revisions.'
            );
        }
    }

    private function generateArtifact(
        array $document,
        array $revision,
        array $source,
        string $sourcePath,
        string $type
    ): array {
        $temporaryFiles = [];
        $artifactPath = null;

        try {
            if ($source['extension'] !== 'pdf') {
                [
                    $sourcePath,
                    $temporaryFiles,
                ] = $this->convert($sourcePath);
            }

            $artifactPath = $this->buildArtifactPdf(
                $sourcePath,
                $document,
                $revision,
                $type
            );

            return $this->storeArtifact(
                $artifactPath,
                $document,
                $revision,
                $source,
                $type
            );
        } catch (Problem $e) {
            throw $e;
        } catch (\Throwable $e) {
            if (
                $artifactPath
                && is_file($artifactPath)
            ) {
                unlink($artifactPath);
            }

            error_log(
                'Artifact generation: '
                . $e->getMessage()
            );

            throw new Problem(
                'PDF generation failed. Encrypted or unsupported PDFs must be exported to a compatible PDF first.',
                422
            );
        } finally {
            foreach ($temporaryFiles as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function buildArtifactPdf(
        string $sourcePath,
        array $document,
        array $revision,
        string $type
    ): string {
        $pdf = new \setasign\Fpdi\Fpdi();
        $pageCount = $pdf->setSourceFile($sourcePath);

        if ($pageCount > 1000) {
            throw new Problem(
                'The PDF exceeds the 1,000-page artifact limit.'
            );
        }

        $pdf->SetAutoPageBreak(false);

        for ($page = 1; $page <= $pageCount; ++$page) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);

            $pdf->AddPage(
                $size['orientation'],
                [
                    $size['width'],
                    $size['height'] + 12,
                ]
            );

            $pdf->useTemplate($template);
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(3, $size['height'] + 2);

            $label = $this->artifactLabel(
                $type,
                $document,
                $revision,
                $page,
                $pageCount
            );

            $pdf->MultiCell(
                $size['width'] - 6,
                4,
                iconv(
                    'UTF-8',
                    'Windows-1252//TRANSLIT',
                    $label
                ),
                0,
                'C',
                true
            );
        }

        $storageName =
            bin2hex(random_bytes(24))
            . '.pdf';

        $path =
            $this->directory()
            . '/'
            . $storageName;

        $pdf->Output('F', $path);
        chmod($path, 0600);

        return $path;
    }

    private function artifactLabel(
        string $type,
        array $document,
        array $revision,
        int $page,
        int $pageCount
    ): string {
        $label =
            strtoupper($type)
            . ' COPY | #'
            . $document['id']
            . ' '
            . $document['document_number']
            . ' | REV '
            . $revision['new_revision_level']
            . ' | '
            . $page
            . '/'
            . $pageCount;

        if (
            (int) $document['current_revision_id']
            !== (int) $revision['id']
        ) {
            $label .= ' | HISTORICAL';
        }

        return $label;
    }

    private function storeArtifact(
        string $path,
        array $document,
        array $revision,
        array $source,
        string $type
    ): array {
        $storageName = basename($path);
        $name =
            $type
            . '-revision-'
            . $revision['revision_number']
            . '.pdf';

        $fileId = $this->model()->insert(
            'files',
            [
                'original_name' => $name,
                'storage_name' => $storageName,
                'size' => filesize($path),
                'mime_type' => 'application/pdf',
                'fingerprint' => hash_file('sha256', $path),
                'extension' => 'pdf',
                'purpose' => 'artifact',
                'domain' => 'softcopy',
                'document_id' => $document['id'],
                'uploaded_by' => $this->ctx->id(),
                'status' => 'approved',
                'approved_by' => $this->ctx->id(),
                'approved_at' => date('Y-m-d H:i:s'),
            ]
        );

        $artifactId = $this->model()->insert(
            'revision_artifacts',
            [
                'revision_id' => $revision['id'],
                'artifact_type' => $type,
                'file_id' => $fileId,
                'source_fingerprint' => $source['fingerprint'],
                'generator_version' => 'pk-artifact-1/fpdi-2',
            ]
        );

        $this->ctx->audit(
            'files',
            'artifact_generated',
            $artifactId,
            null,
            [
                'revision_id' => $revision['id'],
                'type' => $type,
                'source_fingerprint' => $source['fingerprint'],
            ]
        );

        return [
            'id' => $artifactId,
            'file_id' => $fileId,
            'message' => 'A labeled PDF artifact was generated from the preserved source.',
        ];
    }

    private function convert(string $source): array
    {
        $binary = $this->findLibreOffice();

        if ($binary === '') {
            throw new Problem(
                'Install LibreOffice in its standard location to enable DOCX/XLSX conversion, or upload a PDF revision.',
                503
            );
        }

        $directory =
            PK_ROOT
            . '/storage/conversions/'
            . bin2hex(random_bytes(10));

        if (!mkdir($directory, 0700, true)) {
            throw new Problem(
                'Cannot create conversion workspace.',
                503
            );
        }

        $command = [
            $binary,
            '-env:UserInstallation=file://'
                . str_replace('\\', '/', $directory)
                . '/profile',
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $directory,
            $source,
        ];

        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        if (!is_resource($process)) {
            throw new Problem(
                'Could not start Office conversion.',
                503
            );
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $startedAt = time();
        $state = ['running' => true];

        do {
            stream_get_contents($pipes[1], 16384);
            stream_get_contents($pipes[2], 16384);

            $state = proc_get_status($process);

            if (!$state['running']) {
                break;
            }

            usleep(100000);
        } while (time() - $startedAt < 45);

        if ($state['running']) {
            proc_terminate($process, 9);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $pdfPath =
            $directory
            . '/'
            . pathinfo(
                $source,
                PATHINFO_FILENAME
            )
            . '.pdf';

        if (
            $state['running']
            || !is_file($pdfPath)
        ) {
            throw new Problem(
                'Office conversion failed or exceeded 45 seconds.',
                422
            );
        }

        // Maintenance cleans the private conversion workspace after processing.
        return [
            $pdfPath,
            [$pdfPath],
        ];
    }

    private function findLibreOffice(): string
    {
        $candidates = [
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            '/usr/bin/libreoffice',
            '/usr/bin/soffice',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return '';
    }
}
