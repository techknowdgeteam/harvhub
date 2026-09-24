<style>
    /* ============================================================
    GLOBAL iOS ZOOM FIX
    iOS Safari auto-zooms any input with font-size < 16px.
    Force 16px on all form controls at mobile widths.
    ============================================================ */
    @media (max-width: 768px) {
        input,
        select,
        textarea,
        .dd-input,
        .dd-select,
        .dd-am-input,
        .dd-inline-input,
        .dd-req-input,
        .dd-json-edit-textarea,
        .pt-modal-input {
            font-size: 16px !important;
        }
    }
    /* ============================================================
    STYLE SHEET 1: ROOT VARIABLES, GLOBAL RESET, MODALS, SCROLLBARS
    ============================================================ */

    /* ===== ROOT VARIABLES - LIGHT MODE ===== */
    :root {
        --bg: #f0f4f8;
        --bg-card: #ffffff;
        --text: #1a2332;
        --text-secondary: #5a6c7d;
        --text-muted: #8a9aa8;
        --text-light: #e8edf2;
        --accent: #2d7b8c;
        --accent-light: #e8f4f7;
        --accent-hover: #236673;
        --success: #2ecc71;
        --success-bg: #eafaf1;
        --danger: #e74c3c;
        --danger-bg: #fdedec;
        --warning: #f39c12;
        --warning-bg: #fef9e7;
        --info: #3498db;
        --info-bg: #ebf5fb;
        --border-color: #e2e8f0;
        --shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.08);
        --radius: 16px;
        --radius-sm: 10px;
        
        --modal-overlay: rgba(0, 0, 0, 0.5);
        --modal-bg: #ffffff;
        --modal-text: #1a2332;
        --glass-border: rgba(0, 0, 0, 0.08);
        --input-bg: #f7f9fc;
        --input-border: #dce2eb;
        --input-text: #1a2332;
        --input-placeholder: #8a9aa8;
    }

    /* ===== DARK MODE ===== */
    body.dark-mode {
        --bg: #0d1117;
        --bg-card: #161b22;
        --text: #e6edf3;
        --text-secondary: #8b949e;
        --text-muted: #6e7681;
        --text-light: #30363d;
        --accent: #3fb5c9;
        --accent-light: #1a2a30;
        --accent-hover: #4ec5d9;
        --success: #3fb950;
        --success-bg: #1a2a1a;
        --danger: #f85149;
        --danger-bg: #2a1a1a;
        --warning: #d29922;
        --warning-bg: #2a241a;
        --info: #58a6ff;
        --info-bg: #1a2430;
        --border-color: #30363d;
        --shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
        --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.4);
        --modal-overlay: rgba(0, 0, 0, 0.8);
        --modal-bg: #161b22;
        --modal-text: #e6edf3;
        --glass-border: rgba(255, 255, 255, 0.08);
        --input-bg: #21262d;
        --input-border: #30363d;
        --input-text: #e6edf3;
        --input-placeholder: #6e7681;
    }

    /* ===== BASE RESET ===== */
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        background: var(--bg);
        color: var(--text);
        min-height: 100vh;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    body::-webkit-scrollbar { display: none; }
    html::-webkit-scrollbar { display: none; }

    /* ===== BLUR MODE ===== */
    .dashboard-wrapper.blur-mode .stat-card h2,
    .dashboard-wrapper.blur-mode .stat-card .card-value .value-amount {
        filter: blur(8px);
        user-select: none;
    }

    /* ===== SCROLLBAR GLOBAL ===== */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: var(--bg); }
    ::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 10px; }

    /* ============================================================
    MODAL STYLES - OTHER MODALS (Keep as popups)
    ============================================================ */
    /* ===== BODY SCROLL LOCK - Prevent scrolling when modal is open ===== */
    body.modal-open {
        overflow: hidden !important;
        height: 100vh !important;
        position: fixed !important;
        width: 100% !important;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
    }
    .modal:not(#revenueHistoryModal) {
        display: none;
        position: fixed;
        inset: 0;
        background: var(--modal-overlay);
        backdrop-filter: blur(6px);
        align-items: center;
        justify-content: center;
        z-index: 999;
        padding: 20px;
    }

    .modal:not(#revenueHistoryModal).active {
        display: flex;
    }

    .modal:not(#revenueHistoryModal) .modal-content {
        background: var(--modal-bg);
        color: var(--modal-text);
        padding: 32px;
        border-radius: var(--radius);
        max-width: 500px;
        width: 100%;
        max-height: 80vh;
        overflow-y: auto;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-lg);
    }

    .modal:not(#revenueHistoryModal) .modal-content h2 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 12px;
        color: var(--modal-text);
    }

    .modal:not(#revenueHistoryModal) .modal-content p,
    .modal:not(#revenueHistoryModal) .modal-content label,
    .modal:not(#revenueHistoryModal) .modal-content small {
        color: var(--modal-text);
    }

    .modal-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
    }

    .modal-actions button {
        flex: 1;
        padding: 10px 20px;
        border: none;
        border-radius: var(--radius-sm);
        font-weight: 600;
        cursor: pointer;
        color: #fff;
    }

    .modal-actions button:active { transform: scale(0.98); }

    .modal-actions .btn-confirm {
        background: var(--accent);
    }

    .modal-actions .btn-cancel {
        background: var(--text-muted);
    }



    /* ============================================================
    OTHER MODAL OVERRIDES - Keep these as popups
    ============================================================ */
    #profitSplitModal.modal .modal-content,
    #paymentModal.modal .modal-content,
    #paymentFailedModal.modal .modal-content,
    #finalConfirmationModal.modal .modal-content,
    #reenrollModal.modal .modal-content,
    #tradeHistoryModal.modal .modal-content,
    #disconnectModal.modal .modal-content,
    #finalDisconnectModal.modal .modal-content,
    #applyModal.modal .modal-content,
    #resetModal.modal .modal-content,
    #applySuccessModal.modal .modal-content {
        max-width: 500px;
        border-radius: var(--radius);
        padding: 32px;
        height: auto;
        max-height: 80vh;
    }

    /* ===== RESPONSIVE FOR OTHER MODALS ===== */
    @media (max-width: 768px) {
        #profitSplitModal.modal .modal-content,
        #paymentModal.modal .modal-content,
        #paymentFailedModal.modal .modal-content,
        #finalConfirmationModal.modal .modal-content,
        #reenrollModal.modal .modal-content,
        #tradeHistoryModal.modal .modal-content,
        #disconnectModal.modal .modal-content,
        #finalDisconnectModal.modal .modal-content,
        #applyModal.modal .modal-content,
        #resetModal.modal .modal-content,
        #applySuccessModal.modal .modal-content {
            padding: 20px;
            margin: 12px;
            max-height: 90vh;
        }
    }
</style>

