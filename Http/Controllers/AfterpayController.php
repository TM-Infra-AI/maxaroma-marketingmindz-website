<?php 
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;

use Hash;
use Session;
use App\Models\MetaInfo;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\PaymentMethod;

use App\Http\Controllers\Traits\CommonTrait;
use App\Http\Controllers\Traits\EncryptTrait;
use App\Http\Controllers\Traits\CartTrait;
	
use DB;
use Mail;

use Illuminate\Support\Facades\Log;



use Afterpay\SDK\HTTP\Request\Ping as AfterpayPingRequest;

use Afterpay\SDK\Exception\NetworkException as AfterpayNetworkException;
use Afterpay\SDK\Exception\ParsingException as AfterpayParsingException;


use Afterpay\SDK\Config as AfterpayConfig;
use Afterpay\SDK\MerchantAccount as AfterpayMerchantAccount;
use Afterpay\SDK\PersistentStorage as AfterpayPersistentStorage;
use Afterpay\SDK\HTTP\Request\GetConfiguration as AfterpayGetConfigurationRequest;


use Afterpay\SDK\Exception\InvalidModelException as AfterpayInvalidModelException;
use Afterpay\SDK\HTTP\Request\CreateCheckout as AfterpayCreateCheckoutRequest;
use Afterpay\SDK\Model\Consumer as AfterpayConsumer;
use Afterpay\SDK\Model\Money as AfterpayMoney;


use Afterpay\SDK\HTTP\Request\DeferredPaymentAuth as AfterpayDeferredPaymentAuthRequest;


use Afterpay\SDK\Helper\StringHelper as AfterpayStringHelper;
use Afterpay\SDK\Model\Payment as AfterpayPayment;
use Afterpay\SDK\HTTP\Request\DeferredPaymentCapture as AfterpayDeferredPaymentCaptureRequest;

class AfterpayController extends Controller
{
	use CommonTrait;
	use EncryptTrait;
	use CartTrait;
		
	public $PageData,$Payment_Url,$Token_JS_Url,$TRANSACTION_MODE;
	public $ap_arr;

	public $merchant = null;
	public $error = null;
	public $order = null;
	public $paymentEvent = null;

