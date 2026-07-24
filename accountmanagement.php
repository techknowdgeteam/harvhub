<?php
// accountmanagement.php
?>


<h2>Account Management</h2>


<!-- Tab Navigation -->
<div class="management-tabs">
    <button class="tab-btn active" data-tab="server">📁 Server Configuration</button>
    <button class="tab-btn" data-tab="users">👥 User Configuration</button>
    <button class="tab-btn" data-tab="invested">💰 Invested With</button>
    <button class="tab-btn" data-tab="pending">⏳ Pending Users</button>
    <button class="tab-btn" data-tab="justjoined">🆕 Just Joined</button>
    <button class="tab-btn" data-tab="justjoinedvalid">📝 Just Joined & Valid</button>
    <button class="tab-btn" data-tab="approved">✓ Approved Users</button>
    <button class="tab-btn" data-tab="verified">👥 Active Investors</button>
    <button class="tab-btn" data-tab="execution">📜 Execution History</button>
    <button class="tab-btn" data-tab="suspended">🚫 Suspended Users</button>
    <button class="tab-btn" data-tab="bypassed">⚠️ Users that should Bypass Unauthorized actions</button>
    <button class="tab-btn" data-tab="autotrading">🤖 Restrictions Decision</button>
</div>

<!-- User Configuration Tab -->
<div id="users-tab" class="management-tab" style="display: none;">
    <div class="split-view">
        <div class="user-list-panel">
            <div class="users-sidebar-header" onclick="showAllUsersModal()">
                <div class="search-user-btn">
                    <span class="search-icon">🔍</span>
                    <span class="search-placeholder">Search users...</span>
                </div>
            </div>
            <div class="default-user-card" id="default-user-card">
                <div class="loading-spinner-small">
                    <div class="spinner-small"></div>
                    <div>Loading default user...</div>
                </div>
            </div>
        </div>
        <div class="management-panel">
            <div class="management-header">
                <h3>User Account Configuration</h3>
            </div>

            <div id="selected-user-info" class="user-info">
                <div class="user-info-item"><span class="user-info-label">Selected User:</span> <span id="selected-user-name">None</span></div>
                <div class="user-info-item"><span class="user-info-label">Email:</span> <span id="selected-user-email">-</span></div>
                <div class="user-info-item"><span class="user-info-label">Source:</span> <span id="selected-user-source">-</span></div>
                <div class="user-info-item"><span class="user-info-label">Current Status:</span> <span id="current-application-status" class="status-badge-pending">-</span></div>
                <div class="user-info-item">
                    <span class="user-info-label">Change Status:</span>
                    <select id="application-status-select" class="status-select">
                        <option value="">Select Status</option>
                        <option value="approved">Approve</option>
                        <option value="declined">Decline</option>
                        <option value="pending">Pending</option>
                        <option value="suspended">Suspend</option>
                        <option value="blacklisted">Blacklist</option>
                    </select>
                    <button id="update-status-btn" class="update-status-small-btn" onclick="updateApplicationStatus()">Update</button>
                </div>
            </div>

            <!-- Foldable User Configuration Container -->
            <div class="account-management-container" style="margin-top: 20px;">
                <div class="config-entry-header" onclick="toggleUserConfigExpand()">
                    <div class="config-entry-title-wrapper">
                        <span class="collapse-icon" id="user-config-icon">▶</span>
                        <span class="config-entry-title" id="user-config-title">📁 User Configuration</span>
                    </div>
                    <div class="config-entry-buttons" onclick="event.stopPropagation()">
                        <button type="button" class="edit-json-btn-header" id="user-edit-btn" onclick="openEditModal('user')" disabled>✏️ Edit JSON</button>
                        <button type="button" class="copy-config-btn" id="user-config-copy-btn" onclick="copyUserConfigToClipboard()" style="display: none;">📋 Copy JSON</button>
                    </div>
                </div>
                <div class="config-entry-content" id="user-config-content" style="display: none;">
                    <div id="user-json-viewer" class="json-viewer">
                        <div style="text-align: center; padding: 40px; color: #888;">Select a user from the list to view their configuration</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Server Configuration Tab -->
<div id="server-tab" class="management-tab active">
    <!-- Server Configuration with Fold Layout -->
    <div class="account-management-container">
        <div class="config-entry-header" onclick="toggleServerConfigExpand()">
            <div class="config-entry-title-wrapper">
                <span class="collapse-icon" id="server-config-icon">▶</span>
                <span class="config-entry-title">📁 Server Configuration</span>
            </div>
            <div class="config-entry-buttons" onclick="event.stopPropagation()">
                <button type="button" class="edit-config-btn" id="server-edit-btn" onclick="openEditModal('server')">✏️ Edit JSON</button>
                <button type="button" class="copy-config-btn" id="server-copy-btn" onclick="copyJsonToClipboard('server')">📋 Copy JSON</button>
            </div>
        </div>
        <div class="config-entry-content" id="server-config-content" style="display: none;">
            <div id="server-json-viewer" class="json-viewer">
                <div style="text-align: center; padding: 40px;">Loading server configuration...</div>
            </div>
        </div>
    </div>
    
    <!-- Account Management Configs Section -->
    <div class="account-management-container">
        <div class="management-header">
            <h3>📁 Account Management Configurations</h3>
            <div class="header-buttons">
                <button type="button" class="refresh-configs-btn" onclick="loadAccountManagementConfigs()" style="background: #3498db;">🔄 Refresh</button>
                <button type="button" class="add-config-entry-btn" onclick="showAddConfigEntryModal()" style="background: #27ae60;">➕ Add New Configuration</button>
            </div>
        </div>
        <div id="config-entries-container" class="config-entries-grid">
            <div style="text-align: center; padding: 40px;">Loading configurations...</div>
        </div>
    </div>
</div>

<!-- Invested With Management Tab -->
<div id="invested-tab" class="management-tab" style="display: none;">
    <div class="invested-management-container">
        <div class="management-header">
            <h3>💰 Invested With Management</h3>
            <div class="header-buttons">
                <button type="button" class="refresh-invested-btn" onclick="loadInvestedWithUsers()">🔄 Refresh List</button>
            </div>
        </div>
        <div style="padding: 10px 20px; border-bottom: 1px solid var(--border-color);">
            <input type="text" id="invested-users-search" class="search-input" placeholder="🔍 Search by name, email or ID..." style="width: 100%; padding: 8px; font-size: 12px;">
        </div>
        <div id="invested-users-list" class="invested-users-table-container" style="max-height: 500px; overflow-y: auto;">
            <div style="text-align: center; padding: 40px;">Loading users...</div>
        </div>
    </div>
</div>

<!-- Active Investors Tab -->
<div id="verified-tab" class="management-tab" style="display: none;">
    <div class="user-viewer-container">
        <div class="management-header">
            <h3>✅ Active Investors</h3>
            <div class="header-buttons">
                <button type="button" class="refresh-verified-btn" onclick="loadVerifiedUsers()">🔄 Refresh</button>
            </div>
        </div>
        <div style="padding: 10px 20px; border-bottom: 1px solid var(--border-color);">
            <input type="text" id="verified-users-search" class="search-input" placeholder="🔍 Search by name, email or ID..." style="width: 100%; padding: 8px; font-size: 12px;">
        </div>
        <div id="verified-users-list" class="user-viewer-table-container" style="max-height: 500px; overflow-y: auto;">
            <div style="text-align: center; padding: 40px;">Loading Active Investors...</div>
        </div>
    </div>
</div>

<!-- Pending Users Tab -->
<div id="pending-tab" class="management-tab" style="display: none;">
    <div class="user-viewer-container">
        <div class="management-header">
            <h3>⏳ Pending Users</h3>
            <div class="header-buttons">
                <button type="button" class="refresh-pending-btn" onclick="loadPendingUsers()">🔄 Refresh</button>
            </div>
        </div>
        <div style="padding: 10px 20px; border-bottom: 1px solid var(--border-color);">
            <input type="text" id="pending-users-search" class="search-input" placeholder="🔍 Search by name, email or ID..." style="width: 100%; padding: 8px; font-size: 12px;">
        </div>
        <div id="pending-users-list" class="user-viewer-table-container" style="max-height: 500px; overflow-y: auto;">
            <div style="text-align: center; padding: 40px;">Loading pending users...</div>
        </div>
    </div>
</div>

<!-- Suspended Users Tab -->
<div id="suspended-tab" class="management-tab" style="display: none;">
    <div class="user-viewer-container">
        <div class="management-header">
            <h3>🚫 Suspended/Blacklisted Users</h3>
            <div class="header-buttons">
                <button type="button" class="refresh-suspended-btn" onclick="loadSuspendedUsers()">🔄 Refresh</button>
            </div>
        </div>
        <div style="padding: 10px 20px; border-bottom: 1px solid var(--border-color);">
            <input type="text" id="suspended-users-search" class="search-input" placeholder="🔍 Search by name, email or ID..." style="width: 100%; padding: 8px; font-size: 12px;">
        </div>
        <div id="suspended-users-list" class="user-viewer-table-container" style="max-height: 500px; overflow-y: auto;">
            <div style="text-align: center; padding: 40px;">Loading suspended users...</div>
        </div>
    </div>
</div>

<!-- Just Joined Users Tab -->
<div id="justjoined-tab" class="management-tab" style="display: none;">
    <div class="user-viewer-container">
        <div class="management-header">
            <h3>🆕 Just Joined Users</h3>
            <div class="header-buttons">
                <button type="button" class="refresh-justjoined-btn" onclick="loadJustJoinedUsers()">🔄 Refresh</button>
            </div>
        </div>
        <div style="padding: 10px 20px; border-bottom: 1px solid var(--border-color);">
            <input type="text" id="justjoined-users-search" class="search-input" placeholder="🔍 Search by name, email or ID..." style="width: 100%; padding: 8px; font-size: 12px;">
        </div>
        <div id="justjoined-users-list" class="user-viewer-table-container" style="max-height: 500px; overflow-y: auto;">
            <div style="text-align: center; padding: 40px;">Loading just joined users...</div>
        </div>
    </div>
</div>

<!-- Just Joined & Valid Credentials Users Tab -->
<div id="justjoinedvalid-tab" class="management-tab" style="display: none;">
    <div class="user-viewer-container">
        <div class="management-header">
            <h3>📝 Just Joined & Valid Credentials</h3>
            <div class="header-buttons">
                <button type="button" class="refresh-justjoinedvalid-btn" onclick="loadJustJoinedValidUsers()">🔄 Refresh</button>
            </div>
        </div>
        <div style="padding: 10px 20px; border-bottom: 1px solid var(--border-color);">
            <input type="text" id="justjoinedvalid-users-search" class="search-input" placeholder="🔍 Search by name, email or ID..." style="width: 100%; padding: 8px; font-size: 12px;">
        </div>
        <div id="justjoinedvalid-users-list" class="user-viewer-table-container" style="max-height: 500px; overflow-y: auto;">
            <div style="text-align: center; padding: 40px;">Loading users...</div>
        </div>
    </div>
</div>

<!-- Approved Users Tab -->
<div id="approved-tab" class="management-tab" style="display: none;">
    <div class="user-viewer-container">
        <div class="management-header">
            <h3>✓ Approved Users</h3>
            <div class="header-buttons">
                <button type="button" class="refresh-approved-btn" onclick="loadApprovedUsers()">🔄 Refresh</button>
            </div>
        </div>
        <div style="padding: 10px 20px; border-bottom: 1px solid var(--border-color);">
            <input type="text" id="approved-users-search" class="search-input" placeholder="🔍 Search by name, email or ID..." style="width: 100%; padding: 8px; font-size: 12px;">
        </div>
        <div id="approved-users-list" class="user-viewer-table-container" style="max-height: 500px; overflow-y: auto;">
            <div style="text-align: center; padding: 40px;">Loading approved users...</div>
        </div>
    </div>
</div>

<!-- Bypassed Unauthorized Actions Users Tab -->
<div id="bypassed-tab" class="management-tab" style="display: none;">
    <div class="user-viewer-container">
        <div class="management-header">
            <h3>⚠️ Bypassed Unauthorized Actions Users</h3>
            <div class="header-buttons">
                <button type="button" class="refresh-bypassed-btn" onclick="loadBypassedUsers()">🔄 Refresh</button>
            </div>
        </div>
        <div style="padding: 10px 20px; border-bottom: 1px solid var(--border-color);">
            <input type="text" id="bypassed-users-search" class="search-input" placeholder="🔍 Search by name, email or ID..." style="width: 100%; padding: 8px; font-size: 12px;">
        </div>
        <div id="bypassed-users-list" class="user-viewer-table-container" style="max-height: 500px; overflow-y: auto;">
            <div style="text-align: center; padding: 40px;">Loading bypassed users...</div>
        </div>
    </div>
</div>

<!-- Execution History Tab -->
<div id="execution-tab" class="management-tab" style="display: none;">
    <div class="split-view">
        <div class="user-list-panel">
            <div class="users-sidebar-header" onclick="showExecutionUsersModal()">
                <div class="search-user-btn">
                    <span class="search-icon">🔍</span>
                    <span class="search-placeholder">Search users...</span>
                </div>
            </div>
            <div class="default-user-card" id="execution-default-user-card">
                <div class="loading-spinner-small">
                    <div class="spinner-small"></div>
                    <div>Loading default user...</div>
                </div>
            </div>
        </div>
        <div class="management-panel">
            <div class="management-header">
                <h3>Execution History</h3>
                <div class="header-buttons">
                    <button type="button" class="refresh-execution-btn" onclick="loadExecutionHistoryForUser()" style="background: #3498db;">🔄 Refresh</button>
                </div>
            </div>
            <div id="selected-execution-user-info" class="user-info">
                <div class="user-info-item"><span class="user-info-label">Selected User:</span> <span id="selected-execution-user-name">None</span></div>
                <div class="user-info-item"><span class="user-info-label">Email:</span> <span id="selected-execution-user-email">-</span></div>
                <div class="user-info-item"><span class="user-info-label">Source:</span> <span id="selected-execution-user-source">-</span></div>
            </div>
            <div id="execution-history-list" class="execution-history-container" style="max-height: 500px; overflow-y: auto;">
                <div style="text-align: center; padding: 40px; color: #888;">Select a user from the list to view their execution history</div>
            </div>
        </div>
    </div>
</div>

<!-- Autotrading & Restrictions Tab -->
<div id="autotrading-tab" class="management-tab" style="display: none;">
    <div class="split-view">
        <div class="user-list-panel">
            <div class="users-sidebar-header" onclick="showAutotradingUsersModal()">
                <div class="search-user-btn">
                    <span class="search-icon">🔍</span>
                    <span class="search-placeholder">Search users...</span>
                </div>
            </div>
            <div class="default-user-card" id="autotrading-default-user-card">
                <div class="loading-spinner-small">
                    <div class="spinner-small"></div>
                    <div>Loading default user...</div>
                </div>
            </div>
        </div>
        <div class="management-panel" style="overflow: hidden; display: flex; flex-direction: column;">
            <div class="management-header">
                <h3>Autotrading</h3>
                <div class="header-buttons">
                    <button type="button" class="save-settings-btn" id="save-autotrading-btn" onclick="saveAutoTradingSettings()" style="background: #27ae60;">💾 Save All Settings</button>
                </div>
            </div>
            <div id="auto-trading-settings" class="auto-trading-settings-container" style="flex: 1; overflow-y: auto;">
                <div style="text-align: center; padding: 40px; color: #888;">Select a user from the list to manage their settings</div>
            </div>
        </div>
    </div>
</div>

