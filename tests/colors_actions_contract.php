<?php
declare(strict_types=1);
define('BASEPATH', __DIR__);

// Shared root palette is authoritative: no module-specific hex palettes.
$root=dirname(__DIR__);
$colors=file_get_contents($root.'/public/assets/css/colors.css');
$dashboard=file_get_contents($root.'/public/assets/css/dashboard.css');
$app=file_get_contents($root.'/public/assets/css/app.css');
$document=file_get_contents($root.'/public/assets/css/document-view.css');
$unified=file_get_contents($root.'/public/assets/css/unified-ux.css');
$header=file_get_contents($root.'/application/view/layout/header.php');
$login=file_get_contents($root.'/application/view/pages/authentication/index.php');

function colors_assert(bool $condition,string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
preg_match_all('/--ui-[a-z0-9-]+:\s*#[0-9a-fA-F]{3,8}\s*;/', $colors, $matches);
colors_assert(count($matches[0]) >= 45,'Root colors file must define the complete centralized palette.');
colors_assert(strpos($colors,'--accent: var(--ui-brand-500)')!==FALSE,
    'Legacy accent aliases must continue using central brand colors.');
foreach (['--navy: var(--ui-navy)','--border: var(--ui-line)',
          '--surface: var(--ui-white)','--bg: var(--ui-canvas)'] as $alias)
    colors_assert(strpos($colors,$alias)!==FALSE,'Shared root palette is missing alias: '.$alias);
foreach ([
 'app.css'=>$app,'dashboard.css'=>$dashboard,'document-view.css'=>$document,
 'unified-ux.css'=>$unified
] as $file=>$source) {
    colors_assert(preg_match('/#[0-9a-fA-F]{3,8}\b/', $source)!==1,
        $file.' must not hardcode color hex literals.');
    colors_assert(strpos($source,'var(--ui-')!==FALSE,
        $file.' must reference centralized colors.');
}
colors_assert(strpos($header,'assets/css/colors.css')!==FALSE &&
    strpos($header,'assets/css/app.css')!==FALSE &&
    strpos($header,'assets/css/colors.css')<strpos($header,'assets/css/app.css'),
    'Root palette must load before every system stylesheet.');
colors_assert(strpos($login,'assets/css/colors.css')!==FALSE &&
    strpos($login,'assets/css/colors.css')<strpos($login,'assets/css/app.css'),
    'Sign-in must load the same centralized palette.');
colors_assert(strpos($unified,'.table-actions .btn-icon.record-action')!==FALSE,
    'Labeled actions must size naturally in table/grid/folder layouts.');

function site_url(string $path): string { return '/'.$path; }
function html_escape($value): string {
    return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}
$types=['view','edit','steps','grants','history','upload','review','download','action'];
$buttons=[];
foreach ($types as $type) {
    $buttons[]=['type'=>$type,'url'=>'items/1','label'=>strtoupper($type),
        'files'=>[], 'description'=>'Safe action','decision'=>''];
}
$dt_row=['id'=>1,'buttons'=>$buttons,'display'=>['Name'=>'Test'],
    'cells'=>['title'=>'Test','name'=>'Test'],'record'=>['id'=>1]];
ob_start();
require $root.'/application/view/reusable_components/record_actions.php';
$html=(string)ob_get_clean();
foreach ($types as $type) {
    colors_assert(strpos($html,'>'.strtoupper($type).'</span>')!==FALSE,
        'Every action must have readable text: '.$type);
}
colors_assert(substr_count($html,'class="fa-') >= 5,
    'Action rows must preserve icon alongside accessible text.');
colors_assert(strpos($html,'class="btn-icon record-action')!==FALSE,
    'All actions must use one reusable labeled button component.');
echo "Central color palette and labeled action contracts passed.\n";
