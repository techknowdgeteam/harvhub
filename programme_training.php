<?php
// programme_training.php — placeholder
session_start();

if (!isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit;
}

$programmeId = (int)($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Programme Training - HarvHub</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<?php include 'style.php'; ?>
<?php include 'dev_style.php'; ?>
</head>
<body>
    <?php include 'dev_tabs.php'; ?>
    <div style="max-width:720px;margin:0 auto;padding:28px 20px;text-align:center;">
        <h1 style="color:var(--accent,#2e8b57);">Programme Training</h1>
        <p style="color:var(--text-muted,#888);">Programme ID: <?= (int)$programmeId ?></p>
        <p style="color:var(--text-muted,#888);font-size:0.9rem;margin-top:20px;">
            Script not available for now. We will come back to this.
        </p>
    </div>
</body>
</html>