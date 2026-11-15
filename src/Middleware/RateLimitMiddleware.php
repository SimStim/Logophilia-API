<?php

declare(strict_types=1);

namespace App\Middleware;

use Exception;

class RateLimitMiddleware
{
    private const int WINDOW_SECONDS = 3600;
    private const int MAX_REQUESTS = 4;

    /**
     * Limit POST requests per client IP and route (fixed window, file based).
     * @param bool $testing
     * @return void
     * @throws Exception
     */
    public function handle(bool $testing = false): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip === '') return;
        $route = explode(separator: '?', string: $_SERVER['REQUEST_URI'] ?? '')[0];
        if (!is_dir(RATELIMITS))
            mkdir(RATELIMITS, 0755, true);
        $counterFile = RATELIMITS . md5(string: $ip . ':' . $route);
        $now = time();
        // Fail open: an unwritable directory must never take the API down.
        if (($handle = fopen(filename: $counterFile, mode: 'c+')) === false) return;
        flock(stream: $handle, operation: LOCK_EX);
        $parts = sscanf(trim((string) stream_get_contents(stream: $handle)), '%d %d') ?: [];
        $windowStart = $parts[0] ?? 0;
        $count = $parts[1] ?? 0;
        if ($now - $windowStart >= self::WINDOW_SECONDS) {
            $windowStart = $now;
            $count = 0;
        }
        if ($count >= self::MAX_REQUESTS) {
            header(header: 'Retry-After: ' . ($windowStart + self::WINDOW_SECONDS - $now));
            http_response_code(response_code: 429);
            echo json_encode([
                'status' => 'error',
                'message' => strtoupper(string: 'Too many requests. Please try again later.')
            ]);
            flock(stream: $handle, operation: LOCK_UN);
            fclose($handle);
            if ($testing) throw new Exception(message: 'EXIT');
            exit;
        }
        $count++;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, data: "{$windowStart} {$count}");
        fflush($handle);
        flock(stream: $handle, operation: LOCK_UN);
        fclose($handle);
    }
}
