<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// ════════════════════════════════════════════════════════
// STATISTIQUES PRINCIPALES
// ════════════════════════════════════════════════════════
$nb_banques      = $pdo->query("SELECT COUNT(*) FROM banque_de_sang")->fetchColumn();
$nb_sous_banques = $pdo->query("SELECT COUNT(*) FROM sous_banque")->fetchColumn();
$nb_hopitaux     = $pdo->query("SELECT COUNT(*) FROM hopital")->fetchColumn();
$nb_donneurs     = $pdo->query("SELECT COUNT(*) FROM donneur")->fetchColumn();
$nb_dons         = $pdo->query("SELECT COUNT(*) FROM don")->fetchColumn();
$nb_demandes     = $pdo->query("SELECT COUNT(*) FROM demande")->fetchColumn();

// Stock total (banques + sous-banques)
$total_stock_b   = $pdo->query("SELECT COALESCE(SUM(quantite_disponible), 0) FROM stock")->fetchColumn();
$total_stock_sb  = 0;
try {
    $total_stock_sb = $pdo->query("SELECT COALESCE(SUM(quantite_disponible), 0) FROM stock_sous_banque")->fetchColumn();
} catch (Exception $e) {}
$total_stock = $total_stock_b + $total_stock_sb;

// Donneurs en attente de confirmation de groupe
$nb_non_verifies = $pdo->query("SELECT COUNT(*) FROM donneur WHERE groupe_confirme = 0")->fetchColumn();

// Demandes en attente
$nb_demandes_attente = $pdo->query("SELECT COUNT(*) FROM demande WHERE statut = 'en_attente'")->fetchColumn();

// Stocks critiques (sous le seuil d'alerte)
$nb_stocks_critiques = 0;
try {
    $critiques_banque = $pdo->query("SELECT COUNT(*) FROM stock WHERE quantite_disponible <= seuil_alerte")->fetchColumn();
    $critiques_sous_banque = $pdo->query("SELECT COUNT(*) FROM stock_sous_banque WHERE quantite_disponible <= seuil_alerte")->fetchColumn();
    $nb_stocks_critiques = $critiques_banque + $critiques_sous_banque;
} catch (Exception $e) {
    $nb_stocks_critiques = $pdo->query("SELECT COUNT(*) FROM stock WHERE quantite_disponible < 5")->fetchColumn();
}

