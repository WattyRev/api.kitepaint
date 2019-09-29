<?php
/**
 * Temporary endpoint for doing a data upgrade on Designs.
 */
require_once "header.php";

function updateDesigns($skip, $limit, $variationsByProduct) {
    echo "<p>Starting batch of $limit, starting at $skip</p>";

    $query = sprintf("SELECT id,variations,product FROM designs ORDER BY id LIMIT $skip, $limit");
    $result = mysql_query($query);
    $num = mysql_num_rows($result);
    for ($i = 0; $i < $num; $i++) {
        $id = mysql_result($result,$i,"id");
        $productId = mysql_result($result,$i,"product");
        echo "<p>Design $id - Processing design</p>";
        $variations = json_decode(mysql_result($result,$i,"variations"));

        foreach($variations as $variation) {
            $variationName = $variation->name;
            $productVariation = $variationsByProduct->$productId->$variationName;
            $variation->id = $productVariation->id;
            $variation->sortIndex = $productVariation->sortIndex;
            $variation->productId = $productVariation->productId;
        }

        $variationsJson = json_encode($variations);
        $sql = sprintf("UPDATE designs SET variations = '%s' WHERE id = $id", mysql_real_escape_string($variationsJson));
        if (!mysql_query($sql)) {
            echo "<p>Design $id - Failed to update design</p>";
            echo "<p>" . mysql_error() . "</p>";
            return;
        };
        echo "<p>Design $id - Updated design</p>";
    }
    if ($num === $limit) {
        updateDesigns($skip + $limit, $limit, $variationsByProduct);
    } else {
        echo "<p>No more records to update.</p>";
    }
}

function getVariationsByProduct() {
    $query = sprintf("SELECT id,name,sortIndex,productId FROM variations");
    $variationsByProduct = (object) array();
    $result = mysql_query($query);
    $num = mysql_num_rows($result);
    for ($i = 0; $i < $num; $i++) {
        $productId = mysql_result($result,$i,"productId");
        $variationName = mysql_result($result,$i,"name");
        $variation = (object) array();
        $variation->id = mysql_result($result,$i,"id");
        $variation->name = $variationName;
        $variation->sortIndex = mysql_result($result,$i,"sortIndex");
        $variation->productId = $productId;

        if (!isset($variationsByProduct->$productId)) {
            $variationsByProduct->$productId = (object) array();
        }
        $variationsByProduct->$productId->$variationName = $variation;
    }
    return $variationsByProduct;
}

updateDesigns(0, 10, getVariationsByProduct());
 ?>
