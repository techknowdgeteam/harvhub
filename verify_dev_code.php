<?php
// verify_dev_code.php
// Generic email verification gate.
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
// source = the php file to return to after successful verification
//          e.g. "disconnect_dev_broker"  ->  disconnect_dev_broker.php
// action = a human label shown in the UI + email (e.g. "Disconnect Broker")
$source = isset($_GET['source']) ? preg_replace('/[^a-z0-9_]/i', '', $_GET['source']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : 'Verify Identity';

if (empty($source)) {
    // No source given → just go back to app
    header("Location: dev_app.php");
    exit;
}

$source_file = $source . '.php';

// Whitelist of allowed sources (only real files we control)
$allowed_sources = ['disconnect_dev_broker', 'connect_dev_broker', 'mydashboard'];
if (!in_array($source, $allowed_sources, true)) {
    header("Location: dev_app.php");
    exit;
}

// Session key used to mark this source as verified
$session_key = 'verified_' . $source;

// ==================== FETCH MAILER CREDENTIALS ====================
$mailer_email = '';
$mailer_password = '';
try {
    $stmt = $pdo->prepare("SELECT mailer_email, mailer_password FROM server_account WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $mailerData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($mailerData) {
        $mailer_email    = trim($mailerData['mailer_email'] ?? '');
        $mailer_password = trim($mailerData['mailer_password'] ?? '');
    }
} catch (Exception $e) {}

if (empty($mailer_email))    $mailer_email    = 'techknowdgeteam@gmail.com';
if (empty($mailer_password)) $mailer_password = 'rqcrossbioujepda';

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

function sendVerifyEmail($toEmail, $toName, $code, $action, $mailer_email, $mailer_password) {
    require_once 'PHPMailer/src/PHPMailer.php';
    require_once 'PHPMailer/src/SMTP.php';
    require_once 'PHPMailer/src/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailer_email;
        $mail->Password   = $mailer_password;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($mailer_email, 'HarvHub Security');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Verification Code - ' . $action;
        $safeAction = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
        $mail->Body    = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color:#333; }
                    .container { max-width:500px; margin:0 auto; padding:20px; background:#f9f9f9; border-radius:10px; }
                    .code { font-size:32px; font-weight:bold; color:#2e8b57; text-align:center; padding:20px; background:#fff; border-radius:8px; margin:20px 0; letter-spacing:6px; }
                    .footer { font-size:12px; color:#999; text-align:center; margin-top:20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2 style='text-align:center; color:#2e8b57;'>HarvHub</h2>
                    <h3 style='text-align:center;'>{$safeAction}</h3>
                    <p>You requested to perform: <strong>{$safeAction}</strong>.</p>
                    <p>Enter this verification code to confirm:</p>
                    <div class='code'>{$code}</div>
                    <p>If you didn't request this, please ignore this email and consider changing your password.</p>
                    <div class='footer'>HarvHub Security</div>
                </div>
            </body>
            </html>
        ";
        $mail->AltBody = "Your verification code for {$action} is: {$code}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Verify mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

// ==================== HANDLE: SEND CODE ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vc_action']) && $_POST['vc_action'] === 'send_code') {
    $code = generateCode();

    // Remove any old codes for this email
    try {
        $del = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $del->execute([$email]);
    } catch (Exception $e) {}

    // Store code (no expiry)
    try {
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, reset_code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 YEAR))");
        $stmt->execute([$email, $code]);
    } catch (Exception $e) {
        $_SESSION['vc_error'] = 'Could not generate a code. Please try again.';
        header('Location: verify_dev_code.php?source=' . urlencode($source) . '&action=' . urlencode($action));
        exit;
    }

    if (sendVerifyEmail($email, $fullname, $code, $action, $mailer_email, $mailer_password)) {
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
            // Mark used
            $upd = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $upd->execute([$rec['id']]);

            // Mark source as verified in session
            $_SESSION[$session_key] = true;

            // Clear verify state
            unset($_SESSION['vc_step']);
            unset($_SESSION['vc_error']);
            unset($_SESSION['vc_success']);

            // Redirect back to the source with verified=1
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

        if (sendVerifyEmail($email, $fullname, $code, $action, $mailer_email, $mailer_password)) {
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

// Consume messages so they don't persist
unset($_SESSION['vc_error']);
unset($_SESSION['vc_success']);

$maskedEmail = maskEmail($email);

// Auto-send first code if user lands on this page fresh
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
        border-left:3px solid #ff6b6b;
        font-size:0.85rem;
        padding:8px 12px;
        border-radius:8px;
        margin:10px 0;
    }
    .vc-success {
        text-align:center;
        color:#90ee90;
        background: rgba(46,139,87,0.1);
        border-left:3px solid #2e8b57;
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

            <button type="submit" class="vc-btn" id="vcVerifyBtn">Verify & Continue</button>
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
    // ==================== AUTO-ADVANCE OTP INPUTS ====================
    (function() {
        var inputs = document.querySelectorAll('.vc-code-input');
        var hidden = document.getElementById('vcCodeHidden');
        var form   = document.getElementById('vcVerifyForm');
        var btn    = document.getElementById('vcVerifyBtn');

        if (!inputs.length) return;

        function updateHidden() {
            var code = '';
            inputs.forEach(function(i){ code += i.value; });
            if (hidden) hidden.value = code;
        }

        setTimeout(function(){ if (inputs[0]) inputs[0].focus(); }, 100);

        inputs.forEach(function(input, idx) {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
                if (this.value.length === 1 && idx < inputs.length - 1) {
                    inputs[idx + 1].focus();
                }
                updateHidden();
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
                    var complete = true;
                    inputs.forEach(function(i){ if (i.value === '') complete = false; });
                    if (complete && form) form.submit();
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
            });

            input.addEventListener('focus', function(){ this.select(); });
        });
    })();

    // ==================== AUTO-SEND FIRST CODE ====================
    <?php if ($autoSend): ?>
    (function() {
        var sendBtn = document.getElementById('vcSendBtn');
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