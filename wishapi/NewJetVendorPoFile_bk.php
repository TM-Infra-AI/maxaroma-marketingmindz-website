<?php

include_once("/home/peraroma/public_html/lib/config_setting.php");
//include_once("../lib/configuration.php");
$vemail = "tempchecknew@gmail.com";
$sendmessage = "Jet Vendor And Wish Create PO Cron Started In Maxaroma on ".date('m-d-Y');	
$test 	= @mail($vemail,"Jet Vendor And Wish Create PO Cron Started In Maxaroma",$MSG);

/* Jet PO Code Start */
$sql = "SELECT o.orders_id,od.merchant_sku,sum(od.request_order_quantity) as quantity,od.VendorSKU,od.base_price,od.is_po_number FROM ".TABLE_PREFIX."jet_orders as o,".TABLE_PREFIX."jet_order_detail as od WHERE od.VendorSKU!='' AND  (od.IsCosmo='Yes' || od.IsNandansons='Yes' || od.IsPerfumePW='Yes') AND o.IsVender='Yes' AND od.po_number='' AND od.is_po_number='No' AND o.orders_id=od.orders_id AND o.status!='Return'  GROUP BY od.merchant_sku order by o.orders_id desc ";
$order_res = $obj->select($sql);
$TotalOrders = count($order_res);

