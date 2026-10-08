<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/MY_Web_Controller.php';

/**
 * Server-rendered dashboard module. No REST endpoint or browser-side fetch.
 */
class Dashboard_controller extends MY_Web_Controller
{
    public function index(): void
    {
        $context = $this->webContext();

        try {
            $summary = $context->can('dashboard.view')
                ? (new Read_service($context))->dashboard()
                : [];

            $this->webView('modules/dashboard/views/index', $context, [
                'page_title' => 'Dashboard',
                'summary' => $summary,
            ]);
        } catch (Throwable $error) {
            $this->webError($context, $error);
        }
    }
}
