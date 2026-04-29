<?php
set_time_limit(0);
include_once("/home/maxaroma/public_html/lib/config_setting.php");
define("EXPORT_CSV_PEPPERJAM_PATH","/home/maxaroma/public_html/pepperjam/");
function add_special_characters($data) {
		 //$data = str_replace(",","@",trim($data));
		 //$data = str_replace(";","*",trim($data));
		 //$data = str_replace("'","(",trim($data));
		 return $data;
}
function getcategorydetails($products_id) {
		 global $obj;
		 $catres = $obj->select("SELECT category_id FROM ".TABLE_PREFIX."products_category
	 									WHERE products_id = '".$products_id."' 
								   	    ORDER BY category_id"
						  	   );
		 if($catres)
				return $catres;
		 else
				return false;
}

function getcatstructure($category_id) {
		 global $obj;
		 if(!empty($category_id) or $$category_id != '') {
				$db_sql = $obj->select("SELECT parent_id, category_name
											   FROM ".TABLE_PREFIX."category 
											   WHERE category_id = '$category_id'"
					   				  );
								
				$parent_id = $db_sql[0]["parent_id"];
				$vcategory = trim($db_sql[0]["category_name"]);
				if(count($db_sql)>0) {
						while($parent_id!='0') {
							  $db_sql = getparents($parent_id);
							  $parent_id = $db_sql[0]["parent_id"];
							  $parentcategory = trim($db_sql[0]["category_name"]);
							  $vcategory = $parentcategory.":".$vcategory;
							  //if(!empty($category_name)) $vcategory .= ":".$category_name;
						}
				}
		 }
		 return $vcategory;
}

function getparents($category_id) {
		 global $obj;
		 $db_sql = $obj->select("SELECT category_id,parent_id, category_name
										FROM ".TABLE_PREFIX."category 
								   	    WHERE category_id = '".$category_id."'"
	  					       );
		 return $db_sql;
}

$start_limit = 0;
$end_limit   = 50;

$total_batch = 0;
$total_record_batch	= 0;


$export_file_name = "PepperJam_Product_Export.txt";
$export_file_path = EXPORT_CSV_PEPPERJAM_PATH.$export_file_name;
if(file_exists($export_file_path))
{
	unlink($export_file_path); 
}


		

//#################### MAil sent after completed////////////////////////////
$subject = "Pepperjam Product file created for maxaroma";
$headers = "From: cron@maxraoma.com\n" . "MIME-Version: 1.0\n" . "Content-type: text/html; charset=iso-8859-1";
$sendmessage = "PepperJam_Product_Export.txt is Pepperjam Product Export ftp is in process start on server.<br>";
$sendmessage = $sendmessage;		
$onesendstat = mail("naresh.qualdev@gmail.com", $subject, $sendmessage, $headers);
///######################## Mail sent after completed///////////////////////



$sql = " SELECT DISTINCT (`" . TABLE_PREFIX . "products`.products_id) ";
$sql .= " FROM `" . TABLE_PREFIX . "products`,`" . TABLE_PREFIX . "products_one` ,`" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m,`".TABLE_PREFIX."brand` as b  ";
$sql .= " WHERE 1 ";
$sql .= " AND `" . TABLE_PREFIX . "products`.status='1' ";
$sql .= " AND `" . TABLE_PREFIX . "category`.status='1' ";
$sql .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
$sql .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
$sql .="  AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
$sql .="  AND `".TABLE_PREFIX."products`.imanufactureid = b.imanufactureid ";
$sql .= " AND `".TABLE_PREFIX."products`.products_id = `".TABLE_PREFIX."products_one`.products_id ";
$sql .="  AND `" . TABLE_PREFIX . "products`.brand_id = b.brand_id GROUP BY `" . TABLE_PREFIX . "products`.products_id ";
$res_total = $obj->select($sql);


		
$res_total = $obj->select($sql);
			
$total_prod	= count($res_total);
$total_record_batch = ceil($total_prod/$end_limit)+1;		
		
/*if(file_exists($export_file_path))
		{unlink($export_file_path); } */	
		
$fp = fopen ($export_file_path, "a+");

//New code add on :: 12-07-2018 :: Start
$fpsec = fopen ($export_fileactions_path, "a+");
//New code add on :: 12-07-2018 :: End

		
/*$header_row = "title\tid\tproduct_type\tcondition\tdescription\timage_link\tlink\tprice\tc:totalweight\tc:manufacturer\tpayment_notes\tquantity\tbrand\tmpn\tupc\tavailability\tgoogle_product_category\n";*/

