<?php
set_time_limit(0);
ini_set('memory_limit',"500M");

//include_once("/home/peraroma/public_html/lib/config_setting.php");
include_once("../lib/configuration.php");

error_reporting(E_ALL);
ini_set('display_errors',1);

	$transaction_mode = "Sandbox";

	if($transaction_mode == "Live"){
		$curl_transurl = "https://api.supplieroasis.com";
	}else{
		$curl_transurl = "https://api.test.supplieroasis.com";
	}
	
	echo $curl_transurl;

	$start_date = date("Y-m-d");
	$end_date = $start_date;
	$header_arr = array(
						'Connection: keep-alive',
						'content-type: application/xml',
						'Authorization: Basic UEVSQVJPTUE6T3ZlcnN0b2NrMSE',
						'accept: application/xml',
						'Host: api.test.supplieroasis.com'
					   );
	$requestarr = array(
						'startTime' => $start_date,
						'endTime' => $end_date
						);				   
	
	///salesorders?startTime=2013-08-01&endTime=20130802 HTTP/1.1
	//$ch = curl_init($curl_transurl.'/salesorders');
	//$ch = curl_init($curl_transurl.'/salesorders?startTime='.$start_date.'&endTime='.$end_date); 
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL, $curl_transurl.'/salesorders?startTime='.$start_date.'&endTime='.$end_date);
	
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	//curl_setopt($ch, CURLOPT_POST, 1);
	//curl_setopt($ch, CURLOPT_POSTFIELDS, $requestarr);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	//curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header_arr); 
	
	curl_setopt($ch, CURLOPT_TIMEOUT, 100);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
	
	//execute post
	$result = curl_exec($ch);
	curl_close($ch); // close cURL handler
	
	echo "Response:: <pre>";print_r($result); exit;
	$response1 		= simplexml_load_string($result);
	$json_string 	= json_encode($response1);
	$result_array	= json_decode($json_string, TRUE);
	
	$res_data = $result_array['processedSalesOrder'];
	$count_rec 	= count($res_data);
	
	if($count_rec > 0)
	{
		for($i=0; $i < $count_rec;$i++){	
			
			if($res_data[$i]['salesChannelOrderNumber']!='')
			{
				$sql 	=	"SELECT orders_id,salesChannelOrderNumber FROM `".TABLE_PREFIX."overstock_orders` WHERE salesChannelOrderNumber='".$res_data[$i]['salesChannelOrderNumber']."'";
				$db_res = $obj->select($sql);
			}
			
			if(count($db_res)<=0) 
			{
					$InsertOrder= array(
								'salesChannelOrderNumber'     => $res_data[$i]['salesChannelOrderNumber'],
								'salesChannelName'    	=> $res_data[$i]['salesChannelName'],
								'orderDate'     => $res_data[$i]['orderDate'],
								'warehouseName_code'     => $res_data[$i]['warehouseName']['code'],
								'shipping_contactName'     => $res_data[$i]['shipToAddress']['contactName'],
								'shipping_address1'     => $res_data[$i]['shipToAddress']['address1'],
								'shipping_city'     => $res_data[$i]['shipToAddress']['city'],
								'shipping_stateOrProvince'     => $res_data[$i]['shipToAddress']['stateOrProvince'],
								'shipping_postalCode'     => $res_data[$i]['shipToAddress']['postalCode'],
								'shipping_countryCode'     => $res_data[$i]['shipToAddress']['countryCode'],
								'shipping_phone'     => $res_data[$i]['shipToAddress']['phone'],

								'returnAddress_contactName'     => $res_data[$i]['returnAddress']['contactName'],
								'returnAddress_address1'     => $res_data[$i]['returnAddress']['address1'],
								'returnAddress_city'     => $res_data[$i]['returnAddress']['city'],
								'returnAddress_stateOrProvince'     => $res_data[$i]['returnAddress']['stateOrProvince'],
								'returnAddress_postalCode'     => $res_data[$i]['returnAddress']['postalCode'],
								'returnAddress_countryCode'     => $res_data[$i]['returnAddress']['countryCode'],
								'returnAddress_phone'     => $res_data[$i]['returnAddress']['phone'],

								'isThirdPartyBilling'     => $res_data[$i]['shippingSpecifications']['isThirdPartyBilling'],
								'isSignatureRequired'     => $res_data[$i]['shippingSpecifications']['isSignatureRequired'],
								'isDeclaredValueRequired'     => $res_data[$i]['shippingSpecifications']['isDeclaredValueRequired'],
								'shippingServiceLevel_code'     => $res_data[$i]['shippingSpecifications']['smallParcelShipment']['shippingServiceLevel']['code'],
								'shippingAccountNumber'     => $res_data[$i]['shippingSpecifications']['smallParcelShipment']['shippingAccountNumber'],
								'carrier_code'     => $res_data[$i]['shippingSpecifications']['smallParcelShipment']['carrier']['code'],
								'isExport'     => $res_data[$i]['shippingSpecifications']['isExport'],

								'overstock_orderId'     => $res_data[$i]['orderId'],
								'overstock_status'     => $res_data[$i]['status'],
								'sent_overstock'     => 'No',
								'ship_status'     => 'Pending',
								'status'     => 'Pending'
						   );
		
					$OrderID = $obj->insert(TABLE_PREFIX.'overstock_orders', $InsertOrder);
					if(isset($OrderID) && !empty($OrderID))
					{
						$new_Order_ids = 'OVERSTOCK'.$OrderID;
						$updateOrder = array (
							'our_orders_id'	 => $new_Order_ids
						 );	
						$udpRefer = $obj->update(TABLE_PREFIX.'overstock_orders', $updateOrder, "`orders_id` = '".$OrderID."'");	
							
					}
					
					$order_detail = $res_data[$i]['processedSalesOrderLine'];
					$count_od 	= count($order_detail);
					
					for($j=0 ; $j < $count_od; $j++){
						 $InsertOrder_Details = array();
			 
						 $InsertOrder_Details	= array(
											'orders_id' 			=> $OrderID,
											'salesChannelLineId'    => $order_detail[$j]['salesChannelLineId'],
											'partnerSKU'    => $order_detail[$j]['partnerSKU'],
											'barcode'    => $order_detail[$j]['barcode'],
											'salesChannelSKU'    => $order_detail[$j]['salesChannelSKU'],
											'quantity'    => $order_detail[$j]['quantity'],
											'specialHandling_code'    => $order_detail[$j]['specialHandling']['code'],
											'lineId'    => $order_detail[$j]['lineId'],
											'itemId'    => $order_detail[$j]['itemId'],
											'itemName'    => $order_detail[$j]['itemName'],
											'lineStatus'    => $order_detail[$j]['lineStatus'],
											'unitCost'    => $order_detail[$j]['unitCost'],
											
											'shipconfirm_quantityShipped'    => $order_detail[$j]['shipConfirmationDetail']['quantityShipped'],
											'packagedetail_packageID'    => $order_detail[$j]['shipConfirmationDetail']['packageDetail']['packageID'],
											'packagedetail_packageType_code'    => $order_detail[$j]['shipConfirmationDetail']['packageDetail']['packageType']['code'],
											'packagedetail_packageNumber'    => $order_detail[$j]['shipConfirmationDetail']['packageDetail']['packageNumber'],
											'packagedetail_packageWeight'    => $order_detail[$j]['shipConfirmationDetail']['packageDetail']['packageWeight'],
											'packagedetail_trackingNumber'    => $order_detail[$j]['shipConfirmationDetail']['packageDetail']['trackingNumber'],
											
											'shipmentdetail_shipmentID'    => $order_detail[$j]['shipConfirmationDetail']['shipmentDetail']['shipmentID'],
											'shipmentdetail_shipmentCarrier'    => $order_detail[$j]['shipConfirmationDetail']['shipmentDetail']['shipmentCarrier'],
											'shipmentdetail_billingAccountNumber'    => $order_detail[$j]['shipConfirmationDetail']['shipmentDetail']['billingAccountNumber'],
											'shipmentdetail_dateShipped'    => $order_detail[$j]['shipConfirmationDetail']['shipmentDetail']['dateShipped'],
											'shipmentdetail_dateConfirmed'    => $order_detail[$j]['shipConfirmationDetail']['shipmentDetail']['dateConfirmed']
										);
						
						$orders_detail_id = $obj->insert(TABLE_PREFIX.'overstock_order_details', $InsertOrder_Details);
					}
			}
		}
	}
?>
