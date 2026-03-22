<?php

declare(strict_types=1);

namespace App\Models;

class APIModels
{
    public function processContact(): bool
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

    public static function sendFile(string $fileName): bool
    {
        $filePath = REPO . $fileName;
        if (file_exists($filePath)) {
            header(header: "Content-Description: File Transfer");
            header(header: "Content-Type: application/pdf"); // Set the appropriate MIME type
            header(header: "Content-Disposition: attachment; filename=\"$fileName\"");
            header(header: "Expires: 0");
            header(header: "Cache-Control: must-revalidate");
            header(header: "Pragma: public");
            header(header: "Content-Length: " . filesize($filePath));
            // Read the file content and output it to the browser
            echo $filePath;
            readfile($filePath);
        } else {
            header(header: "Content-Type: application/json; charset=UTF-8");
            echo json_encode([
                'message' => strtoupper(string: 'File not found.'),
                'status' => 'error'
            ]);
        }
        return true;
    }
}
