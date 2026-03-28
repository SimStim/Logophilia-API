<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
define(constant_name: "DOWNLOADS", value: realpath(path: __DIR__ . "/../downloads/") . "/");
define(constant_name: "UPLOADS", value: realpath(path: __DIR__ . "/../uploads/") . "/");

use App\Controllers\APIControllers;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

try {
    new CorsMiddleware()->handle();
    new AuthMiddleware()->handle();
} catch (Exception $e) {
    exit($e->getMessage());
}

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$route = explode(separator: '?', string: $requestUri)[0];

switch ($route) {
    case "/":
        return APIControllers::processGreeting($method);
    case "/download":
        return APIControllers::processDownload($method);
    case "/contact":
        return APIControllers::processContact($method);
    case "/newsletter":
        return APIControllers::processNewsletter($method);
    case "/submission":
        return APIControllers::processSubmission($method);
    default:
        http_response_code(response_code: 404);
        echo json_encode([
            'message' => strtoupper(string: 'Route not defined.'),
            'status' => 'error'
        ]);
        return false;
}
