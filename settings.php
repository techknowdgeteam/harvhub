<?php
// settings.php - Server Settings and Configuration
// This file is included in serveraccount.php when view=settings
?>

<h2> Server Settings</h2>

<form method="POST" action="serveraccount.php?view=settings" id="address-form">
    <input type="hidden" name="update_addresses" value="1">
    
    <h3> Payment & Revenue Settings</h3>
    <div class="settings-grid">
        <div class="settings-card">
            <label for="contract_duration">Contract Duration (Days)</label>
            <input type="number" min="0" id="contract_duration" name="contract_duration" value="<?= htmlspecialchars($serverAccount['contract_duration'] ?? '') ?>">
        </div>
    </div>
    
    <h3>Profit Sharing Settings</h3>
    <div class="settings-grid">
        <div class="settings-card">
            <label for="server_share_percent">Server Share (%)</label>
            <input type="number" min="0" max="100" id="server_share_percent" name="server_share_percent" value="<?= htmlspecialchars($serverAccount['server_share_percent'] ?? '30') ?>">
        </div>
        <div class="settings-card">
            <label for="user_share_percent">User Share (%)</label>
            <input type="number" min="0" max="100" id="user_share_percent" name="user_share_percent" value="<?= htmlspecialchars($serverAccount['user_share_percent'] ?? '70') ?>">
        </div>
        <div class="settings-card">
            <label for="min_profit_for_split">Min Profit for Split ($)</label>
            <input type="number" step="0.01" min="0" id="min_profit_for_split" name="min_profit_for_split" value="<?= htmlspecialchars($serverAccount['min_profit_for_split'] ?? '30.00') ?>">
        </div>
        <div class="settings-card">
            <label for="min_broker_balance">Min Broker Balance ($)</label>
            <input type="number" step="0.01" min="0" id="min_broker_balance" name="min_broker_balance" value="<?= htmlspecialchars($serverAccount['min_broker_balance'] ?? '30.00') ?>">
        </div>
    </div>
    
    <h3> Crypto Addresses</h3>
    <div class="settings-grid">
        <div class="settings-card">
            <label for="btc_address">BTC Address</label>
            <input type="text" id="btc_address" name="btc_address" value="<?= htmlspecialchars($serverAccount['btc_address'] ?? '') ?>" required>
        </div>
        <div class="settings-card">
            <label for="eth_address">ETH Address</label>
            <input type="text" id="eth_address" name="eth_address" value="<?= htmlspecialchars($serverAccount['eth_address'] ?? '') ?>" required>
        </div>
        <div class="settings-card">
            <label for="eth_network">ETH Network</label>
            <input type="text" id="eth_network" name="eth_network" value="<?= htmlspecialchars($serverAccount['eth_network'] ?? 'ERC20') ?>" required>
        </div>
        <div class="settings-card">
            <label for="usdt_address">USDT Address</label>
            <input type="text" id="usdt_address" name="usdt_address" value="<?= htmlspecialchars($serverAccount['usdt_address'] ?? '') ?>" required>
        </div>
        <div class="settings-card">
            <label for="usdt_network">USDT Network</label>
            <input type="text" id="usdt_network" name="usdt_network" value="<?= htmlspecialchars($serverAccount['usdt_network'] ?? 'TRC20') ?>" required>
        </div>
    </div>

    <!-- ===== Columns to Reset Section ===== -->
    <h3>Columns to Reset</h3>
    <div class="columns-reset-container">
        <p class="help-text">
            <strong>💡 Configure columns to reset with their default values when a contract is completed.</strong>
        </p>
        
        <div class="columns-reset-input-group">
            <div class="columns-reset-input-wrapper">
                <input type="text" id="new_column_to_reset" placeholder="Column name (e.g., broker_balance)" class="column-input">
                <input type="text" id="new_column_value" placeholder="Default value (optional)" class="column-value-input">
                <button type="button" id="add-column-btn" class="btn-add-column">
                    ➕ Add
                </button>
            </div>
            <small class="input-helper-text">Leave value blank for NULL, use numbers for numeric values, text for string values.</small>
        </div>
        
        <div id="columns-to-reset-display" class="columns-display-area">
            <?php 
            $columnsToReset = json_decode($serverAccount['columns_to_reset'] ?? '[]', true);
            if (!is_array($columnsToReset)) {
                $columnsToReset = [];
            }
            if (!empty($columnsToReset)):
                foreach ($columnsToReset as $entry): 
                    $columnName = $entry['column'] ?? '';
                    $columnValue = $entry['value'] ?? null;
                    $displayValue = ($columnValue !== null) ? $columnValue : 'NULL';
                    if (is_string($displayValue) && $displayValue !== 'NULL') {
                        $displayValue = '"' . $displayValue . '"';
                    }
                ?>
                    <span class="column-tag saved-column" data-column="<?= htmlspecialchars($columnName) ?>">
                        <span class="tag-text">
                            <strong><?= htmlspecialchars($columnName) ?></strong>
                            <span class="column-value-display">= <?= htmlspecialchars($displayValue) ?></span>
                        </span>
                        <button type="button" class="remove-column-btn" data-column="<?= htmlspecialchars($columnName) ?>" data-temp="false">✕</button>
                    </span>
                <?php endforeach;
            else: ?>
                <span class="empty-message">No columns configured to reset.</span>
            <?php endif; ?>
        </div>
        
        <input type="hidden" id="columns_to_reset_hidden" name="columns_to_reset" value='<?= htmlspecialchars($serverAccount['columns_to_reset'] ?? '[]') ?>'>
        
        <button type="button" id="save-columns-btn" class="btn-save-columns">
            💾 Save Columns to Reset
        </button>
    </div>

    <button type="button" id="save-all-settings-btn">💾 Save All Settings</button>
