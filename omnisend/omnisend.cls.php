<?php 
class Omnisend { 
  
	protected $api_key= '61a57424f7860b001f9ed49f-7g0VYzQJLyDNsljKTSUIRTtDu5e44XZToAeB4WMGNmN3c4cv5q';
    
    public $host_https = 'https://api.omnisend.com/v3/';
	
	public function make_post_request($path, $params) 
    {
        $url = $this->host_https.$path ;
      
		$data =$params;                                                                    
		$data_string = json_encode($data);    

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); 
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);

		$headers = array();
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'X-API-KEY: '.$this->api_key;
		$headers[] = 'cache-control: no-cache';
		$headers[] = 'Accept: application/json';
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			echo 'Error:' . curl_error($ch);
		}
		curl_close($ch);
		$result = json_decode($result); 
		return $result;
    }
    public function GetBillShipAddress($order_res)
    {
        ## Billing Address Start Here
        $billing_address  = $order_res["bill_first_name"]." ".$order_res["bill_last_name"]."<br>";
        $billing_address .= $order_res["bill_address1"].",<br>";
        if(!empty($order_res["bill_address2"]))
            $billing_address .= $order_res["bill_address2"]."<br>";
        $billing_address .= $order_res["bill_city"]."<br>";
        $billing_address .= $order_res["bill_state"]." - ".$order_res["bill_zip"]."<br>";
        $billing_address .= $order_res["bill_country"]."<br>";
        $billing_address .= "Phone :".$order_res["bill_phone"];

        ## Shipping Address Start Here
        $shipping_address .= $order_res["ship_first_name"]." ".$order_res["ship_last_name"]."<br>";
        $shipping_address .= $order_res["ship_address1"].",<br>";
        if(!empty($order_res["ship_address2"]))
            $shipping_address .= $order_res["ship_address2"]."<br>";
        $shipping_address .= $order_res["ship_city"]."<br>";
        $shipping_address .= $order_res["ship_state"]." - ".$order_res["ship_zip"]."<br>";
        $shipping_address .= $order_res["ship_country"]."<br>";
        $shipping_address .= "Phone :".$order_res["ship_phone"];
        
        return array('billing_address' => $billing_address, 'shipping_address' => $shipping_address);
    }
    public function Make_Price($text, $currency_symbol='$', $currency_rate = 0, $round=false) 
    {
		$text1	   = $text*$currency_rate;
        if($round==true) {
            return $currency_symbol.number_format(round($text1), 0, '', ',');
        } else {
            ##return SITE_CURRENCY_SYMBOL.number_format($text1, 2, '.', ',');
            return $currency_symbol.number_format($text1, 2, '.', ',');
        }
    }
    public function GetOrderDetails($orders_id,$order_status)
    {
        global $obj;
        if($orders_id != '')
        {
            $sql 		= "SELECT * FROM ".TABLE_PREFIX."orders WHERE orders_id ='".$orders_id."'";
            $order_res	= $obj->select($sql);
            $order_res 	= $order_res[0];
            
            $currencyguide = explode("#",$order_res['currency_info']);
            $currencysymbol = $currencyguide[1];
            $currencyrate = $currencyguide[2];
            
            $sql = "SELECT * FROM ".TABLE_PREFIX."order_detail WHERE orders_id='".$orders_id."'";
            $allOrderItems 	= $obj->select($sql);
            $ordered_items = '';
            $ordered_items .= '<table width="100%" border="0" cellpadding="0" cellspacing="0" style="border:1px solid #e8e8e8; border-bottom:none;">
                           <tbody>';
            $cancelcontent = '';
            if($order_status=="Canceled")
            {
                $ordered_items .= ' <td colspan="5" style="color:#000000;"><b>Item(s) Canceled</b></td>';
            }
            $ordered_items .='<tr align="center" valign="top">
                        <td style="background-color:#e5e5e5; padding:5px;"><b>Gift Wrap</b></td>
                        <td style="background-color:#e5e5e5;padding:5px"><b>Images</b></td>
                        <td style="background-color:#e5e5e5;padding:5px" align="left"><b>Your Order Summary</b></td>
                        <td style="background-color:#e5e5e5;padding:5px"><b>Quantity</b></td>
                        <td style="background-color:#e5e5e5;padding:5px" align="right"><b>Price</b></td>
                        </tr>';

            $prodArr = array();
            for ( $p = 0; $p < count($allOrderItems); $p++ )
            {
                    $orderItem	  = $allOrderItems[$p];
                    $products_id  = $orderItem["products_id"];
                    $item_sku 	  = trim($orderItem["sku"]);
                    $product_name = trim($orderItem["product_name"]);
                    if($order_status=="Canceled")
                    {
                        $product_res = $obj->select("SELECT * FROM `".TABLE_PREFIX."products` WHERE  products_id='".$products_id."'");
                        if(count($products_id) > 0)
                        {

                            $ProductResArr = RelatedItems($product_res[0]["imanufactureid"],$product_res[0]["gender"],$product_res[0]["products_id"]);
                            if(count($ProductResArr) > 1)
                            {
                                $prodArr = $ProductResArr;
                            }
                        }
                    }

                    if($orderItem["is_free_gift_products"]=="Yes")
                    {
                        $sql = "SELECT product_image FROM `".TABLE_PREFIX."free_gift_product`
                        WHERE  LOWER(TRIM(sku))='".strtolower(trim($orderItem['sku']))."' LIMIT 0,1" ;
                        $prod_res = $obj->select($sql);

                        if(file_exists(PRD_THUMB_IMG_PATH.$prod_res[0]['product_image']) and!empty($prod_res[0]['product_image']))
                        {
                            $prod_image = PRD_THUMB_IMG_URL.$prod_res[0]['product_image'];
                        }
                        else
                        {
                        $prod_image = NO_IMAGE_THUMB;
                        }
                        $thumb_image	='<img src="'.$prod_image.'" width="125" border="0" class="img-resp-75" />';
                    }
                    else
                    {
                        $thumb_image = getItemThumb($item_sku);
                    }

                    ## Here get gc info
                    if($item_sku==GIFT_CERTIFICATE_SKU)
                    {
                        $gcSql = "SELECT * FROM `".TABLE_PREFIX."gift_certificate` WHERE
                                           orders_detail_id ='".$orderItem["orders_detail_id"]."'
                                           AND customer_id ='".$order_res["customer_id"]."'" ;
                        $GCRs = $obj->select($gcSql);


                        $orderItem['RecipientName']  = $GCRs[0]['recipient_name'];
                        $orderItem['RecipientEmail'] = $GCRs[0]['recipient_email'];
                        $orderItem['SenderName']  	 = $GCRs[0]['your_name'];
                        $orderItem['SenderEmail'] 	 = $GCRs[0]['your_email'];

                    }
                    else if($item_sku==GIFT_CERTIFICATE_SKU1)
                    {
                        $gcSql = "SELECT * FROM `".TABLE_PREFIX."gift_certificate` WHERE
                                           orders_detail_id ='".$orderItem["orders_detail_id"]."'
                                           AND customer_id ='".$order_res["customer_id"]."'" ;
                        $GCRs = $obj->select($gcSql);


                        $orderItem['RecipientName']  = $GCRs[0]['recipient_name'];
                        $orderItem['RecipientEmail'] = $GCRs[0]['recipient_email'];
                        $orderItem['SenderName']  	 = $GCRs[0]['your_name'];
                        $orderItem['SenderEmail'] 	 = $GCRs[0]['your_email'];
                    }
                    else if($item_sku==GIFT_CERTIFICATE_SKU2)
                    {
                        $gcSql = "SELECT * FROM `".TABLE_PREFIX."gift_certificate` WHERE
                                           orders_detail_id ='".$orderItem["orders_detail_id"]."'
                                           AND customer_id ='".$order_res["customer_id"]."'" ;
                        $GCRs = $obj->select($gcSql);


                        $orderItem['RecipientName']  = $GCRs[0]['recipient_name'];
                        $orderItem['RecipientEmail'] = $GCRs[0]['recipient_email'];
                        $orderItem['SenderName']  	 = $GCRs[0]['your_name'];
                        $orderItem['SenderEmail'] 	 = $GCRs[0]['your_email'];

                    }

                    $checked = '';
                    if($orderItem['is_gift_wrap']=='Yes')
                    { $checked = 'checked="checked" '; }

                    $ordered_items .= '<tr align="center" valign="top">
                        <td style="padding:10px 5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8"><input type="checkbox"  disabled="disabled" '.$checked.' /></td>
                        <td style="padding:10px 5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8">'.$thumb_image.'</td>
                        <td style="padding:10px 5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="left">
                            <p style="margin:0px"><b>'.$orderItem['product_name'].'</b></p>
                            <p>SKU: '.$orderItem['sku'].'</p>';
                            if($item_sku==GIFT_CERTIFICATE_SKU)
                            {
                                $ordered_items .= '
                                    <p>Sender Name: '.$orderItem['SenderName'].'</p>
                                    <p>Sender Email: '.$orderItem['SenderEmail'].'</p>
                                    <p>Recipient Name: '.$orderItem['RecipientName'].'</p>
                                    <p>Recipient Email: '.$orderItem['RecipientEmail'].'</p>';
                            }
                            elseif($item_sku==GIFT_CERTIFICATE_SKU1)
                            {
                                $ordered_items .= '
                                    <p>Sender Name: '.$orderItem['SenderName'].'</p>
                                    <p>Sender Email: '.$orderItem['SenderEmail'].'</p>
                                    <p>Recipient Name: '.$orderItem['RecipientName'].'</p>
                                    <p>Recipient Email: '.$orderItem['RecipientEmail'].'</p>';
                            }
                            elseif($item_sku==GIFT_CERTIFICATE_SKU2)
                            {
                                $ordered_items .= '
                                    <p>Sender Name: '.$orderItem['SenderName'].'</p>
                                    <p>Sender Email: '.$orderItem['SenderEmail'].'</p>
                                    <p>Recipient Name: '.$orderItem['RecipientName'].'</p>
                                    <p>Recipient Email: '.$orderItem['RecipientEmail'].'</p>';
                            }
                            if($orderItem['handling_time_str']!='')
                            {
                                $ordered_items .= '<p>'.$orderItem['handling_time_str'].'</p>';
                            }
                    $ordered_items .= '</td>';
                    $ordered_items .= '<td style="padding:10px 5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8"><b>'.$orderItem['quantity'].'</b></td>';
                    $ordered_items .= '<td style="padding:10px 5px;border-bottom:1px solid #e8e8e8" align="right"><b>'.$this->Make_Price($orderItem['price'],$currencysymbol, $currencyrate).'</b></td></tr>';
                    $TotalProducts = (int)$TotalProducts + (int)$orderItem['quantity'];
            }

            if($order_res['is_gift_wrap']=='Yes')
            {
                $ordered_items .= '<tr align="center" valign="top">
                                      <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right"><b> Gift Wrap:</b></td>
                                      <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="left">Yes</td>
                                    </tr>';
            }
            $ordered_items .= '<tr align="center" valign="top">
                                  <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right"><b> Total item purchased:</b></td>
                                  <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right">'.$TotalProducts.'</td>
                                </tr>';

            $ordered_items .= ' <tr align="center" valign="top">
                                  <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right">Subtotal:</td>
                                  <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right">'.$this->Make_Price($order_res['sub_total'],$currencysymbol, $currencyrate).'</td>
                                </tr>';
            if($order_res["shipping_amt"]>0)
            {
                $ordered_items .= ' <tr align="center" valign="top">
                                      <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right">Shipping Charge:</td>
                                      <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right">'.$this->Make_Price($order_res['shipping_amt'],$currencysymbol, $currencyrate).'</td>
                                    </tr>';
            }
            if($order_res["route_shipping_insurance_charge"] > 0){
                $ordered_items .= ' <tr align="center" valign="top">
                                      <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right">Shipping Insurance Charge:</td>
                                      <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right">'.$this->Make_Price($order_res['route_shipping_insurance_charge'],$currencysymbol, $currencyrate).'</td>
                                    </tr>';
            }
            if($order_res["tax"]>0)
            {
                $ordered_items .= ' <tr align="center" valign="top">
                                      <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right">Sales Tax:</td>
                                      <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right">'.$this->Make_Price($order_res['tax'],$currencysymbol, $currencyrate).'</td>
                                    </tr>';
            }
            if($order_res["gift_charge"]>0)
            {
                $ordered_items .= ' <tr align="center" valign="top">
                                              <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right">Gift Wrap Charge:</td>
                                              <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right">'.$this->Make_Price($order_res['gift_charge'],$currencysymbol, $currencyrate).'</td>
                                            </tr>';
            }
            if($order_res["auto_discount"]>0)
            {
                $ordered_items .= ' <tr align="center" valign="top">
                                              <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right">Auto Discount:</td>
                                              <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right">'.$this->Make_Price($order_res['auto_discount'],$currencysymbol, $currencyrate).'</td>
                                            </tr>';
            }
            if($order_res["quantity_discount"]>0)
            {
                $ordered_items .= ' <tr align="center" valign="top">
                                              <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right">Quantity Discount:</td>
                                              <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right">'.$this->Make_Price($order_res['quantity_discount'],$currencysymbol, $currencyrate).'</td>
                                            </tr>';
            }
            if($order_res["shipping_signature"]>0)
            {
                $ordered_items .= ' <tr align="center" valign="top">
                                              <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right">Shipping Signature:</td>
                                              <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right">'.$this->Make_Price($order_res['shipping_signature'],$currencysymbol, $currencyrate).'</td>
                                            </tr>';
            }
            if($order_res["coupon_amount"]>0)
            {
                $ordered_items .= ' <tr align="center" valign="top">
                                              <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right">Coupon Discount:</td>
                                              <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right">'.$this->Make_Price($order_res['coupon_amount'],$currencysymbol, $currencyrate).'</td>
                                            </tr>';
            }
            if($order_res["bogo_discount"]>0)
            {
                $ordered_items .= ' <tr align="center" valign="top">
                                              <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right">Bogo Discount:</td>
                                              <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right">'.$this->Make_Price($order_res['bogo_discount'],$currencysymbol, $currencyrate).'</td>
                                            </tr>';
            }
            if($order_res["gc_amount"]>0)
            {
                $ordered_items .= ' <tr align="center" valign="top">
                                              <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right">Gift Certificate Discount:</td>
                                              <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right">'.$this->Make_Price($order_res['gc_amount'],$currencysymbol, $currencyrate).'</td>
                                            </tr>';
            }
            if($order_res["apply_credit"]>0)
            {
                $ordered_items .= ' <tr align="center" valign="top">
                                              <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right">Credit Discount:</td>
                                              <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right">'.$this->Make_Price($order_res['apply_credit'],$currencysymbol, $currencyrate).'</td>
                                            </tr>';
            }
            if($order_res["reward_discount"]>0)
            {
                $ordered_items .= ' <tr align="center" valign="top">
                                              <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right">Reward Discount:</td>
                                              <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right">'.$this->Make_Price($order_res['reward_discount'],$currencysymbol, $currencyrate).'</td>
                                            </tr>';
            }

            if($order_res["refer_amount"]>0)
            {
                $ordered_items .= ' <tr align="center" valign="top">
                                              <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right">'.$AUTO_REFER_DISCOUNT.':</td>
                                              <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right">'.$this->Make_Price($order_res['refer_amount'],$currencysymbol, $currencyrate).'</td>
                                            </tr>';
            }
            $ordered_items .= ' <tr align="center" valign="top">
                                              <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8;border-right:1px solid #e8e8e8" align="right"><b>Order
                                                Total:</b></td>
                                              <td style="padding:5px;border-bottom:1px solid #e8e8e8" align="right"><b>'.$this->Make_Price($order_res['order_total'],$currencysymbol, $currencyrate).'</b></td>
                                            </tr>';
            $ordered_items .= ' </tbody></table>';
            return array('ordered_items' => $ordered_items);
        }
    }
}
?>