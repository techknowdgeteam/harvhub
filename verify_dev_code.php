<?php
// verify_dev_code.php
// Generic email verification gate for dev panel.
// Accepts: ?source=<page_to_return_to>&action=<optional_action_label>
// On success, sets $_SESSION['verified_<source>'] = true,
// then redirects back to:  <source>.php?verified=1

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

// ==================== CHECK LOGIN ====================
if (!isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit;
}

$email = strtolower($_SESSION['user_email']);

// ==================== SOURCE / ACTION PARAMS ====================
$source = isset($_GET['source']) ? preg_replace('/[^a-z0-9_]/i', '', $_GET['source']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : 'Verify Identity';

if (empty($source)) {
    header("Location: dev_app.php");
    exit;
}

$source_file = $source . '.php';

$allowed_sources = ['disconnect_dev_broker', 'connect_dev_broker', 'mydashboard'];
if (!in_array($source, $allowed_sources, true)) {
    header("Location: dev_app.php");
    exit;
}

$session_key = 'verified_' . $source;

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
} catch (Exception $e) {
    // silent
}

// ==================== FETCH USER ====================
$stmt = $pdo->prepare("SELECT fullname, email FROM harvhub WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: index.php");
    exit;
}

$fullname = $user['fullname'] ?? 'User';

// ==================== HELPERS ====================
function generateCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function maskEmail($email) {
    $parts    = explode('@', $email);
    $username = $parts[0] ?? '';
    $domain   = $parts[1] ?? '';
    if (strlen($username) <= 2) {
        $masked = $username;
    } else {
        $masked = substr($username, 0, 2)
                . str_repeat('*', max(0, strlen($username) - 4))
                . substr($username, -2);
    }
    return $masked . '@' . $domain;
}

