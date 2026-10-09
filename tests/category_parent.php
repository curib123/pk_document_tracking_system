<?php
$path=dirname(__DIR__).'/application/services/places/category_parent_policy.php';
if (!is_file($path)) {fwrite(STDERR,"Category parent validation missing.\n");exit(1);}
require $path;
$rows=[['id'=>1,'parent_id'=>NULL,'active'=>1],['id'=>2,'parent_id'=>1,'active'=>1],
 ['id'=>3,'parent_id'=>2,'active'=>1],['id'=>4,'parent_id'=>NULL,'active'=>0]];
Category_parent_policy::validate($rows,3,1);
Category_parent_policy::validate($rows,0,1);
foreach ([[1,3],[2,2],[2,999],[2,4]] as $invalid) {
    try {Category_parent_policy::validate($rows,...$invalid);throw new RuntimeException('Invalid category parent accepted.');}
    catch (DomainException $e) {}
}
$deep=[];for($i=1;$i<=32;$i++)$deep[]=['id'=>$i,'parent_id'=>$i===1?NULL:$i-1,'active'=>1];
try {Category_parent_policy::validate($deep,0,32);throw new RuntimeException('Excessive hierarchy depth accepted.');}
catch (DomainException $e) {}
echo "Category cycle, self-parent, missing/inactive parent and depth rules passed.\n";
