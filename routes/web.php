<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\StaticpageController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\PopupController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\ShoppingcartController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\MainCategoryController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\PaypalController;
use App\Http\Controllers\AfterpayController;
use App\Http\Controllers\AmazonpayController;
use App\Http\Controllers\FrontCacheController;
use App\Http\Controllers\SocialLoginController;
use App\Http\Controllers\FbUserDeleteController;
use App\Http\Controllers\LandingpageController;
use App\Http\Controllers\SkuController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
| 
*/

Route::get('/clear-cache', function() {
   $exitCode = Artisan::call('cache:clear');
});

Route::get('/clear-view', function() {
   $exitCode = Artisan::call('view:clear');
});

/** Homepage Module Start **/
Route::get('/', [HomeController::class,'index'])->name('home');
Route::get('/homepagebanners', [HomeController::class,'HomePageBanners']);
/** Homepage Module End **/

/** Customer Module Start **/
Route::get('/register.html', [CustomerController::class,'Register'])->name('retailer-registration');
Route::post('/register.html', [CustomerController::class,'Register']);
Route::get('/login.html', [CustomerController::class,'Login'])->name('login');
Route::post('/login.html', [CustomerController::class,'Login']);
Route::get('/logout.html', [CustomerController::class,'Logout']);
Route::post('/autologin', [CustomerController::class,'Login']);
Route::post('/forgot-password.html', [CustomerController::class,'ForgotPassword'])->name('forgot-password');
Route::get('/forgot-password.html', [CustomerController::class,'ForgotPassword'])->name('forgot-password');
Route::get('/wholesaleregister.html', [CustomerController::class,'WholeSaleRegister'])->name('wholesaler-registration');
Route::post('/wholesaleregister.html', [CustomerController::class,'WholeSaleRegister']);
Route::get('/wholesale-register.html', [CustomerController::class,'WholeSaleRegister']);
Route::post('/wholesale-register.html', [CustomerController::class,'WholeSaleRegister']);


/* Below routes are accessible only if the user is logged in, otherwise it will be redirected to the login page. */
Route::middleware(['auth'])->group(function () {
	Route::get('/myaccount.html', [CustomerController::class,'Myaccount']);
	Route::get('/sendmail', [CustomerController::class,'SendMails']);
	Route::get('/editprofile.html', [CustomerController::class,'EditProfile']);
	Route::post('/editprofile.html', [CustomerController::class,'EditProfile']);
	Route::get('/changepassword.html', [CustomerController::class,'ChangePassword']);
	Route::post('/changepassword.html', [CustomerController::class,'ChangePassword']);
	Route::get('/addressbook.html', [CustomerController::class,'AddressBook']);
	Route::post('/addressbook.html', [CustomerController::class,'AddressBook']);
	Route::get('/addressbook/add.html',[CustomerController::class,'AddAddressbook']);
	Route::post('/addressbook/add.html',[CustomerController::class,'AddAddressbook']);
	Route::get('/addressbook/edit/{id}.html',[CustomerController::class,'EditAddressbook']);
	Route::post('/addressbook/edit/{id}.html',[CustomerController::class,'EditAddressbook']);
	Route::delete('/addressbook/remove.html',[CustomerController::class,'RemoveAddressbook'])->name('remove-addressbook');
	Route::get('/referral-customer.html', [CustomerController::class,'ReferralCustomer']);
	Route::get('/cancel-orders.html', [CustomerController::class,'OrderCancel'])->name('cancel_orders');
	Route::get('/order-history.html', [CustomerController::class,'OrderHistory'])->name('order_history');
	Route::post('/order-history.html', [CustomerController::class,'OrderHistory']);
	Route::get('/order-detail/{id}.html', [CustomerController::class,'OrderDetail']);
	Route::get('/order-detail-pdf/{id}.html', [CustomerController::class,'OrderDetailPdf']);
	Route::get('/wish-category.html', [CustomerController::class,'wishCategory']);
	Route::delete('/wish-category.html', [CustomerController::class,'wishCategory']);
	Route::get('/wish-category/{category_id}.html', [CustomerController::class,'WishCategoryEdit']);
	Route::post('/wish-category/{category_id}.html', [CustomerController::class,'WishCategoryEdit']);
	Route::get('/wish-product/{category_id}.html', [CustomerController::class,'WishProduct']);
	Route::delete('/wish-product/{category_id}.html', [CustomerController::class,'WishProduct']);
	Route::get('/order-item-return.html', [CustomerController::class,'OrderItemReturn']);
	Route::post('/order-item-return.html', [CustomerController::class,'OrderItemReturn']);
	Route::get('/dropshipper-fund-summary.html', [CustomerController::class,'DropshipperFundSummary'])->name('dropshipper-fund-summary');
	Route::get('/editftp.html', [CustomerController::class,'EditFtp']);
	Route::post('/editftp.html', [CustomerController::class,'EditFtp']);
	Route::get('/import-order.html', [CustomerController::class,'ImportOrder']);
	// Route::post('/import-order.html', [CustomerController::class,'ImportOrder']);
	Route::post('/import-order-csv.html', [ImportExportController::class,'ImportOrderCSV']);
	//Route::get('/imported-order-list.html', [CustomerController::class,'ImportedOrderList']);
	Route::get('/imported-order-list.html', [CustomerController::class,'ImportedOrderList'])->name('DropshipOrder');
	Route::get('/imported-order-detail/{id}.html', [CustomerController::class,'ImportedOrderDetail']);
	Route::post('/imported-order-detail/{id}.html', [CustomerController::class,'ImportedOrderDetail']);
	
	Route::delete('/delete-imported-order-list.html', [CustomerController::class,'DeleteImportedOrderList']);
	Route::get('/export-fund-history.html', [ImportExportController::class,'ExportFundHistory']);

	Route::get('/re-order-detail/{id}.html', [CustomerController::class,'ReOrderDetail']);
	Route::get('/exportorders.html', [CustomerController::class,'ExportOrders']);
	Route::post('/export-order-csv.html', [ImportExportController::class,'ExportOrderCSV']);
    Route::get('/return-orders.html', [CustomerController::class,'OrderReturnHistory'])->name('return_orders');	
	Route::post('/return-orders.html', [CustomerController::class,'OrderReturnHistory']);
	Route::get('/tracking/{id}', [CustomerController::class,'OrderTracking']);	

});

