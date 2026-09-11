<?php
// registration_token.php - Simple Registration Token Generator
// This file is included in serveraccount.php when view=registration_tokens
?>

<div class="registration-token-container" id="registration-token-container">
    <!-- Header -->
    <div class="token-header">
        <h2>🔑 Registration Token Generator</h2>
        <p class="token-subtitle">Generate a registration token with an expiry time. Users can use this token to create an account before it expires.</p>
    </div>

    <?php if (isset($message) && !empty($message)): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- GENERATE NEW TOKEN SECTION                    -->
    <!-- ============================================ -->
    <div class="token-generate-card">
        <h3>Generate New Registration Token</h3>
        
        <form method="POST" action="serveraccount.php?view=registration_tokens" id="token-generate-form" class="token-form">
            <input type="hidden" name="generate_token" value="1">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="token-email">User Email <span class="required">*</span></label>
                    <input type="email" id="token-email" name="email" placeholder="user@example.com" required>
                    <small class="form-help">The email address of the person who will use this token.</small>
                </div>
                
                <div class="form-group">
                    <label for="token-fullname">Full Name <span class="required">*</span></label>
                    <input type="text" id="token-fullname" name="fullname" placeholder="John Doe" required>
                    <small class="form-help">Full name of the user who will receive this token.</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="token-duration">Token Expiry Duration <span class="required">*</span></label>
                    <div class="duration-input-group">
                        <input type="number" id="token-duration" name="duration" value="24" min="1" max="8760" required>
                        <span class="duration-unit">hours</span>
                    </div>
                    <small class="form-help">How long until the token expires. Max 365 days (8760 hours).</small>
                </div>
                
                <div class="form-group" style="justify-content: flex-end;">
                    <button type="submit" class="btn-generate-token" id="btn-generate-token">
                        <span class="btn-icon">✨</span> Generate Token
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- ============================================ -->
    <!-- TOKEN LIST / HISTORY                         -->
    <!-- ============================================ -->
    <div class="token-list-card">
        <div class="token-list-header">
            <h3>Generated Tokens</h3>
            <div class="token-list-actions">
                <button class="btn-refresh" onclick="TokenManager.refreshTokens()">
                    <span class="btn-icon">🔄</span> Refresh
                </button>
                <button class="btn-cleanup" onclick="TokenManager.cleanupExpired()">
                    <span class="btn-icon">🧹</span> Cleanup Expired
                </button>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="token-search-wrapper">
            <div class="search-bar-wrapper">
                <div class="search-bar">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="token-search-input" class="search-input" 
                           placeholder="Search by email, name, or token..." 
                           oninput="TokenManager.filterTokens()" autocomplete="off">
                    <span class="search-clear" id="token-search-clear" onclick="TokenManager.clearSearch()" style="display:none;">✕</span>
                </div>
            </div>
            <div class="filter-wrapper">
                <select id="token-filter-status" onchange="TokenManager.filterTokens()">
                    <option value="all">All Tokens</option>
                    <option value="active">Active</option>
                    <option value="expired">Expired</option>
                    <option value="used">Used</option>
                </select>
            </div>
        </div>

        <!-- Tokens Table -->
        <div class="tokens-table-container">
            <div class="table-wrapper">
                <table class="token-table" id="tokens-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Token</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="tokens-body">
                        <tr>
                            <td colspan="6" style="text-align:center;padding:40px;color:#888;">
                                <div class="loading-spinner">
                                    <div class="spinner"></div>
                                    <p>Loading tokens...</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <div class="token-pagination" id="token-pagination">
            <div class="pagination-info">
                <span id="token-count-display">0 tokens</span>
            </div>
            <div class="pagination-controls" id="pagination-controls">
                <!-- Pagination buttons rendered by JS -->
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- COPY TOKEN registration_token_modal                             -->
<!-- ============================================ -->
<div id="copy-token-registration_token_modal" class="registration_token_modal-overlay" style="display:none;" onclick="TokenManager.closeCopyregistration_token_modalIfClickOutside(event)">
    <div class="registration_token_modal-container registration_token_modal-medium" onclick="event.stopPropagation()">
        <div class="registration_token_modal-header">
            <span>📋 Registration Token</span>
            <span class="registration_token_modal-close" onclick="TokenManager.closeCopyregistration_token_modal()">✕</span>
        </div>
        <div class="registration_token_modal-body">
            <div class="token-display-wrapper">
                <div class="token-display-label">Registration URL</div>
                <div class="token-display-box" id="token-display-url">
                    Loading...
                </div>
                <button class="btn-copy-token" onclick="TokenManager.copyTokenToClipboard()">
                    📋 Copy to Clipboard
                </button>
            </div>
            <div class="token-info-grid">
                <div class="token-info-item">
                    <span class="info-label">👤 User</span>
                    <span class="info-value" id="token-registration_token_modal-user">-</span>
                </div>
                <div class="token-info-item">
                    <span class="info-label">📧 Email</span>
                    <span class="info-value" id="token-registration_token_modal-email">-</span>
                </div>
                <div class="token-info-item">
                    <span class="info-label">⏰ Expires</span>
                    <span class="info-value" id="token-registration_token_modal-expires">-</span>
                </div>
                <div class="token-info-item">
                    <span class="info-label">⏱️ Created</span>
                    <span class="info-value" id="token-registration_token_modal-created">-</span>
                </div>
            </div>
            <div class="registration_token_modal-buttons">
                <button class="btn-confirm" onclick="TokenManager.closeCopyregistration_token_modal()">Done</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- PASSWORD CONFIRMATION registration_token_modal                  -->
