<?php
/** Shared server-admin feature styling. Uses server_theme_settings.dark_mode (1=dark, 0=light). */
$serverFeatureTheme = !empty($serverAppConfig['dark_mode']) ? 'dark' : 'light';
?>
<style>
:root{color-scheme:light;--bg-color:#faf7ef;--text-color:#302718;--container-bg:#fffdf8;--header-color:#382c18;--primary-color:#b88720;--primary-hover:#946b16;--accent-color:#b88720;--accent-hover:#946b16;--border-color:#e8dec8;--table-header-bg:#f5eedc;--table-even-row-bg:#fcf8ee;--input-bg:#fffefa;--profit-color:#247a4b;--loss-color:#b94335;--warning-bg:#fff4d7;--bg-secondary:#f7f0df;--bg-tertiary:#eee4ce;--text-muted:#82745d;--text-secondary:#5d4d33;--danger-color:#b94335;--success-color:#247a4b;--info-color:#a77a1d;--settings_modal-overlay:rgba(35,27,12,.52);--shadow-color:rgba(71,52,13,.09);--shadow-light:rgba(40,29,8,.24)}
html[data-theme="dark"]{color-scheme:dark;--bg-color:#15130f;--text-color:#f2ead8;--container-bg:#211e17;--header-color:#f7e9c8;--primary-color:#d2a640;--primary-hover:#e0b957;--accent-color:#d2a640;--accent-hover:#e0b957;--border-color:#403722;--table-header-bg:#2e281b;--table-even-row-bg:#262116;--input-bg:#191710;--profit-color:#73c893;--loss-color:#ef8f85;--warning-bg:#413316;--bg-secondary:#1c1912;--bg-tertiary:#302919;--text-muted:#b6a98c;--text-secondary:#ddcfb0;--danger-color:#ef8f85;--success-color:#73c893;--info-color:#d2a640;--settings_modal-overlay:rgba(0,0,0,.78);--shadow-color:rgba(0,0,0,.34);--shadow-light:rgba(0,0,0,.58)}
html[data-theme="light"]{color-scheme:light}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;background:var(--bg-color);color:var(--text-color)}body{padding:18px;line-height:1.5}button,input,select,textarea{font:inherit}.server-feature-wrap{width:min(1500px,100%);margin:0 auto}.settings-container,.risk-dictionary-container{width:100%;max-width:1500px;margin:0 auto;min-height:calc(100vh - 36px);background:transparent;color:var(--text-color)}
.settings-header h2,.risk-dictionary-container h2{color:var(--header-color)}
.settings-sub,.settings-card-sub,.settings-field-hint{color:var(--text-muted)}
.settings-card,.settings-tabs,.settings-modal-container,.risk-dictionary-container .management-header,.recovery-generator-container,.recovery-table-wrapper{background:var(--container-bg)}
.settings-card{border-color:var(--border-color)}.settings-card-header,.settings-card-footer{background:var(--bg-secondary);border-color:var(--border-color)}
.settings-input,.form-input,.form-select{background:var(--input-bg);color:var(--text-color);border-color:var(--border-color)}
.settings-tabs .tab-btn{color:var(--text-secondary)}.settings-tabs .tab-btn.active{color:#fff}
.settings-modal-overlay{background:var(--settings_modal-overlay)}
.recovery-form-group .form-label{color:var(--text-secondary)}.recovery-table th{background:var(--table-header-bg);color:var(--text-color)}.recovery-table td{color:var(--text-color)}
.recovery-table tr:nth-child(even){background:var(--table-even-row-bg)}
@media(max-width:850px){body{padding:12px}.server-feature-wrap{width:100%}}
    /* ============================================
    SETTINGS DASHBOARD STYLES
    ============================================ */
    .settings-container {
        width: 100%;
        min-height: calc(100vh - 100px);
        background: var(--bg-color);
        padding: 15px 0;
    }
    .settings-header h2 {
        margin: 0 0 4px 0;
        font-size: 1.5rem;
        color: var(--text-color);
    }
    .settings-sub {
        margin: 0 0 20px 0;
        font-size: 13px;
        color: #888;
    }
    .settings-sub code,
    .settings-card-sub code,
    .settings-field-hint code {
        background: rgba(127,127,127,0.15);
        padding: 1px 5px;
        border-radius: 4px;
        font-size: 11px;
    }

    /* Tabs */
    .settings-tabs-wrapper {
        position: relative;
        margin-bottom: 15px;
        overflow: hidden;
    }
    .settings-tabs {
        display: flex;
        flex-wrap: nowrap;
        gap: 6px;
        background: var(--bg-secondary);
        border-radius: 10px;
        padding: 6px;
        border: 1px solid var(--border-color);
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .settings-tabs::-webkit-scrollbar { display: none; }
    .settings-tabs .tab-btn {
        flex: 0 0 auto;
        padding: 8px 16px;
        border: none;
        background: transparent;
        color: var(--text-secondary);
        font-size: 13px;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        text-align: center;
        white-space: nowrap;
        position: relative;
    }
    .settings-tabs .tab-btn.active {
        background: var(--accent-color);
        color: white;
    }
    .settings-tabs .tab-btn:hover:not(.active):not(:disabled) {
        background: var(--bg-tertiary);
    }
    .settings-tabs .tab-btn:disabled {
        cursor: not-allowed;
    }
    .tab-badge {
        display: inline-block;
        background: rgba(255,255,255,0.25);
        border-radius: 10px;
        padding: 0 8px;
        font-size: 10px;
        margin-left: 4px;
        font-weight: 700;
    }
    .tab-btn.active .tab-badge {
        background: rgba(255,255,255,0.3);
    }
    .settings-sub-tabs-wrapper { margin-top: -5px; margin-bottom: 15px; }
    .sub-tabs .sub-tab-btn { padding: 6px 12px; font-size: 11px; }

    .tab-content {
        display: none;
        animation: settingsFadeIn 0.3s ease;
    }
    .tab-content.active {
        display: block;
    }

    @keyframes settingsFadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* Cards */
    .settings-card {
        background: var(--container-bg);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .settings-card-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
        background: var(--table-header-bg);
    }
    .settings-card-header h3 {
        margin: 0 0 4px 0;
        font-size: 16px;
        color: var(--text-color);
    }
    .settings-card-sub {
        margin: 0;
        font-size: 12px;
        color: #888;
        line-height: 1.5;
    }
    .settings-card-body { padding: 20px; }
    .settings-card-footer {
        padding: 14px 20px;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        background: var(--bg-secondary);
    }

    /* Buttons */
    .settings-btn {
        padding: 9px 18px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s;
        border: 1px solid var(--border-color);
        background: transparent;
        color: var(--text-color);
    }
    .settings-btn:hover { background: var(--bg-tertiary); }
    .settings-btn-primary {
        background: var(--accent-color);
        color: white;
        border: none;
    }
    .settings-btn-primary:hover { background: var(--accent-hover); }

    /* Fields */
    .settings-field { margin-bottom: 16px; }
    .settings-field label {
        display: block;
        margin-bottom: 6px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-color);
    }
    .settings-input {
        width: 100%;
        padding: 10px 12px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-color);
        font-size: 14px;
        box-sizing: border-box;
        transition: border-color 0.2s;
    }
    .settings-input:focus {
        outline: none;
        border-color: var(--accent-color);
    }
    .settings-field-hint {
        margin-top: 4px;
        font-size: 11px;
        color: #888;
    }
    .settings-field-error {
        margin-top: 8px;
        padding: 10px 12px;
        background: rgba(244, 67, 54, 0.1);
        border-left: 3px solid #f44336;
        color: #f44336;
        font-size: 12px;
        border-radius: 4px;
    }

    /* Payment tiles */
    .payment-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 14px;
    }
    .payment-tile {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        padding: 18px 16px;
        text-align: center;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .payment-tile:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.08);
    }
    .payment-tile-icon {
        font-size: 28px;
        margin-bottom: 8px;
        color: var(--accent-color);
        font-weight: 700;
    }
    .payment-tile-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #888;
        margin-bottom: 8px;
        font-weight: 700;
    }
    .payment-tile-value {
        font-size: 12px;
        font-family: monospace;
        word-break: break-all;
        color: var(--text-color);
        line-height: 1.4;
    }
    .payment-tile-sub {
        margin-top: 8px;
        font-size: 11px;
        color: #888;
    }

    /* Brokers */
    .broker-pairs-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .broker-pair-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        transition: background 0.2s;
    }
    .broker-pair-row:hover { background: var(--bg-tertiary); }
    .broker-pair-index {
        flex-shrink: 0;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--accent-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
    }
    .broker-pair-body { flex: 1; min-width: 0; }
    .broker-pair-name {
        font-weight: 700;
        font-size: 14px;
        color: var(--text-color);
        margin-bottom: 2px;
    }
    .broker-pair-link a {
        font-size: 12px;
        color: var(--accent-color);
        text-decoration: none;
        word-break: break-all;
    }
    .broker-pair-link a:hover { text-decoration: underline; }
    .broker-pair-nolink {
        font-size: 12px;
        color: #f44336;
        font-style: italic;
    }
    .broker-pair-actions { flex-shrink: 0; }
    .broker-delete-btn {
        padding: 6px 14px;
        border-radius: 6px;
        border: 1px solid #f44336;
        background: transparent;
        color: #f44336;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .broker-delete-btn:hover {
        background: #f44336;
        color: white;
    }
    .settings-empty {
        text-align: center;
        padding: 40px 20px;
        color: #888;
        font-size: 13px;
    }

    /* ============================================
       TIER LIMIT STYLES
       ============================================ */
    .tier-records-list {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .tier-record-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        overflow: hidden;
        transition: box-shadow 0.2s;
    }
    .tier-record-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .tier-record-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: var(--table-header-bg);
        border-bottom: 1px solid var(--border-color);
        gap: 10px;
        flex-wrap: wrap;
    }
    .tier-record-title {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .tier-record-key {
        font-weight: 700;
        font-size: 14px;
        color: var(--accent-color);
        background: rgba(46, 204, 113, 0.1);
        padding: 3px 12px;
        border-radius: 20px;
        font-family: monospace;
    }
    .tier-record-actions {
        display: flex;
        gap: 8px;
    }
    .tier-save-btn {
        padding: 5px 14px;
        border-radius: 6px;
        border: 1px solid var(--accent-color);
        background: transparent;
        color: var(--accent-color);
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .tier-save-btn:hover {
        background: var(--accent-color);
        color: white;
    }
    .tier-delete-btn {
        padding: 5px 14px;
        border-radius: 6px;
        border: 1px solid #f44336;
        background: transparent;
        color: #f44336;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .tier-delete-btn:hover {
        background: #f44336;
        color: white;
    }
    .tier-record-body {
        padding: 12px 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .tier-inline-row {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .tier-inline-label {
        flex: 0 0 160px;
        font-size: 12px;
        font-weight: 600;
        color: var(--text-secondary);
        font-family: monospace;
        word-break: break-all;
    }
    .tier-inline-input {
        flex: 1;
        min-width: 140px;
    }
    .tier-empty-msg {
        text-align: center;
        color: #888;
        font-size: 12px;
        padding: 8px;
        font-style: italic;
    }

    /* Fields matrix */
    .tier-fields-matrix {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .tier-matrix {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: max-content;
    }
    .tier-matrix-row {
        display: flex;
        gap: 8px;
        align-items: stretch;
    }
    .tier-matrix-header {
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        color: #888;
        padding-bottom: 6px;
        border-bottom: 1px solid var(--border-color);
    }
    .tier-matrix-cell {
        display: flex;
        align-items: center;
        padding: 4px 0;
    }
    .tier-matrix-fieldname-cell {
        flex: 0 0 200px;
        min-width: 200px;
    }
    .tier-matrix-entry-cell {
        flex: 0 0 180px;
        min-width: 180px;
    }
    .tier-matrix-actions-cell {
        flex: 0 0 160px;
        min-width: 160px;
        gap: 6px;
    }
    .tier-matrix-fieldname-input,
    .tier-matrix-value-input {
        width: 100%;
        font-size: 12px;
        padding: 7px 9px;
    }

    /* Tier field list (legacy, kept for safety) */
    .tier-fields-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .tier-field-list-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        gap: 12px;
    }
    .tier-field-list-name {
        font-weight: 600;
        font-size: 13px;
        color: var(--text-color);
        font-family: monospace;
    }
    .tier-field-list-actions {
        display: flex;
        gap: 8px;
    }
    .tier-field-edit-btn {
        padding: 5px 14px;
        border-radius: 6px;
        border: 1px solid var(--accent-color);
        background: transparent;
        color: var(--accent-color);
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .tier-field-edit-btn:hover {
        background: var(--accent-color);
        color: white;
    }
    .tier-field-delete-btn {
        padding: 5px 14px;
        border-radius: 6px;
        border: 1px solid #f44336;
        background: transparent;
        color: #f44336;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .tier-field-delete-btn:hover {
        background: #f44336;
        color: white;
    }

    .tier-modal-fields-title {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #888;
        margin: 10px 0 12px 0;
        padding-bottom: 6px;
        border-bottom: 1px solid var(--border-color);
    }

    /* Modals */
    .settings-modal-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        z-index: 5000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .settings-modal-container {
        background: var(--container-bg);
        border-radius: 12px;
        max-width: 95%;
        width: 450px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        border: 1px solid var(--border-color);
        box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        overflow: hidden;
        animation: settingsModalSlide 0.3s ease;
    }
    .settings-modal-container.settings-modal-small { width: 450px; }
    .settings-modal-container.settings-modal-medium { width: 520px; }
    @keyframes settingsModalSlide {
        from { transform: translateY(-30px) scale(0.95); opacity: 0; }
        to   { transform: translateY(0) scale(1); opacity: 1; }
    }
    .settings-modal-header {
        padding: 16px 20px;
        background: var(--table-header-bg);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }
    .settings-modal-header span {
        font-weight: 700;
        font-size: 15px;
    }
    .settings-modal-close {
        cursor: pointer;
        font-size: 18px;
        font-weight: 700;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: var(--bg-tertiary);
        transition: all 0.2s;
        flex-shrink: 0;
    }
    .settings-modal-close:hover {
        background: var(--accent-color);
        color: white;
    }
    .settings-modal-body {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
    }
    .settings-modal-body p {
        margin: 0 0 12px 0;
        font-size: 14px;
        color: var(--text-secondary);
    }
    .settings-modal-buttons {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 16px;
    }
    .settings-modal-buttons-padded {
        padding: 0 20px 20px 20px;
        margin-top: 0;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .settings-tabs .tab-btn { font-size: 11px; padding: 6px 10px; }
        .sub-tabs .sub-tab-btn { font-size: 10px; padding: 5px 8px; }
        .settings-card-body { padding: 14px; }
        .settings-card-header,
        .settings-card-footer { padding: 12px 14px; }
        .settings-modal-container { width: 100%; margin: 10px; max-height: 95vh; }
        .settings-modal-container.settings-modal-medium { width: 100%; }
        .payment-grid { grid-template-columns: 1fr; }
        .broker-pair-row { flex-wrap: wrap; }
        .broker-pair-actions { width: 100%; text-align: right; }
        .tier-record-header { flex-direction: column; align-items: flex-start; }
        .tier-inline-row { flex-direction: column; align-items: flex-start; gap: 4px; }
        .tier-inline-label { flex: 0 0 auto; }
        .tier-matrix-fieldname-cell,
        .tier-matrix-entry-cell,
        .tier-matrix-actions-cell { flex: 0 0 150px; min-width: 150px; }
    }
    @media (max-width: 480px) {
        .settings-tabs .tab-btn { font-size: 10px; padding: 5px 8px; }
        .broker-pair-row { padding: 10px; }
        .broker-pair-index { width: 26px; height: 26px; font-size: 11px; }
    }

    /* ============================================
    RISK DICTIONARY STYLES
    ============================================ */

    .risk-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        flex-wrap: wrap;
    }

    .risk-tab-btn {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        color: var(--text-color);
        cursor: pointer;
        padding: 10px 20px;
        font-size: 14px;
        font-weight: bold;
        border-radius: 8px 8px 0 0;
        transition: all 0.3s ease;
        margin: 0;
        width: auto;
    }

    .risk-tab-btn:hover {
        background: var(--bg-tertiary);
        transform: translateY(-2px);
    }

    .risk-tab-btn.active {
        background: var(--accent-color);
        color: white;
        border-color: var(--accent-color);
    }

    .risk-tab-content {
        display: none;
    }

    .risk-tab-content.active {
        display: block;
    }

    /* Recovery Table Styles */
    .recovery-generator-container {
        padding: 15px 20px;
        background: var(--bg-secondary);
        border-radius: 8px;
        margin-top: 15px;
    }

    .recovery-form-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px 20px;
        align-items: end;
    }

    .recovery-form-group {
        margin-bottom: 0;
    }

    .recovery-form-group .form-label {
        font-weight: bold;
        margin-bottom: 4px;
        display: block;
        font-size: 12px;
        opacity: 0.8;
    }

    .recovery-form-group .form-select,
    .recovery-form-group .form-input {
        width: 100%;
        padding: 6px 10px;
        border: 1px solid var(--border-color);
        background: var(--input-bg);
        color: var(--text-color);
        border-radius: 4px;
        font-size: 13px;
        box-sizing: border-box;
        height: 34px;
    }

    .recovery-form-group .form-select:focus,
    .recovery-form-group .form-input:focus {
        outline: none;
        border-color: var(--accent-color);
    }

    .recovery-form-group small {
        display: none;
    }

    .recovery-table-wrapper {
        margin-top: 15px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        overflow: hidden;
        background: var(--bg-secondary);
        overflow-x: auto;
    }

    .recovery-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        min-width: 600px;
    }

    .recovery-table th,
    .recovery-table td {
        padding: 8px 12px;
        text-align: center;
        border-bottom: 1px solid var(--border-color);
        min-width: 60px;
        vertical-align: middle;
    }

    .recovery-table th {
        background: var(--table-header-bg);
        font-weight: bold;
        position: sticky;
        top: 0;
        z-index: 10;
        font-size: 11px;
    }

    .recovery-table tr:hover {
        background: var(--bg-tertiary);
    }

    .recovery-table td:first-child {
        font-weight: bold;
    }

    /* Trade Cell Styles */
    .trade-cell {
        text-align: left !important;
        padding: 10px 12px !important;
        min-width: 180px;
        vertical-align: top;
    }

    .trade-data {
        display: flex;
        flex-direction: column;
        gap: 2px;
        font-size: 11px;
        line-height: 1.5;
    }

    .trade-row {
        display: flex;
        align-items: baseline;
        gap: 4px;
        flex-wrap: wrap;
    }

    .trade-label {
        font-weight: bold;
        opacity: 0.7;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .trade-value {
        font-weight: 600;
        color: var(--text-color);
    }

    .trade-sequence {
        font-family: 'Courier New', monospace;
        font-size: 10px;
        color: #f39c12;
        font-weight: bold;
        padding: 4px 0 4px 8px;
        border-left: 2px solid #f39c12;
        margin: 2px 0 4px 0;
        line-height: 1.6;
        word-break: break-word;
    }

    .trade-sequence br {
        display: block;
        content: '';
    }

    .risk-cap-value {
        color: #e74c3c;
        font-weight: bold;
        font-size: 12px;
    }

    .management-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 20px;
        background: var(--table-header-bg);
        border-bottom: 1px solid var(--border-color);
        flex-wrap: wrap;
        gap: 8px;
    }

    .management-header h3 {
        margin: 0;
        font-size: 16px;
    }

    .header-buttons {
        display: flex;
        gap: 8px;
    }

    .refresh-risk-btn {
        padding: 6px 14px;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.2s;
        height: 32px;
    }

    .refresh-risk-btn:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
    }

    /* Responsive */
    @media (max-width: 992px) {
        .recovery-form-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px 15px;
        }

        .trade-cell {
            min-width: 150px;
            padding: 8px 10px !important;
        }

        .trade-sequence {
            font-size: 9px;
        }
    }

    @media (max-width: 768px) {
        .recovery-form-grid {
            grid-template-columns: 1fr;
            gap: 8px;
        }

        .recovery-table {
            font-size: 10px;
            min-width: 500px;
        }
        
        .recovery-table th,
        .recovery-table td {
            padding: 4px 6px;
            min-width: 40px;
        }

        .trade-cell {
            min-width: 120px;
            padding: 6px 8px !important;
        }

        .trade-data {
            font-size: 10px;
        }

        .trade-sequence {
            font-size: 8px;
            padding: 2px 0 2px 6px;
        }

        .trade-label {
            font-size: 9px;
        }

        .recovery-form-group .form-select,
        .recovery-form-group .form-input {
            height: 30px;
            font-size: 12px;
            padding: 4px 8px;
        }

        .recovery-form-group .form-label {
            font-size: 11px;
        }
    }

    @media (max-width: 480px) {
        .recovery-table {
            font-size: 9px;
            min-width: 400px;
        }
        
        .recovery-table th,
        .recovery-table td {
            padding: 3px 4px;
            min-width: 30px;
        }

        .trade-cell {
            min-width: 100px;
            padding: 4px 6px !important;
        }

        .trade-data {
            font-size: 9px;
        }

        .trade-sequence {
            font-size: 7px;
            padding: 2px 0 2px 4px;
        }

        .trade-label {
            font-size: 8px;
        }

        .management-header h3 {
            font-size: 14px;
        }
    }

