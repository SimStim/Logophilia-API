<?php

declare(strict_types=1);

namespace App\Middleware;

use Exception;

class AuthMiddleware
{
    private array $validKeys {
        get => $this->validKeys = array(
            $_ENV["HTTP_X_API_KEY"],
        );
    }

    /**
     * Handle the authentication request
     * @param bool $testing
     * @return void
     * @throws Exception
     */
    public function handle(bool $testing = false): void
    {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
        // Empty string counts as missing: it must never match an unset or empty expected key.
        if ($apiKey === null || $apiKey === '') {
            http_response_code(response_code: 401);
            echo json_encode([
                'status' => 'error',
                'message' => strtoupper(string: 'Unauthorized: API Key is missing.')
            ]);
            if ($testing) throw new Exception(message: 'EXIT');
            exit;
        }
        // Strict comparison: the expected key from .env must match exactly, or fail closed.
        if (!in_array($apiKey, $this->validKeys, true)) {
            http_response_code(response_code: 403);
            echo json_encode([
                'status' => 'error',
                'message' => strtoupper(string: 'Forbidden: Invalid API Key.')
            ]);
            if ($testing) throw new Exception(message: 'EXIT');
            exit;
        }
    }
}
