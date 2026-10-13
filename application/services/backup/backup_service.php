<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Offline-friendly, encrypted, signed disaster-recovery snapshots.
 * Database backup/import uses the local XAMPP/MariaDB client binaries.
 * All backups are kept outside public/. Never expose a ZIP by direct URL.
 */
final class Backup_service
{
    private $root;
    private $directory;
    private $timezone;
    private $maxUpload = 2147483648; // PHP upload_max_filesize may be lower.
    private $maxExpanded = 21474836480; // 20 GiB defensive ZIP ceiling.

    public function __construct($root)
    {
        $this->root=rtrim(str_replace('\\','/',(string)$root),'/');
        $configured=trim((string)(getenv('PK_BACKUP_DIR')?:''));
        $this->directory=$configured!==''?rtrim(str_replace('\\','/',$configured),'/'):
            $this->root.'/storage/backups';
        if ($this->directory==='' || strpos($this->directory,"\0")!==FALSE)
            throw new DomainException('Backup directory is invalid.');
        $this->timezone=new DateTimeZone('Asia/Manila');
        if (!is_dir($this->directory) && !mkdir($this->directory,0700,TRUE))
            throw new DomainException('Cannot create the private backup directory.');
        $real=realpath($this->directory);
        $public=realpath($this->root.'/public');
        if (!$real || ($public && $this->inside($real,$public)))
            throw new DomainException('Backups must stay outside the public web directory.');
        $rootReal=realpath($this->root);
        if (!$rootReal) throw new DomainException('Application directory is unavailable.');
        // An archive directory inside live data would be skipped by
        // sourceFiles(), silently omitting stored documents or audit logs.
        // Inside the application, ONLY storage/backups (and its descendants)
        // may contain backup archives. External private volumes are allowed.
        $safeInternal=$this->root.'/storage/backups';
        if ($this->inside($rootReal,$real) ||
            ($this->inside($real,$rootReal) && !$this->inside($real,$safeInternal)))
            throw new DomainException('Backup destination overlaps live application or private data.');
        $this->directory=str_replace('\\','/',$real);
        if (!is_writable($this->directory)) throw new DomainException('Backup directory is not writable.');
    }

