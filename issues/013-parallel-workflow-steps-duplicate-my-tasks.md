# Issue 013: Parallel active workflow steps can duplicate My Tasks rows

Severity: MEDIUM (conditional) | Status: Confirmed join multiplication possibility; depends on workflow model | Implemented: NO

Affected: application/models/Request_model.php::scope() and listing().

For task listing, scope() INNER JOINs workflow_steps to requests, filtering steps assigned to the user or their role. If multiple active matching steps exist for one request, COUNT(*) and the request list can count/show it twice. Dashboard_model::summary() already uses COUNT(DISTINCT r.id), suggesting deduplicated task counts are intended.

## Copyable fix, part A

Replace the task-specific block in Request_model::scope() with an EXISTS filter that cannot duplicate request rows:

~~~php
if ($task) {
    $uid = (int)$user['id'];
    $role = (int)$user['role_id'];
    $assigned = "(ws.assigned_user_id={$uid} OR ".
        "(JSON_UNQUOTE(JSON_EXTRACT(ws.assignment, '$.type'))='role' ".
        "AND CAST(JSON_UNQUOTE(JSON_EXTRACT(ws.assignment, '$.value')) ".
        "AS UNSIGNED)={$role}))";
    $this->db->where('r.status','submitted');
    $this->db->where(
        "EXISTS (SELECT 1 FROM workflow_steps ws ".
        "WHERE ws.request_id=r.id AND ws.status='active' ".
        "AND {$assigned})", NULL, FALSE
    );
} else {
    $this->db->where('r.requested_by',$user['id']);
}
~~~

## Copyable fix, part B

In Request_model::listing(), replace the task-specific SELECT referencing joined ws with the following scalar correlated selects so the existing step_label/step_id keys remain present:

~~~php
if ($task) {
    $uid = (int)$user['id'];
    $role = (int)$user['role_id'];
    $match = "(x.assigned_user_id={$uid} OR ".
        "(JSON_UNQUOTE(JSON_EXTRACT(x.assignment, '$.type'))='role' ".
        "AND CAST(JSON_UNQUOTE(JSON_EXTRACT(x.assignment, '$.value')) ".
        "AS UNSIGNED)={$role}))";
    $base = " FROM workflow_steps x WHERE x.request_id=r.id ".
        "AND x.status='active' AND {$match} ORDER BY x.id LIMIT 1";
    $this->db->select(
        "(SELECT x.label{$base}) AS step_label, ".
        "(SELECT x.id{$base}) AS step_id", FALSE
    );
}
~~~

Keep all other listing filters and ordering. This is an optional enhancement if parallel approvals are supported; if the database guarantees only one active matching step, document that invariant and close the issue.

## Verify
Create a request with two active steps matching the same user (one direct and one role) in a test database. It should appear exactly once in My Tasks, with total and pagination both equal to one. The selected step_id must refer to an authorized active step. Test submitting an approval against the selected step and role-based approvals.
