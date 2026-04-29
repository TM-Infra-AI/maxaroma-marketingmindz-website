<?php
	//https://docs.afterpay.com/online-api-v2-b857508478e7.html#capture-without-auth
	//$url = "https://api.us-sandbox.afterpay.com/v2/payments/auth";
	
	require_once("../lib/configuration.php");
	require_once("afterpay_functions.php");
	
	$order_id = $_SESSION["phoneorder_detail"]["order_id"];
	if(isset($_REQUEST) && $_REQUEST['status'] == "SUCCESS"){
		$payload = array();
		$payload['token'] = $_REQUEST['orderToken'];
		
		$payloaddata = json_encode($payload);
	
		$auth_arr = GetAfterPayResult($payloaddata,"payments/auth");
		//echo "<pre>";print_r($payload);exit;
		
		$trans_id = $auth_arr['id'];
		$trans_token = $auth_arr['token'];
		
		$_SESSION["phoneorder_detail"]["Afterpay"]['AP_Auth_Token'] = $trans_token;
		$_SESSION["phoneorder_detail"]["Afterpay"]['AP_Auth_ID'] = $trans_id;
		$_SESSION["phoneorder_detail"]["Afterpay"]['AP_Auth_Status'] = $auth_arr['status'];
		
		if($auth_arr['status'] == "APPROVED" && $auth_arr['paymentState'] == "AUTH_APPROVED" && $trans_token != ""){
			$_SESSION["phoneorder_detail"]["Afterpay"]['AP_Auth_Amt'] = $auth_arr['originalAmount']['amount'];
			$_SESSION["phoneorder_detail"]["Afterpay"]['AP_Auth_Currency'] = $auth_arr['originalAmount']['currency'];
			
			$cust_id = (int)$_SESSION["phoneorder_detail"]["customer_id"];
			
			//echo "<pre>";print_r($auth_arr);exit;
			if(isset($cust_id) && $cust_id > 0){
				$payment_gateway_response = "Auth Response::".json_encode($auth_arr);
				$updAray = array (
									'payment_gateway_response' 	=> $payment_gateway_response,
									'afterpay_transaction_id' 	=> $trans_id
								  );
				$where_cond = " orders_id='".$order_id."' ";				  
				
				//echo "<pre>";print_r($updAray);exit;
				$uporderres = $obj->update(TABLE_PREFIX.'orders',$updAray, $where_cond);
				
				//header("location:".$SECURED_PATH."index.php?file=afterpay_order_confirm");
				header("Location:" . $SECURED_PATH . "PayWithAfterpay/afterpay_payment_capture_phoneorder.php?return_flag=confirm_payment");
				exit;
			}
		}else{
			//payment authorise fails
			//status DECLINED or paymentState AUTH_DECLINED
			
			$err_msg = "Something went wrong, Please try again.";
			$commonobj->setDisplayMessage($err_msg);

			header("location:".$Site_URL."payment/".base64_encode($order_id)."/".base64_encode("0"));
			exit;
		}
	}else{
		//order cancelled by customer
		//status >> CANCELLED
		
		//$a = $Cart->destroyCart();
		
		
		$_SESSION["phoneorder_detail"]["Afterpay"] = array();
		unset($_SESSION["phoneorder_detail"]["Afterpay"]);
			
		$err_msg = "Something went wrong, Please try again.";
		$commonobj->setDisplayMessage($err_msg);

		header("location:".$Site_URL."payment/".base64_encode($order_id)."/".base64_encode("0"));
		exit;
	}
?>
