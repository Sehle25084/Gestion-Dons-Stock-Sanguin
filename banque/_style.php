<?php
// ════════════════════════════════════════════════════
// E-SANG — Styles partagés BANQUE
// Inclure AVANT sidebar.php dans chaque page banque
// Usage : require_once '_style.php'; puis utiliser $shared_css
// ════════════════════════════════════════════════════
$shared_css = <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

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

/* ══════════════════════════════════════
   SIDEBAR
   ══════════════════════════════════════ */
.sidebar {
    width: 270px; min-width: 270px;
    background: #FFFFFF;
    border-right: 2px solid #E5E7EB;
    display: flex; flex-direction: column;
    position: fixed; top: 0; left: 0;
    height: 100vh; z-index: 100;
}

.sidebar-logo {
    display: flex; align-items: center;
    padding: 18px 22px 14px;
    border-bottom: 2px solid #E5E7EB;
}

.sidebar-nav {
    flex: 1; padding: 14px 14px;
    display: flex; flex-direction: column; gap: 5px;
    overflow-y: auto;
}

.nav-label {
    font-size: 11px; font-weight: 800; color: #6B7280;
    letter-spacing: 0.1em; text-transform: uppercase;
    padding: 10px 12px 6px;
}

.nav-item {
    display: flex; align-items: center; gap: 11px;
    padding: 12px 15px; border-radius: 10px;
    font-size: 15px; font-weight: 600; color: #111111;
    text-decoration: none;
    border: 1.5px solid transparent;
    background: #FFFFFF;
    width: 100%; text-align: left; cursor: pointer;
    transition: all 0.15s ease;
}
.nav-item:hover { background: #FEF2F2; color: #8B0000; }
.nav-item svg { width: 19px; height: 19px; flex-shrink: 0; }

.nav-item.active {
    background: #FEF2F2;
    color: #8B0000;
    border-color: #8B0000;
    font-weight: 800;
}

/* ── Footer sidebar (admin connecté) ── */
.sidebar-footer { padding: 16px; border-top: 2px solid #E5E7EB; }

.admin-info {
    display: flex; align-items: center; gap: 11px;
    margin-bottom: 14px;
    padding: 10px;
    background: #FEF2F2;
    border-radius: 12px;
    border: 1.5px solid #FCA5A5;
}

.avatar {
    width: 42px; height: 42px; border-radius: 50%;
    background: #8B0000;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 800; color: #FFFFFF;
    flex-shrink: 0;
}

.admin-name { font-size: 14px; font-weight: 800; color: #111111; }
.admin-type { font-size: 12px; color: #8B0000; font-weight: 600; }

.btn-disconnect {
    width: 100%; padding: 11px;
    border: 1.5px solid #8B0000; border-radius: 10px;
    background: none; color: #8B0000;
    font-size: 14px; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    text-decoration: none; transition: all 0.15s ease;
    font-family: inherit;
}
.btn-disconnect:hover { background: #FEF2F2; }

/* ══════════════════════════════════════
   CONTENU PRINCIPAL
   ══════════════════════════════════════ */
.main-content {
    margin-left: 270px;
    flex: 1;
    padding: 40px 48px;
    min-height: 100vh;
}

/* ── Bandeau "Admin connecté" en haut de page ── */
.top-bar {
    background: #FFFFFF;
    border: 2px solid #E5E7EB;
    border-radius: 14px;
    padding: 14px 22px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.top-bar-user {
    display: flex; align-items: center; gap: 12px;
}

.top-bar-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: #FEF2F2;
    border: 2px solid #FCA5A5;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 800; color: #8B0000;
    flex-shrink: 0;
}

.top-bar-info .top-bar-name {
    font-size: 16px; font-weight: 800; color: #111111;
}
.top-bar-info .top-bar-role {
    font-size: 13px; color: #6B7280; font-weight: 600;
}

.top-bar-date {
    font-size: 14px; color: #444444; font-weight: 600;
    text-transform: capitalize;
}

/* ── Header de page ── */
.page-header { margin-bottom: 32px; }
.page-header h1 {
    font-size: 36px;
    font-weight: 900;
    color: #111111;
    letter-spacing: -1.2px;
}
.page-header p {
    color: #444444;
    font-size: 16px;
    margin-top: 8px;
    font-weight: 500;
}

/* ══════════════════════════════════════
   ALERTES
   ══════════════════════════════════════ */
.alerte-success {
    background: #F0FDF4; border: 2px solid #BBF7D0;
    color: #166534; border-radius: 14px;
    padding: 16px 22px; font-size: 15px; font-weight: 700;
    margin-bottom: 28px; display: flex; align-items: center; gap: 10px;
}
.alerte-erreur {
    background: #FEF2F2; border: 2px solid #FCA5A5;
    color: #8B0000; border-radius: 14px;
    padding: 16px 22px; font-size: 15px; font-weight: 700;
    margin-bottom: 28px; display: flex; align-items: center; gap: 10px;
}
.alerte-info {
    background: #EFF6FF; border: 2px solid #BFDBFE;
    color: #1E40AF; border-radius: 14px;
    padding: 16px 22px; font-size: 15px; font-weight: 700;
    margin-bottom: 28px; display: flex; align-items: center; gap: 10px;
}

/* ══════════════════════════════════════
   STATS — Format demandé par pi.docx :
   "titre en haut en gras, chiffre en bas"
   ══════════════════════════════════════ */
.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 22px;
    margin-bottom: 40px;
}

.stat-card {
    background: #FFFFFF;
    border: 2px solid #E5E7EB;
    border-radius: 18px;
    padding: 24px 26px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px -8px rgba(0,0,0,0.10);
    border-color: #8B0000;
}

.stat-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

/* TITRE EN HAUT EN GRAS */
.stat-label {
    font-size: 15px;
    font-weight: 800;
    color: #111111;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    line-height: 1.3;
    flex: 1;
}

.stat-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    border: 2px solid transparent;
    flex-shrink: 0;
}

/* CHIFFRE EN BAS */
.stat-number {
    font-size: 56px;
    font-weight: 900;
    color: #111111;
    line-height: 1;
    letter-spacing: -2.5px;
}

.ic-red  { background: #FEF2F2; border-color: #FCA5A5; color: #8B0000; }
.ic-org  { background: #FFF7ED; border-color: #FED7AA; color: #EA580C; }
.ic-grn  { background: #F0FDF4; border-color: #BBF7D0; color: #16A34A; }
.ic-blu  { background: #EFF6FF; border-color: #BFDBFE; color: #1D4ED8; }

/* ══════════════════════════════════════
   SECTION (boîtes blanches)
   ══════════════════════════════════════ */
.section {
    background: #FFFFFF;
    border: 2px solid #E5E7EB;
    border-radius: 18px;
    padding: 28px 30px;
    margin-bottom: 28px;
}

.section-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px; padding-bottom: 18px;
    border-bottom: 2px solid #F3F4F6;
}

.section-title {
    font-size: 20px;
    font-weight: 800;
    color: #111111;
    display: flex; align-items: center; gap: 12px;
}
.section-title::before {
    content: '';
    display: block;
    width: 5px; height: 24px;
    background: #8B0000;
    border-radius: 99px;
}

.section-link {
    font-size: 14px; color: #8B0000;
    font-weight: 700; text-decoration: none;
}
.section-link:hover { text-decoration: underline; }

/* ══════════════════════════════════════
   TABLEAUX
   ══════════════════════════════════════ */
.table-wrapper {
    border: 2px solid #E5E7EB;
    border-radius: 14px;
    overflow: hidden;
}

table { width: 100%; border-collapse: collapse; font-size: 14px; }
table thead { background: #F9FAFB; }
table th {
    text-align: left;
    color: #111111;
    font-weight: 800;
    padding: 16px 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 12px;
    border-bottom: 2px solid #E5E7EB;
}
table td {
    padding: 16px 20px;
    border-bottom: 1.5px solid #F3F4F6;
    color: #111111;
    font-weight: 600;
    transition: background 0.15s ease;
    font-size: 14px;
}
table tr:last-child td { border-bottom: none; }
table tbody tr:hover td { background: #FAFAFA; }

.vide {
    text-align: center;
    color: #6B7280;
    padding: 60px !important;
    font-weight: 600;
    font-size: 15px;
}

/* ══════════════════════════════════════
   BADGES
   ══════════════════════════════════════ */
.badge {
    display: inline-flex;
    align-items: center;
    font-weight: 800;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 12px;
    letter-spacing: 0.3px;
    white-space: nowrap;
}
.badge-groupe       { background: #111111; color: #FFFFFF; }
.badge-attente      { background: #FFF7ED; color: #C2410C; border: 2px solid #FED7AA; }
.badge-acceptee,
.badge-accepte      { background: #F0FDF4; color: #166534; border: 2px solid #BBF7D0; }
.badge-refusee,
.badge-refuse       { background: #FEF2F2; color: #8B0000; border: 2px solid #FCA5A5; }
.badge-verifie      { background: #F0FDF4; color: #166534; border: 2px solid #BBF7D0; }
.badge-non-verifie  { background: #FFF7ED; color: #C2410C; border: 2px solid #FED7AA; }
.badge-wilaya       { background: #EFF6FF; color: #1D4ED8; border: 2px solid #BFDBFE; }

/* ══════════════════════════════════════
   BOUTONS
   ══════════════════════════════════════ */
.btn-submit {
    padding: 13px 22px;
    background: #8B0000;
    color: #FFFFFF;
    border: none;
    border-radius: 11px;
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
    box-shadow: 0 4px 14px rgba(139,0,0,0.28);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-submit:hover {
    background: #6B0000;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(139,0,0,0.35);
}

.btn-submit-full {
    width: 100%;
    margin-top: 12px;
}

/* Bouton MODIFIER (bleu/info) */
.btn-edit {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1.5px solid #BFDBFE;
    border-radius: 9px;
    padding: 7px 14px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.15s;
    margin-right: 6px;
    font-family: inherit;
    white-space: nowrap;
}
.btn-edit:hover {
    background: #DBEAFE;
    border-color: #1D4ED8;
}

/* Bouton SUPPRIMER */
.btn-del {
    background: #FEF2F2;
    color: #8B0000;
    border: 1.5px solid #FCA5A5;
    border-radius: 9px;
    padding: 7px 14px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.15s;
    font-family: inherit;
    white-space: nowrap;
}
.btn-del:hover {
    background: #FEE2E2;
    border-color: #8B0000;
}

/* ══════════════════════════════════════
   CELLULE D'ACTIONS (table) — boutons côte à côte
   Empêche l'empilement vertical quand cellule étroite
   Usage : <td><div class="actions-cell"> ... </div></td>
   ══════════════════════════════════════ */
.actions-cell {
    display: flex;
    flex-wrap: nowrap;
    gap: 6px;
    align-items: center;
}

/* ══════════════════════════════════════
   FORMULAIRES
   ══════════════════════════════════════ */
.form-sep {
    font-size: 11px;
    font-weight: 800;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin: 20px 0 14px;
    padding-bottom: 8px;
    border-bottom: 1.5px solid #F3F4F6;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
    margin-bottom: 16px;
}

.form-group label {
    font-size: 13px;
    font-weight: 800;
    color: #111111;
}
.form-group label .req { color: #8B0000; }

.form-group input,
.form-group select,
.form-group textarea {
    padding: 12px 15px;
    border: 1.5px solid #E5E7EB;
    border-radius: 11px;
    font-size: 15px;
    background: #FFFFFF;
    outline: none;
    font-family: inherit;
    color: #111111;
    transition: all 0.15s;
    width: 100%;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #8B0000;
    box-shadow: 0 0 0 3px rgba(139,0,0,0.08);
}

.form-group textarea { resize: vertical; min-height: 80px; }

/* ══════════════════════════════════════
   MODAL (popup ajouter/modifier)
   ══════════════════════════════════════ */
.modal {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    z-index: 2000;
    opacity: 0; pointer-events: none;
    transition: opacity 0.2s ease;
}
.modal.active {
    opacity: 1; pointer-events: auto;
}

.modal-content {
    background: #FFFFFF;
    padding: 32px;
    border-radius: 18px;
    width: 100%; max-width: 500px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.18);
    position: relative;
    transform: translateY(-20px);
    transition: transform 0.2s ease;
    max-height: 90vh;
    overflow-y: auto;
}
.modal.active .modal-content {
    transform: translateY(0);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 2px solid #F3F4F6;
}

.modal-title {
    font-size: 22px;
    font-weight: 900;
    color: #111111;
    display: flex; align-items: center; gap: 12px;
}
.modal-title::before {
    content: '';
    display: block;
    width: 5px; height: 24px;
    background: #8B0000;
    border-radius: 99px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 26px;
    color: #6B7280;
    cursor: pointer;
    font-weight: bold;
    width: 32px; height: 32px;
    border-radius: 8px;
    transition: all 0.15s;
    display: flex; align-items: center; justify-content: center;
}
.modal-close:hover { background: #FEF2F2; color: #8B0000; }

/* ══════════════════════════════════════
   GRILLE 2 COLONNES (formulaires)
   ══════════════════════════════════════ */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

/* ══════════════════════════════════════
   ÉTIQUETTES / COMPTEURS
   ══════════════════════════════════════ */
.cnt-badge {
    background: #F3F4F6;
    color: #6B7280;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

/* ══════════════════════════════════════
   STATS COMPACTES (utilisées dans toutes les pages)
   Style harmonisé : icône petite + label + chiffre 28px
   ══════════════════════════════════════ */
.stats-compact {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}

.stat-mini {
    background: #FFFFFF;
    border: 1.5px solid #E5E7EB;
    border-radius: 14px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: all 0.2s;
}
.stat-mini:hover {
    border-color: #8B0000;
    transform: translateY(-2px);
    box-shadow: 0 6px 14px -4px rgba(0,0,0,0.08);
}

.stat-mini-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px;
    border: 1.5px solid transparent;
    flex-shrink: 0;
}

.stat-mini-content {
    flex: 1;
    min-width: 0;
}

/* TITRE en haut, en gras (selon pi.docx) */
.stat-mini-label {
    font-size: 12px;
    font-weight: 800;
    color: #111111;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 4px;
    line-height: 1.2;
}

/* CHIFFRE en bas, lisible mais pas géant */
.stat-mini-number {
    font-size: 28px;
    font-weight: 900;
    color: #111111;
    line-height: 1;
    letter-spacing: -1px;
}

.stat-mini-number.alert { color: #8B0000; }

/* Responsive : sur petit écran, passer en 2 colonnes */
@media (max-width: 1100px) {
    .stats-compact { grid-template-columns: repeat(2, 1fr); }
}

/* ══════════════════════════════════════
   ALERTES RAPIDES (utilisées dans dashboard)
   ══════════════════════════════════════ */
.quick-alerts {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}

.alert-card {
    background: #FFFFFF;
    border: 2px solid #E5E7EB;
    border-radius: 14px;
    padding: 18px 22px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.alert-card.warn   { border-color: #FED7AA; background: #FFFBF5; }
.alert-card.danger { border-color: #FCA5A5; background: #FEF7F7; }
.alert-card.info   { border-color: #BFDBFE; background: #F5FAFF; }

.alert-icon {
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.alert-card.warn   .alert-icon { background: #FFF7ED; color: #EA580C; }
.alert-card.danger .alert-icon { background: #FEF2F2; color: #8B0000; }
.alert-card.info   .alert-icon { background: #EFF6FF; color: #1D4ED8; }

.alert-text strong {
    font-size: 22px;
    font-weight: 900;
    color: #111111;
    line-height: 1;
    display: block;
    margin-bottom: 3px;
}
.alert-text span {
    font-size: 13px;
    font-weight: 700;
    color: #444444;
}

@media (max-width: 1100px) {
    .quick-alerts { grid-template-columns: 1fr; }
}
CSS;
?>