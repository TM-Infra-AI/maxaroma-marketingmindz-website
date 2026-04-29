<?php
/* This Cron is used to create products in wish api */ 
set_time_limit(0);
ini_set('memory_limit',"1024M");
include_once("/home/peraroma/public_html/lib/config_setting.php");

$start_limit 		= 0;
$end_limit			= 50;

$total_batch		= 0;
$total_record_batch	= 0;


$fetch  = " IF(`" . TABLE_PREFIX . "products`.sale_price!=0 AND `" . TABLE_PREFIX . "products`.sale_price < `" . TABLE_PREFIX . "products`.our_price,`" . TABLE_PREFIX . "products`.sale_price,`" . TABLE_PREFIX . "products`.our_price) AS product_price "; 

$sql = " SELECT DISTINCT(`" . TABLE_PREFIX . "products`.sku) ,".$fetch." ";
$sql .= " FROM `" . TABLE_PREFIX . "products`, `" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m,`".TABLE_PREFIX."brand`" . $extraTables;
$sql .= " WHERE 1 ";
$sql .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
$sql .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
$sql .="  AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
$sql .="  AND `".TABLE_PREFIX."products`.brand_id = `".TABLE_PREFIX."brand`.brand_id ";
$sql .="  AND `" . TABLE_PREFIX . "products`.is_wish_disable='No'";
$sql .="  AND `" . TABLE_PREFIX . "category`.category_id IN ('68','69','70','71','90','91','93','94','6','9','43','202') ";
$sql .="  Group By `" . TABLE_PREFIX . "products`.sku ";	

$res_total = $obj->select($sql);
//echo "<pre>"; print_r($res_total); exit;	
$total_prod			 = count($res_total);
$total_record_batch  = ceil($total_prod/$end_limit);
if($total_prod > 0)
{
	
	
	for($b=0; $b < $total_record_batch; $b++ )
	{
	$sql = " SELECT ".TABLE_PREFIX . "products.sku,";
	$sql = " SELECT ".TABLE_PREFIX . "products.products_id ";
	$sql .= " FROM `" . TABLE_PREFIX . "products`, `" . TABLE_PREFIX . "products_category` ,`" . TABLE_PREFIX . "category`, `" . TABLE_PREFIX . "manufacture` as m,`".TABLE_PREFIX."brand`" . $extraTables;
	$sql .= " WHERE 1 ";
	$sql .= " AND `" . TABLE_PREFIX . "products`.products_id = `" . TABLE_PREFIX . "products_category`.products_id ";
	$sql .= " AND `" . TABLE_PREFIX . "category`.category_id = `" . TABLE_PREFIX . "products_category`.category_id ";
	$sql .="  AND `" . TABLE_PREFIX . "products`.imanufactureid = m.imanufactureid ";
	$sql .="  AND `".TABLE_PREFIX."products`.brand_id = `".TABLE_PREFIX."brand`.brand_id ";
	$sql .="  AND `" . TABLE_PREFIX . "products`.is_wish_disable='No'";
	$sql .="  AND `" . TABLE_PREFIX . "category`.category_id IN ('68','69','70','71','90','91','93','94','6','9','43','202') ";
	$sql .="  Group By `" . TABLE_PREFIX . "products`.sku ";	
    $sql .= " LIMIT $start_limit,$end_limit";
      
	
	$prodRes = $obj->select($sql);
	 

	//echo "<pre>"; print_r($prodRes); exit;
	 $TotalProducts = count($prodRes);
	

	
		for($i=0;$i<$TotalProducts;$i++)
		{
			$sql = "Update `" . TABLE_PREFIX . "products` SET is_wish_disable='Yes' WHERE products_id='".$prodRes[$i]["products_id"]."'";
			
			$result = $obj->sql_query($sql);	
		}
		 $start_limit = $start_limit+$end_limit;
		 $total_batch = $total_batch+1;
  }
}

?>