function sendVerifyEmail($toEmail, $toName, $code, $action, $mailer_email, $brevo_api_key) {

    if (empty($mailer_email) || empty($brevo_api_key)) {
        return false;
    }

    $safeAction = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
    $safeCode   = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $safeName   = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');

    $payload = [
        'sender' => [
            'name'  => 'HarvHub Security',
            'email' => $mailer_email,
        ],
        'to' => [
            ['email' => $toEmail, 'name' => $safeName],
        ],
        'subject'     => 'Verification code for ' . $action,
        'htmlContent' =>
            '<!DOCTYPE html>'
            . '<html>'
            . '<head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '</head>'
            . '<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">'

            // Preheader — inbox preview line
            . '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#f4f5f7;opacity:0;">'
            . 'Complete your verification — use this code to confirm ' . $safeAction . '.'
            . '</div>'

            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f5f7;padding:40px 16px;">'
            . '<tr><td align="center">'

            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background-color:#ffffff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.05);overflow:hidden;">'

            // Header
            . '<tr><td style="padding:32px 40px 8px 40px;text-align:center;">'
            . '<div style="display:inline-block;width:56px;height:56px;line-height:56px;border-radius:14px;background-color:#2e8b57;color:#ffffff;font-weight:700;font-size:26px;text-align:center;">H</div>'
            . '<h1 style="margin:16px 0 0 0;font-size:22px;font-weight:700;color:#111827;letter-spacing:-0.2px;">HarvHub</h1>'
            . '</td></tr>'

            // Body
            . '<tr><td style="padding:24px 40px 8px 40px;">'
            . '<h2 style="margin:0 0 12px 0;font-size:18px;font-weight:600;color:#111827;">Complete your verification</h2>'
            . '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#4b5563;">'
            . 'You requested to perform: <strong style="color:#111827;">' . $safeAction . '</strong>.'
            . '</p>'
            . '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#4b5563;">'
            . 'Enter the verification code below to confirm this action.'
            . '</p>'
            . '</td></tr>'

            // Code block
            . '<tr><td style="padding:8px 40px 8px 40px;">'
            . '<div style="background-color:#f0f9f4;border:1px solid #d6ede0;border-radius:10px;padding:24px;text-align:center;">'
            . '<p style="margin:0 0 8px 0;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#4b5563;font-weight:600;">Verification code</p>'
            . '<div style="font-family:\'SF Mono\',Menlo,Consolas,\'Courier New\',monospace;font-size:34px;font-weight:700;letter-spacing:10px;color:#2e8b57;padding-left:10px;">'
            . $safeCode
            . '</div>'
            . '<p style="margin:12px 0 0 0;font-size:13px;color:#6b7280;">Use this code to continue</p>'
            . '</div>'
            . '</td></tr>'

            // Safety
            . '<tr><td style="padding:16px 40px 8px 40px;">'
            . '<p style="margin:0;font-size:14px;line-height:1.6;color:#4b5563;">'
            . 'If you did not request this action, please disregard this email and consider changing your password.'
            . '</p>'
            . '</td></tr>'

            // Divider
            . '<tr><td style="padding:24px 40px 0 40px;">'
            . '<div style="border-top:1px solid #e5e7eb;"></div>'
            . '</td></tr>'

            // Footer
            . '<tr><td style="padding:20px 40px 32px 40px;">'
            . '<p style="margin:0 0 6px 0;font-size:13px;color:#6b7280;">'
            . 'This is an automated security message from HarvHub. Please do not reply to this email.'
            . '</p>'
            . '<p style="margin:0;font-size:12px;color:#9ca3af;">'
            . '&copy; ' . date('Y') . ' HarvHub. All rights reserved.'
            . '</p>'
            . '</td></tr>'

            . '</table>'

            // Sub-footer
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
            "Complete your verification — use this code to confirm {$action}.\n\n"
            . "HARVHUB SECURITY\n"
            . "=====================================\n\n"
            . "Complete your verification\n\n"
            . "You requested to perform: {$action}\n\n"
            . "Enter the verification code below to confirm this\n"
            . "action.\n\n"
            . "VERIFICATION CODE: {$code}\n\n"
            . "If you did not request this action, please disregard\n"
            . "this email and consider changing your password.\n\n"
            . "-------------------------------------\n"
            . "This is an automated security message from HarvHub.\n"
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

    return ($httpCode >= 200 && $httpCode < 300);
}

// ==================== HANDLE: SEND CODE ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vc_action']) && $_POST['vc_action'] === 'send_code') {
    $code = generateCode();

    try {
        $del = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $del->execute([$email]);
    } catch (Exception $e) {}

    try {
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, reset_code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 YEAR))");
        $stmt->execute([$email, $code]);
    } catch (Exception $e) {
        $_SESSION['vc_error'] = 'Could not generate a code. Please try again.';
        header('Location: verify_dev_code.php?source=' . urlencode($source) . '&action=' . urlencode($action));
        exit;
    }

    if (sendVerifyEmail($email, $fullname, $code, $action, $mailer_email, $brevo_api_key)) {
        $_SESSION['vc_step']    = 'verify';
        $_SESSION['vc_success'] = 'A verification code has been sent to ' . maskEmail($email);
        unset($_SESSION['vc_error']);
    } else {
        $_SESSION['vc_error'] = 'Failed to send email. Please try again.';
    }

    header('Location: verify_dev_code.php?source=' . urlencode($source) . '&action=' . urlencode($action));
    exit;
}

// ==================== HANDLE: VERIFY CODE ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vc_action']) && $_POST['vc_action'] === 'verify_dev_code') {
    $entered = trim($_POST['vc_code'] ?? '');

    if (empty($entered) || strlen($entered) !== 6) {
        $_SESSION['vc_error'] = 'Please enter the complete 6-digit code.';
        header('Location: verify_dev_code.php?source=' . urlencode($source) . '&action=' . urlencode($action));
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, reset_code, used
            FROM password_resets
            WHERE email = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $rec = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rec) {
            $_SESSION['vc_error'] = 'No verification request found. Please request a new code.';
        } elseif ((int)$rec['used'] === 1) {
            $_SESSION['vc_error'] = 'This code has already been used.';
        } elseif ($rec['reset_code'] !== $entered) {
            $_SESSION['vc_error'] = 'Invalid verification code. Please try again.';
        } else {
            $upd = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $upd->execute([$rec['id']]);

            $_SESSION[$session_key] = true;

            unset($_SESSION['vc_step']);
            unset($_SESSION['vc_error']);
            unset($_SESSION['vc_success']);

            header('Location: ' . $source_file . '?verified=1');
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['vc_error'] = 'Verification failed. Please try again.';
    }

    header('Location: verify_dev_code.php?source=' . urlencode($source) . '&action=' . urlencode($action));
    exit;
}

