<?php

##### Design image functions #####

// Largest design image accepted, in bytes.
const KP_MAX_DESIGN_IMAGE_BYTES = 5242880; // 5 MB

// True only for PNG data under the size cap.
function is_valid_design_png($data) {
    if (!is_string($data) || $data === '' || strlen($data) > KP_MAX_DESIGN_IMAGE_BYTES) {
        return false;
    }
    $info = @getimagesizefromstring($data);
    return is_array($info) && $info[2] === IMAGETYPE_PNG;
}

// Decodes the {type: base64 PNG} JSON sent by the design editor. Nothing is written here, so a bad
// image rejects the whole request before the design is changed.
// Returns ['valid' => true, 'images' => [type => binary]] or ['valid' => false, 'message' => string].
function prepare_design_images($imagesJson) {
    $invalid = array('valid' => false, 'message' => 'Unable to save the image: each image must be a PNG under 5 MB');

    if (!is_string($imagesJson)) {
        return $invalid;
    }
    $decoded = json_decode($imagesJson, true);
    if (!is_array($decoded)) {
        return $invalid;
    }

    $prepared = array();
    foreach ($decoded as $type => $image) {
        if (!is_string($image)) {
            return $invalid;
        }
        $image = str_replace('data:image/png;base64,', '', $image);
        $image = str_replace(' ', '+', $image);
        $data = base64_decode($image);
        if (!is_valid_design_png($data)) {
            return $invalid;
        }
        $prepared[$type] = $data;
    }
    return array('valid' => true, 'images' => $prepared);
}

// Writes prepared images as {designId}{type}.png and returns [type => path] for the design row.
// Returns false if any write fails.
function write_design_images($designId, array $prepared, $uploadDir) {
    $paths = array();
    foreach ($prepared as $type => $data) {
        $file = $uploadDir . (int) $designId . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $type) . '.png';
        if (file_put_contents($file, $data) === false) {
            return false;
        }
        $paths[$type] = str_replace('..', '', $file);
    }
    return $paths;
}