Route::get('/order-detail-print/{id}.html', [CustomerController::class,'OrderDetailPrint']);
Route::get('/special-product-list/key-{search_keyword}/{all_items?}', [GeneralController::class,'WholeSaleProducts']);
Route::get('/special-product-list/{all_items?}', [GeneralController::class,'WholeSaleProducts']);
Route::post('/specialproductlistmore', [GeneralController::class,'WholeSaleProducts']);
Route::post('/searchwholesaleproducts', [GeneralController::class,'SearchWholeSaleProducts']);
Route::get('/specialwholesaleproductpricelist', [GeneralController::class,'SpecialWholeSaleProductList']);	
Route::get('/download_specialwholesaleproductlist', [GeneralController::class,'SpecialWholeSaleProductList_Download']);	
Route::post('/wholesalealertpopup', [GeneralController::class,'GetProductDetailAlert']);
Route::post('/changecurrency', [GeneralController::class,'ChangeCurrency']);

Route::get('/payment/{id}', [GeneralController::class,'PhoneorderPayReceipt'])->name('phoneorder_payment_receipt');
Route::get('/invoice/{invoice_no}', [GeneralController::class,'PhoneorderDownloadInvoice']);
Route::get('/stripe/phoneorder', [StripeController::class,'PhoneOrder']);
Route::get('/payment_process/{id}/{success}', [GeneralController::class,'PhoneorderPayReceiptResponse']);
Route::get('/downloadpricelist.html', [GeneralController::class,'DownloadPPL']);
/** Customer Module End **/

/** Staticpage Module Start **/

Route::get('/free-sample.html', function () {
return redirect('/site-page/free-sample.html', 301);
});
Route::get('/store-credit.html', function () {
return redirect('/site-page/store-credit.html', 301);
});

Route::get('/site-page/store_credit.html', function () {
return redirect('/site-page/store-credit.html', 301);
});

Route::get('/site-page/privacy_policy.html', function () {
	return redirect('/privacy-policy.html', 301);
});
	
Route::get('/site-page/terms_and_conditions.html', function () {
	return redirect('/terms-and-conditions.html', 301);
});

Route::get('/site-page/site_map.html', function () {
	return redirect('/site-map.html', 301);
});

Route::get('/site-page/shipping_policy.html', function () {
	return redirect('/shipping-policy.html', 301);
});

