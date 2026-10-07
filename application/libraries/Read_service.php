<?php
declare(strict_types=1);

use Pk\Core\Context;

class Read_service
{
    // Thin read service ra ni; actual scoped queries naa sa Read_model.
    private Context $ctx;

    public function __construct(Context|array|null $options = null)
    {
        $this->ctx = Context::fromOptions($options);
    }

    private function model(): Read_model
    {
        return $this->ctx->model(
            Read_model::class
        );
    }

    public function metadata(): array
    {
        return $this->model()->metadata();
    }

    public function listing(
        string $module,
        array $query
    ): array {
        return $this->model()->listing(
            $module,
            $query
        );
    }

    public function detail(
        string $module,
        int $id
    ): array {
        return $this->model()->detail(
            $module,
            $id
        );
    }

    public function lookups(array $query): array
    {
        return $this->model()->lookups(
            $query
        );
    }

    public function dashboard(): array
    {
        return $this->model()->dashboard();
    }

    public function readNotification(array $input): array
    {
        return $this->model()->readNotification(
            $input
        );
    }
}
