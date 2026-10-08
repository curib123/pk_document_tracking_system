<?php
declare(strict_types=1);

use Pk\Core\{Context, Problem, Rules};

class Read_service
{
    // Thin read service ra ni; actual scoped queries naa sa the read models.
    private Context $ctx;

    public function __construct(Context|array|null $options = null)
    {
        $this->ctx = Context::fromOptions($options);
    }

    private function model(): Read_model
    {
        return $this->ctx->model(Read_model::class);
    }

    public function metadata(): array
    {
        return $this->model()->metadata();
    }

    public function listing(string $module, array $query): array
    {
        return $this->model()->listing($module, $query);
    }

    public function detail(string $module, int $id): array
    {
        return $this->model()->detail($module, $id);
    }

    public function lookups(array $query): array
    {
        if (isset($query['request_type'])) {
            $type = Rules::choice($query, 'request_type', ['access', 'assignment']);
            $domain = Rules::choice($query, 'kind', ['softcopy', 'hardcopy']);
            if ($type === 'assignment' && $domain !== 'softcopy') {
                throw new Problem('Assignment requests require a softcopy document.', 422);
            }
            $this->ctx->require('requests.add');
            $this->ctx->require($type . '.request');
            $this->ctx->require($domain . '.view');
            if ($this->ctx->can('documents.request_catalog')) {
                return $this->ctx->model(Workspace_read_model::class)->requestCatalog($query);
            }
        }
        // Ordinary forms and roles without catalog discovery retain row scoping.
        return $this->model()->lookups($query);
    }

    public function dashboard(): array
    {
        $data = $this->model()->dashboard();
        $data['recent_documents'] = $this->ctx->model(Workspace_read_model::class)->recentDocuments();
        return $data;
    }

    public function readNotification(array $input): array
    {
        return $this->model()->readNotification($input);
    }
}
