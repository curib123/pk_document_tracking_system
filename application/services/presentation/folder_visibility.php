<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH.'services/presentation/document_folder_tree.php';

/**
 * Only expose folder names on a path to a document visible to this user.
 * Does not change document permissions, DB scope or existing folder IDs.
 */
final class Folder_visibility
{
    public static function allowed_nodes(array $tree,array $paths,string $domain): array
    {
        $allowed=[];
        $nodes=$tree['nodes']??[];
        foreach ($paths as $path) {
            $node=Document_folder_tree::folder_key($path,$nodes,$domain);
            $seen=[];
            while ($node!=='' && isset($nodes[$node]) && !isset($seen[$node]) &&
                   count($seen)<32) {
                $seen[$node]=TRUE;
                $allowed[$node]=TRUE;
                $node=(string)$nodes[$node]['parent'];
            }
        }
        return $allowed;
    }

    public static function restrict(array $tree,array $paths,string $domain): array
    {
        $allowed=self::allowed_nodes($tree,$paths,$domain);
        $tree['nodes']=array_intersect_key($tree['nodes']??[],$allowed);
        $tree['roots']=array_values(array_filter($tree['roots']??[],
            static function($key) use($allowed){return isset($allowed[$key]);}));
        foreach ($tree['nodes'] as &$node) {
            $node['children']=array_values(array_filter($node['children']??[],
                static function($key) use($allowed){return isset($allowed[$key]);}));
        }
        unset($node);
        return $tree;
    }
}
