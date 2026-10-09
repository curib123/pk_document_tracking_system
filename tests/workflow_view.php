<?php
function html_escape($v) {return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function site_url($v='') {return '/'.$v;}
class WorkflowViewHarness {
    public $load; public $security; public $captured=[];
    public function __construct() {$this->load=$this;$this->security=$this;}
    public function get_csrf_token_name() {return 'csrf';}
    public function get_csrf_hash() {return 'test';}
    public function view($name,$data=[]) {if (str_contains($name,'datatable')) $this->captured=$data;}
    public function render($permissions) {
        $title='Workflows';$total=1;$users=[['id'=>1,'name'=>'Alice']];$roles=[['id'=>1,'name'=>'Administrator']];
        $table=['q'=>'','status'=>'','page'=>1,'limit'=>10,'sort'=>'name','dir'=>'ASC','type'=>'','publication'=>''];
        $graph=json_encode(['steps'=>[['key'=>'step_1','name'=>'Approve by Alice','approver'=>['type'=>'user','value'=>1]]]]);
        $versions=[['id'=>2,'version_number'=>2,'status'=>'draft','is_default'=>0,'graph'=>$graph],
            ['id'=>1,'version_number'=>1,'status'=>'published','is_default'=>1,'graph'=>$graph]];
        $rows=[['id'=>1,'name'=>'Softcopy create','workflow_key'=>'softcopy_create','description'=>'',
            'request_type'=>'softcopy_create','active'=>1,'version'=>1,'latest_version_id'=>2,
            'version_number'=>2,'version_status'=>'draft','is_default'=>0,'graph'=>$graph,'versions'=>$versions]];
        ob_start();require dirname(__DIR__).'/application/view/pages/workflow_builder/index.php';return ob_get_clean();
    }
}
$h=new WorkflowViewHarness();$html=$h->render(['workflows'=>['view'=>TRUE]]);
foreach (['Workflow Steps','Approval Steps','Published','Draft','Available for Request Approval','data-workflow-version'] as $text)
    if (!str_contains($html,$text)) {fwrite(STDERR,"Workflow step view missing: $text\n");exit(1);}
if (str_contains($html,'Create New Step')) throw new RuntimeException('Read-only users must not receive step editing controls.');
$html=$h->render(['*'=>TRUE]);
if (!str_contains($html,'Create New Step') || !str_contains($html,'graph_hash')) throw new RuntimeException('Draft authoring controls are missing.');
if (!in_array('steps',array_column($h->captured['dt_rows'][0]['buttons'],'type'),TRUE)) throw new RuntimeException('Step Actions table button is missing.');
echo "Workflow published/draft versions, approval sections and read-only controls passed.\n";
