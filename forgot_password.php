<?php
// forgot_password.php
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

// ==================== DETECT LOGGED-IN MODE ====================
$isLoggedIn      = isset($_SESSION['user_email']) && !empty($_SESSION['user_email']);
$loggedInEmail   = $isLoggedIn ? strtolower(trim($_SESSION['user_email'])) : '';

// ==================== FETCH MAILER CREDENTIALS ====================
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
} catch (Exception $e) {}

// ==================== BREVO API MAILER ====================
function sendResetEmail($email, $code, $mailer_email, $brevo_api_key) {

    if (empty($mailer_email) || empty($brevo_api_key)) {
        $_SESSION['reset_error'] = 'Mail configuration missing. Please contact support.';
        return false;
    }

    $safeCode = htmlspecialchars($code);

    $payload = [
        'sender' => ['name' => 'HarvHub', 'email' => $mailer_email],
        'to' => [['email' => $email]],
        'subject' => 'Password reset code',
        'htmlContent' =>
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
            . '<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">'
            . '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#f4f5f7;opacity:0;">Reset your password — use this code to verify your identity.</div>'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f5f7;padding:40px 16px;"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background-color:#ffffff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.05);overflow:hidden;">'
            . '<tr><td style="padding:32px 40px 8px 40px;text-align:center;">'
            . '<div style="display:inline-block;width:56px;height:56px;line-height:56px;border-radius:14px;background-color:#2e8b57;color:#ffffff;font-weight:700;font-size:26px;text-align:center;">H</div>'
            . '<h1 style="margin:16px 0 0 0;font-size:22px;font-weight:700;color:#111827;letter-spacing:-0.2px;">HarvHub</h1>'
            . '</td></tr>'
            . '<tr><td style="padding:24px 40px 8px 40px;">'
            . '<h2 style="margin:0 0 12px 0;font-size:18px;font-weight:600;color:#111827;">Reset your password</h2>'
            . '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#4b5563;">We received a request to reset your HarvHub password. Enter the verification code below to continue and set a new password.</p>'
            . '</td></tr>'
            . '<tr><td style="padding:8px 40px 8px 40px;">'
            . '<div style="background-color:#f0f9f4;border:1px solid #d6ede0;border-radius:10px;padding:24px;text-align:center;">'
            . '<p style="margin:0 0 8px 0;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#4b5563;font-weight:600;">Verification code</p>'
            . '<div style="font-family:\'SF Mono\',Menlo,Consolas,\'Courier New\',monospace;font-size:34px;font-weight:700;letter-spacing:10px;color:#2e8b57;padding-left:10px;">' . $safeCode . '</div>'
            . '<p style="margin:12px 0 0 0;font-size:13px;color:#6b7280;">This code expires in 15 minutes</p>'
            . '</div></td></tr>'
            . '<tr><td style="padding:16px 40px 8px 40px;">'
            . '<p style="margin:0;font-size:14px;line-height:1.6;color:#4b5563;">Enter this code on the password reset page to set a new password. For your security, do not share this code with anyone.</p>'
            . '</td></tr>'
            . '<tr><td style="padding:16px 40px 8px 40px;">'
            . '<p style="margin:0;font-size:14px;line-height:1.6;color:#4b5563;">If you did not request a password reset, you can safely disregard this message — no action is required.</p>'
            . '</td></tr>'
            . '<tr><td style="padding:24px 40px 0 40px;"><div style="border-top:1px solid #e5e7eb;"></div></td></tr>'
            . '<tr><td style="padding:20px 40px 32px 40px;">'
            . '<p style="margin:0 0 6px 0;font-size:13px;color:#6b7280;">This is an automated message from HarvHub. Please do not reply to this email.</p>'
            . '<p style="margin:0;font-size:12px;color:#9ca3af;">&copy; ' . date('Y') . ' HarvHub. All rights reserved.</p>'
            . '</td></tr></table>'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;margin-top:20px;">'
            . '<tr><td style="padding:0 8px;text-align:center;font-size:12px;color:#9ca3af;line-height:1.6;">For your security, HarvHub will never ask for your password or verification code via email, phone, or chat.</td></tr>'
            . '</table></td></tr></table></body></html>',
        'textContent' =>
            "Reset your password — use this code to verify your identity.\n\nHARVHUB\n=====================================\n\nReset your password\n\nWe received a request to reset your HarvHub password.\nEnter the verification code below to continue and set\na new password.\n\nVERIFICATION CODE: $code\n\nThis code expires in 15 minutes.\n\nEnter this code on the password reset page to set a new\npassword. For your security, do not share this code\nwith anyone.\n\nIf you did not request a password reset, you can safely\ndisregard this message - no action is required.\n\n-------------------------------------\nThis is an automated message from HarvHub.\nPlease do not reply to this email.\n\n© " . date('Y') . " HarvHub. All rights reserved.\n\nFor your security, HarvHub will never ask for your\npassword or verification code via email, phone, or chat.\n",
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

    if ($httpCode >= 200 && $httpCode < 300) return true;

    $_SESSION['reset_error'] = 'Failed to send reset email. Please try again.';
    return false;
}

function generateResetCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function storeResetCode($pdo, $email, $code) {
    try {
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);

        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, reset_code, expires_at) VALUES (?, ?, ?)");
        return $stmt->execute([$email, $code, $expires_at]);
    } catch (PDOException $e) {
        return false;
    }
}

