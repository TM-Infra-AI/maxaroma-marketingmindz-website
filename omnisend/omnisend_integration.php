<?php
if(OMNISEND_PROGRAM==1 || 1){
	include_once(SITE_DIR."omnisend/omnisend.cls.php");
	$omnisendObj = new Omnisend;
	switch ($omnisend_event) { 
		case '61e55276af90600022058216': 
			//CUSTOMER_REGISTER - customer_a.php
			$ApiPath = 'events/'.$omnisend_event;
			$sql = "SELECT * FROM `".TABLE_PREFIX."customer` WHERE customer_id = '".trim($row)."'";
			$Data = $obj->select($sql);
			if(!empty($Data) && $Data[0]['customer_id'] > 0){
				$RequestData = [];
				$RequestData = ['email' => $Data[0]['email'],
					'fields' => [
						'first_name' => $Data[0]['first_name'],
						'last_name' => $Data[0]['last_name'],
						'password' => $$Data[0]['password'],
						'SITE_NAME' => SITE_TITLE,
						'COUPON_CODE_VALUE' => COUPON_CODE_VALUE,
						'CONTACT_MAIL' => CONTACT_MAIL,
						'Site_URL' => Site_URL,
						'freeshippinginfo' => ''
					]
				];
				$data = $omnisendObj->make_post_request($ApiPath,$RequestData);
			}
			break;
		case '61e048930e8680001cd923aa': 
			//FORGOT_PASSWORD - forgot_pwd.php
			$ApiPath = 'events/'.$omnisend_event;
			$RequestData = [];
			$RequestData = ['email' => $email,
				'fields' => [
					'password' => $new_password,
					'TOLL_FREE_NO' => TOLL_FREE_NO,
					'CONTACT_MAIL' => CONTACT_MAIL,
					'Site_URL' => Site_URL,
					'SITE_NAME' => SITE_TITLE
				]
			];
			$data = $omnisendObj->make_post_request($ApiPath,$RequestData);
			break;
		case '62025e6f8a8d4100249b44a1': 
			//RESET_PASSWORD - customer_a.php
			$ApiPath = 'events/'.$omnisend_event;
			$RequestData = [];
			$RequestData = ['email' => $email,
				'fields' => [
					'forgot_link' => $ForgotLink,
					'password' => $new_password,
					'TOLL_FREE_NO' => CONTACT_PHONE_NO,
					'CONTACT_MAIL' => CONTACT_MAIL,
					'Site_URL' => Site_URL,
					'SITE_NAME' => SITE_TITLE
				]
			];
			$data = $omnisendObj->make_post_request($ApiPath,$RequestData);
			break;
		case '620263beb86552001e978355': 
			//WHOLESALER APPROVE - customer_a.php
			$ApiPath = 'events/'.$omnisend_event;
			$RequestData = [];
			$RequestData = ['email' => $vtoemail,
				'fields' => [
					'CONTACT_MAIL' => CONTACT_MAIL,
					'SITE_NAME' => SITE_TITLE
				]
			];
			$data = $omnisendObj->make_post_request($ApiPath,$RequestData);
			break;
		case '6202679ebf58ef001efc1c14': 
			//DROPSHIPPER_EMAIL_APPROVE - customer_a.php
			$ApiPath = 'events/'.$omnisend_event;
			$RequestData = [];
			$RequestData = ['email' => $customer_res[0]["email"],
				'fields' => [
					'first_name' => $customer_res[0]["first_name"],
					'last_name' => $customer_res[0]["last_name"],
					'customer_id' => $customer_res[0]["customer_id"],
					'CONTACT_MAIL' => CONTACT_MAIL,
					'SITE_NAME' => SITE_TITLE
				]
			];
			$data = $omnisendObj->make_post_request($ApiPath,$RequestData);
			break;
		case '62026eff8a8d4100249b44df': 
			//DROPSHIPPER_EMAIL_DISAPPROVE - customer_a.php
			$ApiPath = 'events/'.$omnisend_event;
			$RequestData = [];
			$RequestData = ['email' => $customer_res[0]["email"],
				'fields' => [
					'first_name' => $customer_res[0]["first_name"],
					'last_name' => $customer_res[0]["last_name"],
					'customer_id' => $customer_res[0]["customer_id"],
					'CONTACT_MAIL' => CONTACT_MAIL,
					'SITE_NAME' => SITE_TITLE
				]
			];
			$data = $omnisendObj->make_post_request($ApiPath,$RequestData);
			break;
        case '6217822212f2a2001715764d':
            //ORDER CANCEL STATUS - order_details_a.php, order_list_a.php
            $ApiPath = 'events/'.$omnisend_event;
            $sql 		= "SELECT * FROM ".TABLE_PREFIX."orders WHERE orders_id ='".$orders_id."'";
            $order_res	= $obj->select($sql);
            $order_res 	= $order_res[0];
            $BillShipAddress = $omnisendObj->GetBillShipAddress($order_res);
            $OrderDetails = $omnisendObj->GetOrderDetails($_POST["orders_id"],$Status);
			$RequestData = [];
			$RequestData = ['email' => $order_res['bill_email'],
				'fields' => [
					'customer_name' => $order_res['bill_first_name']." ".$order_res['bill_last_name'],
					'order_no' => $order_res['orders_no'],
					'ordereddate' => str_replace('+00:00', '.000Z', gmdate('c', strtotime($order_res['order_datetime']))),
                    'ordertotal' => (float)$order_res['order_total'],
                    'billing_address' => $BillShipAddress['billing_address'],
                    'shipping_address' => $BillShipAddress['shipping_address'],
                    'ordered_items' => $OrderDetails['ordered_items'],
                    'order_status' => $order_res['status'],
                    'TOLL_FREE_NO' => CONTACT_PHONE_NO,
					'CONTACT_MAIL' => CONTACT_MAIL
				]
			];
			$data = $omnisendObj->make_post_request($ApiPath,$RequestData);
            break;
        case '62179350e4703a001dfd4016':
            //ORDER STATUS - order_details_a.php
            $ApiPath = 'events/'.$omnisend_event;
            $sql 		= "SELECT * FROM ".TABLE_PREFIX."orders WHERE orders_id ='".$orders_id."'";
            $order_res	= $obj->select($sql);
            $order_res 	= $order_res[0];
            $BillShipAddress = $omnisendObj->GetBillShipAddress($order_res);
            $OrderDetails = $omnisendObj->GetOrderDetails($orders_id,$Status);
            $shippinginfoval = '';
            if($order_res['shipinfo']!='' && $order_res['is_only_gc']=='0')
            {
                $shippinginfoval = '
                    <p style="margin:0;padding:10px;line-height:16px;">
                        <span><b>Your Order Information:</b><br>Date Ordered: '.date("d F, Y",strtotime($order_res['order_datetime'])).'<br></span> Order Total: $'.$order_res['order_total'].'
                        <br /><br /><b>Your Shipment Information:</b><br /> Shipping Method: '.$order_res['shipinfo'].'
                    </p>';
            }
			$RequestData = [];
			$RequestData = ['email' => 'qqualdev@gmail.com',//$order_res['bill_email'],
				'fields' => [
                    'TrackingNumber' => $order_res['tracking_no'],
					'ship_first_name' => $order_res['bill_first_name']." ".$order_res['bill_last_name'],
					'order_no' => $order_res['orders_no'],
					'shippingmethod' => $shippinginfoval,
                    'billing_address' => $BillShipAddress['billing_address'],
                    'shipping_address' => $BillShipAddress['shipping_address'],
                    'ordered_items' => $OrderDetails['ordered_items'],
                    'SITE_NAME' => SITE_TITLE,
					'CONTACT_MAIL' => CONTACT_MAIL
				]
			];
			$data = $omnisendObj->make_post_request($ApiPath,$RequestData);
            break;
        case '62179a3912f2a20017157709':
            //OUTOFSTOCK_ORDER_CANCEL - order_details_a.php
            $ApiPath = 'events/'.$omnisend_event;
            $sql 		= "SELECT * FROM ".TABLE_PREFIX."orders WHERE orders_id ='".$orders_id."'";
            $order_res	= $obj->select($sql);
            $order_res 	= $order_res[0];
            $OrderDetails = $omnisendObj->GetOrderDetails($orders_id,$Status);
			$RequestData = [];
			$RequestData = ['email' => $order_res['bill_email'],
				'fields' => [
					'customer_name' => $order_res['bill_first_name']." ".$order_res['bill_last_name'],
					'order_no' => $order_res['orders_no'],
					'ordereddate' => str_replace('+00:00', '.000Z', gmdate('c', strtotime($order_res['order_datetime']))),
                    'ordertotal' => (float)$order_res['order_total'],
                    'ordered_items' => $OrderDetails['ordered_items'],
                    'TOLL_FREE_NO' => CONTACT_PHONE_NO,
					'CONTACT_MAIL' => CONTACT_MAIL
				]
			];
			$data = $omnisendObj->make_post_request($ApiPath,$RequestData);
            break;
        case '621dc1f86f8f55001ea10b5d':
            //PAYMENT_RECEIPT / PHONEORDER INVOICE - phoneorder_detail_pdf.php
            $ApiPath = 'events/'.$omnisend_event;
            $RequestData = [];
			$RequestData = ['email' => $order_res['bill_email'],
				'fields' => [
					'customer_name' => $OrderRs[0]['bill_first_name']." ".$OrderRs[0]['bill_last_name'],
					'orders_no' => $OrderRs[0]['orders_no'],
					'order_datetime' => str_replace('+00:00', '.000Z', gmdate('c', strtotime($OrderRs[0]['order_datetime']))),
                    'order_total' => (float)$OrderRs[0]['order_total'],
                    'payment_url' => $payment_url,
                    'SITE_NAME' => SITE_TITLE,
					'CONTACT_MAIL' => CONTACT_MAIL
				]
			];
			$data = $omnisendObj->make_post_request($ApiPath,$RequestData);
            break;
        case '6217a66212f2a20017157764':
            //RETURN_APPROVE_ORDER - return_order_list_a.php
            $ApiPath = 'events/'.$omnisend_event;
            $RequestData = [];
			$RequestData = ['email' => $bill_email,
				'fields' => [
					'customer_name' => $customer_name,
					'orders_no' => $orders_no,
					'comments' => $comments,
                    'SITE_NAME' => SITE_TITLE,
					'CONTACT_MAIL' => CONTACT_MAIL
				]
			];
			$data = $omnisendObj->make_post_request($ApiPath,$RequestData);
            break;
        case '6217a86112f2a2001715776d':
            //RETURN_REJECT_ORDER - return_order_list_a.php
            $ApiPath = 'events/'.$omnisend_event;
            $RequestData = [];
			$RequestData = ['email' => $bill_email,
				'fields' => [
					'customer_name' => $customer_name,
					'orders_no' => $orders_no,
					'comments' => $comments,
                    'SITE_NAME' => SITE_TITLE,
					'CONTACT_MAIL' => CONTACT_MAIL
				]
			];
			$data = $omnisendObj->make_post_request($ApiPath,$RequestData);
            break;
        case '6218bd6a96d355001e081182':
            //RETURN_ORDER_ITEM_RECEIVED - return_order_list_a.php
            $ApiPath = 'events/'.$omnisend_event;
            $RequestData = [];
			$RequestData = ['email' => $bill_email,
				'fields' => [
					'orders_no' => $orders_no,
					'comments' => $comments,
                    'refundLink' => $RefunLink,
                    'SITE_NAME' => SITE_TITLE,
					'CONTACT_MAIL' => CONTACT_MAIL
				]
			];
			$data = $omnisendObj->make_post_request($ApiPath,$RequestData);
            break;
        case '621772d9e4703a001dfd3ec6':
            //ORDER_REFUND_EMAIL - order_detail_a.php
            $ApiPath = 'events/'.$omnisend_event;
            $RequestData = [];
			$RequestData = ['email' => $order_res['bill_email'],
				'fields' => [
                    'customer_name' => $order_res['bill_first_name'],
					'order_number' => $order_res['orders_no'],
                    'refun_amount' => (float)number_format($refund_amount,2),
					'refund_shipping_cost' => (float)number_format($refund_shipping_cost,2),
                    'refund_restocking_fee' => (float)number_format($refund_restocking_fee,2),
                    'total_refund_amount' => (float)number_format($total_refund_amount,2),
                    'SITE_NAME' => SITE_TITLE,
					'CONTACT_MAIL' => CONTACT_MAIL
				]
			];
			$data = $omnisendObj->make_post_request($ApiPath,$RequestData);
            break;  
        case '6218c011669202001e8ec963':
            //ORDER_CLOSED - tracking-orders.php
            $ApiPath = 'events/'.$omnisend_event;
            $OrderDetails = $omnisendObj->GetOrderDetails($OrderRs["orders_id"],$OrderRs["status"]);
            $RequestData = [];
			$RequestData = ['email' => 'qqualdev@gmail.com',//$order_res['bill_email'],
				'fields' => [
                    'firstname' => $OrderRs["bill_first_name"],
                    'lastname' => $OrderRs["bill_last_name"],
                    'orders_id' => $OrderRs["orders_id"],
                    'status' => $OrderRs["status"],
                    'tracking_no' => $tmp_trackno,
                    'ship_status' => $OrderRs["ship_status"],
                    'billing_address' => $billing_address,
                    'shipping_address' => $shipping_address,
                    'orderd_items' => $OrderDetails['ordered_items'],
                    'TOLL_FREE_NO' => TOLL_FREE_NO,
                    'SITE_NAME' => SITE_TITLE,
					'CONTACT_MAIL' => CONTACT_MAIL
				]
			];
			$data = $omnisendObj->make_post_request($ApiPath,$RequestData);
            break;  
		default:
	}
}	