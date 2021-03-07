<?php
//error_reporting(0); // we don't want to see errors on screen
error_reporting(E_ALL);
// Start a session
session_start();
require_once ('db_connect.inc.php'); // include the database connection
require_once ("functions.inc.php"); // include all the functions
$seed="0dAfghRqSTgx"; // the seed for the passwords
$domain = "kitepaint.com";

// Allow other domains access
$allowedOrigins = array(
    '//kitepaint.com',
    '//www.kitepaint.com',
    '//admin.kitepaint.com',
    '//static.kitepaint.com'
);
$isProduction = (strpos($_SERVER['HTTP_HOST'], 'beta') === false);
if (!$isProduction) {
    // Allow localhost to access beta API
    array_push($allowedOrigins, 'localhost');
    array_push($allowedOrigins, '//beta.kitepaint.com');
    array_push($allowedOrigins, '//admin.beta.kitepaint.com');
}
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] != '') {
  // If we are on a whitelisted origin, set the appropriate headers
  foreach ($allowedOrigins as $allowedOrigin) {
    if (strpos($_SERVER['HTTP_ORIGIN'], $allowedOrigin) !== false) {
      header('Access-Control-Allow-Origin: *');
      header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
      header('Access-Control-Max-Age: 1000');
      header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

      // respond to preflights	$domain = "kitepaint.com";
      if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
         header('Access-Control-Allow-Origin: *');
         exit;
      }
      break;
    }
  }
}
