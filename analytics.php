<?php
// analytics.php
// This file is included in serveraccount.php when view=analytics
?>

<h2>Analytics Dashboard</h2>

<div class="analytics-container" id="analytics-container">
    <div class="analytics-split" id="analytics-split">
        <div class="users-sidebar" id="users-sidebar">
            <div class="users-sidebar-header" onclick="Analytics.showAllUsersanalyticsmodal()">
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
                                average_trade_dates: [],
                                recent_risk_reward: 0,
                                revenue_percentage: 0.0,
                                revenue_profit_percentage: 0.0,
                                revenue_loss_percentage: 0.0
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
                                all_traded_symbols: {},
                                symbols_traded: 0,
                                closed_deals_with_sl_tp: 0,
                                closed_deals_without_sl_tp: 0,
                                highest_sequential_losses: {},
                                highest_sequential_days_in_loss: {},
                                highest_loss_per_trade: 0,
                                daily_trades_record: {},
                                revenue_percentage: 0.0,
                                revenue_profit_percentage: 0.0,
                                revenue_loss_percentage: 0.0
                            },
                            unauthorized: {
                                total_trades: 0,
                                total_pnl: 0,
                                profit_trades: 0,
                                loss_trades: 0,
                                profit_amount: 0,
                                loss_amount: 0,
                                all_traded_symbols: {},
                                symbols_traded: 0,
                                closed_deals_with_sl_tp: 0,
                                closed_deals_without_sl_tp: 0,
                                highest_sequential_losses: {},
                                highest_sequential_days_in_loss: {},
                                highest_loss_per_trade: 0,
                                daily_trades_record: {},
                                revenue_percentage: 0.0,
                                revenue_profit_percentage: 0.0,
                                revenue_loss_percentage: 0.0
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
                                average_trade_dates: [],
                                recent_risk_reward: 0,
                                revenue_percentage: 0.0,
                                revenue_profit_percentage: 0.0,
                                revenue_loss_percentage: 0.0
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
                                all_traded_symbols: {},
                                symbols_traded: 0,
                                closed_deals_with_sl_tp: 0,
                                closed_deals_without_sl_tp: 0,
                                highest_sequential_losses: {},
                                highest_sequential_days_in_loss: {},
                                highest_loss_per_trade: 0,
                                daily_trades_record: {},
                                revenue_percentage: 0.0,
                                revenue_profit_percentage: 0.0,
                                revenue_loss_percentage: 0.0
                            },
                            unauthorized: {
                                total_trades: 0,
                                total_pnl: 0,
                                profit_trades: 0,
                                loss_trades: 0,
                                profit_amount: 0,
                                loss_amount: 0,
                                all_traded_symbols: {},
                                symbols_traded: 0,
                                closed_deals_with_sl_tp: 0,
                                closed_deals_without_sl_tp: 0,
                                highest_sequential_losses: {},
                                highest_sequential_days_in_loss: {},
                                highest_loss_per_trade: 0,
                                daily_trades_record: {},
                                revenue_percentage: 0.0,
                                revenue_profit_percentage: 0.0,
                                revenue_loss_percentage: 0.0
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
                    <div class="default-user-info" onclick="Analytics.showAllUsersanalyticsmodal()">
                        <div class="default-user-name">${this.escapeHtml(this.selectedUser.fullname || 'N/A')}</div>
                        <div class="default-user-email">${this.escapeHtml(this.selectedUser.email || 'N/A')}</div>
                        <div class="default-user-id">ID: ${this.selectedUser.id}</div>
                    </div>
                `;
            }
        },
        
        showAllUsersanalyticsmodal: function() {
            this.addBodyBlur();
            
            const analyticsmodalHtml = `
                <div class="analyticsmodal-overlay" id="users-analyticsmodal-overlay" onclick="Analytics.closeanalyticsmodalIfClickOutside(event)">
                    <div class="analyticsmodal-container users-analyticsmodal" onclick="event.stopPropagation()">
                        <div class="analyticsmodal-header">
                            <span>All Users (${this.users.length})</span>
                            <span class="analyticsmodal-close" onclick="Analytics.closeanalyticsmodal()">✕</span>
                        </div>
                        <div class="analyticsmodal-body">
                            <div class="users-analyticsmodal-search">
                                <input type="text" id="users-analyticsmodal-search-input" class="user-search-input" placeholder="Search users..." onkeyup="Analytics.filteranalyticsmodalUsers()">
                            </div>
                        </div>
                        <div class="analyticsmodal-body" id="users-analyticsmodal-list">
                                ${this.renderanalyticsmodalUsersList(this.users)}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', analyticsmodalHtml);
        },
        
        renderanalyticsmodalUsersList: function(users) {
            if (users.length === 0) {
                return '<div class="info-message-small">No users found</div>';
            }
            
            return users.map(user => `
                <div class="analyticsmodal-user-item ${this.selectedUser && this.selectedUser.id === user.id ? 'selected' : ''}" 
                    onclick="Analytics.selectUserFromanalyticsmodal(${user.id}, '${user.source}')">
                    <div class="analyticsmodal-user-name">${this.escapeHtml(user.fullname || 'N/A')}</div>
                    <div class="analyticsmodal-user-email">${this.escapeHtml(user.email || 'N/A')}</div>
                    <div class="analyticsmodal-user-id">ID: ${user.id}</div>
                </div>
            `).join('');
        },
        
        filteranalyticsmodalUsers: function() {
            const searchTerm = document.getElementById('users-analyticsmodal-search-input').value.toLowerCase();
            const filteredUsers = this.users.filter(user => 
                (user.fullname && user.fullname.toLowerCase().includes(searchTerm)) ||
                (user.email && user.email.toLowerCase().includes(searchTerm)) ||
                user.id.toString().includes(searchTerm)
            );
            
            const container = document.getElementById('users-analyticsmodal-list');
            if (container) {
                container.innerHTML = this.renderanalyticsmodalUsersList(filteredUsers);
            }
        },
        
        selectUserFromanalyticsmodal: function(userId, source) {
            const user = this.users.find(u => u.id == userId);
            if (!user) return;
            
            this.selectedUser = user;
            this.renderDefaultUser();
            this.loadAnalytics(userId, source);
            this.closeanalyticsmodal();
        },
        
        closeanalyticsmodal: function() {
            const overlay = document.getElementById('users-analyticsmodal-overlay');
            if (overlay) {
                overlay.remove();
            }
            this.removeBodyBlur();
        },
        
        closeanalyticsmodalIfClickOutside: function(event) {
            if (event.target.id === 'users-analyticsmodal-overlay') {
                this.closeanalyticsmodal();
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
                    <div class="stat-option" onclick="Analytics.showAllTradesanalyticsmodal(); Analytics.toggleStatsPanel();">
                        <div class="stat-option-title">All Trades</div>
                        <div class="stat-option-desc">View complete trade history</div>
                    </div>
                </div>
            `;
        },
        
        showAllTradesanalyticsmodal: function() {
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
            
            let analyticsmodalHtml = `
                <div class="analyticsmodal-overlay" id="trades-analyticsmodal-overlay" onclick="Analytics.closeanalyticsmodalIfClickOutsideTrades(event)">
                    <div class="analyticsmodal-container analyticsmodal-large" onclick="event.stopPropagation()">
                        <div class="analyticsmodal-header">
                            <span>All Trades (${uniqueTrades.length})</span>
                            <span class="analyticsmodal-close" onclick="Analytics.closeTradesanalyticsmodal()">✕</span>
                        </div>
                        <div class="analyticsmodal-body" style="max-height: 60vh; overflow-y: auto;">
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
            
            document.body.insertAdjacentHTML('beforeend', analyticsmodalHtml);
        },
        
        closeTradesanalyticsmodal: function() {
            const overlay = document.getElementById('trades-analyticsmodal-overlay');
            if (overlay) {
                overlay.remove();
            }
            this.removeBodyBlur();
        },
        
        closeanalyticsmodalIfClickOutsideTrades: function(event) {
            if (event.target.id === 'trades-analyticsmodal-overlay') {
                this.closeTradesanalyticsmodal();
            }
        },
        
        showSequentialLossesanalyticsmodal: function() {
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
            
            let analyticsmodalHtml = `
                <div class="analyticsmodal-overlay" id="losses-analyticsmodal-overlay" onclick="Analytics.closeanalyticsmodalIfClickOutsideLosses(event)">
                    <div class="analyticsmodal-container" onclick="event.stopPropagation()">
                        <div class="analyticsmodal-header">
                            <span>Highest Sequential Losses</span>
                            <span class="analyticsmodal-close" onclick="Analytics.closeLossesanalyticsmodal()">✕</span>
                        </div>
                        <div class="analyticsmodal-body" style="max-height: 60vh; overflow-y: auto;">
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
            
            document.body.insertAdjacentHTML('beforeend', analyticsmodalHtml);
        },
        
        closeLossesanalyticsmodal: function() {
            const overlay = document.getElementById('losses-analyticsmodal-overlay');
            if (overlay) {
                overlay.remove();
            }
            this.removeBodyBlur();
        },
        
        closeanalyticsmodalIfClickOutsideLosses: function(event) {
            if (event.target.id === 'losses-analyticsmodal-overlay') {
                this.closeLossesanalyticsmodal();
            }
        },
        
        showSequentialDaysLossanalyticsmodal: function() {
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
            
            let analyticsmodalHtml = `
                <div class="analyticsmodal-overlay" id="daysloss-analyticsmodal-overlay" onclick="Analytics.closeanalyticsmodalIfClickOutsideDaysLoss(event)">
                    <div class="analyticsmodal-container" onclick="event.stopPropagation()">
                        <div class="analyticsmodal-header">
                            <span>Highest Sequential Days in Loss</span>
                            <span class="analyticsmodal-close" onclick="Analytics.closeDaysLossanalyticsmodal()">✕</span>
                        </div>
                        <div class="analyticsmodal-body" style="max-height: 60vh; overflow-y: auto;">
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
            
            document.body.insertAdjacentHTML('beforeend', analyticsmodalHtml);
        },
        
        closeDaysLossanalyticsmodal: function() {
            const overlay = document.getElementById('daysloss-analyticsmodal-overlay');
            if (overlay) {
                overlay.remove();
            }
            this.removeBodyBlur();
        },
        
        closeanalyticsmodalIfClickOutsideDaysLoss: function(event) {
            if (event.target.id === 'daysloss-analyticsmodal-overlay') {
                this.closeDaysLossanalyticsmodal();
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
                average_trade_dates: [],
                recent_risk_reward: 0,
                revenue_percentage: 0.0,
                revenue_profit_percentage: 0.0,
                revenue_loss_percentage: 0.0
            };
            
            const regularData = tradeData?.regular_data?.[this.currentAuthType] || {
                total_trades: 0,
                total_pnl: 0,
                profit_trades: 0,
                loss_trades: 0,
                profit_amount: 0,
                loss_amount: 0,
                all_traded_symbols: {},
                symbols_traded: 0,
                closed_deals_with_sl_tp: 0,
                closed_deals_without_sl_tp: 0,
                highest_sequential_losses: {},
                highest_sequential_days_in_loss: {},
                highest_loss_per_trade: 0,
                daily_trades_record: {},
                revenue_percentage: 0.0,
                revenue_profit_percentage: 0.0,
                revenue_loss_percentage: 0.0
            };
            
            // Format dates for display
            const startDate = currentData.start_date ? this.formatDateDisplay(currentData.start_date) : 'N/A';
            const endDate = currentData.end_date ? this.formatDateDisplay(currentData.end_date) : 'N/A';
            
            // Check if sequential losses exist
            const hasSequentialLosses = regularData.highest_sequential_losses && regularData.highest_sequential_losses.consecutive_losses_count;
            const hasSequentialDaysLoss = regularData.highest_sequential_days_in_loss && regularData.highest_sequential_days_in_loss.consecutive_days_count;
            
            // Get recent risk reward
            const recentRiskReward = summaries.recent_risk_reward || 0;
            
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
                        <div class="stat-label">Risk-Reward</div>
                        <div class="stat-value profit">
                            ${recentRiskReward > 0 ? `1:${recentRiskReward}` : 'N/A'}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Win rate</div>
                        <div class="stat-value profit">${this.formatNumber(regularData.revenue_profit_percentage || 0)}%</div>
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
                    <div class="stat-card clickable" onclick="Analytics.showTradedSymbolsanalyticsmodal()" style="cursor: pointer;">
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
                    <div class="stat-card daily-stat-card sequential-loss clickable" onclick="Analytics.showSequentialLossesanalyticsmodal()" style="cursor: pointer; ${hasSequentialLosses ? 'border-left: 4px solid #ff6b6b;' : 'border-left: 4px solid #888;'}">
                        <div class="stat-label">📉 Consecutive Lost Trades</div>
                        <div class="stat-value" style="color: ${hasSequentialLosses ? '#ff6b6b' : '#888'};">${hasSequentialLosses ? regularData.highest_sequential_losses.consecutive_losses_count : '0'}</div>
                        ${hasSequentialLosses ? `<div style="font-size: 10px; color: #888; margin-top: 5px;">Total Loss: $${this.formatNumber(regularData.highest_sequential_losses.total_loss_pnl || 0)}</div>` : '<div class="stat-dates">No data</div>'}
                        <div style="font-size: 9px; color: #888; margin-top: 3px;">Click to view details</div>
                    </div>
                    <div class="stat-card daily-stat-card sequential-days clickable" onclick="Analytics.showSequentialDaysLossanalyticsmodal()" style="cursor: pointer; ${hasSequentialDaysLoss ? 'border-left: 4px solid #ff6b6b;' : 'border-left: 4px solid #888;'}">
                        <div class="stat-label">📉 Consecutive Losing Days</div>
                        <div class="stat-value" style="color: ${hasSequentialDaysLoss ? '#ff6b6b' : '#888'};">${hasSequentialDaysLoss ? regularData.highest_sequential_days_in_loss.consecutive_days_count : '0'}</div>
                        ${hasSequentialDaysLoss ? `<div style="font-size: 10px; color: #888; margin-top: 5px;">Total Loss: $${this.formatNumber(regularData.highest_sequential_days_in_loss.total_loss_pnl || 0)}</div>` : '<div class="stat-dates">No data</div>'}
                        <div style="font-size: 9px; color: #888; margin-top: 3px;">Click to view details</div>
                    </div>
                </div>
            `;
        },
        
        showTradedSymbolsanalyticsmodal: function() {
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
            
            let analyticsmodalHtml = `
                <div class="analyticsmodal-overlay" id="symbols-analyticsmodal-overlay" onclick="Analytics.closeanalyticsmodalIfClickOutsideSymbols(event)">
                    <div class="analyticsmodal-container analyticsmodal-large" onclick="event.stopPropagation()">
                        <div class="analyticsmodal-header">
                            <span>Traded Symbols (${Object.keys(symbols).length})</span>
                            <span class="analyticsmodal-close" onclick="Analytics.closeSymbolsanalyticsmodal()">✕</span>
                        </div>
                        <div class="analyticsmodal-body" style="max-height: 60vh; overflow-y: auto;">
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
            
            document.body.insertAdjacentHTML('beforeend', analyticsmodalHtml);
        },
        
        closeSymbolsanalyticsmodal: function() {
            const overlay = document.getElementById('symbols-analyticsmodal-overlay');
            if (overlay) {
                overlay.remove();
            }
            this.removeBodyBlur();
        },
        
        closeanalyticsmodalIfClickOutsideSymbols: function(event) {
            if (event.target.id === 'symbols-analyticsmodal-overlay') {
                this.closeSymbolsanalyticsmodal();
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
            
            const analyticsmodalHtml = `
                <div class="analyticsmodal-overlay" id="calendar-analyticsmodal-overlay" onclick="Analytics.closeanalyticsmodalIfClickOutsideCalendar(event)">
                    <div class="analyticsmodal-container analyticsmodal-large" onclick="event.stopPropagation()">
                        <div class="analyticsmodal-header">
                            <span>📅 Trades from ${startDate} to ${endDate}</span>
                            <span class="analyticsmodal-close" onclick="Analytics.closeCalendaranalyticsmodal()">✕</span>
                        </div>
                        <div class="analyticsmodal-body" style="max-height: 70vh; overflow-y: auto;">
                            ${this.renderDailyCalendaranalyticsmodal(dailyRecord, regularData)}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', analyticsmodalHtml);
        },
        
        renderDailyCalendaranalyticsmodal: function(dailyRecord, regularData) {
            if (!dailyRecord || Object.keys(dailyRecord).length === 0) {
                return `
                    <div class="section-card" style="text-align: center; color: #888; padding: 40px;">
                        <div style="font-size: 48px; margin-bottom: 10px;">📅</div>
                        <div>No daily trades recorded for the selected filters</div>
                    </div>
                `;
            }
            
            // Get revenue percentage
            const revenuePercentage = regularData?.revenue_percentage || 0;
            
            // Get all dates and sort them
            const dates = Object.keys(dailyRecord).sort();
            
            // Get the first and last date to determine the calendar grid
            const firstDate = new Date(dates[0] + 'T00:00:00');
            const lastDate = new Date(dates[dates.length - 1] + 'T00:00:00');
            
            // Create a set of dates that have data
            const dateSet = new Set(dates);
            
            // Generate calendar grid - FIXED: proper day alignment
            let html = `
                <div class="calendar-grid">
            `;
            
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
                const year = currentDate.getFullYear();
                const month = String(currentDate.getMonth() + 1).padStart(2, '0');
                const day = String(currentDate.getDate()).padStart(2, '0');
                const dateStr = `${year}-${month}-${day}`;
                const hasData = dateSet.has(dateStr);
                const dayOfWeek = currentDate.getDay(); // 0 = Sunday
                const monthName = currentDate.toLocaleString('default', { month: 'short' });
                const dayNumber = currentDate.getDate();
                
                if (hasData) {
                    const data = dailyRecord[dateStr];
                    const pnl = data.profit_and_loss || 0;
                    const tradesCount = data.trades_count || 0;
                    const pnlClass = pnl >= 0 ? 'calendar-profit' : 'calendar-loss';
                    
                    html += `
                        <div class="calendar-day ${pnlClass}" onclick="Analytics.showDayDetailanalyticsmodal('${dateStr}')">
                            <div class="calendar-day-date">${monthName} ${dayNumber}</div>
                            <div class="calendar-day-pnl">$${this.formatNumber(pnl)}</div>
                            <div class="calendar-day-trades">${tradesCount} ${tradesCount > 1 ? 'trades' : 'trade'}</div>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="calendar-day calendar-empty-day">
                            <div class="calendar-day-date">${monthName} ${dayNumber}</div>
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
            html += `
                <div style="margin-top: 10px; padding: 5px; background: var(--bg-secondary, #f5f5f5); font-size: 12px;">
                    <span>Revenue: </span>
                    <span class="${revenuePercentage >= 0 ? 'profit' : 'loss'}" font-weight: bold;">${revenuePercentage}%
                    </span>
                </div>
            `;
            
            return html;
        },
        
        closeCalendaranalyticsmodal: function() {
            const overlay = document.getElementById('calendar-analyticsmodal-overlay');
            if (overlay) {
                overlay.remove();
            }
            this.removeBodyBlur();
        },
        
        closeanalyticsmodalIfClickOutsideCalendar: function(event) {
            if (event.target.id === 'calendar-analyticsmodal-overlay') {
                this.closeCalendaranalyticsmodal();
            }
        },
        
        showDayDetailanalyticsmodal: function(dateStr) {
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
            const allTrades = dayData.all_trades || {};
            const pnlClass = pnl >= 0 ? 'profit' : 'loss';
            
            // Flatten all trades from all symbols into a single list
            let allTradesList = [];
            for (const symbol in allTrades) {
                if (Array.isArray(allTrades[symbol])) {
                    // Add the symbol to each trade object
                    const tradesWithSymbol = allTrades[symbol].map(trade => ({
                        ...trade,
                        symbol: symbol  // Add the symbol from the parent key
                    }));
                    allTradesList = allTradesList.concat(tradesWithSymbol);
                }
            }
            
            // Sort trades by time (newest first)
            allTradesList.sort((a, b) => (b.time_open || '').localeCompare(a.time_open || ''));
            
            let analyticsmodalHtml = `
                <div class="analyticsmodal-overlay" id="daydetail-analyticsmodal-overlay" onclick="Analytics.closeanalyticsmodalIfClickOutsideDayDetail(event)">
                    <div class="analyticsmodal-container" onclick="event.stopPropagation()">
                        <div class="analyticsmodal-header">
                            <span>📅 ${dayOfWeek}, ${month} ${day}, ${year}</span>
                            <span class="analyticsmodal-close" onclick="Analytics.closeDayDetailanalyticsmodal()">✕</span>
                        </div>
                        <div class="analyticsmodal-body" style="max-height: 70vh; overflow-y: auto; padding: 20px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <div class="stat-card" style="padding: 15px;">
                                    <div class="stat-label">Daily P&L</div>
                                    <div class="stat-value ${pnlClass}" style="font-size: 28px;">$${this.formatNumber(pnl)}</div>
                                </div>
                                <div class="stat-card" style="padding: 15px;">
                                    <div class="stat-label">Total Trades</div>
                                    <div class="stat-value" style="font-size: 28px;">${tradesCount}</div>
                                </div>
                            </div>
                            
                            <div class="section-title" style="margin-bottom: 10px;">Trade Summary by Symbol</div>
                            <div class="contest-grid" style="margin-bottom: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
                                ${Object.entries(tradeSummary).map(([symbol, pnlValue]) => `
                                    <div class="contest-card" style="padding: 12px;">
                                        <h4 style="margin-bottom: 5px; font-size: 14px;">${this.escapeHtml(symbol)}</h4>
                                        <div class="symbol-info" style="font-size: 13px;">
                                            <span>P&L:</span>
                                            <span class="${pnlValue >= 0 ? 'profit' : 'loss'}" style="font-size: 16px; font-weight: bold;">
                                                $${this.formatNumber(pnlValue)}
                                            </span>
                                        </div>
                                        <div class="symbol-info" style="font-size: 12px;">
                                            <span>Trades:</span>
                                            <span>${(allTrades[symbol] || []).length}</span>
                                        </div>
                                    </div>
                                `).join('')}
                                ${Object.keys(tradeSummary).length === 0 ? `
                                    <div style="grid-column: 1 / -1; text-align: center; color: #888; padding: 10px;">
                                        No symbol data available for this day
                                    </div>
                                ` : ''}
                            </div>
                            
                            <div class="section-title" style="margin-bottom: 10px;">📊 Individual Trades (${allTradesList.length})</div>
                            ${allTradesList.length > 0 ? `
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    ${allTradesList.map(trade => {
                                        const isProfit = (trade.pnl || 0) >= 0;
                                        return `
                                            <div style="background: var(--bg-secondary, #f5f5f5); border-radius: 8px; padding: 12px 15px; border-left: 4px solid ${isProfit ? '#4caf50' : '#f44336'};">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                                    <span style="font-weight: bold; font-size: 16px;">${this.escapeHtml(trade.symbol || 'N/A')}</span>
                                                    <span style="font-size: 13px; color: #888;">${trade.order_type || trade.type || 'N/A'}</span>
                                                    <span style="font-size: 13px; color: #888;">1:${trade.risk_reward || 'N/A'}</span>
                                                </div>
                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3px 15px; font-size: 13px;">
                                                    <div><span style="color: #888;">Exit:</span> ${trade.exit_price || 'N/A'}</div>
                                                    <div><span style="color: #888;">Entry:</span> ${trade.entry_price || 'N/A'}</div>
                                                    <div><span style="color: #888;">TP:</span> ${trade.take_profit || trade.tp || 'N/A'}</div>
                                                    <div><span style="color: #888;">Volume:</span> ${trade.volume || 'N/A'}</div>
                                                    <div style="grid-column: 1 / -1; margin-top: 3px; font-weight: bold; font-size: 15px; color: ${isProfit ? '#4caf50' : '#f44336'};">
                                                        PnL: $${this.formatNumber(trade.pnl || 0)}
                                                    </div>
                                                </div>
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            ` : `
                                <div style="text-align: center; color: #888; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                                    No individual trade data available for this day
                                </div>
                            `}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', analyticsmodalHtml);
        },
        
        closeDayDetailanalyticsmodal: function() {
            const overlay = document.getElementById('daydetail-analyticsmodal-overlay');
            if (overlay) {
                overlay.remove();
            }
            this.removeBodyBlur();
        },
        
        closeanalyticsmodalIfClickOutsideDayDetail: function(event) {
            if (event.target.id === 'daydetail-analyticsmodal-overlay') {
                this.closeDayDetailanalyticsmodal();
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