<!-- dev_tabs.php -->
<!-- Mobile Top Header (only visible on small screens) -->
<header class="mobile-top-header" id="mobileTopHeader">
    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open navigation menu">
        <i class="fa-solid fa-bars"></i>
    </button>
    <span class="mobile-header-logo">
        <i class="fa-brands fa-pagelines"></i> HarvHub
    </span>
</header>

<!-- Sidebar Navigation (Desktop: collapsible sidebar | Mobile: slide-in panel) -->
<nav class="sidebar-nav" id="sidebarNav">
    <!-- Desktop toggle button (hidden on mobile) -->
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation">
        <i class="fa-solid fa-bars"></i>
    </button>
    
    <!-- Sidebar header (logo) - visible when expanded on desktop, always on mobile -->
    <div class="sidebar-header">
        <span class="sidebar-logo"><i class="fa-brands fa-pagelines"></i> HarvHub</span>
    </div>
    
    <!-- Menu items -->
    <div class="sidebar-menu">

        <!-- Dashboard -->
        <a href="dev_dashboard.php" class="sidebar-menu-item <?= basename($_SERVER['PHP_SELF']) === 'dev_dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-gauge-high"></i></span>
            <span class="nav-label">Dashboard</span>
        </a>

        <!-- VPS -->
        <a href="dev_vps.php" class="sidebar-menu-item <?= basename($_SERVER['PHP_SELF']) === 'dev_vps.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-computer"></i></span>
            <span class="nav-label">VPS</span>
        </a>

        <!-- Broker: Connect / Update / Disconnect -->
        <?php if (!empty($brokerConnected)): ?>
            <a href="connect_dev_broker.php" class="sidebar-menu-item <?= basename($_SERVER['PHP_SELF']) === 'connect_dev_broker.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-pen-to-square"></i></span>
                <span class="nav-label">Update Broker</span>
            </a>
            <a href="disconnect_dev_broker.php" class="sidebar-menu-item <?= basename($_SERVER['PHP_SELF']) === 'disconnect_dev_broker.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-link-slash"></i></span>
                <span class="nav-label">Disconnect Broker</span>
            </a>
        <?php else: ?>
            <a href="connect_dev_broker.php" class="sidebar-menu-item <?= basename($_SERVER['PHP_SELF']) === 'connect_dev_broker.php' ? 'active' : '' ?>">
                <span class="nav-icon"><i class="fa-solid fa-plug"></i></span>
                <span class="nav-label">Connect Broker</span>
            </a>
        <?php endif; ?>

        <!-- Invest -->
        <a href="app.php" class="sidebar-menu-item <?= basename($_SERVER['PHP_SELF']) === 'app.php' ? 'active' : '' ?>">
            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
            <span class="nav-label">Invest</span>
        </a>
        
        <hr class="sidebar-divider">
    </div>
</nav>

<!-- Mobile overlay backdrop -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>