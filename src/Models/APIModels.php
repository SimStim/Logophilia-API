<?php

declare(strict_types=1);

namespace App\Models;

class APIModels
{
    private const int SMTP_TIMEOUT = 5;
    private const int MAX_FILE_SIZE_MB = 10;
    private const int MAX_FILE_SIZE_BYTES = self::MAX_FILE_SIZE_MB * 1024 * 1024;

    private static function verifyEmailDomain(string $email): bool
    {
        $parts = explode(separator: '@', string: $email);
        if (count($parts) !== 2) return false;
        $domain = $parts[1];
        // Check MX records
        if (!getmxrr(hostname: $domain, hosts: $mxHosts))
            if (!checkdnsrr(hostname: $domain, type: 'A'))
                return false;
        $mxHosts = [$domain];
        return true;
    }

    private static function verifyEmailSmtp(string $email): bool
    {
        $parts = explode(separator: '@', string: $email);
        if (count($parts) !== 2)
            return false;
        $domain = $parts[1];
        $mxHosts = [];
        if (!getmxrr(hostname: $domain, hosts: $mxHosts, weights: $mxWeights))
            $mxHosts = [$domain];
        if (!empty($mxWeights))
            array_multisort($mxWeights, SORT_ASC, $mxHosts);
        foreach ($mxHosts as $mx) {
            $sock = @fsockopen(
                hostname: $mx,
                port: 25,
                error_code: $errno,
                error_message: $errstr,
                timeout: self::SMTP_TIMEOUT
            );
            if (!$sock) continue;
            stream_set_timeout(stream: $sock, seconds: self::SMTP_TIMEOUT);
            $response = fgets(stream: $sock, length: 1024);
            if (!$response || !str_starts_with(haystack: $response, needle: '220')) {
                fclose($sock);
                continue;
            }
            fwrite($sock, data: "HELO logophilia.eu\r\n");
            fgets(stream: $sock, length: 1024);
            fwrite($sock, data: "MAIL FROM:<verify@logophilia.eu>\r\n");
            fgets(stream: $sock, length: 1024);
            fwrite($sock, data: "RCPT TO:<$email>\r\n");
            $response = fgets(stream: $sock, length: 1024);
            fwrite(stream: $sock, data: "QUIT\r\n");
            fclose(stream: $sock);
            if ($response
                && (str_starts_with(haystack: $response, needle: '250')
                    || str_starts_with(haystack: $response, needle: '251')))
                return true;
            if ($response && str_starts_with($response, '550'))
                return false;
            // Other responses: inconclusive, accept the email (greylisting, etc.)
            return true;
        }
        // Could not connect to any MX - fall back to domain check only
        return self::verifyEmailDomain($email);
    }

    private static function verifyEmail(string $email): bool
    {
        if (!self::verifyEmailDomain($email)) {
            http_response_code(response_code: 422);
            echo json_encode([
                'message' => strtoupper(string: "The email domain could not be found."),
                'status' => 'error'
            ]);
            return false;
        }
        $smtpValid = self::verifyEmailSmtp($email);
        if ($smtpValid === false) {
            http_response_code(response_code: 422);
            echo json_encode([
                'message' => strtoupper(string: "The email server did not accept the address."),
                'status' => 'error'
            ]);
            return false;
        }
        return true;
    }

    public static function processContact(string $email, string $message, string $consent): bool
    {
        if (!self::verifyEmail($email)) return false;
        $headers = [
            'From: noreply@logophilia.eu',
            'Reply-To: ' . $email,
            "Cc: $email",
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: Logophilia-Contact/1.0',
        ];
        $body = "Contact form submission from Logophilia.EU\n";
        $body .= "==========================================\n\n";
        $body .= "Email:   $email\n";
        $body .= "Message:\n$message\n\n";
        $body .= "---\n";
        $body .= "Consent: $consent\n";
        $body .= "IP: " . $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' . "\n";
        $body .= "Time: " . date(format: 'Y-m-d H:i:s T') . "\n";
        $sent = mail(
            to: "contact@logophilia.eu",
            subject: "[Logophilia Contact]",
            message: $body,
            additional_headers: implode(separator: "\r\n", array: $headers)
        );
        if ($sent) {
            header(header: "Content-Type: application/json; charset=UTF-8");
            echo json_encode([
                'message' => 'Message sent successfully.',
                'status' => 'success'
            ]);
            return true;
        } else {
            http_response_code(response_code: 500);
            echo json_encode([
                'message' => strtoupper(string: 'Failed to send message. Please try again later.'),
                'status' => 'error'
            ]);
            return false;
        }
    }

