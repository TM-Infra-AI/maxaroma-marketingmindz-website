<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use App\Models\SiteSettings;
use App\Models\Currency;
use App\Models\SiteOffers;
use App\Models\ManageNotification;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
//use Illuminate\Http\Request;
use Request;
use App\Http\Controllers\Traits\CartTrait;
use Cache;
use Session;
use Cookie; 

class GlobalServiceProvider extends ServiceProvider
{
	use CartTrait;
	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	public function register()
	{
		view()->composer('*', function ($view) {
			$CurrentRoute = \Route::getCurrentRoute();
			if (isset($CurrentRoute->action['as']))
				View::share('CurrentRoute', $CurrentRoute->action['as']);
			else
				View::share('CurrentRoute', '');
		});
	}

	/**
	 * Bootstrap any application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		$CJParams = array_change_key_case(Request::all(),CASE_LOWER);
		if(config('global.SITE_MODE') == 'Live' && isset($CJParams['cjevent']))
		{
			if($CJParams['cjevent'] != '')
			{
				$event_id = $CJParams['cjevent'];
				$cookie_name = "cje";
				$domain = "maxaroma.com";
				setcookie($cookie_name, $event_id, time() + (86400 * 395), "/", $domain, true, false);
				//Cookie::queue(Cookie::make($cookie_name,$event_id,time() + (86400 * 395), "/", $domain, true, false));
			} else {
				$REQUEST_URI = Request::fullUrl();
				$req_arr = explode("?",$REQUEST_URI);
				if(!empty($req_arr) && count($req_arr) > 1){
					$cj_data = $req_arr[1];
					if($cj_data != ""){
						parse_str($cj_data);
						
						if($CJParams['cjevent'] != '')
						{
							$event_id = $CJParams['cjevent'];
							$cookie_name = "cje";
							$domain = "maxaroma.com";
							setcookie($cookie_name, $event_id, time() + (86400 * 395), "/", $domain, true, false);
							//Cookie::queue(Cookie::make($cookie_name,$event_id,time() + (86400 * 395), "/", $domain, true, false));
						}
					}
				}
			}			
		}
        if(Request::has('omnisendContactID') && trim(Request::get('omnisendContactID')) != "")
        {
            if(Cookie::has('omnisendContactID'))
            {
                Cookie::forget('omnisendContactID');
            }
            $domain = "maxaroma.com";
            setcookie('omnisendContactID', Request::get('omnisendContactID'), time() + (86400 * 395), "/", $domain, true, false);
        }
		//Session::forget('ShoppingCart');
		//Set Category Navigation, Store in cache, Share to all views
		//Cache::forget('Menu');
		/*if (!Cache::has('Menu')) {
			GetCategoryMenu();
		}
		// dd(Cache::get('Menu'));
		View::share('CatMenu', Cache::get('Menu'));*/

		//Cache::forget('AllCategoriesInfo');
		/*if(!Cache::has('AllCategoriesInfo')){
			SetCatTree();
		}*/
		/*$allowed_IPs = ['103.85.90.14'];
		if(in_array($_SERVER['HTTP_CF_CONNECTING_IP'], $allowed_IPs)) {
			config(['app.debug' => true]);
			\Debugbar::enable();
		}*/
		$CatTreeInfo = SetCatTree();
		config(['CATEGORY_INFO' => $CatTreeInfo]);

		//dd(Cache::get('AllCategoriesInfo'));
		//Cache::forget('BottomHtmlText');
		if (!Cache::has('BottomHtmlText')) {
			GetBottomHtml();
		}
		View::share('BottomHtmlText', Cache::get('BottomHtmlText'));
        
        if(!Cache::has('DefaultMetaInfo'))
        {
            GetMetaInfo();
        }
        View::share('DefaultMetaInfo', Cache::get('DefaultMetaInfo'));
        
