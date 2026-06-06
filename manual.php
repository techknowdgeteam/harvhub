<?php
// manual.php - Manual Content Management System
// This file is included in serveraccount.php when view=manual
?>

<style>
    /* Manual Management Styles */
    .manual-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px 0;
    }
    
    .manual-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .manual-header h2 {
        margin: 0;
        font-size: 24px;
    }
    
    .add-manual-btn {
        background: #27ae60;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }
    
    .add-manual-btn:hover {
        background: #219a52;
        transform: translateY(-1px);
    }
    
    .refresh-manual-btn {
        background: #3498db;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }
    
    .refresh-manual-btn:hover {
        background: #2980b9;
        transform: translateY(-1px);
    }
    
    .manual-entries {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .manual-card {
        background: var(--bg-secondary);
        border-radius: 5px;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .manual-card:hover {
        border-color: var(--accent-color);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .manual-card-header {
        background: var(--bg-primary);
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        cursor: pointer;
    }
    
    .manual-card-header:hover {
        background: var(--bg-hover);
    }
    
    .manual-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--accent-color);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .manual-title .title-text {
        word-break: break-word;
    }
    
    .manual-badge {
        background: var(--accent-color);
        color: white;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: normal;
    }
    
    .manual-actions {
        display: flex;
        gap: 8px;
    }
    
    .edit-manual-btn {
        background: #f39c12;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.2s ease;
    }
    
    .edit-manual-btn:hover {
        background: #e67e22;
    }
    
    .delete-manual-btn {
        background: #e74c3c;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.2s ease;
    }
    
    .delete-manual-btn:hover {
        background: #c0392b;
    }
    
    .manual-card-body {
        padding: 20px;
        display: none;
    }
    
    .manual-card.expanded .manual-card-body {
        display: block;
    }
    
    .manual-description {
        background: var(--bg-tertiary);
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        font-size: 14px;
        color: var(--text-secondary);
        border-left: 3px solid var(--accent-color);
    }
    
    .manual-body {
        font-size: 14px;
        line-height: 1.6;
        color: var(--text-primary);
        white-space: pre-wrap;
        word-break: break-word;
    }
    
    .empty-manual {
        text-align: center;
        padding: 60px;
        color: var(--text-secondary);
        border: 2px dashed var(--border-color);
        border-radius: 12px;
    }
    
    /* Modal Styles for Manual */
    .manual-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 10000;
        justify-content: center;
        align-items: center;
    }
    
    .manual-modal.show {
        display: flex;
    }
    
    .manual-modal .modal-content {
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 25px;
        width: 90%;
        max-width: 700px;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .manual-modal h3 {
        margin: 0 0 20px 0;
        font-size: 20px;
    }
    
    .manual-modal label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        margin-top: 15px;
    }
    
    .manual-modal input[type="text"],
    .manual-modal textarea {
        width: 100%;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: var(--bg-primary);
        color: var(--text-primary);
        font-size: 14px;
        box-sizing: border-box;
    }
    
    .manual-modal input[type="text"]:focus,
    .manual-modal textarea:focus {
        outline: none;
        border-color: var(--accent-color);
    }
    
    .manual-modal textarea {
        min-height: 200px;
        resize: vertical;
        font-family: inherit;
        line-height: 1.5;
    }
    
    .modal-buttons {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 25px;
    }
    
    .modal-cancel-btn {
        background: #95a5a6;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
    }
    
    .modal-cancel-btn:hover {
        background: #7f8c8d;
    }
    
    .modal-confirm-btn {
        background: #27ae60;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
    }
    
    .modal-confirm-btn:hover {
        background: #219a52;
    }
    
    .modal-delete-btn {
        background: #e74c3c;
    }
    
    .modal-delete-btn:hover {
        background: #c0392b;
    }
    
    .loading-spinner-manual {
        text-align: center;
        padding: 40px;
        color: var(--text-secondary);
    }
    
    @media (max-width: 768px) {
        .manual-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .add-manual-btn,
        .refresh-manual-btn {
            justify-content: center;
        }
        
        .manual-card-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .manual-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
</style>

<div class="manual-container">
    <div class="manual-header">
        <h2>📚 Manual / Documentation</h2>
        <div style="display: flex; gap: 10px;">
            <button class="refresh-manual-btn" onclick="loadManualContent()">
                🔄 Refresh
            </button>
            <button class="add-manual-btn" onclick="showAddManualModal()">
                ➕ Add New Content
            </button>
        </div>
    </div>
    
    <div id="manual-entries-container">
        <div class="loading-spinner-manual">Loading manual content...</div>
    </div>
</div>

<script>
    let currentManualData = [];
    let currentEditingIndex = null;
    
    // Load manual content from server
    function loadManualContent() {
        const container = document.getElementById('manual-entries-container');
        container.innerHTML = '<div class="loading-spinner-manual">Loading manual content...</div>';
        
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_manual_content'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentManualData = data.manual || [];
                renderManualContent();
            } else {
                container.innerHTML = '<div class="empty-manual">❌ Error loading manual content: ' + escapeHtml(data.error) + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div class="empty-manual">❌ Error loading manual content</div>';
        });
    }
    
    // Render manual content
    function renderManualContent() {
        const container = document.getElementById('manual-entries-container');
        
        if (!currentManualData || currentManualData.length === 0) {
            container.innerHTML = `
                <div class="empty-manual">
                    <div style="font-size: 48px; margin-bottom: 20px;">📚</div>
                    <h3>No Manual Content Yet</h3>
                    <p>Click "Add New Content" to create your first manual entry.</p>
                </div>
            `;
            return;
        }
        
        let html = '<div class="manual-entries">';
        
        currentManualData.forEach((entry, index) => {
            const title = entry.title || 'Untitled';
            const description = entry.description || '';
            const body = entry.body || '';
            const safeIndex = index;
            
            html += `
                <div class="manual-card" data-index="${safeIndex}">
                    <div class="manual-card-header" onclick="toggleManualCard(this)">
                        <div class="manual-title">
                            <span>📄</span>
                            <span class="title-text">${escapeHtml(title)}</span>
                        </div>
                    </div>
                    <div class="manual-card-body">
                        ${description ? `<div class="manual-description">📝 ${escapeHtml(description)}</div>` : ''}
                        <div class="manual-body">${escapeHtml(body).replace(/\n/g, '<br>')}</div>
                        <div class="manual-actions" onclick="event.stopPropagation()">
                            <button class="edit-manual-btn" onclick="editManualEntry(${safeIndex})">✏️ Edit</button>
                            <button class="delete-manual-btn" onclick="deleteManualEntry(${safeIndex})">🗑️ Delete</button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
    }
    
    // Toggle manual card expansion
    function toggleManualCard(headerElement) {
        const card = headerElement.closest('.manual-card');
        card.classList.toggle('expanded');
    }
    
    // Show add manual modal
    function showAddManualModal() {
        currentEditingIndex = null;
        
        let modal = document.getElementById('manual-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'manual-modal';
            modal.className = 'manual-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <h3 id="manual-modal-title">➕ Add Manual Content</h3>
                    <label>Title:</label>
                    <input type="text" id="manual-title-input" placeholder="Enter title..." autocomplete="off">
                    
                    <label>Description:</label>
                    <input type="text" id="manual-description-input" placeholder="Brief description..." autocomplete="off">
                    
                    <label>Body / Content:</label>
                    <textarea id="manual-body-input" placeholder="Enter the main content here..."></textarea>
                    
                    <div class="modal-buttons">
                        <button type="button" id="manual-modal-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="manual-modal-confirm" class="modal-confirm-btn">Save Content</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('manual-modal-title').textContent = '➕ Add Manual Content';
        document.getElementById('manual-title-input').value = '';
        document.getElementById('manual-description-input').value = '';
        document.getElementById('manual-body-input').value = '';
        
        modal.classList.add('show');
        
        const confirmBtn = document.getElementById('manual-modal-confirm');
        const cancelBtn = document.getElementById('manual-modal-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const title = document.getElementById('manual-title-input').value.trim();
            const description = document.getElementById('manual-description-input').value.trim();
            const body = document.getElementById('manual-body-input').value;
            
            if (!title) {
                showMessage('❌ Title is required', 'error');
                document.getElementById('manual-title-input').focus();
                return;
            }
            
            if (!body) {
                showMessage('❌ Body content is required', 'error');
                document.getElementById('manual-body-input').focus();
                return;
            }
            
            modal.classList.remove('show');
            showPasswordModalForManualSave('add', null, { title, description, body });
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        };
    }
    
    // Edit manual entry
    function editManualEntry(index) {
        currentEditingIndex = index;
        const entry = currentManualData[index];
        
        if (!entry) {
            showMessage('❌ Entry not found', 'error');
            return;
        }
        
        let modal = document.getElementById('manual-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'manual-modal';
            modal.className = 'manual-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <h3 id="manual-modal-title">✏️ Edit Manual Content</h3>
                    <label>Title:</label>
                    <input type="text" id="manual-title-input" placeholder="Enter title..." autocomplete="off">
                    
                    <label>Description:</label>
                    <input type="text" id="manual-description-input" placeholder="Brief description..." autocomplete="off">
                    
                    <label>Body / Content:</label>
                    <textarea id="manual-body-input" placeholder="Enter the main content here..."></textarea>
                    
                    <div class="modal-buttons">
                        <button type="button" id="manual-modal-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="manual-modal-confirm" class="modal-confirm-btn">Save Changes</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('manual-modal-title').textContent = '✏️ Edit Manual Content';
        document.getElementById('manual-title-input').value = entry.title || '';
        document.getElementById('manual-description-input').value = entry.description || '';
        document.getElementById('manual-body-input').value = entry.body || '';
        
        modal.classList.add('show');
        
        const confirmBtn = document.getElementById('manual-modal-confirm');
        const cancelBtn = document.getElementById('manual-modal-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const title = document.getElementById('manual-title-input').value.trim();
            const description = document.getElementById('manual-description-input').value.trim();
            const body = document.getElementById('manual-body-input').value;
            
            if (!title) {
                showMessage('❌ Title is required', 'error');
                document.getElementById('manual-title-input').focus();
                return;
            }
            
            if (!body) {
                showMessage('❌ Body content is required', 'error');
                document.getElementById('manual-body-input').focus();
                return;
            }
            
            modal.classList.remove('show');
            showPasswordModalForManualSave('edit', index, { title, description, body });
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        };
    }
    
    // Delete manual entry
    function deleteManualEntry(index) {
        const entry = currentManualData[index];
        if (!entry) return;
        
        let modal = document.getElementById('manual-delete-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'manual-delete-modal';
            modal.className = 'manual-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <h3>⚠️ Delete Manual Content</h3>
                    <p>Are you sure you want to delete "<strong id="delete-title-display"></strong>"?</p>
                    <p style="color: #e74c3c; font-size: 12px;">This action cannot be undone!</p>
                    <div class="modal-buttons">
                        <button type="button" id="manual-delete-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="manual-delete-confirm" class="modal-confirm-btn modal-delete-btn">Delete</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        document.getElementById('delete-title-display').textContent = entry.title || 'Untitled';
        modal.classList.add('show');
        modal.setAttribute('data-index', index);
        
        const confirmBtn = document.getElementById('manual-delete-confirm');
        const cancelBtn = document.getElementById('manual-delete-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const storedIndex = parseInt(modal.getAttribute('data-index'));
            modal.classList.remove('show');
            showPasswordModalForManualSave('delete', storedIndex, null);
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        };
    }
    
    // Show password modal for manual save operations
    function showPasswordModalForManualSave(action, index, data) {
        let modal = document.getElementById('manual-password-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'manual-password-modal';
            modal.className = 'manual-modal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 400px;">
                    <h3 id="manual-password-title">🔐 Security Verification</h3>
                    <p id="manual-password-message">Please enter your admin password to confirm.</p>
                    <input type="password" id="manual-password-input" class="json-password-input" placeholder="Admin Password" autocomplete="off">
                    <div class="modal-buttons">
                        <button type="button" id="manual-password-cancel" class="modal-cancel-btn">Cancel</button>
                        <button type="button" id="manual-password-confirm" class="modal-confirm-btn">Confirm</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        let titleText = '';
        if (action === 'add') titleText = 'Add New Manual Content';
        else if (action === 'edit') titleText = 'Edit Manual Content';
        else if (action === 'delete') titleText = 'Delete Manual Content';
        
        document.getElementById('manual-password-title').textContent = titleText;
        modal.classList.add('show');
        modal.setAttribute('data-action', action);
        modal.setAttribute('data-index', index);
        if (data) {
            modal.setAttribute('data-title', data.title || '');
            modal.setAttribute('data-description', data.description || '');
            modal.setAttribute('data-body', data.body || '');
        }
        
        const passwordInput = document.getElementById('manual-password-input');
        passwordInput.value = '';
        passwordInput.focus();
        
        const confirmBtn = document.getElementById('manual-password-confirm');
        const cancelBtn = document.getElementById('manual-password-cancel');
        
        const newConfirmBtn = confirmBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newConfirmBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                showMessage('Password is required', 'error');
                passwordInput.focus();
                return;
            }
            const storedAction = modal.getAttribute('data-action');
            const storedIndex = parseInt(modal.getAttribute('data-index'));
            const storedTitle = modal.getAttribute('data-title');
            const storedDescription = modal.getAttribute('data-description');
            const storedBody = modal.getAttribute('data-body');
            modal.classList.remove('show');
            
            if (storedAction === 'add') {
                executeManualAdd({ title: storedTitle, description: storedDescription, body: storedBody }, password);
            } else if (storedAction === 'edit') {
                executeManualEdit(storedIndex, { title: storedTitle, description: storedDescription, body: storedBody }, password);
            } else if (storedAction === 'delete') {
                executeManualDelete(storedIndex, password);
            }
        };
        
        newCancelBtn.onclick = () => {
            modal.classList.remove('show');
        };
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
            }
        };
    }
    
    // Execute manual add
    function executeManualAdd(newEntry, password) {
        const updatedManual = [...currentManualData, newEntry];
        
        saveManualToServer(updatedManual, password, 'added successfully');
    }
    
    // Execute manual edit
    function executeManualEdit(index, updatedEntry, password) {
        const updatedManual = [...currentManualData];
        updatedManual[index] = updatedEntry;
        
        saveManualToServer(updatedManual, password, 'updated successfully');
    }
    
    // Execute manual delete
    function executeManualDelete(index, password) {
        const updatedManual = currentManualData.filter((_, i) => i !== index);
        
        saveManualToServer(updatedManual, password, 'deleted successfully');
    }
    
    // Save manual to server
    function saveManualToServer(manualData, password, successMessage) {
        fetch('serveraccount.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'update_manual_content',
                manual: JSON.stringify(manualData),
                admin_password: password,
                login_id: '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentManualData = manualData;
                renderManualContent();
                showMessage(`✅ Manual content ${successMessage}`, 'success');
            } else {
                showMessage('❌ Error: ' + (data.error || 'Failed to save'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('❌ Error saving manual content', 'error');
        });
    }
    
    // Helper Functions
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showMessage(message, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message';
        messageDiv.innerHTML = '<span style="color:' + (type === 'error' ? '#e74c3c' : '#2ecc71') + ';">' + message + '</span>';
        const container = document.querySelector('.container');
        if (!container) return;
        const existingMessage = container.querySelector('.message');
        if (existingMessage) existingMessage.remove();
        container.insertBefore(messageDiv, container.firstChild);
        setTimeout(() => messageDiv.remove(), 3000);
    }
    
    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
        loadManualContent();
    });
</script>
