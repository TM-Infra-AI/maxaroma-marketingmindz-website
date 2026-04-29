<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Traits\CommonTrait;
use App\Http\Controllers\Traits\VendorTrait;
use App\Http\Controllers\Traits\CartTrait;
use Stripe\Stripe;
use Stripe\Exception\ApiErrorException;
use App\Models\MetaInfo;
use App\Models\Category;
use App\Models\Products;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\NewsLetter;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\RewardPoint;
use App\Models\ShippingMode;
use App\Models\ShippingRule;
use App\Models\ShippingRate;
use App\Models\ShippingHoliday;
use App\Models\TaxAreas;
use App\Models\TaxRates;
use App\Models\PaymentMethod;
use App\Models\GiftCertificate;
use App\Models\MailBanner;
use App\Models\AmazonOrder;
use App\Models\AmazonOrderDetails;
use App\Models\Dealofweek;
use App\Models\ReferFriend;
use App\Models\Shoppingcart;
use App\Models\PaypalIpnLog;
use App\Models\CustomerCreditLimitLogs;

use Illuminate\Support\Facades\DB;
use Session;
use Cookie;

class ShoppingcartController extends Controller
{
	use CommonTrait;
	use VendorTrait;
	use CartTrait;
	
	public function __construct()
	{
		config(['app.debug' => true]);
		config(['logging.default' => 'shoppingcart']);
		$this->PageData['CSSFILES'] = ['static.css'];	
		/*
        $PageType = 'NR';
		$MetaInfo = MetaInfo::where('type','=',$PageType)->get(); 
		if($MetaInfo->count() > 0 )
		{
			$this->PageData['meta_title'] = $MetaInfo[0]->meta_title;
			$this->PageData['meta_description'] = $MetaInfo[0]->meta_description;
			$this->PageData['meta_keywords'] = $MetaInfo[0]->meta_keywords;
		}
        */
        if(isset($MetaInfo))
        {
            $this->PageData['meta_title'] = $MetaInfo->meta_title;
            $this->PageData['meta_description'] = $MetaInfo->meta_description;
            $this->PageData['meta_keywords'] = $MetaInfo->meta_keywords;
        }
	}
	
	public function SetCart(Request $request)
	{	
		if($request->ajax()){
			if(isset($request->action))
			{
				if($request->action == 'insert')
				{
                    //config(['YotpoFreeGiftCoupon','']);
					$products_id=(int)$request->products_id;
					if(!isset($request->prodqty) && empty($request->prodqty))
						$quantity = 1;
					else
						$quantity = (int)$request->prodqty;
					return $this->AddToCart($products_id, $quantity ,$cookiee='No');
				}
                if($request->action == 'yotpo_free_gift_insert')
				{
					$products_id=(int)$request->products_id;
					if(!isset($request->prodqty) && empty($request->prodqty))
						$quantity = 1;
					else
						$quantity = (int)$request->prodqty;
                    config(['YotpoFreeGiftCoupon' => $request->yotpo_free_gift_coupon]);
					return $this->AddToCart($products_id, $quantity ,$cookiee='No','No','Yes');
				}
				if($request->action == 'reorder')
				{
					if(isset($request->prodDetails) && count($request->prodDetails) > 0)
					{
						$ProdDetails = $request->prodDetails;
						foreach($ProdDetails as $Prod)
						{
							$products_id=(int)$Prod['productID'];
							$quantity = (int)$Prod['prodqty'];
							$this->AddToCart($products_id, $quantity ,$cookiee='No');
						}
					}
				}
				if($request->action == 'remove')
				{
					return $this->RemoveFromCart($request->CartID);
				}
				if($request->action == 'update')
				{
					$products_id=(int)$request->products_id;
					$giftwrap = 'No';
					if(isset($request->giftwrap) && $request->giftwrap == 'Yes')
						$giftwrap = $request->giftwrap;
					if(!isset($request->prodqty) && empty($request->prodqty))
						$quantity = 1;
					else
						$quantity = (int)$request->prodqty;
					return $this->UpdateCart($products_id,$quantity,$giftwrap);
				}
				if($request->action == 'apply_coupon')
				{
					$CouponNumber = $request->coupon_number;
					$CustomerID = (Auth::user()?Session::get('sess_icustomerid'):null);
					$result = $this->ApplyCouponDiscount($CouponNumber,$CustomerID);
					$this->SetupCart();
					return response()->json(['error' => $result['error'],'message' => $result['message']]);
				}
				if($request->action == 'apply_credit_limit')
				{
					$res = $this->ApplyCreditDiscount(1);
					$Msg = "";
					if(Auth::user() && Auth::user()->eusertype == 'Wholesaler')
						$Msg = 'Applied credit limit successfully.';
					else	
						$Msg = 'Applied store credit successfully.';
					$this->SetupCart();
					return response()->json(['message' => $Msg,'NetTotal' => $this->GetNetTotal()]);
				}
				if($request->action == 'remove_credit_limit')
				{
					Session::put('ShoppingCart.credit_limit_discount',0);
					Session::put('ShoppingCart.customer_remaining_credit_amount',0);
					$Msg = "";
					if(Auth::user() && Auth::user()->eusertype == 'Wholesaler')
						$Msg = 'Applied Credit Limit removed successfully.';
					else	
						$Msg = 'Applied Store Credit removed successfully.';
					$this->SetupCart();
					return response()->json(['message' => $Msg,'NetTotal' => $this->GetNetTotal()]);
				}
				if($request->action == 'apply_gift_coupon')
				{
					if(isset($request->giftcard) && $request->giftcard != '')
					{
						$error = 0;
						if($this->ApplyGiftCoupons($request->giftcard)){
							$Msg = 'E-gift card code is successfully applied.';
							$error = 0;
						}else{	
							$Msg = 'E-gift card code is invalid or does not exists!';
							$error = 1;
						}
						$this->SetupCart();
						return response()->json(['error' => $error,'message' => $Msg]);
					}	
				}
				if($request->action == 'remove_gift_coupon')
				{
					Session::put('ShoppingCart.GiftCoupon.Code','');
					Session::put('ShoppingCart.GiftCoupon.Value','');
					Session::put('ShoppingCart.GiftCoupon.Applicable_Value','');
					$Msg = "Applied E-gift card code removed successfully.";
					$this->SetupCart();
					return response()->json(['message' => $Msg]);	
				}
				if($request->action == 'remove_coupon')
				{
					Session::put('ShoppingCart.PromoCoupon.FirstCouponDiscount',0);
					Session::put('ShoppingCart.PromoCoupon.SecondCouponDiscount',0);
					Session::put('ShoppingCart.PromoCoupon.CouponCode','');
					Session::put('ShoppingCart.PromoCoupon.CouponDiscount',0);
					Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag','');
					Session::put('ShoppingCart.PromoCoupon.FreeShipping','');
					Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID','');
					$Msg = "Applied coupon code removed successfully.";
					$this->SetupCart();
					return response()->json(['message' => $Msg]);	
				}
				if($request->action == 'clear_bag')
				{
					Session::forget('ShoppingCart');
                    if(Auth::user() && Auth::user()->omnisend_accountid != '')
                    {
                        /** OMANISEND **/ 
                        OmanisendRequest('removeCart',['omnisend_accountid' => Auth::user()->omnisend_accountid]);
                        /** OMANISEND **/
                    }
					return true;
				}
				if($request->action == 'FreeGift')
				{
					$freeproductsid = $request->freeproductsid;
					$products_id 	= (int)$request->products_id;
					$this->FreeGiftInsertProductValue($products_id,$freeproductsid);
					$Msg = "Free Gift Product Added Successfully";
					Session::flash('CartSuccess',$Msg);
					$this->SetupCart();
					return response()->json(['message' => $Msg]);
				}
				if($request->action == 'apply_free_gift')
				{
					if(isset($request->GiftValue) && $request->GiftValue != '' )
					{
						if(strtolower(trim($request->GiftMessage))=="*gift message")
						{
							$request->GiftMessage = '';
						}	
						return $this->ApplyFreeGift($request->GiftValue,$request->GiftFrom,$request->GiftTo,$request->GiftMessage);
					}
					else
					{
						return response()->json(['success' => '0','message' => 'Error']);
					}
				}
			}
		}	
	}
	
	public function RemoveFromCart($CartID)
	{
		$ShoppingCart = Session::get('ShoppingCart.Cart');
		if($CartID != '' && $ShoppingCart != null && count($ShoppingCart) > 0)
		{
            $IsYotpoFreeProduct = 'No';
			if($CartID >= 0)
            {
                if($ShoppingCart[$CartID]['IsYotpoFreeProduct'] == 'Yes')
                {
                    $IsYotpoFreeProduct = 'Yes';
                    Session::forget('ShoppingCart.YotpoFreeGiftCoupon');
                }
				unset($ShoppingCart[$CartID]);
            }
            
			$ShoppingCart = array_values($ShoppingCart);
			
            if(count($ShoppingCart) == 1 && $ShoppingCart[0]['IsYotpoFreeProduct'] == 'Yes')
            {
                $ShoppingCart = [];
            }
            Session::put('ShoppingCart.Cart',$ShoppingCart);
			$this->CalculateSubTotal();
			$this->SetupCart();
			$Msg = "Item removed successfully.";
            /** OMANISEND **/ 
            OmanisendRequest('setCart',['CartData' => Session::get('ShoppingCart')]);
            /** OMANISEND **/
			return response()->json(['message' => $Msg,'IsYotpoFreeProduct' => $IsYotpoFreeProduct]);
		}
	}
	
	public function ShoppingcartPage(Request $Request)
	{
		$allCartItems = '';
		$this->PageData['CSSFILES'] = ['slick.css','shoppingcart.css'];	
		$this->PageData['JSFILES'] = ['slick.js','afterpay.js','shoppingcart_page.js'];		
		$this->PageData['meta_title'] = "Shopping Bag :: ".config('Settings.SITE_TITLE');
		//Log::channel('customer')->info('You are on Shopping Cart Page'); 
		//Session::forget('ShoppingCart');
		
		if(config('global.SHOPP_STATUS') == 'Close')
		{
			Session::forget('ShoppingCart');
			return redirect('/');	
		}
		$this->SetShippingInsuranceCharge('remove');		
		
		$this->SetupCart();
		
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			$CartInfo = Session::get('ShoppingCart.Cart');
			$SKUINCART = [];

			foreach($CartInfo as $Cart){
				$allCartItems .= $Cart['SKU'].",";
				$SKUINCART[]=$Cart['SKU'];
			}
			$allCartItems = rtrim($allCartItems,",");

		/* 	foreach($CartInfo as $Cart)
				$SKUINCART[]=$Cart['SKU']; */
			$Filters['NotProductSKUs']= $SKUINCART;
			//$RecentViewProducts = $this->GetProducts('ShoppingCart','',12,$Filters);
			$RecentViewProducts['Products'] = [];
			$this->PageData['RecentViewProducts'] = $RecentViewProducts['Products'];	
			$this->PageData['RecentViewAttr'] = [
				'Title' => 'We think you’ll also love',
				'Slider' => 'products-slider',
				'SeeMore' => '',
			];
			if(Auth::user() && Session::get('sess_useremail') == 'gequaldev@gmail.com')
			{
				//dd(YotpoRequest('create_customer'));
				//dd(YotpoRequest('create_order'));
				//$YotpoUserDetails = YotpoRequest('customer_detail');
				if(isset($YotpoUserDetails->points_balance))
				{
					//dd($YotpoUserDetails->points_balance);
				}
			}
		}
		if(!Session::has('Afterpay.Min_AP_AMT') && !Session::has('Afterpay.Max_AP_AMT'))
		{
			$this->AfterpayMinMax();
		}
		$this->SetAmazonConfig('shoppingcart');
		
