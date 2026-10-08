<?php
declare(strict_types=1);

use Pk\Core\{Problem, Rules};

/** Minimal request candidates; this is discovery, never document/content access. */
class Request_catalog_model extends Repository_model
{
    public function options(array $query): array
    {
        $kind = Rules::choice($query, 'kind', ['softcopy', 'hardcopy']);
        $type = Rules::choice($query, 'request_type', ['access', 'assignment']);
        $this->ctx->require('requests.add');
        $this->ctx->require($kind . '.view');
        $this->ctx->require($type . '.request');

        if ($type === 'assignment' && $kind !== 'softcopy') {
            throw new Problem('Assignment requests support softcopies only.', 403);
        }

        $selected = Rules::id($query, 'selected', false);
        $search = Rules::text($query, 'q', 100, false) ?? '';
        $label = $kind === 'softcopy'
            ? "CONCAT(t.document_number, ' — ', t.title)"
            : 't.title';

        $scope = $this->ctx->can('documents.request_catalog')
            ? '(1 = 1)'
            : $this->ctx->model(Document_visibility_model::class)->predicate($kind, 't');

        // Request picker ra ni. Ayaw apila ang private fields or file URLs.
        $this->db->reset_query()
            ->select('t.id')
            ->select($label . ' AS label', false)
            ->from(Document_service::table($kind) . ' t')
            ->where('t.status', 'active')
            ->where($scope, null, false)
            ->group_start()
            ->like($label, $search);

        if ($selected !== null) {
            $this->db->or_where('t.id', $selected);
        }

        $this->db->group_end()->order_by('label')->order_by('t.id')->limit(101);
        $rows = $this->results();
        $more = count($rows) > 100;

        return [
            'options' => array_slice($rows, 0, 100),
            'more' => $more,
            'message' => $more
                ? 'More results exist. Type a narrower search.'
                : 'Selection does not grant access. Approval is still required.',
        ];
    }
}
