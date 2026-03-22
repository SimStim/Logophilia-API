<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Middleware\CorsMiddleware;

/**
try {
    new CorsMiddleware()->handle();
} catch (Exception $e) {
    exit($e->getMessage());
}
*/

header(header: "Content-Type: application/json; charset=UTF-8");

$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Simple router
if ($requestUri === '/' || $requestUri === '/index.php') {
    echo json_encode([
        'message' => 'Welcome to Logophilia API',
        'status' => 'success'
    ]);
} else {
    http_response_code(response_code: 404);
    echo json_encode([
        'message' => 'Not Found',
        'status' => 'error'
    ]);
}
