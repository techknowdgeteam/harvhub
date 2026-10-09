<?php
// phpmyadmintemplate.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>DB</text></svg>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Query Interface — Multi Window</title>
    <style>
        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #eef1f4;
            height: 100%;
            overflow: hidden; /* page itself doesn't scroll — each window does */
        }

        #topbar {
            height: 46px;
            background: #1e293b;
            color: #fff;
            display: flex;
            align-items: center;
            padding: 0 16px;
            font-size: 14px;
            font-weight: 600;
            gap: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            position: relative;
            z-index: 100;
        }
        #topbar .brand { font-size: 15px; letter-spacing: 0.3px; }
        #topbar .info {
            margin-left: auto;
            font-size: 12px;
            font-weight: 400;
            opacity: 0.75;
            font-family: 'Consolas','Monaco',monospace;
        }

        /* ────────── WORKSPACE ────────── */
        #workspace {
            position: absolute;
            top: 46px; left: 0; right: 0; bottom: 0;
            overflow: auto;
            padding: 16px 100px 16px 16px; /* right space for the floating button */
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        /* ────────── FLOATING NEW WINDOW BUTTON ────────── */
        #new-window-btn {
            position: fixed;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 500;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #007bff;
            color: #fff;
            border: none;
            font-size: 28px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(0,123,255,0.45);
            transition: transform 0.15s, background-color 0.15s, box-shadow 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #new-window-btn:hover {
            background: #0056b3;
            transform: translateY(-50%) scale(1.08);
            box-shadow: 0 8px 26px rgba(0,86,179,0.55);
        }
        #new-window-btn:active {
            transform: translateY(-50%) scale(0.98);
        }
        #new-window-btn .tooltip {
            position: absolute;
            right: 76px;
            top: 50%;
            transform: translateY(-50%);
            background: #1e293b;
            color: #fff;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.15s;
        }
        #new-window-btn:hover .tooltip { opacity: 1; }

        /* ────────── WINDOW ────────── */
        .db-window {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            border: 1px solid #d8dee5;
            display: flex;
            flex-direction: column;
            min-height: 420px;
            max-height: 85vh;
            overflow: hidden;
            transition: box-shadow 0.15s;
        }
        .db-window:hover { box-shadow: 0 5px 18px rgba(0,0,0,0.12); }
        .db-window.focused { box-shadow: 0 5px 22px rgba(0,123,255,0.25); border-color: #b6d4fe; }

        .db-window .win-titlebar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: linear-gradient(180deg, #f8fafc, #eef2f7);
            border-bottom: 1px solid #dde3ea;
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            flex-shrink: 0;
        }
        .db-window .win-id {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 24px;
            padding: 0 8px;
            background: #007bff;
            color: #fff;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            font-family: 'Consolas','Monaco',monospace;
        }
        .db-window.focused .win-id { background: #0056b3; }
        .db-window .win-label {
            font-size: 12px;
            font-weight: 500;
            color: #475569;
            flex: 1;
        }
        .db-window .win-actions { display: flex; gap: 6px; }
        .db-window .win-actions button {
            width: auto;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            cursor: pointer;
            transition: background 0.12s, color 0.12s, border-color 0.12s;
        }
        .db-window .win-actions button:hover { background: #f1f5f9; }
        .db-window .win-actions button.close-btn { color: #b91c1c; border-color: #fecaca; }
        .db-window .win-actions button.close-btn:hover { background: #fee2e2; }

        /* ────────── WINDOW BODY (scrolls internally) ────────── */
        .db-window .win-body {
            flex: 1;
            min-height: 0;
            overflow: auto;
            padding: 14px;
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 16px;
        }
        .db-window.minimized .win-body { display: none; }
        .db-window.minimized { min-height: 0; max-height: none; }

        /* ────────── SIDEBAR ────────── */
        .win-sidebar {
            display: flex;
            flex-direction: column;
            gap: 14px;
            min-width: 0;
        }

        /* ────────── MAIN CONTENT ────────── */
        .win-main {
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-width: 0;
        }
        .win-main label {
            font-weight: bold;
            color: #444;
            font-size: 12px;
            margin-bottom: 4px;
            display: block;
        }
        .win-main select,
        .win-main textarea,
        .win-main button {
            padding: 8px 10px;
            font-size: 13px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 100%;
            font-family: inherit;
        }
        .win-main textarea {
            height: 150px;
            resize: vertical;
            font-family: 'Consolas','Monaco',monospace;
            min-height: 90px;
        }
        .win-main select:focus,
        .win-main textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0,123,255,0.3);
        }
        .win-main .exec-btn {
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.15s;
        }
        .win-main .exec-btn:hover { background-color: #0056b3; }
        .win-main .exec-btn:disabled { background-color: #6c757d; cursor: not-allowed; }
        .win-main .hint {
            font-size: 11px;
            color: #666;
            margin-top: 2px;
        }

        /* ────────── EXEC STATUS (per window) ────────── */
        .win-exec-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .win-exec-status[data-state="idle"]    { background: #eef1f4; border-color: #d3d8dd; color: #4a5159; }
        .win-exec-status[data-state="loading"] { background: #e7f1ff; border-color: #b6d4fe; color: #084298; }
        .win-exec-status[data-state="success"] { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .win-exec-status[data-state="partial"] { background: #fff3cd; border-color: #ffe69c; color: #664d03; }
        .win-exec-status[data-state="empty"]   { background: #fff3cd; border-color: #ffe69c; color: #664d03; }
        .win-exec-status[data-state="error"]   { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .win-exec-status .status-icon {
            width: 16px; height: 16px;
            display: inline-block;
            text-align: center; line-height: 16px;
            flex-shrink: 0;
        }
        .win-exec-status[data-state="loading"] .status-icon::before {
            content: "";
            display: inline-block;
            width: 12px; height: 12px;
            border: 2px solid #084298; border-top-color: transparent;
            border-radius: 50%;
            animation: exec-spin 0.7s linear infinite;
            vertical-align: middle;
        }
        @keyframes exec-spin { to { transform: rotate(360deg); } }
        .win-exec-status[data-state="success"] .status-icon::before { content: "✔"; }
        .win-exec-status[data-state="partial"] .status-icon::before { content: "◐"; }
        .win-exec-status[data-state="empty"]   .status-icon::before { content: "○"; }
        .win-exec-status[data-state="error"]   .status-icon::before { content: "✖"; }
        .win-exec-status[data-state="idle"]    .status-icon::before { content: "•"; }
        .win-exec-status .status-meta {
            margin-left: auto;
            font-weight: 400;
            font-size: 11px;
            opacity: 0.8;
            font-family: 'Consolas','Monaco',monospace;
        }

        /* ────────── SQL DISPLAY ────────── */
        .win-sql-display {
            border: 1px solid #cfe2ff;
            background-color: #f0f7ff;
            border-radius: 4px;
            overflow: hidden;
        }
        .win-sql-display .sql-header {
            background-color: #cfe2ff;
            color: #084298;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .win-sql-display .source-badge {
            background-color: #084298;
            color: #fff;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            text-transform: none;
            letter-spacing: 0;
            font-weight: normal;
        }
        .win-sql-display .sql-body {
            padding: 10px;
            font-family: 'Consolas','Monaco',monospace;
            font-size: 12px;
            color: #084298;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 150px;
            overflow: auto;
            line-height: 1.5;
            margin: 0;
        }

        /* ────────── SUMMARY STRIP ────────── */
        .win-results-summary {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 8px 12px;
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 12px;
        }
        .win-results-summary .pill {
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 11px;
        }
        .pill.total   { background: #e7f1ff; color: #084298; }
        .pill.ok      { background: #d4edda; color: #155724; }
        .pill.empty   { background: #fff3cd; color: #664d03; }
        .pill.skipped { background: #e9ecef; color: #495057; }
        .pill.failed  { background: #f8d7da; color: #721c24; }

        /* ────────── PER-STATEMENT BLOCKS ────────── */
        .win-query-result {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .stmt-block {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .stmt-block[data-status="success"] { border-left: 5px solid #28a745; }
        .stmt-block[data-status="empty"]   { border-left: 5px solid #ffc107; }
        .stmt-block[data-status="error"]   { border-left: 5px solid #dc3545; }
        .stmt-block[data-status="skipped"] { border-left: 5px solid #6c757d; }
        .stmt-block[data-status="unknown"] { border-left: 5px solid #6c757d; }

        .stmt-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-size: 12px;
            font-weight: 600;
            flex-wrap: wrap;
        }
        .stmt-header .stmt-index {
            background: #007bff;
            color: #fff;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            flex-shrink: 0;
        }
        .stmt-header .stmt-type-badge {
            background: #e7f1ff;
            color: #084298;
            border-radius: 4px;
            padding: 2px 8px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            flex-shrink: 0;
        }
        .stmt-header .stmt-status-icon {
            font-size: 14px;
            flex-shrink: 0;
            font-weight: 700;
        }
        .stmt-block[data-status="success"] .stmt-status-icon { color: #28a745; }
        .stmt-block[data-status="empty"]   .stmt-status-icon { color: #ffc107; }
        .stmt-block[data-status="error"]   .stmt-status-icon { color: #dc3545; }
        .stmt-block[data-status="skipped"] .stmt-status-icon { color: #6c757d; }

        .stmt-header .stmt-message {
            flex: 1;
            color: #333;
            font-weight: 500;
            font-size: 12px;
            word-break: break-word;
            min-width: 0;
        }
        .stmt-block[data-status="error"] .stmt-header .stmt-message { color: #721c24; }
        .stmt-block[data-status="skipped"] .stmt-header .stmt-message { color: #495057; }

        .stmt-header .stmt-meta {
            font-size: 10px;
            color: #666;
            font-weight: 400;
            font-family: 'Consolas','Monaco',monospace;
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 10px;
            flex-shrink: 0;
        }
        .stmt-block[data-status="success"] .stmt-header .stmt-meta { background: #d4edda; color: #155724; }
        .stmt-block[data-status="error"]   .stmt-header .stmt-meta { background: #f8d7da; color: #721c24; }
        .stmt-block[data-status="skipped"] .stmt-header .stmt-meta { background: #e9ecef; color: #495057; }

        .stmt-sql {
            padding: 6px 12px;
            background: #f0f7ff;
            border-bottom: 1px solid #e0e8f0;
            font-family: 'Consolas','Monaco',monospace;
            font-size: 11px;
            color: #084298;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 90px;
            overflow: auto;
        }

        .stmt-body { padding: 0; }
        .stmt-error-body { padding: 8px 12px; background: #f8d7da; color: #721c24; font-family: 'Consolas','Monaco',monospace; font-size: 11px; white-space: pre-wrap; word-break: break-word; }
        .stmt-skipped-body { padding: 8px 12px; background: #e9ecef; color: #495057; font-size: 12px; }
        .stmt-affected-body { padding: 8px 12px; background: #d4edda; color: #155724; font-size: 12px; }
        .stmt-empty-body { padding: 8px 12px; background: #fff3cd; color: #664d03; font-size: 12px; }

        /* ────────── TABLE ────────── */
        .table-scroll-container {
            width: 100%;
            overflow-x: auto;
            overflow-y: auto;
            max-height: 50vh;
            background: #fff;
            -webkit-overflow-scrolling: touch;
        }
        .table-scroll-container table {
            border-collapse: collapse;
            background-color: #fff;
            width: max-content;
            min-width: 100%;
            table-layout: auto;
        }
        .table-scroll-container th,
        .table-scroll-container td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
            font-size: 12px;
            vertical-align: top;
        }
        .table-scroll-container th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .table-scroll-container tr:nth-child(even) { background-color: #f9f9f9; }
        .table-scroll-container td .cell-content,
        .table-scroll-container th .cell-content {
            max-width: 320px;
            max-height: 110px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
            display: block;
            font-family: 'Consolas','Monaco',monospace;
            font-size: 11px;
            line-height: 1.4;
            scrollbar-width: thin;
        }
        .table-scroll-container th .cell-content { background: transparent; max-height: none; overflow: visible; }

        /* ────────── MESSAGE BANNERS (per window) ────────── */
        .win-message {
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            display: none;
        }
        .win-message.error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; }
        .win-message.success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; }

        /* ────────── SCROLLBARS ────────── */
        .db-window *::-webkit-scrollbar { width: 8px; height: 8px; }
        .db-window *::-webkit-scrollbar-thumb { background: #bcc4cd; border-radius: 4px; }
        .db-window *::-webkit-scrollbar-thumb:hover { background: #9aa3ad; }
        .db-window *::-webkit-scrollbar-track { background: transparent; }

        @media (max-width: 768px) {
            .db-window .win-body { grid-template-columns: 1fr; }
            #workspace { padding: 12px 84px 12px 12px; }
        }
    </style>
</head>
<body>
    <div id="topbar">
        <span class="brand">🗄️ Database Query Interface</span>
        <span class="info" id="window-count-info">Windows: 0</span>
    </div>

    <div id="workspace"></div>

    <button id="new-window-btn" title="Create new window">
        +
        <span class="tooltip">Create new window</span>
    </button>

    <script>
    /* =====================================================================
     *  GLOBAL WINDOW MANAGER
     *  Each window is a fully isolated engine.
     * ===================================================================== */
    const WindowManager = (function () {
        const MAX_ID = 1000000;
        const state = {
            nextId: 1,
            windows: new Map(),   // id -> controller
            usedIds: new Set(),
            globalTables: [],     // shared table cache across windows
        };

        function allocId() {
            // Sequential 1..1,000,000. If we ever wrap, find the lowest free.
            if (state.nextId <= MAX_ID && !state.usedIds.has(state.nextId)) {
                const id = state.nextId++;
                state.usedIds.add(id);
                return id;
            }
            for (let i = 1; i <= MAX_ID; i++) {
                if (!state.usedIds.has(i)) {
                    state.usedIds.add(i);
                    return i;
                }
            }
            return null; // exhausted
        }

        function releaseId(id) {
            state.usedIds.delete(id);
            state.windows.delete(id);
        }

        function updateCount() {
            const el = document.getElementById('window-count-info');
            if (el) el.textContent = 'Windows: ' + state.windows.size;
        }

        function createWindow() {
            const id = allocId();
            if (id === null) {
                alert('Window limit reached (1,000,000).');
                return null;
            }
            const ctrl = buildWindow(id);
            state.windows.set(id, ctrl);
            updateCount();
            return ctrl;
        }

        function closeWindow(id) {
            const ctrl = state.windows.get(id);
            if (!ctrl) return;
            ctrl.destroy();
            releaseId(id);
            updateCount();
        }

        function broadcastTables(tables) {
            state.globalTables = tables;
            state.windows.forEach(ctrl => ctrl.onTablesUpdate(tables));
        }

        function getGlobalTables() { return state.globalTables; }

        return {
            createWindow,
            closeWindow,
            broadcastTables,
            getGlobalTables,
            updateCount,
            get count() { return state.windows.size; }
        };
    })();

    /* =====================================================================
     *  HTML ESCAPE HELPER (shared)
     * ===================================================================== */
    function escapeHtml(str) {
        if (str === null || str === undefined) return 'NULL';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /* =====================================================================
     *  SHARED TABLE LIST FETCHER
     *  One fetch serves all windows.
     * ===================================================================== */
    let _globalTablePoll = null;
    function startGlobalTablePoll() {
        const fetchOnce = () => {
            fetch('phpmyadmin_tablesfetch.php')
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success' && Array.isArray(data.tables)) {
                        WindowManager.broadcastTables(data.tables);
                    }
                })
                .catch(() => { /* silent — each window shows its own errors */ });
        };
        fetchOnce();
        _globalTablePoll = setInterval(fetchOnce, 5000);
    }
    window.addEventListener('unload', () => {
        if (_globalTablePoll) clearInterval(_globalTablePoll);
    });

    /* =====================================================================
     *  BUILD ONE WINDOW CONTROLLER
     * ===================================================================== */
    function buildWindow(id) {
        const workspace = document.getElementById('workspace');
        const el = document.createElement('div');
        el.className = 'db-window';
        el.dataset.windowId = String(id);

        el.innerHTML = `
            <div class="win-titlebar">
                <span class="win-id">#${id}</span>
                <span class="win-label">Database Query Interface</span>
                <div class="win-actions">
                    <button type="button" class="min-btn" title="Minimize / Restore">Minimize</button>
                    <button type="button" class="close-btn" title="Close window">✕ Close</button>
                </div>
            </div>
            <div class="win-body">
                <div class="win-sidebar">
                    <div>
                        <label>Tables</label>
                        <select class="table-select"></select>
                    </div>
                    <div>
                        <label>Columns</label>
                        <select class="column-select"></select>
                    </div>
                </div>
                <div class="win-main">
                    <div class="win-exec-status" data-state="idle">
                        <span class="status-icon" aria-hidden="true"></span>
                        <span class="status-label">Waiting…</span>
                        <span class="status-meta"></span>
                    </div>

                    <div class="win-message"></div>

                    <div>
                        <label>SQL Query (multiple statements allowed)</label>
                        <textarea class="sql-query" placeholder="Enter your SQL queries here.&#10;Separate statements with ; or put each on its own line.&#10;&#10;Press Enter to execute, Shift+Enter for new line."></textarea>
                        <div class="hint">Press <b>Enter</b> to execute • <b>Shift+Enter</b> for new line • Statements run in dependency-safe order</div>
                    </div>

                    <button type="button" class="exec-btn">Execute Query</button>

                    <div class="win-sql-display" style="display:none;">
                        <div class="sql-header">
                            <span>SQL Query</span>
                            <span class="source-badge">manual</span>
                        </div>
                        <pre class="sql-body"></pre>
                    </div>

                    <div class="win-results-summary" style="display:none;"></div>
                    <div class="win-query-result"></div>
                </div>
            </div>
        `;

        workspace.appendChild(el);

        // ───── Element refs ─────
        const refs = {
            root:       el,
            idBadge:    el.querySelector('.win-id'),
            minBtn:     el.querySelector('.min-btn'),
            closeBtn:   el.querySelector('.close-btn'),
            tableSel:   el.querySelector('.table-select'),
            colSel:     el.querySelector('.column-select'),
            status:     el.querySelector('.win-exec-status'),
            statusLbl:  el.querySelector('.status-label'),
            statusMeta: el.querySelector('.status-meta'),
            statusIcon: el.querySelector('.status-icon'),
            message:    el.querySelector('.win-message'),
            textarea:   el.querySelector('.sql-query'),
            execBtn:    el.querySelector('.exec-btn'),
            sqlDisplay: el.querySelector('.win-sql-display'),
            sqlBody:    el.querySelector('.sql-body'),
            sqlSource:  el.querySelector('.source-badge'),
            summary:    el.querySelector('.win-results-summary'),
            results:    el.querySelector('.win-query-result'),
        };

        // ───── Per-window state ─────
        const st = {
            isExecuting: false,
            cachedTables: [],
            cachedColumns: [],
            lastExecutedTable: null,
            isInitialLoad: true,
            columnPollInterval: null,
            queryCounter: 0,
            destroyed: false,
        };

        // ───── Focus tracking ─────
        el.addEventListener('mousedown', () => {
            document.querySelectorAll('.db-window.focused').forEach(w => w.classList.remove('focused'));
            el.classList.add('focused');
        }, true);

        // ───── Status setter ─────
        function setStatus(state, opts = {}) {
            if (st.destroyed) return;
            refs.status.dataset.state = state;
            switch (state) {
                case 'idle':
                    refs.statusLbl.textContent = 'Waiting…';
                    refs.statusMeta.textContent = '';
                    break;
                case 'loading':
                    refs.statusLbl.textContent = 'Loading…';
                    refs.statusMeta.textContent = opts.queryId ? `#${opts.queryId}` : '';
                    break;
                case 'success':
                    refs.statusLbl.textContent = 'All statements succeeded';
                    refs.statusMeta.textContent = `${opts.succeeded ?? 0}/${opts.statements ?? 0} ok`;
                    break;
                case 'partial':
                    refs.statusLbl.textContent = 'Some statements failed';
                    refs.statusMeta.textContent = `${opts.succeeded ?? 0}/${opts.statements ?? 0} ok · ${opts.failed ?? 0} failed`;
                    break;
                case 'empty':
                    refs.statusLbl.textContent = 'Completed — 0 rows';
                    refs.statusMeta.textContent = '';
                    break;
                case 'error':
                    refs.statusLbl.textContent = 'Error: ' + (opts.message || 'unknown');
                    refs.statusMeta.textContent = '';
                    break;
                default:
                    refs.statusLbl.textContent = state;
            }
        }

        function showMessage(text, kind) {
            if (st.destroyed) return;
            refs.message.className = 'win-message ' + (kind || 'success');
            refs.message.textContent = text;
            refs.message.style.display = 'block';
        }
        function hideMessage() {
            if (st.destroyed) return;
            refs.message.style.display = 'none';
        }

        function showSqlQuery(sql, source) {
            if (st.destroyed) return;
            refs.sqlBody.textContent = sql;
            refs.sqlSource.textContent = source || 'manual';
            refs.sqlDisplay.style.display = 'block';
        }

        // ───── Table builder ─────
        function buildTable(columnNames, rows) {
            let html = '<div class="table-scroll-container"><table><thead><tr>';
            columnNames.forEach(name => {
                html += `<th><span class="cell-content">${escapeHtml(name)}</span></th>`;
            });
            html += '</tr></thead><tbody>';
            rows.forEach(row => {
                html += '<tr>';
                columnNames.forEach(name => {
                    const val = row[name];
                    const display = (val === null || val === undefined) ? 'NULL' : val;
                    html += `<td><span class="cell-content">${escapeHtml(display)}</span></td>`;
                });
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            return html;
        }

        // ───── Per-statement block ─────
        function renderStatementBlock(r, index) {
            const status  = r.status || 'unknown';
            const sql     = r.sql || '';
            const message = r.message || '';
            const type    = r.type || 'OTHER';

            let icon = '•';
            if (status === 'success') icon = '✔';
            else if (status === 'empty') icon = '○';
            else if (status === 'error') icon = '✖';
            else if (status === 'skipped') icon = '⏭';

            let meta = '';
            if (status === 'success' && r.isReadQuery)          meta = `${(r.rows || []).length} row(s)`;
            else if (status === 'success' && !r.isReadQuery)    meta = `${r.affectedRows ?? 0} affected`;
            else if (status === 'empty')                        meta = '0 rows';
            else if (status === 'error')                        meta = 'failed';
            else if (status === 'skipped')                      meta = 'skipped';

            let html = `<div class="stmt-block" data-status="${escapeHtml(status)}" data-index="${index}">`;
            html +=   '<div class="stmt-header">';
            html +=     `<span class="stmt-index">#${index + 1}</span>`;
            html +=     `<span class="stmt-type-badge">${escapeHtml(type)}</span>`;
            html +=     `<span class="stmt-status-icon">${icon}</span>`;
            html +=     `<span class="stmt-message">${escapeHtml(message)}</span>`;
            html +=     `<span class="stmt-meta">${escapeHtml(meta)}</span>`;
            html +=   '</div>';
            html +=   `<div class="stmt-sql">${escapeHtml(sql)}</div>`;
            html +=   '<div class="stmt-body">';

            if (status === 'error') {
                html += `<div class="stmt-error-body">${escapeHtml(r.message || 'Unknown error')}</div>`;
            } else if (status === 'skipped') {
                html += `<div class="stmt-skipped-body">${escapeHtml(r.message || 'Skipped due to failed prerequisite.')}</div>`;
            } else if (status === 'empty') {
                html += '<div class="stmt-empty-body">Statement ran but returned 0 rows.</div>';
            } else if (status === 'success' && r.isReadQuery) {
                const columnNames = (r.columnMeta || []).map(c => c.name);
                const rows = r.rows || [];
                if (rows.length > 0 && columnNames.length > 0) html += buildTable(columnNames, rows);
                else html += '<div class="stmt-empty-body">Statement ran but returned 0 rows.</div>';
            } else if (status === 'success' && !r.isReadQuery) {
                html += `<div class="stmt-affected-body">Statement executed successfully. Affected rows: ${r.affectedRows ?? 0}</div>`;
            } else {
                html += `<div class="stmt-empty-body">No output for this statement.</div>`;
            }

            html +=   '</div>';
            html += '</div>';
            return html;
        }

        // ───── Render all results ─────
        function renderResults(results, queryId) {
            if (st.destroyed) return;
            let succeeded = 0, failed = 0, empty = 0, skipped = 0, totalRows = 0;
            results.forEach(r => {
                if (r.status === 'success')      { succeeded++; if (r.isReadQuery) totalRows += (r.rows || []).length; }
                else if (r.status === 'empty')   { empty++; }
                else if (r.status === 'error')   { failed++; }
                else if (r.status === 'skipped') { skipped++; }
            });

            let sHtml = '';
            sHtml += `<span class="pill total">Total: ${results.length}</span>`;
            sHtml += `<span class="pill ok">Succeeded: ${succeeded}</span>`;
            if (empty > 0)   sHtml += `<span class="pill empty">Empty: ${empty}</span>`;
            if (skipped > 0) sHtml += `<span class="pill skipped">Skipped: ${skipped}</span>`;
            if (failed > 0)  sHtml += `<span class="pill failed">Failed: ${failed}</span>`;
            refs.summary.innerHTML = sHtml;
            refs.summary.style.display = 'flex';

            let html = '';
            results.forEach((r, i) => { html += renderStatementBlock(r, i); });
            refs.results.innerHTML = html;

            if (failed === 0 && skipped === 0 && succeeded > 0) {
                setStatus('success', { statements: results.length, succeeded, failed: 0, rows: totalRows, queryId });
            } else if (failed > 0 || skipped > 0) {
                setStatus('partial', { statements: results.length, succeeded, failed: failed + skipped, rows: totalRows, queryId });
            } else if (succeeded === 0 && empty === 0) {
                setStatus('error', { message: 'no statements succeeded', queryId });
            } else {
                setStatus('empty', { queryId });
            }
        }

        // ───── Load columns for selected table ─────
        function loadColumns(table) {
            if (st.destroyed || !table) return;
            fetch(`phpmyadmin_tablesfetch.php?table=${encodeURIComponent(table)}`)
                .then(r => r.json())
                .then(data => {
                    if (st.destroyed) return;
                    if (data.status !== 'success') {
                        showMessage(data.message, 'error');
                        refs.colSel.innerHTML = '';
                        return;
                    }
                    const newColumns = data.columns.map(c => c.Field);
                    if (JSON.stringify(newColumns.slice().sort()) !== JSON.stringify(st.cachedColumns.slice().sort())) {
                        st.cachedColumns = newColumns;
                        const cur = refs.colSel.value;
                        const selectedColumn = cur && newColumns.includes(cur) ? cur : (newColumns[0] || '');
                        refs.colSel.innerHTML = '';
                        data.columns.forEach(c => {
                            const o = document.createElement('option');
                            o.value = c.Field;
                            o.textContent = `${c.Field} (${c.Type})`;
                            if (c.Field === selectedColumn) o.selected = true;
                            refs.colSel.appendChild(o);
                        });
                    }
                })
                .catch(err => {
                    if (st.destroyed) return;
                    showMessage('Error fetching columns: ' + err.message, 'error');
                    refs.colSel.innerHTML = '';
                });
        }

        // ───── Called by WindowManager when global table list updates ─────
        function onTablesUpdate(newTables) {
            if (st.destroyed) return;
            if (JSON.stringify(newTables.slice().sort()) !== JSON.stringify(st.cachedTables.slice().sort())) {
                st.cachedTables = newTables;
                const currentTable = refs.tableSel.value;
                const selectedTable = currentTable && newTables.includes(currentTable) ? currentTable : (newTables[0] || '');
                refs.tableSel.innerHTML = '';
                newTables.forEach(t => {
                    const o = document.createElement('option');
                    o.value = t; o.textContent = t;
                    if (t === selectedTable) o.selected = true;
                    refs.tableSel.appendChild(o);
                });
                if (selectedTable) {
                    loadColumns(selectedTable);
                } else {
                    refs.colSel.innerHTML = '';
                    if (st.columnPollInterval) { clearInterval(st.columnPollInterval); st.columnPollInterval = null; }
                }
            }
            st.isInitialLoad = false;
        }

        // ───── Auto DESCRIBE on table change ─────
        function autoExecuteTableQuery(tableName) {
            if (st.destroyed || !tableName) return;
            const safe = String(tableName).replace(/[^a-zA-Z0-9_]/g, '');
            if (!safe) return;
            const sql = `DESCRIBE \`${safe}\``;
            refs.textarea.value = sql;
            executeQuery({ sql, source: 'auto: table select' });
        }

        // ───── Execute ─────
        function executeQuery(options = {}) {
            if (st.destroyed || st.isExecuting) return;

            const useOverride = typeof options.sql === 'string' && options.sql.length > 0;
            const sqlQuery = useOverride ? options.sql.trim() : refs.textarea.value.trim();
            const source   = options.source || 'manual';

            if (!sqlQuery) {
                showMessage('Please enter an SQL query.', 'error');
                refs.results.innerHTML = '';
                refs.summary.style.display = 'none';
                setStatus('error', { message: 'empty query' });
                return;
            }

            const queryId = ++st.queryCounter;
            showSqlQuery(sqlQuery, source);
            setStatus('loading', { queryId });
            refs.results.innerHTML = '';
            refs.summary.style.display = 'none';
            refs.summary.innerHTML = '';
            hideMessage();

            st.isExecuting = true;
            refs.execBtn.disabled = true;
            refs.execBtn.textContent = 'Executing...';

            if (source === 'auto: table select') {
                const m = sqlQuery.match(/(?:FROM|DESCRIBE|INTO|UPDATE|TABLE)\s+`?([a-zA-Z0-9_]+)`?/i);
                if (m) st.lastExecutedTable = m[1];
            }

            fetch('phpmyadmin_tablesfetch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `sql_query=${encodeURIComponent(sqlQuery)}`
            })
                .then(r => r.json())
                .then(data => {
                    if (st.destroyed) return;
                    showMessage(data.message || '(no message)',
                                data.status === 'error' ? 'error' : 'success');

                    refs.results.innerHTML = '';
                    refs.summary.style.display = 'none';

                    if (Array.isArray(data.results) && data.results.length > 0) {
                        renderResults(data.results, queryId);
                        if (source === 'manual') refs.textarea.value = '';
                        return;
                    }

                    if (data.status === 'success' || data.status === 'partial') {
                        const columnMeta = (data.data && data.data.columnMeta) || [];
                        const rows = (data.data && data.data.rows) || [];
                        const columnNames = columnMeta.map(c => c.name);
                        if (rows.length > 0) {
                            refs.results.innerHTML = '<h3 style="margin:0 0 6px;font-size:13px;color:#333;">Query Results</h3>' + buildTable(columnNames, rows);
                            setStatus('success', { rows: rows.length, queryId });
                        } else if (data.data && data.data.affectedRows !== undefined) {
                            refs.results.innerHTML = `<p style="font-size:13px;">Query executed. Affected rows: ${data.data.affectedRows}</p>`;
                            setStatus('success', { rows: 0, affected: data.data.affectedRows, queryId });
                        } else {
                            refs.results.innerHTML = '<p style="font-size:13px;">Query executed, no results returned.</p>';
                            setStatus('empty', { queryId });
                        }
                    } else {
                        setStatus('error', { message: data.message || 'query failed', queryId });
                    }

                    if (source === 'manual') refs.textarea.value = '';
                })
                .catch(err => {
                    if (st.destroyed) return;
                    showMessage('Error executing query: ' + err.message, 'error');
                    refs.results.innerHTML = '';
                    refs.summary.style.display = 'none';
                    setStatus('error', { message: err.message || 'network error', queryId });
                })
                .finally(() => {
                    if (st.destroyed) return;
                    st.isExecuting = false;
                    refs.execBtn.disabled = false;
                    refs.execBtn.textContent = 'Execute Query';
                });
        }

        // ───── Event wiring ─────
        refs.execBtn.addEventListener('click', () => executeQuery({ source: 'manual' }));

        refs.textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                executeQuery({ source: 'manual' });
            } else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                executeQuery({ source: 'manual' });
            }
        });

        refs.tableSel.addEventListener('change', (e) => {
            const sel = e.target.value;
            st.cachedColumns = [];
            loadColumns(sel);
            if (st.columnPollInterval) { clearInterval(st.columnPollInterval); st.columnPollInterval = null; }
            if (sel) st.columnPollInterval = setInterval(() => loadColumns(sel), 5000);
            if (sel && sel !== st.lastExecutedTable) autoExecuteTableQuery(sel);
        });

        refs.minBtn.addEventListener('click', () => {
            el.classList.toggle('minimized');
            refs.minBtn.textContent = el.classList.contains('minimized') ? 'Restore' : 'Minimize';
        });

        refs.closeBtn.addEventListener('click', () => {
            WindowManager.closeWindow(id);
        });

        // ───── Init ─────
        setStatus('idle');
        // Seed with current global cache if available
        const initialTables = WindowManager.getGlobalTables();
        if (initialTables && initialTables.length) {
            onTablesUpdate(initialTables);
        }

        // ───── Destroy ─────
        function destroy() {
            st.destroyed = true;
            if (st.columnPollInterval) { clearInterval(st.columnPollInterval); st.columnPollInterval = null; }
            if (el.parentNode) el.parentNode.removeChild(el);
        }

        return {
            id,
            destroy,
            onTablesUpdate,
            getState: () => st,
            getRefs: () => refs,
        };
    }

    /* =====================================================================
     *  BOOTSTRAP
     * ===================================================================== */
    document.addEventListener('DOMContentLoaded', () => {
        // Create the first window
        WindowManager.createWindow();

        // Wire floating button
        document.getElementById('new-window-btn').addEventListener('click', () => {
            const ctrl = WindowManager.createWindow();
            if (ctrl) {
                // Bring new window into view
                const refs = ctrl.getRefs();
                refs.root.scrollIntoView({ behavior: 'smooth', block: 'center' });
                refs.textarea.focus();
            }
        });

        // Start global table poll — broadcasts to every window
        startGlobalTablePoll();
    });
    </script>
</body>
</html>