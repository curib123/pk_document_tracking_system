<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Transfer_service
{
    private $ci;

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function dispatch($id, $actor)
    {
        $this->ci->db->trans_begin();
        try {
            $rows=$this->ci->db->query('SELECT * FROM transfers WHERE id=? FOR UPDATE',[(int)$id])->result_array();
            $transfer=$rows[0]??NULL;
            if (!$transfer || $transfer['status']!=='for_transfer' ||
                (int)$transfer['current_holder_id']!==(int)$actor['id']) {
                throw new DomainException('Only the current holder can dispatch an approved transfer.');
            }
            $this->ci->db->where('id',$id)->update('transfers',[
                'status'=>'in_transit','transferred_by'=>$actor['id'],
                'transferred_at'=>date('Y-m-d H:i:s')
            ]);
            if ($this->ci->db->trans_status()===FALSE) {
                throw new DomainException('Unable to dispatch the document.');
            }
            $this->ci->db->trans_commit();
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            if ($e instanceof DomainException) throw $e;
            log_message('error','Transfer dispatch failure: '.$e->getMessage());
            throw new DomainException('Transfer dispatch failed.');
        }
    }

    public function accept($id, $actor)
    {
        $this->ci->db->trans_begin();
        try {
            $rows=$this->ci->db->query('SELECT * FROM transfers WHERE id=? FOR UPDATE',[(int)$id])->result_array();
            $transfer=$rows[0]??NULL;
            if (!$transfer || $transfer['status']!=='in_transit' ||
                (int)$transfer['recipient_id']!==(int)$actor['id']) {
                throw new DomainException('Only the assigned recipient can accept an in-transit hardcopy.');
            }
            $destination=json_decode($transfer['destination'],TRUE);
            if (!is_array($destination) || empty($destination['location_id'])) {
                throw new DomainException('Destination is missing from this transfer.');
            }
            $docRows=$this->ci->db->query(
                'SELECT * FROM hardcopy_documents WHERE id=? FOR UPDATE',
                [$transfer['hardcopy_id']])->result_array();
            $doc=$docRows[0]??NULL;
            if (!$doc || $doc['status']!=='active' ||
                (int)$doc['holder_id']!==(int)$transfer['current_holder_id']) {
                throw new DomainException('Hardcopy holder or status changed after approval.');
            }
            $location=$this->ci->db->get_where('locations',[
                'id'=>(int)$destination['location_id'],'active'=>1
            ])->row_array();
            if (!$location) throw new DomainException('Destination location is no longer available.');

            $this->ci->db->where('id',$transfer['hardcopy_id'])->update('hardcopy_documents',[
                'area_id'=>$location['area_id'],'specific_id'=>$location['specific_id'],
                'asset_id'=>$location['asset_id'],'location_id'=>$location['id'],
                'holder_id'=>$actor['id']
            ]);
            $this->ci->db->where('id',$id)->update('transfers',[
                'status'=>'accepted','recipient_status'=>'accepted',
                'accepted_by'=>$actor['id'],'accepted_at'=>date('Y-m-d H:i:s')
            ]);
            if (!empty($transfer['request_id'])) {
                $request=$this->ci->db->get_where('requests',['id'=>$transfer['request_id']])->row_array();
                if ($request && $request['status']==='approved') {
                    $this->ci->db->where('id',$request['id'])->update('requests',[
                        'status'=>'completed','completed_at'=>date('Y-m-d H:i:s')
                    ]);
                }
                $this->ci->db->insert('workflow_history',[
                    'request_id'=>$transfer['request_id'], 'step_id'=>NULL,
                    'action'=>'transfer_accepted','user_id'=>$actor['id'],
                    'user_name'=>$actor['name'],'position_title'=>$actor['position_title']?:'',
                    'comments'=>'Physical document received by its intended recipient.'
                ]);
            }
            if ($this->ci->db->trans_status()===FALSE) {
                throw new DomainException('Acceptance transaction failed.');
            }
            $this->ci->db->trans_commit();
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            if ($e instanceof DomainException) throw $e;
            log_message('error','Transfer accept failure: '.$e->getMessage());
            throw new DomainException('Transfer acceptance failed.');
        }
    }
}
