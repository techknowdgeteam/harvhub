<?php
// settings.php - Server Settings Dashboard
// Included by serveraccount.php when view=settings
// Only accesses the server_account table (id = 1)
//
// TABS:
//   1. Payment Methods         -> btc_address, eth_address, eth_network, usdt_address, usdt_network
//   2. Recommended Brokers     -> brokers, brokers_link
//   3. Requirements            -> minimum_deposit, min_broker_balance, contract_duration,
//                                 server_share_percent, user_share_percent,
//                                 min_profit_for_split, expiry_threshold_days
//   4. Tier Limit              -> tier_limit (JSON)
//   5. Mailer Credentials      -> mailer_email, mailer_password
//   6. Credentials             -> admin_login_id, admin_password_hash
//
// Tier Limit sub-tabs:
//   - Tiers          : list of entries, each editable inline
//   - Create Entry   : create a new entry (tier name + values for existing fields)
//   - Entries Fields : manage field schema (add/rename/delete fields across all entries)
//                      LOCKED until at least one entry exists
//
// All AJAX actions are prefixed with "settings_" so they never clash with revenue.php
?>

<div class="settings-container" id="settings-container"
     data-server-share="<?= (int)($serverAccount['server_share_percent'] ?? 30) ?>"
     data-user-share="<?= (int)($serverAccount['user_share_percent'] ?? 70) ?>"
     data-min-profit="<?= (float)($serverAccount['min_profit_for_split'] ?? 30) ?>"
     data-min-deposit="<?= (float)($serverAccount['min_broker_balance'] ?? 30) ?>">

    <!-- ============================================ -->
    <!-- HEADER                                        -->
    <!-- ============================================ -->
    <div class="settings-header">
        <h2>Server Settings</h2>
        <p class="settings-sub">Only the <code>server_account</code> table is used on this page.</p>
    </div>

    <!-- ============================================ -->
    <!-- MAIN TAB BUTTONS                              -->
    <!-- ============================================ -->
    <div class="settings-tabs-wrapper">
        <div class="settings-tabs main-tabs" id="settings-main-tabs">
            <button class="tab-btn active" data-settings-tab="payment" onclick="Settings.switchTab('payment')">
                Payment Methods
            </button>
            <button class="tab-btn" data-settings-tab="brokers" onclick="Settings.switchTab('brokers')">
                Recommended Brokers
            </button>
            <button class="tab-btn" data-settings-tab="requirements" onclick="Settings.switchTab('requirements')">
                Requirements
            </button>
            <button class="tab-btn" data-settings-tab="tierlimit" onclick="Settings.switchTab('tierlimit')">
                Tier Limit
            </button>
            <button class="tab-btn" data-settings-tab="mailer" onclick="Settings.switchTab('mailer')">
                Mailer Credentials
            </button>
            <button class="tab-btn" data-settings-tab="credentials" onclick="Settings.switchTab('credentials')">
                Credentials
            </button>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB 1: PAYMENT METHODS                       -->
    <!-- ============================================ -->
    <div id="tab-payment" class="tab-content active">
        <div class="settings-card">
            <div class="settings-card-header">
                <h3>Payment Methods</h3>
                <p class="settings-card-sub">
                    These are the crypto addresses shown to users on the deposit page.
                    Stored in <code>btc_address</code>, <code>eth_address</code>, <code>eth_network</code>,
                    <code>usdt_address</code>, <code>usdt_network</code>.
                </p>
            </div>
            <div class="settings-card-body">
                <div class="payment-grid">

                    <div class="payment-tile">
                        <div class="payment-tile-icon">₿</div>
                        <div class="payment-tile-label">BTC Address</div>
                        <div class="payment-tile-value" id="settings-btc-address">
                            <?= htmlspecialchars($serverAccount['btc_address'] ?? 'Not set') ?>
                        </div>
                        <div class="payment-tile-sub">Column: btc_address</div>
                    </div>

                    <div class="payment-tile">
                        <div class="payment-tile-icon">Ξ</div>
                        <div class="payment-tile-label">ETH Address</div>
                        <div class="payment-tile-value" id="settings-eth-address">
                            <?= htmlspecialchars($serverAccount['eth_address'] ?? 'Not set') ?>
                        </div>
                        <div class="payment-tile-sub">
                            Network: <span id="settings-eth-network"><?= htmlspecialchars($serverAccount['eth_network'] ?? 'ERC20') ?></span>
                        </div>
                    </div>

                    <div class="payment-tile">
                        <div class="payment-tile-icon">₮</div>
                        <div class="payment-tile-label">USDT Address</div>
                        <div class="payment-tile-value" id="settings-usdt-address">
                            <?= htmlspecialchars($serverAccount['usdt_address'] ?? 'Not set') ?>
                        </div>
                        <div class="payment-tile-sub">
                            Network: <span id="settings-usdt-network"><?= htmlspecialchars($serverAccount['usdt_network'] ?? 'TRC20') ?></span>
                        </div>
                    </div>

                </div>
            </div>
            <div class="settings-card-footer">
                <button class="settings-btn settings-btn-primary" onclick="Settings.openPaymentEdit()">
                    Edit Payment Addresses
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB 2: RECOMMENDED BROKERS                   -->
    <!-- ============================================ -->
    <div id="tab-brokers" class="tab-content">
        <div class="settings-sub-tabs-wrapper">
            <div class="settings-tabs sub-tabs" id="settings-broker-sub-tabs">
                <button class="tab-btn sub-tab-btn active" data-settings-subtab="existing" onclick="Settings.switchBrokerSubTab('existing')">
                    Existing Brokers
                    <span class="tab-badge" id="broker-existing-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-settings-subtab="add" onclick="Settings.switchBrokerSubTab('add')">
                    Add New Broker &amp; Link
                </button>
            </div>
        </div>

        <div id="subtab-existing" class="tab-content active">
            <div class="settings-card">
                <div class="settings-card-header">
                    <h3>Existing Brokers</h3>
                    <p class="settings-card-sub">
                        Broker names and links are stored as comma-separated values in the
                        <code>brokers</code> and <code>brokers_link</code> columns.
                        Pairs are matched when the broker name (letters/numbers only) is found inside a link.
                        Deleting a broker removes both its name and its matched link.
                    </p>
                </div>
                <div class="settings-card-body">
                    <div id="broker-pairs-list" class="broker-pairs-list">
                        <div class="settings-empty">Loading brokers...</div>
                    </div>
                </div>
                <div class="settings-card-footer">
                    <button class="settings-btn settings-btn-primary" onclick="Settings.switchBrokerSubTab('add')">
                        + Add Broker
                    </button>
                </div>
            </div>
        </div>

        <div id="subtab-add" class="tab-content">
            <div class="settings-card">
                <div class="settings-card-header">
                    <h3>Add Broker &amp; Link</h3>
                    <p class="settings-card-sub">
                        Both fields are required. The broker name (letters/numbers only) must be present inside the URL,
                        otherwise saving is blocked with: <em>"URL does not match broker name."</em>
                        Duplicate broker names are rejected with: <em>"Broker already exists."</em>
                    </p>
                </div>
                <div class="settings-card-body">
                    <div class="settings-field">
                        <label for="settings-broker-name">Broker Name</label>
                        <input type="text" id="settings-broker-name" class="settings-input"
                               placeholder="e.g. Exness">
                        <div class="settings-field-hint">Saved into the <code>brokers</code> column (comma-separated).</div>
                    </div>
                    <div class="settings-field">
                        <label for="settings-broker-link">Broker Link</label>
                        <input type="text" id="settings-broker-link" class="settings-input"
                               placeholder="e.g. https://exness.com/open">
                        <div class="settings-field-hint">Saved into the <code>brokers_link</code> column (comma-separated).</div>
                    </div>
                    <div class="settings-field-error" id="settings-broker-error" style="display:none;"></div>
                </div>
                <div class="settings-card-footer">
                    <button class="settings-btn" onclick="Settings.switchBrokerSubTab('existing')">Cancel</button>
                    <button class="settings-btn settings-btn-primary" onclick="Settings.saveBroker()">Save Broker</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB 3: REQUIREMENTS                          -->
    <!-- ============================================ -->
    <div id="tab-requirements" class="tab-content">
        <div class="settings-card">
            <div class="settings-card-header">
                <h3>Requirements</h3>
                <p class="settings-card-sub">
                    <strong>Minimum Deposit</strong> and <strong>Minimum Broker Balance</strong> are linked — they share the
                    same value and update each other. Server Share % and User Share % must total 100%.
                </p>
            </div>
            <div class="settings-card-body">

                <div class="settings-field">
                    <label for="settings-min-deposit">Minimum Deposit ($)</label>
                    <input type="number" step="0.01" min="0" id="settings-min-deposit" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['minimum_deposit'] ?? '0.00') ?>"
                           oninput="Settings.onMinDepositChange()">
                    <div class="settings-field-hint">Column: <code>minimum_deposit</code> — linked to Minimum Broker Balance.</div>
                </div>

                <div class="settings-field">
                    <label for="settings-min-broker-balance">Minimum Broker Balance ($)</label>
                    <input type="number" step="0.01" min="0" id="settings-min-broker-balance" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['min_broker_balance'] ?? '30.00') ?>"
                           oninput="Settings.onMinBrokerBalanceChange()">
                    <div class="settings-field-hint">Column: <code>min_broker_balance</code> — linked to Minimum Deposit.</div>
                </div>

                <div class="settings-field">
                    <label for="settings-contract-duration">Contract Duration (days)</label>
                    <input type="number" min="1" id="settings-contract-duration" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['contract_duration'] ?? '') ?>">
                    <div class="settings-field-hint">Column: <code>contract_duration</code></div>
                </div>

                <div class="settings-field">
                    <label for="settings-server-share">Server Share Percent (%)</label>
                    <input type="number" min="0" max="100" id="settings-server-share" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['server_share_percent'] ?? '30') ?>"
                           oninput="Settings.onServerShareChange()">
                    <div class="settings-field-hint">Column: <code>server_share_percent</code></div>
                </div>

                <div class="settings-field">
                    <label for="settings-user-share">User Share Percent (%)</label>
                    <input type="number" min="0" max="100" id="settings-user-share" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['user_share_percent'] ?? '70') ?>"
                           oninput="Settings.onUserShareChange()">
                    <div class="settings-field-hint">Column: <code>user_share_percent</code> — Server + User share must total 100%.</div>
                </div>

                <div class="settings-field">
                    <label for="settings-min-profit">Minimum Profit for Split ($)</label>
                    <input type="number" step="0.01" min="0" id="settings-min-profit" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['min_profit_for_split'] ?? '30.00') ?>">
                    <div class="settings-field-hint">Column: <code>min_profit_for_split</code></div>
                </div>

                <div class="settings-field">
                    <label for="settings-expiry-threshold">Expiry Threshold Days</label>
                    <input type="number" min="1" id="settings-expiry-threshold" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['expiry_threshold_days'] ?? '5') ?>">
                    <div class="settings-field-hint">Column: <code>expiry_threshold_days</code></div>
                </div>

            </div>
            <div class="settings-card-footer">
                <button class="settings-btn settings-btn-primary" onclick="Settings.saveRequirements()">Save Requirements</button>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB 4: TIER LIMIT                            -->
    <!-- ============================================ -->
    <div id="tab-tierlimit" class="tab-content">
        <div class="settings-sub-tabs-wrapper">
            <div class="settings-tabs sub-tabs" id="settings-tier-sub-tabs">
                <button class="tab-btn sub-tab-btn active" data-settings-tiersubtab="tiers" onclick="Settings.switchTierSubTab('tiers')">
                    Tiers
                    <span class="tab-badge" id="tier-count-badge">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-settings-tiersubtab="create" onclick="Settings.switchTierSubTab('create')">
                    Create Entry
                </button>
                <button class="tab-btn sub-tab-btn" data-settings-tiersubtab="fields" id="tier-fields-tab-btn" onclick="Settings.switchTierSubTab('fields')">
                    Entries Fields
                    <span class="tab-badge" id="tier-field-count-badge">0</span>
                </button>
            </div>
        </div>

        <!-- SUB-TAB: TIERS (list of entries, inline editable) -->
        <div id="subtab-tiers" class="tab-content active">
            <div class="settings-card">
                <div class="settings-card-header">
                    <h3>Tier Entries</h3>
                    <p class="settings-card-sub">
                        Each entry is a JSON object keyed by its tier name (e.g. <code>Tier1</code>).
                        Edit any field's value inline and click <strong>Save</strong> on that entry.
                    </p>
                </div>
                <div class="settings-card-body">
                    <div id="tier-records-list" class="tier-records-list">
                        <div class="settings-empty">Loading entries...</div>
                    </div>
                </div>
                <div class="settings-card-footer">
                    <button class="settings-btn settings-btn-primary" onclick="Settings.switchTierSubTab('create')">
                        + Create Entry
                    </button>
                </div>
            </div>
        </div>

        <!-- SUB-TAB: CREATE ENTRY -->
        <div id="subtab-create" class="tab-content">
            <div class="settings-card">
                <div class="settings-card-header">
                    <h3>Create New Entry</h3>
                    <p class="settings-card-sub">
                        Give this entry a unique key (e.g. <code>Tier1</code>) and fill in values for each existing field.
                        If no fields exist yet, the first field you type below will define the schema.
                        <strong>Adding fields is only possible once at least one entry exists</strong> — you can create the
                        first entry here with or without initial fields.
                    </p>
                </div>
                <div class="settings-card-body">
                    <div class="settings-field">
                        <label for="settings-tier-new-key">Entry Key</label>
                        <input type="text" id="settings-tier-new-key" class="settings-input" placeholder="e.g. Tier1">
                        <div class="settings-field-hint">Must be unique. Cannot contain <code>{ } " , :</code>.</div>
                    </div>

                    <div id="settings-tier-create-fields-container"></div>

                    <div class="settings-field-error" id="settings-tier-create-error" style="display:none;"></div>

                    <div id="settings-tier-create-empty-hint" class="settings-empty" style="display:none;">
                        No fields defined yet. The first entry can be saved without fields, or you can define fields by
                        adding rows below.
                    </div>

                    <div class="settings-field" id="settings-tier-create-add-field-wrapper">
                        <label>Add Field Row (optional)</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" id="settings-tier-create-new-field-name" class="settings-input"
                                   placeholder="Field name (e.g. min-deposit)" style="flex:1;">
                            <input type="text" id="settings-tier-create-new-field-value" class="settings-input"
                                   placeholder="Value" style="flex:1;">
                            <button type="button" class="settings-btn" onclick="Settings.addCreateFieldRow()">Add Row</button>
                        </div>
                        <div class="settings-field-hint">
                            Adding rows here defines new fields. When the entry is saved, these become the schema for
                            all entries (existing ones get empty values for new fields).
                        </div>
                    </div>
                </div>
                <div class="settings-card-footer">
                    <button class="settings-btn" onclick="Settings.switchTierSubTab('tiers')">Cancel</button>
                    <button class="settings-btn settings-btn-primary" onclick="Settings.saveNewTierEntry()">Save Entry</button>
                </div>
            </div>
        </div>

        <!-- SUB-TAB: ENTRIES FIELDS -->
        <div id="subtab-fields" class="tab-content">
            <div class="settings-card">
                <div class="settings-card-header">
                    <h3>Entries Fields</h3>
                    <p class="settings-card-sub">
                        Manage the field schema. Adding a field inserts it into every entry with the default value you
                        provide. Renaming a field renames it in every entry (values preserved). Deleting a field removes
                        it from every entry. The per-entry values can be edited inline in the matrix below.
                        <br><strong>This tab is locked until at least one entry exists.</strong>
                    </p>
                </div>
                <div class="settings-card-body">
                    <div id="settings-tier-fields-locked" class="settings-empty" style="display:none;">
                        🔒 No entries exist yet. Create at least one entry in the <strong>Tiers</strong> or
                        <strong>Create Entry</strong> sub-tab before managing fields.
                    </div>

                    <div id="settings-tier-fields-unlocked" style="display:none;">
                        <!-- Global add field form -->
                        <div class="settings-field" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                            <div style="flex:1;min-width:180px;">
                                <label for="settings-tier-new-field-name">New Field Name</label>
                                <input type="text" id="settings-tier-new-field-name" class="settings-input"
                                       placeholder="e.g. min-deposit">
                            </div>
                            <div style="flex:1;min-width:140px;">
                                <label for="settings-tier-new-field-default">Default Value</label>
                                <input type="text" id="settings-tier-new-field-default" class="settings-input"
                                       placeholder="e.g. 0">
                            </div>
                            <button type="button" class="settings-btn settings-btn-primary"
                                    onclick="Settings.addGlobalField()">+ Add Field</button>
                        </div>
                        <div class="settings-field-error" id="settings-tier-fields-error" style="display:none;"></div>

                        <div class="tier-modal-fields-title" style="margin-top:20px;">Fields &amp; Per-Entry Values</div>
                        <div id="settings-tier-fields-matrix" class="tier-fields-matrix">
                            <!-- populated by JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB 5: MAILER CREDENTIALS                    -->
    <!-- ============================================ -->
    <div id="tab-mailer" class="tab-content">
        <div class="settings-card">
            <div class="settings-card-header">
                <h3>Mailer Credentials</h3>
                <p class="settings-card-sub">
                    Used by the system for outgoing emails.
                    Stored in <code>mailer_email</code> and <code>mailer_password</code>.
                </p>
            </div>
            <div class="settings-card-body">
                <div class="settings-field">
                    <label for="settings-mailer-email">Mailer Email</label>
                    <input type="email" id="settings-mailer-email" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['mailer_email'] ?? '') ?>"
                           placeholder="e.g. no-reply@example.com">
                    <div class="settings-field-hint">Column: <code>mailer_email</code></div>
                </div>
                <div class="settings-field">
                    <label for="settings-mailer-password">Mailer Password</label>
                    <input type="password" id="settings-mailer-password" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['mailer_password'] ?? '') ?>"
                           placeholder="Enter mailer password">
                    <div class="settings-field-hint">Column: <code>mailer_password</code></div>
                </div>
            </div>
            <div class="settings-card-footer">
                <button class="settings-btn settings-btn-primary" onclick="Settings.saveMailer()">Save Mailer Credentials</button>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB 6: CREDENTIALS                           -->
    <!-- ============================================ -->
    <div id="tab-credentials" class="tab-content">
        <div class="settings-card">
            <div class="settings-card-header">
                <h3>Admin Credentials</h3>
                <p class="settings-card-sub">
                    Change the admin login ID and/or password.
                    Stored in <code>admin_login_id</code> and <code>admin_password_hash</code>.
                    Leave the password fields blank if you only want to update the login ID.
                </p>
            </div>
            <div class="settings-card-body">
                <div class="settings-field">
                    <label for="settings-admin-login">Admin Login ID</label>
                    <input type="text" id="settings-admin-login" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>">
                    <div class="settings-field-hint">Column: <code>admin_login_id</code></div>
                </div>
                <div class="settings-field">
                    <label for="settings-admin-password">Admin Password (new)</label>
                    <input type="password" id="settings-admin-password" class="settings-input"
                           placeholder="Leave blank to keep current password">
                    <div class="settings-field-hint">Will be hashed into <code>admin_password_hash</code>.</div>
                </div>
                <div class="settings-field">
                    <label for="settings-admin-password-confirm">Confirm Admin Password</label>
                    <input type="password" id="settings-admin-password-confirm" class="settings-input"
                           placeholder="Re-enter new password">
                </div>
                <div class="settings-field-error" id="settings-credentials-error" style="display:none;"></div>
            </div>
            <div class="settings-card-footer">
                <button class="settings-btn settings-btn-primary" onclick="Settings.saveCredentials()">Save Credentials</button>
            </div>
        </div>
    </div>

