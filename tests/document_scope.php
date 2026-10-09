<?php
define('BASEPATH', __DIR__);
require dirname(__DIR__).'/application/services/security/document_scope.php';
function check($yes, $label) {if (!$yes) throw new RuntimeException($label);}
check(Document_scope::predicate('softcopy',0,[]) === '1=0','anonymous scope');
check(Document_scope::predicate('hardcopy',7,['*'=>[]]) === '1=1','administrator scope');
check(Document_scope::predicate('softcopy',7,['documents'=>['access_all'=>true]]) === '1=1','existing access_all');
check(Document_scope::predicate('softcopy',7,['documents'=>['view_all'=>true]]) === '1=1','register view_all');
check(Document_scope::predicate('softcopy',7,['documents'=>['request_catalog'=>true]],'d',true) === '1=1','catalog allowed deliberately');
check(Document_scope::predicate('softcopy',7,['documents'=>['request_catalog'=>true]]) !== '1=1','catalog is not register access');
$scoped=Document_scope::predicate('softcopy',7,['documents'=>['view_assigned'=>true,'view_granted'=>true]]);
check(str_contains($scoped,'d.created_by=7') && str_contains($scoped,'assignments') && str_contains($scoped,'access_grants'),'owner assignment grant scope');
check(str_contains($scoped,'revoked_at IS NULL') && str_contains($scoped,'expires_at >= CURRENT_TIMESTAMP'),'expired/revoked exclusion');
check(!str_contains(Document_scope::predicate('softcopy',7,[]),'access_grants'),'grant capability required');
check(str_contains(Document_scope::predicate('hardcopy',7,[]),'d.holder_id=7'),'physical custody scope');
foreach ([['bad',7,[],'d'],['softcopy',7,[],'d;DROP TABLE users']] as $args) {
    try {Document_scope::predicate(...$args); throw new RuntimeException('Unsafe SQL argument accepted');}
    catch (InvalidArgumentException $expected) {}
}
check(Document_scope::file_predicate(7,[])==='1=0','file capability required');
check(Document_scope::file_predicate(7,['documents'=>['view_all'=>true]])==='1=0','register access never grants bytes');
check(Document_scope::file_predicate(7,['files'=>['view_all'=>true]])==='1=1','explicit file view all');
check(Document_scope::file_predicate(7,['files'=>['view'=>true],'documents'=>['view_all'=>true]])!=='1=1','metadata global is not file global');
echo "Document scope policy passed (16 metadata and file access boundaries).\n";

check(Document_scope::write_predicate('softcopy',7,[])==='1=0','direct capability required');
check(!str_contains(Document_scope::write_predicate('softcopy',7,['softcopy'=>['direct'=>true],
    'documents'=>['view_all'=>true,'view_granted'=>true]]),'access_grants'),'read grant is never write delegation');
check(Document_scope::write_predicate('hardcopy',7,['hardcopy'=>['direct'=>true],
    'documents'=>['access_all'=>true]])==='d.holder_id=7','hardcopy editing requires custody even with register access');
check(Document_scope::write_predicate('hardcopy',7,['*'=>true])==='1=1','administrator custody override');
echo "Four direct-write versus read-only scope rules passed.\n";