if($TotalOrders > 0)
{
	$DetailsArra = array(
									"PaymentStatus"			=> "NonePaid",
									"Sent Status"			=> "NotSent",
									"SupplierName"			=> "CosmoPolitain Cosmetics Inc.",
									"TenantToken" 			=> 'bvJ19ZvQp102eZZ9lovacsbWdVXAlP96mNl89GfQcFM=',
									"UserToken"	  			=> 'mU1x1Hyefyx2lyZ1Hj8Ea61Ibi6246Q6MG07JDa7g6Q='
									
					);	
	
	$PWWDetailsArra = 	array(
									"PaymentStatus"			=> "NonePaid",
									"Sent Status"			=> "NotSent",
									"SupplierName"			=> "PWW",
									"TenantToken" 			=> 'bvJ19ZvQp102eZZ9lovacsbWdVXAlP96mNl89GfQcFM=',
									"UserToken"	  			=> 'mU1x1Hyefyx2lyZ1Hj8Ea61Ibi6246Q6MG07JDa7g6Q='
									
					);				
	
	for($i=0;$i<$TotalOrders;$i++)
	{
		$sql = "SELECT sku,cosmo_sku,cosmo_price,cosmo_current_stock,cosmo_price,nandansons_sku,nandansons_price,nandansons_current_stock,perfumeworldwide_sku,perfumeworldwide_price,perfumeworldwide_currentstock FROM ".TABLE_PREFIX."products  WHERE sku='".$order_res[$i]['merchant_sku']."' AND ((cosmo_sku!='' AND cosmo_price > 0 ) || (nandansons_sku!='' AND nandansons_price > 0 ) || (perfumeworldwide_sku!='' AND perfumeworldwide_price > 0 ))";
		$prod_res = $obj->select($sql);
		
		
		$TotalProducts = count($prod_res);
		if($TotalProducts > 0)
		{
			
			
			
			if($order_res[$i]["VendorSKU"]==$prod_res[0]["cosmo_sku"])
			{
				$MainPrice = $prod_res[0]["cosmo_price"];
				$VendorSKU_Set = $prod_res[0]["cosmo_sku"]; 
				$CosmoPriceDifference = $prod_res[0]["cosmo_price"] - $prod_res[0]["nandansons_price"];
				$VendorName = "CosmoPolitain Cosmetics Inc.";
				if($CosmoPriceDifference > 0.50 && $prod_res[0]["nandansons_sku"]!='' &&  $prod_res[0]["nandansons_current_stock"]>=$order_res[$i]["quantity"] && $prod_res[0]["nandansons_price"] > 0)
				{
					$NandansonsPriceDifference = $prod_res[0]["nandansons_price"] - $prod_res[0]["perfumeworldwide_price"];
					$MainPrice = $prod_res[0]["nandansons_price"];
					$VendorSKU_Set = $prod_res[0]["nandansons_sku"];
					$VendorName = "Nandansons International"; 
					if($NandansonsPriceDifference > 0.50 && $prod_res[0]["perfumeworldwide_sku"]!='' &&  $prod_res[0]["perfumeworldwide_currentstock"]>=$order_res[$i]["quantity"] && $prod_res[0]["perfumeworldwide_price"] > 0)
					{
						$VendorName = "PWW";
						$VendorSKU_Set = $prod_res[0]["perfumeworldwide_sku"];
						$MainPrice = $prod_res[0]["perfumeworldwide_price"];
					}
				}
				else
				{
					$MainPrice = $prod_res[0]["cosmo_price"];
					$VendorSKU_Set = $prod_res[0]["cosmo_sku"];
					$CosmoPriceDifference = $prod_res[0]["cosmo_price"] - $prod_res[0]["perfumeworldwide_price"];
					$VendorName = "CosmoPolitain Cosmetics Inc.";
					
					if($CosmoPriceDifference > 0.50 && $prod_res[0]["perfumeworldwide_sku"]!='' &&  $prod_res[0]["perfumeworldwide_currentstock"]>=$order_res[$i]["quantity"] && $prod_res[0]["perfumeworldwide_price"] > 0)
					{
						$VendorSKU_Set = $prod_res[0]["perfumeworldwide_sku"];
						$MainPrice = $prod_res[0]["perfumeworldwide_price"];
						$VendorName = "PWW";
					}
					
					
				}
				
			}
			else if($order_res[$i]["VendorSKU"]==$prod_res[0]["nandansons_sku"])
			{
				$MainPrice = $prod_res[0]["nandansons_price"];
				$VendorSKU_Set = $prod_res[0]["nandansons_sku"];
				$NandansonsPriceDifference = $prod_res[0]["nandansons_price"] - $prod_res[0]["cosmo_price"];
				$VendorName = "Nandansons International";
				if($NandansonsPriceDifference > 0.50 && $prod_res[0]["cosmo_sku"]!='' &&  $prod_res[0]["cosmo_current_stock"]>=$order_res[$i]["quantity"] && $prod_res[0]["cosmo_price"] > 0)
				{
					$CosmoPriceDifference = $prod_res[0]["cosmo_price"] - $prod_res[0]["perfumeworldwide_price"];
					$MainPrice = $prod_res[0]["cosmo_price"];
					$VendorSKU_Set = $prod_res[0]["cosmo_sku"];
					$VendorName = "CosmoPolitain Cosmetics Inc.";
					if($CosmoPriceDifference > 0.50 && $prod_res[0]["perfumeworldwide_sku"]!='' &&  $prod_res[0]["perfumeworldwide_currentstock"]>=$order_res[$i]["quantity"] && $prod_res[0]["perfumeworldwide_price"] > 0 )
					{
						$MainPrice = $prod_res[0]["perfumeworldwide_price"];
						$VendorSKU_Set = $prod_res[0]["perfumeworldwide_sku"];
						$VendorName = "PWW";
					}
				}
				else 
				{
					$MainPrice = $prod_res[0]["nandansons_price"];
					$VendorSKU_Set = $prod_res[0]["nandansons_sku"];
					$NandansonsPriceDifference = $prod_res[0]["nandansons_price"] - $prod_res[0]["perfumeworldwide_price"];
					$VendorName = "Nandansons International";
					if($NandansonsPriceDifference > 0.50 && $prod_res[0]["perfumeworldwide_sku"]!='' &&  $prod_res[0]["perfumeworldwide_currentstock"]>=$order_res[$i]["quantity"] && $prod_res[0]["perfumeworldwide_price"]>0)
					{
						$VendorSKU_Set = $prod_res[0]["perfumeworldwide_sku"];
						$MainPrice = $prod_res[0]["perfumeworldwide_price"];
						$VendorName = "PWW";
					}
				}
			}
			else if($order_res[$i]["VendorSKU"]==$prod_res[0]["perfumeworldwide_sku"])
			{
				$MainPrice = $prod_res[0]["perfumeworldwide_price"];
				$VendorSKU_Set = $prod_res[0]["perfumeworldwide_sku"];
				$PWPriceDifference = $prod_res[0]["perfumeworldwide_price"] - $prod_res[0]["cosmo_price"];
				$VendorName = "PWW";
				if($PWPriceDifference > 0.50 && $prod_res[0]["cosmo_sku"]!='' &&  $prod_res[0]["cosmo_current_stock"]>=$order_res[$i]["quantity"] && $prod_res[0]["cosmo_price"] > 0)
				{
					$CosmoPriceDifference = $prod_res[0]["cosmo_price"] - $prod_res[0]["nandansons_price"];
					$VendorSKU_Set = $prod_res[0]["cosmo_sku"];
					$MainPrice = $prod_res[0]["cosmo_price"];
					$VendorName = "CosmoPolitain Cosmetics Inc.";
					if($CosmoPriceDifference > 0.50 && $prod_res[0]["nandansons_sku"]!='' &&  $prod_res[0]["nandansons_current_stock"]>=$order_res[$i]["quantity"] && $prod_res[0]["nandansons_price"] > 0)
					{
						$MainPrice = $prod_res[0]["nandansons_price"];
						$VendorSKU_Set = $prod_res[0]["nandansons_sku"];
						$VendorName = "Nandansons International";
					}
				}
				else
				{
					$NandansonsPriceDifference = $prod_res[0]["perfumeworldwide_price"] - $prod_res[0]["nandansons_price"];
					$MainPrice = $prod_res[0]["perfumeworldwide_price"];
					$VendorSKU_Set = $prod_res[0]["perfumeworldwide_sku"];
					$VendorName = "PWW";
					if($NandansonsPriceDifference > 0.50 && $prod_res[0]["nandansons_sku"]!='' &&  $prod_res[0]["nandansons_current_stock"]>=$order_res[$i]["quantity"] && $prod_res[0]["nandansons_price"] > 0)
					{
						$VendorSKU_Set = $prod_res[0]["nandansons_sku"];
						$MainPrice = $prod_res[0]["nandansons_price"];
						$VendorName = "Nandansons International";
					}
				}
			}
			if($MainPrice > 0 && $VendorSKU_Set!='' && $VendorName=='CosmoPolitain Cosmetics Inc.')
			{
				$DetailsArra["LineItems"][]= array("Cost" => (float)$MainPrice, "QuantityTo3PL" => 0,"Quantity" =>(int)$order_res[$i]["quantity"], "SKU" =>$order_res[$i]["merchant_sku"]);
			}
			if($MainPrice > 0 && $VendorSKU_Set!='' && $VendorName=='PWW')
			{
				$PWWDetailsArra["LineItems"][]= array("Cost" => (float)$MainPrice, "QuantityTo3PL" => 0,"Quantity" =>(int)$order_res[$i]["quantity"], "SKU" =>$order_res[$i]["merchant_sku"]);
			}
			
			}
			}
			
			
			
			if(count($DetailsArra["LineItems"]) > 0)
			{
				$request = 'https://app.skuvault.com/api/purchaseorders/createPO';
				
	 
				$param = json_encode(
					 $DetailsArra
					);
			
				  
			
			      
				  $initialreg = curl_init($request);
				  curl_setopt ($initialreg, CURLOPT_POST, true);
				  curl_setopt ($initialreg, CURLOPT_POSTFIELDS, $param);
				  curl_setopt($initialreg, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ; 
				  curl_setopt($initialreg, CURLOPT_HEADER, False);
				  curl_setopt($initialreg, CURLOPT_HTTPHEADER,array('Content-Type: application/json')); 
				  curl_setopt($initialreg, CURLOPT_RETURNTRANSFER, true);
				  $response = curl_exec($initialreg);
				  curl_close($initialreg);
				  $error_rep = json_decode($response, true);
				  if($error_rep["CreatePOStatus"]!= "Success")
				  {
						$vemail = "naresh.qualdev@gmail.com";
						$MSG =  "Create Jet PO Number Error For Cosmo In Skuvault Of Maxaroma On ".date("Y-m-d H:i:s")."Error is ".$error_rep["Errors"];
						$test = @mail($vemail,"Create Jet PO Number Error For Cosmo In Skuvault Of Maxaroma",$MSG);
				  }
				  else
				  {
					  for($k=0;$k<count($DetailsArra["LineItems"]);$k++)
					  {
					   $UpdateDetails = array(
												"is_po_number" => "Yes"
											);
					   $result = $obj->update(TABLE_PREFIX.'jet_order_detail', $UpdateDetails, "merchant_sku ='".$DetailsArra["LineItems"][$k]['SKU']."' AND VendorSKU!='' AND is_po_number='No'") ;	 						
					  }	
				  }
				 
				 
				  
			}
			if(count($PWWDetailsArra["LineItems"]) > 0)
			{
				$request = 'https://app.skuvault.com/api/purchaseorders/createPO';
				
	 
				$param = json_encode(
					 $PWWDetailsArra
					);
			
				  
			
			      
				  $initialreg = curl_init($request);
				  curl_setopt ($initialreg, CURLOPT_POST, true);
				  curl_setopt ($initialreg, CURLOPT_POSTFIELDS, $param);
				  curl_setopt($initialreg, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ; 
				  curl_setopt($initialreg, CURLOPT_HEADER, False);
				  curl_setopt($initialreg, CURLOPT_HTTPHEADER,array('Content-Type: application/json')); 
				  curl_setopt($initialreg, CURLOPT_RETURNTRANSFER, true);
				  $response = curl_exec($initialreg);
				  curl_close($initialreg);
				  $error_rep = json_decode($response, true);
				  if($error_rep["CreatePOStatus"]!= "Success")
				  {

						$vemail = "naresh.qualdev@gmail.com";
						$MSG =  "Create Jet PO Number Error For Perfume World Wide  In Skuvault Of Maxaroma On ".date("Y-m-d H:i:s")."Error is ".$error_rep["Errors"];
						$test = @mail($vemail,"Create Jet PO Number Error For Perfume World Wide In Skuvault Of Maxaroma",$MSG);
				  }
				  else
				  {
					  for($k=0;$k<count($PWWDetailsArra["LineItems"]);$k++)
					  { 
						$UpdateDetails = array(
												"is_po_number" => "Yes"
											);
						$result = $obj->update(TABLE_PREFIX.'jet_order_detail', $UpdateDetails, "merchant_sku ='".$PWWDetailsArra["LineItems"][$k]['SKU']."' AND VendorSKU!='' AND is_po_number='No'") ;	 						
					  }	
				  }
				 
				 
				  
			}
		
		
	
}
/* Jet PO Code End */

/* Wish PO Code Start */

$sql = "SELECT o.orders_id,od.sku,sum(od.quantity) as quantity,od.VendorSKU,od.price,od.is_po_number FROM ".TABLE_PREFIX."wish_orders as o,".TABLE_PREFIX."wish_order_details as od WHERE od.VendorSKU!='' AND  (od.IsCosmo='Yes' || od.IsNandansons='Yes' || od.IsPerfumePW='Yes') AND o.IsVender='Yes' AND od.po_number='' AND od.is_po_number='No' AND o.orders_id=od.orders_id AND o.status!='Cancelled'  GROUP BY od.sku order by o.orders_id desc ";
$order_res = $obj->select($sql);
$TotalOrders = count($order_res);

if($TotalOrders > 0)
{
	$DetailsArra = array();
	$PWWDetailsArra = array();
	
	$DetailsArra = array(
									"PaymentStatus"			=> "NonePaid",
									"Sent Status"			=> "NotSent",
									"SupplierName"			=> "CosmoPolitain Cosmetics Inc.",
									"TenantToken" 			=> 'bvJ19ZvQp102eZZ9lovacsbWdVXAlP96mNl89GfQcFM=',
									"UserToken"	  			=> 'mU1x1Hyefyx2lyZ1Hj8Ea61Ibi6246Q6MG07JDa7g6Q='
									
					);	
	
	$PWWDetailsArra = 	array(
									"PaymentStatus"			=> "NonePaid",
									"Sent Status"			=> "NotSent",
									"SupplierName"			=> "PWW",
									"TenantToken" 			=> 'bvJ19ZvQp102eZZ9lovacsbWdVXAlP96mNl89GfQcFM=',
									"UserToken"	  			=> 'mU1x1Hyefyx2lyZ1Hj8Ea61Ibi6246Q6MG07JDa7g6Q='
									
					);				
	
	for($i=0;$i<$TotalOrders;$i++)
	{
		$sql = "SELECT sku,cosmo_sku,cosmo_price,cosmo_current_stock,cosmo_price,nandansons_sku,nandansons_price,nandansons_current_stock,perfumeworldwide_sku,perfumeworldwide_price,perfumeworldwide_currentstock FROM ".TABLE_PREFIX."products  WHERE sku='".$order_res[$i]['sku']."' AND ((cosmo_sku!='' AND cosmo_price > 0 ) || (nandansons_sku!='' AND nandansons_price > 0 ) || (perfumeworldwide_sku!='' AND perfumeworldwide_price > 0 ))";
		$prod_res = $obj->select($sql);
		
		
		$TotalProducts = count($prod_res);
		if($TotalProducts > 0)
		{
			
			
			
			if($order_res[$i]["VendorSKU"]==$prod_res[0]["cosmo_sku"])
			{
				$MainPrice = $prod_res[0]["cosmo_price"];
				$VendorSKU_Set = $prod_res[0]["cosmo_sku"]; 
				$CosmoPriceDifference = $prod_res[0]["cosmo_price"] - $prod_res[0]["nandansons_price"];
				$VendorName = "CosmoPolitain Cosmetics Inc.";
				if($CosmoPriceDifference > 0.50 && $prod_res[0]["nandansons_sku"]!='' &&  $prod_res[0]["nandansons_current_stock"]>=$order_res[$i]["quantity"] && $prod_res[0]["nandansons_price"] > 0)
				{
					$NandansonsPriceDifference = $prod_res[0]["nandansons_price"] - $prod_res[0]["perfumeworldwide_price"];
					$MainPrice = $prod_res[0]["nandansons_price"];
					$VendorSKU_Set = $prod_res[0]["nandansons_sku"];
					$VendorName = "Nandansons International"; 
					if($NandansonsPriceDifference > 0.50 && $prod_res[0]["perfumeworldwide_sku"]!='' &&  $prod_res[0]["perfumeworldwide_currentstock"]>=$order_res[$i]["quantity"] && $prod_res[0]["perfumeworldwide_price"] > 0)
					{
						$VendorName = "PWW";
						$VendorSKU_Set = $prod_res[0]["perfumeworldwide_sku"];
						$MainPrice = $prod_res[0]["perfumeworldwide_price"];
					}
				}
				else
				{
					$MainPrice = $prod_res[0]["cosmo_price"];
					$VendorSKU_Set = $prod_res[0]["cosmo_sku"];
					$CosmoPriceDifference = $prod_res[0]["cosmo_price"] - $prod_res[0]["perfumeworldwide_price"];
					$VendorName = "CosmoPolitain Cosmetics Inc.";
					
					if($CosmoPriceDifference > 0.50 && $prod_res[0]["perfumeworldwide_sku"]!='' &&  $prod_res[0]["perfumeworldwide_currentstock"]>=$order_res[$i]["quantity"] && $prod_res[0]["perfumeworldwide_price"] > 0)
					{
						$VendorSKU_Set = $prod_res[0]["perfumeworldwide_sku"];
						$MainPrice = $prod_res[0]["perfumeworldwide_price"];
						$VendorName = "PWW";
					}
					
					
				}
				
			}
			else if($order_res[$i]["VendorSKU"]==$prod_res[0]["nandansons_sku"])
			{
				$MainPrice = $prod_res[0]["nandansons_price"];
				$VendorSKU_Set = $prod_res[0]["nandansons_sku"];
				$NandansonsPriceDifference = $prod_res[0]["nandansons_price"] - $prod_res[0]["cosmo_price"];
				$VendorName = "Nandansons International";
				if($NandansonsPriceDifference > 0.50 && $prod_res[0]["cosmo_sku"]!='' &&  $prod_res[0]["cosmo_current_stock"]>=$order_res[$i]["quantity"] && $prod_res[0]["cosmo_price"] > 0)
				{
					$CosmoPriceDifference = $prod_res[0]["cosmo_price"] - $prod_res[0]["perfumeworldwide_price"];
					$MainPrice = $prod_res[0]["cosmo_price"];
					$VendorSKU_Set = $prod_res[0]["cosmo_sku"];
					$VendorName = "CosmoPolitain Cosmetics Inc.";
					if($CosmoPriceDifference > 0.50 && $prod_res[0]["perfumeworldwide_sku"]!='' &&  $prod_res[0]["perfumeworldwide_currentstock"]>=$order_res[$i]["quantity"] && $prod_res[0]["perfumeworldwide_price"] > 0 )
					{
						$MainPrice = $prod_res[0]["perfumeworldwide_price"];
						$VendorSKU_Set = $prod_res[0]["perfumeworldwide_sku"];
						$VendorName = "PWW";
					}
				}
				else 
				{
					$MainPrice = $prod_res[0]["nandansons_price"];
					$VendorSKU_Set = $prod_res[0]["nandansons_sku"];
					$NandansonsPriceDifference = $prod_res[0]["nandansons_price"] - $prod_res[0]["perfumeworldwide_price"];
					$VendorName = "Nandansons International";
					if($NandansonsPriceDifference > 0.50 && $prod_res[0]["perfumeworldwide_sku"]!='' &&  $prod_res[0]["perfumeworldwide_currentstock"]>=$order_res[$i]["quantity"] && $prod_res[0]["perfumeworldwide_price"]>0)
					{
						$VendorSKU_Set = $prod_res[0]["perfumeworldwide_sku"];
						$MainPrice = $prod_res[0]["perfumeworldwide_price"];
						$VendorName = "PWW";
					}
				}
			}
			else if($order_res[$i]["VendorSKU"]==$prod_res[0]["perfumeworldwide_sku"])
			{
				$MainPrice = $prod_res[0]["perfumeworldwide_price"];
				$VendorSKU_Set = $prod_res[0]["perfumeworldwide_sku"];
				$PWPriceDifference = $prod_res[0]["perfumeworldwide_price"] - $prod_res[0]["cosmo_price"];
				$VendorName = "PWW";
				if($PWPriceDifference > 0.50 && $prod_res[0]["cosmo_sku"]!='' &&  $prod_res[0]["cosmo_current_stock"]>=$order_res[$i]["quantity"] && $prod_res[0]["cosmo_price"] > 0)
				{
					$CosmoPriceDifference = $prod_res[0]["cosmo_price"] - $prod_res[0]["nandansons_price"];
					$VendorSKU_Set = $prod_res[0]["cosmo_sku"];
					$MainPrice = $prod_res[0]["cosmo_price"];
					$VendorName = "CosmoPolitain Cosmetics Inc.";
					if($CosmoPriceDifference > 0.50 && $prod_res[0]["nandansons_sku"]!='' &&  $prod_res[0]["nandansons_current_stock"]>=$order_res[$i]["quantity"] && $prod_res[0]["nandansons_price"] > 0)
					{
						$MainPrice = $prod_res[0]["nandansons_price"];
						$VendorSKU_Set = $prod_res[0]["nandansons_sku"];
						$VendorName = "Nandansons International";
					}
				}
				else
				{
					$NandansonsPriceDifference = $prod_res[0]["perfumeworldwide_price"] - $prod_res[0]["nandansons_price"];
					$MainPrice = $prod_res[0]["perfumeworldwide_price"];
					$VendorSKU_Set = $prod_res[0]["perfumeworldwide_sku"];
					$VendorName = "PWW";
					if($NandansonsPriceDifference > 0.50 && $prod_res[0]["nandansons_sku"]!='' &&  $prod_res[0]["nandansons_current_stock"]>=$order_res[$i]["quantity"] && $prod_res[0]["nandansons_price"] > 0)
					{
						$VendorSKU_Set = $prod_res[0]["nandansons_sku"];
						$MainPrice = $prod_res[0]["nandansons_price"];
						$VendorName = "Nandansons International";
					}
				}
			}
			if($MainPrice > 0 && $VendorSKU_Set!='' && $VendorName=='CosmoPolitain Cosmetics Inc.')
			{
				$DetailsArra["LineItems"][]= array("Cost" => (float)$MainPrice, "QuantityTo3PL" => 0,"Quantity" =>(int)$order_res[$i]["quantity"], "SKU" =>$order_res[$i]["merchant_sku"]);
			}
			if($MainPrice > 0 && $VendorSKU_Set!='' && $VendorName=='PWW')
			{
				$PWWDetailsArra["LineItems"][]= array("Cost" => (float)$MainPrice, "QuantityTo3PL" => 0,"Quantity" =>(int)$order_res[$i]["quantity"], "SKU" =>$order_res[$i]["merchant_sku"]);
			}
			
			}
			}
			
			
			
			if(count($DetailsArra["LineItems"]) > 0)
			{
				$request = 'https://app.skuvault.com/api/purchaseorders/createPO';
				
	 
				$param = json_encode(
					 $DetailsArra
					);
			
				  
			
			      
				  $initialreg = curl_init($request);
				  curl_setopt ($initialreg, CURLOPT_POST, true);
				  curl_setopt ($initialreg, CURLOPT_POSTFIELDS, $param);
				  curl_setopt($initialreg, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ; 
				  curl_setopt($initialreg, CURLOPT_HEADER, False);
				  curl_setopt($initialreg, CURLOPT_HTTPHEADER,array('Content-Type: application/json')); 
				  curl_setopt($initialreg, CURLOPT_RETURNTRANSFER, true);
				  $response = curl_exec($initialreg);
				  curl_close($initialreg);
				  $error_rep = json_decode($response, true);
				  if($error_rep["CreatePOStatus"]!= "Success")
				  {
						$vemail = "naresh.qualdev@gmail.com";
						$MSG =  "Create Wish PO Number Error For Cosmo In Skuvault Of Maxaroma On ".date("Y-m-d H:i:s")."Error is ".$error_rep["Errors"];
						$test = @mail($vemail,"Create Wish PO Number Error For Cosmo In Skuvault Of Maxaroma",$MSG);
				  }
				  else
				  {
					  for($k=0;$k<count($DetailsArra["LineItems"]);$k++)
					  {
					   $UpdateDetails = array(
												"is_po_number" => "Yes"
											);
					   $result = $obj->update(TABLE_PREFIX.'wish_order_details', $UpdateDetails, "sku ='".$DetailsArra["LineItems"][$k]['SKU']."' AND VendorSKU!='' AND is_po_number='No'") ;	 						
					  }	
				  }
				 
				 
				  
			}
			if(count($PWWDetailsArra["LineItems"]) > 0)
			{
				$request = 'https://app.skuvault.com/api/purchaseorders/createPO';
				
	 
				$param = json_encode(
					 $PWWDetailsArra
					);
			
				  
			
			      
				  $initialreg = curl_init($request);
				  curl_setopt ($initialreg, CURLOPT_POST, true);
				  curl_setopt ($initialreg, CURLOPT_POSTFIELDS, $param);
				  curl_setopt($initialreg, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ; 
				  curl_setopt($initialreg, CURLOPT_HEADER, False);
				  curl_setopt($initialreg, CURLOPT_HTTPHEADER,array('Content-Type: application/json')); 
				  curl_setopt($initialreg, CURLOPT_RETURNTRANSFER, true);
				  $response = curl_exec($initialreg);
				  curl_close($initialreg);
				  $error_rep = json_decode($response, true);
				  if($error_rep["CreatePOStatus"]!= "Success")
				  {

						$vemail = "naresh.qualdev@gmail.com";
						$MSG =  "Create Wish PO Number Error For Perfume World Wide  In Skuvault Of Maxaroma On ".date("Y-m-d H:i:s")."Error is ".$error_rep["Errors"];
						$test = @mail($vemail,"Create Jet PO Number Error For Perfume World Wide In Skuvault Of Maxaroma",$MSG);
				  }
				  else
				  {
					  for($k=0;$k<count($PWWDetailsArra["LineItems"]);$k++)
					  { 
						$UpdateDetails = array(
												"is_po_number" => "Yes"
											);
						$result = $obj->update(TABLE_PREFIX.'wish_order_details', $UpdateDetails, "sku ='".$PWWDetailsArra["LineItems"][$k]['SKU']."' AND VendorSKU!='' AND is_po_number='No'") ;	 						
					  }	
				  }
				 
				 
				  
			}
		
		
	
}

/* Wish PO Code End */

$vemail = "tempchecknew@gmail.com";
$sendmessage = "Jet And Wish Vendor Create PO Cron Ended In Maxaroma on ".date('m-d-Y');	
$test 	= @mail($vemail,"Jet And Wish Vendor Create PO Cron Ended In Maxaroma",$MSG);
unset($obj);
exit;
?>
