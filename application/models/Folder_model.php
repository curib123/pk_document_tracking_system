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
            // True document repository hierarchy:
            // Area > Specific > Asset > Location > Documents.
            // Optional/missing Asset uses Specific or Area as fallback so that
            // legacy and incomplete Places never disappear.
            $assetSpecifics=[];$specificAreas=[];
            foreach ($this->levels as $kind=>$definition) {
                $fields='id,'.$definition['title'];
                if ($definition['parent']) $fields.=','.$definition['parent'].'_id';
                if ($kind==='location') $fields.=',area_id,specific_id,asset_id,code';
                $rows=$this->db->select($fields)->get_where($definition['table'],['active'=>1])->result_array();
                foreach ($rows as $row) {
                    if ($kind==='specific')
                        $specificAreas[(int)$row['id']]=(int)$row['area_id'];
                    if ($kind==='asset')
                        $assetSpecifics[(int)$row['id']]=(int)$row['specific_id'];

                    $parent=$definition['parent'];
                    $parentId=$parent?(int)$row[$parent.'_id']:0;
                    if ($kind==='location') {
                        $assetId=(int)($row['asset_id']??0);
                        $specificId=(int)($row['specific_id']??0);
                        if ($assetId && isset($assetSpecifics[$assetId]))
                            $specificId=$assetSpecifics[$assetId];
                        $areaId=(int)($row['area_id']??0);
                        if (!$areaId && $specificId) $areaId=$specificAreas[$specificId]??0;
                        if ($assetId && isset($assetSpecifics[$assetId])) {
                            $parent='asset';$parentId=$assetId;
                        } elseif ($specificId && isset($specificAreas[$specificId])) {
                            $parent='specific';$parentId=$specificId;
                        } elseif ($areaId) {
                            $parent='area';$parentId=$areaId;
                        } else {
                            $parent=NULL;$parentId=0;
                        }
                    }
                    $nodes[]=['key'=>$kind.':'.$row['id'],
                        'parent'=>$parent?$parent.':'.$parentId:'',
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
        // Virtual, permission-scoped directory for records with any/missing
        // folder assignment. It does not add a new table or bypass document scope.
        if ($folder==='all') return [
            'folder'=>'all','level'=>'all','label'=>'All Documents',
            'breadcrumbs'=>[
                ['name'=>'Folders','folder'=>''],
                ['name'=>'All Documents','folder'=>'all']
            ],
            'children'=>[],'filter'=>NULL,'tree'=>$tree
        ];
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
