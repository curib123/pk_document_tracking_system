<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * New and updated Hardcopy records need an accountable physical hierarchy.
 * Asset numbers may be unavailable/destroyed and are NEVER required.
 * Existing incomplete records remain readable; validation applies on writes.
 */
final class Hardcopy_place_policy
{
    public static function require_location_path($area, $specific, $location): void
    {
        if ((int) $area <= 0)
            throw new DomainException('Select an Area.');
        if ((int) $specific <= 0)
            throw new DomainException('Select a Specific.');
        if ((int) $location <= 0)
            throw new DomainException('Select a Location.');
    }

    public static function require_location_parents($area, $specific): void
    {
        if ((int) $area <= 0)
            throw new DomainException('Select an Area.');
        if ((int) $specific <= 0)
            throw new DomainException('Select a Specific.');
    }
}
