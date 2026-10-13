# Issue 002: Folder names visible beyond a user's document access

Severity: HIGH (privacy) | Status: Confirmed data-flow issue; impact depends on permission policy | Implemented: NO

Affected: application/models/Folder_model.php; application/controllers/Documents.php; application/view/reusable_components/document_folder_list.php.

Folder_model::tree() fetches all active Area/Specific/Asset/Location and Category names without applying document visibility. Document_model::listing() scopes records, but document_folder_list.php renders all nodes. A user who may see only one document could discover unrelated department folders.

## Copyable repair in Documents::index()

Immediately after the existing Folder_model::browse call and before listing(), add the following. Keep Administrator unchanged so they can set up empty folders.

~~~php
if (strcasecmp((string)$this->user['role'], 'Administrator') !== 0) {
    require_once APPPATH.'services/presentation/document_folder_tree.php';
    $tree = $browser['tree'];
    $tableName = $this->Document_model->config($domain)['table'];
    $fields = $domain === 'hardcopy'
        ? 'd.area_id,d.specific_id,d.asset_id,d.location_id'
        : 'd.category_id';
    $scope = $this->Document_model->scope($domain, $this->user, 'd');
    $paths = $this->db->distinct()->select($fields)
        ->from($tableName.' d')->where($scope, NULL, FALSE)
        ->get()->result_array();

    $allowed = [];
    foreach ($paths as $path) {
        $key = Document_folder_tree::folder_key(
            $path, $tree['nodes'], $domain
        );
        $visited = [];
        while (isset($tree['nodes'][$key]) && !isset($visited[$key])) {
            $visited[$key] = TRUE;
            $allowed[$key] = TRUE;
            $key = $tree['nodes'][$key]['parent'];
        }
    }
    if ($folder !== '' && $folder !== 'all' && !isset($allowed[$folder])) {
        show_error('This folder is outside your document access.', 403);
        return;
    }
    $tree['nodes'] = array_intersect_key($tree['nodes'], $allowed);
    $tree['roots'] = array_values(array_filter(
        $tree['roots'], static function($key) use($allowed) {
            return isset($allowed[$key]);
        }
    ));
    foreach ($tree['nodes'] as &$node) {
        $node['children'] = array_values(array_filter(
            $node['children'], static function($key) use($allowed) {
                return isset($allowed[$key]);
            }
        ));
    }
    unset($node);
    $browser['tree'] = $tree;
}
~~~

## Verify
Use two non-administrator accounts with documents in different Areas and Categories. Neither may see the other's folder names, including by changing ?folder=area:ID. Check a document on page 2 still makes its ancestor folders visible. Benchmark the distinct-path query with large datasets. If the business policy explicitly allows global Place names, mark this issue accepted instead.
