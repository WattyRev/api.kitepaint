<?php
require_once "header.php";

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

    $result = mysql_query($query);
    $num = mysql_num_rows($result);
    mysql_close();
    $response = array();
    for ($i = 0; $i < $num; $i++) {
        $id = mysql_result($result,$i,"id");
        $product = (object) array();
        $product->id = $id;
        $product->name = mysql_result($result,$i,"name");
        $product->manufacturer = mysql_result($result,$i,"manufacturer");
        $product->url = mysql_result($result,$i,"url");
        $product->colors = mysql_result($result,$i,"colors");
        $product->variations = getVariations($id);
        $product->notes = mysql_result($result,$i,"notes");
        $product->status = mysql_result($result,$i,"status");
        $product->embed = mysql_result($result,$i,"embed");
        array_push($response, $product);
    }
    return JSON_encode($response);
}

function getProduct($id) {
    $query = sprintf("SELECT * FROM products WHERE id = " . $id);
    $result = mysql_query($query);
	$num = mysql_num_rows($result);
	mysql_close();
	$response = array();
	for ($i = 0; $i < $num; $i++) {
        $id = mysql_result($result,$i,"id");
		$product = (object) array();
		$product->id = $id;
		$product->name = mysql_result($result,$i,"name");
		$product->manufacturer = mysql_result($result,$i,"manufacturer");
		$product->url = mysql_result($result,$i,"url");
		$product->colors = mysql_result($result,$i,"colors");
		$product->variations = getVariations($id);
		$product->notes = mysql_result($result,$i,"notes");
		$product->status = mysql_result($result,$i,"status");
		$product->embed = mysql_result($result,$i,"embed");
		array_push($response, $product);
	}
	return json_encode($response);
}

function getAllProducts() {
    $query = sprintf("SELECT * FROM products WHERE status in (\"1\", \"2\")");
    $result = mysql_query($query);
	$num = mysql_num_rows($result);
	mysql_close();
	$response = array();
	for ($i = 0; $i < $num; $i++) {
        $id = mysql_result($result,$i,"id");
		$product = (object) array();
		$product->id = $id;
		$product->name = mysql_result($result,$i,"name");
		$product->manufacturer = mysql_result($result,$i,"manufacturer");
		$product->url = mysql_result($result,$i,"url");
		$product->colors = mysql_result($result,$i,"colors");
		$product->variations = getVariations($id);
		$product->notes = mysql_result($result,$i,"notes");
		$product->status = mysql_result($result,$i,"status");
		$product->embed = mysql_result($result,$i,"embed");
		array_push($response, $product);
	}
	return json_encode($response);
}

function getVariations($productId) {
    echo "getting variations for $productId \n";
    $query = sprintf("SELECT * FROM variations WHERE productId = $productId ORDER BY sortIndex ASC");
    $result = mysql_query($query);

    if (!$result) {
        echo "Failed to get variations";
        var_dump(mysql_error());
    }

    $num = mysql_num_rows($result);
    echo "got $num records. \n";
    $variations = array();
    for($i = 0; $i < $num; $i++) {
        $variation = (object) array();
        $variation->id = mysql_result($variationsResult,$i,'id');
        $variation->name = mysql_result($variationsResult,$i,'name');
        $variation->svg = mysql_result($variationsResult,$i,'svg');
        $variation->sortIndex = intval(mysql_result($variationsResult,$i,'sortIndex'));
        array_push($variations, $variation);
    }
    var_dump($variations);
    return $variations;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	if (isset($_GET['filter'])) {
        echo getProducts($_GET['filter']);
        return;
	}

	if (isset($_GET['id'])) {
		echo getProduct($_GET['id']);
        return;
	}
	echo getAllProducts();
    return;
}
