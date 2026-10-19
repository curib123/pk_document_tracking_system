<?php
declare(strict_types=1);
require_once __DIR__.'/../application/services/workflow/workflow_graph.php';

// Structural checks ignore source-code whitespace (not runtime values).
function contract_position(string $source, string $fragment) {
    return strpos(preg_replace('/\\s+/', '', $source), preg_replace('/\\s+/', '', $fragment));
}
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
$seed=is_file($path.'/database/seed_workflows.sql')
    ? file_get_contents($path.'/database/seed_workflows.sql') : NULL;
check(contract_position($view, 'data-workflow-version-panel')!==FALSE, 'Version panels must render');
check(contract_position($view, 'admin/workflows/publish')!==FALSE, 'Published route action must exist');
check(contract_position($view, 'stepApproverType')!==FALSE, 'Approver chooser must exist');
check(contract_position($renderer, 'readableDetail')!==FALSE, 'Nested details must use safe formatter');
check(contract_position($requests, "if (!empty(\$r['workflow_version_id']) && !empty(\$r['snapshot']))")!==FALSE, 'Returned requests must retain their snapshot');
check(contract_position($badge, "COUNT(DISTINCT r.id)")!==FALSE, 'Task counts must avoid duplicates');
if ($seed!==NULL) check(contract_position($seed, 'NOT EXISTS')!==FALSE,
    'Seeding must not overwrite live workflows'); // Legacy seed no longer bundled.
$detailsModel=file_get_contents($path.'/application/models/Document_detail_model.php');
$docHeader=file_get_contents($path.'/application/view/layout/header.php');
$docFooter=file_get_contents($path.'/application/view/layout/footer.php');
$docViewJs=file_get_contents($path.'/public/assets/js/document-view.js');
check(contract_position($detailsModel, "'kind'=>'document'")!==FALSE, 'Document details must declare their presentation kind');
check(contract_position($detailsModel, "'context_label'")!==FALSE, 'Document details require contextual metadata');
check(contract_position($docHeader, 'document-view.css')!==FALSE, 'Document view styles must be loaded');
check(contract_position($docFooter, 'document-view.js')!==FALSE, 'Document view renderer must be loaded');
check(contract_position($docFooter, 'viewDocumentStatus')!==FALSE, 'Document modal status must be accessible');
check(contract_position($docViewJs, 'aria-controls')!==FALSE, 'Document tab navigation must identify its panels');
check(contract_position($docViewJs, 'renderOverview(overview, data.domain)')!==FALSE, 'Document overview must render before tabs');
check(contract_position($docViewJs, 'sections.filter(isOverviewSection)')!==FALSE, 'Document overview must be excluded from tabs');
$overviewPosition=contract_position($docFooter, 'id="viewDocumentSummary"');
$tabsPosition=contract_position($docFooter, 'id="viewDetails"');
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
check(contract_position($sidebar, "flashdata('notice')")===FALSE, 'No flash banner should be shown at page top');
check(contract_position($docFooter, 'id="appAlertModal"')!==FALSE, 'Flash messages must use an alert modal');
check(contract_position($alertPartial, '<dialog')!==FALSE, 'Authentication feedback must use a dialog');
check(contract_position($loginView, 'login-error')===FALSE, 'Login notifications must not be inline banners');
check(contract_position($authSetup, 'alert alert-danger')===FALSE, 'Password setup notices must not be inline banners');
check(contract_position($requestModel, "'hardcopy-disposal'=>['disposal']")!==FALSE,
  'Disposal must have a My Requests/My Tasks tab');
check(contract_position($notificationModel, "'hardcopy-disposal' => ['disposal']")!==FALSE,
  'Disposal must contribute to its request/task badges');
check(contract_position($requestView, '<option value="disposal">')!==FALSE &&
    contract_position($requestView, "pages/request/disposal_fields")!==FALSE,
  'Disposal request modal must offer the existing disposal reasons');
check(contract_position($requestView, "'kind'=>\$isDocumentRequest?'document':'record'")!==FALSE,
  'Document requests must reuse the System Documents view presentation');
check(contract_position($requestView, "tabCount===0")!==FALSE, 'Every request/task tab shows its count');
check(contract_position($sidebar, "taskBadge")!==FALSE, 'My Tasks link must show its notification count');
check(contract_position($renderer, 'window.PKDocumentView.render(container,data)')!==FALSE,
  'Every system View Details action must use the unified renderer');
check(contract_position($workflowView, 'workflow-setup-guide')!==FALSE, 'Workflow Builder must provide setup guidance');
check(contract_position($workflowView, 'data-draft=')!==FALSE, 'Workflow Builder must identify editable drafts');
check(contract_position($workflowView, 'name="workflow_id"')!==FALSE, 'Workflow actions must retain editor context');
check(contract_position($adminController, "redirect.='?workflow='")!==FALSE, 'Workflow editing must retain context on save');
$retentionSource=file_get_contents($path.'/application/services/documents/retention_countdown.php');
$requestDetails=file_get_contents($path.'/application/models/Request_model.php');
check(contract_position($detailsModel, "'Days until disposal'=>Retention_countdown::label")!==FALSE,
  'Hardcopy document View Details must calculate remaining days to dispose');
check(contract_position($detailsModel, "'Retention Start'=>")===FALSE && contract_position($detailsModel, "'Retention End'=>")===FALSE,
  'Hardcopy document View Details must not show separate retention start/end fields');
check(contract_position($requestDetails, "'Days until disposal']=Retention_countdown::label")!==FALSE,
  'Hardcopy request View Details must use the same countdown');
check(contract_position($retentionSource, "'No retention'")!==FALSE,
  'Disabled or missing retention must be displayed as No retention');
echo "Unified UI/UX and PHP 8.0 contract checks passed.\n";
$register=file_get_contents($path.'/application/view/pages/documents/shared_index.php');
$registerCss=file_get_contents($path.'/public/assets/css/unified-ux.css');
check(contract_position($register, "'title'=>'Document','location_name'=>'Location'")!==FALSE &&
      contract_position($register, "'sequence_number'=>'Copy Number'")===FALSE,
  'Hardcopy register must show document and location without a Copy Number column');
check(contract_position($register, "'holder_name'=>'Holder','status'=>'Status'")!==FALSE,
  'Hardcopy register must retain holder and status');
check(contract_position($register, "'title'=>'Document','document_number'=>'Document Number'")!==FALSE,
  'Softcopy register must show title and document number first');
check(contract_position($register, "'category_name'=>'Category','status'=>'Status'")!==FALSE,
  'Softcopy register must retain category and status');
check(contract_position($register, "'area_name'=>'Area'")===FALSE &&
      contract_position($register, "'asset_name'=>'Asset'")===FALSE &&
      contract_position($register, "'series_number'=>'Series'")===FALSE,
  'Detailed physical hierarchy and series must not crowd the register columns');
check(contract_position($register, '$display=$document_details[$r[\'id\']]??$display;')!==FALSE,
  'The full permission-scoped detail record must still power View Details');
check(contract_position($register, "'dt_extra_params'")!==FALSE && contract_position($register, "'dt_rows'")!==FALSE,
  'Document register must preserve shared table and action components');
check(contract_position($registerCss, '.document-register-listing[data-doc-domain="hardcopy"]')!==FALSE,
  'Hardcopy register must have priority-based mobile columns');
check(contract_position($registerCss, '.document-register-listing[data-doc-domain="softcopy"]')!==FALSE,
  'Softcopy register must have priority-based mobile columns');
echo "Compact System Documents columns and full View Details contracts passed.\n";
echo "Plan workflow/UX contract checks passed.\n";
