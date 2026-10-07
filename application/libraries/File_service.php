<?php
declare(strict_types=1);
use Pk\Core\{Context,Problem,Rules};
class File_service
{
    // Kani nga service mao ang business-rule layer; controllers thin ra para easy i-follow.
    private Context $ctx;
    public function __construct(Context|array|null $options = null) { $ctx=$this->ctx=Context::fromOptions($options);}
    private function directory(): string { $path=PK_ROOT.'/storage/files'; if (!is_dir($path) && !mkdir($path,0700,true) && !is_dir($path)) throw new Problem('Private storage is not writable.',503); return $path; }
    public function upload(array $upload): array
    {
        $this->ctx->require('files.upload');
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '')) throw new Problem('Upload failed. Check the selected file and server upload limit.');
        $size=filesize($upload['tmp_name']); $max=20*1024*1024;
        if (!$size || $size>$max) throw new Problem('The file is empty or exceeds the configured upload limit.');
        $name=basename(str_replace('\\','/',(string)$upload['name']));
        if (strlen($name)>240 || preg_match('/[\x00-\x1F\x7F]/',$name)) throw new Problem('Invalid file name.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']); $extension=Rules::fileType($name,$mime);
        if (in_array($extension,['docx','xlsx'],true)) {
            if (!class_exists(\ZipArchive::class)) throw new Problem('The PHP ZIP extension is required for Office documents.',503);
            $zip=new \ZipArchive();
            if ($zip->open($upload['tmp_name'])!==true) throw new Problem('Invalid Office file.');
            try {
                if ($zip->numFiles>4096 || $zip->locateName('[Content_Types].xml')===false || $zip->locateName($extension==='docx'?'word/document.xml':'xl/workbook.xml')===false) throw new Problem('The archive is not a valid supported Office document.');
                $total=0;
                for($i=0;$i<$zip->numFiles;++$i) {
                    $entry=$zip->statIndex($i); $total+=(int)$entry['size'];
                    if ($total>100*1024*1024 || stripos($entry['name'],'vbaProject')!==false || str_contains($entry['name'],'../')) throw new Problem('Unsafe or oversized Office archive.');
                }
            } finally { $zip->close(); }
        }
        $storage=bin2hex(random_bytes(24)).'.'.$extension; $path=$this->directory().'/'.$storage;
        if (!move_uploaded_file($upload['tmp_name'],$path)) throw new Problem('Could not save the uploaded file.',503);
        chmod($path,0600);
        try {
            $id=$this->ctx->model(\File_model::class)->insert('files',['original_name'=>$name,'storage_name'=>$storage,'size'=>$size,'mime_type'=>$mime,'fingerprint'=>hash_file('sha256',$path),'extension'=>$extension,'uploaded_by'=>$this->ctx->id()]);
            $this->ctx->audit('files','uploaded',$id,null,['name'=>$name,'size'=>$size,'mime'=>$mime]);
            return ['id'=>$id,'name'=>$name];
        } catch(\Throwable $e) { unlink($path); throw $e; }
    }
    public function attach(array $input): array
    {
        $this->ctx->require('files.upload'); $domain=Rules::choice($input,'domain',['softcopy','hardcopy']); $id=Rules::id($input,'document_id');
        $doc=$this->ctx->model(\File_model::class)->lock(Document_service::table($domain),$id); $documents=new Document_service($this->ctx);
        if ($doc['status']!=='active' || !$documents->canRead($domain,$id)) throw new Problem('You cannot attach files to this document.',403);
        $file=$documents->availableFile(Rules::id($input,'file_id'),$this->ctx->id());
        $this->ctx->model(\File_model::class)->update('files',(int)$file['id'],['purpose'=>'attachment','domain'=>$domain,'document_id'=>$id]);
        $this->ctx->audit('files','attachment_submitted',(int)$file['id'],null,['domain'=>$domain,'document_id'=>$id],Rules::text($input,'reason',2000));
        $approvers=$this->ctx->model(\File_model::class)->attachment_approvers([]);
        foreach($approvers as $u) $this->ctx->notify((int)$u['id'],'Attachment approval required',$file['original_name']);
        return ['message'=>'Attachment submitted for approval.'];
    }
    public function decide(array $input): array
    {
        $db=$this->ctx->model(\File_model::class); $file=$db->lock('files',Rules::id($input),Rules::id($input,'version')); $decision=Rules::choice($input,'decision',['approved','rejected','cancelled']);
        if ($file['purpose']!=='attachment' || $file['status']!=='pending') throw new Problem('Only pending attachments can be decided.',409);
        if ($decision==='cancelled') { if ((int)$file['uploaded_by']!==$this->ctx->id()) $this->ctx->require('files.approve'); }
        else $this->ctx->require('files.approve');
        $reason=Rules::text($input,'reason',4000); $data=['status'=>$decision];
        if ($decision==='approved') $data+=['approved_by'=>$this->ctx->id(),'approved_at'=>date('Y-m-d H:i:s')];
        elseif ($decision==='rejected') $data+=['rejected_by'=>$this->ctx->id(),'rejected_at'=>date('Y-m-d H:i:s'),'rejection_reason'=>$reason];
        $db->update('files',(int)$file['id'],$data); $this->ctx->audit('files','attachment_'.$decision,(int)$file['id'],$file,$data,$reason);
        $this->ctx->notify((int)$file['uploaded_by'],'Attachment '.$decision,$file['original_name'].' — '.$reason);
        return ['message'=>'Attachment '.$decision.'.'];
    }
    public function download(int $id): array
    {
        $file=$this->ctx->model(\File_model::class)->row('files',$id); $allowed=false;
        if (!$file['document_id']) $allowed=(int)$file['uploaded_by']===$this->ctx->id() || $this->canReview($id);
        elseif ($file['status']==='approved') $allowed=(new Document_service($this->ctx))->canRead($file['domain'],(int)$file['document_id']);
        else $allowed=(int)$file['uploaded_by']===$this->ctx->id() || $this->ctx->can('files.approve');
        if (!$allowed) throw new Problem('Document access is required or has expired/revoked.',403);
        if ($file['purpose']==='artifact') {
            $artifact=$this->ctx->model(\File_model::class)->artifact_revision_state([$id]);
            if ($artifact && $artifact['artifact_type']==='controlled' && ((int)$artifact['revision_id']!==(int)$artifact['current_revision_id'] || $artifact['status']!=='active')) throw new Problem('This controlled artifact is superseded or inactive. Generate an uncontrolled historical copy instead.',409);
        }
        $path=$this->path($file);
        $this->ctx->audit('files','downloaded',$id,null,['name'=>$file['original_name'],'fingerprint'=>$file['fingerprint']]);
        return ['path'=>$path,'name'=>$file['original_name'],'mime'=>$file['mime_type'],'size'=>(int)$file['size']];
    }
    private function canReview(int $file): bool
    {
        if (!$this->ctx->can('requests.approve')) return false;
        $rows=$this->ctx->model(\File_model::class)->pending_file_reviewers([$file]);
        foreach($rows as $row) foreach(Rules::json($row['candidates']) as $u) if ((int)$u['id']===$this->ctx->id()) return true;
        return false;
    }
    private function path(array $file): string
    {
        if (!preg_match('/^[a-f0-9]{48}\.[a-z0-9]+$/',$file['storage_name'])) throw new Problem('Invalid private storage record.',500);
        $path=$this->directory().'/'.$file['storage_name'];
        if (!is_file($path)) throw new Problem('The stored file is missing. Contact the administrator.',404);
        if (!hash_equals($file['fingerprint'],hash_file('sha256',$path))) throw new Problem('Stored file integrity check failed. Download blocked.',409);
        return $path;
    }
    public function artifact(array $input): array
    {
        $this->ctx->require('files.generate'); $db=$this->ctx->model(\File_model::class); $revision=$db->row('softcopy_revisions',Rules::id($input,'revision_id'));
        $doc=$db->lock('softcopy_documents',(int)$revision['document_id']);
        if (!(new Document_service($this->ctx))->canRead('softcopy',(int)$doc['id'])) throw new Problem('You do not have access to this revision.',403);
        $type=Rules::choice($input,'artifact_type',['controlled','uncontrolled']);
        if ($type==='controlled' && ((int)$doc['current_revision_id']!==(int)$revision['id'] || $doc['status']!=='active')) throw new Problem('Only the active current revision may produce a controlled copy.');
        $source=$db->row('files',(int)$revision['file_id']); $sourcePath=$this->path($source);
        $existing=$db->matching_artifact([$revision['id'],$type,$source['fingerprint']]);
        if ($existing) return ['id'=>(int)$existing['id'],'file_id'=>(int)$existing['file_id'],'message'=>'Existing artifact returned; source fingerprint is unchanged.'];
        if (!class_exists(\setasign\Fpdi\Fpdi::class)) throw new Problem('Install Composer dependencies to generate PDF artifacts.',503);
        if (!in_array($source['extension'],['pdf','docx','xlsx'],true)) throw new Problem('Artifact generation supports PDF, DOCX and XLSX revisions.');
        $temp=[];
        try {
            if ($source['extension']!=='pdf') { [$sourcePath,$temp]=$this->convert($sourcePath); }
            $pdf=new \setasign\Fpdi\Fpdi(); $count=$pdf->setSourceFile($sourcePath);
            if ($count>1000) throw new Problem('The PDF exceeds the 1,000-page artifact limit.');
            $pdf->SetAutoPageBreak(false);
            for($page=1;$page<=$count;++$page) {
                $template=$pdf->importPage($page); $size=$pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'],[$size['width'],$size['height']+12]); $pdf->useTemplate($template);
                $pdf->SetFont('Helvetica','B',8); $pdf->SetFillColor(255,255,255); $pdf->SetTextColor(0,0,0);
                $pdf->SetXY(3,$size['height']+2);
                $label=strtoupper($type).' COPY | #'.$doc['id'].' '.$doc['document_number'].' | REV '.$revision['new_revision_level'].' | '.$page.'/'.$count;
                if ((int)$doc['current_revision_id']!==(int)$revision['id']) $label.=' | HISTORICAL';
                $pdf->MultiCell($size['width']-6,4,iconv('UTF-8','Windows-1252//TRANSLIT',$label),0,'C',true);
            }
            $storage=bin2hex(random_bytes(24)).'.pdf'; $path=$this->directory().'/'.$storage; $pdf->Output('F',$path); chmod($path,0600);
            $name=$type.'-revision-'.$revision['revision_number'].'.pdf';
            $file=$db->insert('files',['original_name'=>$name,'storage_name'=>$storage,'size'=>filesize($path),'mime_type'=>'application/pdf','fingerprint'=>hash_file('sha256',$path),'extension'=>'pdf','purpose'=>'artifact','domain'=>'softcopy','document_id'=>$doc['id'],'uploaded_by'=>$this->ctx->id(),'status'=>'approved','approved_by'=>$this->ctx->id(),'approved_at'=>date('Y-m-d H:i:s')]);
            $id=$db->insert('revision_artifacts',['revision_id'=>$revision['id'],'artifact_type'=>$type,'file_id'=>$file,'source_fingerprint'=>$source['fingerprint'],'generator_version'=>'pk-artifact-1/fpdi-2']);
            $this->ctx->audit('files','artifact_generated',$id,null,['revision_id'=>$revision['id'],'type'=>$type,'source_fingerprint'=>$source['fingerprint']]);
            return ['id'=>$id,'file_id'=>$file,'message'=>'A labeled PDF artifact was generated from the preserved source.'];
        } catch(Problem $e) { throw $e; }
        catch(\Throwable $e) { if (isset($path) && is_file($path)) unlink($path); error_log('Artifact generation: '.$e->getMessage()); throw new Problem('PDF generation failed. Encrypted or unsupported PDFs must be exported to a compatible PDF first.',422); }
        finally { foreach($temp as $path) if (is_file($path)) unlink($path); }
    }
    private function convert(string $source): array
    {
        $binary='';
        foreach([
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            '/usr/bin/libreoffice',
            '/usr/bin/soffice'
        ] as $candidate) {
            if (is_file($candidate)) { $binary=$candidate; break; }
        }
        if ($binary==='') throw new Problem('Install LibreOffice in its standard location to enable DOCX/XLSX conversion, or upload a PDF revision.',503);
        $dir=PK_ROOT.'/storage/conversions/'.bin2hex(random_bytes(10)); if (!mkdir($dir,0700,true)) throw new Problem('Cannot create conversion workspace.',503);
        $process=proc_open([$binary,'-env:UserInstallation=file://'.str_replace('\\','/',$dir).'/profile','--headless','--convert-to','pdf','--outdir',$dir,$source],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($process)) throw new Problem('Could not start Office conversion.',503);
        fclose($pipes[0]); stream_set_blocking($pipes[1],false); stream_set_blocking($pipes[2],false); $start=time();
        do { stream_get_contents($pipes[1],16384); stream_get_contents($pipes[2],16384); $state=proc_get_status($process); if (!$state['running']) break; usleep(100000); } while(time()-$start<45);
        if ($state['running']) proc_terminate($process,9);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
        $pdf=$dir.'/'.pathinfo($source,PATHINFO_FILENAME).'.pdf';
        if ($state['running'] || !is_file($pdf)) throw new Problem('Office conversion failed or exceeded 45 seconds.',422);
        // Private conversion workspaces are removed by the maintenance command after processing.
        return [$pdf,[$pdf]];
    }
}
