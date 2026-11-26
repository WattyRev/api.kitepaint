<?php
 
require_once "header.php";

$username = isset($_POST['username']) ? $_POST['username'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$password2 = isset($_POST['password2']) ? $_POST['password2'] : '';
$email = isset($_POST['email']) ? $_POST['email'] : '';

if (!rate_limit_allow('register', kp_client_ip(), 5, 3600)) {
	register_error('Too many sign-up attempts. Please try again later.');
	return;
}

$response = registerNewUser($username, $password, $password2, $email);
if ($response->valid){

	register_success();

}else {

	register_error($response->message);

}
?>