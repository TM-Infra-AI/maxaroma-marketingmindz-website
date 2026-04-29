<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Traits\CartTrait;
use App\Http\Controllers\Traits\EncryptTrait;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\AuthorizeFundLog;
use App\Models\Customer;
use Stripe\Stripe;
use Session;
use Stripe\Exception\ApiErrorException;

class StripeController extends Controller
{
	use CartTrait;
	use EncryptTrait;	
	public function SetStripe(Request $request)
	{
		$OrderID = Session::get('ShoppingCart.OrderID');
		$CustEmail = Session::get('ShoppingCart.BillingAddress.email');
		$db_res = PaymentMethod::select('pm_group_name', 'pm_gateway_name','pm_details')
							->where('pm_group_name','=', 'PAYMENT_STRIPE')
							->where('pm_status', '=', 'Active')
							->get();
		if($db_res->count() > 0)
		{
			$arrPEVar		= unserialize($db_res[0]->pm_details);	
			$STRIPE_KEY = $this->decrypt($arrPEVar['Secret_Key']);
		}
        /*
		if(Session::get('sess_useremail') == 'wgequaldev@gmail.com' || Session::get('sess_useremail') == 'gequaldev@gmail.com' || Session::get('sess_useremail') == 'qqualdev@gmail.com' || Session::get('sess_useremail') == 'gequaldev123@gmail.com')
		{ 
			Stripe::setApiKey(env('STRIPE_KEY'));
		} else {
			Stripe::setApiKey($STRIPE_KEY);
		}
        */
        
		$stripe_amt = round($this->GetNetTotal() * 100);
		
		$session = \Stripe\Checkout\Session::create([
			'customer_email' => $CustEmail,
			'client_reference_id' => "OR".$OrderID,
			'payment_method_types' => ['card'],
			'line_items' => [[
				'name'	 => 'Net Total',
				'amount' => $stripe_amt,
				'currency' => 'usd',
				'quantity' => 1
			]],
            'payment_method_options' => [
                'wechat_pay' => [
                  'client' => "web"
                ],
            ],
			'cancel_url' =>  config('global.SITE_URL')."billing",
			'success_url' => config('global.SITE_URL')."order-receipt",
			'expand' => ['url']
		]);	
		$OrderUpdate = [
					'stripesessionid'   => $session->id,
					'paymentintentid' 	=> $session->payment_intent,
					'status'			=> 'Sent To Stripe']; 
		
		$Order = Order::where('orders_id','=',$OrderID)->update($OrderUpdate);
		
		if($session && isset($session->url) && $session->url != '')
		{
			return redirect($session->url);
		}
	}
	public function AddFund(Request $request)
	{
		$db_res = PaymentMethod::select('pm_group_name', 'pm_gateway_name','pm_details')
							->where('pm_group_name','=', 'PAYMENT_STRIPE')
							->where('pm_status', '=', 'Active')
							->get();					
		if(isset($request->pagefrom) && $request->pagefrom == 'dropshipfund')
			$Page = "dropshipper-fund-summary.html";					
		else if(isset($request->pagefrom) && $request->pagefrom == 'billing')
			$Page = "billing";
		else if(isset($request->pagefrom) && $request->pagefrom == 'import-dropship-orders')
			$Page = "imported-order-list.html";
		else
			$Page = 'shoppingcart';	
		if($db_res->count() > 0)
		{
			$arrPEVar		= unserialize($db_res[0]->pm_details);	
			$STRIPE_KEY = $this->decrypt($arrPEVar['Secret_Key']);
		}
		if(Session::get('sess_useremail') == 'wgequaldev@gmail.com' || Session::get('sess_useremail') == 'gequaldev@gmail.com' || Session::get('sess_useremail') == 'qqualdev@gmail.com' || Session::get('sess_useremail') == 'gequaldev1234567@gmail.com')
		{ 
			Stripe::setApiKey(env('STRIPE_KEY'));
		} else {
			Stripe::setApiKey($STRIPE_KEY);
		}
		//$fund_amount = $request->fund_amount;
		$fund_amount = Session::get('CurrFundAmount');
		$currencyCode 	  = 'USD';
		$insAray = array(
			"status" => 'Pending'
		);
		$fund_order = AuthorizeFundLog::create($insAray);
		$fund_order_id = $fund_order->auth_log_id; 
		$Customer = Customer::where('customer_id','=',Session::get('sess_icustomerid'))
					->where('is_dropshipper','=','Yes')->where('eusertype','=','Wholesaler')
					->where('status','=','1')->get();	
		$stripe_amt = round($fund_amount * 100);
		try{
			$session = \Stripe\Checkout\Session::create([
				'customer_email' => $Customer[0]['email'],
				'client_reference_id' => 'FUND-PU-'.$fund_order_id,
				'payment_method_types' => ['card'],
				'line_items' => [[
					'name'	 => 'Net Total',
					'amount' => $stripe_amt,
					'currency' => 'usd',
					'quantity' => 1
				]],
				'cancel_url' =>  config('global.SITE_URL').$Page,
				'success_url' => config('global.SITE_URL').$Page,
				'expand' => ['url']
			]);
		}catch(exception $e){
			echo $e->getMessage();
			exit;
		}
		$INV_ORDER_ID 			= 'FUND-PU-'.$fund_order_id;
		$UpdateOrderInformation = array(
									'inv_order_id'              => $INV_ORDER_ID,
									'stripesessionid'   		=> $session->id,
									'paymentintentid' 			=> $session->payment_intent,
									'customer_id'       		=> (int)Session::get('sess_icustomerid'),
									'fund_amount'       		=> $fund_amount,
									'transaction_info' 			=> "This transaction has been sent to stripe.",
									'status'					=> 'Sent To Stripe'
							   );
		$uporderres = AuthorizeFundLog::where('auth_log_id','=',$fund_order_id)->update($UpdateOrderInformation);					   
		if($session && isset($session->url) && $session->url != '')
		{
			return redirect($session->url);
		}
	}
	
