<?php

namespace App\Controllers;

use App\Models\APIModels;

final class APIControllers
{
    private static function checkMethod(string $actualMethod, string $permittedMethod): bool
    {
        if ($actualMethod !== $permittedMethod) {
            http_response_code(response_code: 405);
            echo json_encode([
                'message' => strtoupper(string: 'Method not permitted for this route.'),
                'status' => 'error'
            ]);
            return false;
        }
        return true;
    }

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
        if (self::checkMethod(actualMethod: $method, permittedMethod: 'POST')) return false;
        $email = $_POST["email"] ?? "";
        $message = $_POST["message"] ?? "";
        header(header: "Content-Type: application/json; charset=UTF-8");
        echo json_encode([
            'message' => 'Message sent successfully.',
            'status' => 'success',
            "POST" => $_POST,
        ]);
        return true;
        $errors = [];
        if (!$email)
            $errors[] = 'A valid email address is required.';
        if (strlen($message) < 10)
            $errors[] = 'Message is required (minimum 10 characters).';
        if (!empty($errors)) {
            http_response_code(response_code: 422);
            echo json_encode([
                'message' => strtoupper(implode(separator: " / ", array: $errors)),
                'status' => 'error'
            ]);
            return false;
        }
        return APIModels::processContact(email: $email, message: $message);
    }

    public static function processNewsletter(string $method): bool
    {
        if (self::checkMethod(actualMethod: $method, permittedMethod: 'POST')) return false;
        $email = $_POST["email"] ?? "";
        if (!$email) {
            http_response_code(response_code: 422);
            echo json_encode([
                'message' => strtoupper(string: "A valid email address is required."),
                'status' => 'error'
            ]);
            return false;
        }
        return APIModels::processNewsletter(email: $email);
    }

    public static function processSubmission(string $method): bool
    {
        if (self::checkMethod(actualMethod: $method, permittedMethod: 'POST')) return false;
        return APIModels::processSubmission();
    }

    public static function processDownload(string $method): bool
    {
        if (self::checkMethod(actualMethod: $method, permittedMethod: 'GET')) return false;
        return APIModels::sendFile(fileName: $_GET['fileName'] ?? "");
    }
}
