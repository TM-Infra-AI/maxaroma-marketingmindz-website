<?php 
/* -----------------------------------------------------------------------------------------
   IdiotMinds - http://idiotminds.com
   -----------------------------------------------------------------------------------------
*/

require_once 'config.php'; ?>
<!doctype html>
<html>
<head><meta charset="utf-8">
    <title>Signin with Social Accounts-Idiot Minds</title>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js" type="text/javascript"></script>
<script type="text/javascript" src="js/oauthpopup.js"></script>
<script type="text/javascript">
$(document).ready(function(){
//For Google	
 $('a.login').oauthpopup({
		path: 'login.php?google',
		width:650,
		height:350,
	});
	$('a.google_logout').googlelogout({
		redirect_url:'<?php echo $base_url; ?>logout.php?google'
	});
//For Facebook	
  $('#facebook').oauthpopup({
		path: 'login.php?facebook',
		width:600,
		height:300,
   });
});
</script>

</head>
<body>
 <div style="float:left;width:33%;margin-left:30%;">

<?php
 if(!isset($_SESSION['User'])) {    ?>
 <div  style="margin-left:10%;float:left;width:300px;">
 
  	 <img src='images/facebook.png' id='facebook'  style='cursor:pointer;float:left;margin-right:10%;'  alt="Sign in with Facebook"/>
<a class='login' href='javascript:void(0);'  style="float:left;"><img alt='Signin in with Google' src='images/google.png' /></a>
</div>

 <?php  } else { 
    if(isset($_SESSION['facebook_logout'])){  
	    
	 ?>
      <img src="https://graph.facebook.com/<?php echo $_SESSION['User']['id']; ?>/picture" width="50"/><div><?php echo $_SESSION['User']['name'];?></div>
	  <a href="<?php echo $_SESSION['facebook_logout'];?>">Logout</a>
<?php }
    else{
  ?>
     <img src="<?php echo $_SESSION['User']['picture']; ?>" width="50" /><div><?php echo $_SESSION['User']['name'];?></div>
     <div><?php echo $_SESSION['User']['email']; ?></div>
       <a class='google_logout' href='javascript:void(0);'>Logout</a>
  <?php
	}
  } ?>
</div>

</body></html>