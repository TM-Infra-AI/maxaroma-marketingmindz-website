<?php
set_time_limit(0);
ini_set('memory_limit',"500M");

//include_once("/home/peraroma/public_html/lib/config_setting.php");
include_once("../lib/configuration.php");

error_reporting(E_ALL);
ini_set('display_errors',1);

	$transaction_mode = "Sandbox";

	if($transaction_mode == "Live"){
		$curl_transurl = "https://api.supplieroasis.com";
	}else{
		$curl_transurl = "https://api.test.supplieroasis.com";
	}
	
	echo $curl_transurl;

	$start_date = date("Y-m-d");
	$end_date = $start_date;
	$header_arr = array(
						'Connection: keep-alive',
						'content-type: application/xml',
						'Authorization: Basic UEVSQVJPTUE6T3ZlcnN0b2NrMSE',
						'accept: application/xml',
						'Host: api.test.supplieroasis.com'
					   );		   
	
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL, $curl_transurl.'/inventory');
	
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	//curl_setopt($ch, CURLOPT_POST, 1);
	//curl_setopt($ch, CURLOPT_POSTFIELDS, $requestarr);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	//curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header_arr); 
	
	curl_setopt($ch, CURLOPT_TIMEOUT, 100);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
	
	//execute post
	$result = curl_exec($ch);
	curl_close($ch); // close cURL handler
	
	echo "Response:: <pre>";print_r($result); exit;
	$response1 		= simplexml_load_string($result);
	$json_string 	= json_encode($response1);
	$result_array	= json_decode($json_string, TRUE);
	
	$res_data = $result_array['processedSalesOrder'];
	$count_rec 	= count($res_data);
	
	
?>
