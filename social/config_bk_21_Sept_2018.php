<?php
/*
------------------------------------------------------
  www.idiotminds.com
--------------------------------------------------------
*/
session_start();
define('BASE_URL', filter_var('http://maxaroma.com/social/', FILTER_SANITIZE_URL));

define('CLIENT_ID','997385110909-p3ctk6k3kffsqfn8r39fqkn3rorun4fs.apps.googleusercontent.com');
define('CLIENT_SECRET','7Uv34yVYrJ5_5EjcMml7xvLk');


define('REDIRECT_URI','https://www.maxaroma.com/social/login.php?google');//example:http://localhost/social/login.php?google,http://example/login.php?google
define('APPROVAL_PROMPT','auto');
define('ACCESS_TYPE','offline');


?>
