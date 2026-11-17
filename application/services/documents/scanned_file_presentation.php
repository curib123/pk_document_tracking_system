<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Use the shared document viewer for both pending and approved scanned files. */
final class Scanned_file_presentation
{
    public static function link(array $file, int $requestId = 0): array
    {
        $url = site_url('documents/scanned/file/' . (int) $file['id']);
        $query = $requestId ? ['request_id' => $requestId] : [];
        $extension = strtolower(pathinfo((string) $file['original_name'], PATHINFO_EXTENSION));
        return [
            'label' => $file['original_name'],
            'url' => $url . ($query ? '?' . http_build_query($query) : ''),
            'preview_url' => $url . '?' . http_build_query($query + ['preview' => '1']),
            'preview_type' => in_array($extension, ['docx', 'xlsx'], TRUE) ? 'application/pdf' : $file['mime_type']
        ];
    }
}
