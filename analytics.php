<?php
// analytics.php
// This file is included in serveraccount.php when view=analytics
?>

<h2>Analytics Dashboard</h2>

<div class="analytics-container" id="analytics-container">
    <div class="analytics-split" id="analytics-split">
        <div class="users-sidebar" id="users-sidebar">
            <div class="users-sidebar-header" onclick="Analytics.showAllUsersModal()">
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
        
        <div class="analytics-content" id="analytics-content">
            <div class="info-message">
                Select a user to view their trading analytics
            </div>
        </div>
    </div>
</div>

<button id="floating-stats-btn" class="floating-stats-btn" style="display: none;">📊</button>

<div id="stats-panel-overlay" class="stats-panel-overlay" style="display: none;">
    <div class="stats-panel-overlay-bg"></div>
    <div class="stats-panel-container">
        <div class="stats-panel-header">
            <span>Navigation</span>
            <span class="close-stats-panel" onclick="Analytics.toggleStatsPanel()">✕</span>
        </div>
        <div class="stats-panel-content" id="stats-panel-content"></div>
    </div>
</div>

<div id="custom-alert" class="custom-alert" style="display: none;">
    <div class="custom-alert-content">
        <div class="custom-alert-icon" id="custom-alert-icon">⚠️</div>
        <div class="custom-alert-message" id="custom-alert-message"></div>
        <button class="custom-alert-btn" onclick="Analytics.hideCustomAlert()">OK</button>
    </div>
</div>

