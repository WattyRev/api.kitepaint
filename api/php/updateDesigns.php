<?php
/**
 * Temporary endpoint for doing a data upgrade on Designs.
 */
require_once "header.php";

function updateDesigns($skip, $limit, $variationsByProduct) {
    $query = sprintf("SELECT id,variations,product FROM designs ORDER BY id LIMIT $skip, $limit");
    $result = mysql_query($query);
    $num = mysql_num_rows($result);
    for ($i = 0; $i < $num; $i++) {
        $id = mysql_result($result,$i,"id");
        $productId = mysql_result($result,$i,"product");
        $variations = json_decode(mysql_result($result,$i,"variations"));

        foreach($variations as $variation) {
            $productVariation = $variationsByProduct->$productId->$variation->name;
            $variation->id = $productVariation->id;
            $variation->sortIndex = $productVariation->sortIndex;
            $variation->productId = $productVariation->productId;
        }

        var_dump($variations);
        echo "\n\n\n";
        $variationsJson = json_encode($variations);

        // $sql = sprintf("UPDATE designs SET variations = $variationsJson WHERE id = $id");
        // mysql_result($sql);
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

    var_dump($variationsByProduct);
    echo "\n\n\n";
    return $variationsByProduct;
}

updateDesigns(0, 10, getVariationsByProduct());
 ?>