    public function directory(): string { return $this->directory; }
    public function configuration(): array
    {
        return ['zip'=>class_exists('ZipArchive'),
            'signing'=>strlen((string)getenv('PK_BACKUP_SIGNING_KEY'))>=32,
            'password'=>strlen((string)getenv('PK_BACKUP_PASSWORD'))>=12,
            'dump'=>is_file($this->binary('PK_MYSQLDUMP','mysqldump')),
            'mysql'=>is_file($this->binary('PK_MYSQL','mysql'))];
    }
    public function list_backups(): array
    {
        $items=[];
        foreach (glob($this->directory.'/pk-dts-*.zip')?:[] as $file) {
            if (!is_file($file) || is_link($file)) continue;
            $items[]=['name'=>basename($file),'size'=>(int)filesize($file),
                'created_at'=>(int)filemtime($file),
                'kind'=>strpos(basename($file),'pk-dts-auto-')===0?'Automatic':
                    (strpos(basename($file),'pk-dts-recovery-')===0?'Recovery':'Manual')];
        }
        usort($items,static function($a,$b){return $b['created_at']<=>$a['created_at'];});
        return $items;
    }
    public function backup($kind='manual'): array
    {
        if (!in_array($kind,['manual','auto','recovery'],TRUE))
            throw new DomainException('Invalid backup type.');
        $lock=$this->lock();
        try { return $this->createLocked($kind); }
        finally { $this->unlock($lock); }
    }
    private function createLocked($kind): array
    {
        $this->requirements();
        $now=new DateTimeImmutable('now',$this->timezone);
        $name='pk-dts-'.$kind.'-'.$now->format('Ymd-His').'-'.bin2hex(random_bytes(5)).'.zip';
        $tempDir=$this->temporaryDirectory();
        $tempArchive=$this->directory.'/.'.bin2hex(random_bytes(12)).'.zip.tmp';
        $sqlPath=$tempDir.'/database.sql';
        $zip=new ZipArchive();
        try {
            $this->dumpDatabase($sqlPath);
            if ($zip->open($tempArchive,ZipArchive::CREATE|ZipArchive::EXCL)!==TRUE)
                throw new DomainException('Unable to start the backup ZIP.');
            $entries=[];
            $files=$this->sourceFiles();
            $files['database.sql']=$sqlPath;
            foreach ($files as $archive=>$path) {
                if (!is_file($path) || !is_readable($path))
                    throw new DomainException('A backup source file cannot be read.');
                $bytes=(int)filesize($path);
                if (!$zip->addFile($path,$archive))
                    throw new DomainException('Could not include a source file in the archive.');
                if (!$zip->setEncryptionName($archive,ZipArchive::EM_AES_256,$this->password()))
                    throw new DomainException('ZIP AES-256 encryption is unavailable.');
                $hash=hash_file('sha256',$path);
                if (!$hash) throw new DomainException('Cannot checksum a backup file.');
                $entries[]=['path'=>$archive,'size'=>$bytes,'sha256'=>$hash];
            }
            $manifest=json_encode(['format'=>'pk-dts-full-v1','date'=>$now->format(DATE_ATOM),
                'timezone'=>'Asia/Manila','database'=>$this->databaseName(),
                'files'=>$entries,'mode'=>'full-application-and-private-data'],
                JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
            if (!$manifest) throw new DomainException('Cannot write the archive manifest.');
            $signature=hash_hmac('sha256',$manifest,$this->signingKey());
            foreach (['manifest.json'=>$manifest,'manifest.sig'=>$signature] as $path=>$text) {
                if (!$zip->addFromString($path,$text) ||
                    !$zip->setEncryptionName($path,ZipArchive::EM_AES_256,$this->password()))
                    throw new DomainException('Failed to protect backup metadata.');
            }
            if (!$zip->close()) throw new DomainException('Could not finish backup ZIP.');
            $zip=NULL;
            @chmod($tempArchive,0600);
            if (!rename($tempArchive,$this->directory.'/'.$name))
                throw new DomainException('Cannot publish the completed archive.');
            return ['name'=>$name,'size'=>(int)filesize($this->directory.'/'.$name),
                'created_at'=>$now->getTimestamp(),'kind'=>$kind];
        } finally {
            if ($zip instanceof ZipArchive) $zip->close();
            @unlink($tempArchive);
            $this->removeDirectory($tempDir);
        }
    }

    public function receiveUpload(array $upload): string
    {
        $this->requirements();
        if (($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK ||
            empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name']))
            throw new DomainException('Select a valid ZIP file for import.');
        $size=(int)($upload['size']??0);
        if ($size<1 || $size>$this->maxUpload)
            throw new DomainException('Backup ZIP exceeds the server upload limit (2 GiB maximum).');
        if (strtolower((string)pathinfo((string)($upload['name']??''),PATHINFO_EXTENSION))!=='zip')
            throw new DomainException('Only backup ZIP archives are accepted.');
        $temp=$this->directory.'/.upload-'.bin2hex(random_bytes(12));
        if (!move_uploaded_file($upload['tmp_name'],$temp))
            throw new DomainException('Unable to store uploaded backup.');
        @chmod($temp,0600);
        try {
            $this->verify($temp);
            $name='pk-dts-manual-import-'.(new DateTimeImmutable('now',$this->timezone))
                ->format('Ymd-His').'-'.bin2hex(random_bytes(5)).'.zip';
            if (!rename($temp,$this->directory.'/'.$name))
                throw new DomainException('Cannot save the verified uploaded archive.');
            return $name;
        } finally { @unlink($temp); }
    }
    public function resolve($name): string
    {
        if (!is_string($name) ||
            !preg_match('/^pk-dts-(auto|manual|manual-import|recovery)-[0-9]{8}-[0-9]{6}-[a-f0-9]{10}\.zip$/',$name))
            throw new DomainException('Invalid backup filename.');
        $path=$this->directory.'/'.$name;
        if (!is_file($path) || is_link($path) || !is_readable($path))
            throw new DomainException('Backup ZIP is unavailable.');
        return $path;
    }
    public function verify($file): array
    {
        $this->requirements();
        $zip=new ZipArchive();
        if ($zip->open($file,ZipArchive::RDONLY)!==TRUE)
            throw new DomainException('Backup ZIP is invalid or unreadable.');
        try {
            $zip->setPassword($this->password());
            $manifest=$zip->getFromName('manifest.json');
            $signature=$zip->getFromName('manifest.sig');
            if (!is_string($manifest)||!is_string($signature) ||
                !hash_equals(hash_hmac('sha256',$manifest,$this->signingKey()),trim($signature)))
                throw new DomainException('Backup signature or password is invalid.');
            $data=json_decode($manifest,TRUE);
            if (!is_array($data)||($data['format']??'')!=='pk-dts-full-v1'||
                ($data['database']??'')!==$this->databaseName() ||
                !is_array($data['files']??NULL))
                throw new DomainException('Backup does not belong to this database or uses an unknown format.');
            if ($zip->numFiles>100000) throw new DomainException('Backup contains too many files.');
            $declared=[];$total=0;
            foreach ($data['files'] as $item) {
                $name=$item['path']??NULL;
                $size=$item['size']??NULL;
                $hash=$item['sha256']??NULL;
                if (!is_string($name) || !self::safeEntry($name) ||
                    !is_int($size) || $size<0 || !is_string($hash) ||
                    !preg_match('/^[a-f0-9]{64}$/',$hash) || isset($declared[$name]))
                    throw new DomainException('Backup manifest contains invalid file metadata.');
                $declared[$name]=$item;
                $total+=$size;
                if ($total>$this->maxExpanded)
                    throw new DomainException('Backup is too large to restore safely.');
            }
            if (!isset($declared['database.sql']))
                throw new DomainException('Database dump is missing.');
            if ($zip->numFiles!==count($declared)+2)
                throw new DomainException('Archive contents do not match its signed manifest.');
            foreach (['manifest.json','manifest.sig'] as $known) $declared[$known]=NULL;
            for ($i=0;$i<$zip->numFiles;$i++) {
                $info=$zip->statIndex($i);
                if (!$info || !isset($declared[$info['name']]) && !array_key_exists($info['name'],$declared))
                    throw new DomainException('Archive contains an unexpected file.');
                if (!self::safeEntry($info['name']) || !empty($info['name']) && substr($info['name'],-1)==='/')
                    throw new DomainException('Archive paths are not safe.');
            }
            foreach ($data['files'] as $item) {
                $stream=$zip->getStream($item['path']);
                if (!$stream) throw new DomainException('Unable to decrypt a backup entry.');
                $ctx=hash_init('sha256');$bytes=0;
                while (!feof($stream)) {
                    $chunk=fread($stream,1024*1024);
                    if ($chunk===FALSE) {fclose($stream);throw new DomainException('Archive read failed.');}
                    $bytes+=strlen($chunk);
                    if ($bytes>$item['size']) {fclose($stream);throw new DomainException('Backup entry size mismatch.');}
                    hash_update($ctx,$chunk);
                }
                fclose($stream);
                if ($bytes!==$item['size'] ||
                    !hash_equals($item['sha256'],hash_final($ctx)))
                    throw new DomainException('Backup file checksum mismatch.');
            }
            return $data;
        } finally {$zip->close();}
    }

    /**
     * Restores DB and private working data only; application code in the ZIP
     * is intentionally for offline disaster recovery, not executable web upload.
     * Creates a complete pre-restore recovery snapshot first.
     */
    public function restore($name): array
    {
        $lock=$this->lock();
        $stage=NULL;$maintenance=$this->root.'/storage/.restore-in-progress';
        $leaveMaintenance=FALSE;
        try {
            $archive=$this->resolve($name);
            $meta=$this->verify($archive);
            $recovery=$this->createLocked('recovery');
            $stage=$this->temporaryDirectory();
            $this->extractRestorable($archive,$meta,$stage);
            if (file_put_contents($maintenance,(string)getmypid())===FALSE)
                throw new DomainException('Cannot activate restore maintenance mode.');
            try {
                $this->importSql($stage.'/database.sql');
                // Recover every non-transient private data file from the ZIP,
                // not only controlled documents and JSON audit history.
                if (is_dir($stage.'/storage'))
                    $this->copyDirectory($stage.'/storage',$this->root.'/storage');
            } catch (Throwable $failure) {
                // Best-effort database rollback from the pre-restore snapshot.
                $recoverStage=NULL;
                try {
                    $recoverStage=$this->temporaryDirectory();
                    $recoveryArchive=$this->resolve($recovery['name']);
                    $this->extractRestorable(
                        $recoveryArchive,$this->verify($recoveryArchive),$recoverStage);
                    $this->importSql($recoverStage.'/database.sql');
                    if (is_dir($recoverStage.'/storage'))
                        $this->copyDirectory($recoverStage.'/storage',$this->root.'/storage');
                } catch (Throwable $rollback) {
                    // Fail closed if automatic rollback cannot complete.
                    $leaveMaintenance=TRUE;
                    error_log('PK DTS RESTORE rollback needs intervention: '.$rollback->getMessage());
                    throw new DomainException('Restore failed and automatic recovery was incomplete. Use the recovery ZIP offline.');
                } finally {
                    // Decrypted staging must not survive a failed rollback.
                    if ($recoverStage!==NULL) $this->removeDirectory($recoverStage);
                }
                throw new DomainException('Restore failed; the recovery backup was reapplied.');
            } finally { if (!$leaveMaintenance) @unlink($maintenance); }
            return ['restored'=>$name,'recovery'=>$recovery['name']];
        } finally {
            if ($stage!==NULL) $this->removeDirectory($stage);
            $this->unlock($lock);
        }
    }

    private function extractRestorable($archive,$manifest,$stage): void
    {
        $zip=new ZipArchive();
        if ($zip->open($archive,ZipArchive::RDONLY)!==TRUE)
            throw new DomainException('Cannot open archive.');
        try {
            $zip->setPassword($this->password());
            foreach ($manifest['files'] as $entry) {
                $key=$entry['path'];
                if ($key!=='database.sql' && strpos($key,'files/storage/')!==0) continue;
                if (preg_match('~^files/storage/(?:backups|sessions|cache)/~',$key)) continue;
                $target=$stage.'/'.($key==='database.sql'?$key:substr($key,6));
                $dir=dirname($target);
                if (!is_dir($dir) && !mkdir($dir,0700,TRUE))
                    throw new DomainException('Cannot stage restored data.');
                $from=$zip->getStream($key);
                $to=fopen($target,'wb');
                if (!$from || !$to) throw new DomainException('Cannot stage restored file.');
                try {
                    while (!feof($from)) {
                        $bytes=fread($from,1048576);
                        if ($bytes===FALSE||fwrite($to,$bytes)!==strlen($bytes))
                            throw new DomainException('Cannot extract a backup file.');
                    }
                } finally {fclose($from);fclose($to);}
                @chmod($target,0600);
            }
        } finally {$zip->close();}
    }
    private function sourceFiles(): array
    {
        $files=[];$rootReal=realpath($this->root);
        $iterator=new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root,FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($iterator as $entry) {
            if (!$entry->isFile() || $entry->isLink()) continue;
            $real=$entry->getRealPath();
            if (!$real || !$this->inside($real,$rootReal)) continue;
            if ($this->inside($real,$this->directory)) continue;
            $relative=ltrim(str_replace('\\','/',substr($real,strlen($rootReal))),'/');
            if (preg_match('~^(?:\.git|node_modules|storage/sessions|storage/cache|storage/backups|tests/__pycache__)(?:/|$)~',$relative) ||
                preg_match('~(^|/)\.env(?:\.[^/]*)?$~',$relative) ||
                preg_match('~(^|/)(?:\.DS_Store|Thumbs\.db)$~',$relative))
                continue;
            $files['files/'.$relative]=$real;
        }
        ksort($files,SORT_STRING);
        return $files;
    }

    private function dumpDatabase($target): void
    {
        $binary=$this->binary('PK_MYSQLDUMP','mysqldump');
        $credentials=$this->credentialsFile();
        try {
            $args=[$binary,'--defaults-extra-file='.$credentials,
                '--single-transaction','--quick','--routines','--triggers','--events',
                '--hex-blob','--default-character-set=utf8mb4','--add-drop-table',
                '--result-file='.$target,$this->databaseName()];
            $this->execute($args,NULL);
            if (!is_file($target)||filesize($target)===0)
                throw new DomainException('The database dump is empty.');
        } finally { @unlink($credentials); }
    }
    private function importSql($source): void
    {
        if (!is_file($source)||filesize($source)===0)
            throw new DomainException('Database SQL is missing.');
        $credentials=$this->credentialsFile();
        try {
            $this->execute([$this->binary('PK_MYSQL','mysql'),
                '--defaults-extra-file='.$credentials,
                '--default-character-set=utf8mb4','--database='.$this->databaseName()],
                $source);
        } finally {@unlink($credentials);}
    }
    private function execute(array $args,$stdin): void
    {
        if (!function_exists('proc_open')) throw new DomainException('PHP proc_open is disabled.');
        $descriptors=[
            0=>$stdin===NULL?['pipe','r']:['file',$stdin,'r'],
            1=>['pipe','w'],2=>['pipe','w']
        ];
        $process=@proc_open($args,$descriptors,$pipes,$this->root,NULL,
            ['bypass_shell'=>TRUE]);
        if (!is_resource($process)) throw new DomainException('Database utility could not start.');
        if ($stdin===NULL) fclose($pipes[0]);
        $stdout=stream_get_contents($pipes[1],32768);
        $stderr=stream_get_contents($pipes[2],32768);
        fclose($pipes[1]);fclose($pipes[2]);
        if (proc_close($process)!==0)
            throw new DomainException('Database utility failed. Check credentials and configured binaries. '.
                substr((string)$stderr,0,150));
    }
    private function databaseName(): string
    {
        $name=(string)(getenv('DB_DATABASE')?:'pk_dts');
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/',$name))
            throw new DomainException('Database name is invalid.');
        return $name;
    }
    private function credentialsFile(): string
    {
        $file=$this->directory.'/.db-'.bin2hex(random_bytes(12)).'.cnf';
        $options=[
            'host'=>(string)(getenv('DB_HOST')?:'127.0.0.1'),
            'user'=>(string)(getenv('DB_USERNAME')?:'root'),
            'password'=>(string)(getenv('DB_PASSWORD')?:''),
            'port'=>(string)(getenv('DB_PORT')?:'3306')
        ];
        foreach ($options as $value) {
            if (preg_match('/[\r\n\0]/',$value))
                throw new DomainException('Database connection setting is invalid.');
        }
        $text="[client]\n";
        foreach ($options as $key=>$value)
            $text.=$key.'="'.$this->escapeOption($value)."\"\n";
        if (file_put_contents($file,$text,LOCK_EX)===FALSE)
            throw new DomainException('Cannot prepare private database credentials.');
        @chmod($file,0600);
        return $file;
    }
    private function escapeOption($text): string
    {
        return str_replace(['\\','"'],['\\\\','\\"'],$text);
    }
    private function binary($variable,$default): string
    {
        $configured=trim((string)(getenv($variable)?:''));
        if ($configured!=='') return $configured;
        if (DIRECTORY_SEPARATOR==='\\') {
            $base=trim((string)(getenv('XAMPP_HOME')?:'C:/xampp'));
            return rtrim($base,'\\/').'/mysql/bin/'.$default.'.exe';
        }
        foreach (['/usr/bin/'.$default,'/usr/local/bin/'.$default,'/usr/bin/mariadb'.($default==='mysql'?'':'-dump')] as $candidate)
            if (is_file($candidate)) return $candidate;
        return $default; // proc_open can still resolve via PATH.
    }
    private function password(): string
    {
        $pass=(string)getenv('PK_BACKUP_PASSWORD');
        if (strlen($pass)<12) throw new DomainException('Configure a strong PK_BACKUP_PASSWORD first.');
        return $pass;
    }
    private function signingKey(): string
    {
        $key=(string)getenv('PK_BACKUP_SIGNING_KEY');
        if (strlen($key)<32) throw new DomainException('Configure PK_BACKUP_SIGNING_KEY (32+ characters).');
        return $key;
    }
    private function requirements(): void
    {
        if (!class_exists('ZipArchive') || !defined('ZipArchive::EM_AES_256'))
            throw new DomainException('PHP ZIP extension with AES-256 support is required.');
        $this->password();$this->signingKey();
    }
    private static function safeEntry($name): bool
    {
        if (!is_string($name)||$name===''||$name[0]==='/' ||
            strpos($name,'\\')!==FALSE ||
            preg_match('/[<>:"|?*]/',$name) ||
            preg_match('/[[:cntrl:]]/',$name)) return FALSE;
        foreach (explode('/',$name) as $part) {
            if ($part==='' || $part==='.' || $part==='..' ||
                preg_match('/[. ]$/',$part) ||
                preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\..*)?$/i',$part))
                return FALSE;
        }
        return in_array($name,['database.sql','manifest.json','manifest.sig'],TRUE)||
            strpos($name,'files/')===0;
    }
    private function temporaryDirectory(): string
    {
        $dir=$this->directory.'/.stage-'.bin2hex(random_bytes(12));
        if (!mkdir($dir,0700)) throw new DomainException('Cannot allocate private temporary storage.');
        return $dir;
    }
    private function lock()
    {
        $fp=fopen($this->directory.'/.backup.lock','c');
        if (!$fp || !flock($fp,LOCK_EX|LOCK_NB))
            throw new DomainException('A backup or restore is already running.');
        return $fp;
    }
    private function unlock($fp): void { flock($fp,LOCK_UN);fclose($fp); }
    private function inside($path,$base): bool
    {
        $path=str_replace('\\','/',$path);$base=rtrim(str_replace('\\','/',$base),'/');
        return $path===$base||strpos($path,$base.'/')===0;
    }
    private function copyDirectory($source,$dest): void
    {
        if (!is_dir($dest) && !mkdir($dest,0700,TRUE))
            throw new DomainException('Cannot prepare restored private storage.');
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $source,FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $entry) {
            if ($entry->isLink()||!$entry->isFile()) continue;
            $part=substr($entry->getPathname(),strlen($source)+1);
            $output=$dest.'/'.$part;
            if (!is_dir(dirname($output))&&!mkdir(dirname($output),0700,TRUE))
                throw new DomainException('Cannot create a restored data folder.');
            $temp=$output.'.restore-'.bin2hex(random_bytes(5));
            if (!copy($entry->getPathname(),$temp)||!rename($temp,$output))
                throw new DomainException('Cannot replace a private stored file.');
            @chmod($output,0600);
        }
    }
    private function removeDirectory($path): void
    {
        if (!is_dir($path)) return;
        $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $entry) {
            if ($entry->isDir()&&!$entry->isLink()) @rmdir($entry->getPathname());
            else @unlink($entry->getPathname());
        }
        @rmdir($path);
    }
}
