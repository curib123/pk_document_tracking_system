<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * One Asset Number belongs to one or many Specifics (table asset_specifics).
 * Every caller resolves an asset's Specific and Area through these links, so a
 * hardcopy, Location or transfer destination never relies on a single parent.
 */
final class Asset_specific_policy
{
    /** Active links of an active asset: rows of specific_id and area_id, in Specific order. */
    public static function links($db, $assetId): array
    {
        return $db->select('x.specific_id,s.area_id')
            ->from('asset_specifics x')
            ->join('assets b', 'b.id=x.asset_id')
            ->join('specifics s', 's.id=x.specific_id')
            ->join('areas a', 'a.id=s.area_id')
            ->where('x.asset_id', (int) $assetId)->where('b.active', 1)
            ->where('s.active', 1)->where('a.active', 1)
            ->order_by('x.specific_id')->get()->result_array();
    }

    /**
     * The [specific_id, area_id] an asset is used under. A chosen Specific or Area must match
     * one of its links; with no Specific chosen, the asset must have exactly one.
     */
    public static function resolve($db, $assetId, $specificId = 0, $areaId = 0): array
    {
        $links = self::links($db, $assetId);
        if (!$links)
            throw new DomainException('Choose an active asset linked to an active specific and area.');
        $specificId = (int) $specificId;
        $areaId = (int) $areaId;
        if ($specificId) {
            $links = array_values(array_filter($links, static function ($link) use ($specificId) {
                return (int) $link['specific_id'] === $specificId;
            }));
            if (!$links)
                throw new DomainException('Selected asset does not belong to the chosen specific.');
        }
        if ($areaId) {
            $links = array_values(array_filter($links, static function ($link) use ($areaId) {
                return (int) $link['area_id'] === $areaId;
            }));
            if (!$links)
                throw new DomainException('Selected asset does not belong to the chosen area.');
        }
        if (count($links) > 1)
            throw new DomainException('This Asset Number is used under several Specifics. Choose the Specific first.');
        return [(int) $links[0]['specific_id'], (int) $links[0]['area_id']];
    }
}