<!-- ============================================ -->
<div id="token-password-registration_token_modal" class="registration_token_modal-overlay" style="display:none;">
    <div class="registration_token_modal-container registration_token_modal-small">
        <div class="registration_token_modal-header">
            <span>🔐 Security Check</span>
            <span class="registration_token_modal-close" onclick="TokenManager.closePasswordregistration_token_modal()">✕</span>
        </div>
        <div class="registration_token_modal-body">
            <p id="token-password-message">Please enter your admin password to continue.</p>
            <input type="password" id="token-password-input" placeholder="Enter password" 
                   style="width:100%;padding:10px;border-radius:6px;border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-color);font-size:14px;box-sizing:border-box;">
            <div id="token-password-error" style="color:#f44336;font-size:13px;margin-top:6px;display:none;"></div>
            <div class="registration_token_modal-buttons">
                <button class="btn-cancel" onclick="TokenManager.closePasswordregistration_token_modal()">Cancel</button>
                <button class="btn-confirm" id="token-password-confirm-btn" onclick="TokenManager.confirmPasswordregistration_token_modal()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- NOTIFICATION registration_token_modal                           -->
<!-- ============================================ -->
<div id="token-notification-registration_token_modal" class="registration_token_modal-overlay" style="display:none;">
    <div class="registration_token_modal-container registration_token_modal-small">
        <div class="registration_token_modal-header">
            <span id="token-notification-title">Notification</span>
            <span class="registration_token_modal-close" onclick="TokenManager.closeNotificationregistration_token_modal()">✕</span>
        </div>
        <div class="registration_token_modal-body">
            <p id="token-notification-message"></p>
            <div class="registration_token_modal-buttons">
                <button class="btn-confirm" onclick="TokenManager.closeNotificationregistration_token_modal()">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- DELETE CONFIRMATION registration_token_modal                    -->
