<?php
/* This Cron is used to create products in wish api */ 
set_time_limit(0);
ini_set('memory_limit',"1024M");
include_once("/home/peraroma/public_html/lib/config_setting.php");
require_once 'vendor/autoload.php';
use Wish\UsedToken;
use Wish\WishClient;
use Wish\Exception\ServiceResponseException;


$vemail = "naresh.qualdev@gmail.com";
$sendmessage = "Wish Product Inventory start Cron In Wish API On Maxaroma At ".date('m-d-Y');	
$test 	= @mail($vemail,"Wish Product Inventory start Cron In Wish API On Maxaroma",$sendmessage);

$start_limit 		= 0;
$end_limit			= 500;

$total_batch		= 0;
$total_record_batch	= 0;
$TokenObj = new UsedToken();
$Token  = $TokenObj->getTokens();
$client = new WishClient($Token,'prod');

$fetch = $generalobj->getSystemProductPrice("`" . TABLE_PREFIX . "products`");
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
$sql .="  AND `" . TABLE_PREFIX . "category`.category_id NOT IN('68','69','70','71','90','91','93','94','6','9','43') ";
$sql .="  AND `" . TABLE_PREFIX . "products`.sku NOT IN('UP8053672743760','UP8053672789775','UP8053672770438','UP8053672561814','UP8053672559705','UP8053672737660','UP018600','T5060103310395','UP3360372728313') ";
$sql .="  AND `" . TABLE_PREFIX . "products`.is_wish_disable='No'";
$sql .="  AND `".TABLE_PREFIX."products`.brand_id = `".TABLE_PREFIX."brand`.brand_id ";
$sql .="  Group By `" . TABLE_PREFIX . "products`.sku ";	
$sql .="  having  product_price > 0";	
$sql .= " LIMIT 0,3500";
$res_total = $obj->select($sql);
	
$total_prod			 = count($res_total);


$total_record_batch  = ceil($total_prod/$end_limit);

$skustring = "";
$skulist = '';

if($total_prod > 0)
{
	for($b=0; $b < $total_record_batch; $b++ )
	{
		$sel_pro = " SELECT DISTINCT(`" . TABLE_PREFIX . "products`.sku),`" . TABLE_PREFIX . "category`.parent_id,";
		$sel_pro .= " `" . TABLE_PREFIX . "products_category`.category_id, ";
		$sel_pro .= " `" . TABLE_PREFIX . "products`.sku, ";
		$sel_pro .= " `" . TABLE_PREFIX . "products`.products_id, ";
		$sel_pro .= $fetch.",".$current_stock_check.",".$website_stock;
		$sel_pro .= " FROM `" . TABLE_PREFIX . "products`, `" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m,`".TABLE_PREFIX."brand`" . $extraTables;
		$sel_pro .= " WHERE 1 ";
		$sel_pro .= " AND `" . TABLE_PREFIX . "category`.status='1' ";
		$sel_pro .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
		$sel_pro .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
		$sel_pro .= " AND `".TABLE_PREFIX."brand`.status='1' ";
		$sel_pro .="  AND `".TABLE_PREFIX."products`.brand_id = `".TABLE_PREFIX."brand`.brand_id ";
		$sel_pro .= " AND m.status = '1' ";
		$sel_pro .= " AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
		$sel_pro .="  AND `" . TABLE_PREFIX . "category`.category_id NOT IN('68','69','70','71','90','91','93','94','6','9','43') ";
		$sel_pro .="  AND `" . TABLE_PREFIX . "products`.sku NOT IN('UP8053672743760','UP8053672789775','UP8053672770438','UP8053672561814','UP8053672559705','UP8053672737660','UP018600','T5060103310395','UP3360372728313') ";
		$sel_pro .= " AND `" . TABLE_PREFIX . "products`.is_wish_created='Yes' ";
		$sel_pro .="  AND `" . TABLE_PREFIX . "products`.is_wish_disable='No'";
		$sel_pro .= " Group By `" . TABLE_PREFIX . "products`.sku"; 
		$sel_pro .= " HAVING product_price > 0";
		$sel_pro .= " LIMIT $start_limit,$end_limit";
	
		$prodRes = $obj->select($sel_pro);
		
		
		$TotalProducts = count($prodRes);

		for($i=0;$i<$TotalProducts;$i++)
		{
			
			$sku  	   = $prodRes[$i]["sku"];
			
			if($prodRes[$i]["status"] == '0')
			{
				$prodRes[$i]["current_stock"] = 0;
			}
			if($prodRes[$i]["current_stock"] < 3)
			{
				$prodRes[$i]["current_stock"] = 0;
			}
			$coderror =$client->updateInventoryBySKU($sku,$prodRes[$i]["current_stock"]);
			
			if($coderror > 0)
			{
				$skulist.= $prodRes[$i]["sku"].",";
			}
		}
		 $start_limit = $start_limit+$end_limit;
		 $total_batch = $total_batch+1;
		 
  }
}

if($skulist!='')
{
	$vemail = "naresh.qualdev@gmail.com";
	$sendmessage = "Wish Product Inventory Failed In Wish API On Maxaroma At ".date('m-d-Y') . " And The Product SKU Is ".$skulist;	
	$test 	= @mail($vemail,"Wish Product Inventory Failed In Wish API On Maxaroma For First Cron",$sendmessage);
}

$vemail = "naresh.qualdev@gmail.com";
$sendmessage = "Wish Product Inventory End Cron In Wish API On Maxaroma At ".date('m-d-Y');	
$test 	= @mail($vemail,"Wish Product Inventory End Cron In Wish API On Maxaroma",$sendmessage);

unset($obj);
exit;

?>
