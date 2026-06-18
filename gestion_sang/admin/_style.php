<?php
// Fichier de styles partagés — inclure dans chaque page admin
// Usage: définit $shared_css à injecter dans <style>
$shared_css = <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

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
    overflow-y: auto;
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
}

.nav-item:hover { background: #FEF2F2; color: #8B0000; border-color: #8B0000; }
.nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }
.nav-item.active { background: #FEF2F2; color: #8B0000; border-color: #8B0000; }

.nav-item.action {
    background: #8B0000; color: #FFFFFF;
    font-weight: 700; font-size: 15px;
    padding: 14px 18px; border-radius: 12px;
    border: none; margin-bottom: 6px;
}
.nav-item.action:hover { background: #6B0000; color: #FFFFFF; }
.nav-item.action svg { width: 20px; height: 20px; }

.sidebar-footer { padding: 16px; border-top: 1px solid #E5E7EB; }

.admin-info { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }

.avatar {
    width: 38px; height: 38px; border-radius: 50%;
    background: #FEE2E2;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; color: #8B0000; flex-shrink: 0;
}

.admin-name { font-size: 13px; font-weight: 700; color: #111111; }
.admin-type { font-size: 11px; color: #666; font-weight: 500; }

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

/* ══ STATS ══ */
.stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 48px; }

.stat-card {
    background: #FFFFFF; border: 2px solid #E5E7EB;
    border-radius: 20px; padding: 28px 24px;
    display: flex; flex-direction: column; gap: 14px;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.stat-card:hover { transform: translateY(-5px); box-shadow: 0 12px 20px -5px rgba(0,0,0,0.08); }

.stat-card-header { display: flex; align-items: center; justify-content: space-between; }
.stat-label { font-size: 13px; font-weight: 800; color: #111111; text-transform: uppercase; letter-spacing: 0.5px; }

.stat-icon {
    font-size: 22px; display: flex; align-items: center; justify-content: center;
    width: 46px; height: 46px; border-radius: 12px; border: 2px solid transparent;
}

.stat-number { font-size: 52px; font-weight: 800; color: #111111; line-height: 1; letter-spacing: -2px; }

.ic-1 { background: #F3F4F6; color: #111111; border-color: #D1D5DB; }
.ic-2 { background: #FFF7ED; color: #EA580C; border-color: #FED7AA; }
.ic-3 { background: #F0FDF4; color: #16A34A; border-color: #BBF7D0; }
.ic-4 { background: #FEF2F2; color: #8B0000; border-color: #FCA5A5; }

/* ══ SECTIONS / BOITES ══ */
.section { background: #FFFFFF; border: 2px solid #E5E7EB; border-radius: 20px; padding: 30px; margin-bottom: 28px; }

.section-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 26px; padding-bottom: 18px; border-bottom: 2px solid #F3F4F6;
}

.section-title { font-size: 20px; font-weight: 800; color: #111111; display: flex; align-items: center; gap: 12px; }
.section-title::before { content: ''; display: block; width: 5px; height: 24px; background: #8B0000; border-radius: 99px; }

.section-link { font-size: 13px; color: #8B0000; font-weight: 700; text-decoration: none; }
.section-link:hover { text-decoration: underline; }

/* ══ GRILLE 2 COL ══ */
.grille-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; }
.grille-2-asym { display: grid; grid-template-columns: 1.6fr 1fr; gap: 28px; align-items: flex-start; }

/* ══ TABLEAU ══ */
.table-wrapper { border: 2px solid #E5E7EB; border-radius: 14px; overflow: hidden; }

table { width: 100%; border-collapse: collapse; font-size: 14px; }
table thead { background: #F9FAFB; }
table th { text-align: left; color: #111111; font-weight: 800; padding: 18px 20px; text-transform: uppercase; letter-spacing: 0.5px; font-size: 12px; border-bottom: 2px solid #E5E7EB; }
table td { padding: 18px 20px; border-bottom: 2px solid #F3F4F6; color: #111111; font-weight: 600; transition: background 0.15s ease; }
table tr:last-child td { border-bottom: none; }
table tbody tr:hover td { background: #F9FAFB; }

.vide { text-align: center; color: #444444; font-weight: 600; padding: 60px !important; font-size: 15px; }

/* ══ BADGES ══ */
.badge { display: inline-flex; align-items: center; font-weight: 700; padding: 6px 14px; border-radius: 999px; font-size: 12px; }
.badge-groupe   { background: #111111; color: #FFFFFF; }
.badge-attente  { background: #FFF7ED; color: #C2410C; border: 2px solid #FED7AA; }
.badge-acceptee { background: #F0FDF4; color: #166534; border: 2px solid #BBF7D0; }
.badge-accepte  { background: #F0FDF4; color: #166534; border: 2px solid #BBF7D0; }
.badge-refusee  { background: #FEF2F2; color: #8B0000; border: 2px solid #FCA5A5; }
.badge-refuse   { background: #FEF2F2; color: #8B0000; border: 2px solid #FCA5A5; }
.badge-verifie  { background: #F0FDF4; color: #166534; border: 2px solid #BBF7D0; }
.badge-non-verifie { background: #FFF7ED; color: #C2410C; border: 2px solid #FED7AA; }
.badge-wilaya   { background: #EFF6FF; color: #1D4ED8; border: 2px solid #BFDBFE; }

/* ══ BOUTONS ══ */
.btn-del {
    background: #FEF2F2; color: #8B0000;
    border: 2px solid #FCA5A5; border-radius: 10px;
    padding: 6px 14px; font-size: 12px; font-weight: 700;
    cursor: pointer; text-decoration: none; display: inline-block;
    transition: all 0.15s ease;
}
.btn-del:hover { background: #FEE2E2; border-color: #8B0000; }

/* ══ FORMULAIRE ══ */
.form-sep {
    font-size: 10px; font-weight: 800; color: #111111;
    text-transform: uppercase; letter-spacing: 0.6px;
    margin: 20px 0 14px;
    padding-bottom: 8px; border-bottom: 1.5px solid #F3F4F6;
}

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
    width: 100%; padding: 14px;
    background: #8B0000; color: #FFFFFF;
    border: none; border-radius: 12px;
    font-size: 15px; font-weight: 700;
    cursor: pointer; margin-top: 8px;
    transition: all 0.2s;
    box-shadow: 0 4px 16px rgba(139,0,0,0.28);
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-submit:hover { background: #6B0000; transform: translateY(-1px); }
CSS;
?>
