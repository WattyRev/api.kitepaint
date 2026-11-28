<?php

##### Design color functions #####
# A design's colors are the fills of its colorable panels (elements with a data-id attribute). Each
# distinct color is stored as one row in design_colors, as a normalized hex string such as "#ff0000".
# The table is created by hand (see the plan), so these functions assume it already exists.

// Returns "#rrggbb" (lowercase, 3-digit form expanded) or null when the value is not a hex color.
function normalize_hex_color($value) {
    if (!is_string($value)) {
        return null;
    }
    $value = strtolower(trim($value));
    if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/D', $value, $matches)) {
        return '#' . $matches[1] . $matches[1] . $matches[2] . $matches[2] . $matches[3] . $matches[3];
    }
    if (preg_match('/^#[0-9a-f]{6}$/D', $value)) {
        return $value;
    }
    return null;
}

// Returns the normalized colors of the data-id panels in one SVG string. Unparseable SVG yields none.
function extract_svg_panel_colors($svg) {
    if (!is_string($svg) || $svg === '') {
        return array();
    }
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadXML($svg, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return array();
    }

    $xpath = new DOMXPath($document);
    $panels = $xpath->query('//*[@data-id]');
    $colors = array();
    foreach ($panels as $panel) {
        $color = normalize_hex_color($panel->getAttribute('fill'));
        if ($color !== null) {
            $colors[] = $color;
        }
    }
    return $colors;
}

// Takes a design's variations JSON (an array of {id, name, svg, primary}) and returns the distinct
// colors used across all of its variations. Invalid JSON yields none.
function extract_design_colors($variationsJson) {
    if (!is_string($variationsJson)) {
        return array();
    }
    $variations = json_decode($variationsJson, true);
    if (!is_array($variations)) {
        return array();
    }

    $seen = array();
    foreach ($variations as $variation) {
        if (!is_array($variation) || !isset($variation['svg'])) {
            continue;
        }
        foreach (extract_svg_panel_colors($variation['svg']) as $color) {
            $seen[$color] = true;
        }
    }
    return array_keys($seen);
}

// Replaces the stored colors of one design with the colors in its variations JSON. Runs in a
// transaction so a failure leaves the previous rows in place. Returns false on failure.
function sync_design_colors($conn, $designId, $variationsJson) {
    $designId = (int) $designId;
    $colors = extract_design_colors($variationsJson);

    mysqli_begin_transaction($conn);
    $ok = mysqli_query($conn, "DELETE FROM design_colors WHERE design_id = $designId");
    if ($ok && count($colors) > 0) {
        $values = array();
        foreach ($colors as $color) {
            $values[] = "($designId, '" . mysqli_real_escape_string($conn, $color) . "')";
        }
        $ok = mysqli_query($conn, 'INSERT INTO design_colors (design_id, color) VALUES ' . implode(', ', $values));
    }
    if (!$ok) {
        mysqli_rollback($conn);
        return false;
    }
    return mysqli_commit($conn);
}

// Removes every stored color for one design. Used when a design is hard deleted.
function delete_design_colors($conn, $designId) {
    $designId = (int) $designId;
    return mysqli_query($conn, "DELETE FROM design_colors WHERE design_id = $designId") !== false;
}

?>
