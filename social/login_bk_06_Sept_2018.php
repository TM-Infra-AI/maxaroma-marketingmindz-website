<?php 
/* -----------------------------------------------------------------------------------------
   IdiotMinds - http://idiotminds.com
   -----------------------------------------------------------------------------------------
*/
require 'Social.php';
$Social_obj= new Social();

if(isset($_GET['google'])){
	//echo 1; exit;
	$Social_obj->google();
header("Location: https://www.maxaroma.com/social/social.php");
}

?>
<!-- after authentication close the popup -->
<script type="text/javascript">
window.close();
</script>
