<?php
// accountmanagement.php
?>

<h2>Account Management</h2>

<!-- Search Bar -->
<div class="management-search">
    <div class="search-wrapper">
        <input type="text" id="global-user-search" class="global-search-input" placeholder="🔍 Search users by ID, Email, or Full Name...">
        <button id="clear-global-search" class="clear-search-btn" style="display: none;">✕</button>
    </div>
</div>

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
            <h3>Users List</h3>
            <div style="padding: 8px 12px; border-bottom: 1px solid var(--border-color);">
                <input type="text" id="user-list-search" class="search-input" placeholder="🔍 Search by name, email or ID..." style="width: 100%; padding: 8px; font-size: 12px;">
            </div>
            <div id="user-items-list" class="user-items" style="max-height: 450px; overflow-y: auto;">
                <div style="text-align: center; padding: 20px;">Loading users...</div>
            </div>
        </div>
        <div class="management-panel">
            <div class="management-header">
                <h3>User Account Configuration</h3>
                <div class="header-buttons">
                    <button type="button" class="edit-json-btn-header" id="user-edit-btn" onclick="toggleEditMode('user')" disabled>✏️ Edit JSON</button>
                    <button type="button" class="copy-json-btn-header" id="user-copy-btn" onclick="copyJsonToClipboard('user')" style="background: #3498db; display: none;">📋 Copy JSON</button>
                    <button type="button" class="cancel-edit-btn" id="user-cancel-btn" style="display:none;" onclick="cancelEdit('user')">Cancel</button>
                </div>
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
                <button type="button" class="edit-config-btn" id="server-edit-btn" onclick="toggleEditMode('server')">✏️ Edit JSON</button>
                <button type="button" class="copy-config-btn" id="server-copy-btn" onclick="copyJsonToClipboard('server')">📋 Copy JSON</button>
                <button type="button" class="cancel-config-btn" id="server-cancel-btn" style="display:none;" onclick="cancelEdit('server')">Cancel</button>
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
        <!-- ADD SEARCH INPUT -->
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
        <!-- ADD SEARCH INPUT -->
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
        <!-- ADD SEARCH INPUT -->
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
        <!-- ADD SEARCH INPUT -->
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
        <!-- ADD SEARCH INPUT -->
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
        <!-- ADD SEARCH INPUT -->
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
        <!-- ADD SEARCH INPUT -->
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
        <!-- ADD SEARCH INPUT -->
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
            <h3>Users List</h3>
            <!-- ADD SEARCH INPUT -->
            <div style="padding: 8px 12px; border-bottom: 1px solid var(--border-color);">
                <input type="text" id="execution-user-search" class="search-input" placeholder="🔍 Search by name, email or ID..." style="width: 100%; padding: 8px; font-size: 12px;">
            </div>
            <div id="execution-user-list" class="user-items" style="max-height: 450px; overflow-y: auto;">
                <div style="text-align: center; padding: 20px;">Loading users...</div>
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
            <h3>Users List</h3>
            <div style="padding: 8px 12px; border-bottom: 1px solid var(--border-color);">
                <input type="text" id="autotrading-user-search" class="search-input" placeholder="🔍 Search by name, email or ID..." style="width: 100%; padding: 8px; font-size: 12px;">
            </div>
            <div id="autotrading-user-list" class="user-items" style="max-height: 450px; overflow-y: auto;">
                <div style="text-align: center; padding: 20px;">Loading users...</div>
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

