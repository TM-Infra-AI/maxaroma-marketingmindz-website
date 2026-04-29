<?php
	//https://docs.afterpay.com/online-api-v2-b857508478e7.html#capture-without-auth
	//$url = "https://api.us-sandbox.afterpay.com/v2/payments/auth";
	
	require_once("../lib/configuration.php");
	require_once("afterpay_functions.php");
	
	if(isset($_REQUEST) && $_REQUEST['return_flag'] == "confirm_payment"){
		$trans_id = $_SESSION['AfterPay']['AP_Auth_ID'];
		$trans_token = $_SESSION['AfterPay']['AP_Auth_Token'];
		//$auth_amt = $_SESSION['AfterPay']['AP_Auth_Amt'];
		//$auth_currency = $_SESSION['AfterPay']['AP_Auth_Currency'];
		
		$payload = array();
		
		$PaymentArr  = getOrderTotalDetails(); // For Order Total
		$Payment_Amount  = $PaymentArr['Payment_Amount'];
		$Payment_Currency  = $PaymentArr['Payment_Currency'];

		$payload['amount']['amount'] = $Payment_Amount;
		$payload['amount']['currency'] = $Payment_Currency;
		$payload['merchantReference'] = "OR".$Cart->getOrderID();
		$payloaddata = json_encode($payload);
		//echo "<pre>";print_r($payload);exit;
		
		$url = "payments/".$trans_id."/capture";
		$capture_arr = GetAfterPayResult($payloaddata,$url);
		$payment_gateway_response = json_encode($capture_arr);
		
		if($capture_arr['status'] == "APPROVED" && ($capture_arr['paymentState'] == "PARTIALLY_CAPTURED" || $capture_arr['paymentState'] == "CAPTURED")){
			$transaction_info = "This transaction has been approved.";
			
			$updAray = array (
								'pay_status' 	   			=> 'Paid',
								'status' 	   				=> 'Pending',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response
							  );
			$where_cond = " orders_id='".$Cart->getOrderID()."' ";				  
			$uporderres = $obj->update(TABLE_PREFIX.'orders',$updAray, $where_cond);


			$_SESSION['AfterPay'] = array();
			unset($_SESSION['AfterPay']);
			
			header("location:".$Site_URL."index.php?file=order_receipt");			
			exit;
		}else{
			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response
							  );
							  
			$where_cond = " orders_id='".$Cart->getOrderID()."' ";	
							  
			$uporderres = $obj->update(TABLE_PREFIX.'orders',$updAray, $where_cond);

			$Message = "Error in Processing Request, Please try again.";
			if(isset($capture_arr['errorId'])){
				$Message = $capture_arr['message'];
			}
			$commonobj->setDisplayMessage($Message);
			
			$_SESSION['AfterPay'] = array();
			unset($_SESSION['AfterPay']);
			
			header("location:".$SECURED_PATH."index.php?file=shoppingcart");
			exit;
		}
	}else{
		//order order not confirmed by customer
		//status >> CANCELLED
		$transaction_info = "This transaction has been Declined.";
		$updAray = array (
							'status' 	   				=> 'Declined',
							'transaction_info' 			=> $transaction_info,
							'payment_gateway_response' 	=> $transaction_info
						  );
						  
		$where_cond = " orders_id='".$Cart->getOrderID()."' ";	
						  
		$uporderres = $obj->update(TABLE_PREFIX.'orders',$updAray, $where_cond);
		
		$ErrorLongMsg = "Error in Processing Request, Please try again.";
		$commonobj->setDisplayMessage($ErrorLongMsg);
		header("location:".$SECURED_PATH."index.php?file=shoppingcart");
		exit;
	}
?>

