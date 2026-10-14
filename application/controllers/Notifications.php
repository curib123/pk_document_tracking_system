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

    public function inbox()
    {
        if (!$this->user || !empty($this->user['require_password_change']))
            return $this->output->set_status_header(401)->set_content_type('application/json')
                ->set_output('{"items":[],"total":0}');
        $page=(int)$this->input->get('page',TRUE);
        $limit=(int)$this->input->get('limit',TRUE);
        $this->load->model('Notification_model');
        $data=$this->Notification_model->unread_page($this->user,$page,$limit);
        foreach ($data['items'] as &$item) {
            $parts=explode('?',$item['url'],2);
            $item['url']=site_url($parts[0]).(isset($parts[1])?'?'.$parts[1]:'');
        }
        unset($item);
        return $this->output->set_header('Cache-Control: no-store, private')
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }

    public function mark_read()
    {
        if (!$this->user || !empty($this->user['require_password_change']))
            return $this->output->set_status_header(401)->set_content_type('application/json')
                ->set_output('{"ok":false}');
        if ($this->input->method(TRUE)!=='POST')
            return $this->output->set_status_header(405)->set_content_type('application/json')
                ->set_output('{"ok":false}');
        $key=(string)$this->input->post('key');
        if (!preg_match('/^[1-9][0-9]*:[1-9][0-9]*$/D',$key))
            return $this->output->set_status_header(400)->set_content_type('application/json')
                ->set_output('{"ok":false}');
        $this->load->model('Notification_model');
        $claimed=$this->Notification_model->claim_approvals($this->user,[$key]);
        $page=$this->Notification_model->unread_page($this->user,1,5);
        return $this->output->set_header('Cache-Control: no-store, private')
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok'=>count($claimed)===1,'unread'=>$page['total'],
                'csrf'=>['name'=>$this->security->get_csrf_token_name(),
                    'value'=>$this->security->get_csrf_hash()]
            ]));
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
