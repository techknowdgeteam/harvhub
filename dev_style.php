<!--dev_style.php---->
<style>
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
       MOBILE TOP HEADER
       ============================================================ */
    .mobile-top-header {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 56px;
        background: var(--bg-card, #ffffff);
        border-bottom: 1px solid var(--border-color, #e0e0e0);
        z-index: 2100;
        align-items: center;
        justify-content: center;
        padding: 0 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        transition: background 0.3s ease, border-color 0.3s ease;
    }

    body.dark-mode .mobile-top-header {
        background: var(--bg-card, #1a1a2e);
        border-bottom: 1px solid var(--border-color, #2a2a3e);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
    }

    .mobile-menu-btn {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        display: flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: transparent;
        border: none;
        color: var(--text, #333);
        font-size: 1.3rem;
        cursor: pointer;
        transition: background 0.2s ease, color 0.2s ease;
    }

    .mobile-menu-btn:hover {
        background: var(--bg-hover, rgba(0, 0, 0, 0.05));
        color: var(--accent, #2e8b57);
    }

    .mobile-menu-btn:active {
        transform: translateY(-50%) scale(0.92);
    }

    .mobile-header-logo {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--accent, #2e8b57);
        display: flex;
        align-items: center;
        gap: 6px;
        letter-spacing: 0.5px;
    }

    .mobile-header-logo i {
        font-size: 1.25rem;
    }

    /* ============================================================
       SIDEBAR NAVIGATION
       ============================================================ */
    .sidebar-nav {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 64px;
        background: var(--bg-card, #ffffff);
        border-right: 1px solid var(--border-color, #e0e0e0);
        display: flex;
        flex-direction: column;
        z-index: 2200;
        transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                    box-shadow 0.3s ease,
                    background 0.3s ease,
                    transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        box-shadow: 2px 0 12px rgba(0, 0, 0, 0.06);
        padding-top: 8px;
        padding-bottom: 8px;
    }

    body.dark-mode .sidebar-nav {
        background: var(--bg-card, #1a1a2e);
        border-right: 1px solid var(--border-color, #2a2a3e);
        box-shadow: 2px 0 12px rgba(0, 0, 0, 0.4);
    }

    .sidebar-nav.expanded {
        width: 260px;
        box-shadow: 4px 0 24px rgba(0, 0, 0, 0.12);
    }

    body.dark-mode .sidebar-nav.expanded {
        box-shadow: 4px 0 24px rgba(0, 0, 0, 0.6);
    }

    .sidebar-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        margin: 8px auto 12px;
        border-radius: 12px;
        background: transparent;
        border: none;
        color: var(--text, #333);
        font-size: 1.4rem;
        cursor: pointer;
        transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
        flex-shrink: 0;
        position: relative;
    }

    .sidebar-toggle:hover {
        background: var(--bg-hover, rgba(0, 0, 0, 0.05));
        color: var(--accent, #2e8b57);
    }

    body.dark-mode .sidebar-toggle:hover {
        background: rgba(255, 255, 255, 0.06);
        color: var(--accent, #2e8b57);
    }

    .sidebar-toggle:active {
        transform: scale(0.92);
    }

    .sidebar-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 16px;
        margin-bottom: 16px;
        opacity: 0;
        transition: opacity 0.25s ease;
        pointer-events: none;
        white-space: nowrap;
        overflow: hidden;
        flex-shrink: 0;
    }

    .sidebar-nav.expanded .sidebar-header {
        opacity: 1;
        pointer-events: auto;
    }

    .sidebar-logo {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--accent, #2e8b57);
        display: flex;
        align-items: center;
        gap: 8px;
        letter-spacing: 0.5px;
    }

    .sidebar-logo i {
        font-size: 1.5rem;
    }

    .sidebar-menu {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        flex: 1;
        gap: 4px;
        padding: 0 8px;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .sidebar-menu-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 14px;
        border-radius: 12px;
        text-decoration: none;
        color: var(--text-secondary, #666);
        font-size: 0.9rem;
        font-weight: 500;
        transition: background 0.2s ease, color 0.2s ease, transform 0.15s ease;
        cursor: pointer;
        border: none;
        background: transparent;
        width: 100%;
        white-space: nowrap;
        min-height: 48px;
        position: relative;
        letter-spacing: 0.2px;
    }

    .sidebar-menu-item .nav-icon {
        font-size: 1.25rem;
        width: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: var(--text-muted, #888);
        transition: color 0.2s ease, transform 0.2s ease;
    }

    .sidebar-menu-item .nav-label {
        opacity: 0;
        transition: opacity 0.25s ease, transform 0.25s ease;
        transform: translateX(-8px);
        pointer-events: none;
        font-size: 0.85rem;
        text-transform: capitalize;
        letter-spacing: 0.3px;
        flex: 1;
    }

    .sidebar-nav.expanded .sidebar-menu-item .nav-label {
        opacity: 1;
        transform: translateX(0);
        pointer-events: auto;
    }

    .sidebar-menu-item:hover {
        background: var(--bg-hover, rgba(0, 0, 0, 0.04));
        color: var(--accent, #2e8b57);
    }

    body.dark-mode .sidebar-menu-item:hover {
        background: rgba(255, 255, 255, 0.06);
        color: var(--accent, #2e8b57);
    }

    .sidebar-menu-item:hover .nav-icon {
        color: var(--accent, #2e8b57);
        transform: scale(1.05);
    }

    .sidebar-menu-item.active {
        background: var(--accent-light, rgba(46, 139, 87, 0.12));
        color: var(--accent, #2e8b57);
    }

    body.dark-mode .sidebar-menu-item.active {
        background: rgba(46, 139, 87, 0.18);
        color: var(--accent, #2e8b57);
    }

    .sidebar-menu-item.active .nav-icon {
        color: var(--accent, #2e8b57);
    }

    .sidebar-menu-item.active .nav-label {
        color: var(--accent, #2e8b57);
        font-weight: 600;
    }

    .sidebar-divider {
        border: none;
        border-top: 1px solid var(--border-color, #e0e0e0);
        margin: 8px 8px;
        opacity: 0.5;
    }

    body.dark-mode .sidebar-divider {
        border-top-color: var(--border-color, #2a2a3e);
    }

    .dark-mode-toggle-item {
        cursor: default;
        justify-content: space-between;
    }

    .dark-mode-toggle-item:hover {
        background: transparent;
        color: var(--text-secondary, #666);
    }

    .dark-mode-toggle-item:hover .nav-icon {
        color: var(--text-muted, #888);
        transform: none;
    }

    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 42px;
        height: 24px;
        flex-shrink: 0;
        margin-left: auto;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .3s;
        border-radius: 24px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    input:checked + .toggle-slider {
        background-color: var(--accent, #2e8b57);
    }

    input:checked + .toggle-slider:before {
        transform: translateX(18px);
    }

    .sidebar-nav:not(.expanded) .dark-mode-toggle-item .toggle-switch {
        display: none;
    }

    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.45);
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
        z-index: 2199;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
        pointer-events: none;
    }

    .sidebar-overlay.active {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    /* ============================================================
       LAYOUT
       ============================================================ */
    @media (min-width: 769px) {
        body {
            padding-left: 64px;
            padding-top: 0;
            padding-bottom: 0;
            transition: padding-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.sidebar-expanded-desktop {
            padding-left: 260px;
        }

        .mobile-top-header {
            display: none !important;
        }

        .sidebar-toggle {
            display: flex;
        }
    }

    @media (max-width: 768px) {
        body {
            padding-left: 0 !important;
            padding-top: 56px;
            padding-bottom: 0;
        }

        .mobile-top-header {
            display: flex;
        }

        .sidebar-nav {
            width: 50vw;
            max-width: 280px;
            min-width: 180px;
            transform: translateX(-100%);
            border-right: 1px solid var(--border-color, #e0e0e0);
            box-shadow: 4px 0 32px rgba(0, 0, 0, 0.25);
            padding-top: 12px;
            padding-bottom: 12px;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1),
                        box-shadow 0.35s ease;
        }

        body.dark-mode .sidebar-nav {
            border-right: 1px solid var(--border-color, #2a2a3e);
            box-shadow: 4px 0 32px rgba(0, 0, 0, 0.7);
        }

        .sidebar-nav.expanded {
            transform: translateX(0);
            width: 50vw;
            max-width: 280px;
            min-width: 180px;
            box-shadow: 4px 0 32px rgba(0, 0, 0, 0.3);
        }

        body.dark-mode .sidebar-nav.expanded {
            box-shadow: 4px 0 32px rgba(0, 0, 0, 0.75);
        }

        .sidebar-nav .sidebar-toggle {
            display: none;
        }

        .sidebar-nav .sidebar-header {
            display: flex;
            opacity: 1;
            pointer-events: auto;
            padding: 0 16px;
            margin-bottom: 16px;
            justify-content: center;
        }

        .sidebar-logo {
            font-size: 1.15rem;
        }

        .sidebar-logo i {
            font-size: 1.3rem;
        }

        .sidebar-nav .sidebar-menu-item .nav-label {
            opacity: 1;
            transform: translateX(0);
            pointer-events: auto;
            font-size: 0.85rem;
        }

        .sidebar-nav .sidebar-menu-item {
            padding: 12px 14px;
            gap: 14px;
            min-height: 48px;
        }

        .sidebar-nav .sidebar-menu-item .nav-icon {
            font-size: 1.25rem;
            width: 28px;
        }

        .sidebar-nav .dark-mode-toggle-item .toggle-switch {
            display: inline-block;
        }

        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
    }

    @media (max-width: 480px) {
        .sidebar-nav,
        .sidebar-nav.expanded {
            width: 55vw;
            max-width: 260px;
            min-width: 160px;
        }

        .sidebar-menu-item .nav-label {
            font-size: 0.8rem;
        }

        .sidebar-menu-item {
            padding: 10px 12px;
            min-height: 44px;
        }

        .sidebar-logo {
            font-size: 1.05rem;
        }

        .sidebar-logo i {
            font-size: 1.15rem;
        }

        .mobile-header-logo {
            font-size: 1.05rem;
        }

        .mobile-header-logo i {
            font-size: 1.15rem;
        }
    }

    /* ============================================================
       SPINNER
       ============================================================ */
    #page-content {
        min-height: 80vh;
        transition: opacity 0.15s ease;
    }

    #page-content.loading {
        opacity: 0.4;
    }

    .spinner-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 99999;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }

    .spinner-overlay.active {
        display: flex;
    }

    .spinner {
        display: inline-block;
        width: 40px;
        height: 40px;
        border: 3px solid rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        border-top-color: var(--accent);
        animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* ============================================================
       CONNECT BROKER PAGE STYLES
       ============================================================ */
    #page-content .connect-broker-container {
        max-width: 100%;
        width: 100%;
        padding: 20px;
        margin: 0 auto;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 75vh;
    }

    #page-content .connect-broker-card {
        max-height: 80vh;
        overflow-y: auto;
        padding: 28px 24px;
        margin: 10px auto;
        margin-top: 30px;
        max-width: 480px;
        width: 100%;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        color: var(--text);
    }

    #page-content .connect-broker-card .logo-area p {
        text-align: center;
        font-size: 1.15rem;
        font-weight: 600;
        color: var(--accent);
        margin: 0 0 16px;
    }

    #page-content .connect-broker-card .user-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 12px;
        background: var(--bg-hover);
        border-radius: 10px;
        margin-bottom: 16px;
        font-size: 0.85rem;
    }

    #page-content .connect-broker-card .user-info .label {
        color: var(--text-muted);
        display: block;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    #page-content .connect-broker-card .user-info .value {
        color: var(--text);
        font-weight: 600;
    }

    #page-content .connect-broker-card .status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        background: var(--accent-light);
        color: var(--accent);
    }

    #page-content .connect-broker-card .section-title {
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--text-muted);
        margin: 16px 0 10px;
    }

    #page-content .connect-broker-card .form-group {
        margin-bottom: 14px;
    }

    #page-content .connect-broker-card .form-group label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 6px;
    }

    #page-content .connect-broker-card .form-group select,
    #page-content .connect-broker-card .form-group input {
        width: 100%;
        padding: 11px 13px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
        background: var(--bg);
        color: var(--text);
        font-size: 0.9rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        font-family: inherit;
    }

    #page-content .connect-broker-card .form-group select:focus,
    #page-content .connect-broker-card .form-group input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-light);
    }

    #page-content .connect-broker-card .password-wrapper {
        position: relative;
    }

    #page-content .connect-broker-card .password-wrapper input {
        padding-right: 60px;
    }

    #page-content .connect-broker-card .password-toggle {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        color: var(--accent);
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        padding: 6px 10px;
        border-radius: 6px;
    }

    #page-content .connect-broker-card .password-toggle:hover {
        background: var(--accent-light);
    }

    #page-content .connect-broker-card .btn-submit {
        width: 100%;
        padding: 13px;
        border-radius: 10px;
        border: none;
        background: var(--accent);
        color: #fff;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.1s ease;
        margin-top: 8px;
    }

    #page-content .connect-broker-card .btn-submit:hover {
        background: #256e45;
    }

    #page-content .connect-broker-card .btn-submit:active {
        transform: scale(0.98);
    }

    #page-content .connect-broker-card .btn-submit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    #page-content .connect-broker-card .btn-secondary {
        display: inline-block;
        padding: 12px 32px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text);
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease, border-color 0.2s ease;
    }

    #page-content .connect-broker-card .btn-secondary:hover {
        background: var(--bg-hover);
        border-color: var(--accent);
        color: var(--accent);
    }

    #page-content .connect-broker-card .btn-connect-wrapper {
        text-align: center;
        margin: 16px 0;
    }

    #page-content .connect-broker-card .broker-selection-grid {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    #page-content .connect-broker-card .broker-selection-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 14px;
        border-radius: 10px;
        background: var(--bg-hover);
        border: 1px solid var(--border-color);
    }

    #page-content .connect-broker-card .broker-name {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--text);
    }

    #page-content .connect-broker-card .btn-broker-link {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 8px;
        background: var(--accent);
        color: #fff;
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.2s ease;
    }

    #page-content .connect-broker-card .btn-broker-link:hover {
        background: #256e45;
    }

    #page-content .connect-broker-card .btn-broker-link.no-link {
        background: var(--text-muted);
        cursor: not-allowed;
        pointer-events: none;
        opacity: 0.6;
    }

    #page-content .connect-broker-card .broker-divider {
        border: none;
        border-top: 1px solid var(--border-color);
        margin: 18px 0;
    }

    #page-content .connect-broker-card .info-note {
        margin-top: 18px;
        padding: 12px 14px;
        background: var(--bg-hover);
        border-radius: 10px;
        font-size: 0.8rem;
        color: var(--text-secondary);
        line-height: 1.5;
    }

    #page-content .connect-broker-card .error-message {
        padding: 10px 14px;
        background: rgba(220, 53, 69, 0.1);
        border: 1px solid rgba(220, 53, 69, 0.3);
        color: #dc3545;
        border-radius: 10px;
        font-size: 0.85rem;
        margin-bottom: 12px;
    }

    #page-content .connect-broker-card .success-message {
        padding: 10px 14px;
        background: rgba(40, 167, 69, 0.1);
        border: 1px solid rgba(40, 167, 69, 0.3);
        color: #28a745;
        border-radius: 10px;
        font-size: 0.85rem;
        margin-bottom: 12px;
    }

    #page-content .connect-broker-card .back-link-top {
        margin-bottom: 12px;
    }

    #page-content .connect-broker-card .back-link-top a {
        color: var(--text-muted);
        font-size: 0.85rem;
        text-decoration: none;
        transition: color 0.2s ease;
    }

    #page-content .connect-broker-card .back-link-top a:hover {
        color: var(--accent);
    }

    #page-content .connect-broker-card .hidden {
        display: none !important;
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
        CUSTOM MODAL — NOT A DEVELOPER
        Uses ONLY existing CSS variables from dev_style.php
        No fallbacks — variables are guaranteed by :root / body.dark-mode
        ============================================================ */
    .dev-modal-overlay {
        position: fixed;
        inset: 0;
        background: var(--modal-overlay);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        z-index: 999999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        animation: devFadeIn 0.25s ease;
    }

    @keyframes devFadeIn {
        from { opacity: 0; }
        to   { opacity: 1; }
    }

    @keyframes devSlideUp {
        from { opacity: 0; transform: translateY(24px) scale(0.96); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .dev-modal-card {
        background: var(--modal-bg);
        color: var(--modal-text);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        max-width: 440px;
        width: 100%;
        padding: 32px 28px 26px;
        text-align: center;
        animation: devSlideUp 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        position: relative;
        overflow: hidden;
    }

    .dev-modal-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--accent), var(--accent-light), var(--accent));
        background-size: 200% 100%;
        animation: devGradientShift 3s ease infinite;
    }

    @keyframes devGradientShift {
        0%   { background-position: 0% 50%; }
        50%  { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    .dev-modal-icon {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: var(--accent-light);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 18px;
        font-size: 2rem;
        color: var(--accent);
        animation: devPulse 2s ease-in-out infinite;
    }
    /* Give the broker symbols select room to breathe */
    .dd-symbol-select-group {
        margin-bottom: 24px;
        padding-bottom: 8px;
    }

    .dd-symbol-select-group .dd-select {
        margin-bottom: 6px;
    }

    /* Ensure the card doesn't clip content */
    .dd-card {
        overflow: visible;
    }

    @keyframes devPulse {
        0%, 100% { transform: scale(1); }
        50%      { transform: scale(1.06); }
    }

    .dev-modal-card h2 {
        font-size: 1.3rem;
        font-weight: 700;
        margin: 0 0 12px;
        color: var(--text);
        letter-spacing: -0.3px;
    }

    .dev-modal-card p {
        font-size: 0.92rem;
        line-height: 1.65;
        color: var(--text-secondary);
        margin: 0 0 8px;
    }

    .dev-modal-card p.dev-modal-sub {
        font-size: 0.82rem;
        color: var(--text-muted);
        margin-bottom: 22px;
    }

    .dev-modal-card a.dev-modal-mail {
        color: var(--accent);
        font-weight: 700;
        text-decoration: none;
        border-bottom: 2px solid var(--accent-light);
        transition: border-color 0.2s ease, color 0.2s ease;
        padding-bottom: 1px;
        word-break: break-all;
    }

    .dev-modal-card a.dev-modal-mail:hover {
        border-bottom-color: var(--accent);
    }

    .dev-modal-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 14px 24px;
        border-radius: 12px;
        border: none;
        background: var(--accent);
        color: #fff;
        font-size: 0.95rem;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.12s ease, box-shadow 0.2s ease, color 0.2s ease;
        box-shadow: var(--shadow-lg);
        letter-spacing: 0.3px;
    }

    .dev-modal-btn:hover {
        background: var(--accent-hover);
        color: #fff;
    }

    .dev-modal-btn:active {
        transform: scale(0.97);
    }

    .dev-modal-btn i {
        font-size: 1rem;
    }

    @media (max-width: 480px) {
        .dev-modal-card {
            padding: 26px 20px 22px;
            border-radius: var(--radius);
        }

        .dev-modal-icon {
            width: 60px;
            height: 60px;
            font-size: 1.6rem;
            margin-bottom: 14px;
        }

        .dev-modal-card h2 {
            font-size: 1.15rem;
        }

        .dev-modal-card p {
            font-size: 0.86rem;
        }

        .dev-modal-btn {
            padding: 13px 20px;
            font-size: 0.9rem;
        }
    }
    /* ============================================================
    CUSTOM CONFIRM DIALOG — DD
    ============================================================ */
    .dd-confirm-overlay {
        position: fixed;
        inset: 0;
        background: var(--modal-overlay, rgba(0,0,0,0.5));
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        z-index: 999999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        animation: ddConfirmFadeIn 0.22s ease;
    }

    @keyframes ddConfirmFadeIn {
        from { opacity: 0; }
        to   { opacity: 1; }
    }

    @keyframes ddConfirmSlideUp {
        from { opacity: 0; transform: translateY(20px) scale(0.96); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .dd-confirm-card {
        background: var(--modal-bg, #fff);
        color: var(--modal-text, #222);
        border: 1px solid var(--glass-border, #e0e0e0);
        border-radius: var(--radius, 14px);
        box-shadow: var(--shadow-lg, 0 20px 50px rgba(0,0,0,0.25));
        max-width: 420px;
        width: 100%;
        padding: 28px 24px 22px;
        text-align: center;
        animation: ddConfirmSlideUp 0.32s cubic-bezier(0.34, 1.56, 0.64, 1);
        position: relative;
        overflow: hidden;
    }

    .dd-confirm-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #e74c3c, #ff7675, #e74c3c);
        background-size: 200% 100%;
        animation: ddConfirmGradient 3s ease infinite;
    }

    @keyframes ddConfirmGradient {
        0%   { background-position: 0% 50%; }
        50%  { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    .dd-confirm-icon {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: rgba(231, 76, 60, 0.12);
        color: #e74c3c;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        font-size: 1.75rem;
    }

    .dd-confirm-title {
        font-size: 1.2rem;
        font-weight: 700;
        margin: 0 0 10px;
        color: var(--text, #222);
        letter-spacing: -0.2px;
    }

    .dd-confirm-text {
        font-size: 0.9rem;
        line-height: 1.6;
        color: var(--text-secondary, #555);
        margin: 0 0 22px;
    }

    .dd-confirm-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    .dd-confirm-actions button {
        flex: 1;
        padding: 12px 18px;
        border-radius: 10px;
        border: none;
        font-size: 0.9rem;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.12s ease, opacity 0.2s ease;
    }

    .dd-confirm-cancel {
        background: var(--bg, #f0f0f0);
        color: var(--text, #222);
        border: 1px solid var(--border-color, #e0e0e0);
    }

    .dd-confirm-cancel:hover {
        background: var(--border-color, #e0e0e0);
    }

    .dd-confirm-danger {
        background: #e74c3c;
        color: #fff;
    }

    .dd-confirm-danger:hover {
        background: #c0392b;
    }

    .dd-confirm-actions button:active {
        transform: scale(0.97);
    }

    body.dark-mode .dd-confirm-cancel {
        background: var(--bg-card, #1e1e2a);
        color: var(--text, #eee);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .dd-confirm-cancel:hover {
        background: var(--border-color, #333);
    }

    @media (max-width: 480px) {
        .dd-confirm-card { padding: 24px 18px 20px; }
        .dd-confirm-icon { width: 54px; height: 54px; font-size: 1.5rem; }
        .dd-confirm-title { font-size: 1.1rem; }
        .dd-confirm-text { font-size: 0.85rem; }
        .dd-confirm-actions button { padding: 11px 14px; font-size: 0.85rem; }
    }
</style>

<style>
    /* ============================================================
       DEVELOPER DASHBOARD — dd-*
       All developer-dashboard-only styling lives here.
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

    /* ============================================================
       HEADER
       ============================================================ */
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

    /* ============================================================
       NOTICES
       ============================================================ */
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

    /* ============================================================
       CARD
       ============================================================ */
    .dd-card {
        background: var(--bg, #f5f5f5);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        padding: var(--spacing-md, 20px);
        margin-bottom: var(--spacing-md, 20px);
        overflow: visible;
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

    /* ============================================================
       FORM ELEMENTS
       ============================================================ */
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

    .dd-field-hint {
        font-size: 0.72rem;
        color: var(--text-muted, #888);
        margin-top: 4px;
    }

    .dd-link-btn {
        background: transparent;
        border: none;
        color: var(--accent, #2e8b57);
        font-family: inherit;
        font-size: 0.78rem;
        font-weight: 700;
        cursor: pointer;
        padding: 0;
        text-decoration: underline;
    }
    .dd-link-btn:hover { opacity: 0.8; }

    /* ============================================================
       SELECTED SYMBOLS HEADER + CHIPS
       ============================================================ */
    .dd-selected-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted, #888);
        font-weight: 700;
        margin: var(--spacing-md, 16px) 0 8px 0;
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

    .dd-selected-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        min-height: 44px;
        margin-bottom: var(--spacing-md, 16px);
    }

    /* Scroll container for selected symbols — max height with scroll */
    .dd-selected-list-scroll {
        max-height: 220px;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        padding: 6px 4px 6px 0;
        align-content: flex-start;
    }

    .dd-selected-list-scroll::-webkit-scrollbar { width: 6px; }
    .dd-selected-list-scroll::-webkit-scrollbar-track { background: transparent; }
    .dd-selected-list-scroll::-webkit-scrollbar-thumb {
        background: var(--border-color, #ccc);
        border-radius: 10px;
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

    /* ============================================================
       EMPTY STATE
       ============================================================ */
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

    /* ============================================================
       BUTTONS
       ============================================================ */
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

    /* ============================================================
       PROGRAMMES
       ============================================================ */
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

    /* Delete button on programmes */
    .dd-programme-item .dd-programme-name {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dd-btn-delete {
        flex-shrink: 0;
        background: #e74c3c;
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.15s ease;
    }
    .dd-btn-delete:hover { background: #c0392b; }
    .dd-btn-delete:active { transform: scale(0.97); }
    .dd-btn-delete:disabled { opacity: 0.6; cursor: not-allowed; }

    /* ============================================================
       MODALS (shared shell)
       ============================================================ */
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
        margin-top: var(--spacing-md, 20px);
    }

    /* ============================================================
       SYMBOLS PICKER MODAL
       ============================================================ */
    .dd-symbols-modal-content {
        max-width: 540px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
    }

    .dd-symbols-modal-search-wrap {
        margin-bottom: 10px;
        flex-shrink: 0;
    }

    .dd-symbols-modal-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.78rem;
        color: var(--text-muted, #888);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 8px;
        flex-shrink: 0;
    }

    .dd-symbols-modal-list {
        flex: 1;
        min-height: 180px;
        max-height: 55vh;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        background: var(--bg, #f5f5f5);
        padding: 6px 4px 6px 0;
        margin-bottom: 4px;
    }

    .dd-symbols-modal-list::-webkit-scrollbar { width: 6px; }
    .dd-symbols-modal-list::-webkit-scrollbar-track { background: transparent; }
    .dd-symbols-modal-list::-webkit-scrollbar-thumb {
        background: var(--border-color, #ccc);
        border-radius: 10px;
    }

    .dd-symbols-modal-empty {
        text-align: center;
        padding: 30px 16px;
        font-size: 0.85rem;
        color: var(--text-muted, #888);
        font-style: italic;
    }

    /* Symbol row (checkbox + label) */
    .dd-symbol-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 14px;
        border-radius: var(--radius-sm, 8px);
        cursor: pointer;
        user-select: none;
        transition: background 0.15s ease;
        -webkit-tap-highlight-color: transparent;
        margin: 0 4px 4px 4px;
    }

    .dd-symbol-row:hover {
        background: rgba(46, 139, 87, 0.06);
    }

    .dd-symbol-row.is-checked {
        background: rgba(46, 139, 87, 0.1);
    }

    .dd-symbol-row input[type="checkbox"] {
        width: 20px;
        height: 20px;
        accent-color: var(--accent, #2e8b57);
        cursor: pointer;
        flex-shrink: 0;
        margin: 0;
    }

    .dd-symbol-row-name {
        font-size: 0.92rem;
        font-weight: 600;
        color: var(--text, #222);
        word-break: break-word;
        flex: 1;
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

    /* ============================================================
       PUBLISHED PROGRAMMES
       ============================================================ */
    .dd-published-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
        margin-top: var(--spacing-sm, 12px);
    }

    .dd-published-item {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .dd-published-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .dd-published-name {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text, #222);
        word-break: break-word;
    }

    .dd-published-badge {
        background: var(--accent, #2e8b57);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 3px 10px;
        border-radius: 20px;
        white-space: nowrap;
    }

    .dd-published-investors {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .dd-published-empty {
        font-size: 0.85rem;
        color: var(--text-muted, #888);
        font-style: italic;
        padding: 6px 0;
    }

    .dd-investor-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        background: var(--bg, #f5f5f5);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
    }

    .dd-investor-name {
        font-size: 0.92rem;
        font-weight: 600;
        color: var(--text, #222);
        word-break: break-word;
        flex: 1;
        min-width: 0;
    }

    .dd-investor-stats {
        display: flex;
        gap: 18px;
        flex-shrink: 0;
    }

    .dd-investor-stat {
        display: flex;
        flex-direction: column;
        gap: 2px;
        text-align: right;
        min-width: 80px;
    }

    .dd-investor-stat-label {
        font-size: 0.62rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted, #888);
        font-weight: 600;
    }

    .dd-investor-stat-value {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--text, #222);
    }

    .dd-investor-stat-value.pnl-pos { color: #27ae60; }
    .dd-investor-stat-value.pnl-neg { color: #e74c3c; }
    .dd-investor-stat-value.pnl-neutral { color: var(--text-muted, #888); }

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

    /* ============================================================
       DARK MODE OVERRIDES
       ============================================================ */
    body.dark-mode .dd-card,
    body.dark-mode .dd-programme-item {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .dd-input,
    body.dark-mode .dd-select,
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

    body.dark-mode .dd-subtabs {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .dd-subtab:hover {
        background: rgba(46, 139, 87, 0.15);
    }

    body.dark-mode .dd-subtab.active {
        background: var(--accent, #2e8b57);
    }

    body.dark-mode .dd-visibility-item,
    body.dark-mode .dd-req-item,
    body.dark-mode .dd-published-item,
    body.dark-mode .dd-investor-row {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .dd-req-toggle,
    body.dark-mode .dd-programme-name,
    body.dark-mode .dd-visibility-name,
    body.dark-mode .dd-published-name,
    body.dark-mode .dd-investor-name,
    body.dark-mode .dd-investor-stat-value,
    body.dark-mode .dd-symbol-row-name {
        color: var(--text, #eee);
    }

    body.dark-mode .dd-req-body {
        border-color: var(--border-color, #333);
    }

    body.dark-mode .dd-req-note {
        color: var(--text, #eee);
    }

    body.dark-mode .dd-symbols-modal-list {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }

    body.dark-mode .dd-symbol-row:hover {
        background: rgba(46, 139, 87, 0.12);
    }

    body.dark-mode .dd-symbol-row.is-checked {
        background: rgba(46, 139, 87, 0.18);
    }

    /* ============================================================
       RESPONSIVE
       ============================================================ */
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

        .dd-investor-row {
            flex-direction: column;
            align-items: flex-start;
        }

        .dd-investor-stats {
            width: 100%;
            justify-content: space-between;
        }

        .dd-investor-stat {
            text-align: left;
        }

        .dd-selected-list-scroll {
            max-height: 180px;
        }

        .dd-symbols-modal-content {
            max-width: 100%;
            padding: var(--spacing-md, 20px);
        }

        .dd-symbols-modal-list {
            max-height: 50vh;
        }
    }
</style>


