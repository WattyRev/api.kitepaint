<?php

require_once "header.php";

$email = isset($_POST['email']) ? trim($_POST['email']) : '';

// Limit requests per client address, and per email address so one inbox can't be flooded.
if (!rate_limit_allow('resend_activation', kp_client_ip(), 5, 3600)
    || ($email !== '' && !rate_limit_allow('resend_activation_email', strtolower($email), 3, 3600))) {
	resend_activation_response(false, 'Too many requests. Please try again later.');
	return;
}

$response = resendActivationEmail($email);
if (!$response->valid) {
	resend_activation_response(false, $response->message);
} else {
	resend_activation_response(true, 'If an account with that email is waiting for activation, we have sent a new activation link.');
}

?>
