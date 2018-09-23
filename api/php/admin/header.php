<?php
//error_reporting(0); // we don't want to see errors on screen
error_reporting(E_ALL);
ini_set('display_errors', 'on');
// Start a session
$headers = apache_request_headers();
if (isset($headers['Kp-Auth-Token'])) {
    session_id($headers['Kp-Auth-Token']);
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > (5 * 60 * 60))) {
    // last request was more than 5 hours
    session_unset();     // unset $_SESSION variable for the run-time
    session_destroy();   // destroy session data in storage
}
$_SESSION['LAST_ACTIVITY'] = time();

require_once ('../db_connect.inc.php'); // include the database connection
require_once ("../functions.inc.php"); // include all the functions
$seed = "0dAfghRqSTgx"; // the seed for the passwords
$domain =  "kitepaint.com"; // the domain name without http://www.

// Allow other domains access
$allowedOrigins = array(
  '(http(s)://)?(admin\.|admin\.beta\.)?kitepaint.com'
);
$isProduction = ($_SERVER['HTTP_HOST'] === 'admin.kitepaint.com');
if (!$isProduction) {
    // Allow localhost to access beta API
    array_push($allowedOrigins, '.*localhost.*');
}
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] != '') {
  foreach ($allowedOrigins as $allowedOrigin) {
    if (preg_match('#' . $allowedOrigin . '#', $_SERVER['HTTP_ORIGIN'])) {
      header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
      header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
      header('Access-Control-Max-Age: 1000');

       // respond to preflights
      if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
         header('Access-Control-Allow-Origin: *');
         header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Kp-Auth-Token');
         exit;
      }

      header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Kp-Auth-Token');
      break;
    }
  }
}

if (!isset($_SERVER['PHP_AUTH_USER']) && !isset($_SESSION['authGranted'])) {
    header('WWW-Authenticate: Basic realm="My Realm"');
    header('HTTP/1.0 401 Unauthorized');
    $response = (object) array(
        'message' => 'Access Denied - no authorization has been granted',
    );
    echo json_encode($response);
    exit;
}
if (!isset($_SESSION['authGranted'])) {
    $u = $_SERVER['PHP_AUTH_USER'];
    $p = $_SERVER['PHP_AUTH_PW'];
    $query = sprintf("
    	SELECT admin
    	FROM login
    	WHERE
    	username = '%s' AND password = '%s'
    	AND disabled = 0 AND activated = 1
    	LIMIT 1;", mysql_real_escape_string($u), mysql_real_escape_string(sha1($p . $seed)));
    $result = mysql_query($query);
    $result = mysql_fetch_array($result);
    if ($result['admin'] !== '1') {
        header('WWW-Authenticate: Basic realm="My Realm"');
        header('HTTP/1.0 401 Unauthorized');
    	echo '{ message: "Access Denied - insufficient permissions" }';
        exit;
    }
} ?>
