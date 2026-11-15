<?php

declare(strict_types=1);

// Hard platform check: PHP >= 8.5, enforced on every request.
if (PHP_VERSION_ID < 80500) {
    http_response_code(response_code: 500);
    exit("PHP 8.5 or newer is required.");
}

// Class loading without composer: every class is required explicitly.
require_once __DIR__ . "/../src/Controllers/APIControllers.php";
require_once __DIR__ . "/../src/Middleware/AuthMiddleware.php";
require_once __DIR__ . "/../src/Middleware/CorsMiddleware.php";
require_once __DIR__ . "/../src/Middleware/RateLimitMiddleware.php";
require_once __DIR__ . "/../src/Models/APIModels.php";
define(constant_name: "DOWNLOADS", value: realpath(path: __DIR__ . "/../downloads/") . "/");
define(constant_name: "UPLOADS", value: realpath(path: __DIR__ . "/../uploads/") . "/");
const RATELIMITS = __DIR__ . "/../ratelimits/";

use App\Controllers\APIControllers;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\RateLimitMiddleware;

// The single secret is read directly from .env (INI format, no packages).
// It goes into $_ENV only, never into $_SERVER, so it can never collide with
// the HTTP_X_API_KEY header slot that AuthMiddleware reads the provided key from.
$env = parse_ini_file(filename: __DIR__ . "/../.env") ?: [];
if (!empty($env["HTTP_X_API_KEY"])) {
    $_ENV["HTTP_X_API_KEY"] = $env["HTTP_X_API_KEY"];
}

try {
    new CorsMiddleware()->handle();
    new AuthMiddleware()->handle();
    new RateLimitMiddleware()->handle();
} catch (Exception $e) {
    exit($e->getMessage());
}

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$route = explode(separator: '?', string: $requestUri)[0];

switch ($route) {
    case "/":
        return APIControllers::processGreeting($method);
    case "/freeloot":
        return APIControllers::processFreeLoot($method);
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
