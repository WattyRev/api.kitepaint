<?php
require_once "header.php";

$conn = connectToDb();
if ($_GET) {
	$filter = "";
	if (isset($_GET['filter'])){
		$filter .= "WHERE ";
		$count = 0;
		foreach($_GET['filter'] as $metric => $value){
			$count ++;
			if ($count > 1) {
				$filter .= ' AND ';
			}
			$filter .= "$metric  =  $value";
		}
	}
	$limit = isset($_GET['limit']) ? "LIMIT " . $_GET['limit'] : "";
	$order = isset($_GET['order']) ? "ORDER BY " . $_GET['order'][0] . " " . $_GET['order'][1] : "";
	$query = sprintf("SELECT * FROM retailers $filter $order $limit");

	$result = mysqli_query($conn, $query);
	$num = mysqli_num_rows($result);
	mysqli_close($conn);
	$response = array();
	for ($i = 0; $i < $num; $i++) {
		$designs = (object) array();
		foreach ($_GET['return'] as $key=>$metric){
			$designs->$metric = mysqli_result($result,$i,$metric);
			if ($metric === 'created' || $metric === 'updated') {
				$designs->$metric = date("m/d/Y", strtotime($designs->$metric));
			}
		}
		array_push($response, $designs);
	}
	echo JSON_encode($response);
	return;
} elseif ($_POST) {
	$response = (object) array(
		'valid' => true,
		'message' => ''
	);

	//create
	if (isset($_POST['new'])) {

		// echo json_encode($_POST);
		// return;

		$name = $_POST['name'];
		$email = $_POST['email'];
		$url = $_POST['url'];
		$city = $_POST['city'];
		$state = $_POST['state'];
		$image = $_POST['image'];
		$product_opt_out = json_encode($_POST['product_opt_out']);
		$product_urls = json_encode($_POST['product_urls']);
		$code = generate_code(20);
		$sql = sprintf("insert into retailers (activated,name,email,url,city,state,image,product_opt_out,product_urls,actcode,created,updated) value (0,'%s','%s','%s','%s','%s','%s','%s','%s','%s',now(),now())",
			mysqli_real_escape_string($conn, $name), mysqli_real_escape_string($conn, $email), mysqli_real_escape_string($conn, $url), mysqli_real_escape_string($conn, $city), mysqli_real_escape_string($conn, $state), mysqli_real_escape_string($conn, $image), mysqli_real_escape_string($conn, $product_opt_out), mysqli_real_escape_string($conn, $product_urls), mysqli_real_escape_string($conn, $code));

		if (mysqli_query($conn, $sql)) {
			$id = mysqli_insert_id($conn);
			if (sendRetailerActivation($id, $name, $email, $code)) {
				echo json_encode($response);
				return;
			} else {
				$response->valid = false;
				$response->message = 'Unable to send activation email';
				echo json_encode($response);
				return;
			}
		} else {
			$response->valid = false;
			$response->message = 'Unable to create retailer';
			echo json_encode($response);
			return;
		}
	}

	//Delete
	if (isset($_POST['delete'])) {
		$query = sprintf("delete from retailers where id = '%s'",
			mysqli_real_escape_string($conn, $_POST['id']));

		if (mysqli_query($conn, $query)) {

		} else {
			$response->valid = false;
			$response->message = 'Unable to delete retailer';
		}

		echo json_encode($response);

		return;
	}

	//Update
	$id = $_POST['id'];
	$vars = array(
		'activated' => $_POST['activated'] === 'true' ? '1' : '0',
		'name' => $_POST['name'],
		'username' => $_POST['username'],
		'url' => $_POST['url'],
		'city' => $_POST['city'],
		'state' => $_POST['state'],
		'email' => $_POST['email'],
		'image' => $_POST['image'],
		'product_opt_out' => json_encode($_POST['product_opt_out']),
		'product_urls' => json_encode($_POST['product_urls'])
	);

	foreach($vars as $metric => $val){
		$query = sprintf("update retailers set $metric = '%s' where id = '%s'",
			mysqli_real_escape_string($conn, $val), mysqli_real_escape_string($conn, $id));

		if (mysqli_query($conn, $query)) {
		} else {
			$response->valid = false;
			$response->message = 'Unable to change ' . $metric;
		}
	}
	$query = sprintf("update retailers set updated = now() where id = '%s'",
		mysqli_real_escape_string($conn, $id));

	if (mysqli_query($conn, $query)) {
	} else {
		$response->valid = false;
		$response->message = 'Unable to change updated';
	}

	echo json_encode($response);
}
