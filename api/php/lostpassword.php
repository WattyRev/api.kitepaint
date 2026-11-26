<?php
 
require_once "header.php";

$username = isset($_POST['username']) ? $_POST['username'] : '';
$email = isset($_POST['email']) ? $_POST['email'] : '';

// Limit reset requests per client address, and per email address so an inbox can't be flooded.
if (!rate_limit_allow('lostpassword', kp_client_ip(), 5, 3600)
    || ($email !== '' && !rate_limit_allow('lostpassword_email', strtolower(trim($email)), 3, 3600))) {
	lost_password_error('Too many password reset requests. Please try again later.');
	return;
}

$response = lostPassword($username, $email);
if ($response->valid){

	lost_password_success();

} else {

	lost_password_error($response->message);

}

?>
