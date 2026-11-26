<?php

##### Rate limiting #####

// Server-only directory for counters. It sits next to the DB config file, outside the web root.
const KP_RATELIMIT_DIR = '/home1/r3vfan/private/ratelimit';

// The address of the client making the request. Forwarded headers are ignored because clients can set them.
function kp_client_ip() {
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
}

// Fixed-window counter. Returns true if this attempt is within $max attempts per $windowSeconds for the
// given action and key. Fails open (and logs) if the counter directory is unusable, so a storage problem
// can't lock every user out.
function rate_limit_allow($action, $key, $max, $windowSeconds, $now = null, $dir = KP_RATELIMIT_DIR) {
    if ($now === null) {
        $now = time();
    }

    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        error_log('KitePaint rate limit: counter directory is unavailable; allowing request.');
        return true;
    }

    $file = rtrim($dir, '/') . '/' . hash('sha256', $action . '|' . $key) . '.json';
    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        error_log('KitePaint rate limit: unable to open counter file; allowing request.');
        return true;
    }

    flock($handle, LOCK_EX);
    $state = json_decode((string) stream_get_contents($handle), true);
    if (!is_array($state) || !isset($state['start'], $state['count']) || $now - $state['start'] >= $windowSeconds) {
        $state = array('start' => $now, 'count' => 0);
    }
    $state['count']++;

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    // Clean up old counters now and then so the directory doesn't grow forever.
    if (random_int(1, 200) === 1) {
        kp_ratelimit_cleanup($dir, $now);
    }

    return $state['count'] <= $max;
}

// Deletes counter files that haven't been written to for a day.
function kp_ratelimit_cleanup($dir, $now, $maxAgeSeconds = 86400) {
    $files = glob(rtrim($dir, '/') . '/*.json');
    if ($files === false) {
        return;
    }
    foreach ($files as $file) {
        $modified = @filemtime($file);
        if ($modified !== false && $now - $modified > $maxAgeSeconds) {
            @unlink($file);
        }
    }
}
