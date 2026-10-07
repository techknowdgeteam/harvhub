<?php
/**
 * notification_email.php
 *
 * HarvHub notification email adapter.
 *
 * IMPORTANT:
 * This uses the SAME Brevo API method and credentials
 * used by the HarvHub verification-code email system.
 *
 * Database:
 *
 * server_account.mailer_email
 *      = Brevo verified sender email
 *
 * server_account.mailer_password
 *      = Brevo API key
 *
 * Brevo endpoint:
 *
 * POST https://api.brevo.com/v3/smtp/email
 *
 * Main function:
 *
 * sendNotificationEmail(
 *     $recipient,
 *     $title,
 *     $message,
 *     $meta
 * );
 */


/* ============================================================
 * SEND NOTIFICATION EMAIL
 * ============================================================ */

if (!function_exists('sendNotificationEmail')) {

    function sendNotificationEmail(
        string $recipient,
        string $title,
        string $message,
        array $meta = []
    ): bool {

        /* ----------------------------------------------------
         * NORMALIZE RECIPIENT
         * ---------------------------------------------------- */

        $recipient = strtolower(
            trim($recipient)
        );


        /* ----------------------------------------------------
         * VALIDATE RECIPIENT
         * ---------------------------------------------------- */

        if (
            $recipient === '' ||
            !filter_var(
                $recipient,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            error_log(
                '[HarvHub Notification Email] Invalid recipient email'
                . ' | recipient=' . $recipient
            );

            return false;
        }


        /* ----------------------------------------------------
         * PDO CONNECTION
         * ---------------------------------------------------- */

        $pdo =
            $meta['pdo'] ?? null;

        if (!($pdo instanceof PDO)) {

            error_log(
                '[HarvHub Notification Email] Missing PDO connection.'
            );

            return false;
        }


        /* ----------------------------------------------------
         * LOAD BREVO CREDENTIALS
         *
         * IMPORTANT:
         *
         * mailer_email    = sender email
         * mailer_password = Brevo API key
         * ---------------------------------------------------- */

        $mailer_email = '';
        $brevo_api_key = '';

        try {

            $stmt = $pdo->prepare("
                SELECT
                    mailer_email,
                    mailer_password
                FROM server_account
                WHERE id = 1
                LIMIT 1
            ");

            $stmt->execute();

            $mailerData =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );

            if ($mailerData) {

                $mailer_email = trim(
                    (string)(
                        $mailerData['mailer_email']
                        ?? ''
                    )
                );

                $brevo_api_key = trim(
                    (string)(
                        $mailerData['mailer_password']
                        ?? ''
                    )
                );
            }

        } catch (Throwable $e) {

            error_log(
                '[HarvHub Notification Email] Failed loading Brevo credentials'
                . ' | error=' . $e->getMessage()
            );

            return false;
        }


        /* ----------------------------------------------------
         * VALIDATE SENDER
         * ---------------------------------------------------- */

        if ($mailer_email === '') {

            error_log(
                '[HarvHub Notification Email] '
                . 'server_account.mailer_email is empty.'
            );

            return false;
        }


        if (
            !filter_var(
                $mailer_email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            error_log(
                '[HarvHub Notification Email] Invalid sender email'
                . ' | sender=' . $mailer_email
            );

            return false;
        }


        /* ----------------------------------------------------
         * VALIDATE BREVO API KEY
         * ---------------------------------------------------- */

        if ($brevo_api_key === '') {

            error_log(
                '[HarvHub Notification Email] '
                . 'server_account.mailer_password is empty.'
            );

            return false;
        }


        /* ----------------------------------------------------
         * BASIC VALUES
         * ---------------------------------------------------- */

        $siteName =
            defined(
                'HARVHUB_NOTIFICATION_SITE_NAME'
            )
                ? HARVHUB_NOTIFICATION_SITE_NAME
                : 'HarvHub';


        $safeTitle =
            trim($title) !== ''
                ? trim($title)
                : 'Notification';


        $safeMessage =
            trim($message);


        $section =
            trim(
                (string)(
                    $meta['section']
                    ?? 'Notification'
                )
            );


        if ($section === '') {
            $section = 'Notification';
        }


        $subAccountId =
            (int)(
                $meta['sub_account_id']
                ?? 0
            );


        $notificationId =
            (int)(
                $meta['notification_id']
                ?? 0
            );


        $recipientName =
            trim(
                (string)(
                    $meta['recipient_name']
                    ?? ''
                )
            );


        /* ====================================================
         * RECIPIENT NAME FALLBACK
         * ==================================================== */

        if ($recipientName === '') {

            try {

                $nameStmt = $pdo->prepare("
                    SELECT
                        fullname,
                        first_name
                    FROM harvhub
                    WHERE LOWER(email) = ?
                    ORDER BY id ASC
                    LIMIT 1
                ");

                $nameStmt->execute([
                    $recipient
                ]);

                $nameRow =
                    $nameStmt->fetch(
                        PDO::FETCH_ASSOC
                    );

                if ($nameRow) {

                    $recipientName =
                        trim(
                            (string)(
                                $nameRow['first_name']
                                ?? ''
                            )
                        );

                    if (
                        $recipientName === ''
                    ) {

                        $recipientName =
                            trim(
                                (string)(
                                    $nameRow['fullname']
                                    ?? ''
                                )
                            );
                    }
                }

            } catch (Throwable $e) {

                /*
                 * Recipient name is optional.
                 *
                 * A name lookup failure must NOT prevent
                 * the notification email from being sent.
                 */

                error_log(
                    '[HarvHub Notification Email] '
                    . 'Recipient name lookup failed'
                    . ' | notification_id='
                    . $notificationId
                    . ' | error='
                    . $e->getMessage()
                );
            }
        }


        /* ====================================================
         * EMAIL SUBJECT
         * ==================================================== */

        $subject =
            $siteName
            . ' — '
            . $safeTitle;


        /* ====================================================
         * HTML ESCAPING
         * ==================================================== */

        $safeSiteName =
            htmlspecialchars(
                $siteName,
                ENT_QUOTES,
                'UTF-8'
            );


        $safeSubject =
            htmlspecialchars(
                $subject,
                ENT_QUOTES,
                'UTF-8'
            );


        $safeSection =
            htmlspecialchars(
                $section,
                ENT_QUOTES,
                'UTF-8'
            );


        $safeTitleHtml =
            htmlspecialchars(
                $safeTitle,
                ENT_QUOTES,
                'UTF-8'
            );


        $safeMessageHtml =
            nl2br(
                htmlspecialchars(
                    $safeMessage,
                    ENT_QUOTES,
                    'UTF-8'
                )
            );


        /* ====================================================
         * ACCOUNT INFORMATION
         * ==================================================== */

        $accountHtml = '';

        if ($subAccountId > 0) {

            $accountHtml =
                '<div style="
                    margin-top:20px;
                    padding:12px 14px;
                    background:#f7fafc;
                    border-radius:10px;
                    font-size:12px;
                    color:#6b7280;
                ">
                    Account ID: '
                . $subAccountId .
                '</div>';
        }


        /* ====================================================
         * HTML EMAIL
         * ==================================================== */

        $html = '<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>'
    . $safeSubject .
'</title>

</head>


<body style="
    margin:0;
    padding:0;
    background-color:#f4f5f7;
    font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;
    color:#111827;
">


<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
    style="
        background-color:#f4f5f7;
        padding:40px 16px;
    "
>

<tr>

<td align="center">


<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
    style="
        max-width:620px;
        background-color:#ffffff;
        border-radius:14px;
        box-shadow:0 2px 8px rgba(0,0,0,0.05);
        overflow:hidden;
    "
>


<!-- HEADER -->

<tr>

<td style="
    padding:24px 28px;
    border-bottom:1px solid #edf0f4;
    text-align:center;
">


<div style="
    display:inline-block;
    width:52px;
    height:52px;
    line-height:52px;
    border-radius:13px;
    background-color:#2e8b57;
    color:#ffffff;
    font-weight:700;
    font-size:24px;
    text-align:center;
">
H
</div>


<div style="
    margin-top:10px;
    font-size:18px;
    font-weight:700;
    color:#111827;
">
'
    . $safeSiteName .
'
</div>


</td>

</tr>


<!-- CONTENT -->

<tr>

<td style="
    padding:28px;
">


<div style="
    font-size:12px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.7px;
    color:#2e8b57;
    margin-bottom:8px;
">
'
    . $safeSection .
'
</div>


<h1 style="
    font-size:22px;
    line-height:1.3;
    margin:0 0 14px;
    color:#111827;
">
'
    . $safeTitleHtml .
'
</h1>


<p style="
    font-size:15px;
    line-height:1.7;
    margin:0;
    color:#4b5563;
">
'
    . $safeMessageHtml .
'
</p>


'
    . $accountHtml .
'


</td>

</tr>


<!-- FOOTER -->

<tr>

<td style="
    padding:18px 28px;
    background:#fafbfc;
    border-top:1px solid #edf0f4;
    font-size:12px;
    line-height:1.6;
    color:#7b8491;
">

This is an automated HarvHub notification.
Please do not reply to this email.

</td>

</tr>


</table>


</td>

</tr>

</table>


</body>

</html>';


        /* ====================================================
         * PLAIN TEXT VERSION
         * ==================================================== */

        $plainText =
            $siteName
            . "\n\n"
            . $section
            . "\n"
            . $safeTitle
            . "\n\n"
            . $safeMessage
            . "\n\n";


        if ($subAccountId > 0) {

            $plainText .=
                'Account ID: '
                . $subAccountId
                . "\n\n";
        }


        $plainText .=
            'This is an automated HarvHub notification.'
            . "\n"
            . 'Please do not reply to this email.';


        /* ====================================================
         * BREVO RECIPIENT
         *
         * Do NOT send an empty "name" property.
         * Only add it when we actually have a name.
         * ==================================================== */

        $toEntry = [
            'email' =>
                $recipient
        ];


        if ($recipientName !== '') {

            $toEntry['name'] =
                $recipientName;
        }


        /* ====================================================
         * BREVO API PAYLOAD
         * ==================================================== */

        $payload = [

            'sender' => [

                'name' =>
                    'HarvHub Notifications',

                'email' =>
                    $mailer_email
            ],

            'to' => [
                $toEntry
            ],

            'subject' =>
                $subject,

            'htmlContent' =>
                $html,

            'textContent' =>
                $plainText
        ];


        /* ====================================================
         * JSON ENCODE
         * ==================================================== */

        $jsonPayload =
            json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_UNICODE
            );


        if ($jsonPayload === false) {

            error_log(
                '[HarvHub Notification Email] '
                . 'JSON encoding failed'
                . ' | notification_id='
                . $notificationId
                . ' | error='
                . json_last_error_msg()
            );

            return false;
        }


        /* ====================================================
         * BREVO API REQUEST
         *
         * THIS IS THE SAME API METHOD USED
         * BY THE VERIFICATION EMAIL SYSTEM.
         * ==================================================== */

        $brevoUrl =
            'https://api.brevo.com/v3/smtp/email';


        $ch =
            curl_init(
                $brevoUrl
            );


        if ($ch === false) {

            error_log(
                '[HarvHub Notification Email] '
                . 'curl_init() failed'
                . ' | notification_id='
                . $notificationId
            );

            return false;
        }


        curl_setopt_array(
            $ch,
            [

                CURLOPT_RETURNTRANSFER =>
                    true,

                CURLOPT_POST =>
                    true,

                CURLOPT_HTTPHEADER => [

                    'accept: application/json',

                    'api-key: '
                    . $brevo_api_key,

                    'content-type: application/json'
                ],

                CURLOPT_POSTFIELDS =>
                    $jsonPayload,

                CURLOPT_TIMEOUT =>
                    20,

                CURLOPT_CONNECTTIMEOUT =>
                    10,

                CURLOPT_SSL_VERIFYPEER =>
                    true,

                CURLOPT_SSL_VERIFYHOST =>
                    2
            ]
        );


        /* ====================================================
         * EXECUTE
         * ==================================================== */

        $response =
            curl_exec($ch);


        $httpCode =
            (int)curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );


        $curlError =
            curl_error($ch);


        $curlErrno =
            curl_errno($ch);


        curl_close($ch);


        /* ====================================================
         * CURL FAILURE
         * ==================================================== */

        if (
            $response === false ||
            $curlError !== ''
        ) {

            error_log(
                '[HarvHub Notification Email] CURL FAILURE'
                . ' | notification_id='
                . $notificationId
                . ' | recipient='
                . $recipient
                . ' | errno='
                . $curlErrno
                . ' | error='
                . $curlError
            );

            return false;
        }


        /* ====================================================
         * BREVO HTTP FAILURE
         * ==================================================== */

        if (
            $httpCode < 200 ||
            $httpCode >= 300
        ) {

            error_log(
                '[HarvHub Notification Email] BREVO FAILURE'
                . ' | notification_id='
                . $notificationId
                . ' | recipient='
                . $recipient
                . ' | http_code='
                . $httpCode
                . ' | response='
                . $response
            );

            return false;
        }


        /* ====================================================
         * SUCCESS
         * ==================================================== */

        error_log(
            '[HarvHub Notification Email] BREVO SUCCESS'
            . ' | notification_id='
            . $notificationId
            . ' | recipient='
            . $recipient
            . ' | http_code='
            . $httpCode
            . ' | response='
            . $response
        );


        return true;
    }
}