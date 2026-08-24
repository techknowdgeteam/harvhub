<?php
// old_revenue.php - COMPLETE FIXED VERSION
// All issues addressed: Broker Balance, P&L, Current Balance display
// Expected payments only on positive profit
// Active investors showing balances
// Completed investors summary cards
?>

<!-- Revenue Navigation Tabs -->
<div class="revenue-tabs">
    <button class="revenue-tab-btn active" data-revenue-tab="current">💰 Current Revenue</button>
    <button class="revenue-tab-btn" data-revenue-tab="active">📈 Active Investors</button>
    <button class="revenue-tab-btn" data-revenue-tab="completed">📊 Completed Investors</button>
</div>

<!-- Current Revenue Tab -->
<div id="current-revenue-tab" class="revenue-tab active-tab">
    <h2 style="font-size: 18px; margin-bottom: 15px;"> Revenue & Users Dashboard <span class="live-badge"></span></h2>

    <!-- Revenue Summary Cards with proper color coding -->
    <div class="revenue-summary" style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
        <div class="summary-card" style="flex: 1; min-width: 120px; padding: 12px;">
            <div class="label" style="font-size: 11px;">Total Broker Balance</div>
            <div class="value" style="font-size: 16px;" id="total-broker-balance" data-value="<?= $revenueSummary['total_broker_balance'] ?? 0 ?>">
                <?= format_currency($revenueSummary['total_broker_balance'] ?? 0) ?>
            </div>
        </div>
        <div class="summary-card" style="flex: 1; min-width: 120px; padding: 12px;">
            <div class="label" style="font-size: 11px;">Total P&L</div>
            <div class="value <?= ($revenueSummary['total_profit'] ?? 0) >= 0 ? 'profit' : 'loss' ?>" style="font-size: 16px;" id="total-profit" data-value="<?= $revenueSummary['total_profit'] ?? 0 ?>">
                <?= format_currency($revenueSummary['total_profit'] ?? 0) ?>
            </div>
        </div>
        <div class="summary-card" style="flex: 1; min-width: 120px; padding: 12px;">
            <div class="label" style="font-size: 11px;">Current Balance</div>
            <div class="value <?= ($revenueSummary['total_current_balance'] ?? 0) >= 0 ? 'profit' : 'loss' ?>" style="font-size: 16px;" id="total-current-balance" data-value="<?= $revenueSummary['total_current_balance'] ?? 0 ?>">
                <?= format_currency($revenueSummary['total_current_balance'] ?? 0) ?>
            </div>
        </div>
        <div class="summary-card" style="flex: 1; min-width: 120px; padding: 12px;">
            <div class="label" style="font-size: 11px;">User Share Total</div>
            <div class="value" style="font-size: 16px;" id="total-user-share" data-value="<?= $revenueSummary['total_user_share'] ?? 0 ?>">
                <?= format_currency($revenueSummary['total_user_share'] ?? 0) ?>
            </div>
            <div class="sub" style="font-size: 9px;">User Share (<?= $serverAccount['user_share_percent'] ?? 70 ?>%)</div>
        </div>
        <div class="summary-card warning" style="flex: 1; min-width: 120px; padding: 12px;">
            <div class="label" style="font-size: 11px;">Expected Payments</div>
            <div class="value" style="font-size: 16px;" id="total-expected-payments" data-value="<?= $revenueSummary['total_unpaid_payments'] ?? 0 ?>">
                <?= format_currency($revenueSummary['total_unpaid_payments'] ?? 0) ?>
            </div>
            <div class="sub" style="font-size: 9px;">Expected Server Share</div>
        </div>
        <div class="summary-card payments-made" style="flex: 1; min-width: 120px; padding: 12px;">
            <div class="label" style="font-size: 11px;">Payments Made</div>
            <div class="value" style="font-size: 16px;" id="total-payments-made" data-value="<?= $revenueSummary['total_payments_made'] ?? 0 ?>">
                <?= format_currency($revenueSummary['total_payments_made'] ?? 0) ?>
            </div>
            <div class="sub" style="font-size: 9px;">Payment Made</div>
        </div>
        <div class="summary-card payments-received" style="flex: 1; min-width: 120px; padding: 12px;">
            <div class="label" style="font-size: 11px;">Payments Confirmed</div>
            <div class="value" style="font-size: 16px;" id="total-payments-received" data-value="<?= $revenueSummary['total_payments_received'] ?? 0 ?>">
                <?= format_currency($revenueSummary['total_payments_received'] ?? 0) ?>
            </div>
            <div class="sub" style="font-size: 9px;">Payment Confirmed</div>
        </div>
        <div class="summary-card" style="flex: 1; min-width: 120px; padding: 12px;">
            <div class="label" style="font-size: 11px;">Users with Profit</div>
            <div class="value" style="font-size: 16px;" id="users-with-profit" data-value="<?= $revenueSummary['users_with_profit'] ?? 0 ?>">
                <?= $revenueSummary['users_with_profit'] ?? 0 ?>
            </div>
            <div class="sub" style="font-size: 9px;">Above min threshold</div>
        </div>
    </div>

    <div class="section-divider" style="margin: 15px 0;"><span> All Users Directory</span></div>

    <h3 style="font-size: 15px; margin-bottom: 12px;">👥 User Directory - Filter & Search</h3>

    <div class="filter-section">
        <div class="filter-toggles">
            <button class="filter-btn active" data-filter="all"> All</button>
            <button class="filter-btn" data-filter="active-status"> Active</button>
            <button class="filter-btn" data-filter="completed-status"> Completed</button>
            <button class="filter-btn" data-filter="inactive-status"> Inactive</button>
            <button class="filter-btn" data-filter="confirmed"> Confirmed</button>
            <button class="filter-btn" data-filter="payment-made"> Payment Made</button>
            <button class="filter-btn" data-filter="unpaid"> Unpaid</button>
            <button class="filter-btn" data-filter="failed"> Failed</button>
            <button class="filter-btn" data-filter="eligible"> Eligible</button>
        </div>
        <div class="search-container">
            <input type="text" id="user-search" class="search-input" placeholder="🔍 Search by name, email or ID...">
            <button id="reset-search" class="reset-btn">Reset</button>
        </div>
    </div>

    <?php if (!empty($allUsers)): ?>
        <div class="table-wrapper" style="overflow-x: auto;">
            <table class="user-list-table" style="font-size: 12px; width: 100%; min-width: 1200px;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Broker</th>
                    <th>Login</th>
                    <th>Broker Bal</th>
                    <th>P/L</th>
                    <th>Current Bal</th>
                    <th>Server Share</th>
                    <th>User Share</th>
                    <th>Expected</th>
                    <th>Unpaid Age</th>
                    <th>Current Status</th>
                    <th>Status</th>
                    <th>Server Decision</th>
                    <th>Update</th>
                    <th>Source</th>
                </tr>
            </thead>
                <tbody>
                    <?php foreach ($allUsers as $user): 
                        // IMPORTANT: Extract ALL values properly
                        $userId = $user['id'] ?? 0;
                        $source = $user['source'] ?? 'unknown';
                        $fullname = $user['fullname'] ?? 'N/A';
                        $email = $user['email'] ?? 'N/A';
                        $broker = $user['broker'] ?? 'N/A';
                        $login = $user['login'] ?? 'N/A';
                        
                        // CRITICAL FIX: Get the actual balance values
                        $brokerBalance = $user['broker_balance'] ?? 0;
                        $profitLoss = $user['profitandloss'] ?? 0;
                        $currentBalance = $user['current_balance'] ?? 0;
                        
                        // Debug: Log if values are coming through
                        // error_log("User $userId: broker=$brokerBalance, pl=$profitLoss, current=$currentBalance");
                        
                        $shouldShowInRevenue = $user['should_show_in_revenue'] ?? false;
                        $displayStatus = $user['display_status'] ?? 'unknown';
                        $server_decision = $user['server_decision'] ?? '';
                        $isPaymentConfirmed = ($displayStatus === 'payment-confirmed');
                        $isPaymentMade = ($displayStatus === 'payment-made');
                        $isUnpaidPayment = ($displayStatus === 'unpaid-payment');
                        $isEligible = $user['has_eligible_profit'] ?? false;
                        $isUpdateDisabled = !$shouldShowInRevenue || !$isEligible;
                        $currentStatus = $user['current_status'] ?? 'inactive';
                        $statusLabel = $user['status_label'] ?? '⏹️ Inactive';
                        
                        // Only show values if user should show in revenue
                        $showBroker = $shouldShowInRevenue ? $brokerBalance : null;
                        $showPL = $shouldShowInRevenue ? $profitLoss : null;
                        $showCurrent = $shouldShowInRevenue ? $currentBalance : null;
                        $showServerShare = $isEligible ? ($user['server_share'] ?? 0) : null;
                        $showUserShare = $isEligible ? ($user['user_share'] ?? 0) : null;
                        $showExpected = $isEligible && ($profitLoss > 0) ? ($user['expected_payment'] ?? 0) : null; // ONLY IF PROFIT IS POSITIVE
                        
                        $plClass = ($showPL ?? 0) >= 0 ? 'profit' : 'loss';
                        $balanceClass = ($showCurrent ?? 0) >= 0 ? 'profit' : 'loss';
                    ?>
                        <tr class="user-row" 
                            data-user-id="<?= htmlspecialchars($userId) ?>"
                            data-source-table="<?= htmlspecialchars($source) ?>"
                            data-display-status="<?= htmlspecialchars($displayStatus) ?>"
                            data-is-payment-confirmed="<?= $isPaymentConfirmed ? 'true' : 'false' ?>"
                            data-current-status="<?= htmlspecialchars($currentStatus) ?>"
                            data-is-payment-made="<?= $isPaymentMade ? 'true' : 'false' ?>"
                            data-is-unpaid-payment="<?= $isUnpaidPayment ? 'true' : 'false' ?>"
                            data-is-failed-payment="<?= $displayStatus === 'failed-payment' ? 'true' : 'false' ?>"
                            data-is-eligible="<?= $isEligible ? 'true' : 'false' ?>"
                            data-should-show="<?= $shouldShowInRevenue ? 'true' : 'false' ?>"
                            data-id="<?= htmlspecialchars($userId) ?>"
                            data-email="<?= htmlspecialchars(strtolower($email)) ?>"
                            data-fullname="<?= htmlspecialchars(strtolower($fullname)) ?>"
                            data-broker-balance="<?= htmlspecialchars($brokerBalance) ?>"
                            data-profit-loss="<?= htmlspecialchars($profitLoss) ?>"
                            data-current-balance="<?= htmlspecialchars($currentBalance) ?>">
                           <td><?= htmlspecialchars($userId) ?></td>
                        <td><?= htmlspecialchars(substr($fullname, 0, 20)) ?></td>
                        <td><?= htmlspecialchars(substr($email, 0, 25)) ?></td>
                        <td><?= htmlspecialchars($broker) ?></td>
                        <td><?= htmlspecialchars($login) ?></td>
                        <td class="broker-balance-cell"><?= ($showBroker !== null) ? format_currency($showBroker) : '-' ?></td>
                        <td class="profit-loss-cell <?= $plClass ?>"><?= ($showPL !== null) ? ($showPL >= 0 ? '+' : '') . format_currency($showPL) : '-' ?></td>
                        <td class="current-balance-cell <?= $balanceClass ?>"><?= ($showCurrent !== null) ? format_currency($showCurrent) : '-' ?></td>
                        <td class="server-share-cell"><?= ($showServerShare !== null) ? format_currency($showServerShare) : '-' ?></td>
                        <td class="user-share-cell"><?= ($showUserShare !== null) ? format_currency($showUserShare) : '-' ?></td>
                        <td class="expected-payment-cell" style="font-weight: bold; color: <?= ($showExpected !== null && $showExpected > 0) ? '#27ae60' : '#888' ?>;"><?= ($showExpected !== null) ? format_currency($showExpected) : '-' ?></td>
                        <td class="unpaid-age-cell"><?php if (isset($user['unpaid_payment_age']['ended_on']) && $shouldShowInRevenue): ?><div><strong>Ended:</strong> <?= htmlspecialchars($user['unpaid_payment_age']['ended_on']) ?></div><div><strong>Age:</strong> <?= htmlspecialchars($user['unpaid_payment_age']['age'] ?? '') ?></div><?php else: ?>-<?php endif; ?></td>
                        <td class="current-status-cell"><span class="status-badge-modern <?= $currentStatus === 'active' ? 'status-active' : ($currentStatus === 'completed' ? 'status-completed' : 'status-inactive') ?>" style="font-size: 10px; padding: 3px 8px;"><?= htmlspecialchars($statusLabel) ?></span></td>
                        <td class="status-cell"><span class="status-badge" style="font-size: 10px; padding: 2px 6px;"><?= htmlspecialchars($displayStatus) ?></span><?php if ($isEligible && $isPaymentConfirmed): ?><span class="eligible-badge" style="font-size: 9px;">confirmed</span><?php elseif ($isEligible && $isPaymentMade): ?><span class="eligible-badge" style="font-size: 9px;">made</span><?php elseif ($isEligible && $isUnpaidPayment): ?><span class="eligible-badge" style="font-size: 9px;">unpaid</span><?php elseif ($displayStatus === 'failed-payment'): ?><span class="eligible-badge" style="font-size: 9px; background: #e74c3c;">failed</span><?php elseif ($isEligible && ($profitLoss > 0)): ?><span class="eligible-badge" style="font-size: 9px;">eligible</span><?php endif; ?>  </td>
                            <td class="server-decision-cell"><form method="POST" action="serveraccount.php?view=paid_users" class="server-decision-form" style="display: flex; gap: 4px; flex-wrap: wrap;"><input type="hidden" name="update_server_decision" value="1"><input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>"><input type="hidden" name="source_table" value="<?= htmlspecialchars($source) ?>"><select name="server_decision" class="server-decision-select" style="font-size: 10px; padding: 2px 4px;"><option value="">Select...</option><option value="blacklisted" <?= $server_decision === 'blacklisted' ? 'selected' : '' ?>> Blacklist</option><option value="re-instated" <?= $server_decision === 're-instated' ? 'selected' : '' ?>> Re-instate</option><option value="suspended" <?= $server_decision === 'suspended' ? 'selected' : '' ?>> Suspend</option></select><button type="submit" class="update-decision-btn" style="font-size: 9px; padding: 2px 6px;">Update</button></form>  </td>
                            <td class="status-update-cell"><form method="POST" action="serveraccount.php?view=paid_users" class="payment-status-form" style="display: flex; gap: 4px; flex-wrap: wrap;"><input type="hidden" name="update_payment_status" value="1"><input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>"><input type="hidden" name="source_table" value="<?= htmlspecialchars($source) ?>"><select name="payment_status" class="payment-status-select" style="font-size: 10px; padding: 2px 4px;">
                                <option value="">Select...</option>
                                <option value="payment-confirmed" <?= $displayStatus === 'payment-confirmed' ? 'selected' : '' ?>> Confirmed</option>
                                <option value="payment-made" <?= $displayStatus === 'payment-made' ? 'selected' : '' ?>> Made</option>
                                <option value="unpaid-payment" <?= $displayStatus === 'unpaid-payment' ? 'selected' : '' ?>> Unpaid</option>
                                <option value="failed-payment" <?= $displayStatus === 'failed-payment' ? 'selected' : '' ?>> Failed</option>
                            </select><button type="submit" class="update-status-btn" style="font-size: 9px; padding: 2px 6px;" <?= $isUpdateDisabled ? 'disabled' : '' ?>>Update</button></form><?php if ($isUpdateDisabled && ($user['decision_reason'] ?? '')): ?><small style="color: #888; font-size: 8px;"><?= htmlspecialchars(substr($user['decision_reason'] ?? '', 0, 30)) ?></small><?php endif; ?>  </td>
                            <td><?= htmlspecialchars($source) ?>  </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="user-count" style="font-size: 12px; margin-top: 10px;">Showing <span id="user-count"><?= count($allUsers) ?></span> of <?= count($allUsers) ?> total users</p>
    <?php else: ?>
        <p style="text-align: center; padding: 40px; border: 2px dashed var(--border-color); border-radius: 12px; color: #888;">No users found in the database.</p>
    <?php endif; ?>
