<?php
 
require_once("mail.functions.inc.php");
require_once("user.functions.inc.php");
require_once("display.functions.inc.php");
require_once("login.functions.inc.php");
require_once("validation.functions.inc.php");
require_once("query.functions.inc.php");

set_exception_handler('handle_uncaught_exception');
 
function generate_code($length = 10) {
 
    if ($length <= 0) {
        return false;
    }
 
    $code = "";
    $chars = "abcdefghijklmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ123456789";
    for ($i = 0; $i < $length; $i++) {
        $code = $code . $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
 
}
 
?>