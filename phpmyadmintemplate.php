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
            min-width: 0; /* critical: allows grid child to shrink below content size */
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
        button:hover {
            background-color: #0056b3;
        }
        button:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .hint {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }

        /* ─────────────────────────────────────────────────────────
           SQL QUERY DISPLAY BLOCK (shows what ran)
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
           TABLE CONTAINER — horizontal scroll wrapper
           ───────────────────────────────────────────────────────── */
        .table-scroll-container {
            width: 100%;
            overflow-x: auto;              /* horizontal scroll if table too wide */
            overflow-y: auto;
            max-height: 70vh;              /* vertical scroll if too tall */
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            margin-top: 15px;
            -webkit-overflow-scrolling: touch;
        }

        table {
            border-collapse: collapse;
            background-color: #fff;
            width: max-content;            /* let table grow as wide as needed */
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
            position: sticky;              /* keep header visible while scrolling */
            top: 0;
            z-index: 2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        /* ─────────────────────────────────────────────────────────
           PER-CELL SCROLL — long content scrolls inside the cell
           ───────────────────────────────────────────────────────── */
        td .cell-content,
        th .cell-content {
            max-width: 360px;              /* cap column width */
            max-height: 120px;             /* cap row height */
            overflow: auto;                /* scroll inside cell */
            white-space: pre-wrap;         /* preserve newlines, wrap long text */
            word-break: break-word;
            display: block;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 12px;
            line-height: 1.4;
            scrollbar-width: thin;
        }
        td .cell-content::-webkit-scrollbar,
        th .cell-content::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        td .cell-content::-webkit-scrollbar-thumb,
        th .cell-content::-webkit-scrollbar-thumb {
            background: #bbb;
            border-radius: 4px;
        }
        td .cell-content::-webkit-scrollbar-thumb:hover,
        th .cell-content::-webkit-scrollbar-thumb:hover {
            background: #888;
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
            .container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <h1>Database Query Interface</h1>

    <div id="message" class="success"></div>
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

            <!-- SQL Query display (what was run) -->
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
        let isInitialLoad = true;      // prevents auto-execute on the very first table load
        let lastExecutedTable = null;  // prevents re-executing the same table repeatedly

        // ─────────────────────────────────────────────────────────
        // Escape HTML safely
        // ─────────────────────────────────────────────────────────
        function escapeHtml(str) {
            if (str === null || str === undefined) return 'NULL';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        // ─────────────────────────────────────────────────────────
        // Display the SQL query that was run
        // ─────────────────────────────────────────────────────────
        function showSqlQuery(sql, source) {
            const display = document.getElementById('sql-query-display');
            const body = document.getElementById('sql-query-body');
            const badge = document.getElementById('sql-query-source');
            body.textContent = sql;
            badge.textContent = source || 'manual';
            display.style.display = 'block';
        }

        // ─────────────────────────────────────────────────────────
        // Build a <table> inside a horizontal-scroll container
        // ─────────────────────────────────────────────────────────
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

        // ─────────────────────────────────────────────────────────
        // Fetch and populate tables
        // ─────────────────────────────────────────────────────────
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
                        return;
                    }

                    const newTables = data.tables || [];
                    if (JSON.stringify(newTables.slice().sort()) !== JSON.stringify(cachedTables.slice().sort())) {
                        cachedTables = newTables;
                        const selectedTable = currentTable && newTables.includes(currentTable) ? currentTable : (newTables[0] || '');

                        // Rebuild options
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

                            // ── AUTO-EXECUTE the table's DESCRIBE ──
                            // Skip on the very first load (don't want an initial auto-run)
                            // Skip if it's the same table we already executed
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

                    // After first successful load, allow auto-execute
                    isInitialLoad = false;
                })
                .catch(error => {
                    const messageDiv = document.getElementById('message');
                    messageDiv.className = 'error';
                    messageDiv.textContent = 'Error fetching tables: ' + error.message;
                });
        }

        // ─────────────────────────────────────────────────────────
        // Fetch and populate columns
        // ─────────────────────────────────────────────────────────
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
                    document.getElementById('column-select').innerHTML = '';
                });
        }

        // ─────────────────────────────────────────────────────────
        // AUTO-EXECUTE: DESCRIBE {table}
        // ─────────────────────────────────────────────────────────
        function autoExecuteTableQuery(tableName) {
            if (!tableName) return;
            // Sanitize table name for SQL (defensive — comes from server)
            const safeTable = String(tableName).replace(/[^a-zA-Z0-9_]/g, '');
            if (!safeTable) return;

            // ⬇️ CHANGED: DESCRIBE instead of SELECT * LIMIT 500
            const sql = `DESCRIBE \`${safeTable}\``;
            // Populate the textarea so it's visible & editable
            document.getElementById('sql-query').value = sql;
            // Run it, marking source as "auto: table select"
            executeQuery({ sql: sql, source: 'auto: table select' });
        }

        // ─────────────────────────────────────────────────────────
        // Execute SQL query (main entry point)
        //   options.sql    → optional SQL to run (bypasses textarea)
        //   options.source → badge label: 'manual' | 'auto: table select'
        // ─────────────────────────────────────────────────────────
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
                resultDiv.innerHTML = '';
                columnDataDiv.innerHTML = '';
                return;
            }

            // Show the SQL command being executed
            showSqlQuery(sqlQuery, source);

            isExecuting = true;
            executeBtn.disabled = true;
            executeBtn.textContent = 'Executing...';

            // Remember which table we auto-ran to avoid repeat loops
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
                    resultDiv.innerHTML = '';
                    columnDataDiv.innerHTML = '';

                    if (data.status === 'success') {
                        // Only clear the textarea for manual runs
                        if (source === 'manual') {
                            queryInput.value = '';
                        }

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
                        } else if (data.data && data.data.affectedRows !== undefined) {
                            const queryType = (sqlQuery.match(/^\s*(UPDATE|INSERT|ALTER|DELETE|CREATE|DROP|TRUNCATE)/i)?.[1] || 'Query').toUpperCase();
                            resultDiv.innerHTML = `<p>${queryType} executed successfully. Affected rows: ${data.data.affectedRows}</p>`;
                        } else {
                            resultDiv.innerHTML = '<p>Query executed successfully, but no results returned.</p>';
                        }
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
                    resultDiv.innerHTML = '';
                    columnDataDiv.innerHTML = '';
                })
                .finally(() => {
                    isExecuting = false;
                    executeBtn.disabled = false;
                    executeBtn.textContent = 'Execute Query';
                });
        }

        // ─────────────────────────────────────────────────────────
        // Initialize
        // ─────────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', () => {
            // Load tables WITHOUT auto-executing on first load
            isInitialLoad = true;
            loadTables();
            tablePollInterval = setInterval(loadTables, 5000);

            // ── Table select change → auto-execute DESCRIBE {table} ──
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

                // Auto-execute the DESCRIBE for the newly picked table
                if (selectedTable && selectedTable !== lastExecutedTable) {
                    autoExecuteTableQuery(selectedTable);
                }
            });

            // ── Enter key executes query; Shift+Enter inserts newline ──
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