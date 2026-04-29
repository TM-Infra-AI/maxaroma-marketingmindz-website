<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\ProductDetailTrait;
use App\Http\Controllers\Traits\VendorTrait;
use App\Http\Controllers\Traits\CartTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Models\SiteOffers;
use App\Models\SiteSettings;
use App\Models\InstantCouponText;
use App\Models\InstantCoupon;
use App\Models\NewsLetter;
use App\Models\Customer;
use App\Models\WishlistCategory;
use App\Models\Category;
use App\Models\Wishlist;
use App\Models\NicheMembership;
use App\Models\Coupon;
use App\Models\Products;
use App\Models\ProductsReview;
use App\Models\Stockalert;
use App\Models\Order;
use App\Models\StaticPages;
use App\Models\ShippingMode;
use Illuminate\Support\Facades\Auth;
use App\Http\Services\PopUpServiceContract;
use App\Models\ShippingRate;
use App\Models\ShippingRule;
use App\Models\AddressBook;
use App\Models\RewardPoint;
use Illuminate\Support\Facades\DB;
use App\Models\ReturnOrders;
use App\Models\OrderDetail;
use App\Models\GiftCertificate;
use Session;
use DateTime;
use Cookie;

class PopupController extends Controller
{
	use ProductDetailTrait;
	use VendorTrait;
	use CartTrait;

	public $PageData;

	public $popUpService;

	public function __construct(PopUpServiceContract $popUpService)
	{
		$this->popUpService = $popUpService;
	}

	public function SalesOffer(Request $request)
	{

		$PageDataOne = $this->popUpService->getSiteOffers(1);

		$PageDataTwo = $this->popUpService->getSiteOffers(2);

		$this->PageData['sectionOne'] = $PageDataOne;
		$this->PageData['sectionTwo'] = $PageDataTwo;

		return view('popup.sales-offer-popup')->with($this->PageData);
	}

	public function showPopUp(Request $request)
	{
		Session::put("showPopUp", $request['flag']);
		Session::save();

		$this->PageData['InstantCouponText'] = InstantCouponText::get();
		$this->PageData['FilePath'] = config('global.SITE_URL') . 'public';

		return view('popup.instant-coupon-popup')->with($this->PageData);
	}

	public function instantCouponAjax(Request $request)
	{
		if ($request->ajax()) {

			$email = $request['email'];

			$validatedData = $request->validate([
				'email' => 'required|email'
			], [
				'email.required' => config('message.Login.Email'),
				'email.email' => config('message.Login.ValidEmail'),
			]);
			$newsLetterData = NewsLetter::where('email', '=', $email)->get();

			if (count($newsLetterData) <= 0 and !empty($email)) {
				$newsletterObj =  NewsLetter::create([
					'email' => $email,
					'status' => '1'
				]);
			}

			$InstantCouponData = InstantCoupon::where('email', '=', $email)->get();
			if (count($InstantCouponData) > 0) {
				$response['message'] = "<div class='sumsg p-4' style='color:#ea565c !important;font-weight: bold;'>Your email address is already subscribed</div>";
				return response()->json($response);
			}

			$newsletterObj =  InstantCoupon::create([
				'email' => $email,
				'reg_datetime' => date('Y-m-d H:i:s'),
				'ip_address'   => $_SERVER['REMOTE_ADDR']
			]);

			if ($newsletterObj) {
				$this->popUpService->sendInstantCouponMail($email);
				Session::put('instant_coupon_code_val', config('Settings.COUPON_CODE_VALUE'));

				$response['message'] = "success";
				return response()->json($response);
			} else {
				$response['message'] = "<div class='sumsg p-4' style='color:#ea565c !important;font-weight: bold;'>Your email address is already subscribed</div>";
				return response()->json($response);
			}
		}
	}

	public function wishlistAdd(Request $request)
	{
		if ($request->ajax()) {
			$this->PageData['var_msg'] = "";
			$this->PageData['isAction'] = $request['isAction'];
			$this->PageData['SITE_URL'] = config('global.SITE_URL');

			if ($request['isAction'] == 'wish_forget') {

				if (!isset($request['isPopup'])) {

					$validatedData = $request->validate([
						'email' => 'required|email'
					], [
						'email.required' => config('message.Login.Email'),
						'email.email' => config('message.Login.ValidEmail'),
					]);

					$email = $request['email'];
					$password = "";
					$ChkEmail = Customer::where('email', '=', $request['email'])->where('registration_type', '=', 'M')->get();

					if ($ChkEmail && $ChkEmail->count() > 0) {
						$password = $ChkEmail[0]['password'];
					} else {
						$password = "";
					}

					if (trim($password) != '') {

						$this->popUpService->sendForgotPasswordMail($email, $password);

						Session::flash('failedfp', '');
						Session::flash('successfp', config('message.Forgot.Success'));
					} else {
						Session::flash('successfp', '');
						Session::flash('failedfp', config('message.Forgot.NotExistEmail'));
					}
				}

				return view('popup.wishlist-add-popup')->with($this->PageData);
			}

			if ($request['isAction'] == 'wish_login') {

				$customer_id = Session::get('sess_icustomerid');

				if (isset($request['products_id']) && $request['products_id'] != '') {
					$products_id = $request['products_id'];
					Session::put('Wish_ProductsID', $products_id);
				} else {
					$products_id = Session::get('Wish_ProductsID');
				}

				$email = ($request['email'] ? $request['email'] : "");
				$password = ($request['password'] ? $request['password'] : "");

				if ($request->has('check_value') && $request->check_value == 1) {
					$validatedData = $request->validate([
						'email' => 'required|email',
						'password' => 'required'
					], [
						'email.required' => config('message.Login.Email'),
						'email.email' => config('message.Login.ValidEmail'),
						'password.required' => config('message.Login.Password')
					]);
				}

				if (trim($email) != '' && trim($password) != '') {

					$isLogin = $this->popUpService->LoginpProcess($email, $password);

					if ($isLogin == false) {
						Session::flash('failed', config('message.Login.Failed'));
					}
				}

				if (Session::get('sess_icustomerid') && !empty(Session::get('sess_icustomerid')) && Session::get('etype') == 'M') {

					$WishCatRS = $this->popUpService->getWishlistCategory(Session::get('sess_icustomerid'));
					$prod_info = $this->popUpService->getProductsById(Session::get('Wish_ProductsID'));

					$this->PageData['prod_info'] = $prod_info;
					$this->PageData['WishCatRS'] = $WishCatRS;
					$this->PageData['isAction'] = 'wish_product';
					Session::flash('success', '');
				} else {
					$this->PageData['isAction'] = 'wish_login';
				}

				return view('popup.wishlist-add-popup')->with($this->PageData);
			}

			if ($request['isAction'] == 'wish_category') {

				$this->PageData['Wish_ProductsID'] = Session::get('Wish_ProductsID');
				return view('popup.wishlist-add-popup')->with($this->PageData);
			}


			if ($request['isAction'] == 'AddWishProduct') {

				$validatedData = $request->validate([
					'description' => 'required',
					'wishlist_category_id' => 'required'
				], [
					'description.required' => config('message.WishList.AddDescription'),
					'wishlist_category_id.required' => config('message.WishList.Category')
				]);

				$description  = stripslashes(nl2br(strtr($request['description'], array('\r' => chr(13), '\n' => chr(10)))));
				$description  = str_replace("<br />", "", strip_tags($request['description']));

				$WishListProduct = array();
				$WishListProduct['wishlist_category_id'] = $request['wishlist_category_id'];
				$WishListProduct['customer_id'] = Session::get('sess_icustomerid');
				$WishListProduct['products_id'] = $request['productsId'];
				$WishListProduct['sku'] = $request['sku'];
				$WishListProduct['description'] = $description;
				Wishlist::create($WishListProduct);

				if (Session::get('sess_icustomerid') && !empty(Session::get('sess_icustomerid')) && Session::get('etype') == 'M') {

					$WishCatRS = $this->popUpService->getWishlistCategory(Session::get('sess_icustomerid'));
					$prod_info = $this->popUpService->getProductsById(Session::get('Wish_ProductsID'));

					$this->PageData['prod_info'] = $prod_info;
					$this->PageData['WishCatRS'] = $WishCatRS;
					$this->PageData['isAction'] = 'wish_product';
				}

				Session::flash('success', config('message.WishList.AddSuccess'));
				$this->PageData['prod_info'] = $prod_info;
				$this->PageData['WishCatRS'] = $WishCatRS;
				$this->PageData['isAction'] = 'wish_product';
				return view('popup.wishlist-add-popup')->with($this->PageData);
			}

			if ($request['isAction'] == 'AddWishCategory') {

				$validatedData = $request->validate([
					'category_name' => 'required',
					'description' => 'required'
				], [
					'category_name.required' => config('message.WishList.AddCategory'),
					'description.required' => config('message.WishList.AddDescription')
				]);

				$this->PageData['Wish_ProductsID'] = Session::get('Wish_ProductsID');
				$wishcategory = WishlistCategory::where('name', '=', trim($request['category_name']))->where('customer_id', '=', Session::get('sess_icustomerid'))->get();

				if (count($wishcategory) > 0) {
					Session::flash('successwc', '');
					Session::flash('failedwc', config('message.WishListCategory.ExistCategory'));
				} else {
					$description  = stripslashes(nl2br(strtr($request['description'], array('\r' => chr(13), '\n' => chr(10)))));
					$description  = str_replace("<br />", "", strip_tags($description));

					$WishListCategory = array();
					$WishListCategory['customer_id'] = Session::get('sess_icustomerid');
					$WishListCategory['name'] = $request['category_name'];
					$WishListCategory['description'] = $description;
					$WishListCategory['status'] = '1';
					WishlistCategory::create($WishListCategory);
					Session::flash('failedwc', '');
					Session::flash('successwc', config('message.WishListCategory.AddSuccess'));
				}

				$this->PageData['isAction'] = 'wish_category';
				return view('popup.wishlist-add-popup')->with($this->PageData);
			}
		}
	}

