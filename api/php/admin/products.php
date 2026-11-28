<?php
require_once "header.php";

function getProducts($filter, $return, $order = null) {
    $conn = connectToDb();
    $query = "";
	if(isset($filter)){
		$query = "WHERE " . build_where_conditions($filter, null, sql_escaper($conn));
	}
	$orderClause = build_order_clause($order, array('name', 'manufacturer', 'created'));
	// Break ties on id so that the order is stable when sorting by a non-unique column.
	if ($orderClause !== '') {
		$orderClause .= ', `id` DESC';
	}
	// manufacturer_name is looked up so that the admin can show names next to the ids. It is a
	// scalar subquery, so it never changes the number of rows returned.
	$query = "SELECT products.*,"
		. " (SELECT `name` FROM `manufacturers` WHERE `id` = products.`manufacturer` LIMIT 1) AS `manufacturer_name`"
		. " FROM products $query $orderClause";

	$result = mysqli_query($conn, $query);
	$num = mysqli_num_rows($result);
	$response = array();
	for ($i = 0; $i < $num; $i++) {
        $productId = mysqli_result($result,$i,'id');
		$product = (object) array();
		foreach ($return as $key=>$metric){
            if ($metric === 'variations') {
                $variationsQuery = sprintf("SELECT * FROM variations WHERE productId = %d ORDER BY sortIndex", (int) $productId);
            	$variationsResult = mysqli_query($conn, $variationsQuery);
            	$variationsNum = mysqli_num_rows($variationsResult);
                $variations = array();
                for($variationIndex = 0; $variationIndex < $variationsNum; $variationIndex++) {
                    $variation = (object) array();
                    $variation->id = mysqli_result($variationsResult,$variationIndex,'id');
                    $variation->name = mysqli_result($variationsResult,$variationIndex,'name');
                    $variation->svg = mysqli_result($variationsResult,$variationIndex,'svg');
                    $variation->sortIndex = intval(mysqli_result($variationsResult,$variationIndex,'sortIndex'));
                    array_push($variations, $variation);
                }
                $product->variations = $variations;
                continue;
            }
			$product->$metric = mysqli_result($result,$i,$metric);
			if ($metric === 'created') {
				$product->$metric = date("m/d/Y", strtotime($product->$metric));
			}
		}
		array_push($response, $product);
	}
    mysqli_close($conn);
	return JSON_encode($response);
}

function deleteProduct($id) {
    $conn = connectToDb();
    $response = (object) array(
		'valid' => true,
		'message' => ''
	);

    $query = sprintf("DELETE FROM products WHERE id = '%s'",
        mysqli_real_escape_string($conn, $id));

    if (!mysqli_query($conn, $query)) {
        $response->valid = false;
        $response->message = 'Unable to delete product';
    }

    // Delete relevant variations
    $query = sprintf("DELETE FROM variations WHERE productId = '%s'",
        mysqli_real_escape_string($conn, $id));

    if (!mysqli_query($conn, $query)) {
        $response->valid = false;
        $response->message = 'Unable to delete variations';
    }

    mysqli_close($conn);
    return json_encode($response);
}

function createProduct($postData) {
    $conn = connectToDb();
    $response = (object) array(
		'valid' => true,
		'message' => ''
	);

    $name = $postData['name'];
    $manufacturer = $postData['manufacturer'];
    $url = isset($postData['url']) ? $postData['url'] : '';
    $colors = $postData['colors'];
    $variations = json_decode($postData['variations']);
    $notes = isset($postData['notes']) ? $postData['notes'] : '[""]';
    $embed = isset($postData['embed']) ? $postData['embed'] : '';

    // Create the product
    $sql = sprintf("INSERT INTO products (status,name,manufacturer,created,url,colors) value (0,'%s','%s',now(),'%s','%s')",
    mysqli_real_escape_string($conn, $name), mysqli_real_escape_string($conn, $manufacturer), mysqli_real_escape_string($conn, $url), mysqli_real_escape_string($conn, $colors));


    if (!mysqli_query($conn, $sql)) {
        $response->valid = false;
        $response->message = 'Unable to create product';
        return json_encode($response);
    }

    // Create the variations
    $productId = mysqli_insert_id($conn);
    foreach($variations as $index=>$variation) {
        $sql = sprintf("INSERT INTO variations (name,svg,productId,sortIndex) value ('%s','%s','%s','%s')",
        mysqli_real_escape_string($conn, $variation->name), mysqli_real_escape_string($conn, $variation->svg)
        , mysqli_real_escape_string($conn, $productId), mysqli_real_escape_string($conn, $index));

        if (!mysqli_query($conn, $sql)) {
            $response->warning = 'The product was created, but the creation of some variations may have failed.';
        }
    }

    mysqli_close($conn);
    // Return success
    return json_encode($response);
}

