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
    public $catalog=[];
    public $queries=0;
    private $filters=[];
    private $table='';
    public function __construct(array $rows) {$this->rows=$rows;}
    public function select($columns,$escape=TRUE) {return $this;}
    public function from($table) {$this->table=$table;return $this;}
    public function join($table,$on,$type='inner') {return $this;}
    public function where($column,$value=NULL,$escape=TRUE) {$this->filters[$column]=$value;return $this;}
    public function where_in($column,$values) {$this->filters[$column]=$values;return $this;}
    public function get_where($table,$where): GrantResult
    {
        return new GrantResult([]);
    }
    public function __call($name,$arguments) {return $this;}
    public function get(): GrantResult
    {
        $this->queries++;
        $rows=$this->table==='workflow_steps ws'?[]:($this->table==='permissions'?$this->catalog:$this->rows);
        foreach ($this->filters as $column=>$value) {
            if (in_array($column,['rp.role_id','r.id'],TRUE))
                $rows=array_filter($rows,static function($row) use($value) {return in_array($row['role_id'],(array)$value);});
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
$plantMultiple=$plant+['role_ids'=>[5,7]];
$plantAdmin=$plant+['role_ids'=>[5,1]];
$staff=['id'=>40,'role_id'=>7,'role'=>'Staff'];
$denied=['role_id'=>5,'role_name'=>'plant_manager','active'=>1,'module_key'=>NULL,'action_key'=>NULL];
$allowed=['role_id'=>7,'role_name'=>'Staff','active'=>1,'module_key'=>'hardcopy','action_key'=>'direct'];

$model=grantModel([$denied,$allowed]);
grantCheck(!$model->allowed($plant,'hardcopy','direct'),
    'A role assigned to somebody else must not grant this user access.');
grantCheck($model->allowed($plantMultiple,'hardcopy','direct'),
    'A grant from a secondary assigned role must allow the action despite the primary role having no grant.');
grantCheck($model->allowed($staff,'hardcopy','direct'),
    'A primary assigned role must retain its own grants.');
grantCheck(!$model->allowed($plant,'hardcopy','delete') && !$model->allowed($plant,'softcopy','direct'),
    'A grant for another action or module must not allow the requested action.');
$menu=$model->for_user($plantMultiple);
grantCheck(!empty($menu['hardcopy']['direct']),'UI permissions must include grants from other roles.');
grantCheck(!$model->allowed(NULL,'hardcopy','direct') && $model->for_user(NULL)===[],
    'Global role grants must never authorize an unauthenticated visitor.');

$admin=['role_id'=>1,'role_name'=>'Administrator','active'=>1,'module_key'=>NULL,'action_key'=>NULL];
$model=grantModel([$denied,$admin]);
grantCheck(!$model->allowed($plant,'users','edit'),'An unassigned Administrator role must not grant access.');
grantCheck($model->allowed($plantAdmin,'users','edit') && $model->allowed($plantAdmin,'backups','view'),
    'plant_manager denied plus Administrator allowed must resolve to allowed.');
grantCheck(isset($model->for_user($plantAdmin)['*']),
    'The Administrator role must provide the same effective permissions to navigation and backend checks.');

$model=grantModel([$denied,array_merge($allowed,['active'=>0]),array_merge($admin,['active'=>0])]);
grantCheck(!$model->allowed($plantMultiple,'hardcopy','direct') && !$model->allowed($plantAdmin,'users','edit'),
    'Inactive roles must not grant access, including an inactive Administrator role.');
$model=grantModel([$denied]);
grantCheck(!$model->allowed($plant,'hardcopy','direct'),
    'Access must be denied when no active role grants the action.');

$model=grantModel([$denied,
    array_merge($allowed,['module_key'=>'requests','action_key'=>'manage']),
    array_merge($allowed,['module_key'=>'areas','action_key'=>'view'])]);
grantCheck($model->allowed($plantMultiple,'requests','approve') && $model->allowed($plantMultiple,'tasks','approve') &&
    $model->allowed($plantMultiple,'area','view'),'Existing module and action aliases must use the assigned-role grants.');
grantCheck(!empty($model->for_user($plantMultiple)['tasks']['view']),
    'Task navigation must use the combined request-management grant.');

echo "Assigned-role allow-union, unassigned-role denial, Administrator access, inactive roles and aliases passed.\n";
if (isset($grantConnection)) $grantConnection->close();

// Exercise the actual backup guard; an individual role-name check must not
// override an Administrator grant in the combined permission map.
if (!function_exists('show_error')) {
    function show_error($message,$code=500) {throw new RuntimeException((string)$message);}
}
class MY_Controller
{
    public $user,$Permission_model;
    protected function authenticate() {if (!$this->user) throw new RuntimeException('Sign in required.');}
    protected function require_permission($module,$action='view')
    {
        $this->authenticate();
        if (!$this->Permission_model->allowed($this->user,$module,$action))
            throw new RuntimeException('Permission denied.');
    }
}
require_once APPPATH.'controllers/Backups.php';
$backup=new Backups();$backup->user=$plantAdmin;
$backup->Permission_model=new Permission_model();
$backup->Permission_model->db=new GrantDatabase([$denied,$admin]);
$guard=(new ReflectionClass(Backups::class))->getMethod('administrator');$guard->setAccessible(TRUE);
$guard->invoke($backup);
$backup->Permission_model=new Permission_model();$backup->Permission_model->db=new GrantDatabase([$denied]);
$deniedGuard=FALSE;
try {$guard->invoke($backup);} catch (RuntimeException $e) {$deniedGuard=TRUE;}
grantCheck($deniedGuard,'The backup guard must refuse access when no active role grants it.');

// A role editor delegates the combined privileges, not just primary-role
// privileges. An action absent from every active role still cannot be given.
require_once APPPATH.'services/administration/administration_service.php';
function &get_instance() {return $GLOBALS['grantContext'];}
$grantContext=(object)['db'=>new GrantDatabase([]),'Permission_model'=>new Permission_model()];
$grantContext->Permission_model->db=new GrantDatabase([$denied,
    array_merge($allowed,['module_key'=>'roles','action_key'=>'edit'])]);
$grantContext->db->catalog=[['id'=>11,'module_key'=>'roles','action_key'=>'edit'],
    ['id'=>12,'module_key'=>'files','action_key'=>'delete']];
$administration=new Administration_service();
$delegate=(new ReflectionClass(Administration_service::class))->getMethod('ensure_delegation');$delegate->setAccessible(TRUE);
$delegate->invoke($administration,$plantMultiple,['id'=>7,'name'=>'Staff'],[11]);
$deniedDelegation=FALSE;
try {$delegate->invoke($administration,$plantMultiple,['id'=>7,'name'=>'Staff'],[12]);}
catch (DomainException $e) {$deniedDelegation=TRUE;}
grantCheck($deniedDelegation,'An action granted by no active role must remain unavailable for delegation.');
$grantContext->Permission_model=new Permission_model();$grantContext->Permission_model->db=new GrantDatabase([$denied,$admin]);
$delegate->invoke($administration,$plantAdmin,['id'=>1,'name'=>'Administrator'],[12]);
echo "Backup guard and delegation follow the same combined role grants.\n";
