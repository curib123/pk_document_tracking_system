<?php
$root=dirname(__DIR__);$fails=0;$count=0;
foreach(['application','public','tools','tests'] as $folder) {
 if (!is_dir($root.'/'.$folder)) continue;
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$folder));
 foreach($it as $file) {
  if (!$file->isFile() || $file->getExtension()!=='php') continue;
  $output=[];$status=0;
  exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getPathname()).' 2>&1',$output,$status);
  $count++;if($status){$fails++;fwrite(STDERR,implode(PHP_EOL,$output).PHP_EOL);}
 }
}
if ($fails) exit(1);
echo "PHP lint passed ($count files).\n";
