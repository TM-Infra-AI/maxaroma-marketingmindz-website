<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\StaticPages;
use App\Models\FAQS;
use App\Models\Currency;
use App\Models\MetaInfo;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\GiftCertificate;
use App\Models\Products;
use App\Models\CustRequest;

use App\Http\Controllers\Traits\CommonTrait;
use App\Http\Controllers\Traits\VendorTrait;
use App\Http\Controllers\Traits\CartTrait;
	
use DB;
use Session;
use Cache;
use Illuminate\Support\Facades\Auth;

class StaticpageController extends Controller
{
	use CommonTrait;
	use VendorTrait;
	use CartTrait;
	public $PageData;
	
	public function __construct()
	{
		$this->PageData['CSSFILES'] = ['static.css'];	
		$PageType = 'NR';
		$MetaInfo = MetaInfo::where('type','=',$PageType)->get(); 
		if($MetaInfo->count() > 0 )
		{
			$this->PageData['meta_title'] = $MetaInfo[0]->meta_title;
			$this->PageData['meta_description'] = $MetaInfo[0]->meta_description;
			$this->PageData['meta_keywords'] = $MetaInfo[0]->meta_keywords;
		}
	}
	
	public function show(Request $request)
	{   
		$Page = Route::currentRouteName();
		
		if($Page != '')
		{
			if($Page == 'shipping_policy')
			{
				if(Session::has('eusertype') && Session::get('eusertype') == 'Wholesaler')
					$Page = 'wholesaler_shipping_policy';
			}
			$PData = StaticPages::where('name','=',$Page)->where('status','=','1')->get();
			$Content = $PData[0]->content;
			
			if (str_contains($Content, '{$Site_URL}')) {
				$Content = str_replace('{$Site_URL}',config('global.SITE_URL'),$Content);
			}
			
			if($Page == 'site_map')
			{
				$MainCatLink = "";
				$menu_array = Cache::get('menu_array');
				foreach($menu_array as $maincat)
				{
					if($maincat['parent_id'] == 0)
						$MainCatLink.='<li><a href="'.$maincat['menu_link'].'">'.ucwords(strtolower($maincat['menu_title'])).'</a></li>';
				}
				$Content = str_replace('{$site_map_cat_str}',$MainCatLink,$Content);
			
				//$Content = str_replace('{$Site_URL}',config('global.SITE_URL'),$Content);
			}
			
        
			/*if($Page == 'affiliate-program'){
				$Content = str_replace('{$Site_URL}',config('global.SITE_URL'),$Content);
			}*/

			$this->PageData['PageTitle'] = $PData[0]->title;
			$this->PageData['PageContent'] = $Content;
			$DefaultMetaPage = ['privacy_policy','terms_and_conditions'];
			if(!in_array($Page,$DefaultMetaPage))
			{
				$this->PageData['meta_title'] = $PData[0]->meta_title;
				$this->PageData['meta_description'] = $PData[0]->meta_description;
				$this->PageData['meta_keywords'] = $PData[0]->meta_keywords;
			}
			if($Page == 'reward_point_program')
			{
				$this->PageData['CSSFILES'][]='myaccount.css';
				return view('staticpages.reward-point-program')->with($this->PageData);
			}
		}
		
		return view('staticpages.main')->with($this->PageData);		
	}

	public function BrandPerfume(Request $request)
	{
		$this->PageData['BrandsList'] = BrandsList();
		$this->PageData['JSFILES'] = ['brandlist.js'];
		$PageType = 'DP';
		$MetaInfo = MetaInfo::where('type','=',$PageType)->get(); 
		if($MetaInfo->count() > 0 )
		{
			$this->PageData['meta_title'] = $MetaInfo[0]->meta_title;
			$this->PageData['meta_description'] = $MetaInfo[0]->meta_description;
			$this->PageData['meta_keywords'] = $MetaInfo[0]->meta_keywords;
		}	
		return view('staticpages.brandperfume')->with($this->PageData);		
	}


	public function ContactUs2(Request $request)
	{
		// die('sd');
		Session::flash('success', config('message.ContactUs.Success'));
		return redirect('contact-us.html');
	}

