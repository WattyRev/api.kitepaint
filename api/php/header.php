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