<?php
require_once "header.php";
$conn = connectToDb();
if($_GET) {
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
		$query = sprintf("SELECT * FROM orders WHERE $filter $order $limit");

		$result = mysqli_query($conn, $query);
		$num = mysqli_num_rows($result);
		mysqli_close($conn);
		$response = array();
		for ($i = 0; $i < $num; $i++) {
			$users = (object) array();
			foreach ($_GET['return'] as $key=>$metric){
				$users->$metric = mysqli_result($result,$i,$metric);
				if ($metric === 'created') {
					$users->$metric = date("m/d/Y", strtotime($users->$metric));
				}
			}
			array_push($response, $users);
		}
		echo JSON_encode($response);
		return;
	}
}
if($_POST){
	$response = (object) array();
	$response->valid = true;

	if(isset($_POST['new_order'])) {
		$retailer = $_POST['retailer'];
		$user = $_POST['user'];
		$product = $_POST['product'];
		$name = $_POST['name'];
		$message = $_POST['message'];
		$designs = $_POST['variations'];

		$sql = sprintf("insert into orders (retailer,user,first_name,last_name,email,product,name,designs,message,created) value ('%s','%s','%s','%s','%s','%s','%s','%s','%s',now())",
		mysqli_real_escape_string($conn, $retailer),
		mysqli_real_escape_string($conn, $user['id']),
		mysqli_real_escape_string($conn, $user['first_name']),
		mysqli_real_escape_string($conn, $user['last_name']),
		mysqli_real_escape_string($conn, $user['email']),
		mysqli_real_escape_string($conn, $product),
		mysqli_real_escape_string($conn, $name),
		mysqli_real_escape_string($conn, $designs),
		mysqli_real_escape_string($conn, $message));

		if (mysqli_query($conn, $sql)) {
			$id = mysqli_insert_id($conn);
			echo json_encode($response);
			return;

		} else {
			$response->valid = false;
			$response->message = 'Unable to create order. Try again later.';
			echo json_encode($response);
			return;
		}
	}
}
