<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__.'/../documents/disposal_service.php';

/** Read-only labels shared by request and document viewers. */
final class Disposal_details
{
    public static function reason($value): string
    {
        $value=is_scalar($value)?trim((string)$value):'';
        return (new Disposal_service())->choices()[$value]??ucwords(str_replace('_',' ',$value));
    }

    public static function recorded(array $record): array
    {
        return [
            'Disposal Reason'=>self::reason($record['disposal_action']??''),
            'Disposal Remarks'=>$record['remarks']??'',
            'Disposed By'=>$record['disposed_by_name']??'',
            'Disposed At'=>$record['disposed_at']??'',
            'Previous Status'=>ucfirst((string)($record['previous_status']??''))
        ];
    }
}
