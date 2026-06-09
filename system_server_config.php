<?php
// system_server_config.php - System Server IP Management
// This file is included in serveraccount.php when view=system_config
?>

<style>
    /* Config Properties Section Styles */
    .config-properties-section {
        background: var(--bg-primary);
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 20px;
        border: 1px solid var(--border-color);
    }

    .config-properties-section .assigned-users-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .add-property-btn {
        background: #9b59b6;
        color: white;
        border: none;
        padding: 5px 12px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 11px;
        font-weight: 600;
        transition: background 0.2s ease;
    }

    .add-property-btn:hover {
        background: #8e44ad;
    }

    .config-properties-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .property-item {
        background: var(--bg-secondary);
        border-radius: 6px;
        padding: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid var(--border-color);
    }

    .property-item:hover {
        border-color: #9b59b6;
    }

    .property-info {
        flex: 1;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: baseline;
        min-width: 0;
    }

    .property-key {
        font-weight: 600;
        font-size: 13px;
        color: #9b59b6;
        font-family: monospace;
    }

    .property-value {
        font-size: 13px;
        color: var(--text-primary);
        word-break: break-word;
        white-space: normal;
        overflow-wrap: break-word;
        max-width: 300px;
    }

    .property-actions {
        display: flex;
        gap: 8px;
    }

    .edit-property-btn {
        background: #f39c12;
        color: white;
        border: none;
        padding: 4px 8px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 11px;
    }

    .edit-property-btn:hover {
        background: #e67e22;
    }

    .delete-property-btn {
        background: #e74c3c;
        color: white;
        border: none;
        padding: 4px 8px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 11px;
    }

    .delete-property-btn:hover {
        background: #c0392b;
    }

    .no-properties {
        text-align: center;
        padding: 15px;
        color: var(--text-secondary);
        font-size: 12px;
    }

    /* System IP Management Styles */
    .config-management-container {
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .config-header-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .refresh-config-btn {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }
    
    .refresh-config-btn:hover {
        background: var(--bg-hover);
    }
    
    /* Global Search Styles */
    .global-search-section {
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
        border: 1px solid var(--border-color);
    }
    
    .global-search-title {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .global-search-input-wrapper {
        position: relative;
        margin-bottom: 20px;
    }
    
    .global-search-input {
        width: 100%;
        padding: 15px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
        background: var(--bg-primary);
        color: var(--text-primary);
        font-size: 16px;
        padding-left: 15px;
    }
    
    .global-search-results {
        background: var(--bg-primary);
        border-radius: 10px;
        border: 1px solid var(--border-color);
        max-height: 400px;
        overflow-y: auto;
        display: none;
    }
    
    .global-search-results.show {
        display: block;
    }
    
    .global-search-result-item {
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .global-search-result-item:hover {
        background: var(--bg-hover);
    }
    
    .global-result-name {
        font-weight: 600;
        font-size: 15px;
        margin-bottom: 5px;
    }
    
    .global-result-email {
        font-size: 12px;
        color: var(--text-secondary);
        margin-bottom: 5px;
    }
    
    .config-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(480px, 1fr));
        gap: 20px;
    }
    
    .config-card {
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .config-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .config-card-header:hover {
        background: var(--bg-hover);
    }
    
    .config-card-body {
        padding: 15px;
        display: none;
        overflow-x: hidden;
    }
    
    .config-card.expanded .config-card-body {
        display: block;
    }
    
    .config-card-header {
        background: var(--bg-primary);
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        cursor: pointer;
        transition: background 0.2s ease;
        flex-wrap: wrap;
        gap: 8px;
    }

    .config-address-config {
        width: 98%;
        word-break: break-word;
        white-space: normal;
        overflow-wrap: break-word;
        flex: 1;
        min-width: 0;
    }
    
    .config-address {
        font-size: 16px;
        font-weight: 600;
        font-family: monospace;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        word-break: break-word;
        white-space: normal;
        overflow-wrap: break-word;
    }

    .config-address-config span {
        word-break: break-word;
        white-space: normal;
        overflow-wrap: break-word;
        display: inline-block;
        max-width: 100%;
    }
    
    .config-badge {
        background: var(--accent-color);
        color: white;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: normal;
    }
    
    .assigned-users-section {
        margin-bottom: 20px;
    }
    
    .assigned-users-title {
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--text-secondary);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .assigned-users-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-height: 350px;
        overflow-y: auto;
    }
    
    .assigned-user-item {
        background: var(--bg-primary);
        border-radius: 8px;
        padding: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid var(--border-color);
        transition: all 0.2s ease;
    }
    
    .assigned-user-item:hover {
        border-color: var(--accent-color);
    }
    
    .user-name {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 4px;
    }
    
    .user-email {
        font-size: 12px;
        color: var(--text-secondary);
    }
    
    .remove-user-btn {
        background: #e74c3c;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 11px;
        transition: background 0.2s ease;
    }
    
    .remove-user-btn:hover {
        background: #c0392b;
    }
    
    .remove-user-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .add-users-section {
        border-top: 1px solid var(--border-color);
        padding-top: 15px;
        margin-top: 10px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .search-container-config {
        position: relative;
        margin-bottom: 10px;
    }
    
    .search-input-config {
        width: 100%;
        padding: 14px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: var(--bg-primary);
        color: var(--text-primary);
        font-size: 15px;
    }
    
    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        max-height: 350px;
        overflow-y: auto;
        z-index: 100;
        display: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .search-results.show {
        display: block;
    }
    
    .search-result-item {
        padding: 14px;
        cursor: pointer;
        transition: background 0.2s ease;
        border-bottom: 1px solid var(--border-color);
    }
    
    .search-result-item:hover {
        background: var(--bg-hover);
    }
    
    .search-result-name {
        font-weight: 600;
        font-size: 15px;
        margin-bottom: 5px;
    }
    
    .search-result-email {
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 5px;
    }
    
    .search-result-ip-status {
        font-size: 12px;
        margin-top: 5px;
        padding: 4px 8px;
        border-radius: 5px;
        display: inline-block;
    }
    
    .search-result-ip-status.linked {
        background: rgba(52, 152, 219, 0.1);
        color: var(--accent-color);
    }
    
    .search-result-ip-status.linked-to-other {
        background: rgba(243, 156, 18, 0.1);
        color: #f39c12;
    }
    
    .search-result-ip-status.not-linked {
        background: rgba(231, 76, 60, 0.1);
        color: #e74c3c;
    }
    
    .pending-users {
        margin-top: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .pending-user-tag {
        background: var(--accent-color);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .pending-user-tag .remove-pending {
        cursor: pointer;
        font-weight: bold;
        opacity: 0.8;
    }
    
    .pending-user-tag .remove-pending:hover {
        opacity: 1;
    }
    
    .save-config-changes,
    .edit-config-btn,
    .delete-config-btn {
        width: 100%;
        padding: 10px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: background 0.2s ease;
        border: none;
    }

    .save-config-changes {
        background: #27ae60;
        color: white;
    }

    .save-config-changes:hover {
        background: #219a52;
    }

    .edit-config-btn {
        background: #f39c12;
        color: white;
    }

    .edit-config-btn:hover {
        background: #e67e22;
    }

    .delete-config-btn {
        background: #e74c3c;
        color: white;
        margin-top: 0;
    }

    .delete-config-btn:hover {
        background: #c0392b;
    }
    
    .loading-spinner {
        text-align: center;
        padding: 40px;
        color: var(--text-secondary);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px;
        color: var(--text-secondary);
        border: 2px dashed var(--border-color);
        border-radius: 12px;
    }
    
    /* Modal Styles */
    .json-password-input {
        width: 100%;
        padding: 12px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        background: var(--bg-primary);
        color: var(--text-primary);
        font-size: 14px;
        box-sizing: border-box;
    }

    .json-password-input:focus {
        outline: none;
        border-color: var(--accent-color);
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }

    .modal-cancel-btn {
        background: #95a5a6;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
    }

    .modal-cancel-btn:hover {
        background: #7f8c8d;
    }

    .modal-confirm-btn {
        background: #27ae60;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
    }

    .modal-confirm-btn:hover {
        background: #219a52;
    }

    .add-config-btn {
        background: #3498db;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    .add-config-btn:hover {
        background: #2980b9;
    }

    /* Fix for confirm add config modal - wrap long config keys */
    #config-add-password-modal .modal-content p strong {
        word-break: break-word;
        white-space: normal;
        display: inline-block;
        max-width: 100%;
    }

    #config-add-password-modal .modal-content p {
        word-break: break-word;
        white-space: normal;
        overflow-wrap: break-word;
    }

    /* Additional fix for any other modals that might display long config keys */
    .modal-content p strong,
    .modal-content p {
        word-break: break-word;
        white-space: normal;
        overflow-wrap: break-word;
    }

    @media (max-width: 768px) {
        .config-grid {
            grid-template-columns: 1fr;
        }
        
        .config-card-header {
            flex-direction: column;
            gap: 10px;
            align-items: flex-start;
        }
    }
    /* Ensure cards expand/collapse independently */
    .config-card {
        transition: all 0.3s ease;
        height: auto;
        min-height: fit-content;
    }

    .config-card .config-card-body {
        display: none;
        overflow: hidden;
        transition: none; /* Remove transition to prevent propagation */
    }

    .config-card.expanded .config-card-body {
        display: block;
    }

    /* Prevent grid layout from affecting card heights */
    .config-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(480px, 1fr));
        gap: 20px;
        align-items: start; /* Changed from default stretch to start */
    }

    /* Ensure each card is independent */
    .config-card {
        break-inside: avoid;
        page-break-inside: avoid;
        position: relative;
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--border-color);
        transition: box-shadow 0.3s ease;
        height: auto;
    }

    /* Remove any potential sibling selectors that might affect other cards */
    .config-card:has(.expanded) ~ .config-card {
        /* Reset any unwanted styles */
        margin-top: 0;
    }
