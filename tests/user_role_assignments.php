<?php
declare(strict_types=1);
define('PK_ROOT',dirname(__DIR__));
define('BASEPATH',PK_ROOT.'/vendor/codeigniter/framework/system/');
define('APPPATH',PK_ROOT.'/application/');define('ENVIRONMENT','testing');
function log_message($level,$message) {if ($level==='error') fwrite(STDERR,$message."\n");}
function show_error($message) {throw new RuntimeException((string)$message);}
function &get_instance() {return $GLOBALS['rolesContext'];}
require_once BASEPATH.'core/Common.php';require_once BASEPATH.'core/Model.php';require_once BASEPATH.'database/DB.php';
require APPPATH.'config/database.php';
$connection=DB($db['default'],TRUE);
function assignmentCheck(bool $value,string $message): void {if (!$value) throw new RuntimeException($message);}
function assignmentInsert($db,$table,$data): void {assignmentCheck($db->insert($table,$data)!==FALSE,'Fixture failed: '.$table);}
foreach (['users','roles','permissions','role_permissions','requests','workflow_steps','workflow_history','workflow_versions',
    'softcopy_documents','hardcopy_documents','transfers','status_history'] as $table) {
    assignmentCheck($connection->query('CREATE TEMPORARY TABLE `roles_test_'.$table.'` LIKE `'.$table.'`')!==FALSE &&
        $connection->query('ALTER TABLE `roles_test_'.$table.'` RENAME TO `'.$table.'`')!==FALSE,'Cannot isolate '.$table);
}
assignmentCheck($connection->query('CREATE TEMPORARY TABLE user_roles (user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(user_id,role_id)) ENGINE=InnoDB')!==FALSE,'Cannot isolate assigned roles.');
$connection->data_cache['table_names']=$connection->list_tables();
$connection->data_cache['table_names'][]='user_roles';
class AssignmentLoad
{
    public function model($name) {
        $ci=&get_instance();
        require_once APPPATH.'models/'.$name.'.php';
        if (!isset($ci->$name)) $ci->$name=new $name();
    }
}
$rolesContext=(object)['db'=>$connection,'load'=>new AssignmentLoad()];
foreach (['Identity_model','Permission_model','Request_model','Notification_model','Dashboard_model'] as $name)
    $rolesContext->load->model($name);
require_once APPPATH.'services/administration/administration_service.php';
foreach ([1=>'Administrator',5=>'Plant Manager',7=>'Staff'] as $id=>$name)
    assignmentInsert($connection,'roles',['id'=>$id,'name'=>$name,'active'=>1]);
foreach ([1=>1,10=>5,11=>5,12=>5,13=>5] as $id=>$role) {
    assignmentInsert($connection,'users',['id'=>$id,'username'=>'role-test-'.$id,'first_name'=>'Role',
        'last_name'=>(string)$id,'position_title'=>'Reviewer','role_id'=>$role,'active'=>1,
        'password_hash'=>'unusable-test-fixture','require_password_change'=>0]);
    assignmentInsert($connection,'user_roles',['user_id'=>$id,'role_id'=>$role]);
}
assignmentInsert($connection,'user_roles',['user_id'=>11,'role_id'=>7]);
assignmentInsert($connection,'user_roles',['user_id'=>13,'role_id'=>1]);
assignmentInsert($connection,'permissions',['id'=>80,'name'=>'Hardcopy view','module_key'=>'hardcopy',
    'module_label'=>'Hardcopy','action_key'=>'view','action_label'=>'View']);
assignmentInsert($connection,'role_permissions',['role_id'=>7,'permission_id'=>80]);
$identity=$rolesContext->Identity_model;
$multiple=$identity->active_user(11);
assignmentCheck(($multiple['role_ids']??[])===[5,7],'Identity must load all active roles assigned to the user.');
assignmentCheck($rolesContext->Permission_model->allowed($multiple,'hardcopy','view'),
    'A secondary assigned role must grant its action.');
assignmentCheck(!$rolesContext->Permission_model->allowed($identity->active_user(10),'hardcopy','view'),
    'Another user role assignment must grant nothing to a Plant Manager-only account.');
assignmentCheck($rolesContext->Permission_model->allowed($identity->active_user(13),'users','edit'),
    'Plant Manager denied plus assigned Administrator allowed must resolve to allowed.');
assignmentCheck(!$rolesContext->Permission_model->allowed($identity->active_user(10),'users','edit'),
    'An Administrator assigned to someone else must not grant user management.');

