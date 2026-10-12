<?php
declare(strict_types=1);
require_once __DIR__.'/../application/services/workflow/workflow_graph.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function rejects(callable $fn, string $message): void {
    try { $fn(); }
    catch (DomainException $e) { return; }
    throw new RuntimeException($message);
}
$valid = ['steps' => [
    ['key'=>'step_1','name'=>'Supervisor','approver'=>['type'=>'requester','value'=>NULL]],
    ['key'=>'step_2','name'=>'Records','approver'=>['type'=>'requester_leader','value'=>NULL]]
]];
Workflow_graph::validate($valid, TRUE);
check(count(Workflow_graph::renumber([$valid['steps'][1],$valid['steps'][0]]))===2, 'Renumber must preserve steps');
$changed=Workflow_graph::renumber([$valid['steps'][1],$valid['steps'][0]]);
check($changed[0]['key']==='step_1' && $changed[1]['key']==='step_2', 'Renumber must set sequential keys');
Workflow_graph::validate(['steps'=>[]],FALSE);
rejects(static function() use($valid) {
    $invalid=['steps'=>[1=>$valid['steps'][0],2=>$valid['steps'][1]]];
    Workflow_graph::validate($invalid,TRUE);
},'Approval steps must be indexed as a list even on PHP 8.0');

rejects(static function(){Workflow_graph::validate(['steps'=>[]],TRUE);},'An empty route cannot be published');
rejects(static function()use($valid){$valid['steps'][0]['approver']['type']='unrecognized';Workflow_graph::validate($valid,TRUE);},'Invalid approver types must be refused');
rejects(static function()use($valid){$valid['steps'][0]['approver']=['type'=>'role','value'=>0];Workflow_graph::validate($valid,TRUE);},'A role approver requires a role');
rejects(static function()use($valid){$valid['steps'][1]['key']='step_3';Workflow_graph::validate($valid,TRUE);},'Non-sequential steps must be refused');

$path=dirname(__DIR__);
$view=file_get_contents($path.'/application/view/pages/workflow_builder/index.php');
$renderer=file_get_contents($path.'/public/assets/js/app.js');
$requests=file_get_contents($path.'/application/services/request/request_service.php');
$badge=file_get_contents($path.'/application/models/Notification_model.php');
$seed=file_get_contents($path.'/database/seed_workflows.sql');
check(strpos($view, 'data-workflow-version-panel')!==FALSE, 'Version panels must render');
check(strpos($view, 'admin/workflows/publish')!==FALSE, 'Published route action must exist');
check(strpos($view, 'stepApproverType')!==FALSE, 'Approver chooser must exist');
check(strpos($renderer, 'readableDetail')!==FALSE, 'Nested details must use safe formatter');
check(strpos($requests, "if (!empty(\$r['workflow_version_id']) && !empty(\$r['snapshot']))")!==FALSE, 'Returned requests must retain their snapshot');
check(strpos($badge, "COUNT(DISTINCT r.id)")!==FALSE, 'Task counts must avoid duplicates');
check(strpos($seed, 'NOT EXISTS')!==FALSE, 'Seeding must not overwrite live workflows');
$detailsModel=file_get_contents($path.'/application/models/Document_detail_model.php');
$docHeader=file_get_contents($path.'/application/view/layout/header.php');
$docFooter=file_get_contents($path.'/application/view/layout/footer.php');
$docViewJs=file_get_contents($path.'/public/assets/js/document-view.js');
check(strpos($detailsModel, "'kind'=>'document'")!==FALSE, 'Document details must declare their presentation kind');
check(strpos($detailsModel, "'context_label'")!==FALSE, 'Document details require contextual metadata');
check(strpos($docHeader, 'document-view.css')!==FALSE, 'Document view styles must be loaded');
check(strpos($docFooter, 'document-view.js')!==FALSE, 'Document view renderer must be loaded');
check(strpos($docFooter, 'viewDocumentStatus')!==FALSE, 'Document modal status must be accessible');
check(strpos($docViewJs, 'aria-controls')!==FALSE, 'Document tab navigation must identify its panels');
check(strpos($docViewJs, 'renderOverview(overview,data)')!==FALSE, 'Document overview must render before tabs');
check(strpos($docViewJs, 'sections.filter(isOverviewSection)')!==FALSE, 'Document overview must be excluded from tabs');
$overviewPosition=strpos($docFooter, 'id="viewDocumentSummary"');
$tabsPosition=strpos($docFooter, 'id="viewDetails"');
check($overviewPosition!==FALSE && $tabsPosition!==FALSE &&
    $overviewPosition<$tabsPosition, 'Document overview must appear above the separate-concern tabs');
echo "Plan workflow/UX contract checks passed.\n";