Route::get('/site-page/security_policy.html', function () {
	return redirect('/security-policy.html', 301);
});
Route::get('/site-page/return_exchange_policy.html', function () {
	return redirect('/return-exchange-policy.html', 301);
});
Route::get('/site-page/FAQS.html', function () {
	return redirect('/faq.html', 301);
});

Route::get('/site-page/shipping_information.html', function () {
	return redirect('/shipping-policy.html', 301);
});

Route::get('/site-page/contactus.html', function () {
	return redirect('/contact-us.html', 301);
});

// Route::get('/site-page/coupons_promotional.html', function () {
// 	return redirect('/coupons-promotional.html', 301);
// });

Route::get('/dontseereq.html', function () {
	return redirect('/dont-see-request.html', 301);
});

// Route::get('/site-page/coupon-code.html', function () {
// 	return redirect('/coupons-promotional.html', 301);
// });
Route::get('/site-page/LimeSpot.html', function () {
	return redirect('/', 301);
});
Route::get('/site-page/celebrity_perfume.html', function () {
	return redirect('/', 301);
});

Route::get('/site-page/wholesaler_shipping_policy.html', function () {
	return redirect('/site-page/wholesaler-shipping-policy.html', 301);
});
Route::get('/site-page/Redemption_policy.html', function () {
	return redirect('/site-page/redemption-policy.html', 301);
});

Cache::forget('StaticPagesCache');
if(!Cache::has('StaticPagesCache'))
{
	GetPages();
}

foreach(Cache::get('StaticPagesCache') as $StaticPage)
{
	Route::get($StaticPage['link'],[StaticpageController::class,'show'])->name($StaticPage['slug']);
}
Route::get('/brand-name-perfumes.html', [StaticpageController::class,'BrandPerfume']);
Route::get('/contact-us.html', [StaticpageController::class,'ContactUs']);

Route::get('/contact-us.html2', [StaticpageController::class,'ContactUs2']);

Route::post('/get_brands', [StaticpageController::class,'GetBrands']);
Route::get('/track-order.html', [StaticpageController::class,'TrackOrder']);
Route::post('/track-order.html', [StaticpageController::class,'TrackOrder']);
Route::get('/dont-see-request.html', [StaticpageController::class,'DontSeeRequest']);
Route::post('/dont-see-request.html', [StaticpageController::class,'DontSeeRequest']);

Route::get('/faq.html', [StaticpageController::class,'FAQ']);

/** Staticpage Module End **/

/** Brand Controller Start **/
Route::get('/{brand_name}/smid-{brand_id}', [BrandController::class,'BrandPage']);
Route::get('/{brand_name}/tpid/{brand_id}', [BrandController::class,'BrandHistory']);
Route::post('/getbrandproducts', [BrandController::class,'GetBrandProducts']); 
Route::post('/getbrandhistorybundleproducts', [BrandController::class,'GetBrandHistoryBundleProducts']);
Route::get('/maxaroma-bundles', [BrandController::class,'MaxaromaBundles']);  

/** Brand Controller End **/

/** Category Controller Start **/
Route::get('/{category_name}/{category_name1}/scid/{category_id}', [CategoryController::class,'CategoryPage'])->name('CategoryPage1');
Route::get('/{category_name}/scid/{category_id}', [CategoryController::class,'CategoryPage'])->name('CategoryPage2');
Route::get('/{category_name}/tpid/{category_id}', [CategoryController::class,'CategoryPage'])->name('CategoryPage3');
/** Category Controller End **/

/** Product Controller Start **/

Route::get('/p4u/mid-/view', function () {
return redirect('/brand-name-perfumes.html', 301);
});

Route::get('/p4u/mid-339/cid-3/view', function () {
return redirect('/brand-name-perfumes.html', 301);
});

Route::get('/aaron-terence-hughes/', function () {
return redirect('/brand-name-perfumes.html', 301);
});

Route::get('/fragrances/unisex-perfumes/p4u/cid-4/pp-64/view', function () {
return redirect('/fragrances/unisex-perfumes/p4u/cid-4/view', 301);
});

Route::get('/newsletter/newsletter.html1629143878609', function () {
return redirect('/', 301);
});

Route::get('/max2017/privacy-policy.html', function () {
return redirect('/privacy-policy.html', 301);
});


Route::get('/p4u/key-Jessica-Mcclintock-', function () {
return redirect('/p4u/key-Jessica-Mcclintock/view', 301);
});


Route::get('/p4u/key-Roja/pp-64/view', function () {
return redirect('/p4u/key-Roja/view', 301);
});


