<?php
// public/router.php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $path;

// If a real static file exists, let the server serve it.
if ($path !== '/' && is_file($file)) {
    return false;
}

// Otherwise, hand everything to the front controller.
require __DIR__ . '/index.php';

