<?php
// server_auth.php

// NOTE: This file assumes the database connection ($pdo) and session_start()
// have already been initiated in the main file (insiders_server.php).

// Initialize status and message
$server_auth_error = '';

// Check if the server authentication form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['server_submit'])) {
    $submitted_server_id = trim($_POST['server_id'] ?? '');
    $submitted_passkey = $_POST['server_passkey'] ?? ''; // Passkey is generally treated as sensitive, no trim/lower here

    if (empty($submitted_server_id) || empty($submitted_passkey)) {
        $server_auth_error = "Server ID and Passkey are required.";
    } else {
        try {
            // Prepare the statement to check for the server details.
            // Using a simple check against stored values. In a real system, 
            // the server_passkey should be hashed and compared using password_verify().
            $stmt = $pdo->prepare("SELECT server_id FROM server_auth WHERE server_id = ? AND server_passkey = ? LIMIT 1");
            $stmt->execute([$submitted_server_id, $submitted_passkey]);
            
            if ($stmt->fetch()) {
                // Authentication successful! Set a session flag.
                $_SESSION['server_authenticated'] = true;
                
                // Redirect using PRG pattern to prevent form resubmission and clear POST data
                header("Location: " . basename($_SERVER['PHP_SELF']));
                exit;
            } else {
                $server_auth_error = "Invalid Server ID or Passkey.";
            }
        } catch (PDOException $e) {
            // Handle DB error
            $server_auth_error = "Authentication failed due to a database error.";
        }
    }
}

// Check if the user is already server-authenticated
$server_authenticated = $_SESSION['server_authenticated'] ?? false;

// If not authenticated, display the overlay modal.
if (!$server_authenticated) {
?>
<div id="serverAuthOverlay" style="
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.95); /* Darker overlay for focus */
    backdrop-filter: blur(5px);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
">
    <div style="
        background: var(--bg);
        color: var(--text);
        padding: 30px;
        border-radius: 15px;
        width: 90%;
        max-width: 400px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    ">
        <h2 style="text-align:center; color:var(--accent); margin-bottom: 20px;">Server Login Required</h2>
        
        <?php if ($server_auth_error): ?>
            <p style="color: #ff6b6b; text-align: center; margin-bottom: 15px;">
                <?= htmlspecialchars($server_auth_error) ?>
            </p>
        <?php endif; ?>

        <form method="POST">
            <label style="display:block; margin-bottom:5px;">Server ID</label>
            <input type="text" name="server_id" placeholder="Enter Server ID" required 
                   style="width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 8px; border: 1px solid #555; background: var(--input-bg); color: var(--text);">

            <label style="display:block; margin-bottom:5px;">Server Passkey</label>
            <input type="password" name="server_passkey" placeholder="Enter Passkey" required 
                   style="width: 100%; padding: 10px; margin-bottom: 20px; border-radius: 8px; border: 1px solid #555; background: var(--input-bg); color: var(--text);">

            <button type="submit" name="server_submit" 
                    style="width: 100%; padding: 12px; background: var(--accent); color: #000; font-weight: bold; border: none; border-radius: 50px; cursor: pointer; transition: background 0.3s;">
                Access Server
            </button>
        </form>
    </div>
</div>
<?php
// Since the user is not authenticated, we terminate the script here 
// to prevent the rest of the main page content from rendering.
exit;
}
// If authenticated, the script simply finishes and the main file continues execution.
?>