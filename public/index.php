<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
define(constant_name: "REPO", value: realpath(path: __DIR__ . "/../.."));

use App\Controllers\APIControllers;
use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;

header(header: "Content-Type: application/json; charset=UTF-8");
echo json_encode(__DIR__);
echo json_encode(REPO);
exit;

/**
 * try {
 * new CorsMiddleware()->handle();
 * } catch (Exception $e) {
 * exit($e->getMessage());
 * }
 */

try {
    new AuthMiddleware()->handle();
} catch (Exception $e) {
    exit($e->getMessage());
}

$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($requestUri, ["/", "/download", "/contact", "/newsletter", "/submission"])) {
    http_response_code(response_code: 404);
    echo json_encode([
        'message' => strtoupper(string: 'Route not defined.'),
        'status' => 'error'
    ]);
    exit;
}

switch ($requestUri) {
    case "/":
        APIControllers::processGreeting($method);
        break;
    case "/download":
        APIControllers::processDownload($method);
        break;
    case "/contact":
        if ($method !== 'POST') {
            http_response_code(response_code: 405);
            echo json_encode([
                'message' => strtoupper(string: 'Method not permitted for this route.'),
                'status' => 'error'
            ]);
            exit;
        }
        header(header: "Content-Type: application/json; charset=UTF-8");
        echo json_encode([
            'message' => 'Message sent successfully.',
            'status' => 'success'
        ]);
        break;
    case "/newsletter":
        if ($method !== 'POST') {
            http_response_code(response_code: 405);
            echo json_encode([
                'message' => strtoupper(string: 'Method not permitted for this route.'),
                'status' => 'error'
            ]);
            exit;
        }
        header(header: "Content-Type: application/json; charset=UTF-8");
        echo json_encode([
            'message' => 'Newsletter signup successful.',
            'status' => 'success'
        ]);
        break;
    case "/submission":
        if ($method !== 'POST') {
            http_response_code(response_code: 405);
            echo json_encode([
                'message' => strtoupper(string: 'Method not permitted for this route.'),
                'status' => 'error'
            ]);
            exit;
        }
        header(header: "Content-Type: application/json; charset=UTF-8");
        echo json_encode([
            'message' => 'Manuscript submission processed successfully.',
            'status' => 'success'
        ]);
        break;
}
