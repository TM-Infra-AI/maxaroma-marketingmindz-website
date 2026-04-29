<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use App\Models\RewardPoint;
use Illuminate\Support\Facades\File;
use Exception;
use Hash;
use Session;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Traits\CartTrait;

class SocialLoginController extends Controller
{
	use CartTrait;

	const facebookScope = [
		'user_birthday',
		'user_location',
	];
	/**
	 * Initialize Facebook fields to override
	 */
	const facebookFields = [
		'location', // I've given permission
		'id',
		'first_name',
		'last_name',
		'email',
		'link',
		'gender',
		'locale',
		'picture',
		'birthday',
		'name',
		'third_party_id',
		'relationship_status'
	];

	/**
	 * Create a redirect method to facebook api.
	 *
	 * @return void
	 */
	public function redirect()
	{
		// return Socialite::driver('facebook')->asPopup()->redirect();	
		return Socialite::driver('facebook')->fields(self::facebookFields)->scopes(self::facebookScope)->redirect();
	}
	/**
	 * Return a callback method from facebook api.
	 *
	 * @return callback URL from facebook
	 */
	public function callback()
	{
		try {
			// $user = Socialite::driver('facebook')->user();
			$user = Socialite::driver('facebook')->fields(self::facebookFields)->user();
			return $this->loginSocial($user,'facebook');
		} catch (Exception $e) {
			dd($e->getMessage());
		}
	}


	/**
	 * Create a redirect method to facebook api.
	 *
	 * @return void
	 */
	public function redirectgoogle()
	{
		return Socialite::driver('google')->redirect();
	}
	/**
	 * Return a callback method from facebook api.
	 *
	 * @return callback URL from facebook
	 */
	public function callbackgoogle()
	{
		try {
			$userdata = Socialite::driver('google')->user();
			return $this->loginSocial($userdata,'google');

		} catch (Exception $e) {
			dd($e->getMessage());
		}
	}

