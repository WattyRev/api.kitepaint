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
    return;
}
$u = $_SESSION['PHP_AUTH_USER'];
$p = $_SERVER['PHP_AUTH_PW'];

$seed="0dAfghRqSTgx"; // the seed for the passwords
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
	echo 'Access Denied';
	return;
}

$_SESSION['authGranted'] = true;
$_SESSION['idExtensions'] = array();
$ext = md5(uniqid(mt_rand(), true)); // Random id
$_SESSION['idExtensions'][$ext] = 1;
$sharedId = session_id() . '-' . $ext;

// Get the domain
$returnUrl = $_GET['returnUrl'];
header("Location: " . $returnUrl . "?" . $sharedId);
die();
