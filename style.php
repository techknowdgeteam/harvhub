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
        --hero1:#0b3d2e;--hero2:#12a36b;
        --bg: #ffffff;
        --bg-card: #ffffff;
        --text: #1a2332;
        --text-secondary: #5a6c7d;
        --text-muted: #8a9aa8;
        --text-light: #e8edf2;
        --accent: #2ecc8f;
        --accent-light: #e8f4f7;
        --accent-hover: #2ecc8fba;
        --success: #2ecc71;
        --success-bg: #eafaf1;
        --danger: #e74c3c;
        --danger-bg: #fdedec;
        --warning: #f39c12;
        --warning-bg: #fef9e7;
        --info: #1fb6a6;              /* was #3498db — teal-green, distinct from success */
        --info-bg: #e6f7f4;           /* was #ebf5fb — pale teal-green */
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
        --hero1:#0b3d2e;--hero2:#12a36b;
        --bg: #0c1311;
        --bg-card: #131d1a;
        --text: #e6edf3;
        --text-secondary: #8b949e;
        --text-muted: #6e7681;
        --text-light: #30363d;
        --accent: #2ecc8f;
        --accent-light: #1a2a30;
        --accent-hover: #2ecc8fba;
        --success: #3fb950;
        --success-bg: #1a2a1a;
        --danger: #f85149;
        --danger-bg: #2a1a1a;
        --warning: #d29922;
        --warning-bg: #2a241a;
        --info: #2fd4bf;              /* was #58a6ff — bright teal-green for dark bg */
        --info-bg: #102a28;           /* was #1a2430 — deep teal-green background */
        --border-color: #0c1311;
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
    /* ---------- GLOBAL HORIZONTAL SCROLLBAR HIDER ---------- */
    /* Hide the horizontal scrollbar on every element. Scroll still works,
    it's just the visual bar that's removed. */

    html, body {
        overflow-x: hidden;
        scrollbar-width: none;        /* Firefox */
        -ms-overflow-style: none;     /* IE / Edge legacy */
    }
    html::-webkit-scrollbar,
    body::-webkit-scrollbar {
        width: 0;
        height: 0;
    }

    /* Any element that scrolls horizontally should hide its scrollbar too. */
    * {
        scrollbar-width: none;         /* Firefox */
        -ms-overflow-style: none;      /* IE / Edge legacy */
    }
    *::-webkit-scrollbar {
        width: 0;
        height: 0;
        display: none;                 /* Chrome / Safari / Edge */
    }

    /* Optional: allow horizontal scroll to keep working but visually hidden
    on the specific containers you use (tabs, tables, chips, etc.) */
    .sd-tabs,
    .sd-inv-tabs,
    .sd-check-list,
    .sd-picker-list,
    .rev-header,
    .rev-content,
    .header-sheet,
    .notification-list {
        overflow-x: auto;
        overflow-y: hidden;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .sd-tabs::-webkit-scrollbar,
    .sd-inv-tabs::-webkit-scrollbar,
    .sd-check-list::-webkit-scrollbar,
    .sd-picker-list::-webkit-scrollbar,
    .header-sheet::-webkit-scrollbar,
    .notification-list::-webkit-scrollbar {
        display: none;
        width: 0;
        height: 0;
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
    /* ============================================================
    CONTRACT ENROLLMENT PROTOCOL MODAL - FIXED STYLES
    ============================================================ */

    /* Modal content container */
    #reenrollModal.modal .modal-content {
        max-width: 520px;
        padding: 32px 28px;
        border-radius: var(--radius, 16px);
        background: var(--modal-bg, #ffffff);
        border: 1px solid var(--border-color, #e2e8f0);
        box-shadow: var(--shadow-lg, 0 8px 32px rgba(0, 0, 0, 0.08));
    }

    /* Modal heading */
    #reenrollModal.modal .modal-content h2 {
        font-size: 1.45rem;
        font-weight: 700;
        color: var(--info, #3498db);
        margin: 0 0 10px 0;
        text-align: center;
        letter-spacing: -0.3px;
    }

    /* Intro paragraph */
    #reenrollModal.modal .modal-content > p {
        margin: 0 0 20px 0;
        opacity: 0.85;
        font-size: 0.92rem;
        line-height: 1.6;
        text-align: center;
        color: var(--text-secondary, #5a6c7d);
    }

    /* Instructions block */
    #reenrollModal .reenroll-instructions {
        background: var(--bg, #f0f4f8);
        border: 1px solid var(--border-color, #e2e8f0);
        border-radius: var(--radius-sm, 10px);
        padding: 18px 20px;
        margin-bottom: 20px;
    }

    #reenrollModal .reenroll-instructions h4 {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--text-muted, #8a9aa8);
        margin: 0 0 14px 0;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-color, #e2e8f0);
    }

    #reenrollModal .reenroll-instructions ul {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    #reenrollModal .reenroll-instructions li {
        position: relative;
        padding-left: 26px;
        font-size: 0.88rem;
        line-height: 1.55;
        color: var(--text, #1a2332);
    }

    #reenrollModal .reenroll-instructions li::before {
        content: "●";
        position: absolute;
        left: 0;
        top: 0;
        font-size: 0.6rem;
        color: var(--info, #3498db);
        line-height: 1.8;
    }

    #reenrollModal .reenroll-instructions li strong {
        color: var(--text, #1a2332);
        font-weight: 700;
    }

    /* Consequence note */
    #reenrollModal .consequence-note {
        margin-top: 16px;
        padding: 12px 14px;
        background: var(--danger-bg, #fdedec);
        border-radius: 6px;
        font-size: 0.82rem;
        line-height: 1.5;
        color: var(--danger, #e74c3c);
        font-weight: 600;
    }

    /* Checkbox container */
    #reenrollModal .checkbox-container-legal {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 16px;
        margin-bottom: 20px;
        background: var(--bg, #f0f4f8);
        border: 1px solid var(--border-color, #e2e8f0);
        border-radius: var(--radius-sm, 10px);
        cursor: pointer;
        transition: border-color 0.2s ease, background 0.2s ease;
        -webkit-tap-highlight-color: transparent;
    }

    #reenrollModal .checkbox-container-legal:hover {
        border-color: var(--info, #3498db);
    }

    #reenrollModal .checkbox-container-legal input[type="checkbox"] {
        width: 20px;
        height: 20px;
        margin: 0;
        flex-shrink: 0;
        cursor: pointer;
        accent-color: var(--info, #3498db);
        -webkit-appearance: none;
        appearance: none;
        border: 2px solid var(--border-color, #e2e8f0);
        border-radius: 5px;
        background: var(--bg-card, #ffffff);
        position: relative;
        transition: all 0.15s ease;
    }

    #reenrollModal .checkbox-container-legal input[type="checkbox"]:checked {
        background: var(--info, #3498db);
        border-color: var(--info, #3498db);
    }

    #reenrollModal .checkbox-container-legal input[type="checkbox"]:checked::after {
        content: "✓";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #fff;
        font-size: 13px;
        font-weight: 700;
        line-height: 1;
    }

    #reenrollModal .checkbox-container-legal label {
        font-size: 0.88rem;
        line-height: 1.5;
        color: var(--text, #1a2332);
        cursor: pointer;
        margin: 0;
        padding-top: 1px;
    }

    /* Modal actions */
    #reenrollModal .modal-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 0;
    }

    /* Proceed button */
    #reenrollModal .reenroll-confirm-btn {
        width: 100%;
        padding: 14px 20px;
        background: #555;
        color: #999;
        border: none;
        border-radius: var(--radius-sm, 10px);
        font-size: 0.95rem;
        font-weight: 700;
        cursor: not-allowed;
        transition: all 0.25s ease;
        font-family: inherit;
        opacity: 0.6;
        letter-spacing: 0.2px;
    }

    #reenrollModal .reenroll-confirm-btn:not(:disabled) {
        background: #0080bc;
        color: #000;
        cursor: pointer;
        opacity: 1;
        box-shadow: 0 4px 14px rgba(0, 128, 188, 0.3);
    }

    #reenrollModal .reenroll-confirm-btn:not(:disabled):hover {
        background: #0099e0;
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(0, 128, 188, 0.4);
    }

    #reenrollModal .reenroll-confirm-btn:not(:disabled):active {
        transform: translateY(0) scale(0.98);
    }

    /* Cancel button */
    #reenrollModal .modal-actions > button:last-child {
        width: 100%;
        padding: 12px 20px;
        background: #555;
        color: #ffffff;
        border: none;
        border-radius: var(--radius-sm, 10px);
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        font-family: inherit;
    }

    #reenrollModal .modal-actions > button:last-child:hover {
        background: #666;
    }

    #reenrollModal .modal-actions > button:last-child:active {
        transform: scale(0.98);
    }

    /* ============================================================
    DARK MODE OVERRIDES
    ============================================================ */
    body.dark-mode #reenrollModal.modal .modal-content {
        background: var(--modal-bg, #161b22);
        border-color: var(--border-color, #30363d);
    }

    body.dark-mode #reenrollModal .reenroll-instructions {
        background: var(--bg, #0c1311);
        border-color: var(--border-color, #30363d);
    }

    body.dark-mode #reenrollModal .reenroll-instructions h4 {
        color: var(--text-muted, #6e7681);
        border-color: var(--border-color, #30363d);
    }

    body.dark-mode #reenrollModal .reenroll-instructions li {
        color: var(--text, #e6edf3);
    }

    body.dark-mode #reenrollModal .reenroll-instructions li strong {
        color: var(--text, #e6edf3);
    }

    body.dark-mode #reenrollModal .consequence-note {
        background: rgba(248, 81, 73, 0.1);
        border-color: var(--danger, #f85149);
        color: var(--danger, #f85149);
    }

    body.dark-mode #reenrollModal .checkbox-container-legal {
        background: var(--bg, #0c1311);
        border-color: var(--border-color, #30363d);
    }

    body.dark-mode #reenrollModal .checkbox-container-legal:hover {
        border-color: var(--info, #58a6ff);
    }

    body.dark-mode #reenrollModal .checkbox-container-legal input[type="checkbox"] {
        background: var(--bg-card, #21262d);
        border-color: var(--border-color, #30363d);
    }

    body.dark-mode #reenrollModal .checkbox-container-legal input[type="checkbox"]:checked {
        background: var(--info, #58a6ff);
        border-color: var(--info, #58a6ff);
    }

    body.dark-mode #reenrollModal .checkbox-container-legal label {
        color: var(--text, #e6edf3);
    }

    body.dark-mode #reenrollModal .reenroll-confirm-btn:not(:disabled) {
        background: #0080bc;
        color: #ffffff;
    }

    body.dark-mode #reenrollModal .reenroll-confirm-btn:not(:disabled):hover {
        background: #0099e0;
    }

    body.dark-mode #reenrollModal .modal-actions > button:last-child {
        background: #30363d;
        color: var(--text, #e6edf3);
    }

    body.dark-mode #reenrollModal .modal-actions > button:last-child:hover {
        background: #3d444d;
    }

    /* ============================================================
    RESPONSIVE
    ============================================================ */
    @media (max-width: 768px) {
        #reenrollModal.modal .modal-content {
            padding: 24px 20px;
            margin: 12px;
            max-height: 90vh;
        }

        #reenrollModal.modal .modal-content h2 {
            font-size: 1.25rem;
        }

        #reenrollModal .reenroll-instructions {
            padding: 14px 16px;
        }

        #reenrollModal .reenroll-instructions li {
            font-size: 0.84rem;
            padding-left: 22px;
        }

        #reenrollModal .checkbox-container-legal {
            padding: 12px 14px;
        }

        #reenrollModal .checkbox-container-legal label {
            font-size: 0.84rem;
        }

        #reenrollModal .reenroll-confirm-btn {
            padding: 13px 18px;
            font-size: 0.9rem;
        }
    }

    @media (max-width: 400px) {
        #reenrollModal.modal .modal-content {
            padding: 20px 16px;
        }

        #reenrollModal.modal .modal-content h2 {
            font-size: 1.1rem;
        }

        #reenrollModal.modal .modal-content > p {
            font-size: 0.85rem;
        }

        #reenrollModal .reenroll-instructions h4 {
            font-size: 0.72rem;
        }

        #reenrollModal .reenroll-instructions li {
            font-size: 0.8rem;
        }

        #reenrollModal .consequence-note {
            font-size: 0.78rem;
            padding: 10px 12px;
        }
    }
</style>