function verifyResetCode($pdo, $email, $code) {
    try {
        $stmt = $pdo->prepare("SELECT id, reset_code, used, expires_at FROM password_resets WHERE email = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$email]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) return ['valid' => false, 'message' => 'No reset request found. Please request a new code.'];
        if ($record['used'] == 1) return ['valid' => false, 'message' => 'This code has already been used. Please request a new code.'];

        $expires_at = strtotime($record['expires_at']);
        if ($expires_at < time()) return ['valid' => false, 'message' => 'Reset code has expired. Please request a new code.'];
        if ($record['reset_code'] !== $code) return ['valid' => false, 'message' => 'Invalid verification code. Please try again.'];

        return ['valid' => true, 'message' => 'Code verified successfully.'];
    } catch (PDOException $e) {
        return ['valid' => false, 'message' => 'Database error occurred. Please try again.'];
    }
}

function markCodeAsUsed($pdo, $email) {
    try {
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ? ORDER BY created_at DESC LIMIT 1");
        return $stmt->execute([$email]);
    } catch (PDOException $e) {
        return false;
    }
}

function updatePassword($pdo, $email, $new_password) {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE harvhub SET password = ? WHERE email = ?");
    return $stmt->execute([$hashed, $email]);
}

function maskEmail($email) {
    $parts = explode('@', $email);
    $username = $parts[0];
    $domain = $parts[1] ?? '';

    if (strlen($username) <= 2) $masked = $username;
    else $masked = substr($username, 0, 2) . str_repeat('*', strlen($username) - 4) . substr($username, -2);

    return $masked . '@' . $domain;
}

// ==================== GET SOURCE ====================
$source = isset($_GET['source']) ? $_GET['source'] : (isset($_SESSION['reset_source']) ? $_SESSION['reset_source'] : 'index');
$_SESSION['reset_source'] = $source;

if ($source === 'dev_login') {
    $return_url = 'dev_login.php';
} elseif ($source === 'app') {
    $return_url = 'investorapp.php';
} elseif ($source === 'mydashboard' || $source === 'dashboard') {
    $return_url = 'investorapp.php';
} else {
    $return_url = 'index.php';
}

$email = $_SESSION['pending_reset_email'] ?? '';
$step = $_SESSION['reset_step'] ?? 'request';
$error = $_SESSION['reset_error'] ?? '';
$success = $_SESSION['reset_success'] ?? '';
$return_to = $_SESSION['return_after_reset'] ?? $return_url;

if ($isLoggedIn && $step === 'request' && empty($_SESSION['pending_reset_email'])) {
    $_SESSION['pending_reset_email'] = $loggedInEmail;
    $email = $loggedInEmail;
}

// ==================== HANDLE REQUEST RESET ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_reset') {
    if ($isLoggedIn) {
        $email = $loggedInEmail;
    } else {
        $email = trim(strtolower($_POST['email'] ?? ''));
        if ($email === '') $email = trim(strtolower($_SESSION['pending_reset_email'] ?? ''));
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['reset_error'] = 'Please enter a valid email address.';
        header('Location: forgot_password.php?source=' . $source);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM harvhub WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['reset_error'] = 'No account found with this email address.';
        header('Location: forgot_password.php?source=' . $source);
        exit;
    }

    $code = generateResetCode();
    $maskedEmail = maskEmail($email);

    if (!storeResetCode($pdo, $email, $code)) {
        $_SESSION['reset_error'] = 'Failed to generate reset code. Please try again.';
        header('Location: forgot_password.php?source=' . $source);
        exit;
    }

    if (sendResetEmail($email, $code, $mailer_email, $brevo_api_key)) {
        $_SESSION['pending_reset_email'] = $email;
        $_SESSION['reset_step'] = 'verify';
        $_SESSION['reset_success'] = "A verification code has been sent to {$maskedEmail}.";
        unset($_SESSION['reset_error']);
    } else {
        if (empty($_SESSION['reset_error'])) {
            $_SESSION['reset_error'] = 'Failed to send reset email. Please try again or contact support.';
        }
    }

    header('Location: forgot_password.php?source=' . $source);
    exit;
}

