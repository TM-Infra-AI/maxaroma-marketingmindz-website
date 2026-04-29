<?php
set_time_limit(0);
ini_set('memory_limit',"500M");
include_once("/home/maxaroma/public_html/lib/config_setting.php");
$vemail = "naresh.qualdev@gmail.com";
$vfromemail = "gequaldev@gmail.com";
$headers = "To: $vemail <$vemail>\n" . "From: $vfromemail <$vfromemail>\n" . "MIME-Version: 1.0\n" . "Content-type: text/html; charset=iso-8859-1";
$MSG =  "Sold Order Quanitty Addition In Maxraoma is Started On ".date("Y-m-d H:i:s");
$test = @mail($vemail,"Sold Order Quanitty Addition In Maxraoma is Started",$MSG,$headers);
/************************ Start Code For Website Orders ***********************************/ 

$sql= "SELECT od.sku, o.orders_id, (SELECT SUM( ord.quantity ) FROM  `" . TABLE_PREFIX."order_detail` AS ord, `".TABLE_PREFIX ."orders` AS orn
	   WHERE ord.sku = od.sku AND ord.sku != '' AND ord.sku != 'GIFT-CERTIFICATE' AND orn.is_count_sold_quantity = 'No'
	   AND orn.status = 'Completed' AND orn.orders_id = ord.orders_id) AS quantitysold FROM `".TABLE_PREFIX ."order_detail` AS od, `".TABLE_PREFIX ."orders` AS o
	   WHERE o.status = 'Completed'
	   AND o.is_count_sold_quantity = 'No'
	   AND o.orders_id = od.orders_id
	   AND od.sku != 'GIFT-CERTIFICATE'
	   AND od.sku != '' GROUP BY od.sku";

$res_total = $obj->select($sql);


$TotalOrders = count($res_total);



