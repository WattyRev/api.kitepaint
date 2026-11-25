<?php

// Server-only file holding the database credentials. It lives outside the web root and is never
// committed to the repo. The file must return an array shaped like:
//   ['prod' => ['host' => ..., 'user' => ..., 'pass' => ..., 'db' => ...], 'beta' => [...]]
const KP_DB_CONFIG_PATH = '/home1/r3vfan/private/kitepaint_db.php';

// Returns the connection settings for one environment. Throws if the file is missing or any value is absent.
// Error messages never include the file path or any credential values.
function kp_load_db_config(string $path, bool $isProduction): array
{
    if (!is_readable($path)) {
        throw new RuntimeException('DB config file is missing or unreadable.');
    }

    $all = require $path;
    $env = $isProduction ? 'prod' : 'beta';

    if (!is_array($all) || !isset($all[$env]) || !is_array($all[$env])) {
        throw new RuntimeException("DB config has no '$env' section.");
    }

    $section = $all[$env];
    foreach (['host', 'user', 'pass', 'db'] as $key) {
        if (!isset($section[$key]) || !is_string($section[$key]) || $section[$key] === '') {
            throw new RuntimeException("DB config '$env' section is missing '$key'.");
        }
    }

    return [
        'host' => $section['host'],
        'user' => $section['user'],
        'pass' => $section['pass'],
        'db'   => $section['db'],
    ];
}
