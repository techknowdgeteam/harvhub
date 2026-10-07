<?php
// verify_code.php
// Generic email verification gate.
// Accepts: ?source=<page_to_return_to>&action=<optional_action_label>
//
// On success:
//   - Sets $_SESSION['verified_<source>'] = true            (legacy flat key)
//   - Sets $_SESSION['verified_<source>_<subId>'] = true    (sub-account scoped key)
//   - Also records a timestamp so downstream pages can check freshness if needed.
//
// Then redirects (via shell escape) back to:  <source>.php?verified=1
//
// SHELL NOTE: This page may be loaded inside investorapp.php's iframe
// shell. To avoid nesting the shell inside itself, the success and cancel
// paths render a tiny escape page that uses window.top.location.href to
// reload the browser tab on <source>.php (full page, no shell).

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

// ==================== RESOLVE ACTIVE SUB ACCOUNT ====================
// We resolve the sub account id here too, so the scoped session key we set
// matches the one disconnect_broker.php (and other callers) will check.
$activeSubAccountId = (int)($_SESSION['active_sub_account_id'] ?? 0);

if ($activeSubAccountId <= 0) {
    // Try to resolve from the DB, same rules as the caller pages.
    try {
        $q = $pdo->prepare("SELECT sub_account_id FROM harvhub WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
        $q->execute([$email]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $q = $pdo->prepare("SELECT sub_account_id, id FROM harvhub WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
            $q->execute([$email]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
        }

        if ($row) {
            $activeSubAccountId = (int)($row['sub_account_id'] ?? $row['id'] ?? 0);
        }
    } catch (Exception $e) {}

    if ($activeSubAccountId > 0) {
        $_SESSION['active_sub_account_id'] = $activeSubAccountId;
    }
}

// ==================== SOURCE / ACTION PARAMS ====================
$source = isset($_GET['source']) ? preg_replace('/[^a-z0-9_]/i', '', $_GET['source']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : 'Verify Identity';

if (empty($source)) {
    header("Location: investorapp.php");
    exit;
}

$source_file = $source . '.php';

$allowed_sources = ['disconnect_broker', 'connect_investor_broker', 'mydashboard'];
if (!in_array($source, $allowed_sources, true)) {
    header("Location: investorapp.php");
    exit;
}

// Legacy flat key (kept for compatibility with any other page that still checks it)
$session_key_flat  = 'verified_' . $source;
// Sub-account scoped key (this is what disconnect_broker.php checks)
$session_key_scoped = ($activeSubAccountId > 0)
    ? 'verified_' . $source . '_' . $activeSubAccountId
    : $session_key_flat;

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

            . '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#f4f5f7;opacity:0;">'
            . 'Complete your verification — use this code to confirm ' . $safeAction . '.'
            . '</div>'

            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f5f7;padding:40px 16px;">'
            . '<tr><td align="center">'

            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background-color:#ffffff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.05);overflow:hidden;">'

            . '<tr><td style="padding:32px 40px 8px 40px;text-align:center;">'
            . '<div style="display:inline-block;width:56px;height:56px;line-height:56px;border-radius:14px;background-color:#2e8b57;color:#ffffff;font-weight:700;font-size:26px;text-align:center;">H</div>'
            . '<h1 style="margin:16px 0 0 0;font-size:22px;font-weight:700;color:#111827;letter-spacing:-0.2px;">HarvHub</h1>'
            . '</td></tr>'

            . '<tr><td style="padding:24px 40px 8px 40px;">'
            . '<h2 style="margin:0 0 12px 0;font-size:18px;font-weight:600;color:#111827;">Complete your verification</h2>'
            . '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#4b5563;">'
            . 'You requested to perform: <strong style="color:#111827;">' . $safeAction . '</strong>.'
            . '</p>'
            . '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;color:#4b5563;">'
            . 'Enter the verification code below to confirm this action.'
            . '</p>'
            . '</td></tr>'

            . '<tr><td style="padding:8px 40px 8px 40px;">'
            . '<div style="background-color:#f0f9f4;border:1px solid #d6ede0;border-radius:10px;padding:24px;text-align:center;">'
            . '<p style="margin:0 0 8px 0;font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#4b5563;font-weight:600;">Verification code</p>'
            . '<div style="font-family:\'SF Mono\',Menlo,Consolas,\'Courier New\',monospace;font-size:34px;font-weight:700;letter-spacing:10px;color:#2e8b57;padding-left:10px;">'
            . $safeCode
            . '</div>'
            . '<p style="margin:12px 0 0 0;font-size:13px;color:#6b7280;">Use this code to continue</p>'
            . '</div>'
            . '</td></tr>'

            . '<tr><td style="padding:16px 40px 8px 40px;">'
            . '<p style="margin:0;font-size:14px;line-height:1.6;color:#4b5563;">'
            . 'If you did not request this action, please disregard this email and consider changing your password.'
            . '</p>'
            . '</td></tr>'

            . '<tr><td style="padding:24px 40px 0 40px;">'
            . '<div style="border-top:1px solid #e5e7eb;"></div>'
            . '</td></tr>'

            . '<tr><td style="padding:20px 40px 32px 40px;">'
            . '<p style="margin:0 0 6px 0;font-size:13px;color:#6b7280;">'
            . 'This is an automated security message from HarvHub. Please do not reply to this email.'
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

// ==================== SHELL ESCAPE HELPER ====================
function renderShellEscape($target, $label = 'Returning…') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?= htmlspecialchars($label) ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin:0;background:#000;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
        <div style="display:flex;align-items:center;justify-content:center;min-height:100vh;color:#6e7681;font-size:0.9rem;font-weight:600;">
            <?= htmlspecialchars($label) ?>
        </div>
        <script>
            (function () {
                var target = <?= json_encode($target) ?>;
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
        header('Location: verify_code.php?source=' . urlencode($source) . '&action=' . urlencode($action));
        exit;
    }

    if (sendVerifyEmail($email, $fullname, $code, $action, $mailer_email, $brevo_api_key)) {
        $_SESSION['vc_step']    = 'verify';
        $_SESSION['vc_success'] = 'A verification code has been sent to ' . maskEmail($email);
        unset($_SESSION['vc_error']);
    } else {
        $_SESSION['vc_error'] = 'Failed to send email. Please try again.';
    }

    header('Location: verify_code.php?source=' . urlencode($source) . '&action=' . urlencode($action));
    exit;
}

// ==================== HANDLE: VERIFY CODE ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vc_action']) && $_POST['vc_action'] === 'verify_code') {
    $entered = trim($_POST['vc_code'] ?? '');

    if (empty($entered) || strlen($entered) !== 6) {
        $_SESSION['vc_error'] = 'Please enter the complete 6-digit code.';
        header('Location: verify_code.php?source=' . urlencode($source) . '&action=' . urlencode($action));
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

            // -------- Set BOTH keys so every caller finds it --------
            $_SESSION[$session_key_flat]   = true;   // "verified_disconnect_broker"
            $_SESSION[$session_key_scoped] = true;   // "verified_disconnect_broker_42"
            $_SESSION['verified_' . $source . '_time'] = time();

            // Clear the verification wizard state so the next visit starts fresh
            unset($_SESSION['vc_step']);
            unset($_SESSION['vc_error']);
            unset($_SESSION['vc_success']);

            renderShellEscape($source_file . '?verified=1', 'Verified — returning…');
        }
    } catch (Exception $e) {
        $_SESSION['vc_error'] = 'Verification failed. Please try again.';
    }

    header('Location: verify_code.php?source=' . urlencode($source) . '&action=' . urlencode($action));
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
    header('Location: verify_code.php?source=' . urlencode($source) . '&action=' . urlencode($action));
    exit;
}

// ==================== HANDLE: CANCEL ====================
if (isset($_GET['cancel']) && $_GET['cancel'] === '1') {
    unset($_SESSION['vc_step']);
    unset($_SESSION['vc_error']);
    unset($_SESSION['vc_success']);

    renderShellEscape($source_file, 'Cancelled — returning…');
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
    /* ============================================================
       THEME TOKENS — light default, .dark-mode overrides
       ============================================================ */
    :root {
        --vc-bg:              #f0f4f8;
        --vc-glow-1:          rgba(46, 139, 87, 0.08);
        --vc-glow-2:          rgba(46, 139, 87, 0.05);
        --vc-card-bg:         #ffffff;
        --vc-card-border:     rgba(0, 0, 0, 0.08);
        --vc-card-shadow:     0 20px 60px rgba(0, 0, 0, 0.12);
        --vc-text:            #1a2332;
        --vc-text-muted:      #60736d;
        --vc-text-soft:       #4b5563;
        --vc-accent:          #2e8b57;
        --vc-accent-contrast: #ffffff;
        --vc-input-bg:        #f7fafc;
        --vc-input-border:    rgba(0, 0, 0, 0.12);
        --vc-input-text:      #1a2332;
        --vc-input-focus:     rgba(46, 139, 87, 0.15);
        --vc-error-bg:        rgba(220, 53, 69, 0.08);
        --vc-error-border:    #dc3545;
        --vc-error-text:      #c82333;
        --vc-success-bg:      rgba(46, 139, 87, 0.10);
        --vc-success-border:  #2e8b57;
        --vc-success-text:    #1e6b40;
        --vc-label-bg:        rgba(46, 139, 87, 0.10);
        --vc-label-text:      #1e6b40;
        --vc-footer:          #8a9aa8;
    }
    body.dark-mode {
        --vc-bg:              #000000;
        --vc-glow-1:          rgba(26, 0, 51, 0.6);
        --vc-glow-2:          rgba(0, 0, 51, 0.6);
        --vc-card-bg:         rgba(20, 20, 30, 0.95);
        --vc-card-border:     rgba(255, 255, 255, 0.06);
        --vc-card-shadow:     0 20px 60px rgba(0, 0, 0, 0.8);
        --vc-text:            #e4e6eb;
        --vc-text-muted:      #aaa;
        --vc-text-soft:       #c8ccd4;
        --vc-accent:          #2e8b57;
        --vc-accent-contrast: #000000;
        --vc-input-bg:        #1a1a2e;
        --vc-input-border:    #333;
        --vc-input-text:      #ffffff;
        --vc-input-focus:     rgba(46, 139, 87, 0.2);
        --vc-error-bg:        rgba(255, 107, 107, 0.08);
        --vc-error-border:    #ff6b6b;
        --vc-error-text:      #ff6b6b;
        --vc-success-bg:      rgba(46, 139, 87, 0.10);
        --vc-success-border:  #2e8b57;
        --vc-success-text:    #90ee90;
        --vc-label-bg:        rgba(46, 139, 87, 0.15);
        --vc-label-text:      #2e8b57;
        --vc-footer:          #666;
    }

    * { margin:0; padding:0; box-sizing:border-box; }

    html, body {
        height:100%;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: var(--vc-bg);
        color: var(--vc-text);
        display:flex;
        align-items:center;
        justify-content:center;
        padding:20px;
        overflow:hidden;
        position:fixed;
        width:100%;
        transition: background 0.3s ease, color 0.3s ease;
    }
    body::before {
        content:"";
        position:absolute; inset:0;
        background:
            radial-gradient(circle at 20% 80%, var(--vc-glow-1) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, var(--vc-glow-2) 0%, transparent 50%);
        opacity:0.6;
        pointer-events:none;
        transition: background 0.3s ease;
    }

    .vc-container {
        position:relative;
        z-index:1;
        background: var(--vc-card-bg);
        border:1px solid var(--vc-card-border);
        border-radius:20px;
        padding:30px 25px;
        max-width:500px;
        width:100%;
        box-shadow: var(--vc-card-shadow);
        max-height:95vh;
        overflow-y:auto;
        transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .vc-header { text-align:center; margin-bottom:18px; }
    .vc-header h2 { color: var(--vc-accent); font-size:1.2rem; margin-bottom:6px; }
    .vc-header .vc-action-label {
        display:inline-block;
        background: var(--vc-label-bg);
        color: var(--vc-label-text);
        font-size:0.75rem;
        padding:4px 12px;
        border-radius:20px;
        margin-top:6px;
    }
    .vc-description {
        text-align:center;
        color: var(--vc-text-muted);
        font-size:0.9rem;
        margin-bottom:20px;
        line-height:1.5;
    }
    .vc-error {
        text-align:center;
        color: var(--vc-error-text);
        background: var(--vc-error-bg);
        font-size:0.85rem;
        padding:8px 12px;
        border-radius:8px;
        margin:10px 0;
    }
    .vc-success {
        text-align:center;
        color: var(--vc-success-text);
        background: var(--vc-success-bg);
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
        border:1px solid var(--vc-input-border);
        background: var(--vc-input-bg);
        color: var(--vc-input-text);
        padding:0;
        -webkit-appearance:none;
        outline:none;
        transition: border-color .2s, box-shadow .2s, background .3s, color .3s;
    }
    .vc-code-row input:focus {
        border-color: var(--vc-accent);
        box-shadow:0 0 15px var(--vc-input-focus);
    }
    .vc-btn {
        width:100%;
        padding:12px;
        font-weight:bold;
        font-size:0.95rem;
        border:none;
        border-radius:10px;
        cursor:pointer;
        background: var(--vc-accent);
        color: var(--vc-accent-contrast);
        transition: transform .15s, opacity .2s;
        margin-top:5px;
    }
    .vc-btn:hover { opacity:.9; transform:scale(1.01); }
    .vc-btn:active { transform:scale(.98); }
    .vc-btn:disabled { opacity:.4; cursor:not-allowed; transform:none; }
    .vc-btn-secondary {
        background: transparent;
        color: var(--vc-text-muted);
        border:1px solid var(--vc-card-border);
        margin-top:10px;
    }
    .vc-btn-secondary:hover { background: rgba(127,127,127,0.08); }
    .vc-links {
        text-align:center;
        margin-top:14px;
        font-size:0.85rem;
    }
    .vc-links a {
        color: var(--vc-accent);
        text-decoration:none;
        margin:0 6px;
    }
    .vc-links a:hover { text-decoration:underline; }
    .vc-footer {
        text-align:center;
        margin-top:15px;
        font-size:0.7rem;
        color: var(--vc-footer);
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
<body class="<?= htmlspecialchars($darkModeClass) ?>">

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
            <a href="verify_code.php?source=<?= urlencode($source) ?>&action=<?= urlencode($action) ?>&cancel=1">Cancel</a>
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
            <input type="hidden" name="vc_action" value="verify_code">
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
            <a href="verify_code.php?resend=1&source=<?= urlencode($source) ?>&action=<?= urlencode($action) ?>">Resend Code</a>
            &nbsp;|&nbsp;
            <a href="verify_code.php?source=<?= urlencode($source) ?>&action=<?= urlencode($action) ?>&cancel=1">Cancel</a>
        </div>

    <?php endif; ?>

    <div class="vc-footer">HarvHub Security</div>
</div>

<script>
    // =====================================================================
    // SHELL BRIDGE — hide header + bottom nav in the investor shell,
    // receive theme changes, and report our body class.
    // =====================================================================
    (function () {
        document.body.classList.add('page-verify_code');

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