		//Set Dynamic Constants of site_settings table	
		if (Cache::has('settingvars_cache')) {
			$SiteSetting = Cache::get('settingvars_cache');
		} else {
			$Settings = SiteSettings::selectRaw('var_name,setting')->where('status', '=', '1')->get();
			$SiteSetting = array();
			foreach ($Settings as $Setting) {
				$SiteSetting[$Setting->var_name] = $Setting->setting;
			}
			Cache::put('settingvars_cache', $SiteSetting);
		}
		config(['Settings' => $SiteSetting]);
		config(["global.META_TITLE" => $SiteSetting['SITE_TITLE']]);
		config(["global.META_KEYWORDS" => $SiteSetting['SITE_TITLE']]);
		config(["global.META_DESCRIPTION" => $SiteSetting['SITE_TITLE']]);

		if (!Session::has('currency_code') || Session::get('currency_code') == '') {
			$Currency = Currency::where('currency_code', '=', 'USD')->where('status', '=', '1')->get();
			if ($Currency->count() > 0) {
				Session::put('currency_code', $Currency[0]->currency_code);
				Session::put('currency_symbol', $Currency[0]->currency_symbol);
				Session::put('currency_rate', $Currency[0]->exchange_rate);
			} else {
				Session::put('currency_code', 'USD');
				Session::put('currency_symbol', '$');
				Session::put('currency_rate', 1);
			}
		}

		//Cache::forget('arr_currency');
		if (Cache::has('arr_currency')) {
			$SiteCurrencies = Cache::get('arr_currency');
		} else {
			$AllCurrencies = Currency::selectRaw('currency_id, currency_code,currency_symbol,exchange_rate')->where('status', '=', '1')->orderBy('currency_id')->get();
			$SiteCurrencies = [];
			foreach ($AllCurrencies as $key => $Currency) {
				$SiteCurrencies[] = [
					'currency_id' => $Currency->currency_id,
					'currency_code' => $Currency->currency_code,
					'currency_symbol' => $Currency->currency_symbol,
					'exchange_rate' => $Currency->exchange_rate
				];
			}
			Cache::put('arr_currency', collect($SiteCurrencies));
		}
		config(['Currencies' => $SiteCurrencies]);

		$DealData = GetDealOfWeek();
		config(['DealDetails' => $DealData]);

		############ Site Offeres check start ####################
		$cntTopStickyOffer = SiteOffers::select('expiry_date')->where('status', '=', '1')->orderBy('expiry_date', 'desc')->first();

		$expiry_date = date('Y-m-d', strtotime($cntTopStickyOffer->expiry_date));

		if ($expiry_date < date("Y-m-d")) {
			$cntOffer = 0;
		} else {
			$cntOffer = 1;
		}
		config(["global.cntOffer" => $cntOffer]);
		############# Site Offers check End #####################

		############ Notification IN header TOP Start ####################
		config(['show_notification' => 'No']);
		$managenotification = ManageNotification::select('*')->where('start_date', '<=', date('Y-m-d'))->where('end_date', '>=', date('Y-m-d'))->get();
		if($managenotification->count() <= 0){
			$managenotification = ManageNotification::select('upcoming_notification_text as notification_text','upcoming_color_picker as color_picker','show_notification','upcoming_start_date as start_date','upcoming_end_date as end_date')->where('upcoming_start_date', '<=', date('Y-m-d'))->where('upcoming_end_date', '>=', date('Y-m-d'))->get();
		}

		if($managenotification->count() > 0 && $managenotification[0]['show_notification'] == 'Show'){
			config(['show_notification' => 'Show']);
			config(['color_picker' =>  $managenotification[0]['color_picker']]);
			$managenotificationtext = str_replace('\n', " ", $managenotification[0]['notification_text']);
			$managenotificationtext = str_replace('\r', " ", $managenotificationtext);
			config(['notification_text' =>  str_replace("  ", " ", trim($managenotificationtext))]);
		}
	
		############# Notification IN header TOP End #####################


