<?php 
require_once "header.php"; 
if ($_GET){
	$query = sprintf("SELECT * FROM manufacturers");
	 
	$result = mysqli_query($query);
	$num = mysqli_num_rows($result);
	mysqli_close();
	$response = array();
	for ($i = 0; $i < $num; $i++) {
		$manufacturer = (object) array();
		$manufacturer->id = mysqli_result($result,$i,"id");
		$manufacturer->activated = mysqli_result($result,$i,"activated") === '1' ? true : false;
		$manufacturer->created = date("m/d/Y", strtotime(mysqli_result($result,$i,"created")));
		$manufacturer->name = mysqli_result($result,$i,"name");
		$manufacturer->contact_name = mysqli_result($result,$i,"contact_name");
		$manufacturer->contact_phone = mysqli_result($result,$i,"contact_phone");
		$manufacturer->contact_email = mysqli_result($result,$i,"contact_email");
		$manufacturer->billing_name = mysqli_result($result,$i,"billing_name");
		$manufacturer->billing_phone = mysqli_result($result,$i,"billing_phone");
		$manufacturer->billing_email = mysqli_result($result,$i,"billing_email");
		$manufacturer->billing_address = mysqli_result($result,$i,"billing_address");
		$manufacturer->billing_city = mysqli_result($result,$i,"billing_city");
		$manufacturer->billing_state = mysqli_result($result,$i,"billing_state");
		$manufacturer->billing_postal = mysqli_result($result,$i,"billing_postal");
		$manufacturer->billing_country = mysqli_result($result,$i,"billing_country");
		$manufacturer->invoice_amount = mysqli_result($result,$i,"invoice_amount");
		$manufacturer->last_paid = date("m/d/Y", strtotime(mysqli_result($result,$i,"last_paid")));
		$manufacturer->products = mysqli_result($result,$i,"products");
		$manufacturer->logo = mysqli_result($result,$i,"logo");
		$manufacturer->website = mysqli_result($result,$i,"website");
		array_push($response, $manufacturer);
	}
	echo json_encode($response);
} elseif ($_POST) {

	$response = (object) array(
		'valid' => true,
		'message' => ''
	);

	//Delete
	if (isset($_POST['delete'])) {
		$query = sprintf("delete from manufacturers where id = '%s'", 
			mysqli_real_escape_string($_POST['id']));

		if (mysqli_query($query)) {

		} else {
			$response->valid = false;
			$response->message = 'Unable to delete manufacturer';
		}

		echo json_encode($response);

		return;
	}

	//Paid
	if (isset($_POST['paid'])) {
		$query = sprintf("update manufacturers set last_paid = now() where id = '%s'", 
			mysqli_real_escape_string($_POST['id']));

		if (mysqli_query($query)) {

		} else {
			$response->valid = false;
			$response->message = 'Unable to update manufacurer';
		}

		echo json_encode($response);

		return;
	}

	//Create
	if (isset($_POST['new'])) {

		$name = $_POST['name'];
		$contact_name = $_POST['contact_name'];
		$contact_phone = $_POST['contact_phone'];
		$contact_email = $_POST['contact_email'];
		$billing_name = $_POST['billing_name'];
		$billing_phone = $_POST['billing_phone'];
		$billing_email = $_POST['billing_email'];
		$billing_address = $_POST['billing_address'];
		$billing_city = $_POST['billing_city'];
		$billing_state = $_POST['billing_state'];
		$billing_postal = $_POST['billing_postal'];
		$billing_country = $_POST['billing_country'];
		$invoice_amount = $_POST['invoice_amount'];
		$logo = $_POST['logo'];
		$website = $_POST['website'];


		if (!valid_email($contact_email)) {
			$response->valid = false;
			$response->message = 'Invalid email';
			echo json_encode($response);
			return;
		}
		$sql = sprintf("insert into manufacturers (activated,created,name,contact_name,contact_phone,contact_email,billing_name,billing_phone,billing_email,billing_address,billing_city,billing_state,billing_postal,billing_country,invoice_amount,last_paid,logo,website) value (1, now(), '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', now(), '%s', '%s')",
		mysqli_real_escape_string($name),
		mysqli_real_escape_string($contact_name),
		mysqli_real_escape_string($contact_phone),
		mysqli_real_escape_string($contact_email),
		mysqli_real_escape_string($billing_name),
		mysqli_real_escape_string($billing_phone),
		mysqli_real_escape_string($billing_email),
		mysqli_real_escape_string($billing_address),
		mysqli_real_escape_string($billing_city),
		mysqli_real_escape_string($billing_state),
		mysqli_real_escape_string($billing_postal),
		mysqli_real_escape_string($billing_country),
		mysqli_real_escape_string($invoice_amount),
		mysqli_real_escape_string($logo),
		mysqli_real_escape_string($website));
		
		
		if (mysqli_query($sql)) {
			$id = mysqli_insert_id();		
		} else {
			$response->valid = false;
			$response->message = 'Unable to create manufacturer';
			echo json_encode($response);
			return;
		}
	}

	//Update
	$id = $_POST['id'];
	$vars = array(
		'activated' => $_POST['activated'] === 'true' ? '1' : '0',
		'name' => $_POST['name'],
		'contact_name' => $_POST['contact_name'],
		'contact_phone' => $_POST['contact_phone'],
		'contact_email' => $_POST['contact_email'],
		'billing_name' => $_POST['billing_name'],
		'billing_phone' => $_POST['billing_phone'],
		'billing_email' => $_POST['billing_email'],
		'billing_address' => $_POST['billing_address'],
		'billing_city' => $_POST['billing_city'],
		'billing_state' => $_POST['billing_state'],
		'billing_postal' => $_POST['billing_postal'],
		'billing_country' => $_POST['billing_country'],
		'invoice_amount' => $_POST['invoice_amount'],
		'logo' => $_POST['logo'],
		'website' => $_POST['website'],
	);

	foreach($vars as $metric => $val){
		$query = sprintf("update manufacturers set $metric = '%s' where id = '%s'",
			mysqli_real_escape_string($val), mysqli_real_escape_string($id));

		if (mysqli_query($query)) {
		} else {
			$response->valid = false;
			$response->message = 'Unable to change ' . $metric;
		}
	}

	echo json_encode($response);

} else {
	echo 'No GET or POST variables';
}