</div>

<!-- Active Investors Tab -->
<div id="active-investors-tab" class="revenue-tab">
    <div class="revenue-split-view">
        <div class="revenue-user-list-panel">
            <h3>📈 Active Investors</h3>
            <div style="padding: 8px 12px; border-bottom: 1px solid var(--border-color);">
                <input type="text" id="active-investors-search" class="search-input" placeholder="🔍 Search by name, email or ID..." style="width: 100%; padding: 8px; font-size: 12px;">
            </div>
            <div id="active-investors-list" class="revenue-user-items" style="max-height: 450px; overflow-y: auto;">
                <div style="text-align: center; padding: 20px; font-size: 12px;">Loading...</div>
            </div>
        </div>
        <div class="revenue-history-panel">
            <div class="revenue-history-header">
                <h3 id="active-investor-name">Active Investor Details</h3>
                <button class="refresh-history-btn" onclick="refreshActiveInvestor()">🔄 Refresh</button>
            </div>
            
            <div id="active-investor-details">
                <div id="active-summary-container">
                    <div class="active-summary-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px;">
                        <div class="active-summary-card">
                            <div class="label">Total Investors</div>
                            <div class="value" id="active-total-investors">0</div>
                        </div>
                        <div class="active-summary-card">
                            <div class="label">Total Broker Balance</div>
                            <div class="value" id="active-total-balance">$0.00</div>
                        </div>
                        <div class="active-summary-card">
                            <div class="label">Total Current Balance</div>
                            <div class="value" id="active-total-current">$0.00</div>
                        </div>
                        <div class="active-summary-card">
                            <div class="label">Total P&L</div>
                            <div class="value" id="active-total-pl" style="color: var(--text-primary);">$0.00</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Completed Investors Tab -->