<script>
    const Analytics = {
        users: [],
        selectedUser: null,
        analyticsData: null,
        
        // State
        currentTradeType: 'trades_within_risks_config',
        currentAuthType: 'authorized',
        
        getDefaultAnalyticsStructure: function() {
            return {
                from_execution_start_date: {
                    start_date: null,
                    end_date: null,
                    last_updated: null,
                    trades_within_risks_config: {
                        summaries: {
                            summaries_of_profits_only: {
                                total_lost_trades: 0,
                                total_won_trades: 0,
                                total_lost_trades_amount: 0,
                                total_won_trades_amount: 0,
                                lowest_trades_per_day: 0,
                                highest_trades_per_day: 0,
                                average_trades_per_day: 0,
                                lowest_trade_dates: [],
                                highest_trade_dates: [],
                                average_trade_dates: []
                            }
                        },
                        regular_data: {
                            authorized: {
                                total_trades: 0,
                                total_pnl: 0,
                                profit_trades: 0,
                                loss_trades: 0,
                                profit_amount: 0,
                                loss_amount: 0,
                                win_rate_by_count_percentage: 0.0,
                                loss_rate_by_count_percentage: 0.0,
                                win_rate_by_revenue_percentage: 0.0,
                                loss_rate_by_revenue_percentage: 0.0,
                                all_traded_symbols: {},
                                symbols_traded: 0,
                                closed_deals_with_sl_tp: 0,
                                closed_deals_without_sl_tp: 0,
                                highest_sequential_losses: {},
                                highest_sequential_days_in_loss: {},
                                highest_loss_per_trade: 0,
                                daily_trades_record: {}
                            },
                            unauthorized: {
                                total_trades: 0,
                                total_pnl: 0,
                                profit_trades: 0,
                                loss_trades: 0,
                                profit_amount: 0,
                                loss_amount: 0,
                                win_rate_by_count_percentage: 0.0,
                                loss_rate_by_count_percentage: 0.0,
                                win_rate_by_revenue_percentage: 0.0,
                                loss_rate_by_revenue_percentage: 0.0,
                                all_traded_symbols: {},
                                symbols_traded: 0,
                                closed_deals_with_sl_tp: 0,
                                closed_deals_without_sl_tp: 0,
                                highest_sequential_losses: {},
                                highest_sequential_days_in_loss: {},
                                highest_loss_per_trade: 0,
                                daily_trades_record: {}
                            }
                        }
                    },
                    trades_outside_risks_config: {
                        summaries: {
                            summaries_of_profits_only: {
                                total_lost_trades: 0,
                                total_won_trades: 0,
                                total_lost_trades_amount: 0,
                                total_won_trades_amount: 0,
                                lowest_trades_per_day: 0,
                                highest_trades_per_day: 0,
                                average_trades_per_day: 0,
                                lowest_trade_dates: [],
                                highest_trade_dates: [],
                                average_trade_dates: []
                            }
                        },
                        regular_data: {
                            authorized: {
                                total_trades: 0,
                                total_pnl: 0,
                                profit_trades: 0,
                                loss_trades: 0,
                                profit_amount: 0,
                                loss_amount: 0,
                                win_rate_by_count_percentage: 0.0,
                                loss_rate_by_count_percentage: 0.0,
                                win_rate_by_revenue_percentage: 0.0,
                                loss_rate_by_revenue_percentage: 0.0,
                                all_traded_symbols: {},
                                symbols_traded: 0,
                                closed_deals_with_sl_tp: 0,
                                closed_deals_without_sl_tp: 0,
                                highest_sequential_losses: {},
                                highest_sequential_days_in_loss: {},
                                highest_loss_per_trade: 0,
                                daily_trades_record: {}
                            },
                            unauthorized: {
                                total_trades: 0,
                                total_pnl: 0,
                                profit_trades: 0,
                                loss_trades: 0,
                                profit_amount: 0,
                                loss_amount: 0,
                                win_rate_by_count_percentage: 0.0,
                                loss_rate_by_count_percentage: 0.0,
                                win_rate_by_revenue_percentage: 0.0,
                                loss_rate_by_revenue_percentage: 0.0,
                                all_traded_symbols: {},
                                symbols_traded: 0,
                                closed_deals_with_sl_tp: 0,
                                closed_deals_without_sl_tp: 0,
                                highest_sequential_losses: {},
                                highest_sequential_days_in_loss: {},
                                highest_loss_per_trade: 0,
                                daily_trades_record: {}
                            }
                        }
                    }
                }
            };
        },
        
        mergeWithDefault: function(receivedData) {
            const defaultData = this.getDefaultAnalyticsStructure();
            if (!receivedData || typeof receivedData !== 'object') return defaultData;
            
            const deepMerge = (target, source) => {
                for (const key in source) {
                    if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
                        if (!target[key]) target[key] = {};
                        deepMerge(target[key], source[key]);
                    } else {
                        if (source[key] !== undefined && source[key] !== null) {
                            target[key] = source[key];
                        }
                    }
                }
                return target;
            };
            
            return deepMerge(JSON.parse(JSON.stringify(defaultData)), receivedData);
        },
        
        showCustomAlert: function(message, icon = '⚠️') {
            const alertDiv = document.getElementById('custom-alert');
            const iconDiv = document.getElementById('custom-alert-icon');
            const messageDiv = document.getElementById('custom-alert-message');
            
            iconDiv.textContent = icon;
            messageDiv.textContent = message;
            alertDiv.style.display = 'flex';
            
            setTimeout(() => {
                this.hideCustomAlert();
            }, 3000);
        },
        
        hideCustomAlert: function() {
            document.getElementById('custom-alert').style.display = 'none';
        },
        
        init: function() {
            this.loadUsers();
            this.bindEvents();
        },
        
        bindEvents: function() {
            document.getElementById('floating-stats-btn').addEventListener('click', () => this.toggleStatsPanel());
        },
        
        loadUsers: function() {
            fetch(window.location.pathname, {
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
                    this.users = data.users;
                    if (this.users.length > 0) {
                        this.setDefaultUser(this.users[0]);
                    }
                    this.renderDefaultUser();
                } else {
                    document.getElementById('default-user-card').innerHTML = '<div class="info-message-small">Error loading users</div>';
                }
            })
            .catch(error => {
                console.error('Error loading users:', error);
                document.getElementById('default-user-card').innerHTML = '<div class="info-message-small">Error loading users</div>';
            });
        },
        
        setDefaultUser: function(user) {
            this.selectedUser = user;
            this.loadAnalytics(user.id, user.source);
        },
        
        renderDefaultUser: function() {
            const container = document.getElementById('default-user-card');
            if (this.selectedUser) {
                container.innerHTML = `
                    <div class="default-user-info" onclick="Analytics.showAllUsersModal()">
                        <div class="default-user-name">${this.escapeHtml(this.selectedUser.fullname || 'N/A')}</div>
                        <div class="default-user-email">${this.escapeHtml(this.selectedUser.email || 'N/A')}</div>
                        <div class="default-user-id">ID: ${this.selectedUser.id}</div>
                    </div>
                `;
            }
        },
        
        showAllUsersModal: function() {
            this.addBodyBlur();
            
            const modalHtml = `
                <div class="modal-overlay" id="users-modal-overlay" onclick="Analytics.closeModalIfClickOutside(event)">
                    <div class="modal-container users-modal" onclick="event.stopPropagation()">
                        <div class="modal-header">
                            <span>All Users (${this.users.length})</span>
                            <span class="modal-close" onclick="Analytics.closeModal()">✕</span>
                        </div>
                        <div class="modal-body">
                            <div class="users-modal-search">
                                <input type="text" id="users-modal-search-input" class="user-search-input" placeholder="Search users..." onkeyup="Analytics.filterModalUsers()">
                            </div>
                        </div>
                        <div class="modal-body" id="users-modal-list">
                                ${this.renderModalUsersList(this.users)}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        },
        
        renderModalUsersList: function(users) {
            if (users.length === 0) {
                return '<div class="info-message-small">No users found</div>';
            }
            
            return users.map(user => `
                <div class="modal-user-item ${this.selectedUser && this.selectedUser.id === user.id ? 'selected' : ''}" 
                    onclick="Analytics.selectUserFromModal(${user.id}, '${user.source}')">
                    <div class="modal-user-name">${this.escapeHtml(user.fullname || 'N/A')}</div>
                    <div class="modal-user-email">${this.escapeHtml(user.email || 'N/A')}</div>
                    <div class="modal-user-id">ID: ${user.id}</div>
                </div>
            `).join('');
        },
        
        filterModalUsers: function() {
            const searchTerm = document.getElementById('users-modal-search-input').value.toLowerCase();
            const filteredUsers = this.users.filter(user => 
                (user.fullname && user.fullname.toLowerCase().includes(searchTerm)) ||
                (user.email && user.email.toLowerCase().includes(searchTerm)) ||
                user.id.toString().includes(searchTerm)
            );
            
            const container = document.getElementById('users-modal-list');
            if (container) {
                container.innerHTML = this.renderModalUsersList(filteredUsers);
            }
        },
        
        selectUserFromModal: function(userId, source) {
            const user = this.users.find(u => u.id == userId);
            if (!user) return;
            
            this.selectedUser = user;
            this.renderDefaultUser();
            this.loadAnalytics(userId, source);
            this.closeModal();
        },
        
        closeModal: function() {
            const overlay = document.getElementById('users-modal-overlay');
            if (overlay) {
                overlay.remove();
            }
            this.removeBodyBlur();
        },
        
        closeModalIfClickOutside: function(event) {
            if (event.target.id === 'users-modal-overlay') {
                this.closeModal();
            }
        },
        
        addBodyBlur: function() {
            const container = document.getElementById('analytics-container');
            if (container) {
                container.classList.add('blur-background');
            }
        },
        
        removeBodyBlur: function() {
            const container = document.getElementById('analytics-container');
            if (container) {
                container.classList.remove('blur-background');
            }
        },
        
        selectUser: function(userId, source) {
            const user = this.users.find(u => u.id == userId);
            if (!user) return;
            
            this.selectedUser = user;
            this.renderDefaultUser();
            this.loadAnalytics(userId, source);
        },
        
        loadAnalytics: function(userId, source) {
            this.showLoading();
            
            fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=get_user_analytics&user_id=${userId}&source_table=${source}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let analyticsData = null;
                    if (data.analytics && data.analytics !== 'null' && data.analytics !== '') {
                        try {
                            analyticsData = typeof data.analytics === 'string' ? JSON.parse(data.analytics) : data.analytics;
                        } catch(e) {
                            console.error('JSON parse error:', e);
                        }
                    }
                    this.analyticsData = this.mergeWithDefault(analyticsData);
                    this.renderAnalytics();
                    this.showFloatingButton();
                } else {
                    this.analyticsData = this.getDefaultAnalyticsStructure();
                    this.renderAnalytics();
                }
            })
            .catch(error => {
                console.error('Error loading analytics:', error);
                this.analyticsData = this.getDefaultAnalyticsStructure();
                this.renderAnalytics();
            });
        },
        
        showFloatingButton: function() {
            document.getElementById('floating-stats-btn').style.display = 'flex';
        },
        
        toggleStatsPanel: function() {
            const overlay = document.getElementById('stats-panel-overlay');
            if (overlay.style.display === 'flex') {
                overlay.style.display = 'none';
                this.removeBodyBlur();
            } else {
                overlay.style.display = 'flex';
                this.addBodyBlur();
                this.renderStatsPanel();
            }
        },
        
        renderStatsPanel: function() {
            const container = document.getElementById('stats-panel-content');
            
            container.innerHTML = `
                <div style="margin-bottom: 15px;">
                    <div class="stat-option-title" style="margin-bottom: 8px;">Trade Type</div>
                    <div class="stat-option ${this.currentTradeType === 'trades_within_risks_config' ? 'active' : ''}" onclick="Analytics.setTradeType('trades_within_risks_config'); Analytics.toggleStatsPanel();">
                        <div class="stat-option-title">Within Risk Config</div>
                    </div>
                    <div class="stat-option ${this.currentTradeType === 'trades_outside_risks_config' ? 'active' : ''}" onclick="Analytics.setTradeType('trades_outside_risks_config'); Analytics.toggleStatsPanel();">
                        <div class="stat-option-title">Outside Risk Config</div>
                    </div>
                </div>
                <div style="margin-bottom: 15px; padding-top: 10px; border-top: 1px solid var(--border-color);">
                    <div class="stat-option-title" style="margin-bottom: 8px;">Authorization</div>
                    <div class="stat-option ${this.currentAuthType === 'authorized' ? 'active' : ''}" onclick="Analytics.setAuthType('authorized'); Analytics.toggleStatsPanel();">
                        <div class="stat-option-title">Authorized Trades</div>
                    </div>
                    <div class="stat-option ${this.currentAuthType === 'unauthorized' ? 'active' : ''}" onclick="Analytics.setAuthType('unauthorized'); Analytics.toggleStatsPanel();">
                        <div class="stat-option-title">Unauthorized Trades</div>
                    </div>
                </div>
                <div style="padding-top: 10px; border-top: 1px solid var(--border-color);">
                    <div class="stat-option-title" style="margin-bottom: 8px;">View Trades</div>
                    <div class="stat-option" onclick="Analytics.showAllTradesModal(); Analytics.toggleStatsPanel();">
                        <div class="stat-option-title">All Trades</div>
                        <div class="stat-option-desc">View complete trade history</div>
                    </div>
                </div>
            `;
        },
        
        showAllTradesModal: function() {
            this.addBodyBlur();
            
            const currentData = this.getCurrentData();
            if (!currentData) {
                this.showCustomAlert('No data available for the selected filters', '📊');
                this.removeBodyBlur();
                return;
            }
            
            const tradeData = currentData[this.currentTradeType];
            const regularData = tradeData?.regular_data?.[this.currentAuthType];
            
            let allTradesList = [];
            const sequentialLosses = regularData?.highest_sequential_losses || {};
            if (sequentialLosses.trades && Array.isArray(sequentialLosses.trades)) {
                allTradesList.push(...sequentialLosses.trades);
            }
            
            const daysLoss = regularData?.highest_sequential_days_in_loss || {};
            if (daysLoss.days) {
                for (const date in daysLoss.days) {
                    if (daysLoss.days[date] && Array.isArray(daysLoss.days[date])) {
                        allTradesList.push(...daysLoss.days[date]);
                    }
                }
            }
            
            const uniqueTrades = [];
            const tickets = new Set();
            for (const trade of allTradesList) {
                if (trade.ticket && !tickets.has(trade.ticket)) {
                    tickets.add(trade.ticket);
                    uniqueTrades.push(trade);
                }
            }
            
            const hasNoTrades = uniqueTrades.length === 0;
            
            uniqueTrades.sort((a, b) => (b.time_open || '').localeCompare(a.time_open || ''));
            
            let modalHtml = `
                <div class="modal-overlay" id="trades-modal-overlay" onclick="Analytics.closeModalIfClickOutsideTrades(event)">
                    <div class="modal-container modal-large" onclick="event.stopPropagation()">
                        <div class="modal-header">
                            <span>All Trades (${uniqueTrades.length})</span>
                            <span class="modal-close" onclick="Analytics.closeTradesModal()">✕</span>
                        </div>
                        <div class="modal-body">
                            ${hasNoTrades ? `
                                <div class="empty-state">
                                    <div class="empty-state-icon">📭</div>
                                    <div class="empty-state-text">No trades available for the selected filters</div>
                                    <div class="empty-state-sub">Try changing the trade type or authorization filter</div>
                                </div>
                            ` : `
                                <div class="trades-table-wrapper">
                                    <table class="trades-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Ticket</th>
                                                <th>Symbol</th>
                                                <th>Type</th>
                                                <th>Volume</th>
                                                <th>P&L</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${uniqueTrades.map(trade => `
                                                <tr>
                                                    <td>${trade.time_open ? trade.time_open.split(' ')[0] : 'N/A'}</td>
                                                    <td>${trade.ticket || 'N/A'}</td>
                                                    <td>${this.escapeHtml(trade.symbol || 'N/A')}</td>
                                                    <td>${trade.type || 'N/A'}</td>
                                                    <td>${trade.volume || 'N/A'}</td>
                                                    <td class="${(trade.total_pnl || 0) >= 0 ? 'profit' : 'loss'}">$${this.formatNumber(trade.total_pnl || 0)}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            `}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        },
        
        closeTradesModal: function() {
            const overlay = document.getElementById('trades-modal-overlay');
            if (overlay) {
                overlay.remove();
            }
            this.removeBodyBlur();
        },
        
        closeModalIfClickOutsideTrades: function(event) {
            if (event.target.id === 'trades-modal-overlay') {
                this.closeTradesModal();
            }
        },
        
        showSequentialLossesModal: function() {
            this.addBodyBlur();
            
            const currentData = this.getCurrentData();
            if (!currentData) {
                this.showCustomAlert('No data available for the selected filters', '📊');
                this.removeBodyBlur();
                return;
            }
            
            const tradeData = currentData[this.currentTradeType];
            const regularData = tradeData?.regular_data?.[this.currentAuthType];
            const losses = regularData?.highest_sequential_losses || {};
            
            const hasNoData = !losses.consecutive_losses_count;
            
            let modalHtml = `
                <div class="modal-overlay" id="losses-modal-overlay" onclick="Analytics.closeModalIfClickOutsideLosses(event)">
                    <div class="modal-container" onclick="event.stopPropagation()">
                        <div class="modal-header">
                            <span>Highest Sequential Losses</span>
                            <span class="modal-close" onclick="Analytics.closeLossesModal()">✕</span>
                        </div>
                        <div class="modal-body">
                            ${hasNoData ? `
                                <div class="empty-state">
                                    <div class="empty-state-icon">✅</div>
                                    <div class="empty-state-text">No sequential losses recorded</div>
                                    <div class="empty-state-sub">The user has no consecutive losing trades</div>
                                </div>
                            ` : `
                                <div class="losses-card">
                                    <div class="loss-value">${losses.consecutive_losses_count} Consecutive Losses</div>
                                    <div style="margin-top: 10px;">Total Loss: $${this.formatNumber(losses.total_loss_pnl || 0)}</div>
                                </div>
                                ${losses.trades && losses.trades.length > 0 ? `
                                    <div class="trades-table-wrapper">
                                        <table class="trades-table">
                                            <thead>
                                                <tr>
                                                    <th>Ticket</th>
                                                    <th>Symbol</th>
                                                    <th>Type</th>
                                                    <th>Volume</th>
                                                    <th>P&L</th>
                                                    <th>Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${losses.trades.map(trade => `
                                                    <tr>
                                                        <td>${trade.ticket || 'N/A'}</td>
                                                        <td>${this.escapeHtml(trade.symbol || 'N/A')}</td>
                                                        <td>${trade.type || 'N/A'}</td>
                                                        <td>${trade.volume || 'N/A'}</td>
                                                        <td class="loss">$${this.formatNumber(trade.total_pnl || 0)}</td>
                                                        <td>${trade.time_open ? trade.time_open.split(' ')[0] : 'N/A'}</td>
                                                    </tr>
                                                `).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                ` : ''}
                            `}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        },
        
        closeLossesModal: function() {
            const overlay = document.getElementById('losses-modal-overlay');
            if (overlay) {
                overlay.remove();
            }
            this.removeBodyBlur();
        },
        
        closeModalIfClickOutsideLosses: function(event) {
            if (event.target.id === 'losses-modal-overlay') {
                this.closeLossesModal();
            }
        },
        
        showSequentialDaysLossModal: function() {
            this.addBodyBlur();
            
            const currentData = this.getCurrentData();
            if (!currentData) {
                this.showCustomAlert('No data available for the selected filters', '📊');
                this.removeBodyBlur();
                return;
            }
            
            const tradeData = currentData[this.currentTradeType];
            const regularData = tradeData?.regular_data?.[this.currentAuthType];
            const daysLoss = regularData?.highest_sequential_days_in_loss || {};
            
            const hasNoData = !daysLoss.consecutive_days_count;
            
            let modalHtml = `
                <div class="modal-overlay" id="daysloss-modal-overlay" onclick="Analytics.closeModalIfClickOutsideDaysLoss(event)">
                    <div class="modal-container" onclick="event.stopPropagation()">
                        <div class="modal-header">
                            <span>Highest Sequential Days in Loss</span>
                            <span class="modal-close" onclick="Analytics.closeDaysLossModal()">✕</span>
                        </div>
                        <div class="modal-body">
                            ${hasNoData ? `
                                <div class="empty-state">
                                    <div class="empty-state-icon">✅</div>
                                    <div class="empty-state-text">No sequential days in loss recorded</div>
                                    <div class="empty-state-sub">The user has no consecutive losing days</div>
                                </div>
                            ` : `
                                <div class="losses-card">
                                    <div class="loss-value">${daysLoss.consecutive_days_count} Consecutive Days in Loss</div>
                                    <div style="margin-top: 10px;">Total Loss: $${this.formatNumber(daysLoss.total_loss_pnl || 0)}</div>
                                </div>
                                ${daysLoss.days ? `
                                    <div class="section-title">Daily Breakdown</div>
                                    <div class="stats-grid">
                                        ${Object.entries(daysLoss.days).map(([date, trades]) => {
                                            const dailyTotal = trades.reduce((sum, t) => sum + (t.total_pnl || 0), 0);
                                            return `
                                                <div class="stat-card">
                                                    <div class="stat-label">${date}</div>
                                                    <div class="stat-value loss">$${this.formatNumber(dailyTotal)}</div>
                                                    <div class="stat-label">${trades.length} trades</div>
                                                </div>
                                            `;
                                        }).join('')}
                                    </div>
                                ` : ''}
                            `}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        },
        
        closeDaysLossModal: function() {
            const overlay = document.getElementById('daysloss-modal-overlay');
            if (overlay) {
                overlay.remove();
            }
            this.removeBodyBlur();
        },
        
        closeModalIfClickOutsideDaysLoss: function(event) {
            if (event.target.id === 'daysloss-modal-overlay') {
                this.closeDaysLossModal();
            }
        },
        
        setTradeType: function(tradeType) {
            this.currentTradeType = tradeType;
            this.renderAnalytics();
        },
        
        setAuthType: function(authType) {
            this.currentAuthType = authType;
            this.renderAnalytics();
        },
        
        getCurrentData: function() {
            return this.analyticsData?.from_execution_start_date;
        },
        
        renderAnalytics: function() {
            if (!this.analyticsData) {
                this.showNoAnalyticsMessage();
                return;
            }
            
            const container = document.getElementById('analytics-content');
            const currentData = this.getCurrentData();
            
            if (!currentData) {
                container.innerHTML = '<div class="info-message">No data available for the selected options</div>';
                return;
            }
            
            const tradeData = currentData[this.currentTradeType];
            const summaries = tradeData?.summaries?.summaries_of_profits_only || {
                total_lost_trades: 0,
                total_won_trades: 0,
                total_lost_trades_amount: 0,
                total_won_trades_amount: 0,
                lowest_trades_per_day: 0,
                highest_trades_per_day: 0,
                average_trades_per_day: 0,
                lowest_trade_dates: [],
                highest_trade_dates: [],
                average_trade_dates: []
            };
            
            const regularData = tradeData?.regular_data?.[this.currentAuthType] || {
                total_trades: 0,
                total_pnl: 0,
                profit_trades: 0,
                loss_trades: 0,
                profit_amount: 0,
                loss_amount: 0,
                win_rate_by_count_percentage: 0.0,
                loss_rate_by_count_percentage: 0.0,
                win_rate_by_revenue_percentage: 0.0,
                loss_rate_by_revenue_percentage: 0.0,
                all_traded_symbols: {},
                symbols_traded: 0,
                closed_deals_with_sl_tp: 0,
                closed_deals_without_sl_tp: 0,
                highest_sequential_losses: {},
                highest_sequential_days_in_loss: {},
                highest_loss_per_trade: 0,
                daily_trades_record: {}
            };
            
            // Format dates for display
            const startDate = currentData.start_date ? this.formatDateDisplay(currentData.start_date) : 'N/A';
            const endDate = currentData.end_date ? this.formatDateDisplay(currentData.end_date) : 'N/A';
            
            // Check if sequential losses exist
            const hasSequentialLosses = regularData.highest_sequential_losses && regularData.highest_sequential_losses.consecutive_losses_count;
            const hasSequentialDaysLoss = regularData.highest_sequential_days_in_loss && regularData.highest_sequential_days_in_loss.consecutive_days_count;
            
            container.innerHTML = `
                <div class="analytics-header">
                    <h2>${this.escapeHtml(this.selectedUser?.fullname || 'User')} - Trading Analytics</h2>
                    <div class="selected-user-info">
                        <strong>From </strong> ${startDate}
                        <strong>To:</strong> ${endDate}
                    </div>
                </div>
                
                <!-- Daily Trades Calendar Button -->
                <div style="text-align: center; margin: 20px 0;">
                    <button class="calendar-toggle-btn" onclick="Analytics.showCalendarOverlay()">
                        <span>📅</span> View Daily Trades Calendar
                    </button>
                </div>
            
                
                <div class="section-title">
                    ${this.currentAuthType === 'authorized' ? 'Authorized Trades' : 'Unauthorized Trades'} ${this.currentTradeType === 'trades_within_risks_config' ? 'Within Risk Configuration' : 'Outside Risk Configuration'}
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Loss Amount</div>
                        <div class="stat-value loss">$${this.formatNumber(regularData.loss_amount || 0)}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Profit Amount</div>
                        <div class="stat-value profit">$${this.formatNumber(regularData.profit_amount || 0)}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Lost Trades (Count)</div>
                        <div class="stat-value loss">${summaries.total_lost_trades || 0}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Won Trades (Count)</div>
                        <div class="stat-value profit">${summaries.total_won_trades || 0}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Loss Rate (Count)</div>
                        <div class="stat-value loss">${this.formatNumber(regularData.loss_rate_by_count_percentage || 0)}%</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Win Rate by Count</div>
                        <div class="stat-value profit">${this.formatNumber(regularData.win_rate_by_count_percentage || 0)}%</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Loss Rate Revenue</div>
                        <div class="stat-value loss">${this.formatNumber(regularData.loss_rate_by_revenue_percentage || 0)}%</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Win Rate by Revenue</div>
                        <div class="stat-value profit">${this.formatNumber(regularData.win_rate_by_revenue_percentage || 0)}%</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Highest Loss/Trade</div>
                        <div class="stat-value loss">$${this.formatNumber(regularData.highest_loss_per_trade || 0)}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Deals w/o SL/TP</div>
                        <div class="stat-value">${regularData.closed_deals_without_sl_tp || 0}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Deals with SL/TP</div>
                        <div class="stat-value">${regularData.closed_deals_with_sl_tp || 0}</div>
                    </div>
                    <div class="stat-card clickable" onclick="Analytics.showTradedSymbolsModal()" style="cursor: pointer;">
                        <div class="stat-label">Total Trades</div>
                        <div class="stat-value">${regularData.total_trades || 0}</div>
                        <div style="font-size: 10px; color: #888; margin-top: 5px;">Click to view symbols</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total P&L</div>
                        <div class="stat-value ${(regularData.total_pnl || 0) >= 0 ? 'profit' : 'loss'}">$${this.formatNumber(regularData.total_pnl || 0)}</div>
                    </div>
                    <div class="stat-card daily-stat-card lowest">
                        <div class="stat-label">📉 Lowest Trades/Day</div>
                        <div class="stat-value">${summaries.lowest_trades_per_day || 0}</div>
                        ${summaries.lowest_trade_dates && summaries.lowest_trade_dates.length > 0 ? `<div class="stat-dates">${summaries.lowest_trade_dates.map(d => this.escapeHtml(d)).join(', ')}</div>` : '<div class="stat-dates">—</div>'}
                    </div>
                    <div class="stat-card daily-stat-card average">
                        <div class="stat-label">⚖️ Average Trades/Day</div>
                        <div class="stat-value">${typeof summaries.average_trades_per_day === 'number' ? summaries.average_trades_per_day.toFixed(2) : (summaries.average_trades_per_day || 0)}</div>
                        ${summaries.average_trade_dates && summaries.average_trade_dates.length > 0 ? `<div class="stat-dates">${summaries.average_trade_dates.map(d => this.escapeHtml(d)).join(', ')}</div>` : '<div class="stat-dates">—</div>'}
                    </div>
                    <div class="stat-card daily-stat-card highest">
                        <div class="stat-label">📈 Highest Trades/Day</div>
                        <div class="stat-value">${summaries.highest_trades_per_day || 0}</div>
                        ${summaries.highest_trade_dates && summaries.highest_trade_dates.length > 0 ? `<div class="stat-dates">${summaries.highest_trade_dates.map(d => this.escapeHtml(d)).join(', ')}</div>` : '<div class="stat-dates">—</div>'}
                    </div>
                    <div class="stat-card daily-stat-card sequential-loss clickable" onclick="Analytics.showSequentialLossesModal()" style="cursor: pointer; ${hasSequentialLosses ? 'border-left: 4px solid #ff6b6b;' : 'border-left: 4px solid #888;'}">
                        <div class="stat-label">📉 Consecutive Lost Trades</div>
                        <div class="stat-value" style="color: ${hasSequentialLosses ? '#ff6b6b' : '#888'};">${hasSequentialLosses ? regularData.highest_sequential_losses.consecutive_losses_count : '0'}</div>
                        ${hasSequentialLosses ? `<div style="font-size: 10px; color: #888; margin-top: 5px;">Total Loss: $${this.formatNumber(regularData.highest_sequential_losses.total_loss_pnl || 0)}</div>` : '<div class="stat-dates">No data</div>'}
                        <div style="font-size: 9px; color: #888; margin-top: 3px;">Click to view details</div>
                    </div>
                    <div class="stat-card daily-stat-card sequential-days clickable" onclick="Analytics.showSequentialDaysLossModal()" style="cursor: pointer; ${hasSequentialDaysLoss ? 'border-left: 4px solid #ff6b6b;' : 'border-left: 4px solid #888;'}">
                        <div class="stat-label">📉 Consecutive Losing Days</div>
                        <div class="stat-value" style="color: ${hasSequentialDaysLoss ? '#ff6b6b' : '#888'};">${hasSequentialDaysLoss ? regularData.highest_sequential_days_in_loss.consecutive_days_count : '0'}</div>
                        ${hasSequentialDaysLoss ? `<div style="font-size: 10px; color: #888; margin-top: 5px;">Total Loss: $${this.formatNumber(regularData.highest_sequential_days_in_loss.total_loss_pnl || 0)}</div>` : '<div class="stat-dates">No data</div>'}
                        <div style="font-size: 9px; color: #888; margin-top: 3px;">Click to view details</div>
                    </div>
                </div>
            `;
        },
        
        showTradedSymbolsModal: function() {
            this.addBodyBlur();
            
            const currentData = this.getCurrentData();
            if (!currentData) {
                this.showCustomAlert('No data available for the selected filters', '📊');
                this.removeBodyBlur();
                return;
            }
            
            const tradeData = currentData[this.currentTradeType];
            const regularData = tradeData?.regular_data?.[this.currentAuthType];
            const symbols = regularData?.all_traded_symbols || {};
            
            const hasNoSymbols = Object.keys(symbols).length === 0;
            
            let modalHtml = `
                <div class="modal-overlay" id="symbols-modal-overlay" onclick="Analytics.closeModalIfClickOutsideSymbols(event)">
                    <div class="modal-container modal-large" onclick="event.stopPropagation()">
                        <div class="modal-header">
                            <span>Traded Symbols (${Object.keys(symbols).length})</span>
                            <span class="modal-close" onclick="Analytics.closeSymbolsModal()">✕</span>
                        </div>
                        <div class="modal-body">
                            ${hasNoSymbols ? `
                                <div class="empty-state">
                                    <div class="empty-state-icon">📊</div>
                                    <div class="empty-state-text">No symbols traded</div>
                                    <div class="empty-state-sub">No trading data available for the selected filters</div>
                                </div>
                            ` : `
                                <div class="contest-grid">
                                    ${Object.values(symbols).map(symbol => `
                                        <div class="contest-card">
                                            <h4>${this.escapeHtml(symbol.symbol)}</h4>
                                            <div class="symbol-info">
                                                <span>Total Trades:</span>
                                                <span>${symbol.total_trades || 0}</span>
                                            </div>
                                            <div class="symbol-info">
                                                <span>Total Profit:</span>
                                                <span class="${(symbol.total_profit || 0) >= 0 ? 'profit' : 'loss'}">$${this.formatNumber(symbol.total_profit || 0)}</span>
                                            </div>
                                            <div class="symbol-info">
                                                <span>Total Loss:</span>
                                                <span class="loss">$${this.formatNumber(symbol.total_loss || 0)}</span>
                                            </div>
                                            <div class="symbol-info">
                                                <span>Net P&L:</span>
                                                <span class="${(symbol.total_profit || 0) - (symbol.total_loss || 0) >= 0 ? 'profit' : 'loss'}">$${this.formatNumber((symbol.total_profit || 0) - (symbol.total_loss || 0))}</span>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            `}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        },
        
        closeSymbolsModal: function() {
            const overlay = document.getElementById('symbols-modal-overlay');
            if (overlay) {
                overlay.remove();
            }
            this.removeBodyBlur();
        },
        
        closeModalIfClickOutsideSymbols: function(event) {
            if (event.target.id === 'symbols-modal-overlay') {
                this.closeSymbolsModal();
            }
        },
        
        formatDateDisplay: function(dateStr) {
            if (!dateStr) return 'N/A';
            try {
                const date = new Date(dateStr + 'T00:00:00');
                if (isNaN(date.getTime())) return dateStr;
                return date.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: '2-digit', 
                    year: 'numeric' 
                });
            } catch(e) {
                return dateStr;
            }
        },
        
        showCalendarOverlay: function() {
            this.addBodyBlur();
            
            const currentData = this.getCurrentData();
            if (!currentData) {
                this.showCustomAlert('No data available for the selected filters', '📊');
                this.removeBodyBlur();
                return;
            }
            
            const tradeData = currentData[this.currentTradeType];
            const regularData = tradeData?.regular_data?.[this.currentAuthType];
            const dailyRecord = regularData?.daily_trades_record || {};
            
            const startDate = currentData.start_date ? this.formatDateDisplay(currentData.start_date) : 'N/A';
            const endDate = currentData.end_date ? this.formatDateDisplay(currentData.end_date) : 'N/A';
            
            const modalHtml = `
                <div class="modal-overlay" id="calendar-modal-overlay" onclick="Analytics.closeModalIfClickOutsideCalendar(event)">
                    <div class="modal-container modal-large" onclick="event.stopPropagation()">
                        <div class="modal-header">
                            <span>📅 Trades from ${startDate} to ${endDate}</span>
                            <span class="modal-close" onclick="Analytics.closeCalendarModal()">✕</span>
                        </div>
                        <div class="modal-body">
                            ${this.renderDailyCalendarModal(dailyRecord)}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        },
        
        renderDailyCalendarModal: function(dailyRecord) {
            if (!dailyRecord || Object.keys(dailyRecord).length === 0) {
                return `
                    <div class="section-card" style="text-align: center; color: #888; padding: 40px;">
                        <div style="font-size: 48px; margin-bottom: 10px;">📅</div>
                        <div>No daily trades recorded for the selected filters</div>
                    </div>
                `;
            }
            
            // Get all dates and sort them
            const dates = Object.keys(dailyRecord).sort();
            
            // Get the first and last date to determine the calendar grid
            const firstDate = new Date(dates[0] + 'T00:00:00');
            const lastDate = new Date(dates[dates.length - 1] + 'T00:00:00');
            
            // Create a set of dates that have data
            const dateSet = new Set(dates);
            
            // Generate calendar grid
            let html = '<div class="calendar-grid">';
            
            // Day headers
            const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            html += '<div class="calendar-header">';
            dayHeaders.forEach(day => {
                html += `<div class="calendar-header-cell">${day}</div>`;
            });
            html += '</div>';
            
            // Get the day of week for the first date (0 = Sunday)
            const firstDayOfWeek = firstDate.getDay();
            
            // Calculate total days in range
            const totalDays = Math.ceil((lastDate - firstDate) / (1000 * 60 * 60 * 24)) + 1;
            
            // Generate all days in the range
            let currentDate = new Date(firstDate);
            let dayCount = 0;
            
            // Add empty cells before the first day
            for (let i = 0; i < firstDayOfWeek; i++) {
                html += '<div class="calendar-empty"></div>';
                dayCount++;
            }
            
            // Add each day
            for (let i = 0; i < totalDays; i++) {
                const dateStr = currentDate.toISOString().split('T')[0];
                const hasData = dateSet.has(dateStr);
                
                if (hasData) {
                    const data = dailyRecord[dateStr];
                    const month = currentDate.toLocaleString('default', { month: 'short' });
                    const day = currentDate.getDate();
                    const pnl = data.profit_and_loss || 0;
                    const tradesCount = data.trades_count || 0;
                    const pnlClass = pnl >= 0 ? 'calendar-profit' : 'calendar-loss';
                    
                    html += `
                        <div class="calendar-day ${pnlClass}" onclick="Analytics.showDayDetailModal('${dateStr}')">
                            <div class="calendar-day-date">${month} ${day}</div>
                            <div class="calendar-day-pnl">$${this.formatNumber(pnl)}</div>
                            <div class="calendar-day-trades">${tradesCount} ${tradesCount > 1 ? 'trades' : 'trade'}</div>
                        </div>
                    `;
                } else {
                    const month = currentDate.toLocaleString('default', { month: 'short' });
                    const day = currentDate.getDate();
                    html += `
                        <div class="calendar-day calendar-empty-day">
                            <div class="calendar-day-date">${month} ${day}</div>
                            <div class="calendar-day-pnl" style="color: #888;">—</div>
                            <div class="calendar-day-trades" style="color: #888;">📊 0</div>
                        </div>
                    `;
                }
                
                // Move to next day
                currentDate.setDate(currentDate.getDate() + 1);
                dayCount++;
            }
            
            // Add empty cells at the end if needed
            while (dayCount % 7 !== 0) {
                html += '<div class="calendar-empty"></div>';
                dayCount++;
            }
            
            html += '</div>';
            
            return html;
        },
        
        closeCalendarModal: function() {
            const overlay = document.getElementById('calendar-modal-overlay');
            if (overlay) {
                overlay.remove();
            }
            this.removeBodyBlur();
        },
        
        closeModalIfClickOutsideCalendar: function(event) {
            if (event.target.id === 'calendar-modal-overlay') {
                this.closeCalendarModal();
            }
        },
        
        showDayDetailModal: function(dateStr) {
            this.addBodyBlur();
            
            const currentData = this.getCurrentData();
            if (!currentData) {
                this.showCustomAlert('No data available', '📊');
                this.removeBodyBlur();
                return;
            }
            
            const tradeData = currentData[this.currentTradeType];
            const regularData = tradeData?.regular_data?.[this.currentAuthType];
            const dailyRecord = regularData?.daily_trades_record || {};
            const dayData = dailyRecord[dateStr];
            
            if (!dayData) {
                this.showCustomAlert('No data for this date', '📅');
                this.removeBodyBlur();
                return;
            }
            
            const dateObj = new Date(dateStr + 'T00:00:00');
            const month = dateObj.toLocaleString('default', { month: 'long' });
            const day = dateObj.getDate();
            const year = dateObj.getFullYear();
            const dayOfWeek = dateObj.toLocaleString('default', { weekday: 'long' });
            const pnl = dayData.profit_and_loss || 0;
            const tradesCount = dayData.trades_count || 0;
            const tradeSummary = dayData.trade_summary || {};
            const pnlClass = pnl >= 0 ? 'profit' : 'loss';
            
            let modalHtml = `
                <div class="modal-overlay" id="daydetail-modal-overlay" onclick="Analytics.closeModalIfClickOutsideDayDetail(event)">
                    <div class="modal-container modal-large" onclick="event.stopPropagation()">
                        <div class="modal-header">
                            <span>📅 ${dayOfWeek}, ${month} ${day}, ${year}</span>
                            <span class="modal-close" onclick="Analytics.closeDayDetailModal()">✕</span>
                        </div>
                        <div class="modal-body">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                                <div class="stat-card">
                                    <div class="stat-label">Daily P&L</div>
                                    <div class="stat-value ${pnlClass}" style="font-size: 36px;">$${this.formatNumber(pnl)}</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Total Trades</div>
                                    <div class="stat-value" style="font-size: 36px;">${tradesCount}</div>
                                </div>
                            </div>
                            
                            <div class="section-title">Trade Summary by Symbol</div>
                            <div class="contest-grid">
                                ${Object.entries(tradeSummary).map(([symbol, pnlValue]) => `
                                    <div class="contest-card" style="padding: 15px;">
                                        <h4 style="margin-bottom: 10px;">${this.escapeHtml(symbol)}</h4>
                                        <div class="symbol-info">
                                            <span>P&L:</span>
                                            <span class="${pnlValue >= 0 ? 'profit' : 'loss'}" style="font-size: 18px; font-weight: bold;">
                                                $${this.formatNumber(pnlValue)}
                                            </span>
                                        </div>
                                    </div>
                                `).join('')}
                                ${Object.keys(tradeSummary).length === 0 ? `
                                    <div style="grid-column: 1 / -1; text-align: center; color: #888; padding: 20px;">
                                        No symbol data available for this day
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        },
        
        closeDayDetailModal: function() {
            const overlay = document.getElementById('daydetail-modal-overlay');
            if (overlay) {
                overlay.remove();
            }
            this.removeBodyBlur();
        },
        
        closeModalIfClickOutsideDayDetail: function(event) {
            if (event.target.id === 'daydetail-modal-overlay') {
                this.closeDayDetailModal();
            }
        },
        
        showLoading: function() {
            document.getElementById('analytics-content').innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <div>Loading analytics...</div>
                </div>
            `;
        },
        
        showNoAnalyticsMessage: function() {
            document.getElementById('analytics-content').innerHTML = `
                <div class="info-message">
                    No analytics data available for this user yet.<br>
                    Analytics will appear once trading data is collected.
                </div>
            `;
        },
        
        formatNumber: function(num) {
            if (num === undefined || num === null) return '0.00';
            return parseFloat(num).toFixed(2);
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
        Analytics.init();
    });
</script>