// ==================== HANDLE: RESEND ====================
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    $code = generateCode();
    try {
        $del = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $del->execute([$email]);

        $stmt = $pdo->prepare("INSERT INTO password_resets (email, reset_code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 YEAR))");
        $stmt->execute([$email, $code]);

        if (sendVerifyEmail($email, $fullname, $code, $action, $mailer_email, $brevo_api_key)) {
            $_SESSION['vc_step']    = 'verify';
            $_SESSION['vc_success'] = 'A new code has been sent to ' . maskEmail($email);
            unset($_SESSION['vc_error']);
        } else {
            $_SESSION['vc_error'] = 'Failed to resend. Please try again.';
        }
    } catch (Exception $e) {
        $_SESSION['vc_error'] = 'Failed to resend. Please try again.';
    }
    header('Location: verify_dev_code.php?source=' . urlencode($source) . '&action=' . urlencode($action));
    exit;
}

// ==================== HANDLE: CANCEL ====================
if (isset($_GET['cancel']) && $_GET['cancel'] === '1') {
    unset($_SESSION['vc_step']);
    unset($_SESSION['vc_error']);
    unset($_SESSION['vc_success']);
    header('Location: ' . $source_file);
    exit;
}

// ==================== PULL STATE ====================
$step    = $_SESSION['vc_step']    ?? 'request';
$error   = $_SESSION['vc_error']   ?? '';
$success = $_SESSION['vc_success'] ?? '';

unset($_SESSION['vc_error']);
unset($_SESSION['vc_success']);

$maskedEmail = maskEmail($email);

$autoSend = ($step === 'request' && $_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['nosend']));

