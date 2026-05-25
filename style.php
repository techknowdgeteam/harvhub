<style>
    
    :root {
        --bg-light: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --bg-dark: linear-gradient(135deg, #141e30 0%, #243b55 100%);
        --text-light: #1e293b;
        --text-dark: #f1f5f9;
        --card-light: rgba(255, 255, 255, 0.95);
        --card-dark: rgba(30, 41, 59, 0.95);
        --accent: #10b981;
        --accent-hover: #059669;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
        --success: #10b981;
        --glass-border: rgba(255, 255, 255, 0.2);
        --shadow-sm: 0 10px 40px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.15);
        --shadow-hover: 0 30px 70px rgba(16, 185, 129, 0.2);
        
        /* Passkey modal original colors */
        --passkey-bg: rgba(255, 255, 255, 0.95);
        --passkey-text: #1c1e21;
        --error-color: #ff6b6b;
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --bg-light: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            --text-light: #f1f5f9;
            --card-light: rgba(30, 41, 59, 0.95);
            --glass-border: rgba(255, 255, 255, 0.1);
            /* Preserve passkey dark mode colors */
            --passkey-bg: rgba(40, 40, 40, 0.9);
            --passkey-text: #e4e6eb;
        }
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
    }

    html, body {
        height: 100%;
        color: var(--text-light);
        overflow-x: hidden;
        transition: background 0.3s ease;
    }

    body {
        overflow: hidden;
        position: relative;
        background: var(--bg-light);
    }
    .custom-body {
        width: 100%;
        height: 100%;
        background: var(--bg-light);
        overflow-y: auto; /* Allows vertical scroll only in custom-body */
        -ms-overflow-style: none; /* Hides scrollbar in IE/Edge */
        scrollbar-width: none; /* Hides scrollbar in Firefox */
    }
    .custom-body::-webkit-scrollbar {
        display: none; /* Hides scrollbar in Chrome/Safari/Opera for custom scroller */
    }

    /* Animated background particles (only for dashboard, not passkey) */
    body:not(.passkey-active)::before {
        content: "";
        position: fixed;
        inset: 0;
        background: 
            radial-gradient(circle at 20% 30%, rgba(102, 126, 234, 0.15) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%, rgba(118, 75, 162, 0.15) 0%, transparent 50%),
            repeating-linear-gradient(45deg, rgba(255,255,255,0.02) 0px, rgba(255,255,255,0.02) 2px, transparent 2px, transparent 8px);
        pointer-events: none;
        z-index: -1;
        animation: gradientShift 15s ease infinite;
    }

    @keyframes gradientShift {
        0%, 100% { opacity: 0.5; }
        50% { opacity: 0.8; }
    }

    /* ===== PASSKEY MODAL - PRESERVED ORIGINAL STYLES ===== */
    .passkey-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 1rem;
    }

    .passkey-screen {
        background: var(--passkey-bg);
        color: var(--passkey-text);
        backdrop-filter: blur(12px);
        padding: 3rem 2.5rem;
        border-radius: 20px;
        width: 100%;
        max-width: 480px;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        border: 1px solid rgba(255,255,255,0.1);
    }

    .passkey-screen h2 {
        font-size: 2rem;
        margin-bottom: 1rem;
        color: var(--passkey-text);
        background: none;
        -webkit-text-fill-color: var(--passkey-text);
    }

    .passkey-screen p {
        margin: 1.5rem 0;
        opacity: 0.9;
        font-size: 1rem;
    }

    .passkey-screen input[type="password"] {
        width: 100%;
        padding: 16px;
        margin: 20px 0;
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 12px;
        font-size: 1.1rem;
        text-align: center;
        background: rgba(0,0,0,0.05);
        color: var(--passkey-text);
    }

    @media (prefers-color-scheme: dark) {
        .passkey-screen input[type="password"] { 
            background: rgba(255,255,255,0.1); 
        }
    }

    .passkey-screen .error-message { 
        color: var(--error-color); 
        margin: -10px 0 10px; 
        font-weight: bold; 
    }

    .passkey-screen .btn-full {
        width: 100%;
        padding: 16px;
        background: var(--accent);
        color: #000;
        border: none;
        border-radius: 12px;
        font-weight: bold;
        font-size: 1.1rem;
        cursor: pointer;
        transition: opacity 0.3s;
    }

    .passkey-screen .btn-full:hover {
        opacity: 0.9;
    }

    .passkey-screen a {
        display: block;
        margin: 20px 0;
        color: var(--accent);
        font-size: 0.95rem;
        text-decoration: none;
    }

    .passkey-screen a:hover {
        text-decoration: underline;
    }

    .passkey-screen a[href*="logout"] {
        color: #ff6b6b;
    }
    /* ===== END PASSKEY MODAL STYLES ===== */

    .dashboard-wrapper {
        width: 100%;
        max-width: 1300px;
        height: 100vh;
        margin: 0 auto;
        padding: 2rem;
        overflow-y: auto;
        scroll-behavior: smooth;
        -ms-overflow-style: none;
        scrollbar-width: none;
        position: relative;
    }

    .dashboard-wrapper::-webkit-scrollbar {
        display: none;
    }

    h1 {
        font-size: 3.5rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--accent) 0%, #3b82f6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        text-align: center;
        margin-bottom: 0.5rem;
        letter-spacing: -0.02em;
        animation: fadeInDown 0.6s ease;
    }

    .welcome {
        text-align: center;
        font-size: 1.25rem;
        margin-bottom: 2rem;
        opacity: 0.9;
        animation: fadeInUp 0.6s ease 0.2s both;
    }

    .welcome strong {
        background: linear-gradient(135deg, var(--accent), var(--info));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 700;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        margin: 2rem 0;
        animation: fadeInUp 0.6s ease 0.4s both;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        h1 {
            font-size: 2.5rem;
        }
        
        .dashboard-wrapper {
            padding: 1rem;
        }
    }

    /* Enhanced Stat Cards */
    .stat-card {
        position: relative;
        background: var(--card-light);
        backdrop-filter: blur(20px);
        padding: 1.75rem;
        border-radius: 24px;
        text-align: center;
        border: 1px solid var(--glass-border);
        box-shadow: var(--shadow-sm);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        overflow: hidden;
        animation: cardAppear 0.5s ease;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--accent), var(--info), var(--accent));
        transform: translateX(-100%);
        transition: transform 0.5s ease;
    }

    .stat-card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: var(--shadow-hover);
        border-color: var(--accent);
    }

    .stat-card:hover::before {
        transform: translateX(0);
    }

    .stat-card h3 {
        font-size: 1.1rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.7;
        margin-bottom: 1rem;
    }

    .stat-card h2 {
        font-size: 2.8rem;
        font-weight: 800;
        line-height: 1.2;
        margin: 0.5rem 0;
        transition: all 0.3s ease;
        position: relative;
        display: inline-block;
    }

    .stat-card h2::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 50%;
        transform: translateX(-50%);
        width: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--accent), var(--info));
        transition: width 0.3s ease;
        border-radius: 2px;
    }

    .stat-card:hover h2::after {
        width: 50%;
    }

    /* Balance Toggle Button */
    .balance-toggle-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid var(--glass-border);
        color: var(--text-light);
        font-size: 1.25rem;
        cursor: pointer;
        padding: 8px;
        border-radius: 12px;
        opacity: 0.6;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        z-index: 10;
    }

    .balance-toggle-btn:hover {
        opacity: 1;
        background: var(--accent);
        color: white;
        transform: rotate(15deg);
    }

    /* Profit/Loss Colors with Animation */
    .profit-positive {
        color: var(--success) !important;
        text-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
    }

    .profit-negative {
        color: var(--danger) !important;
        text-shadow: 0 0 20px rgba(239, 68, 68, 0.3);
    }

    /* Stat Details */
    .stat-details-info {
        font-size: 0.9rem;
        opacity: 0.6;
        padding: 0.5rem;
        transition: all 0.3s ease;
    }

    .stat-card:hover .stat-details-info {
        opacity: 0.9;
    }

    /* Trades Card Special Styling */
    .stat-card.trades-card {
        grid-column: 1 / -1;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(59, 130, 246, 0.1));
        border: 2px solid transparent;
        background-clip: padding-box;
        position: relative;
    }

    .stat-card.trades-card::before {
        content: '';
        position: absolute;
        inset: -2px;
        background: linear-gradient(135deg, var(--accent), var(--info));
        border-radius: 26px;
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: -1;
    }

    .stat-card.trades-card:hover::before {
        opacity: 0.3;
    }

    /* Loyalty Card */
    .stat-card.loyalty-card {
        grid-column: 1 / -1;
        max-width: 800px;
        margin: 2rem auto;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(59, 130, 246, 0.15));
        border: 2px solid rgba(0, 255, 34, 0.3);
    }

    .loyalty-status-msg {
        font-size: 1.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--accent), var(--info));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 1rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .loyalty-card p {
        font-size: 1.1rem;
        line-height: 1.6;
        opacity: 0.9;
        max-width: 600px;
        margin: 0 auto 1rem;
    }

    .contract-dates {
        display: inline-block;
        padding: 0.5rem 1.5rem;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 500;
        margin: 0.5rem;
        backdrop-filter: blur(10px);
    }

    .contract-days-left {
        display: inline-block;
        padding: 0.25rem 1rem;
        background: var(--accent);
        color: white;
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 600;
        margin-left: 0.5rem;
    }

    /* Loyalty Buttons */
    .loyalty-card button {
        margin: 1.5rem auto 0;
        padding: 1rem 3rem;
        font-size: 1.1rem;
        font-weight: 700;
        border: none;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .btn-loyalty-action {
        background: linear-gradient(135deg, var(--accent), var(--info)) !important;
        color: white !important;
    }

    .btn-loyalty-action:hover {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 10px 30px rgba(16, 185, 129, 0.5) !important;
    }

    .btn-loyalty-paid {
        background: linear-gradient(135deg, #6b7280, #4b5563) !important;
        color: white !important;
        cursor: not-allowed !important;
        opacity: 0.7;
    }

    .btn-loyalty-confirmed {
        background: linear-gradient(135deg, var(--success), #059669) !important;
        color: white !important;
        cursor: default !important;
    }

    /* Dashboard Disclaimer */
    .dashboard-disclaimer {
        text-align: center;
        margin: 1.5rem auto;
        padding: 1rem 2rem;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(16, 185, 129, 0.2));
        border: 1px solid rgba(16, 185, 129, 0.3);
        border-radius: 50px;
        font-weight: 600;
        font-size: 1.1rem;
        max-width: 600px;
        backdrop-filter: blur(10px);
        animation: slideIn 0.5s ease;
    }
    

    /* Encouragement Note */
    /* Replace these existing styles */
    .note-btndanger{
        display: flex;
        justify-content: center
        width: 100%;
    }
    .note-btndanger-block{
        width: auto;
    }

    /* With these updated styles */
    .note-btndanger {
        display: flex;
        justify-content: center;
        width: 100%;
        margin: 20px 0;
    }

    .note-btndanger-block {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        max-width: 600px;
        width: 100%;
    }

    .note {
        margin-bottom: 15px;
        opacity: 0.8;
        line-height: 1.6;
    }
    .encouragement-note {
        text-align: center;
        margin: 1rem auto;
        padding: 1rem;
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(239, 68, 68, 0.2));
        border: 1px solid var(--warning);
        border-radius: 16px;
        font-style: italic;
        font-size: 1.1rem;
        max-width: 800px;
        animation: pulse 2s infinite;
    }

    /* Danger Button */
    .btn-danger {
        display: block;
        margin-bottom: 10px;
        margin-top: 10px;
        padding: 1rem 1rem;
        background: linear-gradient(135deg, var(--danger), #dc2626);
        color: white;
        border: none;
        border-radius: 20px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
    }

    .btn-danger:hover {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 10px 30px rgba(239, 68, 68, 0.5);
    }

    /* Logout Link */
    .logout-link-p{
        margin-top: 20px;
        margin-bottom: 50px;
    }
    .logout-link {
        display: block;
        text-align: center;
        margin-top: 1rem;
        padding: 0.5rem;
        color: var(--text-light);
        text-decoration: none;
        opacity: 0.6;
        transition: all 0.3s ease;
        font-size: 0.95rem;
    }

    .logout-link:hover {
        opacity: 1;
        color: var(--danger);
        transform: translateY(-2px);
    }

    /* Blur Mode Effect */
    .dashboard-wrapper.blur-mode .stat-card h2 {
        filter: blur(8px);
        transition: filter 0.3s ease;
        user-select: none;
    }

    .dashboard-wrapper.blur-mode .stat-card:hover h2 {
        filter: blur(6px);
    }

    /* Modal Styles (for non-passkey modals) */
    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(10px);
        align-items: center;
        justify-content: center;
        z-index: 999;
        padding: 1rem;
        animation: fadeIn 0.3s ease;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: var(--card-light);
        backdrop-filter: blur(20px);
        padding: 2.5rem;
        border-radius: 24px;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        border: 1px solid var(--glass-border);
        box-shadow: var(--shadow-lg);
        animation: modalSlideUp 0.4s ease;
    }

    .modal-content h2 {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 1rem;
        background: linear-gradient(135deg, var(--accent), var(--info));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Split Items in Modal */
    .split-item {
        background: rgba(255, 255, 255, 0.05);
        padding: 1.5rem;
        border-radius: 16px;
        margin: 1rem 0;
        border: 1px solid var(--glass-border);
        transition: all 0.3s ease;
    }

    .split-item:hover {
        transform: translateY(-3px);
        border-color: var(--accent);
        box-shadow: 0 10px 30px rgba(16, 185, 129, 0.2);
    }

    .split-item h4 {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    /* Coin Selector */
    .coin-selector {
        display: flex;
        gap: 1rem;
        margin: 2rem 0;
    }

    .coin-selector label {
        flex: 1;
        padding: 1rem;
        text-align: center;
        background: rgba(255, 255, 255, 0.05);
        border: 2px solid transparent;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .coin-selector input[type="radio"]:checked + label {
        background: linear-gradient(135deg, var(--accent), var(--info));
        color: white;
        border-color: var(--accent);
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(16, 185, 129, 0.3);
    }

    .coin-selector input[type="radio"] {
        display: none;
    }
    /* Payment Modal Styles */
    .btn-full {
        width: 100%;
        padding: 14px;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-paid {
        background: linear-gradient(135deg, var(--accent), var(--info));
        color: white;
        margin-top: 1rem;
    }

    .btn-paid:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
    }

    .btn-paid:disabled {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        cursor: not-allowed;
        opacity: 0.6;
        transform: none;
    }

    #copyAddressBtn {
        background: rgba(255, 255, 255, 0.1);
        color: var(--text-light);
        border: 1px solid var(--glass-border);
        margin: 1rem 0;
    }

    #copyAddressBtn:hover {
        background: rgba(16, 185, 129, 0.2);
        border-color: var(--accent);
        transform: translateY(-2px);
    }

    /* Checkbox Container Styles */
    .checkbox-container {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin: 1.5rem 0;
        padding: 12px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .checkbox-container:hover {
        background: rgba(16, 185, 129, 0.1);
    }

    .checkbox-container input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
        accent-color: var(--accent);
    }

    .checkbox-container input[type="checkbox"]:checked {
        transform: scale(1.05);
    }

    /* Crypto Details Section */
    .crypto-details {
        background: rgba(0, 0, 0, 0.2);
        padding: 1rem;
        border-radius: 12px;
        margin: 1rem 0;
    }

    .crypto-details p {
        margin: 0.5rem 0;
        font-size: 0.9rem;
    }

    .crypto-details strong {
        color: var(--accent);
    }

    /* Disclaimer Text */
    .disclaimer {
        font-size: 0.75rem;
        text-align: center;
        margin-top: 1rem;
        opacity: 0.6;
        padding: 0.5rem;
    }

    /* Split Container */
    .split-container {
        display: flex;
        gap: 1.5rem;
        margin: 1.5rem 0;
    }

    .split-total {
        font-size: 1.2rem;
        font-weight: 600;
        text-align: center;
        padding: 0.5rem;
        background: rgba(16, 185, 129, 0.1);
        border-radius: 8px;
    }
    /* Hide scrollbar for modal content */
    .modal-content {
        -ms-overflow-style: none;  /* IE and Edge */
        scrollbar-width: none;  /* Firefox */
    }

    .modal-content::-webkit-scrollbar {
        display: none;  /* Chrome, Safari, Opera */
    }

    @media (max-width: 768px) {
        .split-container {
            flex-direction: column;
            gap: 1rem;
        }
    }

    /* Crypto Address Display */
    .btc-address {
        display: block;
        padding: 1rem;
        background: rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        font-family: 'Monaco', 'Menlo', monospace;
        font-size: 0.9rem;
        word-break: break-all;
        border: 1px dashed var(--accent);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btc-address:hover {
        background: rgba(16, 185, 129, 0.1);
        transform: scale(1.02);
    }

    /* History Section */
    .history-section {
        margin-top: 1rem;
        max-height: 300px;
        overflow-y: auto;
        padding: 1rem;
        background: rgba(0, 0, 0, 0.05);
        border-radius: 12px;
    }

    .history-item {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
        border-radius: 8px;
    }

    .history-item:hover {
        background: rgba(16, 185, 129, 0.1);
        transform: translateX(5px);
    }

    .history-symbol {
        font-weight: 600;
    }

    .history-amount-won {
        color: var(--success);
        font-weight: 700;
    }

    .history-amount-lost {
        color: var(--danger);
        font-weight: 700;
    }

    /* Modal Actions */
    .modal-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
    }

    .modal-actions button {
        flex: 1;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .modal-actions button:hover {
        transform: translateY(-2px);
    }

    /* Animations */
    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes cardAppear {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @keyframes modalSlideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.02);
        }
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .stat-card h2 {
            font-size: 2rem;
        }
        
        .trades-count {
            font-size: 3rem;
        }
        
        .loyalty-status-msg {
            font-size: 1.2rem;
        }
        
        .modal-content {
            padding: 1.5rem;
        }
        
        .coin-selector {
            flex-direction: column;
        }
    }

    /* Loading States */
    .loading {
        position: relative;
        overflow: hidden;
    }

    .loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        animation: loading 1.5s infinite;
    }

    @keyframes loading {
        0% {
            transform: translateX(-100%);
        }
        100% {
            transform: translateX(100%);
        }
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.05);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, var(--accent), var(--info));
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, var(--accent-hover), #2563eb);
    }
    /* Additional styles for re-enrollment modal */
    .reenroll-instructions {
        background: rgba(255, 255, 255, 0.05);
        border-left: 4px solid var(--info-color);
        padding: 1.5rem;
        margin: 1.5rem 0;
        border-radius: 0 8px 8px 0;
        text-align: left;
    }
    
    .reenroll-instructions h4 {
        color: var(--info-color);
        margin-bottom: 1rem;
        font-size: 1.1rem;
    }
    
    .reenroll-instructions ul {
        list-style: none;
        padding-left: 0;
    }
    
    .reenroll-instructions ul li {
        position: relative;
        padding-left: 28px;
        margin-bottom: 12px;
        color: #e0e0e0;
        font-size: 0.95rem;
    }
    
    .reenroll-instructions ul li::before {
        content: '⚠';
        position: absolute;
        left: 0;
        top: 0;
        color: var(--warning-color);
        font-size: 1.1rem;
    }
    
    .reenroll-instructions .consequence-note {
        margin-top: 1.2rem;
        padding: 0.8rem;
        background: rgba(255, 107, 107, 0.1);
        border-radius: 6px;
        font-size: 0.9rem;
        color: #ff6b6b;
        font-weight: 500;
    }
    
    .checkbox-container-legal {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin: 1.5rem 0;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 8px;
        cursor: pointer;
    }
    
    .checkbox-container-legal input[type="checkbox"] {
        margin-top: 3px;
        width: 18px;
        height: 18px;
        accent-color: var(--success-color);
        flex-shrink: 0;
    }
    
    .checkbox-container-legal label {
        font-size: 0.9rem;
        color: #ccc;
        line-height: 1.5;
        cursor: pointer;
    }
    
    .passkey-verification-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        z-index: 10001;
        align-items: center;
        justify-content: center;
    }
    
    .passkey-verification-overlay.active {
        display: flex;
    }
    
    .passkey-verification-box {
        background: var(--passkey-bg);
        padding: 2.5rem;
        border-radius: 16px;
        width: 90%;
        max-width: 450px;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .passkey-verification-box h3 {
        margin-bottom: 1rem;
        color: var(--accent);
    }
    
    .passkey-verification-box input {
        width: 100%;
        padding: 12px;
        margin: 10px 0;
        border: 2px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
        font-size: 1rem;
        transition: border-color 0.3s;
    }
    
    .passkey-verification-box input:focus {
        outline: none;
        border-color: var(--accent);
    }
    
    .passkey-verification-box .error-message {
        color: #ff6b6b;
        margin-top: 10px;
        display: none;
    }
    
    .passkey-verification-box .btn-verify-passkey {
        width: 100%;
        padding: 12px;
        margin-top: 15px;
        background: var(--info-color);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        background: #1ab5b2;
        cursor: pointer;
        transition: background 0.3s;
    }
    
    .passkey-verification-box .btn-verify-passkey:hover {
        background: #1a6fb5;
    }
    
    .passkey-verification-box .btn-verify-passkey:disabled {
        background: #555;
        cursor: not-allowed;
    }
    
    .passkey-verification-box .btn-cancel {
        width: 100%;
        padding: 12px;
        margin-top: 10px;
        background: #555;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        cursor: pointer;
    }
    
    .reenroll-confirm-btn {
        width: 100%;
        padding: 14px;
        margin-top: 15px;
        background: var(--success-color);
        color: #00589ca9;
        border: none;
        border-radius: 8px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .reenroll-confirm-btn:disabled {
        background: #555;
        color: #999;
        cursor: not-allowed;
        opacity: 0.6;
    }
    
    .reenroll-confirm-btn:not(:disabled):hover {
        background: #27ae60;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3);
    }
    /* Enhanced Disconnect Modal Styles */
    .disconnect-verify-section {
        margin: 1.5rem 0;
        text-align: left;
    }
    
    .disconnect-verify-section label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--text-color);
    }
    
    .disconnect-verify-section input {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.05);
        color: var(--text-color);
        font-size: 1rem;
        margin-bottom: 1rem;
        box-sizing: border-box;
    }
    
    .disconnect-verify-section input:focus {
        outline: none;
        border-color: var(--accent);
    }
    
    .disconnect-warning-note {
        background: rgba(231, 76, 60, 0.1);
        border-left: 3px solid #e74c3c;
        padding: 1rem;
        margin: 1.5rem 0;
        border-radius: 8px;
        font-size: 0.9rem;
        color: #ff9f9f;
    }
    
    .disconnect-errors {
        background: rgba(231, 76, 60, 0.2);
        border: 1px solid #e74c3c;
        border-radius: 8px;
        padding: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .disconnect-errors ul {
        margin: 0;
        padding-left: 1.25rem;
    }
    
    .disconnect-errors li {
        color: #ff9f9f;
        font-size: 0.85rem;
        margin: 0.25rem 0;
    }
    
    .modal-actions-vertical {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 1rem;
    }
    
    .btn-danger-final {
        background: #e74c3c;
        color: white;
        border: none;
        padding: 14px;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-danger-final:hover {
        background: #c0392b;
        transform: scale(1.02);
    }
    
    .btn-danger-final:disabled {
        background: #555;
        cursor: not-allowed;
        opacity: 0.6;
        transform: none;
    }
    
    .btn-cancel-final {
        background: #555;
        color: white;
        border: none;
        padding: 12px;
        border-radius: 8px;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-cancel-final:hover {
        background: #666;
    }
     /* Notification Bell Styles */
    .notification-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }

    .notification-bell {
        position: relative;
        cursor: pointer;
        background: var(--card-light);
        backdrop-filter: blur(10px);
        padding: 12px;
        border-radius: 50%;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--glass-border);
        transition: all 0.3s ease;
        box-shadow: var(--shadow-sm);
    }

    .notification-bell:hover {
        transform: scale(1.05);
        border-color: var(--accent);
        box-shadow: var(--shadow-hover);
    }

    .notification-bell i {
        font-size: 20px;
        color: var(--text-light);
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: var(--danger);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        animation: pulse 2s infinite;
    }

    /* Fullscreen Notification Panel */
    .notification-panel {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        height: 100%;
        background: var(--card-light);
        backdrop-filter: blur(20px);
        z-index: 10000;
        display: none;
        flex-direction: column;
        overflow: hidden;
        animation: slideDown 0.4s ease forwards;
    }

    .notification-panel.active {
        display: flex;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-100%);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* PASSKEY OVERLAY - MUST BE HIGHER THAN NOTIFICATION PANEL */
    .passkey-overlay {
        z-index: 20000 !important; /* Higher than notification panel */
    }

    /* When passkey overlay is active, ensure it's on top */
    .passkey-overlay.active {
        z-index: 20000 !important;
    }

    /* Lower notification bell z-index when passkey is visible */
    body:has(.passkey-overlay) .notification-container {
        z-index: 10001 !important;
    }

    /* Ensure passkey screen is above everything when active */
    .passkey-overlay .passkey-screen {
        position: relative;
        z-index: 20001;
    }

    /* Notification panel should be below passkey overlay */
    .passkey-overlay ~ .notification-panel,
    body:has(.passkey-overlay) .notification-panel {
        z-index: 9999 !important;
    }

    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem 2rem;
        border-bottom: 1px solid var(--glass-border);
        background: rgba(0, 0, 0, 0.05);
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .notification-header h3 {
        font-size: 1.5rem;
        font-weight: 600;
        margin: 0;
        background: linear-gradient(135deg, var(--accent), var(--info));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .close-notifications {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid var(--glass-border);
        font-size: 24px;
        cursor: pointer;
        color: var(--text-light);
        opacity: 0.7;
        transition: all 0.3s ease;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .close-notifications:hover {
        opacity: 1;
        transform: rotate(90deg);
        background: rgba(239, 68, 68, 0.2);
        border-color: var(--danger);
    }

    .notification-list {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
        max-height: calc(100vh - 80px);
    }

    .notification-item {
        padding: 1.25rem 1.5rem;
        margin-bottom: 0.75rem;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--glass-border);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .notification-item:hover {
        background: rgba(16, 185, 129, 0.1);
        transform: translateX(5px);
        border-color: var(--accent);
    }

    .notification-item.unread {
        background: rgba(59, 130, 246, 0.15);
        border-left: 4px solid var(--info);
    }

    .notification-item.success {
        border-left: 4px solid var(--success);
    }

    .notification-item.error {
        border-left: 4px solid var(--danger);
    }

    .notification-item.warning {
        border-left: 4px solid var(--warning);
    }

    .notification-section {
        font-size: 0.75rem;
        opacity: 0.6;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }

    .notification-message {
        font-size: 1rem;
        line-height: 1.5;
        margin-bottom: 0.75rem;
        color: var(--text-light);
    }

    .notification-time {
        font-size: 0.75rem;
        opacity: 0.5;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .notification-time::before {
        content: "🕐";
        font-size: 0.7rem;
    }

    .empty-notifications {
        text-align: center;
        padding: 4rem 2rem;
        opacity: 0.6;
        font-size: 1rem;
    }

    /* Dark mode adjustments */
    @media (prefers-color-scheme: dark) {
        .notification-bell {
            background: var(--card-dark);
        }
        
        .notification-panel {
            background: var(--card-dark);
        }
        
        .notification-item {
            background: rgba(255, 255, 255, 0.03);
        }
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .notification-header {
            padding: 1rem 1.25rem;
        }
        
        .notification-header h3 {
            font-size: 1.25rem;
        }
        
        .notification-item {
            padding: 1rem;
        }
        
        .notification-message {
            font-size: 0.9rem;
        }
    }
</style>
<style>
    /* Revenue History Button */
    .btn-revenue-history {
        background: none;
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    /* Revenue History Modal */
    #revenueHistoryModal.modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.95);
        backdrop-filter: blur(20px);
        z-index: 10000;
        padding: 0;
    }

    #revenueHistoryModal .modal-content {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        height: 100%;
        max-width: none;
        max-height: none;
        border-radius: 0;
        padding: 2rem;
        display: flex;
        flex-direction: column;
        background: var(--card-light);
        margin: 0;
        overflow: hidden;
    }

    #revenueHistoryModal h2 {
        margin-bottom: 1rem;
        flex-shrink: 0;
    }

    /* Revenue History Container - Takes remaining space, no horizontal scroll */
    .revenue-history-container {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0;
        margin: 1rem 0;
        width: 100%;
        -ms-overflow-style: none;
        scrollbar-width: thin;
    }

    .revenue-history-container::-webkit-scrollbar {
        width: 8px;
    }

    .revenue-history-container::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.1);
        border-radius: 10px;
    }

    .revenue-history-container::-webkit-scrollbar-thumb {
        background: var(--accent);
        border-radius: 10px;
    }

    /* Revenue Item - Fixed width container */
    .revenue-item {
        background: none;
        border-radius: 5px;
        margin-bottom: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
        border: none;
        width: 100%;
        box-sizing: border-box;
        display: block;
    }

    .revenue-item:hover {
        border-color: var(--accent);
    }

    /* Revenue Header - Fixed width container */
    .revenue-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        cursor: pointer;
        background: rgba(255, 255, 255, 0.03);
        transition: all 0.3s ease;
        width: 100%;
        box-sizing: border-box;
        gap: 15px;
    }

    .revenue-header:hover {
        background: rgba(16, 185, 129, 0.1);
    }

    .revenue-header-left {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex: 1;
    }

    .revenue-date-range {
        font-weight: 600;
        font-size: 0.75rem;
        color: rgb(141, 141, 141);
        opacity: 0.9;
        word-break: break-word;
    }

    .revenue-user-share {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--success-color);
    }

    /* Status badge */
    .revenue-status {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 600;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .revenue-status.completed {
        color: #10b981;
    }

    .revenue-status.pending {
        color: #f59e0b;
    }

    .revenue-status.loss {
        color: #ef4444;
    }

    /* Revenue Details section - NO horizontal scroll, preserves layout */
    .revenue-details {
        display: none;
        padding: 16px 20px;
        background: rgba(0, 0, 0, 0.2);
        border-top: 1px solid var(--glass-border);
        width: 100%;
        box-sizing: border-box;
        overflow-x: hidden;
    }

    .revenue-details.active {
        display: block;
        animation: slideDown 0.3s ease;
    }

    /* Each detail row - flex with proper wrapping */
    .revenue-detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        width: 100%;
        box-sizing: border-box;
    }

    .revenue-detail-row:last-child {
        border-bottom: none;
    }

    .revenue-detail-label {
        font-weight: 500;
        opacity: 0.7;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    .revenue-detail-value {
        font-weight: 600;
        font-size: 0.9rem;
        text-align: right;
        word-break: break-word;
        flex-shrink: 0;
    }

    .revenue-detail-value.profit-positive {
        color: var(--success);
    }

    .revenue-detail-value.profit-negative {
        color: var(--danger);
    }

    .empty-revenue {
        text-align: center;
        padding: 60px 20px;
        opacity: 0.7;
        font-size: 1rem;
    }

    #revenueHistoryModal .modal-actions {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--glass-border);
        justify-content: flex-end;
        flex-shrink: 0;
    }

    #revenueHistoryModal .modal-actions button {
        padding: 10px 24px;
        font-size: 0.9rem;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Mobile responsive adjustments - no width changes on click */
    @media (max-width: 768px) {
        #revenueHistoryModal .modal-content {
            padding: 1rem;
        }
        
        .revenue-header {
            padding: 12px 16px;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        
        .revenue-status {
            align-self: flex-start;
        }
        
        .revenue-details {
            padding: 12px 16px;
        }
        
        .revenue-detail-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }
        
        .revenue-detail-value {
            text-align: left;
        }
    }
    /* Active Contract Card */
    .revenue-item.active-contract {
        background: rgba(16, 185, 129, 0.05);
        margin-bottom: 20px;
    }
    
    .revenue-item.active-contract .revenue-header {
        background: none;
    }
    
    .revenue-item.active-contract .revenue-user-share {
        color: var(--accent);
        font-size: 1rem;
        letter-spacing: 0.5px;
    }
    
    .revenue-item.active-contract .revenue-date-range {
        color: var(--accent);
        font-weight: 600;
    }
    
</style>
<style>
    /* Additional styles for unpaid-payment state */
    .unpaid-warning {
        background: rgba(255, 107, 107, 0.1);
        border-left: 4px solid #ff6b6b;
        padding: 12px;
        margin: 10px 0;
        font-size: 0.9rem;
    }
    
    .payment-required-badge {
        background: #ff6b6b;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: bold;
        margin-left: 10px;
    }
    
    .threshold-warning {
        background: rgba(255, 193, 7, 0.1);
        border-left: 4px solid #ffc107;
        padding: 12px;
        margin: 10px 0;
        font-size: 0.9rem;
    }
</style>