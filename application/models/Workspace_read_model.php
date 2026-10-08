<?php
declare(strict_types=1);

use Pk\Core\{Problem, Rules};

/** Read-only projections: request discovery is not document access. */
class Workspace_read_model extends Repository_model
{
    public function requestCatalog(array $query): array
    {
        $type = Rules::choice($query, 'request_type', ['access', 'assignment']);
        $domain = Rules::choice($query, 'kind', ['softcopy', 'hardcopy']);
        if ($type === 'assignment' && $domain !== 'softcopy') {
            throw new Problem('Assignment requests require a softcopy document.', 422);
        }
        $this->ctx->require('requests.add');
        $this->ctx->require($type . '.request');
        $this->ctx->require($domain . '.view');
        $this->ctx->require('documents.request_catalog');

        $search = Rules::text($query, 'q', 100, false) ?? '';
        $selected = Rules::id($query, 'selected', false);
        $label = $domain === 'softcopy'
            ? "CONCAT(d.document_number, ' — ', d.title)"
            : 'd.title';

        // Only these two fields leave the request picker. Never return files,
        // revisions, storage coordinates or any full document metadata here.
        $this->db->reset_query()
            ->select('d.id')
            ->select($label . ' AS label', false)
            ->from(Document_service::table($domain) . ' d')
            ->where('d.status', 'active')
            ->group_start()
            ->like($label, $search);
        if ($selected !== null) {
            $this->db->or_where('d.id', $selected);
        }
        $this->db->group_end()->order_by('label')->order_by('d.id')->limit(51);
        $rows = $this->results();
        return [
            'options' => array_slice($rows, 0, 50),
            'more' => count($rows) > 50,
            'message' => 'Selection is for a request only. Approval is required before document access is granted.'
                . (count($rows) > 50 ? ' Type to narrow the results.' : ''),
        ];
    }

    public function recentDocuments(): array
    {
        $this->ctx->require('dashboard.view');
        $visibility = $this->ctx->model(Document_visibility_model::class);
        $rows = [];
        foreach (['softcopy', 'hardcopy'] as $domain) {
            if (!$this->ctx->can($domain . '.view')) {
                continue;
            }
            $predicate = $visibility->predicate($domain, 'd');
            $this->db->reset_query()
                ->select('d.id,d.title,d.status,d.created_at')
                ->select($domain === 'softcopy' ? 'd.document_number' : 'NULL AS document_number', false)
                ->from(Document_service::table($domain) . ' d')
                ->where($predicate, null, false)
                ->order_by('d.created_at', 'DESC')->order_by('d.id', 'DESC')->limit(5);
            foreach ($this->results() as $row) {
                $row['domain'] = $domain;
                $rows[] = $row;
            }
        }
        usort($rows, static fn(array $a, array $b): int =>
            strcmp((string)$b['created_at'], (string)$a['created_at'])
            ?: ((int)$b['id'] <=> (int)$a['id'])
        );
        return array_slice($rows, 0, 5);
    }
}