</div>

<!-- ============================================ -->
<!-- SETTINGS PASSWORD MODAL                       -->
<!-- ============================================ -->
<div id="settings-password-modal" class="settings-modal-overlay" style="display:none;">
    <div class="settings-modal-container settings-modal-small">
        <div class="settings-modal-header">
            <span id="settings-password-modal-title">Security Check</span>
            <span class="settings-modal-close" onclick="Settings.closePasswordModal()">x</span>
        </div>
        <div class="settings-modal-body">
            <p id="settings-password-modal-message">Please enter your admin password to continue.</p>
            <input type="password" id="settings-password-modal-input" class="settings-input"
                   placeholder="Enter password">
            <div id="settings-password-modal-error" class="settings-field-error" style="display:none;"></div>
            <div class="settings-modal-buttons">
                <button class="settings-btn" onclick="Settings.closePasswordModal()">Cancel</button>
                <button class="settings-btn settings-btn-primary" id="settings-password-modal-confirm-btn"
                        onclick="Settings.confirmPasswordModal()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- SETTINGS CONFIRM MODAL                        -->
<!-- ============================================ -->
<div id="settings-confirm-modal" class="settings-modal-overlay" style="display:none;">
    <div class="settings-modal-container settings-modal-small">
        <div class="settings-modal-header">
            <span id="settings-confirm-modal-title">Confirm Action</span>
            <span class="settings-modal-close" onclick="Settings.closeConfirmModal()">x</span>
        </div>
        <div class="settings-modal-body">
            <p id="settings-confirm-modal-message">Are you sure?</p>
            <div class="settings-modal-buttons">
                <button class="settings-btn" onclick="Settings.closeConfirmModal()">Cancel</button>
                <button class="settings-btn settings-btn-primary" id="settings-confirm-modal-confirm-btn"
                        onclick="Settings.confirmModalAction()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- SETTINGS NOTIFICATION MODAL                   -->
