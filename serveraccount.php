<?php
    session_start();
    // serveraccount.php - Admin interface for managing settings and viewing paid users

    // --- Database Configuration ---
    $host = "sql312.infinityfree.com";
    $dbname = "if0_40473107_harvhub";
    $user = "if0_40473107";
    $pass = "InDQmdl53FZ85";
    $serverAccountTable = "server_account";
    $insidersServerTable = "insiders_server";
    $insidersTable = "insiders";

    $message = "";
    $authenticated = false;
    $initialSetupRequired = true;
    $currentView = $_GET['view'] ?? 'menu'; // Default to menu/navigation screen

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        die("Database connection failed: " . $e->getMessage());
    }

    // --- Fetch Current Account Data (Row ID 1) ---
    $stmt = $pdo->prepare("SELECT * FROM {$serverAccountTable} WHERE id = 1");
    $stmt->execute();
    $serverAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    // Initial setup check/initial row creation
    if (!$serverAccount) {
        try {
            $pdo->exec("
                INSERT INTO {$serverAccountTable} 
                (id, btc_address, eth_address, eth_network, usdt_address, usdt_network, admin_login_id, minimum_deposit)
                VALUES (1, '', '', 'ERC20', '', 'TRC20', 'admin', 0.00)
            ");
            $stmt->execute();
            $serverAccount = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) { /* silent fail for setup */ }
    }
    
    // Check setup status after potential row creation
    $initialSetupRequired = empty($serverAccount['admin_password_hash'] ?? '');
    
    // --- POST Handling: Admin Login/Re-Auth/Setup (via Modal/Form) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $login_id = trim($_POST['login_id'] ?? $serverAccount['admin_login_id'] ?? '');
        $password_input = $_POST['admin_confirmation_password'] ?? $_POST['password'] ?? ''; 
        
        // 1. Initial Setup
        if (isset($_POST['initial_setup']) && $initialSetupRequired) {
            if (!empty($login_id) && !empty($password_input)) {
                $password_hash = password_hash($password_input, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE {$serverAccountTable} SET admin_login_id = ?, admin_password_hash = ? WHERE id = 1");
                $upd->execute([$login_id, $password_hash]);
                $_SESSION['admin_logged_in'] = true;
                header("Location: serveraccount.php?view=menu"); 
                exit;
            } else {
                $message = "<span style='color:red;'>❌ Both Login ID and Password are required for setup.</span>";
            }
        
        // 2. Regular Login / Re-Authentication
        } elseif (!$initialSetupRequired && isset($_POST['password'])) { // Primary login screen submission
            if (isset($serverAccount['admin_login_id']) && $login_id === $serverAccount['admin_login_id'] && password_verify($password_input, $serverAccount['admin_password_hash'] ?? '')) {
                $_SESSION['admin_logged_in'] = true;
                $authenticated = true;
                // Redirect after successful login to clear POST data and navigate to menu
                header("Location: serveraccount.php?view=menu");
                exit;
            } else {
                $message = "<span style='color:red;'>❌ Invalid Login ID or Password.</span>";
            }
        }
    }
    
    // Check authentication status after POST handling
    $authenticated = ($_SESSION['admin_logged_in'] ?? false) && !$initialSetupRequired;
    
    // If authenticated, we handle the other POST actions and data fetching
    if ($authenticated) {
        // --- Re-Authentication Check for Data/Credential Update Actions ---
        $re_authenticated_for_action = false;
        if (isset($_POST['admin_confirmation_password'])) {
             $login_id_reauth = trim($_POST['login_id'] ?? '');
             $password_reauth = $_POST['admin_confirmation_password'];
             
             if (isset($serverAccount['admin_login_id']) && $login_id_reauth === $serverAccount['admin_login_id'] && password_verify($password_reauth, $serverAccount['admin_password_hash'] ?? '')) {
                 $re_authenticated_for_action = true;
             } else {
                 $message = "<span style='color:red;'>❌ Action failed: Invalid Admin Password confirmation. Session terminated.</span>";
                 unset($_SESSION['admin_logged_in']);
                 $authenticated = false;
             }
        }

        // --- POST Handling: Update Addresses and Minimum Deposit ---
        if (isset($_POST['update_addresses']) && $re_authenticated_for_action) {
            try {
                $btc_address = trim($_POST['btc_address'] ?? '');
                $eth_address = trim($_POST['eth_address'] ?? '');
                $eth_network = trim($_POST['eth_network'] ?? 'ERC20');
                $usdt_address = trim($_POST['usdt_address'] ?? '');
                $usdt_network = trim($_POST['usdt_network'] ?? 'TRC20');
                
                // FIELD 1: Minimum Deposit
                $minimum_deposit = floatval($_POST['minimum_deposit'] ?? 0.00);
                
                // FIELD 2: Contract Duration (NEW)
                $contract_duration = is_numeric($_POST['contract_duration'] ?? null) ? (int)$_POST['contract_duration'] : null;

                $stmt = $pdo->prepare("
                    UPDATE {$serverAccountTable} SET 
                        btc_address = ?, eth_address = ?, eth_network = ?, usdt_address = ?, usdt_network = ?, minimum_deposit = ?, contract_duration = ?
                    WHERE id = 1
                ");
                $stmt->execute([$btc_address, $eth_address, $eth_network, $usdt_address, $usdt_network, $minimum_deposit, $contract_duration]);
                $message = "<span style='color:green;'>✅ Payment settings and contract duration updated successfully!</span>";
            } catch (Exception $e) {
                $message = "<span style='color:red;'>❌ Error updating settings: " . htmlspecialchars($e->getMessage()) . "</span>";
            }
        }
        
        // --- POST Handling: Update Admin Credentials ---
        if (isset($_POST['update_credentials']) && $re_authenticated_for_action) {
            $new_login_id = trim($_POST['new_login_id'] ?? $serverAccount['admin_login_id']);
            $new_password = $_POST['new_password'] ?? '';

            try {
                $update_query = "UPDATE {$serverAccountTable} SET admin_login_id = ?";
                $params = [$new_login_id];

                if (!empty($new_password)) {
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_query .= ", admin_password_hash = ?";
                    $params[] = $password_hash;
                }
                
                $update_query .= " WHERE id = 1";
                $stmt = $pdo->prepare($update_query);
                $stmt->execute($params);
                
                $message = "<span style='color:green;'>✅ Credentials updated successfully! Please re-login with your new details.</span>";
                unset($_SESSION['admin_logged_in']); // Force re-login
                $authenticated = false;
                // Redirect to the login page
                header("Location: serveraccount.php");
                exit;
            } catch (Exception $e) {
                $message = "<span style='color:red;'>❌ Error updating credentials: " . htmlspecialchars($e->getMessage()) . "</span>";
            }
        }

        // --- POST Handling: Add New Broker/Link ---
        if (isset($_POST['add_broker']) && $re_authenticated_for_action) {
            $new_broker = trim($_POST['new_broker'] ?? '');
            if (!empty($new_broker)) {
                try {
                    $current_brokers = $serverAccount['brokers'] ?? '';
                    $brokers_array = array_filter(array_map('trim', explode(',', $current_brokers)));
                    if (!in_array($new_broker, $brokers_array)) {
                        $brokers_array[] = $new_broker;
                        $updated_brokers = implode(',', $brokers_array);
                        $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET brokers = ? WHERE id = 1");
                        $stmt->execute([$updated_brokers]);
                        $message = "<span style='color:green;'>✅ Broker '{$new_broker}' added successfully!</span>";
                    } else {
                        $message = "<span style='color:orange;'>⚠️ Broker '{$new_broker}' already exists.</span>";
                    }
                } catch (Exception $e) {
                    $message = "<span style='color:red;'>❌ Error adding broker: " . htmlspecialchars($e->getMessage()) . "</span>";
                }
            } else {
                $message = "<span style='color:red;'>❌ New broker field cannot be empty.</span>";
            }
        }

        if (isset($_POST['add_brokers_link']) && $re_authenticated_for_action) {
            $new_link = trim($_POST['new_link'] ?? '');
            if (!empty($new_link)) {
                try {
                    $current_links = $serverAccount['brokers_link'] ?? '';
                    $links_array = array_filter(array_map('trim', explode(',', $current_links)));
                    if (!in_array($new_link, $links_array)) {
                        $links_array[] = $new_link;
                        $updated_links = implode(',', $links_array);
                        $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET brokers_link = ? WHERE id = 1");
                        $stmt->execute([$updated_links]);
                        $message = "<span style='color:green;'>✅ Broker link '{$new_link}' added successfully!</span>";
                    } else {
                        $message = "<span style='color:orange;'>⚠️ Broker link '{$new_link}' already exists.</span>";
                    }
                } catch (Exception $e) {
                    $message = "<span style='color:red;'>❌ Error adding broker link: " . htmlspecialchars($e->getMessage()) . "</span>";
                }
            } else {
                $message = "<span style='color:red;'>❌ New broker link field cannot be empty.</span>";
            }
        }

        // --- POST Handling: Delete Broker/Link ---
        if (isset($_POST['delete_broker']) && $re_authenticated_for_action) {
            $broker_to_delete = trim($_POST['broker_value'] ?? '');
            if (!empty($broker_to_delete)) {
                try {
                    $current_brokers = $serverAccount['brokers'] ?? '';
                    $brokers_array = array_filter(array_map('trim', explode(',', $current_brokers)));
                    
                    // Find the key to delete
                    $key = array_search($broker_to_delete, $brokers_array);

                    if ($key !== false) {
                        unset($brokers_array[$key]);
                        $updated_brokers = implode(',', array_filter($brokers_array)); // Re-index and implode
                        $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET brokers = ? WHERE id = 1");
                        $stmt->execute([$updated_brokers]);
                        $message = "<span style='color:green;'>✅ Broker '{$broker_to_delete}' deleted successfully!</span>";
                    } else {
                        $message = "<span style='color:orange;'>⚠️ Broker '{$broker_to_delete}' not found.</span>";
                    }
                } catch (Exception $e) {
                    $message = "<span style='color:red;'>❌ Error deleting broker: " . htmlspecialchars($e->getMessage()) . "</span>";
                }
            }
        }
        
        if (isset($_POST['delete_brokers_link']) && $re_authenticated_for_action) {
            $link_to_delete = trim($_POST['link_value'] ?? '');
            if (!empty($link_to_delete)) {
                try {
                    $current_links = $serverAccount['brokers_link'] ?? '';
                    $links_array = array_filter(array_map('trim', explode(',', $current_links)));
                    
                    // Find the key to delete
                    $key = array_search($link_to_delete, $links_array);

                    if ($key !== false) {
                        unset($links_array[$key]);
                        $updated_links = implode(',', array_filter($links_array)); // Re-index and implode
                        $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET brokers_link = ? WHERE id = 1");
                        $stmt->execute([$updated_links]);
                        $message = "<span style='color:green;'>✅ Broker link '{$link_to_delete}' deleted successfully!</span>";
                    } else {
                        $message = "<span style='color:orange;'>⚠️ Broker link '{$link_to_delete}' not found.</span>";
                    }
                } catch (Exception $e) {
                    $message = "<span style='color:red;'>❌ Error deleting broker link: " . htmlspecialchars($e->getMessage()) . "</span>";
                }
            }
        }

        // --- POST Handling: Confirm Payment ---
        if (isset($_POST['confirm_payment']) && $re_authenticated_for_action) {
            $target_table = $_POST['source_table'] ?? '';
            $target_login = $_POST['login'] ?? '';
            
            if (($target_table === $insidersServerTable || $target_table === $insidersTable) && !empty($target_login)) {
                try {
                    $check_stmt = $pdo->prepare("SELECT loyalties FROM {$target_table} WHERE login = ?");
                    $check_stmt->execute([$target_login]);
                    $current_loyalty = $check_stmt->fetchColumn();
                    
                    if ($current_loyalty === 'paid') {
                        $update_stmt = $pdo->prepare("UPDATE {$target_table} SET loyalties = 'paymentconfirmed' WHERE login = ?");
                        $update_stmt->execute([$target_login]);
                        $message = "<span style='color:green;'>✅ Payment for Login {$target_login} successfully Confirmed!</span>";
                    } elseif ($current_loyalty === 'paymentconfirmed') {
                        $message = "<span style='color:orange;'>⚠️ Payment for Login {$target_login} was already Confirmed.</span>";
                    } else {
                        $message = "<span style='color:red;'>❌ Cannot confirm payment for Login {$target_login}. Current status is {$current_loyalty}.</span>";
                    }
                } catch (Exception $e) {
                    $message = "<span style='color:red;'>❌ Error confirming payment: " . htmlspecialchars($e->getMessage()) . "</span>";
                }
            } else {
                $message = "<span style='color:red;'>❌ Invalid payment confirmation request.</span>";
            }
        }
        
        // Re-fetch account data after any potential update
        $stmt = $pdo->prepare("SELECT * FROM {$serverAccountTable} WHERE id = 1");
        $stmt->execute();
        $serverAccount = $stmt->fetch(PDO::FETCH_ASSOC);

        // --- Data Fetching for Views (must run after updates) ---
        if ($currentView === 'paid_users') {
            $paidUsers = [];
            
            // --- MODIFICATION: Only search for 'paid' status ---
            $searchLoyalties = ['paid']; 
            $selectFields = "fullname, email, broker, login, loyalties, paymentdetails";

            // Query 1: insiders_server
            $placeholders = implode(',', array_fill(0, count($searchLoyalties), '?'));
            $stmt1 = $pdo->prepare("SELECT {$selectFields}, '{$insidersServerTable}' AS source FROM {$insidersServerTable} WHERE loyalties IN ({$placeholders})");
            $stmt1->execute($searchLoyalties);
            $paidUsers = array_merge($paidUsers, $stmt1->fetchAll(PDO::FETCH_ASSOC));

            // Query 2: insiders
            $stmt2 = $pdo->prepare("SELECT {$selectFields}, '{$insidersTable}' AS source FROM {$insidersTable} WHERE loyalties IN ({$placeholders})");
            $stmt2->execute($searchLoyalties);
            $paidUsers = array_merge($paidUsers, $stmt2->fetchAll(PDO::FETCH_ASSOC));
        }

    }
    
    // Handle Logout
    if (isset($_GET['logout'])) {
        session_destroy();
        header("Location: serveraccount.php");
        exit;
    }
    
    // Helper function to turn comma-separated string into an array
    function get_list_array($str) {
        return array_filter(array_map('trim', explode(',', $str ?? '')));
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        /* --- THEME MODIFICATION: BLACK/DARK MODE DEFAULT --- */
        :root {
            /* Black Theme */
            --bg-color: #121212; /* Very dark background */
            --text-color: #e0e0e0; /* Light gray text */
            --container-bg: #1e1e1e; /* Dark container background */
            --header-color: #ffffff; /* White headers */
            --primary-color: #4CAF50; /* Green (Action/Success) */
            --primary-hover: #45a049;
            --accent-color: #FF9800; /* Orange (Warning/Secondary Action) */
            --accent-hover: #fb8c00;
            --border-color: #333333; /* Dark border */
            --table-header-bg: #282828; /* Slightly lighter table header */
            --table-even-row-bg: #242424;
            --input-bg: #222222;
        }

        /* Light mode fallback (if system/user prefers light) */
        @media (prefers-color-scheme: light) {
            :root {
                --bg-color: #f4f4f9;
                --text-color: #333;
                --container-bg: white;
                --header-color: #2c3e50;
                --primary-color: #4CAF50;
                --primary-hover: #45a049;
                --accent-color: #e67e22; 
                --accent-hover: #d35400;
                --border-color: #ccc;
                --table-header-bg: #f2f2f2;
                --table-even-row-bg: #f9f9f9;
                --input-bg: white;
            }
        }

        /* General Styles */
        body { 
            font-family: Arial, sans-serif; 
            background-color: var(--bg-color); 
            color: var(--text-color); 
            padding: 20px; 
            transition: background-color 0.3s, color 0.3s; 
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: var(--container-bg); 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.5); /* Stronger shadow in dark theme */
            transition: background 0.3s; 
        }
        .login-container { max-width: 400px; }
        h2, h3 { color: var(--header-color); margin-bottom: 20px; text-align: center; }
        h3 { margin-top: 25px; text-align: left; }
        
        /* Message Styles */
        .message { margin-bottom: 20px; padding: 10px; border-radius: 4px; text-align: center; font-weight: bold; }
        .message span[style*="red"] { background-color: #3d0000 !important; border: 1px solid #c00 !important; display: block; padding: 10px; color: #ff5555 !important; }
        .message span[style*="green"] { background-color: #003d00 !important; border: 1px solid #0c0 !important; display: block; padding: 10px; color: #55ff55 !important; }
        .message span[style*="orange"] { background-color: #3d2d00 !important; border: 1px solid #ff9800 !important; display: block; padding: 10px; color: #ffc107 !important; }

        label { display: block; margin-top: 15px; font-weight: bold; }
        input[type="text"], input[type="password"], input[type="number"], select {
            width: 100%; 
            padding: 10px; 
            margin-top: 5px; 
            border: 1px solid var(--border-color); 
            border-radius: 4px; 
            box-sizing: border-box; 
            background-color: var(--input-bg); 
            color: var(--text-color);
        }
        
        /* Button Styles (using Primary Green/Action) */
        button {
            display: block; 
            width: 100%; 
            padding: 12px; 
            margin-top: 30px; 
            background-color: var(--primary-color); 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 16px; 
            transition: background-color 0.3s;
        }
        button:hover { background-color: var(--primary-hover); }
        .credentials-section { border-top: 2px dashed var(--border-color); margin-top: 40px; padding-top: 20px; display: none; }
        .credentials-section.active { display: block; }
        .logout-link { display: block; text-align: center; margin-top: 20px; color: #e74c3c; text-decoration: none; font-weight: bold; }
        
        /* Secondary Action Buttons (using Accent Orange) */
        .toggle-btn, .back-btn { background-color: var(--accent-color); margin-top: 20px; }
        .toggle-btn:hover, .back-btn:hover { background-color: var(--accent-hover); }
        
        /* Navigation Menu Styles */
        .nav-menu a {
            display: block;
            background-color: var(--accent-color); /* Navigation buttons use accent */
            color: white;
            padding: 15px;
            text-align: center;
            margin-bottom: 10px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .nav-menu a:hover { background-color: var(--accent-hover); }

        /* List Management Styles */
        .list-management { margin-top: 20px; border: 1px solid var(--border-color); padding: 15px; border-radius: 4px; }
        .list-management h4 { margin-top: 0; color: var(--header-color); }
        .list-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px dotted #444; }
        .list-item:last-child { border-bottom: none; }
        .list-item-btn { 
            width: auto !important; 
            padding: 5px 10px !important; 
            margin: 0 0 0 10px !important; 
            font-size: 12px !important; 
            background-color: #e74c3c !important; 
        }
        .list-item-btn:hover { background-color: #c0392b !important; }
        .add-new-form { display: flex; margin-top: 15px; }
        .add-new-form input[type="text"] { flex-grow: 1; margin-top: 0; margin-right: 10px; }
        .add-new-form button { width: auto; padding: 10px 15px; margin: 0; background-color: var(--primary-color); }

        /* User List Table Styles */
        .user-list-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
        .user-list-table th, .user-list-table td { border: 1px solid var(--border-color); padding: 10px; text-align: left; }
        .user-list-table th { background-color: var(--table-header-bg); color: var(--header-color); }
        .user-list-table tr:nth-child(even) { background-color: var(--table-even-row-bg); }
        .user-list-table button { width: auto; padding: 8px 15px; margin: 0; display: inline-block; font-size: 14px; }
        .confirm-btn { background-color: var(--primary-color); margin-top: 0; }
        .confirm-btn:hover { background-color: var(--primary-hover); }
        
        /* Loyalty Status Badges */
        .status-badge { 
            padding: 5px 8px; 
            border-radius: 3px; 
            font-weight: bold; 
            font-size: 12px;
            color: white;
        }
        .loyalty-paid { background-color: #f39c12; } /* Orange/Yellow for 'paid' (Pending Confirmation) */
        .loyalty-paymentconfirmed { background-color: var(--primary-color); } /* Green for 'paymentconfirmed' */


        /* Modal Styles */
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.5); /* Semi-transparent black backdrop */
            backdrop-filter: blur(5px); 
            -webkit-backdrop-filter: blur(5px);
            display: flex; 
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        .modal.show {
            opacity: 1;
            pointer-events: all;
        }
        .modal-content { 
            background-color: var(--container-bg); 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.8); 
            width: 90%; 
            max-width: 350px; 
            text-align: center; 
            transform: scale(0.9);
            transition: transform 0.3s, background-color 0.3s;
        }
        .modal.show .modal-content {
            transform: scale(1);
        }
        .modal-content h3 { color: #e74c3c; margin-top: 0; }
        .modal-content p { color: var(--text-color); }
        .modal-content input[type="password"] { margin: 15px 0; background-color: #222222; border-color: #444; }
        .modal-buttons button { width: 48%; margin-top: 20px; display: inline-block; font-size: 14px; }
        
        /* Modal Button Overrides */
        #modal-confirm-btn { background-color: var(--primary-color); }
        #modal-confirm-btn:hover { background-color: var(--primary-hover); }
        #modal-cancel-btn { background-color: #7f8c8d; } /* Gray color for cancel */
        #modal-cancel-btn:hover { background-color: #6c7a89; }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var credentialsSection = document.getElementById('credentials-section');
            var toggleButton = document.getElementById('toggle-credentials');
            var addressForm = document.getElementById('address-form');
            var credentialsForm = document.getElementById('credentials-form');
            var addBrokerForm = document.getElementById('add-broker-form');
            var deleteBrokerForms = document.querySelectorAll('.delete-broker-form');
            var addLinkForm = document.getElementById('add-link-form');
            var deleteLinkForms = document.querySelectorAll('.delete-link-form');
            var confirmPaymentButtons = document.querySelectorAll('.confirm-payment-btn');

            var modal = document.getElementById('password-modal');
            var modalInput = document.getElementById('modal-password-input');
            var modalConfirmBtn = document.getElementById('modal-confirm-btn');
            var modalCancelBtn = document.getElementById('modal-cancel-btn');
            var modalTitle = document.getElementById('modal-title');
            var modalParagraph = document.getElementById('modal-paragraph');
            
            var currentForm = null;

            // --- Toggle Credentials Section for Address View ---
            if (toggleButton && credentialsSection) {
                toggleButton.addEventListener('click', function() {
                    credentialsSection.classList.toggle('active');
                    toggleButton.textContent = credentialsSection.classList.contains('active') ? 'Hide Admin Credentials Editor' : 'Edit Admin Credentials';
                });
            }

            // --- Custom Modal Handling ---

            function showPasswordModal(form, title, paragraph) {
                currentForm = form;
                modalTitle.textContent = title;
                modalParagraph.innerHTML = paragraph; // Use innerHTML for potential bolding/formatting
                modalInput.value = '';
                modal.classList.add('show'); 
                modalInput.focus();
                return false; 
            }
            
            // Re-authentication for initial page load if not authenticated and not setup
            <?php if (!$authenticated && !$initialSetupRequired): ?>
                var loginForm = document.querySelector('.login-container form');
                if (!loginForm) {
                    window.location.href = 'serveraccount.php';
                }
            <?php endif; ?>
            
            // --- Attach modal trigger to forms (Updates/Actions) ---
            
            // 1. Address Update
            if(addressForm) {
                addressForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    showPasswordModal(this, 'Security Check: Server Settings Update', 'Please enter your Admin Password to authorize updating payment addresses, minimum deposit, and **contract duration**.');
                });
            }

            // 2. Credentials Update
            if(credentialsForm) {
                credentialsForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    showPasswordModal(this, 'Security Check: Credentials Update', 'Please enter your Admin Password to authorize changing your admin credentials.');
                });
            }

            // 3. Add Broker
            if(addBrokerForm) {
                addBrokerForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var newBroker = this.querySelector('input[name="new_broker"]').value;
                    if (newBroker.trim() === '') { alert("Broker name cannot be empty."); return; }
                    showPasswordModal(this, 'Security Check: Add Broker', 'Please enter your Admin Password to authorize adding broker **' + newBroker + '**.');
                });
            }

            // 4. Delete Broker
            deleteBrokerForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var broker = this.querySelector('input[name="broker_value"]').value;
                    showPasswordModal(this, 'Security Check: Delete Broker', 'Please enter your Admin Password to authorize deleting broker **' + broker + '**.');
                });
            });

            // 5. Add Link
            if(addLinkForm) {
                addLinkForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var newLink = this.querySelector('input[name="new_link"]').value;
                    if (newLink.trim() === '') { alert("Link cannot be empty."); return; }
                    showPasswordModal(this, 'Security Check: Add Broker Link', 'Please enter your Admin Password to authorize adding link **' + newLink + '**.');
                });
            }

            // 6. Delete Link
            deleteLinkForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var link = this.querySelector('input[name="link_value"]').value;
                    showPasswordModal(this, 'Security Check: Delete Broker Link', 'Please enter your Admin Password to authorize deleting link **' + link + '**.');
                });
            });


            // 7. Confirm Payment
            confirmPaymentButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    var form = this.closest('form');
                    var login = form.querySelector('input[name="login"]').value;
                    showPasswordModal(form, 'Security Check: Confirm Payment', 'Please enter your Admin Password to confirm payment for login **' + login + '**.');
                });
            });

            // Handle Confirm button in modal
            modalConfirmBtn.addEventListener('click', function() {
                var password = modalInput.value;
                if (password.length > 0) {
                    var hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'admin_confirmation_password';
                    hiddenInput.value = password;
                    
                    var loginIdInput = document.createElement('input');
                    loginIdInput.type = 'hidden';
                    loginIdInput.name = 'login_id';
                    loginIdInput.value = '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>';
                    
                    // Remove old hidden inputs if they exist
                    var oldInput = currentForm.querySelector('input[name="admin_confirmation_password"]');
                    if (oldInput) oldInput.remove();
                    var oldLoginIdInput = currentForm.querySelector('input[name="login_id"]');
                    if (oldLoginIdInput) oldLoginIdInput.remove();
                    
                    currentForm.appendChild(hiddenInput);
                    currentForm.appendChild(loginIdInput); 
                    
                    modal.classList.remove('show');
                    currentForm.submit(); 
                } else {
                    alert("Password cannot be empty.");
                    modalInput.focus();
                }
            });

            // Handle Cancel button in modal
            modalCancelBtn.addEventListener('click', function() {
                modal.classList.remove('show');
                currentForm = null;
            });

            // Close modal if user clicks outside of it
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.classList.remove('show');
                    currentForm = null;
                }
            }
        });
    </script>
