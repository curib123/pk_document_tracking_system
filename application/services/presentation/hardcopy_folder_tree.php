<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__.'/folder_tree.php';

/** Read-only filing projection: imported Location labels can serve several paths. */
final class Hardcopy_folder_tree
{
    private const FIELDS=['area_id','specific_id','asset_id','location_id'];

    private static function parent_key(array $path,array $nodes): string
    {
        foreach (['asset'=>'asset_id','specific'=>'specific_id','area'=>'area_id'] as $kind=>$field) {
            $key=$kind.':'.(int)($path[$field]??0);
            if (isset($nodes[$key])) return $key;
        }
        $location=$nodes['location:'.(int)($path['location_id']??0)]??[];
        $parent=(string)($location['parent']??'');
        return isset($nodes[$parent])?$parent:'';
    }

    public static function folder_key(array $path,array $nodes): string
    {
        $parent=self::parent_key($path,$nodes);
        $location='location:'.(int)($path['location_id']??0);
        $context=$location.':'.$parent;
        if (isset($nodes[$location]) && isset($nodes[$context])) return $context;
        return $parent!==''?$parent:'unfiled';
    }

    public static function project(array $places,array $paths): array
    {
        $nodes=$places['nodes']??[];$rows=[];$locations=[];
        foreach ($nodes as $key=>$node) {
            // Rebuilding a tree must not carry over its already-populated children.
            $node['children']=[];
            if ($node['kind']==='location') $locations[$key]=$node;
            else $rows[$key]=$node;
        }
        $addLocation=static function($location,$parent) use(&$rows,$nodes) {
            if ($parent==='' || !isset($nodes[$parent])) return;
            $key=$location['key'].':'.$parent;
            $rows[$key]=array_merge($location,['key'=>$key,'parent'=>$parent,'children'=>[]]);
        };
        foreach ($locations as $location) $addLocation($location,(string)$location['parent']);
        $normalized=[];
        foreach ($paths as $path) {
            $fields=[];
            foreach (self::FIELDS as $field)
                $fields[$field]=($path[$field]??NULL)===NULL?NULL:(int)$path[$field];
            $normalized[json_encode($fields)]=$fields;
            $location=$locations['location:'.(int)($fields['location_id']??0)]??NULL;
            if ($location) $addLocation($location,self::parent_key($fields,$nodes));
        }
        $tree=Folder_tree::build(array_values($rows));
        // Keep existing location:<id> bookmarks as aliases, outside visible branches.
        foreach ($locations as $key=>$location)
            $tree['nodes'][$key]=$location+['contextual_alias'=>TRUE];
        $tree['document_paths']=[];
        foreach ($normalized as $fields) $tree['document_paths'][]=[
            'fields'=>$fields,'folder'=>self::folder_key($fields,$tree['nodes'])
        ];
        return $tree;
    }
}
