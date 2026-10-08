<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'core/MY_Web_Controller.php';

use Pk\Core\Problem;
use Pk\Core\UiSchema;

class Catalog_controller extends MY_Web_Controller
{
    // Only the five standard catalogue modules use this shared CRUD controller.
    private const MODULES = ['areas', 'specifics', 'assets', 'locations', 'categories'];

    private function definition(string $module): array
    {
        if (!in_array($module, self::MODULES, true)) {
            throw new Problem('Unknown catalogue.', 404);
        }

        return UiSchema::modules()[$module];
    }

    public function index(string $module): void
    {
        $context = $this->webContext();

        try {
            $definition = $this->definition($module);
            $query = $this->input->get(NULL, false) ?: [];
            $records = (new Read_service($context))->listing($module, $query);

            $this->webView('catalog', $context, [
                'page_title' => $definition['label'],
                'module' => $module,
                'definition' => $definition,
                'records' => $records,
                'query' => $query,
                'can_add' => $context->can($module . '.add'),
                'can_edit' => $context->can($module . '.edit'),
                'can_delete' => $context->can($module . '.delete'),
            ]);
        } catch (Throwable $error) {
            $this->webError($context, $error);
        }
    }

    public function create(string $module): void
    {
        $this->form($module, null);
    }

    public function edit(string $module, int $id): void
    {
        $this->form($module, $id);
    }

    private function form(string $module, ?int $id): void
    {
        $context = $this->webContext();

        try {
            $definition = $this->definition($module);
            $context->require($module . '.' . ($id ? 'edit' : 'add'));
            $read = new Read_service($context);
            $record = $id ? $read->detail($module, $id)['row'] : [];
            $lookups = [];

            foreach ($definition['fields'] as $field) {
                if (!empty($field['lookup'])) {
                    $lookups[$field['name']] = $read->lookups([
                        'kind' => $field['lookup'],
                        'selected' => $record[$field['name']] ?? null,
                    ]);
                }
            }

            $this->webView('catalog_form', $context, [
                'page_title' => ($id ? 'Edit ' : 'Add ') . $definition['label'],
                'module' => $module,
                'definition' => $definition,
                'record' => $record,
                'lookups' => $lookups,
            ]);
        } catch (Throwable $error) {
            $this->webError($context, $error);
        }
    }

    public function save(string $module): void
    {
        $context = $this->webContext();
        try {
            $this->definition($module);
            $input = $this->webPost();
            $context->db->transaction(static fn(): array =>
                (new Catalog_service($context))->save($module, $input)
            );
            $this->webFlash('Catalogue record saved.');
            $this->webRedirect('web/catalog/' . $module);
        } catch (Throwable $error) {
            $this->webFailure($error, 'web/catalog/' . $module);
        }
    }

    public function confirm_delete(string $module, int $id): void
    {
        $context = $this->webContext();

        try {
            $definition = $this->definition($module);
            $context->require($module . '.delete');
            $record = (new Read_service($context))->detail($module, $id)['row'];

            $this->webView('catalog_delete', $context, [
                'page_title' => 'Delete ' . $definition['label'],
                'module' => $module,
                'record' => $record,
                'definition' => $definition,
            ]);
        } catch (Throwable $error) {
            $this->webError($context, $error);
        }
    }

    public function delete(string $module): void
    {
        $context = $this->webContext();
        try {
            $this->definition($module);
            $input = $this->webPost();
            $result = $context->db->transaction(static fn(): array =>
                (new Catalog_service($context))->delete($module, $input)
            );
            $this->webFlash($result['message'] ?? 'Record deleted.');
            $this->webRedirect('web/catalog/' . $module);
        } catch (Throwable $error) {
            $this->webFailure($error, 'web/catalog/' . $module);
        }
    }
}
