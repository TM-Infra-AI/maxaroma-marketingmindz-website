<?php
namespace App\Http\Controllers;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Models\SiteSettings;

class ApiController extends Controller
{	
	public $API_KEY;
	public function __construct(Request $request)
	{
        config(['app.debug' => true]);
        /*
		config(['app.debug' => true]);
		$Settings = SiteSettings::where('var_name','=','YOTPO_API_KEY')->get();
		$this->API_KEY = '';
		if($Settings->count() > 0 )
		{
			$this->API_KEY = $Settings[0]->setting;
		}
		$this->AuthenticateRequest($request);
        */
        $this->API_KEY = 'p1m8ysq2s3';
	}
    public function YotpoCouponWebhook(Request $request)
    {
        $data = file_get_contents('php://input');
        $fp = fopen(config('global.PHYSICAL_PATH').'yotpo-coupon-webhook.txt', 'a');  
		fwrite($fp, $data);  
		fclose($fp);  
    }
	public function AuthenticateRequest(Request $request)
	{
		if (!$request->hasHeader('x-api-key')) {
			return response()->json([
				'errors'    => 'API key is missing'
			],400)->send();
		} else if($request->header('x-api-key') != $this->API_KEY){
			return response()->json([
				'errors'    => 'Access Denied. Invalid API key'
			],401)->send();
		}
	}
	
	public function GetContacts(Request $request)
	{
		$AllCustomers = Customer::select('customer_id','first_name','last_name','email')->where('status','=','1')->where('is_deleted','=','No')
							->where('registration_type','=','M')->where('email','!=','')
							->where('omnisend_accountid','!=','')->limit(5)->get()->toArray();
							
		return response()->json(['total' => count($AllCustomers),	'customers' => $AllCustomers]);
	}
	
    public function CreateCoupon(Request $request)
	{
		//$this->AuthenticateRequest($request);
        $data = file_get_contents('php://input');
        $fp = fopen(config('global.PHYSICAL_PATH').'yotpo-coupon-webhook.txt', 'a');  
		fwrite($fp, $data);  
		fclose($fp); 
		$validator = $this->ValidateCouponParams($request,'create');
        if($validator->fails()) {
            if(count($validator->errors()->messages()) > 0)
            {
                $AllErrors = $validator->errors()->messages();
                if(isset($AllErrors['secret']))
                {
                    return response()->json([
                        'errors'    => ['secret' => $AllErrors['secret']]
                     ],401)->send();
                } else {
                    return response()->json([
                        'errors'    => $validator->errors()->messages()
                     ],400)->send();
                }
            }
        }
         
        $YotpoCoupon = [
            'coupon_title' => 'Yotpo Coupon',
            'coupon_number' => $request->coupon_code,
            'discount' => $request->discount_amount,
            'source' => 'Yotpo',
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+1 years')),
            'type' => '0',
            'orders' => '0',
            'order_amount' => 1,
            'is_once' => '1',
            'status' => '1',
            'customer_email' => $request->customer_email,
        ];
        
        $Coupon = Coupon::create($YotpoCoupon);
        if($Coupon)
        {
            return response()->json(['id' => $Coupon->coupon_id],200);
        } else {
            return response()->json(['errors' => 'error in coupon creation'],400);
        }
    }
    
    public function CancelCoupon(Request $request)
    {
        $validator = $this->ValidateCouponParams($request,'cancel');
        if($validator->fails()) {
            if(count($validator->errors()->messages()) > 0)
            {
                $AllErrors = $validator->errors()->messages();
                if(isset($AllErrors['secret']))
                {
                    return response()->json([
                        'errors'    => ['secret' => $AllErrors['secret']]
                     ],401)->send();
                } else {
                    return response()->json([
                        'errors'    => $validator->errors()->messages()
                     ],400)->send();
                }
            }
        }
        $YotpoCoupon = [
            'status' => '0'
        ];
        
        $Coupon = Coupon::where('coupon_id','=',$request->coupon_id)->update($YotpoCoupon);
        if($Coupon)
        {
            return response()->json(['success' => 'Coupon cancelled successfully'],200);
        } else {
            return response()->json(['errors' => 'error in coupon cancellation'],400);
        }
    }
    
