<?php
require_once "header.php";
$conn = connectToDb();
if ($_GET){
	if (isset($_GET['filter'])) {
		$filter = "";
		$count = 0;
		foreach($_GET['filter'] as $metric => $value){
			$count ++;
			if ($count > 1) {
				$filter .= ' AND ';
			}
			$filter .= "$metric  =  $value";
		}
		$limit = isset($_GET['limit']) ? "LIMIT " . $_GET['limit'] : "";
		$order = isset($_GET['order']) ? "ORDER BY " . $_GET['order'][0] . " " . $_GET['order'][1] : "";
		$query = sprintf("SELECT * FROM login WHERE $filter $order $limit");

		$result = mysqli_query($conn, $query);
		$num = mysqli_num_rows($result);
		mysqli_close($conn);
		$response = array();
		for ($i = 0; $i < $num; $i++) {
			$users = (object) array();
			foreach ($_GET['return'] as $key=>$metric){
				$users->$metric = mysqli_result($result,$i,$metric);
				if ($metric === 'last_login' || $metric === 'create_time' || $metric === 'deleted_time') {
					$users->$metric = date("m/d/Y", strtotime($users->$metric));
				}
			}
			array_push($response, $users);
		}
		echo JSON_encode($response);
		return;
	}
}

if (isset($_POST['id'])){
	$response = (object) array(
		'valid' => true,
		'message' => ''
	);

	foreach($_POST as $key => $value) {
		if ($key === 'id') {
			continue;
		}

		$query = sprintf("update login set $key = '%s' where loginid = '%s'",
			mysqli_real_escape_string($conn, $value), mysqli_real_escape_string($conn, $_POST['id']));

		if (!mysqli_query($conn, $query)) {
			$responsive->valid = false;
			$response->message = 'Unable to change ' . $key;
		}
	}
	echo json_encode($response);
	return;
}

if(isset($_POST)) {
	echo json_encode($_POST);
	return;
}
