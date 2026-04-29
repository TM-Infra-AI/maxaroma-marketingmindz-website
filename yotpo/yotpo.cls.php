<?php

class YotpoException extends Exception { }

class Yotpo { 
    //LIVE
    
    protected $guid= 'uIl5V6C_LVeCr5BT4bhDLQ';
	protected $api_key= 'uP0DefYrL1Dc77lwxlIq2gtt';
    
    //Sandbox
    /*
    protected $guid= 'ovP-FrBwbhaFtx8vQlYT6g';
	protected $api_key= 'kfoVMOxbSTz6LfciYXZv0Att';
    */
    public $host_https = 'https://loyalty.yotpo.com/api/v2/';
    
    function make_get_request($path) {
        $ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->host_https.$path);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');


		$headers = array();
		$headers[] = 'x-guid: '.$this->guid;
		$headers[] = 'x-api-key: '.$this->api_key;
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			echo 'Error:' . curl_error($ch);
		}
		curl_close($ch);
		
		$result = json_decode($result); 
        return $result;
    }
    
    function make_post_request($path, $params) 
    {
        $url = $this->host_https.$path ;
      
		$data =$params;                                                                    
		$data_string = json_encode($data);    

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); 
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);

		$headers = array();
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'x-guid: '.$this->guid;
		$headers[] = 'x-api-key: '.$this->api_key;
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			echo 'Error:' . curl_error($ch);
		}
		curl_close($ch);
		$result = json_decode($result); 
		return $result;
    }
	
	function yotpo_make_post_request($path, $params) 
    {
        $url = 'https://api.yotpo.com/'.$path ;
      
		$data =$params;                                                                    
		$data_string = json_encode($data);    

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); 
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);

		$headers = array();
		$headers[] = 'Content-Type: application/json';
		//$headers[] = 'x-guid: '.$this->guid;
		//$headers[] = 'x-api-key: '.$this->api_key;
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			echo 'Error:' . curl_error($ch);
		}
		curl_close($ch);
		$result = json_decode($result); 
		return $result;
    }
    
    function make_delete_request($path, $params) 
    {
        $url = $this->host_https.$path ;
      
        $data =$params;                                                                    
        $data_string = json_encode($data);    
        
        $ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE"); 
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);

		$headers = array();
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'x-guid: '.$this->guid;
		$headers[] = 'x-api-key: '.$this->api_key;
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$result = curl_exec($ch);
		if (curl_errno($ch)) {
			echo 'Error:' . curl_error($ch);
		}
		curl_close($ch);
		
		//echo "<pre>";print_r($result);exit;
        $result = json_decode($result); 
		return $result;
    }
   
};

?>
