<?php
require_once "header.php";
$conn = connectToDb();
if ($_GET){
	if (isset($_GET['filter'])) {
		$filter = build_where_conditions($_GET['filter'], array('loginid'), sql_escaper($conn));
		$return = validate_return_columns(isset($_GET['return']) ? $_GET['return'] : array(), array('username', 'loginid'));
		$limit = build_limit_clause(isset($_GET['limit']) ? $_GET['limit'] : null);
		$query = "SELECT * FROM login WHERE $filter $limit";

		$result = mysqli_query($conn, $query);
		$num = mysqli_num_rows($result);
		mysqli_close($conn);
		$response = array();
		for ($i = 0; $i < $num; $i++) {
			$users = (object) array();
			foreach ($return as $metric){
				$users->$metric = mysqli_result($result,$i,$metric);
			}
			array_push($response, $users);
		}
		echo JSON_encode($response);
		return;
	}
}

echo json_encode(array());
