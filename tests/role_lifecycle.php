<?php
declare(strict_types=1);
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__).'/application/');
require_once APPPATH.'services/administration/administration_service.php';
function role_check(bool $value,string $message):void {if(!$value)throw new RuntimeException($message);}
function role_reject(callable $fn,string $message):void {try{$fn();}catch(DomainException $e){return;}throw new RuntimeException($message);}
class RoleResult {private $rows;function __construct($rows){$this->rows=$rows;}function result_array(){return $this->rows;}function row_array(){return $this->rows[0]??NULL;}}
class RoleDB {
 public $roles=[1=>['id'=>1,'name'=>'Administrator','active'=>1,'version'=>1],2=>['id'=>2,'name'=>'Unused role','active'=>1,'version'=>1]],$grants=[1=>[7,8],2=>[7]],$users=[],$workflows=[],$requests=[],$steps=[],$commits=0,$rollbacks=0,$valid=TRUE;
 private $table,$where=[],$snapshot;
 function trans_begin(){$this->snapshot=[$this->roles,$this->grants];}function trans_commit(){$this->commits++;}function trans_rollback(){[$this->roles,$this->grants]=$this->snapshot;$this->rollbacks++;}function trans_status(){return $this->valid;}
 function query($sql,$args=[]){return new RoleResult([]);}
 function select($value,$escape=TRUE){return $this;}function from($value){$this->table=$value;return $this;}function where($key,$value=NULL,$escape=TRUE){$this->where[$key]=$value;return $this;}function where_in($key,$value){return $this;}function where_not_in($key,$value){return $this;}function group_by($value){return $this;}function join($a,$b,$type='inner'){return $this;}
 function get_where($table,$where){$this->where=[];if($table==='roles')return new RoleResult(isset($this->roles[$where['id']])?[$this->roles[$where['id']]]:[]);if($table==='role_permissions')return new RoleResult(array_map(static function($id){return ['permission_id'=>$id];},$this->grants[$where['role_id']]??[]));return new RoleResult([]);}
 function get(){
  $table=$this->table;$this->where=[];
  if($table==='users'){ $counts=[];foreach($this->users as $user)$counts[$user['role_id']]=($counts[$user['role_id']]??0)+1;$out=[];foreach($counts as$id=>$count)$out[]=['role_id'=>$id,'user_count'=>$count];return new RoleResult($out); }
  if($table==='workflow_versions')return new RoleResult(array_values(array_filter($this->workflows,static function($row){return $row['status']==='draft'||$row['is_default'];})));
  if($table==='requests')return new RoleResult(array_values(array_filter($this->requests,static function($row){return !in_array($row['status'],['completed','cancelled','rejected'],TRUE);})));
  if($table==='workflow_steps s')return new RoleResult($this->steps);
  return new RoleResult([]);
 }
 function count_all_results($table=NULL){$this->where=[];return 1;}
 function update($table,$data=[]){if($table==='roles')$this->roles[$this->where['id']]=array_merge($this->roles[$this->where['id']],$data);$this->where=[];return TRUE;}
 function set($key,$value,$escape=TRUE){$this->where['set_'.$key]=$value;return $this;}
 function delete($table){if($table==='role_permissions')unset($this->grants[$this->where['role_id']]);if($table==='roles')unset($this->roles[$this->where['id']]);$this->where=[];return TRUE;}
 function insert($table,$data){if($table==='role_permissions')$this->grants[$data['role_id']][]=$data['permission_id'];return TRUE;}
}
class RoleIdentity {public $actor=['id'=>10,'role_id'=>1,'role'=>'Administrator'];function active_user($id){return $this->actor;}}
class RolePermissions {public $allowed=TRUE,$permissions=['*'=>['*'=>TRUE]];function allowed($actor,$module,$action){return $this->allowed;}function for_user($actor){return $this->permissions;}}
class RoleLoad {function model($name){}}
$ci=(object)['db'=>new RoleDB(),'Identity_model'=>new RoleIdentity(),'Permission_model'=>new RolePermissions(),'load'=>new RoleLoad()];
function &get_instance(){global $ci;return $ci;}
role_check(method_exists(Administration_service::class,'remove_role'),'Unused roles need a removal operation');
role_check(method_exists(Administration_service::class,'deactivate_role'),'Unused roles need a deactivation operation');
function role_reset(){global $ci;$ci->db=new RoleDB();$ci->Identity_model=new RoleIdentity();$ci->Permission_model=new RolePermissions();return new Administration_service();}
$service=role_reset();$service->deactivate_role(2,10);role_check($ci->db->roles[2]['active']===0&&$ci->db->roles[2]['version']===2,'Deactivation must update status and version');role_check($ci->db->grants[2]===[7],'Deactivation must keep permissions');
$service->remove_role(2,10);role_check(!isset($ci->db->roles[2])&&!isset($ci->db->grants[2]),'Removal must clean role and permission links');
foreach([1,0,999]as$id)foreach(['remove_role','deactivate_role']as$method){$service=role_reset();role_reject(static function()use($service,$id,$method){$service->$method($id,10);},'Reserved or missing roles cannot be changed');}
foreach([0,1]as$active){$service=role_reset();$ci->db->users=[['role_id'=>2,'active'=>$active]];foreach(['remove_role','deactivate_role']as$method)role_reject(static function()use($service,$method){$service->$method(2,10);},'Assigned roles must remain intact');role_reject(static function()use($service){$service->save_role(['id'=>2,'name'=>'Unused role','active'=>0,'permissions'=>[7]],10);},'Edit Role must enforce the same unused-role restriction');role_check($ci->db->roles[2]['active']===1&&$ci->db->grants[2]===[7],'Failed changes must leave assignments intact');}
$graph=json_encode(['steps'=>[['approver'=>['type'=>'role','value'=>2]]]]);
foreach([['draft',0],['published',1]]as[$status,$default]){$service=role_reset();$ci->db->workflows=[['id'=>3,'status'=>$status,'is_default'=>$default,'graph'=>$graph]];role_reject(static function()use($service){$service->remove_role(2,10);},'Current workflow references must protect roles');}
foreach(['returned','submitted','approved']as$status){$service=role_reset();$ci->db->requests=[['id'=>4,'status'=>$status,'snapshot'=>$graph]];role_reject(static function()use($service){$service->deactivate_role(2,10);},'Unfinished snapshots must protect roles');}
$service=role_reset();$ci->db->steps=[['request_id'=>4,'assignment'=>json_encode(['type'=>'role','value'=>2])]];role_reject(static function()use($service){$service->remove_role(2,10);},'Pending legacy role assignments must protect roles');
$service=role_reset();$ci->db->workflows=[['id'=>3,'status'=>'published','is_default'=>0,'graph'=>$graph]];$ci->db->requests=[['id'=>4,'status'=>'completed','snapshot'=>$graph]];$service->remove_role(2,10);role_check(!isset($ci->db->roles[2]),'Historical snapshots must remain intact without blocking unused roles');
$service=role_reset();$ci->db->workflows=[['id'=>3,'status'=>'draft','is_default'=>0,'graph'=>json_encode(['steps'=>[['approver'=>['type'=>'user','value'=>2]]]])]];$service->remove_role(2,10);role_check(!isset($ci->db->roles[2]),'User IDs must not be mistaken for role IDs');
$service=role_reset();$ci->Permission_model->allowed=FALSE;foreach(['remove_role','deactivate_role']as$method)role_reject(static function()use($service,$method){$service->$method(2,10);},'Role changes need the corresponding management permission');
$service=role_reset();$ci->Identity_model->actor=['id'=>10,'role_id'=>1,'role'=>'Manager'];$ci->db->grants[1]=[7];$ci->db->grants[2]=[8];$service->remove_role(2,10);role_check(!isset($ci->db->roles[2]),'An Administrator grant from the combined roles must allow role management');
$service=role_reset();$ci->Identity_model->actor=['id'=>10,'role_id'=>1,'role'=>'Manager'];$ci->Permission_model->permissions=['roles'=>['delete'=>TRUE]];$ci->db->grants[2]=[8];role_reject(static function()use($service){$service->remove_role(2,10);},'Delegation must reject privileges absent from every active role');
$service=role_reset();$ci->db->valid=FALSE;role_reject(static function()use($service){$service->remove_role(2,10);},'Database failure must roll back');role_check(isset($ci->db->roles[2],$ci->db->grants[2])&&$ci->db->commits===0,'Failed removal must preserve role and permissions');
class RoleViewCaptured extends RuntimeException {}
class RoleViewHarness {
 public $load,$captured;
 function __construct(){$this->load=$this;}
 function view($name,$data){$this->captured=$data['dt_rows'];throw new RoleViewCaptured();}
 function render($permissions){
  $rows=[['id'=>1,'name'=>'Administrator','active'=>1,'user_count'=>0,'workflow_count'=>0,'request_count'=>0],['id'=>2,'name'=>'Unused','active'=>1,'user_count'=>0,'workflow_count'=>0,'request_count'=>0],['id'=>3,'name'=>'Inactive unused','active'=>0,'user_count'=>0,'workflow_count'=>0,'request_count'=>0],['id'=>4,'name'=>'Assigned','active'=>1,'user_count'=>1,'workflow_count'=>0,'request_count'=>0],['id'=>5,'name'=>'Workflow reviewer','active'=>1,'user_count'=>0,'workflow_count'=>1,'request_count'=>0],['id'=>6,'name'=>'Pending reviewer','active'=>1,'user_count'=>0,'workflow_count'=>0,'request_count'=>1]];
  $grants=[];$table=['q'=>'','status'=>'','page'=>1,'limit'=>25,'sort'=>'name','dir'=>'ASC'];$total=count($rows);
  ob_start();try{include APPPATH.'view/pages/roles_and_permission/index.php';}catch(RoleViewCaptured $e){}finally{ob_end_clean();}return array_column($this->captured,NULL,'id');
 }
}
$view=new RoleViewHarness();$rows=$view->render(['*'=>[]]);
role_check(array_column($rows[2]['buttons'],'label')===['Deactivate','Remove'],'Active unused roles need inline deactivation and removal');
role_check(array_column($rows[3]['buttons'],'label')===['Remove'],'Inactive unused roles need removal only');
foreach([1,4,5,6]as$id)role_check(!array_column($rows[$id]['buttons'],'label'),'Reserved and referenced roles must not offer lifecycle actions');
$rows=$view->render(['roles'=>['view'=>TRUE,'edit'=>TRUE]]);role_check(array_column($rows[2]['buttons'],'label')===['Deactivate'],'Deactivation requires edit permission');
$rows=$view->render(['roles'=>['view'=>TRUE,'delete'=>TRUE]]);role_check(array_column($rows[2]['buttons'],'label')===['Remove'],'Removal must be available with delete permission independently of edit');
$rows=$view->render(['roles'=>['view'=>TRUE]]);role_check(!array_column($rows[2]['buttons'],'label'),'Read-only roles must not offer mutations');
echo "Unused-role lifecycle and assignment protection checks passed.\n";
