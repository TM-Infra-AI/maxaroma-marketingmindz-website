<?php

require_once 'config.php';
require_once 'lib/facebook/facebook.php';

require_once 'lib/google/Google_Client.php';
require_once 'lib/google/Google_Oauth2Service.php';
include("../lib/configuration.php");
//include_once("../classes/general.cls.php");
//include_once("../classes/product.cls.php");
//$generalobj	= new General($obj);
//$productobj	= new Product($obj,$generalobj);

if(isset($_POST['billing']) and !empty($_POST['billing']))
{
  $url = $Site_URL . "index.php?file=billing";
} else {
  $url = $Site_URL."myaccount.html";
}

$client = new Google_Client();
$client->setApplicationName("Idiot Minds Google Login Functionallity");
$client->setClientId(CLIENT_ID);
$client->setClientSecret(CLIENT_SECRET);
$client->setRedirectUri(REDIRECT_URI);
$client->setApprovalPrompt(APPROVAL_PROMPT);
$client->setAccessType(ACCESS_TYPE);
$oauth2 = new Google_Oauth2Service($client);
if (isset($_GET['code'])) {
  $client->authenticate($_GET['code']);
  $_SESSION['token'] = $client->getAccessToken();
}
if (isset($_SESSION['token'])) {
 $client->setAccessToken($_SESSION['token']);
}
if (isset($_REQUEST['error'])) {
 echo '<script type="text/javascript">window.close();</script>'; exit;
}
if ($client->getAccessToken()) {
  $user_profile = $oauth2->userinfo->get();
	//echo "<pre>"; 
	//print_r($user_profile);exit;
  //$_SESSION['User']=$user;
  //$_SESSION['token'] = $client->getAccessToken();

$email = $user_profile['email'];
$vfirst_name = $user_profile['given_name'];
$vlast_name = $user_profile['family_name'];
$social_url = $user_profile['link'];
$social_id = $user_profile['id'];

$gender = ucwords(strtolower($user_profile['gender']));
$eusertype 			= 'eusertype';
$is_dropshipper = "No";
$etype 				= "M";


$db_sql = "SELECT customer_id,first_name,email,eusertype,is_dropshipper FROM `".TABLE_PREFIX."customer` WHERE email = '".$email."' AND status='1' AND registration_type = 'M'";		

$result = $obj->select($db_sql);			
if($result && $result[0]["email"]!="" && $result[0]["customer_id"]>0)
{	


	$_SESSION['sess_useremail'] 	= $result[0]["email"];
	$_SESSION['sess_username'] 		= $result[0]["first_name"];
	$_SESSION['sess_icustomerid'] 	= $result[0]["customer_id"];
	$_SESSION['eusertype'] 			= $result[0]["eusertype"];
	$_SESSION['is_dropshipper'] 	= $result[0]["is_dropshipper"];
	$_SESSION['etype'] 				= $etype;
	$_SESSION['google_id']	 		= $social_id;

	//$Cart->GenerateShopCartFromCookieAfterLogin();
	//$Cart->StoreShopCartInCookie();

	if(isset($_SESSION['redirecttofile']) && $_SESSION['redirecttofile']!='')
	{
		$redirecttofile = $_SESSION['redirecttofile'];
		unset($_SESSION['redirecttofile']);
		header("Location: ".$redirecttofile);
		exit;
	}
	else
	{
		header("Location:".$url);
		exit;
	}
}
else if($email!="" && $social_id!="")
{  
	
	
	$aData['email'] 			= $email;				
	$aData['first_name'] 		= $vfirst_name;
	$aData['last_name'] 		= $vlast_name;
	$aData['is_google'] 		= "Yes";
	$aData['google_url'] 		= $social_url;
	$aData['google_id'] 		= $social_id;
	$aData['registration_type'] = $etype; // member or guest customer
	$aData['reg_datetime'] 		= date('Y-m-d H:i:s');
	$aData['status'] 			= 1;
	$aData['eusertype'] 		= "Retailer";
	$aData['is_dropshipper'] 	= $is_dropshipper;

	
	$iCustomerId = $obj->insert(TABLE_PREFIX.'customer', $aData);
	
	if (isset($iCustomerId) && $iCustomerId>0)
	{
		$_SESSION['sess_useremail'] 	= $email;
		$_SESSION['sess_username'] 		= $vfirst_name;
		$_SESSION['sess_icustomerid'] 	= $iCustomerId;
		$_SESSION['eusertype'] 			= "Retailer";
		$_SESSION['is_dropshipper'] 	= $is_dropshipper;
		$_SESSION['etype'] 				= $etype;
		$_SESSION['google_id'] 			= $social_id;
		//$Cart->GenerateShopCartFromCookieAfterLogin();
	   // $Cart->StoreShopCartInCookie();
		if(isset($_SESSION['redirecttofile']) && $_SESSION['redirecttofile']!='')
		{
			//echo $redirecttofile . "<br>"; exit; 
			$redirecttofile=$_SESSION['redirecttofile'];
			unset($_SESSION['redirecttofile']);
			header("Location: ".$redirecttofile);
			exit;
		}
		else
		{
			//echo 1 . "<br>"; exit;
			header("Location:".$url);
			exit;
		}
	}
	
 }




} else {
  $authUrl = $client->createAuthUrl();
  header('Location: '.$authUrl);

}
     
  
  
  






?>
