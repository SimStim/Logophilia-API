<?php

declare(strict_types=1);

namespace App\Middleware;

use Exception;

class CorsMiddleware
{
    private array $allowedOrigins = [
        "http://localhost:1313",
        "https://refresh.usha-ludwig.vip",
        'https://logophilia.eu',
        'https://www.logophilia.eu'
    ];

    /**
     * Handle CORS requests
     * @param bool $testing
     * @return void
     * @throws Exception
     */
    public function handle(bool $testing = false): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $isAllowedOrigin = $origin && in_array($origin, $this->allowedOrigins);
        // CORS check
        if ($isAllowedOrigin) {
            header(header: "Access-Control-Allow-Origin: $origin");
            header(header: "Access-Control-Allow-Methods: GET, POST, OPTIONS");
            header(header: "Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Api-key");
        } elseif ($origin) {
            // If Origin is present but not allowed, deny access
            http_response_code(response_code: 403);
            echo json_encode([
                'status' => 'error',
                'message' => strtoupper(string: 'Access forbidden for invalid origin.')
            ]);
            if ($testing) throw new Exception(message: 'EXIT');
            exit;
        }
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(response_code: 204);
            if ($testing) throw new Exception(message: 'EXIT');
            exit;
        }
        // Referrer check (for direct access or from other sites)
        // If it's not a CORS request (no Origin), we check Referer
        if (!$origin) {
            $refererHost = parse_url(url: $referer, component: PHP_URL_HOST);
            $isAllowedReferer = false;
            foreach ($this->allowedOrigins as $allowedOrigin) {
                if ($refererHost !== parse_url(url: $allowedOrigin, component: PHP_URL_HOST)) continue;
                $isAllowedReferer = true;
            }
            if (!$isAllowedReferer) {
                http_response_code(response_code: 403);
                echo json_encode([
                    'status' => 'error',
                    'message' => strtoupper(string: 'Access forbidden for invalid referrer.')
                ]);
                if ($testing) throw new Exception(message: 'EXIT');
                exit;
            }
        }
    }
}