// ════════════════════════════════════════════════════════
// DONNÉES POUR LES ONGLETS
// ════════════════════════════════════════════════════════
// ✔ CORRECTION : LEFT JOIN au lieu de INNER JOIN pour ne pas exclure les demandes
//   internes (hôpital → sous-banque, où id_banque = NULL).
//   On joint aussi `hopital` pour afficher correctement le demandeur dans chaque cas.
$demandes = $pdo->query("
    SELECT d.*,
           h.nom  AS nom_hopital,
           s.nom  AS nom_sous_banque,
           b.nom  AS nom_banque,
           g.libelle AS groupe
    FROM demande d
    LEFT JOIN hopital h         ON h.id_hopital      = d.id_hopital
    LEFT JOIN sous_banque s     ON s.id_sous_banque  = d.id_sous_banque
    LEFT JOIN banque_de_sang b  ON b.id_banque       = d.id_banque
    JOIN groupe_sanguin g       ON g.id_groupe       = d.id_groupe
    ORDER BY d.date_demande DESC
    LIMIT 5
")->fetchAll();

$dons = $pdo->query("
    SELECT don.*, g.libelle AS groupe, b.nom AS nom_banque
    FROM don
    JOIN groupe_sanguin g  ON g.id_groupe = don.id_groupe
    JOIN banque_de_sang b  ON b.id_banque = don.id_banque
    ORDER BY don.date_don DESC
    LIMIT 5
")->fetchAll();

// ════════════════════════════════════════════════════════
// DATE EN FRANÇAIS
// ════════════════════════════════════════════════════════
$jours_fr  = ['Sunday'=>'dimanche', 'Monday'=>'lundi', 'Tuesday'=>'mardi', 'Wednesday'=>'mercredi', 'Thursday'=>'jeudi', 'Friday'=>'vendredi', 'Saturday'=>'samedi'];
$mois_fr   = ['January'=>'janvier', 'February'=>'février', 'March'=>'mars', 'April'=>'avril', 'May'=>'mai', 'June'=>'juin', 'July'=>'juillet', 'August'=>'août', 'September'=>'septembre', 'October'=>'octobre', 'November'=>'novembre', 'December'=>'décembre'];
$date_fr   = $jours_fr[date('l')] . ' ' . date('j') . ' ' . $mois_fr[date('F')] . ' ' . date('Y');

$page_active = 'dashboard';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — Admin E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* ──────────────────────────────────────
           STYLES SPÉCIFIQUES AU DASHBOARD
           (le reste est dans _style.php)
           ────────────────────────────────────── */

        /* Onglets demandes/dons */
        .tab-container {
            background: #FFFFFF;
            border: 2px solid #E5E7EB;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .tab-headers {
            display: flex;
            gap: 6px;
            border-bottom: 2px solid #F3F4F6;
            margin-bottom: 18px;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 11px 18px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: #6B7280;
            cursor: pointer;
            position: relative;
            bottom: -2px;
            transition: all 0.2s;
            border-bottom: 3px solid transparent;
        }
        .tab-btn:hover { color: #8B0000; }
        .tab-btn.active {
            color: #8B0000;
            border-bottom-color: #8B0000;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <!-- ══ BANDEAU ADMIN CONNECTÉ ══ -->
    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar"><?php echo $admin_initials; ?></div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bienvenue, <?php echo $admin_display; ?></div>
                <div class="top-bar-role">Espace administrateur</div>
            </div>
        </div>
        <div class="top-bar-date">📅 <?php echo $date_fr; ?></div>
    </div>

    <!-- ══ TITRE ══ -->
    <div class="page-header">
        <h1>Tableau de bord</h1>
        <p>Vue d'ensemble du système national de gestion du sang.</p>
    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!-- STATISTIQUES COMPACTES (8 en grille 4×2)      -->
    <!-- ══════════════════════════════════════════════ -->
    <div class="stats-compact">

        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🏦</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Banques</div>
                <div class="stat-mini-number"><?php echo $nb_banques; ?></div>
            </div>
        </div>

        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">🏪</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Sous-banques</div>
                <div class="stat-mini-number"><?php echo $nb_sous_banques; ?></div>
            </div>
        </div>

        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">🏥</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Hôpitaux</div>
                <div class="stat-mini-number"><?php echo $nb_hopitaux; ?></div>
            </div>
        </div>

        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">👤</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Donneurs</div>
                <div class="stat-mini-number"><?php echo $nb_donneurs; ?></div>
            </div>
        </div>

        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">💉</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Historique dons</div>
                <div class="stat-mini-number"><?php echo $nb_dons; ?></div>
            </div>
        </div>

        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">📋</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Historique demandes</div>
                <div class="stat-mini-number"><?php echo $nb_demandes; ?></div>
            </div>
        </div>

        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">🩸</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Stock global</div>
                <div class="stat-mini-number"><?php echo (int)$total_stock; ?></div>
            </div>
        </div>

        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">⏳</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">À confirmer</div>
                <div class="stat-mini-number <?php echo $nb_non_verifies > 0 ? 'alert' : ''; ?>">
                    <?php echo $nb_non_verifies; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!-- ALERTES RAPIDES (statistiques claires/directes) -->
    <!-- ══════════════════════════════════════════════ -->
    <div class="quick-alerts">

        <div class="alert-card <?php echo $nb_demandes_attente > 0 ? 'warn' : 'info'; ?>">
            <div class="alert-icon">📋</div>
            <div class="alert-text">
                <strong><?php echo $nb_demandes_attente; ?></strong>
                <span>Demande(s) en attente de traitement</span>
            </div>
        </div>

        <div class="alert-card <?php echo $nb_stocks_critiques > 0 ? 'danger' : 'info'; ?>">
            <div class="alert-icon">⚠️</div>
            <div class="alert-text">
                <strong><?php echo $nb_stocks_critiques; ?></strong>
                <span>Stock(s) sous le seuil d'alerte</span>
            </div>
        </div>

        <div class="alert-card info">
            <div class="alert-icon">👤</div>
            <div class="alert-text">
                <strong><?php echo $nb_non_verifies; ?></strong>
                <span>Donneur(s) en attente de confirmation</span>
            </div>
        </div>

    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!-- ONGLETS DEMANDES / DONS RÉCENTS                -->
    <!-- ══════════════════════════════════════════════ -->
    <div class="tab-container">
        <div class="tab-headers">
            <button class="tab-btn active" onclick="switchTab('tab-demandes', this)">📋 Dernières demandes</button>
            <button class="tab-btn"        onclick="switchTab('tab-dons', this)">💉 Derniers dons</button>
        </div>

        <!-- ── Onglet Demandes ── -->
        <div id="tab-demandes" class="tab-content active">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Demandeur</th>
                            <th>Récepteur</th>
                            <th>Groupe</th>
                            <th>Quantité</th>
                            <th>Date</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($demandes): ?>
                            <?php foreach ($demandes as $d): ?>
                            <?php
                                // ✔ CORRECTION : affichage adapté selon le type de demande
                                //   - interne : hôpital → sous-banque
                                //   - externe : sous-banque → banque principale
                                if ($d['type_demande'] === 'interne') {
                                    $type_label    = '🏥 Interne';
                                    $type_style    = 'background:#EEF2FF; color:#4338CA; border:1.5px solid #C7D2FE;';
                                    $demandeur     = $d['nom_hopital']     ?? '—';
                                    $recepteur     = $d['nom_sous_banque'] ?? '—';
                                    $recepteur_ico = '🏪';
                                } else {
                                    $type_label    = '🏦 Externe';
                                    $type_style    = 'background:#FDF4FF; color:#86198F; border:1.5px solid #F5D0FE;';
                                    $demandeur     = $d['nom_sous_banque'] ?? '—';
                                    $recepteur     = $d['nom_banque']      ?? '—';
                                    $recepteur_ico = '🏦';
                                }
                            ?>
                            <tr>
                                <td><span class="badge" style="<?php echo $type_style; ?>"><?php echo $type_label; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($demandeur); ?></strong></td>
                                <td><?php echo $recepteur_ico . ' ' . htmlspecialchars($recepteur); ?></td>
                                <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                                <td><strong><?php echo (int)$d['quantite_demandee']; ?></strong> pochette(s)</td>
                                <td><?php echo date('d/m/Y', strtotime($d['date_demande'])); ?></td>
                                <td>
                                    <?php if ($d['statut'] === 'en_attente'): ?>
                                        <span class="badge badge-attente">En attente</span>
                                    <?php elseif ($d['statut'] === 'acceptée'): ?>
                                        <span class="badge badge-acceptee">Acceptée</span>
                                    <?php elseif ($d['statut'] === 'annulée'): ?>
                                        <span class="badge badge-annulee">Annulée</span>
                                    <?php else: ?>
                                        <span class="badge badge-refusee">Refusée</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="vide">Aucune demande enregistrée pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- ── Onglet Dons ── -->
        <div id="tab-dons" class="tab-content">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Donneur</th>
                            <th>Banque</th>
                            <th>Groupe</th>
                            <th>Quantité</th>
                            <th>Date</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($dons): ?>
                            <?php foreach ($dons as $d): ?>
                            <tr>
                                <td><strong>Donneur #<?php echo $d['id_donneur']; ?></strong></td>
                                <td><?php echo htmlspecialchars($d['nom_banque']); ?></td>
                                <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                                <td><strong><?php echo (int)$d['quantite']; ?></strong> pochette(s)</td>
                                <td><?php echo date('d/m/Y', strtotime($d['date_don'])); ?></td>
                                <td>
                                    <?php if ($d['statut'] === 'en_attente'): ?>
                                        <span class="badge badge-attente">En attente</span>
                                    <?php elseif ($d['statut'] === 'accepté'): ?>
                                        <span class="badge badge-accepte">Accepté</span>
                                    <?php else: ?>
                                        <span class="badge badge-refuse">Refusé</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="vide">Aucun don enregistré pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="text-align: right; margin-top: 16px;">
                <a href="dons.php" class="section-link">Voir tous les dons →</a>
            </div>
        </div>
    </div>

</div>

<script>
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
}
</script>

</body>
</html>