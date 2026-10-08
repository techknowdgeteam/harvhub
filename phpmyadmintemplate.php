<?php
// phpmyadmintemplate.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>DB</text></svg>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Query Interface</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            display: grid;
            grid-template-columns: 1fr 2.5fr;
            gap: 20px;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .sidebar { display: flex; flex-direction: column; gap: 15px; }
        .main-content { display: flex; flex-direction: column; gap: 15px; min-width: 0; }
        h1 { font-size: 24px; color: #333; margin: 0 0 20px; text-align: center; }
        label { font-weight: bold; color: #444; margin-bottom: 5px; display: block; }
        select, textarea, button {
            padding: 10px; font-size: 14px;
            border: 1px solid #ccc; border-radius: 4px;
            width: 100%; box-sizing: border-box;
        }
        select:focus, textarea:focus, button:focus {
            outline: none; border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }
        textarea {
            height: 180px; resize: vertical;
            font-family: 'Consolas', 'Monaco', monospace;
        }
        button {
            background-color: #007bff; color: white;
            border: none; cursor: pointer;
            font-weight: bold; transition: background-color 0.2s;
        }
        button:hover { background-color: #0056b3; }
        button:disabled { background-color: #6c757d; cursor: not-allowed; }
        .hint { font-size: 12px; color: #666; margin-top: 4px; }

        /* ───── EXEC STATUS BANNER ───── */
        #exec-status {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; border-radius: 6px;
            font-size: 13px; font-weight: 600;
            border: 1px solid transparent;
            transition: background-color 0.15s, border-color 0.15s, color 0.15s;
        }
        #exec-status[data-state="idle"]    { background: #eef1f4; border-color: #d3d8dd; color: #4a5159; }
        #exec-status[data-state="loading"] { background: #e7f1ff; border-color: #b6d4fe; color: #084298; }
        #exec-status[data-state="success"] { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        #exec-status[data-state="partial"] { background: #fff3cd; border-color: #ffe69c; color: #664d03; }
        #exec-status[data-state="empty"]   { background: #fff3cd; border-color: #ffe69c; color: #664d03; }
        #exec-status[data-state="error"]   { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        #exec-status .status-icon {
            width: 18px; height: 18px; display: inline-block;
            text-align: center; line-height: 18px; flex-shrink: 0;
        }
        #exec-status[data-state="loading"] .status-icon::before {
            content: ""; display: inline-block;
            width: 14px; height: 14px;
            border: 2px solid #084298; border-top-color: transparent;
            border-radius: 50%; animation: exec-spin 0.7s linear infinite;
            vertical-align: middle;
        }
        @keyframes exec-spin { to { transform: rotate(360deg); } }
        #exec-status[data-state="success"] .status-icon::before { content: "✔"; }
        #exec-status[data-state="partial"] .status-icon::before { content: "◐"; }
        #exec-status[data-state="empty"]   .status-icon::before { content: "○"; }
        #exec-status[data-state="error"]   .status-icon::before { content: "✖"; }
        #exec-status[data-state="idle"]    .status-icon::before { content: "•"; }
        #exec-status .status-meta {
            margin-left: auto; font-weight: 400; font-size: 12px;
            opacity: 0.8; font-family: 'Consolas', 'Monaco', monospace;
        }

        /* ───── SQL DISPLAY ───── */
        .sql-query-display {
            margin-top: 15px; border: 1px solid #cfe2ff;
            background-color: #f0f7ff; border-radius: 4px; overflow: hidden;
        }
        .sql-query-display .sql-query-header {
            background-color: #cfe2ff; color: #084298;
            padding: 8px 12px; font-size: 12px; font-weight: bold;
            text-transform: uppercase; letter-spacing: 0.5px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .sql-query-display .sql-query-header .source-badge {
            background-color: #084298; color: #fff;
            padding: 2px 8px; border-radius: 10px;
            font-size: 10px; text-transform: none;
            letter-spacing: 0; font-weight: normal;
        }
        .sql-query-display .sql-query-body {
            padding: 12px; font-family: 'Consolas', 'Monaco', monospace;
            font-size: 13px; color: #084298;
            white-space: pre-wrap; word-break: break-word;
            max-height: 200px; overflow: auto;
            line-height: 1.5; margin: 0;
        }

        /* ───── SUMMARY STRIP ───── */
        .results-summary {
            display: flex; gap: 10px; flex-wrap: wrap;
            padding: 12px 14px; margin-top: 15px;
            background: #f8f9fa; border: 1px solid #e0e0e0;
            border-radius: 8px; font-size: 13px;
        }
        .results-summary .pill {
            padding: 4px 12px; border-radius: 20px;
            font-weight: 700; font-size: 12px;
        }
        .pill.total   { background: #e7f1ff; color: #084298; }
        .pill.ok      { background: #d4edda; color: #155724; }
        .pill.empty   { background: #fff3cd; color: #664d03; }
        .pill.failed  { background: #f8d7da; color: #721c24; }

        /* ───── PER-STATEMENT BLOCKS ───── */
        #query-result {
            display: flex; flex-direction: column; gap: 14px;
            margin-top: 15px;
        }

        .stmt-block {
            border: 1px solid #ddd; border-radius: 8px;
            overflow: hidden; background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .stmt-block[data-status="success"] { border-left: 5px solid #28a745; }
        .stmt-block[data-status="empty"]   { border-left: 5px solid #ffc107; }
        .stmt-block[data-status="error"]   { border-left: 5px solid #dc3545; }
        .stmt-block[data-status="unknown"] { border-left: 5px solid #6c757d; }

        .stmt-header {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; background: #f8f9fa;
            border-bottom: 1px solid #eee;
            font-size: 13px; font-weight: 600;
            flex-wrap: wrap;
        }
        .stmt-header .stmt-index {
            background: #007bff; color: #fff;
            border-radius: 50%; width: 26px; height: 26px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; flex-shrink: 0;
        }
        .stmt-header .stmt-status-icon {
            font-size: 16px; flex-shrink: 0; font-weight: 700;
        }
        .stmt-block[data-status="success"] .stmt-status-icon { color: #28a745; }
        .stmt-block[data-status="empty"]   .stmt-status-icon { color: #ffc107; }
        .stmt-block[data-status="error"]   .stmt-status-icon { color: #dc3545; }

        .stmt-header .stmt-message {
            flex: 1; color: #333; font-weight: 500;
            font-size: 13px;
            word-break: break-word;
            min-width: 0;
        }
        .stmt-block[data-status="error"] .stmt-header .stmt-message { color: #721c24; }

        .stmt-header .stmt-meta {
            font-size: 11px; color: #666; font-weight: 400;
            font-family: 'Consolas', 'Monaco', monospace;
            background: #e9ecef; padding: 2px 8px;
            border-radius: 10px; flex-shrink: 0;
        }
        .stmt-block[data-status="success"] .stmt-header .stmt-meta {
            background: #d4edda; color: #155724;
        }
        .stmt-block[data-status="error"] .stmt-header .stmt-meta {
            background: #f8d7da; color: #721c24;
        }

        .stmt-sql {
            padding: 8px 14px; background: #f0f7ff;
            border-bottom: 1px solid #e0e8f0;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 12px; color: #084298;
            white-space: pre-wrap; word-break: break-word;
            max-height: 100px; overflow: auto;
        }

        .stmt-body { padding: 0; }
        .stmt-error-body {
            padding: 10px 14px;
            background: #f8d7da; color: #721c24;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 12px; white-space: pre-wrap; word-break: break-word;
        }
        .stmt-affected-body {
            padding: 10px 14px; background: #d4edda;
            color: #155724; font-size: 13px;
        }
        .stmt-empty-body {
            padding: 10px 14px; background: #fff3cd;
            color: #664d03; font-size: 13px;
        }

        /* ───── TABLE ───── */
        .table-scroll-container {
            width: 100%; overflow-x: auto; overflow-y: auto;
            max-height: 60vh; background: #fff;
            -webkit-overflow-scrolling: touch;
        }
        table {
            border-collapse: collapse; background-color: #fff;
            width: max-content; min-width: 100%; table-layout: auto;
        }
        th, td {
            border: 1px solid #ddd; padding: 8px 10px;
            text-align: left; font-size: 13px; vertical-align: top;
        }
        th {
            background-color: #f8f9fa; color: #333; font-weight: bold;
            position: sticky; top: 0; z-index: 2;
        }
        tr:nth-child(even) { background-color: #f9f9f9; }
        td .cell-content, th .cell-content {
            max-width: 360px; max-height: 120px;
            overflow: auto; white-space: pre-wrap;
            word-break: break-word; display: block;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 12px; line-height: 1.4;
            scrollbar-width: thin;
        }
        td .cell-content::-webkit-scrollbar,
        th .cell-content::-webkit-scrollbar { width: 8px; height: 8px; }
        td .cell-content::-webkit-scrollbar-thumb,
        th .cell-content::-webkit-scrollbar-thumb { background: #bbb; border-radius: 4px; }
        th .cell-content { background: transparent; max-height: none; overflow: visible; }

        .error {
            color: #721c24; padding: 10px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px; margin-bottom: 15px;
        }
        .success {
            color: #155724; padding: 10px;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px; margin-bottom: 15px;
        }

        .column-data { margin-top: 15px; min-width: 0; }
        .column-data h3 { margin: 10px 0 5px; font-size: 15px; color: #333; }

        @media (max-width: 768px) {
            .container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <h1>Database Query Interface</h1>

    <div id="exec-status"
         data-state="idle"
         data-rows="0"
         data-affected="0"
         data-statements="0"
         data-succeeded="0"
         data-failed="0"
         data-query-id=""
         role="status"
         aria-live="polite">
        <span class="status-icon" aria-hidden="true"></span>
        <span class="status-label">Waiting…</span>
        <span class="status-meta"></span>
    </div>

    <div id="message" class="success" style="display:none;"></div>

    <div class="container">
        <div class="sidebar">
            <div>
                <label for="table-select">Tables</label>
                <select id="table-select"></select>
            </div>
            <div>
                <label for="column-select">Columns</label>
                <select id="column-select"></select>
            </div>
        </div>
        <div class="main-content">
            <label for="sql-query">SQL Query (multiple statements allowed)</label>
            <textarea id="sql-query" placeholder="Enter your SQL queries here. Separate statements with ; or put each on its own line.&#10;&#10;Example:&#10;ALTER TABLE `a` ADD COLUMN `x` INT;&#10;ALTER TABLE `b` ADD COLUMN `y` INT;&#10;&#10;Press Enter to execute, Shift+Enter for new line."></textarea>
            <div class="hint">Press <b>Enter</b> to execute • <b>Shift+Enter</b> for new line • Each statement runs independently</div>
            <button id="execute-btn" onclick="executeQuery()">Execute Query</button>

            <div id="sql-query-display" class="sql-query-display" style="display:none;">
                <div class="sql-query-header">
                    <span>SQL Query</span>
                    <span id="sql-query-source" class="source-badge">manual</span>
                </div>
                <pre id="sql-query-body" class="sql-query-body"></pre>
            </div>

            <div id="results-summary" class="results-summary" style="display:none;"></div>
            <div id="query-result"></div>
            <div id="column-data" class="column-data"></div>
        </div>
    </div>

    <script>
        let tablePollInterval = null;
        let columnPollInterval = null;
        let cachedTables = [];
        let cachedColumns = [];
        let isExecuting = false;
        let isInitialLoad = true;
        let lastExecutedTable = null;
        let _lastQueryId = 0;

        // ───── STATUS ─────
        function setExecStatus(state, opts = {}) {
            const el = document.getElementById('exec-status');
            if (!el) return;
            const label = el.querySelector('.status-label');
            const meta  = el.querySelector('.status-meta');

            el.dataset.state = state;
            if (opts.rows !== undefined)        el.dataset.rows       = String(opts.rows);
            if (opts.affected !== undefined)    el.dataset.affected   = String(opts.affected);
            if (opts.statements !== undefined)  el.dataset.statements = String(opts.statements);
            if (opts.succeeded !== undefined)   el.dataset.succeeded  = String(opts.succeeded);
            if (opts.failed !== undefined)      el.dataset.failed     = String(opts.failed);
            if (opts.queryId !== undefined)     el.dataset.queryId    = String(opts.queryId);

            switch (state) {
                case 'idle':    label.textContent = 'Waiting…'; meta.textContent = ''; break;
                case 'loading': label.textContent = 'Loading…'; meta.textContent = opts.queryId ? `#${opts.queryId}` : ''; break;
                case 'success': label.textContent = 'All statements succeeded';
                                meta.textContent  = `${opts.succeeded ?? 0}/${opts.statements ?? 0} ok`;
                                break;
                case 'partial': label.textContent = 'Some statements failed';
                                meta.textContent  = `${opts.succeeded ?? 0}/${opts.statements ?? 0} ok · ${opts.failed ?? 0} failed`;
                                break;
                case 'empty':   label.textContent = 'Completed — 0 rows'; meta.textContent = ''; break;
                case 'error':   label.textContent = 'Error: ' + (opts.message || 'unknown'); meta.textContent = ''; break;
                default:        label.textContent = state;
            }
        }
        window.setExecStatus = setExecStatus;

        function escapeHtml(str) {
            if (str === null || str === undefined) return 'NULL';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function showSqlQuery(sql, source) {
            document.getElementById('sql-query-body').textContent = sql;
            document.getElementById('sql-query-source').textContent = source || 'manual';
            document.getElementById('sql-query-display').style.display = 'block';
        }

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

        // ───── RENDER ONE STATEMENT BLOCK ─────
        function renderStatementBlock(r, index) {
            const status  = r.status || 'unknown';
            const sql     = r.sql || '';
            const message = r.message || '';

            let icon = '•';
            if (status === 'success') icon = '✔';
            else if (status === 'empty') icon = '○';
            else if (status === 'error') icon = '✖';

            let meta = '';
            if (status === 'success' && r.isReadQuery) {
                meta = `${(r.rows || []).length} row(s)`;
            } else if (status === 'success' && !r.isReadQuery) {
                meta = `${r.affectedRows ?? 0} affected`;
            } else if (status === 'empty') {
                meta = '0 rows';
            } else if (status === 'error') {
                meta = 'failed';
            }

            let html = `<div class="stmt-block" data-status="${escapeHtml(status)}" data-index="${index}">`;
            html +=   '<div class="stmt-header">';
            html +=     `<span class="stmt-index">#${index + 1}</span>`;
            html +=     `<span class="stmt-status-icon">${icon}</span>`;
            html +=     `<span class="stmt-message">${escapeHtml(message)}</span>`;
            html +=     `<span class="stmt-meta">${escapeHtml(meta)}</span>`;
            html +=   '</div>';
            html +=   `<div class="stmt-sql">${escapeHtml(sql)}</div>`;
            html +=   '<div class="stmt-body">';

            if (status === 'error') {
                html += `<div class="stmt-error-body">${escapeHtml(r.message || 'Unknown error')}</div>`;
            } else if (status === 'empty') {
                html += '<div class="stmt-empty-body">Statement ran but returned 0 rows.</div>';
            } else if (status === 'success' && r.isReadQuery) {
                const columnNames = (r.columnMeta || []).map(c => c.name);
                const rows = r.rows || [];
                if (rows.length > 0 && columnNames.length > 0) {
                    html += buildTable(columnNames, rows);
                } else {
                    html += '<div class="stmt-empty-body">Statement ran but returned 0 rows.</div>';
                }
            } else if (status === 'success' && !r.isReadQuery) {
                html += `<div class="stmt-affected-body">Statement executed successfully. Affected rows: ${r.affectedRows ?? 0}</div>`;
            } else {
                html += `<div class="stmt-empty-body">No output for this statement.</div>`;
            }

            html +=   '</div>';
            html += '</div>';
            return html;
        }

        // ───── FRONTEND FALLBACK SPLITTER ─────
        // If backend somehow returns a legacy single-blob shape, we split client-side
        // so the user still sees per-statement blocks.
        function splitSqlClientSide(sql) {
            const parts = [];
            let cur = '';
            let inSingle = false, inDouble = false, inBacktick = false;
            for (let i = 0; i < sql.length; i++) {
                const ch = sql[i];
                if (ch === "'" && !inDouble && !inBacktick) inSingle = !inSingle;
                else if (ch === '"' && !inSingle && !inBacktick) inDouble = !inDouble;
                else if (ch === '`' && !inSingle && !inDouble) inBacktick = !inBacktick;

                if (ch === ';' && !inSingle && !inDouble && !inBacktick) {
                    const t = cur.trim();
                    if (t) parts.push(t);
                    cur = '';
                    continue;
                }
                cur += ch;
            }
            const t = cur.trim();
            if (t) parts.push(t);
            return parts;
        }

        // ───── LOAD TABLES ─────
        function loadTables() {
            fetch('phpmyadmin_tablesfetch.php')
                .then(r => r.json())
                .then(data => {
                    const messageDiv = document.getElementById('message');
                    const tableSelect = document.getElementById('table-select');
                    const currentTable = tableSelect.value;

                    if (data.status !== 'success') {
                        messageDiv.className = 'error';
                        messageDiv.textContent = data.message;
                        messageDiv.style.display = 'block';
                        return;
                    }

                    const newTables = data.tables || [];
                    if (JSON.stringify(newTables.slice().sort()) !== JSON.stringify(cachedTables.slice().sort())) {
                        cachedTables = newTables;
                        const selectedTable = currentTable && newTables.includes(currentTable)
                            ? currentTable
                            : (newTables[0] || '');

                        tableSelect.innerHTML = '';
                        newTables.forEach(t => {
                            const o = document.createElement('option');
                            o.value = t; o.textContent = t;
                            if (t === selectedTable) o.selected = true;
                            tableSelect.appendChild(o);
                        });

                        if (selectedTable) {
                            loadColumns(selectedTable);
                            if (!isInitialLoad && selectedTable !== lastExecutedTable) {
                                autoExecuteTableQuery(selectedTable);
                            }
                        } else {
                            document.getElementById('column-select').innerHTML = '';
                            if (columnPollInterval) { clearInterval(columnPollInterval); columnPollInterval = null; }
                        }

                        if (!messageDiv.textContent || newTables.length === 0) {
                            messageDiv.className = 'success';
                            messageDiv.textContent = newTables.length > 0 ? 'Tables retrieved.' : 'No tables found.';
                        }
                    }
                    isInitialLoad = false;
                })
                .catch(err => {
                    const m = document.getElementById('message');
                    m.className = 'error';
                    m.textContent = 'Error fetching tables: ' + err.message;
                    m.style.display = 'block';
                });
        }

        function loadColumns(table) {
            if (!table) return;
            fetch(`phpmyadmin_tablesfetch.php?table=${encodeURIComponent(table)}`)
                .then(r => r.json())
                .then(data => {
                    const messageDiv = document.getElementById('message');
                    const columnSelect = document.getElementById('column-select');
                    const currentColumn = columnSelect.value;

                    if (data.status !== 'success') {
                        messageDiv.className = 'error';
                        messageDiv.textContent = data.message;
                        messageDiv.style.display = 'block';
                        columnSelect.innerHTML = '';
                        return;
                    }

                    const newColumns = data.columns.map(c => c.Field);
                    if (JSON.stringify(newColumns.slice().sort()) !== JSON.stringify(cachedColumns.slice().sort())) {
                        cachedColumns = newColumns;
                        const selectedColumn = currentColumn && newColumns.includes(currentColumn)
                            ? currentColumn
                            : (newColumns[0] || '');

                        columnSelect.innerHTML = '';
                        data.columns.forEach(c => {
                            const o = document.createElement('option');
                            o.value = c.Field;
                            o.textContent = `${c.Field} (${c.Type})`;
                            if (c.Field === selectedColumn) o.selected = true;
                            columnSelect.appendChild(o);
                        });

                        if (!currentColumn || selectedColumn) {
                            messageDiv.className = 'success';
                            messageDiv.textContent = data.message;
                        }
                    }
                })
                .catch(err => {
                    const m = document.getElementById('message');
                    m.className = 'error';
                    m.textContent = 'Error fetching columns: ' + err.message;
                    m.style.display = 'block';
                    document.getElementById('column-select').innerHTML = '';
                });
        }

        function autoExecuteTableQuery(tableName) {
            if (!tableName) return;
            const safe = String(tableName).replace(/[^a-zA-Z0-9_]/g, '');
            if (!safe) return;
            const sql = `DESCRIBE \`${safe}\``;
            document.getElementById('sql-query').value = sql;
            executeQuery({ sql, source: 'auto: table select' });
        }

        // ───── EXECUTE ─────
        function executeQuery(options = {}) {
            if (isExecuting) return;

            const useOverride = typeof options.sql === 'string' && options.sql.length > 0;
            const sqlQuery = useOverride ? options.sql.trim() : document.getElementById('sql-query').value.trim();
            const source   = options.source || 'manual';

            const messageDiv   = document.getElementById('message');
            const resultDiv    = document.getElementById('query-result');
            const summaryDiv   = document.getElementById('results-summary');
            const columnDataDiv= document.getElementById('column-data');
            const queryInput   = document.getElementById('sql-query');
            const executeBtn   = document.getElementById('execute-btn');

            if (!sqlQuery) {
                messageDiv.className = 'error';
                messageDiv.textContent = 'Please enter an SQL query.';
                messageDiv.style.display = 'block';
                resultDiv.innerHTML = '';
                summaryDiv.style.display = 'none';
                columnDataDiv.innerHTML = '';
                setExecStatus('error', { message: 'empty query' });
                return;
            }

            const queryId = ++_lastQueryId;
            showSqlQuery(sqlQuery, source);

            setExecStatus('loading', { queryId });
            resultDiv.innerHTML = '';
            summaryDiv.style.display = 'none';
            summaryDiv.innerHTML = '';
            columnDataDiv.innerHTML = '';
            messageDiv.style.display = 'none';

            isExecuting = true;
            executeBtn.disabled = true;
            executeBtn.textContent = 'Executing...';

            if (source === 'auto: table select') {
                const m = sqlQuery.match(/(?:FROM|DESCRIBE|INTO|UPDATE|TABLE)\s+`?([a-zA-Z0-9_]+)`?/i);
                if (m) lastExecutedTable = m[1];
            }

            fetch('phpmyadmin_tablesfetch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `sql_query=${encodeURIComponent(sqlQuery)}`
            })
                .then(r => r.json())
                .then(data => {
                    messageDiv.className = (data.status === 'error') ? 'error' : 'success';
                    messageDiv.textContent = data.message || '(no message)';
                    messageDiv.style.display = 'block';

                    resultDiv.innerHTML = '';
                    summaryDiv.style.display = 'none';

                    // ─── PRIMARY PATH: backend gave us per-statement results ───
                    if (Array.isArray(data.results) && data.results.length > 0) {
                        renderResults(data.results, summaryDiv, resultDiv, queryId);
                        if (source === 'manual') queryInput.value = '';
                        return;
                    }

                    // ─── FALLBACK: backend gave legacy shape, split client-side ───
                    // This guarantees per-statement rendering even if the backend
                    // is an older version that just returns one blob.
                    const clientStmts = splitSqlClientSide(sqlQuery);
                    if (clientStmts.length > 1) {
                        // We only have one blob of result for many statements.
                        // Show a single "aggregate" result AND per-statement SQL so
                        // the user can see each statement it tried.
                        const aggResults = clientStmts.map((s, i) => ({
                            index: i,
                            sql: s,
                            status: (i === 0 && data.data && data.data.rows && data.data.rows.length > 0) ? 'success' : 'unknown',
                            message: (i === 0)
                                ? (data.message || 'Result from aggregate call.')
                                : 'Result not separated by server — see top message.',
                            rows: (i === 0 && data.data && data.data.rows) ? data.data.rows : [],
                            columnMeta: (i === 0 && data.data && data.data.columnMeta) ? data.data.columnMeta : [],
                            affectedRows: (i === 0 && data.data && data.data.affectedRows) ? data.data.affectedRows : 0,
                            isReadQuery: (i === 0 && data.data && data.data.rows) ? true : false,
                        }));
                        renderResults(aggResults, summaryDiv, resultDiv, queryId);
                        if (source === 'manual') queryInput.value = '';
                        return;
                    }

                    // ─── SINGLE STATEMENT legacy handling ───
                    if (data.status === 'success' || data.status === 'partial') {
                        const columnMeta = (data.data && data.data.columnMeta) || [];
                        const rows = (data.data && data.data.rows) || [];
                        const columnNames = columnMeta.map(c => c.name);

                        if (rows.length > 0) {
                            resultDiv.innerHTML = '<h3>Query Results</h3>' + buildTable(columnNames, rows);
                            setExecStatus('success', { rows: rows.length, queryId });
                        } else if (data.data && data.data.affectedRows !== undefined) {
                            resultDiv.innerHTML = `<p>Query executed. Affected rows: ${data.data.affectedRows}</p>`;
                            setExecStatus('success', { rows: 0, affected: data.data.affectedRows, queryId });
                        } else {
                            resultDiv.innerHTML = '<p>Query executed, no results returned.</p>';
                            setExecStatus('empty', { queryId });
                        }
                    } else {
                        setExecStatus('error', { message: data.message || 'query failed', queryId });
                    }

                    if (source === 'manual') queryInput.value = '';
                })
                .then(() => {
                    loadTables();
                    const sel = document.getElementById('table-select').value;
                    if (sel) loadColumns(sel);
                })
                .catch(err => {
                    messageDiv.className = 'error';
                    messageDiv.textContent = 'Error executing query: ' + err.message;
                    messageDiv.style.display = 'block';
                    resultDiv.innerHTML = '';
                    summaryDiv.style.display = 'none';
                    columnDataDiv.innerHTML = '';
                    setExecStatus('error', { message: err.message || 'network error', queryId });
                })
                .finally(() => {
                    isExecuting = false;
                    executeBtn.disabled = false;
                    executeBtn.textContent = 'Execute Query';
                });
        }

        // ───── RENDER ALL RESULTS ─────
        function renderResults(results, summaryDiv, resultDiv, queryId) {
            // summary
            let succeeded = 0, failed = 0, empty = 0, totalRows = 0;
            results.forEach(r => {
                if (r.status === 'success') {
                    succeeded++;
                    if (r.isReadQuery) totalRows += (r.rows || []).length;
                } else if (r.status === 'empty') {
                    empty++;
                } else if (r.status === 'error') {
                    failed++;
                }
            });

            let sHtml = '';
            sHtml += `<span class="pill total">Total: ${results.length}</span>`;
            sHtml += `<span class="pill ok">Succeeded: ${succeeded}</span>`;
            if (empty > 0)   sHtml += `<span class="pill empty">Empty: ${empty}</span>`;
            if (failed > 0)  sHtml += `<span class="pill failed">Failed: ${failed}</span>`;
            summaryDiv.innerHTML = sHtml;
            summaryDiv.style.display = 'flex';

            // blocks
            let html = '';
            results.forEach((r, i) => { html += renderStatementBlock(r, i); });
            resultDiv.innerHTML = html;

            // overall status
            if (failed === 0 && succeeded > 0) {
                setExecStatus('success', {
                    statements: results.length,
                    succeeded: succeeded,
                    failed: 0,
                    rows: totalRows,
                    queryId
                });
            } else if (failed > 0 && (succeeded + empty) > 0) {
                setExecStatus('partial', {
                    statements: results.length,
                    succeeded: succeeded,
                    failed: failed,
                    rows: totalRows,
                    queryId
                });
            } else if (failed > 0) {
                setExecStatus('error', {
                    message: failed + ' statement(s) failed',
                    queryId
                });
            } else {
                setExecStatus('empty', { queryId });
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            setExecStatus('idle');
            isInitialLoad = true;
            loadTables();
            tablePollInterval = setInterval(loadTables, 5000);

            document.getElementById('table-select').addEventListener('change', (e) => {
                const sel = e.target.value;
                cachedColumns = [];
                loadColumns(sel);
                if (columnPollInterval) { clearInterval(columnPollInterval); columnPollInterval = null; }
                if (sel) columnPollInterval = setInterval(() => loadColumns(sel), 5000);
                if (sel && sel !== lastExecutedTable) autoExecuteTableQuery(sel);
            });

            const textarea = document.getElementById('sql-query');
            textarea.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey) {
                    e.preventDefault();
                    executeQuery({ source: 'manual' });
                } else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    executeQuery({ source: 'manual' });
                }
            });
        });

        window.addEventListener('unload', () => {
            if (tablePollInterval) clearInterval(tablePollInterval);
            if (columnPollInterval) clearInterval(columnPollInterval);
        });
    </script>
</body>
</html>