<div id="completed-investors-tab" class="revenue-tab">
    <div class="revenue-split-view">
        <div class="revenue-user-list-panel">
            <h3>📊 Completed Investors</h3>
            <div style="padding: 8px 12px; border-bottom: 1px solid var(--border-color);">
                <input type="text" id="completed-investors-search" class="search-input" placeholder="🔍 Search by name, email or ID..." style="width: 100%; padding: 8px; font-size: 12px;">
            </div>
            <div id="completed-investors-list" class="revenue-user-items" style="max-height: 450px; overflow-y: auto;">
                <div style="text-align: center; padding: 20px; font-size: 12px;">Loading...</div>
            </div>
        </div>
        <div class="revenue-history-panel">
            <div class="revenue-history-header">
                <h3 id="completed-investor-name">Revenue History</h3>
                <button class="refresh-history-btn" onclick="refreshCompletedInvestor()">🔄 Refresh</button>
            </div>
            
            <div style="margin-bottom: 15px; padding: 10px; background: var(--bg-primary); border-radius: 8px;">
                <div>
                    <input type="text" id="history-search-input" class="search-input" placeholder="🔍 Search history records..." style="width: 100%; padding: 8px; font-size: 12px;">
                </div>
            </div>
            
            <div id="revenue-history-container">
                <div style="text-align: center; padding: 40px; color: #888; font-size: 13px;">Select a user from the list to view their revenue history</div>
            </div>
        </div>
    </div>
</div>

<script>
// ==================== REVENUE.PHP - COMPLETE FIXED VERSION ====================

// Global variables
let currentRevenueUserId = null;
let currentRevenueUserSource = null;
let currentRevenueUserFullname = null;
let currentActiveUserId = null;
let currentActiveUserSource = null;
let currentActiveUserFullname = null;
let currentActiveUserEmail = null;
let currentActiveUserExecutionStart = null;
let currentActiveUserContractDuration = null;
let currentActiveUserProfitLoss = null;
let currentActiveUserCurrentBalance = null;
let currentActiveUserBrokerBalance = null;
let currentActiveUserServerShare = null;
let currentActiveUserUserShare = null;
let allActiveInvestorsData = [];
let allCompletedUsersData = [];

// ============ TAB SWITCHING ============
document.addEventListener('DOMContentLoaded', function() {
    const revenueTabBtns = document.querySelectorAll('.revenue-tab-btn');
    const revenueTabs = document.querySelectorAll('.revenue-tab');
    
    revenueTabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.getAttribute('data-revenue-tab');
            
            revenueTabBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            revenueTabs.forEach(tab => tab.classList.remove('active-tab'));
            
            if (tabId === 'current') {
                document.getElementById('current-revenue-tab').classList.add('active-tab');
            } else if (tabId === 'active') {
                document.getElementById('active-investors-tab').classList.add('active-tab');
                loadActiveInvestors();
            } else if (tabId === 'completed') {
                document.getElementById('completed-investors-tab').classList.add('active-tab');
                loadCompletedInvestors();
            }
        });
    });

    // ============ MAIN TABLE FILTERS ============
    const filterBtns = document.querySelectorAll('.filter-btn');
    const searchInput = document.getElementById('user-search');
    const resetSearchBtn = document.getElementById('reset-search');
    const userCountSpan = document.getElementById('user-count');
    
    function filterAndSearchUsers() {
        const activeFilter = document.querySelector('.filter-btn.active');
        const filterType = activeFilter ? activeFilter.getAttribute('data-filter') : 'all';
        const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const allRows = document.querySelectorAll('.user-row');
        let visibleCount = 0;
        
        allRows.forEach(row => {
            const shouldShow = row.getAttribute('data-should-show') === 'true';
            const currentStatus = row.getAttribute('data-current-status') || 'inactive';
            const isPaymentConfirmed = row.getAttribute('data-is-payment-confirmed') === 'true';
            const isPaymentMade = row.getAttribute('data-is-payment-made') === 'true';
            const isUnpaidPayment = row.getAttribute('data-is-unpaid-payment') === 'true';
            const isFailedPayment = row.getAttribute('data-is-failed-payment') === 'true';
            const isEligible = row.getAttribute('data-is-eligible') === 'true';
            const name = (row.getAttribute('data-fullname') || '').toLowerCase();
            const email = (row.getAttribute('data-email') || '').toLowerCase();
            const id = row.getAttribute('data-id') || '';
            
            let matchesFilter = false;
            switch(filterType) {
                case 'all':
                    matchesFilter = true;
                    break;
                case 'active-status':
                    matchesFilter = currentStatus === 'active' && shouldShow;
                    break;
                case 'completed-status':
                    matchesFilter = currentStatus === 'completed' && shouldShow;
                    break;
                case 'inactive-status':
                    matchesFilter = currentStatus === 'inactive';
                    break;
                case 'confirmed':
                    matchesFilter = isPaymentConfirmed && shouldShow;
                    break;
                case 'payment-made':
                    matchesFilter = isPaymentMade && shouldShow;
                    break;
                case 'unpaid':
                    matchesFilter = isUnpaidPayment && shouldShow;
                    break;
                case 'failed':
                    matchesFilter = isFailedPayment && shouldShow;
                    break;
                case 'eligible':
                    matchesFilter = isEligible && shouldShow;
                    break;
                default:
                    matchesFilter = true;
            }
            
            let matchesSearch = true;
            if (searchTerm !== '') {
                matchesSearch = id.includes(searchTerm) || email.includes(searchTerm) || name.includes(searchTerm);
            }
            
            if (matchesFilter && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (userCountSpan) {
            userCountSpan.textContent = visibleCount;
        }
    }
    
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            filterAndSearchUsers();
        });
    });
    
    if (searchInput) {
        searchInput.addEventListener('keyup', filterAndSearchUsers);
    }
    
    if (resetSearchBtn) {
        resetSearchBtn.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            filterAndSearchUsers();
        });
    }
    
    // ============ LIVE UPDATES FOR TABLE ============
    startLiveUserUpdates(30);
});

