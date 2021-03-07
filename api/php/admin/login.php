<?php
//error_reporting(0); // we don't want to see errors on screen
error_reporting(E_ALL);
ini_set('display_errors', 'on');
// Start a session
session_start();
require_once ('../db_connect.inc.php'); // include the database connection
require_once ("../functions.inc.php"); // include all the functions

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="My Realm"');
    header('HTTP/1.0 401 Unauthorized');
	echo 'Access Denied';
    exit;
}
$u = $_SERVER['PHP_AUTH_USER'];
$p = $_SERVER['PHP_AUTH_PW'];

$seed="0dAfghRqSTgx"; // the seed for the passwords
$conn = connectToDb();
$query = sprintf("
	SELECT admin
	FROM login
	WHERE
	username = '%s' AND password = '%s'
	AND disabled = 0 AND activated = 1
	LIMIT 1;", mysqli_real_escape_string($conn, $u), mysqli_real_escape_string($conn, sha1($p . $seed)));
$result = mysqli_query($conn, $query);
$result = mysqli_fetch_array($result);
if ($result['admin'] !== '1') {
    header('WWW-Authenticate: Basic realm="My Realm"');
    header('HTTP/1.0 401 Unauthorized');
	echo '{ message: "Access Denied - insufficient permissions" }';
	exit;
}

setcookie('Kp-Auth-Token', session_id(), time() + (86400 * 30), "/", 'kitepaint.com');
$_SESSION['authGranted'] = true;

// Get the domain
$returnUrl = $_GET['returnUrl'];
// TODO use cookies?
header("Location: " . $returnUrl);
die();
