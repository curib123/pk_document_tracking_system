<?php
declare(strict_types=1);

use Pk\Core\Problem;

/** One row-access policy for metadata, counts, lookups and file content. */
class Document_visibility_model extends Repository_model
{
    /**
     * Build a correlated predicate without resetting the shared Query Builder.
     * Aliases are internal identifiers, never request parameters.
     */
    public function predicate(
        string $domain,
        string $alias = 't',
        bool $content = false
    ): string {
        $this->validateDomain($domain);
        $this->validateAlias($alias);

        if (!$this->ctx->id() || !$this->ctx->can($domain . '.view')) {
            return '(1 = 0)';
        }

        if ($this->ctx->can('documents.access_all')) {
            return '(1 = 1)';
        }

        // Metadata browsing is separate from permission to download content.
        if (!$content && $this->ctx->can('documents.view_all')) {
            return '(1 = 1)';
        }

        $userId = $this->db->escape($this->ctx->id());
        $matches = [];

        if ($this->ctx->can('documents.view_assigned')) {
            if ($domain === 'softcopy') {
                $matches[] = 'EXISTS (SELECT 1 FROM assignments pv_assignment'
                    . ' WHERE pv_assignment.softcopy_id = ' . $alias . '.id'
                    . ' AND pv_assignment.user_id = ' . $userId
                    . ' AND pv_assignment.active = 1)';
            } else {
                // Ang named holder mao ang hardcopy assignment; not its creator.
                $matches[] = $alias . '.holder_id = ' . $userId;
            }
        }

        if ($this->ctx->can('documents.view_granted')) {
            $matches[] = 'EXISTS (SELECT 1 FROM access_grants pv_grant'
                . ' WHERE pv_grant.domain = ' . $this->db->escape($domain)
                . ' AND pv_grant.document_id = ' . $alias . '.id'
                . ' AND pv_grant.user_id = ' . $userId
                . " AND pv_grant.status = 'access_granted'"
                . ' AND pv_grant.revoked_at IS NULL'
                . ' AND pv_grant.expires_at > NOW())';
        }

        if (!$matches) {
            return '(1 = 0)';
        }

        return '(' . $alias . ".status = 'active' AND ("
            . implode(' OR ', $matches) . '))';
    }

    public function canView(string $domain, int $id): bool
    {
        return $this->allows($domain, $id, false);
    }

    public function canRead(string $domain, int $id): bool
    {
        return $this->allows($domain, $id, true);
    }

    private function allows(string $domain, int $id, bool $content): bool
    {
        $predicate = $this->predicate($domain, 'pv_document', $content);
        if ($id < 1 || $predicate === '(1 = 0)') {
            return false;
        }

        $this->db->reset_query()
            ->select('pv_document.id')
            ->from(Document_service::table($domain) . ' pv_document')
            ->where('pv_document.id', $id)
            ->where($predicate, null, false)
            ->limit(1);

        return $this->first() !== null;
    }

    /** Restrict linked file metadata too; unlinked private uploads stay personal. */
    public function filePredicate(string $alias = 't'): string
    {
        $this->validateAlias($alias);
        if (!$this->ctx->id() || !$this->ctx->can('files.view')) {
            return '(1 = 0)';
        }

        if ($this->ctx->can('files.approve') || $this->ctx->can('files.view_all')) {
            return '(1 = 1)';
        }

        $matches = [
            '(' . $alias . '.document_id IS NULL AND '
                . $alias . '.uploaded_by = ' . $this->db->escape($this->ctx->id()) . ')',
        ];

        foreach (['softcopy', 'hardcopy'] as $domain) {
            $matches[] = '(' . $alias . '.domain = ' . $this->db->escape($domain)
                . ' AND EXISTS (SELECT 1 FROM ' . Document_service::table($domain)
                . ' pv_file_document WHERE pv_file_document.id = ' . $alias . '.document_id'
                . ' AND ' . $this->predicate($domain, 'pv_file_document') . '))';
        }

        return '(' . implode(' OR ', $matches) . ')';
    }

    private function validateDomain(string $domain): void
    {
        if (!in_array($domain, ['softcopy', 'hardcopy'], true)) {
            throw new Problem('Unknown document domain.', 422);
        }
    }

    private function validateAlias(string $alias): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $alias)) {
            throw new \InvalidArgumentException('Invalid internal document alias.');
        }
    }
}
