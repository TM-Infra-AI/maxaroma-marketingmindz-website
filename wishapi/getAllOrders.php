<?php
set_time_limit(0);
//ini_set('memory_limit',"500M");
//ini_set('memory_limit',"96M");
include_once("/home/maxaroma/public_html/lib/config_setting.php");
require_once 'vendor/autoload.php';

use Wish\UsedToken;
use Wish\WishClient;
use Wish\Model\WishTracker;
use Wish\Exception\OrderAlreadyFulfilledException;
use Wish\Model\WishReason;

$myFile = '/home/maxaroma/public_html/Logs/getAllOrders.txt';
if(filesize($myFile)==10000000)
{
	rename($myFile,"/home/maxaroma/public_html/Logs/getAllOrders_bk.txt");
	unlink($myFile);
}

if(fopen($myFile, 'a+'))
{
	$fh = fopen($myFile, 'a+');

	$stringData .= date("m/d/Y H:i:s")." : getAllOrders.php :  Wish Fetch Orders  : Wish Fetch Orders Start\n";
	fwrite($fh, $stringData);
	fclose($fh);
}


$TokenObj = new UsedToken();
$Token  = $TokenObj->getTokens();
$client = new WishClient($Token,'prod');
$unfulfilled_orders = $client->getAllUnfulfilledOrdersSince();
//echo "<pre>"; print_r($unfulfilled_orders); exit;
$TotalOrders = count($unfulfilled_orders);



$db_del = $obj->sql_query("delete from " . TABLE_PREFIX . "import_wish_orders");
//Fulfill all unfulfilled orders

