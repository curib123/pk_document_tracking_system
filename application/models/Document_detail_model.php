<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Bounded, page-batched related records; never load another document's history. */
class Document_detail_model extends CI_Model
{
    public function for_page($domain,array $rows,array $viewer,array $fileHistories): array
    {
        if (!$rows) return [];
        $this->load->model('Document_model');
        $this->load->model('Permission_model');
        $permissions=$this->Permission_model->for_user($viewer);
        $can=static function($module,$action) use($permissions) {
            return isset($permissions['*']) || !empty($permissions[$module][$action]);
        };
        $ids=array_values(array_unique(array_map('intval',array_column($rows,'id'))));
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $table=$this->Document_model->config($domain)['table'];
        $scope=$this->Document_model->scope($domain,$viewer);
        $userId=(int)$viewer['id'];$roleId=(int)$viewer['role_id'];
        $requestColumn=$domain==='softcopy'?'softcopy_id':'hardcopy_id';
        $requestScope=$can('requests','view_all')?'1=1':
            '(r.requested_by='.$userId.' OR EXISTS (SELECT 1 FROM workflow_steps own_step WHERE own_step.request_id=r.id AND '.
            '(own_step.assigned_user_id='.$userId." OR (JSON_UNQUOTE(JSON_EXTRACT(own_step.assignment,'$.type'))='role' AND ".
            "CAST(JSON_UNQUOTE(JSON_EXTRACT(own_step.assignment,'$.value')) AS UNSIGNED)=$roleId))))";
        $requests=$this->db->query("SELECT recent.* FROM (
            SELECT r.id,r.$requestColumn AS document_id,r.reference,r.type,r.status,r.created_at,
                JSON_UNQUOTE(JSON_EXTRACT(r.payload,'$.subject')) AS subject,
                ROW_NUMBER() OVER (PARTITION BY r.$requestColumn ORDER BY r.created_at DESC,r.id DESC) AS row_rank
            FROM requests r JOIN $table d ON d.id=r.$requestColumn
            WHERE d.id IN ($placeholders) AND ($scope) AND ($requestScope)
            ) recent WHERE recent.row_rank<=25 ORDER BY recent.created_at DESC, recent.id DESC",$ids)->result_array();
        $requestIds=array_column($requests,'id');$histories=[];
        if ($requestIds) {
            $slots=implode(',',array_fill(0,count($requestIds),'?'));
            $historyRows=$this->db->query("SELECT recent.* FROM (
                SELECT h.request_id,h.action,h.user_name,h.created_at,
                    ROW_NUMBER() OVER (PARTITION BY h.request_id ORDER BY h.id DESC) AS row_rank
                FROM workflow_history h WHERE h.request_id IN ($slots)
                ) recent WHERE recent.row_rank<=30 ORDER BY recent.created_at DESC",$requestIds)->result_array();
            foreach ($historyRows as $history) $histories[$history['request_id']][]=
                ucwords(str_replace('_',' ',$history['action'])).' · '.$history['user_name'].' · '.$history['created_at'];
        }
        $requestsByDoc=[];$approvalsByDoc=[];
        foreach ($requests as $request) {
            $requestsByDoc[$request['document_id']][$request['reference']]=
                ucwords(str_replace('_',' ',$request['type'])).' · '.ucfirst($request['status']).' · '.($request['subject']??'');
            if (!empty($histories[$request['id']])) $approvalsByDoc[$request['document_id']][$request['reference']]=implode("\n",$histories[$request['id']]);
        }
        $auditByDoc=[];
        if ($can('audit','view')) {
            $audit=$this->db->query("SELECT recent.* FROM (
                SELECT h.document_id,h.action,h.previous_status,h.new_status,h.remarks,h.created_at,
                    CONCAT_WS(' ',u.first_name,u.last_name) AS actor,
                    ROW_NUMBER() OVER (PARTITION BY h.document_id ORDER BY h.id DESC) AS row_rank
                FROM status_history h JOIN $table d ON d.id=h.document_id JOIN users u ON u.id=h.user_id
                WHERE h.domain=? AND d.id IN ($placeholders) AND ($scope)
                ) recent WHERE recent.row_rank<=25 ORDER BY recent.created_at DESC",array_merge([$domain],$ids))->result_array();
            foreach ($audit as $index=>$event) $auditByDoc[$event['document_id']][($index+1).'. '.$event['created_at']]=
                ucwords(str_replace('_',' ',$event['action'])).' · '.$event['actor'].' · '.
                ($event['previous_status']?:'New').' → '.$event['new_status'].($event['remarks']?' · '.$event['remarks']:'');
        }
        $transfersByDoc=[];
        if ($domain==='hardcopy' && $can('transfer','view')) {
            $transferScope=$can('transfer','view_all')?'1=1':"(t.current_holder_id=$userId OR t.recipient_id=$userId)";
            $transfers=$this->db->query("SELECT recent.* FROM (
                SELECT t.hardcopy_id,t.status,t.created_at,t.transferred_at,t.accepted_at,
                    CONCAT_WS(' ',holder.first_name,holder.last_name) AS holder,
                    CONCAT_WS(' ',recipient.first_name,recipient.last_name) AS recipient,
                    ROW_NUMBER() OVER (PARTITION BY t.hardcopy_id ORDER BY t.id DESC) AS row_rank
                FROM transfers t JOIN hardcopy_documents d ON d.id=t.hardcopy_id
                JOIN users holder ON holder.id=t.current_holder_id JOIN users recipient ON recipient.id=t.recipient_id
                WHERE d.id IN ($placeholders) AND ($scope) AND ($transferScope)
                ) recent WHERE recent.row_rank<=25 ORDER BY recent.created_at DESC",$ids)->result_array();
            foreach ($transfers as $index=>$transfer) $transfersByDoc[$transfer['hardcopy_id']][($index+1).'. '.$transfer['created_at']]=
                $transfer['holder'].' → '.$transfer['recipient'].' · '.ucwords(str_replace('_',' ',$transfer['status'])).
                ' · Accepted: '.($transfer['accepted_at']?:'Not accepted');
        }
        $output=[];
        foreach ($rows as $document) {
            $id=(int)$document['id'];
            $sections=[['title'=>'Document Identity','fields'=>[
                'Title'=>$document['title'],'Reference / Copy Number'=>$document[$domain==='softcopy'?'document_number':'sequence_number']??'',
                'Document Type'=>ucfirst($domain),'Status'=>ucfirst($document['status']),
                'Created By'=>$document['creator_name'],'Created'=>$document['created_at'],
                'Modified'=>$document['updated_at'],'Record Version'=>$document['version']]]];
            if ($domain==='hardcopy') {
                $sections[]=['title'=>'Storage and Custody','fields'=>[
                    'Area'=>$document['area_name']??'','Specific'=>$document['specific_name']??'',
                    'Asset'=>$document['asset_name']??'','Location'=>$document['location_name']??'',
                    'Location Code'=>$document['location_code']??'','Current Holder'=>$document['holder_name']??'',
                    'Retention'=>$document['retention_enabled']?'Enabled':'Not enabled',
                    'Retention Start'=>$document['retention_start_date']??'','Retention End'=>$document['retention_end_date']??'']];
            } else {
                $sections[]=['title'=>'Category and Revision','fields'=>[
                    'Category / Folder'=>$document['category_name']??'','Series'=>$document['series_number']??'',
                    'Revision Number'=>$document['revision_number']??'','Revision Level'=>$document['new_revision_level']??'',
                    'Effective Date'=>$document['new_effective_date']??'','Pages'=>$document['page_number']??'',
                    'Date Received'=>$document['date_received']??'','Date Released'=>$document['date_released']??'']];
                $fileFields=[];$links=[];
                foreach ($fileHistories[$id]??[] as $index=>$file) {
                    $fileFields[($index+1).'. '.$file['original_name']]=
                        number_format((int)$file['size']).' bytes · '.$file['mime_type'].' · '.$file['created_at'];
                    $links[]=['label'=>'Download '.$file['original_name'],'url'=>site_url('files/download/'.$file['id'])];
                }
                $sections[]=['title'=>'Controlled Files · Latest 25','fields'=>$fileFields,
                    'links'=>$links,'empty'=>'No approved files available within your file permissions.'];
            }
            $sections[]=['title'=>'Related Requests · Latest 25','fields'=>$requestsByDoc[$id]??[],
                'empty'=>'No related requests available within your permissions.'];
            $sections[]=['title'=>'Workflow and Approval History','fields'=>$approvalsByDoc[$id]??[],
                'empty'=>'No approval history available for these requests.'];
            if ($domain==='hardcopy' && $can('transfer','view')) $sections[]=[
                'title'=>'Transfer History · Latest 25','fields'=>$transfersByDoc[$id]??[],
                'empty'=>'No transfers available within your permissions.'];
            if ($can('audit','view')) $sections[]=['title'=>'Document Audit · Latest 25','fields'=>$auditByDoc[$id]??[],
                'empty'=>'No document audit entries yet.'];
            $output[$id]=['sections'=>$sections];
        }
        return $output;
    }
}
