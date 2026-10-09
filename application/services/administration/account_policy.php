<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Account_policy
{
    public static function can_delegate($actorRole, $targetRole, $owned, $requested)
    {
        if (strcasecmp(trim((string)$actorRole), 'Administrator') === 0) return TRUE;
        if (strcasecmp(trim((string)$targetRole), 'Administrator') === 0) return FALSE;
        return !array_diff(array_map('intval', $requested), array_map('intval', $owned));
    }
}
