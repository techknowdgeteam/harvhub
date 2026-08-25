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
        
        <!-- Password required for saving -->
        <div class="password-required-section">
            <label for="columns_admin_password" class="password-label">🔒 Admin Password (Required to save changes)</label>
            <input type="password" id="columns_admin_password" name="admin_confirmation_password" placeholder="Enter your admin password" class="password-input" required>
            <small class="password-helper">Password is required to confirm changes to columns to reset.</small>
        </div>
        
        <button type="submit" name="save_columns_to_reset" value="1" class="btn-save-columns">
            💾 Save Columns to Reset
        </button>
    </div>

    <button type="submit">💾 Save All Settings</button>
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
                <form method="POST" action="serveraccount.php?view=settings" class="delete-broker-form" style="display:inline;">
                    <input type="hidden" name="delete_broker" value="1">
                    <input type="hidden" name="broker_value" value="<?= htmlspecialchars($broker) ?>">
                    <button type="submit" class="list-item-btn">Delete</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted);">No brokers configured.</p>
    <?php endif; ?>

    <h4>Add New Broker</h4>
    <form method="POST" action="serveraccount.php?view=settings" id="add-broker-form" class="add-new-form">
        <input type="hidden" name="add_broker" value="1">
        <input type="text" name="new_broker" placeholder="e.g., BrokerXYZ" required>
        <button type="submit">Add Broker</button>
    </form>
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
                <form method="POST" action="serveraccount.php?view=settings" class="delete-link-form" style="display:inline;">
                    <input type="hidden" name="delete_brokers_link" value="1">
                    <input type="hidden" name="link_value" value="<?= htmlspecialchars($link) ?>">
                    <button type="submit" class="list-item-btn">Delete</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted);">No broker links configured.</p>
    <?php endif; ?>
    
    <h4>Add New Link</h4>
    <form method="POST" action="serveraccount.php?view=settings" id="add-link-form" class="add-new-form">
        <input type="hidden" name="add_brokers_link" value="1">
        <input type="text" name="new_link" placeholder="https://broker.com/signup" required>
        <button type="submit">Add Link</button>
    </form>
</div>

<button type="button" id="toggle-credentials" class="toggle-btn">👤 Edit Admin Credentials</button>