	public function __construct()
    {
		$db_res = PaymentMethod::select('pm_group_name', 'pm_gateway_name','pm_details')
							->where('pm_group_name','=', 'PAYMENT_PAYWITHAFTERPAY')
							->where('pm_status', '=', 'Active')
							->get();
		
		if($db_res->count() > 0)
		{
			// if($_SERVER['HTTP_X_FORWARDED_FOR'] == "157.32.19.248" || Session::get('sess_useremail') == 'gequaldev@gmail.com'){
				// $db_res[0]->pm_details = 'a:6:{s:27:"PaywithAfterpay_Merchant_ID";s:16:"6+/un9C1fWJXNwA=";s:35:"PaywithAfterpay_Merchant_Secret_Key";s:144:"DcpbEsIgDADA6zu8EopYb+IASQPpbarSHkH3e3eHHWoFXrewBFBLb4q+A56KUw5Sm1ZhDBzbyF7VuFq8Lzfky/VWJC3PhyvGD+WAkHrErwEhylcjmiLZ7ZqO2LY7Z339k53V4oAxmjmPT/gB";s:36:"PaywithAfterpay_Header_Authorization";s:244:"BcHJCoJAAABQJOjXszKtY4IkRc6kDokOpJ1qRiRbXVDTKKx7tFide0+byik49feqlcaIYgYYO1bho7b9ezezKue1wTqzHIWqo72AxHEF4ycBfrxwgi2bB2cODegKvIZ0kt89pMo14IUNxVgf9DJ0j7trrJm4Cxnagn6U6NSpoBQG0ChaqP5ws9rN1Tq4hzcigduLYKuEwXLCmemX2PWSzcuCzBJDAB9zCf2iiyQ4B1JETato/AE=";s:33:"PaywithAfterpay_Header_User_Agent";s:152:"AWsAlP+h0NLTyfR4p8fU8MXMhZC3inmwnIaf+MTM8f2776nYxr/Pxc7M9HilvMzui4+Ej7eKlKinoay4jo28uYjFqdW/0L7HwMj7h4iLj7qMjo+SuYN58MvNzPyRjrv+wPn5trvEz8TRyujRhb7O9g==";s:32:"PaywithAfterpay_Transaction_Mode";s:7:"Sandbox";s:29:"PaywithAfterpay_Currency_Code";s:3:"USD";}';
			// }
			
			$arrPEVar		= unserialize($db_res[0]->pm_details);
			
			//echo "<pre>";print_r($arrPEVar);//exit;
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
				$this->TRANSACTION_MODE = 'production';
				$this->Payment_Url = "https://api.us.afterpay.com/v2/";
				$this->Token_JS_Url = "https://portal.afterpay.com/afterpay.js";
			}
		}else{
			
		}
	}
	
	public function GetAfterPayResult($data_payload = array(),$ApiType="",$IsPost = "Yes"){
		if(empty($data_payload)){
			$data_payload = json_encode($data_payload);
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->Payment_Url.$ApiType);
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
		$headers[] = 'Authorization: Basic '.$this->ap_arr["PaywithAfterpay_Header_Authorization"];	//taken from doc
		$headers[] = 'User-Agent: '.$this->ap_arr["PaywithAfterpay_Header_User_Agent"];
		$headers[] = 'Accept: application/json';

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($ch);

		curl_close($ch);

		$resultArr = json_decode($response,true);
		//echo "<pre>sss";print_r($resultArr);exit;
		return $resultArr;
	}
	

	public function SetAfterpay(Request $request)
	{
		/*
		if($this->Is_WholeSaler_Allow() == false)
		{
			return redirect('/shoppingcart');
		}
		*/
		
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) <= 0)
		{
			Session::forget('ShoppingCart');
			return redirect('/shoppingcart');	
		}

		/*$pingRequest = new AfterpayPingRequest();
		$this->tryPing($pingRequest);*/

		$merchant = new AfterpayMerchantAccount();

		$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
		    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
				->setApiEnvironment($this->TRANSACTION_MODE)
		    	->setCountryCode('US');


	    $getConfigurationRequest = new AfterpayGetConfigurationRequest();

		$getConfigurationRequest->setMerchantAccount($merchant);

		$getConfigurationRequest->send();

		$body = $getConfigurationRequest->getResponse()->getParsedBody();

		// dd($body);

		$Payment_Amount  = NumberFormat($this->GetNetTotal());
		$Payment_Currency  = 'USD';
		// $payload['merchantReference'] = "OR".Session::get('ShoppingCart.OrderID');
		$setConsumer = [];
		if( (Session::has('sess_icustomerid')) && Session::get('sess_icustomerid') > 0) {
			$customer = Customer::where('customer_id', '=', Session::get('sess_icustomerid'))->get();
			if($customer && count($customer) > 0) {
				$setConsumer['givenNames'] = $customer[0]['first_name'];		//optional
				$setConsumer['surname'] = $customer[0]['last_name'];		//optional
				$setConsumer['email'] = $customer[0]['email'];	//required
			} else {
				Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
				return redirect('billing');
			}
		} else {
			Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
			return redirect('billing');
		}

		$setMerchant['redirectConfirmUrl'] = url('afterpay/success');
		$setMerchant['redirectCancelUrl'] = url('afterpay/cancel');
		
		$tempBillingAdd  = Session::get('ShoppingCart.BillingAddress');
		$tempShippingAdd = Session::get('ShoppingCart.ShippingAddress');

		$setBilling = [];
		$setBilling['name'] = $tempBillingAdd['first_name']." ".$tempBillingAdd['last_name'];
		$setBilling['line1'] = $tempBillingAdd['address1'];
		$setBilling['area1'] = $tempBillingAdd['city'];
		$setBilling['region'] = $tempBillingAdd['state'];
		$setBilling['postcode'] = $tempBillingAdd['zip'];
		$setBilling['countryCode'] = $tempBillingAdd['country'];
		$setBilling['phoneNumber'] = $tempBillingAdd['phone'];

		$setShipping = [];
		$setShipping['name'] = $tempShippingAdd['first_name']." ".$tempShippingAdd['last_name'];
		$setShipping['line1'] = $tempShippingAdd['address1'];
		$setShipping['area1'] = $tempShippingAdd['city'];
		$setShipping['region'] = $tempShippingAdd['state'];
		$setShipping['postcode'] = $tempShippingAdd['zip'];
		$setShipping['countryCode'] = $tempShippingAdd['country'];
		$setShipping['phoneNumber'] = $tempShippingAdd['phone'];


		//Items details

		$setItems = [];
		$ShopCart = Session::get('ShoppingCart.Cart');
		foreach($ShopCart as $key => $CartItem)
		{
			if(isset($CartItem['IS_Free_Gift']) && $CartItem['IS_Free_Gift'] == 'Yes')
			{	
				$ItemPrice = $CartItem['TotPrice'];
			}else{ 
				$ItemPrice = $CartItem['ItemPrice'];
			}
			$setItems[] = [
		        'name' => $CartItem['ProductName'],
		        'sku' => $CartItem['SKU'],
		        'quantity' => $CartItem['Qty'],
		        'pageUrl' => $CartItem['Prod_URL'],
		        'price' => [$ItemPrice, 'USD']
		    ];
		}

		/**
		 * Method B:
		 *
		 * Instantiating an empty Request class, then setting the values of each field using the individual
		 * setter methods. If Automatic Validation is disabled, you can load in all of the data and then iterate over
		 * the list of errors, rather than only catching the first.
		 */
		// dd($setConsumer, $setBilling, $setShipping, $setItems, $setMerchant);
		\Afterpay\SDK\Model::setAutomaticValidationEnabled(false);

		$createCheckoutRequest = new AfterpayCreateCheckoutRequest();

		$createCheckoutRequest
		    ->setAmount($Payment_Amount, 'USD')
		    ->setConsumer($setConsumer)
		    ->setBilling($setBilling)
		    ->setShipping($setShipping)
		    /*->setCourier([
		        'shippedAt' => '2019-01-01T00:00:00+10:00',
		        'name' => 'FedEx',
		        'tracking' => 'AA0000000000000',
		        'priority' => 'STANDARD'
		    ])*/
		    ->setItems($setItems)
		    /*->setDiscounts([
		        [
		            'displayName' => '20% off SALE',
		            'amount' => [ '24.00', 'USD' ]
		        ]
		    ])*/
		    ->setMerchant($setMerchant)
		    /*->setTaxAmount('0.00', 'USD')
		    ->setShippingAmount('0.00', 'USD')*/
		;
		if ($createCheckoutRequest->isValid()) {
			$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
			    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
					->setApiEnvironment($this->TRANSACTION_MODE)
			    	->setCountryCode('US');

			$createCheckoutRequest->setMerchantAccount($merchant);

		    $createCheckoutRequest->send();

		    // $response = $createCheckoutRequest->getRawLog();

		    $createCheckoutResponse = $createCheckoutRequest->getResponse();
    		$response = $createCheckoutResponse->getParsedBody();
				// dd($response);
			if ($createCheckoutResponse->isSuccessful()) {
				// dd($response->redirectCheckoutUrl);
				if(isset($response->token) && $response->token != ""){
					$redirect = $response->redirectCheckoutUrl;
					$token = $response->token;
					$expires = $response->expires;
					
					$updAray = array ('status' => 'Sent To AfterPay');

					$order_id = Session::get('ShoppingCart.OrderID');
					$uporderres = Order::Where("orders_id","=",$order_id)
										->update($updAray);

			    	return redirect($response->redirectCheckoutUrl);
				}else{
					$transaction_info = "This transaction has been Declined.";
					$Payment_response = json_encode($response);

					$updAray = array (
										'status' 	   			=> 'Declined',
										'transaction_info' 			=> $transaction_info,
										'payment_gateway_response' 	=> $Payment_response
									  );

					$order_id = Session::get('ShoppingCart.OrderID');
					$updOrder = Order::Where("orders_id","=",$order_id)
										->update($updAray);		
					
					Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
					return redirect('billing');
				}

			} else {
			    $this->error = $response;
			}
		} else {
		    $response = $createCheckoutRequest->getValidationErrorsAsHtml();
		}

	}

	public function Success(Request $request)
	{
		// dd($request->all());
		if($request->has('status') && $request->status == "SUCCESS") {
			if ($request->has('orderToken') && $request->orderToken != '') {

			    	$deferredPaymentAuthRequest = new AfterpayDeferredPaymentAuthRequest([
				        'token' => urlencode($request->orderToken)
				    ]);

					$merchant = new AfterpayMerchantAccount();
					$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
					    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
							->setApiEnvironment($this->TRANSACTION_MODE)
					    	->setCountryCode('US');

				    if (!is_null($merchant)) {
				        $deferredPaymentAuthRequest->setMerchantAccount($merchant);
				    }

				    $deferredPaymentAuthRequest->send();

				    $deferredPaymentAuthResponse = $deferredPaymentAuthRequest->getResponse();
				    $repsonse = $deferredPaymentAuthResponse->getParsedBody();

					/////////// Log Start ////////////
					$cur_date = date("Y-m-d");
					$myFile = config('global.SITE_URL').'PayWithAfterpay/afterpay_logs/afterpay-log'.$cur_date.'.txt';
					if(@fopen($myFile, 'a+'))
					{
						$fh = fopen($myFile, 'a+');

						$stringData .= chr(13) . chr(13) . 'Auth REQUEST == ' . serialize($request->orderToken) . chr(13) . chr(13) ;
						$stringData .= chr(13) . chr(13) . 'Auth RESPONSE == ' . serialize($repsonse) . chr(13) . chr(13);

						fwrite($fh, $stringData);
						fclose($fh);
					}
					/////////// Log End ////////////
					// dd($repsonse);
				    /*if ($deferredPaymentAuthResponse->isSuccessful()) {
				        $this->PageData['order'] = $repsonse;
				        $this->PageData['type'] = 'order';
				    } else {
				        $this->PageData['error'] = $repsonse;
				        $this->PageData['type'] = 'error';
				    }*/

				    if($deferredPaymentAuthRequest->getResponse()->isApproved() && $repsonse->paymentState == "AUTH_APPROVED" && $request->orderToken != "") {

						if( (Session::has('sess_icustomerid')) && Session::get('sess_icustomerid') > 0) {
							$payment_gateway_response = "Auth Response::".json_encode($repsonse);
							$updAray = array (
												'payment_gateway_response' 	=> $payment_gateway_response,
												'afterpay_transaction_id' 	=> $repsonse->id
											  );

							$order_id = Session::get('ShoppingCart.OrderID');
							$uporderres = Order::Where("orders_id","=",$order_id)
												->update($updAray);	
							$capturePayment = url('afterpay/dopayment/'.$repsonse->id);
							return redirect($capturePayment);
						} else {

							//order order not confirmed by customer
							//status >> CANCELLED
							$transaction_info = "This transaction has been Declined.";
							$updAray = array (
												'status' 	   				=> 'Declined',
												'transaction_info' 			=> $transaction_info,
												'payment_gateway_response' 	=> $transaction_info
											  );
											  
							$order_id = Session::get('ShoppingCart.OrderID');
							$uporderres = Order::Where("orders_id","=",$order_id)
												->update($updAray);	

							Session::flash('CartError','Error in Processing Request, Please try again.');								
							return redirect('shoppingcart');

						}
				    } else {
						$transaction_info = "This transaction has been Declined.";
						$Payment_response = json_encode($repsonse);
						
						$updAray = array (
											'status' 	   				=> 'Declined',
											'transaction_info' 			=> $transaction_info,
											'payment_gateway_response' 	=> $Payment_response
										  );

						$order_id = Session::get('ShoppingCart.OrderID');
						$updOrder = Order::Where("orders_id","=",$order_id)
											->update($updAray);	

						Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
						return redirect('billing');
				    }
			}
		} else {

			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> "This transaction has been Declined by User."
							  );
							  
			$order_id = Session::get('ShoppingCart.OrderID');
			$uporderres = Order::Where("orders_id","=",$order_id)
								->update($updAray);
				
			Session::flash('CartError','Error in Processing Request, Please try again.');								
			return redirect('shoppingcart');
		}

		// dd('Success', $request->all(), $this->order, $this->error);
	}

	public function Cancel(Request $request)
	{
		$transaction_info = "This transaction has been Declined.";
		$updAray = array (
							'status' 	   				=> 'Declined',
							'transaction_info' 			=> $transaction_info,
							'payment_gateway_response' 	=> "This transaction has been Declined by User."
						  );
						  
		$order_id = Session::get('ShoppingCart.OrderID');
		$uporderres = Order::Where("orders_id","=",$order_id)
							->update($updAray);
		return redirect('shoppingcart');
		//dd('Cancel', $request->all());
	}

	public function DoPayment(Request $request)
	{
		$Payment_Amount  = NumberFormat($this->GetNetTotal());
		$Payment_Currency  = 'USD';
		$requestId = AfterpayStringHelper::generateUuid();
		// dd($requestId);
	    $capturePaymentRequest = new AfterpayDeferredPaymentCaptureRequest([
	        'requestId' => $requestId,
	        'amount' => [$Payment_Amount, 'USD']
	    ]);
	    if(!is_numeric($request[ 'order_id' ])) {
			Session::flash('PlaceOrderError','Error in Processing Request, Please try again.');								
			return redirect('billing');
	    }
	    $capturePaymentRequest->setOrderId($request->order_id);

		$merchant = new AfterpayMerchantAccount();
		$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
		    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
				->setApiEnvironment($this->TRANSACTION_MODE)
		    	->setCountryCode('US');

        $capturePaymentRequest->setMerchantAccount($merchant);

		$merchantReference = "OR".Session::get('ShoppingCart.OrderID');
        $capturePaymentRequest->setMerchantReference($merchantReference);

		/////////// Log Start ////////////
		/*$cur_date = date("Y-m-d");
		$myFile = config('global.SITE_URL').'PayWithAfterpay/afterpay_logs/afterpay-log'.$cur_date.'.txt';
		if(@fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');

			$stringData .= chr(13) . chr(13) . 'Auth REQUEST == ' . serialize($request->order_id) . chr(13) . chr(13) ;
			$stringData .= chr(13) . chr(13) . 'Auth RESPONSE == ' . serialize($repsonse) . chr(13) . chr(13);

			fwrite($fh, $stringData);
			fclose($fh);
		}*/
		/////////// Log End ////////////

	    $capturePaymentRequest->send();

	    $capturePaymentResponse = $capturePaymentRequest->getResponse();
	    $repsonse = $capturePaymentResponse->getParsedBody();
	    // dd($capturePaymentResponse);

		$payment_gateway_response = json_encode($repsonse);

		$order_id = Session::get('ShoppingCart.OrderID');
	    if((isset($repsonse->status) && $repsonse->status == "APPROVED") && ($repsonse->paymentState == "CAPTURED" || $repsonse->paymentState == "PARTIALLY_CAPTURED") ) {
	    	$transaction_info = "This transaction has been approved.";

			$order_result = Order::select('payment_gateway_response')->where("orders_id","=",$order_id)->get();
			if($order_result && count($order_result) > 0) {
				$payment_gateway_response = $order_result[0]['payment_gateway_response']."\n\n==============\n\nCapture Response::".$payment_gateway_response;
			}

			$updAray = array (
								'pay_status' 	   			=> 'Paid',
								'status' 	   				=> 'Pending',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response,
								'afterpay_transaction_id' 	=> $repsonse->id,
							  );

			$uporderres = Order::Where("orders_id","=",$order_id)
								->update($updAray);	

			return redirect('order-receipt');
	    } else {

			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response
							  );
				
			$uporderres = Order::Where("orders_id","=",$order_id)
								->update($updAray);	

			$Message = 'Error in Processing Request, Please try again.';				
			if(isset($repsonse->errorId)){
				$Message = $repsonse->message;
			}
			Session::flash('CartError', $Message);								
			return redirect('shoppingcart');

	    }
	    /*if ($capturePaymentRequest->send()) {
	        $order = new AfterpayPayment($capturePaymentRequest->getResponse()->getParsedBody());
	        $this->pageData['paymentEvent'] = json_encode($capturePaymentRequest->getResponse()->getPaymentEvent());
	    } else {
	        $this->pageData['error'] = $capturePaymentRequest->getResponse()->getParsedBody();
	    }
	    dd($this->pageData);*/
	}



	function tryPing($pingRequest)
	{
	    try {
	        if ($pingRequest->send()) {
	            # Success

	            echo "Afterpay/HTTP is UP\n";
	        } else {
	            # A 3xx, 4xx, or 5xx series HTTP Response.
	            # Please log the response code,
	            # errorCode, errorId and message from the body (if available),
	            # or the CF-Ray ID otherwise.

	            $pingResponse = $pingRequest->getResponse();
	            $responseCode = $pingResponse->getHttpStatusCode();
	            $contentType = $pingResponse->getContentTypeSimplified();

	            if (is_object($body = $pingResponse->getParsedBody())) {
	                $errorCode = $body->errorCode;
	                $errorId = $body->errorId;
	                $message = $body->message;

	                echo "ERROR: Received unexpected HTTP {$responseCode} {$contentType} response from Afterpay with errorCode: {$errorCode}; errorId: {$errorId}; message: {$message}\n";
	            } else {
	                $cfRayId = $pingResponse->getParsedHeaders()[ 'cf-ray' ];

	                echo "ERROR: Received unexpected HTTP {$responseCode} {$contentType} response from Afterpay with CF-Ray ID: {$cfRayId}\n";
	            }
	        }
	    } catch (AfterpayNetworkException $e) {
	        # This generally indicates a transient network error, such as a connection reset
	        # or client timeout.

	        $curl_error_number = $e->getCode();
	        $curl_error_message = $e->getMessage();

	        echo "ERROR: Cannot connect to Afterpay via HTTP; caught Afterpay\SDK\Exception\NetworkException #{$curl_error_number}: '{$curl_error_message}'\n";
	    } catch (AfterpayParsingException $e) {
	        # This means that the SDK could not process the response
	        # according to the Content-Type that the API declared.

	        $contentType = $pingRequest->getResponse()->getContentTypeSimplified();
	        $json_parsing_error_number = $e->getCode();
	        $json_parsing_error_message = $e->getMessage();

	        echo "ERROR: Received unparsable {$contentType} response from Afterpay; caught Afterpay\SDK\Exception\ParsingException #{$json_parsing_error_number}: '{$json_parsing_error_message}'\n";
	    }
	}


	public function PhoneOrder(Request $request)
	{
		$OrderID = Session::get('phoneorder_detail.order_id');
		
		if( $OrderID <= 0) 
		{
			Session::flash('error','Error in Processing Request, Please try again.');		
			return redirect(config('global.SITE_URL'));
		}
		
		$merchant = new AfterpayMerchantAccount();

		$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
		    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
				->setApiEnvironment($this->TRANSACTION_MODE)
		    	->setCountryCode('US');


	    $getConfigurationRequest = new AfterpayGetConfigurationRequest();

		$getConfigurationRequest->setMerchantAccount($merchant);

		$getConfigurationRequest->send();

		$body = $getConfigurationRequest->getResponse()->getParsedBody();

		// dd($body);
		$OrderRs = DB::table('pu_orders as o')
								->join('pu_customer as c','o.customer_id','=','c.customer_id')
								->select('o.orders_id', 'o.orders_no','o.customer_id', 'o.order_total', 'o.bill_email','o.bill_first_name','o.bill_last_name','o.bill_company','o.bill_email','o.bill_address1','o.bill_address2','o.bill_city','o.bill_zip','o.bill_state','o.bill_country','o.bill_phone','o.ship_first_name','o.ship_last_name','o.ship_company','o.ship_email','o.ship_address1','o.ship_address2','o.ship_city','o.ship_zip','o.ship_state','o.ship_country','o.ship_phone','c.first_name','c.last_name','c.email')
								->where('orders_id', '=', $OrderID)
								->get();
		
		if($OrderRs->count() <= 0 || (isset($OrderRs[0]->order_total) && $OrderRs[0]->order_total <= 0)){
			Session::flash('error','Error in Processing Request, Please try again.');		
			return redirect(config('global.SITE_URL')."payment/".base64_encode($OrderID));
		}

		$Payment_Amount  = NumberFormat($OrderRs[0]->order_total);
		$Payment_Currency  = 'USD';
		// $payload['merchantReference'] = "OR".Session::get('ShoppingCart.OrderID');
		$setConsumer = [];
		if($OrderRs[0]->customer_id > 0) {
			$setConsumer['givenNames'] = $OrderRs[0]->first_name;		//optional
			$setConsumer['surname'] = $OrderRs[0]->last_name;		//optional
			$setConsumer['email'] = $OrderRs[0]->email;	//required
		} else {
			Session::flash('error','Error in Processing Request, Please try again.');								
			return redirect(config('global.SITE_URL')."payment/".base64_encode($OrderID));
		}

		$setMerchant['redirectConfirmUrl'] = url('afterpay/success_phoneorder');
		$setMerchant['redirectCancelUrl'] = url('afterpay/cancel_phoneorder');
		
		$setBilling = [];
		$setBilling['name'] = $OrderRs[0]->bill_first_name." ".$OrderRs[0]->bill_last_name;
		$setBilling['line1'] = $OrderRs[0]->bill_address1;
		$setBilling['area1'] = $OrderRs[0]->bill_city;
		$setBilling['region'] = $OrderRs[0]->bill_state;
		$setBilling['postcode'] = $OrderRs[0]->bill_zip;
		$setBilling['countryCode'] = $OrderRs[0]->bill_country;
		$setBilling['phoneNumber'] = $OrderRs[0]->bill_phone;

		$setShipping = [];
		$setShipping['name'] = $OrderRs[0]->ship_first_name." ".$OrderRs[0]->ship_last_name;
		$setShipping['line1'] = $OrderRs[0]->ship_address1;
		$setShipping['area1'] = $OrderRs[0]->ship_city;
		$setShipping['region'] = $OrderRs[0]->ship_state;
		$setShipping['postcode'] = $OrderRs[0]->ship_zip;
		$setShipping['countryCode'] = $OrderRs[0]->ship_country;
		$setShipping['phoneNumber'] = $OrderRs[0]->ship_phone;


		//Items details
		$Data_order_detail = OrderDetail::where('orders_id', '=', $OrderID)
								->get();
		$setItems = [];
			
		for($i=0;$i < count($Data_order_detail); $i++){
			$prd_res = DB::table('pu_products as p')
								->join('pu_products_category as pc','p.products_id','=','pc.products_id')
								->select('p.products_id', 'p.product_name' ,'p.sku', 'p.sale_price', 'p.current_stock','p.short_description','p.status','pc.category_id')
								->where('sku', '=', $Data_order_detail[$i]['sku'])
								->where('status', '=', '1')
								->get();
								
		    $prd_sku = $prd_res[0]->sku;	
		    $iprod_id = $prd_res[0]->products_id;
		    $product_name = $prd_res[0]->product_name;
		    $short_description = $prd_res[0]->short_description;	
		    $category_id = $prd_res[0]->category_id;	
		   
			// $p_link = $generalobj->getProductRewriteURL($prd_res[0]['products_id'], $prd_res[0]['product_name']);
			$p_link = SetProductURL($iprod_id, $product_name, $category_id);
			
			$setItems[] = [
		        'name' => $Data_order_detail[$i]['product_name'],
		        'sku' => $Data_order_detail[$i]['sku'],
		        'quantity' => $Data_order_detail[$i]['quantity'],
		        'pageUrl' => $p_link,
		        'price' => [$Data_order_detail[$i]['total'], 'USD']
		    ];
			 
			//$payload['items'][$i]['imageUrl'] = $ItemArr[$i]['ProductName'];
		}

		/**
		 * Method B:
		 *
		 * Instantiating an empty Request class, then setting the values of each field using the individual
		 * setter methods. If Automatic Validation is disabled, you can load in all of the data and then iterate over
		 * the list of errors, rather than only catching the first.
		 */
		// dd($setConsumer, $setBilling, $setShipping, $setItems, $setMerchant);
		\Afterpay\SDK\Model::setAutomaticValidationEnabled(false);

		$createCheckoutRequest = new AfterpayCreateCheckoutRequest();

		$createCheckoutRequest
		    ->setAmount($Payment_Amount, 'USD')
		    ->setConsumer($setConsumer)
		    ->setBilling($setBilling)
		    ->setShipping($setShipping)
		    /*->setCourier([
		        'shippedAt' => '2019-01-01T00:00:00+10:00',
		        'name' => 'FedEx',
		        'tracking' => 'AA0000000000000',
		        'priority' => 'STANDARD'
		    ])*/
		    ->setItems($setItems)
		    /*->setDiscounts([
		        [
		            'displayName' => '20% off SALE',
		            'amount' => [ '24.00', 'USD' ]
		        ]
		    ])*/
		    ->setMerchant($setMerchant)
		    /*->setTaxAmount('0.00', 'USD')
		    ->setShippingAmount('0.00', 'USD')*/
		;
		if ($createCheckoutRequest->isValid()) {
			$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
			    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
					->setApiEnvironment($this->TRANSACTION_MODE)
			    	->setCountryCode('US');

			$createCheckoutRequest->setMerchantAccount($merchant);

		    $createCheckoutRequest->send();

		    // $response = $createCheckoutRequest->getRawLog();

		    $createCheckoutResponse = $createCheckoutRequest->getResponse();
    		$response = $createCheckoutResponse->getParsedBody();
				// dd($response);echo $OrderID;exit;
			if ($createCheckoutResponse->isSuccessful()) {
				// dd($response->redirectCheckoutUrl);
				if(isset($response->token) && $response->token != ""){
					$redirect = $response->redirectCheckoutUrl;
					$token = $response->token;
					$expires = $response->expires;
					
					$updAray = array (
										'status' => 'Sent To AfterPay',
										'payment_type' 			=> 'PAYMENT_PAYWITHAFTERPAY',
										'payment_method' 		=> 'Pay With Afterpay'
									);

					$uporderres = Order::Where("orders_id","=",$OrderID)->update($updAray);

			    	return redirect($response->redirectCheckoutUrl);
				}else{
					$transaction_info = "This transaction has been Declined.";
					$Payment_response = json_encode($response);

					$updAray = array (
										'status' 	   			=> 'Declined',
										'transaction_info' 		=> $transaction_info,
										'payment_gateway_response' => $Payment_response
									  );

					$updOrder = Order::Where("orders_id","=",$OrderID)->update($updAray);		
					
					Session::flash('error','Error in Processing Request, Please try again.');								
					return redirect(config('global.SITE_URL')."payment/".base64_encode($OrderID));
				}

			} else {
			    $this->error = $response;
			}
		} else {
		    $response = $createCheckoutRequest->getValidationErrorsAsHtml();
		}

	}
	
	public function Success_Phoneorder(Request $request)
	{
		// dd($request->all());
		if($request->has('status') && $request->status == "SUCCESS") {
			if ($request->has('orderToken') && $request->orderToken != '') {

			    	$deferredPaymentAuthRequest = new AfterpayDeferredPaymentAuthRequest([
				        'token' => urlencode($request->orderToken)
				    ]);

					$merchant = new AfterpayMerchantAccount();
					$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
					    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
							->setApiEnvironment($this->TRANSACTION_MODE)
					    	->setCountryCode('US');

				    if (!is_null($merchant)) {
				        $deferredPaymentAuthRequest->setMerchantAccount($merchant);
				    }

				    $deferredPaymentAuthRequest->send();

				    $deferredPaymentAuthResponse = $deferredPaymentAuthRequest->getResponse();
				    $repsonse = $deferredPaymentAuthResponse->getParsedBody();

					/////////// Log Start ////////////
					// $cur_date = date("Y-m-d");
					// $myFile = config('global.SITE_URL').'PayWithAfterpay/afterpay_logs/afterpay-log'.$cur_date.'.txt';
					// if(@fopen($myFile, 'a+'))
					// {
						// $fh = fopen($myFile, 'a+');

						// $stringData .= chr(13) . chr(13) . 'Auth REQUEST == ' . serialize($request->orderToken) . chr(13) . chr(13) ;
						// $stringData .= chr(13) . chr(13) . 'Auth RESPONSE == ' . serialize($repsonse) . chr(13) . chr(13);

						// fwrite($fh, $stringData);
						// fclose($fh);
					// }
					/////////// Log End ////////////
			
					Session::put('phoneorder_detail.Afterpay.AP_Auth_Token',$repsonse->token);
					Session::put('phoneorder_detail.Afterpay.AP_Auth_ID',$repsonse->id);
					Session::put('phoneorder_detail.Afterpay.AP_Auth_Status',$repsonse->status);
		
				    if($deferredPaymentAuthRequest->getResponse()->isApproved() && $repsonse->paymentState == "AUTH_APPROVED" && $repsonse->token != "") {
						Session::put('phoneorder_detail.Afterpay.AP_Auth_Amt',$repsonse->originalAmount->amount);
						Session::put('phoneorder_detail.Afterpay.AP_Auth_Currency',$repsonse->originalAmount->currency);
						
						$customer_id = Session::get('phoneorder_detail.customer_id');
						$order_id = Session::get('phoneorder_detail.order_id');
						
						if($customer_id > 0) {
							$payment_gateway_response = "Auth Response::".json_encode($repsonse);
							$updAray = array (
												'payment_gateway_response' 	=> $payment_gateway_response,
												'afterpay_transaction_id' 	=> $repsonse->id
											  );

							$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);
							
							$capturePayment = url('afterpay/dopayment_phoneorder/'.$repsonse->id);
							return redirect($capturePayment);
						} else {

							//order order not confirmed by customer
							//status >> CANCELLED
							$transaction_info = "This transaction has been Declined.";
							$updAray = array (
												'status' 	   				=> 'Declined',
												'transaction_info' 			=> $transaction_info,
												'payment_gateway_response' 	=> $transaction_info
											  );
											  
							$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);	

							Session::flash('error','Error in Processing Request, Please try again.');								
							return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));

						}
				    } else {
						$transaction_info = "This transaction has been Declined.";
						$Payment_response = json_encode($repsonse);
						
						$updAray = array (
											'status' 	   				=> 'Declined',
											'transaction_info' 			=> $transaction_info,
											'payment_gateway_response' 	=> $Payment_response
										  );

						$order_id = Session::get('phoneorder_detail.order_id');
						$updOrder = Order::Where("orders_id","=",$order_id)->update($updAray);	

						Session::flash('error','Error in Processing Request, Please try again.');								
						return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
				    }
			}
		} else {

			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> "This transaction has been Declined by User."
							  );
							  
			$order_id = Session::get('phoneorder_detail.order_id');
			$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);
				
			Session::flash('error','Error in Processing Request, Please try again.');								
			return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
		}

		// dd('Success', $request->all(), $this->order, $this->error);
	}

	public function DoPayment_Phoneorder(Request $request)
	{
		$customer_id = Session::get('phoneorder_detail.customer_id');
		$order_id = Session::get('phoneorder_detail.order_id');
		$Payment_Amount = NumberFormat(Session::get('phoneorder_detail.order_amt'));
		$Payment_Currency  = 'USD';
		
		$requestId = AfterpayStringHelper::generateUuid();
		// dd($requestId);
	    $capturePaymentRequest = new AfterpayDeferredPaymentCaptureRequest([
	        'requestId' => $requestId,
	        'amount' => [$Payment_Amount, 'USD']
	    ]);
	    if(!is_numeric($request[ 'order_id' ])) {
			Session::flash('error','Error in Processing Request, Please try again.');								
			return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
	    }
	    $capturePaymentRequest->setOrderId($request->order_id);

		$merchant = new AfterpayMerchantAccount();
		$merchant->setMerchantId($this->ap_arr['PaywithAfterpay_Merchant_ID'])
		    	->setSecretKey($this->ap_arr['PaywithAfterpay_Merchant_Secret_Key'])
				->setApiEnvironment($this->TRANSACTION_MODE)
		    	->setCountryCode('US');

        $capturePaymentRequest->setMerchantAccount($merchant);

		$merchantReference = "OR".$order_id;
        $capturePaymentRequest->setMerchantReference($merchantReference);

		/////////// Log Start ////////////
		/*$cur_date = date("Y-m-d");
		$myFile = config('global.SITE_URL').'PayWithAfterpay/afterpay_logs/afterpay-log'.$cur_date.'.txt';
		if(@fopen($myFile, 'a+'))
		{
			$fh = fopen($myFile, 'a+');

			$stringData .= chr(13) . chr(13) . 'Auth REQUEST == ' . serialize($request->order_id) . chr(13) . chr(13) ;
			$stringData .= chr(13) . chr(13) . 'Auth RESPONSE == ' . serialize($repsonse) . chr(13) . chr(13);

			fwrite($fh, $stringData);
			fclose($fh);
		}*/
		/////////// Log End ////////////

	    $capturePaymentRequest->send();

	    $capturePaymentResponse = $capturePaymentRequest->getResponse();
	    $repsonse = $capturePaymentResponse->getParsedBody();
	    // dd($capturePaymentResponse);

		$payment_gateway_response = json_encode($repsonse);

	    if((isset($repsonse->status) && $repsonse->status == "APPROVED") && ($repsonse->paymentState == "CAPTURED" || $repsonse->paymentState == "PARTIALLY_CAPTURED") ) {
	    	$transaction_info = "This transaction has been approved.";

			$order_result = Order::select('payment_gateway_response')->where("orders_id","=",$order_id)->get();
			if($order_result && $order_result->count() > 0) {
				$payment_gateway_response = $order_result[0]['payment_gateway_response']."\n\n==============\n\nCapture Response::".$payment_gateway_response;
			}

			$updAray = array (
								'pay_status' 	   			=> 'Paid',
								'status' 	   				=> 'Pending',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response,
								'afterpay_transaction_id' 	=> $repsonse->id,
								'phoneorder_paymentdate' => date("Y-m-d H:i:s")
							  );

			$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);	

			//stock,other related changes start
			$response_arr = $this->PhoneorderPaymentSuccess('Afterpay');
			if($response_arr['success'] == "1"){
				Session::flash('success',$response_arr['err_msg']);
			}else{
				Session::flash('error',$response_arr['err_msg']);
			}	
			return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
	    } else {

			$transaction_info = "This transaction has been Declined.";
			$updAray = array (
								'status' 	   				=> 'Declined',
								'transaction_info' 			=> $transaction_info,
								'payment_gateway_response' 	=> $payment_gateway_response
							  );
				
			$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);	

			$Message = 'Error in Processing Request, Please try again.';				
			if(isset($repsonse->errorId)){
				$Message = $repsonse->message;
			}
			Session::flash('error', $Message);								
			return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));

	    }
	    /*if ($capturePaymentRequest->send()) {
	        $order = new AfterpayPayment($capturePaymentRequest->getResponse()->getParsedBody());
	        $this->pageData['paymentEvent'] = json_encode($capturePaymentRequest->getResponse()->getPaymentEvent());
	    } else {
	        $this->pageData['error'] = $capturePaymentRequest->getResponse()->getParsedBody();
	    }
	    dd($this->pageData);*/
	}

	public function Cancel_Phoneorder(Request $request)
	{
		$order_id = Session::get('phoneorder_detail.order_id');
		$transaction_info = "This transaction has been Declined.";
		$updAray = array (
							'status' 	   				=> 'Declined',
							'transaction_info' 			=> $transaction_info,
							'payment_gateway_response' 	=> "This transaction has been Declined by User."
						  );
		
		$uporderres = Order::Where("orders_id","=",$order_id)->update($updAray);
		return redirect(config('global.SITE_URL')."payment/".base64_encode($order_id));
	}
}
?>