		$GTMDATA = ['page' => 'shoppingcart', 'pagetype' => 'cart'];
		$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);
		$this->PageData['body_class'] = 'cart-body';
		$this->PageData['allCartItems'] = $allCartItems;
		return view('shoppingcart.cart')->with($this->PageData);		
	}
	
	public function SetupCart()
	{
		if(Session::get('sess_useremail') == 'gequaldev@gmail.com')
		{
			config(['app.debug' => true]);
		}
		if(!Session::has('eusertype') || (Session::has('eusertype') && strtolower(trim(Session::has('eusertype')) != 'wholesaler')))
		{	
			$this->ApplyAutoDiscount();
			$this->ApplyQuantityDiscount();
		}
        if(Session::has('Niche_Fragrances_Membership') && Session::get('Niche_Fragrances_Membership') == 'Yes')
		{
			$cname = trim(config('Settings.NICHEFRAGRANCESCODE'));
			$niche_res = Coupon::select('coupon_number')->where('coupon_number','=',$cname)->limit(1)->get();
			if($niche_res && $niche_res->count() > 0)
			{
				$this->ApplyCouponDiscount($cname,Session::get('sess_icustomerid'));
			}
		}
		$this->ApplyDogoDiscount();
		$this->ApplyAutoRewardDiscount();
		
		if(Session::has('ShoppingCart.credit_limit_discount') && Session::get('ShoppingCart.credit_limit_discount') > 0)
		{
			$this->ApplyCreditDiscount(1);
		}
		
		$CartAttr = $this->SetCartAttributes();
		$this->PageData['CartAttr'] = $CartAttr;
		$this->ApplyGiftWrapping();
		
		if($CartAttr['onlyGCPurchased'] == 1)
		{
			Session::forget('ShoppingCart.Shipping');
			Session::forget('ShoppingCart.Tax');
			Session::forget('ShoppingCart.GiftWrapping');
			Session::forget('ShoppingCart.ShippingSignature');
			Session::forget('shipping_insurance_charge');
			Session::forget('ShoppingCart.ShippingSignature');
		}
		
		$AllCharges = $this->GetAllCharges();
		$this->PageData['AllCharges'] = $AllCharges['Charges'];
		$AllDiscounts = $this->GetAllDiscounts();
		$this->PageData['AllDiscounts'] = $AllDiscounts['Discounts'];
		$CreditDiscount = (isset($AllDiscounts['Discounts']['CreditLimitDiscount']['discount']) ? $AllDiscounts['Discounts']['CreditLimitDiscount']['discount']:0); 		
		$CreditLimit = (isset($CartAttr["CreditLimit"])?$CartAttr["CreditLimit"]:0);
		$this->PageData['CreditDiscount'] = $CreditDiscount;
		if(Auth::user() && ($CreditLimit <= 0 || config('Settings.WHOLESALE_CREDIT_LIMIT') != 'Yes' || Session::get("etype") != "M" || Auth::user()->is_dropshipper == "Yes") && $CreditDiscount > 0)
		{
			Session::put('ShoppingCart.credit_limit_discount',0);
			Session::put('ShoppingCart.customer_remaining_credit_amount',0);
		}
		
		if(Session::has('ShoppingCart.GiftCoupon.Code') && Session::get('ShoppingCart.GiftCoupon.Code') !='' )
		{
			Session::put('ShoppingCart.GiftCoupon.Value', 0.0);
			Session::put('ShoppingCart.GiftCoupon.Applicable_Value', 0.0);
			$this->ApplyGiftCoupons(Session::get('ShoppingCart.GiftCoupon.Code'));
		}
		
		$AllDiscounts = $this->GetAllDiscounts();
		$TotalValue = NumberFormat(Session::get('ShoppingCart.SubTotal')) - $AllDiscounts['TotalDiscount'];
		
		$Gift_Free_In_Cart = $this->CheckFreeGiftInCart($TotalValue);
		
		

		
		
		
		if($Gift_Free_In_Cart == 'No')
		{
			$this->SetFreegift($Gift_Free_In_Cart);
			/*
			if(strtolower(trim(Session::get('eusertype'))) !="wholesaler" && trim(Session::get('is_dropshipper')) != "Yes" && $Gift_Free_In_Cart == "No")
			{
				$Free_Gift_Res = $this->GetFreeCouponPopup($this->GetNetTotal());
				
				if(count($Free_Gift_Res) == 1)
				{
					$products_id = $Free_Gift_Res[0]['products_id'];
					$freeproductsid = $Free_Gift_Res[0]['free_gift_products_id'];
					$this->FreeGiftInsertProductValue($products_id,$freeproductsid);
					$Msg = "Free Gift Product Added Successfully";
					Session::flash('CartSuccess',$Msg);	
				}
			}	*/			
		}
		
		$this->PageData['NetTotal'] = $this->GetNetTotal();
		$this->StoreShopCartInCookie();
	}
	
	public function GetCartHTML(Request $request)
	{
		if($request->ajax())
		{
			$ShoppingCart = [];
			$TotalItemInCart = 0;
			if(Session::has('ShoppingCart'))
			{
				$ShoppingCart = Session::get('ShoppingCart');
				if(isset($ShoppingCart['Cart']) && count($ShoppingCart['Cart']) > 0)
					$TotalItemInCart = $ShoppingCart['TotalItemInCart'];
			}	
			
			$CartAttr = $this->SetCartAttributes();
			$ShoppingCart['IsPaypalExpressCheckout'] = $CartAttr['IsPaypalExpressCheckout'];
			$ShoppingCart['Amazon_pay_Checkout'] = $CartAttr['Amazon_pay_Checkout'];
			$ShoppingCart['Afterpay_Checkout'] = $CartAttr['Afterpay_Checkout'];
			$this->PageData['CartDetails'] = $ShoppingCart;
			$this->SetAmazonConfig();
			$this->StoreShopCartInCookie();
			$MerchantID = config('MERCHANT_ID');
			$CallBackURL = config('CALLBACK_CHECKOUT_URL');
			$CartHTML = view('layouts.sidecart_ajax')->with($this->PageData)->render();
			return response()->json(array('ShoppingCart' => $CartHTML,'TotalItemInCart' => $TotalItemInCart, 'MerchantID' => $MerchantID, 'CallBackURL' => $CallBackURL));
		}
	}
	
	public function UpdateCart($products_id,$prodqty,$giftwrap='No')
	{
		$ProductChkStock = $this->ProductCheckInStock($products_id, $prodqty,"update");	
		$CartErrors = [];
		if($ProductChkStock == '1111')
			$CartErrors[] = config('message.Cart.ProductNotAvailable');
		if($ProductChkStock == '2222')
			$CartErrors[] = config('message.Cart.QuantityNotAvailable');
		if(count($CartErrors) > 0)
		{
			Session::flash('CartErrors', $CartErrors);
			return response()->json(array('Update' => 0,'CartErrors' => $CartErrors));
		}
		$ProductChkFlg = $this->ProductCheckInCart($products_id, $prodqty, 'update','No',$giftwrap);
		$this->CalculateSubTotal();
		$this->SetupCart();
        /** OMANISEND **/ 
        OmanisendRequest('setCart',['CartData' => Session::get('ShoppingCart')]);
        /** OMANISEND **/
	}

	public function GetCartPartial(Request $request)
	{

		$allCartItems = '';
		if($request->ajax())
		{
			$ShoppingCart = [];
			$TotalItemInCart = 0;
			if(Session::has('ShoppingCart'))
			{
				$this->SetupCart();
				$ShoppingCart = Session::get('ShoppingCart');
				if(isset($ShoppingCart['Cart']) && count($ShoppingCart['Cart']) > 0)
					$TotalItemInCart = $ShoppingCart['TotalItemInCart'];
			}	 
			
			foreach($ShoppingCart['Cart'] as $Cart){
				$allCartItems .= $Cart['SKU'].",";
				$SKUINCART[]=$Cart['SKU'];
			}
			$allCartItems = rtrim($allCartItems,",");
			$this->PageData['allCartItems'] = $allCartItems;

			$this->PageData['CartDetails'] = $ShoppingCart;
			$NetTotal = NumberFormat($this->GetNetTotal());
			if($TotalItemInCart > 0)
			{
				$CartHTML = view('shoppingcart.cart_table')->with($this->PageData)->render();
				$SubtotalBoxHTML = view('shoppingcart.subtotalbox')->with($this->PageData)->render();
				$CheckoutBoxHTML = view('shoppingcart.checkoutbox')->with($this->PageData)->render();
				$CreditCouponBoxesHtml = view('shoppingcart.creditcouponboxes')->with($this->PageData)->render();
				return response()->json(array('Cart' => $CartHTML,'SubtotalBoxHTML' => $SubtotalBoxHTML, 'CheckoutBoxHTML' => $CheckoutBoxHTML,'CreditCouponBoxesHtml' => $CreditCouponBoxesHtml, 'TotalItemInCart' => $TotalItemInCart, 'Total' => $NetTotal));
			} else {
				$EmptyCartHTML = view('shoppingcart.empty')->with($this->PageData)->render();
				return response()->json(array('EmptyCartHTML' => $EmptyCartHTML,'TotalItemInCart' => $TotalItemInCart));
			}
		}
	}
	public function GetFreeGiftProducts(Request $request)
	{ 
		$AllDiscounts = $this->GetAllDiscounts();
		$TotalValue = NumberFormat(Session::get('ShoppingCart.SubTotal')) - $AllDiscounts['TotalDiscount'];
		if(config('Settings.CHECKOUT_SHOIPPINGCART') == "Yes" && $TotalValue > 0){
			$Gift_Free_In_Cart = $this->CheckFreeGiftInCart($TotalValue);
			if(strtolower(trim(Session::get('eusertype'))) !="wholesaler" && trim(Session::get('is_dropshipper')) != "Yes" && $Gift_Free_In_Cart == "No")
			{
				$Free_Gift_Res = $this->GetFreeCouponPopup($TotalValue);
				if(count($Free_Gift_Res) > 1)
				{
					$this->PageData['Free_Gift_Res'] = $Free_Gift_Res;
					return view('popup.freegift-popup')->with($this->PageData)->render();
				}
			}
		}
	}
	
	public function CheckoutPage(Request $request)
	{
		
		$this->PageData['CSSFILES'] = ['shoppingcart.css','checkout.css'];	
		$this->PageData['JSFILES'] = ['afterpay.js','billing.js','login.js','login_validate.js'];		
			
		if(!Session::has('ShoppingCart.Cart') || count(Session::get('ShoppingCart.Cart')) == 0)
			return redirect('/shoppingcart');
		
		if($this->Is_WholeSaler_Allow() == false)
		{
			return redirect('/shoppingcart');
		}
		
		$this->PageData['meta_title'] = "Billing and Shipping Information :: ".config('Settings.SITE_TITLE');
		$this->PageData['Countries'] = GetCountries();
		$this->PageData['States'] = GetStates();
		
		$this->PageData['SelPayMethod'] = (isset($request->method)?$request->method:'');
		
		$Billing = [];
		$Billing['first_name'] = '';
		$Billing['last_name']= '';
		$Billing['company']= '';
		$Billing['address1']= '';
		$Billing['address2']= '';
		$Billing['city']= '';
		$Billing['zip']= '';
		$Billing['state']= '';
		$Billing['country']= 'US';
		$Billing['phone']= '';
		$Billing['email']= '';
		$Billing['confirm_email']= '';
		$Shipping=[];
		$Shipping['first_name'] 		= '';
		$Shipping['last_name']  		= '';
		$Shipping['company']    		= '';
		$Shipping['address1']   		= '';
		$Shipping['address2']   		= '';
		$Shipping['city'] 	   		= '';
		$Shipping['zip'] 	   		= '';
		$Shipping['state'] 	   		= '';
		$Shipping['country']    	= '';
		$Shipping['phone'] 	   		= '';
		$Shipping['email'] 	   		= '';
		$Shipping['confirm_email'] 	= '';
		if(Auth::user())
		{
			if(Session::has('ShoppingCart.BillingAddress') && count(Session::get('ShoppingCart.BillingAddress')) > 0 )
			{
				$Billing = Session::get('ShoppingCart.BillingAddress');
			} else {
				$Billing['first_name'] 		= Auth::user()->first_name;
				$Billing['last_name']  		= Auth::user()->last_name;
				$Billing['company']    		= Auth::user()->company_name;
				$Billing['address1']   		= Auth::user()->address1;
				$Billing['address2']   		= Auth::user()->address2;
				$Billing['city'] 	   		= Auth::user()->city;
				$Billing['zip'] 	   		= Auth::user()->zip;
				$Billing['state'] 	   		= Auth::user()->state;
				$Billing['country']    		= Auth::user()->country;
				$Billing['phone'] 	   		= Auth::user()->phone;
				$Billing['email'] 	   		= Auth::user()->email;
				$Billing['confirm_email'] 	= '';
			}
			
			if(Session::has('ShoppingCart.ShippingAddress') && count(Session::get('ShoppingCart.ShippingAddress')) > 0 )
			{
				$Shipping = Session::get('ShoppingCart.ShippingAddress');
			} else {
				$Shipping['first_name'] 		= '';
				$Shipping['last_name']  		= '';
				$Shipping['company']    		= '';
				$Shipping['address1']   		= '';
				$Shipping['address2']   		= '';
				$Shipping['city'] 	   		= '';
				$Shipping['zip'] 	   		= '';
				$Shipping['state'] 	   		= '';
				$Shipping['country']    	= '';
				$Shipping['phone'] 	   		= '';
				$Shipping['email'] 	   		= '';
				$Shipping['confirm_email'] 	= '';
			}
			
			$this->PageData['IsBillingAsShipping'] = 'No';
			if(Session::has('ShoppingCart.BillingAsShipping'))
				$this->PageData['IsBillingAsShipping'] = Session::has('ShoppingCart.BillingAsShipping');
		}
		
		if(isset($request->method) && $request->method == 'paypal')
		{
			$Billing = Session::get('ShoppingCart.BillingAddress');
			$Shipping = Session::get('ShoppingCart.ShippingAddress');
			$this->PageData['IsBillingAsShipping'] = 'No';
			if(Session::has('ShoppingCart.BillingAsShipping'))
				$this->PageData['IsBillingAsShipping'] = Session::has('ShoppingCart.BillingAsShipping');
		}
		
		$this->PageData['Billing'] = $Billing;
		$this->PageData['IsBillingAsShipping'] = 'No';
		$this->PageData['Shipping'] = $Shipping;
		$this->SetupCart();
		$this->PageData['IsGuestCheckout'] = '0';
		if(!Auth::user() && config('global.IS_GUEST_CHECKOUT') == 'Yes')
			$this->PageData['IsGuestCheckout'] = '1'; 
		
		if(Session::has('ShoppingCart') && count(Session::get('ShoppingCart.Cart')) > 0 && !Session::has('Afterpay.Min_AP_AMT') && !Session::has('Afterpay.Max_AP_AMT'))
		{
			$this->AfterpayMinMax();
		} 
		$this->SetShippingInsuranceCharge('remove');
		Session::forget('ShoppingCart.ShippingSignature');
		$this->SetupCart();
		$this->SetAmazonConfig('billing');
		$this->PageData['PageMethod'] = (isset($request->method)?$request->method:'billing');
		$GTMDATA = ['page' => 'billing', 'pagetype' => 'cart'];
		$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);
		
		return view('checkout.index')->with($this->PageData);
	}
	
	public function ShippingMethods(Request $request)
	{
		$this->PageData = [];
		if(isset($request->action) && $request->action == 'setshippinginsurance')
		{
			/*if($request->ShippingInsuranceCharge == '')
				Session::forget('shipping_insurance_charge');
			else
				Session::put('shipping_insurance_charge',NumberFormat($request->ShippingInsuranceCharge));
			*/
			/*if($request->shipping_signature == 'Yes')
				Session::put('ShoppingCart.ShippingSignature',config('Settings.SHIPPING_SIGNATURE'));
			else
				Session::forget('ShoppingCart.ShippingSignature');*/
			
			if($request->shipping_signature != '' && $request->shipping_signature != '0')
			{
				Session::put('ShoppingCart.ShippingSignature',$request->shipping_signature);
			} else {
				Session::forget('ShoppingCart.ShippingSignature');
			}			
			$this->SetShippingInsuranceCharge($request->subaction);
			$this->SetupCart();
			return view('checkout.subtotalbox')->with($this->PageData)->render();
		}
		if(isset($request->action) && $request->action == 'shippinginfo')
		{
			$this->PageData['PageFrom'] = $request->PageFrom;
			$this->PageData['VendorPopup'] = '';
			if($request->OnlyHead == '0')
			{
				$IsMaxaromaTwoDelivery	= $request["IsMaxaromaTwoDelivery"];
				$ISMaxTwoItem = $request['ISMaxTwoItem'];	
				$IsVenderItem = $request["IsVenderItem"];
				$IsCosmo 	 = $request["IsCosmo"];
				$IsNandansons = $request["IsNandansons"];
				$IsPerfumePW  = $request["IsPerfumePW"];
				$IsPCA  	= $request["IsPCA"];
				$onlyGCPurchased = $request['onlyGCPurchased'];	
				$ship_country = '';
				$ship_zip 	  = '';
				$ship_state	  = '';
				$ship_address1 = '';
				$ship_address2 = '';
				
				if(Session::has('ShoppingCart.BillingAddress'))
				{
					if(Session::has('ShoppingCart.BillingAsShipping') && Session::get('ShoppingCart.BillingAsShipping') == "No")
					{
						$Shipping = Session::get('ShoppingCart.ShippingAddress');
						$ship_country = $Shipping["country"];
						$ship_zip 	  = $Shipping["zip"];
						$ship_state	  = $Shipping["state"];
						$ship_address1 = trim($Shipping["address1"]);
						$ship_address2 = trim($Shipping["address2"]);
					}
					else
					{
						$Billing = Session::get('ShoppingCart.BillingAddress');
						$ship_country = $Billing["country"];
						$ship_zip 	  = $Billing["zip"];
						$ship_state	  = $Billing["state"];
						$ship_address1 = trim($Billing["address1"]);
						$ship_address2 = trim($Billing["address2"]);
					}
				}
				if(isset($request->PageFrom) && $request->PageFrom =='AmazonBilling')
				{
					$ship_state = Session::get('AmazonShipState');
					$ship_zip = Session::get('AmazonShipZip');
					$ship_country = Session::get('AmazonShipCountry');
				}
				if(isset($request->subaction) && $request->subaction =='stripecart')
				{
					$ShopCartItems = Session::get('ShoppingCart.Cart');
					$TempCart = [];
					foreach($ShopCartItems as $ShopItem)
					{
						if((isset($ShopItem['IsCosmo']) && $ShopItem['IsCosmo']=="Yes") || (isset($ShopItem['IsNandansons']) && $ShopItem['IsNandansons']=='Yes') || (isset($ShopItem['IsPerfumePW']) && $ShopItem['IsPerfumePW']=='Yes') || (isset($ShopItem['IsPCA']) && $ShopItem['IsPCA']=='Yes') && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
						{
							$IsVenderItem = "Yes";
						}
						if((isset($ShopItem['IsCosmo']) && $ShopItem['IsCosmo']=="Yes") && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
						{
							$IsCosmo = "Yes";
						}
						if((isset($ShopItem['IsNandansons']) && $ShopItem['IsNandansons']=="Yes") && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
						{
							$IsNandansons = "Yes";
						}
						if((isset($ShopItem['IsPerfumePW']) && $ShopItem['IsPerfumePW']=="Yes") && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
						{
							$IsPerfumePW = "Yes";
						}
						if((isset($ShopItem['IsPCA']) && $ShopItem['IsPCA']=="Yes") && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
						{
							$IsPCA = "Yes";
						}
						
						if(isset($ShopItem['IsMaxaromaTwoDelivery']) && $ShopItem['IsMaxaromaTwoDelivery']=="Yes" && $ShopItem['IsMaxaromaTwoDelivery']!='')
						{
							$IsMaxaromaTwoDelivery = "Yes";
						}else{
							$IsMaxaromaTwoDelivery = "No";
						}
						if($ShopItem['SKU']!= config('global.GIFT_CERTIFICATE_SKU') && $ShopItem['SKU']!= config('global.GIFT_CERTIFICATE_SKU1'))
							$onlyGCPurchased = 0;
					}
					
					if($request->zip != ""){
						$ship_zip = $request->zip;
						$ship_state = $request->state;
						$ship_country = $request->country;
						$ship_address1 = trim($request->address1);
						$ship_address2 = trim($request->address2);
					} else {
						if(Auth::user())
						{
							$ship_zip = Auth::user()->zip;
							$ship_state = Auth::user()->state;
							$ship_country = Auth::user()->country;
							$ship_address1 = trim(Auth::user()->address1);
							$ship_address2 = trim(Auth::user()->address2);
						} else {
							$ship_zip = '10080';
							$ship_state = 'NY';
							$ship_country = 'US';
						}
					}
				}
				$ShippingModeRS = ShippingMode::where('status','=','1')->orderBy('display_position','asc')->get();
				$Sess_ShippingInfo = "";
				if(Session::has('ShoppingCart.Shipping'))
					$Sess_ShippingInfo = Session::get('ShoppingCart.Shipping');
			
				$shipping_mode_idMainArr = $this->CheckAvailableShippingMethod(29, $ship_country,$ship_state,$ship_zip);
				
				$shipping_mode_idMainArr = explode("###",$shipping_mode_idMainArr);
				$shipping_mode_id =(int) $shipping_mode_idMainArr[0];
				$istwoday = "No";
				if($shipping_mode_id >0 )
				{
					$istwoday = "Yes";
				}
				$AddressCheck = "No";
				if($ship_address1!='')
				{
					if(preg_match("/pobox/i",strtolower($ship_address1)) || preg_match("/po-box/i",strtolower($ship_address1)) || preg_match("/po box/i",strtolower($ship_address1)) || preg_match("/p.o.box/i",strtolower($ship_address1)) || preg_match("/po.box/i",strtolower($ship_address1)) || preg_match("/p.o.b.o.x/i",strtolower($ship_address1)))
					{
						$istwoday = "No";
						$AddressCheck = "Yes";
					}
				}
				if($ship_address2!='')
				{
					if(preg_match("/pobox/i",strtolower($ship_address2)) || preg_match("/po-box/i",strtolower($ship_address2)) || preg_match("/po box/i",strtolower($ship_address2)) || preg_match("/p.o.box/i",strtolower($ship_address2)) || preg_match("/po.box/i",strtolower($ship_address2)) || preg_match("/p.o.b.o.x/i",strtolower($ship_address2)))
					{
						$istwoday = "No";
						$AddressCheck = "Yes";
					}
				}
				
				$count = 0; // This var used for count availabe method
				$Checkcounter = 0;
				$MsgVal=[];
				$ChargeInfo = [];
				$SelShipMethod = 0;
				$shipping_mode_idArr="";
				$Max2Days = 0;
				
				for($p=0; $p<count($ShippingModeRS); $p++ )
				{
					if($AddressCheck=="Yes" && $ShippingModeRS[$p]['shipping_mode_id']==29)
					{
						continue;
					}
					if(($istwoday=="Yes" && $IsMaxaromaTwoDelivery =='Yes' && ($ShippingModeRS[$p]['shipping_mode_id']==22 || $ShippingModeRS[$p]['shipping_mode_id']==34 || $ShippingModeRS[$p]['shipping_mode_id']==29)))
					{
						$shipping_mode_idArr = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]['shipping_mode_id'], $ship_country,$ship_state,$ship_zip);
					}
					else if($istwoday=="No" && $IsMaxaromaTwoDelivery =='Yes' )
					{
						$shipping_mode_idArr = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]['shipping_mode_id'], $ship_country,$ship_state,$ship_zip);
					}
					else if($istwoday=="Yes" && $IsMaxaromaTwoDelivery =='No' &&  $ShippingModeRS[$p]['shipping_mode_id']!=29)
					{
						$shipping_mode_idArr = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]['shipping_mode_id'], $ship_country,$ship_state,$ship_zip);
					}
					else if($istwoday=="No" && $IsMaxaromaTwoDelivery =='No' )
					{
						$shipping_mode_idArr = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]['shipping_mode_id'], $ship_country,$ship_state,$ship_zip);
					}
					
					if(strtolower(Session::get('eusertype'))=="wholesaler")
					{
						$shipping_mode_idArr = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]['shipping_mode_id'], $ship_country,$ship_state,$ship_zip);
					}
					$normalWeight = 0;
					$lightWeight = 0;
					$heavyWeight = 0;
					$shipping_mode_id = 0;
					
					if($shipping_mode_idArr != '' && !is_array($shipping_mode_idArr))
					{
						//if(!is_array($shipping_mode_idArr))
						$shipping_mode_idArr = explode("###",$shipping_mode_idArr);
						$shipping_mode_id =(int) $shipping_mode_idArr[0];
						$normalWeight = $shipping_mode_idArr[1];
						$lightWeight = $shipping_mode_idArr[2];
						$heavyWeight = $shipping_mode_idArr[3];
					}
					if($AddressCheck == 'No' && $IsMaxaromaTwoDelivery=="No" && $istwoday=="No" && strtolower(Session::get('eusertype')) != "wholesaler")
					{
						$MsgVal[] = 'Your order is not eligible for Max2days shipping as one of the item is not eligible.<br/>Since you are shipping to a different address this order is not eligible for Max2days shipping option.';
						//return response()->json(array('error' => 1,'Message' => $MsgVal));
					}
					else if($AddressCheck == 'No' && $istwoday=="No" && strtolower(Session::get('eusertype')) != "wholesaler")
					{
						$MsgVal[] = 'Since you are shipping to a different address this order is not eligible for Max2days shipping option.';
						//return response()->json(array('error' => 1,'Message' => $MsgVal));
					}
					else if($AddressCheck == 'No' && $IsMaxaromaTwoDelivery=="No" && $ISMaxTwoItem == 'Yes' && strtolower(Session::get('eusertype')) != "wholesaler")
					{
						$MsgVal[] = 'Your order is not eligible for Max2days shipping as one of the item is not eligible.';
						//return response()->json(array('error' => 1,'Message' => $MsgVal));
					}
					if($AddressCheck == 'Yes' && $shipping_mode_id == 22)
					{
						$MsgVal[] = 'Your order is not eligible for Max2days shipping because our carrier does not ship using this service to PO BOX Addresses';
						//continue;
					}
					if(is_int($shipping_mode_id) == true && $shipping_mode_id > 0)
					{
						$tempChargeStr = $this->CalculateAvailableShippingCharge($ship_zip,$ship_state,$ship_country,$shipping_mode_id);
						$tempChargeArr = explode("###",$tempChargeStr);
						
						$tempCharge = $tempChargeArr[0];
						$days		= $tempChargeArr[1];
						
						$IsCosmo 	 	= $request["IsCosmo"];
						$IsNandansons	= $request["IsNandansons"];
						$IsPerfumePW  	= $request["IsPerfumePW"];
						$IsPCA  		= $request["IsPCA"];
						$VendorDays = 0;
						if(($IsVenderItem=="Yes" && $IsPerfumePW=="Yes"))
						{
							$days		= $tempChargeArr[1];
							$days = $days + 3;
							$VendorDays = 3;
						}
						else if(($IsVenderItem=="Yes" && $IsCosmo=="Yes") || ($IsVenderItem=="Yes" && $IsPCA=="Yes") || ($IsVenderItem=="Yes" && $IsNandansons=="Yes"))
						{
							$days		= $tempChargeArr[1];
							$days = $days + 2;
							$VendorDays = 2;
						}
						$ShippingModeRS[$p]['days']	= $days;
						$DayVal = date("h@@a");
						$DayValArr = explode("@@",$DayVal);
					   
						if($DayValArr[1] == "pm")
						{
						   if($DayValArr[0] >=2)
						   {
							   $ShippingModeRS[$p]['days'] = $ShippingModeRS[$p]['days'] + 1;
						   }	
						}
						$normalPWeight = 0;
						$lightPWeight = 0;
						$heavyPWeight = 0;
						$CartArr = Session::get('ShoppingCart.Cart');
						
						for($t=0;$t<count($CartArr);$t++)
						{
							if(isset($CartArr[$t]["shipping_weightVal"]) && $CartArr[$t]["shipping_weightVal"] == "Normal" && $normalWeight > 0)
							{
								$normalPWeight = $normalPWeight + ($normalWeight * $CartArr[$t]["Qty"] ); 
							}
							if(isset($CartArr[$t]["shipping_weightVal"]) && $CartArr[$t]["shipping_weightVal"] == "Light" && $lightWeight > 0)
							{
								$lightPWeight = $lightPWeight + ($lightWeight * $CartArr[$t]["Qty"] ); 
							}
							if(isset($CartArr[$t]["shipping_weightVal"]) && $CartArr[$t]["shipping_weightVal"] == "Heavy" && $heavyWeight > 0)
							{
								$heavyPWeight = $heavyPWeight + ($heavyWeight * $CartArr[$t]["Qty"] );
							}
						}
						
						$tempCharge = $tempCharge + $normalPWeight + $lightPWeight + $heavyPWeight;

						$charge_str = '';
						if($tempCharge>0)
						{
							$charge_str = Price($tempCharge,true);
						}

						if(empty($Sess_ShippingInfo['ShippingMethodID']))
						{
							 if($shipping_mode_id==29)
							 {
								$r_sel = " checked ";
								$r_sel_box = 'active'; 
							 }
							 else if($count==0)
							 {
								$r_sel = " checked ";
								$r_sel_box = 'active';
							 }
							else
							{
								$r_sel = "";
								$r_sel_box = '';
							}
						}
						else
						{
							if($Sess_ShippingInfo['ShippingMethodID']==$ShippingModeRS[$p]['shipping_mode_id'])
							{
								$r_sel = " checked ";
								$r_sel_box = 'active';
								$SelShipMethod = $Sess_ShippingInfo['ShippingMethodID'];
							}
							else
							{
								$r_sel = "";
								$r_sel_box = '';
							}
						}
						$estimateShipDate='';
						$DateFieldVal = '';
						$EstimatedDeliveryDate = '';
						$DateSuffix = '';
						$DayNewValOf = '';
						
						if($ShippingModeRS[$p]['days']!='')
						{
							if($ShippingModeRS[$p]['days']==0)
							{
								$estimateShipDate='';
								$EstimatedDeliveryDate = '';
								$DateFieldVal = '';
								$DateSuffix = '';
								$DayNewValOf = '';
							}
							else
							{
								$sdate = date('Y-m-d');

								$edate = date('Y-m-d', strtotime("+" . $ShippingModeRS[$p]['days'] . "days"));
								$satsun_cnt = $this->countWeekendDays($sdate, $edate);
								$holiday_day_arr = ShippingHoliday::whereBetween('holiday_date',[$sdate,$edate])->where('holiday_status','=','1')->where('holiday_date','!=',date("Y-m-d"))->get();
								$holiday_day = $holiday_day_arr->count();

								$exact_shipday = $ShippingModeRS[$p]['days'] + $satsun_cnt + $holiday_day;
								$approx_shipdate = date('Y-m-d', strtotime("+" . $exact_shipday . "days"));
								$extradays = '0';
								$daynew = $this->checkday($approx_shipdate);
								if ($daynew == 'saturday')
								{
									$extradays = '2';
								}
								else if ($daynew == 'sunday')
								{
									$extradays = '1';
								}
								$ShippingModeRS[$p]['days'] = $exact_shipday + $extradays;
								$dt_date =  date('M d', strtotime("+".$ShippingModeRS[$p]['days']. "days"));
					
								$estimateShipDate='Estimated Delivery on or before <b>'.$dt_date.'</b>';
								$EstimatedDeliveryDate =  date('Y-m-d H:i:s', strtotime("+".$ShippingModeRS[$p]['days']. "days"));
								
								$DateFieldVal =  date('M d', strtotime("+".$ShippingModeRS[$p]['days']. "days"));
								$DateSuffix =  date('S', strtotime("+".$ShippingModeRS[$p]['days']. "days"));
								$DayNewValOf = date('D', strtotime("+".$ShippingModeRS[$p]['days']. "days"));
							}
						}else
						{
							$estimateShipDate='';
							$DateFieldVal = '';
							$EstimatedDeliveryDate = '';
							$DateSuffix = '';
							$DayNewValOf = '';
						}
						$Checkcounter = 1;
						if(Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag') == 'Yes' && (in_array($ShippingModeRS[$p]['shipping_mode_id'],Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeID'))) && Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes')
						{
							$charge_str = '';
						}
						
						if(Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes' && $ShippingModeRS[$p]['shipping_mode_id'] == Session::get('ShoppingCart.PromoCoupon.FreeShippingModeID'))
						{
							$charge_str = '';
						}
						
						if($r_sel_box == 'active' && $IsVenderItem == 'Yes')
						{ 
							$dt_date =  date('m/d', strtotime("+".$VendorDays. "days"));
							$VendorPopup = str_replace('{$daysval}',$dt_date,config('Settings.VENDORITEM_POPUP_WINDOW'));
							$VendorPopup = str_replace('{$days}',$VendorDays,$VendorPopup);
							$this->PageData['VendorPopup'] = $VendorPopup; 
						}
						
						if($ShippingModeRS[$p]['shipping_mode_id'] == 29 && $tempCharge <= 0)
						{
							$Max2Days = 1;
						}
						
						if($charge_str!='')
						{
							$ChargeInfo[] = [
								'active' => $r_sel_box,
								'days' => $days,
								'charge' => $this->Make_Price($tempCharge,true),
								'chargewithoutformat' => $tempCharge,
								'checked' => $r_sel,
								'shipping_mode_id' => $ShippingModeRS[$p]['shipping_mode_id'],
								//'display_date' => $DateFieldVal.'<sup>'.$DateSuffix.'</sup> '.$DayNewValOf,
								'display_date' => date('D, F d',strtotime($EstimatedDeliveryDate)),
								'estdate' => $EstimatedDeliveryDate,
								'method_name' => $ShippingModeRS[$p]['type'],
								'charge_str' => $charge_str,
								'estimateShipDate' => $estimateShipDate,
								'dateSort' => $EstimatedDeliveryDate
							];
						}
						else
						{
							$ChargeInfo[] = [
								'active' => $r_sel_box,
								'checked' => $r_sel,
								'charge' => 0,
								'chargewithoutformat' => 0,
								'days' => $days,
								'shipping_mode_id' => $ShippingModeRS[$p]['shipping_mode_id'],
								//'display_date' => $DateFieldVal.'<sup>'.$DateSuffix.'</sup> '.$DayNewValOf,
								'display_date' => date('D, F d',strtotime($EstimatedDeliveryDate)),
								'estdate' => $EstimatedDeliveryDate,
								'method_name' => $ShippingModeRS[$p]['type'],
								'charge_str' => 'Free',
								'estimateShipDate' => $EstimatedDeliveryDate,
								'dateSort' => 0
							];
						}
						$count = $count +1;
					}
					else
					{
						continue;
					}
				}
				
				if(count($ChargeInfo)>0)
				{
					$NewMethods = [];
					foreach($ChargeInfo as $CheckForMaxDay)
					{
						if($Max2Days == 1 && $CheckForMaxDay['shipping_mode_id'] == 22)
						{
							continue;
						}
						$NewMethods[]=$CheckForMaxDay;
					}
					$ChargeInfo = $NewMethods;
				}
				
				$shipping_insurance_checked = 'checked="checked"';	
				$shipping_insurance_widget_checked = 'data-default-checked="true"';
				$shipping_signature_css = 'style="display:none;"';

				if(Session::has('shipping_insurance') && Session::get('shipping_insurance') == "N")
				{
					$shipping_insurance_checked = '';
					$shipping_insurance_widget_checked = 'data-default-checked="false"';
					$shipping_signature_css = '';	
				} 
				$shipping_insurance_charge = 0;
				if(Session::has('shipping_insurance_charge') && Session::get('shipping_insurance_charge') !=""){
					$shipping_insurance_charge = Session::get('shipping_insurance_charge');
				}
				$ShippingSignatureInfo = [];
				if($Checkcounter==1)
				{
					if(config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE') > 0 && Session::get('is_dropshipper') == "Yes" && Session::get('eusertype') == "Wholesaler" && Session::get('etype') == "M" && $ship_country=="US")
					{
						/*$ShippingSignatureInfo[]= '
						<div id="shipping_signature_div" class="checkbox" '.$shipping_signature_css.'>
							<label style="padding-left:0px; vertical-align:top;" class="fsbold">$'.config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE').' Request Signature &nbsp;&nbsp;</label>
							<label class="switch">
								<input type="checkbox"  value="Yes" name="shipping_signature" id="shipping_signature" >
								<span class="slider round text_off" id="slider_round">Off</span>					
							</label>
						</div>';*/
						$ShippingSignatureInfo[]='
						<div id="shipping_signature_div" class="checkbox" '.$shipping_signature_css.'>
						<span>$'.config('Settings.DROPSHIPPER_SHIPPING_SIGNATURE').' Request Signature</span>
						<label class="switch" id="insurance">
							<input type="checkbox" value="Yes" name="shipping_signature" id="shipping_signature">
							<span class="slider round"></span>
						</label>
						</div>
						';
					}
					if(config('Settings.SHIPPING_SIGNATURE') > 0 && Session::get('is_dropshipper') !="Yes" && $ship_country=="US")
					{
						/*$ShippingSignatureInfo[]= '
						<div id="shipping_signature_div" class="checkbox" '.$shipping_signature_css.'>
							<label class="fsbold" style="padding-left:0px; vertical-align:top;">$'.config('Settings.SHIPPING_SIGNATURE').' Request Signature &nbsp;&nbsp;</label><label class="switch">
								<input type="checkbox" value="Yes" name="shipping_signature" id="shipping_signature" ">						
								<span class="slider round text_off" id="slider_round">Off</span>
							</label>					 
						</div>';*/
						$ShippingSignatureInfo[]='
						<div id="shipping_signature_div" class="checkbox" '.$shipping_signature_css.'>
						<span>$'.config('Settings.SHIPPING_SIGNATURE').' Request Signature</span>
						<label class="switch" id="insurance">
							<input type="checkbox" value="Yes" name="shipping_signature" id="shipping_signature">
							<span class="slider round"></span>
						</label>
						</div>
						';
					}
				}

				$RouteWidget ='<div id="RouteWidget" '.$shipping_insurance_widget_checked.'></div><input type="checkbox" value="Yes" name="shipping_insurance" '.$shipping_insurance_checked.' style="display:none;" id="shipping_insurance" ><input type="hidden" id="shipping_insurance_charge" value="'.$shipping_insurance_charge.'">';
				if(count($ChargeInfo) > 0)
				{
					/*if($IsVenderItem=="Yes" && config('Settings.VENDORITEM_POPUP_WINDOW') !='')
					{
						$RouteWidget.=' <a href="Javascript:void(0);" onclick="SetPaymentMethods();" data-target="#myModalPopUp" data-toggle="modal" class="button btn-1 btn-medium">Continue</a>';
					}
					else
					{
						$RouteWidget.=' <a href="Javascript:void(0);" onclick="SetPaymentMethods();"  class="button btn-1 btn-medium">Continue</a>';
					}*/
				}
				
				if($Checkcounter == 0)
				{
					$ChargeInfo = [];
				}else {
					$sortDates = array_column($ChargeInfo, 'dateSort');
					array_multisort($sortDates, SORT_ASC, $ChargeInfo);
				}
				
				if(count($ChargeInfo) > 0)
				{
					if($SelShipMethod == 0 ){
						Session::put('ShoppingCart.EstimatedDeliveryDate',$ChargeInfo[0]['estdate']);
						Session::put('ShoppingCart.Shipping.ShippingMethodName',$ChargeInfo[0]['method_name']);
						Session::put('ShoppingCart.Shipping.ShippingMethodID',$ChargeInfo[0]['shipping_mode_id']);
						Session::put('ShoppingCart.VendorShippingDateVal.setVendorshipDay',$ChargeInfo[0]['days']);
						Session::put('ShoppingCart.Shipping.ShippingDays',$ChargeInfo[0]['estimateShipDate']);
						Session::put('ShoppingCart.Shipping.ShippingCharge',NumberFormat($ChargeInfo[0]['chargewithoutformat']));
						$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased);
					} else {
						foreach($ChargeInfo as $key => $ShipCharge)
						{
							if($key == 0)
							{
								Session::put('ShoppingCart.EstimatedDeliveryDate',$ShipCharge['estdate']);
								Session::put('ShoppingCart.Shipping.ShippingMethodName',$ShipCharge['method_name']);
								Session::put('ShoppingCart.Shipping.ShippingMethodID',$ShipCharge['shipping_mode_id']);
								Session::put('ShoppingCart.VendorShippingDateVal.setVendorshipDay',$ShipCharge['days']);
								Session::put('ShoppingCart.Shipping.ShippingDays',$ShipCharge['estimateShipDate']);
								Session::put('ShoppingCart.Shipping.ShippingCharge',NumberFormat($ShipCharge['chargewithoutformat']));
								$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased);
							}
						}
					}
				}
				$CurrDate = date('Y-m-d');
				$CurrDayVal = date("H@@a");
				$CurrDayValArr = explode("@@",$CurrDayVal);
				$this->PageData['datediff'] = '';
				if($CurrDayValArr[1] == "pm")
				{
					if($CurrDayValArr[0] >=14)
					{
						$NewCurrDate = date_create(date('Y-m-d H:i:s'));
						$NewDate = date_create(date('Y-m-d H:i:s', strtotime(date('Y-m-d 14:00:00') . ' +1 day')));   
						$DateDiff = $NewCurrDate->diff($NewDate);
						
						if($DateDiff->format('%h') > 0)		
							$this->PageData['datediff'] = $DateDiff->format("%h hours %i minutes");
						else 
							$this->PageData['datediff'] = $DateDiff->format("%i minutes");
					} else {
						$NewCurrDate = date_create(date('Y-m-d H:i:s'));
						$NewDate = date_create(date('Y-m-d 14:00:00'));   
						$DateDiff = date_diff($NewCurrDate,$NewDate);
						
						if($DateDiff->format('%h') > 0)		
							$this->PageData['datediff'] = $DateDiff->format("%h hours %i minutes");
						else 
							$this->PageData['datediff'] = $DateDiff->format("%i minutes");
					} 					   
				} else {
					$NewCurrDate = date_create(date('Y-m-d H:i:s'));
					$NewDate = date_create(date('Y-m-d 14:00:00'));   
					$DateDiff = date_diff($NewCurrDate,$NewDate);
					
					if($DateDiff->format('%h') > 0)		
						$this->PageData['datediff'] = $DateDiff->format("%h hours %i minutes");
					else 
						$this->PageData['datediff'] = $DateDiff->format("%i minutes");
				}
				
				$this->PageData['ShippingMessage'] = array_unique($MsgVal);
				$this->PageData['ShippingSignatureInfo'] = $ShippingSignatureInfo;
				$this->PageData['RouteWidget'] = $RouteWidget;
				$this->PageData['ShippingMethods'] = $ChargeInfo;
			}	
			$this->PageData['OnlyHead'] = $request->OnlyHead;
			$this->SetShippingInsuranceCharge('remove');
			$this->SetShippingInsuranceCharge('add');
			$this->SetupCart();
			$ShippingInsuranceCharge = (Session::has('shipping_insurance_charge')) ? Session::get('shipping_insurance_charge'):0;
			$ShippingSignature = (Session::has('shipping_signature')) ? Session::get('shipping_signature'):0;
			$InsureAmount = $this->GetNetTotal() - $ShippingInsuranceCharge - $ShippingSignature;
			if($InsureAmount > 200)
			{
				Session::forget('ShoppingCart.ShippingSignature');
			}
			
			$this->PageData['InsureAmount'] = $this->GetNetTotal() - $ShippingInsuranceCharge - $ShippingSignature;
			if(isset($request->subaction) && $request->subaction =='stripecart')
			{
				$shipping_mode_tmp_arr = [];
				foreach($ChargeInfo as $ckey => $SMethod)
				{
					$shipping_mode_tmp_arr[] = array(
						'id'=>(string)$SMethod['shipping_mode_id'],
						'label'=>strip_tags($SMethod['method_name']),
						'detail'=>$SMethod['display_date'],
						'amount'=>round($SMethod['chargewithoutformat']*100),
					);
				}
				return $shipping_mode_tmp_arr;
			}
			$ShipMethodsHtml = view('checkout.shipping-methods')->with($this->PageData)->render();
			$CheckoutBoxHTML = view('checkout.subtotalbox')->with($this->PageData)->render();
			return response()->json(['ShipMethodsHtml' => $ShipMethodsHtml, 'CheckoutBoxHTML' => $CheckoutBoxHTML]);
			//return view('checkout.shipping-methods')->with($this->PageData);		
		}
	}
	
	public function PaymentMethods(Request $request)
	{
		$this->PageData = [];
		$Views = [];
		if($request->ajax())
		{
			if(isset($request->action) && $request->action == 'setcreditlimit')
			{
				$check = $request->check;
				$this->ApplyCreditDiscount($check);
				$ship_country = Session::get('ShoppingCart.ShippingAddress.country');
				$ship_state = Session::get('ShoppingCart.ShippingAddress.state'); 
				$ship_zip = Session::get('ShoppingCart.ShippingAddress.zip');
				$onlyGCPurchased = $request->onlyGCPurchased;
				$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased);
				if($request->ShipInsCharge == 'yes')
				{
					$this->SetShippingInsuranceCharge('remove');
					$this->SetShippingInsuranceCharge('add');
				}
				$this->SetupCart();
				$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
				$this->PageData['CreditDiscount'] = $CreditDiscount;
				$CreditData = $this->GetCreditLimitAmount();
				if($check == 1)
					$RemainCreditLimit = $CreditData['RemainCreditLimit'];
				else 
					$RemainCreditLimit = $CreditData['CreditLimit'];		
				$CheckoutBoxHTML = view('checkout.subtotalbox')->with($this->PageData)->render();
				$CreditLimitBoxHTML = view('checkout.credit-limit')->with($this->PageData)->render();
				$NetTotal = $this->GetNetTotal();
				return response()->json(['CheckoutBoxHTML' => $CheckoutBoxHTML, 'CreditLimitBoxHTML' => $CreditLimitBoxHTML,'UnformatedRemainCreditLimit' => $RemainCreditLimit,'RemainCreditLimit' => Price($RemainCreditLimit), 'NetTotal' => $NetTotal]);
			}
		}
		if(isset($request->action) && $request->action == 'paymentinfo')
		{
			$this->PageData['SelMethod'] = '';
			$this->PageData['is_paypal'] = 'no';
			$this->PageData['SelPayMethod'] = $request->SelPayMethod;
			if($request->OnlyHead == '0')
			{
				$Billing  = Session::get('ShoppingCart.BillingAddress');
				$MethodNoShow = "No";
				$onlyAmazonPaypal = 0;
				$allowpaymentoption = ['PAYMENT_STRIPE','PAYMENT_MOC'];
				$OrderTotal = NumberFormat($this->GetNetTotal()) * 100;
				$this->PageData['Is_Afterpay_Checkout'] = 'No';
				if($OrderTotal >= Session::get('Afterpay.Min_AP_AMT') && $OrderTotal <= Session::get('Afterpay.Max_AP_AMT'))
				{
					$this->PageData['Is_Afterpay_Checkout'] = 'Yes';
					$allowpaymentoption[]= 'PAYMENT_PAYWITHAFTERPAY';
				}
				/*if(Session::get('Is_Afterpay_Checkout') == "Yes"){
					$allowpaymentoption[]= 'PAYMENT_PAYWITHAFTERPAY';
				}*/
				if(isset($request->SelPayMethod) && $request->SelPayMethod == 'paypal'){
					$allowpaymentoption=['PAYMENT_PAYPALEC'];
					$this->PageData['SelMethod'] = 	'PAYMENT_PAYPALEC';
					$this->PageData['is_paypal'] = 'yes';
				}
				$show="No";
				$OnlyWT = 0;
				$NetTotal = $this->GetNetTotal();
				if(Session::get('payment_amount') > 0 && Session::get('sess_icustomerid') > 0 && Session::get('etype') == "M")
				{
					if($NetTotal > Session::get('payment_amount'))
					{
						$allowpaymentoption = ['PAYMENT_WT'];
						$OnlyWT = 1;
						$show="No";
						$MethodNoShow = "No";
					}
				}
				else
				{
					if($NetTotal > 5000)
					{
						$allowpaymentoption = ['PAYMENT_WT'];
						$OnlyWT = 1;
						$show="No";
						$MethodNoShow = "No";	
					}
				}
				$OnlyGiftCert = 0;
				$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
				if(strtolower(trim(Session::get('eusertype')))!="wholesaler" && trim(Session::get('is_dropshipper')) !="Yes" && $this->isGiftCouponsAvailable() == 1)
				{
					if($NetTotal <=0 && $CreditDiscount <= 0)
					{
						$allowpaymentoption = "'PAYMENT_GIFT_CERTIFICATE'";
						$OnlyGiftCert = 1;
						$show="No";
						$MethodNoShow = "No";
					}
				}
				if($OnlyGiftCert!=1)
				{
					$PaymentMethodList = PaymentMethod::where('pm_status','=','Active')->whereIn('pm_group_name',$allowpaymentoption)->orderBy('pm_type','desc')->get();
					if(count($PaymentMethodList)==1)
					{
						if($PaymentMethodList[0]['pm_group_name']=='PAYMENT_WT')
						{
							$allowpaymentoption = ['PAYMENT_WT'];
							$OnlyWT = 1;
							$show="No";
							$MethodNoShow = "No";
						}
					}
					if(count($PaymentMethodList)<=0)
					{
						$PaymentMethodList = PaymentMethod::whereIn('pm_group_name',['PAYMENT_PAYPALCC'])->orderBy('pm_type','desc')->get();
					}
				}
				else
				{
					$PaymentMethodList = array("0"=>array("pm_group_name"=>"PAYMENT_GIFT_CERTIFICATE","pm_name"=>"Gift Certificate "));
				}
				$this->PageData['MethodNoShow'] = $MethodNoShow;
				$this->PageData['OnlyWT'] = $OnlyWT;
				$this->PageData['onlyAmazonPaypal'] = $onlyAmazonPaypal;
				$this->PageData['show'] = $show;
				$is_ds = 0;
				$dropshipFundSection = "No";
				if(Session::get('is_dropshipper') == 'Yes' && Session::get('eusertype') == 'Wholesaler')
				{
					$is_ds = 1;
					$DropshipperAccountDetails = $this->GetDropshipperAccountDetails();
					if(count($DropshipperAccountDetails) > 0)
					{	
						$this->PageData["DropshipperAccountDetails"] = $DropshipperAccountDetails;
						if($DropshipperAccountDetails['total_fund']>=$DropshipperAccountDetails['total_payment']  && $DropshipperAccountDetails['fund_available'] == 'Yes'){
							$PaymentMethodList = array("0"=>array("pm_group_name"=>"PAYMENT_DS","pm_name"=>"Dropshipper Fund"));
						}else{
							$dropshipFundSection = "Yes";
							$PaymentMethodList = array();
						}
					}
				}
				$this->PageData['PaymentMethodList'] = $PaymentMethodList;
				
				$ptype = (isset($request['ptype']) && $request['ptype'] == "AP") ? "AP" : "";
				$this->PageData['ptype'] = $ptype;
				$this->PageData['dropshipFundSection'] = $dropshipFundSection;
				$this->PageData['is_ds'] = $is_ds;
				
				$GiftValue= $this->FreeGiftValue($this->GetNetTotal());
				$giftflag = 0;
				$freegiftcombo = '';
				if(count($GiftValue) > 0)
				{
					$giftflag = 1;
					$freegiftcombo = "<select name='freegiftvalue' id='freegiftvalue' class='form-control'>";
					$freegiftcombo .="<option value=''>Select Gift</option>";
					
					for($i=0;$i<count($GiftValue);$i++)
					{
						$selected="";
						if(Session::get('ShoppingCart.FreeGift') == $GiftValue[$i])
						{
							$selected = "selected=selected";
						}
						$freegiftcombo .="<option value=\"".$GiftValue[$i]."\" $selected >".$GiftValue[$i]."</option>";
					}
					$freegiftcombo .= "</select>";
				}
				$this->PageData['giftflag'] = $giftflag;
				$this->PageData['NetTotal'] = $NetTotal;
				$this->PageData['freegiftcombo'] = $freegiftcombo;
				$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
				$this->PageData['CreditDiscount'] = $CreditDiscount;	
				$CreditData = $this->GetCreditLimitAmount();
				$this->PageData['CartAttr'] = $CreditData ;
			}
			$ship_country = Session::get('ShoppingCart.ShippingAddress.country');
			$ship_state = Session::get('ShoppingCart.ShippingAddress.state'); 
			$ship_zip = Session::get('ShoppingCart.ShippingAddress.zip');
			$onlyGCPurchased = $request->onlyGCPurchased;
			$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased);
			if($request->ShipInsCharge == 'yes')
			{
				$this->SetShippingInsuranceCharge('remove');
				$this->SetShippingInsuranceCharge('add');
			}
			$this->SetupCart();
			$Views['CheckoutBoxHTML'] = view('checkout.subtotalbox')->with($this->PageData)->render();
			$Views['ShipInfo'] = view('checkout.shippinginfo')->render();
			$this->PageData['OnlyHead'] = $request->OnlyHead;
			$Views['PayMethods'] = view('checkout.payment-methods')->with($this->PageData)->render();
			return response()->json($Views);
		}
	}
	
	public function SetBilling(Request $request)
	{
		$this->SetBillingAddress($request);
		$this->SetShippingAddress($request);
		$this->PageData['Billing'] = Session::get('ShoppingCart.BillingAddress');
		$this->PageData['Shipping'] = Session::get('ShoppingCart.ShippingAddress');
		if(!Auth::user() && config('global.IS_GUEST_CHECKOUT') == 'Yes')
		{
			$this->SetGuestCustomer($request);
		} else {
			$this->CustomerInfoUpdate($request);
		}
		return view('checkout.shipbillinfo')->with($this->PageData);		
	}
	
	public function CustomerInfoUpdate($request)
	{
		$allow_update_details = "Yes";
		if(Auth::user() && $allow_update_details == "Yes" )
		{
			if($request['bill_country'] != 'US'){
				$state = $request['bill_other_state'];
			}else{
				$state = $request['bill_state'];
			}
			$CustomerAddNew = array (
			'first_name'		=> stripslashes($request['bill_fname']),
			'last_name' 		=> stripslashes($request['bill_lname']),
			'address1' 			=> stripslashes($request['bill_address1']),
			'city' 				=> stripslashes($request['bill_city']),
			'state' 			=> $state,
			'country' 			=> $request['bill_country'],
			'zip' 				=> $request['bill_zip'],
			'phone' 			=> $request['bill_phone']
			);
			if($request['bill_company'] != ""){
				$CustomerAddNew['company_name'] = stripslashes($request['bill_company']);		
			}	
			if($request['bill_address2'] != ""){
				$CustomerAddNew['address2'] = stripslashes($request['bill_address2']);		
			}	
			$cust_upd = Customer::where('customer_id','=',Auth::user()->customer_id)->update($CustomerAddNew);
			
			//merge guest accounts
			$user_email = Session::has('sess_useremail') ? Session::get('sess_useremail') : "";
			if($user_email == ""){
				$user_email = $request['bill_email'];
			}
			$this->Merge_Guest_Register($user_email,Session::get('sess_icustomerid'));
			//merge guest accounts
			/** OMANISEND **/
			$NewsLetter = (isset($request['newsletter']))?'Yes':'No';
			OmanisendRequest('create_customer',Auth::user(),['newsletter' => $NewsLetter]);
			/** OMANISEND **/
		}
		
		if(isset($request['newsletter']) && $request['newsletter'] == 'Yes' && trim($request['bill_email'])!='')
		{
			$check_news = NewsLetter::where('email','=',trim($request['bill_email']))->get();
			if($check_news && $check_news->count() <=0)
			{
				$arrInsert = array(
				'first_name' => trim($request['bill_fname']),
				'last_name'  => trim($request['bill_lname']),
				'email' 	 => trim($request['bill_email']),
				'phone_no' => trim($request['bill_phone']),
				'status'	 => '1'
				);
				$News = NewsLetter::create($arrInsert);
				$NewsId = $News->news_letter_id;
				if($NewsId)
				{
					$data["phone"] = trim($request['bill_phone']); //"+12679018713";
					$data["email"] = trim($request['bill_email']);//"test@gmail.com";
					$data["first_name"] = trim($request['bill_fname']);
					$data["visitorId"] = $NewsId; //"762bb2a97d604f958e3071fef83dfd5a";
					if(trim($data["phone"])!="" && config('global.SITE_MODE') == 'Live' ){
						AddAttentiveSubscriber($data);
					}
				}
			}
		}
	}
	
	public function SetGuestCustomer($request)
	{
		if(!Auth::user() && config('global.IS_GUEST_CHECKOUT') == 'Yes')
		{ 
			$check_cust_email = Customer::where('email','=',trim($request['bill_email']))
								->where('registration_type','=','M')->get();
			$registration_type = "Member";
			$allow_update_details = "Yes";
			if($check_cust_email && $check_cust_email->count() <= 0 )
			{
				
				$check_cust_email = Customer::where('email','=',trim($request['bill_email']))
								->where('registration_type','=','G')
								->where('is_deleted','=','No')->get();
				$registration_type = "Guest";
			}
			if($check_cust_email && $check_cust_email->count() > 0 )
			{
				Session::put('sess_icustomerid',$check_cust_email[0]['customer_id']);
				Session::put('etype','G');
				Session::put('eusertype',$check_cust_email[0]['eusertype']);
				Session::put('sess_useremail',$check_cust_email[0]['email']);
				$allow_update_details = "No";
				
				if($check_cust_email[0]['status'] == "0"){
					$CustomerArr = array (
						'upd_datetime' 		=> date('Y-m-d H:i:s'),
						'merge_log' 		=> "Auto updated to Active from billing page",
						'status' 			=> '1'
					);
					$check_cust_email[0]['status'] = '1';
					$cust_upd = Customer::where('customer_id',Session::get('sess_icustomerid'))->update($CustomerArr);
				}
				/** OMANISEND **/
				$NewsLetter = (isset($request['newsletter']))?'Yes':'No';
				OmanisendRequest('create_customer',$check_cust_email[0],['status' => '1', 'newsletter' => $NewsLetter]);
				/** OMANISEND **/
				
				Session::put('ShoppingCart.merge_note',"Merge with ".$check_cust_email[0]['eusertype']." (".$registration_type.") customer id: ".$check_cust_email[0]['customer_id']);
				Session::put('ShoppingCart.is_registered_guest',"Yes");
			}else{
				if($request['bill_country'] != 'US'){
					$state = $request['bill_other_state'];
				}else{
					$state = $request['bill_state'];
				}
				$CustomerAddNew = array (
					'first_name'		=> stripslashes($request['bill_fname']),
					'last_name' 		=> stripslashes($request['bill_lname']),
					'address1' 			=> stripslashes($request['bill_address1']),
					'city' 				=> stripslashes($request['bill_city']),
					'state' 			=> $state,
					'country' 			=> $request['bill_country'],
					'zip' 				=> $request['bill_zip'],
					'phone' 			=> $request['bill_phone'],
					'email' 			=> $request['bill_email'],
					'registration_type' => 'G',
					'status' 			=> '1',
					'eusertype'			=> 'Retailer',
					'customer_ip' 		=> $_SERVER['REMOTE_ADDR'],
					'customer_browser' 	=> $_SERVER['HTTP_USER_AGENT']
				);	
				$customer_id = (int)Session::get('sess_icustomerid');
				if(!Session::has('sess_icustomerid'))
				{
					$NewCustomer = Customer::create($CustomerAddNew);
					$customer_id = $NewCustomer->customer_id;
					Session::put('sess_icustomerid',$customer_id) ;
					Session::put('etype','G');
					Session::put('eusertype','Retailer');
					Session::put('sess_useremail',$request['bill_email']);
					/** OMANISEND **/
					$NewsLetter = (isset($request['newsletter']))?'Yes':'No';
					OmanisendRequest('create_customer',$NewCustomer,['newsletter' => $NewsLetter]);
					/** OMANISEND **/
				}else{
					Customer::where('customer_id','=',$customer_id)->update($CustomerAddNew);
					$ExistingCustomer = Customer::findOrFail($customer_id);
					/** OMANISEND **/
					$NewsLetter = (isset($request['newsletter']))?'Yes':'No';
					OmanisendRequest('create_customer',$ExistingCustomer,['newsletter' => $NewsLetter]);
					/** OMANISEND **/
					//$customer_id = Customer::where('customer_id','=',$customer_id)->update($CustomerAddNew);
				}
			}
			
			if($allow_update_details == "No"){
				$user_email = $request['bill_email'];
				$this->Merge_Guest_Register($user_email,Session::get('sess_icustomerid'));
			}
			$this->SetBillingAddress($request);
			$Billing = Session::get('ShoppingCart.BillingAddress');
			if(isset($request['newsletter']) && $request['newsletter'] == 'Yes' && trim($request['bill_email'])!='')
			{
				$check_news = NewsLetter::where('email','=',trim($request['bill_email']))->get();	
				if($check_news && $check_news->count() <=0)
				{
					$arrInsert = array(
						'first_name' => trim($request['bill_fname']),
						'last_name'  => trim($request['bill_lname']),
						'email' 	 => trim($request['bill_email']),
						'phone_no' => trim($request['bill_phone']),
						'status'	 => '1'
					);
					$News = NewsLetter::create($arrInsert);
					$NewsId = $News->news_letter_id;
					if($NewsId)
					{
						$data["phone"] = trim($request['bill_phone']); //"+12679018713";
						$data["email"] = trim($request['bill_email']);//"test@gmail.com";
						$data["first_name"] = trim($request['bill_fname']);
						$data["visitorId"] = $NewsId;
						if(trim($data["phone"])!="" && config('global.SITE_MODE') == 'Live'){
							AddAttentiveSubscriber($data);
						}
					}
				}
			}
			if(!Auth::user() && Session::get('sess_icustomerid') != '')
			{
				$customer_password = trim($request['guest_password']);
				if(isset($customer_password) && $customer_password!='' && $registration_type == "Guest")
				{
					$check_cust_email = Customer::where('customer_id','=',(int)Session::get('sess_icustomerid'))
										->where('email','=',trim($request['bill_email']))
										->where('registration_type','=','M')->get();
					if($check_cust_email && $check_cust_email->count()>0)
					{
						$msg = "To become a registered customer, please change your email address, as its already in use.";
						return response()->json(array('error' => 1,'Message' => $msg));
					}
					else
					{
						$check_cust_email = Customer::where('customer_ip','=',$_SERVER['REMOTE_ADDR'])
											->where('registration_type','=','M')
											->where('customer_id','!=',(int)Session::get('sess_icustomerid'))->get();
						if(count($check_cust_email)>=5)
						{
							$msg = "Oops .. Your IP has reached the maximum count of user registered with Fragrance Depot.There are 5 different users already registered from this IP.";
							return response()->json(array('error' => 1,'Message' => $msg));
						}
						else
						{
							if ($request['bl_country'] != 'US'){
								$state = $request['bill_other_state'];
							}else{
								$state = $request['bill_state'];
							}
							
							$CustomerUPDArr = array (	
							'first_name'		=> stripslashes($request['bill_fname']),
							'last_name' 		=> stripslashes($request['bill_lname']),
							'address1' 			=> stripslashes($request['bill_address1']),
							'address2' 			=> stripslashes($request['bill_address2']),
							'city' 				=> stripslashes($request['bill_city']),
							'state' 			=> $state,
							'country' 			=> $request['bill_country'],
							'zip' 				=> $request['bill_zip'],
							'phone' 			=> $request['bill_phone'],
							'email' 			=> $request['bill_email'],
							'status' 			=> '1',
							'eusertype'			=> 'Retailer',
							'customer_ip' 		=> $_SERVER['REMOTE_ADDR'],
							'customer_browser' 	=> $_SERVER['HTTP_USER_AGENT'],
							'password'			=> $customer_password,
							'registration_type' => 'M',
							//'reg_datetime' 		=> date('Y-m-d H:i:s'),
							'upd_datetime' 		=> date('Y-m-d H:i:s'),
							'iRewardpoint'		=> '150'
							);
							$where = "customer_id= '".(int)Session::get('sess_icustomerid')."' ";
							$cust_upd = Customer::where('customer_id','=',(int)Session::get('sess_icustomerid'))->update($CustomerUPDArr);
							if($cust_upd)
							{
								/** OMANISEND **/
								$ExistingCustomer = Customer::findOrFail((int)Session::get('sess_icustomerid'));
								$NewsLetter = (isset($request['newsletter']))?'Yes':'No';
								OmanisendRequest('create_customer',$ExistingCustomer,['newsletter' => $NewsLetter]);
								/** OMANISEND **/
								$RewardPointVal["customer_id"] = Session::get('sess_icustomerid');
								$RewardPointVal["note"] = "Reward Point Added By Checkout Register";
								$RewardPointVal["iRewardpoint"] = 150;
								$RewardDiscountPoint = RewardPoint::create($RewardPointVal);
								Session::put('etype','M');
								Session::put('sess_useremail',trim($request['bill_email']));
								
								$Template = GetMailTemplate("CUSTOMER_REGISTER");
								$EmailBody = str_replace('{$vFirstName}',$request['bill_fname'],$Template[0]->mail_body);
								$EmailBody = str_replace('{$vLastName}',$request['bill_lname'],$EmailBody);
								$EmailBody = str_replace('{$vemail}',$request['bill_email'],$EmailBody);
								$EmailBody = str_replace('{$password}',$customer_password,$EmailBody);
								$EmailBody = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$EmailBody);
								$EmailBody = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$EmailBody);
								$EmailBody = str_replace('{$TOLL_FREE_NO}',config('Settings.CONTACT_PHONE_NO'),$EmailBody);
								$EmailBody = str_replace('{$Site_URL}',config('global.SITE_URL'),$EmailBody);
								$FreeShipping = "";
								if(config('Settings.FREESHIPPING_VALUE') && config('Settings.FREESHIPPING_VALUE') > 0) {
									$FreeShipping = '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</span>';
								}
								$EmailBody = str_replace('{$freeshippinginfo}',$FreeShipping,$EmailBody);
								
								$To = $request['bill_email'];
								$Subject = $Template[0]->subject;
								$EmailBody = $Template[0]->mail_body;
								$From = config('Settings.CONTACT_MAIL');
								SendMail($Subject,$EmailBody,$To,$From);
								
								$To = config('Settings.ADMIN_MAIL');
								SendMail($Subject,$EmailBody,$To,$From);
								
								//merge guest accounts
								$user_email = $request['bill_email'];
								$this->Merge_Guest_Register($user_email,Session::get('sess_icustomerid'));
								//merge guest accounts
								
								$show_login_box    = 'No';
								$show_password_box = 'No';
								if(!Auth::user())
								{
									$show_password_box 	= 'Yes';
									$show_login_box 	= 'Yes';
								}
								$this->SetBillingAddress($request);
								$Billing  = Session::get('ShoppingCart.BillingAddress');
								//echo $ajaxTemplate."###1";
								exit;
							}
							else
							{
								$msg = "Error###You have not been registered, please try again to become a registered customer.";
								return response()->json(array('error' => 1,'Message' => $msg));
								exit;
							}
						}								
					}
				}
			}				
		}
	}
	
	public function CheckAvailableShippingMethod($shipping_mode_id = NULL, $ship_country,$ship_state,$ship_zip)
	{
		$shipping_mode_id = (int)$shipping_mode_id;
	
		$ShippingMethodRS = ShippingMode::where('status','=','1')->where('shipping_mode_id','=',$shipping_mode_id)->get();
       
		if ($ship_country != "")
		{
			## this condition is for Z + S + C
			$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();
            	
			## this condition is for Z + C
			if ($rid && $rid->count() <= 0)
			{
				$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('country','like','%'.$ship_country.'%')->get();		
							
				## this condition is for S + C
				if ($rid && $rid->count() <= 0)
				{
					$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();
                    	
					## this condition is for only C
					if ($rid && $rid->count() <= 0)
					{
						$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','=','')->where('zipcode_to','=','')->where('zipcode_from','=','')
								->where('country','like','%'.$ship_country.'%')->get();
					}
				}
			}
            
			if ($rid && $rid->count() > 0 && $ShippingMethodRS && $ShippingMethodRS->count() > 0)
			{
				if($rid[0]["normal_charge"]=="")
				{
					$normal_chrage = 0;
				}
				else
				{
					$normal_chrage = $rid[0]["normal_charge"];
				}
				if($rid[0]["light_charge"]=="")
				{
					$light_charge = 0;
				}
				else
				{
					$light_charge = $rid[0]["light_charge"];
				}
				if($rid[0]["heavy_charge"]=="")
				{
					$heavy_charge = 0;
				}
				else
				{
					$heavy_charge = $rid[0]["heavy_charge"];
				}
				$shipping_mode_id = (int)$ShippingMethodRS[0]['shipping_mode_id'];
				$deusertype = '';
				if(Session::get('is_dropshipper')=='Yes')
					$deusertype = 'Dropshipper';

				if($ShippingMethodRS[0]["eusertype"] == $deusertype && Session::get('sess_icustomerid')!="" && Session::get('eusertype')=="Wholesaler")
				{
				   return (int)$shipping_mode_id."###".$normal_chrage."###".$light_charge."###".$heavy_charge;
				}
				else if($ShippingMethodRS[0]["eusertype"]==Session::get('eusertype') && Session::get('is_dropshipper')!='Yes')
				{
					return (int)$shipping_mode_id."###".$normal_chrage."###".$light_charge."###".$heavy_charge;
				}
				else
				{
					if(Session::get('sess_icustomerid')=="" && $ShippingMethodRS[0]["eusertype"]=="Retailer")
					{
						return (int)$shipping_mode_id."###".$normal_chrage."###".$light_charge."###".$heavy_charge;
					}
					if(Session::get('sess_icustomerid')!="" && $ShippingMethodRS[0]["eusertype"]=="Retailer" && Session::get('eusertype')=="")
					{
						return (int)$shipping_mode_id."###".$normal_chrage."###".$light_charge."###".$heavy_charge;
					}
				}
			}
			else
			{
				return false;
			}
		}else{
			return false;
		}
	}
	public function CalculateAvailableShippingCharge($ship_zip,$ship_state,$ship_country,$shipping_mode_id)
	{	
		$AllDiscount = $this->GetAllDiscounts();
		$subTotal = Session::get('ShoppingCart.SubTotal') - $AllDiscount['TotalDiscount'];

		$ship_country  = substr($ship_country, 0, 2);
		$shipping_mode_id = (int)$shipping_mode_id;
		if ($ship_country != "")
		{
			## this condition is for Z + S + C
			$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();

			## this condition is for Z + C
			if ($rid && $rid->count() <= 0)
			{
				$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('country','like','%'.$ship_country.'%')->get();

				## this condition is for S + C
				if ($rid && $rid->count() <= 0)
				{
					$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();

					## this condition is for only C
					if ($rid && $rid->count() <= 0)
					{
						$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','=','')->where('zipcode_to','=','')->where('zipcode_from','=','')
								->where('country','like','%'.$ship_country.'%')->get();
					}
				}
			}
		}
		if($rid && $rid->count() > 0 )
		{
			$shipping_rule_id 	= $rid[0]["shipping_rule_id"];
			$rule_type  		= $rid[0]["rule_type"];
			$days				= $rid[0]["days"];
							
			if ($shipping_rule_id != "" && $rule_type == 1 )
			{
				$rowrate = ShippingRate::where('shipping_rule_id','=',$shipping_rule_id)
											->where('order_amount','<=',$subTotal)
											->orderBy('order_amount','desc')->limit(1)->get();				
			}
			else if($shipping_rule_id != "" && ($rule_type==0 || $rule_type==2))
			{
				//$totalitem = $Cart->getTotalItemInCart() - $Cart->getGiftCertiCount();
				$totalitem = Session::get('ShoppingCart.TotalItemInCart') ;
				
				$rowrate = ShippingRate::where('shipping_rule_id','=',$shipping_rule_id)
											->where('order_amount','<=',$totalitem)
											->orderBy('order_amount','desc')->limit(1)->get();			   
				############ FOR FREE SHIPPING FOR ITEM COUNT ##########
					if($rid[0]["is_free_ship"]=="Yes")
					{
						if($rid[0]["free_ship_amt"]<=$subTotal)
						{
							$temp_ShippingCharge=0;
							//return $temp_ShippingCharge;
						}
					}
				############## FOR FREE SHIPPING FOR ITEM COUNT ##############
			}
			$charge = 0;
			if($rowrate && $rowrate->count() > 0)
			{
				$charge = $rowrate[0]["charge"];
				if($rid[0]["is_free_ship"]=="Yes")
				{
					if($rid[0]["free_ship_amt"]<=$subTotal)
					{
						$charge=0;
					}
				}
			}
			if ($charge > 0)
				$temp_ShippingCharge = $charge;
			else
				$temp_ShippingCharge = 0;

			########### START CODE FOR CALCULATE PROP SHIP CHARGE###########
			if($rid[0]["prop_item"] > 0)
			{
				if($rid[0]["prop_charge"] > 0)
				{
					if($totalitem >= $rid[0]["prop_item"])
					{
						$extraitem = ($totalitem-$rid[0]["prop_item"]) + 1;
						$propshippingcharge  = ($rid[0]["prop_charge"]*$extraitem);
						$temp_ShippingCharge = $temp_ShippingCharge+$propshippingcharge;
					}
				}
			}
			if(Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag') == 'Yes' && (in_array($shipping_mode_id,Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeID'))) && Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes')
			{
				$temp_ShippingCharge = 0;
			}
			if(Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes' && $shipping_mode_id == Session::get('ShoppingCart.PromoCoupon.FreeShippingModeID'))
			{
				$temp_ShippingCharge = 0;
			}
			########### END CODE FOR CALCULATE PROP SHIP CHARGE###########
			return $temp_ShippingCharge."###".$days;
		}
	}
	
	public function countWeekendDays($start, $end)
	{
		$iter = 24*60*60; // whole day in seconds
		$count = 0; // keep a count of Sats & Suns
		$start = strtotime($start);
		$end   = strtotime($end);
		for($i = $start; $i <= $end; $i=$i+$iter)
		{
		   if(Date('D',$i) == 'Sat' || Date('D',$i) == 'Sun')
		   {
				$count++;
		   }
		}
		return $count;
	}
	public function checkday($date)
	{
		$timestamp = strtotime($date);
		$weekday= date("l", $timestamp );
		$normalized_weekday = strtolower($weekday);
		if (($normalized_weekday == "saturday") || ($normalized_weekday == "sunday")) {
			return $normalized_weekday;
		}
	}
	
	public function TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased)
	{
		Log:info($ship_country.'==='.$ship_state.'==='.$ship_zip.'==='.$onlyGCPurchased);
		if(Session::get('eusertype') == "Wholesaler")
		{
			Session::put('ShoppingCart.Tax', 0);
			return NULL;
		}
		$is_cal_tax = 'Yes';
		if($onlyGCPurchased==1) { 
			$is_cal_tax = 'No'; 
		}

		if($is_cal_tax == 'No')
		{
			Session::put('ShoppingCart.Tax', 0);
			return NULL;
		}
		
		$AllDiscount = $this->GetAllDiscounts();
		$GiftCertiTotal = 0;
		if(Session::has('ShoppingCart.GiftCertiTotal'))
			$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
		$ShippingChargeTotal = 0;
		If(Session::has('ShoppingCart.Shipping.ShippingCharge') && Session::get('ShoppingCart.Shipping.ShippingCharge') > 0)
			$ShippingChargeTotal = Session::get('ShoppingCart.Shipping.ShippingCharge');
		
		$AllDiscount["TotalDiscount"] = $AllDiscount["TotalDiscount"] - Session::get('ShoppingCart.credit_limit_discount');
		$subTotal = (Session::get('ShoppingCart.SubTotal') + $ShippingChargeTotal) - ($GiftCertiTotal+ $AllDiscount["TotalDiscount"]);

		$subTotal = NumberFormat($subTotal);
		if($subTotal <=0)
		{
			$subTotal = 0;
		}
		if($ship_zip == '' )
			$ship_zip = '0';
		
		$temp_tax = 0; 
		## Compare Zip and country
		 $taxtareas = TaxAreas::where('zip_from','>=',(int)$ship_zip)->where('zip_to','<=',(int)$ship_zip)
								->where('zip_from','!=','')->where('zip_to','!=','')->where('states','=','')
								->where('country','=',$ship_country)->where('status','=','1')->get();	
		if($taxtareas && $taxtareas->count() > 0)
		{
			$taxrates = TaxRates::where('tax_areas_id','=',(int)$taxtareas[0]["tax_areas_id"])
								->where('amount_from','<=',$subTotal)->orderBy('amount_from','desc')->get();
			if($taxrates && $taxrates->count() > 0)
			{
				$pertex = $taxrates[0]["charge_amount"];
				if($subTotal>=$taxrates[0]["amount_from"])
				{
					if ($taxrates[0]["amount_in_percent"] == 'Y')
					{
						$temp_tax = (($subTotal * $pertex) / 100);
						Session::put('ShoppingCart.Tax', $temp_tax);
						return NULL;
					}
					else
					{
						Session::put('ShoppingCart.Tax', $pertex);
						return NULL;
					}
				}
			}
		}

		## Compare Country or State or Zip
		$taxtareas = TaxAreas::where('zip_from','>=',(int)$ship_zip)->where('zip_to','<=',(int)$ship_zip)
								->where('zip_from','!=','')->where('zip_to','!=','')
								->where('states','!=','')->where('states','=',$ship_state)
								->where('country','=',$ship_country)->where('status','=','1')->get();
		if($taxtareas && $taxtareas->count() > 0)
		{
			$taxrates = TaxRates::where('tax_areas_id','=',(int)$taxtareas[0]["tax_areas_id"])
								->where('amount_from','<=',$subTotal)->orderBy('amount_from','desc')->get();

			if($taxrates && $taxrates->count() > 0)
			{
				$pertex = $taxrates[0]["charge_amount"];
				if($subTotal >= $taxrates[0]["amount_from"])
				{
					if ($taxrates[0]["amount_in_percent"] == 'Y')
					{
						$temp_tax = (($subTotal * $pertex) / 100);
						Session::put('ShoppingCart.Tax', $temp_tax);
						return NULL;
					}
					else
					{
						Session::put('ShoppingCart.Tax', $pertex);
						return NULL;
					}
				}
			}
		}

		### Code on New perfume4us
		## Compare Country AND  State
		$taxtareas = TaxAreas::where('zip_from','=','')->where('zip_to','=','')
								->where('states','!=','')->where('states','=',$ship_state)
								->where('country','=',$ship_country)->where('status','=','1')->get();
		if($taxtareas && $taxtareas->count() > 0)
		{
			$taxrates = TaxRates::where('tax_areas_id','=',(int)$taxtareas[0]["tax_areas_id"])
								->where('amount_from','<=',$subTotal)->orderBy('amount_from','desc')->get();

			if($taxrates && $taxrates->count() > 0)
			{
				$pertex = $taxrates[0]["charge_amount"];
				if($subTotal>=$taxrates[0]["amount_from"])
				{
					if ($taxrates[0]["amount_in_percent"] == 'Y')
					{
						$temp_tax = (($subTotal * $pertex) / 100);
						Session::put('ShoppingCart.Tax', $temp_tax);
						return NULL;
					}
					else
					{
						Session::put('ShoppingCart.Tax', $pertex);
						return NULL;
					}
				}
			}
		}
		## Compare Country AND  State
		### Code on New perfume4us

		## Compare Country
		$taxtareas = TaxAreas::where('country','=',$ship_country)->where('country','!=','US')->where('status','=','1')->get();
		
		if($taxtareas && $taxtareas->count() > 0)
		{
			$taxrates = TaxRates::where('tax_areas_id','=',(int)$taxtareas[0]["tax_areas_id"])
								->where('amount_from','<=',$subTotal)->orderBy('amount_from','desc')->get();

			if($taxrates && $taxrates->count() > 0)
			{
				$pertex = $taxrates[0]["charge_amount"];
				if($subTotal>=$taxrates[0]["amount_from"])
				{
					if ($taxrates[0]["amount_in_percent"] == 'Y')
					{
						$temp_tax = (($subTotal * $pertex) / 100);
						Session::put('ShoppingCart.Tax', $temp_tax);
						return NULL;
					}
					else
					{
						Session::put('ShoppingCart.Tax', $pertex);
						return NULL;
					}
				}
			}
		}
		Session::put('ShoppingCart.Tax', $temp_tax);
		return NULL;
	}
	
	public function SetShippingMethod(Request $request)
	{
		if($request->ajax())
		{
			if(isset($request->ShipMethodID) && $request->ShipMethodID != '')
			{
				$this->PageData = [];
				$ship_country = Session::get('ShoppingCart.ShippingAddress.country');
				$ship_state = Session::get('ShoppingCart.ShippingAddress.state');
				$ship_zip = Session::get('ShoppingCart.ShippingAddress.zip');
				$IsCosmo = $request->IsCosmo;
				$IsNandansons = $request->IsNandansons;
				$IsPerfumePW = $request->IsPerfumePW;
				$IsPCA = $request->IsPCA;
				$IsVenderItem = $request->IsVenderItem;	
				$shipping_mode_id = $request->ShipMethodID;
				$onlyGCPurchased = $request->onlyGCPurchased;
				if(isset($request->action) && $request->action == 'stripecart')
				{
					$ship_country = $request->country;
					$ship_state = $request->state;
					$ship_zip = $request->zip;
					$IsVenderItem = "No";
					$IsCosmo = "No";
					$IsNandansons = "No";
					$IsPerfumePW = "No";
					$IsPCA = "No";
					$IsMaxaromaTwoDelivery = "No";
					$ShopCartItems = Session::get('ShoppingCart.Cart');
					foreach($ShopCartItems as $ShopItem)
					{
						if((isset($ShopItem['IsCosmo']) && $ShopItem['IsCosmo']=="Yes") || (isset($ShopItem['IsNandansons']) && $ShopItem['IsNandansons']=='Yes') || (isset($ShopItem['IsPerfumePW']) && $ShopItem['IsPerfumePW']=='Yes') || (isset($ShopItem['IsPCA']) && $ShopItem['IsPCA']=='Yes') && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
						{
							$IsVenderItem = "Yes";
						}
						if((isset($ShopItem['IsCosmo']) && $ShopItem['IsCosmo']=="Yes") && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
						{
							$IsCosmo = "Yes";
						}
						if((isset($ShopItem['IsNandansons']) && $ShopItem['IsNandansons']=="Yes") && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
						{
							$IsNandansons = "Yes";
						}
						if((isset($ShopItem['IsPerfumePW']) && $ShopItem['IsPerfumePW']=="Yes") && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
						{
							$IsPerfumePW = "Yes";
						}
						if((isset($ShopItem['IsPCA']) && $ShopItem['IsPCA']=="Yes") && (isset($ShopItem['VendorSKU']) && $ShopItem['VendorSKU']!=''))
						{
							$IsPCA = "Yes";
						}
						
						if(isset($ShopItem['IsMaxaromaTwoDelivery']) && $ShopItem['IsMaxaromaTwoDelivery']=="Yes" && $ShopItem['IsMaxaromaTwoDelivery']!='')
						{
							$IsMaxaromaTwoDelivery = "Yes";
						}else{
							$IsMaxaromaTwoDelivery = "No";
						}
						if($ShopItem['SKU']!= config('global.GIFT_CERTIFICATE_SKU') && $ShopItem['SKU']!= config('global.GIFT_CERTIFICATE_SKU1'))
							$onlyGCPurchased = 0;
					}
				}		
				$ShipMethod = $this->CheckShippingMethod($shipping_mode_id,$ship_country,$ship_state,$ship_zip,$IsCosmo,$IsNandansons,$IsPerfumePW,$IsPCA,$IsVenderItem);	
				$VendorPopup = '';
				if($IsVenderItem == 'Yes')
				{
					$vendorDays = Session::get('ShoppingCart.VendorShippingDateVal.setVendorshipDay');
					$vendorName = Session::get('ShoppingCart.VendorShippingDateVal.setVendorNameVal');
					if($vendorName=="IsCosmo" || $vendorName=="IsPCA" || $vendorName=="ISNandansons" )
					{
						$daysnew = 2;
					}
					if($vendorName=="IsPWW" )
					{
						$daysnew = 3;
					}
					$dt_date =  date('m/d', strtotime("+".$vendorDays. "days"));
					$VendorPopup = str_replace('{$daysval}',$dt_date,config('Settings.VENDORITEM_POPUP_WINDOW'));
					$VendorPopup = str_replace('{$days}',$vendorDays,$VendorPopup);
				}
				if($ShipMethod['status'] == 'success')
				{
					$this->CalculateShippingCharge($ship_zip,$ship_state,$ship_country,$shipping_mode_id);
					$this->TaxCalculation($ship_country, $ship_state, $ship_zip,$onlyGCPurchased);
					$this->SetShippingInsuranceCharge('add');
					Session::put('ShoppingCart.EstimatedDeliveryDate',$request->EstDate);
					$this->SetupCart();
				} else { 
					$this->PageData['ShipMethodError'] = $ShipMethod['error'];
				}
				$this->SetShippingInsuranceCharge('remove');
				$this->SetShippingInsuranceCharge('add');
				$this->SetupCart();
				
				$ShipInsCharge = 0;
				if(Session::has('shipping_insurance_charge') && Session::get('shipping_insurance_charge') > 0)
					$ShipInsCharge = Session::get('shipping_insurance_charge');
				if(!isset($request->action))
				{
					$SubTotalBox =  view('checkout.subtotalbox')->with($this->PageData)->render();
					return response()->json([ 'SubTotalBox' => $SubTotalBox, 'ShipInsCharge' => Price($ShipInsCharge), 'VendorPopup' => $VendorPopup]);
				} else {
					return $ShipMethod['status'];
				}
			}
		}
	}
	
	public function GetDropshipperAccountDetails($action='',$order_amount=0)
	{
		$DropshipperAccountDetails = array();
		$ds_res = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->get();
		if($ds_res && $ds_res->count() > 0)
		{
			$available_funds = $ds_res[0]['available_funds'];
			if(Session::get('is_dropshipper') == 'Yes' && Session::get('eusertype') == 'Wholesaler')
			{
				if($action == 'dropshipdetails')
					$NetTotal = $order_amount;
				else 
					$NetTotal = $this->GetNetTotal();
				if($available_funds >= $NetTotal)
				{
					$DropshipperAccountDetails['fund_available'] = 'Yes';
					$DropshipperAccountDetails['fund_msg'] = "";
					$DropshipperAccountDetails['total_fund'] = $available_funds;
					$DropshipperAccountDetails['total_payment'] = $NetTotal;
					$DropshipperAccountDetails['remaining_fund'] = $available_funds - $NetTotal;
					$DropshipperAccountDetails['required_fund'] = "";
				}
				else
				{
					$DropshipperAccountDetails['fund_available'] = 'No';
					$DropshipperAccountDetails['fund_msg'] = "Your dropshipper account does not have sufficient balance";
					$DropshipperAccountDetails['total_fund'] = $available_funds;
					$DropshipperAccountDetails['total_payment'] = $NetTotal;
					$DropshipperAccountDetails['remaining_fund'] = "";
					$DropshipperAccountDetails['required_fund'] = $NetTotal - $available_funds;
				}
			}
		}
		return $DropshipperAccountDetails; 
	}
	
	public function GetDropshipperFundDetails(Request $request)
	{
		if(isset($request->action) && $request->action == 'dropshipdetails')
		{
			$this->PageData['order_amount'] = $request->order_amount;
			$this->PageData['DropshipperAccountDetails'] = $this->GetDropshipperAccountDetails('dropshipdetails',$request->order_amount);
			return view('myaccount.dropshipper-fund')->with($this->PageData)->render();
		}	
	}
	
	public function CheckMember(Request $request)
	{
		if($request->ajax())
		{
			if(isset($request->action) && $request->action == 'chkmember')
			{
				$Email = $request->email;
				$Result = Customer::where(db::raw('trim(email)'),'=',$Email)->where('registration_type','=','M')->get();
				if($Result && $Result->count()>0)
					return true;
				else 
					return false;
			}
		}
	}
	
	public function CheckShippingMethod($shipping_mode_id = NULL, $ship_country,$ship_state,$ship_zip,$IsCosmo='No',$IsNandansons='No',$IsPerfumePW='No',$IsPCA='No',$IsVenderItem='No')
	{
		$shipping_mode_id = (int)$shipping_mode_id;
		$ShippingMethodRS = ShippingMode::where('shipping_mode_id','=',$shipping_mode_id)->get();

		if ($ship_country != "")
		{
			## this condition is for Z + S + C
			$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();

			## this condition is for Z + C
			if ($rid && $rid->count() <= 0)
			{
				$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('country','like','%'.$ship_country.'%')->get();

				## this condition is for S + C
				if ($rid && $rid->count() <= 0)
				{
					$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();

					## this condition is for only C
					if ($rid && $rid->count() <= 0)
					{
						$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','=','')->where('zipcode_to','=','')->where('zipcode_from','=','')
								->where('country','like','%'.$ship_country.'%')->get();
					}
				}
			}

			if($rid && $rid->count() > 0 )
			{
				Session::put('ShoppingCart.Shipping.ShippingMethodName', $ShippingMethodRS[0]['type']);
				Session::put('ShoppingCart.Shipping.ShippingMethodID', $ShippingMethodRS[0]['shipping_mode_id']);

				$days = 0;
				if(($IsVenderItem=="Yes" && $IsPerfumePW=="Yes"))
				{
					$days = $this->GetShippingChargeDays($ship_zip,$ship_state,$ship_country,$shipping_mode_id);
					$days = $days + 3;
				}
				else if(($IsVenderItem=="Yes" && $IsCosmo=="Yes") || ($IsVenderItem=="Yes" && $IsPCA=="Yes") || ($IsVenderItem=="Yes" && $IsNandansons=="Yes"))
				{
					$days = $this->GetShippingChargeDays($ship_zip,$ship_state,$ship_country,$shipping_mode_id);
					$days = $days + 2;
				}
					
				$ShippingMethodRS[0]['days']	= $days;
				///////// added on 20-feb-2019 for vendoritempopup
				if($IsVenderItem=="Yes" && $IsCosmo=="Yes")
				{
					Session::put('ShoppingCart.VendorShippingDateVal.setVendorNameVal','IsCosmo');
				}
				if($IsVenderItem=="Yes" && $IsNandansons=="Yes")
				{
					Session::put('ShoppingCart.VendorShippingDateVal.setVendorNameVal','ISNandansons');
				}
				if($IsVenderItem=="Yes" && $IsPCA=="Yes")
				{
					Session::put('ShoppingCart.VendorShippingDateVal.setVendorNameVal','IsPCA');
				}
				if($IsVenderItem=="Yes" && $IsPerfumePW=="Yes")
				{
					Session::put('ShoppingCart.VendorShippingDateVal.setVendorNameVal','IsPWW');
				}
				/////////added on 20-feb-2019 for vendoritempopup
				Session::put('ShoppingCart.Shipping.ShippingDays','');
				if($ShippingMethodRS[0]['days']!='')
				{
					if($ShippingMethodRS[0]['days']==0)
					{
						$estimateShipDate='';
					} else {
						$sdate = date('Y-m-d');
						$edate = date('Y-m-d', strtotime("+" . $ShippingMethodRS[0]['days'] . "days"));
						$satsun_cnt = $this->countWeekendDays($sdate, $edate);
						$holiday_day_arr = ShippingHoliday::whereBetween('holiday_date',[$sdate,$edate])->where('holiday_status','=','1')->where('holiday_date','!=',date("Y-m-d"))->get();	
						$holiday_day = $holiday_day_arr->count();
						$exact_shipday = $ShippingMethodRS[0]['days'] + $satsun_cnt + $holiday_day;
						$approx_shipdate = date('Y-m-d', strtotime("+" . $exact_shipday . "days"));
						$extradays = '0';
						$daynew = $this->checkday($approx_shipdate);
						if ($daynew == 'saturday')
						{
							$extradays = '2';
						} else if ($daynew == 'sunday'){
							$extradays = '1';
						}
						$ShippingMethodRS[0]['days'] = $exact_shipday + $extradays;
						Session::put('ShoppingCart.VendorShippingDateVal.setVendorshipDay',$ShippingMethodRS[0]['days']);
						$dt_date =  date('M d', strtotime("+".$ShippingMethodRS[0]['days']. "days"));
						Session::put('ShoppingCart.Shipping.ShippingDays', 'Estimated Delivery on or before <b>'.$dt_date.'</b>');
					}
				}
				return ['status' => 'success', 'ShipMethodID' => (int)$ShippingMethodRS[0]['shipping_mode_id']];
			}else{
				$errMsg = "The shipping method you selected is not available to your destination. Please select a different method.";
				return ['status' => 'fail','error' => $errMsg];
			}
		}else{
			$errMsg = "The shipping method you selected is not available to your destination. Please select a different method";
			return ['status' => 'fail','error' => $errMsg];
		}
	}
	
	public function CalculateShippingCharge($ship_zip,$ship_state,$ship_country,$shipping_mode_id)
	{
		$TotalDiscount = $this->GetAllDiscounts();
		$subTotal = Session::get('ShoppingCart.SubTotal') - $TotalDiscount['TotalDiscount'];
		$ship_country  = substr($ship_country, 0, 2);
		$shipping_mode_id = (int)$shipping_mode_id;

		if ($ship_country != "")
		{
			## this condition is for Z + S + C
			$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();

			## this condition is for Z + C
			if ($rid && $rid->count() <= 0)
			{
				$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('zipcode_to','>=',$ship_zip)->where('zipcode_from','<=',$ship_zip)
								->where('country','like','%'.$ship_country.'%')->get();

				## this condition is for S + C
				if ($rid && $rid->count() <= 0)
				{
					$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','like','%'.$ship_state.'%')
								->where('country','like','%'.$ship_country.'%')->get();

					## this condition is for only C
					if ($rid && $rid->count() <= 0)
					{
						$rid = ShippingRule::where('shipping_mode_id','=',$shipping_mode_id)
								->where('state','=','')->where('zipcode_to','=','')->where('zipcode_from','=','')
								->where('country','like','%'.$ship_country.'%')->get();
					}
				}
			}
		}
		if($rid && $rid->count() > 0 )
		{
			$shipping_rule_id 	= $rid[0]["shipping_rule_id"];
			$rule_type  		= $rid[0]["rule_type"];
			$NewdaysVal			= $rid[0]["days"];
			if ($shipping_rule_id != "" && $rule_type == 1 )
			{
				$resultrate = ShippingRate::where('shipping_rule_id','=',$shipping_rule_id)
								->where('order_amount','<=',$subTotal)->orderBy('order_amount','desc')->limit(1);
			}
			else if($shipping_rule_id != "" && ($rule_type==0 || $rule_type==2))
			{
				##$totalitem = $this->getTotalItemInCart() - $this->getGiftCertiCount();
				$totalitem = Session::get('ShoppingCart.TotalItemInCart');
				$resultrate = ShippingRate::where('shipping_rule_id','=',$shipping_rule_id)
								->where('order_amount','<=',$totalitem)->orderBy('order_amount','desc')->limit(1);
				##	FOR FREE SHIPPING FOR ITEM COUNT ##
				if($rid[0]["is_free_ship"]=="Yes")
				{
					if($rid[0]["free_ship_amt"]<=$subTotal)
					{
						$temp_ShippingCharge=0;
						Session::put('ShoppingCart.Shipping.ShippingCharge',$temp_ShippingCharge);
						//return NULL;
					}
				}
				## FOR FREE SHIPPING FOR ITEM COUNT ##
			}

			$rowrate = $resultrate->get();
			$charge = $rowrate[0]["charge"];

			if($rid[0]["is_free_ship"]=="Yes")
			{
				if($rid[0]["free_ship_amt"]<=$subTotal)
				{
					$charge=0;
					//return $temp_ShippingCharge;
				}
			}
			if ($charge > 0)
				$temp_ShippingCharge = $charge;
			else
				$temp_ShippingCharge = 0;

			########### START CODE FOR CALCULATE PROP SHIP CHARGE###########
			if($rid[0]["prop_item"] > 0)
			{
				if($rid[0]["prop_charge"] > 0)
				{
					if($totalitem >= $rid[0]["prop_item"])
					{
						$extraitem = ($totalitem-$rid[0]["prop_item"]) + 1;
						$propshippingcharge  = ($rid[0]["prop_charge"]*$extraitem);
						$temp_ShippingCharge = $temp_ShippingCharge+$propshippingcharge;
					}
				}
			}
			
			if($rid[0]["normal_charge"]==""){
				$normal_chrage = 0;
			}else{
				$normal_chrage = $rid[0]["normal_charge"];
			}
			if($rid[0]["light_charge"]==""){
				$light_charge = 0;
			}else{
				$light_charge = $rid[0]["light_charge"];
			}
			if($rid[0]["heavy_charge"]==""){
				$heavy_charge = 0;
			}else{
				$heavy_charge = $rid[0]["heavy_charge"];
			}
			
			$normalPWeight = 0;
			$lightPWeight = 0;
			$heavyPWeight = 0;
			
			$CartArr = Session::get('ShoppingCart.Cart');
			
			for($t=0;$t<count($CartArr);$t++)
			{
				if(isset($CartArr[$t]["shipping_weightVal"]) && $CartArr[$t]["shipping_weightVal"] == "Normal" && $normal_chrage > 0)
				{
					$normalPWeight = $normalPWeight + ($normal_chrage * $CartArr[$t]["Qty"]);  
				}
				if(isset($CartArr[$t]["shipping_weightVal"]) && $CartArr[$t]["shipping_weightVal"] == "Light" && $light_charge > 0)
				{
					$lightPWeight = $lightPWeight + ($light_charge * $CartArr[$t]["Qty"]);  
				}
				if(isset($CartArr[$t]["shipping_weightVal"]) && $CartArr[$t]["shipping_weightVal"] == "Heavy" && $heavy_charge > 0)
				{
					$heavyPWeight = $heavyPWeight + ($heavy_charge * $CartArr[$t]["Qty"]); 
				}
			}
			
			$temp_ShippingCharge = $temp_ShippingCharge + $normalPWeight + $lightPWeight + $heavyPWeight;
			
			if(Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag') == 'Yes' && (in_array($shipping_mode_id,Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeID'))) && Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes')
			{
				$temp_ShippingCharge = 0;
			}
			if(Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes' && $shipping_mode_id == Session::get('ShoppingCart.PromoCoupon.FreeShippingModeID'))
			{
				$temp_ShippingCharge = 0;
			}
			
			//echo $temp_ShippingCharge;
			Session::put('ShoppingCart.Shipping.ShippingCharge',$temp_ShippingCharge);
		}	
		return NULL;
	}
	
	public function PlaceOrder(Request $request)
	{
		if(Session::get('sess_useremail') == 'gequaldev@gmail.com')
		{
			config(['app.debug' => true]);
		}
		if(config('global.SHOPP_STATUS') == 'Close')
		{
			Session::forget('ShoppingCart');
			return redirect('/');	
		}
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) <= 0)
		{
			Session::forget('ShoppingCart');
			return redirect('/shoppingcart');	
		}
		if($this->Is_WholeSaler_Allow() == false)
		{
			return redirect('/shoppingcart');
		}
		
		$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
		$is_amazon = 0;
		if($this->GetNetTotal() <=0 && $CreditDiscount > 0 && config('Settings.WHOLESALE_CREDIT_LIMIT')=='Yes' && Session::get("etype") == "M" && Session::get("sess_icustomerid") > 0 && Session::get("is_dropshipper") !="Yes")
		{
			if($request->PaymentMethod == 'PAYMENT_PAYWITHAMAZON')
				$is_amazon = 1;
			$request->PaymentMethod  = "PAYMENT_CL";
		}   
		
		if($this->GetNetTotal() <= 0 && $CreditDiscount <= 0)
		{
			if($request->PaymentMethod == 'PAYMENT_PAYWITHAMAZON')
				$is_amazon = 1;
			$request->PaymentMethod = 'PAYMENT_GIFT_CERTIFICATE';
		}
	
		if($request->PaymentMethod != 'PAYMENT_PAYPALEC' && (!isset($request->is_paypal) || $request->is_paypal !="yes"))
		{
			$this->setPaymentDetail($request);
		}
		
		if($request->PaymentMethod == 'PAYMENT_PAYPALEC' && isset($request->is_paypal) && $request->is_paypal =="yes")
		{
			$this->setPaymentDetail($request);
		}
		if($request->PaymentMethod == 'PAYMENT_PAYWITHAFTERPAY')
		{
			$this->setPaymentDetail($request);
		}
		if($request->PaymentMethod == 'PAYMENT_PAYWITHAMAZON')
		{
			$this->setPaymentDetail($request);
		}
		
		if($request->PaymentMethod == 'PAYMENT_STRIPE_BUTTON')
		{
			$this->setPaymentDetail($request);
		}
		
		if($request->PaymentMethod == 'PAYMENT_AUTHORIZENETCC' || $request->PaymentMethod == 'PAYMENT_PAYPALCC'  || $request->PaymentMethod == 'PAYMENT_BRAINTREECC')
		{
			if($request->CCType =='' || $request->CCNumber =='' ||  $request->CCMonth =='' || $request->CCYear == '' || $request->CCholdername =='')
			{
				$err_msg = "Error while processing your order, Please try again.";
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError:'.$request->PaymentMethod.' - Credit Card issue.');
				return redirect()->back();
			}
		}
		
		$customer_id = (int)Session::get('sess_icustomerid');
		
		if(!Session::has('sess_icustomerid') || empty($customer_id))
		{
			$err_msg = "Error while processing your order, Please try again.";
			Session::flash('PlaceOrderError',$err_msg);
			Log::info('PlaceOrderError: Customer Not Found');
			return redirect()->back();
		}
		
		$Billing  = Session::get('ShoppingCart.BillingAddress');

		if(($request->PaymentMethod != 'PAYMENT_STRIPE_BUTTON' && $request->PaymentMethod != 'PAYMENT_PAYWITHAMAZON' && $is_amazon !=1) && (trim($Billing['first_name'])== '' || trim($Billing['last_name'])== '' || trim($Billing['address1'])== '' || trim($Billing['city'])== '' ||
		   trim($Billing['zip'])== '' || trim($Billing['state'])== '' || trim($Billing['country'])== '' || trim($Billing['phone'])== '' ||
		   trim($Billing['email'])== ''))
		{
				$err_msg = "Please fill the required fields for billing information. ";
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError: BillingAddress Validation');
				return redirect()->back();
		}
		
		$Shipping = Session::get('ShoppingCart.ShippingAddress');
		$onlyGCPurchased = $this->GetCartAttribute('onlyGCPurchased');
		//$onlyGCPurchased = $request->onlyGCPurchased;
		/*if(!$onlyGCPurchased || $onlyGCPurchased == "")
			$onlyGCPurchased = 0;*/
		if($onlyGCPurchased == 0)
		{
			if($request->PaymentMethod != 'PAYMENT_PAYWITHAMAZON' && (trim($Shipping['first_name']) == '' || trim($Shipping['last_name']) == '' || trim($Shipping['address1']) == '' ||
			trim($Shipping['city']) == '' || trim($Shipping['zip'])== '' || trim($Shipping['state'])== '' || trim($Shipping['country'])== '' ))
			{
				$err_msg = "Please fill the required fields for shipping information. ";
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError: ShippingAddress Validation');
				return redirect()->back();
			}
		}
		
		if($onlyGCPurchased == 1)
		{
			Session::put('ShoppingCart.BillingAsShipping','Yes');
			$Billing['sameasbill'] = 'Yes';
			$this->SetShippingAddress($Billing);
		}
		
		$arrPaymentDetail = Session::get('ShoppingCart.Payment_Detail');
		if(Session::has('ShoppingCart.Payment_Detail'))
		{
			if((trim($arrPaymentDetail['Payment_Type']) == '' || $arrPaymentDetail['Payment_Type'] =='PAYMENT_PAYPALEC') && (!isset($request->is_paypal)))
			{
				$err_msg =  "Please choose payment method.";
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError: Payment Method Not Selected');
				return redirect()->back();
			}
		
			if(trim($arrPaymentDetail['Payment_Type']) == '' && isset($request->is_paypal) && $request->is_paypal == "yes")
			{
				$err_msg =  "Please choose payment method.";
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError: Payment Method Not Selected');
				return redirect()->back();
			}
		
			if($arrPaymentDetail['Payment_Type']=='PAYMENT_AUTHORIZENETCC' || $arrPaymentDetail['Payment_Type']=='PAYMENT_PAYPALCC' )
			{
				if($arrPaymentDetail['CCType']=='' || $arrPaymentDetail['CCNumber']=='' || $arrPaymentDetail['CCMonth']=='' || $arrPaymentDetail['CCYear']=='' || $arrPaymentDetail['CCName']=='')
				{
					$msg = "Please fill the credit card information.";
					Session::flash('PlaceOrderError',$msg);
					Log::info('PlaceOrderError: '.$arrPaymentDetail['Payment_Type'].' Credit Card issue.');
					return redirect()->back();
				}
			}
		}
		$ShippingInfo = Session::get('ShoppingCart.Shipping');
		if($onlyGCPurchased == 0)
		{
			if(empty($ShippingInfo['ShippingMethodID']) || (int)$ShippingInfo['ShippingMethodID'] <=0 )
			{
				$err_msg =  "Please choose shipping method.";
				Session::flash('PlaceOrderError',$err_msg);
				Log::info('PlaceOrderError: Shipping Method Not Selected');
				return redirect()->back();
			}
		}
		
		$currency_info = Session::get('currency_code')."#".Session::get('currency_symbol')."#".Session::get('currency_rate');
		if(Session::has('etype') && Session::get('etype') == 'M')
			$checkout_type = 'M';
		else
			$checkout_type = 'G';
		
		$cc_info ="";
		if(Session::has('ShoppingCart.Payment_Detail'))
		{
			if($arrPaymentDetail['Payment_Type'] == 'PAYMENT_AUTHORIZENETCC' || $arrPaymentDetail['Payment_Type'] =='PAYMENT_PAYPALCC')
			{
				$cc_info = $arrPaymentDetail['CCType'].",".substr(trim($arrPaymentDetail['CCNumber']),-4).",Exp.".$arrPaymentDetail['CCMonth']."/".$arrPaymentDetail['CCYear'].",CSC.".$arrPaymentDetail['CSC'];
			}
		}
		$ShippingInfo	 = Session::get('ShoppingCart.Shipping');
		$GiftCouponInfo  = Session::get('ShoppingCart.GiftCoupon');
		$CouponCode = $this->GetAllCoupons('CouponCode');
		$couponRS = array();
		$OneCouponCode = "";
		$coupon_id = '';
		$SecondCouponCode = "";
		$Second_coupon_id = "";
		if(!empty($CouponCode))
		{
			$couponRS = Coupon::where('coupon_number','=',$CouponCode)->limit(1)->get();
			if($couponRS && $couponRS->count() > 0)
			{
				$OneCouponCode = $couponRS[0]->coupon_number;
				$coupon_id = $couponRS[0]->coupon_id;
			}

			$Temp_Second_Coupon_Code =  $this->GetAllCoupons('SecondCouponCode');
			if($Temp_Second_Coupon_Code!='')
			{
				$couponRS = Coupon::where('coupon_number','=',$Temp_Second_Coupon_Code)->limit(1)->get();
				
				if(count($couponRS) > 0)
				{
					$SecondCouponCode = $couponRS[0]->coupon_number;
					$Second_coupon_id = $couponRS[0]->coupon_id;
				}
				if($SecondCouponCode!='')
				{
					$OneCouponCode = $OneCouponCode."#". Session::get('ShoppingCart.PromoCoupon.FirstCouponDiscount').":".$SecondCouponCode."#".Session::get('ShoppingCart.PromoCoupon.SecondCouponDiscount');
				}
			}
		}
			
		## Wholesaler Field Start ###
		$w_user_type = Session::get('eusertype');
		$w_ilevelid  = (Session::has('ilevelid'))?Session::get('ilevelid'):0;
		## Wholesaler Field End ###

		$GiftValue = $this->FreeGiftValue($this->GetNetTotal());
		
		if(count($GiftValue) > 0) {
			$free_gift = Session::get('ShoppingCart.FreeGift');
			$gift_from = Session::get('ShoppingCart.GiftFrom');
			$gift_to   = Session::get('ShoppingCart.GiftTo');
			$gift_message_customer = Session::get('ShoppingCart.GiftMessageCustomer');
		} else {
			$free_gift = '';
			$gift_from = '';
			$gift_to   = '';
			$gift_message_customer = (isset($request->gift_message_customer))?$request->gift_message_customer:'';
			if(strtolower(trim($gift_message_customer)) == " *gift message")
			{
				$gift_message_customer = '';
			}
			Session::forget('ShoppingCart.FreeGift');
			Session::forget('ShoppingCart.GiftFrom');
			Session::forget('ShoppingCart.GiftTo');
			Session::forget('ShoppingCart.GiftMessageCustomer'); 
		}
		
		$CreditData = $this->GetCreditLimitAmount();
		$CreditAmt = $CreditData['CreditLimit'];
		$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
		$cust_current_credit_limit = $CreditAmt;
		$apply_credit = $CreditDiscount;
		$remaining_credit = $CreditData['RemainCreditLimit'];

		if($CreditDiscount>0){
			$use_credit_limit = 'Yes';
		}else{
			$use_credit_limit = 'No';
		}
		//Credit Limit Code End
		
		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == 'PAYMENT_DS')
			$is_dropship_order = 'Yes';
		else
			$is_dropship_order = 'No';
		
		if(strtolower(trim($request->customer_comment)) == "special request")
		{
			$request->customer_comment = "";
		}

		$fullShippingname = '';
		$is_maxtwoday = "No";
		$EstimatedDeliveryDate = '';
		$ShipMethodName = '';
		$ShipMethodCharge = 0;
		$OrdShipSignature = 0;
		$OrdShipInsurance = 0;
		if($onlyGCPurchased == 0)
		{
			if($ShippingInfo["ShippingCharge"] > 0)
			{
				$fullShippingname =  $ShippingInfo["ShippingMethodName"]. " <b>(".Session::get('currency_symbol').$ShippingInfo["ShippingCharge"].")</b> ".Session::get('ShoppingCart.Shipping.ShippingDays');
			}
			else
			{
				$fullShippingname =  $ShippingInfo["ShippingMethodName"]. " <b>(Free)</b> ".Session::get('ShoppingCart.Shipping.ShippingDays');
			}
			if(strtolower($ShippingInfo['ShippingMethodName'])=='max2days')
			{
				$is_maxtwoday = "Yes";
			}
			$EstimatedDeliveryDate = Session::get("ShoppingCart.EstimatedDeliveryDate");
			$ShipMethodName = $ShippingInfo['ShippingMethodName'];
			$ShipMethodCharge = $this->GetShippingCharge();
			$OrdShipSignature = $this->GetAllCharges('ShippingSignature');
			$OrdShipInsurance = $this->GetAllCharges('ShippingInsurance');
		}	
		
		$cur_date = date("Y-m-d");
		if($EstimatedDeliveryDate == "" && strtotime($EstimatedDeliveryDate) < strtotime($cur_date)){
			$EstimatedDeliveryDate = "0000-00-00 00:00:00";
		}
		
		$referDiscountId = (Session::has('ShoppingCart.ReferDiscountId'))?Session::get('ShoppingCart.ReferDiscountId'):0;
		
		$merge_note = (Session::has('ShoppingCart.merge_note')) ? Session::get('ShoppingCart.merge_note') : "";
		$Payment_Type = (isset($arrPaymentDetail['Payment_Type']))?$arrPaymentDetail['Payment_Type']:'';
		$Payment_Method = (isset($arrPaymentDetail['Payment_Method']))?$arrPaymentDetail['Payment_Method']:'';
		
		$PaymentResponse = "";
		if(isset($arrPaymentDetail['Payment_Type']) && $arrPaymentDetail['Payment_Type'] == "PAYMENT_STRIPE_BUTTON")
		{
			$Payment_Type = "PAYMENT_STRIPE";
			$Payment_Method = Session::get("StripePaymentType");
			$PaymentResponse = Session::get("PayMethodRes");
		}
		
		$customer_comment = (isset($request->customer_comment))?$request->customer_comment:'';
		$SubTotal = (float)Session::get('ShoppingCart.SubTotal');
		$GiftCoupon = $this->GetAllCoupons('GiftCoupon');
		$GCAmount = 0;
		$GCCode = "";
		if($GiftCoupon)
		{
			$GCAmount = $GiftCoupon['Value'];
			$GCCode = $GiftCoupon['Code'];
		}
		
		$ShippingSignatureFlag = 'No';
		if($onlyGCPurchased == 0 && isset($request->shipsignatureflag) && $request->shipsignatureflag == 'Yes')
			$ShippingSignatureFlag = 'Yes';
		
		$OrderInsert = array (
				'customer_id'		=> $customer_id,
				'sub_total' 		=> $SubTotal,
				'shipping_amt' 		=> $ShipMethodCharge,
				'tax' 				=> $this->GetAllCharges('Tax'),
				'gift_charge' 		=> $this->GetAllCharges('GiftWrappingCharge'),
				'gift_message' 		=> '',
				'is_gift_order'		=> 'No',
				'handling_charge' 	=> '0.00',
				'wire_discount' 	=> '0.00',
				'auto_discount' 	=> $this->GetAllDiscounts('AutoDiscount'),
				'quantity_discount'	=> $this->GetAllDiscounts('QuantityDiscount'),
				'reward_discount'	=> $this->GetAllDiscounts('AutoRewardDiscount'),
				'coupon_amount' 	=> $this->GetAllDiscounts('CouponDiscount'),
				'coupon_id' 		=> $coupon_id,
				'Second_coupon_id'	=> $Second_coupon_id,
				'coupon_code' 		=> $OneCouponCode,
				'gc_amount' 		=> $this->GetAllDiscounts('GiftCoupon'),
				'gc_code' 			=> $GCCode,
				'refer_id'			=> $referDiscountId,
				'refer_amount' 		=> $this->GetAllDiscounts('AutoReferDiscount'),
				'order_total' 		=> $this->GetNetTotal(),
				'shipinfo' 			=> $ShipMethodName,
				'payment_type' 		=> $Payment_Type,
				'payment_method' 	=> $Payment_Method,
				'pay_status' 		=> 'Unpaid',
				'ccinfo' 			=> $cc_info,
				'customer_comment' 	=> $customer_comment,
				'status'			=> 'Pending',
				'currency_info'		=> $currency_info,
				'checkout_type' 	=> $checkout_type,
				'user_type' 		=> $w_user_type,
				'ilevelid' 			=> $w_ilevelid,
				//'level_price' 		=> $w_level_price,
				'ship_first_name' 	=> $Shipping['first_name'],
				'ship_last_name' 	=> $Shipping['last_name'],
				'ship_company' 		=> $Shipping['company'],
				'ship_email' 		=> $Shipping['email'],
				'ship_address1' 	=> $Shipping['address1'],
				'ship_address2' 	=> $Shipping['address2'],
				'ship_city' 		=> $Shipping['city'],
				'ship_zip' 			=> $Shipping['zip'],
				'ship_state' 		=> $Shipping['state'],
				'ship_country' 		=> $Shipping['country'],
				'ship_phone' 		=> $Shipping['phone'],
				'bill_first_name' 	=> $Billing['first_name'],
				'bill_last_name' 	=> $Billing['last_name'],
				'bill_company' 		=> $Billing['company'],
				'bill_email' 		=> $Billing['email'],
				'bill_address1' 	=> $Billing['address1'],
				'bill_address2' 	=> $Billing['address2'],
				'bill_city' 		=> $Billing['city'],
				'bill_zip' 			=> $Billing['zip'],
				'bill_state' 		=> $Billing['state'],
				'bill_country' 		=> $Billing['country'],
				'bill_phone' 		=> $Billing['phone'],
				'customer_ip' 		=> $_SERVER['REMOTE_ADDR'],
				'customer_browser' 	=> $_SERVER['HTTP_USER_AGENT'],
				'is_only_gc'		=> (string)$onlyGCPurchased,
				'free_gift'			=> $free_gift,
				//======= Added Code Date 09/02/2015 Start Here ===//
				'gift_from'				=> $gift_from,
				'gift_to'				=> $gift_to,
				'gift_message_customer'	=> $gift_message_customer,
				//======= Added Code Date 09/02/2015 End Here ===//
											//Credit Limit Code Start
			   'cust_current_credit_limit' => $cust_current_credit_limit,
			   'apply_credit'          => $apply_credit,
			   'remaining_credit'      => $remaining_credit,
			   'use_credit_limit'      => $use_credit_limit,
											//Credit Limit Code End
			   'is_dropship_order'     => $is_dropship_order,
			   'shipping_signature'	 => $OrdShipSignature,
			   'is_shipping_signature' => $ShippingSignatureFlag,
			   'Is_GiftCertificatPurchase' => $this->GetCartAttribute('CheckGCPurchasedVal'),
			   'EstimatedDeliveryDate' 	=> $EstimatedDeliveryDate,
			   'fullshipping_info'		=> 	$fullShippingname,
			   'merge_note'		=> 	$merge_note,
			   'bogo_discount'	=> $this->GetAllDiscounts('DogoDiscount'),
			   'is_maxtwoday'	=> $is_maxtwoday,
			   'route_shipping_insurance_charge' => $OrdShipInsurance,
                'vLang_flag' => Session::get('ShoppingCart.YotpoFreeGiftCoupon'),
			   //'payment_gateway_response' => $PaymentResponse,
		);
		
		$PlaceOrder = Order::create($OrderInsert);
		$OrderID = $PlaceOrder->orders_id;
		$aa = Session::put('ShoppingCart.OrderID',$OrderID); // set order id in cart
		if($OrderID != "")
		{
			// To add 'OR' Change on :: 06-10-2015	
			$CurrOrder = Order::find($OrderID);
			$updateOrder = array ('orders_no'	 => "OR".$OrderID );
			$udpRefer = $CurrOrder->update($updateOrder);
		}
		
		$tempCart = Session::get('ShoppingCart.Cart');
		$cnt_row  = count($tempCart);
		
		$IsVender = "No";
		for($i=0; $i<$cnt_row; $i++)
		{
            if(!isset($tempCart[$i]['YotpoFreeGift']))
                $tempCart[$i]['YotpoFreeGift'] = '';
			$OrderDetailInsert = array (
				'orders_id'				=> $OrderID,
				'orders_no'				=> "OR".$OrderID, // To add 'OR' Change on :: 06-10-2015
				'products_id'			=> $tempCart[$i]['ProductID'],
				'sku' 					=> $tempCart[$i]['SKU'],
				'product_name'			=> $tempCart[$i]['ProductName'].'<br>'.$tempCart[$i]['short_description'],
				'quantity' 				=> $tempCart[$i]['Qty'],
				'price' 				=> $tempCart[$i]['Price'],
				'total' 				=> $tempCart[$i]['TotPrice'],
				'status' 				=> '1',
				'item_price' 			=> (isset($tempCart[$i]['ItemPrice']))?$tempCart[$i]['ItemPrice']:0,
				'excluded_flag'  		=> (isset($tempCart[$i]['ExcludedFlag']))?$tempCart[$i]['ExcludedFlag']:'',
				'is_gift_wrap'			=> (isset($tempCart[$i]['gift_wrap']))?$tempCart[$i]['gift_wrap']:'',
				'is_free_gift_products' => (isset($tempCart[$i]['IS_Free_Gift']))?$tempCart[$i]['IS_Free_Gift']:'No',
				'VendorSKU'				=> $tempCart[$i]['VendorSKU'],
				'IsCosmo'				=> $tempCart[$i]['IsCosmo'],
				'IsNandansons'  		=> $tempCart[$i]['IsNandansons'],
				'IsPerfumePW'			=> $tempCart[$i]['IsPerfumePW'],
				'IsPCA'					=> $tempCart[$i]['IsPCA'],
				'coupon_itemwise_discount' => $tempCart[$i]['ItemWiseCouponDiscount'],
				'handling_time_str'		=> 	(isset($tempCart[$i]['HandlingTimeStr']))?$tempCart[$i]['HandlingTimeStr']:'',
                'attribute_info'        => (isset($tempCart[$i]['IsYotpoFreeProduct']))?$tempCart[$i]['IsYotpoFreeProduct']:'No',
			);
			$OrdDetail = OrderDetail::create($OrderDetailInsert);
			$OrderDetailID = $OrdDetail->orders_detail_id;			
			if(($tempCart[$i]['IsCosmo']=="Yes" || $tempCart[$i]['IsNandansons']=='Yes' || $tempCart[$i]['IsPerfumePW']=='Yes' || $tempCart[$i]['IsPCA']=="Yes") && $tempCart[$i]['VendorSKU']!='' )
			{
				$IsVender = "Yes";
			}

			## Insert purchased GC
			if($tempCart[$i]['SKU'] == config('global.GIFT_CERTIFICATE_SKU') || $tempCart[$i]['SKU'] == config('global.GIFT_CERTIFICATE_SKU1'))
			{
				$AddGC = $this->InsertGiftCertificateDB($tempCart[$i], $OrdDetail->orders_detail_id, $customer_id);
			}
		}
		
		if($OrderID!="" && $IsVender=="Yes")
		{
			// To add 'OR' Change on :: 06-10-2015
			$updateOrder1 = array('IsVender' => $IsVender);
			$udpRefer1 = $CurrOrder->update($updateOrder1);
		}
		
		$gc_remaining_value = 0;
		if($GiftCouponInfo && count($GiftCouponInfo) > 0)
		{
			$GiftCouponInfo['Value'] = ($GiftCouponInfo['Value']!='')?$GiftCouponInfo['Value']:0.00;
			$new_total = $this->GetNetTotal() + $GiftCouponInfo['Value'];
			if($new_total <= $GiftCouponInfo['Applicable_Value'])
			{
				$gc_remaining_value = NumberFormat(($GiftCouponInfo['Applicable_Value']-$new_total));
			}
			
			if($GiftCouponInfo['Code'] != '' && $new_total <= $GiftCouponInfo['Applicable_Value'])
			{
				$str_info  = 'Gift Certificate discount value is greater than order total amount. \n\n';
				$str_info .= 'So net $'.$new_total.' is deduct from gift certifiacte value. \n\n';
				$str_info .= 'Used Gift Certificate code is ('.$GiftCouponInfo['Code'].')';

				$updAray = array ('pay_status' 	   => 'Paid','transaction_info' => $str_info);
				$updOrder = $CurrOrder->update($updAray);
				return redirect(config('global.SITE_URL')."order-receipt");
			}
		} else if($this->GetNetTotal() == 0){
			$updAray = array ('pay_status' => 'Paid');
			$updOrder = $CurrOrder->update($updAray);
			return redirect(config('global.SITE_URL')."order-receipt");
		}
		
		if($arrPaymentDetail['Payment_Type']=='PAYMENT_PAYPALEC') ## Paypal Express payment gateway condition
		{
			return redirect(config('global.SITE_URL').'paypal/dopayment');
			exit;
		}
		elseif($arrPaymentDetail['Payment_Type'] == 'PAYMENT_PAYPALCC') ## Paypal Do direct payment gateway condition
		{
			//header("location:".$SECURED_PATH."paypal_checkout/paypal_dodirect_payment.php");
			//exit;
		}
		elseif($arrPaymentDetail['Payment_Type'] == 'PAYMENT_STRIPE') ## Braintree payment gateway condition
		{
			return redirect(config('global.SITE_URL').'stripe/placeorder');
			exit;
		}
		elseif($arrPaymentDetail['Payment_Type'] == 'PAYMENT_AUTHORIZENETCC') ## AUTHORIZE payment gateway condition
		{
			//header("location:".$SECURED_PATH."authorize_checkout/payment_authorize.php");
			//exit;
		}
		elseif($arrPaymentDetail['Payment_Type'] == 'PAYMENT_MOC' || $arrPaymentDetail['Payment_Type'] == 'PAYMENT_WT' || $arrPaymentDetail['Payment_Type'] == 'PAYMENT_CL' || $arrPaymentDetail['Payment_Type'] == 'PAYMENT_DS' || $arrPaymentDetail['Payment_Type'] == 'PAYMENT_GIFT_CERTIFICATE' ) ## Other payment gateway condition
		{
			return redirect(config('global.SITE_URL').'order-receipt');
			exit;
		}else if ($arrPaymentDetail['Payment_Type'] == 'PAYMENT_PAYWITHAFTERPAY') { ## Afterpay payment gateway condition
			return redirect(config('global.SITE_URL').'afterpay/placeorder');
			exit;	
			//header("Location:" . $SECURED_PATH . "PayWithAfterpay/afterpay_checkout.php");
			//exit();
		}elseif($arrPaymentDetail['Payment_Type'] == 'PAYMENT_PAYWITHAMAZON'){
			return redirect(config('global.SITE_URL').'amazon/placeorder');
			exit;	
		}elseif($arrPaymentDetail['Payment_Type'] == 'PAYMENT_STRIPE_BUTTON'){
			return redirect(config('global.SITE_URL').'order-receipt');
			exit;
		}
		#### Here check payment type and do processing end ####
		if($OrderID > 0)
		{
			$updArayVAL = array ('pay_status' => 'Unpaid', 'status' => 'Declined');
			$uporderres11 = $CurrOrder->update($updArayVAL);
		}
		return redirect('/');
	}
	
	public function OrderReceipt(Request $request)
	{
		$this->PageData['CSSFILES'] = ['shoppingcart.css','checkout.css'];	
		//$this->PageData['JSFILES'] = ['billing.js','login.js','login_validate.js'];		
		$wholesale_terms = "";
		
		$order_receipt_image_path = config('global.SITE_URL').'images/';
		$order_receipt_image = generalsetting('ORDER_RECEIPT_IMAGE',1);
		$order_receipt_image = $order_receipt_image_path.$order_receipt_image.'?ver='.time();
		$this->PageData['order_receipt_image'] = $order_receipt_image;
		
		if(Session::get('eusertype') == 'Wholesaler')
			$wholesale_terms = "<br><strong style='font-size:14px'>Read our Fragrance Depot Wholesale <a class='viewlink' data-target='#myModalPopUp' data-toggle='modal' href='javascript:void(0);' onclick='DisplayWholesalerTerms();' class='normallink'>Policy</a></strong>";
		$this->PageData['wholesale_terms'] = $wholesale_terms;
		
		$IS_WEB_SITE_ORDER 	= 'Yes';
		$IS_GOOGLE_ORDER 	= 'No';
		$IS_AMAZON_ORDER    = 'No';
		
		$Template = 'order-receipt';
		
		$merchant_order_no = (isset($request->merchant_order_no)?$request->merchant_order_no:'');
		$amazon_order_no = (isset($request->amznPmtsOrderIds)?$request->amznPmtsOrderIds:'');
		
		//$amazon_order_no = '104-5474895-5200227';
		
		if(trim($merchant_order_no) !='')
		{
			$IS_WEB_SITE_ORDER 	= 'No';
			$IS_GOOGLE_ORDER    = 'Yes';
		}

		if(trim($amazon_order_no) !='')
		{
			$IS_WEB_SITE_ORDER 	= 'No';
			$IS_AMAZON_ORDER    = 'Yes';
			$Template = 'order-receipt-amazon';
		}
		$this->PageData['IS_WEB_SITE_ORDER'] = $IS_WEB_SITE_ORDER;
		$this->PageData['IS_GOOGLE_ORDER'] = $IS_GOOGLE_ORDER;
		$this->PageData['IS_AMAZON_ORDER'] = $IS_AMAZON_ORDER;
		
		$topmenubar = '<a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'fragrances/cid/1" target="_blank">Fragrances</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'skincare/cid/18" target="_blank">Skincare</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'pocket-perfume/cid/68" target="_blank">Pocket Perfume</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'bath-body/cid/12" target="_blank">Bath &amp; Body</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>		
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'candles/cid/208" target="_blank">Candles</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#ff0000; text-decoration:none;" href="'.config('global.SITE_URL').'offers.html" target="_blank">SALES & OFFERS</a>
						   ';
		
        $CustomerID = Session::get('sess_icustomerid');
        if($CustomerID == null || empty($CustomerID))	
        {
            return redirect(config('global.SITE_URL'));
            exit;
        }
        $OrderID = Session::get('ShoppingCart.OrderID');
        //$OrderID = '77799';
        if($OrderID == '' || empty($OrderID)) 
        {
            return redirect(config('global.SITE_URL'));
            exit;
        }
        
        Log::channel('order_receipt')->info('Order Receipt Started for Order - '.$OrderID);
        
        $OrderRs = Order::where('orders_id','=',$OrderID)->where('customer_id','=',$CustomerID)->get();

        if(isset($OrderRs[0]["Is_GiftCertificatPurchase"]) && $OrderRs[0]["Is_GiftCertificatPurchase"]=='1')
        {
            $mailTemplate = GetMailTemplate("GC_SEND_CODE");
        }

        $OrderDetailRs = OrderDetail::where('orders_id','=',$OrderID)->orderBy('orders_detail_id')->get();

        $GOOGLE_ORDER_TRACKING = '';
        $GOOGLE_ORDER_TRACKING_GTM = '';
        $gtm_purchase_prod_str = "";

        $RIO_EBAY_COMMERCE_CODE  = '';
        $RIO_EBAY_COMMERCE_CODE  .= "var _roi = _roi || [];
        _roi.push(['_setMerchantId', '532042']);
        _roi.push(['_setOrderId', '".$OrderRs[0]['orders_no']."']);
        _roi.push(['_setOrderAmount', '".$OrderRs[0]['sub_total']."']);
        _roi.push(['_setOrderNotes',  '".$OrderRs[0]['customer_comment']."']);";

        $trusted_code = '';		
        $Bizrate_POS_Code = "var orderId  ='".$OrderRs[0]["orders_no"]."'; 
                             var cartTotal='".$OrderRs[0]['order_total']."'; 
                             var billingZipCode='".$OrderRs[0]["bill_zip"]."'; ";

        $Bizrate_POS_Code_NEW = "";
        $Bizrate_POS_Code_NEW.= "var productsPurchased='";

        $freeshippinginfo = '';
        if(config('Settings.FREESHIPPING_VALUE') !="" && config('Settings.FREESHIPPING_VALUE') > 0)
        {
            $freeshippinginfo.= '<p class="siteoffer" style="margin:0;padding:5px 0px; font-size:16px; font-weight:normal; font-family:Arial,  sans-serif; color:#666666;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</p>';
        }

        $critieostr = '';
        $ProdDetails = [];
        $roktstr = '';
        for($p=0;$p<$OrderDetailRs->count();$p++)
        {
            if($OrderRs[0]["payment_type"]=="PAYMENT_PAYPALEC")
            {
                $GiftCardRes = GiftCertificate::where('status','=','0')->where('orders_detail_id','=',$OrderDetailRs[$p]["orders_detail_id"])->get();
                if($GiftCardRes && $GiftCardRes->count() > 0)
                {
                    $updGiftAray = array ('status' => '1');
                    $uporderresgift = GiftCertificate::where('gc_id','=',$GiftCardRes[0]['gc_id'])->update($updGiftAray);
                }
            }
            $critieostr .=  '{ id: "'.$OrderDetailRs[$p]['sku'].'", price: "'.$OrderDetailRs[$p]['price'].'", quantity: "'.$OrderDetailRs[$p]['quantity'].'" } ,';
            $roktstr.= "{sku:'".$OrderDetailRs[$p]['sku']."',quantity:'".$OrderDetailRs[$p]['quantity']."',productname:'".$OrderDetailRs[$p]['product_name']."',price:'".$OrderDetailRs[$p]['price']."',majorcat:'',minorcat:'',currency:'USD'},";
            $order_items_pixel[] = array(
                                    'id'=> $OrderDetailRs[$p]["sku"],
                                    'quantity' => $OrderDetailRs[$p]["quantity"],
                                    'price' => $OrderDetailRs[$p]["price"]
                                    );

            $giftcertificateItem = "No";
            if($OrderDetailRs[$p]["sku"] == config('global.GIFT_CERTIFICATE_SKU') || $OrderDetailRs[$p]["sku"] == config('global.GIFT_CERTIFICATE_SKU1'))
            {
                $GC_IMAGE_URL = "";
                if($OrderDetailRs[$p]["sku"] == config('global.GIFT_CERTIFICATE_SKU'))
                    $GC_IMAGE_URL = config('global.GC_IMAGE_URL');
                else if($OrderDetailRs[$p]["sku"] == config('global.GIFT_CERTIFICATE_SKU1'))
                    $GC_IMAGE_URL = config('global.GC_IMAGE_URL1');
                if($OrderDetailRs[$p]["sku"] == config('global.GIFT_CERTIFICATE_SKU2'))
                    $GC_IMAGE_URL = config('global.GC_IMAGE_URL2');
                $GCRs = GiftCertificate::where('orders_detail_id','=',$OrderDetailRs[$p]['orders_detail_id'])->where('customer_id','=',$CustomerID)->get();
                if($GCRs && $GCRs->count($GCRs) > 0)
                {
                    $OrderDetailRs[$p]['RecipientName']  = $GCRs[0]['recipient_name'];	
                    $OrderDetailRs[$p]['RecipientEmail'] = $GCRs[0]['recipient_email'];	
                    $OrderDetailRs[$p]['SenderName']  	 = $GCRs[0]['your_name'];	
                    $OrderDetailRs[$p]['SenderEmail'] 	 = $GCRs[0]['your_email'];	
                    $OrderDetailRs[$p]['Image']			 = $GC_IMAGE_URL;
                    $OrderDetailRs[$p]['DeliveryDate']	 = $GCRs[0]['deliverydate'];	
                    $giftcertificateItem = "Yes";
                }
            }else{	
                $FreeGiftSku = '';
                if($OrderDetailRs[$p]["is_free_gift_products"] == "Yes")
                {
                    $FreeGiftSku = 	$OrderDetailRs[$p]['sku'];
                    $OrderDetailRs[$p]['sku'] = str_replace("GIFT-","",$OrderDetailRs[$p]['sku']);	
                }
                $prod_res = DB::table('pu_products as p')
                            ->join('pu_products_category as pc','p.products_id','=','pc.products_id')
                            ->select('p.products_id','p.UPC','p.image','p.product_name','p.vtype','p.short_description','p.product_description','p.sku','p.current_stock','p.cosmo_current_stock','p.nandansons_current_stock','p.perfumeworldwide_currentstock','p.pca_current_stock','pc.category_id')
                            ->where(DB::raw('LOWER(TRIM(sku))'),'=',strtolower(trim($OrderDetailRs[$p]['sku'])))
                            ->limit(1)->get();
                 if(file_exists(config('global.PRD_THUMB_IMG_PATH').$prod_res[0]->image) && !empty($prod_res[0]->image))
                    $thumb_image = config('global.PRD_THUMB_IMG_URL').$prod_res[0]->image;
                 else
                    $thumb_image = config('global.NO_IMAGE_THUMB');

                $OrderDetailRs[$p]['Image'] =$thumb_image;
                $vlink_name = SetProductURL($prod_res[0]->products_id,$prod_res[0]->product_name,$prod_res[0]->category_id);
                $OrderDetailRs[$p]['ProdLink'] = $vlink_name; 
                 if($p < 5)
                 {
                     $Bizrate_POS_Code_NEW.= "".$vlink_name."=^".addslashes($prod_res[0]->sku)."=^".addslashes($prod_res[0]->UPC)."=^".$OrderDetailRs[$p]['total']."=|";  
                 }		
            }	

            if($giftcertificateItem == "Yes" && $GCRs->count() > 0 )
            {
                $message_back = $mailTemplate[0]['mail_body'];
                $subject_back = $mailTemplate[0]['subject'];								
                $subject_back = str_replace('{$SITE_NAME}', config('Settings.SITE_TITLE'),$subject_back);
                $subject_back = str_replace('{$recipient_name}', $GCRs[0]['recipient_name'],$subject_back);
                $subject_back = str_replace('{$sender_name}', $GCRs[0]['your_name'],$subject_back);

                $this->PageData["CONTACT_MAIL"] = config('Settings.CONTACT_MAIL');
                $this->PageData["topmenubar"] = $topmenubar;
                $this->PageData["freeshippinginfo"] = $freeshippinginfo;	
                $this->PageData["recipient_name"] = $GCRs[0]['recipient_name'];	
                $this->PageData["sender_name"] = $GCRs[0]['your_name'];	
                $this->PageData["SITE_NAME"] = config('global.SITE_TITLE');
                $this->PageData["Site_URL"] = config('global.SITE_URL');
                $this->PageData["remaining_value"] = $GCRs[0]["remaining_value"];
                $this->PageData["gc_code"] = $GCRs[0]["gc_code"];
                $this->PageData["freeshippinginfo"] = $freeshippinginfo;
                $this->PageData["message"] = $GCRs[0]["messae"];
                $this->PageData["TOLL_FREE_NO"]= config('global.TOLL_FREE_NO');
                $this->PageData["GiftCard"] = $GCRs[0]['giftimage'];

                $message_back = str_replace('{$freeshippinginfo}',$freeshippinginfo,$message_back);
                $message_back = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$message_back);
                $message_back = str_replace('{$recipient_name}',$GCRs[0]['recipient_name'],$message_back);
                $message_back = str_replace('{$remaining_value}',$GCRs[0]['remaining_value'],$message_back);
                $message_back = str_replace('{$sender_name}',$GCRs[0]['your_name'],$message_back);
                $message_back = str_replace('{$gc_code}',$GCRs[0]["gc_code"],$message_back);
                //$message_back = str_replace('{$gc_amount}',$GiftCouponInfo['Value'],$message_back);
                $message_back = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_PHONE_NO'),$message_back);

                $vtoemail   = $GCRs[0]["recipient_email"];
                $vfromemail = $GCRs[0]["your_email"];

                //SendMail($subject_back,$message_back,$vtoemail,$vfromemail);
                /** OMANISEND **/
                OmanisendRequest('6209ffc44fa101001e950228',$GCRs[0]);
                /** OMANISEND **/
                $cust_res 	= Customer::where('customer_id','=',Session::get('sess_icustomerid'))->get();
                $reward_point = $cust_res[0]['iRewardpoint']+100;
                $giftdata = array();
                $giftdata['is_email'] = "Yes";
                GiftCertificate::where("gc_id","=",$GCRs[0]['gc_id'])->update($giftdata);
            }
            //========GOOGLE ITEM TRACK CODE For Google Trusted on 20June2014=======
            if($OrderDetailRs[$p]["products_id"] > 0)
            {
            $iproductid = $OrderDetailRs[$p]["products_id"];
            $prores = Products::select('our_price','products_id', 'product_name','short_description','sale_price','sku','product_description','vtype','gender','UPC')
                        ->where('products_id','=',$iproductid)->get();

            $g_unit_price = $OrderDetailRs[$p]["price"] / $OrderDetailRs[$p]["quantity"];

            $trusted_code.= '{"gtin":"'.$prores[0]["UPC"].'"},';
            //========GOOGLE ITEM TRACK CODE For Google Trusted on 20June2014=======

            //=======GOOGLE ITEM TRACK CODE START HERE===========//
            $fetch_category = array();
            $gcat = '';
            $fetch_category = DB::table('pu_category as c')
                                ->join('pu_products_category as pc','c.category_id','=','pc.category_id')
                                ->join('pu_products as p', 'pc.products_id','=','p.products_id')
                                ->where('p.products_id','=',$OrderDetailRs[$p]['products_id'])->get();
            if($fetch_category && $fetch_category->count() > 0)
            {
                $gcat = stripcslashes($fetch_category[0]->category_name);
                $gcatid = $fetch_category[0]->category_id;
            }

            $RIO_EBAY_COMMERCE_CODE  .= "_roi.push(['_addItem', 
                                                    '".$prores[0]["sku"]."', 
                                                    '".stripcslashes($prores[0]["product_name"])."',
                                                    '".$gcatid."',
                                                    '".$gcat."',
                                                    '".$OrderDetailRs[$p]["price"]."',
                                                    '".$OrderDetailRs[$p]["quantity"]."'
                                                    ]);";
            // Enanced Google Purchase tracking						* 				*/	   
            $gtm_purchase_prod_str .= "{
                                    'id': '".$prores[0]["sku"]."',  
                                    'name': '".stripcslashes($prores[0]["product_name"])."',
                                    'category': '".$gcat."',
                                    'price': '".$OrderDetailRs[$p]['price']."',						
                                    'quantity': ".$OrderDetailRs[$p]['quantity']."
                                   },";

            $GOOGLE_ORDER_TRACKING .="ga('ec:addProduct', {
                                         id: '".$prores[0]["sku"]."',         // Product SKU
                                        name: '".stripcslashes($prores[0]["product_name"])."',   // Product Name*
                                    category: '".$gcat."',      // Product Category
                                       price: '".$OrderDetailRs[$p]['price']."',              // Price
                                    quantity: '".$OrderDetailRs[$p]['quantity']."'                   // Quantity
                                });";						

            //=======GOOGLE ITEM TRACK CODE END HERE===========//

            }

            $ProdDetails[] = $OrderDetailRs[$p];	
        }

        if($critieostr!='')
        {
            $critieostr = substr($critieostr,0,-1);
        }
        $this->PageData["critieostr"] = $critieostr;

        if($roktstr != '')
        {
            $roktstr = substr($roktstr,0,-1);
        }
        $this->PageData["roktstr"] = $roktstr;

        if(Cookie::has("PEPPERJAM") && Cookie::get("PEPPERJAM") == "YES" && Session::get('eusertype') != "Wholesaler")
        {
            $customArr = Order::where('customer_id','=',$OrderRs[0]["customer_id"])->get();
            $new_to_file = 1;
            if($customArr->count() > 1)
            {
                $new_to_file = 0;
            }

            $order_id = $OrderRs[0]["orders_no"];
            $integration = 'DYNAMIC';
            $program_id = '8716';
            $coupons = $OrderRs[0]["coupon_code"];
            $couponsArr = explode(":",$coupons);
            $TotalCouponsArr = count($couponsArr);
            if(count($couponsArr) > 0)
            {
                if($TotalCouponsArr == 2)
                {
                    $OtherCouponArr1 = explode("#",$couponsArr[0]);
                    $OtherCouponArr2 = explode("#",$couponsArr[1]);
                    $coupons = $OtherCouponArr1[0].','.$OtherCouponArr2[0];
                }else{
                    $coupons = $OrderRs[0]["coupon_code"];
                }
            }
            $pixel_html = '';

            if(count($order_items_pixel) > 0) 
            {
                $AllDiscounts = $this->GetAllDiscounts();
                $TotalDiscount = $AllDiscounts['TotalDiscount'];
                $SubTotal = NumberFormat(Session::get('ShoppingCart.SubTotal'));
                $affiliateDiscount = 0;
                if($TotalDiscount > 0)
                {
                    $affiliateDiscount = 1 -($TotalDiscount / $SubTotal);
                    $affiliateDiscount =  number_format($affiliateDiscount , 2, '.','');
                }
                $x = 1;
                foreach ($order_items_pixel as $order_item) 
                {
                    if($affiliateDiscount > 0 && $affiliateDiscount!='')
                    {
                        $affiliateItemAmount = $order_item['price'] * $affiliateDiscount;
                        $affiliateItemAmount =  number_format($affiliateItemAmount , 2, '.','');
                    }else{
                        $affiliateItemAmount = $order_item['price'];
                    } 

                    $pixel_html .=
                    '&' . 'ITEM_ID' . $x . '=' . $order_item['id'] .
                    '&' . 'ITEM_PRICE' . $x . '=' . $affiliateItemAmount .
                    '&' . 'QUANTITY' . $x . '=' . $order_item['quantity'];
                    $x++;
                }
                if($pixel_html!='' && $coupons!='')
                {
                    $pixel_html ='<iframe src="https://t.pepperjamnetwork.com/track?'.'INT='.$integration.'&PROGRAM_ID='.$program_id.'&ORDER_ID='.$order_id.'&COUPON='.$coupons.'&NEW_TO_FILE='.$new_to_file.$pixel_html.'" width="1" height="1" frameborder="0"></iframe>';
                }else{
                    $pixel_html ='<iframe src="https://t.pepperjamnetwork.com/track?'.'INT='.$integration.'&PROGRAM_ID='.$program_id.'&ORDER_ID='.$order_id.'&NEW_TO_FILE='.$new_to_file.$pixel_html.'" width="1" height="1" frameborder="0"></iframe>';
                }
                $updateOrdernewArr = array ('is_pepperjam'	 => "Yes" );
                //Order::where('orders_id','=',$OrderID)->update($updateOrdernewArr);
                $this->PageData["pixel_html"] = $pixel_html;
            }
        }

        // Enanced Google Purchase tracking
        $GOOGLE_ORDER_TRACKING .="ga('ec:setAction', 'purchase', {
                                    id: '".$OrderRs[0]['orders_id']."',     		// Transaction ID*
                                    affiliation: 'Fragrance Depot', 						// Store Name
                                    revenue: '".$OrderRs[0]['order_total']."',    // Total
                                    tax: '".$OrderRs[0]['tax']."',         		// Tax
                                    shipping: '".$OrderRs[0]['shipping_amt']."' // Shipping
                                });";		

        $GOOGLE_ORDER_TRACKING_GTM .="dataLayer.push(				 
                                         {
                                          'ecommerce': {
                                            'purchase': {
                                              'actionField': {
                                                'id': '".$OrderRs[0]['orders_id']."',
                                                'affiliation': 'Fragrance Depot',
                                                'revenue': '".$OrderRs[0]['order_total']."',
                                                'tax':'".$OrderRs[0]['tax']."',
                                                'shipping': '".$OrderRs[0]['shipping_amt']."'
                                              },
                                              'products': [".rtrim($gtm_purchase_prod_str, ',')."]
                                            }
                                          }
                                        });";
        $Bizrate_POS_Code_NEW = substr($Bizrate_POS_Code_NEW,0,-1);
        $Bizrate_POS_Code_NEW.= "'";
        $Bizrate_POS_Code =$Bizrate_POS_Code.$Bizrate_POS_Code_NEW;

        $this->PageData['Bizrate_POS_Code'] = '<script type="text/javascript">'.$Bizrate_POS_Code.'</script>';	
        $this->PageData['trusted_code'] = trim($trusted_code,",");

        $ShippingInsurance = $OrderRs[0]['route_shipping_insurance_charge'];
        /*if($ShippingInsurance > 0){
            $this->RouteShippingInsuranceOrderProcess($OrderRs[0],$OrderDetailRs);

        }*/	

        #### Deduct product stock Start #####
        if($OrderRs[0]['pay_status'] == 'Paid')
        {
            for($n=0;$n<count($OrderDetailRs);$n++)
            {
                $this->ProductDeductStock($OrderDetailRs[$n]["sku"],$OrderDetailRs[$n]["quantity"],$OrderDetailRs[$n]["IsCosmo"],$OrderDetailRs[$n]["IsNandansons"],$OrderDetailRs[$n]["IsPerfumePW"],$OrderDetailRs[$n]["IsPCA"],$OrderDetailRs[$n]["VendorSKU"]);
            }
        }
        else if($OrderRs[0]['payment_type']=="PAYMENT_STRIPE"  || $OrderRs[0]['payment_type']=="PAYMENT_WT" || $OrderRs[0]['payment_type']=="PAYMENT_DS" ||  $OrderRs[0]['payment_type']=="PAYMENT_CL" || $OrderRs[0]['payment_type']=="PAYMENT_GIFT_CERTIFICATE" )
        {
            for($n=0;$n<count($OrderDetailRs);$n++)
            {
                $this->ProductDeductStock($OrderDetailRs[$n]["sku"],$OrderDetailRs[$n]["quantity"],$OrderDetailRs[$n]["IsCosmo"],$OrderDetailRs[$n]["IsNandansons"],$OrderDetailRs[$n]["IsPerfumePW"],$OrderDetailRs[$n]["IsPCA"],$OrderDetailRs[$n]["VendorSKU"]);
            }
        }
        #### Deduct product stock End #####

        ### Code for payment message Start #######

        $PaymentRs = PaymentMethod::where('pm_group_name','=',$OrderRs[0]['payment_type'])->limit(1)->get();

        $Payment_Method_Message = '';
        if($PaymentRs && $PaymentRs->count() > 0 && trim($PaymentRs[0]['pm_short_desc'])!='' )
        {
            $Payment_Method_Message = stripslashes(trim($PaymentRs[0]['pm_short_desc']));
        }

        ## Msg for status "Pending Review" of AuthNet Start ##		
        if(($OrderRs[0]['payment_type']=='PAYMENT_AUTHORIZENETCC' || $OrderRs[0]['payment_type']=='PAYMENT_PAYPALCC') && $OrderRs[0]['payment_gateway_response']!='')
        {
            $arr_gateway_response = explode(",",$OrderRs[0]['payment_gateway_response']);		
            if ($arr_gateway_response[0] == 4)
            {
                $Payment_Method_Message = "<h5>Thank you! Your order will be processed pending a standard transaction review.</h5>
                <p>We hope you enjoyed shopping with us. Your order will be processed as soon as possible. We will contact you with updates. <br />Please allow 24hrs to process the payment. An E-mail Confirmation will be sent upon payment received.</p>";
            }
        }
        $this->PageData['Payment_Method_Message'] = str_replace('>rn<','><',$Payment_Method_Message);

        $STR_EMAIL_ITEM = '';
        $STR_EMAIL_ITEM .= '<table cellpadding="0" cellspacing="0" width="100%" border="0">
                <tr align="center" valign="top">
                    <td style="background-color:#e5e5e5; padding:5px;"><strong>Gift Wrap</strong></td>
                    <td style="background-color:#e5e5e5; padding:5px;"><strong>Images</strong></td>
                    <td style="background-color:#e5e5e5; padding:5px;" align="left"><strong>Your Order Summary</strong></td>
                    <td style="background-color:#e5e5e5; padding:5px;"><strong>Quantity</strong></td>
                    <td style="background-color:#e5e5e5; padding:5px;" align="right"><strong>Price</strong></td>
                </tr>';
        $TotalProducts = 0;		
        for($n=0;$n<count($OrderDetailRs);$n++)
        {
            $checked = '';
            if($OrderDetailRs[$n]['is_gift_wrap']=='Yes')
            { $checked = 'checked="checked" '; }

            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td valign="middle" style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><input type="checkbox"  disabled="disabled" '.$checked.' /></td><td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><img src="'.$OrderDetailRs[$n]['Image'].'" border="0" width="125" border="0" class="img-resp-75" /></td><td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="left"><p style="color:#000; margin:0px;"><strong>'.$OrderDetailRs[$n]['product_name'].'</strong></p><p>SKU:'.$OrderDetailRs[$n]['sku'].'</p>';

            if($OrderDetailRs[$n]["sku"] == config('global.GIFT_CERTIFICATE_SKU') || $OrderDetailRs[$n]["sku"] == config('global.GIFT_CERTIFICATE_SKU1') || $OrderDetailRs[$n]["sku"] == config('global.GIFT_CERTIFICATE_SKU2'))
            {
                $STR_EMAIL_ITEM .='<p><strong>Sender Name : </strong> '.$OrderDetailRs[$n]['SenderName'].'</p>
                                   <p><strong>Sender Email : </strong> '.$OrderDetailRs[$n]['SenderEmail'].'</p>';
                $STR_EMAIL_ITEM .='<p><strong>Recipient Name : </strong> '.$OrderDetailRs[$n]['RecipientName'].'</p>
                                   <p><strong>Recipient Email : </strong> '.$OrderDetailRs[$n]['RecipientEmail'].'</p>';
            }
            if($OrderDetailRs[$n]["handling_time_str"]!='')
            {
                $STR_EMAIL_ITEM .='<p>'.$OrderDetailRs[$n]['handling_time_str'].'</p>';
            }
            $STR_EMAIL_ITEM .= '</td>';
            $STR_EMAIL_ITEM .= '<td style="padding:10px 5px; border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;"><strong>'.$OrderDetailRs[$n]['quantity'].'</strong></td>
            <td style="padding:10px 5px; border-bottom:1px solid #e8e8e8;" align="right"><strong>'.Price($OrderDetailRs[$n]['price']).'</strong></td>
            </tr>';		

            $TotalProducts = (int)$TotalProducts + (int)$OrderDetailRs[$n]['quantity'];
        }

        if(isset($OrderDetailRs[$n]['is_gift_wrap']) && $OrderDetailRs[$n]['is_gift_wrap']=='Yes')
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong>Gift Wrap:</strong></td><td align="left" style="padding:5px;border-bottom:1px solid #e8e8e8;">Yes</td></tr>';
        }

        $STR_EMAIL_ITEM .= '<tr align="center" valign="top">
            <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong> Total item purchased:</strong></td>
            <td align="left" style="padding:5px;border-bottom:1px solid #e8e8e8;">'.$TotalProducts.'</td>
        </tr>';

        $STR_EMAIL_ITEM .= '<tr align="center" valign="top">
            <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Subtotal:</td>
            <td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['sub_total']).'</td>
        </tr>';

        if($OrderRs[0]["shipping_amt"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Shipping Charge:</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['shipping_amt']).'</td></tr>';
        }

        if($OrderRs[0]["tax"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Sales Tax:</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['tax']).'</td></tr>';
        }

        if($OrderRs[0]["gift_charge"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Gift Wrap Charge :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['gift_charge']).'</td></tr>';
        }

        if($OrderRs[0]["auto_discount"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Auto Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['auto_discount']).'</td></tr>';
        }

        if($OrderRs[0]["quantity_discount"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Quantity Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['quantity_discount']).'</td></tr>';
        }

        if($OrderRs[0]["shipping_signature"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Shipping Signature :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['shipping_signature']).'</td></tr>';
        }

        if($OrderRs[0]["route_shipping_insurance_charge"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Shipping Insurance Charge :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['route_shipping_insurance_charge']).'</td></tr>';
        } 

        if($OrderRs[0]["coupon_amount"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Coupon Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['coupon_amount']).'</td></tr>';
        }

        if($OrderRs[0]["gc_amount"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Gift Certificate Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['gc_amount']).'</td></tr>';
        }

        if($OrderRs[0]["bogo_discount"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Bogo Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['bogo_discount']).'</td></tr>';
        }

        if($OrderRs[0]["reward_discount"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Reward Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['reward_discount']).'</td></tr>';
        }

        if($OrderRs[0]["refer_amount"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Refer Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['refer_amount']).'</td></tr>';
        }

        if($OrderRs[0]["apply_credit"]>0)
        {
            $STR_EMAIL_ITEM .= '<tr align="center" valign="top"><td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right">Credit Discount :</td><td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right">'.Price($OrderRs[0]['apply_credit']).'</td></tr>';
        }

        $STR_EMAIL_ITEM .= '<tr align="center" valign="top">
            <td colspan="4" style="padding:5px;border-bottom:1px solid #e8e8e8; border-right:1px solid #e8e8e8;" align="right"><strong>Order Total:</strong></td>
            <td style="padding:5px;border-bottom:1px solid #e8e8e8;" align="right"><strong>'.Price($OrderRs[0]['order_total']).'</strong></td>
        </tr>';

        $STR_EMAIL_ITEM .= '</table>';

        if($OrderRs[0]["free_gift"]!="")
        {
            $OrderRs[0]["free_gift"] = "Got a Free Gift Of ".$OrderRs[0]["free_gift"]; 
        }
        $OrdDate = date('Y-m-d h:i:s',$OrderRs[0]["order_datetime"]);
        $estimated_ship_date = date('Y-m-d', strtotime($OrdDate . ' + 23 day'));
        $this->PageData["Estimated_Ship_Date"] = $estimated_ship_date;
        $this->PageData["GOOGLE_ORDER_TRACKING"] = $GOOGLE_ORDER_TRACKING;

        $mailTemplate = GetMailTemplate("ORDER_RECEIPT_NEW");
        $this->PageData["topmenubar"] = $topmenubar;
        $this->PageData["freeshippinginfo"] = $freeshippinginfo;

        $MailBanners = MailBanner::where('status','=','1');
        $Addblock = '';
        if($MailBanners && $MailBanners->count() > 0)
        {
            $Addblock .= ' <td class="flex" valign="top" width="27%"><table width="100%" border="0" cellpadding="0" cellspacing="0">
                          <tbody>';
            foreach($MailBanners as $MailBanner)
            {
                $banner_img = config('global.MAIL_BANNERS_URL').$MailBanner->mail_banner_image.".jpg";
                $banner_link = $MailBanner->mail_banner_link;
                $Addblock .= ' <tr class="halftd"> 
                                    <td style="padding:5px;border:1px solid #e8e8e8" align="center"><a href="'.$banner_link.'"  target="_blank"><img src="'.$banner_img.'" alt="" class="img-responsive"/></a>
                                </td></tr>';
            }
            $Addblock .= '</tbody></table></td>';
        }
        $this->PageData["Addblock"] = $Addblock;

        ##Send Email TO Customer
        $to_email =  $OrderRs[0]["bill_email"];
        $bcc= 'b1ff7c3c82@invite.trustpilot.com';
        $ReceiptMailBody = $mailTemplate[0]['mail_body'];
        $ReceiptMailBody = str_replace('{$Site_URL}',config('global.SITE_URL'),$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$TOPMENUBAR}',$topmenubar,$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$topmenubar}',$topmenubar,$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$freeshippinginfo}',$freeshippinginfo,$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$Addblock}',$Addblock,$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$orders_no}',$OrderRs[0]['orders_no'],$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$order_total}',Price($OrderRs[0]['order_total']),$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$order_datetime}',date('M d, Y',$OrderRs[0]['order_datetime']),$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$shipinfo}',$OrderRs[0]['shipinfo'],$ReceiptMailBody);

        $BillAddress = $OrderRs[0]['bill_first_name'].' '.$OrderRs[0]['bill_last_name']."<br>";
        if($OrderRs[0]['bill_address2'] != '')
            $BillAddress.= $OrderRs[0]['bill_address1'].', '.$OrderRs[0]['bill_address2']."<br>";
        else 
            $BillAddress.= $OrderRs[0]['bill_address1'].',<br>';
        $BillAddress.=$OrderRs[0]['bill_city'].', '.$OrderRs[0]['bill_state']."<br>";
        $BillAddress.=$OrderRs[0]['bill_zip'].' - '.$OrderRs[0]['bill_country'];

        $ReceiptMailBody = str_replace('{$bill_address}',$BillAddress,$ReceiptMailBody);

        $ShipAddress = $OrderRs[0]['ship_first_name'].' '.$OrderRs[0]['ship_last_name']."<br>";
        if($OrderRs[0]['ship_address2'] != '')
            $ShipAddress.= $OrderRs[0]['ship_address1'].', '.$OrderRs[0]['ship_address2']."<br>";
        else 
            $ShipAddress.= $OrderRs[0]['ship_address1'].',<br>';
        $ShipAddress.=$OrderRs[0]['ship_city'].', '.$OrderRs[0]['ship_state']."<br>";
        $ShipAddress.=$OrderRs[0]['ship_zip'].' - '.$OrderRs[0]['ship_country'];

        $ReceiptMailBody = str_replace('{$ship_address}',$ShipAddress,$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$STR_EMAIL_ITEM}',$STR_EMAIL_ITEM,$ReceiptMailBody);
        $ReceiptMailBody = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$ReceiptMailBody);

        $MailSubject = $mailTemplate[0]['subject'];
        $MailSubject = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$MailSubject);
        $MailSubject = str_replace('{$OrderRs.orders_no}',$OrderRs[0]['orders_no'],$MailSubject);
        /*if(Session::get('sess_useremail') == 'gequaldev@gmail.com')
        {
            echo $ReceiptMailBody;
            exit;
        }*/
        //SendMail($MailSubject,$ReceiptMailBody,$to_email,config('Settings.ADMIN_MAIL'),'',$bcc);
        ##Send Email TO Admin 
        //SendMail($MailSubject,$ReceiptMailBody,config('Settings.ADMIN_MAIL'),config('Settings.CONTACT_MAIL'));
        /** OMANISEND **/
        $OtherData = ['toMail' => $to_email, 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM];
        //OmanisendRequest('61fb93a4b86552001e976b3c',$OrderRs[0],$OtherData);
        $OtherData = ['toMail' => config('Settings.ADMIN_MAIL'), 'addblock' => $Addblock, 'BillAddress' => $BillAddress, 'ShipAddress' => $ShipAddress, 'STR_EMAIL_ITEM' => $STR_EMAIL_ITEM];
        
        Log::channel('order_receipt')->info('Order Receipt Email Sent for Order - '.$OrderID);
        //OmanisendRequest('61fb93a4b86552001e976b3c',$OrderRs[0],$OtherData);
        /** OMANISEND **/
        $this->PageData["GOOGLE_ORDER_TRACKING"] = $GOOGLE_ORDER_TRACKING;
        $this->PageData["GOOGLE_ORDER_TRACKING_GTM"] = '<script type="text/javascript">'.$GOOGLE_ORDER_TRACKING_GTM.'</script>';

        $GiftCouponInfo  = Session::get('ShoppingCart.GiftCoupon');
        if($GiftCouponInfo && count($GiftCouponInfo) > 0 && $GiftCouponInfo['Code'] != '' && $GiftCouponInfo['Value'] > 0 ) 
        {
            $gcRES = GiftCertificate::where('gc_code','=',$GiftCouponInfo['Code'])->where('status','=','1')->get();
            if($gcRES && $gcRES->count() > 0)
            {
                $gc_remaining_value = 0;
                $new_total = $this->GetNetTotal() + $GiftCouponInfo['Value'];
                if($new_total <= $GiftCouponInfo['Applicable_Value'])
                {
                    $gc_remaining_value = NumberFormat(($GiftCouponInfo['Applicable_Value']-$new_total));
                }

                if($GiftCouponInfo['Code'] != '' && $GiftCouponInfo['Value'] > 0 ) 
                {
                    $upgGif = array (
                                    'remaining_value' => $gc_remaining_value,
                                    'last_used_date'  => date('Y-m-d H:i:s')
                                );
                    $udpGift = GiftCertificate::where('gc_code','=',$GiftCouponInfo['Code'])->update($upgGif);
                }
                $freeshippinginfo = '';
                if(config('Settings.FREESHIPPING_VALUE')!="" && config('Settings.FREESHIPPING_VALUE') > 0)
                {
                    $freeshippinginfo .= '<strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders';
                }
                $gcRESNew = GiftCertificate::where('gc_code','=',$GiftCouponInfo['Code'])->where('status','=','1')->get();

                $res_mail = GetMailTemplate("GC_USAGE");	
                $to_recipient = $gcRES[0]['recipient_email'];
                $GC_Subject = $res_mail[0]['subject'];
                $GC_Subject = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$GC_Subject);

                $GCMailBody = $res_mail[0]['mail_body'];
                $GCMailBody = str_replace('{$freeshippinginfo}',$freeshippinginfo,$GCMailBody);
                $GCMailBody = str_replace('{$recipient_name}',$gcRES[0]['recipient_name'],$GCMailBody);
                $GCMailBody = str_replace('{$gc_code}',$GiftCouponInfo['Code'],$GCMailBody);
                $GCMailBody = str_replace('{$gc_amount}',$GiftCouponInfo['Value'],$GCMailBody);
                $GCMailBody = str_replace('{$remaining_value}',$gcRESNew[0]['remaining_value'],$GCMailBody);
                $GCMailBody = str_replace('{$TOLL_FREE_NO}',config('Settings.CONTACT_PHONE_NO'),$GCMailBody);
                $GCMailBody = str_replace('{$Site_URL}',config('global.SITE_URL'),$GCMailBody);
                $GCMailBody = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$GCMailBody);
                $GCMailBody = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$GCMailBody);

                $this->PageData["recipient_name"] = $gcRES[0]['recipient_name'];
                $this->PageData["gc_code"] = $GiftCouponInfo['Code'];
                $this->PageData["gc_amount"] = $GiftCouponInfo['Value'];
                $this->PageData["remaining_value"] = $gcRESNew[0]['remaining_value'];
                $this->PageData["TOLL_FREE_NO"] = config('global.CONTACT_PHONE_NO');
                $this->PageData['Site_URL'] = config('global.SITE_URL');
                $this->PageData["freeshippinginfo"] = $freeshippinginfo;

                //SendMail($GC_Subject,  $GCMailBody, $to_recipient, config('Settings.ADMIN_MAIL'));
                /** OMANISEND **/
                $OtherData = ['gc_code' => $GiftCouponInfo['Code'], 'gc_amount' => $GiftCouponInfo['Value'], 'remaining_value' => $gcRESNew[0]['remaining_value']];
                OmanisendRequest('61fbcf88bf58ef001efc0243',$gcRES[0],$OtherData);
                /** OMANISEND **/
            }
        }
        
		$RIO_EBAY_COMMERCE_CODE .= "_roi.push(['_trackTrans']);";
		$this->PageData['RIO_EBAY_COMMERCE_CODE'] = '<script type="text/javascript">'.$RIO_EBAY_COMMERCE_CODE.'</script>';
		
		$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
		if(config('Settings.WHOLESALE_CREDIT_LIMIT') == 'Yes' && $CreditDiscount>0 && Session::get('etype')=="M" && Session::get('is_dropshipper')!='Yes' && Session::has('sess_icustomerid')){
			$CustomerRemainingCreditAmount = Session::get('ShoppingCart.customer_remaining_credit_amount');
			$upgCustomer = array ('credit_limit' => $CustomerRemainingCreditAmount);
			$udpCL = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->update($upgCustomer);
			
			$cpay_status = 'Paid';
			if($OrderRs[0]['payment_type'] == 'PAYMENT_MOC' || $OrderRs[0]['payment_type'] == 'PAYMENT_WT' || $OrderRs[0]['payment_type'] =='PAYMENT_PAYWITHAMAZON'){
				$cpay_status = 'Unpaid';
			}
			
			$upgOrder = array ('pay_status' => $cpay_status);
			Order::where('orders_id','=',$OrderRs[0]['orders_id'])->update($upgOrder);
			
			$log_insert = array (
				'orderid' => $OrderRs[0]['orders_id'],
				'custid' => Session::get('sess_icustomerid'),
				'current_credit_limit' => $OrderRs[0]['cust_current_credit_limit'],
				'apply_credit' => $OrderRs[0]['apply_credit'],
				'remaining_credit' => $OrderRs[0]['remaining_credit']
			);
			CustomerCreditLimitLogs::create($log_insert);
			Session::put('ShoppingCart.credit_limit_discount', 0.00);
            Session::put('ShoppingCart.customer_remaining_credit_amount', 0.00);
		}
		
		if(Session::get('is_dropshipper') == 'Yes' && Session::get('eusertype') == 'Wholesaler')
		{    
			$ds_res = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->get();
			
			$DropshipperAccountDetails = $this->GetDropshipperAccountDetails();
			if($ds_res[0]['available_funds']>0 && $DropshipperAccountDetails['fund_available'] == 'Yes')
			{
				if($OrderRs[0]['payment_type'] == 'PAYMENT_DS')
				{
					$remaining_fund = $DropshipperAccountDetails['remaining_fund'];
					$upgCustomer = array ('available_funds' => $remaining_fund);
					$udpDS = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->update($upgCustomer);
				}
				$fpay_status = 'Paid';
				if($OrderRs[0]['payment_type'] == 'PAYMENT_MOC' || $OrderRs[0]['payment_type'] == 'PAYMENT_WT' ||  $OrderRs[0]['payment_type'] =='PAYMENT_PAYWITHAMAZON'){
					$fpay_status = 'Unpaid';
				}
				$upgOrder2 = array ('pay_status' => $fpay_status);
				Order::where('orders_id', '=',$OrderRs[0]['orders_id'])->update($upgOrder2);
			}
		}
		
		if(Session::has('ShoppingCart.Reward_array'))		
		{
			if(strtolower(Session::get('eusertype'))=='retailer') {
				$rewardarray_use = array();
				$rewardarray_use = Session::get('ShoppingCart.Reward_array');
			
				if(is_array($rewardarray_use) && !empty($rewardarray_use)) {
					$res_client = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->get();
					$FinalReaminRewardpoint = 0;
					if((int)$rewardarray_use['RemainRewardPoint']>0  && $rewardarray_use['RewardDiscount']>0) {
						 $FinalReaminRewardpoint = (int)$rewardarray_use['RemainRewardPoint'];
					}else {
						 $FinalReaminRewardpoint = $res_client[0]['iRewardpoint'];
					}
					$upgCustomer = array ('iRewardpoint' => $FinalReaminRewardpoint);									 
					Customer::where('customer_id','=',Session::get('sess_icustomerid'))->update($upgCustomer);
				
					if($rewardarray_use['AppliedRewardPoint'] > 0)
					{
						$InsertCustomer = array (
											'customer_id' 	=> Session::get('sess_icustomerid'),
											'note'		  	=> "Deduct Reward Point By Order",
											'iRewardpoint'	=> $rewardarray_use['AppliedRewardPoint'],
											'Order_No'		=> $OrderRs[0]["orders_no"]
										   );	 
						RewardPoint::create($InsertCustomer);
					}
				}
			}
		}
		$tempCart = Session::get('ShoppingCart.Cart');
		$cnt_row  = count($tempCart);
		$Rewardchk_arr = array();
		if($cnt_row > 0) {
			$DealTotalprice = 0;
			for($dl=0; $dl<$cnt_row; $dl++) {
				$dealofdayRS= Dealofweek::where('status','=','1')
								->where('start_date','<=',date('Y-m-d'))->where('end_date','>=',date('Y-m-d'))
								->where('product_sku','=',$tempCart[$dl]['SKU'])
								->limit(1)->get();
				if($dealofdayRS && $dealofdayRS->count()>0) {
					$DealTotalprice = $DealTotalprice+$tempCart[$dl]['TotPrice'];
				}else {
					$Rewardchk_arr[] = $tempCart[$dl]['SKU'];
				}
			}
		}
		
		if(strtolower(Session::get('eusertype'))=='retailer' && Session::get('etype') == 'M' && Session::has('ShoppingCart.RewardPointItemWiseTotal')) 
		{
			$Rewardpoint = Session::get('ShoppingCart.RewardPointItemWiseTotal');
			if($Rewardpoint>0)
			{
				$res_client = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->get();
				$FinalRewardpoint = $Rewardpoint + $res_client[0]['iRewardpoint'];
				$upgCustomer = array ('iRewardpoint' => $FinalRewardpoint);
				Customer::where('customer_id','=',Session::get('sess_icustomerid'))->update($upgCustomer);
				$InsertCustomer = array (
										'customer_id' 	=> Session::get('sess_icustomerid'),
										'note'		  	=> "Reward Point Added By Order",
										'iRewardpoint'	=> $Rewardpoint,
										'Order_No'		=> $OrderRs[0]["orders_no"]
							   );
				RewardPoint::create($InsertCustomer);
			}		
		}
		
		$cust_res1 	= Order::where('customer_id','=',Session::get('sess_icustomerid'))->get();

		if($cust_res1 && $cust_res1->count()<=0)
		{
			$cust_res = Customer::where('customer_id','=',Session::get('sess_icustomerid'))
						->where('registration_type','=','M')->where('status','=','1')->get();
		
			$referenced_by = "";
			if($cust_res && $cust_res->count()>0 )
			{ 
				$referenced_by = $cust_res[0]['referenced_by']; 
				$new_str_arr = explode('#', $referenced_by);
				$id = $new_str_arr[0];
				$Remail =  $new_str_arr[1];
			}
			if($referenced_by!='')
			{	
				$referralRes = ReferFriend::where('customer_id','=',$id)->where('receiver','=',$Remail)->limit(1)->get();
				$datetime = date('Y-m-d H:i:s');
				if($referralRes && $referralRes->count()>0) 
				{
					//Condition For Adding Referral Point First Time When Refferal Client Clicks in Link and Updating Referrel Customer Status//
					if($referralRes[0]['is_sender_notified']=='N') 
					{
						$saveData['is_sender_notified'] = 'Y';
						$saveData['refer_datetime']	 	= $datetime;       
						$where = "customer_id= '".$id."' AND receiver = '".$Remail."'";
						ReferFriend::where('customer_id','=',$id)->where('receiver','=',$Remail)->update($saveData);
				
						// Query For Updating Reward Point in Customer Table //
						$cust_res = Customer::where('customer_id','=',$id)->get();
						$reward_point = $cust_res[0]['iRewardpoint']+100;
						$custdata['iRewardpoint'] = $reward_point;
				
						$where = "customer_id= '".$id."'";
						Customer::where('customer_id','=',$id)->update($custdata);
						
						$InsertCustomer = array (
											'customer_id' 	=> $id,
											'note'		  	=> "Reward Point For Adding Referral Point First Time",
											'iRewardpoint'	=> 100,
											'Order_No'		=> $OrderRs[0]["orders_no"]
										);		 
						RewardPoint::create($InsertCustomer);
					}
				}
			}
		}
		
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			Shoppingcart::where('customer_id','=',Session::get('sess_icustomerid'))->delete();
		}
		
		$GTMDATA = ['page' => 'order_receipt', 'pagetype' => 'purchase'];
		$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);
		
		Session::forget('TOKEN');
		Session::forget('PAYPAL_PAYER_ID');
		Session::forget('token');
		Session::forget('nvpReqArray');
		Session::forget('shipping_insurance');
		Session::forget('shipping_insurance_charge');
		Session::forget('ShoppingCart');
		Session::forget('AMAZON_ACCESS_TOKEN');	
		Cookie::forget('MY_SHOP_CART_COOKIE');
        
        /** OMANISEND **/ 
      //  OmanisendRequest('removeCart',['omnisend_accountid' => Auth::user()->omnisend_accountid]);
        /** OMANISEND **/
	    Log::channel('order_receipt')->info('Session data expired for Order - '.$OrderID);
		
        $this->PageData['MainOrder'] = $OrderRs[0];
		$Charges = [];
		$Charges['shipping'] = ['field' => 'shipping_amt','label' => 'Shipping Charge'];
		$Charges['amazon_shipping'] = ['field' => 'OrderShippingCharge','label' => 'Shipping Charge'];
		$Charges['tax'] = ['field' => 'tax','label' => 'Sales Tax'];
		$Charges['amazon_tax'] = ['field' => 'OrderSalesTax','label' => 'Sales Tax'];
		$Charges['gift_charge'] = ['field' => 'gift_charge','label' => 'Gift Wrapping Charge'];
		$Charges['amazon_gift_charge'] = ['field' => 'GiftWrappingCharge','label' => 'Gift Wrapping Charge'];
		$Charges['shipping_insurance'] = ['field' => 'route_shipping_insurance_charge','label' => 'Shipping Insurance Charge'];
		$Charges['shipping_signature'] = ['field' => 'shipping_signature','label' => 'Shipping Signature'];
		
		$Discounts = [];
		$AutoDiscountLabel = (config('Settings.AUTO_DISCOUNT_FLAG') != '' ? config('Settings.AUTO_DISCOUNT_FLAG') : 'Auto Discount');
		$QuantityDiscountLabel = (config('Settings.QUANTITY_DISCOUNT_FLAG') != '' ? config('Settings.QUANTITY_DISCOUNT_FLAG') : 'Quantity Discount');
		$BogoDiscountLabel = (config('Settings.BOGO_DISCOUNT_FLAG') != '' ? config('Settings.BOGO_DISCOUNT_FLAG') : 'Bogo Discount');
		$Discounts['auto_discount'] = ['field' => 'auto_discount','label' => $AutoDiscountLabel];
		$Discounts['quantity_discount'] = ['field' => 'quantity_discount','label' => $QuantityDiscountLabel];
		$Discounts['coupon_amount'] = ['field' => 'coupon_amount','label' => 'Coupon Discount'];
		$Discounts['gc_amount'] = ['field' => 'gc_amount','label' => 'Gift Certificate Discount'];
		$Discounts['refer_amount'] = ['field' => 'refer_amount','label' => 'Refer Discount'];
		$Discounts['bogo_discount'] = ['field' => 'bogo_discount','label' => $BogoDiscountLabel];
		$Discounts['apply_credit'] = ['field' => 'apply_credit','label' => 'Credit Discount'];
		$Discounts['reward_discount'] = ['field' => 'reward_discount','label' => 'Reward Discount'];
		
		$this->PageData['AllCharges'] = $Charges;
		$this->PageData['AllDiscounts'] = $Discounts;
		$this->PageData['OrderDetails'] = $ProdDetails;
		
        Log::channel('order_receipt')->info('Order Receipt Ended for Order - '.$OrderID);
        Log::channel('order_receipt')->info('--------------------------------');
		//return view('checkout.'.$Template)->with($this->PageData);
        return view('checkout.order-receipt')->with($this->PageData);
	}
	
	public function CustomerAddFund(Request $request)
	{
		$Page = '';
		if(isset($request->page_from) && $request->page_from != '')
		{
			$Page = $request->page_from;
		}
		if(isset($request->fund_type) && $request->fund_type != '')
		{
			if($request->fund_type == 'card')
			{
				return redirect('stripe/addfund/'.$Page);
				exit;
			}
		}
	}
	
	public function PaypalFundResponse(Request $request)
	{
		if(isset($request->status) && $request->status == 'Completed'){	
			$msg = "Fund added to your account successfully.";
			Session::flash('fund_msg',$msg);
		}else{
			$msg = "Unable to process payment.";
			Session::flash('fund_msg',$msg);
		}
		
		if(isset($request->pagefrom) && $request->pagefrom == 'dropshipfund'){
			return redirect('/dropshipper-fund-summary.html');
		}elseif(isset($request->pagefrom) && $request->pagefrom == 'billing'){			
			return redirect('/billing');
		} else {
			return redirect('/shoppingcart');
		}
	}
	
	public function PaypalFundProcess(Request $request)
	{
		$fp = fopen(config('global.PHYSICAL_PATH').'paypal_response.txt', 'a');  
		fwrite($fp, 'CustomerID : '.$request->uid.'\n');  
		fwrite($fp, 'Payment Status : '.$request->payment_status.'\n');  
		fwrite($fp, 'Payment Gross : '.$request->payment_gross.'\n');  
		fwrite($fp, 'TXNID : '.$request->txn_id.'\n');  
		fwrite($fp, '--------------------------------\n');  
		fclose($fp);  
		
		if(isset($request->uid) && $request->uid != '')
		{
			if(isset($request->payment_status) && $request->payment_status == 'Completed')
			{        
				$fundsres = Customer::where('customer_id', '=', $request->uid)->get();
				$insertArray = array(
					"customer_id" => $request->uid,
					"cust_available_fund" => $fundsres[0]['available_funds'],
					"cust_requested_fund" => $request->payment_gross,
					"paypal_ipn_response" => serialize($request->all()),
					"payment_status"      => $request->payment_status,
					"txn_id"              => $request->txn_id
				);        
				
				$result = PaypalIpnLog::create($insertArray);        
				
				if($result){
					$total_available_funds = $fundsres[0]['available_funds'] + $request->payment_gross;
					$custdata = array(
						"available_funds"=>$total_available_funds
					);
					Customer::where('customer_id','=',$request->uid)->update($custdata);           
				}
			}
		}
	}
	
	public function AmazonCheckout(Request $request)
	{
		$this->PageData['CSSFILES'] = ['shoppingcart.css','checkout.css'];	
		$this->PageData['JSFILES'] = ['billing.js'];		
		
		if(!Session::has('ShoppingCart.Cart') || count(Session::get('ShoppingCart.Cart')) == 0)
			return redirect('/shoppingcart');
		$this->PageData['meta_title'] = "Billing and Shipping Information :: ".config('Settings.SITE_TITLE');
		$this->SetupCart();
		$GTMDATA = ['page' => 'billing_amazon', 'pagetype' => 'cart'];
		$this->PageData['GTMDATA'] = $this->GoogleTagManager($GTMDATA);
		return view('checkout.amazon-checkout')->with($this->PageData);
	}
	
	public function AmazonFundCheckout(Request $request)
	{
		$this->PageData['CSSFILES'] = ['shoppingcart.css','checkout.css'];	
		$this->PageData['JSFILES'] = ['billing.js'];		
		
		$this->PageData['meta_title'] = "Billing and Shipping Information :: ".config('Settings.SITE_TITLE');
		$this->SetAmazonConfig('fund');
		return view('checkout.amazon-fund-checkout')->with($this->PageData);
	}
	
	public function ApplyFreeGift($GiftValue,$GiftFrom,$GiftTo,$GiftMessage)
	{
		Session::put('ShoppingCart.FreeGift',$GiftValue);
		//============Added Code Date 7-Feb-2015 Start Here ==============//
		Session::put('ShoppingCart.GiftFrom', $GiftFrom);
		Session::put('ShoppingCart.GiftTo', $GiftTo);
		Session::put('ShoppingCart.GiftMessageCustomer', $GiftMessage);
		//============Added Code Date 7-Feb-2015 End Here ==============//
		$Msg =  "Free Gift is applied successfully";
		return response()->json(['success' => '1', 'message' => $Msg]);
	}
	
	public function GetShippingOptionsJson(Request $request)
	{
		if($request->zip != ""){
			$ship_zip = $request->zip;
			$ship_state = $request->state;
			$ship_country = $request->country;
		} else {
			if(Auth::user())
			{
				$ship_zip = Auth::user()->zip;
				$ship_state = Auth::user()->state;
				$ship_country = Auth::user()->country;
			} else {
				$ship_zip = '10080';
				$ship_state = 'NY';
				$ship_country = 'US';
			}
		}
		$shipping_modes = ShippingMode::where('status','=','1')->orderBy('display_position')->get();
		$show_shipping_modes = 0;
		$shipping_mode_tmp_arr = [];
		if($shipping_modes && $shipping_modes->count($shipping_modes) > 0)
		{
			foreach($shipping_modes as $shipping_mode)
			{
				$shipping_charge = $this->CalculateShippingCharge($ship_zip,$ship_state,"US",$shipping_mode->shipping_mode_id,"Yes");					
				if($shipping_charge > 0 )
				{
					$shipping_mode_tmp_arr[] = array(
						'id'=>(string)$shipping_mode->shipping_mode_id,
						'label'=>strip_tags($shipping_mode->type),
						'detail'=>strip_tags($shipping_mode->type),
						'amount'=>round($shipping_charge*100)
					);
				}
			}
		}
		return response()->json(['shippingmodes' => $shipping_mode_tmp_arr]);
	}
	public function StripButtonResponse(Request $request)
	{
		if(isset($request->paymentMethod))
		{
			Stripe::setApiKey(env('STRIPE_KEY'));
			$MethodRes = \Stripe\PaymentMethod::retrieve($request->paymentMethod);
			$Wallet = "Credit Card";
			if(isset($MethodRes->card['wallet']['type']))
			{
				if($MethodRes->card['wallet']['type'] == 'google_pay')
					$Wallet = "Google Pay";
				if($MethodRes->card['wallet']['type'] == 'apple_pay')
					$Wallet = "Apple Pay";
			}
			Session::put('StripePaymentType',$Wallet);
			Session::put('PayMethodRes',json_encode($MethodRes));
		}
		$Status = "fail";
		$CustomerEmail = $request->payerEmail;;
		if(isset($request->shippingAddress))
		{
			$Billing = $request->shippingAddress;
			$newrequest = [
				'bill_country' => $Billing['country'],
				'bill_fname' => $request->payerName,
				'bill_lname' => $request->payerName,
				'bill_company' => $Billing['organization'],
				'bill_address1' => $Billing['addressLine'][0],
				'bill_address2' => (isset($Billing['addressLine'][1])?$Billing['addressLine'][1]:''),
				'bill_city' => $Billing['city'],
				'bill_state' => $Billing['region'],
				'bill_zip' => $Billing['postalCode'],
				'bill_phone' => $Billing['phone'],
				'bill_email' => $CustomerEmail,
				'bill_cemail' => $CustomerEmail,
				'sameasbill' => 'Yes'
			];
			
			$this->SetBillingAddress($newrequest);
			$this->SetShippingAddress($newrequest);
			
			$ShippingOption = $request->shippingOption;
			$ShipCharge = $ShippingOption['amount'] / 100;
			Session::put('ShoppingCart.Shipping.ShippingMethodID',$ShippingOption['id']);
			Session::put('ShoppingCart.Shipping.ShippingMethodName',$ShippingOption['label']);
			Session::put('ShoppingCart.Shipping.ShippingCharge',$ShipCharge);
			Session::put('ShoppingCart.Shipping.ShippingDays',$ShippingOption['detail']);
			$Status = 'success';
		}
		return response()->json(['status' => $Status]);
	}

	public function SetCartForStripe(Request $request)
	{
		$this->SetShippingInsuranceCharge('remove');
		$this->SetupCart();
		$this->SetShippingInsuranceCharge('add');
		$CartItems = $this->GetCartForStripe();
		$NetTotal = $this->GetNetTotal();
		return response()->json(['items' => $CartItems, 'NetTotal' => ($NetTotal*100)]);
	}
	
	public function GetClientSecret(Request $request)
	{
		$clientSecret = "";
		if($this->GetNetTotal() > 0 )
		{
			$NetTotal = $this->GetNetTotal();
			Stripe::setApiKey(env('STRIPE_KEY'));
			$intent = \Stripe\PaymentIntent::create([
			  'amount' => round($NetTotal * 100),
			  'currency' => 'usd',
			]);
			if($intent && isset($intent->client_secret))
				$clientSecret = $intent->client_secret;
		}
		return response()->json(['clientSecret' => $clientSecret]);
	}
	
	public function CheckMailTemplate()
	{
		$topmenubar = '<a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'fragrances/cid/1" target="_blank">Fragrances</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'skincare/cid/18" target="_blank">Skincare</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'makeup/cid/30" target="_blank">Makeup</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'bath-body/cid/12" target="_blank">Bath &amp; Body</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>		
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'at-home/cid/15" target="_blank">At Home</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'sunglasses/cid/68" target="_blank">Sunglasses</a>
						   <span class="hide">&nbsp;&nbsp; &nbsp;&nbsp;</span>	
						   <a class="nav-one-third" style="color:#fff; text-decoration:none;" href="'.config('global.SITE_URL').'perfumesale/p4u/special-sl/view" target="_blank">Sale</a>';

		$mailTemplate = GetMailTemplate("ORDER_RECEIPT_NEW");
		$ReceiptMailBody = $mailTemplate[0]['mail_body'];
		$ReceiptMailBody = str_replace('{$Site_URL}',config('global.SITE_URL'),$ReceiptMailBody);
		$ReceiptMailBody = str_replace('{$TOPMENUBAR}',$topmenubar,$ReceiptMailBody);
		dd($ReceiptMailBody);
	}
    
    public function SetOmnisendCart(Request $request)
    {
        if(isset($request->omnisendContactID) && $request->omnisendContactID != '')
        {
            $CartData = OmanisendRequest('getCart',['omnisend_accountid' => $request->omnisendContactID]);
            if(isset($CartData['products']) && count($CartData['products']) > 0)
            {
                $ArrMyShopCart = $CartData['products'];

                Session::put("RemoveItem",'');
                $RemoveItem = '';
                for ($p = 0; $p < count($ArrMyShopCart); $p++) {
                    $prod_sku = strtolower(trim($ArrMyShopCart[$p]['sku']));
                    $quantity = (int) $ArrMyShopCart[$p]['quantity'];
                    if($ArrMyShopCart[$p]['sku'] == config('global.GIFT_CERTIFICATE_SKU') || $ArrMyShopCart[$p]['sku'] == config('global.GIFT_CERTIFICATE_SKU1'))
                    {
                        /*
                        $data['gc_value']     	= $ArrMyShopCart[$p]['price'];
                        $data['recipient_name']	= $ArrMyShopCart[$p]['RecipientName'];
                        $data['recipient_email']	= $ArrMyShopCart[$p]['RecipientEmail'];
                        $data['subject']			= $ArrMyShopCart[$p]['Subject'];
                        $data['message']			= $ArrMyShopCart[$p]['Message'];
                        $data['signature']		= $ArrMyShopCart[$p]['Signature'];
                        $data['deliverydate']		= $ArrMyShopCart[$p]['DeliveryDate'];
                        $data['yourname']			= $ArrMyShopCart[$p]['YourName'];
                        $data['youremail']		= $ArrMyShopCart[$p]['YourEmail'];
                        $data["GiftImage"]		= $ArrMyShopCart[$p]['GiftImage'];

                        if($data['gc_value'] >= config('Settings.MINIMUM_GIFTCERTIFICATE_AMOUNT') && $data['gc_value'] <= config('Settings.MAXIMUM_GIFTCERTIFICATE_AMOUNT'))
                        {
                            $CartRequest->merge($data);
                            $this->insertGiftCertificate($CartRequest);	
                        }
                        */
                    }else{
                        $ProductRs = Products::where('status','=','1')->where(DB::raw('lower(sku)'),'=',$prod_sku)->get();
                        if($ProductRs && $ProductRs->count() > 0)
                        {
                            $ProductRs = $this->SetProduct($ProductRs[0]);
                            if($ProductRs->product_price > 0 && ($ProductRs->current_stock > 0 || ($ProductRs->cosmo_current_stock > 0 && $ProductRs->cosmo_sku!='') || ($ProductRs->nandansons_current_stock > 0 && $ProductRs->nandansons_sku!='')))
                            {
                                $RemoveItem.= $prod_sku.",";
                                $products_id = $ProductRs->products_id;
                                $this->AddToCart($products_id,$quantity,'Yes');
                            }
                        } else {
                            continue;
                        }
                    }
                }
                //$this->StoreShopCartInCookie();
                Session::put("RemoveItem",substr($RemoveItem,0,-1));
            }
        }
        return redirect('/shoppingcart');
    }
}
