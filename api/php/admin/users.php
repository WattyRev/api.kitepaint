<?php
require_once "header.php";
$conn = connectToDb();
if ($_GET){
	$conditions = array();
	if (isset($_GET['filter'])){
		// username and email are case-insensitive "contains" searches; other columns match exactly.
		$conditions[] = build_admin_filter_clause($_GET['filter'], array('username', 'email'), sql_escaper($conn));
	}
	// Lookup by exact loginid or partial username, used when picking a user for a design.
	if (isset($_GET['search']) && $_GET['search'] !== '') {
		$conditions[] = build_user_search_clause($_GET['search'], sql_escaper($conn));
	}
	$conditions = array_filter($conditions, function ($condition) { return $condition !== ''; });
	$filter = count($conditions) > 0 ? "WHERE " . implode(' AND ', $conditions) : "";
	$limit = build_limit_clause(isset($_GET['limit']) ? $_GET['limit'] : null, isset($_GET['skip']) ? $_GET['skip'] : null);
	$order = build_order_clause(isset($_GET['order']) ? $_GET['order'] : null);
	// Break ties on loginid so that pages are stable when sorting by a non-unique column.
	if ($order !== '' && strpos($order, '`loginid`') === false) {
		$order .= ', `loginid` DESC';
	}
	$query = "SELECT * FROM login $filter $order $limit";

	$result = mysqli_query($conn, $query);
	$num = mysqli_num_rows($result);

	// When requested, also report the total number of matching users (ignoring the limit) so
	// that clients can paginate. Opt-in so existing callers keep receiving a bare array.
	$includeTotal = isset($_GET['includeTotal']) && (string) $_GET['includeTotal'] !== '' && (string) $_GET['includeTotal'] !== '0';
	$total = null;
	if ($includeTotal) {
		$countResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM login $filter");
		$total = $countResult ? (int) mysqli_result($countResult, 0, "total") : 0;
	}
	mysqli_close($conn);
	$response = array();
	for ($i = 0; $i < $num; $i++) {
		$designs = (object) array();
		foreach ($_GET['return'] as $key=>$metric){
			$designs->$metric = mysqli_result($result,$i,$metric);
			if ($metric === 'create_time' || $metric === 'last_login' || $metric === 'deleted_time') {
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
		$query = sprintf("delete from login where loginid = '%s'",
			mysqli_real_escape_string($conn, $_POST['loginid']));

		if (mysqli_query($conn, $query)) {

		} else {
			$response->valid = false;
			$response->message = 'Unable to delete user';
		}

		echo json_encode($response);

		return;
	}

	//Create
	if (isset($_POST['new'])) {

		$username = $_POST['username'];
		$password = generate_code(8);
		$email = $_POST['email'];


		if (!valid_username($username)) {
			$response->valid = false;
			$response->message = 'Invalid username';
			echo json_encode($response);
			return;
		} elseif (is_reserved_username($username)) {
			$response->valid = false;
			$response->message = 'Invalid username';
			echo json_encode($response);
			return;
		} elseif (!valid_email($email)) {
			$response->valid = false;
			$response->message = 'Invalid email';
			echo json_encode($response);
			return;
		} elseif (user_exists($username)) {
			$response->valid = false;
			$response->message = $username . ' has already been taken';
			echo json_encode($response);
			return;
		}
		$code = generate_code(20);
		$sql = sprintf("insert into login (username,password,email,actcode,create_time,last_login,activated) value ('%s','%s','%s','%s', now(), now(), 1)",
		mysqli_real_escape_string($conn, $username), mysqli_real_escape_string($conn, sha1($password . $seed))
		, mysqli_real_escape_string($conn, $email), mysqli_real_escape_string($conn, $code));
		

		if (mysqli_query($conn, $sql)) {
			$id = mysqli_insert_id($conn);

			if (sendAccountInfo($username, $password, $email)) {
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
			$response->message = 'Unable to register';
			echo json_encode($response);
			return;
		}
	}

	//Reset Password
	if (isset($_POST['reset'])) {
		global $seed;

		$id = $_POST['loginid'];
		$username = $_POST['username'];
		$email = $_POST['email'];

		$query = sprintf("select loginid from login where loginid = '%s' limit 1",
			mysqli_real_escape_string($conn, $id));

		$result = mysqli_query($conn, $query);

		if (mysqli_num_rows($result) != 1) {
			$response->valid = false;
			$response->message = 'Incorrect user or email address';
			echo json_encode($response);
			return;
		}


		$newpass = generate_code(8);

		$query = sprintf("update login set password = '%s' where username = '%s'",
		    mysqli_real_escape_string($conn, sha1($newpass.$seed)), mysqli_real_escape_string($conn, $username));

		if (mysqli_query($conn, $query)) {

			if (sendLostPasswordEmail($username, $email, $newpass)) {
				return $response;
			} else {
				$response->valid = false;
				$response->message = 'Unable to send lost password email';
				echo json_encode($response);
				return;
			}

		} else {
			$response->valid = false;
			$response->message = 'Unable to reset password';
			echo json_encode($response);
			return;
		}
		echo json_encode($response);
		return;
	}

	//Update
	$id = $_POST['loginid'];
	if (isset($_POST['username']) && is_reserved_username($_POST['username'])) {
		$response->valid = false;
		$response->message = 'Invalid username';
		echo json_encode($response);
		return;
	}
	$vars = array(
		'username' => $_POST['username'],
		'email' => $_POST['email'],
		'activated' => $_POST['activated'] === 'true' ? '1' : '0'
	);

	foreach($vars as $metric => $val){
		$query = sprintf("update login set $metric = '%s' where loginid = '%s'",
			mysqli_real_escape_string($conn, $val), mysqli_real_escape_string($conn, $id));

		if (mysqli_query($conn, $query)) {
		} else {
			$response->valid = false;
			$response->message = 'Unable to change ' . $metric;
		}
	}

	echo json_encode($response);

} else {
	echo 'No GET or POST variables';
}
