<?php
require_once "header.php";

// Re-picks the primary variation (the one shown as the design's thumbnail) of existing designs so it
// is a colored variation rather than a blank one. Each POST processes one batch of designs with an id
// greater than afterId, in ascending id order, and responds with the cursor for the next batch, how
// many designs were changed, and how many remain. Only the variations column is written (the updated
// timestamp is left alone). Re-running is safe. Requires an admin session (checked by header.php).
const KP_PRIMARY_BACKFILL_BATCH_SIZE = 50;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('valid' => false, 'message' => 'POST required'));
    return;
}

$conn = connectToDb();
$afterId = parse_non_negative_int(isset($_POST['afterId']) ? $_POST['afterId'] : '0', 'afterId');

$query = sprintf("SELECT id, variations FROM designs WHERE id > %d ORDER BY id ASC LIMIT %d",
    $afterId, KP_PRIMARY_BACKFILL_BATCH_SIZE);
$result = mysqli_query($conn, $query);
if ($result === false) {
    throw new RuntimeException('Unable to read designs: ' . mysqli_error($conn));
}

$processed = 0;
$changed = 0;
$nextAfterId = $afterId;
while ($row = mysqli_fetch_assoc($result)) {
    $updatedVariations = set_primary_variation($row['variations']);
    if ($updatedVariations !== null) {
        $update = sprintf("UPDATE designs SET variations = '%s' WHERE id = %d",
            mysqli_real_escape_string($conn, $updatedVariations), (int) $row['id']);
        if (mysqli_query($conn, $update) === false) {
            // Stop here so that a re-run resumes from the last design that was saved.
            http_response_code(500);
            echo json_encode(array(
                'valid' => false,
                'message' => 'Unable to save the primary variation for design ' . $row['id'],
                'processed' => $processed,
                'changed' => $changed,
                'nextAfterId' => $nextAfterId
            ));
            mysqli_close($conn);
            return;
        }
        $changed++;
    }
    $processed++;
    $nextAfterId = (int) $row['id'];
}

$countResult = mysqli_query($conn, sprintf("SELECT COUNT(*) AS total FROM designs WHERE id > %d", $nextAfterId));
if ($countResult === false) {
    throw new RuntimeException('Unable to count designs: ' . mysqli_error($conn));
}
$remaining = (int) mysqli_result($countResult, 0, 'total');
mysqli_close($conn);

echo json_encode(array(
    'valid' => true,
    'processed' => $processed,
    'changed' => $changed,
    'nextAfterId' => $nextAfterId,
    'remaining' => $remaining
));
return;