// ==================== HANDLE VERIFY CODE ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_code') {
    $code = trim($_POST['reset_code'] ?? '');
    $email = $_SESSION['pending_reset_email'] ?? '';

    if (empty($email)) {
        $_SESSION['reset_error'] = 'Session expired. Please start over.';
        $_SESSION['reset_step'] = 'request';
        unset($_SESSION['pending_reset_email']);
        header('Location: forgot_password.php?source=' . $source);
        exit;
    }

    if (empty($code) || strlen($code) !== 6) {
        $_SESSION['reset_error'] = 'Please enter the complete 6-digit verification code.';
        header('Location: forgot_password.php?source=' . $source);
        exit;
    }

    $result = verifyResetCode($pdo, $email, $code);

    if ($result['valid']) {
        markCodeAsUsed($pdo, $email);
        $_SESSION['reset_step'] = 'reset';
        $_SESSION['reset_success'] = 'Code verified! Please set your new password.';
        unset($_SESSION['reset_error']);
    } else {
        $_SESSION['reset_error'] = $result['message'];
    }

    header('Location: forgot_password.php?source=' . $source);
    exit;
}

// ==================== HANDLE RESET PASSWORD ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = $_SESSION['pending_reset_email'] ?? '';

    if (empty($email) || ($_SESSION['reset_step'] ?? '') !== 'reset') {
        $_SESSION['reset_error'] = 'Session expired. Please start over.';
        $_SESSION['reset_step'] = 'request';
        unset($_SESSION['pending_reset_email']);
        header('Location: forgot_password.php?source=' . $source);
        exit;
    }

    if (empty($new_password) || strlen($new_password) < 4) {
        $_SESSION['reset_error'] = 'Password must be at least 4 characters long.';
        header('Location: forgot_password.php?source=' . $source);
        exit;
    }

    if ($new_password !== $confirm_password) {
        $_SESSION['reset_error'] = 'Passwords do not match.';
        header('Location: forgot_password.php?source=' . $source);
        exit;
    }

    if (updatePassword($pdo, $email, $new_password)) {
        unset($_SESSION['pending_reset_email']);
        unset($_SESSION['reset_step']);
        unset($_SESSION['reset_success']);
        $_SESSION['reset_error'] = '';

        $_SESSION['reset_complete'] = true;
        $_SESSION['reset_complete_email'] = $email;
        $_SESSION['reset_step'] = 'success';

        header('Location: forgot_password.php?source=' . $source);
        exit;
    } else {
        $_SESSION['reset_error'] = 'Failed to update password. Please try again.';
        header('Location: forgot_password.php?source=' . $source);
        exit;
    }
}

// ==================== HANDLE RESEND CODE ====================
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    $email = $_SESSION['pending_reset_email'] ?? '';

    if (!empty($email)) {
        $code = generateResetCode();
        $maskedEmail = maskEmail($email);

        if (storeResetCode($pdo, $email, $code)) {
            if (sendResetEmail($email, $code, $mailer_email, $brevo_api_key)) {
                $_SESSION['reset_success'] = "A new verification code has been sent to {$maskedEmail}.";
                unset($_SESSION['reset_error']);
            } else {
                if (empty($_SESSION['reset_error'])) {
                    $_SESSION['reset_error'] = 'Failed to send email. Please try again.';
                }
            }
        } else {
            $_SESSION['reset_error'] = 'Failed to generate new code. Please try again.';
        }
    }

    header('Location: forgot_password.php?source=' . $source);
    exit;
}

