<?php
declare(strict_types=1);
define('BASEPATH',__DIR__);
define('APPPATH',dirname(__DIR__).'/application/');
require_once APPPATH.'services/places/hardcopy_place_policy.php';

// Static contract comparison ignores formatting while checking required code paths.
function planCodePosition(string $source,string $part) {
    return strpos(preg_replace('/\\s+/', '', $source),preg_replace('/\\s+/', '', $part));
}
function planCheck(bool $pass,string $message): void {
    if (!$pass) throw new RuntimeException($message);
}
function failsLocation(callable $fn,string $expected): void {
    try { $fn(); } catch (DomainException $e) {
        planCheck($e->getMessage()===$expected,'Expected '.$expected.', got '.$e->getMessage());
        return;
    }
    throw new RuntimeException('Expected a required Places validation failure.');
}

// Asset is intentionally NOT an input to this validation policy.
Hardcopy_place_policy::require_location_path(1,2,3);
Hardcopy_place_policy::require_location_parents(1,2);
failsLocation(static function(){Hardcopy_place_policy::require_location_path(0,2,3);},'Select an Area.');
failsLocation(static function(){Hardcopy_place_policy::require_location_path(1,0,3);},'Select a Specific.');
failsLocation(static function(){Hardcopy_place_policy::require_location_path(1,2,0);},'Select a Location.');
failsLocation(static function(){Hardcopy_place_policy::require_location_parents(0,2);},'Select an Area.');
failsLocation(static function(){Hardcopy_place_policy::require_location_parents(1,0);},'Select a Specific.');

$root=dirname(__DIR__);
$hardcopy=file_get_contents($root.'/application/view/pages/hardcopy_document/modal_action/upsert.php');
$location=file_get_contents($root.'/application/view/pages/places/location/modal_action/upsert.php');
$transfer=file_get_contents($root.'/application/view/pages/request/hardcopy_transfer_fields.php');
$service=file_get_contents($root.'/application/services/documents/document_service.php');
$places=file_get_contents($root.'/application/services/places/place_service.php');
$request=file_get_contents($root.'/application/services/request/request_service.php');
$js=file_get_contents($root.'/public/assets/js/app.js');
$tree=file_get_contents($root.'/application/models/Folder_model.php');
$motionCss=file_get_contents($root.'/public/assets/css/motion.css');
$motionJs=file_get_contents($root.'/public/assets/js/motion.js');
$header=file_get_contents($root.'/application/view/layout/header.php');
$footer=file_get_contents($root.'/application/view/layout/footer.php');
$login=file_get_contents($root.'/application/view/pages/authentication/index.php');

foreach ([
 'Hardcopy Area'=>[$hardcopy,'area_id'],
 'Hardcopy Specific'=>[$hardcopy,'specific_id'],
 'Hardcopy Location'=>[$hardcopy,'location_id'],
 'Location Area'=>[$location,'area_id'],
 'Location Specific'=>[$location,'specific_id']
] as $label=>$pair) {
    planCheck(preg_match('/<select[^>]*name="'.preg_quote($pair[1],'/').'"[^>]*>/s',$pair[0],$m)===1 &&
        planCodePosition($m[0],'required')!==FALSE, $label.' should be required in the browser.');
}
foreach ([['Hardcopy',$hardcopy],['Location',$location]] as $pair) {
    planCheck(preg_match('/<select[^>]*name="asset_id"[^>]*>/s',$pair[1],$m)===1 &&
        planCodePosition($m[0],'required')===FALSE,$pair[0].' must NOT require an Asset.');
}
planCheck(planCodePosition($transfer,"\$level==='asset'?'':'required'")!==FALSE,
    'Transfer form must require Area/Specific/Location, not Asset.');
planCheck(planCodePosition($service,'Hardcopy_place_policy::require_location_path')!==FALSE &&
    planCodePosition($places,'Hardcopy_place_policy::require_location_parents')!==FALSE &&
    planCodePosition($request,'Hardcopy_place_policy::require_location_path')!==FALSE,
    'Hardcopy, Location and Transfer server validations must share required Places policy.');
planCheck(planCodePosition($service,'$data[\'asset_id\']=$asset?:NULL;')!==FALSE &&
    planCodePosition($places,'$data[\'asset_id\']=$assetId?:NULL;')!==FALSE,
    'The optional Asset must be stored as SQL NULL when unassigned.');
planCheck(planCodePosition($service,'The selected Location is already assigned to a document.')===FALSE &&
    planCodePosition($request,'The destination is already occupied by another hardcopy.')===FALSE,
    'Hardcopy Locations now support multiple separate documents without an occupancy rule.');
planCheck(planCodePosition($tree,"'asset'=>['table'=>'assets'")!==FALSE &&
    planCodePosition($tree,"\$parent='asset'")!==FALSE,
    'Document Repository must keep Asset as an optional folder level.');
planCheck(planCodePosition($js,'data-hardcopy-level')!==FALSE &&
    preg_match('/changed\\s*===\\s*[\"\\\']asset[\"\\\']\\s*&&\\s*!asset\\.value\\s*&&\\s*location\\.value/', $js) === 1,
    'Dependent dropdowns must preserve a deliberately cleared Asset.');
planCheck(planCodePosition($motionCss,'@keyframes ui-shimmer')!==FALSE &&
    planCodePosition($motionCss,'.motion-skeleton')!==FALSE &&
    planCodePosition($motionCss,'prefers-reduced-motion: reduce')!==FALSE,
    'Skeleton loading and reduced-motion CSS must be available.');
planCheck(planCodePosition($motionJs,'aria-busy')!==FALSE &&
    planCodePosition($motionJs,'defaultPrevented')!==FALSE &&
    planCodePosition($motionJs,'pageshow')!==FALSE,
    'Skeleton must be scoped to navigation and recovered on canceled or restored pages.');
planCheck(planCodePosition($header,'assets/css/motion.css')!==FALSE &&
    planCodePosition($footer,'assets/js/motion.js')!==FALSE &&
    planCodePosition($login,'assets/js/motion.js')!==FALSE,
    'Animated loading must be available on authenticated and login pages.');
planCheck(preg_match('/#[0-9a-fA-F]{3,8}\b/',$motionCss)!==1,
    'New motion CSS must use the centralized colors palette, not hardcoded colors.');
echo "Plan.md hierarchy, required Places, optional Asset and shimmer/motion contracts passed.\n";
