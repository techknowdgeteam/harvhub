<?php
// revenue.php - Revenue Dashboard with Tab Navigation
// This file is included in serveraccount.php when view=paid_users
// UPDATED: Uses revenue_history table instead of column
// UPDATED: Understands contract-cancelled-* combined statuses
// UPDATED: Fetches programme_investors for developer/investor percentages and contract duration
// UPDATED: Daily Target + Balance Log now read from `daily_target_revenue` and `balance_log` tables
?>

<div class="revenue-container" id="revenue-container">
    <!-- Header -->
    <div class="revenue-header">
        <h2>Revenue Dashboard</h2>
    </div>

    <!-- Main Tabs: Active | Completed | Inactive | Revenue History -->
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
            <button class="tab-btn" data-tab="inactive" onclick="Revenue.switchTab('inactive')">
                Inactive
                <span class="tab-badge" id="inactive-count">0</span>
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

        <div class="search-bar-wrapper">
            <div class="search-bar search-bar-dummy" id="active-search-dummy" onclick="Revenue.activateSearch('active')">
                <span class="search-icon">Q</span>
                <span class="search-placeholder">Search active users by name, email, or ID...</span>
            </div>
            <div class="search-bar search-bar-real" id="active-search-real" style="display:none;">
                <span class="search-icon">Q</span>
                <input type="text" id="active-search-input" class="search-input" placeholder="Search active users by name, email, or ID..." oninput="Revenue.filterActiveTable()" autocomplete="off">
                <span class="search-clear" id="active-search-clear" onclick="Revenue.clearActiveSearch()" style="display:none;">x</span>
            </div>
        </div>

        <div class="users-table-container">
            <div class="table-wrapper">
                <table class="revenue-table" id="active-users-table">
                    <thead>
                        <tr>
                            <th>Programme Developer</th>
                            <th>Investor</th>
                            <th>Investor Broker</th>
                            <th>Investor Login ID</th>
                            <th>Investor Broker Balance</th>
                            <th>Investor P&L</th>
                            <th>Investor Current Balance</th>
                            <th>Dev %</th>
                            <th>Dev Amount</th>
                            <th>Inv %</th>
                            <th>Inv Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="active-users-body">
                        <tr><td colspan="13" style="text-align:center;padding:40px;color:#888;">Loading active users...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB: COMPLETED                               -->
    <!-- ============================================ -->
    <div id="tab-completed" class="tab-content">
        <div class="revenue-tabs-wrapper sub-tabs-wrapper">
            <div class="revenue-tabs sub-tabs" id="completed-sub-tabs">
                <button class="tab-btn sub-tab-btn active" data-subtab="inactive-above" onclick="Revenue.switchCompletedSubTab('inactive-above')">
                    Inactive (Above)
                    <span class="tab-badge" id="inactive-above-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="inactive-below" onclick="Revenue.switchCompletedSubTab('inactive-below')">
                    Inactive (Below)
                    <span class="tab-badge" id="inactive-below-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="inactive-loss" onclick="Revenue.switchCompletedSubTab('inactive-loss')">
                    Inactive (Loss)
                    <span class="tab-badge" id="inactive-loss-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="unpaid" onclick="Revenue.switchCompletedSubTab('unpaid')">
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

        <div class="search-bar-wrapper">
            <div class="search-bar search-bar-dummy" id="completed-search-dummy" onclick="Revenue.activateSearch('completed')">
                <span class="search-icon">Q</span>
                <span class="search-placeholder">Search completed users by name, email, or ID...</span>
            </div>
            <div class="search-bar search-bar-real" id="completed-search-real" style="display:none;">
                <span class="search-icon">Q</span>
                <input type="text" id="completed-search-input" class="search-input" placeholder="Search completed users by name, email, or ID..." oninput="Revenue.filterCompletedTable()" autocomplete="off">
                <span class="search-clear" id="completed-search-clear" onclick="Revenue.clearCompletedSearch()" style="display:none;">x</span>
            </div>
        </div>

        <div class="users-table-container">
            <div class="table-wrapper">
                <table class="revenue-table" id="completed-users-table">
                    <thead>
                        <tr>
                            <th>Programme Developer</th>
                            <th>Investor</th>
                            <th>Investor Broker</th>
                            <th>Investor Login ID</th>
                            <th>Invested With</th>
                            <th>Profit</th>
                            <th>Dev %</th>
                            <th>Dev Amount</th>
                            <th>Inv %</th>
                            <th>Inv Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="completed-users-body">
                        <tr><td colspan="12" style="text-align:center;padding:40px;color:#888;">Loading completed users...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB: INACTIVE                                -->
    <!-- ============================================ -->
    <div id="tab-inactive" class="tab-content">
        <div class="revenue-tabs-wrapper sub-tabs-wrapper">
            <div class="revenue-tabs sub-tabs" id="inactive-sub-tabs">
                <button class="tab-btn sub-tab-btn active" data-subtab="all" onclick="Revenue.switchInactiveSubTab('all')">
                    All Inactive
                    <span class="tab-badge" id="inactive-all-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="no-contract" onclick="Revenue.switchInactiveSubTab('no-contract')">
                    No Contract
                    <span class="tab-badge" id="inactive-no-contract-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="expired" onclick="Revenue.switchInactiveSubTab('expired')">
                    Contract Expired
                    <span class="tab-badge" id="inactive-expired-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="cancelled" onclick="Revenue.switchInactiveSubTab('cancelled')">
                    Cancelled
                    <span class="tab-badge" id="inactive-cancelled-count">0</span>
                </button>
            </div>
        </div>

        <div class="summary-cubes" id="inactive-summary-cubes">
            <div class="summary-cube">
                <div class="cube-value" id="inactive-total-investment">$0.00</div>
                <div class="cube-label">Total Investment</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="inactive-total-pnl">$0.00</div>
                <div class="cube-label">Total P&L</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="inactive-current-balance">$0.00</div>
                <div class="cube-label">Current Balance</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="inactive-count-total">0</div>
                <div class="cube-label">Total Inactive Users</div>
            </div>
        </div>

        <div class="search-bar-wrapper">
            <div class="search-bar search-bar-dummy" id="inactive-search-dummy" onclick="Revenue.activateSearch('inactive')">
                <span class="search-icon">Q</span>
                <span class="search-placeholder">Search inactive users by name, email, or ID...</span>
            </div>
            <div class="search-bar search-bar-real" id="inactive-search-real" style="display:none;">
                <span class="search-icon">Q</span>
                <input type="text" id="inactive-search-input" class="search-input" placeholder="Search inactive users by name, email, or ID..." oninput="Revenue.filterInactiveTable()" autocomplete="off">
                <span class="search-clear" id="inactive-search-clear" onclick="Revenue.clearInactiveSearch()" style="display:none;">x</span>
            </div>
        </div>

        <div class="users-table-container">
            <div class="table-wrapper">
                <table class="revenue-table" id="inactive-users-table">
                    <thead>
                        <tr>
                            <th>Programme Developer</th>
                            <th>Investor</th>
                            <th>Investor Broker</th>
                            <th>Investor Login ID</th>
                            <th>Investor Broker Balance</th>
                            <th>Investor P&L</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="inactive-users-body">
                        <tr><td colspan="8" style="text-align:center;padding:40px;color:#888;">Loading inactive users...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB: REVENUE HISTORY                         -->
    <!-- ============================================ -->
    <div id="tab-revenue-history" class="tab-content">
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
                <div class="cube-value" id="rev-global-total-cancelled">0</div>
                <div class="cube-label">Total Cancelled Contracts</div>
            </div>
        </div>

        <div class="search-bar-wrapper" style="margin-bottom: 16px;">
            <div class="search-bar search-bar-dummy" id="rev-history-global-search-dummy" onclick="Revenue.activateRevenueHistoryGlobalSearch()">
                <span class="search-icon">Q</span>
                <span class="search-placeholder">Search users by name, email, or ID...</span>
            </div>
            <div class="search-bar search-bar-real" id="rev-history-global-search-real" style="display:none;">
                <span class="search-icon">Q</span>
                <input type="text" id="rev-history-global-search-input" class="search-input" placeholder="Search users by name, email, or ID..." oninput="Revenue.filterRevenueHistoryGlobalUsers()" autocomplete="off">
                <span class="search-clear" id="rev-history-global-search-clear" onclick="Revenue.clearRevenueHistoryGlobalSearch()" style="display:none;">x</span>
            </div>
        </div>

        <div id="revenue-history-overview">
            <div class="users-table-container">
                <div class="table-wrapper">
                    <div id="revenue-history-users-list" style="padding: 0 !important;">
                        <div style="text-align:center;padding:20px;color:#888;">Loading users...</div>
                    </div>
                </div>
            </div>
        </div>

        <div id="revenue-history-user-details" style="display:none; margin-top: 20px;">
            <div class="revenue-history-user-header">
                <h3 id="revenue-history-user-name">User Name</h3>
                <button class="back-to-overview-btn" onclick="Revenue.clearRevenueHistoryUserSelection()"><- Back to Overview</button>
            </div>

            <div class="search-bar-wrapper" id="revenue-history-search-wrapper">
                <div class="search-bar search-bar-dummy" id="rev-history-search-dummy" onclick="Revenue.showRevenueHistoryUsersrevenuemodal()">
                    <span class="search-icon">+</span>
                    <span class="search-placeholder" id="revenue-history-search-placeholder">Search users by name, email, or ID...</span>
                </div>
            </div>

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
                    <div class="cube-label">Total Dev Share</div>
                </div>
                <div class="summary-cube">
                    <div class="cube-value" id="rev-user-total-user-share">$0.00</div>
                    <div class="cube-label">Total Inv Share</div>
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
                    <div class="cube-value" id="rev-user-total-cancelled">0</div>
                    <div class="cube-label">Cancelled Contracts</div>
                </div>
                <div class="summary-cube">
                    <div class="cube-value" id="rev-user-total-failed">$0.00</div>
                    <div class="cube-label">Failed Payments</div>
                </div>
            </div>

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
<!-- INITIALIZE ENROLLMENT revenuemodal           -->
<!-- ============================================ -->
<div id="initialize-enrollment-revenuemodal" class="revenuemodal-overlay" style="display:none;">
    <div class="revenuemodal-container revenuemodal-medium">
        <div class="revenuemodal-header">
            <span>Initialize Enrollment</span>
            <span class="revenuemodal-close" onclick="Revenue.closeInitializeEnrollmentrevenuemodal()">x</span>
        </div>
        <div class="revenuemodal-body">
            <p style="margin-bottom: 16px; color: #888; font-size: 14px;">
                Enter the broker balance to initialize enrollment for <strong id="init-enroll-user-name">User</strong>.
                This will set the contract start date to today and reset relevant fields.
            </p>

            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 4px; font-weight: 500;">Broker Balance ($)</label>
                <input type="number" id="init-enroll-broker-balance" step="0.01" min="0"
                       placeholder="Enter broker balance" style="width:100%; padding:10px; border-radius:6px; border:1px solid var(--border-color); background:var(--bg-secondary); color:var(--text-color); font-size:14px; box-sizing:border-box;">
                <div style="font-size: 12px; color: #888; margin-top: 4px;">Minimum required: $<span id="init-enroll-min-deposit">0.00</span></div>
            </div>

            <div style="background: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 16px; border-radius: 4px;">
                <strong style="color: #ffc107;">Warning: This will:</strong>
                <ul style="margin: 8px 0 0 20px; color: #aaa; font-size: 13px;">
                    <li>Set broker_balance to the entered amount</li>
                    <li>Set balance_verification to 'verified'</li>
                    <li>Set loyalties to NULL</li>
                    <li>Set execution_start_date to today</li>
                    <li>Set profitandloss to 0</li>
                    <li>Set reset_contract to 0</li>
                </ul>
            </div>

            <div id="init-enroll-error" style="color: #f44336; font-size: 13px; margin-bottom: 12px; display:none;"></div>

            <div class="revenuemodal-buttons">
                <button class="btn-cancel" onclick="Revenue.closeInitializeEnrollmentrevenuemodal()">Cancel</button>
                <button class="btn-confirm" id="init-enroll-confirm-btn" onclick="Revenue.confirmInitializeEnrollment()">Initialize Enrollment</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- CUSTOM CONFIRMATION revenuemodal             -->
