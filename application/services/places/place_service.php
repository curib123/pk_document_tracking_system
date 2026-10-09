<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Place_service
{
    private $ci;
    public function __construct() { $this->ci =& get_instance(); $this->ci->load->model('Place_model'); }

    public function save($c, $post, $actorId)
    {
        // sequences has a string primary key and controls system counters.
        // It has no numeric id or active column, so keep it read-only in Places.
        if (!empty($c['read_only'])) throw new DomainException('System sequences are managed automatically.');
        $id=(int)($post['id']??0); $data=[];
        foreach ($c['fields'] as $field) {
            $value=trim((string)($post[$field]??''));
            if (str_ends_with($field,'_id')) $data[$field] = $value !== '' ? (int)$value : NULL;
            elseif ($field === 'value') $data[$field] = max(0,(int)$value);
            else $data[$field] = $value;
        }
        if ($data[$c['key']] === '') throw new DomainException('Name/code is required.');
        foreach (['name','code','asset_number','sequence_key','folder_name'] as $key) {
            if (isset($data[$key]) && mb_strlen((string)$data[$key]) > 150) throw new DomainException('Value is too long.');
        }
        if ($c['slug']==='specific' && !$data['area_id']) throw new DomainException('Choose an area.');
        if ($c['slug']==='asset' && !$data['specific_id']) throw new DomainException('Choose a specific.');
        if ($c['slug']==='location' && empty($data['code'])) throw new DomainException('Location code is required.');
        if ($c['slug']==='softcopy-categories') {
            if (!$data['folder_name']) throw new DomainException('Folder name is required.');
            if (!$id) $data['created_by']=$actorId;
            if ($id && $id===$data['parent_id']) throw new DomainException('Category cannot be its own parent.');
        }
        if ($c['active']) $data['active'] = !empty($post['active']) ? 1 : 0;
        $this->ci->db->trans_begin();
        if ($id) {
            $old=$this->ci->Place_model->find($c,$id);
            if (!$old) throw new DomainException('Record not found.');
            $this->ci->db->where('id',$id)->update($c['table'],$data);
        } else {
            $this->ci->db->insert($c['table'],$data);
        }
        if ($this->ci->db->trans_status()===FALSE) {
            $this->ci->db->trans_rollback(); throw new DomainException('Could not save. Check unique values and relations.');
        }
        $this->ci->db->trans_commit();
    }
    public function deactivate($c,$id)
    {
        if (!$c['active']) throw new DomainException('Sequences cannot be deactivated.');
        $record=$this->ci->Place_model->find($c,$id);
        if (!$record) throw new DomainException('Record not found.');
        $this->ci->db->where('id',(int)$id)->update($c['table'],['active'=>0]);
    }
}
