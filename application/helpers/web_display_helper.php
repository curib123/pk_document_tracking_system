<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Keep internal identifiers, credentials and raw JSON out of detail pages.
 * The model supplies resolved labels alongside foreign keys when authorized.
 */
if (!function_exists('pk_web_display_fields')) {
    function pk_web_display_fields(array $source): array
    {
        $hide = [
            'id','version','payload','snapshot','result','graph','config','value',
            'storage_name','session_version','password_hash','permission_ids',
            'available_permissions','permissions','role_permissions','candidates',
            'assignment','request_details','before_state','after_state',
            'previous_state','origin','destination','user_agent','ip_address',
        ];
        $rename = [
            'requester'=>'Requested by','created_by_name'=>'Created by',
            'uploaded_by_name'=>'Uploaded by','approved_by_name'=>'Approved by',
            'rejected_by_name'=>'Rejected by','disposed_by_name'=>'Disposed by',
            'assigned_by_name'=>'Assigned by','location_path'=>'Physical location',
            'step_name'=>'Approval step','require_password_change'=>'Password change',
        ];
        $output = [];

        foreach ($source as $key => $value) {
            $key = (string) $key;
            $normalized = strtolower($key);

            if (in_array($normalized, $hide, true)
                || str_ends_with($normalized, '_id')
                || (str_ends_with($normalized, '_by') && is_numeric($value))
                || str_contains($normalized, 'password') && $normalized !== 'require_password_change'
                || str_contains($normalized, 'token')
                || str_contains($normalized, 'secret')
                || is_array($value) || is_object($value)) {
                continue;
            }

            if ($normalized === 'active') {
                $formatted = (int) $value === 1 ? 'Active' : 'Inactive';
            } elseif ($normalized === 'require_password_change') {
                $formatted = (int) $value === 1 ? 'Required' : 'Complete';
            } elseif ($value === null || $value === '') {
                $formatted = '—';
            } elseif (is_bool($value)) {
                $formatted = $value ? 'Yes' : 'No';
            } elseif (in_array($normalized, ['status','type','action','recipient_status'], true)) {
                $formatted = ucwords(str_replace('_', ' ', (string) $value));
            } else {
                $formatted = (string) $value;
            }

            $output[] = [
                'label' => $rename[$normalized] ?? ucwords(str_replace('_', ' ', $normalized)),
                'value' => $formatted,
            ];
        }

        return $output;
    }
}

/** Request_service/Read_model already resolve relationship names here. */
if (!function_exists('pk_web_request_fields')) {
    function pk_web_request_fields(array $data): array
    {
        $safe = [];
        foreach ($data as $name => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            if (str_ends_with((string) $name, '_id')) {
                continue;
            }
            $safe[$name] = $value;
        }
        return pk_web_display_fields($safe);
    }
}
