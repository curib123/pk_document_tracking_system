<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Maintenance extends CI_Controller
{
    public function run()
    {
        if (!is_cli()) {
            show_404();
            return;
        }

        try {
            $this->load->database();

            require_once APPPATH . 'services/backup/backup_service.php';
            $retention = (new Backup_service(PK_ROOT))->apply_retention_daily();
            if ($retention !== NULL && $retention['failed']) {
                throw new RuntimeException(
                    'Backup and log retention was incomplete; retry with php tools/backup.php retention.'
                );
            }

            require_once APPPATH . 'services/files/file_service.php';
            if (!(new File_service())->cleanup_daily()) {
                throw new RuntimeException('Controlled-file reconciliation was incomplete; it will be retried.');
            }

            echo "Daily maintenance completed.\n";
        } catch (Throwable $error) {
            log_message('error', 'Daily maintenance failed: ' . $error->getMessage());
            fwrite(STDERR, 'Daily maintenance failed: ' . $error->getMessage() . "\n");
            exit(1);
        }
    }
}
