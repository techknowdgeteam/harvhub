<?php
// revenue.php - Revenue Dashboard with Tab Navigation
// This file is included in serveraccount.php when view=paid_users
?>

<div class="revenue-container" id="revenue-container">
    <!-- Header -->
    <div class="revenue-header">
        <h2>Revenue Dashboard</h2>
    </div>

    <!-- Main Tabs: Active | Completed -->
    <div class="revenue-tabs-wrapper">
        <div class="revenue-tabs main-tabs" id="main-tabs">
            <button class="tab-btn active" data-tab="active" onclick="Revenue.switchTab('active')">
                Active
                <span class="tab-badge" id="active-count">0</span>
            </button>
            <button class="tab-btn" data-tab="completed" onclick="Revenue.switchTab('completed')">
                Completed
                <span class="tab-badge" id="completed-count">0</span>
            </button>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB: ACTIVE                                  -->
    <!-- ============================================ -->
    <div id="tab-active" class="tab-content active">
        <!-- Active Sub Tabs -->
        <div class="revenue-tabs-wrapper sub-tabs-wrapper">
            <div class="revenue-tabs sub-tabs" id="active-sub-tabs">
                <button class="tab-btn sub-tab-btn active" data-subtab="all" onclick="Revenue.switchActiveSubTab('all')">
                    All Investors
                    <span class="tab-badge" id="active-all-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="above-threshold" onclick="Revenue.switchActiveSubTab('above-threshold')">
                    Above Threshold
                    <span class="tab-badge" id="active-above-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="below-threshold" onclick="Revenue.switchActiveSubTab('below-threshold')">
                    Below Threshold
                    <span class="tab-badge" id="active-below-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="profit" onclick="Revenue.switchActiveSubTab('profit')">
                    In Profit
                    <span class="tab-badge" id="active-profit-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="loss" onclick="Revenue.switchActiveSubTab('loss')">
                    In Loss
                    <span class="tab-badge" id="active-loss-count">0</span>
                </button>
            </div>
        </div>

        <!-- Summary Cards for Active Tab -->
        <div class="summary-cubes" id="active-summary-cubes">
            <div class="summary-cube">
                <div class="cube-value" id="active-total-investment">$0.00</div>
                <div class="cube-label">Total Investment</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="active-total-pnl">$0.00</div>
                <div class="cube-label">Total P&L</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="active-current-balance">$0.00</div>
                <div class="cube-label">Current Balance</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="active-user-share">$0.00</div>
                <div class="cube-label">Users Share</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="active-server-share">$0.00</div>
                <div class="cube-label">Server Share</div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="search-bar">
            <span class="search-icon">Q</span>
            <input type="text" id="active-search-input" class="search-input" placeholder="Search active users by name, email, or ID..." oninput="Revenue.filterActiveTable()" autocomplete="off">
            <span class="search-clear" id="active-search-clear" onclick="Revenue.clearActiveSearch()" style="display:none;">X</span>
        </div>

        <!-- Active Users Table -->
        <div class="users-table-container">
            <div class="table-wrapper">
                <table class="revenue-table" id="active-users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Broker Balance</th>
                            <th>P&L</th>
                            <th>Current Balance</th>
                            <th>User Share</th>
                            <th>Server Share</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="active-users-body">
                        <tr><td colspan="7" style="text-align:center;padding:40px;color:#888;">Loading active users...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB: COMPLETED                               -->
    <!-- ============================================ -->
    <div id="tab-completed" class="tab-content">
        <!-- Completed Sub Tabs -->
        <div class="revenue-tabs-wrapper sub-tabs-wrapper">
            <div class="revenue-tabs sub-tabs" id="completed-sub-tabs">
                <button class="tab-btn sub-tab-btn active" data-subtab="unpaid" onclick="Revenue.switchCompletedSubTab('unpaid')">
                    Unpaid
                    <span class="tab-badge" id="unpaid-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="payment-made" onclick="Revenue.switchCompletedSubTab('payment-made')">
                    Payment Made
                    <span class="tab-badge" id="payment-made-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="payment-confirmed" onclick="Revenue.switchCompletedSubTab('payment-confirmed')">
                    Payment Confirmed
                    <span class="tab-badge" id="payment-confirmed-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="failed" onclick="Revenue.switchCompletedSubTab('failed')">
                    Failed
                    <span class="tab-badge" id="failed-count">0</span>
                </button>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="search-bar">
            <span class="search-icon">Q</span>
            <input type="text" id="completed-search-input" class="search-input" placeholder="Search completed users by name, email, or ID..." oninput="Revenue.filterCompletedTable()" autocomplete="off">
            <span class="search-clear" id="completed-search-clear" onclick="Revenue.clearCompletedSearch()" style="display:none;">X</span>
        </div>

        <!-- Completed Users Table -->
        <div class="users-table-container">
            <div class="table-wrapper">
                <table class="revenue-table" id="completed-users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Invested With</th>
                            <th>Profit</th>
                            <th>Server Share</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="completed-users-body">
                        <tr><td colspan="6" style="text-align:center;padding:40px;color:#888;">Loading completed users...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const Revenue = {
        // Data
        allActiveUsers: [],
        filteredActiveUsers: [],
        allCompletedUsers: [],
        filteredCompletedUsers: [],
        
        // State
        currentTab: 'active',
        currentActiveSubTab: 'all',
        currentCompletedSubTab: 'unpaid',
        activeSearchTerm: '',
        completedSearchTerm: '',
        
        // Config
        serverSharePercent: 30,
        userSharePercent: 70,
        minProfitForSplit: 30,

        // ============================================
        // INITIALIZATION
        // ============================================
        init: function() {
            // Get shares from server account
            this.serverSharePercent = parseInt(document.querySelector('[data-server-share]')?.dataset?.serverShare) || 30;
            this.userSharePercent = parseInt(document.querySelector('[data-user-share]')?.dataset?.userShare) || 70;
            this.minProfitForSplit = parseFloat(document.querySelector('[data-min-profit]')?.dataset?.minProfit) || 30;
            
            this.loadUsers();
            this.bindEvents();
        },

        bindEvents: function() {
            // No modal events needed anymore
        },

        // ============================================
        // LOAD USERS
        // ============================================
        loadUsers: function() {
            this.loadActiveUsers();
            this.loadCompletedUsers();
        },

        loadActiveUsers: function() {
            fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=get_active_investors'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.allActiveUsers = data.users || [];
                    this.filteredActiveUsers = [...this.allActiveUsers];
                    this.renderActiveUsers();
                    this.updateActiveBadges();
                    this.updateActiveCubes();
                } else {
                    this.allActiveUsers = [];
                    this.filteredActiveUsers = [];
                    this.renderActiveUsers();
                }
            })
            .catch(error => {
                console.error('Error loading active users:', error);
                this.allActiveUsers = [];
                this.filteredActiveUsers = [];
                this.renderActiveUsers();
            });
        },

        loadCompletedUsers: function() {
            fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=get_completed_investors'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.allCompletedUsers = data.users || [];
                    // Apply current sub-tab filter immediately
                    this.filteredCompletedUsers = this.getFilteredCompletedUsers();
                    this.renderCompletedUsers();
                    this.updateCompletedBadges();
                    this.updateBadge('completed-count', this.allCompletedUsers.length);
                } else {
                    this.allCompletedUsers = [];
                    this.filteredCompletedUsers = [];
                    this.renderCompletedUsers();
                }
            })
            .catch(error => {
                console.error('Error loading completed users:', error);
                this.allCompletedUsers = [];
                this.filteredCompletedUsers = [];
                this.renderCompletedUsers();
            });
        },

        // ============================================
        // FILTER ACTIVE USERS
        // ============================================
        getFilteredActiveUsers: function() {
            let users = [...this.allActiveUsers];
            const subTab = this.currentActiveSubTab;

            switch(subTab) {
                case 'all':
                    break;
                case 'above-threshold':
                    users = users.filter(u => {
                        const pnl = parseFloat(u.profitandloss) || 0;
                        return pnl > this.minProfitForSplit;
                    });
                    break;
                case 'below-threshold':
                    users = users.filter(u => {
                        const pnl = parseFloat(u.profitandloss) || 0;
                        return pnl > 0 && pnl <= this.minProfitForSplit;
                    });
                    break;
                case 'profit':
                    users = users.filter(u => {
                        const pnl = parseFloat(u.profitandloss) || 0;
                        return pnl > 0;
                    });
                    break;
                case 'loss':
                    users = users.filter(u => {
                        const pnl = parseFloat(u.profitandloss) || 0;
                        return pnl < 0;
                    });
                    break;
                default:
                    break;
            }

            if (this.activeSearchTerm) {
                const term = this.activeSearchTerm.toLowerCase();
                users = users.filter(u => {
                    const name = (u.fullname || '').toLowerCase();
                    const email = (u.email || '').toLowerCase();
                    const id = String(u.id || '');
                    return name.includes(term) || email.includes(term) || id.includes(term);
                });
            }

            return users;
        },

        filterActiveTable: function() {
            const input = document.getElementById('active-search-input');
            this.activeSearchTerm = input.value.trim();
            document.getElementById('active-search-clear').style.display = this.activeSearchTerm ? 'block' : 'none';
            this.filteredActiveUsers = this.getFilteredActiveUsers();
            this.renderActiveUsers();
            this.updateActiveCubes();
        },

        clearActiveSearch: function() {
            document.getElementById('active-search-input').value = '';
            document.getElementById('active-search-clear').style.display = 'none';
            this.activeSearchTerm = '';
            this.filteredActiveUsers = this.getFilteredActiveUsers();
            this.renderActiveUsers();
            this.updateActiveCubes();
        },

        // ============================================
        // FILTER COMPLETED USERS
        // ============================================
        getFilteredCompletedUsers: function() {
            let users = [...this.allCompletedUsers];
            const subTab = this.currentCompletedSubTab;

            switch(subTab) {
                case 'unpaid':
                    users = users.filter(u => {
                        const l = (u.current_loyalties || u.loyalties || '').toLowerCase();
                        return l === 'unpaid-payment' || l === 'unpaid_payment' || l === 'unpaid';
                    });
                    break;
                case 'payment-made':
                    users = users.filter(u => {
                        const l = (u.current_loyalties || u.loyalties || '').toLowerCase();
                        return l === 'payment-made' || l === 'payment_made';
                    });
                    break;
                case 'payment-confirmed':
                    users = users.filter(u => {
                        const l = (u.current_loyalties || u.loyalties || '').toLowerCase();
                        return l === 'payment-confirmed' || l === 'payment_confirmed';
                    });
                    break;
                case 'failed':
                    users = users.filter(u => {
                        const l = (u.current_loyalties || u.loyalties || '').toLowerCase();
                        return l === 'failed-payment' || l === 'failed_payment' || l === 'payment-failed' || l === 'payment_failed';
                    });
                    break;
                default:
                    break;
            }

            if (this.completedSearchTerm) {
                const term = this.completedSearchTerm.toLowerCase();
                users = users.filter(u => {
                    const name = (u.fullname || '').toLowerCase();
                    const email = (u.email || '').toLowerCase();
                    const id = String(u.id || '');
                    return name.includes(term) || email.includes(term) || id.includes(term);
                });
            }

            return users;
        },

        filterCompletedTable: function() {
            const input = document.getElementById('completed-search-input');
            this.completedSearchTerm = input.value.trim();
            document.getElementById('completed-search-clear').style.display = this.completedSearchTerm ? 'block' : 'none';
            this.filteredCompletedUsers = this.getFilteredCompletedUsers();
            this.renderCompletedUsers();
        },

        clearCompletedSearch: function() {
            document.getElementById('completed-search-input').value = '';
            document.getElementById('completed-search-clear').style.display = 'none';
            this.completedSearchTerm = '';
            this.filteredCompletedUsers = this.getFilteredCompletedUsers();
            this.renderCompletedUsers();
        },

        // ============================================
        // RENDER ACTIVE USERS
        // ============================================
        renderActiveUsers: function() {
            const tbody = document.getElementById('active-users-body');
            const users = this.filteredActiveUsers;

            if (!users || users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#888;">No active users found</td></tr>';
                return;
            }

            let html = '';
            users.forEach(user => {
                const brokerBalance = parseFloat(user.broker_balance) || 0;
                const profitAndLoss = parseFloat(user.profitandloss) || 0;
                const currentBalance = brokerBalance + profitAndLoss;
                const profitClass = profitAndLoss >= 0 ? 'profit' : 'loss';
                const balanceClass = currentBalance >= 0 ? 'profit' : 'loss';
                
                // Calculate User Share and Server Share (only if profit > 0)
                let userShare = 0;
                let serverShare = 0;
                if (profitAndLoss > 0) {
                    userShare = (profitAndLoss * this.userSharePercent) / 100;
                    serverShare = (profitAndLoss * this.serverSharePercent) / 100;
                }
                
                let status = 'Active';
                let statusClass = 'status-active';
                if (profitAndLoss > this.minProfitForSplit) {
                    status = 'Above Threshold';
                    statusClass = 'status-above';
                } else if (profitAndLoss > 0) {
                    status = 'In Profit';
                    statusClass = 'status-profit';
                } else if (profitAndLoss < 0) {
                    status = 'In Loss';
                    statusClass = 'status-loss';
                } else {
                    status = 'Break Even';
                    statusClass = 'status-breakeven';
                }

                html += `
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-name">${this.escapeHtml(user.fullname || 'N/A')}</div>
                                <div class="user-email">${this.escapeHtml(user.email || 'N/A')}</div>
                                <div class="user-id">ID: ${user.id}</div>
                            </div>
                        </td>
                        <td>$${this.formatNumber(brokerBalance)}</td>
                        <td class="${profitClass}">$${this.formatNumber(profitAndLoss)}</td>
                        <td class="${balanceClass}">$${this.formatNumber(currentBalance)}</td>
                        <td>$${this.formatNumber(userShare)}</td>
                        <td>$${this.formatNumber(serverShare)}</td>
                        <td><span class="status-badge ${statusClass}">${status}</span></td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        },

        // ============================================
        // RENDER COMPLETED USERS
        // ============================================
        renderCompletedUsers: function() {
            const tbody = document.getElementById('completed-users-body');
            const users = this.filteredCompletedUsers;

            if (!users || users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#888;">No completed users found</td></tr>';
                return;
            }

            let html = '';
            users.forEach(user => {
                const summary = user.payment_summary || {};
                const profit = parseFloat(user.profitandloss) || 0;
                const statusLabel = this.getStatusLabel(user.current_loyalties || user.loyalties || '');
                const statusClass = this.getStatusClass(user.current_loyalties || user.loyalties || '');
                const currentStatus = (user.current_loyalties || user.loyalties || '').toLowerCase();

                let displayShare = 0;
                if (currentStatus === 'payment-confirmed' || currentStatus === 'payment_confirmed') {
                    displayShare = summary.total_payment_confirmed || 0;
                } else if (currentStatus === 'payment-made' || currentStatus === 'payment_made') {
                    displayShare = summary.total_payment_made || 0;
                } else if (currentStatus === 'unpaid-payment' || currentStatus === 'unpaid_payment' || currentStatus === 'unpaid') {
                    displayShare = summary.total_unpaid_revenue || 0;
                } else if (currentStatus === 'failed-payment' || currentStatus === 'failed_payment' || currentStatus === 'payment-failed' || currentStatus === 'payment_failed') {
                    displayShare = summary.total_failed_payments || 0;
                }

                html += `
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-name">${this.escapeHtml(user.fullname || 'N/A')}</div>
                                <div class="user-email">${this.escapeHtml(user.email || 'N/A')}</div>
                                <div class="user-id">ID: ${user.id}</div>
                            </div>
                        </td>
                        <td>${this.escapeHtml(user.invested_with || 'N/A')}</td>
                        <td class="${profit >= 0 ? 'profit' : 'loss'}">$${this.formatNumber(profit)}</td>
                        <td>$${this.formatNumber(displayShare)}</td>
                        <td><span class="status-badge ${statusClass}">${statusLabel}</span></td>
                        <td>
                            <select class="status-select" data-user-id="${user.id}" data-source="${user.source}" onchange="Revenue.updateUserStatus(this)">
                                <option value="">Select Status</option>
                                <option value="unpaid-payment" ${currentStatus === 'unpaid-payment' || currentStatus === 'unpaid_payment' || currentStatus === 'unpaid' ? 'selected' : ''}>Unpaid</option>
                                <option value="payment-made" ${currentStatus === 'payment-made' || currentStatus === 'payment_made' ? 'selected' : ''}>Payment Made</option>
                                <option value="payment-confirmed" ${currentStatus === 'payment-confirmed' || currentStatus === 'payment_confirmed' ? 'selected' : ''}>Payment Confirmed</option>
                                <option value="failed-payment" ${currentStatus === 'failed-payment' || currentStatus === 'failed_payment' || currentStatus === 'payment-failed' || currentStatus === 'payment_failed' ? 'selected' : ''}>Failed</option>
                            </select>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        },

        // ============================================
        // UPDATE USER STATUS
        // ============================================
        updateUserStatus: function(selectElement) {
            const userId = selectElement.dataset.userId;
            const source = selectElement.dataset.source;
            const newStatus = selectElement.value;
            
            if (!newStatus) return;
            
            // Show loading state
            selectElement.disabled = true;
            selectElement.style.opacity = '0.6';
            
            // You can trigger a form submission or AJAX call here
            // For now, we'll just log it
            console.log(`Update user ${userId} (${source}) status to: ${newStatus}`);
            
            // Example: Submit a form or make an AJAX request
            // window.location.href = `serveraccount.php?view=paid_users&update_status=1&user_id=${userId}&source=${source}&status=${newStatus}`;
            
            // Re-enable after a moment
            setTimeout(() => {
                selectElement.disabled = false;
                selectElement.style.opacity = '1';
            }, 1000);
        },

        // ============================================
        // SUMMARY CUBES
        // ============================================
        updateActiveCubes: function() {
            const users = this.filteredActiveUsers;
            let totalInvestment = 0;
            let totalPnl = 0;
            let totalBalance = 0;
            let totalUserShare = 0;
            let totalServerShare = 0;

            users.forEach(user => {
                const brokerBalance = parseFloat(user.broker_balance) || 0;
                const pnl = parseFloat(user.profitandloss) || 0;
                totalInvestment += brokerBalance;
                totalPnl += pnl;
                totalBalance += brokerBalance + pnl;

                if (pnl > 0) {
                    const serverShare = (pnl * this.serverSharePercent) / 100;
                    const userShare = (pnl * this.userSharePercent) / 100;
                    totalServerShare += serverShare;
                    totalUserShare += userShare;
                }
            });

            document.getElementById('active-total-investment').textContent = '$' + this.formatNumber(totalInvestment);
            document.getElementById('active-total-pnl').textContent = '$' + this.formatNumber(totalPnl);
            document.getElementById('active-current-balance').textContent = '$' + this.formatNumber(totalBalance);
            document.getElementById('active-user-share').textContent = '$' + this.formatNumber(totalUserShare);
            document.getElementById('active-server-share').textContent = '$' + this.formatNumber(totalServerShare);
        },

        // ============================================
        // BADGE UPDATES
        // ============================================
        updateBadge: function(id, count) {
            const el = document.getElementById(id);
            if (el) el.textContent = count || 0;
        },

        updateActiveBadges: function() {
            const users = this.allActiveUsers;
            const counts = {
                all: users.length,
                'above-threshold': 0,
                'below-threshold': 0,
                profit: 0,
                loss: 0
            };

            users.forEach(u => {
                const pnl = parseFloat(u.profitandloss) || 0;
                if (pnl > this.minProfitForSplit) counts['above-threshold']++;
                else if (pnl > 0 && pnl <= this.minProfitForSplit) counts['below-threshold']++;
                if (pnl > 0) counts.profit++;
                else if (pnl < 0) counts.loss++;
            });

            Object.keys(counts).forEach(key => {
                this.updateBadge('active-' + key + '-count', counts[key]);
            });
            this.updateBadge('active-count', users.length);
        },

        updateCompletedBadges: function() {
            const users = this.allCompletedUsers;
            const counts = {
                unpaid: 0,
                'payment-made': 0,
                'payment-confirmed': 0,
                failed: 0
            };

            users.forEach(u => {
                const l = (u.current_loyalties || u.loyalties || '').toLowerCase();
                if (l === 'unpaid-payment' || l === 'unpaid_payment' || l === 'unpaid') {
                    counts.unpaid++;
                } else if (l === 'payment-made' || l === 'payment_made') {
                    counts['payment-made']++;
                } else if (l === 'payment-confirmed' || l === 'payment_confirmed') {
                    counts['payment-confirmed']++;
                } else if (l === 'failed-payment' || l === 'failed_payment' || l === 'payment-failed' || l === 'payment_failed') {
                    counts.failed++;
                }
            });

            Object.keys(counts).forEach(key => {
                this.updateBadge(key + '-count', counts[key]);
            });
        },

        // ============================================
        // STATUS HELPERS
        // ============================================
        getStatusLabel: function(status) {
            const s = (status || '').toLowerCase();
            if (s === 'payment-confirmed' || s === 'payment_confirmed') return 'Payment Confirmed';
            if (s === 'payment-made' || s === 'payment_made') return 'Payment Made';
            if (s === 'unpaid-payment' || s === 'unpaid_payment' || s === 'unpaid') return 'Unpaid';
            if (s === 'failed-payment' || s === 'failed_payment' || s === 'payment-failed' || s === 'payment_failed') return 'Failed';
            return status || 'Unknown';
        },

        getStatusClass: function(status) {
            const s = (status || '').toLowerCase();
            if (s === 'payment-confirmed' || s === 'payment_confirmed') return 'status-confirmed';
            if (s === 'payment-made' || s === 'payment_made') return 'status-made';
            if (s === 'unpaid-payment' || s === 'unpaid_payment' || s === 'unpaid') return 'status-unpaid';
            if (s === 'failed-payment' || s === 'failed_payment' || s === 'payment-failed' || s === 'payment_failed') return 'status-failed';
            return 'status-default';
        },

        // ============================================
        // TAB NAVIGATION
        // ============================================
        switchTab: function(tab) {
            this.currentTab = tab;

            document.querySelectorAll('.main-tabs .tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tab);
            });

            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.toggle('active', el.id === 'tab-' + tab);
            });
            
            // Re-render completed tab with proper filtering when switching to it
            if (tab === 'completed') {
                this.filteredCompletedUsers = this.getFilteredCompletedUsers();
                this.renderCompletedUsers();
            }
        },

        switchActiveSubTab: function(subTab) {
            this.currentActiveSubTab = subTab;

            document.querySelectorAll('#active-sub-tabs .sub-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.subtab === subTab);
            });

            this.filteredActiveUsers = this.getFilteredActiveUsers();
            this.renderActiveUsers();
            this.updateActiveCubes();
        },

        switchCompletedSubTab: function(subTab) {
            this.currentCompletedSubTab = subTab;

            document.querySelectorAll('#completed-sub-tabs .sub-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.subtab === subTab);
            });

            this.filteredCompletedUsers = this.getFilteredCompletedUsers();
            this.renderCompletedUsers();
        },

        // ============================================
        // UTILITY FUNCTIONS
        // ============================================
        formatNumber: function(num) {
            if (num === undefined || num === null || isNaN(num)) return '0.00';
            return parseFloat(num).toFixed(2);
        },

        formatDate: function(dateStr) {
            if (!dateStr) return 'N/A';
            try {
                const d = new Date(dateStr);
                if (isNaN(d.getTime())) return dateStr;
                return d.toLocaleDateString('en-US', {
                    month: 'short',
                    day: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch(e) {
                return dateStr;
            }
        },

        escapeHtml: function(str) {
            if (!str) return '';
            return String(str).replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
    };

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', () => {
        Revenue.init();
    });
</script>