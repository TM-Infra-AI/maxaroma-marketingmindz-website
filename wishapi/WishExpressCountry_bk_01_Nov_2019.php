<?php
/* This Cron is used to create products in wish api */ 
set_time_limit(0);
ini_set('memory_limit',"1024M");
include_once("/home/peraroma/public_html/lib/config_setting.php");
require_once 'vendor/autoload.php';
use Wish\UsedToken;
use Wish\WishClient;
use Wish\Exception\ServiceResponseException;

$start_limit 		= 0;
$end_limit			= 50;
$total_batch		= 0;
$total_record_batch	= 0;
$TokenObj = new UsedToken();
$Token  = $TokenObj->getTokens();
$client = new WishClient($Token,'prod');

$fetch = $generalobj->getSystemProductPrice("`" . TABLE_PREFIX . "products`");
$retail_price_check =  $generalobj->getSystemProductRetailPrice("`" . TABLE_PREFIX . "products`");
$current_stock_check = $generalobj->getSystemStockCheck("`" . TABLE_PREFIX . "products`");
$website_stock		 = $generalobj->getSystemWebsiteCurrentStock("`" . TABLE_PREFIX . "products`");


$sql = " SELECT DISTINCT(`" . TABLE_PREFIX . "products`.sku) ,".$fetch." ";
$sql .= " FROM `" . TABLE_PREFIX . "products`, `" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m,`".TABLE_PREFIX."brand`" . $extraTables;
$sql .= " WHERE 1 ";
$sql .= " AND `" . TABLE_PREFIX . "category`.status='1' ";
$sql .= " AND `".TABLE_PREFIX."brand`.status='1' ";
$sql .= " AND m.status = '1' ";
$sql .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
$sql .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
$sql .="  AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
$sql .="  AND `" . TABLE_PREFIX . "products`.is_wish_created='Yes' ";
$sql .="  AND `" . TABLE_PREFIX . "category`.category_id NOT IN('68','69','70','71') ";
$sql .="  AND `" . TABLE_PREFIX . "products`.is_wish_express='No' ";
$sql .="  AND `".TABLE_PREFIX."products`.brand_id = `".TABLE_PREFIX."brand`.brand_id ";
$sql .="  Group By `" . TABLE_PREFIX . "products`.sku ";	
$sql .="  having  product_price > 0 ";	

$res_total = $obj->select($sql);
	
$total_prod			 = count($res_total);

$total_record_batch  = ceil($total_prod/$end_limit);

$skustring = "";

if($total_prod > 0)
{
	
	
	for($b=0; $b < $total_record_batch; $b++ )
	{
		 $sel_pro = " SELECT DISTINCT(`" . TABLE_PREFIX . "products`.sku),`" . TABLE_PREFIX . "category`.parent_id,";
		 $sel_pro .= $fetch.",".$retail_price_check.",".$current_stock_check.",".$website_stock;
		 $sel_pro .= " FROM `" . TABLE_PREFIX . "products`, `" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m,`".TABLE_PREFIX."brand`" . $extraTables;
		 $sel_pro .= " WHERE 1 ";
		 $sel_pro .= " AND `" . TABLE_PREFIX . "category`.status='1' ";
		 $sel_pro .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
		 $sel_pro .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
		 $sel_pro .= " AND `".TABLE_PREFIX."brand`.status='1' ";
		 $sel_pro .="  AND `".TABLE_PREFIX."products`.brand_id = `".TABLE_PREFIX."brand`.brand_id ";
		 $sel_pro .= " AND m.status = '1' ";
		 $sel_pro .="  AND `" . TABLE_PREFIX . "products`.is_wish_express='No' ";
		 $sel_pro .="  AND `" . TABLE_PREFIX . "category`.category_id NOT IN('68','69','70','71') ";
		 $sel_pro .= " AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
		 $sel_pro .= " AND `" . TABLE_PREFIX . "products`.is_wish_created='Yes' ";
		 $sel_pro .= " Group By `" . TABLE_PREFIX . "products`.sku"; 
		 $sel_pro .= " HAVING product_price > 0";
		 $sel_pro .= " LIMIT $start_limit,$end_limit";
		
		$prodRes = $obj->select($sel_pro);
	    
	    
		$TotalProducts = count($prodRes);
		
		for($i=0;$i<$TotalProducts;$i++)
		{
			
			if($prodRes[$i]['current_stock'] <=0)
			{
				continue;
			}
			$sku						= $prodRes[$i]['sku'];		
			
			/*$response = $client->getShippingById($sku,'US','true','true');
			
			if($response==0)
			{
				
				$wishArr = array (
								'is_wish_express'	 => "Yes"
						    );									 
				
				$udpRefer = $obj->update(TABLE_PREFIX.'products', $wishArr, " `sku` = '".$sku."'");
				continue;
			}*/
			
			
			$Res = $client->updateShippingById($sku,'US',0,'true','true');
			
			if($Res > 0)
			{
				/*$vemail = "naresh.qualdev@gmail.com";
				$sendmessage = "In Maxaroma Wish Express Product Update Error And Sku".$sku." And Error Code is ".$Res." on ".date('Y-m-d H:i:s');	
				$test 	= @mail($vemail,"In Maxaroma Wish Express Product Update Error" ,$sendmessage);*/
			}
			else
			{
			  $wishArr = array (
								'is_wish_express'	 => "Yes"
						    );									 
			 $udpRefer = $obj->update(TABLE_PREFIX.'products', $wishArr, " `sku` = '".$sku."'");	
			 
			}
			
			
		}
		 $start_limit = $start_limit+$end_limit;
		 $total_batch = $total_batch+1;
		 
		 sleep(5);
  }
}


$vemail = "naresh.qualdev@gmail.com";
$sendmessage = "In Maxaroma Wish Express Product Cron on ".date('Y-m-d H:i:s');	
$test 	= @mail($vemail,"In Maxaroma Wish Express Product Cron",$sendmessage);

unset($obj);
exit;

function removeSpCharacters ($data)
{
	
	$data = str_replace("<br />","",trim($data));
	$data = str_replace("<br>","",trim($data));
	$data = str_replace("\n","",trim($data));
	$data = str_replace("\r","",trim($data));
	$data = str_replace("\t","",trim($data));
	$data = str_replace('"',"",trim($data));
	$data = str_replace(',',"",trim($data));
	$data = str_replace("&amp;","and",trim($data));
	$data = str_replace("+","and",trim($data));
	$data = str_replace("&","and",trim($data));
	$data = str_replace("&eacute;","e",trim($data));
	$data = str_replace("andeacute;","e",trim($data));
	$data = str_replace("é","e",trim($data));
	$data = str_replace("andEacute;","E",trim($data));
	$data = str_replace("&Eacute;","E",trim($data));
	$data = str_replace("É","E",trim($data));
	$data = str_replace("!","",trim($data));		
	return $data;
}
?>
