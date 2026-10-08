<?php
declare(strict_types=1);

/** Read-only dashboard presentation data with the existing visibility boundary. */
class Workspace_model extends Repository_model
{
    public function recentDocuments(): array
    {
        $recent = [];
        foreach (['softcopy', 'hardcopy'] as $domain) {
            if (!$this->ctx->can($domain . '.view')) {
                continue;
            }
            $predicate = $this->ctx->model(Document_visibility_model::class)
                ->predicate($domain, 't');
            $this->db->reset_query()
                ->select('t.id,t.title,t.status,t.created_at')
                ->from(Document_service::table($domain) . ' t')
                ->where($predicate, null, false)
                ->order_by('t.created_at', 'DESC')
                ->order_by('t.id', 'DESC')
                ->limit(5);
            if ($domain === 'softcopy') {
                $this->db->select('t.document_number');
            }
            foreach ($this->results() as $row) {
                $recent[] = [
                    'id' => (int) $row['id'],
                    'domain' => $domain,
                    'title' => $row['title'],
                    'reference' => $row['document_number'] ?? '',
                    'status' => $row['status'],
                    'created_at' => $row['created_at'],
                ];
            }
        }
        usort($recent, static function (array $left, array $right): int {
            return strcmp($right['created_at'], $left['created_at'])
                ?: ($right['id'] <=> $left['id'])
                ?: strcmp($left['domain'], $right['domain']);
        });
        return array_slice($recent, 0, 5);
    }
}
