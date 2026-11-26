<?php
require_once "header.php";

function getPublicProductIds() {
	$conn = connectToDb();
	// Query IDs of products that are public (status=2)
	$query = sprintf("SELECT id FROM products WHERE status = \"2\"");
	$result = mysqli_query($conn, $query);
	$num = mysqli_num_rows($result);

	// Cast SQL response into an array of IDs.
	$response = array();
	for ($i = 0; $i < $num; $i++) {
		array_push($response, mysqli_result($result,$i,"id"));
	}
	return $response;
}

function getProductStatusesById() {
	$conn = connectToDb();
	// Query the statuses of products and their ids
	$query = sprintf("SELECT id, status FROM products");
	$result = mysqli_query($conn, $query);
	$num = mysqli_num_rows($result);

	// Cast SQL response into a key value pair.
	$response = array();
	for ($i = 0; $i < $num; $i++) {
		$response[mysqli_result($result,$i,"id")] = mysqli_result($result,$i,"status");
	}
	return $response;
}


$conn = connectToDb();

if ($_GET){
	$productStatuses = getProductStatusesById();
	$status = '';
	if (isset($_GET['filter'])) {
		$filter = build_where_conditions($_GET['filter'], array('id', 'active', 'status', 'user', 'product'), sql_escaper($conn));
		if (isset($_GET['filter']['status'])) {
			$status = (string) $_GET['filter']['status'];
		}

		$productFilter = '';
		// If requesting for public designs, only get designs of public products as well.
		if ($status == '2') {
			// Build filter to get only designs of public products
			$productIdList = '';
			$count = 0;
			$ids = getPublicProductIds();
			forEach($ids as $id) {
				$count ++;
				if ($count > 1) {
					$productIdList .= ',';
				}
				$productIdList .= $id;
			}
			$productFilter = "AND product in ($productIdList)";
		}

		$limit = build_limit_clause(isset($_GET['limit']) ? $_GET['limit'] : null);
		$order = build_order_clause(isset($_GET['order']) ? $_GET['order'] : null, array('id', 'created', 'updated', 'name', 'status'));
		$query = "SELECT * FROM designs WHERE $filter $productFilter $order $limit";

		$result = mysqli_query($conn, $query);
		$num = mysqli_num_rows($result);
		mysqli_close($conn);
		$response = array();
		for ($i = 0; $i < $num; $i++) {
			$design = (object) array();
			$design->id = mysqli_result($result,$i,"id");
			$design->created = date("m/d/Y", strtotime(mysqli_result($result,$i,"created")));
			$design->updated = date("m/d/Y", strtotime(mysqli_result($result,$i,"updated")));
			$design->name = mysqli_result($result,$i,"name");
			$design->user = mysqli_result($result,$i,"user");
			$design->product = mysqli_result($result,$i,"product");
			$design->variations = mysqli_result($result,$i,"variations");
			$design->status = mysqli_result($result,$i,"status");
			$design->active = mysqli_result($result,$i,"active");
			$design->images = mysqli_result($result,$i,"images");
			$design->productStatus = $productStatuses[$design->product];
			array_push($response, $design);
		}
		echo JSON_encode($response);
		return;
	}

	if (isset($_GET['id'])) {
		$query = sprintf("SELECT * FROM designs WHERE id = %d", (int) $_GET['id']);
	} else if (isset($_GET['status'])) {
		$query = sprintf("SELECT * FROM designs WHERE status = %d", (int) $_GET['status']);
	} else {
		$query = sprintf("SELECT * FROM designs");
	}

	$result = mysqli_query($conn, $query);
	$num = mysqli_num_rows($result);
	mysqli_close($conn);
	$response = array();
	for ($i = 0; $i < $num; $i++) {
		$design = (object) array();
		$design->id = mysqli_result($result,$i,"id");
		$design->created = date("m/d/Y", strtotime(mysqli_result($result,$i,"created")));
		$design->updated = date("m/d/Y", strtotime(mysqli_result($result,$i,"updated")));
		$design->name = mysqli_result($result,$i,"name");
		$design->user = mysqli_result($result,$i,"user");
		$design->product = mysqli_result($result,$i,"product");
		$design->variations = mysqli_result($result,$i,"variations");
		$design->status = mysqli_result($result,$i,"status");
		$design->active = mysqli_result($result,$i,"active");
		$design->images = mysqli_result($result,$i,"images");
		$design->productStatus = $productStatuses[$design->product];
		array_push($response, $design);
	}
	echo json_encode($response);
	return;

} elseif ($_POST) {
	$response = (object) array(
		'valid' => true,
		'message' => '',
		'images' => ''
	);

	//Delete
	if (isset($_POST['delete'])) {
		$id = $_POST['id'];

		$query = sprintf("UPDATE designs SET active = 0 WHERE id = '%s'",
			mysqli_real_escape_string($conn, $id));

		if (mysqli_query($conn, $query)) {
		} else {
			$response->valid = false;
			$response->message = 'Unable to delete design';
		}
		$query = sprintf("UPDATE designs SET updated = now() WHERE id = '%s'",
		mysqli_real_escape_string($conn, $id));

		if (mysqli_query($conn, $query)) {
		} else {
		}
		echo json_encode($response);
		return;
	}

	//Create
	if (isset($_POST['new'])) {

		$name = $_POST['name'];
		$user = $_POST['user'];
		$product = $_POST['product'];
		$variations = $_POST['variations'];
		$status = $_POST['status'];

		$code = generate_code(20);

		$prepared = null;
		if (isset($_POST['images'])) {
			$prepared = prepare_design_images($_POST['images']);
			if (!$prepared['valid']) {
				$response->valid = false;
				$response->message = $prepared['message'];
				echo json_encode($response);
				return;
			}
		}

		$sql = sprintf("INSERT INTO designs (created, updated, name, user, product, variations, status) value (now(), now(), '%s', '%s', '%s', '%s', '%s')",
		mysqli_real_escape_string($conn, $name), mysqli_real_escape_string($conn, $user)
		, mysqli_real_escape_string($conn, $product), mysqli_real_escape_string($conn, $variations), mysqli_real_escape_string($conn, $status));

		if (mysqli_query($conn, $sql)) {
			$id = mysqli_insert_id($conn);
			$response->id = $id;
		} else {
			$response->valid = false;
			$response->message = 'Unable to save';
			echo json_encode($response);
			return;
		}

		if ($prepared !== null) {
			$saved = write_design_images((int) $response->id, $prepared['images'], '../img/designs/');
			if ($saved === false) {
				$response->valid = false;
				$response->message = 'Unable to save the image';
				echo json_encode($response);
				return;
			}
			$response->images = JSON_encode($saved);

			$query = sprintf("update designs set images = '%s' where id = '%s'",
				mysqli_real_escape_string($conn, $response->images), mysqli_real_escape_string($conn, $response->id));

			if (!mysqli_query($conn, $query)) {
				$response->valid = false;
				$response->message = 'Unable to save the images';
			}
		}
		$response->name = $name;
		echo json_encode($response);
	}

	//Update
	if (isset($_POST['id'])) {
		$id = $_POST['id'];
		$vars = array();

		if (isset($_POST['variations'])) {
			$vars['variations'] = $_POST['variations'];
		}
		if (isset($_POST['name'])) {
			$vars['name'] = $_POST['name'];
		}
		if (isset($_POST['status'])) {
			$vars['status'] = $_POST['status'];
		}
		if (isset($_POST['images'])) {
			$prepared = prepare_design_images($_POST['images']);
			if (!$prepared['valid']) {
				$response->valid = false;
				$response->message = $prepared['message'];
				echo json_encode($response);
				return;
			}
			$saved = write_design_images((int) $id, $prepared['images'], '../img/designs/');
			if ($saved === false) {
				$response->valid = false;
				$response->message = 'Unable to save the image';
				echo json_encode($response);
				return;
			}
			$vars['images'] = JSON_encode($saved);
			$response->images = JSON_encode($saved);
		}

		foreach($vars as $metric => $val){
			$query = sprintf("update designs set $metric = '%s' where id = '%s'",
				mysqli_real_escape_string($conn, $val), mysqli_real_escape_string($conn, $id));

			if (mysqli_query($conn, $query)) {
			} else {
				$response->valid = false;
				$response->message = 'Unable to change ' . $metric;
			}
		}
		$query = sprintf("update designs set updated = now() where id = '%s'",
		mysqli_real_escape_string($conn, $id));

		if (mysqli_query($conn, $query)) {
		} else {
			$response->valid = false;
			$response->message = 'Unable to change updated';
		}
		echo json_encode($response);
	}
}
