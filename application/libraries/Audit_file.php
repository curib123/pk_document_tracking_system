<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Per-day valid JSON-array audit files, outside public/, never database rows.
 * Records only action metadata, never passwords, request bodies or attachments.
 */
class Audit_file
{
    public function append($actorId,$action,$subject=NULL,$meta=[])
    {
        $dir=PK_ROOT.'/storage/audit';
        if (!is_dir($dir) && !mkdir($dir,0700,TRUE)) return FALSE;
        $day=(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))
            ->format('Y-m-d');
        $path=$dir.'/'.$day.'.json';
        $lock=fopen($dir.'/.audit.lock','c');
        if (!$lock) return FALSE;
        try {
            if (!flock($lock,LOCK_EX)) return FALSE;
            $entries=is_file($path)?json_decode((string)file_get_contents($path),TRUE):[];
            if (!is_array($entries)) return FALSE;
            $entries[]=[
                'timestamp'=>(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))
                    ->format(DATE_ATOM),
                'actor_id'=>(int)$actorId,
                'action'=>substr((string)$action,0,120),
                'subject'=>$subject===NULL?NULL:(string)$subject,
                'status'=>'success',
                'metadata'=>is_array($meta)?$meta:[]
            ];
            $json=json_encode($entries,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
            if ($json===FALSE) return FALSE;
            $temp=tempnam($dir,'.audit-');
            if (!$temp) return FALSE;
            if (file_put_contents($temp,$json."\n")===FALSE) {
                @unlink($temp);return FALSE;
            }
            chmod($temp,0600);
            if (!rename($temp,$path)) {@unlink($temp);return FALSE;}
            return TRUE;
        } finally {
            flock($lock,LOCK_UN);
            fclose($lock);
        }
    }
}
