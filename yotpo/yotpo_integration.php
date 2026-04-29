<?php
if(YOTPO_PROGRAM==1 || 1){
	include_once(SITE_DIR."yotpo/yotpo.cls.php");
	$yotpoObj = new Yotpo;
	switch ($yotpo_event) { 
		case 'newCustomer':
			$params = array(
				//'id' => $iCustomerId,
				'email' => $aData['email'],
				'first_name' => $aData['first_name'],
				'last_name' => $aData['last_name']
			);
			$data = $yotpoObj->make_post_request('customers',$params);
			
			$params = array(
				'customer_email' => $aData['email'],
				'day' => $bd_param[1],
				'month' => $bd_param[0],
				'year' => $bd_param[2]
			);
			$data = $yotpoObj->make_post_request('customer_birthdays',$params);
			break;	
			
		case 'newOrder':
			if(isset($_SESSION['sess_useremail']) && $_SESSION['sess_useremail'] != '' && $OrderRs[0]['status'] != 'Declined'){
				$data = $yotpoObj->make_get_request('customers?customer_email='.$_SESSION['sess_useremail']);
				/*if(isset($data->errors)){
					//New Customer
					$params = array(
						'id' => $_SESSION['sess_icustomerid'],
						'email' => $_SESSION['sess_useremail'],
						'first_name' => $_SESSION['sess_first_name'],
						'last_name' => $_SESSION['sess_last_name']
					);
					$data = $yotpoObj->make_post_request('customers',$params);	
				}*/

				//New Order
				if(!isset($data->errors)){
					$currency_info = explode('#',$OrderRs[0]['currency_info']);
					$discount_amount_cents = $OrderRs[0]['wire_discount'] + $OrderRs[0]['auto_discount'] + $OrderRs[0]['quantity_discount'] + $OrderRs[0]['reward_discount']  + $OrderRs[0]['coupon_amount'];
					$params = array(
						'customer_email' => $_SESSION['sess_useremail'],
						'total_amount_cents' => ($OrderRs[0]['order_total'] * 100),
						'currency_code' => $currency_info[0],
						'order_id' => $OrderRs[0]['orders_id'],
						'status' => 'Pending',
						'created_at' => $OrderRs[0]['order_datetime'],
						'ip_address' => $_SERVER['REMOTE_ADDR'],
						'user_agent' => $_SERVER['HTTP_USER_AGENT'],
						'discount_amount_cents' => ($discount_amount_cents * 100),
						'items' => $yotpo_items,
					);
					$data = $yotpoObj->make_post_request('orders',$params);	
				}
				//print_r($data);exit;
			}
			break;

		case 'completeOrder':
			$data = $yotpoObj->make_get_request('customers?customer_email='.$order_res_chk[0]['bill_email']);
			if(!isset($data->errors)){
				//Complete Order
				$currency_info = explode('#',$order_res_chk[0]['currency_info']);
				$discount_amount_cents = $order_res_chk[0]['gc_amount'] + $order_res_chk[0]['reward_discount'] + $order_res_chk[0]['apply_credit'] + $order_res_chk[0]['auto_discount'] + $order_res_chk[0]['bogo_discount'] + $order_res_chk[0]['quantity_discount']  + $order_res_chk[0]['coupon_amount'];
				$params = array(
					'customer_email' => $order_res_chk[0]['bill_email'],
					'total_amount_cents' => ($order_res_chk[0]['order_total'] * 100),
					'currency_code' => $currency_info[0],
					'order_id' => $order_res_chk[0]['orders_id'],
					'status' => 'Completed',
					'created_at' => $order_res_chk[0]['order_datetime'],
					'ip_address' => $order_res_chk[0]['customer_ip'],
					'user_agent' => $order_res_chk[0]['customer_browser'],
					'discount_amount_cents' => ($discount_amount_cents * 100),
					'items' => $yotpo_items,
				);
				if($order_res_chk[0]['coupon_code'] != '')
				{
					$params['coupon_code'] = $order_res_chk[0]['coupon_code'];
				}
                if($order_res_chk[0]['vLang_flag'] != '')
				{
					$params['coupon_code'] = $order_res_chk[0]['vLang_flag'];
				}
				
				$data = $yotpoObj->make_post_request('orders',$params);	
				//print_r($data);exit;
			}
			break;
			
		case 'refundOrder':
			$params = array(
				'order_id' => $orders_id,
				'total_amount_cents' => ($refundAmount * 100),
			);
		
			$data = $yotpoObj->make_post_request('refunds',$params);	
			//print_r($data);exit;
			break;
		
		case 'productReview':
			$data = $yotpoObj->make_get_request('customers?customer_email='.$custEmail);
			if(!isset($data->errors)){
				$params = array(
					'appkey' => 'MQY5nd09CBJk1IVKoMXrZmiUjvJj7s9krlkG1eL8',
					'domain' => 'https://www.maxaroma.com/',
					'sku' => $db_res_chk[0]["sku"],
					'product_title' => $db_res_chk[0]["product_name"],
					'display_name' => $db_res_chk[0]["first_name"],
					'email' => $custEmail,
					'review_content' => $db_res_chk[0]["user_review"],
					'review_title' => $db_res_chk[0]["first_name"],
					'review_score' =>$db_res_chk[0]["star_rate"],
					'reviewer_type' => 'verified_buyer'
				);
				$data = $yotpoObj->yotpo_make_post_request('reviews/dynamic_create',$params);	
			}
			break;
		case 'customAction':
			$data = $yotpoObj->make_get_request('customers?customer_email='.$custEmail);
			if(!isset($data->errors)){
				$params = array(
					'type' => 'CustomAction',
					'customer_email' => $custEmail,
					'action_name' => $customAction,
				);
				$data = $yotpoObj->make_post_request('actions',$params);	
			}
			break;	
		case 'checkCustomer':
			$data = $yotpoObj->make_get_request('customers?customer_email='.$ref_email);
			if(!isset($data->errors)){
				$_SESSION['ref_email'] = $data->email;
				$_SESSION['ref_id'] = $data->third_party_id;
			}
			else{
				$_SESSION['ref_email'] = '';
				$_SESSION['ref_id'] = '';
			}
			break;
			
		case 'subscribesms':
			$data = $yotpoObj->make_get_request('customers?customer_email='.$email);
			if(!isset($data->errors)){
				$params = array(
					'type' => "CustomAction",
					'customer_email' => $email,
					'action_name' => "SMS_signup"
				);
				$data = $yotpoObj->make_post_request('actions',$params);
			}
			break;
		
		case 'addReferral':
			$data = $yotpoObj->make_get_request('customers?customer_email='.$toemail);
			$addnewcustomer = 0;
			if(isset($data->errors)){
					$params = array(
					//'id' => $iCustomerId,
					'email' => $toemail,
					'first_name' => $yname
				);
				$addnewcustomer = 1;
				$data = $yotpoObj->make_post_request('customers',$params);
			}
			$params = array(
				'email' => $toemail,
				'emails' => $email_str
			);
			$referraldata = $yotpoObj->make_post_request('referral/share',$params);
			break;	
		
		case 'newslettercheckCustomer':
			$data = $yotpoObj->make_get_request('customers?customer_email='.$email);
			if(!isset($data->errors)){
				$yotpoCust = 1;
			}
			else{
				$yotpoCust = 0;
			}
			break;
		
		case 'addSpecialDates':
			$data = $yotpoObj->make_get_request('customers?customer_email='.$_SESSION['sess_useremail']);
			if(!isset($data->errors)){
				$params = array(
					'type' => "CustomAction",
					'customer_email' => $_SESSION['sess_useremail'],
					'action_name' => "special_dates"
				);
				$data = $yotpoObj->make_post_request('actions',$params);
			}
			break;
		
		default:
	}		
}
?>