	public function NicheFragranceMembership(Request $request)
	{
		if ($request->ajax()) {
			Session::flash('successfm', '');
			Session::flash('failedfm', '');
			if ($request['isAction'] == 'AddNicheFragrances') {

				$validatedData = $request->validate([
					'email' => 'required|email',
					'first_name' => 'required',
					'last_name' => 'required',
					'gender' => 'required',
				], [
					'email.required' => config('message.Validate.Email'),
					'email.email' => config('message.Login.ValidEmail'),
					'first_name.required' => config('message.Validate.FirstName'),
					'last_name.required' => config('message.Login.LastName'),
					'gender.required' => config('message.Login.Gender'),
				]);
				// dd($request);
				$email = $request['email'];
				$first_name = $request['first_name'];
				$last_name = $request['last_name'];
				$gender = $request['gender'];

				$checkemailExist = NicheMembership::where('email', $email)->first();

				if ($checkemailExist) {
					Session::flash('successfm', config('message.NicheFragrances.ExistingEmail'));
					return view('popup.niche-fragrance-popup');
				} else {

					$NicheMemberShip = array();
					$NicheMemberShip['email'] = $email;
					$NicheMemberShip['first_name'] = $first_name;
					$NicheMemberShip['last_name'] = $last_name;
					$NicheMemberShip['gender'] = $gender;
					$NicheResult = NicheMemberShip::create($NicheMemberShip);

					if ($NicheResult) {
						$coupon_name = config('Settings.NICHEFRAGRANCESCODE');

						$getCouponData = Coupon::select('coupon_number')->where('coupon_number', '=', $coupon_name)->first();

						if ($getCouponData) {

							$coupon_code = $getCouponData->coupon_number;

							$this->popUpService->sendNicheFragranceMail($email, $coupon_code);
							Session::flash('successfm', config('message.NicheFragrances.AddSuccess'));
							return view('popup.niche-fragrance-popup');
						} else {
							Session::flash('failedfm', config('message.NicheFragrances.NotAvailableCoupon'));
							return view('popup.niche-fragrance-popup');
						}
					} else {
						Session::flash('failedfm', config('message.NicheFragrances.NotCreated'));
						return view('popup.niche-fragrance-popup');
					}
				}
			} else {
				return view('popup.niche-fragrance-popup');
			}
		}
	}

