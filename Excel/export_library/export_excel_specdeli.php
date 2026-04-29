<?php

include_once "Writer.php";
include("../../includes/global.inc.php");
require_once("../../classes/db.inc.php");
$db = new db();
include_once("../includes/functions.inc.php");
include_once("../../includes/functions.inc.php");
include_once("../includes/config.inc.php");

#:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::\\
@ini_set('max_execution_time',1200);
#:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::\\
$cat_id= $_POST['cat_id'];
$cat_name = getcatname($db,$cat_id);
$parent_id = getparentid($db,$cat_id);
if(((stristr($cat_name,"divan") && (stristr($cat_name,"bed"))  || stristr($cat_name,"mattr") ) && $parent_id==0))
{
	$filename = "Divans & Mattresses"."-export-".date("d-m-Y h-ia").".xls";
}else{
	$filename = $cat_name."-export-".date("d-m-Y h-ia").".xls";
}
if(isset($_POST['supplier']) && $_POST['supplier']!="" ){
	$supplier = $_POST['supplier'];
}

#:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
if($parent_id ==0)
{
	if(stristr($cat_name,"fold")) //Folding Beds
	{
		$query = "SELECT pcode,product_status,specialdelivery,
		( SELECT optionvalue FROM tblbed_option WHERE id = supplier ) AS supplier,
		 collection, model,
		( SELECT optionvalue FROM tblbed_option WHERE id = size	) AS size,
		( SELECT optionvalue FROM tblbed_option	WHERE id = baseType	) AS baseType,
		( SELECT optionvalue FROM tblbed_option WHERE id = mattressType	) AS mattressType,
		closedDimention,openDimension,
		( SELECT optionvalue FROM tblbed_option WHERE id = framecolor and supplier_id= supplier	) AS framecolor,
( SELECT optionvalue FROM tblbed_option WHERE id = mattressColor and supplier_id= supplier	) AS mattressColor,
		headboard, legType,costOfGood,costOfBoxes,cost_of_packing,creditCardCharge,costWithoutFreight,FreightType,weight,to20Kilos,additionalKgs,ratePKilo,cpdTohouse,totalDeliveryCost,totalCost,special_case_cost,vat,retailIncVat,marginInPound,marginPercent,supEmail,autoOrder, numberOfLots,leadTime,pronto,tibs, desCollection,desModel,no_of_items,".$glob['dbprefix']."img_idx.imgpath1 as photo1,".$glob['dbprefix']."img_idx.imgpath2 as photo2,".$glob['dbprefix']."img_idx.imgpath3 as photo3  from ".$glob['dbprefix']."inventory left join ".$glob['dbprefix']."img_idx on ".$glob['dbprefix']."img_idx.product_id=".$glob['dbprefix']."inventory.product_id where cat_id=".$cat_id;

		$Heading =array('Product Code','Product Status','Supplier','Collection','Model','Size','Base Type','Mattress Type','Dimensions when closed','Dimensions when open','Frame Colour','Mattress Colour','With Headboard','Leg Type','Cost of Goods','Cost of Boxes','Cost of Packing',' Credit Card Charge','Cost without Freight','Freight Type','Weight','£ to 20 Kilos','Additional kgs','Rate per Kilo','CPD to Private House','Special Delivery',' Total Delivery Cost','Total Cost','Special Case Cost','VAT','retail inc vat','margin in £££s','margin %','suppliers e mail','Auto Order','Number Of Lots','Lead Time','Pronto','Tibs','Description of Collection','Description Of Model','No. of Items', 'Photo 1','Photo 2','Photo3');
	}else if(stristr($cat_name,"frame")) //Bed Frames
	{
		$query = "select pcode,product_status,specialdelivery,
				( SELECT optionvalue FROM tblbed_option WHERE id = supplier ) AS supplier,
				collection, model,
				( SELECT optionvalue FROM tblbed_option WHERE id = size ) AS size,
				( SELECT optionvalue FROM tblbed_option WHERE id = finish ) AS finish,
				( SELECT optionvalue FROM tblbed_option WHERE id = colour ) AS colour,
				( SELECT optionvalue FROM tblbed_option WHERE id = colour2 ) AS colour2,
				footEndOption, baseHeightadjOption,
				( SELECT optionvalue FROM tblbed_option WHERE id = withStorage ) AS withStorage,
				overallDimension, headboard, footboard,
				( SELECT optionvalue FROM tblbed_option WHERE id = baseType ) AS baseType,
		 surround,cover,costOfGood,costOfBoxes,cost_of_packing, creditCardCharge, costWithoutFreight, FreightType, weight,to20Kilos,additionalKgs,ratePKilo,cpdTohouse,totalDeliveryCost, totalCost, special_case_cost, vat, retailIncVat, marginInPound, marginPercent,supEmail,autoOrder, numberOfLots,leadTime,pronto,tibs, desCollection,desModel,no_of_items ,".$glob['dbprefix']."img_idx.imgpath1 as photo1,".$glob['dbprefix']."img_idx.imgpath2 as photo2,".$glob['dbprefix']."img_idx.imgpath3 as photo3  from ".$glob['dbprefix']."inventory left join ".$glob['dbprefix']."img_idx on ".$glob['dbprefix']."img_idx.product_id=".$glob['dbprefix']."inventory.product_id where cat_id=".$cat_id;

		$Heading =array('Product Code','Product Status','Supplier','Collection','Model','Size','Finish','Colour1','Colour2','Foot End Option','Base Height Adjustment Option','withStorage','Overall Dimension','Headboard Height','Footboard Height','Base Type','Available as Surround','Cover','Cost of Good','Cost of Boxes','Cost of Packing',' Credit Card Charge','Cost without Freight','Freight Type','Weight','£ to 20 Kilos','Additional kgs','Rate per Kilo','CPD to Private House','Special Delivery',' Total Delivery Cost','Total Cost','Special Case Cost','VAT','Retail Inc. VAT','Margin in £££s','Margin %','Suppliers Email','Auto Order','Number Of Lots','Lead Time','Pronto','Tibs','Description of Collection','Description Of Model','No. of Items', 'Photo 1','Photo 2','Photo3');
	}
	else if(stristr($cat_name,"head")) //HeadBoards
	{
		$query = "select pcode,product_status,cost_of_packing,special_case_cost,specialdelivery,
		( SELECT optionvalue FROM tblbed_option WHERE id = supplier ) AS supplier,
		collection, model,
		( SELECT optionvalue FROM tblbed_option WHERE id = size ) AS size,
		( SELECT optionvalue FROM tblbed_option WHERE id = finish ) AS finish,
		 ( SELECT optionvalue FROM tblbed_option WHERE id = colour ) AS colour,
		 ( SELECT optionvalue FROM tblbed_option WHERE id = colour2 ) AS colour2,
		  overallDimension,floorStanding,
		  ( SELECT optionvalue FROM tblbed_option WHERE id = cover ) AS cover,
		  costOfGood,costOfBoxes, creditCardCharge, costWithoutFreight, FreightType, weight,to20Kilos,additionalKgs,ratePKilo,cpdTohouse,totalDeliveryCost, totalCost, vat, retailIncVat, marginInPound, marginPercent,supEmail,autoOrder, numberOfLots,leadTime,pronto,tibs, desCollection,desModel,no_of_items ,".$glob['dbprefix']."img_idx.imgpath1 as photo1,".$glob['dbprefix']."img_idx.imgpath2 as photo2,".$glob['dbprefix']."img_idx.imgpath3 as photo3  from ".$glob['dbprefix']."inventory left join ".$glob['dbprefix']."img_idx on ".$glob['dbprefix']."img_idx.product_id=".$glob['dbprefix']."inventory.product_id where cat_id=".$cat_id;

		$Heading=array('Product Code','Product Status','Supplier',' Collection',' Model',' Size',' Finish','Colour1','Colour2','Overall Dimensions','Floor Standing',' Cover Grade','Cost of Goods','Cost of Boxes','cost_of_packing','Special Case Cost','Credit Card Charge','Cost Without Freight','Freight Type','Weight','£ to 20 kilos',' Additional Kgs','Rate per Kilo','cpd to Private House','Special Delivery','Total Delivery Cost','Total Cost','VAT','Retail INC VAT','Margin in £££s',' Margin %','Supplier E-mail','Auto Order','Number of Lots','Lead Time','Pronto','Tibs','Description of Collection','Description of Model','No. of Items' ,'Photo 1','Photo 2','Photo3');

	}else if(stristr($cat_name,"adjust")) //Adjustible Beds
	{
		$query = "select pcode,product_status,cost_of_packing,special_case_cost,specialdelivery,
		( SELECT optionvalue FROM tblbed_option WHERE id = supplier ) AS supplier,
		collection, model,
		( SELECT optionvalue FROM tblbed_option WHERE id = fillings ) AS fillings,
		( SELECT optionvalue FROM tblbed_option WHERE id = mattressType ) AS mattressType,
		( SELECT optionvalue FROM tblbed_option WHERE id = springCount ) AS springCount,
		( SELECT optionvalue FROM tblbed_option WHERE id = dov ) AS dov,
		std_remote,infra_remote,single_massage_unit,dual_massage_unit,heavy_duty_motor,
		( SELECT optionvalue FROM tblbed_option WHERE id = finish ) AS finish,
		( SELECT optionvalue FROM tblbed_option WHERE id = comfort ) AS comfort,
		turningOrder,
		( SELECT optionvalue FROM tblbed_option WHERE id = mattDepth ) AS mattDepth,
		( SELECT optionvalue FROM tblbed_option WHERE id = baseType ) AS baseType,
		( SELECT optionvalue FROM tblbed_option WHERE id = drawer ) AS drawer,
		 overallDimension,
		( SELECT optionvalue FROM tblbed_option WHERE id = tension ) AS tension,
		 ( SELECT optionvalue FROM tblbed_option WHERE id = cover ) AS cover,
		 ( SELECT optionvalue FROM tblbed_option WHERE id = size ) AS size,
		  costOfGood,costOfBoxes, creditCardCharge, costWithoutFreight, FreightType, weight,to20Kilos,additionalKgs,ratePKilo,cpdTohouse,totalDeliveryCost, totalCost, vat, retailIncVat, marginInPound, marginPercent,supEmail,autoOrder, numberOfLots,leadTime,pronto,tibs, desCollection,desModel,no_of_items,".$glob['dbprefix']."img_idx.imgpath1 as photo1,".$glob['dbprefix']."img_idx.imgpath2 as photo2,".$glob['dbprefix']."img_idx.imgpath3 as photo3  from ".$glob['dbprefix']."inventory left join ".$glob['dbprefix']."img_idx on ".$glob['dbprefix']."img_idx.product_id=".$glob['dbprefix']."inventory.product_id where cat_id=".$cat_id;

		$Heading=array('Product Code','Product Status','Supplier','Collection','Model','Fillings','Mattress Type','Spring Count','Depth of Visco','Std Remote','Infra Red Remote','Single Massage','Dual Massage','Heavy Duty Moter','Finish','Comfort','Turning Order','Mattress Depth','Base Type','Drawer','Overall Dimensions','Tension','Cover Grade','Size','Cost of Goods',' Cost of Boxes','cost_of_packing','Special Case Cost','Credit Card Charge','Cost Without Freight','Freight Type','Weight','£ to 20 kilos','Additional Kgs','Rate per Kilo','cpd to Private House','Special Delivery','Total Delivery Cost','Total Cost','VAT','Retail Inc. VAT','Margin in £££s','Margin %','Supplier E-mail','Auto Order','Number of Lots','Lead Time','Pronto','Tibs','Description of Collection',' Description of Model','No. of Items', 'Photo 1','Photo 2','Photo3');
	}
	//else if(stristr($cat_name,"divan") || stristr($cat_name,"mattr")) //Adjustible Beds
	else if((stristr($cat_name,"divan") and stristr($cat_name,"bed")) || (stristr($cat_name,"mattr") and (!stristr($cat_name,"top")) and (!stristr($cat_name,"prot"))))
	{
		$query = "SELECT * FROM ".$glob['dbprefix']."category WHERE cat_name like '%mat%'";
		$results = $db->select($query);
		$matcatid=$results[0]['cat_id'];

		$query = "SELECT * FROM ".$glob['dbprefix']."category WHERE cat_name like '%div%' and cat_name like '%bed%'";
		$results = $db->select($query);
		$divcatid=$results[0]['cat_id'];
		//echo $divcatid."- ".$matcatid; die;
		global $glob;

		$query = "select pcode,product_status,cost_of_packing,special_case_cost,specialdelivery,
		( SELECT optionvalue FROM tblbed_option WHERE id = supplier ) AS supplier,
		collection, model,
		 ( SELECT optionvalue FROM tblbed_option WHERE id = size ) AS size,
		 overallDimension,
		 ( SELECT optionvalue FROM tblbed_option WHERE id = mattressTick and supplier_id= supplier	) AS mattressTick,
		 ( SELECT optionvalue FROM tblbed_option WHERE id = baseTick and supplier_id= supplier	) AS baseTick,
		 ( SELECT optionvalue FROM tblbed_option WHERE id = baseColor and supplier_id= supplier	) AS baseColor,
		  ( SELECT optionvalue FROM tblbed_option WHERE id = comfort ) AS comfort,
 		  ( SELECT optionvalue FROM tblbed_option WHERE id = baseType ) AS baseType,
 		  ( SELECT optionvalue FROM tblbed_option WHERE id = tension ) AS tension,
  		  ( SELECT optionvalue FROM tblbed_option WHERE id = finish ) AS finish,
		   ( SELECT optionvalue FROM tblbed_option WHERE id = mattressType ) AS mattressType,
		 ( SELECT optionvalue FROM tblbed_option WHERE id = fillings ) AS fillings,
		 ( SELECT optionvalue FROM tblbed_option WHERE id = dov ) AS dov,
 		 ( SELECT optionvalue FROM tblbed_option WHERE id = springCount ) AS springCount,
 		 turningOrder,
  		 ( SELECT optionvalue FROM tblbed_option WHERE id = drawer ) AS drawer,
		 mattDepth,
		 costOfGood,costOfBoxes, creditCardCharge, costWithoutFreight, FreightType, weight,to20Kilos,additionalKgs,ratePKilo,cpdTohouse,totalDeliveryCost, totalCost, vat, retailIncVat, marginInPound, marginPercent,supEmail,autoOrder, numberOfLots,leadTime,pronto,tibs, desCollection,desModel,no_of_items,".$glob['dbprefix']."img_idx.imgpath1 as photo1,".$glob['dbprefix']."img_idx.imgpath2 as photo2,".$glob['dbprefix']."img_idx.imgpath3 as photo3  from ".$glob['dbprefix']."inventory left join ".$glob['dbprefix']."img_idx on ".$glob['dbprefix']."img_idx.product_id=".$glob['dbprefix']."inventory.product_id where (".$glob['dbprefix']."inventory.cat_id=".$divcatid. " or ".$glob['dbprefix']."inventory.cat_id=".$matcatid.") ";


		$Heading=array('Product Code','Product Status','Supplier',' Collection',' Model',' Size','Overall Dimensions','Mattress Tick','Base Tick','Base Color','Comfort','Base Type','Tension','Finish','Mattress Type','Fillings','Depth of Visco','Spring Count','Turning Order','No.of Drawers','Mattress Depth','Cost of Goods',' Cost of Boxes','cost_of_packing','Special Case Cost',' Credit Card Charge','Cost Without Freight','Freight Type','Weight',' £ to 20 kilos','Additional Kgs','Rate per Kilo','cpd to Private House','Special Delivery','Total Delivery Cost','Total Cost','VAT','Retail Inc. VAT','Margin in £££s','Margin %','Supplier E-mail','Auto Order','Number of Lots','Lead Time','Pronto','Tibs','Description of Collection',' Description of Model','No. of Items', 'Photo 1','Photo 2','Photo3');
	} else if(stristr($cat_name,"bases")) //Adjustible Beds
	{
		$query = "select pcode,product_status,cost_of_packing,special_case_cost,specialdelivery,
		( SELECT optionvalue FROM tblbed_option WHERE id = supplier ) AS supplier,
		model,
		( SELECT optionvalue FROM tblbed_option WHERE id = size ) AS size,
		( SELECT optionvalue FROM tblbed_option WHERE id = baseType ) AS baseType,
		( SELECT optionvalue FROM tblbed_option WHERE id = drawer ) AS drawer,
		overallDimension, costOfGood,costOfBoxes, creditCardCharge, costWithoutFreight, FreightType, weight,to20Kilos,additionalKgs,ratePKilo,cpdTohouse,totalDeliveryCost, totalCost, vat, retailIncVat, marginInPound, marginPercent,supEmail,autoOrder, numberOfLots,leadTime,pronto,tibs, desCollection,desModel,no_of_items,".$glob['dbprefix']."img_idx.imgpath1 as photo1,".$glob['dbprefix']."img_idx.imgpath2 as photo2,".$glob['dbprefix']."img_idx.imgpath3 as photo3  from ".$glob['dbprefix']."inventory left join ".$glob['dbprefix']."img_idx on ".$glob['dbprefix']."img_idx.product_id=".$glob['dbprefix']."inventory.product_id where cat_id=".$cat_id;

		$Heading=array('Product Code','Product Status','Supplier','Model','Size','Base Type','Drawers','Overall Dimensions','Cost of Goods','Cost of Boxes','cost_of_packing','Special Case Cost','Credit Card Charge','Cost Without Freight','Freight Type','Weight','£ to 20 kilos','Additional kgs','Rate per kilo','Cpd to private house','Special Delivery','Total delivery cost','Total cost','Vat','Retail inc vat','Margin in £££s','Margin %','Suppliers e mail','Auto order','Number of lots','Lead time','Pronto','Tibs','Description of collection','Description of model','No. of Items', 'Photo 1','Photo 2','Photo3');
	}
	else if(stristr($cat_name,"sofa")) //sofa beds
	{
		$query = "select pcode,product_status,cost_of_packing,special_case_cost,specialdelivery,
		( SELECT optionvalue FROM tblbed_option WHERE id = supplier ) AS supplier,
		collection,
		 ( SELECT optionvalue FROM tblbed_option WHERE id = model ) AS model,
		  ( SELECT optionvalue FROM tblbed_option WHERE id = tblbed_inventory.type ) AS type,
		 ( SELECT optionvalue FROM tblbed_option WHERE id = size ) AS size,
		 ( SELECT optionvalue FROM tblbed_option WHERE id = mattressType ) AS mattressType,
		 leftright,
		 ( SELECT optionvalue FROM tblbed_option WHERE id = colour ) AS colour,
		 ( SELECT optionvalue FROM tblbed_option WHERE id = colour2 ) AS colour2,
		 overallDimension,sizeOpen,sizeSleepingSurface,cover,costOfGood,costOfBoxes, creditCardCharge, costWithoutFreight, FreightType, weight,to20Kilos,additionalKgs,ratePKilo,cpdTohouse,totalDeliveryCost, totalCost, vat, retailIncVat, marginInPound, marginPercent,supEmail,autoOrder, numberOfLots,leadTime,pronto,tibs, desCollection,desModel,no_of_items ,".$glob['dbprefix']."img_idx.imgpath1 as photo1,".$glob['dbprefix']."img_idx.imgpath2 as photo2,".$glob['dbprefix']."img_idx.imgpath3 as photo3  from ".$glob['dbprefix']."inventory left join ".$glob['dbprefix']."img_idx on ".$glob['dbprefix']."img_idx.product_id=".$glob['dbprefix']."inventory.product_id where cat_id=".$cat_id;

		$Heading=array('Product Code','Product Status','Supplier','Collection','Model','Type','Size','Mattress Type','Left Right','Colour1','Colour2','Overall dimensions','Size When Open','Size of Sleeping Surface','Cover grade','Cost of Goods','Cost of Boxes','cost_of_packing','Special Case Cost','Credit Card Charge','Cost Without Freight','Freight Type','Weight','£ to 20 kilos','Additional kgs','Rate per kilo','Cpd to private house','Special Delivery','Total delivery cost','Total cost','Vat','Retail inc vat','Margin in £££s','Margin %','Suppliers e mail','Auto order','Number of lots','Lead time','Pronto','Tibs','Description of collection','Description of model','No. of Items','Photo 1','Photo 2','Photo3');


	}
} else // accessories file code
{
	$query = "select pcode,product_status,cost_of_packing,special_case_cost,specialdelivery,
	( SELECT optionvalue FROM tblbed_option WHERE id = supplier ) AS supplier,
	collection, model,
	( SELECT optionvalue FROM tblbed_option WHERE id = fillings ) AS fillings,
	( SELECT optionvalue FROM tblbed_option WHERE id = size ) AS size,
	( SELECT optionvalue FROM tblbed_option WHERE id = finish ) AS finish,
	cover,
	( SELECT optionvalue FROM tblbed_option WHERE id = colour ) AS colour,
	fabricType,waterproof,costOfGood,costOfBoxes, creditCardCharge, costWithoutFreight, FreightType, weight,to20Kilos,additionalKgs,ratePKilo,cpdTohouse,totalDeliveryCost, totalCost, vat, retailIncVat, marginInPound, marginPercent,supEmail,autoOrder, numberOfLots,leadTime,pronto,tibs, desCollection,desModel,no_of_items ,".$glob['dbprefix']."img_idx.imgpath1 as photo1,".$glob['dbprefix']."img_idx.imgpath2 as photo2,".$glob['dbprefix']."img_idx.imgpath3 as photo3  from ".$glob['dbprefix']."inventory left join ".$glob['dbprefix']."img_idx on ".$glob['dbprefix']."img_idx.product_id=".$glob['dbprefix']."inventory.product_id where cat_id=".$cat_id;

	$Heading=array('Product Code','Product Status','Supplier','Collection','Model','Fillings','Size','Finish','Cover','Colour','Fabric Type','Water Proof','Cost of Goods','Cost of Boxes','cost_of_packing','Special Case Cost','Credit Card Charge','Cost Without Freight','Freight Type','Weight','£ to 20 kilos','Additional kgs','Rate per kilo','Cpd to private house','Special Delivery','Total delivery cost','Total cost','Vat','Retail inc vat','Margin in £££s','Margin %','Suppliers e mail','Auto order','Number of lots','Lead time','Pronto','Tibs','Description of collection','Description of model','No. of Items','Photo 1','Photo 2','Photo3');
}

