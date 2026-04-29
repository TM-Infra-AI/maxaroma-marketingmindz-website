<?php
require_once("functions.php");
//echo $file;

// note  do not  change number name position, if you want to change then make required changes accordingly in "IF" conidtions.
$fnames = array("home","category","subcategory","productlist","productdetail","contact_us");

if($file == $fnames[0])
{
	$schema = getSchema('home');
}
else if($file == $fnames[1])
{
	$schema = getSchema('category_list');
}
else if($file == $fnames[2])
{
	$schema = getSchema('sub_category');
}
else if($file == $fnames[3])
{
	$schema = getSchema('productlist');
}
else if($file == $fnames[4])
{
	$schema = getSchema('productdetail');
}
else if($file == $fnames[5])
{
	$schema = getSchema('contact_us');
}

//echo $schema;exit;

$smarty->assign("schema", $schema);
?>
