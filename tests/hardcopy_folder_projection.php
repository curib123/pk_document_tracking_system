<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__).'/application/');

// Minimal, read-only CI Model/Query Builder fakes for testing the actual
// Folder_model::tree() relation projection without a running database.
class CI_Model { public $db; }
final class FolderQueryResult
{
    private $rows;
    public function __construct(array $rows) { $this->rows=array_values($rows); }
    public function result_array(): array { return $this->rows; }
    public function row_array() { return $this->rows[0]??NULL; }
}
final class FolderFakeDB
{
    private $rows;
    public function __construct(array $rows) { $this->rows=$rows; }
    public function select($fields) { return $this; }
    public function get_where($table,array $where): FolderQueryResult
    {
        $out=array_filter($this->rows[$table]??[],static function($row) use($where) {
            foreach ($where as $key=>$value)
                if ((string)($row[$key]??'')!==(string)$value) return FALSE;
            return TRUE;
        });
        return new FolderQueryResult($out);
    }
}
require_once __DIR__.'/../application/models/Folder_model.php';
function folder_check(bool $condition,string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$model=new Folder_model();
$model->db=new FolderFakeDB([
    'areas'=>[
        ['id'=>1,'name'=>'Area 01','active'=>1],
        ['id'=>2,'name'=>'Area 02','active'=>1]
    ],
    'specifics'=>[
        ['id'=>10,'name'=>'Specific 01','area_id'=>1,'active'=>1],
        ['id'=>11,'name'=>'Specific 02','area_id'=>1,'active'=>1]
    ],
    'assets'=>[
        ['id'=>100,'asset_number'=>'Asset 001','specific_id'=>10,'active'=>1],
        ['id'=>101,'asset_number'=>'Asset 002','specific_id'=>10,'active'=>1]
    ],
    'locations'=>[
        ['id'=>201,'name'=>'Location A','code'=>'A','area_id'=>1,
         'specific_id'=>10,'asset_id'=>100,'active'=>1],
        ['id'=>202,'name'=>'Location B','code'=>'B','area_id'=>1,
         'specific_id'=>NULL,'asset_id'=>100,'active'=>1],
        ['id'=>203,'name'=>'Location C','code'=>'C','area_id'=>1,
         'specific_id'=>11,'asset_id'=>NULL,'active'=>1],
        ['id'=>204,'name'=>'Unassigned Specific','code'=>'D','area_id'=>2,
         'specific_id'=>NULL,'asset_id'=>NULL,'active'=>1],
        ['id'=>205,'name'=>'Location E','code'=>'E','area_id'=>NULL,
         'specific_id'=>NULL,'asset_id'=>101,'active'=>1]
    ]
]);
$tree=$model->tree('hardcopy');
folder_check($tree['roots']===['area:1','area:2'],'Top level must contain Areas only');
folder_check(!isset($tree['nodes']['asset:100']) && !isset($tree['nodes']['asset:101']),
    'Asset must not be a folder in Hardcopy mode');
folder_check($tree['nodes']['area:1']['children']===['specific:10','specific:11'],
    'Areas must contain Specific folders directly');
folder_check($tree['nodes']['specific:10']['children']===
    ['location:201','location:202','location:205'],
    'Specific folders must contain Locations even when Location points to an Asset');
folder_check($tree['nodes']['location:201']['parent']==='specific:10' &&
    $tree['nodes']['location:202']['parent']==='specific:10' &&
    $tree['nodes']['location:205']['parent']==='specific:10',
    'An Asset-linked Location must resolve its Specific without creating Asset folders');
folder_check($tree['nodes']['location:203']['parent']==='specific:11',
    'Asset-less Locations must still display under their Specific');
folder_check($tree['nodes']['location:204']['parent']==='area:2',
    'Location with no Specific must stay accessible under its Area');

$browse=$model->browse('hardcopy','asset:100');
folder_check($browse['folder']==='specific:10' &&
    $browse['breadcrumbs'][2]['folder']==='specific:10',
    'Legacy Asset-folder bookmarks should open the corresponding Specific folder');

echo "Hardcopy actual Folder_model Area > Specific > Location contracts passed.\n";
