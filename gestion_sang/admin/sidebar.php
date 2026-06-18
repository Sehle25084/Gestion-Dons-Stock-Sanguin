<?php
// ══ SIDEBAR COMMUNE — ADMIN ══
// Utilisation : include 'sidebar.php'; avec $page_active défini avant
// Ex: $page_active = 'dashboard';
?>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        background: #FAFAFA;
        color: #111111;
        font-size: 15px;
        line-height: 1.5;
        display: flex;
        min-height: 100vh;
    }

    /* ══ SIDEBAR ══ */
    .sidebar {
        width: 260px;
        min-width: 260px;
        background: #FFFFFF;
        border-right: 1px solid #E5E7EB;
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0; left: 0;
        height: 100vh;
        z-index: 100;
    }

    .sidebar-logo {
        display: flex;
        align-items: center;
        padding: 12px 20px 8px;
        border-bottom: 1px solid #E5E7EB;
    }

    .sidebar-nav {
        flex: 1;
        padding: 10px 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .nav-label {
        font-size: 10px;
        font-weight: 700;
        color: #111111;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 8px 10px 4px;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 11px 14px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        color: #111111;
        text-decoration: none;
        border: 1.5px solid #E5E7EB;
        background: #FFFFFF;
        width: 100%;
        text-align: left;
        cursor: pointer;
        transition: all 0.15s ease;
        margin-bottom: 4px;
    }

    .nav-item:hover { background: #FEF2F2; color: #8B0000; border-color: #8B0000; }

    .nav-item.active {
        background: #FEF2F2;
        color: #8B0000;
        border-color: #8B0000;
        font-weight: 700;
    }

    .nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }

    .sidebar-footer { padding: 16px; border-top: 1px solid #E5E7EB; }

    .hospital-info { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }

    .avatar {
        width: 38px; height: 38px;
        border-radius: 50%;
        background: #FEE2E2;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 700; color: #8B0000;
        flex-shrink: 0;
    }

    .hospital-name { font-size: 13px; font-weight: 700; color: #111111; }
    .hospital-type { font-size: 11px; color: #111111; font-weight: 500; }

    .btn-disconnect {
        width: 100%; padding: 9px;
        border: 1.5px solid #8B0000;
        border-radius: 10px;
        background: none; color: #8B0000;
        font-size: 13px; font-weight: 600;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 7px;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .btn-disconnect:hover { background: #FEF2F2; }

    /* ══ CONTENU PRINCIPAL ══ */
    .main-content {
        margin-left: 260px;
        flex: 1;
        padding: 40px;
        min-height: 100vh;
    }

    .page-header { margin-bottom: 36px; }
    .page-header h1 { font-size: 32px; font-weight: 800; color: #111111; letter-spacing: -1px; }
    .page-header p  { color: #444444; font-size: 15px; margin-top: 6px; }

    /* ══ ALERTES ══ */
    .alerte-success {
        background: #F0FDF4; border: 2px solid #BBF7D0;
        color: #166534; border-radius: 14px;
        padding: 14px 20px; font-size: 14px; font-weight: 600;
        margin-bottom: 28px; display: flex; align-items: center; gap: 10px;
    }
    .alerte-erreur {
        background: #FEF2F2; border: 2px solid #FECACA;
        color: #8B0000; border-radius: 14px;
        padding: 14px 20px; font-size: 14px; font-weight: 600;
        margin-bottom: 28px; display: flex; align-items: center; gap: 10px;
    }
    .alerte-info {
        background: #EFF6FF; border: 2px solid #BFDBFE;
        color: #1E40AF; border-radius: 14px;
        padding: 14px 20px; font-size: 14px; font-weight: 600;
        margin-bottom: 28px; display: flex; align-items: center; gap: 10px;
    }

    /* ══ CARTES STATS ══ */
    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 40px; }

    .stat-card {
        background: #FFFFFF; border: 2px solid #E5E7EB;
        border-radius: 20px; padding: 24px;
        display: flex; flex-direction: column; gap: 14px;
        transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
    }

    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 12px 20px -5px rgba(0,0,0,0.08); }
    .stat-card.c1:hover { border-color: #8B0000; }
    .stat-card.c2:hover { border-color: #EA580C; }
    .stat-card.c3:hover { border-color: #16A34A; }
    .stat-card.c4:hover { border-color: #1D4ED8; }

    .stat-card-header { display: flex; align-items: center; justify-content: space-between; }

    .stat-label { font-size: 12px; font-weight: 800; color: #111111; text-transform: uppercase; letter-spacing: 0.5px; }

    .stat-icon {
        width: 44px; height: 44px; border-radius: 12px;
        border: 2px solid transparent;
        display: flex; align-items: center; justify-content: center;
        font-size: 20px;
    }

    .stat-number { font-size: 48px; font-weight: 800; color: #111111; line-height: 1; letter-spacing: -2px; }

    .ic-red  { background: #FEF2F2; border-color: #FCA5A5; }
    .ic-org  { background: #FFF7ED; border-color: #FED7AA; }
    .ic-grn  { background: #F0FDF4; border-color: #BBF7D0; }
    .ic-blu  { background: #EFF6FF; border-color: #BFDBFE; }

    /* ══ SECTION / CARD ══ */
    .section {
        background: #FFFFFF; border: 2px solid #E5E7EB;
        border-radius: 20px; padding: 28px;
        margin-bottom: 24px;
    }

    .section-header {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 22px; padding-bottom: 16px;
        border-bottom: 2px solid #F3F4F6;
    }

    .section-title {
        font-size: 18px; font-weight: 800; color: #111111;
        display: flex; align-items: center; gap: 12px;
    }

    .section-title::before {
        content: ''; display: block;
        width: 5px; height: 22px;
        background: #8B0000; border-radius: 99px;
    }

    .section-link { font-size: 13px; color: #8B0000; font-weight: 600; text-decoration: none; }
    .section-link:hover { text-decoration: underline; }

    /* ══ TABLEAU ══ */
    .table-wrapper { border: 2px solid #E5E7EB; border-radius: 14px; overflow: hidden; }

    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    table thead { background: #F9FAFB; }
    table th {
        text-align: left; color: #111111; font-weight: 800;
        padding: 14px 18px; text-transform: uppercase;
        letter-spacing: 0.5px; font-size: 11px;
        border-bottom: 2px solid #E5E7EB;
    }
    table td {
        padding: 14px 18px; border-bottom: 2px solid #F3F4F6;
        color: #111111; font-weight: 600;
        transition: background 0.15s ease;
    }
    table tr:last-child td { border-bottom: none; }
    table tbody tr:hover td { background: #F9FAFB; }
    .vide { text-align: center; color: #6B7280; padding: 48px !important; font-weight: 500; font-size: 14px; }

    /* ══ BADGES ══ */
    .badge { display: inline-flex; align-items: center; font-weight: 700; padding: 5px 12px; border-radius: 999px; font-size: 11px; }
    .badge-groupe      { background: #111111; color: #FFFFFF; }
    .badge-attente     { background: #FFF7ED; color: #C2410C; border: 2px solid #FED7AA; }
    .badge-acceptee, .badge-accepte { background: #F0FDF4; color: #166534; border: 2px solid #BBF7D0; }
    .badge-refusee, .badge-refuse   { background: #FEF2F2; color: #8B0000; border: 2px solid #FCA5A5; }
    .badge-verifie     { background: #F0FDF4; color: #166534; border: 2px solid #BBF7D0; }
    .badge-non-verifie { background: #FFF7ED; color: #C2410C; border: 2px solid #FED7AA; }
    .badge-wilaya      { background: #EFF6FF; color: #1D4ED8; border: 2px solid #BFDBFE; }

    /* ══ BOUTONS ══ */
    .btn-del {
        background: #FEF2F2; color: #8B0000;
        border: 1.5px solid #FCA5A5; border-radius: 8px;
        padding: 6px 14px; font-size: 12px; font-weight: 700;
        cursor: pointer; text-decoration: none;
        display: inline-block; transition: all 0.15s;
    }
    .btn-del:hover { background: #FEE2E2; }

    /* ══ FORMULAIRE ══ */
    .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }

    .form-group label {
        font-size: 11px; font-weight: 800; color: #111111;
        text-transform: uppercase; letter-spacing: 0.5px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 11px 14px;
        border: 1.5px solid #E5E7EB; border-radius: 10px;
        font-size: 14px; background: #FFFFFF;
        outline: none; font-family: inherit; color: #111111;
        transition: border-color 0.15s; width: 100%;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus { border-color: #8B0000; box-shadow: 0 0 0 3px rgba(139,0,0,0.07); }

    .btn-submit {
        width: 100%; padding: 13px;
        background: #8B0000; color: #FFFFFF;
        border: none; border-radius: 10px;
        font-size: 14px; font-weight: 700;
        cursor: pointer; transition: all 0.2s;
        box-shadow: 0 4px 14px rgba(139,0,0,0.25);
    }
    .btn-submit:hover { background: #6B0000; }

    /* ══ GRILLE ══ */
    .grille-2-asym { display: grid; grid-template-columns: 1.6fr 1fr; gap: 28px; align-items: flex-start; }
</style>

<!-- ══ SIDEBAR HTML ══ -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <svg width="210" height="70" viewBox="0 0 210 70" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M22 46C31.941 46 40 37.941 40 28C40 20.5 33 11 22 2C11 11 4 20.5 4 28C4 37.941 12.059 46 22 46Z" fill="#8B0000"/>
            <rect x="18.5" y="13" width="7" height="22" rx="3" fill="white"/>
            <rect x="11" y="20.5" width="22" height="7" rx="3" fill="white"/>
            <text x="50" y="30" font-family="Arial Black, Arial, sans-serif" font-size="26" font-weight="900" fill="#1a1a1a" letter-spacing="-0.5">E-Sang</text>
            <text x="50" y="50" font-family="Arial, sans-serif" font-size="12" font-weight="500" fill="#444444">Administrateur</text>
        </svg>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Navigation</div>

        <a href="dashboard.php" class="nav-item <?php echo ($page_active==='dashboard') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
            Dashboard
        </a>

        <a href="banques.php" class="nav-item <?php echo ($page_active==='banques') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="22" x2="21" y2="22"/>
                <line x1="6" y1="18" x2="6" y2="11"/><line x1="10" y1="18" x2="10" y2="11"/>
                <line x1="14" y1="18" x2="14" y2="11"/><line x1="18" y1="18" x2="18" y2="11"/>
                <polygon points="12 2 2 7 22 7"/>
            </svg>
            Banques de sang
        </a>

        <a href="hopitaux.php" class="nav-item <?php echo ($page_active==='hopitaux') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Hôpitaux
        </a>

        <a href="donneurs.php" class="nav-item <?php echo ($page_active==='donneurs') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Donneurs
        </a>

        <a href="dons.php" class="nav-item <?php echo ($page_active==='dons') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            Dons
        </a>

        <a href="demandes.php" class="nav-item <?php echo ($page_active==='demandes') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            Demandes
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="hospital-info">
            <div class="avatar">AD</div>
            <div>
                <div class="hospital-name">Administrateur</div>
                <div class="hospital-type">Espace d'administration</div>
            </div>
        </div>
        <a href="../logout.php" class="btn-disconnect">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Se déconnecter
        </a>
    </div>
</aside>
