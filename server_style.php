<style>
    * {
        box-sizing: border-box;
    }
    html, body {
        margin: 0;
        padding: 0;
        height: 100%;
        overflow: hidden;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
        background-color: #121212;
        color: #e0e0e0;
        -webkit-tap-highlight-color: transparent;
    }

    #custom-body {
        height: 100vh;
        width: 100vw;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        padding: 16px;
        padding-bottom: 30px;
        background-color: #121212;
        scroll-behavior: smooth;
    }

    :root {
        --bg-color: #121212;
        --text-color: #e0e0e0;
        --container-bg: #1e1e1e;
        --header-color: #ffffff;
        --primary-color: #4CAF50;
        --primary-hover: #45a049;
        --accent-color: #FF9800;
        --accent-hover: #fb8c00;
        --border-color: #333333;
        --table-header-bg: #282828;
        --table-even-row-bg: #242424;
        --input-bg: #222222;
        --profit-color: #4CAF50;
        --loss-color: #e74c3c;
        --warning-bg: rgba(255, 152, 0, 0.15);
        --bg-secondary: #1e1e1e;
        --bg-tertiary: #2a2a2a;
        --text-muted: #888888;
    }

    @media (prefers-color-scheme: light) {
        :root {
            --bg-color: #f4f4f9;
            --text-color: #333;
            --container-bg: white;
            --header-color: #2c3e50;
            --primary-color: #4CAF50;
            --primary-hover: #45a049;
            --accent-color: #e67e22;
            --accent-hover: #d35400;
            --border-color: #ddd;
            --table-header-bg: #f2f2f2;
            --table-even-row-bg: #f9f9f9;
            --input-bg: white;
            --profit-color: #27ae60;
            --loss-color: #c0392b;
            --warning-bg: #fff3e0;
            --bg-secondary: #f8f8f8;
            --bg-tertiary: #f0f0f0;
            --text-muted: #999999;
        }
        html, body, #custom-body { background-color: var(--bg-color); }
    }

    .container { 
        max-width: 1200px; 
        margin: 0 auto; 
        background: none; 
        padding: 20px; 
        margin-bottom: 100px; 
        border-radius: 2px; 
        box-shadow: 0 4px 20px rgba(0,0,0,0.3); 
        transition: background 0.3s; 
    }
    .login-container { max-width: 400px; }
    h2, h3, h4 { color: var(--header-color); margin-bottom: 16px; }
    h2 { text-align: center; font-size: 1.6rem; }
    h3 { margin-top: 20px; text-align: left; font-size: 1.2rem; }
    h4 { font-size: 1rem; margin: 15px 0 10px; }
    
    .message { margin-bottom: 20px; padding: 10px; border-radius: 8px; text-align: center; font-weight: bold; }
    .message span[style*="red"] { background-color: #3d0000 !important; border: 1px solid #c00 !important; display: block; padding: 10px; color: #ff5555 !important; }
    .message span[style*="green"] { background-color: #003d00 !important; border: 1px solid #0c0 !important; display: block; padding: 10px; color: #55ff55 !important; }
    .message span[style*="orange"] { background-color: #3d2d00 !important; border: 1px solid #ff9800 !important; display: block; padding: 10px; color: #ffc107 !important; }

    label { display: block; margin-top: 15px; font-weight: 600; font-size: 0.9rem; }
    input[type="text"], input[type="password"], input[type="number"], select, textarea {
        width: 100%; 
        padding: 12px; 
        margin-top: 5px; 
        border: 1px solid var(--border-color); 
        border-radius: 10px; 
        background-color: var(--input-bg); 
        color: var(--text-color);
        font-size: 16px;
    }
    textarea { resize: vertical; min-height: 100px; }
    
    button {
        display: block; 
        width: 100%; 
        padding: 14px; 
        margin-top: 20px; 
        background-color: var(--primary-color); 
        color: white; 
        border: none; 
        border-radius: 10px; 
        cursor: pointer; 
        font-size: 16px; 
        font-weight: bold;
        transition: background-color 0.2s;
        -webkit-appearance: none;
    }
    button:hover { background-color: var(--primary-hover); }
    
    .credentials-section { border-top: 2px dashed var(--border-color); margin-top: 30px; padding-top: 20px; display: none; }
    .credentials-section.active { display: block; }
    .logout-link { display: block; text-align: center; margin-top: 20px; color: #e74c3c; text-decoration: none; font-weight: bold; }
    
    .toggle-btn { margin-bottom: 20px; }
    .toggle-btn, .back-btn { background-color: var(--accent-color); margin-top: 20px; }
    .toggle-btn:hover, .back-btn:hover { background-color: var(--accent-hover); }
    .back-btn { 
        display: inline-block; 
        width: auto; 
        padding: 10px 20px; 
        margin-bottom: 15px; 
        text-decoration: none; 
        border-radius: 30px; 
        font-size: 14px; 
        color: white;
    }
    
    .nav-menu a {
        display: block;
        background-color: var(--accent-color);
        color: white;
        padding: 18px;
        text-align: center;
        margin-bottom: 14px;
        border-radius: 14px;
        text-decoration: none;
        font-weight: bold;
        font-size: 1.1rem;
        transition: background-color 0.2s;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .nav-menu a:hover { background-color: var(--accent-hover); }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }
    .settings-card {
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 15px;
    }

    .list-management { margin-top: 20px; border: 1px solid var(--border-color); padding: 15px; border-radius: 12px; }
    .list-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dotted #444; }
    .list-item:last-child { border-bottom: none; }
    .list-item span { word-break: break-word; margin-right: 10px; }
    .list-item-btn { 
        width: auto !important; 
        padding: 8px 16px !important; 
        margin: 0 0 0 10px !important; 
        font-size: 14px !important; 
        background-color: #e74c3c !important; 
        border-radius: 8px !important;
        flex-shrink: 0;
    }
    .list-item-btn:hover { background-color: #c0392b !important; }
    .add-new-form { display: flex; flex-wrap: wrap; margin-top: 15px; gap: 10px; }
    .add-new-form input[type="text"] { flex: 1 1 200px; margin-top: 0; }
    .add-new-form button { width: auto; padding: 12px 20px; margin: 0; }

    .section-divider {
        margin: 40px 0 20px;
        border-top: 2px solid var(--border-color);
        position: relative;
    }
    .section-divider span {
        position: absolute;
        top: -12px;
        left: 20px;
        background-color: var(--container-bg);
        padding: 0 15px;
        color: var(--accent-color);
        font-weight: bold;
        font-size: 1.1rem;
    }

    .modal { 
        display: none; 
        position: fixed; 
        z-index: 1000; 
        left: 0; top: 0; 
        width: 100%; height: 100%; 
        background-color: rgba(0,0,0,0.6); 
        backdrop-filter: blur(5px);
        align-items: center;
        justify-content: center;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s;
        padding: 20px;
    }
    .modal.show { display: flex; opacity: 1; pointer-events: all; }
    .modal-content { 
        background-color: var(--container-bg); 
        padding: 25px; 
        border-radius: 20px; 
        box-shadow: 0 10px 40px rgba(0,0,0,0.5); 
        width: 100%; max-width: 360px; 
        text-align: center; 
        transform: scale(0.9);
        transition: transform 0.3s;
    }
    .modal.show .modal-content { transform: scale(1); }
    .modal-content h3 { color: #e74c3c; margin-top: 0; }
    .modal-content p { margin-bottom: 20px; }
    .modal-content input[type="password"] { margin: 10px 0 20px; background-color: #222; border-color: #444; }
    .modal-buttons { display: flex; gap: 12px; }
    .modal-buttons button { width: 48%; margin-top: 0; }
    #modal-cancel-btn { background-color: #7f8c8d; }

    hr { border: 0; border-top: 1px solid var(--border-color); margin: 30px 0; }

    @media (max-width: 600px) {
        #custom-body { padding: 10px; }
        .container { padding: 16px; }
        h2 { font-size: 1.4rem; }
        .toggle-btn { margin-bottom: 100px; }
        .table-wrapper {
            margin-bottom: 100px;
        }
    }
    
</style>

<style>
    /* Account Management Styles */
    .account-management-container,
    .invested-management-container,
    .execution-management-container,
    .user-viewer-container {
        margin-top: 20px;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        overflow-x: auto;
        overflow-y: visible;
        position: relative;
        width: 100%;
        background: var(--bg-secondary);
    }

    /* Search Bar Styles */
    .management-search {
        margin-bottom: 20px;
        padding: 0 10px;
    }
    
    .search-wrapper {
        position: relative;
        max-width: 500px;
        margin: 0 auto;
    }
    
    .global-search-input {
        width: 100%;
        padding: 12px 40px 12px 20px;
        border: 2px solid var(--border-color);
        border-radius: 50px;
        background: var(--input-bg);
        color: var(--text-color);
        font-size: 14px;
        transition: all 0.3s ease;
    }
    
    .global-search-input:focus {
        outline: none;
        border-color: var(--accent-color);
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }
    
    .clear-search-btn {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #888;
        font-size: 18px;
        cursor: pointer;
        padding: 0;
        width: auto;
        transition: color 0.2s;
    }
    
    .clear-search-btn:hover {
        color: var(--accent-color);
        background: none;
        transform: translateY(-50%);
    }

    .management-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        background: var(--table-header-bg);
        border-bottom: 1px solid var(--border-color);
        flex-wrap: wrap;
        gap: 10px;
    }

    .management-header h3 {
        margin: 0;
    }

    .header-buttons {
        display: flex;
        gap: 10px;
    }

    .edit-json-btn-header,
    .copy-json-btn-header,
    .cancel-edit-btn,
    .refresh-invested-btn,
    .refresh-verified-btn,
    .refresh-pending-btn,
    .refresh-suspended-btn,
    .refresh-justjoined-btn,
    .refresh-justjoinedvalid-btn,
    .refresh-approved-btn,
    .save-settings-btn {
        width: auto;
        padding: 8px 16px;
        font-size: 14px;
        margin: 0;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .edit-json-btn-header {
        background: #27ae60;
        color: white;
    }

    .edit-json-btn-header:hover {
        background: #229954;
        transform: translateY(-1px);
    }

    .copy-json-btn-header {
        background: #3498db;
        color: white;
    }

    .copy-json-btn-header:hover {
        background: #2980b9;
        transform: translateY(-1px);
    }

    .cancel-edit-btn {
        background: #e74c3c;
        color: white;
    }

    .cancel-edit-btn:hover {
        background: #c0392b;
        transform: translateY(-1px);
    }
    
    .refresh-invested-btn,
    .refresh-verified-btn,
    .refresh-pending-btn,
    .refresh-suspended-btn,
    .refresh-justjoined-btn,
    .refresh-justjoinedvalid-btn,
    .refresh-approved-btn {
        background: #3498db;
        color: white;
    }
    
    .refresh-invested-btn:hover,
    .refresh-verified-btn:hover,
    .refresh-pending-btn:hover,
    .refresh-suspended-btn:hover,
    .refresh-justjoined-btn:hover,
    .refresh-justjoinedvalid-btn:hover,
    .refresh-approved-btn:hover {
        background: #2980b9;
        transform: translateY(-1px);
    }

    /* User View Table Styles */
    .user-viewer-container,
    .invested-management-container {
        overflow-x: auto;
    }
    
    .user-view-table,
    .invested-users-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    
    .user-view-table th,
    .user-view-table td,
    .invested-users-table th,
    .invested-users-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }
    
    .user-view-table th,
    .invested-users-table th {
        background: var(--table-header-bg);
        font-weight: bold;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .user-view-table tr:hover,
    .invested-users-table tr:hover {
        background: var(--bg-tertiary);
    }
    
    .table-responsive {
        overflow-x: auto;
        width: 100%;
    }
    
    .user-count-badge {
        padding: 10px 20px;
        background: var(--bg-tertiary);
        border-bottom: 1px solid var(--border-color);
        font-size: 13px;
        font-weight: bold;
    }
    
    .source-badge {
        background: var(--accent-color);
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 11px;
        color: white;
    }
    
    .invested-value {
        background: var(--bg-primary);
        padding: 4px 8px;
        border-radius: 6px;
        font-family: monospace;
        font-size: 12px;
    }
    
    .status-enabled {
        color: #2ecc71;
        font-weight: bold;
    }
    
    .status-disabled {
        color: #e74c3c;
        font-weight: bold;
    }
    
    .balance-cell {
        font-family: monospace;
        font-weight: bold;
    }
    
    .mode-badge {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .mode-demo {
        background: #f39c12;
        color: white;
    }
    
    .mode-real {
        background: #27ae60;
        color: white;
    }
    
    /* Split View Styles */
    .split-view {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 20px;
        margin-top: 20px;
        width: 100%;
        overflow: hidden;
    }

    @media (max-width: 768px) {
        .split-view {
            grid-template-columns: 1fr;
        }
        
        .management-tabs {
            flex-wrap: wrap;
        }
        
        .tab-btn {
            flex: 1;
            text-align: center;
            padding: 8px 12px;
            font-size: 12px;
        }
        
        .management-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .user-view-table th,
        .user-view-table td,
        .invested-users-table th,
        .invested-users-table td {
            padding: 8px;
            font-size: 12px;
        }
    }

    .user-list-panel {
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
        max-height: 80vh;
        overflow-y: auto;
    }

    .user-list-panel h3 {
        padding: 15px;
        margin: 0;
        background: var(--table-header-bg);
        border-bottom: 1px solid var(--border-color);
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .user-items {
        max-height: calc(80vh - 60px);
        overflow-y: auto;
    }

    .user-item {
        padding: 12px 15px;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: background 0.2s;
    }

    .user-item:hover {
        background: var(--border-color);
    }

    .user-item.active {
        background: var(--accent-color);
        color: white;
    }

    .user-item-name {
        font-weight: bold;
    }

    .user-item-email {
        font-size: 12px;
        opacity: 0.7;
    }

    .management-panel {
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: 80vh;
        width: 100%;
    }

    .management-panel .management-header {
        position: sticky;
        top: 0;
        z-index: 10;
        background: var(--table-header-bg);
    }

    .management-panel .json-viewer-full {
        overflow-y: auto;
        flex: 1;
        width: 100%;
    }

    .user-info {
        background: var(--bg-tertiary);
        border-radius: 8px;
        padding: 12px 15px;
        margin: 15px;
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        font-size: 13px;
    }

    .user-info-item {
        display: inline-flex;
        align-items: baseline;
        gap: 5px;
    }

    .user-info-label {
        font-weight: bold;
        color: var(--accent-color);
    }

    /* JSON Viewer Styles */
    .json-viewer, .json-viewer-full {
        padding: 20px;
        background: var(--input-bg);
        width: 100%;
        overflow-x: auto;
        overflow-y: auto;
        box-sizing: border-box;
    }

    .json-viewer-full {
        min-height: 500px;
    }

    .json-structure {
        font-family: 'Courier New', 'Monaco', monospace;
        font-size: 13px;
        line-height: 1.6;
        color: var(--text-color);
        background: var(--bg-secondary);
        padding: 20px;
        border-radius: 8px;
        margin: 0;
        white-space: pre;
        overflow-x: auto;
        width: 100%;
        box-sizing: border-box;
    }

    .editor-full-wrapper {
        width: 100%;
        display: block;
    }

    .json-editor-fullwidth {
        width: 100%;
        min-height: 500px;
        padding: 20px;
        background: var(--bg-secondary);
        color: var(--text-color);
        border: 2px solid var(--accent-color);
        border-radius: 8px;
        font-family: 'Courier New', 'Monaco', monospace;
        font-size: 13px;
        line-height: 1.6;
        resize: vertical;
        white-space: pre;
        overflow-x: auto;
        box-sizing: border-box;
    }

    .json-editor-fullwidth:focus {
        outline: none;
        border-color: #27ae60;
    }

    /* Management Tabs */
    .management-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
        border-bottom: 2px solid var(--border-color);
        padding-bottom: 10px;
        flex-wrap: wrap;
    }

    .tab-btn {
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

    .tab-btn:hover {
        background: var(--bg-tertiary);
        transform: translateY(-2px);
    }

    .tab-btn.active {
        background: var(--accent-color);
        color: white;
        border-color: var(--accent-color);
    }

    /* Invested With Edit Styles */
    .current-invested-with code {
        background: var(--bg-primary);
        padding: 4px 8px;
        border-radius: 6px;
        font-family: monospace;
        font-size: 12px;
        display: inline-block;
        word-break: break-all;
    }
    
    .invested-edit-input {
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        color: var(--text-color);
        border-radius: 6px;
        font-family: monospace;
        font-size: 12px;
    }
    
    .invested-edit-input:focus {
        outline: none;
        border-color: var(--accent-color);
    }
    
    .save-invested-btn {
        background: #27ae60;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.2s;
    }
    
    .save-invested-btn:hover {
        background: #229954;
        transform: translateY(-1px);
    }
    
    .save-invested-btn:disabled {
        background: #95a5a6;
        cursor: not-allowed;
        transform: none;
    }

    /* Execution History Styles */
    .execution-management-container {
        margin-top: 20px;
        margin-bottom: 20px;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        background: var(--bg-secondary);
        display: flex;
        flex-direction: column;
        height: calc(100vh - 250px);
        min-height: 400px;
    }

    /* For split-view layout in execution tab */
    #execution-tab .split-view {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 20px;
        margin-top: 0;
        width: 100%;
        overflow: hidden;
        height: calc(100vh - 200px);
    }

    #execution-tab .user-list-panel {
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
        max-height: calc(100vh - 200px);
        overflow-y: auto;
    }

    #execution-tab .management-panel {
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 200px);
        width: 100%;
    }

    .execution-history-container {
        flex: 1;
        overflow-y: auto;
        padding: 0;
    }

    @media (max-width: 768px) {
        #execution-tab .split-view {
            grid-template-columns: 1fr;
            height: auto;
        }
    }

    /* Rest of execution history styles remain the same */
    .execution-timeline {
        padding: 20px;
    }

    .execution-record {
        background: var(--bg-tertiary);
        border-left: 4px solid #3498db;
        border-radius: 8px;
        margin-bottom: 16px;
        padding: 15px;
        transition: all 0.2s;
    }

    .execution-record:hover {
        background: var(--border-color);
        transform: translateX(4px);
    }

    .execution-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .execution-time {
        font-size: 12px;
        color: #888;
        font-family: monospace;
    }

    .execution-message {
        font-size: 14px;
        line-height: 1.5;
        color: var(--text-color);
        word-break: break-word;
    }

    /* Execution History Badge Styles */
    .execution-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .execution-type-badge,
    .execution-update-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: bold;
    }

    .execution-type-info {
        background: #3498db;
        color: white;
    }

    .execution-type-error {
        background: #e74c3c;
        color: white;
    }

    .execution-type-success {
        background: #27ae60;
        color: white;
    }

    .execution-type-warning {
        background: #f39c12;
        color: white;
    }

    .execution-update-default {
        color: white;
    }

    .execution-update-new {
        color: white;
    }

    .execution-update-updated {
        color: white;
    }

    .execution-update-deleted {
        color: white;
    }

    .execution-section {
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid var(--border-color);
        font-size: 12px;
        color: #888;
        font-family: monospace;
    }

    /* Scrollbar styling for execution history container */
    .execution-history-container::-webkit-scrollbar {
        width: 8px;
    }

    .execution-history-container::-webkit-scrollbar-track {
        background: var(--bg-tertiary);
        border-radius: 4px;
    }

    .execution-history-container::-webkit-scrollbar-thumb {
        background: var(--accent-color);
        border-radius: 4px;
    }

    .execution-history-container::-webkit-scrollbar-thumb:hover {
        background: #27ae60;
    }

    /* Auto Trading Settings Styles */
    .auto-trading-settings-container {
        padding: 20px;
        background: var(--bg-secondary);
        border-radius: 12px;
        min-height: 500px;
        overflow-y: auto;
        max-height: 70vh;
    }

    .auto-trading-settings {
        max-width: 600px;
        margin: 0 auto;
        padding-bottom: 30px;
    }

    .user-info-settings {
        background: var(--bg-tertiary);
        border-radius: 8px;
        padding: 15px;
        margin-top: 30px;
        margin-bottom: 30px;
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }

    .user-info-settings .user-info-item {
        display: inline-flex;
        align-items: baseline;
        gap: 5px;
    }

    .setting-card {
        background: var(--bg-tertiary);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid var(--border-color);
    }

    .setting-label {
        margin-bottom: 15px;
    }

    .setting-label label {
        font-weight: bold;
        font-size: 16px;
        display: block;
        margin-bottom: 5px;
    }

    .setting-description {
        font-size: 12px;
        color: #888;
    }

    .setting-control {
        margin-top: 10px;
    }

    .setting-select {
        width: 100%;
        max-width: 300px;
        padding: 10px 15px;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        color: var(--text-color);
        border-radius: 8px;
        font-size: 14px;
        cursor: pointer;
    }

    .setting-select:focus {
        outline: none;
        border-color: var(--accent-color);
    }

    .save-settings-btn:hover {
        background: #229954;
        transform: translateY(-1px);
    }

    /* Scrollbar Styling */
    .json-viewer::-webkit-scrollbar,
    .json-viewer-full::-webkit-scrollbar,
    .account-management-container::-webkit-scrollbar,
    .json-structure::-webkit-scrollbar,
    .json-editor-fullwidth::-webkit-scrollbar,
    .user-viewer-container::-webkit-scrollbar {
        height: 10px;
        width: 10px;
    }

    .json-viewer::-webkit-scrollbar-track,
    .json-viewer-full::-webkit-scrollbar-track,
    .account-management-container::-webkit-scrollbar-track,
    .json-structure::-webkit-scrollbar-track,
    .json-editor-fullwidth::-webkit-scrollbar-track,
    .user-viewer-container::-webkit-scrollbar-track {
        background: var(--bg-tertiary);
        border-radius: 5px;
    }

    .json-viewer::-webkit-scrollbar-thumb,
    .json-viewer-full::-webkit-scrollbar-thumb,
    .account-management-container::-webkit-scrollbar-thumb,
    .json-structure::-webkit-scrollbar-thumb,
    .json-editor-fullwidth::-webkit-scrollbar-thumb,
    .user-viewer-container::-webkit-scrollbar-thumb {
        background: var(--accent-color);
        border-radius: 5px;
    }

    .json-viewer::-webkit-scrollbar-thumb:hover,
    .json-viewer-full::-webkit-scrollbar-thumb:hover,
    .account-management-container::-webkit-scrollbar-thumb:hover,
    .json-structure::-webkit-scrollbar-thumb:hover,
    .json-editor-fullwidth::-webkit-scrollbar-thumb:hover,
    .user-viewer-container::-webkit-scrollbar-thumb:hover {
        background: #27ae60;
    }
    /* Status Badge Styles */
    .status-badge-default {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: bold;
        background: #95a5a6;
        color: white;
    }

    .status-badge-approved {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: bold;
        background: #27ae60;
        color: white;
    }

    .status-badge-declined {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: bold;
        background: #e74c3c;
        color: white;
    }

    .status-badge-pending {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: bold;
        background: #f39c12;
        color: white;
    }

    .status-badge-suspended {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: bold;
        background: #e67e22;
        color: white;
    }

    .status-badge-blacklisted {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: bold;
        background: #2c3e50;
        color: white;
    }

    .status-select {
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        background: var(--input-bg);
        color: var(--text-color);
        font-size: 13px;
        cursor: pointer;
    }

    .status-select:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .update-status-small-btn {
        padding: 6px 12px;
        background: #27ae60;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        margin-left: 8px;
        width: auto;
    }

    .update-status-small-btn:hover {
        background: #229954;
        transform: translateY(-1px);
    }

    .update-status-small-btn:disabled {
        background: #95a5a6;
        cursor: not-allowed;
        transform: none;
    }
    /* Unauthorized Actions Badge Styles */
    /* Unauthorized Actions Badge Styles */
    .unauthorized-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        text-align: center;
        min-width: 30px;
    }

    .unauthorized-present {
        background: #e74c3c;
        color: white;
    }

    .unauthorized-none {
        background: #27ae60;
        color: white;
    }

    /* Bypass badge */
    .bypass-enabled-badge {
        background: #f39c12;
        color: white;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: bold;
        display: inline-block;
    }
    /* Config Entries Grid Styles */
    .config-entries-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
        gap: 20px;
        padding: 20px;
    }

    .config-entry-card {
        background: var(--bg-tertiary);
        border: 2px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .config-entry-card:hover {
        border-color: var(--accent-color);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .config-entry-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        background: var(--table-header-bg);
        border-bottom: 1px solid var(--border-color);
        flex-wrap: wrap;
        gap: 10px;
    }

    .config-entry-title {
        font-weight: bold;
        font-size: 16px;
        color: var(--accent-color);
        word-break: break-all;
    }

    .config-entry-buttons {
        display: flex;
        gap: 8px;
    }

    .config-entry-buttons button {
        padding: 6px 12px;
        font-size: 12px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
    }

    .edit-config-btn {
        background: #3498db;
        color: white;
    }

    .edit-config-btn:hover {
        background: #2980b9;
        transform: translateY(-1px);
    }

    .copy-config-btn {
        background: #9b59b6;
        color: white;
    }

    .copy-config-btn:hover {
        background: #8e44ad;
        transform: translateY(-1px);
    }

    .delete-config-btn {
        background: #e74c3c;
        color: white;
    }

    .delete-config-btn:hover {
        background: #c0392b;
        transform: translateY(-1px);
    }

    .save-config-btn {
        background: #27ae60;
        color: white;
    }

    .save-config-btn:hover {
        background: #229954;
        transform: translateY(-1px);
    }

    .cancel-config-btn {
        background: #95a5a6;
        color: white;
    }

    .cancel-config-btn:hover {
        background: #7f8c8d;
        transform: translateY(-1px);
    }

    .config-entry-content {
        padding: 15px;
        max-height: 400px;
        overflow-y: auto;
    }

    .config-json-view {
        background: var(--bg-secondary);
        padding: 15px;
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-size: 12px;
        white-space: pre-wrap;
        word-break: break-word;
        overflow-x: auto;
    }

    .config-json-editor {
        width: 100%;
        min-height: 300px;
        padding: 15px;
        background: var(--bg-secondary);
        color: var(--text-color);
        border: 2px solid var(--accent-color);
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-size: 12px;
        resize: vertical;
        white-space: pre;
        overflow-x: auto;
    }

    .config-json-editor:focus {
        outline: none;
        border-color: #27ae60;
    }

    /* Add Config Modal */
    .add-config-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        z-index: 10000;
        justify-content: center;
        align-items: center;
    }

    .add-config-modal.show {
        display: flex;
    }

    .add-config-modal .modal-content {
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 25px;
        min-width: 400px;
        max-width: 600px;
    }

    .add-config-modal input {
        width: 100%;
        padding: 12px;
        margin: 15px 0;
        border: 1px solid var(--border-color);
        background: var(--input-bg);
        color: var(--text-color);
        border-radius: 6px;
    }
    /* Key Editor Styles */
    .config-key-editor {
        width: 100%;
        padding: 8px 12px;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        color: var(--text-color);
        border-radius: 6px;
        font-family: monospace;
        font-size: 13px;
    }

    .config-key-editor:focus {
        outline: none;
        border-color: var(--accent-color);
    }

    .config-key-editor:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Action Column Styles */
        .action-cell {
            min-width: 150px;
        }

        .contract-action-select {
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--input-bg);
            color: var(--text-color);
            font-size: 12px;
            cursor: pointer;
            margin-right: 8px;
        }

        .apply-action-btn {
            padding: 6px 12px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .apply-action-btn:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .apply-action-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }

        .contract-action-select:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

    /* Terminal Path Styles - Updated for better wrapping */
    .terminal-path-value {
        background: var(--bg-primary);
        padding: 4px 8px;
        border-radius: 6px;
        font-family: monospace;
        font-size: 11px;
        display: block;
        word-break: break-all;
        white-space: normal;
        line-height: 1.4;
        max-width: 250px;
        min-width: 100px;
        cursor: help;
    }

    /* Optional: Add a tooltip style for full path on hover */
    .terminal-path-value:hover {
        background: var(--accent-color);
        color: white;
    }

    .contract-days-cell {
        font-family: monospace;
        font-weight: bold;
        text-align: center;
    }

    .contract-days-cell:empty:before {
        content: "-";
        color: #888;
    }

    /* Make the terminal path column wider on larger screens */
    @media (min-width: 1400px) {
        .user-view-table th:nth-child(12),
        .user-view-table td:nth-child(12) {
            min-width: 300px;
        }
        
        .terminal-path-value {
            max-width: 350px;
        }
    }

    @media (max-width: 1200px) {
        .user-view-table th,
        .user-view-table td {
            padding: 8px 6px;
            font-size: 12px;
        }
        
        .terminal-path-value {
            max-width: 180px;
            font-size: 10px;
        }
    }

    @media (max-width: 992px) {
        .user-view-table {
            font-size: 11px;
        }
        
        .terminal-path-value {
            max-width: 150px;
            font-size: 9px;
        }
    }

    @media (max-width: 768px) {
        .terminal-path-value {
            max-width: 120px;
            font-size: 8px;
        }
    }
    @media (max-width: 768px) {
        .config-entries-grid {
            grid-template-columns: 1fr;
        }
        
        .config-entry-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .config-entry-buttons {
            width: 100%;
            justify-content: flex-end;
        }
    }
    /* ==================== FIXED SCROLLABLE LISTS FOR ALL TABS ==================== */

    /* User Configuration Tab - User List */
    #users-tab .user-list-panel {
        max-height: 550px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    #users-tab .user-list-panel h3 {
        flex-shrink: 0;
    }

    #user-items-list {
        flex: 1;
        overflow-y: auto;
        min-height: 200px;
    }

    /* Invested With Tab */
    #invested-tab .invested-management-container {
        max-height: 550px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    #invested-users-list {
        flex: 1;
        overflow-y: auto;
        min-height: 300px;
    }

    /* Active Investors Tab */
    #verified-tab .user-viewer-container {
        max-height: 550px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    #verified-users-list {
        flex: 1;
        overflow-y: auto;
        min-height: 300px;
    }

    /* Pending Users Tab */
    #pending-tab .user-viewer-container {
        max-height: 550px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    #pending-users-list {
        flex: 1;
        overflow-y: auto;
        min-height: 300px;
    }

    /* Suspended Users Tab */
    #suspended-tab .user-viewer-container {
        max-height: 550px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    #suspended-users-list {
        flex: 1;
        overflow-y: auto;
        min-height: 300px;
    }

    /* Just Joined Users Tab */
    #justjoined-tab .user-viewer-container {
        max-height: 550px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    #justjoined-users-list {
        flex: 1;
        overflow-y: auto;
        min-height: 300px;
    }

    /* Just Joined & Valid Tab */
    #justjoinedvalid-tab .user-viewer-container {
        max-height: 550px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    #justjoinedvalid-users-list {
        flex: 1;
        overflow-y: auto;
        min-height: 300px;
    }

    /* Approved Users Tab */
    #approved-tab .user-viewer-container {
        max-height: 550px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    #approved-users-list {
        flex: 1;
        overflow-y: auto;
        min-height: 300px;
    }

    /* Bypassed Users Tab */
    #bypassed-tab .user-viewer-container {
        max-height: 550px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    #bypassed-users-list {
        flex: 1;
        overflow-y: auto;
        min-height: 300px;
    }

    /* Auto Trading Tab - User List Panel */
    #autotrading-tab .user-list-panel {
        max-height: 550px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    #autotrading-tab .user-list-panel h3 {
        flex-shrink: 0;
    }

    #autotrading-user-list {
        flex: 1;
        overflow-y: auto;
        min-height: 200px;
    }

    /* Execution History Tab - User List Panel */
    #execution-tab .user-list-panel {
        max-height: 550px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    #execution-tab .user-list-panel h3 {
        flex-shrink: 0;
    }

    #execution-user-list {
        flex: 1;
        overflow-y: auto;
        min-height: 200px;
    }

    /* Ensure table containers are scrollable */
    .user-viewer-table-container,
    .invested-users-table-container {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 450px;
    }

    /* Table wrapper for horizontal scroll */
    .table-responsive {
        overflow-x: auto;
        width: 100%;
    }

    /* Fix for user-items container */
    .user-items {
        overflow-y: auto;
        max-height: none;
        flex: 1;
    }
    /* Make Server Configuration container match Account Management Configurations height */
    #server-tab .account-management-container:first-child .json-viewer {
        max-height: 400px;
        overflow-y: auto;
        padding: 20px;
    }

    /* Ensure the pre element inside scrolls properly */
    #server-tab .account-management-container:first-child .json-structure {
        margin: 0;
        white-space: pre-wrap;
        word-break: break-word;
    }
    /* Collapsible Config Entry Styles */
    .config-entry-header {
        cursor: pointer;
        user-select: none;
    }

    .config-entry-header:hover {
        background: var(--bg-tertiary);
    }

    .config-entry-title-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
    }

    .collapse-icon {
        display: inline-block;
        width: 20px;
        font-size: 14px;
        color: var(--accent-color);
        font-weight: bold;
        transition: transform 0.2s ease;
    }

    .config-entry-title {
        font-weight: bold;
        font-size: 16px;
        color: var(--accent-color);
        word-break: break-all;
    }

    /* Keep buttons clickable without triggering header click */
    .config-entry-buttons button {
        cursor: pointer;
        position: relative;
        z-index: 1;
    }

    .config-entry-buttons button:hover {
        transform: translateY(-1px);
    }

    /* Smooth transition for content */
    .config-entry-content {
        transition: all 0.3s ease;
        overflow: hidden;
    }

    .config-entry-content.show {
        display: block;
    }

    /* Animation for expand/collapse */
    @keyframes fadeSlideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .config-entry-content > * {
        animation: fadeSlideIn 0.2s ease;
    }