    public function ValidateCouponParams(Request $request,$reqType='')
	{
        $Rules = [];
        $Errors = [];
        switch($reqType)
        {
            case 'create':
                $Rules = [
                    'secret' => ['required',Rule::in($this->API_KEY)],
                    'coupon_code' => 'required|unique:pu_coupon,coupon_number',
                    'discount_amount' => 'required|numeric|min:1',
                    'discount_amount_cents' => 'required|numeric|min:1',
                    'currency' => ['required',Rule::in('USD')],
                    'customer_email' => 'required|email|regex:/(.+)@(.+)\.(.+)/i'
                ];
                
                break;
            case 'cancel':
                $Rules = [
                    'secret' => ['required',Rule::in($this->API_KEY)],
                    'coupon_id' => [
                        'required',
                        'numeric',
                        Rule::exists('pu_coupon')                     
                        ->where('source', 'Yotpo')->where('status','1'),
                    ]
                ];
                break;    
            default:
                $Rules = [];
                break;                
        }
        $Messages = [
                    'secret.in' => 'Access Denied. Invalid Secret key',
                    'coupon_code.unique' => 'The Coupon code already exist, please try again with new coupon code.',
                    'currency.in' => 'The Currency is invalid',
                    'customer_email.email' => 'The customer email address is invalid',
                    'customer_email.regex' => 'The customer email address is invalid',
                    'coupon_id.numeric' => 'The Coupon id is invalid',
                    'coupon_id.exists' => 'The Coupon id is invalid',
                ];
        if(count($Rules) > 0)
        {
            $validator = (Validator::make($request->all(), $Rules,$Messages));
            return $validator;
        }
	}
    
    /*
	public function CreateCoupon(Request $request)
	{
		//$this->AuthenticateRequest($request);
		$this->ValidateCouponParams($request);
		$Coupons = [];
		$Total = 1;
		if(isset($request->count) && $request->count > 0)
			$Total = $request->count;
		$CouponAmount = $request->amount;
		
		for($i=0;$i<$Total;$i++)
		{
            $NewCouponCode = substr(md5(uniqid(rand(), true)),0,10);
            $ChkCoupon = Coupon::where('coupon_number','=',$NewCouponCode)->get();
            if($ChkCoupon && $ChkCoupon->count() > 0)
            {
                $NewCouponCode = substr(md5(uniqid(rand(), true)),0,10);
            }
            $YotpoCoupon = [
                'coupon_title' => 'Yotpo Coupon',
                'coupon_number' => $NewCouponCode,
                'discount' => $CouponAmount,
                'source' => 'Yotpo',
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+1 years')),
                'type' => '0',
                'orders' => '0',
                'order_amount' => 1,
                'is_once' => '1',
                'status' => '1'
            ];
            $Coupon = Coupon::create($YotpoCoupon);
            if($Coupon)
            {
                $Coupons[$i]['coupon_id'] = $Coupon->coupon_id;
                $Coupons[$i]['coupon'] = $NewCouponCode;
                $Coupons[$i]['amount'] = $CouponAmount;
            }
		}
		return response()->json(['total' => $Total, 'coupons' => $Coupons],200);
	}
	*/
	
	
	public function CouponList()
	{
		$Params = array('total_coupons' => 5, 'amount' => 10);
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => url('/api/create_coupons'),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => http_build_query($Params),
			CURLOPT_HTTPHEADER => array(
				"x-api-key: 1234567890",
				"cache-control: no-cache",
				"content-type: application/x-www-form-urlencoded"
			),
		));
		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo "cURL Error #:" . $err;
		} else {
			echo "<pre>";
			print_r(json_decode($response,true));
			echo "</pre>";
		}
	}
	
	public function getavailablequantity()
	{
	    dd();
		$Params = array('ModifiedBeforeDateTimeUtc' => time(), 'PageNumber' => 0,'PageSize'=>1000,'ExpandAlternateSkus'=>false,'TenantToken'=>"EXBzDdcDiF8Z27fpd5BMpi5nlYeMMz4NAXX0IWHwv4I=",'UserToken'=>"cykl6FqQtQEOJ2y8jWYfk5XChVzPOV+fZcQIjSh2+8Y=");
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => url('https://app.skuvault.com/api/inventory/getAvailableQuantities'),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => http_build_query($Params),
			CURLOPT_HTTPHEADER => array(
				"x-api-key: 1234567890",
				"cache-control: no-cache",
				"content-type: application/x-www-form-urlencoded"
			),
		));
		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo "cURL Error #:" . $err;
		} else {
			echo "<pre>";
			print_r(json_decode($response,true));
			echo "</pre>";
		}
	}
}