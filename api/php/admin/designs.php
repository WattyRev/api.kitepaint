<?php
require_once "header.php";
$conn = connectToDb();
if ($_GET){
	$filter = "";
	if (isset($_GET['filter'])){
		$filter = "WHERE " . build_where_conditions($_GET['filter'], null, sql_escaper($conn));
	}
	$order = build_order_clause(isset($_GET['order']) ? $_GET['order'] : null);
	$limit = build_limit_clause(isset($_GET['limit']) ? $_GET['limit'] : null, isset($_GET['skip']) ? $_GET['skip'] : null);
	// Break ties on id so that pages are stable when sorting by a non-unique column.
	if ($order !== '' && strpos($order, '`id`') === false) {
		$order .= ', `id` DESC';
	}
	$query = "SELECT * FROM designs $filter $order $limit";

	$result = mysqli_query($conn, $query);
	$num = mysqli_num_rows($result);

	// When requested, also report the total number of matching designs (ignoring the limit) so
	// that clients can paginate. Opt-in so existing callers keep receiving a bare array.
	$includeTotal = isset($_GET['includeTotal']) && (string) $_GET['includeTotal'] !== '' && (string) $_GET['includeTotal'] !== '0';
	$total = null;
	if ($includeTotal) {
		$countResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM designs $filter");
		$total = $countResult ? (int) mysqli_result($countResult, 0, "total") : 0;
	}
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
	if ($includeTotal) {
		echo JSON_encode(array('data' => $response, 'total' => $total));
		return;
	}
	echo JSON_encode($response);
	return;

} elseif ($_POST) {
	$response = (object) array(
		'valid' => true,
		'message' => ''
	);

	//Delete
	if (isset($_POST['delete'])) {
		$query = sprintf("delete from designs where id = '%s'",
			mysqli_real_escape_string($conn, $_POST['id']));

		if (mysqli_query($conn, $query)) {
			delete_design_colors($conn, $_POST['id']);
		} else {
			$response->valid = false;
			$response->message = 'Unable to delete design';
		}

		echo json_encode($response);

		return;
	}

	//Update
	$id = $_POST['id'];
	$vars = (object) array();

	if (isset($_POST['active'])) {
		$vars->active = $_POST['active'] === 'true' ? '1' : '0';
	}

	if (isset($_POST['user'])) {
		$vars->user = $_POST['user'];
	}

	if (isset($_POST['status'])) {
		$vars->status = $_POST['status'];
	}

	if (isset($_POST['name'])) {
		$vars->name = $_POST['name'];
	}

	if (isset($_POST['variations'])) {
		$vars->variations = $_POST['variations'];
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

	if (isset($_POST['variations']) && !sync_design_colors($conn, $id, $_POST['variations'])) {
		$response->valid = false;
		$response->message = 'Unable to change colors';
	}

	$query = sprintf("update designs set updated = now() where id = '%s'",
		mysqli_real_escape_string($conn, $id));

	if (mysqli_query($conn, $query)) {
	} else {
		$response->valid = false;
		$response->message = 'Unable to change updated';
	}

	echo json_encode($response);

} else {
	echo 'No GET or POST variables';
}
