<?php
$root=dirname(__DIR__);
$files=['application/bootstrap.php','application/core/MY_Controller.php',
 'application/controllers/Auth.php','application/controllers/Dashboard.php',
 'application/controllers/Places.php','application/controllers/Documents.php',
 'application/controllers/Requests.php','application/controllers/Administration.php',
 'application/models/Place_model.php','application/models/Document_model.php',
 'application/models/Request_model.php','application/models/Administration_model.php',
 'application/view/reusable_components/reusable_datatable.php',
 'database/pk_dts.sql','database/seed.sql'];
$fail=[];
foreach($files as $f) if (!is_file($root.'/'.$f) || filesize($root.'/'.$f)===0) $fail[]="Missing ".$f;
$sql=file_get_contents($root.'/database/pk_dts.sql');
foreach(['users','roles','permissions','areas','specifics','assets','locations','categories',
  'hardcopy_documents','softcopy_documents','requests','workflow_steps','workflow_history',
  'workflow_versions','access_grants','transfers','files'] as $t) {
 if (strpos($sql,'CREATE TABLE ' . chr(96) . $t . chr(96))===FALSE) $fail[]="Missing table ".$t;
}
if (strpos($sql,'INSERT INTO ' . chr(96) . 'users' . chr(96))!==FALSE) $fail[]='User data in schema';
// Login screen retains native CI3 form posts and its full-viewport visual layout.
$login=file_get_contents($root.'/application/view/pages/authentication/index.php');
$css=file_get_contents($root.'/public/assets/css/app.css');
foreach (['login-shell','login-cover','login-panel','login-card','assets/images/building.jpg',
    'assets/images/peanut-kisses.jpg','site_url(\'login\')'] as $item) {
    if (strpos($login,$item)===FALSE) $fail[]='Login layout missing: '.$item;
}
foreach (['.login-page::before','.login-shell','.login-card','background-size: cover'] as $style) {
    if (strpos($css,$style)===FALSE) $fail[]='Full-cover login CSS missing: '.$style;
}
// Exactly one Places sidebar link; the six authorized module tabs remain inside Places.
$sidebar = file_get_contents($root.'/application/view/layout/sidebar_top_nav.php');
$placeController = file_get_contents($root.'/application/controllers/Places.php');
$routes = file_get_contents($root.'/application/config/routes.php');
$placeView = file_get_contents($root.'/application/view/pages/places/shared_index.php');
if (substr_count($sidebar, "site_url('places')") !== 1 ||
    strpos($sidebar, "fa-map-location-dot") === FALSE ||
    strpos($sidebar, "aria-current=\"page\"") === FALSE) {
    $fail[] = 'Places must be a single accessible sidebar link.';
}
foreach (['placesSidebarGroup', 'placesSidebarLinks', 'side-submenu',
    'sidebar-chevron', 'site_url(\'places/\' . $slug)'] as $oldDropdown) {
    if (strpos($sidebar, $oldDropdown) !== FALSE) {
        $fail[] = 'Old Places sidebar dropdown remains: '.$oldDropdown;
    }
}
if (strpos($routes, "'places']['GET'] = 'Places/home'") === FALSE ||
    strpos($placeController, 'public function home()') === FALSE) {
    $fail[] = 'Places landing route is missing.';
}
foreach (['areas','specifics','assets','locations','sequences','categories'] as $module) {
    if (strpos($placeController, "'".$module."'") === FALSE) {
        $fail[] = 'Places landing permission mapping missing: '.$module;
    }
}
foreach (['area', 'specific', 'asset', 'location',
    'sequence', 'softcopy-categories'] as $tab) {
    if (strpos($placeView, "'".$tab."'") === FALSE) {
        $fail[] = 'Places tab missing: '.$tab;
    }
}
// Ensure predefined Location Upsert stays aligned with pk_dts relationships.
$location = file_get_contents($root.'/application/view/pages/places/location/modal_action/upsert.php');
$places = file_get_contents($root.'/application/services/places/place_service.php');
$placeModel = file_get_contents($root.'/application/models/Place_model.php');
$placeJs = file_get_contents($root.'/public/assets/js/app.js');
foreach (['name="name"', 'name="code"', 'name="area_id"',
    'name="specific_id"', 'name="asset_id"', 'name="archive_date"',
    'data-specific-id', 'data-area-id'] as $field) {
    if (strpos($location, $field) === FALSE) $fail[] = 'Location Upsert field missing: '.$field;
}
foreach (['prepare_location', "join('specifics s'", "join('areas a'",
    "'archive_date'"] as $part) {
    if (strpos($places, $part) === FALSE && strpos($placeModel, $part) === FALSE) {
        $fail[] = 'Location hierarchy validation missing: '.$part;
    }
}
if (strpos($placeJs, 'updateLocationHierarchy') === FALSE) {
    $fail[] = 'Location hierarchy filtering script missing.';
}
// Softcopy direct and requested actions MUST share both form fields and effects.
$docView=file_get_contents($root.'/application/view/pages/documents/shared_index.php');
$reqView=file_get_contents($root.'/application/view/pages/request/shared_index.php');
$docCtl=file_get_contents($root.'/application/controllers/Documents.php');
$reqSvc=file_get_contents($root.'/application/services/request/request_service.php');
$directSvc=file_get_contents($root.'/application/services/softcopy/softcopy_direct_service.php');
$routes=file_get_contents($root.'/application/config/routes.php');
foreach ([$docView,$reqView] as $page) {
    if (strpos($page, "pages/request/softcopy_fields")===FALSE) {
        $fail[] = 'Direct and requested Softcopy must use the shared fieldset.';
    }
}
if (strpos($reqSvc, 'Softcopy_operation_service')===FALSE ||
    strpos($directSvc, 'Softcopy_operation_service')===FALSE) {
    $fail[] = 'Direct and requested softcopy must share domain effects.';
}
if (strpos($docCtl, "require_permission('softcopy','direct')")===FALSE ||
    strpos($routes, "'documents/softcopy/direct'")===FALSE) {
    $fail[] = 'Softcopy Direct endpoint must require original softcopy.direct permission.';
}
if (!is_file($root.'/tests/direct_softcopy_smoke.sh')) {
    $fail[] = 'Missing database-backed Softcopy Direct tests.';
}
$dashboard = file_get_contents($root.'/application/view/pages/dashboard/index.php');
$hardcopy = file_get_contents($root.'/application/view/pages/hardcopy_document/modal_action/upsert.php');
$docService = file_get_contents($root.'/application/services/documents/document_service.php');
$docModel = file_get_contents($root.'/application/models/Document_model.php');
$docView = file_get_contents($root.'/application/view/pages/documents/shared_index.php');
foreach (['dashboardGreeting', 'Good morning', 'Good afternoon', 'Good evening',
    'Asia/Manila', 'first_name'] as $text) {
    if (strpos($dashboard, $text)===FALSE) $fail[]='Dashboard greeting missing: '.$text;
}
foreach (['name="holder_name"','readonly','name="holder_id"',
    'is_administrator','data-hardcopy-level="area"',
    'data-hardcopy-level="specific"','data-hardcopy-level="asset"',
    'data-hardcopy-level="location"'] as $text) {
    if (strpos($hardcopy, $text)===FALSE) $fail[]='Hardcopy upsert field missing: '.$text;
}
if (strpos($docView, "pages/hardcopy_document/modal_action/upsert")===FALSE ||
    strpos($docView, 'data-hardcopy-upsert')===FALSE ||
    strpos($docModel, 'function hardcopy_locations(')===FALSE ||
    strpos($placeJs, 'updateHardcopyHierarchy')===FALSE ||
    strpos($docService, 'validate_hardcopy_location')===FALSE ||
    strpos($docService, "'Administrator'")===FALSE) {
    $fail[]='Hardcopy predefined hierarchy or server-side holder protection missing.';
}
if (!is_file($root.'/tests/hardcopy_dashboard_smoke.sh')) {
    $fail[]='Hardcopy/Dashboard integration tests missing.';
}
// Controlled files must be reviewable only by the assigned approver.
$softcopyFields=file_get_contents($root.'/application/view/pages/request/softcopy_fields.php');
$hcFields=file_get_contents($root.'/application/view/pages/hardcopy_document/modal_action/upsert.php');
$requestSvc=file_get_contents($root.'/application/services/request/request_service.php');
$softSvc=file_get_contents($root.'/application/services/softcopy/softcopy_operation_service.php');
$fileModel=file_get_contents($root.'/application/models/File_model.php');
$fileCtl=file_get_contents($root.'/application/controllers/Files.php');
$routeCode=file_get_contents($root.'/application/config/routes.php');
foreach (['controlled_file_id','revision_file_id'] as $key) {
    if (strpos($requestSvc,$key)===FALSE) $fail[]='Missing pending file reference: '.$key;
}
foreach (['stage_creation','stage_revision'] as $stage) {
    if (strpos(file_get_contents($root.'/application/services/files/file_service.php'),$stage)===FALSE)
        $fail[]='Missing upload staging: '.$stage;
}
if (strpos($softcopyFields,'Controlled File')===FALSE ||
    strpos($softSvc,'controlled_file_id')===FALSE ||
    strpos($fileModel,'review_file(')===FALSE ||
    strpos($fileCtl,'function review(')===FALSE ||
    strpos($routeCode,'files/review/')===FALSE) {
    $fail[]='Controlled File create/revise upload and review flow incomplete.';
}
if (strpos($hcFields,'data-hardcopy-retention hidden')===FALSE ||
    strpos($placeJs,'updateHardcopyRetention')===FALSE ||
    strpos($reqView, 'hardcopy_document/modal_action/upsert')===FALSE ||
    strpos($requestSvc, 'validate_hardcopy_proposal')===FALSE) {
    $fail[]='Conditional retention or shared hardcopy request form missing.';
}
// Workflow Editor controls who receives each request at each approval stage.
$wfService=file_get_contents($root.'/application/services/administration/administration_service.php');
$requestSvc=file_get_contents($root.'/application/services/request/request_service.php');
$requestCtl=file_get_contents($root.'/application/controllers/Requests.php');
$wfView=file_get_contents($root.'/application/view/pages/workflow_builder/index.php');
foreach (['move_workflow_step','validate_approval_graph','active_request_type'] as $key) {
    if (strpos($wfService,$key)===FALSE) $fail[]='Workflow routing missing: '.$key;
}
if (strpos($requestSvc,"'superseded'")===FALSE ||
    strpos($requestCtl,'(new Request_service())->decide')===FALSE ||
    strpos($wfView,'Predefined Approval Workflows')===FALSE) {
    $fail[]='Sequential approver route not implemented.';
}
if (!is_file($root.'/tests/workflow_builder_smoke.sh')) {
    $fail[]='Workflow Builder integration test missing.';
}
// No replacement access/assignment schema; explicit two-domain routing.
$accessSvc=file_get_contents($root.'/application/services/documents/document_access_service.php');
$audit=file_get_contents($root.'/application/libraries/Audit_file.php');
$softcopyRevision=file_get_contents($root.'/application/services/softcopy/softcopy_operation_service.php');
foreach (["'softcopy','hardcopy'", "'hardcopy_documents'", "'assignments'"] as $key) {
    if (strpos($accessSvc,$key)===FALSE)
        $fail[]='Missing schema-native cross-domain assignment: '.$key;
}
foreach (['/storage/audit','LOCK_EX','JSON_PRETTY_PRINT'] as $key) {
    if (strpos($audit,$key)===FALSE) $fail[]='Daily filesystem JSON audit missing: '.$key;
}
if (strpos($softcopyRevision,'$effective=')===FALSE ||
    strpos($softcopyRevision,"'date_released'=>\$date")===FALSE)
    $fail[]='Softcopy automatic dates are incomplete.';
