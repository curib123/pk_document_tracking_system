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
        if ($c['slug']==='location') $this->prepare_location($data);
        if ($c['slug']==='softcopy-categories') {
            if (!$data['folder_name']) throw new DomainException('Folder name is required.');
            if (!$id) $data['created_by']=$actorId;
            if ($id && $id===$data['parent_id']) throw new DomainException('Category cannot be its own parent.');
        }
        if ($c['active']) $data['active'] = !empty($post['active']) ? 1 : 0;
        $this->ci->db->trans_begin();
        try {
            if ($c['slug']==='softcopy-categories') {
                require_once APPPATH.'services/places/category_parent_policy.php';
                $categories=$this->ci->db->query('SELECT id,parent_id,active FROM categories ORDER BY id FOR UPDATE')->result_array();
                Category_parent_policy::validate($categories,$id,(int)$data['parent_id']);
            }
            if ($c['slug']==='specific' && !$this->ci->db->get_where('areas',[
                'id'=>$data['area_id'],'active'=>1])->row_array())
                throw new DomainException('Choose an active parent area.');
            if ($c['slug']==='asset' && !$this->ci->db->select('s.id')->from('specifics s')
                ->join('areas a','a.id=s.area_id')->where('s.id',$data['specific_id'])
                ->where('s.active',1)->where('a.active',1)->get()->row_array())
                throw new DomainException('Choose an active specific in an active area.');
            if ($id) {
                $old=$this->ci->db->query('SELECT * FROM '.$c['table'].' WHERE id=? FOR UPDATE',[$id])->row_array();
                if (!$old) throw new DomainException('Record not found.');
                if (isset($post['version']) && $post['version']!=='' && (int)$post['version']!==(int)$old['version'])
                    throw new DomainException('This place changed in another session. Refresh before editing.');
                $data['version']=(int)$old['version']+1;
                $this->ci->db->where('id',$id)->update($c['table'],$data);
            } else $this->ci->db->insert($c['table'],$data);
            if ($this->ci->db->trans_status()===FALSE)
                throw new DomainException('Could not save. Check unique values and relations.');
            $this->ci->db->trans_commit();
        } catch (Throwable $e) {
            $this->ci->db->trans_rollback();
            if ($e instanceof DomainException) throw $e;
            throw new DomainException('Could not save this place. Refresh and try again.');
        }
    }
    /**
     * Predefined Location hierarchy, strictly resolved from the existing pk_dts
     * areas, specifics and assets (not from client-supplied parent IDs).
     * The source SQL allows each of the three references to be nullable.
     */
    private function prepare_location(&$data)
    {
        if (empty($data['code'])) throw new DomainException('Location code is required.');
        if (mb_strlen($data['name'])>150 || mb_strlen($data['code'])>100) {
            throw new DomainException('Location name must be 150 characters or less and code 100 or less.');
        }

        $date=$data['archive_date'] ?? '';
        if ($date==='') $data['archive_date']=NULL;
        else {
            $parsed=DateTime::createFromFormat('!Y-m-d',$date);
            if (!$parsed || $parsed->format('Y-m-d')!==$date) {
                throw new DomainException('Archive date must be a valid date.');
            }
        }

        $areaId=(int)($data['area_id']??0);
        $specificId=(int)($data['specific_id']??0);
        $assetId=(int)($data['asset_id']??0);

        if ($assetId) {
            $asset=$this->ci->db->select('b.id,b.specific_id,s.area_id')
                ->from('assets b')->join('specifics s','s.id=b.specific_id')
                ->join('areas a','a.id=s.area_id')
                ->where('b.id',$assetId)->where('b.active',1)
                ->where('s.active',1)->where('a.active',1)
                ->limit(1)->get()->row_array();
            if (!$asset) throw new DomainException('Choose an active asset linked to an active specific and area.');
            if ($specificId && $specificId!==(int)$asset['specific_id']) {
                throw new DomainException('Selected asset does not belong to the chosen specific.');
            }
            if ($areaId && $areaId!==(int)$asset['area_id']) {
                throw new DomainException('Selected asset does not belong to the chosen area.');
            }
            $specificId=(int)$asset['specific_id'];
            $areaId=(int)$asset['area_id'];
        }

        if ($specificId) {
            $specific=$this->ci->db->select('s.id,s.area_id')
                ->from('specifics s')->join('areas a','a.id=s.area_id')
                ->where('s.id',$specificId)->where('s.active',1)
                ->where('a.active',1)->limit(1)->get()->row_array();
            if (!$specific) throw new DomainException('Choose a specific linked to an active area.');
            if ($areaId && $areaId!==(int)$specific['area_id']) {
                throw new DomainException('Selected specific does not belong to the chosen area.');
            }
            $areaId=(int)$specific['area_id'];
        }

        if ($areaId && !$this->ci->db->select('id')->get_where('areas',[
            'id'=>$areaId,'active'=>1
        ])->row_array()) throw new DomainException('Choose an active area.');

        $data['area_id']=$areaId?:NULL;
        $data['specific_id']=$specificId?:NULL;
        $data['asset_id']=$assetId?:NULL;
    }

    public function deactivate($c,$id)
    {
        if (!$c['active']) throw new DomainException('Sequences cannot be deactivated.');
        $record=$this->ci->Place_model->find($c,$id);
        if (!$record) throw new DomainException('Record not found.');
        $this->ci->db->where('id',(int)$id)->set('version','version+1',FALSE)->update($c['table'],['active'=>0]);
        if ($this->ci->db->error()['code']) throw new DomainException('This place could not be deactivated.');
    }
}
