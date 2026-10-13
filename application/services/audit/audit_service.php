<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Read private daily audit arrays without changing files or creating locks. */
class Audit_service
{
    private $directory;
    private const MAX_FILE_BYTES=16777216;

    public function __construct(string $directory)
    {
        $this->directory=rtrim($directory,'/\\');
    }

    public function listing(array $filters): array
    {
        $result=['rows'=>[],'total'=>0,'page'=>1,'warnings'=>[]];
        if (!is_dir($this->directory)) return $result;
        if (is_link($this->directory)) {
            $result['warnings'][]='Audit storage is unavailable.';
            return $result;
        }
        $lock=NULL;
        $lockPath=$this->directory.'/.audit.lock';
        if (file_exists($lockPath)) {
            if (is_link($lockPath) || !($lock=@fopen($lockPath,'rb'))) {
                $result['warnings'][]='Audit logs could not be opened.';
                return $result;
            }
            $acquired=FALSE;
            for ($attempt=0;$attempt<11;$attempt++) {
                if (flock($lock,LOCK_SH|LOCK_NB)) {$acquired=TRUE;break;}
                if ($attempt<10) usleep(20000);
            }
            if (!$acquired) {
                fclose($lock);
                $result['warnings'][]='Audit logs are currently in use. Please try again.';
                return $result;
            }
        }
        try {
            return $this->read($filters,$result);
        } finally {
            if (is_resource($lock)) {flock($lock,LOCK_UN);fclose($lock);}
        }
    }

    private function read(array $filters,array $result): array
    {
        $files=@scandir($this->directory);
        if ($files===FALSE) {
            $result['warnings'][]='Audit storage could not be read.';
            return $result;
        }
        $descending=($filters['dir']??'DESC')!=='ASC';
        if ($descending) $files=array_reverse($files);
        $limit=in_array((int)($filters['limit']??10),[10,25,50,100],TRUE)?(int)$filters['limit']:10;
        $page=max(1,(int)($filters['page']??1));
        $offset=($page-1)*$limit;
        $tail=[];
        foreach ($files as $file) {
            if (!preg_match('/^(\d{4}-\d{2}-\d{2})\.json$/D',$file,$matches)) continue;
            $day=$matches[1];
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$day);
            if (!$date || $date->format('Y-m-d')!==$day) continue;
            if (!empty($filters['from']) && $day<$filters['from']) continue;
            if (!empty($filters['to']) && $day>$filters['to']) continue;
            $path=$this->directory.'/'.$file;
            $size=@filesize($path);
            if (is_link($path) || !is_file($path) || $size===FALSE || $size>$this->readBudget()) {
                $result['warnings'][]='Audit file '.$day.' is unavailable or exceeds the safe reading limit.';
                continue;
            }
            $json=@file_get_contents($path,FALSE,NULL,0,self::MAX_FILE_BYTES+1);
            $entries=$json===FALSE?NULL:json_decode($json,TRUE);
            unset($json);
            if (!is_array($entries) || ($entries && array_keys($entries)!==range(0,count($entries)-1))) {
                $result['warnings'][]='Audit file '.$day.' could not be read.';
                continue;
            }
            $invalid=FALSE;
            for ($index=$descending?count($entries)-1:0;
                 $descending?$index>=0:$index<count($entries);
                 $index+=$descending?-1:1) {
                $entry=$this->entry($entries[$index],$day);
                if ($entry===NULL) {$invalid=TRUE;continue;}
                if (!$this->matches($entry,$filters)) continue;
                $entry['id']=$result['total']+1;
                if ($result['total']>=$offset && $result['total']<$offset+$limit) $result['rows'][]=$entry;
                $tail[]=$entry;
                if (count($tail)>$limit) array_shift($tail);
                $result['total']++;
            }
            if ($invalid) $result['warnings'][]='Some entries in audit file '.$day.' could not be read.';
            unset($entries);
        }
        $result['page']=min($page,max(1,(int)ceil($result['total']/$limit)));
        if ($result['page']!==$page) {
            $remaining=$result['total']%$limit?:$limit;
            $result['rows']=array_slice($tail,-$remaining);
        }
        return $result;
    }

    /** JSON arrays consume more memory after decoding; reserve that headroom. */
    private function readBudget(): int
    {
        $setting=trim((string)ini_get('memory_limit'));
        if ($setting==='' || $setting==='-1') return self::MAX_FILE_BYTES;
        $memory=(int)$setting;
        $unit=strtolower(substr($setting,-1));
        if ($unit==='g') $memory*=1073741824;
        elseif ($unit==='m') $memory*=1048576;
        elseif ($unit==='k') $memory*=1024;
        return min(self::MAX_FILE_BYTES,max(0,(int)(($memory-memory_get_usage(TRUE)-16777216)/12)));
    }

    private function entry($entry,string $day): ?array
    {
        if (!is_array($entry)) return NULL;
        foreach (['timestamp','actor_id','action','status'] as $key) {
            if (!isset($entry[$key]) || !is_scalar($entry[$key])) return NULL;
        }
        $timestamp=(string)$entry['timestamp'];
        $parsed=DateTimeImmutable::createFromFormat(DATE_ATOM,$timestamp);
        if (!$parsed || $parsed->format(DATE_ATOM)!==$timestamp || substr($timestamp,0,10)!==$day ||
            !ctype_digit((string)$entry['actor_id']) ||
            !in_array($entry['status'],['success','failed'],TRUE) ||
            (isset($entry['subject']) && !is_scalar($entry['subject']))) return NULL;
        return ['timestamp'=>$timestamp,'actor_id'=>(int)$entry['actor_id'],
            'action'=>(string)$entry['action'],'subject'=>(string)($entry['subject']??''),
            'status'=>$entry['status'],'metadata'=>is_array($entry['metadata']??NULL)?$entry['metadata']:[]];
    }

    private function matches(array $entry,array $filters): bool
    {
        if (!empty($filters['status']) && $entry['status']!==$filters['status']) return FALSE;
        $query=(string)($filters['q']??'');
        return $query==='' || mb_stripos(implode(' ',[
            $entry['timestamp'],$entry['actor_id'],$entry['action'],$entry['subject'],$entry['status']
        ]),$query)!==FALSE;
    }
}
