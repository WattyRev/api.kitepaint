<?php
// Database settings
// database hostname or IP. default:localhost
// localhost will be correct for 99% of times
define("HOST", "localhost");

// Database user
// Database password
define("DBUSER", "r3vfan_kitepaint");
define("PASS", "cJH,^ViVDm21");

// Database name
$isProduction = (strpos($_SERVER['HTTP_HOST'], 'beta') === false);
define("DB", $isProduction ? "r3vfan_kite_paint" : "r3vfan_kite_paint_beta");

############## Make the mysql connection ###########
$conn = mysqli_connect(HOST, DBUSER, PASS) or  die('Could not connect !<br />Please contact the site\'s administrator.');

$db = mysqli_select_db(DB) or  die('Could not connect to database !<br />Please contact the site\'s administrator.');

?>
