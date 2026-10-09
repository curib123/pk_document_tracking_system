<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Existing relational Places/category folders, not filesystem paths or new tables. */
class Folder_model extends CI_Model
{
    private $trees=[];
    private $levels=[
        'area'=>['table'=>'areas','title'=>'name','parent'=>NULL],
        'specific'=>['table'=>'specifics','title'=>'name','parent'=>'area'],
        'asset'=>['table'=>'assets','title'=>'asset_number','parent'=>'specific'],
        'location'=>['table'=>'locations','title'=>'name','parent'=>NULL]
    ];

    public function tree($domain)
    {
        if (!in_array($domain,['hardcopy','softcopy'],TRUE)) show_404();
        if (isset($this->trees[$domain])) return $this->trees[$domain];
        require_once APPPATH.'services/presentation/folder_tree.php';
        $nodes=[];
        if ($domain==='softcopy') {
            $rows=$this->db->select('id,parent_id,name,folder_name')->get_where('categories',['active'=>1])->result_array();
            foreach ($rows as $row) $nodes[]=[
                'key'=>'category:'.$row['id'],'parent'=>$row['parent_id']?'category:'.$row['parent_id']:'',
                'name'=>$row['name'],'meta'=>$row['folder_name'],'kind'=>'category'
            ];
        } else {
            foreach ($this->levels as $kind=>$definition) {
                $fields='id,'.$definition['title'];
                if ($definition['parent']) $fields.=','.$definition['parent'].'_id';
                if ($kind==='location') $fields.=',area_id,specific_id,asset_id,code';
                $rows=$this->db->select($fields)->get_where($definition['table'],['active'=>1])->result_array();
                foreach ($rows as $row) {
                    $parent=$definition['parent'];
                    if ($kind==='location') {
                        $parent=!empty($row['asset_id'])?'asset':(!empty($row['specific_id'])?'specific':(!empty($row['area_id'])?'area':NULL));
                    }
                    $nodes[]=['key'=>$kind.':'.$row['id'],
                        'parent'=>$parent?$parent.':'.$row[$parent.'_id']:'',
                        'name'=>$row[$definition['title']],
                        'meta'=>$kind==='location'?$row['code']:ucfirst($kind),'kind'=>$kind];
                }
            }
        }
        return $this->trees[$domain]=Folder_tree::build($nodes);
    }

    public function browse($domain,$folder='')
    {
        $tree=$this->tree($domain);
        if ($folder!=='' && (!preg_match('/^(area|specific|asset|location|category):[1-9][0-9]*$/',$folder) ||
            !isset($tree['nodes'][$folder]))) show_404();
        $result=['folder'=>$folder,'level'=>'root','label'=>'All Documents',
            'breadcrumbs'=>[['name'=>'All Documents','folder'=>'']],
            'children'=>[],'filter'=>NULL,'tree'=>$tree];
        $children=$tree['roots'];
        if ($folder!=='') {
            $node=$tree['nodes'][$folder];
            $result['level']=$node['kind']; $result['label']=$node['name'];
            $result['filter']=[$node['kind'].'_id'=>(int)explode(':',$folder)[1]];
            $children=$node['children'];
            $chain=[]; $at=$folder;
            while ($at!=='') {
                $ancestor=$tree['nodes'][$at];
                array_unshift($chain,['name'=>$ancestor['name'],'folder'=>$at]);
                $at=$ancestor['parent'];
            }
            $result['breadcrumbs']=array_merge($result['breadcrumbs'],$chain);
        }
        foreach ($children as $key) {
            $node=$tree['nodes'][$key];
            $result['children'][]=['folder'=>$key,'name'=>$node['name'],'meta'=>$node['meta'],'kind'=>$node['kind']];
        }
        return $result;
    }

    public function scope_documents($domain,$browser,$alias='d')
    {
        if (!empty($browser['filter'])) {
            foreach ($browser['filter'] as $column=>$value) $this->db->where($alias.'.'.$column,(int)$value);
        }
    }
}
