<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'core/MY_Web_Controller.php';

use Pk\Core\Problem;
use Pk\Core\UiSchema;

/**
 * Catalog browser flow: GET pages / POST forms.
 * Catalog_service owns business rules; Read_service uses Read_model for SQL.
 */
class Catalog_controller extends MY_Web_Controller
{
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
            $query = $this->input->get(null, false) ?: [];
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

            $lookupField = (string) ($this->input->get('lookup') ?? '');
            $lookupQuery = (string) ($this->input->get('q') ?? '');
            $resuming = $this->input->get('resume') === '1';

            if ($resuming) {
                $draft = $this->draft($context->id(), $module, $id);
                if ($draft !== null) {
                    $record = array_replace($record, $draft);
                }
            } else {
                $this->forgetDraft();
            }

            $lookups = [];
            foreach ($definition['fields'] as $field) {
                if (empty($field['lookup'])) {
                    continue;
                }

                $name = $field['name'];
                $selected = (int) ($record[$name] ?? 0);
                $filter = $lookupField === $name ? $lookupQuery : '';
                $list = $read->lookups([
                    'kind' => $field['lookup'],
                    'q' => $filter,
                    'selected' => $selected ?: null,
                ]);

                // If selected record sorts past the first 100, request just that ID.
                if ($selected && !in_array(
                    $selected,
                    array_map(static fn(array $item): int => (int) $item['id'], $list['options']),
                    true
                )) {
                    $selectedOnly = $read->lookups([
                        'kind' => $field['lookup'],
                        'selected' => $selected,
                        'q' => '__selected_only__' . bin2hex(random_bytes(6)),
                    ]);
                    foreach ($selectedOnly['options'] as $option) {
                        if ((int) $option['id'] === $selected) {
                            $list['options'][] = $option;
                        }
                    }
                }

                $lookups[$name] = $list;
            }

            $errors = null;
            if ($resuming && isset($_SESSION['pk_catalog_error'])) {
                $candidate = $_SESSION['pk_catalog_error'];
                if (($candidate['module'] ?? '') === $module
                    && (int) ($candidate['id'] ?? 0) === (int) $id) {
                    $errors = $candidate;
                }
            }
            unset($_SESSION['pk_catalog_error']);

            $this->webView('catalog_form', $context, [
                'page_title' => ($id ? 'Edit ' : 'Add ') . $definition['label'],
                'module' => $module,
                'definition' => $definition,
                'record' => $record,
                'lookups' => $lookups,
                'lookup_field' => $lookupField,
                'lookup_query' => $lookupQuery,
                'form_error' => $errors,
            ]);
        } catch (Throwable $error) {
            $this->webError($context, $error);
        }
    }

    private function formRoute(string $module, ?int $id): string
    {
        return 'web/catalog/' . $module . ($id ? '/edit/' . $id : '/new');
    }

    private function draft(int $userId, string $module, ?int $id): ?array
    {
        $draft = $_SESSION['pk_catalog_draft'] ?? null;
        if (!is_array($draft)
            || (int) ($draft['owner'] ?? 0) !== $userId
            || ($draft['module'] ?? '') !== $module
            || (int) ($draft['id'] ?? 0) !== (int) $id
            || time() - (int) ($draft['time'] ?? 0) > 1800) {
            return null;
        }

        return $draft['values'] ?? null;
    }

    private function rememberDraft(int $userId, string $module, array $input): ?int
    {
        $definition = $this->definition($module);
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $id = $id !== false && $id > 0 ? $id : null;
        $values = [];

        foreach ($definition['fields'] as $field) {
            $name = $field['name'];
            $value = $input[$name] ?? '';
            $values[$name] = is_scalar($value) ? (string) $value : '';
        }

        if ($id) {
            $values['id'] = $id;
            $values['version'] = (int) ($input['version'] ?? 0);
        }

        $_SESSION['pk_catalog_draft'] = [
            'owner' => $userId,
            'module' => $module,
            'id' => $id,
            'time' => time(),
            'values' => $values,
        ];

        return $id;
    }

    private function forgetDraft(): void
    {
        unset($_SESSION['pk_catalog_draft'], $_SESSION['pk_catalog_error']);
    }

    public function lookup(string $module): void
    {
        $context = $this->webContext();

        try {
            $input = $this->webPost();
            $definition = $this->definition($module);
            $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
            $id = $id !== false && $id > 0 ? $id : null;
            $context->require($module . '.' . ($id ? 'edit' : 'add'));

            $field = (string) ($input['lookup_field'] ?? '');
            $valid = false;
            foreach ($definition['fields'] as $candidate) {
                if ($candidate['name'] === $field && !empty($candidate['lookup'])) {
                    $valid = true;
                    break;
                }
            }

            if (!$valid) {
                throw new Problem('Unknown searchable field.', 422);
            }

            $this->rememberDraft($context->id(), $module, $input);
            $allQueries = $input['lookup_q'] ?? [];
            $q = is_array($allQueries) && is_string($allQueries[$field] ?? null)
                ? mb_substr(trim($allQueries[$field]), 0, 100)
                : '';

            $this->webRedirect(
                $this->formRoute($module, $id)
                . '?resume=1&lookup=' . rawurlencode($field)
                . '&q=' . rawurlencode($q)
            );
        } catch (Throwable $error) {
            $this->webFailure($error, 'web/catalog/' . $module);
        }
    }

    public function save(string $module): void
    {
        $context = $this->webContext();
        $input = null;
        $id = null;

        try {
            $this->definition($module);
            $input = $this->webPost();
            $id = $this->rememberDraft($context->id(), $module, $input);

            // Business mutation and audit run in the existing transactional service.
            $context->db->transaction(static fn(): array =>
                (new Catalog_service($context))->save($module, $input)
            );

            $this->forgetDraft();
            $this->webFlash($id ? 'Record updated successfully.' : 'Record created successfully.');
            $this->webRedirect('web/catalog/' . $module);
        } catch (Throwable $error) {
            if ($input !== null) {
                $_SESSION['pk_catalog_error'] = [
                    'module' => $module,
                    'id' => $id,
                    'message' => $error instanceof Problem
                        ? $error->getMessage()
                        : 'The record could not be saved.',
                    'fields' => $error instanceof Problem ? $error->fields : [],
                ];

                if (!($error instanceof Problem)) {
                    log_message('error', $error->getMessage());
                }

                $this->webRedirect($this->formRoute($module, $id) . '?resume=1');
            }

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
