<?php
final class Category_parent_policy
{
    public static function validate(array $rows,int $id,int $parent): void
    {
        $byId=[];$children=[];
        foreach ($rows as $row) {
            $byId[(int)$row['id']]=$row;
            $children[(int)$row['parent_id']][]=(int)$row['id'];
        }
        $seen=$id?[$id=>TRUE]:[];$ancestor=$parent;$depth=1;
        while ($ancestor) {
            if (isset($seen[$ancestor]) || !isset($byId[$ancestor]) || !$byId[$ancestor]['active'])
                throw new DomainException('Choose an active parent outside this category and its descendants.');
            $seen[$ancestor]=TRUE;
            if (++$depth>32) throw new DomainException('Folder hierarchy cannot exceed 32 levels.');
            $ancestor=(int)$byId[$ancestor]['parent_id'];
        }
        if (!$id) return;
        $stack=[[$id,$depth]];$seen=[];
        while ($stack) {
            [$node,$level]=array_pop($stack);
            if (isset($seen[$node]) || $level>32)
                throw new DomainException('This move would create a cycle or an excessively deep folder hierarchy.');
            $seen[$node]=TRUE;
            foreach ($children[$node]??[] as $child) $stack[]=[$child,$level+1];
        }
    }
}
