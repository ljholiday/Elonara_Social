<?php
declare(strict_types=1);

// Dev-only error display; remove or disable in production.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/../src/bootstrap.php';

use App\Http\Router;

/** @var \App\Http\Request $request */
$request = vt_service('http.request');

// Normalize: remove trailing slash (except root)
$path = $request->path();
if ($path !== '/' && str_ends_with($path, '/')) {
    $normalizedPath = rtrim($path, '/');
    header('Location: ' . $normalizedPath, true, 301);
    exit;
}

// Initialize router and load routes
$router = new Router();
$registerRoutes = require __DIR__ . '/../src/Http/routes.php';
$registerRoutes($router);

// Dispatch request
$result = $router->dispatch($request);

// If no route matched, show 404
if ($result === null && headers_sent() === false) {
    http_response_code(404);
    echo 'Not Found';
}
