<?php
 
require_once "header.php";

$id = isset($_POST['id']) ? $_POST['id'] : '';
$email = isset($_POST['email']) ? $_POST['email'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Limit password attempts per account, since this endpoint checks the current password.
if (!rate_limit_allow('change_email', (string) $id, 5, 900)) {
	change_email_error('Too many attempts. Please wait a few minutes and try again.');
	return;
}

$response = changeEmail($id, $email, $password);
if ($response->valid) {

  change_email_success();

} else {

  change_email_error($response->message);

}
 
?>