	public function ContactUs(Request $request)
	{
		// echo "<pre>";
		// print_r($request['action']);die();
		if($request['action'] == 'submit') {
	        $validatedData = $request->validate([
								'name' => 'required',
								'email' => 'required|email',
								'subject' => 'required',
								// 'your_comment' => 'required',
								// 'order_number' => 'required'
					        ], [
					            'name.required' => config('message.ContactUs.YourName'),
					            'email.required' => config('message.ContactUs.YourEmail'),
					            'email.email' => config('message.ContactUs.YourEmail'),
					            'subject.required' => config('message.ContactUs.YourSubject'),
					            // 'your_comment.required' => config('message.ContactUs.YourComment'),
								// 'order_number.required' => config('message.ContactUs.OrderNumber')
					        ]);
							// print_r($validatedData);die();
			$comments  = stripslashes(nl2br(strtr($request['your_comment'], array('\r' => chr(13), '\n' => chr(10)))));
			$comments  = str_replace("<br />","",strip_tags($comments));
			$request['your_comment'] = $comments;
			$to_email 	= config('Settings.CONTACT_MAIL'); 
			// print_r($to_email );die();
			$to_email = 'customercare@7-n3mszh7wtz8driry3hnhv5e77agveinrndgguh1wjsraw63f2.75-8hageaq.cs210.case.sandbox.salesforce.com';
			$from_email = $request['your_email']; //CONTACT_MAIL; 
			$yourname = $request['your_name'];
			// print_r($from_email);die();
			
			$mail_subject = " Contact From Fragrance Depot Customer";
			$mail_subject =  $request['your_subject'];
			// $mail_body = $this->GetContactUsEmailTemplate($request);
			$mail_body = $request['your_comment'];
			$ordernumber = $request['order_number'];
			// echo $mail_subject; die();
			SendMail2($mail_subject,$mail_body,$to_email,$from_email,$yourname,$ordernumber);
			
			
			/** OMANISEND **/
			OmanisendRequest('6201253fb86552001e977a7b',$request);
			/** OMANISEND **/
			
			Session::flash('success', config('message.ContactUs.Success'));
			return redirect()->back();
			
		}
		$PData = StaticPages::where('name','=','contactus')->where('status','=','1')->get();
		if(count($PData) > 0) {
			$Content = $PData[0]->content;
			$this->PageData['PageTitle'] = $PData[0]->title;
			$this->PageData['PageContent'] = $PData[0]->content;
			$this->PageData['meta_title'] = $PData[0]->meta_title;
			$this->PageData['meta_description'] = $PData[0]->meta_description;
			$this->PageData['meta_keywords'] = $PData[0]->meta_keywords;
			
			$Content = stripslashes($Content);
			$Content = str_replace('{$Site_URL}',config('global.SITE_URL'),$Content);
			$Content = str_replace('src="images/','src="'.config('global.SITE_URL').'images/',$Content);
			$Content = str_replace('{$TOLL_FREE_NO}', config('Settings.TOLL_FREE_NO'), $Content);	
			$Content = str_replace('{$ADMIN_MAIL}', config('Settings.ADMIN_MAIL'), $Content);
			// $Content = str_replace('{$ADMIN_MAIL}', 'mohsin@marketingmindz.in', $Content);	
			// print_r(config('Settings.ADMIN_MAIL'));die();
			$Content = str_replace('{$CONTACT_PHONE_NO}', config('Settings.CONTACT_PHONE_NO'), $Content);

			$this->PageData['Content'] = $Content;
		} else {
			return redirect('/');
		}
		$GTMDATA = ['page' => 'contact_us', 'pagetype' => 'other'];
		$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);
		$this->PageData['JSFILES'] = ['contactus.js'];	
		return view('staticpages.contactus')->with($this->PageData);
	}

	public function GetContactUsEmailTemplate($request)
	{
		return $html = view('email_templates.contactUsEmailTemplate', compact('request'))->render();
	}

	public function GetBrands(Request $request)
	{
		if(isset($request->Char) && $request->Char != '')
		{
			$BrandList = BrandsList($request->Char);
			$BrandStr='<a href="#" class="act">'.$request->Char.'</a>';
			foreach($BrandList as $Brand)
			{
				$BrandStr.='<a href="'.$Brand['Link'].'">'.$Brand['Name'].'</a>';
			}
			return response()->json(array('Brands' => $BrandStr));
		}
	}

	public function TrackOrder(Request $request)
	{
		if(Auth::user()) {
			return redirect('/order-history.html');
		}

		if(isset($request->action) && $request->action == 'submit')
		{

	        $validatedData = $request->validate([
					            'bill_email' => 'required|email',
					            'orders_id' => 'required'
					        ], [
					            'bill_email.required' => config('message.TrackOrder.ValidEmail'),
					            'bill_email.email' => config('message.TrackOrder.ValidEmail'),
					            'orders_id.required' => config('message.TrackOrder.OrderID')
					        ]);

			$orders_id  = trim($request->orders_id);
			$optionname = trim($request->optionname);


			if(trim($orders_id) !='') {
				Session::put('orders_idnew', $orders_id);
			}

			if(trim($optionname) !='') {
				Session::put('optionnamenew', $optionname);
			}
			
			$track_flag=0;

			if($optionname=="check_order_zip")
			{
				$bill_email=trim($request->bill_email);
				if($orders_id!='')
				{
					$order_res = $this->GetOrderinfo($orders_id,$bill_email);
					if(count($order_res) >0)
					{
						$track_flag=1;
						Session::put('track_flag', 1);
					}
					else
					{
						return redirect()->back()
						->withInput()
						->withErrors([
							'incorrect' => 'Unfortunately, we could not locate an order with the information you provided. Please make sure you entered the correct billing email address and order number is correct.',
						]);
					}
				}
				else
				{
					return redirect()->back()
					->withInput()
					->withErrors([
						'incorrect' => 'Unfortunately, we could not locate an order with the information you provided. Please make sure you entered the correct billing email address and order number is correct.',
					]);
				}
			}

			if($track_flag==1 || (Session::has('track_flag') && Session::has('track_flag') == 1))
			{
				$OrderRs = Order::select('*')->addSelect(DB::raw("DATE_FORMAT(order_datetime, '%m-%d-%Y %H:%i') AS datetime,DATE_FORMAT(order_datetime, '%d-%m-%Y') AS newdatetime"))
									->where('orders_no', '=', $orders_id)
									->where('bill_email', '=', $bill_email)
									->first();
			}
			else
			{
				return redirect('/login.html');
			}

			$GC_Only = 0;

			$OrderDetailRs = OrderDetail::select('*')
								->where('orders_id', '=', $OrderRs->orders_id)
								->get();

			if(count($OrderDetailRs) > 0) {				
				for($p=0;$p<count($OrderDetailRs);$p++)
				{
					if($OrderDetailRs[$p]["sku"]==config('global.GIFT_CERTIFICATE_SKU'))
					{

						$GCRs = GiftCertificate::where('orders_detail_id', '=', $OrderDetailRs[$p]['orders_detail_id'])
												->where('customer_id', '=', Auth::user()->customer_id)
												->first();
						if($GCRs && $GCRs->count() > 0) {
							$OrderDetailRs[$p]['RecipientName']  	= $GCRs->recipient_name;
							$OrderDetailRs[$p]['RecipientEmail'] 	= $GCRs->recipient_email;
							$OrderDetailRs[$p]['Image']				= config('global.GC_IMAGE_URL');
						}
					}
					else
					{

						$prod_res = Products::whereRaw("LOWER(TRIM(sku))='".strtolower(trim($OrderDetailRs[$p]['sku']))."'")->select('image')->first();
						if($prod_res && $prod_res->count() > 0) {
							if(file_exists(config('global.PRD_THUMB_IMG_PATH').$prod_res->image) && !empty($prod_res->image))
								$thumb_image = config('global.PRD_THUMB_IMG_URL').$prod_res->image;
							 else
								$thumb_image = config('global.NO_IMAGE_THUMB');
						} else {
							$thumb_image = config('global.NO_IMAGE_THUMB');
						}
						$OrderDetailRs[$p]['Image'] = $thumb_image;

					}
				}

				if($OrderRs->is_only_gc==1) {
					$GC_Only = 1;
				}
				$this->PageData['OrderRs'] = $OrderRs;
				$this->PageData['OrderDetailRs'] = $OrderDetailRs;
				$this->PageData['GC_Only'] = $GC_Only;
				$view_fileType = "";
				if(Session::get('eusertype') == "Wholesaler" || (Session::get('eusertype')=="Wholesaler" && Session::get('is_dropshipper')=="Yes")) {
					$view_fileType  = 'pdf';
				} else {
					$view_fileType = 'print';
				}
				$this->PageData['view_fileType'] = $view_fileType;

				$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Order Detail';
				$this->PageData['CSSFILES'] = ['myaccount.css'];
				return view('myaccount.orderdetail')->with($this->PageData);
			}	

		}

		$PageType = 'NR';
		$MetaInfo = MetaInfo::where('type','=',$PageType)->get(); 
		if($MetaInfo->count() > 0 )
		{
			$this->PageData['meta_description'] = $MetaInfo[0]->meta_description;
			$this->PageData['meta_keywords'] = $MetaInfo[0]->meta_keywords;
		}
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE').' :: Track Order';
		$this->PageData['JSFILES'] = ['login.js', 'trackorder.js'];	
		return view('staticpages.trackorder')->with($this->PageData);
	}
	public function GetOrderinfo($orders_id,$bill_email)
	{
		$order_res = Order::select('orders_id')
							->where('orders_no', '=', $orders_id)
							->where('bill_email', '=', $bill_email)
							->get();
		return $order_res;
	}

	public function DontSeeRequest(Request $request)
	{

		if($request['action'] == 'submit') {

	        $validatedData = $request->validate([
								'custname' => 'required',
								'email'	=> 'required|email',
								// 'comments' => 'required|alpha_num',
								'comments' => 'required',
								'product_price' => 'required',
								'product_qty' => 'required',
								'delivery_date' => 'required'
					        ], [
					            'custname.required' => config('message.DontSeeRequest.CustName'),
					            'email.required' => config('message.DontSeeRequest.ValidEmail'),
					            'email.email' => config('message.DontSeeRequest.ValidEmail'),
					            'comments.required' => config('message.DontSeeRequest.Comments'),
					            // 'comments.alpha_num' => "Only alpha_num allowed.",
					            'product_price.required' => config('message.DontSeeRequest.ProductPrice'),
					            'product_qty.required' => config('message.DontSeeRequest.ProductQty'),
					            'delivery_date.required' => config('message.DontSeeRequest.DeliveryDate')
					        ]);

			$comments  = stripslashes(nl2br(strtr(trim($request['comments']), array('\r' => chr(13), '\n' => chr(10)))));
			$comments  = str_replace("<br />","",strip_tags($comments));

			$datetime = date('Y-m-d');
			$saveData['vname'] = $request['custname'];
			$saveData['vemail'] = $request['email'];
			$saveData['vproduct'] = $comments;
			$saveData['ddateadded'] = $datetime;
			$saveData['product_price'] = $request['product_price'];
			$saveData['product_qty'] = $request['product_qty'];
			$saveData['delivery_date'] = date("Y-m-d",strtotime($request['delivery_date']));

			$CustRequest = CustRequest::create($saveData);
			if($CustRequest) {
				$to_email 	= config('Settings.CONTACT_MAIL');
				$mail = GetMailTemplate("DONOT_SEE_REQUEST");

				$subject = stripslashes($mail[0]->subject);

				$message = stripslashes($mail[0]->mail_body);
				$message = str_replace('{$Site_URL}', config('global.Site_URL'), $message);
				$message = str_replace('{$CUST_NAME}', $request['custname'], $message);
				$message = str_replace('{$comments}', $comments, $message); 
				$message = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$message);
				$freeshippinginfo = '';
				if(config('Settings.FREESHIPPING_VALUE')!="")
				{
					$freeshippinginfo .= '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</span>';
				}
				$message = str_replace('{$freeshippinginfo}',$freeshippinginfo,$message);
				$Subject = $mail[0]->subject;
				$To 	= config('Settings.CONTACT_MAIL'); 
				$From = $request['email'];
				// echo $message; exit;
				SendMail($Subject,$message,$To,$From);
				/** OMANISEND **/
				//OmanisendRequest('6201175ab86552001e977a60',$request);
				/** OMANISEND **/
				Session::flash('success', config('message.DontSeeRequest.Success'));
			} else {
				Session::flash('error', config('message.DontSeeRequest.Error'));
			}
			return redirect()->back();
		}

		$PageType = 'NR';
		$MetaInfo = MetaInfo::where('type','=',$PageType)->get(); 
		if($MetaInfo->count() > 0 )
		{
			$this->PageData['meta_description'] = $MetaInfo[0]->meta_description;
			$this->PageData['meta_keywords'] = $MetaInfo[0]->meta_keywords;
		}
		$this->PageData['meta_title'] =  config('Settings.SITE_TITLE')." :: Don't See Request";
		$this->PageData['JSFILES'] = ['dontseerequest.js','moment.min.js'];	
		return view('staticpages.dontseerequest')->with($this->PageData);		
	}
	
	public function FAQ(Request $request)
	{   
		$PData = FAQS::where('status','=','1')->orderBy('rank')->get();
		
		if($PData->count() > 0)
		{
			foreach($PData as $key => $Data)
			{
				//dd($Data->question);
				$Data->question = stripslashes($Data->question);
				$Data->answer = stripslashes($Data->answer);
			}
		}
		
		$this->PageData['PageTitle'] = 'FAQS';
		$this->PageData['PData'] = $PData;

		return view('staticpages.faq')->with($this->PageData);		
	}

}
?>
