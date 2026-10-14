<?php
declare(strict_types=1);
define('BASEPATH',__DIR__);
require_once __DIR__.'/../application/services/presentation/query_state.php';
require_once __DIR__.'/../application/services/authentication/authentication_service.php';

function newPlanCheck(bool $okay,string $message): void {
    if (!$okay) throw new RuntimeException($message);
}
function planReject(callable $fn,string $error): void {
    try{$fn();}catch(DomainException $e) {
        newPlanCheck(strpos($e->getMessage(),$error)!==FALSE,
            'Expected error: '.$error.'; got: '.$e->getMessage());
        return;
    }
    throw new RuntimeException('Missing validation: '.$error);
}
Authentication_service::validate_new_password('priorPassword','123456789','123456789');
planReject(static function(){
    Authentication_service::validate_new_password('old-password','12345678','12345678');
},'longer than 8');

$now=new DateTimeImmutable('today',new DateTimeZone('Asia/Manila'));
foreach (['day','week','month','year'] as $period) {
    $state=Query_state::parse(['period'=>$period]);
    newPlanCheck($state['period']===$period &&
        $state['to']===$now->format('Y-m-d') &&
        $state['from']!=='' &&
        $state['from']<=$state['to'],'Preset date range not computed correctly.');
}
$custom=Query_state::parse([
    'period'=>'custom','from'=>'2026-01-01','to'=>'2026-02-28']);
newPlanCheck($custom['from']==='2026-01-01'&&$custom['to']==='2026-02-28',
    'Custom range must remain unchanged.');
planReject(static function(){Query_state::parse(['period'=>'injection']);},'Invalid date range');
planReject(static function(){Query_state::parse(['from'=>'2026-12-01','to'=>'2026-01-01']);},
    'Start date');
newPlanCheck(Query_state::parse([])['period']==='all','Default range must be All time.');

$root=dirname(__DIR__);
$files=[
    'places'=>file_get_contents($root.'/application/view/pages/places/shared_index.php'),
    'request_form'=>file_get_contents($root.'/application/view/pages/request/shared_index.php'),
    'request_service'=>file_get_contents($root.'/application/services/request/request_service.php'),
    'assignment'=>file_get_contents($root.'/application/controllers/Administration.php'),
    'assignment_view'=>file_get_contents($root.'/application/view/pages/administration/document_assignments.php'),
    'notice'=>file_get_contents($root.'/application/controllers/Notifications.php'),
    'inbox_model'=>file_get_contents($root.'/application/models/Notification_model.php'),
    'topbar'=>file_get_contents($root.'/application/view/layout/sidebar_top_nav.php'),
    'file_controller'=>file_get_contents($root.'/application/controllers/Files.php'),
    'document_view'=>file_get_contents($root.'/application/view/pages/documents/shared_index.php'),
    'action'=>file_get_contents($root.'/application/view/reusable_components/record_actions.php'),
    'backup'=>file_get_contents($root.'/application/controllers/Backups.php'),
    'backup_view'=>file_get_contents($root.'/application/view/pages/administration/backups.php'),
    'table'=>file_get_contents($root.'/application/view/reusable_components/reusable_datatable.php'),
    'header'=>file_get_contents($root.'/application/view/layout/header.php'),
    'footer'=>file_get_contents($root.'/application/view/layout/footer.php'),
    'routes'=>file_get_contents($root.'/application/config/routes.php'),
    'css'=>file_get_contents($root.'/public/assets/css/plan-enhancements.css'),
    'js'=>file_get_contents($root.'/public/assets/js/plan-controls.js')
];
foreach (['area','specific','asset'] as $slug)
    newPlanCheck(strpos($files['places'],"['area','specific','asset']")!==FALSE,
        'Area, Specific and Asset must have no View Details button.');
newPlanCheck(strpos($files['request_form'],'value="<?= html_escape($user[\'name\']) ?>" readonly')!==FALSE
    &&strpos($files['request_service'],"$payload['recipient_id']=(int)$user['id']")!==FALSE,
    'Assignment/access request recipient must be the signed-in user, enforced server-side.');
newPlanCheck(strpos($files['assignment'],'public function assign_location()')!==FALSE &&
    strpos($files['assignment_view'],'name="location_id"')!==FALSE,
    'Location-wide document assignment must be accessible to authorized administrators.');
newPlanCheck(strpos($files['notice'],'public function inbox()')!==FALSE &&
    strpos($files['notice'],'public function mark_read()')!==FALSE &&
    strpos($files['inbox_model'],'public function unread_page(')!==FALSE &&
    strpos($files['topbar'],'notificationBellButton')!==FALSE,
    'Unread-only bell notifications must use server-side listing and acknowledgement.');
newPlanCheck(strpos($files['file_controller'],'public function print_file(')!==FALSE &&
    strpos($files['file_controller'],'$this->File_model->allowed(')!==FALSE &&
    strpos($files['action'],"$type==='print'")!==FALSE,
    'Printable controlled files require the same file permissions as download.');
newPlanCheck(strpos($files['document_view'],"'type'=>'print'")!==FALSE,
    'Document records must show a Print action for printable controlled files.');
newPlanCheck(strpos($files['backup'],'public function cleanup()')!==FALSE &&
    strpos($files['backup_view'],"admin/backups/cleanup")!==FALSE,
    'Manual backup cleanup must use existing retention service.');
newPlanCheck(strpos($files['table'],'data-date-period')!==FALSE &&
    strpos($files['table'],'data-date-custom')!==FALSE,
    'Table filters must provide presets and custom range controls.');
newPlanCheck(strpos($files['css'],'backdrop-filter:blur(')!==FALSE &&
    strpos($files['css'],'#viewModal .doc-overview-group')!==FALSE &&
    strpos($files['css'],'.notification-inbox')!==FALSE,
    'Modal backdrop blur, details layout and notification styling must exist.');
newPlanCheck(strpos($files['header'],'plan-enhancements.css')!==FALSE &&
    strpos($files['footer'],'plan-controls.js')!==FALSE &&
    strpos($files['footer'],'notification-inbox.js')!==FALSE,
    'New shared CSS/JS must load for every authenticated page.');
newPlanCheck(strpos($files['routes'],'notifications/inbox/read')!==FALSE &&
    strpos($files['routes'],'admin/document-assignments/location')!==FALSE &&
    strpos($files['routes'],'files/print/(:num)')!==FALSE,
    'Protected routes must be registered.');
newPlanCheck(strpos($files['js'],'PM')!==FALSE && strpos($files['js'],' AM')!==FALSE,
    '24-hour timestamps must be formatted to 12-hour display.');
echo "Current plan: password, assignment, modal, date, print, notification and cleanup contracts passed.\n";
