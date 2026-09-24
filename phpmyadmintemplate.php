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
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 15px;
            min-width: 0;
        }
        h1 {
            font-size: 24px;
            color: #333;
            margin: 0 0 20px;
            text-align: center;
        }
        label {
            font-weight: bold;
            color: #444;
            margin-bottom: 5px;
            display: block;
        }
        select, textarea, button {
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
        }
        select:focus, textarea:focus, button:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
        }
        textarea {
            height: 120px;
            resize: vertical;
            font-family: 'Consolas', 'Monaco', monospace;
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.2s;
        }
        button:hover { background-color: #0056b3; }
        button:disabled { background-color: #6c757d; cursor: not-allowed; }
        .hint {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }

        /* ─────────────────────────────────────────────────────────
           EXEC STATUS BANNER — the machine-readable state element
           ───────────────────────────────────────────────────────── */
        #exec-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid transparent;
            transition: background-color 0.15s, border-color 0.15s, color 0.15s;
        }
        #exec-status[data-state="idle"] {
            background: #eef1f4; border-color: #d3d8dd; color: #4a5159;
        }
        #exec-status[data-state="loading"] {
            background: #e7f1ff; border-color: #b6d4fe; color: #084298;
        }
        #exec-status[data-state="success"] {
            background: #d4edda; border-color: #c3e6cb; color: #155724;
        }
        #exec-status[data-state="empty"] {
            background: #fff3cd; border-color: #ffe69c; color: #664d03;
        }
        #exec-status[data-state="error"] {
            background: #f8d7da; border-color: #f5c6cb; color: #721c24;
        }
        #exec-status .status-icon {
            width: 18px; height: 18px;
            display: inline-block;
            text-align: center;
            line-height: 18px;
            flex-shrink: 0;
        }
        #exec-status[data-state="loading"] .status-icon::before {
            content: "";
            display: inline-block;
            width: 14px; height: 14px;
            border: 2px solid #084298;
            border-top-color: transparent;
            border-radius: 50%;
            animation: exec-spin 0.7s linear infinite;
            vertical-align: middle;
        }
        @keyframes exec-spin { to { transform: rotate(360deg); } }
        #exec-status[data-state="success"] .status-icon::before { content: "✔"; }
        #exec-status[data-state="empty"]   .status-icon::before { content: "○"; }
        #exec-status[data-state="error"]   .status-icon::before { content: "✖"; }
        #exec-status[data-state="idle"]    .status-icon::before { content: "•"; }
        #exec-status .status-meta {
            margin-left: auto;
            font-weight: 400;
            font-size: 12px;
            opacity: 0.8;
            font-family: 'Consolas', 'Monaco', monospace;
        }

        /* ─────────────────────────────────────────────────────────
           SQL QUERY DISPLAY BLOCK
           ───────────────────────────────────────────────────────── */
        .sql-query-display {
            margin-top: 15px;
            border: 1px solid #cfe2ff;
            background-color: #f0f7ff;
            border-radius: 4px;
            overflow: hidden;
        }
        .sql-query-display .sql-query-header {
            background-color: #cfe2ff;
            color: #084298;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sql-query-display .sql-query-header .source-badge {
            background-color: #084298;
            color: #fff;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            text-transform: none;
            letter-spacing: 0;
            font-weight: normal;
        }
        .sql-query-display .sql-query-body {
            padding: 12px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 13px;
            color: #084298;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 200px;
            overflow: auto;
            line-height: 1.5;
            margin: 0;
        }

        /* ─────────────────────────────────────────────────────────
           TABLE CONTAINER
           ───────────────────────────────────────────────────────── */
        .table-scroll-container {
            width: 100%;
            overflow-x: auto;
            overflow-y: auto;
            max-height: 70vh;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            margin-top: 15px;
            -webkit-overflow-scrolling: touch;
        }
        table {
            border-collapse: collapse;
            background-color: #fff;
            width: max-content;
            min-width: 100%;
            table-layout: auto;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px 10px;
            text-align: left;
            font-size: 13px;
            vertical-align: top;
        }
        th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 2;
        }
        tr:nth-child(even) { background-color: #f9f9f9; }

        td .cell-content,
        th .cell-content {
            max-width: 360px;
            max-height: 120px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
            display: block;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 12px;
            line-height: 1.4;
            scrollbar-width: thin;
        }
        td .cell-content::-webkit-scrollbar,
        th .cell-content::-webkit-scrollbar {
            width: 8px; height: 8px;
        }
        td .cell-content::-webkit-scrollbar-thumb,
        th .cell-content::-webkit-scrollbar-thumb {
            background: #bbb; border-radius: 4px;
        }
        th .cell-content {
            background: transparent;
            max-height: none;
            overflow: visible;
        }

        .error {
            color: #721c24;
            padding: 10px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .success {
            color: #155724;
            padding: 10px;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .query-result, .column-data {
            margin-top: 15px;
            min-width: 0;
        }
        .query-result h3,
        .column-data h3 {
            margin: 10px 0 5px;
            font-size: 15px;
            color: #333;
        }
        @media (max-width: 768px) {
            .container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <h1>Database Query Interface</h1>

    <!-- ─────────────────────────────────────────────────────────
         MACHINE-READABLE EXEC STATUS
         State machine: idle → loading → success | empty | error
         The scraper polls #exec-status[data-state], NOT the table.
         ───────────────────────────────────────────────────────── -->
    <div id="exec-status"
         data-state="idle"
         data-rows="0"
         data-affected="0"
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
            <label for="sql-query">SQL Query</label>
            <textarea id="sql-query" placeholder="Enter your SQL query (e.g., SELECT * FROM users)&#10;&#10;Press Enter to execute, Shift+Enter for new line."></textarea>
            <div class="hint">Press <b>Enter</b> to execute • <b>Shift+Enter</b> for new line</div>
            <button id="execute-btn" onclick="executeQuery()">Execute Query</button>

            <div id="sql-query-display" class="sql-query-display" style="display:none;">
                <div class="sql-query-header">
                    <span>SQL Query</span>
                    <span id="sql-query-source" class="source-badge">manual</span>
                </div>
                <pre id="sql-query-body" class="sql-query-body"></pre>
            </div>

            <div id="query-result" class="query-result"></div>
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

        // ─────────────────────────────────────────────────────────
        // EXEC STATUS STATE MACHINE
        // Every transition here is observable by the scraper.
        // ─────────────────────────────────────────────────────────
        function setExecStatus(state, opts = {}) {
            const el = document.getElementById('exec-status');
            if (!el) return;

            const label = el.querySelector('.status-label');
            const meta  = el.querySelector('.status-meta');

            el.dataset.state = state;

            if (opts.rows !== undefined)     el.dataset.rows     = String(opts.rows);
            if (opts.affected !== undefined) el.dataset.affected = String(opts.affected);
            if (opts.queryId !== undefined)  el.dataset.queryId  = String(opts.queryId);

            switch (state) {
                case 'idle':
                    label.textContent = 'Waiting…';
                    meta.textContent  = '';
                    break;
                case 'loading':
                    label.textContent = 'Loading… running query';
                    meta.textContent  = opts.queryId ? `#${opts.queryId}` : '';
                    break;
                case 'success':
                    label.textContent = 'Results ready';
                    meta.textContent  = `${opts.rows ?? 0} row(s)`;
                    break;
                case 'empty':
                    label.textContent = 'Query completed — 0 rows';
                    meta.textContent  = opts.queryId ? `#${opts.queryId}` : '';
                    break;
                case 'error':
                    label.textContent = 'Error: ' + (opts.message || 'unknown');
                    meta.textContent  = opts.queryId ? `#${opts.queryId}` : '';
                    break;
                default:
                    label.textContent = state;
            }
        }

        // Expose for manual override / debugging
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
            const display = document.getElementById('sql-query-display');
            const body    = document.getElementById('sql-query-body');
            const badge   = document.getElementById('sql-query-source');
            body.textContent = sql;
            badge.textContent = source || 'manual';
            display.style.display = 'block';
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

        function loadTables() {
            fetch('phpmyadmin_tablesfetch.php')
                .then(response => response.json())
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
                        const selectedTable = currentTable && newTables.includes(currentTable) ? currentTable : (newTables[0] || '');

                        tableSelect.innerHTML = '';
                        newTables.forEach(table => {
                            const option = document.createElement('option');
                            option.value = table;
                            option.textContent = table;
                            if (table === selectedTable) option.selected = true;
                            tableSelect.appendChild(option);
                        });

                        if (selectedTable) {
                            loadColumns(selectedTable);
                            if (!isInitialLoad && selectedTable !== lastExecutedTable) {
                                autoExecuteTableQuery(selectedTable);
                            }
                        } else {
                            document.getElementById('column-select').innerHTML = '';
                            if (columnPollInterval) {
                                clearInterval(columnPollInterval);
                                columnPollInterval = null;
                            }
                        }

                        if (!messageDiv.textContent || newTables.length === 0) {
                            messageDiv.className = 'success';
                            messageDiv.textContent = newTables.length > 0
                                ? 'Tables retrieved successfully'
                                : 'No tables found in the database.';
                        }
                    }

                    isInitialLoad = false;
                })
                .catch(error => {
                    const messageDiv = document.getElementById('message');
                    messageDiv.className = 'error';
                    messageDiv.textContent = 'Error fetching tables: ' + error.message;
                    messageDiv.style.display = 'block';
                });
        }

        function loadColumns(table) {
            if (!table) return;

            fetch(`phpmyadmin_tablesfetch.php?table=${encodeURIComponent(table)}`)
                .then(response => response.json())
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

                    const newColumns = data.columns.map(col => col.Field);
                    if (JSON.stringify(newColumns.slice().sort()) !== JSON.stringify(cachedColumns.slice().sort())) {
                        cachedColumns = newColumns;
                        const selectedColumn = currentColumn && newColumns.includes(currentColumn)
                            ? currentColumn
                            : (newColumns[0] || '');

                        columnSelect.innerHTML = '';
                        data.columns.forEach(column => {
                            const option = document.createElement('option');
                            option.value = column.Field;
                            option.textContent = `${column.Field} (${column.Type})`;
                            if (column.Field === selectedColumn) option.selected = true;
                            columnSelect.appendChild(option);
                        });

                        if (!currentColumn || selectedColumn) {
                            messageDiv.className = 'success';
                            messageDiv.textContent = data.message;
                        }
                    }
                })
                .catch(error => {
                    const messageDiv = document.getElementById('message');
                    messageDiv.className = 'error';
                    messageDiv.textContent = 'Error fetching columns: ' + error.message;
                    messageDiv.style.display = 'block';
                    document.getElementById('column-select').innerHTML = '';
                });
        }

        function autoExecuteTableQuery(tableName) {
            if (!tableName) return;
            const safeTable = String(tableName).replace(/[^a-zA-Z0-9_]/g, '');
            if (!safeTable) return;

            const sql = `DESCRIBE \`${safeTable}\``;
            document.getElementById('sql-query').value = sql;
            executeQuery({ sql: sql, source: 'auto: table select' });
        }

        function executeQuery(options = {}) {
            if (isExecuting) return;

            const useOverride = typeof options.sql === 'string' && options.sql.length > 0;
            const sqlQuery = useOverride
                ? options.sql.trim()
                : document.getElementById('sql-query').value.trim();
            const source = options.source || 'manual';

            const messageDiv = document.getElementById('message');
            const resultDiv = document.getElementById('query-result');
            const columnDataDiv = document.getElementById('column-data');
            const queryInput = document.getElementById('sql-query');
            const executeBtn = document.getElementById('execute-btn');

            if (!sqlQuery) {
                messageDiv.className = 'error';
                messageDiv.textContent = 'Please enter an SQL query.';
                messageDiv.style.display = 'block';
                resultDiv.innerHTML = '';
                columnDataDiv.innerHTML = '';
                setExecStatus('error', { message: 'empty query' });
                return;
            }

            const queryId = ++_lastQueryId;

            showSqlQuery(sqlQuery, source);

            // ── SIGNAL: LOADING ──────────────────────────────────
            setExecStatus('loading', { queryId: queryId });
            resultDiv.innerHTML = '';
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
                .then(response => response.json())
                .then(data => {
                    messageDiv.className = data.status === 'success' ? 'success' : 'error';
                    messageDiv.textContent = data.message;
                    messageDiv.style.display = 'block';
                    resultDiv.innerHTML = '';
                    columnDataDiv.innerHTML = '';

                    if (data.status === 'success') {
                        if (source === 'manual') queryInput.value = '';

                        const columnMeta = (data.data && data.data.columnMeta) || [];
                        const rows = (data.data && data.data.rows) || [];
                        const columnNames = columnMeta.map(c => c.name);

                        if (rows.length > 0) {
                            const isSingleColumnSelect =
                                /^\s*SELECT\s+[a-zA-Z0-9_]+\s+FROM\s+/i.test(sqlQuery) &&
                                columnNames.length === 1;

                            if (isSingleColumnSelect) {
                                const colName = columnNames[0];
                                let colHtml = `<h3>Data for Column: ${escapeHtml(colName)}</h3>`;
                                colHtml += '<div class="table-scroll-container"><table><thead><tr>';
                                colHtml += `<th><span class="cell-content">${escapeHtml(colName)}</span></th>`;
                                colHtml += '</tr></thead><tbody>';
                                rows.forEach(row => {
                                    const val = row[colName];
                                    const display = (val === null || val === undefined) ? 'NULL' : val;
                                    colHtml += `<tr><td><span class="cell-content">${escapeHtml(display)}</span></td></tr>`;
                                });
                                colHtml += '</tbody></table></div>';
                                columnDataDiv.innerHTML = colHtml;

                                resultDiv.innerHTML = '<h3>Query Results</h3>' + buildTable(columnNames, rows);
                            } else {
                                resultDiv.innerHTML = '<h3>Query Results</h3>' + buildTable(columnNames, rows);
                            }

                            // ── SIGNAL: SUCCESS ───────────────────
                            setExecStatus('success', {
                                rows: rows.length,
                                queryId: queryId
                            });
                        } else if (data.data && data.data.affectedRows !== undefined) {
                            const queryType = (sqlQuery.match(/^\s*(UPDATE|INSERT|ALTER|DELETE|CREATE|DROP|TRUNCATE)/i)?.[1] || 'Query').toUpperCase();
                            resultDiv.innerHTML = `<p>${queryType} executed successfully. Affected rows: ${data.data.affectedRows}</p>`;

                            // ── SIGNAL: SUCCESS (non-read) ────────
                            setExecStatus('success', {
                                rows: 0,
                                affected: data.data.affectedRows,
                                queryId: queryId
                            });
                        } else {
                            resultDiv.innerHTML = '<p>Query executed successfully, but no results returned.</p>';

                            // ── SIGNAL: EMPTY ─────────────────────
                            setExecStatus('empty', { rows: 0, queryId: queryId });
                        }
                    } else {
                        // ── SIGNAL: ERROR ─────────────────────────
                        setExecStatus('error', {
                            message: data.message || 'query failed',
                            queryId: queryId
                        });
                    }
                })
                .then(() => {
                    loadTables();
                    const selectedTable = document.getElementById('table-select').value;
                    if (selectedTable) loadColumns(selectedTable);
                })
                .catch(error => {
                    messageDiv.className = 'error';
                    messageDiv.textContent = 'Error executing query: ' + error.message;
                    messageDiv.style.display = 'block';
                    resultDiv.innerHTML = '';
                    columnDataDiv.innerHTML = '';
                    setExecStatus('error', {
                        message: error.message || 'network error',
                        queryId: queryId
                    });
                })
                .finally(() => {
                    isExecuting = false;
                    executeBtn.disabled = false;
                    executeBtn.textContent = 'Execute Query';
                });
        }

        document.addEventListener('DOMContentLoaded', () => {
            setExecStatus('idle');
            isInitialLoad = true;
            loadTables();
            tablePollInterval = setInterval(loadTables, 5000);

            document.getElementById('table-select').addEventListener('change', (e) => {
                const selectedTable = e.target.value;
                cachedColumns = [];
                loadColumns(selectedTable);

                if (columnPollInterval) {
                    clearInterval(columnPollInterval);
                    columnPollInterval = null;
                }
                if (selectedTable) {
                    columnPollInterval = setInterval(() => loadColumns(selectedTable), 5000);
                }

                if (selectedTable && selectedTable !== lastExecutedTable) {
                    autoExecuteTableQuery(selectedTable);
                }
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