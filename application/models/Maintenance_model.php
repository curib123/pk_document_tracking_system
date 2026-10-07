<?php
declare(strict_types=1);

/** Maintenance persistence using the native CodeIgniter MySQLi Query Builder. */
class Maintenance_model extends Repository_model
{
    // Ari ang DB queries; service layer ang magbuot sa business rules para klaro ang separation.

    public function expired_grants(): array
    {
        $this->db
            ->reset_query()
            ->from('access_grants')
            ->where(
                'status',
                'access_granted'
            )
            ->where(
                'expires_at < NOW()',
                null,
                false
            );

        return $this->results(true);
    }

    public function prune_login_attempts(): bool
    {
        $this->db
            ->reset_query()
            ->where(
                'window_started < DATE_SUB(NOW(),INTERVAL 2 DAY)',
                null,
                false
            );

        return $this->written(
            $this->db->delete(
                'login_attempts'
            )
        );
    }
}