// ============ ACTIVE INVESTORS ============
function loadActiveInvestors() {
    const container = document.getElementById('active-investors-list');
    if (!container) return;
    
    container.innerHTML = '<div style="text-align: center; padding: 20px; font-size: 12px;">Loading active investors...</div>';
    setupActiveInvestorsSearch();
    
    fetch('serveraccount.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=get_active_investors'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.users) {
            allActiveInvestorsData = data.users;
            
            // Update summary cards
            updateActiveSummaryCards(data.users);
            
            if (data.users.length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888; font-size: 12px;">No active investors found</div>';
                return;
            }
            
            container.innerHTML = '';
            data.users.forEach(user => {
                const userDiv = document.createElement('div');
                userDiv.className = 'revenue-user-item';
                userDiv.setAttribute('data-user-id', user.id);
                userDiv.setAttribute('data-source', user.source);
                userDiv.setAttribute('data-fullname', user.fullname || '');
                userDiv.setAttribute('data-email', user.email || '');
                userDiv.setAttribute('data-execution-start', user.execution_start_date || '');
                userDiv.setAttribute('data-contract-duration', user.contract_duration || 0);
                userDiv.setAttribute('data-profit-loss', user.profitandloss || 0);
                userDiv.setAttribute('data-current-balance', user.current_balance || 0);
                userDiv.setAttribute('data-broker-balance', user.broker_balance || 0);
                userDiv.setAttribute('data-server-share', user.server_share || 0);
                userDiv.setAttribute('data-user-share', user.user_share || 0);
                userDiv.setAttribute('data-status', user.status || 'active');
                userDiv.onclick = () => {
                    selectActiveInvestor(user.id, user.source, user.fullname, user.email, 
                        user.execution_start_date, user.contract_duration, user.profitandloss,
                        user.current_balance, user.broker_balance, user.server_share, user.user_share);
                };
                userDiv.innerHTML = `
                    <div class="revenue-user-item-name">${escapeHtml(user.fullname || 'N/A')}</div>
                    <div class="revenue-user-item-email">${escapeHtml(user.email || 'N/A')}</div>
                    <div class="revenue-user-item-id">ID: ${user.id} | Bal: $${parseFloat(user.broker_balance || 0).toFixed(2)}</div>
                `;
                container.appendChild(userDiv);
            });
        } else {
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c; font-size: 12px;">Error loading active investors</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c; font-size: 12px;">Error loading active investors</div>';
    });
}

// Update Active Summary Cards
function updateActiveSummaryCards(users) {
    const totalInvestors = users.length;
    let totalBalance = 0, totalCurrent = 0, totalPL = 0;
    
    users.forEach(user => {
        totalBalance += parseFloat(user.broker_balance || 0);
        totalCurrent += parseFloat(user.current_balance || 0);
        totalPL += parseFloat(user.profitandloss || 0);
    });
    
    document.getElementById('active-total-investors').textContent = totalInvestors;
    document.getElementById('active-total-balance').textContent = '$' + totalBalance.toFixed(2);
    document.getElementById('active-total-current').textContent = '$' + totalCurrent.toFixed(2);
    
    const plElement = document.getElementById('active-total-pl');
    plElement.textContent = (totalPL >= 0 ? '+' : '') + '$' + totalPL.toFixed(2);
    plElement.style.color = totalPL >= 0 ? '#27ae60' : '#e74c3c';
}

// Select Active Investor - FIXED to show all values
function selectActiveInvestor(userId, sourceTable, fullname, email, executionStartDate, contractDuration, profitLoss, currentBalance, brokerBalance, serverShare, userShare) {
    currentActiveUserId = userId;
    currentActiveUserSource = sourceTable;
    currentActiveUserFullname = fullname;
    currentActiveUserEmail = email;
    currentActiveUserExecutionStart = executionStartDate;
    currentActiveUserContractDuration = contractDuration;
    currentActiveUserProfitLoss = profitLoss;
    currentActiveUserCurrentBalance = currentBalance;
    currentActiveUserBrokerBalance = brokerBalance;
    currentActiveUserServerShare = serverShare;
    currentActiveUserUserShare = userShare;
    
    // Hide summary cards
    const summaryContainer = document.getElementById('active-summary-container');
    if (summaryContainer) summaryContainer.style.display = 'none';
    
    document.querySelectorAll('#active-investors-list .revenue-user-item').forEach(item => {
        item.classList.remove('active');
    });
    const selectedItem = document.querySelector(`#active-investors-list .revenue-user-item[data-user-id="${userId}"]`);
    if (selectedItem) selectedItem.classList.add('active');
    
    document.getElementById('active-investor-name').innerHTML = `${escapeHtml(fullname)} <span style="font-size: 11px; color: #888; display: block;">${escapeHtml(email)}</span>`;
    
    let contractStartFormatted = '-';
    let contractEndFormatted = '-';
    let daysRemaining = '-';
    let statusClass = 'status-active';
    let statusText = 'Active';
    
    if (executionStartDate && contractDuration > 0) {
        const start = new Date(executionStartDate);
        contractStartFormatted = start.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        
        const end = new Date(start);
        end.setDate(end.getDate() + parseInt(contractDuration));
        contractEndFormatted = end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        
        const today = new Date();
        const diffTime = end - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        daysRemaining = diffDays > 0 ? diffDays + ' days' : 'Expired';
        
        if (diffDays <= 0) {
            statusClass = 'status-ended';
            statusText = 'Ended';
        }
    }
    
    const plValue = parseFloat(profitLoss || 0);
    const plFormatted = (plValue >= 0 ? '+' : '') + '$' + plValue.toFixed(2);
    const plClass = plValue >= 0 ? 'profit-positive' : 'profit-negative';
    
    const detailsHtml = `
        <div class="investor-details-grid">
            <div class="detail-card">
                <div class="detail-label">Status</div>
                <div class="detail-value"><span class="status-badge-modern ${statusClass}">${statusText}</span></div>
            </div>
            <div class="detail-card">
                <div class="detail-label">Start Date</div>
                <div class="detail-value">${contractStartFormatted}</div>
            </div>
            <div class="detail-card">
                <div class="detail-label">End Date</div>
                <div class="detail-value">${contractEndFormatted}</div>
            </div>
            <div class="detail-card">
                <div class="detail-label">Days Left</div>
                <div class="detail-value">${daysRemaining}</div>
            </div>
            <div class="detail-card">
                <div class="detail-label">Broker Balance</div>
                <div class="detail-value">$${parseFloat(brokerBalance || 0).toFixed(2)}</div>
            </div>
            <div class="detail-card">
                <div class="detail-label">Current Balance</div>
                <div class="detail-value">$${parseFloat(currentBalance || 0).toFixed(2)}</div>
            </div>
            <div class="detail-card">
                <div class="detail-label">P/L</div>
                <div class="detail-value ${plClass}">${plFormatted}</div>
            </div>
            <div class="detail-card">
                <div class="detail-label">Server Share</div>
                <div class="detail-value">$${parseFloat(serverShare || 0).toFixed(2)}</div>
            </div>
            <div class="detail-card">
                <div class="detail-label">User Share</div>
                <div class="detail-value">$${parseFloat(userShare || 0).toFixed(2)}</div>
            </div>
        </div>
        <div style="margin-top: 15px; text-align: center;">
            <button class="cancel-contract-btn" onclick="showCancelContractModal()">Cancel Contract</button>
        </div>
    `;
    
    document.getElementById('active-investor-details').innerHTML = detailsHtml;
}