</form>

<hr>

<h3> Broker Management</h3>
<?php $current_brokers = get_list_array($serverAccount['brokers'] ?? ''); ?>
<div class="list-management">
    <h4>Current Brokers (<?= count($current_brokers) ?>)</h4>
    <?php if (!empty($current_brokers)): ?>
        <?php foreach ($current_brokers as $broker): ?>
            <div class="list-item">
                <span><?= htmlspecialchars($broker) ?></span>
                <button type="button" class="list-item-btn delete-broker-btn" data-broker="<?= htmlspecialchars($broker) ?>">Delete</button>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted);">No brokers configured.</p>
    <?php endif; ?>

    <h4>Add New Broker</h4>
    <div class="add-new-form">
        <input type="text" id="new_broker_input" placeholder="e.g., BrokerXYZ" required>
        <button type="button" id="add-broker-btn">Add Broker</button>
    </div>
</div>

<h4 style="margin-top: 30px;">🔗 Broker Links</h4>
<?php $current_links = get_list_array($serverAccount['brokers_link'] ?? ''); ?>
<div class="list-management">
    <h4>Current Links (<?= count($current_links) ?>)</h4>
    <p style="font-size: 12px; color: var(--text-muted);">Links correspond to broker order above.</p>
    <?php if (!empty($current_links)): ?>
        <?php foreach ($current_links as $link): ?>
            <div class="list-item">
                <span style="font-size: 13px; word-break: break-all;"><?= htmlspecialchars($link) ?></span>
                <button type="button" class="list-item-btn delete-link-btn" data-link="<?= htmlspecialchars($link) ?>">Delete</button>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted);">No broker links configured.</p>
    <?php endif; ?>
    
    <h4>Add New Link</h4>
    <div class="add-new-form">
        <input type="text" id="new_link_input" placeholder="https://broker.com/signup" required>
        <button type="button" id="add-link-btn">Add Link</button>
    </div>
</div>

<button type="button" id="toggle-credentials" class="toggle-btn">👤 Edit Admin Credentials</button>

<div id="credentials-section" class="credentials-section">
    <h2>Edit Admin Credentials</h2>
    <div class="credentials-form-wrapper">
        <label for="new_login_id">New Login ID:</label>
        <input type="text" id="new_login_id" name="new_login_id" value="<?= htmlspecialchars($serverAccount['admin_login_id'] ?? '') ?>" required>

        <label for="new_password">New Password (leave blank to keep):</label>
        <input type="password" id="new_password" name="new_password" placeholder="********">
        
        <button type="button" id="update-credentials-btn">Update Credentials</button>
    </div>
