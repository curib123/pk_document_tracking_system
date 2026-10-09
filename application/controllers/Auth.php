<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Auth extends MY_Controller
{
    public function index()
    {
        if ($this->user) return redirect('dashboard');
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
        redirect('dashboard');
    }

    public function logout()
    {
        $this->confirmed();
        $this->session->sess_destroy();
        redirect('login');
    }

    public function change_password()
    {
        $this->authenticate();
        $this->confirmed();
        $row = $this->db->get_where('users', ['id' => $this->user['id']])->row_array();
        $old = (string) $this->input->post('current_password');
        $new = (string) $this->input->post('new_password');
        if (!password_verify($old, $row['password_hash']) || strlen($new) < 12) {
            $this->notice('Incorrect current password or new password shorter than 12 characters.', 'danger');
            return redirect('dashboard');
        }
        $this->db->where('id', $this->user['id'])->update('users', [
            'password_hash' => password_hash($new, PASSWORD_DEFAULT),
            'require_password_change' => 0,
            'session_version' => (int) $row['session_version'] + 1
        ]);
        $this->session->set_userdata('session_version', (int) $row['session_version'] + 1);
        $this->notice('Password changed successfully.');
        redirect('dashboard');
    }
}
