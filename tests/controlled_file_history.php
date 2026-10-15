<?php
declare(strict_types=1);
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__).'/application/');
class CI_Model {public $db,$load,$Document_model,$Permission_model;}
function html_escape($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function site_url($path){return '/'.$path;}
function historyCheck(bool $value,string $message):void {if (!$value) throw new RuntimeException($message);}
require APPPATH.'models/Document_detail_model.php';
$document=['id'=>9,'title'=>'Revised policy','document_number'=>'SC-9','series_number'=>'PK','category_id'=>7,
    'category_name'=>'Policies','status'=>'active','version'=>1,'created_at'=>'2026-10-01','updated_at'=>'2026-10-13','creator_name'=>'Requester'];
$old=['id'=>700,'document_id'=>9,'original_name'=>'previous.txt','size'=>23,'mime_type'=>'text/plain',
    'created_at'=>'2026-10-01','first_name'=>'Records','last_name'=>'Officer','status'=>'superseded','is_current'=>0];
$current=array_replace($old,['id'=>710,'original_name'=>'revised.txt','created_at'=>'2026-10-13','status'=>'approved','is_current'=>1]);
$files=[9=>[$old,$current]];
$detail=new Document_detail_model();$detail->load=new class {public function model($name){}};
$detail->db=new class {public function query($sql,$params){return new class {public function result_array(){return [];}};}};
$detail->Document_model=new class {public function config($domain){return ['table'=>'softcopy_documents'];}public function scope($domain,$viewer){return '1=1';}};
$detail->Permission_model=new class {public function for_user($viewer){return ['files'=>['history'=>TRUE,'download'=>TRUE,'print'=>TRUE]];}};
$display=$detail->for_page('softcopy',[$document],['id'=>20,'role_id'=>1],$files)[9];
$section=array_column($display['sections'],NULL,'key')['files'];
historyCheck(count($section['fields'])===2,'The document viewer must preserve old controlled-file metadata.');
historyCheck(empty($section['links'][0]['url']) && $section['links'][1]['url']==='/files/download/710',
    'Only the current revision may expose a Download link.');
historyCheck(strpos(array_values($section['fields'])[0],'Superseded')!==FALSE,
    'Previous controlled files must be visibly identified as superseded.');

class HistoryViewCaptured extends RuntimeException {}
class HistoryViewHarness {
    public $permissions = ['files'=>['view'=>TRUE,'history'=>TRUE,'download'=>TRUE,'print'=>TRUE]];
    public $load,$data;public function __construct(){$this->load=$this;}
    public function view($name,$data){if ($name==='reusable_components/reusable_datatable') {$this->data=$data;throw new HistoryViewCaptured();}}
    public function render($document,$files,$current):array {
        $cfg=['domain'=>'softcopy','fields'=>['document_number','title','series_number','category_id'],'title'=>'Softcopy'];
        $rows=[$document];$permissions=$this->permissions;$user=['id'=>20,'name'=>'Requester'];$is_administrator=FALSE;
        $folder_browser=['tree'=>['nodes'=>[]],'folder'=>'','items'=>[]];$folder_value='';$total=1;$owner_options=[];
        $table=['q'=>'','status'=>'','page'=>1,'limit'=>25,'sort'=>'title','dir'=>'ASC','layout'=>'table','owner'=>'','from'=>'','to'=>''];
        $folder_params=$table;
        $file_histories=[9=>$files];$file_access=[9=>TRUE];$latest_files=$current?[9=>$current]:[];$document_details=[];
        ob_start();try{include APPPATH.'view/pages/documents/shared_index.php';}
        catch(HistoryViewCaptured $error){}finally{ob_end_clean();}
        return $this->data['dt_rows'][0]['buttons'];
    }
}
$view=new HistoryViewHarness();
$buttons=array_column($view->render($document,[$old,$current],$current),NULL,'type');
historyCheck(empty($buttons['history']['files'][0]['url']) && $buttons['history']['files'][1]['url']==='/files/download/710',
    'The File History dialog must omit old download URLs.');
historyCheck(strpos($buttons['history']['files'][0]['detail'],'Superseded')!==FALSE &&
    $buttons['download']['url']==='files/download/710','The register download must point to the current controlled file.');
$buttons=array_column($view->render($document,[$old],NULL),NULL,'type');
historyCheck(isset($buttons['history']) && !isset($buttons['download']),
    'History metadata must remain viewable when no current controlled download is available.');
echo "Controlled-file details and history preserve records with downloads only for the current revision.\n";
$view->permissions = ['files'=>['view'=>TRUE]];
$buttons=array_column($view->render($document,[$old,$current],$current),NULL,'type');
historyCheck(!isset($buttons['history'], $buttons['print'], $buttons['download']),
    'File access alone cannot grant history, print or download actions.');
$detail->Permission_model=new class {public function for_user($viewer){return ['files'=>['view'=>TRUE]];}};
$display=$detail->for_page('softcopy',[$document],['id'=>20,'role_id'=>1],$files)[9];
$section=array_column($display['sections'],NULL,'key')['files'];
historyCheck(count($section['fields'])===1 && empty($section['links'][0]['url']) && empty($section['links'][0]['print_url']),
    'The detail viewer must hide superseded metadata and denied action URLs.');
