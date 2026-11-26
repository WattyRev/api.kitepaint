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

// Returns the ratio of colored panels to data-id panels in one SVG string. A panel counts as colored
// when its fill is a hex color other than white. Unparseable SVG or an SVG without panels yields 0.
function svg_colored_panel_ratio($svg) {
    if (!is_string($svg) || $svg === '') {
        return 0.0;
    }
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadXML($svg, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return 0.0;
    }

    $xpath = new DOMXPath($document);
    $panels = $xpath->query('//*[@data-id]');
    if ($panels->length === 0) {
        return 0.0;
    }
    $colored = 0;
    foreach ($panels as $panel) {
        $color = normalize_hex_color($panel->getAttribute('fill'));
        if ($color !== null && $color !== '#ffffff') {
            $colored++;
        }
    }
    return $colored / $panels->length;
}

// Returns the index of the variation with the highest ratio of colored panels (ties go to the
// earliest), mirroring the editor. Returns null when no variation has a colored panel.
function pick_primary_variation_index($variations) {
    if (!is_array($variations)) {
        return null;
    }
    $bestIndex = null;
    $bestRatio = 0.0;
    $index = 0;
    foreach ($variations as $variation) {
        if (is_array($variation) && isset($variation['svg'])) {
            $ratio = svg_colored_panel_ratio($variation['svg']);
            if ($ratio > $bestRatio) {
                $bestRatio = $ratio;
                $bestIndex = $index;
            }
        }
        $index++;
    }
    return $bestIndex;
}

// Takes a design's variations JSON and returns it with exactly the most colored variation marked as
// primary. Returns null when nothing needs to change: invalid JSON, no colored variation, or the
// right variation is already the only primary.
function set_primary_variation($variationsJson) {
    if (!is_string($variationsJson)) {
        return null;
    }
    $variations = json_decode($variationsJson, true);
    if (!is_array($variations)) {
        return null;
    }
    $primaryIndex = pick_primary_variation_index($variations);
    if ($primaryIndex === null) {
        return null;
    }

    $changed = false;
    $index = 0;
    foreach ($variations as &$variation) {
        if (is_array($variation)) {
            $shouldBePrimary = $index === $primaryIndex;
            if (!isset($variation['primary']) || $variation['primary'] !== $shouldBePrimary) {
                $variation['primary'] = $shouldBePrimary;
                $changed = true;
            }
        }
        $index++;
    }
    unset($variation);

    if (!$changed) {
        return null;
    }
    $encoded = json_encode($variations, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $encoded === false ? null : $encoded;
}

// Removes every stored color for one design. Used when a design is hard deleted.
function delete_design_colors($conn, $designId) {
    $designId = (int) $designId;
    return mysqli_query($conn, "DELETE FROM design_colors WHERE design_id = $designId") !== false;
}

?>