Route::get('/{category_name}/{category_name1}/p4u/cid-{category_id}/{filters?}', [ProductController::class,'ProductList'])->where('filters', '(.*)')->name('product-list1');
Route::get('/{category_name}/p4u/cid-{category_id}/{filters?}', [ProductController::class,'ProductList'])->where('filters', '(.*)')->name('product-list2');
Route::get('/{category_name}/p4u/cid-{category_id}/{filters?}', [ProductController::class,'ProductList'])->where('filters', '(.*)')->name('product-list3');
Route::get('/p4u/cid-{category_id}/{filters?}', [ProductController::class,'ProductList'])->where('filters', '(.*)')->name('product-list4');


Route::post('/get_products', [ProductController::class,'ProductListPage']); 
Route::get('/{brand_name}/p4u/mid-{mid}/{filters?}', [ProductController::class,'BrandProductList'])->where('filters', '(.*)');
Route::get('/{category_name}/p4u/{filters?}', [ProductController::class,'ProductList'])->where('filters', '(.*)');
Route::get('/promotional.html', [BrandController::class,'Promotional']);
Route::post('/getpromotional', [BrandController::class,'GetPromotional']);
Route::get('/offers.html', [BrandController::class,'Offers']);

Route::get('/maxtwoday.html/{filters?}', [ProductController::class,'Maxtwoday'])->where('filters', '(.*)'); 
Route::post('/getmaxtwoday', [ProductController::class,'GetMaxtwoday']);


Route::post('/searchspring_autocomplete',[ProductController::class, 'SearchSpringAutocomplete']);
Route::get('/p4u/key-{keyword}/view', [ProductController::class,'ProductList'])->name('product-list5');
Route::get('allproducts', [ProductController::class,'allproducts'])->name('allproducts');
Route::get('getproducts', [ProductController::class,'getproducts'])->name('getproducts');
/** Product Controller End **/


/** Popup Controller Start **/
Route::post('/get_sales_offers', [PopupController::class, 'SalesOffer']);
Route::post('/show_popup',[PopupController::class, 'showPopUp']);
Route::post('/instant_coupon_ajax',[PopupController::class, 'instantCouponAjax']);
Route::post('/wishlist_add',[PopupController::class, 'wishlistAdd']);
Route::post('/niche_fragrance_membership',[PopupController::class, 'NicheFragranceMembership']);
Route::post('/product_alert_me',[PopupController::class, 'ProductAlertMe']);
Route::post('/email_friend', [PopupController::class, 'EmailFriend']);
Route::post('/ratings_review', [PopupController::class, 'ProductRatingsReview']);
Route::post('/login_pdetail_page', [PopupController::class, 'LoginProductDetailsPage']);
Route::post('/product_quick_view', [PopupController::class, 'ProductQuickView']);
Route::post('/cancel_order', [PopupController::class, 'CancelOrder']);
Route::post('/return_order', [PopupController::class, 'ReturnOrder']);
Route::post('/add_fund', [PopupController::class, 'AddFund']);
Route::post('/shipping_calculate', [PopupController::class, 'ShippingCalculate']);
Route::post('/free_shipping', [PopupController::class, 'FreeShippingPopUp']);
Route::post('/shipping_service', [PopupController::class, 'ShippingServicePopUp']);
Route::post('/wholesaler_shipping_policy', [PopupController::class, 'WholesalerShippingPolicyPopUp']);
Route::post('/signin_signup',[PopupController::class, 'SigninSignUpPopUp']);
Route::post('/wholesaler-terms',[PopupController::class, 'WholesalerTerms']);

Route::get('/{filters?}/pid/{products_id}/{category_id}', [CategoryController::class,'ProductDetails'])->where('filters', '(.*)')->name('proddetails');
Route::get('/{filters?}/pid/{products_id}/{category_id}/{resize}', [CategoryController::class,'ProductDetails'])->where('filters', '(.*)')->name('proddetails_size');
Route::get('/{filters?}/pid/{products_id}/{category_id}/{code}/{private}', [CategoryController::class,'ProductDetails'])->where('filters', '(.*)')->name('proddetails_code');

Route::post('/get_freegift_products',[ShoppingcartController::class,'GetFreeGiftProducts']);
/** Common Popup - Coupon Controller End **/

