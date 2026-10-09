<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Pk\Core\Context;
use Pk\Core\Database;
use Pk\Core\Problem;
use Pk\Core\Security;

/**
 * Shared CI3 controller mechanics: sessions, authentication and authorization.
 *
 * Feature controllers explicitly call require_permission() in protected actions.
 * Business validation remains in services and SQL stays in models.
 */
class MY_Controller extends CI_Controller
{
    protected ?Context $requestContext = null;

    public function __construct()
    {
        parent::__construct();

        // Every HTTP controller uses ONE CodeIgniter session driver, including
        // the legacy workspace while its routes are being migrated.
        $this->load->library('session');
        $token = $this->session->userdata('csrf');

        if (!is_string($token) || strlen($token) !== 64) {
            $this->session->set_userdata('csrf', bin2hex(random_bytes(32)));
        }
    }

    /**
     * Restore a session against the current database account and role.
     * Never trust permissions, account status or session version from a cookie.
     */
    protected function sessionContext(
        bool $guestAllowed = false,
        bool $passwordAllowed = false
    ): Context {
        $context = new Context(Database::connect());
        $userId = (int) $this->session->userdata('user_id');

        if ($userId > 0) {
            try {
                $lastSeen = (int) $this->session->userdata('last_seen');

                if ($lastSeen <= 0 || time() - $lastSeen > 1800) {
                    throw new Problem('Your session expired.', 401);
                }

                $context->identify($userId);

                if ((int) $context->user['session_version']
                    !== (int) $this->session->userdata('session_version')) {
                    throw new Problem('Your session is no longer valid.', 401);
                }

                $this->session->set_userdata('last_seen', time());
            } catch (Problem $error) {
                if ($error->status !== 401) {
                    throw $error;
                }

                $this->clearAuthenticatedSession();
                $context = new Context(Database::connect());
            }
        }

        $this->requestContext = $context;

        if (!$guestAllowed && !$context->id()) {
            $this->load->helper('url');
            header('Location: ' . site_url('web/login'), true, 303);
            exit;
        }

        if ($context->id()
            && !empty($context->user['require_password_change'])
            && !$passwordAllowed) {
            $this->load->helper('url');
            header('Location: ' . site_url('web/password'), true, 303);
            exit;
        }

        return $context;
    }

    /**
     * Explicit gate to invoke at the START of index, detail, create, edit,
     * save and delete when an action requires a capability.
     */
    protected function require_permission(
        string $permission,
        ?Context $context = null
    ): void {
        $current = $context ?? $this->requestContext;

        if ($current === null) {
            throw new \LogicException('Load the authenticated request context first.');
        }

        $current->require($permission);
    }

    protected function verifyPost(): array
    {
        Security::method($_SERVER['REQUEST_METHOD'] ?? 'GET', true);
        Security::csrf(
            (string) $this->session->userdata('csrf'),
            (string) ($_POST['csrf'] ?? '')
        );

        return $_POST;
    }

    /**
     * Shared authentication transitions. The underscore deliberately makes
     * this bridge inaccessible through CodeIgniter's public URL routing.
     *
     * Used by native form controllers and the still-supported API gateway.
     */
    public function _complete_auth_session(string $operation, Context $context): void
    {
        if ($operation === 'auth.logout') {
            $this->clearAuthenticatedSession();
            return;
        }

        if (!$context->id() || !isset($context->user['session_version'])) {
            throw new \LogicException('An authenticated user is required.');
        }

        if ($operation !== 'auth.login' && $operation !== 'auth.password') {
            throw new \LogicException('Unsupported session transition.');
        }

        $this->session->sess_regenerate(true);

        if ($operation === 'auth.login') {
            // Prevent credentials and unsaved forms leaking across users.
            $this->session->unset_userdata([
                'pk_catalog_draft', 'pk_catalog_error', 'web_flash',
            ]);
        }

        $this->session->set_userdata([
            'user_id' => $context->id(),
            'session_version' => (int) $context->user['session_version'],
            'last_seen' => time(),
            'csrf' => bin2hex(random_bytes(32)),
        ]);
    }

    private function clearAuthenticatedSession(): void
    {
        $this->session->unset_userdata([
            'user_id', 'session_version', 'last_seen',
            'pk_catalog_draft', 'pk_catalog_error', 'web_flash',
        ]);
        $this->session->sess_regenerate(true);
        $this->session->set_userdata('csrf', bin2hex(random_bytes(32)));
    }

    // Old workspace remains available during the ordinary MVC migration.
    protected function endpoint(string $path, array $parameters = []): void
    {
        Http_gateway::respond($path, $parameters);
    }

    protected function module_page(?string $module = null): void
    {
        Security::headers();
        $this->load->helper('ui');
        $data = [
            'initial_module' => $module ?? '',
            'page_title' => 'PK Document Tracking System',
        ];
        $this->load->view('templates/header', $data);
        $this->load->view('modules/index', $data);
        $this->load->view('templates/footer', $data);
    }
}
