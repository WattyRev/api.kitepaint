<?php
require_once "header.php";
$response = (object) array(
    'message' => 'pong',
);
var_dump($_SESSION);
echo json_encode($response);
 ?>
