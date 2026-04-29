<?
/*
+--------------------------------------------------------------------------
|	Upload File for import Product Data
|   ========================================
|	Exporting excel data
+--------------------------------------------------------------------------
*/
include("../../includes/global.inc.php");
require_once("../../classes/db.inc.php");
$db = new db();
include_once("../../includes/functions.inc.php");
include("../includes/config.inc.php");
include_once("../includes/functions.inc.php");
include_once("../../lang.inc.php");
include("../includes/auth.inc.php");


if($_SERVER['REQUEST_METHOD'] =='GET' && $_GET['category']!="")
{
	$cat_id =$_REQUEST['category'];
	$cat_name = getcatname($db,$cat_id);
	$parent_id = getparentid($db,$cat_id);
	if($parent_id == 0 and ((stristr($cat_name,"divan") and stristr($cat_name,"bed")) or stristr($cat_name,"matt"))) {

		$query = "SELECT * FROM ".$glob['dbprefix']."category WHERE cat_name like '%matt%' and cat_father_id =0" ;
		$results = $db->select($query);
		$matcatid=$results[0]['cat_id'];

		$query = "SELECT * FROM ".$glob['dbprefix']."category WHERE cat_name like '%div%' and cat_name not like '%base%'";
		$results = $db->select($query);
		$divcatid=$results[0]['cat_id'];
		//$query = "select distinct  (select optionvalue from tblbed_option where id=supplier) as supplier from ".$glob['dbprefix']."inventory where (cat_id=$matcatid or cat_id=$divcatid) and supplier <> 'N/A' order by supplier";
		$query = "select distinct (select optionvalue from tblbed_option where id = supplier) as
 supplier from tblbed_inventory where cat_id=$matcatid or cat_id=$divcatid AND supplier in (select id from tblbed_option where type='supplier' and optionvalue <> 'N/A') order by supplier";		
	}else{

		//$query = "select distinct  (select optionvalue from tblbed_option where id=supplier) as supplier from ".$glob['dbprefix']."inventory where cat_id=$cat_id  and supplier <> 'N/A' order by supplier";
		$query = "select distinct (select optionvalue from tblbed_option where id = supplier) as
 supplier from tblbed_inventory where cat_id = $cat_id AND supplier in (select id from tblbed_option where type='supplier' and optionvalue <> 'N/A') order by supplier";
	}
	
	$results=$db->select($query);
}
/*	print_r($categoryArray);
die;*/
//////////////code for divans matteress and Divans & Matteress make a single category///////////

include("../includes/header.inc.php");
?>
<table width="100%"  border="0" cellspacing="0" cellpadding="0" class="tdContent">
  <tr>
    <td nowrap='nowrap'><p class="pageTitle">Product Export </p></td>

  </tr>
</table>
<p class="copyText"></p>
 <?php if(isset($msg)){ echo stripslashes($msg); } ?>
<? //if($_SERVER['REQUEST_METHOD'] !== 'POST'){ ?>
<p class="copyText">You select category to Export. </p>
<form action="" method="GET" name="form1" id="form1">
  <table width="100%" border="0" align="center" class="mainTable">
    <tr>
      <td colspan="2" class="tdTitle" >Step1&nbsp;</td>
    </tr>
    <tr>
      <td width="40%" class="tdText" align="right"><strong>Category :</strong></td>
      <td width="60%">  <select name="category" id="category" class="textbox">
	<?php
	$categoryArray = populateCategory($db, $glob);
	 for ($i=0; $i<count($categoryArray); $i++){ ?>
	<option value="<?php echo $categoryArray[$i]['cat_id']; ?>" <?php if(isset($_GET['category']) && $categoryArray[$i]['cat_id']==$_REQUEST['category']) echo "selected='selected'"; ?>><?php echo getCatDir($categoryArray[$i]['cat_name'],$categoryArray[$i]['cat_father_id'], $categoryArray[$i]['cat_id']); ?></option>
	<?php } ?>
	</select>
</td>
    </tr>
    <tr>
      <td>&nbsp;</td>
      <td><input type="submit" name="Submit" value="Submit" class="submit" /></td>
    </tr>
  </table>
</form>


<p>
  <? if(isset($_GET) && $_GET['category']!="" && $_SERVER['REQUEST_METHOD'] !== 'POST') { ?>
</p>
<p class="copyText">You can export products specific to a supplier using the below form. </p>
<!--<form action="export_excel.php" method="post" name="form1" id="form1" onSubmit="progress(this)">-->
<form action="export_excel.php" method="post" name="form1" id="form1">
  <table width="100%" border="0" align="center" class="mainTable" cellpadding="4">
    <tr>
      <td colspan="2" class="tdTitle">Step2</td>
    </tr>
    <tr>
      <td width="40%" class="tdText" align="right"><strong> Select a Supplier :</strong></td>
      <td width="60%"><select name="supplier" id="supplier">
	  <option value=''>All</option>
	  <?php for($i=0; $i<count($results); $i++) { ?>
	  <option value="<?php echo $results[$i]['supplier']?>" ><?php echo $results[$i]['supplier']?></option>
	  <?php } ?>
	  </select>
	  </td>
    </tr>
    <tr>
      <td>&nbsp;</td>
      <td>
	  	<input type="submit" name="Submit" value="Submit" class="submit" id="sub_form1" style="" />
		<input type="image" src="load.gif" style="display:none" border="0" id="img_form1" />
	  </td>
    </tr>
  </table>
  <input type="hidden" name="cat_id" value="<?php echo $_GET['category']; ?>" />
</form>
<? }
?>