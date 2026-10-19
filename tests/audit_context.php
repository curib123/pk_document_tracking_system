<?php
define('BASEPATH', __DIR__);
$file = __DIR__ . '/../application/services/audit/audit_context.php';
if (!is_file($file)) throw new RuntimeException('Movement audit context is missing.');
require_once $file;
class ContextDB {
    public $rows = ['hardcopy_documents'=>[['id'=>7,'holder_id'=>2,'location_id'=>3,'title'=>'Manual','status'=>'active','password_hash'=>'NEVER LOG']]];
    private $where = [];
    private $maxRows = NULL;
    public function where($key,$value) {$this->where[$key]=[$value];return $this;}
    public function where_in($key,$values) {$this->where[$key]=$values;return $this;}
    public function limit($value) {$this->maxRows=$value;return $this;}
    public function get($table) {
        $rows = array_values(array_filter($this->rows[$table] ?? [], function($row) {
            foreach ($this->where as $key=>$values) if (!in_array($row[$key] ?? NULL,$values)) return FALSE;
            return TRUE;
        }));
        if ($this->maxRows !== NULL) $rows=array_slice($rows,0,$this->maxRows);
        $this->maxRows=NULL;
        $this->where=[];
        return new class($rows) {private $rows;function __construct($rows){$this->rows=$rows;}function result_array(){return $this->rows;}};
    }
}
$db = new ContextDB();
$audit = new Audit_context($db, 'admin/document-assignments/save', ['document_domain'=>'hardcopy','hardcopy_id'=>7,'recipient_id'=>4,'password'=>'SECRET','confirmed'=>'yes']);
$db->rows['hardcopy_documents'][0]['holder_id']=4;
$meta = $audit->finish();
if ($meta['before']['hardcopy_documents:7']['holder_id'] !== 2 || $meta['after']['hardcopy_documents:7']['holder_id'] !== 4)
    throw new RuntimeException('Assignment audits must identify both holders and the affected document.');
if (strpos(json_encode($meta), 'SECRET') !== FALSE || strpos(json_encode($meta), 'NEVER LOG') !== FALSE)
    throw new RuntimeException('Audit entries must never contain passwords or unapproved fields.');
if ($meta['input']['recipient_id'] !== 4) throw new RuntimeException('Movement recipient is missing.');
echo "Movement audit snapshots retain IDs and before/after custody without credentials.\n";

$db->rows = [
    'transfers'=>[['id'=>7,'request_id'=>12,'hardcopy_id'=>9,'current_holder_id'=>2,'recipient_id'=>4,'status'=>'in_transit']],
    'requests'=>[['id'=>12,'hardcopy_id'=>9,'status'=>'approved']],
    'hardcopy_documents'=>[['id'=>9,'holder_id'=>2,'sequence_number'=>'COPY-OLD','retention_end_date'=>'2026-12-31']],
    'access_grants'=>[['id'=>4,'domain'=>'hardcopy','document_id'=>9,'user_id'=>2,'status'=>'access_granted']],
];
$audit = new Audit_context($db,'my-tasks/hardcopy-transfer/accept',['id'=>7]);
$db->rows['requests'][0]['status']='completed';
$db->rows['access_grants'][0]['status']='revoked';
$db->rows['hardcopy_documents'][0]['sequence_number']='COPY-NEW';
$meta=$audit->finish();
if (($meta['before']['requests:12']['status'] ?? '') !== 'approved' || ($meta['after']['requests:12']['status'] ?? '') !== 'completed' ||
    ($meta['after']['access_grants:4']['status'] ?? '') !== 'revoked' ||
    ($meta['before']['hardcopy_documents:9']['sequence_number'] ?? '') !== 'COPY-OLD' ||
    ($meta['after']['hardcopy_documents:9']['sequence_number'] ?? '') !== 'COPY-NEW')
    throw new RuntimeException('Transfer audit must include linked request completion, grant revocation and changed document metadata.');

$db->rows = ['requests'=>[['id'=>8,'softcopy_id'=>9,'payload'=>'{"revision_file_id":5}']],
    'softcopy_documents'=>[['id'=>9,'document_number'=>'DOC-9']],
    'files'=>[['id'=>5,'document_id'=>NULL,'status'=>'pending'],['id'=>6,'domain'=>'softcopy','document_id'=>9,'status'=>'approved']],
    'softcopy_revisions'=>[['id'=>3,'document_id'=>9,'file_id'=>6,'new_revision_level'=>'01']]];
$audit = new Audit_context($db,'my-tasks/softcopy/decide',['id'=>8,'decision'=>'approved']);
$db->rows['files'][0]['status']='approved';$db->rows['files'][1]['status']='superseded';
$db->rows['softcopy_revisions'][0]['new_revision_level']='02';
$meta=$audit->finish();
if (($meta['before']['files:5']['status'] ?? '') !== 'pending' || ($meta['after']['files:6']['status'] ?? '') !== 'superseded' ||
    ($meta['after']['softcopy_revisions:3']['new_revision_level'] ?? '') !== '02')
    throw new RuntimeException('Revision audits must include staged, superseded files and revision metadata.');
echo "Transfer request/grant changes, document numbers, staged files and revisions are fully audited.\n";

$db->rows=['softcopy_documents'=>[['id'=>9,'status'=>'active']], 'assignments'=>[]];
for ($i=1;$i<=2200;$i++) $db->rows['assignments'][]=['id'=>$i,'softcopy_id'=>9,'user_id'=>$i,'active'=>1];
$audit=new Audit_context($db,'admin/document-assignments/save',['document_domain'=>'softcopy','softcopy_id'=>9,'recipient_id'=>3000]);
$db->rows['assignments'][]=['id'=>2201,'softcopy_id'=>9,'user_id'=>3000,'active'=>1];
$meta=$audit->finish();
if (!isset($meta['after']['assignments:2201']) || count($meta['after'])!==2202 || $meta['truncated'])
    throw new RuntimeException('Related assignments must never be silently truncated before the affected recipient.');
echo "Large related assignment histories retain the actual changed recipient without silent truncation.\n";