	public function loginSocial($userdata,$social)
	{

		$url = config('global.SITE_URL') ."myaccount.html";

		if (!Session::has('sess_icustomerid') && empty(Session::get('sess_icustomerid'))) {

			$email = $userdata->email;

			if($social == 'google'){
				$vfirst_name = substr($email, 0, 5);
				$vlast_name = substr($email, 0, 5);
			}else{
				$vfirst_name = $userdata->user['first_name'];
				$vlast_name = $userdata->user['last_name'];
			}	

			$social_id = $userdata->id;
			$eusertype 			= 'eusertype';
			$is_dropshipper = "No";
			$etype 				= "M";

			$registration_type = "Member";
			$result = Customer::select('customer_id', 'status', 'first_name', 'email', 'eusertype', 'is_dropshipper')
				->where('email', '=', $email)->where('registration_type', '=', 'M')->get();

			if ($result && $result->count() <= 0) {
				$result = Customer::select('customer_id', 'status', 'first_name', 'email', 'eusertype', 'is_dropshipper')
					->where('email', '=', $email)->where('registration_type', '=', 'G')->where('is_deleted', '=', 'No')->get();
				$registration_type = "Guest";
			}

			if ($result && $result->count() > 0) {
				//convert guest to member
				if ($registration_type == "Guest") {
					if ($email != '') {
						//$aData['email'] 			= $email;				
						$aData['first_name'] 		= $vfirst_name;
						$aData['last_name'] 		= $vlast_name;
						if($social == 'google'){
							$aData['is_google']     = "Yes";
							$aData['google_id'] 	= $social_id;
						}else{
							$aData['is_fb'] 		= "Yes";
							$aData['fb_id'] 		= $social_id;
						}
						
						// $aData['fb_social_url'] 	= $social_url;
						$aData['registration_type'] = $etype; // member or guest customer
						$aData['upd_datetime'] 		= date('Y-m-d H:i:s');
						$aData['status'] 			= 1;
						$aData['eusertype'] 		= "Retailer";
						$aData['is_dropshipper'] 	= $is_dropshipper;
						$aData['iRewardpoint'] 		= '150';

						$aData['merge_log'] = "Auto updated guest to member from fb login";
						$iCustomerId = $result[0]["customer_id"];

						$User = Customer::create($aData);
						if ($User) {
							$iCustomerId = $User->customer_id;

							$RewardPoints = array();
							$RewardPoints["customer_id"] = $iCustomerId;
							$RewardPoints["note"] = "Reward Point Added By Register";
							$RewardPoints["iRewardpoint"] = 150;
							RewardPoint::create($RewardPoints);

							$remember_me = false;
							if ($User->eusertype == "Wholesaler Pending") {
								$User->eusertype = "Retailer";
							}
						}
					}
				}

				//convert guest to member
				if ($result[0]['status'] == "0") {	//mostly member
					$CustomerArr = array(
						'upd_datetime' 		=> date('Y-m-d H:i:s'),
						'merge_log' 		=> "Auto updated to Active from $social login page",
						'status' 			=> '1'
					);
					$iCustomerId = $result[0]["customer_id"];
					$User = Customer::find($iCustomerId);
					$User->update($CustomerArr);
				}

				$iCustomerId = $result[0]["customer_id"];
				$User = Customer::find($iCustomerId);
				$remember_me = false;

				Auth::login($User, $remember_me);

				Session::put('sess_useremail', $result[0]["email"]);
				Session::put('sess_username', 	$result[0]["first_name"]);
				Session::put('sess_icustomerid', $result[0]["customer_id"]);
				Session::put('eusertype', $result[0]["eusertype"]);
				Session::put('is_dropshipper', $result[0]["is_dropshipper"]);
				Session::put('etype', $etype);
				Session::put('eusertype', $result[0]["eusertype"]);

				if($social == 'google'){
					Session::put('google_id', $social_id);
				}else{
					Session::put('facebook_id', $social_id);
				}	

				$this->GenerateShopCartFromCookieAfterLogin();
				$this->StoreShopCartInCookie();

				if (Session::has('redirecttofile')  && Session::get('redirecttofile') != '') {
					$redirecttofile = Session::get('redirecttofile');
					Session::forget('redirecttofile');
					return redirect($redirecttofile);
				} else {
					return redirect($url);
				}
			} else if ($email != "" && $social_id != "") {
				
				$UserData = array(
					'first_name' => $vfirst_name,
					'last_name' => $vlast_name,
					'email' => $email,
					'status' => '1',
					'eusertype' => 'Retailer',
					'customer_ip' => $_SERVER['REMOTE_ADDR'],
					'customer_browser' => $_SERVER['HTTP_USER_AGENT'],
					'is_dropshipper' => $is_dropshipper,
					'iRewardpoint' => '150',
					'reg_datetime' => date('Y-m-d H:i:s'),
					'registration_type' => $etype // member or guest customer
				);

				if($social == 'google'){
					$UserData['is_google'] = "Yes";
					$UserData['google_id'] = $social_id;
				}else{
					$UserData['is_fb'] = "Yes";
					$UserData['fb_id'] = $social_id;
				}

				$User = Customer::create($UserData);
				if ($User) {
					$iCustomerId = $User->customer_id;

					$RewardPoints = array();
					$RewardPoints["customer_id"] = $iCustomerId;
					$RewardPoints["note"] = "Reward Point Added By Register";
					$RewardPoints["iRewardpoint"] = 150;
					RewardPoint::create($RewardPoints);

					$remember_me = false;
					if ($User->eusertype == "Wholesaler Pending") {
						$User->eusertype = "Retailer";
					}
					Auth::login($User, $remember_me);

					Session::put('sess_useremail', $email);
					Session::put('sess_username', $vfirst_name);
					Session::put('sess_icustomerid', $iCustomerId);
					Session::put('eusertype', "Retailer");
					Session::put('is_dropshipper', $is_dropshipper);
					Session::put('etype', $etype);
					Session::put('eusertype', 'Retailer');

					if($social == 'google'){
						Session::put('google_id', $social_id);
					}else{
						Session::put('facebook_id', $social_id);
					}	

					$this->GenerateShopCartFromCookieAfterLogin();
					$this->StoreShopCartInCookie();

					if (Session::has('redirecttofile')  && Session::get('redirecttofile') != '') {
						$redirecttofile = Session::get('redirecttofile');
						Session::forget('redirecttofile');
						return redirect($redirecttofile);
					} else {
						return redirect($url);
					}
				}
			}
		} else {
			redirect(config('global.SITE_URL').'myaccount.html');
		}
	}
}
