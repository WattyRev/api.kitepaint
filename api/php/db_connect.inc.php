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
function connectToDb() {
    static $conn;
    if ($conn===NULL){
        $conn = mysqli_connect(HOST, DBUSER, PASS, DB) or  die('Could not connect to mySQL!<br />Please contact the site\'s administrator.');
    }
    return $conn;
}
function mysqli_result($res,$row=0,$col=0){
    $numrows = mysqli_num_rows($res);
    if ($numrows && $row <= ($numrows-1) && $row >=0){
        mysqli_data_seek($res,$row);
        $resrow = (is_numeric($col)) ? mysqli_fetch_row($res) : mysqli_fetch_assoc($res);
        if (isset($resrow[$col])){
            return $resrow[$col];
        }
    }
    return false;
}
?>
