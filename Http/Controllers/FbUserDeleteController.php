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
use Illuminate\Support\Facades\Log;

class FbUserDeleteController extends Controller
{

	public function deletion(Request $request)
    {
        Log::error('sagarar');
        $signed_request = $request->get('signed_request');

		Log::error($signed_request);

		return 'user deleted sucessfully';

        $signed_request = $request->get('signed_request');

		Log::error($signed_request);

        $data = $this->parse_signed_request($signed_request);
        $user_id = $data['user_id'];

        // here will delete the user base on the user_id from facebook

        $User = Customer::find($user_id);
		if($User){
			$UserDataArray = array(
				'status' => 0);
			$User->update($UserDataArray); 
		}
		
        // here will check if the user is deleted
        $isDeleted = Customer::find($user_id);

        if ($isDeleted ===null) {
            return response()->json([
                'url' => '', 
                'code' => '',
            ]);
        }

        return response()->json([
            'message' => 'operation not successful'
        ], 500);
    }

    private function parse_signed_request($signed_request) {
        list($encoded_sig, $payload) = explode('.', $signed_request, 2);

        $secret = env('FACEBOOK_APP_SECRET'); // Use your app secret here

        // decode the data
        $sig = $this->base64_url_decode($encoded_sig);
        $data = json_decode($this->base64_url_decode($payload), true);

        // confirm the signature
        $expected_sig = hash_hmac('sha256', $payload, $secret, $raw = true);
        if ($sig !== $expected_sig) {
            error_log('Bad Signed JSON signature!');
            return null;
        }

        return $data;
    }

    private function base64_url_decode($input) {
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