/** Shoppingcart **/
Route::post('/shoppingcart',[ShoppingcartController::class,'ShoppingcartPage'])->name('shoppingcart');
Route::get('/shoppingcart',[ShoppingcartController::class,'ShoppingcartPage'])->name('shoppingcart');
Route::post('/cart',[ShoppingcartController::class,'SetCart']);
Route::post('/getcart',[ShoppingcartController::class,'GetCartHTML']);
Route::post('/getshopcart',[ShoppingcartController::class,'GetCartPartial']);
Route::post('/billing/{method?}',[ShoppingcartController::class,'CheckoutPage'])->name('billing');
Route::get('/billing/{method?}',[ShoppingcartController::class,'CheckoutPage'])->name('billing');
Route::any('/shippinginfo',[ShoppingcartController::class,'ShippingMethods']);
Route::post('/paymentinfo',[ShoppingcartController::class,'PaymentMethods']);
Route::post('/setbilling',[ShoppingcartController::class,'SetBilling']);
Route::post('/checkmember',[ShoppingcartController::class,'CheckMember']);
Route::post('/setshipmethod',[ShoppingcartController::class,'SetShippingMethod']);
Route::post('/custaddfund',[ShoppingcartController::class,'CustomerAddFund']);
Route::post('/paypal_fund_response/{pagefrom}',[ShoppingcartController::class,'PaypalFundResponse']);
Route::get('/paypal_fund_response/{pagefrom}',[ShoppingcartController::class,'PaypalFundResponse']);
Route::post('/paypal_fund_process/{uid}',[ShoppingcartController::class,'PaypalFundProcess']);
Route::get('/paypal_fund_process/{uid}',[ShoppingcartController::class,'PaypalFundProcess']);
Route::get('/billing-amazon-checkout',[ShoppingcartController::class,'AmazonCheckout'])->name('AmazonBilling');
Route::get('/billing-amazon',[ShoppingcartController::class,'AmazonFundCheckout'])->name('AmazonBillingFund');
Route::get('/phoneorder-amazon',[GeneralController::class,'AmazonPhoneOrderCheckout'])->name('AmazonPhoneOrderCheckout');
Route::get('/check-template',[ShoppingcartController::class,'CheckMailTemplate']);
Route::post('/get-wholesale-price',[ProductController::class,'SetWholesalePrice']);
Route::get('/sitecart',[ShoppingcartController::class,'SetOmnisendCart']);
/** Shoppingcart **/

Route::get('/gift-guide', [LandingpageController::class,'GiftGuide']);
Route::get('/fragrance', [LandingpageController::class,'fragranceLandingPage']);

Route::post('/getgiftproducts', [LandingpageController::class,'GetGiftGuideProducts']);
/** Newsletter Starts **/
Route::post('/newsletter-subscribe', [NewsletterController::class,'NewsletterSubscribe']);
/** Newsletter Ends **/

/** Customer Reviews Starts **/
Route::get('/customer-reviews.html', [CustomerController::class,'CustomerReviews']);
/** Customer Reviews Ends **/

/* Main Category Page Starts*/
// Route::get('/{category_name}/cid/{category_id}', [MainCategoryController::class,'CategoryPage']);
Route::get('/{category_name}/cid/{category_id}/{filters?}', [MainCategoryController::class,'CategoryPage'])->where('filters', '(.*)')->name('CategoryPage4');
Route::post('/{category_name}/cid/{category_id}/{filters?}', [MainCategoryController::class,'CategoryPage'])->where('filters', '(.*)');
/* Main Category Page Ends*/

Route::get('/{category_name}/tcid/{category_id}/{filters?}', [GeneralController::class,'ProductPage'])->where('filters', '(.*)')->name('product-list6');

/** Ref product start  **/
Route::post('/getrefproduct',[CategoryController::class,'GetRefProduct']);
Route::post('/getquickviewrefproduct',[PopupController::class,'GetProductQuickViewRef']);
/** Ref product end  **/

/** STRIPE BUTTON **/
Route::post('/stripebtnres',[ShoppingcartController::class,'StripButtonResponse']);
Route::post('/getclientsecret',[ShoppingcartController::class,'GetClientSecret']);
Route::post('/getstripecart',[ShoppingcartController::class,'SetCartForStripe']);
Route::post('/getshippingmodes',[ShoppingcartController::class,'GetShippingOptionsJson']);
/** STRIPE BUTTON **/

