<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Role IDs come from the server-loaded identity, never an action form. */
final class User_roles
{
    public static function ids(array $user): array
    {
        $ids=array_merge([(int)($user['role_id']??0)],(array)($user['role_ids']??[]));
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static function($id){return $id>0;})));
        sort($ids,SORT_NUMERIC);
        return $ids;
    }
    public static function matches(array $user,int $roleId): bool
    {
        return $roleId>0 && in_array($roleId,self::ids($user),TRUE);
    }
}