    public static function processNewsletter(string $email, string $consent): bool
    {
        if (!self::verifyEmail($email)) return false;
        $headers = [
            'From: noreply@logophilia.eu',
            "Cc: $email",
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: Logophilia-Newsletter/1.0',
        ];
        $body = "New newsletter subscriber: $email\n";
        $body .= "Consent: $consent\n";
        $body .= "IP: " . $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' . "\n";
        $body .= "Time: " . date(format: 'Y-m-d H:i:s T') . "\n";
        $sent = mail(
            to: "newsletter@logophilia.eu",
            subject: "[Logophilia Newsletter New Subscriber]",
            message: $body,
            additional_headers: implode(separator: "\r\n", array: $headers)
        );
        header(header: "Content-Type: application/json; charset=UTF-8");
        echo json_encode([
            'message' => 'You are subscribed to the Logophilia newsletter.',
            'status' => 'success'
        ]);
        return $sent;
    }

    public static function processSubmission(): bool
    {
        if (empty($_FILES) || count($_FILES) !== 1) {
            http_response_code(response_code: 400);
            echo json_encode([
                'message' => strtoupper(string: 'Exactly one file expected.'),
                'status' => 'error'
            ]);
            return false;
        }
        // Get first and only file
        $file = reset(array: $_FILES);
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large',
                UPLOAD_ERR_PARTIAL => 'File partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file uploaded',
                default => 'Upload failed'
            };
            http_response_code(response_code: 400);
            echo json_encode([
                'message' => strtoupper(string: $errorMessage),
                'status' => 'error'
            ]);
            return false;
        }
        if ($file['size'] === 0) {
            http_response_code(response_code: 400);
            echo json_encode([
                'message' => strtoupper(string: "Empty file not permitted."),
                'status' => 'error'
            ]);
            return false;
        }
        if ($file['size'] > self::MAX_FILE_SIZE_BYTES) {
            http_response_code(response_code: 400);
            echo json_encode([
                'message' => strtoupper(string: "File exceeds " . self::MAX_FILE_SIZE_MB . " MB limit."),
                'status' => 'error'
            ]);
            return false;
        }
        // Generate hash-based filename
        $fileHash = hash_file(algo: 'sha256', filename: $file['tmp_name']);
        $extension = pathinfo(path: $file['name'], flags: PATHINFO_EXTENSION);
        $filename = $fileHash . ($extension ? "." . $extension : "");
        $destination = UPLOADS . $filename;
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            http_response_code(response_code: 500);
            echo json_encode([
                'message' => strtoupper(string: "Failed to save file"),
                'status' => 'error'
            ]);
            return false;
        }
        header(header: "Content-Type: application/json; charset=UTF-8");
        echo json_encode([
            'message' => 'Manuscript submission processed successfully.',
            'status' => 'success'
        ]);
        // Send email notification for new upload
        $to = "idoru.toei@logophilia.eu";
        $subject = "New Manuscript Submission";
        $message = sprintf(
            "A new manuscript submission has been processed:\r\n\n"
            . "scp " . UPLOADS . "/%s .",
            $filename
        );
        $additionalHeaders = sprintf(
            "From: postmaster@logophilia.eu\r\n"
            . "X-Mailer: PHP/%s",
            phpversion()
        );
        return mail(
            to: $to,
            subject: $subject,
            message: $message,
            additional_headers: $additionalHeaders
        );
    }

    public static function sendFile(string $fileName): bool
    {
        $filePath = DOWNLOADS . $fileName;
        if (file_exists($filePath)) {
            header(header: "Content-Description: File Transfer");
            header(header: "Content-Type: application/octet-stream");
            header(header: "Content-Disposition: attachment; filename=\"$fileName\"");
            header(header: "Expires: 0");
            header(header: "Cache-Control: must-revalidate");
            header(header: "Pragma: public");
            header(header: "Content-Length: " . filesize($filePath));
            readfile($filePath);
            return true;
        } else {
            header(header: "Content-Type: application/json; charset=UTF-8");
            echo json_encode([
                'message' => strtoupper(string: 'File not found.'),
                'status' => 'error'
            ]);
            return false;
        }
    }
}