	public function ProductAlertMe(Request $request)
	{

		$productId = $request['productsId'];
		$sku = $request['sku'];
		$isAction = $request['isAction'];
		$email = $request['email'];

		if ($sku) {
			$prodData = Products::select('products_id', 'image', 'product_name', 'short_description', 'sku', 'UPC', 'product_description')
				->where('sku', '=', $sku)
				->get();

			if (count($prodData) > 0) {
				if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $prodData[0]->image) && $prodData[0]->image != '') {
					$thumb_image = config('global.PRD_THUMB_IMG_URL') . $prodData[0]->image;
				} else {
					$thumb_image = config('global.NO_IMAGE_THUMB');
				}
				$prodData[0]->image = $thumb_image;
				$this->PageData['prod_detail'] = $prodData;
			}
		}

		if ($isAction == "AddProductAlertMe") {
			$validatedData = $request->validate([
				'email' => 'required|email'
			], [
				'email.required' => config('message.Validate.Email'),
				'email.email' => config('message.Validate.ValidEmail'),
			]);

			$productAlert = array(
				'email'    => $email,
				'estatus'  => 'No',
				'prod_id'  => $productId,
				'sku'      => $sku,
			);
			Stockalert::create($productAlert);
			Session::flash('successam', config('message.Product.productStockAlert'));
		}

		return view('popup.product-alert-popup')->with($this->PageData);
	}

	public function EmailFriend(Request $request)
	{
		if ($request->ajax()) {
			$productId = $request['productId'];
			$this->PageData['productId'] = $productId;
			Session::flash('successef', '');
			if (isset($productId) && !empty($productId) && isset($request['isAction'])) {

				$validatedData = $request->validate([
					'fmail1' => 'required|email',
					'youremail' => 'required|email',
					'g-recaptcha-response' => 'required|captcha',
				], [
					'fmail1.required' => config('message.Validate.ValidEmail'),
					'fmail1.email' => config('message.Login.ValidEmail'),
					'youremail.required' => config('message.Validate.ValidEmail'),
					'youremail.email' => config('message.Login.ValidEmail'),
				]);

				if ($request['fmail2'] != '') {
					$validatedData = $request->validate([
						'fmail2' => 'email',
					], [
						'fmail2.email' => config('message.Login.ValidEmail'),
					]);
				}
				if ($request['fmail3'] != '') {
					$validatedData = $request->validate([
						'fmail3' => 'email',
					], [
						'fmail3.email' => config('message.Login.ValidEmail'),
					]);
				}
				if ($request['fmail4'] != '') {
					$validatedData = $request->validate([
						'fmail4' => 'email',
					], [
						'fmail4.email' => config('message.Login.ValidEmail'),
					]);
				}
				if ($request['fmail5'] != '') {
					$validatedData = $request->validate([
						'fmail5' => 'email',
					], [
						'fmail5.email' => config('message.Login.ValidEmail'),
					]);
				}
				$fmail1 = $request['fmail1'];
				$fmail2 = $request['fmail2'];
				$fmail3 = $request['fmail3'];
				$fmail4 = $request['fmail4'];
				$fmail5 = $request['fmail5'];
				$youremail = $request['youremail'];
				$yourname = $request['yourname'];

				$message  = stripslashes(nl2br(strtr($request['message'], array('\r' => chr(13), '\n' => chr(10)))));
				$message  = str_replace("<br />", "", strip_tags($message));

				$arr_toemail   = array($fmail1, $fmail2, $fmail3, $fmail4, $fmail5);

				## ## ## get product info here ## ## ## 

				$prod_info = $this->popUpService->getProductsDetailsById($productId);

				$product_name = $prod_info->product_name;
				$short_desc = $prod_info->short_description;
				$sale_price = $prod_info->sale_price;
				$medium_image = "";
				$product_page_link = "";

				## ## ##  Set Mail Here ## ## ## 

				$Template = GetMailTemplate("SEND_TO_FRIEND");

				$EmailBody = str_replace('{$SITE_NAME}', config('Settings.SITE_NAME'), $Template[0]->mail_body);
				$EmailBody = str_replace('{$Site_URL}', config('Settings.SITE_URL'), $EmailBody);
				$EmailBody = str_replace('{$TOLL_FREE_NO}', config('Settings.CONTACT_PHONE_NO'), $EmailBody);
				$EmailBody = str_replace('{$Sess_custid}', Session::get('sess_icustomerid'), $EmailBody);
				$EmailBody = str_replace('{$medium_image}', $medium_image, $EmailBody);
				$EmailBody = str_replace('{$product_name}', $product_name, $EmailBody);
				$EmailBody = str_replace('{$short_desc}', $short_desc, $EmailBody);
				$EmailBody = str_replace('{$sale_price}', $sale_price, $EmailBody);
				$EmailBody = str_replace('{$your_email}', $youremail, $EmailBody);
				$EmailBody = str_replace('{$message}', $message, $EmailBody);
				$EmailBody = str_replace('{$product_page_link}', $product_page_link, $EmailBody);

				$FreeShipping = "";
				if (config('Settings.FREESHIPPING_VALUE')) {
					$FreeShipping = '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $' . config('Settings.FREESHIPPING_VALUE') . ' or more Orders</span>';
				}

				$EmailBody = str_replace('{$freeshippinginfo}', $FreeShipping, $EmailBody);

				$Subject = str_replace('{$SITE_NAME}', config('Settings.SITE_NAME'), $Template[0]->subject);
				$From = config('Settings.CONTACT_MAIL');
				
				foreach ($arr_toemail as $key => $To) {
					if ($To) {
						//SendMail($Subject, $EmailBody, $To, $From);
						/** OMANISEND **/
						//OmanisendRequest('61e6ba7faf9060002205881d',$prod_info[0],['toMail' => $To, 'message' => $message]);
						/** OMANISEND **/
					}
				}
				Session::flash('successef', config('message.EmailAFriend.EmailSent'));

				return view('popup.email-a-friend-popup')->with($this->PageData);
			}
			return view('popup.email-a-friend-popup')->with($this->PageData);
		}
	}

	public function ProductRatingsReview(Request $request)
	{

		if ($request->ajax()) {
			$productId = $request['productId'];
			$ip_address	  = $_SERVER['REMOTE_ADDR'];
			$star_rate	  = $request['star_rate'];
			$first_name   = $request['FirstName'];
			$city 		  = $request['city'];
			$state 		  = $request['r_state'];
			$country 	  = $request['country'];
			$user_review  = $request['user_review'];

			$state = $request['state'];
			if ($request['country'] != 'US')
				$state = $request['other_state'];

			$this->PageData['Countries'] = GetCountries();
			$this->PageData['States'] = GetStates();

			$this->PageData['productId'] = $productId;
			Session::flash('successpr', '');

			$customerID = Session::get('sess_icustomerid');
			$this->PageData['state'] = $this->PageData['country']  = "";
			if ($customerID) {
				$Userdata = Customer::select('customer_id', 'first_name', 'last_name', 'city', 'country', 'state')
					->where('customer_id', '=', Auth::user()->customer_id)
					->get();

				$this->PageData['name'] = ($Userdata[0]->first_name ? $Userdata[0]->first_name . ' ' : "") . ($Userdata[0]->last_name ? $Userdata[0]->last_name : "");
				$this->PageData['city'] = ($Userdata[0]->city ? $Userdata[0]->city : "");
				$this->PageData['state'] = ($Userdata[0]->state ? $Userdata[0]->state : "");
				$this->PageData['country'] = ($Userdata[0]->country ? $Userdata[0]->country : "");
			}

			if (isset($productId) && !empty($productId) && isset($request['isAction'])) {

				$validatedData = $request->validate([
					'FirstName' => 'required',
					'user_review' => 'required',
				], [
					'FirstName.required' => config('message.Validate.FirstName'),
					'user_review.required' => config('message.Validate.Review'),
				]);

				$prodDetails = $this->popUpService->getProductsById($productId);
				$sku = "";
				if (isset($prodDetails[0]->sku)) {
					$sku = $prodDetails[0]->sku;
				}

				$productReview = ProductsReview::select('review_id', 'sku')
					->where('sku', '=', $sku)
					->where('ip_address', '=', $ip_address)
					->get();

				if (count($productReview) <= 0) {

					$customer_id = ($customerID ? $customerID : 0);
					$month = date("m");

					if ($customer_id > 0) {
						$checkReviewLimit = DB::table('pu_products_review')->select('review_id')
							->where('customer_id', '=', $customer_id)
							->whereMonth('date', $month)
							->get();
					} else {
						$checkReviewLimit = DB::table('pu_products_review')->select('review_id')
							->where('ip_address', '=', $ip_address)
							->whereMonth('date', $month)
							->get();
					}

					if (count($checkReviewLimit) < 10) {

						$insertReview =  ProductsReview::create([
							'products_id' => $productId,
							'sku' => $sku,
							'star_rate' => $star_rate,
							'first_name' => $first_name,
							'city' => $city,
							'state' => $state,
							'country' => $country,
							'user_review' => $user_review,
							'customer_id' => $customer_id,
							'date' => date("Y-m-d"),
							'approved' => 'No',
							'ip_address' => $ip_address
						]);
						if ($insertReview) {
							Session::flash('successpr', config('message.ProductRatingReview.AddSuccess'));
						} else {
							Session::flash('successpr', config('message.ProductRatingReview.ReviewFail'));
						}
					} else {
						Session::flash('successpr', config('message.ProductRatingReview.ReviewLimit'));
					}
				} else {
					Session::flash('successpr', config('message.ProductRatingReview.ReviewGiven'));
				}
			}

			return view('popup.ratings-review-popup')->with($this->PageData);;
		}
	}

	public function LoginProductDetailsPage(Request $request)
	{
		if ($request->ajax()) {

			$this->pageData['SITE_URL'] = config('global.SITE_URL');

			$productId = $request['productId'];

			$email = ($request['email'] ? $request['email'] : "");
			$password = ($request['password'] ? $request['password'] : "");

			if ($request->has('check_value') && $request->check_value == 1) {
				$validatedData = $request->validate([
					'email' => 'required|email',
					'password' => 'required'
				], [
					'email.required' => config('message.Login.Email'),
					'email.email' => config('message.Login.ValidEmail'),
					'password.required' => config('message.Login.Password')
				]);
			}

			if (trim($email) != '' && trim($password) != '') {
				$isLogin = $this->popUpService->LoginpProcess($email, $password);

				if ($isLogin == false) {
					Session::flash('failed', config('message.Login.Failed'));
					return view('popup.login-popup')->with($this->pageData);
				} else {
					$response['message'] = true;
					return response()->json($response);
				}
			} else {
				return view('popup.login-popup')->with($this->pageData);
			}
		}
	}

	public function ProductQuickView(Request $request)
	{

		$products_id = $request->productId;

		if ($products_id == '' || !is_numeric($products_id) || empty($products_id)) {
			return redirect('/');
		}

		$category_id = $request->category_id;

		$check_cat_res = Category::where('status', '=', '1')->where('category_id', '=', $category_id)->get();

		if ($category_id == '' ||  empty($category_id) || !$check_cat_res || $check_cat_res->count() == 0) {

			$res_category = DB::table('pu_products_category as pcr')
				->join('pu_category as c', 'pcr.category_id', '=', 'c.category_id')
				->select('c.category_id')
				->where('c.status', '=', '1')
				->where('pcr.products_id', '=', $products_id)
				->orderBy('c.display_position')->orderBy('c.category_name')->offset(0)->limit(1)->get();

			$category_id = $res_category[0]->category_id;
		}

		$productDetail = $this->getProductsDetail($products_id);

		$this->PageData['productDetails'] = $productDetail;

		$this->PageData['OutOfStockCount'] = 0;
		if (isset($productDetail->referenced_products['OutOfStock'])) {
			$this->PageData['OutOfStockCount'] = count($productDetail->referenced_products['OutOfStock']);
		}

		$SpecialPriceDetails = "";
		if (strtolower(Session::get('eusertype')) == 'wholesaler' && $productDetail->WebsiteStock == 'In' && ($productDetail->product_type == 'wholesaler' || $productDetail->product_type == 'both')) {
			$SpecialPriceDetails = $this->getWholesalerSpecialPricesDetails2($productDetail->product_price);
		}
		$this->PageData['SpecialPriceDetails'] = $SpecialPriceDetails;

		$breadcrumbs = $this->getProductNavigation($category_id);
		$this->PageData['breadcrumbs'] = $breadcrumbs;

		$manuf_prods = $this->list_manufacturer_gender($productDetail->products_id, $productDetail->category_id, $productDetail->brand_id, $productDetail->imanufactureid, array('U', 'W', 'M'));


		$this->PageData['manuf_prods_wom'] = array();
		if (isset($manuf_prods['W'])) {
			$this->PageData['manuf_prods_wom'] = ($manuf_prods['W'] ? $manuf_prods['W'] : array());
		}

		$this->PageData['manuf_prods_man'] = array();
		if (isset($manuf_prods['M'])) {
			$this->PageData['manuf_prods_man'] = ($manuf_prods['M'] ? $manuf_prods['M'] : array());
		}

		$this->PageData['manuf_prods_uni'] =  array();
		if (isset($manuf_prods['U'])) {
			$this->PageData['manuf_prods_uni'] = ($manuf_prods['U'] ? $manuf_prods['U'] : array());
		}

		########################### For Weeekly Deal Start ###########################
		$DealIcon = '';
		$dealofdayRS = array();
		if ($productDetail->sku != "") {
			$dealofdayRS = GetDealOfWeek($productDetail->sku, 'Weekly');
		}
		if (isset($dealofdayRS[$productDetail->sku])) {
			$dealofdayRS = $dealofdayRS[$productDetail->sku];
		}
		if (count($dealofdayRS) > 0) {
			if (isset($dealofdayRS)) {
				if ($dealofdayRS['deal_price'] != '' && $dealofdayRS['deal_price'] < $productDetail->product_price) {
					$dealprice = $dealofdayRS['deal_price'];
					if (isset($dealofdayRS['description']) && $dealofdayRS['description'] != '') {
						$productDetail->short_description = $dealofdayRS['description'];
					}
					$yousave = ($productDetail->retail_price - $dealofdayRS['deal_price']) / $productDetail->retail_price;
					$yousave = $yousave * 100;
					$yousave = number_format($yousave, 0);
					$productDetail->yousave   = $yousave;
					$productDetail->dealprice = $dealprice;
					$yousaveprice = $productDetail->retail_price - $dealofdayRS['deal_price'];
					$productDetail->yousaveprice = $yousaveprice;
					$productDetail->product_price = $dealprice;
				}
				$productDetail->dealprice = (isset($productDetail->dealprice) ? $productDetail->dealprice : '');
				if ($productDetail->dealprice == "") {
					$productDetail->dealprice = $productDetail->product_price;
				}
				$this->PageData['DateDiff'] =  date_diff(date_create($dealofdayRS['start_date']), date_create($dealofdayRS['end_date']));

				$dealofdayRS['formatted_end_date']	=  (isset($dealofdayRS['end_date']) ? date('d', strtotime($dealofdayRS['end_date'])) : '');
				$dealofdayRS['formatted_end_month']	= (isset($dealofdayRS['end_date']) ? date('m', strtotime($dealofdayRS['end_date'])) : '');
				$dealofdayRS['formatted_end_year']	= (isset($dealofdayRS['end_date']) ? date('Y', strtotime($dealofdayRS['end_date'])) : '');
				$DealStartDate = date("d-m-Y H:i:s");
				$DealEndDate = $dealofdayRS['formatted_end_date'] . "-" . $dealofdayRS['formatted_end_month'] . "-" . $dealofdayRS['formatted_end_year'] . " 23:59:59";

				$this->PageData['DealEndDate'] = $DealEndDate;
				$this->PageData['DealStartDate'] = $DealStartDate;

				$DealIcon = $this->getIconsSaleDeal("Yes", "No", "No");
				if ($dealofdayRS['deal_type'] == trim("Weekly")) {
					$this->PageData['deal_type'] = "Weekly";
				}
			}
		}
		$this->PageData['DealIcon'] = $DealIcon;
		$this->PageData['dealofdayRS'] = $dealofdayRS;
		## For Weeekly Deal End ###########################

		################ Recent viewed item Logic start #########################
		$arr_recent_item = array();
		if (Session::has('RECENT_VIEWED_ITEMS') && Session::get('RECENT_VIEWED_ITEMS') && count(Session::get('RECENT_VIEWED_ITEMS')) > 0) {
			$arr_recent_item = $this->getRecent_ViewedItems($products_id);
		}
		$this->PageData['arr_recent_item'] = (isset($arr_recent_item) ? $arr_recent_item : array());

		if (!Session::has('RECENT_VIEWED_ITEMS')) {
			$productArry = array();
			Session::put('RECENT_VIEWED_ITEMS', $productArry);
			Session::save();
		}
		if (Session::has('RECENT_VIEWED_ITEMS') && !in_array((int)$products_id, Session::get('RECENT_VIEWED_ITEMS'))) {
			$productArry = Session::get('RECENT_VIEWED_ITEMS');
			array_push($productArry, (int)$products_id);
			Session::put('RECENT_VIEWED_ITEMS', $productArry);
			Session::save();
		}
		################ Recent viewed item Logic End ##############################

		################ Max2Day Start ##################
		$this->PageData['Max2Day'] = Session::get('Max2Day');

		if ($productDetail->stock == "In" && $productDetail->WebsiteStock == "Out") {
			if (is_numeric(config('Settings.VENDOR_ARRIVES_FROM_DAYS')) && is_numeric(config('Settings.VENDOR_ARRIVES_TO_DAYS')) && is_numeric(config('Settings.VENDOR_EXPEDITED_DAYS'))) {
				$currentDate = date("M. d");
				$estimateDate = date('M. d', strtotime("+" . config('Settings.VENDOR_ARRIVES_FROM_DAYS') . " Weekday"));
				$estimateDate1 = date('M. d', strtotime("+" . config('Settings.VENDOR_ARRIVES_TO_DAYS') . " Weekday"));
				$totalEstimate = $estimateDate . ' - ' . $estimateDate1;
				$this->PageData['totalEstimate'] = $totalEstimate;
				$fastEstimateDate = date('l, F d', strtotime("+" . config('Settings.VENDOR_EXPEDITED_DAYS') . " Weekday"));
			}
		} else {
			if (is_numeric(config('Settings.ARRIVES_FROM_DAYS')) && is_numeric(config('Settings.ARRIVES_TO_DAYS')) && is_numeric(config('Settings.EXPEDITED_DAYS'))) {
				$currentDate = date("M. d");
				$estimateDate = date('M. d', strtotime("+" . config('Settings.ARRIVES_FROM_DAYS') . " Weekday"));
				$estimateDate1 = date('M. d', strtotime("+" . config('Settings.ARRIVES_TO_DAYS') . " Weekday"));
				$totalEstimate = $estimateDate . ' - ' . $estimateDate1;
				$this->PageData['totalEstimate'] = $totalEstimate;
				$fastEstimateDate = date('l, F d', strtotime("+" . config('Settings.EXPEDITED_DAYS') . " Weekday"));
			}
		}

		$this->PageData['fastEstimateDate'] = $fastEstimateDate;

		$DayVal = date("h@@a");
		$DayValArr = explode("@@", $DayVal);
		$startdateVal = date("Y-m-d H:i:s");
		$enddateVal = date("Y-m-d 02:00:00", strtotime("+2 Weekday"));
		$maxtwodaycontent =  date('l F d', strtotime("+2 Weekday"));
		if ($DayValArr[1] == "pm") {
			if ($DayValArr[0] >= 2) {
				$maxtwodaycontent = date('l F d', strtotime("+3 Weekday"));
				$enddateVal = date("Y-m-d 02:00:00", strtotime("+3 Weekday"));
			}
		}
		$datetime1 = new DateTime($startdateVal);
		$datetime2 = new DateTime($enddateVal);
		$interval = $datetime1->diff($datetime2);

		$this->PageData['Maxhour'] = (int)($interval->format('%h') * $interval->format('%d'));
		$this->PageData['Maxmin'] = $interval->format('%i');
		$this->PageData['maxtwodaycontent'] = $maxtwodaycontent;
		############### Max2Day End #########################

		$arr_product_review = $this->getProductReview($productDetail->sku);
		$avg_rate = $this->getProductAverageRating($productDetail->sku);

		$this->PageData['avg_rate'] = $avg_rate;
		$this->PageData['arr_product_review'] = $arr_product_review;

		// dd($this->PageData);
		return view('popup.product-quick-view-popup')->with($this->PageData);
	}

	public function GetProductQuickViewRef(Request $request)
	{
		$products_id = $request->products_id;

		if ($products_id == '' || !is_numeric($products_id) || empty($products_id)) {
			return redirect('/');
		}

		$category_id = $request->category_id;

		$check_cat_res = Category::where('status', '=', '1')->where('category_id', '=', $category_id)->get();

		if ($category_id == '' ||  empty($category_id) || !$check_cat_res || $check_cat_res->count() == 0) {

			$res_category = DB::table('pu_products_category as pcr')
				->join('pu_category as c', 'pcr.category_id', '=', 'c.category_id')
				->select('c.category_id')
				->where('c.status', '=', '1')
				->where('pcr.products_id', '=', $products_id)
				->orderBy('c.display_position')->orderBy('c.category_name')->offset(0)->limit(1)->get();

			$category_id = $res_category[0]->category_id;
		}

		$productDetail = $this->getProductsDetail($products_id);

		$this->PageData['productDetails'] = $productDetail;

		$this->PageData['OutOfStockCount'] = 0;
		if (isset($productDetail->referenced_products['OutOfStock'])) {
			$this->PageData['OutOfStockCount'] = count($productDetail->referenced_products['OutOfStock']);
		}

		$SpecialPriceDetails = "";
		if (strtolower(Session::get('eusertype')) == 'wholesaler' && $productDetail->WebsiteStock == 'In' && ($productDetail->product_type == 'wholesaler' || $productDetail->product_type == 'both')) {
			$SpecialPriceDetails = $this->getWholesalerSpecialPricesDetails2($productDetail->product_price);
		}
		$this->PageData['SpecialPriceDetails'] = $SpecialPriceDetails;

		$breadcrumbs = $this->getProductNavigation($category_id);
		$this->PageData['breadcrumbs'] = $breadcrumbs;



		$productDetail->SaleIcon = '';
		$productDetail->sale_item = '0';
		$this->PageData['SaleIcon'] = '';
		$this->PageData['GiftwrapIcon'] = '';
		if ($productDetail->sale_price > 0 && strtolower(Session::get('eusertype')) != 'wholesaler') {
			$productDetail->sale_item = '1';
		}

		if (isset($productDetail->sale_item) && $productDetail->sale_item == 1) {
			$productDetail->SaleIcon = $this->getIconsSaleDeal("No", "Yes");
			$this->PageData['SaleIcon'] = $productDetail->SaleIcon;
		}

		$productDetail->GiftwrapIcon = '';
		if ($productDetail->is_gift_wrap == "Yes") {
			$productDetail->GiftwrapIcon = $this->getIconsSaleDeal("No", "No", "Yes");
			$this->PageData['GiftwrapIcon'] = $productDetail->GiftwrapIcon;
		}

		########################### For Weeekly Deal Start ###########################
		$DealIcon = '';
		$dealofdayRS = array();
		if ($productDetail->sku != "") {
			$dealofdayRS = GetDealOfWeek($productDetail->sku, 'Weekly');
		}
		if (isset($dealofdayRS[$productDetail->sku])) {
			$dealofdayRS = $dealofdayRS[$productDetail->sku];
		}
		if (count($dealofdayRS) > 0) {
			if (isset($dealofdayRS)) {
				if ($dealofdayRS['deal_price'] != '' && $dealofdayRS['deal_price'] < $productDetail->product_price) {
					$dealprice = $dealofdayRS['deal_price'];
					if (isset($dealofdayRS['description']) && $dealofdayRS['description'] != '') {
						$productDetail->short_description = $dealofdayRS['description'];
					}
					$yousave = ($productDetail->retail_price - $dealofdayRS['deal_price']) / $productDetail->retail_price;
					$yousave = $yousave * 100;
					$yousave = number_format($yousave, 0);
					$productDetail->yousave   = $yousave;
					$productDetail->dealprice = $dealprice;
					$yousaveprice = $productDetail->retail_price - $dealofdayRS['deal_price'];
					$productDetail->yousaveprice = $yousaveprice;
					$productDetail->product_price = $dealprice;
				}
				if ($productDetail->dealprice == "") {
					$productDetail->dealprice = $productDetail->product_price;
				}
				$this->PageData['DateDiff'] =  date_diff(date_create($dealofdayRS['start_date']), date_create($dealofdayRS['end_date']));

				$dealofdayRS['formatted_end_date']	=  (isset($dealofdayRS['end_date']) ? date('d', strtotime($dealofdayRS['end_date'])) : '');
				$dealofdayRS['formatted_end_month']	= (isset($dealofdayRS['end_date']) ? date('m', strtotime($dealofdayRS['end_date'])) : '');
				$dealofdayRS['formatted_end_year']	= (isset($dealofdayRS['end_date']) ? date('Y', strtotime($dealofdayRS['end_date'])) : '');
				$DealStartDate = date("d-m-Y H:i:s");
				$DealEndDate = $dealofdayRS['formatted_end_date'] . "-" . $dealofdayRS['formatted_end_month'] . "-" . $dealofdayRS['formatted_end_year'] . " 23:59:59";

				$this->PageData['DealEndDate'] = $DealEndDate;
				$this->PageData['DealStartDate'] = $DealStartDate;

				$DealIcon = $this->getIconsSaleDeal("Yes", "No", "No");
				if ($dealofdayRS['deal_type'] == trim("Weekly")) {
					$this->PageData['deal_type'] = "Weekly";
				}
			}
		}
		$this->PageData['DealIcon'] = $DealIcon;
		$this->PageData['dealofdayRS'] = $dealofdayRS;
		## For Weeekly Deal End ###########################

		################ Recent viewed item Logic start #########################
		$arr_recent_item = array();
		if (Session::has('RECENT_VIEWED_ITEMS') && Session::get('RECENT_VIEWED_ITEMS') && count(Session::get('RECENT_VIEWED_ITEMS')) > 0) {
			$arr_recent_item = $this->getRecent_ViewedItems($products_id);
		}
		$this->PageData['arr_recent_item'] = (isset($arr_recent_item) ? $arr_recent_item : array());

		if (!Session::has('RECENT_VIEWED_ITEMS')) {
			$productArry = array();
			Session::put('RECENT_VIEWED_ITEMS', $productArry);
			Session::save();
		}
		if (Session::has('RECENT_VIEWED_ITEMS') && !in_array((int)$products_id, Session::get('RECENT_VIEWED_ITEMS'))) {
			$productArry = Session::get('RECENT_VIEWED_ITEMS');
			array_push($productArry, (int)$products_id);
			Session::put('RECENT_VIEWED_ITEMS', $productArry);
			Session::save();
		}
		################ Recent viewed item Logic End ##############################

		################ Max2Day Start ##################
		$this->PageData['Max2Day'] = Session::get('Max2Day');

		if ($productDetail->stock == "In" && $productDetail->WebsiteStock == "Out") {
			if (is_numeric(config('Settings.VENDOR_ARRIVES_FROM_DAYS')) && is_numeric(config('Settings.VENDOR_ARRIVES_TO_DAYS')) && is_numeric(config('Settings.VENDOR_EXPEDITED_DAYS'))) {
				$currentDate = date("M. d");
				$estimateDate = date('M. d', strtotime("+" . config('Settings.VENDOR_ARRIVES_FROM_DAYS') . " Weekday"));
				$estimateDate1 = date('M. d', strtotime("+" . config('Settings.VENDOR_ARRIVES_TO_DAYS') . " Weekday"));
				$totalEstimate = $estimateDate . ' - ' . $estimateDate1;
				$this->PageData['totalEstimate'] = $totalEstimate;
				$fastEstimateDate = date('l, F d', strtotime("+" . config('Settings.VENDOR_EXPEDITED_DAYS') . " Weekday"));
			}
		} else {
			if (is_numeric(config('Settings.ARRIVES_FROM_DAYS')) && is_numeric(config('Settings.ARRIVES_TO_DAYS')) && is_numeric(config('Settings.EXPEDITED_DAYS'))) {
				$currentDate = date("M. d");
				$estimateDate = date('M. d', strtotime("+" . config('Settings.ARRIVES_FROM_DAYS') . " Weekday"));
				$estimateDate1 = date('M. d', strtotime("+" . config('Settings.ARRIVES_TO_DAYS') . " Weekday"));
				$totalEstimate = $estimateDate . ' - ' . $estimateDate1;
				$this->PageData['totalEstimate'] = $totalEstimate;
				$fastEstimateDate = date('l, F d', strtotime("+" . config('Settings.EXPEDITED_DAYS') . " Weekday"));
			}
		}

		$this->PageData['fastEstimateDate'] = $fastEstimateDate;

		$DayVal = date("h@@a");
		$DayValArr = explode("@@", $DayVal);
		$startdateVal = date("Y-m-d H:i:s");
		$enddateVal = date("Y-m-d 02:00:00", strtotime("+2 Weekday"));
		$maxtwodaycontent =  date('l F d', strtotime("+2 Weekday"));
		if ($DayValArr[1] == "pm") {
			if ($DayValArr[0] >= 2) {
				$maxtwodaycontent = date('l F d', strtotime("+3 Weekday"));
				$enddateVal = date("Y-m-d 02:00:00", strtotime("+3 Weekday"));
			}
		}
		$datetime1 = new DateTime($startdateVal);
		$datetime2 = new DateTime($enddateVal);
		$interval = $datetime1->diff($datetime2);

		$this->PageData['Maxhour'] = (int)($interval->format('%h') * $interval->format('%d'));
		$this->PageData['Maxmin'] = $interval->format('%i');
		$this->PageData['maxtwodaycontent'] = $maxtwodaycontent;
		############### Max2Day End #########################

		$arr_product_review = $this->getProductReview($productDetail->sku);
		$avg_rate = $this->getProductAverageRating($productDetail->sku);

		$this->PageData['avg_rate'] = $avg_rate;
		$this->PageData['arr_product_review'] = $arr_product_review;
		$quickview_price_details = view('popup.quick_view_details.quickview_price_details')->with($this->PageData)->render();
		$quickview_addtocart_description = view('popup.quick_view_details.quickview_addtocart_description')->with($this->PageData)->render();
		$productIcons = view('popup.quick_view_details.quickview_producticon')->with($this->PageData)->render();
		$quickviewprodimg = view('popup.quick_view_details.quickview_productimg')->with($this->PageData)->render();



		$product_url = $this->getProductRewriteURL($productDetail->products_id, $productDetail->product_name, $category_id, $productDetail->vmanufacture);

		$array = array(
			'quickview_price_details' => $quickview_price_details,
			'quickview_addtocart_description' => $quickview_addtocart_description,
			'productIcons' => $productIcons,
			'sku' => $productDetail->sku,
			'quickviewprodimg' => $quickviewprodimg,
		);

		return response()->json($array);
	}

    public function ReturnOrder(Request $request)
	{

		if ($request['isAction'] == 'returnorder') {
	
			if($request['order_details_id'] != null && $request['order_details_id'] != '') {
				if ($request['action'] == 'trackorder') {
					$ord_data = Order::select('customer_id')
						->where('orders_id', '=', $request['orders_id'])
						->first();
					$customerId = $ord_data->customer_id;
				} else {
					$customerId = Auth::user()->customer_id;
					$ord_data = Order::select('customer_id')
						->where('orders_id', '=', $request['orders_id'])
						->first();
					if (empty($ord_data) || $ord_data->customer_id != Auth::user()->customer_id) {
						return redirect()->back()
							->withInput()
							->withErrors([
								'something_went_wrong' => config('message.General.SomethingWentWrong')
							]);
					}
				}
				
				if($request['customerReturnReason']!='Other') {
					$request['otherreason'] = "";
				}
				if($request['otherreason']!='' && $request['customerReturnReason']=="Other") {
					$request['customerReturnReason'] = $request['otherreason'];
				}

				$quantity = $request['quantity'];
						
				$reason_return = trim($request['customerReturnReason'])."##";
				$order_detail_id_return = $request['order_details_id']."##";
				$quantity_return = $quantity."##";
				
				$return_details = "<tr>
										<td>".$request['sku']."</td>
										<td>".trim($request['customerReturnReason'])."</td>
									</tr>";
    
				if($request['customerReturnReason'] == "Item Damaged"){
					
					if ($request->hasFile('damaged_item_image'))
					{
						
						$resSKU = OrderDetail::select('sku')
									->where('orders_detail_id', $request['order_details_id'])
									->first();

						$image = $request->file('damaged_item_image');
						if($resSKU && isset($resSKU->sku) && $resSKU->sku != null){
							$image_name = $resSKU->sku.'-damaged-'.time().'.jpg';
						}else{
							$image_name = $request['order_details_id'].'-damaged-'.time().'.jpg';
						}
				
						$path_large = config('global.RETURN_PDF_PATH')."damaged/".$image_name;
						
						if (file_exists(config('global.RETURN_PDF_PATH')."damaged/".$image_name)) {
							unlink(config('global.RETURN_PDF_PATH')."damaged/".$image_name);
						}
						
						if(copy($request->file('damaged_item_image'),$path_large))
						{
							$damaged_item_image = $image_name;
						}
					}else{
						$damaged_item_image = '';
					}
				}else{
					$damaged_item_image = '';
				}

				$returnOrderData = new ReturnOrders;
				$returnOrderData->customer_id = $customerId;
				$returnOrderData->orders_id = $request["orders_id"];
				$returnOrderData->reason = rtrim($reason_return,"##");
				$returnOrderData->is_rma_scan = 'No';
				$returnOrderData->damaged_item_image = $damaged_item_image;
				$returnOrderData->status = 'Return Requested';
				$returnOrderData->orders_detail_id = rtrim($order_detail_id_return,"##");
				$returnOrderData->quantity = rtrim($quantity_return,"##");
				$returnOrderData->save();

				$updateQry = Order::where('orders_id', (int)$request["orders_id"])
										->update(['status' => 'Return Requested']);

					$return_info = "&nbsp;(<a href='".config('global.SITE_ADMIN_URL')."index.php?f=viewreturnorder&return_id=".$returnOrderData->return_id."' target='_blank'>Return Info</a>)";
				    $returnLink = config('global.SITE_ADMIN_URL')."index.php?f=viewreturnorder&return_id=".$returnOrderData->return_id;
					$updateQry = OrderDetail::where('orders_detail_id', '=',$request['order_details_id'])
											->update(['ostatus' => 'Return Requested']);
				
				Session::flash('success', config('message.Order.ReturnRequestSubmit'));

				$orderRes = Order::where('orders_id', (int)$request["orders_id"])
										->select('orders_no', 'bill_email')
										->first();
                if($orderRes  && $orderRes->count() > 0) {
                    $mail = GetMailTemplate("ORDER_RETURN_NOTIFICATION");
                    $EmailBody = str_replace('{$return_details}',$return_details,$mail[0]->mail_body);
                    $EmailBody = str_replace('{$return_info}',$return_info,$EmailBody);
                    $EmailBody = str_replace('{$order_no}',$orderRes->order_no,$EmailBody);
                    $EmailBody = str_replace('{$SITE_NAME}',config('Settings.SITE_TITLE'),$EmailBody);
                    $EmailBody = str_replace('{$Site_URL}',config('global.Site_URL'),$EmailBody);
                    $EmailBody = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$EmailBody);
                    $freeshippinginfo = '';
                    if(config('Settings.FREESHIPPING_VALUE')!="")
                    {
                        $freeshippinginfo .= '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</span>';
                    }
                    $EmailBody = str_replace('{$freeshippinginfo}',$freeshippinginfo,$EmailBody);
                    $Subject = $mail[0]->subject;
                    $To = config('Settings.ADMIN_MAIL');
                    $From = config('Settings.CONTACT_MAIL');
                    //SendMail($Subject,$EmailBody,$To,$From);
                    /** OMANISEND **/
                    OmanisendRequest('6201293db86552001e977a84',$orderRes,['returnLink' => $returnLink, 'return_info' => $return_info,'reason' => trim($request['customerReturnReason']), 'sku' => $request['sku']]);
                    //OmanisendRequest('61e55276af90600022058216',$User);
                    /** OMANISEND **/
                }
				if ($request['action'] == 'trackorder') {
					$orders_id = $orderRes->orders_no;
					$bill_email = $orderRes->bill_email;
					$GC_Only = 0;
					$OrderRs = Order::select('*')->addSelect(DB::raw("DATE_FORMAT(order_datetime, '%m-%d-%Y %H:%i') AS datetime,DATE_FORMAT(order_datetime, '%d-%m-%Y') AS newdatetime,DATEDIFF(now(), order_datetime) as days"))
						->where('orders_no', '=', $orders_id)
						->where('bill_email', '=', $bill_email)
						->first();
					$OrderDetailRs = OrderDetail::select('*')
						->where('orders_id', '=', $OrderRs->orders_id)
						->get();

					if (count($OrderDetailRs) > 0) {
						for ($p = 0; $p < count($OrderDetailRs); $p++) {
							if ($OrderDetailRs[$p]["sku"] == config('global.GIFT_CERTIFICATE_SKU')) {

								$GCRs = GiftCertificate::where('orders_detail_id', '=', $OrderDetailRs[$p]['orders_detail_id'])
									->where('customer_id', '=', $customerId)
									->first();
								if ($GCRs && $GCRs->count() > 0) {
									$OrderDetailRs[$p]['RecipientName']  	= $GCRs->recipient_name;
									$OrderDetailRs[$p]['RecipientEmail'] 	= $GCRs->recipient_email;
									$OrderDetailRs[$p]['Image']				= config('global.GC_IMAGE_URL');
								}
							} else {

								$prod_res = Products::whereRaw("LOWER(TRIM(sku))='" . strtolower(trim($OrderDetailRs[$p]['sku'])) . "'")->select('image')->first();
								if ($prod_res && $prod_res->count() > 0) {
									if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $prod_res->image) && !empty($prod_res->image))
										$thumb_image = config('global.PRD_THUMB_IMG_URL') . $prod_res->image;
									else
										$thumb_image = config('global.NO_IMAGE_THUMB');
								} else {
									$thumb_image = config('global.NO_IMAGE_THUMB');
								}
								$OrderDetailRs[$p]['Image'] = $thumb_image;
							}
						}
					}

					if ($OrderRs->is_only_gc == 1) {
						$GC_Only = 1;
					}
					$this->PageData['OrderRs'] = $OrderRs;
					$this->PageData['OrderDetailRs'] = $OrderDetailRs;
					$this->PageData['GC_Only'] = $GC_Only;
					$view_fileType = "";
					if (Session::get('eusertype') == "Wholesaler" || (Session::get('eusertype') == "Wholesaler" && Session::get('is_dropshipper') == "Yes")) {
						$view_fileType  = 'pdf';
					} else {
						$view_fileType = 'print';
					}
					$this->PageData['view_fileType'] = $view_fileType;

					$this->PageData['meta_title'] =  config('Settings.SITE_TITLE') . ' :: Order Detail';
					$this->PageData['CSSFILES'] = ['myaccount.css'];
					return view('myaccount.orderdetail')->with($this->PageData);
				} else {
					return redirect()->back();
				}									

					
			} else {
				return redirect()->back()
				->withInput()
				->withErrors([
					'order_not_returned' => config('message.Order.OrderNotReturned')
				]);
			}
		}
		
		$this->PageData['trackorder'] = ($request["action"] ? $request["action"] : "");
		$this->PageData['orders_id'] = $request["orders_id"];
		$this->PageData['order_details_id'] = $request["order_details_id"];
		$this->PageData['qty'] = $request["qty"];
		
		return view('popup.return-order-popup')->with($this->PageData);
	}

	public function CancelOrder(Request $request)
	{
		$this->PageData['orders_id'] = $request["orders_id"];
		$this->PageData['trackorder'] = ($request["action"] ? $request["action"] : "");
		if ($request['isAction'] == 'cancelorder') {
			if ($request['customerCancelReason'] != 'Other') {
				$request['otherreason'] = "";
			}
			$updateArr = array(
				'status'   					=> 'Request Cancellation',
				'customer_cancelReason'		=> $request['customerCancelReason'],
				'other_customer_cancelReason'	=> $request['otherreason'],
				'cancellation_reasons'			=> "Buyer Canceled",
				'CancelRequestDate'			=> date("Y-m-d h:i:s")
			);
			$updateQry = Order::find((int)$request["orders_id"]);
			$updateRecord = $updateQry->update($updateArr);
			if ($updateRecord) {
				$orderRes = Order::select('orders_no', 'bill_email')
					->where('orders_id', '=', $request["orders_id"])
					->first();
				$orders_no = $orderRes->orders_no;
				$reason = $request['customerCancelReason'];
				if ($request['customerCancelReason'] == 'Other') {
					$reason = $request['otherreason'];
				}

				$mail = GetMailTemplate("ORDER_CANCEL_NOTIFICATION");
				// dd($mail);
				$EmailBody = $mail[0]->mail_body;
				$EmailBody = str_replace('{$order_no}', $orders_no, $EmailBody);
				$EmailBody = str_replace('{$reason}', $reason, $EmailBody);
				$EmailBody = str_replace('{$SITE_NAME}', config('Settings.SITE_TITLE'), $EmailBody);
				$EmailBody = str_replace('{$Site_URL}', config('global.SITE_URL'), $EmailBody);
				$EmailBody = str_replace('{$CONTACT_MAIL}', config('Settings.CONTACT_MAIL'), $EmailBody);
				$freeshippinginfo = '';
				if (config('Settings.FREESHIPPING_VALUE') != "") {
					$freeshippinginfo .= '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $' . config('Settings.FREESHIPPING_VALUE') . ' or more Orders</span>';
				}
				$EmailBody = str_replace('{$freeshippinginfo}', $freeshippinginfo, $EmailBody);

				$Subject = str_replace('{$order_no}', $orders_no, $mail[0]->subject);
				$To = config('Settings.ADMIN_MAIL');
				$From = $orderRes->bill_email;
				//SendMail($Subject, $EmailBody, $To, $From);
				/** OMANISEND **/
				$orderRes->reason = $reason;
				//OmanisendRequest('62012eb2b86552001e977a87',$orderRes);
				/** OMANISEND **/
				Session::flash('success', config('message.CancelOrder.OrderCancelSuccess'));
			} else {
				Session::flash('error', config('message.CancelOrder.OrderCancelFail'));
			}
			if ($request['action'] == 'trackorder') {
				$orders_id = $orderRes->orders_no;
				$bill_email = $orderRes->bill_email;
				$GC_Only = 0;
				$OrderRs = Order::select('*')->addSelect(DB::raw("DATE_FORMAT(order_datetime, '%m-%d-%Y %H:%i') AS datetime,DATE_FORMAT(order_datetime, '%d-%m-%Y') AS newdatetime,DATEDIFF(now(), order_datetime) as days"))
					->where('orders_no', '=', $orders_id)
					->where('bill_email', '=', $bill_email)
					->first();
				$OrderDetailRs = OrderDetail::select('*')
					->where('orders_id', '=', $OrderRs->orders_id)
					->get();

				if (count($OrderDetailRs) > 0) {
					for ($p = 0; $p < count($OrderDetailRs); $p++) {
						if ($OrderDetailRs[$p]["sku"] == config('global.GIFT_CERTIFICATE_SKU')) {

							$GCRs = GiftCertificate::where('orders_detail_id', '=', $OrderDetailRs[$p]['orders_detail_id'])
								->where('customer_id', '=', Auth::user()->customer_id)
								->first();
							if ($GCRs && $GCRs->count() > 0) {
								$OrderDetailRs[$p]['RecipientName']  	= $GCRs->recipient_name;
								$OrderDetailRs[$p]['RecipientEmail'] 	= $GCRs->recipient_email;
								$OrderDetailRs[$p]['Image']				= config('global.GC_IMAGE_URL');
							}
						} else {

							$prod_res = Products::whereRaw("LOWER(TRIM(sku))='" . strtolower(trim($OrderDetailRs[$p]['sku'])) . "'")->select('image')->first();
							if ($prod_res && $prod_res->count() > 0) {
								if (file_exists(config('global.PRD_THUMB_IMG_PATH') . $prod_res->image) && !empty($prod_res->image))
									$thumb_image = config('global.PRD_THUMB_IMG_URL') . $prod_res->image;
								else
									$thumb_image = config('global.NO_IMAGE_THUMB');
							} else {
								$thumb_image = config('global.NO_IMAGE_THUMB');
							}
							$OrderDetailRs[$p]['Image'] = $thumb_image;
						}
					}
				}

				if ($OrderRs->is_only_gc == 1) {
					$GC_Only = 1;
				}
				$this->PageData['OrderRs'] = $OrderRs;
				$this->PageData['OrderDetailRs'] = $OrderDetailRs;
				$this->PageData['GC_Only'] = $GC_Only;
				$view_fileType = "";
				if (Session::get('eusertype') == "Wholesaler" || (Session::get('eusertype') == "Wholesaler" && Session::get('is_dropshipper') == "Yes")) {
					$view_fileType  = 'pdf';
				} else {
					$view_fileType = 'print';
				}
				$this->PageData['view_fileType'] = $view_fileType;

				$this->PageData['meta_title'] =  config('Settings.SITE_TITLE') . ' :: Order Detail';
				$this->PageData['CSSFILES'] = ['myaccount.css'];
				return view('myaccount.orderdetail')->with($this->PageData);
			} else {
				return redirect()->back();
			}
		}
		return view('popup.cancel-order-popup')->with($this->PageData);
	}

	public function AddFund(Request $request)
	{
		$NetTotal = $this->GetNetTotal();
		Log::info($NetTotal . '--' . $request->pageFrom);
		$CustomerFund = Customer::select('available_funds', 'email')
			->where('customer_id', '=', Session::get('sess_icustomerid'))
			->where('eusertype', '=', 'Wholesaler')
			->where('is_dropshipper', '=', 'Yes')
			->where('status', '=', '1')->get();
		$AvailableFund = 0;
		if ($CustomerFund && $CustomerFund->count() > 0) {
			$AvailableFund = $CustomerFund[0]['available_funds'];
		}
		$this->PageData['FundAmount'] = config('Settings.FUND_AMOUNT_LIMIT');
		if (isset($request->pageFrom) && $request->pageFrom == 'billing') {
			$RemainingFund = $NetTotal - $AvailableFund;
			if ($NetTotal > $AvailableFund) {
				if ($RemainingFund > config('Settings.FUND_AMOUNT_LIMIT')) {
					$this->PageData['FundAmount'] =  $RemainingFund;
				} else {
					$this->PageData['FundAmount'] = config('Settings.FUND_AMOUNT_LIMIT');
				}
			} else {
				$this->PageData['FundAmount'] = config('Settings.FUND_AMOUNT_LIMIT');
			}
		}
		Session::put('CurrFundAmount', $this->PageData['FundAmount']);
		return view('popup.add-fund-popup')->with($this->PageData);
	}

	public function ShippingCalculate(Request $request)
	{


		if ($request->ajax() && $request->has('value_check') && $request->value_check == 1) {

			$ship_country = $request->country;
			$ship_state = $request->state;
			$ship_zip = $request->zip;

			$ShippingModeRS = ShippingMode::where('status', '=', '1')->orderBy('display_position', 'ASC')->get();

			// dd($ShippingModeRS);

			if (Session::has('ShoppingCart.Shipping'))
				$Sess_ShippingInfo = Session::get('ShoppingCart.Shipping');

			// 	dd($Sess_ShippingInfo);

			$ROW_STR = '<table class="table" style="font-size:14px"> <thead>
						<tr>
						<td width="70%" align="left" style="font-weight: 600;"><strong>Shipping Method Name</strong></td>
						<td width="30%" align="right" style="font-weight: 600;"><strong>Price</strong></td>
						</tr> </thead>
						<tbody class="lightbg" valign="top">';

			$count = 0; // This var used for count availabe method

			$CartInfo 		 = Session::get('ShoppingCart.Cart');
			$TotalQuantity 	 = count($CartInfo);

			$subTotal = Session::get('ShoppingCart.SubTotal');
			for ($p = 0; $p < count($ShippingModeRS); $p++) {

				$shipping_mode_id = $this->CheckAvailableShippingMethod($ShippingModeRS[$p]->shipping_mode_id, $ship_country, $ship_state, $ship_zip);

				if (is_int($shipping_mode_id) == true and $shipping_mode_id > 0) {
					$tempCharge = $this->CalculateAvailableShippingCharge($ship_zip, $ship_state, $ship_country, $shipping_mode_id, $subTotal, $TotalQuantity);

					$charge_str = '$0.00';

					if ($tempCharge > 0) {
						$charge_str = $this->Make_Price($tempCharge, true);
					}

					if (empty($Sess_ShippingInfo['ShippingMethodID'])) {
						if ($count == 0)
							$r_sel = " checked ";
						else
							$r_sel = "";
					} else {
						if ($Sess_ShippingInfo['ShippingMethodID'] == $ShippingModeRS[$p]['shipping_mode_id'])
							$r_sel = " checked ";
						else
							$r_sel = "";
					}
					$estimateShipDate = '';

					if ($ShippingModeRS[$p]['days'] == 0) {


						$estimateShipDate = '';
					} else {

						$dt_date =  date('m/d/Y', strtotime("+" . $ShippingModeRS[$p]['days'] . "days"));
						$estimateShipDate = '(Est. Delivery ' . $dt_date . ')';
					}

					$ROW_STR .= ' <tr align="center">
					<td align="left">' . $ShippingModeRS[$p]['type'] . '</td>
					<td align="right">' . $charge_str . " " . $estimateShipDate . '</td>
					</tr>';

					$count = $count + 1;
				} else {
					continue;
				}
			}
			$response['shipppingfield'] = $ROW_STR;
			return response()->json($response);
		} else {
			$this->PageData['Countries'] = GetCountries();
			$this->PageData['States'] = GetStates();
			return view('popup.shipping-calculate-pop-up')->with($this->PageData);
		}
	}

	public function CheckAvailableShippingMethod($shipping_mode_id = NULL, $ship_country, $ship_state, $ship_zip)
	{
		$shipping_mode_id = (int)$shipping_mode_id;

		$ShippingMethodRS = ShippingMode::select('shipping_mode_id')->where('shipping_mode_id', '=', $shipping_mode_id)->get();
		if ($ship_country != "") {

			## this condition is for Z + S + C
			$rid = ShippingRule::select('*')
				->where('shipping_mode_id', '=', $shipping_mode_id)
				->where('zipcode_to', '>=', $ship_zip)
				->where('zipcode_from', '<=', $ship_zip)
				->where('state', 'like', '%' . $ship_state . '%')
				->where('country', 'like', '%' . $ship_country . '%')
				->get();
			## this condition is for Z + C
			if (count($rid) <= 0) {
				$rid = ShippingRule::select('*')
					->where('shipping_mode_id', '=', $shipping_mode_id)
					->where('zipcode_to', '>=', $ship_zip)
					->where('zipcode_from', '<=', $ship_zip)
					->where('country', 'like', '%' . $ship_country . '%')
					->get();
				## this condition is for S + C
				if (count($rid) <= 0) {
					$rid = ShippingRule::select('*')
						->where('shipping_mode_id', '=', $shipping_mode_id)
						->where('state', 'like', '%' . $ship_state . '%')
						->where('country', 'like', '%' . $ship_country . '%')
						->get();
					## this condition is for only C
					if (count($rid) <= 0) {
						$rid = ShippingRule::select('*')
							->where('shipping_mode_id', '=', $shipping_mode_id)
							->where('zipcode_to', '=', '')
							->where('zipcode_from', '=', '')
							->where('state', '=', '')
							->where('country', 'like', '%' . $ship_country . '%')
							->get();
					}
				}
			}

			if (count($rid) > 0) {
				return $ShippingMethodRS[0]['shipping_mode_id'];
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	public function CalculateAvailableShippingCharge($ship_zip, $ship_state, $ship_country, $shipping_mode_id, $subTotal, $TotalQuantity)
	{
		$ship_country = substr($ship_country, 0, 2);
		$shipping_mode_id = (int)$shipping_mode_id;
		$totalitem = $TotalQuantity;
		if ($ship_country != "") {
			## this condition is for Z + S + C
			$rid = ShippingRule::select('shipping_rule_id', 'rule_type', 'is_free_ship', 'free_ship_amt', 'prop_item', 'prop_charge')
				->where('shipping_mode_id', '=', $shipping_mode_id)
				->where('zipcode_to', '>=', $ship_zip)
				->where('zipcode_from', '<=', $ship_zip)
				->where('state', 'like', '%' . $ship_state . '%')
				->where('country', 'like', '%' . $ship_country . '%')
				->get();

			## this condition is for Z + C
			if (count($rid) <= 0) {
				$rid = ShippingRule::select('shipping_rule_id', 'rule_type', 'is_free_ship', 'free_ship_amt', 'prop_item', 'prop_charge')
					->where('shipping_mode_id', '=', $shipping_mode_id)
					->where('zipcode_to', '>=', $ship_zip)
					->where('zipcode_from', '<=', $ship_zip)
					->where('country', 'like', '%' . $ship_country . '%')
					->get();

				## this condition is for S + C
				if (count($rid) <= 0) {
					$rid = ShippingRule::select('shipping_rule_id', 'rule_type', 'is_free_ship', 'free_ship_amt', 'prop_item', 'prop_charge')
						->where('shipping_mode_id', '=', $shipping_mode_id)
						->where('state', 'like', '%' . $ship_state . '%')
						->where('country', 'like', '%' . $ship_country . '%')
						->get();

					## this condition is for only C
					if (count($rid) <= 0) {
						$rid = ShippingRule::select('shipping_rule_id', 'rule_type', 'is_free_ship', 'free_ship_amt', 'prop_item', 'prop_charge')
							->where('shipping_mode_id', '=', $shipping_mode_id)
							->where('zipcode_to', '=', '')
							->where('zipcode_from', '=', '')
							->where('state', '=', '')
							->where('country', 'like', '%' . $ship_country . '%')
							->get();
					}
				}
			}
		}

		$shipping_rule_id = $rid[0]["shipping_rule_id"];
		$rule_type = $rid[0]["rule_type"];

		if ($shipping_rule_id != "" && $rule_type == 1) {
			$rowrate = ShippingRate::select('charge')
				->where('shipping_rule_id', '=', $shipping_rule_id)
				->where('order_amount', '<=', $subTotal)
				->orderBy('order_amount', 'DESC')
				->first();
		} else if ($shipping_rule_id != "" && $rule_type == 0) {
			$rowrate = ShippingRate::select('charge')
				->where('shipping_rule_id', '=', $shipping_rule_id)
				->where('order_amount', '<=', $totalitem)
				->orderBy('order_amount', 'DESC')
				->first();
			############ FOR FREE SHIPPING FOR ITEM COUNT ##########
			if ($rid[0]["is_free_ship"] == "Yes") {
				if ($rid[0]["free_ship_amt"] <= $subTotal) {
					$temp_ShippingCharge = 0;
					return $temp_ShippingCharge;
				}
			}
			############## FOR FREE SHIPPING FOR ITEM COUNT ##############
		}
		// if(!empty($rowrate)) {
		if ($rowrate && $rowrate->count() > 0) {
			$charge = $rowrate->charge;
		} else {
			$charge = 0;
		}

		if ($charge > 0) {
			$temp_ShippingCharge = $charge;
		} else {
			$temp_ShippingCharge = 0;
		}

		########### START CODE FOR CALCULATE PROP SHIP CHARGE###########
		if ($rid[0]["prop_item"] > 0) {
			if ($rid[0]["prop_charge"] > 0) {
				if ($totalitem >= $rid[0]["prop_item"]) {
					$extraitem = ($totalitem - $rid[0]["prop_item"]) + 1;
					$propshippingcharge = ($rid[0]["prop_charge"] * $extraitem);
					$temp_ShippingCharge = $temp_ShippingCharge + $propshippingcharge;
				}
			}
		}

		return $temp_ShippingCharge;
	}

	public function FreeShippingPopUp(Request $request)
	{
		$PData = StaticPages::where('name', '=', 'free-shipping')->where('status', '=', '1')->get();
		if (count($PData) > 0) {
			$Content = $PData[0]->content;
			$Content = stripslashes($Content);
			$Content = str_replace('{$Site_URL}', config('global.SITE_URL'), $Content);
			$Content = str_replace('{$SECURED_PATH}', config('global.SECURED_PATH'), $Content);
			$Content = str_replace('src="images/', 'src="' . config('global.SITE_URL') . 'images/', $Content);
			$this->PageData['PageTitle'] = $PData[0]->title;
			$this->PageData['PageContent'] = $Content;
		} else {
			$this->PageData['PageTitle'] = '';
			$this->PageData['PageContent'] = '<h5 class="text-center">Coming Soon...</h5>';
		}
		return view('popup.static-page-popup')->with($this->PageData);
	}

	public function ShippingServicePopUp(Request $request)
	{
		$PData = StaticPages::where('name', '=', 'shipping_service')->where('status', '=', '1')->get();
		if (count($PData) > 0) {
			$Content = $PData[0]->content;
			$Content = stripslashes($Content);
			$Content = str_replace('{$Site_URL}', config('global.SITE_URL'), $Content);
			$Content = str_replace('{$SECURED_PATH}', config('global.SECURED_PATH'), $Content);
			$Content = str_replace('src="images/', 'src="' . config('global.SITE_URL') . 'images/', $Content);
			$this->PageData['PageTitle'] = $PData[0]->title;
			$this->PageData['PageContent'] = $Content;
		} else {
			$this->PageData['PageTitle'] = '';
			$this->PageData['PageContent'] = '<h5 class="text-center">Coming Soon...</h5>';
		}
		return view('popup.static-page-popup')->with($this->PageData);
	}

	public function WholesalerShippingPolicyPopUp(Request $request)
	{
		$PData = StaticPages::where('name', '=', 'wholesaler_shipping_policy')->where('status', '=', '1')->get();
		if (count($PData) > 0) {
			$Content = $PData[0]->content;
			$Content = stripslashes($Content);
			$Content = str_replace('{$Site_URL}', config('global.SITE_URL'), $Content);
			$Content = str_replace('{$SECURED_PATH}', config('global.SECURED_PATH'), $Content);
			$Content = str_replace('src="images/', 'src="' . config('global.SITE_URL') . 'images/', $Content);
			$this->PageData['PageTitle'] = $PData[0]->title;
			$this->PageData['PageContent'] = $Content;
		} else {
			$this->PageData['PageTitle'] = '';
			$this->PageData['PageContent'] = '<h5 class="text-center">Coming Soon...</h5>';
		}
		return view('popup.static-page-popup')->with($this->PageData);
	}

	public function SigninSignUpPopUp(Request $request)
	{
		if ($request->ajax()) {
			$this->PageData['var_msg'] = "";
			$this->PageData['isAction'] = $request['isAction'];
			$this->PageData['SITE_URL'] = config('global.SITE_URL');

			if ($request['isAction'] == 'sign_in') {

				$email = ($request['email'] ? $request['email'] : "");
				$password = ($request['password'] ? $request['password'] : "");

				if ($request->has('check_value') && $request->check_value == 1) {
					$validatedData = $request->validate([
						'email' => 'required|email',
						'password' => 'required'
					], [
						'email.required' => config('message.Login.Email'),
						'email.email' => config('message.Login.ValidEmail'),
						'password.required' => config('message.Login.Password')
					]);
				}

				if (trim($email) != '' && trim($password) != '') {
					$isLogin = $this->popUpService->LoginpProcess($email, $password);

					if ($isLogin == 'wrong') {
						Session::flash('failed', config('message.Login.Failed'));
						return view('popup.signin-signup-popup')->with($this->PageData);
					} 
					if($isLogin == 'inactive'){
					    	Session::flash('failed', "User waiting for approval");
						return view('popup.signin-signup-popup')->with($this->PageData);
					}
					if($isLogin == 'active') {
						$response['message'] = true;
						Session::forget('ShoppingCart.BillingAddress');
						Session::forget('ShoppingCart.ShippingAddress');
						$this->GenerateShopCartFromCookieAfterLogin();
						$this->StoreShopCartInCookie();
						return response()->json($response);
					}
				} else {
					return view('popup.signin-signup-popup')->with($this->PageData);
				}
			}
			$email = ($request['email'] ? $request['email'] : "");
			$password = ($request['password'] ? $request['password'] : "");

			$this->PageData['email'] = $email;
			$this->PageData['password'] = $password;
			
			if ($request['isAction'] == 'sign_upsecondpart') {
				$this->PageData['Countries'] = GetCountries();
				$this->PageData['States'] = GetStates();
				$this->PageData['YoutubeAttrs'] = GetCustomerAttribute('Youtube');
				$this->PageData['InstagramAttrs'] = GetCustomerAttribute('Instagram');
				$this->PageData['SelCountry'] = 'US';

				if($request->has('check_value') && $request->check_value == 1)
				{
					$this->PageData['SelCountry'] = $request['country'];
					$validatedData = $request->validate([
										'first_name' => 'required',
										'last_name'	=> 'required',
										'address1' => 'required',
										'city' => 'required',
										'zip' => 'required',
										'phone' => 'required',
										'email' => 'required|email',
										'password' => 'required|same:confirm_Password',
										'confirm_Password' => 'required',
										'instagram' => 'required_if:hear_about,Instagram',
										'youtube' => 'required_if:hear_about,Youtube',
										'other' => 'required_if:hear_about,Other',
										'state' => 'required_if:country,US',
										'other_state' => 'required_unless:country,US',
									], [
										'first_name.required' => config('message.Validate.FirstName'),
										'last_name.required' => config('message.Validate.LastName'),
										'address1.required' => config('message.Validate.Address'),
										'city.required' => config('message.Validate.City'),
										'zip.required' => config('message.Validate.ZipCode'),
										'phone.required' => config('message.Validate.Phone'),
										'email.required' => config('message.Validate.ValidEmail'),
										'email.email' => config('message.Validate.ValidEmail'),
										'password.required' => config('message.Register.Password'),
										'password.same' => config('message.Validate.ValidConfirmPassword'),
										'confirm_Password.required' => config('message.Validate.ValidConfirmPassword'),
										'instagram.required_if' => config('message.Register.Instagram'),
										'youtube.required_if' => config('message.Register.Youtube'),
										'other.required_if' => config('message.Register.OtherText'),
										'state.required_if' => config('message.Validate.State'),
										'other_state.required_unless' => config('message.Validate.OtherState'),
									]);

					$ChkEmail = Customer::where('email','=',$request['email'])->where('registration_type','=','M')->get();
					if($ChkEmail && $ChkEmail->count() > 0)
					{

						Session::flash('error', config('message.Register.ExistingEmail'));
						return view('popup.signin-signup-popup')->with($this->PageData);
						// return redirect()->back()
						// ->withInput()
						// ->withErrors([
						// 	'existing_email' => config('message.Register.ExistingEmail'),
						// ]);
					}
					$ChkIP = Customer::where('customer_ip','=',$_SERVER['REMOTE_ADDR'])->where('registration_type','=','M')->get();
					if($ChkIP && $ChkIP->count() >= 5)
					{
						Session::flash('error', config('message.Register.DuplicateIP'));
						return view('popup.signin-signup-popup')->with($this->PageData);

						// return redirect()->back()
						// ->withInput()
						// ->withErrors([
						// 	'duplicate_ip' => config('message.Register.DuplicateIP'),
						// ]);
					}
					$genderoption = array();

					if(isset($request['niche']) && !empty($request['niche'])){
						array_push($genderoption,'Niche');
					}
					if(isset($request['designer']) && !empty($request['designer'])){
						array_push($genderoption,'Designer');
					}
					if(isset($request['traveler_spray']) && !empty($request['traveler_spray'])){
						array_push($genderoption,'Traveler Spray');
					}
					$State = $request['state'];
					if($request['country'] != 'US')
						$State = $request['other_state'];
					$UserData = array(
						'first_name' => $request['first_name'],
						'last_name' => $request['last_name'],
						'company_name' => ($request['company_name'] != '') ? $request['company_name'] : '',
						'address1' => $request['address1'],
						'address2' => ($request['address2'] != '') ? $request['address2'] : '',
						'phone' => $request['phone'],
						'email' => $request['email'],
						'password' => $request['password'],
						'city' => $request['city'],
						'state' => $State,
						'country' => $request['country'],
						'zip' => $request['zip'],
						'status' => '1',
						'eusertype' => 'Retailer',
						'customer_ip' => $_SERVER['REMOTE_ADDR'],
						'customer_browser' => $_SERVER['HTTP_USER_AGENT'],
						'is_dropshipper' => "No",
						'iRewardpoint' => '150',
						'upd_datetime' => date('Y-m-d H:i:s'),
						'merge_log' => 'Auto updated from guest to member on registration page',
						'gender' => $request['gender'],
						'genderoption'=>($genderoption?implode(', ', $genderoption):''),
						
					);
					
					$User = Customer::create($UserData);
					if($User)
					{
						$iCustomerId = $User->customer_id;
						$AddressBook=array();
						$AddressBook['customer_id'] = $iCustomerId;
						$AddressBook['title'] = 'Self Address';
						$AddressBook['email'] = $User->email;
						$AddressBook['first_name'] = $User->first_name;
						$AddressBook['last_name'] = $User->last_name;
						$AddressBook['address1'] = $User->address1;
						$AddressBook['address2'] = $User->address2;
						$AddressBook['company_name'] = $User->company_name;
						$AddressBook['city'] = $User->city;
						$AddressBook['state'] = $User->state;
						$AddressBook['country'] = $User->country;
						$AddressBook['zip'] = $User->zip;
						$AddressBook['phone'] = $User->phone;
						$AddressBook['status'] = '1';
						$UserAddress = AddressBook::create($AddressBook);
						
						$RewardPoints = array();
						$RewardPoints["customer_id"] = $iCustomerId;
						$RewardPoints["note"] = "Reward Point Added By Register";
						$RewardPoints["iRewardpoint"] = 150;
						$UserRewardPoints = RewardPoint::create($RewardPoints);
						
						$request->session()->regenerate();
						Session::put('sess_useremail',$User->email);
						Session::put('sess_username',$User->first_name);
						Session::put('sess_icustomerid',$User->customer_id);
						Session::put('eusertype',$User->eusertype);
						Session::put('is_dropshipper',$User->is_dropshipper);
						Session::put('etype','M');
						Session::put('eusertype','Retailer');
						Session::put('payment_amount',$User->payment_amount);
						Session::flash('success', config('message.Register.Success'));
						
						$Template = GetMailTemplate("CUSTOMER_REGISTER");
						$EmailBody = str_replace('{$vFirstName}',$User->first_name,$Template[0]->mail_body);
						$EmailBody = str_replace('{$vLastName}',$User->last_name,$EmailBody);
						$EmailBody = str_replace('{$vemail}',$User->email,$EmailBody);
						$EmailBody = str_replace('{$password}',$User->password,$EmailBody);
						$EmailBody = str_replace('{$COUPON_CODE_VALUE}',config('Settings.COUPON_CODE_VALUE'),$EmailBody);
						$EmailBody = str_replace('{$CONTACT_MAIL}',config('Settings.CONTACT_MAIL'),$EmailBody);
						$FreeShipping = "";
						if(config('Settings.FREESHIPPING_VALUE')) {
							$FreeShipping = '<span style="font-size:16px; font-family:Arial;"><strong>FREE</strong> Shipping On $'.config('Settings.FREESHIPPING_VALUE').' or more Orders</span>';
						}
						$EmailBody = str_replace('{$freeshippinginfo}',$FreeShipping,$EmailBody);
						
						$To = $User->email;
						$Subject = $Template[0]->subject;
						$EmailBody = $Template[0]->mail_body;
						$From = config('Settings.ADMIN_MAIL');
						//SendMail($Subject,$EmailBody,$To,$From);
						
						/** OMANISEND **/
						$NewsLetter = (isset($request['termsprivacy']))?'Yes':'No';
						OmanisendRequest('create_customer',$User,['newsletter' => $NewsLetter]);
						/** OMANISEND **/
						
						/** YOTPO **/
						//YotpoRequest('create_customer',$User);
						/** YOTPO **/
						
						Auth::login($User);
                        //Cookie::queue(Cookie::make('omnisendContactID',$User->omnisend_accountid,time()+60*60*24*15));
                        Cookie::make('omnisendContactID',$User->omnisend_accountid,time()+60*60*24*15);
						$this->GenerateShopCartFromCookieAfterLogin();
						$this->StoreShopCartInCookie();
						$response['message'] = true;
						return response()->json($response);
						return view('popup.signin-signup-popup')->with($this->PageData);
					} else {
						Session::flash('failed', config('message.Register.Failed'));
						return view('popup.signin-signup-popup')->with($this->PageData);
					}
				}else {		
					$ChkEmail = Customer::where('email','=',$request['email'])->where('registration_type','=','M')->get();
					if($ChkEmail && $ChkEmail->count() > 0)
					{
						$this->PageData['isAction'] = 'sign_upfirstpart';
						Session::flash('error', config('message.Register.ExistingEmail'));
						return view('popup.signin-signup-popup')->with($this->PageData);
					}

					$email = ($request['email'] ? $request['email'] : "");
					$password = ($request['password'] ? $request['password'] : "");

					$this->PageData['email'] = $email;
					$this->PageData['password'] = $password;

					return view('popup.signin-signup-popup')->with($this->PageData);
				}
			}

			if ($request['isAction'] == 'sign_upfirstpart') {
				return view('popup.signin-signup-popup')->with($this->PageData);
			}
		}
	}
	public function WholesalerTerms(Request $request)
	{
		return view('popup.wholesaler-terms')->with($this->PageData);
	}
}
