<?php
//verify_email.php
    session_start();

    // ==================== DATABASE CONNECTION ====================
    try {
        $pdo = new PDO(
            "mysql:host=sql312.infinityfree.com;dbname=if0_40473107_harvhub;charset=utf8mb4",
            "if0_40473107",
            "InDQmdl53FZ85",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        die("Database connection failed.");
    }

    // ==================== FETCH MAILER CREDENTIALS FROM server_account ====================
    $mailer_email  = '';
    $brevo_api_key = '';

    try {
        $stmt = $pdo->prepare("SELECT mailer_email, mailer_password FROM server_account WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $mailerData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($mailerData) {
            $mailer_email  = trim($mailerData['mailer_email'] ?? '');
            $brevo_api_key = trim($mailerData['mailer_password'] ?? '');
        }
    } catch (Exception $e) {
        // silent
    }

    // ==================== BREVO API MAILER ====================
    function sendOTPEmail($email, $code, $mailer_email, $brevo_api_key) {

        if (empty($mailer_email) || empty($brevo_api_key)) {
            $_SESSION['otp_error'] = 'Mail configuration missing. Please contact support.';
            return false;
        }

        $safeCode = htmlspecialchars($code);

        $payload = [
            'sender' => [
                'name'  => 'HarvHub',
                'email' => $mailer_email,
            ],
            'to' => [
                ['email' => $email],
            ],
            'subject'     => 'Verify your email address',
            'htmlContent' =>
                '<!DOCTYPE html>'
                . '<html>'
                . '<head>'
                . '<meta charset="UTF-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
                . '</head>'
                . '<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">'

                // ===== PREHEADER: text shown in the inbox preview line =====
                . '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#f4f5f7;opacity:0;">'
                . 'Complete your registration — use this code to verify your email address.'
                . '</div>'
                // ===========================================================

                . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f5f7;padding:40px 16px;">'
                . '<tr><td align="center">'

                . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background-color:#ffffff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.05);overflow:hidden;">'

                . '<tr><td style="padding:32px 40px 8px 40px;text-align:center;">'
                . '<div style="display:inline-block;width:56px;height:56px;line-height:56px;border-radius:14px;background-color:#2e8b57;color:#ffffff;font-weight:700;font-size:26px;text-align:center;">H</div>'
                . '<h1 style="margin:16px 0 0 0;font-size:22px;font-weight:700;color:#111827;letter-spacing:-0.2px;">HarvHub</h1>'
                . '</td></tr>'

                . '<tr><td style="padding:24px 40px 8px 40px;">'
                . '<h2 style="margin:0 0 12px 0;font-size:18px;font-weight:600;color:#111827;">Complete your registration</h2>'
                . '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#4b5563;">'
                . 'Thank you for registering with HarvHub. To complete your signup and secure your account, please use the verification code below.'
                . '</p>'
                . '</td></tr>'

                . '<tr><td style="padding:8px 40px 8px 40px;">'
                . '<div style="background-color:#f0f9f4;border:1px solid #d6ede0;border-radius:10px;padding:24px;text-align:center;">'
                . '<p style="margin:0 0 8px 0;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#4b5563;font-weight:600;">Verification code</p>'
                . '<div style="font-family:\'SF Mono\',Menlo,Consolas,\'Courier New\',monospace;font-size:34px;font-weight:700;letter-spacing:10px;color:#2e8b57;padding-left:10px;">'
                . $safeCode
                . '</div>'
                . '<p style="margin:12px 0 0 0;font-size:13px;color:#6b7280;">This code expires in 15 minutes</p>'
                . '</div>'
                . '</td></tr>'

                . '<tr><td style="padding:16px 40px 8px 40px;">'
                . '<p style="margin:0;font-size:14px;line-height:1.6;color:#4b5563;">'
                . 'Enter this code on the verification page to activate your account. For your security, do not share this code with anyone.'
                . '</p>'
                . '</td></tr>'

                . '<tr><td style="padding:16px 40px 8px 40px;">'
                . '<p style="margin:0;font-size:14px;line-height:1.6;color:#4b5563;">'
                . 'If you did not create an account with HarvHub, you can safely disregard this message — no action is required.'
                . '</p>'
                . '</td></tr>'

                . '<tr><td style="padding:24px 40px 0 40px;">'
                . '<div style="border-top:1px solid #e5e7eb;"></div>'
                . '</td></tr>'

                . '<tr><td style="padding:20px 40px 32px 40px;">'
                . '<p style="margin:0 0 6px 0;font-size:13px;color:#6b7280;">'
                . 'This is an automated message from HarvHub. Please do not reply to this email.'
                . '</p>'
                . '<p style="margin:0;font-size:12px;color:#9ca3af;">'
                . '&copy; ' . date('Y') . ' HarvHub. All rights reserved.'
                . '</p>'
                . '</td></tr>'

                . '</table>'

                . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;margin-top:20px;">'
                . '<tr><td style="padding:0 8px;text-align:center;font-size:12px;color:#9ca3af;line-height:1.6;">'
                . 'For your security, HarvHub will never ask for your password or verification code via email, phone, or chat.'
                . '</td></tr>'
                . '</table>'

                . '</td></tr>'
                . '</table>'
                . '</body>'
                . '</html>',

            'textContent' =>
                "Complete your registration — use this code to verify your email address.\n\n"
                . "HARVHUB\n"
                . "=====================================\n\n"
                . "Complete your registration\n\n"
                . "Thank you for registering with HarvHub. To complete\n"
                . "your signup and secure your account, please use the\n"
                . "verification code below.\n\n"
                . "VERIFICATION CODE: $code\n\n"
                . "This code expires in 15 minutes.\n\n"
                . "Enter this code on the verification page to activate\n"
                . "your account. For your security, do not share this\n"
                . "code with anyone.\n\n"
                . "If you did not create an account with HarvHub, you can\n"
                . "safely disregard this message - no action is required.\n\n"
                . "-------------------------------------\n"
                . "This is an automated message from HarvHub.\n"
                . "Please do not reply to this email.\n\n"
                . "© " . date('Y') . " HarvHub. All rights reserved.\n\n"
                . "For your security, HarvHub will never ask for your\n"
                . "password or verification code via email, phone, or chat.\n",
        ];

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'api-key: ' . $brevo_api_key,
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        $_SESSION['otp_error'] = 'Failed to send verification email. Please try again.';
        return false;
    }

    function generateOTP() {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    function storeOTP($pdo, $email, $code) {
        try {
            $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE email = ?");
            $stmt->execute([$email]);

            $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $stmt = $pdo->prepare("INSERT INTO email_verifications (email, otp_code, expires_at) VALUES (?, ?, ?)");
            return $stmt->execute([$email, $code, $expires_at]);
        } catch (PDOException $e) {
            return false;
        }
    }

    function verifyOTP($pdo, $email, $code) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, otp_code, verified, expires_at
                FROM email_verifications
                WHERE email = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return ['valid' => false, 'message' => 'No verification request found. Please request a new code.'];
            }
            if ($record['verified'] == 1) {
                return ['valid' => false, 'message' => 'This email has already been verified.'];
            }
            $expires_at = strtotime($record['expires_at']);
            if ($expires_at < time()) {
                return ['valid' => false, 'message' => 'Verification code has expired. Please request a new code.'];
            }
            if ($record['otp_code'] !== $code) {
                return ['valid' => false, 'message' => 'Invalid verification code. Please try again.'];
            }
            return ['valid' => true, 'message' => 'Email verified successfully!'];
        } catch (PDOException $e) {
            return ['valid' => false, 'message' => 'Database error occurred. Please try again.'];
        }
    }

    function markEmailVerified($pdo, $email) {
        try {
            $stmt = $pdo->prepare("UPDATE email_verifications SET verified = 1 WHERE email = ? ORDER BY created_at DESC LIMIT 1");
            return $stmt->execute([$email]);
        } catch (PDOException $e) {
            return false;
        }
    }

    function maskEmail($email) {
        $parts = explode('@', $email);
        $username = $parts[0];
        $domain = $parts[1] ?? '';

        if (strlen($username) <= 2) {
            $masked = $username;
        } else {
            $masked = substr($username, 0, 2) . str_repeat('*', strlen($username) - 4) . substr($username, -2);
        }
        return $masked . '@' . $domain;
    }

    // ==================== GET SOURCE ====================
    $source = isset($_GET['source']) ? $_GET['source'] : (isset($_SESSION['verify_source']) ? $_SESSION['verify_source'] : 'index');
    $_SESSION['verify_source'] = $source;

    if ($source === 'dev_login') {
        $return_url = 'dev_login.php';
    } elseif ($source === 'app') {
        $return_url = 'app.php';
    } elseif ($source === 'mydashboard') {
        $return_url = 'mydashboard.php';
    } else {
        $return_url = 'index.php';
    }

    // ==================== GET SESSION DATA ====================
    $email = $_SESSION['pending_verification_email'] ?? '';
    $step = $_SESSION['otp_step'] ?? 'request';
    $error = $_SESSION['otp_error'] ?? '';
    $success = $_SESSION['otp_success'] ?? '';
    $return_to = $_SESSION['return_after_verify'] ?? $return_url;

    // ==================== HANDLE REQUEST OTP ====================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_otp') {

        $email = trim(strtolower($_POST['email'] ?? ''));
        if ($email === '') {
            $email = trim(strtolower($_SESSION['pending_verification_email'] ?? ''));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['otp_error'] = 'Please enter a valid email address.';
            header('Location: verify_email.php?source=' . $source);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, email_verified FROM harvhub WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['email_verified'] == 1) {
            $_SESSION['otp_error'] = 'This email is already verified. Please login.';
            header('Location: verify_email.php?source=' . $source);
            exit;
        }

        $code = generateOTP();
        $maskedEmail = maskEmail($email);

        if (!storeOTP($pdo, $email, $code)) {
            $_SESSION['otp_error'] = 'Failed to generate verification code. Please try again.';
            header('Location: verify_email.php?source=' . $source);
            exit;
        }

        if (sendOTPEmail($email, $code, $mailer_email, $brevo_api_key)) {
            $_SESSION['pending_verification_email'] = $email;
            $_SESSION['otp_step'] = 'verify';
            $_SESSION['otp_success'] = "A verification code has been sent to {$maskedEmail}.";
            unset($_SESSION['otp_error']);
        } else {
            if (empty($_SESSION['otp_error'])) {
                $_SESSION['otp_error'] = 'Failed to send verification email. Please try again or contact support.';
            }
        }

        header('Location: verify_email.php?source=' . $source);
        exit;
    }

    // ==================== HANDLE VERIFY OTP ====================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
        $code = trim($_POST['otp_code'] ?? '');
        $email = $_SESSION['pending_verification_email'] ?? '';

        if (empty($email)) {
            $_SESSION['otp_error'] = 'Session expired. Please start over.';
            $_SESSION['otp_step'] = 'request';
            unset($_SESSION['pending_verification_email']);
            header('Location: verify_email.php?source=' . $source);
            exit;
        }

        if (empty($code) || strlen($code) !== 6) {
            $_SESSION['otp_error'] = 'Please enter the complete 6-digit verification code.';
            header('Location: verify_email.php?source=' . $source);
            exit;
        }

        $result = verifyOTP($pdo, $email, $code);

        if ($result['valid']) {
            try {
                $pdo->beginTransaction();

                markEmailVerified($pdo, $email);

                $hashed_password = $_SESSION['pending_verification_password'] ?? '';
                $fullname = $_SESSION['pending_verification_fullname'] ?? '';

                $stmt = $pdo->prepare("SELECT id, email_verified FROM harvhub WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $existing = $stmt->fetch();

                if ($existing) {
                    if (!empty($hashed_password)) {
                        $stmt = $pdo->prepare("UPDATE harvhub SET password = ?, email_verified = 1 WHERE email = ?");
                        $result = $stmt->execute([$hashed_password, $email]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE harvhub SET email_verified = 1 WHERE email = ?");
                        $result = $stmt->execute([$email]);
                    }
                } else {
                    if (empty($fullname)) {
                        $fullname = explode('@', $email)[0];
                    }
                    if (!empty($hashed_password)) {
                        $stmt = $pdo->prepare("INSERT INTO harvhub (email, fullname, password, email_verified) VALUES (?, ?, ?, 1)");
                        $result = $stmt->execute([$email, $fullname, $hashed_password]);
                    } else {
                        $_SESSION['otp_error'] = 'Missing password information. Please try signing up again.';
                        header('Location: verify_email.php?source=' . $source);
                        exit;
                    }
                }

                $pdo->commit();

                $_SESSION['otp_step'] = 'success';
                $_SESSION['otp_success'] = 'Email verified successfully!';
                unset($_SESSION['otp_error']);

                $_SESSION['email_verified'] = true;
                $_SESSION['user_email'] = $email;

                unset($_SESSION['pending_verification_password']);
                unset($_SESSION['pending_verification_fullname']);
                unset($_SESSION['signup_in_progress']);

            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['otp_error'] = 'An error occurred while creating your account. Please try again.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['otp_error'] = 'An unexpected error occurred. Please try again.';
            }
        } else {
            $_SESSION['otp_error'] = $result['message'];
        }

        header('Location: verify_email.php?source=' . $source);
        exit;
    }

    // ==================== HANDLE RESEND OTP ====================
    if (isset($_GET['resend']) && $_GET['resend'] === '1') {
        $email = $_SESSION['pending_verification_email'] ?? '';

        if (!empty($email)) {
            $code = generateOTP();
            $maskedEmail = maskEmail($email);

            if (storeOTP($pdo, $email, $code)) {
                if (sendOTPEmail($email, $code, $mailer_email, $brevo_api_key)) {
                    $_SESSION['otp_success'] = "A new verification code has been sent to {$maskedEmail}.";
                    unset($_SESSION['otp_error']);
                } else {
                    if (empty($_SESSION['otp_error'])) {
                        $_SESSION['otp_error'] = 'Failed to send email. Please try again.';
                    }
                }
            } else {
                $_SESSION['otp_error'] = 'Failed to generate new code. Please try again.';
            }
        }

        header('Location: verify_email.php?source=' . $source);
        exit;
    }

    // ==================== HANDLE CANCEL ====================
    if (isset($_GET['cancel'])) {
        $is_approved = $_SESSION['is_approved_user'] ?? false;

        unset($_SESSION['pending_verification_email']);
        unset($_SESSION['otp_step']);
        unset($_SESSION['otp_success']);
        unset($_SESSION['otp_error']);
        unset($_SESSION['email_verified']);
        unset($_SESSION['is_approved_user']);
        unset($_SESSION['return_after_verify']);
        unset($_SESSION['verify_source']);

        if ($is_approved) {
            header('Location: ' . $return_url);
        } else {
            unset($_SESSION['user_email']);
            session_destroy();
            header('Location: ' . $return_url);
        }
        exit;
    }

    // ==================== HANDLE CONTINUE ====================
    if (isset($_GET['continue'])) {
        if (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === true) {
            $return_to = $_SESSION['return_after_verify'] ?? $return_url;

            unset($_SESSION['pending_verification_email']);
            unset($_SESSION['otp_step']);
            unset($_SESSION['otp_success']);
            unset($_SESSION['otp_error']);
            unset($_SESSION['is_approved_user']);
            unset($_SESSION['return_after_verify']);
            unset($_SESSION['verify_source']);

            header('Location: ' . $return_to);
            exit;
        }
        header('Location: verify_email.php?source=' . $source);
        exit;
    }

    // ==================== GET CURRENT STATE ====================
    $email = $_SESSION['pending_verification_email'] ?? '';
    $step = $_SESSION['otp_step'] ?? 'request';
    $error = $_SESSION['otp_error'] ?? '';
    $success = $_SESSION['otp_success'] ?? '';
    $maskedEmail = !empty($email) ? maskEmail($email) : '';

    unset($_SESSION['otp_error']);
    unset($_SESSION['otp_success']);

    $back_link = $return_url;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<title>Verify Email - HarvHub</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<style>
    :root {
        --bg-primary: #000;
        --bg-secondary: rgba(20, 20, 30, 0.95);
        --bg-input: #1a1a2e;
        --bg-success-details: rgba(46, 139, 87, 0.1);
        --bg-overlay-start: #1a0033;
        --bg-overlay-end: #000033;
        --text-primary: #e4e6eb;
        --text-secondary: #aaa;
        --text-muted: #888;
        --text-dark: #555;
        --border-color: #333;
        --border-light: rgba(255,255,255,0.05);
        --shadow-color: rgba(0,0,0,0.8);
        --input-focus: #2e8b57;
        --success-color: #90ee90;
        --error-color: #ff6b6b;
        --error-bg: rgba(255, 107, 107, 0.1);
        --success-bg: rgba(46, 139, 87, 0.1);
        --badge-bg: rgba(46, 139, 87, 0.2);
        --btn-text: #000;
        --scrollbar-track: #1a1a2e;
        --scrollbar-thumb: #2e8b57;
        --scrollbar-thumb-hover: #3a9b67;
        --dot-color: white;
        --debug-bg: rgba(255, 200, 0, 0.1);
        --debug-text: #ffaa00;
    }

    @media (prefers-color-scheme: light) {
        :root {
            --bg-primary: #f0f2f5;
            --bg-secondary: rgba(255, 255, 255, 0.95);
            --bg-input: #ffffff;
            --bg-success-details: rgba(46, 139, 87, 0.08);
            --bg-overlay-start: #e8f0e8;
            --bg-overlay-end: #d4e8d4;
            --text-primary: #1a1a2e;
            --text-secondary: #555;
            --text-muted: #777;
            --text-dark: #333;
            --border-color: #ddd;
            --border-light: rgba(0,0,0,0.08);
            --shadow-color: rgba(0,0,0,0.15);
            --input-focus: #2e8b57;
            --success-color: #2e8b57;
            --error-color: #dc3545;
            --error-bg: rgba(220, 53, 69, 0.08);
            --success-bg: rgba(46, 139, 87, 0.08);
            --badge-bg: rgba(46, 139, 87, 0.15);
            --btn-text: #fff;
            --scrollbar-track: #e8e8e8;
            --scrollbar-thumb: #2e8b57;
            --scrollbar-thumb-hover: #3a9b67;
            --dot-color: #666;
            --debug-bg: rgba(255, 200, 0, 0.05);
            --debug-text: #996600;
        }

        .container {
            border: 1px solid rgba(0,0,0,0.08);
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }
        input[type="email"], input[type="password"] {
            border: 1px solid #ddd;
            background: #ffffff;
            color: #1a1a2e;
        }
        input[type="email"]::placeholder, input[type="password"]::placeholder { color: #999; }
        input[type="email"]:focus, input[type="password"]:focus {
            border-color: #2e8b57;
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
        }
        .btn { color: #fff; background: #2e8b57; }
        .btn:hover { background: #3a9b67; }
        .btn-secondary { color: #555; border: 1px solid #ddd; background: transparent; }
        .btn-secondary:hover { background: rgba(0,0,0,0.03); }
        .error-message { color: #dc3545; background: rgba(220, 53, 69, 0.08); border-left: 3px solid #dc3545; }
        .success-message { color: #2e8b57; background: rgba(46, 139, 87, 0.08); border-left: 3px solid #2e8b57; }
        .code-input-container input { border: 1px solid #ddd; background: #ffffff; color: #1a1a2e; }
        .code-input-container input:focus { border-color: #2e8b57; box-shadow: 0 0 15px rgba(46, 139, 87, 0.15); }
        .back-link a, .resend-link a { color: #2e8b57; }
        .footer { color: #999; }
        .info-text { color: #777; }
        label { color: #555; }
        .description { color: #555; }
        .success-details { background: rgba(46, 139, 87, 0.08); }
        .success-details p { color: #555; }
        .success-details strong { color: #2e8b57; }
        ::-webkit-scrollbar-track { background: #f0f0f0; }
        ::-webkit-scrollbar-thumb { background: #2e8b57; }
        ::-webkit-scrollbar-thumb:hover { background: #3a9b67; }
    }

    @media (prefers-color-scheme: dark) {
        input[type="email"], input[type="password"] {
            border: 1px solid #333;
            background: #1a1a2e;
            color: #fff;
        }
        input[type="email"]::placeholder, input[type="password"]::placeholder { color: #666; }
        input[type="email"]:focus, input[type="password"]:focus {
            border-color: #2e8b57;
            box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.15);
        }
        .btn { color: #000; background: #2e8b57; }
        .btn:hover { background: #3a9b67; }
        .btn-secondary { color: #888; border: 1px solid #333; background: transparent; }
        .btn-secondary:hover { background: rgba(255,255,255,0.05); }
        .code-input-container input { border: 1px solid #333; background: #1a1a2e; color: #fff; }
        .code-input-container input:focus { border-color: #2e8b57; box-shadow: 0 0 15px rgba(46, 139, 87, 0.2); }
        .success-details { background: rgba(46, 139, 87, 0.1); }
        .success-details p { color: #aaa; }
        .success-details strong { color: #2e8b57; }
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    html, body {
        height: 100%; overflow: hidden; position: fixed; width: 100%;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: var(--bg-primary);
        color: var(--text-primary);
        min-height: 100vh;
        display: flex; align-items: center; justify-content: center;
        padding: 20px;
        -webkit-overflow-scrolling: none;
        overscroll-behavior: none;
        transition: background 0.3s ease, color 0.3s ease;
    }

    body::before {
        content: "";
        position: absolute; inset: 0;
        background:
            radial-gradient(circle at 20% 80%, var(--bg-overlay-start) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, var(--bg-overlay-end) 0%, transparent 50%),
            url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><circle cx="10" cy="10" r="1" fill="%23' . (isset($_COOKIE['prefers_color_scheme']) && $_COOKIE['prefers_color_scheme'] === 'light' ? '999' : 'fff') . '"/><circle cx="30" cy="70" r="1.5" fill="%23' . (isset($_COOKIE['prefers_color_scheme']) && $_COOKIE['prefers_color_scheme'] === 'light' ? '999' : 'fff') . '"/><circle cx="70" cy="30" r="1" fill="%23' . (isset($_COOKIE['prefers_color_scheme']) && $_COOKIE['prefers_color_scheme'] === 'light' ? '999' : 'fff') . '"/><circle cx="90" cy="80" r="1.2" fill="%23' . (isset($_COOKIE['prefers_color_scheme']) && $_COOKIE['prefers_color_scheme'] === 'light' ? '999' : 'fff') . '"/><circle cx="50" cy="50" r="1.8" fill="%23' . (isset($_COOKIE['prefers_color_scheme']) && $_COOKIE['prefers_color_scheme'] === 'light' ? '999' : 'fff') . '"/></svg>') repeat;
        background-size: cover, cover, 120px 120px;
        opacity: 0.5;
        pointer-events: none;
        z-index: 0;
        transition: opacity 0.3s ease;
    }

    @media (prefers-color-scheme: light) {
        body::before {
            opacity: 0.3;
            background:
                radial-gradient(circle at 20% 80%, #d4e8d4 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, #e8f0e8 0%, transparent 50%),
                url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><circle cx="10" cy="10" r="1" fill="%23999"/><circle cx="30" cy="70" r="1.5" fill="%23999"/><circle cx="70" cy="30" r="1" fill="%23999"/><circle cx="90" cy="80" r="1.2" fill="%23999"/><circle cx="50" cy="50" r="1.8" fill="%23999"/></svg>') repeat;
            background-size: cover, cover, 120px 120px;
        }
    }

    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: var(--scrollbar-track); border-radius: 10px; }
    ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--scrollbar-thumb-hover); }

    @media (max-width: 768px) {
        input, select, textarea { font-size: 16px !important; }
    }

    .container {
        background: var(--bg-secondary);
        border-radius: 20px;
        padding: 30px 25px;
        max-width: 500px;
        width: 100%;
        position: relative;
        z-index: 1;
        box-shadow: 0 20px 60px var(--shadow-color);
        border: 1px solid var(--border-light);
        backdrop-filter: blur(10px);
        max-height: 95vh;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
        transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    }

    h2 { color: #2e8b57; margin-bottom: 8px; text-align: center; font-size: 1.2rem; }

    .description {
        text-align: center; color: var(--text-secondary);
        font-size: 0.9rem; margin-bottom: 20px; line-height: 1.5;
        transition: color 0.3s ease;
    }

    label {
        display: block; font-weight: 600; margin-bottom: 5px;
        color: var(--text-secondary); font-size: 0.85rem;
        transition: color 0.3s ease;
    }

    input[type="email"], input[type="password"] {
        width: 100%; padding: 12px 14px; border-radius: 10px;
        font-size: 0.95rem;
        transition: border-color 0.3s ease, background 0.3s ease, color 0.3s ease, box-shadow 0.3s ease;
        -webkit-appearance: none;
        appearance: none;
    }
    input[type="email"]:focus, input[type="password"]:focus {
        outline: none;
        border-color: var(--input-focus);
        box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
    }

    .btn {
        width: 100%; padding: 12px;
        font-weight: bold; font-size: 0.95rem;
        border: none; border-radius: 10px; cursor: pointer;
        transition: all 0.3s ease; margin-top: 5px;
        -webkit-tap-highlight-color: transparent;
    }
    .btn:hover { transform: scale(1.01); opacity: 0.9; }
    .btn:active { transform: scale(0.98); }
    .btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

    .btn-secondary {
        padding: 10px;
        transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease;
    }
    .btn-secondary:hover { transform: none; }

    .error-message {
        text-align: center; margin: 10px 0; font-size: 0.85rem;
        padding: 8px 12px; border-radius: 8px;
        border-left: 3px solid var(--error-color);
        word-break: break-word;
        transition: color 0.3s ease, background 0.3s ease;
    }

    .success-message {
        text-align: center; margin: 10px 0; font-size: 0.85rem;
        padding: 8px 12px; border-radius: 8px;
        border-left: 3px solid var(--input-focus);
        word-break: break-word;
        transition: color 0.3s ease, background 0.3s ease;
    }

    .info-text {
        color: var(--text-muted); font-size: 0.8rem; text-align: center;
        margin: 12px 0;
        transition: color 0.3s ease;
    }

    .back-link { text-align: center; margin-top: 15px; }
    .back-link a {
        color: #2e8b57; text-decoration: none; font-size: 0.85rem;
        transition: opacity 0.3s ease;
    }
    .back-link a:hover { text-decoration: underline; opacity: 0.8; }

    .code-input-container {
        display: flex; gap: 8px; justify-content: center;
        margin: 15px 0 10px; flex-wrap: nowrap;
    }
    .code-input-container input {
        width: 45px; height: 50px; text-align: center;
        font-size: 1.4rem; font-weight: bold; border-radius: 10px;
        transition: border-color 0.3s ease, background 0.3s ease, color 0.3s ease, box-shadow 0.3s ease;
        padding: 0;
        -webkit-appearance: none;
        appearance: none;
        -moz-appearance: textfield;
        flex-shrink: 0;
    }
    .code-input-container input::-webkit-outer-spin-button,
    .code-input-container input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .code-input-container input:focus {
        outline: none;
        border-color: var(--input-focus);
        box-shadow: 0 0 15px rgba(46, 139, 87, 0.15);
    }
    .code-input-container input:disabled { opacity: 0.5; cursor: not-allowed; }

    .resend-link { text-align: center; margin: 12px 0 5px; }
    .resend-link a {
        color: #2e8b57; text-decoration: none; font-size: 0.85rem;
        transition: opacity 0.3s ease;
    }
    .resend-link a:hover { text-decoration: underline; opacity: 0.8; }

    .email-display {
        text-align: center; font-weight: bold; font-size: 0.95rem;
        margin: 3px 0 12px; padding: 6px 12px; border-radius: 8px;
        word-break: break-all;
        transition: background 0.3s ease, color 0.3s ease;
    }

    .footer {
        text-align: center; margin-top: 15px; font-size: 0.7rem;
        transition: color 0.3s ease;
    }

    .success-icon { font-size: 4rem; text-align: center; margin: 10px 0; }

    .success-details {
        border-radius: 12px; padding: 15px; margin: 15px 0;
        transition: background 0.3s ease;
    }
    .success-details p { margin: 5px 0; transition: color 0.3s ease; }
    .success-details strong { transition: color 0.3s ease; }

    .status-badge {
        display: inline-block; padding: 4px 12px; border-radius: 20px;
        font-size: 0.7rem; margin-bottom: 15px; text-align: center; width: 100%;
        transition: background 0.3s ease, color 0.3s ease;
    }

    @media (max-width: 480px) {
        .container { padding: 20px 15px; max-height: 90vh; }
        .code-input-container input { width: 38px; height: 44px; font-size: 1.2rem; }
        .code-input-container { gap: 5px; }
        h2 { font-size: 1.1rem; }
        input[type="email"], input[type="password"] { padding: 10px 12px; font-size: 0.9rem; }
        .btn { padding: 10px; font-size: 0.9rem; }
        .success-icon { font-size: 3rem; }
    }

    @media (max-width: 380px) {
        .code-input-container input { width: 32px; height: 38px; font-size: 1rem; }
        .code-input-container { gap: 4px; }
        .container { padding: 15px 12px; }
    }

    @media (min-width: 768px) {
        .container { padding: 40px 35px; }
        .code-input-container input { width: 55px; height: 60px; font-size: 1.6rem; }
        .code-input-container { gap: 12px; }
    }

    input { font-size: 16px !important; }

    .no-select {
        user-select: none;
        -webkit-user-select: none;
    }
</style>
</head>
<body>

<div class="container">
    <?php if ($step === 'request'): ?>
        <h2>Verify Your Email</h2>
        <p class="description">We'll send a verification code to <strong><?= htmlspecialchars($maskedEmail) ?></strong> to verify your email.</p>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="verify_email.php?source=<?= htmlspecialchars($source) ?>">
            <input type="hidden" name="action" value="request_otp">
            <input type="hidden" name="email" id="email" value="<?= htmlspecialchars($email) ?>">
            <button type="submit" class="btn">Send Verification Code</button>
        </form>

        <div class="back-link">
            <a href="verify_email.php?cancel=1&source=<?= htmlspecialchars($source) ?>">Cancel &amp; Return</a>
        </div>

    <?php elseif ($step === 'verify'): ?>
        <h2>Enter Verification Code</h2>
        <p class="success-message">We sent a 6-digit verification code to <?= htmlspecialchars($maskedEmail) ?></p>
        <?php if (!empty($error)): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="verify_email.php?source=<?= htmlspecialchars($source) ?>" id="verifyForm">
            <input type="hidden" name="action" value="verify_otp">
            <label>Enter 6-Digit Code</label>
            <div class="code-input-container" id="codeContainer">
                <input type="text" maxlength="1" class="code-input" data-index="0" autofocus required inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="code-input" data-index="1" required inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="code-input" data-index="2" required inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="code-input" data-index="3" required inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="code-input" data-index="4" required inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="code-input" data-index="5" required inputmode="numeric" pattern="[0-9]">
            </div>
            <input type="hidden" name="otp_code" id="otpCodeHidden" value="">
            <button type="submit" class="btn" id="verifyBtn">Confirm</button>
        </form>

        <div class="resend-link">
            <a href="verify_email.php?resend=1&source=<?= htmlspecialchars($source) ?>" id="resendLink">Resend Code</a> &nbsp;|&nbsp;
            <a href="verify_email.php?cancel=1&source=<?= htmlspecialchars($source) ?>">Cancel</a>
        </div>

    <?php elseif ($step === 'success'): ?>
        <div class="success-icon">✅</div>
        <h2>Email Verified!</h2>
        <p class="description">Your email has been successfully verified. You can now proceed to complete your registration.</p>

        <div class="success-details">
            <p><strong>Email:</strong> <?= htmlspecialchars($email) ?></p>
            <p><strong>Status:</strong> <span style="color: #2e8b57;">Verified ✓</span></p>
        </div>

        <button class="btn" onclick="window.location.href='verify_email.php?continue=1&source=<?= htmlspecialchars($source) ?>'">Continue to Registration</button>

        <div class="back-link">
            <a href="verify_email.php?cancel=1&source=<?= htmlspecialchars($source) ?>">Cancel &amp; Return</a>
        </div>
    <?php endif; ?>

    <div class="footer">Secure your account</div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.body.addEventListener('touchmove', function(e) {
            if (!e.target.closest('.container')) {
                e.preventDefault();
            }
        }, { passive: false });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const codeInputs = document.querySelectorAll('.code-input');
        const hiddenInput = document.getElementById('otpCodeHidden');
        const verifyBtn = document.getElementById('verifyBtn');
        const verifyForm = document.getElementById('verifyForm');
        let isSubmitting = false;

        if (codeInputs.length > 0) {
            setTimeout(function() {
                if (codeInputs[0] && !codeInputs[0].disabled) {
                    codeInputs[0].focus();
                }
            }, 100);

            codeInputs.forEach((input, index) => {
                input.setAttribute('inputmode', 'numeric');
                input.setAttribute('pattern', '[0-9]');

                input.addEventListener('input', function(e) {
                    this.value = this.value.replace(/\D/g, '');

                    if (this.value.length === 1 && index < codeInputs.length - 1) {
                        codeInputs[index + 1].focus();
                    }

                    updateHiddenCode();

                    if (!isSubmitting && isCodeComplete()) {
                        isSubmitting = true;
                        setTimeout(function() {
                            if (verifyBtn) { verifyBtn.disabled = true; verifyBtn.textContent = 'Verifying...'; }
                            verifyForm.submit();
                        }, 120);
                    }
                });

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && this.value === '' && index > 0) {
                        codeInputs[index - 1].focus();
                        codeInputs[index - 1].value = '';
                        updateHiddenCode();
                    }

                    if (e.key === 'ArrowLeft' && index > 0) {
                        e.preventDefault();
                        codeInputs[index - 1].focus();
                    }

                    if (e.key === 'ArrowRight' && index < codeInputs.length - 1) {
                        e.preventDefault();
                        codeInputs[index + 1].focus();
                    }

                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (isCodeComplete() && !isSubmitting) {
                            isSubmitting = true;
                            verifyForm.submit();
                        }
                    }
                });

                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const paste = (e.clipboardData || window.clipboardData).getData('text');
                    const digits = paste.replace(/\D/g, '').slice(0, 6);
                    const digitArray = digits.split('');

                    digitArray.forEach((digit, i) => {
                        if (i < codeInputs.length) {
                            codeInputs[i].value = digit;
                        }
                    });

                    let nextIndex = Math.min(digitArray.length, codeInputs.length - 1);
                    if (nextIndex < codeInputs.length) {
                        codeInputs[nextIndex].focus();
                    }

                    updateHiddenCode();

                    if (!isSubmitting && isCodeComplete()) {
                        isSubmitting = true;
                        setTimeout(function() {
                            if (verifyBtn) { verifyBtn.disabled = true; verifyBtn.textContent = 'Verifying...'; }
                            verifyForm.submit();
                        }, 120);
                    }
                });

                input.addEventListener('focus', function() {
                    this.select();
                });
            });
        }

        function isCodeComplete() {
            for (let i = 0; i < codeInputs.length; i++) {
                if (codeInputs[i].value === '' || !/^\d$/.test(codeInputs[i].value)) {
                    return false;
                }
            }
            return true;
        }

        function updateHiddenCode() {
            if (hiddenInput) {
                let code = '';
                codeInputs.forEach(input => {
                    code += input.value;
                });
                hiddenInput.value = code;
            }
        }
    });

    <?php if ($step === 'success'): ?>
    setTimeout(function() {
        window.location.href = 'verify_email.php?continue=1&source=<?= htmlspecialchars($source) ?>&source=<?= htmlspecialchars($source) ?>';
    }, 5000);
    <?php endif; ?>
</script>

</body>
</html>