</head>
<body>

    <?php if ($initialSetupRequired || !$authenticated): ?>
        <div class="container login-container">
            <h2><?= $initialSetupRequired ? '🔑 Admin Setup' : '🔒 Admin Login' ?></h2>
            <?php if ($message): ?>
                <p class="message"><?= $message ?></p>
            <?php endif; ?>
            
            <?php if (!$authenticated && !$initialSetupRequired): ?>
                        <p style="text-align: center; color: #e74c3c; font-weight: bold;">Session expired or login required.</p>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="<?= $initialSetupRequired ? 'initial_setup' : 'admin_login' ?>" value="1">
                <label for="login_id">Login ID:</label>
                <input type="text" id="login_id" name="login_id" required autofocus 
                        value="<?= htmlspecialchars($serverAccount['admin_login_id'] ?? ($_POST['login_id'] ?? '')) ?>">

                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>

                <button type="submit"><?= $initialSetupRequired ? 'Set Credentials & Login' : 'Login' ?></button>
            </form>
        </div>

    <?php else: // Display main admin dashboard ?>

        <div class="container">
            <a href="?logout=1" class="logout-link">Logout</a>

            <?php if ($message): ?>
                <p class="message"><?= $message ?></p>
            <?php endif; ?>
            
            <?php if ($currentView !== 'menu'): ?>
                <a href="serveraccount.php?view=menu" class="back-btn">← Back to Menu</a>
            <?php endif; ?>

            <?php if ($currentView === 'menu'): ?>
                <h2>🏠 Admin Navigation Menu</h2>
                <div class="nav-menu">
                    <a href="serveraccount.php?view=settings">⚙️ Edit Addresses, Brokers, and Admin Credentials</a>
                    <a href="serveraccount.php?view=paid_users">💳 View Users That Paid (Pending Confirmation)</a>
                </div>

            <?php elseif ($currentView === 'settings'): ?>
                <h2>⚙️ Edit Server Settings</h2>
                
                <form method="POST" id="address-form">
                    <input type="hidden" name="update_addresses" value="1">
                    
                    <h3>Payment Addresses & Deposit Settings</h3>
                    
                    <label for="minimum_deposit">Minimum Deposit Amount (Decimal):</label>
                    <input type="number" step="0.01" min="0.00" id="minimum_deposit" name="minimum_deposit" value="<?= htmlspecialchars($serverAccount['minimum_deposit'] ?? '0.00') ?>" required>

                    <label for="contract_duration">Contract Duration (Days, Leave empty for NULL):</label>
                    <input type="number" min="0" id="contract_duration" name="contract_duration" value="<?= htmlspecialchars($serverAccount['contract_duration'] ?? '') ?>">
                    
                    <label for="btc_address">Bitcoin (BTC) Address:</label>
                    <input type="text" id="btc_address" name="btc_address" value="<?= htmlspecialchars($serverAccount['btc_address'] ?? '') ?>" required>
                    
                    <label for="eth_address">Ethereum (ETH) Address:</label>
                    <input type="text" id="eth_address" name="eth_address" value="<?= htmlspecialchars($serverAccount['eth_address'] ?? '') ?>" required>
                    
                    <label for="eth_network">ETH Network:</label>
                    <input type="text" id="eth_network" name="eth_network" value="<?= htmlspecialchars($serverAccount['eth_network'] ?? 'ERC20') ?>" required>

                    <label for="usdt_address">Tether (USDT) Address:</label>
                    <input type="text" id="usdt_address" name="usdt_address" value="<?= htmlspecialchars($serverAccount['usdt_address'] ?? '') ?>" required>
                    
                    <label for="usdt_network">USDT Network:</label>
                    <input type="text" id="usdt_network" name="usdt_network" value="<?= htmlspecialchars($serverAccount['usdt_network'] ?? 'TRC20') ?>" required>

                    <button type="submit">Update Payment Settings</button>
                </form>

                <hr style="margin: 40px 0; border-color: var(--border-color);">

                <h3>Broker Management 📊</h3>
                <?php $current_brokers = get_list_array($serverAccount['brokers'] ?? ''); ?>
                <div class="list-management">
                    <h4>Current Brokers (<?= count($current_brokers) ?>)</h4>
                    <?php if (!empty($current_brokers)): ?>
                        <?php foreach ($current_brokers as $broker): ?>
                            <div class="list-item">
                                <span><?= htmlspecialchars($broker) ?></span>
                                <form method="POST" class="delete-broker-form" style="display:inline;">
                                    <input type="hidden" name="delete_broker" value="1">
                                    <input type="hidden" name="broker_value" value="<?= htmlspecialchars($broker) ?>">
                                    <button type="submit" class="list-item-btn">Delete</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #7f8c8d;">No brokers configured.</p>
                    <?php endif; ?>

                    <h4>Add New Broker</h4>
                    <form method="POST" id="add-broker-form" class="add-new-form">
                        <input type="hidden" name="add_broker" value="1">
                        <input type="text" name="new_broker" placeholder="e.g., BrokerXYZ" required>
                        <button type="submit">Add Broker</button>
                    </form>
                </div>
                
                <h4 style="margin-top: 40px;">Broker Links Management 🔗</h4>
                <?php $current_links = get_list_array($serverAccount['brokers_link'] ?? ''); ?>
                <div class="list-management">
                    <h4>Current Broker Links (<?= count($current_links) ?>)</h4>
                    <p style="font-size: 12px; color: #7f8c8d;">(Links must correspond to the order of brokers above)</p>
                    <?php if (!empty($current_links)): ?>
                        <?php foreach ($current_links as $link): ?>
                            <div class="list-item">
                                <span style="font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($link) ?></span>
                                <form method="POST" class="delete-link-form" style="display:inline;">
                                    <input type="hidden" name="delete_brokers_link" value="1">
                                    <input type="hidden" name="link_value" value="<?= htmlspecialchars($link) ?>">
                                    <button type="submit" class="list-item-btn">Delete</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #7f8c8d;">No broker links configured.</p>
                    <?php endif; ?>
                    
                    <h4>Add New Broker Link (URL)</h4>
                    <form method="POST" id="add-link-form" class="add-new-form">
                        <input type="hidden" name="add_brokers_link" value="1">
                        <input type="text" name="new_link" placeholder="e.g., https://brokerxyz.com/signup" required>
                        <button type="submit">Add Link</button>
                    </form>
                </div>


                <button id="toggle-credentials" class="toggle-btn">Edit Admin Credentials</button>

                <div id="credentials-section" class="credentials-section">
                    <h2>👤 Edit Admin Credentials</h2>
                    <form method="POST" id="credentials-form">
                        <input type="hidden" name="update_credentials" value="1">
                        
                        <label for="new_login_id">New Login ID:</label>
                        <input type="text" id="new_login_id" name="new_login_id" value="<?= htmlspecialchars($serverAccount['admin_login_id'] ?? '') ?>" required>

                        <label for="new_password">New Password (Leave blank to keep current):</label>
                        <input type="password" id="new_password" name="new_password" placeholder="********">
                        
                        <button type="submit">Update Credentials</button>
                    </form>
                </div>
            
            <?php elseif ($currentView === 'paid_users'): ?>
                <h2> Users that have paid 💳.</h2>
                <?php if (!empty($paidUsers)): ?>
                    <table class="user-list-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Broker</th>
                                <th>Login</th>
                                <th>Source Table</th>
                                <th>Loyalties</th> 
                                <th>Payment Details</th> 
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paidUsers as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['fullname']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['broker']) ?></td>
                                    <td><?= htmlspecialchars($user['login']) ?></td>
                                    <td><?= htmlspecialchars($user['source']) ?></td> 
                                    <td>
                                        <span class="status-badge loyalty-<?= htmlspecialchars($user['loyalties']) ?>">
                                            <?= htmlspecialchars($user['loyalties']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($user['paymentdetails'] ?? '-') ?></td> 
                                    <td>
                                        <?php if ($user['loyalties'] === 'paid'): ?>
                                            <form method="POST" class="confirm-payment-form">
                                                <input type="hidden" name="confirm_payment" value="1">
                                                <input type="hidden" name="source_table" value="<?= htmlspecialchars($user['source']) ?>">
                                                <input type="hidden" name="login" value="<?= htmlspecialchars($user['login']) ?>">
                                                <button type="submit" class="confirm-payment-btn confirm-btn">Confirm</button>
                                            </form>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="text-align: center; margin-top: 20px;">Users that paid: <?= count($paidUsers) ?></p>
                <?php else: ?>
                    <p style="text-align: center; padding: 20px; border: 1px dashed var(--border-color);">No user has paid yet.</p>
                <?php endif; ?>
            <?php endif; ?>
            
        </div>
        
        <div id="password-modal" class="modal">
            <div class="modal-content">
                <h3 id="modal-title">SECURITY CHECK</h3>
                <p id="modal-paragraph">Please enter your Admin Password to authorize this action.</p>
                <input type="password" id="modal-password-input" placeholder="Admin Password" required>
                <div class="modal-buttons">
                    <button type="button" id="modal-confirm-btn">Confirm</button>
                    <button type="button" id="modal-cancel-btn">Cancel</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>