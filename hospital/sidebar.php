<?php
// ════════════════════════════════════════════════════
// SIDEBAR HÔPITAL — HTML uniquement (CSS dans _style.php)
// Usage : require_once 'sidebar.php' APRÈS require_once '_style.php';
// Variables attendues :
//   $page_active : 'dashboard', 'demandes', 'notifications', 'profil'
// ════════════════════════════════════════════════════
if (!isset($pdo)) { require_once '../config/db.php'; }
require_once '../config/notifications_helper.php';

// ✔ NOUVEAU : utilise la fonction du helper pour compter les notifs non lues
$nb_notifs_non_lues = 0;
if (isset($_SESSION['id_hopital'])) {
    $nb_notifs_non_lues = compter_notifications_non_lues($pdo, (int)$_SESSION['id_hopital']);
}

// ── Récupération des infos responsable ──
$resp_prenom = $_SESSION['prenom_responsable'] ?? '';
$resp_nom    = $_SESSION['nom_responsable'] ?? '';
$resp_poste  = $_SESSION['poste_responsable'] ?? 'Responsable';
$nom_hopital = $_SESSION['nom_hopital'] ?? 'Hôpital';

$resp_display = htmlspecialchars($resp_prenom . ' ' . $resp_nom);
$resp_initials = strtoupper(substr($resp_prenom, 0, 1) . substr($resp_nom, 0, 1));
if (empty($resp_initials)) $resp_initials = 'H';
?>

<style>
    /* Badge de notification dans la sidebar */
    .nav-badge {
        margin-left: auto;
        background: #8B0000;
        color: #FFFFFF;
        font-size: 11px;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 999px;
        min-width: 22px;
        text-align: center;
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(139, 0, 0, 0.4); }
        50%      { box-shadow: 0 0 0 6px rgba(139, 0, 0, 0); }
    }
</style>

<aside class="sidebar">

    <!-- ── LOGO ── -->
    <div class="sidebar-logo">
        <svg width="200" height="60" viewBox="0 0 200 60" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M22 48C32.493 48 41 39.493 41 29C41 21 33 11 22 2C11 11 3 21 3 29C3 39.493 11.507 48 22 48Z" fill="#8B0000"/>
            <rect x="18.5" y="13" width="7" height="22" rx="2.5" fill="white"/>
            <rect x="11" y="20.5" width="22" height="7" rx="2.5" fill="white"/>
            <text x="50" y="28" font-family="Inter, Arial, sans-serif" font-size="22" font-weight="900" fill="#111111" letter-spacing="-0.5">E-Sang</text>
            <text x="50" y="46" font-family="Inter, Arial, sans-serif" font-size="11" font-weight="700" fill="#8B0000" letter-spacing="0.5"><?php echo htmlspecialchars(substr($nom_hopital, 0, 20)); ?></text>
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

        <a href="demandes.php" class="nav-item <?php echo ($page_active === 'demandes') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            Mes demandes
        </a>

        <!-- ✔ NOUVEAU : Lien Notifications avec badge dynamique -->
        <a href="notifications.php" class="nav-item <?php echo ($page_active === 'notifications') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            Notifications
            <?php if ($nb_notifs_non_lues > 0): ?>
                <span class="nav-badge"><?php echo $nb_notifs_non_lues; ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-label" style="margin-top: 12px;">Paramètres</div>

        <a href="profil.php" class="nav-item <?php echo ($page_active === 'profil') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            Mon Profil
        </a>
    </nav>

    <!-- ── FOOTER ── -->
    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="avatar"><?php echo $resp_initials; ?></div>
            <div>
                <div class="admin-name"><?php echo $resp_display; ?></div>
                <div class="admin-type"><?php echo htmlspecialchars($resp_poste); ?></div>
            </div>
        </div>
        <a href="../logout.php" class="btn-disconnect">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Se déconnecter
        </a>
    </div>

</aside>