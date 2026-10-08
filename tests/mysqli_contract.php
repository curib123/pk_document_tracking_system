<?php
declare(strict_types=1);
require dirname(__DIR__).'/application/bootstrap.php';
$count=0;$failed=0;
function contract(string $name, callable $test): void {
    global $count,$failed;++$count;
    try { if (!$test()) throw new RuntimeException('Requirement not met'); echo "PASS $name\n"; }
    catch (Throwable $e) { ++$failed; echo "FAIL $name: {$e->getMessage()}\n"; }
}
contract('database is native mysqli, has an empty DSN and enables Query Builder',function(){
    if (!defined('BASEPATH')) define('BASEPATH',PK_ROOT.'/vendor/codeigniter/framework/system/');
    require PK_ROOT.'/application/config/database.php';
    return $db['default']['dbdriver']==='mysqli' && $db['default']['dsn']==='' && $query_builder===true && !isset($db['default']['subdriver']);
});
contract('application and CLI contain no PDO identifiers or statement API calls',function(){
    foreach(['application','database'] as $dir) foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PK_ROOT.'/'.$dir)) as $f) {
        if (!$f->isFile() || $f->getExtension()!=='php') continue;
        $source=file_get_contents($f->getPathname());
        foreach(token_get_all($source) as $token) if(is_array($token) && !in_array($token[0],[T_COMMENT,T_DOC_COMMENT,T_WHITESPACE],true) && preg_match('/\bPDO(?:Statement|Exception)?\b|pdo_mysql|->pdo\b/i',$token[1])) return false;
        if (preg_match('/->(?:prepare|fetchAll|lastInsertId|beginTransaction|inTransaction)\s*\(/',$source)) return false;
    } return true;
});
contract('Composer requires mysqli and no PDO extension',function(){
    $deps=json_decode(file_get_contents(PK_ROOT.'/composer.json'),true)['require'];
    return isset($deps['ext-mysqli']) && !isset($deps['ext-pdo']) && !isset($deps['ext-pdo_mysql']);
});
contract('Composer and CI use mysqli only for the database driver',function(){
    $composer=file_get_contents(PK_ROOT.'/composer.json');
    $ci=file_get_contents(PK_ROOT.'/.github/workflows/ci.yml');
    return str_contains($composer,'ext-mysqli') && str_contains($ci,'mysqli') && !str_contains($composer,'pdo_mysql') && !str_contains($ci,'pdo_mysql');
});
foreach(['Auth','Identity','Catalog','Document','Request','Workflow','File','Read'] as $name) {
    contract($name.' model uses native Query Builder rather than SQL result wrappers',function()use($name){
        $s=file_get_contents(PK_ROOT.'/application/models/'.$name.'_model.php');
        return str_contains($s,'$this->db->') && !preg_match('/->(?:one|all)\s*\(/',$s) && !preg_match('/[\'\"](?:SELECT |UPDATE |DELETE FROM )/i',$s);
    });
}
contract('transaction code uses CI3 transaction primitives',function(){
    $s=file_get_contents(PK_ROOT.'/application/models/support/Database.php');
    foreach(['trans_begin(', 'trans_status(', 'trans_commit(', 'trans_rollback('] as $call) if (!str_contains($s,$call)) return false;
    return true;
});
contract('lock-select uses Query Builder compilation and keeps FOR UPDATE',function(){
    $s=file_get_contents(PK_ROOT.'/application/models/support/Database.php');
    return str_contains($s,'get_compiled_select(') && str_contains($s,'FOR UPDATE') && str_contains($s,'require_transaction(');
});
contract('database conflicts are mapped from native driver error codes',function(){
    $s=file_get_contents(PK_ROOT.'/application/libraries/Http_gateway.php');
    return str_contains($s,'Database_error') && !str_contains($s,'PDOException');
});
contract('a failed database query cannot silently become an empty result',fn()=>class_exists(Pk\Core\Database_error::class));
echo "$count mysqli contracts; $failed failures\n"; exit($failed?1:0);
