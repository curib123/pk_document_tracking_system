<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Existing schema capabilities; metadata/catalog access never grants file bytes. */
class Document_scope
{
    public static function write_predicate($domain,$userId,array $permissions,$alias='d')
    {
        self::predicate($domain,$userId,[],$alias); // Validate interpolated SQL identifiers.
        $id=(int)$userId;
        if ($id<1) return '1=0';
        if (isset($permissions['*'])) return '1=1';
        if (empty($permissions[$domain]['direct'])) return '1=0';
        if ($domain==='hardcopy') return $alias.'.holder_id='.$id;
        unset($permissions['documents']['view_all'],$permissions['documents']['view_granted']);
        return self::predicate($domain,$id,$permissions,$alias,FALSE);
    }

    public static function file_predicate($userId, array $permissions, $alias = 'd')
    {
        if (!isset($permissions['*']) && empty($permissions['files']['view']) &&
            empty($permissions['files']['view_all'])) return '1=0';
        unset($permissions['documents']['view_all']);
        if (!empty($permissions['files']['view_all'])) $permissions['documents']['access_all']=TRUE;
        return self::predicate('softcopy',$userId,$permissions,$alias,FALSE);
    }

    public static function predicate($domain, $userId, array $permissions, $alias = 'd', $catalog = FALSE)
    {
        if (!in_array($domain, ['hardcopy', 'softcopy'], TRUE) ||
            !preg_match('/^[a-z][a-z0-9_]*$/i', $alias)) {
            throw new InvalidArgumentException('Invalid document scope.');
        }
        $id = (int) $userId;
        if ($id <= 0) return '1=0';
        $can = static function ($module, $action) use ($permissions) {
            return isset($permissions['*']) || !empty($permissions[$module][$action]);
        };
        if ($can('documents', 'access_all') || $can('documents', 'view_all') ||
            ($catalog && $can('documents', 'request_catalog'))) return '1=1';

        $parts = [$alias.'.created_by='.$id];
        if ($domain === 'hardcopy') $parts[] = $alias.'.holder_id='.$id;
        if ($domain === 'softcopy' && $can('documents', 'view_assigned')) {
            $parts[] = 'EXISTS (SELECT 1 FROM assignments scope_assignment WHERE '.
                'scope_assignment.softcopy_id='.$alias.'.id AND '.
                'scope_assignment.user_id='.$id.' AND scope_assignment.active=1)';
        }
        if ($can('documents', 'view_granted')) {
            $parts[] = "EXISTS (SELECT 1 FROM access_grants scope_grant WHERE ".
                "scope_grant.domain='".$domain."' AND scope_grant.document_id=".$alias.'.id AND '.
                'scope_grant.user_id='.$id." AND scope_grant.status='access_granted' AND ".
                'scope_grant.revoked_at IS NULL AND scope_grant.expires_at >= CURRENT_TIMESTAMP)';
        }
        return '('.implode(' OR ', $parts).')';
    }
}
