<?php

namespace App\Controllers;

use App\Models\APIModels;

final class APIControllers
{
    public static function processGreeting(string $method): void
    {
        header(header: "Content-Type: application/json; charset=UTF-8");
        if ($method === 'GET') {
            echo json_encode([
                'message' => 'Welcome to Logophilia API.',
                'status' => 'success'
            ]);
        } else {
            echo json_encode([
                "message" => "This is Logophilia API, and you're very pushy.",
                "status" => "partial success"
            ]);
        }
    }

    public static function processContact(string $method): bool
    {
        return true;
    }

    public function processNewsletter(): bool
    {
        return true;
    }

    public function processSubmission(): bool
    {
        return true;
    }

    public static function processDownload(string $method): bool
    {
        if ($method !== 'GET') {
            http_response_code(response_code: 405);
            echo json_encode([
                'message' => strtoupper(string: 'Method not permitted for this route.'),
                'status' => 'error'
            ]);
            return false;
        }
        return APIModels::sendFile(filePath: $_GET['fileName'] ?? "");
    }
}
