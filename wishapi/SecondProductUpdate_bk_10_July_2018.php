<?php
/* This Cron is used to create products in wish api */ 
set_time_limit(0);
ini_set('memory_limit',"1024M");
include_once("/home/peraroma/public_html/lib/config_setting.php");
ini_set('display_errors',0);
require_once 'vendor/autoload.php';

use Wish\UsedToken;
use Wish\WishClient;
use Wish\Exception\ServiceResponseException;



$start_limit 		= 5000;
$end_limit			= 500;

$total_batch		= 0;
$total_record_batch	= 0;
$TokenObj = new UsedToken();
$Token  = $TokenObj->getTokens();
$client = new WishClient($Token,'prod');

$fetch = $generalobj->getSystemProductPrice("`" . TABLE_PREFIX . "products`");
$retail_price_check =  $generalobj->getSystemProductRetailPrice("`" . TABLE_PREFIX . "products`");
$current_stock_check = $generalobj->getSystemStockCheck("`" . TABLE_PREFIX . "products`");
$website_stock		 = $generalobj->getSystemWebsiteCurrentStock("`" . TABLE_PREFIX . "products`");
$newsql = " SELECT DISTINCT(`" . TABLE_PREFIX . "products`.sku) ,".$fetch." ";
$newsql .= " FROM `" . TABLE_PREFIX . "products`, `" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m,`".TABLE_PREFIX."brand`" . $extraTables;
$newsql .= " WHERE 1 ";
$newsql .= " AND `" . TABLE_PREFIX . "category`.status='1' ";
$newsql .= " AND `".TABLE_PREFIX."brand`.status='1' ";
$newsql .= " AND m.status = '1' ";
$newsql .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
$newsql .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
$newsql .="  AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
$newsql .="  AND `" . TABLE_PREFIX . "products`.is_wish_created='Yes' ";
$newsql .="  AND `" . TABLE_PREFIX . "category`.category_id NOT IN('68','69','70','71','90','91','93','94') ";
$newsql .="  AND `" . TABLE_PREFIX . "products`.sku NOT IN('UP8053672743760','UP8053672789775','UP8053672770438','UP8053672561814','UP8053672559705','UP8053672737660') ";
$newsql .="  AND `".TABLE_PREFIX."products`.brand_id = `".TABLE_PREFIX."brand`.brand_id ";
$newsql .="  Group By `" . TABLE_PREFIX . "products`.sku ";	
$newsql .="  having  product_price > 0";	
$new_res_total = $obj->select($newsql);
$Totalcount = count($new_res_total);

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
$sql .="  AND `" . TABLE_PREFIX . "category`.category_id NOT IN('68','69','70','71','90','91','93','94') ";
$sql .="  AND `" . TABLE_PREFIX . "products`.sku NOT IN('UP8053672743760','UP8053672789775','UP8053672770438','UP8053672561814','UP8053672559705','UP8053672737660') ";
$sql .="  AND `".TABLE_PREFIX."products`.brand_id = `".TABLE_PREFIX."brand`.brand_id ";
$sql .="  Group By `" . TABLE_PREFIX . "products`.sku ";	
$sql .="  having  product_price > 0";	
$sql .= " LIMIT 5000,".$Totalcount;
$res_total = $obj->select($sql);
$total_prod			 = count($res_total);
$total_record_batch  = ceil($total_prod/$end_limit);

$skustring = "";