if (!is_file($root.'/tests/domain_audit_smoke.sh'))
    $fail[]='Missing domain + audit tests.';
$uiScript=file_get_contents($root.'/public/assets/js/app.js');
if (strpos($uiScript, "name !== 'effective_date'")===FALSE) {
    $fail[]='Softcopy revision effective date must remain optional in the browser.';
}
// Fixed workflow definitions are seeded; admin users customize published steps.
$wfUi=file_get_contents($root.'/application/view/pages/workflow_builder/index.php');
$wfCtl=file_get_contents($root.'/application/controllers/Administration.php');
$routes=file_get_contents($root.'/application/config/routes.php');
$trUi=file_get_contents($root.'/application/view/pages/request/hardcopy_transfer_fields.php');
$trJs=file_get_contents($root.'/public/assets/js/app.js');
$trSvc=file_get_contents($root.'/application/services/request/request_service.php');
if (strpos($wfUi,'New Workflow')!==FALSE ||
    strpos($wfUi,'admin/workflows/save')!==FALSE ||
    strpos($wfCtl,'function save_workflow()')!==FALSE ||
    strpos($routes,"'admin/workflows/save'")!==FALSE)
    $fail[]='Predefined workflows must not be user creatable.';
foreach (['data-transfer-source','data-transfer-origin',
    'destination_<?= $level ?>_id','data-area-id','data-specific-id',
    'holder_name','recipient_id'] as $field) {
    if (strpos($trUi,$field)===FALSE) $fail[]='Transfer form missing: '.$field;
}
if (strpos($trJs,'updateHardcopyTransfer')===FALSE ||
    strpos($trSvc,'validate_transfer_destination')===FALSE)
    $fail[]='Destination auto population and server validation missing.';