// ============ CANCEL CONTRACT WITH AUTO-REFRESH ============
function showCancelContractModal() {
    let modal = document.getElementById('cancel-contract-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'cancel-contract-modal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 400px;">
                <h3>⚠️ Cancel Contract</h3>
                <p>Are you sure you want to cancel <strong id="cancel-contract-name"></strong>'s contract?</p>
                <p style="font-size: 12px; color: #e74c3c;">This will change the execution start date to make the contract expired immediately.</p>
                <p style="font-size: 12px; margin-top: 10px;">Please enter your admin password to confirm.</p>
                <input type="password" id="cancel-contract-password" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" id="cancel-contract-modal-cancel" class="modal-cancel-btn">Cancel</button>
                    <button type="button" id="cancel-contract-modal-confirm" class="modal-confirm-btn">Confirm Cancellation</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    document.getElementById('cancel-contract-name').textContent = currentActiveUserFullname || 'this user';
    modal.classList.add('show');
    
    const passwordInput = document.getElementById('cancel-contract-password');
    if (passwordInput) {
        passwordInput.value = '';
        passwordInput.focus();
    }
    
    const confirmBtn = document.getElementById('cancel-contract-modal-confirm');
    const cancelBtn = document.getElementById('cancel-contract-modal-cancel');
    
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
        modal.classList.remove('show');
        executeCancelContract(password);
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

function executeCancelContract(password) {
    if (!currentActiveUserId || !currentActiveUserSource) {
        showMessage('No user selected', 'error');
        return;
    }
    
    const cancelBtn = document.querySelector('.cancel-contract-btn');
    const originalText = cancelBtn ? cancelBtn.innerHTML : '';
    if (cancelBtn) {
        cancelBtn.innerHTML = '⏳ Processing...';
        cancelBtn.disabled = true;
    }
    
    let formData = new URLSearchParams();
    formData.append('action', 'cancel_contract');
    formData.append('user_id', currentActiveUserId);
    formData.append('source_table', currentActiveUserSource);
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
            showMessage(data.message || 'Contract cancelled successfully!', 'success');
            // Reload active investors to remove the cancelled user
            loadActiveInvestors();
            // Reset the details panel to show summary
            document.getElementById('active-investor-details').innerHTML = `
                <div id="active-summary-container">
                    <div class="active-summary-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px;">
                        <div class="active-summary-card">
                            <div class="label">Total Investors</div>
                            <div class="value" id="active-total-investors">0</div>
                        </div>
                        <div class="active-summary-card">
                            <div class="label">Total Broker Balance</div>
                            <div class="value" id="active-total-balance">$0.00</div>
                        </div>
                        <div class="active-summary-card">
                            <div class="label">Total Current Balance</div>
                            <div class="value" id="active-total-current">$0.00</div>
                        </div>
                        <div class="active-summary-card">
                            <div class="label">Total P&L</div>
                            <div class="value" id="active-total-pl" style="color: var(--text-primary);">$0.00</div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('active-investor-name').innerHTML = 'Active Investor Details';
            // Update summary cards with remaining investors
            updateActiveSummaryCards(allActiveInvestorsData.filter(u => u.id != currentActiveUserId));
            currentActiveUserId = null;
        } else {
            showMessage(data.error || 'Error cancelling contract', 'error');
            if (cancelBtn) {
                cancelBtn.innerHTML = originalText;
                cancelBtn.disabled = false;
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error cancelling contract', 'error');
        if (cancelBtn) {
            cancelBtn.innerHTML = originalText;
            cancelBtn.disabled = false;
        }
    });
}

function refreshActiveInvestor() {
    loadActiveInvestors();
}

// ============ COMPLETED INVESTORS ============
function loadCompletedInvestors() {
    const container = document.getElementById('completed-investors-list');
    if (!container) return;
    
    container.innerHTML = '<div style="text-align: center; padding: 20px; font-size: 12px;">Loading all users...</div>';
    setupCompletedInvestorsSearch();
    
    Promise.all([
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_all_users_for_management'
        }).then(res => res.json()),
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_completed_investors'
        }).then(res => res.json()),
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_active_investors'
        }).then(res => res.json())
    ])
    .then(([allUsersData, historyData, activeData]) => {
        let userMap = new Map();
        const today = new Date();
        const contractDuration = <?= (int)($serverAccount['contract_duration'] ?? 30) ?>;
        
        if (allUsersData.success && allUsersData.users) {
            allUsersData.users.forEach(user => {
                userMap.set(`${user.id}_${user.source}`, {
                    id: user.id,
                    source: user.source,
                    fullname: user.fullname || 'N/A',
                    email: user.email || 'N/A',
                    has_history: false,
                    has_active_contract: false,
                    is_completed: false,
                    payment_summary: { 
                        total_unpaid_revenue: 0, 
                        total_payment_made: 0, 
                        total_payment_confirmed: 0, 
                        total_cancelled_contracts: 0, 
                        total_failed_payments: 0, 
                        unpaid_count: 0, 
                        payment_made_count: 0, 
                        payment_confirmed_count: 0, 
                        cancelled_count: 0, 
                        failed_count: 0 
                    },
                    application_status: user.application_status || 'unknown',
                    user_type: 'no_history'
                });
            });
        }
        
        if (historyData.success && historyData.users) {
            historyData.users.forEach(user => {
                const key = `${user.id}_${user.source}`;
                if (userMap.has(key)) {
                    let existing = userMap.get(key);
                    existing.has_history = true;
                    existing.payment_summary = user.payment_summary || existing.payment_summary;
                    existing.user_type = 'recorded';
                    existing.history_count = user.history_count || 0;
                    existing.current_loyalties = user.current_loyalties;
                } else {
                    userMap.set(key, {
                        ...user,
                        has_history: true,
                        user_type: 'recorded'
                    });
                }
            });
        }
        
        if (activeData.success && activeData.users) {
            activeData.users.forEach(user => {
                const key = `${user.id}_${user.source}`;
                if (userMap.has(key)) {
                    let existing = userMap.get(key);
                    existing.has_active_contract = true;
                    
                    if (user.execution_start_date) {
                        const start = new Date(user.execution_start_date);
                        const end = new Date(start);
                        end.setDate(end.getDate() + contractDuration);
                        
                        if (end <= today) {
                            existing.is_completed = true;
                            existing.user_type = 'recent';
                            existing.execution_end_date = end.toISOString().split('T')[0];
                        } else if (existing.user_type === 'no_history') {
                            existing.user_type = 'active';
                        }
                    }
                }
            });
        }
        
        const allUsers = Array.from(userMap.values());
        allCompletedUsersData = allUsers;
        
        allUsers.sort((a, b) => {
            const typeOrder = { 'recorded': 1, 'recent': 2, 'active': 3, 'no_history': 4 };
            const orderA = typeOrder[a.user_type] || 5;
            const orderB = typeOrder[b.user_type] || 5;
            if (orderA !== orderB) return orderA - orderB;
            return (a.fullname || '').localeCompare(b.fullname || '');
        });
        
        if (allUsers.length === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888; font-size: 12px;">No users found</div>';
            return;
        }
        
        container.innerHTML = '';
        allUsers.forEach(user => {
            container.appendChild(createCompletedUserItem(user, user.user_type));
        });
    })
    .catch(error => {
        console.error('Error:', error);
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #e74c3c; font-size: 12px;">Error loading users</div>';
    });
}

function createCompletedUserItem(user, type) {
    const userDiv = document.createElement('div');
    userDiv.className = 'revenue-user-item';
    userDiv.setAttribute('data-user-id', user.id);
    userDiv.setAttribute('data-source', user.source);
    userDiv.setAttribute('data-fullname', user.fullname || '');
    userDiv.setAttribute('data-email', user.email || '');
    userDiv.setAttribute('data-has-history', user.has_history || false);
    userDiv.setAttribute('data-user-type', type);
    
    if (user.payment_summary) {
        userDiv.setAttribute('data-unpaid-revenue', user.payment_summary.total_unpaid_revenue || 0);
        userDiv.setAttribute('data-payment-made', user.payment_summary.total_payment_made || 0);
        userDiv.setAttribute('data-payment-confirmed', user.payment_summary.total_payment_confirmed || 0);
        userDiv.setAttribute('data-cancelled-contracts', user.payment_summary.total_cancelled_contracts || 0);
        userDiv.setAttribute('data-failed-payments', user.payment_summary.total_failed_payments || 0);
        userDiv.setAttribute('data-unpaid-count', user.payment_summary.unpaid_count || 0);
        userDiv.setAttribute('data-made-count', user.payment_summary.payment_made_count || 0);
        userDiv.setAttribute('data-confirmed-count', user.payment_summary.payment_confirmed_count || 0);
        userDiv.setAttribute('data-cancelled-count', user.payment_summary.cancelled_count || 0);
        userDiv.setAttribute('data-failed-count', user.payment_summary.failed_count || 0);
    }
    
    userDiv.onclick = () => {
        selectCompletedInvestor(user.id, user.source, user.fullname, user.email, user.has_history, user.payment_summary);
    };
    
    const totalRecords = (user.payment_summary?.unpaid_count || 0) + 
                        (user.payment_summary?.payment_made_count || 0) + 
                        (user.payment_summary?.payment_confirmed_count || 0) + 
                        (user.payment_summary?.cancelled_count || 0) +
                        (user.payment_summary?.failed_count || 0);
    
    let typeBadge = '';
    switch(type) {
        case 'recent':
            typeBadge = '<span class="status-badge-modern status-recent" style="font-size: 8px; margin-left: 5px;">Recent</span>';
            break;
        case 'recorded':
            typeBadge = '<span class="status-badge-modern status-completed" style="font-size: 8px; margin-left: 5px;">Recorded</span>';
            break;
        case 'no_history':
            typeBadge = '<span class="status-badge-modern" style="background: rgba(52, 152, 219, 0.15); color: #3498db; font-size: 8px; margin-left: 5px;">No History</span>';
            break;
        case 'active':
            typeBadge = '<span class="status-badge-modern status-active" style="font-size: 8px; margin-left: 5px;">Active</span>';
            break;
        default:
            typeBadge = '';
    }
    
    let summaryBadges = '';
    if (user.payment_summary && (user.payment_summary.payment_made_count > 0 || user.payment_summary.payment_confirmed_count > 0 || user.payment_summary.unpaid_count > 0 || user.payment_summary.failed_count > 0)) {
        if (user.payment_summary.payment_made_count > 0) {
            summaryBadges += `<span class="status-badge-modern" style="background: rgba(241, 196, 15, 0.15); color: #f39c12; font-size: 8px; margin-left: 4px;">💰 ${user.payment_summary.payment_made_count} made ($${user.payment_summary.total_payment_made.toFixed(2)})</span>`;
        }
        if (user.payment_summary.payment_confirmed_count > 0) {
            summaryBadges += `<span class="status-badge-modern" style="background: rgba(46, 204, 113, 0.15); color: #27ae60; font-size: 8px; margin-left: 4px;">✅ ${user.payment_summary.payment_confirmed_count} confirmed ($${user.payment_summary.total_payment_confirmed.toFixed(2)})</span>`;
        }
        if (user.payment_summary.unpaid_count > 0) {
            summaryBadges += `<span class="status-badge-modern" style="background: rgba(231, 76, 60, 0.15); color: #e74c3c; font-size: 8px; margin-left: 4px;">⚠️ ${user.payment_summary.unpaid_count} unpaid ($${user.payment_summary.total_unpaid_revenue.toFixed(2)})</span>`;
        }
        if (user.payment_summary.cancelled_count > 0) {
            summaryBadges += `<span class="status-badge-modern" style="background: rgba(155, 89, 182, 0.15); color: #9b59b6; font-size: 8px; margin-left: 4px;">${user.payment_summary.cancelled_count} cancelled ($${user.payment_summary.total_cancelled_contracts.toFixed(2)})</span>`;
        }
        if (user.payment_summary.failed_count > 0) {
            summaryBadges += `<span class="status-badge-modern" style="background: rgba(231, 76, 60, 0.15); color: #e74c3c; font-size: 8px; margin-left: 4px;">💀 ${user.payment_summary.failed_count} failed ($${user.payment_summary.total_failed_payments.toFixed(2)})</span>`;
        }
    } else if (type === 'no_history') {
        summaryBadges = '<span class="status-badge-modern" style="background: rgba(52, 152, 219, 0.1); color: #7f8c8d; font-size: 8px;">No contract activity yet</span>';
    } else if (type === 'active') {
        summaryBadges = '<span class="status-badge-modern status-active" style="font-size: 8px;">Contract in progress</span>';
    }
    
    let recordsInfo = '';
    if (totalRecords > 0) {
        recordsInfo = ` | ${totalRecords} records`;
    } else if (type === 'no_history') {
        recordsInfo = ' | No records';
    }
    
    userDiv.innerHTML = `
        <div class="revenue-user-item-name">${escapeHtml(user.fullname || 'N/A')} ${typeBadge}</div>
        <div class="revenue-user-item-email">${escapeHtml(user.email || 'N/A')}</div>
        <div class="revenue-user-item-id">ID: ${user.id}${recordsInfo}</div>
        <div class="revenue-user-item-badges" style="margin-top: 5px; display: flex; flex-wrap: wrap; gap: 4px;">${summaryBadges}</div>
    `;
    return userDiv;
}

// ============ SELECT COMPLETED INVESTOR WITH SUMMARY CARDS ============
function selectCompletedInvestor(userId, sourceTable, fullname, email, hasHistory, paymentSummary) {
    currentRevenueUserId = userId;
    currentRevenueUserSource = sourceTable;
    currentRevenueUserFullname = fullname;
    
    document.querySelectorAll('#completed-investors-list .revenue-user-item').forEach(item => {
        item.classList.remove('active');
    });
    const selectedItem = document.querySelector(`#completed-investors-list .revenue-user-item[data-user-id="${userId}"]`);
    if (selectedItem) selectedItem.classList.add('active');
    
    document.getElementById('completed-investor-name').innerHTML = `${escapeHtml(fullname)} <span style="font-size: 11px; color: #888; display: block;">${escapeHtml(email)}</span>`;
    
    if (hasHistory) {
        loadRevenueHistoryForUserWithSummary(paymentSummary);
    } else {
        const container = document.getElementById('revenue-history-container');
        container.innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <div class="status-badge-modern" style="display: inline-block; margin-bottom: 15px; background: rgba(52, 152, 219, 0.15); color: #3498db;">ℹ️ No Revenue History</div>
                <p style="color: #888;">This user is registered but has no completed contract history yet.</p>
                <p style="font-size: 12px; color: #666; margin-top: 10px;">Revenue history will appear here once the user completes a contract and profit split is processed.</p>
            </div>
        `;
    }
}

// ============ LOAD REVENUE HISTORY WITH SUMMARY CARDS ============
function loadRevenueHistoryForUserWithSummary(paymentSummary) {
    const container = document.getElementById('revenue-history-container');
    if (!container) return;
    
    if (!currentRevenueUserId || !currentRevenueUserSource) {
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888; font-size: 13px;">Select a user from the list to view their revenue history</div>';
        return;
    }
    
    container.innerHTML = '<div style="text-align: center; padding: 20px; font-size: 12px;">Loading revenue history...</div>';
    
    fetch('serveraccount.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=get_revenue_history&user_id=' + encodeURIComponent(currentRevenueUserId) + '&source_table=' + encodeURIComponent(currentRevenueUserSource)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.history && data.history.length > 0) {
            data.history.sort((a, b) => {
                const idA = parseInt(a.id) || 0;
                const idB = parseInt(b.id) || 0;
                return idB - idA;
            });
            
            window.currentRevenueRecords = [...data.history];
            currentHistoryFilter = 'all';
            currentHistorySearchTerm = '';
            
            // Calculate summary totals
            let totals = { 
                unpaid: 0, 
                payment_made: 0, 
                payment_confirmed: 0, 
                cancelled: 0, 
                failed: 0 
            };
            let totalProfit = 0, totalServerShare = 0, totalUserShare = 0;
            let categoryCounts = { 
                'payment-made': 0, 
                'payment-confirmed': 0, 
                'unpaid-payment': 0, 
                'contract-cancelled': 0,
                'failed-payment': 0 
            };

            data.history.forEach(record => {
                const loyalties = (record.loyalties || '').toLowerCase();
                const amount = parseFloat(record.server_share || 0);
                totalProfit += parseFloat(record.profit || 0);
                totalServerShare += parseFloat(record.server_share || 0);
                totalUserShare += parseFloat(record.user_share || 0);
                
                if (loyalties.includes('unpaid')) { 
                    totals.unpaid += amount; 
                    categoryCounts['unpaid-payment']++; 
                } else if (loyalties.includes('payment-made')) { 
                    totals.payment_made += amount; 
                    categoryCounts['payment-made']++; 
                } else if (loyalties.includes('payment-confirmed')) { 
                    totals.payment_confirmed += amount; 
                    categoryCounts['payment-confirmed']++; 
                } else if (loyalties.includes('cancelled')) { 
                    totals.cancelled += amount; 
                    categoryCounts['contract-cancelled']++; 
                } else if (loyalties.includes('failed')) { 
                    totals.failed += amount; 
                    categoryCounts['failed-payment']++; 
                }
            });
            
            // Build summary cards HTML
            const summaryHtml = `
                <div class="completed-stats-row" style="margin-bottom: 15px; display: flex; flex-wrap: wrap; gap: 10px;">
                    <div class="completed-stat-card">
                        <div class="completed-stat-label">Total Profit</div>
                        <div class="completed-stat-value ${totalProfit >= 0 ? 'profit-positive' : 'profit-negative'}">${totalProfit >= 0 ? '+' : ''}$${totalProfit.toFixed(2)}</div>
                    </div>
                    <div class="completed-stat-card">
                        <div class="completed-stat-label">Total User Revenue</div>
                        <div class="completed-stat-value">$${totalUserShare.toFixed(2)}</div>
                    </div>
                    <div class="completed-stat-card">
                        <div class="completed-stat-label">Total Server Share</div>
                        <div class="completed-stat-value">$${totalServerShare.toFixed(2)}</div>
                    </div>
                    <div class="completed-stat-card">
                        <div class="completed-stat-label">Total Records</div>
                        <div class="completed-stat-value">${data.history.length}</div>
                    </div>
                </div>
                <div class="completed-stats-row" style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px;">
                    <div class="completed-stat-card" style="border-left: 3px solid #f39c12;">
                        <div class="completed-stat-label">💰 Payments Made</div>
                        <div class="completed-stat-value" style="color: #f39c12;">$${totals.payment_made.toFixed(2)}</div>
                        <div style="font-size: 10px; color: var(--text-secondary);">${categoryCounts['payment-made']} records</div>
                    </div>
                    <div class="completed-stat-card" style="border-left: 3px solid #27ae60;">
                        <div class="completed-stat-label">✅ Payments Confirmed</div>
                        <div class="completed-stat-value" style="color: #27ae60;">$${totals.payment_confirmed.toFixed(2)}</div>
                        <div style="font-size: 10px; color: var(--text-secondary);">${categoryCounts['payment-confirmed']} records</div>
                    </div>
                    <div class="completed-stat-card" style="border-left: 3px solid #9b59b6;">
                        <div class="completed-stat-label">❌ Cancelled</div>
                        <div class="completed-stat-value" style="color: #9b59b6;">$${totals.cancelled.toFixed(2)}</div>
                        <div style="font-size: 10px; color: var(--text-secondary);">${categoryCounts['contract-cancelled']} records</div>
                    </div>
                    <div class="completed-stat-card" style="border-left: 3px solid #e74c3c;">
                        <div class="completed-stat-label">⚠️ Unpaid</div>
                        <div class="completed-stat-value" style="color: #e74c3c;">$${totals.unpaid.toFixed(2)}</div>
                        <div style="font-size: 10px; color: var(--text-secondary);">${categoryCounts['unpaid-payment']} records</div>
                    </div>
                    <div class="completed-stat-card" style="border-left: 3px solid #c0392b;">
                        <div class="completed-stat-label">💀 Failed</div>
                        <div class="completed-stat-value" style="color: #c0392b;">$${totals.failed.toFixed(2)}</div>
                        <div style="font-size: 10px; color: var(--text-secondary);">${categoryCounts['failed-payment']} records</div>
                    </div>
                </div>
                <div id="revenue-history-table-container">
                    ${renderHistoryTableHTML(window.currentRevenueRecords)}
                </div>
            `;
            
            container.innerHTML = summaryHtml;
        } else {
            container.innerHTML = '<div style="text-align: center; padding: 20px; color: #888; font-size: 12px;">No revenue history records found for this user</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        container.innerHTML = '<div style="text-align: center; padding: 20px; color: #e74c3c; font-size: 12px;">Error loading revenue history</div>';
    });
}

// ============ HELPER FUNCTIONS ============
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderHistoryTableHTML(records) {
    if (!records || records.length === 0) {
        return '<div style="text-align: center; padding: 40px; color: #888; font-size: 13px;">No matching records found.</div>';
    }
    
    let tableHtml = `
        <div class="revenue-history-table">
            <div style="overflow-x: auto;">
                <table style="width: 100%; min-width: 1000px; border-collapse: collapse; font-size: 12px;">
                    <thead>
                        <tr style="background: var(--bg-primary);">
                            <th style="padding: 10px 8px;">ID</th>
                            <th>Contract ID</th>
                            <th>Recorded At</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Start Bal</th>
                            <th>End Bal</th>
                            <th>Profit</th>
                            <th>Server</th>
                            <th>User</th>
                            <th>Invested With</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    records.forEach(record => {
        const profitClass = record.profit >= 0 ? 'profit-positive' : 'profit-negative';
        const statusDisplay = record.loyalties || '-';
        const contractId = record.contract_id || 'N/A';
        const investedWith = record.invested_with || '-';
        
        let recordedAtDisplay = '-';
        if (record.recorded_at) {
            const date = new Date(record.recorded_at);
            recordedAtDisplay = date.toLocaleString();
        } else if (record.id) {
            const date = new Date(record.id * 1000);
            recordedAtDisplay = date.toLocaleString() + ' (approx)';
        }
        
        let statusClass = 'status-completed';
        const lowerStatus = statusDisplay.toLowerCase();
        if (lowerStatus.includes('unpaid')) statusClass = 'status-ended';
        else if (lowerStatus.includes('payment-made')) statusClass = 'status-active';
        else if (lowerStatus.includes('cancelled')) statusClass = 'status-ended';
        else if (lowerStatus.includes('failed')) statusClass = 'status-failed';
        
        tableHtml += `
            <tr style="border-bottom: 1px solid var(--border-color);">
                <td style="padding: 8px;"><code>${escapeHtml(String(record.id)).substring(0, 8)}</code></td>
                <td style="padding: 8px; font-size: 10px; font-family: monospace;">${escapeHtml(contractId)}</td>
                <td style="padding: 8px; font-size: 11px; white-space: nowrap;">${escapeHtml(recordedAtDisplay)}</td>
                <td style="padding: 8px;">${escapeHtml(record.execution_start_date || '-')}</td>
                <td style="padding: 8px;">${escapeHtml(record.execution_end_date || '-')}</td>
                <td style="padding: 8px;">$${parseFloat(record.starting_balance || 0).toFixed(2)}</td>
                <td style="padding: 8px;">$${parseFloat(record.current_balance || 0).toFixed(2)}</td>
                <td style="padding: 8px;" class="${profitClass}">${record.profit >= 0 ? '+' : ''}$${parseFloat(record.profit || 0).toFixed(2)}</td>
                <td style="padding: 8px;">$${parseFloat(record.server_share || 0).toFixed(2)}</td>
                <td style="padding: 8px;">$${parseFloat(record.user_share || 0).toFixed(2)}</td>
                <td style="padding: 8px;"><span class="status-badge-modern" style="background: rgba(52, 152, 219, 0.1);">${escapeHtml(investedWith)}</span></td>
                <td style="padding: 8px;"><span class="status-badge-modern ${statusClass}">${escapeHtml(statusDisplay)}</span></td>
            </tr>
        `;
    });
    
    tableHtml += `</tbody></table></div></div>`;
    return tableHtml;
}

function refreshCompletedInvestor() {
    if (currentRevenueUserId && currentRevenueUserSource) {
        loadRevenueHistoryForUserWithSummary(null);
    } else {
        loadCompletedInvestors();
    }
}

// ============ SEARCH SETUP ============
function setupActiveInvestorsSearch() {
    const searchInput = document.getElementById('active-investors-search');
    if (!searchInput) return;
    
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const userItems = document.querySelectorAll('#active-investors-list .revenue-user-item');
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
        
        const container = document.getElementById('active-investors-list');
        const noResultsMsg = container.querySelector('.no-results-msg');
        if (visibleCount === 0 && searchTerm !== '') {
            if (!noResultsMsg) {
                const msg = document.createElement('div');
                msg.className = 'no-results-msg';
                msg.style.textAlign = 'center';
                msg.style.padding = '20px';
                msg.style.color = '#888';
                msg.style.fontSize = '12px';
                msg.innerHTML = 'No matching investors found';
                container.appendChild(msg);
            }
        } else if (noResultsMsg) {
            noResultsMsg.remove();
        }
    });
}

function setupCompletedInvestorsSearch() {
    const searchInput = document.getElementById('completed-investors-search');
    if (!searchInput) return;
    
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const userItems = document.querySelectorAll('#completed-investors-list .revenue-user-item');
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
        
        const container = document.getElementById('completed-investors-list');
        const noResultsMsg = container.querySelector('.no-results-msg');
        if (visibleCount === 0 && searchTerm !== '') {
            if (!noResultsMsg) {
                const msg = document.createElement('div');
                msg.className = 'no-results-msg';
                msg.style.textAlign = 'center';
                msg.style.padding = '20px';
                msg.style.color = '#888';
                msg.style.fontSize = '12px';
                msg.innerHTML = 'No matching investors found';
                container.appendChild(msg);
            }
        } else if (noResultsMsg) {
            noResultsMsg.remove();
        }
    });
}