<script>
    // Account Management Functions
    let currentEditingData = null;
    let currentUserId = null;
    let currentSourceTable = null;
    let currentTargetType = null;
    let isEditMode = false;
    let originalDataBackup = null;
    let autoRefreshInterval = null;
    let isAutoRefreshEnabled = true;
    let pendingSaveData = null;
    let currentTab = 'server';
    let allUsersCache = [];
    let currentFilteredUsers = [];

    // Autotrading Settings Variables
    let currentAutoTradingUserId = null;
    let currentAutoTradingSourceTable = null;
    let currentAutoTradingData = {
        enable_autotrading: 1,
        bypass_restriction: 0
    };

    // Global Search Function
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
            
            // Apply search based on current tab
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
        }
        else if (currentTab === 'bypassed') {
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
        
        // Update count display if exists
        const countSpan = document.getElementById(`${containerId}-count`);
        if (countSpan) {
            countSpan.textContent = visibleCount;
        }
        
        // Show/hide no results message
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
        
        // Update count display
        const countSpan = document.getElementById('justjoinedvalid-users-count');
        if (countSpan) {
            countSpan.textContent = visibleCount;
        }
        
        // Show/hide no results message
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
    }

    // Copy JSON to clipboard function
    function copyJsonToClipboard(type) {
        let dataToCopy = null;
        
        if (type === 'server') {
            dataToCopy = currentEditingData;
        } else if (type === 'user') {
            if (!currentUserId || !currentSourceTable) {
                showMessage('Please select a user first', 'error');
                return;
            }
            dataToCopy = currentEditingData;
        }
        
        if (!dataToCopy) {
            showMessage('No data to copy', 'error');
            return;
        }
        
        if (typeof dataToCopy === 'object' && Object.keys(dataToCopy).length === 0) {
            showMessage('No configuration data available to copy', 'error');
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
                showMessage('JSON copied to clipboard!', 'success');
            } else {
                showMessage('Failed to copy JSON', 'error');
            }
        } catch (err) {
            console.error('Fallback copy error:', err);
            showMessage('Failed to copy JSON', 'error');
        }
        
        document.body.removeChild(textArea);
    }


    function loadUserAccountManagement(userId, sourceTable) {
        if (!userId || !sourceTable) {
            showMessage('Invalid user selection', 'error');
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
        
        const copyBtn = document.getElementById('user-copy-btn');
        if (copyBtn) {
            copyBtn.style.display = 'none';
        }
        
        const userJsonViewer = document.querySelector('#user-json-viewer');
        if (userJsonViewer) {
            userJsonViewer.innerHTML = '<div style="text-align: center; padding: 40px;">Loading user configuration...</div>';
        }
        
        if (isEditMode) {
            exitEditMode('user');
        }
        
        // Get user info for title extraction
        const selectedUserItem = document.querySelector(`.user-item[data-user-id="${userId}"]`);
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
                    
                    // Reset title to default
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
                    // Pass user info to displayJsonViewer for title extraction
                    // Also check if the data has a single key that contains the config
                    let configKeyName = null;
                    if (data.data && typeof data.data === 'object' && Object.keys(data.data).length === 1) {
                        // If there's only one key in the data, that key might be the config name
                        const singleKey = Object.keys(data.data)[0];
                        // Check if the value is an object (nested config)
                        if (data.data[singleKey] && typeof data.data[singleKey] === 'object') {
                            configKeyName = singleKey;
                            // Pass the nested data instead? No, keep as is for display
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
                showMessage(data.error || 'Error loading account management', 'error');
                currentEditingData = {};
                
                // Reset title to default
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
            showMessage('Error loading account management', 'error');
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
        
        // If this is user configuration, extract and display the config name
        if (isUserConfig && userInfo) {
            const configTitle = extractConfigTitle(data, userInfo, configKeyName);
            const titleSpan = document.getElementById('user-config-title');
            if (titleSpan) {
                titleSpan.innerHTML = `📁 ${escapeHtml(configTitle)}`;
            }
            
            // Also show the copy button if there's data
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

    // New function to extract configuration title based on priority rules
    function extractConfigTitle(data, userInfo, configKeyName = null) {
        // Priority 1: Check if data has a direct string field "configuration_title"
        if (data.configuration_title && typeof data.configuration_title === 'string' && data.configuration_title.trim() !== '') {
            return data.configuration_title;
        }
        
        // Priority 2: Check if data has a direct string field "configuration_title"
        if (data.configuration_title && typeof data.configuration_title === 'string' && data.configuration_title.trim() !== '') {
            return data.configuration_title;
        }
        
        // Priority 3: Check if data has a direct string field "configuration_title"
        if (data.configuration_title && typeof data.configuration_title === 'string' && data.configuration_title.trim() !== '') {
            return data.configuration_title;
        }
        
        // Priority 4: Check if the data itself is stored with a key name (passed from displayJsonViewer)
        // This handles the case where the entire config is stored as { "key_name": { ... } }
        if (configKeyName && typeof configKeyName === 'string' && configKeyName.trim() !== '') {
            // Check if the key name is not a generic/default name
            if (!configKeyName.match(/^configuration_\d+$/) && configKeyName !== 'user_config') {
                return configKeyName;
            }
        }
        
        // Priority 5: Check if data has a nested object with a name field
        // Look for the first top-level key that contains name-related fields
        for (const [key, value] of Object.entries(data)) {
            if (value && typeof value === 'object') {
                if (value.configuration_title && typeof value.configuration_title === 'string' && value.configuration_title.trim() !== '') {
                    return value.configuration_title;
                }
                if (value.configuration_title && typeof value.configuration_title === 'string' && value.configuration_title.trim() !== '') {
                    return value.configuration_title;
                }
                if (value.configuration_title && typeof value.configuration_title === 'string' && value.configuration_title.trim() !== '') {
                    return value.configuration_title;
                }
                // If the key itself looks like a descriptive name (not a generic one)
                if (!key.match(/^config_?\d*$|^settings$|^data$/) && key !== 'user_config' && !key.startsWith('_')) {
                    return key;
                }
            }
        }
        
        // Priority 6: Fallback to fullname + id
        if (userInfo.fullname && userInfo.id) {
            return `${userInfo.fullname} (ID: ${userInfo.id})`;
        } else if (userInfo.fullname) {
            return userInfo.fullname;
        } else if (userInfo.id) {
            return `User ID: ${userInfo.id}`;
        }
        
        // Ultimate fallback
        return 'User Configuration';
    }

    // New function to copy user config to clipboard
    function copyUserConfigToClipboard() {
        if (!currentEditingData) {
            showMessage('No configuration data to copy', 'error');
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
                showMessage('User configuration copied to clipboard!', 'success');
            }).catch(err => {
                console.error('Failed to copy: ', err);
                fallbackCopyToClipboard(jsonString);
            });
        } else {
            fallbackCopyToClipboard(jsonString);
        }
    }

    // Toggle expand/collapse for User Configuration
    let isUserConfigExpanded = false;

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

    function toggleEditMode(type) {
        if (type === 'user') {
            if (!currentUserId || !currentSourceTable) {
                showMessage('Please select a user first', 'error');
                return;
            }
        }
        
        if (isEditMode) {
            showPasswordModalForSave(type);
        } else {
            enterEditMode(type);
        }
    }

    function showPasswordModalForSave(type) {
        let modal = document.getElementById('json-save-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'json-save-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3 id="json-modal-title">🔐 Security Verification</h3>
                    <p id="json-modal-message">Please enter your admin password to save changes.</p>
                    <input type="password" id="json-save-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="json-modal-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="json-modal-confirm" class="modal-confirm-btn">Confirm Save</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            if (!document.querySelector('#json-modal-styles')) {
                const style = document.createElement('style');
                style.id = 'json-modal-styles';
                style.textContent = `
                    #json-save-password-modal {
                        display: none;
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0,0,0,0.7);
                        z-index: 10000;
                        justify-content: center;
                        align-items: center;
                    }
                    #json-save-password-modal.show {
                        display: flex;
                    }
                    #json-save-password-modal .modal-content {
                        background: var(--bg-secondary, #2d2d3a);
                        border-radius: 12px;
                        padding: 25px;
                        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                        min-width: 350px;
                    }
                    #json-save-password-modal .json-password-input {
                        width: 100%;
                        padding: 10px;
                        margin: 15px 0;
                        border: 1px solid var(--border-color, #3a3a4a);
                        background: var(--bg-primary, #1e1e2a);
                        color: var(--text-primary, #e4e4e7);
                        border-radius: 6px;
                        font-size: 14px;
                    }
                    #json-save-password-modal .modal-confirm-btn {
                        background: #27ae60;
                        color: white;
                        border: none;
                        padding: 8px 16px;
                        border-radius: 6px;
                        cursor: pointer;
                    }
                    #json-save-password-modal .modal-cancel-btn {
                        background: #e74c3c;
                        color: white;
                        border: none;
                        padding: 8px 16px;
                        border-radius: 6px;
                        cursor: pointer;
                    }
                    #json-save-password-modal .modal-confirm-btn:hover {
                        background: #2ecc71;
                    }
                    #json-save-password-modal .modal-cancel-btn:hover {
                        background: #c0392b;
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        const typeText = type === 'server' ? 'Server Configuration' : `User Configuration for ${document.getElementById('selected-user-name')?.textContent || 'User'}`;
        const messageElem = document.getElementById('json-modal-message');
        if (messageElem) {
            messageElem.innerHTML = `Please enter your admin password to save changes to:<br><strong style="color: #3498db;">${typeText}</strong>`;
        }
        
        modal.classList.add('show');
        const passwordInput = document.getElementById('json-save-password');
        if (passwordInput) {
            passwordInput.value = '';
            passwordInput.focus();
        }
        
        pendingSaveData = { type };
        
        const confirmBtn = document.getElementById('json-modal-confirm');
        const cancelBtn = document.getElementById('json-modal-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                showMessage('Password is required', 'error');
                passwordInput.focus();
                return;
            }
            modal.classList.remove('show');
            executeSaveWithPassword(password, type);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
            pendingSaveData = null;
            showMessage('Save cancelled', 'success');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
                pendingSaveData = null;
            }
        };
    }
    
    function executeSaveWithPassword(password, type) {
        const containerId = type === 'server' ? '#server-json-viewer' : '#user-json-viewer';
        const container = document.querySelector(containerId);
        const textarea = container.querySelector('textarea');
        
        if (!textarea) {
            showMessage('No changes to save', 'error');
            return;
        }
        
        let newData;
        try {
            newData = JSON.parse(textarea.value);
        } catch (e) {
            showMessage('Invalid JSON: ' + e.message, 'error');
            return;
        }
        
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
        
        const editBtn = document.getElementById(type + '-edit-btn');
        if (editBtn) {
            editBtn.disabled = true;
            editBtn.textContent = '💾 Saving...';
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
                displayJsonViewer(newData, containerId);
                exitEditMode(type);
                showMessage('JSON configuration saved successfully!', 'success');
                
                isAutoRefreshEnabled = true;
                
                if (type === 'user') {
                    const copyBtn = document.getElementById('user-copy-btn');
                    if (copyBtn && newData && Object.keys(newData).length > 0) {
                        copyBtn.style.display = 'inline-block';
                    }
                }
            } else {
                showMessage(data.error || 'Error saving configuration', 'error');
                if (data.error === 'Invalid password') {
                    showMessage('Password verification failed. Please try again.', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error saving configuration', 'error');
        })
        .finally(() => {
            if (editBtn && !isEditMode) {
                editBtn.disabled = false;
                editBtn.textContent = '✏️ Edit JSON';
                editBtn.style.background = '#27ae60';
            }
            pendingSaveData = null;
        });
    }

    function enterEditMode(type) {
        if (type === 'user') {
            if (!currentUserId || !currentSourceTable) {
                showMessage('Please select a user first', 'error');
                return;
            }
            
            if (!currentEditingData) {
                currentEditingData = {};
            }
        }
        
        if (type === 'server') {
            if (!currentEditingData) {
                showMessage('No server data to edit', 'error');
                return;
            }
        }
        
        isAutoRefreshEnabled = false;
        isEditMode = true;
        originalDataBackup = JSON.parse(JSON.stringify(currentEditingData || {}));
        
        const containerId = type === 'server' ? '#server-json-viewer' : '#user-json-viewer';
        const container = document.querySelector(containerId);
        const editBtn = document.getElementById(type + '-edit-btn');
        const cancelBtn = document.getElementById(type + '-cancel-btn');
        const copyBtn = document.getElementById(type + '-copy-btn');
        
        if (!container) return;
        
        if (copyBtn) {
            copyBtn.style.display = 'none';
        }
        
        const wrapper = document.createElement('div');
        wrapper.className = 'editor-full-wrapper';
        
        const textarea = document.createElement('textarea');
        textarea.className = 'json-editor-fullwidth';
        
        let dataToEdit = currentEditingData;
        if (!dataToEdit || (typeof dataToEdit === 'object' && Object.keys(dataToEdit).length === 0)) {
            dataToEdit = {
                "example_setting": "value",
                "description": "Edit this JSON as needed"
            };
        }
        
        textarea.value = JSON.stringify(dataToEdit, null, 2);
        textarea.spellcheck = false;
        
        wrapper.appendChild(textarea);
        
        container.innerHTML = '';
        container.appendChild(wrapper);
        
        textarea.focus();
        
        if (editBtn) {
            editBtn.textContent = '💾 Save Changes';
            editBtn.style.background = '#27ae60';
            editBtn.disabled = false;
        }
        if (cancelBtn) {
            cancelBtn.style.display = 'inline-block';
        }
        
        textarea.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                showPasswordModalForSave(type);
            }
        });
    }

    // Account Management Configs Functions
    let currentEditingConfigEntry = null;
    let originalConfigEntryBackup = null;

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
                // Handle case where all_data might be nested differently
                displayConfigEntries(data.data);
            } else {
                container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No configurations found. Click "Add New Configuration" to create one.</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading configurations. Please refresh the page.</div>';
        });
    }

    function displayConfigEntries(configs) {
        const container = document.getElementById('config-entries-container');
        if (!container) return;
        
        // Handle different possible data structures
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
            // Cache the data
            setCachedConfigData(key, value);
            
            const card = document.createElement('div');
            card.className = 'config-entry-card';
            card.id = `config-card-${key.replace(/[^a-zA-Z0-9]/g, '_')}`;
            
            // Handle null or undefined values
            const displayValue = value || {};
            
            // Set ALL cards as folded by default
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
    // Toggle expand/collapse for config entries
    let currentlyExpandedConfig = null;

    function toggleConfigExpand(entryKey) {
        const safeKey = entryKey.replace(/[^a-zA-Z0-9]/g, '_');
        const contentDiv = document.getElementById(`content-${safeKey}`);
        const iconSpan = document.getElementById(`icon-${safeKey}`);
        
        if (!contentDiv || !iconSpan) return;
        
        // If there's already an expanded config and it's not this one, collapse it
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
        
        // Toggle the clicked config
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
            showMessage('Please save or cancel the current edit first', 'error');
            return;
        }
        
        currentEditingConfigEntry = entryKey;
        
        // Ensure the config is expanded when editing
        const safeKey = entryKey.replace(/[^a-zA-Z0-9]/g, '_');
        const contentDiv = document.getElementById(`content-${safeKey}`);
        const iconSpan = document.getElementById(`icon-${safeKey}`);
        
        if (contentDiv && contentDiv.style.display === 'none') {
            contentDiv.style.display = 'block';
            if (iconSpan) iconSpan.textContent = '▼';
            currentlyExpandedConfig = entryKey;
        }
        
        // Load the current data for this entry
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
            showMessage('Error loading entry data', 'error');
            currentEditingConfigEntry = null;
        });
    }

    function cancelConfigEntry(entryKey) {
        if (!currentEditingConfigEntry || currentEditingConfigEntry !== entryKey) {
            showMessage('No active edit for this entry', 'error');
            return;
        }
        
        const safeKey = entryKey.replace(/[^a-zA-Z0-9]/g, '_');
        const contentDiv = document.getElementById(`content-${safeKey}`);
        const buttonsDiv = document.getElementById(`buttons-${safeKey}`);
        
        if (contentDiv && originalConfigEntryBackup) {
            contentDiv.innerHTML = `
                <pre class="config-json-view">${escapeHtml(JSON.stringify(originalConfigEntryBackup.data, null, 2))}</pre>
            `;
            
            // Keep the content expanded if it was expanded before
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
        showMessage('Edit cancelled', 'success');
    }

    function saveConfigEntry(entryKey) {
        if (!currentEditingConfigEntry || currentEditingConfigEntry !== entryKey) {
            showMessage('No active edit for this entry', 'error');
            return;
        }
        
        const safeKey = entryKey.replace(/[^a-zA-Z0-9]/g, '_');
        const editor = document.getElementById(`editor-${safeKey}`);
        const keyEditor = document.getElementById(`key-editor-${safeKey}`);
        
        if (!editor) {
            showMessage('Editor not found', 'error');
            return;
        }
        
        let newValue;
        try {
            newValue = JSON.parse(editor.value);
        } catch (e) {
            showMessage('Invalid JSON: ' + e.message, 'error');
            return;
        }
        
        let newKey = entryKey;
        if (keyEditor) {
            newKey = keyEditor.value.trim();
            if (!newKey) {
                showMessage('Configuration key cannot be empty', 'error');
                return;
            }
            if (!/^[a-zA-Z0-9_\-]+$/.test(newKey)) {
                showMessage('Key can only contain letters, numbers, underscores, and hyphens', 'error');
                return;
            }
        }
        
        showPasswordModalForConfigSave(entryKey, newKey, newValue);
    }

    function showPasswordModalForConfigSave(oldKey, newKey, newValue) {
        let modal = document.getElementById('config-save-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'config-save-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>🔐 Security Verification</h3>
                    <p>Please enter your admin password to save this configuration.</p>
                    <input type="password" id="config-save-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="config-modal-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="config-modal-confirm" class="modal-confirm-btn">Confirm Save</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        window.pendingConfigSave = { oldKey, newKey, newValue };
        
        modal.classList.add('show');
        const passwordInput = document.getElementById('config-save-password');
        if (passwordInput) {
            passwordInput.value = '';
            passwordInput.focus();
        }
        
        const confirmBtn = document.getElementById('config-modal-confirm');
        const cancelBtn = document.getElementById('config-modal-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                showMessage('Password is required', 'error');
                passwordInput.focus();
                return;
            }
            modal.classList.remove('show');
            executeSaveConfigEntry(password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
            window.pendingConfigSave = null;
            showMessage('Save cancelled', 'success');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
                window.pendingConfigSave = null;
            }
        };
    }

    function executeSaveConfigEntry(password) {
        const saveData = window.pendingConfigSave;
        if (!saveData) {
            showMessage('No pending save', 'error');
            return;
        }
        
        const { oldKey, newKey, newValue } = saveData;
        
        // Function to handle the save operation
        function performSave(deleteOldKey, createNewKey, valueToSave) {
            let formData = new URLSearchParams();
            formData.append('action', 'update_config_entry');
            formData.append('target_type', 'server');
            
            if (deleteOldKey && createNewKey) {
                // We're renaming - need to handle specially
                // First delete the old entry
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
                        // Then create the new entry with the new key
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
                        showMessage(message, 'success');
                        // Clear the editing state without showing cancel message
                        clearConfigEntryEditState(deleteOldKey);
                        loadAccountManagementConfigs();
                    } else {
                        showMessage(createData.error || 'Error creating new entry', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Error saving configuration: ' + error.message, 'error');
                    if (error.message === 'Invalid password') {
                        showMessage('Password verification failed. Please try again.', 'error');
                    }
                })
                .finally(() => {
                    window.pendingConfigSave = null;
                });
                
                return; // Exit early as we're handling the async differently
            }
            
            // Same key, just update the data - this will sync to both columns
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
                    showMessage(message, 'success');
                    // Clear the editing state without showing cancel message
                    clearConfigEntryEditState(oldKey);
                    loadAccountManagementConfigs();
                } else {
                    showMessage(data.error || 'Error saving configuration', 'error');
                    if (data.error === 'Invalid password') {
                        showMessage('Password verification failed. Please try again.', 'error');
                    }
                    // Don't clear the edit state on error - let user try again
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Error saving configuration', 'error');
            })
            .finally(() => {
                window.pendingConfigSave = null;
            });
        }
        
        // Check if we're renaming
        if (oldKey !== newKey) {
            performSave(oldKey, newKey, newValue);
        } else {
            performSave(null, null, newValue);
        }
    }

    // New helper function to clear config entry edit state without showing cancel message
    function clearConfigEntryEditState(entryKey) {
        if (!currentEditingConfigEntry || currentEditingConfigEntry !== entryKey) {
            return;
        }
        
        const safeKey = entryKey.replace(/[^a-zA-Z0-9]/g, '_');
        const contentDiv = document.getElementById(`content-${safeKey}`);
        const buttonsDiv = document.getElementById(`buttons-${safeKey}`);
        
        // Restore the content to display mode
        if (contentDiv && originalConfigEntryBackup) {
            contentDiv.innerHTML = `
                <pre class="config-json-view">${escapeHtml(JSON.stringify(originalConfigEntryBackup.data, null, 2))}</pre>
            `;
            
            // Keep the content expanded if it was expanded before
            if (currentlyExpandedConfig === entryKey) {
                contentDiv.style.display = 'block';
                const iconSpan = document.getElementById(`icon-${safeKey}`);
                if (iconSpan) iconSpan.textContent = '▼';
            }
        }
        
        // Restore the buttons
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
    // Cache for configuration data to avoid repeated network requests
    let configDataCache = {};
    let configDataCacheTimestamp = {};

    function getCachedConfigData(entryKey, forceRefresh = false) {
        const now = Date.now();
        const cacheExpiry = 30000; // 30 seconds cache
        
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
        // Get the displayed JSON directly from the DOM - NO NETWORK REQUEST
        const safeKey = entryKey.replace(/[^a-zA-Z0-9]/g, '_');
        const contentDiv = document.getElementById(`content-${safeKey}`);
        
        if (!contentDiv) {
            showMessage('Configuration content not found', 'error');
            return;
        }
        
        // Try to get JSON from the pre element (display mode)
        let preElement = contentDiv.querySelector('.config-json-view');
        let dataValue = null;
        
        if (preElement) {
            // In display mode - get text from pre element
            dataValue = preElement.textContent;
        } else {
            // In edit mode - try to get from textarea
            let textarea = contentDiv.querySelector('.config-json-editor');
            if (textarea) {
                dataValue = textarea.value;
            }
        }
        
        if (!dataValue) {
            showMessage('No configuration data found to copy', 'error');
            return;
        }
        
        // Validate JSON before copying
        let parsedData;
        try {
            parsedData = JSON.parse(dataValue); // Parse and store the parsed data
        } catch (e) {
            showMessage('Invalid JSON format in configuration', 'error');
            return;
        }
        
        // Create the full JSON object with the key as the property name
        const fullJsonObject = {
            [entryKey]: parsedData
        };
        
        // Convert to formatted JSON string
        const jsonString = JSON.stringify(fullJsonObject, null, 2);
        
        // Copy immediately - NO NETWORK DELAY
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(jsonString).then(() => {
                showMessage(`Configuration "${entryKey}" (with key) copied to clipboard!`, 'success');
                // Visual feedback on the copy button
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
                showMessage(`Configuration "${entryKey}" (with key) copied to clipboard!`, 'success');
            });
        } else {
            fallbackCopyToClipboard(jsonString);
            showMessage(`Configuration "${entryKey}" (with key) copied to clipboard!`, 'success');
        }
    }

    function deleteConfigEntry(entryKey) {
        if (confirm(`Are you sure you want to delete the configuration "${entryKey}"? This action cannot be undone.`)) {
            showPasswordModalForConfigDelete(entryKey);
        }
    }

    function showPasswordModalForConfigDelete(entryKey) {
        let modal = document.getElementById('config-delete-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'config-delete-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>⚠️ Security Verification</h3>
                    <p>Please enter your admin password to delete this configuration.</p>
                    <input type="password" id="config-delete-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="config-delete-modal-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="config-delete-modal-confirm" class="modal-confirm-btn">Confirm Delete</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        window.pendingConfigDelete = entryKey;
        
        modal.classList.add('show');
        const passwordInput = document.getElementById('config-delete-password');
        if (passwordInput) {
            passwordInput.value = '';
            passwordInput.focus();
        }
        
        const confirmBtn = document.getElementById('config-delete-modal-confirm');
        const cancelBtn = document.getElementById('config-delete-modal-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                showMessage('Password is required', 'error');
                passwordInput.focus();
                return;
            }
            modal.classList.remove('show');
            executeDeleteConfigEntry(password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
            window.pendingConfigDelete = null;
            showMessage('Delete cancelled', 'success');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
                window.pendingConfigDelete = null;
            }
        };
    }

    function executeDeleteConfigEntry(password) {
        const entryKey = window.pendingConfigDelete;
        if (!entryKey) {
            showMessage('No pending delete', 'error');
            return;
        }
        
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
                showMessage(`Configuration "${entryKey}" deleted successfully!`, 'success');
                loadAccountManagementConfigs();
            } else {
                showMessage(data.error || 'Error deleting configuration', 'error');
                if (data.error === 'Invalid password') {
                    showMessage('Password verification failed. Please try again.', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error deleting configuration', 'error');
        })
        .finally(() => {
            window.pendingConfigDelete = null;
        });
    }

    function showAddConfigEntryModal() {
        let modal = document.getElementById('add-config-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'add-config-modal';
            modal.className = 'add-config-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <h3>➕ Add New Configuration</h3>
                    <p>Enter a unique key name for this configuration:</p>
                    <input type="text" id="new-config-key" placeholder="e.g., configuration_3, my_custom_settings, etc.">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="add-config-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="add-config-confirm" class="modal-confirm-btn">Create</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        const input = document.getElementById('new-config-key');
        if (input) input.value = '';
        
        modal.classList.add('show');
        
        const confirmBtn = document.getElementById('add-config-confirm');
        const cancelBtn = document.getElementById('add-config-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const key = document.getElementById('new-config-key')?.value.trim();
            if (!key) {
                showMessage('Please enter a configuration key', 'error');
                return;
            }
            
            // Allow any key name - removed validation
            // Keys can now contain any characters
            
            modal.classList.remove('show');
            createNewConfigEntry(key);
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
        
        showPasswordModalForNewConfig(entryKey, defaultTemplate);
    }

    function showPasswordModalForNewConfig(entryKey, template) {
        let modal = document.getElementById('config-new-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'config-new-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>🔐 Security Verification</h3>
                    <p>Please enter your admin password to create the new configuration.</p>
                    <input type="password" id="config-new-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="config-new-modal-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="config-new-modal-confirm" class="modal-confirm-btn">Confirm Create</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        window.pendingNewConfig = { entryKey, template };
        
        modal.classList.add('show');
        const passwordInput = document.getElementById('config-new-password');
        if (passwordInput) {
            passwordInput.value = '';
            passwordInput.focus();
        }
        
        const confirmBtn = document.getElementById('config-new-modal-confirm');
        const cancelBtn = document.getElementById('config-new-modal-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                showMessage('Password is required', 'error');
                passwordInput.focus();
                return;
            }
            modal.classList.remove('show');
            executeCreateNewConfigEntry(password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
            window.pendingNewConfig = null;
            showMessage('Creation cancelled', 'success');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
                window.pendingNewConfig = null;
            }
        };
    }

    function executeCreateNewConfigEntry(password) {
        const configData = window.pendingNewConfig;
        if (!configData) {
            showMessage('No pending creation', 'error');
            return;
        }
        
        const { entryKey, template } = configData;
        
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
                showMessage(`Configuration "${entryKey}" created successfully!`, 'success');
                loadAccountManagementConfigs();
            } else {
                showMessage(data.error || 'Error creating configuration', 'error');
                if (data.error === 'Invalid password') {
                    showMessage('Password verification failed. Please try again.', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error creating configuration', 'error');
        })
        .finally(() => {
            window.pendingNewConfig = null;
        });
    }

    function cancelEdit(type) {
        if (!originalDataBackup) return;
        
        currentEditingData = JSON.parse(JSON.stringify(originalDataBackup));
        
        const containerId = type === 'server' ? '#server-json-viewer' : '#user-json-viewer';
        displayJsonViewer(currentEditingData, containerId);
        
        exitEditMode(type);
        showMessage('Edit cancelled, original data restored', 'success');
        
        isAutoRefreshEnabled = true;
        
        if (type === 'user') {
            const copyBtn = document.getElementById('user-copy-btn');
            if (copyBtn && currentEditingData && Object.keys(currentEditingData).length > 0) {
                copyBtn.style.display = 'inline-block';
            }
        } else if (type === 'server') {
            const copyBtn = document.getElementById('server-copy-btn');
            if (copyBtn && currentEditingData && Object.keys(currentEditingData).length > 0) {
                copyBtn.style.display = 'inline-block';
            }
        }
    }

    function exitEditMode(type) {
        isEditMode = false;
        originalDataBackup = null;
        
        const editBtn = document.getElementById(type + '-edit-btn');
        const cancelBtn = document.getElementById(type + '-cancel-btn');
        
        if (editBtn) {
            editBtn.textContent = '✏️ Edit JSON';
            editBtn.style.background = '#27ae60';
        }
        if (cancelBtn) {
            cancelBtn.style.display = 'none';
        }
    }

    function loadServerAccountManagement() {
        currentTargetType = 'server';
        currentUserId = null;
        currentSourceTable = null;
        
        const copyBtn = document.getElementById('server-copy-btn');
        if (copyBtn) {
            copyBtn.style.display = 'inline-block';
        }
        
        if (isEditMode) {
            exitEditMode('server');
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
                showMessage(data.error || 'Error loading server account management', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error loading server account management', 'error');
        });
    }
    // Fallback to ensure configs load if there's any timing issue
    setTimeout(function() {
        if (document.getElementById('config-entries-container') && 
            document.getElementById('config-entries-container').innerHTML.includes('Loading configurations')) {
            loadAccountManagementConfigs();
        }
    }, 1000);

    function startAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
        
        autoRefreshInterval = setInterval(function() {
            if (!isEditMode && isAutoRefreshEnabled) {
                if (currentTargetType === 'server') {
                    loadServerAccountManagement();
                } else if (currentTargetType === 'user' && currentUserId && currentSourceTable) {
                    loadUserAccountManagement(currentUserId, currentSourceTable);
                }
            }
        }, 5000);
    }

    // Updated Tab switching functionality with search setup
    document.addEventListener('DOMContentLoaded', function() {
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabs = document.querySelectorAll('.management-tab');
        
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                currentTab = tabId;
                
                if (isEditMode) {
                    if (currentTargetType === 'server') {
                        cancelEdit('server');
                    } else {
                        cancelEdit('user');
                    }
                }
                
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
                    setupUserListSearch(); // ADDED: Search for user list
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
                    setupExecutionUserSearch(); // ADDED: Search for execution user list
                } else if (tabId === 'autotrading') {
                    document.getElementById('autotrading-tab').style.display = 'block';
                    loadAllUsersForAutoTrading();
                    setupAutoTradingUserSearch(); // ADDED: Search for auto trading user list
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
        startAutoRefresh();
        setupGlobalSearch();
    });

    function loadAllUsersForManagement() {
        const userListDiv = document.getElementById('user-items-list');
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
        
        userListDiv.innerHTML = '<div style="text-align: center; padding: 20px;">Loading users...</div>';
        
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
                    userListDiv.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No users found in the system</div>';
                    const userJsonViewer = document.querySelector('#user-json-viewer');
                    if (userJsonViewer) {
                        userJsonViewer.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No users available. Add users to the system first.</div>';
                    }
                    return;
                }
                
                data.users.forEach(user => {
                    const userDiv = document.createElement('div');
                    userDiv.className = 'user-item';
                    userDiv.setAttribute('data-user-id', user.id);
                    userDiv.setAttribute('data-source', user.source);
                    userDiv.setAttribute('data-fullname', user.fullname || '');
                    userDiv.setAttribute('data-email', user.email || '');
                    userDiv.setAttribute('data-application-status', user.application_status || '');
                    userDiv.onclick = () => {
                        selectUser(user.id, user.source, user.fullname || 'N/A', user.email || 'N/A');
                    };
                    
                    // Get status badge class
                    let statusClass = 'status-badge-default';
                    let statusText = user.application_status || 'Not Set';
                    if (user.application_status === 'approved') statusClass = 'status-badge-approved';
                    else if (user.application_status === 'declined') statusClass = 'status-badge-declined';
                    else if (user.application_status === 'pending') statusClass = 'status-badge-pending';
                    else if (user.application_status === 'suspended') statusClass = 'status-badge-suspended';
                    else if (user.application_status === 'blacklisted') statusClass = 'status-badge-blacklisted';
                    
                    userDiv.innerHTML = `
                        <div class="user-item-name">${escapeHtml(user.fullname || 'N/A')}</div>
                        <div class="user-item-email">${escapeHtml(user.email || 'N/A')}</div>
                        <div style="font-size: 10px; opacity: 0.7; margin-top: 5px;">
                            <span>ID: ${user.id} | ${user.source}</span>
                            <span class="${statusClass}" style="margin-left: 8px;">${escapeHtml(statusText)}</span>
                        </div>
                    `;
                    userListDiv.appendChild(userDiv);
                });
            } else {
                userListDiv.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading users</div>';
                showMessage(data.error || 'Error loading users', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            userListDiv.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading users</div>';
            showMessage('Error loading users', 'error');
        });
    }

    function selectUser(userId, sourceTable, fullname, email) {
        if (!userId || !sourceTable) {
            showMessage('Invalid user selection', 'error');
            return;
        }
        
        currentUserId = userId;
        currentSourceTable = sourceTable;
        currentTargetType = 'user';
        
        if (isEditMode) {
            cancelEdit('user');
        }
        
        // Reset the fold state when selecting a new user (keep it folded initially)
        if (isUserConfigExpanded) {
            toggleUserConfigExpand();
        }
        
        document.querySelectorAll('.user-item').forEach(item => {
            item.classList.remove('active');
        });
        const selectedItem = document.querySelector(`.user-item[data-user-id="${userId}"]`);
        if (selectedItem) selectedItem.classList.add('active');
        
        const nameSpan = document.getElementById('selected-user-name');
        const emailSpan = document.getElementById('selected-user-email');
        const sourceSpan = document.getElementById('selected-user-source');
        if (nameSpan) nameSpan.textContent = fullname;
        if (emailSpan) emailSpan.textContent = email;
        if (sourceSpan) sourceSpan.textContent = sourceTable;
        
        // Get current application status
        const currentStatus = selectedItem ? selectedItem.getAttribute('data-application-status') : '';
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

    // ========== NEW FUNCTIONS FOR ADDED TABS ==========
    
    // Load Active Investors (invested_with NOT empty, execution_start_date NOT empty, enable_autotrading = 1, broker_balance >= min_broker_balance)
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
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading Active Investors</div>';
        });
    }
    
    // Load Pending Users (application_status = 'pending')
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
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading pending users</div>';
        });
    }
    
    // Load Suspended Users (application_status = 'suspended' OR 'blacklisted')
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
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading suspended users</div>';
        });
    }
    
    // Load Just Joined Users (application_status = 'just-joined')
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
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading just joined users</div>';
        });
    }
    
    // Load Just Joined & Valid Credentials Users with status update capability
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
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading users</div>';
        });
    }

    // Special display function for Just Joined & Valid users with status update dropdown
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
            // FIX: Check for both terminal_path (lowercase) and Terminal_path (uppercase) - case insensitive
            const terminalPath = user.terminal_path || user.Terminal_path || '-';
            
            // Get status badge class for current status
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
        
        // Attach event listeners to all update buttons in this table
        document.querySelectorAll('#justjoinedvalid-users-list .update-status-from-table-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const sourceTable = this.getAttribute('data-source');
                const row = this.closest('tr');
                const select = row.querySelector('.status-update-select');
                const newStatus = select.value;
                
                if (!newStatus) {
                    showMessage('Please select a status', 'error');
                    return;
                }
                
                showPasswordModalForJustJoinedStatusUpdate(userId, sourceTable, newStatus, row);
            });
        });
    }

    function showPasswordModalForJustJoinedStatusUpdate(userId, sourceTable, newStatus, row) {
        let modal = document.getElementById('justjoined-status-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'justjoined-status-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>Security Verification</h3>
                    <p>Please enter your admin password to update application status.</p>
                    <input type="password" id="justjoined-status-password-input" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="justjoined-status-modal-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="justjoined-status-modal-confirm" class="modal-confirm-btn">Confirm Update</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        window.pendingJustJoinedStatusUpdate = { userId, sourceTable, newStatus, row };
        
        modal.classList.add('show');
        const passwordInput = document.getElementById('justjoined-status-password-input');
        if (passwordInput) {
            passwordInput.value = '';
            passwordInput.focus();
        }
        
        const confirmBtn = document.getElementById('justjoined-status-modal-confirm');
        const cancelBtn = document.getElementById('justjoined-status-modal-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                showMessage('Password is required', 'error');
                passwordInput.focus();
                return;
            }
            modal.classList.remove('show');
            executeJustJoinedStatusUpdate(password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
            window.pendingJustJoinedStatusUpdate = null;
            showMessage('Update cancelled', 'success');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
                window.pendingJustJoinedStatusUpdate = null;
            }
        };
    }

    function executeJustJoinedStatusUpdate(password) {
        const updateData = window.pendingJustJoinedStatusUpdate;
        if (!updateData) {
            showMessage('No pending update', 'error');
            return;
        }
        
        const { userId, sourceTable, newStatus, row } = updateData;
        
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
                showMessage(`Application status updated to ${newStatus} for User ID ${userId}`, 'success');
                
                // Update the status badge in the row
                const statusCell = row.querySelector('td:nth-child(12)');
                let statusClass = 'status-badge-pending';
                if (newStatus === 'approved') statusClass = 'status-badge-approved';
                else if (newStatus === 'declined') statusClass = 'status-badge-declined';
                else if (newStatus === 'suspended') statusClass = 'status-badge-suspended';
                
                if (statusCell) {
                    statusCell.innerHTML = `<span class="${statusClass}">${escapeHtml(newStatus)}</span>`;
                }
                
                // Disable the select and button or remove the row
                const select = row.querySelector('.status-update-select');
                const button = row.querySelector('.update-status-from-table-btn');
                if (select) select.disabled = true;
                if (button) button.disabled = true;
                
                // Also update the user list in User Configuration tab if it exists
                const userItem = document.querySelector(`.user-item[data-user-id="${userId}"]`);
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
                showMessage(data.error || 'Error updating status', 'error');
                if (data.error === 'Invalid password') {
                    showMessage('Password verification failed. Please try again.', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error updating status', 'error');
        })
        .finally(() => {
            window.pendingJustJoinedStatusUpdate = null;
        });
    }
    
    // Load Approved Users (application_status = 'approved')
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
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading approved users</div>';
        });
    }
    // Load Bypassed Users (bypass_restriction = 1)
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
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading bypassed users</div>';
        });
    }

    // Generic function to display bypassed user table with unauthorized actions column
    // Generic function to display bypassed user table with unauthorized actions column
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
            
            // Check unauthorized_actions value - 1 means present, 0 means none
            let unauthorizedStatus = '';
            let unauthorizedClass = '';
            
            if (user.unauthorized_actions == 1) {
                unauthorizedStatus = '1';
                unauthorizedClass = 'unauthorized-present';
            } else if (user.unauthorized_actions == 0) {
                unauthorizedStatus = '0';
                unauthorizedClass = 'unauthorized-none';
            } else {
                // Handle null, empty, or other values as 0 (none)
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
    
    // Generic function to display user table with all columns including terminal_path
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
                            <th>Execution Start Date</th>
                            <th>Contract Days Left</th>
                            <th>Autotrading</th>
                            <th>Demo Account</th>
                            <th>Account Mode</th>
                            <th>Terminal Path</th>
                            <th>Broker Balance</th>
                            <th>Action</th>
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
                    <td class="execution-date-cell" data-original-date="${escapeHtml(user.execution_start_date || '')}">${escapeHtml(user.execution_start_date || '-')}</td>
                    <td class="contract-days-cell">${contractDaysLeft}</td>
                    <td><span class="${autoTradingClass}">${autoTradingStatus}</span></td>
                    <td><span class="${demoAccountClass}">${demoAccountStatus}</span></td>
                    <td><span class="mode-badge ${(user.account_mode || '').toLowerCase() === 'demo' ? 'mode-demo' : 'mode-real'}">${escapeHtml(user.account_mode || 'N/A')}</span></td>
                    <td><code class="terminal-path-value" title="${escapeHtml(terminalPath)}">${escapeHtml(terminalPath)}</code></td>
                    <td class="balance-cell">${user.broker_balance ? '$' + parseFloat(user.broker_balance).toFixed(2) : '-'}</td>
                    <td class="action-cell">
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
        
        // Add event listeners for the action buttons (only for Active Investors tab)
        if (type === 'verified') {
            document.querySelectorAll('#verified-users-list .apply-action-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const sourceTable = this.getAttribute('data-source');
                    const row = this.closest('tr');
                    const select = row.querySelector('.contract-action-select');
                    const action = select.value;
                    
                    if (action === 'cancel_contract') {
                        showPasswordModalForContractCancellation(userId, sourceTable, row);
                    } else {
                        showMessage('No action taken - user remains active', 'success');
                    }
                });
            });
        }
    }

    // Function to show password modal for contract cancellation
    function showPasswordModalForContractCancellation(userId, sourceTable, row) {
        let modal = document.getElementById('contract-cancel-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'contract-cancel-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>⚠️ Cancel Contract</h3>
                    <p>Are you sure you want to cancel this user's contract?</p>
                    <p style="font-size: 12px; color: #e74c3c;">This will change the execution start date to make the contract expired.</p>
                    <p style="font-size: 12px; margin-top: 10px;">Please enter your admin password to confirm.</p>
                    <input type="password" id="contract-cancel-password-input" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="contract-cancel-modal-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="contract-cancel-modal-confirm" class="modal-confirm-btn">Confirm Cancellation</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        window.pendingContractCancellation = { userId, sourceTable, row };
        
        modal.classList.add('show');
        const passwordInput = document.getElementById('contract-cancel-password-input');
        if (passwordInput) {
            passwordInput.value = '';
            passwordInput.focus();
        }
        
        const confirmBtn = document.getElementById('contract-cancel-modal-confirm');
        const cancelBtn = document.getElementById('contract-cancel-modal-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                showMessage('Password is required', 'error');
                passwordInput.focus();
                return;
            }
            modal.classList.remove('show');
            executeContractCancellation(password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
            window.pendingContractCancellation = null;
            showMessage('Cancellation aborted', 'success');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
                window.pendingContractCancellation = null;
            }
        };
    }

    // Function to execute contract cancellation
    function executeContractCancellation(password) {
        const cancelData = window.pendingContractCancellation;
        if (!cancelData) {
            showMessage('No pending cancellation', 'error');
            return;
        }
        
        const { userId, sourceTable, row } = cancelData;
        
        let formData = new URLSearchParams();
        formData.append('action', 'cancel_contract');
        formData.append('user_id', userId);
        formData.append('source_table', sourceTable);
        formData.append('admin_password', password);
        formData.append('login_id', '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>');
        
        // Disable the button while processing
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
                showMessage(data.message || 'Contract cancelled successfully!', 'success');
                
                // Update the execution start date in the row
                const executionDateCell = row.querySelector('.execution-date-cell');
                if (executionDateCell && data.new_execution_date) {
                    executionDateCell.textContent = data.new_execution_date;
                    executionDateCell.setAttribute('data-original-date', data.new_execution_date);
                }
                
                // Update contract days left
                const contractDaysCell = row.querySelector('.contract-days-cell');
                if (contractDaysCell && data.contract_days_left !== undefined) {
                    contractDaysCell.textContent = data.contract_days_left;
                }
                
                // Disable the select and button after successful cancellation
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
                showMessage(data.error || 'Error cancelling contract', 'error');
                if (data.error === 'Invalid password') {
                    showMessage('Password verification failed. Please try again.', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error cancelling contract', 'error');
        })
        .finally(() => {
            if (actionBtn && actionBtn.disabled !== true) {
                actionBtn.innerHTML = originalBtnText || 'Apply';
                actionBtn.disabled = false;
            }
            window.pendingContractCancellation = null;
        });
    }
    
    // Invested With Management Functions (keep existing)
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
                showMessage(data.error || 'Error loading users', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">❌ Error loading users</div>';
            showMessage('Error loading users', 'error');
        });
    }
    
    function saveInvestedWith(userId, sourceTable, rowId) {
        const inputElement = document.getElementById(`input-${rowId}`);
        if (!inputElement) {
            showMessage('Input field not found', 'error');
            return;
        }
        
        const newValue = inputElement.value.trim();
        
        showPasswordModalForInvestedWith(userId, sourceTable, newValue, rowId);
    }
    
    function showPasswordModalForInvestedWith(userId, sourceTable, newValue, rowId) {
        let modal = document.getElementById('invested-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'invested-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>🔐 Security Verification</h3>
                    <p>Please enter your admin password to save INVESTED_WITH changes.</p>
                    <input type="password" id="invested-password-input" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="invested-modal-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="invested-modal-confirm" class="modal-confirm-btn">Confirm Save</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        window.pendingInvestedSave = { userId, sourceTable, newValue, rowId };
        
        modal.classList.add('show');
        const passwordInput = document.getElementById('invested-password-input');
        if (passwordInput) {
            passwordInput.value = '';
            passwordInput.focus();
        }
        
        const confirmBtn = document.getElementById('invested-modal-confirm');
        const cancelBtn = document.getElementById('invested-modal-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                showMessage('Password is required', 'error');
                passwordInput.focus();
                return;
            }
            modal.classList.remove('show');
            executeSaveInvestedWith(password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
            window.pendingInvestedSave = null;
            showMessage('Save cancelled', 'success');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
                window.pendingInvestedSave = null;
            }
        };
    }
    
    function executeSaveInvestedWith(password) {
        const saveData = window.pendingInvestedSave;
        if (!saveData) {
            showMessage('No pending save operation', 'error');
            return;
        }
        
        const { userId, sourceTable, newValue, rowId } = saveData;
        
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
                showMessage(`✅ INVESTED_WITH updated for User ID ${userId}`, 'success');
            } else {
                showMessage(data.error || 'Error updating INVESTED_WITH', 'error');
                if (data.error === 'Invalid password') {
                    showMessage('Password verification failed. Please try again.', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error updating INVESTED_WITH', 'error');
        })
        .finally(() => {
            if (saveBtn) {
                saveBtn.innerHTML = originalBtnText || '💾 Save';
                saveBtn.disabled = false;
            }
            window.pendingInvestedSave = null;
        });
    }

    // Execution History Functions
    function loadExecutionHistory() {
        const container = document.getElementById('execution-history-list');
        if (!container) return;
        
        container.innerHTML = '<div style="text-align: center; padding: 40px;">Loading execution history...</div>';
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_execution_history'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.history) {
                if (Object.keys(data.history).length === 0) {
                    container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No execution history records found.</div>';
                    return;
                }
                
                let html = '<div class="execution-timeline">';
                
                // Sort by time descending (newest first)
                const sortedKeys = Object.keys(data.history).sort((a, b) => {
                    const timeA = new Date(data.history[a].time);
                    const timeB = new Date(data.history[b].time);
                    return timeB - timeA;
                });
                
                for (const key of sortedKeys) {
                    const record = data.history[key];
                    const formattedTime = formatDate(record.time);
                    
                    // Determine badge class based on type
                    let typeBadgeClass = 'execution-type-info';
                    let typeText = record.type || 'info';
                    if (record.type === 'error') {
                        typeBadgeClass = 'execution-type-error';
                    } else if (record.type === 'success') {
                        typeBadgeClass = 'execution-type-success';
                    } else if (record.type === 'warning') {
                        typeBadgeClass = 'execution-type-warning';
                    }
                    
                    // Determine update badge class
                    let updateBadgeClass = 'execution-update-default';
                    let updateText = record.update || 'none';
                    if (record.update === 'new') {
                        updateBadgeClass = 'execution-update-new';
                    } else if (record.update === 'updated') {
                        updateBadgeClass = 'execution-update-updated';
                    } else if (record.update === 'deleted') {
                        updateBadgeClass = 'execution-update-deleted';
                    }
                    
                    html += `
                        <div class="execution-record">
                            <div class="execution-header">
                                <span class="execution-time">${formattedTime}</span>
                                <div class="execution-badges">
                                    <span class="execution-type-badge ${typeBadgeClass}">${escapeHtml(typeText)}</span>
                                </div>
                            </div>
                            ${record.section ? `<div class="execution-message">Section: ${escapeHtml(record.section)}</div>` : ''}
                            <div class="execution-message">${escapeHtml(record.message)}</div>
                                <div class="execution-badges">
                                <span class="execution-update-badge ${updateBadgeClass}">${escapeHtml(updateText)}</span>
                            </div>
                            
                        </div>
                    `;
                }
                
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = `<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading execution history: ${data.error || 'Unknown error'}</div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading execution history</div>';
        });
    }
    // Execution History Variables
    let currentExecutionUserId = null;
    let currentExecutionSourceTable = null;

    // Load users for execution history selection
    function loadUsersForExecutionHistory() {
        const userListDiv = document.getElementById('execution-user-list');
        if (!userListDiv) return;
        
        userListDiv.innerHTML = '<div style="text-align: center; padding: 20px;">Loading users...</div>';
        
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
                    userListDiv.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No users found in the system</div>';
                    return;
                }
                
                data.users.forEach(user => {
                    const userDiv = document.createElement('div');
                    userDiv.className = 'user-item';
                    userDiv.setAttribute('data-user-id', user.id);
                    userDiv.setAttribute('data-source', user.source);
                    userDiv.setAttribute('data-fullname', user.fullname || '');
                    userDiv.setAttribute('data-email', user.email || '');
                    userDiv.onclick = () => {
                        selectUserForExecutionHistory(user.id, user.source, user.fullname || 'N/A', user.email || 'N/A');
                    };
                    userDiv.innerHTML = `
                        <div class="user-item-name">${escapeHtml(user.fullname || 'N/A')}</div>
                        <div class="user-item-email">${escapeHtml(user.email || 'N/A')}</div>
                        <div style="font-size: 10px; opacity: 0.5;">ID: ${user.id} | ${user.source}</div>
                    `;
                    userListDiv.appendChild(userDiv);
                });
            } else {
                userListDiv.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading users</div>';
                showMessage(data.error || 'Error loading users', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            userListDiv.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading users</div>';
            showMessage('Error loading users', 'error');
        });
    }

    // Select user for execution history
    function selectUserForExecutionHistory(userId, sourceTable, fullname, email) {
        if (!userId || !sourceTable) {
            showMessage('Invalid user selection', 'error');
            return;
        }
        
        currentExecutionUserId = userId;
        currentExecutionSourceTable = sourceTable;
        
        // Remove active class from all user items
        document.querySelectorAll('#execution-user-list .user-item').forEach(item => {
            item.classList.remove('active');
        });
        // Add active class to selected user
        const selectedItem = document.querySelector(`#execution-user-list .user-item[data-user-id="${userId}"]`);
        if (selectedItem) selectedItem.classList.add('active');
        
        // Update user info display
        const nameSpan = document.getElementById('selected-execution-user-name');
        const emailSpan = document.getElementById('selected-execution-user-email');
        const sourceSpan = document.getElementById('selected-execution-user-source');
        if (nameSpan) nameSpan.textContent = fullname;
        if (emailSpan) emailSpan.textContent = email;
        if (sourceSpan) sourceSpan.textContent = sourceTable;
        
        // Load execution history for selected user
        loadExecutionHistoryForUser();
    }

    // Load execution history for selected user
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
                
                // Sort by time descending (newest first)
                const sortedKeys = Object.keys(data.history).sort((a, b) => {
                    const timeA = new Date(data.history[a].time);
                    const timeB = new Date(data.history[b].time);
                    return timeB - timeA;
                });
                
                for (const key of sortedKeys) {
                    const record = data.history[key];
                    const formattedTime = formatDate(record.time);
                    
                    // Determine badge class based on type
                    let typeBadgeClass = 'execution-type-info';
                    let typeText = record.type || 'info';
                    if (record.type === 'error') {
                        typeBadgeClass = 'execution-type-error';
                    } else if (record.type === 'success') {
                        typeBadgeClass = 'execution-type-success';
                    } else if (record.type === 'warning') {
                        typeBadgeClass = 'execution-type-warning';
                    }
                    
                    // Determine update badge class
                    let updateBadgeClass = 'execution-update-default';
                    let updateText = record.update || 'none';
                    if (record.update === 'new') {
                        updateBadgeClass = 'execution-update-new';
                    } else if (record.update === 'updated') {
                        updateBadgeClass = 'execution-update-updated';
                    } else if (record.update === 'deleted') {
                        updateBadgeClass = 'execution-update-deleted';
                    }
                    
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
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading execution history</div>';
        });
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

    // Autotrading & Restriction Management Functions
    function loadAllUsersForAutoTrading() {
        const userListDiv = document.getElementById('autotrading-user-list');
        if (!userListDiv) return;
        
        userListDiv.innerHTML = '<div style="text-align: center; padding: 20px;">Loading users...</div>';
        
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
                    userListDiv.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No users found in the system</div>';
                    return;
                }
                
                data.users.forEach(user => {
                    const userDiv = document.createElement('div');
                    userDiv.className = 'user-item';
                    userDiv.setAttribute('data-user-id', user.id);
                    userDiv.setAttribute('data-source', user.source);
                    userDiv.setAttribute('data-fullname', user.fullname || '');
                    userDiv.setAttribute('data-email', user.email || '');
                    userDiv.onclick = () => {
                        selectUserForAutoTrading(user.id, user.source, user.fullname || 'N/A', user.email || 'N/A');
                    };
                    userDiv.innerHTML = `
                        <div class="user-item-name">${escapeHtml(user.fullname || 'N/A')}</div>
                        <div class="user-item-email">${escapeHtml(user.email || 'N/A')}</div>
                        <div style="font-size: 10px; opacity: 0.5;">ID: ${user.id} | ${user.source}</div>
                    `;
                    userListDiv.appendChild(userDiv);
                });
            } else {
                userListDiv.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading users</div>';
                showMessage(data.error || 'Error loading users', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            userListDiv.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c;">Error loading users</div>';
            showMessage('Error loading users', 'error');
        });
    }
    
    function selectUserForAutoTrading(userId, sourceTable, fullname, email) {
        if (!userId || !sourceTable) {
            showMessage('Invalid user selection', 'error');
            return;
        }
        
        currentAutoTradingUserId = userId;
        currentAutoTradingSourceTable = sourceTable;
        
        document.querySelectorAll('#autotrading-user-list .user-item').forEach(item => {
            item.classList.remove('active');
        });
        const selectedItem = document.querySelector(`#autotrading-user-list .user-item[data-user-id="${userId}"]`);
        if (selectedItem) selectedItem.classList.add('active');
        
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
                                <option value="1" ${currentAutoTradingData.demo_account == 1 ? 'selected' : ''}>Enable Demo  (1)</option>
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
    
    function saveSingleSetting(settingName) {
        if (!currentAutoTradingUserId || !currentAutoTradingSourceTable) {
            showMessage('No user selected', 'error');
            return;
        }
        
        let value = null;
        if (settingName === 'enable_autotrading') {
            const select = document.getElementById('enable_autotrading_select');
            if (select) value = parseInt(select.value);
        } else if (settingName === 'bypass_restriction') {
            const select = document.getElementById('bypass_restriction_select');
            if (select) value = parseInt(select.value);
        }
        
        if (value === null) {
            showMessage('Unable to get setting value', 'error');
            return;
        }
        
        showPasswordModalForAutoTrading(currentAutoTradingUserId, currentAutoTradingSourceTable, settingName, value);
    }
    
    function saveAutoTradingSettings() {
        if (!currentAutoTradingUserId || !currentAutoTradingSourceTable) {
            showMessage('No user selected', 'error');
            return;
        }
        
        const autoTradingValue = parseInt(document.getElementById('enable_autotrading_select')?.value || 1);
        const bypassValue = parseInt(document.getElementById('bypass_restriction_select')?.value || 0);
        const demoAccountValue = parseInt(document.getElementById('demo_account_select')?.value || 0);
        
        showPasswordModalForAutoTradingBatch(currentAutoTradingUserId, currentAutoTradingSourceTable, autoTradingValue, bypassValue, demoAccountValue);
    }
    
    function showPasswordModalForAutoTrading(userId, sourceTable, columnName, value) {
        let modal = document.getElementById('autotrading-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'autotrading-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>🔐 Security Verification</h3>
                    <p>Please enter your admin password to save this setting.</p>
                    <input type="password" id="autotrading-password-input" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="autotrading-modal-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="autotrading-modal-confirm" class="modal-confirm-btn">Confirm Save</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        window.pendingAutoTradingSave = { userId, sourceTable, columnName, value, isBatch: false };
        
        modal.classList.add('show');
        const passwordInput = document.getElementById('autotrading-password-input');
        if (passwordInput) {
            passwordInput.value = '';
            passwordInput.focus();
        }
        
        const confirmBtn = document.getElementById('autotrading-modal-confirm');
        const cancelBtn = document.getElementById('autotrading-modal-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                showMessage('Password is required', 'error');
                passwordInput.focus();
                return;
            }
            modal.classList.remove('show');
            executeSaveAutoTradingSetting(password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
            window.pendingAutoTradingSave = null;
            showMessage('Save cancelled', 'success');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
                window.pendingAutoTradingSave = null;
            }
        };
    }
    
    function showPasswordModalForAutoTradingBatch(userId, sourceTable, autoTradingValue, bypassValue, demoAccountValue) {
        let modal = document.getElementById('autotrading-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'autotrading-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>Security Verification</h3>
                    <p>Please enter your admin password to save all settings.</p>
                    <input type="password" id="autotrading-password-input" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="autotrading-modal-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="autotrading-modal-confirm" class="modal-confirm-btn">Confirm Save</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        window.pendingAutoTradingSave = { userId, sourceTable, autoTradingValue, bypassValue, demoAccountValue, isBatch: true };
        
        modal.classList.add('show');
        const passwordInput = document.getElementById('autotrading-password-input');
        if (passwordInput) {
            passwordInput.value = '';
            passwordInput.focus();
        }
        
        const confirmBtn = document.getElementById('autotrading-modal-confirm');
        const cancelBtn = document.getElementById('autotrading-modal-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                showMessage('Password is required', 'error');
                passwordInput.focus();
                return;
            }
            modal.classList.remove('show');
            executeSaveAutoTradingSettingBatch(password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
            window.pendingAutoTradingSave = null;
            showMessage('Save cancelled', 'success');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
                window.pendingAutoTradingSave = null;
            }
        };
    }
    
    function executeSaveAutoTradingSetting(password) {
        const saveData = window.pendingAutoTradingSave;
        if (!saveData || saveData.isBatch) {
            showMessage('Invalid save operation', 'error');
            return;
        }
        
        const { userId, sourceTable, columnName, value } = saveData;
        
        let formData = new URLSearchParams();
        formData.append('action', 'update_user_setting');
        formData.append('user_id', userId);
        formData.append('source_table', sourceTable);
        formData.append('column_name', columnName);
        formData.append('value', value);
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
                if (columnName === 'enable_autotrading') {
                    currentAutoTradingData.enable_autotrading = value;
                } else if (columnName === 'bypass_restriction') {
                    currentAutoTradingData.bypass_restriction = value;
                }
                showMessage(`${columnName} updated successfully!`, 'success');
            } else {
                showMessage(data.error || 'Error updating setting', 'error');
                if (data.error === 'Invalid password') {
                    showMessage('Password verification failed. Please try again.', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error updating setting', 'error');
        })
        .finally(() => {
            window.pendingAutoTradingSave = null;
        });
    }
    
    function executeSaveAutoTradingSettingBatch(password) {
        const saveData = window.pendingAutoTradingSave;
        if (!saveData || !saveData.isBatch) {
            showMessage('Invalid save operation', 'error');
            return;
        }
        
        const { userId, sourceTable, autoTradingValue, bypassValue, demoAccountValue } = saveData;
        
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
                showMessage('All settings updated successfully!', 'success');
            } else {
                showMessage(data.error || 'Error updating settings', 'error');
                if (data.error === 'Invalid password') {
                    showMessage('Password verification failed. Please try again.', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error updating settings', 'error');
        })
        .finally(() => {
            window.pendingAutoTradingSave = null;
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Update application status for selected user
    function updateApplicationStatus() {
        if (!currentUserId || !currentSourceTable) {
            showMessage('No user selected', 'error');
            return;
        }
        
        const select = document.getElementById('application-status-select');
        const newStatus = select.value;
        
        if (!newStatus) {
            showMessage('Please select a status', 'error');
            return;
        }
        
        showPasswordModalForStatusUpdate(currentUserId, currentSourceTable, newStatus);
    }

    function showPasswordModalForStatusUpdate(userId, sourceTable, newStatus) {
        let modal = document.getElementById('status-update-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'status-update-password-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3>Security Verification</h3>
                    <p>Please enter your admin password to update application status.</p>
                    <input type="password" id="status-update-password-input" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" id="status-update-modal-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="status-update-modal-confirm" class="modal-confirm-btn">Confirm Update</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        window.pendingStatusUpdate = { userId, sourceTable, newStatus };
        
        modal.classList.add('show');
        const passwordInput = document.getElementById('status-update-password-input');
        if (passwordInput) {
            passwordInput.value = '';
            passwordInput.focus();
        }
        
        const confirmBtn = document.getElementById('status-update-modal-confirm');
        const cancelBtn = document.getElementById('status-update-modal-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                showMessage('Password is required', 'error');
                passwordInput.focus();
                return;
            }
            modal.classList.remove('show');
            executeStatusUpdate(password);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
            window.pendingStatusUpdate = null;
            showMessage('Update cancelled', 'success');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
                window.pendingStatusUpdate = null;
            }
        };
    }
    // Toggle expand/collapse for Server Configuration
    let isServerConfigExpanded = false;

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
    // ==================== TAB SEARCH FUNCTIONS ====================

    // User List Search (User Configuration Tab)
    function setupUserListSearch() {
        const searchInput = document.getElementById('user-list-search');
        if (!searchInput) return;
        
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const userItems = document.querySelectorAll('#user-items-list .user-item');
            let visibleCount = 0;
            
            userItems.forEach(item => {
                const name = (item.getAttribute('data-fullname') || '').toLowerCase();
                const email = (item.getAttribute('data-email') || '').toLowerCase();
                const userId = (item.getAttribute('data-user-id') || '');
                
                if (searchTerm === '' || name.includes(searchTerm) || email.includes(searchTerm) || userId.includes(searchTerm)) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            const container = document.getElementById('user-items-list');
            let noResultsMsg = container.querySelector('.no-results-msg');
            if (visibleCount === 0 && searchTerm !== '') {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.className = 'no-results-msg';
                    noResultsMsg.style.cssText = 'text-align: center; padding: 20px; color: #888;';
                    noResultsMsg.innerHTML = 'No matching users found';
                    container.appendChild(noResultsMsg);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        });
    }

    // Generic table search setup
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

    // Execution User List Search
    function setupExecutionUserSearch() {
        const searchInput = document.getElementById('execution-user-search');
        if (!searchInput) return;
        
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const userItems = document.querySelectorAll('#execution-user-list .user-item');
            
            userItems.forEach(item => {
                const name = (item.getAttribute('data-fullname') || '').toLowerCase();
                const email = (item.getAttribute('data-email') || '').toLowerCase();
                const userId = (item.getAttribute('data-user-id') || '');
                
                if (searchTerm === '' || name.includes(searchTerm) || email.includes(searchTerm) || userId.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    // Auto Trading User List Search
    function setupAutoTradingUserSearch() {
        const searchInput = document.getElementById('autotrading-user-search');
        if (!searchInput) return;
        
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const userItems = document.querySelectorAll('#autotrading-user-list .user-item');
            
            userItems.forEach(item => {
                const name = (item.getAttribute('data-fullname') || '').toLowerCase();
                const email = (item.getAttribute('data-email') || '').toLowerCase();
                const userId = (item.getAttribute('data-user-id') || '');
                
                if (searchTerm === '' || name.includes(searchTerm) || email.includes(searchTerm) || userId.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    function executeStatusUpdate(password) {
        const updateData = window.pendingStatusUpdate;
        if (!updateData) {
            showMessage('No pending update', 'error');
            return;
        }
        
        const { userId, sourceTable, newStatus } = updateData;
        
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
                showMessage(`Application status updated to ${newStatus} for User ID ${userId}`, 'success');
                
                // Update the user list item status display
                const userItem = document.querySelector(`.user-item[data-user-id="${userId}"]`);
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
                showMessage(data.error || 'Error updating status', 'error');
                if (data.error === 'Invalid password') {
                    showMessage('Password verification failed. Please try again.', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error updating status', 'error');
        })
        .finally(() => {
            window.pendingStatusUpdate = null;
        });
    }
</script>