// ==================== HANDLE CANCEL ====================
if (isset($_GET['cancel'])) {
    unset($_SESSION['pending_reset_email']);
    unset($_SESSION['reset_step']);
    unset($_SESSION['reset_success']);
    unset($_SESSION['reset_error']);
    unset($_SESSION['reset_complete']);
    unset($_SESSION['reset_complete_email']);
    unset($_SESSION['return_after_reset']);
    unset($_SESSION['reset_source']);

    if (!$isLoggedIn) {
        $is_approved = $_SESSION['is_approved_user'] ?? false;
        if (!$is_approved) {
            unset($_SESSION['user_email']);
            session_destroy();
        }
    }

    $cancelTarget = ($source === 'dev_login') ? 'dev_login.php' : 'investorapp.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Returning…</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin:0;background:#f0f4f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
        <div style="display:flex;align-items:center;justify-content:center;min-height:100vh;color:#8a9aa8;font-size:0.9rem;font-weight:600;">
            Returning…
        </div>
        <script>
            (function () {
                var target = <?= json_encode($cancelTarget) ?>;
                try {
                    if (window.top && window.top !== window) {
                        window.top.location.href = target;
                        return;
                    }
                } catch (e) {}
                window.location.href = target;
            })();
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ==================== HANDLE CONTINUE ====================
if (isset($_GET['continue'])) {
    if (isset($_SESSION['reset_complete']) && $_SESSION['reset_complete'] === true) {
        $return_to = $_SESSION['return_after_reset'] ?? $return_url;

        unset($_SESSION['pending_reset_email']);
        unset($_SESSION['reset_step']);
        unset($_SESSION['reset_success']);
        unset($_SESSION['reset_error']);
        unset($_SESSION['reset_complete']);
        unset($_SESSION['reset_complete_email']);
        unset($_SESSION['return_after_reset']);
        unset($_SESSION['reset_source']);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Returning…</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin:0;background:#f0f4f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
            <div style="display:flex;align-items:center;justify-content:center;min-height:100vh;color:#8a9aa8;font-size:0.9rem;font-weight:600;">
                Returning…
            </div>
            <script>
                (function () {
                    var target = <?= json_encode($return_to) ?>;
                    try {
                        if (window.top && window.top !== window) {
                            window.top.location.href = target;
                            return;
                        }
                    } catch (e) {}
                    window.location.href = target;
                })();
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    header('Location: forgot_password.php?source=' . $source);
    exit;
}

// ==================== GET CURRENT STATE ====================
$email = $_SESSION['pending_reset_email'] ?? '';
$step = $_SESSION['reset_step'] ?? 'request';
$error = $_SESSION['reset_error'] ?? '';
$success = $_SESSION['reset_success'] ?? '';
$maskedEmail = !empty($email) ? maskEmail($email) : '';

if ($step === 'success' && !empty($_SESSION['reset_complete_email'])) {
    $email = $_SESSION['reset_complete_email'];
}

$back_link = $return_url;

unset($_SESSION['reset_error']);
unset($_SESSION['reset_success']);

// ==================== DARK MODE ====================
$darkModeClass = '';

if (!empty($email)) {
    try {
        $stmtDark = $pdo->prepare("SELECT dark_mode FROM harvhub WHERE email = ? LIMIT 1");
        $stmtDark->execute([$email]);
        $darkRow = $stmtDark->fetch(PDO::FETCH_ASSOC);
        if ($darkRow && (int)$darkRow['dark_mode'] === 1) $darkModeClass = 'dark-mode';
    } catch (Exception $e) {}
}

if ($darkModeClass === '' && isset($_SESSION['user_email'])) {
    try {
        $stmtDark = $pdo->prepare("SELECT dark_mode FROM harvhub WHERE email = ? LIMIT 1");
        $stmtDark->execute([strtolower($_SESSION['user_email'])]);
        $darkRow = $stmtDark->fetch(PDO::FETCH_ASSOC);
        if ($darkRow && (int)$darkRow['dark_mode'] === 1) $darkModeClass = 'dark-mode';
    } catch (Exception $e) {}
}

$showEmailInput = !$isLoggedIn;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<title>Forgot Password - HarvHub</title>
<style>
    html, body { margin: 0; padding: 0; }

    body.fp-body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        background: var(--bg);
        color: var(--text);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        box-sizing: border-box;
        transition: background 0.3s ease, color 0.3s ease;
    }

    body.fp-body::before {
        content: "";
        position: fixed;
        inset: 0;
        background:
            radial-gradient(circle at 20% 80%, var(--accent-light) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, var(--accent-light) 0%, transparent 50%);
        opacity: 0.5;
        pointer-events: none;
        z-index: 0;
        transition: opacity 0.3s ease;
    }

    body.fp-body.dark-mode::before { opacity: 0.25; }

    .fp-container {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius, 16px);
        padding: 30px 25px;
        max-width: 500px;
        width: 100%;
        position: relative;
        z-index: 1;
        box-shadow: var(--shadow-lg);
        max-height: 95vh;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: var(--accent) transparent;
        transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .fp-container::-webkit-scrollbar { width: 6px; }
    .fp-container::-webkit-scrollbar-track { background: transparent; }
    .fp-container::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 10px; }
    .fp-container::-webkit-scrollbar-thumb:hover { background: var(--accent); }

    .fp-container h2 { color: var(--accent); margin: 0 0 8px 0; text-align: center; font-size: 1.2rem; font-weight: 700; }

    .fp-description { text-align: center; color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 20px; line-height: 1.5; }

    .fp-container label { display: block; font-weight: 600; margin-bottom: 5px; color: var(--text-secondary); font-size: 0.85rem; }

    .fp-input {
        width: 100%; padding: 12px 14px; border-radius: 10px; border: 1px solid var(--input-border);
        background: var(--input-bg); color: var(--input-text); font-size: 16px !important; font-family: inherit;
        box-sizing: border-box;
        transition: border-color 0.3s ease, background 0.3s ease, color 0.3s ease, box-shadow 0.3s ease;
        -webkit-appearance: none; appearance: none;
    }

    .fp-input::placeholder { color: var(--input-placeholder); }
    .fp-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.15); }
    .fp-input[readonly] { background: var(--bg); color: var(--text-secondary); cursor: default; }

    .fp-btn {
        width: 100%; padding: 12px; font-weight: 700; font-size: 0.95rem; font-family: inherit;
        border: none; border-radius: 10px; cursor: pointer; transition: all 0.3s ease; margin-top: 5px;
        -webkit-tap-highlight-color: transparent;
    }

    body.dark-mode .fp-btn { background: var(--accent); color: #000; }
    body:not(.dark-mode) .fp-btn { background: var(--accent); color: #fff; }
    .fp-btn:hover { opacity: 0.9; transform: scale(1.01); }
    .fp-btn:active { transform: scale(0.98); }
    .fp-btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

    .fp-btn-secondary {
        width: 100%; padding: 12px; font-weight: 600; font-size: 0.9rem; font-family: inherit;
        border-radius: 10px; cursor: pointer; background: transparent; color: var(--text-secondary);
        border: 1px solid var(--border-color); transition: all 0.2s ease; -webkit-tap-highlight-color: transparent;
    }

    .fp-btn-secondary:hover { background: var(--bg); color: var(--text); }

    .fp-error {
        text-align: center; margin: 10px 0; font-size: 0.85rem; padding: 8px 12px; border-radius: 8px;
        word-break: break-word; background: var(--danger-bg); color: var(--danger); 
    }

    .fp-success {
        text-align: center; margin: 10px 0; font-size: 0.85rem; padding: 8px 12px; border-radius: 8px;
        word-break: break-word; background: var(--success-bg); color: var(--success); 
    }

    .fp-info-box {
        text-align: center; margin: 10px 0 15px 0; font-size: 0.88rem; padding: 10px 14px; border-radius: 8px;
        word-break: break-word; background: var(--bg); color: var(--text-secondary);
        border: 1px solid var(--border-color); line-height: 1.5;
    }

    .fp-info-box strong { color: var(--accent); }

    .fp-code-container { display: flex; gap: 8px; justify-content: center; margin: 15px 0 10px; flex-wrap: nowrap; }

    .fp-code-container input {
        width: 45px; height: 50px; text-align: center; font-size: 1.4rem; font-weight: bold;
        border-radius: 10px; padding: 0;
        border: 1px solid var(--input-border); background: var(--input-bg); color: var(--input-text);
        transition: border-color 0.3s ease, background 0.3s ease, color 0.3s ease, box-shadow 0.3s ease;
        -webkit-appearance: none; appearance: none; -moz-appearance: textfield; flex-shrink: 0;
        font-family: inherit; box-sizing: border-box;
    }

    .fp-code-container input::-webkit-outer-spin-button,
    .fp-code-container input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

    .fp-code-container input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 15px rgba(46, 139, 87, 0.2); }
    .fp-code-container input:disabled { opacity: 0.5; cursor: not-allowed; }

    .fp-password-wrapper { position: relative; margin-bottom: 10px; }
    .fp-password-wrapper .fp-input { padding-right: 55px; }

    .fp-password-toggle {
        position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
        cursor: pointer; font-size: 0.8rem; font-family: inherit; user-select: none;
        background: transparent; border: none; padding: 4px 8px; border-radius: 5px;
        color: var(--text-muted); transition: color 0.2s ease; -webkit-tap-highlight-color: transparent;
    }

    .fp-password-toggle:hover { color: var(--accent); }

    .fp-resend-link { text-align: center; margin: 12px 0 5px; font-size: 0.85rem; }
    .fp-resend-link a { color: var(--accent); text-decoration: none; transition: opacity 0.3s ease; }
    .fp-resend-link a:hover { text-decoration: underline; opacity: 0.8; }

    .fp-back-link { text-align: center; margin-top: 15px; }
    .fp-back-link a { color: var(--accent); text-decoration: none; font-size: 0.85rem; transition: opacity 0.3s ease; }
    .fp-back-link a:hover { text-decoration: underline; opacity: 0.8; }

    .fp-success-icon { font-size: 4rem; text-align: center; margin: 10px 0; }

    .fp-success-details {
        border-radius: 12px; padding: 15px; margin: 15px 0;
        background: var(--success-bg); border: 1px solid var(--border-color); color: var(--text);
    }

    .fp-success-details p { margin: 5px 0; }
    .fp-success-details strong { color: var(--accent); }

    .fp-footer { text-align: center; margin-top: 15px; font-size: 0.7rem; color: var(--text-muted); }

    @media (max-width: 480px) {
        .fp-container { padding: 20px 15px; max-height: 90vh; }
        .fp-code-container input { width: 38px; height: 44px; font-size: 1.2rem; }
        .fp-code-container { gap: 5px; }
        .fp-container h2 { font-size: 1.1rem; }
        .fp-input { padding: 10px 12px; font-size: 0.9rem; }
        .fp-btn { padding: 10px; font-size: 0.9rem; }
        .fp-success-icon { font-size: 3rem; }
    }

    @media (max-width: 380px) {
        .fp-code-container input { width: 32px; height: 38px; font-size: 1rem; }
        .fp-code-container { gap: 4px; }
        .fp-container { padding: 15px 12px; }
        .fp-description { font-size: 0.8rem; }
    }

    @media (min-width: 768px) {
        .fp-container { padding: 40px 35px; }
        .fp-code-container input { width: 55px; height: 60px; font-size: 1.6rem; }
        .fp-code-container { gap: 12px; }
    }
</style>
<?php include 'style.php'; ?>
</head>
<body class="fp-body <?= htmlspecialchars($darkModeClass) ?>">

<div class="fp-container">
    <?php if ($step === 'request'): ?>

        <?php if ($isLoggedIn): ?>
            <h2>Change Password</h2>
            <p class="fp-description">To change your password, we'll send a verification code to the email address on your account.</p>

            <?php if (!empty($error)): ?>
                <div class="fp-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="fp-info-box">
                Sending code to <strong><?= htmlspecialchars($loggedInEmail) ?></strong>
            </div>

            <form method="POST" action="forgot_password.php?source=<?= htmlspecialchars($source) ?>">
                <input type="hidden" name="action" value="request_reset">
                <button type="submit" class="fp-btn">Send Verification Code</button>
            </form>

            <div class="fp-back-link">
                <a href="forgot_password.php?cancel=1&source=<?= htmlspecialchars($source) ?>">Cancel &amp; Return</a>
            </div>

        <?php else: ?>
            <h2>Forgot Password?</h2>
            <p class="fp-description">Enter the email address associated with your account. We'll send a verification code to reset your password.</p>

            <?php if (!empty($error)): ?>
                <div class="fp-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="forgot_password.php?source=<?= htmlspecialchars($source) ?>">
                <input type="hidden" name="action" value="request_reset">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" class="fp-input" placeholder="youremail@gmail.com" value="<?= htmlspecialchars($email) ?>" required>
                <button type="submit" class="fp-btn">Send Reset Code</button>
            </form>

            <div class="fp-back-link">
                <a href="forgot_password.php?cancel=1&source=<?= htmlspecialchars($source) ?>">Cancel &amp; Return</a>
            </div>
        <?php endif; ?>

    <?php elseif ($step === 'verify'): ?>
        <h2>Enter Reset Code</h2>
        <p class="fp-success">We sent a 6-digit verification code to <?= htmlspecialchars($maskedEmail) ?></p>
        <?php if (!empty($error)): ?>
            <div class="fp-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php?source=<?= htmlspecialchars($source) ?>" id="verifyForm">
            <input type="hidden" name="action" value="verify_code">
            <label>Enter 6-Digit Code</label>
            <div class="fp-code-container" id="codeContainer">
                <input type="text" maxlength="1" class="fp-code-input" data-index="0" autofocus required inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="fp-code-input" data-index="1" required inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="fp-code-input" data-index="2" required inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="fp-code-input" data-index="3" required inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="fp-code-input" data-index="4" required inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="fp-code-input" data-index="5" required inputmode="numeric" pattern="[0-9]">
            </div>
            <input type="hidden" name="reset_code" id="resetCodeHidden" value="">
            <button type="submit" class="fp-btn" id="verifyBtn">Confirm</button>
        </form>

        <div class="fp-resend-link">
            <a href="forgot_password.php?resend=1&source=<?= htmlspecialchars($source) ?>" id="resendLink">Resend Code</a> &nbsp;|&nbsp;
            <a href="forgot_password.php?cancel=1&source=<?= htmlspecialchars($source) ?>">Cancel</a>
        </div>

    <?php elseif ($step === 'reset'): ?>
        <h2>Set New Password</h2>
        <p class="fp-description">Create a new password for your account. Make sure it's something you'll remember.</p>
        <?php if (!empty($error)): ?>
            <div class="fp-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="fp-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php?source=<?= htmlspecialchars($source) ?>" id="resetForm">
            <input type="hidden" name="action" value="reset_password">

            <label for="new_password">New Password</label>
            <div class="fp-password-wrapper">
                <input type="password" name="new_password" id="new_password" class="fp-input" placeholder="Min 4 characters" required>
                <button type="button" class="fp-password-toggle" onclick="togglePassword('new_password', this)">Show</button>
            </div>

            <label for="confirm_password">Confirm Password</label>
            <div class="fp-password-wrapper">
                <input type="password" name="confirm_password" id="confirm_password" class="fp-input" placeholder="Confirm your new password" required>
                <button type="button" class="fp-password-toggle" onclick="togglePassword('confirm_password', this)">Show</button>
            </div>

            <button type="submit" class="fp-btn">Update Password</button>
        </form>

        <div class="fp-back-link">
            <a href="forgot_password.php?cancel=1&source=<?= htmlspecialchars($source) ?>">Cancel</a>
        </div>

    <?php elseif ($step === 'success'): ?>
        <div class="fp-success-icon">✅</div>
        <h2>Password Updated!</h2>
        <p class="fp-description">Your password has been successfully reset. You can now log in to your account with your new password.</p>

        <div class="fp-success-details">
            <p><strong>Email:</strong> <?= htmlspecialchars($email) ?></p>
            <p><strong>Status:</strong> <span style="color: var(--accent);">Password Reset ✓</span></p>
        </div>

        <button class="fp-btn" onclick="window.location.href='forgot_password.php?continue=1&source=<?= htmlspecialchars($source) ?>'">Continue</button>

        <div class="fp-back-link">
            <a href="forgot_password.php?cancel=1&source=<?= htmlspecialchars($source) ?>">Cancel &amp; Return</a>
        </div>
    <?php endif; ?>

    <div class="fp-footer">Secure your account</div>
</div>

<script>
    // =====================================================================
    // SHELL BRIDGE — hide header + bottom nav in the investor shell.
    // =====================================================================
    (function () {
        document.body.classList.add('page-forgot_password');

        window.addEventListener('message', function (e) {
            if (!e.data || typeof e.data !== 'object') return;
            if (e.data.type === 'theme') {
                document.body.classList.toggle('dark-mode', !!e.data.dark);
            }
        });

        var WATCHED = [
            'page-connect_investor_broker',
            'profile-page-open',
            'page-revenue_history',
            'page-profit_split',
            'page-vps',
            'page-disconnect_broker',
            'page-programmes',
            'page-verify_code',
            'page-forgot_password'
        ];
        function broadcast() {
            var add = WATCHED.filter(function (c) { return document.body.classList.contains(c); });
            try {
                window.parent.postMessage({
                    type: 'bodyClass',
                    add: add,
                    remove: WATCHED.filter(function (c) { return add.indexOf(c) === -1; })
                }, '*');
            } catch (e) {}
        }
        new MutationObserver(broadcast).observe(document.body, { attributes: true, attributeFilter: ['class'] });
        broadcast();
        try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}
    })();

    document.addEventListener('DOMContentLoaded', function() {
        document.body.addEventListener('touchmove', function(e) {
            if (!e.target.closest('.fp-container')) {
                e.preventDefault();
            }
        }, { passive: false });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const codeInputs = document.querySelectorAll('.fp-code-input');
        const hiddenInput = document.getElementById('resetCodeHidden');
        const verifyBtn = document.getElementById('verifyBtn');
        const verifyForm = document.getElementById('verifyForm');
        let isSubmitting = false;

        if (codeInputs.length > 0) {
            setTimeout(function() {
                if (codeInputs[0] && !codeInputs[0].disabled) codeInputs[0].focus();
            }, 100);

            codeInputs.forEach((input, index) => {
                input.setAttribute('inputmode', 'numeric');
                input.setAttribute('pattern', '[0-9]');

                input.addEventListener('input', function() {
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
                    if (e.key === 'ArrowLeft' && index > 0) { e.preventDefault(); codeInputs[index - 1].focus(); }
                    if (e.key === 'ArrowRight' && index < codeInputs.length - 1) { e.preventDefault(); codeInputs[index + 1].focus(); }
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
                        if (i < codeInputs.length) codeInputs[i].value = digit;
                    });

                    let nextIndex = Math.min(digitArray.length, codeInputs.length - 1);
                    if (nextIndex < codeInputs.length) codeInputs[nextIndex].focus();

                    updateHiddenCode();

                    if (!isSubmitting && isCodeComplete()) {
                        isSubmitting = true;
                        setTimeout(function() {
                            if (verifyBtn) { verifyBtn.disabled = true; verifyBtn.textContent = 'Verifying...'; }
                            verifyForm.submit();
                        }, 120);
                    }
                });

                input.addEventListener('focus', function() { this.select(); });
            });
        }

        function isCodeComplete() {
            for (let i = 0; i < codeInputs.length; i++) {
                if (codeInputs[i].value === '' || !/^\d$/.test(codeInputs[i].value)) return false;
            }
            return true;
        }

        function updateHiddenCode() {
            if (hiddenInput) {
                let code = '';
                codeInputs.forEach(input => { code += input.value; });
                hiddenInput.value = code;
            }
        }
    });

    function togglePassword(inputId, button) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            button.textContent = 'Hide';
        } else {
            input.type = 'password';
            button.textContent = 'Show';
        }
    }

    <?php if ($step === 'success'): ?>
    setTimeout(function() {
        window.location.href = 'forgot_password.php?continue=1&source=<?= htmlspecialchars($source) ?>';
    }, 5000);
    <?php endif; ?>
</script>

</body>
</html>