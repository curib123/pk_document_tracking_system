<?php
declare(strict_types=1);
require dirname(__DIR__).'/application/bootstrap.php';
use Pk\Core\{Database,Context};
if (getenv('PK_TEST_DB')!=='1' || !str_ends_with(getenv('DB_DATABASE') ?: '', '_test')) throw new RuntimeException('Use a dedicated test database.');
if (($argv[1] ?? '')==='worker') {
    $ctx=new Context(Database::connect()); $values=[];
    for($i=0;$i<10;$i++) $values[]=$ctx->db->transaction(fn()=>$ctx->sequence($argv[2],'TEST-'));
    echo json_encode($values,JSON_THROW_ON_ERROR); exit;
}
$key='concurrency_'.bin2hex(random_bytes(10)); $jobs=[]; $values=[];
for($i=0;$i<8;$i++) {
    $process=proc_open([PHP_BINARY,__FILE__,'worker',$key],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start concurrency worker.');
    fclose($pipes[0]); $jobs[]=[$process,$pipes];
}
foreach($jobs as [$process,$pipes]) {
    $result=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]);fclose($pipes[2]);
    if (proc_close($process)!==0) throw new RuntimeException('Concurrency worker failed: '.$error);
    $values=[...$values,...json_decode($result,true,32,JSON_THROW_ON_ERROR)];
}
if (count($values)!==80 || count(array_unique($values))!==80) throw new RuntimeException('Duplicate or missing identifiers under concurrent writes.');
$db=Database::connect(); $row=$db->one('SELECT value FROM sequences WHERE sequence_key=?',[$key]);
if ((int)$row['value']!==80) throw new RuntimeException('Lost sequence increments.');
$db->query('DELETE FROM sequences WHERE sequence_key=?',[$key]);
echo "PASS 80 unique identifiers from 8 concurrent MySQL writers; no lost increments.\n";
