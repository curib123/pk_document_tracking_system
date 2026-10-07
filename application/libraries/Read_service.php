<?php
declare(strict_types=1);
use Pk\Core\Context;
class Read_service
{
    private Context $ctx;
    public function __construct(Context|array|null $options = null) { $this->ctx = Context::fromOptions($options); }
    public function metadata(): array { return $this->ctx->model(Read_model::class)->metadata(); }
    public function listing(string $module,array $query): array { return $this->ctx->model(Read_model::class)->listing($module,$query); }
    public function detail(string $module,int $id): array { return $this->ctx->model(Read_model::class)->detail($module,$id); }
    public function lookups(array $query): array { return $this->ctx->model(Read_model::class)->lookups($query); }
    public function dashboard(): array { return $this->ctx->model(Read_model::class)->dashboard(); }
    public function readNotification(array $input): array { return $this->ctx->model(Read_model::class)->readNotification($input); }
}
