<?php
// ════════════════════════════════════════════════════
// SIDEBAR SOUS-BANQUE — HTML uniquement (CSS dans _style.php)
// Usage : require_once 'sidebar.php' APRÈS require_once '_style.php';
// Variables attendues :
//   $page_active : 'dashboard', 'demandes', 'stock', 'lots', 'alertes', 'historique'
//
// Récupère le nom de l'agent connecté depuis utilisateur_sous_banque
// ════════════════════════════════════════════════════

// ── Récupération des infos agent (avec fallbacks pour compat ancienne archi) ──
$agent_nom_complet = $_SESSION['nom_complet'] ?? ($_SESSION['user_nom'] ?? null);
$agent_email       = $_SESSION['email'] ?? ($_SESSION['user_email'] ?? 'agent@sous-banque.mr');
$nom_sb            = $_SESSION['nom_sb'] ?? ($_SESSION['nom_sous_banque'] ?? 'Sous-banque');

// Affichage
if ($agent_nom_complet) {
    $agent_display = htmlspecialchars($agent_nom_complet);
    $parts = preg_split('/\s+/', trim($agent_nom_complet));
    if (count($parts) >= 2) {
        $agent_initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    } else {
        $agent_initials = mb_strtoupper(mb_substr($agent_nom_complet, 0, 2));
    }
} else {
    $agent_display = htmlspecialchars($agent_email);
    $agent_initials = strtoupper(substr($agent_email, 0, 2));
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
            <text x="50" y="46" font-family="Inter, Arial, sans-serif" font-size="11" font-weight="700" fill="#8B0000" letter-spacing="0.5"><?php echo htmlspecialchars(strtoupper(mb_substr($nom_sb, 0, 25))); ?></text>
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

        <div class="nav-label" style="margin-top: 12px;">Opérations</div>

        <a href="demandes.php" class="nav-item <?php echo ($page_active === 'demandes') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            Demandes
        </a>

        <div class="nav-label" style="margin-top: 12px;">Inventaire</div>

        <a href="stock.php" class="nav-item <?php echo ($page_active === 'stock') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                <line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
            Stock interne
        </a>

        <a href="lots.php" class="nav-item <?php echo ($page_active === 'lots') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                <line x1="12" y1="22.08" x2="12" y2="12"/>
            </svg>
            Lots
        </a>

        <a href="alertes.php" class="nav-item <?php echo ($page_active === 'alertes') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Alertes
        </a>

        <div class="nav-label" style="margin-top: 12px;">Audit</div>

        <a href="historique.php" class="nav-item <?php echo ($page_active === 'historique') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            Historique
        </a>
    </nav>

    <!-- ── AGENT CONNECTÉ ── -->
    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="avatar"><?php echo $agent_initials; ?></div>
            <div style="overflow: hidden;">
                <div class="admin-name"><?php echo $agent_display; ?></div>
                <div class="admin-type">Agent sous-banque</div>
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