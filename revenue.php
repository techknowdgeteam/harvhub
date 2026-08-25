<?php
// revenue.php - Revenue Dashboard with Tab Navigation
// This file is included in serveraccount.php when view=paid_users
?>

<div class="revenue-container" id="revenue-container">
    <!-- Header -->
    <div class="revenue-header">
        <h2>Revenue Dashboard</h2>
    </div>

    <!-- Main Tabs: Active | Completed | Revenue History -->
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
            <button class="tab-btn" data-tab="revenue-history" onclick="Revenue.switchTab('revenue-history')">
                Revenue History
                <span class="tab-badge" id="revenue-history-count">0</span>
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
                <button class="tab-btn sub-tab-btn" data-subtab="unusual" onclick="Revenue.switchActiveSubTab('unusual')">
                    Unusual Activity
                    <span class="tab-badge" id="active-unusual-count">0</span>
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

        <!-- Unusual Activity Sub-Sub Tabs (shown only when unusual tab is active) -->
        <div class="revenue-tabs-wrapper sub-sub-tabs-wrapper" id="unusual-sub-sub-tabs" style="display:none;">
            <div class="revenue-tabs sub-tabs" id="unusual-sub-tabs">
                <button class="tab-btn sub-tab-btn active" data-unusual-subtab="all" onclick="Revenue.switchUnusualSubTab('all')">
                    All Unusual
                    <span class="tab-badge" id="unusual-all-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-unusual-subtab="withdrawals" onclick="Revenue.switchUnusualSubTab('withdrawals')">
                    Unauthorized Withdrawals
                    <span class="tab-badge" id="unusual-withdrawals-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-unusual-subtab="trades" onclick="Revenue.switchUnusualSubTab('trades')">
                    Unauthorized Trades
                    <span class="tab-badge" id="unusual-trades-count">0</span>
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
            <div class="summary-cube">
                <div class="cube-value" id="active-investors-profit">0/0</div>
                <div class="cube-label">Investors in Profit</div>
            </div>
        </div>

        <!-- Search Bar - Dummy + Real Input -->
        <div class="search-bar-wrapper">
            <div class="search-bar search-bar-dummy" id="active-search-dummy" onclick="Revenue.activateSearch('active')">
                <span class="search-icon">Q</span>
                <span class="search-placeholder">Search active users by name, email, or ID...</span>
            </div>
            <div class="search-bar search-bar-real" id="active-search-real" style="display:none;">
                <span class="search-icon">Q</span>
                <input type="text" id="active-search-input" class="search-input" placeholder="Search active users by name, email, or ID..." oninput="Revenue.filterActiveTable()" autocomplete="off">
                <span class="search-clear" id="active-search-clear" onclick="Revenue.clearActiveSearch()" style="display:none;">✕</span>
            </div>
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
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="active-users-body">
                        <tr><td colspan="8" style="text-align:center;padding:40px;color:#888;">Loading active users...</td></tr>
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

        <!-- Search Bar - Dummy + Real Input -->
        <div class="search-bar-wrapper">
            <div class="search-bar search-bar-dummy" id="completed-search-dummy" onclick="Revenue.activateSearch('completed')">
                <span class="search-icon">Q</span>
                <span class="search-placeholder">Search completed users by name, email, or ID...</span>
            </div>
            <div class="search-bar search-bar-real" id="completed-search-real" style="display:none;">
                <span class="search-icon">Q</span>
                <input type="text" id="completed-search-input" class="search-input" placeholder="Search completed users by name, email, or ID..." oninput="Revenue.filterCompletedTable()" autocomplete="off">
                <span class="search-clear" id="completed-search-clear" onclick="Revenue.clearCompletedSearch()" style="display:none;">✕</span>
            </div>
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

    <!-- ============================================ -->
    <!-- TAB: REVENUE HISTORY                         -->
    <!-- ============================================ -->
    <div id="tab-revenue-history" class="tab-content">
        <!-- ============================================ -->
        <!-- GLOBAL SUMMARY CARDS FOR REVENUE HISTORY    -->
        <!-- ============================================ -->
        <div class="summary-cubes" id="revenue-history-global-cubes">
            <div class="summary-cube">
                <div class="cube-value" id="rev-global-total-investment">$0.00</div>
                <div class="cube-label">Total Investment</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="rev-global-total-pnl">$0.00</div>
                <div class="cube-label">Total P&L</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="rev-global-total-unpaid">$0.00</div>
                <div class="cube-label">Total Unpaid Payments</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="rev-global-total-payments-made">$0.00</div>
                <div class="cube-label">Total Payments Made</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="rev-global-total-payments-confirmed">$0.00</div>
                <div class="cube-label">Total Payments Confirmed</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="rev-global-total-failed">$0.00</div>
                <div class="cube-label">Total Failed Payments</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="rev-global-total-cancelled">$0.00</div>
                <div class="cube-label">Total Cancelled Contracts</div>
            </div>
        </div>

        <!-- Search Bar for Revenue History Users -->
        <div class="search-bar-wrapper" style="margin-bottom: 16px;">
            <div class="search-bar search-bar-dummy" id="rev-history-global-search-dummy" onclick="Revenue.activateRevenueHistoryGlobalSearch()">
                <span class="search-icon">Q</span>
                <span class="search-placeholder">Search users by name, email, or ID...</span>
            </div>
            <div class="search-bar search-bar-real" id="rev-history-global-search-real" style="display:none;">
                <span class="search-icon">Q</span>
                <input type="text" id="rev-history-global-search-input" class="search-input" placeholder="Search users by name, email, or ID..." oninput="Revenue.filterRevenueHistoryGlobalUsers()" autocomplete="off">
                <span class="search-clear" id="rev-history-global-search-clear" onclick="Revenue.clearRevenueHistoryGlobalSearch()" style="display:none;">✕</span>
            </div>
        </div>

        <!-- Revenue History Users List - Overview (shown when no user selected) -->
        <div id="revenue-history-overview">
            <div class="users-table-container">
                <div class="table-wrapper">
                    <div id="revenue-history-users-list" style="padding: 0 !important;">
                        <div style="text-align:center;padding:20px;color:#888;">Loading users...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue History User Details (shown when a user is selected) -->
        <div id="revenue-history-user-details" style="display:none; margin-top: 20px;">
            <!-- Back to Overview Button + Search Bar (below it) -->
            <div class="revenue-history-user-header">
                <h3 id="revenue-history-user-name">User Name</h3>
                <button class="back-to-overview-btn" onclick="Revenue.clearRevenueHistoryUserSelection()">← Back to Overview</button>
            </div>

            <!-- Search Bar - Below Back button, opens modal -->
            <div class="search-bar-wrapper" id="revenue-history-search-wrapper">
                <div class="search-bar search-bar-dummy" id="rev-history-search-dummy" onclick="Revenue.showRevenueHistoryUsersModal()">
                    <span class="search-icon">👤</span>
                    <span class="search-placeholder" id="revenue-history-search-placeholder">Search users by name, email, or ID...</span>
                </div>
            </div>
            
            <!-- Revenue History Sub Tabs -->
            <div class="revenue-tabs-wrapper sub-tabs-wrapper" id="revenue-history-sub-tabs-wrapper">
                <div class="revenue-tabs sub-tabs" id="revenue-history-sub-tabs">
                    <button class="tab-btn sub-tab-btn active" data-revenue-subtab="all" onclick="Revenue.switchRevenueHistorySubTab('all')">
                        All Revenue
                        <span class="tab-badge" id="rev-all-count">0</span>
                    </button>
                    <button class="tab-btn sub-tab-btn" data-revenue-subtab="unpaid" onclick="Revenue.switchRevenueHistorySubTab('unpaid')">
                        Unpaid Payments
                        <span class="tab-badge" id="rev-unpaid-count">0</span>
                    </button>
                    <button class="tab-btn sub-tab-btn" data-revenue-subtab="payment-made" onclick="Revenue.switchRevenueHistorySubTab('payment-made')">
                        Payments Made
                        <span class="tab-badge" id="rev-payment-made-count">0</span>
                    </button>
                    <button class="tab-btn sub-tab-btn" data-revenue-subtab="payment-confirmed" onclick="Revenue.switchRevenueHistorySubTab('payment-confirmed')">
                        Payments Confirmed
                        <span class="tab-badge" id="rev-payment-confirmed-count">0</span>
                    </button>
                    <button class="tab-btn sub-tab-btn" data-revenue-subtab="failed" onclick="Revenue.switchRevenueHistorySubTab('failed')">
                        Failed Payments
                        <span class="tab-badge" id="rev-failed-count">0</span>
                    </button>
                    <button class="tab-btn sub-tab-btn" data-revenue-subtab="cancelled" onclick="Revenue.switchRevenueHistorySubTab('cancelled')">
                        Cancelled Contracts
                        <span class="tab-badge" id="rev-cancelled-count">0</span>
                    </button>
                </div>
            </div>

            <!-- User Summary Cards -->
            <div class="summary-cubes" id="revenue-history-user-cubes">
                <div class="summary-cube">
                    <div class="cube-value" id="rev-user-total-investment">$0.00</div>
                    <div class="cube-label">Total Investment</div>
                </div>
                <div class="summary-cube">
                    <div class="cube-value" id="rev-user-total-pnl">$0.00</div>
                    <div class="cube-label">Total P&L</div>
                </div>
                <div class="summary-cube">
                    <div class="cube-value" id="rev-user-total-server-share">$0.00</div>
                    <div class="cube-label">Total Server Share</div>
                </div>
                <div class="summary-cube">
                    <div class="cube-value" id="rev-user-total-user-share">$0.00</div>
                    <div class="cube-label">Total Investors Share</div>
                </div>
                <div class="summary-cube">
                    <div class="cube-value" id="rev-user-total-payments-made">$0.00</div>
                    <div class="cube-label">Payments Made</div>
                </div>
                <div class="summary-cube">
                    <div class="cube-value" id="rev-user-total-payments-confirmed">$0.00</div>
                    <div class="cube-label">Payments Confirmed</div>
                </div>
                <div class="summary-cube">
                    <div class="cube-value" id="rev-user-total-cancelled">$0.00</div>
                    <div class="cube-label">Cancelled Contracts</div>
                </div>
                <div class="summary-cube">
                    <div class="cube-value" id="rev-user-total-failed">$0.00</div>
                    <div class="cube-label">Failed Payments</div>
                </div>
            </div>
            
            <!-- Revenue History Records Table -->
            <div id="revenue-history-records-container" style="margin-top: 20px;">
                <h4 style="margin-bottom: 10px;">Revenue Records</h4>
                <div id="revenue-history-records-list" style="max-height: 400px; overflow-y: auto;">
                    <div style="text-align:center;padding:20px;color:#888;">No revenue records found</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- REVENUE HISTORY USERS MODAL                  -->
