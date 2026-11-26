<?php
require_once "header.php";

// Lists the distinct colors used by public designs, so the designs page can show the color swatches.
// Optionally narrowed by the same manufacturer and product IDs that designs.php accepts. Only active
// public designs of public products count. Responds with a sorted JSON array of "#rrggbb" strings.
$conn = connectToDb();
$escape = sql_escaper($conn);

// The products a design may belong to: public products, and of the selected manufacturers if any.
$productConditions = array("status = 2");
if (isset($_GET['manufacturers']) && $_GET['manufacturers'] !== array()) {
    $productConditions[] = build_in_clause('manufacturer', $_GET['manufacturers'], $escape);
}

$conditions = array(
    "d.active = 1",
    "d.status = 2",
    "d.product IN (SELECT id FROM products WHERE " . implode(' AND ', $productConditions) . ")"
);
if (isset($_GET['products']) && $_GET['products'] !== array()) {
    $conditions[] = build_in_clause('product', $_GET['products'], $escape);
}

$query = "SELECT DISTINCT dc.color FROM design_colors dc"
    . " JOIN designs d ON d.id = dc.design_id"
    . " WHERE " . implode(' AND ', $conditions)
    . " ORDER BY dc.color";

$result = mysqli_query($conn, $query);
if ($result === false) {
    throw new RuntimeException('Unable to query design colors: ' . mysqli_error($conn));
}
$colors = array();
while ($row = mysqli_fetch_row($result)) {
    $colors[] = $row[0];
}
mysqli_close($conn);

echo json_encode(array_values($colors));
return;