<style>
    /* ============================================================
       REVENUE HISTORY — PAGE STYLES
       (Header is fixed, content scrolls underneath)

       Alignment goal:
       The header title "Revenue History" starts on the SAME vertical
       line as the 💰 money-bag icon inside the collapsed card.

       Geometry:
         wrapper padding-left .............. --rev-gutter
         card padding-left ................. --rev-card-pad
         header padding-left ............... --rev-gutter + --rev-card-pad
       That makes the header text column start exactly where the
       card's icon-wrap starts.
       ============================================================ */

    /* Hide the shell bottom nav on this takeover page */
    body.page-revenue_history .bottom-nav {
        display: none !important;
    }

    /* Body layout: fixed header on top, scrollable content below */
    body.page-revenue_history {
        padding: 0 !important;
        margin: 0 !important;
        overflow: hidden !important;
    }

    /* ============================================================
       SHARED TOKENS
       ============================================================ */
    :root {
        --rev-gutter: 16px;       /* page gutter (wrapper + header base) */
        --rev-card-pad: 14px;     /* matches .revenue-header-folded padding */
        --rev-header-h: 56px;
        --rev-max-w: 800px;
    }

    @media (max-width: 600px) {
        :root {
            --rev-gutter: 10px;
            --rev-card-pad: 12px;
            --rev-header-h: 52px;
        }
    }

    @media (max-width: 400px) {
        :root {
            --rev-gutter: 8px;
            --rev-card-pad: 10px;
            --rev-header-h: 50px;
        }
    }

    /* ============================================================
       FIXED TOP HEADER  (title only)
       ============================================================ */
    .rev-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        border-bottom: 1px solid var(--border-color, #e0e0e0);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        padding-top: env(safe-area-inset-top, 0);
    }

    body.dark-mode .rev-header {
        border-bottom-color: var(--border-color, #333);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
    }

    /*
       LEFT padding = --rev-gutter + --rev-card-pad
       This lands the header's text column exactly on top of the
       card's icon-wrap column, so "Revenue History" starts on the
       same vertical line as the 💰 icon.
    */
    .rev-header-inner {
        display: flex;
        align-items: center;
        max-width: var(--rev-max-w);
        margin: 0 auto;
        padding: 9px var(--rev-gutter) 9px calc(var(--rev-gutter) + var(--rev-card-pad));
        min-height: var(--rev-header-h);
        box-sizing: border-box;
    }

    .rev-header-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text, #222);
        margin: 0;
        letter-spacing: -0.3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    body.dark-mode .rev-header-title {
        color: var(--text, #eee);
    }

    /* ============================================================
       SCROLLABLE CONTENT UNDER THE FIXED HEADER
       ============================================================ */
    .rev-content {
        position: absolute;
        top: var(--rev-header-h);
        left: 0;
        right: 0;
        bottom: 0;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
    }

    @supports (padding: env(safe-area-inset-top)) {
        .rev-content {
            top: calc(var(--rev-header-h) + env(safe-area-inset-top, 0px));
        }
    }

    /* ============================================================
       MAIN CONTAINER — CENTERED, MAX WIDTH 800px
       ============================================================ */
    .revenue-wrapper {
        width: 100%;
        max-width: var(--rev-max-w);
        margin: 0 auto;
        padding: 16px var(--rev-gutter) 110px var(--rev-gutter); /* bottom padding leaves room for the fixed button */
        box-sizing: border-box;
    }

    /* ============================================================
       REVENUE ITEM (CARD)
       ============================================================ */
    .revenue-item {
        background: var(--bg-card);
        margin-bottom: 8px;
        overflow: hidden;
        transition: all 0.25s ease;
        cursor: pointer;
        border-radius: var(--radius-sm, 8px);
    }

    /* ============================================================
       FOLDED (COLLAPSED) STATE
       ============================================================ */
    .revenue-header-folded {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px var(--rev-card-pad);
        gap: 12px;
        transition: background 0.2s ease;
        min-height: 58px;
    }

    .revenue-header-folded:hover {
        background: var(--bg-hover, rgba(0,0,0,0.02));
    }

    body.dark-mode .revenue-header-folded:hover {
        background: var(--bg-hover, rgba(255,255,255,0.03));
    }

    /* Icon wrapper — spans the full folded header height */
    .revenue-icon-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        align-self: stretch;
        flex-shrink: 0;
        width: 28px;
    }

    .revenue-icon {
        font-size: 1.1rem;
        line-height: 1;
        display: inline-block;
    }

    /* The stacked text block (user-share on top, date-range below) */
    .revenue-folded-text {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 2px;
        flex: 1 1 auto;
        min-width: 0;
    }

    .revenue-user-share {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--text);
        line-height: 1.15;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .revenue-date-range {
        font-size: 0.7rem;
        font-weight: 500;
        color: var(--text-muted);
        letter-spacing: 0.2px;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Right side: status + toggle, vertically centered */
    .revenue-right {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 0 0 auto;
        flex-shrink: 0;
    }

    /* ============================================================
       STATUS BADGES
       ============================================================ */
    .revenue-status-badge {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.55rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        white-space: nowrap;
    }

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

    /* ============================================================
       TOGGLE ARROW
       ============================================================ */
    .revenue-toggle {
        font-size: 0.65rem;
        color: var(--text-muted);
        transition: transform 0.3s ease;
        flex-shrink: 0;
        width: 14px;
        text-align: center;
        font-weight: 700;
    }

    .revenue-item.expanded .revenue-toggle {
        transform: rotate(180deg);
    }

    /* ============================================================
       EXPANDED STATE (DETAILS)
       ============================================================ */
    .revenue-details {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.35s ease, padding 0.35s ease, opacity 0.25s ease;
        opacity: 0;
        padding: 0 var(--rev-card-pad);
    }

    .revenue-item.expanded .revenue-details {
        max-height: 900px;
        opacity: 1;
        padding: 0 var(--rev-card-pad) var(--rev-card-pad) var(--rev-card-pad);
    }

    .revenue-details-inner {
        border-top: 1px solid var(--border-color);
        padding-top: 10px;
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .revenue-detail-row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 12px;
        padding: 8px 0;
        font-size: 0.85rem;
        border-bottom: 1px solid var(--border-color-light, rgba(0,0,0,0.04));
    }

    .revenue-detail-row:last-child {
        border-bottom: none;
    }

    .revenue-detail-row.full-width {
        display: flex;
    }

    .revenue-detail-label {
        color: var(--text-muted);
        font-weight: 500;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .revenue-detail-value {
        color: var(--text);
        font-weight: 600;
        text-align: right;
        word-break: break-word;
        overflow-wrap: anywhere;
        max-width: 65%;
        line-height: 1.35;
    }

    .revenue-detail-value.mono {
        font-family: 'SF Mono', 'Courier New', monospace;
        font-size: 0.72rem;
        font-weight: 500;
        letter-spacing: -0.2px;
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

    /* ============================================================
       EMPTY STATE
       ============================================================ */
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

    /* ============================================================
       FLOATING CLOSE BUTTON
       ============================================================ */
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
        font-family: inherit;
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
       RESPONSIVE — small layout tweaks only.
       Alignment is maintained because both the header and the
       cards use --rev-gutter + --rev-card-pad.
       ============================================================ */

    @media (max-width: 600px) {
        .rev-header-inner {
            padding: 8px var(--rev-gutter) 8px calc(var(--rev-gutter) + var(--rev-card-pad));
        }

        .rev-header-title {
            font-size: 1.1rem;
        }

        .revenue-wrapper {
            padding: 12px var(--rev-gutter) 100px var(--rev-gutter);
        }

        .revenue-header-folded {
            padding: 10px var(--rev-card-pad);
            gap: 10px;
            min-height: 54px;
        }

        .revenue-icon-wrap {
            width: 24px;
        }

        .revenue-icon {
            font-size: 1rem;
        }

        .revenue-user-share {
            font-size: 1.05rem;
        }

        .revenue-date-range {
            font-size: 0.65rem;
        }

        .revenue-right {
            gap: 6px;
        }

        .revenue-status-badge {
            font-size: 0.5rem;
            padding: 2px 9px;
        }

        .revenue-toggle {
            font-size: 0.6rem;
            width: 12px;
        }

        .revenue-item.expanded .revenue-details {
            padding: 0 var(--rev-card-pad) var(--rev-card-pad) var(--rev-card-pad);
        }

        .revenue-detail-row {
            font-size: 0.8rem;
            padding: 7px 0;
        }

        .revenue-detail-label {
            font-size: 0.68rem;
        }

        .revenue-detail-value {
            font-size: 0.8rem;
            max-width: 60%;
        }

        .revenue-detail-value.mono {
            font-size: 0.68rem;
        }

        .floating-close-btn {
            bottom: 20px;
            padding: 12px 30px;
            font-size: 0.9rem;
            min-width: 140px;
        }
    }

    @media (max-width: 400px) {
        .rev-header-inner {
            padding: 8px var(--rev-gutter) 8px calc(var(--rev-gutter) + var(--rev-card-pad));
        }

        .rev-header-title {
            font-size: 1rem;
        }

        .revenue-wrapper {
            padding: 10px var(--rev-gutter) 90px var(--rev-gutter);
        }

        .revenue-header-folded {
            padding: 9px var(--rev-card-pad);
            gap: 8px;
            min-height: 50px;
        }

        .revenue-icon-wrap {
            width: 22px;
        }

        .revenue-icon {
            font-size: 0.95rem;
        }

        .revenue-user-share {
            font-size: 0.95rem;
        }

        .revenue-date-range {
            font-size: 0.6rem;
        }

        .revenue-status-badge {
            font-size: 0.45rem;
            padding: 2px 7px;
        }

        .revenue-toggle {
            font-size: 0.55rem;
            width: 11px;
        }

        .revenue-item.expanded .revenue-details {
            padding: 0 var(--rev-card-pad) var(--rev-card-pad) var(--rev-card-pad);
        }

        .revenue-detail-row {
            font-size: 0.75rem;
            padding: 6px 0;
        }

        .revenue-detail-label {
            font-size: 0.62rem;
        }

        .revenue-detail-value {
            font-size: 0.75rem;
            max-width: 58%;
        }

        .revenue-detail-value.mono {
            font-size: 0.62rem;
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
            font-size: 0.85rem;
        }

        .revenue-date-range {
            font-size: 0.55rem;
        }

        .revenue-status-badge {
            font-size: 0.4rem;
            padding: 1px 6px;
        }

        .revenue-toggle {
            font-size: 0.5rem;
            width: 10px;
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
       MYDASHBOARD STYLE
       ============================================================ */
    body.sd-scroll-locked {
        overflow: hidden;
    }

    /* ============================================================
       SESSION INFO MODAL
       ============================================================ */
    .session-info-modal {
        position: fixed !important;
        inset: 0 !important;
        z-index: 99999 !important;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--modal-overlay);
        padding: 20px;
        overscroll-behavior: contain;
    }

    .session-info-modal .session-modal-content {
        width: min(92vw, 460px);
        max-width: 460px;
        border-radius: var(--radius);
        padding: 24px;
        box-shadow: var(--shadow-lg);
        text-align: center;
        background: var(--modal-bg);
        border: 1px solid var(--border-color);
        color: var(--modal-text);
    }

    .session-modal-content.success {
        border-top: 4px solid var(--success);
    }

    .session-modal-content.warning {
        border-top: 4px solid var(--warning);
    }

    .session-modal-content.danger {
        border-top: 4px solid var(--danger);
    }

    .session-modal-content.info {
        border-top: 4px solid var(--accent);
    }

    .session-modal-title {
        margin: 0 0 12px;
        color: var(--modal-text);
    }

    .session-modal-message {
        line-height: 1.6;
        opacity: 0.9;
        margin: 0 0 18px;
        color: var(--text-secondary);
    }

    .session-modal-ok {
        min-width: 120px;
    }

    /* ============================================================
       PROFILE COMPLETE MODAL
       ============================================================ */
    #profileCompleteModal {
        z-index: 100000 !important;
    }

    #profileCompleteModal .modal-content {
        max-width: 520px;
        width: min(92vw, 520px);
        max-height: 92vh;
        overflow: auto;
        overscroll-behavior: contain;
    }

    #profileCompleteModal input {
        background: var(--input-bg);
        color: var(--input-text);
        border: 1px solid var(--input-border);
        box-sizing: border-box;
        width: 100%;
        padding: 12px;
        margin-top: 6px;
        border-radius: var(--radius-sm);
    }

    #profileCompleteModal input::placeholder {
        color: var(--input-placeholder);
    }

    #profileCompleteModal .pc-error {
        display: none;
        background: var(--danger-bg);
        padding: 12px;
        margin: 12px 0;
        color: var(--danger);
        border-radius: var(--radius-sm);
        font-size: 0.9rem;
    }

    #profileCompleteModal .pc-success {
        display: none;
        background: var(--success-bg);
        padding: 12px;
        margin: 12px 0;
        color: var(--success);
        border-radius: var(--radius-sm);
        font-size: 0.9rem;
    }

    /* ============================================================
       ACCOUNT NAME MODAL
       ============================================================ */
    #accountNameModal {
        z-index: 100001 !important;
    }

    #accountNameModal .modal-content {
        max-width: 460px;
        width: min(92vw, 460px);
        max-height: 92vh;
        overflow: auto;
        overscroll-behavior: contain;
    }

    #accountNameModal input {
        background: var(--input-bg);
        color: var(--input-text);
        border: 1px solid var(--input-border);
        box-sizing: border-box;
        width: 100%;
        padding: 12px;
        margin-top: 6px;
        border-radius: var(--radius-sm);
    }

    #accountNameModal input::placeholder {
        color: var(--input-placeholder);
    }

    #accountNameModal .ac-error {
        display: none;
        background: var(--danger-bg);
        padding: 12px;
        margin: 12px 0;
        color: var(--danger);
        border-radius: var(--radius-sm);
        font-size: 0.9rem;
    }

    #accountNameModal .ac-hint {
        font-size: 0.72rem;
        opacity: 0.7;
        margin-top: 6px;
        line-height: 1.4;
        color: var(--text-muted);
    }

    /* ============================================================
       REENROLL / APPLY / RESET MODALS
       ============================================================ */
    #reenrollModal .modal-content,
    #applyModal .modal-content,
    #resetModal .modal-content {
        max-height: 92vh;
        overflow: auto;
        overscroll-behavior: contain;
    }

    #reenrollModal .modal-content h2 {
        color: var(--accent);
    }

    #applyModal .modal-content h2 {
        color: var(--accent);
    }

    #resetModal .modal-content h2 {
        color: var(--accent);
    }

    .reenroll-instructions {
        background: var(--bg);
        padding: 1rem;
        border-radius: var(--radius-sm);
        margin: 1rem 0;
    }

    .reenroll-instructions h4 {
        color: var(--text);
        margin-bottom: 0.5rem;
    }

    .reenroll-instructions ul {
        list-style: none;
        padding-left: 0;
    }

    .reenroll-instructions ul li {
        margin-bottom: 10px;
        color: var(--text-secondary);
    }

    .consequence-note {
        background: var(--danger-bg);
        padding: 0.75rem;
        border-radius: var(--radius-sm);
        color: var(--danger);
        font-size: 0.9rem;
        margin-top: 0.5rem;
    }

    .checkbox-container-legal {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 1rem 0;
        cursor: pointer;
    }

    .checkbox-container-legal input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--accent);
    }

    .checkbox-container-legal label {
        cursor: pointer;
        color: var(--text-secondary);
        font-size: 0.95rem;
    }

    .reenroll-confirm-btn {
        width: 100%;
        padding: 12px;
        border: none;
        border-radius: var(--radius-sm);
        font-weight: 600;
        font-size: 0.95rem;
        cursor: not-allowed;
        background: var(--bg);
        color: var(--text-muted);
        transition: all 0.2s ease;
    }

    .reenroll-confirm-btn:not(:disabled) {
        background: var(--accent);
        color: #fff;
        cursor: pointer;
    }

    .reenroll-confirm-btn:not(:disabled):hover {
        background: var(--accent-hover);
    }

    /* ============================================================
       APPLY MODAL SPECIFIC
       ============================================================ */
    .apply-instructions {
        background: var(--bg);
        padding: 1rem;
        border-radius: var(--radius-sm);
        margin: 1rem 0;
    }

    .apply-instructions ul {
        list-style: none;
        padding-left: 0;
    }

    .apply-instructions ul li {
        margin-bottom: 10px;
        color: var(--text-secondary);
    }

    .apply-warning {
        background: var(--warning-bg);
        padding: 1rem;
        border-radius: var(--radius-sm);
        margin: 1rem 0;
    }

    .apply-warning strong {
        color: var(--warning);
    }

    .apply-warning p {
        margin-top: 0.5rem;
        color: var(--text-secondary);
    }

    /* ============================================================
       RESET MODAL SPECIFIC
       ============================================================ */
    .reset-note {
        background: var(--success-bg);
        padding: 1rem;
        border-radius: var(--radius-sm);
        margin: 1rem 0;
    }

    .reset-note strong {
        color: var(--success);
    }

    .reset-note p {
        margin-top: 0.5rem;
        color: var(--text-secondary);
    }

    /* ============================================================
       MODAL ACTION BUTTONS (generic)
       ============================================================ */
    .modal-actions .btn-full {
        width: 100%;
    }

    .modal-actions .btn-cancel {
        width: 100%;
        padding: 12px;
        background: var(--bg);
        color: var(--text);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-weight: 600;
        transition: background 0.2s ease;
    }

    .modal-actions .btn-cancel:hover {
        background: var(--border-color);
    }

    /* ============================================================
       MYDASHBOARD GREETING CARD
       ============================================================ */
    .md-greeting {
        background: linear-gradient(135deg, rgba(46, 204, 143, 0.12), rgba(46, 204, 143, 0.03));
        border-radius: var(--radius);
        padding: 16px 18px;
        margin-bottom: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    body.dark-mode .md-greeting {
        background: linear-gradient(135deg, rgba(46, 204, 143, 0.18), rgba(46, 204, 143, 0.04));
    }

    .md-greeting-top {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .md-greeting-avatar {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: var(--accent);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.15rem;
        flex-shrink: 0;
    }

    .md-greeting-text {
        flex: 1;
        min-width: 0;
    }

    .md-greeting-text .md-greet-account {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--accent);
        font-weight: 700;
        margin: 0 0 2px 0;
        word-break: break-word;
    }

    .md-greeting-text .md-greet-title {
        font-size: 1.02rem;
        font-weight: 700;
        color: var(--text);
        margin: 0 0 2px 0;
        word-break: break-word;
    }

    .md-greeting-text .md-greet-sub {
        font-size: 0.82rem;
        color: var(--text-muted);
        margin: 0;
        line-height: 1.45;
    }

    body.dark-mode .md-greeting-text .md-greet-title {
        color: var(--text);
    }

    .md-greeting-divider {
        height: 1px;
        background: var(--border-color);
        margin: 0 -18px;
    }

    body.dark-mode .md-greeting-divider {
        background: var(--border-color);
    }

    /* Programme card with arrow */
    .md-greeting-programme {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 4px 2px;
        cursor: pointer;
        border-radius: var(--radius-sm);
        transition: background 0.2s ease;
        text-decoration: none;
        color: inherit;
        border: none;
        background: transparent;
        width: 100%;
        text-align: left;
        font-family: inherit;
    }

    .md-greeting-programme:hover {
        background: rgba(46, 204, 143, 0.08);
    }

    body.dark-mode .md-greeting-programme:hover {
        background: rgba(46, 204, 143, 0.14);
    }

    .md-greeting-programme .md-prog-text {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
        flex: 1;
    }

    .md-greeting-programme .account-label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        font-weight: 600;
    }

    .md-greeting-programme .account-number {
        font-size: 0.98rem;
        font-weight: 700;
        color: var(--text);
        word-break: break-word;
    }

    body.dark-mode .md-greeting-programme .account-number {
        color: var(--text);
    }

    .md-greeting-programme .md-prog-arrow {
        flex-shrink: 0;
        color: var(--text-muted);
        font-size: 0.85rem;
        transition: transform 0.2s ease, color 0.2s ease;
    }

    .md-greeting-programme:hover .md-prog-arrow {
        color: var(--accent);
        transform: translateX(2px);
    }

    .md-greeting-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .md-account-meta {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px dashed var(--border-color);
        display: flex;
        flex-wrap: wrap;
        gap: 8px 16px;
        font-size: 0.78rem;
    }

    .md-account-meta .md-account-meta-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }

    .md-account-meta .md-account-meta-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted);
        font-weight: 600;
    }

    .md-account-meta .md-account-meta-value {
        font-weight: 700;
        color: var(--text);
        word-break: break-word;
    }

    body.dark-mode .md-account-meta .md-account-meta-value {
        color: var(--text);
    }

    body {
        padding-top: 0 !important;
        padding-bottom: 70px;
        margin-top: 0 !important;
    }

    /* ============================================================
       INCOMING REQUESTS ROWS
       ============================================================ */
    .md-greeting-incoming {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .md-incoming-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
        padding: 12px 14px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        color: var(--text);
        font-family: inherit;
        font-size: 0.92rem;
        font-weight: 600;
        cursor: pointer;
        text-align: left;
        transition: transform 0.15s ease, border-color 0.2s ease, background 0.2s ease;
        -webkit-tap-highlight-color: transparent;
    }

    .md-incoming-row:hover {
        border-color: var(--accent);
        transform: translateX(2px);
    }

    .md-incoming-row:active {
        transform: translateX(0) scale(0.99);
    }

    body.dark-mode .md-incoming-row {
        background: var(--bg-card);
        border-color: var(--border-color);
        color: var(--text);
    }

    body.dark-mode .md-incoming-row:hover {
        border-color: var(--accent);
        background: rgba(46, 204, 143, 0.08);
    }

    .md-incoming-row-left {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        flex: 1;
    }

    .md-incoming-row-icon {
        width: 32px;
        height: 32px;
        flex-shrink: 0;
        border-radius: var(--radius-sm);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(46, 204, 143, 0.12);
        color: var(--accent);
        font-size: 0.9rem;
    }

    .md-incoming-row.is-vps .md-incoming-row-icon {
        background: rgba(46, 204, 143, 0.12);
        color: var(--accent);
    }

    .md-incoming-row-label {
        display: flex;
        flex-direction: column;
        gap: 1px;
        min-width: 0;
    }

    .md-incoming-row-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--text);
        word-break: break-word;
    }

    body.dark-mode .md-incoming-row-title {
        color: var(--text);
    }

    .md-incoming-row-sub {
        font-size: 0.72rem;
        font-weight: 500;
        color: var(--text-muted);
    }

    .md-incoming-row-right {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }

    .md-incoming-row-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        padding: 0 7px;
        border-radius: 999px;
        background: var(--accent);
        color: #fff;
        font-size: 0.7rem;
        font-weight: 800;
        line-height: 1;
    }

    .md-incoming-row.is-vps .md-incoming-row-count {
        background: var(--accent);
    }

    .md-incoming-row-chev {
        color: var(--text-muted);
        font-size: 0.85rem;
        transition: transform 0.2s ease;
    }

    .md-incoming-row:hover .md-incoming-row-chev {
        transform: translateX(2px);
        color: var(--accent);
    }

    .md-incoming-row.is-vps:hover .md-incoming-row-chev {
        color: var(--accent);
    }

    /* ============================================================
       ACCOUNT ACTIONS
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
        background: linear-gradient(135deg, var(--accent), var(--accent-hover));
        color: #fff;
    }

    .btn-find-trader:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(46, 204, 143, 0.3);
    }

    .btn-get-vps {
        background: linear-gradient(135deg, var(--accent), var(--accent-hover));
        color: #fff;
    }

    .btn-get-vps:hover {
        background: linear-gradient(135deg, var(--accent-hover), var(--accent));
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(46, 204, 143, 0.3);
    }

    .btn-account-action.btn-get-vps {
        background: linear-gradient(135deg, var(--accent), var(--accent-hover));
        color: #fff;
    }

    .btn-account-action.btn-get-vps:hover {
        background: linear-gradient(135deg, var(--accent-hover), var(--accent));
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
       CURRENT BALANCE CARD WITH PEAK BADGE
       ============================================================ */
    .current-balance-card .card-value-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
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

    .current-balance-card .peak-balance {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        justify-content: center;
        gap: 2px;
        flex-shrink: 0;
        padding: 6px 12px;
        background: var(--bg);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        min-width: 0;
    }

    .current-balance-card .peak-balance .peak-label {
        font-size: 0.55rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--text-muted);
        font-weight: 600;
        white-space: nowrap;
    }

    .current-balance-card .peak-balance .peak-value {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--accent);
        font-family: 'SF Mono', 'Monaco', monospace;
        letter-spacing: -0.3px;
        white-space: nowrap;
    }

    .current-balance-card .peak-balance .peak-value .peak-currency {
        font-size: 0.7rem;
        font-weight: 500;
        color: var(--text-muted);
        margin-right: 1px;
    }

    .current-balance-card .peak-balance.peak-above {
        border-color: var(--warning);
        background: var(--warning-bg);
    }

    .current-balance-card .peak-balance.peak-above .peak-value {
        color: var(--warning);
    }

    /* ============================================================
       PROFIT & LOSS CARD WITH INLINE CHART
       ============================================================ */
    .pnl-card .card-value-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
    }

    .pnl-card .card-value {
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

    .pnl-card .card-value .currency-symbol {
        font-size: 1.4rem;
        font-weight: 400;
        color: var(--text-muted);
    }

    .pnl-card .card-value .value-amount {
        font-size: 2.2rem;
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    .pnl-card .chart-bars-container {
        flex: 1;
        margin-top: 0;
        padding-top: 0;
        border-top: none;
        min-width: 60px;
    }

    .pnl-card .chart-bars-wrapper {
        height: 40px;
        gap: 1.5px;
    }

    .pnl-card .chart-bar-item {
        flex: 1;
        max-width: 8px;
        min-width: 2px;
        margin: 0 0.5px;
    }

    .pnl-card .chart-bar {
        min-height: 2px;
        border-radius: 1px 1px 0 0;
    }

    .pnl-card .chart-labels {
        display: none;
    }

    .pnl-card .pnl-indicator {
        margin-top: 8px;
    }

    /* ============================================================
       RESPONSIVE DESIGN
       ============================================================ */
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

        .current-balance-card .chart-bars-wrapper,
        .pnl-card .chart-bars-wrapper {
            height: 36px;
            gap: 1px;
        }

        .current-balance-card .chart-bar-item,
        .pnl-card .chart-bar-item {
            max-width: 7px;
            min-width: 2px;
            margin: 0 0.3px;
        }

        .current-balance-card .peak-balance {
            padding: 5px 10px;
        }

        .current-balance-card .peak-balance .peak-value {
            font-size: 0.85rem;
        }
    }

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

        .current-balance-card .card-value-row,
        .pnl-card .card-value-row {
            gap: 8px;
            flex-wrap: nowrap;
        }

        .current-balance-card .card-value,
        .pnl-card .card-value {
            font-size: 1.8rem;
        }

        .current-balance-card .card-value .value-amount,
        .pnl-card .card-value .value-amount {
            font-size: 1.8rem;
        }

        .current-balance-card .chart-bars-wrapper,
        .pnl-card .chart-bars-wrapper {
            height: 32px;
            gap: 1px;
        }

        .current-balance-card .chart-bar-item,
        .pnl-card .chart-bar-item {
            max-width: 6px;
            min-width: 1.5px;
            margin: 0 0.3px;
        }

        .current-balance-card .peak-balance {
            padding: 4px 8px;
        }

        .current-balance-card .peak-balance .peak-label {
            font-size: 0.5rem;
        }

        .current-balance-card .peak-balance .peak-value {
            font-size: 0.8rem;
        }
    }

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

        .current-balance-card .card-value-row,
        .pnl-card .card-value-row {
            gap: 6px;
        }

        .current-balance-card .card-value,
        .pnl-card .card-value {
            font-size: 1.4rem;
        }

        .current-balance-card .card-value .value-amount,
        .pnl-card .card-value .value-amount {
            font-size: 1.4rem;
        }

        .current-balance-card .chart-bars-wrapper,
        .pnl-card .chart-bars-wrapper {
            height: 28px;
            gap: 0.5px;
        }

        .current-balance-card .chart-bar-item,
        .pnl-card .chart-bar-item {
            max-width: 5px;
            min-width: 1.5px;
            margin: 0 0.2px;
        }

        .current-balance-card .peak-balance {
            padding: 3px 6px;
            border-radius: 6px;
        }

        .current-balance-card .peak-balance .peak-label {
            font-size: 0.45rem;
            letter-spacing: 0.5px;
        }

        .current-balance-card .peak-balance .peak-value {
            font-size: 0.7rem;
        }

        .current-balance-card .peak-balance .peak-value .peak-currency {
            font-size: 0.6rem;
        }
    }

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

        .current-balance-card .chart-bars-wrapper,
        .pnl-card .chart-bars-wrapper {
            height: 24px;
            gap: 0.5px;
        }

        .current-balance-card .chart-bar-item,
        .pnl-card .chart-bar-item {
            max-width: 4px;
            min-width: 1px;
            margin: 0 0.15px;
        }
    }

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

        .current-balance-card .chart-bars-wrapper,
        .pnl-card .chart-bars-wrapper {
            height: 26px;
            gap: 0.5px;
        }

        .current-balance-card .chart-bar-item,
        .pnl-card .chart-bar-item {
            max-width: 5px;
            min-width: 1.5px;
            margin: 0 0.2px;
        }
    }

    @media (orientation: portrait) {
        .chart-bars-wrapper {
            height: 35px;
        }

        .chart-bar-item {
            max-width: 5px;
            min-width: 2px;
        }

        .current-balance-card .chart-bars-wrapper,
        .pnl-card .chart-bars-wrapper {
            height: 30px;
        }

        .current-balance-card .chart-bar-item,
        .pnl-card .chart-bar-item {
            max-width: 5px;
            min-width: 2px;
        }
    }

    @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
        .chart-bar {
            border-radius: 0.5px 0.5px 0 0;
        }
    }

    @media (prefers-color-scheme: dark) {
        .chart-bar {
            background: var(--border-color);
        }
    }

    /* ============================================================
       MYDASHBOARD CONTAINER & ALL DASHBOARD ELEMENTS
       ============================================================ */
    .mydashboard-box {
        max-width: 1100px;
        margin: 0 auto;
        padding: 0px 20px;
    }

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

    .disclaimer-icon {
        font-size: 1.2rem;
    }

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

    .warning-icon {
        font-size: 1.2rem;
    }

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

    .encourage-icon {
        font-size: 1.2rem;
    }

    .account-header {
        margin-top: 10px;
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

    .balance-toggle-form {
        flex-shrink: 0;
    }

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

    .balance-toggle-btn:active {
        transform: scale(0.95);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }

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

    .profit-positive .value-amount {
        color: var(--success);
    }

    .profit-negative .value-amount {
        color: var(--danger);
    }

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

    .balance-card .card-value.status-unverified .value-amount {
        color: var(--warning);
    }

    .balance-card .card-value.status-pending .value-amount {
        color: var(--info);
    }

    .balance-card .card-value.status-failed .value-amount {
        color: var(--danger);
    }

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

    .btn-revenue-history:active {
        transform: scale(0.98);
    }

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

    .status-indicator.active {
        background: var(--success);
    }

    .status-indicator.completed {
        background: var(--info);
    }

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

    .btn-action:active {
        transform: scale(0.98);
    }

    .btn-action:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-reset {
        background: linear-gradient(135deg, var(--accent), var(--accent-hover));
        color: #fff;
    }

    .btn-apply {
        background: linear-gradient(135deg, var(--accent), var(--accent-hover));
        color: #fff;
    }

    .btn-loyalty-action {
        background: linear-gradient(135deg, var(--accent), var(--accent-hover));
        color: #fff;
    }

    .btn-loyalty-paid {
        background: var(--bg);
        color: var(--text-muted);
        cursor: not-allowed;
    }

    .btn-loyalty-confirmed {
        background: linear-gradient(135deg, var(--accent), var(--accent-hover));
        cursor: default;
        color: #fff;
    }

    .btn-loyalty-failed {
        background: var(--danger-bg);
        color: var(--danger);
        border: 2px solid var(--danger);
    }

    .btn-loyalty-deposit {
        background: linear-gradient(135deg, var(--accent), var(--accent-hover));
        color: #fff;
    }

    /* ============================================================
       MODAL TITLE / DESCRIPTION / FIELD HELPERS
       ============================================================ */
    .modal-title-accent {
        color: var(--accent);
        margin-bottom: 12px;
    }

    .modal-description {
        margin: 1rem 0;
        opacity: 0.85;
        color: var(--text-secondary);
        line-height: 1.6;
    }

    .modal-field {
        margin-bottom: 12px;
    }

    .field-hint {
        font-size: 0.75rem;
        opacity: 0.7;
        margin-top: 4px;
        color: var(--text-muted);
    }

    .modal-actions-column {
        flex-direction: column;
        gap: 10px;
    }

    /* ============================================================
       RESPONSIVE FOR MODALS
       ============================================================ */
    @media (max-width: 768px) {
        #reenrollModal.modal .modal-content {
            padding: 24px 20px;
            margin: 12px;
            max-height: 90vh;
        }

        #reenrollModal.modal .modal-content h2 {
            font-size: 1.25rem;
        }

        #reenrollModal .reenroll-instructions {
            padding: 14px 16px;
        }

        #reenrollModal .reenroll-instructions li {
            font-size: 0.84rem;
            padding-left: 22px;
        }

        #reenrollModal .checkbox-container-legal {
            padding: 12px 14px;
        }

        #reenrollModal .checkbox-container-legal label {
            font-size: 0.84rem;
        }

        #reenrollModal .reenroll-confirm-btn {
            padding: 13px 18px;
            font-size: 0.9rem;
        }
    }

    @media (max-width: 400px) {
        #reenrollModal.modal .modal-content {
            padding: 20px 16px;
        }

        #reenrollModal.modal .modal-content h2 {
            font-size: 1.1rem;
        }

        #reenrollModal.modal .modal-content > p {
            font-size: 0.85rem;
        }

        #reenrollModal .reenroll-instructions h4 {
            font-size: 0.72rem;
        }

        #reenrollModal .reenroll-instructions li {
            font-size: 0.8rem;
        }

        #reenrollModal .consequence-note {
            font-size: 0.78rem;
            padding: 10px 12px;
        }
    }

    @media (max-width: 768px) {
        .mydashboard-container {
            padding: 16px 12px;
        }

        .account-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }

        .account-info {
            flex-wrap: wrap;
        }

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
        margin-top: 10px;
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
        transition: all 0.2s ease;
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
    /* ============================================================
       MENU PAGE STYLES
       ============================================================ */
    body {
        padding-top: calc(56px + env(safe-area-inset-top, 0px)) !important;
        padding-bottom: 60px;
        background: var(--bg);
        color: var(--text);
        margin: 0;
        transition: background 0.3s, color 0.3s;
    }
    @media (max-width: 480px) {
        body {
            padding-top: calc(52px + env(safe-area-inset-top, 0px)) !important;
        }
    }

    .menu-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 0 15px;
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
       PAGE VIEW SYSTEM (menu <-> profile)
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

    /* ============================================================
       HERO ROW: back arrow pinned to the LEFT edge,
       avatar stays centered.
       ============================================================ */
    .profile-page-hero {
        width: 100%;
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 28px;
        min-height: 110px;
    }

    .profile-page-hero .profile-page-back {
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
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

    .profile-page-hero .profile-page-back:hover {
        background: rgba(255,255,255,0.1);
        transform: translateY(-50%) scale(1.05);
    }

    .profile-page-hero .profile-page-back:active {
        transform: translateY(-50%) scale(0.95);
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

    /* ============================================================
       PROFILE SECTIONS
       ============================================================ */
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

    /* ============================================================
       USERNAME EDIT
       ============================================================ */
    .profile-page-btn.profile-page-btn-secondary {
        background: transparent;
        color: var(--accent, #2e8b57);
        border: 1px solid var(--accent, #2e8b57);
        box-shadow: none;
        margin-top: 12px;
        padding: 12px 18px;
        font-size: 14px;
    }
    .profile-page-btn.profile-page-btn-secondary:hover {
        background: rgba(46, 139, 87, 0.08);
        box-shadow: none;
        color: var(--accent, #2e8b57);
    }

    .profile-username-edit {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--glass-border, rgba(255,255,255,0.08));
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .profile-username-edit-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        color: var(--text-muted, #888);
    }

    .profile-username-edit-input {
        width: 100%;
        box-sizing: border-box;
        padding: 12px 14px;
        border-radius: 10px;
        border: 1px solid var(--glass-border, rgba(255,255,255,0.12));
        background: var(--bg, #0f0f1a);
        color: var(--text, #fff);
        font-size: 15px;
        outline: none;
        transition: border-color 0.2s ease;
    }
    .profile-username-edit-input:focus {
        border-color: var(--accent, #2e8b57);
    }

    .profile-username-edit-hint {
        font-size: 12px;
        color: var(--text-muted, #888);
    }

    .profile-username-edit-actions {
        display: flex;
        gap: 10px;
        margin-top: 4px;
    }
    .profile-username-edit-actions .profile-page-btn {
        flex: 1;
        justify-content: center;
        margin-top: 0;
    }

    .profile-username-edit-msg {
        padding: 10px 12px;
        border-radius: 8px;
        font-size: 13px;
        line-height: 1.4;
    }
    .profile-username-edit-msg.error {
        background: rgba(231, 76, 60, 0.12);
        color: #e74c3c;
    }
    .profile-username-edit-msg.success {
        background: rgba(46, 204, 113, 0.12);
        color: #2ecc71;
    }

    /* ============================================================
       TIER LIMIT
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

    /* ============================================================
       ACTION BUTTONS
       ============================================================ */
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
       MODALS
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
        z-index: 10000;
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

    .modal-box .modal-title-accent {
        color: var(--accent, #2e8b57);
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
        background: var(--accent, #2e8b57);
        color: white;
    }

    .btn-danger-confirm:hover {
        background: #3a9b67;
    }

    /* ============================================================
       MOBILE RESPONSIVE
       ============================================================ */
    @media (max-width: 480px) {
        .menu-container {
            padding: 0 12px;
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

        .profile-page-hero {
            min-height: 90px;
            margin-bottom: 22px;
        }

        .profile-page-hero .profile-page-back {
            width: 40px;
            height: 40px;
            font-size: 16px;
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
    .activi-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 0 15px;
    }

    .activity-header {
        margin-top: 10px;
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
        margin-top: 10px;
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

    /*
       Theme follows the application's .dark-mode class, not the operating
       system preference. useranalytics.php initializes and synchronizes this
       class with the active account and the parent page's live theme.
    */
    body.dark-mode,
    html.dark-mode body {
        background: var(--bg);
        color: var(--text);
    }

    body.dark-mode .analytics-container .stat-card,
    body.dark-mode .analytics-container .section-card,
    body.dark-mode .analytics-container .trade-stat-box,
    body.dark-mode .analytics-container .chart-container,
    body.dark-mode .analytics-container .empty-state,
    html.dark-mode body .analytics-container .stat-card,
    html.dark-mode body .analytics-container .section-card,
    html.dark-mode body .analytics-container .trade-stat-box,
    html.dark-mode body .analytics-container .chart-container,
    html.dark-mode body .analytics-container .empty-state {
        background: rgba(255,255,255,0.045);
        color: var(--text);
        border-color: var(--glass-border);
    }

    body.dark-mode .analytics-header .user-info,
    body.dark-mode .analytics-container .symbol-row,
    html.dark-mode body .analytics-header .user-info,
    html.dark-mode body .analytics-container .symbol-row {
        background: rgba(255,255,255,0.035);
        color: var(--text);
    }

    body.dark-mode .analytics-container .stat-label,
    body.dark-mode .analytics-container .stat-sub,
    body.dark-mode .analytics-container .section-grid,
    body.dark-mode .analytics-container .trade-stat-count,
    body.dark-mode .analytics-container .loss-label,
    body.dark-mode .analytics-header p,
    body.dark-mode .analytics-header .user-info span,
    html.dark-mode body .analytics-container .stat-label,
    html.dark-mode body .analytics-container .stat-sub,
    html.dark-mode body .analytics-container .section-grid,
    html.dark-mode body .analytics-container .trade-stat-count,
    html.dark-mode body .analytics-container .loss-label,
    html.dark-mode body .analytics-header p,
    html.dark-mode body .analytics-header .user-info span {
        color: var(--text-muted);
    }

    body.dark-mode .analytics-container .stat-value,
    body.dark-mode .analytics-container .trade-stat-value,
    body.dark-mode .analytics-container .symbol-name,
    body.dark-mode .analytics-container .section-grid strong,
    body.dark-mode .analytics-header .user-info strong,
    html.dark-mode body .analytics-container .stat-value,
    html.dark-mode body .analytics-container .trade-stat-value,
    html.dark-mode body .analytics-container .symbol-name,
    html.dark-mode body .analytics-container .section-grid strong,
    html.dark-mode body .analytics-header .user-info strong {
        color: var(--text);
    }

    body.dark-mode .pnl-chart,
    html.dark-mode body .pnl-chart {
        background: transparent;
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
        z-index: 800;
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
        background: var(--bg);
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
        padding-bottom: 60px; /* Space for fixed bottom nav */
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
    CONNECT / DISCONNECT BROKER STYLES
    Mirrors the VPS page layout:
        - body owns nothing (no padding, no margin, no scroll)
        - .broker-page-wrapper is 100dvh and owns the layout
        - .broker-sticky-top is the fixed top header (arrow left, title center)
        - .broker-scroll-area is the only element that scrolls,
        and it owns all top/bottom padding
    ============================================================ */

    /* ---------- BODY RESET — kill all padding/margins/scroll ---------- */
    body.broker-page-body {
        margin: 0 !important;
        padding: 0 !important;
        height: 100vh !important;
        height: 100dvh !important;
        min-height: 0 !important;
        overflow: hidden !important;
        background: var(--bg, #ffffff);
        color: var(--text, #222);
        font-family: var(--font-family, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
        -webkit-font-smoothing: antialiased;
    }

    /* Prevent iOS zoom on inputs */
    @media (max-width: 768px) {
        body.broker-page-body input,
        body.broker-page-body select,
        body.broker-page-body textarea {
            font-size: 16px !important;
        }
    }

    /* ---------- PAGE WRAPPER — 100dvh flex column ---------- */
    .broker-page-wrapper {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: var(--bg, #ffffff);
        color: var(--text, #222);
    }

    /* ---------- FIXED HEADER (sticky, does not move) ---------- */
    .broker-sticky-top {
        flex: 0 0 auto;
        position: relative;
        z-index: 10;
        background: var(--bg, #ffffff);
        border-bottom: 1px solid var(--border-color, #e0e0e0);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    body.dark-mode .broker-sticky-top {
        background: var(--bg, #000);
        border-bottom-color: var(--border-color, #333);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
    }

    /* Inner topbar: arrow pinned left, title centered */
    .broker-topbar {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 56px;
        padding: calc(env(safe-area-inset-top, 0px) + 8px) 16px 8px;
        box-sizing: border-box;
        max-width: 720px;
        margin: 0 auto;
        width: 100%;
    }

    .broker-topbar-back {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: var(--bg-card, #f5f5f5);
        border: 1px solid var(--border-color, #e0e0e0);
        color: var(--text, #222);
        text-decoration: none;
        cursor: pointer;
        font-size: 0.95rem;
        transition: all 0.2s ease;
        padding: 0;
    }
    .broker-topbar-back:hover {
        background: var(--accent, #2e8b57);
        color: #fff;
        border-color: var(--accent, #2e8b57);
        transform: translateY(-50%) translateX(-2px);
    }
    .broker-topbar-back:active {
        transform: translateY(-50%) scale(0.95);
    }

    body.dark-mode .broker-topbar-back {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }
    body.dark-mode .broker-topbar-back:hover {
        background: var(--accent, #2e8b57);
        color: #fff;
        border-color: var(--accent, #2e8b57);
    }

    .broker-topbar-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text, #222);
        margin: 0;
        letter-spacing: -0.3px;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: calc(100% - 120px);
        line-height: 1.2;
    }

    body.dark-mode .broker-topbar-title {
        color: var(--text, #eee);
    }

    @media (max-width: 480px) {
        .broker-topbar {
            min-height: 52px;
            padding: calc(env(safe-area-inset-top, 0px) + 6px) 12px 6px;
        }
        .broker-topbar-back {
            left: 12px;
            width: 34px;
            height: 34px;
            font-size: 0.85rem;
        }
        .broker-topbar-title {
            font-size: 1.25rem;
            max-width: calc(100% - 100px);
        }
    }

    /* ---------- INTERNAL SCROLL AREA — the only scroller ---------- */
    .broker-scroll-area {
        flex: 1 1 auto;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        padding: 16px 20px 24px;
        box-sizing: border-box;
        width: 100%;
        max-width: 720px;
        margin: 0 auto;
    }

    @media (max-width: 480px) {
        .broker-scroll-area {
            padding: 14px 12px 20px;
        }
    }

    /* ---------- USER INFO ---------- */
    .broker-user-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: 12px;
        padding: 12px 16px;
        margin-bottom: 16px;
    }

    .broker-user-info-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }

    .broker-user-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted, #888);
        font-weight: 600;
    }

    .broker-user-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text, #222);
        word-break: break-word;
    }

    .broker-status-badge {
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        padding: 3px 12px;
        border-radius: 20px;
    }
    .broker-status-badge.approved { background: rgba(40,167,69,0.15); color: #28a745; }
    .broker-status-badge.pending  { background: rgba(255,193,7,0.15); color: #ffc107; }
    .broker-status-badge.declined,
    .broker-status-badge.suspended { background: rgba(220,53,69,0.15); color: #dc3545; }

    /* ---------- MESSAGES ---------- */
    .broker-error-message {
        background: rgba(220,53,69,0.1);
        color: #dc3545;
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 0.85rem;
        margin-bottom: 16px;
        line-height: 1.5;
    }

    .broker-success-message {
        background: rgba(40,167,69,0.1);
        color: #28a745;
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 0.85rem;
        margin-bottom: 16px;
        line-height: 1.5;
    }

    /* ---------- SECTION TITLE ---------- */
    .broker-section-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--text-secondary, #555);
        margin: 16px 0 12px;
        text-align: left;
        letter-spacing: 0.2px;
    }

    /* ---------- BROKER LIST (name left, link right) ---------- */
    .broker-selection-grid {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-bottom: 16px;
        transition: all 0.3s ease;
    }
    .broker-selection-grid.hidden {
        display: none !important;
    }

    .broker-selection-item {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: 10px;
        padding: 12px 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        transition: border-color 0.2s ease, transform 0.15s ease;
    }
    .broker-selection-item:hover {
        border-color: var(--accent, #2e8b57);
        transform: translateY(-1px);
    }

    .broker-name {
        font-weight: 600;
        color: var(--text, #222);
        font-size: 0.95rem;
        flex: 1 1 auto;
        min-width: 0;
        word-break: break-word;
    }

    .broker-action {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
    }

    .broker-link-btn {
        padding: 6px 14px;
        background: var(--accent, #2e8b57);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        white-space: nowrap;
        transition: background 0.2s ease, transform 0.15s ease;
    }
    .broker-link-btn:hover {
        background: var(--accent-hover, #3cb371);
        transform: scale(1.03);
    }
    .broker-link-btn:active {
        transform: scale(0.97);
    }

    .broker-link-btn.no-link {
        background: var(--text-muted, #888);
        cursor: not-allowed;
        opacity: 0.6;
    }
    .broker-link-btn.no-link:hover {
        transform: none;
        background: var(--text-muted, #888);
    }

    /* ---------- BUTTONS ---------- */
    .broker-btn-secondary {
        padding: 12px 28px;
        background: var(--bg-card, #fff);
        color: var(--text, #222);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.15s ease;
        display: inline-block;
    }
    .broker-btn-secondary:hover {
        background: var(--border-color, #e0e0e0);
        transform: scale(1.02);
    }
    .broker-btn-secondary:active {
        transform: scale(0.98);
    }

    .broker-connect-wrapper {
        text-align: center;
        margin: 16px 0;
    }
    .broker-connect-wrapper.hidden {
        display: none !important;
    }

    .broker-btn-submit {
        width: 100%;
        padding: 14px;
        background: var(--accent, #2e8b57);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.15s ease;
        text-align: center;
    }
    .broker-btn-submit:hover {
        background: var(--accent-hover, #3cb371);
        transform: scale(1.01);
    }
    .broker-btn-submit:active {
        transform: scale(0.98);
    }
    .broker-btn-submit:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    /* ---------- FORM ---------- */
    .broker-divider {
        border: none;
        border-top: 1px solid var(--border-color, #e0e0e0);
        margin: 20px 0;
    }

    .broker-form-group {
        margin-bottom: 14px;
    }

    .broker-form-group label {
        display: block;
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--text-secondary, #555);
        margin-bottom: 5px;
    }

    .broker-form-group select,
    .broker-form-group input {
        width: 100%;
        padding: 12px 14px;
        border-radius: 8px;
        border: 1px solid var(--border-color, #e0e0e0);
        background: var(--bg-card, #fff);
        color: var(--text, #222);
        font-size: 16px !important; /* prevents iOS zoom */
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        -webkit-appearance: none;
        appearance: none;
        box-sizing: border-box;
        -webkit-text-size-adjust: 100%;
    }

    .broker-form-group select:focus,
    .broker-form-group input:focus {
        outline: none;
        border-color: var(--accent, #2e8b57);
        box-shadow: 0 0 0 3px rgba(46,139,87,0.1);
    }

    .broker-form-group select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        padding-right: 36px;
    }

    .broker-password-wrapper {
        position: relative;
    }

    .broker-password-wrapper input {
        padding-right: 60px;
    }

    .broker-password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        font-size: 0.75rem;
        color: var(--text-muted, #888);
        background: transparent;
        border: none;
        padding: 4px 8px;
        border-radius: 4px;
        user-select: none;
        font-weight: 600;
        z-index: 5;
    }
    .broker-password-toggle:hover {
        color: var(--accent, #2e8b57);
    }

    #connectForm {
        transition: all 0.3s ease;
    }
    #connectForm.hidden {
        display: none !important;
    }

    /* ---------- INFO NOTE ---------- */
    .broker-info-note {
        font-size: 0.75rem;
        color: var(--text-muted, #888);
        text-align: center;
        margin-top: 16px;
        padding: 12px;
        background: var(--bg-card, #fff);
        border-radius: 8px;
        border: 1px solid var(--border-color, #e0e0e0);
        line-height: 1.5;
    }
    .broker-info-note strong {
        color: var(--text-secondary, #555);
    }

    /* ---------- RESPONSIVE ---------- */
    @media (max-width: 480px) {
        .broker-selection-item {
            padding: 11px 12px;
        }
        .broker-link-btn {
            padding: 6px 10px;
            font-size: 0.72rem;
        }
        .broker-btn-submit {
            padding: 12px;
            font-size: 0.92rem;
        }
        .broker-btn-secondary {
            width: 100%;
            padding: 12px;
        }
        .broker-user-info {
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
    }

    /* ---------- DARK MODE ---------- */
    body.dark-mode.broker-page-body {
        background: var(--bg, #000);
    }

    body.dark-mode .broker-page-wrapper {
        background: var(--bg, #000);
    }

    body.dark-mode .broker-user-info,
    body.dark-mode .broker-selection-item,
    body.dark-mode .broker-info-note {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .broker-name,
    body.dark-mode .broker-user-value {
        color: var(--text, #eee);
    }

    body.dark-mode .broker-btn-secondary {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }
    body.dark-mode .broker-btn-secondary:hover {
        background: var(--border-color, #444);
    }

    body.dark-mode .broker-form-group select,
    body.dark-mode .broker-form-group input {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }

    body.dark-mode .broker-divider {
        border-top-color: var(--border-color, #333);
    }
</style>



<style>
    /* ============================================================
       VPS PAGE — SCOPED LAYOUT
       ============================================================ */

    body.vps-page-body {
        padding: 0 !important;
        margin: 0 !important;
        height: 100vh !important;
        height: 100dvh !important;
        min-height: 0 !important;
        overflow: hidden !important;
        background: var(--bg, #ffffff);
        color: var(--text, #222);
        font-family: var(--font-family, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
    }

    @media (max-width: 768px) {
        body.vps-page-body input,
        body.vps-page-body select,
        body.vps-page-body textarea {
            font-size: 16px !important;
        }
    }
    /* ============================================================
       HIDE HORIZONTAL SCROLLBAR ON PROGRAMME VPS TABS
       (mirrors the .sd-inv-tabs behaviour on the signals dashboard)
       ============================================================ */

    /* Firefox */
    .vps-tabs {
        scrollbar-width: none;
        -ms-overflow-style: none;      /* IE / legacy Edge */
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
    }

    /* Chrome / Safari / new Edge */
    .vps-tabs::-webkit-scrollbar {
        display: none;
        width: 0;
        height: 0;
    }

    /* Extra safety: hide any scrollbar rendered by the inner tabs */
    .vps-tabs::-webkit-scrollbar-track,
    .vps-tabs::-webkit-scrollbar-thumb,
    .vps-tabs::-webkit-scrollbar-corner {
        display: none;
        background: transparent;
    }
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

    .vps-page-wrapper {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: var(--bg, #ffffff);
        color: var(--text, #222);
        font-family: var(--font-family, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
    }

    /* ============================================================
       STICKY TOP GROUP — topbar + search + tabs
       ============================================================ */
    .vps-sticky-top {
        flex: 0 0 auto;
        background: var(--bg, #ffffff);
        z-index: 10;
        transform: translateY(0);
        transition: transform 0.3s ease, opacity 0.3s ease;
        opacity: 1;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    body.dark-mode .vps-sticky-top {
        background: var(--bg, #000);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
    }

    .vps-sticky-top.is-hidden {
        transform: translateY(-100%);
        opacity: 0;
        pointer-events: none;
    }

    .vps-sticky-top > .vps-topbar,
    .vps-sticky-top > .vps-search-wrap,
    .vps-sticky-top > .vps-tabs {
        max-width: 720px;
        margin-left: auto;
        margin-right: auto;
        width: 100%;
        box-sizing: border-box;
    }

    /* ============================================================
       TOPBAR
       ============================================================ */
    .vps-topbar {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 56px;
        padding: calc(env(safe-area-inset-top, 0px) + 8px) 16px 8px;
        box-sizing: border-box;
        border-bottom: 1px solid var(--border-color, #e0e0e0);
    }

    body.dark-mode .vps-topbar {
        border-bottom-color: var(--border-color, #333);
    }

    .vps-topbar-back {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: var(--bg-card, #f5f5f5);
        border: 1px solid var(--border-color, #e0e0e0);
        color: var(--text, #222);
        text-decoration: none;
        cursor: pointer;
        font-size: 0.95rem;
        transition: all 0.2s ease;
        padding: 0;
    }

    .vps-topbar-back:hover {
        background: var(--accent, #2e8b57);
        color: #fff;
        border-color: var(--accent, #2e8b57);
        transform: translateY(-50%) translateX(-2px);
    }

    body.dark-mode .vps-topbar-back {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }

    body.dark-mode .vps-topbar-back:hover {
        background: var(--accent, #2e8b57);
        color: #fff;
        border-color: var(--accent, #2e8b57);
    }

    .vps-topbar-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text, #222);
        margin: 0;
        letter-spacing: -0.3px;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: calc(100% - 120px);
        line-height: 1.2;
    }

    body.dark-mode .vps-topbar-title {
        color: var(--text, #eee);
    }

    @media (max-width: 480px) {
        .vps-topbar {
            min-height: 52px;
            padding: calc(env(safe-area-inset-top, 0px) + 6px) 12px 6px;
        }

        .vps-topbar-back {
            left: 12px;
            width: 34px;
            height: 34px;
            font-size: 0.85rem;
        }

        .vps-topbar-title {
            font-size: 1.25rem;
            max-width: calc(100% - 100px);
        }
    }

    /* ============================================================
       SEARCH
       ============================================================ */
    .vps-search-wrap {
        position: relative;
        padding: 12px 20px 4px;
    }

    .vps-search-input {
        width: 100%;
        box-sizing: border-box;
        padding: 12px 16px;
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        background: var(--bg-card, #fff);
        color: var(--text, #222);
        font-size: 16px !important;
        font-family: inherit;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        -webkit-appearance: none;
        appearance: none;
    }

    .vps-search-input:focus {
        outline: none;
        border-color: var(--accent, #2e8b57);
        box-shadow: 0 0 0 3px rgba(46, 139, 87, 0.1);
    }

    .vps-search-input::placeholder {
        color: var(--text-muted, #888);
    }

    body.dark-mode .vps-search-input {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }

    /* ============================================================
       TABS
       ============================================================ */
    .vps-tabs {
        display: flex;
        align-items: stretch;
        gap: var(--spacing-xs, 8px);
        border-bottom: 1px solid var(--border-color, #e0e0e0);
        padding: 8px 20px 0;
        overflow-x: auto;
        overflow-y: hidden;
        flex-wrap: nowrap;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: rgba(127, 127, 127, 0.35) transparent;
    }

    .vps-tabs::-webkit-scrollbar {
        height: 4px;
    }
    .vps-tabs::-webkit-scrollbar-track {
        background: transparent;
    }
    .vps-tabs::-webkit-scrollbar-thumb {
        background: rgba(127, 127, 127, 0.35);
        border-radius: 4px;
    }

    .vps-tabs.vps-tabs-hidden,
    .vps-tabs:not(:has(.vps-tab:nth-child(2))) {
        display: none !important;
    }
    /* ============================================================
    LINKABLE VPS CARD — theme-aware
    ============================================================ */
    .vps-link-card {
        background: var(--bg, #f5f5f5);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        padding: var(--spacing-md, 16px);
        margin-bottom: var(--spacing-md, 16px);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm, 12px);
        transition: all 0.2s ease;
    }

    .vps-link-card:hover {
        border-color: var(--accent, #2e8b57);
    }

    .vps-link-note {
        font-size: 0.9rem;
        color: var(--text, #222);
        line-height: 1.5;
        margin: 0;
        font-weight: 500;
    }

    .vps-link-note strong {
        color: var(--accent, #2e8b57);
        font-weight: 700;
    }

    .vps-btn-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        align-self: flex-start;
        padding: var(--spacing-xs, 8px) var(--spacing-md, 20px);
        background: var(--accent, #2e8b57);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm, 8px);
        font-size: 0.85rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .vps-btn-link:hover {
        background: var(--accent-hover, #3cb371);
        transform: scale(1.02);
    }

    .vps-btn-link:active {
        transform: scale(0.98);
    }

    .vps-btn-link:disabled {
        background: var(--text-muted, #888);
        cursor: not-allowed;
        opacity: 0.6;
        transform: none;
    }

    .vps-btn-link i {
        font-size: 0.8rem;
    }

    /* Dark mode overrides */
    body.dark-mode .vps-link-card {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .vps-link-card:hover {
        border-color: var(--accent, #2e8b57);
    }

    body.dark-mode .vps-link-note {
        color: var(--text, #eee);
    }

    body.dark-mode .vps-link-note strong {
        color: var(--accent, #2e8b57);
    }

    /* Mobile adjustments */
    @media (max-width: 480px) {
        .vps-link-card {
            padding: var(--spacing-sm, 12px);
        }

        .vps-link-note {
            font-size: 0.85rem;
        }

        .vps-btn-link {
            width: 100%;
            align-self: stretch;
        }
    }
    .vps-tab {
        flex: 1 1 0;
        min-width: max-content;
        background: transparent;
        border: none;
        padding: var(--spacing-sm, 12px) var(--spacing-md, 16px);
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-muted, #888);
        cursor: pointer;
        border-bottom: 2px solid transparent;
        transition: color 0.2s ease, border-color 0.2s ease;
        font-family: inherit;
        margin-bottom: -1px;
        white-space: nowrap;
        line-height: 1;
        text-align: center;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .vps-tab:hover {
        color: var(--text, #222);
    }

    .vps-tab.active {
        color: var(--accent, #2e8b57);
        border-bottom-color: var(--accent, #2e8b57);
    }

    /* Counter badge next to a tab label */
    .vps-tab-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 999px;
        background: #e74c3c;
        color: #fff;
        font-size: 0.65rem;
        font-weight: 700;
        line-height: 1;
        letter-spacing: 0;
    }

    .vps-tab.active .vps-tab-badge {
        background: var(--accent, #2e8b57);
        color: #fff;
    }

    @media (max-width: 480px) {
        .vps-search-wrap {
            padding: 10px 12px 4px;
        }

        .vps-tabs {
            padding: 6px 12px 0;
            gap: 4px;
        }

        .vps-tab {
            font-size: 0.78rem;
            padding: var(--spacing-sm, 10px) 12px;
        }
    }

    /* ============================================================
       SCROLL AREA
       ============================================================ */
    .vps-scroll-area {
        flex: 1 1 auto;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        padding: 16px 20px 24px;
        box-sizing: border-box;
    }

    @media (max-width: 480px) {
        .vps-scroll-area {
            padding: 14px 12px 20px;
        }
    }

    /* ============================================================
       TAB CONTENT
       ============================================================ */
    .vps-tab-content {
        display: none;
    }

    .vps-tab-content.active {
        display: block;
    }

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

    /* ============================================================
       HOSTS LIST
       ============================================================ */
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

    /* ============================================================
       BUTTONS
       ============================================================ */
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

    /* ============================================================
       MODALS
       ============================================================ */
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

    body.dark-mode .vps-request-item {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .vps-request-select {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }

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
       CONFIRM MODAL
       ============================================================ */
    #vpsConfirmModal .vps-modal-title { color: #e74c3c; }
    #vpsConfirmModal .vps-btn-confirm { background: #e74c3c; }
    #vpsConfirmModal .vps-btn-confirm:hover { background: #c0392b; }
    #vpsConfirmModal .vps-btn-confirm:disabled {
        background: #e74c3c;
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* ============================================================
       FOLLOWER LOCK NOTICE
       ============================================================ */
    .vps-follower-lock {
        background: rgba(243, 156, 18, 0.1) !important;
    }

    .vps-follower-lock strong {
        display: block;
        margin-bottom: 4px;
        color: #f39c12;
        font-size: 13px;
    }

    .vps-btn-request.disabled[title="You are already a follower of a VPS"] {
        background: var(--text-muted, #888);
        cursor: not-allowed;
        opacity: 0.55;
    }

    /* ============================================================
       OWNER CARD (My Space view)
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
        color: var(--text, #222);
    }
    .dd-notice-warning {
        background: rgba(243, 156, 18, 0.1);
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
            /* programmes.php — external page styles */

        body.prog-page-body {

                padding: 0 !important;

                margin: 0 !important;

                height: 100vh !important;

                height: 100dvh !important;

                min-height: 0 !important;

                overflow: hidden !important;

                background: var(--bg, #ffffff);

                color: var(--text, #222);

                font-family: var(--font-family, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);

            }

            @media (max-width: 768px) {

                body.prog-page-body input,

                body.prog-page-body select,

                body.prog-page-body textarea {

                    font-size: 16px !important;

                }

            }

            .prog-page-wrapper {

                position: relative;

                width: 100%;

                height: 100%;

                overflow: hidden;

                background: var(--bg, #ffffff);

                color: var(--text, #222);

                font-family: var(--font-family, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);

            }

            /* ============================================================

            FIXED TOP GROUP

            ============================================================ */

            .prog-sticky-top {

                position: absolute;

                top: 0;

                left: 0;

                right: 0;

                background: var(--bg, #ffffff);

                z-index: 10;

                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);

            }

            body.dark-mode .prog-sticky-top {

                background: var(--bg, #000);

                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);

            }

            /* ============================================================

            LISTING HEADER

            ============================================================ */

            .prog-list-header {

                position: relative;

                display: flex;

                align-items: center;

                justify-content: center;

                min-height: 56px;

                padding: 10px 16px;

                box-sizing: border-box;

                border-bottom: 1px solid var(--border-color, #e0e0e0);

            }

            body.dark-mode .prog-list-header {

                border-bottom-color: var(--border-color, #333);

            }

            .prog-list-header .prog-list-back {

                position: absolute;

                left: 16px;

                top: 50%;

                transform: translateY(-50%);

                background: transparent;

                border: none;

                color: var(--text, #222);

                font-size: 1.2rem;

                cursor: pointer;

                padding: 8px;

                width: 40px;

                height: 40px;

                display: flex;

                align-items: center;

                justify-content: center;

                border-radius: 8px;

                transition: background 0.2s ease;

            }

            .prog-list-header .prog-list-back:hover { background: rgba(0, 0, 0, 0.06); }

            body.dark-mode .prog-list-header .prog-list-back { color: var(--text, #eee); }

            body.dark-mode .prog-list-header .prog-list-back:hover { background: rgba(255, 255, 255, 0.08); }

            .prog-list-header .prog-list-title {

                font-size: 1.5rem;

                font-weight: 700;

                color: var(--text, #222);

                margin: 0;

                letter-spacing: -0.3px;

                text-align: center;

                white-space: nowrap;

                overflow: hidden;

                text-overflow: ellipsis;

                max-width: calc(100% - 120px);

                line-height: 1.2;

            }

            body.dark-mode .prog-list-header .prog-list-title { color: var(--text, #eee); }

            /* ============================================================

            TABS — share width equally among the tabs actually present

            ============================================================ */

            .prog-tabs {

                display: flex;

                border-bottom: 1px solid var(--border-color, #e0e0e0);

                padding: 0;

                overflow-x: auto;

                overflow-y: hidden;

                flex-wrap: nowrap;

                -webkit-overflow-scrolling: touch;

                scrollbar-width: thin;

                scrollbar-color: rgba(127, 127, 127, 0.35) transparent;

            }

            body.dark-mode .prog-tabs { border-bottom-color: var(--border-color, #333); }

            .prog-tabs::-webkit-scrollbar { height: 4px; }

            .prog-tabs::-webkit-scrollbar-track { background: transparent; }

            .prog-tabs::-webkit-scrollbar-thumb {

                background: rgba(127, 127, 127, 0.35);

                border-radius: 4px;

            }

            .prog-tab {

                flex: 1 1 0;

                min-width: max-content;

                background: transparent;

                border: none;

                padding: 14px 8px;

                font-size: 0.9rem;

                font-weight: 600;

                color: var(--text-muted, #888);

                cursor: pointer;

                border-bottom: 2px solid transparent;

                transition: all 0.2s;

                font-family: inherit;

                margin-bottom: -1px;

                white-space: nowrap;

                line-height: 1;

                text-align: center;

                box-sizing: border-box;

                display: inline-flex;

                align-items: center;

                justify-content: center;

                gap: 6px;

            }

            .prog-tab:hover { color: var(--text, #222); }

            body.dark-mode .prog-tab:hover { color: var(--text, #eee); }

            .prog-tab.active {

                color: var(--accent, #2e8b57);

                border-bottom-color: var(--accent, #2e8b57);

            }

            .prog-tab-badge {

                display: inline-flex;

                align-items: center;

                justify-content: center;

                min-width: 18px;

                height: 18px;

                padding: 0 5px;

                border-radius: 999px;

                background: #e74c3c;

                color: #fff;

                font-size: 0.65rem;

                font-weight: 700;

                line-height: 1;

            }

            .prog-tab.active .prog-tab-badge { background: var(--accent, #2e8b57); }

            /* ============================================================

            SEARCH

            ============================================================ */

            .prog-search-wrap {

                position: relative;

                padding: 12px 20px 16px;

                box-sizing: border-box;

            }

            .prog-search-input {

                width: 100%;

                box-sizing: border-box;

                padding: 12px 16px;

                border: 1px solid var(--border-color, #e0e0e0);

                border-radius: var(--radius-sm, 8px);

                background: var(--bg-card, #fff);

                color: var(--text, #222);

                font-size: 16px !important;

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

            .prog-search-input::placeholder { color: var(--text-muted, #888); }

            body.dark-mode .prog-search-input {

                background: var(--bg-card, #1e1e2a);

                border-color: var(--border-color, #333);

                color: var(--text, #eee);

            }

            /* ============================================================

            SCROLL AREA

            ============================================================ */

            .prog-scroll-area {

                position: absolute;

                inset: 0;

                overflow-y: auto;

                overflow-x: hidden;

                -webkit-overflow-scrolling: touch;

                padding: 185px 20px 24px;

                box-sizing: border-box;

            }

            body.prog-tab-invested .prog-scroll-area { padding-top: 111px; }

            @media (max-width: 480px) {

                .prog-scroll-area { padding: 175px 12px 20px; }

                body.prog-tab-invested .prog-scroll-area { padding-top: 101px; }

            }

            .prog-tab-content { display: none; }

            .prog-tab-content.active { display: block; }

            #progTabInvested { padding-top: 0; }

            /* ============================================================

            EMPTY STATE

            ============================================================ */

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

            }

            .prog-empty-state p { font-size: 0.95rem; margin: 0; }

            /* ============================================================

            PROGRAMMES LIST

            ============================================================ */

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

            .prog-item-main { display: flex; flex-direction: column; gap: 8px; width: 100%; }

            .prog-item-header {

                display: flex;

                justify-content: space-between;

                align-items: flex-start;

                gap: var(--spacing-md, 16px);

                width: 100%;

            }

            .prog-item-name {

                font-weight: 700;

                color: var(--text, #222);

                font-size: 1rem;

                word-break: break-word;

                flex: 1;

                min-width: 0;

            }

            .prog-item-meta {

                display: flex;

                gap: var(--spacing-lg, 24px);

                flex-wrap: wrap;

            }

            .prog-item-stat { display: flex; flex-direction: column; gap: 2px; }

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

            .prog-item-details {

                display: flex;

                flex-wrap: wrap;

                gap: 8px 16px;

                margin-top: 4px;

            }

            .prog-detail-item {

                font-size: 0.75rem;

                color: var(--text-muted, #888);

                background: var(--bg, #f5f5f5);

                padding: 3px 8px;

                border-radius: 4px;

                white-space: nowrap;

            }

            body.dark-mode .prog-detail-item { background: rgba(255,255,255,0.06); }

            /* ============================================================

            BUTTONS

            ============================================================ */

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

                padding: 6px 16px;

                background: transparent;

                color: var(--accent, #2e8b57);

                border: 1px solid var(--accent, #2e8b57);

                flex-shrink: 0;

            }

            .prog-btn-view:hover { background: rgba(46, 139, 87, 0.08); transform: scale(1.02); }

            .prog-btn-view:active { transform: scale(0.98); }

            .prog-btn-invest {

                padding: 8px 24px;

                background: var(--accent, #2e8b57);

                color: #fff;

                border: none;

            }

            .prog-btn-invest:hover { background: var(--accent-hover, #3cb371); transform: scale(1.02); }

            .prog-btn-invest:active { transform: scale(0.98); }

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

            .prog-btn-cancel:hover { background: #e74c3c; color: #fff; }

            .prog-btn-cancel:active { transform: scale(0.98); }

            .prog-btn-confirm {

                padding: var(--spacing-sm, 10px) var(--spacing-md, 20px);

                background: var(--accent, #2e8b57);

                color: #fff;

                border: none;

                width: 100%;

            }

            .prog-btn-confirm:hover { background: var(--accent-hover, #3cb371); }

            .prog-btn-confirm:disabled { opacity: 0.5; cursor: not-allowed; }

            .prog-modal-close {

                padding: var(--spacing-sm, 10px) var(--spacing-md, 20px);

                background: var(--bg, #f5f5f5);

                color: var(--text, #222);

                border: 1px solid var(--border-color, #e0e0e0);

                width: 100%;

            }

            .prog-modal-close:hover { background: var(--border-color, #e0e0e0); }

            body.dark-mode .prog-modal-close {

                background: var(--bg, #2a2a3a);

                border-color: var(--border-color, #333);

                color: var(--text, #eee);

            }

            body.dark-mode .prog-modal-close:hover { background: var(--border-color, #444); }

            /* ============================================================

            INVESTED CARD

            ============================================================ */

            .prog-invested-card {

                background: var(--bg-card, #fff);

                border: 1px solid var(--border-color, #e0e0e0);

                border-radius: var(--radius-sm, 8px);

                padding: var(--spacing-md, 16px);

            }

            body.dark-mode .prog-invested-card {

                background: var(--bg-card, #1e1e2a);

                border-color: var(--border-color, #333);

            }

            .prog-detail-header {

                text-align: center;

                margin-bottom: var(--spacing-md, 16px);

                padding-bottom: var(--spacing-sm, 12px);

                border-bottom: 1px solid var(--border-color, #e0e0e0);

            }

            body.dark-mode .prog-detail-header { border-color: var(--border-color, #333); }

            .prog-detail-name {

                font-size: 1.25rem;

                font-weight: 700;

                color: var(--text, #222);

                margin-bottom: 6px;

                word-break: break-word;

            }

            body.dark-mode .prog-detail-name { color: var(--text, #eee); }

            .prog-detail-dev { font-size: 0.9rem; color: var(--text-muted, #888); }

            .prog-detail-dev strong { color: var(--text, #222); font-weight: 600; }

            body.dark-mode .prog-detail-dev strong { color: var(--text, #eee); }

            .prog-detail-section-title {

                font-size: 0.75rem;

                text-transform: uppercase;

                letter-spacing: 0.6px;

                color: var(--text-muted, #888);

                font-weight: 700;

                margin: var(--spacing-md, 16px) 0 var(--spacing-sm, 10px) 0;

                padding-bottom: 6px;

                border-bottom: 1px solid var(--border-color, #e0e0e0);

            }

            body.dark-mode .prog-detail-section-title { border-color: var(--border-color, #333); }

            .prog-detail-section-title:first-of-type { margin-top: 0; }

            .prog-detail-grid { display: flex; flex-direction: column; gap: 4px; }

            .prog-detail-grid-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; }

            .prog-detail-row {

                display: flex;

                justify-content: space-between;

                align-items: center;

                gap: var(--spacing-sm, 12px);

                padding: 8px 0;

                border-bottom: 1px solid var(--border-color, rgba(0, 0, 0, 0.04));

            }

            .prog-detail-row:last-child { border-bottom: none; }

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

            body.dark-mode .prog-detail-value { color: var(--text, #eee); }

            /* ============================================================

            MODALS

            ============================================================ */

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

            .prog-modal.active { display: flex; }

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

            .prog-modal-wide { max-width: 500px; }

            @keyframes progModalSlideIn {

                from { opacity: 0; transform: scale(0.96) translateY(16px); }

                to   { opacity: 1; transform: scale(1) translateY(0); }

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

            .prog-modal-body { margin-bottom: var(--spacing-md, 20px); }

            .prog-modal-loading,

            .prog-modal-error {

                text-align: center;

                padding: var(--spacing-md, 20px) 0;

                color: var(--text-muted, #888);

                font-size: 0.9rem;

            }

            .prog-modal-error { color: var(--danger, #e74c3c); }

            .prog-modal-actions { display: flex; flex-direction: column; gap: var(--spacing-sm, 10px); }

            .prog-alert-text {

                font-size: 0.95rem;

                line-height: 1.6;

                color: var(--text, #222);

                margin: 0;

                text-align: center;

                white-space: pre-line;

            }

            /* ============================================================

            FULL-PAGE TAKEOVER — #progDetailModal

            ============================================================ */

            #progDetailModal {

                padding: 0;

                background: var(--bg, #f5f5f5);

                backdrop-filter: none;

                -webkit-backdrop-filter: none;

                display: none;

                flex-direction: column;

                align-items: stretch;

                justify-content: flex-start;

            }

            #progDetailModal.active { display: flex; }

            #progDetailModal .prog-modal-content {

                background: var(--bg, #f5f5f5);

                border: none;

                border-radius: 0;

                padding: 0;

                margin: 0;

                max-width: 100%;

                width: 100%;

                height: 100%;

                max-height: 100%;

                overflow-y: auto;

                overflow-x: hidden;

                -webkit-overflow-scrolling: touch;

                box-shadow: none;

                animation: none;

                display: block;

            }

            .prog-view-header {

                position: sticky;

                top: 0;

                left: 0;

                right: 0;

                z-index: 100;

                display: flex;

                align-items: center;

                justify-content: center;

                padding: 14px 16px;

                background: var(--bg-card, #fff);

                border-bottom: 1px solid var(--border-color, #e0e0e0);

                min-height: 56px;

                box-sizing: border-box;

                margin: 0;

            }

            body.dark-mode .prog-view-header {

                background: var(--bg-card, #1e1e2a);

                border-color: var(--border-color, #333);

            }

            .prog-view-header .prog-view-back {

                position: absolute;

                left: 16px;

                top: 50%;

                transform: translateY(-50%);

                background: transparent;

                border: none;

                color: var(--text, #222);

                font-size: 1.25rem;

                cursor: pointer;

                padding: 8px;

                display: flex;

                align-items: center;

                justify-content: center;

                border-radius: 8px;

                transition: background 0.2s ease;

            }

            .prog-view-header .prog-view-back:hover { background: rgba(0,0,0,0.06); }

            body.dark-mode .prog-view-header .prog-view-back:hover { background: rgba(255,255,255,0.08); }

            .prog-view-header .prog-view-title {

                font-size: 1rem;

                font-weight: 700;

                color: var(--accent, #2e8b57);

                margin: 0;

                text-align: center;

                max-width: calc(100% - 100px);

                white-space: nowrap;

                overflow: hidden;

                text-overflow: ellipsis;

            }

            .prog-view-body {

                padding: 20px;

                margin: 0 auto;

                max-width: 720px;

                width: 100%;

                box-sizing: border-box;

            }

            .prog-view-actions {

                padding: 0 20px 60px;

                margin: 0 auto;

                max-width: 720px;

                width: 100%;

                box-sizing: border-box;

                display: flex;

                flex-direction: column;

                gap: var(--spacing-sm, 10px);

            }

            body.prog-detail-view-open { overflow: hidden; }

            body.prog-detail-view-open .prog-page-wrapper { display: none; }

            /* ============================================================

            DARK MODE OVERRIDES

            ============================================================ */

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

            /* ============================================================

            RESPONSIVE

            ============================================================ */

            @media (max-width: 768px) {

                .prog-search-wrap { padding: 10px 12px 14px; }

                .prog-tab { font-size: 0.85rem; padding: 12px 6px; }

                .prog-detail-grid-2col { grid-template-columns: 1fr; }

            }

            @media (max-width: 480px) {

                .prog-list-header { min-height: 52px; padding: 8px 12px; }

                .prog-list-header .prog-list-back { left: 12px; width: 34px; height: 34px; font-size: 1.05rem; }

                .prog-list-header .prog-list-title { font-size: 1.25rem; max-width: calc(100% - 100px); }

                .prog-tab { font-size: 0.8rem; padding: 12px 4px; }

                .prog-item { padding: 14px 12px; }

                .prog-invested-card { padding: 14px 12px; }

                .prog-detail-name { font-size: 1.15rem; }

                .prog-detail-row { flex-direction: column; align-items: flex-start; gap: 4px; }

                .prog-detail-value { text-align: left; }

                .prog-modal-content { padding: var(--spacing-md, 20px) var(--spacing-sm, 16px); }

                .prog-view-header { padding: 12px 14px; }

                .prog-view-header .prog-view-back { left: 10px; }

                .prog-view-header .prog-view-title { max-width: calc(100% - 90px); }

                .prog-view-body { padding: 16px 14px; }

                .prog-view-actions { padding: 0 14px 60px; }

            }

        /* Investor-facing Signals Provision Request visual language */

        :root{

            --spr-bg:var(--bg,#f4f8f7);--spr-card:var(--bg-card,#fff);--spr-text:var(--text,#12211d);

            --spr-muted:var(--text-muted,#60736d);--spr-accent:var(--accent,#12a36b);--spr-accent-2:var(--accent-hover,#0b7d52);

            --spr-soft:var(--accent-light,#e3f6ee);--spr-border:var(--border-color,#dfe9e5);

            --spr-danger:var(--danger,#df4e4e);--spr-warning:var(--warning,#b87513);

            --spr-success:var(--success,#2ecc71);--spr-info:var(--info,#3498db);

            --spr-shadow:var(--shadow,0 2px 12px rgba(18,33,29,.06));--spr-radius:var(--radius,16px);

        }

        body.dark-mode{

            --spr-bg:#0c1311;--spr-card:#131d1a;--spr-text:#e8f2ee;--spr-muted:#93a8a1;

            --spr-soft:#12271f;--spr-border:#22322d;--spr-shadow:0 10px 30px rgba(0,0,0,.4);

        }

        .spr-item{background:var(--spr-bg);border:1px solid var(--spr-border);border-radius:var(--spr-radius);padding:16px 20px;display:flex;flex-direction:column;align-items:stretch;justify-content:flex-start;gap:12px;width:100%;min-width:0;box-shadow:var(--spr-shadow)}

        .spr-item-main{display:flex;flex-direction:column;gap:6px;flex:0 0 auto;width:100%;min-width:0}

        .spr-item-header{display:flex;flex-direction:column;gap:2px}

        .spr-item-title{font-size:1rem;font-weight:800;color:var(--spr-text);word-break:break-word;line-height:1.25}

        .spr-item-sub{font-size:.78rem;color:var(--spr-muted)}

        .spr-challenge-begun-indicator{margin-top:3px;font-size:.72rem;font-weight:800;color:var(--spr-info)}

        .spr-duration-provision{margin-top:5px;max-width:850px;color:var(--spr-muted);font-size:.72rem;line-height:1.5}

        .spr-meta-row{display:flex;flex-wrap:wrap;align-items:baseline;gap:8px 22px;margin-top:2px;width:100%}

        .spr-meta-item{display:flex;align-items:baseline;gap:5px;font-size:.84rem;line-height:1.4;white-space:normal;flex:0 1 auto;max-width:100%;word-break:break-word}

        .spr-meta-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.4px;color:var(--spr-muted);font-weight:700;white-space:nowrap}

        .spr-meta-value{font-size:.88rem;font-weight:700;color:var(--spr-text);word-break:break-word}

        .spr-progress-list{display:grid;gap:10px;width:100%;margin-top:9px}

        .spr-progress-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:7px 10px;align-items:center;width:100%}

        .spr-progress-heading{grid-column:1/-1;display:flex;align-items:baseline;justify-content:space-between;gap:12px;min-width:0}

        .spr-progress-label{color:var(--spr-muted);font-size:.71rem;font-weight:800;letter-spacing:.025em}

        .spr-progress-count{color:var(--spr-text);font-size:.75rem;font-weight:850;font-variant-numeric:tabular-nums;white-space:nowrap}

        .spr-progress-track{height:9px;min-width:0;width:100%;border-radius:999px;overflow:hidden;background:rgba(120,135,145,.18);position:relative}

        .spr-progress-fill{height:100%;width:0;border-radius:999px;background:var(--spr-info);transition:width .35s ease,background .25s ease}

        .spr-progress-fill.is-complete{background:#16a36b}

        .spr-progress-loss-track{background:#159b68}

        .spr-progress-loss-fill{height:100%;width:0;max-width:100%;border-radius:999px;background:#e14d59;transition:width .35s ease}

        .spr-progress-mark{width:19px;height:19px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:900;color:#fff;background:#16a36b;box-shadow:0 0 0 2px rgba(22,163,107,.14)}

        .spr-progress-mark.is-failed{background:#e14d59;box-shadow:0 0 0 2px rgba(225,77,89,.14)}

        .spr-progress-note{grid-column:1/-1;color:var(--spr-muted);font-size:.65rem;margin-top:-3px}

        .spr-progress-readonly{grid-column:1/-1;color:var(--spr-info);font-size:.64rem;font-weight:750;margin-top:2px}

        .spr-period-nav{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;padding:8px 0 10px;border-bottom:1px solid var(--spr-border);margin:3px 0 7px}

        .spr-period-nav-btn{width:34px;height:34px;flex:0 0 34px;border:1px solid var(--spr-border);border-radius:10px;color:var(--spr-text);background:var(--spr-card);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem}

        .spr-period-nav-btn:disabled{opacity:.28;cursor:not-allowed}

        .spr-period-nav-title{flex:1;min-width:0;text-align:center;font-size:.94rem;font-weight:900;color:var(--spr-text)}

        .spr-period-nav-range{display:block;font-size:.67rem;font-weight:600;color:var(--spr-muted);margin-top:1px}

        .spr-progress-lock{color:var(--spr-muted);font-size:.82rem}

        .spr-restricted-list{margin-top:8px;padding:9px 10px;border-radius:9px;background:rgba(120,135,145,.08);color:var(--spr-muted);font-size:.69rem}

        .spr-restricted-list strong{display:block;color:var(--spr-text);font-size:.7rem;margin-bottom:4px}

        .spr-restricted-list ul{margin-left:16px;display:grid;gap:2px}

        .spr-restricted-list>div+div{margin-top:4px}

        .spr-challenge-failed{padding:10px 12px;margin-top:10px;border:1px solid rgba(223,78,78,.35);border-radius:10px;color:var(--spr-danger);font-size:.79rem;font-weight:800}

        .prog-investor-provision{width:100%;display:flex;flex-direction:column;gap:12px;margin:12px 0 16px}

        .prog-investor-provision-card{width:100%;}

        .spr-month-checkpoint-list{display:grid;gap:12px;width:100%;}

        .spr-month-checkpoint{padding:11px 12px 12px;border:1px solid var(--spr-border);border-radius:11px;background:var(--spr-card);}

        .spr-month-checkpoint+.spr-month-checkpoint{margin-top:0;}

        .spr-checkpoint-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;padding-bottom:7px;border-bottom:1px solid var(--spr-border);}

        .spr-checkpoint-head strong{font-size:.78rem;font-weight:850;color:var(--spr-text);}

        .spr-checkpoint-head span{font-size:.68rem;color:var(--spr-muted);}

        .spr-provision-no-checkpoint{padding:17px 13px;border:1px dashed var(--spr-border);border-radius:10px;color:var(--spr-muted);font-size:.77rem;text-align:center;background:rgba(120,135,145,.04);}

        .prog-see-analytics{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;min-height:42px;padding:10px 16px;border:1px solid var(--accent,var(--spr-accent));border-radius:9px;background:transparent;color:var(--accent,var(--spr-accent));font-family:inherit;font-size:.86rem;font-weight:800;cursor:pointer;transition:background .18s ease,transform .15s ease;}

        .prog-see-analytics:hover{background:rgba(18,163,107,.08);}

        .prog-see-analytics:active{transform:scale(.99);}

        .prog-investor-provision .prog-detail-section-title{margin-top:3px;}

        .prog-analytics-modal .prog-analytics-modal-content{width:min(1180px,calc(100vw - 20px));max-width:1180px;height:min(94vh,1000px);height:min(94dvh,1000px);max-height:94dvh;min-height:0;padding:0;display:flex;flex-direction:column;overflow:hidden;}

        .prog-analytics-header{flex:0 0 auto;z-index:2;}

        .prog-analytics-body{flex:1 1 auto;min-height:0;overflow-y:auto;overflow-x:hidden;margin:0;padding:0 0 18px;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;}

        .prog-analytics-body>.pa-panel{margin:18px;}

        .prog-analytics-body .pa-page{max-width:1280px;margin:0 auto;padding:0 18px 50px;}

        .prog-analytics-modal .pa-page{line-height:1.5;}

        .prog-analytics-modal .pa-symbol-modal{z-index:100020;}

        .prog-analytics-modal .pa-locate-failure-modal{z-index:100021;}

        @media(max-width:650px){

            .spr-item{padding:14px 12px;}

            .spr-period-nav{gap:8px;}

            .spr-month-checkpoint{padding:10px;}

            .prog-analytics-modal .prog-analytics-modal-content{width:100vw;max-width:100vw;height:100dvh;max-height:100dvh;border-radius:0;}

            .prog-analytics-body .pa-page{padding:0 12px 40px;}

        }

        /* Programme Analytics dashboard styles (same templates as programme_analytics.php) */

        :root{--pa-bg:var(--bg,#f4f8f7);--pa-card:var(--bg-card,#fff);--pa-text:var(--text,#14231e);--pa-muted:var(--text-muted,#6c7c76);--pa-accent:var(--accent,#12a36b);--pa-border:var(--border-color,#dfe9e5);--pa-shadow:var(--shadow,0 2px 14px rgba(12,35,27,.06));--pa-red:#dd5862;--pa-green:#119b68;--pa-blue:#4295d2;--pa-amber:#c58b2d}

        @media(prefers-color-scheme:dark){html:not(.force-light){--pa-bg:#0b1310;--pa-card:#131e1a;--pa-text:#e9f2ed;--pa-muted:#94aaa0;--pa-border:#263831;--pa-shadow:0 10px 28px rgba(0,0,0,.28)}}

        *{box-sizing:border-box}

        .prog-analytics-modal .pa-page{color:var(--pa-text);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;line-height:1.5}

        body.dark-mode{--pa-bg:#0b1310;--pa-card:#131e1a;--pa-text:#e9f2ed;--pa-muted:#94aaa0;--pa-border:#263831;--pa-shadow:0 10px 28px rgba(0,0,0,.28)}

        .prog-analytics-body::-webkit-scrollbar{display:none}

        .pa-page{width:100%;max-width:1280px;margin:0 auto 0;padding:0 18px 100px}

        .pa-control-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:12px}

        .pa-root-row{grid-template-columns:minmax(0,1fr);}

        .pa-confirmation-row{grid-template-columns:minmax(0,1fr);margin:0 0 10px}

        .pa-scoped-control-row{margin:0 0 13px}

        .pa-confirmation-control{min-width:0}

        .pa-control{min-width:0}

        .pa-control label{display:block;font-size:.68rem;font-weight:800;color:var(--pa-muted);letter-spacing:.055em;text-transform:uppercase;margin:0 0 5px}

        .pa-control select,.pa-symbol-button{width:100%;height:42px;min-height:42px;max-height:42px;border:1px solid var(--pa-border);border-radius:11px;background:var(--pa-card);color:var(--pa-text);padding:8px 10px;font:inherit;font-size:.82rem;line-height:1.2;outline:none}

        .pa-control select:focus,.pa-symbol-button:focus{border-color:var(--pa-accent)}

        .pa-symbol-button{text-align:left;cursor:pointer;display:flex;align-items:center}

        .pa-tabs{display:flex;gap:7px;flex-wrap:wrap;margin:4px 0 13px}

        .pa-source-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0;width:100%;border:1px solid var(--pa-border);border-radius:11px;overflow:hidden;background:var(--pa-card); margin-bottom: 10px;}

        .pa-source-tab{width:100%;min-width:0;border:0;border-right:1px solid var(--pa-border);padding:11px 12px;background:transparent;color:var(--pa-muted);font:inherit;font-size:.82rem;font-weight:850;cursor:pointer}

        .pa-source-tab:last-child{border-right:0}

        .pa-source-tab.active{background:var(--pa-accent);color:#fff}

        .pa-source-tab:focus-visible{outline:2px solid var(--pa-blue);outline-offset:-3px}

        .pa-tab{border:1px solid var(--pa-border);border-radius:9px;padding:8px 13px;background:transparent;color:var(--pa-text);font:inherit;font-size:.79rem;font-weight:800;cursor:pointer}

        .pa-tab.active{background:var(--pa-accent);border-color:var(--pa-accent);color:#fff}

        .pa-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}

        .pa-card{min-width:0;background:var(--pa-card);border:1px solid var(--pa-border);border-radius:14px;box-shadow:var(--pa-shadow);padding:13px 14px}

        .pa-metric{min-height:114px;display:flex;flex-direction:column;justify-content:space-between}

        .pa-metric-top{display:flex;justify-content:space-between;align-items:center;gap:8px}

        .pa-label{font-size:.72rem;color:var(--pa-muted);font-weight:750}

        .pa-icon{width:28px;height:28px;border-radius:9px;display:grid;place-items:center;background:color-mix(in srgb,var(--pa-accent) 12%,transparent);color:var(--pa-accent);font-size:.77rem}

        .pa-value{font-size:1.42rem;font-weight:850;letter-spacing:-.5px;line-height:1.1;margin-top:9px;font-variant-numeric:tabular-nums}

        .pa-detail{font-size:.68rem;color:var(--pa-muted);margin-top:6px}

        .pa-action{border:0;background:none;color:var(--pa-accent);font:inherit;font-size:.69rem;font-weight:800;padding:0;margin-top:8px;cursor:pointer;text-align:left}

        .pa-action:hover{text-decoration:underline}

        .positive{color:var(--pa-green)}

        .negative{color:var(--pa-red)}

        .pa-panel{border:1px solid var(--pa-border);border-radius:15px;background:var(--pa-card);box-shadow:var(--pa-shadow);overflow:hidden}

        .pa-empty{padding:30px 18px;text-align:center;color:var(--pa-muted);font-size:.82rem}

        .pa-empty i{display:block;font-size:1.45rem;color:var(--pa-accent);margin-bottom:10px}

        .pa-empty strong{display:block;color:var(--pa-text);font-size:.91rem;margin-bottom:4px}

        .pa-section-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:0 0 10px}

        .pa-section-title{font-size:.9rem;font-weight:850}

        .pa-section-sub{font-size:.7rem;color:var(--pa-muted)}

        .pa-family-list{display:grid;gap:9px}

        .pa-family{padding:12px 13px}

        .pa-family-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}

        .pa-family-title{font-size:.79rem;font-weight:850}

        .pa-family-meta{font-size:.67rem;color:var(--pa-muted);margin-top:3px}

        .pa-status{display:inline-flex;align-items:center;padding:3px 8px;border-radius:999px;font-size:.64rem;font-weight:850;text-transform:capitalize;white-space:nowrap;background:color-mix(in srgb,var(--pa-blue) 12%,transparent);color:var(--pa-blue)}

        .pa-status.stoploss-hit{background:color-mix(in srgb,var(--pa-red) 13%,transparent);color:var(--pa-red)}

        .pa-status.take-profit-reached{background:color-mix(in srgb,var(--pa-green) 13%,transparent);color:var(--pa-green)}

        .pa-status.in-trade{background:color-mix(in srgb,var(--pa-blue) 13%,transparent);color:var(--pa-blue)}

        .pa-status.pending-entry{background:color-mix(in srgb,var(--pa-amber) 14%,transparent);color:var(--pa-amber)}

        .pa-trade-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:11px}

        .pa-trade-cell{min-width:0}

        .pa-trade-cell span{display:block;color:var(--pa-muted);font-size:.63rem}

        .pa-trade-cell b{display:block;font-size:.73rem;margin-top:2px;overflow-wrap:anywhere;font-variant-numeric:tabular-nums}

        .pa-family-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:10px}

        .pa-locate{border:1px solid color-mix(in srgb,var(--pa-accent) 45%,var(--pa-border));border-radius:9px;background:color-mix(in srgb,var(--pa-accent) 8%,transparent);color:var(--pa-accent);font:inherit;font-size:.71rem;font-weight:850;padding:7px 10px;cursor:pointer}

        .pa-symbol-modal{position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;padding:16px;overscroll-behavior:contain}

        .pa-locate-failure-modal{position:fixed;inset:0;z-index:100001;background:rgba(0,0,0,.58);display:flex;align-items:center;justify-content:center;padding:18px}.pa-locate-failure-card{width:min(390px,100%);background:var(--pa-card);color:var(--pa-text);border:1px solid var(--pa-border);border-radius:16px;padding:22px;box-shadow:var(--pa-shadow);text-align:center}.pa-locate-failure-title{font-size:.95rem;font-weight:850;margin:0 0 16px}.pa-locate-failure-ok{border:0;border-radius:9px;background:var(--pa-accent);color:#fff;font:inherit;font-size:.78rem;font-weight:850;padding:9px 22px;cursor:pointer}

        .pa-modal-card{width:min(440px,100%);height:60vh;max-height:60vh;min-height:min(240px,60vh);display:flex;flex-direction:column;overflow:hidden;overscroll-behavior:contain;background:var(--pa-card);color:var(--pa-text);border:1px solid var(--pa-border);border-radius:16px;padding:14px;box-shadow:var(--pa-shadow)}

        .pa-modal-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px;flex:0 0 auto;font-weight:850}

        .pa-modal-close{border:0;background:transparent;color:var(--pa-text);font-size:1.3rem;cursor:pointer}

        .pa-symbol-list{display:grid;align-content:start;gap:7px;flex:1 1 auto;min-height:0;max-height:none;overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;touch-action:pan-y;padding:1px 2px}

        .pa-symbol-item{display:flex;align-items:center;gap:9px;padding:8px;border:1px solid var(--pa-border);border-radius:9px;font-size:.83rem}

        .pa-symbol-item input{accent-color:var(--pa-accent)}

        .pa-modal-actions{display:flex;justify-content:space-between;gap:7px;margin-top:10px;flex:0 0 auto}

        .pa-modal-actions button,.pa-back{border:1px solid var(--pa-border);border-radius:9px;padding:8px 12px;background:var(--pa-bg);color:var(--pa-text);cursor:pointer;font:inherit;font-size:.77rem;font-weight:800}

        .pa-symbol-scope{margin:13px 0 16px}

        .pa-symbol-scope-title{font-size:.83rem;font-weight:850;margin-bottom:7px}

        .pa-symbol-note{font-size:.73rem;color:var(--pa-muted);padding:8px 0}

        .pa-isolated-head{display:flex;align-items:center;gap:12px;margin:0 0 15px}

        .pa-isolated-head h2{font-size:.98rem;margin:0}

        .pa-isolated-head p{font-size:.7rem;color:var(--pa-muted);margin:2px 0 0}

        .pa-period-list{display:grid;gap:8px}

        .pa-period{display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%;border:1px solid var(--pa-border);border-radius:12px;background:var(--pa-card);color:var(--pa-text);padding:13px 14px;text-align:left;font:inherit;cursor:pointer}

        .pa-period strong{font-size:.81rem}

        .pa-period span{color:var(--pa-muted);font-size:.7rem}

        .pa-hidden{display:none!important}.pa-no-data{min-height:calc(100vh - 20px);display:flex;align-items:center;justify-content:center;text-align:center;font-size:1rem;font-weight:800;color:var(--pa-muted)}

        .pa-foot{font-size:.65rem;color:var(--pa-muted);margin:12px 2px 0}

        @media(max-width:900px){.pa-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}

        @media(max-width:650px){.pa-page{padding:0 12px 88px;margin-top:10px}.pa-control-row{grid-template-columns:1fr 1fr}.pa-root-row{grid-template-columns:1fr;}.pa-control-row.pa-confirmation-row{grid-template-columns:1fr;}.pa-control-root{grid-column:1/-1}.pa-confirmation-control{grid-column:1/-1}.pa-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.pa-card{padding:11px}.pa-metric{min-height:110px}.pa-value{font-size:1.27rem}.pa-trade-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.pa-section-title{font-size:.84rem}.pa-modal-card{padding:12px}.pa-modal-actions{gap:6px}.pa-modal-actions button{flex:1 1 0;padding:8px 7px;font-size:.7rem}}

        /* Preserve scroll behaviour while keeping all scrollbar tracks hidden. */

        .prog-page-wrapper,

        .prog-page-wrapper *,

        .prog-modal,

        .prog-modal * {

            scrollbar-width: none !important;

            -ms-overflow-style: none !important;

        }

        .prog-page-wrapper *::-webkit-scrollbar,

        .prog-modal *::-webkit-scrollbar,

        .prog-page-wrapper::-webkit-scrollbar,

        .prog-modal::-webkit-scrollbar {

            display: none !important;

            width: 0 !important;

            height: 0 !important;

        }

        .prog-investor-provision-requirements { margin-top: 2px; }


    /* ========================================================================
    RESPONSIVE PAGE SHELL — layout is flow-based, not spacer-based.
    The header remains fixed in the page shell while only the content pane
    scrolls. This removes the old hard-coded 185px / 111px spacer dependency.
    ======================================================================== */
    html,
    body.prog-page-body {
        width: 100%;
        min-width: 0;
        height: 100%;
        min-height: 100%;
    }

    body.prog-page-body {
        position: relative !important;
        height: 100vh !important;
        height: 100dvh !important;
        min-height: 0 !important;
        overflow: hidden !important;
        overscroll-behavior: none;
        -webkit-text-size-adjust: 100%;
    }

    body.prog-page-body,
    body.prog-page-body * {
        box-sizing: border-box;
    }

    .prog-page-wrapper {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch;
        width: 100%;
        height: 100%;
        min-width: 0;
        min-height: 0;
        overflow: hidden;
        isolation: isolate;
    }

    .prog-sticky-top {
        position: relative !important;
        inset: auto !important;
        z-index: 20;
        flex: 0 0 auto;
        width: 100%;
        min-width: 0;
    }

    .prog-scroll-area,
    body.prog-tab-invested .prog-scroll-area {
        position: relative !important;
        inset: auto !important;
        flex: 1 1 auto;
        width: 100%;
        min-width: 0;
        min-height: 0;
        height: auto;
        padding: 16px 20px calc(24px + env(safe-area-inset-bottom, 0px)) !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        touch-action: pan-y;
    }

    .prog-tab-content,
    .prog-list,
    .prog-item,
    .prog-item-main,
    .prog-invested-card,
    .prog-requests-list,
    .prog-request-item,
    .prog-detail-grid,
    .prog-investor-provision,
    .prog-investor-provision-card,
    .prog-analytics-body,
    .pa-page,
    .pa-panel,
    .pa-family,
    .pa-period {
        min-width: 0;
        max-width: 100%;
    }

    .prog-list,
    .prog-requests-list {
        width: 100%;
    }

    .prog-item {
        width: 100%;
        overflow-wrap: anywhere;
    }

    .prog-item-header {
        align-items: flex-start;
        min-width: 0;
    }

    .prog-item-name {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .prog-item-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex: 0 0 auto;
        max-width: 100%;
    }

    .prog-item-meta,
    .prog-item-details,
    .spr-meta-row,
    .prog-request-row {
        min-width: 0;
        max-width: 100%;
    }

    .prog-detail-grid-2col {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .prog-detail-row {
        min-width: 0;
    }

    .prog-detail-label,
    .prog-detail-value,
    .prog-request-value,
    .spr-meta-label,
    .spr-meta-value {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .prog-detail-label {
        white-space: normal;
    }

    .prog-detail-value {
        max-width: 60%;
    }

    .prog-empty-state {
        width: 100%;
        min-width: 0;
    }

    /* Provision card: keep the supplied Signals Provision visual language while
    allowing long names, dates and requirement labels to wrap on narrow screens. */
    .prog-investor-provision {
        width: 100%;
        min-width: 0;
    }

    .prog-investor-provision-card,
    .spr-item {
        width: 100%;
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .spr-item-header,
    .spr-checkpoint-head,
    .spr-period-nav,
    .spr-progress-heading {
        min-width: 0;
    }

    .spr-period-nav-title {
        overflow-wrap: anywhere;
    }

    .spr-month-checkpoint-list,
    .spr-progress-list,
    .spr-restricted-list {
        width: 100%;
        min-width: 0;
    }

    .prog-investor-provision-requirements {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: start;
        gap: 10px 16px;
    }

    .prog-investor-provision-requirements .spr-meta-item {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 4px 6px;
        min-width: 0;
    }

    /* Modal sizing is based on the visual viewport and remains internally scrollable. */
    .prog-modal {
        padding: clamp(8px, 2vw, 20px);
        padding-bottom: max(8px, env(safe-area-inset-bottom, 0px));
        overscroll-behavior: contain;
    }

    .prog-modal-content {
        width: min(100%, 520px);
        max-width: 520px;
        max-height: min(90vh, 900px);
        max-height: min(90dvh, 900px);
        min-width: 0;
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }

    #progDetailModal {
        width: 100%;
        height: 100vh;
        height: 100dvh;
        max-height: none;
        overflow: hidden;
    }

    #progDetailModal .prog-modal-content {
        width: 100%;
        max-width: none;
        min-width: 0;
        height: 100%;
        max-height: 100%;
        min-height: 0;
        overflow-x: hidden;
        overflow-y: auto;
        padding-bottom: env(safe-area-inset-bottom, 0px);
    }

    .prog-view-header {
        min-width: 0;
    }

    .prog-view-title {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .prog-view-body,
    .prog-view-actions {
        min-width: 0;
        width: 100%;
    }

    /* Programme Analytics dashboard: controls, scorecards and trade cards all
    shrink into the available modal width without horizontal page overflow. */
    .prog-analytics-modal {
        padding: 10px;
    }

    .prog-analytics-modal .prog-analytics-modal-content {
        display: flex;
        flex-direction: column;
        width: min(100%, 1280px);
        max-width: 1280px;
        height: min(94vh, 1000px);
        height: min(94dvh, 1000px);
        max-height: 100%;
        min-height: 0;
        min-width: 0;
        overflow: hidden;
        padding: 0;
    }

    .prog-analytics-header {
        flex: 0 0 auto;
    }

    .prog-analytics-body {
        flex: 1 1 auto;
        min-width: 0;
        min-height: 0;
        width: 100%;
        margin: 0 !important;
        padding: 0 0 calc(18px + env(safe-area-inset-bottom, 0px)) !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }

    .prog-analytics-body .pa-page {
        width: 100%;
        max-width: 1280px;
        min-width: 0;
        margin: 0 auto;
        padding: 14px 18px 48px;
        overflow-wrap: anywhere;
    }

    .pa-control-row {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .pa-control,
    .pa-control select,
    .pa-symbol-button,
    .pa-card,
    .pa-metric,
    .pa-trade-cell,
    .pa-family,
    .pa-period,
    .pa-modal-card {
        min-width: 0;
        max-width: 100%;
    }

    .pa-control select,
    .pa-symbol-button {
        width: 100%;
    }

    .pa-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .pa-value,
    .pa-family-title,
    .pa-trade-cell b,
    .pa-period strong,
    .pa-period span {
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .pa-family-head {
        min-width: 0;
        flex-wrap: wrap;
    }

    .pa-trade-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .pa-period {
        min-width: 0;
        flex-wrap: wrap;
    }

    .pa-period span {
        text-align: right;
    }

    .pa-modal-card {
        width: min(440px, 100%);
        max-height: min(80vh, 700px);
        max-height: min(80dvh, 700px);
    }

    /* Tablet */
    @media (max-width: 900px) {
        .prog-scroll-area { padding-right: 16px !important; padding-left: 16px !important; }
        .prog-analytics-modal .prog-analytics-modal-content { width: min(100%, 900px); }
        .pa-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .pa-trade-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    /* Phones and narrow embedded app panes */
    @media (max-width: 650px) {
        .prog-list-header { min-height: 52px; padding: 8px 54px; }
        .prog-list-header .prog-list-back { left: 8px; }
        .prog-list-header .prog-list-title { max-width: 100%; font-size: clamp(1.05rem, 4.8vw, 1.35rem); }
        .prog-tabs { width: 100%; }
        .prog-tab { flex: 1 0 auto; padding: 12px 10px; font-size: .82rem; }
        .prog-search-wrap { padding: 10px 12px 12px; }
        .prog-scroll-area,
        body.prog-tab-invested .prog-scroll-area { padding: 12px 12px calc(18px + env(safe-area-inset-bottom, 0px)) !important; }
        .prog-list { gap: 10px; }
        .prog-item { padding: 13px; gap: 10px; }
        .prog-item-header { gap: 10px; }
        .prog-item-name { font-size: .96rem; }
        .prog-item-meta { gap: 12px 18px; }
        .prog-item-details { gap: 7px; }
        .prog-detail-item { white-space: normal; }
        .prog-invested-card { padding: 13px; }
        .prog-detail-name { font-size: 1.1rem; }
        .prog-detail-grid-2col { grid-template-columns: minmax(0, 1fr); }
        .prog-detail-row { align-items: flex-start; gap: 8px; }
        .prog-detail-label { flex: 1 1 48%; font-size: .69rem; }
        .prog-detail-value { flex: 0 1 52%; max-width: 52%; font-size: .86rem; }
        .prog-investor-provision-requirements { grid-template-columns: minmax(0, 1fr); gap: 8px; }
        .prog-investor-provision-requirements .spr-meta-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 7px; }
        .spr-meta-label { white-space: normal; }
        .spr-meta-value { text-align: right; }
        .prog-see-analytics { min-height: 44px; }
        .prog-modal { padding: 0; }
        .prog-modal-content { width: 100%; max-width: 100%; max-height: 100vh; max-height: 100dvh; border-radius: 0; }
        .prog-analytics-modal .prog-analytics-modal-content { width: 100%; max-width: 100%; height: 100vh; height: 100dvh; max-height: 100%; border-radius: 0; }
        .prog-analytics-body .pa-page { padding: 12px 12px calc(34px + env(safe-area-inset-bottom, 0px)); }
        .pa-control-row { grid-template-columns: minmax(0, 1fr); gap: 9px; }
        .pa-confirmation-row, .pa-scoped-control-row { grid-template-columns: minmax(0, 1fr); }
        .pa-control-root, .pa-confirmation-control { grid-column: auto; }
        .pa-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .pa-card { padding: 10px; }
        .pa-metric { min-height: 105px; }
        .pa-value { font-size: clamp(1.05rem, 5vw, 1.3rem); }
        .pa-trade-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .pa-family-head { align-items: flex-start; }
        .pa-period { align-items: flex-start; flex-direction: column; }
        .pa-period span { text-align: left; }
        .pa-modal-card { width: 100%; max-height: 78vh; max-height: 78dvh; border-radius: 14px; }
        .pa-modal-actions { flex-wrap: wrap; }
        .pa-modal-actions button { flex: 1 1 30%; min-width: 0; }
    }

    @media (max-width: 380px) {
        .prog-scroll-area,
        body.prog-tab-invested .prog-scroll-area { padding-right: 9px !important; padding-left: 9px !important; }
        .prog-item { padding: 11px; }
        .prog-item-header { flex-wrap: wrap; }
        .prog-item-actions { width: 100%; justify-content: flex-start; }
        .prog-btn-view { min-width: 88px; }
        .prog-detail-row { flex-direction: column; align-items: flex-start; gap: 3px; }
        .prog-detail-label, .prog-detail-value { max-width: 100%; flex: 1 1 auto; text-align: left; }
        .pa-grid { grid-template-columns: minmax(0, 1fr); }
        .pa-trade-grid { grid-template-columns: minmax(0, 1fr); }
        .pa-source-tab { padding: 10px 7px; font-size: .75rem; }
        .spr-period-nav { gap: 6px; }
        .spr-period-nav-btn { width: 32px; height: 32px; flex-basis: 32px; }
    }

    @media (max-height: 520px) and (orientation: landscape) {
        .prog-list-header { min-height: 44px; }
        .prog-tab { padding-top: 9px; padding-bottom: 9px; }
        .prog-search-wrap { padding-top: 7px; padding-bottom: 7px; }
        .prog-scroll-area { padding-top: 10px !important; }
        .prog-analytics-modal .prog-analytics-modal-content { height: 100vh; height: 100dvh; }
    }

    @media (prefers-reduced-motion: reduce) {
        .prog-page-wrapper *,
        .prog-modal *,
        .prog-see-analytics,
        .prog-item { transition-duration: .01ms !important; animation-duration: .01ms !important; }
    }



    /* ================================================================
       INVESTOR PROGRAMME VIEW — FINAL PAGE / PROVISION / ANALYTICS RULES
       These overrides intentionally remove the card treatment only from
       the invested programme and the investor-facing provision panel.
       ================================================================ */
    body.prog-page-body,
    .prog-page-wrapper,
    .prog-scroll-area,
    #progTabInvested,
    #progTabInvested.active,
    .prog-invested-card,
    .prog-investor-provision,
    .prog-investor-provision-card,
    .prog-analytics-modal,
    .prog-analytics-modal .prog-analytics-modal-content,
    .prog-analytics-body,
    .prog-analytics-body .pa-page {
        background: var(--bg, #ffffff) !important;
    }

    .prog-invested-card {
        display: block !important;
        width: 100% !important;
        max-width: none !important;
        min-width: 0 !important;
        min-height: 100%;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }

    #progTabInvested {
        width: 100%;
        min-width: 0;
        padding: 0 !important;
    }

    #progTabInvested .prog-detail-header,
    #progTabInvested .prog-detail-grid,
    #progTabInvested .prog-investor-provision-mount {
        width: 100%;
        max-width: none;
        min-width: 0;
    }

    .prog-investor-provision {
        display: block;
        width: 100%;
        max-width: none;
        min-width: 0;
        margin: 0;
        padding: 0;
    }

    .prog-investor-provision-card.spr-item {
        display: block !important;
        width: 100% !important;
        max-width: none !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        border-radius: 0 !important;
        background: var(--bg, #ffffff) !important;
        box-shadow: none !important;
    }

    .prog-investor-provision-card .spr-item-main,
    .prog-investor-provision-card .spr-item-header,
    .prog-investor-provision-card .spr-progress-list {
        width: 100%;
        min-width: 0;
        max-width: none;
    }

    .prog-investor-provision-card .spr-item-header {
        gap: 0;
        margin: 0;
        padding: 0 0 8px;
    }

    .prog-investor-provision-card .spr-item-title {
        margin: 0;
        font-size: 1rem;
        line-height: 1.3;
    }

    .prog-investor-provision-card .spr-period-nav {
        margin: 0 0 8px !important;
        padding: 8px 0 10px !important;
        border-top: 0 !important;
    }

    .prog-investor-provision-card .spr-period-nav-btn {
        flex: 0 0 34px;
        width: 34px;
        height: 34px;
        touch-action: manipulation;
    }

    .prog-investor-provision-card .spr-period-nav-btn:disabled {
        opacity: .28;
        cursor: not-allowed;
    }

    .prog-investor-provision-card .spr-progress-list {
        margin-top: 8px;
        gap: 10px;
    }

    .prog-investor-provision-card .spr-progress-heading {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 12px;
    }

    .prog-see-analytics {
        display: flex;
        width: 100%;
        min-height: 42px;
        margin: 10px 0 18px !important;
        border-radius: 8px;
    }

    .prog-investor-provision .spr-provision-no-checkpoint {
        margin: 8px 0 0;
        padding: 12px 0;
        border: 0;
        border-radius: 0;
        background: transparent;
        text-align: left;
    }

    /* Full-page programme details and analytics; do not constrain either to cards. */
    #progDetailModal,
    #progAnalyticsModal {
        position: fixed !important;
        inset: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        height: 100dvh !important;
        max-height: 100dvh !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        border-radius: 0 !important;
        background: var(--bg, #ffffff) !important;
    }

    #progDetailModal .prog-modal-content,
    #progAnalyticsModal .prog-modal-content,
    .prog-analytics-modal .prog-analytics-modal-content {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch;
        justify-content: flex-start;
        width: 100vw !important;
        max-width: none !important;
        min-width: 0 !important;
        height: 100vh !important;
        height: 100dvh !important;
        max-height: 100dvh !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        overflow: hidden !important;
        background: var(--bg, #ffffff) !important;
        animation: none !important;
    }

    .prog-view-header,
    .prog-analytics-header {
        flex: 0 0 auto !important;
        width: 100%;
        margin: 0 !important;
        background: var(--bg, #ffffff) !important;
    }

    .prog-view-body,
    .prog-view-actions {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
    }

    .prog-view-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-x: hidden;
        overflow-y: auto;
        padding: 16px 20px calc(18px + env(safe-area-inset-bottom, 0px)) !important;
        background: var(--bg, #ffffff) !important;
    }

    .prog-view-actions {
        flex: 0 0 auto;
        padding: 0 20px calc(18px + env(safe-area-inset-bottom, 0px)) !important;
        background: var(--bg, #ffffff) !important;
    }

    /* Zero vertical gap: Live / History tabs begin at the very first pixel
       immediately below the Programme Analytics header. */
    .prog-analytics-body {
        display: block;
        flex: 1 1 auto !important;
        width: 100% !important;
        min-width: 0 !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        background: var(--bg, #ffffff) !important;
    }

    .prog-analytics-body > #paAnalyticsLoadMessage {
        margin: 0 !important;
    }

    .prog-analytics-body .pa-page,
    .prog-analytics-modal .pa-page {
        display: block;
        width: 100% !important;
        max-width: none !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: 0 20px calc(30px + env(safe-area-inset-bottom, 0px)) !important;
        background: var(--bg, #ffffff) !important;
    }

    .prog-analytics-body #paDashboard,
    .prog-analytics-body #paIsolated {
        width: 100%;
        min-width: 0;
        margin: 0;
        padding: 0;
    }

    .prog-analytics-body .pa-source-tabs {
        margin: 0 0 10px !important;
        border-radius: 0 !important;
    }

    .prog-analytics-body .pa-source-tabs + *,
    .prog-analytics-body .pa-control-row:first-child {
        margin-top: 0 !important;
    }

    /* Keep scrolling available while scrollbars remain visually hidden. */
    .prog-scroll-area,
    .prog-view-body,
    .prog-analytics-body,
    #progDetailModal .prog-modal-content {
        scrollbar-width: none !important;
        -ms-overflow-style: none !important;
    }

    .prog-scroll-area::-webkit-scrollbar,
    .prog-view-body::-webkit-scrollbar,
    .prog-analytics-body::-webkit-scrollbar,
    #progDetailModal .prog-modal-content::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }

    @media (max-width: 650px) {
        .prog-scroll-area,
        body.prog-tab-invested .prog-scroll-area {
            padding: 12px 12px calc(18px + env(safe-area-inset-bottom, 0px)) !important;
        }

        .prog-invested-card {
            padding: 0 !important;
        }

        .prog-detail-header {
            padding-bottom: 10px;
        }

        .prog-detail-grid-2col {
            grid-template-columns: minmax(0, 1fr) !important;
        }

        .prog-detail-row {
            flex-direction: row;
            align-items: center;
        }

        .prog-detail-value {
            max-width: 55%;
            text-align: right;
        }

        .prog-investor-provision-card .spr-period-nav {
            gap: 8px;
        }

        .prog-investor-provision-card .spr-period-nav-title {
            font-size: .86rem;
        }

        .prog-analytics-modal .prog-analytics-modal-content {
            width: 100vw !important;
            height: 100dvh !important;
            max-height: 100dvh !important;
        }

        .prog-analytics-body .pa-page {
            padding: 0 12px calc(24px + env(safe-area-inset-bottom, 0px)) !important;
        }

        .prog-analytics-body .pa-source-tabs {
            margin-top: 0 !important;
        }
    }

    @media (max-width: 380px) {
        .prog-scroll-area,
        body.prog-tab-invested .prog-scroll-area {
            padding-right: 9px !important;
            padding-left: 9px !important;
        }

        .prog-analytics-body .pa-page,
        .prog-analytics-modal .pa-page {
            padding-right: 9px !important;
            padding-left: 9px !important;
        }

        .prog-investor-provision-card .spr-progress-label {
            font-size: .67rem;
        }

        .prog-detail-row {
            align-items: flex-start;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .prog-investor-provision-card *,
        .prog-see-analytics,
        .prog-modal * {
            transition-duration: .01ms !important;
            animation-duration: .01ms !important;
        }
    }



    /* Investor analytics: show only visitor-safe signal summaries. */
    .prog-analytics-body .pa-isolated-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0 0 12px;
        min-width: 0;
    }

    .prog-analytics-body .pa-isolated-head h2 {
        min-width: 0;
        margin: 0;
        color: var(--pa-text);
        font-size: .98rem;
        font-weight: 850;
        line-height: 1.25;
        overflow-wrap: anywhere;
    }

    .prog-analytics-body .pa-signal-list {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 9px;
        width: 100%;
        min-width: 0;
    }

    .prog-analytics-body .pa-signal-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
        min-width: 0;
        padding: 13px 14px;
    }

    .prog-analytics-body .pa-signal-symbol {
        flex: 1 1 auto;
        min-width: 0;
        color: var(--pa-text);
        font-size: .83rem;
        font-weight: 900;
        letter-spacing: .015em;
        overflow-wrap: anywhere;
    }

    .prog-analytics-body .pa-signal-result {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        min-width: 0;
        margin-left: auto;
        white-space: nowrap;
    }

    .prog-analytics-body .pa-signal-result .pa-status {
        max-width: 100%;
    }

    .prog-analytics-body .pa-signal-rr {
        color: var(--pa-text);
        font-size: .82rem;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .prog-analytics-body .pa-signal-rr.positive { color: var(--pa-green); }
    .prog-analytics-body .pa-signal-rr.negative { color: var(--pa-red); }

    /* Keep period name on the left and count/open action on the right edge. */
    .prog-analytics-body .pa-period {
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        width: 100%;
        min-width: 0;
    }

    .prog-analytics-body .pa-period strong {
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .prog-analytics-body .pa-period span {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: center;
        justify-content: flex-end;
        gap: 4px;
        max-width: 62%;
        margin-left: auto;
        color: var(--pa-muted);
        font-size: .70rem;
        text-align: right;
        white-space: nowrap;
    }

    .prog-analytics-body .pa-source-tabs {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0;
        width: 100%;
    }

    @media (max-width: 650px) {
        .prog-analytics-body .pa-signal-card {
            gap: 8px;
            padding: 11px 10px;
        }

        .prog-analytics-body .pa-signal-result { gap: 7px; }
        .prog-analytics-body .pa-signal-symbol,
        .prog-analytics-body .pa-signal-rr { font-size: .76rem; }
        .prog-analytics-body .pa-period { gap: 7px; padding: 12px 10px; }
        .prog-analytics-body .pa-period span { font-size: .66rem; }
    }

    @media (max-width: 360px) {
        .prog-analytics-body .pa-signal-card { padding: 10px 8px; }
        .prog-analytics-body .pa-signal-result { gap: 5px; }
        .prog-analytics-body .pa-signal-result .pa-status { padding: 3px 6px; font-size: .59rem; }
        .prog-analytics-body .pa-period span { font-size: .61rem; }
    }

</style>

<style>
    /* ============================================================
       PROGRAMME TRAINING — pt-*  (fullscreen chart + sidebar)
       Offsets mirror dev_style.php:
         - desktop collapsed sidebar width : 64px
         - desktop expanded sidebar width  : 260px
         - mobile has a 56px top header
       ============================================================ */

    body.pt-fullbody {
        margin: 0;
        padding: 0;
        background: var(--bg, #f5f5f5);
        overflow: hidden;
    }

    /* ============================================================
       TOP OVERLAY: symbol + timeframe
       Desktop: sit to the right of the sidebar.
       Mobile : sit below the 56px mobile top header.
       ============================================================ */
    .pt-topbar {
        position: fixed;
        top: 12px;
        left: 12px;                /* no sidebar in trader shell */
        right: 12px;
        z-index: 500;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: nowrap;         /* keep single row */
        pointer-events: none;
    }

    /* ============================================================
       FULLSCREEN CHART
       Desktop: fill the area to the right of the sidebar.
       Mobile : fill below the mobile top header.
    ============================================================ */
    .pt-chart-fullscreen {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;                   /* was 64px */
        background: var(--bg-card, #fff);
    }


    /* ---------- MOBILE ---------- */
    @media (max-width: 768px) {
        .pt-topbar {
            top: calc(56px + 8px);   /* below mobile top header */
            left: 8px;
            right: 8px;
            gap: 8px;
        }
        .pt-chart-fullscreen {
            top: 0;               /* below mobile top header */
            left: 0;
        }
        body.sidebar-expanded-desktop .pt-topbar,
        body.sidebar-expanded-desktop .pt-chart-fullscreen {
            /* don't apply desktop offsets on mobile */
            top: calc(56px + 8px);
            left: 8px;
        }
        body.sidebar-expanded-desktop .pt-chart-fullscreen {
            top: 56px;
            left: 0;
        }
    }

    /* --- Symbol button (top-left of the overlay) --- */
    .pt-topbar-btn {
        pointer-events: auto;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 9px 14px;
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        color: var(--text, #222);
        font-family: inherit;
        font-size: 0.92rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        max-width: 60vw;
        overflow: hidden;
    }
    .pt-topbar-btn:hover {
        border-color: var(--accent, #2e8b57);
        box-shadow: 0 3px 14px rgba(0,0,0,0.1);
    }

    .pt-topbar-symbol {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pt-topbar-caret {
        font-size: 0.7rem;
        color: var(--text-muted, #888);
        flex-shrink: 0;
    }

    /* --- Timeframe strip --- */
    .pt-tf-strip {
        pointer-events: auto;
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 5px;
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        overflow-x: auto;
        max-width: 100%;
        -webkit-overflow-scrolling: touch;
    }
    .pt-tf-strip::-webkit-scrollbar { height: 0; }

    .pt-tf-btn {
        background: transparent;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        font-family: inherit;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text-muted, #888);
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .pt-tf-btn:hover {
        color: var(--text, #222);
        background: rgba(46, 139, 87, 0.08);
    }
    .pt-tf-btn.active {
        background: var(--accent, #2e8b57);
        color: #fff;
    }

    .pt-tf-empty {
        font-size: 0.78rem;
        color: var(--text-muted, #888);
        padding: 6px 10px;
    }

    /* ============================================================
       CHART INTERNALS
       ============================================================ */
    .pt-chart-scroll {
        position: absolute;
        inset: 0;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
    }
    .pt-chart-scroll::-webkit-scrollbar { height: 10px; }
    .pt-chart-scroll::-webkit-scrollbar-track { background: transparent; }
    .pt-chart-scroll::-webkit-scrollbar-thumb {
        background: var(--border-color, #ccc);
        border-radius: 10px;
    }

    .pt-chart-canvas {
        display: block;
        max-width: none;
    }

    .pt-chart-empty,
    .pt-chart-loading {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-align: center;
        color: var(--text-muted, #888);
        background: var(--bg-card, #fff);
        font-size: 0.9rem;
        padding: 40px 20px;
        z-index: 400;
    }

    .pt-empty-icon {
        font-size: 1.6rem;
        opacity: 0.4;
    }

    .pt-chart-empty p,
    .pt-chart-loading p { margin: 0; }

    .pt-loading-spinner {
        width: 28px;
        height: 28px;
        border: 3px solid var(--border-color, #e0e0e0);
        border-top-color: var(--accent, #2e8b57);
        border-radius: 50%;
        animation: ptSpin 0.8s linear infinite;
    }
    @keyframes ptSpin { to { transform: rotate(360deg); } }

    /* ============================================================
       FLOATING READOUT (bottom-right)
       ============================================================ */
    .pt-floating-readout {
        position: absolute;
        right: 16px;
        bottom: 24px;
        z-index: 500;
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        padding: 10px 14px;
        min-width: 190px;
        pointer-events: none;
    }

    .pt-fr-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px;
        padding-bottom: 6px;
        border-bottom: 1px solid var(--border-color, #e0e0e0);
    }

    .pt-fr-time {
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--text, #222);
        font-variant-numeric: tabular-nums;
    }

    .pt-fr-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 6px;
    }

    .pt-fr-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .pt-fr-label {
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: var(--text-muted, #888);
        font-weight: 700;
    }

    .pt-fr-value {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--text, #222);
        font-variant-numeric: tabular-nums;
    }

    /* ============================================================
       MODAL (symbol picker)
       ============================================================ */
    .pt-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 20px;
        box-sizing: border-box;
    }
    .pt-modal.active { display: flex; }

    .pt-modal-content {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius, 16px);
        padding: 22px;
        max-width: 480px;
        width: 100%;
        max-height: 82vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 10px 40px rgba(0,0,0,0.25);
    }

    .pt-modal-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--accent, #2e8b57);
        margin: 0 0 14px 0;
        text-align: center;
    }

    .pt-modal-search-wrap {
        margin-bottom: 10px;
        flex-shrink: 0;
    }

    .pt-modal-input {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        background: var(--bg, #f5f5f5);
        color: var(--text, #222);
        font-size: 0.9rem;
        font-family: inherit;
    }
    .pt-modal-input:focus {
        outline: none;
        border-color: var(--accent, #2e8b57);
    }

    .pt-modal-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.75rem;
        color: var(--text-muted, #888);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 8px;
        flex-shrink: 0;
    }

    .pt-modal-list {
        flex: 1;
        min-height: 180px;
        max-height: 55vh;
        overflow-y: auto;
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        background: var(--bg, #f5f5f5);
        padding: 6px;
        margin-bottom: 14px;
    }
    .pt-modal-list::-webkit-scrollbar { width: 6px; }
    .pt-modal-list::-webkit-scrollbar-track { background: transparent; }
    .pt-modal-list::-webkit-scrollbar-thumb {
        background: var(--border-color, #ccc);
        border-radius: 10px;
    }

    .pt-modal-row {
        display: block;
        width: 100%;
        text-align: left;
        padding: 11px 14px;
        margin-bottom: 4px;
        border: none;
        background: transparent;
        border-radius: var(--radius-sm, 8px);
        font-family: inherit;
        font-size: 0.92rem;
        font-weight: 600;
        color: var(--text, #222);
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .pt-modal-row:hover {
        background: rgba(46, 139, 87, 0.08);
    }
    .pt-modal-row.is-active {
        background: rgba(46, 139, 87, 0.15);
        color: var(--accent, #2e8b57);
    }

    .pt-modal-empty {
        text-align: center;
        padding: 30px 16px;
        font-size: 0.85rem;
        color: var(--text-muted, #888);
        font-style: italic;
    }

    .pt-modal-actions {
        display: flex;
        gap: 10px;
    }

    .pt-btn-ghost {
        flex: 1;
        padding: 11px 18px;
        background: var(--bg, #f5f5f5);
        color: var(--text, #222);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        font-family: inherit;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .pt-btn-ghost:hover { background: var(--border-color, #e0e0e0); }

    /* ============================================================
       DARK MODE OVERRIDES
       ============================================================ */
    body.dark-mode.pt-fullbody {
        background: var(--bg, #1e1e2a);
    }

    body.dark-mode .pt-topbar-btn,
    body.dark-mode .pt-tf-strip,
    body.dark-mode .pt-floating-readout,
    body.dark-mode .pt-modal-content {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }

    body.dark-mode .pt-topbar-symbol,
    body.dark-mode .pt-fr-time,
    body.dark-mode .pt-fr-value,
    body.dark-mode .pt-modal-row {
        color: var(--text, #eee);
    }

    body.dark-mode .pt-tf-btn:hover {
        background: rgba(46, 139, 87, 0.15);
    }

    body.dark-mode .pt-chart-fullscreen,
    body.dark-mode .pt-chart-empty,
    body.dark-mode .pt-chart-loading {
        background: var(--bg-card, #1e1e2a);
    }

    body.dark-mode .pt-modal-input,
    body.dark-mode .pt-modal-list {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }

    body.dark-mode .pt-modal-row:hover {
        background: rgba(46, 139, 87, 0.12);
    }

    body.dark-mode .pt-modal-row.is-active {
        background: rgba(46, 139, 87, 0.2);
    }

    body.dark-mode .pt-btn-ghost {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }

    /* ============================================================
       RESPONSIVE — small refinements for mobile
       ============================================================ */
    @media (max-width: 600px) {
        .pt-topbar-btn {
            padding: 8px 11px;
            font-size: 0.85rem;
        }

        .pt-tf-strip {
            padding: 4px;
            gap: 4px;
        }

        .pt-tf-btn {
            padding: 5px 9px;
            font-size: 0.74rem;
        }

        .pt-floating-readout {
            right: 8px;
            bottom: 14px;
            min-width: 160px;
            padding: 8px 11px;
        }

        .pt-fr-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
</style>