$header_row = 
"id\ttitle\tdescription\tlink\tprice\tbrand\tcondition\timage_url\tisbn\tmpn\tupc\tweight\tgoogle_product_category\tproduct_type\tavailability\texcluded_destination\n";

fwrite($fp,$header_row);
fclose($fp);


//====== Insert the header =========//
for($b=0;$b<$total_record_batch;$b++) {		
		$prodSQL  = " SELECT `".TABLE_PREFIX."products`.products_id, ";
		$prodSQL  .= " `".TABLE_PREFIX."products`.sku,";
		$prodSQL .= " `".TABLE_PREFIX."products`.short_description,";
		$prodSQL .= " `".TABLE_PREFIX."products`.product_description,";
		$prodSQL .= " `".TABLE_PREFIX."products`.vtype,";
		$prodSQL  .= " `".TABLE_PREFIX."products`.product_type,";
		$prodSQL  .= " `".TABLE_PREFIX."products_one`.walmart_price,";
		$prodSQL  .= " `".TABLE_PREFIX."products_one`.walmart_active_seller_price,";
		$prodSQL  .= " `".TABLE_PREFIX."products`.image,";
		$prodSQL  .= " `".TABLE_PREFIX."products`.UPC,";
		$prodSQL  .= " `".TABLE_PREFIX."products`.size,";
		$prodSQL .= " `" . TABLE_PREFIX. "products`.cosmo_sku ,";
        $prodSQL .= " `" . TABLE_PREFIX. "products`.nandansons_sku ,";
        $prodSQL .= " `" . TABLE_PREFIX. "products`.pca_sku ,";
		$prodSQL  .= "  `".TABLE_PREFIX."products`.product_name as product_name, m.vmanufacture, `".TABLE_PREFIX."products`.vtype, ";
		$prodSQL  .= " `".TABLE_PREFIX."products`.gender, ";
		$prodSQL  .= " `".TABLE_PREFIX."products`.cosmo_current_stock, ";
        $prodSQL  .= " `".TABLE_PREFIX."products`.nandansons_current_stock, ";
        $prodSQL  .= " `".TABLE_PREFIX."products`.pca_current_stock, ";
		$prodSQL  .= " `".TABLE_PREFIX."products_one`.cosmo_walmart_price, ";
		$prodSQL  .= " `".TABLE_PREFIX."products_one`.nandansons_walmart_price, ";
		$prodSQL  .= " `".TABLE_PREFIX."products_one`.pca_walmart_price, ";
		$prodSQL  .= " `".TABLE_PREFIX."products`.our_price,";
		$prodSQL  .= " `".TABLE_PREFIX."products`.sale_price,";
		$prodSQL  .= " `".TABLE_PREFIX."products`.retail_price,";
		$prodSQL  .= " `".TABLE_PREFIX."products`.cosmo_our_price,";
		$prodSQL  .= " `".TABLE_PREFIX."products`.nandansons_our_price,";
		$prodSQL  .= " `".TABLE_PREFIX."products`.pca_our_price,";
		$prodSQL  .= " `".TABLE_PREFIX."products`.cosmo_retail_price,";
		$prodSQL  .= " `".TABLE_PREFIX."products`.nandansons_retail_price,";
		$prodSQL  .= " `".TABLE_PREFIX."products`.pca_retail_price,";
		$prodSQL  .= " `".TABLE_PREFIX."products`.current_stock,";
		$prodSQL  .= " `".TABLE_PREFIX."products`.minimum_stock, ";
		$prodSQL  .= " `".TABLE_PREFIX."products`.image, ";
		$prodSQL  .= " `".TABLE_PREFIX."products_category`.category_id, ";
		$prodSQL  .= " `".TABLE_PREFIX."products_one`.googleshopping_enable , ";
		$prodSQL  .= " `".TABLE_PREFIX."products`.is_atomizer , ";
		$prodSQL  .= " `".TABLE_PREFIX."products`.brand_id ,";
		$prodSQL  .= " b.brand_name ,";
		$prodSQL  .= " `".TABLE_PREFIX."products`.imanufactureid ";
		$prodSQL .= " FROM `".TABLE_PREFIX."products`,`".TABLE_PREFIX."products_one`, `".TABLE_PREFIX."products_category` ,`".TABLE_PREFIX."category`, `".TABLE_PREFIX."manufacture` as m,`".TABLE_PREFIX."brand` as b  ";
		$prodSQL .= " WHERE 1 ";
		$prodSQL .= " AND `".TABLE_PREFIX."products`.status='1' ";
		$prodSQL .= " AND `".TABLE_PREFIX."category`.status='1' ";
		$prodSQL .= " AND `".TABLE_PREFIX."products`.products_id = `".TABLE_PREFIX."products_category`.products_id ";
		$prodSQL .= " AND `".TABLE_PREFIX."products`.products_id = `".TABLE_PREFIX."products_one`.products_id ";
		$prodSQL .= " AND `".TABLE_PREFIX."category`.category_id = `".TABLE_PREFIX."products_category`.category_id ";
		$prodSQL .="  AND `".TABLE_PREFIX."products`.imanufactureid = m.imanufactureid ";
		$prodSQL .="  AND `".TABLE_PREFIX."products`.imanufactureid = b.imanufactureid ";
		$prodSQL .="  AND `".TABLE_PREFIX."products`.brand_id = b.brand_id ";
		$prodSQL .="  GROUP BY `".TABLE_PREFIX."products`.products_id";
		$prodSQL .= " ORDER BY  `".TABLE_PREFIX."products`.display_position ASC ";
		$prodSQL .="  LIMIT $start_limit,$end_limit";
		
		$result = $obj->select($prodSQL);
		
		
		$file_content = ''; $file_contentaction = '';	
		$file_content_ct = '';	
		for($i=0; $i<count($result); $i++) {	
				
				$result[$i]['stock'] = getSystemStockAvalilable($result[$i]);
				$result[$i]['WebsiteStock'] = getMainSystemStockAvalilable($result[$i]['current_stock'],$result[$i]['minimum_stock']);
				$prices = getMainSystemStockProductPrice($result[$i]);
				//echo "<pre>"; print_r($prices); exit;
				$prices = explode("##",$prices);
				
				$result[$i]['product_price'] = $prices[0];
				$result[$i]['current_stock'] = $prices[2];
				$result[$i]['retail_price']  = $prices[1];
				
				if($result[$i]['product_price']<=0)
				{
					continue;
				}
				
				$sku = $result[$i]['sku'];
				$upc = trim($result[$i]['UPC']);
				$mpn = $result[$i]['sku'];
			
				$title = removeSpCharacters($result[$i]['product_name']);
				$title = str_replace("<br />","",$title);
				$title = str_replace("<br>","",$title);
				$title = str_replace("\n","",$title);
				$title = str_replace("\r","",$title);
				$title = str_replace("\t","",$title);
			
			    $title = $title." ".$result[$i]['size'];
				
				$desc = removeSpCharacters($result[$i]['short_description']);
				$desc = str_replace("<br />","",$desc);
				$desc = str_replace("<br>","",$desc);
				$desc = str_replace("\n","",$desc);
				$desc = str_replace("\t","",$desc);
				$desc = str_replace("\r","",$desc);
		
				if (trim($desc) == '')
						 $desc = $title;
		

		
				$manufacturer = trim($result[$i]['vmanufacture']);
				if ($manufacturer == '')
						$manufacturer = "Maxaroma";	
		
				$generalobj = new General($obj,$smarty);
				$prod_link  = $generalobj->getProductRewriteURL($result[$i]['products_id'],$result[$i]['product_name'],$result[$i]['category_id'],$manufacturer);
				
				$prod_link = $prod_link.'?utm_source=google&utm_medium=cpc&utm_campaign=product';
				
				if(file_exists(PRD_NOWATER_IMG_PATH.trim($result[$i]['UPC']).".jpg") and !empty(trim($result[$i]['UPC'])))
				{
					$prod_image = PRD_NOWATER_IMG_URL.trim($result[$i]['UPC']).".jpg";
				}
				elseif(file_exists(PRD_NOWATER_IMG_PATH.trim($result[$i]['image'])) and !empty(trim($result[$i]['image'])))
				{
					$prod_image = PRD_NOWATER_IMG_URL.trim($result[$i]['image']);
				}
				elseif(file_exists(PRD_THUMB_IMG_PATH.trim($result[$i]['image'])) and !empty(trim($result[$i]['image'])))
				{
					$prod_image = PRD_THUMB_IMG_URL.trim($result[$i]['image']);
				}
				else
				{ 
					continue; 
				}
				$prod_image = str_replace("http://","https://",$prod_image);
				$prod_image = str_replace("httpss://","https://",$prod_image);
		


				$condition = "New";	
				$quantity = $result[$i]['current_stock'];
				$sale_price = $result[$i]['product_price'];
				//Added on :: 12-07-2018
				$shoppingsale_price = round(($sale_price*1.10),2);
				//End
					
				$categoryres = getcategorydetails($result[$i]["products_id"]);
				$category_path = '';
		
				$cat_cnt = 0;	
				for($l = 0; $l < count($categoryres); $l++) {
						if(!empty($categoryres[$l]["category_id"]) && $cat_cnt == 0) {
								  $category_path .= getcatstructure($categoryres[$l]["category_id"])."#";
								  $cat_cnt++;
						}	
				}
		
				if (trim($category_path)!='')
						$category_path = substr($category_path,0,-1);
		
				//$category = str_replace(":"," > ",$category_path);
				$category = "Health & Beauty > Personal Care > Cosmetics > Perfume & Cologne";
				$weight = 1;
				
				$brand = '';
				$shipping = ':::0.00';
				
				$tax = 'US::0:';
				if($quantity > 0)
						$quantity = 'in stock';
				else
						$quantity = 'out of stock';
						
				/*$file_content .= $title."\t".$sku."\t".$category."\t".$condition."\t".$desc."\t";
				$file_content .= $prod_image."\t".$prod_link."\t".$sale_price."\t".$weight."\t";
				$file_content .= $manufacturer."\t"."GoogleCheckout\t".$quantity."\t";
				$file_content .= $manufacturer."\t".$mpn."\t".$upc."\t"."in stock\t".$category."\n";*/
				$excluded_destination = "Shopping Actions";
				
				if($result[$i]['googleshopping_enable']=="Yes")
				{
					$excluded_destination = '';
				}
				
				if($result[$i]['is_atomizer']=="Yes")
				{
					$excluded_destination = 'Shopping Actions';
				}
				
				
				
				$file_content .= $sku."\t".$title."\t".$desc."\t".$prod_link."\t".$sale_price."\t";
				$file_content .= $manufacturer."\t".$condition."\t".$prod_image."\t".$isbn."\t";
				$file_content .= $mpn."\t".$upc."\t";
				$file_content .= 
				$weight."\t".$category."\t".$category."\t".$quantity."\t".$excluded_destination."\n";
				


	}
	
	$fp = fopen ($export_file_path, "a+");
	fwrite($fp,$file_content);
	fclose($fp);


	

				
	$start_limit = $start_limit+$end_limit;
	$total_batch = $total_batch+1;
	
	if($total_batch == $total_record_batch ) 
			{$msg = "Products exported successfully !";}
}
unset($obj);