</style>

<style>
    /* Analytics specific styles - Mobile First */
    .analytics-container {
        width: 100%;
        min-height: calc(100vh - 100px);
        background: var(--bg-color);
        position: relative;
        transition: filter 0.3s ease;
    }
    
    .analytics-container.blur-background {
        filter: blur(8px);
        pointer-events: none;
    }

    /* Users Sidebar */
    .users-sidebar {
        background: var(--container-bg);
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--border-color);
        margin-bottom: 20px;
    }
    
    .users-sidebar-header {
        padding: 15px;
        background: var(--table-header-bg);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .users-sidebar-header h3 {
        margin: 0;
        font-size: 16px;
    }
    
    .show-all-users-btn {
        padding: 8px 16px;
        background: var(--accent-color);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.2s;
    }
    
    .show-all-users-btn:hover {
        background: var(--accent-hover);
        transform: scale(1.02);
    }
    
    .default-user-card {
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .default-user-info {
        background: var(--bg-tertiary);
        border-radius: 10px;
        padding: 12px;
        cursor: pointer;
        transition: all 0.2s;
        border: 1px solid var(--border-color);
    }
    
    .default-user-info:hover {
        background: var(--bg-secondary);
        transform: translateX(5px);
    }
    
    .default-user-name {
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 5px;
    }
    
    .default-user-email {
        font-size: 11px;
        opacity: 0.7;
        margin-bottom: 3px;
    }
    
    .default-user-id {
        font-size: 10px;
        opacity: 0.5;
    }
    
    .loading-spinner-small {
        text-align: center;
        padding: 20px;
    }
    
    .spinner-small {
        border: 2px solid var(--border-color);
        border-top: 2px solid var(--accent-color);
        border-radius: 50%;
        width: 30px;
        height: 30px;
        animation: spin 1s linear infinite;
        margin: 0 auto 10px;
    }
    
    .info-message-small {
        text-align: center;
        padding: 20px;
        color: #888;
        font-size: 13px;
    }

    /* Floating Stats Button */
    .floating-stats-btn {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--accent-color);
        color: white;
        border: none;
        cursor: pointer;
        font-size: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        transition: all 0.3s ease;
        z-index: 1001;
    }

    .floating-stats-btn:hover {
        transform: scale(1.1);
        background: var(--accent-hover);
    }

    /* Stats Panel Overlay */
    .stats-panel-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .stats-panel-overlay-bg {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
    }
    
    .stats-panel-container {
        position: relative;
        background: var(--container-bg);
        border-radius: 12px;
        width: 90%;
        max-width: 400px;
        max-height: 80vh;
        display: flex;
        flex-direction: column;
        border: 1px solid var(--border-color);
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        z-index: 2001;
        overflow: hidden;
    }
    
    .stats-panel-header {
        padding: 15px 20px;
        background: var(--table-header-bg);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: bold;
        font-size: 16px;
    }
    
    .close-stats-panel {
        cursor: pointer;
        font-size: 20px;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: var(--bg-tertiary);
        transition: all 0.2s;
    }
    
    .close-stats-panel:hover {
        background: var(--accent-color);
        color: white;
    }
    
    .stats-panel-content {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
    }
    
    .stat-option {
        padding: 12px;
        margin: 5px 0;
        cursor: pointer;
        border-radius: 8px;
        transition: all 0.2s;
    }
    
    .stat-option:hover {
        background: var(--bg-tertiary);
    }
    
    .stat-option.active {
        background: var(--primary-color);
        color: white;
    }
    
    .stat-option-title {
        font-weight: bold;
        font-size: 14px;
    }
    
    .stat-option-desc {
        font-size: 11px;
        opacity: 0.7;
    }

    /* Custom Alert */
    .custom-alert {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        z-index: 3000;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.2s ease;
    }
    
    .custom-alert-content {
        background: var(--container-bg);
        border-radius: 12px;
        padding: 25px 30px;
        text-align: center;
        max-width: 300px;
        border: 1px solid var(--border-color);
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    
    .custom-alert-icon {
        font-size: 48px;
        margin-bottom: 15px;
    }
    
    .custom-alert-message {
        font-size: 14px;
        margin-bottom: 20px;
        color: var(--text-color);
    }
    
    .custom-alert-btn {
        padding: 10px 25px;
        background: var(--accent-color);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .custom-alert-btn:hover {
        background: var(--accent-hover);
        transform: scale(1.02);
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    /* Analytics Content */
    .analytics-content {
        background: var(--container-bg);
        border-radius: 12px;
        padding: 15px;
        border: 1px solid var(--border-color);
    }

    @media (min-width: 768px) {
        .analytics-content {
            padding: 20px;
        }
    }

    .analytics-header {
        margin-bottom: 20px;
    }

    .analytics-header h2 {
        margin: 0 0 10px 0;
        font-size: 1.3rem;
    }

    @media (min-width: 768px) {
        .analytics-header h2 {
            font-size: 1.6rem;
        }
    }

    .selected-user-info {
        background: var(--bg-tertiary);
        padding: 12px;
        border-radius: 8px;
        margin-top: 10px;
        font-size: 12px;
        word-break: break-word;
    }

    @media (min-width: 768px) {
        .selected-user-info {
            font-size: 13px;
        }
    }

    /* Custom Select */
    .custom-select {
        margin: 15px 0;
    }

    .custom-select label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        font-size: 13px;
        color: var(--accent-color);
    }

    .custom-select select {
        width: 100%;
        padding: 12px;
        background: var(--input-bg);
        border: 2px solid var(--border-color);
        color: var(--text-color);
        border-radius: 10px;
        font-size: 14px;
        cursor: pointer;
    }

    .custom-select select:focus {
        outline: none;
        border-color: var(--accent-color);
    }

    /* Section Cards */
    .section-card {
        background: var(--bg-tertiary);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid var(--border-color);
    }

    .section-title {
        font-size: 16px;
        font-weight: bold;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--accent-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .section-title small {
        font-size: 11px;
        font-weight: normal;
        color: #888;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin: 10px 0;
    }

    @media (min-width: 480px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (min-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (min-width: 1024px) {
        .stats-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }

    .stat-card {
        background: var(--bg-tertiary);
        border-radius: 10px;
        padding: 10px;
        text-align: center;
        border: 1px solid var(--border-color);
    }

    .stat-card .stat-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #888;
        margin-bottom: 5px;
        word-break: break-word;
    }

    .stat-card .stat-value {
        font-size: 18px;
        font-weight: bold;
        color: var(--accent-color);
        word-break: break-word;
    }

    @media (min-width: 768px) {
        .stat-card .stat-value {
            font-size: 20px;
        }
    }

    .stat-card .stat-value.profit {
        color: var(--profit-color);
    }

    .stat-card .stat-value.loss {
        color: var(--loss-color);
    }

    /* Contest Grid (now for symbols) */
    .contest-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
        margin: 15px 0;
    }

    @media (min-width: 640px) {
        .contest-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    .contest-card {
        background: var(--bg-secondary);
        border-radius: 10px;
        padding: 12px;
        border: 1px solid var(--border-color);
    }

    .contest-card h4 {
        margin: 0 0 8px 0;
        font-size: 13px;
        color: var(--accent-color);
    }

    .contest-card .symbol-info {
        padding: 6px 0;
        border-bottom: 1px solid var(--border-color);
        font-size: 12px;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
    }

    /* Losses Card */
    .losses-card {
        background: var(--bg-secondary);
        border-radius: 10px;
        padding: 15px;
        margin: 15px 0;
        border: 1px solid var(--border-color);
        text-align: center;
    }

    .losses-card h4 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: var(--loss-color);
    }

    .loss-value {
        font-size: 24px;
        font-weight: bold;
        color: var(--loss-color);
    }

    /* Trades Table */
    .trades-table-wrapper {
        overflow-x: auto;
        margin: 15px 0;
    }

    .trades-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
    }

    .trades-table th,
    .trades-table td {
        border: 1px solid var(--border-color);
        padding: 8px 6px;
        text-align: left;
        vertical-align: top;
    }

    .trades-table th {
        background: var(--table-header-bg);
        font-weight: bold;
    }

    /* Info Message */
    .info-message {
        text-align: center;
        padding: 40px 20px;
        color: #888;
        font-size: 14px;
    }

    /* Loading */
    .loading-spinner {
        text-align: center;
        padding: 40px;
    }

    .spinner {
        border: 3px solid var(--border-color);
        border-top: 3px solid var(--accent-color);
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto 15px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Risk Reward Grid Styles */
    .risk-reward-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 12px;
        margin-top: 10px;
    }

    @media (min-width: 640px) {
        .risk-reward-grid {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        }
    }

    .risk-reward-box {
        background: var(--bg-secondary);
        border-radius: 8px;
        padding: 12px 8px;
        text-align: center;
        border: 1px solid var(--border-color);
        transition: all 0.2s ease;
    }

    .risk-reward-box:hover {
        transform: translateY(-2px);
        border-color: var(--accent-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .risk-reward-ratio {
        font-size: 14px;
        font-weight: bold;
        color: var(--accent-color);
        margin-bottom: 5px;
    }

    .risk-reward-count {
        font-size: 11px;
        color: #888;
    }

    /* Modal Styles */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-container {
        background: var(--container-bg);
        border-radius: 12px;
        max-width: 95%;
        width: 500px;
        max-height: 85vh;
        display: flex;
        flex-direction: column;
        border: 1px solid var(--border-color);
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
    
    .modal-container.users-modal {
        width: 500px;
    }
    
    .modal-container.modal-large {
        width: 1200px;
        max-width: 95%;
    }

    .modal-header {
        padding: 15px 20px;
        background: var(--table-header-bg);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: bold;
        font-size: 16px;
        border-radius: 12px 12px 0 0;
    }

    .modal-close {
        cursor: pointer;
        font-size: 20px;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: var(--bg-tertiary);
        transition: all 0.2s;
    }

    .modal-close:hover {
        background: var(--accent-color);
        color: white;
    }

    .modal-body {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
    }
    
    /* Users Modal Styles */
    .users-modal-search {
        margin-bottom: 15px;
    }
    
    .users-modal-list {
        max-height: calc(85vh - 120px);
        overflow-y: auto;
    }
    
    .modal-user-item {
        padding: 12px 15px;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: all 0.2s;
        border-radius: 8px;
        margin-bottom: 5px;
    }
    
    .modal-user-item:hover {
        background: var(--bg-tertiary);
        transform: translateX(5px);
    }
    
    .modal-user-item.selected {
        background: var(--accent-color);
        color: white;
    }
    
    .modal-user-name {
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 3px;
    }
    
    .modal-user-email {
        font-size: 11px;
        opacity: 0.7;
    }
    
    .modal-user-id {
        font-size: 10px;
        opacity: 0.5;
        margin-top: 3px;
    }
    
    .user-search-input {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--border-color);
        background: var(--input-bg);
        color: var(--text-color);
        font-size: 14px;
        border-radius: 8px;
    }
    
    .user-search-input:focus {
        outline: none;
        border-color: var(--accent-color);
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
    }
    
    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 15px;
    }
    
    .empty-state-text {
        font-size: 16px;
        font-weight: bold;
        margin-bottom: 8px;
        color: var(--text-color);
    }
    
    .empty-state-sub {
        font-size: 12px;
        color: #888;
    }
    /* Daily Statistics Card Styles - within Regular Data section */
    .daily-stat-card {
        position: relative;
        transition: all 0.3s ease;
        border-width: 2px;
    }

    .daily-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.15);
    }

    .daily-stat-card.lowest {
        border-left: 4px solid #ff6b6b;
    }

    .daily-stat-card.lowest .stat-value {
        color: #ff6b6b;
        font-size: 20px;
    }

    .daily-stat-card.highest {
        border-left: 4px solid #51cf66;
    }

    .daily-stat-card.highest .stat-value {
        color: #51cf66;
        font-size: 20px;
    }

    .daily-stat-card.average {
        border-left: 4px solid #ffd43b;
    }

    .daily-stat-card.average .stat-value {
        color: #ffd43b;
        font-size: 20px;
    }

    .stat-dates {
        font-size: 9px;
        color: #888;
        margin-top: 6px;
        word-break: break-word;
        line-height: 1.3;
        padding: 3px 5px;
        background: var(--bg-tertiary);
        border-radius: 4px;
        display: inline-block;
        max-width: 100%;
    }
</style>

<style>
    /* New Users Styles */
    .new-users-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        min-width: 800px;
        margin-bottom: 20px;
    }
    .new-users-table th, .new-users-table td {
        border: 1px solid var(--border-color);
        padding: 12px 8px;
        text-align: left;
        vertical-align: middle;
    }
    .new-users-table th {
        background-color: var(--table-header-bg);
        color: var(--header-color);
        font-weight: 600;
        white-space: nowrap;
    }
    .new-users-table tr:nth-child(even) {
        background-color: var(--table-even-row-bg);
    }
    
    .status-update-form {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    .status-input {
        flex: 1;
        min-width: 120px;
        padding: 8px 10px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background-color: var(--input-bg);
        color: var(--text-color);
        font-size: 13px;
        margin: 0;
    }
    .update-btn {
        width: auto;
        padding: 8px 14px;
        background-color: #2196F3;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        font-weight: bold;
        margin-top: 20px;
        white-space: nowrap;
    }
    .update-btn:hover {
        background-color: #1976D2;
    }
    
    .status-badge-new {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 11px;
        background-color: #2196F3;
        color: white;
        white-space: nowrap;
    }
    
    .no-users-message {
        text-align: center;
        padding: 40px;
        border: 2px dashed var(--border-color);
        border-radius: 12px;
        color: #888;
    }

    @media (max-width: 600px) {
        .status-update-form { flex-direction: column; }
        .status-input { width: 100%; min-width: auto; }
        .update-btn { width: 100%; }
    }
</style>

<style>
    /* Revenue Summary Cards */
    .revenue-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin: 20px 0;
    }
    .summary-card {
        background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(76, 175, 80, 0.05));
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 16px;
        text-align: center;
    }
    .summary-card.warning {
        background: linear-gradient(135deg, rgba(255, 152, 0, 0.1), rgba(255, 152, 0, 0.05));
    }
    .summary-card .label {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #888;
        margin-bottom: 5px;
    }
    .summary-card .value {
        font-size: 1.6rem;
        font-weight: bold;
        color: var(--header-color);
    }
    .summary-card .sub {
        font-size: 0.8rem;
        color: #888;
        margin-top: 5px;
    }

    /* Filter Toggle Buttons */
    .filter-section {
        margin: 20px 0;
    }
    .filter-toggles {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 15px;
    }
    .filter-btn {
        flex: 1;
        min-width: 100px;
        padding: 12px 20px;
        background-color: var(--input-bg);
        color: var(--text-color);
        border: 2px solid var(--border-color);
        border-radius: 10px;
        cursor: pointer;
        font-size: 14px;
        font-weight: bold;
        transition: all 0.2s;
        margin: 0;
    }
    .filter-btn.active {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    .filter-btn:hover {
        border-color: var(--primary-color);
    }
    .search-container {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }
    .search-input {
        flex: 1;
        padding: 12px;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background-color: var(--input-bg);
        color: var(--text-color);
        font-size: 14px;
    }
    .search-input::placeholder {
        color: #888;
    }
    .reset-btn {
        width: auto;
        padding: 12px 20px;
        background-color: var(--accent-color);
        margin: 0;
    }

    .table-wrapper {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-top: 20px;
        margin-bottom: 100px;
        border-radius: 12px;
    }
    .user-list-table { 
        width: 100%; 
        border-collapse: collapse; 
        font-size: 13px; 
        min-width: 1100px;
        margin-bottom: 20px;
    }
    .user-list-table th, .user-list-table td { 
        border: 1px solid var(--border-color); 
        padding: 12px 8px; 
        text-align: left; 
        vertical-align: middle;
    }
    .user-list-table th { 
        background-color: var(--table-header-bg); 
        color: var(--header-color); 
        font-weight: 600;
        white-space: nowrap;
    }
    .user-list-table tr:nth-child(even) { background-color: var(--table-even-row-bg); }
    .user-list-table button { width: auto; padding: 8px 14px; margin: 0; font-size: 13px; }
    .confirm-btn { background-color: var(--primary-color); margin-top: 0; }
    
    .profit { color: var(--profit-color); font-weight: bold; }
    .loss { color: var(--loss-color); font-weight: bold; }
    .status-badge { 
        padding: 4px 10px; 
        border-radius: 20px; 
        font-weight: bold; 
        font-size: 11px;
        color: white;
        white-space: nowrap;
        display: inline-block;
    }
    .loyalty-paid { background-color: #f39c12; }
    .loyalty-paymentconfirmed { background-color: var(--primary-color); }
    .loyalty-unpaid { background-color: #7f8c8d; }
    .eligible-badge {
        background-color: var(--primary-color);
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 10px;
        margin-left: 5px;
    }

    /* Payment Status Styles */
    .payment-status-select {
        padding: 6px 10px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        background: white;
        color: black;
        font-size: 12px;
        cursor: pointer;
    }
    
    .payment-status-select:focus {
        outline: none;
        border-color: var(--accent-color);
    }
    
    .update-status-btn {
        padding: 6px 12px;
        background: var(--accent-color);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 11px;
        cursor: pointer;
        margin-left: 5px;
        transition: all 0.3s ease;
    }
    
    .update-status-btn:hover {
        background: var(--accent-hover);
        transform: translateY(-1px);
    }
    
    .status-cell {
        min-width: 180px;
    }
    
    .status-badge-payment-confirmed {
        background: #27ae60;
        color: white;
    }
    
    .status-badge-payment-rejected {
        background: #e74c3c;
        color: white;
    }
    
    .status-badge-payment-not-received {
        background: #f39c12;
        color: white;
    }
    
    .status-badge-payment-failed {
        background: #c0392b;
        color: white;
    }
    
    /* Server Decision Styles */
    .server-decision-select {
        padding: 6px 10px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        background: white;
        color: black;
        font-size: 12px;
        cursor: pointer;
    }
    
    .server-decision-select:focus {
        outline: none;
        border-color: var(--accent-color);
    }
    
    .update-decision-btn {
        padding: 6px 12px;
        background: #9b59b6;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 11px;
        cursor: pointer;
        margin-left: 5px;
        transition: all 0.3s ease;
    }
    
    .update-decision-btn:hover {
        background: #8e44ad;
        transform: translateY(-1px);
    }
    
    .unpaid-age-cell {
        font-size: 11px;
        line-height: 1.4;
    }
    
    .unpaid-age-ended {
        color: #e74c3c;
        font-weight: bold;
    }
    
    .unpaid-age-not-ended {
        color: #f39c12;
    }
    
    .expected-payment-badge {
        background: #e74c3c;
        color: white;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 10px;
        margin-left: 5px;
    }
    
    .server-decision-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .server-decision-blacklisted {
        background: #2c3e50;
        color: white;
    }
    
    .server-decision-re-instate {
        background: #27ae60;
        color: white;
    }
    
    .server-decision-suspend {
        background: #e74c3c;
        color: white;
    }

    /* Live update indicator styles */
    .updating {
        animation: highlightPulse 0.3s ease-out;
    }
    
    @keyframes highlightPulse {
        0% { background-color: rgba(52, 152, 219, 0); }
        50% { background-color: rgba(52, 152, 219, 0.3); }
        100% { background-color: rgba(52, 152, 219, 0); }
    }
    
    .live-badge {
        display: inline-block;
        width: 8px;
        height: 8px;
        background-color: #2ecc71;
        border-radius: 50%;
        margin-left: 8px;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 0.5; transform: scale(1); }
        50% { opacity: 1; transform: scale(1.2); }
        100% { opacity: 0.5; transform: scale(1); }
    }
    
    .user-row.updating-row {
        background-color: rgba(52, 152, 219, 0.1);
        transition: background-color 0.3s;
    }
    
    .filter-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
        padding: 15px;
        background: var(--bg-secondary);
        border-radius: 12px;
    }
    
    .filter-toggles {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: 8px 16px;
        background: var(--bg-tertiary);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .filter-btn.active {
        background: var(--accent-color);
        color: white;
        border-color: var(--accent-color);
    }
    
    .search-container {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .search-input {
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        width: 250px;
    }
    
    .reset-btn {
        padding: 8px 16px;
        background: #e74c3c;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }
    
    .reset-btn:hover {
        background: #c0392b;
    }
    
    .status-update-form.disabled-form {
        opacity: 0.5;
        pointer-events: none;
    }
    
    .status-badge-not-eligible {
        background: #7f8c8d;
        color: white;
    }
    
    @media (max-width: 600px) {
        .revenue-summary { grid-template-columns: repeat(2, 1fr); }
        .summary-card .value { font-size: 1.3rem; }
        .filter-toggles { flex-direction: column; }
        .filter-btn { width: 100%; }
        .table-wrapper { margin-bottom: 100px; }
    }
</style>
