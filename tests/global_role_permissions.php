<?php
declare(strict_types=1);
$useDatabase=in_array('--database',$argv,TRUE);
define('BASEPATH',$useDatabase?dirname(__DIR__).'/vendor/codeigniter/framework/system/':__DIR__);
define('APPPATH',dirname(__DIR__).'/application/');
class CI_Model {public $db;}
require_once APPPATH.'models/Permission_model.php';
if ($useDatabase) {
    define('ENVIRONMENT','testing');
    function log_message($level,$message) {}
    function show_error($message) {throw new RuntimeException((string)$message);}
    require_once BASEPATH.'core/Common.php';
    require_once BASEPATH.'database/DB.php';
    require APPPATH.'config/database.php';
    $grantConnection=DB($db['default'],TRUE);
    // Temporary tables isolate fixtures from installed accounts and grants.
    foreach (['roles','permissions','role_permissions','requests','workflow_steps'] as $table) {
        if ($grantConnection->query('CREATE TEMPORARY TABLE `grant_test_'.$table.'` LIKE `'.$table.'`')===FALSE ||
            $grantConnection->query('ALTER TABLE `grant_test_'.$table.'` RENAME TO `'.$table.'`')===FALSE)
            throw new RuntimeException('Could not isolate '.$table.': '.$grantConnection->error()['message']);
    }
}

function grantCheck(bool $value,string $message): void
{
    if (!$value) throw new RuntimeException($message);
}
class GrantResult
{
    private $rows;
    public function __construct(array $rows) {$this->rows=$rows;}
    public function result_array(): array {return $this->rows;}
    public function num_rows(): int {return count($this->rows);}
}
// The database double represents joined role/grant rows. Query filters are
// honored so a missing active-role filter or a primary-role filter is caught.
class GrantDatabase
{
    public $rows;
    public $queries=0;
    private $filters=[];
    private $table='';
    public function __construct(array $rows) {$this->rows=$rows;}
    public function select($columns,$escape=TRUE) {return $this;}
    public function from($table) {$this->table=$table;return $this;}
    public function join($table,$on,$type='inner') {return $this;}
    public function where($column,$value=NULL,$escape=TRUE) {$this->filters[$column]=$value;return $this;}
    public function __call($name,$arguments) {return $this;}
    public function get(): GrantResult
    {
        $this->queries++;
        $rows=$this->table==='workflow_steps ws'?[]:$this->rows;
        foreach ($this->filters as $column=>$value) {
            if (in_array($column,['rp.role_id','r.id'],TRUE))
                $rows=array_filter($rows,static function($row) use($value) {return $row['role_id']==$value;});
            if ($column==='r.active')
                $rows=array_filter($rows,static function($row) use($value) {return $row['active']==$value;});
        }
        $this->filters=[];
        return new GrantResult(array_values($rows));
    }
}
function grantModel(array $rows): Permission_model
{
    $model=new Permission_model();
    if (!isset($GLOBALS['grantConnection'])) {
        $model->db=new GrantDatabase($rows);return $model;
    }
    $connection=$GLOBALS['grantConnection'];
    foreach (['role_permissions','permissions','roles'] as $table)
        grantCheck($connection->query('DELETE FROM `'.$table.'`')!==FALSE,'Fixture reset failed.');
    $roles=[];$catalog=[];
    foreach ($rows as $row) {
        if (!isset($roles[$row['role_id']])) {
            grantCheck($connection->insert('roles',['id'=>$row['role_id'],'name'=>$row['role_name'],
                'active'=>$row['active']])!==FALSE,'Role fixture failed.');
            $roles[$row['role_id']]=TRUE;
        }
        if ($row['module_key']===NULL || $row['action_key']===NULL) continue;
        $key=$row['module_key'].':'.$row['action_key'];
        if (!isset($catalog[$key])) {
            grantCheck($connection->insert('permissions',['name'=>$key,'module_key'=>$row['module_key'],
                'module_label'=>$row['module_key'],'action_key'=>$row['action_key'],
                'action_label'=>$row['action_key']])!==FALSE,'Permission fixture failed.');
            $catalog[$key]=(int)$connection->insert_id();
        }
        grantCheck($connection->insert('role_permissions',[
            'role_id'=>$row['role_id'],'permission_id'=>$catalog[$key]])!==FALSE,'Grant fixture failed.');
    }
    $model->db=$connection;return $model;
}
$plant=['id'=>30,'role_id'=>5,'role'=>'plant_manager'];
$staff=['id'=>40,'role_id'=>7,'role'=>'Staff'];
$denied=['role_id'=>5,'role_name'=>'plant_manager','active'=>1,'module_key'=>NULL,'action_key'=>NULL];
$allowed=['role_id'=>7,'role_name'=>'Staff','active'=>1,'module_key'=>'hardcopy','action_key'=>'direct'];

$model=grantModel([$denied,$allowed]);
grantCheck($model->allowed($plant,'hardcopy','direct'),
    'A grant from another role must allow the action despite the user role having no grant.');
grantCheck($model->allowed($staff,'hardcopy','direct'),
    'The combined permission result must apply to every signed-in user.');
grantCheck(!$model->allowed($plant,'hardcopy','delete') && !$model->allowed($plant,'softcopy','direct'),
    'A grant for another action or module must not allow the requested action.');
$menu=$model->for_user($plant);
grantCheck(!empty($menu['hardcopy']['direct']),'UI permissions must include grants from other roles.');
grantCheck(!$model->allowed(NULL,'hardcopy','direct') && $model->for_user(NULL)===[],
    'Global role grants must never authorize an unauthenticated visitor.');

$admin=['role_id'=>1,'role_name'=>'Administrator','active'=>1,'module_key'=>NULL,'action_key'=>NULL];
$model=grantModel([$denied,$admin]);
grantCheck($model->allowed($plant,'users','edit') && $model->allowed($plant,'backups','view'),
    'plant_manager denied plus Administrator allowed must resolve to allowed.');
grantCheck(isset($model->for_user($plant)['*']),
    'The Administrator role must provide the same effective permissions to navigation and backend checks.');

$model=grantModel([$denied,array_merge($allowed,['active'=>0]),array_merge($admin,['active'=>0])]);
grantCheck(!$model->allowed($plant,'hardcopy','direct') && !$model->allowed($plant,'users','edit'),
    'Inactive roles must not grant access, including an inactive Administrator role.');
$model=grantModel([$denied]);
grantCheck(!$model->allowed($plant,'hardcopy','direct'),
    'Access must be denied when no active role grants the action.');

$model=grantModel([$denied,
    array_merge($allowed,['module_key'=>'requests','action_key'=>'manage']),
    array_merge($allowed,['module_key'=>'areas','action_key'=>'view'])]);
grantCheck($model->allowed($plant,'requests','approve') && $model->allowed($plant,'tasks','approve') &&
    $model->allowed($plant,'area','view'),'Existing module and action aliases must use the combined grants.');
grantCheck(!empty($model->for_user($plant)['tasks']['view']),
    'Task navigation must use the combined request-management grant.');

echo "Global role allow-union, Administrator access, inactive roles, aliases and anonymous denial passed.\n";
if (isset($grantConnection)) $grantConnection->close();
