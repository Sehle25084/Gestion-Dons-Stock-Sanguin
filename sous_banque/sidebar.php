<?php
// ══ SIDEBAR COMMUNE — SOUS-BANQUE ══
// À inclure en haut de chaque page du dossier /sous_banque/
// Définir $page_active avant l'include :
// Ex: $page_active = 'dashboard'; / 'stock' / 'demandes' / 'alertes'

// Sécurité : toutes les pages sous-banque passent par ici
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sous_banque') {
    header("Location: ../index.php");
    exit;
}

// Compter les alertes non traitées pour le badge
$nb_alertes = 0;
try {
    $stmt_al = $pdo->prepare("
        SELECT COUNT(*) FROM alerte_stock
        WHERE id_sous_banque = ? AND traitee = 0
    ");
    $stmt_al->execute([$_SESSION['id_sous_banque']]);
    $nb_alertes = (int)$stmt_al->fetchColumn();
} catch (Exception $e) {
    $nb_alertes = 0;
}
?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    /* ... Tes styles CSS restent exactement identiques à ceux fournis ... */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: #FAFAFA; color: #111111; font-size: 15px; line-height: 1.5; display: flex; min-height: 100vh; }
    .sidebar { width: 260px; min-width: 260px; background: #FFFFFF; border-right: 1px solid #E5E7EB; display: flex; flex-direction: column; position: fixed; top: 0; left: 0; height: 100vh; z-index: 100; }
    .sidebar-logo { display: flex; align-items: center; padding: 12px 20px 10px; border-bottom: 1px solid #E5E7EB; }
    .sidebar-nav { flex: 1; padding: 10px 12px; display: flex; flex-direction: column; gap: 4px; overflow-y: auto; }
    .nav-label { font-size: 10px; font-weight: 700; color: #111111; letter-spacing: 0.08em; text-transform: uppercase; padding: 8px 10px 4px; }
    .nav-item { display: flex; align-items: center; gap: 10px; padding: 11px 14px; border-radius: 10px; font-size: 14px; font-weight: 600; color: #111111; text-decoration: none; border: 1.5px solid #E5E7EB; background: #FFFFFF; width: 100%; text-align: left; cursor: pointer; transition: all 0.15s ease; margin-bottom: 4px; position: relative; }
    .nav-item:hover { background: #FDF2F2; color: #6B0000; border-color: #6B0000; }
    .nav-item.active { background: #FDF2F2; color: #6B0000; border-color: #6B0000; font-weight: 700; }
    .nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }
    .badge-alerte { margin-left: auto; background: #6B0000; color: #FFFFFF; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 999px; min-width: 20px; text-align: center; }
    .sidebar-footer { padding: 14px 16px; border-top: 1px solid #E5E7EB; }
    .agent-info { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; padding: 10px 12px; background: #FAFAFA; border: 1px solid #E5E7EB; border-radius: 10px; }
    .avatar { width: 38px; height: 38px; border-radius: 50%; background: #FEE2E2; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #6B0000; flex-shrink: 0; }
    .agent-name { font-size: 13px; font-weight: 700; color: #111111; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .agent-role { font-size: 11px; color: #6B7280; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hopital-badge { display: flex; align-items: center; gap: 6px; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 7px 12px; font-size: 12px; font-weight: 600; color: #166534; margin-bottom: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .btn-disconnect { width: 100%; padding: 9px; border: 1.5px solid #6B0000; border-radius: 10px; background: none; color: #6B0000; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 7px; text-decoration: none; transition: all 0.15s ease; font-family: inherit; }
    .btn-disconnect:hover { background: #FDF2F2; }
    .main-content { margin-left: 260px; flex: 1; padding: 36px 40px; min-height: 100vh; }

    /* ══ EN-TÊTE DE PAGE ══ */
    .page-header { margin-bottom: 28px; }
    .page-header h1 { font-size: 26px; font-weight: 800; color: #111111; margin-bottom: 6px; }
    .page-header p { font-size: 14px; color: #111111; }

    /* ══ ALERTES / MESSAGES ══ */
    .alerte-success, .alerte-erreur, .alerte-warning {
        padding: 14px 18px; border-radius: 10px; font-size: 14px; font-weight: 600;
        margin-bottom: 22px; display: flex; align-items: center; justify-content: space-between; gap: 12px;
    }
    .alerte-success { background: #F0FDF4; border: 1.5px solid #BBF7D0; color: #166534; }
    .alerte-erreur  { background: #FEF2F2; border: 1.5px solid #FCA5A5; color: #6B0000; }
    .alerte-warning { background: #FFFBEB; border: 1.5px solid #FCD34D; color: #92400E; }

    /* ══ CARTES STATISTIQUES ══ */
    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
    .stat-card { background: #FFFFFF; border: 1.5px solid #E5E7EB; border-radius: 14px; padding: 18px 20px; }
    .stat-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .stat-label { font-size: 13px; font-weight: 600; color: #111111; }
    .stat-icon { font-size: 18px; width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
    .ic-red { background: #FEE2E2; }
    .ic-org { background: #FEF3C7; }
    .ic-grn { background: #DCFCE7; }
    .stat-number { font-size: 30px; font-weight: 800; color: #111111; display: block; }

    /* ══ SECTIONS ══ */
    .section { background: #FFFFFF; border: 1.5px solid #E5E7EB; border-radius: 14px; padding: 22px 24px; margin-bottom: 28px; }
    .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
    .section-title { font-size: 17px; font-weight: 800; color: #111111; }
    .cnt-badge { background: #FDF2F2; color: #6B0000; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 999px; }

    /* ══ TABLEAUX ══ */
    .table-wrapper { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    thead th { text-align: left; padding: 12px 14px; font-size: 12px; font-weight: 700; color: #111111; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 2px solid #E5E7EB; background: #FAFAFA; }
    tbody td { padding: 14px; border-bottom: 1px solid #F3F4F6; font-size: 14px; color: #111111; }
    tbody tr:last-child td { border-bottom: none; }
    td.vide { text-align: center; color: #111111; padding: 28px; font-style: italic; }

    /* ══ BADGES ══ */
    .badge { display: inline-flex; align-items: center; gap: 5px; font-weight: 700; padding: 5px 12px; border-radius: 999px; font-size: 12px; }
    .badge-groupe { background: #FDF2F2; color: #6B0000; border: 1px solid #FCA5A5; }
    .badge-ok { background: #D1FAE5; color: #065F46; }
    .badge-faible { background: #FEF3C7; color: #92400E; }
    .badge-critique { background: #FFEDD5; color: #9A3412; }
    .badge-vide { background: #6B0000; color: #FFFFFF; }
    .badge-attente { background: #FEF3C7; color: #92400E; }

    /* ══ BOUTONS ══ */
    .btn-submit { background: #6B0000; color: #FFFFFF; border: none; padding: 11px 20px; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-family: inherit; transition: background 0.15s ease; }
    .btn-submit:hover { background: #550000; }
    .btn-submit-full { width: 100%; justify-content: center; }

    /* ══ CARTES GROUPE SANGUIN (dashboard) ══ */
    .groupes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 14px; }
    .groupe-card { position: relative; background: #FFFFFF; border: 1.5px solid #E5E7EB; border-radius: 14px; padding: 18px 14px; text-align: center; }
    .groupe-card.etat-ok       { border-color: #BBF7D0; }
    .groupe-card.etat-faible   { border-color: #FCD34D; }
    .groupe-card.etat-critique { border-color: #FDBA74; }
    .groupe-card.etat-vide     { border-color: #FCA5A5; }
    .groupe-etat { position: absolute; top: 10px; right: 10px; font-size: 9px; font-weight: 800; padding: 3px 8px; border-radius: 999px; letter-spacing: 0.03em; }
    .etat-label-ok       { background: #D1FAE5; color: #065F46; }
    .etat-label-faible   { background: #FEF3C7; color: #92400E; }
    .etat-label-critique { background: #FFEDD5; color: #9A3412; }
    .etat-label-vide      { background: #6B0000; color: #FFFFFF; }
    .groupe-badge { font-size: 14px; font-weight: 800; color: #6B0000; margin-bottom: 10px; margin-top: 6px; }
    .groupe-qty { font-size: 30px; font-weight: 800; line-height: 1; }
    .groupe-qty.ok       { color: #166534; }
    .groupe-qty.faible   { color: #92400E; }
    .groupe-qty.critique { color: #9A3412; }
    .groupe-qty.vide     { color: #6B0000; }
    .groupe-lbl { font-size: 13px; color: #111111; margin-bottom: 10px; }
    .groupe-bar { width: 100%; height: 7px; background: #F3F4F6; border-radius: 99px; overflow: hidden; }
    .groupe-bar-fill { height: 100%; border-radius: 99px; }

    /* ══ NIVEAU DE STOCK (page stock.php) ══ */
    .niveau-bar  { width: 100%; height: 8px; background: #F3F4F6; border-radius: 99px; overflow: hidden; margin-top: 4px; }
    .niveau-fill { height: 100%; border-radius: 99px; }
    .fill-ok       { background: #22C55E; }
    .fill-faible   { background: #F59E0B; }
    .fill-critique { background: #EF4444; }
    .fill-vide     { background: #6B0000; }

    .etat-tag { display: inline-flex; align-items: center; gap: 5px; font-weight: 700; padding: 4px 10px; border-radius: 999px; font-size: 12px; }
    .etat-ok       { background: #D1FAE5; color: #065F46; }
    .etat-faible   { background: #FEF3C7; color: #92400E; }
    .etat-critique { background: #FFEDD5; color: #9A3412; }
    .etat-vide     { background: #6B0000; color: #FFFFFF; }

    .btn-seuil { background: none; border: 1.5px solid #E5E7EB; border-radius: 8px; padding: 6px 12px; font-size: 12px; font-weight: 700; color: #111111; cursor: pointer; font-family: inherit; transition: all 0.15s ease; }
    .btn-seuil:hover { border-color: #6B0000; color: #6B0000; background: #FDF2F2; }

    /* ══ FORMULAIRES (modal seuil) ══ */
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 13px; font-weight: 700; color: #111111; margin-bottom: 8px; }
    .form-group input { width: 100%; padding: 10px 12px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; color: #111111; font-family: inherit; }
    .form-group input:focus { outline: none; border-color: #6B0000; }
</style>

<!-- ══ SIDEBAR HTML ══ -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <svg width="215" height="68" viewBox="0 0 215 68" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M22 46C31.941 46 40 37.941 40 28C40 20.5 33 11 22 2C11 11 4 20.5 4 28C4 37.941 12.059 46 22 46Z" fill="#6B0000"/>
            <rect x="18.5" y="13" width="7" height="22" rx="3" fill="white"/>
            <rect x="11" y="20.5" width="22" height="7" rx="3" fill="white"/>
            <text x="50" y="28" font-family="Arial Black, Arial, sans-serif" font-size="22" font-weight="900" fill="#1a1a1a" letter-spacing="-0.5">E-Sang</text>
            <rect x="50" y="34" width="26" height="14" rx="4" fill="#6B0000"/>
            <text x="56" y="45" font-family="Arial, sans-serif" font-size="9" font-weight="700" fill="white">SB</text>
            <text x="82" y="45" font-family="Arial, sans-serif" font-size="11" font-weight="500" fill="#444444">Dépôt : <?php echo htmlspecialchars($_SESSION['nom_sb'] ?? '—'); ?></text>
        </svg>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Navigation</div>

        <!-- Dashboard -->
        <a href="dashboard.php" class="nav-item <?php echo ($page_active === 'dashboard') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
            Dashboard
        </a>

        <!-- Stock -->
        <a href="stock.php" class="nav-item <?php echo ($page_active === 'stock') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="12" cy="5" rx="9" ry="3"/>
                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
            </svg>
            Stock Interne
        </a>

        <!-- L'ONGLET SORTIES MÉDICALES A ÉTÉ SUPPRIMÉ ICI -->

        <!-- Demandes -->
        <a href="demandes.php" class="nav-item <?php echo ($page_active === 'demandes') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            Demandes
        </a>

        <!-- Alertes avec badge -->
        <a href="alertes.php" class="nav-item <?php echo ($page_active === 'alertes') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            Alertes
            <?php if ($nb_alertes > 0): ?>
                <span class="badge-alerte"><?php echo $nb_alertes; ?></span>
            <?php endif; ?>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="hopital-badge">
            🏥 <?php echo htmlspecialchars($_SESSION['nom_hopital'] ?? '—'); ?>
        </div>

        <div class="agent-info">
            <div class="avatar"><?php echo strtoupper(substr($_SESSION['user_nom'] ?? 'A', 0, 2)); ?></div>
            <div style="overflow: hidden;">
                <div class="agent-name"><?php echo htmlspecialchars($_SESSION['user_nom'] ?? '—'); ?></div>
                <div class="agent-role">Agent de dépôt</div>
            </div>
        </div>

        <a href="../../logout.php" class="btn-disconnect">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Se déconnecter
        </a>
    </div>
</aside>