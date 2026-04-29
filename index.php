<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
?>
<?php /*
<HTML><HEAD><TITLE>Coming Soon - maxaromasfour</TITLE></HEAD>
<BODY><CENTER><H1><FONT COLOR='NAVY'>Site under construction - maxaromasfour .</FONT></H1>
<BR/><H2><FONT COLOR='BLUE'>Coming Soon . . . .</FONT></H2><HR COLOR='RED'>
<?phpinfo()?>
</BODY></HTML>
*/?>