<!-- ============================================ -->
<div id="revenue-history-users-modal" class="modal-overlay" style="display:none;" onclick="Revenue.closeRevenueHistoryUsersModalIfClickOutside(event)">
    <div class="modal-container modal-large" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span>Select User</span>
            <span class="modal-close" onclick="Revenue.closeRevenueHistoryUsersModal()">✕</span>
        </div>
        <div class="modal-body">
            <div class="users-modal-search">
                <input type="text" id="revenue-history-modal-search-input" class="user-search-input" placeholder="Search users by name, email, or ID..." onkeyup="Revenue.filterRevenueHistoryModalUsers()">
            </div>
        </div>
        <div class="modal-body" id="revenue-history-modal-list" style="max-height: 50vh; overflow-y: auto;">
            <!-- User list rendered here -->
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- USER DETAIL VIEW - Full Screen Overlay       -->
<!-- ============================================ -->
<div id="user-detail-overlay" class="detail-overlay" style="display:none;">
    <div class="detail-overlay-content">
        <div class="detail-overlay-header">
            <button class="back-btn" onclick="Revenue.closeUserDetail()">← Back</button>
            <h2 id="detail-user-name">User Details</h2>
            <span></span>
        </div>
        <div class="detail-overlay-body" id="detail-overlay-body">
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p>Loading user details...</p>
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
        unusualUsers: [],
        filteredUnusualUsers: [],
        allRevenueHistoryUsers: [],
        filteredRevenueHistoryUsers: [],
        selectedRevenueHistoryUser: null,
        revenueHistoryData: {},
        selectedUserRevenueHistory: [],
        
        // State
        currentTab: 'active',
        currentActiveSubTab: 'all',
        currentUnusualSubTab: 'all',
        currentCompletedSubTab: 'unpaid',
        currentRevenueHistorySubTab: 'all',
        activeSearchTerm: '',
        completedSearchTerm: '',
        revenueHistorySearchTerm: '',
        selectedUserId: null,
        selectedUserSource: null,
        isDetailViewOpen: false,
        isRevenueHistorySearchActive: false,
        isMobileView: false,
        
        // Config
        serverSharePercent: 30,
        userSharePercent: 70,
        minProfitForSplit: 30,

        // ============================================
        // INITIALIZATION
        // ============================================
        init: function() {
            this.serverSharePercent = parseInt(document.querySelector('[data-server-share]')?.dataset?.serverShare) || 30;
            this.userSharePercent = parseInt(document.querySelector('[data-user-share]')?.dataset?.userShare) || 70;
            this.minProfitForSplit = parseFloat(document.querySelector('[data-min-profit]')?.dataset?.minProfit) || 30;
            
            this.loadUsers();
            this.bindEvents();
        },

        bindEvents: function() {
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && Revenue.isDetailViewOpen) {
                    Revenue.closeUserDetail();
                }
                if (e.key === 'Escape') {
                    Revenue.closeRevenueHistoryUsersModal();
                }
                if (e.key === 'Escape') {
                    Revenue.deactivateSearch('active');
                    Revenue.deactivateSearch('completed');
                }
            });

            document.addEventListener('click', function(e) {
                const row = e.target.closest('.clickable-row');
                if (row && !e.target.closest('.action-select') && !e.target.closest('.status-select')) {
                    const userId = row.dataset.userId;
                    const source = row.dataset.source || 'insiders';
                    Revenue.viewUserDetail(userId, source);
                }
            });
            
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.search-bar-wrapper') && !e.target.closest('.search-bar')) {
                    Revenue.deactivateSearch('active');
                    Revenue.deactivateSearch('completed');
                }
            });
        },

        // ============================================
        // SEARCH FUNCTIONS - Dummy/Real toggle
        // ============================================
        activateSearch: function(tab) {
            const dummyId = tab + '-search-dummy';
            const realId = tab + '-search-real';
            const dummy = document.getElementById(dummyId);
            const real = document.getElementById(realId);
            const input = document.getElementById(tab + '-search-input');
            
            if (dummy) dummy.style.display = 'none';
            if (real) real.style.display = 'flex';
            if (input) {
                input.focus();
                if (tab === 'active' && this.activeSearchTerm) {
                    input.value = this.activeSearchTerm;
                } else if (tab === 'completed' && this.completedSearchTerm) {
                    input.value = this.completedSearchTerm;
                }
            }
        },

        deactivateSearch: function(tab) {
            const dummyId = tab + '-search-dummy';
            const realId = tab + '-search-real';
            const dummy = document.getElementById(dummyId);
            const real = document.getElementById(realId);
            
            if (tab === 'active' && !this.activeSearchTerm) {
                if (dummy) dummy.style.display = 'flex';
                if (real) real.style.display = 'none';
            } else if (tab === 'completed' && !this.completedSearchTerm) {
                if (dummy) dummy.style.display = 'flex';
                if (real) real.style.display = 'none';
            } else if (tab === 'active' && this.activeSearchTerm) {
                if (dummy) dummy.style.display = 'none';
                if (real) real.style.display = 'flex';
            } else if (tab === 'completed' && this.completedSearchTerm) {
                if (dummy) dummy.style.display = 'none';
                if (real) real.style.display = 'flex';
            }
        },

        // ============================================
        // REVENUE HISTORY GLOBAL SEARCH
        // ============================================
        activateRevenueHistoryGlobalSearch: function() {
            const dummy = document.getElementById('rev-history-global-search-dummy');
            const real = document.getElementById('rev-history-global-search-real');
            const input = document.getElementById('rev-history-global-search-input');
            
            if (dummy) dummy.style.display = 'none';
            if (real) real.style.display = 'flex';
            if (input) {
                input.focus();
                if (this.revenueHistorySearchTerm) {
                    input.value = this.revenueHistorySearchTerm;
                }
            }
        },

        deactivateRevenueHistoryGlobalSearch: function() {
            const dummy = document.getElementById('rev-history-global-search-dummy');
            const real = document.getElementById('rev-history-global-search-real');
            
            if (!this.revenueHistorySearchTerm) {
                if (dummy) dummy.style.display = 'flex';
                if (real) real.style.display = 'none';
            } else {
                if (dummy) dummy.style.display = 'none';
                if (real) real.style.display = 'flex';
            }
        },

        filterRevenueHistoryGlobalUsers: function() {
            const input = document.getElementById('rev-history-global-search-input');
            this.revenueHistorySearchTerm = input.value.trim();
            document.getElementById('rev-history-global-search-clear').style.display = this.revenueHistorySearchTerm ? 'block' : 'none';
            
            this.filteredRevenueHistoryUsers = this.getFilteredRevenueHistoryUsers();
            
            if (!this.selectedRevenueHistoryUser) {
                this.renderRevenueHistoryUsersList(this.filteredRevenueHistoryUsers);
            }
        },

        clearRevenueHistoryGlobalSearch: function() {
            document.getElementById('rev-history-global-search-input').value = '';
            document.getElementById('rev-history-global-search-clear').style.display = 'none';
            this.revenueHistorySearchTerm = '';
            this.deactivateRevenueHistoryGlobalSearch();
            
            this.filteredRevenueHistoryUsers = this.getFilteredRevenueHistoryUsers();
            
            if (!this.selectedRevenueHistoryUser) {
                this.renderRevenueHistoryUsersList(this.filteredRevenueHistoryUsers);
            }
        },

        getFilteredRevenueHistoryUsers: function() {
            let users = [...this.allRevenueHistoryUsers];
            
            if (this.revenueHistorySearchTerm) {
                const term = this.revenueHistorySearchTerm.toLowerCase();
                users = users.filter(u => {
                    const name = (u.fullname || '').toLowerCase();
                    const email = (u.email || '').toLowerCase();
                    const id = String(u.id || '');
                    return name.includes(term) || email.includes(term) || id.includes(term);
                });
            }
            
            return users;
        },

        // ============================================
        // LOAD USERS
        // ============================================
        loadUsers: function() {
            this.loadActiveUsers();
            this.loadCompletedUsers();
            this.loadRevenueHistoryUsers();
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
        // LOAD REVENUE HISTORY USERS
        // ============================================
        loadRevenueHistoryUsers: function() {
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
                    this.allRevenueHistoryUsers = data.users || [];
                    
                    this.allRevenueHistoryUsers = this.allRevenueHistoryUsers.map(user => {
                        if (user.revenue_history) {
                            if (typeof user.revenue_history === 'string') {
                                try {
                                    user.revenue_history = JSON.parse(user.revenue_history);
                                } catch (e) {
                                    user.revenue_history = [];
                                }
                            }
                        } else {
                            user.revenue_history = [];
                        }
                        if (!Array.isArray(user.revenue_history)) {
                            user.revenue_history = [];
                        }
                        return user;
                    });
                    
                    this.filteredRevenueHistoryUsers = this.getFilteredRevenueHistoryUsers();
                    this.updateBadge('revenue-history-count', this.allRevenueHistoryUsers.length);
                    
                    // Update global summary cubes
                    this.updateRevenueHistoryGlobalCubes();
                    
                    // Show overview (users list) if no user selected
                    if (!this.selectedRevenueHistoryUser) {
                        this.showOverview();
                    }
                } else {
                    this.allRevenueHistoryUsers = [];
                    this.filteredRevenueHistoryUsers = [];
                }
            })
            .catch(error => {
                console.error('Error loading revenue history users:', error);
                this.allRevenueHistoryUsers = [];
                this.filteredRevenueHistoryUsers = [];
            });
        },

        // ============================================
        // UPDATE REVENUE HISTORY GLOBAL CUBES
        // ============================================
        updateRevenueHistoryGlobalCubes: function() {
            let totals = {
                totalInvestment: 0,
                totalPnl: 0,
                totalUnpaid: 0,
                totalPaymentsMade: 0,
                totalPaymentsConfirmed: 0,
                totalFailed: 0,
                totalCancelled: 0
            };

            this.allRevenueHistoryUsers.forEach(user => {
                let history = user.revenue_history || [];
                
                if (typeof history === 'string') {
                    try {
                        history = JSON.parse(history);
                    } catch (e) {
                        history = [];
                    }
                }
                if (!Array.isArray(history)) {
                    history = [];
                }

                if (history.length > 0) {
                    const latestRecord = history[0] || {};
                    totals.totalInvestment += parseFloat(latestRecord.starting_balance || 0);
                    totals.totalPnl += parseFloat(latestRecord.profit || 0);
                }

                history.forEach(record => {
                    const loyalties = (record.loyalties || '').toLowerCase();
                    const serverShare = parseFloat(record.server_share || 0);
                    
                    if (loyalties.includes('unpaid') || loyalties === 'unpaid-payment' || loyalties === 'unpaid_payment') {
                        totals.totalUnpaid += serverShare;
                    } else if (loyalties.includes('payment-made') || loyalties === 'payment_made') {
                        totals.totalPaymentsMade += serverShare;
                    } else if (loyalties.includes('payment-confirmed') || loyalties === 'payment_confirmed') {
                        totals.totalPaymentsConfirmed += serverShare;
                    } else if (loyalties.includes('failed') || loyalties === 'failed-payment' || loyalties === 'failed_payment' || loyalties === 'payment-failed' || loyalties === 'payment_failed') {
                        totals.totalFailed += serverShare;
                    } else if (loyalties.includes('cancelled') || loyalties === 'contract_cancelled' || loyalties === 'contract-cancelled') {
                        totals.totalCancelled += serverShare;
                    }
                });
            });

            this.updateCubeValue('rev-global-total-investment', totals.totalInvestment);
            this.updateCubeValue('rev-global-total-pnl', totals.totalPnl);
            this.updateCubeValue('rev-global-total-unpaid', totals.totalUnpaid);
            this.updateCubeValue('rev-global-total-payments-made', totals.totalPaymentsMade);
            this.updateCubeValue('rev-global-total-payments-confirmed', totals.totalPaymentsConfirmed);
            this.updateCubeValue('rev-global-total-failed', totals.totalFailed);
            this.updateCubeValue('rev-global-total-cancelled', totals.totalCancelled);
        },

        // ============================================
        // SHOW/HIDE OVERVIEW / USER DETAILS
        // ============================================
        showOverview: function() {
            document.getElementById('revenue-history-overview').style.display = 'block';
            document.getElementById('revenue-history-user-details').style.display = 'none';
            this.renderRevenueHistoryUsersList(this.filteredRevenueHistoryUsers);
        },

        showUserDetails: function() {
            document.getElementById('revenue-history-overview').style.display = 'none';
            document.getElementById('revenue-history-user-details').style.display = 'block';
        },

        // ============================================
        // RENDER REVENUE HISTORY USERS LIST
        // ============================================
        renderRevenueHistoryUsersList: function(users) {
            const container = document.getElementById('revenue-history-users-list');
            
            if (!container) return;
            
            if (users.length === 0) {
                container.innerHTML = '<div style="text-align:center;padding:30px;color:#888;">No users found</div>';
                return;
            }
            
            let html = '';
            users.forEach(user => {
                let history = user.revenue_history || [];
                if (typeof history === 'string') {
                    try {
                        history = JSON.parse(history);
                    } catch (e) {
                        history = [];
                    }
                }
                if (!Array.isArray(history)) {
                    history = [];
                }
                const recordCount = history.length;
                
                html += `
                    <div class="revenue-history-user-item" 
                        onclick="Revenue.selectRevenueHistoryUser('${user.id}', '${user.source}')">
                        <div class="user-name">${this.escapeHtml(user.fullname || 'N/A')}</div>
                        <div class="user-email">${this.escapeHtml(user.email || 'N/A')}</div>
                        <div class="user-id">ID: ${user.id}</div>
                        ${recordCount > 0 ? 
                            `<div class="user-record-count">${recordCount} records</div>` : 
                            `<div class="user-record-count" style="color:#888;">No records</div>`
                        }
                    </div>
                `;
            });
            
            container.innerHTML = html;
        },

        // ============================================
        // SELECT REVENUE HISTORY USER
        // ============================================
        selectRevenueHistoryUser: function(userId, source) {
            const user = this.allRevenueHistoryUsers.find(u => u.id == userId && u.source === source);
            if (!user) return;
            
            this.selectRevenueHistoryUserFromModal(userId, source);
        },

        // ============================================
        // PROCESS SINGLE USER REVENUE HISTORY
        // ============================================
        processUserRevenueHistory: function(user) {
            let history = user.revenue_history || [];
            
            if (!Array.isArray(history)) {
                if (typeof history === 'string') {
                    try {
                        history = JSON.parse(history);
                    } catch (e) {
                        history = [];
                    }
                } else {
                    history = [];
                }
            }
            
            let totals = {
                totalInvestment: 0,
                totalPnl: 0,
                totalServerShare: 0,
                totalUserShare: 0,
                totalPaymentsMade: 0,
                totalPaymentsConfirmed: 0,
                totalCancelled: 0,
                totalFailed: 0
            };
            
            if (history.length > 0) {
                const latestRecord = history[0] || {};
                totals.totalInvestment = parseFloat(latestRecord.starting_balance || 0);
                totals.totalPnl = parseFloat(latestRecord.profit || 0);
            }
            
            history.forEach(record => {
                const loyalties = (record.loyalties || '').toLowerCase();
                const serverShare = parseFloat(record.server_share || 0);
                const userShare = parseFloat(record.user_share || 0);
                
                totals.totalUserShare += userShare;
                
                if (loyalties.includes('payment-made') || loyalties === 'payment_made') {
                    totals.totalPaymentsMade += serverShare;
                    totals.totalServerShare += serverShare;
                } else if (loyalties.includes('payment-confirmed') || loyalties === 'payment_confirmed') {
                    totals.totalPaymentsConfirmed += serverShare;
                    totals.totalServerShare += serverShare;
                } else if (loyalties.includes('unpaid') || loyalties === 'unpaid-payment' || loyalties === 'unpaid_payment') {
                    totals.totalServerShare += serverShare;
                } else if (loyalties.includes('cancelled')) {
                    totals.totalCancelled += serverShare;
                    totals.totalServerShare += serverShare;
                } else if (loyalties.includes('failed')) {
                    totals.totalFailed += serverShare;
                    totals.totalServerShare += serverShare;
                }
            });
            
            return totals;
        },

        // ============================================
        // RENDER USER REVENUE HISTORY SUMMARY
        // ============================================
        renderUserRevenueHistorySummary: function(user) {
            if (user.revenue_history && typeof user.revenue_history === 'string') {
                try {
                    user.revenue_history = JSON.parse(user.revenue_history);
                } catch (e) {
                    user.revenue_history = [];
                }
            }
            if (!Array.isArray(user.revenue_history)) {
                user.revenue_history = [];
            }
            
            const totals = this.processUserRevenueHistory(user);
            this.selectedUserRevenueHistory = user.revenue_history || [];
            
            this.updateCubeValue('rev-user-total-investment', totals.totalInvestment);
            this.updateCubeValue('rev-user-total-pnl', totals.totalPnl);
            this.updateCubeValue('rev-user-total-server-share', totals.totalServerShare);
            this.updateCubeValue('rev-user-total-user-share', totals.totalUserShare);
            this.updateCubeValue('rev-user-total-payments-made', totals.totalPaymentsMade);
            this.updateCubeValue('rev-user-total-payments-confirmed', totals.totalPaymentsConfirmed);
            this.updateCubeValue('rev-user-total-cancelled', totals.totalCancelled);
            this.updateCubeValue('rev-user-total-failed', totals.totalFailed);
            
            this.renderUserRevenueRecords(this.selectedUserRevenueHistory);
            this.updateUserRevenueHistorySubTabBadges(user);
        },

        updateCubeValue: function(id, value) {
            const el = document.getElementById(id);
            if (!el) return;
            const numValue = parseFloat(value) || 0;
            el.textContent = '$' + this.formatNumber(numValue);
            
            if (numValue > 0) {
                el.style.color = '#4caf50';
            } else if (numValue < 0) {
                el.style.color = '#f44336';
            } else {
                el.style.color = 'var(--text-color, #ffffff)';
            }
        },

        // ============================================
        // RENDER USER REVENUE RECORDS
        // ============================================
        renderUserRevenueRecords: function(history) {
            const container = document.getElementById('revenue-history-records-list');
            
            if (!Array.isArray(history)) {
                history = [];
            }
            
            let filteredHistory = this.filterRevenueHistoryBySubTab(history);
            
            if (filteredHistory.length === 0) {
                container.innerHTML = '<div style="text-align:center;padding:40px;color:#888;">No revenue records found for this filter</div>';
                return;
            }
            
            const sortedHistory = [...filteredHistory].sort((a, b) => {
                const idA = parseInt(a.id) || 0;
                const idB = parseInt(b.id) || 0;
                return idB - idA;
            });
            
            let html = `
                <div style="overflow-x:auto; border-radius: 8px; border: 1px solid var(--border-color);">
                    <table style="width:100%; border-collapse: collapse; font-size: 12px;">
                        <thead>
                            <tr style="background: var(--bg-secondary);">
                                <th style="padding: 8px 10px; text-align: left; border-bottom: 2px solid var(--border-color);">Contract ID</th>
                                <th style="padding: 8px 10px; text-align: left; border-bottom: 2px solid var(--border-color);">Start</th>
                                <th style="padding: 8px 10px; text-align: left; border-bottom: 2px solid var(--border-color);">End</th>
                                <th style="padding: 8px 10px; text-align: right; border-bottom: 2px solid var(--border-color);">Starting Balance</th>
                                <th style="padding: 8px 10px; text-align: right; border-bottom: 2px solid var(--border-color);">Current Balance</th>
                                <th style="padding: 8px 10px; text-align: right; border-bottom: 2px solid var(--border-color);">Profit</th>
                                <th style="padding: 8px 10px; text-align: right; border-bottom: 2px solid var(--border-color);">Server Share</th>
                                <th style="padding: 8px 10px; text-align: right; border-bottom: 2px solid var(--border-color);">User Share</th>
                                <th style="padding: 8px 10px; text-align: left; border-bottom: 2px solid var(--border-color);">Status</th>
                                <th style="padding: 8px 10px; text-align: left; border-bottom: 2px solid var(--border-color);">Invested With</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            sortedHistory.forEach(record => {
                const startingBalance = parseFloat(record.starting_balance) || 0;
                const currentBalance = parseFloat(record.current_balance) || 0;
                const profit = parseFloat(record.profit) || 0;
                const serverShare = parseFloat(record.server_share) || 0;
                const userShare = parseFloat(record.user_share) || 0;
                const status = record.loyalties || 'Unknown';
                const statusClass = this.getStatusClass(status);
                const investedWith = record.invested_with || 'N/A';
                
                html += `
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 6px 10px; font-size: 10px; font-family: monospace;">${this.escapeHtml(record.contract_id || 'N/A')}</td>
                        <td style="padding: 6px 10px;">${this.formatDate(record.execution_start_date)}</td>
                        <td style="padding: 6px 10px;">${this.formatDate(record.execution_end_date)}</td>
                        <td style="padding: 6px 10px; text-align: right;">$${this.formatNumber(startingBalance)}</td>
                        <td style="padding: 6px 10px; text-align: right;">$${this.formatNumber(currentBalance)}</td>
                        <td style="padding: 6px 10px; text-align: right; color: ${profit >= 0 ? '#4caf50' : '#f44336'}; font-weight: 600;">$${this.formatNumber(profit)}</td>
                        <td style="padding: 6px 10px; text-align: right;">$${this.formatNumber(serverShare)}</td>
                        <td style="padding: 6px 10px; text-align: right;">$${this.formatNumber(userShare)}</td>
                        <td style="padding: 6px 10px;"><span class="status-badge ${statusClass}">${this.escapeHtml(this.getStatusLabel(status))}</span></td>
                        <td style="padding: 6px 10px; font-size: 11px;">${this.escapeHtml(investedWith)}</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 10px; font-size: 11px; color: #888; text-align: right;">
                    ${sortedHistory.length} record(s) found
                </div>
            `;
            
            container.innerHTML = html;
        },

        // ============================================
        // UPDATE USER REVENUE HISTORY SUB-TAB BADGES
        // ============================================
        updateUserRevenueHistorySubTabBadges: function(user) {
            let history = user.revenue_history || [];
            if (!Array.isArray(history)) {
                if (typeof history === 'string') {
                    try {
                        history = JSON.parse(history);
                    } catch (e) {
                        history = [];
                    }
                } else {
                    history = [];
                }
            }
            
            let counts = {
                all: 0,
                unpaid: 0,
                'payment-made': 0,
                'payment-confirmed': 0,
                failed: 0,
                cancelled: 0
            };
            
            history.forEach(record => {
                const loyalties = (record.loyalties || '').toLowerCase();
                counts.all++;
                
                if (loyalties.includes('unpaid') || loyalties === 'unpaid-payment' || loyalties === 'unpaid_payment') {
                    counts.unpaid++;
                } else if (loyalties.includes('payment-made') || loyalties === 'payment_made') {
                    counts['payment-made']++;
                } else if (loyalties.includes('payment-confirmed') || loyalties === 'payment_confirmed') {
                    counts['payment-confirmed']++;
                } else if (loyalties.includes('failed') || loyalties === 'failed-payment' || loyalties === 'failed_payment' || loyalties === 'payment-failed' || loyalties === 'payment_failed') {
                    counts.failed++;
                } else if (loyalties.includes('cancelled') || loyalties === 'contract_cancelled' || loyalties === 'contract-cancelled') {
                    counts.cancelled++;
                }
            });
            
            this.updateBadge('rev-all-count', counts.all);
            this.updateBadge('rev-unpaid-count', counts.unpaid);
            this.updateBadge('rev-payment-made-count', counts['payment-made']);
            this.updateBadge('rev-payment-confirmed-count', counts['payment-confirmed']);
            this.updateBadge('rev-failed-count', counts.failed);
            this.updateBadge('rev-cancelled-count', counts.cancelled);
        },

        // ============================================
        // FILTER REVENUE HISTORY BY SUB-TAB
        // ============================================
        filterRevenueHistoryBySubTab: function(history) {
            const subTab = this.currentRevenueHistorySubTab;
            
            if (subTab === 'all') {
                return history;
            }
            
            return history.filter(record => {
                const loyalties = (record.loyalties || '').toLowerCase();
                
                switch(subTab) {
                    case 'unpaid':
                        return loyalties.includes('unpaid') || loyalties === 'unpaid-payment' || loyalties === 'unpaid_payment';
                    case 'payment-made':
                        return loyalties.includes('payment-made') || loyalties === 'payment_made';
                    case 'payment-confirmed':
                        return loyalties.includes('payment-confirmed') || loyalties === 'payment_confirmed';
                    case 'failed':
                        return loyalties.includes('failed') || loyalties === 'failed-payment' || loyalties === 'failed_payment' || loyalties === 'payment-failed' || loyalties === 'payment_failed';
                    case 'cancelled':
                        return loyalties.includes('cancelled') || loyalties === 'contract_cancelled' || loyalties === 'contract-cancelled';
                    default:
                        return true;
                }
            });
        },

        // ============================================
        // SWITCH REVENUE HISTORY SUB-TAB
        // ============================================
        switchRevenueHistorySubTab: function(subTab) {
            this.currentRevenueHistorySubTab = subTab;
            
            document.querySelectorAll('#revenue-history-sub-tabs .sub-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.revenueSubtab === subTab);
            });
            
            if (this.selectedRevenueHistoryUser) {
                this.renderUserRevenueRecords(this.selectedUserRevenueHistory);
            }
        },

        // ============================================
        // REVENUE HISTORY USERS MODAL
        // ============================================
        showRevenueHistoryUsersModal: function() {
            const modal = document.getElementById('revenue-history-users-modal');
            const searchInput = document.getElementById('revenue-history-modal-search-input');
            
            modal.style.display = 'flex';
            searchInput.value = '';
            
            this.renderRevenueHistoryModalUsers(this.allRevenueHistoryUsers);
            searchInput.focus();
        },

        closeRevenueHistoryUsersModal: function() {
            document.getElementById('revenue-history-users-modal').style.display = 'none';
        },

        closeRevenueHistoryUsersModalIfClickOutside: function(event) {
            if (event.target.id === 'revenue-history-users-modal') {
                this.closeRevenueHistoryUsersModal();
            }
        },

        filterRevenueHistoryModalUsers: function() {
            const searchTerm = document.getElementById('revenue-history-modal-search-input').value.toLowerCase();
            const filteredUsers = this.allRevenueHistoryUsers.filter(user => {
                const name = (user.fullname || '').toLowerCase();
                const email = (user.email || '').toLowerCase();
                const id = String(user.id || '');
                return name.includes(searchTerm) || email.includes(searchTerm) || id.includes(searchTerm);
            });
            this.renderRevenueHistoryModalUsers(filteredUsers);
        },

        // ============================================
        // RENDER REVENUE HISTORY MODAL USERS
        // ============================================
        renderRevenueHistoryModalUsers: function(users) {
            const container = document.getElementById('revenue-history-modal-list');
            
            if (users.length === 0) {
                container.innerHTML = '<div style="text-align:center;padding:40px;color:#888;">No users found</div>';
                return;
            }
            
            let html = '';
            users.forEach(user => {
                let history = user.revenue_history || [];
                if (typeof history === 'string') {
                    try {
                        history = JSON.parse(history);
                    } catch (e) {
                        history = [];
                    }
                }
                if (!Array.isArray(history)) {
                    history = [];
                }
                const recordCount = history.length;
                
                const isSelected = this.selectedRevenueHistoryUser && 
                    this.selectedRevenueHistoryUser.id === user.id && 
                    this.selectedRevenueHistoryUser.source === user.source;
                html += `
                    <div class="modal-user-item ${isSelected ? 'selected' : ''}" 
                        onclick="Revenue.selectRevenueHistoryUserFromModal(${user.id}, '${user.source}')">
                        <div class="modal-user-name">${this.escapeHtml(user.fullname || 'N/A')}</div>
                        <div class="modal-user-email">${this.escapeHtml(user.email || 'N/A')}</div>
                        <div class="modal-user-id">ID: ${user.id}</div>
                        ${recordCount > 0 ? 
                            `<div style="font-size:10px;color:#888;margin-top:4px;">${recordCount} records</div>` : 
                            `<div style="font-size:10px;color:#888;margin-top:4px;">No history records</div>`
                        }
                    </div>
                `;
            });
            
            container.innerHTML = html;
        },

        // ============================================
        // SELECT REVENUE HISTORY USER FROM MODAL
        // ============================================
        selectRevenueHistoryUserFromModal: function(userId, source) {
            const user = this.allRevenueHistoryUsers.find(u => u.id == userId && u.source === source);
            if (!user) return;
            
            if (user.revenue_history && typeof user.revenue_history === 'string') {
                try {
                    user.revenue_history = JSON.parse(user.revenue_history);
                } catch (e) {
                    user.revenue_history = [];
                }
            }
            if (!Array.isArray(user.revenue_history)) {
                user.revenue_history = [];
            }
            
            this.selectedRevenueHistoryUser = user;
            this.closeRevenueHistoryUsersModal();
            
            // Show user details view
            this.showUserDetails();
            
            // Update search placeholder
            const placeholder = document.getElementById('revenue-history-search-placeholder');
            if (placeholder) {
                placeholder.textContent = this.escapeHtml(user.fullname || 'N/A') + ' (ID: ' + user.id + ')';
            }
            
            document.getElementById('revenue-history-user-name').textContent = 
                this.escapeHtml(user.fullname || 'N/A') + ' - Revenue History';
            
            // Reset sub-tab to 'all'
            this.currentRevenueHistorySubTab = 'all';
            document.querySelectorAll('#revenue-history-sub-tabs .sub-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.revenueSubtab === 'all');
            });
            
            this.renderUserRevenueHistorySummary(user);
        },

        clearRevenueHistoryUserSelection: function() {
            this.selectedRevenueHistoryUser = null;
            this.selectedUserRevenueHistory = [];
            
            // Show overview (users list)
            this.showOverview();
            
            // Update search placeholder
            const placeholder = document.getElementById('revenue-history-search-placeholder');
            if (placeholder) {
                placeholder.textContent = 'Search users by name, email, or ID...';
            }
        },

        // ============================================
        // LOAD UNUSUAL USERS
        // ============================================
        loadUnusualUsers: function() {
            const searchTerm = this.activeSearchTerm || '';
            
            fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=get_unusual_users&search=${encodeURIComponent(searchTerm)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.unusualUsers = data.users || [];
                    this.filteredUnusualUsers = this.getFilteredUnusualUsers();
                    this.filteredActiveUsers = this.filteredUnusualUsers;
                    this.renderActiveUsers();
                    this.updateActiveCubes();
                    this.updateUnusualBadges();
                } else {
                    this.unusualUsers = [];
                    this.filteredUnusualUsers = [];
                    this.filteredActiveUsers = [];
                    this.renderActiveUsers();
                }
            })
            .catch(error => {
                console.error('Error loading unusual users:', error);
                this.unusualUsers = [];
                this.filteredUnusualUsers = [];
                this.filteredActiveUsers = [];
                this.renderActiveUsers();
            });
        },

        // ============================================
        // GET FILTERED UNUSUAL USERS
        // ============================================
        getFilteredUnusualUsers: function() {
            let users = [...this.unusualUsers];
            const subTab = this.currentUnusualSubTab;

            switch(subTab) {
                case 'all':
                    break;
                case 'withdrawals':
                    users = users.filter(u => (u.withdrawal_count || 0) > 0);
                    break;
                case 'trades':
                    users = users.filter(u => (u.unauthorized_trade_count || 0) > 0);
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

        // ============================================
        // FETCH USER DATA
        // ============================================
        fetchUserData: function(userId, sourceTable) {
            return fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=get_user_daily_log&user_id=${userId}&source_table=${sourceTable}`
            })
            .then(response => response.json());
        },

        fetchUserBasicData: function(userId, sourceTable) {
            return fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=get_user_data&user_id=${userId}&source_table=${sourceTable}`
            })
            .then(response => response.json());
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
                case 'unusual':
                    return this.filteredUnusualUsers;
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

            if (this.activeSearchTerm && subTab !== 'unusual') {
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
            
            if (this.currentActiveSubTab === 'unusual') {
                this.filteredUnusualUsers = this.getFilteredUnusualUsers();
                this.filteredActiveUsers = this.filteredUnusualUsers;
                this.renderActiveUsers();
                this.updateActiveCubes();
            } else {
                this.filteredActiveUsers = this.getFilteredActiveUsers();
                this.renderActiveUsers();
                this.updateActiveCubes();
            }
        },

        clearActiveSearch: function() {
            document.getElementById('active-search-input').value = '';
            document.getElementById('active-search-clear').style.display = 'none';
            this.activeSearchTerm = '';
            this.deactivateSearch('active');
            
            if (this.currentActiveSubTab === 'unusual') {
                this.filteredUnusualUsers = this.getFilteredUnusualUsers();
                this.filteredActiveUsers = this.filteredUnusualUsers;
                this.renderActiveUsers();
                this.updateActiveCubes();
            } else {
                this.filteredActiveUsers = this.getFilteredActiveUsers();
                this.renderActiveUsers();
                this.updateActiveCubes();
            }
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
            this.deactivateSearch('completed');
            this.filteredCompletedUsers = this.getFilteredCompletedUsers();
            this.renderCompletedUsers();
        },

        // ============================================
        // RENDER ACTIVE USERS
        // ============================================
        renderActiveUsers: function() {
            const tbody = document.getElementById('active-users-body');
            const users = this.filteredActiveUsers;
            const isUnusualTab = this.currentActiveSubTab === 'unusual';

            if (!users || users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#888;">' + 
                    (isUnusualTab ? 'No unusual activity found' : 'No active users found') + 
                    '</td></tr>';
                return;
            }

            let html = '';
            users.forEach(user => {
                const brokerBalance = parseFloat(user.broker_balance) || 0;
                const profitAndLoss = parseFloat(user.profitandloss) || 0;
                const currentBalance = brokerBalance + profitAndLoss;
                const profitClass = profitAndLoss >= 0 ? 'profit' : 'loss';
                const balanceClass = currentBalance >= 0 ? 'profit' : 'loss';
                
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

                let actionHtml = '';
                let clickableClass = 'clickable-row';
                let dataAttrs = `data-user-id="${user.id}" data-source="${user.source || 'insiders'}"`;
                
                if (isUnusualTab) {
                    const withdrawalCount = user.withdrawal_count || 0;
                    const tradeCount = user.unauthorized_trade_count || 0;
                    
                    status = `Unusual (W:${withdrawalCount}, T:${tradeCount})`;
                    statusClass = 'status-unusual';
                    
                    actionHtml = `
                        <select class="action-select" data-user-id="${user.id}" data-source="${user.source || 'insiders'}" onchange="Revenue.handleUnusualAction(this)">
                            <option value="">Remain Active</option>
                            <option value="cancel-contract">Cancel Contract</option>
                        </select>
                    `;
                } else {
                    actionHtml = `
                        <select class="action-select" data-user-id="${user.id}" data-source="${user.source || 'insiders'}" onchange="Revenue.handleActiveAction(this)">
                            <option value="">Remain Active</option>
                            <option value="cancel-contract">Cancel Contract</option>
                        </select>
                    `;
                }

                html += `
                    <tr class="${clickableClass}" ${dataAttrs}>
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
                        <td>${actionHtml}</td>
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
            const subTab = this.currentCompletedSubTab;

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

                let actionHtml = '';
                switch(subTab) {
                    case 'unpaid':
                        actionHtml = `
                            <select class="status-select" data-user-id="${user.id}" data-source="${user.source}" onchange="Revenue.updateUserStatus(this)">
                                <option value="">Observe</option>
                                <option value="suspend">Suspend</option>
                            </select>
                        `;
                        break;
                    case 'payment-made':
                        actionHtml = `
                            <select class="status-select" data-user-id="${user.id}" data-source="${user.source}" onchange="Revenue.updateUserStatus(this)">
                                <option value="">Select Status</option>
                                <option value="payment-confirmed" ${currentStatus === 'payment-confirmed' || currentStatus === 'payment_confirmed' ? 'selected' : ''}>Payment Confirmed</option>
                                <option value="failed-payment" ${currentStatus === 'failed-payment' || currentStatus === 'failed_payment' || currentStatus === 'payment-failed' || currentStatus === 'payment_failed' ? 'selected' : ''}>Payment Failed</option>
                            </select>
                        `;
                        break;
                    case 'payment-confirmed':
                        actionHtml = `
                            <select class="status-select" data-user-id="${user.id}" data-source="${user.source}" onchange="Revenue.updateUserStatus(this)">
                                <option value="">Select Status</option>
                                <option value="payment-made" ${currentStatus === 'payment-made' || currentStatus === 'payment_made' ? 'selected' : ''}>Payment Made</option>
                                <option value="failed-payment" ${currentStatus === 'failed-payment' || currentStatus === 'failed_payment' || currentStatus === 'payment-failed' || currentStatus === 'payment_failed' ? 'selected' : ''}>Payment Failed</option>
                                <option value="unpaid-payment" ${currentStatus === 'unpaid-payment' || currentStatus === 'unpaid_payment' || currentStatus === 'unpaid' ? 'selected' : ''}>Unpaid</option>
                                <option value="suspend">Suspend</option>
                            </select>
                        `;
                        break;
                    case 'failed':
                        actionHtml = `
                            <select class="status-select" data-user-id="${user.id}" data-source="${user.source}" onchange="Revenue.updateUserStatus(this)">
                                <option value="">Select Status</option>
                                <option value="payment-confirmed" ${currentStatus === 'payment-confirmed' || currentStatus === 'payment_confirmed' ? 'selected' : ''}>Payment Confirmed</option>
                                <option value="payment-made" ${currentStatus === 'payment-made' || currentStatus === 'payment_made' ? 'selected' : ''}>Payment Made</option>
                            </select>
                        `;
                        break;
                    default:
                        actionHtml = `
                            <select class="status-select" data-user-id="${user.id}" data-source="${user.source}" onchange="Revenue.updateUserStatus(this)">
                                <option value="">Select Status</option>
                                <option value="unpaid-payment" ${currentStatus === 'unpaid-payment' || currentStatus === 'unpaid_payment' || currentStatus === 'unpaid' ? 'selected' : ''}>Unpaid</option>
                                <option value="payment-made" ${currentStatus === 'payment-made' || currentStatus === 'payment_made' ? 'selected' : ''}>Payment Made</option>
                                <option value="payment-confirmed" ${currentStatus === 'payment-confirmed' || currentStatus === 'payment_confirmed' ? 'selected' : ''}>Payment Confirmed</option>
                                <option value="failed-payment" ${currentStatus === 'failed-payment' || currentStatus === 'failed_payment' || currentStatus === 'payment-failed' || currentStatus === 'payment_failed' ? 'selected' : ''}>Failed</option>
                                <option value="suspend">Suspend</option>
                            </select>
                        `;
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
                        <td>${actionHtml}</td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        },

        // ============================================
        // HANDLE ACTIVE ACTION
        // ============================================
        handleActiveAction: function(selectElement) {
            const userId = selectElement.dataset.userId;
            const source = selectElement.dataset.source;
            const action = selectElement.value;
            
            if (!action) return;
            
            if (action === 'cancel-contract') {
                this.cancelContract(userId, source);
            }
            
            selectElement.value = '';
        },

        // ============================================
        // HANDLE UNUSUAL ACTION
        // ============================================
        handleUnusualAction: function(selectElement) {
            const userId = selectElement.dataset.userId;
            const source = selectElement.dataset.source;
            const action = selectElement.value;
            
            if (!action) return;
            
            if (action === 'cancel-contract') {
                this.cancelContract(userId, source);
            }
            
            selectElement.value = '';
        },

        cancelContract: function(userId, source) {
            if (!confirm(`Are you sure you want to cancel the contract for User ID ${userId}?`)) {
                return;
            }
            
            this.showPasswordModal('Cancel Contract', `Enter admin password to cancel contract for User ID ${userId}`, function(password) {
                const loginId = document.getElementById('login-id-hidden')?.value || '';
                
                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=cancel_contract&user_id=${userId}&source_table=${source}&admin_password=${encodeURIComponent(password)}&login_id=${encodeURIComponent(loginId)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Contract cancelled successfully!');
                        Revenue.loadActiveUsers();
                        Revenue.loadCompletedUsers();
                        Revenue.loadRevenueHistoryUsers();
                    } else {
                        alert('Error: ' + (data.error || data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            });
        },

        suspendUser: function(userId, source) {
            if (!confirm(`Are you sure you want to suspend User ID ${userId}?`)) {
                return;
            }
            
            this.showPasswordModal('Suspend User', `Enter admin password to suspend User ID ${userId}`, function(password) {
                const loginId = document.getElementById('login-id-hidden')?.value || '';
                
                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=update_application_status_batch&user_id=${userId}&source_table=${source}&application_status=suspended&admin_password=${encodeURIComponent(password)}&login_id=${encodeURIComponent(loginId)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('User suspended successfully!');
                        Revenue.loadCompletedUsers();
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            });
        },

        // ============================================
        // VIEW USER DETAIL - Full Screen Overlay
        // ============================================
        viewUserDetail: function(userId, source) {
            this.selectedUserId = userId;
            this.selectedUserSource = source || 'insiders';
            this.isDetailViewOpen = true;
            
            const overlay = document.getElementById('user-detail-overlay');
            overlay.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            document.getElementById('detail-user-name').textContent = `User Details - ID: ${userId}`;
            document.getElementById('detail-overlay-body').innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Loading user details...</p>
                </div>
            `;
            
            this.fetchUserDetails(userId, source);
        },

        fetchUserDetails: function(userId, source) {
            Promise.all([
                this.fetchUserData(userId, source),
                this.fetchUserBasicData(userId, source)
            ])
            .then(([detailData, basicData]) => {
                this.renderUserDetail(detailData, basicData);
            })
            .catch(error => {
                console.error('Error fetching user details:', error);
                document.getElementById('detail-overlay-body').innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">✕</div>
                        <div class="empty-text">Error loading user details</div>
                        <div class="empty-sub">${error.message || 'Please try again'}</div>
                    </div>
                `;
            });
        },

        renderUserDetail: function(detailData, basicData) {
            const container = document.getElementById('detail-overlay-body');
            
            const user = basicData.user || {};
            const dailyLog = detailData.log || {};
            const dailyTarget = detailData.daily_target || {};
            
            let dailyTargetData = {};
            if (dailyTarget) {
                if (typeof dailyTarget === 'string') {
                    try {
                        const parsed = JSON.parse(dailyTarget);
                        if (parsed && typeof parsed === 'object') {
                            if (parsed.daily_target_met) {
                                dailyTargetData = parsed.daily_target_met;
                            } else {
                                dailyTargetData = parsed;
                            }
                        }
                    } catch(e) {
                        try {
                            const lines = dailyTarget.split(',');
                            const obj = {};
                            lines.forEach(line => {
                                const parts = line.trim().split(':');
                                if (parts.length === 2) {
                                    const key = parts[0].trim().replace(/["']/g, '');
                                    const value = parts[1].trim().replace(/["']/g, '');
                                    obj[key] = value;
                                }
                            });
                            if (Object.keys(obj).length > 0) {
                                dailyTargetData = obj;
                            }
                        } catch(e2) {
                            dailyTargetData = {};
                        }
                    }
                } else if (typeof dailyTarget === 'object') {
                    if (dailyTarget.daily_target_met) {
                        dailyTargetData = dailyTarget.daily_target_met;
                    } else {
                        dailyTargetData = dailyTarget;
                    }
                }
            }
            
            if (typeof dailyTargetData !== 'object' || Array.isArray(dailyTargetData)) {
                dailyTargetData = {};
            }
            
            let dailyLogData = {};
            if (dailyLog) {
                if (typeof dailyLog === 'string') {
                    try {
                        const parsed = JSON.parse(dailyLog);
                        if (parsed && typeof parsed === 'object') {
                            dailyLogData = parsed;
                        }
                    } catch(e) {
                        dailyLogData = {};
                    }
                } else if (typeof dailyLog === 'object') {
                    dailyLogData = dailyLog;
                }
            }
            
            const brokerBalance = parseFloat(user.broker_balance) || 0;
            const profitAndLoss = parseFloat(user.profitandloss) || 0;
            const currentBalance = brokerBalance + profitAndLoss;
            const isAboveThreshold = profitAndLoss > this.minProfitForSplit;
            const isInProfit = profitAndLoss > 0;
            
            const targetDates = Object.keys(dailyTargetData).sort((a, b) => {
                const dateA = new Date(a);
                const dateB = new Date(b);
                return dateB - dateA;
            });
            
            const logDates = Object.keys(dailyLogData).sort((a, b) => {
                const partsA = a.split('-');
                const partsB = b.split('-');
                if (partsA.length === 3 && partsB.length === 3) {
                    const dateA = new Date(partsA[2], partsA[1] - 1, partsA[0]);
                    const dateB = new Date(partsB[2], partsB[1] - 1, partsB[0]);
                    return dateB - dateA;
                }
                return b.localeCompare(a);
            });
            
            let html = `
                <div class="user-detail-grid">
                    <div class="detail-card-full">
                        <div class="detail-user-header">
                            <div>
                                <h3>${this.escapeHtml(user.fullname || 'N/A')}</h3>
                                <p class="detail-user-email">${this.escapeHtml(user.email || 'N/A')}</p>
                                <p class="detail-user-id">ID: ${user.id} | Source: ${this.escapeHtml(user.source || 'N/A')}</p>
                            </div>
                            <div class="detail-user-status">
                                <span class="status-badge ${isAboveThreshold ? 'status-above' : isInProfit ? 'status-profit' : 'status-loss'}">
                                    ${isAboveThreshold ? 'Above Threshold' : isInProfit ? 'In Profit' : 'In Loss'}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-stats-grid">
                        <div class="detail-stat-card">
                            <div class="stat-label">Broker Balance</div>
                            <div class="stat-value">$${this.formatNumber(brokerBalance)}</div>
                        </div>
                        <div class="detail-stat-card">
                            <div class="stat-label">P&L</div>
                            <div class="stat-value ${profitAndLoss >= 0 ? 'profit' : 'loss'}">$${this.formatNumber(profitAndLoss)}</div>
                        </div>
                        <div class="detail-stat-card">
                            <div class="stat-label">Current Balance</div>
                            <div class="stat-value ${currentBalance >= 0 ? 'profit' : 'loss'}">$${this.formatNumber(currentBalance)}</div>
                        </div>
                        <div class="detail-stat-card">
                            <div class="stat-label">Above Threshold</div>
                            <div class="stat-value">${isAboveThreshold ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="detail-stat-card">
                            <div class="stat-label">In Profit</div>
                            <div class="stat-value">${isInProfit ? 'Yes' : 'No'}</div>
                        </div>
                        <div class="detail-stat-card">
                            <div class="stat-label">Unusual Activity Days</div>
                            <div class="stat-value ${Object.values(dailyLogData).filter(d => d.unusual_activity).length > 0 ? 'unusual' : ''}">
                                ${Object.values(dailyLogData).filter(d => d.unusual_activity).length}
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-tabs-wrapper">
                        <div class="detail-tabs">
                            <button class="detail-tab-btn active" data-detail-tab="daily-target" onclick="Revenue.switchDetailTab('daily-target')">
                                Daily Target
                                <span class="tab-badge">${targetDates.length}</span>
                            </button>
                            <button class="detail-tab-btn" data-detail-tab="balance-log" onclick="Revenue.switchDetailTab('balance-log')">
                                Balance Log
                                <span class="tab-badge">${logDates.length}</span>
                            </button>
                        </div>
                    </div>
                    
                    <div id="detail-tab-daily-target" class="detail-tab-content active">
            `;
            
            if (targetDates.length > 0) {
                html += `
                    <div class="detail-section">
                        <div class="section-content">
                            <div class="daily-target-list">
                `;
                
                targetDates.forEach(date => {
                    const targetValue = dailyTargetData[date];
                    const dateObj = new Date(date + 'T00:00:00');
                    const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long' });
                    const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
                    
                    const parts = date.split('-');
                    const logDateKey = `${parts[2]}-${parts[1]}-${parts[0]}`;
                    const isUnusual = dailyLogData[logDateKey] && dailyLogData[logDateKey].unusual_activity;
                    
                    html += `
                        <div class="daily-target-item ${isUnusual ? 'unusual' : ''}">
                            <div class="target-left">
                                <div class="target-day">${dayName}</div>
                                <div class="target-date">${formattedDate}</div>
                                ${isUnusual ? '<span class="status-badge status-unusual">Unusual</span>' : ''}
                            </div>
                            <div class="target-value">${this.escapeHtml(targetValue)}</div>
                        </div>
                    `;
                });
                
                html += `
                            </div>
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="empty-state">
                        <div class="empty-icon">-</div>
                        <div class="empty-text">No Daily Target Data</div>
                        <div class="empty-sub">This user has no daily target records.</div>
                    </div>
                `;
            }
            
            html += `
                    </div>
                    
                    <div id="detail-tab-balance-log" class="detail-tab-content" style="display:none;">
            `;
            
            if (logDates.length > 0) {
                html += `
                    <div class="detail-section">
                        <div class="section-content">
                            <div class="balance-log-list">
                `;
                
                logDates.forEach(date => {
                    const dayData = dailyLogData[date];
                    if (!dayData) return;
                    
                    const parts = date.split('-');
                    let dateObj;
                    if (parts.length === 3) {
                        dateObj = new Date(parts[2], parts[1] - 1, parts[0]);
                    } else {
                        dateObj = new Date(date);
                    }
                    const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long' });
                    const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
                    
                    const isUnusual = dayData.unusual_activity || false;
                    const hasWithdrawals = parseFloat(dayData.day_unauthorized_withdrawals) > 0;
                    const hasUnauthorizedTrades = (dayData.unauthorized_trades_count || 0) > 0;
                    
                    let unusualBadge = '';
                    if (isUnusual) {
                        let badges = [];
                        if (hasWithdrawals) badges.push('Withdrawal');
                        if (hasUnauthorizedTrades) badges.push('Trades');
                        unusualBadge = `<span class="status-badge status-unusual">${badges.join(' + ')}</span>`;
                    }
                    
                    html += `
                        <div class="balance-log-item ${isUnusual ? 'unusual' : ''}">
                            <div class="log-header" onclick="Revenue.toggleBalanceLogDetails(this.parentElement)">
                                <div class="log-left">
                                    <div class="log-day">${dayName}</div>
                                    <div class="log-date">${formattedDate}</div>
                                    ${unusualBadge}
                                </div>
                                <div class="log-toggle">▼</div>
                            </div>
                            <div class="log-details">
                                <div class="log-row">
                                    <span class="log-label">Open Balance</span>
                                    <span class="log-value">$${this.formatNumber(dayData.day_starting_balance)}</span>
                                </div>
                                <div class="log-row">
                                    <span class="log-label">Authorized Trades P&L</span>
                                    <span class="log-value ${parseFloat(dayData.day_authorized_trades_pnl) >= 0 ? 'profit' : 'loss'}">
                                        $${this.formatNumber(dayData.day_authorized_trades_pnl)}
                                    </span>
                                </div>
                                <div class="log-row">
                                    <span class="log-label">Unauthorized Trades P&L</span>
                                    <span class="log-value ${parseFloat(dayData.day_unauthorized_trades_pnl) >= 0 ? 'profit' : 'loss'}">
                                        $${this.formatNumber(dayData.day_unauthorized_trades_pnl)}
                                    </span>
                                </div>
                                <div class="log-row">
                                    <span class="log-label">Unauthorized Withdrawals</span>
                                    <span class="log-value ${parseFloat(dayData.day_unauthorized_withdrawals) > 0 ? 'loss' : ''}">
                                        $${this.formatNumber(dayData.day_unauthorized_withdrawals)}
                                    </span>
                                </div>
                                <div class="log-row">
                                    <span class="log-label">Closing Balance</span>
                                    <span class="log-value">$${this.formatNumber(dayData.day_closing_balance)}</span>
                                </div>
                                <div class="log-row">
                                    <span class="log-label">Unusual Activity</span>
                                    <span class="log-value ${isUnusual ? 'unusual' : ''}">${isUnusual ? 'Yes' : 'No'}</span>
                                </div>
                                ${hasUnauthorizedTrades && dayData.day_unauthorized_trades ? `
                                    <div class="unauthorized-trades-section">
                                        <div class="log-label">Unauthorized Trades</div>
                                        ${dayData.day_unauthorized_trades.map(trade => `
                                            <div class="trade-row-detail">
                                                <span class="trade-symbol">${this.escapeHtml(trade.symbol || 'N/A')}</span>
                                                <span class="trade-pnl ${parseFloat(trade.pnl) < 0 ? 'loss' : 'profit'}">
                                                    $${this.formatNumber(trade.pnl)}
                                                </span>
                                                <span class="trade-meta">Ticket: ${trade.ticket || 'N/A'}</span>
                                            </div>
                                        `).join('')}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                });
                
                html += `
                            </div>
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="empty-state">
                        <div class="empty-icon">-</div>
                        <div class="empty-text">No Balance Log Data</div>
                        <div class="empty-sub">This user has no balance log records.</div>
                    </div>
                `;
            }
            
            html += `
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
        },

        switchDetailTab: function(tabId) {
            document.querySelectorAll('.detail-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.detailTab === tabId);
            });
            
            document.querySelectorAll('.detail-tab-content').forEach(content => {
                const isActive = content.id === 'detail-tab-' + tabId;
                content.classList.toggle('active', isActive);
                content.style.display = isActive ? 'block' : 'none';
            });
        },

        toggleBalanceLogDetails: function(element) {
            const details = element.querySelector('.log-details');
            const toggle = element.querySelector('.log-toggle');
            if (details) {
                if (details.style.display === 'none' || details.style.display === '') {
                    details.style.display = 'block';
                    if (toggle) toggle.textContent = '▲';
                } else {
                    details.style.display = 'none';
                    if (toggle) toggle.textContent = '▼';
                }
            }
        },

        closeUserDetail: function() {
            document.getElementById('user-detail-overlay').style.display = 'none';
            document.body.style.overflow = '';
            this.isDetailViewOpen = false;
            this.selectedUserId = null;
            this.selectedUserSource = null;
        },

        // ============================================
        // PASSWORD MODAL
        // ============================================
        showPasswordModal: function(title, message, callback) {
            let modal = document.getElementById('password-modal');
            if (!modal) {
                modal = this.createPasswordModal();
            }
            
            document.getElementById('modal-title').textContent = title;
            document.getElementById('modal-paragraph').textContent = message;
            document.getElementById('modal-password-input').value = '';
            
            modal._callback = callback;
            modal.style.display = 'flex';
            document.getElementById('modal-password-input').focus();
        },

        createPasswordModal: function() {
            const modalHtml = `
                <div id="password-modal" class="modal-overlay" style="display:none;">
                    <div class="modal-container modal-small">
                        <div class="modal-header">
                            <h3 id="modal-title">Security Check</h3>
                            <span class="modal-close" onclick="Revenue.closePasswordModal()">✕</span>
                        </div>
                        <div class="modal-body">
                            <p id="modal-paragraph">Please enter your admin password.</p>
                            <input type="password" id="modal-password-input" placeholder="Enter password" style="width:100%;padding:10px;border-radius:6px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-color);font-size:14px;">
                            <div style="margin-top:16px;display:flex;gap:10px;justify-content:flex-end;">
                                <button onclick="Revenue.closePasswordModal()" style="padding:8px 20px;border-radius:6px;border:1px solid var(--border-color);background:transparent;color:var(--text-color);cursor:pointer;">Cancel</button>
                                <button onclick="Revenue.confirmPasswordModal()" style="padding:8px 20px;border-radius:6px;border:none;background:var(--accent-color);color:white;cursor:pointer;">Confirm</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            const container = document.getElementById('revenue-container');
            container.insertAdjacentHTML('afterend', modalHtml);
            
            modal = document.getElementById('password-modal');
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    Revenue.closePasswordModal();
                }
            });
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    Revenue.closePasswordModal();
                }
                if (e.key === 'Enter' && document.getElementById('password-modal').style.display === 'flex') {
                    Revenue.confirmPasswordModal();
                }
            });
            
            return modal;
        },

        closePasswordModal: function() {
            const modal = document.getElementById('password-modal');
            if (modal) {
                modal.style.display = 'none';
                modal._callback = null;
            }
        },

        confirmPasswordModal: function() {
            const modal = document.getElementById('password-modal');
            const password = document.getElementById('modal-password-input').value;
            
            if (!password) {
                alert('Please enter your password.');
                return;
            }
            
            if (modal._callback) {
                modal._callback(password);
            }
            
            this.closePasswordModal();
        },

        // ============================================
        // UPDATE USER STATUS
        // ============================================
        updateUserStatus: function(selectElement) {
            const userId = selectElement.dataset.userId;
            const source = selectElement.dataset.source;
            const newStatus = selectElement.value;
            
            if (!newStatus) return;
            
            if (newStatus === 'suspend') {
                this.suspendUser(userId, source);
                selectElement.value = '';
                return;
            }
            
            if (!confirm(`Update status to "${newStatus}" for User ID ${userId}?`)) {
                selectElement.value = selectElement.dataset.previousValue || '';
                return;
            }
            
            selectElement.disabled = true;
            selectElement.style.opacity = '0.6';
            
            this.showPasswordModal('Update Status', `Enter admin password to update status for User ID ${userId}`, function(password) {
                const loginId = document.getElementById('login-id-hidden')?.value || '';
                
                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=update_payment_status&user_id=${userId}&source_table=${source}&payment_status=${encodeURIComponent(newStatus)}&admin_password=${encodeURIComponent(password)}&login_id=${encodeURIComponent(loginId)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Status updated successfully!');
                        Revenue.loadCompletedUsers();
                        Revenue.loadRevenueHistoryUsers();
                    } else {
                        alert('Error: ' + (data.error || data.message || 'Unknown error'));
                        selectElement.disabled = false;
                        selectElement.style.opacity = '1';
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                    selectElement.disabled = false;
                    selectElement.style.opacity = '1';
                });
            });
        },

        // ============================================
        // SWITCH UNUSUAL SUB TAB
        // ============================================
        switchUnusualSubTab: function(subTab) {
            this.currentUnusualSubTab = subTab;
            
            document.querySelectorAll('#unusual-sub-tabs .sub-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.unusualSubtab === subTab);
            });
            
            this.filteredUnusualUsers = this.getFilteredUnusualUsers();
            this.filteredActiveUsers = this.filteredUnusualUsers;
            this.renderActiveUsers();
            this.updateActiveCubes();
            this.updateUnusualBadges();
        },

        // ============================================
        // UPDATE UNUSUAL BADGES
        // ============================================
        updateUnusualBadges: function() {
            const allCount = this.unusualUsers.length;
            const withdrawalCount = this.unusualUsers.filter(u => (u.withdrawal_count || 0) > 0).length;
            const tradeCount = this.unusualUsers.filter(u => (u.unauthorized_trade_count || 0) > 0).length;
            
            this.updateBadge('unusual-all-count', allCount);
            this.updateBadge('unusual-withdrawals-count', withdrawalCount);
            this.updateBadge('unusual-trades-count', tradeCount);
            this.updateBadge('active-unusual-count', allCount);
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
            let totalInProfit = 0;

            users.forEach(user => {
                const brokerBalance = parseFloat(user.broker_balance) || 0;
                const pnl = parseFloat(user.profitandloss) || 0;
                totalInvestment += brokerBalance;
                totalPnl += pnl;
                totalBalance += brokerBalance + pnl;

                if (pnl > 0) {
                    totalInProfit++;
                    const serverShare = (pnl * this.serverSharePercent) / 100;
                    const userShare = (pnl * this.userSharePercent) / 100;
                    totalServerShare += serverShare;
                    totalUserShare += userShare;
                }
            });

            this.updateCubeValue('active-total-investment', totalInvestment);
            this.updateCubeValue('active-total-pnl', totalPnl);
            this.updateCubeValue('active-current-balance', totalBalance);
            this.updateCubeValue('active-user-share', totalUserShare);
            this.updateCubeValue('active-server-share', totalServerShare);
            
            const profitEl = document.getElementById('active-investors-profit');
            if (profitEl) {
                profitEl.textContent = totalInProfit + '/' + users.length;
                if (totalInProfit > 0) {
                    profitEl.style.color = '#4caf50';
                } else {
                    profitEl.style.color = 'var(--text-color, #ffffff)';
                }
            }
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
                unusual: 0,
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

            this.updateBadge('active-unusual-count', this.unusualUsers.length || 0);

            Object.keys(counts).forEach(key => {
                if (key !== 'unusual') {
                    this.updateBadge('active-' + key + '-count', counts[key]);
                }
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
            if (s.includes('cancelled')) return 'Cancelled';
            if (s === 'loss_completed') return 'Loss Completed';
            if (s === 'below_threshold') return 'Below Threshold';
            return status || 'Unknown';
        },

        getStatusClass: function(status) {
            const s = (status || '').toLowerCase();
            if (s === 'payment-confirmed' || s === 'payment_confirmed') return 'status-confirmed';
            if (s === 'payment-made' || s === 'payment_made') return 'status-made';
            if (s === 'unpaid-payment' || s === 'unpaid_payment' || s === 'unpaid') return 'status-unpaid';
            if (s === 'failed-payment' || s === 'failed_payment' || s === 'payment-failed' || s === 'payment_failed') return 'status-failed';
            if (s.includes('cancelled')) return 'status-cancelled';
            if (s === 'loss_completed') return 'status-loss';
            if (s === 'below_threshold') return 'status-below';
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
            
            if (tab === 'completed') {
                this.filteredCompletedUsers = this.getFilteredCompletedUsers();
                this.renderCompletedUsers();
            } else if (tab === 'revenue-history') {
                this.loadRevenueHistoryUsers();
                this.clearRevenueHistoryUserSelection();
            }
        },

        switchActiveSubTab: function(subTab) {
            this.currentActiveSubTab = subTab;

            document.querySelectorAll('#active-sub-tabs .sub-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.subtab === subTab);
            });

            const unusualSubTabs = document.getElementById('unusual-sub-sub-tabs');
            if (subTab === 'unusual') {
                unusualSubTabs.style.display = 'block';
                this.loadUnusualUsers();
            } else {
                unusualSubTabs.style.display = 'none';
                this.filteredActiveUsers = this.getFilteredActiveUsers();
                this.renderActiveUsers();
                this.updateActiveCubes();
            }
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
            if (!dateStr || dateStr === '0000-00-00') return 'N/A';
            try {
                const d = new Date(dateStr + 'T00:00:00');
                if (isNaN(d.getTime())) return dateStr;
                return d.toLocaleDateString('en-US', {
                    month: 'short',
                    day: '2-digit',
                    year: 'numeric'
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

    document.addEventListener('DOMContentLoaded', () => {
        Revenue.init();
    });
</script>