<!-- ============================================ -->
<div id="custom-confirm-revenuemodal" class="revenuemodal-overlay" style="display:none;">
    <div class="revenuemodal-container revenuemodal-small">
        <div class="revenuemodal-header">
            <span id="confirm-revenuemodal-title">Confirm Action</span>
            <span class="revenuemodal-close" onclick="Revenue.closeConfirmrevenuemodal()">x</span>
        </div>
        <div class="revenuemodal-body">
            <p id="confirm-revenuemodal-message">Are you sure?</p>
            <div class="revenuemodal-buttons">
                <button class="btn-cancel" onclick="Revenue.closeConfirmrevenuemodal()">Cancel</button>
                <button class="btn-confirm" id="confirm-revenuemodal-confirm-btn" onclick="Revenue.confirmrevenuemodalAction()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- PASSWORD revenuemodal                        -->
<!-- ============================================ -->
<div id="password-revenuemodal" class="revenuemodal-overlay" style="display:none;">
    <div class="revenuemodal-container revenuemodal-small">
        <div class="revenuemodal-header">
            <span id="password-revenuemodal-title">Security Check</span>
            <span class="revenuemodal-close" onclick="Revenue.closePasswordrevenuemodal()">x</span>
        </div>
        <div class="revenuemodal-body">
            <p id="password-revenuemodal-message">Please enter your admin password to continue.</p>
            <input type="password" id="password-revenuemodal-input" placeholder="Enter password" style="width:100%;padding:10px;border-radius:6px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-color);font-size:14px;box-sizing:border-box;">
            <div id="password-revenuemodal-error" style="color:#f44336;font-size:13px;margin-top:6px;display:none;"></div>
            <div class="revenuemodal-buttons">
                <button class="btn-cancel" onclick="Revenue.closePasswordrevenuemodal()">Cancel</button>
                <button class="btn-confirm" id="password-revenuemodal-confirm-btn" onclick="Revenue.confirmPasswordrevenuemodal()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- SUCCESS/ERROR NOTIFICATION revenuemodal      -->
<!-- ============================================ -->
<div id="notification-revenuemodal" class="revenuemodal-overlay" style="display:none;">
    <div class="revenuemodal-container revenuemodal-small">
        <div class="revenuemodal-header">
            <span id="notification-revenuemodal-title">Notification</span>
            <span class="revenuemodal-close" onclick="Revenue.closeNotificationrevenuemodal()">x</span>
        </div>
        <div class="revenuemodal-body">
            <p id="notification-revenuemodal-message"></p>
            <div class="revenuemodal-buttons">
                <button class="btn-confirm" onclick="Revenue.closeNotificationrevenuemodal()">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- REVENUE HISTORY USERS revenuemodal           -->
<!-- ============================================ -->
<div id="revenue-history-users-revenuemodal" class="revenuemodal-overlay" style="display:none;" onclick="Revenue.closeRevenueHistoryUsersrevenuemodalIfClickOutside(event)">
    <div class="revenuemodal-container revenuemodal-large" onclick="event.stopPropagation()">
        <div class="revenuemodal-header">
            <span>Select User</span>
            <span class="revenuemodal-close" onclick="Revenue.closeRevenueHistoryUsersrevenuemodal()">x</span>
        </div>
        <div class="revenuemodal-body">
            <div class="users-revenuemodal-search">
                <input type="text" id="revenue-history-revenuemodal-search-input" class="user-search-input" placeholder="Search users by name, email, or ID..." onkeyup="Revenue.filterRevenueHistoryrevenuemodalUsers()">
            </div>
        </div>
        <div class="revenuemodal-body" id="revenue-history-revenuemodal-list" style="max-height: 50vh; overflow-y: auto;">
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
            <button class="back-btn" onclick="Revenue.closeUserDetail()"><- Back</button>
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
    <input type="hidden" id="login-id-hidden" value="<?php echo htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin'); ?>">
</div>