function removeSpCharacters ($data)
{
	
	$data = str_replace("<br />","",trim($data));
	$data = str_replace("<br>","",trim($data));
	$data = str_replace("\n","",trim($data));
	$data = str_replace("\r","",trim($data));
	$data = str_replace("\t","",trim($data));
	$data = str_replace('"',"",trim($data));
	$data = str_replace(',',"",trim($data));
	$data = str_replace("+","and",trim($data));
	$data = str_replace("&eacute;","e",trim($data));
	$data = str_replace("andeacute;","e",trim($data));
	$data = str_replace("é","e",trim($data));
	$data = str_replace("andEacute;","E",trim($data));
	$data = str_replace("&Eacute;","E",trim($data));
	$data = str_replace("É","E",trim($data));		
	return $data;
}
##

//#################### MAil sent after completed////////////////////////////
$subject = "Pepperjam Product file created for maxaroma End";
$headers = "From: cron@maxaroma.com <cron@maxaroma.com>\n" . "MIME-Version: 1.0\n" . "Content-type: text/html; charset=iso-8859-1";
$sendmessage = "PepperJam_Product_Export.txt is created for Pepperjam Product Export ftp<br>";
$sendmessage = $sendmessage.$errmsg;		
$onesendstat = mail("naresh.qualdev@gmail.com", $subject, $sendmessage, $headers);
///######################## Mail sent after completed/////////////////////////////
exit;
?>