// ============ SHOW MESSAGE HELPER ============
function showMessage(message, type) {
    const container = document.querySelector('.container');
    if (!container) return;
    
    const existingMsg = container.querySelector('.message');
    if (existingMsg) existingMsg.remove();
    
    const msgDiv = document.createElement('div');
    msgDiv.className = 'message';
    msgDiv.style.cssText = `
        padding: 12px 20px;
        margin: 10px 0;
        border-radius: 8px;
        background: ${type === 'success' ? 'rgba(46, 204, 113, 0.1)' : 'rgba(231, 76, 60, 0.1)'};
        border: 1px solid ${type === 'success' ? '#27ae60' : '#e74c3c'};
        color: ${type === 'success' ? '#27ae60' : '#e74c3c'};
        font-weight: 500;
    `;
    msgDiv.textContent = message;
    container.insertBefore(msgDiv, container.firstChild);
    
    setTimeout(() => {
        msgDiv.style.opacity = '0';
        msgDiv.style.transition = 'opacity 0.5s';
        setTimeout(() => msgDiv.remove(), 500);
    }, 5000);
}

// ============ LIVE USER DATA UPDATES ============
var updateIntervals = {};
var isLiveUpdateRunning = false;

function startLiveUserUpdates(intervalSeconds = 30) {
    const userRows = document.querySelectorAll('.user-row');
    userRows.forEach(row => {
        const userId = row.dataset.userId;
        const sourceTable = row.dataset.sourceTable;
        
        if (userId && sourceTable && !updateIntervals[userId]) {
            fetchUserLiveData(userId, sourceTable, row);
            updateIntervals[userId] = setInterval(() => {
                fetchUserLiveData(userId, sourceTable, row);
            }, intervalSeconds * 1000);
        }
    });
}