foreach($unfulfilled_orders as $order){
	 
	  $order = (array)$order;
	
	  $ordershipping = (array)$order["ShippingDetail"]; 
	  $InsertOrders = array( 	
										'last_updated' 			=> trim($order["last_updated"]),
										'order_time' 			=> trim($order["order_time"]),
										'quantity' 				=> trim($order["quantity"]),
										'color' 				=> trim($order["color"]),
										'price' 				=> trim($order["price"]),
										'variant_id' 			=> trim($order["variant_id"]),
										'phone_number' 			=> trim($ordershipping["phone_number"]),
										'city' 					=> trim($ordershipping["city"]),
										'state' 				=> trim($ordershipping["state"]),
										'name' 					=> trim($ordershipping["name"]),
										'country' 				=> trim($ordershipping["country"]),
										'street_address2' 		=> trim($ordershipping["street_address2"]),
										'street_address1' 		=> trim($ordershipping["street_address1"]),
										'zipcode' 				=> trim($ordershipping["zipcode"]),
										'is_wish_express' 		=> trim($order["is_wish_express"]),
										'cost' 					=> trim($order["cost"]),
										'shipping_cost' 		=> trim($order["shipping_cost"]),
										'hours_to_fulfill' 		=> trim($order["hours_to_fulfill"]),
										'product_image_url' 	=> trim($order["product_image_url"]),
										'size' 					=> trim($order["size"]),
										'sku' 					=> trim($order["sku"]),
										'order_total' 			=> trim($order["order_total"]),
										'product_id' 			=> trim($order["product_id"]),
										'shipping' 				=> trim($order["shipping"]),
										'order_id' 				=> trim($order["order_id"]),
										'order_state' 			=> trim($order["state"]),
										'days_to_fulfill' 		=> trim($order["days_to_fulfill"]),
										'product_name' 			=> trim($order["product_name"]),
										'transaction_id' 		=> trim($order["transaction_id"]),
										'buyer_id' 				=> trim($order["buyer_id"])
							);
				
	  $row = $obj->insert(TABLE_PREFIX.'import_wish_orders', $InsertOrders);						
	
	
 
}
 $SQL = "SELECT * from ".TABLE_PREFIX."import_wish_orders WHERE order_id!='order_id'";
 $import_order_res = $obj->select($SQL);
 $TotalOrders = count($import_order_res);
 if($TotalOrders > 0)
 {
	for($i=0;$i<$TotalOrders;$i++)
	{ 
	  $Duplicate_Order_Res = $obj->select("SELECT orders_id FROM " . TABLE_PREFIX . "wish_orders WHERE order_id='".trim($import_order_res[$i]['order_id'])."'");
	  $IsVender = "No";	
	  if(count($Duplicate_Order_Res) <=0)
	  {	  
		  
			$OrderInsert = array ( 
								   'last_updated' 				=> trim($import_order_res[$i]['last_updated']),
								   'order_time'					=> trim($import_order_res[$i]['order_time']),
								   'variant_id'					=> trim($import_order_res[$i]['variant_id']),
								   'phone_number'				=> trim($import_order_res[$i]['phone_number']),
								   'city'						=> trim($import_order_res[$i]['city']),
								   'state'						=> trim($import_order_res[$i]['state']),
								   'name'						=> trim($import_order_res[$i]['name']),
								   'country'					=> trim($import_order_res[$i]['country']),
								   'street_address2'			=> trim($import_order_res[$i]['street_address2']),
								   'street_address1'			=> trim($import_order_res[$i]['street_address1']),
								   'zipcode'					=> trim($import_order_res[$i]['zipcode']),
								   'is_wish_express'			=> trim($import_order_res[$i]['is_wish_express']),
								   'cost'						=> number_format(trim($import_order_res[$i]['cost']), 2, '.',''),
								   'shipping_cost'				=> number_format(trim($import_order_res[$i]['shipping_cost']), 2, '.',''),
								   'hours_to_fulfill'			=> trim($import_order_res[$i]['hours_to_fulfill']),
								   'order_total'				=> number_format(trim($import_order_res[$i]['order_total']), 2, '.',''),
								   'shipping'					=> number_format(trim($import_order_res[$i]['shipping']), 2, '.',''),
								   'order_id'					=> trim($import_order_res[$i]['order_id']),
								   'order_state'				=> trim($import_order_res[$i]['order_state']),
								   'days_to_fulfill'			=> trim($import_order_res[$i]['days_to_fulfill']),
								   'transaction_id'				=> trim($import_order_res[$i]['transaction_id']),
								   'buyer_id'					=> trim($import_order_res[$i]['buyer_id']),
								   'is_wish'					=> "No",
								   'ship_status'				=> "Pending",
								   'status'						=> "Pending"
								   	   
							   );
							   
						  
						$OrderID = $obj->insert(TABLE_PREFIX.'wish_orders', $OrderInsert);
						
						
						
						
						 if(isset($OrderID) && !empty($OrderID))
						 {
							$SQL11 = "SELECT quantity,color,price,product_image_url,size,sku,product_id,product_name from " . TABLE_PREFIX ."import_wish_orders WHERE order_id='".trim($import_order_res[$i]['order_id'])."'";
							$OrderDetailRes = $obj->select($SQL11);  
						    $TotalOrderDetailsOrder = count($OrderDetailRes);
						    if($TotalOrderDetailsOrder > 0)
						    { 
								for($j=0;$j<count($OrderDetailRes);$j++)
								{
									
								/*$EXTRA_SQL ='';
								$EXTRA_SQL = $generalobj->getSystemProductPrice();
								$casewhen = $generalobj->getSystemStockCheck(); 
								$StockCondition = $generalobj->getSystemStockAvalilable();
								$WebsiteCurrentStock = $generalobj->getSystemWebsiteCurrentStock();
								*/
								
								$IsCosmo = "No";
								$IsNandansons = "No";
								$IsPerfumePW  = "No";
								$VendorSKU = "";
								
								$sql = "SELECT p.* FROM `".TABLE_PREFIX."products` as p WHERE p.status = '1' AND p.sku = '".trim($OrderDetailRes[$j]['sku'])."'";
						
								$ProductRs =  $obj->select($sql);
								
								
									$OrderDetailInsert = array ( 
													  'orders_id'			=> $OrderID,
													  'quantity'			=> trim($OrderDetailRes[$j]['quantity']),
													  'color'				=> trim($OrderDetailRes[$j]['color']),
													  'price'				=> number_format(trim($OrderDetailRes[$j]['price']), 2, '.',''),
													  'product_image_url'	=> trim($OrderDetailRes[$j]['product_image_url']),
													  'size'				=> trim($OrderDetailRes[$j]['size']),
													  'sku'					=> trim($OrderDetailRes[$j]['sku']),
													  'product_id'			=> trim($OrderDetailRes[$j]['product_id']),
													  'product_name'		=> trim($OrderDetailRes[$j]['product_name']),
													  'VendorSKU'			=> trim($VendorSKU),
													  'IsCosmo'				=> trim($IsCosmo),
												      'IsNandansons'		=> trim($IsNandansons),
												      'IsPerfumePW'			=> trim($IsPerfumePW)
													);	
												   
								    $OrderDetailID = $obj->insert(TABLE_PREFIX.'wish_order_details', $OrderDetailInsert);
					           }
					           
					           
					           
							}
							if(isset($OrderID))
							{
								$new_Order_ids = 'WISH'.$OrderID;
								$updateOrder = array (
									'our_order_id'	 => $new_Order_ids,
									'IsVender'	 => $IsVender
								 );	
								$udpRefer = $obj->update(TABLE_PREFIX.'wish_orders', $updateOrder, " `orders_id` = '".$OrderID."'");			
							}
					    }	
					}
	} 
 } 
unset($obj);

if(fopen($myFile, 'a+'))
{
	$fh = fopen($myFile, 'a+');
	$stringData = "";
	$stringData .= date("m/d/Y H:i:s")." : getAllOrders.php :  Wish Fetch Orders  : Wish Fetch Orders End\n";
	fwrite($fh, $stringData);
	fclose($fh);
}

exit;
?>