/* Revision 3: complete theme-aware feature surfaces for configuration and risk tools. */
body{background:var(--bg-color)!important;color:var(--text-color)!important}
.server-feature-wrap{width:min(1500px,100%);max-width:1500px;margin:0 auto}
.settings-container{width:100%;max-width:1500px;margin:0 auto;padding:8px 0 30px;background:transparent!important}
.settings-header{display:flex;flex-direction:column;gap:4px;margin:0 0 18px;padding:0 2px}
.settings-header h2{margin:0;color:var(--header-color);font-size:clamp(22px,2.2vw,29px);font-weight:800;letter-spacing:-.035em;text-align:left}
.settings-sub{margin:0;color:var(--text-muted);font-size:13px;line-height:1.55}
.settings-tabs-wrapper,.settings-sub-tabs-wrapper{width:100%;max-width:100%;margin-bottom:14px}
.settings-tabs{background:var(--bg-secondary)!important;border:1px solid var(--border-color)!important;border-radius:12px!important;box-shadow:0 4px 14px var(--shadow-color)}
.settings-tabs .tab-btn{color:var(--text-secondary)!important}
.settings-tabs .tab-btn.active{background:var(--accent-color)!important;color:#fff!important}
.settings-card{background:var(--container-bg)!important;border:1px solid var(--border-color)!important;border-radius:15px!important;box-shadow:0 6px 22px var(--shadow-color)!important;overflow:hidden}
.settings-card-header{background:var(--bg-secondary)!important;border-bottom:1px solid var(--border-color)!important;padding:17px 20px!important}
.settings-card-header h3{color:var(--header-color)!important;font-size:16px!important;font-weight:800!important}
.settings-card-sub,.settings-field-hint{color:var(--text-muted)!important}
.settings-card-body{padding:20px!important}
.settings-card-footer{background:var(--bg-secondary)!important;border-top:1px solid var(--border-color)!important}
.settings-field label{color:var(--text-color)!important}
.settings-input,.settings-field input,.settings-field select,.settings-field textarea{background:var(--input-bg)!important;color:var(--text-color)!important;border-color:var(--border-color)!important}
.settings-input:focus,.settings-field input:focus,.settings-field select:focus,.settings-field textarea:focus{outline:none;border-color:var(--accent-color)!important;box-shadow:0 0 0 3px color-mix(in srgb,var(--accent-color) 18%,transparent)!important}
.settings-btn{border-color:var(--border-color)!important;color:var(--text-color)!important;background:var(--bg-secondary)!important;border-radius:9px!important}
.settings-btn-primary{background:var(--accent-color)!important;border-color:var(--accent-color)!important;color:#fff!important}
.settings-btn-primary:hover{background:var(--accent-hover)!important}
.settings-empty{background:var(--bg-secondary)!important;color:var(--text-muted)!important;border:1px dashed var(--border-color)!important;border-radius:10px!important;padding:24px!important;text-align:center}
.payment-tile{background:var(--bg-secondary)!important;border:1px solid var(--border-color)!important;border-radius:12px!important;color:var(--text-color)!important;min-width:0;overflow-wrap:anywhere}
.payment-tile-label,.payment-tile-sub{color:var(--text-muted)!important}
.payment-tile-value{color:var(--header-color)!important;overflow-wrap:anywhere}
.settings-modal-overlay{background:var(--settings_modal-overlay)!important;backdrop-filter:blur(5px)}
.settings-modal-container{background:var(--container-bg)!important;color:var(--text-color)!important;border:1px solid var(--border-color)!important;border-radius:16px!important;box-shadow:0 18px 60px var(--shadow-light)!important}
.settings-modal-header{background:var(--bg-secondary)!important;color:var(--header-color)!important;border-bottom:1px solid var(--border-color)!important}
.settings-modal-body{color:var(--text-color)!important}
.settings-modal-close{color:var(--text-muted)!important}
.settings-field-error{background:color-mix(in srgb,var(--danger-color) 10%,var(--container-bg))!important;border-color:var(--danger-color)!important;color:var(--danger-color)!important}
.settings-table,.settings-table table,.tier-table{background:var(--container-bg)!important;color:var(--text-color)!important;border-color:var(--border-color)!important}
.settings-table th,.settings-table td,.tier-table th,.tier-table td{border-color:var(--border-color)!important}
.settings-table th,.tier-table th{background:var(--table-header-bg)!important;color:var(--text-muted)!important}
.risk-dictionary-container{width:100%;max-width:1500px;margin:0 auto 28px;padding:0 0 24px;background:transparent!important;color:var(--text-color)!important}
body>h2,.server-feature-wrap>h2{max-width:1500px;margin:0 auto 18px!important;color:var(--header-color)!important;font-size:clamp(22px,2.2vw,29px)!important;font-weight:800!important;letter-spacing:-.035em;text-align:left!important}
.risk-dictionary-container>h2{color:var(--header-color)!important;text-align:left!important}
.risk-dictionary-container .management-header{background:var(--bg-secondary)!important;border:1px solid var(--border-color)!important;border-radius:14px 14px 0 0;padding:17px 20px!important}
.risk-dictionary-container .management-header h3{color:var(--header-color)!important;font-size:16px!important;font-weight:800!important}
.recovery-generator-container{margin-top:0!important;padding:20px!important;background:var(--container-bg)!important;border:1px solid var(--border-color)!important;border-top:0!important;border-radius:0 0 14px 14px!important;box-shadow:0 5px 20px var(--shadow-color)!important}
.recovery-form-grid{grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:14px!important}
.recovery-form-group{min-width:0}
.recovery-form-group .form-label{font-size:12px!important;font-weight:750!important;color:var(--text-secondary)!important;opacity:1!important}
.recovery-form-group .form-input,.recovery-form-group .form-select{height:42px!important;padding:9px 11px!important;border:1px solid var(--border-color)!important;border-radius:9px!important;background:var(--input-bg)!important;color:var(--text-color)!important;font-size:14px!important}
.recovery-form-group .form-input:focus,.recovery-form-group .form-select:focus{border-color:var(--accent-color)!important;box-shadow:0 0 0 3px color-mix(in srgb,var(--accent-color) 18%,transparent)!important}
.recovery-table-wrapper{margin-top:16px!important;background:var(--container-bg)!important;border:1px solid var(--border-color)!important;border-radius:14px!important;box-shadow:0 5px 20px var(--shadow-color)!important;overflow:auto!important}
.recovery-table{background:var(--container-bg)!important;color:var(--text-color)!important}
.recovery-table th{background:var(--table-header-bg)!important;color:var(--header-color)!important;border-color:var(--border-color)!important}
.recovery-table td{background:transparent;color:var(--text-color)!important;border-color:var(--border-color)!important}
.recovery-table tr:nth-child(even){background:var(--bg-secondary)!important}
.recovery-table tr:hover{background:var(--bg-tertiary)!important}
.table-responsive{width:100%;overflow-x:auto;overscroll-behavior-x:contain}
.table-responsive table{min-width:600px}
.trade-value{color:var(--text-color)!important}
.trade-sequence{color:var(--accent-color)!important;border-color:var(--accent-color)!important}
@media(max-width:1050px){.recovery-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}}
@media(max-width:700px){body{padding:12px!important}.settings-card-body{padding:14px!important}.settings-card-header,.settings-card-footer{padding:14px!important}.recovery-generator-container{padding:14px!important}.recovery-form-grid{grid-template-columns:1fr!important;gap:10px!important}.settings-card-footer{flex-wrap:wrap}.settings-card-footer .settings-btn{flex:1 1 130px}}


/* Mobile and compact-tablet treatment for all standalone Server App feature pages. */
@media(max-width:850px){
 body{padding:12px!important}
 .server-feature-wrap,.settings-container,.risk-dictionary-container{width:100%!important;max-width:100%!important;margin:0 auto!important}
 .settings-container{padding:8px 0 20px!important}
 .settings-header{margin-bottom:12px!important}
 .settings-header h2,.risk-dictionary-container>h2{font-size:clamp(20px,5vw,26px)!important}
 .settings-tabs-wrapper,.settings-sub-tabs-wrapper{margin-bottom:10px!important}
 .settings-tabs{gap:5px!important;padding:5px!important;overflow-x:auto!important;scroll-snap-type:x proximity!important}
 .settings-tabs .tab-btn{font-size:12px!important;padding:9px 11px!important;min-height:40px!important;scroll-snap-align:start}
 .settings-card{border-radius:12px!important;margin-bottom:12px!important}
 .settings-card-header,.settings-card-footer{padding:13px 14px!important}
 .settings-card-body{padding:14px!important}
 .settings-field{min-width:0!important}
 .settings-input,.form-input,.form-select{width:100%!important;min-height:42px!important;font-size:16px!important}
 .settings-card-footer{display:flex!important;gap:8px!important;flex-wrap:wrap!important}
 .settings-card-footer .settings-btn{flex:1 1 145px!important;min-height:42px!important}
 .settings-modal-overlay{padding:12px!important;align-items:flex-end!important}
 .settings-modal-container{width:100%!important;max-width:100%!important;max-height:88dvh!important;overflow:auto!important;border-radius:16px 16px 0 0!important}
 .settings-modal-body{padding:15px!important}
 .settings-modal-buttons{display:flex!important;gap:8px!important;flex-wrap:wrap!important}
 .settings-modal-buttons .settings-btn{flex:1 1 120px!important;min-height:43px!important}
 .recovery-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}
 .recovery-table-wrapper,.table-responsive{max-width:100%!important;overflow-x:auto!important;-webkit-overflow-scrolling:touch!important}
 .recovery-table{min-width:680px!important}
 .settings-table,.tier-table{display:block!important;max-width:100%!important;overflow-x:auto!important;-webkit-overflow-scrolling:touch!important}
}
@media(max-width:560px){
 body{padding:9px!important}
 .settings-card-header h3{font-size:15px!important}
 .settings-card-sub,.settings-field-hint{font-size:11px!important}
 .settings-tabs{border-radius:9px!important}
 .settings-tabs .tab-btn{font-size:11px!important;padding:8px 10px!important}
 .settings-card-body{padding:12px!important}
 .settings-card-footer .settings-btn{flex:1 1 100%!important;width:100%!important}
 .recovery-form-grid{grid-template-columns:1fr!important}
 .recovery-generator-container{padding:11px!important}
 .recovery-table{min-width:600px!important;font-size:12px!important}
 .risk-dictionary-container .management-header{padding:13px!important}
 .recovery-table-wrapper{border-radius:10px!important}
}


