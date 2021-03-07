<?php
require_once "header.php";

$conn = connectToDb();
function getProducts($filter) {
    $querySegment = "";
    $count = 0;
    foreach($filter as $metric => $value){
        $count ++;
        if ($count > 1) {
            $querySegment .= ' AND ';
        }
        $querySegment .= "$metric  =  $value";
    }
    $query = sprintf("SELECT * FROM products WHERE $querySegment");

    $result = mysqli_query($conn, $query);
    $num = mysqli_num_rows($result);
    $response = array();
    for ($i = 0; $i < $num; $i++) {
        $id = mysqli_result($result,$i,"id");
        $product = (object) array();
        $product->id = $id;
        $product->name = mysqli_result($result,$i,"name");
        $product->manufacturer = mysqli_result($result,$i,"manufacturer");
        $product->url = mysqli_result($result,$i,"url");
        $product->colors = mysqli_result($result,$i,"colors");
        $product->variations = getVariations($id);
        $product->notes = mysqli_result($result,$i,"notes");
        $product->status = mysqli_result($result,$i,"status");
        $product->embed = mysqli_result($result,$i,"embed");
        array_push($response, $product);
    }
    return JSON_encode($response);
}

function getProduct($id) {
    $query = sprintf("SELECT * FROM products WHERE id = " . $id);
    $result = mysqli_query($conn, $query);
	$num = mysqli_num_rows($result);
	$response = array();
	for ($i = 0; $i < $num; $i++) {
        $id = mysqli_result($result,$i,"id");
		$product = (object) array();
		$product->id = $id;
		$product->name = mysqli_result($result,$i,"name");
		$product->manufacturer = mysqli_result($result,$i,"manufacturer");
		$product->url = mysqli_result($result,$i,"url");
		$product->colors = mysqli_result($result,$i,"colors");
		$product->variations = getVariations($id);
		$product->notes = mysqli_result($result,$i,"notes");
		$product->status = mysqli_result($result,$i,"status");
		$product->embed = mysqli_result($result,$i,"embed");
		array_push($response, $product);
	}
	return json_encode($response);
}

function getAllProducts() {
    $query = sprintf("SELECT * FROM products WHERE status in (\"1\", \"2\")");
    $result = mysqli_query($conn, $query);
	$num = mysqli_num_rows($result);
	$response = array();
	for ($i = 0; $i < $num; $i++) {
        $id = mysqli_result($result,$i,"id");
		$product = (object) array();
		$product->id = $id;
		$product->name = mysqli_result($result,$i,"name");
		$product->manufacturer = mysqli_result($result,$i,"manufacturer");
		$product->url = mysqli_result($result,$i,"url");
		$product->colors = mysqli_result($result,$i,"colors");
		$product->variations = getVariations($id);
		$product->notes = mysqli_result($result,$i,"notes");
		$product->status = mysqli_result($result,$i,"status");
		$product->embed = mysqli_result($result,$i,"embed");
		array_push($response, $product);
	}
	return json_encode($response);
}

function getVariations($productId) {
    $query = sprintf("SELECT * FROM variations WHERE productId = $productId ORDER BY sortIndex ASC");
    $result = mysqli_query($conn, $query);
    $num = mysqli_num_rows($result);
    $variations = array();
    for($i = 0; $i < $num; $i++) {
        $variation = (object) array();
        $variation->id = mysqli_result($result,$i,'id');
        $variation->name = mysqli_result($result,$i,'name');
        $variation->svg = mysqli_result($result,$i,'svg');
        $variation->sortIndex = intval(mysqli_result($result,$i,'sortIndex'));
        array_push($variations, $variation);
    }
    return $variations;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	if (isset($_GET['filter'])) {
        echo getProducts($_GET['filter']);
        mysqli_close($conn);
        return;
	}

	if (isset($_GET['id'])) {
		echo getProduct($_GET['id']);
        mysqli_close($conn);
        return;
	}
	echo getAllProducts();
    mysqli_close($conn);
    return;
}
