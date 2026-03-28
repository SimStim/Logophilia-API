<?php

declare(strict_types=1);

namespace App\Middleware;

class AuthMiddleware
{
    private array $validKeys = [];

    public function __construct()
    {
        $this->validKeys[] = $_ENV['HTTP_X_API_KEY'];
    }

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