<style>
    /* ===== PAGE-SPECIFIC STYLES - NO body or * overrides ===== */
    /* ============================================================
    HIDE BOTTOM NAV ON REVENUE HISTORY PAGE
    ============================================================ */
    body.page-revenue_history .bottom-nav {
        display: none !important;
    }

    /* Also remove the extra padding when nav is hidden */
    body.page-revenue_history {
        padding-bottom: 20px !important;
    }
    /* ===== MAIN CONTAINER - CENTERED, MAX WIDTH 800px ===== */
    .revenue-wrapper {
        width: 100%;
        max-width: 800px;
        margin: 0 auto;
        padding: 20px 16px 100px 16px;
    }

    /* ===== HEADER ===== */
    .revenue-header {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .revenue-header .back-btn {
        background: var(--bg-card);
        padding: 8px 20px;
        border-radius: var(--radius-sm, 8px);
        color: var(--text);
        text-decoration: none;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }

    .revenue-header .back-btn:hover {
        background: var(--accent, #2ecc71);
        color: #fff;
    }

    /* ===== REVENUE ITEM (CARD) ===== */
    .revenue-item {
        background: var(--bg-card);
        margin-bottom: 8px;
        overflow: hidden;
        transition: all 0.25s ease;
        cursor: pointer;
    }

    /* ===== FOLDED STATE (COLLAPSED) ===== */
    .revenue-header-folded {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        gap: 10px;
        transition: background 0.2s ease;
        min-height: 56px;
    }

    .revenue-header-folded:hover {
        background: var(--bg-hover, rgba(0,0,0,0.02));
    }

    body.dark-mode .revenue-header-folded:hover {
        background: var(--bg-hover, rgba(255,255,255,0.03));
    }

    .revenue-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 0 1 auto;
        min-width: 0;
    }

    .revenue-icon {
        font-size: 0.9rem;
        flex-shrink: 0;
        line-height: 1;
    }

    .revenue-user-share {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text);
        white-space: nowrap;
    }

    .revenue-user-share .currency {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--text-muted);
    }

    .revenue-right {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 0 0 auto;
        margin-left: auto;
    }

    .revenue-status-badge {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.55rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        white-space: nowrap;
    }

    /* Status Colors */
    .status-active { background: var(--success-bg, #d4edda); color: var(--success, #28a745); }
    .status-payment-confirmed { background: var(--info-bg, #d1ecf1); color: var(--info, #17a2b8); }
    .status-payment-made { background: var(--warning-bg, #fff3cd); color: var(--warning, #ffc107); }
    .status-unpaid-payment { background: var(--danger-bg, #f8d7da); color: var(--danger, #dc3545); }
    .status-payment-failed { background: var(--danger-bg, #f8d7da); color: var(--danger, #dc3545); }
    .status-failed-payment { background: var(--danger-bg, #f8d7da); color: var(--danger, #dc3545); }
    .status-loss_completed { background: var(--danger-bg, #f8d7da); color: var(--danger, #dc3545); }
    .status-below_threshold { background: var(--warning-bg, #fff3cd); color: var(--warning, #ffc107); }
    .status-completed { background: var(--success-bg, #d4edda); color: var(--success, #28a745); }
    .status-default { background: var(--bg, #e9ecef); color: var(--text-muted, #6c757d); }

    .revenue-toggle {
        font-size: 0.7rem;
        color: var(--text-muted);
        transition: transform 0.3s ease;
        flex-shrink: 0;
        width: 18px;
        text-align: center;
        font-weight: 700;
    }

    .revenue-item.expanded .revenue-toggle {
        transform: rotate(180deg);
    }

    /* ===== EXPANDED STATE (DETAILS) ===== */
    .revenue-details {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.35s ease, padding 0.35s ease, opacity 0.25s ease;
        opacity: 0;
        padding: 0 16px;
    }

    .revenue-item.expanded .revenue-details {
        max-height: 800px;
        opacity: 1;
        padding: 0 16px 16px 16px;
    }

    .revenue-details-inner {
        border-top: 1px solid var(--border-color);
        padding-top: 14px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 20px;
    }

    .revenue-detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 0;
        font-size: 0.85rem;
        border-bottom: 1px solid var(--border-color-light, rgba(0,0,0,0.04));
    }

    .revenue-detail-row:last-child {
        border-bottom: none;
    }

    .revenue-detail-row.full-width {
        grid-column: 1 / -1;
    }

    .revenue-detail-label {
        color: var(--text-muted);
        font-weight: 500;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        flex-shrink: 0;
    }

    .revenue-detail-value {
        color: var(--text);
        font-weight: 600;
        text-align: right;
        word-break: break-all;
        max-width: 60%;
    }

    .revenue-detail-value.mono {
        font-family: 'SF Mono', 'Courier New', monospace;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .revenue-detail-value.profit-positive {
        color: var(--success, #28a745);
    }

    .revenue-detail-value.profit-negative {
        color: var(--danger, #dc3545);
    }

    .revenue-detail-value.profit-neutral {
        color: var(--text-muted);
    }

    /* ===== EMPTY STATE ===== */
    .empty-revenue {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }

    .empty-revenue .empty-icon {
        font-size: 3.5rem;
        margin-bottom: 16px;
        display: block;
    }

    .empty-revenue h3 {
        font-size: 1.3rem;
        color: var(--text);
        margin-bottom: 8px;
    }

    .empty-revenue p {
        font-size: 0.95rem;
        max-width: 400px;
        margin: 0 auto;
        color: var(--text-muted);
    }

    /* ===== FLOATING CLOSE BUTTON ===== */
    .floating-close-btn {
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 999;
        background: var(--accent, #2ecc71);
        color: #fff;
        border: none;
        padding: 14px 40px;
        border-radius: 50px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 4px 20px rgba(46, 204, 113, 0.4);
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        min-width: 180px;
    }

    .floating-close-btn:hover {
        transform: translateX(-50%) scale(1.03);
        box-shadow: 0 6px 30px rgba(46, 204, 113, 0.6);
    }

    .floating-close-btn:active {
        transform: translateX(-50%) scale(0.97);
    }

    body.dark-mode .floating-close-btn {
        box-shadow: 0 4px 20px rgba(46, 204, 113, 0.3);
    }

    body.dark-mode .floating-close-btn:hover {
        box-shadow: 0 6px 30px rgba(46, 204, 113, 0.5);
    }

    /* ============================================================
        RESPONSIVE
        ============================================================ */

    @media (max-width: 600px) {
        .revenue-wrapper {
            padding: 12px 10px 90px 10px;
        }

        .revenue-header .back-btn {
            font-size: 0.75rem;
            padding: 5px 14px;
        }

        .revenue-header-folded {
            padding: 12px 14px;
            gap: 8px;
            min-height: 50px;
            flex-wrap: nowrap;
        }

        .revenue-left {
            gap: 8px;
            flex: 0 1 auto;
            min-width: 0;
        }

        .revenue-icon {
            font-size: 0.9rem;
        }

        .revenue-user-share {
            font-size: 1.2rem;
        }

        .revenue-user-share .currency {
            font-size: 0.75rem;
        }

        .revenue-right {
            gap: 6px;
            flex: 0 0 auto;
            margin-left: auto;
            flex-shrink: 0;
        }

        .revenue-status-badge {
            font-size: 0.45rem;
            padding: 2px 10px;
            letter-spacing: 0.3px;
        }

        .revenue-toggle {
            font-size: 0.65rem;
            width: 16px;
        }

        .revenue-details-inner {
            grid-template-columns: 1fr;
            gap: 4px;
            padding-top: 12px;
        }

        .revenue-detail-row {
            font-size: 0.8rem;
            padding: 4px 0;
        }

        .revenue-detail-value {
            max-width: 55%;
            font-size: 0.8rem;
        }

        .revenue-detail-row.full-width {
            grid-column: 1;
        }

        .revenue-item.expanded .revenue-details {
            padding: 0 14px 14px 14px;
        }

        .revenue-detail-label {
            font-size: 0.7rem;
        }

        .revenue-detail-value.mono {
            font-size: 0.7rem;
        }

        .floating-close-btn {
            bottom: 20px;
            padding: 12px 30px;
            font-size: 0.9rem;
            min-width: 140px;
        }
    }

    @media (max-width: 400px) {
        .revenue-wrapper {
            padding: 10px 8px 80px 8px;
        }

        .revenue-header-folded {
            padding: 10px 12px;
            gap: 6px;
            min-height: 46px;
        }

        .revenue-left {
            gap: 6px;
        }

        .revenue-icon {
            font-size: 1rem;
        }

        .revenue-user-share {
            font-size: 0.85rem;
        }

        .revenue-right {
            gap: 4px;
        }

        .revenue-status-badge {
            font-size: 0.4rem;
            padding: 2px 8px;
        }

        .revenue-toggle {
            font-size: 0.6rem;
            width: 14px;
        }

        .revenue-detail-row {
            font-size: 0.75rem;
            padding: 3px 0;
        }

        .revenue-detail-value {
            max-width: 50%;
            font-size: 0.75rem;
        }

        .revenue-item.expanded .revenue-details {
            padding: 0 12px 12px 12px;
        }

        .floating-close-btn {
            bottom: 16px;
            padding: 10px 24px;
            font-size: 0.8rem;
            min-width: 120px;
        }
    }

    @media (max-width: 340px) {
        .revenue-user-share {
            font-size: 0.75rem;
        }

        .revenue-status-badge {
            font-size: 0.35rem;
            padding: 1px 6px;
        }

        .revenue-toggle {
            font-size: 0.5rem;
            width: 12px;
        }
    }
</style>

<style>
    .profit-split-container {
        max-width: 800px;
        margin: 40px auto;
        padding: 0 20px;
    }

    .profit-split-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 16px;
    }

    .profit-split-header h1 {
        font-size: 1.8rem;
        color: var(--text);
        margin: 0;
    }

    .profit-split-header .back-btn {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        padding: 10px 24px;
        border-radius: var(--radius-sm);
        color: var(--text);
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .profit-split-header .back-btn:hover {
        background: var(--accent);
        color: #fff;
        border-color: var(--accent);
    }

    .profit-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 30px;
        box-shadow: var(--shadow);
        margin-bottom: 24px;
    }

    .profit-card .profit-amount {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--success);
        text-align: center;
        margin: 10px 0;
    }

    .profit-card .profit-label {
        text-align: center;
        color: var(--text-muted);
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .split-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin: 24px 0;
    }

    .split-box {
        background: var(--bg);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        padding: 20px;
        text-align: center;
    }

    .split-box .split-percent {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text);
    }

    .split-box .split-label {
        font-size: 0.8rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .split-box .split-amount {
        font-size: 1.5rem;
        font-weight: 700;
        margin-top: 8px;
    }

    .split-box.user-box .split-amount { color: var(--info); }
    .split-box.server-box .split-amount { color: #9b59b6; }

    .action-section {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
    }

    .payment-status {
        padding: 16px 20px;
        border-radius: var(--radius-sm);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .payment-status.info {
        background: var(--info-bg);
        border: 1px solid var(--info);
        color: var(--info);
    }

    .payment-status.warning {
        background: var(--warning-bg);
        border: 1px solid var(--warning);
        color: var(--warning);
    }

    .payment-status.success {
        background: var(--success-bg);
        border: 1px solid var(--success);
        color: var(--success);
    }

    .payment-status.danger {
        background: var(--danger-bg);
        border: 1px solid var(--danger);
        color: var(--danger);
    }

    .payment-status .status-icon {
        font-size: 1.5rem;
    }

    .btn-pay-server {
        display: inline-block;
        padding: 14px 40px;
        background: linear-gradient(135deg, #9b59b6, #8e44ad);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 100%;
    }

    .btn-pay-server:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(155, 89, 182, 0.3);
    }

    .btn-pay-server:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    .btn-withdraw-profit {
        display: inline-block;
        padding: 14px 40px;
        background: linear-gradient(135deg, var(--success), #27ae60);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 100%;
        text-decoration: none;
        text-align: center;
    }

    .btn-withdraw-profit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(46, 204, 113, 0.3);
    }

    .btn-retry {
        display: inline-block;
        padding: 14px 40px;
        background: linear-gradient(135deg, #f39c12, #e67e22);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 100%;
    }

    .btn-retry:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(243, 156, 18, 0.3);
    }

    .status-message {
        text-align: center;
        padding: 16px;
        font-weight: 500;
    }

    .status-message .status-title {
        font-size: 1.2rem;
        font-weight: 700;
    }

    /* Payment Modal Styles */
    .coin-selector {
        display: flex;
        gap: 12px;
        justify-content: center;
        margin: 16px 0;
        flex-wrap: wrap;
    }

    .coin-selector input[type="radio"] {
        display: none;
    }

    .coin-selector label {
        padding: 10px 24px;
        border: 2px solid var(--border-color);
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-weight: 600;
        transition: all 0.2s ease;
        background: var(--bg);
        color: var(--text);
    }

    .coin-selector input[type="radio"]:checked + label {
        border-color: var(--accent);
        background: var(--accent-light);
        color: var(--accent);
    }

    .coin-selector label:hover {
        border-color: var(--accent);
    }

    .crypto-details {
        background: var(--bg);
        padding: 16px;
        border-radius: var(--radius-sm);
        margin: 16px 0;
        text-align: center;
    }

    .crypto-details .address {
        font-family: 'SF Mono', monospace;
        font-size: 0.9rem;
        word-break: break-all;
        color: var(--accent);
        cursor: pointer;
        padding: 8px;
        background: var(--bg-card);
        border-radius: 4px;
        display: inline-block;
    }

    .crypto-details .network {
        color: var(--text-muted);
        font-size: 0.85rem;
    }

    .checkbox-container {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 16px 0;
        cursor: pointer;
    }

    .checkbox-container input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }

    .disclaimer {
        font-size: 0.8rem;
        color: var(--text-muted);
        text-align: center;
        margin-top: 12px;
    }

    .modal-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    .modal-actions button {
        padding: 10px 30px;
        border: none;
        border-radius: var(--radius-sm);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .modal-actions .btn-cancel {
        background: #555;
        color: white;
    }

    .modal-actions .btn-cancel:hover {
        background: #666;
    }

    .modal-actions .btn-confirm {
        background: var(--success);
        color: white;
    }

    .modal-actions .btn-confirm:hover {
        background: #27ae60;
    }

    .modal-actions .btn-confirm:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .split-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-top: 16px;
    }

    @media (max-width: 768px) {
        .split-grid {
            grid-template-columns: 1fr;
        }
        .split-actions {
            grid-template-columns: 1fr;
        }
        .profit-split-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<style>
    /* ============================================================
    MYDASHBOARD
    ============================================================ */
    .account-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 12px;
        padding-top: 14px;
        border-top: 1px solid var(--border-color);
    }

    .btn-account-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: var(--radius-sm);
        font-size: 0.9rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
    }

    .btn-account-action:active {
        transform: scale(0.97);
    }

    .btn-connect-broker {
        background: var(--accent);
        color: #fff;
    }

    .btn-connect-broker:hover {
        background: var(--accent-hover);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .btn-update-broker {
        background: var(--bg);
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
    }

    .btn-update-broker:hover {
        border-color: var(--accent);
        color: var(--text);
    }

    .btn-find-trader {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: #fff;
    }

    .btn-find-trader:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .btn-invested {
        background: var(--success-bg);
        color: var(--success);
        border: 1px solid var(--success);
        cursor: default;
        opacity: 0.8;
    }

    @media (max-width: 480px) {
        .btn-account-action {
            width: 100%;
            justify-content: center;
            padding: 12px 16px;
        }
        
        .account-actions {
            flex-direction: column;
        }
    }
    /* ============================================================
    CHART BARS - DYNAMIC VISUALIZATION (THIN BARS)
    ============================================================ */
    .chart-bars-container {
        margin-top: 14px;
        padding-top: 8px;
        border-top: 1px solid var(--border-color);
        width: 100%;
    }

    .chart-bars-wrapper {
        display: flex;
        align-items: flex-end;
        justify-content: center;
        height: 50px;
        gap: 1px;
        padding: 0;
        width: 100%;
    }

    .chart-bar-item {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        height: 100%;
        position: relative;
        max-width: 8px;
        min-width: 2px;
        margin: 0 0.5px;
    }

    .chart-bar {
        width: 100%;
        min-height: 2px;
        border-radius: 1px 1px 0 0;
        transition: height 0.6s ease, background-color 0.3s ease;
        position: relative;
        background: var(--border-color);
    }

    .chart-bar.green {
        background: var(--success);
    }

    .chart-bar.red {
        background: var(--danger);
    }

    .chart-bar.equal {
        background: var(--warning);
    }

    .chart-bar-value {
        font-size: 6px;
        font-weight: 600;
        color: var(--text-muted);
        margin-top: 3px;
        white-space: nowrap;
        opacity: 0.7;
        transition: opacity 0.3s ease;
        font-family: 'SF Mono', 'Monaco', monospace;
        line-height: 1;
    }

    .chart-bar-value.start-value {
        color: var(--accent);
        opacity: 0.9;
        font-weight: 700;
    }

    .chart-bar-value.end-value {
        color: var(--text);
        opacity: 0.9;
        font-weight: 700;
    }

    .chart-labels {
        display: flex;
        justify-content: space-between;
        margin-top: 2px;
        padding: 0;
    }

    .chart-label-start,
    .chart-label-end {
        font-size: 7px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        font-weight: 600;
        opacity: 0.5;
    }

    /* Bar Animation */
    .chart-bar-item {
        animation: barFadeIn 0.4s ease forwards;
        opacity: 0;
    }

    .chart-bar-item:nth-child(1) { animation-delay: 0.02s; }
    .chart-bar-item:nth-child(2) { animation-delay: 0.04s; }
    .chart-bar-item:nth-child(3) { animation-delay: 0.06s; }
    .chart-bar-item:nth-child(4) { animation-delay: 0.08s; }
    .chart-bar-item:nth-child(5) { animation-delay: 0.10s; }
    .chart-bar-item:nth-child(6) { animation-delay: 0.12s; }
    .chart-bar-item:nth-child(7) { animation-delay: 0.14s; }
    .chart-bar-item:nth-child(8) { animation-delay: 0.16s; }
    .chart-bar-item:nth-child(9) { animation-delay: 0.18s; }

    @keyframes barFadeIn {
        from {
            opacity: 0;
            transform: translateY(8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ============================================================
    CURRENT BALANCE CARD WITH INLINE CHART
    ============================================================ */
    .current-balance-card .card-value-row {
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
    }

    .current-balance-card .card-value {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 2.2rem;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 0;
        flex-wrap: wrap;
    }

    .current-balance-card .card-value .currency-symbol {
        font-size: 1.4rem;
        font-weight: 400;
        color: var(--text-muted);
    }

    .current-balance-card .card-value .value-amount {
        font-size: 2.2rem;
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    .current-balance-card .chart-bars-container {
        flex: 1;
        margin-top: 0;
        padding-top: 0;
        border-top: none;
        min-width: 60px;
    }

    .current-balance-card .chart-bars-wrapper {
        height: 40px;
        gap: 1.5px;
    }

    .current-balance-card .chart-bar-item {
        flex: 1;
        max-width: 8px;
        min-width: 2px;
        margin: 0 0.5px;
    }

    .current-balance-card .chart-bar {
        min-height: 2px;
        border-radius: 1px 1px 0 0;
    }

    /* Remove Start/Now labels for current balance card */
    .current-balance-card .chart-labels {
        display: none;
    }

    /* ===== RESPONSIVE DESIGN ===== */

    /* Tablet and small laptops */
    @media (max-width: 1024px) {
        .chart-bars-wrapper {
            height: 45px;
            gap: 1px;
        }
        
        .chart-bar-item {
            max-width: 7px;
            min-width: 2px;
            margin: 0 0.3px;
        }

        .current-balance-card .chart-bars-wrapper {
            height: 36px;
            gap: 1px;
        }
        
        .current-balance-card .chart-bar-item {
            max-width: 7px;
            min-width: 2px;
            margin: 0 0.3px;
        }
    }

    /* Large phones and tablets in portrait */
    @media (max-width: 768px) {
        .chart-bars-wrapper {
            height: 40px;
            gap: 1px;
        }
        
        .chart-bar-item {
            max-width: 6px;
            min-width: 2px;
            margin: 0 0.3px;
        }
        
        .chart-bar-value {
            font-size: 5px;
        }

        .current-balance-card .card-value-row {
            gap: 8px;
            flex-wrap: nowrap;
        }
        
        .current-balance-card .card-value {
            font-size: 1.8rem;
        }
        
        .current-balance-card .card-value .value-amount {
            font-size: 1.8rem;
        }
        
        .current-balance-card .chart-bars-wrapper {
            height: 32px;
            gap: 1px;
        }
        
        .current-balance-card .chart-bar-item {
            max-width: 6px;
            min-width: 1.5px;
            margin: 0 0.3px;
        }
    }

    /* Small phones in portrait */
    @media (max-width: 480px) {
        .chart-bars-wrapper {
            height: 35px;
            gap: 0.5px;
        }
        
        .chart-bar-item {
            max-width: 5px;
            min-width: 2px;
            margin: 0 0.2px;
        }
        
        .chart-bar-value {
            font-size: 4px;
            margin-top: 2px;
        }
        
        .chart-label-start,
        .chart-label-end {
            font-size: 6px;
        }

        .current-balance-card .card-value-row {
            gap: 6px;
        }
        
        .current-balance-card .card-value {
            font-size: 1.4rem;
        }
        
        .current-balance-card .card-value .value-amount {
            font-size: 1.4rem;
        }
        
        .current-balance-card .chart-bars-wrapper {
            height: 28px;
            gap: 0.5px;
        }
        
        .current-balance-card .chart-bar-item {
            max-width: 5px;
            min-width: 1.5px;
            margin: 0 0.2px;
        }
    }

    /* Very small phones */
    @media (max-width: 360px) {
        .chart-bars-wrapper {
            height: 30px;
            gap: 0.5px;
        }
        
        .chart-bar-item {
            max-width: 4px;
            min-width: 1.5px;
            margin: 0 0.15px;
        }

        .current-balance-card .chart-bars-wrapper {
            height: 24px;
            gap: 0.5px;
        }
        
        .current-balance-card .chart-bar-item {
            max-width: 4px;
            min-width: 1px;
            margin: 0 0.15px;
        }
    }

    /* Landscape phone mode */
    @media (max-height: 500px) and (orientation: landscape) {
        .chart-bars-wrapper {
            height: 30px;
            gap: 0.5px;
        }
        
        .chart-bar-item {
            max-width: 5px;
            min-width: 1.5px;
            margin: 0 0.2px;
        }

        .current-balance-card .chart-bars-wrapper {
            height: 26px;
            gap: 0.5px;
        }
        
        .current-balance-card .chart-bar-item {
            max-width: 5px;
            min-width: 1.5px;
            margin: 0 0.2px;
        }
    }

    /* Portrait mode specific adjustments */
    @media (orientation: portrait) {
        .chart-bars-wrapper {
            height: 35px;
        }
        
        .chart-bar-item {
            max-width: 5px;
            min-width: 2px;
        }

        .current-balance-card .chart-bars-wrapper {
            height: 30px;
        }
        
        .current-balance-card .chart-bar-item {
            max-width: 5px;
            min-width: 2px;
        }
    }

    /* High DPI / Retina screens */
    @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
        .chart-bar {
            border-radius: 0.5px 0.5px 0 0;
        }
    }

    /* Dark mode adjustments */
    @media (prefers-color-scheme: dark) {
        .chart-bar {
            background: var(--border-color);
        }
    }

    /* ============================================================
    STYLE SHEET 2: MYDASHBOARD CONTAINER & ALL DASHBOARD ELEMENTS
    ============================================================ */

    .mydashboard-box {
        max-width: 1100px;
        margin: 0 auto;
        padding: 0px 20px;
    }

    /* ===== DISCLAIMER ===== */
    .dashboard-disclaimer {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        background: var(--info-bg);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        margin-bottom: 20px;
        font-size: 0.95rem;
        color: var(--text-secondary);
    }

    .disclaimer-icon { font-size: 1.2rem; }

    .payment-required-badge {
        background: var(--danger);
        color: #fff;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-left: auto;
    }

    .threshold-warning {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        background: var(--warning-bg);
        border: 1px solid var(--warning);
        border-radius: var(--radius-sm);
        margin-bottom: 20px;
        font-size: 0.95rem;
        color: var(--text-secondary);
    }

    .warning-icon { font-size: 1.2rem; }

    .encouragement-note {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        background: var(--success-bg);
        border: 1px solid var(--success);
        border-radius: var(--radius-sm);
        margin-bottom: 20px;
        font-size: 0.95rem;
        font-style: italic;
        color: var(--text-secondary);
    }

    .encourage-icon { font-size: 1.2rem; }

    /* ===== ACCOUNT HEADER ===== */
    .account-header {
        margin-top: 30px;
        padding: 16px 20px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        margin-bottom: 24px;
        box-shadow: var(--shadow);
    }

    .account-info {
        gap: 16px;
        flex-wrap: wrap;
    }

    .account-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
        font-weight: 600;
    }

    .account-number {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text);
        font-family: 'SF Mono', 'Monaco', monospace;
    }

    .account-server {
        font-size: 0.85rem;
        color: var(--text-muted);
        background: var(--bg);
        padding: 2px 12px;
        border-radius: 20px;
    }

    .balance-toggle-form { flex-shrink: 0; }

    .balance-toggle-btn {
        background: var(--bg);
        border: 1px solid var(--border-color);
        border-radius: 50%;
        width: 40px;
        height: 40px;
        font-size: 1.1rem;
        cursor: pointer;
        color: var(--text);
    }

    .balance-toggle-btn:active { transform: scale(0.95); }

    /* ===== STATS GRID ===== */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }

    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: 1fr; }
    }

    /* ===== STAT CARDS ===== */
    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 24px 22px;
        box-shadow: var(--shadow);
        position: relative;
    }

    .card-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
        font-weight: 600;
        margin-bottom: 10px;
    }

    .card-value {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 2.2rem;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 4px;
        flex-wrap: wrap;
    }

    .card-value .currency-symbol {
        font-size: 1.4rem;
        font-weight: 400;
        color: var(--text-muted);
    }

    .card-value .value-amount {
        font-size: 2.2rem;
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    .card-value .status-badge {
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 2px 12px;
        border-radius: 20px;
        margin-left: 10px;
    }

    .status-badge.success {
        background: var(--success-bg);
        color: var(--success);
    }

    .status-badge.warning {
        background: var(--warning-bg);
        color: var(--warning);
    }

    .status-badge.info {
        background: var(--info-bg);
        color: var(--info);
    }

    .status-badge.danger {
        background: var(--danger-bg);
        color: var(--danger);
    }

    .card-sub {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-top: 2px;
    }

    /* Profit/Loss Colors */
    .profit-positive .value-amount { color: var(--success); }
    .profit-negative .value-amount { color: var(--danger); }

    /* PNL Indicator */
    .pnl-indicator {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 3px 14px;
        border-radius: 20px;
        margin-top: 8px;
        letter-spacing: 0.3px;
    }

    .pnl-indicator.positive {
        background: var(--success-bg);
        color: var(--success);
    }

    .pnl-indicator.negative {
        background: var(--danger-bg);
        color: var(--danger);
    }

    /* Mini Chart */
    .mini-chart {
        display: flex;
        align-items: flex-end;
        gap: 4px;
        margin-top: 14px;
        height: 30px;
    }

    .mini-chart .chart-bar {
        flex: 1;
        height: 8px;
        background: var(--border-color);
        border-radius: 4px;
    }

    .mini-chart .chart-bar:nth-child(1) { height: 14px; }
    .mini-chart .chart-bar:nth-child(2) { height: 22px; }
    .mini-chart .chart-bar:nth-child(3) { height: 28px; background: var(--accent); }
    .mini-chart .chart-bar:nth-child(4) { height: 18px; }
    .mini-chart .chart-bar:nth-child(5) { height: 10px; }

    /* Balance Card Specific */
    .balance-card .card-value.status-unverified .value-amount { color: var(--warning); }
    .balance-card .card-value.status-pending .value-amount { color: var(--info); }
    .balance-card .card-value.status-failed .value-amount { color: var(--danger); }

    /* ===== REVENUE HISTORY BUTTON ===== */
    .btn-revenue-history {
        background: var(--bg);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        padding: 8px 16px;
        font-size: 0.8rem;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
        margin-top: 14px;
        width: 100%;
    }

    .btn-revenue-history:active { transform: scale(0.98); }

    /* ===== LOYALTY CARD ===== */
    .loyalty-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 24px 28px;
        box-shadow: var(--shadow);
        margin-top: 4px;
    }

    .loyalty-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border-color);
    }

    .loyalty-status {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .status-indicator {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--text-muted);
        flex-shrink: 0;
    }

    .status-indicator.active { background: var(--success); }
    .status-indicator.completed { background: var(--info); }

    .loyalty-status-msg {
        font-size: 1.05rem;
        font-weight: 600;
        color: var(--text);
    }

    .loyalty-badge {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 4px 16px;
        background: var(--accent-light);
        color: var(--accent);
        border-radius: 20px;
        border: 1px solid var(--border-color);
    }

    .loyalty-body {
        display: flex;
        flex-wrap: wrap;
        gap: 20px 40px;
        margin-bottom: 20px;
    }

    .contract-dates,
    .contract-duration,
    .split-threshold {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
    }

    .date-label,
    .duration-label,
    .threshold-label {
        color: var(--text-muted);
        font-weight: 500;
    }

    .date-value,
    .duration-value,
    .threshold-value {
        color: var(--text);
        font-weight: 600;
    }

    .date-divider {
        color: var(--text-muted);
        margin: 0 2px;
    }

    .loyalty-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        padding-top: 16px;
        border-top: 1px solid var(--border-color);
    }

    .btn-action {
        padding: 10px 28px;
        font-size: 0.9rem;
        font-weight: 600;
        border: none;
        border-radius: var(--radius-sm);
        cursor: pointer;
        color: #fff;
        flex: 1;
        min-width: 180px;
    }

    .btn-action:active { transform: scale(0.98); }
    .btn-action:disabled { opacity: 0.5; cursor: not-allowed; }

    .btn-reset {
        background: linear-gradient(135deg, var(--accent), var(--accent-hover));
    }

    .btn-apply {
        background: linear-gradient(135deg, #667eea, #764ba2);
    }

    .btn-loyalty-action {
        background: linear-gradient(135deg, var(--accent), var(--accent-hover));
    }

    .btn-loyalty-paid {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        cursor: not-allowed;
    }

    .btn-loyalty-confirmed {
        background: linear-gradient(135deg, var(--success), #27ae60);
        cursor: default;
    }

    .btn-loyalty-failed {
        background: rgba(231, 76, 60, 0.15);
        color: #e74c3c;
        border: 2px solid #e74c3c;
    }

    .btn-loyalty-deposit {
        background: linear-gradient(135deg, #f39c12, #e67e22);
        color: white;
    }

    /* ===== RESPONSIVE FOR DASHBOARD ===== */
    @media (max-width: 768px) {
        .mydashboard-container {
            padding: 16px 12px;
        }
        
        .account-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
        
        .account-info { flex-wrap: wrap; }
        
        .card-value {
            font-size: 1.8rem;
        }
        .card-value .value-amount {
            font-size: 1.8rem;
        }
        
        .loyalty-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .loyalty-body {
            flex-direction: column;
            gap: 12px;
        }
        .loyalty-actions {
            flex-direction: column;
        }
        .btn-action {
            min-width: unset;
        }
    }
</style>

<style>
    
    /* ============================================
    TRADES PAGE STYLES
    ============================================ */
    .trade-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 0 15px;
    }

    .trades-header {
        margin-top: 30px;
        margin-bottom: 20px;
    }

    .trades-header h1 {
        font-size: 1.5rem;
        margin-bottom: 5px;
        color: var(--text);
    }

    .trades-header p {
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    .tab-content {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Daily Target Grid */
    .daily-target-grid {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .week-section {
        border-radius: 12px;
        padding: 16px;
        border: 1px solid var(--glass-border);
    }

    .week-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--glass-border);
    }

    .week-label {
        font-weight: bold;
        font-size: 14px;
        text-transform: uppercase;
        color: var(--accent);
    }

    .week-summary {
        font-size: 12px;
        color: var(--text-muted);
    }

    .daily-target-items {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
    }

    .daily-target-item {
        background: var(--bg, rgba(0,0,0,0.03));
        border-radius: 8px;
        padding: 12px;
        border-left: 3px solid var(--text-muted);
        transition: all 0.2s ease;
    }

    .daily-target-item.met {
        border-left-color: var(--success);
    }

    .daily-target-item.owed {
        border-left-color: var(--warning);
    }

    .daily-target-item.pending {
        border-left-color: var(--info);
    }

    .daily-target-item.not-listed {
        border-left-color: var(--text-muted);
        opacity: 0.6;
    }

    .daily-target-item .day-label {
        font-weight: 600;
        font-size: 13px;
        color: var(--text);
    }

    .daily-target-item .day-date {
        font-size: 11px;
        color: var(--text-muted);
    }

    .daily-target-item .day-status {
        font-size: 11px;
        margin-top: 4px;
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-weight: 500;
    }

    .daily-target-item .day-status.met {
        background: rgba(16, 185, 129, 0.15);
        color: var(--success);
    }

    .daily-target-item .day-status.owed {
        background: rgba(245, 158, 11, 0.15);
        color: var(--warning);
    }

    .daily-target-item .day-status.pending {
        background: rgba(59, 130, 246, 0.15);
        color: var(--info);
    }

    .daily-target-item .day-status.not-listed {
        background: rgba(136, 136, 136, 0.15);
        color: var(--text-muted);
    }

    .daily-target-item .target-amounts {
        margin-top: 6px;
        font-size: 12px;
        color: var(--text-secondary);
    }

    .daily-target-item .target-amounts .target {
        color: var(--text-muted);
    }

    .daily-target-item .target-amounts .allocated {
        color: var(--success);
    }

    .daily-target-item .target-amounts .remaining {
        color: var(--warning);
    }

    /* Mobile optimizations for trades */
    @media (max-width: 480px) {
        .daily-target-items {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        
        .daily-target-item {
            padding: 10px;
        }
    }

    @media (max-width: 360px) {
        .daily-target-items {
            grid-template-columns: 1fr;
        }
    }
</style>

<style>
    /* ===== MENU PAGE STYLES ===== */
    .menu-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 0 15px;
    }

    .menu-header {
        margin-top: 30px;
        margin-bottom: 25px;
    }

    .menu-header h1 {
        font-size: 1.5rem;
        margin-bottom: 5px;
        color: var(--text);
    }

    .menu-header p {
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    /* User card */
    .user-card {
        background: var(--card-light);
        border-radius: 12px;
        padding: 20px;
        border: 1px solid var(--glass-border);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.2s ease;
    }

    .user-card.profile-clickable {
        cursor: pointer;
        position: relative;
    }

    .user-card.profile-clickable:hover {
        background: var(--bg, rgba(0,0,0,0.03));
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.06);
    }

    .user-card.profile-clickable:active {
        transform: translateY(0);
    }

    .user-card.profile-clickable:focus {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
    }

    .user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: white;
        flex-shrink: 0;
    }

    .user-info .user-name {
        font-size: 18px;
        font-weight: 600;
        color: var(--text);
    }

    .user-info .user-email {
        font-size: 13px;
        color: var(--text-muted);
    }

    .profile-chevron {
        margin-left: auto;
        color: var(--text-muted);
        font-size: 14px;
        transition: transform 0.2s ease, color 0.2s ease;
        flex-shrink: 0;
    }

    .user-card.profile-clickable:hover .profile-chevron {
        transform: translateX(3px);
        color: var(--accent);
    }

    /* Menu items */
    .menu-items {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .menu-item {
        background: var(--card-light);
        border-radius: 10px;
        padding: 16px 18px;
        border: 1px solid var(--glass-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        color: var(--text);
    }

    .menu-item:hover {
        background: var(--bg, rgba(0,0,0,0.03));
        transform: translateX(4px);
    }

    .menu-item .item-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .menu-item .item-icon {
        font-size: 22px;
        width: 36px;
        text-align: center;
    }

    .menu-item .item-text {
        display: flex;
        flex-direction: column;
    }

    .menu-item .item-title {
        font-size: 15px;
        font-weight: 500;
        color: var(--text);
    }

    .menu-item .item-desc {
        font-size: 12px;
        color: var(--text-muted);
    }

    .menu-item .item-arrow {
        color: var(--text-muted);
        font-size: 16px;
    }

    .menu-item.danger {
        border-color: rgba(239, 68, 68, 0.2);
    }

    .menu-item.danger .item-title {
        color: var(--danger);
    }

    .menu-item.danger:hover {
        background: rgba(239, 68, 68, 0.06);
        border-color: rgba(239, 68, 68, 0.3);
    }

    /* Dark Mode Toggle Item */
    .menu-item.dark-mode-toggle-item {
        cursor: pointer;
    }

    .menu-item.dark-mode-toggle-item:hover {
        background: var(--bg, rgba(0,0,0,0.03));
        transform: translateX(4px);
    }

    /* Toggle switch */
    .toggle-switch {
        position: relative;
        width: 50px;
        height: 28px;
        flex-shrink: 0;
        margin-left: 8px;
        cursor: pointer;
        display: inline-block;
    }

    .toggle-switch input[type="checkbox"] {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        margin: 0;
        cursor: pointer;
        z-index: 10;
        opacity: 1 !important;
        width: 50px !important;
        height: 28px !important;
        -webkit-appearance: none;
        appearance: none;
        background: transparent;
        border: none;
        outline: none;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--text-muted);
        transition: 0.3s;
        border-radius: 34px;
        pointer-events: none;
        z-index: 1;
    }

    .toggle-slider::before {
        content: "";
        position: absolute;
        height: 20px;
        width: 20px;
        left: 4px;
        bottom: 4px;
        background: white;
        transition: 0.3s;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .toggle-switch input[type="checkbox"]:checked + .toggle-slider {
        background: var(--accent);
    }

    .toggle-switch input[type="checkbox"]:checked + .toggle-slider::before {
        transform: translateX(22px);
    }

    .dark-mode-icon {
        font-size: 20px;
        width: 36px;
        text-align: center;
    }

    .mode-label {
        font-size: 13px;
        color: var(--text-muted);
        margin-left: auto;
        margin-right: 5px;
    }

    .menu-divider {
        border: none;
        border-top: 1px solid var(--glass-border);
        margin: 10px 0;
    }

    .version-info {
        text-align: center;
        padding: 20px 0 10px 0;
        font-size: 11px;
        color: var(--text-muted);
    }

    /* ============================================================
       PAGE VIEW SYSTEM (menu <-> profile, like a page switch)
       ============================================================ */
    .page-view {
        transition: opacity 0.2s ease, transform 0.2s ease;
    }

    .page-view.hidden {
        display: none;
    }

    .profile-page {
        display: none;
        min-height: 100vh;
        min-height: 100dvh;
        width: 100%;
        background: var(--bg, #0f0f1a);
        padding: 0 0 40px 0;
        box-sizing: border-box;
    }

    .profile-page.active {
        display: block;
        animation: profilePageIn 0.22s ease;
    }

    @keyframes profilePageIn {
        from { opacity: 0; transform: translateX(12px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    .profile-page-inner {
        max-width: 520px;
        margin: 0 auto;
        padding: 20px 18px 40px;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        box-sizing: border-box;
        min-height: 100vh;
        min-height: 100dvh;
        justify-content: center;
    }

    .profile-page-header {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 30px;
        position: relative;
    }

    .profile-page-close {
        background: var(--card-light, rgba(255,255,255,0.05));
        border: 1px solid var(--glass-border, rgba(255,255,255,0.08));
        color: var(--text, #fff);
        font-size: 18px;
        cursor: pointer;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s ease, transform 0.15s ease;
        -webkit-tap-highlight-color: transparent;
        flex-shrink: 0;
    }

    .profile-page-close:hover {
        background: rgba(255,255,255,0.1);
        transform: scale(1.05);
    }

    .profile-page-close:active {
        transform: scale(0.95);
    }

    .profile-page-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text, #fff);
        margin: 0;
    }

    .profile-page-avatar-wrap {
        display: flex;
        justify-content: center;
        margin-bottom: 28px;
    }

    .profile-page-avatar {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        background: var(--accent, #2e8b57);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        color: #fff;
        font-weight: 600;
        box-shadow: 0 12px 36px rgba(46, 139, 87, 0.35);
    }

    .profile-page-section {
        margin-bottom: 18px;
        background: var(--card-light, rgba(255,255,255,0.04));
        border: 1px solid var(--glass-border, rgba(255,255,255,0.08));
        border-radius: 16px;
        padding: 18px 22px;
        width: 100%;
        text-align: left;
        box-sizing: border-box;
    }

    .profile-page-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        color: var(--text-muted, #888);
        margin-bottom: 6px;
    }

    .profile-page-value {
        font-size: 16px;
        font-weight: 500;
        color: var(--text, #fff);
        word-break: break-word;
        line-height: 1.5;
    }

    .profile-page-email {
        font-size: 14px;
        color: var(--text-secondary, #ccc);
    }

    /* ============================================================
       TIER LIMIT DISPLAY (below email in profile)
       ============================================================ */
    .profile-tier-block {
        margin-top: 16px;
        padding-top: 14px;
        border-top: 1px solid var(--glass-border, rgba(255,255,255,0.08));
    }

    .profile-tier-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        color: var(--text-muted, #888);
        margin-bottom: 10px;
    }

    .profile-tier-empty {
        font-size: 13px;
        color: var(--text-muted, #888);
        font-style: italic;
        padding: 8px 0;
    }

    .profile-tier-entry {
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--glass-border, rgba(255,255,255,0.08));
        border-radius: 10px;
        padding: 12px 14px;
        margin-bottom: 10px;
    }

    .profile-tier-entry:last-child {
        margin-bottom: 0;
    }

    .profile-tier-entry-key {
        font-size: 13px;
        font-weight: 700;
        color: var(--accent, #2e8b57);
        font-family: monospace;
        background: rgba(46, 139, 87, 0.12);
        padding: 3px 10px;
        border-radius: 6px;
        display: inline-block;
        margin-bottom: 10px;
    }

    .profile-tier-entry-fields {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .profile-tier-field-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 6px 10px;
        background: rgba(0,0,0,0.15);
        border-radius: 6px;
        font-size: 12px;
    }

    .profile-tier-field-name {
        font-weight: 600;
        color: var(--text-muted, #888);
        font-family: monospace;
        word-break: break-all;
        flex-shrink: 0;
    }

    .profile-tier-field-value {
        color: var(--text, #fff);
        font-weight: 500;
        text-align: right;
        word-break: break-word;
    }

    .profile-tier-empty-fields {
        font-size: 12px;
        color: var(--text-muted, #888);
        font-style: italic;
    }

    .profile-page-actions {
        margin-top: 24px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        width: 100%;
    }

    .profile-page-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        padding: 15px 20px;
        border-radius: 14px;
        font-size: 15px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: transform 0.15s ease, background 0.2s ease, box-shadow 0.2s ease;
        border: none;
        -webkit-tap-highlight-color: transparent;
        background: var(--accent, #2e8b57);
        color: #fff;
        box-shadow: 0 6px 20px rgba(46, 139, 87, 0.35);
        box-sizing: border-box;
    }

    .profile-page-btn:hover {
        background: #3a9b67;
        box-shadow: 0 8px 26px rgba(46, 139, 87, 0.45);
    }

    .profile-page-btn:active {
        transform: scale(0.98);
    }

    /* ============================================================
       LOGOUT MODAL
       ============================================================ */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--modal-overlay);
        backdrop-filter: blur(8px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-box {
        background: var(--modal-bg);
        border-radius: 12px;
        padding: 25px;
        max-width: 400px;
        width: 100%;
        border: 1px solid var(--glass-border);
    }

    .modal-box h2 {
        color: var(--danger);
        margin-bottom: 12px;
    }

    .modal-box p {
        color: var(--text-secondary);
        margin-bottom: 20px;
        line-height: 1.6;
    }

    .modal-actions {
        display: flex;
        gap: 10px;
    }

    .modal-actions button {
        flex: 1;
        padding: 12px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        font-size: 14px;
        transition: all 0.2s ease;
    }

    .btn-cancel {
        background: var(--bg, rgba(0,0,0,0.05));
        color: var(--text);
    }

    .btn-cancel:hover {
        background: var(--bg-tertiary, rgba(0,0,0,0.08));
    }

    .btn-danger-confirm {
        background: var(--danger);
        color: white;
    }

    .btn-danger-confirm:hover {
        background: #dc2626;
    }

    /* ============================================================
       MOBILE RESPONSIVE
       ============================================================ */
    @media (max-width: 480px) {
        .menu-container {
            padding: 0 12px;
        }

        .menu-header {
            margin-top: 30px;
        }

        .user-card {
            padding: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            font-size: 22px;
        }

        .menu-item {
            padding: 14px 16px;
        }

        .menu-item .item-title {
            font-size: 14px;
        }

        .profile-page-inner {
            padding: 16px 14px 40px;
            justify-content: flex-start;
            padding-top: 40px;
        }

        .profile-page-avatar {
            width: 90px;
            height: 90px;
            font-size: 38px;
        }

        .profile-page-section {
            padding: 15px 18px;
        }

        .profile-page-value {
            font-size: 15px;
        }

        .profile-page-email {
            font-size: 13px;
        }

        .profile-page-btn {
            padding: 14px 18px;
            font-size: 14px;
        }

        .profile-tier-field-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }

        .profile-tier-field-value {
            text-align: left;
        }
    }

    /* Bottom Nav Override for dark mode */
    body.dark-mode .bottom-nav {
        background: rgba(20, 20, 30, 0.3);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
    }

    body:not(.dark-mode) .bottom-nav {
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }
</style>

<style>
    /* ============================================
    ACTIVITY PAGE STYLES (Balance Log)
    ============================================ */
    .activities-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 0 15px;
    }

    .activity-header {
        margin-top: 30px;
        margin-bottom: 20px;
    }

    .activity-header h1 {
        font-size: 1.5rem;
        margin-bottom: 5px;
        color: var(--text);
        Margin:10px;
    }

    .activity-header p {
        color: var(--text-muted);
        font-size: 0.9rem;
        Margin:10px;
    }

    .balance-log-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .balance-log-item {
        border-radius: 10px;
        border: 1px solid var(--glass-border);
        overflow: hidden;
    }

    .balance-log-item.unusual {
        border-color: var(--danger);
        background: rgba(239, 68, 68, 0.05);
    }

    .log-header {
        padding: 12px 16px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.2s ease;
    }

    .log-header:hover {
        background: var(--bg, rgba(0,0,0,0.03));
    }

    .log-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .log-day {
        font-weight: 600;
        font-size: 14px;
        color: var(--text);
    }

    .log-date {
        font-size: 12px;
        color: var(--text-muted);
    }

    .log-toggle {
        font-size: 12px;
        color: var(--text-muted);
        transition: transform 0.3s ease;
    }

    .log-details {
        padding: 0 16px 16px 16px;
        display: none;
        border-top: 1px solid var(--glass-border);
        padding-top: 12px;
    }

    .log-details.open {
        display: block;
    }

    .log-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        font-size: 13px;
        color: var(--text-secondary);
    }

    .log-label {
        color: var(--text-muted);
    }

    .log-value.profit {
        color: var(--success);
    }

    .log-value.loss {
        color: var(--danger);
    }

    .log-value.unusual {
        color: var(--danger);
        font-weight: 600;
    }

    .unauthorized-trades-section {
        margin-top: 8px;
        padding: 8px 12px;
        background: rgba(239, 68, 68, 0.06);
        border-radius: 6px;
    }

    .unauthorized-trades-section .log-label {
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }

    .trade-row-detail {
        display: flex;
        justify-content: space-between;
        padding: 2px 0;
        font-size: 12px;
        border-bottom: 1px solid var(--glass-border);
    }

    .trade-row-detail:last-child {
        border-bottom: none;
    }

    .trade-symbol {
        font-weight: 500;
        color: var(--text);
    }

    .trade-pnl.profit {
        color: var(--success);
    }

    .trade-pnl.loss {
        color: var(--danger);
    }

    .trade-meta {
        color: var(--text-muted);
        font-size: 10px;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-muted);
    }

    .empty-state .empty-icon {
        font-size: 48px;
        margin-bottom: 12px;
        opacity: 0.5;
    }

    .empty-state .empty-text {
        font-size: 16px;
        font-weight: 500;
        color: var(--text-secondary);
    }

    .empty-state .empty-sub {
        font-size: 13px;
        margin-top: 4px;
        color: var(--text-muted);
    }

    .status-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 500;
    }

    .status-badge.status-unusual {
        background: rgba(239, 68, 68, 0.12);
        color: var(--danger);
    }

    .status-badge.status-profit {
        background: rgba(16, 185, 129, 0.12);
        color: var(--success);
    }

    .status-badge.status-loss {
        background: rgba(239, 68, 68, 0.12);
        color: var(--danger);
    }


</style>

<style>
   /* ============================================
   ANALYTICS PAGE STYLES
   ============================================ */
    .analytics-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 0 15px;
    }

    .analytics-header {
        margin-top: 30px;
        margin-bottom: 20px;
    }

    .analytics-header h1 {
        font-size: 1.5rem;
        margin-bottom: 5px;
        color: var(--text);
    }

    .analytics-header p {
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    .analytics-header .user-info {
        background: var(--bg, rgba(0,0,0,0.03));
        border-radius: 8px;
        padding: 10px 14px;
        margin-top: 10px;
        font-size: 13px;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        border: 1px solid var(--glass-border);
    }

    .analytics-header .user-info span {
        color: var(--text-muted);
    }

    .analytics-header .user-info strong {
        color: var(--text);
    }

    /* Stats Grid for Analytics - NO ANIMATION */
    .analytics-container .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin: 15px 0;
    }

    @media (min-width: 480px) {
        .analytics-container .stats-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (min-width: 768px) {
        .analytics-container .stats-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }

    .analytics-container .stat-card {
        background: var(--card-light);
        border-radius: 10px;
        padding: 12px;
        text-align: center;
        border: 1px solid var(--glass-border);
        /* NO transition, NO animation, NO transform */
    }

    .analytics-container .stat-card .stat-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        margin-bottom: 4px;
    }

    .analytics-container .stat-card .stat-value {
        font-size: 18px;
        font-weight: bold;
        color: var(--text);
    }

    .analytics-container .stat-card .stat-value.profit {
        color: var(--success);
    }

    .analytics-container .stat-card .stat-value.loss {
        color: var(--danger);
    }

    .analytics-container .stat-card .stat-value.neutral {
        color: var(--text);
    }

    .analytics-container .stat-card .stat-sub {
        font-size: 10px;
        color: var(--text-muted);
        margin-top: 3px;
    }

    /* Section Cards for Analytics */
    .analytics-container .section-card {
        background: var(--card-light);
        border-radius: 10px;
        padding: 16px;
        border: 1px solid var(--glass-border);
        margin-bottom: 16px;
        transition: box-shadow 0.2s ease;
    }

    .analytics-container .section-card:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    .analytics-container .section-title {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--glass-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: var(--text);
    }

    .analytics-container .section-title .badge {
        font-size: 11px;
        background: var(--bg, rgba(0,0,0,0.03));
        padding: 2px 10px;
        border-radius: 12px;
        color: var(--text-muted);
    }

    /* Section grid for two-column layout */
    .analytics-container .section-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        color: var(--text-secondary);
    }

    .analytics-container .section-grid strong {
        color: var(--text);
    }

    @media (max-width: 480px) {
        .analytics-container .section-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Trades Grid for Authorized/Unauthorized cards */
    .analytics-container .trades-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    @media (min-width: 600px) {
        .analytics-container .trades-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }

    .analytics-container .trade-stat-box {
        background: var(--bg, rgba(0,0,0,0.03));
        border-radius: 8px;
        padding: 12px;
        text-align: center;
        border: 1px solid var(--glass-border);
        transition: transform 0.2s ease;
    }

    .analytics-container .trade-stat-box:hover {
        transform: translateY(-2px);
    }

    .analytics-container .trade-stat-box .trade-stat-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        margin-bottom: 4px;
    }

    .analytics-container .trade-stat-box .trade-stat-value {
        font-size: 18px;
        font-weight: bold;
        color: var(--text);
    }

    .analytics-container .trade-stat-box .trade-stat-value.profit {
        color: var(--success);
    }

    .analytics-container .trade-stat-box .trade-stat-value.loss {
        color: var(--danger);
    }

    .analytics-container .trade-stat-box .trade-stat-value.neutral {
        color: var(--text);
    }

    .analytics-container .trade-stat-box .trade-stat-count {
        font-size: 10px;
        color: var(--text-muted);
        margin-top: 3px;
    }

    /* Sequential losses box */
    .analytics-container .loss-box {
        background: rgba(239, 68, 68, 0.06);
        border-radius: 8px;
        padding: 12px;
        border-left: 3px solid var(--danger);
    }

    .analytics-container .loss-box .loss-count {
        font-size: 24px;
        font-weight: bold;
        color: var(--danger);
    }

    .analytics-container .loss-box .loss-label {
        font-size: 12px;
        color: var(--text-muted);
    }

    /* Symbols List - Row layout */
    .analytics-container .symbols-list {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .analytics-container .symbol-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 14px;
    }

    .analytics-container .symbol-row .symbol-name {
        font-weight: 500;
        color: var(--text);
    }

    .analytics-container .symbol-row .symbol-pnl {
        font-weight: 600;
        font-size: 14px;
    }

    .analytics-container .symbol-row .symbol-pnl.profit {
        color: var(--success);
    }

    .analytics-container .symbol-row .symbol-pnl.loss {
        color: var(--danger);
    }

    /* Chart Styles */
    .chart-container {
        background: var(--card-light);
        border-radius: 12px;
        padding: 16px;
        margin: 15px 0 20px 0;
        border: 1px solid var(--glass-border);
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--glass-border);
    }

    .chart-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--text);
    }

    .chart-stats {
        display: flex;
        gap: 15px;
        align-items: center;
    }

    .chart-stat {
        font-size: 13px;
        font-weight: 600;
        color: var(--text);
    }

    .chart-stat.profit {
        color: var(--success);
    }

    .chart-stat.loss {
        color: var(--danger);
    }

    .chart-wrapper {
        position: relative;
        width: 100%;
        min-height: 250px;
    }

    .pnl-chart {
        width: 100%;
        height: auto;
        display: block;
        border-radius: 6px;
        background: transparent;
    }

    .chart-legend {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px solid var(--glass-border);
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        color: var(--text-muted);
    }

    .legend-line {
        display: inline-block;
        width: 20px;
        height: 2.5px;
        border-radius: 2px;
    }

    .legend-line[style*="dashed"] {
        height: 0;
        border-top: 2px dashed #888;
        width: 20px;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: var(--card-light);
        border-radius: 12px;
        border: 1px solid var(--glass-border);
        margin: 20px 0;
    }

    .empty-icon {
        font-size: 48px;
        margin-bottom: 15px;
    }

    .empty-text {
        font-size: 18px;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 8px;
    }

    .empty-sub {
        font-size: 14px;
        color: var(--text-muted);
        max-width: 350px;
        margin: 0 auto;
        line-height: 1.5;
    }

    /* Canvas tooltip override */
    #chart-tooltip {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.4;
    }

    /* Dark mode adjustments for chart */
    @media (prefers-color-scheme: dark) {
        .chart-container {
            background: rgba(255,255,255,0.03);
        }
        
        .pnl-chart {
            background: transparent;
        }
    }

    /* Mobile adjustments */
    @media (max-width: 480px) {
        .chart-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
        
        .chart-stats {
            width: 100%;
            justify-content: flex-start;
        }
        
        .chart-wrapper {
            min-height: 200px;
        }
        
        .chart-legend {
            flex-wrap: wrap;
            gap: 10px;
        }

        .analytics-container .symbol-row {
            font-size: 13px;
            padding: 6px 10px;
        }

        .analytics-container .symbol-row .symbol-pnl {
            font-size: 13px;
        }
    }
</style>

<style>
    /* ============================================================
    HEADER STYLES - FIXED TOP
    ============================================================ */
    
    .harvhub-header-top {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 60px;
        background: var(--bg, #ffffff);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--bg);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 20px;
        z-index: 10000;
        box-shadow: 0 1px 10px rgba(0, 0, 0, 0.05);
        transition: background 0.3s ease;
    }
    
    body.dark-mode .harvhub-header-top {
        background: var(--bg);
        border-bottom: 1px solid var(--bg);
    }
    
    .harvhub-header-top .header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .harvhub-header-top .header-logo {
        font-size: 20px;
        font-weight: 700;
        color: var(--accent, #10b981);
        letter-spacing: -0.5px;
    }
    
    .harvhub-header-top .header-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    /* ============================================================
    NOTIFICATION BELL - INSIDE HEADER
    ============================================================ */
    .harvhub-header-top .notification-bell {
        position: relative;
        cursor: pointer;
        background: var(--bg, rgba(0,0,0,0.05));
        padding: 8px;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--glass-border, rgba(0,0,0,0.08));
        transition: all 0.3s ease;
        z-index: 10001;
    }
    
    .harvhub-header-top .notification-bell:hover {
        background: var(--accent, #10b981);
        transform: scale(1.05);
        border-color: var(--accent, #10b981);
    }
    
    .harvhub-header-top .notification-bell:hover i {
        color: white;
    }
    
    .harvhub-header-top .notification-bell i {
        font-size: 18px;
        color: var(--text, #1a2332);
        transition: color 0.3s ease;
    }
    
    body.dark-mode .harvhub-header-top .notification-bell i {
        color: var(--text, #e6edf3);
    }
    
    .harvhub-header-top .notification-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: var(--danger, #e74c3c);
        color: white;
        border-radius: 50%;
        min-width: 18px;
        height: 18px;
        font-size: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        border: 2px solid var(--modal-bg, #ffffff);
        padding: 0 4px;
        animation: pulse 2s infinite;
    }
    
    body.dark-mode .harvhub-header-top .notification-badge {
        border-color: var(--modal-bg, #161b22);
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    /* ============================================================
    NOTIFICATION PANEL - FULL SCREEN LIKE REVENUE HISTORY
    ============================================================ */
    .harvhub-header-top ~ .notification-panel,
    .notification-panel {
        display: none !important;
        position: fixed;
        inset: 0;
        width: 100%;
        height: 100%;
        background: var(--modal-overlay, rgba(0,0,0,0.8));
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 99999;
        padding: 0;
        margin: 0;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        overflow: hidden;
    }
    
    .harvhub-header-top ~ .notification-panel.active,
    .notification-panel.active {
        display: flex !important;
    }
    
    /* Panel content - same as revenue history modal */
    .harvhub-header-top ~ .notification-panel .panel-content,
    .notification-panel .panel-content {
        background: var(--modal-bg, #ffffff);
        color: var(--modal-text, #1a2332);
        border-radius: 0;
        padding: 0;
        max-width: 800px;
        width: 100%;
        height: 100%;
        max-height: 100vh;
        border: none;
        box-shadow: none;
        margin: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        position: relative;
    }
    
    body.dark-mode .harvhub-header-top ~ .notification-panel .panel-content,
    body.dark-mode .notification-panel .panel-content {
        background: rgba(22, 27, 34, 0.95);
        color: var(--modal-text, #e6edf3);
    }
    
    /* Panel Header */
    .harvhub-header-top ~ .notification-panel .notification-header,
    .notification-panel .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px 16px 24px;
        border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.08));
        flex-shrink: 0;
        background: var(--modal-bg, #ffffff);
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    body.dark-mode .harvhub-header-top ~ .notification-panel .notification-header,
    body.dark-mode .notification-panel .notification-header {
        background: rgba(22, 27, 34, 0.95);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    
    .harvhub-header-top ~ .notification-panel .notification-header h3,
    .notification-panel .notification-header h3 {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0;
        color: var(--modal-text, #1a2332);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    body.dark-mode .harvhub-header-top ~ .notification-panel .notification-header h3,
    body.dark-mode .notification-panel .notification-header h3 {
        color: var(--modal-text, #e6edf3);
    }
    
    .harvhub-header-top ~ .notification-panel .notification-header h3::before,
    .notification-panel .notification-header h3::before {
        content: "🔔";
        font-size: 1.3rem;
    }
    
    .harvhub-header-top ~ .notification-panel .close-notifications,
    .notification-panel .close-notifications {
        background: var(--bg, rgba(0,0,0,0.05));
        border: 1px solid var(--border-color, rgba(0,0,0,0.08));
        border-radius: 50%;
        width: 44px;
        height: 44px;
        font-size: 1.4rem;
        cursor: pointer;
        color: var(--text, #1a2332);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        flex-shrink: 0;
        padding: 0;
        line-height: 1;
    }
    
    body.dark-mode .harvhub-header-top ~ .notification-panel .close-notifications,
    body.dark-mode .notification-panel .close-notifications {
        background: rgba(255,255,255,0.05);
        border-color: rgba(255,255,255,0.1);
        color: var(--text, #e6edf3);
    }
    
    .harvhub-header-top ~ .notification-panel .close-notifications:hover,
    .notification-panel .close-notifications:hover {
        background: var(--danger, #e74c3c);
        color: #fff;
        border-color: var(--danger, #e74c3c);
        transform: rotate(90deg);
    }
    
    .harvhub-header-top ~ .notification-panel .close-notifications:active,
    .notification-panel .close-notifications:active {
        transform: rotate(90deg) scale(0.95);
    }
    
    /* Panel List - Scrollable */
    .harvhub-header-top ~ .notification-panel .notification-list,
    .notification-panel .notification-list {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 16px 24px 24px 24px;
        margin: 0;
        max-height: calc(100vh - 100px);
        scrollbar-width: thin;
        scrollbar-color: var(--border-color) transparent;
    }
    
    .harvhub-header-top ~ .notification-panel .notification-list::-webkit-scrollbar,
    .notification-panel .notification-list::-webkit-scrollbar {
        width: 6px;
    }
    
    .harvhub-header-top ~ .notification-panel .notification-list::-webkit-scrollbar-track,
    .notification-panel .notification-list::-webkit-scrollbar-track {
        background: transparent;
        border-radius: 10px;
    }
    
    .harvhub-header-top ~ .notification-panel .notification-list::-webkit-scrollbar-thumb,
    .notification-panel .notification-list::-webkit-scrollbar-thumb {
        background: var(--border-color, rgba(0,0,0,0.15));
        border-radius: 10px;
        transition: background 0.2s ease;
    }
    
    .harvhub-header-top ~ .notification-panel .notification-list::-webkit-scrollbar-thumb:hover,
    .notification-panel .notification-list::-webkit-scrollbar-thumb:hover {
        background: var(--text-muted, #6e7681);
    }
    
    /* Notification Items */
    .harvhub-header-top ~ .notification-panel .notification-item,
    .notification-panel .notification-item {
        display: block !important;
        padding: 14px 16px !important;
        margin-bottom: 10px !important;
        border-radius: 10px !important;
        background: var(--bg, rgba(0,0,0,0.03)) !important;
        border: 1px solid var(--border-color, rgba(0,0,0,0.06)) !important;
        transition: all 0.3s ease !important;
        cursor: pointer !important;
        color: var(--text, #1a2332) !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    
    body.dark-mode .harvhub-header-top ~ .notification-panel .notification-item,
    body.dark-mode .notification-panel .notification-item {
        background: rgba(255,255,255,0.03) !important;
        border-color: rgba(255,255,255,0.06) !important;
        color: var(--text, #e6edf3) !important;
    }
    
    .harvhub-header-top ~ .notification-panel .notification-item:hover,
    .notification-panel .notification-item:hover {
        background: rgba(16, 185, 129, 0.06) !important;
        transform: translateX(4px) !important;
        border-color: var(--accent, #10b981) !important;
    }
    
    body.dark-mode .harvhub-header-top ~ .notification-panel .notification-item:hover,
    body.dark-mode .notification-panel .notification-item:hover {
        background: rgba(16, 185, 129, 0.08) !important;
    }
    
    .harvhub-header-top ~ .notification-panel .notification-item.unread,
    .notification-panel .notification-item.unread {
        background: rgba(59, 130, 246, 0.06) !important;
        border-left: 3px solid var(--info, #3498db) !important;
    }
    
    body.dark-mode .harvhub-header-top ~ .notification-panel .notification-item.unread,
    body.dark-mode .notification-panel .notification-item.unread {
        background: rgba(59, 130, 246, 0.08) !important;
    }
    
    /* Notification item - Section */
    .harvhub-header-top ~ .notification-panel .notification-item .notification-section,
    .notification-panel .notification-item .notification-section {
        font-size: 10px !important;
        opacity: 0.6 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        margin-bottom: 6px !important;
        font-weight: 600 !important;
        color: var(--text-secondary, #5a6c7d) !important;
        display: block !important;
    }
    
    body.dark-mode .harvhub-header-top ~ .notification-panel .notification-item .notification-section,
    body.dark-mode .notification-panel .notification-item .notification-section {
        color: var(--text-secondary, #8b949e) !important;
    }
    
    /* Notification item - Message */
    .harvhub-header-top ~ .notification-panel .notification-item .notification-message,
    .notification-panel .notification-item .notification-message {
        font-size: 14px !important;
        line-height: 1.5 !important;
        margin-bottom: 8px !important;
        color: var(--text, #1a2332) !important;
        display: block !important;
        word-wrap: break-word !important;
    }
    
    body.dark-mode .harvhub-header-top ~ .notification-panel .notification-item .notification-message,
    body.dark-mode .notification-panel .notification-item .notification-message {
        color: var(--text, #e6edf3) !important;
    }
    
    /* Notification item - Time */
    .harvhub-header-top ~ .notification-panel .notification-item .notification-time,
    .notification-panel .notification-item .notification-time {
        font-size: 11px !important;
        opacity: 0.5 !important;
        color: var(--text-secondary, #5a6c7d) !important;
        display: block !important;
    }
    
    body.dark-mode .harvhub-header-top ~ .notification-panel .notification-item .notification-time,
    body.dark-mode .notification-panel .notification-item .notification-time {
        color: var(--text-secondary, #8b949e) !important;
    }
    
    /* Empty state */
    .harvhub-header-top ~ .notification-panel .empty-notifications,
    .notification-panel .empty-notifications {
        text-align: center !important;
        padding: 60px 20px !important;
        opacity: 0.6 !important;
        font-size: 14px !important;
        color: var(--text, #1a2332) !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    
    body.dark-mode .harvhub-header-top ~ .notification-panel .empty-notifications,
    body.dark-mode .notification-panel .empty-notifications {
        color: var(--text, #e6edf3) !important;
    }
    
    /* ============================================================
    ANIMATION ON OPEN
    ============================================================ */
    .harvhub-header-top ~ .notification-panel.active .panel-content,
    .notification-panel.active .panel-content {
        animation: modalSlideIn 0.3s ease;
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: scale(0.98) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    
    /* ============================================================
    RESPONSIVE
    ============================================================ */
    @media (max-width: 820px) {
        .harvhub-header-top ~ .notification-panel .panel-content,
        .notification-panel .panel-content {
            max-width: 100%;
            border-radius: 0;
        }
    }
    
    @media (max-width: 768px) {
        .harvhub-header-top {
            height: 52px !important;
            padding: 0 14px !important;
        }
        
        .harvhub-header-top .header-logo {
            font-size: 17px !important;
        }
        
        .harvhub-header-top ~ .notification-panel .notification-header,
        .notification-panel .notification-header {
            padding: 16px 18px 12px 18px !important;
            min-height: 50px !important;
        }
        
        .harvhub-header-top ~ .notification-panel .notification-header h3,
        .notification-panel .notification-header h3 {
            font-size: 1.2rem !important;
        }
        
        .harvhub-header-top ~ .notification-panel .close-notifications,
        .notification-panel .close-notifications {
            width: 38px !important;
            height: 38px !important;
            font-size: 1.2rem !important;
        }
        
        .harvhub-header-top ~ .notification-panel .notification-list,
        .notification-panel .notification-list {
            padding: 12px 16px 16px 16px !important;
            max-height: calc(100vh - 80px) !important;
        }
        
        .harvhub-header-top ~ .notification-panel .notification-item,
        .notification-panel .notification-item {
            padding: 12px 14px !important;
            margin-bottom: 8px !important;
        }
        
        .harvhub-header-top ~ .notification-panel .notification-item .notification-message,
        .notification-panel .notification-item .notification-message {
            font-size: 13px !important;
        }
        
        .harvhub-header-top .notification-bell {
            width: 36px !important;
            height: 36px !important;
        }
        
        .harvhub-header-top .notification-bell i {
            font-size: 16px !important;
        }
    }
    
    @media (max-width: 480px) {
        .harvhub-header-top ~ .notification-panel .notification-header,
        .notification-panel .notification-header {
            padding: 12px 14px 10px 14px !important;
        }
        
        .harvhub-header-top ~ .notification-panel .notification-header h3,
        .notification-panel .notification-header h3 {
            font-size: 1rem !important;
        }
        
        .harvhub-header-top ~ .notification-panel .close-notifications,
        .notification-panel .close-notifications {
            width: 34px !important;
            height: 34px !important;
            font-size: 1rem !important;
        }
        
        .harvhub-header-top ~ .notification-panel .notification-list,
        .notification-panel .notification-list {
            padding: 10px 12px 12px 12px !important;
        }
        
        .harvhub-header-top ~ .notification-panel .notification-item,
        .notification-panel .notification-item {
            padding: 10px 12px !important;
        }
        
        .harvhub-header-top ~ .notification-panel .notification-item .notification-message,
        .notification-panel .notification-item .notification-message {
            font-size: 12px !important;
        }
        
        .harvhub-header-top ~ .notification-panel .empty-notifications,
        .notification-panel .empty-notifications {
            padding: 40px 16px !important;
            font-size: 1rem !important;
        }
    }
    /* Ensure panel content fills the modal properly */
    .harvhub-header-top ~ .notification-panel .panel-content,
    .notification-panel .panel-content {
        background: var(--modal-bg, #ffffff);
        color: var(--modal-text, #1a2332);
        border-radius: 0;
        padding: 0;
        max-width: 800px;
        width: 100%;
        height: 100%;
        max-height: 100vh;
        border: none;
        box-shadow: none;
        margin: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        position: relative;
    }

    /* Make sure the notification items take full width */
    .harvhub-header-top ~ .notification-panel .notification-item,
    .notification-panel .notification-item {
        display: block !important;
        padding: 14px 16px !important;
        margin-bottom: 10px !important;
        border-radius: 10px !important;
        background: var(--bg, rgba(0,0,0,0.03)) !important;
        border: 1px solid var(--border-color, rgba(0,0,0,0.06)) !important;
        transition: all 0.3s ease !important;
        cursor: pointer !important;
        color: var(--text, #1a2332) !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    /* Notification list should fill the remaining space */
    .harvhub-header-top ~ .notification-panel .notification-list,
    .notification-panel .notification-list {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 16px 24px 24px 24px;
        margin: 0;
        width: 100%;
        box-sizing: border-box;
    }

    /* Header should be at full width */
    .harvhub-header-top ~ .notification-panel .notification-header,
    .notification-panel .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px 16px 24px;
        border-bottom: 1px solid var(--border-color, rgba(0,0,0,0.08));
        flex-shrink: 0;
        background: var(--modal-bg, #ffffff);
        width: 100%;
        box-sizing: border-box;
        min-height: 70px;
    }
</style>

<style>
    /* ============================================================
    BODY PADDING FOR FIXED HEADER & BOTTOM NAV
    ============================================================ */
    body {
        padding-top: 60px; /* Space for fixed header */
        padding-bottom: 70px; /* Space for fixed bottom nav */
    }
    
    @media (max-width: 480px) {
        body {
            padding-top: 52px;
            padding-bottom: 80px;
        }
    }
    
    @media (max-width: 360px) {
        body {
            padding-bottom: 70px;
        }
    }

    /* ============================================================
    DASHBOARD TABS (Bottom Navigation) - Glass Effect
    ============================================================ */
    .bottom-nav {
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        width: 80%;
        max-width: 500px;
        background: rgba(30, 30, 40, 0.75);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        display: flex;
        justify-content: space-around;
        align-items: center;
        padding: 10px 0;
        z-index: 999;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        border-radius: 40px;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    .bottom-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        flex: 1;
        padding: 4px 0;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        color: rgba(255, 255, 255, 0.6);
        border: none;
        background: transparent;
        font-size: 10px;
        gap: 2px;
        border-radius: 12px;
        padding: 8px 4px;
        position: relative;
    }

    .bottom-nav-item .nav-icon {
        font-size: 22px;
        line-height: 1;
        filter: grayscale(0.3);
        transition: all 0.3s ease;
        color: rgba(255, 255, 255, 0.7);
    }

    .bottom-nav-item .nav-label {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: rgba(255, 255, 255, 0.5);
        transition: all 0.3s ease;
    }

    .bottom-nav-item.active {
        color: var(--accent, #10b981);
    }

    .bottom-nav-item.active .nav-icon {
        transform: scale(1.15);
        filter: grayscale(0);
        color: var(--accent, #10b981);
    }

    .bottom-nav-item.active .nav-label {
        color: var(--accent, #10b981);
        font-weight: 600;
    }

    .bottom-nav-item:hover {
        color: var(--accent, #10b981);
    }

    .bottom-nav-item:hover .nav-icon {
        transform: scale(1.08);
        color: var(--accent, #10b981);
    }

    .bottom-nav-item:hover .nav-label {
        color: rgba(255, 255, 255, 0.8);
    }

    /* ===== LIGHT MODE OVERRIDE ===== */
    body:not(.dark-mode) .bottom-nav {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    }

    body:not(.dark-mode) .bottom-nav-item {
        color: rgba(0, 0, 0, 0.5);
    }

    body:not(.dark-mode) .bottom-nav-item .nav-icon {
        color: rgba(0, 0, 0, 0.5);
        filter: grayscale(0.3);
    }

    body:not(.dark-mode) .bottom-nav-item .nav-label {
        color: rgba(0, 0, 0, 0.4);
    }

    body:not(.dark-mode) .bottom-nav-item.active .nav-icon {
        color: var(--accent, #10b981);
        filter: grayscale(0);
    }

    body:not(.dark-mode) .bottom-nav-item.active .nav-label {
        color: var(--accent, #10b981);
    }

    body:not(.dark-mode) .bottom-nav-item:hover .nav-icon {
        color: var(--accent, #10b981);
    }

    body:not(.dark-mode) .bottom-nav-item:hover .nav-label {
        color: rgba(0, 0, 0, 0.7);
    }

    /* ===== DARK MODE OVERRIDE ===== */
    body.dark-mode .bottom-nav {
        background: rgba(20, 20, 30, 0.85);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    }

    body.dark-mode .bottom-nav-item {
        color: rgba(255, 255, 255, 0.5);
    }

    body.dark-mode .bottom-nav-item .nav-icon {
        color: rgba(255, 255, 255, 0.6);
        filter: grayscale(0.3);
    }

    body.dark-mode .bottom-nav-item .nav-label {
        color: rgba(255, 255, 255, 0.4);
    }

    body.dark-mode .bottom-nav-item.active .nav-icon {
        color: var(--accent, #10b981);
        filter: grayscale(0);
    }

    body.dark-mode .bottom-nav-item.active .nav-label {
        color: var(--accent, #10b981);
    }

    body.dark-mode .bottom-nav-item:hover .nav-icon {
        color: var(--accent, #10b981);
    }

    body.dark-mode .bottom-nav-item:hover .nav-label {
        color: rgba(255, 255, 255, 0.7);
    }

    /* ============================================================
    RESPONSIVE
    ============================================================ */
    @media (max-width: 768px) {
        .bottom-nav {
            width: 85%;
            padding: 10px 0;
            bottom: 24px;
            border-radius: 32px;
        }
    }

    @media (max-width: 480px) {
        .bottom-nav {
            width: 92%;
            padding: 8px 0;
            bottom: 16px;
            border-radius: 24px;
        }
        
        .bottom-nav-item .nav-icon {
            font-size: 18px;
        }
        
        .bottom-nav-item .nav-label {
            font-size: 8px;
        }
        
        body {
            padding-bottom: 80px;
        }
    }

    @media (max-width: 360px) {
        .bottom-nav {
            width: 96%;
            padding: 6px 0;
            bottom: 10px;
            border-radius: 18px;
        }
        
        .bottom-nav-item {
            padding: 4px 2px;
        }
        
        .bottom-nav-item .nav-icon {
            font-size: 16px;
        }
        
        .bottom-nav-item .nav-label {
            font-size: 7px;
        }
        
        body {
            padding-bottom: 70px;
        }
    }
</style>

<style>
    /* ============================================================
    CONNECT BROKER STYLES
    All styles use CSS variables for consistency
    ============================================================ */
    
    .connect-broker-container {
        max-width: 480px;
        width: 100%;
        padding: var(--spacing-md, 20px);
        margin: 0 auto;
        box-sizing: border-box;
    }

    .connect-broker-card {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius, 16px);
        padding: var(--spacing-lg, 28px);
        box-shadow: var(--shadow-lg, 0 8px 32px rgba(0,0,0,0.12));
        max-height: 90vh;
        overflow-y: auto;
        transition: background var(--transition-speed, 0.3s), border-color var(--transition-speed, 0.3s);
    }
    
    /* Scrollbar styling */
    .connect-broker-card::-webkit-scrollbar {
        width: 4px;
    }
    .connect-broker-card::-webkit-scrollbar-track {
        background: var(--bg, #f5f5f5);
        border-radius: 4px;
    }
    .connect-broker-card::-webkit-scrollbar-thumb {
        background: var(--border-color, #ccc);
        border-radius: 4px;
    }

    /* Back to Dashboard - Top */
    .back-link-top {
        margin-bottom: var(--spacing-md, 20px);
    }
    
    .back-link-top a {
        display: inline-flex;
        align-items: center;
        gap: var(--spacing-xs, 8px);
        color: var(--accent, #2e8b57);
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 600;
        padding: var(--spacing-xs, 8px) var(--spacing-md, 16px);
        border-radius: var(--radius-sm, 8px);
        background: var(--bg, #f5f5f5);
        border: 1px solid var(--border-color, #e0e0e0);
        transition: all var(--transition-speed, 0.2s);
    }
    
    .back-link-top a:hover {
        background: var(--border-color, #e0e0e0);
        transform: translateX(-3px);
    }
    
    body.dark-mode .back-link-top a {
        background: var(--bg, #2a2a2a);
        border-color: var(--border-color, #444);
    }
    
    body.dark-mode .back-link-top a:hover {
        background: var(--border-color, #444);
    }

    /* Logo Area */
    .logo-area {
        text-align: center;
        margin-bottom: var(--spacing-md, 20px);
    }

    .logo-area h1 {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--accent, #2e8b57);
        margin: 0;
        letter-spacing: -0.5px;
    }

    .logo-area p {
        font-size: 0.9rem;
        color: var(--text-muted, #888);
        margin-top: var(--spacing-xs, 4px);
    }

    /* User Info */
    .user-info {
        background: var(--bg, #f5f5f5);
        border-radius: var(--radius-sm, 8px);
        padding: var(--spacing-sm, 12px) var(--spacing-md, 16px);
        margin-bottom: var(--spacing-md, 20px);
        border: 1px solid var(--border-color, #e0e0e0);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: var(--spacing-xs, 8px);
        transition: background var(--transition-speed, 0.3s);
    }

    .user-info .label {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: var(--text-muted, #888);
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .user-info .value {
        font-weight: 600;
        color: var(--text, #222);
        font-size: 0.95rem;
    }

    .user-info .status-badge {
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        padding: 3px 12px;
        border-radius: 20px;
    }

    .status-badge.approved {
        background: var(--success-bg, #d4edda);
        color: var(--success, #28a745);
    }

    .status-badge.pending {
        background: var(--warning-bg, #fff3cd);
        color: var(--warning, #ffc107);
    }

    .status-badge.declined {
        background: var(--danger-bg, #f8d7da);
        color: var(--danger, #dc3545);
    }

    /* Messages */
    .error-message {
        background: var(--danger-bg, #f8d7da);
        color: var(--danger, #dc3545);
        padding: var(--spacing-sm, 10px) var(--spacing-md, 14px);
        border-radius: var(--radius-sm, 8px);
        font-size: 0.85rem;
        margin-bottom: var(--spacing-md, 16px);
        border-left: 3px solid var(--danger, #dc3545);
    }

    .success-message {
        background: var(--success-bg, #d4edda);
        color: var(--success, #28a745);
        padding: var(--spacing-sm, 10px) var(--spacing-md, 14px);
        border-radius: var(--radius-sm, 8px);
        font-size: 0.85rem;
        margin-bottom: var(--spacing-md, 16px);
        border-left: 3px solid var(--success, #28a745);
    }

    /* Broker Selection Grid */
    .broker-selection-grid {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm, 10px);
        margin: var(--spacing-md, 16px) 0;
        transition: all 0.3s ease;
    }
    
    .broker-selection-grid.hidden {
        display: none !important;
    }

    .broker-selection-item {
        background: var(--bg, #f5f5f5);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        padding: var(--spacing-sm, 12px) var(--spacing-md, 16px);
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.2s ease;
    }

    .broker-selection-item:hover {
        border-color: var(--accent, #2e8b57);
        transform: translateY(-1px);
    }

    .broker-selection-item .broker-name {
        font-weight: 600;
        color: var(--text, #222);
        font-size: 0.95rem;
    }

    .broker-selection-item .broker-action {
        display: flex;
        gap: var(--spacing-xs, 8px);
        align-items: center;
    }

    /* Buttons */
    .btn-broker-link {
        padding: var(--spacing-xs, 6px) var(--spacing-md, 16px);
        background: var(--accent, #2e8b57);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm, 8px);
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-broker-link:hover {
        background: var(--accent-hover, #3cb371);
        transform: scale(1.02);
    }

    .btn-broker-link:active {
        transform: scale(0.98);
    }

    .btn-broker-link.no-link {
        background: var(--text-muted, #888);
        cursor: not-allowed;
        opacity: 0.6;
    }

    .btn-broker-link.no-link:hover {
        transform: none;
        background: var(--text-muted, #888);
    }

    .btn-secondary {
        padding: var(--spacing-sm, 10px) var(--spacing-lg, 24px);
        background: var(--bg, #f5f5f5);
        color: var(--text, #222);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-block;
    }

    .btn-secondary:hover {
        background: var(--border-color, #e0e0e0);
        transform: scale(1.02);
    }

    .btn-secondary:active {
        transform: scale(0.98);
    }

    .btn-connect-wrapper {
        text-align: center;
        margin: var(--spacing-md, 16px) 0;
    }
    
    .btn-connect-wrapper.hidden {
        display: none !important;
    }

    .btn-submit {
        width: 100%;
        padding: var(--spacing-md, 14px);
        background: var(--accent, #2e8b57);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm, 8px);
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-submit:hover {
        background: var(--accent-hover, #3cb371);
        transform: scale(1.01);
    }

    .btn-submit:active {
        transform: scale(0.98);
    }

    .btn-submit:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    /* Form */
    .broker-divider {
        border: none;
        border-top: 1px solid var(--border-color, #e0e0e0);
        margin: var(--spacing-md, 16px) 0;
    }

    .form-group {
        margin-bottom: var(--spacing-md, 16px);
    }

    .form-group label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-secondary, #555);
        margin-bottom: var(--spacing-xs, 5px);
    }

    /* ============================================================
    PREVENT ZOOM ON INPUT FOCUS (iOS Safari)
    Font size must be at least 16px to prevent auto-zoom
    ============================================================ */
    .form-group select,
    .form-group input,
    .form-group input[type="text"],
    .form-group input[type="password"],
    .form-group input[type="email"],
    .form-group input[type="number"],
    .form-group input[type="tel"],
    .form-group input[type="search"] {
        width: 100%;
        padding: var(--spacing-sm, 12px) var(--spacing-md, 14px);
        border-radius: var(--radius-sm, 8px);
        border: 1px solid var(--border-color, #e0e0e0);
        background: var(--bg, #f5f5f5);
        color: var(--text, #222);
        font-size: 16px !important; /* CRITICAL: Prevents iOS zoom */
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        -webkit-appearance: none;
        appearance: none;
        box-sizing: border-box;
        -webkit-text-size-adjust: 100%; /* Prevent font size adjustment */
    }

    .form-group select:focus,
    .form-group input:focus {
        outline: none;
        border-color: var(--accent, #2e8b57);
        box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
    }

    .form-group select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        padding-right: 36px;
    }

    .password-wrapper {
        position: relative;
    }

    .password-wrapper input {
        padding-right: 55px;
    }

    .password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        font-size: 0.75rem;
        color: var(--text-muted, #888);
        background: transparent;
        border: none;
        padding: var(--spacing-xs, 4px) var(--spacing-sm, 8px);
        border-radius: 4px;
        user-select: none;
        font-weight: 600;
        z-index: 5;
    }

    .password-toggle:hover {
        color: var(--accent, #2e8b57);
    }

    /* Connect Form - hidden by default */
    #connectForm {
        transition: all 0.3s ease;
    }

    #connectForm.hidden {
        display: none !important;
    }

    /* Info Note */
    .info-note {
        font-size: 0.75rem;
        color: var(--text-muted, #888);
        text-align: center;
        margin-top: var(--spacing-md, 16px);
        padding: var(--spacing-sm, 10px);
        background: var(--bg, #f5f5f5);
        border-radius: var(--radius-sm, 8px);
        border: 1px solid var(--border-color, #e0e0e0);
        line-height: 1.5;
    }

    .info-note strong {
        color: var(--text-secondary, #555);
    }

    .section-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--text-secondary, #555);
        margin: var(--spacing-md, 16px) 0 var(--spacing-sm, 12px) 0;
        text-align: center;
    }

    /* ============================================================
    RESPONSIVE
    ============================================================ */
    @media (max-width: 480px) {
        .connect-broker-container {
            padding: var(--spacing-sm, 12px);
        }

        .connect-broker-card {
            padding: var(--spacing-md, 20px) var(--spacing-md, 16px);
        }

        .logo-area h1 {
            font-size: 1.4rem;
        }

        .form-group select,
        .form-group input {
            padding: var(--spacing-sm, 10px) var(--spacing-sm, 12px);
            font-size: 16px !important; /* Keep 16px to prevent zoom */
        }

        .btn-submit {
            padding: var(--spacing-sm, 12px);
            font-size: 0.9rem;
        }

        .user-info {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--spacing-xs, 4px);
        }

        .broker-selection-item {
            flex-direction: column;
            align-items: stretch;
            gap: var(--spacing-sm, 10px);
        }

        .broker-selection-item .broker-action {
            justify-content: flex-start;
        }

        .btn-broker-link,
        .btn-secondary {
            width: 100%;
            text-align: center;
            padding: var(--spacing-xs, 8px) var(--spacing-sm, 12px);
        }
        
        .back-link-top a {
            font-size: 0.8rem;
            padding: var(--spacing-xs, 6px) var(--spacing-sm, 12px);
        }
    }

    @media (min-width: 481px) and (max-width: 768px) {
        .broker-selection-item {
            flex-direction: row;
            gap: var(--spacing-sm, 12px);
        }
        
        .broker-selection-item .broker-action {
            gap: var(--spacing-xs, 8px);
        }
    }
    
    /* Dark mode overrides */
    body.dark-mode .connect-broker-card {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
    }
    
    body.dark-mode .user-info {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }
    
    body.dark-mode .broker-selection-item {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }
    
    body.dark-mode .form-group select,
    body.dark-mode .form-group input {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }
    
    body.dark-mode .info-note {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }
    
    body.dark-mode .btn-secondary {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }
    
    body.dark-mode .btn-secondary:hover {
        background: var(--border-color, #444);
    }
</style>


<style>
    /* ============================================================
    GET VPS BUTTON
    ============================================================ */
    .btn-get-vps {
        background: linear-gradient(135deg, #f39c12, #e67e22);
        color: #fff;
    }

    .btn-get-vps:hover {
        background: linear-gradient(135deg, #e67e22, #d35400);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
    }

    .btn-account-action.btn-get-vps {
        background: linear-gradient(135deg, #f39c12, #e67e22);
        color: #fff;
    }

    .btn-account-action.btn-get-vps:hover {
        background: linear-gradient(135deg, #e67e22, #d35400);
    }
</style>

<style>
    /* ============================================================
    VPS PAGE STYLES - FULL PAGE DESIGN
    ============================================================ */

    .vps-page-wrapper {
        max-width: 720px;
        width: 100%;
        margin: 0 auto;
        padding: var(--spacing-lg, 28px) var(--spacing-md, 20px);
        min-height: 100vh;
        box-sizing: border-box;
    }

    /* Back link */
    .vps-back-link {
        margin-bottom: var(--spacing-md, 20px);
    }

    .vps-back-link a {
        display: inline-flex;
        align-items: center;
        gap: var(--spacing-xs, 8px);
        color: var(--accent, #2e8b57);
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 600;
        padding: var(--spacing-xs, 8px) var(--spacing-md, 16px);
        border-radius: var(--radius-sm, 8px);
        background: var(--bg, #f5f5f5);
        border: 1px solid var(--border-color, #e0e0e0);
        transition: all var(--transition-speed, 0.2s);
    }

    .vps-back-link a:hover {
        background: var(--border-color, #e0e0e0);
        transform: translateX(-3px);
    }

    body.dark-mode .vps-back-link a {
        background: var(--bg, #2a2a2a);
        border-color: var(--border-color, #444);
    }

    body.dark-mode .vps-back-link a:hover {
        background: var(--border-color, #444);
    }

    /* Page header */
    .vps-page-header {
        text-align: center;
        margin-bottom: var(--spacing-lg, 28px);
    }

    .vps-page-header h1 {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--accent, #2e8b57);
        margin: 0;
        letter-spacing: -0.5px;
    }

    .vps-page-header p {
        font-size: 0.9rem;
        color: var(--text-muted, #888);
        margin-top: var(--spacing-xs, 4px);
    }

    /* Tabs */
    .vps-tabs {
        display: flex;
        gap: var(--spacing-xs, 8px);
        border-bottom: 1px solid var(--border-color, #e0e0e0);
        margin-bottom: var(--spacing-md, 20px);
        padding-bottom: 0;
    }

    .vps-tab {
        flex: 1;
        background: transparent;
        border: none;
        padding: var(--spacing-sm, 12px) var(--spacing-md, 16px);
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-muted, #888);
        cursor: pointer;
        border-bottom: 2px solid transparent;
        transition: all var(--transition-speed, 0.2s);
        font-family: inherit;
        margin-bottom: -1px;
    }

    .vps-tab:hover {
        color: var(--text, #222);
    }

    .vps-tab.active {
        color: var(--accent, #2e8b57);
        border-bottom-color: var(--accent, #2e8b57);
    }

    /* Tab content */
    .vps-tab-content {
        display: none;
    }

    .vps-tab-content.active {
        display: block;
    }

    /* Empty state */
    .vps-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: var(--spacing-xl, 60px) var(--spacing-md, 20px);
        text-align: center;
        color: var(--text-muted, #888);
    }

    .vps-empty-icon {
        font-size: 2rem;
        margin-bottom: var(--spacing-sm, 12px);
        opacity: 0.4;
        color: var(--text-muted, #888);
    }

    .vps-empty-state p {
        font-size: 0.95rem;
        margin: 0;
    }

    /* Hosts list */
    .vps-hosts-list {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm, 12px);
    }

    .vps-host-item {
        background: var(--bg, #f5f5f5);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        padding: var(--spacing-md, 16px);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm, 12px);
        transition: all 0.2s ease;
    }

    .vps-host-item:hover {
        border-color: var(--accent, #2e8b57);
        transform: translateY(-1px);
    }

    .vps-host-name {
        font-weight: 700;
        color: var(--text, #222);
        font-size: 1rem;
        cursor: pointer;
        transition: color 0.2s ease;
        display: inline-block;
        align-self: flex-start;
    }

    .vps-host-name:hover {
        color: var(--accent, #2e8b57);
        text-decoration: underline;
    }

    .vps-host-meta {
        display: flex;
        gap: var(--spacing-lg, 24px);
        flex-wrap: wrap;
    }

    .vps-host-stat {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .vps-host-stat-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted, #888);
        font-weight: 600;
    }

    .vps-host-stat-value {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text, #222);
    }

    .vps-host-actions {
        display: flex;
        justify-content: flex-end;
    }

    /* Buttons */
    .vps-btn-request {
        padding: var(--spacing-xs, 8px) var(--spacing-md, 20px);
        background: var(--accent, #2e8b57);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm, 8px);
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        font-family: inherit;
    }

    .vps-btn-request:hover {
        background: var(--accent-hover, #3cb371);
        transform: scale(1.02);
    }

    .vps-btn-request:active {
        transform: scale(0.98);
    }

    .vps-btn-request.disabled {
        background: var(--text-muted, #888);
        cursor: not-allowed;
        opacity: 0.6;
    }

    .vps-btn-request.disabled:hover {
        transform: none;
        background: var(--text-muted, #888);
    }

    .vps-btn-confirm {
        padding: var(--spacing-sm, 10px) var(--spacing-md, 20px);
        background: var(--accent, #2e8b57);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm, 8px);
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        font-family: inherit;
        width: 100%;
    }

    .vps-btn-confirm:hover {
        background: var(--accent-hover, #3cb371);
    }

    .vps-btn-confirm:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Modals (only for host details) */
    .vps-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: var(--spacing-md, 20px);
        box-sizing: border-box;
    }

    .vps-modal.active {
        display: flex;
    }

    .vps-modal-content {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius, 16px);
        padding: var(--spacing-lg, 28px);
        max-width: 420px;
        width: 100%;
        box-shadow: var(--shadow-lg, 0 8px 32px rgba(0,0,0,0.2));
        max-height: 85vh;
        overflow-y: auto;
    }

    .vps-modal-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--accent, #2e8b57);
        margin: 0 0 var(--spacing-md, 20px) 0;
        text-align: center;
    }

    .vps-modal-body {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm, 12px);
        margin-bottom: var(--spacing-md, 20px);
    }

    .vps-modal-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--spacing-md, 16px);
        padding: var(--spacing-sm, 10px) 0;
        border-bottom: 1px solid var(--border-color, #e0e0e0);
    }

    .vps-modal-row:last-child {
        border-bottom: none;
    }

    .vps-modal-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted, #888);
        font-weight: 600;
    }

    .vps-modal-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text, #222);
        text-align: right;
        word-break: break-word;
    }

    .vps-modal-loading,
    .vps-modal-error {
        text-align: center;
        padding: var(--spacing-md, 20px) 0;
        color: var(--text-muted, #888);
        font-size: 0.9rem;
    }

    .vps-modal-error {
        color: var(--danger, #dc3545);
    }

    .vps-modal-actions {
        display: flex;
        gap: var(--spacing-sm, 12px);
        flex-direction: column;
    }

    .vps-modal-close {
        padding: var(--spacing-sm, 10px) var(--spacing-md, 20px);
        background: var(--bg, #f5f5f5);
        color: var(--text, #222);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        font-family: inherit;
        width: 100%;
    }

    .vps-modal-close:hover {
        background: var(--border-color, #e0e0e0);
    }

    .vps-modal-close.requested {
        background: var(--success-bg, #d4edda);
        color: var(--success, #28a745);
        border-color: var(--success, #28a745);
        cursor: default;
    }

    .vps-modal-close.requested:hover {
        background: var(--success-bg, #d4edda);
    }

    body.dark-mode .vps-modal-close {
        background: var(--bg, #2a2a2a);
        border-color: var(--border-color, #444);
        color: var(--text, #eee);
    }

    body.dark-mode .vps-modal-close:hover {
        background: var(--border-color, #444);
    }

    body.dark-mode .vps-modal-close.requested {
        background: rgba(40, 167, 69, 0.2);
        color: var(--success, #28a745);
        border-color: var(--success, #28a745);
    }

    /* Dark mode overrides */
    body.dark-mode .vps-host-item {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .vps-modal-content {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .vps-modal-row {
        border-color: var(--border-color, #333);
    }

    /* Responsive */
    @media (max-width: 480px) {
        .vps-page-wrapper {
            padding: var(--spacing-md, 20px) var(--spacing-sm, 12px);
        }

        .vps-page-header h1 {
            font-size: 1.4rem;
        }

        .vps-tab {
            font-size: 0.8rem;
            padding: var(--spacing-sm, 10px) var(--spacing-xs, 8px);
        }

        .vps-host-meta {
            gap: var(--spacing-md, 16px);
        }

        .vps-modal-row {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--spacing-xs, 4px);
        }

        .vps-modal-value {
            text-align: left;
        }
    }

    /* ============================================================
    VPS REQUESTS — Sent & Incoming
    ============================================================ */

    .vps-requests-list {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm, 12px);
    }

    .vps-requests-loading {
        text-align: center;
        padding: var(--spacing-lg, 40px) var(--spacing-md, 20px);
        color: var(--text-muted, #888);
        font-size: 0.9rem;
    }

    /* Request card — mirrors .vps-host-item vocabulary */
    .vps-request-item {
        background: var(--bg, #f5f5f5);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        padding: var(--spacing-md, 16px);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xs, 8px);
        transition: all 0.2s ease;
    }

    .vps-request-item:hover {
        border-color: var(--accent, #2e8b57);
        transform: translateY(-1px);
    }

    .vps-request-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--spacing-sm, 12px);
        flex-wrap: wrap;
    }

    .vps-request-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted, #888);
        font-weight: 600;
    }

    .vps-request-value {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text, #222);
        text-align: right;
        word-break: break-word;
    }

    /* Status badges — same family as .vps-badge in vps_style */
    .vps-status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        white-space: nowrap;
    }

    .vps-status-badge.vps-status-pending {
        background: rgba(243, 156, 18, 0.15);
        color: #f39c12;
    }

    .vps-status-badge.vps-status-accept {
        background: rgba(39, 174, 96, 0.15);
        color: #27ae60;
    }

    .vps-status-badge.vps-status-reject {
        background: rgba(231, 76, 60, 0.15);
        color: #e74c3c;
    }

    /* Select element for accept / reject (incoming tab) */
    .vps-request-select {
        padding: 8px 12px;
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        background: var(--bg-card, #fff);
        color: var(--text, #222);
        font-size: 0.85rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        min-width: 120px;
        transition: border-color 0.2s ease;
    }

    .vps-request-select:focus {
        outline: none;
        border-color: var(--accent, #2e8b57);
    }

    .vps-request-select:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Delete button (sent tab) */
    .vps-btn-delete {
        padding: 8px 16px;
        background: rgba(231, 76, 60, 0.15);
        color: #e74c3c;
        border: 1px solid #e74c3c;
        border-radius: var(--radius-sm, 8px);
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .vps-btn-delete:hover {
        background: #e74c3c;
        color: #fff;
    }

    .vps-btn-delete:active {
        transform: scale(0.97);
    }

    .vps-btn-delete:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Dark mode */
    body.dark-mode .vps-request-item {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .vps-request-select {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }

    /* Responsive */
    @media (max-width: 480px) {
        .vps-request-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }

        .vps-request-value {
            text-align: left;
        }

        .vps-request-select,
        .vps-btn-delete {
            width: 100%;
            text-align: center;
        }
    }

    /* ============================================================
    REMOVE FOLLOWER CONFIRMATION MODAL
    ============================================================ */
    #vpsConfirmModal .vps-modal-title {
        color: #e74c3c;
    }

    #vpsConfirmModal .vps-btn-confirm {
        background: #e74c3c;
    }

    #vpsConfirmModal .vps-btn-confirm:hover {
        background: #c0392b;
    }

    #vpsConfirmModal .vps-btn-confirm:disabled {
        background: #e74c3c;
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* ============================================================
    VPS FOLLOWER LOCK NOTICE
    ============================================================ */
    .vps-follower-lock {
        border-left: 4px solid #f39c12 !important;
        background: rgba(243, 156, 18, 0.1) !important;
    }

    .vps-follower-lock strong {
        display: block;
        margin-bottom: 4px;
        color: #f39c12;
        font-size: 13px;
    }

    /* Readonly state on Request Space button */
    .vps-btn-request.disabled[title="You are already a follower of a VPS"] {
        background: var(--text-muted, #888);
        cursor: not-allowed;
        opacity: 0.55;
    }
    /* ============================================================
    VPS OWNER CARD (Sent Requests — follower mode)
    ============================================================ */
    .vps-owner-card {
        background: linear-gradient(135deg, rgba(46, 139, 87, 0.08), rgba(46, 139, 87, 0.02));
        border: 1px solid var(--accent, #2e8b57);
        border-radius: var(--radius-sm, 8px);
        padding: 18px 20px;
        margin-bottom: 20px;
        position: relative;
    }

    .vps-owner-badge {
        display: inline-block;
        background: var(--accent, #2e8b57);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        padding: 3px 10px;
        border-radius: 20px;
        margin-bottom: 10px;
    }

    .vps-owner-name {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text, #222);
        margin-bottom: 12px;
    }

    .vps-owner-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px 28px;
    }

    .vps-owner-stat {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .vps-owner-stat-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted, #888);
        font-weight: 600;
    }

    .vps-owner-stat-value {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text, #222);
    }

    /* Followers section */
    .vps-followers-section {
        margin-top: 8px;
    }

    .vps-followers-title {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--text-muted, #888);
        font-weight: 700;
        margin-bottom: 10px;
    }

    .vps-request-item.vps-follower-self {
        border-color: var(--accent, #2e8b57);
        background: rgba(46, 139, 87, 0.05);
    }

    .vps-self-badge {
        display: inline-block;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 2px 8px;
        background: var(--accent, #2e8b57);
        color: #fff;
        border-radius: 20px;
        margin-left: 6px;
    }
</style>

<style>
    /* ============================================================
       DEVELOPER DASHBOARD — dd-*
       Uses root CSS vars from style.php / dev_style.php
       ============================================================ */

    .dd-page-wrapper {
        max-width: 720px;
        width: 100%;
        margin: 0 auto;
        padding: var(--spacing-lg, 28px) var(--spacing-md, 20px);
        min-height: 100vh;
        box-sizing: border-box;
    }

    @media (min-width: 769px) {
        body.sidebar-expanded-desktop .dd-page-wrapper {
            margin-left: 240px;
            transition: margin-left 0.3s ease;
        }
    }

    /* Header */
    .dd-page-header {
        text-align: center;
        margin-bottom: var(--spacing-lg, 28px);
    }

    .dd-page-header h1 {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--accent, #2e8b57);
        margin: 0;
        letter-spacing: -0.5px;
    }

    .dd-page-header p {
        font-size: 0.9rem;
        color: var(--text-muted, #888);
        margin-top: var(--spacing-xs, 4px);
    }

    /* Notices */
    .dd-notice {
        padding: 12px 16px;
        border-radius: var(--radius-sm, 8px);
        font-size: 0.9rem;
        line-height: 1.5;
        margin-bottom: var(--spacing-md, 20px);
    }
    .dd-notice a { color: inherit; font-weight: 700; }
    .dd-notice-info {
        background: rgba(46, 139, 87, 0.08);
        border-left: 4px solid var(--accent, #2e8b57);
        color: var(--text, #222);
    }
    .dd-notice-warning {
        background: rgba(243, 156, 18, 0.1);
        border-left: 4px solid #f39c12;
        color: var(--text, #222);
    }
    .dd-notice-warning strong { color: #f39c12; }

    /* Card */
    .dd-card {
        background: var(--bg, #f5f5f5);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        padding: var(--spacing-md, 20px);
        margin-bottom: var(--spacing-md, 20px);
    }

    .dd-card-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: var(--spacing-md, 16px);
        flex-wrap: wrap;
        margin-bottom: var(--spacing-sm, 12px);
    }

    .dd-card-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--text, #222);
        margin: 0 0 4px 0;
    }

    .dd-card-subtitle {
        font-size: 0.85rem;
        color: var(--text-muted, #888);
        margin: 0 0 var(--spacing-sm, 12px) 0;
    }

    /* Form */
    .dd-form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-bottom: var(--spacing-sm, 12px);
    }

    .dd-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted, #888);
        font-weight: 600;
    }

    .dd-input,
    .dd-select {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        background: var(--bg-card, #fff);
        color: var(--text, #222);
        font-size: 0.9rem;
        font-family: inherit;
        transition: border-color 0.2s ease;
    }

    .dd-input:focus,
    .dd-select:focus {
        outline: none;
        border-color: var(--accent, #2e8b57);
    }

    /* Search */
    .dd-search-wrap {
        position: relative;
        margin-bottom: var(--spacing-sm, 12px);
    }

    .dd-suggestions {
        display: none;
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        max-height: 260px;
        overflow-y: auto;
        z-index: 20;
    }
    .dd-suggestions.active { display: block; }

    .dd-suggestion-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 14px;
        cursor: pointer;
        font-size: 0.9rem;
        color: var(--text, #222);
        transition: background 0.15s ease;
    }
    .dd-suggestion-item:hover { background: rgba(46, 139, 87, 0.08); }
    .dd-suggestion-item.is-selected { opacity: 0.7; }
    .dd-suggestion-tag {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        background: var(--accent, #2e8b57);
        color: #fff;
        padding: 2px 8px;
        border-radius: 20px;
    }
    .dd-suggestion-empty {
        padding: 12px 14px;
        font-size: 0.85rem;
        color: var(--text-muted, #888);
        text-align: center;
    }

    /* Selected header */
    .dd-selected-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted, #888);
        font-weight: 700;
        margin: var(--spacing-sm, 12px) 0 8px 0;
    }

    .dd-count-badge {
        background: var(--accent, #2e8b57);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 9px;
        border-radius: 20px;
        min-width: 22px;
        text-align: center;
    }

    /* Chips */
    .dd-selected-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        min-height: 44px;
        margin-bottom: var(--spacing-md, 16px);
    }

    .dd-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(46, 139, 87, 0.1);
        border: 1px solid var(--accent, #2e8b57);
        color: var(--accent, #2e8b57);
        padding: 6px 10px 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .dd-chip-remove {
        background: transparent;
        border: none;
        color: inherit;
        font-size: 1.1rem;
        line-height: 1;
        cursor: pointer;
        padding: 0 2px;
        font-family: inherit;
        opacity: 0.7;
        transition: opacity 0.15s ease;
    }
    .dd-chip-remove:hover { opacity: 1; }

    /* Empty state */
    .dd-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: var(--spacing-lg, 40px) var(--spacing-md, 20px);
        text-align: center;
        color: var(--text-muted, #888);
    }
    .dd-empty-small { padding: var(--spacing-sm, 14px) 0; }
    .dd-empty-icon {
        font-size: 1.6rem;
        margin-bottom: 6px;
        opacity: 0.4;
    }
    .dd-empty p { font-size: 0.9rem; margin: 0; }

    /* Buttons */
    .dd-btn-primary,
    .dd-btn-secondary,
    .dd-btn-ghost,
    .dd-btn-switch {
        font-family: inherit;
        font-weight: 600;
        cursor: pointer;
        border-radius: var(--radius-sm, 8px);
        transition: all 0.2s ease;
        font-size: 0.85rem;
        white-space: nowrap;
    }

    .dd-btn-primary {
        padding: 12px 20px;
        background: var(--accent, #2e8b57);
        color: #fff;
        border: none;
        width: 100%;
    }
    .dd-btn-primary:hover { background: var(--accent-hover, #3cb371); }
    .dd-btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

    .dd-btn-secondary {
        padding: 10px 18px;
        background: transparent;
        color: var(--accent, #2e8b57);
        border: 1px solid var(--accent, #2e8b57);
    }
    .dd-btn-secondary:hover {
        background: rgba(46, 139, 87, 0.08);
    }

    .dd-btn-ghost {
        padding: 12px 20px;
        background: var(--bg-card, #fff);
        color: var(--text, #222);
        border: 1px solid var(--border-color, #e0e0e0);
        width: 100%;
    }
    .dd-btn-ghost:hover { background: var(--border-color, #e0e0e0); }

    .dd-btn-switch {
        padding: 6px 16px;
        background: var(--accent, #2e8b57);
        color: #fff;
        border: none;
        font-size: 0.8rem;
    }
    .dd-btn-switch:hover { background: var(--accent-hover, #3cb371); }

    /* Programmes */
    .dd-programmes-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: var(--spacing-sm, 12px);
    }

    .dd-programme-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--spacing-sm, 12px);
        padding: 12px 16px;
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        transition: border-color 0.2s ease;
    }
    .dd-programme-item:hover { border-color: var(--accent, #2e8b57); }

    .dd-programme-name {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text, #222);
        word-break: break-word;
    }

    /* Modal */
    .dd-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: var(--spacing-md, 20px);
        box-sizing: border-box;
    }
    .dd-modal.active { display: flex; }

    .dd-modal-content {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius, 16px);
        padding: var(--spacing-lg, 28px);
        max-width: 420px;
        width: 100%;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        max-height: 85vh;
        overflow-y: auto;
    }

    .dd-modal-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--accent, #2e8b57);
        margin: 0 0 var(--spacing-md, 20px) 0;
        text-align: center;
    }

    .dd-modal-body {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm, 12px);
        margin-bottom: var(--spacing-md, 20px);
    }

    .dd-modal-text {
        margin: 0;
        font-size: 0.9rem;
        color: var(--text-muted, #888);
        line-height: 1.5;
        text-align: center;
    }

    .dd-modal-error {
        background: rgba(231, 76, 60, 0.1);
        border-left: 3px solid #e74c3c;
        color: #e74c3c;
        font-size: 0.85rem;
        padding: 10px 12px;
        border-radius: var(--radius-sm, 8px);
    }

    .dd-modal-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    /* Dark mode overrides */
    body.dark-mode .dd-card,
    body.dark-mode .dd-programme-item {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .dd-input,
    body.dark-mode .dd-select,
    body.dark-mode .dd-suggestions,
    body.dark-mode .dd-modal-content {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }

    body.dark-mode .dd-btn-ghost {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }

    /* Responsive */
    @media (max-width: 480px) {
        .dd-page-wrapper {
            padding: var(--spacing-md, 20px) var(--spacing-sm, 12px);
        }
        .dd-page-header h1 { font-size: 1.4rem; }
        .dd-card-head { flex-direction: column; align-items: stretch; }
        .dd-btn-secondary { width: 100%; text-align: center; }
        .dd-programme-item {
            flex-direction: column;
            align-items: flex-start;
        }
        .dd-btn-switch { width: 100%; text-align: center; }
    }

    /* ============================================================
       SUB-TABS
       ============================================================ */
    .dd-subtabs {
        display: flex;
        gap: 6px;
        background: var(--bg, #f5f5f5);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        padding: 6px;
        margin-bottom: var(--spacing-md, 20px);
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .dd-subtab {
        flex: 1;
        min-width: 90px;
        background: transparent;
        border: none;
        padding: 10px 14px;
        border-radius: var(--radius-sm, 8px);
        font-family: inherit;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-muted, #888);
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s ease;
    }

    .dd-subtab:hover {
        color: var(--text, #222);
        background: rgba(46, 139, 87, 0.06);
    }

    .dd-subtab.active {
        background: var(--accent, #2e8b57);
        color: #fff;
        box-shadow: 0 2px 8px rgba(46, 139, 87, 0.25);
    }

    .dd-subtab-content {
        display: none;
    }

    .dd-subtab-content.active {
        display: block;
    }

    /* ============================================================
       VISIBILITY LIST
       ============================================================ */
    .dd-visibility-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: var(--spacing-md, 16px);
    }

    .dd-visibility-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--spacing-sm, 12px);
        padding: 12px 16px;
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        transition: border-color 0.2s ease;
    }

    .dd-visibility-item:hover {
        border-color: var(--accent, #2e8b57);
    }

    .dd-visibility-name {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text, #222);
        word-break: break-word;
        flex: 1;
    }

    .dd-visibility-select {
        width: auto;
        min-width: 120px;
        flex-shrink: 0;
    }

    /* Dark mode overrides for new pieces */
    body.dark-mode .dd-subtabs {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .dd-subtab.active {
        background: var(--accent, #2e8b57);
    }

    body.dark-mode .dd-subtab:hover {
        background: rgba(46, 139, 87, 0.15);
    }

    body.dark-mode .dd-visibility-item {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }

    /* Responsive */
    @media (max-width: 480px) {
        .dd-subtab {
            font-size: 0.78rem;
            padding: 8px 10px;
            min-width: 80px;
        }

        .dd-visibility-item {
            flex-direction: column;
            align-items: flex-start;
        }

        .dd-visibility-select {
            width: 100%;
        }
    }

    /* ============================================================
       SET REQUIREMENTS (accordion)
       ============================================================ */
    .dd-req-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: var(--spacing-sm, 12px);
    }

    .dd-req-item {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        overflow: hidden;
        transition: border-color 0.2s ease;
    }

    .dd-req-item:hover {
        border-color: var(--accent, #2e8b57);
    }

    .dd-req-item.open {
        border-color: var(--accent, #2e8b57);
        box-shadow: 0 2px 10px rgba(46, 139, 87, 0.08);
    }

    .dd-req-toggle {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        padding: 14px 16px;
        background: transparent;
        border: none;
        font-family: inherit;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text, #222);
        cursor: pointer;
        text-align: left;
        gap: var(--spacing-sm, 12px);
    }

    .dd-req-toggle-name {
        word-break: break-word;
        flex: 1;
    }

    .dd-req-toggle-icon {
        font-size: 0.9rem;
        color: var(--accent, #2e8b57);
        transition: transform 0.2s ease;
    }

    .dd-req-body {
        padding: 0 16px 16px 16px;
        border-top: 1px solid var(--border-color, #e0e0e0);
    }

    .dd-req-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-top: var(--spacing-sm, 12px);
        margin-bottom: var(--spacing-sm, 12px);
    }

    .dd-req-grid .dd-form-group {
        margin-bottom: 0;
    }

    body.dark-mode .dd-req-item {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .dd-req-toggle {
        color: var(--text, #eee);
    }

    body.dark-mode .dd-req-body {
        border-color: var(--border-color, #333);
    }

    @media (max-width: 480px) {
        .dd-req-grid {
            grid-template-columns: 1fr;
        }

        .dd-req-toggle {
            padding: 12px 14px;
            font-size: 0.9rem;
        }

        .dd-req-body {
            padding: 0 14px 14px 14px;
        }
    }

    .dd-field-hint {
        font-size: 0.72rem;
        color: var(--text-muted, #888);
        margin-top: 4px;
    }

    .dd-input-warn {
        border-color: #e74c3c !important;
        box-shadow: 0 0 0 2px rgba(231, 76, 60, 0.15);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .dd-req-note {
        font-size: 0.82rem;
        color: var(--text, #222);
        background: rgba(46, 139, 87, 0.08);
        border-left: 4px solid var(--accent, #2e8b57);
        border-radius: var(--radius-sm, 8px);
        padding: 10px 14px;
        margin-bottom: var(--spacing-sm, 12px);
        line-height: 1.5;
    }

    body.dark-mode .dd-req-note {
        color: var(--text, #eee);
    }

    /* ============================================================
       PUBLISHED PROGRAMMES
       ============================================================ */
    .dd-pub-list {
        display: flex;
        flex-direction: column;
        gap: 14px;
        margin-top: var(--spacing-sm, 12px);
    }

    .dd-pub-programme {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        overflow: hidden;
    }

    .dd-pub-programme-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--spacing-sm, 12px);
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color, #e0e0e0);
        background: rgba(46, 139, 87, 0.04);
    }

    .dd-pub-programme-name {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text, #222);
        word-break: break-word;
    }

    .dd-pub-tag-public,
    .dd-pub-tag-private {
        display: inline-block;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 2px 8px;
        border-radius: 20px;
        margin-left: 6px;
        vertical-align: middle;
    }

    .dd-pub-tag-public {
        background: rgba(46, 139, 87, 0.12);
        color: var(--accent, #2e8b57);
        border: 1px solid var(--accent, #2e8b57);
    }

    .dd-pub-tag-private {
        background: rgba(136, 136, 136, 0.12);
        color: var(--text-muted, #888);
        border: 1px solid var(--border-color, #e0e0e0);
    }

    .dd-pub-count {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: var(--text-muted, #888);
        white-space: nowrap;
    }

    .dd-pub-empty-investors {
        padding: 18px 16px;
        font-size: 0.85rem;
        color: var(--text-muted, #888);
        text-align: center;
        font-style: italic;
    }

    .dd-pub-investors {
        display: flex;
        flex-direction: column;
    }

    .dd-pub-investor {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--spacing-sm, 12px);
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color, #e0e0e0);
        flex-wrap: wrap;
    }

    .dd-pub-investor:last-child {
        border-bottom: none;
    }

    .dd-pub-investor-name {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text, #222);
        word-break: break-word;
        flex: 1 1 auto;
        min-width: 120px;
    }

    .dd-pub-investor-stats {
        display: flex;
        gap: 18px;
        flex-wrap: wrap;
    }

    .dd-pub-stat {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 90px;
    }

    .dd-pub-stat-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted, #888);
        font-weight: 600;
    }

    .dd-pub-stat-value {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--text, #222);
    }

    .dd-pnl-pos {
        color: #27ae60;
    }

    .dd-pnl-neg {
        color: #e74c3c;
    }

    /* Dark mode overrides */
    body.dark-mode .dd-pub-programme {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .dd-pub-programme-head {
        border-color: var(--border-color, #333);
        background: rgba(46, 139, 87, 0.08);
    }

    body.dark-mode .dd-pub-programme-name,
    body.dark-mode .dd-pub-investor-name,
    body.dark-mode .dd-pub-stat-value {
        color: var(--text, #eee);
    }

    body.dark-mode .dd-pub-investor {
        border-color: var(--border-color, #333);
    }

    /* Responsive */
    @media (max-width: 480px) {
        .dd-pub-investor {
            flex-direction: column;
            align-items: flex-start;
        }

        .dd-pub-investor-stats {
            width: 100%;
            justify-content: space-between;
            gap: 10px;
        }

        .dd-pub-stat {
            min-width: 0;
        }
    }
</style>
<style>
    /* ============================================================
       PROGRAMMES PAGE STYLES - FULL PAGE DESIGN
       Matches the VPS page / HarvHub design system
       ============================================================ */

    /* ===== PAGE WRAPPER ===== */
    .prog-page-wrapper {
        max-width: 720px;
        width: 100%;
        margin: 0 auto;
        padding: var(--spacing-lg, 28px) var(--spacing-md, 20px);
        min-height: 100vh;
        box-sizing: border-box;
    }

    /* ===== BACK LINK ===== */
    .prog-back-link {
        margin-bottom: var(--spacing-md, 20px);
    }

    .prog-back-link a {
        display: inline-flex;
        align-items: center;
        gap: var(--spacing-xs, 8px);
        color: var(--accent, #2e8b57);
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 600;
        padding: var(--spacing-xs, 8px) var(--spacing-md, 16px);
        border-radius: var(--radius-sm, 8px);
        background: var(--bg, #f5f5f5);
        border: 1px solid var(--border-color, #e0e0e0);
        transition: all var(--transition-speed, 0.2s);
    }

    .prog-back-link a:hover {
        background: var(--border-color, #e0e0e0);
        transform: translateX(-3px);
    }

    body.dark-mode .prog-back-link a {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .prog-back-link a:hover {
        background: var(--border-color, #444);
    }

    /* ===== PAGE HEADER ===== */
    .prog-page-header {
        text-align: center;
        margin-bottom: var(--spacing-lg, 28px);
    }

    .prog-page-header h1 {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--accent, #2e8b57);
        margin: 0;
        letter-spacing: -0.5px;
    }

    .prog-page-header p {
        font-size: 0.9rem;
        color: var(--text-muted, #888);
        margin-top: var(--spacing-xs, 4px);
    }

    /* ===== SEARCH ===== */
    .prog-search-wrap {
        position: relative;
        margin-bottom: var(--spacing-md, 20px);
    }

    .prog-search-input {
        width: 100%;
        box-sizing: border-box;
        padding: 12px 16px;
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        background: var(--bg-card, #fff);
        color: var(--text, #222);
        font-size: 16px !important; /* prevents iOS zoom */
        font-family: inherit;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        -webkit-appearance: none;
        appearance: none;
    }

    .prog-search-input:focus {
        outline: none;
        border-color: var(--accent, #2e8b57);
        box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
    }

    .prog-search-input::placeholder {
        color: var(--text-muted, #888);
    }

    body.dark-mode .prog-search-input {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }

    /* ===== EMPTY STATE ===== */
    .prog-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: var(--spacing-xl, 60px) var(--spacing-md, 20px);
        text-align: center;
        color: var(--text-muted, #888);
    }

    .prog-empty-icon {
        font-size: 2rem;
        margin-bottom: var(--spacing-sm, 12px);
        opacity: 0.4;
        color: var(--text-muted, #888);
    }

    .prog-empty-state p {
        font-size: 0.95rem;
        margin: 0;
    }

    /* ===== PROGRAMMES LIST ===== */
    .prog-list {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm, 12px);
    }

    .prog-item {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        padding: var(--spacing-md, 16px);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm, 12px);
        transition: all 0.2s ease;
    }

    .prog-item:hover {
        border-color: var(--accent, #2e8b57);
        transform: translateY(-1px);
    }

    .prog-item-main {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .prog-item-name {
        font-weight: 700;
        color: var(--text, #222);
        font-size: 1rem;
        word-break: break-word;
    }

    .prog-item-meta {
        display: flex;
        gap: var(--spacing-lg, 24px);
        flex-wrap: wrap;
    }

    .prog-item-stat {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .prog-item-stat-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted, #888);
        font-weight: 600;
    }

    .prog-item-stat-value {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text, #222);
    }

    .prog-item-actions {
        display: flex;
        justify-content: flex-end;
        gap: var(--spacing-xs, 8px);
        flex-wrap: wrap;
    }

    /* ===== BUTTONS ===== */
    .prog-btn-view,
    .prog-btn-invest,
    .prog-btn-cancel,
    .prog-btn-confirm,
    .prog-modal-close {
        font-family: inherit;
        font-weight: 600;
        cursor: pointer;
        border-radius: var(--radius-sm, 8px);
        transition: all 0.2s ease;
        font-size: 0.85rem;
        white-space: nowrap;
    }

    .prog-btn-view {
        padding: 8px 20px;
        background: transparent;
        color: var(--accent, #2e8b57);
        border: 1px solid var(--accent, #2e8b57);
    }

    .prog-btn-view:hover {
        background: rgba(46, 139, 87, 0.08);
        transform: scale(1.02);
    }

    .prog-btn-view:active {
        transform: scale(0.98);
    }

    .prog-btn-invest {
        padding: 8px 20px;
        background: var(--accent, #2e8b57);
        color: #fff;
        border: none;
    }

    .prog-btn-invest:hover {
        background: var(--accent-hover, #3cb371);
        transform: scale(1.02);
    }

    .prog-btn-invest:active {
        transform: scale(0.98);
    }

    .prog-btn-invest.disabled,
    .prog-btn-invest:disabled {
        background: var(--text-muted, #888);
        cursor: not-allowed;
        opacity: 0.6;
    }

    .prog-btn-invest.disabled:hover,
    .prog-btn-invest:disabled:hover {
        transform: none;
        background: var(--text-muted, #888);
    }

    .prog-btn-cancel {
        padding: 12px 20px;
        background: rgba(231, 76, 60, 0.12);
        color: #e74c3c;
        border: 1px solid #e74c3c;
        width: 100%;
        margin-top: var(--spacing-sm, 12px);
        font-size: 0.9rem;
    }

    .prog-btn-cancel:hover {
        background: #e74c3c;
        color: #fff;
    }

    .prog-btn-cancel:active {
        transform: scale(0.98);
    }

    .prog-btn-confirm {
        padding: var(--spacing-sm, 10px) var(--spacing-md, 20px);
        background: var(--accent, #2e8b57);
        color: #fff;
        border: none;
        width: 100%;
    }

    .prog-btn-confirm:hover {
        background: var(--accent-hover, #3cb371);
    }

    .prog-btn-confirm:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .prog-modal-close {
        padding: var(--spacing-sm, 10px) var(--spacing-md, 20px);
        background: var(--bg, #f5f5f5);
        color: var(--text, #222);
        border: 1px solid var(--border-color, #e0e0e0);
        width: 100%;
    }

    .prog-modal-close:hover {
        background: var(--border-color, #e0e0e0);
    }

    body.dark-mode .prog-modal-close {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }

    body.dark-mode .prog-modal-close:hover {
        background: var(--border-color, #444);
    }

    /* ===== INVESTED PROGRAMME CARD ===== */
    .prog-invested-card {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius, 16px);
        padding: var(--spacing-lg, 28px) var(--spacing-md, 20px);
        box-shadow: var(--shadow, 0 2px 12px rgba(0, 0, 0, 0.06));
    }

    .prog-detail-header {
        text-align: center;
        margin-bottom: var(--spacing-lg, 24px);
        padding-bottom: var(--spacing-md, 16px);
        border-bottom: 1px solid var(--border-color, #e0e0e0);
    }

    .prog-detail-name {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--accent, #2e8b57);
        margin-bottom: 6px;
        word-break: break-word;
    }

    .prog-detail-dev {
        font-size: 0.9rem;
        color: var(--text-muted, #888);
    }

    .prog-detail-dev strong {
        color: var(--text, #222);
        font-weight: 600;
    }

    .prog-detail-section-title {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--text-muted, #888);
        font-weight: 700;
        margin: var(--spacing-md, 20px) 0 var(--spacing-sm, 12px) 0;
        padding-bottom: 6px;
        border-bottom: 1px solid var(--border-color, #e0e0e0);
    }

    .prog-detail-section-title:first-of-type {
        margin-top: 0;
    }

    .prog-detail-grid {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .prog-detail-grid-2col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4px 24px;
    }

    .prog-detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--spacing-sm, 12px);
        padding: 8px 0;
        border-bottom: 1px solid var(--border-color, rgba(0, 0, 0, 0.04));
    }

    .prog-detail-row:last-child {
        border-bottom: none;
    }

    .prog-detail-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: var(--text-muted, #888);
        font-weight: 600;
        flex-shrink: 0;
    }

    .prog-detail-value {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text, #222);
        text-align: right;
        word-break: break-word;
    }

    /* ===== MODALS ===== */
    .prog-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: var(--spacing-md, 20px);
        box-sizing: border-box;
    }

    .prog-modal.active {
        display: flex;
    }

    .prog-modal-content {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius, 16px);
        padding: var(--spacing-lg, 28px);
        max-width: 420px;
        width: 100%;
        box-shadow: var(--shadow-lg, 0 8px 32px rgba(0, 0, 0, 0.2));
        max-height: 85vh;
        overflow-y: auto;
        animation: progModalSlideIn 0.25s ease;
    }

    .prog-modal-wide {
        max-width: 500px;
    }

    @keyframes progModalSlideIn {
        from {
            opacity: 0;
            transform: scale(0.96) translateY(16px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .prog-modal-header {
        margin-bottom: var(--spacing-md, 20px);
        padding-bottom: var(--spacing-sm, 12px);
        border-bottom: 1px solid var(--border-color, #e0e0e0);
    }

    .prog-modal-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--accent, #2e8b57);
        margin: 0;
        text-align: center;
    }

    .prog-modal-body {
        margin-bottom: var(--spacing-md, 20px);
    }

    .prog-modal-loading,
    .prog-modal-error {
        text-align: center;
        padding: var(--spacing-md, 20px) 0;
        color: var(--text-muted, #888);
        font-size: 0.9rem;
    }

    .prog-modal-error {
        color: var(--danger, #e74c3c);
    }

    .prog-modal-actions {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm, 10px);
    }

    .prog-alert-text {
        font-size: 0.95rem;
        line-height: 1.6;
        color: var(--text, #222);
        margin: 0;
        text-align: center;
        white-space: pre-line;
    }

    /* ===== DARK MODE OVERRIDES ===== */
    body.dark-mode .prog-item,
    body.dark-mode .prog-invested-card,
    body.dark-mode .prog-modal-content {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .prog-item-name,
    body.dark-mode .prog-item-stat-value,
    body.dark-mode .prog-detail-value,
    body.dark-mode .prog-detail-dev strong,
    body.dark-mode .prog-alert-text {
        color: var(--text, #eee);
    }

    body.dark-mode .prog-detail-header,
    body.dark-mode .prog-detail-section-title,
    body.dark-mode .prog-detail-row,
    body.dark-mode .prog-modal-header {
        border-color: var(--border-color, #333);
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
        .prog-page-wrapper {
            padding: var(--spacing-md, 20px) var(--spacing-sm, 12px);
        }

        .prog-page-header h1 {
            font-size: 1.5rem;
        }

        .prog-detail-grid-2col {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .prog-page-header h1 {
            font-size: 1.4rem;
        }

        .prog-item-actions {
            flex-direction: column;
        }

        .prog-btn-view,
        .prog-btn-invest {
            width: 100%;
            text-align: center;
        }

        .prog-invested-card {
            padding: var(--spacing-md, 20px) var(--spacing-sm, 14px);
        }

        .prog-detail-name {
            font-size: 1.2rem;
        }

        .prog-detail-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }

        .prog-detail-value {
            text-align: left;
        }

        .prog-modal-content {
            padding: var(--spacing-md, 20px) var(--spacing-sm, 16px);
        }
    }
</style>