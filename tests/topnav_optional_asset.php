<?php
declare(strict_types=1);

/** No database or browser needed: safeguard sticky navigation and optional
 * Asset submission across the shared hardcopy/location/transfer forms.
 */
function expect_feature(bool $ok,string $message):void {
    if (!$ok) throw new RuntimeException($message);
}
$root=dirname(__DIR__);
$nav=file_get_contents($root.'/application/view/layout/sidebar_top_nav.php');
$styles=file_get_contents($root.'/public/assets/css/unified-ux.css');
$hardcopy=file_get_contents($root.'/application/view/pages/hardcopy_document/modal_action/upsert.php');
$location=file_get_contents($root.'/application/view/pages/places/location/modal_action/upsert.php');
$transfer=file_get_contents($root.'/application/view/pages/request/hardcopy_transfer_fields.php');
$app=file_get_contents($root.'/public/assets/js/app.js');
$hardcopyService=file_get_contents($root.'/application/services/documents/document_service.php');
$placeService=file_get_contents($root.'/application/services/places/place_service.php');

expect_feature(strpos($nav,'<header class="topbar">')!==FALSE,
    'Current module and page must remain visible in top bar.');
expect_feature(strpos($nav,'class="topbar-module"')!==FALSE &&
    strpos($nav,'class="topbar-label"')!==FALSE,
    'Both top-navigation context labels must be preserved.');
expect_feature(strpos($styles,'.app-main > .topbar {')!==FALSE &&
    strpos($styles,'position: sticky;')!==FALSE &&
    strpos($styles,'top: 0;')!==FALSE,
    'Top nav must remain visible on scroll without absolute positioning.');
expect_feature(strpos($styles,'.app-main > .topbar .topbar-label {')!==FALSE,
    'Mobile view must preserve the page subtitle.');
expect_feature(strpos($styles,'.app-main > .topbar .topbar-context')!==FALSE &&
    strpos($styles,'min-width: 0;')!==FALSE,
    'Long page names must not overflow the top navigation.');

foreach ([
    'Hardcopy document'=>$hardcopy,
    'Location master data'=>$location
] as $label=>$markup) {
    expect_feature(preg_match('/<select[^>]*name="asset_id"[^>]*>/s',$markup,$matches)===1,
        $label.' must contain Asset choice.');
    expect_feature(strpos($matches[0],'required')===FALSE,
        $label.' Asset choice must NOT be required.');
    expect_feature(strpos($markup,'<option value="">No asset / not assigned</option>')!==FALSE,
        $label.' Asset must include a selectable empty option.');
}
expect_feature(strpos($hardcopy,'name="title" required')!==FALSE,
    'The hardcopy title must remain mandatory.');
expect_feature(strpos($location,'name="code" type="text"')!==FALSE &&
    strpos($location,'name="name" type="text"')!==FALSE,
    'Location Name and Code must remain editable and validated.');
expect_feature(strpos($transfer,"\$level==='asset'?'No asset / not assigned'")!==FALSE,
    'Transfer destination must accept an empty Asset choice.');
$normalizedHardcopyService = preg_replace('/\\s+/', '', $hardcopyService);
expect_feature(strpos($normalizedHardcopyService,"'asset_id','location_id'")!==FALSE &&
    strpos($normalizedHardcopyService,'$data[$field]=!empty($payload[$field])?(int)$payload[$field]:NULL;')!==FALSE,
    'Hardcopy service must normalize empty Asset to SQL NULL.');
$normalizedPlaceService = preg_replace('/\\s+/', '', $placeService);
expect_feature(strpos($normalizedPlaceService,"\$data['asset_id']=\$assetId?:NULL;")!==FALSE,
    'Location service must normalize empty Asset to SQL NULL.');
expect_feature(strpos(preg_replace('/\\s+/', '', $app),"changed==='asset'&&!asset.value&&location.value")!==FALSE,
    'Cleared Asset must not be silently repopulated by a linked Location.');
expect_feature(strpos(preg_replace('/\\s+/', '', $app),'field.required=showDetails')!==FALSE,
    'Form visibility must retain server-matched required/optional input state.');

echo "Sticky top navigation and optional Asset UI/service contracts passed.\n";