// Folder navigation is shared by documents and admin assignments.
$folders=file_get_contents($root.'/application/models/Folder_model.php');
$docModel=file_get_contents($root.'/application/models/Document_model.php');
$docCtl=file_get_contents($root.'/application/controllers/Documents.php');
$adminCtl=file_get_contents($root.'/application/controllers/Administration.php');
$docView=file_get_contents($root.'/application/view/pages/documents/shared_index.php');
$assignmentView=file_get_contents($root.'/application/view/pages/administration/document_assignments.php');
$tableView=file_get_contents($root.'/application/view/reusable_components/reusable_datatable.php');
$ui=file_get_contents($root.'/public/assets/js/app.js');
foreach (['category','area','specific','asset','location','parent_id','scope_documents']
    as $item) if (strpos($folders,$item)===FALSE) $fail[]='Folder hierarchy missing '.$item;
if (strpos($docModel,'options_in_folder')===FALSE ||
    strpos($docCtl,"Folder_model")===FALSE ||
    strpos($adminCtl,"Folder_model")===FALSE) {
    $fail[]='Folder filtering is missing from document / assignment queries.';
}
if (strpos($docView,'reusable_components/folder_browser')===FALSE ||
    strpos($assignmentView,'reusable_components/folder_browser')===FALSE ||
    strpos($assignmentView,'reusable_components/reusable_datatable')===FALSE ||
    strpos($tableView,'data-row-view')===FALSE ||
    strpos($tableView,'class="table-cell-view"')===FALSE ||
    strpos($ui,'showTableCellDetails')===FALSE) {
    $fail[]='Shared folder navigator or cell-click view missing.';
}
if (!is_file($root.'/tests/folder_browser_smoke.sh'))
    $fail[]='Folder navigation tests are missing.';
if ($fail) {fwrite(STDERR,implode(PHP_EOL,$fail).PHP_EOL);exit(1);}
echo "Source-of-truth schema and module contract passed.\n";