function updateProduct($postData) {
    $conn = connectToDb();
    $response = (object) array(
		'valid' => true,
		'message' => ''
	);
    $id = $postData['id'];
    $variations = json_decode($postData['variations']);
	$vars = array(
		'status' => $postData['status'],
		'name' => $postData['name'],
		'manufacturer' => $postData['manufacturer'],
		'url' => $postData['url'],
		'colors' => $postData['colors'],
		'notes' => $postData['notes'],
		'embed' => $postData['embed']
	);

	foreach($vars as $metric => $val){
		$query = sprintf("UPDATE products SET $metric = '%s' WHERE id = '%s'",
			mysqli_real_escape_string($conn, $val), mysqli_real_escape_string($conn, $id));

		if (!mysqli_query($conn, $query)) {
			$response->valid = false;
			$response->message = 'Unable to change ' . $metric;
		}
	}

    // Update variations
    $touchedIds = array();
    $storedIds = array();
    $query = sprintf("SELECT id FROM variations WHERE productId = '%s'", mysqli_real_escape_string($conn, $id));
    $result = mysqli_query($conn, $query);
    $num = mysqli_num_rows($result);
    for ($i = 0; $i < $num; $i++) {
        $variationId = mysqli_result($result,$i,'id');
        array_push($storedIds, $variationId);
    }
    foreach($variations as $index=>$variation) {
        // Update existing variation
        if (isset($variation->id)) {
            $variationId = $variation->id;
            array_push($touchedIds, $variationId);
            $sql = sprintf("UPDATE variations SET name = '%s', svg = '%s', sortIndex = '%s' WHERE id = '%s'", mysqli_real_escape_string($conn, $variation->name), mysqli_real_escape_string($conn, $variation->svg), mysqli_real_escape_string($conn, $index), mysqli_real_escape_string($conn, $variationId));

            if (!mysqli_query($conn, $sql)) {
    			$response->valid = false;
    			$response->message = 'Unable to update variation ' . $variationId;
    		}
        } else {
            // Create new variation
            $sql = sprintf("INSERT INTO variations (name,svg,productId,sortIndex) value ('%s','%s','%s','%s')", mysqli_real_escape_string($conn, $variation->name), mysqli_real_escape_string($conn, $variation->svg), mysqli_real_escape_string($conn, $id), mysqli_real_escape_string($conn, $index));
            if (!mysqli_query($conn, $sql)) {
    			$response->valid = false;
    			$response->message = 'Failed to add variation ' . $variation->name;
    		}
        }
    }
    // Delete unmentioned variations
    foreach ($storedIds as $storedId) {
        if (in_array($storedId, $touchedIds)) {
            continue;
        }
        $sql = sprintf("DELETE from variations WHERE id = '%s'", mysqli_real_escape_string($conn, $storedId));
        if (!mysqli_query($conn, $sql)) {
            $response->valid = false;
            $response->message = 'Failed to remove variation ' . $storedId;
        }
    }

    mysqli_close($conn);
	return json_encode($response);
}

if ($_GET){
    $filter = isset($_GET['filter']) ? $_GET['filter'] : null;
    $order = isset($_GET['order']) ? $_GET['order'] : null;
    echo getProducts($filter, $_GET['return'], $order);
    return;
}
if ($_POST) {
	$response = (object) array(
		'valid' => true,
		'message' => ''
	);

	//Delete
	if (isset($_POST['delete'])) {
		echo deleteProduct($_POST['id']);
        return;
	}

	//Create
	if (isset($_POST['new'])) {
        echo createProduct($_POST);
        return;
	}

	//Update
    echo updateProduct($_POST);
    return;
}

echo 'No GET or POST variables';
