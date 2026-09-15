<?php
// analytics.php
// Included from serveraccount.php when view=analytics
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
    payload: null,          // full response from server
    currentTradeType: 'authorized',   // 'authorized' | 'unauthorized'

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    formatNumber: function(num, dp = 2) {
        if (num === undefined || num === null || num === '') return '0.00';
        const n = parseFloat(num);
        if (isNaN(n)) return '0.00';
        return n.toFixed(dp);
    },

    escapeHtml: function(str) {
        if (str === undefined || str === null) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            if (m === '"') return '&quot;';
            if (m === "'") return '&#39;';
            return m;
        });
    },

    formatDateDisplay: function(dateStr) {
        if (!dateStr) return 'N/A';
        try {
            const d = new Date(dateStr + 'T00:00:00');
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
        } catch (e) { return dateStr; }
    },

    showCustomAlert: function(message, icon = '⚠️') {
        const a = document.getElementById('custom-alert');
        document.getElementById('custom-alert-icon').textContent = icon;
        document.getElementById('custom-alert-message').textContent = message;
        a.style.display = 'flex';
        setTimeout(() => this.hideCustomAlert(), 3000);
    },

    hideCustomAlert: function() {
        document.getElementById('custom-alert').style.display = 'none';
    },

    addBodyBlur: function() {
        const c = document.getElementById('analytics-container');
        if (c) c.classList.add('blur-background');
    },

    removeBodyBlur: function() {
        const c = document.getElementById('analytics-container');
        if (c) c.classList.remove('blur-background');
    },

    // ------------------------------------------------------------------
    // Init / users
    // ------------------------------------------------------------------
    init: function() {
        this.loadUsers();
        this.bindEvents();
    },

    bindEvents: function() {
        const btn = document.getElementById('floating-stats-btn');
        if (btn) btn.addEventListener('click', () => this.toggleStatsPanel());
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
        .then(r => r.json())
        .then(data => {
            if (data.success && data.users && data.users.length) {
                this.users = data.users;
                this.selectedUser = this.users[0];
                this.renderDefaultUser();
                this.loadAnalytics(this.selectedUser.id, this.selectedUser.source);
            } else {
                document.getElementById('default-user-card').innerHTML =
                    '<div class="info-message-small">No users found</div>';
            }
        })
        .catch(err => {
            console.error(err);
            document.getElementById('default-user-card').innerHTML =
                '<div class="info-message-small">Error loading users</div>';
        });
    },

    renderDefaultUser: function() {
        const c = document.getElementById('default-user-card');
        if (!this.selectedUser) return;
        c.innerHTML = `
            <div class="default-user-info" onclick="Analytics.showAllUsersanalyticsmodal()">
                <div class="default-user-name">${this.escapeHtml(this.selectedUser.fullname || 'N/A')}</div>
                <div class="default-user-email">${this.escapeHtml(this.selectedUser.email || 'N/A')}</div>
                <div class="default-user-id">ID: ${this.selectedUser.id}</div>
            </div>`;
    },

    // ------------------------------------------------------------------
    // User picker modal
    // ------------------------------------------------------------------
    showAllUsersanalyticsmodal: function() {
        this.addBodyBlur();
        const html = `
            <div class="analyticsmodal-overlay" id="users-analyticsmodal-overlay"
                 onclick="Analytics.closeanalyticsmodalIfClickOutside(event)">
                <div class="analyticsmodal-container users-analyticsmodal" onclick="event.stopPropagation()">
                    <div class="analyticsmodal-header">
                        <span>All Users (${this.users.length})</span>
                        <span class="analyticsmodal-close" onclick="Analytics.closeanalyticsmodal()">✕</span>
                    </div>
                    <div class="analyticsmodal-body">
                        <div class="users-analyticsmodal-search">
                            <input type="text" id="users-analyticsmodal-search-input"
                                   class="user-search-input" placeholder="Search users..."
                                   onkeyup="Analytics.filteranalyticsmodalUsers()">
                        </div>
                    </div>
                    <div class="analyticsmodal-body" id="users-analyticsmodal-list">
                        ${this.renderanalyticsmodalUsersList(this.users)}
                    </div>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    },

    renderanalyticsmodalUsersList: function(users) {
        if (!users || !users.length) return '<div class="info-message-small">No users found</div>';
        return users.map(u => `
            <div class="analyticsmodal-user-item ${this.selectedUser && this.selectedUser.id === u.id ? 'selected' : ''}"
                 onclick="Analytics.selectUserFromanalyticsmodal(${u.id}, '${u.source}')">
                <div class="analyticsmodal-user-name">${this.escapeHtml(u.fullname || 'N/A')}</div>
                <div class="analyticsmodal-user-email">${this.escapeHtml(u.email || 'N/A')}</div>
                <div class="analyticsmodal-user-id">ID: ${u.id}</div>
            </div>`).join('');
    },

    filteranalyticsmodalUsers: function() {
        const t = document.getElementById('users-analyticsmodal-search-input').value.toLowerCase();
        const f = this.users.filter(u =>
            (u.fullname && u.fullname.toLowerCase().includes(t)) ||
            (u.email && u.email.toLowerCase().includes(t)) ||
            String(u.id).includes(t));
        const c = document.getElementById('users-analyticsmodal-list');
        if (c) c.innerHTML = this.renderanalyticsmodalUsersList(f);
    },

    selectUserFromanalyticsmodal: function(userId) {
        const u = this.users.find(x => x.id == userId);
        if (!u) return;
        this.selectedUser = u;
        this.renderDefaultUser();
        this.loadAnalytics(u.id, u.source);
        this.closeanalyticsmodal();
    },

    closeanalyticsmodal: function() {
        const o = document.getElementById('users-analyticsmodal-overlay');
        if (o) o.remove();
        this.removeBodyBlur();
    },

    closeanalyticsmodalIfClickOutside: function(e) {
        if (e.target.id === 'users-analyticsmodal-overlay') this.closeanalyticsmodal();
    },

    // ------------------------------------------------------------------
    // Fetch
    // ------------------------------------------------------------------
    loadAnalytics: function(userId, source) {
        this.showLoading();
        fetch(window.location.pathname, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `action=get_user_analytics&user_id=${encodeURIComponent(userId)}&source_table=${encodeURIComponent(source)}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                this.payload = data;
                this.renderAnalytics();
                this.showFloatingButton();
            } else {
                this.payload = null;
                this.showNoAnalyticsMessage();
            }
        })
        .catch(err => {
            console.error(err);
            this.payload = null;
            this.showNoAnalyticsMessage();
        });
    },

    // ------------------------------------------------------------------
    // Render
    // ------------------------------------------------------------------
    showFloatingButton: function() {
        document.getElementById('floating-stats-btn').style.display = 'flex';
    },

    toggleStatsPanel: function() {
        const o = document.getElementById('stats-panel-overlay');
        if (o.style.display === 'flex') {
            o.style.display = 'none';
            this.removeBodyBlur();
        } else {
            o.style.display = 'flex';
            this.addBodyBlur();
            this.renderStatsPanel();
        }
    },

    renderStatsPanel: function() {
        const c = document.getElementById('stats-panel-content');
        c.innerHTML = `
            <div style="margin-bottom: 15px;">
                <div class="stat-option-title" style="margin-bottom: 8px;">Trade Type</div>
                <div class="stat-option ${this.currentTradeType === 'authorized' ? 'active' : ''}"
                     onclick="Analytics.setTradeType('authorized'); Analytics.toggleStatsPanel();">
                    <div class="stat-option-title">Authorized Trades</div>
                </div>
                <div class="stat-option ${this.currentTradeType === 'unauthorized' ? 'active' : ''}"
                     onclick="Analytics.setTradeType('unauthorized'); Analytics.toggleStatsPanel();">
                    <div class="stat-option-title">Unauthorized Trades</div>
                </div>
            </div>
            <div style="padding-top: 10px; border-top: 1px solid var(--border-color);">
                <div class="stat-option-title" style="margin-bottom: 8px;">View Trades</div>
                <div class="stat-option" onclick="Analytics.showAllTradesanalyticsmodal(); Analytics.toggleStatsPanel();">
                    <div class="stat-option-title">All Trades</div>
                    <div class="stat-option-desc">View complete trade history</div>
                </div>
            </div>`;
    },

    setTradeType: function(t) {
        this.currentTradeType = t;
        this.renderAnalytics();
    },

    getSummary: function() {
        if (!this.payload) return null;
        return this.currentTradeType === 'authorized'
            ? this.payload.authorized
            : this.payload.unauthorized;
    },

    renderAnalytics: function() {
        if (!this.payload) { this.showNoAnalyticsMessage(); return; }
        const s = this.getSummary();
        if (!s) { this.showNoAnalyticsMessage(); return; }

        const container = document.getElementById('analytics-content');
        const startDate = this.formatDateDisplay(this.payload.start_date);
        const endDate = this.formatDateDisplay(this.payload.end_date);

        container.innerHTML = `
            <div class="analytics-header">
                <h2>${this.escapeHtml(this.selectedUser?.fullname || 'User')} - Trading Analytics</h2>
                <div class="selected-user-info">
                    <strong>From:</strong> ${startDate} &nbsp; <strong>To:</strong> ${endDate}
                </div>
            </div>

            <div style="text-align: center; margin: 20px 0;">
                <button class="calendar-toggle-btn" onclick="Analytics.showCalendarOverlay()">
                    <span>📅</span> View Daily Trades Calendar
                </button>
            </div>

            <div class="section-title">
                ${this.currentTradeType === 'authorized' ? 'Authorized Trades' : 'Unauthorized Trades'}
            </div>

            <!-- ============== Primary P&L cards ============== -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Loss Amount</div>
                    <div class="stat-value loss">$${this.formatNumber(s.loss_amount)}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Profit Amount</div>
                    <div class="stat-value profit">$${this.formatNumber(s.profit_amount)}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total P&L</div>
                    <div class="stat-value ${(s.total_pnl || 0) >= 0 ? 'profit' : 'loss'}">
                        $${this.formatNumber(s.total_pnl)}
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Win Rate</div>
                    <div class="stat-value profit">${this.formatNumber(s.revenue_profit_percentage, 2)}%</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Total Trades</div>
                    <div class="stat-value">${s.total_trades || 0}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Won / Lost Trades</div>
                    <div class="stat-value">
                        <span class="profit">${s.profit_trades || 0}</span> /
                        <span class="loss">${s.loss_trades || 0}</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Highest Loss/Trade</div>
                    <div class="stat-value loss">$${this.formatNumber(s.highest_loss_per_trade)}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Highest Drawdown</div>
                    <div class="stat-value loss">$${this.formatNumber(s.highest_drawdown)}</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Deals w/ SL-TP</div>
                    <div class="stat-value">${s.closed_deals_with_sl_tp || 0}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Deals w/o SL-TP</div>
                    <div class="stat-value">${s.closed_deals_without_sl_tp || 0}</div>
                </div>
                <div class="stat-card clickable" onclick="Analytics.showTradedSymbolsanalyticsmodal()" style="cursor:pointer;">
                    <div class="stat-label">Symbols Traded</div>
                    <div class="stat-value">${s.symbols_traded || 0}</div>
                    <div style="font-size:10px;color:#888;margin-top:5px;">Click to view</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Revenue %</div>
                    <div class="stat-value ${(s.revenue_percentage || 0) >= 0 ? 'profit' : 'loss'}">
                        ${this.formatNumber(s.revenue_percentage, 2)}%
                    </div>
                </div>
            </div>

            <!-- ============== Per-day (weekly + daily) ============== -->
            <div class="section-title">Trades per Day / Week</div>
            <div class="stats-grid">
                <div class="stat-card daily-stat-card lowest">
                    <div class="stat-label">📉 Lowest Trades / Day</div>
                    <div class="stat-value">${s.lowest_trades_per_day || 0}</div>
                </div>
                <div class="stat-card daily-stat-card average">
                    <div class="stat-label">⚖️ Average Trades / Day</div>
                    <div class="stat-value">${s.average_trades_per_day || 0}</div>
                </div>
                <div class="stat-card daily-stat-card highest">
                    <div class="stat-label">📈 Highest Trades / Day</div>
                    <div class="stat-value">${s.highest_trades_per_day || 0}</div>
                </div>
                <div class="stat-card daily-stat-card lowest">
                    <div class="stat-label">📉 Lowest Trades / Week</div>
                    <div class="stat-value">${s.lowest_trades_per_week || 0}</div>
                </div>
                <div class="stat-card daily-stat-card average">
                    <div class="stat-label">⚖️ Average Trades / Week</div>
                    <div class="stat-value">${s.average_trades_per_week || 0}</div>
                </div>
                <div class="stat-card daily-stat-card highest">
                    <div class="stat-label">📈 Highest Trades / Week</div>
                    <div class="stat-value">${s.highest_trades_per_week || 0}</div>
                </div>
            </div>

            <!-- ============== Revenue split ============== -->
            <div class="section-title">Revenue Split</div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Profit Revenue %</div>
                    <div class="stat-value profit">${this.formatNumber(s.revenue_profit_percentage, 2)}%</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Loss Revenue %</div>
                    <div class="stat-value loss">${this.formatNumber(s.revenue_loss_percentage, 2)}%</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Revenue %</div>
                    <div class="stat-value">${this.formatNumber(s.revenue_percentage, 2)}%</div>
                </div>
            </div>

            <!-- ============== Sequential loss / days ============== -->
            <div class="section-title">Drawdown Streaks</div>
            <div class="stats-grid">
                <div class="stat-card daily-stat-card sequential-loss clickable"
                     onclick="Analytics.showSequentialLossesanalyticsmodal()"
                     style="cursor:pointer; border-left:4px solid ${s.consecutive_losses_count ? '#ff6b6b' : '#888'};">
                    <div class="stat-label">📉 Consecutive Lost Trades</div>
                    <div class="stat-value" style="color:${s.consecutive_losses_count ? '#ff6b6b' : '#888'};">
                        ${s.consecutive_losses_count || 0}
                    </div>
                    ${s.consecutive_losses_count
                        ? `<div style="font-size:10px;color:#888;margin-top:5px;">Total Loss: $${this.formatNumber(s.total_loss_pnl)}</div>`
                        : '<div class="stat-dates">No data</div>'}
                </div>
                <div class="stat-card daily-stat-card sequential-days clickable"
                     onclick="Analytics.showSequentialDaysLossanalyticsmodal()"
                     style="cursor:pointer; border-left:4px solid ${s.consecutive_days_in_loss_count ? '#ff6b6b' : '#888'};">
                    <div class="stat-label">📉 Consecutive Losing Days</div>
                    <div class="stat-value" style="color:${s.consecutive_days_in_loss_count ? '#ff6b6b' : '#888'};">
                        ${s.consecutive_days_in_loss_count || 0}
                    </div>
                    ${s.consecutive_days_in_loss_count
                        ? `<div style="font-size:10px;color:#888;margin-top:5px;">Total Loss: $${this.formatNumber(s.consecutive_days_in_loss_count_total_loss_pnl)}</div>`
                        : '<div class="stat-dates">No data</div>'}
                </div>
            </div>
        `;
    },

    showLoading: function() {
        document.getElementById('analytics-content').innerHTML = `
            <div class="loading-spinner">
                <div class="spinner"></div>
                <div>Loading analytics...</div>
            </div>`;
    },

    showNoAnalyticsMessage: function() {
        document.getElementById('analytics-content').innerHTML = `
            <div class="info-message">
                No analytics data available for this user yet.<br>
                Analytics will appear once trading data has been collected.
            </div>`;
    },

    // ------------------------------------------------------------------
    // Calendar
    // ------------------------------------------------------------------
    showCalendarOverlay: function() {
        if (!this.payload) { this.showCustomAlert('No data available', '📊'); return; }
        const s = this.getSummary();
        const dailyRecord = (s && s.daily_trades_record) ? s.daily_trades_record : {};

        this.addBodyBlur();
        const startDate = this.formatDateDisplay(this.payload.start_date);
        const endDate   = this.formatDateDisplay(this.payload.end_date);

        const html = `
            <div class="analyticsmodal-overlay" id="calendar-analyticsmodal-overlay"
                 onclick="Analytics.closeanalyticsmodalIfClickOutsideCalendar(event)">
                <div class="analyticsmodal-container analyticsmodal-large" onclick="event.stopPropagation()">
                    <div class="analyticsmodal-header">
                        <span>📅 Trades from ${startDate} to ${endDate}</span>
                        <span class="analyticsmodal-close" onclick="Analytics.closeCalendaranalyticsmodal()">✕</span>
                    </div>
                    <div class="analyticsmodal-body" style="max-height:70vh;overflow-y:auto;">
                        ${this.renderDailyCalendar(dailyRecord, s)}
                    </div>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    },

    renderDailyCalendar: function(dailyRecord, s) {
        const dates = Object.keys(dailyRecord || {}).sort();
        if (!dates.length) {
            return `<div class="section-card" style="text-align:center;color:#888;padding:40px;">
                        <div style="font-size:48px;margin-bottom:10px;">📅</div>
                        <div>No daily trades recorded</div>
                    </div>`;
        }

        const firstDate = new Date(dates[0] + 'T00:00:00');
        const lastDate  = new Date(dates[dates.length - 1] + 'T00:00:00');
        const dateSet   = new Set(dates);

        let html = '<div class="calendar-grid">';
        const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        html += '<div class="calendar-header">';
        dayHeaders.forEach(d => { html += `<div class="calendar-header-cell">${d}</div>`; });
        html += '</div>';

        const firstDow = firstDate.getDay();
        const totalDays = Math.ceil((lastDate - firstDate) / 86400000) + 1;

        let current = new Date(firstDate);
        let count = 0;

        for (let i = 0; i < firstDow; i++) { html += '<div class="calendar-empty"></div>'; count++; }

        for (let i = 0; i < totalDays; i++) {
            const y = current.getFullYear();
            const m = String(current.getMonth() + 1).padStart(2, '0');
            const d = String(current.getDate()).padStart(2, '0');
            const dateStr = `${y}-${m}-${d}`;
            const monthName = current.toLocaleString('default', { month: 'short' });
            const dayNum = current.getDate();
            const hasData = dateSet.has(dateStr);

            if (hasData) {
                const row = dailyRecord[dateStr];
                const pnl = row.profit_and_loss || 0;
                const trades = row.trades_count || 0;
                const cls = pnl >= 0 ? 'calendar-profit' : 'calendar-loss';
                html += `
                    <div class="calendar-day ${cls}" onclick="Analytics.showDayDetailanalyticsmodal('${dateStr}')">
                        <div class="calendar-day-date">${monthName} ${dayNum}</div>
                        <div class="calendar-day-pnl">$${this.formatNumber(pnl)}</div>
                        <div class="calendar-day-trades">${trades} ${trades === 1 ? 'trade' : 'trades'}</div>
                    </div>`;
            } else {
                html += `
                    <div class="calendar-day calendar-empty-day">
                        <div class="calendar-day-date">${monthName} ${dayNum}</div>
                        <div class="calendar-day-pnl" style="color:#888;">—</div>
                        <div class="calendar-day-trades" style="color:#888;">📊 0</div>
                    </div>`;
            }
            current.setDate(current.getDate() + 1);
            count++;
        }
        while (count % 7 !== 0) { html += '<div class="calendar-empty"></div>'; count++; }
        html += '</div>';

        html += `
            <div style="margin-top:10px;padding:5px;background:var(--bg-secondary,#f5f5f5);font-size:12px;">
                <span>Revenue: </span>
                <span class="${(s.revenue_percentage || 0) >= 0 ? 'profit' : 'loss'}" style="font-weight:bold;">
                    ${this.formatNumber(s.revenue_percentage, 2)}%
                </span>
            </div>`;
        return html;
    },

    closeCalendaranalyticsmodal: function() {
        const o = document.getElementById('calendar-analyticsmodal-overlay');
        if (o) o.remove();
        this.removeBodyBlur();
    },

    closeanalyticsmodalIfClickOutsideCalendar: function(e) {
        if (e.target.id === 'calendar-analyticsmodal-overlay') this.closeCalendaranalyticsmodal();
    },

    showDayDetailanalyticsmodal: function(dateStr) {
        if (!this.payload) return;
        const s = this.getSummary();
        const dailyRecord = (s && s.daily_trades_record) || {};
        const dayData = dailyRecord[dateStr];
        if (!dayData) { this.showCustomAlert('No data for this date', '📅'); return; }

        const d = new Date(dateStr + 'T00:00:00');
        const month = d.toLocaleString('default', { month: 'long' });
        const dayNum = d.getDate();
        const year = d.getFullYear();
        const dow = d.toLocaleString('default', { weekday: 'long' });
        const pnl = dayData.profit_and_loss || 0;
        const tradesCount = dayData.trades_count || 0;
        const tradeSummary = dayData.trade_summary || {};
        const allTrades = dayData.all_trades || {};

        // Flatten
        let flat = [];
        for (const sym in allTrades) {
            if (Array.isArray(allTrades[sym])) {
                allTrades[sym].forEach(t => flat.push(Object.assign({}, t, { symbol: sym })));
            }
        }
        flat.sort((a, b) => (b.time_open || '').localeCompare(a.time_open || ''));

        this.addBodyBlur();
        const html = `
            <div class="analyticsmodal-overlay" id="daydetail-analyticsmodal-overlay"
                 onclick="Analytics.closeanalyticsmodalIfClickOutsideDayDetail(event)">
                <div class="analyticsmodal-container" onclick="event.stopPropagation()">
                    <div class="analyticsmodal-header">
                        <span>📅 ${dow}, ${month} ${dayNum}, ${year}</span>
                        <span class="analyticsmodal-close" onclick="Analytics.closeDayDetailanalyticsmodal()">✕</span>
                    </div>
                    <div class="analyticsmodal-body" style="max-height:70vh;overflow-y:auto;padding:20px;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                            <div class="stat-card" style="padding:15px;">
                                <div class="stat-label">Daily P&L</div>
                                <div class="stat-value ${pnl >= 0 ? 'profit' : 'loss'}" style="font-size:28px;">
                                    $${this.formatNumber(pnl)}
                                </div>
                            </div>
                            <div class="stat-card" style="padding:15px;">
                                <div class="stat-label">Total Trades</div>
                                <div class="stat-value" style="font-size:28px;">${tradesCount}</div>
                            </div>
                        </div>

                        <div class="section-title" style="margin-bottom:10px;">Trade Summary by Symbol</div>
                        <div class="contest-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-bottom:20px;">
                            ${Object.entries(tradeSummary).map(([sym, val]) => `
                                <div class="contest-card" style="padding:12px;">
                                    <h4 style="margin-bottom:5px;font-size:14px;">${this.escapeHtml(sym)}</h4>
                                    <div class="symbol-info" style="font-size:13px;">
                                        <span>P&L:</span>
                                        <span class="${val >= 0 ? 'profit' : 'loss'}" style="font-size:16px;font-weight:bold;">
                                            $${this.formatNumber(val)}
                                        </span>
                                    </div>
                                    <div class="symbol-info" style="font-size:12px;">
                                        <span>Trades:</span>
                                        <span>${(allTrades[sym] || []).length}</span>
                                    </div>
                                </div>`).join('') || `
                                <div style="grid-column:1/-1;text-align:center;color:#888;padding:10px;">
                                    No symbol data available for this day
                                </div>`}
                        </div>

                        <div class="section-title" style="margin-bottom:10px;">📊 Individual Trades (${flat.length})</div>
                        ${flat.length ? `
                            <div style="display:flex;flex-direction:column;gap:10px;">
                                ${flat.map(t => {
                                    const prof = (t.pnl || 0) >= 0;
                                    return `
                                        <div style="background:var(--bg-secondary,#f5f5f5);border-radius:8px;padding:12px 15px;border-left:4px solid ${prof ? '#4caf50' : '#f44336'};">
                                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                                                <span style="font-weight:bold;font-size:16px;">${this.escapeHtml(t.symbol || 'N/A')}</span>
                                                <span style="font-size:12px;color:#888;">${this.escapeHtml(t.ticket || 'N/A')}</span>
                                            </div>
                                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:3px 15px;font-size:13px;">
                                                <div><span style="color:#888;">Entry:</span> ${this.escapeHtml(t.entry || 'N/A')}</div>
                                                <div><span style="color:#888;">SL:</span> ${this.escapeHtml(t.stoploss || 'N/A')}</div>
                                                <div><span style="color:#888;">Target:</span> ${this.escapeHtml(t.target || 'N/A')}</div>
                                                <div><span style="color:#888;">Closed:</span> ${this.escapeHtml(t.closed_time || 'N/A')}</div>
                                                <div style="grid-column:1/-1;margin-top:3px;font-weight:bold;font-size:15px;color:${prof ? '#4caf50' : '#f44336'};">
                                                    PnL: $${this.formatNumber(t.pnl)}
                                                </div>
                                            </div>
                                        </div>`;
                                }).join('')}
                            </div>` : `
                            <div style="text-align:center;color:#888;padding:20px;background:#f9f9f9;border-radius:8px;">
                                No individual trade data available for this day
                            </div>`}
                    </div>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    },

    closeDayDetailanalyticsmodal: function() {
        const o = document.getElementById('daydetail-analyticsmodal-overlay');
        if (o) o.remove();
        this.removeBodyBlur();
    },

    closeanalyticsmodalIfClickOutsideDayDetail: function(e) {
        if (e.target.id === 'daydetail-analyticsmodal-overlay') this.closeDayDetailanalyticsmodal();
    },

    // ------------------------------------------------------------------
    // Symbols modal (built from authorized_trades / unauthorized_trades grouping)
    // ------------------------------------------------------------------
    showTradedSymbolsanalyticsmodal: function() {
        if (!this.payload) { this.showCustomAlert('No data available', '📊'); return; }
        const s = this.getSummary();
        const symbols = (s && s.symbols) ? s.symbols : {};
        const hasAny = Object.keys(symbols).length > 0;

        this.addBodyBlur();
        const html = `
            <div class="analyticsmodal-overlay" id="symbols-analyticsmodal-overlay"
                 onclick="Analytics.closeanalyticsmodalIfClickOutsideSymbols(event)">
                <div class="analyticsmodal-container analyticsmodal-large" onclick="event.stopPropagation()">
                    <div class="analyticsmodal-header">
                        <span>Traded Symbols (${Object.keys(symbols).length})</span>
                        <span class="analyticsmodal-close" onclick="Analytics.closeSymbolsanalyticsmodal()">✕</span>
                    </div>
                    <div class="analyticsmodal-body" style="max-height:60vh;overflow-y:auto;">
                        ${hasAny ? `
                            <div class="contest-grid">
                                ${Object.values(symbols).map(sym => `
                                    <div class="contest-card">
                                        <h4>${this.escapeHtml(sym.symbol)}</h4>
                                        <div class="symbol-info"><span>Total Trades:</span><span>${sym.total_trades || 0}</span></div>
                                        <div class="symbol-info"><span>Total Profit:</span><span class="${(sym.total_profit || 0) >= 0 ? 'profit' : 'loss'}">$${this.formatNumber(sym.total_profit)}</span></div>
                                        <div class="symbol-info"><span>Total Loss:</span><span class="loss">$${this.formatNumber(sym.total_loss)}</span></div>
                                        <div class="symbol-info"><span>Net P&L:</span><span class="${(sym.total_profit || 0) - (sym.total_loss || 0) >= 0 ? 'profit' : 'loss'}">$${this.formatNumber((sym.total_profit || 0) - (sym.total_loss || 0))}</span></div>
                                    </div>`).join('')}
                            </div>` : `
                            <div class="empty-state">
                                <div class="empty-state-icon">📊</div>
                                <div class="empty-state-text">No symbols traded</div>
                            </div>`}
                    </div>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    },

    closeSymbolsanalyticsmodal: function() {
        const o = document.getElementById('symbols-analyticsmodal-overlay');
        if (o) o.remove();
        this.removeBodyBlur();
    },

    closeanalyticsmodalIfClickOutsideSymbols: function(e) {
        if (e.target.id === 'symbols-analyticsmodal-overlay') this.closeSymbolsanalyticsmodal();
    },

    // ------------------------------------------------------------------
    // All trades modal
    // ------------------------------------------------------------------
    showAllTradesanalyticsmodal: function() {
        if (!this.payload) { this.showCustomAlert('No data available', '📊'); return; }
        const s = this.getSummary();
        const trades = (s && s.all_trades) ? s.all_trades : [];

        this.addBodyBlur();
        const html = `
            <div class="analyticsmodal-overlay" id="trades-analyticsmodal-overlay"
                 onclick="Analytics.closeanalyticsmodalIfClickOutsideTrades(event)">
                <div class="analyticsmodal-container analyticsmodal-large" onclick="event.stopPropagation()">
                    <div class="analyticsmodal-header">
                        <span>All Trades (${trades.length})</span>
                        <span class="analyticsmodal-close" onclick="Analytics.closeTradesanalyticsmodal()">✕</span>
                    </div>
                    <div class="analyticsmodal-body" style="max-height:60vh;overflow-y:auto;">
                        ${trades.length ? `
                            <div class="trades-table-wrapper">
                                <table class="trades-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th><th>Ticket</th><th>Symbol</th>
                                            <th>Entry</th><th>SL</th><th>Target</th><th>P&L</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${trades.map(t => `
                                            <tr>
                                                <td>${t.closed_time ? String(t.closed_time).split(' ')[0] : 'N/A'}</td>
                                                <td>${this.escapeHtml(t.ticket || 'N/A')}</td>
                                                <td>${this.escapeHtml(t.symbol || 'N/A')}</td>
                                                <td>${this.escapeHtml(t.entry || 'N/A')}</td>
                                                <td>${this.escapeHtml(t.stoploss || 'N/A')}</td>
                                                <td>${this.escapeHtml(t.target || 'N/A')}</td>
                                                <td class="${(t.pnl || 0) >= 0 ? 'profit' : 'loss'}">$${this.formatNumber(t.pnl)}</td>
                                            </tr>`).join('')}
                                    </tbody>
                                </table>
                            </div>` : `
                            <div class="empty-state">
                                <div class="empty-state-icon">📭</div>
                                <div class="empty-state-text">No trades available</div>
                            </div>`}
                    </div>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    },

    closeTradesanalyticsmodal: function() {
        const o = document.getElementById('trades-analyticsmodal-overlay');
        if (o) o.remove();
        this.removeBodyBlur();
    },

    closeanalyticsmodalIfClickOutsideTrades: function(e) {
        if (e.target.id === 'trades-analyticsmodal-overlay') this.closeTradesanalyticsmodal();
    },

    // ------------------------------------------------------------------
    // Sequential losses / days in loss
    // ------------------------------------------------------------------
    showSequentialLossesanalyticsmodal: function() {
        if (!this.payload) return;
        const s = this.getSummary();
        const trades = (s && s.highest_sequential_losses_trades) ? s.highest_sequential_losses_trades : [];
        const count = s.consecutive_losses_count || 0;
        const totalLoss = s.total_loss_pnl || 0;

        this.addBodyBlur();
        const html = `
            <div class="analyticsmodal-overlay" id="losses-analyticsmodal-overlay"
                 onclick="Analytics.closeanalyticsmodalIfClickOutsideLosses(event)">
                <div class="analyticsmodal-container" onclick="event.stopPropagation()">
                    <div class="analyticsmodal-header">
                        <span>Highest Sequential Losses</span>
                        <span class="analyticsmodal-close" onclick="Analytics.closeLossesanalyticsmodal()">✕</span>
                    </div>
                    <div class="analyticsmodal-body" style="max-height:60vh;overflow-y:auto;">
                        ${count ? `
                            <div class="losses-card">
                                <div class="loss-value">${count} Consecutive Losses</div>
                                <div style="margin-top:10px;">Total Loss: $${this.formatNumber(totalLoss)}</div>
                            </div>
                            ${trades.length ? `
                                <div class="trades-table-wrapper">
                                    <table class="trades-table">
                                        <thead><tr><th>Ticket</th><th>Symbol</th><th>Entry</th><th>SL</th><th>Target</th><th>P&L</th><th>Closed</th></tr></thead>
                                        <tbody>
                                            ${trades.map(t => `
                                                <tr>
                                                    <td>${this.escapeHtml(t.ticket || 'N/A')}</td>
                                                    <td>${this.escapeHtml(t.symbol || 'N/A')}</td>
                                                    <td>${this.escapeHtml(t.entry || 'N/A')}</td>
                                                    <td>${this.escapeHtml(t.stoploss || 'N/A')}</td>
                                                    <td>${this.escapeHtml(t.target || 'N/A')}</td>
                                                    <td class="loss">$${this.formatNumber(t.pnl)}</td>
                                                    <td>${t.closed_time ? String(t.closed_time).split(' ')[0] : 'N/A'}</td>
                                                </tr>`).join('')}
                                        </tbody>
                                    </table>
                                </div>` : ''}
                        ` : `
                            <div class="empty-state">
                                <div class="empty-state-icon">✅</div>
                                <div class="empty-state-text">No sequential losses recorded</div>
                            </div>`}
                    </div>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    },

    closeLossesanalyticsmodal: function() {
        const o = document.getElementById('losses-analyticsmodal-overlay');
        if (o) o.remove();
        this.removeBodyBlur();
    },

    closeanalyticsmodalIfClickOutsideLosses: function(e) {
        if (e.target.id === 'losses-analyticsmodal-overlay') this.closeLossesanalyticsmodal();
    },

    showSequentialDaysLossanalyticsmodal: function() {
        if (!this.payload) return;
        const s = this.getSummary();
        const count = s.consecutive_days_in_loss_count || 0;
        const totalLoss = s.consecutive_days_in_loss_count_total_loss_pnl || 0;
        const days = (s && s.highest_sequential_days_in_loss_days) ? s.highest_sequential_days_in_loss_days : {};

        this.addBodyBlur();
        const html = `
            <div class="analyticsmodal-overlay" id="daysloss-analyticsmodal-overlay"
                 onclick="Analytics.closeanalyticsmodalIfClickOutsideDaysLoss(event)">
                <div class="analyticsmodal-container" onclick="event.stopPropagation()">
                    <div class="analyticsmodal-header">
                        <span>Highest Sequential Days in Loss</span>
                        <span class="analyticsmodal-close" onclick="Analytics.closeDaysLossanalyticsmodal()">✕</span>
                    </div>
                    <div class="analyticsmodal-body" style="max-height:60vh;overflow-y:auto;">
                        ${count ? `
                            <div class="losses-card">
                                <div class="loss-value">${count} Consecutive Days in Loss</div>
                                <div style="margin-top:10px;">Total Loss: $${this.formatNumber(totalLoss)}</div>
                            </div>
                            ${Object.keys(days).length ? `
                                <div class="section-title">Daily Breakdown</div>
                                <div class="stats-grid">
                                    ${Object.entries(days).map(([date, trades]) => {
                                        const dailyTotal = (trades || []).reduce((sum, t) => sum + (parseFloat(t.pnl) || 0), 0);
                                        return `
                                            <div class="stat-card">
                                                <div class="stat-label">${this.escapeHtml(date)}</div>
                                                <div class="stat-value loss">$${this.formatNumber(dailyTotal)}</div>
                                                <div class="stat-label">${(trades || []).length} trades</div>
                                            </div>`;
                                    }).join('')}
                                </div>` : ''}
                        ` : `
                            <div class="empty-state">
                                <div class="empty-state-icon">✅</div>
                                <div class="empty-state-text">No sequential days in loss recorded</div>
                            </div>`}
                    </div>
                </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    },

    closeDaysLossanalyticsmodal: function() {
        const o = document.getElementById('daysloss-analyticsmodal-overlay');
        if (o) o.remove();
        this.removeBodyBlur();
    },

    closeanalyticsmodalIfClickOutsideDaysLoss: function(e) {
        if (e.target.id === 'daysloss-analyticsmodal-overlay') this.closeDaysLossanalyticsmodal();
    }
};

document.addEventListener('DOMContentLoaded', () => Analytics.init());
</script>