<?php 
namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Afterpay\SDK\MerchantAccount as AfterpayMerchantAccount;
use Afterpay\SDK\HTTP\Request\GetConfiguration as AfterpayGetConfigurationRequest;
use App\Http\Controllers\Traits\EncryptTrait;
		
use App\Http\Controllers\Traits\VendorTrait;
use App\Http\Controllers\Traits\CommonTrait;
use App\Models\Products;
use App\Models\ProductsCategory;
use App\Models\Customer;
use App\Models\Coupon;
use App\Models\Category;
use App\Models\AutoDiscount;
use App\Models\QuantityDiscount;
use App\Models\Manufacture;
use App\Models\GiftCertificate;
use App\Models\Shoppingcart;
use App\Models\BogoDiscount;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\FreeGiftProduct;
use App\Models\FreegiftBrand;
use App\Models\ShippingRule;
use App\Models\RewardRule;
use App\Models\RewardPoint;
use DB;
use Session;
use Cookie;

trait CartTrait
{	
	use VendorTrait;
	use CommonTrait;
	use EncryptTrait;

	public function ShowCart()
	{
		$ShoppingCart = [];
		if(Session::has('ShoppingCart'))
		{
			$ShoppingCart = Session::get('ShoppingCart');
			/*
			$CartAttr = $this->SetCartAttributes();
			$ShoppingCart['IsPaypalExpressCheckout'] = $CartAttr['IsPaypalExpressCheckout'];
			$ShoppingCart['Amazon_pay_Checkout'] = $CartAttr['Amazon_pay_Checkout'];
			$ShoppingCart['Afterpay_Checkout'] = $CartAttr['Afterpay_Checkout'];
			*/
		}
		return $ShoppingCart;
	}
	
