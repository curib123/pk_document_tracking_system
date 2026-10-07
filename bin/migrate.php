<?php
declare(strict_types=1);

if (PHP_SAPI!=='cli') { http_response_code(404); exit; }

require dirname(__DIR__).'/application/bootstrap.php';

use Pk\Core\{Context,Database,WorkflowGraph};

function table_exists(Database $db,string $table): bool
{
    return (bool)$db->one(
        'SELECT 1 AS found FROM information_schema.tables WHERE table_schema=? AND table_name=? LIMIT 1',
        [$db->builder->database,$table]
    );
}

function column_exists(Database $db,string $table,string $column): bool
{
    return (bool)$db->one(
        'SELECT 1 AS found FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=? LIMIT 1',
        [$db->builder->database,$table,$column]
    );
}

function legacy_workflow(Database $db,array $graph,int $adminRole): array
{
    if (isset($graph['steps']) && is_array($graph['steps'])) {
        $normalized=WorkflowGraph::validate($graph,false);
        $map=[];
        foreach ($normalized['steps'] as $step) $map[$step['key']]=$step['key'];
        return [$normalized,$map];
    }

    $steps=[]; $map=[];
    foreach (($graph['nodes'] ?? []) as $node) {
        if (!is_array($node) || ($node['type'] ?? '')!=='approval') continue;
        $assignment=is_array($node['assignment'] ?? null) ? $node['assignment'] : [];
        $type=$assignment['type'] ?? '';
        $approver=null;

        if ($type==='user' && !empty($assignment['value'])) {
            $user=$db->one('SELECT u.*, r.active AS role_active FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? LIMIT 1',[(int)$assignment['value']]);
            if ($user && (int)$user['active']===1 && (int)$user['role_active']===1) {
                $approver=['type'=>'user','value'=>(int)$user['id'],'label'=>Context::name($user).' — '.$user['position_title']];
            }
        } elseif ($type==='role' && !empty($assignment['value'])) {
            $role=$db->one('SELECT id,name FROM roles WHERE id=? AND active=1 LIMIT 1',[(int)$assignment['value']]);
            if ($role) $approver=['type'=>'role','value'=>(int)$role['id'],'label'=>$role['name']];
        } elseif ($type==='leader') {
            $approver=['type'=>'leader','label'=>"Requester's Leader"];
        }

        // Legacy permission/document approvers have no equivalent in the simplified
        // workflow. Route those steps to the Administrator role during migration.
        if (!$approver) {
            $approver=['type'=>'role','value'=>$adminRole,'label'=>'Administrator'];
        }

        $index=count($steps)+1;
        $steps[]=[
            'name'=>(string)($node['label'] ?? ('Approval Step '.$index)),
            'approver'=>$approver,
        ];
        if (is_string($node['key'] ?? null)) $map[$node['key']]='step_'.$index;
    }

    if (!$steps) $steps[]=['name'=>'Administrator Approval','approver'=>['type'=>'role','value'=>$adminRole,'label'=>'Administrator']];
    return [WorkflowGraph::validate(['steps'=>$steps],true),$map];
}

try {
    $db=Database::connect();
    if (!table_exists($db,'schema_migrations')) throw new RuntimeException('Database schema is not installed.');

    $current=(int)($db->one('SELECT COALESCE(MAX(version),0) AS version FROM schema_migrations')['version'] ?? 0);
    if ($current>=2) { echo "Database schema is already version $current.\n"; exit(0); }
    if ($current!==1) throw new RuntimeException('Unsupported schema version '.$current.'.');

    $adminRole=(int)($db->one("SELECT id FROM roles WHERE name='Administrator' LIMIT 1")['id'] ?? 0);
    if (!$adminRole) throw new RuntimeException('Administrator role is missing.');

    if (!column_exists($db,'workflow_versions','is_default')) {
        $db->query('ALTER TABLE workflow_versions ADD COLUMN is_default TINYINT NOT NULL DEFAULT 0 AFTER status');
        $db->query('ALTER TABLE workflow_versions ADD INDEX publication_default (workflow_id,status,is_default)');
    }

    foreach ($db->all('SELECT id,graph FROM workflow_versions ORDER BY id') as $version) {
        $graph=json_decode((string)$version['graph'],true,512,JSON_THROW_ON_ERROR);
        [$converted]=legacy_workflow($db,$graph,$adminRole);
        $db->update('workflow_versions',(int)$version['id'],['graph'=>Context::json($converted)]);
    }

    foreach ($db->all('SELECT id,snapshot,current_node FROM requests WHERE snapshot IS NOT NULL') as $request) {
        $graph=json_decode((string)$request['snapshot'],true,512,JSON_THROW_ON_ERROR);
        [$converted,$map]=legacy_workflow($db,$graph,$adminRole);
        $data=['snapshot'=>Context::json($converted)];
        $oldNode=$request['current_node'];
        if ($oldNode!==null && isset($map[$oldNode])) $data['current_node']=$map[$oldNode];
        $db->update('requests',(int)$request['id'],$data);

        foreach ($db->all('SELECT id,node_key FROM workflow_steps WHERE request_id=?',[(int)$request['id']]) as $step) {
            if (isset($map[$step['node_key']])) {
                $db->update('workflow_steps',(int)$step['id'],['node_key'=>$map[$step['node_key']]]);
            }
        }
    }

    $db->query('UPDATE workflow_versions SET is_default=0');
    $db->query(
        "UPDATE workflow_versions v
         JOIN workflows w ON w.id=v.workflow_id AND w.active=1
         JOIN (
             SELECT workflow_id,MAX(version_number) AS max_version
             FROM workflow_versions
             WHERE status='published'
             GROUP BY workflow_id
         ) latest ON latest.workflow_id=v.workflow_id AND latest.max_version=v.version_number
         SET v.is_default=1"
    );

    if (column_exists($db,'requests','approver_config')) {
        $db->query('ALTER TABLE requests DROP COLUMN approver_config');
    }
    if (table_exists($db,'document_approvers')) {
        $db->query('DROP TABLE document_approvers');
    }

    $db->query('INSERT INTO schema_migrations(version) VALUES(2)');
    echo "Migrated database schema from version 1 to version 2.\n";
} catch (Throwable $e) {
    fwrite(STDERR,'Migration failed: '.$e->getMessage()."\n");
    exit(1);
}
