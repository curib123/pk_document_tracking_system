<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Pk\Core\Context;
use Pk\Core\Database;
use Pk\Core\Problem;
use Pk\Core\Security;
use Pk\Core\UiSchema;

/**
 * Browser-only HTTP mechanics. Controllers dispatch; services own rules;
 * models handle persistence. No client-side API or JavaScript is required.
 */
class MY_Web_Controller extends MY_Controller
{
    protected function webContext(bool $guestAllowed = false, bool $passwordAllowed = false): Context
    {
        $this->load->helper(['url', 'ui']);
        Security::startSession();
        Security::headers();

        // Bootstrap CSS only. Browser pages intentionally execute no JavaScript.
        header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self' https://cdn.jsdelivr.net; font-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");

        $context = new Context(Database::connect());

        if (!empty($_SESSION['user_id'])) {
            try {
                if (Security::expired($_SESSION, time())) {
                    throw new Problem('Session expired.', 401);
                }

                $context->identify((int) $_SESSION['user_id']);

                if ((int) $context->user['session_version'] !== (int) ($_SESSION['session_version'] ?? -1)) {
                    throw new Problem('Your session is no longer valid.', 401);
                }

                $_SESSION['last_seen'] = time();
            } catch (Problem $error) {
                // Expired or disabled accounts cannot keep their old permissions.
                $context = new Context(Database::connect());
                $_SESSION = ['csrf' => bin2hex(random_bytes(32))];
                session_regenerate_id(true);
            }
        }

        if (!$guestAllowed && !$context->id()) {
            $this->webRedirect('web/login');
        }

        if ($context->id() && !empty($context->user['require_password_change']) && !$passwordAllowed) {
            $this->webRedirect('web/password');
        }

        return $context;
    }

    protected function webPost(): array
    {
        Security::method($_SERVER['REQUEST_METHOD'] ?? 'GET', true);
        Security::csrf((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''));

        return $_POST;
    }

    protected function webView(string $view, Context $context, array $data = []): void
    {
        $data['viewer'] = $context->safeUser();
        $data['csrf'] = (string) ($_SESSION['csrf'] ?? '');
        $data['navigation'] = array_filter(
            UiSchema::modules(),
            static fn(array $module): bool =>
                $context->can($module['permission'])
                && empty($module['navigation_hidden'])
        );
        $data['flash'] = $_SESSION['web_flash'] ?? null;
        unset($_SESSION['web_flash']);

        $this->load->view('web/header', $data);
        // Internal module views are supported without changing public routes.
        // Only trusted controller-supplied view names may reach this method.
        $path = str_starts_with($view, 'modules/') ? $view : 'web/' . $view;
        $this->load->view($path, $data);
        $this->load->view('web/footer', $data);
    }

    protected function webRedirect(string $route): void
    {
        header('Location: ' . site_url($route), true, 303);
        exit;
    }

    protected function webFlash(string $message, string $type = 'success'): void
    {
        $_SESSION['web_flash'] = ['message' => $message, 'type' => $type];
    }

    protected function webFailure(Throwable $error, string $route): void
    {
        if ($error instanceof Problem) {
            $this->webFlash($error->getMessage(), 'danger');
        } else {
            log_message('error', $error->getMessage());
            $this->webFlash('The operation could not be completed.', 'danger');
        }

        $this->webRedirect($route);
    }

    protected function webError(Context $context, Throwable $error): void
    {
        $status = $error instanceof Problem ? $error->status : 500;
        http_response_code($status);
        if (!($error instanceof Problem)) {
            log_message('error', $error->getMessage());
        }

        $this->webView('error', $context, [
            'page_title' => 'Request unavailable',
            'message' => $error instanceof Problem ? $error->getMessage() : 'The requested page is unavailable.',
        ]);
    }
}