/* Final shared Server App skin: gold accents, refined touch targets, hidden scrollbars. */
:root{--primary-color:#b88720;--primary-hover:#946b16;--accent-color:#b88720;--accent-hover:#946b16;--info-color:#a77a1d}
html[data-theme="dark"]{--primary-color:#d2a640;--primary-hover:#e0b957;--accent-color:#d2a640;--accent-hover:#e0b957;--info-color:#d2a640}
*{scrollbar-width:none!important;-ms-overflow-style:none!important}
*::-webkit-scrollbar{width:0!important;height:0!important;display:none!important}
html,body{background:var(--bg-color)!important;color:var(--text-color)!important}
.settings-container,.risk-dictionary-container{max-width:1500px!important}
.settings-card,.settings-tabs,.settings-modal-container,.recovery-generator-container,.recovery-table-wrapper{border-color:var(--border-color)!important}
.settings-btn-primary,.settings-tabs .tab-btn.active,.tab-btn.active,.tier-save-btn,.settings-modal-buttons .settings-btn-primary{background:var(--primary-color)!important;border-color:var(--primary-color)!important;color:#21180a!important}
.settings-btn-primary:hover,.settings-tabs .tab-btn.active:hover,.tab-btn.active:hover,.tier-save-btn:hover{background:var(--primary-hover)!important;border-color:var(--primary-hover)!important;color:#21180a!important}
.settings-input:focus,.form-input:focus,.form-select:focus{border-color:var(--accent-color)!important;box-shadow:0 0 0 3px color-mix(in srgb,var(--accent-color) 18%,transparent)!important;outline:0!important}
a{color:var(--accent-color)}
.settings-tabs .tab-btn{border-radius:10px!important;transition:background .16s,color .16s,transform .16s!important}
.settings-btn,.broker-delete-btn,.tier-save-btn,.tier-field-delete-btn,.settings-tabs .tab-btn{transition:transform .16s,box-shadow .16s,background .16s!important}
.settings-btn:active,.broker-delete-btn:active,.tier-save-btn:active,.settings-tabs .tab-btn:active{transform:scale(.98)}
.settings-card{box-shadow:0 8px 26px var(--shadow-color)!important}
.settings-card-header{border-bottom-color:var(--border-color)!important}
.broker-pair-row{border-color:var(--border-color)!important;background:var(--bg-secondary)!important;border-radius:12px!important}
.broker-pair-index{background:var(--gold-soft,#f2e5c2)!important;color:var(--text-color)!important}
@media(max-width:850px){
 body{padding:10px!important}
 .settings-container{padding:7px 0 22px!important;min-height:calc(100dvh - 20px)!important}
 .settings-header{padding:4px 2px 8px!important}
 .settings-header h2{font-size:clamp(21px,5vw,27px)!important;letter-spacing:-.03em!important}
 .settings-sub{font-size:12px!important;line-height:1.55!important}
 .settings-tabs-wrapper{margin:0 0 12px!important}
 .settings-tabs{display:flex!important;flex-wrap:nowrap!important;gap:6px!important;padding:6px!important;border:1px solid var(--border-color)!important;border-radius:13px!important;overflow-x:auto!important;overflow-y:hidden!important;overscroll-behavior-x:contain!important;scroll-snap-type:x proximity!important}
 .settings-tabs .tab-btn{flex:0 0 auto!important;min-height:42px!important;padding:9px 12px!important;font-size:12px!important;white-space:nowrap!important;scroll-snap-align:start!important}
 .settings-card{overflow:hidden!important;border-radius:15px!important;margin-bottom:13px!important}
 .settings-card-header,.settings-card-footer{padding:14px 15px!important}
 .settings-card-body{padding:15px!important}
 .settings-field{min-width:0!important}
 .settings-input,.form-input,.form-select{min-height:44px!important;font-size:16px!important;border-radius:11px!important}
 .settings-card-footer{gap:8px!important;flex-wrap:wrap!important}
 .settings-card-footer .settings-btn{flex:1 1 140px!important;min-height:44px!important}
 .settings-modal-overlay{padding:0!important;align-items:flex-end!important}
 .settings-modal-container{width:100%!important;max-width:100%!important;max-height:90dvh!important;overflow:auto!important;border-radius:20px 20px 0 0!important;padding-bottom:env(safe-area-inset-bottom)!important}
 .settings-modal-body{padding:16px!important}
 .settings-modal-buttons{gap:9px!important}
 .settings-modal-buttons .settings-btn{flex:1 1 125px!important;min-height:44px!important}
 .payment-grid,.settings-form-grid,.recovery-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}
 .broker-pair-row{display:grid!important;grid-template-columns:34px minmax(0,1fr) auto!important;gap:9px!important;padding:11px!important}
 .broker-pair-link{overflow-wrap:anywhere!important;word-break:break-word!important}
 .broker-pair-actions .broker-delete-btn{min-height:38px!important}
 .tier-matrix{min-width:0!important}
 .tier-matrix-row{gap:8px!important}
 .recovery-table-wrapper,.table-responsive,.settings-table,.tier-table{overflow:auto!important;overscroll-behavior-x:contain!important;-webkit-overflow-scrolling:touch!important}
}
@media(max-width:560px){
 body{padding:8px!important}
 .settings-header{padding:3px 3px 8px!important}
 .settings-header h2{font-size:22px!important}
 .settings-card-header,.settings-card-body,.settings-card-footer{padding:12px!important}
 .settings-card-header h3{font-size:15px!important}
 .settings-card-sub,.settings-field-hint{font-size:11px!important}
 .settings-tabs{margin-left:-1px!important;margin-right:-1px!important}
 .settings-tabs .tab-btn{min-height:40px!important;padding:8px 10px!important;font-size:11px!important}
 .payment-grid,.settings-form-grid,.recovery-form-grid{grid-template-columns:1fr!important}
 .settings-card-footer .settings-btn{flex:1 1 100%!important;width:100%!important}
 .broker-pair-row{grid-template-columns:30px minmax(0,1fr)!important}
 .broker-pair-actions{grid-column:2!important;display:flex!important;justify-content:flex-start!important}
 .broker-pair-actions .broker-delete-btn{width:100%!important}
 .settings-modal-container{max-height:92dvh!important}
 .settings-modal-header{padding:14px 16px!important}
 .recovery-generator-container{padding:11px!important}
 .recovery-table{min-width:600px!important;font-size:12px!important}
}



/* ============================================================
   HARVHUB GOLD / MONOCHROME SKIN — shared feature-page finish
   Light mode: clean white surfaces. Dark mode: true black surfaces.
   Gold is reserved for actions, selected states and small accents.
   ============================================================ */
:root{
 --bg-color:#ffffff!important;
 --text-color:#171717!important;
 --container-bg:#ffffff!important;
 --header-color:#141414!important;
 --primary-color:#c99b36!important;
 --primary-hover:#ad7e21!important;
 --accent-color:#c99b36!important;
 --accent-hover:#ad7e21!important;
 --border-color:#e8e5df!important;
 --table-header-bg:#ffffff!important;
 --table-even-row-bg:#ffffff!important;
 --input-bg:#ffffff!important;
 --profit-color:#21834d!important;
 --loss-color:#c44135!important;
 --warning-bg:#fff7e5!important;
 --bg-secondary:#ffffff!important;
 --bg-tertiary:#f8f7f4!important;
 --text-muted:#77736c!important;
 --text-secondary:#46433d!important;
 --danger-color:#c44135!important;
 --success-color:#21834d!important;
 --info-color:#a77a1d!important;
 --gold-soft:#fff5dc!important;
 --gold-border:rgba(201,155,54,.38)!important;
 --settings_modal-overlay:rgba(0,0,0,.55)!important;
 --shadow-color:rgba(20,18,12,.07)!important;
 --shadow-light:rgba(20,18,12,.18)!important;
 color-scheme:light;
}
html[data-theme="dark"]{
 --bg-color:#000000!important;
 --text-color:#f5f5f5!important;
 --container-bg:#000000!important;
 --header-color:#ffffff!important;
 --primary-color:#d5aa43!important;
 --primary-hover:#e4bd5d!important;
 --accent-color:#d5aa43!important;
 --accent-hover:#e4bd5d!important;
 --border-color:#292929!important;
 --table-header-bg:#000000!important;
 --table-even-row-bg:#000000!important;
 --input-bg:#050505!important;
 --profit-color:#75d69a!important;
 --loss-color:#ff8278!important;
 --warning-bg:#171208!important;
 --bg-secondary:#000000!important;
 --bg-tertiary:#101010!important;
 --text-muted:#aaa49a!important;
 --text-secondary:#d5d1ca!important;
 --danger-color:#ff8278!important;
 --success-color:#75d69a!important;
 --info-color:#e0b64f!important;
 --gold-soft:#171208!important;
 --gold-border:rgba(213,170,67,.42)!important;
 --settings_modal-overlay:rgba(0,0,0,.82)!important;
 --shadow-color:rgba(0,0,0,.48)!important;
 --shadow-light:rgba(0,0,0,.72)!important;
 color-scheme:dark;
}
html,body{width:100%;max-width:100%;background:var(--bg-color)!important;color:var(--text-color)!important}
body{padding:18px clamp(14px,2vw,24px) 28px!important;line-height:1.55!important;overflow-x:hidden!important}
.server-feature-wrap{width:min(1500px,100%)!important;max-width:1500px!important;margin:0 auto!important;padding:4px 2px 28px!important}
.settings-container,.risk-dictionary-container{width:100%!important;max-width:1500px!important;margin:0 auto!important;background:transparent!important;color:var(--text-color)!important}
.settings-container{padding:8px 0 28px!important}
.settings-header,.risk-dictionary-container>h2,.volume-head,.vps-head,.head{
 background:var(--container-bg)!important;color:var(--text-color)!important;
 border:1px solid var(--border-color)!important;border-radius:17px!important;
 padding:19px 22px!important;margin:0 0 17px!important;
 box-shadow:0 8px 28px var(--shadow-color)!important;
}
.settings-header h2,.risk-dictionary-container>h2,.volume-head h1,.vps-head h1,.head h1{
 color:var(--header-color)!important;margin:0 0 5px!important;font-weight:800!important;
 letter-spacing:-.035em!important;line-height:1.2!important;
}
.settings-sub,.volume-head p,.vps-head p,.head p{color:var(--text-muted)!important;margin:5px 0 0!important;line-height:1.55!important}
.settings-tabs,.tabs,.vps-tabs{
 background:var(--container-bg)!important;border:1px solid var(--border-color)!important;
 border-radius:14px!important;padding:6px!important;gap:6px!important;
 box-shadow:0 4px 16px var(--shadow-color)!important;
}
.settings-tabs .tab-btn,.tab,.vps-tab,.vps-subtab{
 color:var(--text-secondary)!important;border-radius:10px!important;
 transition:background .18s ease,color .18s ease,transform .18s ease,box-shadow .18s ease!important;
}
.settings-tabs .tab-btn.active,.tab.active,.vps-tab.active,.vps-subtab.active{
 background:var(--accent-color)!important;color:#17120a!important;
 border-color:var(--accent-color)!important;box-shadow:0 4px 12px rgba(201,155,54,.2)!important;
}
.settings-card,.panel,.volume-panel,.volume-card,.vps-card,.settings-modal-container,
.recovery-generator-container,.recovery-table-wrapper,.payment-tile,.metric,.settings-empty{
 background:var(--container-bg)!important;color:var(--text-color)!important;
 border-color:var(--border-color)!important;box-shadow:0 7px 24px var(--shadow-color)!important;
}
.settings-card,.panel,.volume-panel,.volume-card,.vps-card,.payment-tile{border-radius:16px!important}
.settings-card-header,.settings-card-footer,.panel-head,.volume-panel-head,.vps-card-head,.settings-modal-header{
 background:var(--container-bg)!important;color:var(--text-color)!important;border-color:var(--border-color)!important;
}
.settings-card-header,.vps-card-head{padding:18px 20px!important}
.settings-card-header h3,.panel-head h2,.volume-panel-head h2,.vps-card-head h2{color:var(--header-color)!important;font-weight:800!important}
.settings-card-sub,.settings-field-hint,.vps-card-head p{color:var(--text-muted)!important}
.settings-card-body,.vps-card-body{background:var(--container-bg)!important;color:var(--text-color)!important}
.settings-input,.settings-field input,.settings-field select,.settings-field textarea,
.form-input,.form-select,.vps-field input,.vps-field select,.vps-field textarea,.vps-search,
.volume-search,.panel-head input,.settings-modal-container input,.settings-modal-container select{
 background:var(--input-bg)!important;color:var(--text-color)!important;border-color:var(--border-color)!important;
 border-radius:11px!important;min-height:44px!important;
}
.settings-input:focus,.settings-field input:focus,.settings-field select:focus,.settings-field textarea:focus,
.form-input:focus,.form-select:focus,.vps-field input:focus,.vps-field select:focus,.vps-field textarea:focus,
.volume-search:focus,.panel-head input:focus{
 border-color:var(--accent-color)!important;box-shadow:0 0 0 3px rgba(201,155,54,.16)!important;outline:0!important;
}
.settings-btn-primary,.settings-modal-buttons .settings-btn-primary,.settings-tabs .tab-btn.active,
.tab.active,.vps-tab.active,.vps-subtab.active,.tier-save-btn,.vps-btn:not(.secondary):not(.danger),
.btn:not(.ghost),button.primary,.primary-btn,.btn-primary,.config-entry-buttons button,
.add-new-form button,.back-btn,.toggle-btn{
 background:var(--accent-color)!important;border-color:var(--accent-color)!important;color:#17120a!important;
 font-weight:800!important;box-shadow:0 5px 14px rgba(201,155,54,.18)!important;
}
.settings-btn-primary:hover,.tier-save-btn:hover,.vps-btn:not(.secondary):not(.danger):hover,
.btn:not(.ghost):hover,.primary-btn:hover,.btn-primary:hover,.config-entry-buttons button:hover,
.add-new-form button:hover,.back-btn:hover,.toggle-btn:hover{
 background:var(--accent-hover)!important;border-color:var(--accent-hover)!important;color:#17120a!important;
}
.settings-btn,.vps-btn.secondary,.btn.ghost,.settings-modal-buttons .settings-btn:not(.settings-btn-primary){
 background:var(--container-bg)!important;color:var(--text-color)!important;border:1px solid var(--border-color)!important;
}
.settings-btn:hover,.vps-btn.secondary:hover,.btn.ghost:hover{border-color:var(--accent-color)!important;background:var(--bg-tertiary)!important}
button,a,.settings-btn,.vps-btn,.tab,.vps-tab,.settings-tabs .tab-btn{ -webkit-tap-highlight-color:transparent }
button:active,.settings-btn:active,.vps-btn:active,.btn:active,.tab:active,.vps-tab:active{transform:scale(.985)!important}
.settings-modal-overlay,.vps-modal-backdrop{background:var(--settings_modal-overlay)!important;backdrop-filter:blur(7px)!important}
.settings-modal-container,.vps-modal{background:var(--container-bg)!important;color:var(--text-color)!important;border-color:var(--border-color)!important;border-radius:18px!important}
.settings-table,.settings-table table,.tier-table,.recovery-table,.volume-table,.vps-table,
table{background:var(--container-bg)!important;color:var(--text-color)!important;border-color:var(--border-color)!important}
.settings-table th,.tier-table th,.recovery-table th,.volume-table th,.vps-table th,th{
 background:var(--table-header-bg)!important;color:var(--text-muted)!important;border-color:var(--border-color)!important;
}
.settings-table td,.tier-table td,.recovery-table td,.volume-table td,.vps-table td,td{color:var(--text-color)!important;border-color:var(--border-color)!important}
.settings-table tr:nth-child(even),.tier-table tr:nth-child(even),.recovery-table tr:nth-child(even),.volume-table tr:nth-child(even){background:var(--bg-secondary)!important}
.broker-pair-row{background:var(--container-bg)!important;border-color:var(--border-color)!important;border-radius:13px!important}
.broker-pair-index,.menu-card .ico{background:var(--gold-soft)!important;color:var(--accent-color)!important;border:1px solid var(--gold-border)!important}
.metric.active{outline:2px solid var(--accent-color)!important;border-color:var(--accent-color)!important}
.volume-card{position:relative;overflow:hidden}
.volume-card:before{content:"";position:absolute;inset:0 0 auto;height:3px;background:var(--accent-color)}
.vps-status,.volume-badge,.pill.none{background:var(--bg-tertiary)!important;color:var(--text-secondary)!important}
a{color:var(--accent-color)}
*{scrollbar-width:none!important;-ms-overflow-style:none!important}
*::-webkit-scrollbar{width:0!important;height:0!important;display:none!important}

@media(max-width:850px){
 body{padding:14px 13px 26px!important}
 .server-feature-wrap{padding:3px 1px 24px!important;width:100%!important}
 .settings-container{padding:6px 0 24px!important}
 .settings-header,.risk-dictionary-container>h2,.volume-head,.vps-head,.head{
  padding:16px 17px!important;border-radius:16px!important;margin-bottom:14px!important;gap:12px!important;
 }
 .settings-header h2,.risk-dictionary-container>h2,.volume-head h1,.vps-head h1,.head h1{font-size:clamp(20px,5vw,25px)!important}
 .settings-sub,.volume-head p,.vps-head p,.head p{font-size:12px!important}
 .settings-tabs,.tabs,.vps-tabs{display:flex!important;flex-wrap:nowrap!important;overflow-x:auto!important;overflow-y:hidden!important;overscroll-behavior-x:contain!important;scroll-snap-type:x proximity!important;margin-bottom:13px!important}
 .settings-tabs .tab-btn,.tab,.vps-tab{flex:0 0 auto!important;min-height:44px!important;padding:10px 13px!important;font-size:12px!important;scroll-snap-align:start!important}
 .settings-card,.panel,.volume-panel,.volume-card,.vps-card{border-radius:15px!important;margin-bottom:13px!important}
 .settings-card-header,.settings-card-footer,.panel-head,.volume-panel-head,.vps-card-head{padding:15px 16px!important}
 .settings-card-body,.vps-card-body{padding:16px!important}
 .settings-field{min-width:0!important}
 .settings-input,.form-input,.form-select,.vps-field input,.vps-field select,.vps-field textarea{width:100%!important;font-size:16px!important;min-height:46px!important}
 .settings-card-footer{display:flex!important;flex-wrap:wrap!important;gap:9px!important}
 .settings-card-footer .settings-btn{flex:1 1 145px!important;min-height:45px!important}
 .payment-grid,.settings-form-grid,.recovery-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:11px!important}
 .vps-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:11px!important}
 .volume-cards{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:10px!important}
 .volume-card{padding:15px!important}.volume-card strong{font-size:23px!important}
 .metrics{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:9px!important}
 .metric{padding:13px!important;border-radius:13px!important}
 .table-scroll,.table-responsive,.recovery-table-wrapper,.volume-table-wrap,.vps-table-wrap,
 .settings-table,.tier-table{max-width:100%!important;overflow-x:auto!important;overflow-y:hidden!important;overscroll-behavior-x:contain!important;-webkit-overflow-scrolling:touch!important}
 .settings-modal-overlay,.vps-modal-backdrop{padding:12px!important;align-items:flex-end!important}
 .settings-modal-container,.vps-modal{width:100%!important;max-width:100%!important;max-height:90dvh!important;overflow:auto!important;border-radius:20px 20px 0 0!important;padding-bottom:env(safe-area-inset-bottom)!important}
 .settings-modal-body{padding:16px!important}
 .settings-modal-buttons{display:flex!important;gap:9px!important;flex-wrap:wrap!important}
 .settings-modal-buttons .settings-btn{flex:1 1 125px!important;min-height:45px!important}
 .broker-pair-row{display:grid!important;grid-template-columns:34px minmax(0,1fr) auto!important;gap:10px!important;padding:12px!important}
 .broker-pair-link{overflow-wrap:anywhere!important;word-break:break-word!important}
 .recovery-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}
}
@media(max-width:560px){
 body{padding:13px 12px 24px!important}
 .server-feature-wrap{padding:2px 0 22px!important}
 .settings-header,.risk-dictionary-container>h2,.volume-head,.vps-head,.head{padding:15px 15px!important;border-radius:15px!important}
 .settings-header h2,.risk-dictionary-container>h2,.volume-head h1,.vps-head h1,.head h1{font-size:21px!important}
 .settings-card-header,.settings-card-body,.settings-card-footer,.panel-head,.volume-panel-head,.vps-card-head,.vps-card-body{padding:13px!important}
 .settings-card-header h3{font-size:15px!important}
 .settings-card-sub,.settings-field-hint{font-size:11px!important}
 .settings-tabs,.tabs,.vps-tabs{border-radius:12px!important;padding:5px!important}
 .settings-tabs .tab-btn,.tab,.vps-tab{min-height:43px!important;padding:9px 11px!important;font-size:11px!important}
 .payment-grid,.settings-form-grid,.recovery-form-grid,.vps-grid,.volume-cards{grid-template-columns:1fr!important}
 .metrics{grid-template-columns:repeat(2,minmax(0,1fr))!important}
 .settings-card-footer .settings-btn{flex:1 1 100%!important;width:100%!important}
 .broker-pair-row{grid-template-columns:30px minmax(0,1fr)!important}
 .broker-pair-actions{grid-column:2!important;display:flex!important;justify-content:flex-start!important}
 .broker-pair-actions .broker-delete-btn{width:100%!important;min-height:42px!important}
 .recovery-generator-container{padding:13px!important}
 .risk-dictionary-container .management-header{padding:15px!important}
 .recovery-table{min-width:600px!important;font-size:12px!important}
 .volume-card strong{font-size:22px!important}
 .head{align-items:flex-start!important}
 .head .btn{width:100%!important;min-height:44px!important}
 .vps-actions{display:grid!important;grid-template-columns:1fr 1fr!important}
 .vps-actions .vps-btn{min-height:44px!important}
}

</style>
<script>
(function(){
  window.__serverAppActive = true;
  window.addEventListener('message', function(event){
    if (event.origin !== window.location.origin || !event.data || event.data.type !== 'serverapp:module-state') return;
    window.__serverAppActive = !!event.data.active;
    if (event.data.theme) document.documentElement.setAttribute('data-theme', event.data.theme);
    window.dispatchEvent(new CustomEvent('serverapp:activechange', {detail:{active:window.__serverAppActive}}));
  });
})();
</script>

<script>
/* Any expired CSRF form is recovered automatically; no manual reload prompt. */
(function(){if(window.__serverFetchExpiryHook)return;window.__serverFetchExpiryHook=true;const nativeFetch=window.fetch.bind(window);window.fetch=function(){return nativeFetch.apply(null,arguments).then(function(response){if(response.status===419){if(!window.__serverExpiryReloading){window.__serverExpiryReloading=true;window.location.reload();}}return response;});};})();
</script>