		Session::put('Max2Day', 'Yes');
		//Code for deal value set in session :: START, used in product / shiopping cart
		if ((Session::has('LAST_ACTIVITY') && (time() - Session::get('LAST_ACTIVITY') > 1500)) || (Session::has('LAST_ACTIVITY_DATE') && (date('Y-m-d') != date(Session::get('LAST_ACTIVITY_DATE'))))) //300 :: 5min
		{
			Session::forget('dealcheck_array');
			Session::forget('dealcompare_array');
			Session::forget('LAST_ACTIVITY');
			Session::forget('LAST_ACTIVITY_DATE');
		}
		if (!Session::has('dealcheck_array') && empty(Session::get('dealcheck_array')) && !Session::has('dealcompare_array') && empty(Session::get('dealcompare_array')) && !Session::has('LAST_ACTIVITY') && Session::get('LAST_ACTIVITY') == '') {
			$result_array = GetDealCheckProduct();

			if (!empty($result_array['dealcheck_array']) && !empty($result_array['dealcompare_array'])) {
				Session::put('dealcheck_array', $result_array['dealcheck_array']);
				Session::put('dealcompare_array', $result_array['dealcompare_array']);
				Session::put('LAST_ACTIVITY', time()); // update last activity time stamp;
				Session::put('LAST_ACTIVITY_DATE', date('Y-m-d')); // update last activity date;
			}
		}
		//Code for deal value set in session :: END

		//Code for deal of day value set in session :: START
		if ((Session::has('DAYLAST_ACTIVITY') && (time() - Session::get('DAYLAST_ACTIVITY') > 300)) || (Session::has('DAYLAST_ACTIVITY_DATE') && (date('Y-m-d') != date(Session::get('DAYLAST_ACTIVITY_DATE'))))) //300 :: 5min
		{
			Session::forget('daydealcheck_array');
			Session::forget('daydealcompare_array');
			Session::forget('DAYLAST_ACTIVITY');
			Session::forget('DAYLAST_ACTIVITY_DATE');
		}
		if (!Session::has('daydealcheck_array') && empty(Session::get('daydealcheck_array')) && !Session::has('daydealcompare_array') && empty(Session::get('daydealcompare_array')) && !Session::has('DAYLAST_ACTIVITY') && Session::get('DAYLAST_ACTIVITY') == '') {

			$result_array = GetDayDealCheckProduct();
			if (!empty($result_array['ddealcheck_array']) && !empty($result_array['ddealcompare_array'])) {
				if (!Session::has('aroma_popup_flg') || Session::get('aroma_popup_flg') == '') {
					$aroma_popup_flg = 1;
					Session::put('aroma_popup_flg', 1);
				}
				Session::put('daydealcheck_array', $result_array['ddealcheck_array']);
				Session::put('daydealcompare_array', $result_array['ddealcompare_array']);
				Session::put('DAYLAST_ACTIVITY', time()); // update last activity time stamp;
				Session::put('DAYLAST_ACTIVITY_DATE', date('Y-m-d')); // update last activity date;
			}
		}
		// echo $_SERVER['REMOTE_ADDR'];
		//Code for deal of day value set in session :: END

			if (!Cache::has('brandlist')) {
				$brandlist = BrandsList();
				Cache::put('brandlist', $brandlist);
			} else {
				$brandlist = Cache::get('brandlist');
			}

			View::share('brandlist', Cache::get('brandlist'));

			/*view()->composer('layouts/menu', function ($view) 
	        {
				$menu_array = GetFrontMegaMenu();
				$view->with('menu_array', $menu_array );
			}); */

			/*view()->composer('layouts/menu', function ($view) 
	        {
				$brandlist = BrandsList();
				$view->with('brandlist', $brandlist );
			});*/ 

			/*view()->composer('layouts/menu', function ($view) 
	        {
				$popular_brands = getPopularBrands();
				// dd($popular_brands);
				$view->with('popular_brands', $popular_brands );
			}); */
			//Cache::forget('menu_array');	
			if (!Cache::has('menu_array')) {
				GetFrontMegaMenu();
			}
			View::share('menu_array', Cache::get('menu_array'));

			if (!Cache::has('popular_brands')) {
				getPopularBrands();
			}
			View::share('popular_brands', Cache::get('popular_brands'));
			// $pb = getPopularBrands();
			// View::share('popular_brands', getPopularBrands());
			
			$isMobile = isMobile();
			config(['typevalofcriteo' => "d"]);
			if($isMobile==1)
				config(['typevalofcriteo' => "m"]);
			
			$this->SetAmazonConfig();
	}
}
