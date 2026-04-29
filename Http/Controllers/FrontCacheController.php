<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Blade;
use Cache;
use Illuminate\Support\Facades\Artisan;

class FrontCacheController extends Controller
{
	public function ClearFrontCache(Request $request)
	{
		$cacheArray = array('menu_array','brandlist','popular_brands','arr_currency','BottomHtmlText','HomeBanners','HomeCategoryBanners','HOMEMETAINFO','DefaultMetaInfo','DealBrands','settingvars_cache');
		if (in_array($request->cachevarialbe, $cacheArray)) {
			Cache::forget($request->cachevarialbe);
			$array = array(
				'message' => $request->cachevarialbe.'cache clear sucessfully',
			);

			return response()->json($array);
		}
	}
}