function fetchUserLiveData(userId, sourceTable, rowElement) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        credentials: 'same-origin',
        body: 'user_id=' + encodeURIComponent(userId) + '&source_table=' + encodeURIComponent(sourceTable)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            rowElement.classList.add('updating-row');
            setTimeout(() => rowElement.classList.remove('updating-row'), 300);
            
            const brokerBalanceCell = rowElement.querySelector('.broker-balance-cell');
            if (brokerBalanceCell && data.broker_balance !== undefined) {
                animateValue(brokerBalanceCell, brokerBalanceCell.innerText.replace(/[^0-9.-]/g, ''), data.broker_balance, '$');
            }
            
            const profitLossCell = rowElement.querySelector('.profit-loss-cell');
            if (profitLossCell && data.profit_loss !== undefined) {
                const currentText = profitLossCell.innerText.replace(/[^0-9.-]/g, '');
                animateValue(profitLossCell, currentText, data.profit_loss, data.profit_loss >= 0 ? '+' : '');
                profitLossCell.className = 'profit-loss-cell ' + (data.profit_loss >= 0 ? 'profit' : 'loss');
            }
            
            const currentBalanceCell = rowElement.querySelector('.current-balance-cell');
            if (currentBalanceCell && data.current_balance !== undefined) {
                animateValue(currentBalanceCell, currentBalanceCell.innerText.replace('$', ''), data.current_balance, '$');
                currentBalanceCell.className = 'current-balance-cell ' + (data.current_balance >= 0 ? 'profit' : 'loss');
            }
        }
    })
    .catch(error => {
        // Silently fail - live updates are optional
    });
}

function animateValue(element, start, end, prefix = '', suffix = '', duration = 300) {
    if (!element) return;
    
    start = parseFloat(start.toString().replace(/[^0-9.-]/g, '')) || 0;
    end = parseFloat(end.toString().replace(/[^0-9.-]/g, '')) || 0;
    
    if (start === end) return;
    
    const range = end - start;
    let current = start;
    let startTime = null;
    
    function step(timestamp) {
        if (!startTime) startTime = timestamp;
        const elapsed = timestamp - startTime;
        const progress = Math.min(1, elapsed / duration);
        current = start + (range * progress);
        element.innerText = prefix + current.toFixed(2) + suffix;
        
        if (progress < 1) {
            requestAnimationFrame(step);
        } else {
            element.innerText = prefix + end.toFixed(2) + suffix;
        }
    }
    
    requestAnimationFrame(step);
}
</script>