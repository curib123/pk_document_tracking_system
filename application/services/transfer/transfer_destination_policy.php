<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Preserve approved physical destination relationships until receipt. */
final class Transfer_destination_policy
{
    public static function require_approved_parents(array $approved,array $current): void
    {
        foreach (['area_id','specific_id','asset_id'] as $field) {
            if ((int)($approved[$field]??0)!==(int)($current[$field]??0))
                throw new DomainException(
                    'Destination hierarchy changed after approval. Create a new transfer.');
        }
    }
}
