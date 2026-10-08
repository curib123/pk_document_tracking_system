<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'core/MY_Web_Controller.php';

use Pk\Core\Problem;
use Pk\Core\UiSchema;

class Web_records extends MY_Web_Controller
{
    private function definition(string $module): array
    {
        return UiSchema::modules()[$module]
            ?? throw new Problem('Unknown record type.', 404);
    }

    public function index(string $module): void
    {
        $context = $this->webContext();

        try {
            $definition = $this->definition($module);
            $query = $this->input->get(NULL, false) ?: [];
            $result = (new Read_service($context))->listing($module, $query);

            $this->webView('records', $context, [
                'page_title' => $definition['label'],
                'module' => $module,
                'definition' => $definition,
                'records' => $result,
                'query' => $query,
            ]);
        } catch (Throwable $error) {
            $this->webError($context, $error);
        }
    }

    public function detail(string $module, int $id): void
    {
        $context = $this->webContext();

        try {
            $definition = $this->definition($module);
            $detail = (new Read_service($context))->detail($module, $id);

            $this->webView('record_detail', $context, [
                'page_title' => $definition['label'] . ' details',
                'module' => $module,
                'definition' => $definition,
                'detail' => $detail,
            ]);
        } catch (Throwable $error) {
            $this->webError($context, $error);
        }
    }
}
