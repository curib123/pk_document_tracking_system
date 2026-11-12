<?php
/** Turns a raw audit entry into one plain sentence for non-technical reviewers (internal audit). */
final class Audit_describer
{
    private const AREAS = [
        'softcopy' => 'softcopy (electronic) documents', 'hardcopy' => 'hardcopy (paper) documents',
        'external' => 'external documents', 'hardcopy-transfer' => 'hardcopy transfers',
        'hardcopy-disposal' => 'hardcopy disposals', 'document-assign' => 'document assignments',
        'access-grant' => 'access requests', 'dashboard' => 'the dashboard',
        'softcopy-categories' => 'softcopy categories', 'external-categories' => 'external categories',
        'area' => 'areas', 'specific' => 'specific areas', 'location' => 'locations',
        'subsidiary' => 'subsidiaries', 'asset' => 'assets', 'users' => 'user accounts',
        'roles' => 'roles and permissions', 'workflows' => 'approval workflows',
        'backups' => 'backups', 'audit' => 'the audit trail',
        'document-assignments' => 'document assignments',
        'user-assigned-documents' => 'documents assigned to the user'
    ];
    private const DECISIONS = ['approved' => 'approved', 'rejected' => 'rejected', 'returned' => 'returned for correction'];

    public static function describe(array $e, string $who): string
    {
        $action = trim((string) $e['action'], '/');
        $failed = ($e['status'] ?? '') !== 'success';
        $meta = is_array($e['metadata'] ?? NULL) ? $e['metadata'] : [];
        $input = is_array($meta['input'] ?? NULL) ? $meta['input'] : [];
        $body = self::sentence($action, $meta, $input);
        if ($failed && strpos($body, 'PERMANENTLY DELETED') === 0)
            $body = 'tried to permanently delete' . substr($body, strlen('PERMANENTLY DELETED'));
        $text = $who . ' ' . $body . '.';
        if ($failed && $action !== 'auth/login')
            $text .= ' This did NOT go through' . (!empty($meta['reason']) ? ': ' . rtrim((string) $meta['reason'], '.') : ' (it was refused or had an error)') . '.';
        if ($failed && $action === 'auth/login')
            $text = 'Someone tried to sign in but failed (wrong username or password).';
        return $text;
    }

    private static function area(string $slug): string
    {
        return self::AREAS[$slug] ?? str_replace('-', ' ', $slug);
    }

    private static function sentence(string $a, array $m, array $in): string
    {
        $title = !empty($m['title']) ? ' "' . $m['title'] . '"' : '';
        if ($a === 'auth/login') return 'signed in to the system';
        if ($a === 'auth/logout') return 'signed out of the system';
        if ($a === 'change-password') return 'changed an account password';
        if (strpos($a, 'view/') === 0) return 'opened the page for ' . self::area(substr($a, 5) ?: 'home');
        if (preg_match('#^documents/(\w+)/hard-delete$#', $a, $x))
            return 'PERMANENTLY DELETED a disposed ' . $x[1] . ' document' . $title .
                (!empty($m['deleted_file_count']) ? ' and ' . $m['deleted_file_count'] . ' file(s)' : '');
        if (preg_match('#^documents/(\w+)/dispose$#', $a, $x)) return 'disposed of a ' . $x[1] . ' document';
        if (preg_match('#^documents/(\w+)/direct$#', $a, $x)) return 'saved a ' . $x[1] . ' document directly (without a request)';
        if (preg_match('#^documents/(\w+)/save$#', $a, $x)) return 'added or updated a ' . $x[1] . ' document record';
        if (preg_match('#^my-tasks/([\w-]+)/decide$#', $a, $x))
            return (self::DECISIONS[$in['decision'] ?? ''] ?? 'acted on') . ' a ' . str_replace('-', ' ', $x[1]) . ' request in their approval tasks';
        if (preg_match('#^my-requests/([\w-]+)/submit$#', $a, $x)) return 'submitted a ' . str_replace('-', ' ', $x[1]) . ' request for approval';
        if (preg_match('#^my-requests/([\w-]+)/save$#', $a, $x)) return 'saved a draft ' . str_replace('-', ' ', $x[1]) . ' request';
        if (preg_match('#^my-requests/([\w-]+)/delete$#', $a, $x)) return 'deleted a cancelled draft ' . str_replace('-', ' ', $x[1]) . ' request';
        if (preg_match('#^my-requests/([\w-]+)/cancel$#', $a, $x)) return 'cancelled a ' . str_replace('-', ' ', $x[1]) . ' request';
        if (preg_match('#^places/([\w-]+)/save$#', $a, $x)) return 'added or changed an item in ' . self::area($x[1]);
        if (preg_match('#^places/([\w-]+)/deactivate$#', $a, $x)) return 'deactivated an item in ' . self::area($x[1]);
        $map = [
            'files/preview' => 'previewed a document file', 'files/preview-content' => 'previewed a document file',
            'files/review' => 'viewed a file attached to a request',
            'files/review-content' => 'opened a request file in an office app',
            'files/review-open' => 'opened a request file for review',
            'files/review-download' => 'downloaded a file attached to a request',
            'files/review-download-pdf' => 'downloaded the PDF copy of a request file',
            'files/download' => 'downloaded a document file',
            'files/download-pdf' => 'downloaded the PDF copy of a document file',
            'files/download-external' => 'downloaded an external document file',
            'files/print' => 'opened a document file for printing', 'files/print-content' => 'printed a document file',
            'admin/users/save' => 'created or changed a user account',
            'admin/roles/save' => 'created or changed a role and its permissions',
            'admin/workflows/clone' => 'made a new draft copy of an approval workflow',
            'admin/workflows/publish' => 'published an approval workflow (it now applies to new requests)',
            'admin/workflows/step/save' => 'changed an approval step in a workflow',
            'admin/workflows/step/move' => 'changed the order of approval steps in a workflow',
            'admin/backups/cleanup' => 'cleaned up old backups',
            'admin/backups/restore' => 'RESTORED the system from a backup (replaces current data)',
            'admin/document-assignments/location' => 'assigned documents of a location to a user',
            'hardcopy_location_bulk_assigned' => 'assigned all documents of a location to a user'
        ];
        return $map[$a] ?? 'performed the action "' . $a . '"';
    }
}