$service=new Administration_service();
$target=$connection->get_where('users',['id'=>10])->row_array();
$post=$target+['role_ids'=>[5,7]];
$service->save_user($post,1);
assignmentCheck($identity->active_user(10)['role_ids']===[5,7],'Saving a user must persist selected secondary roles.');
$post=$connection->get_where('users',['id'=>10])->row_array()+['role_ids'=>[5]];
$service->save_user($post,1);
assignmentCheck($identity->active_user(10)['role_ids']===[5],'Removing a secondary role must revoke its membership.');
$before=$connection->get_where('users',['id'=>10])->row_array();
$bad=$before+['role_ids'=>[5,999]];$rejected=FALSE;
try {$service->save_user($bad,1);} catch (DomainException $e) {$rejected=TRUE;}
assignmentCheck($rejected && $connection->get_where('users',['id'=>10])->row_array()===$before,
    'Invalid secondary roles must fail without partially updating the account.');
$self=$connection->get_where('users',['id'=>13])->row_array()+['role_ids'=>[5]];$rejected=FALSE;
try {$service->save_user($self,13);} catch (DomainException $e) {$rejected=TRUE;}
assignmentCheck($rejected && $identity->active_user(13)['role_ids']===[1,5],
    'A user must not remove their own secondary Administrator role.');

require_once APPPATH.'services/administration/role_usage.php';
$usage=Role_usage::for_roles($connection,[7]);
assignmentCheck($usage[7]['user_count']===1,'Secondary assignments must prevent removing or deactivating an assigned role.');
assignmentInsert($connection,'requests',['id'=>50,'reference'=>'REQ-ROLES','type'=>'hardcopy_create',
    'requested_by'=>12,'payload'=>'{}','status'=>'submitted','current_node'=>'step_1']);
assignmentInsert($connection,'workflow_steps',['request_id'=>50,'node_key'=>'step_1','label'=>'Staff review',
    'assignment'=>'{"type":"role","value":7}','candidates'=>'[]','status'=>'active']);
[$tasks]=$rolesContext->Request_model->listing('hardcopy',$multiple,TRUE,'','',1,20);
assignmentCheck(count($tasks)===1,'A secondary role approver must see their active task.');
assignmentCheck($rolesContext->Notification_model->for_user($multiple)['tasks_total']===1,
    'A secondary role approval must contribute to task badges.');
assignmentCheck($rolesContext->Dashboard_model->summary($multiple)['metrics']['pending_tasks']===1,
    'Dashboard approval counts must include secondary role assignments.');
$rolesContext->load->model('Administration_model');
[$filteredUsers]=$rolesContext->Administration_model->users('','',1,20,['role'=>7]);
assignmentCheck(count($filteredUsers)===1 && $filteredUsers[0]['role_ids']===[5,7],
    'User listing and role filters must include secondary assignments.');
require_once APPPATH.'services/request/request_service.php';
$requests=new Request_service();$rejected=FALSE;
try {$requests->decide('hardcopy',50,$identity->active_user(10),'rejected','Not assigned');}
catch (DomainException $e) {$rejected=TRUE;}
assignmentCheck($rejected,'An unassigned role must not authorize a workflow decision.');
$requests->decide('hardcopy',50,$multiple,'rejected','Secondary-role review');
assignmentCheck($connection->get_where('requests',['id'=>50])->row_array()['status']==='rejected',
    'A secondary assigned role must authorize its workflow decision.');
$versionBefore=(int)$connection->get_where('users',['id'=>11])->row_array()['session_version'];
$service->save_role(['id'=>7,'name'=>'Staff','active'=>1,'permissions'=>[80]],1);
assignmentCheck((int)$connection->get_where('users',['id'=>11])->row_array()['session_version']===$versionBefore+1,
    'Role permission updates must invalidate sessions of secondary members.');
$service->deactivate_user(1,13);
assignmentCheck($identity->active_user(1)===NULL && $identity->active_user(13)!==NULL,
    'A secondary Administrator must count when preserving the last active administrator.');
assignmentCheck($identity->find_login('role-test-11')['role_ids']===[5,7],
    'Login must resolve the same assigned roles as subsequent authenticated requests.');
$connection->where('id',5)->update('roles',['active'=>0]);
assignmentCheck($identity->active_user(11)['role_ids']===[7],
    'An inactive primary role must not disable a user with another active assigned role.');
assignmentCheck($identity->active_user(10)===NULL,'A user with no active assigned role must not authenticate.');
$connection->close();
echo "Stored multi-role identity, assignment saves, revocation, lifecycle protection and secondary-role tasks passed.\n";