	public function AddToCart($products_id, $qty = 1,$cookiee='No',$Omniflag='Yes',$YotpoFreeGift = 'No')
	{
		//Session::forget('ShoppingCart');
		
		$ProductChkStock = $this->ProductCheckInStock($products_id, $qty,"insert",$cookiee);	
		$CartErrors = [];
		if($ProductChkStock == '1111')
			$CartErrors[] = config('message.Cart.ProductNotAvailable');
		if($ProductChkStock == '2222')
			$CartErrors[] = config('message.Cart.QuantityNotAvailable');
		if(count($CartErrors) > 0)
		{
			Session::flash('CartErrors', $CartErrors);
			return response()->json(array('Added' => 0,'CartErrors' => $CartErrors));
		}
	
		if($cookiee=='Yes')
			$ProductChkFlg = $this->ProductCheckInCart($products_id, $qty,'insert',$cookiee);
		else
			$ProductChkFlg = $this->ProductCheckInCart($products_id, $qty);
		
		if($ProductChkFlg==1)
		{
			$a = $this->CalculateSubTotal();
            /** OMANISEND **/ 
            if($Omniflag == 'Yes'){
                OmanisendRequest('setCart',['CartData' => Session::get('ShoppingCart')]);
            }
            /** OMANISEND **/
			return response()->json(array('Added' => 0));
		}
		/*
		if($YotpoFreeGift == 'Yes')
        {
            $this->FreeGiftInsertProductValue($products_id,$products_id);
            return response()->json(array('Added' => 1));
        }
        */
		$per = 0;
		$val = 0;
		if(Session::has('eusertype') && strtolower(Session::get('eusertype'))=='wholesaler')
		{
			if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
			{
				$specialpricedtl = GetSpecialPricePercentandValue($qty);
				$perval = explode("#",$specialpricedtl);
				$per = $perval[0];
				$val = $perval[1];
			}
		}
		
		$ProdInfo = DB::table('pu_products as p')
					->join('pu_products_one as po','p.products_id','=','po.products_id')
					->where(function($query){
						$query->orWhere('p.status','=','1');
						$query->OrWhere(function($qry){
							$qry->where('p.status','=','2')->where('po.is_private','=','Yes')->where('po.private_code','!=','');	
						});
					})
					->where('p.products_id','=',$products_id)->get();
		if(!$ProdInfo || $ProdInfo->count() == 0 )
		{
			return response()->json(array('Added' => 0));
		}
		
		
		
		$ProductRs = $this->SetProduct($ProdInfo[0]);

		$CodeVal = "";
		if($ProductRs->private_code!='' && $ProductRs->is_private == 'Yes' && $ProductRs->status == '2')
		{
			$CodeVal = $ProductRs->private_code;
		}
		## Here Overwrite sale Price Field
		$ProductRs->sale_price = $ProductRs->product_price;
		$actual_product_price = $ProductRs->product_price;

		$ProductRs->IsDealProducts = 'No';
		$ProductRs->DealDiscountFlag = 'No';
		
		// $ProductRs->sku = strtoupper($ProductRs->sku);	
		$ProductRs->ItemPrice = NumberFormat($ProductRs->sale_price);
		$DealOfWeek = GetDealOfWeek($ProductRs->sku,'Weekly','Cart');
		if(count($DealOfWeek) > 0)
		{
			if($DealOfWeek[$ProductRs->sku]['deal_price']!='' && $DealOfWeek[$ProductRs->sku]['deal_price'] < $ProductRs->sale_price )
			{
				$dealprice = NumberFormat($DealOfWeek[$ProductRs->sku]['deal_price']);
				$ProductRs->sale_price = $dealprice;
				$ProductRs->ItemPrice  = $dealprice;
				if($DealOfWeek[$ProductRs->sku]['description']!='')
				{
					$ProductRs->short_description = $DealOfWeek[$ProductRs->sku]['description'];
				}
			}
			$ProductRs->DealDiscountFlag = $DealOfWeek[$ProductRs->sku]['discount_coupon_flag'];
			$ProductRs->IsDealProducts = 'Yes';
		}
		/*
		$DailyDeal = GetDealOfWeek($ProductRs->sku,'Daily','Cart');
		if(count($DailyDeal) > 0)
		{
			if($DailyDeal[$ProductRs->sku]['deal_price']!='' && $DailyDeal[$ProductRs->sku]['deal_price'] < $ProductRs->sale_price )
			{
				$dealprice = NumberFormat($DailyDeal[$ProductRs->sku]['deal_price']);
				$ProductRs->sale_price = $dealprice;
				$ProductRs->ItemPrice  = $dealprice;
				if($DailyDeal[$ProductRs->sku]['description']!='')
				{
					$ProductRs->short_description = $DailyDeal[$ProductRs->sku]['description'];
				}
			}
			$ProductRs->DealDiscountFlag = $DailyDeal[$ProductRs->sku]['discount_coupon_flag'];
			$ProductRs->IsDealProducts = 'Yes';
		}*/
		
		if(file_exists(config('global.PRD_THUMB_IMG_PATH').stripslashes($ProductRs->image)) && !empty($ProductRs->image))
			$thumb_image = config('global.PRD_THUMB_IMG_URL').rawurlencode($ProductRs->image);
		else
			$thumb_image = config('global.NO_IMAGE_THUMB');
		
		$ProductRs->prod_image ='<img src="'.$thumb_image.'" border="0" width="125" />';
		$ProductRs->image_forpopup ='<img src="'.$thumb_image.'" border="0" width="75" />';
		$ProductRs->billing_image ='<img src="'.$thumb_image.'" border="0" width="195" alt="'.$ProductRs->product_name.'" title="'.$ProductRs->product_name.'"/>';
		
		$p_link = $this->getProductRewriteURL($ProductRs->products_id, $ProductRs->product_name);
		if($CodeVal!='')
			$p_link = $p_link."/".$CodeVal;
		
		$IsCosmo = "No";
		$IsNandansons = "No";
		$IsPerfumePW  = "No";
		$IsPCA  = "No";
		$VendorSKU = "";
		
		if(Session::has('eusertype') && strtolower(Session::get('eusertype'))=='wholesaler')
		{
			$cosmo_our_price = $ProductRs->cosmo_wholesale_price;
			$nandansons_our_price = $ProductRs->nandansons_wholesale_price;
			$perfumeworldwide_our_price = $ProductRs->perfumeworldwide_wholesale_price;
			$pca_our_price = $ProductRs->pca_wholesale_price;
		} else {
			$cosmo_our_price = $ProductRs->cosmo_our_price;
			$nandansons_our_price = $ProductRs->nandansons_our_price;
			$perfumeworldwide_our_price = $ProductRs->perfumeworldwide_our_price;
			$pca_our_price = $ProductRs->pca_our_price;
		}
		if($ProductRs->WebsiteStock == "Out")
		{
			if($ProductRs->cosmo_sku!='' &&  $ProductRs->cosmo_current_stock > 0 &&  $cosmo_our_price > 0)
			{
				$IsCosmo = "Yes";
				$VendorSKU = $ProductRs->cosmo_sku;
			}
			else if($ProductRs->pca_sku!='' &&  $ProductRs->pca_current_stock > 0 && $pca_our_price > 0)
			{
				$IsPCA  = "Yes";
				$VendorSKU = $ProductRs->pca_sku;
			}
			else if($ProductRs->nandansons_sku!='' &&  $ProductRs->nandansons_current_stock > 0 && $nandansons_our_price > 0)
			{
				$IsNandansons = "Yes";
				$VendorSKU = $ProductRs->nandansons_sku;
			}
		}
		$temp_ary = array();
		$temp_ary['ProductID']   		= $ProductRs->products_id;
		$temp_ary['SKU']         		= $ProductRs->sku;
		$temp_ary['ProductName'] 		= stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$ProductRs->product_name));
		$temp_ary['short_description'] 	= strip_tags(stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$ProductRs->short_description)));
		$temp_ary['Billing_Image'] 		= $ProductRs->billing_image;
		$temp_ary['IsDealProducts']		= $ProductRs->IsDealProducts;
		$temp_ary['DealDiscountFlag']	= $ProductRs->DealDiscountFlag;
		$temp_ary['IsGiftWrapProduct']	= $ProductRs->is_gift_wrap;
		$temp_ary['VendorSKU']			= $VendorSKU;
		$temp_ary['IsCosmo']			= $IsCosmo;
		$temp_ary['IsNandansons']		= $IsNandansons;
		$temp_ary['IsPerfumePW']		= $IsPerfumePW;
		$temp_ary['IsPCA']				= $IsPCA;
		$temp_ary['ImanufactureID']		= $ProductRs->imanufactureid;
		$ProductName_description 		= $ProductRs->product_name.' '.$ProductRs->short_description;
		
		
		if($ProductRs->WebsiteStock == "In")
		{
			$temp_ary['IsMaxaromaTwoDelivery'] = $ProductRs->maxtwodaydelivery;
		}
		if($ProductRs->shipping_weight == "Normal")
		{
			$temp_ary['shipping_weightVal'] = 'Normal';
		}
		if($ProductRs->shipping_weight == "Light")
		{
			$temp_ary['shipping_weightVal'] = 'Light';
		}
		if($ProductRs->shipping_weight == "Heavy")
		{
			$temp_ary['shipping_weightVal'] = 'Heavy';
		}
		
		if(Session::has('eusertype') && strtolower(Session::get('eusertype'))=='wholesaler')
		{
			$SpecialPriceDetails = '';	
			if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
			{
				if($per > 0)
					$ProductRs->sale_price = $ProductRs->sale_price - $ProductRs->sale_price* $per/100;
				$SpecialPriceDetails = getWholesalerSpecialPricesDetails($actual_product_price);
			}
		}
		
		if(strlen($ProductName_description) > 34)
			$temp_ary['ProductName_description'] = substr($ProductName_description,0,34).'...';
		else
			$temp_ary['ProductName_description'] = $ProductName_description;

		

		## set check out process price
        $SalePrice = NumberFormat($ProductRs->sale_price);
        $TotalPrice = NumberFormat($SalePrice*$qty);
        $ItemPrice = NumberFormat($ProductRs->ItemPrice);
        $YotpoFreeGiftCoupon = ""; 
        $IsYotpoFreeProduct = 'No';
        if($YotpoFreeGift == 'Yes')
        {
            $SalePrice = 0;
            $TotalPrice = 0;
            $ItemPrice = 0;
            $YotpoFreeGiftCoupon = config('YotpoFreeGiftCoupon'); 
            $IsYotpoFreeProduct = 'Yes';
        }
        $temp_ary['IsYotpoFreeProduct'] = $IsYotpoFreeProduct;
        $temp_ary['ItemPrice'] = $ItemPrice;
		$temp_ary['Price']       	= $SalePrice;
		$temp_ary['Qty'] 		 	= $qty;
		$temp_ary['TotPrice']    	= $TotalPrice;
		$temp_ary['Image']       	= $ProductRs->prod_image;
		$temp_ary['Prod_URL']       = $p_link;
		$temp_ary['image_forpopup'] = $ProductRs->image_forpopup;
		$temp_ary['Product_Type'] 	= $ProductRs->product_type;
		$temp_ary['gift_wrap']		= 'No';
		
		if($ProductRs->point_multiplier <=0)
		{
			$ProductRs->point_multiplier = 0;
		}
		$temp_ary['RewardItemWise'] = $temp_ary['TotPrice'] * $ProductRs->point_multiplier;
		$temp_ary['RewardItemWise'] = NumberFormat($temp_ary['RewardItemWise']);
		$temp_ary['PointMultipier'] = $ProductRs->point_multiplier;
		
		if(Session::has('eusertype') && strtolower(Session::get('eusertype'))=='wholesaler')
		{
			$temp_ary['Markup_Percent']		  = $per;
			$temp_ary['Markup_Value']		  = $val;
			$temp_ary['SpecialPriceDetails']  = $SpecialPriceDetails;
			$temp_ary['ActualWholesalePrice'] = $actual_product_price;
		}
	
		$temp_ary['HandlingTimeStr'] = '';
		if($ProductRs->WebsiteStock == "Out" && $ProductRs->stock == "In" && ($temp_ary['IsCosmo']=="Yes" || $temp_ary['IsPCA']=="Yes" || $temp_ary['IsNandansons']=="Yes"))
		{
			$temp_ary['HandlingTimeStr'] = "2 Business Days Handling Time";
		}
		
		if($temp_ary['Price'] <= 0 && $YotpoFreeGift == 'No'){
			return response()->json(array('Added' => 0));
		}
		
		$Cart = Session::get('ShoppingCart.Cart');
		if($Cart && count($Cart) > 0)
			$Cart = array_values($Cart);
		$Cart[] = $temp_ary;
		Session::put('ShoppingCart.Cart',$Cart);
		Session::put('ShoppingCart.YotpoFreeGiftCoupon',$YotpoFreeGiftCoupon);
		if(!Session::has('eusertype') && $temp_ary['Product_Type'] == 'wholesaler')
		{
			Session::forget('ShoppingCart.Cart');
		}
		if(Session::has('eusertype') && strtolower(Session::get('eusertype'))=='wholesaler' && $temp_ary['Product_Type'] == 'retailer')
		{
			Session::forget('ShoppingCart.Cart');
		}
		if(Session::has('eusertype') && strtolower(Session::get('eusertype'))=='retailer' && $temp_ary['Product_Type'] == 'wholesaler')
		{
			Session::forget('ShoppingCart.Cart');
		}
		
		$a = $this->CalculateSubTotal();
		
		$AllDiscounts = $this->GetAllDiscounts();
		$TotalValue = NumberFormat(Session::get('ShoppingCart.SubTotal')) - $AllDiscounts['TotalDiscount'];
		$Gift_Free_In_Cart = $this->CheckFreeGiftInCart($TotalValue);

		if($Gift_Free_In_Cart == 'No')
		{
			$this->SetFreegift($Gift_Free_In_Cart);
		}
		
		if(isset($ProductRs->products_id))
		{
			/** OMANISEND **/ 
            if($Omniflag == 'Yes'){
                OmanisendRequest('setCart',['CartData' => Session::get('ShoppingCart')]);
            }
            //OmanisendRequest('setCart',$ProductRs,['quantity' => $qty,'CartData' => Session::get('ShoppingCart'),'imageUrl' => $thumb_image, 'prodLink' => $p_link]);
			/** OMANISEND **/
            
			return response()->json(array('Added' => 1));
		}
        
		return response()->json(array('Added' => 0));
	}
	
	public function ProductCheckInStock($Product_id,$qty=1,$opt,$cookiee='No')
	{
		//Below condition for Gift Wrap and gift certificate (Do not check stock)
		if($Product_id==0)
		{
			return 3333;
		}
		$ProdInfo = DB::table('pu_products as p')
						->join('pu_products_one as po','p.products_id','=','po.products_id')
						->where(function($query){
							$query->orWhere('p.status','=','1');
							$query->OrWhere(function($qry){
								$qry->where('p.status','=','2')->where('po.is_private','=','Yes')->where('po.private_code','!=','');	
							});
						})
						->where('p.products_id','=',$Product_id)->get();
		if(!$ProdInfo || $ProdInfo->count() == 0)
			return 1111;
		
		if($cookiee=='Yes' && $opt == "insert")
		{
			$originalquantity = $this->ProductStockInCart($Product_id);

			if( $originalquantity > $qty)
			{
				$productQuantity = $qty + $originalquantity;
			}
			else
			{
				$productQuantity = $originalquantity;
			}
		}

		if($cookiee=='No')
		{
			if($opt=="insert")
			{
				$productQuantity = $this->ProductStockInCart($Product_id) + $qty;
			}
			else
			{
				$productQuantity = $qty;
			}
		}
		$ProductStock = $this->SetProduct($ProdInfo[0]);
		$availableStock =  $ProductStock->current_stock - $ProductStock->minimum_stock;
		
		return ($productQuantity > $availableStock)?2222:3333;
	}
	
	public function ProductCheckInCart($products_id, $qty, $opt = 'insert',$cookiee='No',$giftwrap='No')
	{
		$Cart = Session::get('ShoppingCart.Cart');
		$ProductInCart = 0;
		if(Session::has('ShoppingCart.Cart') && count($Cart) > 0)
		{
			if($qty == 0 )
				$qty = 1 ;
			for($a=0; $a < count($Cart); $a++)
			{
				if($Cart[$a]['ProductID'] == $products_id && $products_id != 0 && !isset($Cart[$a]['IS_Free_Gift']))
				{
					if($opt == 'insert')
					{
						if($cookiee=='Yes')
						{
							if($Cart[$a]['Qty'] > $qty)
							{
								$Cart[$a]['Qty'] += $qty;
							}
							else
							{
								$Cart[$a]['Qty'] = $qty;
							}
						}
						else
						{
							$Cart[$a]['Qty'] += $qty;
						}
					}
					else
					{
						$Cart[$a]['Qty'] = $qty;
					}

					if(Session::has('eusertype') && strtolower(Session::get('eusertype'))=='wholesaler')
					{
						$per = '';
						$val = '';
						if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
						{
							$specialpricedtl = GetSpecialPricePercentandValue($Cart[$a]['Qty']);
							$perval = explode("#",$specialpricedtl);
							$per = $perval[0];
							$val = $perval[1];
						}
						
						if(!isset($Cart[$a]['ActualWholesalePrice']))
							$Cart[$a]['ActualWholesalePrice'] = $Cart[$a]['Price'];
						
						if($per != '')
						{
							$Cart[$a]['Price'] = NumberFormat($Cart[$a]['ActualWholesalePrice'] - $Cart[$a]['ActualWholesalePrice']*$per/100);
						}
						else
						{
							$Cart[$a]['Price']  = NumberFormat($Cart[$a]['ActualWholesalePrice']);
						}

						$Cart[$a]['Markup_Percent'] = $per;
						$Cart[$a]['Markup_Value'] = $val;
					}

					$Cart[$a]['TotPrice'] = NumberFormat($Cart[$a]['Qty'] * $Cart[$a]['Price']);
					$Cart[$a]['gift_wrap'] = $giftwrap;
					$ProductInCart = 1;
				}
			}
		}
		if($ProductInCart == 1)
		{
			Session::put('ShoppingCart.Cart',$Cart);
			return true;
		} else {
			return false;
		}
	}
	
	public function ProductStockInCart($Product_id)
	{
		$cart_qty=0;
		if(Session::has('ShoppingCart.Cart'))
		{
			$count = count(Session::get('ShoppingCart.Cart'));
			$Cart = Session::get('ShoppingCart.Cart');
			for($a=0; $a < $count; $a++)
			{
				if($Cart[$a]['ProductID'] == $Product_id && $Product_id != 0)
				{
					$cart_qty +=$Cart[$a]['Qty'];
				}
			}
		}
		return $cart_qty;
	}
	
	public function setGiftCertiTotalUpdate($val, $qty)
	{
		if(Session::has('ShoppingCart.GiftCertiTotal'))
		{
			$GiftCertiTotal = Session::get("GiftCertiTotal") + $val;
			$GiftCertiCount = Session::get("GiftCertiCount") + $qty;
		} else {
			$GiftCertiTotal = $val;
			$GiftCertiCount = $qty;
		}
		Session::put("ShoppingCart.GiftCertiTotal",$GiftCertiTotal);
		Session::put("ShoppingCart.GiftCertiCount",$GiftCertiCount);
	}
	
	public function ApplyCouponDiscount($couponCode, $customer_id = NULL)
	{
		if(Session::has('ShoppingCart.PromoCoupon'))
			Session::forget('ShoppingCart.PromoCoupon');
		if(Session::has('Niche_Fragrances_Membership'))
			Session::forget('Niche_Fragrances_Membership');
				
				
				
		$error = 0;
		$CouponDiscount  = 0.0 ;
		$couponCode 	 = trim($couponCode);
		$customer_id 	 = (int)$customer_id;
		$FreeShippingFlg = false;
		$CartInfo 		 = Session::get('ShoppingCart.Cart');
		$TotalItems 	 = count($CartInfo);
		
		$CouponQry = Coupon::where('coupon_number','=',$couponCode)
							->where('status','=','1')
							->where('start_date','<=',DB::raw('curdate()'))
							->where('end_date','>=',DB::raw('curdate()'));
		if(Auth::user())
		{
			if(Auth::user()->eusertype !='')
				$CouponQry->where('coupon_user_type','=',Auth::user()->eusertype);
			else 				
				$CouponQry->where('coupon_user_type','=','Retailer');
		} else {
			$CouponQry->where('coupon_user_type','=','Retailer');
		}
		
		$CouponRS = $CouponQry->get();
		
		$IsDeal="Yes";
		$TotalDealPrice = 0;
		if($CouponRS && $CouponRS->count() > 0 )
		{
			foreach($CartInfo as $i => $Cart)
			{
				if((isset($Cart["IsDealProducts"]) && $Cart["IsDealProducts"]=="No") || (isset($Cart["IsDealProducts"]) && $Cart["IsDealProducts"]=="Yes" && ($Cart["DealDiscountFlag"]=="Yes" ||  (isset($CouponRS[0]["dealdiscount_flag"]) && $CouponRS[0]["dealdiscount_flag"]=="Yes"))))
					$IsDeal = "No";
				
				if((isset($Cart["IsDealProducts"]) && $Cart["IsDealProducts"]=="Yes") && ($Cart["DealDiscountFlag"]=="No" || $Cart["DealDiscountFlag"]=='') && $CouponRS[0]["dealdiscount_flag"]=="No")
					$TotalDealPrice =  $TotalDealPrice  + $Cart["TotPrice"];	
					
			}
			
			
			if($IsDeal == "Yes")
			{
				$CouponDiscount = 0;
				$couponCode='';
				$msg = "Coupon code does not apply to the item you have in your bag.";
				$error = 1;
				$Info = ['error' => $error, 'message' => $msg];
				return $Info;
			}
			if(trim($couponCode) == '' )
				$CouponDiscount = 0;
					
			if($CouponRS && $CouponRS->count() > 0)
			{
				if($CouponRS[0]["autodiscount_flag"]=='No')
				{
					Session::put('ShoppingCart.AutoDiscount',0.0);
					Session::put('ShoppingCart.AutoDiscountFlag', '');
				}
				if($CouponRS[0]["bogodiscount_flag"]=='No')
				{
					Session::put('ShoppingCart.DogoDiscount', 0.0);
					Session::put('ShoppingCart.BogoDiscountFlag','');
				}
				if($CouponRS[0]["quantitydiscount_flag"]=='No')
				{
					Session('ShoppingCart.QuantityDiscount', 0.0);
					Session('ShoppingCart.QuantityDiscountFlag','');
				}
			
				if($CouponRS && $CouponRS->count() <= 0)
					Session::put('ShoppingCart.PromoCoupon.CouponCode','');
			
				if(Session::has('ShoppingCart.PromoCoupon.CouponCode') && Session::get('ShoppingCart.PromoCoupon.CouponCode') == '' && $CouponRS[0]["allow_free_gift_product"] == "Yes" && $CouponRS[0]["free_gift_product_value"] != '')
					$this->RemoveFreeGiftValueProduct($CouponRS[0]["free_gift_product_value"]);
			}	
			
			$TotalExcludePrice = 0;
			$ExcludeSKUListArr = [];
			if($TotalItems > 0 && $CouponRS && $CouponRS->count() > 0 && trim($CouponRS[0]["exclude_sku"])!='')
			{
				$ExcludeSKUListArr = [];
				$ExcludeSKUListArr  = explode(",",$CouponRS[0]["exclude_sku"]);
				$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
				$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
				
				foreach($CartInfo as $i => $Cart)
				{
					if(in_array($Cart["SKU"],$ExcludeSKUListArr))
						$TotalExcludePrice =  $TotalExcludePrice  + $Cart["TotPrice"];
				}
			}
			
			$GiftCertiTotal = 0;
			if(Session::has('ShoppingCart.GiftCertiTotal'))
				$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
			$SubTotal = Session::get('ShoppingCart.SubTotal'); 
			$subTotal = NumberFormat($SubTotal - $GiftCertiTotal - $TotalDealPrice - $TotalExcludePrice);
			$shippingCharge = $this->GetShippingCharge();
			
			$gc_certi_total = 0;
			if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['count_gc_purchase'] == '0' && Session::has('ShoppingCart.GiftCertiTotal'))
				$gc_certi_total = Session::get('ShoppingCart.GiftCertiTotal');
			
			$SubTotal = Session::get('ShoppingCart.SubTotal');
			$GrandTotal = $SubTotal - $TotalDealPrice - $TotalExcludePrice;
			$GrandTotalSale = $SubTotal - $TotalDealPrice - $TotalExcludePrice;

			if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['count_ship_tax'] == '1')
			{
				$TaxValue = $this->GetAllCharges('TaxValue');
				$GrandTotal = ($GrandTotal - $gc_certi_total) + $shippingCharge + $TaxValue;
				$GrandTotalSale = ($GrandTotalSale  - $gc_certi_total) + $shippingCharge + $TaxValue;
			}else{
				$GrandTotal = $GrandTotal - $gc_certi_total;
				$GrandTotalSale = $GrandTotalSale - $gc_certi_total;
			}
			
			Session::put("count_ship_tax",0);
			Session::put("coupon_per",0);
			
			
			if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['is_once'] == '1')
			{ // only one time use
				$sqlorder = Order::where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])->get();
				if($sqlorder && $sqlorder->count() > 0 )
					$switchCase = '';
				else
					$switchCase = $CouponRS[0]['orders'];
			}else if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['is_once'] == '2' && Session::get('sess_icustomerid') != 0){ 
				// Once per customer
				
				$sqlorder = Order::where('customer_id','=',Session::get('sess_icustomerid'))->where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])->get();
				
				if($sqlorder && $sqlorder->count() > 0 )
				{
					$switchCase = '';
				}else{
					
					if(Session::get('etype') == "G")
					{
						$Billing  = Session::get('ShoppingCart.BillingAddress');
						$sqlorder = Order::select('orders_id')->where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])
									->where('bill_email','=',$Billing['email'])->get();
						if($sqlorder && $sqlorder->count() > 0 )
							$switchCase = '';
						else
							$switchCase = $CouponRS[0]['orders'];
					}else{
						$switchCase = $CouponRS[0]['orders'];
					}
				}
			}else{
				$switchCase = $CouponRS[0]['orders'];
			}
			
			switch ($switchCase)
			{
				## On Order Amount
				case '0' :
					$tempsubTotal = $GrandTotal;
					$tempSaleTotal = $GrandTotalSale;
					// Added code on 17 July 2012
					if($CouponRS[0]['count_ship_tax']=='1'){
						// Added by CK on 7th Feb, 2012 for Sale Item Coupon
						$tempSaleTotal=$tempSaleTotal;
						Session::put("count_ship_tax",1);
					}else{
						$tempsubTotal = $SubTotal;
						$tempSaleTotal = Session::get('ShoppingCart.SubTotal') - $gc_certi_total - $TotalDealPrice - $TotalExcludePrice;
						$tempSaleTotal = $tempSaleTotal;
					}
					if($tempSaleTotal >= $CouponRS[0]['order_amount'])
					{
						if($CouponRS[0]['type'] == 1 )
							$CouponDiscount = ( $tempSaleTotal * ($CouponRS[0]['discount']/100) );
						else
							$CouponDiscount = $CouponRS[0]['discount'];
					}
					else
					{
						$msg = "Coupon code does not apply to the item you have in your bag.";
						$error = 1;
					}

					if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
					{
						Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
						Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
						Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
						$FreeShippingFlg = true;
					}

					if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0)
					{
						$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
					}
					break;
				## On Product SKU
				case '1' :
					$CouponSKU = trim($CouponRS[0]['sku']);
					########### For Multiple SKU ###############
					$arr_CouponSKU  = explode(",",$CouponSKU);
					$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
					$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');

					$Matched_Item_Total = 0;
					$IS_Any_Matched 	= 0;
					
					if(is_array($arr_CouponSKU) and !empty($arr_CouponSKU))
					{
						$tempCart  = Session::get('ShoppingCart.Cart');
						for ($a=0; $a<count($tempCart); $a++)
						{
							if(in_array( $tempCart[$a]['SKU'] , $arr_CouponSKU) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
							{
								$IS_Any_Matched = $IS_Any_Matched+1;
								if($CouponRS[0]['type'] == 1 )
								{
									$Matched_Item_Total = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+ $Matched_Item_Total;
								}
							}
						}
					}
					if($IS_Any_Matched >0 )
					{
						if($CouponRS[0]["count_ship_tax"]=='1')
						{
							if($CouponRS[0]['type'] == 1 )
							{
								if(Session::has('ShoppingCart.TaxValue') && Session::get('ShoppingCart.TaxValue') > 0)
									$Matched_Item_Total = $Matched_Item_Total + Session::get('ShoppingCart.TaxValue');
								if($shippingCharge > 0)
									$Matched_Item_Total = $Matched_Item_Total + $shippingCharge;
							}
						}
						if($CouponRS[0]["count_gc_purchase"]=='1')
						{
							$GiftCertiTotal = 0;
							if(Session::has('ShoppingCart.GiftCertiTotal'))
								$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
							$Matched_Item_Total = $Matched_Item_Total + $GiftCertiTotal;
						}

						if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
						{
							if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($Matched_Item_Total * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = ($CouponRS[0]['discount'] * $IS_Any_Matched);
							}
						}
						elseif($Matched_Item_Total >= $CouponRS[0]['minimum_order_amount'])
						{
							if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($Matched_Item_Total * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = ($CouponRS[0]['discount'] * $IS_Any_Matched);
							}
						}
						else
						{
							$CouponDiscount = 0;
							$msg = "Coupon code is invalid or does not exists.";
							$error = 1;
						}
					}
					else
					{
						$msg = "Coupon code is invalid or does not exists.";
						$error = 1;
					}
					if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
					{
						Session::put('ShoppingCart.PromoCoupon.FreeShipping', 'Yes');
						Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
						Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
						$FreeShippingFlg = true;
					}
					if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0)
					{

						$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
					}
					####################################################
					break;
				case '7' :
						$CouponSKU = trim($CouponRS[0]['sku']);
						
						$CouponSKU = unserialize($CouponSKU); 
						$arr_CouponSKU = array();
						$arr_CouponDiscount = array();
						for($d=0;$d<count($CouponSKU);$d++)
						{
							if($CouponSKU[$d]["sku"]!='')
							{
								$arr_CouponSKU[] = $CouponSKU[$d]["sku"];
								$arr_CouponDiscount[$CouponSKU[$d]["sku"]] = $CouponSKU[$d]["discount"];
							}
						}
						########### For Multiple SKU ###############
						$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
						$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');
					//	echo "<pre>"; print_r($arr_CouponSKU); exit;	
						
						$Matched_Item_Total = 0;
						$IS_Any_Matched 	= 0;
						$CouponDiscountCalculate	= 0;	
						$CouponDiscount = 0;
						if(is_array($arr_CouponSKU) and !empty($arr_CouponSKU))
						{
							$tempCart  = Session::get('ShoppingCart.Cart');
							for ($a=0; $a<count($tempCart); $a++)
							{
								if(in_array( $tempCart[$a]['SKU'] , $arr_CouponSKU) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{
									$IS_Any_Matched = $IS_Any_Matched+1;
									$Current_Item_Total  = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']);
									$Matched_Item_Total = $Current_Item_Total + $Matched_Item_Total;
									
									$CouponDiscountCalculate = ($Current_Item_Total *($arr_CouponDiscount[$tempCart[$a]['SKU']] /100));
									$CouponDiscountCalculate = $this->NumberFormat($CouponDiscountCalculate);
									$CouponDiscount = $CouponDiscount + $CouponDiscountCalculate;
									//item wise discount for cj
									$tempCart[$a]['ItemWiseCouponDiscount_CJ'] = $CouponDiscountCalculate;
									//item wise discount for cj
								}
							}
							Session::put('ShoppingCart.Cart',$tempCart);
						}

						if($IS_Any_Matched >0 )
						{
							if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
							{
								$CouponDiscount = $CouponDiscount;
							}
							elseif($Matched_Item_Total >= $CouponRS[0]['minimum_order_amount'])
							{
								$CouponDiscount = $CouponDiscount;
							}
							else
							{
								$CouponDiscount = 0;
								$msg = "Coupon code is invalid or does not exists.";
								$error = 1;
							}
						}
						else
						{
							$msg = "Coupon code is invalid or does not exists.";
							$error = 1;
						}

						if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
						{
							Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
							$FreeShippingFlg = true;
						}
						if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0)
						{
							$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
						}
						####################################################
						break;	
				## On Product Brand
				case '2' :
						break;
				## On Product Category
				case '3' :
						$CouponCatID    	= trim($CouponRS[0]['sku']); // Category IDS
						$arr_CouponCatID    = explode(",",$CouponCatID);

						$CouponDiscount = 0;
						$found_cat = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_CatID = Category::where('status','=','1')->whereIn('category_id',$arr_CouponCatID)->get();
						$arr_active_CatID = array();
						if($Res_active_CatID && $Res_active_CatID->count() > 0)
						{
							for($h=0;$h<$Res_active_CatID->count();$h++)
							{
								$arr_active_CatID[] = $Res_active_CatID[$h]['category_id'];
							}
						}
						
						if(count($arr_active_CatID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart  	    = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}
							
							$ProdIds = ProductsCategory::select('products_id')->distinct()
										->whereIn('category_id',$arr_active_CatID)
										->whereIn('products_id',$temp_prod_id)
										->get();
							$cat_prod_id  = array();
							for ($a=0; $a < $ProdIds->count(); $a++)
							{
								$cat_prod_id[$a] = $ProdIds[$a]['products_id'];
							}

							for ($a=0; $a<count($tempCart); $a++)
							{
								if (in_array( $tempCart[$a]['ProductID'] , $cat_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{
									if($CouponRS[0]['type'] == 1 )
									{
										$CouponDiscount = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']) + $CouponDiscount;
									}
									$found_cat = true; // make true if category match
								}
							}
						}
						else
						{
							$CouponDiscount = 0;
						}
						if($found_cat==true)
						{
							if($CouponRS[0]["count_ship_tax"]=='1')
							{
								if($CouponRS[0]['type'] == 1 )
								{
									if(Session::has('ShoppingCart.TaxValue') && Session::get('ShoppingCart.TaxValue') > 0)
										$CouponDiscount  = $CouponDiscount  + Session::has('ShoppingCart.TaxValue');
									if($shippingCharge > 0)
										$CouponDiscount  = $CouponDiscount  + $shippingCharge;
								}
						  }
						  if($CouponRS[0]["count_gc_purchase"]=='1')
						  {
								$GiftCertiTotal = 0;
								if(Session::has('ShoppingCart.GiftCertiTotal'))
									$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
								$CouponDiscount  = $CouponDiscount  + $GiftCertiTotal;
						  }
						  if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
						  {
								if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
						  }
						  elseif($CouponDiscount >= $CouponRS[0]['minimum_order_amount'])
						  {
							  if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
						  }
						  else
						  {
							  $CouponDiscount = 0;
							  $msg = "Coupon code is invalid or does not exists.";
							  $error = 1;
						  }
						}
						if($found_cat==false)
						{
							$msg = "Coupon code does not apply to the item you have in your bag.";
							$error = 1;
						}

						if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0 && $found_cat==true)
						{
							Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID',explode(",",$CouponRS[0]["free_shipping_value"]));
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
							$FreeShippingFlg = true;
						}
						if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0 && $found_cat==true)
						{
							$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
						}
						break;

				case '5' :
							break;

				## On Product Brand
				case '6' :
						$CouponBrandID    	= trim($CouponRS[0]['sku']); // Brand IDS
						$arr_CouponBrandID  = explode(",",$CouponBrandID);
						
						$CouponDiscount = 0;
						$found_brand = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_BrandID = Manufacture::where('status','=','1')
												->whereIn('imanufactureid',$arr_CouponBrandID)->get();
						$arr_active_BrandID = array();
						for($h=0;$h<count($Res_active_BrandID);$h++)
						{
							$arr_active_BrandID[] = $Res_active_BrandID[$h]['imanufactureid'];
						}
							
						if(count($arr_active_BrandID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}
							
							$ProdIds = Products::select('products_id')->distinct()
										->whereIn('imanufactureid',$arr_active_BrandID)
										->whereIn('products_id',$temp_prod_id)
										->get();
							$brand_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$brand_prod_id[$a] = $ProdIds[$a]['products_id'];
							}

							for ($a=0; $a<count($tempCart); $a++)
							{
								if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{
									if($CouponRS[0]['type'] == 1 )
									{
										$CouponDiscount = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+$CouponDiscount;
									}
									else
									{
										$CouponDiscount = $CouponRS[0]['discount']  ;
									}
									$found_brand = true; // make true if category match
								}
							}
						}
						else
						{
							$CouponDiscount = 0;
						}

						if($found_brand==true)
						{

						  if($CouponRS[0]["count_ship_tax"]=='1')
						  {
							if($CouponRS[0]['type'] == 1 )
							{
								if(Session::has('ShoppingCart.TaxValue') && Session::get('ShoppingCart.TaxValue') > 0)
									$CouponDiscount  = $CouponDiscount  + Session::has('ShoppingCart.TaxValue');
								if($shippingCharge > 0)
									$CouponDiscount  = $CouponDiscount  + $shippingCharge;
							}
						  }
						  if($CouponRS[0]["count_gc_purchase"]=='1')
						  {
							$GiftCertiTotal = 0;
							if(Session::has('ShoppingCart.GiftCertiTotal'))
								$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
							$CouponDiscount  = $CouponDiscount  + $GiftCertiTotal;
						  }
							if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
							{
								if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
							}
							elseif($CouponDiscount >= $CouponRS[0]['minimum_order_amount'])
							{
								if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
							}
							else
							{
							  $CouponDiscount = 0;
							  $msg = "Coupon code is invalid or does not exists.";
							  $error = 1;
							}

						}

						if($found_brand==false)
						{
							$msg = "Coupon code does not apply to the item you have in your bag.";
							$error = 1;
						}

						if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0 && $found_brand==true)
						{
							Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID',explode(",",$CouponRS[0]["free_shipping_value"]));
							Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
							$FreeShippingFlg = true;
						}
						if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0 && $found_brand==true)
						{
							$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
						}
						break;

				## For Free Shipping
				case '4' :
						$CouponDiscount = 0;
						$Total_Item_count_val  = Session::get('ShoppingCart.TotalItemInCart');

						if($CouponRS[0]['minimum_order_amount'] == 0.00 ||  $CouponRS[0]['minimum_order_amount'] == 0)
						{
							if($Total_Item_count_val >= $CouponRS[0]['total_free_shipping'])
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.FreeShippingModeID',$ShippingID);

								if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
								{
									Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
									$FreeShippingFlg = true;
								}
								if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
								{
									$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
								}
								$FreeShippingFlg = true;
							}
							else if($Total_Item_count_val==0 ||$Total_Item_count_val =='')
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.FreeShippingModeID',$ShippingID);

									if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
									{
										Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
										Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
										Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
										$FreeShippingFlg = true;
									}
									if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
									{
										$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
									}
									$FreeShippingFlg = true;
							}
							else
							{
								$FreeShippingFlg = false;
							}
					   }

					  elseif(Session::get('ShoppingCart.SubTotal') >= $CouponRS[0]['minimum_order_amount'])
						{
							if($Total_Item_count_val >= $CouponRS[0]['total_free_shipping'])
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.FreeShippingModeID',$ShippingID);

								if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
								{
									Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
									$FreeShippingFlg = true;
								}
								if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
								{
									$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
								}
								$FreeShippingFlg = true;
							}
							else if($Total_Item_count_val==0 ||$Total_Item_count_val =='')
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.FreeShippingModeID',$ShippingID);

								if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
								{
									Session::put('ShoppingCart.PromoCoupon.FreeShipping','Yes');
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
									Session::put('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
									$FreeShippingFlg = true;
								}
								if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
								{
									$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
								}
								$FreeShippingFlg = true;
							}
							else
							{
								$FreeShippingFlg = false;
							}
					   }
					   else
					   {
							$msg = "Coupon code does not apply to the item you have in your bag.";
							$error = 1;
							$FreeShippingFlg = false;
					   }
					break;
				default :
						$CouponDiscount = 0;
						$couponCode='';
						$msg = "Coupon code does not apply to the item you have in your bag.";
						$error = 1;
						break;
			}
			if($FreeShippingFlg==false)
			{
				Session::put('ShoppingCart.PromoCoupon.FreeShipping','No');
			}
			$CouponDiscount = NumberFormat($CouponDiscount);
			$msg='';
			if($CouponDiscount > 0 or $FreeShippingFlg==true)
			{
				Session::put('ShoppingCart.PromoCoupon.CouponCode',$couponCode);	
				Session::put('ShoppingCart.Coupon_Detail_CJ.CouponCode',$couponCode);
				Session::put('ShoppingCart.Coupon_Detail_CJ.orders',$CouponRS[0]['orders']);
			}
			else
			{
				Session::put('ShoppingCart.PromoCoupon.CouponCode','');
			}

			if($CouponDiscount > 0 )
			{
				Session::put('ShoppingCart.PromoCoupon.FirstCouponDiscount',$CouponDiscount);
			}
			else
			{
				Session::put('ShoppingCart.PromoCoupon.FirstCouponDiscount',0);
			}
			if(Session::get('ShoppingCart.PromoCoupon.CouponCode') !='' && Session::get('ShoppingCart.PromoCoupon.FirstCouponDiscount') > 0)
			{
				$msg = "Coupon code applied successfully.";
			}
			else
			{
					$error = 1;
					$msg = "Coupon code not found.";
			}	
		} else {
			$error = 1;
			$msg = "Coupon code not found.";
		}
			$Info = ['error' => $error, 'message' => $msg];
			return $Info;
	}
	
	public function ApplyCouponDiscountSecond($couponCode, $customer_id = NULL)
	{
		if(Session::get('ShoppingCart.AutoDiscountFlag') == "No" && Session::get('ShoppingCart.AutoDiscountFlag') !='')
		{
			$CouponDiscount = 0;
			$couponCode='';
			$msg = "Coupon code does not apply to the item you have in your bag.";
			Session:flash('CartError',$msg);
			return null;
		}
		if(Session::get('ShoppingCart.QuantityDiscountFlag') == "No" && Session::get('ShoppingCart.QuantityDiscountFlag') !='')
		{
			$CouponDiscount = 0;
			$couponCode='';
			$msg = "Coupon code does not apply to the item you have in your bag.";
			Session::flash('CartError',$msg);
			return null;
		}
		
		$CouponDiscount  = 0.0 ;
		$couponCode 	 = trim($couponCode);
		$customer_id 	 = (int)$customer_id;
		$FreeShippingFlg = false;
		$CartInfo 		 = Session::get('ShoppingCart.Cart');
		$TotalItems 	 = count($CartInfo);
		
		$CouponQry = Coupon::where('coupon_number','=',$couponCode)
							->where('status','=','1')
							->where('start_date','<=',DB::raw('curdate()'))
							->where('end_date','>=',DB::raw('curdate()'));
		if(Auth::user())
		{
			if(Auth::user()->eusertype !='')
				$CouponQry->where('coupon_user_type','=',Auth::user()->eusertype);
			else 				
				$CouponQry->where('coupon_user_type','=','Retailer');
		} else {
			$CouponQry->where('coupon_user_type','=','Retailer');
		}
		
		$CouponRS = $CouponQry->get();
		$IsDeal="Yes";
		$TotalDealPrice = 0;
		
		foreach($CartInfo as $i => $Cart)
		{
			if((isset($Cart[$i]["IsDealProducts"]) && $Cart[$i]["IsDealProducts"]!="" && ($Cart[$i]["DealDiscountFlag"]=="Yes" ||  $CouponRS[0]["dealdiscount_flag"]=="Yes")))
				$IsDeal = "No";
			
			if(isset($Cart[$i]["IsDealProducts"])&& $Cart[$i]["IsDealProducts"]=="Yes" && ($Cart[$i]["DealDiscountFlag"]=="No" || $Cart[$i]["DealDiscountFlag"]=='') && $CouponRS[0]["dealdiscount_flag"]=="No")
				$TotalDealPrice =  $TotalDealPrice  + $Cart[$i]["TotPrice"];	
		}
		if($IsDeal == "Yes")
		{
			$CouponDiscount = 0;
			$couponCode='';
			$msg = "Coupon code does not apply to the item you have in your bag.";
			return response()->json(array('error' => 1,'Message' => $msg));
		}
		if(trim($couponCode) == '' )
			$CouponDiscount = 0;
		
		if($CouponRS && $CouponRS->count() <= 0)
			Session::put('ShoppingCart.PromoCoupon.SecondPromoCoupon','');
		
		if(Session::has('ShoppingCart.PromoCoupon.SecondPromoCoupon') && Session::get('ShoppingCart.PromoCoupon.SecondPromoCoupon') == '' && $CouponRS[0]["allow_free_gift_product"] == "Yes" && $CouponRS[0]["free_gift_product_value"] != '')
			$this->RemoveFreeGiftValueProduct($CouponRS[0]["free_gift_product_value"]);
		
		$TotalExcludePrice = 0;
		if($TotalItems > 0 && $CouponRS && $CouponRS->count() > 0 && trim($CouponRS[0]["exclude_sku"])!='')
		{
			$ExcludeSKUListArr = array();
			$ExcludeSKUListArr  = explode(",",$CouponRS[0]["exclude_sku"]);
			$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
			$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');

			foreach($CartInfo as $i => $Cart)
			{
				if(in_array($Cart[$i]["SKU"],$ExcludeSKUListArr))
					$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$i]["TotPrice"];
			}
		}
		$GiftCertiTotal = 0;
		if(Session::has('ShoppingCart.GiftCertiTotal'))
			$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
		$SubTotal = Session::get('ShoppingCart.SubTotal'); 
		$subTotal = $this->NumberFormat($SubTotal - $GiftCertiTotal - $TotalDealPrice - $TotalExcludePrice);
		$shippingCharge = $this->GetShippingCharge();
		
		$gc_certi_total = 0;
		if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['count_gc_purchase'] == '0' && Session::has('ShoppingCart.GiftCertiTotal'))
			$gc_certi_total = Session::get('ShoppingCart.GiftCertiTotal');
		
		$SubTotal = Session::get('ShoppingCart.SubTotal');
		$GrandTotal = $SubTotal - $TotalDealPrice - $TotalExcludePrice;
		$GrandTotalSale = $SubTotal - $TotalDealPrice - $TotalExcludePrice;

		if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['count_ship_tax'] == '1')
		{
			$TaxValue = $this->GetAllCharges('TaxValue');
			$GrandTotal = ($GrandTotal - $gc_certi_total) + $shippingCharge + $TaxValue;
			$GrandTotalSale = ($GrandTotalSale  - $gc_certi_total) + $shippingCharge + $TaxValue;
		}else{
			$GrandTotal = $GrandTotal - $gc_certi_total;
			$GrandTotalSale = $GrandTotalSale - $gc_certi_total;
		}
		
		Session::put("count_ship_tax",0);
		Session::put("coupon_per",0);
		
		if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['is_once'] == '1')
		{ // only one time use
			$sqlorder = Order::where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])->get();
			if($sqlorder && $sqlorder->count() > 0 )
				$switchCase = '';
			else
				$switchCase = $CouponRS[0]['orders'];
		}else if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['is_once'] == '2' && $customer_id != 0){ 
			// Once per customer
			$sqlorder = Order::where('customer_id','=',$customer_id)->where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])->get();
			if($sqlorder && $sqlorder->count() > 0 )
			{
				$switchCase = '';
			}else{
				if(Auth::user() && Session::get('etype') == "G")
				{
					$Billing  = Session::get('ShoppingCart.BillingAddress');
					$sqlorder = Order::select('orders_id')->where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])
								->where('bill_email','=',$Billing['email'])->get();
					if($sqlorder && $sqlorder->count() > 0 )
						$switchCase = '';
					else
						$switchCase = $CouponRS[0]['orders'];
				}else{
					$switchCase = $CouponRS[0]['orders'];
				}
			}
		}else{
			$switchCase = $CouponRS[0]['orders'];
		}
		
		switch ($switchCase)
		{
			## On Order Amount
			case '0' :
				$tempsubTotal = $GrandTotal;
				$tempSaleTotal = $GrandTotalSale;
				// Added code on 17 July 2012
				if($CouponRS[0]['count_ship_tax']=='1'){
					// Added by CK on 7th Feb, 2012 for Sale Item Coupon
					$tempSaleTotal=$tempSaleTotal;
					Session::put("count_ship_tax",1);
				}else{
					$tempsubTotal = $SubTotal;
					$tempSaleTotal = Session::get('ShoppingCart.SubTotal') - $gc_certi_total - $TotalDealPrice - $TotalExcludePrice;
					$tempSaleTotal = $tempSaleTotal;
				}
				if($tempSaleTotal >= $CouponRS[0]['order_amount'])
				{
					if($CouponRS[0]['type'] == 1 )
						$CouponDiscount = ( $tempSaleTotal * ($CouponRS[0]['discount']/100) );
					else
						$CouponDiscount = $CouponRS[0]['discount'];
				}
				else
				{
					$msg = "Coupon code does not apply to the item you have in your bag.";
					Session::flash('CartError',$msg);
				}

				if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
				{
					Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
					Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
					Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
					$FreeShippingFlg = true;
				}

				if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0)
				{
					$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
				}
				break;
			## On Product SKU
			case '1' :
				$CouponSKU = trim($CouponRS[0]['sku']);
				########### For Multiple SKU ###############
				$arr_CouponSKU  = explode(",",$CouponSKU);
				$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
				$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');

				$Matched_Item_Total = 0;
				$IS_Any_Matched 	= 0;
				
				if(is_array($arr_CouponSKU) and !empty($arr_CouponSKU))
				{
					$tempCart  = Session::get('ShoppingCart.Cart');
					for ($a=0; $a<count($tempCart); $a++)
					{
						if(in_array( $tempCart[$a]['SKU'] , $arr_CouponSKU) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
						{
							$IS_Any_Matched = $IS_Any_Matched+1;
							if($CouponRS[0]['type'] == 1 )
							{
								$Matched_Item_Total = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+ $Matched_Item_Total;
							}
						}
					}
				}
				if($IS_Any_Matched >0 )
				{
					if($CouponRS[0]["count_ship_tax"]=='1')
					{
						if($CouponRS[0]['type'] == 1 )
						{
							if(Session::has('ShoppingCart.TaxValue') && Session::get('ShoppingCart.TaxValue') > 0)
								$Matched_Item_Total = $Matched_Item_Total + Session::get('ShoppingCart.TaxValue');
							if($shippingCharge > 0)
								$Matched_Item_Total = $Matched_Item_Total + $shippingCharge;
						}
					}
					if($CouponRS[0]["count_gc_purchase"]=='1')
					{
						$GiftCertiTotal = 0;
						if(Session::has('ShoppingCart.GiftCertiTotal'))
							$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
						$Matched_Item_Total = $Matched_Item_Total + $GiftCertiTotal;
					}

					if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
					{
						if($CouponRS[0]['type'] == 1)
						{
							$CouponDiscount = ($Matched_Item_Total * ($CouponRS[0]['discount']/100));
						}
						else
						{
							 $CouponDiscount = ($CouponRS[0]['discount'] * $IS_Any_Matched);
						}
					}
					elseif($Matched_Item_Total >= $CouponRS[0]['minimum_order_amount'])
					{
						if($CouponRS[0]['type'] == 1)
						{
							$CouponDiscount = ($Matched_Item_Total * ($CouponRS[0]['discount']/100));
						}
						else
						{
							 $CouponDiscount = ($CouponRS[0]['discount'] * $IS_Any_Matched);
						}
					}
					else
					{
						$CouponDiscount = 0;
						$msg = "Coupon code is invalid or does not exists.";
						Session::flash('CartError',$msg);
					}
				}
				else
				{
					$msg = "Coupon code is invalid or does not exists.";
					Session::flash('CartError',$msg);
				}
				if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
				{
					Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping', 'Yes');
					Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
					Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
					$FreeShippingFlg = true;
				}
				if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0)
				{

					$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
				}
				####################################################
				break;
			case '7' :
					$CouponSKU = trim($CouponRS[0]['sku']);
					
					$CouponSKU = unserialize($CouponSKU); 
					$arr_CouponSKU = array();
					$arr_CouponDiscount = array();
					for($d=0;$d<count($CouponSKU);$d++)
					{
						if($CouponSKU[$d]["sku"]!='')
						{
							$arr_CouponSKU[] = $CouponSKU[$d]["sku"];
							$arr_CouponDiscount[$CouponSKU[$d]["sku"]] = $CouponSKU[$d]["discount"];
						}
					}
					########### For Multiple SKU ###############
					$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
					$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');
				//	echo "<pre>"; print_r($arr_CouponSKU); exit;	
					
					$Matched_Item_Total = 0;
					$IS_Any_Matched 	= 0;
					$CouponDiscountCalculate	= 0;	
					$CouponDiscount = 0;
					if(is_array($arr_CouponSKU) and !empty($arr_CouponSKU))
					{
						$tempCart  = Session::get('ShoppingCart.Cart');
						for ($a=0; $a<count($tempCart); $a++)
						{
							if(in_array( $tempCart[$a]['SKU'] , $arr_CouponSKU) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
							{
								$IS_Any_Matched = $IS_Any_Matched+1;
								$Current_Item_Total  = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']);
								$Matched_Item_Total = $Current_Item_Total + $Matched_Item_Total;
								
								$CouponDiscountCalculate = ($Current_Item_Total *($arr_CouponDiscount[$tempCart[$a]['SKU']] /100));
								$CouponDiscountCalculate = $this->NumberFormat($CouponDiscountCalculate);
								$CouponDiscount = $CouponDiscount + $CouponDiscountCalculate;
								//item wise discount for cj
								$tempCart[$a]['ItemWiseCouponDiscount_CJ'] = $CouponDiscountCalculate;
								//item wise discount for cj
							}
						}
						Session::put('ShoppingCart.Cart',$tempCart);
					}

					if($IS_Any_Matched >0 )
					{
						if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
						{
							$CouponDiscount = $CouponDiscount;
						}
						elseif($Matched_Item_Total >= $CouponRS[0]['minimum_order_amount'])
						{
							$CouponDiscount = $CouponDiscount;
						}
						else
						{
							$CouponDiscount = 0;
							$msg = "Coupon code is invalid or does not exists.";
							Session::flash('CartError',$msg);
						}
					}
					else
					{
						$msg = "Coupon code is invalid or does not exists.";
						Session::flash('CartError',$msg);
					}

					if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
					{
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
						$FreeShippingFlg = true;
					}
					if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0)
					{
						$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
					}
					####################################################
					break;	
			## On Product Brand
			case '2' :
					break;
			## On Product Category
			case '3' :
					$CouponCatID    	= trim($CouponRS[0]['sku']); // Category IDS
					$arr_CouponCatID    = explode(",",$CouponCatID);

					$CouponDiscount = 0;
					$found_cat = false; // Use for if coupon valid but category not found in cart;

					## Get Active Cat ID
					$Res_active_CatID = Category::where('status','=','1')->whereIn('category_id',$arr_CouponCatID)->get();
					$arr_active_CatID = array();
					if($Res_active_CatID && $Res_active_CatID->count() > 0)
					{
						for($h=0;$h<$Res_active_CatID->count();$h++)
						{
							$arr_active_CatID[] = $Res_active_CatID[$h]['category_id'];
						}
					}
					
					if(count($arr_active_CatID) > 0 )
					{
						## Get Cart Prod ID
						$tempCart  	    = Session::get('ShoppingCart.Cart');
						$temp_prod_id   = array();

						for ($a=0; $a<count($tempCart); $a++)
						{
							$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
						}
						
						$ProdIds = ProductsCategory::select('products_id')->distinct()
									->whereIn('category_id',$arr_active_CatID)
									->whereIn('products_id',$temp_prod_id)
									->get();
						$cat_prod_id  = array();
						for ($a=0; $a<count($ProdIds); $a++)
						{
							$cat_prod_id[$a] = $ProdIds[$a]['products_id'];
						}

						for ($a=0; $a<count($tempCart); $a++)
						{
							if (in_array( $tempCart[$a]['ProductID'] , $cat_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
							{
								if($CouponRS[0]['type'] == 1 )
								{
									$CouponDiscount = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']) + $CouponDiscount;
								}
								$found_cat = true; // make true if category match
							}
						}
					}
					else
					{
						$CouponDiscount = 0;
					}
					if($found_cat==true)
					{
						if($CouponRS[0]["count_ship_tax"]=='1')
						{
							if($CouponRS[0]['type'] == 1 )
							{
								if(Session::has('ShoppingCart.TaxValue') && Session::get('ShoppingCart.TaxValue') > 0)
									$CouponDiscount  = $CouponDiscount  + Session::has('ShoppingCart.TaxValue');
								if($shippingCharge > 0)
									$CouponDiscount  = $CouponDiscount  + $shippingCharge;
							}
					  }
					  if($CouponRS[0]["count_gc_purchase"]=='1')
					  {
							$GiftCertiTotal = 0;
							if(Session::has('ShoppingCart.GiftCertiTotal'))
								$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
							$CouponDiscount  = $CouponDiscount  + $GiftCertiTotal;
					  }
					  if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
					  {
							if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = $CouponRS[0]['discount'];
							}
					  }
					  elseif($CouponDiscount >= $CouponRS[0]['minimum_order_amount'])
					  {
						  if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = $CouponRS[0]['discount'];
							}
					  }
					  else
					  {
						  $CouponDiscount = 0;
						  $msg = "Coupon code is invalid or does not exists.";
						  Session::flash('CartError',$msg);
					  }

					}
					if($found_cat==false)
					{
						$msg = "Coupon code does not apply to the item you have in your bag.";
						Session::flash('CartError',$msg);
					}

					if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0 && $found_cat==true)
					{
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID',explode(",",$CouponRS[0]["free_shipping_value"]));
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
						$FreeShippingFlg = true;
					}
					if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0 && $found_cat==true)
					{
						$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
					}
					break;

			case '5' :
				        break;

			## On Product Brand
			case '6' :
					$CouponBrandID    	= trim($CouponRS[0]['sku']); // Brand IDS
					$arr_CouponBrandID  = explode(",",$CouponBrandID);

					$CouponDiscount = 0;
					$found_brand = false; // Use for if coupon valid but category not found in cart;

					## Get Active Cat ID
					$Res_active_BrandID = Manufacture::where('status','=','1')
											->whereIn('imanufactureid',$arr_CouponBrandID)->get();
					$arr_active_BrandID = array();
					for($h=0;$h<count($Res_active_BrandID);$h++)
					{
						$arr_active_BrandID[] = $Res_active_BrandID[$h]['imanufactureid'];
					}
					if(count($arr_active_BrandID) > 0 )
					{
						## Get Cart Prod ID
						$tempCart = Session::get('ShoppingCart.Cart');
						$temp_prod_id   = array();

						for ($a=0; $a<count($tempCart); $a++)
						{
							$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
						}
						
						$ProdIds = Products::select('products_id')->distinct()
									->whereIn('imanufactureid',$arr_active_BrandID)
									->whereIn('products_id',$temp_prod_id)
									->get();
						$brand_prod_id  = array();
						for ($a=0; $a<count($ProdIds); $a++)
						{
							$brand_prod_id[$a] = $ProdIds[$a]['products_id'];
						}

						for ($a=0; $a<count($tempCart); $a++)
						{
							if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
							{
								if($CouponRS[0]['type'] == 1 )
								{
									$CouponDiscount = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+$CouponDiscount;
								}
								else
								{
									$CouponDiscount = $CouponRS[0]['discount']  ;
								}
								$found_brand = true; // make true if category match
							}
						}
					}
					else
					{
						$CouponDiscount = 0;
					}

				    if($found_brand==true)
				    {

					  if($CouponRS[0]["count_ship_tax"]=='1')
					  {
						if($CouponRS[0]['type'] == 1 )
						{
							if(Session::has('ShoppingCart.TaxValue') && Session::get('ShoppingCart.TaxValue') > 0)
								$CouponDiscount  = $CouponDiscount  + Session::has('ShoppingCart.TaxValue');
							if($shippingCharge > 0)
								$CouponDiscount  = $CouponDiscount  + $shippingCharge;
						}
					  }
					  if($CouponRS[0]["count_gc_purchase"]=='1')
					  {
						$GiftCertiTotal = 0;
						if(Session::has('ShoppingCart.GiftCertiTotal'))
							$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
						$CouponDiscount  = $CouponDiscount  + $GiftCertiTotal;
					  }
						if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
						{
					 	    if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = $CouponRS[0]['discount'];
							}
						}
						elseif($CouponDiscount >= $CouponRS[0]['minimum_order_amount'])
						{
							if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = $CouponRS[0]['discount'];
							}
						}
						else
					    {
						  $CouponDiscount = 0;
						  $msg = "Coupon code is invalid or does not exists.";
						  Session::flash('CartError',$msg);
					    }

				    }

					if($found_brand==false)
					{
						$msg = "Coupon code does not apply to the item you have in your bag.";
						Session::flash('CartError',$msg);
					}

					if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0 && $found_brand==true)
					{
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID',explode(",",$CouponRS[0]["free_shipping_value"]));
						Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
						$FreeShippingFlg = true;
					}
					if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0 && $found_brand==true)
					{
						$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
					}
					break;

			## For Free Shipping
			case '4' :
					$CouponDiscount = 0;
					$Total_Item_count_val  = Session::get('ShoppingCart.TotalItemInCart');

					if($CouponRS[0]['minimum_order_amount'] == 0.00 ||  $CouponRS[0]['minimum_order_amount'] == 0)
					{
						if($Total_Item_count_val >= $CouponRS[0]['total_free_shipping'])
						{
							$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingModeID',$ShippingID);

							if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
							{
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
								$FreeShippingFlg = true;
							}
							if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
							{
								$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
							}
							$FreeShippingFlg = true;
						}
						else if($Total_Item_count_val==0 ||$Total_Item_count_val =='')
						{
							$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingModeID',$ShippingID);

								if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
								{
									Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
									Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
									Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
									$FreeShippingFlg = true;
								}
								if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
								{
									$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
								}
								$FreeShippingFlg = true;
						}
						else
						{
							$FreeShippingFlg = false;
						}
				   }

				  elseif(Session::get('ShoppingCart.SubTotal') >= $CouponRS[0]['minimum_order_amount'])
					{
						if($Total_Item_count_val >= $CouponRS[0]['total_free_shipping'])
						{
							$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingModeID',$ShippingID);

							if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
							{
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
								$FreeShippingFlg = true;
							}
							if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
							{
								$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
							}
							$FreeShippingFlg = true;
						}
						else if($Total_Item_count_val==0 ||$Total_Item_count_val =='')
						{
							$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
							Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingModeID',$ShippingID);

							if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
							{
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','Yes');
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
								Session::put('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag',"Yes");
								$FreeShippingFlg = true;
							}
							if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='')
							{
								$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
							}
							$FreeShippingFlg = true;
						}
						else
						{
							$FreeShippingFlg = false;
						}
				   }
				   else
				   {
					    $msg = "Coupon code does not apply to the item you have in your bag.";
						Session::flash('CartError',$msg);
						$FreeShippingFlg = false;
				   }
				break;
			default :
					$CouponDiscount = 0;
					$couponCode='';
					$msg = "Coupon code does not apply to the item you have in your bag.";
					Session::flash('CartError',$msg);
					break;
		}
		if($FreeShippingFlg==false)
		{
			Session::put('ShoppingCart.PromoCoupon.SecondFreeShipping','No');
		}
		$CouponDiscount = $this->NumberFormat($CouponDiscount);

		if($CouponDiscount > 0 or $FreeShippingFlg==true)
		{
			Session::put('ShoppingCart.PromoCoupon.SecondPromoCoupon',$couponCode);	
		}
		else
		{
			Session::put('ShoppingCart.PromoCoupon.CouponCodeSecond','');
		}

		if($CouponDiscount > 0 )
		{
			Session::put('ShoppingCart.PromoCoupon.SecondCouponDiscount',$CouponDiscount);
		}
		else
		{
			Session::put('ShoppingCart.PromoCoupon.SecondCouponDiscount',0);
		}
		/*
		if($this->request['actiononcart']=='apply_coupon')
		{
			if(Session::get('ShoppingCart.PromoCoupon.CouponCode') !='' && Session::get('ShoppingCart.PromoCoupon.FirstCouponDiscount') > 0)
			{
				$msg = "Coupon code applied successfully.";
				Session::flash('CartError',$msg);
			}
		}*/
		return NULL;
	}
	
	public function GetShippingCharge()
	{
		if(Session::has('ShoppingCart.Shipping'))
		{
			$temp = Session::get('ShoppingCart.Shipping');
			if(Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeIDFlag') == 'Yes' && (in_array($temp['ShippingMethodID'],Session::get('ShoppingCart.PromoCoupon.FreeShippingCouponModeID'))) && Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes')
				Session::put('ShoppingCart.Shipping.ShippingCharge',0.00);
			
			if(Session::get('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeIDFlag')== 'Yes' && (in_array($temp['ShippingMethodID'],Session::get('ShoppingCart.PromoCoupon.SecondFreeShippingCouponModeID'))) && Session::get('ShoppingCart.PromoCoupon.SecondFreeShipping') == 'Yes')
				Session::put('ShoppingCart.Shipping.ShippingCharge',0.00);
		
			if(Session::get('ShoppingCart.PromoCoupon.FreeShipping') == 'Yes' && $temp['ShippingMethodID'] == Session::get('ShoppingCart.PromoCoupon.FreeShippingModeID'))
				Session::put('ShoppingCart.Shipping.ShippingCharge',0.00);
			
			if(Session::get('ShoppingCart.PromoCoupon.SecondFreeShipping') == 'Yes' && $temp['ShippingMethodID'] == Session::get('ShoppingCart.PromoCoupon.SecondFreeShippingModeID'))
				Session::put('ShoppingCart.Shipping.ShippingCharge',0.00);
			return NumberFormat(Session::get('ShoppingCart.Shipping.ShippingCharge'));
		}
	}
	
	public function ApplyAutoDiscount()
	{
		$auto_discount = 0;
		$NewSubTotal = 0;
        if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
        {
			
			if(Auth::user() && Session::get('eusertype') == "Wholesaler")
			{
				Session::put('ShoppingCart.AutoDiscount',0);
				return null;
			}
			
            if(Session::has('ShoppingCart.SubTotal'))
                $NewSubTotal = NumberFormat(Session::get('ShoppingCart.SubTotal'));
            $GiftCertiTotal = 0;
            if(Session::has('ShoppingCart.GiftCertiTotal'))
                $GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));	
            $subTotal = $NewSubTotal - $GiftCertiTotal;
			
			$DealSubTotal = $this->getDealSubTotal();
		
			$subTotal = $subTotal - $DealSubTotal;
				
            $Cart = Session::get('ShoppingCart.Cart');
            $discount_coupon_flag = '';
            if($subTotal <= 0 )
            {
                Session::put('ShoppingCart.AutoDiscount',0);
                Session::put('ShoppingCart.AutoDiscountFlag','');
                return NULL;
            }
            $CouponCode = "";
            if(Session::has('ShoppingCart.PromoCoupon.CouponCode') && Session::get('ShoppingCart.PromoCoupon.CouponCode') != '')
                $CouponCode = Session::get('ShoppingCart.PromoCoupon.CouponCode');	
            if($CouponCode !='')
            {
                $coupon_res = Coupon::select('autodiscount_flag')
                                ->where('coupon_number','=',$CouponCode)
                                ->where('status','=','1')
                                ->where('start_date','<=',DB::raw('curdate()'))
                                ->where('end_date','>=',DB::raw('curdate()'))
                                ->get();		
                if($coupon_res && $coupon_res->count() > 0)
                {
                    if($coupon_res[0]->autodiscount_flag == "No")
                    {
                        Session('ShoppingCart.AutoDiscount',0);
                        Session('ShoppingCart.AutoDiscountFlag','');
                        return NULL;
                    }
                }
            }

            $AutoRS = AutoDiscount::where('start_date','<=',DB::raw('curdate()'))
                        ->where('end_date','>=',DB::raw('curdate()'))	
                        ->where('end_order_amount','>=',$subTotal)
                        ->where('order_amount','<=',$subTotal)
                        ->where('status','=','1')->orderBy('end_order_amount','desc')->get();

            if($AutoRS && $AutoRS->count() <= 0)
            {
                $AutoRS = AutoDiscount::where('start_date','<=',DB::raw('curdate()'))
                        ->where('end_date','>=',DB::raw('curdate()'))	
                        ->where('end_order_amount','<=',$subTotal)
                        ->where('status','=','1')->orderBy('end_order_amount','desc')->get();
            }

            $TotalItems = count(Session::get('ShoppingCart.Cart'));
            $TotalAutoDiscountRecords = $AutoRS->count();
            $TotalExcludePrice = 0;

            if($TotalAutoDiscountRecords > 0)
            {
                 $SKURemoveArr = '';
                 for($i=0;$i<$TotalAutoDiscountRecords;$i++)
                 {
                    $discount_coupon_flag = $AutoRS[$i]->discount_coupon_flag;
                    $ExcludeSKUListArr = array();
                    $TotalExcludePrice = 0;

                    if($TotalItems > 0 && $TotalAutoDiscountRecords > 0 && trim($AutoRS[$i]->exclude_sku)!='')
                    {
                        $ExcludeSKUListArr = array();
                        $ExcludeSKUListArr  = explode(",",$AutoRS[$i]->exclude_sku);
                        $ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
                        $ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
                        $TotalExcludePrice = 0;
                        for($p=0;$p<$TotalItems;$p++)
                        {
                            if(in_array($Cart[$p]["SKU"],$ExcludeSKUListArr))
                            {
                                $TotalExcludePrice =  $TotalExcludePrice  + $Cart[$p]["TotPrice"];
                            }
                        }
                    }		

                    if($AutoRS[$i]->sku !='')
                    {
                        $QtySKU = trim($AutoRS[$i]->sku);
                        $QtyBrandID    	= trim($AutoRS[$i]->sku); // Category IDS
                        $arr_QtyBrandID    = explode(",",$QtyBrandID);
                        $AutoDiscount1 = 0;
                        $found_brand = false; // Use for if coupon valid but category not found in cart;

                        ## Get Active Cat ID
                        $Res_active_BrandID = Manufacture::where('status','=','1')
                                                ->whereIn('imanufactureid',$arr_QtyBrandID)->get();

                        $arr_active_BrandID = array();
                        for($h=0;$h<count($Res_active_BrandID);$h++)
                        {
                            $arr_active_BrandID[] = $Res_active_BrandID[$h]['imanufactureid'];
                        }
                        if(count($arr_active_BrandID) > 0 )
                        {
                            ## Get Cart Prod ID
                            $tempCart = Session::get('ShoppingCart.Cart');
                            $temp_prod_id   = array();
							
                            for ($a=0; $a<count($tempCart); $a++)
                            {
                                $temp_prod_id[$a] = $tempCart[$a]['ProductID'];
                            }
                            $ProdIds = Products::select('products_id')->distinct()
                                        ->whereIn('imanufactureid',$arr_active_BrandID)
                                        ->whereIn('products_id',$temp_prod_id)
                                        ->get();
			
                            $brand_prod_id  = array();
							
                            for ($a=0; $a<$ProdIds->count(); $a++)
                            {
                                $brand_prod_id[$a] = $ProdIds[$a]['products_id'];
                            }
                            $SKURemoveArrNew = [];
                            if($SKURemoveArr!='')
                            {
                                $SKURemoveArrNew = explode(",",$SKURemoveArr);
                                $SKURemoveArrNew = array_filter($SKURemoveArrNew);
                                $SKURemoveArrNew = array_values($SKURemoveArrNew);
                            }
                            for ($a=0; $a<count($tempCart); $a++)
                            {
                                $FreeGift = "";
                                if(isset($tempCart[$a]["IS_Free_Gift"]))
                                    $FreeGift = $tempCart[$a]["IS_Free_Gift"];
                                if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) &&  !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
                                {
                                    if($AutoRS[$i]['type'] == 1 )
                                    {
                                        $AutoDiscount1 = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']) + $AutoDiscount1;
                                    }
                                    $found_brand = true;
                                    $SKURemoveArr.= $tempCart[$a]['SKU'].",";
                                }
                            }
                        }
                        if($found_brand==true)
                        {
                            $QuantityDiscountFlag = $AutoRS[$i]["discount_coupon_flag"];
                            $ExcludeSKUListArr = array();
                            $TotalExcludePrice = 0;
                            if($TotalItems > 0 && $TotalAutoDiscountRecords > 0 && trim($AutoRS[$i]["exclude_sku"])!='')
                            {
                                $ExcludeSKUListArr = array();
                                $ExcludeSKUListArr  = explode(",",$AutoRS[$i]["exclude_sku"]);
                                $ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
                                $ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
                                $TotalExcludePrice = 0;
                                for($p=0;$p<$TotalItems;$p++)
                                {
                                    if(in_array($Cart[$p]["SKU"],$ExcludeSKUListArr))
                                    {
                                        $TotalExcludePrice =  $TotalExcludePrice  + $Cart[$i]["TotPrice"];
                                    }
                                }
                            }
                            $MatchNewAutoDiscount=  0;
                            if($auto_discount > 0)
                            {
                                $MatchNewAutoDiscount = $auto_discount;
                            }
                            if($AutoRS[$i]['type'] == 1)
                            {
                                $auto_discount = ($AutoDiscount1  * $AutoRS[$i]['auto_discount_amount']/100);
                            }
                            else
                            {
                                $auto_discount = $AutoRS[$i]['auto_discount_amount'];
                            }
                            $auto_discount = $auto_discount + $MatchNewAutoDiscount;
                        }
                    }
                    else
                    {
                        $AutoRS1 = AutoDiscount::where('start_date','<=',DB::raw('curdate()'))
                            ->where('end_date','>=',DB::raw('curdate()'))	
                            ->where('end_order_amount','>=',$subTotal)
                            ->where('order_amount','<=',$subTotal)
                            ->where('status','=','1')->where('sku','=','')
                            ->orderBy('end_order_amount','desc')->limit(1)->get();

                        $TotalAutoDiscountRecords1 = $AutoRS1->count();
                        if($TotalAutoDiscountRecords1<=0)
                        {
                            $AutoRS1 = AutoDiscount::where('start_date','<=',DB::raw('curdate()'))
                            ->where('end_date','>=',DB::raw('curdate()'))	
                            ->where('end_order_amount','<=',$subTotal)
                            ->where('status','=','1')->where('sku','=','')
                            ->orderBy('end_order_amount','desc')->limit(1)->get();
                            $TotalAutoDiscountRecords1 = $AutoRS1->count();
                        }

                        if($TotalAutoDiscountRecords1 > 0)
                        {
                            $discount_coupon_flag = $AutoRS1[0]['discount_coupon_flag'];
                            $subTotal = $subTotal - $TotalExcludePrice;
                            if($AutoRS1[0]['type'] == '1')
                                $auto_discount = ( $subTotal * ($AutoRS1[0]['auto_discount_amount']/100) );
                            else
                                $auto_discount = $AutoRS1[0]['auto_discount_amount'];
                            break;
                        }
                        else
                        {
                             $auto_discount = 0;
                        }
                    }
                 }
            }
            Session::put('ShoppingCart.AutoDiscount',NumberFormat($auto_discount));
            Session::put('ShoppingCart.AutoDiscountFlag',$discount_coupon_flag);
        }
		return NULL;
	}
	
	public function GetAllDiscounts($DiscountName='')
	{
		$Discounts = [];
		If(Session::has('ShoppingCart.AutoDiscount') && Session::get('ShoppingCart.AutoDiscount') > 0)
			$Discounts['AutoDiscount'] = ['label' => 'Auto Discount', 'discount' => Session::get('ShoppingCart.AutoDiscount')];
		If(Session::has('ShoppingCart.QuantityDiscount') && Session::get('ShoppingCart.QuantityDiscount') > 0)
			$Discounts['QuantityDiscount'] = ['label' => 'Quantity Discount', 'discount' => Session::get('ShoppingCart.QuantityDiscount')];
		$CouponTotal = 0;
		If(Session::has('ShoppingCart.PromoCoupon.FirstCouponDiscount') && Session::get('ShoppingCart.PromoCoupon.FirstCouponDiscount') > 0)
			$CouponTotal += NumberFormat(Session::get('ShoppingCart.PromoCoupon.FirstCouponDiscount'));
		If(Session::has('ShoppingCart.PromoCoupon.SecondCouponDiscount') && Session::get('ShoppingCart.PromoCoupon.SecondCouponDiscount') > 0)
			$CouponTotal += NumberFormat(Session::get('ShoppingCart.PromoCoupon.SecondCouponDiscount'));
		if($CouponTotal > 0)
			Session::put('ShoppingCart.PromoCoupon.CouponDiscount',$CouponTotal);	
		
		If(Session::has('ShoppingCart.PromoCoupon.CouponDiscount') && Session::get('ShoppingCart.PromoCoupon.CouponDiscount') > 0)
			$Discounts['CouponDiscount'] = ['label' => 'Coupon Discount', 'discount' => Session::get('ShoppingCart.PromoCoupon.CouponDiscount'),'Ricon' => 'Yes', 'dataid' => 'CouponDiscount'];
		If(Session::has('ShoppingCart.GiftCoupon') && Session::get('ShoppingCart.GiftCoupon') > 0)
		{	
			$GiftCoupon = Session::get('ShoppingCart.GiftCoupon');
			$Discounts['GiftCoupon'] = ['label' => 'Gift Certificate Discount', 'discount' => $GiftCoupon['Value'],'Ricon' => 'Yes', 'dataid' => 'GiftCoupon'];
		}
		If(Session::has('ShoppingCart.AutoReferDiscount') && Session::get('ShoppingCart.AutoReferDiscount') > 0)
			$Discounts['AutoReferDiscount'] = ['label' => 'Auto Refer Discount', 'discount' => Session::get('ShoppingCart.AutoReferDiscount')];
		If(Session::has('ShoppingCart.Reward_array.RewardDiscount') && Session::get('ShoppingCart.Reward_array.RewardDiscount') > 0)
			$Discounts['AutoRewardDiscount'] = ['label' => 'Reward Discount', 'discount' => Session::get('ShoppingCart.Reward_array.RewardDiscount')];
		If(Session::has('ShoppingCart.credit_limit_discount') && Session::get('ShoppingCart.credit_limit_discount') > 0)
			$Discounts['CreditLimitDiscount'] = ['label' => 'Credit Limit Discount', 'discount' => Session::get('ShoppingCart.credit_limit_discount')];
		If(Session::has('ShoppingCart.DogoDiscount') && Session::get('ShoppingCart.DogoDiscount') > 0)
			$Discounts['DogoDiscount'] = ['label' => 'Bogo Discount', 'discount' => Session::get('ShoppingCart.DogoDiscount')];
		if($DiscountName != '')
		{
			$DiscountDetail = 0;
			if(isset($Discounts[$DiscountName]))
				$DiscountDetail = NumberFormat($Discounts[$DiscountName]['discount']);
			return $DiscountDetail;
		} else {
			$TotalDiscount = array_sum(array_column($Discounts,'discount'));
			$DiscountInfo  = ['Discounts' => $Discounts, 'TotalDiscount' => NumberFormat($TotalDiscount)];
			return $DiscountInfo;
		}
	}
	
	public function GetAllCharges($ChargeName='')
	{
		$Charges = [];
		If(Session::has('ShoppingCart.Shipping.ShippingCharge') && Session::get('ShoppingCart.Shipping.ShippingCharge') > 0)
			$Charges['ShippingCharge'] = ['label' => 'Shipping Charge', 'charge' => Session::get('ShoppingCart.Shipping.ShippingCharge')];
		If(Session::has('ShoppingCart.Tax') && Session::get('ShoppingCart.Tax') > 0)
			$Charges['Tax'] = ['label' => 'Sales Tax', 'charge' => Session::get('ShoppingCart.Tax')];	
		if(Session::has('ShoppingCart.GiftWrapping'))
		{
			$giftWrap = Session::get('ShoppingCart.GiftWrapping');
			if($giftWrap['Charge'] > 0)
				$Charges['GiftWrappingCharge'] = ['label' => 'Gift Wrapping Charge', 'charge' => $giftWrap['Charge']];
		}
		if(Session::has('ShoppingCart.ShippingSignature'))
			$Charges['ShippingSignature'] = ['label' => 'Shipping Signature', 'charge' => Session::get('ShoppingCart.ShippingSignature')];
		if(Session::has('shipping_insurance_charge'))
			$Charges['ShippingInsurance'] = ['label' => 'Shipping Insurance Charge', 'charge' => Session::get('shipping_insurance_charge')];
		
		if($ChargeName != '')
		{
			$Charge = (isset($Charges[$ChargeName])?NumberFormat($Charges[$ChargeName]['charge']):0);
			return $Charge;
		} else {
			$TotalCharges = array_sum(array_column($Charges,'charge'));
			$ChargesInfo  = ['Charges' => $Charges, 'TotalCharges' => NumberFormat($TotalCharges)];
			return $ChargesInfo;
		}
	}
	
	public function GetAllCoupons($CouponID='')
	{
		$Coupons = ['CouponCode' => '','CouponCodeSecond' => '', 'GiftCoupon'];
		If(Session::has('ShoppingCart.PromoCoupon.CouponCode') && Session::get('ShoppingCart.PromoCoupon.CouponCode') != '')
			$Coupons['CouponCode'] = Session::get('ShoppingCart.PromoCoupon.CouponCode');
		If(Session::has('ShoppingCart.PromoCoupon.CouponCodeSecond') && Session::get('ShoppingCart.PromoCoupon.CouponCodeSecond') != '')
			$Coupons['CouponCodeSecond'] = Session::get('ShoppingCart.PromoCoupon.CouponCodeSecond');
		If(Session::has('ShoppingCart.GiftCoupon') && Session::get('ShoppingCart.GiftCoupon') != '')
			$Coupons['GiftCoupon'] = Session::get('ShoppingCart.GiftCoupon');	
		if($CouponID != '') {
			if(isset($Coupons[$CouponID]))
				return $Coupons[$CouponID];
		} else { 
			return $Coupons;
		}
	}
	public function ApplyQuantityDiscount()
	{
		$QuantityDiscount = 0;
		$NewSubTotal = 0;
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			
			if(Auth::user() && Session::get('eusertype') == "Wholesaler")
			{
				Session::put('ShoppingCart.QuantityDiscount',0);
				return null;
			}
			
			if(Session::has('ShoppingCart.SubTotal'))
				$NewSubTotal = NumberFormat(Session::get('ShoppingCart.SubTotal'));
			$GiftCertiTotal = 0;
			$GiftCertiCount = 0 ;
			if(Session::has('ShoppingCart.GiftCertiTotal'))
			{
				$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.GiftCertiTotal'));
				$GiftCertiCount = NumberFormat(Session::get('ShoppingCart.GiftCertiCount'));				
			}
			$subTotal = $NewSubTotal - $GiftCertiTotal;
			
			$Cart = Session::get('ShoppingCart.Cart');
			
			$TotalItem 	= Session::get('ShoppingCart.TotalItemInCart') - $GiftCertiCount;
			$QuantityDiscountFlag = '';
			if($subTotal <= 0 || $TotalItem <= 0)
			{
				Session('ShoppingCart.QuantityDiscount',0);
				Session('ShoppingCart.QuantityDiscountFlag','');
				return NULL;
			}
			$CouponCode = $this->GetAllCoupons('CouponCode');
			if($CouponCode != '')
			{
				$coupon_res = Coupon::select('quantitydiscount_flag')
								->where('coupon_number','=',$CouponCode)
								->where('status','=','1')
								->where('start_date','<=',DB::raw('curdate()'))
								->where('end_date','>=',DB::raw('curdate()'))
								->get();		
				if($coupon_res && $coupon_res->count() > 0)
				{
					if($coupon_res[0]->quantitydiscount_flag == "No")
					{
						Session('ShoppingCart.QuantityDiscount',0);
						Session('ShoppingCart.QuantityDiscountFlag','');
						return NULL;
					}
				}
			}
			
			$QtyRS = QuantityDiscount::where('status','=','1')
						->where('start_date','<=',DB::raw('curdate()'))
						->where('end_date','>=',DB::raw('curdate()'))
						->where('quantity','<=',$TotalItem)
						->orderBy('quantity_discount_id','desc')
						->get();
			$TotalQuantityDiscoundRecords = $QtyRS->count();
			$TotalItems = count(Session::get('ShoppingCart.Cart'));
			$TotalExcludePrice = 0;

			if($TotalQuantityDiscoundRecords > 0)
			{
			   $TotalQuantity = $QtyRS->count();
			   $SKURemoveArr = '';
			   for($i=0;$i<$TotalQuantity;$i++)
			   {
					$QuantityDiscountFlag = $QtyRS[$i]["discount_coupon_flag"];
					$ExcludeSKUListArr = array();
					$TotalExcludePrice = 0;
					if($TotalItems > 0 && $TotalQuantityDiscoundRecords > 0 && trim($QtyRS[$i]["exclude_sku"])!='')
					{
						$ExcludeSKUListArr = array();
						$ExcludeSKUListArr  = explode(",",$QtyRS[$i]["exclude_sku"]);
						$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
						$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
						$TotalExcludePrice = 0;
						for($p=0;$p<$TotalItems;$p++)
						{
							if(in_array($Cart[$p]["SKU"],$ExcludeSKUListArr))
							{
								$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$p]["TotPrice"];
							}
						}
					}
					if($QtyRS[$i]["orders"]=='0')
					{
						$QtySKU = trim($QtyRS[$i]['sku']);
						########### For Multiple SKU ###############
						$arr_QtySKU  = explode(",",$QtySKU);

						$arr_QtySKU  = array_unique(array_map('trim',$arr_QtySKU));
						$arr_QtySKU  = array_filter($arr_QtySKU, 'strlen');

						$Matched_Item_Total = 0;
						$IS_Any_Matched 	= 0;

						if(is_array($arr_QtySKU) and !empty($arr_QtySKU))
						{
							$tempCart  = Session::get('ShoppingCart.Cart');
							$SKURemoveArrNew = [];
							if($SKURemoveArr!='')
							{
								$SKURemoveArrNew = explode(",",$SKURemoveArr);
								$SKURemoveArrNew = array_filter($SKURemoveArrNew);
								$SKURemoveArrNew = array_values($SKURemoveArrNew);
							}
							$total_qty = 0;
							$total_price = 0;
							for ($a=0; $a<count($tempCart); $a++)
							{
								$FreeGift = '';
								if(isset($tempCart[$a]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$a]["IS_Free_Gift"];
								if(in_array($tempCart[$a]['SKU'] , $arr_QtySKU) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift!="Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] ,$SKURemoveArrNew))
								{
									$IS_Any_Matched = $IS_Any_Matched+1;
									$total_qty = $total_qty + $tempCart[$a]['Qty'];
									if($QtyRS[$i]['type'] == 1 )
									{
									   $Matched_Item_Total = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+ $Matched_Item_Total;
									}
									$SKURemoveArr.= $tempCart[$a]['SKU'].",";
								}
							}
							$MatchNewQTYDiscount=  0;
							if($QuantityDiscount > 0)
							{
								$MatchNewQTYDiscount = $QuantityDiscount;
							}
							if($IS_Any_Matched > 0  && $total_qty >=$QtyRS[$i]['quantity'])
							{
								if($QtyRS[$i]['type'] == 1)
								{
									$QuantityDiscount = ($Matched_Item_Total * ($QtyRS[$i]['quantity_discount_amount']/100));
								}
								else
								{
									$QuantityDiscount = $QtyRS[$i]['quantity_discount_amount'];
								}						
							}
						}
					} else if($QtyRS[$i]["orders"]=='1'){
						$QtySKU = trim($QtyRS[$i]['sku']);
						########### For Multiple SKU ###############
						$QtyCatID    	= trim($QtyRS[$i]['sku']); // Category IDS
						$arr_QtyCatID    = explode(",",$QtyCatID);

						$QuantityDiscount1 = 0;
						$found_cat = false; // Use for if coupon valid but category not found in cart;
							
						## Get Active Cat ID
						$Res_active_CatID = Category::select('category_id')->where('status','=','1')
											->whereIn('category_id',$arr_QtyCatID)->get();
						$arr_active_CatID = array();
						for($h=0;$h<count($Res_active_CatID);$h++)
						{
							$arr_active_CatID[] = $Res_active_CatID[$h]['category_id'];
						}
						if(count($arr_active_CatID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart  	    = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}
							$ProdIds = ProductsCategory::select('products_id')->distinct()
										->whereIn('category_id',$arr_active_CatID)
										->whereIn('products_id',$temp_prod_id)
										->get();
							$cat_prod_id  = array();
							for ($a=0; $a< $ProdIds->count(); $a++)
							{
								$cat_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
							$SKURemoveArrNew = [];	
							if($SKURemoveArr!='')
							{
								$SKURemoveArrNew = explode(",",$SKURemoveArr);
								$SKURemoveArrNew = array_filter($SKURemoveArrNew);
								$SKURemoveArrNew = array_values($SKURemoveArrNew);
							}	
							$total_qty = 0;
							$total_price = 0;
							$total_percentage = false;
							
							for ($a=0; $a<count($tempCart); $a++)
							{
								$FreeGift = '';
								if(isset($tempCart[$a]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$a]["IS_Free_Gift"];
								
								if (in_array( $tempCart[$a]['ProductID'] , $cat_prod_id) && (isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
								{
									$total_qty = $total_qty + $tempCart[$a]['Qty'];
									$total_price = $total_price + ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']);
									if($tempCart[$a]['Qty'] >= $QtyRS[$i]['quantity'] || $total_qty >= $QtyRS[$i]['quantity'])
									{
										if($QtyRS[$i]['type'] == 1 )
										{
											$total_percentage = true;
										}
										$found_cat = true;
										$SKURemoveArr.= $tempCart[$a]['SKU'].",";
									}
								}
							}
							if($total_percentage == true)
							{
								$QuantityDiscount1 = $total_price + $QuantityDiscount1;
							}
						}
						if($found_cat==true)
						{
							$QuantityDiscountFlag = $QtyRS[$i]["discount_coupon_flag"];
							$ExcludeSKUListArr = array();
							$TotalExcludePrice = 0;
							if($TotalItems > 0 && $TotalQuantityDiscoundRecords > 0 && trim($QtyRS[$i]["exclude_sku"])!='')
							{
								$ExcludeSKUListArr = array();
								$ExcludeSKUListArr  = explode(",",$QtyRS[$i]["exclude_sku"]);
								$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
								$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
								$TotalExcludePrice = 0;
								for($p=0;$p<$TotalItems;$p++)
								{
									if(in_array($Cart[$p]["SKU"],$ExcludeSKUListArr))
									{
										$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$i]["TotPrice"];
									}
								}
							 }
							$MatchNewQTYDiscount=  0;
							if($QuantityDiscount > 0)
							{
								$MatchNewQTYDiscount = $QuantityDiscount;
							}
							if($QtyRS[$i]['type'] == 1)
							{
								$QuantityDiscount = ($QuantityDiscount1  * $QtyRS[$i]['quantity_discount_amount']/100);
							}
							else
							{
								$QuantityDiscount = $QtyRS[$i]['quantity_discount_amount'];
							}
								
							if($QuantityDiscount > $MatchNewQTYDiscount)
							{
								$QuantityDiscount = $QuantityDiscount;
							}
							elseif($MatchNewQTYDiscount > $QuantityDiscount)
							{
								$QuantityDiscount = $MatchNewQTYDiscount;
							}					
						}
				   }
				   else if($QtyRS[$i]["orders"]=='2')
				   {
						$QtySKU = trim($QtyRS[$i]['sku']);

						########### For Multiple SKU ###############

						$QtyBrandID    	= trim($QtyRS[$i]['sku']); // Category IDS
						$arr_QtyBrandID    = explode(",",$QtyBrandID);

						$QuantityDiscount1 = 0;
						$found_brand = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_BrandID = Manufacture::where('status','=','1')
												->whereIn('imanufactureid',$arr_QtyBrandID)->get();
						$arr_active_BrandID = array();
						for($h=0;$h<count($Res_active_BrandID);$h++)
						{
							$arr_active_BrandID[] = $Res_active_BrandID[$h]['imanufactureid'];
						}
						if(count($arr_active_BrandID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart  	    = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}
							$ProdIds = Products::select('products_id')->distinct()
										->whereIn('imanufactureid',$arr_active_BrandID)
										->whereIn('products_id',$temp_prod_id)
										->get();
							$brand_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$brand_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
							$SKURemoveArrNew = [];
							if($SKURemoveArr!='')
							{
								$SKURemoveArrNew = explode(",",$SKURemoveArr);
								$SKURemoveArrNew = array_filter($SKURemoveArrNew);
								$SKURemoveArrNew = array_values($SKURemoveArrNew);
							}							
							$total_qty = 0;
							$total_price = 0;
							$total_percentage = false;
							for ($a=0; $a<count($tempCart); $a++)
							{
								$FreeGift = '';
								if(isset($tempCart[$a]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$a]["IS_Free_Gift"];
								if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift!="Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
								{
									$total_qty = $total_qty + $tempCart[$a]['Qty'];
									$total_price = $total_price + ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']);
									
									if($tempCart[$a]['Qty'] >= $QtyRS[$i]['quantity'] || $total_qty >= $QtyRS[$i]['quantity'])
									{
										if($QtyRS[$i]['type'] == 1 )
										{
											$total_percentage = true;
										}
										$found_brand = true;
										$SKURemoveArr.= $tempCart[$a]['SKU'].",";
									}
								}
							}
							if($total_percentage == true)
							{
								$QuantityDiscount1 = $total_price + $QuantityDiscount1;
							}
						}

						if($found_brand==true)
						{
							$QuantityDiscountFlag = $QtyRS[$i]["discount_coupon_flag"];
							$ExcludeSKUListArr = array();
							$TotalExcludePrice = 0;
							if($TotalItems > 0 && $TotalQuantityDiscoundRecords > 0 && trim($QtyRS[$i]["exclude_sku"])!='')
							{
								$ExcludeSKUListArr = array();
								$ExcludeSKUListArr  = explode(",",$QtyRS[$i]["exclude_sku"]);
								$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
								$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
								$TotalExcludePrice = 0;
								for($p=0;$p<$TotalItems;$p++)
								{
									if(in_array($Cart[$p]["SKU"],$ExcludeSKUListArr))
									{
										$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$i]["TotPrice"];
									}
								}
							}
							$MatchNewQTYDiscount=  0;
							if($QuantityDiscount > 0)
							{
								$MatchNewQTYDiscount = $QuantityDiscount;
							}
							if($QtyRS[$i]['type'] == 1)
							{
								 $QuantityDiscount = ($QuantityDiscount1  * $QtyRS[$i]['quantity_discount_amount']/100);
							}
							else
							{
								$QuantityDiscount = $QtyRS[$i]['quantity_discount_amount'];
							}
								
							if($QuantityDiscount > $MatchNewQTYDiscount)
							{
								$QuantityDiscount = $QuantityDiscount;
							}
							elseif($MatchNewQTYDiscount > $QuantityDiscount)
							{
								$QuantityDiscount = $MatchNewQTYDiscount;
							}
						}
				   }
				    else
				    {
					   $QtyRS1 = QuantityDiscount::where('status','=','1')
						->where('start_date','<=',DB::raw('curdate()'))
						->where('end_date','>=',DB::raw('curdate()'))
						->where('quantity','<=',$TotalItem)->where('orders','=','')
						->orderBy('quantity_discount_id')->limit(1)
						->get();
						$IS_Any_Matched 	= 0;
						$total_qty			= 0;
						if($QtyRS1 && $QtyRS1->count() > 0)
						{
							$tempCart  = Session::get('ShoppingCart.Cart');
							$subTotal = 0;
							for ($a=0; $a<count($tempCart); $a++)
							{
								$FreeGift = '';
								if(isset($tempCart[$a]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$a]["IS_Free_Gift"];
								if($tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift!="Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{
									$IS_Any_Matched = $IS_Any_Matched+1;
									$total_qty = $total_qty + $tempCart[$a]['Qty'];
									$subTotal+=$tempCart[$a]['TotPrice'];
								}
							}
							if($IS_Any_Matched > 0  && $total_qty >=$QtyRS[$i]['quantity'])
							{
								if($QtyRS1[0]['type'] == '1')
								$QuantityDiscount = ( $subTotal * ($QtyRS1[0]['quantity_discount_amount']/100) );
								else
								$QuantityDiscount = $QtyRS1[0]['quantity_discount_amount'];
							}

							break;
						}
						else
						{
							$QuantityDiscount = 0;
						}
				   }
			  
			   }
			}
			else
			{
				$QuantityDiscountFlag = '';
				$QuantityDiscount = 0;
			}
			Session::put('ShoppingCart.QuantityDiscount',NumberFormat($QuantityDiscount));
			Session::put('ShoppingCart.QuantityDiscountFlag',$QuantityDiscountFlag);
			return NULL;
		}
	}
	
	public function GetNetTotal()
	{
		$GiftCouponInfo = $this->GetAllCoupons('GiftCoupon');
		$giftWrapCharge = 0;
		if(Session::has('ShoppingCart.GiftWrapping'))
		{
			$giftWrap = Session::get('ShoppingCart.GiftWrapping');
			$giftWrapCharge = (float)$giftWrap['Charge'];
		}
		$ShippingSignature = 0;
		if(Session::has('ShoppingCart.ShippingSignature'))
			$ShippingSignature = (float)Session::get('ShoppingCart.ShippingSignature');
		
		$AllCharges = $this->GetAllCharges();
		$SubTotal = Session::get('ShoppingCart.SubTotal');
		$TotalAmount = $SubTotal + $AllCharges['TotalCharges'];

		$AllDiscount = $this->GetAllDiscounts();
		
		$TotalDiscount = $AllDiscount['TotalDiscount'];
		$NetTotal = $TotalAmount - $TotalDiscount;

		if($NetTotal <= 0)
			$NetTotal = 0;
		
		return NumberFormat( $NetTotal );
	}
	
	public function ApplyGiftWrapping($ProductID='')
	{
		$shopcart = [];
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			$shopcart = Session::get('ShoppingCart.Cart');
			$total_gift_charge = 0;
			$GiftWrappingCharge = config('Settings.GIFT_WRAPPING_CHARGE');
			for($i=0;$i<count($shopcart);$i++)
			{
				if((isset($shopcart[$i]['gift_wrap']) && $shopcart[$i]['gift_wrap']=='Yes') || ($ProductID != '' && $shopcart[$i]['ProductID'] == $ProductID))
				{
					$total_gift_charge+=$shopcart[$i]['Qty'] * $GiftWrappingCharge;
				}
			}
			$tempAry['Charge'] 	= NumberFormat($total_gift_charge);
			$tempAry['Applied'] = 'Yes';
			Session::put('ShoppingCart.GiftWrapping',$tempAry);
			return null;
		}
	}
	
	public function GetCreditLimitAmount()
	{
		$CreditAmt = 0;
		$RemainCreditLimit = 0;
		$CreditLimitFlag = 0;
	
		if(Auth::user() && $CreditAmt = Auth::user()->credit_limit > 0 && Auth::user()->registration_type == 'M' && config('Settings.WHOLESALE_CREDIT_LIMIT') == 'Yes' && Auth::user()->is_dropshipper != 'Yes')
		{
			$CreditDiscount = $this->GetAllDiscounts('CreditLimitDiscount');
			if(Session::has('ShoppingCart.customer_remaining_credit_amount'))
				$RemainCreditLimit = $this->Make_Price(Session::get('ShoppingCart.customer_remaining_credit_amount'));
			
			$NetTotal = $this->GetNetTotal();
			$CreditAmt = Auth::user()->credit_limit;
			if($CreditDiscount <= 0 && $NetTotal > 0){
				$CreditLimitFlag = 1;
			}elseif($CreditDiscount > 0){
				$CreditLimitFlag = 2;
			}else{ 
				$CreditAmt = 0;	
			}
		}
		return ['CreditLimitFlag' => $CreditLimitFlag, 'CreditLimit' => $CreditAmt, 'RemainCreditLimit' => $RemainCreditLimit];
	}
	
	public function isCouponsAvailable()
	{
		$CouponRS = Coupon::select('quantitydiscount_flag')
							->where('status','=','1')
							->where('start_date','<=',DB::raw('curdate()'))
							->where('end_date','>=',DB::raw('curdate()'))
							->get();
		if($CouponRS && $CouponRS->count() > 0)
			return true;
		else
			return false;
	}
	
	public function isSecondCouponsAvailable()
	{
		$CouponRS = Coupon::select('quantitydiscount_flag')
							->where('status','=','1')
							->where('start_date','<=',DB::raw('curdate()'))
							->where('end_date','>=',DB::raw('curdate()'))
							->limit(2)
							->get();
		if($CouponRS && $CouponRS->count() >= 2)
			return true;
		else
			return false;
	}
	
	public function SetCartAttributes()
	{
		
		$Attrs = [];
		$onlyGCPurchased = 1;
		$CheckGCPurchasedVal = 0;
		$RewardPointItemWiseTotal = 0;
		$critieostr = '';
		$IsVenderItem = "No";
		$IsCosmo = "No";
		$IsNandansons = "No";
		$IsPerfumePW = "No";
		$IsPCA = "No";
		$IsMaxaromaTwoDelivery = "No";
		$ISMax2day = "";
		$ISMaxTwoItem = "No";
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			$ShopCartItems = Session::get('ShoppingCart.Cart');
			$TempCart = [];
			foreach($ShopCartItems as $ShopItem)
			{
				$GiftChkOpt = "Yes";
				if(isset($ShopItem['IS_Free_Gift']) && $ShopItem['IS_Free_Gift'] =="Yes")
					$GiftChkOpt = "No";
				
				if($ShopItem['SKU'] == config('global.GIFT_CERTIFICATE_SKU') || $ShopItem['SKU'] == config('global.GIFT_CERTIFICATE_SKU1'))
				{
					$GiftChkOpt = "No";
					$CheckGCPurchasedVal = 1;
				}
				
				if(isset($ShopItem['IsGiftWrapProduct']) && $ShopItem['IsGiftWrapProduct'] == 'No')
					$GiftChkOpt = "No";
				if(isset($ShopItem['HandlingTimeStr']) && $ShopItem['HandlingTimeStr'] != '')
					$GiftChkOpt = "No";
				
				if($ShopItem['SKU']!= config('global.GIFT_CERTIFICATE_SKU') && $ShopItem['SKU']!= config('global.GIFT_CERTIFICATE_SKU1'))
					$onlyGCPurchased = 0;
				
				$ShopItem['ShowGiftChkOpt'] = $GiftChkOpt;
				
				if(Auth::user() && strtolower(Auth::user()->eusertype) == 'retailer' && Session::get('sess_icustomerid') > 0 && Session::get('etype') == 'M')
				{
					if(isset($ShopItem['PointMultipier']) && $ShopItem['PointMultipier'] > 0)
					{
						$ShopItem['RewardItemWise'] = $ShopItem['TotPrice'] * $ShopItem['PointMultipier'];
						$ShopItem['RewardItemWise'] = NumberFormat($ShopItem['RewardItemWise']);
						$RewardPointItemWiseTotal = $RewardPointItemWiseTotal + $ShopItem['RewardItemWise'];
					}
				}
				
				$critieostr.='{ id: "'.$ShopItem["SKU"].'", price: '.$ShopItem["Price"].', quantity: '.$ShopItem["Qty"].' } ,';
				
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
					$ISMaxTwoItem = "Yes";
				}
				else
				{
					$ISMax2day = "No";
				}	
				
				$TempCart[] = $ShopItem;
			}
			Session::put('ShoppingCart.Cart',$TempCart);
			Session::put('ShoppingCart.RewardPointItemWiseTotal', ceil($RewardPointItemWiseTotal));
		}
		if($ISMax2day=="No")
		{
			$IsMaxaromaTwoDelivery = "No";
		}
		$onlyWireTrabsfer = 0;
		$onlyAmazonPaypal = 0;
		if(Session::get('payment_amount') > 0 && Session::get('sess_icustomerid') > 0 && Session::get('etype') == "M")
		{
			$NetTotal = $this->GetNetTotal();
			if($NetTotal <= Session::get('payment_amount')){
				$onlyAmazonPaypal = 1;
				$onlyWireTrabsfer = 1;
			}else if($NetTotal > Session::get('payment_amount')){
				$onlyAmazonPaypal = 0;
				$onlyWireTrabsfer = 1;	
			}
		} else {
			$NetTotal = $this->GetNetTotal();
			if($NetTotal > 0  && $NetTotal <= 5000){
				$onlyAmazonPaypal = 1;
				$onlyWireTrabsfer = 1;
			}else if($NetTotal > 5000){
				$onlyAmazonPaypal=0;
				$onlyWireTrabsfer = 1;
			}
		}
		if(Session::has('ShoppingCart.GiftCoupon'))
		{
			$GiftCouponInfo = Session::get('ShoppingCart.GiftCoupon');
			if($GiftCouponInfo['Code'] !='' && $GiftCouponInfo['Code'] != null)
			{
				$new_total = NumberFormat($this->GetNetTotal() + $GiftCouponInfo['Value']);
				if($new_total <= $GiftCouponInfo["Applicable_Value"])
					Session::put('ShoppingCart.GiftCoupon.Value',$new_total);
				else
					Session::put('ShoppingCart.GiftCoupon.Value',$GiftCouponInfo['Applicable_Value']);
			}
		}	
		
		$IsPaypalExpressCheckout ='No';
		$IsGoogleCheckout ='No';
		## Amazon Checkout Display Setting
		Session::forget('Afterpay');
		$Amazon_pay_Checkout ='No';
		$Afterpay_Checkout ='No';
		$PaymentMethods =  PaymentMethod::where('pm_status','=','Active')->get();
		if($PaymentMethods && $PaymentMethods->count() > 0 )
		{
			foreach($PaymentMethods as $PayeMethod)
			{
				if($PayeMethod->pm_group_name =='PAYMENT_AMAZONC')
				{
					$pm_details = unserialize($PayeMethod->pm_details);
					$payment_methods_settings = [];
					foreach ( $pm_details as $pm_var_name => $pm_var_value )
					{
						$payment_methods_settings[$pm_var_name] = $pm_var_value;
					}
				}
				if($PayeMethod->pm_group_name == 'PAYMENT_PAYWITHAMAZON')
				{
					$pm_details = unserialize($PayeMethod->pm_details);
					foreach ( $pm_details as $pm_var_name => $pm_var_value )
					{
						$payment_methods_settings[$pm_var_name] = $pm_var_value;
					}
					if(count($payment_methods_settings) > 0 && $payment_methods_settings['paywithamazon_Access_Key_Id'] !='' && $payment_methods_settings['paywithamazon_Secret_Key_ID'] !='' && $payment_methods_settings['paywithamazon_Merchant_Id'] != '')
					{
						$Amazon_pay_Checkout ='Yes';
					}
					config(['CLIENT_ID' => $this->decrypt($pm_details['paywithamazon_Client_ID'])]);
					config(['MERCHANT_ID' => $this->decrypt($pm_details['paywithamazon_Merchant_Id'])]);
					//config(['CALLBACK_URL' => url('/billing-amazon-checkout')]);
					config(['CALLBACK_URL' => url('/setupamazon')]);
					config(['CALLBACK_CHECKOUT_URL' => url('amazon/checkoutlogin')]);
					
					
					if(strtoupper(trim($payment_methods_settings['paywithamazon_Transaction_Mode'])) == 'SANDBOX'){
						config(['JS_SERVER_URL' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js?sellerId='.config('MERCHANT_ID')]);
					}else{
						config(['JS_SERVER_URL' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js?sellerId='.config('MERCHANT_ID')]);	
					}
				}
				
				if($PayeMethod->pm_group_name == 'PAYMENT_PAYPALEC')
				{
					if($this->Is_WholeSaler_Allow() == false)
						$IsPaypalExpressCheckout ='No';
					else
						$IsPaypalExpressCheckout ='Yes';
				}
				if($PayeMethod->pm_group_name == 'PAYMENT_PAYWITHAFTERPAY')
				{
					$Afterpay_Checkout ='Yes';
					if(Auth::user() && Auth::user()->eusertype == 'Wholesaler')
						$Afterpay_Checkout ='No';
				}
			}
		}
		$show_AP = 'Yes';
		if($Afterpay_Checkout == "Yes" && $show_AP == "Yes"){
			$this->AfterpayMinMax();
		}
		$CouponCode = $this->GetAllCoupons('CouponCode');
		$CouponCodeSecond = $this->GetAllCoupons('CouponCodeSecond');
		$CustomerID = (Auth::user() ? Session::get('sess_icustomerid'):null);
		if($CouponCode != '')
			$this->ApplyCouponDiscount($CouponCode,$CustomerID);
		if($CouponCodeSecond != '')
			$this->ApplyCouponDiscountSecond($CouponCodeSecond,$CustomerID);
		
		$this->ApplyItemWiseCoupon($CouponCode);
		$this->Is_WholeSaler_Allow(true);
		
		$DiscountInfo = $this->GetAllDiscounts();
		$SubTotal = Session::get('ShoppingCart.SubTotal');
		$TotalValue = ($SubTotal - $DiscountInfo['TotalDiscount']);
		$Attrs['TotalValue'] = $TotalValue;
		
		if(config('Settings.CHECKOUT_SHOIPPINGCART') == "No")
		{
			$this->RemoveFreeGiftCache();
		}
		
		$CreditData = $this->GetCreditLimitAmount();
		$Attrs = array_merge($Attrs,$CreditData);
		if(Session::has('ShoppingCart.Reward_array') && count(Session::get('ShoppingCart.Reward_array')) > 0)
		{
			$Attrs['RemainRewardPoint'] = Session::get('ShoppingCart.Reward_array.RemainRewardPoint');
			$Attrs['TotalRewardPoint'] = Session::get('ShoppingCart.Reward_array.TotalRewardPoint');
			$Attrs['AppliedRewardPoint'] = Session::get('ShoppingCart.Reward_array.AppliedRewardPoint');
		}
		$Attrs['IsVenderItem'] = $IsVenderItem;
		$Attrs['IsCosmo'] = $IsCosmo;
		$Attrs['IsNandansons'] = $IsNandansons;
		$Attrs['IsPerfumePW'] = $IsPerfumePW;
		$Attrs['IsPCA'] = $IsPCA;
		$Attrs['IsMaxaromaTwoDelivery'] = $IsMaxaromaTwoDelivery;
		$Attrs['ISMaxTwoItem'] = $ISMaxTwoItem;
		$Attrs['onlyGCPurchased'] = $onlyGCPurchased;
		$Attrs['onlyAmazonPaypal'] = $onlyAmazonPaypal;
		$Attrs['onlyWireTrabsfer'] = $onlyWireTrabsfer;
		$Attrs['CheckGCPurchasedVal'] = $CheckGCPurchasedVal;
		$Attrs['isCouponsAvailable'] = $this->isCouponsAvailable();
		$Attrs['coupon_number'] = $CouponCode;
		$Attrs['isSecondCouponAvalilabe'] = $this->isSecondCouponsAvailable(); 
		$Attrs['second_coupon_number'] = $CouponCodeSecond;
		$Attrs['isGiftCouponsAvailable'] = $this->isGiftCouponsAvailable();
		$Attrs['Amazon_pay_Checkout'] = $Amazon_pay_Checkout;
		$Attrs['IsPaypalExpressCheckout'] = $IsPaypalExpressCheckout;
		$Attrs['Afterpay_Checkout'] = $Afterpay_Checkout;
		$Attrs['show_AP'] = $show_AP;
		$Attrs['critieostr'] = ($critieostr != '' ? substr($critieostr,0,-1):'');
		$Attrs['allow_gift'] = 0;
		if(strtolower(trim(Session::get('eusertype'))) !="wholesaler" && trim(Session::get('is_dropshipper')) != "Yes" && $this->isGiftCouponsAvailable() == 1)
			$Attrs['allow_gift'] = 1;
		
		$Attrs['FundFlag'] = 0; 
		$Attrs['available_funds'] = 0;
		if(Auth::user() && Auth::user()->is_dropshipper == "Yes" && Auth::user()->eusertype == "Wholesaler")
		{
			$Attrs['FundFlag'] = 1; 
			if(Auth::user()->available_funds > 0)
				$Attrs['available_funds'] = Auth::user()->available_funds;
		}
		
		return $Attrs;
	}
	
	function ApplyAutoRewardDiscount()
    {
		Session::forget('ShoppingCart.Reward_array');
		$NetTotal = $this->GetNetTotal();
		$AllDiscount = $this->GetAllDiscounts();
		$discount = $AllDiscount['TotalDiscount'];
		$subtotal = NumberFormat($NetTotal - $discount);
		$reward_discount = 0;
		if(Session::get('etype') == 'M' && strtolower(Session::get('eusertype'))=='retailer')
		{
			$Customer_Reward = Customer::where('customer_id','=',Session::get('sess_icustomerid'))->where('status','=','1')->get();
			$Redeem_Reward = RewardRule::where('erewardrule','=','redeem')->get();
			$Max_Reward = RewardRule::where('erewardrule','=','max')->get();

			if($Customer_Reward && $Customer_Reward->count() > 0 && $Max_Reward && $Max_Reward->count($Max_Reward) > 0 )
			{
				if($Customer_Reward[0]['iRewardpoint'] >= $Max_Reward[0]["fcharge"])
				{
					$refer_amount = ($Customer_Reward[0]['iRewardpoint']/$Redeem_Reward[0]["fcharge"]);
					$reward_discount = (int)$refer_amount*$Redeem_Reward[0]["forderamount"];
					$remain_count = $Redeem_Reward[0]["fcharge"] * (int)$refer_amount;
					$reward_remaining = $Customer_Reward[0]['iRewardpoint'] - $remain_count;
					$Total_Reward_Point = $Customer_Reward[0]['iRewardpoint'];
					
					$temp_reward =array();
					if(NumberFormat($reward_discount) < $subtotal)
					{
						$temp_reward['RemainRewardPoint'] = NumberFormat($reward_remaining);
						$temp_reward['TotalRewardPoint'] = NumberFormat($Total_Reward_Point);
						$temp_reward['RewardDiscount'] = NumberFormat($reward_discount);
						$temp_reward['AppliedRewardPoint'] = NumberFormat($remain_count);
						Session::put('ShoppingCart.Reward_array',$temp_reward);
					}
				}
			}
			return NULL;
		}
	}
	
	public function GetCartAttribute($Attribute='')
	{
		if($Attribute != '')
		{
			$CartAttr = $this->SetCartAttributes();
			if(isset($CartAttr[$Attribute]))
				return $CartAttr[$Attribute];
		}
	}
	public function isGiftCouponsAvailable()
	{
		$CouponRS = GiftCertificate::where('remaining_value','>',0)->where('status','=','1')->get();
		if(count($CouponRS) > 0)
			return true;
		else
			return false;
	}
	
	public function StoreShopCartInCookie()
	{
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			$tempCart = Session::get('ShoppingCart.Cart');
			$ArrMyCookie = array();
			for($p = 0; $p < count($tempCart); $p++) 
			{
                if($tempCart[$p]['IsYotpoFreeProduct'] == 'Yes')
                    continue;
				$temp_ary = array();
				$temp_ary['SKU'] = $tempCart[$p]['SKU'];
				$temp_ary['Qty'] = $tempCart[$p]['Qty'];

			   if($tempCart[$p]['SKU'] == config('Settings.GIFT_CERTIFICATE_SKU') || $tempCart[$p]['SKU'] == config('Settings.GIFT_CERTIFICATE_SKU1') || $tempCart[$p]['SKU'] == config('Settings.GIFT_CERTIFICATE_SKU2'))
				{
					$temp_ary['RecipientName']		= $tempCart[$p]['RecipientName'];
					$temp_ary['RecipientEmail']		= $tempCart[$p]['RecipientEmail'];
					$temp_ary['YourName']			= $tempCart[$p]['YourName'];
					$temp_ary['YourEmail']			= $tempCart[$p]['YourEmail'];
					$temp_ary['Subject']			= $tempCart[$p]['Subject'];
					$temp_ary['Message']			= $tempCart[$p]['Message'];
					$temp_ary['Signature']			= $tempCart[$p]['Signature'];
					$temp_ary['DeliveryDate']		= $tempCart[$p]['DeliveryDate'];
					$temp_ary['GCPrice'] 			= $tempCart[$p]['Price'];
					$temp_ary['GCPrice'] 			= $tempCart[$p]['Price'];
					$temp_ary['SKU'] 				= $tempCart[$p]['SKU'];
					$temp_ary['GiftImage'] 			= $tempCart[$p]['GiftImage'];
				}
				$ArrMyCookie[] = $temp_ary;
			}
			 ## First Delete The Old Cart From Table
			if(Cookie::has("MY_SHOP_CART_COOKIE") && Cookie::get("MY_SHOP_CART_COOKIE") != "" )
			{
				$cookie_id = Cookie::get("MY_SHOP_CART_COOKIE");
				Shoppingcart::where('cookie_id','=',$cookie_id)->where('customer_id','=',0)->delete();
			}	
			if(count($ArrMyCookie)>0)
			{
				$cookie_id = time()."_".Session::getId();
				Cookie::queue(Cookie::make('MY_SHOP_CART_COOKIE',$cookie_id,time()+60*60*24*15));
				
				if(Auth::user())
				{
					$result = Shoppingcart::where('customer_id','=',Session::get('sess_icustomerid'))->get();
				
					if($result && $result->count() <=0)
					{
						$InsertCart = array(
										'customer_id' 		=> Session::get('sess_icustomerid'),
										'cookie_id' 		=> $cookie_id,
										'cart_string' 		=> serialize($ArrMyCookie),
										'created_date' 		=> date("Y-m-d H:i:s")
									);
						DB::table('pu_shoppingcart')->insert($InsertCart);
					}else{
						$UpdateCart = array(
										'cookie_id' 		=> $cookie_id,
										'cart_string' 		=> serialize($ArrMyCookie),
										'created_date' 		=> date("Y-m-d H:i:s")
								  );
						DB::table('pu_shoppingcart')->where('customer_id','=',Session::get('sess_icustomerid'))->update($UpdateCart);		  
					}
				}else{
					$InsertCart = array(
									'customer_id' 		=> '0',
									'cookie_id' 		=> $cookie_id,
									'cart_string' 		=> serialize($ArrMyCookie),
									'created_date' 		=> date("Y-m-d H:i:s")
								);
					DB::table('pu_shoppingcart')->insert($InsertCart);
				}
			}
		} else {
			if(Auth::user())
			{
				Shoppingcart::where('customer_id','=',Session::get('sess_icustomerid'))->delete();
			} else {
				$cookie_id = Cookie::get("MY_SHOP_CART_COOKIE");
				Shoppingcart::where('cookie_id','=',$cookie_id)->where('customer_id','=',0)->delete();
				
				$cookie_id = time()."_".Session::getId();
				Cookie::queue(Cookie::make('MY_SHOP_CART_COOKIE',$cookie_id,time()+60*60*24*15));
			}
		}
	}
	
	public function ApplyDogoDiscount()
	{
		$DogoDiscount = 0;
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0 )
		{
			$GiftCertiTotal = 0;
			$GiftCertiCount = 0;
			if(Session::has('ShoppingCart.GiftCertiTotal') && Session::get('ShoppingCart.GiftCertiTotal') != '')
				$GiftCertiTotal = Session::get('ShoppingCart.GiftCertiTotal');
			if(Session::has('ShoppingCart.GiftCertiCount') && Session::get('ShoppingCart.GiftCertiCount') != '')
				$GiftCertiCount = Session::get('ShoppingCart.GiftCertiCount');
			
			$subTotal = Session::get('ShoppingCart.SubTotal') - $GiftCertiTotal;
			
			$Cart = Session::get('ShoppingCart.Cart');
			$TotalItem 	= Session::get('ShoppingCart.TotalItemInCart');
			$DogoDiscountFlag = '';
			if($subTotal <= 0 || $TotalItem <= 0)
			{
				Session::put('ShoppingCart.DogoDiscount',0);
				return null;
			}
			if(Auth::user() && Session::get('eusertype') == "Wholesaler")
			{
				Session::put('ShoppingCart.DogoDiscount',0);
				return null;
			}
			$CouponCode = $this->GetAllCoupons('CouponCode');
			if($CouponCode !='')
			{
				$coupon_res = Coupon::select('bogodiscount_flag')->where('coupon_number','=',$CouponCode)
								->where('status','=','1')
								->where('start_date','<=',DB::raw('curdate()'))
								->where('end_date','>=',DB::raw('curdate()'))
								->get();
				if($coupon_res && $coupon_res->count() > 0)
				{
					if($coupon_res[0]->bogodiscount_flag == "No")
					{
						Session::put('ShoppingCart.DogoDiscount',0);
						Session::put('ShoppingCart.BogoDiscountFlag','');
						return null;
					}
				}
			}
			
			$DogoRS = BogoDiscount::where('start_date','<=',DB::raw('curdate()'))
						->where('end_date','>=',DB::raw('curdate()'))
						->where('status','=','1')->orderBy('bogo_discount_id','desc')->get();
			
			if($DogoRS && $DogoRS->count() > 0)
			{
			   $DogoDiscount = 0;
			   for($i=0;$i < $DogoRS->count();$i++)
			   {
				   if($DogoRS[$i]["orders"]=='2')
				   {
						$QtySKU = trim($DogoRS[$i]['sku']);
						########### For Multiple SKU ###############
						$arr_QtySKU  = explode(",",$QtySKU);
						$arr_QtySKU  = array_unique(array_map('trim',$arr_QtySKU));
						$arr_QtySKU  = array_filter($arr_QtySKU, 'strlen');
						$Matched_Item_Total = 0;
						$IS_Any_Matched 	= 0;
						if(is_array($arr_QtySKU) and !empty($arr_QtySKU))
						{
							$CartVal  = Session::get('ShoppingCart.Cart');						
							$tempCart = array();
							for($a=0;$a<count($CartVal); $a++)
							{
								$FreeGiftCheck = isset($CartVal[$a]["IS_Free_Gift"])?$CartVal[$a]["IS_Free_Gift"]:'No';
								if($CartVal[$a]['SKU']!= config('global.GIFT_CERTIFICATE_SKU') && $CartVal[$a]['SKU']!= config('global.GIFT_CERTIFICATE_SKU1') && in_array($CartVal[$a]['SKU'] , $arr_QtySKU) && $CartVal[$a]["IsDealProducts"]!="Yes" && $FreeGiftCheck != "Yes")
								{
									$tempCart[] = $CartVal[$a];
								}
							}
							$modCount  = intdiv(count($tempCart), 2);
							$prices = array_column($tempCart, 'Price');
							
							if($DogoRS[$i]['sortBy']=="High")
							{
								rsort($prices);
							}
							else if($DogoRS[$i]['sortBy']=="Low")
							{
								sort($prices);
							}
							
							
							for ($a=0; $a<$modCount; $a++)
							{
								if($DogoRS[$i]["type"]=='1')
								{
									$DogoDiscountPrice = ($prices[$a]*($DogoRS[$i]["bogo_discount_amount"]/100));
									$DogoDiscount = $DogoDiscount + $DogoDiscountPrice;		
								}else{
									$DogoDiscount = $DogoDiscount + $DogoRS[$i]["bogo_discount_amount"];	
								}
							} 
						}
					}else if($DogoRS[$i]["orders"]=='0'){
						$QtyCatID = trim($DogoRS[$i]['sku']);
						$arr_QtyCatID    = explode(",",$QtyCatID);

						$DogoDiscount = 0;
						$found_cat = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_CatID = Category::where('status','=','1')->whereIn('category_id',$arr_QtyCatID)->get();
						$arr_active_CatID = array();
						for($h=0;$h<count($Res_active_CatID);$h++)
						{
							$arr_active_CatID[] = $Res_active_CatID[$h]['category_id'];
						}
						
						if(count($arr_active_CatID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart  	    = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}
							$ProdIds = ProductsCategory::select('products_id')->distinct()
								->whereIn('category_id',$arr_active_CatID)
								->whereIn('products_id',$temp_prod_id)->get();
							
							$cat_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$cat_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
							$total_qty = 0;
							$total_price = 0;
							$total_percentage = false;
							
							$CartVal  = Session::get('ShoppingCart.Cart');						
							$tempCart = array();
							for($a=0;$a<count($CartVal); $a++)
							{
								if(!isset($CartVal[$a]["IS_Free_Gift"]))
									$CartVal[$a]["IS_Free_Gift"]="No";
								if(!isset($CartVal[$a]["IsDealProducts"]))
									$CartVal[$a]["IsDealProducts"]="No";
								if($CartVal[$a]['SKU']!= config('Settings.GIFT_CERTIFICATE_SKU') && $CartVal[$a]['SKU']!= config('Settings.GIFT_CERTIFICATE_SKU1') && $CartVal[$a]['SKU'] != config('Settings.GIFT_CERTIFICATE_SKU2') && in_array($CartVal[$a]['ProductID'] , $cat_prod_id) && $CartVal[$a]["IsDealProducts"]!="Yes" &&  isset($CartVal[$a]["IS_Free_Gift"]) && $CartVal[$a]["IS_Free_Gift"]!="Yes")
									$tempCart[] = $CartVal[$a];
							}
							
							$modCount  = intdiv(count($tempCart), 2);
							$prices = array_column($tempCart, 'Price');
							
							if($DogoRS[$i]['sortBy']=="High")
							{
								rsort($prices);
							}
							else if($DogoRS[$i]['sortBy']=="Low")
							{
								sort($prices);
							}
							
							for ($a=0; $a<$modCount; $a++)
							{
								if($DogoRS[$i]["type"]=='1')
								{
									$DogoDiscountPrice = ($prices[$a]*($DogoRS[$i]["bogo_discount_amount"]/100));
									$DogoDiscount = $DogoDiscount + $DogoDiscountPrice;		
								}else{
									$DogoDiscount = $DogoDiscount + $DogoRS[$i]["bogo_discount_amount"];	
								}
							} 
						}
					}else if($DogoRS[$i]["orders"]=='1'){
						$QtySKU = trim($DogoRS[$i]['sku']);
						########### For Multiple SKU ###############
						$QtyBrandID    	= trim($DogoRS[$i]['sku']); // Category IDS
						$arr_QtyBrandID    = explode(",",$QtyBrandID);

						$Dogodiscount = 0;
						$found_brand = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_BrandID = Manufacture::where('status','=','1')->whereIn('imanufactureid',$arr_QtyBrandID)->get();
						$arr_active_BrandID = array();

						for($h=0;$h<$Res_active_BrandID->count();$h++)
						{
							$arr_active_BrandID[] = $Res_active_BrandID[$h]['imanufactureid'];
						}

						if(count($arr_active_BrandID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart  	    = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}
							$ProdIds = Products::select('products_id')->distinct()
								->whereIn('imanufactureid',$arr_active_BrandID)
								->whereIn('products_id',$temp_prod_id)->get();

							$brand_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$brand_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
							
							$total_qty = 0;
							$total_price = 0;
							$total_percentage = false;
							$tempCart = array();
							$CartVal  = Session::get('ShoppingCart.Cart');						
							
							for($a=0;$a<count($CartVal); $a++)
							{
								if(!isset($CartVal[$a]["IS_Free_Gift"]))
									$CartVal[$a]["IS_Free_Gift"]="No";
								if(!isset($CartVal[$a]["IsDealProducts"]))
									$CartVal[$a]["IsDealProducts"]="No";
								
								if($CartVal[$a]['SKU']!= config('Settings.GIFT_CERTIFICATE_SKU') && $CartVal[$a]['SKU']!= config('Settings.GIFT_CERTIFICATE_SKU1') && $CartVal[$a]['SKU'] != config('Settings.GIFT_CERTIFICATE_SKU2') && in_array($CartVal[$a]['ProductID'] , $brand_prod_id) && $CartVal[$a]["IsDealProducts"]!="Yes" && $CartVal[$a]["IS_Free_Gift"]!="Yes")
									$tempCart[] = $CartVal[$a];
							}
							$modCount  = intdiv(count($tempCart), 2);
							$prices = array_column($tempCart, 'Price');
							
							if($DogoRS[$i]['sortBy']=="High")
							{
								rsort($prices);
							}
							else if($DogoRS[$i]['sortBy']=="Low")
							{
								sort($prices);
							}
						  
							for ($a=0; $a<$modCount; $a++)
							{
								if($DogoRS[$i]["type"]=='1')
								{
									$DogoDiscountPrice = ($prices[$a]*($DogoRS[$i]["bogo_discount_amount"]/100));
									$DogoDiscount = $DogoDiscount + $DogoDiscountPrice;
								}else{
									$DogoDiscount = $DogoDiscount + $DogoRS[$i]["bogo_discount_amount"];	
								}
							}
						}
					}
				}
			}else{
				$DogoDiscount = 0;
			}
			Session::put('ShoppingCart.DogoDiscount',NumberFormat($DogoDiscount));
		}
	}
	
	public function RemoveFreeGiftValueProduct($sku)
	{
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')))
		{
			$count = count(Session::get('ShoppingCart.Cart'));
			$TempCart = array();
			$CartInfo = Session::get('ShoppingCart.Cart');
			foreach($CartInfo as $a => $Cart)
			{
				if($Cart['SKU'] == $sku)
				{
					if(isset($Cart["IS_Free_Gift"]) && $Cart['IS_Free_Gift']=="Yes")
						unset($Cart);
				}else if($Cart['SKU']==""){
					unset($Cart);
				}else{
					$TempCart[] = $Cart;
				}
			}
			Session::put('ShoppingCart.Cart',$TempCart);
			$this->CalculateSubTotal();
		}
	}
	
	public function ApplyItemWiseCoupon($couponCode)
	{
		if(Session::has('ShoppingCart.Cart'))
		{	
			$TotalItems = count(Session::get('ShoppingCart.Cart'));
			$Cart = Session::get('ShoppingCart.Cart');
			if($couponCode=='')
			{
				for($p=0;$p<$TotalItems;$p++)
					$Cart[$p]["ItemWiseCouponDiscount"] = 0;
				Session::put('ShoppingCart.Cart',$Cart);
				return null;
			}
			$UserType = 'Retailer';
			
			if(Auth::user())
			{
				if(Auth::user()->eusertype != '' && Session::get('sess_icustomerid') != '')
					$UserType = Auth::user()->eusertype;		
			}
			
			$CouponRS = Coupon::select('bogodiscount_flag')->where('coupon_number','=',$couponCode)
								->where('status','=','1')
								->where('start_date','<=',DB::raw('curdate()'))
								->where('end_date','>=',DB::raw('curdate()'))
								->where('coupon_user_type','=',$UserType)
								->get();	
			
			if($CouponRS && $CouponRS->count() > 0)
			{
				$switchCase = $CouponRS[0]['orders'];
				$TotalCouponCount = $CouponRS->count();
				if($TotalItems > 0)
				{
					if($switchCase=='3')
					{
						$CouponCatID    	= trim($CouponRS[0]['sku']); // Category IDS
						$arr_CouponCatID    = explode(",",$CouponCatID);

						$CouponDiscount = 0;
						$found_cat = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_CatID = Category::where('status','=','1')->whereIn('category_id',$arr_CouponCatID)->get();
						$arr_active_CatID = array();

						for($h=0;$h<$Res_active_CatID->count();$h++)
						{
							$arr_active_CatID[] = $Res_active_CatID[$h]['category_id'];
						}
						if(count($arr_active_CatID) > 0 )
						{
							$tempCart  	    = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}
							$ProdIds = Products::select('products_id')->distinct()
										->whereIn('imanufactureid',$arr_active_BrandID)
										->whereIn('products_id',$temp_prod_id)->get();
							$cat_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$cat_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
						}
					}
					if($switchCase=='6')
					{

						$CouponBrandID    	= trim($CouponRS[0]['sku']); // Brand IDS
						$arr_CouponBrandID  = explode(",",$CouponBrandID);

						$CouponDiscount = 0;
						$found_brand = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_BrandID = Manufacture::where('status','=','1')->whereIn('imanufactureid',$arr_CouponBrandID)->get();
						$arr_active_BrandID = array();

						for($h=0;$h<count($Res_active_BrandID);$h++)
						{
							$arr_active_BrandID[] = $Res_active_BrandID[$h]['imanufactureid'];
						}

						if(count($arr_active_BrandID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart  	    = Session::get('ShoppingCart.Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}
							
							$ProdIds = Products::select('products_id')->distinct()
											->whereIn('imanufactureid',$arr_active_BrandID)
											->whereIn('products_id',$temp_prod_id)->get();
							$brand_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$brand_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
						}
					}
					$ExcludeSKUListArr = array();
					$ExcludeSKUListArr  = explode(",",$CouponRS[0]["exclude_sku"]);
					$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
					$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
					$IsMatchItem = 0;
					for($p=0;$p<$TotalItems;$p++)
					{	
						if((isset($Cart[$p]["IsDealProducts"]) && $Cart[$p]["IsDealProducts"]=="No") || ((isset($Cart[$p]["IsDealProducts"]) && $Cart[$p]["IsDealProducts"]=="Yes") && ($Cart[$p]["DealDiscountFlag"]=="Yes" ||  $CouponRS[0]["dealdiscount_flag"]=="Yes")))
						{
							if($switchCase=='3')
							{
								if(!in_array($Cart[$p]["SKU"],$ExcludeSKUListArr) && in_array($Cart[$p]['ProductID'] , $cat_prod_id))
								{
									if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}
									else if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}
									else
									{
										if($Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
										{
											$IsMatchItem = $IsMatchItem + 1;
										}
									}
								}
							}
							else if($switchCase=='1')
							{
								$CouponSKU = trim($CouponRS[0]['sku']);

								$arr_CouponSKU  = explode(",",$CouponSKU);
								$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
								$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');
								if(in_array($Cart[$p]["SKU"] , $arr_CouponSKU) && !in_array($Cart[$p]["SKU"],$ExcludeSKUListArr))
								{
									if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}
									else if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}
									else
									{
										if($Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
										{
											$IsMatchItem = $IsMatchItem + 1;
										}
									}
								}
							}
							else if($switchCase=='6')
							{
								if(!in_array($Cart[$p]["SKU"],$ExcludeSKUListArr) && in_array($Cart[$p]['ProductID'] , $brand_prod_id))
								{
									if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}
									else if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}
									else
									{
										if($Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
										{
											$IsMatchItem = $IsMatchItem + 1;
										}
									}
								}
							}
							else
							{
								if(!in_array($Cart[$p]["SKU"],$ExcludeSKUListArr))
								{
									if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}
									else if($Cart[$p]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										$IsMatchItem = $IsMatchItem + 1;
									}
									else
									{
										if($Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$p]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
										{
											$IsMatchItem = $IsMatchItem + 1;
										}
									}
								}
							}
						}
					}
					$tempCart = [];
					for($i=0;$i<$TotalItems;$i++)
					{

						if((isset($Cart[$p]["IsDealProducts"]) && $Cart[$i]["IsDealProducts"]=="No") || (isset($Cart[$p]["IsDealProducts"])&& $Cart[$i]["IsDealProducts"]=="Yes" && ($Cart[$i]["DealDiscountFlag"]=="Yes" ||  $CouponRS[0]["dealdiscount_flag"]=="Yes")))
						{
							$CouponDiscount  = 0;

							if(!in_array($Cart[$i]["SKU"],$ExcludeSKUListArr))
							{
								switch ($switchCase)
								{
									case '0' :

									if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										if($CouponRS[0]['type'] == 1 )
											$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
										else
											$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

										$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
									}
									else if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
									{
										if($CouponRS[0]['type'] == 1 )
											$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
										else
											$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

										$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
									}
									else
									{
										if($Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
										{
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}
									}

									$CouponDiscount = number_format($CouponDiscount, 2, '.', ',');

									$Cart[$i]["ItemWiseCouponDiscount"] = $CouponDiscount;
									break;
									case '1' :
									$CouponSKU = trim($CouponRS[0]['sku']);

									$arr_CouponSKU  = explode(",",$CouponSKU);
									$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
									$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');
									if(in_array($Cart[$i]["SKU"] , $arr_CouponSKU))
									{
										if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
										{
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}
										else if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
										{
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}
										else
										{
											if($Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
											{
												if($CouponRS[0]['type'] == 1 )
													$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
												else
													$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

												$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
											}
										}
									}

									$CouponDiscount = NumberFormat($CouponDiscount);
									$Cart[$i]["ItemWiseCouponDiscount"] = $CouponDiscount;

									break;
									case '2' :
									break;
									case '3' :
									if(in_array($Cart[$i]['ProductID'] , $cat_prod_id))
									{
										if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
										{
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}
										else if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
										{
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}
										else
										{
											if($Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
											{
												if($CouponRS[0]['type'] == 1 )
													$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
												else
													$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

												$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
											}
										}
										$CouponDiscount = $this->NumberFormat($CouponDiscount);
										$Cart[$i]["ItemWiseCouponDiscount"] = $CouponDiscount;
									}
									else
									{
										$Cart[$i]["ItemWiseCouponDiscount"] =0;
									}
									break;
									case '6' :
									if(in_array($Cart[$i]['ProductID'] , $brand_prod_id))
									{
										if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU') && $CouponRS[0]["count_gc_purchase"]=='1')
										{
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}
										else if($Cart[$i]["SKU"]==config('global.GIFT_CERTIFICATE_SKU1') && $CouponRS[0]["count_gc_purchase"]=='1')
										{
											if($CouponRS[0]['type'] == 1 )
												$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
											else
												$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

											$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
										}
										else
										{
											if($Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU') && $Cart[$i]["SKU"]!=config('global.GIFT_CERTIFICATE_SKU1'))
											{
												if($CouponRS[0]['type'] == 1 )
													$CouponDiscount = ($Cart[$i]['TotPrice'] * ($CouponRS[0]['discount']/100) );
												else
													$CouponDiscount =  ($CouponRS[0]['discount']/$IsMatchItem);

												$CouponDiscount = $Cart[$i]['TotPrice'] - $CouponDiscount;
											}

										}
										$CouponDiscount = NumberFormat($CouponDiscount);
										$Cart[$i]["ItemWiseCouponDiscount"] = $CouponDiscount;
									}
									else
									{
										$Cart[$i]["ItemWiseCouponDiscount"] =0;
									}
									break;
									Default:
									$Cart[$i]["ItemWiseCouponDiscount"] =0;
									break;
								}
							}
							else
							{
								$Cart[$i]["ItemWiseCouponDiscount"] = 0;
							}
						}
						else
						{
							$Cart[$i]["ItemWiseCouponDiscount"] = 0;
						}
					}
					Session::put('ShoppingCart.Cart',$Cart);
				}
			}
		}	
	}
	
	public function Is_WholeSaler_Allow($is_set_msg=false)
	{
		if(Auth::user() && Auth::user()->is_dropshipper != 'Yes' && strtolower(Auth::user()->eusertype) == 'wholesaler')
		{
			if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
			{
				$order_sub_total  = Session::get('ShoppingCart.SubTotal');
				$w_min_order_amt  = NumberFormat(config('Settings.WHOLESALER_MIN_ORDER_AMOUNT'));
				if($order_sub_total < $w_min_order_amt)
				{
					if($is_set_msg == true)
					{
						$msg = "For wholesaler minimum order amount should be ".$this->Make_Price($w_min_order_amt,true);
						Session::flash('CartError',$msg);
					}
					return false;
				}else{
					return true;
				}
			}
		}
		return true;
	}
	
	public function FreeGiftInsertProductValue($products_id,$freeproductsid)
	{
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			$count = count (Session::get('ShoppingCart.Cart'));
			$Cart = Session::get('ShoppingCart.Cart');
			$Cart = array_values($Cart);
			for($a=0; $a<$count; $a++)
			{
				/*if($Cart[$a]['SKU'] == $sku && $sku !='' )
				{
					unset($Cart[$a]);
				}*/
				if(isset($Cart[$a]["IS_Free_Gift"]) && $Cart[$a]['IS_Free_Gift']=="Yes")
				{
				  unset($Cart[$a]);
				}
			}
			$Cart = array_values($Cart);
			Session::put('ShoppingCart.Cart',$Cart);
			
			$FreeGiftProd = Products::where('products_id','=',$products_id)->where('status','=','1')->get();
			if($FreeGiftProd && $FreeGiftProd->count() > 0)
			{
				$free_gift_res = $this->SetProduct($FreeGiftProd[0]);
				if($free_gift_res->current_stock > 0 || ($free_gift_res->cosmo_current_stock > 0 && $free_gift_res->cosmo_sku!='') || ($free_gift_res->nandansons_current_stock > 0 && $free_gift_res->nandansons_sku!='')  || ($free_gift_res->pca_current_stock > 0 && $free_gift_res->pca_sku!=''))
				{
					if(file_exists(config('global.PRD_THUMB_IMG_PATH').$free_gift_res->image) && !empty($free_gift_res->image))
						$thumb_image = config('global.PRD_THUMB_IMG_URL').$free_gift_res->image;
					else
						$thumb_image = config('global.NO_IMAGE_THUMB');

					//$thumb_image = str_replace(config('global.SITE_URL'),config('global.SECURED_PATH'),$thumb_image);

					$free_gift_res->prod_image ='<img src="'.$thumb_image.'" border="0" width="125" />';
					$free_gift_res->image_forpopup ='<img src="'.$thumb_image.'" border="0" width="75" />';
					$free_gift_res->billing_image ='<img src="'.$thumb_image.'" border="0" width="195" alt="'.$free_gift_res->product_name.'" title="'.$free_gift_res->product_name.'"/>';

					$VendorSKU 		= "";
					$IsCosmo  		= "";
					$IsNandansons	= "";
					$IsPerfumePW	= "";
					$IsPCA	= "";
						
					if($free_gift_res->stock == "Out")
					{
						if($free_gift_res->cosmo_sku !='' &&  $free_gift_res->cosmo_current_stock > 0 )
						{
							$IsCosmo = "Yes";
							$VendorSKU = $free_gift_res->cosmo_sku;
						}
						else if($free_gift_res->pca_sku !='' &&  $free_gift_res->pca_current_stock > 0)
						{
							$IsPCA  = "Yes";
							$VendorSKU = $free_gift_res->pca_sku;
						}else if($free_gift_res->nandansons_sku !='' &&  $free_gift_res->nandansons_current_stock > 0)
						{
							$IsNandansons = "Yes";
							$VendorSKU = $free_gift_res->nandansons_sku;
						}
					}
					$temp_ary = array();
					
					if($free_gift_res->WebsiteStock == "In")
					{
						$temp_ary['IsMaxaromaTwoDelivery'] = $free_gift_res->maxtwodaydelivery;
					}
								
					$temp_ary['ProductID']   		= $free_gift_res->products_id;
					$temp_ary['SKU']         		= "GIFT-".$free_gift_res->sku;
					$temp_ary['ORGSKU']         	= $free_gift_res->sku;
					$temp_ary['ProductName'] 		= stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$free_gift_res->product_name));
					$temp_ary['short_description'] 	= strip_tags(stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$free_gift_res->short_description)));
					$temp_ary['Billing_Image'] 		= $free_gift_res->billing_image;
					$temp_ary['Price']       		= 0;
					$temp_ary['Qty'] 		 		= 1;
					$temp_ary['TotPrice']    		= 0;
					$temp_ary['Image']       		= $free_gift_res->prod_image;
					$temp_ary['Prod_URL']       	= "";
					$temp_ary['IS_Free_Gift']       = "Yes";
					$temp_ary['image_forpopup']		= $free_gift_res->image_forpopup;
					$temp_ary['freeproductsid']		= $freeproductsid;
					$temp_ary['VendorSKU']			= $VendorSKU;
					$temp_ary['IsCosmo']			= $IsCosmo;
					$temp_ary['IsNandansons']		= $IsNandansons;
					$temp_ary['IsPerfumePW']		= $IsPerfumePW;
					$temp_ary['IsPCA']				= $IsPCA;
					$temp_ary['ImanufactureID']		= $free_gift_res->imanufactureid;
					$temp_ary['IsDealProducts']		= "No";
					$temp_ary['DealDiscountFlag']	= "No";
					$temp_ary['dealdiscount_flag']	= "No";
					
					$Cart[]=$temp_ary;
					Session::put('ShoppingCart.Cart',$Cart);
					$this->CalculateSubTotal();
				}					
			}
		}
	}
	public function CheckFreeGiftInCart($TotalValue)
	{
		$is_free_gift_cart = 'No';
		$Price = 0;
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			$cartitem = Session::get('ShoppingCart.Cart');
			
			$ManufactureStr = "";
			
			if(count($cartitem) > 0)
			{
				for($d=0;$d<count($cartitem);$d++)
				{
					if(empty($cartitem[$d]["IS_Free_Gift"]))
					{
						if(isset($cartitem[$d]["ImanufactureID"]))
						$ManufactureStr.= $cartitem[$d]["ImanufactureID"].",";
					}
					$Price+=NumberFormat($cartitem[$d]["TotPrice"]);
				}
			}
			
			if(trim($ManufactureStr)!='')
			{
				$ManufactureStr = substr(trim($ManufactureStr),0,-1);
			}
			
			
			
			
					$free_gift_res = FreeGiftProduct::where('price_end_range','>=',$TotalValue)
								->where('price_start_range','<=',$TotalValue)
								->where('status','=','1')->where('flag_range','=','')
								->where('start_date','<=',date('Y-m-d'))
								->where('end_date','>=',date('Y-m-d'))
								->limit(1)->get();	
			if($free_gift_res->count()<=0)
			{
				
				$FreeGiftQry = DB::table('pu_free_gift_product as f')
								->join('pu_freegift_brand as b','f.products_id','=','b.products_id')
								->where('f.price_end_range','>=',$TotalValue)
								->where('f.price_start_range','<=',$TotalValue)
								->where('f.status','=','1')
								->where('f.start_date','<=',date('Y-m-d'))
								->where('f.end_date','>=',date('Y-m-d'))
								->where('f.flag_range','=','Brand');
				if(trim($ManufactureStr)!='')
				{
					$ExpManufactureStr = explode(",",$ManufactureStr);
					$FreeGiftQry->whereIn('b.imanufactureid',$ExpManufactureStr);
				}					
				$free_gift_res = $FreeGiftQry->limit(1)->get();
				
			
				
				if($free_gift_res && $free_gift_res->count()<=0)
				{
					$FreeGiftQry = DB::table('pu_free_gift_product as f')
								->join('pu_freegift_brand as b','f.products_id','=','b.products_id')
								->where('f.price_end_range','<=',$TotalValue)
								//->where('f.price_start_range','<=',$TotalValue)
								->where('f.status','=','1')
								->where('f.start_date','<=',date('Y-m-d'))
								->where('f.end_date','>=',date('Y-m-d'))
								->where('f.flag_range','=','Brand');
					if(trim($ManufactureStr)!='')
					{
						$ExpManufactureStr = explode(",",$ManufactureStr);
						$FreeGiftQry->whereIn('b.imanufactureid',$ExpManufactureStr);
					}					
					$free_gift_res = $FreeGiftQry->orderBy('price_end_range','desc')->limit(1)->get();			
					
					if($free_gift_res && $free_gift_res->count()<=0)
					{
						$free_gift_res = FreeGiftProduct::where('price_end_range','<=',$TotalValue)
								->where('status','=','1')->where('flag_range','=','')
								->where('start_date','<=',date('Y-m-d'))
								->where('end_date','>=',date('Y-m-d'))
								->orderBy('price_end_range','desc')->limit(1)->get();		
					}
				}
			}		
			$is_free_gift_cart = "No";
			//dd($free_gift_res);
			if(count($cartitem) > 0 )
			{
				if($free_gift_res->count() > 0)
				{
					//dd($free_gift_res);	
					$is_exclude_item = "No";
					$flag = "No";
					for($i=0;$i<count($cartitem);$i++)
					{
						$exculde_skulist_arr = [];
						if(trim($free_gift_res[0]->exclude_sku) != '')
							$exculde_skulist_arr = explode("#",trim($free_gift_res[0]->exclude_sku));
						
						
						
						if(count($exculde_skulist_arr) > 0)
						{
							$cartitem[$i]["IS_Free_Gift"] = (isset($cartitem[$i]["IS_Free_Gift"]) && $cartitem[$i]["IS_Free_Gift"] != ''?$cartitem[$i]["IS_Free_Gift"]:'');
							if(in_array($cartitem[$i]["SKU"],$exculde_skulist_arr) && $cartitem[$i]["IS_Free_Gift"]=="")
							{
								if($flag=="Yes")
								{
									$is_exclude_item = "No";
									break;
								}
								$flag = "No";
								$is_exclude_item = "Yes";
								
							}
							else if(isset($cartitem[$i]["IS_Free_Gift"]) && $cartitem[$i]["IS_Free_Gift"]=="")
							{
								if($is_exclude_item=="Yes")
								{
									$is_exclude_item = "No";
									break;
								}
								$is_exclude_item = "No";
								$flag="Yes";
							}
						}
					}
					
					if($is_exclude_item=="Yes")
					{
						for($i=0;$i<count($cartitem);$i++)
						{
							 if(isset($cartitem[$i]["IS_Free_Gift"]) && $cartitem[$i]["IS_Free_Gift"]=="Yes")
							 {
								 $is_free_gift_cart = "Yes";
								 $this->RemoveFreeGiftValueProduct($cartitem[$i]["SKU"]);
								 return $is_free_gift_cart;
							 }
						}
					}
					//echo $is_exclude_item; exit;
					
					if($is_exclude_item=="Yes")
					{
						$is_free_gift_cart = "Yes";
						return $is_free_gift_cart;
					}
					$is_removed = "No";
					if($free_gift_res[0]->flag_range=="Brand")
					{
						$brand_res_val = FreegiftBrand::where('products_id','=',$free_gift_res[0]->products_id)->get();
						
						$TotalAvilBrand = $brand_res_val->count();

						if($brand_res_val && $brand_res_val->count() >  0 )
						{
							$NewManufactureArr = array();
							for($i=0;$i<$TotalAvilBrand;$i++)
							{
								$NewManufactureArr[] = $brand_res_val[$i]["imanufactureid"];
							}
							
							
							$is_removed = "No";
							$flag = "No";
							
							if(count($NewManufactureArr) > 0 )
							{
								for($i=0;$i<count($cartitem);$i++)
								{
									if(empty($cartitem[$i]["IS_Free_Gift"])){
										$exculde_skulist_arr = [];
										if(trim($free_gift_res[0]->exclude_sku) != '')
											$exculde_skulist_arr = explode("#",trim($free_gift_res[0]->exclude_sku));
										 
										if(isset($cartitem[$i]["ImanufactureID"]) && !in_array($cartitem[$i]["ImanufactureID"],$NewManufactureArr))
										{
											if($flag=="Yes")
											{
												$is_removed = "No";
												break;
											}
											$flag = "No";
											$is_removed = "Yes";
										}
										else
										{
											if(in_array($cartitem[$i]["SKU"],$exculde_skulist_arr) && count($exculde_skulist_arr) > 0)
											{
												if($flag=="Yes")
												{
													$is_removed = "No";
													break;
												}
												$flag = "No";
												$is_removed = "Yes";
											}
											else
											{
												if($is_removed=="Yes")
												{
													$is_removed = "No";
													break;
												}
												$is_removed = "No";
												$flag="Yes";
											}
										}
									}
								}
							}
						}
					}
					if($is_removed =="Yes")
					{
						for($i=0;$i<count($cartitem);$i++)
						{
							 if(isset($cartitem[$i]["IS_Free_Gift"]) && $cartitem[$i]["IS_Free_Gift"]=="Yes")
							 {
								 $is_free_gift_cart = "Yes";
								 $this->RemoveFreeGiftValueProduct($cartitem[$i]["SKU"]);
								 return $is_free_gift_cart;
							 }
						}
					}
					if($is_removed =="Yes")
					{
						$is_free_gift_cart = "Yes";
						return $is_free_gift_cart;
					}
				}
				
				$FreeSKU = "";
				if($free_gift_res->count() > 0)
				{
					$FreeSKU = $free_gift_res[0]->sku;
				}
				for($i=0;$i<count($cartitem);$i++)
				{
					if(isset($cartitem[$i]["IS_Free_Gift"]) && $cartitem[$i]["IS_Free_Gift"]=="Yes")
					{
						$skulist_arr = explode("#",$FreeSKU);
						$skunewlist_arr = [];
						if(in_array($cartitem[$i]["ORGSKU"],$skulist_arr))
						{
							$is_free_gift_cart = "Yes";
							if($cartitem[$i]["ORGSKU"]=="")
							{
								$this->RemoveFreeGiftValueProduct($cartitem[$i]["SKU"]);
							}
						}
						else
						{
							$free_gift_res_new = FreeGiftProduct::where('products_id','=',$cartitem[$i]["freeproductsid"])->get();	
							if($free_gift_res_new && $free_gift_res_new->count() > 0)
							{								
								$skunewlist_arr =explode("#",$free_gift_res_new[0]["sku"]);
								if(in_array($cartitem[$i]["ORGSKU"],$skunewlist_arr))
								{
									$this->RemoveFreeGiftValueProduct($cartitem[$i]["SKU"]);
								}
								else if($cartitem[$i]["ORGSKU"]=="")
								{
									$this->RemoveFreeGiftValueProduct($cartitem[$i]["SKU"]);
								}
							}
						}

						if(Auth::user() && strtolower(trim(Session::get('eusertype'))) == "wholesaler" || Session::get('is_dropshipper') == "Yes")
						{
							if(in_array($cartitem[$i]["ORGSKU"],$skunewlist_arr))
							{
								$this->RemoveFreeGiftValueProduct($cartitem[$i]["SKU"]);
							}
						}
					}
				}
			} else {
				if($Price == 0)
				{
					Session::forget('ShoppingCart');
				}					
			}
		}
		return $is_free_gift_cart;
	}
	
	public function SetFreegift($Gift_Free_In_Cart)
	{ 
		if($Gift_Free_In_Cart == 'No')
		{
			$AllDiscounts = $this->GetAllDiscounts();
			$TotalValue = NumberFormat(Session::get('ShoppingCart.SubTotal')) - $AllDiscounts['TotalDiscount'];
			if(strtolower(trim(Session::get('eusertype'))) !="wholesaler" && trim(Session::get('is_dropshipper')) != "Yes" && $Gift_Free_In_Cart == "No")
			{
				$Free_Gift_Res = $this->GetFreeCouponPopup($this->GetNetTotal());
				if(isset($Free_Gift_Res) && is_array($Free_Gift_Res) && count($Free_Gift_Res) == 1)
				{
					$products_id = $Free_Gift_Res[0]['products_id'];
					$freeproductsid = $Free_Gift_Res[0]['free_gift_products_id'];
					$this->FreeGiftInsertProductValue($products_id,$freeproductsid);
					$Msg = "Free Gift Product Added Successfully";
					Session::flash('CartSuccess',$Msg);	
				}
			}				
		}
	}
	
	public function RemoveFreeGiftCache()
	{
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')) > 0)
		{
			$cartitem = Session::get('ShoppingCart.Cart');
			if(count($cartitem) > 0)
			{
				for($i=0;$i<count($cartitem);$i++)
				{
					if(isset($cartitem[$i]["IS_Free_Gift"]) && $cartitem[$i]["IS_Free_Gift"]=="Yes")
					{
						$this->RemoveFreeGiftValueProduct($cartitem[$i]["SKU"]);
					}
				}
			}
		}	
	}
	
	public function GenerateShopCartFromCookieAfterLogin() 
	{
		$ArrMyShopCart = array();
		if( Auth::user())
		{
			$CustomerID = Session::get('sess_icustomerid');
			$ArrMyShopCart = Shoppingcart::where('customer_id','=',$CustomerID)->get();
			if($ArrMyShopCart && $ArrMyShopCart->count() > 0)
				$ArrMyShopCart = unserialize(stripslashes($ArrMyShopCart[0]["cart_string"]));
		}elseif(trim(Cookie::get('MY_SHOP_CART_COOKIE')) != ''){
			$CookieID = trim(Cookie::get('MY_SHOP_CART_COOKIE'));
			$ArrMyShopCart = Shoppingcart::where('cookie_id','=',$CookieID)->get();
			if($ArrMyShopCart && $ArrMyShopCart->count() > 0)
				$ArrMyShopCart = unserialize(stripslashes($ArrMyShopCart[0]["cart_string"]));
		}
		
        if (count($ArrMyShopCart) == 0) {
            return null;
        }

        Session::put("RemoveItem",'');
        $RemoveItem = '';
		$CartRequest = new \Illuminate\Http\Request();
		for ($p = 0; $p < count($ArrMyShopCart); $p++) {
            $prod_sku = strtolower(trim($ArrMyShopCart[$p]['SKU']));
            $quantity = (int) $ArrMyShopCart[$p]['Qty'];
            if($ArrMyShopCart[$p]['SKU'] == config('global.GIFT_CERTIFICATE_SKU') || $ArrMyShopCart[$p]['SKU'] == config('global.GIFT_CERTIFICATE_SKU1'))
			{
                /*
				$data['gc_value']     	= $ArrMyShopCart[$p]['Price'];
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
        return null;
    }
	
	public function ReformatCartPrice()
	{
		if(Session::has('ShoppingCart.Cart'))
		{
			$Cart = Session::get('ShoppingCart.Cart');
			$count = 0;
			if($Cart != null)
			{
				$count = count (Session::get('ShoppingCart.Cart'));
				if($count <=0)
					return null;
			}
			$TempCart = Session::get('ShoppingCart.Cart');
			Session::forget('ShoppingCart');
			$CartRequest = new \Illuminate\Http\Request();
			for($a=0; $a<$count; $a++)
			{
				if($TempCart[$a]['SKU'] == config('global.GIFT_CERTIFICATE_SKU') || $TempCart[$a]['SKU'] == config('global.GIFT_CERTIFICATE_SKU1'))
				{
					$data['gc_value']     	= $TempCart[$a]['Price'];
					$data['recipient_name']	= $TempCart[$a]['RecipientName'];
					$data['recipient_email']	= $TempCart[$a]['RecipientEmail'];
					$data['subject']			= $TempCart[$a]['Subject'];
					$data['message']			= $TempCart[$a]['Message'];
					$data['signature']		= $TempCart[$a]['Signature'];
					$data['deliverydate']		= $TempCart[$a]['DeliveryDate'];
					$data['yourname']			= $TempCart[$a]['YourName'];
					$data['youremail']		= $TempCart[$a]['YourEmail'];
					$data["GiftImage"]		= $TempCart[$a]['GiftImage'];
					$CartRequest->merge($data);
					$this->insertGiftCertificate($CartRequest);
				}
				else
				{
					$products_id 	= (int)$TempCart[$a]['ProductID'];
					$quantity  		= (int)$TempCart[$a]['Qty'];
					
					if(isset($TempCart[$a]["freeproductsid"]) && $TempCart[$a]["freeproductsid"]  > 0)
					{
						continue;
					}
					
					$this->AddToCart($products_id,$quantity,'No','reformatPrice');
				}
			}
			$a = $this->CalculateSubTotal();
		}
	}
	
	public function ApplyCreditDiscount($check)
	{
		Session::put('ShoppingCart.credit_limit_discount',0);
		Session::put('ShoppingCart.customer_remaining_credit_amount',0);
		$NetTotal = $this->GetNetTotal() - Session::get('shipping_insurance_charge');	
		if(Auth::user() && Session::get('etype') == "M" && Auth::user()->is_dropshipper !='Yes' && config('Settings.WHOLESALE_CREDIT_LIMIT') == 'Yes')
		{
			if(Auth::user()->credit_limit > 0 && $check==1)
			{
				if(Auth::user()->credit_limit > $NetTotal)
				{
					$credit_limit_discount = $this->GetNetTotal();
					$rem_amt = Auth::user()->credit_limit - $NetTotal;
				}
				else
				{
					$credit_limit_discount = Auth::user()->credit_limit;
					$rem_amt = 0.00;
				}
				Session::put('ShoppingCart.credit_limit_discount',$credit_limit_discount);
			}
			else
			{
				Session::put('ShoppingCart.credit_limit_discount',0.00);
				$rem_amt = 0.00;
			}
		}
		else
		{
			Session::put('ShoppingCart.credit_limit_discount',0.00);
			$rem_amt = 0.00;
		}
		Session::put('ShoppingCart.customer_remaining_credit_amount',$rem_amt);
		return NumberFormat($rem_amt)."###".$this->GetNetTotal();
	}
	
	public function ApplyGiftCoupons($coupon)
	{
		
		$totvalue = $this->GetNetTotal();
		
		if($totvalue<=0)
		{
			$totvalue = 0;
		}
		
		$coupon = trim($coupon);
		$CouponRS = GiftCertificate::where('remaining_value','>',0)->where('status','=','1')->where('gc_code','=',$coupon)->get();
		if($CouponRS && $CouponRS->count() > 0)
		{
			$remainingValue = $CouponRS[0]['remaining_value'];
			if($CouponRS[0]['remaining_value'] >= $totvalue)
			{			
				$CouponRS[0]['remaining_value'] = $totvalue;
				$CouponRS[0]['remaining_value'] = NumberFormat($CouponRS[0]['remaining_value']);
			}
			
			Session::put('ShoppingCart.GiftCoupon.Code',$CouponRS[0]['gc_code']);
			Session::put('ShoppingCart.GiftCoupon.Value', $CouponRS[0]['remaining_value']);
			Session::put('ShoppingCart.GiftCoupon.Applicable_Value', $CouponRS[0]['remaining_value']);
			$NewValue = $remainingValue - Session::get('ShoppingCart.GiftCoupon.Applicable_Value');		
			Session::put('ShoppingCart.GiftCoupon.Remaining_Value',$NewValue);
			return true;
		}
		else
		{
			Session::put('ShoppingCart.GiftCoupon.Code','');
			Session::put('ShoppingCart.GiftCoupon.Value', 0.0);
			Session::put('ShoppingCart.GiftCoupon.Applicable_Value',0.0);
			return false;
		}
	}
	
	public function GetFreeCouponPopup($TotalValue)
	{
		if(Session::has('ShoppingCart.Cart') && count(Session::get('ShoppingCart.Cart')))
		{
			$cartitem = Session::get('ShoppingCart.Cart');

			$ManufactureStr = "";
			if(count($cartitem) > 0)
			{
				for($d=0;$d<count($cartitem);$d++)
				{
					if(empty($cartitem[$d]["ImanufactureID"]))
					{
						$cartitem[$d]["ImanufactureID"] = 99999;
					}
					$ManufactureStr.= $cartitem[$d]["ImanufactureID"].",";
				}
			}
			if(trim($ManufactureStr)!='')
			{
				$ManufactureStr = substr($ManufactureStr,0,-1);
			}
			$free_gift_res = FreeGiftProduct::where('price_end_range','>=',$TotalValue)
							->where('price_start_range','<=',$TotalValue)
							->where('start_date','<=',date('Y-m-d'))  
							->where('end_date','>=',date('Y-m-d'))  
							->where('status','=','1')->where('flag_range','=','')->limit(1)->get();
			$ProductArr = array();
			if($free_gift_res && $free_gift_res->count() <=0)
			{
				
				$FreegiftQry = DB::table('pu_free_gift_product as f')
							->join('pu_freegift_brand as b','f.products_id','=','b.products_id')
							->where('price_end_range','>=',$TotalValue)
							->where('price_start_range','<=',$TotalValue)
							->where('start_date','<=',date('Y-m-d'))  
							->where('end_date','>=',date('Y-m-d'))  
							->where('status','=','1')->where('flag_range','=','Brand');
				if(trim($ManufactureStr)!='')
				{
					$ExpManufacture = explode(',',$ManufactureStr);
					$FreegiftQry->whereIn('b.imanufactureid',$ExpManufacture);
				}
				$free_gift_res = $FreegiftQry->limit(1)->get();
				
				if($free_gift_res && $free_gift_res->count() <=0 )
				{
					$FreegiftQry = DB::table('pu_free_gift_product as f')
							->join('pu_freegift_brand as b','f.products_id','=','b.products_id')
							->where('price_end_range','<=',$TotalValue)
							->where('start_date','<=',date('Y-m-d'))  
							->where('end_date','>=',date('Y-m-d'))  
							->where('status','=','1')->where('flag_range','=','Brand');
					if(trim($ManufactureStr)!='')
					{
						$ExpManufacture = explode(',',$ManufactureStr);
						$FreegiftQry->whereIn('b.imanufactureid',$ExpManufacture);
					}
					$free_gift_res = $FreegiftQry->limit(1)->get();
					if($free_gift_res && $free_gift_res->count() <=0 )
					{
						$free_gift_res = FreeGiftProduct::where('price_end_range','<=',$TotalValue)
								->where('start_date','<=',date('Y-m-d'))  
								->where('end_date','>=',date('Y-m-d'))  
								->where('status','=','1')->where('flag_range','=','')
								->orderBy('price_end_range','desc')->limit(1)->get();
					}
				}
				
				$FreeGiftValue = [];
	
				if($free_gift_res && $free_gift_res->count() > 0)
				{
					
					foreach($free_gift_res as $FreeGift)
					{
						$free_gift_value = explode("#",$FreeGift->sku);
						$FreeGiftValue = array_merge($FreeGiftValue,$free_gift_value);
					}
				}
				
				$ProductArr = array();
				//dd($FreeGiftValue);
				if(count($FreeGiftValue) > 0)
				{
					$product_res = Products::whereIn('sku',$FreeGiftValue)->where('is_free_gift_products','=','Yes')->where('status','=','1')->get();
					
					//echo "<pre>"; print_r($product_res); exit;
					//exit;
					
					$TotalProducts = count($product_res);
					
					if($TotalProducts > 0)
					{
						for($i=0;$i<$TotalProducts;$i++)
						{
							$product_res[$i] = $this->SetProduct($product_res[$i]);
							$products_id = $product_res[$i]["products_id"];
							$product_name = $product_res[$i]["product_name"];
							$sku	= $product_res[$i]["sku"];
							$image = $product_res[$i]["image"];
							$short_description = $product_res[$i]["short_description"];

							if(file_exists(config('global.PRD_THUMB_IMG_PATH').$product_res[$i]['image']) && !empty($product_res[$i]['image']))
								$thumb_image = config('global.PRD_THUMB_IMG_URL').$product_res[$i]['image'];
							else
								$thumb_image = config('global.NO_IMAGE_THUMB');
							
							$ProductArr[] = array(
										"free_gift_products_id" => $free_gift_res[0]->products_id,
										"products_id"			=> $products_id,
										"product_name" 			=> $product_name,
										"sku"					=> $sku,
										"thumb_image" 			=> $thumb_image,
										"short_description"		=> $short_description
									  );
						}
					}
				}
			}
			if(count($ProductArr)==1)
			{
				$this->FreeGiftInsertProductValue($ProductArr[0]["products_id"],$ProductArr[0]["free_gift_products_id"]);
			}
			return $ProductArr;
		}
	}
	
	public function SetBillingAddress($Billing)
	{
		$temp = [];
		if($Billing['bill_country'] != 'US')
			$state = $Billing['bill_other_state'];
		else
			$state = $Billing['bill_state'];	
		$temp['first_name'] 	= stripslashes($Billing['bill_fname']);
		$temp['last_name']  	= stripslashes($Billing['bill_lname']);
		$temp['company']    	= stripslashes($Billing['bill_company']);
		
		$Billing['bill_address1'] = str_replace("__"," ",$Billing['bill_address1']);
		$temp['address1'] 		= stripslashes($Billing['bill_address1']);
		$Billing['bill_address2'] = str_replace("__"," ",$Billing['bill_address2']);
		$temp['address2'] 		= stripslashes($Billing['bill_address2']);
		
		$temp['city'] 			= stripslashes($Billing['bill_city']);
		$temp['country'] 		= $Billing['bill_country'];
		$temp['state'] 			= $state;
		$temp['zip'] 			= $Billing['bill_zip'];
		$temp['phone'] 			= $Billing['bill_phone'];
		$temp['email'] 			= $Billing['bill_email'];
		$temp['confirm_email'] 	= $Billing['bill_cemail'];
		Session::put('ShoppingCart.BillingAddress',$temp);
		$BillingAsShipping = 'No';
		if(isset($Billing['sameasbill']))
			$BillingAsShipping = $Billing['sameasbill'];
		Session::put('ShoppingCart.BillingAsShipping',$BillingAsShipping);
		return null;
	}
	
	public function SetShippingAddress($Shipping)
	{
		$temp = [];
		$prefix = 'ship';
		if($Shipping['sameasbill'] == 'Yes')
		{
			$prefix = 'bill';
			$Billing = Session::get('ShoppingCart.BillingAddress');
			Session::put('ShoppingCart.ShippingAddress',$Billing);
			return null;
		}
		if($Shipping[$prefix.'_country'] != 'US')
			$state = $Shipping[$prefix.'_other_state'];
		else
			$state = $Shipping[$prefix.'_state'];

		$temp['first_name'] 	= stripslashes($Shipping[$prefix.'_fname']);
		$temp['last_name']  	= stripslashes($Shipping[$prefix.'_lname']);
		$temp['company']    	= stripslashes($Shipping[$prefix.'_company']);
		
		$Shipping[$prefix.'_address1'] = str_replace("Unions","Union",$Shipping[$prefix.'_address1']);
		$Shipping[$prefix.'_address1'] = str_replace("unions","union",$Shipping[$prefix.'_address1']);
		$temp['address1'] 		= stripslashes($Shipping[$prefix.'_address1']);
		
		$Shipping[$prefix.'_address2'] = str_replace("Unions","Union",$Shipping[$prefix.'_address2']);
		$Shipping[$prefix.'_address2'] = str_replace("unions","union",$Shipping[$prefix.'_address2']);
		$temp['address2'] 		= stripslashes($Shipping[$prefix.'_address2']);
		
		$temp['city'] 			= stripslashes($Shipping[$prefix.'_city']);
		$temp['country'] 		= $Shipping[$prefix.'_country'];
		$temp['state'] 			= $state;
		$temp['zip'] 			= $Shipping[$prefix.'_zip'];
		$temp['phone'] 			= $Shipping[$prefix.'_phone'];
		$temp['email'] 			= $Shipping[$prefix.'_email'];
		Session::put('ShoppingCart.ShippingAddress',$temp);
		return null;
	}
	
	public function FreeGiftValue($subtotal) 
	{
		$free_gift_array = array();
		
		if($subtotal >= config('Settings.FREEGIFT_VALUE'))
		{
			if(config('Settings.BEAUTY_SAMPLE') == "Yes") 
			{
				$free_gift_array[] = "Beauty & Accessories Sample";
			}
			if(config('Settings.PERFUME_SAMPLE') == "Yes") 
			{
				$free_gift_array[] = "Perfume Sample";
			}
		} else {
			return $free_gift_array;
		}
		return $free_gift_array;
	}
	
	public function setPaymentDetail($request)
	{
		$temp = [];
		$temp['Payment_Type']     	= $request->PaymentMethod;
		$temp['Payment_Method']   	= $this->GetPaymentMethodName($request->PaymentMethod);
		if($temp['Payment_Type'] == 'PAYMENT_AUTHORIZENETCC' || $temp['Payment_Type'] =='PAYMENT_PAYPALCC' || $temp['Payment_Type'] =='PAYMENT_BRAINTREECC')
		{
			$temp['CCName']   	= trim($request->CCholdername);
			$temp['CCType']   	= $request->CCType;
			$temp['CCNumber'] 	= $request->CCNumber;
			$temp['CCMonth']  	= $request->CCMonth;
			$temp['CCYear']   	= $request->CCYear;
			$temp['CSC']      	= $request->CSC;
			$temp['BRAINNONCE']     = '';
			$temp['BRAINTREEDEVICEDATE'] = '';
		}
		else
		{
			$temp['CCName']   	= '';
			$temp['CCType']   	= '';
			$temp['CCNumber'] 	= '';
			$temp['CCMonth']  	= '';
			$temp['CCYear']   	= '';
			$temp['CSC']      	= '';
			$temp['BRAINNONCE']     = '';
			$temp['BRAINTREEDEVICEDATE'] = '';
		}
		Session::put('ShoppingCart.Payment_Detail',$temp);
		return NULL;
	}
	
	public function GetPaymentMethodName($pType)
	{
		switch ($pType)
		{
			case 'PAYMENT_PAYPALEC':
				return 'Paypal Express Checkout';
				break;
                        case 'PAYMENT_BRAINTREECC':
                        return 'Credit Card';
			break;
			case 'PAYMENT_PAYPALCC':
				return 'Credit Card';
				break;
			case 'PAYMENT_AUTHORIZENETCC':
				return 'Credit Card';
				break;
			case 'PAYMENT_GOOGLEC':
				return 'Google Checkout';
				break;
			case 'PAYMENT_GIFT_CERTIFICATE':
				return 'Gift Certificate';
				break;
			case 'PAYMENT_2CO':
				return '2Checkout';
				break;
			case 'PAYMENT_MOC':
				return 'Check or Money Order';
				break;
			case 'PAYMENT_WT':
				return 'Wire Transfer';
				break;
			case 'PAYMENT_PH':
				return 'Phone Order';
				break;
            case 'PAYMENT_CL':
				return 'Credit Limit';
				break;
            case 'PAYMENT_DS':
				return 'Dropshipper Fund';
				break;
			case 'PAYMENT_PAYWITHAMAZON':
				return 'Pay With Amazon';
				break;
			case 'PAYMENT_STRIPE':
				return 'Credit Card';
				break;
			case 'PAYMENT_PAYWITHAFTERPAY':
				return 'Pay With Afterpay';
				break;
			default:
				return NULL;
				break;
		}
		return NULL;
	}
	
	public function InsertGiftCertificateDB($ary, $orders_detail_id, $custId = 0,$is_amazon="No" )
	{
		do
		{
			$status = ($is_amazon=='Yes')?'0':'1';
			$status = 0;	

			$GCInsert =	array(
				'customer_id' 		=> $custId,
				'orders_detail_id' 	=> $orders_detail_id,
				'gc_code' 			=> GCGenerateCode(),
				'gc_value' 			=> $ary['Price'],
				'remaining_value' 	=> $ary['Price'],
				'recipient_name' 	=> $ary['RecipientName'],
				'recipient_email' 	=> $ary['RecipientEmail'],
				'subject' 			=> (isset($ary['Subject']))?$ary['Subject']:'',
				'message' 			=> (isset($ary['Message']))?$ary['Message']:'',
				'your_name' 		=> $ary['YourName'],
				'your_email' 		=> $ary['YourEmail'],
				'giftimage'			=> $ary['GiftImage'],
				'giftsku'			=> $ary['SKU'],
				'deliverydate'		=> date("Y-m-d", strtotime($ary['DeliveryDate'])),
				'status' 			=> $status,
				'is_email'			=> 'No'
			);
			$gc_id = GiftCertificate::create($GCInsert) ;
		}
		while($gc_id == false);
		return $gc_id;
	}
	public function ProductDeductStock($sku, $qty = 1,$IsCosmo="",$IsNandansons="",$IsPerfumePW="",$IsPCA="",$VendorSKU="")
	{
		$ProductSt = Products::select('current_stock','cosmo_current_stock','cosmo_sku','nandansons_sku','nandansons_current_stock','perfumeworldwide_sku','pca_sku','perfumeworldwide_currentstock','pca_current_stock')->where('status','=','1')->where('sku','=',$sku)->get();
		if($ProductSt->count() <= 0 )
		{
			return NULL;
		}

		$new_stock=0;
		if($IsCosmo=="Yes" && $VendorSKU==$ProductSt[0]["cosmo_sku"])
		{
			if($ProductSt[0]["cosmo_current_stock"]>$qty)
			{
				$new_stock = $ProductSt[0]["cosmo_current_stock"]-$qty;
			}
			else if($qty>$ProductSt[0]["cosmo_current_stock"])
			{
				$new_stock = $qty-$ProductSt[0]["cosmo_current_stock"];
			}
			if($new_stock<=0)
			{
				$new_stock=0;
			}
			$UpdateStock = array ('cosmo_current_stock' => $new_stock);
		}else if($IsNandansons=="Yes" && $VendorSKU==$ProductSt[0]["nandansons_sku"]){
			if($ProductSt[0]["nandansons_current_stock"]>$qty)
			{
				$new_stock = $ProductSt[0]["nandansons_current_stock"]-$qty;
			}
			else if($qty>$ProductSt[0]["nandansons_current_stock"])
			{
				$new_stock = $qty-$ProductSt[0]["nandansons_current_stock"];
			}
			if($new_stock<=0)
			{
				$new_stock=0;
			}
			$UpdateStock = array ('nandansons_current_stock' => $new_stock);
		}else if($IsPerfumePW=="Yes" && $VendorSKU==$ProductSt[0]["perfumeworldwide_sku"]){
			if($ProductSt[0]["perfumeworldwide_currentstock"]>$qty)
			{
				$new_stock = $ProductSt[0]["perfumeworldwide_currentstock"]-$qty;
			}
			else if($qty>$ProductSt[0]["perfumeworldwide_currentstock"])
			{
				$new_stock = $qty-$ProductSt[0]["perfumeworldwide_currentstock"];
			}
			if($new_stock<=0)
			{
				$new_stock=0;
			}
			$UpdateStock = array ('perfumeworldwide_currentstock' => $new_stock);
		}else if($IsPCA=="Yes" && $VendorSKU==$ProductSt[0]["pca_sku"]){
			if($ProductSt[0]["pca_current_stock"]>$qty)
			{
				$new_stock = $ProductSt[0]["pca_current_stock"]-$qty;
			}
			else if($qty>$ProductSt[0]["pca_current_stock"])
			{
				$new_stock = $qty-$ProductSt[0]["pca_current_stock"];
			}
			if($new_stock<=0)
			{
				$new_stock=0;
			}
			$UpdateStock = array ('pca_current_stock' => $new_stock);
		}
		else
		{
			if($ProductSt[0]["current_stock"]>$qty)
			{
				$new_stock = $ProductSt[0]["current_stock"]-$qty;
			}
			else if($qty>$ProductSt[0]["current_stock"])
			{
				$new_stock = $qty-$ProductSt[0]["current_stock"];
			}
			if($new_stock<=0)
			{
					$new_stock=0;
			}
			$UpdateStock = array ('current_stock' => $new_stock);
		}
		//$result = true;
		$result = Products::where('sku','=',$sku)->update($UpdateStock);
		if($result)
			return true;
		else
			return false;
	}
	
	public function RouteShippingInsuranceOrderProcess($order,$order_details)
	{	
		$route_token = "test-c836e757-a9af-4844-84ac-30777c420121";
		//$route_token = "2b3983e3-6b7d-45e4-bc21-a4ee1db24609";
		$datas["source_order_id"] = $order["orders_no"]; //$order["orders_id"];
		$datas["subtotal"] = $order["sub_total"];
		$datas["taxes"] = $order["tax"];
		$datas["insurance_selected"] = true;
		
		$datas["customer_details"]["first_name"] = $order["bill_first_name"];//"John";
		$datas["customer_details"]["last_name"] = $order["bill_last_name"];
		$datas["customer_details"]["email"] = $order["bill_email"];
		
		$datas["shipping_details"]["first_name"] = $order["ship_first_name"];
		$datas["shipping_details"]["last_name"] = $order["ship_last_name"];
		$datas["shipping_details"]["street_address1"] = $order["ship_address1"];//"8400 NW 25TH ST STE 100";
		$datas["shipping_details"]["street_address2"] = $order["ship_address2"];
		$datas["shipping_details"]["province"] = $order["ship_state"];
		$datas["shipping_details"]["city"] = $order['ship_city'];
		$datas["shipping_details"]["zip"] = $order['ship_zip'];
		$datas["shipping_details"]["country_code"] = $order['ship_country'];
		
		for($i = 0; $i < count($order_details); $i++)
		{
			$line_items[$i]['source_product_id'] = $order_details[$i]['products_id'];
			$line_items[$i]['sku'] = $order_details[$i]['sku'];
			$line_items[$i]['name'] = $order_details[$i]['product_name'];
			$line_items[$i]['price'] = $order_details[$i]['price'];
			$line_items[$i]['quantity'] = $order_details[$i]['quantity'];
			//$line_items[$i]['upc'] = "1234";
			//$line_items[$i]['image_url'] = "https://exampleimageurl.com";
		}
		$datas['line_items'] = $line_items;
		$d = json_encode($datas);	
		$order_url = "https://api.route.com/v1/orders";
		
		$chs = curl_init();
		curl_setopt_array($chs, array(	  	
	  		CURLOPT_URL => $order_url, 
		  	CURLOPT_RETURNTRANSFER => true,
		  	CURLOPT_ENCODING => "",
		  	CURLOPT_MAXREDIRS => 10,
		  	CURLOPT_TIMEOUT => 30,
		  	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  	CURLOPT_CUSTOMREQUEST => "POST",
		  	CURLOPT_POSTFIELDS => $d,//  http_build_query($data),
		  	CURLOPT_HTTPHEADER => array(
				"cache-control: no-cache",
				"Content-Type: application/json",
				"token: ".$route_token."",
				//"Authorization: Basic ".$basic_auth."",
				"Accept: application/json"),
			));
			$results = curl_exec($chs);
			if($results === false)
			{
    			//dd(curl_error($results));
			} else {
				$responses = json_decode($results,true);				
				if(isset($route_response["email_account_id"]) && $route_response["email_account_id"]!="")
				{
					$route_res_db_store = "email_account_id:".$route_response["email_account_id"]."||id:".$route_response["id"]."||order_number:".$route_response["order_number"]."||source_order_id:".$route_response["source_order_id"];
					$route_upd_arr = array(
						'route_shipping_insurance_response' => $route_res_db_store
					);
					$udpRoute = Order::where('orders_id','=',$order["orders_id"])->update($route_upd_arr);
				}
			}
		/*
		$headers = [
				"cache-control" => "no-cache",
				"Content-Type" => "application/json",
				"token" => $route_token,
				"Accept" => "application/json"];
		$curl = new \GuzzleHttp\Client(['headers' => $headers,'verify' => false,'debug' => true]);		
		$order_url = 'https://api.route.com/v1/orders';
		$order_url = (string)$order_url;
		$response = $curl->request('POST',$order_url,$datas);
		*/
		//dd($response);
	}
	
	public function AfterpayMinMax()
	{
		$db_res = PaymentMethod::select('pm_group_name', 'pm_gateway_name','pm_details')
							->where('pm_group_name','=', 'PAYMENT_PAYWITHAFTERPAY')
							->where('pm_status', '=', 'Active')
							->get();
		
		if($db_res->count() > 0)
		{
			$arrPEVar		= unserialize($db_res[0]->pm_details);
			#############################
			$this->ap_arr['PaywithAfterpay_Merchant_ID']   = $this->decrypt($arrPEVar['PaywithAfterpay_Merchant_ID']);
			$this->ap_arr['PaywithAfterpay_Merchant_Secret_Key']   = $this->decrypt($arrPEVar['PaywithAfterpay_Merchant_Secret_Key']);
			$this->ap_arr['PaywithAfterpay_Header_Authorization']   = $this->decrypt($arrPEVar['PaywithAfterpay_Header_Authorization']);
			$this->ap_arr['PaywithAfterpay_Header_User_Agent']   = $this->decrypt($arrPEVar['PaywithAfterpay_Header_User_Agent']);
			#############################
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
	
	public function SetShippingInsuranceCharge($action='add')
	{
		Session::forget('shipping_insurance_charge');
		if($action == 'add')
		{
			//Session::forget('ShoppingCart.ShippingSignature');
			$NetTotal = $this->GetNetTotal();
			$ShipInsurance = 0;
			if($NetTotal > 0 )
			{
				if($NetTotal <= 99)
				{
					$ShipInsurance = 0.98;
				} else {
					$ShipInsurance = NumberFormat($NetTotal*0.02) + 0.98;
				}
			}
			Session::put('shipping_insurance_charge',NumberFormat($ShipInsurance));
		} 
	}
	
	public function SetAmazonConfig($PageFrom='')
	{
		$PaymentMethod =  PaymentMethod::where('pm_group_name','=','PAYMENT_PAYWITHAMAZON')->where('pm_status','=','Active')->get();
		if($PaymentMethod && $PaymentMethod->count() > 0)
		{
			$pm_details = unserialize($PaymentMethod[0]->pm_details);
			foreach ( $pm_details as $pm_var_name => $pm_var_value )
			{
				$payment_methods_settings[$pm_var_name] = $pm_var_value;
			}
			config(['CLIENT_ID' => $this->decrypt($pm_details['paywithamazon_Client_ID'])]);
			config(['MERCHANT_ID' => $this->decrypt($pm_details['paywithamazon_Merchant_Id'])]);
			if($PageFrom != '')
				config(['CALLBACK_URL' => url('/setupamazon/'.$PageFrom)]);
			else 
				config(['CALLBACK_URL' => url('/setupamazon')]);
			
			if($PageFrom == "phoneorder_payment_receipt"){
				config(['CALLBACK_CHECKOUT_URL' => url('/setupamazon/phoneorder_payment_receipt')]);
			}else{
				config(['CALLBACK_CHECKOUT_URL' => url('/setupamazon')]);
			}
			// config(['CALLBACK_CHECKOUT_URL' => url('/setupamazon')]);
			/*if(Session::get('sess_useremail') == 'gequaldev@gmail.com')
			{
				$payment_methods_settings['paywithamazon_Transaction_Mode'] = 'Sandbox';
			}*/ 
			
			
			// if($_SERVER['HTTP_X_FORWARDED_FOR'] == "157.32.6.77"){
				// $payment_methods_settings['paywithamazon_Transaction_Mode'] = 'sandbox';
			// }
			if(strtoupper(trim($payment_methods_settings['paywithamazon_Transaction_Mode'])) == 'SANDBOX')
				config(['JS_SERVER_URL' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js?sellerId='.config('MERCHANT_ID')]);
			else
				config(['JS_SERVER_URL' => 'https://static-na.payments-amazon.com/OffAmazonPayments/us/js/Widgets.js?sellerId='.config('MERCHANT_ID')]);	
		}
	}
	
	public function GoogleTagManager($Data=[])
	{
		if($Data['page'] == 'order_receipt')
		{
			$Coupon_DiscLevel = "";
			$coupon_code = $this->GetAllCoupons('CouponCode');
			$discountInfo = $this->GetAllDiscounts();
			$discount = $discountInfo['TotalDiscount'];
			
			if($coupon_code != "" && Session::has('ShoppingCart.Coupon_Detail_CJ.orders'))
			{
				$coupon_order = Session::get('ShoppingCart.Coupon_Detail_CJ.orders');
				if($coupon_order != ""){
					if($coupon_order == 0){ 
						//on order amount so whole order discount
						$Coupon_DiscLevel = "Order";
					}else if($coupon_order == 4){
						//free shipping so no discount
						$Coupon_DiscLevel = "None";
					}else{
						//category,sku,brand etc.
						$Coupon_DiscLevel = "Item";
					}
				}
			}
			
			$cj_discount = $discount;
			if($Coupon_DiscLevel == "None" || $Coupon_DiscLevel == "Item"){
				$cj_discount = "0";
			}
		}
		if($Data['page'] == 'shoppingcart' || $Data['page'] == 'billing' || $Data['page'] == 'billing_amazon' || $Data['page'] == 'order_receipt')
		{
			$pid_fstr = '';
			$tot_qty = 0;
			$sf_item_array = array();
			$tempCart = (Session::has('ShoppingCart.Cart'))?Session::get('ShoppingCart.Cart'):[];
			$item_array = array();
			$cnt_row = count($tempCart);
			if($cnt_row > 1)
			{
				for($ir=0;$ir<$cnt_row;$ir++)
				{
					if($tempCart[$ir]['SKU'] != "")
					{
						$product_quantity = $tempCart[$ir]['Qty'];
						$tot_qty += $product_quantity;
				
						$product_name = $tempCart[$ir]['ProductName'];
						$product_name = str_replace("\"","'",$product_name);
						
						$item_array[$ir]['product_name'] = $product_name;
						$item_array[$ir]['product_id'] = $tempCart[$ir]['SKU'];
						$item_array[$ir]['product_price'] = $tempCart[$ir]['Price'];
						$item_array[$ir]['product_quantity'] = $product_quantity;
						
						$ItemWiseDiscount = 0;
						if($Data['page'] == 'order_receipt')
						{
							if(((isset($tempCart[$ir]['ItemWiseCouponDiscount']) && $tempCart[$ir]['ItemWiseCouponDiscount'] > 0) || (isset($tempCart[$ir]['ItemWiseCouponDiscount_CJ']) && $tempCart[$ir]['ItemWiseCouponDiscount_CJ'] > 0)) && $Coupon_DiscLevel == "Item"){
								if($coupon_order == "7"){
									$ItemWiseDiscount = $tempCart[$ir]['ItemWiseCouponDiscount_CJ'];
								}else{
									$ItemWiseDiscount = $tempCart[$ir]['TotPrice'] - $tempCart[$ir]['ItemWiseCouponDiscount'];
								}
							}
							$item_array[$ir]['ItemWiseDiscount'] = number_format($ItemWiseDiscount,2);
						} else {
							if(isset($tempCart[$ir]['ItemWiseCouponDiscount']) && $tempCart[$ir]['ItemWiseCouponDiscount'] > 0){
								$ItemWiseDiscount = $tempCart[$ir]['TotPrice'] - $tempCart[$ir]['ItemWiseCouponDiscount'];
							}
							$item_array[$ir]['ItemWiseDiscount'] = number_format($ItemWiseDiscount,2);
						}
						$pid_fstr .= $tempCart[$ir]['SKU'].",";
						
						//sf track cart start
						$sf_item_array[$ir]['item'] = $tempCart[$ir]['SKU'];
						$sf_item_array[$ir]['quantity'] = $product_quantity;
						$sf_item_array[$ir]['price'] = $tempCart[$ir]['Price'];
						$sf_item_array[$ir]['unique_id'] = $tempCart[$ir]['SKU'];			
						//sf track cart end
					}
				}
				$pid_fstr = substr($pid_fstr,0,-1);
				if($pid_fstr != "")
				{
					$Data['RemarketingprodID'] = explode(",",$pid_fstr);
				}
			}
			else
			{
				for($ir=0;$ir<$cnt_row;$ir++)
				{	
					if($tempCart[$ir]['SKU'] != "")
					{
						$product_quantity = $tempCart[$ir]['Qty'];
						$tot_qty += $product_quantity;
						
						$product_name = $tempCart[$ir]['ProductName'];
						$product_name = str_replace("\"","'",$product_name);
						
						$item_array[$ir]['product_name'] = $product_name;
						$item_array[$ir]['product_id'] = $tempCart[$ir]['SKU'];
						$item_array[$ir]['product_price'] = $tempCart[$ir]['Price'];
						$item_array[$ir]['product_quantity'] = $product_quantity;
						
						$ItemWiseDiscount = 0;
						$tempCart[$ir]['ItemWiseCouponDiscount'] = (isset($tempCart[$ir]['ItemWiseCouponDiscount'])?$tempCart[$ir]['ItemWiseCouponDiscount']:0);
						$tempCart[$ir]['ItemWiseCouponDiscount_CJ'] = (isset($tempCart[$ir]['ItemWiseCouponDiscount_CJ'])?$tempCart[$ir]['ItemWiseCouponDiscount_CJ']:0);
						if($Data['page'] == 'order_receipt')
						{
							if(($tempCart[$ir]['ItemWiseCouponDiscount'] > 0 || $tempCart[$ir]['ItemWiseCouponDiscount_CJ'] > 0) && $Coupon_DiscLevel == "Item"){
								if($coupon_order == "7"){
									$ItemWiseDiscount = $tempCart[$ir]['ItemWiseCouponDiscount_CJ'];
								}else{
									$ItemWiseDiscount = $tempCart[$ir]['TotPrice'] - $tempCart[$ir]['ItemWiseCouponDiscount'];
								}
							}
							$item_array[$ir]['ItemWiseDiscount'] = number_format($ItemWiseDiscount,2);
						} else {
							if($tempCart[$ir]['ItemWiseCouponDiscount'] > 0){
								$ItemWiseDiscount = $tempCart[$ir]['TotPrice'] - $tempCart[$ir]['ItemWiseCouponDiscount'];
							}
							$item_array[$ir]['ItemWiseDiscount'] = number_format($ItemWiseDiscount,2);
						}
						
						$pid_fstr .= $tempCart[$ir]['SKU'];
						
						//sf track cart start
						$sf_item_array[$ir]['item'] = $tempCart[$ir]['SKU'];
						$sf_item_array[$ir]['quantity'] = $product_quantity;
						$sf_item_array[$ir]['price'] = $tempCart[$ir]['Price'];
						$sf_item_array[$ir]['unique_id'] = $tempCart[$ir]['SKU'];			
						//sf track cart end
					}
				}
				if($pid_fstr != "")
				{
					$Data['RemarketingprodID'] = $pid_fstr;
				}		
			}
			$Data['RemarketingtotalValue'] = Session::get('ShoppingCart.SubTotal');
			
			if($Data['page'] != 'order_receipt')
				$Data['SF_TrackCart'] = $sf_item_array;
			
			/*$line_items = json_encode($item_array);
			$line_items = str_replace("'","\'",$line_items);
			*/
			//$temp_items_arr = $file_value."==".$line_items;
			
			//sf track cart start
				/*$sf_items = json_encode($sf_item_array);
				$sf_items = str_replace("'","\'",$sf_items);
				*/	
			//sf track cart end
			
			
		}
		
		$DataLayer = [];
		$DataLayer['RemarketingprodID'] = isset($Data['RemarketingprodID'])?$Data['RemarketingprodID']:'';
		$DataLayer['RemarketingpageType'] = $Data['pagetype'];
		if(isset($Data['RemarketingtotalValue']))
			$DataLayer['RemarketingtotalValue'] = $Data['RemarketingtotalValue'];
		$DataLayer['RemarketingOnly'] = "true"; 
		if(isset($Data['search_query']))
			$DataLayer['search_query'] = $Data['search_query'];
		if(isset($Data['SF_TrackCart']))
			$DataLayer['SF_TrackCart'] = $Data['SF_TrackCart'];
		
		if($Data['page'] == 'shoppingcart')
		{
			$DataLayer['order_quantity'] = $tot_qty;
			$DataLayer['currency'] = Session::get('currency_code');
		}
		if($Data['page'] == 'billing' || $Data['page'] == 'billing_amazon')
		{
			$DataLayer['line_items_array'] = $item_array;
			$DataLayer['line_items'] = $item_array;
			$DataLayer['order_quantity'] = $tot_qty;
			$DataLayer['currency'] = Session::get('currency_code');
		}
		
		if($Data['page'] == 'order_receipt')
		{
			$DataLayer['RemarketingConversionLanguage'] = 'en';
			$DataLayer['RemarketingConversionFormat'] = '3';
			$DataLayer['RemarketingConversionColor'] = 'ffffff';
			$DataLayer['RemarketingConversionLabel'] = 'O5inCKztgGsQkqLpuQM';
			$DataLayer['RemarketingOnly'] = "false"; 
			$DataLayer['RemarketingCouponCode'] = $coupon_code;
			$DataLayer['RemarketingDiscount'] = $discount;
			$DataLayer['RemarketingDiscountCJ'] = $cj_discount;
			$DataLayer['RemarketingOrderId'] = Session::get('ShoppingCart.OrderID');
			$DataLayer['line_items_array'] = $item_array;
			$DataLayer['line_items'] = $item_array;
			$DataLayer['order_quantity'] = $tot_qty;
			$DataLayer['currency'] = Session::get('currency_code');
			$DataLayer['SF_TrackPurchase'] = $sf_item_array;
			$GData['LabelVal'] = 'O5inCKztgGsQkqLpuQM';
		}
		
		if($Data['pagetype'] == 'other')
		{
			$DataLayer['RemarketingConversionLanguage'] = 'en';
			$DataLayer['RemarketingConversionFormat'] = '3';
			$DataLayer['RemarketingConversionColor'] = 'ffffff';
			$DataLayer['RemarketingOnly'] = "false";
			if($Data['page'] == 'register')
			{
				$DataLayer['RemarketingConversionLabel'] = 'FFhXCJKNhmsQkqLpuQM';
				$DataLayer['lead_type'] = 'RegistrationForm';
				$GData['LabelVal'] = 'FFhXCJKNhmsQkqLpuQM';
			}
			if($Data['page'] == 'wholesaleregister')
			{
				$DataLayer['RemarketingConversionLabel'] = 'SX50CJaUhmsQkqLpuQM';
				$DataLayer['lead_type'] = 'RegistrationForm';
				$GData['LabelVal'] = 'SX50CJaUhmsQkqLpuQM';
			}
			if($Data['page'] == 'myaccount')
			{
				if(Session::get('eusertype') == 'Retailer')
				{
					$DataLayer['RemarketingConversionLabel'] = 'FFhXCJKNhmsQkqLpuQM';
					$GData['LabelVal'] = 'FFhXCJKNhmsQkqLpuQM';
				}
				if(Session::get('eusertype') == 'Wholesaler')
				{
					$DataLayer['RemarketingConversionLabel'] = 'SX50CJaUhmsQkqLpuQM';
					$GData['LabelVal'] = 'SX50CJaUhmsQkqLpuQM';
				}
			}
			if($Data['page'] == 'contact_us')
			{
				$DataLayer['RemarketingConversionLabel'] = 'I4tECI6u7WoQkqLpuQM';
				$GData['LabelVal'] = 'I4tECI6u7WoQkqLpuQM';
			}
		}
		//Log::info($DataLayer);
		$DataLayer = json_encode($DataLayer);
		$GData['pagetype'] = $Data['pagetype'];
		$GData['google_remarketing_codes']="<script type='text/javascript'>dataLayer.push(".$DataLayer.");</script>";
		if(Session::has('sess_icustomerid') && Session::get('sess_icustomerid') > 0 && Session::get('sess_useremail') != "" )
		{
			$GData['google_remarketing_codes'].= "<script type='text/javascript'>	
				if(dataLayer.length > 0){
					dataLayer[0].SF_EmailUniqueId='".Session::get('sess_useremail')."';
				}else{
					dataLayer.push({
						'SF_EmailUniqueId': '".Session::get('sess_useremail')."'
					});
				}
			 </script>";
		}
		return $GData;
	}
	
	public function GetShippingChargeDays($ship_zip,$ship_state,$ship_country,$shipping_mode_id)
	{
		$DiscountAll = $this->GetAllDiscounts();
		$TotalDiscount = $DiscountAll['TotalDiscount'];
		$SubTotal = Session::get('ShoppingCart.SubTotal');
		$subTotal = $SubTotal - $TotalDiscount;
		$ship_country  = substr($ship_country, 0, 2);
		$shipping_mode_id = (int)$shipping_mode_id;

		if ($ship_country != "")
		{
			## this condition is for Z + S + C
			$rid = ShippingRule::where('shipping_mode_id', '=', $shipping_mode_id)
					->where('zipcode_to','>=',$ship_zip)
					->where('zipcode_from','<=',$ship_zip)
					->where('state','like','%'.$ship_state.'%')
					->where('country','like','%'.$ship_country.'%')->get();
			
			## this condition is for Z + C
			if ($rid && $rid->count() <= 0)
			{
				$rid = ShippingRule::where('shipping_mode_id', '=', $shipping_mode_id)
					->where('zipcode_to','>=',$ship_zip)
					->where('zipcode_from','<=',$ship_zip)
					->where('country','like','%'.$ship_country.'%')->get();
					
				## this condition is for S + C
				if ($rid && $rid->count() <= 0)
				{
					$rid = ShippingRule::where('shipping_mode_id', '=', $shipping_mode_id)
							->where('state','like','%'.$ship_state.'%')
							->where('country','like','%'.$ship_country.'%')->get();

					## this condition is for only C
					if ($rid && $rid->count() <= 0)
					{
						$rid = ShippingRule::where('shipping_mode_id', '=', $shipping_mode_id)
								->where('state','like','%'.$ship_state.'%')
								->where('zipcode_to','=','')
								->where('zipcode_from','=','')
								->where('country','like','%'.$ship_country.'%')->get();
					}
				}
			}
		}
		
		$shipping_rule_id 	= $rid[0]["shipping_rule_id"];
		$rule_type  		= $rid[0]["rule_type"];
		$NewdaysVal			= $rid[0]["days"];

		########### END CODE FOR CALCULATE PROP SHIP CHARGE###########
		//$this->setShippingCharge($temp_ShippingCharge);
		return $NewdaysVal;
	}
	
	public function GetCartForStripe()
	{
		$CartItems = [];
		$i=0;
		if(Session::has('ShoppingCart'))
		{
			$ShoppingCart = Session::get('ShoppingCart');
			if(isset($ShoppingCart['Cart']) && count($ShoppingCart['Cart']) > 0)
			{
				foreach($ShoppingCart['Cart'] as $key => $Cart)
				{
					$CartItems[$i]['amount'] = round($Cart['Price']*100);
					$CartItems[$i]['label'] = $Cart['ProductName'];
					$i++;
				}
				$AllCharges = $this->GetAllCharges();
				foreach($AllCharges['Charges'] as $Charge)
				{
					$CartItems[$i]['amount'] = round($Charge['charge']*100);
					$CartItems[$i]['label'] = $Charge['label'];
					$i++;
				}
			}	
		}
		return $CartItems;
	}
	
	public function Merge_Guest_Register($email,$customerid){
		$merge_data = Customer::where('status','=','1')->where('email','=',$email)->where('registration_type','=','M')->get();
		
		if($merge_data && $merge_data->count() <= 0){
			$check_cust_email = Customer::where('status','=','1')->where('email','=',$email)->where('registration_type','=','G')->where('is_deleted','=','No')->get();
			
			if($check_cust_email && $check_cust_email->count() <= 0){
				$merge_id = $merge_data[0]['customer_id'];
				$merge_log = "Merge with ".$merge_data[0]['eusertype'].$registration_type." customer id: ".$merge_id;
				
				$CustUpdateArr = array ('is_deleted'=> "Yes",
										'merge_log' => $merge_log
										);
				$cust_upd = Customer::where('customer_id','=',$check_cust_email[0]['customer_id'])->update($CustUpdateArr);
				
				$merge_log = "Merge with ".$merge_data[0]['eusertype'].$registration_type." customer id: ".$merge_id."<br>Previous customer id: ".$check_cust_email[0]['customer_id'];
				$OrderUpdateArr = array('customer_id'  => $merge_id,
										'merge_note' => $merge_log,
										'old_customerid' => $check_cust_email[0]['customer_id']
										);
				$cust_upd = Order::where('customer_id','=',$check_cust_email[0]['customer_id'])->update($OrderUpdateArr);
			}
		}
	}
	public function getDealSubTotal()
	{
		 $tempCart = Session::get('ShoppingCart.Cart');
	//	 echo "<pre>"; print_r($tempCart); exit;
		 $TotalDeal = 0;
		 for($a=0;$a<count($tempCart);$a++)
		 {
			if(isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]=='Yes')
			{
			  $TotalDeal = $TotalDeal + $tempCart[$a]["TotPrice"];	
			}
		 }
		 return $TotalDeal;	
	}
	
	public function GetWholesalePrice($products_id, $qty = 1)
	{
		$per = 0;
		$val = 0;
		if(Session::has('eusertype') && strtolower(Session::get('eusertype'))=='wholesaler')
		{
			if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
			{
				$specialpricedtl = GetSpecialPricePercentandValue($qty);
				$perval = explode("#",$specialpricedtl);
				$per = $perval[0];
				$val = $perval[1];
			}
		
			$ProdInfo = DB::table('pu_products as p')
					->join('pu_products_one as po','p.products_id','=','po.products_id')
					->where(function($query){
						$query->orWhere('p.status','=','1');
						$query->OrWhere(function($qry){
							$qry->where('p.status','=','2')->where('po.is_private','=','Yes')->where('po.private_code','!=','');	
						});
					})
					->where('p.products_id','=',$products_id)->get();
			if(!$ProdInfo || $ProdInfo->count() == 0 )
			{
				return response()->json(array('Error' => 0));
			}
		
			$ProductRs = $this->SetProduct($ProdInfo[0]);
			
			## Here Overwrite sale Price Field
			$ProductRs->sale_price = $ProductRs->product_price;
			$actual_product_price = $ProductRs->product_price;

			$ProductRs->IsDealProducts = 'No';
			$ProductRs->DealDiscountFlag = 'No';
				
			$ProductRs->ItemPrice = NumberFormat($ProductRs->sale_price);
			$DealOfWeek = GetDealOfWeek($ProductRs->sku,'Weekly','Cart');
			if(count($DealOfWeek) > 0)
			{
				if($DealOfWeek[$ProductRs->sku]['deal_price']!='' && $DealOfWeek[$ProductRs->sku]['deal_price'] < $ProductRs->sale_price )
				{
					$dealprice = NumberFormat($DealOfWeek[$ProductRs->sku]['deal_price']);
					$ProductRs->sale_price = $dealprice;
					$ProductRs->ItemPrice  = $dealprice;
				}
			}
			
			if(Session::has('eusertype') && strtolower(Session::get('eusertype'))=='wholesaler')
			{
				if(config('Settings.WHOLESALE_MARKUP') == 'Yes')
				{
					if($per > 0)
						$ProductRs->sale_price = $ProductRs->sale_price - $ProductRs->sale_price* $per/100;
				}
			}
			return response()->json(array('Price' => Price($ProductRs->sale_price)));
		}
	}
}
