<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Maintenance extends CI_Controller
{
    public function run($scope = 'all')
    {
        if (!is_cli()) {
            show_404();
            return;
        }

        try {
            $this->load->database();

            require_once APPPATH . 'services/backup/backup_service.php';
            require_once APPPATH . 'services/files/file_service.php';
            $backup = new Backup_service(PK_ROOT);
            if ($scope === 'orphans') {
                $backup->with_maintenance_lock(static function () {
                    if (!(new File_service())->cleanup_orphans_daily())
                        throw new RuntimeException('Unregistered file cleanup was incomplete; it will be retried.');
                });
                echo "Unregistered file cleanup completed.\n";
                return;
            }
            if ($scope !== 'all')
                throw new InvalidArgumentException('Unknown maintenance scope.');

            $retention = $backup->maintain_daily(static function () {
                if (!(new File_service())->cleanup_daily())
                    throw new RuntimeException('Controlled-file reconciliation was incomplete; it will be retried.');
            });
            if ($retention !== NULL && $retention['failed']) {
                throw new RuntimeException(
                    'Backup and log retention was incomplete; retry with php tools/backup.php retention.'
                );
            }

            echo "Daily maintenance completed.\n";
        } catch (Throwable $error) {
            log_message('error', 'Daily maintenance failed: ' . $error->getMessage());
            fwrite(STDERR, 'Daily maintenance failed: ' . $error->getMessage() . "\n");
            exit(1);
        }
    }
}
