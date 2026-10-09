<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Auth extends MY_Controller
{
    public function index()
    {
        if ($this->user) return redirect(!empty($this->user['require_password_change']) ? 'change-password' : 'dashboard');
        $this->load->view('pages/authentication/index');
    }

    public function login()
    {
        if ($this->input->method(TRUE) !== 'POST') show_error('Method not allowed.', 405);
        $username = trim((string) $this->input->post('login', TRUE));
        $password = (string) $this->input->post('password');
        $key = hash('sha256', strtolower($username) . '|' . (string) $this->input->ip_address());
        $attempt = $this->db->get_where('login_attempts', ['attempt_key' => $key])->row_array();
        if ($attempt && strtotime($attempt['window_started']) > time() - 900 && $attempt['failures'] >= 10) {
            $this->notice('Too many attempts. Please try again later.', 'danger');
            return redirect('login');
        }
        $user = $this->Identity_model->find_login($username);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            if (!$attempt || strtotime($attempt['window_started']) <= time() - 900) {
                $this->db->replace('login_attempts', [
                    'attempt_key' => $key, 'failures' => 1,
                    'window_started' => date('Y-m-d H:i:s')
                ]);
            } else {
                $this->db->where('attempt_key', $key)
                    ->set('failures', 'failures + 1', FALSE)->update('login_attempts');
            }
            $this->notice('Invalid username or password.', 'danger');
            return redirect('login');
        }
        $this->db->where('attempt_key', $key)->delete('login_attempts');
        $this->session->sess_regenerate(TRUE);
        $this->session->set_userdata([
            'user_id' => (int) $user['id'],
            'session_version' => (int) $user['session_version']
        ]);
        redirect(!empty($user['require_password_change']) ? 'change-password' : 'dashboard');
    }

    public function logout()
    {
        $this->confirmed();
        $this->session->sess_destroy();
        redirect('login');
    }

    public function setup()
    {
        $this->authenticate();
        if (empty($this->user['require_password_change'])) return redirect('dashboard');
        $this->output->set_header('Cache-Control: no-store, private');
        $this->load->view('layout/header', ['title'=>'Secure Your Account']);
        $this->load->view('pages/authentication/first_login');
    }

    public function change_password()
    {
        $this->authenticate();
        $this->confirmed();
        $target = !empty($this->user['require_password_change']) ? 'change-password' : 'dashboard';
        require_once APPPATH.'services/authentication/authentication_service.php';
        $old = (string)$this->input->post('current_password');
        $new = (string)$this->input->post('new_password');
        try {
            Authentication_service::validate_new_password($old, $new,
                (string)$this->input->post('confirm_password'));
            $this->db->trans_begin();
            $row = $this->db->query('SELECT * FROM users WHERE id=? FOR UPDATE',
                [(int)$this->user['id']])->row_array();
            if (!$row || !$row['active'] ||
                (int)$row['session_version'] !== (int)$this->user['session_version'] ||
                !password_verify($old, $row['password_hash'])) {
                throw new DomainException('Your current password or session is no longer valid.');
            }
            $version = (int)$row['session_version'] + 1;
            $this->db->where('id', $row['id'])->update('users', [
                'password_hash'=>password_hash($new, PASSWORD_DEFAULT),
                'require_password_change'=>0, 'session_version'=>$version
            ]);
            if ($this->db->trans_status() === FALSE) throw new DomainException('Password could not be changed.');
            $this->db->trans_commit();
            $this->session->sess_regenerate(TRUE);
            $this->session->set_userdata('session_version', $version);
            $this->notice('Password changed successfully.');
            redirect('dashboard');
        } catch (DomainException $e) {
            $this->db->trans_rollback();
            $this->notice($e->getMessage(), 'danger');
            redirect($target);
        }
    }
}
