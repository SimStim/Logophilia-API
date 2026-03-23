<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
define(constant_name: "DOWNLOADS", value: realpath(path: __DIR__ . "/../downloads/") . "/");
define(constant_name: "UPLOADS", value: realpath(path: __DIR__ . "/../uploads/") . "/");

use App\Controllers\APIControllers;
use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;

try {
    new CorsMiddleware()->handle();
} catch (Exception $e) {
    exit($e->getMessage());
}


try {
    new AuthMiddleware()->handle();
} catch (Exception $e) {
    exit($e->getMessage());
}

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$route = explode(separator: '?', string: $requestUri)[0];

switch ($route) {
    case "/":
        APIControllers::processGreeting($method);
        break;
    case "/download":
        APIControllers::processDownload($method);
        break;
    case "/contact":
        APIControllers::processContact($method);
        break;
    case "/newsletter":
        APIControllers::processNewsletter($method);
        break;
    case "/submission":
        APIControllers::processSubmission($method);
        break;
    default:
        http_response_code(response_code: 404);
        echo json_encode([
            'message' => strtoupper(string: 'Route not defined.'),
            'status' => 'error'
        ]);
}
http_response_code(response_code: 234);
echo json_encode([
    "ROUTE" => $route,
]);
