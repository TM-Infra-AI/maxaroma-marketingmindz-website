<?php
//header('Access-Control-Allow-Origin: *');
//Access-Control-Allow-Origin: *
///header('Access-Control-Allow-Methods:  POST, GET, OPTIONS, PUT, DELETE');
///header('Access-Control-Allow-Headers:  Content-Type, X-Auth-Token, Origin, Authorization');

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\SkuController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/*Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});*/ 
Route::get('token',[HomeController::class,'Token']);
Route::get('homebanner', [HomeController::class,'homebanner']);
Route::post('contacts',[ApiController::class,'GetContacts']);
Route::post('coupons',[ApiController::class,'CreateCoupon']);
Route::post('cancel-coupon',[ApiController::class,'CancelCoupon']);
Route::get('yotpo-coupon',[ApiController::class,'YotpoCouponWebhook']);
Route::any('getavailablequantity',[ApiController::class,'getavailablequantity']);
//Route::get('coupons',[ApiController::class,'CouponList']);
Route::any('/additem',[SkuController::class,'additem']);
Route::any('/create_brand',[SkuController::class,'additem']);
Route::any('/create_product',[SkuController::class,'create_product']);
Route::any('/gettoken',[SkuController::class,'gettoken']);