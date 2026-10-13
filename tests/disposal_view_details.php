<?php
declare(strict_types=1);
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__).'/application/');
class CI_Model {public $db,$load,$Document_model,$Permission_model;}
require_once APPPATH.'models/Request_model.php';
require_once APPPATH.'models/Document_detail_model.php';
function disposal_view_check(bool $value,string $message):void {if(!$value)throw new RuntimeException($message);}
class DisposalViewResult {private $rows;function __construct($rows){$this->rows=$rows;}function result_array(){return $this->rows;}}
class DisposalViewDB {
 public $calls=[],$records=[];
 function __call($method,$args){$this->calls[]=[$method,$args];return $this;}
 function get(){return new DisposalViewResult([]);}
 function query($sql,$args){$this->calls[]=['query',[$sql,$args]];return new DisposalViewResult(strpos($sql,'FROM disposals')!==FALSE?$this->records:[]);}
}
$request=new Request_model();$request->db=new DisposalViewDB();
$request->db->records=[['request_id'=>2,'document_id'=>20,'disposal_action'=>'other','remarks'=>'Damaged paper beyond repair','disposed_by_name'=>'Final Approver','disposed_at'=>'2026-10-13 12:00:00','previous_status'=>'active']];
$details=new ReflectionMethod(Request_model::class,'details');$details->setAccessible(TRUE);
$base=['type'=>'disposal','source_title'=>'Archived register','source_reference'=>'HC-020'];
$rows=$details->invoke($request,[
 $base+['id'=>1,'hardcopy_id'=>10,'status'=>'submitted','payload'=>json_encode(['disposal_reason'=>'shred'])],
 $base+['id'=>2,'hardcopy_id'=>20,'status'=>'completed','payload'=>json_encode(['disposal_reason'=>'other','disposal_other'=>'Damaged paper beyond repair'])]
]);
disposal_view_check($rows[0]['detail_labels']['Disposal Reason']==='Shred','Disposal reasons must use readable labels');
disposal_view_check(!isset($rows[0]['detail_labels']['Disposed At']),'Pending disposal cannot pretend it was executed');
disposal_view_check(($rows[1]['detail_labels']['Disposed By']??'')==='Final Approver','Completed request must show its actual disposer');
disposal_view_check(($rows[1]['detail_labels']['Disposed At']??'')==='2026-10-13 12:00:00','Completed request must show the recorded disposal time');
disposal_view_check($rows[1]['detail_labels']['Other Disposal Details']==='Damaged paper beyond repair','Other disposal descriptions must remain intact');
$queries=array_values(array_filter($request->db->calls,static function($call){return $call[0]==='query';}));
disposal_view_check(count($queries)===1&&$queries[0][1][1]===[1,2],'Disposal metadata must be loaded once for the authorized request page');
disposal_view_check(strpos($queries[0][1][0],"p.domain='hardcopy'")!==FALSE&&strpos($queries[0][1][0],'r.hardcopy_id=p.document_id')!==FALSE,
 'Request disposal lookup must match both the domain and selected document');
class DisposalViewRendered extends RuntimeException {}
class DisposalRequestViewHarness {
 public $load,$data;
 function __construct(){$this->load=$this;}
 function view($name,$data=[]){if($name==='reusable_components/reusable_datatable'){$this->data=$data;throw new DisposalViewRendered();}}
 function render($requestRows,$taskFlag){
  $tab='hardcopy-disposal';$task=$taskFlag;$rows=$requestRows;$history=[];$approval_routes=[];$permissions=[];$allowed_request_types=[];$navigation_counts=[];$user=['name'=>'Requester'];$total=count($rows);
  $table=['q'=>'','status'=>'','page'=>1,'limit'=>25,'sort'=>'updated_at','dir'=>'DESC','type'=>'','from'=>'','to'=>''];
  ob_start();try{include APPPATH.'view/pages/request/shared_index.php';}catch(DisposalViewRendered $e){}finally{ob_end_clean();}return $this->data['dt_rows'][0]['display'];
 }
}
function site_url($path){return '/'.$path;}function html_escape($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$viewRow=$rows[0]+['reference'=>'REQ-001','requester_name'=>'Requester','submitted_at'=>'2026-10-13 10:00:00','created_at'=>'2026-10-13 09:00:00','updated_at'=>'2026-10-13 10:00:00','version'=>1,'workflow_version_id'=>1];
$harness=new DisposalRequestViewHarness();
foreach([FALSE,TRUE]as$task){$display=$harness->render([$viewRow],$task);$sections=array_column($display['sections'],NULL,'key');
 disposal_view_check(isset($sections['disposal'])&&$sections['disposal']['title']==='Disposal Details','My Requests and My Tasks must both show a dedicated Disposal Details concern');
 disposal_view_check($sections['disposal']['fields']['Disposal Reason']==='Shred','Modal must include the disposal reason');
 disposal_view_check(!isset($sections['revision']),'Disposal must not be labeled as proposed classification or revision');
 disposal_view_check($display['domain']==='hardcopy','Disposal must keep the physical document identity');
}
$document=new Document_detail_model();$document->db=new DisposalViewDB();$document->load=new class{function model($name){}};
$document->Document_model=new class{function config($domain){return ['table'=>$domain.'_documents'];}function scope($domain,$viewer){return 'd.created_by=7';}};
$document->Permission_model=new class{function for_user($viewer){return [];}};
$document->db->records=[['document_id'=>20,'disposal_action'=>'scratch','remarks'=>'Scratch','disposed_by_name'=>'Document Officer','disposed_at'=>'2026-10-13 13:00:00','previous_status'=>'active']];
$doc=['id'=>20,'title'=>'Disposed register','sequence_number'=>'HC-020','document_number'=>'SC-020','status'=>'disposed','creator_name'=>'Requester','created_at'=>'2026-10-01','updated_at'=>'2026-10-13','version'=>2];
foreach(['hardcopy','softcopy']as$domain){$document->db->calls=[];$result=$document->for_page($domain,[$doc],['id'=>7,'role_id'=>5],[]);
 $sections=array_column($result[20]['sections'],NULL,'key');disposal_view_check(isset($sections['disposal']),'Disposed document modal must retain its disposal record');
 disposal_view_check($sections['disposal']['fields']['Disposed By']==='Document Officer'&&$sections['disposal']['fields']['Disposal Reason']==='Scratch','Document modal must show recorded actor and readable reason');
 $concerns=array_values(array_filter($result[20]['sections'],static function($section){return !in_array($section['key'],['overview','storage','revision'],TRUE);}));
 disposal_view_check($concerns[0]['key']==='disposal','Disposed documents must open their disposal details before other concerns');
 $queries=array_values(array_filter($document->db->calls,static function($call){return $call[0]==='query'&&strpos($call[1][0],'FROM disposals')!==FALSE;}));
 disposal_view_check(count($queries)===1&&$queries[0][1][1]===[$domain,20],'Document disposal lookup must be batched and bound to its domain and page');
 disposal_view_check(strpos($queries[0][1][0],'d.created_by=7')!==FALSE,'Recorded disposal details must respect document access scope');
}
echo "Disposal modal labels, proposed/recorded details, request/task reuse and document scope passed.\n";
