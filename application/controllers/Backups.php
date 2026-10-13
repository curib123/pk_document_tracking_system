<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Backups extends MY_Controller
{
    private function administrator(): void
    {
        $this->require_permission('backups','view');
    }
    private function service(): Backup_service
    {
        require_once APPPATH.'services/backup/backup_service.php';
        return new Backup_service(PK_ROOT);
    }
    public function index()
    {
        $this->administrator();
        $service=$this->service();
        $this->render('Backup & Restore','pages/administration/backups',[
            'backup_items'=>$service->list_backups(),
            'backup_config'=>$service->configuration(),
            'backup_last_auto'=>$this->lastAuto($service->list_backups())
        ]);
    }
    private function lastAuto($list)
    {
        foreach ($list as $row) if ($row['kind']==='Automatic') return $row;
        return NULL;
    }
    private function execute($task): void
    {
        $this->administrator();
        $this->confirmed(); // CSRF is enforced by CI3 configuration.
        if (function_exists('set_time_limit')) @set_time_limit(0);
        try {
            $message=$task($this->service());
            $this->notice($message);
        } catch (DomainException $error) {
            log_message('error','Administrator backup/restore failed: '.$error->getMessage());
            $this->notice($error->getMessage(),'danger');
        } catch (Throwable $error) {
            log_message('error','Backup/restore internal exception: '.$error->getMessage());
            $this->notice('Backup operation failed. Check protected server logs.','danger');
        }
        redirect('admin/backups');
    }
    public function create()
    {
        $this->execute(static function($service) {
            $item=$service->backup('manual');
            return 'Encrypted backup created: '.$item['name'];
        });
    }
    public function import()
    {
        $upload=$_FILES['backup_zip']??[];
        $this->execute(static function($service) use($upload) {
            $name=$service->receiveUpload($upload);
            return 'Verified backup imported: '.$name;
        });
    }
    public function restore()
    {
        $this->execute(function($service) {
            if ((string)$this->input->post('confirm_phrase')!=='RESTORE')
                throw new DomainException('Type RESTORE to confirm data replacement.');
            $password=(string)$this->input->post('admin_password');
            if ($password==='') throw new DomainException('Your password is required.');
            $current=$this->db->select('password_hash')->get_where('users',[
                'id'=>(int)$this->user['id'],'active'=>1])->row_array();
            if (!$current || !password_verify($password,(string)$current['password_hash']))
                throw new DomainException('Your password is incorrect.');
            $name=(string)$this->input->post('backup_name');
            $result=$service->restore($name);
            $this->load->library('Audit_file');
            $this->audit_file->append((int)$this->user['id'],
                'admin/backups/restore',$name,['recovery'=>$result['recovery']],'success');
            // Restored database can invalidate live accounts and sessions.
            $this->session->sess_destroy();
            redirect('login');
            exit;
        });
    }
    public function download($name='')
    {
        $this->administrator();
        $service=$this->service();
        try {$path=$service->resolve((string)$name);}
        catch (DomainException $e) {show_404();return;}
        $this->load->library('Audit_file');
        $this->audit_file->append((int)$this->user['id'],'admin/backups/download',
            $name,[],'success');
        while (ob_get_level()>0) @ob_end_clean();
        header('Content-Type: application/zip');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        header('Content-Disposition: attachment; filename="'.basename($path).'"');
        header('Content-Length: '.filesize($path));
        readfile($path);
        exit;
    }
}
