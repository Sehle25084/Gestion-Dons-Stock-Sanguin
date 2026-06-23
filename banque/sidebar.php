<?php
// ════════════════════════════════════════════════════
// SIDEBAR BANQUE — HTML uniquement (CSS dans _style.php)
// Usage : require_once 'sidebar.php' APRÈS require_once '_style.php';
// Variables attendues :
//   $page_active : 'dashboard', 'demandes', 'dons', 'donneurs', 'stock', 'dechets', 'activites'
//
// Récupère le nom de l'agent connecté depuis utilisateur_banque
// ════════════════════════════════════════════════════

// ── Récupération des infos agent banque (avec fallbacks) ──
// Nouvelle architecture : nom_complet vient de utilisateur_banque
$agent_nom_complet = $_SESSION['nom_complet'] ?? null;
$agent_email       = $_SESSION['email']       ?? 'agent@banque.mr';
$nom_banque        = $_SESSION['nom_banque']  ?? ($_SESSION['nom'] ?? 'Banque');

// Affichage : préférer le nom complet s'il existe, sinon fallback email
if ($agent_nom_complet) {
    $agent_display = htmlspecialchars($agent_nom_complet);
    // Calculer les initiales (ex: "Ahmed Mohamed Salem" → "AS")
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
            <text x="50" y="46" font-family="Inter, Arial, sans-serif" font-size="11" font-weight="700" fill="#8B0000" letter-spacing="0.5"><?php echo htmlspecialchars(strtoupper(mb_substr($nom_banque, 0, 25))); ?></text>
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

        <a href="dons.php" class="nav-item <?php echo ($page_active === 'dons') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            Dons
        </a>

        <a href="donneurs.php" class="nav-item <?php echo ($page_active === 'donneurs') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Donneurs
        </a>

        <a href="analyse.php" class="nav-item <?php echo ($page_active === 'analyse') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 2v7.31"/>
                <path d="M14.5 9.31V2"/>
                <path d="M8.5 2h7"/>
                <path d="M14.5 9.31a3.5 3.5 0 0 1 1.5 2.86V19a3 3 0 0 1-3 3h-6a3 3 0 0 1-3-3v-6.83a3.5 3.5 0 0 1 1.5-2.86"/>
                <path d="M8.5 16h7"/>
            </svg>
            Analyses
        </a>

        <div class="nav-label" style="margin-top: 12px;">Inventaire</div>

        <a href="stock.php" class="nav-item <?php echo ($page_active === 'stock') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                <line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
            Stock
        </a>

        <a href="dechets.php" class="nav-item <?php echo ($page_active === 'dechets') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="3 6 5 6 21 6"/>
                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                <path d="M10 11v6"/>
                <path d="M14 11v6"/>
            </svg>
            Déchets
        </a>

        <div class="nav-label" style="margin-top: 12px;">Audit</div>

        <a href="activites.php" class="nav-item <?php echo ($page_active === 'activites') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            Activités
        </a>
    </nav>

    <!-- ── AGENT CONNECTÉ ── -->
    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="avatar"><?php echo $agent_initials; ?></div>
            <div style="overflow: hidden;">
                <div class="admin-name"><?php echo $agent_display; ?></div>
                <div class="admin-type">Agent de banque</div>
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