<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Navigation for existing pk_dts master records. Folder paths are GET state,
 * not filesystem directories and never new database tables.
 */
class Folder_model extends CI_Model
{
    private $levels = [
        'area' => ['table'=>'areas','label'=>'Area','title'=>'name'],
        'specific' => ['table'=>'specifics','label'=>'Specific','title'=>'name'],
        'asset' => ['table'=>'assets','label'=>'Asset','title'=>'asset_number'],
        'location' => ['table'=>'locations','label'=>'Location','title'=>'name']
    ];

    private function get_active($table,$id)
    {
        return $this->db->get_where($table,['id'=>(int)$id,'active'=>1],1)->row_array();
    }

    public function browse($domain,$folder='')
    {
        if (!in_array($domain,['hardcopy','softcopy'],TRUE)) show_404();
        if ($folder==='') {
            $result=['folder'=>'','level'=>'root','label'=>'All Documents',
                'breadcrumbs'=>[['name'=>'All Documents','folder'=>'']],
                'children'=>[],'filter'=>NULL];
        } else {
            if (!preg_match('/^(area|specific|asset|location|category):([1-9][0-9]*)$/',$folder,$match))
                show_404();
            $level=$match[1]; $id=(int)$match[2];
            if (($domain==='softcopy')!==($level==='category')) show_404();
            if ($domain==='softcopy') {
                $chain=[];$seen=[];$at=$id;
                while ($at) {
                    if (isset($seen[$at]) || count($chain)>=32) show_404();
                    $seen[$at]=TRUE;
                    $category=$this->get_active('categories',$at);
                    if (!$category) show_404();
                    array_unshift($chain,$category);
                    $at=(int)($category['parent_id']??0);
                }
                $result=['folder'=>$folder,'level'=>'category',
                    'label'=>$chain[count($chain)-1]['name'],
                    'breadcrumbs'=>[['name'=>'All Documents','folder'=>'']],
                    'children'=>[],'filter'=>['category_id'=>$id]];
                foreach ($chain as $c) $result['breadcrumbs'][]=[
                    'name'=>$c['name'],'folder'=>'category:'.$c['id']
                ];
            } else {
                if (!isset($this->levels[$level])) show_404();
                $node=$this->get_active($this->levels[$level]['table'],$id);
                if (!$node) show_404();
                // Follow the actual relational parent chain; do not trust
                // arbitrary combinations of area/specific/asset IDs.
                $chain=[['name'=>$node[$this->levels[$level]['title']],
                    'folder'=>$folder]];
                $cursor=$node; $type=$level;
                while ($type!=='area') {
                    $parent= $type==='location'
                        ? (!empty($cursor['asset_id'])?'asset':
                           (!empty($cursor['specific_id'])?'specific':'area'))
                        : ($type==='asset'?'specific':'area');
                    $parentId=(int)($cursor[$parent.'_id']??0);
                    if (!$parentId) break;
                    $parentNode=$this->get_active($this->levels[$parent]['table'],$parentId);
                    if (!$parentNode) show_404();
                    array_unshift($chain,[
                        'name'=>$parentNode[$this->levels[$parent]['title']],
                        'folder'=>$parent.':'.$parentId
                    ]);
                    $cursor=$parentNode; $type=$parent;
                }
                $field=['area'=>'area_id','specific'=>'specific_id',
                    'asset'=>'asset_id','location'=>'location_id'][$level];
                $result=['folder'=>$folder,'level'=>$level,
                    'label'=>$node[$this->levels[$level]['title']],
                    'breadcrumbs'=>array_merge(
                        [['name'=>'All Documents','folder'=>'']],$chain),
                    'children'=>[],'filter'=>[$field=>$id]];
            }
        }

        if ($domain==='softcopy') {
            $parent=$result['level']==='root'?NULL:(int)substr($result['folder'],9);
            $this->db->select('id,name,folder_name')->from('categories')
                ->where('active',1);
            if ($parent===NULL) $this->db->where('parent_id IS NULL',NULL,FALSE);
            else $this->db->where('parent_id',$parent);
            $items=$this->db->order_by('name')->get()->result_array();
            foreach ($items as $item) $result['children'][]=[
                'folder'=>'category:'.$item['id'],'name'=>$item['name'],
                'meta'=>$item['folder_name'],'kind'=>'category'
            ];
        } else {
            $kind=$result['level'];
            $next=['root'=>'area','area'=>'specific','specific'=>'asset','asset'=>'location'];
            if (isset($next[$kind])) {
                $childType=$next[$kind];$table=$this->levels[$childType]['table'];
                $column=$this->levels[$childType]['title'];
                $this->db->select('id,'.$column.' AS name')->from($table)
                    ->where('active',1);
                if ($kind!=='root') {
                    $id=(int)explode(':',$result['folder'])[1];
                    $this->db->where($kind.'_id',$id);
                }
                $items=$this->db->order_by($column)->get()->result_array();
                foreach ($items as $item) $result['children'][]=[
                    'folder'=>$childType.':'.$item['id'],'name'=>$item['name'],
                    'meta'=>$this->levels[$childType]['label'],
                    'kind'=>$childType
                ];
            }
        }
        return $result;
    }

    public function scope_documents($domain,$browser,$alias='d')
    {
        if (!empty($browser['filter'])) {
            foreach ($browser['filter'] as $column=>$value)
                $this->db->where($alias.'.'.$column,(int)$value);
        }
    }
}
