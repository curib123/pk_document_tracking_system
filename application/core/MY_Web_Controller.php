<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Pk\Core\Context;
use Pk\Core\Problem;
use Pk\Core\Security;
use Pk\Core\UiSchema;

/**
 * Browser HTML response layout only. Session and permission mechanics are
 * centralized in MY_Controller; the controller modules own action gates.
 */
class MY_Web_Controller extends MY_Controller
{
    protected function webContext(bool $guestAllowed = false, bool $passwordAllowed = false): Context
    {
        $this->load->helper(['url', 'ui', 'web_ui']);
        Security::headers();

        header(
            "Content-Security-Policy: default-src 'self'; " .
            "script-src 'self'; " .
            "style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
            "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.gstatic.com; " .
            "img-src 'self' data:; object-src 'none'; base-uri 'self'; " .
            "frame-ancestors 'none'; form-action 'self'; connect-src 'self'"
        );

        return $this->sessionContext($guestAllowed, $passwordAllowed);
    }

    protected function webPost(): array
    {
        return $this->verifyPost();
    }

    protected function webView(string $view, Context $context, array $data = []): void
    {
        $data['viewer'] = $context->safeUser();
        $data['csrf'] = (string) $this->session->userdata('csrf');
        $data['navigation'] = array_filter(
            UiSchema::modules(),
            static fn(array $module): bool =>
                $context->can($module['permission'])
                && empty($module['navigation_hidden'])
        );
        $data['current_route'] = trim((string) $this->uri->uri_string(), '/');
        $data['flash'] = $this->session->flashdata('web_flash');

        $this->load->view('web/header', $data);
        // CI3 normally searches application/views/. Module-owned views need
        // their package root registered with the CI Loader for this render.
        if (str_starts_with($view, 'modules/')) {
            if (!preg_match('~^modules/([a-z_]+)/views/([a-z_][a-z0-9_/]*)$~', $view, $matches)) {
                throw new \LogicException('Invalid trusted module view path.');
            }

            $package = APPPATH . 'modules/' . $matches[1] . '/';
            $this->load->add_package_path($package, false);

            try {
                $this->load->view($matches[2], $data);
            } finally {
                $this->load->remove_package_path($package);
            }
        } else {
            $this->load->view('web/' . $view, $data);
        }

        $this->load->view('web/footer', $data);
    }

    protected function webRedirect(string $route): void
    {
        header('Location: ' . site_url($route), true, 303);
        exit;
    }

    protected function webFlash(string $message, string $type = 'success'): void
    {
        $this->session->set_flashdata('web_flash', [
            'message' => $message,
            'type' => $type,
        ]);
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
            'message' => $error instanceof Problem
                ? $error->getMessage()
                : 'The requested page is unavailable.',
        ]);
    }
}
