<?php
require_once "header.php";

// Fills in design_colors for designs saved before colors were recorded on save. Each POST processes
// one batch of designs with an id greater than afterId, in ascending id order, and responds with the
// cursor for the next batch and how many designs remain. Re-running is safe: each design's rows are
// replaced. Requires an admin session (checked by header.php).
const KP_COLOR_BACKFILL_BATCH_SIZE = 50;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('valid' => false, 'message' => 'POST required'));
    return;
}

$conn = connectToDb();
$afterId = parse_non_negative_int(isset($_POST['afterId']) ? $_POST['afterId'] : '0', 'afterId');

$query = sprintf("SELECT id, variations FROM designs WHERE id > %d ORDER BY id ASC LIMIT %d",
    $afterId, KP_COLOR_BACKFILL_BATCH_SIZE);
$result = mysqli_query($conn, $query);
if ($result === false) {
    throw new RuntimeException('Unable to read designs: ' . mysqli_error($conn));
}

$processed = 0;
$nextAfterId = $afterId;
while ($row = mysqli_fetch_assoc($result)) {
    if (!sync_design_colors($conn, $row['id'], $row['variations'])) {
        // Stop here so that a re-run resumes from the last design that was saved.
        http_response_code(500);
        echo json_encode(array(
            'valid' => false,
            'message' => 'Unable to save colors for design ' . $row['id'],
            'processed' => $processed,
            'nextAfterId' => $nextAfterId
        ));
        mysqli_close($conn);
        return;
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
    'nextAfterId' => $nextAfterId,
    'remaining' => $remaining
));
return;