<div id="credentials-section" class="credentials-section">
    <h2>Edit Admin Credentials</h2>
    <form method="POST" action="serveraccount.php?view=settings" id="credentials-form">
        <input type="hidden" name="update_credentials" value="1">
        
        <label for="new_login_id">New Login ID:</label>
        <input type="text" id="new_login_id" name="new_login_id" value="<?= htmlspecialchars($serverAccount['admin_login_id'] ?? '') ?>" required>

        <label for="new_password">New Password (leave blank to keep):</label>
        <input type="password" id="new_password" name="new_password" placeholder="********">
        
        <button type="submit">Update Credentials</button>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle credentials section
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

        // ===== Columns to Reset Management =====
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

        // Helper function to get display value
        function getDisplayValue(value) {
            if (value === null || value === undefined) return 'NULL';
            if (typeof value === 'string') return '"' + value + '"';
            return value;
        }

        // Function to render columns
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
            
            // Update hidden input with all columns (saved + temp)
            const allColumnsArray = [...columnsToReset, ...tempColumns];
            hiddenInput.value = JSON.stringify(allColumnsArray);
        }

        // Add column
        addColumnBtn.addEventListener('click', function() {
            const newColumn = newColumnInput.value.trim();
            if (!newColumn) {
                alert('Please enter a column name.');
                return;
            }
            
            // Check if column already exists in saved or temp
            const exists = columnsToReset.some(e => e.column === newColumn) || 
                          tempColumns.some(e => e.column === newColumn);
            if (exists) {
                alert('Column "' + newColumn + '" already exists.');
                newColumnInput.value = '';
                newValueInput.value = '';
                return;
            }
            
            // Get value and determine type
            let value = newValueInput.value.trim();
            if (value === '') {
                value = null; // NULL
            } else if (!isNaN(value) && value !== '') {
                // Numeric - convert to number
                if (value.includes('.')) {
                    value = parseFloat(value);
                } else {
                    value = parseInt(value, 10);
                }
            } else {
                // String
                value = value;
            }
            
            // Add to temp columns
            tempColumns.push({
                column: newColumn,
                value: value
            });
            renderColumns();
            newColumnInput.value = '';
            newValueInput.value = '';
            newColumnInput.focus();
        });

        // Allow Enter key to add
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

        // Remove column (with password check for saved columns)
        columnsDisplay.addEventListener('click', function(e) {
            const removeBtn = e.target.closest('.remove-column-btn');
            if (!removeBtn) return;
            
            const columnName = removeBtn.getAttribute('data-column');
            const isTemp = removeBtn.getAttribute('data-temp') === 'true';
            
            if (isTemp) {
                // Remove from temp columns immediately
                const index = tempColumns.findIndex(e => e.column === columnName);
                if (index !== -1) {
                    tempColumns.splice(index, 1);
                    renderColumns();
                }
            } else {
                // Saved column - require password
                showPasswordModal(function() {
                    const index = columnsToReset.findIndex(e => e.column === columnName);
                    if (index !== -1) {
                        columnsToReset.splice(index, 1);
                        // Also remove from temp if it exists there
                        const tempIndex = tempColumns.findIndex(e => e.column === columnName);
                        if (tempIndex !== -1) {
                            tempColumns.splice(tempIndex, 1);
                        }
                        renderColumns();
                        alert('Column "' + columnName + '" has been removed. Click "Save Columns to Reset" to save changes.');
                    }
                });
            }
        });

        // Password Modal Functions
        function showPasswordModal(callback) {
            let modal = document.getElementById('column-password-modal');
            
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'column-password-modal';
                modal.className = 'modal';
                
                modal.innerHTML = `
                    <div class="modal-content">
                        <h3>🔒 Security Check</h3>
                        <p class="modal-subtitle">Please enter your Admin Password to remove this column.</p>
                        <input type="password" id="column-modal-password" placeholder="Admin Password">
                        <div class="modal-buttons">
                            <button id="column-modal-cancel" class="btn-cancel-password">Cancel</button>
                            <button id="column-modal-confirm" class="btn-confirm-password">Confirm</button>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
            }
            
            modal.style.display = 'flex';
            modal.style.opacity = '1';
            modal.style.pointerEvents = 'all';
            
            const passwordInput = document.getElementById('column-modal-password');
            const confirmBtn = document.getElementById('column-modal-confirm');
            const cancelBtn = document.getElementById('column-modal-cancel');
            
            passwordInput.value = '';
            passwordInput.focus();
            
            // Remove old listeners and add new ones
            const newConfirmBtn = confirmBtn.cloneNode(true);
            const newCancelBtn = cancelBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
            
            // Get login_id from the page
            const loginId = document.querySelector('#new_login_id') ? document.querySelector('#new_login_id').value : '';
            
            newConfirmBtn.addEventListener('click', function() {
                const password = passwordInput.value;
                if (!password) {
                    alert('Please enter your password.');
                    return;
                }
                
                // Use the action-based endpoint
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
                    if (data.success) {
                        modal.style.display = 'none';
                        modal.style.opacity = '0';
                        modal.style.pointerEvents = 'none';
                        callback();
                    } else {
                        alert('❌ Invalid password. Please try again.');
                        passwordInput.value = '';
                        passwordInput.focus();
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                    alert('Error verifying password. Please try again.');
                });
            });
            
            newCancelBtn.addEventListener('click', function() {
                modal.style.display = 'none';
                modal.style.opacity = '0';
                modal.style.pointerEvents = 'none';
            });
            
            // Close on ESC key
            document.addEventListener('keydown', function escHandler(e) {
                if (e.key === 'Escape') {
                    modal.style.display = 'none';
                    modal.style.opacity = '0';
                    modal.style.pointerEvents = 'none';
                    document.removeEventListener('keydown', escHandler);
                }
            });
            
            // Close on click outside
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                    modal.style.opacity = '0';
                    modal.style.pointerEvents = 'none';
                }
            });
        }

        // Initial render
        renderColumns();
    });
</script>