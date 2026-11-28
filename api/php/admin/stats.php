<?php
require_once "header.php";
$conn = connectToDb();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	http_response_code(405);
	echo json_encode(array('message' => 'Method not allowed'));
	return;
}

// Each table is counted with a single query using conditional aggregation. Every row in the table is
// counted, including deleted accounts and inactive or private designs.
$userResult = mysqli_query($conn,
	"SELECT COUNT(*) AS total,"
	. " COALESCE(SUM(`last_login` >= (NOW() - INTERVAL 30 DAY)), 0) AS active30,"
	. " COALESCE(SUM(`last_login` >= (NOW() - INTERVAL 365 DAY)), 0) AS active365"
	. " FROM `login`");
$designResult = mysqli_query($conn,
	"SELECT COUNT(*) AS total,"
	. " COALESCE(SUM(`created` >= (NOW() - INTERVAL 30 DAY)), 0) AS created30,"
	. " COALESCE(SUM(`created` >= (NOW() - INTERVAL 1 YEAR)), 0) AS createdYear"
	. " FROM `designs`");

if (!$userResult || !$designResult) {
	mysqli_close($conn);
	http_response_code(500);
	echo json_encode(array('message' => 'Unable to load stats'));
	return;
}

$users = mysqli_fetch_assoc($userResult);
$designs = mysqli_fetch_assoc($designResult);
mysqli_close($conn);

echo json_encode(array(
	'users' => array(
		'total' => (int) $users['total'],
		'active30' => (int) $users['active30'],
		'active365' => (int) $users['active365'],
	),
	'designs' => array(
		'total' => (int) $designs['total'],
		'created30' => (int) $designs['created30'],
		'createdYear' => (int) $designs['createdYear'],
	),
));
