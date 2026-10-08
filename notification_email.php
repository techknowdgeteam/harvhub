<?php
/**
 * notification_email.php
 *
 * HarvHub notification email adapter.
 *
 * Uses the same Brevo API method and credentials as the HarvHub
 * verification-code email system.
 *
 * Database:
 *   server_account.mailer_email    = Brevo verified sender email
 *   server_account.mailer_password = Brevo API key
 *
 * Brevo endpoint:
 *   POST https://api.brevo.com/v3/smtp/email
 *
 * Main function:
 *   sendNotificationEmail($recipient, $title, $message, $meta);
 *
 * Email body:
 *   {message}
 *
 * "HarvHub" appears ONLY as the Brevo sender name (shown by the
 * mail client as the sender). It is NOT repeated in the body.
 *
 * NO title, NO section, NO account ID filtering, NO extra lines.
 * The message is sent exactly as passed in.
 */

if (!function_exists('sendNotificationEmail')) {

    function sendNotificationEmail(
        string $recipient,
        string $title,
        string $message,
        array $meta = []
    ): bool {

        $recipient = strtolower(trim($recipient));

        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            error_log('[HarvHub Notification Email] Invalid recipient email | recipient=' . $recipient);
            return false;
        }

        $pdo = $meta['pdo'] ?? null;

        if (!($pdo instanceof PDO)) {
            error_log('[HarvHub Notification Email] Missing PDO connection.');
            return false;
        }

        $mailer_email  = '';
        $brevo_api_key = '';

        try {
            $stmt = $pdo->prepare("
                SELECT mailer_email, mailer_password
                FROM server_account
                WHERE id = 1
                LIMIT 1
            ");
            $stmt->execute();
            $mailerData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($mailerData) {
                $mailer_email  = trim((string)($mailerData['mailer_email']    ?? ''));
                $brevo_api_key = trim((string)($mailerData['mailer_password'] ?? ''));
            }
        } catch (Throwable $e) {
            error_log('[HarvHub Notification Email] Failed loading Brevo credentials | error=' . $e->getMessage());
            return false;
        }

        if ($mailer_email === '') {
            error_log('[HarvHub Notification Email] server_account.mailer_email is empty.');
            return false;
        }

        if (!filter_var($mailer_email, FILTER_VALIDATE_EMAIL)) {
            error_log('[HarvHub Notification Email] Invalid sender email | sender=' . $mailer_email);
            return false;
        }

        if ($brevo_api_key === '') {
            error_log('[HarvHub Notification Email] server_account.mailer_password is empty.');
            return false;
        }

        /* ----------------------------------------------------
         * BASIC VALUES
         * ---------------------------------------------------- */
        $safeTitle   = trim($title) !== '' ? trim($title) : 'Notification';
        $safeMessage = trim($message);

        $notificationId = (int)($meta['notification_id'] ?? 0);
        $recipientName  = trim((string)($meta['recipient_name'] ?? ''));

        if ($recipientName === '') {
            try {
                $nameStmt = $pdo->prepare("
                    SELECT fullname, first_name
                    FROM harvhub
                    WHERE LOWER(email) = ?
                    ORDER BY id ASC
                    LIMIT 1
                ");
                $nameStmt->execute([$recipient]);
                $nameRow = $nameStmt->fetch(PDO::FETCH_ASSOC);

                if ($nameRow) {
                    $recipientName = trim((string)($nameRow['first_name'] ?? ''));
                    if ($recipientName === '') {
                        $recipientName = trim((string)($nameRow['fullname'] ?? ''));
                    }
                }
            } catch (Throwable $e) {
                error_log(
                    '[HarvHub Notification Email] Recipient name lookup failed'
                    . ' | notification_id=' . $notificationId
                    . ' | error=' . $e->getMessage()
                );
            }
        }

        $subject = $safeTitle;

        $safeMessageHtml = nl2br(htmlspecialchars($safeMessage, ENT_QUOTES, 'UTF-8'));

        $html = '<!DOCTYPE html>
            <html lang="en">
            <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . htmlspecialchars($safeTitle, ENT_QUOTES, 'UTF-8') . '</title>
            </head>
            <body style="margin:0;padding:0;background-color:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#111827;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f5f7;padding:40px 16px;">
            <tr>
            <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:620px;background-color:#ffffff;border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,0.05);overflow:hidden;">
            <tr>
            <td style="padding:32px 28px 28px;">
            <p style="font-size:15px;line-height:1.7;margin:0;color:#4b5563;">' . $safeMessageHtml . '</p>
            </td>
            </tr>
            </table>
            </td>
            </tr>
            </table>
            </body>
            </html>';

        $plainText = $safeMessage;

        $toEntry = ['email' => $recipient];

        if ($recipientName !== '') {
            $toEntry['name'] = $recipientName;
        }

        $payload = [
            'sender' => [
                'name'  => 'HarvHub',
                'email' => $mailer_email,
            ],
            'to'          => [$toEntry],
            'subject'     => $subject,
            'htmlContent' => $html,
            'textContent' => $plainText,
        ];

        $jsonPayload = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($jsonPayload === false) {
            error_log(
                '[HarvHub Notification Email] JSON encoding failed'
                . ' | notification_id=' . $notificationId
                . ' | error=' . json_last_error_msg()
            );
            return false;
        }

        $brevoUrl = 'https://api.brevo.com/v3/smtp/email';

        $ch = curl_init($brevoUrl);
        if ($ch === false) {
            error_log(
                '[HarvHub Notification Email] curl_init() failed'
                . ' | notification_id=' . $notificationId
            );
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'api-key: ' . $brevo_api_key,
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        if ($response === false || $curlError !== '') {
            error_log(
                '[HarvHub Notification Email] CURL FAILURE'
                . ' | notification_id=' . $notificationId
                . ' | recipient=' . $recipient
                . ' | errno=' . $curlErrno
                . ' | error=' . $curlError
            );
            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log(
                '[HarvHub Notification Email] BREVO FAILURE'
                . ' | notification_id=' . $notificationId
                . ' | recipient=' . $recipient
                . ' | http_code=' . $httpCode
                . ' | response=' . $response
            );
            return false;
        }

        error_log(
            '[HarvHub Notification Email] BREVO SUCCESS'
            . ' | notification_id=' . $notificationId
            . ' | recipient=' . $recipient
            . ' | http_code=' . $httpCode
            . ' | response=' . $response
        );

        return true;
    }
}