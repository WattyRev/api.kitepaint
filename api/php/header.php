<?php
//error_reporting(0); // we don't want to see errors on screen
error_reporting(0);
ini_set('display_errors', 'on');
// Start a session
session_start();
require_once ('db_connect.inc.php'); // include the database connection
require_once ("functions.inc.php"); // include all the functions
$seed="0dAfghRqSTgx"; // the seed for the passwords
$domain = "kitepaint.com";

// Allow other domains access
$allowedOrigins = array(
  '(http(s)?:\/\/)kitepaint.com'
);
$isProduction = ($_SERVER['HTTP_HOST'] === 'kitepaint.com' || $_SERVER['HTTP_HOST'] === 'www.kitepaint.com');
if (!$isProduction) {
    // Allow localhost to access beta API
    array_push($allowedOrigins, '.*localhost.*')
}
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] != '') {
  foreach ($allowedOrigins as $allowedOrigin) {
    if (preg_match('#' . $allowedOrigin . '#', $_SERVER['HTTP_ORIGIN'])) {
      header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
      header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
      header('Access-Control-Max-Age: 1000');
      header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

       // respond to preflights
      if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
         header('Access-Control-Allow-Origin: *');
         exit;
      }
      break;
    }
  }
}
