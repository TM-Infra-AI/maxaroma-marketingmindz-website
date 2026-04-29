<?php
	//echo "<pre>";print_r($Cart);exit;
	require_once(CLASS_PATH."encrypt.cls.php");
	
	$sql = "SELECT pm_group_name, pm_gateway_name,pm_details FROM `".TABLE_PREFIX."payment_methods` WHERE pm_status = 'Active' AND pm_group_name = 'PAYMENT_PAYWITHAFTERPAY' LIMIT 0,1";
	$db_res = $obj->select($sql);

	$arrPEVar = array();
	if( count($db_res) > 0)
	{
		$Payment_Method = $db_res[0]['pm_name'];
		
		/* 
		if($_SESSION['sess_useremail'] == "gequaldev@gmail.com"){
			$db_res[0]['pm_details'] = 'a:6:{s:27:"PaywithAfterpay_Merchant_ID";s:16:"6+/un9C1fWJXNwA=";s:35:"PaywithAfterpay_Merchant_Secret_Key";s:148:"BcHBAYMgDADAvfvpsyICiVZxlRICFHQZCrpC78oEyThn2AY9a5Ol/3lUycCd4YrVZ7nYyKAZ6buqZ1sECQL3AvYcopMFth0/o8CzAQw7I0Q/zW088j1c1B9r2iBUdO/ScDalsvIiRpL26MpRp2z+";s:36:"PaywithAfterpay_Header_Authorization";s:244:"BcHJCoJAAABQJOjXszKtY4IkRc6kDokOpJ1qRiRbXVDTKKx7tFide0+byik49feqlcaIYgYYO1bho7b9ezezKue1wTqzHIWqo72AxHEF4ycBfrxwgi2bB2cODegKvIZ0kt89pMo14IUNxVgf9DJ0j7trrJm4Cxnagn6U6NSpoBQG0ChaqP5ws9rN1Tq4hzcigduLYKuEwXLCmemX2PWSzcuCzBJDAB9zCf2iiyQ4B1JETato/AE=";s:33:"PaywithAfterpay_Header_User_Agent";s:152:"AWsAlP+h0NLTyfR4p8fU8MXMhZC3inmwnIaf+MTM8f2776nYxr/Pxc7M9HilvMzui4+Ej7eKlKinoay4jo28uYjFqdW/0L7HwMj7h4iLj7qMjo+SuYN58MvNzPyRjrv+wPn5trvEz8TRyujRhb7O9g==";s:32:"PaywithAfterpay_Transaction_Mode";s:7:"Sandbox";s:29:"PaywithAfterpay_Currency_Code";s:3:"USD";}';
		}
		*/
		 
		$arrPEVar		= unserialize($db_res[0]['pm_details']);
	
		#############################
		$eobj = new Encrypt();
		$arrPEVar['PaywithAfterpay_Merchant_ID']   = $eobj->decrypt($arrPEVar['PaywithAfterpay_Merchant_ID']);
		$arrPEVar['PaywithAfterpay_Merchant_Secret_Key']   = $eobj->decrypt($arrPEVar['PaywithAfterpay_Merchant_Secret_Key']);
		$arrPEVar['PaywithAfterpay_Header_Authorization']   = $eobj->decrypt($arrPEVar['PaywithAfterpay_Header_Authorization']);
		$arrPEVar['PaywithAfterpay_Header_User_Agent']   = $eobj->decrypt($arrPEVar['PaywithAfterpay_Header_User_Agent']);
		unset($eobj);
		#############################
		// echo "<pre>";print_r($arrPEVar);exit;

		if( strtoupper(trim($arrPEVar['PaywithAfterpay_Transaction_Mode'])) == 'SANDBOX'){
			$TRANSACTION_MODE = 'sandbox';
			$Payment_Url = "https://api.us-sandbox.afterpay.com/v2/";
			//$Payment_Url = "https://api.us-sandbox.afterpay.com/v1/";
			$Token_JS_Url = "https://portal.sandbox.afterpay.com/afterpay.js";
		}else{
			$TRANSACTION_MODE = '';
			$Payment_Url = "https://api.us.afterpay.com/v2/";
			$Token_JS_Url = "https://portal.afterpay.com/afterpay.js";
		}
	}
	else
	{
		$msg = "Pay by Afterpay service is temporarily unavailable at this time, Please contact administrator.";
		$commonobj->setDisplayMessage($msg);

		header("location:".$SECURED_PATH."index.php?file=shoppingcart");
		exit;
	}

	function GetAfterPayResult($data_payload = array(),$ApiType="",$IsPost = "Yes"){
		global $TRANSACTION_MODE,$Payment_Url,$arrPEVar;
		
		if(empty($data_payload)){
			$data_payload = json_encode($data_payload);
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $Payment_Url.$ApiType);
		curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		if($IsPost == "Yes"){
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_payload);
		}

		if(!empty($data_payload)){

		}

		$headers = array();
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Authorization: Basic '.$arrPEVar["PaywithAfterpay_Header_Authorization"];	//taken from doc
		$headers[] = 'User-Agent: '.$arrPEVar["PaywithAfterpay_Header_User_Agent"];
		$headers[] = 'Accept: application/json';

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($ch);

		curl_close($ch);

		$resultArr = json_decode($response,true);
		//echo "<pre>sss";print_r($resultArr);exit;
		return $resultArr;
	}

	function getOrderTotalDetails(){
		global $Cart;

		/* $tempBillingAdd  = $Cart->getBillingAddress();
		$tempShippingAdd = $Cart->getShippingAddress();

		$OrderSubTotal 	 =  $Cart->getSubTotal();
		$ShippingCharge	 =  $Cart->getShippingCharge();

		$GiftCouponInfo  = $Cart->getGiftCouponInfo(); 	// GC Discount Info
		$NetDiscount 	 = $Cart->getNetDiscount(); 	    // Coupon + Auto + Qty
		$TotalDiscount 	 = $Cart->NumberFormat($NetDiscount + $GiftCouponInfo['Value']);

		$OrderSubTotal 	 = $Cart->NumberFormat($OrderSubTotal - $TotalDiscount);
		$Payment_Amount  = $OrderSubTotal +$ShippingCharge ; // For Order Total */

		$Payment_Amount = $Cart->getNetTotal();
		$returnArr['Payment_Amount'] = $Payment_Amount;
		$returnArr['Payment_Currency'] = "USD";
		return $returnArr;
	}
	
	function getRefundId($order_id){
		global $obj;
		
		$slq = "SELECT payment_gateway_response,afterpay_transaction_id FROM `".TABLE_PREFIX."orders` 
	 		WHERE orders_id = '".(int)$order_id."' LIMIT 0,1";
				
		 $order_res = $obj->select($slq);	
		 
		 if(trim($order_res[0]['payment_gateway_response']) == '' || empty($order_res))
		 {
			return NULL;
		 }else{
			if($order_res[0]['afterpay_transaction_id'] != ""){
				$returnArr['transaction_id'] = $order_res[0]['afterpay_transaction_id'];
				$returnArr['transaction_token'] = "";
			}else{
				$res_str = $order_res[0]['payment_gateway_response'];
				$res_arr = explode("Capture Response::",$res_str);
				// echo "<pre>";print_r($res_arr);
				
				$gateway_response = json_decode($res_arr[1],true);
				
				$returnArr['transaction_id'] = $gateway_response['id'];
				$returnArr['transaction_token'] = $gateway_response['token'];
			}
			return $returnArr;
		 }
	}
?>
