<?php

   include_once('includes/application_top.php');
   include_once(EXPORT_LIB . "Writer.php");

  $rows = 0;
  $search = ' where 1 ';
  if (isset($HTTP_GET_VARS['search']) && tep_not_null($HTTP_GET_VARS['search'])) {
     $keywords = tep_db_input(tep_db_prepare_input($HTTP_GET_VARS['search']));
     $search .= " AND ( title like '%" . $keywords . "%' ) ";
  }

  $store_query_raw = "select * from " . TABLE_STORE .  $search." order by store_id";
 
  $stores_query = tep_db_query($store_query_raw);
  while ($stores = tep_db_fetch_array($stores_query)) {
    $results[]=$stores;
	}

  $Heading =array('Store Name','Website','E-Mail Address','Address1','Address2','Phone','City','State','Zip','Country');
 
if(count($results)>0 && $HTTP_GET_VARS['action']=="export")
{
	//:::::::::PATH SETTING FOR THE EXCEL FILE:::::::::::::::::::::::::::::::::::::::::\\
	// Creating a workbook
	$filename = "UltraPRO-Stores-List.xls";
	$cat_name="stores";
	$workbook = new Spreadsheet_Excel_Writer();
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
		
		$worksheet2->write($i+1,0,$results[$i]['title'],$formatot);
		$worksheet2->write($i+1,1,$results[$i]['url'],$formatot);
		$worksheet2->write($i+1,2,$results[$i]['email'],$formatot);
		$worksheet2->write($i+1,3,$results[$i]['address1'],$formatot);
		$worksheet2->write($i+1,4,$results[$i]['address2'],$formatot);
		$worksheet2->write($i+1,5,$results[$i]['phone'],$formatot);
		$worksheet2->write($i+1,6,$results[$i]['city'],$formatot);
		$worksheet2->write($i+1,7,$results[$i]['state'],$formatot);
		$worksheet2->write($i+1,8,$results[$i]['zip'],$formatot);
		$worksheet2->write($i+1,9,$results[$i]['country'],$formatot);
		
	}
	
/* Close workbook  and exit excel */
$workbook->close();
}else{
	 tep_redirect(tep_href_link(FILENAME_STORE));
}
?>