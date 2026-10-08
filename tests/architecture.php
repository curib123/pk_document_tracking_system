<?php
declare(strict_types=1);
require dirname(__DIR__).'/application/bootstrap.php';
$failures=0; $count=0;
function verify(string $name, callable $check): void {
    global $failures,$count; ++$count;
    try { if (!$check()) throw new RuntimeException('Requirement not met'); echo "PASS $name\n"; }
    catch (Throwable $e) { ++$failures; echo "FAIL $name: {$e->getMessage()}\n"; }
}
verify('native CI3 libraries replace the custom src service directory', fn()=>is_file(PK_ROOT.'/application/libraries/Request_service.php') && !is_dir(PK_ROOT.'/application/src'));
verify('native service classes load without compatibility aliases', fn()=>class_exists('Request_service') && class_exists('Catalog_service') && !is_file(PK_ROOT.'/application/config/class_aliases.php'));
verify('native models can autoload on HTTP requests, not only CLI', fn()=>!str_contains(file_get_contents(PK_ROOT.'/application/bootstrap.php'), 'CI_Model\' && PHP_SAPI'));
verify('CI3 database configuration is available', fn()=>is_file(PK_ROOT.'/application/config/database.php'));
verify('domain persistence is implemented in native models', function(){
    foreach(['Catalog','Document','Workflow','Request','Transfer','File','Identity','Read'] as $name) {
        $path=PK_ROOT.'/application/models/'.$name.'_model.php';
        if (!is_file($path) || !preg_match('/extends (Repository_model|CI_Model)/',file_get_contents($path))) return false;
    }
    return true;
});
verify('business service libraries contain no SQL statements',function(){
    $files=glob(PK_ROOT.'/application/libraries/*_service.php'); if (!$files) return false;
    foreach($files as $file) if (preg_match('/[\'\"](?:SELECT |UPDATE |DELETE FROM |INSERT (?:INTO|IGNORE))/i',file_get_contents($file))) return false;
    return true;
});
verify('native module endpoints are distinct and fixed to the module',function(){
    $registry=new Endpoint_registry();
    $users=$registry->byPath('users/datatable');
    $soft=$registry->byPath('softcopy/direct');
    return $users['fixed']===['module'=>'users'] && $soft['fixed']===['domain'=>'softcopy'];
});
verify('unknown native endpoint paths are rejected',function(){
    try { (new Endpoint_registry())->byPath('../config'); } catch(Pk\Core\Problem $e){return $e->status===404;}
    return false;
});
verify('DataTables ordering is allowlisted and lengths are bounded',function(){
    $r=Datatable_service::normalize(['draw'=>'4','start'=>'10','length'=>'-1','order'=>[['column'=>'99','dir'=>'desc']],'search'=>['value'=>'forms']],['id','title']);
    return $r['draw']===4 && $r['limit']===25 && $r['offset']===10 && $r['sort']==='id' && $r['q']==='forms';
});
verify('DataTables offset is not rounded to a page boundary',function(){
    $r=Datatable_service::normalize(['start'=>3,'length'=>10],['id']); return $r['offset']===3 && $r['limit']===10;
});
verify('normal page queries remain supported',function(){
    $r=Datatable_service::normalize(['page'=>2,'limit'=>50,'q'=>'audit','sort'=>'id','direction'=>'asc'],['id','title']);
    return $r['offset']===50 && $r['limit']===50 && $r['direction']==='asc';
});
verify('shared PHP templates own reusable UI skeletons',function(){
    require_once PK_ROOT.'/application/helpers/ui_helper.php';
    ob_start();
    $initial_module='';
    require PK_ROOT.'/application/views/templates/header.php';
    require PK_ROOT.'/application/views/templates/footer.php';
    $html=ob_get_clean();
    foreach (['navigation','global-status','module-page-template','navigation-group-template',
        'record-card-template','data-table-template','modal-shell','login-workspace-template',
        'dashboard-workspace-template','field-lookup-template'] as $id) {
        if (!str_contains($html,'id="'.$id.'"')) return false;
    }
    foreach (Pk\Core\UiSchema::modules() as $key=>$module) {
        if (!is_file(PK_ROOT.'/application/views/pages/'.$key.'/index.php')) return false;
        if (!str_contains($html,'id="page-'.$key.'-template"')) return false;
    }
    return true;
});
verify('audit persistence is file-backed instead of a database table',function(){
    $context=file_get_contents(PK_ROOT.'/application/libraries/support/Context.php');
    $schema=file_get_contents(PK_ROOT.'/database/schema.sql');
    $read=file_get_contents(PK_ROOT.'/application/models/Read_model.php');
    return str_contains($context,'/storage/audit')
        && str_contains($context,'FILE_APPEND | LOCK_EX')
        && !str_contains($schema,'CREATE TABLE audit_logs')
        && str_contains($read,'auditListing');
});
verify('hardcopy holder is enforced server-side for ordinary requesters',function(){
    $service=file_get_contents(PK_ROOT.'/application/libraries/Document_service.php');
    return str_contains($service,"can('hardcopy.direct')")
        && str_contains($service,"can('requests.manage')")
        && str_contains($service,'$defaultHolder');
});
verify('view styles remain external and stylesheet links are centralized',function(){
    $authorizedStylesheets=[
        PK_ROOT.'/application/views/components/assets/styles.php',
        PK_ROOT.'/application/views/web/header.php',
    ];
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PK_ROOT.'/application/views')) as $file) {
        if (!$file->isFile()) continue;
        $html=file_get_contents($file->getPathname());
        if (preg_match('/<style\b|\bstyle\s*=/i',$html)) return false;
        if (preg_match('/rel=[\'\"]stylesheet/i',$html) && !in_array($file->getPathname(),$authorizedStylesheets,true)) return false;
    }
    return true;
});
verify('browser transport uses native controller endpoints',fn()=>is_file(PK_ROOT.'/public/assets/js/api.js') && str_contains(file_get_contents(PK_ROOT.'/public/assets/js/api.js'),'endpointRoutes'));
echo "$count architecture assertions; $failures failures\n";exit($failures?1:0);
