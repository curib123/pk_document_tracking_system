<?php
declare(strict_types=1);
define('BASEPATH',__DIR__);
require_once __DIR__.'/../application/services/audit/audit_service.php';

function auditExpect(bool $condition,string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root=sys_get_temp_dir().'/pk-audit-test-'.bin2hex(random_bytes(6));
$directory=$root.'/storage/audit';
mkdir($directory,0700,TRUE);
define('PK_ROOT',$root);
define('APPPATH',dirname(__DIR__).'/application/');
class MY_Controller
{
    public $allow=FALSE;
    public $permission;
    public $rendered;
    public $input;
    public $db;
    protected function require_permission($module,$action): void
    {
        $this->permission=[$module,$action];
        if (!$this->allow) throw new DomainException('Access denied.');
    }
    protected function table_state($sort): array
    {
        return ['sort'=>$sort,'q'=>'','status'=>'','from'=>'','to'=>'','page'=>1,'limit'=>10,'dir'=>'ASC'];
    }
    protected function render($title,$view,$data): void {$this->rendered=[$title,$view,$data];}
}
class AuditTestInput {public function get($key) {return NULL;}}
class AuditTestDB
{
    public function __call($method,$arguments) {return $this;}
    public function result_array(): array {return [['id'=>1,'name'=>'Ana Reviewer']];}
}
require_once APPPATH.'controllers/Audit.php';
$files=[];
$write=static function(string $name,string $contents) use($directory,&$files): void {
    file_put_contents($directory.'/'.$name,$contents);
    $files[]=$directory.'/'.$name;
};
try {
    $entries=[];
    for ($index=0;$index<25;$index++) $entries[]=[
        'timestamp'=>'2026-10-13T09:00:'.str_pad((string)$index,2,'0',STR_PAD_LEFT).'+08:00',
        'actor_id'=>$index%2+1,'action'=>'my-requests/hardcopy-disposal/save',
        'subject'=>'Hardcopy '.$index,'status'=>$index%2?'failed':'success',
        'metadata'=>['reference'=>'REQ-'.$index]
    ];
    $write('2026-10-13.json',json_encode($entries,JSON_THROW_ON_ERROR));
    $write('2026-10-12.json',json_encode([[
        'timestamp'=>'2026-10-12T08:00:00+08:00','actor_id'=>3,
        'action'=>'documents/hardcopy/dispose','subject'=>'Old log',
        'status'=>'success','metadata'=>[]
    ]],JSON_THROW_ON_ERROR));
    $write('2026-10-11.json','corrupt log');
    $write('private-archive.json',json_encode($entries,JSON_THROW_ON_ERROR));
    $write('2026-02-30.json',json_encode($entries,JSON_THROW_ON_ERROR));
    $write('.audit.lock','');

    $service=new Audit_service($directory);
    $filters=['q'=>'','status'=>'','from'=>'','to'=>'','page'=>1,'limit'=>10,'dir'=>'DESC'];
    $result=$service->listing($filters);
    auditExpect($result['total']===26 && count($result['rows'])===10,'Daily logs must be counted and paginated.');
    auditExpect($result['rows'][0]['subject']==='Hardcopy 24','Newest event must appear first.');
    auditExpect(count($result['warnings'])===1,'Corrupt files must be reported; non-daily filenames are ignored.');
    $last=$service->listing(array_merge($filters,['page'=>999]));
    auditExpect($last['page']===3 && count($last['rows'])===6,'An excessive page must resolve to the final page.');
    auditExpect($last['rows'][5]['subject']==='Old log','Final page must include the older daily log.');
    $ascending=$service->listing(array_merge($filters,['dir'=>'ASC']));
    auditExpect($ascending['rows'][0]['subject']==='Old log','Ascending order must reverse day and event order.');
    $filtered=$service->listing(array_merge($filters,['status'=>'failed','from'=>'2026-10-13','to'=>'2026-10-13']));
    auditExpect($filtered['total']===12 && !$filtered['warnings'],'Status and date range must filter before pagination.');
    auditExpect($service->listing(array_merge($filters,['q'=>'hardcopy 23']))['total']===1,'Search must match subject without case sensitivity.');
    auditExpect($service->listing(array_merge($filters,['q'=>'documents/hardcopy/dispose']))['total']===1,'Search must match the recorded action.');

    $controller=new Audit();
    try {$controller->index();throw new RuntimeException('Unauthorized audit browsing must be refused.');}
    catch (DomainException $e) {auditExpect($controller->permission===['audit','view'],'Audit access must use the existing audit/view permission.');}
    auditExpect($controller->rendered===NULL,'Unauthorized audit requests must not render log data.');
    $controller->allow=TRUE;$controller->input=new AuditTestInput();$controller->db=new AuditTestDB();
    $controller->index();
    auditExpect($controller->rendered[1]==='pages/administration/audit','Authorized audit browsing must render the audit page.');
    auditExpect($controller->rendered[2]['rows'][0]['actor_name']==='Ana Reviewer','The page must resolve actor IDs to user names.');
    auditExpect($controller->rendered[2]['table']['dir']==='DESC','Audit page must default to newest events first.');

    $before=hash_file('sha256',$directory.'/2026-10-13.json');
    $lock=fopen($directory.'/.audit.lock','rb');
    flock($lock,LOCK_EX);
    $locked=$service->listing($filters);
    flock($lock,LOCK_UN);fclose($lock);
    auditExpect(!$locked['rows'] && count($locked['warnings'])===1,'An unavailable audit lock must return a clear warning.');
    auditExpect(hash_file('sha256',$directory.'/2026-10-13.json')===$before,'Browsing must never modify audit logs.');
    $large=$directory.'/2026-10-14.json';
    $files[]=$large;$handle=fopen($large,'wb');ftruncate($handle,16777217);fclose($handle);
    $bounded=$service->listing(array_merge($filters,['from'=>'2026-10-14']));
    auditExpect(!$bounded['rows'] && count($bounded['warnings'])===1,'An oversized daily file must be rejected before reading.');
    auditExpect((new Audit_service($directory.'/missing'))->listing($filters)['total']===0,'An installation without logs must have an empty result.');
} finally {
    foreach ($files as $file) unlink($file);
    rmdir($directory);
    rmdir($root.'/storage');
    rmdir($root);
}
echo "Audit permission, actor names, daily-file filtering, pagination, corruption, read bounds and lock tests passed.\n";
