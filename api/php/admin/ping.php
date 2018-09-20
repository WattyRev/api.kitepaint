<?php
require_once "header.php";
$response = (object) array(
    'message' => 'pong',
);
echo json_encode($response);
 ?>
