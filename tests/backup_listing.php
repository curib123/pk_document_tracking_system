<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
require_once dirname(__DIR__) . '/application/services/backup/backup_listing.php';
$items=[];
for ($n=1;$n<=32;$n++) $items[]=['name'=>sprintf('backup-%02d.zip',$n),'kind'=>$n%2?'Manual':'Automatic','created_at'=>$n,'size'=>$n*1024];
$state=['q'=>'','type'=>'','page'=>99,'limit'=>10,'sort'=>'created_at','dir'=>'DESC'];
$result=Backup_listing::page($items,$state);
if ($result['total']!==32 || $result['page']!==4 || count($result['rows'])!==2 || $result['rows'][0]['name']!=='backup-02.zip')
    throw new RuntimeException('Backup listing must clamp the page and sort before slicing.');
$result=Backup_listing::page($items,array_replace($state,['q'=>'BACKUP-03','page'=>1]));
if ($result['total']!==1 || $result['rows'][0]['name']!=='backup-03.zip') throw new RuntimeException('Search must be case insensitive.');
$result=Backup_listing::page($items,array_replace($state,['type'=>'Automatic','page'=>1,'limit'=>25,'sort'=>'name','dir'=>'ASC']));
if ($result['total']!==16 || count($result['rows'])!==16 || $result['rows'][0]['name']!=='backup-02.zip') throw new RuntimeException('Type filtering must precede pagination.');
$result=Backup_listing::page([],array_replace($state,['page'=>9]));
if ($result['page']!==1 || $result['rows']!==[]) throw new RuntimeException('Empty backup lists must retain valid pagination.');
echo "Backup filtering, sorting, page clamping and limits passed.\n";
