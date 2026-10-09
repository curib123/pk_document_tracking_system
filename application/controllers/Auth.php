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
        $this->require_post();
        $this->load->model('Auth_model');
        $login = trim((string) $this->input->post('login', TRUE));
        $password = (string) $this->input->post('password');
        $user = $this->Auth_model->find_active($login);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->notice('Invalid credentials.', 'danger');
            return redirect('login');
        }
        $this->session->sess_regenerate(TRUE);
        $this->session->set_userdata('user_id', (int) $user['id']);
        redirect('dashboard');
    }

    public function logout()
    {
        $this->require_post();
        $this->session->sess_destroy();
        redirect('login');
    }

    public function change_password()
    {
        $this->authenticate();
        $this->confirmed();
        $current = (string) $this->input->post('current_password');
        $new = (string) $this->input->post('new_password');
        $stored = $this->db->get_where('users', ['id' => $this->user['id']])->row_array();
        if (!password_verify($current, $stored['password_hash']) || strlen($new) < 12) {
            $this->notice('Check current password and use at least 12 characters.', 'danger');
            return redirect('dashboard');
        }
        $this->db->where('id', $this->user['id'])->update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
        $this->notice('Password updated.');
        redirect('dashboard');
    }
}
