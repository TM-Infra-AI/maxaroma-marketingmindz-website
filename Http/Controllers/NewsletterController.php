<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NewsLetter;

class NewsletterController extends Controller
{
	public function NewsletterSubscribe(Request $request)
	{
		if($request->ajax()){
			config(['app.debug' => true]);
	        $validatedData = $request->validate([
								'news_firstname' => 'required',
								'news_lastname'	=> 'required',
					            'news_email1' => 'required|email',
					        ], [
					            'news_firstname.required' => config('message.Newsletter.FirstName'),
					            'news_lastname.required' => config('message.Newsletter.LastName'),
					            'news_email1.required' => config('message.Newsletter.Email'),
					            'news_email1.email' => config('message.Newsletter.ValidEmail'),
					        ]);
	        
			/*
			$EventData['identifiers']=[
				[
					'channels' => [
						'email' => ['status' => 'subscribed', 'statusDate' => str_replace('+00:00', '.000Z', gmdate('c', strtotime(date('Y-m-d H:i:s'))))]
					],
					'type' => 'email',
					'id' => trim($request['news_email1'])
				]
			];
			if(trim($request['news_contactno']) != '')
			{
				$EventData['identifiers'][]=[
					'channels' => [
						'sms' => ['status' => 'subscribed', 'statusDate' => str_replace('+00:00', '.000Z', gmdate('c', strtotime(date('Y-m-d H:i:s'))))]
					],
					'type' => 'phone',
					'id' => trim($request['news_contactno'])
				];
			}
			$EventData['firstName'] = trim($request['news_firstname']);
			$EventData['lastName'] = trim($request['news_lastname']);
			*/
			$check_email = NewsLetter::whereRaw('LCASE(`email`) = "' . strtolower(trim($request['news_email1'])) . '"')->count();
	        if($check_email > 0) {
		        $response['status'] = 200;
		        $response['success'] = false;
		        $response['message'] = str_replace("{email}",$request['news_email1'],config('message.Newsletter.EmailExist'));
				return response()->json($response, 200);
	        } else {
				$arrInsert = array(
									'first_name' => $request['news_firstname'],
									'last_name'  => $request['news_lastname'], 
									'email' 	 => $request['news_email1'], 
									'status'	 => '1'
								);
				NewsLetter::create($arrInsert);
		        $response['status'] = 200;
		        $response['success'] = true;
		        $response['message'] = str_replace("{email}",$request['news_email1'],config('message.Newsletter.Success'));
		        
				//OmanisendContactRequest('61d700ca6dbb5b001ba4f0fa',$EventData);
				/** OMANISEND **/
				OmanisendRequest('newletter_create_customer',$request);
				/** OMANISEND **/
				/** YOTPO **/
				YotpoRequest('customAction',['email' => $request['news_email1']],['action' => 'NewsletterSubscription']);
				/** YOTPO **/
				return response()->json($response, 200);
	    	}
		} else {
	        $response['status'] = 404;
	        $response['message'] = "Not found";
	        return response()->json($response, 400);
		}
	}

}