/*####################################################################
					Supplier based Query
#####################################################################*/

if(isset($supplier) and !empty($supplier)) {
		if((stristr($cat_name,"divan") and stristr($cat_name,"bed")) || (stristr($cat_name,"mattr") and (!stristr($cat_name,"top")) and (!stristr($cat_name,"prot"))))
		{
		$query .= " and ".$glob['dbprefix']."inventory.supplier in  (select id from tblbed_option where type ='supplier' and optionvalue ='".$supplier."' and cat_id = $cat_id)";
		}else{
			$query .= " and ".$glob['dbprefix']."inventory.supplier= (select id from tblbed_option where type ='supplier' and optionvalue ='".$supplier."' and cat_id = $cat_id)";
		}
}
		$query .=" order by ".$glob['dbprefix']."inventory.product_id asc";


$results = $db->select($query);
$numrows = $db->numrows($query);
$numfields = $db->numfields($query);

//:::::::::PATH SETTING FOR THE EXCEL FILE:::::::::::::::::::::::::::::::::::::::::\\
// Creating a workbook
$workbook =& new Spreadsheet_Excel_Writer();
$workbook->setVersion(8);
$workbook->send($filename);
$worksheet2 =& $workbook->addWorksheet($cat_name);

#:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::\\
# Format for the headings
$formatot =& $workbook->addFormat();
$formatot->setSize(11);
$formatot->setAlign('center');
$formatot->setColor('black');
$formatot->setPattern();
$formatot->setFgColor('white');
$formatot->setBold(1);
//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::\\
//===WRITE HEADINH=====\\
//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::\\
for($i=0; $i<count($Heading); $i++) {
	$worksheet2->write(0,$i,$Heading[$i],$formatot);
}
//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::\\
$formatot =& $workbook->addFormat();
$formatot->setSize(10);
$formatot->setAlign('left');
$formatot->setColor('black');
$formatot->setPattern();
$formatot->setFgColor('white');
$formatot->setNumFormat('49'); // 49 is for text type format

//:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::\\
if($parent_id ==0)
{

	if(stristr($cat_name,"fold")) //Folding Beds
	{
		for($i=0; $i<count($results); $i++)
		{
			$withheadboards = $results[$i]['headboard'] == 1?'Yes':'No';
			$specialDelivery=$results[$i]['specialdelivery'] == 1?'Yes':'No';
			$autoOrder = $results[$i]['autoOrder'] == 1?'Yes':'No';
			$pronto = $results[$i]['pronto'] == 1?'Yes':'No';
			$tibs = $results[$i]['tibs'] == 1?'Yes':'No';
			$freightType= isset($freight_type[$results[$i]['FreightType']])? $freight_type[$results[$i]['FreightType']]:'';
			$worksheet2->write($i+1,0,$results[$i]['pcode'],$formatot);
			$worksheet2->write($i+1,1,$results[$i]['product_status'],$formatot);
			$worksheet2->write($i+1,2,$results[$i]['supplier'],$formatot);
			$worksheet2->write($i+1,3,$results[$i]['collection'],$formatot);
			$worksheet2->write($i+1,4,$results[$i]['model'],$formatot);
			$worksheet2->write($i+1,5,$results[$i]['size'],$formatot);
			$worksheet2->write($i+1,6,$results[$i]['baseType'],$formatot);
			$worksheet2->write($i+1,7,$results[$i]['mattressType'],$formatot);
			$worksheet2->write($i+1,8,$results[$i]['closedDimention'],$formatot);
			$worksheet2->write($i+1,9,$results[$i]['openDimension'],$formatot);
			$worksheet2->write($i+1,10,$results[$i]['framecolor'],$formatot);
			$worksheet2->write($i+1,11,$results[$i]['mattressColor'],$formatot);
			$worksheet2->write($i+1,12,$withheadboards,$formatot);
			$worksheet2->write($i+1,13,$results[$i]['legType'],$formatot);
			$worksheet2->write($i+1,14,$results[$i]['costOfGood'],$formatot);
			$worksheet2->write($i+1,15,$results[$i]['costOfBoxes'],$formatot);
			$worksheet2->write($i+1,16,$results[$i]['cost_of_packing'],$formatot);
			$worksheet2->write($i+1,17,"=round(((AE".($i+2)."/100)*5),2)",$formatot); // Credit card charges
			$worksheet2->write($i+1,18,"=round((O".($i+2)."+P".($i+2)."+R".($i+2)."+Q".($i+2)."),2)",$formatot);  //Cost Without freight
			$worksheet2->write($i+1,19,$freightType,$formatot);
			$worksheet2->write($i+1,20,$results[$i]['weight'],$formatot);
			$worksheet2->write($i+1,21,$results[$i]['to20Kilos'],$formatot);
			$worksheet2->write($i+1,22,"=if(U".($i+2)." > 20 , U".($i+2)."-20,0)",$formatot);  //Additional Kgs
			$worksheet2->write($i+1,23,$results[$i]['ratePKilo'],$formatot);
			$worksheet2->write($i+1,24,$results[$i]['cpdTohouse'],$formatot);
			$worksheet2->write($i+1,25,$specialDelivery,$formatot);
			$worksheet2->write($i+1,26,"= if(AC".($i+2)." > 0 ,AC".($i+2).",round((W".($i+2)."*X".($i+2).")+V".($i+2)."+Y".($i+2).",2))",$formatot); // Total Delivery Cost
			$worksheet2->write($i+1,27,"=round((S".($i+2)."+AA".($i+2)."),2)",$formatot); // Total Cost
			$worksheet2->write($i+1,28,$results[$i]['special_case_cost'],$formatot);
			$worksheet2->write($i+1,29,"=round(AE".($i+2)."-(AE".($i+2)."/1.175),2)",$formatot); // Vat
			$worksheet2->write($i+1,30,$results[$i]['retailIncVat'],$formatot);
			$worksheet2->write($i+1,31,"=round((AE".($i+2)."/1.175)-AB".($i+2).",2)",$formatot); //Margin in Pounds
			$worksheet2->write($i+1,32,"=round((AF".($i+2)."/(AE".($i+2)."/1.175)*100),2)",$formatot); //Margin in Percent
			$worksheet2->write($i+1,33,$results[$i]['supEmail'],$formatot);
			$worksheet2->write($i+1,34,$autoOrder,$formatot);
			$worksheet2->write($i+1,35,$results[$i]['numberOfLots'],$formatot);
			$worksheet2->write($i+1,36,$results[$i]['leadTime'],$formatot);
			$worksheet2->write($i+1,37,$pronto,$formatot);
			$worksheet2->write($i+1,38,$tibs,$formatot);
			$worksheet2->write($i+1,39,$results[$i]['desCollection'],$formatot);
			$worksheet2->write($i+1,40,$results[$i]['desModel'],$formatot);
			$worksheet2->write($i+1,41,$results[$i]['no_of_items'],$formatot);
			$worksheet2->write($i+1,42,$results[$i]['photo1'],$formatot);
			$worksheet2->write($i+1,43,$results[$i]['photo2'],$formatot);
			$worksheet2->write($i+1,44,$results[$i]['photo3'],$formatot);
		}
	}else if(stristr($cat_name,"frame")) //Bed Frames
	{
		for($i=0; $i<count($results); $i++)
		{
			$availableassurround = $results[$i]['surround'] == 1?'Yes':'No';
			$specialDelivery=$results[$i]['specialdelivery'] == 1?'Yes':'No';
			//$withStorage=$results[$i]['withStorage'] == 1?'Yes':'No';
			$baseHeightadjOption=$results[$i]['baseHeightadjOption'] == 1?'Yes':'No';
			$autoOrder = $results[$i]['autoOrder'] == 1?'Yes':'No';
			$pronto = $results[$i]['pronto'] == 1?'Yes':'No';
			$tibs = $results[$i]['tibs'] == 1?'Yes':'No';
			$freightType= isset($freight_type[$results[$i]['FreightType']])? $freight_type[$results[$i]['FreightType']]:'';
			$worksheet2->write($i+1,0,$results[$i]['pcode'],$formatot);
			$worksheet2->write($i+1,1,$results[$i]['product_status'],$formatot);
			$worksheet2->write($i+1,2,$results[$i]['supplier'],$formatot);
			$worksheet2->write($i+1,3,$results[$i]['collection'],$formatot);
			$worksheet2->write($i+1,4,$results[$i]['model'],$formatot);
			$worksheet2->write($i+1,5,$results[$i]['size'],$formatot);
			$worksheet2->write($i+1,6,$results[$i]['finish'],$formatot);
			$worksheet2->write($i+1,7,$results[$i]['colour'],$formatot);
			$worksheet2->write($i+1,8,$results[$i]['colour2'],$formatot);
			$worksheet2->write($i+1,9,$results[$i]['footEndOption'],$formatot);
			$worksheet2->write($i+1,10,$baseHeightadjOption,$formatot);
			$worksheet2->write($i+1,11,$results[$i]['withStorage'],$formatot);
			//  $worksheet2->write($i+1,10,$withStorage,$formatot);
			$worksheet2->write($i+1,12,$results[$i]['overallDimension'],$formatot);
			$worksheet2->write($i+1,13,$results[$i]['headboard'],$formatot);
			$worksheet2->write($i+1,14,$results[$i]['footboard'],$formatot);
			$worksheet2->write($i+1,15,$results[$i]['baseType'],$formatot);
			$worksheet2->write($i+1,16,$availableassurround,$formatot);
			$worksheet2->write($i+1,17,$results[$i]['cover'],$formatot);
			$worksheet2->write($i+1,18,$results[$i]['costOfGood'],$formatot);
			$worksheet2->write($i+1,19,$results[$i]['costOfBoxes'],$formatot);
			$worksheet2->write($i+1,20,$results[$i]['cost_of_packing'],$formatot);
			$worksheet2->write($i+1,21,"=round(((AI".($i+2)."/100)*5),2)",$formatot); // Credit card charges
			$worksheet2->write($i+1,22,"=round((S".($i+2)."+T".($i+2)."+V".($i+2)."+U".($i+2)."),2)",$formatot); //Cost Without freight
			$worksheet2->write($i+1,23,$freightType,$formatot);
			$worksheet2->write($i+1,24,$results[$i]['weight'],$formatot);
			$worksheet2->write($i+1,25,$results[$i]['to20Kilos'],$formatot);
			$worksheet2->write($i+1,26,"=if(Y".($i+2)." > 20 , Y".($i+2)."-20,0)",$formatot);  // Aditional kga
			$worksheet2->write($i+1,27,$results[$i]['ratePKilo'],$formatot);
		    $worksheet2->write($i+1,28,$results[$i]['cpdTohouse'],$formatot);
			$worksheet2->write($i+1,29,$specialDelivery,$formatot);
		//	$worksheet2->write($i+1,29,"=round((AA".($i+2)."*AB".($i+2).")+Z".($i+2)."+AC".($i+2).",2)",$formatot);
			$worksheet2->write($i+1,30,"= if(AG".($i+2)." > 0 ,AG".($i+2).",round((AA".($i+2)."*AB".($i+2).")+Z".($i+2)."+AC".($i+2).",2))",$formatot); // Total Delivery Cost
			$worksheet2->write($i+1,31,"=round((W".($i+2)."+AE".($i+2)."),2)",$formatot); // Total Cost
			$worksheet2->write($i+1,32,$results[$i]['special_case_cost'],$formatot);
			$worksheet2->write($i+1,33,"=round(AI".($i+2)."-(AI".($i+2)."/1.175),2)",$formatot); //Vat
			$worksheet2->write($i+1,34,$results[$i]['retailIncVat'],$formatot);
			$worksheet2->write($i+1,35,"=round((AI".($i+2)."/1.175)-AF".($i+2).",2)",$formatot);  // Margin in pounds
			$worksheet2->write($i+1,36,"=round((AJ".($i+2)."/(AI".($i+2)."/1.175)*100),2)",$formatot); // Margin in Percent
			$worksheet2->write($i+1,37,$results[$i]['supEmail'],$formatot);
			$worksheet2->write($i+1,38,$autoOrder,$formatot);
			$worksheet2->write($i+1,39,$results[$i]['numberOfLots'],$formatot);
			$worksheet2->write($i+1,40,$results[$i]['leadTime'],$formatot);
			$worksheet2->write($i+1,41,$pronto,$formatot);
			$worksheet2->write($i+1,42,$tibs,$formatot);
			$worksheet2->write($i+1,43,$results[$i]['desCollection'],$formatot);
			$worksheet2->write($i+1,44,$results[$i]['desModel'],$formatot);
			$worksheet2->write($i+1,45,$results[$i]['no_of_items'],$formatot);
			$worksheet2->write($i+1,46,$results[$i]['photo1'],$formatot);
			$worksheet2->write($i+1,47,$results[$i]['photo2'],$formatot);
			$worksheet2->write($i+1,48,$results[$i]['photo3'],$formatot);
		} //end for
	}else if(stristr($cat_name,"head")) //head boards
	{
		for($i=0; $i<count($results); $i++)
		{

			$floorStanding = $results[$i]['floorStanding'] == 1?'Yes':'No';
			$specialDelivery=$results[$i]['specialdelivery'] == 1?'Yes':'No';
			$autoOrder = $results[$i]['autoOrder'] == 1?'Yes':'No';
			$pronto = $results[$i]['pronto'] == 1?'Yes':'No';
			$tibs = $results[$i]['tibs'] == 1?'Yes':'No';
			$freightType= isset($freight_type[$results[$i]['FreightType']])? $freight_type[$results[$i]['FreightType']]:'';

			$worksheet2->write($i+1,0,$results[$i]['pcode'],$formatot);
			$worksheet2->write($i+1,1,$results[$i]['product_status'],$formatot);
			$worksheet2->write($i+1,2,$results[$i]['supplier'],$formatot);
			$worksheet2->write($i+1,3,$results[$i]['collection'],$formatot);
			$worksheet2->write($i+1,4,$results[$i]['model'],$formatot);
			$worksheet2->write($i+1,5,$results[$i]['size'],$formatot);
			$worksheet2->write($i+1,6,$results[$i]['finish'],$formatot);
			$worksheet2->write($i+1,7,$results[$i]['colour'],$formatot);
			$worksheet2->write($i+1,8,$results[$i]['colour2'],$formatot);
			$worksheet2->write($i+1,9,$results[$i]['overallDimension'],$formatot);
			$worksheet2->write($i+1,10,$floorStanding ,$formatot);
			$worksheet2->write($i+1,11,$results[$i]['cover'],$formatot);
			$worksheet2->write($i+1,12,$results[$i]['costOfGood'],$formatot);
			$worksheet2->write($i+1,13,$results[$i]['costOfBoxes'],$formatot);
			$worksheet2->write($i+1,14,$results[$i]['cost_of_packing'],$formatot);
			$worksheet2->write($i+1,15,$results[$i]['special_case_cost'],$formatot);
			$worksheet2->write($i+1,16,"=round(((AC".($i+2)."/100)*5),2)",$formatot); // Credit card charges
			$worksheet2->write($i+1,17,"=round((M".($i+2)."+N".($i+2)."+O".($i+2)."+Q".($i+2)."),2)",$formatot); // cost without freight
			$worksheet2->write($i+1,18,$freightType,$formatot);
			$worksheet2->write($i+1,19,$results[$i]['weight'],$formatot);
			$worksheet2->write($i+1,20,$results[$i]['to20Kilos'],$formatot);
			$worksheet2->write($i+1,21,"=if(T".($i+2)." > 20 , T".($i+2)."-20,0)",$formatot); // additional kgs
			$worksheet2->write($i+1,22,$results[$i]['ratePKilo'],$formatot);
			$worksheet2->write($i+1,23,$results[$i]['cpdTohouse'],$formatot);
			$worksheet2->write($i+1,24,$specialDelivery,$formatot);
			$worksheet2->write($i+1,25,"= if(P".($i+2)." > 0 ,P".($i+2).",round((V".($i+2)."*W".($i+2).")+U".($i+2)."+X".($i+2).",2))",$formatot); // Total Delivery Cost
			$worksheet2->write($i+1,26,"=round((R".($i+2)."+Z".($i+2)."),2)",$formatot); // total cost
			$worksheet2->write($i+1,27,"=round(AC".($i+2)."-(AC".($i+2)."/1.175),2)",$formatot); // vat
			$worksheet2->write($i+1,28,$results[$i]['retailIncVat'],$formatot);
			$worksheet2->write($i+1,29,"=round((AC".($i+2)."/1.175)-AA".($i+2).",2)",$formatot);  // margin in pounds
			$worksheet2->write($i+1,30,"=round((AD".($i+2)."/(AC".($i+2)."/1.175)*100),2)",$formatot); // margin in percent
			$worksheet2->write($i+1,31,$results[$i]['supEmail'],$formatot);
			$worksheet2->write($i+1,32,$autoOrder,$formatot);
			$worksheet2->write($i+1,33,$results[$i]['numberOfLots'],$formatot);
			$worksheet2->write($i+1,34,$results[$i]['leadTime'],$formatot);
			$worksheet2->write($i+1,35,$pronto,$formatot);
			$worksheet2->write($i+1,36,$tibs,$formatot);
			$worksheet2->write($i+1,37,$results[$i]['desCollection'],$formatot);
			$worksheet2->write($i+1,38,$results[$i]['desModel'],$formatot);
			$worksheet2->write($i+1,39,$results[$i]['no_of_items'],$formatot);
			$worksheet2->write($i+1,40,$results[$i]['photo1'],$formatot);
			$worksheet2->write($i+1,41,$results[$i]['photo2'],$formatot);
			$worksheet2->write($i+1,42,$results[$i]['photo3'],$formatot);
		} //end for
	}else if(stristr($cat_name,"adjust")) //Adjustable
	{
		for($i=0; $i<count($results); $i++)
		{
			$std_remote = $results[$i]['std_remote'] == 1?'Yes':'No';
			$specialDelivery=$results[$i]['specialdelivery'] == 1?'Yes':'No';
			$infra_remote = $results[$i]['infra_remote'] == 1?'Yes':'No';
			$single_massage_unit = $results[$i]['single_massage_unit'] == 1?'Yes':'No';
			$dual_massage_unit = $results[$i]['dual_massage_unit'] == 1?'Yes':'No';
			$heavy_duty_motor = $results[$i]['heavy_duty_motor'] == 1?'Yes':'No';
			$turningOrder = $results[$i]['turningOrder'] == 1?'Yes':'No';
			$autoOrder = $results[$i]['autoOrder'] == 1?'Yes':'No';
			$pronto = $results[$i]['pronto'] == 1?'Yes':'No';
			$tibs = $results[$i]['tibs'] == 1?'Yes':'No';
			$freightType= isset($freight_type[$results[$i]['FreightType']])? $freight_type[$results[$i]['FreightType']]:'';

			$worksheet2->write($i+1,0,$results[$i]['pcode'],$formatot);
			$worksheet2->write($i+1,1,$results[$i]['product_status'],$formatot);
			$worksheet2->write($i+1,2,$results[$i]['supplier'],$formatot);
			$worksheet2->write($i+1,3,$results[$i]['collection'],$formatot);
			$worksheet2->write($i+1,4,$results[$i]['model'],$formatot);
			$worksheet2->write($i+1,5,$results[$i]['fillings'],$formatot);
			$worksheet2->write($i+1,6,$results[$i]['mattressType'],$formatot);
			$worksheet2->write($i+1,7,$results[$i]['springCount'],$formatot);
			$worksheet2->write($i+1,8,$results[$i]['dov'],$formatot);
			$worksheet2->write($i+1,9,$std_remote,$formatot);
			$worksheet2->write($i+1,10,$infra_remote,$formatot);
			$worksheet2->write($i+1,11,$single_massage_unit,$formatot);
			$worksheet2->write($i+1,12,$dual_massage_unit,$formatot);
			$worksheet2->write($i+1,13,$heavy_duty_motor,$formatot);
			$worksheet2->write($i+1,14,$results[$i]['finish'],$formatot);
			$worksheet2->write($i+1,15,$results[$i]['comfort'],$formatot);
			$worksheet2->write($i+1,16,$turningOrder,$formatot);
			$worksheet2->write($i+1,17,$results[$i]['mattDepth'],$formatot);
			$worksheet2->write($i+1,18,$results[$i]['baseType'],$formatot);
			$worksheet2->write($i+1,19,$results[$i]['drawer'],$formatot);
			$worksheet2->write($i+1,20,$results[$i]['overallDimension'],$formatot);
			$worksheet2->write($i+1,21,$results[$i]['tension'],$formatot);
			$worksheet2->write($i+1,22,$results[$i]['cover'],$formatot);
			$worksheet2->write($i+1,23,$results[$i]['size'],$formatot);
			$worksheet2->write($i+1,24,$results[$i]['costOfGood'],$formatot);
			$worksheet2->write($i+1,25,$results[$i]['costOfBoxes'],$formatot);
			$worksheet2->write($i+1,26,$results[$i]['cost_of_packing'],$formatot);
			$worksheet2->write($i+1,27,$results[$i]['special_case_cost'],$formatot);
			$worksheet2->write($i+1,28,"=round(((AO".($i+2)."/100)*5),2)",$formatot);
			$worksheet2->write($i+1,29,"=round((Y".($i+2)."+Z".($i+2)."+AA".($i+2)."+AC".($i+2)."),2)",$formatot);
			$worksheet2->write($i+1,30,$freightType,$formatot);
			$worksheet2->write($i+1,31,$results[$i]['weight'],$formatot);
			$worksheet2->write($i+1,32,$results[$i]['to20Kilos'],$formatot);
//			$worksheet2->write($i+1,31,$results[$i]['additionalKgs'],$formatot);
			$worksheet2->write($i+1,33,"=if(AF".($i+2)." > 20 , AF".($i+2)."-20,0)",$formatot);
			$worksheet2->write($i+1,34,$results[$i]['ratePKilo'],$formatot);
			$worksheet2->write($i+1,35,$results[$i]['cpdTohouse'],$formatot);
			$worksheet2->write($i+1,36,$specialDelivery,$formatot);
//			$worksheet2->write($i+1,34,$results[$i]['totalDeliveryCost'],$formatot);
//			$worksheet2->write($i+1,36,"=round((AH".($i+2)."*AI".($i+2).")+AG".($i+2)."+AJ".($i+2).",2)",$formatot);
			$worksheet2->write($i+1,37,"= if(AB".($i+2)." > 0 ,AB".($i+2).",round((AH".($i+2)."*AI".($i+2).")+AG".($i+2)."+AJ".($i+2).",2))",$formatot); // Total Delivery Cost
			$worksheet2->write($i+1,38,"=round((AD".($i+2)."+AL".($i+2)."),2)",$formatot); //total Cost
			$worksheet2->write($i+1,39,"=round(AO".($i+2)."-(AO".($i+2)."/1.175),2)",$formatot); //vat
			$worksheet2->write($i+1,40,$results[$i]['retailIncVat'],$formatot);
			$worksheet2->write($i+1,41,"=round((AO".($i+2)."/1.175)-AM".($i+2).",2)",$formatot); // margin in percent
			$worksheet2->write($i+1,42,"=round((AP".($i+2)."/(AO".($i+2)."/1.175)*100),2)",$formatot); // margin in pounds
			$worksheet2->write($i+1,43,$results[$i]['supEmail'],$formatot);
			$worksheet2->write($i+1,44,$autoOrder,$formatot);
			$worksheet2->write($i+1,45,$results[$i]['numberOfLots'],$formatot);
			$worksheet2->write($i+1,46,$results[$i]['leadTime'],$formatot);
			$worksheet2->write($i+1,47,$pronto,$formatot);
			$worksheet2->write($i+1,48,$tibs,$formatot);
			$worksheet2->write($i+1,49,$results[$i]['desCollection'],$formatot);
			$worksheet2->write($i+1,50,$results[$i]['desModel'],$formatot);
			$worksheet2->write($i+1,51,$results[$i]['no_of_items'],$formatot);
			$worksheet2->write($i+1,52,$results[$i]['photo1'],$formatot);
			$worksheet2->write($i+1,53,$results[$i]['photo2'],$formatot);
			$worksheet2->write($i+1,54,$results[$i]['photo3'],$formatot);

		} //end for
	}
else if((stristr($cat_name,"divan") and stristr($cat_name,"bed")) || (stristr($cat_name,"mattr") and !stristr($cat_name,"top") and !stristr($cat_name,"prot"))) //Divans
	{
		for($i=0; $i<count($results); $i++)
		{
			$turningOrder = ucwords($turningOption_divan[$results[$i]['turningOrder']]);
			$freightType=isset($freight_type[$results[$i]['FreightType']])? $freight_type[$results[$i]['FreightType']]:'';
			$autoOrder = $results[$i]['autoOrder'] == 1?'Yes':'No';
			$specialDelivery=$results[$i]['specialdelivery'] == 1?'Yes':'No';
			$pronto = $results[$i]['pronto'] == 1?'Yes':'No';
			$tibs = $results[$i]['tibs'] == 1?'Yes':'No';

			$worksheet2->write($i+1,0,$results[$i]['pcode'],$formatot);
			$worksheet2->write($i+1,1,$results[$i]['product_status'],$formatot);
			$worksheet2->write($i+1,2,$results[$i]['supplier'],$formatot);
			$worksheet2->write($i+1,3,$results[$i]['collection'],$formatot);
			$worksheet2->write($i+1,4,$results[$i]['model'],$formatot);
			$worksheet2->write($i+1,5,$results[$i]['size'],$formatot);
			$worksheet2->write($i+1,6,$results[$i]['overallDimension'],$formatot);
			$worksheet2->write($i+1,7,$results[$i]['mattressTick'],$formatot);
			$worksheet2->write($i+1,8,$results[$i]['baseTick'],$formatot);
			$worksheet2->write($i+1,9,$results[$i]['baseColor'],$formatot);
			$worksheet2->write($i+1,10,$results[$i]['comfort'],$formatot);
			$worksheet2->write($i+1,11,$results[$i]['baseType'],$formatot);
			$worksheet2->write($i+1,12,$results[$i]['tension'],$formatot);
			$worksheet2->write($i+1,13,$results[$i]['finish'],$formatot);
			$worksheet2->write($i+1,14,$results[$i]['mattressType'],$formatot);
			$worksheet2->write($i+1,15,$results[$i]['fillings'],$formatot);
			$worksheet2->write($i+1,16,$results[$i]['dov'],$formatot);
			$worksheet2->write($i+1,17,$results[$i]['springCount'],$formatot);
			$worksheet2->write($i+1,18,$turningOrder,$formatot);
			$worksheet2->write($i+1,19,$results[$i]['drawer'],$formatot);
			$worksheet2->write($i+1,20,$results[$i]['mattDepth'],$formatot);
			$worksheet2->write($i+1,21,$results[$i]['costOfGood'],$formatot);
			$worksheet2->write($i+1,22,$results[$i]['costOfBoxes'],$formatot);
			$worksheet2->write($i+1,23,$results[$i]['cost_of_packing'],$formatot);
			$worksheet2->write($i+1,24,$results[$i]['special_case_cost'],$formatot);
			$worksheet2->write($i+1,25,"=round(((AL".($i+2)."/100)*5),2)",$formatot);
			$worksheet2->write($i+1,26,"=round((V".($i+2)."+W".($i+2)."+X".($i+2)."+Z".($i+2)."),2)",$formatot);
			$worksheet2->write($i+1,27,$freightType,$formatot);
			$worksheet2->write($i+1,28,$results[$i]['weight'],$formatot);
			$worksheet2->write($i+1,29,$results[$i]['to20Kilos'],$formatot);
			$worksheet2->write($i+1,30,"=if(AC".($i+2)." > 20 , AC".($i+2)."-20,0)",$formatot);
			$worksheet2->write($i+1,31,$results[$i]['ratePKilo'],$formatot);
			$worksheet2->write($i+1,32,$results[$i]['cpdTohouse'],$formatot);
			$worksheet2->write($i+1,33,$specialDelivery,$formatot);
			
//			$worksheet2->write($i+1,33,"=round((AE".($i+2)."*AF".($i+2).")+AD".($i+2)."+AG".($i+2).",2)",$formatot);
			$worksheet2->write($i+1,34,"= if(Y".($i+2)." > 0 ,Y".($i+2).",round((AE".($i+2)."*AF".($i+2).")+AD".($i+2)."+AG".($i+2).",2))",$formatot); // Total Delivery Cost

			//$worksheet2->write($i+1,31,$results[$i]['totalDeliveryCost'],$formatot); //AE
			$worksheet2->write($i+1,35,"=round((AA".($i+2)."+AI".($i+2)."),2)",$formatot); // Total cost
			$worksheet2->write($i+1,36,"=round(AL".($i+2)."-(AL".($i+2)."/1.175),2)",$formatot); //vat
			$worksheet2->write($i+1,37,$results[$i]['retailIncVat'],$formatot); 
			$worksheet2->write($i+1,38,"=round((AL".($i+2)."/1.175)-AJ".($i+2).",2)",$formatot); // margin in pounds
			$worksheet2->write($i+1,39,"=round((AM".($i+2)."/(AL".($i+2)."/1.175)*100),2)",$formatot); // margin in percents
			$worksheet2->write($i+1,40,$results[$i]['supEmail'],$formatot);
			$worksheet2->write($i+1,41,$autoOrder,$formatot);
			$worksheet2->write($i+1,42,$results[$i]['numberOfLots'],$formatot);
			$worksheet2->write($i+1,43,$results[$i]['leadTime'],$formatot);
			$worksheet2->write($i+1,44,$pronto,$formatot);
			$worksheet2->write($i+1,45,$tibs,$formatot);
			$worksheet2->write($i+1,46,$results[$i]['desCollection'],$formatot);
			$worksheet2->write($i+1,47,$results[$i]['desModel'],$formatot);
			$worksheet2->write($i+1,48,$results[$i]['no_of_items'],$formatot);
			$worksheet2->write($i+1,49,$results[$i]['photo1'],$formatot);
			$worksheet2->write($i+1,50,$results[$i]['photo2'],$formatot);
			$worksheet2->write($i+1,51,$results[$i]['photo3'],$formatot);
		} //end for
	}else if(stristr($cat_name,"bases")) //Bed Frames
	{
		for($i=0; $i<count($results); $i++)
		{

			$autoOrder = $results[$i]['autoOrder'] == 1?'Yes':'No';
			$specialDelivery=$results[$i]['specialdelivery'] == 1?'Yes':'No';
			$pronto = $results[$i]['pronto'] == 1?'Yes':'No';
			$tibs = $results[$i]['tibs'] == 1?'Yes':'No';
			$freightType= isset($freight_type[$results[$i]['FreightType']])? $freight_type[$results[$i]['FreightType']]:'';

			$worksheet2->write($i+1,0,$results[$i]['pcode'],$formatot);
			$worksheet2->write($i+1,1,$results[$i]['product_status'],$formatot);
			$worksheet2->write($i+1,2,$results[$i]['supplier'],$formatot);
			$worksheet2->write($i+1,3,$results[$i]['model'],$formatot);
			$worksheet2->write($i+1,4,$results[$i]['size'],$formatot);
			$worksheet2->write($i+1,5,$results[$i]['baseType'],$formatot);
			$worksheet2->write($i+1,6,$results[$i]['drawer'],$formatot);
			$worksheet2->write($i+1,7,$results[$i]['overallDimension'],$formatot);
			$worksheet2->write($i+1,8,$results[$i]['costOfGood'],$formatot);
			$worksheet2->write($i+1,9,$results[$i]['costOfBoxes'],$formatot);
			$worksheet2->write($i+1,10,$results[$i]['cost_of_packing'],$formatot);
			$worksheet2->write($i+1,11,$results[$i]['special_case_cost'],$formatot);
			$worksheet2->write($i+1,12,"=round(((Y".($i+2)."/100)*5),2)",$formatot);
			$worksheet2->write($i+1,13,"=round((I".($i+2)."+J".($i+2)."+K".($i+2)."+M".($i+2)."),2)",$formatot);
			$worksheet2->write($i+1,14,$freightType,$formatot);
			$worksheet2->write($i+1,15,$results[$i]['weight'],$formatot);
			$worksheet2->write($i+1,16,$results[$i]['to20Kilos'],$formatot);
//			$worksheet2->write($i+1,15,$results[$i]['additionalKgs'],$formatot);
			$worksheet2->write($i+1,17,"=if(P".($i+2)." > 20 , P".($i+2)."-20,0)",$formatot);
			$worksheet2->write($i+1,18,$results[$i]['ratePKilo'],$formatot);
			$worksheet2->write($i+1,19,$results[$i]['cpdTohouse'],$formatot);
			$worksheet2->write($i+1,20,$specialDelivery,$formatot);
//			$worksheet2->write($i+1,18,$results[$i]['totalDeliveryCost'],$formatot);
//			$worksheet2->write($i+1,20,"=round((R".($i+2)."*S".($i+2).")+Q".($i+2)."+T".($i+2).",2)",$formatot);
			$worksheet2->write($i+1,21,"= if(L".($i+2)." > 0 ,L".($i+2).",round((R".($i+2)."*S".($i+2).")+Q".($i+2)."+T".($i+2).",2))",$formatot); // Total Delivery Cost
			$worksheet2->write($i+1,22,"=round((V".($i+2)."+N".($i+2)."),2)",$formatot); // Total cost
			$worksheet2->write($i+1,23,"=round(Y".($i+2)."-(Y".($i+2)."/1.175),2)",$formatot); // vat
			$worksheet2->write($i+1,24,$results[$i]['retailIncVat'],$formatot);
			$worksheet2->write($i+1,25,"=round((Y".($i+2)."/1.175)-W".($i+2).",2)",$formatot); // margin in pounds
			$worksheet2->write($i+1,26,"=round((Z".($i+2)."/(Y".($i+2)."/1.175)*100),2)",$formatot); // margin in percent
			$worksheet2->write($i+1,27,$results[$i]['supEmail'],$formatot);
			$worksheet2->write($i+1,28,$autoOrder,$formatot);
			$worksheet2->write($i+1,29,$results[$i]['numberOfLots'],$formatot);
			$worksheet2->write($i+1,30,$results[$i]['leadTime'],$formatot);
			$worksheet2->write($i+1,31,$pronto,$formatot);
			$worksheet2->write($i+1,32,$tibs,$formatot);
			$worksheet2->write($i+1,33,$results[$i]['desCollection'],$formatot);
			$worksheet2->write($i+1,34,$results[$i]['desModel'],$formatot);
			$worksheet2->write($i+1,35,$results[$i]['no_of_items'],$formatot);
			$worksheet2->write($i+1,36,$results[$i]['photo1'],$formatot);
			$worksheet2->write($i+1,37,$results[$i]['photo2'],$formatot);
			$worksheet2->write($i+1,38,$results[$i]['photo3'],$formatot);
		} //end for
	}
	else if(stristr($cat_name,"sofa")) //Sofa
	{
		for($i=0; $i<count($results); $i++)
		{

			$autoOrder = $results[$i]['autoOrder'] == 1?'Yes':'No';
			$specialDelivery=$results[$i]['specialdelivery'] == 1?'Yes':'No';
			$pronto = $results[$i]['pronto'] == 1?'Yes':'No';
			$tibs = $results[$i]['tibs'] == 1?'Yes':'No';
			$freightType= isset($freight_type[$results[$i]['FreightType']])? $freight_type[$results[$i]['FreightType']]:'';

			$worksheet2->write($i+1,0,$results[$i]['pcode'],$formatot);
			$worksheet2->write($i+1,1,$results[$i]['product_status'],$formatot);
			$worksheet2->write($i+1,2,$results[$i]['supplier'],$formatot);
			$worksheet2->write($i+1,3,$results[$i]['collection'],$formatot);
			$worksheet2->write($i+1,4,$results[$i]['model'],$formatot);
			$worksheet2->write($i+1,5,$results[$i]['type'],$formatot);
			$worksheet2->write($i+1,6,$results[$i]['size'],$formatot);
			$worksheet2->write($i+1,7,$results[$i]['mattressType'],$formatot);
			$worksheet2->write($i+1,8,$results[$i]['leftright'],$formatot);
			$worksheet2->write($i+1,9,$results[$i]['colour'],$formatot);
			$worksheet2->write($i+1,10,isset($results[$i]['colour2'])?$results[$i]['colour2']:'',$formatot);
			$worksheet2->write($i+1,11,$results[$i]['overallDimension'],$formatot);
			$worksheet2->write($i+1,12,$results[$i]['sizeOpen'],$formatot);
			$worksheet2->write($i+1,13,$results[$i]['sizeSleepingSurface'],$formatot);
			$worksheet2->write($i+1,14,$results[$i]['cover'],$formatot);
			$worksheet2->write($i+1,15,$results[$i]['costOfGood'],$formatot);
			$worksheet2->write($i+1,16,$results[$i]['costOfBoxes'],$formatot);
			$worksheet2->write($i+1,17,$results[$i]['cost_of_packing'],$formatot);
			$worksheet2->write($i+1,18,$results[$i]['special_case_cost'],$formatot);
			$worksheet2->write($i+1,19,"=round(((AF".($i+2)."/100)*5),2)",$formatot);
			$worksheet2->write($i+1,20,"=round((P".($i+2)."+Q".($i+2)."+R".($i+2)."+T".($i+2)."),2)",$formatot);
			$worksheet2->write($i+1,21,$freightType,$formatot);
			$worksheet2->write($i+1,22,$results[$i]['weight'],$formatot);
			$worksheet2->write($i+1,23,$results[$i]['to20Kilos'],$formatot);
//			$worksheet2->write($i+1,22,$results[$i]['additionalKgs'],$formatot);
			$worksheet2->write($i+1,24,"=if(W".($i+2)." > 20 , W".($i+2)."-20,0)",$formatot);
			$worksheet2->write($i+1,25,$results[$i]['ratePKilo'],$formatot);
			$worksheet2->write($i+1,26,$results[$i]['cpdTohouse'],$formatot);
			$worksheet2->write($i+1,27,$specialDelivery,$formatot);
//			$worksheet2->write($i+1,25,$results[$i]['totalDeliveryCost'],$formatot);
//			$worksheet2->write($i+1,27,"=round((Y".($i+2)."*Z".($i+2).")+X".($i+2)."+AA".($i+2).",2)",$formatot);
			$worksheet2->write($i+1,28,"= if(S".($i+2)." > 0 ,S".($i+2).",round((Y".($i+2)."*Z".($i+2).")+X".($i+2)."+AA".($i+2).",2))",$formatot); // Total Delivery Cost
			$worksheet2->write($i+1,29,"=round((U".($i+2)."+AC".($i+2)."),2)",$formatot); // Total cost
			$worksheet2->write($i+1,30,"=round(AF".($i+2)."-(AF".($i+2)."/1.175),2)",$formatot); // vat
			$worksheet2->write($i+1,31,$results[$i]['retailIncVat'],$formatot);
			$worksheet2->write($i+1,32,"=round((AF".($i+2)."/1.175)-AD".($i+2).",2)",$formatot); // margin in pounds
			$worksheet2->write($i+1,33,"=round((AG".($i+2)."/(AF".($i+2)."/1.175)*100),2)",$formatot); // margin in percent
			$worksheet2->write($i+1,34,$results[$i]['supEmail'],$formatot);
			$worksheet2->write($i+1,35,$autoOrder,$formatot);
			$worksheet2->write($i+1,36,$results[$i]['numberOfLots'],$formatot);
			$worksheet2->write($i+1,37,$results[$i]['leadTime'],$formatot);
			$worksheet2->write($i+1,38,$pronto,$formatot);
			$worksheet2->write($i+1,39,$tibs,$formatot);
			$worksheet2->write($i+1,40,$results[$i]['desCollection'],$formatot);
			$worksheet2->write($i+1,41,$results[$i]['desModel'],$formatot);
			$worksheet2->write($i+1,42,$results[$i]['no_of_items'],$formatot);
			$worksheet2->write($i+1,43,$results[$i]['photo1'],$formatot);
			$worksheet2->write($i+1,44,$results[$i]['photo2'],$formatot);
			$worksheet2->write($i+1,45,$results[$i]['photo3'],$formatot);
		} //end for
	}
} else //Export fields for all Accessories Categories
{

	for($i=0; $i<count($results); $i++)
	{

		$autoOrder = $results[$i]['autoOrder'] == 1?'Yes':'No';
		$specialDelivery=$results[$i]['specialdelivery'] == 1?'Yes':'No';
		$pronto = $results[$i]['pronto'] == 1?'Yes':'No';
		$tibs = $results[$i]['tibs'] == 1?'Yes':'No';
		$freightType= isset($freight_type[$results[$i]['FreightType']])? $freight_type[$results[$i]['FreightType']]:'';
		$waterproof = $results[$i]['waterproof'] == 1?'Yes':'No';


		$worksheet2->write($i+1,0,$results[$i]['pcode'],$formatot);
		$worksheet2->write($i+1,1,$results[$i]['product_status'],$formatot);
		$worksheet2->write($i+1,2,$results[$i]['supplier'],$formatot);
		$worksheet2->write($i+1,3,$results[$i]['collection'],$formatot);
		$worksheet2->write($i+1,4,$results[$i]['model'],$formatot);
		$worksheet2->write($i+1,5,$results[$i]['fillings'],$formatot);
		$worksheet2->write($i+1,6,$results[$i]['size'],$formatot);
		$worksheet2->write($i+1,7,$results[$i]['finish'],$formatot);
		$worksheet2->write($i+1,8,$results[$i]['cover'],$formatot);
		$worksheet2->write($i+1,9,$results[$i]['colour'],$formatot);
		$worksheet2->write($i+1,10,$results[$i]['fabricType'],$formatot);
		$worksheet2->write($i+1,11,$waterproof,$formatot);
		$worksheet2->write($i+1,12,$results[$i]['costOfGood'],$formatot);
		$worksheet2->write($i+1,13,$results[$i]['costOfBoxes'],$formatot);
		$worksheet2->write($i+1,14,$results[$i]['cost_of_packing'],$formatot);
			$worksheet2->write($i+1,15,$results[$i]['special_case_cost'],$formatot);
//		$worksheet2->write($i+1,13,$results[$i]['creditCardCharge'],$formatot);
		$worksheet2->write($i+1,16,"=round(((AC".($i+2)."/100)*5),2)",$formatot);
//		$worksheet2->write($i+1,14,$results[$i]['costWithoutFreight'],$formatot);
		$worksheet2->write($i+1,17,"=round((M".($i+2)."+N".($i+2)."+O".($i+2)."+Q".($i+2)."),2)",$formatot);
		$worksheet2->write($i+1,18,$freightType,$formatot);
		$worksheet2->write($i+1,19,$results[$i]['weight'],$formatot);
		$worksheet2->write($i+1,20,$results[$i]['to20Kilos'],$formatot);
//		$worksheet2->write($i+1,19,$results[$i]['additionalKgs'],$formatot);
		$worksheet2->write($i+1,21,"=if(T".($i+2)." > 20 , T".($i+2)."-20,0)",$formatot);
		$worksheet2->write($i+1,22,$results[$i]['ratePKilo'],$formatot);
		$worksheet2->write($i+1,23,$results[$i]['cpdTohouse'],$formatot);
		$worksheet2->write($i+1,24,$specialDelivery,$formatot);
		$worksheet2->write($i+1,25,"= if(P".($i+2)." > 0 ,P".($i+2).",round((V".($i+2)."*W".($i+2).")+U".($i+2)."+X".($i+2).",2))",$formatot); // Total Delivery Cost
		$worksheet2->write($i+1,26,"=round((R".($i+2)."+Z".($i+2)."),2)",$formatot); // total cost
		$worksheet2->write($i+1,27,"=round(AC".($i+2)."-(AC".($i+2)."/1.175),2)",$formatot); // Vat
		$worksheet2->write($i+1,28,$results[$i]['retailIncVat'],$formatot);
		$worksheet2->write($i+1,29,"=round((AC".($i+2)."/1.175)-AA".($i+2).",2)",$formatot); // Margin in pounds
		$worksheet2->write($i+1,30,"=round((AD".($i+2)."/(AC".($i+2)."/1.175)*100),2)",$formatot); // Margin in percent
		$worksheet2->write($i+1,31,$results[$i]['supEmail'],$formatot);
		$worksheet2->write($i+1,32,$autoOrder,$formatot);
		$worksheet2->write($i+1,33,$results[$i]['numberOfLots'],$formatot);
		$worksheet2->write($i+1,34,$results[$i]['leadTime'],$formatot);
		$worksheet2->write($i+1,35,$pronto,$formatot);
		$worksheet2->write($i+1,36,$tibs,$formatot);
		$worksheet2->write($i+1,37,$results[$i]['desCollection'],$formatot);
		$worksheet2->write($i+1,38,$results[$i]['desModel'],$formatot);
		$worksheet2->write($i+1,39,$results[$i]['no_of_items'],$formatot);
		$worksheet2->write($i+1,40,$results[$i]['photo1'],$formatot);
		$worksheet2->write($i+1,41,$results[$i]['photo2'],$formatot);
		$worksheet2->write($i+1,42,$results[$i]['photo3'],$formatot);
	} //end for

}

//end if


/* Close workbook  and exit excel */
$workbook->close();


?>