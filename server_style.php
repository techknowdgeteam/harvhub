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
        background-color: var(--bg-color);
        color: var(--text-color);
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
        background-color: var(--bg-color);
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
        --danger-color: #e74c3c;
        --danger-hover: #c0392b;
        --success-color: #4CAF50;
        --success-hover: #45a049;
        --info-color: #2196F3;
        --info-hover: #1976D2;
        --tag-saved-bg: #1a3a5c;
        --tag-saved-color: #90caf9;
        --tag-temp-bg: #3d2d00;
        --tag-temp-color: #ffc107;
        --modal-overlay: rgba(0,0,0,0.6);
        --shadow-color: rgba(0,0,0,0.3);
        --shadow-light: rgba(0,0,0,0.5);
    }

    @media (prefers-color-scheme: light) {
        :root {
            --bg-color: #f4f4f9;
            --text-color: #000000;
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
            --danger-color: #e74c3c;
            --danger-hover: #c0392b;
            --success-color: #4CAF50;
            --success-hover: #45a049;
            --info-color: #2196F3;
            --info-hover: #1976D2;
            --tag-saved-bg: #e3f2fd;
            --tag-saved-color: #0d47a1;
            --tag-temp-bg: #fff3e0;
            --tag-temp-color: #e65100;
            --modal-overlay: rgba(0,0,0,0.4);
            --shadow-color: rgba(0,0,0,0.1);
            --shadow-light: rgba(0,0,0,0.2);
        }
        html, body, #custom-body { 
            background-color: var(--bg-color); 
            color: var(--text-color); 
        }
    }

    .container { 
        max-width: 1200px; 
        margin: 0 auto; 
        background: none; 
        padding: 20px; 
        margin-bottom: 100px; 
        border-radius: 2px; 
        box-shadow: 0 4px 20px var(--shadow-color); 
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

    label { display: block; margin-top: 15px; font-weight: 600; font-size: 0.9rem; color: var(--text-color); }
    input[type="text"], input[type="password"], input[type="number"], select, textarea {
        width: 100%; 
        padding: 12px; 
        margin-top: 5px; 
        border: 1px solid var(--border-color); 
        border-radius: 10px; 
        background-color: var(--input-bg); 
        color: var(--text-color);
        font-size: 16px;
        transition: border-color 0.3s;
    }
    input[type="text"]:focus, input[type="password"]:focus, input[type="number"]:focus, select:focus, textarea:focus {
        border-color: var(--accent-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.2);
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
        transition: all 0.2s ease;
        -webkit-appearance: none;
    }
    button:hover { 
        background-color: var(--primary-hover); 
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
    }
    button:active {
        transform: scale(0.98);
    }
    
    .credentials-section { border-top: 2px dashed var(--border-color); margin-top: 30px; padding-top: 20px; display: none; }
    .credentials-section.active { display: block; }
    .logout-link { display: block; text-align: center; margin-top: 20px; color: var(--danger-color); text-decoration: none; font-weight: bold; }
    .logout-link:hover { color: var(--danger-hover); text-decoration: underline; }
    
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
    
    /* ===== NAV MENU GRID STYLES ===== */
    .nav-menu {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        margin: 20px 0;
    }

    /* Target the last child when it's alone in the last row */
    .nav-menu > a:last-child:nth-child(odd) {
        grid-column: 1 / -1;
    }

    .nav-menu > a {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: linear-gradient(145deg, var(--accent-color), var(--accent-hover));
        color: white;
        padding: 20px 15px;
        border-radius: 16px;
        text-decoration: none;
        font-weight: 600;
        box-shadow: 0 4px 15px var(--shadow-color);
        transition: all 0.3s ease;
        min-height: 140px;
        height: 140px;
        text-align: center;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .nav-menu > a::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(180deg, rgba(255,255,255,0.1) 0%, transparent 100%);
        pointer-events: none;
    }

    .nav-menu > a:hover {
        transform: translateY(-4px) scale(1.02);
        box-shadow: 0 8px 25px rgba(255, 152, 0, 0.4);
        border-color: rgba(255,255,255,0.3);
    }

    .nav-menu > a:active {
        transform: scale(0.97);
    }

    .nav-icon {
        font-size: 2.2rem;
        margin-bottom: 8px;
        display: block;
        line-height: 1;
        position: relative;
        z-index: 1;
    }

    .nav-label {
        font-size: 0.85rem;
        line-height: 1.3;
        position: relative;
        z-index: 1;
        word-break: break-word;
        max-width: 100%;
        text-shadow: 0 1px 3px var(--shadow-color);
    }

    .nav-label .sub-text {
        display: block;
        font-size: 0.65rem;
        font-weight: 400;
        opacity: 0.85;
        margin-top: 3px;
        letter-spacing: 0.3px;
    }

    .nav-menu > a:nth-child(1) .nav-icon { color: #ffd700; }
    .nav-menu > a:nth-child(2) .nav-icon { color: #00bcd4; }
    .nav-menu > a:nth-child(3) .nav-icon { color: #4caf50; }
    .nav-menu > a:nth-child(4) .nav-icon { color: #9c27b0; }
    .nav-menu > a:nth-child(5) .nav-icon { color: #ff6b6b; }
    .nav-menu > a:nth-child(6) .nav-icon { color: #ffb74d; }
    .nav-menu > a:nth-child(7) .nav-icon { color: #ffd700; }

    @media (max-width: 480px) {
        .nav-menu {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .nav-menu > a {
            min-height: 100px;
            height: 100px;
            padding: 16px;
            flex-direction: row;
            gap: 15px;
        }
        .nav-icon {
            font-size: 1.8rem;
            margin-bottom: 0;
        }
        .nav-label {
            font-size: 0.95rem;
            text-align: left;
        }
        .nav-label .sub-text {
            font-size: 0.7rem;
        }
        /* In mobile view (1 column), the last item already takes full width */
        .nav-menu > a:last-child:nth-child(odd) {
            grid-column: auto;
        }
    }

    @media (min-width: 481px) and (max-width: 768px) {
        .nav-menu {
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }
        .nav-menu > a {
            min-height: 120px;
            height: 120px;
            padding: 18px 12px;
        }
        .nav-icon {
            font-size: 1.8rem;
        }
        .nav-label {
            font-size: 0.8rem;
        }
        /* Tablet view - odd last item spans full width */
        .nav-menu > a:last-child:nth-child(odd) {
            grid-column: 1 / -1;
        }
    }

    @media (min-width: 769px) {
        .nav-menu {
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .nav-menu > a {
            min-height: 150px;
            height: 150px;
            padding: 22px 18px;
        }
        .nav-icon {
            font-size: 2.5rem;
        }
        .nav-label {
            font-size: 0.9rem;
        }
        .nav-label .sub-text {
            font-size: 0.7rem;
        }
        /* Desktop view - odd last item spans full width */
        .nav-menu > a:last-child:nth-child(odd) {
            grid-column: 1 / -1;
        }
    }

    .nav-menu:has(> a:only-child) {
        grid-template-columns: 1fr;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
    }
    .nav-menu:has(> a:only-child) > a {
        min-height: 120px;
        height: 120px;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }
    .settings-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 15px;
        transition: border-color 0.3s;
    }
    .settings-card:hover {
        border-color: var(--accent-color);
    }

    .list-management { margin-top: 20px; border: 1px solid var(--border-color); padding: 15px; border-radius: 12px; background: var(--bg-secondary); }
    .list-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dotted var(--border-color); }
    .list-item:last-child { border-bottom: none; }
    .list-item span { word-break: break-word; margin-right: 10px; color: var(--text-color); }
    .list-item-btn { 
        width: auto !important; 
        padding: 8px 16px !important; 
        margin: 0 0 0 10px !important; 
        font-size: 14px !important; 
        background-color: var(--danger-color) !important; 
        border-radius: 8px !important;
        flex-shrink: 0;
    }
    .list-item-btn:hover { background-color: var(--danger-hover) !important; }
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
        background-color: var(--modal-overlay); 
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
        box-shadow: 0 10px 40px var(--shadow-light); 
        width: 100%; max-width: 360px; 
        text-align: center; 
        transform: scale(0.9);
        transition: transform 0.3s;
    }
    .modal.show .modal-content { transform: scale(1); }
    .modal-content h3 { color: var(--danger-color); margin-top: 0; }
    .modal-content p { margin-bottom: 20px; color: var(--text-muted); }
    .modal-content input[type="password"] { margin: 10px 0 20px; }
    .modal-buttons { display: flex; gap: 12px; }
    .modal-buttons button { width: 48%; margin-top: 0; }
    #modal-cancel-btn { background-color: #7f8c8d; }
    #modal-cancel-btn:hover { background-color: #6a7a7a; }

    hr { border: 0; border-top: 1px solid var(--border-color); margin: 30px 0; }

    /* ============================================
       COLUMNS TO RESET - COMPLETE STYLING
       ============================================ */
    
    .columns-reset-container {
        margin-bottom: 15px;
        padding: 15px;
        background: var(--bg-secondary);
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }
    
    .columns-reset-container .help-text {
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 12px;
        line-height: 1.4;
    }
    
    .columns-reset-container .help-text strong {
        color: var(--accent-color);
    }
    
    .columns-reset-input-group {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    
    .columns-reset-input-wrapper {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        width: 100%;
    }
    
    .columns-reset-input-wrapper .column-input,
    .columns-reset-input-wrapper .column-value-input {
        flex: 1;
        min-width: 150px;
        padding: 10px 14px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background-color: var(--input-bg);
        color: var(--text-color);
        font-size: 14px;
        margin-top: 0;
    }
    
    .columns-reset-input-wrapper .column-input:focus,
    .columns-reset-input-wrapper .column-value-input:focus {
        border-color: var(--accent-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.2);
    }
    
    .columns-reset-input-wrapper .btn-add-column {
        padding: 10px 24px;
        background-color: var(--success-color);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s ease;
        white-space: nowrap;
        margin-top: 0;
        width: auto;
        box-shadow: 0 2px 8px rgba(76, 175, 80, 0.2);
    }
    
    .columns-reset-input-wrapper .btn-add-column:hover {
        background-color: var(--success-hover);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
    }
    
    .columns-reset-input-wrapper .btn-add-column:active {
        transform: scale(0.97);
        box-shadow: none;
    }
    
    .input-helper-text {
        display: block;
        margin-top: 5px;
        color: var(--text-muted);
        font-size: 12px;
        width: 100%;
    }
    
    .columns-display-area {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 12px;
        background: var(--input-bg);
        border-radius: 8px;
        min-height: 46px;
        border: 1px dashed var(--border-color);
        transition: border-color 0.3s, background 0.3s;
    }
    
    .columns-display-area:has(.column-tag) {
        border-color: var(--accent-color);
        background: var(--bg-tertiary);
    }
    
    .columns-display-area .empty-message {
        color: var(--text-muted);
        font-size: 13px;
        width: 100%;
        text-align: center;
        padding: 8px 0;
    }
    
    /* Individual column tags */
    .column-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.2s ease;
        animation: tagAppear 0.25s ease;
        max-width: 100%;
        cursor: default;
    }
    
    @keyframes tagAppear {
        from {
            transform: scale(0.8);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }
    
    /* Saved columns (exist in database) */
    .column-tag.saved-column {
        background: var(--tag-saved-bg);
        color: var(--tag-saved-color);
        border: 1px solid var(--tag-saved-color);
    }
    
    .column-tag.saved-column .tag-text {
        color: var(--tag-saved-color);
    }
    
    .column-tag.saved-column .tag-text .column-value-display {
        color: var(--tag-saved-color);
        opacity: 0.8;
    }
    
    .column-tag.saved-column .remove-column-btn {
        color: var(--tag-saved-color);
    }
    
    .column-tag.saved-column .remove-column-btn:hover {
        background: rgba(231, 76, 60, 0.3);
        color: var(--danger-color);
    }
    
    /* Temporary columns (not yet saved) */
    .column-tag.temp-column {
        background: var(--tag-temp-bg);
        color: var(--tag-temp-color);
        border: 1px solid var(--tag-temp-color);
    }
    
    .column-tag.temp-column .tag-text {
        color: var(--tag-temp-color);
    }
    
    .column-tag.temp-column .tag-text .column-value-display {
        color: var(--tag-temp-color);
        opacity: 0.8;
    }
    
    .column-tag.temp-column .remove-column-btn {
        color: var(--tag-temp-color);
    }
    
    .column-tag.temp-column .remove-column-btn:hover {
        background: rgba(231, 76, 60, 0.3);
        color: var(--danger-color);
    }
    
    .column-tag .tag-text {
        font-size: 13px;
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .column-tag .tag-text .column-value-display {
        font-weight: normal;
        font-size: 0.85em;
        opacity: 0.8;
    }
    
    .column-tag .remove-column-btn {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 14px;
        padding: 0 2px;
        line-height: 1;
        opacity: 0.7;
        transition: all 0.2s ease;
        margin-top: 0;
        width: auto;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        min-height: 18px;
        border-radius: 50%;
    }
    
    .column-tag .remove-column-btn:hover {
        opacity: 1;
        transform: scale(1.2);
    }
    
    .btn-save-columns {
        margin-top: 15px;
        padding: 10px 24px;
        background-color: var(--info-color);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s ease;
        width: auto;
        box-shadow: 0 2px 8px rgba(33, 150, 243, 0.2);
    }
    
    .btn-save-columns:hover {
        background-color: var(--info-hover);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(33, 150, 243, 0.4);
    }
    
    .btn-save-columns:active {
        transform: scale(0.97);
        box-shadow: none;
    }
    
    /* Password Modal for column removal */
    #column-password-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: var(--modal-overlay);
        backdrop-filter: blur(5px);
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    #column-password-modal .modal-content {
        background-color: var(--container-bg);
        padding: 25px;
        border-radius: 20px;
        box-shadow: 0 10px 40px var(--shadow-light);
        width: 100%;
        max-width: 400px;
        text-align: center;
        transform: scale(0.9);
        transition: transform 0.3s;
    }
    
    #column-password-modal .modal-content h3 {
        color: var(--danger-color);
        margin-bottom: 8px;
        margin-top: 0;
    }
    
    #column-password-modal .modal-content .modal-subtitle {
        color: var(--text-muted);
        font-size: 14px;
        margin-bottom: 15px;
    }
    
    #column-password-modal .modal-content input[type="password"] {
        margin: 10px 0 20px;
        width: 100%;
        padding: 12px;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background-color: var(--input-bg);
        color: var(--text-color);
        font-size: 16px;
    }
    
    #column-password-modal .modal-content input[type="password"]:focus {
        border-color: var(--danger-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.2);
    }
    
    #column-password-modal .modal-buttons {
        display: flex;
        gap: 12px;
    }
    
    #column-password-modal .modal-buttons button {
        width: 48%;
        padding: 12px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-size: 16px;
        font-weight: bold;
        transition: all 0.2s ease;
        margin-top: 0;
    }
    
    #column-password-modal .modal-buttons .btn-cancel-password {
        background-color: #7f8c8d;
        color: white;
    }
    
    #column-password-modal .modal-buttons .btn-cancel-password:hover {
        background-color: #6a7a7a;
        box-shadow: none;
        transform: none;
    }
    
    #column-password-modal .modal-buttons .btn-confirm-password {
        background-color: var(--danger-color);
        color: white;
    }
    
    #column-password-modal .modal-buttons .btn-confirm-password:hover {
        background-color: var(--danger-hover);
        box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
    }

    /* Password required section styling */
    .password-required-section {
        margin-top: 15px;
        padding: 15px;
        background: var(--bg-secondary);
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }
    
    .password-required-section .password-label {
        color: var(--text-color);
        font-weight: 600;
        display: block;
        margin-bottom: 5px;
        margin-top: 0;
        font-size: 14px;
    }
    
    .password-required-section .password-input {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background-color: var(--input-bg);
        color: var(--text-color);
        font-size: 14px;
        margin-top: 5px;
    }
    
    .password-required-section .password-input:focus {
        border-color: var(--accent-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.2);
    }
    
    .password-required-section .password-helper {
        display: block;
        margin-top: 5px;
        color: var(--text-muted);
        font-size: 12px;
    }

    /* Responsive adjustments */
    @media (max-width: 600px) {
        #custom-body { padding: 10px; }
        .container { padding: 16px; }
        h2 { font-size: 1.4rem; }
        .toggle-btn { margin-bottom: 100px; }
        .table-wrapper {
            margin-bottom: 100px;
        }
        
        .columns-reset-input-wrapper {
            flex-direction: column;
        }
        
        .columns-reset-input-wrapper .column-input,
        .columns-reset-input-wrapper .column-value-input {
            min-width: unset;
            width: 100%;
        }
        
        .columns-reset-input-wrapper .btn-add-column {
            width: 100%;
        }
        
        .column-tag .tag-text {
            max-width: 100px;
        }
        
        .btn-save-columns {
            width: 100%;
        }
        
        #column-password-modal .modal-content {
            margin: 10% auto;
            padding: 20px;
            max-width: 90%;
        }
        
        #column-password-modal .modal-buttons {
            flex-direction: column;
            gap: 8px;
        }
        
        #column-password-modal .modal-buttons button {
            width: 100%;
        }
        
        .password-required-section {
            padding: 12px;
        }
    }
    /* ============================================
   PASSWORD MODAL STYLES
   ============================================ */
    #settings-password-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: var(--modal-overlay);
        backdrop-filter: blur(5px);
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }

    #settings-password-modal .modal-content {
        background-color: var(--container-bg);
        padding: 30px;
        border-radius: 20px;
        box-shadow: 0 10px 40px var(--shadow-light);
        width: 100%;
        max-width: 400px;
        text-align: center;
        transform: scale(0.9);
        transition: transform 0.3s ease;
        border: 1px solid var(--border-color);
    }

    #settings-password-modal .modal-content h3 {
        color: var(--accent-color);
        margin-bottom: 8px;
        margin-top: 0;
        font-size: 1.2rem;
    }

    #settings-password-modal .modal-content p {
        color: var(--text-muted);
        font-size: 14px;
        margin-bottom: 15px;
        line-height: 1.5;
    }

    #settings-password-modal .modal-content input[type="password"] {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid var(--border-color);
        border-radius: 10px;
        background-color: var(--input-bg);
        color: var(--text-color);
        font-size: 16px;
        margin: 10px 0 20px;
        box-sizing: border-box;
        transition: border-color 0.3s;
    }

    #settings-password-modal .modal-content input[type="password"]:focus {
        border-color: var(--accent-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.15);
    }

    #settings-password-modal .modal-buttons {
        display: flex;
        gap: 12px;
    }

    #settings-password-modal .modal-buttons button {
        width: 48%;
        padding: 12px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-size: 15px;
        font-weight: 600;
        transition: all 0.2s ease;
        margin-top: 0;
    }

    #settings-password-modal .modal-buttons #settings-modal-cancel {
        background-color: #7f8c8d;
        color: white;
    }

    #settings-password-modal .modal-buttons #settings-modal-cancel:hover {
        background-color: #6a7a7a;
        transform: none;
        box-shadow: none;
    }

    #settings-password-modal .modal-buttons #settings-modal-confirm {
        background-color: var(--primary-color);
        color: white;
    }

    #settings-password-modal .modal-buttons #settings-modal-confirm:hover {
        background-color: var(--primary-hover);
        box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
    }

    #settings-password-modal .modal-buttons #settings-modal-confirm:active {
        transform: scale(0.97);
    }

    /* Custom Modal (Success/Error) Styles */
    .modal-overlay#custom-modal-overlay {
        display: flex;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: var(--modal-overlay);
        backdrop-filter: blur(4px);
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-overlay#custom-modal-overlay .custom-modal {
        max-width: 450px;
        animation: modalSlideIn 0.3s ease;
    }

    .modal-overlay#custom-modal-overlay .modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
        font-size: 1.1rem;
    }

    .modal-overlay#custom-modal-overlay .modal-body {
        padding: 25px 20px;
    }

    .modal-overlay#custom-modal-overlay .modal-close {
        cursor: pointer;
        font-size: 20px;
        opacity: 0.7;
        transition: opacity 0.2s;
    }

    .modal-overlay#custom-modal-overlay .modal-close:hover {
        opacity: 1;
    }

    .modal-overlay#custom-modal-overlay .modal-confirm-btn,
    .modal-overlay#custom-modal-overlay .modal-cancel-btn {
        padding: 10px 24px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .modal-overlay#custom-modal-overlay .modal-confirm-btn {
        background-color: var(--primary-color);
        color: white;
    }

    .modal-overlay#custom-modal-overlay .modal-confirm-btn:hover {
        background-color: var(--primary-hover);
        box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
    }

    .modal-overlay#custom-modal-overlay .modal-cancel-btn {
        background-color: #7f8c8d;
        color: white;
    }

    .modal-overlay#custom-modal-overlay .modal-cancel-btn:hover {
        background-color: #6a7a7a;
    }

    @keyframes modalSlideIn {
        from {
            transform: scale(0.8) translateY(20px);
            opacity: 0;
        }
        to {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
    }

    /* Mobile responsiveness for modals */
    @media (max-width: 600px) {
        #settings-password-modal .modal-content {
            padding: 20px;
            margin: 10px;
        }
        
        #settings-password-modal .modal-buttons {
            flex-direction: column;
            gap: 8px;
        }
        
        #settings-password-modal .modal-buttons button {
            width: 100%;
        }
        
        .modal-overlay#custom-modal-overlay .custom-modal {
            margin: 10px;
            max-width: 100%;
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
    /* ============================================
    USER SELECTION MODAL STYLES (Analytics Style)
    ============================================ */

    /* Users Sidebar Header - Search Button */
    .users-sidebar-header {
        padding: 12px 15px;
        background: var(--table-header-bg);
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .users-sidebar-header:hover {
        background: var(--bg-tertiary);
    }

    .search-user-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 14px;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        color: var(--text-color);
        transition: all 0.2s ease;
    }

    .search-user-btn:hover {
        border-color: var(--accent-color);
        background: var(--bg-secondary);
    }

    .search-icon {
        font-size: 14px;
        opacity: 0.7;
    }
    /* ============================================
    EDIT JSON MODAL STYLES
    ============================================ */

    /* Modal Container Large */
    .modal-container.modal-large {
        width: 800px;
        max-width: 95vw;
        max-height: 90vh;
        min-height: 500px;
    }

    @media (max-width: 768px) {
        .modal-container.modal-large {
            width: 98vw;
            max-height: 95vh;
            min-height: 300px;
        }
    }

    /* Modal Overlay for all modals */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        animation: modalFadeIn 0.3s ease;
    }

    /* Modal Container */
    .modal-container {
        background: var(--container-bg, #1e1e2a);
        border-radius: 12px;
        max-width: 95%;
        max-height: 85vh;
        display: flex;
        flex-direction: column;
        border: 1px solid var(--border-color, #3a3a4a);
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        animation: modalSlideIn 0.3s ease;
        overflow: hidden;
    }

    /* Modal Header */
    .modal-header {
        padding: 15px 20px;
        background: var(--table-header-bg, #2d2d3a);
        border-bottom: 1px solid var(--border-color, #3a3a4a);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: bold;
        font-size: 16px;
        border-radius: 12px 12px 0 0;
        flex-shrink: 0;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 16px;
    }

    .modal-header .modal-close {
        cursor: pointer;
        font-size: 20px;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: var(--bg-tertiary, #3a3a4a);
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .modal-header .modal-close:hover {
        background: var(--accent-color, #3498db);
        color: white;
    }

    /* Modal Body */
    .modal-body {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
        overflow-x: hidden;
    }

    /* Modal Buttons */
    .modal-confirm-btn {
        background: #27ae60;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }

    .modal-confirm-btn:hover {
        background: #229954;
        transform: translateY(-1px);
    }

    .modal-cancel-btn {
        background: #e74c3c;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }

    .modal-cancel-btn:hover {
        background: #c0392b;
        transform: translateY(-1px);
    }

    /* JSON Editor in Modal */
    #edit-json-textarea {
        font-family: 'Courier New', monospace;
        font-size: 13px;
        line-height: 1.6;
        tab-size: 2;
    }

    #edit-json-textarea:focus {
        outline: none;
        border-color: #27ae60;
    }

    /* Animations */
    @keyframes modalFadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes modalSlideIn {
        from {
            transform: translateY(-30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* Blur Background */
    .blur-background {
        filter: blur(8px);
        pointer-events: none;
    }

    /* Password Input in Modal */
    .json-password-input {
        width: 100%;
        padding: 10px;
        margin: 15px 0;
        border: 1px solid var(--border-color, #3a3a4a);
        background: var(--bg-primary, #1e1e2a);
        color: var(--text-primary, #e4e4e7);
        border-radius: 6px;
        font-size: 14px;
    }

    .json-password-input:focus {
        outline: none;
        border-color: var(--accent-color, #3498db);
    }

    /* User Info Section - Fixed */
    .user-info {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 20px;
        padding: 15px 20px;
        background: var(--bg-tertiary, #2d2d3a);
        border-bottom: 1px solid var(--border-color, #3a3a4a);
    }

    .user-info-item {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 13px;
    }

    .user-info-label {
        font-weight: bold;
        opacity: 0.7;
    }

    /* Remove User Details Container - Hide it */
    .user-details-container {
        display: none !important;
    }

    /* Responsive modal adjustments */
    @media (max-width: 768px) {
        .modal-container {
            max-width: 100%;
            max-height: 100vh;
            border-radius: 8px;
            margin: 5px;
        }
        
        .modal-body {
            padding: 12px;
        }
        
        #edit-json-textarea {
            min-height: 300px;
            font-size: 12px;
        }
    }

    @media (max-width: 480px) {
        .modal-header {
            font-size: 14px;
            padding: 12px 15px;
        }
        
        .modal-body {
            padding: 10px;
        }
        
        #edit-json-textarea {
            min-height: 250px;
            font-size: 11px;
        }
    }
    .search-placeholder {
        font-size: 13px;
        opacity: 0.6;
    }

    /* Default User Card */
    .default-user-card {
        padding: 12px 15px;
        border-bottom: 1px solid var(--border-color);
    }

    .default-user-info {
        background: var(--bg-tertiary);
        border-radius: 10px;
        padding: 12px 15px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid var(--border-color);
    }

    .default-user-info:hover {
        background: var(--bg-secondary);
        transform: translateX(5px);
        border-color: var(--accent-color);
    }

    .default-user-name {
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .default-user-email {
        font-size: 12px;
        opacity: 0.7;
        margin-bottom: 3px;
    }

    .default-user-id {
        font-size: 10px;
        opacity: 0.6;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 4px;
    }

    /* Loading Spinner Small */
    .loading-spinner-small {
        text-align: center;
        padding: 20px;
        color: var(--text-color);
        font-size: 13px;
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

    /* Modal Overlay - matches analytics */
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
        animation: modalFadeIn 0.3s ease;
    }

    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
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
        animation: modalSlideIn 0.3s ease;
        overflow: hidden;
    }

    @keyframes modalSlideIn {
        from {
            transform: translateY(-30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-container.users-modal {
        width: 500px;
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
        flex-shrink: 0;
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
        flex-shrink: 0;
    }

    .modal-close:hover {
        background: var(--accent-color);
        color: white;
    }

    .modal-body {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
        overflow-x: hidden;
    }

    /* Users Modal Styles */
    .users-modal-search {
        margin-bottom: 15px;
    }

    .users-modal-list {
        max-height: calc(85vh - 120px);
        overflow-y: auto;
        overflow-x: hidden;
    }

    .modal-user-item {
        padding: 12px 15px;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: all 0.2s;
        border-radius: 8px;
        margin-bottom: 5px;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .modal-user-item:hover {
        background: var(--bg-tertiary);
        transform: translateX(5px);
    }

    .modal-user-item.selected {
        background: var(--accent-color);
        color: white;
    }

    .modal-user-item.selected .modal-user-email,
    .modal-user-item.selected .modal-user-id {
        color: rgba(255,255,255,0.8);
    }

    .modal-user-name {
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 3px;
        word-wrap: break-word;
    }

    .modal-user-email {
        font-size: 11px;
        opacity: 0.7;
        word-wrap: break-word;
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
        box-sizing: border-box;
    }

    .user-search-input:focus {
        outline: none;
        border-color: var(--accent-color);
    }

    .info-message-small {
        text-align: center;
        padding: 20px;
        color: #888;
        font-size: 13px;
    }

    /* Blur Background */
    .blur-background {
        filter: blur(8px);
        pointer-events: none;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .modal-container.users-modal {
            width: 95%;
            max-width: 95%;
        }
        
        .modal-body {
            padding: 12px;
        }
        
        .modal-user-item {
            padding: 10px 12px;
        }
    }

    @media (max-width: 480px) {
        .modal-container.users-modal {
            width: 100%;
            max-width: 100%;
            max-height: 95vh;
            border-radius: 8px;
            margin: 5px;
        }
        
        .modal-header {
            font-size: 14px;
            padding: 12px 15px;
        }
        
        .modal-body {
            padding: 10px;
        }
        
        .modal-user-item {
            padding: 8px 10px;
        }
        
        .modal-user-name {
            font-size: 13px;
        }
        
        .modal-user-email {
            font-size: 10px;
        }
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
        flex-shrink: 0;
    }

    .management-panel .json-viewer-full {
        overflow-y: auto;
        flex: 1;
        width: 100%;
        max-height: calc(80vh - 100px);
        min-height: 400px;
    }

    /* JSON Viewer Styles - SCROLLABLE */
    .json-viewer, 
    .json-viewer-full {
        padding: 20px;
        background: var(--input-bg);
        width: 100%;
        overflow: auto;
        box-sizing: border-box;
        max-height: calc(80vh - 100px);
        min-height: 400px;
    }

    .json-viewer-full {
        min-height: 400px;
        max-height: calc(80vh - 100px);
        overflow: auto;
    }

    /* JSON Structure - READ MODE with scrolling */
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
        overflow: auto;
        width: 100%;
        box-sizing: border-box;
        max-height: calc(80vh - 160px);
        min-height: 350px;
    }

    .editor-full-wrapper {
        width: 100%;
        display: block;
        max-height: calc(80vh - 100px);
        overflow: auto;
    }

    /* JSON Editor - EDIT MODE with scrolling */
    .json-editor-fullwidth {
        width: 100%;
        min-height: 400px;
        max-height: calc(80vh - 100px);
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
        overflow: auto;
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

    /* Scrollbar Styling for all JSON containers */
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

    .config-entry-buttons {
        display: flex;
        gap: 8px;
        flex-shrink: 0;
    }

    .config-entry-buttons button {
        padding: 6px 12px;
        font-size: 12px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        position: relative;
        z-index: 1;
    }

    .config-entry-buttons button:hover {
        transform: translateY(-1px);
    }

    .edit-config-btn {
        background: #3498db;
        color: white;
    }

    .edit-config-btn:hover {
        background: #2980b9;
    }

    .copy-config-btn {
        background: #9b59b6;
        color: white;
    }

    .copy-config-btn:hover {
        background: #8e44ad;
    }

    .delete-config-btn {
        background: #e74c3c;
        color: white;
    }

    .delete-config-btn:hover {
        background: #c0392b;
    }

    .save-config-btn {
        background: #27ae60;
        color: white;
    }

    .save-config-btn:hover {
        background: #229954;
    }

    .cancel-config-btn {
        background: #95a5a6;
        color: white;
    }

    .cancel-config-btn:hover {
        background: #7f8c8d;
    }

    .config-entry-content {
        padding: 15px;
        max-height: 500px;
        overflow: auto;
        transition: all 0.3s ease;
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

    .config-json-view {
        background: var(--bg-secondary);
        padding: 15px;
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-size: 12px;
        white-space: pre-wrap;
        word-break: break-word;
        overflow: auto;
        max-height: 400px;
        min-height: 100px;
    }

    .config-json-editor {
        width: 100%;
        min-height: 300px;
        max-height: 500px;
        padding: 15px;
        background: var(--bg-secondary);
        color: var(--text-color);
        border: 2px solid var(--accent-color);
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-size: 12px;
        resize: vertical;
        white-space: pre;
        overflow: auto;
        box-sizing: border-box;
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
    
    /* Fixed scrollable lists for all tabs */
    #users-tab .user-list-panel,
    #invested-tab .invested-management-container,
    #verified-tab .user-viewer-container,
    #pending-tab .user-viewer-container,
    #suspended-tab .user-viewer-container,
    #justjoined-tab .user-viewer-container,
    #justjoinedvalid-tab .user-viewer-container,
    #approved-tab .user-viewer-container,
    #bypassed-tab .user-viewer-container,
    #autotrading-tab .user-list-panel,
    #execution-tab .user-list-panel {
        max-height: 550px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }

    #users-tab .user-list-panel h3,
    #autotrading-tab .user-list-panel h3,
    #execution-tab .user-list-panel h3 {
        flex-shrink: 0;
    }

    #user-items-list,
    #invested-users-list,
    #verified-users-list,
    #pending-users-list,
    #suspended-users-list,
    #justjoined-users-list,
    #justjoinedvalid-users-list,
    #approved-users-list,
    #bypassed-users-list,
    #autotrading-user-list,
    #execution-user-list {
        flex: 1;
        overflow-y: auto;
        min-height: 200px;
    }

    .user-viewer-table-container,
    .invested-users-table-container {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 450px;
    }

    .table-responsive {
        overflow-x: auto;
        width: 100%;
    }

    .user-items {
        overflow-y: auto;
        max-height: none;
        flex: 1;
    }

    /* Server Configuration scrollable */
    #server-tab .account-management-container:first-child .json-viewer {
        max-height: 400px;
        min-height: 200px;
        overflow-y: auto;
        padding: 20px;
    }

    #server-tab .account-management-container:first-child .json-structure {
        margin: 0;
        white-space: pre-wrap;
        word-break: break-word;
        max-height: 350px;
        overflow-y: auto;
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

    /* Date Range Card - New Improved Style */
    .date-range-card {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: 8px 15px;
        background: linear-gradient(135deg, var(--bg-tertiary), var(--container-bg));
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .date-range-item {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .date-range-label {
        font-size: 12px;
        font-weight: bold;
        color: var(--accent-color);
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .date-range-value {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-color);
        background: var(--bg-secondary);
        padding: 3px 10px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
    }

    .date-range-divider {
        width: 1px;
        height: 30px;
        background: var(--border-color);
        flex-shrink: 0;
    }

    @media (max-width: 480px) {
        .date-range-card {
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
            padding: 12px;
        }
        
        .date-range-divider {
            width: 100%;
            height: 1px;
        }
        
        .date-range-item {
            justify-content: center;
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
        animation: modalFadeIn 0.3s ease;
    }

    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
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
        animation: modalSlideIn 0.3s ease;
        overflow: hidden;
    }

    @keyframes modalSlideIn {
        from {
            transform: translateY(-30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
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
        flex-shrink: 0;
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
        flex-shrink: 0;
    }

    .modal-close:hover {
        background: var(--accent-color);
        color: white;
    }

    .modal-body {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
        overflow-x: hidden;
    }

    /* Users Modal Styles */
    .users-modal-search {
        margin-bottom: 15px;
    }

    .users-modal-list {
        max-height: calc(85vh - 120px);
        overflow-y: auto;
        overflow-x: hidden;
    }

    .modal-user-item {
        padding: 12px 15px;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: all 0.2s;
        border-radius: 8px;
        margin-bottom: 5px;
        word-wrap: break-word;
        overflow-wrap: break-word;
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
        word-wrap: break-word;
    }

    .modal-user-email {
        font-size: 11px;
        opacity: 0.7;
        word-wrap: break-word;
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
        box-sizing: border-box;
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

    /* ============================================
    CALENDAR STYLES - FIXED (No ellipsis, text shrinks)
    ============================================ */

    /* Calendar Grid Container */
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 4px;
        margin: 10px 0;
        width: 100%;
        min-width: 0;
    }

    /* Calendar Header */
    .calendar-header {
        display: contents;
    }

    .calendar-header-cell {
        padding: 6px 2px;
        text-align: center;
        font-weight: bold;
        font-size: 10px;
        background: var(--table-header-bg);
        border-radius: 4px;
        border: 1px solid var(--border-color);
        color: var(--text-color);
        text-transform: uppercase;
        letter-spacing: 0.3px;
        min-height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        word-break: break-word;
    }

    /* Calendar Day Cards - Fixed Height, No Ellipsis */
    .calendar-day {
        padding: 4px 2px;
        text-align: center;
        border-radius: 6px;
        border: 2px solid transparent;
        cursor: pointer;
        transition: all 0.2s ease;
        min-height: 65px;
        height: 65px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        background: var(--bg-tertiary);
        border-color: var(--border-color);
        width: 100%;
        overflow: visible;
        box-sizing: border-box;
        font-size: 10px;
    }

    /* Ensure ALL calendar days have the same height */
    .calendar-day,
    .calendar-empty,
    .calendar-day.calendar-profit,
    .calendar-day.calendar-loss,
    .calendar-day.calendar-empty-day {
        min-height: 65px;
        height: 65px;
    }

    .calendar-day:hover {
        transform: scale(1.03);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        border-color: var(--accent-color);
        z-index: 1;
    }

    .calendar-day.calendar-profit {
        background: #4CAF50;
        color: white;
        border-color: #45a049;
    }

    .calendar-day.calendar-profit:hover {
        background: #45a049;
        border-color: #388e3c;
    }

    .calendar-day.calendar-loss {
        background: #f44336;
        color: white;
        border-color: #d32f2f;
    }

    .calendar-day.calendar-loss:hover {
        background: #d32f2f;
        border-color: #b71c1c;
    }

    .calendar-day.calendar-empty-day {
        background: #e0e0e0;
        color: #999;
        border-color: #ccc;
        cursor: default;
        opacity: 0.7;
    }

    .calendar-day.calendar-empty-day:hover {
        transform: none;
        box-shadow: none;
        border-color: #ccc;
    }

    .calendar-empty {
        background: transparent;
        border: none;
        min-height: 65px;
        height: 65px;
    }

    /* Calendar Day Content - No ellipsis, text shrinks naturally */
    .calendar-day-date {
        font-size: 9px;
        font-weight: bold;
        margin-bottom: 1px;
        line-height: 1.2;
        word-break: break-word;
        max-width: 100%;
    }

    .calendar-day.calendar-profit .calendar-day-date,
    .calendar-day.calendar-loss .calendar-day-date {
        color: white;
    }

    .calendar-day-pnl {
        font-size: 13px;
        font-weight: bold;
        line-height: 1.2;
        margin: 1px 0;
        word-break: break-word;
        max-width: 100%;
    }

    .calendar-day.calendar-profit .calendar-day-pnl,
    .calendar-day.calendar-loss .calendar-day-pnl {
        color: white;
    }

    .calendar-day-trades {
        font-size: 8px;
        margin-top: 1px;
        opacity: 0.85;
        line-height: 1.2;
        word-break: break-word;
        max-width: 100%;
    }

    .calendar-day.calendar-profit .calendar-day-trades,
    .calendar-day.calendar-loss .calendar-day-trades {
        color: white;
    }

    /* Calendar Toggle Button */
    .calendar-toggle-btn {
        padding: 10px 20px;
        background: var(--accent-color);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: bold;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        white-space: nowrap;
    }

    .calendar-toggle-btn:hover {
        background: var(--accent-hover);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    }

    .calendar-toggle-btn:active {
        transform: translateY(0);
    }

    /* Mobile optimizations for calendar */
    @media (max-width: 480px) {
        .modal-container.modal-large {
            max-width: 100%;
            padding: 0;
            margin: 5px;
            max-height: 98vh;
            border-radius: 8px;
        }
        
        .modal-body {
            padding: 8px;
        }
        
        .calendar-grid {
            gap: 2px;
            margin: 5px 0;
        }
        
        /* Fixed height for mobile - No ellipsis */
        .calendar-day,
        .calendar-empty,
        .calendar-day.calendar-profit,
        .calendar-day.calendar-loss,
        .calendar-day.calendar-empty-day {
            min-height: 50px;
            height: 50px;
            padding: 2px 1px;
            border-radius: 4px;
        }
        
        .calendar-header-cell {
            font-size: 7px;
            padding: 3px 1px;
            min-height: 22px;
        }
        
        .calendar-day-date {
            font-size: 7px;
        }
        
        .calendar-day-pnl {
            font-size: 10px;
        }
        
        .calendar-day-trades {
            font-size: 6px;
        }
        
        /* Allow text to shrink naturally */
        .calendar-day-date,
        .calendar-day-pnl,
        .calendar-day-trades {
            max-width: 100%;
            word-break: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
        }
    }

    @media (min-width: 481px) and (max-width: 768px) {
        .calendar-day,
        .calendar-empty,
        .calendar-day.calendar-profit,
        .calendar-day.calendar-loss,
        .calendar-day.calendar-empty-day {
            min-height: 60px;
            height: 60px;
            padding: 3px 2px;
        }
        
        .calendar-header-cell {
            font-size: 8px;
            min-height: 25px;
        }
        
        .calendar-day-date {
            font-size: 8px;
        }
        
        .calendar-day-pnl {
            font-size: 11px;
        }
        
        .calendar-day-trades {
            font-size: 7px;
        }
    }

    @media (min-width: 769px) {
        .calendar-day,
        .calendar-empty,
        .calendar-day.calendar-profit,
        .calendar-day.calendar-loss,
        .calendar-day.calendar-empty-day {
            min-height: 75px;
            height: 75px;
            padding: 5px 3px;
        }
        
        .calendar-header-cell {
            font-size: 11px;
            padding: 8px 4px;
        }
        
        .calendar-day-date {
            font-size: 10px;
        }
        
        .calendar-day-pnl {
            font-size: 14px;
        }
        
        .calendar-day-trades {
            font-size: 9px;
        }
    }

    /* Extra small screens - text shrinks further */
    @media (max-width: 360px) {
        .calendar-day,
        .calendar-empty,
        .calendar-day.calendar-profit,
        .calendar-day.calendar-loss,
        .calendar-day.calendar-empty-day {
            min-height: 40px;
            height: 40px;
            padding: 1px;
        }
        
        .calendar-header-cell {
            font-size: 6px;
            padding: 2px 1px;
            min-height: 18px;
        }
        
        .calendar-day-date {
            font-size: 6px;
        }
        
        .calendar-day-pnl {
            font-size: 8px;
        }
        
        .calendar-day-trades {
            font-size: 5px;
        }
    }

    /* Day Detail Modal - Enhanced */
    .modal-container .day-detail-pnl {
        font-size: 42px;
        font-weight: bold;
        text-align: center;
        padding: 15px 0;
    }

    .modal-container .day-detail-pnl.profit {
        color: var(--profit-color);
    }

    .modal-container .day-detail-pnl.loss {
        color: var(--loss-color);
    }

    .modal-container .day-detail-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin: 15px 0;
    }

    .modal-container .day-detail-stat {
        text-align: center;
        padding: 12px;
        background: var(--bg-tertiary);
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }

    .modal-container .day-detail-stat-label {
        font-size: 11px;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .modal-container .day-detail-stat-value {
        font-size: 24px;
        font-weight: bold;
        margin-top: 4px;
    }

    .modal-container .day-detail-stat-value.profit {
        color: var(--profit-color);
    }

    .modal-container .day-detail-stat-value.loss {
        color: var(--loss-color);
    }

    /* Scrollable modal body for large content */
    .modal-body-scroll {
        max-height: calc(85vh - 120px);
        overflow-y: auto;
        padding: 20px;
    }

    /* Smooth hover transitions for calendar days */
    .calendar-day {
        transition: all 0.15s ease-in-out;
    }

    .calendar-day:active {
        transform: scale(0.95);
    }

    /* Print styles for calendar */
    @media print {
        .calendar-day {
            break-inside: avoid;
            border: 1px solid #ccc !important;
        }
        
        .calendar-day.calendar-profit {
            background: #4CAF50 !important;
            color: white !important;
        }
        
        .calendar-day.calendar-loss {
            background: #f44336 !important;
            color: white !important;
        }
        
        .calendar-toggle-btn,
        .modal-close,
        .floating-stats-btn {
            display: none !important;
        }
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
    /* ============================================
    REVENUE DASHBOARD STYLES - Mobile First
    ============================================ */

    .revenue-container {
        width: 100%;
        min-height: calc(100vh - 100px);
        background: var(--bg-color);
        padding: 15px 0;
    }

    .revenue-header h2 {
        margin: 0 0 20px 0;
        font-size: 1.5rem;
        color: var(--text-color);
    }

    /* ============================================
    TABS - Horizontal Scroll
    ============================================ */
    .revenue-tabs-wrapper {
        position: relative;
        margin-bottom: 15px;
        overflow: hidden;
    }

    .revenue-tabs {
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

    .revenue-tabs::-webkit-scrollbar {
        display: none;
    }

    .revenue-tabs .tab-btn {
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

    .revenue-tabs .tab-btn.active {
        background: var(--accent-color);
        color: white;
    }

    .revenue-tabs .tab-btn:hover:not(.active) {
        background: var(--bg-tertiary);
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

    .sub-tabs-wrapper {
        margin-top: -5px;
    }

    .sub-tabs .sub-tab-btn {
        padding: 6px 12px;
        font-size: 11px;
        flex: 0 0 auto;
    }

    .sub-sub-tabs-wrapper {
        margin-top: -5px;
    }

    @media (min-width: 768px) {
        .sub-tabs .sub-tab-btn {
            font-size: 12px;
            padding: 8px 16px;
        }
    }

    .tab-content {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    .tab-content.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* ============================================
    SEARCH BAR - Dummy + Real
    ============================================ */
    .search-bar-wrapper {
        position: relative;
        margin-bottom: 16px;
    }

    .search-bar {
        display: flex;
        align-items: center;
        background: var(--container-bg);
        border: 2px solid var(--border-color);
        border-radius: 10px;
        padding: 0 14px;
        transition: border-color 0.2s;
        min-height: 48px;
    }

    .search-bar:focus-within {
        border-color: var(--accent-color);
    }

    .search-bar-dummy {
        cursor: pointer;
        transition: all 0.2s;
    }

    .search-bar-dummy:hover {
        border-color: var(--accent-color);
        background: var(--bg-tertiary);
    }

    .search-bar .search-icon {
        font-size: 16px;
        font-weight: 700;
        color: #888;
        margin-right: 10px;
        flex-shrink: 0;
    }

    .search-bar .search-placeholder {
        flex: 1;
        padding: 12px 0;
        color: #999;
        font-size: 14px;
        cursor: pointer;
    }

    .search-bar .search-input {
        flex: 1;
        padding: 12px 0;
        border: none;
        background: transparent;
        color: var(--text-color);
        font-size: 14px;
        outline: none;
        min-width: 0;
    }

    .search-bar .search-input::placeholder {
        color: #999;
    }

    .search-bar .search-clear {
        cursor: pointer;
        font-size: 16px;
        font-weight: 700;
        color: #888;
        padding: 0 4px;
        flex-shrink: 0;
        transition: color 0.2s;
    }

    .search-bar .search-clear:hover {
        color: var(--text-color);
    }

    /* ============================================
    SUMMARY CUBES
    ============================================ */
    .summary-cubes {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
        width: 100%;
    }

    .summary-cube {
        flex: 1 0 calc(16.66% - 12px);
        min-width: 120px;
        background: var(--container-bg);
        border-radius: 12px;
        padding: 16px 12px;
        text-align: center;
        border: 1px solid var(--border-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        transition: transform 0.2s, box-shadow 0.2s;
        overflow: hidden;
        min-height: 80px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    @media (max-width: 992px) {
        .summary-cube {
            flex: 1 0 calc(33.33% - 12px);
            min-width: 100px;
        }
    }

    @media (max-width: 768px) {
        .summary-cube {
            flex: 1 0 calc(50% - 10px);
            min-width: 80px;
            padding: 12px 8px;
            min-height: 70px;
        }
    }

    @media (max-width: 480px) {
        .summary-cube {
            flex: 1 0 calc(50% - 8px);
            min-width: 70px;
            padding: 10px 6px;
            min-height: 60px;
        }
        
        .summary-cube .cube-value {
            font-size: 14px;
        }
        
        .summary-cube .cube-label {
            font-size: 9px;
        }
    }

    /* ============================================
    REVENUE HISTORY USER HEADER
    ============================================ */
    .revenue-history-user-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding: 12px 16px;
        background: var(--bg-secondary);
        border-radius: 10px;
        border: 1px solid var(--border-color);
        flex-wrap: wrap;
        gap: 10px;
    }

    .revenue-history-user-header h3 {
        margin: 0;
        font-size: 16px;
    }

    /* ============================================
    BACK TO OVERVIEW BUTTON
    ============================================ */
    .back-to-overview-btn {
        padding: 8px 20px;
        border: none;
        background: var(--bg-secondary);
        color: var(--text-color);
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s;
        border: 1px solid var(--border-color);
        white-space: nowrap;
    }

    .back-to-overview-btn:hover {
        background: var(--bg-tertiary);
    }

    /* ============================================
    TABLES
    ============================================ */
    .users-table-container {
        background: var(--container-bg);
        border-radius: 12px;
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    .table-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .revenue-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .revenue-table thead {
        background: var(--table-header-bg);
    }

    .revenue-table th {
        padding: 10px 15px;
        text-align: left;
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #888;
        border-bottom: 2px solid var(--border-color);
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 1;
        background: var(--table-header-bg);
    }

    .revenue-table td {
        padding: 12px 14px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }

    .revenue-table tbody tr {
        transition: background 0.15s;
        cursor: pointer;
    }

    .revenue-table tbody tr:hover {
        background: var(--bg-tertiary);
    }

    .revenue-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Revenue History Users List */
    #revenue-history-users-list {
        padding: 0 !important;
    }

    #revenue-history-users-list .revenue-history-user-item {
        padding: 12px 14px;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: background 0.15s;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    #revenue-history-users-list .revenue-history-user-item:last-child {
        border-bottom: none;
    }

    #revenue-history-users-list .revenue-history-user-item:hover {
        background: var(--bg-tertiary);
    }

    #revenue-history-users-list .revenue-history-user-item .user-name {
        font-weight: 600;
        font-size: 14px;
    }

    #revenue-history-users-list .revenue-history-user-item .user-email {
        font-size: 11px;
        opacity: 0.6;
    }

    #revenue-history-users-list .revenue-history-user-item .user-id {
        font-size: 10px;
        opacity: 0.4;
    }

    #revenue-history-users-list .revenue-history-user-item .user-record-count {
        font-size: 10px;
        opacity: 0.5;
        margin-top: 2px;
    }

    .user-cell .user-name {
        font-weight: 600;
        font-size: 14px;
    }

    .user-cell .user-email {
        font-size: 11px;
        opacity: 0.6;
    }

    .user-cell .user-id {
        font-size: 10px;
        opacity: 0.4;
        margin-top: 2px;
    }

    .revenue-table .profit {
        color: #4caf50;
        font-weight: 600;
    }

    .revenue-table .loss {
        color: #f44336;
        font-weight: 600;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
    }

    .status-active {
        background: #e3f2fd;
        color: #1565c0;
    }

    .status-above {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .status-profit {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .status-loss {
        background: #ffebee;
        color: #c62828;
    }

    .status-breakeven {
        background: #fff3e0;
        color: #e65100;
    }

    .status-confirmed {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .status-made {
        background: #fff3e0;
        color: #e65100;
    }

    .status-unpaid {
        background: #ffebee;
        color: #c62828;
    }

    .status-failed {
        background: #f5f5f5;
        color: #616161;
    }

    .status-normal {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .status-default {
        background: #f5f5f5;
        color: #616161;
    }

    .status-unusual {
        background: #fff3e0;
        color: #e65100;
        border: 1px solid #ff9800;
    }

    .status-cancelled {
        background: #f3e5f5;
        color: #7b1fa2;
    }

    .status-below {
        background: #e3f2fd;
        color: #0d47a1;
    }

    .action-select {
        padding: 4px 8px;
        border-radius: 4px;
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-color);
        font-size: 12px;
        cursor: pointer;
        min-width: 120px;
    }

    .action-select:focus {
        outline: none;
        border-color: var(--accent-color);
    }

    .status-select {
        padding: 4px 8px;
        border-radius: 4px;
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-color);
        font-size: 12px;
        cursor: pointer;
        min-width: 130px;
    }

    .status-select:focus {
        outline: none;
        border-color: var(--accent-color);
    }

    .clickable-row {
        cursor: pointer;
    }

    .clickable-row:hover td {
        background: var(--bg-tertiary);
    }

    /* ============================================
    USER DETAIL OVERLAY - Full Screen
    ============================================ */
    .detail-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--bg-color);
        z-index: 3000;
        overflow-y: auto;
        padding: 20px;
    }

    .detail-overlay-content {
        max-width: 1200px;
        margin: 0 auto;
    }

    .detail-overlay-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 0 20px 0;
        border-bottom: 2px solid var(--border-color);
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .detail-overlay-header h2 {
        margin: 0;
        font-size: 1.3rem;
        color: var(--text-color);
        flex: 1;
        text-align: center;
    }

    .back-btn {
        padding: 8px 20px;
        border: none;
        background: var(--bg-secondary);
        color: var(--text-color);
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s;
        border: 1px solid var(--border-color);
    }

    .back-btn:hover {
        background: var(--bg-tertiary);
    }

    /* ============================================
    USER DETAIL GRID
    ============================================ */
    .user-detail-grid {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .detail-card-full {
        background: var(--container-bg);
        border-radius: 12px;
        padding: 20px;
        border: 1px solid var(--border-color);
    }

    .detail-user-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 10px;
    }

    .detail-user-header h3 {
        margin: 0 0 4px 0;
        font-size: 1.2rem;
        color: var(--text-color);
    }

    .detail-user-email {
        margin: 2px 0;
        font-size: 14px;
        color: var(--text-secondary);
    }

    .detail-user-id {
        margin: 2px 0;
        font-size: 12px;
        color: #888;
    }

    .detail-user-status {
        flex-shrink: 0;
    }

    /* Stats Grid */
    .detail-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
    }

    .detail-stat-card {
        background: var(--container-bg);
        border-radius: 10px;
        padding: 14px 16px;
        border: 1px solid var(--border-color);
        text-align: center;
    }

    .detail-stat-card .stat-label {
        font-size: 11px;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .detail-stat-card .stat-value {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-color);
        margin-top: 4px;
    }

    .detail-stat-card .stat-value.profit {
        color: #4caf50;
    }

    .detail-stat-card .stat-value.loss {
        color: #f44336;
    }

    .detail-stat-card .stat-value.unusual {
        color: #e65100;
    }

    /* ============================================
    DETAIL TABS
    ============================================ */
    .detail-tabs-wrapper {
        margin-top: 4px;
    }

    .detail-tabs {
        display: flex;
        gap: 4px;
        background: var(--bg-secondary);
        border-radius: 10px;
        padding: 4px;
        border: 1px solid var(--border-color);
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }

    .detail-tabs::-webkit-scrollbar {
        display: none;
    }

    .detail-tab-btn {
        flex: 1;
        padding: 10px 16px;
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
    }

    .detail-tab-btn.active {
        background: var(--accent-color);
        color: white;
    }

    .detail-tab-btn:hover:not(.active) {
        background: var(--bg-tertiary);
    }

    .detail-tab-content {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    .detail-tab-content.active {
        display: block;
    }

    /* ============================================
    DAILY TARGET LIST - Updated for left/right layout
    ============================================ */
    .daily-target-list {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .daily-target-item {
        display: flex;
        justify-content: space-between;
        align-items: stretch;
        padding: 12px 16px;
        background: var(--bg-secondary);
        border-radius: 8px;
        border-left: 3px solid var(--border-color);
        transition: all 0.2s;
        gap: 12px;
        flex-wrap: nowrap;
    }

    .daily-target-item.unusual {
        border-left-color: #ff9800;
        background: #fff8e1;
    }

    .daily-target-item .target-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
        min-width: 180px;
        flex-wrap: wrap;
    }

    .daily-target-item .target-day {
        font-weight: 600;
        font-size: 14px;
        color: var(--text-color);
        min-width: 90px;
    }

    .daily-target-item .target-date {
        font-size: 11px;
        color: #888;
        min-width: 100px;
    }

    .daily-target-item .target-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 2px;
        flex-shrink: 0;
        min-width: 120px;
    }

    .daily-target-item .target-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        width: 100%;
        font-size: 12px;
    }

    .daily-target-item .target-label {
        color: #888;
        font-weight: 400;
        min-width: 70px;
        text-align: left;
    }

    .daily-target-item .target-value {
        font-weight: 600;
        color: var(--text-color);
        text-align: right;
        min-width: 60px;
    }

    .daily-target-item .target-value.remaining {
        color: #ff9800;
    }

    .daily-target-item .target-value .status-badge {
        font-size: 10px;
        padding: 2px 10px;
    }

    /* Week Container */
    .week-container {
        margin: 16px 0;
        padding: 12px;
        background: var(--container-bg);
        border-radius: 10px;
        border: 1px solid var(--border-color);
    }

    .week-container:first-child {
        margin-top: 0;
    }

    .week-container:last-child {
        margin-bottom: 0;
    }

    .week-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0 12px 0;
        border-bottom: 2px solid var(--border-color);
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 8px;
    }

    .week-label {
        font-weight: 700;
        font-size: 15px;
        color: var(--text-color);
        letter-spacing: 0.5px;
    }

    .week-summary {
        font-size: 12px;
        color: #888;
        font-weight: 500;
    }

    /* Status badges for daily target */
    .status-met {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .status-owed {
        background: #fff3e0;
        color: #e65100;
    }

    .status-pending {
        background: #e3f2fd;
        color: #0d47a1;
    }

    .status-not-listed {
        background: #f5f5f5;
        color: #888;
    }

    /* ============================================
    BALANCE LOG LIST
    ============================================ */
    .balance-log-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .balance-log-item {
        background: var(--bg-secondary);
        border-radius: 8px;
        border-left: 3px solid var(--border-color);
        overflow: hidden;
        transition: all 0.2s;
    }

    .balance-log-item.unusual {
        border-left-color: #ff9800;
        background: #fff8e1;
    }

    .balance-log-item .log-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        cursor: pointer;
        transition: background 0.2s;
        gap: 10px;
        flex-wrap: wrap;
    }

    .balance-log-item .log-header:hover {
        background: var(--bg-tertiary);
    }

    .balance-log-item .log-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .balance-log-item .log-day {
        font-weight: 600;
        font-size: 14px;
        color: var(--text-color);
        min-width: 80px;
    }

    .balance-log-item .log-date {
        font-size: 12px;
        color: #888;
        min-width: 100px;
    }

    .balance-log-item .log-toggle {
        font-size: 14px;
        color: #888;
        cursor: pointer;
        padding: 0 4px;
        transition: transform 0.2s;
        flex-shrink: 0;
    }

    .balance-log-item .log-details {
        display: none;
        padding: 12px 16px 16px 16px;
        border-top: 1px solid var(--border-color);
        background: var(--bg-color);
    }

    .balance-log-item .log-details .log-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        border-bottom: 1px solid rgba(128, 128, 128, 0.08);
        gap: 16px;
        flex-wrap: wrap;
    }

    .balance-log-item .log-details .log-row:last-child {
        border-bottom: none;
    }

    .balance-log-item .log-details .log-label {
        font-size: 13px;
        color: #888;
        min-width: 180px;
    }

    .balance-log-item .log-details .log-value {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-color);
        text-align: right;
    }

    .balance-log-item .log-details .log-value.profit {
        color: #4caf50;
    }

    .balance-log-item .log-details .log-value.loss {
        color: #f44336;
    }

    .balance-log-item .log-details .log-value.unusual {
        color: #e65100;
    }

    /* Unauthorized Trades Section */
    .unauthorized-trades-section {
        margin-top: 10px;
        padding: 10px 14px;
        background: var(--bg-secondary);
        border-radius: 6px;
        border-left: 3px solid #ff9800;
    }

    .unauthorized-trades-section .log-label {
        font-size: 12px;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-bottom: 6px;
        display: block;
    }

    .trade-row-detail {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 4px 0;
        border-bottom: 1px solid var(--border-color);
        font-size: 13px;
        flex-wrap: wrap;
    }

    .trade-row-detail:last-child {
        border-bottom: none;
    }

    .trade-row-detail .trade-symbol {
        font-weight: 600;
        min-width: 60px;
    }

    .trade-row-detail .trade-pnl {
        font-weight: 600;
    }

    .trade-row-detail .trade-pnl.profit {
        color: #4caf50;
    }

    .trade-row-detail .trade-pnl.loss {
        color: #f44336;
    }

    .trade-row-detail .trade-meta {
        font-size: 11px;
        color: #888;
        margin-left: auto;
    }

    /* ============================================
    MODAL
    ============================================ */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        z-index: 4000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-container {
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
        animation: modalSlideIn 0.3s ease;
    }

    .modal-container.modal-large {
        width: 550px;
    }

    .modal-container.modal-small {
        width: 450px;
        max-width: 95%;
    }

    @keyframes modalSlideIn {
        from { transform: translateY(-30px) scale(0.95); opacity: 0; }
        to { transform: translateY(0) scale(1); opacity: 1; }
    }

    .modal-header {
        padding: 16px 20px;
        background: var(--table-header-bg);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }

    .modal-header span {
        font-weight: 700;
        font-size: 16px;
    }

    .modal-close {
        cursor: pointer;
        font-size: 20px;
        font-weight: 700;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: var(--bg-tertiary);
        transition: all 0.2s;
        flex-shrink: 0;
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

    .modal-body p {
        margin: 0 0 12px 0;
        font-size: 14px;
        color: var(--text-secondary);
    }

    .modal-body input {
        width: 100%;
        padding: 10px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-color);
        font-size: 14px;
        box-sizing: border-box;
    }

    .modal-body input:focus {
        outline: none;
        border-color: var(--accent-color);
    }

    .modal-buttons {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 16px;
    }

    .modal-buttons button {
        padding: 8px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
    }

    .modal-buttons .btn-cancel {
        border: 1px solid var(--border-color);
        background: transparent;
        color: var(--text-color);
    }

    .modal-buttons .btn-cancel:hover {
        background: var(--bg-tertiary);
    }

    .modal-buttons .btn-confirm {
        border: none;
        background: var(--accent-color);
        color: white;
    }

    .modal-buttons .btn-confirm:hover {
        background: var(--accent-hover);
    }

    /* Users Modal List */
    .modal-user-item {
        padding: 12px 16px;
        cursor: pointer;
        transition: background 0.2s;
        border-bottom: 1px solid var(--border-color);
    }

    .modal-user-item:last-child {
        border-bottom: none;
    }

    .modal-user-item:hover {
        background: var(--bg-tertiary);
    }

    .modal-user-item.selected {
        background: var(--accent-color);
        color: white;
    }

    .modal-user-item .modal-user-name {
        font-weight: 600;
        font-size: 14px;
    }

    .modal-user-item .modal-user-email {
        font-size: 12px;
        opacity: 0.6;
    }

    .modal-user-item .modal-user-id {
        font-size: 10px;
        opacity: 0.4;
    }

    .users-modal-search {
        margin-bottom: 12px;
    }

    .user-search-input {
        width: 100%;
        padding: 10px 14px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-color);
        font-size: 14px;
        box-sizing: border-box;
    }

    .user-search-input:focus {
        outline: none;
        border-color: var(--accent-color);
    }

    /* ============================================
    LOADING & EMPTY STATES
    ============================================ */
    .loading-spinner {
        text-align: center;
        padding: 40px;
    }

    .loading-spinner .spinner {
        border: 3px solid var(--border-color);
        border-top: 3px solid var(--accent-color);
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto 12px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
    }

    .empty-state .empty-icon {
        font-size: 36px;
        font-weight: 700;
        color: #888;
        margin-bottom: 12px;
    }

    .empty-state .empty-text {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 6px;
        color: var(--text-color);
    }

    .empty-state .empty-sub {
        font-size: 13px;
        color: #888;
    }

    /* ============================================
    DARK MODE SUPPORT
    ============================================ */
    @media (prefers-color-scheme: dark) {
        .daily-target-item.unusual,
        .balance-log-item.unusual {
            background: #2a1a0a;
        }
        
        .status-unusual {
            background: #3a2a0a;
            color: #ffa726;
            border-color: #ff9800;
        }
        
        .status-active {
            background: #1a3a5c;
            color: #64b5f6;
        }
        
        .status-above,
        .status-profit,
        .status-normal {
            background: #1b3a1b;
            color: #81c784;
        }
        
        .status-loss {
            background: #3a1a1a;
            color: #ef5350;
        }
        
        .status-breakeven {
            background: #3a2a0a;
            color: #ffa726;
        }
        
        .status-confirmed {
            background: #1b3a1b;
            color: #81c784;
        }
        
        .status-made {
            background: #3a2a0a;
            color: #ffa726;
        }
        
        .status-unpaid {
            background: #3a1a1a;
            color: #ef5350;
        }
        
        .status-failed {
            background: #2a2a2a;
            color: #bdbdbd;
        }
        
        .status-cancelled {
            background: #2a1a3a;
            color: #ce93d8;
        }
        
        .status-below {
            background: #1a2a3a;
            color: #64b5f6;
        }

        .status-met {
            background: #1b3a1b;
            color: #81c784;
        }

        .status-owed {
            background: #3a2a0a;
            color: #ffa726;
        }

        .status-pending {
            background: #1a2a3a;
            color: #64b5f6;
        }

        .status-not-listed {
            background: #2a2a2a;
            color: #888;
        }
    }

    /* ============================================
    RESPONSIVE
    ============================================ */
    @media (max-width: 992px) {
        .revenue-history-user-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    @media (max-width: 768px) {
        .detail-stats-grid {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .detail-overlay {
            padding: 10px;
        }
        
        .detail-overlay-header h2 {
            font-size: 1rem;
        }
        
        .detail-user-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .detail-tab-btn {
            font-size: 11px;
            padding: 8px 12px;
        }
        
        .daily-target-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
            padding: 12px 14px;
        }

        .daily-target-item .target-left {
            min-width: unset;
            width: 100%;
            gap: 8px;
        }

        .daily-target-item .target-right {
            min-width: unset;
            width: 100%;
            align-items: stretch;
        }

        .daily-target-item .target-row {
            gap: 12px;
        }

        .daily-target-item .target-label {
            min-width: 60px;
            font-size: 11px;
        }

        .daily-target-item .target-value {
            font-size: 12px;
            min-width: 50px;
        }

        .week-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }

        .balance-log-item .log-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .balance-log-item .log-left {
            width: 100%;
        }
        
        .balance-log-item .log-toggle {
            align-self: flex-end;
            margin-top: -24px;
        }
        
        .balance-log-item .log-details .log-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
        }
        
        .balance-log-item .log-details .log-value {
            text-align: left;
        }
        
        .trade-row-detail {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }
        
        .trade-row-detail .trade-meta {
            margin-left: 0;
        }
        
        .modal-container {
            width: 100%;
            margin: 10px;
            border-radius: 8px;
            max-height: 95vh;
        }
        
        .modal-container.modal-large {
            width: 100%;
        }
        
        .revenue-table {
            font-size: 11px;
        }
        
        .revenue-table th,
        .revenue-table td {
            padding: 6px 8px;
        }
        
        .user-cell .user-name {
            font-size: 12px;
        }
    }

    @media (max-width: 480px) {
        .detail-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .summary-cubes {
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 8px;
        }
        
        .summary-cube {
            padding: 12px 8px;
            min-height: 70px;
        }
        
        .summary-cube .cube-value {
            font-size: 16px;
        }
        
        .revenue-tabs .tab-btn {
            font-size: 10px;
            padding: 5px 10px;
        }
        
        .sub-tabs .sub-tab-btn {
            font-size: 9px;
            padding: 4px 8px;
        }
        
        .detail-tab-btn {
            font-size: 10px;
            padding: 6px 10px;
        }
        
        .revenue-table {
            font-size: 10px;
        }
        
        .revenue-table th,
        .revenue-table td {
            padding: 5px 6px;
        }
        
        .daily-target-item .target-day {
            min-width: 60px;
            font-size: 12px;
        }
        
        .daily-target-item .target-date {
            min-width: 80px;
            font-size: 10px;
        }
        
        .daily-target-item .target-label {
            min-width: 50px;
            font-size: 10px;
        }

        .daily-target-item .target-value {
            font-size: 11px;
            min-width: 45px;
        }
        
        .balance-log-item .log-day {
            min-width: 60px;
            font-size: 12px;
        }
        
        .balance-log-item .log-date {
            min-width: 80px;
            font-size: 10px;
        }
        
        .balance-log-item .log-details .log-label {
            min-width: 120px;
            font-size: 11px;
        }
        
        .action-select {
            font-size: 10px;
            min-width: 80px;
            padding: 2px 6px;
        }
        
        .status-select {
            font-size: 10px;
            min-width: 90px;
            padding: 2px 6px;
        }

        .week-label {
            font-size: 13px;
        }
    }
</style>

<style>
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
        background: #3498db;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.2s;
        height: 32px;
    }

    .refresh-risk-btn:hover {
        background: #2980b9;
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
</style>
