<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notifications extends MY_Controller
{
    private function response(array $tasks,int $status=200)
    {
        foreach ($tasks as &$task) {
            $task=['key'=>$task['key'],'reference'=>$task['reference'],'label'=>$task['label'],
                'url'=>site_url('my-tasks/'.$task['tab']).'?'.http_build_query(['q'=>$task['reference']])];
        }
        unset($task);
        return $this->output->set_status_header($status)->set_header('Cache-Control: no-store, private')
            ->set_content_type('application/json')->set_output(json_encode(['tasks'=>$tasks,
                'csrf'=>['name'=>$this->security->get_csrf_token_name(),'value'=>$this->security->get_csrf_hash()]]));
    }

    public function pending()
    {
        if (!$this->user || !empty($this->user['require_password_change'])) return $this->response([],401);
        $this->load->model('Notification_model');
        return $this->response($this->Notification_model->pending_approvals($this->user));
    }

    public function claim()
    {
        if (!$this->user || !empty($this->user['require_password_change'])) return $this->response([],401);
        $keys=$this->input->post('keys');
        if (!is_array($keys) || count($keys)>100) return $this->response([],400);
        $this->load->model('Notification_model');
        try {return $this->response($this->Notification_model->claim_approvals($this->user,$keys));}
        catch (Throwable $error) {
            log_message('error','Task notification claim failed.');
            return $this->response([],503);
        }
    }
}
