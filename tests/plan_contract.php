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
$sidebar=file_get_contents($path.'/application/view/layout/sidebar_top_nav.php');
$requestView=file_get_contents($path.'/application/view/pages/request/shared_index.php');
$requestModel=file_get_contents($path.'/application/models/Request_model.php');
$notificationModel=file_get_contents($path.'/application/models/Notification_model.php');
$workflowView=file_get_contents($path.'/application/view/pages/workflow_builder/index.php');
$adminController=file_get_contents($path.'/application/controllers/Administration.php');
$loginView=file_get_contents($path.'/application/view/pages/authentication/index.php');
$authSetup=file_get_contents($path.'/application/view/pages/authentication/first_login.php');
$alertPartial=file_get_contents($path.'/application/view/reusable_components/auth_alert.php');
check(strpos($sidebar, "flashdata('notice')")===FALSE, 'No flash banner should be shown at page top');
check(strpos($docFooter, 'id="appAlertModal"')!==FALSE, 'Flash messages must use an alert modal');
check(strpos($alertPartial, '<dialog')!==FALSE, 'Authentication feedback must use a dialog');
check(strpos($loginView, 'login-error')===FALSE, 'Login notifications must not be inline banners');
check(strpos($authSetup, 'alert alert-danger')===FALSE, 'Password setup notices must not be inline banners');
check(strpos($requestModel, "'hardcopy'=>['hardcopy_create','hardcopy_update']")!==FALSE,
  'Disposal is not a My Requests/My Tasks type');
check(strpos($notificationModel, "'hardcopy' => ['hardcopy_create', 'hardcopy_update']")!==FALSE,
  'Disposal must not contribute to request/task badges');
check(strpos($requestView, '<option value="disposal">')===FALSE,
  'Hardcopy request modal must not allow disposal');
check(strpos($requestView, "'kind'=>\$isDocumentRequest?'document':'record'")!==FALSE,
  'Document requests must reuse the System Documents view presentation');
check(strpos($requestView, "tabCount===0")!==FALSE, 'Every request/task tab shows its count');
check(strpos($sidebar, "taskBadge")!==FALSE, 'My Tasks link must show its notification count');
check(strpos($renderer, 'window.PKDocumentView.render(container,data)')!==FALSE,
  'Every system View Details action must use the unified renderer');
check(strpos($workflowView, 'workflow-setup-guide')!==FALSE, 'Workflow Builder must provide setup guidance');
check(strpos($workflowView, 'data-draft=')!==FALSE, 'Workflow Builder must identify editable drafts');
check(strpos($workflowView, 'name="workflow_id"')!==FALSE, 'Workflow actions must retain editor context');
check(strpos($adminController, "redirect.='?workflow='")!==FALSE, 'Workflow editing must retain context on save');
$retentionSource=file_get_contents($path.'/application/services/documents/retention_countdown.php');
$requestDetails=file_get_contents($path.'/application/models/Request_model.php');
check(strpos($detailsModel, "'Days until disposal'=>Retention_countdown::label")!==FALSE,
  'Hardcopy document View Details must calculate remaining days to dispose');
check(strpos($detailsModel, "'Retention Start'=>")===FALSE && strpos($detailsModel, "'Retention End'=>")===FALSE,
  'Hardcopy document View Details must not show separate retention start/end fields');
check(strpos($requestDetails, "'Days until disposal']=Retention_countdown::label")!==FALSE,
  'Hardcopy request View Details must use the same countdown');
check(strpos($retentionSource, "'No retention'")!==FALSE,
  'Disabled or missing retention must be displayed as No retention');
echo "Unified UI/UX and PHP 8.0 contract checks passed.\n";
echo "Plan workflow/UX contract checks passed.\n";
