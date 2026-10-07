<?php
declare(strict_types=1);

/** Transfer persistence using the native CodeIgniter MySQLi Query Builder. */
class Transfer_model extends Repository_model
{
    // Ari ang DB locks for physical transfer; business decisions stay sa service.

    public function lock_transfer(
        int $id,
        ?int $version = null
    ): array {
        return $this->lock(
            'transfers',
            $id,
            $version
        );
    }

    public function lock_hardcopy(int $id): array
    {
        return $this->lock(
            'hardcopy_documents',
            $id
        );
    }
}
