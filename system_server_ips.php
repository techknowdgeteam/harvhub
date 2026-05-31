<?php
// system_server_ips.php - System Server IP Management
// This file is included in serveraccount.php when view=system_ips
?>

<style>
    /* System IP Management Styles */
    .ip-management-container {
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .ip-header-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .refresh-ip-btn {
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
    
    .refresh-ip-btn:hover {
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
    
    .global-result-ip {
        font-size: 12px;
        color: var(--accent-color);
        font-family: monospace;
        margin-top: 5px;
        padding: 4px 8px;
        background: rgba(52, 152, 219, 0.1);
        border-radius: 5px;
        display: inline-block;
    }
    
    .global-result-no-ip {
        font-size: 12px;
        color: #e74c3c;
        margin-top: 5px;
        padding: 4px 8px;
        background: rgba(231, 76, 60, 0.1);
        border-radius: 5px;
        display: inline-block;
    }
    
    .ip-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(480px, 1fr));
        gap: 20px;
    }
    
    .ip-card {
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .ip-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .ip-card-header {
        background: var(--bg-primary);
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        transition: background 0.2s ease;
    }
    
    .ip-card-header:hover {
        background: var(--bg-hover);
    }
    
    .ip-address {
        font-size: 16px;
        font-weight: 600;
        font-family: monospace;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        height: 50px;
    }
    
    .ip-badge {
        background: var(--accent-color);
        color: white;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: normal;
    }
    
    .ip-card-body {
        padding: 15px;
        display: none;
    }
    
    .ip-card.expanded .ip-card-body {
        display: block;
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
    
    .user-info {
        flex: 1;
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
    }
    
    .search-container-ip {
        position: relative;
        margin-bottom: 10px;
    }
    
    .search-input-ip {
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
    
    .save-ip-changes {
        width: 100%;
        margin-top: 15px;
        padding: 10px;
        background: #27ae60;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: background 0.2s ease;
    }
    
    .save-ip-changes:hover {
        background: #219a52;
    }
    
    .delete-ip-btn {
        background: #e74c3c;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: background 0.2s ease;
        width: 100%;
        margin-top: 10px;
    }
    
    .delete-ip-btn:hover {
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
    
    @media (max-width: 768px) {
        .ip-grid {
            grid-template-columns: 1fr;
        }
        
        .ip-card-header {
            flex-direction: column;
            gap: 10px;
            align-items: flex-start;
        }
    }
    .edit-ip-btn {
        background: #f39c12;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: background 0.2s ease;
        width: 100%;
        margin-top: 10px;
    }

    .edit-ip-btn:hover {
        background: #e67e22;
    }
    .add-users-section {
        border-top: 1px solid var(--border-color);
        padding-top: 15px;
        margin-top: 10px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .save-ip-changes,
    .edit-ip-btn,
    .delete-ip-btn {
        width: 100%;
        padding: 10px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: background 0.2s ease;
        border: none;
    }

    .save-ip-changes {
        background: #27ae60;
        color: white;
    }

    .save-ip-changes:hover {
        background: #219a52;
    }

    .edit-ip-btn {
        background: #f39c12;
        color: white;
    }

    .edit-ip-btn:hover {
        background: #e67e22;
    }

    .delete-ip-btn {
        background: #e74c3c;
        color: white;
        margin-top: 0;
    }

    .delete-ip-btn:hover {
        background: #c0392b;
    }
    /* Custom IP Edit Input Styles */
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
    .add-ip-btn {
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

    .add-ip-btn:hover {
        background: #2980b9;
    }
</style>

<div class="ip-management-container">
    <div class="ip-header-actions">
        <h2 style="font-size: 20px;">🌐 System Servers IP Management</h2>
        <div style="display: flex; gap: 10px;">
            <button class="refresh-ip-btn" onclick="loadSystemIpConfig()">
                🔄 Refresh
            </button>
            <button class="add-ip-btn" onclick="addNewIpAddress()">
            ➕ Add New IP
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
    
    <div id="ip-config-container">
        <div class="loading-spinner">Loading IP configuration...</div>
    </div>
</div>

<script>
    let currentIpConfig = {};
    let pendingChanges = {};
    let userCache = {};
    let globalSearchTimeout = null;
    
    // Load system IP configuration
    function loadSystemIpConfig() {
        const container = document.getElementById('ip-config-container');
        container.innerHTML = '<div class="loading-spinner">Loading IP configuration...</div>';
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_system_ip_config'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentIpConfig = data.config || {};
                renderIpConfig(currentIpConfig);
            } else {
                container.innerHTML = '<div class="empty-state">❌ Error loading configuration: ' + escapeHtml(data.error) + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div class="empty-state">❌ Error loading IP configuration</div>';
        });
    }
    
    // Render IP configuration
    function renderIpConfig(config) {
        const container = document.getElementById('ip-config-container');
        const ipAddresses = Object.keys(config);
        
        if (ipAddresses.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div style="font-size: 48px; margin-bottom: 20px;">🌐</div>
                    <h3>No Server IPs Configured</h3>
                    <p>No server IP addresses have been configured.</p>
                </div>
            `;
            return;
        }
        
        // Fetch all user details for all IPs
        const allUserIds = [];
        ipAddresses.forEach(ip => {
            const userIds = config[ip] || [];
            userIds.forEach(id => {
                if (!allUserIds.includes(id)) allUserIds.push(id);
            });
        });
        
        if (allUserIds.length > 0) {
            fetchUserDetails(allUserIds);
        }
        
        let html = '<div class="ip-grid">';
        
        ipAddresses.forEach(ip => {
            const userIds = config[ip] || [];
            const userCount = userIds.length;

            html += `
                <div class="ip-card" data-ip="${escapeHtml(ip)}">
                    <div class="ip-card-header" onclick="toggleIpCard(this)">
                        <div class="ip-address">
                            <span>🌐 ${escapeHtml(ip)}</span>
                            <span class="ip-badge">${userCount} user${userCount !== 1 ? 's' : ''}</span>
                        </div>
                    </div>
                    <div class="ip-card-body">
                        <div class="assigned-users-section">
                            <div class="assigned-users-title">
                                <span>📋 Assigned Users (${userCount})</span>
                            </div>
                            <div class="assigned-users-list" id="users-list-${escapeHtml(ip).replace(/\./g, '-')}">
                                ${renderAssignedUsers(ip, userIds)}
                            </div>
                        </div>
                        <div class="add-users-section">
                            <div class="assigned-users-title">
                                <span>➕ Add Users to ${escapeHtml(ip)}</span>
                            </div>
                            <div class="search-container-ip">
                                <input type="text" class="search-input-ip" placeholder="Search by name, email, or ID..." 
                                    onkeyup="searchUsers(this, '${escapeHtml(ip)}')" autocomplete="off">
                                <div class="search-results" id="search-results-${escapeHtml(ip).replace(/\./g, '-')}"></div>
                            </div>
                            <div class="pending-users" id="pending-users-${escapeHtml(ip).replace(/\./g, '-')}"></div>
                            <button class="save-ip-changes" onclick="saveIpChanges('${escapeHtml(ip)}')">
                                💾 Save Changes for ${escapeHtml(ip)}
                            </button>
                            <button class="edit-ip-btn" onclick="editIpAddress('${escapeHtml(ip)}')">
                                ✏️ Edit IP Address
                            </button>
                            <button class="delete-ip-btn" onclick="deleteIpAddress('${escapeHtml(ip)}')">
                                🗑️ Delete IP Address
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
        // Initialize pending changes tracking
        ipAddresses.forEach(ip => {
            if (!pendingChanges[ip]) {
                pendingChanges[ip] = {
                    toAdd: [],
                    toRemove: []
                };
            }
        });
    }
    
    // Render assigned users for an IP
    function renderAssignedUsers(ip, userIds) {
        if (!userIds || userIds.length === 0) {
            return '<div style="text-align: center; padding: 20px; color: var(--text-secondary);">No users assigned</div>';
        }
        
        let html = '';
        userIds.forEach(userId => {
            const user = userCache[userId];
            if (user) {
                html += `
                    <div class="assigned-user-item" data-user-id="${userId}" data-ip="${escapeHtml(ip)}">
                        <div class="user-info">
                            <div class="user-name">👤 ${escapeHtml(user.fullname || 'N/A')} (ID: ${userId}) <br> ${escapeHtml(user.email || 'N/A')}</div>
                        </div>
                        <button class="remove-user-btn" onclick="removeUserFromIp('${escapeHtml(ip)}', '${userId}')">
                            Remove
                        </button>
                    </div>
                `;
            } else {
                html += `
                    <div class="assigned-user-item" data-user-id="${userId}" data-ip="${escapeHtml(ip)}">
                        <div class="user-info">
                            <div class="user-name">Loading user ${userId}...</div>
                        </div>
                        <button class="remove-user-btn" onclick="removeUserFromIp('${escapeHtml(ip)}', '${userId}')">
                            Remove
                        </button>
                    </div>
                `;
            }
        });
        
        return html;
    }
    
    // Fetch user details for multiple user IDs
    function fetchUserDetails(userIds) {
        const uniqueIds = [...new Set(userIds)];
        const idsToFetch = uniqueIds.filter(id => !userCache[id]);
        
        if (idsToFetch.length === 0) return;
        
        // Try both tables
        Promise.all([
            fetchUsersFromTable(idsToFetch, '<?= $insidersTable ?>'),
            fetchUsersFromTable(idsToFetch, '<?= $insidersServerTable ?>')
        ]).then(([users1, users2]) => {
            const allUsers = [...users1, ...users2];
            allUsers.forEach(user => {
                userCache[user.id] = user;
            });
            
            // Re-render to show loaded users
            renderIpConfig(currentIpConfig);
        });
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
    
    // Toggle IP card expansion
    function toggleIpCard(headerElement) {
        const card = headerElement.closest('.ip-card');
        card.classList.toggle('expanded');
    }
    
    // Search users for IP assignment - FIXED to always show users even if linked
    let searchTimeouts = {};
    
    function searchUsers(inputElement, ip) {
        const searchTerm = inputElement.value.trim();
        const searchKey = ip.replace(/\./g, '-');
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
            // Get current users for this IP and pending adds
            const currentUsers = currentIpConfig[ip] || [];
            const pendingAdds = pendingChanges[ip]?.toAdd || [];
            const excludeIds = [...currentUsers, ...pendingAdds];
            
            // Build reverse mapping: user_id -> ip for ALL users
            const userToIpMap = {};
            for (const [existingIp, userIds] of Object.entries(currentIpConfig)) {
                if (Array.isArray(userIds)) {
                    userIds.forEach(userId => {
                        userToIpMap[userId] = existingIp;
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
                    action: 'search_users_for_ip',
                    search: searchTerm,
                    exclude_ids: JSON.stringify([]) // DON'T exclude anyone from search results
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
                        const linkedIp = userToIpMap[user.id];
                        const isAlreadyInThisIp = currentUsers.includes(parseInt(user.id));
                        const isPendingAdd = pendingAdds.includes(parseInt(user.id));
                        let ipStatusHtml = '';
                        let selectable = true;
                        let onclickAction = `selectUserForIp('${escapeHtml(ip)}', ${user.id}, '${escapeHtml(user.fullname || user.fullname || '')}', '${escapeHtml(user.email || '')}')`;
                        let disabledStyle = '';
                        
                        if (isAlreadyInThisIp || isPendingAdd) {
                            ipStatusHtml = `<div class="search-result-ip-status linked">✅ User is already assigned to this IP</div>`;
                            selectable = false;
                            onclickAction = '';
                            disabledStyle = 'style="opacity:0.5; cursor:not-allowed;"';
                        } else if (linkedIp) {
                            ipStatusHtml = `<div class="search-result-ip-status linked-to-other">🔗 User is currently linked to IP: <strong>${escapeHtml(linkedIp)}</strong></div>`;
                            // Still selectable - can be moved/reassigned
                            selectable = true;
                            disabledStyle = '';
                        } else {
                            ipStatusHtml = `<div class="search-result-ip-status not-linked">⚠️ User is not linked to any server IP (available to assign)</div>`;
                            selectable = true;
                            disabledStyle = '';
                        }
                        
                        const selectableClass = selectable ? 'search-result-item' : 'search-result-item disabled-search-result';
                        
                        return `
                            <div class="${selectableClass}" onclick="${onclickAction}" ${disabledStyle}>
                                <div class="search-result-name">👤 ${escapeHtml(user.fullname || user.fullname || 'N/A')} (ID: ${user.id})</div>
                                <div class="search-result-email">📧 ${escapeHtml(user.email || 'N/A')}</div>
                                ${ipStatusHtml}
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
        
    // Select user for IP assignment
    function selectUserForIp(ip, userId, fullname, email) {
        const searchKey = ip.replace(/\./g, '-');
        const resultsDiv = document.getElementById(`search-results-${searchKey}`);
        const searchInput = document.querySelector(`#search-results-${searchKey}`).previousElementSibling;
        
        // Clear search
        searchInput.value = '';
        resultsDiv.classList.remove('show');
        resultsDiv.innerHTML = '';
        
        // Initialize pending changes for this IP if needed
        if (!pendingChanges[ip]) {
            pendingChanges[ip] = { toAdd: [], toRemove: [] };
        }
        
        // Check if user is already assigned (should not happen due to exclude in search, but double-check)
        const currentUsers = currentIpConfig[ip] || [];
        if (!currentUsers.includes(userId) && !pendingChanges[ip].toAdd.includes(userId)) {
            pendingChanges[ip].toAdd.push(userId);
            updatePendingUsersDisplay(ip);
            
            // Cache user info
            if (!userCache[userId]) {
                userCache[userId] = { id: userId, fullname: fullname, email: email };
            }
        }
    }
    
    // Update pending users display
    function updatePendingUsersDisplay(ip) {
        const searchKey = ip.replace(/\./g, '-');
        const pendingContainer = document.getElementById(`pending-users-${searchKey}`);
        const pending = pendingChanges[ip] || { toAdd: [], toRemove: [] };
        
        if (pending.toAdd.length === 0) {
            pendingContainer.innerHTML = '';
            return;
        }
        
        pendingContainer.innerHTML = pending.toAdd.map(userId => {
            const user = userCache[userId] || { fullname: `User ${userId}`, email: '' };
            return `
                <div class="pending-user-tag">
                    <span>➕ ${escapeHtml(user.fullname)} (ID: ${userId})</span>
                    <span class="remove-pending" onclick="removePendingUser('${escapeHtml(ip)}', '${userId}')">✕</span>
                </div>
            `;
        }).join('');
    }
    
    // Remove pending user
    function removePendingUser(ip, userId) {
        if (pendingChanges[ip]) {
            pendingChanges[ip].toAdd = pendingChanges[ip].toAdd.filter(id => id != userId);
            updatePendingUsersDisplay(ip);
        }
    }
    
    // Remove user from IP
    function removeUserFromIp(ip, userId) {
        showPasswordModalForUserRemoval(ip, userId);
    }
    
    function showPasswordModalForUserRemoval(ip, userId) {
        let modal = document.getElementById('user-remove-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'user-remove-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>🔐 Confirm User Removal</h3>
                    <p>You are about to remove user from IP: <strong id="remove-ip-display"></strong></p>
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
        
        document.getElementById('remove-ip-display').textContent = ip;
        modal.classList.add('show');
        modal.setAttribute('data-pending-ip', ip);
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
            const storedIp = modal.getAttribute('data-pending-ip');
            const storedUserId = modal.getAttribute('data-pending-userid');
            modal.classList.remove('show');
            executeUserRemoval(storedIp, storedUserId, password);
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
    
    function executeUserRemoval(ip, userId, password) {
        let currentUsers = [...(currentIpConfig[ip] || [])];
        
        const index = currentUsers.indexOf(parseInt(userId));
        if (index !== -1) {
            currentUsers.splice(index, 1);
        }
        
        currentUsers.sort((a, b) => a - b);
        
        const newConfig = { ...currentIpConfig };
        newConfig[ip] = currentUsers;
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'update_system_ip_config',
                config: JSON.stringify(newConfig),
                admin_password: password,
                login_id: '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentIpConfig = newConfig;
                if (pendingChanges[ip]) {
                    pendingChanges[ip].toRemove = pendingChanges[ip].toRemove.filter(id => id != userId);
                }
                loadSystemIpConfig();
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
    
    // Save IP changes with password confirmation
    function saveIpChanges(ip) {
        const pending = pendingChanges[ip];
        if (!pending || (pending.toAdd.length === 0 && pending.toRemove.length === 0)) {
            showMessage('No changes to save for this IP.', 'error');
            return;
        }
        
        showPasswordModalForIpSave(ip);
    }
    
    function showPasswordModalForIpSave(ip) {
        let modal = document.getElementById('ip-save-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'ip-save-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>🔐 Confirm Changes</h3>
                    <p>You are about to modify the user assignments for <strong id="save-ip-name"></strong></p>
                    <p style="font-size: 12px; margin-top: 10px;">Please enter your admin password to confirm.</p>
                    <input type="password" id="ip-save-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="ip-save-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="ip-save-confirm" class="modal-confirm-btn" style="background: #27ae60;">Confirm Save</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('save-ip-name').textContent = ip;
        modal.classList.add('show');
        modal.setAttribute('data-pending-ip', ip);
        
        const passwordInput = document.getElementById('ip-save-password');
        passwordInput.value = '';
        passwordInput.focus();
        
        const confirmBtn = document.getElementById('ip-save-confirm');
        const cancelBtn = document.getElementById('ip-save-cancel');
        
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
            const storedIp = modal.getAttribute('data-pending-ip');
            modal.classList.remove('show');
            executeIpSave(storedIp, password);
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
    
    function executeIpSave(ip, password) {
        const pending = pendingChanges[ip];
        if (!pending) return;
        
        let currentUsers = [...(currentIpConfig[ip] || [])];
        
        pending.toRemove.forEach(userId => {
            const index = currentUsers.indexOf(parseInt(userId));
            if (index !== -1) {
                currentUsers.splice(index, 1);
            }
        });
        
        pending.toAdd.forEach(userId => {
            if (!currentUsers.includes(parseInt(userId))) {
                currentUsers.push(parseInt(userId));
            }
        });
        
        currentUsers.sort((a, b) => a - b);
        
        const newConfig = { ...currentIpConfig };
        newConfig[ip] = currentUsers;
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'update_system_ip_config',
                config: JSON.stringify(newConfig),
                admin_password: password,
                login_id: '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                pendingChanges[ip] = { toAdd: [], toRemove: [] };
                loadSystemIpConfig();
                showMessage('✅ IP configuration saved successfully!', 'success');
            } else {
                showMessage('❌ Error: ' + (data.error || 'Failed to save'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('❌ Error saving IP configuration', 'error');
        });
    }
    
    // Delete IP address
    function deleteIpAddress(ip) {
        showPasswordModalForIpDelete(ip);
    }
    // Edit IP address - rename existing IP with custom modal
    function editIpAddress(oldIp) {
        // Create custom input modal
        let editModal = document.getElementById('ip-edit-input-modal');
        if (!editModal) {
            editModal = document.createElement('div');
            editModal.id = 'ip-edit-input-modal';
            editModal.className = 'modal';
            editModal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>✏️ Edit IP Address</h3>
                    <p>Enter new value:</p>
                    <input type="text" id="ip-edit-input" class="json-password-input" placeholder="Enter any value..." autocomplete="off" style="width: 100%; padding: 10px; margin: 10px 0; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="ip-edit-input-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="ip-edit-input-confirm" class="modal-confirm-btn" style="background: #f39c12;">Continue</button>
                    </div>
                </div>
            `;
            document.body.appendChild(editModal);
        }
        
        // Set the current IP as default value
        const inputField = document.getElementById('ip-edit-input');
        inputField.value = oldIp;
        inputField.focus();
        inputField.select();
        
        editModal.classList.add('show');
        editModal.setAttribute('data-old-ip', oldIp);
        
        const confirmBtn = document.getElementById('ip-edit-input-confirm');
        const cancelBtn = document.getElementById('ip-edit-input-cancel');
        
        // Remove old event listeners by cloning
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const newIp = inputField.value.trim();
            
            // Validate input - ONLY check if empty
            if (!newIp) {
                showMessage('❌ Value cannot be empty', 'error');
                inputField.focus();
                return;
            }
            
            if (newIp === oldIp) {
                showMessage('No changes made', 'error');
                editModal.classList.remove('show');
                return;
            }
            
            // REMOVED IP PATTERN VALIDATION - allow any value
            
            editModal.classList.remove('show');
            showPasswordModalForIpEdit(oldIp, newIp);
        };
        
        newCancelBtn.onclick = () => {
            editModal.classList.remove('show');
        };
        
        // Allow Enter key to submit
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

    function showPasswordModalForIpEdit(oldIp, newIp) {
        let modal = document.getElementById('ip-edit-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'ip-edit-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>✏️ Confirm IP Rename</h3>
                    <p>You are about to rename IP address:</p>
                    <p><strong id="edit-old-ip-display"></strong> → <strong id="edit-new-ip-display"></strong></p>
                    <p style="color: #f39c12; font-size: 12px;">All user assignments will be moved to the new IP address.</p>
                    <p style="font-size: 12px; margin-top: 10px;">Please enter your admin password to confirm.</p>
                    <input type="password" id="ip-edit-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="ip-edit-password-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="ip-edit-password-confirm" class="modal-confirm-btn" style="background: #f39c12;">Confirm Rename</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('edit-old-ip-display').textContent = oldIp;
        document.getElementById('edit-new-ip-display').textContent = newIp;
        modal.classList.add('show');
        modal.setAttribute('data-old-ip', oldIp);
        modal.setAttribute('data-new-ip', newIp);
        
        const passwordInput = document.getElementById('ip-edit-password');
        passwordInput.value = '';
        passwordInput.focus();
        
        const confirmBtn = document.getElementById('ip-edit-password-confirm');
        const cancelBtn = document.getElementById('ip-edit-password-cancel');
        
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
            const storedOldIp = modal.getAttribute('data-old-ip');
            const storedNewIp = modal.getAttribute('data-new-ip');
            modal.classList.remove('show');
            executeIpEdit(storedOldIp, storedNewIp, password);
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

    function executeIpEdit(oldIp, newIp, password) {
        // Create new config with the IP renamed
        const newConfig = { ...currentIpConfig };
        
        // Check if new IP already exists
        if (newConfig[newIp]) {
            showMessage('❌ Error: IP address ' + newIp + ' already exists!', 'error');
            return;
        }
        
        // Move the users from old IP to new IP
        newConfig[newIp] = newConfig[oldIp];
        delete newConfig[oldIp];
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'update_system_ip_config',
                config: JSON.stringify(newConfig),
                admin_password: password,
                login_id: '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update local config
                currentIpConfig = newConfig;
                
                // Update pending changes reference if exists
                if (pendingChanges[oldIp]) {
                    pendingChanges[newIp] = pendingChanges[oldIp];
                    delete pendingChanges[oldIp];
                }
                
                loadSystemIpConfig();
                showMessage('✅ IP address renamed from ' + oldIp + ' to ' + newIp + ' successfully!', 'success');
            } else {
                showMessage('❌ Error: ' + (data.error || 'Failed to rename IP'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('❌ Error renaming IP address', 'error');
        });
    }
    
    function showPasswordModalForIpDelete(ip) {
        let modal = document.getElementById('ip-delete-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'ip-delete-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>⚠️ Confirm IP Deletion</h3>
                    <p>You are about to delete IP: <strong id="delete-ip-display"></strong></p>
                    <p style="color: #e74c3c; font-size: 12px;">This action cannot be undone! All user assignments for this IP will be lost.</p>
                    <p style="font-size: 12px; margin-top: 10px;">Please enter your admin password to confirm.</p>
                    <input type="password" id="ip-delete-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="ip-delete-password-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="ip-delete-password-confirm" class="modal-confirm-btn" style="background: #e74c3c;">Delete IP</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('delete-ip-display').textContent = ip;
        modal.classList.add('show');
        modal.setAttribute('data-delete-ip', ip);
        
        const passwordInput = document.getElementById('ip-delete-password');
        passwordInput.value = '';
        passwordInput.focus();
        
        const confirmBtn = document.getElementById('ip-delete-password-confirm');
        const cancelBtn = document.getElementById('ip-delete-password-cancel');
        
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
            const storedIp = modal.getAttribute('data-delete-ip');
            modal.classList.remove('show');
            executeIpDelete(storedIp, password);
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
    
    function executeIpDelete(ip, password) {
        const newConfig = { ...currentIpConfig };
        delete newConfig[ip];
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'update_system_ip_config',
                config: JSON.stringify(newConfig),
                admin_password: password,
                login_id: '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                delete currentIpConfig[ip];
                delete pendingChanges[ip];
                loadSystemIpConfig();
                showMessage('✅ IP address deleted successfully!', 'success');
            } else {
                showMessage('❌ Error: ' + (data.error || 'Failed to delete'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('❌ Error deleting IP address', 'error');
        });
    }
    
    // Global Search Function
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
        
        // Close results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
                resultsDiv.classList.remove('show');
            }
        });
    }
    
    function performGlobalSearch(searchTerm) {
        const resultsDiv = document.getElementById('global-search-results');
        
        // Build reverse mapping: user_id -> ip
        const userToIpMap = {};
        for (const [ip, userIds] of Object.entries(currentIpConfig)) {
            if (Array.isArray(userIds)) {
                userIds.forEach(userId => {
                    userToIpMap[userId] = ip;
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
                action: 'search_users_for_ip',
                search: searchTerm,
                exclude_ids: JSON.stringify([])
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.users.length > 0) {
                resultsDiv.innerHTML = data.users.map(user => {
                    const linkedIp = userToIpMap[user.id];
                    let ipHtml = '';
                    if (linkedIp) {
                        ipHtml = `<div class="global-result-ip">🔗 Linked to IP: ${escapeHtml(linkedIp)}</div>`;
                    } else {
                        ipHtml = `<div class="global-result-no-ip">⚠️ User is not linked to any server IP</div>`;
                    }
                    
                    return `
                        <div class="global-search-result-item" onclick="scrollToIp('${linkedIp ? escapeHtml(linkedIp) : ''}', ${user.id})">
                            <div class="global-result-name">👤 ${escapeHtml(user.fullname)} (ID: ${user.id})</div>
                            <div class="global-result-email">📧 ${escapeHtml(user.email)}</div>
                            ${ipHtml}
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
    
    function scrollToIp(ip, userId) {
        const resultsDiv = document.getElementById('global-search-results');
        resultsDiv.classList.remove('show');
        document.getElementById('global-search-input').value = '';
        
        if (!ip) {
            showMessage(`User ID ${userId} is not linked to any server IP.`, 'error');
            return;
        }
        
        // Find and expand the IP card
        const cards = document.querySelectorAll('.ip-card');
        for (const card of cards) {
            if (card.getAttribute('data-ip') === ip) {
                card.classList.add('expanded');
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                // Highlight the user in the list
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
    
    // Close modals when clicking outside
    document.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal && modal.style.display === 'flex') {
                modal.style.display = 'none';
            }
            if (event.target === modal && modal.classList.contains('show')) {
                modal.classList.remove('show');
            }
        });
    });
    
    // Show message helper
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
    // Add new IP address
    function addNewIpAddress() {
        // Create custom input modal
        let addModal = document.getElementById('ip-add-input-modal');
        if (!addModal) {
            addModal = document.createElement('div');
            addModal.id = 'ip-add-input-modal';
            addModal.className = 'modal';
            addModal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>➕ Add New IP Address</h3>
                    <p>Enter the new IP address to add:</p>
                    <input type="text" id="ip-add-input" class="json-password-input" placeholder="e.g., 192.168.1.100 or any value" autocomplete="off" style="width: 100%; padding: 10px; margin: 10px 0; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary);">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="ip-add-input-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="ip-add-input-confirm" class="modal-confirm-btn" style="background: #3498db;">Add IP</button>
                    </div>
                </div>
            `;
            document.body.appendChild(addModal);
        }
        
        const inputField = document.getElementById('ip-add-input');
        inputField.value = '';
        inputField.focus();
        
        addModal.classList.add('show');
        
        const confirmBtn = document.getElementById('ip-add-input-confirm');
        const cancelBtn = document.getElementById('ip-add-input-cancel');
        
        // Remove old event listeners by cloning
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const newIp = inputField.value.trim();
            
            // Validate input - ONLY check if empty
            if (!newIp) {
                showMessage('❌ Value cannot be empty', 'error');
                inputField.focus();
                return;
            }
            
            // REMOVED IP PATTERN VALIDATION - allow any value
            
            // Check if key already exists
            if (currentIpConfig[newIp]) {
                showMessage('❌ Key "' + newIp + '" already exists!', 'error');
                inputField.focus();
                return;
            }
            
            addModal.classList.remove('show');
            showPasswordModalForIpAdd(newIp);
        };
        
        newCancelBtn.onclick = () => {
            addModal.classList.remove('show');
        };
        
        // Allow Enter key to submit
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

    function showPasswordModalForIpAdd(newIp) {
        let modal = document.getElementById('ip-add-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'ip-add-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>🔐 Confirm Add IP</h3>
                    <p>You are about to add new IP address:</p>
                    <p><strong id="add-ip-display"></strong></p>
                    <p style="color: #3498db; font-size: 12px;">This will create a new IP with no users assigned.</p>
                    <p style="font-size: 12px; margin-top: 10px;">Please enter your admin password to confirm.</p>
                    <input type="password" id="ip-add-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="ip-add-password-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="ip-add-password-confirm" class="modal-confirm-btn" style="background: #3498db;">Confirm Add</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('add-ip-display').textContent = newIp;
        modal.classList.add('show');
        modal.setAttribute('data-new-ip', newIp);
        
        const passwordInput = document.getElementById('ip-add-password');
        passwordInput.value = '';
        passwordInput.focus();
        
        const confirmBtn = document.getElementById('ip-add-password-confirm');
        const cancelBtn = document.getElementById('ip-add-password-cancel');
        
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
            const storedNewIp = modal.getAttribute('data-new-ip');
            modal.classList.remove('show');
            executeIpAdd(storedNewIp, password);
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

    function executeIpAdd(newIp, password) {
        // Create new config with the IP added as empty array
        const newConfig = { ...currentIpConfig };
        newConfig[newIp] = [];
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'update_system_ip_config',
                config: JSON.stringify(newConfig),
                admin_password: password,
                login_id: '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update local config
                currentIpConfig = newConfig;
                
                // Initialize pending changes for new IP
                pendingChanges[newIp] = {
                    toAdd: [],
                    toRemove: []
                };
                
                loadSystemIpConfig();
                showMessage('✅ IP address ' + newIp + ' added successfully!', 'success');
            } else {
                showMessage('❌ Error: ' + (data.error || 'Failed to add IP'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('❌ Error adding IP address', 'error');
        });
    }
    
    // Escape HTML helper
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
        loadSystemIpConfig();
        setupGlobalSearch();
    });
</script>
