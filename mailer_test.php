<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==================== DB CONNECTION ====================
try {
    $pdo = new PDO(
        "mysql:host=sql312.infinityfree.com;dbname=if0_40473107_harvhub;charset=utf8mb4",
        "if0_40473107",
        "InDQmdl53FZ85",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("DB connection failed: " . $e->getMessage());
}

// ==================== FETCH MAILER CREDS FROM DB ====================
$mailer_email  = '';
$brevo_api_key = '';

$stmt = $pdo->prepare("SELECT mailer_email, mailer_password FROM server_account WHERE id = 1 LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $mailer_email  = trim($row['mailer_email'] ?? '');
    $brevo_api_key = trim($row['mailer_password'] ?? '');
}

// ==================== SHOW WHAT CAME FROM DB ====================
echo "<h2>What came from the DB</h2>";
echo "<table border='1' cellpadding='6' style='font-family:monospace'>";
echo "<tr><th>Field</th><th>Value</th><th>Length</th><th>Prefix</th></tr>";

$show = function($label, $val) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($label) . "</td>";
    echo "<td>[" . htmlspecialchars((string)$val) . "]</td>";
    echo "<td>" . strlen((string)$val) . "</td>";
    echo "<td>" . htmlspecialchars(substr((string)$val, 0, 14)) . "</td>";
    echo "</tr>";
};

if (!$row) {
    echo "<tr><td colspan='4' style='color:red'>NO ROW with id=1 in server_account!</td></tr>";
} else {
    $show('mailer_email',                     $mailer_email);
    $show('mailer_password (Brevo API key)',  $brevo_api_key);
}
echo "</table>";

if (empty($mailer_email) || empty($brevo_api_key)) {
    echo "<h3 style='color:red'>STOPPING: one or both credentials are empty.</h3>";
    exit;
}

if (strpos($brevo_api_key, 'xkeysib-') !== 0) {
    echo "<p style='color:orange'>⚠ mailer_password does not start with <code>xkeysib-</code>. " .
         "It looks like the old Gmail app password or something else. " .
         "Update the DB with the Brevo API key.</p>";
}

// ==================== SEND VIA BREVO API ====================
$recipient = 'adymi0987@gmail.com';   // <-- change to any Gmail you own
$code      = '123456';

$payload = [
    'sender' => [
        'name'  => 'HarvHub',
        'email' => $mailer_email,
    ],
    'to' => [
        ['email' => $recipient],
    ],
    'subject'     => 'HarvHub Brevo API test',
    'htmlContent' =>
        '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;line-height:1.5">'
        . '<p>Hi,</p>'
        . '<p>Use this code to verify your HarvHub account:</p>'
        . '<p style="font-size:28px;font-weight:bold;letter-spacing:6px;margin:18px 0;color:#2e8b57">'
        . htmlspecialchars($code) . '</p>'
        . '<p>The code expires in 15 minutes.</p>'
        . '<p>If you did not request this, you can safely ignore this email.</p>'
        . '<p style="color:#888;font-size:12px;margin-top:20px">— HarvHub</p>'
        . '</div>',
    'textContent' =>
        "Hi,\n\n"
        . "Use this code to verify your HarvHub account:\n\n"
        . "$code\n\n"
        . "The code expires in 15 minutes.\n"
        . "If you did not request this, you can safely ignore this email.\n\n"
        . "— HarvHub",
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
$curlErr  = curl_error($ch);
curl_close($ch);

echo "<h2>Brevo API response</h2>";
echo "<p>HTTP status: <b>" . htmlspecialchars((string)$httpCode) . "</b></p>";
echo "<p>cURL error: <b>" . htmlspecialchars((string)$curlErr) . "</b></p>";
echo "<pre style='background:#111;color:#0f0;padding:12px;max-height:400px;overflow:auto'>"
     . htmlspecialchars((string)$response) . "</pre>";

if ($httpCode >= 200 && $httpCode < 300) {
    echo "<h2 style='color:green'>✅ SENT via Brevo — check the recipient's Inbox (not Spam)</h2>";
} else {
    echo "<h2 style='color:red'>❌ FAILED — read the HTTP status and response above</h2>";
    echo "<p>Common errors:<br>";
    echo "<b>401</b>: bad API key (re-copy from Brevo, ensure no spaces, starts with <code>xkeysib-</code>)<br>";
    echo "<b>400</b>: sender email not verified in Brevo (Senders page)<br>";
    echo "<b>403</b>: Brevo account not fully activated<br>";
    echo "<b>429</b>: daily quota exceeded (300/day free tier)</p>";
}