<!-- ============================================ -->
<div id="settings-notification-modal" class="settings-modal-overlay" style="display:none;">
    <div class="settings-modal-container settings-modal-small">
        <div class="settings-modal-header">
            <span id="settings-notification-modal-title">Notification</span>
            <span class="settings-modal-close" onclick="Settings.closeNotificationModal()">x</span>
        </div>
        <div class="settings-modal-body">
            <p id="settings-notification-modal-message"></p>
            <div class="settings-modal-buttons">
                <button class="settings-btn settings-btn-primary" onclick="Settings.closeNotificationModal()">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- EDIT PAYMENT ADDRESSES MODAL                  -->
<!-- ============================================ -->
<div id="settings-payment-modal" class="settings-modal-overlay" style="display:none;">
    <div class="settings-modal-container settings-modal-medium">
        <div class="settings-modal-header">
            <span>Edit Payment Addresses</span>
            <span class="settings-modal-close" onclick="Settings.closePaymentEdit()">x</span>
        </div>
        <div class="settings-modal-body">
            <div class="settings-field">
                <label for="settings-modal-btc">BTC Address</label>
                <input type="text" id="settings-modal-btc" class="settings-input"
                       value="<?= htmlspecialchars($serverAccount['btc_address'] ?? '') ?>">
            </div>
            <div class="settings-field">
                <label for="settings-modal-eth">ETH Address</label>
                <input type="text" id="settings-modal-eth" class="settings-input"
                       value="<?= htmlspecialchars($serverAccount['eth_address'] ?? '') ?>">
            </div>
            <div class="settings-field">
                <label for="settings-modal-eth-network">ETH Network</label>
                <select id="settings-modal-eth-network" class="settings-input">
                    <?php
                    $ethNetworks = ['ERC20', 'BEP20', 'Polygon', 'Arbitrum', 'Optimism'];
                    $currentEth = $serverAccount['eth_network'] ?? 'ERC20';
                    foreach ($ethNetworks as $net) {
                        $sel = ($currentEth === $net) ? 'selected' : '';
                        echo "<option value=\"$net\" $sel>$net</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="settings-field">
                <label for="settings-modal-usdt">USDT Address</label>
                <input type="text" id="settings-modal-usdt" class="settings-input"
                       value="<?= htmlspecialchars($serverAccount['usdt_address'] ?? '') ?>">
            </div>
            <div class="settings-field">
                <label for="settings-modal-usdt-network">USDT Network</label>
                <select id="settings-modal-usdt-network" class="settings-input">
                    <?php
                    $usdtNetworks = ['TRC20', 'ERC20', 'BEP20', 'Polygon'];
                    $currentUsdt = $serverAccount['usdt_network'] ?? 'TRC20';
                    foreach ($usdtNetworks as $net) {
                        $sel = ($currentUsdt === $net) ? 'selected' : '';
                        echo "<option value=\"$net\" $sel>$net</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
        <div class="settings-modal-buttons settings-modal-buttons-padded">
            <button class="settings-btn" onclick="Settings.closePaymentEdit()">Cancel</button>
            <button class="settings-btn settings-btn-primary" onclick="Settings.savePaymentAddresses()">Save</button>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- TIER FIELD EDIT MODAL                         -->
<!-- ============================================ -->
<div id="settings-tier-field-modal" class="settings-modal-overlay" style="display:none;">
    <div class="settings-modal-container settings-modal-medium">
        <div class="settings-modal-header">
            <span id="settings-tier-field-modal-title">Rename Field</span>
            <span class="settings-modal-close" onclick="Settings.closeTierFieldModal()">x</span>
        </div>
        <div class="settings-modal-body">
            <div class="settings-field">
                <label for="settings-tier-field-name">New Field Name</label>
                <input type="text" id="settings-tier-field-name" class="settings-input" placeholder="e.g. min-deposit">
            </div>
            <div class="settings-field-hint">
                Renaming updates this field's key in every entry. Values are preserved.
            </div>
            <div class="settings-field-error" id="settings-tier-field-modal-error" style="display:none;"></div>
        </div>
        <div class="settings-modal-buttons settings-modal-buttons-padded">
            <button class="settings-btn" onclick="Settings.closeTierFieldModal()">Cancel</button>
            <button class="settings-btn settings-btn-primary" onclick="Settings.saveTierFieldFromModal()">Rename Field</button>
        </div>
    </div>
</div>

<input type="hidden" id="settings-login-id-hidden"
       value="<?php echo htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin'); ?>">

<script>
const Settings = {
    serverSharePercent: 30,
    userSharePercent: 70,
    minProfitForSplit: 30,
    minBrokerBalance: 30,

    currentTab: 'payment',
    currentBrokerSubTab: 'existing',
    currentTierSubTab: 'tiers',
    brokers: [],
    links: [],

    // Tier data
    tiers: {},            // { Tier1: { "min-deposit": "100", ... }, ... }
    tierFields: [],       // ["min-deposit", "max-revenue", ...] (union of all keys)
    editingFieldName: null,

    // Draft state for the "Create Entry" sub-tab
    createEntryFields: [], // [ { name: "min-deposit", value: "100" }, ... ]

    _passwordCallback: null,
    _confirmCallback: null,

    init: function() {
        const container = document.getElementById('settings-container');
        if (container) {
            this.serverSharePercent = parseInt(container.dataset.serverShare) || 30;
            this.userSharePercent   = parseInt(container.dataset.userShare) || 70;
            this.minProfitForSplit  = parseFloat(container.dataset.minProfit) || 30;
            this.minBrokerBalance   = parseFloat(container.dataset.minDeposit) || 30;
        }
        this.loadBrokers();
        this.loadTiers();
        this.bindEvents();
    },

    bindEvents: function() {
        const self = this;
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                self.closePasswordModal();
                self.closeConfirmModal();
                self.closeNotificationModal();
                self.closePaymentEdit();
                self.closeTierFieldModal();
            }
            if (e.key === 'Enter') {
                const pw = document.getElementById('settings-password-modal');
                if (pw && pw.style.display === 'flex') self.confirmPasswordModal();
                const cf = document.getElementById('settings-confirm-modal');
                if (cf && cf.style.display === 'flex') self.confirmModalAction();
            }
        });
    },

    // ============================================
    // TAB SWITCHING
    // ============================================
    switchTab: function(tab) {
        this.currentTab = tab;

        document.querySelectorAll('#settings-main-tabs .tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.settingsTab === tab);
        });

        document.querySelectorAll('#settings-container > .tab-content').forEach(el => {
            el.classList.toggle('active', el.id === 'tab-' + tab);
        });

        if (tab === 'tierlimit') {
            // Re-render in case data changed
            this.renderTierRecords();
            this.renderTierCreateForm();
            this.renderTierFieldsTab();
        }
    },

    switchBrokerSubTab: function(subTab) {
        this.currentBrokerSubTab = subTab;
        document.querySelectorAll('#settings-broker-sub-tabs .tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.settingsSubtab === subTab);
        });
        document.querySelectorAll('#tab-brokers > .tab-content').forEach(el => {
            el.classList.toggle('active', el.id === 'subtab-' + subTab);
        });
    },

    switchTierSubTab: function(subTab) {
        // Lock the "fields" sub-tab if no entries exist
        if (subTab === 'fields' && Object.keys(this.tiers).length === 0) {
            this.showNotification('You must create at least one entry before managing fields.', 'Locked', true);
            return;
        }
        this.currentTierSubTab = subTab;
        document.querySelectorAll('#settings-tier-sub-tabs .tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.settingsTiersubtab === subTab);
        });
        document.querySelectorAll('#tab-tierlimit > .tab-content').forEach(el => {
            el.classList.toggle('active', el.id === 'subtab-' + subTab);
        });

        if (subTab === 'create') this.renderTierCreateForm();
        if (subTab === 'fields') this.renderTierFieldsTab();
        if (subTab === 'tiers') this.renderTierRecords();
    },

    // ============================================
    // MODALS
    // ============================================
    showPasswordModal: function(title, message, callback) {
        document.getElementById('settings-password-modal-title').textContent = title || 'Security Check';
        document.getElementById('settings-password-modal-message').textContent = message || 'Please enter your admin password to continue.';
        document.getElementById('settings-password-modal-input').value = '';
        document.getElementById('settings-password-modal-error').style.display = 'none';
        this._passwordCallback = callback;
        document.getElementById('settings-password-modal').style.display = 'flex';
        setTimeout(() => document.getElementById('settings-password-modal-input').focus(), 100);
    },

    closePasswordModal: function() {
        document.getElementById('settings-password-modal').style.display = 'none';
        this._passwordCallback = null;
        document.getElementById('settings-password-modal-error').style.display = 'none';
    },

    confirmPasswordModal: function() {
        const password = document.getElementById('settings-password-modal-input').value;
        const errorEl = document.getElementById('settings-password-modal-error');
        if (!password) {
            errorEl.textContent = 'Please enter your password.';
            errorEl.style.display = 'block';
            return;
        }
        const cb = this._passwordCallback;
        this.closePasswordModal();
        if (typeof cb === 'function') cb(password);
    },

    showConfirmModal: function(title, message, callback) {
        document.getElementById('settings-confirm-modal-title').textContent = title || 'Confirm Action';
        document.getElementById('settings-confirm-modal-message').textContent = message || 'Are you sure?';
        this._confirmCallback = callback;
        document.getElementById('settings-confirm-modal').style.display = 'flex';
    },

    closeConfirmModal: function() {
        document.getElementById('settings-confirm-modal').style.display = 'none';
        this._confirmCallback = null;
    },

    confirmModalAction: function() {
        const cb = this._confirmCallback;
        this.closeConfirmModal();
        if (typeof cb === 'function') cb();
    },

    showNotification: function(message, title, isError) {
        document.getElementById('settings-notification-modal-title').textContent = title || (isError ? 'Error' : 'Success');
        document.getElementById('settings-notification-modal-message').textContent = message || '';
        document.getElementById('settings-notification-modal').style.display = 'flex';
    },

    closeNotificationModal: function() {
        document.getElementById('settings-notification-modal').style.display = 'none';
    },

    // ============================================
    // LINKED FIELDS
    // ============================================
    onMinDepositChange: function() {
        document.getElementById('settings-min-broker-balance').value =
            document.getElementById('settings-min-deposit').value;
    },
    onMinBrokerBalanceChange: function() {
        document.getElementById('settings-min-deposit').value =
            document.getElementById('settings-min-broker-balance').value;
    },
    onServerShareChange: function() {
        const s = parseInt(document.getElementById('settings-server-share').value) || 0;
        document.getElementById('settings-user-share').value = Math.max(0, 100 - s);
    },
    onUserShareChange: function() {
        const u = parseInt(document.getElementById('settings-user-share').value) || 0;
        document.getElementById('settings-server-share').value = Math.max(0, 100 - u);
    },

    // ============================================
    // AJAX
    // ============================================
    postAction: function(action, params) {
        const body = new URLSearchParams();
        body.append('action', action);
        if (params) for (const k in params) body.append(k, params[k]);
        return fetch(window.location.pathname, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        }).then(r => r.json());
    },

    // ============================================
    // PAYMENT METHODS
    // ============================================
    openPaymentEdit: function() {
        document.getElementById('settings-payment-modal').style.display = 'flex';
    },
    closePaymentEdit: function() {
        document.getElementById('settings-payment-modal').style.display = 'none';
    },
    savePaymentAddresses: function() {
        const self = this;
        this.showPasswordModal(
            'Edit Payment Addresses',
            'Enter admin password to save payment addresses.',
            function(password) {
                const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
                const payload = {
                    btc_address:  document.getElementById('settings-modal-btc').value.trim(),
                    eth_address:  document.getElementById('settings-modal-eth').value.trim(),
                    eth_network:  document.getElementById('settings-modal-eth-network').value,
                    usdt_address: document.getElementById('settings-modal-usdt').value.trim(),
                    usdt_network: document.getElementById('settings-modal-usdt-network').value,
                    admin_password: password,
                    login_id: loginId
                };
                self.postAction('settings_update_payment', payload).then(data => {
                    if (data.success) {
                        document.getElementById('settings-btc-address').textContent  = payload.btc_address  || 'Not set';
                        document.getElementById('settings-eth-address').textContent  = payload.eth_address  || 'Not set';
                        document.getElementById('settings-eth-network').textContent  = payload.eth_network;
                        document.getElementById('settings-usdt-address').textContent = payload.usdt_address || 'Not set';
                        document.getElementById('settings-usdt-network').textContent = payload.usdt_network;
                        self.closePaymentEdit();
                        self.showNotification('Payment addresses updated successfully!', 'Success', false);
                    } else if (data.error === 'Invalid password') {
                        self.showNotification('Password verification failed.', 'Error', true);
                    } else {
                        self.showNotification('Error: ' + (data.error || 'Unknown error'), 'Error', true);
                    }
                }).catch(err => self.showNotification('Error: ' + err.message, 'Error', true));
            }
        );
    },

    // ============================================
    // BROKERS
    // ============================================
    parseList: function(raw) {
        if (!raw) return [];
        return String(raw).split(',').map(s => s.trim()).filter(s => s.length > 0);
    },
    loadBrokers: function() {
        const self = this;
        this.postAction('settings_get_brokers', {}).then(data => {
            if (data.success) {
                self.brokers = self.parseList(data.brokers);
                self.links   = self.parseList(data.brokers_link);
            }
            self.renderBrokerPairs();
        }).catch(() => self.renderBrokerPairs());
    },
    matchBrokerPairs: function() {
        const usedLinks = new Set();
        const pairs = [];
        const norm = s => String(s || '').toLowerCase().replace(/[^a-z0-9]/g, '');
        this.brokers.forEach(broker => {
            const bNorm = norm(broker);
            let matchIndex = -1;
            for (let i = 0; i < this.links.length; i++) {
                if (usedLinks.has(i)) continue;
                if (bNorm && norm(this.links[i]).includes(bNorm)) { matchIndex = i; break; }
            }
            if (matchIndex >= 0) {
                usedLinks.add(matchIndex);
                pairs.push({ broker, link: this.links[matchIndex], linkIndex: matchIndex });
            } else {
                pairs.push({ broker, link: '', linkIndex: -1 });
            }
        });
        this.links.forEach((link, i) => {
            if (!usedLinks.has(i)) pairs.push({ broker: '', link, linkIndex: i });
        });
        return pairs;
    },
    renderBrokerPairs: function() {
        const container = document.getElementById('broker-pairs-list');
        const pairs = this.matchBrokerPairs();
        document.getElementById('broker-existing-count').textContent = this.brokers.length;
        if (pairs.length === 0) {
            container.innerHTML = '<div class="settings-empty">No brokers added yet.</div>';
            return;
        }
        let html = '';
        pairs.forEach((p, idx) => {
            const brokerEsc = this.escapeHtml(p.broker || '');
            const linkEsc = this.escapeHtml(p.link || '');
            html += `
                <div class="broker-pair-row">
                    <div class="broker-pair-index">${idx + 1}</div>
                    <div class="broker-pair-body">
                        <div class="broker-pair-name">${p.broker ? brokerEsc : '— (unmatched link)'}</div>
                        <div class="broker-pair-link">
                            ${p.link
                                ? `<a href="${linkEsc}" target="_blank" rel="noopener">${linkEsc}</a>`
                                : '<span class="broker-pair-nolink">No matching link</span>'}
                        </div>
                    </div>
                    <div class="broker-pair-actions">
                        <button class="broker-delete-btn" title="Delete broker"
                                onclick="Settings.deleteBroker(${idx})">Delete</button>
                    </div>
                </div>`;
        });
        container.innerHTML = html;
    },
    deleteBroker: function(pairIndex) {
        const pairs = this.matchBrokerPairs();
        const pair = pairs[pairIndex];
        if (!pair) return;
        const self = this;
        const brokerName = pair.broker || '(unmatched link)';
        const linkName = pair.link || '(no link)';

        this.showConfirmModal(
            'Delete Broker',
            'Are you sure you want to delete "' + brokerName + '" and its link "' + linkName + '"? This cannot be undone.',
            function() {
                self.showPasswordModal(
                    'Delete Broker',
                    'Enter admin password to delete this broker and its link.',
                    function(password) {
                        const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
                        self.postAction('settings_delete_broker', {
                            broker: pair.broker || '',
                            broker_link: pair.link || '',
                            admin_password: password,
                            login_id: loginId
                        }).then(data => {
                            if (data.success) {
                                self.brokers = self.parseList(data.brokers);
                                self.links   = self.parseList(data.brokers_link);
                                self.renderBrokerPairs();
                                self.showNotification('Broker deleted successfully!', 'Success', false);
                            } else if (data.error === 'Invalid password') {
                                self.showNotification('Password verification failed.', 'Error', true);
                            } else {
                                self.showNotification('Error: ' + (data.error || 'Unknown error'), 'Error', true);
                            }
                        }).catch(err => self.showNotification('Error: ' + err.message, 'Error', true));
                    }
                );
            }
        );
    },
    saveBroker: function() {
        const name = document.getElementById('settings-broker-name').value.trim();
        const link = document.getElementById('settings-broker-link').value.trim();
        const errorEl = document.getElementById('settings-broker-error');
        errorEl.style.display = 'none';

        if (!name || !link) {
            errorEl.textContent = 'Both Broker Name and Broker Link are required.';
            errorEl.style.display = 'block';
            return;
        }

        const existsLocally = this.brokers.some(b => b.toLowerCase() === name.toLowerCase());
        if (existsLocally) {
            errorEl.textContent = 'Broker already exists.';
            errorEl.style.display = 'block';
            return;
        }

        const normalize = s => String(s || '').toLowerCase().replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/[^a-z0-9]/g, '');
        const nameNorm = normalize(name);
        const linkNorm = normalize(link);
        if (!nameNorm) {
            errorEl.textContent = 'Broker Name must contain letters or numbers.';
            errorEl.style.display = 'block';
            return;
        }
        if (!linkNorm.includes(nameNorm)) {
            errorEl.textContent = 'URL does not match broker name. "' + name + '" was not found in the URL.';
            errorEl.style.display = 'block';
            return;
        }
        const self = this;
        this.showPasswordModal(
            'Save Broker',
            'Enter admin password to save this broker and link.',
            function(password) {
                const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
                self.postAction('settings_save_broker', {
                    broker: name, broker_link: link,
                    admin_password: password, login_id: loginId
                }).then(data => {
                    if (data.success) {
                        self.brokers = self.parseList(data.brokers);
                        self.links   = self.parseList(data.brokers_link);
                        self.renderBrokerPairs();
                        document.getElementById('settings-broker-name').value = '';
                        document.getElementById('settings-broker-link').value = '';
                        self.switchBrokerSubTab('existing');
                        self.showNotification('Broker saved successfully!', 'Success', false);
                    } else if (data.error === 'Invalid password') {
                        self.showNotification('Password verification failed.', 'Error', true);
                    } else {
                        errorEl.textContent = data.error || 'Unknown error';
                        errorEl.style.display = 'block';
                    }
                }).catch(err => {
                    errorEl.textContent = 'Error: ' + err.message;
                    errorEl.style.display = 'block';
                });
            }
        );
    },

    // ============================================
    // REQUIREMENTS
    // ============================================
    saveRequirements: function() {
        const self = this;
        const payload = {
            minimum_deposit:      document.getElementById('settings-min-deposit').value,
            min_broker_balance:   document.getElementById('settings-min-broker-balance').value,
            contract_duration:    document.getElementById('settings-contract-duration').value,
            server_share_percent: document.getElementById('settings-server-share').value,
            user_share_percent:   document.getElementById('settings-user-share').value,
            min_profit_for_split: document.getElementById('settings-min-profit').value,
            expiry_threshold_days: document.getElementById('settings-expiry-threshold').value
        };
        const s = parseInt(payload.server_share_percent) || 0;
        const u = parseInt(payload.user_share_percent) || 0;
        if (s + u !== 100) {
            this.showNotification('Server Share + User Share must equal 100%. Currently ' + (s + u) + '%.', 'Error', true);
            return;
        }
        this.showPasswordModal(
            'Save Requirements',
            'Enter admin password to save requirements.',
            function(password) {
                const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
                payload.admin_password = password;
                payload.login_id = loginId;
                self.postAction('settings_update_requirements', payload).then(data => {
                    if (data.success) {
                        self.showNotification('Requirements saved successfully!', 'Success', false);
                    } else if (data.error === 'Invalid password') {
                        self.showNotification('Password verification failed.', 'Error', true);
                    } else {
                        self.showNotification('Error: ' + (data.error || 'Unknown error'), 'Error', true);
                    }
                }).catch(err => self.showNotification('Error: ' + err.message, 'Error', true));
            }
        );
    },

    // ============================================
    // TIER LIMIT
    // ============================================
    loadTiers: function() {
        const self = this;
        this.postAction('settings_get_tier_limit', {}).then(data => {
            if (data.success && data.tiers) {
                self.tiers = data.tiers;
            } else {
                self.tiers = {};
            }
            self.recomputeTierFields();
            self.renderTierRecords();
            self.renderTierCreateForm();
            self.renderTierFieldsTab();
        }).catch(() => {
            self.tiers = {};
            self.recomputeTierFields();
            self.renderTierRecords();
            self.renderTierCreateForm();
            self.renderTierFieldsTab();
        });
    },

    recomputeTierFields: function() {
        const fieldSet = new Set();
        Object.values(this.tiers).forEach(tierObj => {
            if (tierObj && typeof tierObj === 'object') {
                Object.keys(tierObj).forEach(k => fieldSet.add(k));
            }
        });
        this.tierFields = Array.from(fieldSet).sort();

        const badge = document.getElementById('tier-field-count-badge');
        if (badge) badge.textContent = this.tierFields.length;

        const countBadge = document.getElementById('tier-count-badge');
        if (countBadge) countBadge.textContent = Object.keys(this.tiers).length;
    },

    // ---------- Tiers sub-tab (list of entries, inline editable) ----------
    renderTierRecords: function() {
        const container = document.getElementById('tier-records-list');
        if (!container) return;
        const keys = Object.keys(this.tiers);
        document.getElementById('tier-count-badge').textContent = keys.length;

        if (keys.length === 0) {
            container.innerHTML = '<div class="settings-empty">No entries created yet. Use <strong>Create Entry</strong> to add one.</div>';
            return;
        }

        let html = '';
        keys.forEach(tierKey => {
            const tierObj = this.tiers[tierKey] || {};
            const fields = this.tierFields.length > 0 ? this.tierFields : Object.keys(tierObj);

            let fieldRowsHtml = '';
            if (fields.length === 0) {
                fieldRowsHtml = '<div class="tier-empty-msg">No fields defined for this entry.</div>';
            } else {
                fields.forEach(field => {
                    const value = tierObj[field] !== undefined ? tierObj[field] : '';
                    fieldRowsHtml += `
                        <div class="tier-inline-row">
                            <label class="tier-inline-label">${this.escapeHtml(field)}</label>
                            <input type="text" class="settings-input tier-inline-input"
                                   data-tier-key="${this.escapeHtml(tierKey)}"
                                   data-field-name="${this.escapeHtml(field)}"
                                   value="${this.escapeHtml(String(value))}">
                        </div>`;
                });
            }

            html += `
                <div class="tier-record-card" data-tier-key="${this.escapeHtml(tierKey)}">
                    <div class="tier-record-header">
                        <div class="tier-record-title">
                            <span class="tier-record-key">${this.escapeHtml(tierKey)}</span>
                        </div>
                        <div class="tier-record-actions">
                            <button class="tier-save-btn" onclick="Settings.saveTierInline('${this.escapeHtml(tierKey)}')">Save</button>
                            <button class="tier-delete-btn" onclick="Settings.deleteTier('${this.escapeHtml(tierKey)}')">Delete</button>
                        </div>
                    </div>
                    <div class="tier-record-body">
                        ${fieldRowsHtml}
                    </div>
                </div>`;
        });
        container.innerHTML = html;
    },

    saveTierInline: function(tierKey) {
        const self = this;
        const card = document.querySelector(`.tier-record-card[data-tier-key="${CSS.escape(tierKey)}"]`);
        if (!card) return;

        // Collect values
        const tierObj = {};
        card.querySelectorAll('.tier-inline-input').forEach(inp => {
            tierObj[inp.dataset.fieldName] = inp.value;
        });

        // Ensure every known field exists in this entry (fill missing with current tier value or empty)
        this.tierFields.forEach(f => {
            if (!(f in tierObj)) {
                tierObj[f] = (this.tiers[tierKey] && this.tiers[tierKey][f] !== undefined) ? this.tiers[tierKey][f] : '';
            }
        });

        const updatedTiers = Object.assign({}, this.tiers);
        updatedTiers[tierKey] = tierObj;

        this.showPasswordModal(
            'Save Entry',
            'Enter admin password to save changes to "' + tierKey + '".',
            function(password) {
                const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
                self.postAction('settings_save_tier_limit', {
                    tiers: JSON.stringify(updatedTiers),
                    admin_password: password,
                    login_id: loginId
                }).then(data => {
                    if (data.success) {
                        self.tiers = updatedTiers;
                        self.recomputeTierFields();
                        self.renderTierRecords();
                        self.renderTierFieldsTab();
                        self.showNotification('Entry "' + tierKey + '" saved!', 'Success', false);
                    } else if (data.error === 'Invalid password') {
                        self.showNotification('Password verification failed.', 'Error', true);
                    } else {
                        self.showNotification('Error: ' + (data.error || 'Unknown error'), 'Error', true);
                    }
                }).catch(err => self.showNotification('Error: ' + err.message, 'Error', true));
            }
        );
    },

    deleteTier: function(tierKey) {
        const self = this;
        this.showConfirmModal(
            'Delete Entry',
            'Delete entry "' + tierKey + '"? This removes the entry and its values. Fields are kept if other entries still use them.',
            function() {
                self.showPasswordModal(
                    'Delete Entry',
                    'Enter admin password to delete this entry.',
                    function(password) {
                        const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
                        const updatedTiers = Object.assign({}, self.tiers);
                        delete updatedTiers[tierKey];

                        self.postAction('settings_save_tier_limit', {
                            tiers: JSON.stringify(updatedTiers),
                            admin_password: password,
                            login_id: loginId
                        }).then(data => {
                            if (data.success) {
                                self.tiers = updatedTiers;
                                self.recomputeTierFields();
                                self.renderTierRecords();
                                self.renderTierCreateForm();
                                self.renderTierFieldsTab();
                                self.showNotification('Entry deleted!', 'Success', false);
                            } else if (data.error === 'Invalid password') {
                                self.showNotification('Password verification failed.', 'Error', true);
                            } else {
                                self.showNotification('Error: ' + (data.error || 'Unknown error'), 'Error', true);
                            }
                        }).catch(err => self.showNotification('Error: ' + err.message, 'Error', true));
                    }
                );
            }
        );
    },

    // ---------- Create Entry sub-tab ----------
    renderTierCreateForm: function() {
        // Reset draft fields to current schema, with empty values
        this.createEntryFields = this.tierFields.map(f => ({ name: f, value: '' }));

        const container = document.getElementById('settings-tier-create-fields-container');
        const emptyHint = document.getElementById('settings-tier-create-empty-hint');
        if (!container) return;

        if (this.createEntryFields.length === 0) {
            container.innerHTML = '';
            emptyHint.style.display = 'block';
        } else {
            emptyHint.style.display = 'none';
            let html = '<div class="tier-modal-fields-title">Field Values</div>';
            this.createEntryFields.forEach((f, idx) => {
                html += `
                    <div class="settings-field">
                        <label>${this.escapeHtml(f.name)}</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="text" class="settings-input tier-create-field-input"
                                   data-index="${idx}"
                                   data-field-name="${this.escapeHtml(f.name)}"
                                   value="${this.escapeHtml(f.value)}"
                                   placeholder="Enter value for ${this.escapeHtml(f.name)}">
                            <button type="button" class="tier-field-delete-btn"
                                    onclick="Settings.removeCreateFieldRow(${idx})"
                                    title="Remove this row (field will not be created on this entry)">Remove</button>
                        </div>
                    </div>`;
            });
            container.innerHTML = html;
        }

        // Reset the "Add Field Row" inputs
        const nameInput = document.getElementById('settings-tier-create-new-field-name');
        const valInput  = document.getElementById('settings-tier-create-new-field-value');
        if (nameInput) nameInput.value = '';
        if (valInput) valInput.value = '';
    },

    addCreateFieldRow: function() {
        const nameInput = document.getElementById('settings-tier-create-new-field-name');
        const valInput  = document.getElementById('settings-tier-create-new-field-value');
        const name = nameInput.value.trim();
        const value = valInput.value;

        const errorEl = document.getElementById('settings-tier-create-error');
        errorEl.style.display = 'none';

        if (!name) {
            errorEl.textContent = 'Field name cannot be empty.';
            errorEl.style.display = 'block';
            return;
        }
        if (/[{}",:]/.test(name)) {
            errorEl.textContent = 'Field name cannot contain { } " , : characters.';
            errorEl.style.display = 'block';
            return;
        }
        if (this.createEntryFields.some(f => f.name === name)) {
            // Just update the value of the existing row
            const row = this.createEntryFields.find(f => f.name === name);
            row.value = value;
        } else {
            this.createEntryFields.push({ name, value });
        }

        nameInput.value = '';
        valInput.value = '';
        this.renderTierCreateForm();
    },

    removeCreateFieldRow: function(index) {
        this.createEntryFields.splice(index, 1);
        this.renderTierCreateForm();
    },

    saveNewTierEntry: function() {
        const self = this;
        const errorEl = document.getElementById('settings-tier-create-error');
        errorEl.style.display = 'none';

        const keyInput = document.getElementById('settings-tier-new-key');
        const tierKey = keyInput.value.trim();

        if (!tierKey) {
            errorEl.textContent = 'Entry Key is required.';
            errorEl.style.display = 'block';
            return;
        }
        if (/[{}",:]/.test(tierKey)) {
            errorEl.textContent = 'Entry Key cannot contain { } " , : characters.';
            errorEl.style.display = 'block';
            return;
        }
        if (this.tiers[tierKey]) {
            errorEl.textContent = 'An entry with this key already exists.';
            errorEl.style.display = 'block';
            return;
        }

        // Collect values from rendered inputs (authoritative) into the draft
        const renderedInputs = document.querySelectorAll('.tier-create-field-input');
        renderedInputs.forEach(inp => {
            const idx = parseInt(inp.dataset.index, 10);
            if (!isNaN(idx) && self.createEntryFields[idx]) {
                self.createEntryFields[idx].value = inp.value;
            }
        });

        // Build new tier object
        const tierObj = {};
        this.createEntryFields.forEach(f => {
            if (f.name) tierObj[f.name] = f.value;
        });

        const updatedTiers = Object.assign({}, this.tiers);
        updatedTiers[tierKey] = tierObj;

        this.showPasswordModal(
            'Create Entry',
            'Enter admin password to create entry "' + tierKey + '".',
            function(password) {
                const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
                self.postAction('settings_save_tier_limit', {
                    tiers: JSON.stringify(updatedTiers),
                    admin_password: password,
                    login_id: loginId
                }).then(data => {
                    if (data.success) {
                        self.tiers = updatedTiers;
                        self.recomputeTierFields();
                        self.renderTierRecords();
                        self.renderTierCreateForm();
                        self.renderTierFieldsTab();

                        // Clear form
                        keyInput.value = '';
                        self.switchTierSubTab('tiers');
                        self.showNotification('Entry "' + tierKey + '" created!', 'Success', false);
                    } else if (data.error === 'Invalid password') {
                        self.showNotification('Password verification failed.', 'Error', true);
                    } else {
                        errorEl.textContent = data.error || 'Unknown error';
                        errorEl.style.display = 'block';
                    }
                }).catch(err => {
                    errorEl.textContent = 'Error: ' + err.message;
                    errorEl.style.display = 'block';
                });
            }
        );
    },

    // ---------- Entries Fields sub-tab ----------
    renderTierFieldsTab: function() {
        const lockedEl   = document.getElementById('settings-tier-fields-locked');
        const unlockedEl = document.getElementById('settings-tier-fields-unlocked');
        const matrixEl   = document.getElementById('settings-tier-fields-matrix');
        const tabBtn     = document.getElementById('tier-fields-tab-btn');

        const hasEntries = Object.keys(this.tiers).length > 0;

        if (tabBtn) {
            tabBtn.disabled = !hasEntries;
            tabBtn.style.opacity = hasEntries ? '1' : '0.5';
            tabBtn.style.cursor  = hasEntries ? 'pointer' : 'not-allowed';
            tabBtn.title = hasEntries ? '' : 'Create an entry first to unlock field management';
        }

        if (!hasEntries) {
            lockedEl.style.display = 'block';
            unlockedEl.style.display = 'none';

            // If user is currently on fields subtab, bounce back to tiers
            if (this.currentTierSubTab === 'fields') {
                this.currentTierSubTab = 'tiers';
                document.querySelectorAll('#settings-tier-sub-tabs .tab-btn').forEach(b => {
                    b.classList.toggle('active', b.dataset.settingsTiersubtab === 'tiers');
                });
                document.querySelectorAll('#tab-tierlimit > .tab-content').forEach(el => {
                    el.classList.toggle('active', el.id === 'subtab-tiers');
                });
            }
            return;
        }

        lockedEl.style.display = 'none';
        unlockedEl.style.display = 'block';

        if (this.tierFields.length === 0) {
            matrixEl.innerHTML = '<div class="settings-empty">No fields defined yet. Add one above.</div>';
            return;
        }

        const tierKeys = Object.keys(this.tiers);

        // Build a matrix: rows = fields, columns = entries
        // Header row with entry keys, then each field row has: field name input, rename/save/delete buttons,
        // and one input per entry for the value.
        let html = '';
        html += '<div class="tier-matrix">';

        // Header row
        html += '<div class="tier-matrix-row tier-matrix-header">';
        html += '<div class="tier-matrix-cell tier-matrix-fieldname-cell">Field</div>';
        tierKeys.forEach(k => {
            html += `<div class="tier-matrix-cell tier-matrix-entry-cell">${this.escapeHtml(k)}</div>`;
        });
        html += '<div class="tier-matrix-cell tier-matrix-actions-cell">Actions</div>';
        html += '</div>';

        // Field rows
        this.tierFields.forEach(field => {
            html += '<div class="tier-matrix-row" data-field-name="' + this.escapeHtml(field) + '">';

            // Field name cell with an editable input
            html += `<div class="tier-matrix-cell tier-matrix-fieldname-cell">
                        <input type="text" class="settings-input tier-matrix-fieldname-input"
                               data-original-name="${this.escapeHtml(field)}"
                               value="${this.escapeHtml(field)}">
                     </div>`;

            // One input per entry
            tierKeys.forEach(k => {
                const tierObj = this.tiers[k] || {};
                const value = tierObj[field] !== undefined ? tierObj[field] : '';
                html += `<div class="tier-matrix-cell tier-matrix-entry-cell">
                            <input type="text" class="settings-input tier-matrix-value-input"
                                   data-tier-key="${this.escapeHtml(k)}"
                                   data-field-name="${this.escapeHtml(field)}"
                                   value="${this.escapeHtml(String(value))}">
                         </div>`;
            });

            // Actions
            html += `<div class="tier-matrix-cell tier-matrix-actions-cell">
                        <button class="tier-save-btn" onclick="Settings.saveFieldRow('${this.escapeHtml(field)}')">Save</button>
                        <button class="tier-field-delete-btn" onclick="Settings.deleteTierField('${this.escapeHtml(field)}')">Delete</button>
                     </div>`;

            html += '</div>';
        });

        html += '</div>';
        matrixEl.innerHTML = html;
    },

    addGlobalField: function() {
        const self = this;
        const errorEl = document.getElementById('settings-tier-fields-error');
        errorEl.style.display = 'none';

        const nameInput = document.getElementById('settings-tier-new-field-name');
        const defaultInput = document.getElementById('settings-tier-new-field-default');
        const newName = nameInput.value.trim();
        const defaultValue = defaultInput.value;

        if (!newName) {
            errorEl.textContent = 'Field name is required.';
            errorEl.style.display = 'block';
            return;
        }
        if (/[{}",:]/.test(newName)) {
            errorEl.textContent = 'Field name cannot contain { } " , : characters.';
            errorEl.style.display = 'block';
            return;
        }
        if (this.tierFields.includes(newName)) {
            errorEl.textContent = 'A field with this name already exists.';
            errorEl.style.display = 'block';
            return;
        }

        // Add the field to every entry with the default value
        const updatedTiers = {};
        Object.keys(this.tiers).forEach(k => {
            const obj = Object.assign({}, this.tiers[k] || {});
            obj[newName] = defaultValue;
            updatedTiers[k] = obj;
        });

        this.showPasswordModal(
            'Add Field',
            'Enter admin password to add field "' + newName + '" to all entries.',
            function(password) {
                const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
                self.postAction('settings_save_tier_limit', {
                    tiers: JSON.stringify(updatedTiers),
                    admin_password: password,
                    login_id: loginId
                }).then(data => {
                    if (data.success) {
                        self.tiers = updatedTiers;
                        self.recomputeTierFields();
                        nameInput.value = '';
                        defaultInput.value = '';
                        self.renderTierRecords();
                        self.renderTierFieldsTab();
                        self.showNotification('Field "' + newName + '" added to all entries!', 'Success', false);
                    } else if (data.error === 'Invalid password') {
                        self.showNotification('Password verification failed.', 'Error', true);
                    } else {
                        errorEl.textContent = data.error || 'Unknown error';
                        errorEl.style.display = 'block';
                    }
                }).catch(err => {
                    errorEl.textContent = 'Error: ' + err.message;
                    errorEl.style.display = 'block';
                });
            }
        );
    },

    saveFieldRow: function(originalFieldName) {
        const self = this;
        const errorEl = document.getElementById('settings-tier-fields-error');
        errorEl.style.display = 'none';

        const row = document.querySelector(`.tier-matrix-row[data-field-name="${CSS.escape(originalFieldName)}"]`);
        if (!row) return;

        const nameInput = row.querySelector('.tier-matrix-fieldname-input');
        const newName = nameInput ? nameInput.value.trim() : originalFieldName;

        if (!newName) {
            errorEl.textContent = 'Field name cannot be empty.';
            errorEl.style.display = 'block';
            return;
        }
        if (/[{}",:]/.test(newName)) {
            errorEl.textContent = 'Field name cannot contain { } " , : characters.';
            errorEl.style.display = 'block';
            return;
        }
        if (newName !== originalFieldName && this.tierFields.includes(newName)) {
            errorEl.textContent = 'Another field already uses the name "' + newName + '".';
            errorEl.style.display = 'block';
            return;
        }

        // Collect per-entry values from inputs
        const valueInputs = row.querySelectorAll('.tier-matrix-value-input');
        const valueMap = {}; // { tierKey: value }
        valueInputs.forEach(inp => {
            valueMap[inp.dataset.tierKey] = inp.value;
        });

        // Build updated tiers
        const updatedTiers = {};
        Object.keys(this.tiers).forEach(k => {
            const oldObj = this.tiers[k] || {};
            const newObj = {};

            Object.keys(oldObj).forEach(key => {
                if (key === originalFieldName) {
                    // Rename or keep the key, but use the new value from the matrix
                    const newKey = newName;
                    newObj[newKey] = (valueMap[k] !== undefined) ? valueMap[k] : oldObj[key];
                } else {
                    newObj[key] = oldObj[key];
                }
            });

            // If this entry didn't have the field at all, add it now
            if (!(originalFieldName in oldObj)) {
                newObj[newName] = (valueMap[k] !== undefined) ? valueMap[k] : '';
            }

            updatedTiers[k] = newObj;
        });

        this.showPasswordModal(
            'Save Field',
            'Enter admin password to save field changes.',
            function(password) {
                const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
                self.postAction('settings_save_tier_limit', {
                    tiers: JSON.stringify(updatedTiers),
                    admin_password: password,
                    login_id: loginId
                }).then(data => {
                    if (data.success) {
                        self.tiers = updatedTiers;
                        self.recomputeTierFields();
                        self.renderTierRecords();
                        self.renderTierFieldsTab();
                        self.showNotification('Field saved!', 'Success', false);
                    } else if (data.error === 'Invalid password') {
                        self.showNotification('Password verification failed.', 'Error', true);
                    } else {
                        errorEl.textContent = data.error || 'Unknown error';
                        errorEl.style.display = 'block';
                    }
                }).catch(err => {
                    errorEl.textContent = 'Error: ' + err.message;
                    errorEl.style.display = 'block';
                });
            }
        );
    },

    deleteTierField: function(fieldName) {
        const self = this;
        this.showConfirmModal(
            'Delete Field',
            'Delete field "' + fieldName + '" from ALL entries? This cannot be undone.',
            function() {
                self.showPasswordModal(
                    'Delete Field',
                    'Enter admin password to delete this field from all entries.',
                    function(password) {
                        const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
                        const updatedTiers = {};
                        Object.keys(self.tiers).forEach(k => {
                            const obj = Object.assign({}, self.tiers[k] || {});
                            delete obj[fieldName];
                            updatedTiers[k] = obj;
                        });

                        self.postAction('settings_save_tier_limit', {
                            tiers: JSON.stringify(updatedTiers),
                            admin_password: password,
                            login_id: loginId
                        }).then(data => {
                            if (data.success) {
                                self.tiers = updatedTiers;
                                self.recomputeTierFields();
                                self.renderTierRecords();
                                self.renderTierFieldsTab();
                                self.showNotification('Field deleted from all entries!', 'Success', false);
                            } else if (data.error === 'Invalid password') {
                                self.showNotification('Password verification failed.', 'Error', true);
                            } else {
                                self.showNotification('Error: ' + (data.error || 'Unknown error'), 'Error', true);
                            }
                        }).catch(err => self.showNotification('Error: ' + err.message, 'Error', true));
                    }
                );
            }
        );
    },

    closeTierFieldModal: function() {
        document.getElementById('settings-tier-field-modal').style.display = 'none';
        this.editingFieldName = null;
    },

    saveTierFieldFromModal: function() {
        // This modal is no longer used in the new flow, but kept for safety.
        this.closeTierFieldModal();
    },

    // ============================================
    // MAILER
    // ============================================
    saveMailer: function() {
        const self = this;
        const payload = {
            mailer_email:    document.getElementById('settings-mailer-email').value.trim(),
            mailer_password: document.getElementById('settings-mailer-password').value
        };
        this.showPasswordModal(
            'Save Mailer Credentials',
            'Enter admin password to save mailer credentials.',
            function(password) {
                const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
                payload.admin_password = password;
                payload.login_id = loginId;
                self.postAction('settings_update_mailer', payload).then(data => {
                    if (data.success) {
                        self.showNotification('Mailer credentials saved!', 'Success', false);
                    } else if (data.error === 'Invalid password') {
                        self.showNotification('Password verification failed.', 'Error', true);
                    } else {
                        self.showNotification('Error: ' + (data.error || 'Unknown error'), 'Error', true);
                    }
                }).catch(err => self.showNotification('Error: ' + err.message, 'Error', true));
            }
        );
    },

    // ============================================
    // CREDENTIALS
    // ============================================
    saveCredentials: function() {
        const self = this;
        const errorEl = document.getElementById('settings-credentials-error');
        errorEl.style.display = 'none';

        const loginId = document.getElementById('settings-admin-login').value.trim();
        const pw      = document.getElementById('settings-admin-password').value;
        const pwConfirm = document.getElementById('settings-admin-password-confirm').value;

        if (!loginId) {
            errorEl.textContent = 'Admin Login ID is required.';
            errorEl.style.display = 'block';
            return;
        }
        if (pw && pw !== pwConfirm) {
            errorEl.textContent = 'Password and Confirm Password do not match.';
            errorEl.style.display = 'block';
            return;
        }

        this.showPasswordModal(
            'Save Credentials',
            'Enter current admin password to confirm changes.',
            function(currentPassword) {
                const payload = {
                    admin_login_id: loginId,
                    admin_password: pw || '',
                    admin_password_current: currentPassword,
                    login_id: document.getElementById('settings-login-id-hidden')?.value || ''
                };
                self.postAction('settings_update_credentials', payload).then(data => {
                    if (data.success) {
                        document.getElementById('settings-login-id-hidden').value = loginId;
                        document.getElementById('settings-admin-password').value = '';
                        document.getElementById('settings-admin-password-confirm').value = '';
                        self.showNotification('Credentials updated successfully!', 'Success', false);
                    } else if (data.error === 'Invalid password') {
                        self.showNotification('Current password verification failed.', 'Error', true);
                    } else {
                        self.showNotification('Error: ' + (data.error || 'Unknown error'), 'Error', true);
                    }
                }).catch(err => self.showNotification('Error: ' + err.message, 'Error', true));
            }
        );
    },

    escapeHtml: function(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            if (m === '"') return '&quot;';
            if (m === "'") return '&#039;';
            return m;
        });
    }
};

document.addEventListener('DOMContentLoaded', function() {
    Settings.init();
});
</script>