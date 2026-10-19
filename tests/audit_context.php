<?php
define('BASEPATH', __DIR__);
$file = __DIR__ . '/../application/services/audit/audit_context.php';
if (!is_file($file)) throw new RuntimeException('Movement audit context is missing.');
require_once $file;
class ContextDB {
    public $rows = ['hardcopy_documents'=>[['id'=>7,'holder_id'=>2,'location_id'=>3,'title'=>'Manual','status'=>'active','password_hash'=>'NEVER LOG']]];
    private $where = [];
    public function where($key,$value) {$this->where[$key]=[$value];return $this;}
    public function where_in($key,$values) {$this->where[$key]=$values;return $this;}
    public function limit($value) {return $this;}
    public function get($table) {
        $rows = array_values(array_filter($this->rows[$table] ?? [], function($row) {
            foreach ($this->where as $key=>$values) if (!in_array($row[$key] ?? NULL,$values)) return FALSE;
            return TRUE;
        }));
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
