<?php
	//https://docs.afterpay.com/online-api-v2-b857508478e7.html#capture-without-auth
	//$url = "https://api.us-sandbox.afterpay.com/v2/payments/auth";
	
	require_once("../lib/configuration.php");
	require_once("afterpay_functions.php");
	
	function process_Refund_Payment_Afterpay($orders_id = NULL,$refund_amt=0,$refund_comment = '',$refund_shipping_cost=0,$refund_restocking_fee=0)
	{ 
		global $obj;
		
		if((int)$orders_id==0)
		{
			return NULL;
		}
		
		## Must required if Partial Refund
		$refund_amount      =  number_format($refund_amt,2,'.','');
		
		if($refund_amount <=0)
		{
			$p_msg = "Your order can not been refund.<br>For refund order, Refund Amount must be greater than zero.";
			return $p_msg;	
		}
		$detail_arr = getRefundId($orders_id);
		//echo "<pre>";print_r($_SESSION);exit;
		if(!empty($detail_arr) && $detail_arr['transaction_id'] != ""){
			$transaction_id = $detail_arr['transaction_id'];
		}else{
			$p_msg = "Sorry,Transaction id can not found from payment gateway response.";
			return $p_msg;	
		}
		
		// echo "<pre>";print_r($detail_arr);exit;
		$payload = array();
		$payload['amount']['amount'] = $refund_amount;
		$payload['amount']['currency'] = "USD";
		
		$payloaddata = json_encode($payload);
		
		$url = "payments/".$transaction_id."/refund";
		
		$refund_arr = GetAfterPayResult($payloaddata,$url);
			
		## Update Order Table Start #############
			$slq = "SELECT total_refund_amount,refund_transaction_response, refund_comment,order_total FROM `".TABLE_PREFIX."orders` WHERE orders_id = '".(int)$orders_id."' LIMIT 0,1";
			$order_res = $obj->select($slq);
			
			
			## Make Gate Response String From Array
			$gateway_response = json_encode($refund_arr);
			// echo "<pre>";print_r($refund_arr);exit;
			
			$refund_transaction_response   = $order_res[0]['refund_transaction_response'];
			$refund_transaction_response  .= $gateway_response."<br><br> ";
			$refund_transaction_response  .= "Response recieved on date ".date('m/d/Y H:i:s')."<br><br> ";
			
			$new_refund_comment  = $order_res[0]['refund_comment'];
			$new_refund_comment  .= "<br>".$refund_comment."<br><br>";
			$new_refund_comment  .= "Refund processed by(original payment) : <b>".$_SESSION['sess_admin_email']."</b> on date ".date('m/d/Y H:i:s')."<br>Original Shipping Cost:".$refund_shipping_cost."<br>Restocking Fee:".$refund_restocking_fee."<br><br>" ;
			
			$arrUp = array();
			$arrUp['refund_transaction_response'] = $refund_transaction_response;
			$arrUp['refund_comment'] = $new_refund_comment;
			$arrUp['refund_shipping_cost'] = $refund_shipping_cost;
			$arrUp['refund_restocking_fee'] = $refund_restocking_fee;
			$arrUp['order_upd_datetime'] = date("Y-m-d H:i:s");
					
					
			//echo "<pre>===".$refund_amount;print_r($order_res);
			//echo "<pre>";print_r($refund_arr);exit;
			
			if(isset($refund_arr['refundId']) && $refund_arr['refundId'] > 0 ){
				//$refundId = $refund_arr['refundId'];
				//$refundedAt = $refund_arr['refundedAt'];	
				
				$total_refund_amount  			= $order_res[0]['total_refund_amount'] + $refund_amount;
				$arrUp['total_refund_amount']   = number_format($total_refund_amount,2,'.','');
				
				$p_msg = "Refund processed successfully.<br>This transaction has been approved.";
				
				if($total_refund_amount==$order_res[0]['order_total']) {
					$arrUp['status'] = "Refund";
				}
				
				$result = $obj->update(TABLE_PREFIX . 'orders', $arrUp,"orders_id ='" . $orders_id . "'");
			}else{
				$p_msg = "Your order has not been refunded.<br>".$refund_arr['message'];
			}
			
			
		## Update Order Table End #############
			return $p_msg;
	}
?>