	public function PhoneOrder(Request $request)
	{
		$OrderID = Session::get('phoneorder_detail.order_id');
	
		$OrderRs = DB::table('pu_orders as o')
								->join('pu_customer as c','o.customer_id','=','c.customer_id')
								->select('o.orders_id', 'o.orders_no','o.customer_id', 'o.order_total', 'o.bill_email','c.email')
								->where('orders_id', '=', $OrderID)
								->get();
		
		if($OrderRs->count() <= 0){
			return redirect()->back();
		}
			$CustEmail = $OrderRs[0]->bill_email;
			$db_res = PaymentMethod::select('pm_group_name', 'pm_gateway_name','pm_details')
								->where('pm_group_name','=', 'PAYMENT_STRIPE')
								->where('pm_status', '=', 'Active')
								->get();
			if($db_res->count() > 0)
			{
				$arrPEVar		= unserialize($db_res[0]->pm_details);	
				$STRIPE_KEY = $this->decrypt($arrPEVar['Secret_Key']);
			}
			//Stripe::setApiKey(env('STRIPE_KEY'));
			
			if($CustEmail == 'wgequaldev@gmail.com' || $CustEmail == 'gequaldev@gmail.com' || $CustEmail == 'qqualdev@gmail.com' || $CustEmail == 'gequaldev1234567@gmail.com')
			{ 
				Stripe::setApiKey(env('STRIPE_KEY'));
			} else {
				Stripe::setApiKey($STRIPE_KEY);
			}
			
			//$fund_amount = $request->fund_amount;
			$stripe_amt = round($OrderRs[0]->order_total * 100);
			try{
				$session = \Stripe\Checkout\Session::create([
					'customer_email' => $CustEmail,
					'client_reference_id' => "OR".$OrderID,
					'payment_method_types' => ['card'],
					'line_items' => [[
						'name'	 => 'Net Total',
						'amount' => $stripe_amt,
						'currency' => 'usd',
						'quantity' => 1
					]],
					'cancel_url' =>  config('global.SITE_URL')."payment_process/".base64_encode($OrderID)."/".base64_encode("0"),
					'success_url' => config('global.SITE_URL')."payment_process/".base64_encode($OrderID)."/".base64_encode("1"),
					'expand' => ['url']
				]);
			}catch(exception $e){
				echo $e->getMessage();exit;
			}
			$UpdateOrderInformation = [
						'stripesessionid'   => $session->id,
						'paymentintentid' 	=> $session->payment_intent,
						'status'			=> 'Sent To Stripe',
						'payment_type'		=> 'PAYMENT_STRIPE',
						'payment_method'	=> 'Credit Card',
					]; 
			$Order = Order::where('orders_id','=',$OrderID)->update($UpdateOrderInformation);
			if($session && isset($session->url) && $session->url != '')
			{
				return redirect($session->url);
			}
	}
}
