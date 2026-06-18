<?php
// ══ SIDEBAR COMMUNE — SOUS-BANQUE ══
// Utilisation : include 'sidebar.php'; avec $page_active défini avant
?>
<style>
    /* Intégration de l'intégralité de tes styles CSS fournis */
    <?php include '../banque/sidebar.php'; ?>
</style>

<aside class="sidebar">
    <div class="sidebar-logo">
        <svg width="210" height="70" viewBox="0 0 210 70" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M22 46C31.941 46 40 37.941 40 28C40 20.5 33 11 22 2C11 11 4 20.5 4 28C4 37.941 12.059 46 22 46Z" fill="#A30000"/>
            <rect x="18.5" y="13" width="7" height="22" rx="3" fill="white"/>
            <rect x="11" y="20.5" width="22" height="7" rx="3" fill="white"/>
            <text x="50" y="30" font-family="Arial Black, Arial, sans-serif" font-size="24" font-weight="900" fill="#1a1a1a" letter-spacing="-0.5">E-Sang SB</text>
            <text x="50" y="50" font-family="Arial, sans-serif" font-size="11" font-weight="500" fill="#444444">Dépôt : <?php echo htmlspecialchars($_SESSION['nom_sb'] ?? 'Inconnu'); ?></text>
        </svg>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Navigation Dépôt</div>

        <a href="dashboard.php" class="nav-item <?php echo ($page_active === 'dashboard') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>

        <a href="stock.php" class="nav-item <?php echo ($page_active === 'stock') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
            Stock Interne
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="hospital-info">
            <div class="avatar" style="background: #FEE2E2; color: #A30000;"><?php echo strtoupper(substr($_SESSION['user_nom'] ?? 'AG', 0, 2)); ?></div>
            <div>
                <div class="hospital-name"><?php echo htmlspecialchars($_SESSION['user_nom'] ?? 'Agent'); ?></div>
                <div class="hospital-type">Agent de Dépôt</div>
            </div>
        </div>
        <a href="../logout.php" class="btn-disconnect">Se déconnecter</a>
    </div>
</aside>