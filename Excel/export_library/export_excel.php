<?php

  require('../includes/application_top.php');
include_once "Writer.php";


 if (isset($HTTP_GET_VARS['page']) && ($HTTP_GET_VARS['page'] > 1)) $rows = $HTTP_GET_VARS['page'] * MAX_DISPLAY_SEARCH_RESULTS - MAX_DISPLAY_SEARCH_RESULTS;
  $rows = 0;
  $products_query_raw = "select p.products_id, pd.products_name, pd.products_viewed, l.name from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_LANGUAGES . " l where p.products_id = pd.products_id and l.languages_id = pd.language_id order by pd.products_viewed DESC";
  $products_split = new splitPageResults($HTTP_GET_VARS['page'], MAX_DISPLAY_SEARCH_RESULTS, $products_query_raw, $products_query_numrows);
  $products_query = tep_db_query($products_query_raw);
  while ($products = tep_db_fetch_array($products_query)) {
    $results[]=$products;
	}

die;	
		$query = "SELECT pcode,product_status,tibsownlabel,
		( SELECT optionvalue FROM tblbed_option WHERE id = supplier ) AS supplier,
		 collection, model,
		( SELECT optionvalue FROM tblbed_option WHERE id = size ) AS size,
		( SELECT optionvalue FROM tblbed_option	WHERE id = baseType	) AS baseType,
		( SELECT optionvalue FROM tblbed_option WHERE id = mattressType	) AS mattressType,
		  closedDimention,openDimension,
		( SELECT optionvalue FROM tblbed_option WHERE id = framecolor and supplier_id= supplier	) AS framecolor,
		( SELECT optionvalue FROM tblbed_option WHERE id = mattressColor and supplier_id= supplier	) AS mattressColor,
		headboard, legType,costOfGood,costOfBoxes,cost_of_packing,creditCardCharge,costWithoutFreight,FreightType,weight,to20Kilos,additionalKgs,ratePKilo,cpdTohouse,totalDeliveryCost,totalCost,special_case_cost,vat,retailIncVat,marginInPound,marginPercent,supEmail,autoOrder, numberOfLots,leadTime,pronto,tibs, desCollection,desModel,no_of_items,".$glob['dbprefix']."img_idx.img1 as photo1,".$glob['dbprefix']."img_idx.img2 as photo2,".$glob['dbprefix']."img_idx.img3 as photo3  from ".$glob['dbprefix']."inventory left join ".$glob['dbprefix']."img_idx on ".$glob['dbprefix']."img_idx.product_id=".$glob['dbprefix']."inventory.product_id where cat_id=".$cat_id;
		
	$Heading =array('Product Code','Product Status','Tibs Own Label','Supplier','Collection','Model','Size','Base Type','Mattress Type','Dimensions when closed','Dimensions when open','Frame Colour','Mattress Colour','With Headboard','Leg Type','Cost of Goods','Cost of Boxes','Cost of Packing',' Credit Card Charge','Cost without Freight','Freight Type','Weight','£ to 20 Kilos','Additional kgs','Rate per Kilo','CPD to Private House',' Total Delivery Cost','Total Cost','Special Case Cost','VAT','retail inc vat','margin in £££s','margin %','suppliers e mail','Auto Order','Number Of Lots','Lead Time','Tibs','Pronto','Description of Collection','Description Of Model','No. of Items','Photo 1','Photo 2','Photo3');


 

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


		for($i=0; $i<count($results); $i++)
		{
			$withheadboards = $results[$i]['headboard'] == 1?'Yes':'No';
			$autoOrder = $results[$i]['autoOrder'] == 1?'Yes':'No';
			$tibsownlabel = $results[$i]['tibsownlabel'] == 1?'Y':'';
			$pronto = $results[$i]['pronto'] == 1?'Yes':'No';
			$tibs = $results[$i]['tibs'] == 1?'Yes':'No';
			$freightType= isset($freight_type[$results[$i]['FreightType']])? $freight_type[$results[$i]['FreightType']]:'';
			$worksheet2->write($i+1,0,$results[$i]['pcode'],$formatot);
			$worksheet2->write($i+1,1,$results[$i]['product_status'],$formatot);
			$worksheet2->write($i+1,2,$tibsownlabel,$formatot);
			$worksheet2->write($i+1,3,$results[$i]['supplier'],$formatot);
			$worksheet2->write($i+1,4,$results[$i]['collection'],$formatot);
			$worksheet2->write($i+1,5,$results[$i]['model'],$formatot);
			$worksheet2->write($i+1,6,$results[$i]['size'],$formatot);
			$worksheet2->write($i+1,7,$results[$i]['baseType'],$formatot);
			$worksheet2->write($i+1,8,$results[$i]['mattressType'],$formatot);
			$worksheet2->write($i+1,9,$results[$i]['closedDimention'],$formatot);
			$worksheet2->write($i+1,10,$results[$i]['openDimension'],$formatot);
			$worksheet2->write($i+1,11,$results[$i]['framecolor'],$formatot);
			$worksheet2->write($i+1,12,$results[$i]['mattressColor'],$formatot);
			$worksheet2->write($i+1,13,$withheadboards,$formatot);
			$worksheet2->write($i+1,14,$results[$i]['legType'],$formatot);
			$worksheet2->write($i+1,15,$results[$i]['costOfGood'],$formatot);
			$worksheet2->write($i+1,16,$results[$i]['costOfBoxes'],$formatot);
			$worksheet2->write($i+1,17,$results[$i]['cost_of_packing'],$formatot);
			$worksheet2->write($i+1,18,"=round(((AE".($i+2)."/100)*5),2)",$formatot); // Credit card charges
			$worksheet2->write($i+1,19,"=round((P".($i+2)."+Q".($i+2)."+S".($i+2)."+R".($i+2)."),2)",$formatot);  //Cost Without freight
			$worksheet2->write($i+1,20,$freightType,$formatot);
			$worksheet2->write($i+1,21,$results[$i]['weight'],$formatot);
			$worksheet2->write($i+1,22,$results[$i]['to20Kilos'],$formatot);
			$worksheet2->write($i+1,23,"=if(V".($i+2)." > 20 , V".($i+2)."-20,0)",$formatot);  //Additional Kgs
			$worksheet2->write($i+1,24,$results[$i]['ratePKilo'],$formatot);
			$worksheet2->write($i+1,25,$results[$i]['cpdTohouse'],$formatot);
			$worksheet2->write($i+1,26,"= if(AC".($i+2)." > 0 ,AC".($i+2).",round((X".($i+2)."*Y".($i+2).")+W".($i+2)."+Z".($i+2).",2))",$formatot); // Total Delivery Cost
			$worksheet2->write($i+1,27,"=round((T".($i+2)."+AA".($i+2)."),2)",$formatot); // Total Cost
			$worksheet2->write($i+1,28,$results[$i]['special_case_cost'],$formatot);
			$worksheet2->write($i+1,29,"=round(AE".($i+2)."-(AE".($i+2)."/1.175),2)",$formatot); // Vat
			$worksheet2->write($i+1,30,$results[$i]['retailIncVat'],$formatot);
			$worksheet2->write($i+1,31,"=round((AE".($i+2)."/1.175)-AB".($i+2).",2)",$formatot); //Margin in Pounds
			$worksheet2->write($i+1,32,"=round((AF".($i+2)."/(AE".($i+2)."/1.175)*100),2)",$formatot); //Margin in Percent
			$worksheet2->write($i+1,33,$results[$i]['supEmail'],$formatot);
			$worksheet2->write($i+1,34,$autoOrder,$formatot);
			$worksheet2->write($i+1,35,$results[$i]['numberOfLots'],$formatot);
			$worksheet2->write($i+1,36,$results[$i]['leadTime'],$formatot);
			$worksheet2->write($i+1,37,$tibs,$formatot);
			$worksheet2->write($i+1,38,$pronto,$formatot);
			$worksheet2->write($i+1,39,$results[$i]['desCollection'],$formatot);
			$worksheet2->write($i+1,40,$results[$i]['desModel'],$formatot);
			$worksheet2->write($i+1,41,$results[$i]['no_of_items'],$formatot);
			$worksheet2->write($i+1,42,$results[$i]['photo1'],$formatot);
			$worksheet2->write($i+1,43,$results[$i]['photo2'],$formatot);
			$worksheet2->write($i+1,44,$results[$i]['photo3'],$formatot);
		}
	

//end if

/* Close workbook  and exit excel */
$workbook->close();


?>