<!-- Edit JSON Modal -->
<div id="edit-json-modal" class="modal-overlay" style="display: none;">
    <div class="modal-container modal-large" id="edit-json-modal-container">
        <div class="modal-header">
            <span id="edit-json-modal-title">✏️ Edit JSON Configuration</span>
            <span class="modal-close" onclick="closeEditModal()">✕</span>
        </div>
        <div class="modal-body" style="padding: 20px; display: flex; flex-direction: column; height: 100%;">
            <div style="flex: 1; display: flex; flex-direction: column; min-height: 400px;">
                <textarea id="edit-json-textarea" class="json-editor-fullwidth" style="flex: 1; min-height: 400px; width: 100%; padding: 15px; background: var(--bg-secondary); color: var(--text-color); border: 2px solid var(--accent-color); border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; resize: vertical; white-space: pre; overflow: auto; box-sizing: border-box;"></textarea>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-color);">
                <button type="button" class="modal-cancel-btn" onclick="closeEditModal()" style="background: #e74c3c; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px;">Cancel</button>
                <button type="button" class="modal-confirm-btn" onclick="saveEditModal()" style="background: #27ae60; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px;">💾 Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
    // ============================================
    // ACCOUNT MANAGEMENT - COMPLETE SCRIPT
    // ============================================

    // Global Variables
    let currentEditingData = null;
    let currentUserId = null;
    let currentSourceTable = null;
    let currentTargetType = null;
    let isEditMode = false;
    let originalDataBackup = null;
    let currentTab = 'server';
    let allUsersCache = [];
    let currentFilteredUsers = [];
    let editModalTargetType = null;
    let editModalDataBackup = null;
    let pendingSaveData = null;

    // Autotrading Settings Variables
    let currentAutoTradingUserId = null;
    let currentAutoTradingSourceTable = null;
    let currentAutoTradingData = {
        enable_autotrading: 1,
        bypass_restriction: 0,
        demo_account: 0
    };

    // Execution History Variables
    let currentExecutionUserId = null;
    let currentExecutionSourceTable = null;

    // User Config Fold State
    let isUserConfigExpanded = false;
    let isServerConfigExpanded = false;

    // Config Entry State
    let currentEditingConfigEntry = null;
    let originalConfigEntryBackup = null;
    let currentlyExpandedConfig = null;

    // Config Data Cache
    let configDataCache = {};
    let configDataCacheTimestamp = {};

    // ============================================
    // CUSTOM MODAL FUNCTIONS
    // ============================================

    function showCustomModal(title, message, type = 'info', callback = null) {
        // Remove any existing modal
        const existingModal = document.getElementById('custom-modal-overlay');
        if (existingModal) {
            existingModal.remove();
        }

        let icon = 'ℹ️';
        let modalClass = 'modal-info';
        if (type === 'success') {
            icon = '✅';
            modalClass = 'modal-success';
        } else if (type === 'error') {
            icon = '❌';
            modalClass = 'modal-error';
        } else if (type === 'warning') {
            icon = '⚠️';
            modalClass = 'modal-warning';
        }

        const modalHtml = `
            <div class="modal-overlay" id="custom-modal-overlay" onclick="closeCustomModal(event)">
                <div class="modal-container custom-modal ${modalClass}" onclick="event.stopPropagation()" style="max-width: 450px;">
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
        
        // Store callback if provided
        if (callback) {
            window._customModalCallback = callback;
        }
    }

    function showCustomConfirm(title, message, confirmText = 'Confirm', cancelText = 'Cancel', onConfirm = null) {
        // Remove any existing modal
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
        if (window._customModalCallback) {
            window._customModalCallback = null;
        }
    }

    function showPasswordModal(title, message, onConfirm) {
        // Remove any existing modal
        const existingModal = document.getElementById('password-modal-overlay');
        if (existingModal) {
            existingModal.remove();
        }

        const modalHtml = `
            <div class="modal-overlay" id="password-modal-overlay" onclick="closePasswordModal(event)">
                <div class="modal-container" onclick="event.stopPropagation()" style="max-width: 400px;">
                    <div class="modal-header">
                        <span>🔐 ${title}</span>
                        <span class="modal-close" onclick="closePasswordModal()">✕</span>
                    </div>
                    <div class="modal-body">
                        <p style="font-size: 14px; margin-bottom: 15px;">${message}</p>
                        <input type="password" id="password-modal-input" class="json-password-input" placeholder="Enter admin password..." autocomplete="off" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-color); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px;">
                            <button class="modal-cancel-btn" onclick="closePasswordModal()" style="padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; background: #e74c3c; color: white;">Cancel</button>
                            <button class="modal-confirm-btn" id="password-modal-confirm" style="padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; background: #27ae60; color: white;">Confirm</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const input = document.getElementById('password-modal-input');
        const confirmBtn = document.getElementById('password-modal-confirm');

        input.focus();

        confirmBtn.addEventListener('click', function() {
            const password = input.value;
            if (!password) {
                showCustomModal('Error', 'Password is required', 'error');
                input.focus();
                return;
            }
            closePasswordModal();
            if (onConfirm) {
                onConfirm(password);
            }
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                confirmBtn.click();
            }
        });
    }

    function closePasswordModal(event) {
        if (event && event.target && event.target.id !== 'password-modal-overlay' && event.target.className !== 'modal-close' && event.target.className !== 'modal-confirm-btn' && event.target.className !== 'modal-cancel-btn') {
            return;
        }
        const modal = document.getElementById('password-modal-overlay');
        if (modal) {
            modal.remove();
        }
    }

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateString) {
        if (!dateString) return 'Unknown date';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const month = months[date.getMonth()];
        const day = date.getDate();
        const year = date.getFullYear();
        let hours = date.getHours();
        const minutes = date.getMinutes().toString().padStart(2, '0');
        const seconds = date.getSeconds().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        return `${month} ${day}, ${year} at ${hours}:${minutes}:${seconds} ${ampm}`;
    }

    function addBlur() {
        const container = document.querySelector('.split-view') || document.querySelector('.account-management-container');
        if (container) {
            container.classList.add('blur-background');
        }
    }

    function removeBlur() {
        const container = document.querySelector('.split-view') || document.querySelector('.account-management-container');
        if (container) {
            container.classList.remove('blur-background');
        }
    }

    // ============================================
    // EDIT JSON MODAL FUNCTIONS
    // ============================================

    function openEditModal(type) {
        if (type === 'user') {
            if (!currentUserId || !currentSourceTable) {
                showCustomModal('Error', 'Please select a user first', 'error');
                return;
            }
        }
        
        editModalTargetType = type;
        const modal = document.getElementById('edit-json-modal');
        const textarea = document.getElementById('edit-json-textarea');
        const title = document.getElementById('edit-json-modal-title');
        
        let dataToEdit = currentEditingData;
        let titleText = type === 'server' ? '✏️ Edit Server Configuration' : `✏️ Edit User Configuration - ${document.getElementById('selected-user-name')?.textContent || 'User'}`;
        
        if (!dataToEdit || (typeof dataToEdit === 'object' && Object.keys(dataToEdit).length === 0)) {
            dataToEdit = {
                "example_setting": "value",
                "description": "Edit this JSON as needed"
            };
        }
        
        editModalDataBackup = JSON.parse(JSON.stringify(dataToEdit));
        textarea.value = JSON.stringify(dataToEdit, null, 2);
        title.textContent = titleText;
        
        modal.style.display = 'flex';
        addBlur();
        
        setTimeout(() => {
            textarea.focus();
            textarea.setSelectionRange(0, 0);
        }, 100);
        
        // Add keyboard shortcut for Ctrl+S
        textarea.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                saveEditModal();
            }
        });
    }

    function closeEditModal() {
        const modal = document.getElementById('edit-json-modal');
        modal.style.display = 'none';
        removeBlur();
        editModalTargetType = null;
        editModalDataBackup = null;
    }

    function saveEditModal() {
        const textarea = document.getElementById('edit-json-textarea');
        let newData;
        try {
            newData = JSON.parse(textarea.value);
        } catch (e) {
            showCustomModal('Invalid JSON', 'Error parsing JSON: ' + e.message, 'error');
            return;
        }
        
        const typeText = editModalTargetType === 'server' ? 'Server Configuration' : `User Configuration for ${document.getElementById('selected-user-name')?.textContent || 'User'}`;
        showPasswordModal(
            'Security Verification',
            `Please enter your admin password to save changes to:<br><strong style="color: #3498db;">${typeText}</strong>`,
            function(password) {
                executeSaveWithPassword(password, editModalTargetType, newData);
            }
        );
    }

    function executeSaveWithPassword(password, type, newData) {
        let formData = new URLSearchParams();
        formData.append('action', 'update_json_value');
        formData.append('target_type', type);
        formData.append('path', '');
        formData.append('value', JSON.stringify(newData));
        formData.append('admin_password', password);
        formData.append('login_id', '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
        
        if (currentUserId) {
            formData.append('user_id', currentUserId);
            formData.append('source_table', currentSourceTable);
        }
        
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
                currentEditingData = newData;
                const containerId = type === 'server' ? '#server-json-viewer' : '#user-json-viewer';
                displayJsonViewer(newData, containerId);
                showCustomModal('Success', 'JSON configuration saved successfully!', 'success');
                closeEditModal();
                
                if (type === 'user') {
                    const copyBtn = document.getElementById('user-copy-btn');
                    if (copyBtn && newData && Object.keys(newData).length > 0) {
                        copyBtn.style.display = 'inline-block';
                    }
                }
            } else {
                if (data.error === 'Invalid password') {
                    showCustomModal('Error', 'Password verification failed. Please try again.', 'error');
                } else {
                    showCustomModal('Error', data.error || 'Error saving configuration', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showCustomModal('Error', 'Error saving configuration', 'error');
        })
        .finally(() => {
            pendingSaveData = null;
        });
    }

    // ============================================
    // GLOBAL SEARCH FUNCTIONS
    // ============================================

    function setupGlobalSearch() {
        const searchInput = document.getElementById('global-user-search');
        const clearBtn = document.getElementById('clear-global-search');
        if (!searchInput) return;
        
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            if (searchTerm.length > 0) {
                clearBtn.style.display = 'inline-block';
            } else {
                clearBtn.style.display = 'none';
            }
            applySearchToCurrentTab(searchTerm);
        });
        
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                clearBtn.style.display = 'none';
                applySearchToCurrentTab('');
                searchInput.focus();
            });
        }
    }

    function applySearchToCurrentTab(searchTerm) {
        if (currentTab === 'users') {
            filterUserList(searchTerm);
        } else if (currentTab === 'verified') {
            filterVerifiedUsers(searchTerm);
        } else if (currentTab === 'pending') {
            filterPendingUsers(searchTerm);
        } else if (currentTab === 'suspended') {
            filterSuspendedUsers(searchTerm);
        } else if (currentTab === 'justjoined') {
            filterJustJoinedUsers(searchTerm);
        } else if (currentTab === 'justjoinedvalid') {
            filterJustJoinedValidUsers(searchTerm);
        } else if (currentTab === 'approved') {
            filterApprovedUsers(searchTerm);
        } else if (currentTab === 'invested') {
            filterInvestedUsers(searchTerm);
        } else if (currentTab === 'autotrading') {
            filterAutoTradingUsers(searchTerm);
        } else if (currentTab === 'bypassed') {
            filterBypassedUsers(searchTerm);
        }
    }

    function filterBypassedUsers(searchTerm) {
        filterTableRows('bypassed-users-list', searchTerm, (row) => ({
            id: row.getAttribute('data-user-id') || '',
            email: (row.getAttribute('data-email') || '').toLowerCase(),
            fullname: (row.getAttribute('data-fullname') || '').toLowerCase()
        }));
    }

    function filterUserList(searchTerm) {
        const userItems = document.querySelectorAll('#user-items-list .user-item');
        let visibleCount = 0;
        userItems.forEach(item => {
            const userId = item.getAttribute('data-user-id') || '';
            const fullname = (item.getAttribute('data-fullname') || '').toLowerCase();
            const email = (item.getAttribute('data-email') || '').toLowerCase();
            if (searchTerm === '' || userId.includes(searchTerm) || fullname.includes(searchTerm) || email.includes(searchTerm)) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        const noResultsMsg = document.getElementById('user-list-no-results');
        if (!noResultsMsg && visibleCount === 0 && searchTerm !== '') {
            const container = document.getElementById('user-items-list');
            const msg = document.createElement('div');
            msg.id = 'user-list-no-results';
            msg.style.cssText = 'text-align: center; padding: 40px; color: #888;';
            msg.innerHTML = '🔍 No users match your search';
            container.appendChild(msg);
        } else if (noResultsMsg && visibleCount > 0) {
            noResultsMsg.remove();
        } else if (noResultsMsg && searchTerm === '') {
            noResultsMsg.remove();
        }
    }

    function filterTableRows(containerId, searchTerm, getRowData) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const rows = container.querySelectorAll('.user-data-row');
        let visibleCount = 0;
        rows.forEach(row => {
            const rowData = getRowData(row);
            if (searchTerm === '' || rowData.id.includes(searchTerm) || rowData.email.includes(searchTerm) || rowData.fullname.includes(searchTerm)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        const countSpan = document.getElementById(`${containerId}-count`);
        if (countSpan) {
            countSpan.textContent = visibleCount;
        }
        let noResultsMsg = container.querySelector('.no-results-message');
        if (visibleCount === 0 && searchTerm !== '') {
            if (!noResultsMsg) {
                noResultsMsg = document.createElement('div');
                noResultsMsg.className = 'no-results-message';
                noResultsMsg.style.cssText = 'text-align: center; padding: 40px; color: #888;';
                noResultsMsg.innerHTML = '🔍 No users match your search';
                container.appendChild(noResultsMsg);
            }
        } else if (noResultsMsg) {
            noResultsMsg.remove();
        }
    }

    function filterVerifiedUsers(searchTerm) {
        filterTableRows('verified-users-list', searchTerm, (row) => ({
            id: row.getAttribute('data-user-id') || '',
            email: (row.getAttribute('data-email') || '').toLowerCase(),
            fullname: (row.getAttribute('data-fullname') || '').toLowerCase()
        }));
    }

    function filterPendingUsers(searchTerm) {
        filterTableRows('pending-users-list', searchTerm, (row) => ({
            id: row.getAttribute('data-user-id') || '',
            email: (row.getAttribute('data-email') || '').toLowerCase(),
            fullname: (row.getAttribute('data-fullname') || '').toLowerCase()
        }));
    }

    function filterSuspendedUsers(searchTerm) {
        filterTableRows('suspended-users-list', searchTerm, (row) => ({
            id: row.getAttribute('data-user-id') || '',
            email: (row.getAttribute('data-email') || '').toLowerCase(),
            fullname: (row.getAttribute('data-fullname') || '').toLowerCase()
        }));
    }

    function filterJustJoinedUsers(searchTerm) {
        filterTableRows('justjoined-users-list', searchTerm, (row) => ({
            id: row.getAttribute('data-user-id') || '',
            email: (row.getAttribute('data-email') || '').toLowerCase(),
            fullname: (row.getAttribute('data-fullname') || '').toLowerCase()
        }));
    }

    function filterJustJoinedValidUsers(searchTerm) {
        const container = document.getElementById('justjoinedvalid-users-list');
        if (!container) return;
        const rows = container.querySelectorAll('.user-data-row');
        let visibleCount = 0;
        rows.forEach(row => {
            const userId = row.getAttribute('data-user-id') || '';
            const email = (row.getAttribute('data-email') || '').toLowerCase();
            const fullname = (row.getAttribute('data-fullname') || '').toLowerCase();
            if (searchTerm === '' || userId.includes(searchTerm) || email.includes(searchTerm) || fullname.includes(searchTerm)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        const countSpan = document.getElementById('justjoinedvalid-users-count');
        if (countSpan) {
            countSpan.textContent = visibleCount;
        }
        let noResultsMsg = container.querySelector('.no-results-message');
        if (visibleCount === 0 && searchTerm !== '') {
            if (!noResultsMsg) {
                noResultsMsg = document.createElement('div');
                noResultsMsg.className = 'no-results-message';
                noResultsMsg.style.cssText = 'text-align: center; padding: 40px; color: #888;';
                noResultsMsg.innerHTML = '🔍 No users match your search';
                container.appendChild(noResultsMsg);
            }
        } else if (noResultsMsg) {
            noResultsMsg.remove();
        }
    }

    function filterApprovedUsers(searchTerm) {
        filterTableRows('approved-users-list', searchTerm, (row) => ({
            id: row.getAttribute('data-user-id') || '',
            email: (row.getAttribute('data-email') || '').toLowerCase(),
            fullname: (row.getAttribute('data-fullname') || '').toLowerCase()
        }));
    }

    function filterInvestedUsers(searchTerm) {
        filterTableRows('invested-users-list', searchTerm, (row) => ({
            id: row.getAttribute('data-user-id') || '',
            email: (row.getAttribute('data-email') || '').toLowerCase(),
            fullname: (row.getAttribute('data-fullname') || '').toLowerCase()
        }));
    }

    function filterAutoTradingUsers(searchTerm) {
        const userItems = document.querySelectorAll('#autotrading-user-list .user-item');
        userItems.forEach(item => {
            const userId = item.getAttribute('data-user-id') || '';
            const fullname = (item.getAttribute('data-fullname') || '').toLowerCase();
            const email = (item.getAttribute('data-email') || '').toLowerCase();
            if (searchTerm === '' || userId.includes(searchTerm) || fullname.includes(searchTerm) || email.includes(searchTerm)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    // ============================================
    // JSON COPY FUNCTIONS
    // ============================================

    function copyJsonToClipboard(type) {
        let dataToCopy = null;
        if (type === 'server') {
            dataToCopy = currentEditingData;
        } else if (type === 'user') {
            if (!currentUserId || !currentSourceTable) {
                showCustomModal('Error', 'Please select a user first', 'error');
                return;
            }
            dataToCopy = currentEditingData;
        }
        if (!dataToCopy) {
            showCustomModal('Error', 'No data to copy', 'error');
            return;
        }
        if (typeof dataToCopy === 'object' && Object.keys(dataToCopy).length === 0) {
            showCustomModal('Error', 'No configuration data available to copy', 'error');
            return;
        }
        const jsonString = JSON.stringify(dataToCopy, null, 2);
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(jsonString).then(() => {
                const btn = document.getElementById(type + '-copy-btn');
                if (btn) {
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '✓ Copied!';
                    btn.style.background = '#27ae60';
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.style.background = '#3498db';
                    }, 2000);
                }
                showCustomModal('Success', 'JSON copied to clipboard!', 'success');
            }).catch(err => {
                console.error('Failed to copy: ', err);
                fallbackCopyToClipboard(jsonString);
            });
        } else {
            fallbackCopyToClipboard(jsonString);
        }
    }

    function fallbackCopyToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.top = '0';
        textArea.style.left = '0';
        textArea.style.width = '2em';
        textArea.style.height = '2em';
        textArea.style.padding = '0';
        textArea.style.border = 'none';
        textArea.style.outline = 'none';
        textArea.style.boxShadow = 'none';
        textArea.style.background = 'transparent';
        document.body.appendChild(textArea);
        textArea.focus();
        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showCustomModal('Success', 'JSON copied to clipboard!', 'success');
            } else {
                showCustomModal('Error', 'Failed to copy JSON', 'error');
            }
        } catch (err) {
            console.error('Fallback copy error:', err);
            showCustomModal('Error', 'Failed to copy JSON', 'error');
        }
        document.body.removeChild(textArea);
    }

    // ============================================
    // USER CONFIGURATION FUNCTIONS
    // ============================================

    function loadAllUsersForManagement() {
        const userListDiv = document.getElementById('default-user-card');
        const userEditBtn = document.getElementById('user-edit-btn');
        const userCopyBtn = document.getElementById('user-copy-btn');
        currentUserId = null;
        currentSourceTable = null;
        currentEditingData = null;
        if (userCopyBtn) {
            userCopyBtn.style.display = 'none';
        }
        if (userEditBtn) {
            userEditBtn.disabled = true;
            userEditBtn.style.opacity = '0.5';
            userEditBtn.title = 'Select a user first';
        }
        if (!userListDiv) return;
        userListDiv.innerHTML = '<div class="loading-spinner-small"><div class="spinner-small"></div><div>Loading users...</div></div>';
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_all_users_for_management'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                userListDiv.innerHTML = '';
                if (data.users.length === 0) {
                    userListDiv.innerHTML = '<div class="info-message-small">No users found in the system</div>';
                    const userJsonViewer = document.querySelector('#user-json-viewer');
                    if (userJsonViewer) {
                        userJsonViewer.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No users available. Add users to the system first.</div>';
                    }
                    return;
                }
                allUsersCache = data.users;
                const randomIndex = Math.floor(Math.random() * data.users.length);
                const defaultUser = data.users[randomIndex];
                displaySingleUser(defaultUser);
                selectUser(defaultUser.id, defaultUser.source, defaultUser.fullname || 'N/A', defaultUser.email || 'N/A');
            } else {
                userListDiv.innerHTML = '<div class="info-message-small" style="color: #e74c3c;">Error loading users</div>';
                showCustomModal('Error', data.error || 'Error loading users', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            userListDiv.innerHTML = '<div class="info-message-small" style="color: #e74c3c;">Error loading users</div>';
            showCustomModal('Error', 'Error loading users', 'error');
        });
    }

    function displaySingleUser(user) {
        const container = document.getElementById('default-user-card');
        if (!container) return;
        container.innerHTML = '';
        const userDiv = document.createElement('div');
        userDiv.className = 'default-user-info';
        userDiv.setAttribute('data-user-id', user.id);
        userDiv.setAttribute('data-source', user.source);
        userDiv.setAttribute('data-fullname', user.fullname || '');
        userDiv.setAttribute('data-email', user.email || '');
        userDiv.setAttribute('data-application-status', user.application_status || '');
        userDiv.onclick = function(e) {
            e.stopPropagation();
            selectUser(user.id, user.source, user.fullname || 'N/A', user.email || 'N/A');
            showAllUsersModal();
        };
        let statusClass = 'status-badge-default';
        let statusText = user.application_status || 'Not Set';
        if (user.application_status === 'approved') statusClass = 'status-badge-approved';
        else if (user.application_status === 'declined') statusClass = 'status-badge-declined';
        else if (user.application_status === 'pending') statusClass = 'status-badge-pending';
        else if (user.application_status === 'suspended') statusClass = 'status-badge-suspended';
        else if (user.application_status === 'blacklisted') statusClass = 'status-badge-blacklisted';
        userDiv.innerHTML = `
            <div class="default-user-name">${escapeHtml(user.fullname || 'N/A')}</div>
            <div class="default-user-email">${escapeHtml(user.email || 'N/A')}</div>
            <div class="default-user-id">ID: ${user.id} | ${user.source} <span class="${statusClass}" style="margin-left: 8px;">${escapeHtml(statusText)}</span></div>
        `;
        container.appendChild(userDiv);
    }

    function showAllUsersModal() {
        const container = document.querySelector('.split-view') || document.querySelector('.account-management-container');
        if (container) {
            container.classList.add('blur-background');
        }
        const modalHtml = `
            <div class="modal-overlay" id="users-modal-overlay" onclick="closeModalIfClickOutside(event)">
                <div class="modal-container users-modal" onclick="event.stopPropagation()">
                    <div class="modal-header">
                        <span>All Users (${allUsersCache.length})</span>
                        <span class="modal-close" onclick="closeModal()">✕</span>
                    </div>
                    <div class="modal-body">
                        <div class="users-modal-search">
                            <input type="text" id="users-modal-search-input" class="user-search-input" placeholder="Search users..." onkeyup="filterModalUsers()">
                        </div>
                    </div>
                    <div class="modal-body users-modal-list" id="users-modal-list">
                        ${renderModalUsersList(allUsersCache)}
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        setTimeout(() => {
            const searchInput = document.getElementById('users-modal-search-input');
            if (searchInput) searchInput.focus();
        }, 100);
    }

    function renderModalUsersList(users) {
        if (!users || users.length === 0) {
            return '<div class="info-message-small">No users found</div>';
        }
        return users.map(user => `
            <div class="modal-user-item ${currentUserId && currentUserId == user.id ? 'selected' : ''}" 
                onclick="selectUserFromModal(${user.id}, '${user.source}')">
                <div class="modal-user-name">${escapeHtml(user.fullname || 'N/A')}</div>
                <div class="modal-user-email">${escapeHtml(user.email || 'N/A')}</div>
                <div class="modal-user-id">ID: ${user.id}</div>
            </div>
        `).join('');
    }

    function filterModalUsers() {
        const searchTerm = document.getElementById('users-modal-search-input').value.toLowerCase();
        const filteredUsers = allUsersCache.filter(user => 
            (user.fullname && user.fullname.toLowerCase().includes(searchTerm)) ||
            (user.email && user.email.toLowerCase().includes(searchTerm)) ||
            user.id.toString().includes(searchTerm)
        );
        const container = document.getElementById('users-modal-list');
        if (container) {
            container.innerHTML = renderModalUsersList(filteredUsers);
        }
    }

    function selectUserFromModal(userId, source) {
        const user = allUsersCache.find(u => u.id == userId);
        if (!user) return;
        selectUser(userId, source, user.fullname || 'N/A', user.email || 'N/A');
        displaySingleUser(user);
        closeModal();
    }

    function closeModal() {
        const overlay = document.getElementById('users-modal-overlay');
        if (overlay) {
            overlay.remove();
        }
        removeBlur();
    }

    function closeModalIfClickOutside(event) {
        if (event.target.id === 'users-modal-overlay') {
            closeModal();
        }
    }

    function selectUser(userId, sourceTable, fullname, email) {
        if (!userId || !sourceTable) {
            showCustomModal('Error', 'Invalid user selection', 'error');
            return;
        }
        currentUserId = userId;
        currentSourceTable = sourceTable;
        currentTargetType = 'user';
        if (isUserConfigExpanded) {
            toggleUserConfigExpand();
        }
        const nameSpan = document.getElementById('selected-user-name');
        const emailSpan = document.getElementById('selected-user-email');
        const sourceSpan = document.getElementById('selected-user-source');
        if (nameSpan) nameSpan.textContent = fullname;
        if (emailSpan) emailSpan.textContent = email;
        if (sourceSpan) sourceSpan.textContent = sourceTable;
        const currentStatus = document.querySelector(`.default-user-info[data-user-id="${userId}"]`)?.getAttribute('data-application-status') || '';
        const statusSpan = document.getElementById('current-application-status');
        const statusSelect = document.getElementById('application-status-select');
        if (statusSpan) {
            let statusClass = 'status-badge-pending';
            if (currentStatus === 'approved') statusClass = 'status-badge-approved';
            else if (currentStatus === 'declined') statusClass = 'status-badge-declined';
            else if (currentStatus === 'suspended') statusClass = 'status-badge-suspended';
            else if (currentStatus === 'blacklisted') statusClass = 'status-badge-blacklisted';
            else if (currentStatus === 'pending') statusClass = 'status-badge-pending';
            else statusClass = 'status-badge-default';
            statusSpan.className = statusClass;
            statusSpan.textContent = currentStatus || 'Not Set';
        }
        if (statusSelect) {
            statusSelect.value = currentStatus || '';
        }
        loadUserAccountManagement(userId, sourceTable);
    }

    function loadUserAccountManagement(userId, sourceTable) {
        if (!userId || !sourceTable) {
            showCustomModal('Error', 'Invalid user selection', 'error');
            return;
        }
        currentUserId = userId;
        currentSourceTable = sourceTable;
        currentTargetType = 'user';
        const editBtn = document.getElementById('user-edit-btn');
        if (editBtn) {
            editBtn.disabled = true;
            editBtn.style.opacity = '0.5';
            editBtn.title = 'Loading user data...';
        }
        const copyBtn = document.getElementById('user-config-copy-btn');
        if (copyBtn) {
            copyBtn.style.display = 'none';
        }
        const userJsonViewer = document.querySelector('#user-json-viewer');
        if (userJsonViewer) {
            userJsonViewer.innerHTML = '<div style="text-align: center; padding: 40px;">Loading user configuration...</div>';
        }
        // FIX: Use .default-user-info instead of .user-item
        const selectedUserItem = document.querySelector(`.default-user-info[data-user-id="${userId}"]`);
        const userFullname = selectedUserItem ? selectedUserItem.getAttribute('data-fullname') : '';
        const userEmail = selectedUserItem ? selectedUserItem.getAttribute('data-email') : '';
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_account_management&user_id=' + encodeURIComponent(userId) + '&source_table=' + encodeURIComponent(sourceTable)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentEditingData = data.data;
                const isEmpty = !data.data || 
                            (typeof data.data === 'object' && Object.keys(data.data).length === 0) ||
                            (Array.isArray(data.data) && data.data.length === 0);
                if (isEmpty) {
                    const container = document.querySelector('#user-json-viewer');
                    if (container) {
                        container.innerHTML = `
                            <div style="text-align: center; padding: 40px; color: #888;">
                                <p>📭 No configuration data yet.</p>
                                <p style="font-size: 12px; margin-top: 10px;">Click "Edit JSON" to create a new configuration.</p>
                            </div>
                        `;
                    }
                    const titleSpan = document.getElementById('user-config-title');
                    if (titleSpan) {
                        titleSpan.innerHTML = '📁 User Configuration';
                    }
                    if (editBtn) {
                        editBtn.disabled = false;
                        editBtn.style.opacity = '1';
                        editBtn.title = 'Create/Edit JSON configuration';
                    }
                    const configCopyBtn = document.getElementById('user-config-copy-btn');
                    if (configCopyBtn) {
                        configCopyBtn.style.display = 'none';
                    }
                } else {
                    let configKeyName = null;
                    if (data.data && typeof data.data === 'object' && Object.keys(data.data).length === 1) {
                        const singleKey = Object.keys(data.data)[0];
                        if (data.data[singleKey] && typeof data.data[singleKey] === 'object') {
                            configKeyName = singleKey;
                        }
                    }
                    displayJsonViewer(data.data, '#user-json-viewer', true, {
                        id: userId,
                        fullname: userFullname,
                        email: userEmail,
                        source: sourceTable
                    }, configKeyName);
                    if (editBtn) {
                        editBtn.disabled = false;
                        editBtn.style.opacity = '1';
                        editBtn.title = 'Edit JSON configuration';
                    }
                }
            } else {
                showCustomModal('Error', data.error || 'Error loading account management', 'error');
                currentEditingData = {};
                const titleSpan = document.getElementById('user-config-title');
                if (titleSpan) {
                    titleSpan.innerHTML = '📁 User Configuration';
                }
                if (editBtn) {
                    editBtn.disabled = false;
                    editBtn.style.opacity = '1';
                    editBtn.title = 'Create JSON configuration (user exists)';
                }
                const configCopyBtn = document.getElementById('user-config-copy-btn');
                if (configCopyBtn) {
                    configCopyBtn.style.display = 'none';
                }
                const container = document.querySelector('#user-json-viewer');
                if (container) {
                    container.innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #888;">
                            <p>${data.error || 'No configuration found'}</p>
                            <p style="font-size: 12px; margin-top: 10px;">Click "Edit JSON" to create a new configuration.</p>
                        </div>
                    `;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showCustomModal('Error', 'Error loading account management', 'error');
            currentEditingData = {};
            const titleSpan = document.getElementById('user-config-title');
            if (titleSpan) {
                titleSpan.innerHTML = '📁 User Configuration';
            }
            if (editBtn) {
                editBtn.disabled = false;
                editBtn.style.opacity = '1';
                editBtn.title = 'Create JSON configuration';
            }
            const configCopyBtn = document.getElementById('user-config-copy-btn');
            if (configCopyBtn) {
                configCopyBtn.style.display = 'none';
            }
        });
    }

    function displayJsonViewer(data, containerId, isUserConfig = false, userInfo = null, configKeyName = null) {
        const container = document.querySelector(containerId);
        if (!container) return;
        container.innerHTML = '';
        if (!data || typeof data !== 'object' || Object.keys(data).length === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">📭 No configuration data available.</div>';
            return;
        }
        if (isUserConfig && userInfo) {
            const configTitle = extractConfigTitle(data, userInfo, configKeyName);
            const titleSpan = document.getElementById('user-config-title');
            if (titleSpan) {
                titleSpan.innerHTML = `📁 ${escapeHtml(configTitle)}`;
            }
            const copyBtn = document.getElementById('user-config-copy-btn');
            if (copyBtn && Object.keys(data).length > 0) {
                copyBtn.style.display = 'inline-block';
            }
        }
        const preElement = document.createElement('pre');
        preElement.className = 'json-structure';
        preElement.textContent = JSON.stringify(data, null, 2);
        container.appendChild(preElement);
    }

    function extractConfigTitle(data, userInfo, configKeyName = null) {
        if (data.configuration_title && typeof data.configuration_title === 'string' && data.configuration_title.trim() !== '') {
            return data.configuration_title;
        }
        if (configKeyName && typeof configKeyName === 'string' && configKeyName.trim() !== '') {
            if (!configKeyName.match(/^configuration_\d+$/) && configKeyName !== 'user_config') {
                return configKeyName;
            }
        }
        for (const [key, value] of Object.entries(data)) {
            if (value && typeof value === 'object') {
                if (value.configuration_title && typeof value.configuration_title === 'string' && value.configuration_title.trim() !== '') {
                    return value.configuration_title;
                }
                if (!key.match(/^config_?\d*$|^settings$|^data$/) && key !== 'user_config' && !key.startsWith('_')) {
                    return key;
                }
            }
        }
        if (userInfo.fullname && userInfo.id) {
            return `${userInfo.fullname} (ID: ${userInfo.id})`;
        } else if (userInfo.fullname) {
            return userInfo.fullname;
        } else if (userInfo.id) {
            return `User ID: ${userInfo.id}`;
        }
        return 'User Configuration';
    }

    function copyUserConfigToClipboard() {
        if (!currentEditingData) {
            showCustomModal('Error', 'No configuration data to copy', 'error');
            return;
        }
        const jsonString = JSON.stringify(currentEditingData, null, 2);
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(jsonString).then(() => {
                const btn = document.getElementById('user-config-copy-btn');
                if (btn) {
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '✓ Copied!';
                    btn.style.background = '#27ae60';
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.style.background = '#9b59b6';
                    }, 2000);
                }
                showCustomModal('Success', 'User configuration copied to clipboard!', 'success');
            }).catch(err => {
                console.error('Failed to copy: ', err);
                fallbackCopyToClipboard(jsonString);
            });
        } else {
            fallbackCopyToClipboard(jsonString);
        }
    }

    function toggleUserConfigExpand() {
        const contentDiv = document.getElementById('user-config-content');
        const iconSpan = document.getElementById('user-config-icon');
        if (!contentDiv || !iconSpan) return;
        if (isUserConfigExpanded) {
            contentDiv.style.display = 'none';
            iconSpan.textContent = '▶';
            isUserConfigExpanded = false;
        } else {
            contentDiv.style.display = 'block';
            iconSpan.textContent = '▼';
            isUserConfigExpanded = true;
        }
    }

    // ============================================
    // APPLICATION STATUS UPDATE FUNCTIONS
    // ============================================

    function updateApplicationStatus() {
        if (!currentUserId || !currentSourceTable) {
            showCustomModal('Error', 'No user selected', 'error');
            return;
        }
        const select = document.getElementById('application-status-select');
        const newStatus = select.value;
        if (!newStatus) {
            showCustomModal('Error', 'Please select a status', 'error');
            return;
        }
        showPasswordModal(
            'Security Verification',
            'Please enter your admin password to update application status.',
            function(password) {
                executeStatusUpdate(password, currentUserId, currentSourceTable, newStatus);
            }
        );
    }

    function executeStatusUpdate(password, userId, sourceTable, newStatus) {
        let formData = new URLSearchParams();
        formData.append('action', 'update_application_status_batch');
        formData.append('user_id', userId);
        formData.append('source_table', sourceTable);
        formData.append('application_status', newStatus);
        formData.append('admin_password', password);
        formData.append('login_id', '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
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
                showCustomModal('Success', `Application status updated to ${newStatus} for User ID ${userId}`, 'success');
                // FIX: Use .default-user-info instead of .user-item
                const userItem = document.querySelector(`.default-user-info[data-user-id="${userId}"]`);
                if (userItem) {
                    userItem.setAttribute('data-application-status', newStatus);
                    let statusClass = 'status-badge-default';
                    if (newStatus === 'approved') statusClass = 'status-badge-approved';
                    else if (newStatus === 'declined') statusClass = 'status-badge-declined';
                    else if (newStatus === 'pending') statusClass = 'status-badge-pending';
                    else if (newStatus === 'suspended') statusClass = 'status-badge-suspended';
                    else if (newStatus === 'blacklisted') statusClass = 'status-badge-blacklisted';
                    const statusSpan = userItem.querySelector('.status-badge-default, .status-badge-approved, .status-badge-declined, .status-badge-pending, .status-badge-suspended, .status-badge-blacklisted');
                    if (statusSpan) {
                        statusSpan.className = statusClass;
                        statusSpan.textContent = newStatus;
                    }
                }
            } else {
                if (data.error === 'Invalid password') {
                    showCustomModal('Error', 'Password verification failed. Please try again.', 'error');
                } else {
                    showCustomModal('Error', data.error || 'Error updating status', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showCustomModal('Error', 'Error updating status', 'error');
        });
    }

    // ============================================
    // SERVER CONFIGURATION FUNCTIONS
    // ============================================

    function loadServerAccountManagement() {
        currentTargetType = 'server';
        currentUserId = null;
        currentSourceTable = null;
        const copyBtn = document.getElementById('server-copy-btn');
        if (copyBtn) {
            copyBtn.style.display = 'inline-block';
        }
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_server_account_management'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentEditingData = data.data;
                displayJsonViewer(data.data, '#server-json-viewer');
            } else {
                showCustomModal('Error', data.error || 'Error loading server account management', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showCustomModal('Error', 'Error loading server account management', 'error');
        });
    }

    function toggleServerConfigExpand() {
        const contentDiv = document.getElementById('server-config-content');
        const iconSpan = document.getElementById('server-config-icon');
        if (!contentDiv || !iconSpan) return;
        if (isServerConfigExpanded) {
            contentDiv.style.display = 'none';
            iconSpan.textContent = '▶';
            isServerConfigExpanded = false;
        } else {
            contentDiv.style.display = 'block';
            iconSpan.textContent = '▼';
            isServerConfigExpanded = true;
        }
    }

    // ============================================
    // ACCOUNT MANAGEMENT CONFIG FUNCTIONS
    // ============================================

    function loadAccountManagementConfigs() {
        const container = document.getElementById('config-entries-container');
        if (!container) return;
        container.innerHTML = '<div style="text-align: center; padding: 40px;">Loading configurations...</div>';
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_config_entry&target_type=server&entry_key=all'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.all_data) {
                displayConfigEntries(data.all_data);
            } else if (data.success && data.data) {
                displayConfigEntries(data.data);
            } else {
                container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No configurations found. Click "Add New Configuration" to create one.</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading configurations. Please refresh the page.</div>';
            showCustomModal('Error', 'Error loading configurations', 'error');
        });
    }

    function displayConfigEntries(configs) {
        const container = document.getElementById('config-entries-container');
        if (!container) return;
        let configsData = configs;
        if (configs && configs.all_data) {
            configsData = configs.all_data;
        }
        if (!configsData || typeof configsData !== 'object' || Object.keys(configsData).length === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No configurations found. Click "Add New Configuration" to create one.</div>';
            return;
        }
        container.innerHTML = '';
        Object.entries(configsData).forEach(([key, value]) => {
            setCachedConfigData(key, value);
            const card = document.createElement('div');
            card.className = 'config-entry-card';
            card.id = `config-card-${key.replace(/[^a-zA-Z0-9]/g, '_')}`;
            const displayValue = value || {};
            const isExpanded = false;
            card.innerHTML = `
                <div class="config-entry-header" onclick="toggleConfigExpand('${escapeHtml(key).replace(/'/g, "\\'")}')">
                    <div class="config-entry-title-wrapper">
                        <span class="collapse-icon" id="icon-${key.replace(/[^a-zA-Z0-9]/g, '_')}">${isExpanded ? '▼' : '▶'}</span>
                        <span class="config-entry-title">📄 ${escapeHtml(key)}</span>
                    </div>
                    <div class="config-entry-buttons" id="buttons-${key.replace(/[^a-zA-Z0-9]/g, '_')}" onclick="event.stopPropagation()">
                        <button class="edit-config-btn" onclick="editConfigEntry('${escapeHtml(key).replace(/'/g, "\\'")}')">✏️ Edit</button>
                        <button class="copy-config-btn" onclick="copyConfigEntry('${escapeHtml(key).replace(/'/g, "\\'")}')">📋 Copy</button>
                        <button class="delete-config-btn" onclick="deleteConfigEntry('${escapeHtml(key).replace(/'/g, "\\'")}')">🗑️ Delete</button>
                    </div>
                </div>
                <div class="config-entry-content" id="content-${key.replace(/[^a-zA-Z0-9]/g, '_')}" style="display: none;">
                    <pre class="config-json-view">${escapeHtml(JSON.stringify(displayValue, null, 2))}</pre>
                </div>
            `;
            container.appendChild(card);
        });
    }

    function toggleConfigExpand(entryKey) {
        const safeKey = entryKey.replace(/[^a-zA-Z0-9]/g, '_');
        const contentDiv = document.getElementById(`content-${safeKey}`);
        const iconSpan = document.getElementById(`icon-${safeKey}`);
        if (!contentDiv || !iconSpan) return;
        if (currentlyExpandedConfig && currentlyExpandedConfig !== entryKey) {
            const prevSafeKey = currentlyExpandedConfig.replace(/[^a-zA-Z0-9]/g, '_');
            const prevContentDiv = document.getElementById(`content-${prevSafeKey}`);
            const prevIconSpan = document.getElementById(`icon-${prevSafeKey}`);
            if (prevContentDiv) {
                prevContentDiv.style.display = 'none';
            }
            if (prevIconSpan) {
                prevIconSpan.textContent = '▶';
            }
        }
        if (contentDiv.style.display === 'none') {
            contentDiv.style.display = 'block';
            iconSpan.textContent = '▼';
            currentlyExpandedConfig = entryKey;
        } else {
            contentDiv.style.display = 'none';
            iconSpan.textContent = '▶';
            currentlyExpandedConfig = null;
        }
    }

    function editConfigEntry(entryKey) {
        if (currentEditingConfigEntry) {
            showCustomModal('Error', 'Please save or cancel the current edit first', 'error');
            return;
        }
        currentEditingConfigEntry = entryKey;
        const safeKey = entryKey.replace(/[^a-zA-Z0-9]/g, '_');
        const contentDiv = document.getElementById(`content-${safeKey}`);
        const iconSpan = document.getElementById(`icon-${safeKey}`);
        if (contentDiv && contentDiv.style.display === 'none') {
            contentDiv.style.display = 'block';
            if (iconSpan) iconSpan.textContent = '▼';
            currentlyExpandedConfig = entryKey;
        }
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_config_entry&target_type=server&entry_key=' + encodeURIComponent(entryKey)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                originalConfigEntryBackup = {
                    key: entryKey,
                    data: JSON.parse(JSON.stringify(data.data))
                };
                if (contentDiv) {
                    contentDiv.innerHTML = `
                        <div style="margin-bottom: 10px;">
                            <label style="font-weight: bold; display: block; margin-bottom: 5px;">Configuration Key Name:</label>
                            <input type="text" id="key-editor-${safeKey}" class="config-key-editor" value="${escapeHtml(entryKey)}" style="width: 100%; padding: 8px; margin-bottom: 15px; background: var(--input-bg); border: 1px solid var(--border-color); color: var(--text-color); border-radius: 6px;">
                        </div>
                        <label style="font-weight: bold; display: block; margin-bottom: 5px;">Configuration Data (JSON):</label>
                        <textarea class="config-json-editor" id="editor-${safeKey}">${escapeHtml(JSON.stringify(data.data, null, 2))}</textarea>
                    `;
                }
                const buttonsDiv = document.getElementById(`buttons-${safeKey}`);
                if (buttonsDiv) {
                    buttonsDiv.innerHTML = `
                        <button class="save-config-btn" onclick="saveConfigEntry('${escapeHtml(entryKey).replace(/'/g, "\\'")}')">💾 Save</button>
                        <button class="cancel-config-btn" onclick="cancelConfigEntry('${escapeHtml(entryKey).replace(/'/g, "\\'")}')">❌ Cancel</button>
                    `;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showCustomModal('Error', 'Error loading entry data', 'error');
            currentEditingConfigEntry = null;
        });
    }

    function cancelConfigEntry(entryKey) {
        if (!currentEditingConfigEntry || currentEditingConfigEntry !== entryKey) {
            showCustomModal('Error', 'No active edit for this entry', 'error');
            return;
        }
        const safeKey = entryKey.replace(/[^a-zA-Z0-9]/g, '_');
        const contentDiv = document.getElementById(`content-${safeKey}`);
        const buttonsDiv = document.getElementById(`buttons-${safeKey}`);
        if (contentDiv && originalConfigEntryBackup) {
            contentDiv.innerHTML = `
                <pre class="config-json-view">${escapeHtml(JSON.stringify(originalConfigEntryBackup.data, null, 2))}</pre>
            `;
            if (currentlyExpandedConfig === entryKey) {
                contentDiv.style.display = 'block';
                const iconSpan = document.getElementById(`icon-${safeKey}`);
                if (iconSpan) iconSpan.textContent = '▼';
            }
        }
        if (buttonsDiv) {
            buttonsDiv.innerHTML = `
                <button class="edit-config-btn" onclick="editConfigEntry('${escapeHtml(entryKey).replace(/'/g, "\\'")}')">✏️ Edit</button>
                <button class="copy-config-btn" onclick="copyConfigEntry('${escapeHtml(entryKey).replace(/'/g, "\\'")}')">📋 Copy</button>
                <button class="delete-config-btn" onclick="deleteConfigEntry('${escapeHtml(entryKey).replace(/'/g, "\\'")}')">🗑️ Delete</button>
            `;
        }
        currentEditingConfigEntry = null;
        originalConfigEntryBackup = null;
        showCustomModal('Success', 'Edit cancelled', 'success');
    }

    function saveConfigEntry(entryKey) {
        if (!currentEditingConfigEntry || currentEditingConfigEntry !== entryKey) {
            showCustomModal('Error', 'No active edit for this entry', 'error');
            return;
        }
        const safeKey = entryKey.replace(/[^a-zA-Z0-9]/g, '_');
        const editor = document.getElementById(`editor-${safeKey}`);
        const keyEditor = document.getElementById(`key-editor-${safeKey}`);
        if (!editor) {
            showCustomModal('Error', 'Editor not found', 'error');
            return;
        }
        let newValue;
        try {
            newValue = JSON.parse(editor.value);
        } catch (e) {
            showCustomModal('Invalid JSON', 'Error parsing JSON: ' + e.message, 'error');
            return;
        }
        let newKey = entryKey;
        if (keyEditor) {
            newKey = keyEditor.value.trim();
            if (!newKey) {
                showCustomModal('Error', 'Configuration key cannot be empty', 'error');
                return;
            }
            if (!/^[a-zA-Z0-9_\-]+$/.test(newKey)) {
                showCustomModal('Error', 'Key can only contain letters, numbers, underscores, and hyphens', 'error');
                return;
            }
        }
        
        showPasswordModal(
            'Security Verification',
            'Please enter your admin password to save this configuration.',
            function(password) {
                executeSaveConfigEntry(password, entryKey, newKey, newValue);
            }
        );
    }

    function executeSaveConfigEntry(password, oldKey, newKey, newValue) {
        function performSave(deleteOldKey, createNewKey, valueToSave) {
            let formData = new URLSearchParams();
            formData.append('action', 'update_config_entry');
            formData.append('target_type', 'server');
            if (deleteOldKey && createNewKey) {
                let deleteFormData = new URLSearchParams();
                deleteFormData.append('action', 'update_config_entry');
                deleteFormData.append('target_type', 'server');
                deleteFormData.append('entry_key', deleteOldKey);
                deleteFormData.append('value', 'null');
                deleteFormData.append('admin_password', password);
                deleteFormData.append('login_id', '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
                fetch('serveraccount.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: deleteFormData
                })
                .then(response => response.json())
                .then(deleteData => {
                    if (deleteData.success) {
                        let createFormData = new URLSearchParams();
                        createFormData.append('action', 'update_config_entry');
                        createFormData.append('target_type', 'server');
                        createFormData.append('entry_key', createNewKey);
                        createFormData.append('value', JSON.stringify(valueToSave));
                        createFormData.append('admin_password', password);
                        createFormData.append('login_id', '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
                        return fetch('serveraccount.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: createFormData
                        });
                    } else {
                        throw new Error(deleteData.error || 'Error deleting old entry');
                    }
                })
                .then(response => response.json())
                .then(createData => {
                    if (createData.success) {
                        let message = `Configuration renamed from "${deleteOldKey}" to "${createNewKey}" and saved successfully!`;
                        if (createData.synced_to_management) {
                            message += ' (Synced to accountmanagement)';
                        }
                        showCustomModal('Success', message, 'success');
                        clearConfigEntryEditState(deleteOldKey);
                        loadAccountManagementConfigs();
                    } else {
                        showCustomModal('Error', createData.error || 'Error creating new entry', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showCustomModal('Error', 'Error saving configuration: ' + error.message, 'error');
                    if (error.message === 'Invalid password') {
                        showCustomModal('Error', 'Password verification failed. Please try again.', 'error');
                    }
                })
                .finally(() => {
                    window.pendingConfigSave = null;
                });
                return;
            }
            formData.append('entry_key', oldKey);
            formData.append('value', JSON.stringify(valueToSave));
            formData.append('admin_password', password);
            formData.append('login_id', '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
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
                    let message = `Configuration "${oldKey}" saved successfully!`;
                    if (data.synced_to_management) {
                        message += ' (Synced to accountmanagement)';
                    }
                    showCustomModal('Success', message, 'success');
                    clearConfigEntryEditState(oldKey);
                    loadAccountManagementConfigs();
                } else {
                    if (data.error === 'Invalid password') {
                        showCustomModal('Error', 'Password verification failed. Please try again.', 'error');
                    } else {
                        showCustomModal('Error', data.error || 'Error saving configuration', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showCustomModal('Error', 'Error saving configuration', 'error');
            })
            .finally(() => {
                window.pendingConfigSave = null;
            });
        }
        if (oldKey !== newKey) {
            performSave(oldKey, newKey, newValue);
        } else {
            performSave(null, null, newValue);
        }
    }

    function clearConfigEntryEditState(entryKey) {
        if (!currentEditingConfigEntry || currentEditingConfigEntry !== entryKey) {
            return;
        }
        const safeKey = entryKey.replace(/[^a-zA-Z0-9]/g, '_');
        const contentDiv = document.getElementById(`content-${safeKey}`);
        const buttonsDiv = document.getElementById(`buttons-${safeKey}`);
        if (contentDiv && originalConfigEntryBackup) {
            contentDiv.innerHTML = `
                <pre class="config-json-view">${escapeHtml(JSON.stringify(originalConfigEntryBackup.data, null, 2))}</pre>
            `;
            if (currentlyExpandedConfig === entryKey) {
                contentDiv.style.display = 'block';
                const iconSpan = document.getElementById(`icon-${safeKey}`);
                if (iconSpan) iconSpan.textContent = '▼';
            }
        }
        if (buttonsDiv) {
            buttonsDiv.innerHTML = `
                <button class="edit-config-btn" onclick="editConfigEntry('${escapeHtml(entryKey).replace(/'/g, "\\'")}')">✏️ Edit</button>
                <button class="copy-config-btn" onclick="copyConfigEntry('${escapeHtml(entryKey).replace(/'/g, "\\'")}')">📋 Copy</button>
                <button class="delete-config-btn" onclick="deleteConfigEntry('${escapeHtml(entryKey).replace(/'/g, "\\'")}')">🗑️ Delete</button>
            `;
        }
        currentEditingConfigEntry = null;
        originalConfigEntryBackup = null;
    }

    function getCachedConfigData(entryKey, forceRefresh = false) {
        const now = Date.now();
        const cacheExpiry = 30000;
        if (!forceRefresh && 
            configDataCache[entryKey] && 
            configDataCacheTimestamp[entryKey] && 
            (now - configDataCacheTimestamp[entryKey]) < cacheExpiry) {
            return configDataCache[entryKey];
        }
        return null;
    }

    function setCachedConfigData(entryKey, data) {
        configDataCache[entryKey] = data;
        configDataCacheTimestamp[entryKey] = Date.now();
    }

    function clearConfigCache() {
        configDataCache = {};
        configDataCacheTimestamp = {};
    }

    function copyConfigEntry(entryKey) {
        const safeKey = entryKey.replace(/[^a-zA-Z0-9]/g, '_');
        const contentDiv = document.getElementById(`content-${safeKey}`);
        if (!contentDiv) {
            showCustomModal('Error', 'Configuration content not found', 'error');
            return;
        }
        let preElement = contentDiv.querySelector('.config-json-view');
        let dataValue = null;
        if (preElement) {
            dataValue = preElement.textContent;
        } else {
            let textarea = contentDiv.querySelector('.config-json-editor');
            if (textarea) {
                dataValue = textarea.value;
            }
        }
        if (!dataValue) {
            showCustomModal('Error', 'No configuration data found to copy', 'error');
            return;
        }
        let parsedData;
        try {
            parsedData = JSON.parse(dataValue);
        } catch (e) {
            showCustomModal('Error', 'Invalid JSON format in configuration', 'error');
            return;
        }
        const fullJsonObject = {
            [entryKey]: parsedData
        };
        const jsonString = JSON.stringify(fullJsonObject, null, 2);
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(jsonString).then(() => {
                showCustomModal('Success', `Configuration "${entryKey}" (with key) copied to clipboard!`, 'success');
                const buttons = document.querySelectorAll(`.copy-config-btn`);
                buttons.forEach(btn => {
                    if (btn.parentElement.parentElement.querySelector('.config-entry-title')?.textContent.includes(entryKey)) {
                        const originalText = btn.innerHTML;
                        btn.innerHTML = '✓ Copied!';
                        btn.style.background = '#27ae60';
                        setTimeout(() => {
                            btn.innerHTML = originalText;
                            btn.style.background = '#9b59b6';
                        }, 2000);
                    }
                });
            }).catch(() => {
                fallbackCopyToClipboard(jsonString);
                showCustomModal('Success', `Configuration "${entryKey}" (with key) copied to clipboard!`, 'success');
            });
        } else {
            fallbackCopyToClipboard(jsonString);
            showCustomModal('Success', `Configuration "${entryKey}" (with key) copied to clipboard!`, 'success');
        }
    }

    function deleteConfigEntry(entryKey) {
        showCustomConfirm(
            'Confirm Delete',
            `Are you sure you want to delete the configuration "${entryKey}"? This action cannot be undone.`,
            'Delete',
            'Cancel',
            function() {
                showPasswordModal(
                    'Security Verification',
                    'Please enter your admin password to delete this configuration.',
                    function(password) {
                        executeDeleteConfigEntry(password, entryKey);
                    }
                );
            }
        );
    }

    function executeDeleteConfigEntry(password, entryKey) {
        let formData = new URLSearchParams();
        formData.append('action', 'update_config_entry');
        formData.append('target_type', 'server');
        formData.append('entry_key', entryKey);
        formData.append('value', 'null');
        formData.append('admin_password', password);
        formData.append('login_id', '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
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
                showCustomModal('Success', `Configuration "${entryKey}" deleted successfully!`, 'success');
                loadAccountManagementConfigs();
            } else {
                if (data.error === 'Invalid password') {
                    showCustomModal('Error', 'Password verification failed. Please try again.', 'error');
                } else {
                    showCustomModal('Error', data.error || 'Error deleting configuration', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showCustomModal('Error', 'Error deleting configuration', 'error');
        });
    }

    function showAddConfigEntryModal() {
        // Remove any existing modal
        const existingModal = document.getElementById('add-config-modal');
        if (existingModal) {
            existingModal.remove();
        }

        const modalHtml = `
            <div class="modal-overlay" id="add-config-modal" onclick="document.getElementById('add-config-modal').style.display='none'">
                <div class="modal-container" onclick="event.stopPropagation()" style="max-width: 500px;">
                    <div class="modal-header">
                        <span>➕ Add New Configuration</span>
                        <span class="modal-close" onclick="document.getElementById('add-config-modal').style.display='none'">✕</span>
                    </div>
                    <div class="modal-body">
                        <p style="margin-bottom: 10px;">Enter a unique key name for this configuration:</p>
                        <input type="text" id="new-config-key" placeholder="e.g., configuration_3, my_custom_settings, etc." style="width: 100%; padding: 12px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-color); border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px;">
                            <button class="modal-cancel-btn" onclick="document.getElementById('add-config-modal').style.display='none'" style="padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; background: #e74c3c; color: white;">Cancel</button>
                            <button class="modal-confirm-btn" id="add-config-confirm" style="padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; background: #27ae60; color: white;">Create</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modal = document.getElementById('add-config-modal');
        modal.style.display = 'flex';
        const input = document.getElementById('new-config-key');
        input.focus();

        const confirmBtn = document.getElementById('add-config-confirm');
        confirmBtn.addEventListener('click', function() {
            const key = document.getElementById('new-config-key')?.value.trim();
            if (!key) {
                showCustomModal('Error', 'Please enter a configuration key', 'error');
                input.focus();
                return;
            }
            modal.style.display = 'none';
            createNewConfigEntry(key);
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                confirmBtn.click();
            }
        });
    }

    function createNewConfigEntry(entryKey) {
        const defaultTemplate = {
            "selected_risk_reward": [3],
            "hedge_orders_risk_reward": [2],
            "timeframe": ["15m"],
            "bars": 3,
            "settings": {
                "enable_auto_trading": true,
                "place_grid_trades": true,
                "enable_breakeven": true
            }
        };
        
        showPasswordModal(
            'Security Verification',
            'Please enter your admin password to create the new configuration.',
            function(password) {
                executeCreateNewConfigEntry(password, entryKey, defaultTemplate);
            }
        );
    }

    function executeCreateNewConfigEntry(password, entryKey, template) {
        let formData = new URLSearchParams();
        formData.append('action', 'update_config_entry');
        formData.append('target_type', 'server');
        formData.append('entry_key', entryKey);
        formData.append('value', JSON.stringify(template));
        formData.append('admin_password', password);
        formData.append('login_id', '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
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
                showCustomModal('Success', `Configuration "${entryKey}" created successfully!`, 'success');
                loadAccountManagementConfigs();
            } else {
                if (data.error === 'Invalid password') {
                    showCustomModal('Error', 'Password verification failed. Please try again.', 'error');
                } else {
                    showCustomModal('Error', data.error || 'Error creating configuration', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showCustomModal('Error', 'Error creating configuration', 'error');
        });
    }

    // ============================================
    // USER TAB FUNCTIONS (Active Investors, Pending, etc.)
    // ============================================

    function loadVerifiedUsers() {
        const container = document.getElementById('verified-users-list');
        if (!container) return;
        container.innerHTML = '<div style="text-align: center; padding: 40px;">Loading Active Investors...</div>';
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_verified_users'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayUserTable(container, data.users, 'verified');
            } else {
                container.innerHTML = `<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading Active Investors: ${data.error || 'Unknown error'}</div>`;
                showCustomModal('Error', data.error || 'Error loading Active Investors', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading Active Investors</div>';
            showCustomModal('Error', 'Error loading Active Investors', 'error');
        });
    }

    function loadPendingUsers() {
        const container = document.getElementById('pending-users-list');
        if (!container) return;
        container.innerHTML = '<div style="text-align: center; padding: 40px;">Loading pending users...</div>';
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_pending_users'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayUserTable(container, data.users, 'pending');
            } else {
                container.innerHTML = `<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading pending users: ${data.error || 'Unknown error'}</div>`;
                showCustomModal('Error', data.error || 'Error loading pending users', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading pending users</div>';
            showCustomModal('Error', 'Error loading pending users', 'error');
        });
    }

    function loadSuspendedUsers() {
        const container = document.getElementById('suspended-users-list');
        if (!container) return;
        container.innerHTML = '<div style="text-align: center; padding: 40px;">Loading suspended users...</div>';
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_suspended_users'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayUserTable(container, data.users, 'suspended');
            } else {
                container.innerHTML = `<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading suspended users: ${data.error || 'Unknown error'}</div>`;
                showCustomModal('Error', data.error || 'Error loading suspended users', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading suspended users</div>';
            showCustomModal('Error', 'Error loading suspended users', 'error');
        });
    }

    function loadJustJoinedUsers() {
        const container = document.getElementById('justjoined-users-list');
        if (!container) return;
        container.innerHTML = '<div style="text-align: center; padding: 40px;">Loading just joined users...</div>';
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_just_joined_users'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayUserTable(container, data.users, 'justjoined');
            } else {
                container.innerHTML = `<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading just joined users: ${data.error || 'Unknown error'}</div>`;
                showCustomModal('Error', data.error || 'Error loading just joined users', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading just joined users</div>';
            showCustomModal('Error', 'Error loading just joined users', 'error');
        });
    }

    function loadJustJoinedValidUsers() {
        const container = document.getElementById('justjoinedvalid-users-list');
        if (!container) return;
        container.innerHTML = '<div style="text-align: center; padding: 40px;">Loading users...</div>';
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_just_joined_valid_users'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayJustJoinedValidUserTable(container, data.users, 'justjoinedvalid');
            } else {
                container.innerHTML = `<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading users: ${data.error || 'Unknown error'}</div>`;
                showCustomModal('Error', data.error || 'Error loading users', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading users</div>';
            showCustomModal('Error', 'Error loading users', 'error');
        });
    }

    function displayJustJoinedValidUserTable(container, users, type) {
        if (!users || users.length === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No users found with just-joined-and-valid_credentials status</div>';
            return;
        }
        let html = `
            <div class="user-count-badge">Total: <span id="${type}-users-count">${users.length}</span> users</div>
            <div class="table-responsive">
                <table class="user-view-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Broker</th>
                            <th>Source</th>
                            <th>Invested With</th>
                            <th>Execution Start Date</th>
                            <th>Contract Days Left</th>
                            <th>Autotrading</th>
                            <th>Demo Account</th>
                            <th>Account Mode</th>
                            <th>Terminal Path</th>
                            <th>Broker Balance</th>
                            <th>Application Status</th>
                            <th>Update Status</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        users.forEach(user => {
            const autoTradingStatus = user.enable_autotrading == 1 ? 'Enabled' : 'Disabled';
            const autoTradingClass = user.enable_autotrading == 1 ? 'status-enabled' : 'status-disabled';
            const demoAccountStatus = user.demo_account == 1 ? 'Yes' : 'No';
            const demoAccountClass = user.demo_account == 1 ? 'status-enabled' : 'status-disabled';
            const contractDaysLeft = user.contract_days_left !== null && user.contract_days_left !== undefined ? user.contract_days_left : '-';
            const terminalPath = user.terminal_path || user.Terminal_path || '-';
            let currentStatus = user.application_status || 'just-joined-and-valid_credentials';
            let statusClass = 'status-badge-pending';
            if (currentStatus === 'approved') statusClass = 'status-badge-approved';
            else if (currentStatus === 'declined') statusClass = 'status-badge-declined';
            else if (currentStatus === 'suspended') statusClass = 'status-badge-suspended';
            else if (currentStatus === 'pending') statusClass = 'status-badge-pending';
            html += `
                <tr class="user-data-row" 
                    data-user-id="${escapeHtml(user.id)}" 
                    data-source="${escapeHtml(user.source)}"
                    data-email="${escapeHtml(user.email || '').toLowerCase()}" 
                    data-fullname="${escapeHtml(user.fullname || '').toLowerCase()}">
                    <td>${escapeHtml(user.id)}</td>
                    <td><strong>${escapeHtml(user.fullname || 'N/A')}</strong></td>
                    <td>${escapeHtml(user.email || 'N/A')}</td>
                    <td>${escapeHtml(user.broker || 'N/A')}</td>
                    <td><span class="source-badge">${escapeHtml(user.source)}</span></td>
                    <td><code class="invested-value">${escapeHtml(user.invested_with || '-')}</code></td>
                    <td>${escapeHtml(user.execution_start_date || '-')}</td>
                    <td class="contract-days-cell">${contractDaysLeft}</td>
                    <td><span class="${autoTradingClass}">${autoTradingStatus}</span></td>
                    <td><span class="${demoAccountClass}">${demoAccountStatus}</span></td>
                    <td><span class="mode-badge ${(user.account_mode || '').toLowerCase() === 'demo' ? 'mode-demo' : 'mode-real'}">${escapeHtml(user.account_mode || 'N/A')}</span></td>
                    <td><code class="terminal-path-value" title="${escapeHtml(terminalPath)}">${escapeHtml(terminalPath)}</code></td>
                    <td class="balance-cell">${user.broker_balance ? '$' + parseFloat(user.broker_balance).toFixed(2) : '-'}</td>
                    <td><span class="${statusClass}">${escapeHtml(currentStatus)}</span></td>
                    <td>
                        <select class="status-update-select" data-user-id="${escapeHtml(user.id)}" data-source="${escapeHtml(user.source)}">
                            <option value="">Select Status</option>
                            <option value="approved">Approve</option>
                            <option value="declined">Decline</option>
                            <option value="suspended">Suspend</option>
                        </select>
                        <button class="update-status-from-table-btn" data-user-id="${escapeHtml(user.id)}" data-source="${escapeHtml(user.source)}" data-current-status="${escapeHtml(currentStatus)}">Update</button>
                    </td>
                </tr>
            `;
        });
        html += `
                        </tbody>
                    </table>
                </div>
            `;
        container.innerHTML = html;
        document.querySelectorAll('#justjoinedvalid-users-list .update-status-from-table-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const sourceTable = this.getAttribute('data-source');
                const row = this.closest('tr');
                const select = row.querySelector('.status-update-select');
                const newStatus = select.value;
                if (!newStatus) {
                    showCustomModal('Error', 'Please select a status', 'error');
                    return;
                }
                showPasswordModal(
                    'Security Verification',
                    'Please enter your admin password to update application status.',
                    function(password) {
                        executeJustJoinedStatusUpdate(password, userId, sourceTable, newStatus, row);
                    }
                );
            });
        });
    }

    function executeJustJoinedStatusUpdate(password, userId, sourceTable, newStatus, row) {
        let formData = new URLSearchParams();
        formData.append('action', 'update_application_status_batch');
        formData.append('user_id', userId);
        formData.append('source_table', sourceTable);
        formData.append('application_status', newStatus);
        formData.append('admin_password', password);
        formData.append('login_id', '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
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
                showCustomModal('Success', `Application status updated to ${newStatus} for User ID ${userId}`, 'success');
                const statusCell = row.querySelector('td:nth-child(14)');
                let statusClass = 'status-badge-pending';
                if (newStatus === 'approved') statusClass = 'status-badge-approved';
                else if (newStatus === 'declined') statusClass = 'status-badge-declined';
                else if (newStatus === 'suspended') statusClass = 'status-badge-suspended';
                if (statusCell) {
                    statusCell.innerHTML = `<span class="${statusClass}">${escapeHtml(newStatus)}</span>`;
                }
                const select = row.querySelector('.status-update-select');
                const button = row.querySelector('.update-status-from-table-btn');
                if (select) select.disabled = true;
                if (button) button.disabled = true;
                // FIX: Use .default-user-info instead of .user-item
                const userItem = document.querySelector(`.default-user-info[data-user-id="${userId}"]`);
                if (userItem) {
                    userItem.setAttribute('data-application-status', newStatus);
                    let userStatusClass = 'status-badge-default';
                    if (newStatus === 'approved') userStatusClass = 'status-badge-approved';
                    else if (newStatus === 'declined') userStatusClass = 'status-badge-declined';
                    else if (newStatus === 'suspended') userStatusClass = 'status-badge-suspended';
                    else if (newStatus === 'pending') userStatusClass = 'status-badge-pending';
                    else if (newStatus === 'blacklisted') userStatusClass = 'status-badge-blacklisted';
                    const statusSpan = userItem.querySelector('.status-badge-default, .status-badge-approved, .status-badge-declined, .status-badge-pending, .status-badge-suspended, .status-badge-blacklisted');
                    if (statusSpan) {
                        statusSpan.className = userStatusClass;
                        statusSpan.textContent = newStatus;
                    }
                }
            } else {
                if (data.error === 'Invalid password') {
                    showCustomModal('Error', 'Password verification failed. Please try again.', 'error');
                } else {
                    showCustomModal('Error', data.error || 'Error updating status', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showCustomModal('Error', 'Error updating status', 'error');
        });
    }

    function loadApprovedUsers() {
        const container = document.getElementById('approved-users-list');
        if (!container) return;
        container.innerHTML = '<div style="text-align: center; padding: 40px;">Loading approved users...</div>';
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_approved_users'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayUserTable(container, data.users, 'approved');
            } else {
                container.innerHTML = `<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading approved users: ${data.error || 'Unknown error'}</div>`;
                showCustomModal('Error', data.error || 'Error loading approved users', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading approved users</div>';
            showCustomModal('Error', 'Error loading approved users', 'error');
        });
    }

    function loadBypassedUsers() {
        const container = document.getElementById('bypassed-users-list');
        if (!container) return;
        container.innerHTML = '<div style="text-align: center; padding: 40px;">Loading bypassed users...</div>';
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_bypassed_users'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayBypassedUserTable(container, data.users, 'bypassed');
            } else {
                container.innerHTML = `<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading bypassed users: ${data.error || 'Unknown error'}</div>`;
                showCustomModal('Error', data.error || 'Error loading bypassed users', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading bypassed users</div>';
            showCustomModal('Error', 'Error loading bypassed users', 'error');
        });
    }

    function displayBypassedUserTable(container, users, type) {
        if (!users || users.length === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No users found with bypass restriction enabled</div>';
            return;
        }
        let html = `
            <div class="user-count-badge">Total: <span id="${type}-users-count">${users.length}</span> users with bypass restriction enabled</div>
            <div class="table-responsive">
                <table class="user-view-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Source</th>
                            <th>Invested With</th>
                            <th>Execution Start Date</th>
                            <th>Autotrading</th>
                            <th>Bypass Restriction</th>
                            <th>Broker Balance</th>
                            <th>Account Mode</th>
                            <th>Demo Account</th>
                            <th>Unauthorized Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        users.forEach(user => {
            const autoTradingStatus = user.enable_autotrading == 1 ? 'Enabled' : 'Disabled';
            const autoTradingClass = user.enable_autotrading == 1 ? 'status-enabled' : 'status-disabled';
            const demoAccountStatus = user.demo_account == 1 ? 'Yes' : 'No';
            const demoAccountClass = user.demo_account == 1 ? 'status-enabled' : 'status-disabled';
            let unauthorizedStatus = '';
            let unauthorizedClass = '';
            if (user.unauthorized_actions == 1) {
                unauthorizedStatus = '1';
                unauthorizedClass = 'unauthorized-present';
            } else if (user.unauthorized_actions == 0) {
                unauthorizedStatus = '0';
                unauthorizedClass = 'unauthorized-none';
            } else {
                unauthorizedStatus = '0';
                unauthorizedClass = 'unauthorized-none';
            }
            html += `
                <tr class="user-data-row" 
                    data-user-id="${escapeHtml(user.id)}" 
                    data-email="${escapeHtml(user.email || '').toLowerCase()}" 
                    data-fullname="${escapeHtml(user.fullname || '').toLowerCase()}">
                    <td>${escapeHtml(user.id)}</td>
                    <td><strong>${escapeHtml(user.fullname || 'N/A')}</strong></td>
                    <td>${escapeHtml(user.email || 'N/A')}</td>
                    <td><span class="source-badge">${escapeHtml(user.source)}</span></td>
                    <td><code class="invested-value">${escapeHtml(user.invested_with || '-')}</code></td>
                    <td>${escapeHtml(user.execution_start_date || '-')}</td>
                    <td><span class="${autoTradingClass}">${autoTradingStatus}</span></td>
                    <td><span class="bypass-enabled-badge">Enabled (1)</span></td>
                    <td class="balance-cell">${user.broker_balance ? '$' + parseFloat(user.broker_balance).toFixed(2) : '-'}</td>
                    <td><span class="mode-badge ${user.account_mode === 'demo' ? 'mode-demo' : 'mode-real'}">${escapeHtml(user.account_mode || 'N/A')}</span></td>
                    <td><span class="${demoAccountClass}">${demoAccountStatus}</span></td>
                    <td class="unauthorized-cell">
                        <span class="unauthorized-badge ${unauthorizedClass}">${unauthorizedStatus}</span>
                    </td>
                </tr>
            `;
        });
        html += `
                    </tbody>
                </table>
            </div>
        `;
        container.innerHTML = html;
    }

    function displayUserTable(container, users, type) {
        if (!users || users.length === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No users found</div>';
            return;
        }
        let html = `
            <div class="user-count-badge">Total: <span id="${type}-users-count">${users.length}</span> users</div>
            <div class="table-responsive">
                <table class="user-view-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Broker</th>
                            <th>Source</th>
                            <th>Invested With</th>
                            <th>Broker Balance</th>
                            <th>Execution Start Date</th>
                            <th>Contract Days Left</th>
                            <th>Autotrading</th>
                            <th>Demo Account</th>
                            <th>Account Mode</th>
                            <th>Terminal Path</th>
                            <th style="display: none">Action</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        users.forEach(user => {
            const autoTradingStatus = user.enable_autotrading == 1 ? 'Enabled' : 'Disabled';
            const autoTradingClass = user.enable_autotrading == 1 ? 'status-enabled' : 'status-disabled';
            const demoAccountStatus = user.demo_account == 1 ? 'Yes' : 'No';
            const demoAccountClass = user.demo_account == 1 ? 'status-enabled' : 'status-disabled';
            const contractDaysLeft = user.contract_days_left !== null && user.contract_days_left !== undefined ? user.contract_days_left : '-';
            const terminalPath = user.terminal_path || user.Terminal_path || '-';
            html += `
                <tr class="user-data-row" 
                    data-user-id="${escapeHtml(user.id)}" 
                    data-source="${escapeHtml(user.source)}"
                    data-email="${escapeHtml(user.email || '').toLowerCase()}" 
                    data-fullname="${escapeHtml(user.fullname || '').toLowerCase()}">
                    <td>${escapeHtml(user.id)}</td>
                    <td><strong>${escapeHtml(user.fullname || 'N/A')}</strong></td>
                    <td>${escapeHtml(user.email || 'N/A')}</td>
                    <td>${escapeHtml(user.broker || 'N/A')}</td>
                    <td><span class="source-badge">${escapeHtml(user.source)}</span></td>
                    <td><code class="invested-value">${escapeHtml(user.invested_with || '-')}</code></td>
                    <td class="balance-cell">${user.broker_balance ? '$' + parseFloat(user.broker_balance).toFixed(2) : '-'}</td>
                    <td class="execution-date-cell" data-original-date="${escapeHtml(user.execution_start_date || '')}">${escapeHtml(user.execution_start_date || '-')}</td>
                    <td class="contract-days-cell">${contractDaysLeft}</td>
                    <td><span class="${autoTradingClass}">${autoTradingStatus}</span></td>
                    <td><span class="${demoAccountClass}">${demoAccountStatus}</span></td>
                    <td><span class="mode-badge ${(user.account_mode || '').toLowerCase() === 'demo' ? 'mode-demo' : 'mode-real'}">${escapeHtml(user.account_mode || 'N/A')}</span></td>
                    <td><code class="terminal-path-value" title="${escapeHtml(terminalPath)}">${escapeHtml(terminalPath)}</code></td>
                    <td class="action-cell" style="display: none">
                        <select class="contract-action-select" data-user-id="${escapeHtml(user.id)}" data-source="${escapeHtml(user.source)}">
                            <option value="remain_active">Remain Active</option>
                            <option value="cancel_contract">Cancel Contract</option>
                        </select>
                        <button class="apply-action-btn" data-user-id="${escapeHtml(user.id)}" data-source="${escapeHtml(user.source)}">Apply</button>
                    </td>
                </tr>
            `;
        });
        html += `
                        </tbody>
                    </table>
                </div>
            `;
        container.innerHTML = html;
        if (type === 'verified') {
            document.querySelectorAll('#verified-users-list .apply-action-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const sourceTable = this.getAttribute('data-source');
                    const row = this.closest('tr');
                    const select = row.querySelector('.contract-action-select');
                    const action = select.value;
                    if (action === 'cancel_contract') {
                        showCustomConfirm(
                            'Cancel Contract',
                            'Are you sure you want to cancel this user\'s contract? This will change the execution start date to make the contract expired.',
                            'Cancel Contract',
                            'Abort',
                            function() {
                                showPasswordModal(
                                    'Security Verification',
                                    'Please enter your admin password to confirm contract cancellation.',
                                    function(password) {
                                        executeContractCancellation(password, userId, sourceTable, row);
                                    }
                                );
                            }
                        );
                    } else {
                        showCustomModal('Info', 'No action taken - user remains active', 'info');
                    }
                });
            });
        }
    }

    function executeContractCancellation(password, userId, sourceTable, row) {
        let formData = new URLSearchParams();
        formData.append('action', 'cancel_contract');
        formData.append('user_id', userId);
        formData.append('source_table', sourceTable);
        formData.append('admin_password', password);
        formData.append('login_id', '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
        const actionBtn = row.querySelector('.apply-action-btn');
        const originalBtnText = actionBtn?.innerHTML;
        if (actionBtn) {
            actionBtn.innerHTML = '⏳ Processing...';
            actionBtn.disabled = true;
        }
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
                showCustomModal('Success', data.message || 'Contract cancelled successfully!', 'success');
                const executionDateCell = row.querySelector('.execution-date-cell');
                if (executionDateCell && data.new_execution_date) {
                    executionDateCell.textContent = data.new_execution_date;
                    executionDateCell.setAttribute('data-original-date', data.new_execution_date);
                }
                const contractDaysCell = row.querySelector('.contract-days-cell');
                if (contractDaysCell && data.contract_days_left !== undefined) {
                    contractDaysCell.textContent = data.contract_days_left;
                }
                const select = row.querySelector('.contract-action-select');
                if (select) {
                    select.disabled = true;
                    select.value = 'remain_active';
                }
                if (actionBtn) {
                    actionBtn.disabled = true;
                    actionBtn.textContent = 'Cancelled';
                    actionBtn.style.background = '#95a5a6';
                }
            } else {
                if (data.error === 'Invalid password') {
                    showCustomModal('Error', 'Password verification failed. Please try again.', 'error');
                } else {
                    showCustomModal('Error', data.error || 'Error cancelling contract', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showCustomModal('Error', 'Error cancelling contract', 'error');
        })
        .finally(() => {
            if (actionBtn && actionBtn.disabled !== true) {
                actionBtn.innerHTML = originalBtnText || 'Apply';
                actionBtn.disabled = false;
            }
        });
    }

    // ============================================
    // INVESTED WITH MANAGEMENT FUNCTIONS
    // ============================================

    function loadInvestedWithUsers() {
        const container = document.getElementById('invested-users-list');
        if (!container) return;
        container.innerHTML = '<div style="text-align: center; padding: 40px;">Loading users...</div>';
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_users_invested_with'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.users.length === 0) {
                    container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No users found in the system</div>';
                    return;
                }
                let html = `
                    <div class="user-count-badge">Total: <span id="invested-users-count">${data.users.length}</span> users</div>
                    <div class="table-responsive">
                        <table class="invested-users-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Source</th>
                                    <th>Current INVESTED_WITH</th>
                                    <th>Edit INVESTED_WITH</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                data.users.forEach(user => {
                    const investedWith = user.invested_with || '';
                    const escapedInvestedWith = escapeHtml(investedWith);
                    const rowId = `invested-row-${user.id}-${user.source}`;
                    html += `
                        <tr id="${rowId}" class="user-data-row" data-user-id="${user.id}" data-email="${escapeHtml(user.email || '').toLowerCase()}" data-fullname="${escapeHtml(user.fullname || '').toLowerCase()}">
                            <td>${escapeHtml(user.id)}</td>
                            <td><strong>${escapeHtml(user.fullname || 'N/A')}</strong></td>
                            <td>${escapeHtml(user.email || 'N/A')}</td>
                            <td><span class="source-badge">${escapeHtml(user.source)}</span></td>
                            <td class="current-invested-with">
                                <code class="invested-value-display">${escapedInvestedWith || '<em style="color: #888;">Not set</em>'}</code>
                            </td>
                            <td>
                                <input type="text" 
                                       class="invested-edit-input" 
                                       id="input-${rowId}"
                                       value="${escapedInvestedWith.replace(/"/g, '&quot;')}"
                                       placeholder="e.g., deriv6_strategy1, deriv6_strategy2"
                                       style="width: 100%; padding: 8px;">
                            </td>
                            <td>
                                <button class="save-invested-btn" 
                                        onclick="saveInvestedWith('${user.id}', '${user.source}', '${rowId}')">
                                    💾 Save
                                </button>
                            </td>
                        </tr>
                    `;
                });
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
                container.innerHTML = html;
            } else {
                container.innerHTML = `<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading users: ${data.error || 'Unknown error'}</div>`;
                showCustomModal('Error', data.error || 'Error loading users', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading users</div>';
            showCustomModal('Error', 'Error loading users', 'error');
        });
    }

    function saveInvestedWith(userId, sourceTable, rowId) {
        const inputElement = document.getElementById(`input-${rowId}`);
        if (!inputElement) {
            showCustomModal('Error', 'Input field not found', 'error');
            return;
        }
        const newValue = inputElement.value.trim();
        showPasswordModal(
            'Security Verification',
            'Please enter your admin password to save INVESTED_WITH changes.',
            function(password) {
                executeSaveInvestedWith(password, userId, sourceTable, newValue, rowId);
            }
        );
    }

    function executeSaveInvestedWith(password, userId, sourceTable, newValue, rowId) {
        const row = document.getElementById(rowId);
        const saveBtn = row?.querySelector('.save-invested-btn');
        const originalBtnText = saveBtn?.innerHTML;
        if (saveBtn) {
            saveBtn.innerHTML = '💾 Saving...';
            saveBtn.disabled = true;
        }
        let formData = new URLSearchParams();
        formData.append('action', 'update_invested_with');
        formData.append('user_id', userId);
        formData.append('source_table', sourceTable);
        formData.append('invested_with', newValue);
        formData.append('admin_password', password);
        formData.append('login_id', '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
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
                const displayCell = row?.querySelector('.current-invested-with code');
                if (displayCell) {
                    displayCell.innerHTML = newValue || '<em style="color: #888;">Not set</em>';
                }
                showCustomModal('Success', `INVESTED_WITH updated for User ID ${userId}`, 'success');
            } else {
                if (data.error === 'Invalid password') {
                    showCustomModal('Error', 'Password verification failed. Please try again.', 'error');
                } else {
                    showCustomModal('Error', data.error || 'Error updating INVESTED_WITH', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showCustomModal('Error', 'Error updating INVESTED_WITH', 'error');
        })
        .finally(() => {
            if (saveBtn) {
                saveBtn.innerHTML = originalBtnText || '💾 Save';
                saveBtn.disabled = false;
            }
        });
    }

    // ============================================
    // EXECUTION HISTORY FUNCTIONS
    // ============================================

    function loadUsersForExecutionHistory() {
        const userListDiv = document.getElementById('execution-default-user-card');
        if (!userListDiv) return;
        userListDiv.innerHTML = '<div class="loading-spinner-small"><div class="spinner-small"></div><div>Loading users...</div></div>';
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_all_users_for_management'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                userListDiv.innerHTML = '';
                if (data.users.length === 0) {
                    userListDiv.innerHTML = '<div class="info-message-small">No users found in the system</div>';
                    return;
                }
                window.executionUsersCache = data.users;
                const randomIndex = Math.floor(Math.random() * data.users.length);
                const defaultUser = data.users[randomIndex];
                displayExecutionSingleUser(defaultUser);
                selectUserForExecutionHistory(defaultUser.id, defaultUser.source, defaultUser.fullname || 'N/A', defaultUser.email || 'N/A');
            } else {
                userListDiv.innerHTML = '<div class="info-message-small" style="color: #e74c3c;">Error loading users</div>';
                showCustomModal('Error', data.error || 'Error loading users', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            userListDiv.innerHTML = '<div class="info-message-small" style="color: #e74c3c;">Error loading users</div>';
            showCustomModal('Error', 'Error loading users', 'error');
        });
    }

    function displayExecutionSingleUser(user) {
        const container = document.getElementById('execution-default-user-card');
        if (!container) return;
        container.innerHTML = '';
        const userDiv = document.createElement('div');
        userDiv.className = 'default-user-info';
        userDiv.setAttribute('data-user-id', user.id);
        userDiv.setAttribute('data-source', user.source);
        userDiv.setAttribute('data-fullname', user.fullname || '');
        userDiv.setAttribute('data-email', user.email || '');
        userDiv.onclick = function(e) {
            e.stopPropagation();
            selectUserForExecutionHistory(user.id, user.source, user.fullname || 'N/A', user.email || 'N/A');
            showExecutionUsersModal();
        };
        userDiv.innerHTML = `
            <div class="default-user-name">${escapeHtml(user.fullname || 'N/A')}</div>
            <div class="default-user-email">${escapeHtml(user.email || 'N/A')}</div>
            <div class="default-user-id">ID: ${user.id} | ${user.source}</div>
        `;
        container.appendChild(userDiv);
    }

    function showExecutionUsersModal() {
        const container = document.querySelector('.split-view') || document.querySelector('.account-management-container');
        if (container) {
            container.classList.add('blur-background');
        }
        const users = window.executionUsersCache || [];
        const modalHtml = `
            <div class="modal-overlay" id="execution-users-modal-overlay" onclick="closeExecutionModalIfClickOutside(event)">
                <div class="modal-container users-modal" onclick="event.stopPropagation()">
                    <div class="modal-header">
                        <span>All Users (${users.length})</span>
                        <span class="modal-close" onclick="closeExecutionModal()">✕</span>
                    </div>
                    <div class="modal-body">
                        <div class="users-modal-search">
                            <input type="text" id="execution-users-modal-search-input" class="user-search-input" placeholder="Search users..." onkeyup="filterExecutionModalUsers()">
                        </div>
                    </div>
                    <div class="modal-body users-modal-list" id="execution-users-modal-list">
                        ${renderExecutionModalUsersList(users)}
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        setTimeout(() => {
            const searchInput = document.getElementById('execution-users-modal-search-input');
            if (searchInput) searchInput.focus();
        }, 100);
    }

    function renderExecutionModalUsersList(users) {
        if (!users || users.length === 0) {
            return '<div class="info-message-small">No users found</div>';
        }
        return users.map(user => `
            <div class="modal-user-item ${currentExecutionUserId && currentExecutionUserId == user.id ? 'selected' : ''}" 
                onclick="selectExecutionUserFromModal(${user.id}, '${user.source}')">
                <div class="modal-user-name">${escapeHtml(user.fullname || 'N/A')}</div>
                <div class="modal-user-email">${escapeHtml(user.email || 'N/A')}</div>
                <div class="modal-user-id">ID: ${user.id}</div>
            </div>
        `).join('');
    }

    function filterExecutionModalUsers() {
        const searchTerm = document.getElementById('execution-users-modal-search-input').value.toLowerCase();
        const users = window.executionUsersCache || [];
        const filteredUsers = users.filter(user => 
            (user.fullname && user.fullname.toLowerCase().includes(searchTerm)) ||
            (user.email && user.email.toLowerCase().includes(searchTerm)) ||
            user.id.toString().includes(searchTerm)
        );
        const container = document.getElementById('execution-users-modal-list');
        if (container) {
            container.innerHTML = renderExecutionModalUsersList(filteredUsers);
        }
    }

    function selectExecutionUserFromModal(userId, source) {
        const users = window.executionUsersCache || [];
        const user = users.find(u => u.id == userId);
        if (!user) return;
        selectUserForExecutionHistory(userId, source, user.fullname || 'N/A', user.email || 'N/A');
        displayExecutionSingleUser(user);
        closeExecutionModal();
    }

    function closeExecutionModal() {
        const overlay = document.getElementById('execution-users-modal-overlay');
        if (overlay) {
            overlay.remove();
        }
        removeBlur();
    }

    function closeExecutionModalIfClickOutside(event) {
        if (event.target.id === 'execution-users-modal-overlay') {
            closeExecutionModal();
        }
    }

    function selectUserForExecutionHistory(userId, sourceTable, fullname, email) {
        if (!userId || !sourceTable) {
            showCustomModal('Error', 'Invalid user selection', 'error');
            return;
        }
        currentExecutionUserId = userId;
        currentExecutionSourceTable = sourceTable;
        const nameSpan = document.getElementById('selected-execution-user-name');
        const emailSpan = document.getElementById('selected-execution-user-email');
        const sourceSpan = document.getElementById('selected-execution-user-source');
        if (nameSpan) nameSpan.textContent = fullname;
        if (emailSpan) emailSpan.textContent = email;
        if (sourceSpan) sourceSpan.textContent = sourceTable;
        loadExecutionHistoryForUser();
    }

    function loadExecutionHistoryForUser() {
        const container = document.getElementById('execution-history-list');
        if (!container) return;
        if (!currentExecutionUserId || !currentExecutionSourceTable) {
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">Select a user from the list to view their execution history</div>';
            return;
        }
        container.innerHTML = '<div style="text-align: center; padding: 40px;">Loading execution history...</div>';
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_user_execution_history&user_id=' + encodeURIComponent(currentExecutionUserId) + '&source_table=' + encodeURIComponent(currentExecutionSourceTable)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.history) {
                if (Object.keys(data.history).length === 0) {
                    container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No execution history records found for this user.</div>';
                    return;
                }
                let html = '<div class="execution-timeline">';
                const sortedKeys = Object.keys(data.history).sort((a, b) => {
                    const timeA = new Date(data.history[a].time);
                    const timeB = new Date(data.history[b].time);
                    return timeB - timeA;
                });
                for (const key of sortedKeys) {
                    const record = data.history[key];
                    const formattedTime = formatDate(record.time);
                    let typeBadgeClass = 'execution-type-info';
                    let typeText = record.type || 'info';
                    if (record.type === 'error') typeBadgeClass = 'execution-type-error';
                    else if (record.type === 'success') typeBadgeClass = 'execution-type-success';
                    else if (record.type === 'warning') typeBadgeClass = 'execution-type-warning';
                    let updateBadgeClass = 'execution-update-default';
                    let updateText = record.update || 'none';
                    if (record.update === 'new') updateBadgeClass = 'execution-update-new';
                    else if (record.update === 'updated') updateBadgeClass = 'execution-update-updated';
                    else if (record.update === 'deleted') updateBadgeClass = 'execution-update-deleted';
                    html += `
                        <div class="execution-record">
                            <div class="execution-header">
                                <span class="execution-time">${formattedTime}</span>
                                <div class="execution-badges">
                                    <span class="execution-type-badge ${typeBadgeClass}">${escapeHtml(typeText)}</span>
                                    <span class="execution-update-badge ${updateBadgeClass}">${escapeHtml(updateText)}</span>
                                </div>
                            </div>
                            ${record.section ? `<div class="execution-section">Section: ${escapeHtml(record.section)}</div>` : ''}
                            <div class="execution-message">${escapeHtml(record.message)}</div>
                        </div>
                    `;
                }
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = `<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading execution history: ${data.error || 'Unknown error'}</div>`;
                showCustomModal('Error', data.error || 'Error loading execution history', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading execution history</div>';
            showCustomModal('Error', 'Error loading execution history', 'error');
        });
    }

    // ============================================
    // AUTOTRADING FUNCTIONS
    // ============================================

    function loadAllUsersForAutoTrading() {
        const userListDiv = document.getElementById('autotrading-default-user-card');
        if (!userListDiv) return;
        userListDiv.innerHTML = '<div class="loading-spinner-small"><div class="spinner-small"></div><div>Loading users...</div></div>';
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_all_users_for_management'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                userListDiv.innerHTML = '';
                if (data.users.length === 0) {
                    userListDiv.innerHTML = '<div class="info-message-small">No users found in the system</div>';
                    return;
                }
                window.autotradingUsersCache = data.users;
                const randomIndex = Math.floor(Math.random() * data.users.length);
                const defaultUser = data.users[randomIndex];
                displayAutotradingSingleUser(defaultUser);
                selectUserForAutoTrading(defaultUser.id, defaultUser.source, defaultUser.fullname || 'N/A', defaultUser.email || 'N/A');
            } else {
                userListDiv.innerHTML = '<div class="info-message-small" style="color: #e74c3c;">Error loading users</div>';
                showCustomModal('Error', data.error || 'Error loading users', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            userListDiv.innerHTML = '<div class="info-message-small" style="color: #e74c3c;">Error loading users</div>';
            showCustomModal('Error', 'Error loading users', 'error');
        });
    }

    function displayAutotradingSingleUser(user) {
        const container = document.getElementById('autotrading-default-user-card');
        if (!container) return;
        container.innerHTML = '';
        const userDiv = document.createElement('div');
        userDiv.className = 'default-user-info';
        userDiv.setAttribute('data-user-id', user.id);
        userDiv.setAttribute('data-source', user.source);
        userDiv.setAttribute('data-fullname', user.fullname || '');
        userDiv.setAttribute('data-email', user.email || '');
        userDiv.onclick = function(e) {
            e.stopPropagation();
            selectUserForAutoTrading(user.id, user.source, user.fullname || 'N/A', user.email || 'N/A');
            showAutotradingUsersModal();
        };
        userDiv.innerHTML = `
            <div class="default-user-name">${escapeHtml(user.fullname || 'N/A')}</div>
            <div class="default-user-email">${escapeHtml(user.email || 'N/A')}</div>
            <div class="default-user-id">ID: ${user.id} | ${user.source}</div>
        `;
        container.appendChild(userDiv);
    }

    function showAutotradingUsersModal() {
        const container = document.querySelector('.split-view') || document.querySelector('.account-management-container');
        if (container) {
            container.classList.add('blur-background');
        }
        const users = window.autotradingUsersCache || [];
        const modalHtml = `
            <div class="modal-overlay" id="autotrading-users-modal-overlay" onclick="closeAutotradingModalIfClickOutside(event)">
                <div class="modal-container users-modal" onclick="event.stopPropagation()">
                    <div class="modal-header">
                        <span>All Users (${users.length})</span>
                        <span class="modal-close" onclick="closeAutotradingModal()">✕</span>
                    </div>
                    <div class="modal-body">
                        <div class="users-modal-search">
                            <input type="text" id="autotrading-users-modal-search-input" class="user-search-input" placeholder="Search users..." onkeyup="filterAutotradingModalUsers()">
                        </div>
                    </div>
                    <div class="modal-body users-modal-list" id="autotrading-users-modal-list">
                        ${renderAutotradingModalUsersList(users)}
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        setTimeout(() => {
            const searchInput = document.getElementById('autotrading-users-modal-search-input');
            if (searchInput) searchInput.focus();
        }, 100);
    }

    function renderAutotradingModalUsersList(users) {
        if (!users || users.length === 0) {
            return '<div class="info-message-small">No users found</div>';
        }
        return users.map(user => `
            <div class="modal-user-item ${currentAutoTradingUserId && currentAutoTradingUserId == user.id ? 'selected' : ''}" 
                onclick="selectAutotradingUserFromModal(${user.id}, '${user.source}')">
                <div class="modal-user-name">${escapeHtml(user.fullname || 'N/A')}</div>
                <div class="modal-user-email">${escapeHtml(user.email || 'N/A')}</div>
                <div class="modal-user-id">ID: ${user.id}</div>
            </div>
        `).join('');
    }

    function filterAutotradingModalUsers() {
        const searchTerm = document.getElementById('autotrading-users-modal-search-input').value.toLowerCase();
        const users = window.autotradingUsersCache || [];
        const filteredUsers = users.filter(user => 
            (user.fullname && user.fullname.toLowerCase().includes(searchTerm)) ||
            (user.email && user.email.toLowerCase().includes(searchTerm)) ||
            user.id.toString().includes(searchTerm)
        );
        const container = document.getElementById('autotrading-users-modal-list');
        if (container) {
            container.innerHTML = renderAutotradingModalUsersList(filteredUsers);
        }
    }

    function selectAutotradingUserFromModal(userId, source) {
        const users = window.autotradingUsersCache || [];
        const user = users.find(u => u.id == userId);
        if (!user) return;
        selectUserForAutoTrading(userId, source, user.fullname || 'N/A', user.email || 'N/A');
        displayAutotradingSingleUser(user);
        closeAutotradingModal();
    }

    function closeAutotradingModal() {
        const overlay = document.getElementById('autotrading-users-modal-overlay');
        if (overlay) {
            overlay.remove();
        }
        removeBlur();
    }

    function closeAutotradingModalIfClickOutside(event) {
        if (event.target.id === 'autotrading-users-modal-overlay') {
            closeAutotradingModal();
        }
    }

    function selectUserForAutoTrading(userId, sourceTable, fullname, email) {
        if (!userId || !sourceTable) {
            showCustomModal('Error', 'Invalid user selection', 'error');
            return;
        }
        currentAutoTradingUserId = userId;
        currentAutoTradingSourceTable = sourceTable;
        loadUserAutoTradingSettings(userId, sourceTable, fullname, email);
    }

    function loadUserAutoTradingSettings(userId, sourceTable, fullname, email) {
        const container = document.getElementById('auto-trading-settings');
        if (!container) return;
        container.innerHTML = '<div style="text-align: center; padding: 40px;">Loading user settings...</div>';
        Promise.all([
            fetchUserSetting(userId, sourceTable, 'enable_autotrading'),
            fetchUserSetting(userId, sourceTable, 'bypass_restriction'),
            fetchUserSetting(userId, sourceTable, 'demo_account')
        ])
        .then(([autoTradingResult, bypassResult, demoAccountResult]) => {
            currentAutoTradingData = {
                enable_autotrading: autoTradingResult !== null ? autoTradingResult : 1,
                bypass_restriction: bypassResult !== null ? bypassResult : 0,
                demo_account: demoAccountResult !== null ? demoAccountResult : 0
            };
            const html = `
                <div class="auto-trading-settings">
                    <div class="user-info-settings">
                        <div class="user-info-item"><span class="user-info-label">Selected User:</span> <span>${escapeHtml(fullname)}</span></div>
                        <div class="user-info-item"><span class="user-info-label">Email:</span> <span>${escapeHtml(email)}</span></div>
                        <div class="user-info-item"><span class="user-info-label">User ID:</span> <span>${userId}</span></div>
                        <div class="user-info-item"><span class="user-info-label">Source:</span> <span>${sourceTable}</span></div>
                    </div>
                    <div class="setting-card">
                        <div class="setting-label">
                            <label>Autotrading</label>
                            <span class="setting-description">Enable or disable automatic trading for this user</span>
                        </div>
                        <div class="setting-control">
                            <select id="enable_autotrading_select" class="setting-select">
                                <option value="1" ${currentAutoTradingData.enable_autotrading == 1 ? 'selected' : ''}>Enabled (1)</option>
                                <option value="0" ${currentAutoTradingData.enable_autotrading == 0 ? 'selected' : ''}>Disabled (0)</option>
                            </select>
                        </div>
                    </div>
                    <div class="setting-card">
                        <div class="setting-label">
                            <label>Bypass Restriction</label>
                            <span class="setting-description">Allow user to bypass trading restrictions when enabled</span>
                        </div>
                        <div class="setting-control">
                            <select id="bypass_restriction_select" class="setting-select">
                                <option value="1" ${currentAutoTradingData.bypass_restriction == 1 ? 'selected' : ''}>Allow Bypass (1)</option>
                                <option value="0" ${currentAutoTradingData.bypass_restriction == 0 ? 'selected' : ''}>Restrict (0)</option>
                            </select>
                        </div>
                    </div>
                    <div class="setting-card">
                        <div class="setting-label">
                            <label>Demo Account Restriction</label>
                            <span class="setting-description">Enable or disable demo if account mode is demo</span>
                        </div>
                        <div class="setting-control">
                            <select id="demo_account_select" class="setting-select">
                                <option value="1" ${currentAutoTradingData.demo_account == 1 ? 'selected' : ''}>Enable Demo (1)</option>
                                <option value="0" ${currentAutoTradingData.demo_account == 0 ? 'selected' : ''}>Disable Demo (0)</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading user settings</div>';
            showCustomModal('Error', 'Error loading user settings', 'error');
        });
    }

    function fetchUserSetting(userId, sourceTable, columnName) {
        return new Promise((resolve, reject) => {
            let formData = new URLSearchParams();
            formData.append('action', 'get_user_setting');
            formData.append('user_id', userId);
            formData.append('source_table', sourceTable);
            formData.append('column_name', columnName);
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
                    resolve(data.value);
                } else {
                    resolve(null);
                }
            })
            .catch(error => {
                console.error('Error fetching setting:', error);
                resolve(null);
            });
        });
    }

    function saveAutoTradingSettings() {
        if (!currentAutoTradingUserId || !currentAutoTradingSourceTable) {
            showCustomModal('Error', 'No user selected', 'error');
            return;
        }
        const autoTradingValue = parseInt(document.getElementById('enable_autotrading_select')?.value || 1);
        const bypassValue = parseInt(document.getElementById('bypass_restriction_select')?.value || 0);
        const demoAccountValue = parseInt(document.getElementById('demo_account_select')?.value || 0);
        showPasswordModal(
            'Security Verification',
            'Please enter your admin password to save all settings.',
            function(password) {
                executeSaveAutoTradingSettingsBatch(password);
            }
        );
    }

    function executeSaveAutoTradingSettingsBatch(password) {
        const userId = currentAutoTradingUserId;
        const sourceTable = currentAutoTradingSourceTable;
        const autoTradingValue = parseInt(document.getElementById('enable_autotrading_select')?.value || 1);
        const bypassValue = parseInt(document.getElementById('bypass_restriction_select')?.value || 0);
        const demoAccountValue = parseInt(document.getElementById('demo_account_select')?.value || 0);
        
        let formData = new URLSearchParams();
        formData.append('action', 'update_user_settings_batch');
        formData.append('user_id', userId);
        formData.append('source_table', sourceTable);
        formData.append('enable_autotrading', autoTradingValue);
        formData.append('bypass_restriction', bypassValue);
        formData.append('demo_account', demoAccountValue);
        formData.append('admin_password', password);
        formData.append('login_id', '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
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
                currentAutoTradingData.enable_autotrading = autoTradingValue;
                currentAutoTradingData.bypass_restriction = bypassValue;
                currentAutoTradingData.demo_account = demoAccountValue;
                showCustomModal('Success', 'All settings updated successfully!', 'success');
            } else {
                if (data.error === 'Invalid password') {
                    showCustomModal('Error', 'Password verification failed. Please try again.', 'error');
                } else {
                    showCustomModal('Error', data.error || 'Error updating settings', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showCustomModal('Error', 'Error updating settings', 'error');
        });
    }

    // ============================================
    // TABLE SEARCH FUNCTIONS
    // ============================================

    function setupTableSearch(searchInputId, tableContainerId, getRowData) {
        const searchInput = document.getElementById(searchInputId);
        if (!searchInput) return;
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const container = document.getElementById(tableContainerId);
            if (!container) return;
            const rows = container.querySelectorAll('.user-data-row');
            let visibleCount = 0;
            rows.forEach(row => {
                const rowData = getRowData(row);
                if (searchTerm === '' || rowData.id.includes(searchTerm) || rowData.email.includes(searchTerm) || rowData.fullname.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            let noResultsMsg = container.querySelector('.no-results-msg');
            if (visibleCount === 0 && searchTerm !== '') {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.className = 'no-results-msg';
                    noResultsMsg.style.cssText = 'text-align: center; padding: 40px; color: #888;';
                    noResultsMsg.innerHTML = '🔍 No users match your search';
                    container.appendChild(noResultsMsg);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        });
    }

    // ============================================
    // DOM CONTENT LOADED - INITIALIZATION
    // ============================================

    document.addEventListener('DOMContentLoaded', function() {
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabs = document.querySelectorAll('.management-tab');
        
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                currentTab = tabId;
                
                tabBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                tabs.forEach(tab => {
                    tab.style.display = 'none';
                });
                
                if (tabId === 'server') {
                    document.getElementById('server-tab').style.display = 'block';
                    loadServerAccountManagement();
                    loadAccountManagementConfigs();
                } else if (tabId === 'users') {
                    document.getElementById('users-tab').style.display = 'block';
                    loadAllUsersForManagement();
                } else if (tabId === 'invested') {
                    document.getElementById('invested-tab').style.display = 'block';
                    loadInvestedWithUsers();
                    setupTableSearch('invested-users-search', 'invested-users-list', (row) => ({
                        id: row.getAttribute('data-user-id') || '',
                        email: (row.getAttribute('data-email') || '').toLowerCase(),
                        fullname: (row.getAttribute('data-fullname') || '').toLowerCase()
                    }));
                } else if (tabId === 'verified') {
                    document.getElementById('verified-tab').style.display = 'block';
                    loadVerifiedUsers();
                    setupTableSearch('verified-users-search', 'verified-users-list', (row) => ({
                        id: row.getAttribute('data-user-id') || '',
                        email: (row.getAttribute('data-email') || '').toLowerCase(),
                        fullname: (row.getAttribute('data-fullname') || '').toLowerCase()
                    }));
                } else if (tabId === 'pending') {
                    document.getElementById('pending-tab').style.display = 'block';
                    loadPendingUsers();
                    setupTableSearch('pending-users-search', 'pending-users-list', (row) => ({
                        id: row.getAttribute('data-user-id') || '',
                        email: (row.getAttribute('data-email') || '').toLowerCase(),
                        fullname: (row.getAttribute('data-fullname') || '').toLowerCase()
                    }));
                } else if (tabId === 'suspended') {
                    document.getElementById('suspended-tab').style.display = 'block';
                    loadSuspendedUsers();
                    setupTableSearch('suspended-users-search', 'suspended-users-list', (row) => ({
                        id: row.getAttribute('data-user-id') || '',
                        email: (row.getAttribute('data-email') || '').toLowerCase(),
                        fullname: (row.getAttribute('data-fullname') || '').toLowerCase()
                    }));
                } else if (tabId === 'justjoined') {
                    document.getElementById('justjoined-tab').style.display = 'block';
                    loadJustJoinedUsers();
                    setupTableSearch('justjoined-users-search', 'justjoined-users-list', (row) => ({
                        id: row.getAttribute('data-user-id') || '',
                        email: (row.getAttribute('data-email') || '').toLowerCase(),
                        fullname: (row.getAttribute('data-fullname') || '').toLowerCase()
                    }));
                } else if (tabId === 'justjoinedvalid') {
                    document.getElementById('justjoinedvalid-tab').style.display = 'block';
                    loadJustJoinedValidUsers();
                    setupTableSearch('justjoinedvalid-users-search', 'justjoinedvalid-users-list', (row) => ({
                        id: row.getAttribute('data-user-id') || '',
                        email: (row.getAttribute('data-email') || '').toLowerCase(),
                        fullname: (row.getAttribute('data-fullname') || '').toLowerCase()
                    }));
                } else if (tabId === 'approved') {
                    document.getElementById('approved-tab').style.display = 'block';
                    loadApprovedUsers();
                    setupTableSearch('approved-users-search', 'approved-users-list', (row) => ({
                        id: row.getAttribute('data-user-id') || '',
                        email: (row.getAttribute('data-email') || '').toLowerCase(),
                        fullname: (row.getAttribute('data-fullname') || '').toLowerCase()
                    }));
                } else if (tabId === 'execution') {
                    document.getElementById('execution-tab').style.display = 'block';
                    loadUsersForExecutionHistory();
                } else if (tabId === 'autotrading') {
                    document.getElementById('autotrading-tab').style.display = 'block';
                    loadAllUsersForAutoTrading();
                } else if (tabId === 'bypassed') {
                    document.getElementById('bypassed-tab').style.display = 'block';
                    loadBypassedUsers();
                    setupTableSearch('bypassed-users-search', 'bypassed-users-list', (row) => ({
                        id: row.getAttribute('data-user-id') || '',
                        email: (row.getAttribute('data-email') || '').toLowerCase(),
                        fullname: (row.getAttribute('data-fullname') || '').toLowerCase()
                    }));
                }
            });
        });
        
        loadServerAccountManagement();
        setupGlobalSearch();
    });

    // Fallback to ensure configs load
    setTimeout(function() {
        if (document.getElementById('config-entries-container') && 
            document.getElementById('config-entries-container').innerHTML.includes('Loading configurations')) {
            loadAccountManagementConfigs();
        }
    }, 1000);

</script>