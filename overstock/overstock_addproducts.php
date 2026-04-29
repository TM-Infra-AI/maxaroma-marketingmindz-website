<?php
set_time_limit(0);
ini_set('memory_limit',"500M");

	include_once("/home/maxaroma/public_html/lib/config_setting.php");
	//include_once("../lib/configuration.php");

	//~ error_reporting(E_ALL);
	//~ ini_set('display_errors',1);
	
	include_once("/home/maxaroma/public_html/classes/general.cls.php");
	require_once("/home/maxaroma/public_html/classes/mysql.cls.php");
	
	$obj = new MySqlClass;
	$generalobj	= new General($obj,$smarty);
	//echo "<pre>dd";print_r($generalobj);exit;
	
	$rxmlstr= "";
	$start_limit 		= 0;
	//~ $end_limit			= 500;
	$end_limit			= 10;

	$total_batch		= 0;
	$total_record_batch	= 0; 
	
	$fetch = $generalobj->getSystemProductPrice_Cron("`" . TABLE_PREFIX . "products`");
	$retail_price_check =  $generalobj->getSystemProductRetailPrice_Cron("`" . TABLE_PREFIX . "products`");
	$current_stock_check = $generalobj->getSystemStockCheck_Cron("`" . TABLE_PREFIX . "products`");
	$website_stock		 = $generalobj->getSystemWebsiteCurrentStock_Cron("`" . TABLE_PREFIX . "products`");
	$StockAvail          = $generalobj->getSystemStockAvalilable_Cron("`" . TABLE_PREFIX . "products`");
	
	
	$sql = " SELECT DISTINCT(`" . TABLE_PREFIX . "products`.sku) ,".$fetch." ";
	$sql .= " FROM `" . TABLE_PREFIX . "products`, `" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m " . $extraTables;
	$sql .= " WHERE 1 ";
	//$sql .= " AND `" . TABLE_PREFIX . "products`.status='1' ";
	$sql .= " AND `" . TABLE_PREFIX . "category`.status='1' ";
	$sql .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
	$sql .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
	$sql .="  AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
	$sql .=" Group By `" . TABLE_PREFIX . "products`.sku ";	
	$sql .=" having  product_price > 0  LIMIT $start_limit,$end_limit";
	$res_total = $obj->select($sql);	
	
	//echo "<pre>";print_r($res_total);exit;
	
	$total_prod			 = count($res_total);
	$total_record_batch  = ceil($total_prod/$end_limit);

	if($total_prod > 0)
	{
		$item_xml_data = '';
		$item_xml_data1 = '';
		$item_xml_data2	= '';
		for($b=0; $b < $total_record_batch; $b++ )
		{
			 $sel_pro = " SELECT DISTINCT(`" . TABLE_PREFIX . "products`.sku),`" . TABLE_PREFIX . "category`.parent_id,";
			 $sel_pro .= " `" . TABLE_PREFIX . "products_category`.category_id, ";
			 $sel_pro .= " `" . TABLE_PREFIX . "products`.sku, ";
			 $sel_pro .= " `" . TABLE_PREFIX . "products`.image, ";
			 $sel_pro .= " `" . TABLE_PREFIX . "products`.UPC, ";
			 $sel_pro .= " `" . TABLE_PREFIX . "products`.short_description, ";
			 $sel_pro .= " `" . TABLE_PREFIX . "products`.product_description, ";
			 $sel_pro .= " `" . TABLE_PREFIX.  "products`.gender, "; 
			 $sel_pro .= " `" . TABLE_PREFIX.  "products`.size, "; 
			 $sel_pro .= " `" . TABLE_PREFIX.  "products`.vtype, "; 
			 $sel_pro .= " `" . TABLE_PREFIX.  "products`.product_name, ";
			 //$sel_pro .= " `" . TABLE_PREFIX.  "products`.current_stock, ";
			 $sel_pro .= " `" . TABLE_PREFIX.  "products`.status, ";
			 $sel_pro .= " m.vmanufacture, "; 	
		
			 $sel_pro .= " `" . TABLE_PREFIX.  "products`.brand_id ,";
			 $sel_pro .= $fetch.",".$retail_price_check.",".$current_stock_check.",".$StockAvail.",".$website_stock;

			 $sel_pro .= " FROM `" . TABLE_PREFIX . "products`, `" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m " . $extraTables;
			 $sel_pro .= " WHERE 1 ";
			// $sel_pro .= " AND `" . TABLE_PREFIX . "products`.status='1' ";
			 $sel_pro .= " AND `" . TABLE_PREFIX . "category`.status='1' ";
			 $sel_pro .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
			 $sel_pro .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
			 $sel_pro .="  AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
			
			 $sel_pro .=" Group By `" . TABLE_PREFIX . "products`.sku"; 
			 $sel_pro .= " HAVING product_price > 0";
			 $sel_pro .=" LIMIT $start_limit,$end_limit";

			 $prodRes = $obj->select($sel_pro);

			echo "<pre>";print_r($prodRes);exit;
			$TotalProducts = count($prodRes);

			$item_xml_data = '';
			$item_xml_data1 = '';
			$item_xml_data2	= '';	
			
			for($i=0;$i<$TotalProducts;$i++)
			{
				if(file_exists(PRD_LARGE_IMG_PATH . $prodRes[$i]['image']) and ! empty($prodRes[$i]['image'])) {
					$mainImageUrl = PRD_LARGE_IMG_URL.$prodRes[$i]['image'];

				} else {
					//continue;
					$mainImageUrl = NO_IMAGE_LARGE;
						
				}
				$image_url  = $mainImageUrl;
				 

				if($prodRes[$i]['status']=='0')
				{
					$prodRes[$i]['current_stock'] = 0;
				}
			
				if($prodRes[$i]['current_stock'] <=0 )
				{
					$prodRes[$i]['current_stock'] = 0;
				}
				if($prodRes[$i]['product_price'] <=0 )
				{
					$prodRes[$i]['current_stock'] = 0;
				}
			 		 
				$current_stock = $prodRes[$i]['current_stock'];
			 
				if($current_stock<=0)
				{
					$current_stock = 0;
				}
				
				$prodRes[$i]['current_stock'] = 0;
				
				if($prodRes[$i]['status']=='1')
				{
					$partnerSku = $prodRes[$i]['sku'];
					$name = removeSpCharacters(trim(htmlentities(ucwords($prodRes[$i]['product_name']))));
					
					$description = $prodRes[$i]['short_description'];
					if($description == ""){
						$description = $prodRes[$i]['product_description'];
					}
					if($description == ""){
						$description = $prodRes[$i]['product_name'];
					}
					$description = removeSpCharacters(trim(htmlentities(ucwords($description))));
					
					$upc = $prodRes[$i]['UPC'];
					
					$mfgPartNumber = $partnerSku;
					$length = $prodRes[$i]['sku'];
					
					$item_xml_data .= '<itemMaster>';
											
					$item_xml_data .= setParameter("partnerSku",$partnerSku);
					$item_xml_data .= setParameter("name",$name);
					$item_xml_data .= setParameter("description",$description);
					$item_xml_data .= setParameter("upc",$upc);
					$item_xml_data .= setParameter("mfgPartNumber",$mfgPartNumber);
						
					//product dimensions
						/*
						$item_xml_data .= '<dimensions>';
							$item_xml_data .= setParameter("length",$prodRes[$i]['image']);
							$item_xml_data .= '<lengthUnitOfMeasure>';
								$item_xml_data .= setParameter("code",$prodRes[$i]['image']);
							$item_xml_data .= '</lengthUnitOfMeasure>';
							
							$item_xml_data .= setParameter("width",$prodRes[$i]['image']);
							$item_xml_data .= '<widthUnitOfMeasure>';
								$item_xml_data .= setParameter("code",$prodRes[$i]['image']);
							$item_xml_data .= '</widthUnitOfMeasure>';
							
							$item_xml_data .= setParameter("height",$prodRes[$i]['image']);
							$item_xml_data .= '<heightUnitOfMeasure>';
								$item_xml_data .= setParameter("code",$prodRes[$i]['image']);
							$item_xml_data .= '</heightUnitOfMeasure>';
						$item_xml_data .= '</dimensions>';
						 
						$item_xml_data .= '<weight>';
							$item_xml_data .= setParameter("weight",$prodRes[$i]['image']);
							$item_xml_data .= '<weightUnitOfMeasure>';
								$item_xml_data .= setParameter("code",$prodRes[$i]['image']);
							$item_xml_data .= '</weightUnitOfMeasure>';
						$item_xml_data .= '</weight>';	
						
						$item_xml_data .= setParameter("quantityInASellableUnit",$quantityInASellableUnit);				
						$item_xml_data .= '<sellableUnitOfMeasure>';
							$item_xml_data .= setParameter("code",$prodRes[$i]['image']);
						$item_xml_data .= '</sellableUnitOfMeasure>';			
						
						$item_xml_data .= setParameter("specialHandlingInstructions",$prodRes[$i]['image']);	
						*/
					//product dimensions
					
					
					$status = ($prodRes[$i]['status'] == "1") ? "ACTIVE" : "INACTIVE";
								
					$item_xml_data .= setParameter("replacementCost",$prodRes[$i]['product_price']);	
					$item_xml_data .= setParameter("status",$status);	
					
					/*
						$item_xml_data .= setParameter("hazmatCode",$prodRes[$i]['image']);	
						$item_xml_data .= '<htsCode>';
							$item_xml_data .= setParameter("code",$prodRes[$i]['image']);
						$item_xml_data .= '</htsCode>';
						
						$item_xml_data .= setParameter("eccnNumber",$prodRes[$i]['image']);	
					*/
						
					$item_xml_data .= setParameter("receivingThreshold",$prodRes[$i]['current_stock']);	
					$item_xml_data .= setParameter("isLTL",false);	
											
					$item_xml_data .= '</itemMaster>';
				}
			}
		}
	}
	$item_xml_data		= xmlEscape($item_xml_data);
	
	if($item_xml_data!=''){
		$post_string  		 = '<?xml version="1.0" encoding="UTF-8"?>
								<itemMasterMessage xmlns="api.supplieroasis.com">';
		$post_string 	.= $item_xml_data;
		$post_string .= '</itemMasterMessage>'; 
	}
	
	echo $post_string;//exit;		
	
	/*$requestxml = '<?xml version="1.0" encoding="UTF-8"?>
			<itemMasterMessage xmlns="api.supplieroasis.com">
				<itemMaster>
					<partnerSku>123-456-789</partnerSku>
					<name>name</name>
					<description>description</description>
					<upc>123456789012</upc>
					<mfgPartNumber>P13455</mfgPartNumber>
					<dimensions>
						<length>2.0</length>
						<lengthUnitOfMeasure>
							<code>INCHES</code>
						</lengthUnitOfMeasure>
						<width>2.0</width>
						<widthUnitOfMeasure>
							<code>INCHES</code>
						</widthUnitOfMeasure>
						<height>2.0</height>
						<heightUnitOfMeasure>
							<code>INCHES</code>
						</heightUnitOfMeasure>
					</dimensions>
					<weight>
						<weight>5</weight>
						<weightUnitOfMeasure>
							<code>OUNCES</code>
						</weightUnitOfMeasure>
					</weight>
					<quantityInASellableUnit>1</quantityInASellableUnit>
					<sellableUnitOfMeasure>
						<code>CASE</code>
					</sellableUnitOfMeasure>
					<specialHandlingInstructions>Don\'t crush</specialHandlingInstructions>
					<replacementCost>189.95</replacementCost>
					<status>ACTIVE</status>
					<hazmatCode>hazardous</hazmatCode>
					<htsCode>
						<code>5</code>
					</htsCode>
					<eccnNumber>eccn 1</eccnNumber>
					<receivingThreshold>20</receivingThreshold>
					<isLTL>false</isLTL>
				</itemMaster>
			</itemMasterMessage>';*/
	
	
	$user_name = "peraromallc";
	$pass_code = "Welcome20!";
	$auth_str = base64_encode($user_name.":".$pass_code); 
	
	$ch = curl_init('https://api.test.supplieroasis.com/catalog'); //for testing

	//echo "<br>==".$auth_str;exit;
	
	//curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml',
			'authorization: Basic '.$auth_str)); 
	curl_setopt($ch, CURLOPT_TIMEOUT, 100);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
	
	//execute post
	$result = curl_exec($ch);
	
	echo "<br>";echo "Result--<br><pre>"; print_r($result);
	
	curl_close($ch); // close cURL handler
	
	## Store Response ID from XML data in Table ##
	$rxml = simplexml_load_string($result);			
	
	$rxmlstr = json_encode($rxml);
	
	$result_array	= json_decode($rxmlstr, TRUE);
	
	$rxmlstr .= "<br>==============<br><br>";	
	
	echo "<pre>Result Array::";print_r($result_array);exit;
	/*
	foreach( $rxml as $response )
	{ 				
		$response_id = $response;
		$response_doc_id = $response['document-id'];
		$response_xml = $rxml;
		$result = $result;
		$error_info = $response['report']['detail']['errors']['error']['error-info'];
		if($error_info!='')	{
			$stringData = chr(13) .$response_id. chr(13);
			$stringData .= chr(13) .$error_info. chr(13);
			$myFile = 'sears_error_response.txt';
			$fh1 = fopen($myFile, 'a+');
			fwrite($fh1, $stringData);					
		}
	}					
	$InsertResponse = array(
							'response_id' 	=> $response_id,
							'response_doc_id' 	=> $response_doc_id,
							'response_xml' 	=> $response_xml,
							'result' 	=> $result
						 );
	$obj->insert(TABLE_PREFIX.'sears_response', $InsertResponse);	
*/