Route::post('/stripe/placeorder',[StripeController::class,'SetStripe']);
Route::get('/stripe/placeorder',[StripeController::class,'SetStripe']);
Route::get('/stripe/addfund/{pagefrom}',[StripeController::class,'AddFund']);
Route::post('/placeorder',[ShoppingcartController::class,'PlaceOrder'])->name('placeorder');
Route::post('/order-receipt',[ShoppingcartController::class,'OrderReceipt'])->name('order-receipt');
Route::get('/order-receipt',[ShoppingcartController::class,'OrderReceipt'])->name('order-receipt');
Route::post('/paypal/placeorder',[PaypalController::class,'SetPaypal']);
Route::get('/paypal/placeorder/{dropsipflag?}',[PaypalController::class,'SetPaypal']);
Route::get('/paypal/success/{dropsipflag?}',[PaypalController::class,'Success']);
Route::get('/paypal/cancel',[PaypalController::class,'Cancel']);
Route::get('/paypal/dopayment/{dropsipflag?}',[PaypalController::class,'DoPayment']);
Route::get('/dropshipper-details',[ShoppingcartController::class,'GetDropshipperFundDetails']);
Route::post('/set-dropship-order',[CustomerController::class,'SetDropshiperOrder']);


Route::post('/paypal/phoneorder',[PaypalController::class,'PhoneOrder']);
Route::get('/paypal/phoneorder',[PaypalController::class,'PhoneOrder']);
Route::get('/paypal/success_phoneorder',[PaypalController::class,'Success_Phoneorder']);
Route::get('/paypal/cancel_phoneorder',[PaypalController::class,'Cancel_Phoneorder']);

Route::get('/amazon/login',[PaypalController::class,'DoPayment'])->name('AmazonpayLogin');

/* Afterpay Routes Start */
Route::post('/afterpay/placeorder',[AfterpayController::class,'SetAfterpay']);
Route::get('/afterpay/placeorder',[AfterpayController::class,'SetAfterpay']);
Route::get('/afterpay/success',[AfterpayController::class,'Success']);
Route::get('/afterpay/cancel',[AfterpayController::class,'Cancel']);
Route::get('/afterpay/dopayment/{order_id}',[AfterpayController::class,'DoPayment']);
Route::get('/afterpay/phoneorder', [AfterpayController::class,'PhoneOrder']);
Route::get('/afterpay/success_phoneorder',[AfterpayController::class,'Success_Phoneorder']);
Route::get('/afterpay/cancel_phoneorder',[AfterpayController::class,'Cancel_Phoneorder']);
Route::get('/afterpay/dopayment_phoneorder/{order_id}',[AfterpayController::class,'DoPayment_Phoneorder']);
/* Afterpay Routes End */

Route::get('/setupamazon/{page_from?}',[AmazonpayController::class,'SetupAmazon']);
Route::post('/amazon/order-details',[AmazonpayController::class,'GetOrderInfo']);
Route::post('/amazon/phoneorder-details',[AmazonpayController::class,'GetOrderInfo_Phoneorder']);
Route::get('amazon/placeorder',[AmazonpayController::class, 'AmazonPlaceOrder']);
Route::post('amazon-fund-process',[AmazonpayController::class, 'AmazonFundProcess']);
Route::post('amazon-phoneorder-process',[AmazonpayController::class, 'AmazonPhoneOrderProcess']);

/** Cache Clear Start **/
Route::post('/clearfrontcache',[FrontCacheController::class,'ClearFrontCache']);
/** Cache Clear End **/

Route::get('/redirect', [SocialLoginController::class,'redirect']);
Route::get('/callback', [SocialLoginController::class,'callback']);

Route::get('/redirectgoogle', [SocialLoginController::class,'redirectgoogle']);
Route::get('/callbackgoogle', [SocialLoginController::class,'callbackgoogle']);

Route::get('/fb/deletion/{id}', [FbUserDeleteController::class,'deletion']);
Route::get('/fb/deletion', [FbUserDeleteController::class,'deletion']);

Route::post('/dropship-shipping-method-ajax',[CustomerController::class,'DropshipShippingMethods']);
Route::post('/dropship-order-summary-ajax',[CustomerController::class,'DropshipOrderSummary']);
Route::delete('/remove-dropship-order-item',[CustomerController::class,'DropshipOrderItemRemove']);
Route::post('/ajax-list-skus',[CustomerController::class,'AjaxListSkus']);

Route::any('/additem',[SkuController::class,'additem']);
Route::any('/create_brand',[SkuController::class,'create_brand']);

