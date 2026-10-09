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
// Places navigation: one accessible expandable group and six distinct icon assets.
$sidebar = file_get_contents($root.'/application/view/layout/sidebar_top_nav.php');
foreach (['<details', '<summary', 'placesSidebarGroup', 'placesSidebarLinks',
    "strpos($path, 'places/') === 0", '$visible($place[\'module\'])',
    'aria-current="page"'] as $token) {
    if (strpos($sidebar, $token) === FALSE) {
        $fail[] = 'Places dropdown markup missing: ' . $token;
    }
}
preg_match_all("/'icon'\s*=>\s*'(fa-[a-z0-9-]+)'/", $sidebar, $foundPlaceIcons);
if (count($foundPlaceIcons[1]) !== 6 ||
    count(array_unique($foundPlaceIcons[1])) !== 6) {
    $fail[] = 'Every Places child must have a unique Font Awesome icon.';
}
foreach (['area'=>'areas', 'specific'=>'specifics', 'asset'=>'assets',
    'location'=>'locations', 'sequence'=>'sequences',
    'softcopy-categories'=>'categories'] as $slug=>$module) {
    if (strpos($sidebar, "'$slug' =>") === FALSE ||
        strpos($sidebar, "'module' => '$module'") === FALSE) {
        $fail[] = "Places route/permission mapping missing: $slug";
    }
}
$sidebarCss = file_get_contents($root.'/public/assets/css/app.css');
foreach (['.side-link-parent', '.side-submenu', '.sidebar-chevron',
    '.sidebar-group[open]'] as $selector) {
    if (strpos($sidebarCss, $selector) === FALSE) {
        $fail[] = 'Places dropdown style missing: '.$selector;
    }
}
if ($fail) {fwrite(STDERR,implode(PHP_EOL,$fail).PHP_EOL);exit(1);}
echo "Source-of-truth schema and module contract passed.\n";
