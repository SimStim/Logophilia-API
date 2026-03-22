<?php

declare(strict_types=1);

namespace App\Middleware;

class AuthMiddleware
{
    private array $validKeys = [
        '247e5c56d2619ee9d29c4c56d69cacf917b49a572696ea60ba742d365b983112',
    ];

    /**
     * Handle the authentication request
     * @return void
     */
    public function handle(): void
    {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if ($apiKey === null) {
            http_response_code(response_code: 401);
            echo json_encode([
                'status' => 'error',
                'message' => strtoupper('Unauthorized: API Key is missing.')
            ]);
            exit;
        }
        if (!in_array($apiKey, $this->validKeys)) {
            http_response_code(response_code: 403);
            echo json_encode([
                'status' => 'error',
                'message' => strtoupper('Forbidden: Invalid API Key.')
            ]);
            exit;
        }
    }
}
