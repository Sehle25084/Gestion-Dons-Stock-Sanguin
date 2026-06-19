<?php
// ════════════════════════════════════════════════════
// SIDEBAR ADMIN — HTML uniquement (CSS dans _style.php)
// Usage : require_once 'sidebar.php' APRÈS require_once '_style.php';
// Variables attendues :
//   $page_active : 'dashboard', 'banques', 'hopitaux', etc.
//
// Récupère le nom/prénom de l'admin connecté depuis la session
// ════════════════════════════════════════════════════

// ── Récupération des infos admin (avec fallback) ──
$admin_nom    = $_SESSION['nom']    ?? null;
$admin_prenom = $_SESSION['prenom'] ?? null;
$admin_email  = $_SESSION['email']  ?? 'admin@e-sang.mr';

// Nom complet à afficher
if ($admin_prenom && $admin_nom) {
    $admin_display = htmlspecialchars($admin_prenom . ' ' . $admin_nom);
    $admin_initials = strtoupper(substr($admin_prenom, 0, 1) . substr($admin_nom, 0, 1));
} else {
    // Fallback : utiliser email
    $admin_display = htmlspecialchars($admin_email);
    $admin_initials = strtoupper(substr($admin_email, 0, 2));
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
            <text x="50" y="46" font-family="Inter, Arial, sans-serif" font-size="11" font-weight="700" fill="#8B0000" letter-spacing="0.5">ADMINISTRATEUR</text>
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
            Tableau de bord
        </a>

        <div class="nav-label" style="margin-top: 12px;">Établissements</div>

        <a href="banques.php" class="nav-item <?php echo ($page_active === 'banques') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="22" x2="21" y2="22"/>
                <line x1="6" y1="18" x2="6" y2="11"/>
                <line x1="10" y1="18" x2="10" y2="11"/>
                <line x1="14" y1="18" x2="14" y2="11"/>
                <line x1="18" y1="18" x2="18" y2="11"/>
                <polygon points="12 2 2 7 22 7"/>
            </svg>
            Banques de sang
        </a>

        <a href="sous_banques.php" class="nav-item <?php echo ($page_active === 'sous_banques') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                <line x1="12" y1="22.08" x2="12" y2="12"/>
            </svg>
            Sous-banques
        </a>

        <a href="hopitaux.php" class="nav-item <?php echo ($page_active === 'hopitaux') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Hôpitaux
        </a>

        <div class="nav-label" style="margin-top: 12px;">Données</div>

        <a href="donneurs.php" class="nav-item <?php echo ($page_active === 'donneurs') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Donneurs
        </a>

        <a href="dons.php" class="nav-item <?php echo ($page_active === 'dons') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            Dons
        </a>

        <a href="demandes.php" class="nav-item <?php echo ($page_active === 'demandes') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            Demandes
        </a>

        <a href="stock.php" class="nav-item <?php echo ($page_active === 'stock') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                <line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
            Stock global
        </a>
    </nav>

    <!-- ── ADMIN CONNECTÉ ── -->
    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="avatar"><?php echo $admin_initials; ?></div>
            <div style="overflow: hidden;">
                <div class="admin-name"><?php echo $admin_display; ?></div>
                <div class="admin-type">Administrateur</div>
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