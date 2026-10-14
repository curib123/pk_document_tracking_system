<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Audit extends MY_Controller
{
    public function index()
    {
        $this->require_permission('audit', 'view');
        require_once APPPATH . 'services/audit/audit_service.php';
        $table = $this->table_state('timestamp');
        if (!in_array($table['status'], ['', 'success', 'failed'], TRUE))
            show_error('Choose a valid audit status.', 400);
        $table['sort'] = 'timestamp';
        if ($this->input->get('dir') === NULL)
            $table['dir'] = 'DESC';
        $result = (new Audit_service(PK_ROOT . '/storage/audit'))->listing($table);
        $table['page'] = $result['page'];
        $ids = array_unique(array_column($result['rows'], 'actor_id'));
        $names = $ids ? array_column($this->db->select('id,CONCAT_WS(" ",first_name,last_name) AS name', FALSE)
            ->from('users')->where_in('id', $ids)->get()->result_array(), 'name', 'id') : [];
        foreach ($result['rows'] as &$row)
            $row['actor_name'] = $names[$row['actor_id']] ?? 'User #' . $row['actor_id'];
        unset($row);
        $this->render('Audit Trail', 'pages/administration/audit', [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'table' => $table,
            'audit_warnings' => $result['warnings']
        ]);
    }
}