if($total_prod > 0)
{
	$sql = "SELECT * FROM `".TABLE_PREFIX."site_settings` WHERE status = '1' AND var_name='WISH_PER_VAR' ORDER BY site_settings_id";
	$setting_res_new	=	$obj->select($sql);
	$WISH_PER_VAR 		= 	$setting_res_new[0]["setting"];
	for($b=0; $b < $total_record_batch; $b++ )
	{
		 $sel_pro = " SELECT DISTINCT(`" . TABLE_PREFIX . "products`.sku),`" . TABLE_PREFIX . "category`.parent_id,";
		 $sel_pro .= " `" . TABLE_PREFIX . "products_category`.category_id, ";
		 $sel_pro .= " `" . TABLE_PREFIX . "products`.sku, ";
		 $sel_pro .= " `" . TABLE_PREFIX . "products`.product_name ,";
		 $sel_pro .= " `" . TABLE_PREFIX . "products`.image, ";
		 $sel_pro .= " `" . TABLE_PREFIX . "products`.short_description, ";
		 $sel_pro .= " `" . TABLE_PREFIX . "products`.product_description, ";
		 $sel_pro .= " `" . TABLE_PREFIX . "products`.products_id, ";
		 $sel_pro .= " `" . TABLE_PREFIX.  "products`.size, "; 
		 $sel_pro .= $fetch.",".$retail_price_check.",".$current_stock_check.",".$website_stock;
		 $sel_pro .= " FROM `" . TABLE_PREFIX . "products`, `" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m,`".TABLE_PREFIX."brand`" . $extraTables;
		 $sel_pro .= " WHERE 1 ";
		 $sel_pro .= " AND `" . TABLE_PREFIX . "category`.status='1' ";
		 $sel_pro .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
		 $sel_pro .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
		 $sel_pro .= " AND `".TABLE_PREFIX."brand`.status='1' ";
		 $sel_pro .="  AND `".TABLE_PREFIX."products`.brand_id = `".TABLE_PREFIX."brand`.brand_id ";
		 $sel_pro .= " AND m.status = '1' ";
		 $sel_pro .= " AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
		 $sel_pro .= " AND `" . TABLE_PREFIX . "products`.is_wish_created='Yes' ";
		 $sel_pro .="  AND `" . TABLE_PREFIX . "category`.category_id NOT IN('68','69','70','71','90','91','93','94') "; 
		 $sel_pro .="  AND `" . TABLE_PREFIX . "products`.sku NOT IN('UP8053672743760','UP8053672789775','UP8053672770438','UP8053672561814','UP8053672559705','UP8053672737660') ";
		 $sel_pro .= " Group By `" . TABLE_PREFIX . "products`.sku"; 
		 $sel_pro .= " HAVING product_price > 0";
		 $sel_pro .= " LIMIT $start_limit,$end_limit";
	
		$prodRes = $obj->select($sel_pro);
		//echo "<pre>"; print_r($prodRes); exit;
		
		$TotalProducts = count($prodRes);

		for($i=0;$i<$TotalProducts;$i++)
		{
			
			$sku						= $prodRes[$i]['sku'];
			
			$name 		 				= removeSpCharacters(trim(ucwords($prodRes[$i]['product_name'])));
			
			$product_description 		= removeSpCharacters(trim(ucwords($prodRes[$i]["product_description"])));
			if($product_description=='')
			{
				$product_description 	= removeSpCharacters(trim(ucwords($prodRes[$i]["short_description"])));
			}
		
			if($product_description=='')
			{
				continue;
			}
			
			$size      = $prodRes[$i]["size"];
			$large_image = '';
			
			if(file_exists(PRD_LARGE_IMG_PATH . $prodRes[$i]['image']) and !empty($prodRes[$i]['image'])) {
                $large_image = PRD_LARGE_IMG_URL . $prodRes[$i]['image'];
            } 
          
            if($large_image=='')
            {
				continue;
			}
	        $Image1 = $large_image;
	    
	        $Image1 = str_replace('https://','https://',$Image1);
		    $Image1 = str_replace('httpss://','https://',$Image1);
			
			
			
			$msrp	   = $prodRes[$i]["retail_price"];
			
			$product_price = $prodRes[$i]["product_price"];
			
			if($product_price > 0)
			{
				$priceCal = ($product_price * ($WISH_PER_VAR/100));
				$product_price = $product_price +  $priceCal;
			}
			
			$var = $client->getProductVariationBySKU($sku);
			
			$newarr = (array)$var;
			
			if(count($newarr)<=0)
			{
			  $skustring.= $sku.",";
			  continue;
			}
			
			if($prodRes[$i]["status"] == '0')
			{
				$prodRes[$i]["current_stock"] = 0;
			}
			if($prodRes[$i]["current_stock"] < 3)
			{
				$prodRes[$i]["current_stock"] = 0;
			}
			$var->price = $product_price;
			$var->msrp = $prodRes[$i]["retail_price"];
			$var->inventory = $prodRes[$i]["current_stock"];
			//$var->main_image = $Image1;
			$var->size = $size;	
			$var->enabled = 'true';
			if($prodRes[$i]["current_stock"]<=0)
			{
				$var->enabled = 'false';
			}
			$var->shipping = 0;					
		try {
			$sql = "Update `" . TABLE_PREFIX . "products` SET wish_update_date='".date("Y-m-d H:i:s")."' WHERE products_id='".$prodRes[$i]["products_id"]."'";
			$result = $obj->sql_query($sql);
			$client->updateProductVariation($var);
			}catch(ServiceResponsException $e){
			$vemail = "naresh.qualdev@gmail.com";
			$sendmessage = "Wish Product Updation Failed In Wish API On Maxaroma At ".date('m-d-Y') . " And The Product SKU Is ".$prodRes[$i]["sku"];	
			$test 	= @mail($vemail,"Wish Product Updation Failed In Wish API On Maxaroma For Second Cron",$sendmessage);
			}
		}
		 $start_limit = $start_limit+$end_limit;
		 $total_batch = $total_batch+1;
		 
		 sleep(5);
  }
}

if($skustring!='')
{
	$vemail = "naresh.qualdev@gmail.com";
	$sendmessage = "In Maxaroma ".$skustring." is not found in wish api on ".date('Y-m-d H:i:s'). " For Second Cron";	
	$test 	= @mail($vemail,"Sku is not found in wish api For Second Cron in Maxaroma",$sendmessage);	
}
$vemail = "naresh.qualdev@gmail.com";
$sendmessage = "In Maxaroma Second Wish Product Update Cron End on ".date('Y-m-d H:i:s');	
$test 	= @mail($vemail,"In Maxaroma Second Wish Product Update Cron End",$sendmessage);
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
