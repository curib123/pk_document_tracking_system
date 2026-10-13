<?php
declare(strict_types=1);
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__).'/application/');
function html_escape($value) {return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function site_url($path) {return '/'.$path;}
class ApprovalViewCaptured extends RuntimeException {}
class ApprovalViewHarness
{
    public $load,$display;
    public function __construct() {$this->load=$this;}
    public function view($name,$data)
    {
        $this->display=$data['dt_rows'][0]['display'];throw new ApprovalViewCaptured();
    }
    public function render(bool $task,array $steps,array $events=[]): array
    {
        $tab='hardcopy';$history=[42=>$events];$permissions=[];$allowed_request_types=[];$navigation_counts=[];
        $user=['name'=>'Requester'];$total=1;
        $table=['q'=>'','status'=>'','page'=>1,'limit'=>25,'sort'=>'updated_at','dir'=>'DESC','type'=>'','from'=>'','to'=>''];
        $rows=[['id'=>42,'type'=>'hardcopy_create','reference'=>'REQ-42','payload'=>'{}','hardcopy_id'=>NULL,
            'softcopy_id'=>NULL,'requester_name'=>'Requester','version'=>1,'workflow_version_id'=>3,
            'status'=>'submitted','submitted_at'=>'2026-10-13 10:00:00',
            'created_at'=>'2026-10-13 09:00:00','updated_at'=>'2026-10-13 10:00:00']];
        $approval_routes=[42=>$steps];
        ob_start();
        try {include APPPATH.'view/pages/request/shared_index.php';}
        catch (ApprovalViewCaptured $e) {}
        finally {ob_end_clean();}
        return array_column($this->display['sections'],NULL,'key')['approvals'];
    }
}
$steps=[
    ['label'=>'Noted By','approver_label'=>'Staff1 Staff1','status'=>'active'],
    ['label'=>'Plant Manager Approval','approver_label'=>'Plant Manager','status'=>'pending'],
    ['label'=>'Document Officer Approval','approver_label'=>'Document Control Officer','status'=>'pending']
];
$view=new ApprovalViewHarness();
foreach ([FALSE,TRUE] as $task) {
    $approval=$view->render($task,$steps);
    if (($approval['steps']??NULL)!==$steps)
        throw new RuntimeException('My Requests and My Tasks must pass each approval step separately to the viewer.');
    if (($approval['fields']['Workflow Version']??'')!=='Assigned at submission')
        throw new RuntimeException('Workflow version metadata must remain visible.');
}
if ($view->render(FALSE,[])['fields']['Steps']!=='No steps assigned yet')
    throw new RuntimeException('Requests without assigned steps must retain their empty message.');
$recordedSteps=$steps;
foreach ($recordedSteps as $i=>&$step) $step['step_id']=$i+101;
unset($step);
$events=[
    ['step_id'=>NULL,'action'=>'submitted','user_name'=>'Requester','position_title'=>'Staff','comments'=>'','created_at'=>'2026-10-13 10:00:00'],
    ['step_id'=>102,'action'=>'returned','user_name'=>'Manager','position_title'=>'Plant Manager','comments'=>'First return','created_at'=>'2026-10-13 11:00:00'],
    ['step_id'=>101,'action'=>'approved','user_name'=>'Staff1','position_title'=>'Staff','comments'=>'First recheck','created_at'=>'2026-10-13 11:30:00'],
    ['step_id'=>102,'action'=>'returned','user_name'=>'Manager','position_title'=>'Plant Manager','comments'=>'Second return','created_at'=>'2026-10-13 12:00:00'],
    ['step_id'=>101,'action'=>'approved','user_name'=>'Staff1','position_title'=>'Staff','comments'=>'Second recheck','created_at'=>'2026-10-13 12:30:00'],
    ['step_id'=>102,'action'=>'approved','user_name'=>'Manager','position_title'=>'Plant Manager','comments'=>'Accepted','created_at'=>'2026-10-13 13:00:00']
];
foreach ([FALSE,TRUE] as $task) {
    $tiles=$view->render($task,$recordedSteps,$events)['steps'];
    if (array_column($tiles[0]['history']??[],'comments')!==['First recheck','Second recheck'] ||
        array_column($tiles[1]['history']??[],'comments')!==['First return','Second return','Accepted'] ||
        isset($tiles[2]['history']))
        throw new RuntimeException('Each tile must preserve all of its own decisions in order without mixing steps or submissions.');
    foreach ($tiles[1]['history'] as $event) {
        if ($event['user_name']!=='Manager' || $event['position_title']!=='Plant Manager' || empty($event['created_at']))
            throw new RuntimeException('Decision history must preserve approver, position and timestamp.');
    }
}
echo "Request and task views preserve approval tiles and every step's recorded decisions.\n";
