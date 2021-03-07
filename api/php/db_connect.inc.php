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
function db () {
    static $conn;
    if ($conn===NULL){
        $conn = mysqli_connect ("localhost", "root", "", "database");
    }
    return $conn;
}
?>
