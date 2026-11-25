<?php
// Database settings are loaded from a server-only file (see db_config.functions.inc.php).
// No credentials are kept in this repo.
require_once __DIR__ . '/db_config.functions.inc.php';

// Selects prod or beta by the request host. Unchanged from before; see SECURITY_FOLLOWUPS.md for the follow-up.
$isProduction = (strpos($_SERVER['HTTP_HOST'], 'beta') === false);

// If the config is missing or incomplete, log the reason and leave $dbConfig null.
// connectToDb() then refuses to connect. There is no fallback.
try {
    $dbConfig = kp_load_db_config(KP_DB_CONFIG_PATH, $isProduction);
} catch (Throwable $e) {
    error_log('KitePaint DB config error: ' . $e->getMessage());
    $dbConfig = null;
}

// Database hostname or IP, user, password, and database name for the selected environment
define("HOST", $dbConfig['host'] ?? '');
define("DBUSER", $dbConfig['user'] ?? '');
define("PASS", $dbConfig['pass'] ?? '');
define("DB", $dbConfig['db'] ?? '');

############## Make the mysql connection ###########
function connectToDb() {
    static $conn;
    if ($conn===NULL){
        if (HOST === '' || DBUSER === '' || DB === '') {
            die('Could not connect to mySQL!<br />Please contact the site\'s administrator.');
        }
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