</style>

<div class="config-management-container">
    <div class="config-header-actions">
        <h2 style="font-size: 20px;">🌐 System Servers Config Management</h2>
        <div style="display: flex; gap: 10px;">
            <button class="refresh-config-btn" onclick="loadSystemConfig()">
                🔄 Refresh
            </button>
            <button class="add-config-btn" onclick="addNewConfig()">
            ➕ Add New Config
        </button>
        </div>
    </div>
    
    <!-- Global Search Section -->
    <div class="global-search-section">
        <div class="global-search-title">
            🔍 Search Users
        </div>
        <div class="global-search-input-wrapper">
            <input type="text" class="global-search-input" id="global-search-input" placeholder="Search by name, email, or user ID..." autocomplete="off">
        </div>
        <div class="global-search-results" id="global-search-results"></div>
    </div>
    
    <div id="config-config-container">
        <div class="loading-spinner">Loading IP configuration...</div>
    </div>
</div>

<script>
    let currentConfig = {};
    let pendingChanges = {};
    let userCache = {};
    let globalSearchTimeout = null;
    
    // Load system configuration
    function loadSystemConfig() {
        const container = document.getElementById('config-config-container');
        container.innerHTML = '<div class="loading-spinner">Loading configuration...</div>';
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_system_config'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentConfig = data.config || {};
                renderConfig(currentConfig);
            } else {
                container.innerHTML = '<div class="empty-state">❌ Error loading configuration: ' + escapeHtml(data.error) + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div class="empty-state">❌ Error loading configuration</div>';
        });
    }
    
    // Store expanded config before re-render
    let expandedConfigs = [];

    // Function to save expanded state
    function saveExpandedState() {
        expandedConfigs = [];
        document.querySelectorAll('.config-card.expanded').forEach(card => {
            const configKey = card.getAttribute('data-config');
            if (configKey) expandedConfigs.push(configKey);
        });
    }

    // Function to restore expanded state
    function restoreExpandedState() {
        // Only expand cards that were previously expanded
        expandedConfigs.forEach(configKey => {
            const card = document.querySelector(`.config-card[data-config="${escapeHtml(configKey).replace(/"/g, '&quot;')}"]`);
            if (card) {
                card.classList.add('expanded');
            }
        });
        // Clear the array after restoration
        expandedConfigs = [];
    }

    // Update the renderConfig function - ensure no global class changes
    function renderConfig(config) {
        const container = document.getElementById('config-config-container');
        const configKeys = Object.keys(config);
        
        // Save current expanded state before re-rendering
        saveExpandedState();
        
        if (configKeys.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div style="font-size: 48px; margin-bottom: 20px;">🌐</div>
                    <h3>No Server Configurations Configured</h3>
                    <p>No server configurations have been set up.</p>
                </div>
            `;
            return;
        }
        
        // Fetch all user details for all configs (but don't re-render immediately)
        const allUserIds = [];
        configKeys.forEach(configKey => {
            let configData = config[configKey];
            let userIds = [];
            
            // Extract user IDs from the data structure
            if (Array.isArray(configData)) {
                if (configData.length > 0 && typeof configData[configData.length - 1] === 'object' && !Array.isArray(configData[configData.length - 1])) {
                    userIds = configData.slice(0, -1);
                } else {
                    userIds = [...configData];
                }
            } else if (typeof configData === 'object') {
                userIds = configData._userIds || [];
            }
            
            userIds.forEach(id => {
                if (!allUserIds.includes(id)) allUserIds.push(id);
            });
        });
        
        let html = '<div class="config-grid">';
        
        configKeys.forEach(configKey => {
            let configData = config[configKey];
            let userIds = [];
            let properties = {};
            
            // Extract user IDs and properties from the data structure
            if (Array.isArray(configData)) {
                if (configData.length > 0 && typeof configData[configData.length - 1] === 'object' && !Array.isArray(configData[configData.length - 1])) {
                    properties = configData[configData.length - 1];
                    userIds = configData.slice(0, -1);
                } else {
                    userIds = [...configData];
                    properties = {};
                }
            } else if (typeof configData === 'object') {
                userIds = configData._userIds || [];
                properties = { ...configData };
                delete properties._userIds;
            }
            
            const userCount = userIds.length;
            
            // Add unique ID for each card for better DOM manipulation
            const cardId = 'config-card-' + configKey.replace(/[^a-zA-Z0-9]/g, '-');
            
            html += `
                <div class="config-card" data-config="${escapeHtml(configKey)}" id="${cardId}">
                    <div class="config-card-header" onclick="toggleConfigCard(this, event)">
                        <div class="config-address">
                            <span>🌐</span>
                        </div>
                        <div class="config-address config-address-config">
                            <span> ${escapeHtml(configKey)}</span>
                        </div>
                        <div class="config-address">
                            <span class="config-badge">${userCount} user${userCount !== 1 ? 's' : ''}</span>
                        </div>
                    </div>
                    <div class="config-card-body">
                        <!-- Config Properties Section -->
                        <div class="config-properties-section">
                            <div class="assigned-users-title">
                                <span>⚙️ Config Properties / Configuration</span>
                            </div>
                            <div class="config-properties-list" id="properties-list-${escapeHtml(configKey).replace(/\./g, '-')}">
                                ${renderConfigProperties(configKey, configData)}
                            </div>
                            <div class="assigned-users-title">
                                <button class="add-property-btn" onclick="event.stopPropagation(); showAddPropertyModal('${escapeHtml(configKey)}')">
                                    + Add Property
                                </button>
                            </div>
                        </div>
                        <div class="assigned-users-section">
                            <div class="assigned-users-title">
                                <span>📋 Assigned Users (${userCount})</span>
                            </div>
                            <div class="assigned-users-list" id="users-list-${escapeHtml(configKey).replace(/\./g, '-')}">
                                ${renderAssignedUsers(configKey, userIds)}
                            </div>
                        </div>
                        
                        <div class="add-users-section">
                            <div class="assigned-users-title">
                                <span>➕ Add Users to ${escapeHtml(configKey)}</span>
                            </div>
                            <div class="search-container-config">
                                <input type="text" class="search-input-config" placeholder="Search by name, email, or ID..." 
                                    onkeyup="searchUsers(this, '${escapeHtml(configKey)}')" autocomplete="off">
                                <div class="search-results" id="search-results-${escapeHtml(configKey).replace(/\./g, '-')}"></div>
                            </div>
                            <div class="pending-users" id="pending-users-${escapeHtml(configKey).replace(/\./g, '-')}"></div>
                                <button class="save-config-changes" onclick="saveConfigChanges('${escapeHtml(configKey)}')">
                                    💾 Save Changes
                                </button>
                                <button class="edit-config-btn" onclick="editConfigKey('${escapeHtml(configKey)}')">
                                    ✏️ Edit Config Key
                                </button>
                                <button class="delete-config-btn" onclick="deleteConfigValue('${escapeHtml(configKey)}')">
                                    🗑️ Delete Config
                                </button>
                            </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
        // Restore expanded state after re-render - this only affects previously expanded cards
        restoreExpandedState();
        
        // Initialize pending changes tracking
        configKeys.forEach(configKey => {
            if (!pendingChanges[configKey]) {
                pendingChanges[configKey] = {
                    toAdd: [],
                    toRemove: []
                };
            }
        });
        
        // Now fetch user details asynchronously without disrupting the UI
        if (allUserIds.length > 0) {
            fetchUserDetailsBackground(allUserIds);
        }
    }

    // Fetch user details in background without re-rendering
    function fetchUserDetailsBackground(userIds) {
        const uniqueIds = [...new Set(userIds)];
        const idsToFetch = uniqueIds.filter(id => !userCache[id]);
        
        if (idsToFetch.length === 0) return;
        
        Promise.all([
            fetchUsersFromTable(idsToFetch, '<?= $insidersTable ?>'),
            fetchUsersFromTable(idsToFetch, '<?= $insidersServerTable ?>')
        ]).then(([users1, users2]) => {
            const allUsers = [...users1, ...users2];
            let hasNewUsers = false;
            allUsers.forEach(user => {
                if (!userCache[user.id]) {
                    userCache[user.id] = user;
                    hasNewUsers = true;
                }
            });
            
            if (hasNewUsers) {
                updateAllUserDisplays();
            }
        });
    }

    // Update ALL user displays (both expanded and collapsed)
    function updateAllUserDisplays() {
        document.querySelectorAll('.config-card').forEach(card => {
            const configKey = card.getAttribute('data-config');
            if (configKey && currentConfig[configKey]) {
                let configData = currentConfig[configKey];
                let userIds = [];
                
                // Extract user IDs from the data structure
                if (Array.isArray(configData)) {
                    if (configData.length > 0 && typeof configData[configData.length - 1] === 'object' && !Array.isArray(configData[configData.length - 1])) {
                        userIds = configData.slice(0, -1);
                    } else {
                        userIds = [...configData];
                    }
                } else if (typeof configData === 'object') {
                    userIds = configData._userIds || [];
                }
                
                const usersListDiv = card.querySelector('.assigned-users-list');
                if (usersListDiv) {
                    usersListDiv.innerHTML = renderAssignedUsers(configKey, userIds);
                }
                
                // Update the user count badge
                const userCount = userIds.length;
                const badgeSpan = card.querySelector('.config-badge');
                if (badgeSpan) {
                    badgeSpan.textContent = `${userCount} user${userCount !== 1 ? 's' : ''}`;
                }
                
                // Update the assigned users title count
                const usersTitle = card.querySelector('.assigned-users-section .assigned-users-title span');
                if (usersTitle) {
                    usersTitle.textContent = `📋 Assigned Users (${userCount})`;
                }
            }
        });
    }

    // Render Config Properties
    function renderConfigProperties(configKey, configData) {
        let propertiesHtml = '';
        let properties = {};
        
        if (configData && typeof configData === 'object') {
            if (Array.isArray(configData)) {
                if (configData.length > 0 && typeof configData[configData.length - 1] === 'object' && !Array.isArray(configData[configData.length - 1])) {
                    properties = configData[configData.length - 1];
                } else {
                    properties = {};
                }
            } else {
                properties = configData;
            }
        }
        
        const propertyKeys = Object.keys(properties).filter(key => {
            return !/^\d+$/.test(key) && key !== '_userIds';
        });
        
        if (propertyKeys.length === 0) {
            return '<div class="no-properties">No properties configured. Click "Add Property" to add configuration.</div>';
        }
        
        propertyKeys.forEach(key => {
            const value = properties[key];
            let displayValue = '';
            
            if (typeof value === 'object') {
                displayValue = JSON.stringify(value);
            } else {
                displayValue = value;
            }
            
            propertiesHtml += `
                <div class="property-item" data-property-key="${escapeHtml(key)}">
                    <div class="property-info">
                        <span class="property-key">📌 ${escapeHtml(key)}:</span>
                        <span class="property-value">${escapeHtml(String(displayValue))}</span>
                    </div>
                    <div class="property-actions">
                        <button class="edit-property-btn" onclick="event.stopPropagation(); editProperty('${escapeHtml(configKey)}', '${escapeHtml(key)}', '${escapeHtml(String(displayValue)).replace(/'/g, "\\'")}')">
                            ✏️
                        </button>
                        <button class="delete-property-btn" onclick="event.stopPropagation(); deleteProperty('${escapeHtml(configKey)}', '${escapeHtml(key)}')">
                            🗑️
                        </button>
                    </div>
                </div>
            `;
        });
        
        return propertiesHtml;
    }
    
    // Render assigned users for a config
    function renderAssignedUsers(configKey, userIds) {
        if (!userIds || userIds.length === 0) {
            return '<div style="text-align: center; padding: 20px; color: var(--text-secondary);">No users assigned</div>';
        }
        
        let html = '';
        let hasMissingUsers = false;
        
        userIds.forEach(userId => {
            const user = userCache[userId];
            if (user) {
                html += `
                    <div class="assigned-user-item" data-user-id="${userId}" data-config="${escapeHtml(configKey)}">
                        <div class="user-info">
                            <div class="user-name">👤 ${escapeHtml(user.fullname || 'N/A')} (ID: ${userId}) <span> ${escapeHtml(user.email || 'N/A')}</span></div>
                        </div>
                        <button class="remove-user-btn" onclick="removeUserFromConfig('${escapeHtml(configKey)}', '${userId}')">
                            Remove
                        </button>
                    </div>
                `;
            } else {
                hasMissingUsers = true;
                html += `
                    <div class="assigned-user-item" data-user-id="${userId}" data-config="${escapeHtml(configKey)}">
                        <div class="user-info">
                            <div class="user-name">⏳ Loading user ${userId}...</div>
                            <div class="user-email">Please wait...</div>
                        </div>
                        <button class="remove-user-btn" onclick="removeUserFromConfig('${escapeHtml(configKey)}', '${userId}')" disabled style="opacity:0.5; cursor:not-allowed;">
                            Remove
                        </button>
                    </div>
                `;
            }
        });
        
        if (hasMissingUsers) {
            const missingUserIds = userIds.filter(id => !userCache[id]);
            if (missingUserIds.length > 0) {
                setTimeout(() => {
                    fetchUserDetailsBackground(missingUserIds);
                }, 100);
            }
        }
        
        return html;
    }
    
    function fetchUsersFromTable(userIds, table) {
        return new Promise((resolve) => {
            if (userIds.length === 0) {
                resolve([]);
                return;
            }
            
            fetch('serveraccount.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'get_users_by_ids',
                    user_ids: JSON.stringify(userIds),
                    source_table: table
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resolve(data.users);
                } else {
                    resolve([]);
                }
            })
            .catch(() => resolve([]));
        });
    }
    
    // Toggle config card expansion - COMPLETELY INDEPENDENT
    function toggleConfigCard(headerElement, event) {
        if (event && event.stopPropagation) {
            event.stopPropagation();
        }
        
        const card = headerElement.closest('.config-card');
        const isCurrentlyExpanded = card.classList.contains('expanded');
        
        if (isCurrentlyExpanded) {
            // Collapsing
            card.classList.remove('expanded');
        } else {
            // Expanding - add class first
            card.classList.add('expanded');
            
            // Force browser to recalculate layout without affecting other cards
            // This prevents layout thrashing
            requestAnimationFrame(() => {
                const height = card.scrollHeight;
                card.style.setProperty('--expanded-height', height + 'px');
            });
        }
    }
    
    // Search users for config assignment
    let searchTimeouts = {};
    
    function searchUsers(inputElement, configKey) {
        const searchTerm = inputElement.value.trim();
        const searchKey = configKey.replace(/\./g, '-');
        const resultsDiv = document.getElementById(`search-results-${searchKey}`);
        
        if (searchTimeouts[searchKey]) {
            clearTimeout(searchTimeouts[searchKey]);
        }
        
        if (searchTerm.length < 1) {
            resultsDiv.classList.remove('show');
            resultsDiv.innerHTML = '';
            return;
        }
        
        searchTimeouts[searchKey] = setTimeout(() => {
            const currentUsers = currentConfig[configKey] || [];
            const pendingAdds = pendingChanges[configKey]?.toAdd || [];
            
            const userToConfigMap = {};
            for (const [existingKey, userIds] of Object.entries(currentConfig)) {
                if (Array.isArray(userIds)) {
                    userIds.forEach(userId => {
                        userToConfigMap[userId] = existingKey;
                    });
                }
            }
            
            fetch('serveraccount.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    action: 'search_users_for_config',
                    search: searchTerm,
                    exclude_ids: JSON.stringify([])
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const users = data.users;
                    
                    if (users.length === 0) {
                        resultsDiv.innerHTML = '<div class="search-result-item" style="color: var(--text-secondary);">No users found matching your search criteria.</div>';
                        resultsDiv.classList.add('show');
                        return;
                    }
                    
                    resultsDiv.innerHTML = users.map(user => {
                        const linkedConfig = userToConfigMap[user.id];
                        const isAlreadyInThisConfig = currentUsers.includes(parseInt(user.id));
                        const isPendingAdd = pendingAdds.includes(parseInt(user.id));
                        let configStatusHtml = '';
                        let selectable = true;
                        let onclickAction = `selectUserForConfig('${escapeHtml(configKey)}', ${user.id}, '${escapeHtml(user.fullname || user.username || '')}', '${escapeHtml(user.email || '')}')`;
                        let disabledStyle = '';
                        
                        if (isAlreadyInThisConfig || isPendingAdd) {
                            configStatusHtml = `<div class="search-result-ip-status linked">✅ User is already assigned to THIS Config</div>`;
                            selectable = false;
                            onclickAction = '';
                            disabledStyle = 'style="opacity:0.5; cursor:not-allowed;"';
                        } else if (linkedConfig) {
                            configStatusHtml = `<div class="search-result-ip-status linked-to-other">🔒 User is currently linked to config: <strong>${escapeHtml(linkedConfig)}</strong><br><span style="font-size: 11px;">⚠️ Must be removed from current config first</span></div>`;
                            selectable = false;
                            onclickAction = '';
                            disabledStyle = 'style="opacity:0.5; cursor:not-allowed;"';
                        } else {
                            configStatusHtml = `<div class="search-result-ip-status not-linked">✅ User is not linked to any server config (available to assign)</div>`;
                            selectable = true;
                            disabledStyle = '';
                        }
                        
                        const selectableClass = selectable ? 'search-result-item' : 'search-result-item disabled-search-result';
                        
                        return `
                            <div class="${selectableClass}" onclick="${onclickAction}" ${disabledStyle}>
                                <div class="search-result-name">👤 ${escapeHtml(user.fullname || user.username || 'N/A')} (ID: ${user.id})</div>
                                <div class="search-result-email">📧 ${escapeHtml(user.email || 'N/A')}</div>
                                ${configStatusHtml}
                            </div>
                        `;
                    }).join('');
                    resultsDiv.classList.add('show');
                } else {
                    resultsDiv.innerHTML = '<div class="search-result-item" style="color: #e74c3c;">Error searching users</div>';
                    resultsDiv.classList.add('show');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultsDiv.innerHTML = '<div class="search-result-item" style="color: #e74c3c;">Error searching users</div>';
                resultsDiv.classList.add('show');
            });
        }, 300);
    }
        
    // Select user for config assignment
    function selectUserForConfig(configKey, userId, fullname, email) {
        const searchKey = configKey.replace(/\./g, '-');
        const resultsDiv = document.getElementById(`search-results-${searchKey}`);
        const searchInput = document.querySelector(`#search-results-${searchKey}`).previousElementSibling;
        
        searchInput.value = '';
        resultsDiv.classList.remove('show');
        resultsDiv.innerHTML = '';
        
        if (!pendingChanges[configKey]) {
            pendingChanges[configKey] = { toAdd: [], toRemove: [] };
        }
        
        const currentUsers = currentConfig[configKey] || [];
        if (!currentUsers.includes(userId) && !pendingChanges[configKey].toAdd.includes(userId)) {
            pendingChanges[configKey].toAdd.push(userId);
            updatePendingUsersDisplay(configKey);
            
            if (!userCache[userId]) {
                userCache[userId] = { id: userId, fullname: fullname, email: email };
            }
        }
    }
    
    // Update pending users display
    function updatePendingUsersDisplay(configKey) {
        const searchKey = configKey.replace(/\./g, '-');
        const pendingContainer = document.getElementById(`pending-users-${searchKey}`);
        const pending = pendingChanges[configKey] || { toAdd: [], toRemove: [] };
        
        if (pending.toAdd.length === 0) {
            pendingContainer.innerHTML = '';
            return;
        }
        
        pendingContainer.innerHTML = pending.toAdd.map(userId => {
            const user = userCache[userId] || { fullname: `User ${userId}`, email: '' };
            return `
                <div class="pending-user-tag">
                    <span>➕ ${escapeHtml(user.fullname)} (ID: ${userId})</span>
                    <span class="remove-pending" onclick="removePendingUser('${escapeHtml(configKey)}', '${userId}')">✕</span>
                </div>
            `;
        }).join('');
    }
    
    // Remove pending user
    function removePendingUser(configKey, userId) {
        if (pendingChanges[configKey]) {
            pendingChanges[configKey].toAdd = pendingChanges[configKey].toAdd.filter(id => id != userId);
            updatePendingUsersDisplay(configKey);
        }
    }
    
    // Remove user from config
    function removeUserFromConfig(configKey, userId) {
        showPasswordModalForUserRemoval(configKey, userId);
    }
    
    function showPasswordModalForUserRemoval(configKey, userId) {
        let modal = document.getElementById('user-remove-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'user-remove-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>🔐 Confirm User Removal</h3>
                    <p>You are about to remove user from config: <strong id="remove-config-display"></strong></p>
                    <p style="font-size: 12px; margin-top: 10px;">Please enter your admin password to confirm.</p>
                    <input type="password" id="user-remove-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="user-remove-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="user-remove-confirm" class="modal-confirm-btn" style="background: #e74c3c;">Confirm Removal</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('remove-config-display').textContent = configKey;
        modal.classList.add('show');
        modal.setAttribute('data-pending-config', configKey);
        modal.setAttribute('data-pending-userid', userId);
        
        const passwordInput = document.getElementById('user-remove-password');
        passwordInput.value = '';
        passwordInput.focus();
        
        const confirmBtn = document.getElementById('user-remove-confirm');
        const cancelBtn = document.getElementById('user-remove-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                alert('Password is required');
                passwordInput.focus();
                return;
            }
            const storedConfig = modal.getAttribute('data-pending-config');
            const storedUserId = modal.getAttribute('data-pending-userid');
            modal.classList.remove('show');
            executeUserRemoval(storedConfig, storedUserId, password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        };
    }
    
    function executeUserRemoval(configKey, userId, password) {
        let currentUsers = [...(currentConfig[configKey] || [])];
        
        const index = currentUsers.indexOf(parseInt(userId));
        if (index !== -1) {
            currentUsers.splice(index, 1);
        }
        
        currentUsers.sort((a, b) => a - b);
        
        const newConfig = { ...currentConfig };
        newConfig[configKey] = currentUsers;
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'update_system_config',
                config: JSON.stringify(newConfig),
                admin_password: password,
                login_id: '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentConfig = newConfig;
                if (pendingChanges[configKey]) {
                    pendingChanges[configKey].toRemove = pendingChanges[configKey].toRemove.filter(id => id != userId);
                }
                loadSystemConfig();
                showMessage('✅ User removed successfully!', 'success');
            } else {
                showMessage('❌ Error: ' + (data.error || 'Failed to remove user'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('❌ Error removing user', 'error');
        });
    }
    
    // Save config changes with password confirmation
    function saveConfigChanges(configKey) {
        const pending = pendingChanges[configKey];
        if (!pending || (pending.toAdd.length === 0 && pending.toRemove.length === 0)) {
            showMessage('No changes to save for this config.', 'error');
            return;
        }
        
        showPasswordModalForConfigSave(configKey);
    }
    
    function showPasswordModalForConfigSave(configKey) {
        let modal = document.getElementById('config-save-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'config-save-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>🔐 Confirm Changes</h3>
                    <p>You are about to modify the user assignments for <strong id="save-config-name"></strong></p>
                    <p style="font-size: 12px; margin-top: 10px;">Please enter your admin password to confirm.</p>
                    <input type="password" id="config-save-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="config-save-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="config-save-confirm" class="modal-confirm-btn" style="background: #27ae60;">Confirm Save</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('save-config-name').textContent = configKey;
        modal.classList.add('show');
        modal.setAttribute('data-pending-config', configKey);
        
        const passwordInput = document.getElementById('config-save-password');
        passwordInput.value = '';
        passwordInput.focus();
        
        const confirmBtn = document.getElementById('config-save-confirm');
        const cancelBtn = document.getElementById('config-save-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                alert('Password is required');
                passwordInput.focus();
                return;
            }
            const storedConfig = modal.getAttribute('data-pending-config');
            modal.classList.remove('show');
            executeConfigSave(storedConfig, password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        };
    }
    
    function executeConfigSave(configKey, password) {
        const pending = pendingChanges[configKey];
        if (!pending) return;
        
        let configData = currentConfig[configKey] || [];
        let userIds = [];
        let properties = {};
        
        if (Array.isArray(configData)) {
            if (configData.length > 0 && typeof configData[configData.length - 1] === 'object' && !Array.isArray(configData[configData.length - 1])) {
                properties = configData[configData.length - 1];
                userIds = configData.slice(0, -1);
            } else {
                userIds = [...configData];
                properties = {};
            }
        } else if (typeof configData === 'object') {
            userIds = configData._userIds || [];
            properties = { ...configData };
            delete properties._userIds;
        }
        
        pending.toRemove.forEach(userId => {
            const index = userIds.indexOf(parseInt(userId));
            if (index !== -1) {
                userIds.splice(index, 1);
            }
        });
        
        pending.toAdd.forEach(userId => {
            if (!userIds.includes(parseInt(userId))) {
                userIds.push(parseInt(userId));
            }
        });
        
        userIds.sort((a, b) => a - b);
        
        let newConfigData;
        if (Object.keys(properties).length === 0) {
            newConfigData = userIds;
        } else {
            newConfigData = [...userIds, properties];
        }
        
        const newConfig = { ...currentConfig };
        newConfig[configKey] = newConfigData;
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'update_system_config',
                config: JSON.stringify(newConfig),
                admin_password: password,
                login_id: '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                pendingChanges[configKey] = { toAdd: [], toRemove: [] };
                loadSystemConfig();
                showMessage('✅ Configuration saved successfully!', 'success');
            } else {
                showMessage('❌ Error: ' + (data.error || 'Failed to save'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('❌ Error saving configuration', 'error');
        });
    }
    
    // Delete config value
    function deleteConfigValue(configKey) {
        showPasswordModalForConfigDelete(configKey);
    }
    
    // Edit config key
    function editConfigKey(oldConfigKey) {
        let editModal = document.getElementById('config-edit-input-modal');
        if (!editModal) {
            editModal = document.createElement('div');
            editModal.id = 'config-edit-input-modal';
            editModal.className = 'modal';
            editModal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>✏️ Edit Config Key</h3>
                    <p>Enter new value:</p>
                    <input type="text" id="config-edit-input" class="json-password-input" placeholder="Enter any value..." autocomplete="off" style="width: 100%; padding: 10px; margin: 10px 0; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="config-edit-input-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="config-edit-input-confirm" class="modal-confirm-btn" style="background: #f39c12;">Continue</button>
                    </div>
                </div>
            `;
            document.body.appendChild(editModal);
        }
        
        const inputField = document.getElementById('config-edit-input');
        inputField.value = oldConfigKey;
        inputField.focus();
        inputField.select();
        
        editModal.classList.add('show');
        editModal.setAttribute('data-old-config', oldConfigKey);
        
        const confirmBtn = document.getElementById('config-edit-input-confirm');
        const cancelBtn = document.getElementById('config-edit-input-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const newConfigKey = inputField.value.trim();
            
            if (!newConfigKey) {
                showMessage('❌ Value cannot be empty', 'error');
                inputField.focus();
                return;
            }
            
            if (newConfigKey === oldConfigKey) {
                showMessage('No changes made', 'error');
                editModal.classList.remove('show');
                return;
            }
            
            editModal.classList.remove('show');
            showPasswordModalForConfigEdit(oldConfigKey, newConfigKey);
        };
        
        newCancelBtn.onclick = () => {
            editModal.classList.remove('show');
        };
        
        inputField.onkeypress = (e) => {
            if (e.key === 'Enter') {
                newConfirmBtn.click();
            }
        };
        
        editModal.onclick = (e) => {
            if (e.target === editModal) {
                editModal.classList.remove('show');
            }
        };
    }

    function showPasswordModalForConfigEdit(oldConfigKey, newConfigKey) {
        let modal = document.getElementById('config-edit-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'config-edit-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>✏️ Confirm Config Rename</h3>
                    <p>You are about to rename config key:</p>
                    <p><strong id="edit-old-config-display"></strong> → <strong id="edit-new-config-display"></strong></p>
                    <p style="color: #f39c12; font-size: 12px;">All user assignments will be moved to the new config key.</p>
                    <p style="font-size: 12px; margin-top: 10px;">Please enter your admin password to confirm.</p>
                    <input type="password" id="config-edit-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="config-edit-password-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="config-edit-password-confirm" class="modal-confirm-btn" style="background: #f39c12;">Confirm Rename</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('edit-old-config-display').textContent = oldConfigKey;
        document.getElementById('edit-new-config-display').textContent = newConfigKey;
        modal.classList.add('show');
        modal.setAttribute('data-old-config', oldConfigKey);
        modal.setAttribute('data-new-config', newConfigKey);
        
        const passwordInput = document.getElementById('config-edit-password');
        passwordInput.value = '';
        passwordInput.focus();
        
        const confirmBtn = document.getElementById('config-edit-password-confirm');
        const cancelBtn = document.getElementById('config-edit-password-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                alert('Password is required');
                passwordInput.focus();
                return;
            }
            const storedOldConfig = modal.getAttribute('data-old-config');
            const storedNewConfig = modal.getAttribute('data-new-config');
            modal.classList.remove('show');
            executeConfigEdit(storedOldConfig, storedNewConfig, password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        };
    }

    function executeConfigEdit(oldConfigKey, newConfigKey, password) {
        let oldConfigData = currentConfig[oldConfigKey] || [];
        let userIds = [];
        let properties = {};
        
        if (Array.isArray(oldConfigData)) {
            if (oldConfigData.length > 0 && typeof oldConfigData[oldConfigData.length - 1] === 'object' && !Array.isArray(oldConfigData[oldConfigData.length - 1])) {
                properties = oldConfigData[oldConfigData.length - 1];
                userIds = oldConfigData.slice(0, -1);
            } else {
                userIds = [...oldConfigData];
                properties = {};
            }
        }
        
        const newConfig = { ...currentConfig };
        
        if (newConfig[newConfigKey]) {
            showMessage('❌ Error: Config key "' + newConfigKey + '" already exists!', 'error');
            return;
        }
        
        let newConfigData;
        if (Object.keys(properties).length === 0) {
            newConfigData = userIds;
        } else {
            newConfigData = [...userIds, properties];
        }
        
        newConfig[newConfigKey] = newConfigData;
        delete newConfig[oldConfigKey];
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'update_system_config',
                config: JSON.stringify(newConfig),
                admin_password: password,
                login_id: '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentConfig = newConfig;
                
                if (pendingChanges[oldConfigKey]) {
                    pendingChanges[newConfigKey] = pendingChanges[oldConfigKey];
                    delete pendingChanges[oldConfigKey];
                }
                
                loadSystemConfig();
                showMessage('✅ Config key renamed from "' + oldConfigKey + '" to "' + newConfigKey + '" successfully!', 'success');
            } else {
                showMessage('❌ Error: ' + (data.error || 'Failed to rename config key'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('❌ Error renaming config key', 'error');
        });
    }
    
    function showPasswordModalForConfigDelete(configKey) {
        let modal = document.getElementById('config-delete-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'config-delete-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>⚠️ Confirm Config Deletion</h3>
                    <p>You are about to delete config: <strong id="delete-config-display"></strong></p>
                    <p style="color: #e74c3c; font-size: 12px;">This action cannot be undone! All user assignments for this config will be lost.</p>
                    <p style="font-size: 12px; margin-top: 10px;">Please enter your admin password to confirm.</p>
                    <input type="password" id="config-delete-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="config-delete-password-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="config-delete-password-confirm" class="modal-confirm-btn" style="background: #e74c3c;">Delete Config</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('delete-config-display').textContent = configKey;
        modal.classList.add('show');
        modal.setAttribute('data-delete-config', configKey);
        
        const passwordInput = document.getElementById('config-delete-password');
        passwordInput.value = '';
        passwordInput.focus();
        
        const confirmBtn = document.getElementById('config-delete-password-confirm');
        const cancelBtn = document.getElementById('config-delete-password-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                alert('Password is required');
                passwordInput.focus();
                return;
            }
            const storedConfig = modal.getAttribute('data-delete-config');
            modal.classList.remove('show');
            executeConfigDelete(storedConfig, password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        };
    }
    
    function executeConfigDelete(configKey, password) {
        const newConfig = { ...currentConfig };
        delete newConfig[configKey];
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'update_system_config',
                config: JSON.stringify(newConfig),
                admin_password: password,
                login_id: '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                delete currentConfig[configKey];
                delete pendingChanges[configKey];
                loadSystemConfig();
                showMessage('✅ Config deleted successfully!', 'success');
            } else {
                showMessage('❌ Error: ' + (data.error || 'Failed to delete'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('❌ Error deleting config', 'error');
        });
    }
    
    // Add New Config key
    function addNewConfig() {
        let addModal = document.getElementById('config-add-input-modal');
        if (!addModal) {
            addModal = document.createElement('div');
            addModal.id = 'config-add-input-modal';
            addModal.className = 'modal';
            addModal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>➕ Add New Config Key</h3>
                    <p>Enter the new config key to add:</p>
                    <input type="text" id="config-add-input" class="json-password-input" placeholder="e.g., production_server, staging_config, etc." autocomplete="off" style="width: 100%; padding: 10px; margin: 10px 0; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="config-add-input-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="config-add-input-confirm" class="modal-confirm-btn" style="background: #3498db;">Add Config</button>
                    </div>
                </div>
            `;
            document.body.appendChild(addModal);
        }
        
        const inputField = document.getElementById('config-add-input');
        inputField.value = '';
        inputField.focus();
        
        addModal.classList.add('show');
        
        const confirmBtn = document.getElementById('config-add-input-confirm');
        const cancelBtn = document.getElementById('config-add-input-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const newConfigKey = inputField.value.trim();
            
            if (!newConfigKey) {
                showMessage('❌ Value cannot be empty', 'error');
                inputField.focus();
                return;
            }
            
            if (currentConfig[newConfigKey]) {
                showMessage('❌ Config key "' + newConfigKey + '" already exists!', 'error');
                inputField.focus();
                return;
            }
            
            addModal.classList.remove('show');
            showPasswordModalForConfigAdd(newConfigKey);
        };
        
        newCancelBtn.onclick = () => {
            addModal.classList.remove('show');
        };
        
        inputField.onkeypress = (e) => {
            if (e.key === 'Enter') {
                newConfirmBtn.click();
            }
        };
        
        addModal.onclick = (e) => {
            if (e.target === addModal) {
                addModal.classList.remove('show');
            }
        };
    }

    function showPasswordModalForConfigAdd(newConfigKey) {
        let modal = document.getElementById('config-add-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'config-add-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>🔐 Confirm Add Config</h3>
                    <p>You are about to add new config key:</p>
                    <p><strong id="add-config-display"></strong></p>
                    <p style="color: #3498db; font-size: 12px;">This will create a new config with no users assigned.</p>
                    <p style="font-size: 12px; margin-top: 10px;">Please enter your admin password to confirm.</p>
                    <input type="password" id="config-add-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="config-add-password-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="config-add-password-confirm" class="modal-confirm-btn" style="background: #3498db;">Confirm Add</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('add-config-display').textContent = newConfigKey;
        modal.classList.add('show');
        modal.setAttribute('data-new-config', newConfigKey);
        
        const passwordInput = document.getElementById('config-add-password');
        passwordInput.value = '';
        passwordInput.focus();
        
        const confirmBtn = document.getElementById('config-add-password-confirm');
        const cancelBtn = document.getElementById('config-add-password-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                alert('Password is required');
                passwordInput.focus();
                return;
            }
            const storedNewConfig = modal.getAttribute('data-new-config');
            modal.classList.remove('show');
            executeConfigAdd(storedNewConfig, password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        };
    }

    function executeConfigAdd(newConfigKey, password) {
        const newConfig = { ...currentConfig };
        newConfig[newConfigKey] = [];
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'update_system_config',
                config: JSON.stringify(newConfig),
                admin_password: password,
                login_id: '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentConfig = newConfig;
                pendingChanges[newConfigKey] = { toAdd: [], toRemove: [] };
                loadSystemConfig();
                showMessage('✅ Config key "' + newConfigKey + '" added successfully!', 'success');
            } else {
                showMessage('❌ Error: ' + (data.error || 'Failed to add config'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('❌ Error adding config key', 'error');
        });
    }
    
    // Global Search Functions
    function setupGlobalSearch() {
        const searchInput = document.getElementById('global-search-input');
        const resultsDiv = document.getElementById('global-search-results');
        
        if (!searchInput) return;
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            
            if (globalSearchTimeout) {
                clearTimeout(globalSearchTimeout);
            }
            
            if (searchTerm.length < 1) {
                resultsDiv.classList.remove('show');
                resultsDiv.innerHTML = '';
                return;
            }
            
            globalSearchTimeout = setTimeout(() => {
                performGlobalSearch(searchTerm);
            }, 500);
        });
        
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
                resultsDiv.classList.remove('show');
            }
        });
    }
    
    function performGlobalSearch(searchTerm) {
        const resultsDiv = document.getElementById('global-search-results');
        
        const userToConfigMap = {};
        for (const [configKey, userIds] of Object.entries(currentConfig)) {
            if (Array.isArray(userIds)) {
                userIds.forEach(userId => {
                    userToConfigMap[userId] = configKey;
                });
            }
        }
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'search_users_for_config',
                search: searchTerm,
                exclude_ids: JSON.stringify([])
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.users.length > 0) {
                resultsDiv.innerHTML = data.users.map(user => {
                    const linkedConfig = userToConfigMap[user.id];
                    let configHtml = '';
                    if (linkedConfig) {
                        configHtml = `<div class="global-result-config">🔗 Linked to config: ${escapeHtml(linkedConfig)}</div>`;
                    } else {
                        configHtml = `<div class="global-result-no-config">⚠️ User is not linked to any server config</div>`;
                    }
                    
                    return `
                        <div class="global-search-result-item" onclick="scrollToConfig('${linkedConfig ? escapeHtml(linkedConfig) : ''}', ${user.id})">
                            <div class="global-result-name">👤 ${escapeHtml(user.fullname)} (ID: ${user.id})</div>
                            <div class="global-result-email">📧 ${escapeHtml(user.email)}</div>
                            ${configHtml}
                        </div>
                    `;
                }).join('');
                resultsDiv.classList.add('show');
            } else {
                resultsDiv.innerHTML = '<div class="global-search-result-item" style="color: var(--text-secondary);">No users found</div>';
                resultsDiv.classList.add('show');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            resultsDiv.innerHTML = '<div class="global-search-result-item" style="color: #e74c3c;">Error searching users</div>';
            resultsDiv.classList.add('show');
        });
    }
    
    function scrollToConfig(configKey, userId) {
        const resultsDiv = document.getElementById('global-search-results');
        resultsDiv.classList.remove('show');
        document.getElementById('global-search-input').value = '';
        
        if (!configKey) {
            showMessage(`User ID ${userId} is not linked to any server config.`, 'error');
            return;
        }
        
        const cards = document.querySelectorAll('.config-card');
        for (const card of cards) {
            if (card.getAttribute('data-config') === configKey) {
                card.classList.add('expanded');
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                setTimeout(() => {
                    const userItem = card.querySelector(`.assigned-user-item[data-user-id="${userId}"]`);
                    if (userItem) {
                        userItem.style.backgroundColor = 'rgba(46, 204, 113, 0.2)';
                        userItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        setTimeout(() => {
                            userItem.style.backgroundColor = '';
                        }, 3000);
                    }
                }, 500);
                break;
            }
        }
    }
    
    // Property Management Functions
    function showAddPropertyModal(configKey) {
        let modal = document.getElementById('config-property-add-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'config-property-add-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 450px;">
                    <h3>➕ Add Config Property</h3>
                    <p>Add a new property for config: <strong id="add-property-config-display"></strong></p>
                    <label style="display: block; margin-top: 15px; font-size: 13px; font-weight: 600;">Property Key:</label>
                    <input type="text" id="property-key-input" class="json-password-input" placeholder="e.g., capacity, url, loginid" autocomplete="off" style="width: 100%; padding: 10px; margin: 5px 0;">
                    <label style="display: block; margin-top: 10px; font-size: 13px; font-weight: 600;">Property Value:</label>
                    <input type="text" id="property-value-input" class="json-password-input" placeholder="Enter value" autocomplete="off" style="width: 100%; padding: 10px; margin: 5px 0;">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="property-add-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="property-add-confirm" class="modal-confirm-btn" style="background: #9b59b6;">Add Property</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('add-property-config-display').textContent = configKey;
        const keyInput = document.getElementById('property-key-input');
        const valueInput = document.getElementById('property-value-input');
        keyInput.value = '';
        valueInput.value = '';
        keyInput.focus();
        
        modal.classList.add('show');
        modal.setAttribute('data-config', configKey);
        
        const confirmBtn = document.getElementById('property-add-confirm');
        const cancelBtn = document.getElementById('property-add-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const propertyKey = keyInput.value.trim();
            const propertyValue = valueInput.value.trim();
            
            if (!propertyKey) {
                showMessage('❌ Property key cannot be empty', 'error');
                keyInput.focus();
                return;
            }
            
            const storedConfig = modal.getAttribute('data-config');
            modal.classList.remove('show');
            showPasswordModalForPropertyAdd(storedConfig, propertyKey, propertyValue);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        };
    }

    function showPasswordModalForPropertyAdd(configKey, propertyKey, propertyValue) {
        let modal = document.getElementById('property-add-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'property-add-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>🔐 Confirm Add Property</h3>
                    <p>You are about to add property:</p>
                    <p><strong id="add-prop-key"></strong> = <strong id="add-prop-value"></strong></p>
                    <p style="font-size: 12px; margin-top: 10px;">Please enter your admin password to confirm.</p>
                    <input type="password" id="property-add-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="property-add-password-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="property-add-password-confirm" class="modal-confirm-btn" style="background: #9b59b6;">Confirm Add</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('add-prop-key').textContent = propertyKey;
        document.getElementById('add-prop-value').textContent = propertyValue || '(empty)';
        modal.classList.add('show');
        modal.setAttribute('data-config', configKey);
        modal.setAttribute('data-key', propertyKey);
        modal.setAttribute('data-value', propertyValue);
        
        const passwordInput = document.getElementById('property-add-password');
        passwordInput.value = '';
        passwordInput.focus();
        
        const confirmBtn = document.getElementById('property-add-password-confirm');
        const cancelBtn = document.getElementById('property-add-password-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                alert('Password is required');
                passwordInput.focus();
                return;
            }
            const storedConfig = modal.getAttribute('data-config');
            const storedKey = modal.getAttribute('data-key');
            const storedValue = modal.getAttribute('data-value');
            modal.classList.remove('show');
            executePropertyAdd(storedConfig, storedKey, storedValue, password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        };
    }

    function executePropertyAdd(configKey, propertyKey, propertyValue, password) {
        let configData = currentConfig[configKey] || [];
        let userIds = [];
        let properties = {};
        
        if (Array.isArray(configData)) {
            if (configData.length > 0 && typeof configData[configData.length - 1] === 'object' && !Array.isArray(configData[configData.length - 1])) {
                properties = configData[configData.length - 1];
                userIds = configData.slice(0, -1);
            } else {
                userIds = [...configData];
                properties = {};
            }
        } else if (typeof configData === 'object') {
            userIds = configData._userIds || [];
            properties = { ...configData };
            delete properties._userIds;
        }
        
        properties[propertyKey] = propertyValue;
        
        const newConfigData = [...userIds, properties];
        const newConfig = { ...currentConfig };
        newConfig[configKey] = newConfigData;
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'update_system_config',
                config: JSON.stringify(newConfig),
                admin_password: password,
                login_id: '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentConfig = newConfig;
                loadSystemConfig();
                showMessage('✅ Property "' + propertyKey + '" added successfully!', 'success');
            } else {
                showMessage('❌ Error: ' + (data.error || 'Failed to add property'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('❌ Error adding property', 'error');
        });
    }

    function editProperty(configKey, propertyKey, currentValue) {
        let modal = document.getElementById('config-property-edit-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'config-property-edit-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 450px;">
                    <h3>✏️ Edit Property</h3>
                    <p>Edit property for config: <strong id="edit-property-config-display"></strong></p>
                    <label style="display: block; margin-top: 15px; font-size: 13px; font-weight: 600;">Property Key:</label>
                    <input type="text" id="edit-property-key-input" class="json-password-input" autocomplete="off" style="width: 100%; padding: 10px; margin: 5px 0;">
                    <label style="display: block; margin-top: 10px; font-size: 13px; font-weight: 600;">Property Value:</label>
                    <input type="text" id="edit-property-value-input" class="json-password-input" autocomplete="off" style="width: 100%; padding: 10px; margin: 5px 0;">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="property-edit-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="property-edit-confirm" class="modal-confirm-btn" style="background: #f39c12;">Save Changes</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('edit-property-config-display').textContent = configKey;
        const keyInput = document.getElementById('edit-property-key-input');
        const valueInput = document.getElementById('edit-property-value-input');
        keyInput.value = propertyKey;
        valueInput.value = currentValue;
        keyInput.focus();
        keyInput.select();
        
        modal.classList.add('show');
        modal.setAttribute('data-config', configKey);
        modal.setAttribute('data-old-key', propertyKey);
        
        const confirmBtn = document.getElementById('property-edit-confirm');
        const cancelBtn = document.getElementById('property-edit-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const newKey = keyInput.value.trim();
            const newValue = valueInput.value.trim();
            
            if (!newKey) {
                showMessage('❌ Property key cannot be empty', 'error');
                keyInput.focus();
                return;
            }
            
            const storedConfig = modal.getAttribute('data-config');
            const oldKey = modal.getAttribute('data-old-key');
            modal.classList.remove('show');
            showPasswordModalForPropertyEdit(storedConfig, oldKey, newKey, newValue);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        };
    }

    function showPasswordModalForPropertyEdit(configKey, oldKey, newKey, newValue) {
        let modal = document.getElementById('property-edit-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'property-edit-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>🔐 Confirm Edit Property</h3>
                    <p>You are about to edit property:</p>
                    <p><strong id="edit-prop-old"></strong> → <strong id="edit-prop-new"></strong></p>
                    <p style="font-size: 12px; margin-top: 10px;">Please enter your admin password to confirm.</p>
                    <input type="password" id="property-edit-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="property-edit-password-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="property-edit-password-confirm" class="modal-confirm-btn" style="background: #f39c12;">Confirm Edit</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('edit-prop-old').textContent = oldKey + ' = ' + (newValue || '(empty)');
        document.getElementById('edit-prop-new').textContent = newKey + ' = ' + (newValue || '(empty)');
        modal.classList.add('show');
        modal.setAttribute('data-config', configKey);
        modal.setAttribute('data-old-key', oldKey);
        modal.setAttribute('data-new-key', newKey);
        modal.setAttribute('data-value', newValue);
        
        const passwordInput = document.getElementById('property-edit-password');
        passwordInput.value = '';
        passwordInput.focus();
        
        const confirmBtn = document.getElementById('property-edit-password-confirm');
        const cancelBtn = document.getElementById('property-edit-password-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                alert('Password is required');
                passwordInput.focus();
                return;
            }
            const storedConfig = modal.getAttribute('data-config');
            const storedOldKey = modal.getAttribute('data-old-key');
            const storedNewKey = modal.getAttribute('data-new-key');
            const storedValue = modal.getAttribute('data-value');
            modal.classList.remove('show');
            executePropertyEdit(storedConfig, storedOldKey, storedNewKey, storedValue, password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        };
    }

    function executePropertyEdit(configKey, oldKey, newKey, newValue, password) {
        let configData = currentConfig[configKey] || [];
        let userIds = [];
        let properties = {};
        
        if (Array.isArray(configData)) {
            if (configData.length > 0 && typeof configData[configData.length - 1] === 'object' && !Array.isArray(configData[configData.length - 1])) {
                properties = configData[configData.length - 1];
                userIds = configData.slice(0, -1);
            } else {
                userIds = [...configData];
                properties = {};
            }
        }
        
        delete properties[oldKey];
        properties[newKey] = newValue;
        
        const newConfigData = [...userIds, properties];
        const newConfig = { ...currentConfig };
        newConfig[configKey] = newConfigData;
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'update_system_config',
                config: JSON.stringify(newConfig),
                admin_password: password,
                login_id: '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentConfig = newConfig;
                loadSystemConfig();
                showMessage('✅ Property updated successfully!', 'success');
            } else {
                showMessage('❌ Error: ' + (data.error || 'Failed to update property'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('❌ Error updating property', 'error');
        });
    }

    function deleteProperty(configKey, propertyKey) {
        showPasswordModalForPropertyDelete(configKey, propertyKey);
    }

    function showPasswordModalForPropertyDelete(configKey, propertyKey) {
        let modal = document.getElementById('property-delete-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'property-delete-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>⚠️ Confirm Delete Property</h3>
                    <p>You are about to delete property: <strong id="delete-prop-key"></strong></p>
                    <p style="color: #e74c3c; font-size: 12px;">This action cannot be undone!</p>
                    <p style="font-size: 12px; margin-top: 10px;">Please enter your admin password to confirm.</p>
                    <input type="password" id="property-delete-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="property-delete-password-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="property-delete-password-confirm" class="modal-confirm-btn" style="background: #e74c3c;">Delete Property</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('delete-prop-key').textContent = propertyKey;
        modal.classList.add('show');
        modal.setAttribute('data-config', configKey);
        modal.setAttribute('data-key', propertyKey);
        
        const passwordInput = document.getElementById('property-delete-password');
        passwordInput.value = '';
        passwordInput.focus();
        
        const confirmBtn = document.getElementById('property-delete-password-confirm');
        const cancelBtn = document.getElementById('property-delete-password-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                alert('Password is required');
                passwordInput.focus();
                return;
            }
            const storedConfig = modal.getAttribute('data-config');
            const storedKey = modal.getAttribute('data-key');
            modal.classList.remove('show');
            executePropertyDelete(storedConfig, storedKey, password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        };
    }

    function executePropertyDelete(configKey, propertyKey, password) {
        let configData = currentConfig[configKey] || [];
        let userIds = [];
        let properties = {};
        
        if (Array.isArray(configData)) {
            if (configData.length > 0 && typeof configData[configData.length - 1] === 'object' && !Array.isArray(configData[configData.length - 1])) {
                properties = configData[configData.length - 1];
                userIds = configData.slice(0, -1);
            } else {
                userIds = [...configData];
                properties = {};
            }
        }
        
        delete properties[propertyKey];
        
        let newConfigData;
        if (Object.keys(properties).length === 0) {
            newConfigData = userIds;
        } else {
            newConfigData = [...userIds, properties];
        }
        
        const newConfig = { ...currentConfig };
        newConfig[configKey] = newConfigData;
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'update_system_config',
                config: JSON.stringify(newConfig),
                admin_password: password,
                login_id: '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentConfig = newConfig;
                loadSystemConfig();
                showMessage('✅ Property "' + propertyKey + '" deleted successfully!', 'success');
            } else {
                showMessage('❌ Error: ' + (data.error || 'Failed to delete property'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('❌ Error deleting property', 'error');
        });
    }
    
    // Helper Functions
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showMessage(message, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message';
        messageDiv.innerHTML = '<span style="color:' + (type === 'error' ? '#e74c3c' : '#2ecc71') + ';">' + message + '</span>';
        const container = document.querySelector('.container');
        if (!container) return;
        const existingMessage = container.querySelector('.message');
        if (existingMessage) existingMessage.remove();
        container.insertBefore(messageDiv, container.firstChild);
        setTimeout(() => messageDiv.remove(), 3000);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        loadSystemConfig();
        setupGlobalSearch();
    });
</script>