</div>

<!-- Password Modal for Settings -->
<div id="settings-password-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3 id="settings-modal-title">🔐 Security Verification</h3>
        <p id="settings-modal-message">Please enter your admin password to confirm this action.</p>
        <input type="password" id="settings-modal-password" placeholder="Admin Password" autocomplete="off">
        <div class="modal-buttons">
            <button type="button" id="settings-modal-cancel">Cancel</button>
            <button type="button" id="settings-modal-confirm">Confirm</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // ============================================
        // PASSWORD MODAL FUNCTIONS
        // ============================================
        
        let pendingAction = null;
        let pendingActionData = null;
        
        function showSettingsPasswordModal(title, message, onConfirm) {
            const modal = document.getElementById('settings-password-modal');
            const titleEl = document.getElementById('settings-modal-title');
            const messageEl = document.getElementById('settings-modal-message');
            const passwordInput = document.getElementById('settings-modal-password');
            const confirmBtn = document.getElementById('settings-modal-confirm');
            const cancelBtn = document.getElementById('settings-modal-cancel');
            
            if (!modal) return;
            
            titleEl.textContent = title || '🔐 Security Verification';
            messageEl.textContent = message || 'Please enter your admin password to confirm this action.';
            passwordInput.value = '';
            
            modal.style.display = 'flex';
            modal.style.opacity = '1';
            modal.style.pointerEvents = 'all';
            
            setTimeout(() => {
                passwordInput.focus();
            }, 100);
            
            // Remove old listeners
            const newConfirmBtn = confirmBtn.cloneNode(true);
            const newCancelBtn = cancelBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
            
            // Get login_id from the page
            const loginId = document.querySelector('#new_login_id') ? document.querySelector('#new_login_id').value : '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>';
            
            newConfirmBtn.addEventListener('click', function() {
                const password = passwordInput.value;
                if (!password) {
                    showCustomModal('Error', 'Please enter your password.', 'error');
                    return;
                }
                
                // Verify password first
                verifyPassword(password, loginId, function(isValid) {
                    if (isValid) {
                        modal.style.display = 'none';
                        modal.style.opacity = '0';
                        modal.style.pointerEvents = 'none';
                        if (onConfirm) {
                            onConfirm(password);
                        }
                    } else {
                        showCustomModal('Error', '❌ Invalid password. Please try again.', 'error');
                        passwordInput.value = '';
                        passwordInput.focus();
                    }
                });
            });
            
            newCancelBtn.addEventListener('click', function() {
                modal.style.display = 'none';
                modal.style.opacity = '0';
                modal.style.pointerEvents = 'none';
                pendingAction = null;
                pendingActionData = null;
            });
            
            passwordInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    newConfirmBtn.click();
                }
            });
            
            // Close on ESC
            document.addEventListener('keydown', function escHandler(e) {
                if (e.key === 'Escape' && modal.style.display === 'flex') {
                    modal.style.display = 'none';
                    modal.style.opacity = '0';
                    modal.style.pointerEvents = 'none';
                    pendingAction = null;
                    pendingActionData = null;
                    document.removeEventListener('keydown', escHandler);
                }
            });
            
            // Close on click outside
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                    modal.style.opacity = '0';
                    modal.style.pointerEvents = 'none';
                    pendingAction = null;
                    pendingActionData = null;
                }
            });
        }
        
        function verifyPassword(password, loginId, callback) {
            fetch('serveraccount.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=verify_password&password=' + encodeURIComponent(password) + '&login_id=' + encodeURIComponent(loginId)
            })
            .then(response => response.json())
            .then(data => {
                callback(data.success === true);
            })
            .catch(function(error) {
                console.error('Error:', error);
                callback(false);
            });
        }
        
        // ============================================
        // SAVE ALL SETTINGS (with password modal)
        // ============================================
        
        document.getElementById('save-all-settings-btn').addEventListener('click', function() {
            showSettingsPasswordModal(
                '🔐 Security Verification',
                'Please enter your admin password to save all settings changes.',
                function(password) {
                    submitSettingsForm(password);
                }
            );
        });
        
        function submitSettingsForm(password) {
            // Get form data
            const form = document.getElementById('address-form');
            const formData = new FormData(form);
            
            // Add admin password and login_id
            formData.append('admin_confirmation_password', password);
            formData.append('login_id', document.querySelector('#new_login_id') ? document.querySelector('#new_login_id').value : '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
            formData.append('update_addresses', '1');
            
            // Get columns_to_reset from hidden input
            const columnsHidden = document.getElementById('columns_to_reset_hidden');
            if (columnsHidden) {
                formData.set('columns_to_reset', columnsHidden.value);
            }
            
            // Show loading state
            const btn = this;
            const originalText = btn.textContent;
            btn.textContent = '⏳ Saving...';
            btn.disabled = true;
            
            fetch('serveraccount.php?view=settings', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Check if we got redirected (success)
                if (html.includes('✅ Payment settings updated successfully!') || html.includes('Session expired')) {
                    // Reload the page to show updated settings
                    window.location.href = 'serveraccount.php?view=settings';
                } else {
                    // Check for error messages
                    const errorMatch = html.match(/<span style='color:red;'>❌([^<]*)<\/span>/);
                    if (errorMatch) {
                        showCustomModal('Error', errorMatch[1].trim(), 'error');
                    } else {
                        // Reload anyway to show updated state
                        window.location.href = 'serveraccount.php?view=settings';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showCustomModal('Error', 'Error saving settings. Please try again.', 'error');
                btn.textContent = originalText;
                btn.disabled = false;
            });
        }
        
        // ============================================
        // SAVE COLUMNS TO RESET (with password modal)
        // ============================================
        
        document.getElementById('save-columns-btn').addEventListener('click', function() {
            showSettingsPasswordModal(
                '🔐 Security Verification',
                'Please enter your admin password to save columns to reset.',
                function(password) {
                    saveColumnsToReset(password);
                }
            );
        });
        
        function saveColumnsToReset(password) {
            const hiddenInput = document.getElementById('columns_to_reset_hidden');
            const columnsData = hiddenInput.value;
            
            const formData = new URLSearchParams();
            formData.append('action', 'update_config_entry');
            formData.append('target_type', 'server');
            formData.append('entry_key', 'columns_to_reset');
            formData.append('value', columnsData);
            formData.append('admin_password', password);
            formData.append('login_id', document.querySelector('#new_login_id') ? document.querySelector('#new_login_id').value : '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
            
            const btn = this;
            const originalText = btn.textContent;
            btn.textContent = '⏳ Saving...';
            btn.disabled = true;
            
            fetch('serveraccount.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showCustomModal('Success', 'Columns to reset saved successfully!', 'success');
                    // Reload to show updated state
                    setTimeout(() => {
                        window.location.href = 'serveraccount.php?view=settings';
                    }, 1000);
                } else {
                    showCustomModal('Error', data.error || 'Error saving columns to reset', 'error');
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showCustomModal('Error', 'Error saving columns to reset', 'error');
                btn.textContent = originalText;
                btn.disabled = false;
            });
        }

        // ============================================
        // UPDATE CREDENTIALS (with password modal)
        // ============================================
        
        document.getElementById('update-credentials-btn').addEventListener('click', function() {
            const loginId = document.getElementById('new_login_id').value.trim();
            const newPassword = document.getElementById('new_password').value;
            
            if (!loginId) {
                showCustomModal('Error', 'Login ID is required.', 'error');
                return;
            }
            
            showSettingsPasswordModal(
                '🔐 Security Verification',
                'Please enter your current admin password to update credentials.',
                function(password) {
                    updateCredentials(password, loginId, newPassword);
                }
            );
        });
        
        function updateCredentials(password, loginId, newPassword) {
            const formData = new URLSearchParams();
            formData.append('update_credentials', '1');
            formData.append('new_login_id', loginId);
            formData.append('new_password', newPassword);
            formData.append('admin_confirmation_password', password);
            formData.append('login_id', document.querySelector('#new_login_id') ? document.querySelector('#new_login_id').value : '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
            
            const btn = document.getElementById('update-credentials-btn');
            const originalText = btn.textContent;
            btn.textContent = '⏳ Updating...';
            btn.disabled = true;
            
            fetch('serveraccount.php?view=settings', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                if (html.includes('✅ Credentials updated successfully!') || html.includes('Session terminated')) {
                    showCustomModal('Success', '✅ Credentials updated successfully! Please login with your new credentials.', 'success');
                    setTimeout(() => {
                        window.location.href = 'serveraccount.php?logout=1';
                    }, 2000);
                } else {
                    const errorMatch = html.match(/<span style='color:red;'>❌([^<]*)<\/span>/);
                    if (errorMatch) {
                        showCustomModal('Error', errorMatch[1].trim(), 'error');
                    } else {
                        showCustomModal('Error', 'Error updating credentials. Please try again.', 'error');
                    }
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showCustomModal('Error', 'Error updating credentials', 'error');
                btn.textContent = originalText;
                btn.disabled = false;
            });
        }

        // ============================================
        // ADD BROKER (with password modal)
        // ============================================
        
        document.getElementById('add-broker-btn').addEventListener('click', function() {
            const brokerName = document.getElementById('new_broker_input').value.trim();
            if (!brokerName) {
                showCustomModal('Error', 'Please enter a broker name.', 'error');
                return;
            }
            
            showSettingsPasswordModal(
                '🔐 Security Verification',
                'Please enter your admin password to add broker: ' + brokerName,
                function(password) {
                    addBroker(password, brokerName);
                }
            );
        });
        
        function addBroker(password, brokerName) {
            const formData = new URLSearchParams();
            formData.append('add_broker', '1');
            formData.append('new_broker', brokerName);
            formData.append('admin_confirmation_password', password);
            formData.append('login_id', document.querySelector('#new_login_id') ? document.querySelector('#new_login_id').value : '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
            
            const btn = document.getElementById('add-broker-btn');
            const originalText = btn.textContent;
            btn.textContent = '⏳ Adding...';
            btn.disabled = true;
            
            fetch('serveraccount.php?view=settings', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                if (html.includes('✅ Broker')) {
                    showCustomModal('Success', '✅ Broker added successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = 'serveraccount.php?view=settings';
                    }, 1000);
                } else {
                    const errorMatch = html.match(/<span style='color:red;'>❌([^<]*)<\/span>/) || html.match(/<span style='color:orange;'>⚠️([^<]*)<\/span>/);
                    if (errorMatch) {
                        showCustomModal('Error', errorMatch[1].trim(), 'error');
                    } else {
                        showCustomModal('Error', 'Error adding broker', 'error');
                    }
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showCustomModal('Error', 'Error adding broker', 'error');
                btn.textContent = originalText;
                btn.disabled = false;
            });
        }

        // ============================================
        // DELETE BROKER (with password modal)
        // ============================================
        
        document.querySelectorAll('.delete-broker-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const brokerName = this.getAttribute('data-broker');
                showCustomConfirm(
                    'Confirm Delete',
                    'Are you sure you want to delete broker: "' + brokerName + '"?',
                    'Delete',
                    'Cancel',
                    function() {
                        showSettingsPasswordModal(
                            '🔐 Security Verification',
                            'Please enter your admin password to delete broker: ' + brokerName,
                            function(password) {
                                deleteBroker(password, brokerName);
                            }
                        );
                    }
                );
            });
        });
        
        function deleteBroker(password, brokerName) {
            const formData = new URLSearchParams();
            formData.append('delete_broker', '1');
            formData.append('broker_value', brokerName);
            formData.append('admin_confirmation_password', password);
            formData.append('login_id', document.querySelector('#new_login_id') ? document.querySelector('#new_login_id').value : '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
            
            fetch('serveraccount.php?view=settings', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                if (html.includes('✅ Broker') && html.includes('deleted')) {
                    showCustomModal('Success', '✅ Broker deleted successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = 'serveraccount.php?view=settings';
                    }, 1000);
                } else {
                    const errorMatch = html.match(/<span style='color:red;'>❌([^<]*)<\/span>/) || html.match(/<span style='color:orange;'>⚠️([^<]*)<\/span>/);
                    if (errorMatch) {
                        showCustomModal('Error', errorMatch[1].trim(), 'error');
                    } else {
                        showCustomModal('Error', 'Error deleting broker', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showCustomModal('Error', 'Error deleting broker', 'error');
            });
        }

        // ============================================
        // ADD LINK (with password modal)
        // ============================================
        
        document.getElementById('add-link-btn').addEventListener('click', function() {
            const link = document.getElementById('new_link_input').value.trim();
            if (!link) {
                showCustomModal('Error', 'Please enter a link URL.', 'error');
                return;
            }
            
            showSettingsPasswordModal(
                '🔐 Security Verification',
                'Please enter your admin password to add broker link.',
                function(password) {
                    addLink(password, link);
                }
            );
        });
        
        function addLink(password, link) {
            const formData = new URLSearchParams();
            formData.append('add_brokers_link', '1');
            formData.append('new_link', link);
            formData.append('admin_confirmation_password', password);
            formData.append('login_id', document.querySelector('#new_login_id') ? document.querySelector('#new_login_id').value : '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
            
            const btn = document.getElementById('add-link-btn');
            const originalText = btn.textContent;
            btn.textContent = '⏳ Adding...';
            btn.disabled = true;
            
            fetch('serveraccount.php?view=settings', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                if (html.includes('✅ Broker link')) {
                    showCustomModal('Success', '✅ Link added successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = 'serveraccount.php?view=settings';
                    }, 1000);
                } else {
                    const errorMatch = html.match(/<span style='color:red;'>❌([^<]*)<\/span>/) || html.match(/<span style='color:orange;'>⚠️([^<]*)<\/span>/);
                    if (errorMatch) {
                        showCustomModal('Error', errorMatch[1].trim(), 'error');
                    } else {
                        showCustomModal('Error', 'Error adding link', 'error');
                    }
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showCustomModal('Error', 'Error adding link', 'error');
                btn.textContent = originalText;
                btn.disabled = false;
            });
        }

        // ============================================
        // DELETE LINK (with password modal)
        // ============================================
        
        document.querySelectorAll('.delete-link-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const link = this.getAttribute('data-link');
                showCustomConfirm(
                    'Confirm Delete',
                    'Are you sure you want to delete this link?',
                    'Delete',
                    'Cancel',
                    function() {
                        showSettingsPasswordModal(
                            '🔐 Security Verification',
                            'Please enter your admin password to delete this link.',
                            function(password) {
                                deleteLink(password, link);
                            }
                        );
                    }
                );
            });
        });
        
        function deleteLink(password, link) {
            const formData = new URLSearchParams();
            formData.append('delete_brokers_link', '1');
            formData.append('link_value', link);
            formData.append('admin_confirmation_password', password);
            formData.append('login_id', document.querySelector('#new_login_id') ? document.querySelector('#new_login_id').value : '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
            
            fetch('serveraccount.php?view=settings', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                if (html.includes('✅ Broker link') && html.includes('deleted')) {
                    showCustomModal('Success', '✅ Link deleted successfully!', 'success');
                    setTimeout(() => {
                        window.location.href = 'serveraccount.php?view=settings';
                    }, 1000);
                } else {
                    const errorMatch = html.match(/<span style='color:red;'>❌([^<]*)<\/span>/) || html.match(/<span style='color:orange;'>⚠️([^<]*)<\/span>/);
                    if (errorMatch) {
                        showCustomModal('Error', errorMatch[1].trim(), 'error');
                    } else {
                        showCustomModal('Error', 'Error deleting link', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showCustomModal('Error', 'Error deleting link', 'error');
            });
        }

        // ============================================
        // TOGGLE CREDENTIALS SECTION
        // ============================================
        
        const toggleBtn = document.getElementById('toggle-credentials');
        const credSection = document.getElementById('credentials-section');
        
        if (toggleBtn && credSection) {
            credSection.style.display = 'none';
            
            toggleBtn.addEventListener('click', function() {
                if (credSection.style.display === 'none') {
                    credSection.style.display = 'block';
                    toggleBtn.textContent = '👤 Hide Admin Credentials';
                } else {
                    credSection.style.display = 'none';
                    toggleBtn.textContent = '👤 Edit Admin Credentials';
                }
            });
        }

        // ============================================
        // COLUMNS TO RESET MANAGEMENT
        // ============================================
        
        const addColumnBtn = document.getElementById('add-column-btn');
        const newColumnInput = document.getElementById('new_column_to_reset');
        const newValueInput = document.getElementById('new_column_value');
        const columnsDisplay = document.getElementById('columns-to-reset-display');
        const hiddenInput = document.getElementById('columns_to_reset_hidden');
        
        let columnsToReset = [];
        let tempColumns = [];

        // Initialize from hidden input
        try {
            const existingData = JSON.parse(hiddenInput.value || '[]');
            if (Array.isArray(existingData)) {
                columnsToReset = existingData;
            }
        } catch (e) {
            columnsToReset = [];
        }

        function getDisplayValue(value) {
            if (value === null || value === undefined) return 'NULL';
            if (typeof value === 'string') return '"' + value + '"';
            return value;
        }

        function renderColumns() {
            const allColumns = [...columnsToReset, ...tempColumns];
            columnsDisplay.innerHTML = '';
            
            if (allColumns.length === 0) {
                columnsDisplay.innerHTML = '<span class="empty-message">No columns configured to reset.</span>';
                return;
            }
            
            allColumns.forEach(function(entry) {
                const columnName = entry.column;
                const value = entry.value;
                const isSaved = columnsToReset.some(e => e.column === columnName);
                const displayValue = getDisplayValue(value);
                
                const span = document.createElement('span');
                span.className = 'column-tag ' + (isSaved ? 'saved-column' : 'temp-column');
                span.setAttribute('data-column', columnName);
                
                const textSpan = document.createElement('span');
                textSpan.className = 'tag-text';
                textSpan.innerHTML = '<strong>' + columnName + '</strong> <span class="column-value-display">= ' + displayValue + '</span>';
                
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-column-btn';
                removeBtn.setAttribute('data-column', columnName);
                removeBtn.setAttribute('data-temp', isSaved ? 'false' : 'true');
                removeBtn.textContent = '✕';
                
                span.appendChild(textSpan);
                span.appendChild(removeBtn);
                columnsDisplay.appendChild(span);
            });
            
            const allColumnsArray = [...columnsToReset, ...tempColumns];
            hiddenInput.value = JSON.stringify(allColumnsArray);
        }

        addColumnBtn.addEventListener('click', function() {
            const newColumn = newColumnInput.value.trim();
            if (!newColumn) {
                showCustomModal('Error', 'Please enter a column name.', 'error');
                return;
            }
            
            const exists = columnsToReset.some(e => e.column === newColumn) || 
                          tempColumns.some(e => e.column === newColumn);
            if (exists) {
                showCustomModal('Error', 'Column "' + newColumn + '" already exists.', 'error');
                newColumnInput.value = '';
                newValueInput.value = '';
                return;
            }
            
            let value = newValueInput.value.trim();
            if (value === '') {
                value = null;
            } else if (!isNaN(value) && value !== '') {
                if (value.includes('.')) {
                    value = parseFloat(value);
                } else {
                    value = parseInt(value, 10);
                }
            }
            
            tempColumns.push({
                column: newColumn,
                value: value
            });
            renderColumns();
            newColumnInput.value = '';
            newValueInput.value = '';
            newColumnInput.focus();
        });

        newColumnInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addColumnBtn.click();
            }
        });
        newValueInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addColumnBtn.click();
            }
        });

        columnsDisplay.addEventListener('click', function(e) {
            const removeBtn = e.target.closest('.remove-column-btn');
            if (!removeBtn) return;
            
            const columnName = removeBtn.getAttribute('data-column');
            const isTemp = removeBtn.getAttribute('data-temp') === 'true';
            
            if (isTemp) {
                const index = tempColumns.findIndex(e => e.column === columnName);
                if (index !== -1) {
                    tempColumns.splice(index, 1);
                    renderColumns();
                }
            } else {
                showSettingsPasswordModal(
                    '🔐 Security Verification',
                    'Please enter your admin password to remove column: ' + columnName,
                    function() {
                        const index = columnsToReset.findIndex(e => e.column === columnName);
                        if (index !== -1) {
                            columnsToReset.splice(index, 1);
                            const tempIndex = tempColumns.findIndex(e => e.column === columnName);
                            if (tempIndex !== -1) {
                                tempColumns.splice(tempIndex, 1);
                            }
                            renderColumns();
                            showCustomModal('Success', 'Column "' + columnName + '" removed. Click "Save Columns to Reset" to save changes.', 'success');
                        }
                    }
                );
            }
        });

        renderColumns();

        // ============================================
        // SHOW CUSTOM CONFIRM FUNCTION
        // ============================================
        
        function showCustomConfirm(title, message, confirmText, cancelText, onConfirm) {
            const existingModal = document.getElementById('custom-modal-overlay');
            if (existingModal) {
                existingModal.remove();
            }

            const modalHtml = `
                <div class="modal-overlay" id="custom-modal-overlay" onclick="closeCustomModal(event)">
                    <div class="modal-container custom-modal" onclick="event.stopPropagation()" style="max-width: 450px;">
                        <div class="modal-header">
                            <span>⚠️ ${title}</span>
                            <span class="modal-close" onclick="closeCustomModal()">✕</span>
                        </div>
                        <div class="modal-body" style="text-align: center; padding: 30px 20px;">
                            <p style="font-size: 15px; line-height: 1.6; margin: 0;">${message}</p>
                        </div>
                        <div style="padding: 15px 20px 20px; display: flex; gap: 10px; justify-content: center; border-top: 1px solid var(--border-color);">
                            <button class="modal-cancel-btn" onclick="closeCustomModal()" style="min-width: 80px;">${cancelText}</button>
                            <button class="modal-confirm-btn" id="custom-confirm-btn" style="min-width: 80px; background: #e74c3c;">${confirmText}</button>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);

            if (onConfirm) {
                const confirmBtn = document.getElementById('custom-confirm-btn');
                confirmBtn.addEventListener('click', function() {
                    closeCustomModal();
                    onConfirm();
                });
            }
        }
        
        function closeCustomModal(event) {
            if (event && event.target && event.target.id !== 'custom-modal-overlay' && event.target.className !== 'modal-close' && event.target.className !== 'modal-confirm-btn' && event.target.className !== 'modal-cancel-btn') {
                return;
            }
            const modal = document.getElementById('custom-modal-overlay');
            if (modal) {
                modal.remove();
            }
        }
        
        function showCustomModal(title, message, type) {
            const existingModal = document.getElementById('custom-modal-overlay');
            if (existingModal) {
                existingModal.remove();
            }

            let icon = 'ℹ️';
            if (type === 'success') icon = '✅';
            else if (type === 'error') icon = '❌';
            else if (type === 'warning') icon = '⚠️';

            const modalHtml = `
                <div class="modal-overlay" id="custom-modal-overlay" onclick="closeCustomModal(event)">
                    <div class="modal-container custom-modal" onclick="event.stopPropagation()" style="max-width: 450px;">
                        <div class="modal-header">
                            <span>${icon} ${title}</span>
                            <span class="modal-close" onclick="closeCustomModal()">✕</span>
                        </div>
                        <div class="modal-body" style="text-align: center; padding: 30px 20px;">
                            <p style="font-size: 15px; line-height: 1.6; margin: 0;">${message}</p>
                        </div>
                        <div style="padding: 15px 20px 20px; display: flex; justify-content: center; border-top: 1px solid var(--border-color);">
                            <button class="modal-confirm-btn" onclick="closeCustomModal()" style="min-width: 100px;">OK</button>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }
    });
</script>