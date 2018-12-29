<?php
require_once "header.php";
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

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
		$query = sprintf("SELECT * FROM products WHERE $filter");

		$result = mysql_query($query);
		$num = mysql_num_rows($result);
		mysql_close();
		$response = array();
		for ($i = 0; $i < $num; $i++) {
			$product = (object) array();
			$product->id = mysql_result($result,$i,"id");
			$product->name = mysql_result($result,$i,"name");
			$product->manufacturer = mysql_result($result,$i,"manufacturer");
			$product->url = mysql_result($result,$i,"url");
			$product->colors = mysql_result($result,$i,"colors");
			$product->variations = mysql_result($result,$i,"variations");
			$product->notes = mysql_result($result,$i,"notes");
			$product->status = mysql_result($result,$i,"status");
			array_push($response, $product);
		}
		echo JSON_encode($response);
		return;
	}

	if (isset($_GET['id'])) {
		$query = sprintf("SELECT * FROM products WHERE id = " . $_GET['id']);
	} else {
		$query = sprintf("SELECT * FROM products WHERE status in (\"1\", \"2\")");
	}

	$result = mysql_query($query);
	$num = mysql_num_rows($result);
	mysql_close();
	$response = array();
	for ($i = 0; $i < $num; $i++) {
		$product = (object) array();
		$product->id = mysql_result($result,$i,"id");
		$product->name = mysql_result($result,$i,"name");
		$product->manufacturer = mysql_result($result,$i,"manufacturer");
		$product->url = mysql_result($result,$i,"url");
		$product->colors = mysql_result($result,$i,"colors");
		$product->variations = mysql_result($result,$i,"variations");
		$product->notes = mysql_result($result,$i,"notes");
		$product->status = mysql_result($result,$i,"status");
		array_push($response, $product);
	}
	echo json_encode($response);
}
