<?php
// ══ SIDEBAR COMMUNE — DONNEUR ══
// $page_active doit être défini avant l'include
// $donneur et $citoyen doivent être disponibles
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
        width: 260px; min-width: 260px;
        background: #FFFFFF;
        border-right: 1px solid #E5E7EB;
        display: flex; flex-direction: column;
        position: fixed; top: 0; left: 0;
        height: 100vh; z-index: 100;
    }

    .sidebar-logo {
        display: flex; align-items: center;
        padding: 12px 20px 8px;
        border-bottom: 1px solid #E5E7EB;
    }

    .sidebar-nav {
        flex: 1; padding: 10px 12px;
        display: flex; flex-direction: column; gap: 4px;
    }

    .nav-label {
        font-size: 10px; font-weight: 700; color: #111111;
        letter-spacing: 0.08em; text-transform: uppercase;
        padding: 8px 10px 4px;
    }

    .nav-item {
        display: flex; align-items: center; gap: 10px;
        padding: 11px 14px; border-radius: 10px;
        font-size: 14px; font-weight: 600; color: #111111;
        text-decoration: none;
        border: 1.5px solid #E5E7EB; background: #FFFFFF;
        width: 100%; text-align: left; cursor: pointer;
        transition: all 0.15s ease;
        margin-bottom: 4px;
    }

    .nav-item:hover { background: #FEF2F2; color: #8B0000; border-color: #8B0000; }
    .nav-item.active { background: #FEF2F2; color: #8B0000; border-color: #8B0000; font-weight: 700; }
    .nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }

    .sidebar-footer { padding: 16px; border-top: 1px solid #E5E7EB; }

    .user-info { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }

    .avatar {
        width: 38px; height: 38px; border-radius: 50%;
        background: #FEE2E2;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 700; color: #8B0000; flex-shrink: 0;
    }

    .user-name { font-size: 13px; font-weight: 700; color: #111111; }
    .user-type { font-size: 11px; color: #666; font-weight: 500; }

    .btn-disconnect {
        width: 100%; padding: 9px;
        border: 1.5px solid #8B0000; border-radius: 10px;
        background: none; color: #8B0000;
        font-size: 13px; font-weight: 600; cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 7px;
        text-decoration: none; transition: all 0.15s ease;
    }
    .btn-disconnect:hover { background: #FEF2F2; }

    /* ══ CONTENU PRINCIPAL ══ */
    .main-content { margin-left: 260px; flex: 1; padding: 40px; min-height: 100vh; }

    .page-header { margin-bottom: 36px; }
    .page-header h1 { font-size: 32px; font-weight: 800; color: #111111; letter-spacing: -1px; }
    .page-header p  { color: #444444; font-size: 15px; margin-top: 6px; }

    /* ══ ALERTES ══ */
    .alerte-warning {
        background: #FFFBEB; border: 2px solid #FCD34D;
        color: #92400E; border-radius: 14px;
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
    .stats { display: grid; gap: 24px; margin-bottom: 40px; }
    .stats-3 { grid-template-columns: repeat(3, 1fr); }
    .stats-4 { grid-template-columns: repeat(4, 1fr); }

    .stat-card {
        background: #FFFFFF; border: 2px solid #E5E7EB;
        border-radius: 20px; padding: 24px;
        display: flex; flex-direction: column; gap: 14px;
        transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
    }
    .stat-card:hover { transform: translateY(-5px); box-shadow: 0 12px 20px -5px rgba(0,0,0,0.08); }
    .stat-card.c1:hover { border-color: #8B0000; }
    .stat-card.c2:hover { border-color: #16A34A; }
    .stat-card.c3:hover { border-color: #EA580C; }
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
    .ic-grn  { background: #F0FDF4; border-color: #BBF7D0; }
    .ic-org  { background: #FFF7ED; border-color: #FED7AA; }
    .ic-blu  { background: #EFF6FF; border-color: #BFDBFE; }

    /* ══ PROFIL CARD ══ */
    .profil-card {
        background: linear-gradient(135deg, #8B0000, #6B0000);
        border-radius: 20px; padding: 28px;
        margin-bottom: 32px;
        display: flex; align-items: center; gap: 20px;
        border: 2px solid #6B0000;
    }
    .profil-avatar {
        width: 64px; height: 64px; border-radius: 50%;
        background: rgba(255,255,255,0.15);
        display: flex; align-items: center; justify-content: center;
        font-size: 28px; flex-shrink: 0;
    }
    .profil-info h2 { font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 6px; }
    .profil-info p  { font-size: 13px; color: rgba(255,255,255,0.7); margin-bottom: 10px; }
    .badge-groupe-profil {
        background: rgba(255,255,255,0.2); color: #fff;
        padding: 5px 16px; border-radius: 999px;
        font-size: 13px; font-weight: 700; display: inline-block;
        border: 1.5px solid rgba(255,255,255,0.3);
    }
    .badge-non-verifie-profil {
        background: rgba(255,200,0,0.2); color: #FDE68A;
        padding: 5px 16px; border-radius: 999px;
        font-size: 13px; font-weight: 700; display: inline-block;
        border: 1.5px solid rgba(253,230,138,0.4);
    }

    /* ══ SECTION ══ */
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

    .section-link { font-size: 13px; color: #8B0000; font-weight: 700; text-decoration: none; }
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
    .badge-groupe  { background: #111111; color: #FFFFFF; }
    .badge-attente { background: #FFF7ED; color: #C2410C; border: 2px solid #FED7AA; }
    .badge-accepte { background: #F0FDF4; color: #166534; border: 2px solid #BBF7D0; }
    .badge-refuse  { background: #FEF2F2; color: #8B0000; border: 2px solid #FCA5A5; }

    /* ══ CHAT ══ */
    .chat-wrapper {
        background: #FFFFFF; border: 2px solid #E5E7EB;
        border-radius: 20px; overflow: hidden;
    }
    .chat-messages {
        height: 460px; overflow-y: auto;
        padding: 24px;
        display: flex; flex-direction: column; gap: 16px;
    }
    .message { display: flex; gap: 10px; align-items: flex-end; }
    .message.moi { flex-direction: row-reverse; }

    .msg-avatar {
        width: 36px; height: 36px; border-radius: 50%;
        background: #F3F4F6;
        display: flex; align-items: center; justify-content: center;
        font-size: 16px; flex-shrink: 0;
        border: 2px solid #E5E7EB;
    }
    .message.moi .msg-avatar { background: #FEE2E2; border-color: #FCA5A5; }

    .bulle {
        max-width: 65%; padding: 12px 16px;
        border-radius: 16px; font-size: 14px; line-height: 1.5; font-weight: 500;
    }
    .message.autre .bulle {
        background: #F3F4F6; color: #111111;
        border-bottom-left-radius: 4px;
    }
    .message.moi .bulle {
        background: #8B0000; color: #fff;
        border-bottom-right-radius: 4px;
    }
    .msg-info { font-size: 11px; color: #9CA3AF; margin-top: 5px; font-weight: 500; }
    .message.moi .msg-info { text-align: right; }

    .vide-chat {
        text-align: center; color: #9CA3AF;
        font-size: 14px; font-weight: 500;
        margin: auto; padding: 40px;
    }

    .chat-input-bar {
        border-top: 2px solid #F3F4F6;
        padding: 16px 20px;
        display: flex; gap: 10px; align-items: center;
        background: #FAFAFA;
    }
    .chat-input-bar input {
        flex: 1; padding: 12px 16px;
        border: 1.5px solid #E5E7EB; border-radius: 12px;
        font-size: 14px; outline: none;
        background: #fff; font-family: inherit; color: #111111;
        transition: all 0.15s;
    }
    .chat-input-bar input:focus {
        border-color: #8B0000;
        box-shadow: 0 0 0 3px rgba(139,0,0,0.07);
    }
    .chat-input-bar button {
        padding: 12px 22px;
        background: #8B0000; color: #fff;
        border: none; border-radius: 12px;
        font-size: 14px; font-weight: 700;
        cursor: pointer; font-family: inherit;
        transition: all 0.2s;
        display: flex; align-items: center; gap: 8px;
    }
    .chat-input-bar button:hover { background: #6B0000; }

    .groupe-badge-header {
        width: 52px; height: 52px; border-radius: 50%;
        background: #8B0000; color: #fff;
        font-size: 16px; font-weight: 800;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; border: 3px solid #FCA5A5;
    }
</style>

<!-- ══ SIDEBAR HTML ══ -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <svg width="210" height="70" viewBox="0 0 210 70" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M22 46C31.941 46 40 37.941 40 28C40 20.5 33 11 22 2C11 11 4 20.5 4 28C4 37.941 12.059 46 22 46Z" fill="#8B0000"/>
            <rect x="18.5" y="13" width="7" height="22" rx="3" fill="white"/>
            <rect x="11" y="20.5" width="22" height="7" rx="3" fill="white"/>
            <text x="50" y="30" font-family="Arial Black, Arial, sans-serif" font-size="26" font-weight="900" fill="#1a1a1a" letter-spacing="-0.5">E-Sang</text>
            <text x="50" y="50" font-family="Arial, sans-serif" font-size="12" font-weight="500" fill="#444444">Espace Donneur</text>
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

        <a href="dons.php" class="nav-item <?php echo ($page_active==='dons') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            Mes dons
        </a>

        <a href="chat.php" class="nav-item <?php echo ($page_active==='chat') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            Chat groupe
        </a>

        <a href="analyses.php" class="nav-item <?php echo ($page_active==='analyses') ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
            Mes analyses
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar">
                <?php
                if (isset($citoyen) && $citoyen) {
                    echo strtoupper(substr($citoyen['prenom'], 0, 1) . substr($citoyen['nom'], 0, 1));
                } else {
                    echo 'DN';
                }
                ?>
            </div>
            <div>
                <div class="user-name">
                    <?php echo isset($citoyen) && $citoyen ? htmlspecialchars($citoyen['prenom'] . ' ' . $citoyen['nom']) : 'Donneur'; ?>
                </div>
                <div class="user-type">Espace donneur</div>
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