if($TotalOrders > 0)
{
	for($i=0;$i<$TotalOrders;$i++)
	{
		$TotalQuantity = 0;
		$sql = "SELECT  is_sold_quantity FROM  `" . TABLE_PREFIX . "products` WHERE sku='".$res_total[$i]["sku"]."' Limit 0,1";
		$Prod_res = $obj->select($sql);
		if(count($Prod_res) > 0)
		{
			$TotalQuantity = $Prod_res[0]["is_sold_quantity"] + $res_total[$i]["quantitysold"];
		
		
			$updatequery = $obj->sql_query("UPDATE `" . TABLE_PREFIX . "products` SET  is_sold_quantity='".$TotalQuantity."' 
										WHERE sku='".$res_total[$i]["sku"]."'");
		}
		$sql = "SELECT distinct od.orders_id as oid FROM `" . TABLE_PREFIX . "orders` as o,`" . TABLE_PREFIX . "order_detail` as od
					  WHERE o.orders_id=od.orders_id AND od.sku='".$res_total[$i]["sku"]."' AND o.is_count_sold_quantity='No'"; 
		$orders_res = $obj->select($sql);
		$ordercounts = count($orders_res);
		if($ordercounts > 0)
		{
			for($j=0;$j<$ordercounts;$j++)
			{
			$updatequery = $obj->sql_query("UPDATE `" . TABLE_PREFIX . "orders` SET  is_count_sold_quantity='Yes' 
										WHERE orders_id='".$orders_res[$j]["oid"]."'");
			}
		}		
		 			
	
	}
}
/************************ END Code For Website Orders ***********************************/ 


/************************ Jet Code For Website Orders ***********************************/ 
/*
$sql= "SELECT od.merchant_sku, o.orders_id, (SELECT SUM( ord.request_order_quantity ) FROM `".TABLE_PREFIX ."jet_order_detail` AS ord, `".TABLE_PREFIX ."jet_orders` AS orn
	   WHERE ord.merchant_sku = od.merchant_sku AND ord.merchant_sku != '' AND ord.merchant_sku != 'GIFT-CERTIFICATE' AND orn.is_count_sold_quantity = 'No'
	   AND orn.status = 'Completed' AND orn.orders_id = ord.orders_id) AS quantitysold FROM `".TABLE_PREFIX ."jet_order_detail` AS od, `".TABLE_PREFIX ."jet_orders` AS o
	   WHERE o.status = 'Completed'
	   AND o.is_count_sold_quantity = 'No'
	   AND o.orders_id = od.orders_id
	   AND od.merchant_sku != 'GIFT-CERTIFICATE'
	   AND od.merchant_sku != '' GROUP BY od.merchant_sku";

$res_total = $obj->select($sql);


$TotalOrders = count($res_total);



if($TotalOrders > 0)
{
	for($i=0;$i<$TotalOrders;$i++)
	{
		$TotalQuantity = 0;
		$sql = "SELECT  is_sold_quantity FROM  `" . TABLE_PREFIX ."products` WHERE sku='".$res_total[$i]["merchant_sku"]."' Limit 0,1";
		$Prod_res = $obj->select($sql);
		
		if(count($Prod_res) > 0)
		{
			$TotalQuantity = $Prod_res[0]["is_sold_quantity"] + $res_total[$i]["quantitysold"];
		
		
			$updatequery = $obj->sql_query("UPDATE `" . TABLE_PREFIX . "products` SET  is_sold_quantity='".$TotalQuantity."' 
										WHERE sku='".$res_total[$i]["merchant_sku"]."'");
		
		}
		$sql = "SELECT distinct od.orders_id as oid FROM `" . TABLE_PREFIX . "jet_orders` as o,`" . TABLE_PREFIX . "jet_order_detail` as od
					  WHERE o.orders_id=od.orders_id AND od.merchant_sku='".$res_total[$i]["merchant_sku"]."' AND o.is_count_sold_quantity='No'"; 
		$orders_res = $obj->select($sql);
		$ordercounts = count($orders_res);
		if($ordercounts > 0)
		{
			for($j=0;$j<$ordercounts;$j++)
			{
			$updatequery = $obj->sql_query("UPDATE `" . TABLE_PREFIX . "jet_orders` SET  is_count_sold_quantity='Yes' 
										WHERE orders_id='".$orders_res[$j]["oid"]."'");
			}
		}		
		 			
	
	}
}*/
/************************ END Jet Code For Website Orders ***********************************/ 


/************************ Walmart Code For Website Orders ***********************************/ 

$sql= "SELECT od.item_sku, o.orders_id, (SELECT SUM( ord.amount ) FROM `".TABLE_PREFIX ."walmart_order_details` AS ord, `".TABLE_PREFIX ."walmart_orders` AS orn
	   WHERE ord.item_sku = od.item_sku AND ord.item_sku != '' AND ord.item_sku != 'GIFT-CERTIFICATE' AND orn.is_count_sold_quantity = 'No'
	   AND orn.status = 'Completed' AND orn.orders_id = ord.orders_id) AS quantitysold FROM `".TABLE_PREFIX ."walmart_order_details` AS od, `".TABLE_PREFIX ."walmart_orders` AS o
	   WHERE o.status = 'Completed'
	   AND o.is_count_sold_quantity = 'No'
	   AND o.orders_id = od.orders_id
	   AND od.item_sku != 'GIFT-CERTIFICATE'
	   AND od.item_sku != '' GROUP BY od.item_sku";

$res_total = $obj->select($sql);



$TotalOrders = count($res_total);



if($TotalOrders > 0)
{
	for($i=0;$i<$TotalOrders;$i++)
	{
		$TotalQuantity = 0;
		$sql = "SELECT  is_sold_quantity FROM  `" . TABLE_PREFIX ."products` WHERE sku='".$res_total[$i]["item_sku"]."' Limit 0,1";
		$Prod_res = $obj->select($sql);
		
		if(count($Prod_res) > 0)
		{
			$TotalQuantity = $Prod_res[0]["is_sold_quantity"] + $res_total[$i]["quantitysold"];
		
		
			$updatequery = $obj->sql_query("UPDATE `" . TABLE_PREFIX . "products` SET  is_sold_quantity='".$TotalQuantity."' 
										WHERE sku='".$res_total[$i]["item_sku"]."'");
		
		}
		$sql = "SELECT distinct od.orders_id as oid FROM `" . TABLE_PREFIX . "walmart_orders` as o,`" . TABLE_PREFIX . "walmart_order_details` as od
					  WHERE o.orders_id=od.orders_id AND od.item_sku='".$res_total[$i]["item_sku"]."' AND o.is_count_sold_quantity='No'"; 
		$orders_res = $obj->select($sql);
		$ordercounts = count($orders_res);
		if($ordercounts > 0)
		{
			for($j=0;$j<$ordercounts;$j++)
			{
			$updatequery = $obj->sql_query("UPDATE `" . TABLE_PREFIX . "walmart_orders` SET  is_count_sold_quantity='Yes' 
										WHERE orders_id='".$orders_res[$j]["oid"]."'");
			}
		}		
		 			
	
	}
}
/************************ END Walmart Code For Website Orders ***********************************/ 

/************************ Amazon Code For Website Orders ***********************************/ 

$sql= "SELECT od.Item_SKU, o.cba_iorder_id, (SELECT SUM( ord.Item_Quantity ) FROM `".TABLE_PREFIX ."amazon_order_details` AS ord, `".TABLE_PREFIX ."amazon_order` AS orn
	   WHERE ord.Item_SKU = od.Item_SKU AND ord.Item_SKU != '' AND ord.Item_SKU != 'GIFT-CERTIFICATE' AND orn.is_count_sold_quantity = 'No'
	   AND orn.OrderStatus = 'Shipped' AND orn.cba_iorder_id = ord.cba_iorder_id) AS quantitysold FROM `".TABLE_PREFIX ."amazon_order_details` AS od, `".TABLE_PREFIX ."amazon_order` AS o
	   WHERE o.OrderStatus = 'Shipped'
	   AND o.is_count_sold_quantity = 'No'
	   AND o.cba_iorder_id = od.cba_iorder_id
	   AND od.Item_SKU != 'GIFT-CERTIFICATE'
	   AND od.Item_SKU != '' GROUP BY od.Item_SKU";

$res_total = $obj->select($sql);




$TotalOrders = count($res_total);



if($TotalOrders > 0)
{
	for($i=0;$i<$TotalOrders;$i++)
	{
		$TotalQuantity = 0;
		$sql = "SELECT  is_sold_quantity FROM  `" . TABLE_PREFIX ."products` WHERE sku='".$res_total[$i]["Item_SKU"]."' Limit 0,1";
		$Prod_res = $obj->select($sql);
		
		if(count($Prod_res) > 0)
		{
			$TotalQuantity = $Prod_res[0]["is_sold_quantity"] + $res_total[$i]["quantitysold"];
		
		
			$updatequery = $obj->sql_query("UPDATE `" . TABLE_PREFIX . "products` SET  is_sold_quantity='".$TotalQuantity."' 
										WHERE sku='".$res_total[$i]["Item_SKU"]."'");
		
		}
		$sql = "SELECT distinct od.cba_iorder_id as oid FROM `" . TABLE_PREFIX . "amazon_order` as o,`" . TABLE_PREFIX . "amazon_order_details` as od
					  WHERE o.cba_iorder_id=od.cba_iorder_id AND od.item_sku='".$res_total[$i]["Item_SKU"]."' AND o.is_count_sold_quantity='No'"; 
		$orders_res = $obj->select($sql);
		$ordercounts = count($orders_res);
		if($ordercounts > 0)
		{
			for($j=0;$j<$ordercounts;$j++)
			{
			$updatequery = $obj->sql_query("UPDATE `" . TABLE_PREFIX . "amazon_order` SET  is_count_sold_quantity='Yes' 
										WHERE cba_iorder_id='".$orders_res[$j]["oid"]."'");
			}
		}		
		 			
	
	}
}
/************************ END Amazon Code For Website Orders ***********************************/ 
$vemail = "naresh.qualdev@gmail.com";
$vfromemail = "gequaldev@gmail.com";
$headers = "To: $vemail <$vemail>\n" . "From: $vfromemail <$vfromemail>\n" . "MIME-Version: 1.0\n" . "Content-type: text/html; charset=iso-8859-1";
$MSG =  "Sold Order Quanitty Addition In Maxraoma is Ended On ".date("Y-m-d H:i:s");
$test = @mail($vemail,"Sold Order Quanitty Addition In Maxraoma is Ended",$MSG,$headers);
unset($obj);
exit;
?>
