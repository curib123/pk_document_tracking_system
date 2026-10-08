<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'core/MY_Web_Controller.php';

class Web_auth extends MY_Web_Controller
{
    public function index(): void
    {
        $context = $this->webContext(true, true);
        if ($context->id()) {
            $this->webRedirect('web');
        }

        $this->webView('login', $context, ['page_title' => 'Sign in']);
    }

    public function sign_in(): void
    {
        $context = $this->webContext(true, true);

        try {
            $input = $this->webPost();
            if ($context->id()) {
                $this->webRedirect('web');
            }

            (new Auth_service($context))->login($input);
            $this->webRedirect('web');
        } catch (Pk\Core\Problem $error) {
            http_response_code($error->status);
            $this->webView('login', $context, [
                'page_title' => 'Sign in',
                'message' => $error->getMessage(),
                'username' => (string) ($_POST['username'] ?? ''),
            ]);
        }
    }

    public function password(): void
    {
        $context = $this->webContext(false, true);
        $this->webView('password', $context, ['page_title' => 'Change password']);
    }

    public function change_password(): void
    {
        $context = $this->webContext(false, true);

        try {
            (new Auth_service($context))->changePassword($this->webPost());
            $this->webFlash('Password changed successfully.');
            $this->webRedirect('web');
        } catch (Throwable $error) {
            $this->webFailure($error, 'web/password');
        }
    }

    public function logout(): void
    {
        $context = $this->webContext(false, true);

        try {
            $this->webPost();
            (new Auth_service($context))->logout();
            $this->webRedirect('web/login');
        } catch (Throwable $error) {
            $this->webFailure($error, 'web');
        }
    }
}
