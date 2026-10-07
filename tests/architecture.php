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
verify('legacy domain imports resolve to the native service implementation', fn()=>class_exists('Request_service') && class_exists(Pk\Services\RequestService::class) && is_a('Request_service',Pk\Services\RequestService::class,true));
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
    $users=$registry->resolve('list',['module'=>'users']);
    $soft=$registry->resolve('documents.direct',['domain'=>'softcopy']);
    return $users['path']==='users/datatable' && $users['fixed']===['module'=>'users'] && $soft['path']==='softcopy/direct';
});
verify('unknown operations are rejected before database initialization',function(){
    try { (new Endpoint_registry())->resolve('invented.action',[]); } catch(Pk\Core\Problem $e){return $e->status===404;}
    return false;
});
verify('unknown module selectors do not become controller names',function(){
    try {(new Endpoint_registry())->resolve('list',['module'=>'../config']);} catch(Pk\Core\Problem $e){return $e->status===404;}
    return false;
});
verify('DataTables ordering is allowlisted and lengths are bounded',function(){
    $r=Datatable_service::normalize(['draw'=>'4','start'=>'10','length'=>'-1','order'=>[['column'=>'99','dir'=>'desc']],'search'=>['value'=>'forms']],['id','title']);
    return $r['draw']===4 && $r['limit']===25 && $r['offset']===10 && $r['sort']==='id' && $r['q']==='forms';
});
verify('DataTables offset is not rounded to a page boundary',function(){
    $r=Datatable_service::normalize(['start'=>3,'length'=>10],['id']); return $r['offset']===3 && $r['limit']===10;
});
verify('normal page queries stay backward compatible',function(){
    $r=Datatable_service::normalize(['page'=>2,'limit'=>50,'q'=>'audit','sort'=>'id','direction'=>'asc'],['id','title']);
    return $r['offset']===50 && $r['limit']===50 && $r['direction']==='asc';
});
verify('shared PHP templates own the table and native modal shell',function(){
    return is_file(PK_ROOT.'/application/views/templates/header.php') && is_file(PK_ROOT.'/application/views/templates/footer.php') && is_file(PK_ROOT.'/application/views/components/data_table.php') && is_file(PK_ROOT.'/application/views/modal/shell.php');
});
verify('the view layer remains unstyled',function(){
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PK_ROOT.'/application/views')) as $file) if($file->isFile() && preg_match('/<style\b|\bstyle\s*=|rel=[\'\"]stylesheet/i',file_get_contents($file->getPathname())))return false;
    return true;
});
verify('browser transport uses native controller endpoints',fn()=>is_file(PK_ROOT.'/public/assets/js/api.js') && str_contains(file_get_contents(PK_ROOT.'/public/assets/js/api.js'),'endpointRoutes'));
verify('original schema is unchanged by architecture refactoring',fn()=>hash_file('sha256',PK_ROOT.'/database/schema.sql')===trim(file_get_contents(__DIR__.'/schema-baseline.sha256')));
echo "$count architecture assertions; $failures failures\n";exit($failures?1:0);