<script>
    const Revenue = {
        // Data
        allActiveUsers: [],
        filteredActiveUsers: [],
        allCompletedUsers: [],
        filteredCompletedUsers: [],
        allInactiveUsers: [],
        filteredInactiveUsers: [],
        unusualUsers: [],
        filteredUnusualUsers: [],
        allRevenueHistoryUsers: [],
        filteredRevenueHistoryUsers: [],
        selectedRevenueHistoryUser: null,
        revenueHistoryData: {},
        selectedUserRevenueHistory: [],

        // NEW: per-user caches for table-based daily target / balance log
        dailyTargetCache: {},   // { userId: { week_1: { Monday: {...} } } }
        balanceLogCache: {},    // { userId: { 'dd-mm-yyyy': {...} } }

        // Programme Investors data (userid => {...})
        programmeInvestorsMap: {},

        // State
        currentTab: 'active',
        currentActiveSubTab: 'all',
        currentUnusualSubTab: 'all',
        currentCompletedSubTab: 'inactive-above',
        currentInactiveSubTab: 'all',
        currentRevenueHistorySubTab: 'all',
        activeSearchTerm: '',
        completedSearchTerm: '',
        inactiveSearchTerm: '',
        revenueHistorySearchTerm: '',
        selectedUserId: null,
        selectedUserSource: null,
        isDetailViewOpen: false,
        isRevenueHistorySearchActive: false,
        isMobileView: false,

        _confirmCallback: null,
        _passwordCallback: null,
        _initEnrollCallback: null,

        serverSharePercent: 30,
        userSharePercent: 70,
        minProfitForSplit: 30,
        minBrokerBalance: 30,
        contractDuration: 30,

        // ============================================
        // NORMALIZATION HELPERS
        // ============================================
        normalizeLoyaltyStatus: function(status) {
            const s = String(status || '').toLowerCase().trim();
            if (s === '') return '';

            const isCancelled = s.indexOf('cancelled') !== -1
                             || s.indexOf('canceled') !== -1
                             || s.indexOf('contract_cancelled') !== -1;

            let base = null;
            if (s.indexOf('payment-confirmed') !== -1 || s.indexOf('payment_confirmed') !== -1) {
                base = 'payment-confirmed';
            } else if (s.indexOf('payment-made') !== -1 || s.indexOf('payment_made') !== -1) {
                base = 'payment-made';
            } else if (s.indexOf('failed-payment') !== -1 || s.indexOf('failed_payment') !== -1
                    || s.indexOf('payment-failed') !== -1 || s.indexOf('payment_failed') !== -1) {
                base = 'failed-payment';
            } else if (s.indexOf('unpaid-payment') !== -1 || s.indexOf('unpaid_payment') !== -1
                    || s.indexOf('unpaid') !== -1
                    || s.indexOf('payment-required') !== -1 || s.indexOf('payment_required') !== -1) {
                base = 'unpaid-payment';
            }

            if (isCancelled) {
                if (base !== null) {
                    return 'contract-cancelled-' + base;
                }
                return 'contract_cancelled';
            }

            if (base !== null) {
                return base;
            }

            return s;
        },

        loyaltyIsFamily: function(status, family) {
            const n = this.normalizeLoyaltyStatus(status);
            switch (family) {
                case 'unpaid':
                    return n === 'unpaid-payment' || n === 'contract-cancelled-unpaid-payment';
                case 'payment-made':
                    return n === 'payment-made' || n === 'contract-cancelled-payment-made';
                case 'payment-confirmed':
                    return n === 'payment-confirmed' || n === 'contract-cancelled-payment-confirmed';
                case 'failed':
                    return n === 'failed-payment' || n === 'contract-cancelled-failed-payment';
                case 'cancelled':
                    return n.indexOf('contract-cancelled') === 0 || n === 'contract_cancelled';
                default:
                    return false;
            }
        },

        // ============================================
        // PROGRAMME HELPERS
        // ============================================
        getProgrammeDataForUser: function(userId) {
            const pid = parseInt(userId);
            if (this.programmeInvestorsMap[pid]) {
                return this.programmeInvestorsMap[pid];
            }
            return {
                developerid: 0,
                developer_name: 'N/A',
                programme_name: '',
                contract_duration: this.contractDuration,
                developer_percentage: this.serverSharePercent,
                investor_percentage: this.userSharePercent,
                minimum_investment_amount: 0,
                maximum_investment_amount: 0,
                has_programme: false
            };
        },

        getDevPercent: function(userId) { return this.getProgrammeDataForUser(userId).developer_percentage; },
        getInvPercent: function(userId) { return this.getProgrammeDataForUser(userId).investor_percentage; },
        getContractDurationForUser: function(userId) { return this.getProgrammeDataForUser(userId).contract_duration; },

        // ============================================
        // INIT
        // ============================================
        init: function() {
            const configEl = document.getElementById('revenue-config');
            if (configEl) {
                this.serverSharePercent = parseInt(configEl.dataset.serverShare) || 30;
                this.userSharePercent = parseInt(configEl.dataset.userShare) || 70;
                this.minProfitForSplit = parseFloat(configEl.dataset.minProfit) || 30;
                this.minBrokerBalance = parseFloat(configEl.dataset.minDeposit) || 30;
                this.contractDuration = parseInt(configEl.dataset.contractDuration) || 30;
            } else {
                this.serverSharePercent = parseInt(document.querySelector('[data-server-share]')?.dataset?.serverShare) || 30;
                this.userSharePercent = parseInt(document.querySelector('[data-user-share]')?.dataset?.userShare) || 70;
                this.minProfitForSplit = parseFloat(document.querySelector('[data-min-profit]')?.dataset?.minProfit) || 30;
                this.minBrokerBalance = parseFloat(document.querySelector('[data-min-deposit]')?.dataset?.minDeposit) || 30;
                this.contractDuration = parseInt(document.querySelector('[data-contract-duration]')?.dataset?.contractDuration) || 30;
            }

            document.getElementById('init-enroll-min-deposit').textContent = this.minBrokerBalance.toFixed(2);

            this.loadUsers();
            this.bindEvents();
        },

        bindEvents: function() {
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (Revenue.isDetailViewOpen) Revenue.closeUserDetail();
                    Revenue.closeConfirmrevenuemodal();
                    Revenue.closePasswordrevenuemodal();
                    Revenue.closeNotificationrevenuemodal();
                    Revenue.closeRevenueHistoryUsersrevenuemodal();
                    Revenue.closeInitializeEnrollmentrevenuemodal();
                    Revenue.deactivateSearch('active');
                    Revenue.deactivateSearch('completed');
                    Revenue.deactivateSearch('inactive');
                }
                if (e.key === 'Enter') {
                    if (document.getElementById('password-revenuemodal').style.display === 'flex') Revenue.confirmPasswordrevenuemodal();
                    if (document.getElementById('custom-confirm-revenuemodal').style.display === 'flex') Revenue.confirmrevenuemodalAction();
                    if (document.getElementById('initialize-enrollment-revenuemodal').style.display === 'flex') Revenue.confirmInitializeEnrollment();
                }
            });

            document.addEventListener('click', function(e) {
                const row = e.target.closest('.clickable-row');
                if (row && !e.target.closest('.action-select') && !e.target.closest('.status-select')) {
                    const userId = row.dataset.userId;
                    const source = row.dataset.source || 'harvhub';
                    Revenue.viewUserDetail(userId, source);
                }
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.search-bar-wrapper') && !e.target.closest('.search-bar')) {
                    Revenue.deactivateSearch('active');
                    Revenue.deactivateSearch('completed');
                    Revenue.deactivateSearch('inactive');
                }
            });
        },

        // ============================================
        // MODAL HELPERS
        // ============================================
        showConfirmrevenuemodal: function(title, message, callback) {
            document.getElementById('confirm-revenuemodal-title').textContent = title || 'Confirm Action';
            document.getElementById('confirm-revenuemodal-message').textContent = message || 'Are you sure?';
            this._confirmCallback = callback;
            document.getElementById('custom-confirm-revenuemodal').style.display = 'flex';
        },
        closeConfirmrevenuemodal: function() {
            document.getElementById('custom-confirm-revenuemodal').style.display = 'none';
            this._confirmCallback = null;
        },
        confirmrevenuemodalAction: function() {
            const cb = this._confirmCallback;
            this.closeConfirmrevenuemodal();
            if (typeof cb === 'function') cb();
        },

        showPasswordrevenuemodal: function(title, message, callback) {
            document.getElementById('password-revenuemodal-title').textContent = title || 'Security Check';
            document.getElementById('password-revenuemodal-message').textContent = message || 'Please enter your admin password to continue.';
            document.getElementById('password-revenuemodal-input').value = '';
            document.getElementById('password-revenuemodal-error').style.display = 'none';
            this._passwordCallback = callback;
            document.getElementById('password-revenuemodal').style.display = 'flex';
            setTimeout(() => document.getElementById('password-revenuemodal-input').focus(), 100);
        },
        closePasswordrevenuemodal: function() {
            document.getElementById('password-revenuemodal').style.display = 'none';
            this._passwordCallback = null;
            document.getElementById('password-revenuemodal-error').style.display = 'none';
        },
        confirmPasswordrevenuemodal: function() {
            const password = document.getElementById('password-revenuemodal-input').value;
            const errorEl = document.getElementById('password-revenuemodal-error');
            if (!password) {
                errorEl.textContent = 'Please enter your password.';
                errorEl.style.display = 'block';
                return;
            }
            const cb = this._passwordCallback;
            this.closePasswordrevenuemodal();
            if (typeof cb === 'function') cb(password);
        },

        showNotification: function(message, title, isError) {
            document.getElementById('notification-revenuemodal-title').textContent = title || (isError ? 'Error' : 'Success');
            document.getElementById('notification-revenuemodal-message').textContent = message || '';
            document.getElementById('notification-revenuemodal').style.display = 'flex';
        },
        closeNotificationrevenuemodal: function() {
            document.getElementById('notification-revenuemodal').style.display = 'none';
        },

        // ============================================
        // SEARCH toggle
        // ============================================
        activateSearch: function(tab) {
            const dummy = document.getElementById(tab + '-search-dummy');
            const real  = document.getElementById(tab + '-search-real');
            const input = document.getElementById(tab + '-search-input');
            if (dummy) dummy.style.display = 'none';
            if (real) real.style.display = 'flex';
            if (input) {
                input.focus();
                if (tab === 'active' && this.activeSearchTerm) input.value = this.activeSearchTerm;
                else if (tab === 'completed' && this.completedSearchTerm) input.value = this.completedSearchTerm;
                else if (tab === 'inactive' && this.inactiveSearchTerm) input.value = this.inactiveSearchTerm;
            }
        },
        deactivateSearch: function(tab) {
            const dummy = document.getElementById(tab + '-search-dummy');
            const real  = document.getElementById(tab + '-search-real');
            const term = tab === 'active' ? this.activeSearchTerm
                       : tab === 'completed' ? this.completedSearchTerm
                       : this.inactiveSearchTerm;
            if (term) {
                if (dummy) dummy.style.display = 'none';
                if (real) real.style.display = 'flex';
            } else {
                if (dummy) dummy.style.display = 'flex';
                if (real) real.style.display = 'none';
            }
        },

        activateRevenueHistoryGlobalSearch: function() {
            const dummy = document.getElementById('rev-history-global-search-dummy');
            const real  = document.getElementById('rev-history-global-search-real');
            const input = document.getElementById('rev-history-global-search-input');
            if (dummy) dummy.style.display = 'none';
            if (real) real.style.display = 'flex';
            if (input) {
                input.focus();
                if (this.revenueHistorySearchTerm) input.value = this.revenueHistorySearchTerm;
            }
        },
        deactivateRevenueHistoryGlobalSearch: function() {
            const dummy = document.getElementById('rev-history-global-search-dummy');
            const real  = document.getElementById('rev-history-global-search-real');
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
            this.loadProgrammeInvestors().then(() => {
                this.loadActiveUsers();
                this.loadCompletedUsers();
                this.loadInactiveUsers();
                this.loadRevenueHistoryUsers();
            });
        },

        loadProgrammeInvestors: function() {
            return fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=get_programme_investors_map'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.investors) this.programmeInvestorsMap = data.investors;
                return true;
            })
            .catch(err => {
                console.error('Error loading programme investors:', err);
                return true;
            });
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
            .then(r => r.json())
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
            .catch(err => {
                console.error('Error loading active users:', err);
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
            .then(r => r.json())
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
            .catch(err => {
                console.error('Error loading completed users:', err);
                this.allCompletedUsers = [];
                this.filteredCompletedUsers = [];
                this.renderCompletedUsers();
            });
        },

        loadInactiveUsers: function() {
            fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=get_inactive_users'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.allInactiveUsers = data.users || [];
                    this.filteredInactiveUsers = this.getFilteredInactiveUsers();
                    this.renderInactiveUsers();
                    this.updateInactiveBadges();
                    this.updateInactiveCubes();
                    this.updateBadge('inactive-count', this.allInactiveUsers.length);
                } else {
                    this.allInactiveUsers = [];
                    this.filteredInactiveUsers = [];
                    this.renderInactiveUsers();
                }
            })
            .catch(err => {
                console.error('Error loading inactive users:', err);
                this.allInactiveUsers = [];
                this.filteredInactiveUsers = [];
                this.renderInactiveUsers();
            });
        },

        loadRevenueHistoryUsers: function() {
            fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=get_completed_investors'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.allRevenueHistoryUsers = data.users || [];
                    this.allRevenueHistoryUsers = this.allRevenueHistoryUsers.map(user => {
                        if (user.revenue_history && typeof user.revenue_history === 'string') {
                            try { user.revenue_history = JSON.parse(user.revenue_history); }
                            catch (e) { user.revenue_history = []; }
                        }
                        if (!Array.isArray(user.revenue_history)) user.revenue_history = [];
                        return user;
                    });
                    this.filteredRevenueHistoryUsers = this.getFilteredRevenueHistoryUsers();
                    this.updateBadge('revenue-history-count', this.allRevenueHistoryUsers.length);
                    this.selectedRevenueHistoryUser = null;
                    this.selectedUserRevenueHistory = [];
                    this.updateRevenueHistoryGlobalCubes();
                    this.showOverview();
                } else {
                    this.allRevenueHistoryUsers = [];
                    this.filteredRevenueHistoryUsers = [];
                }
            })
            .catch(err => {
                console.error('Error loading revenue history users:', err);
                this.allRevenueHistoryUsers = [];
                this.filteredRevenueHistoryUsers = [];
            });
        },

        // ============================================
        // REVENUE HISTORY GLOBAL CUBES
        // ============================================
        updateRevenueHistoryGlobalCubes: function() {
            let totals = {
                totalInvestment: 0, totalPnl: 0, totalUnpaid: 0,
                totalPaymentsMade: 0, totalPaymentsConfirmed: 0,
                totalFailed: 0, totalCancelled: 0,
                totalServerShare: 0, totalUserShare: 0
            };

            this.allRevenueHistoryUsers.forEach(user => {
                let history = user.revenue_history || [];
                if (typeof history === 'string') {
                    try { history = JSON.parse(history); } catch (e) { history = []; }
                }
                if (!Array.isArray(history)) history = [];

                history.forEach(record => {
                    const loyaltiesRaw = record.loyalties || '';
                    const serverShare = parseFloat(record.server_share || 0);
                    const userShare = parseFloat(record.user_share || 0);
                    const profit = parseFloat(record.profit || 0);
                    const startingBalance = parseFloat(record.starting_balance || 0);

                    if (String(loyaltiesRaw).toLowerCase().indexOf('active') !== -1) return;

                    if (Revenue.loyaltyIsFamily(loyaltiesRaw, 'payment-confirmed')) totals.totalPaymentsConfirmed += serverShare;
                    else if (Revenue.loyaltyIsFamily(loyaltiesRaw, 'payment-made')) totals.totalPaymentsMade += serverShare;
                    else if (Revenue.loyaltyIsFamily(loyaltiesRaw, 'failed')) totals.totalFailed += serverShare;
                    else if (Revenue.loyaltyIsFamily(loyaltiesRaw, 'unpaid')) totals.totalUnpaid += serverShare;
                    else if (Revenue.loyaltyIsFamily(loyaltiesRaw, 'cancelled')) totals.totalCancelled += 1;

                    totals.totalInvestment += startingBalance;
                    totals.totalPnl += profit;
                    totals.totalServerShare += serverShare;
                    totals.totalUserShare += userShare;
                });
            });

            this.updateCubeValue('rev-global-total-investment', totals.totalInvestment);
            this.updateCubeValue('rev-global-total-pnl', totals.totalPnl);
            this.updateCubeValue('rev-global-total-unpaid', totals.totalUnpaid);
            this.updateCubeValue('rev-global-total-payments-made', totals.totalPaymentsMade);
            this.updateCubeValue('rev-global-total-payments-confirmed', totals.totalPaymentsConfirmed);
            this.updateCubeValue('rev-global-total-failed', totals.totalFailed);
            this.updateCubeValueCount('rev-global-total-cancelled', totals.totalCancelled);
        },

        // ============================================
        // SHOW/HIDE OVERVIEW / USER DETAILS
        // ============================================
        showOverview: function() {
            document.getElementById('revenue-history-global-cubes').style.display = 'flex';
            document.getElementById('revenue-history-user-cubes').style.display = 'none';
            document.getElementById('revenue-history-overview').style.display = 'block';
            document.getElementById('revenue-history-user-details').style.display = 'none';
            this.renderRevenueHistoryUsersList(this.filteredRevenueHistoryUsers);
        },

        showUserDetails: function() {
            document.getElementById('revenue-history-global-cubes').style.display = 'none';
            document.getElementById('revenue-history-user-cubes').style.display = 'flex';
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
                    try { history = JSON.parse(history); } catch (e) { history = []; }
                }
                if (!Array.isArray(history)) history = [];
                const recordCount = history.length;

                html += `
                    <div class="revenue-history-user-item"
                        onclick="Revenue.selectRevenueHistoryUser('${user.id}', '${user.source}')">
                        <div class="user-name">${this.escapeHtml(user.fullname || 'N/A')}</div>
                        <div class="user-email">${this.escapeHtml(user.email || 'N/A')}</div>
                        <div class="user-id">ID: ${user.id}</div>
                        ${recordCount > 0
                            ? `<div class="user-record-count">${recordCount} records</div>`
                            : `<div class="user-record-count" style="color:#888;">No records</div>`}
                    </div>
                `;
            });

            container.innerHTML = html;
        },

        selectRevenueHistoryUser: function(userId, source) {
            const user = this.allRevenueHistoryUsers.find(u => u.id == userId && u.source === source);
            if (!user) return;
            this.selectRevenueHistoryUserFromrevenuemodal(userId, source);
        },

        processUserRevenueHistory: function(user) {
            let history = user.revenue_history || [];
            if (!Array.isArray(history)) {
                if (typeof history === 'string') {
                    try { history = JSON.parse(history); } catch (e) { history = []; }
                } else { history = []; }
            }

            let totals = {
                totalInvestment: 0, totalPnl: 0, totalServerShare: 0, totalUserShare: 0,
                totalPaymentsMade: 0, totalPaymentsConfirmed: 0, totalCancelled: 0, totalFailed: 0
            };

            history.forEach(record => {
                const loyaltiesRaw = record.loyalties || '';
                const serverShare = parseFloat(record.server_share || 0);
                const userShare = parseFloat(record.user_share || 0);
                const profit = parseFloat(record.profit || 0);
                const startingBalance = parseFloat(record.starting_balance || 0);

                totals.totalInvestment += startingBalance;
                totals.totalPnl += profit;
                totals.totalUserShare += userShare;
                totals.totalServerShare += serverShare;

                if (Revenue.loyaltyIsFamily(loyaltiesRaw, 'payment-confirmed')) totals.totalPaymentsConfirmed += serverShare;
                else if (Revenue.loyaltyIsFamily(loyaltiesRaw, 'payment-made')) totals.totalPaymentsMade += serverShare;
                else if (Revenue.loyaltyIsFamily(loyaltiesRaw, 'failed')) totals.totalFailed += serverShare;
                else if (Revenue.loyaltyIsFamily(loyaltiesRaw, 'cancelled')) totals.totalCancelled += 1;
            });

            return totals;
        },

        renderUserRevenueHistorySummary: function(user) {
            if (user.revenue_history && typeof user.revenue_history === 'string') {
                try { user.revenue_history = JSON.parse(user.revenue_history); } catch (e) { user.revenue_history = []; }
            }
            if (!Array.isArray(user.revenue_history)) user.revenue_history = [];

            const totals = this.processUserRevenueHistory(user);
            this.selectedUserRevenueHistory = user.revenue_history || [];

            this.updateCubeValue('rev-user-total-investment', totals.totalInvestment);
            this.updateCubeValue('rev-user-total-pnl', totals.totalPnl);
            this.updateCubeValue('rev-user-total-server-share', totals.totalServerShare);
            this.updateCubeValue('rev-user-total-user-share', totals.totalUserShare);
            this.updateCubeValue('rev-user-total-payments-made', totals.totalPaymentsMade);
            this.updateCubeValue('rev-user-total-payments-confirmed', totals.totalPaymentsConfirmed);
            this.updateCubeValueCount('rev-user-total-cancelled', totals.totalCancelled);
            this.updateCubeValue('rev-user-total-failed', totals.totalFailed);

            this.renderUserRevenueRecords(this.selectedUserRevenueHistory);
            this.updateUserRevenueHistorySubTabBadges(user);
        },

        updateCubeValue: function(id, value) {
            const el = document.getElementById(id);
            if (!el) return;
            const num = parseFloat(value) || 0;
            el.textContent = '$' + this.formatNumber(num);
            el.style.color = num > 0 ? '#4caf50' : (num < 0 ? '#f44336' : 'var(--text-color, #ffffff)');
        },
        updateCubeValueCount: function(id, value) {
            const el = document.getElementById(id);
            if (!el) return;
            const num = parseInt(value) || 0;
            el.textContent = num;
            el.style.color = num > 0 ? '#4caf50' : 'var(--text-color, #ffffff)';
        },

        renderUserRevenueRecords: function(history) {
            const container = document.getElementById('revenue-history-records-list');
            if (!Array.isArray(history)) history = [];

            let filteredHistory = this.filterRevenueHistoryBySubTab(history);

            if (filteredHistory.length === 0) {
                container.innerHTML = '<div style="text-align:center;padding:40px;color:#888;">No revenue records found for this filter</div>';
                return;
            }

            const sortedHistory = [...filteredHistory].sort((a, b) => (parseInt(b.id) || 0) - (parseInt(a.id) || 0));

            let html = `
                <div style="overflow-x:auto; border-radius: 8px; border: 1px solid var(--border-color);">
                    <table style="width:100%; border-collapse: collapse; font-size: 12px;">
                        <thead>
                            <tr style="background: var(--bg-secondary);">
                                <th style="padding: 8px 10px; text-align: left; border-bottom: 2px solid var(--border-color);">Contract ID</th>
                                <th style="padding: 8px 10px; text-align: left; border-bottom: 2px solid var(--border-color);">Start</th>
                                <th style="padding: 8px 10px; text-align: left; border-bottom: 2px solid var(--border-color);">End</th>
                                <th style="padding: 8px 10px; text-align: left; border-bottom: 2px solid var(--border-color);">Duration</th>
                                <th style="padding: 8px 10px; text-align: right; border-bottom: 2px solid var(--border-color);">Starting Balance</th>
                                <th style="padding: 8px 10px; text-align: right; border-bottom: 2px solid var(--border-color);">Current Balance</th>
                                <th style="padding: 8px 10px; text-align: right; border-bottom: 2px solid var(--border-color);">Profit</th>
                                <th style="padding: 8px 10px; text-align: right; border-bottom: 2px solid var(--border-color);">Dev %</th>
                                <th style="padding: 8px 10px; text-align: right; border-bottom: 2px solid var(--border-color);">Dev Amount</th>
                                <th style="padding: 8px 10px; text-align: right; border-bottom: 2px solid var(--border-color);">Inv %</th>
                                <th style="padding: 8px 10px; text-align: right; border-bottom: 2px solid var(--border-color);">Inv Amount</th>
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
                const status = this.normalizeLoyaltyStatus(record.loyalties || 'Unknown');
                const statusClass = this.getStatusClass(status);
                const investedWith = record.invested_with || 'N/A';

                const progData = this.getProgrammeDataForUser(this.selectedRevenueHistoryUser?.id);
                const devPercent = progData.developer_percentage;
                const invPercent = progData.investor_percentage;
                const duration = progData.contract_duration;

                html += `
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 6px 10px; font-size: 10px; font-family: monospace;">${this.escapeHtml(record.contract_id || 'N/A')}</td>
                        <td style="padding: 6px 10px;">${this.formatDate(record.execution_start_date)}</td>
                        <td style="padding: 6px 10px;">${this.formatDate(record.execution_end_date)}</td>
                        <td style="padding: 6px 10px;">${duration} days</td>
                        <td style="padding: 6px 10px; text-align: right;">$${this.formatNumber(startingBalance)}</td>
                        <td style="padding: 6px 10px; text-align: right;">$${this.formatNumber(currentBalance)}</td>
                        <td style="padding: 6px 10px; text-align: right; color: ${profit >= 0 ? '#4caf50' : '#f44336'}; font-weight: 600;">$${this.formatNumber(profit)}</td>
                        <td style="padding: 6px 10px; text-align: right;">${devPercent}%</td>
                        <td style="padding: 6px 10px; text-align: right;">$${this.formatNumber(serverShare)}</td>
                        <td style="padding: 6px 10px; text-align: right;">${invPercent}%</td>
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

        updateUserRevenueHistorySubTabBadges: function(user) {
            let history = user.revenue_history || [];
            if (!Array.isArray(history)) {
                if (typeof history === 'string') {
                    try { history = JSON.parse(history); } catch (e) { history = []; }
                } else { history = []; }
            }

            let counts = { all: 0, unpaid: 0, 'payment-made': 0, 'payment-confirmed': 0, failed: 0, cancelled: 0 };

            history.forEach(record => {
                const l = record.loyalties || '';
                counts.all++;
                if (Revenue.loyaltyIsFamily(l, 'payment-confirmed')) counts['payment-confirmed']++;
                else if (Revenue.loyaltyIsFamily(l, 'payment-made')) counts['payment-made']++;
                else if (Revenue.loyaltyIsFamily(l, 'failed')) counts.failed++;
                else if (Revenue.loyaltyIsFamily(l, 'unpaid')) counts.unpaid++;
                else if (Revenue.loyaltyIsFamily(l, 'cancelled')) counts.cancelled++;
            });

            this.updateBadge('rev-all-count', counts.all);
            this.updateBadge('rev-unpaid-count', counts.unpaid);
            this.updateBadge('rev-payment-made-count', counts['payment-made']);
            this.updateBadge('rev-payment-confirmed-count', counts['payment-confirmed']);
            this.updateBadge('rev-failed-count', counts.failed);
            this.updateBadge('rev-cancelled-count', counts.cancelled);
        },

        filterRevenueHistoryBySubTab: function(history) {
            const subTab = this.currentRevenueHistorySubTab;
            if (subTab === 'all') return history;

            return history.filter(record => {
                const l = record.loyalties || '';
                switch (subTab) {
                    case 'unpaid': return Revenue.loyaltyIsFamily(l, 'unpaid');
                    case 'payment-made': return Revenue.loyaltyIsFamily(l, 'payment-made');
                    case 'payment-confirmed': return Revenue.loyaltyIsFamily(l, 'payment-confirmed');
                    case 'failed': return Revenue.loyaltyIsFamily(l, 'failed');
                    case 'cancelled': return Revenue.loyaltyIsFamily(l, 'cancelled');
                    default: return true;
                }
            });
        },

        switchRevenueHistorySubTab: function(subTab) {
            this.currentRevenueHistorySubTab = subTab;
            document.querySelectorAll('#revenue-history-sub-tabs .sub-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.revenueSubtab === subTab);
            });
            if (this.selectedRevenueHistoryUser) {
                this.renderUserRevenueRecords(this.selectedUserRevenueHistory);
            }
        },

        showRevenueHistoryUsersrevenuemodal: function() {
            const modal = document.getElementById('revenue-history-users-revenuemodal');
            const input = document.getElementById('revenue-history-revenuemodal-search-input');
            modal.style.display = 'flex';
            input.value = '';
            this.renderRevenueHistoryrevenuemodalUsers(this.allRevenueHistoryUsers);
            input.focus();
        },
        closeRevenueHistoryUsersrevenuemodal: function() {
            document.getElementById('revenue-history-users-revenuemodal').style.display = 'none';
        },
        closeRevenueHistoryUsersrevenuemodalIfClickOutside: function(event) {
            if (event.target.id === 'revenue-history-users-revenuemodal') {
                this.closeRevenueHistoryUsersrevenuemodal();
            }
        },
        filterRevenueHistoryrevenuemodalUsers: function() {
            const term = document.getElementById('revenue-history-revenuemodal-search-input').value.toLowerCase();
            const filtered = this.allRevenueHistoryUsers.filter(user => {
                const name = (user.fullname || '').toLowerCase();
                const email = (user.email || '').toLowerCase();
                const id = String(user.id || '');
                return name.includes(term) || email.includes(term) || id.includes(term);
            });
            this.renderRevenueHistoryrevenuemodalUsers(filtered);
        },
        renderRevenueHistoryrevenuemodalUsers: function(users) {
            const container = document.getElementById('revenue-history-revenuemodal-list');
            if (users.length === 0) {
                container.innerHTML = '<div style="text-align:center;padding:40px;color:#888;">No users found</div>';
                return;
            }

            let html = '';
            users.forEach(user => {
                let history = user.revenue_history || [];
                if (typeof history === 'string') {
                    try { history = JSON.parse(history); } catch (e) { history = []; }
                }
                if (!Array.isArray(history)) history = [];
                const recordCount = history.length;

                const isSelected = this.selectedRevenueHistoryUser &&
                    this.selectedRevenueHistoryUser.id === user.id &&
                    this.selectedRevenueHistoryUser.source === user.source;

                html += `
                    <div class="revenuemodal-user-item ${isSelected ? 'selected' : ''}"
                        onclick="Revenue.selectRevenueHistoryUserFromrevenuemodal(${user.id}, '${user.source}')">
                        <div class="revenuemodal-user-name">${this.escapeHtml(user.fullname || 'N/A')}</div>
                        <div class="revenuemodal-user-email">${this.escapeHtml(user.email || 'N/A')}</div>
                        <div class="revenuemodal-user-id">ID: ${user.id}</div>
                        ${recordCount > 0
                            ? `<div style="font-size:10px;color:#888;margin-top:4px;">${recordCount} records</div>`
                            : `<div style="font-size:10px;color:#888;margin-top:4px;">No history records</div>`}
                    </div>
                `;
            });
            container.innerHTML = html;
        },
        selectRevenueHistoryUserFromrevenuemodal: function(userId, source) {
            const user = this.allRevenueHistoryUsers.find(u => u.id == userId && u.source === source);
            if (!user) return;

            if (user.revenue_history && typeof user.revenue_history === 'string') {
                try { user.revenue_history = JSON.parse(user.revenue_history); } catch (e) { user.revenue_history = []; }
            }
            if (!Array.isArray(user.revenue_history)) user.revenue_history = [];

            this.selectedRevenueHistoryUser = user;
            this.closeRevenueHistoryUsersrevenuemodal();
            this.showUserDetails();

            const placeholder = document.getElementById('revenue-history-search-placeholder');
            if (placeholder) placeholder.textContent = this.escapeHtml(user.fullname || 'N/A') + ' (ID: ' + user.id + ')';

            document.getElementById('revenue-history-user-name').textContent =
                this.escapeHtml(user.fullname || 'N/A') + ' - Revenue History';

            this.currentRevenueHistorySubTab = 'all';
            document.querySelectorAll('#revenue-history-sub-tabs .sub-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.revenueSubtab === 'all');
            });

            this.renderUserRevenueHistorySummary(user);
        },
        clearRevenueHistoryUserSelection: function() {
            this.selectedRevenueHistoryUser = null;
            this.selectedUserRevenueHistory = [];
            this.showOverview();
            this.updateRevenueHistoryGlobalCubes();
            const placeholder = document.getElementById('revenue-history-search-placeholder');
            if (placeholder) placeholder.textContent = 'Search users by name, email, or ID...';
        },

        // ============================================
        // UNUSUAL / ACTIVE / INACTIVE FILTERS
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
            .then(r => r.json())
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
            .catch(err => {
                console.error('Error loading unusual users:', err);
                this.unusualUsers = [];
                this.filteredUnusualUsers = [];
                this.filteredActiveUsers = [];
                this.renderActiveUsers();
            });
        },

        getFilteredUnusualUsers: function() {
            let users = [...this.unusualUsers];
            const subTab = this.currentUnusualSubTab;

            switch (subTab) {
                case 'withdrawals': users = users.filter(u => (u.withdrawal_count || 0) > 0); break;
                case 'trades':      users = users.filter(u => (u.unauthorized_trade_count || 0) > 0); break;
                default: break;
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
        // USER DATA FETCHERS (for detail overlay) — now table-based
        // ============================================
        fetchUserData: function(userId, sourceTable) {
            // 1) Daily target from `daily_target_revenue`
            // 2) Balance log from `balance_log`
            return fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=get_user_daily_log&user_id=${userId}&source_table=${sourceTable}`
            })
            .then(r => r.json());
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
            .then(r => r.json());
        },

        // ============================================
        // FILTER ACTIVE / COMPLETED / INACTIVE
        // ============================================
        getFilteredActiveUsers: function() {
            let users = [...this.allActiveUsers];
            const subTab = this.currentActiveSubTab;

            switch (subTab) {
                case 'all': break;
                case 'unusual': return this.filteredUnusualUsers;
                case 'above-threshold':
                    users = users.filter(u => (parseFloat(u.profitandloss) || 0) > this.minProfitForSplit);
                    break;
                case 'below-threshold':
                    users = users.filter(u => {
                        const p = parseFloat(u.profitandloss) || 0;
                        return p > 0 && p <= this.minProfitForSplit;
                    });
                    break;
                case 'profit':
                    users = users.filter(u => (parseFloat(u.profitandloss) || 0) > 0);
                    break;
                case 'loss':
                    users = users.filter(u => (parseFloat(u.profitandloss) || 0) < 0);
                    break;
                default: break;
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

        getFilteredCompletedUsers: function() {
            let users = [...this.allCompletedUsers];
            const subTab = this.currentCompletedSubTab;

            const isContractEnded = (user) => {
                const execDate = user.execution_start_date;
                if (!execDate || execDate === '0000-00-00' || execDate === null) return false;
                const duration = this.getContractDurationForUser(user.id);
                const start = new Date(execDate);
                const end = new Date(start);
                end.setDate(end.getDate() + duration);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                end.setHours(0, 0, 0, 0);
                return today > end;
            };

            const isCancelled = (user) => {
                const l = (user.current_loyalties || user.loyalties || '').toLowerCase();
                return l === 'contract-cancelled' || l === 'contract_cancelled' || l.includes('cancelled');
            };

            const hasPaymentStatus = (user) => {
                const l = (user.current_loyalties || user.loyalties || '');
                return Revenue.loyaltyIsFamily(l, 'payment-confirmed')
                    || Revenue.loyaltyIsFamily(l, 'payment-made')
                    || Revenue.loyaltyIsFamily(l, 'unpaid')
                    || Revenue.loyaltyIsFamily(l, 'failed');
            };

            switch (subTab) {
                case 'inactive-above':
                    users = users.filter(u => {
                        const p = parseFloat(u.profitandloss) || 0;
                        return isContractEnded(u) && p > this.minProfitForSplit && !hasPaymentStatus(u);
                    });
                    break;
                case 'inactive-below':
                    users = users.filter(u => {
                        const p = parseFloat(u.profitandloss) || 0;
                        return isContractEnded(u) && p > 0 && p <= this.minProfitForSplit && !hasPaymentStatus(u) && !isCancelled(u);
                    });
                    break;
                case 'inactive-loss':
                    users = users.filter(u => {
                        const p = parseFloat(u.profitandloss) || 0;
                        return isContractEnded(u) && p < 0 && !hasPaymentStatus(u) && !isCancelled(u);
                    });
                    break;
                case 'unpaid':
                    users = users.filter(u => Revenue.loyaltyIsFamily(u.current_loyalties || u.loyalties || '', 'unpaid'));
                    break;
                case 'payment-made':
                    users = users.filter(u => Revenue.loyaltyIsFamily(u.current_loyalties || u.loyalties || '', 'payment-made'));
                    break;
                case 'payment-confirmed':
                    users = users.filter(u => Revenue.loyaltyIsFamily(u.current_loyalties || u.loyalties || '', 'payment-confirmed'));
                    break;
                case 'failed':
                    users = users.filter(u => Revenue.loyaltyIsFamily(u.current_loyalties || u.loyalties || '', 'failed'));
                    break;
                default: break;
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

        getFilteredInactiveUsers: function() {
            let users = [...this.allInactiveUsers];
            const subTab = this.currentInactiveSubTab;

            switch (subTab) {
                case 'no-contract':
                    users = users.filter(u => {
                        const e = u.execution_start_date;
                        return !e || e === '0000-00-00' || e === null;
                    });
                    break;
                case 'expired':
                    users = users.filter(u => {
                        const e = u.execution_start_date;
                        if (!e || e === '0000-00-00' || e === null) return false;
                        const duration = this.getContractDurationForUser(u.id);
                        const start = new Date(e);
                        const end = new Date(start);
                        end.setDate(end.getDate() + duration);
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        end.setHours(0, 0, 0, 0);
                        return today > end;
                    });
                    break;
                case 'cancelled':
                    users = users.filter(u => Revenue.loyaltyIsFamily(u.loyalties || '', 'cancelled'));
                    break;
                default: break;
            }

            if (this.inactiveSearchTerm) {
                const term = this.inactiveSearchTerm.toLowerCase();
                users = users.filter(u => {
                    const name = (u.fullname || '').toLowerCase();
                    const email = (u.email || '').toLowerCase();
                    const id = String(u.id || '');
                    return name.includes(term) || email.includes(term) || id.includes(term);
                });
            }
            return users;
        },

        filterInactiveTable: function() {
            const input = document.getElementById('inactive-search-input');
            this.inactiveSearchTerm = input.value.trim();
            document.getElementById('inactive-search-clear').style.display = this.inactiveSearchTerm ? 'block' : 'none';
            this.filteredInactiveUsers = this.getFilteredInactiveUsers();
            this.renderInactiveUsers();
            this.updateInactiveCubes();
        },
        clearInactiveSearch: function() {
            document.getElementById('inactive-search-input').value = '';
            document.getElementById('inactive-search-clear').style.display = 'none';
            this.inactiveSearchTerm = '';
            this.deactivateSearch('inactive');
            this.filteredInactiveUsers = this.getFilteredInactiveUsers();
            this.renderInactiveUsers();
            this.updateInactiveCubes();
        },

        // ============================================
        // RENDER ACTIVE / COMPLETED / INACTIVE TABLES
        // ============================================
        renderActiveUsers: function() {
            const tbody = document.getElementById('active-users-body');
            const users = this.filteredActiveUsers;
            const isUnusualTab = this.currentActiveSubTab === 'unusual';

            if (!users || users.length === 0) {
                tbody.innerHTML = `<tr><td colspan="13" style="text-align:center;padding:40px;color:#888;">${isUnusualTab ? 'No unusual activity found' : 'No active users found'}</td></tr>`;
                return;
            }

            let html = '';
            users.forEach(user => {
                const brokerBalance = parseFloat(user.broker_balance) || 0;
                const profitAndLoss = parseFloat(user.profitandloss) || 0;
                const currentBalance = brokerBalance + profitAndLoss;
                const profitClass = profitAndLoss >= 0 ? 'profit' : 'loss';
                const balanceClass = currentBalance >= 0 ? 'profit' : 'loss';

                const progData = this.getProgrammeDataForUser(user.id);
                const devPercent = progData.developer_percentage;
                const invPercent = progData.investor_percentage;

                let investorAmount = 0, developerAmount = 0;
                if (profitAndLoss > 0) {
                    investorAmount = (profitAndLoss * invPercent) / 100;
                    developerAmount = (profitAndLoss * devPercent) / 100;
                }

                let status = 'Active', statusClass = 'status-active';
                if (profitAndLoss > this.minProfitForSplit) { status = 'Above Threshold'; statusClass = 'status-above'; }
                else if (profitAndLoss > 0) { status = 'In Profit'; statusClass = 'status-profit'; }
                else if (profitAndLoss < 0) { status = 'In Loss'; statusClass = 'status-loss'; }
                else { status = 'Break Even'; statusClass = 'status-breakeven'; }

                let actionHtml = '';
                const clickableClass = 'clickable-row';
                const dataAttrs = `data-user-id="${user.id}" data-source="${user.source || 'harvhub'}"`;

                if (isUnusualTab) {
                    const wc = user.withdrawal_count || 0;
                    const tc = user.unauthorized_trade_count || 0;
                    status = 'Unusual (W:' + wc + ', T:' + tc + ')';
                    statusClass = 'status-unusual';
                    actionHtml = `
                        <select class="action-select" data-user-id="${user.id}" data-source="${user.source || 'harvhub'}" onchange="Revenue.handleUnusualAction(this)">
                            <option value="">Remain Active</option>
                            <option value="cancel-contract">Cancel Contract</option>
                        </select>
                    `;
                } else {
                    actionHtml = `
                        <select class="action-select" data-user-id="${user.id}" data-source="${user.source || 'harvhub'}" onchange="Revenue.handleActiveAction(this)">
                            <option value="">Remain Active</option>
                            <option value="cancel-contract">Cancel Contract</option>
                        </select>
                    `;
                }

                let devDisplay = '';
                if (progData.has_programme && progData.developer_name && progData.developer_name !== 'N/A') {
                    devDisplay = `
                        <div class="programme-developer-cell">
                            <div class="dev-name">${this.escapeHtml(progData.developer_name)}</div>
                            ${progData.programme_name ? `<div class="prog-name">${this.escapeHtml(progData.programme_name)}</div>` : ''}
                        </div>
                    `;
                } else {
                    devDisplay = '<span style="color:#888;">N/A</span>';
                }

                html += `
                    <tr class="${clickableClass}" ${dataAttrs}>
                        <td>${devDisplay}</td>
                        <td>
                            <div class="user-cell">
                                <div class="user-name">${this.escapeHtml(user.fullname || 'N/A')}</div>
                                <div class="user-email">${this.escapeHtml(user.email || 'N/A')}</div>
                                <div class="user-id">ID: ${user.id}</div>
                            </div>
                        </td>
                        <td>${this.escapeHtml(user.broker || 'N/A')}</td>
                        <td>${this.escapeHtml(user.login || 'N/A')}</td>
                        <td>$${this.formatNumber(brokerBalance)}</td>
                        <td class="${profitClass}">$${this.formatNumber(profitAndLoss)}</td>
                        <td class="${balanceClass}">$${this.formatNumber(currentBalance)}</td>
                        <td>${devPercent}%</td>
                        <td>$${this.formatNumber(developerAmount)}</td>
                        <td>${invPercent}%</td>
                        <td>$${this.formatNumber(investorAmount)}</td>
                        <td><span class="status-badge ${statusClass}">${status}</span></td>
                        <td>${actionHtml}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        },

        renderCompletedUsers: function() {
            const tbody = document.getElementById('completed-users-body');
            const users = this.filteredCompletedUsers;
            const subTab = this.currentCompletedSubTab;

            if (!users || users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:40px;color:#888;">No completed users found</td></tr>';
                return;
            }

            let html = '';
            users.forEach(user => {
                const profit = parseFloat(user.profitandloss) || 0;
                const profitClass = profit >= 0 ? 'profit' : 'loss';

                const progData = this.getProgrammeDataForUser(user.id);
                const devPercent = progData.developer_percentage;
                const invPercent = progData.investor_percentage;

                let investorAmount = 0, developerAmount = 0;
                if (profit > 0) {
                    investorAmount = (profit * invPercent) / 100;
                    developerAmount = (profit * devPercent) / 100;
                }

                let displayStatus = '';
                let statusClass = '';

                if (subTab === 'inactive-above') { displayStatus = 'Inactive (Above Threshold)'; statusClass = 'status-above'; }
                else if (subTab === 'inactive-below') { displayStatus = 'Inactive (Below Threshold)'; statusClass = 'status-below'; }
                else if (subTab === 'inactive-loss') { displayStatus = 'Inactive (Loss)'; statusClass = 'status-loss'; }
                else {
                    displayStatus = this.getStatusLabel(user.current_loyalties || user.loyalties || '');
                    statusClass = this.getStatusClass(user.current_loyalties || user.loyalties || '');
                }

                let actionHtml = '';
                if (subTab === 'inactive-above') {
                    actionHtml = `
                        <select class="status-select" data-user-id="${user.id}" data-source="${user.source}" onchange="Revenue.updateUserStatus(this)">
                            <option value="">Select Action</option>
                            <option value="unpaid-payment">Mark Unpaid</option>
                        </select>
                    `;
                } else if (subTab === 'inactive-below') {
                    actionHtml = '<span style="color:#888;font-size:12px;">Below threshold</span>';
                } else if (subTab === 'inactive-loss') {
                    actionHtml = '<span style="color:#888;font-size:12px;">Loss completed</span>';
                } else {
                    switch (subTab) {
                        case 'unpaid':
                            actionHtml = `
                                <select class="status-select" data-user-id="${user.id}" data-source="${user.source}" onchange="Revenue.updateUserStatus(this)">
                                    <option value="">Select Status</option>
                                    <option value="payment-made">Payment Made</option>
                                    <option value="payment-confirmed">Payment Confirmed</option>
                                    <option value="failed-payment">Payment Failed</option>
                                    <option value="suspend">Suspend</option>
                                </select>`;
                            break;
                        case 'payment-made':
                            actionHtml = `
                                <select class="status-select" data-user-id="${user.id}" data-source="${user.source}" onchange="Revenue.updateUserStatus(this)">
                                    <option value="">Select Status</option>
                                    <option value="payment-confirmed">Payment Confirmed</option>
                                    <option value="failed-payment">Payment Failed</option>
                                </select>`;
                            break;
                        case 'payment-confirmed':
                            actionHtml = `
                                <select class="status-select" data-user-id="${user.id}" data-source="${user.source}" onchange="Revenue.updateUserStatus(this)">
                                    <option value="">Select Status</option>
                                    <option value="payment-made">Payment Made</option>
                                    <option value="failed-payment">Payment Failed</option>
                                    <option value="unpaid-payment">Unpaid</option>
                                    <option value="suspend">Suspend</option>
                                </select>`;
                            break;
                        case 'failed':
                            actionHtml = `
                                <select class="status-select" data-user-id="${user.id}" data-source="${user.source}" onchange="Revenue.updateUserStatus(this)">
                                    <option value="">Select Status</option>
                                    <option value="payment-confirmed">Payment Confirmed</option>
                                    <option value="payment-made">Payment Made</option>
                                </select>`;
                            break;
                        default:
                            actionHtml = `
                                <select class="status-select" data-user-id="${user.id}" data-source="${user.source}" onchange="Revenue.updateUserStatus(this)">
                                    <option value="">Select Status</option>
                                    <option value="unpaid-payment">Unpaid</option>
                                    <option value="payment-made">Payment Made</option>
                                    <option value="payment-confirmed">Payment Confirmed</option>
                                    <option value="failed-payment">Failed</option>
                                    <option value="suspend">Suspend</option>
                                </select>`;
                    }
                }

                let devDisplay = '';
                if (progData.has_programme && progData.developer_name && progData.developer_name !== 'N/A') {
                    devDisplay = `
                        <div class="programme-developer-cell">
                            <div class="dev-name">${this.escapeHtml(progData.developer_name)}</div>
                            ${progData.programme_name ? `<div class="prog-name">${this.escapeHtml(progData.programme_name)}</div>` : ''}
                        </div>
                    `;
                } else {
                    devDisplay = '<span style="color:#888;">N/A</span>';
                }

                html += `
                    <tr>
                        <td>${devDisplay}</td>
                        <td>
                            <div class="user-cell">
                                <div class="user-name">${this.escapeHtml(user.fullname || 'N/A')}</div>
                                <div class="user-email">${this.escapeHtml(user.email || 'N/A')}</div>
                                <div class="user-id">ID: ${user.id}</div>
                            </div>
                        </td>
                        <td>${this.escapeHtml(user.broker || 'N/A')}</td>
                        <td>${this.escapeHtml(user.login || 'N/A')}</td>
                        <td>${this.escapeHtml(user.invested_with || 'N/A')}</td>
                        <td class="${profitClass}">$${this.formatNumber(profit)}</td>
                        <td>${devPercent}%</td>
                        <td>$${this.formatNumber(developerAmount)}</td>
                        <td>${invPercent}%</td>
                        <td>$${this.formatNumber(investorAmount)}</td>
                        <td><span class="status-badge ${statusClass}">${displayStatus}</span></td>
                        <td>${actionHtml}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        },

        renderInactiveUsers: function() {
            const tbody = document.getElementById('inactive-users-body');
            const users = this.filteredInactiveUsers;

            if (!users || users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#888;">No inactive users found</td></tr>';
                return;
            }

            let html = '';
            users.forEach(user => {
                const brokerBalance = parseFloat(user.broker_balance) || 0;
                const profitAndLoss = parseFloat(user.profitandloss) || 0;
                const profitClass = profitAndLoss >= 0 ? 'profit' : 'loss';

                const progData = this.getProgrammeDataForUser(user.id);

                let status = 'Inactive';
                let statusClass = 'status-inactive';

                const execDate = user.execution_start_date;
                const loyalties = (user.loyalties || '');
                const duration = progData.contract_duration;

                if (Revenue.loyaltyIsFamily(loyalties, 'cancelled')) {
                    status = 'Cancelled'; statusClass = 'status-cancelled';
                } else if (!execDate || execDate === '0000-00-00' || execDate === null) {
                    status = 'No Contract'; statusClass = 'status-no-contract';
                } else {
                    const start = new Date(execDate);
                    const end = new Date(start);
                    end.setDate(end.getDate() + duration);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    end.setHours(0, 0, 0, 0);
                    if (today > end) { status = 'Contract Expired'; statusClass = 'status-expired'; }
                }

                let devDisplay = '';
                if (progData.has_programme && progData.developer_name && progData.developer_name !== 'N/A') {
                    devDisplay = `
                        <div class="programme-developer-cell">
                            <div class="dev-name">${this.escapeHtml(progData.developer_name)}</div>
                            ${progData.programme_name ? `<div class="prog-name">${this.escapeHtml(progData.programme_name)}</div>` : ''}
                        </div>
                    `;
                } else {
                    devDisplay = '<span style="color:#888;">N/A</span>';
                }

                html += `
                    <tr>
                        <td>${devDisplay}</td>
                        <td>
                            <div class="user-cell">
                                <div class="user-name">${this.escapeHtml(user.fullname || 'N/A')}</div>
                                <div class="user-email">${this.escapeHtml(user.email || 'N/A')}</div>
                                <div class="user-id">ID: ${user.id}</div>
                            </div>
                        </td>
                        <td>${this.escapeHtml(user.broker || 'N/A')}</td>
                        <td>${this.escapeHtml(user.login || 'N/A')}</td>
                        <td>$${this.formatNumber(brokerBalance)}</td>
                        <td class="${profitClass}">$${this.formatNumber(profitAndLoss)}</td>
                        <td><span class="status-badge ${statusClass}">${status}</span></td>
                        <td>
                            <select class="action-select" data-user-id="${user.id}" data-source="${user.source || 'harvhub'}" onchange="Revenue.handleInactiveAction(this)">
                                <option value="">Remain Inactive</option>
                                <option value="initialize-enrollment">Initialize Enrollment</option>
                            </select>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        },

        // ============================================
        // ACTION HANDLERS
        // ============================================
        handleActiveAction: function(selectElement) {
            const userId = selectElement.dataset.userId;
            const source = selectElement.dataset.source;
            const action = selectElement.value;
            if (!action) return;
            if (action === 'cancel-contract') this.cancelContract(userId, source);
            selectElement.value = '';
        },
        handleUnusualAction: function(selectElement) {
            const userId = selectElement.dataset.userId;
            const source = selectElement.dataset.source;
            const action = selectElement.value;
            if (!action) return;
            if (action === 'cancel-contract') this.cancelContract(userId, source);
            selectElement.value = '';
        },
        handleInactiveAction: function(selectElement) {
            const userId = selectElement.dataset.userId;
            const source = selectElement.dataset.source;
            const action = selectElement.value;
            if (!action) return;
            if (action === 'initialize-enrollment') this.showInitializeEnrollmentrevenuemodal(userId, source);
            selectElement.value = '';
        },

        showInitializeEnrollmentrevenuemodal: function(userId, source) {
            const user = this.allInactiveUsers.find(u => u.id == userId && u.source === source);
            if (!user) {
                this.showNotification('User not found', 'Error', true);
                return;
            }
            document.getElementById('init-enroll-user-name').textContent = user.fullname || 'User #' + userId;
            document.getElementById('init-enroll-broker-balance').value = user.broker_balance || '';
            document.getElementById('init-enroll-error').style.display = 'none';
            this._initEnrollCallback = { userId, source };
            document.getElementById('initialize-enrollment-revenuemodal').style.display = 'flex';
            setTimeout(() => document.getElementById('init-enroll-broker-balance').focus(), 100);
        },
        closeInitializeEnrollmentrevenuemodal: function() {
            document.getElementById('initialize-enrollment-revenuemodal').style.display = 'none';
            this._initEnrollCallback = null;
            document.getElementById('init-enroll-error').style.display = 'none';
        },
        confirmInitializeEnrollment: function() {
            const brokerBalance = parseFloat(document.getElementById('init-enroll-broker-balance').value);
            const errorEl = document.getElementById('init-enroll-error');

            if (isNaN(brokerBalance) || brokerBalance < 0) {
                errorEl.textContent = 'Please enter a valid broker balance.';
                errorEl.style.display = 'block';
                return;
            }
            if (brokerBalance < this.minBrokerBalance) {
                errorEl.textContent = 'Broker balance must be at least $' + this.minBrokerBalance.toFixed(2) + '.';
                errorEl.style.display = 'block';
                return;
            }
            const cb = this._initEnrollCallback;
            this.closeInitializeEnrollmentrevenuemodal();
            if (cb) this.confirmInitializeEnrollmentWithPassword(cb.userId, cb.source, brokerBalance);
        },
        confirmInitializeEnrollmentWithPassword: function(userId, source, brokerBalance) {
            const self = this;
            this.showPasswordrevenuemodal(
                'Initialize Enrollment',
                'Enter admin password to initialize enrollment for User ID ' + userId,
                function(password) {
                    const loginId = document.getElementById('login-id-hidden')?.value || '';
                    if (!password) { self.showNotification('Password is required', 'Error', true); return; }

                    const confirmBtn = document.getElementById('password-revenuemodal-confirm-btn');
                    const originalText = confirmBtn.textContent;
                    confirmBtn.textContent = 'Processing...';
                    confirmBtn.disabled = true;

                    fetch(window.location.pathname, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: 'action=initialize_enrollment&user_id=' + userId + '&source_table=' + source + '&broker_balance=' + brokerBalance + '&admin_password=' + encodeURIComponent(password) + '&login_id=' + encodeURIComponent(loginId)
                    })
                    .then(r => r.json())
                    .then(data => {
                        confirmBtn.textContent = originalText;
                        confirmBtn.disabled = false;
                        if (data.success) {
                            self.showNotification('Enrollment initialized successfully!', 'Success', false);
                            self.loadInactiveUsers();
                            self.loadActiveUsers();
                            self.loadCompletedUsers();
                            self.loadRevenueHistoryUsers();
                        } else {
                            if (data.error === 'Invalid password') {
                                self.showNotification('Password verification failed. Please try again.', 'Error', true);
                            } else {
                                self.showNotification('Error: ' + (data.error || data.message || 'Unknown error'), 'Error', true);
                            }
                        }
                    })
                    .catch(error => {
                        confirmBtn.textContent = originalText;
                        confirmBtn.disabled = false;
                        self.showNotification('Error: ' + error.message, 'Error', true);
                    });
                }
            );
        },

        cancelContract: function(userId, source) {
            const self = this;
            this.showConfirmrevenuemodal(
                'Cancel Contract',
                'Are you sure you want to cancel the contract for User ID ' + userId + '? This action cannot be undone.',
                function() {
                    self.showPasswordrevenuemodal(
                        'Cancel Contract',
                        'Enter admin password to cancel contract for User ID ' + userId,
                        function(password) {
                            const loginId = document.getElementById('login-id-hidden')?.value || '';
                            if (!password) { self.showNotification('Password is required', 'Error', true); return; }

                            const confirmBtn = document.getElementById('password-revenuemodal-confirm-btn');
                            const originalText = confirmBtn.textContent;
                            confirmBtn.textContent = 'Processing...';
                            confirmBtn.disabled = true;

                            fetch(window.location.pathname, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: 'action=cancel_contract&user_id=' + userId + '&source_table=' + source + '&admin_password=' + encodeURIComponent(password) + '&login_id=' + encodeURIComponent(loginId)
                            })
                            .then(r => r.json())
                            .then(data => {
                                confirmBtn.textContent = originalText;
                                confirmBtn.disabled = false;
                                if (data.success) {
                                    self.showNotification('Contract cancelled successfully! User moved to inactive tab.', 'Success', false);
                                    self.loadActiveUsers();
                                    self.loadCompletedUsers();
                                    self.loadRevenueHistoryUsers();
                                    self.loadInactiveUsers();
                                    if (self.currentActiveSubTab === 'unusual') self.loadUnusualUsers();
                                } else {
                                    if (data.error === 'Invalid password') {
                                        self.showNotification('Password verification failed. Please try again.', 'Error', true);
                                    } else {
                                        self.showNotification('Error: ' + (data.error || data.message || 'Unknown error'), 'Error', true);
                                    }
                                }
                            })
                            .catch(error => {
                                confirmBtn.textContent = originalText;
                                confirmBtn.disabled = false;
                                self.showNotification('Error: ' + error.message, 'Error', true);
                            });
                        }
                    );
                }
            );
        },

        suspendUser: function(userId, source) {
            const self = this;
            this.showConfirmrevenuemodal(
                'Suspend User',
                'Are you sure you want to suspend User ID ' + userId + '?',
                function() {
                    self.showPasswordrevenuemodal(
                        'Suspend User',
                        'Enter admin password to suspend User ID ' + userId,
                        function(password) {
                            const loginId = document.getElementById('login-id-hidden')?.value || '';
                            if (!password) { self.showNotification('Password is required', 'Error', true); return; }

                            const confirmBtn = document.getElementById('password-revenuemodal-confirm-btn');
                            const originalText = confirmBtn.textContent;
                            confirmBtn.textContent = 'Processing...';
                            confirmBtn.disabled = true;

                            fetch(window.location.pathname, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: 'action=update_application_status_batch&user_id=' + userId + '&source_table=' + source + '&application_status=suspended&admin_password=' + encodeURIComponent(password) + '&login_id=' + encodeURIComponent(loginId)
                            })
                            .then(r => r.json())
                            .then(data => {
                                confirmBtn.textContent = originalText;
                                confirmBtn.disabled = false;
                                if (data.success) {
                                    self.showNotification('User suspended successfully!', 'Success', false);
                                    self.loadCompletedUsers();
                                } else {
                                    if (data.error === 'Invalid password') {
                                        self.showNotification('Password verification failed. Please try again.', 'Error', true);
                                    } else {
                                        self.showNotification('Error: ' + (data.error || 'Unknown error'), 'Error', true);
                                    }
                                }
                            })
                            .catch(error => {
                                confirmBtn.textContent = originalText;
                                confirmBtn.disabled = false;
                                self.showNotification('Error: ' + error.message, 'Error', true);
                            });
                        }
                    );
                }
            );
        },

        // ============================================
        // VIEW USER DETAIL
        // ============================================
        viewUserDetail: function(userId, source) {
            this.selectedUserId = userId;
            this.selectedUserSource = source || 'harvhub';
            this.isDetailViewOpen = true;

            const overlay = document.getElementById('user-detail-overlay');
            overlay.style.display = 'block';
            document.body.style.overflow = 'hidden';

            document.getElementById('detail-user-name').textContent = 'User Details - ID: ' + userId;
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
                        <div class="empty-icon">x</div>
                        <div class="empty-text">Error loading user details</div>
                        <div class="empty-sub">${error.message || 'Please try again'}</div>
                    </div>
                `;
            });
        },

        // ============================================
        // RENDER USER DETAIL (now table-based)
        // ============================================
        renderUserDetail: function(detailData, basicData) {
            const container = document.getElementById('detail-overlay-body');

            const user = basicData.user || {};

            // detailData.log   = daily_target_revenue (nested { week_X: { Day: {...} } })
            // detailData.balance_log = balance_log keyed by dd-mm-yyyy
            const dailyTargetData = (detailData && typeof detailData.log === 'object' && detailData.log !== null)
                ? detailData.log : {};
            const dailyLogData = (detailData && typeof detailData.balance_log === 'object' && detailData.balance_log !== null)
                ? detailData.balance_log : {};

            const progData = this.getProgrammeDataForUser(user.id);

            const brokerBalance = parseFloat(user.broker_balance) || 0;
            const profitAndLoss = parseFloat(user.profitandloss) || 0;
            const currentBalance = brokerBalance + profitAndLoss;
            const isAboveThreshold = profitAndLoss > this.minProfitForSplit;
            const isInProfit = profitAndLoss > 0;

            const weekKeys = Object.keys(dailyTargetData).filter(k => k.startsWith('week_')).sort((a, b) => {
                const na = parseInt(a.replace('week_', '')) || 0;
                const nb = parseInt(b.replace('week_', '')) || 0;
                return na - nb;
            });

            const logDates = Object.keys(dailyLogData).sort((a, b) => {
                const da = Revenue.parseDMY(a);
                const db = Revenue.parseDMY(b);
                if (da && db) return db - da;
                return b.localeCompare(a);
            });

            let totalDays = 0, totalMet = 0, totalOwed = 0, totalPending = 0, totalNotListed = 0;
            weekKeys.forEach(wk => {
                const wd = dailyTargetData[wk];
                if (wd && typeof wd === 'object' && !Array.isArray(wd)) {
                    Object.keys(wd).forEach(day => {
                        const dd = wd[day];
                        totalDays++;
                        if (dd.status === 'met') totalMet++;
                        else if (dd.status === 'owed') totalOwed++;
                        else if (dd.status === 'pending') totalPending++;
                        else if (dd.status === 'not_listed') totalNotListed++;
                    });
                }
            });

            const unusualDays = Object.values(dailyLogData).filter(d => d && d.unusual_activity).length;

            let html = `
                <div class="user-detail-grid">
                    <div class="detail-card-full">
                        <div class="detail-user-header">
                            <div>
                                <h3>${this.escapeHtml(user.fullname || 'N/A')}</h3>
                                <p class="detail-user-email">${this.escapeHtml(user.email || 'N/A')}</p>
                                <p class="detail-user-id">ID: ${user.id} | Source: ${this.escapeHtml(user.source || 'N/A')}</p>
                                ${progData.has_programme ? `
                                    <p class="detail-programme-info" style="margin-top:8px;font-size:12px;color:#888;">
                                        <strong>Developer:</strong> ${this.escapeHtml(progData.developer_name)}
                                        ${progData.programme_name ? ` | <strong>Programme:</strong> ${this.escapeHtml(progData.programme_name)}` : ''}
                                        | <strong>Duration:</strong> ${progData.contract_duration} days
                                        | <strong>Dev %:</strong> ${progData.developer_percentage}%
                                        | <strong>Inv %:</strong> ${progData.investor_percentage}%
                                    </p>
                                ` : ''}
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
                            <div class="stat-label">Total Days</div>
                            <div class="stat-value">${totalDays}</div>
                        </div>
                        <div class="detail-stat-card">
                            <div class="stat-label">Met</div>
                            <div class="stat-value" style="color:#4caf50;">${totalMet}</div>
                        </div>
                        <div class="detail-stat-card">
                            <div class="stat-label">Owed</div>
                            <div class="stat-value" style="color:#ff9800;">${totalOwed}</div>
                        </div>
                        <div class="detail-stat-card">
                            <div class="stat-label">Pending</div>
                            <div class="stat-value" style="color:#2196f3;">${totalPending}</div>
                        </div>
                        <div class="detail-stat-card">
                            <div class="stat-label">Unusual Activity Days</div>
                            <div class="stat-value ${unusualDays > 0 ? 'unusual' : ''}">${unusualDays}</div>
                        </div>
                    </div>

                    <div class="detail-tabs-wrapper">
                        <div class="detail-tabs">
                            <button class="detail-tab-btn active" data-detail-tab="daily-target" onclick="Revenue.switchDetailTab('daily-target')">
                                Daily Target
                                <span class="tab-badge">${weekKeys.length} weeks</span>
                            </button>
                            <button class="detail-tab-btn" data-detail-tab="balance-log" onclick="Revenue.switchDetailTab('balance-log')">
                                Balance Log
                                <span class="tab-badge">${logDates.length}</span>
                            </button>
                        </div>
                    </div>

                    <div id="detail-tab-daily-target" class="detail-tab-content active">
            `;

            if (weekKeys.length > 0) {
                html += `<div class="detail-section"><div class="section-content"><div class="weekly-target-container">`;

                weekKeys.forEach(weekKey => {
                    const weekData = dailyTargetData[weekKey];
                    if (typeof weekData !== 'object' || Array.isArray(weekData)) return;

                    const weekDays = Object.keys(weekData);
                    const weekMet = weekDays.filter(d => weekData[d].status === 'met').length;
                    const weekOwed = weekDays.filter(d => weekData[d].status === 'owed').length;
                    const weekPending = weekDays.filter(d => weekData[d].status === 'pending').length;

                    html += `
                        <div class="week-container">
                            <div class="week-header">
                                <span class="week-label">${weekKey.replace('_', ' ').toUpperCase()}</span>
                                <span class="week-summary">
                                    Met: ${weekMet}  Owed: ${weekOwed}  Pending: ${weekPending}
                                </span>
                            </div>
                            <div class="daily-target-list">
                    `;

                    weekDays.forEach(day => {
                        const dayData = weekData[day];
                        const status = dayData.status || 'unknown';
                        const target = parseFloat(dayData.daily_target) || 0;
                        const allocated = parseFloat(dayData.profit_allocated) || 0;
                        const remaining = parseFloat(dayData.remaining_needed);
                        const dateStr = dayData.date || '';

                        let isUnusual = false;
                        if (dateStr) {
                            const parts = dateStr.split('-'); // YYYY-MM-DD
                            if (parts.length === 3) {
                                const logKey = parts[2] + '-' + parts[1] + '-' + parts[0]; // dd-mm-yyyy
                                if (dailyLogData[logKey] && dailyLogData[logKey].unusual_activity) isUnusual = true;
                            }
                        }

                        const statusClass = status === 'met' ? 'status-met' : status === 'owed' ? 'status-owed' : 'status-pending';
                        const hasRemaining = !isNaN(remaining) && remaining > 0 && status === 'owed';
                        const remainingDisplay = hasRemaining ? '$' + this.formatNumber(remaining) : '$0.00';

                        html += `
                            <div class="daily-target-item ${isUnusual ? 'unusual' : ''}">
                                <div class="target-left">
                                    <div class="target-day">${this.escapeHtml(day)}</div>
                                    <div class="target-date">${this.escapeHtml(dateStr)}</div>
                                    ${isUnusual ? '<span class="status-badge status-unusual" style="font-size:9px;">Unusual</span>' : ''}
                                </div>
                                <div class="target-right">
                                    <div class="target-row">
                                        <span class="target-label">Target</span>
                                        <span class="target-value">$${this.formatNumber(target)}</span>
                                    </div>
                                    <div class="target-row">
                                        <span class="target-label">Allocated</span>
                                        <span class="target-value">$${this.formatNumber(allocated)}</span>
                                    </div>
                                    <div class="target-row">
                                        <span class="target-label">Remaining</span>
                                        <span class="target-value ${hasRemaining ? 'remaining' : ''}">${remainingDisplay}</span>
                                    </div>
                                    <div class="target-row">
                                        <span class="target-label">Status</span>
                                        <span class="target-value"><span class="status-badge ${statusClass}" style="font-size:10px;">${String(status).toUpperCase()}</span></span>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    html += `</div></div>`;
                });

                html += `</div></div></div>`;
            } else {
                html += `
                    <div class="empty-state">
                        <div class="empty-icon">-</div>
                        <div class="empty-text">No Daily Target Data</div>
                        <div class="empty-sub">This user has no daily target records.</div>
                    </div>
                `;
            }

            html += `</div><div id="detail-tab-balance-log" class="detail-tab-content" style="display:none;">`;

            if (logDates.length > 0) {
                html += `<div class="detail-section"><div class="section-content"><div class="balance-log-list">`;

                logDates.forEach(date => {
                    const dayData = dailyLogData[date];
                    if (!dayData) return;

                    const dateObj = Revenue.parseDMY(date);
                    const dayName = dateObj ? dateObj.toLocaleDateString('en-US', { weekday: 'long' }) : '';
                    const formattedDate = dateObj
                        ? dateObj.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })
                        : date;

                    const isUnusual = !!dayData.unusual_activity;
                    const hasWithdrawals = parseFloat(dayData.day_unauthorized_withdrawals) > 0;
                    const hasUnauthorizedTrades = (parseInt(dayData.unauthorized_trades_count) || 0) > 0;

                    let unusualBadge = '';
                    if (isUnusual) {
                        const badges = [];
                        if (hasWithdrawals) badges.push('Withdrawal');
                        if (hasUnauthorizedTrades) badges.push('Trades');
                        unusualBadge = `<span class="status-badge status-unusual">${badges.join(' + ') || 'Unusual'}</span>`;
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
                                    <span class="log-label">Authorized Trades Count</span>
                                    <span class="log-value">${parseInt(dayData.authorized_trades_count) || 0}</span>
                                </div>
                                <div class="log-row">
                                    <span class="log-label">Unauthorized Trades Count</span>
                                    <span class="log-value">${parseInt(dayData.unauthorized_trades_count) || 0}</span>
                                </div>
                                <div class="log-row">
                                    <span class="log-label">Unusual Activity</span>
                                    <span class="log-value ${isUnusual ? 'unusual' : ''}">${isUnusual ? 'Yes' : 'No'}</span>
                                </div>
                            </div>
                        </div>
                    `;
                });

                html += `</div></div></div>`;
            } else {
                html += `
                    <div class="empty-state">
                        <div class="empty-icon">-</div>
                        <div class="empty-text">No Balance Log Data</div>
                        <div class="empty-sub">This user has no balance log records.</div>
                    </div>
                `;
            }

            html += `</div></div>`;

            container.innerHTML = html;
        },

        parseDMY: function(s) {
            if (!s) return null;
            const m = String(s).match(/^(\d{2})-(\d{2})-(\d{4})$/);
            if (m) return new Date(parseInt(m[3]), parseInt(m[2]) - 1, parseInt(m[1]));
            const m2 = String(s).match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (m2) return new Date(parseInt(m2[1]), parseInt(m2[2]) - 1, parseInt(m2[3]));
            const d = new Date(s);
            return isNaN(d.getTime()) ? null : d;
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

            const self = this;
            this.showConfirmrevenuemodal(
                'Update Status',
                'Update status to "' + newStatus + '" for User ID ' + userId + '?',
                function() {
                    self.showPasswordrevenuemodal(
                        'Update Status',
                        'Enter admin password to update status for User ID ' + userId,
                        function(password) {
                            const loginId = document.getElementById('login-id-hidden')?.value || '';
                            if (!password) { self.showNotification('Password is required', 'Error', true); return; }

                            const confirmBtn = document.getElementById('password-revenuemodal-confirm-btn');
                            const originalText = confirmBtn.textContent;
                            confirmBtn.textContent = 'Processing...';
                            confirmBtn.disabled = true;

                            fetch(window.location.pathname, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: 'action=update_payment_status&user_id=' + userId + '&source_table=' + source + '&payment_status=' + encodeURIComponent(newStatus) + '&admin_password=' + encodeURIComponent(password) + '&login_id=' + encodeURIComponent(loginId)
                            })
                            .then(r => r.json())
                            .then(data => {
                                confirmBtn.textContent = originalText;
                                confirmBtn.disabled = false;
                                if (data.success) {
                                    self.showNotification('Status updated successfully!', 'Success', false);
                                    self.loadCompletedUsers();
                                    self.loadRevenueHistoryUsers();
                                } else {
                                    if (data.error === 'Invalid password') {
                                        self.showNotification('Password verification failed. Please try again.', 'Error', true);
                                    } else {
                                        self.showNotification('Error: ' + (data.error || data.message || 'Unknown error'), 'Error', true);
                                    }
                                    selectElement.disabled = false;
                                    selectElement.style.opacity = '1';
                                }
                            })
                            .catch(error => {
                                confirmBtn.textContent = originalText;
                                confirmBtn.disabled = false;
                                self.showNotification('Error: ' + error.message, 'Error', true);
                                selectElement.disabled = false;
                                selectElement.style.opacity = '1';
                            });
                        }
                    );
                }
            );
        },

        // ============================================
        // SUB TAB SWITCHERS
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

        updateUnusualBadges: function() {
            const allCount = this.unusualUsers.length;
            const withdrawalCount = this.unusualUsers.filter(u => (u.withdrawal_count || 0) > 0).length;
            const tradeCount = this.unusualUsers.filter(u => (u.unauthorized_trade_count || 0) > 0).length;

            this.updateBadge('unusual-all-count', allCount);
            this.updateBadge('unusual-withdrawals-count', withdrawalCount);
            this.updateBadge('unusual-trades-count', tradeCount);
            this.updateBadge('active-unusual-count', allCount);
        },

        switchInactiveSubTab: function(subTab) {
            this.currentInactiveSubTab = subTab;
            document.querySelectorAll('#inactive-sub-tabs .sub-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.subtab === subTab);
            });
            this.filteredInactiveUsers = this.getFilteredInactiveUsers();
            this.renderInactiveUsers();
            this.updateInactiveCubes();
        },

        // ============================================
        // CUBES
        // ============================================
        updateActiveCubes: function() {
            const users = this.filteredActiveUsers;
            let totalInvestment = 0, totalPnl = 0, totalBalance = 0;
            let totalUserShare = 0, totalServerShare = 0, totalInProfit = 0;

            users.forEach(user => {
                const bb = parseFloat(user.broker_balance) || 0;
                const pnl = parseFloat(user.profitandloss) || 0;
                totalInvestment += bb;
                totalPnl += pnl;
                totalBalance += bb + pnl;

                if (pnl > 0) {
                    totalInProfit++;
                    const progData = this.getProgrammeDataForUser(user.id);
                    totalServerShare += (pnl * progData.developer_percentage) / 100;
                    totalUserShare += (pnl * progData.investor_percentage) / 100;
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
                profitEl.style.color = totalInProfit > 0 ? '#4caf50' : 'var(--text-color, #ffffff)';
            }
        },

        updateInactiveCubes: function() {
            const users = this.filteredInactiveUsers;
            let totalInvestment = 0, totalPnl = 0, totalBalance = 0;

            users.forEach(user => {
                const bb = parseFloat(user.broker_balance) || 0;
                const pnl = parseFloat(user.profitandloss) || 0;
                totalInvestment += bb;
                totalPnl += pnl;
                totalBalance += bb + pnl;
            });

            this.updateCubeValue('inactive-total-investment', totalInvestment);
            this.updateCubeValue('inactive-total-pnl', totalPnl);
            this.updateCubeValue('inactive-current-balance', totalBalance);

            const countEl = document.getElementById('inactive-count-total');
            if (countEl) {
                countEl.textContent = users.length;
                countEl.style.color = 'var(--text-color, #ffffff)';
            }
        },

        updateBadge: function(id, count) {
            const el = document.getElementById(id);
            if (el) el.textContent = count || 0;
        },

        updateActiveBadges: function() {
            const users = this.allActiveUsers;
            const counts = { all: users.length, unusual: 0, 'above-threshold': 0, 'below-threshold': 0, profit: 0, loss: 0 };

            users.forEach(u => {
                const pnl = parseFloat(u.profitandloss) || 0;
                if (pnl > this.minProfitForSplit) counts['above-threshold']++;
                else if (pnl > 0 && pnl <= this.minProfitForSplit) counts['below-threshold']++;
                if (pnl > 0) counts.profit++;
                else if (pnl < 0) counts.loss++;
            });

            this.updateBadge('active-unusual-count', this.unusualUsers.length || 0);
            Object.keys(counts).forEach(k => {
                if (k !== 'unusual') this.updateBadge('active-' + k + '-count', counts[k]);
            });
            this.updateBadge('active-count', users.length);
        },

        updateCompletedBadges: function() {
            const users = this.allCompletedUsers;
            const counts = { 'inactive-above': 0, 'inactive-below': 0, 'inactive-loss': 0, unpaid: 0, 'payment-made': 0, 'payment-confirmed': 0, failed: 0 };

            const isContractEnded = (user) => {
                const e = user.execution_start_date;
                if (!e || e === '0000-00-00' || e === null) return false;
                const duration = this.getContractDurationForUser(user.id);
                const start = new Date(e);
                const end = new Date(start); end.setDate(end.getDate() + duration);
                const today = new Date(); today.setHours(0, 0, 0, 0); end.setHours(0, 0, 0, 0);
                return today > end;
            };
            const isCancelled = (user) => Revenue.loyaltyIsFamily(user.current_loyalties || user.loyalties || '', 'cancelled');
            const hasPaymentStatus = (user) => {
                const l = user.current_loyalties || user.loyalties || '';
                return Revenue.loyaltyIsFamily(l, 'payment-confirmed')
                    || Revenue.loyaltyIsFamily(l, 'payment-made')
                    || Revenue.loyaltyIsFamily(l, 'unpaid')
                    || Revenue.loyaltyIsFamily(l, 'failed');
            };

            users.forEach(u => {
                const p = parseFloat(u.profitandloss) || 0;
                const l = u.current_loyalties || u.loyalties || '';
                if (isContractEnded(u)) {
                    if (isCancelled(u) && p > this.minProfitForSplit) counts['inactive-above']++;
                    else if (!hasPaymentStatus(u) && !isCancelled(u)) {
                        if (p > this.minProfitForSplit) counts['inactive-above']++;
                        else if (p > 0 && p <= this.minProfitForSplit) counts['inactive-below']++;
                        else if (p < 0) counts['inactive-loss']++;
                    }
                }
                if (Revenue.loyaltyIsFamily(l, 'unpaid')) counts.unpaid++;
                else if (Revenue.loyaltyIsFamily(l, 'payment-made')) counts['payment-made']++;
                else if (Revenue.loyaltyIsFamily(l, 'payment-confirmed')) counts['payment-confirmed']++;
                else if (Revenue.loyaltyIsFamily(l, 'failed')) counts.failed++;
            });

            Object.keys(counts).forEach(k => this.updateBadge(k + '-count', counts[k]));
        },

        updateInactiveBadges: function() {
            const users = this.allInactiveUsers;
            const counts = { all: users.length, 'no-contract': 0, expired: 0, cancelled: 0 };

            users.forEach(u => {
                const e = u.execution_start_date;
                const l = u.loyalties || '';
                if (Revenue.loyaltyIsFamily(l, 'cancelled')) counts.cancelled++;
                else if (!e || e === '0000-00-00' || e === null) counts['no-contract']++;
                else {
                    const duration = this.getContractDurationForUser(u.id);
                    const start = new Date(e);
                    const end = new Date(start); end.setDate(end.getDate() + duration);
                    const today = new Date(); today.setHours(0, 0, 0, 0); end.setHours(0, 0, 0, 0);
                    if (today > end) counts.expired++;
                }
            });

            Object.keys(counts).forEach(k => this.updateBadge('inactive-' + k + '-count', counts[k]));
            this.updateBadge('inactive-count', users.length);
        },

        // ============================================
        // STATUS HELPERS
        // ============================================
        getStatusLabel: function(status) {
            const n = this.normalizeLoyaltyStatus(status);
            switch (n) {
                case 'payment-confirmed': return 'Payment Confirmed';
                case 'payment-made': return 'Payment Made';
                case 'unpaid-payment': return 'Unpaid';
                case 'failed-payment': return 'Failed';
                case 'contract-cancelled-payment-confirmed': return 'Cancelled (Payment Confirmed)';
                case 'contract-cancelled-payment-made': return 'Cancelled (Payment Made)';
                case 'contract-cancelled-unpaid-payment': return 'Cancelled (Unpaid)';
                case 'contract-cancelled-failed-payment': return 'Cancelled (Failed)';
                case 'contract_cancelled': return 'Cancelled';
                case 'loss_completed': return 'Loss Completed';
                case 'below_threshold': return 'Below Threshold';
                case 'active': return 'Active Contract';
                default: return status || 'Unknown';
            }
        },
        getStatusClass: function(status) {
            const n = this.normalizeLoyaltyStatus(status);
            if (n === 'payment-confirmed' || n === 'contract-cancelled-payment-confirmed') return 'status-confirmed';
            if (n === 'payment-made' || n === 'contract-cancelled-payment-made') return 'status-made';
            if (n === 'unpaid-payment' || n === 'contract-cancelled-unpaid-payment') return 'status-unpaid';
            if (n === 'failed-payment' || n === 'contract-cancelled-failed-payment') return 'status-failed';
            if (n === 'contract_cancelled') return 'status-cancelled';
            if (n === 'loss_completed') return 'status-loss';
            if (n === 'below_threshold') return 'status-below';
            if (n === 'active') return 'active';
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
            } else if (tab === 'inactive') {
                this.filteredInactiveUsers = this.getFilteredInactiveUsers();
                this.renderInactiveUsers();
                this.updateInactiveCubes();
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
        // UTILS
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
                return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
            } catch (e) { return dateStr; }
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

    document.addEventListener('DOMContentLoaded', function() {
        Revenue.init();
    });
</script>