<!-- ============================================ -->
<div id="token-delete-registration_token_modal" class="registration_token_modal-overlay" style="display:none;">
    <div class="registration_token_modal-container registration_token_modal-small">
        <div class="registration_token_modal-header">
            <span>⚠️ Confirm Delete</span>
            <span class="registration_token_modal-close" onclick="TokenManager.closeDeleteregistration_token_modal()">✕</span>
        </div>
        <div class="registration_token_modal-body">
            <p id="token-delete-message">Are you sure you want to delete this token?</p>
            <div class="registration_token_modal-buttons">
                <button class="btn-cancel" onclick="TokenManager.closeDeleteregistration_token_modal()">Cancel</button>
                <button class="btn-danger" id="token-delete-confirm-btn" onclick="TokenManager.confirmDeleteToken()">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
    const TokenManager = {
        // Data
        allTokens: [],
        filteredTokens: [],
        currentPage: 1,
        pageSize: 10,
        searchTerm: '',
        statusFilter: 'all',
        tokenToDelete: null,
        
        // registration_token_modal callbacks
        _passwordCallback: null,
        _copyTokenData: null,

        // ============================================
        // INITIALIZATION
        // ============================================
        init: function() {
            this.loadTokens();
            this.bindEvents();
            
            // Auto-hide message after 5 seconds
            const messageEl = document.querySelector('.message');
            if (messageEl) {
                setTimeout(() => {
                    messageEl.style.opacity = '0';
                    setTimeout(() => {
                        messageEl.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        },

        bindEvents: function() {
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    TokenManager.closeCopyregistration_token_modal();
                    TokenManager.closePasswordregistration_token_modal();
                    TokenManager.closeNotificationregistration_token_modal();
                    TokenManager.closeDeleteregistration_token_modal();
                }
                if (e.key === 'Enter') {
                    if (document.getElementById('token-password-registration_token_modal').style.display === 'flex') {
                        TokenManager.confirmPasswordregistration_token_modal();
                    }
                }
            });
        },

        // ============================================
        // LOAD TOKENS
        // ============================================
        loadTokens: function() {
            const tbody = document.getElementById('tokens-body');
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align:center;padding:40px;color:#888;">
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p>Loading tokens...</p>
                        </div>
                    </td>
                </tr>
            `;

            fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=get_registration_tokens'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.allTokens = data.tokens || [];
                    this.filteredTokens = [...this.allTokens];
                    this.renderTokens();
                    this.updatePagination();
                } else {
                    this.allTokens = [];
                    this.filteredTokens = [];
                    this.renderTokens();
                    this.updatePagination();
                }
            })
            .catch(error => {
                console.error('Error loading tokens:', error);
                this.allTokens = [];
                this.filteredTokens = [];
                this.renderTokens();
                this.updatePagination();
            });
        },

        refreshTokens: function() {
            this.loadTokens();
        },

        // ============================================
        // FILTER TOKENS
        // ============================================
        filterTokens: function() {
            const searchInput = document.getElementById('token-search-input');
            const statusFilter = document.getElementById('token-filter-status');
            
            this.searchTerm = searchInput.value.trim().toLowerCase();
            this.statusFilter = statusFilter.value;
            
            document.getElementById('token-search-clear').style.display = this.searchTerm ? 'block' : 'none';
            
            this.filteredTokens = this.allTokens.filter(token => {
                if (this.searchTerm) {
                    const email = (token.email || '').toLowerCase();
                    const fullname = (token.fullname || '').toLowerCase();
                    const tokenStr = (token.token || '').toLowerCase();
                    if (!email.includes(this.searchTerm) && 
                        !fullname.includes(this.searchTerm) && 
                        !tokenStr.includes(this.searchTerm)) {
                        return false;
                    }
                }
                
                if (this.statusFilter !== 'all') {
                    const status = this.getTokenStatus(token);
                    if (status !== this.statusFilter) {
                        return false;
                    }
                }
                
                return true;
            });
            
            this.currentPage = 1;
            this.renderTokens();
            this.updatePagination();
        },

        clearSearch: function() {
            document.getElementById('token-search-input').value = '';
            document.getElementById('token-search-clear').style.display = 'none';
            this.searchTerm = '';
            this.filterTokens();
        },

        getTokenStatus: function(token) {
            if (token.is_used == 1) return 'used';
            const expiresAt = new Date(token.expires_at);
            return expiresAt < new Date() ? 'expired' : 'active';
        },

        getStatusBadge: function(token) {
            const status = this.getTokenStatus(token);
            const labels = {
                'active': { label: 'Active', class: 'status-active' },
                'expired': { label: 'Expired', class: 'status-expired' },
                'used': { label: 'Used', class: 'status-used' }
            };
            const info = labels[status] || { label: 'Unknown', class: 'status-unknown' };
            return `<span class="status-badge ${info.class}">${info.label}</span>`;
        },

        // ============================================
        // RENDER TOKENS
        // ============================================
        renderTokens: function() {
            const tbody = document.getElementById('tokens-body');
            const start = (this.currentPage - 1) * this.pageSize;
            const end = start + this.pageSize;
            const pageTokens = this.filteredTokens.slice(start, end);

            if (pageTokens.length === 0) {
                if (this.filteredTokens.length === 0 && this.allTokens.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" style="text-align:center;padding:40px;color:#888;">
                                <div style="font-size: 48px; margin-bottom: 10px;">🔑</div>
                                <p style="font-size: 16px; margin-bottom: 4px;">No tokens generated yet</p>
                                <p style="font-size: 13px; color: var(--text-muted);">Generate your first registration token above.</p>
                            </td>
                        </tr>
                    `;
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" style="text-align:center;padding:40px;color:#888;">
                                <p>No tokens match your filters</p>
                            </td>
                        </tr>
                    `;
                }
                return;
            }

            let html = '';
            pageTokens.forEach(token => {
                const expiresAt = new Date(token.expires_at);
                const now = new Date();
                const isExpired = expiresAt < now;
                
                let expiresDisplay = this.formatDate(expiresAt);
                if (!isExpired) {
                    const diff = expiresAt - now;
                    const hours = Math.floor(diff / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    expiresDisplay += ` <span style="font-size:11px;color:var(--text-muted);">(${hours}h ${minutes}m left)</span>`;
                } else {
                    expiresDisplay += ` <span style="font-size:11px;color:#e74c3c;">(expired)</span>`;
                }

                const tokenDisplay = token.token ? token.token.substring(0, 8) + '...' + token.token.substring(token.token.length - 4) : 'N/A';

                html += `
                    <tr class="${token.is_used ? 'row-used' : (isExpired ? 'row-expired' : 'row-active')}">
                        <td>
                            <div class="user-cell">
                                <div class="user-name">${this.escapeHtml(token.fullname || 'N/A')}</div>
                            </div>
                        </td>
                        <td>${this.escapeHtml(token.email || 'N/A')}</td>
                        <td>
                            <code class="token-code">${this.escapeHtml(tokenDisplay)}</code>
                            <button class="btn-copy-small" onclick="TokenManager.showCopyregistration_token_modal(${token.id})" title="Copy token URL">📋</button>
                        </td>
                        <td>${expiresDisplay}</td>
                        <td>${this.getStatusBadge(token)}</td>
                        <td>
                            <div class="action-buttons">
                                ${!token.is_used && !isExpired ? `
                                    <button class="btn-action btn-copy" onclick="TokenManager.showCopyregistration_token_modal(${token.id})" title="Copy token">📋</button>
                                ` : ''}
                                <button class="btn-action btn-delete" onclick="TokenManager.showDeleteregistration_token_modal(${token.id})" title="Delete token">🗑️</button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
            this.updateTokenCount();
        },

        // ============================================
        // PAGINATION
        // ============================================
        updatePagination: function() {
            const totalPages = Math.ceil(this.filteredTokens.length / this.pageSize);
            const controls = document.getElementById('pagination-controls');
            
            if (totalPages <= 1) {
                controls.innerHTML = '';
                return;
            }

            let html = '';
            html += `<button class="page-btn" onclick="TokenManager.goToPage(${this.currentPage - 1})" ${this.currentPage <= 1 ? 'disabled' : ''}>‹</button>`;
            
            let startPage = Math.max(1, this.currentPage - 2);
            let endPage = Math.min(totalPages, this.currentPage + 2);
            
            if (startPage > 1) {
                html += `<button class="page-btn" onclick="TokenManager.goToPage(1)">1</button>`;
                if (startPage > 2) html += `<span class="page-ellipsis">…</span>`;
            }
            
            for (let i = startPage; i <= endPage; i++) {
                html += `<button class="page-btn ${i === this.currentPage ? 'active' : ''}" onclick="TokenManager.goToPage(${i})">${i}</button>`;
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) html += `<span class="page-ellipsis">…</span>`;
                html += `<button class="page-btn" onclick="TokenManager.goToPage(${totalPages})">${totalPages}</button>`;
            }
            
            html += `<button class="page-btn" onclick="TokenManager.goToPage(${this.currentPage + 1})" ${this.currentPage >= totalPages ? 'disabled' : ''}>›</button>`;
            
            controls.innerHTML = html;
        },

        goToPage: function(page) {
            const totalPages = Math.ceil(this.filteredTokens.length / this.pageSize);
            if (page < 1 || page > totalPages) return;
            this.currentPage = page;
            this.renderTokens();
            this.updatePagination();
            document.querySelector('.tokens-table-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        updateTokenCount: function() {
            const display = document.getElementById('token-count-display');
            if (display) {
                const total = this.filteredTokens.length;
                const all = this.allTokens.length;
                display.textContent = total !== all ? `${total} of ${all} tokens` : `${total} tokens`;
            }
        },

        // ============================================
        // COPY TOKEN registration_token_modal
        // ============================================
        showCopyregistration_token_modal: function(tokenId) {
            const token = this.allTokens.find(t => t.id === tokenId);
            if (!token) {
                this.showNotification('Token not found', 'Error', true);
                return;
            }
            
            this._copyTokenData = token;
            
            const baseUrl = window.location.origin + window.location.pathname.replace('serveraccount.php', '');
            const registrationUrl = `${baseUrl}register.php?token=${encodeURIComponent(token.token)}`;
            
            document.getElementById('token-display-url').textContent = registrationUrl;
            document.getElementById('token-registration_token_modal-user').textContent = token.fullname || 'N/A';
            document.getElementById('token-registration_token_modal-email').textContent = token.email || 'N/A';
            document.getElementById('token-registration_token_modal-expires').textContent = this.formatDate(new Date(token.expires_at));
            document.getElementById('token-registration_token_modal-created').textContent = this.formatDate(new Date(token.created_at));
            
            document.getElementById('copy-token-registration_token_modal').style.display = 'flex';
        },

        closeCopyregistration_token_modal: function() {
            document.getElementById('copy-token-registration_token_modal').style.display = 'none';
            this._copyTokenData = null;
        },

        closeCopyregistration_token_modalIfClickOutside: function(event) {
            if (event.target.id === 'copy-token-registration_token_modal') {
                this.closeCopyregistration_token_modal();
            }
        },

        copyTokenToClipboard: function() {
            const url = document.getElementById('token-display-url').textContent;
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(() => {
                    const btn = document.querySelector('.btn-copy-token');
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '✅ Copied!';
                    setTimeout(() => { btn.innerHTML = originalText; }, 2000);
                }).catch(() => { this.fallbackCopy(url); });
            } else {
                this.fallbackCopy(url);
            }
        },

        fallbackCopy: function(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                const btn = document.querySelector('.btn-copy-token');
                const originalText = btn.innerHTML;
                btn.innerHTML = '✅ Copied!';
                setTimeout(() => { btn.innerHTML = originalText; }, 2000);
            } catch (err) {
                this.showNotification('Failed to copy. Please copy manually.', 'Error', true);
            }
            document.body.removeChild(textarea);
        },

        // ============================================
        // DELETE TOKEN
        // ============================================
        showDeleteregistration_token_modal: function(tokenId) {
            const token = this.allTokens.find(t => t.id === tokenId);
            if (!token) {
                this.showNotification('Token not found', 'Error', true);
                return;
            }
            
            this.tokenToDelete = tokenId;
            document.getElementById('token-delete-message').textContent = 
                `Are you sure you want to delete the token for "${token.fullname || 'User'}" (${token.email || 'N/A'})? This action cannot be undone.`;
            document.getElementById('token-delete-registration_token_modal').style.display = 'flex';
        },

        closeDeleteregistration_token_modal: function() {
            document.getElementById('token-delete-registration_token_modal').style.display = 'none';
            this.tokenToDelete = null;
        },

        confirmDeleteToken: function() {
            const tokenId = this.tokenToDelete;
            if (!tokenId) {
                this.closeDeleteregistration_token_modal();
                return;
            }
            
            this.closeDeleteregistration_token_modal();
            
            this.showPasswordregistration_token_modal(
                'Delete Token',
                'Enter admin password to delete this registration token.',
                function(password) {
                    const loginId = document.getElementById('login-id-hidden')?.value || '';
                    
                    if (!password) {
                        TokenManager.showNotification('Password is required', 'Error', true);
                        return;
                    }
                    
                    const confirmBtn = document.getElementById('token-password-confirm-btn');
                    const originalText = confirmBtn.textContent;
                    confirmBtn.textContent = 'Processing...';
                    confirmBtn.disabled = true;
                    
                    fetch(window.location.pathname, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: 'action=delete_registration_token&token_id=' + tokenId + 
                              '&admin_password=' + encodeURIComponent(password) + 
                              '&login_id=' + encodeURIComponent(loginId)
                    })
                    .then(response => response.json())
                    .then(data => {
                        confirmBtn.textContent = originalText;
                        confirmBtn.disabled = false;
                        
                        if (data.success) {
                            TokenManager.showNotification('Token deleted successfully!', 'Success', false);
                            TokenManager.loadTokens();
                        } else {
                            if (data.error === 'Invalid password') {
                                TokenManager.showNotification('Password verification failed.', 'Error', true);
                            } else {
                                TokenManager.showNotification('Error: ' + (data.error || 'Unknown error'), 'Error', true);
                            }
                        }
                    })
                    .catch(error => {
                        confirmBtn.textContent = originalText;
                        confirmBtn.disabled = false;
                        TokenManager.showNotification('Error: ' + error.message, 'Error', true);
                    });
                }
            );
        },

        // ============================================
        // CLEANUP EXPIRED TOKENS
        // ============================================
        cleanupExpired: function() {
            const expiredCount = this.allTokens.filter(t => {
                if (t.is_used) return false;
                return new Date(t.expires_at) < new Date();
            }).length;
            
            if (expiredCount === 0) {
                this.showNotification('No expired tokens to clean up.', 'Info', false);
                return;
            }
            
            this.showPasswordregistration_token_modal(
                'Cleanup Expired Tokens',
                `Delete ${expiredCount} expired registration token(s)? This action cannot be undone.`,
                function(password) {
                    const loginId = document.getElementById('login-id-hidden')?.value || '';
                    
                    if (!password) {
                        TokenManager.showNotification('Password is required', 'Error', true);
                        return;
                    }
                    
                    const confirmBtn = document.getElementById('token-password-confirm-btn');
                    const originalText = confirmBtn.textContent;
                    confirmBtn.textContent = 'Processing...';
                    confirmBtn.disabled = true;
                    
                    fetch(window.location.pathname, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: 'action=cleanup_expired_tokens&admin_password=' + encodeURIComponent(password) + 
                              '&login_id=' + encodeURIComponent(loginId)
                    })
                    .then(response => response.json())
                    .then(data => {
                        confirmBtn.textContent = originalText;
                        confirmBtn.disabled = false;
                        
                        if (data.success) {
                            TokenManager.showNotification(`Cleaned up ${data.deleted_count || 0} expired tokens!`, 'Success', false);
                            TokenManager.loadTokens();
                        } else {
                            if (data.error === 'Invalid password') {
                                TokenManager.showNotification('Password verification failed.', 'Error', true);
                            } else {
                                TokenManager.showNotification('Error: ' + (data.error || 'Unknown error'), 'Error', true);
                            }
                        }
                    })
                    .catch(error => {
                        confirmBtn.textContent = originalText;
                        confirmBtn.disabled = false;
                        TokenManager.showNotification('Error: ' + error.message, 'Error', true);
                    });
                }
            );
        },

        // ============================================
        // PASSWORD registration_token_modal
        // ============================================
        showPasswordregistration_token_modal: function(title, message, callback) {
            document.getElementById('token-password-registration_token_modal').style.display = 'flex';
            document.getElementById('token-password-message').textContent = message;
            document.getElementById('token-password-input').value = '';
            document.getElementById('token-password-error').style.display = 'none';
            this._passwordCallback = callback;
            setTimeout(() => document.getElementById('token-password-input').focus(), 100);
        },

        closePasswordregistration_token_modal: function() {
            document.getElementById('token-password-registration_token_modal').style.display = 'none';
            this._passwordCallback = null;
        },

        confirmPasswordregistration_token_modal: function() {
            const password = document.getElementById('token-password-input').value;
            const errorEl = document.getElementById('token-password-error');
            
            if (!password) {
                errorEl.textContent = 'Please enter your password.';
                errorEl.style.display = 'block';
                return;
            }
            
            const callback = this._passwordCallback;
            this.closePasswordregistration_token_modal();
            if (typeof callback === 'function') callback(password);
        },

        // ============================================
        // NOTIFICATION registration_token_modal
        // ============================================
        showNotification: function(message, title, isError) {
            document.getElementById('token-notification-title').textContent = title || (isError ? 'Error' : 'Success');
            document.getElementById('token-notification-message').textContent = message || '';
            document.getElementById('token-notification-registration_token_modal').style.display = 'flex';
        },

        closeNotificationregistration_token_modal: function() {
            document.getElementById('token-notification-registration_token_modal').style.display = 'none';
        },

        // ============================================
        // UTILITY FUNCTIONS
        // ============================================
        formatDate: function(date) {
            if (!date) return 'N/A';
            try {
                const d = new Date(date);
                if (isNaN(d.getTime())) return 'Invalid date';
                return d.toLocaleString('en-US', {
                    month: 'short',
                    day: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch(e) {
                return String(date);
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

    document.addEventListener('DOMContentLoaded', function() {
        TokenManager.init();
    });
</script>

