<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;

use App\Models\Products;
use App\Models\PaymentMethod;
use DB;
use Session;

trait AfterpayTrait
{	
	public $PageData,$Payment_Url,$Token_JS_Url,$TRANSACTION_MODE,$ap_arr;
	
	public function constructfunc11()
    {
		$db_res = PaymentMethod::select('pm_group_name', 'pm_gateway_name','pm_details')
							->where('pm_group_name','=', 'PAYMENT_PAYWITHAFTERPAY')
							->where('pm_status', '=', 'Active')
							->get();
		
		if($db_res->count() > 0)
		{
			$arrPEVar		= unserialize($db_res[0]->pm_details);
			
			//echo "<pre>";print_r($arrPEVar);exit;
			#############################
			$this->ap_arr['PaywithAfterpay_Merchant_ID']   = $this->decrypt($arrPEVar['PaywithAfterpay_Merchant_ID']);
			$this->ap_arr['PaywithAfterpay_Merchant_Secret_Key']   = $this->decrypt($arrPEVar['PaywithAfterpay_Merchant_Secret_Key']);
			$this->ap_arr['PaywithAfterpay_Header_Authorization']   = $this->decrypt($arrPEVar['PaywithAfterpay_Header_Authorization']);
			$this->ap_arr['PaywithAfterpay_Header_User_Agent']   = $this->decrypt($arrPEVar['PaywithAfterpay_Header_User_Agent']);
			
			#############################
			//echo "<pre>";print_r($arrPEVar);exit;

			if( strtoupper(trim($arrPEVar['PaywithAfterpay_Transaction_Mode'])) == 'SANDBOX'){
				$this->TRANSACTION_MODE = 'sandbox';
				$this->Payment_Url = "https://api.us-sandbox.afterpay.com/v2/";
				//$Payment_Url = "https://api.us-sandbox.afterpay.com/v1/";
				$this->Token_JS_Url = "https://portal.sandbox.afterpay.com/afterpay.js";
			}else{
				$this->TRANSACTION_MODE = '';
				$this->Payment_Url = "https://api.us.afterpay.com/v2/";
				$this->Token_JS_Url = "https://portal.afterpay.com/afterpay.js";
			}
		}else{
			
		}
	}
	
	public function GetAfterPayResult($data_payload = array(),$ApiType="",$IsPost = "Yes"){
		$this->constructfunc11();
		if(empty($data_payload)){
			$data_payload = json_encode($data_payload);
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->Payment_Url.$ApiType);
		curl_setopt($ch, CURLOPT_SSLVERSION, 'CURL_SSLVERSION_TLSv1_2');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if($IsPost == "Yes"){
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data_payload);
		}
		if(!empty($data_payload)){

		}
		$headers = array();
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Authorization: Basic '.$this->ap_arr["PaywithAfterpay_Header_Authorization"];	//taken from doc
		$headers[] = 'User-Agent: '.$this->ap_arr["PaywithAfterpay_Header_User_Agent"];
		$headers[] = 'Accept: application/json';
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($ch);
		curl_close($ch);
		$resultArr = json_decode($response,true);
		return $resultArr;
	}
	

}
