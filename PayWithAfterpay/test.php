<?
require_once("../lib/configuration.php");
/////////// Log Start ////////////
require_once("afterpay_functions.php");
	
	/* function getRefundId11($order_id){
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
	 */
		
		$detail_arr = getRefundId('118445');
		echo "<pre>";print_r($detail_arr);
			
		$detail_arr = getRefundId('121417');
		
		echo "<pre>";print_r($detail_arr);exit;
		/////////// Log End ////////////
?>