function removeSpCharacters ($data)
{
	
	$data = str_replace("<br />","",trim($data));
	$data = str_replace("<br>","",trim($data));
	$data = str_replace("\n","",trim($data));
	$data = str_replace("\r","",trim($data));
	$data = str_replace("\t","",trim($data));
	$data = str_replace('"',"",trim($data));
	$data = str_replace("&amp;","and",trim($data));
	$data = str_replace("+","and",trim($data));
	$data = str_replace("&","and",trim($data));
	$data = str_replace("&eacute;","e",trim($data));
	$data = str_replace("andeacute;","e",trim($data));
	$data = str_replace("é","e",trim($data));
	$data = str_replace("andEacute;","E",trim($data));
	$data = str_replace("&Eacute;","E",trim($data));
	$data = str_replace("É","E",trim($data));
	$data = str_replace("É","E",trim($data));
	$data = str_replace("","",trim($data));	
	
	return $data;
}
function normalizeString($str)
{
	$special_chars = array("~","`","!","^","&","*","®",""); 
	return str_replace($special_chars,"",$str);
}

function setParameter($param,$value)
{
	$param_tag = '';
	$param_open_tag = '';
	$param_close_tag = '';
	
	$param = trim($param);
    $value = trim($value);
    $value = normalizeString($value);
	
	$param_open_tag = "<".$param.">";
	$param_close_tag = "</".$param.">";
	
    $param_tag = $param_open_tag.$value.$param_close_tag;
	
	return $param_tag;
}
function xmlEscape($string)
{
	return str_replace(array('&'), array('&amp;'), $string);
}
?>
