<?php
// ════════════════════════════════════════════════════
// SIDEBAR DONNEUR — HTML uniquement (CSS dans _style.php)
// Usage : require_once 'sidebar.php' APRÈS require_once '_style.php';
// Variables attendues :
//   $page_active : 'dashboard', 'dons', 'analyses', 'notifications', 'chat', 'profil'
//   $donneur, $citoyen : déjà chargés dans la page
// ════════════════════════════════════════════════════

// ── Calcul affichage donneur ──
if ($citoyen && !empty($citoyen['prenom']) && !empty($citoyen['nom'])) {
    $donneur_display = htmlspecialchars($citoyen['prenom'] . ' ' . $citoyen['nom']);
    $donneur_initials = mb_strtoupper(mb_substr($citoyen['prenom'], 0, 1) . mb_substr($citoyen['nom'], 0, 1));
} else {
    $donneur_display = 'Donneur ' . htmlspecialchars($donneur['NNI'] ?? '');
    $donneur_initials = '👤';
}
?>

<aside class="sidebar">

    <!-- ── LOGO ── -->
    <div class="sidebar-logo">
        <svg width="200" height="60" viewBox="0 0 200 60" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Goutte avec croix -->
            <path d="M22 48C32.493 48 41 39.493 41 29C41 21 33 11 22 2C11 11 3 21 3 29C3 39.493 11.507 48 22 48Z" fill="#8B0000"/>
            <rect x="18.5" y="13" width="7" height="22" rx="2.5" fill="white"/>
            <rect x="11" y="20.5" width="22" height="7" rx="2.5" fill="white"/>
            <!-- Texte -->
            <text x="50" y="28" font-family="Inter, Arial, sans-serif" font-size="22" font-weight="900" fill="#111111" letter-spacing="-0.5">E-Sang</text>
            <text x="50" y="46" font-family="Inter, Arial, sans-serif" font-size="11" font-weight="700" fill="#8B0000" letter-spacing="0.5">DONNEUR</text>
        </svg>
    </div>

    <!-- ── NAVIGATION ── -->
    <nav class="sidebar-nav">
        <div class="nav-label">Navigation</div>

        <a href="dashboard.php" class="nav-item <?php echo ($page_active === 'dashboard') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"/>
                <rect x="14" y="3" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/>
            </svg>
            Mon espace
        </a>

        <div class="nav-label" style="margin-top: 12px;">Mes activités</div>

        <a href="dons.php" class="nav-item <?php echo ($page_active === 'dons') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            Mes dons
        </a>

        <a href="analyses.php" class="nav-item <?php echo ($page_active === 'analyses') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 11l3 3L22 4"/>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
            Mes analyses
        </a>

        <div class="nav-label" style="margin-top: 12px;">Communauté</div>

        <a href="chat.php" class="nav-item <?php echo ($page_active === 'chat') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            Chat groupe
        </a>

        <a href="notifications.php" class="nav-item <?php echo ($page_active === 'notifications') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            Notifications
        </a>

        <div class="nav-label" style="margin-top: 12px;">Compte</div>

        <a href="profil.php" class="nav-item <?php echo ($page_active === 'profil') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            Mon profil
        </a>
    </nav>

    <!-- ── DONNEUR CONNECTÉ ── -->
    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="avatar"><?php echo $donneur_initials; ?></div>
            <div style="overflow: hidden;">
                <div class="admin-name"><?php echo $donneur_display; ?></div>
                <div class="admin-type">Donneur</div>
            </div>
        </div>
        <a href="../logout.php" class="btn-disconnect">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Se déconnecter
        </a>
    </div>

</aside>