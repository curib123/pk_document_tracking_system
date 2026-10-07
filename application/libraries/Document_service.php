<?php
declare(strict_types=1);
use Pk\Core\{Context,Problem,Rules};
class Document_service
{
    private Context $ctx;
    public function __construct(Context|array|null $options = null) { $ctx=$this->ctx=Context::fromOptions($options);}
    public static function table(string $domain): string { return match($domain) { 'softcopy'=>'softcopy_documents','hardcopy'=>'hardcopy_documents',default=>throw new Problem('Invalid document domain.') }; }
    public function direct(string $domain,array $input): array
    {
        $this->ctx->require($domain.'.direct');
        $id=Rules::id($input,'id',false);
        if ($id) $this->ctx->model(\Document_model::class)->lock(self::table($domain),$id,Rules::id($input,'version'));
        $data=$this->validate($domain,$input,$id,$this->ctx->id());
        return $this->apply($domain,$data,$id,'direct',null,$this->ctx->id());
    }
    public function validate(string $domain,array $input,?int $id,int $owner): array
    {
        $data=['title'=>Rules::text($input,'title'),'reason'=>Rules::text($input,'reason',4000)];
        $old=$id ? $this->ctx->model(\Document_model::class)->lock(self::table($domain),$id) : null;
        if ($old && $old['status']!=='active') throw new Problem('Only active documents can be changed.');
        if ($domain==='softcopy') {
            $data['category_id']=Rules::id($input,'category_id'); $this->ctx->active('categories',$data['category_id']);
            $data['document_number']=$old['document_number'] ?? Rules::text($input,'document_number',100,false);
            $data['series_number']=Rules::text($input,'series_number',100,false);
            $data['file_id']=Rules::id($input,'file_id'); $this->availableFile($data['file_id'],$owner);
            $data['effective_date']=Rules::date($input,'effective_date');
            $data['page_number']=Rules::id($input,'page_number');
            if ($data['page_number']>100000) throw new Problem('Invalid page count.');
            $data['new_revision_level']=Rules::text($input,'new_revision_level',30,false);
            $data['date_received']=Rules::date(['date_received'=>($input['date_received'] ?? '') ?: date('Y-m-d')],'date_received');
            $data['date_released']=Rules::date(['date_released'=>($input['date_released'] ?? '') ?: date('Y-m-d')],'date_released');
            if ($data['date_released']<$data['date_received']) throw new Problem('Release date cannot precede receipt date.');
        } elseif ($domain==='hardcopy') {
            $data=[...$data,...$this->physical($input,$id)];
            $data['holder_id']=Rules::id(['holder_id'=>$input['holder_id'] ?? ($old['holder_id'] ?? $owner)],'holder_id'); $this->ctx->active('users',$data['holder_id']);
            $data['sequence_number']=Rules::text($input,'sequence_number',100,false);
            $data['retention_enabled']=Rules::boolean($input['retention_enabled'] ?? 0);
            $data['retention_start_date']=$data['retention_enabled'] ? Rules::date($input,'retention_start_date') : null;
            $data['retention_end_date']=$data['retention_enabled'] ? Rules::date($input,'retention_end_date') : null;
            if ($data['retention_enabled'] && $data['retention_end_date']<$data['retention_start_date']) throw new Problem('Retention end date cannot precede its start.');
            if ($old && ((int)$old['location_id']!==$data['location_id'] || (int)$old['holder_id']!==$data['holder_id'])) throw new Problem('Use a Transfer request to change location or holder; recipient acceptance is required.');
            if ($id) $this->noOpenTransfer($id);
        } else throw new Problem('Invalid document domain.');
        return $data;
    }
    public function physical(array $input,?int $documentId=null): array
    {
        $data=[];
        foreach(['area_id','specific_id','asset_id','location_id'] as $key) $data[$key]=Rules::id($input,$key);
        $this->ctx->active('areas',$data['area_id']); $specific=$this->ctx->active('specifics',$data['specific_id']);
        $asset=$this->ctx->active('assets',$data['asset_id']); $location=$this->ctx->active('locations',$data['location_id']);
        if ((int)$specific['area_id']!==$data['area_id'] || (int)$asset['specific_id']!==$data['specific_id'] || (int)$location['specific_id']!==$data['specific_id'] || (int)$location['asset_id']!==$data['asset_id']) throw new Problem('Area, Specific, asset and location must belong to the same hierarchy.');
        $occupied=$this->ctx->model(\Document_model::class)->location_occupant([$data['location_id']]);
        if ($occupied && (int)$occupied['id']!==$documentId) throw new Problem('That dedicated location is already assigned to another hardcopy.');
        return $data;
    }
    public function availableFile(int $id,int $owner): array
    {
        $file=$this->ctx->model(\Document_model::class)->lock('files',$id);
        if ((int)$file['uploaded_by']!==$owner || $file['document_id']!==null || $file['purpose']!=='upload' || $file['status']!=='pending') throw new Problem('Choose a new, unassigned file uploaded by the requester.');
        return $file;
    }
    public function apply(string $domain,array $data,?int $id,string $source,?int $requestId,int $owner): array
    {
        $db=$this->ctx->model(\Document_model::class); $table=self::table($domain); $before=$id ? $db->lock($table,$id) : null;
        $values=$domain==='softcopy'
            ? array_intersect_key($data,array_flip(['title','category_id','document_number','series_number']))
            : array_intersect_key($data,array_flip(['title','area_id','specific_id','asset_id','location_id','holder_id','sequence_number','retention_enabled','retention_start_date','retention_end_date']));
        if (!$id) {
            if ($domain==='softcopy' && !$values['document_number']) $values['document_number']=$this->ctx->sequence('document_'.date('Y'),'DOC-'.date('Y').'-');
            $id=$db->insert($table,[...$values,'created_by'=>$owner,'creation_source'=>$source,'creation_reason'=>$data['reason'],'source_request_id'=>$requestId]);
            $this->ctx->status($domain,$id,'','active','created',$data['reason']);
        } else $db->update($table,$id,$values);
        if ($domain==='softcopy') $this->revision($id,$data,$owner);
        $this->ctx->audit($domain,$before?'updated':'created',$id,$before,$values,$data['reason'],$requestId);
        if ($before) $this->ctx->status($domain,$id,$before['status'],$before['status'],$domain==='softcopy'?'revised':'updated',$data['reason']);
        return ['id'=>$id,'domain'=>$domain];
    }
    private function revision(int $documentId,array $data,int $owner): void
    {
        $db=$this->ctx->model(\Document_model::class); $doc=$db->lock('softcopy_documents',$documentId);
        $previous=$doc['current_revision_id'] ? $db->row('softcopy_revisions',(int)$doc['current_revision_id']) : null;
        $number=$previous ? (int)$previous['revision_number']+1 : 0;
        $file=$this->availableFile($data['file_id'],$owner);
        $revision=$db->insert('softcopy_revisions',[
            'document_id'=>$documentId,'revision_number'=>$number,'reason'=>$data['reason'],'effective_date'=>$data['effective_date'],'page_number'=>$data['page_number'],
            'series_number'=>$data['series_number'],'document_title'=>$data['title'],'previous_revision_level'=>$previous['new_revision_level'] ?? null,'new_revision_level'=>$data['new_revision_level'] ?? (string)$number,
            'previous_effective_date'=>$previous['effective_date'] ?? null,'new_effective_date'=>$data['effective_date'],'date_received'=>$data['date_received'],'date_released'=>$data['date_released'],'approval_date'=>date('Y-m-d'),
            'file_id'=>$file['id'],'uploaded_by'=>$owner,'approved_by'=>$this->ctx->id(),
        ]);
        $db->update('files',(int)$file['id'],['purpose'=>'revision','domain'=>'softcopy','document_id'=>$documentId,'status'=>'approved','approved_by'=>$this->ctx->id(),'approved_at'=>date('Y-m-d H:i:s')]);
        // The single FK pointer, not independently editable flags, defines the current revision.
        $db->update('softcopy_documents',$documentId,['current_revision_id'=>$revision]);
    }
    public function canRead(string $domain,int $id): bool
    {
        $doc=$this->ctx->model(\Document_model::class)->row(self::table($domain),$id);
        if ($this->ctx->can('documents.access_all')) return true;
        if ($doc['status']!=='active') return false;
        if ((int)$doc['created_by']===$this->ctx->id() || ($domain==='hardcopy' && (int)$doc['holder_id']===$this->ctx->id())) return true;
        if ($domain==='softcopy' && $this->ctx->model(\Document_model::class)->active_assignment([$id,$this->ctx->id()])) return true;
        return (bool)$this->ctx->model(\Document_model::class)->live_access_grant([$domain,$id,$this->ctx->id()]);
    }
    public function noOpenTransfer(int $id): void
    {
        if ($this->ctx->model(\Document_model::class)->open_transfer([$id])) throw new Problem('Finish or cancel the open physical transfer first.');
    }
    public function configureApprovers(array $input): array
    {
        $this->ctx->require('workflows.edit'); $domain=Rules::choice($input,'domain',['softcopy','hardcopy']); $id=Rules::id($input);
        $this->ctx->model(\Document_model::class)->lock(self::table($domain),$id,Rules::id($input,'version'));
        $config=Rules::json($input['config'] ?? []);
        if (count($config)>30) throw new Problem('Too many document approvers.');
        $saved=[];
        foreach($config as $key=>$userId) {
            if (!preg_match('/^[a-z][a-z0-9_]{0,49}$/',(string)$key)) throw new Problem('Invalid approver key.');
            $user=$this->ctx->active('users',Rules::id(['user_id'=>$userId],'user_id'));
            $saved[$key]=['user_id'=>(int)$user['id'],'name'=>Context::name($user),'position'=>$user['position_title']];
        }
        $old=$this->ctx->model(\Document_model::class)->approver_configuration([$domain,$id]);
        $data=['config'=>Context::json($saved),'configured_by'=>$this->ctx->id()];
        if ($old) $this->ctx->model(\Document_model::class)->update('document_approvers',(int)$old['id'],$data);
        else $this->ctx->model(\Document_model::class)->insert('document_approvers',[...$data,'domain'=>$domain,'document_id'=>$id]);
        $this->ctx->model(\Document_model::class)->update(self::table($domain),$id,['title'=>$this->ctx->model(\Document_model::class)->row(self::table($domain),$id)['title']]);
        $this->ctx->audit($domain,'approvers_configured',$id,$old,$saved,Rules::text($input,'reason',2000));
        return ['message'=>'Approvers saved. Submitted requests retain their original configuration.'];
    }
}
