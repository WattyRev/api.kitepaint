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
		$query = sprintf("SELECT * FROM manufacturers WHERE $filter");

		$result = mysqli_query($conn, $query);
		$num = mysqli_num_rows($result);
		mysqli_close($conn);
		$response = array();
		for ($i = 0; $i < $num; $i++) {
			$manufacturers = (object) array();
			foreach ($_GET['return'] as $key=>$metric){
				$manufacturers->$metric = mysqli_result($result,$i,$metric);
				if ($metric === 'created') {
					$manufacturers->$metric = date("m/d/Y", strtotime($manufacturers->$metric));
				}
			}
			array_push($response, $manufacturers);
		}
		echo JSON_encode($response);
		return;
	}

	if (isset($_GET['id'])) {
		$query = sprintf("SELECT * FROM manufacturers WHERE id = " . $_GET['id']);
	} else if (isset($_GET['activated'])) {
		$query = sprintf("SELECT * FROM manufacturers WHERE activated = 1");
	} else {
		$query = sprintf("SELECT * FROM manufacturers");
	}

	$result = mysqli_query($conn, $query);
	$num = mysqli_num_rows($result);
	mysqli_close($conn);
	$response = array();
	for ($i = 0; $i < $num; $i++) {
		$manufacturers = (object) array();
		$manufacturers->id = mysqli_result($result,$i,"id");
		$manufacturers->name = mysqli_result($result,$i,"name");
		$manufacturers->logo = mysqli_result($result,$i,"logo");
		$manufacturers->website = mysqli_result($result,$i,"website");
		array_push($response, $manufacturers);
	}
	echo json_encode($response);
}