$darkMode = 0;
try {
    $stmt = $pdo->prepare("SELECT dark_mode FROM harvhub WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $dm = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dm) $darkMode = (int)$dm['dark_mode'];
} catch (Exception $e) {}
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<title>Verify Identity - HarvHub</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    html, body {
        height:100%;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background:#000;
        color:#e4e6eb;
        display:flex;
        align-items:center;
        justify-content:center;
        padding:20px;
        overflow:hidden;
        position:fixed;
        width:100%;
    }
    body::before {
        content:"";
        position:absolute; inset:0;
        background:
            radial-gradient(circle at 20% 80%, #1a0033 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, #000033 0%, transparent 50%);
        opacity:0.6;
        pointer-events:none;
    }
    .vc-container {
        position:relative;
        z-index:1;
        background: rgba(20,20,30,0.95);
        border:1px solid rgba(255,255,255,0.06);
        border-radius:20px;
        padding:30px 25px;
        max-width:500px;
        width:100%;
        box-shadow:0 20px 60px rgba(0,0,0,0.8);
        max-height:95vh;
        overflow-y:auto;
    }
    .vc-header { text-align:center; margin-bottom:18px; }
    .vc-header h2 { color:#2e8b57; font-size:1.2rem; margin-bottom:6px; }
    .vc-header .vc-action-label {
        display:inline-block;
        background: rgba(46,139,87,0.15);
        color:#2e8b57;
        font-size:0.75rem;
        padding:4px 12px;
        border-radius:20px;
        margin-top:6px;
    }
    .vc-description {
        text-align:center;
        color:#aaa;
        font-size:0.9rem;
        margin-bottom:20px;
        line-height:1.5;
    }
    .vc-error {
        text-align:center;
        color:#ff6b6b;
        background: rgba(255,107,107,0.08);
        font-size:0.85rem;
        padding:8px 12px;
        border-radius:8px;
        margin:10px 0;
    }
    .vc-success {
        text-align:center;
        color:#90ee90;
        background: rgba(46,139,87,0.1);
        font-size:0.85rem;
        padding:8px 12px;
        border-radius:8px;
        margin:10px 0;
    }
    .vc-code-row {
        display:flex;
        gap:8px;
        justify-content:center;
        margin:15px 0 10px;
    }
    .vc-code-row input {
        width:45px; height:50px;
        text-align:center;
        font-size:1.4rem;
        font-weight:bold;
        border-radius:10px;
        border:1px solid #333;
        background:#1a1a2e;
        color:#fff;
        padding:0;
        -webkit-appearance:none;
        outline:none;
        transition: border-color .2s, box-shadow .2s;
    }
    .vc-code-row input:focus {
        border-color:#2e8b57;
        box-shadow:0 0 15px rgba(46,139,87,0.2);
    }
    .vc-btn {
        width:100%;
        padding:12px;
        font-weight:bold;
        font-size:0.95rem;
        border:none;
        border-radius:10px;
        cursor:pointer;
        background:#2e8b57;
        color:#000;
        transition: transform .15s, opacity .2s;
        margin-top:5px;
    }
    .vc-btn:hover { opacity:.9; transform:scale(1.01); }
    .vc-btn:active { transform:scale(.98); }
    .vc-btn:disabled { opacity:.4; cursor:not-allowed; transform:none; }
    .vc-btn-secondary {
        background: transparent;
        color:#888;
        border:1px solid #333;
        margin-top:10px;
    }
    .vc-btn-secondary:hover { background: rgba(255,255,255,0.05); }
    .vc-links {
        text-align:center;
        margin-top:14px;
        font-size:0.85rem;
    }
    .vc-links a {
        color:#2e8b57;
        text-decoration:none;
        margin:0 6px;
    }
    .vc-links a:hover { text-decoration:underline; }
    .vc-footer {
        text-align:center;
        margin-top:15px;
        font-size:0.7rem;
        color:#666;
    }
    @media (max-width: 480px) {
        .vc-code-row input { width:38px; height:44px; font-size:1.2rem; }
        .vc-code-row { gap:5px; }
        .vc-container { padding:20px 15px; }
    }
    @media (max-width: 380px) {
        .vc-code-row input { width:32px; height:38px; font-size:1rem; }
        .vc-code-row { gap:4px; }
    }
    input { font-size:16px !important; }
    @media (max-width: 768px) {
        input,
        select,
        textarea {
            font-size: 16px !important;
        }
    }
</style>
</head>
<body>

<div class="vc-container">

    <?php if ($step === 'request'): ?>

        <div class="vc-header">
            <h2>Verify Your Identity</h2>
            <div class="vc-action-label"><?= htmlspecialchars($action) ?></div>
        </div>

        <p class="vc-description">
            For your security, we need to verify your email before proceeding.
            <br>
            A 6-digit code will be sent to <strong><?= htmlspecialchars($maskedEmail) ?></strong>.
        </p>

        <?php if (!empty($error)): ?>
            <div class="vc-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="vc-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" id="vcSendForm">
            <input type="hidden" name="vc_action" value="send_code">
            <button type="submit" class="vc-btn" id="vcSendBtn">Send Verification Code</button>
        </form>

        <div class="vc-links">
            <a href="<?= htmlspecialchars($source_file) ?>?cancel=1">Cancel</a>
        </div>

    <?php elseif ($step === 'verify'): ?>

        <div class="vc-header">
            <h2>Enter Verification Code</h2>
            <div class="vc-action-label"><?= htmlspecialchars($action) ?></div>
        </div>

        <p class="vc-description">
            We sent a 6-digit code to <strong><?= htmlspecialchars($maskedEmail) ?></strong>.
            Enter it below to continue.
        </p>

        <?php if (!empty($error)): ?>
            <div class="vc-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="vc-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" id="vcVerifyForm">
            <input type="hidden" name="vc_action" value="verify_dev_code">
            <input type="hidden" name="vc_code" id="vcCodeHidden" value="">

            <div class="vc-code-row" id="vcCodeRow">
                <input type="text" maxlength="1" class="vc-code-input" data-index="0" inputmode="numeric" pattern="[0-9]" autofocus>
                <input type="text" maxlength="1" class="vc-code-input" data-index="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="vc-code-input" data-index="2" inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="vc-code-input" data-index="3" inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="vc-code-input" data-index="4" inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="vc-code-input" data-index="5" inputmode="numeric" pattern="[0-9]">
            </div>

            <button type="submit" class="vc-btn" id="vcVerifyBtn">Verify &amp; Continue</button>
        </form>

        <div class="vc-links">
            <a href="verify_dev_code.php?resend=1&source=<?= urlencode($source) ?>&action=<?= urlencode($action) ?>">Resend Code</a>
            &nbsp;|&nbsp;
            <a href="<?= htmlspecialchars($source_file) ?>?cancel=1">Cancel</a>
        </div>

    <?php endif; ?>

    <div class="vc-footer">HarvHub Security</div>
</div>

<script>
    // ==================== AUTO-ADVANCE OTP INPUTS + AUTO-SUBMIT ====================
    (function() {
        var inputs = document.querySelectorAll('.vc-code-input');
        var hidden = document.getElementById('vcCodeHidden');
        var form   = document.getElementById('vcVerifyForm');
        var btn    = document.getElementById('vcVerifyBtn');
        var isSubmitting = false;

        if (!inputs.length) return;

        function updateHidden() {
            var code = '';
            inputs.forEach(function(i){ code += i.value; });
            if (hidden) hidden.value = code;
        }

        function isComplete() {
            for (var i = 0; i < inputs.length; i++) {
                if (inputs[i].value === '' || !/^\d$/.test(inputs[i].value)) return false;
            }
            return true;
        }

        function autoSubmit() {
            if (isSubmitting || !isComplete()) return;
            isSubmitting = true;
            setTimeout(function() {
                if (btn) { btn.disabled = true; btn.textContent = 'Verifying...'; }
                form.submit();
            }, 120);
        }

        setTimeout(function(){ if (inputs[0]) inputs[0].focus(); }, 100);

        inputs.forEach(function(input, idx) {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
                if (this.value.length === 1 && idx < inputs.length - 1) {
                    inputs[idx + 1].focus();
                }
                updateHidden();
                autoSubmit();
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && this.value === '' && idx > 0) {
                    inputs[idx - 1].focus();
                    inputs[idx - 1].value = '';
                    updateHidden();
                }
                if (e.key === 'ArrowLeft' && idx > 0) {
                    e.preventDefault();
                    inputs[idx - 1].focus();
                }
                if (e.key === 'ArrowRight' && idx < inputs.length - 1) {
                    e.preventDefault();
                    inputs[idx + 1].focus();
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (isComplete() && !isSubmitting) {
                        isSubmitting = true;
                        form.submit();
                    }
                }
            });

            input.addEventListener('paste', function(e) {
                e.preventDefault();
                var paste = (e.clipboardData || window.clipboardData).getData('text');
                var digits = paste.replace(/\D/g, '').slice(0, 6).split('');
                digits.forEach(function(d, i) {
                    if (i < inputs.length) inputs[i].value = d;
                });
                var next = Math.min(digits.length, inputs.length - 1);
                if (inputs[next]) inputs[next].focus();
                updateHidden();
                autoSubmit();
            });

            input.addEventListener('focus', function(){ this.select(); });
        });
    })();

    // ==================== AUTO-SEND FIRST CODE ====================
    <?php if ($autoSend): ?>
    (function() {
        var sendForm = document.getElementById('vcSendForm');
        if (sendForm) {
            setTimeout(function() {
                sendForm.submit();
            }, 400);
        }
    })();
    <?php endif; ?>
</script>

</body>
</html>