<?
set_time_limit(0);
ini_set('memory_limit',"1024M");
include_once("/home/maxaroma/public_html/lib/config_setting.php");
require_once 'vendor/autoload.php';
use Wish\UsedToken;
use Wish\WishClient;
use Wish\Exception\ServiceResponseException;



$TokenObj = new UsedToken();
$Token  = $TokenObj->getTokens();
$client = new WishClient($Token,'prod');
/*$ch = curl_init('https://merchant.wish.com/api/v2/variant/get-bulk-update-job-successes?job_id=5c02137b3da7a622370c70fb&access_token='.$Token.'&limit=100'); 
curl_setopt($ch, CURLOPT_POST,0);
curl_setopt($ch, CURLOPT_HEADER, $header);
       
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$output = curl_exec($ch);
echo "<pre>"; print_r($output); exit;
*/

$ch = curl_init('https://merchant.wish.com/api/v2/variant/get-bulk-update-job-status?job_id=5c050338b5d3c879cd5ec3ec&access_token='.$Token); 
curl_setopt($ch, CURLOPT_POST,0);
curl_setopt($ch, CURLOPT_HEADER, $header);
       
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$output = curl_exec($ch);
echo "<pre>"; print_r($output); exit;

?>
