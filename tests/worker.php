<?php

declare(strict_types=1);

use App\Middleware\RateLimitMiddleware;
use App\Models\APIModels;

require_once __DIR__ . '/../src/Middleware/RateLimitMiddleware.php';
require_once __DIR__ . '/../src/Models/APIModels.php';

// Test fixture workers run in their own PHP process, spawned by tests/run.php.
// The scratch directory must match the one used there.
$base = sys_get_temp_dir() . '/logophilia-tests';
define('DOWNLOADS', $base . '/downloads/');
define('UPLOADS', $base . '/uploads/');
define('RATELIMITS', $base . '/ratelimit/');

$mode = $argv[1] ?? '';
switch ($mode) {
    case 'download':
        echo APIModels::sendFile($argv[2] ?? '') ? 'SENT' : 'BLOCKED';
        break;
    case 'list':
        APIModels::sendFileList();
        break;
    case 'limit':
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $argv[2] ?? '/contact';
        $_SERVER['REMOTE_ADDR'] = $argv[3] ?? '203.0.113.7';
        try {
            (new RateLimitMiddleware())->handle(testing: true);
            echo 'PASS';
        } catch (Throwable) {
            echo 'LIMITED';
        }
        break;
}
