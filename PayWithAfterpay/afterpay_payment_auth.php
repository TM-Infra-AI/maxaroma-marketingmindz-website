<?php
	//https://docs.afterpay.com/online-api-v2-b857508478e7.html#capture-without-auth
	//$url = "https://api.us-sandbox.afterpay.com/v2/payments/auth";
	
	require_once("../lib/configuration.php");
	require_once("afterpay_functions.php");
	
	if(isset($_REQUEST) && $_REQUEST['status'] == "SUCCESS"){
		$payload = array();
		$payload['token'] = $_REQUEST['orderToken'];
		
		$payloaddata = json_encode($payload);
	
		$auth_arr = GetAfterPayResult($payloaddata,"payments/auth");
		//echo "<pre>";print_r($payload);exit;
		
		$trans_id = $auth_arr['id'];
		$trans_token = $auth_arr['token'];
		
		$_SESSION['AfterPay']['AP_Auth_Token'] = $trans_token;
		$_SESSION['AfterPay']['AP_Auth_ID'] = $trans_id;
		$_SESSION['AfterPay']['AP_Auth_Status'] = $auth_arr['status'];
		
		/////////// Log Start ////////////
		$cur_date = date("Y-m-d");
		$myFile = $physical_path.'PayWithAfterpay/afterpay_logs/afterpay-log'.$cur_date.'.txt';
		if(fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');

			$stringData .= chr(13) . chr(13) . 'Auth REQUEST == ' . serialize($payload) . chr(13) . chr(13) ;
			$stringData .= chr(13) . chr(13) . 'Auth RESPONSE == ' . serialize($auth_arr) . chr(13) . chr(13);

			fwrite($fh, $stringData);
			fclose($fh);
		}
		/////////// Log End ////////////
		
		if($auth_arr['status'] == "APPROVED" && $auth_arr['paymentState'] == "AUTH_APPROVED" && $trans_token != ""){
			$_SESSION['AfterPay']['AP_Auth_Amt'] = $auth_arr['originalAmount']['amount'];
			$_SESSION['AfterPay']['AP_Auth_Currency'] = $auth_arr['originalAmount']['currency'];
			
			$cust_id = (int)$_SESSION['sess_icustomerid'];
			
			//echo "<pre>";print_r($auth_arr);exit;
			if(isset($cust_id) && $cust_id > 0){
				$payment_gateway_response = "Auth Response::".json_encode($auth_arr);
				$updAray = array (
									'payment_gateway_response' 	=> $payment_gateway_response,
									'afterpay_transaction_id' 	=> $trans_id
								  );
				$where_cond = " orders_id='".$Cart->getOrderID()."' ";				  
				$uporderres = $obj->update(TABLE_PREFIX.'orders',$updAray, $where_cond);
				
				//~ if($_SESSION['sess_useremail'] == "gequaldev@gmail.com"){
					//~ echo "ddd";exit;
				//~ }
				
				//header("location:".$SECURED_PATH."index.php?file=afterpay_order_confirm");
				header("Location:" . $SECURED_PATH . "PayWithAfterpay/afterpay_payment_capture.php?return_flag=confirm_payment");
				exit;
			}
		}else{
			//payment authorise fails
			//status DECLINED or paymentState AUTH_DECLINED
			
			$transaction_info = "This transaction has been Declined.";
			$Payment_response = json_encode($auth_arr);
			
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $Payment_response
							  );
							  
			$where_cond = " orders_id='".$Cart->getOrderID()."' ";	
							  
			$uporderres = $obj->update(TABLE_PREFIX.'orders',$updAray, $where_cond);
			
			$ErrorLongMsg = "Error in Processing Request, Please try again.";
			$commonobj->setDisplayMessage($ErrorLongMsg);
			header("location:".$SECURED_PATH."index.php?file=billing");
			exit;
		}
	}else{
		//order cancelled by customer
		//status >> CANCELLED
		
		//$a = $Cart->destroyCart();
		
		$transaction_info = "This transaction has been Declined.";
		$updAray = array (
							'status' 	   				=> 'Declined',
							'transaction_info' 			=> $transaction_info,
							'payment_gateway_response' 	=> "This transaction has been Declined by User."
						  );
						  
		$where_cond = " orders_id='".$Cart->getOrderID()."' ";	
						  
		$uporderres = $obj->update(TABLE_PREFIX.'orders',$updAray, $where_cond);
		
		$_SESSION['AfterPay'] = array();
		unset($_SESSION['AfterPay']);
			
		$ErrorLongMsg = "Error in Processing Request, Please try again.";
		$commonobj->setDisplayMessage($ErrorLongMsg);
		header("location:".$SECURED_PATH."index.php?file=shoppingcart");
		exit;
	}
?>
