<?php
require_once "header.php";
$response = (object) array(
    'message' => 'pong',
    'authGranted' => $_SESSION['authGranted']
);
echo json_encode($response);
 ?>
