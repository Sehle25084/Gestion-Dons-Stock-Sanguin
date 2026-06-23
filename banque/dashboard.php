<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'banque') {
    header("Location: ../index.php");
    exit;
}

// ── Compatibilité ancienne/nouvelle architecture ──
// Nouvelle : $_SESSION['id_banque'] (depuis utilisateur_banque)
// Ancienne : $_SESSION['id'] (login direct banque_de_sang)
$id_banque = $_SESSION['id_banque'] ?? $_SESSION['id'];

// ── Vérifier les pochettes expirées et alerter ──
verifierPochettesExpirees($pdo, $id_banque);

// ── Charger les alertes de stock (sous seuil) ──
$stmt = $pdo->prepare("
    SELECT s.id_groupe,
           s.quantite_disponible,
           s.seuil_alerte,
           g.libelle
    FROM stock s
    JOIN groupe_sanguin g ON g.id_groupe = s.id_groupe
    WHERE s.id_banque = ?
      AND s.quantite_disponible <= s.seuil_alerte
");
$stmt->execute([$id_banque]);
$alertes = $stmt->fetchAll();

foreach ($alertes as $alerte) {
    verifierSeuilAlerte($pdo, $id_banque, $alerte['id_groupe']);
}

// ── Statistiques principales ──
$stmt = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_banque = ?");
$stmt->execute([$id_banque]);
$nb_dons = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_banque = ? AND type_demande = 'externe'");
$stmt->execute([$id_banque]);
$nb_demandes = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_banque = ? AND type_demande = 'externe' AND statut = 'en_attente'");
$stmt->execute([$id_banque]);
$nb_demandes_attente = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_banque = ? AND statut = 'en_attente'");
$stmt->execute([$id_banque]);
$nb_dons_attente = $stmt->fetchColumn();

$nb_donneurs_non_verifies = $pdo->query("SELECT COUNT(*) FROM donneur WHERE groupe_confirme = 0")->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(quantite_disponible) FROM stock WHERE id_banque = ?");
$stmt->execute([$id_banque]);
$nb_stock = $stmt->fetchColumn() ?: 0;

// ── Stock détaillé par groupe sanguin (pour le graphique en barres) ──
$stmt = $pdo->prepare("
    SELECT
        g.id_groupe,
        g.libelle AS groupe,
        COALESCE(s.quantite_disponible, 0) AS quantite,
        COALESCE(s.seuil_alerte, 5)        AS seuil
    FROM groupe_sanguin g
    LEFT JOIN stock s ON s.id_groupe = g.id_groupe AND s.id_banque = ?
    ORDER BY
        CASE g.libelle
            WHEN 'A+' THEN 1 WHEN 'A-' THEN 2
            WHEN 'B+' THEN 3 WHEN 'B-' THEN 4
            WHEN 'AB+' THEN 5 WHEN 'AB-' THEN 6
            WHEN 'O+' THEN 7 WHEN 'O-' THEN 8
            ELSE 9
        END
");
$stmt->execute([$id_banque]);
$stock_par_groupe = $stmt->fetchAll();

// Calcul max pour échelle du graphique
$max_quantite = 0;
foreach ($stock_par_groupe as $sg) {
    if ((int)$sg['quantite'] > $max_quantite) $max_quantite = (int)$sg['quantite'];
}
// Échelle minimale pour visibilité (au moins 10 ou le double du seuil moyen)
$echelle_max = max($max_quantite, 10);

// ── Dernières 5 demandes externes (depuis les sous-banques) ──
$stmt = $pdo->prepare("
    SELECT d.*, sb.nom AS nom_sous_banque, g.libelle AS groupe
    FROM demande d
    JOIN sous_banque sb ON sb.id_sous_banque = d.id_sous_banque
    JOIN groupe_sanguin g ON g.id_groupe = d.id_groupe
    WHERE d.id_banque = ?
      AND d.type_demande = 'externe'
    ORDER BY d.date_demande DESC
    LIMIT 5
");
$stmt->execute([$id_banque]);
$demandes = $stmt->fetchAll();

// ── Derniers 5 dons reçus ──
$stmt = $pdo->prepare("
    SELECT don.*, g.libelle AS groupe
    FROM don
    JOIN groupe_sanguin g ON g.id_groupe = don.id_groupe
    WHERE don.id_banque = ?
    ORDER BY don.date_don DESC
    LIMIT 5
");
$stmt->execute([$id_banque]);
$dons = $stmt->fetchAll();

// ── Date en français ──
$jours_fr = ['Sunday'=>'dimanche', 'Monday'=>'lundi', 'Tuesday'=>'mardi', 'Wednesday'=>'mercredi', 'Thursday'=>'jeudi', 'Friday'=>'vendredi', 'Saturday'=>'samedi'];
$mois_fr  = ['January'=>'janvier', 'February'=>'février', 'March'=>'mars', 'April'=>'avril', 'May'=>'mai', 'June'=>'juin', 'July'=>'juillet', 'August'=>'août', 'September'=>'septembre', 'October'=>'octobre', 'November'=>'novembre', 'December'=>'décembre'];
$date_fr  = $jours_fr[date('l')] . ' ' . date('j') . ' ' . $mois_fr[date('F')] . ' ' . date('Y');

$page_active = 'dashboard';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — <?php echo htmlspecialchars($_SESSION['nom_banque'] ?? 'Banque'); ?> | E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* ── Onglets pour demandes/dons récents ── */
        .tab-container {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 14px;
            padding: 24px;
            margin-top: 24px;
        }
        .tab-headers {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #F3F4F6;
            margin-bottom: 20px;
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

        /* ══ GRAPHIQUE EN BARRES — Stock par groupe ══ */
        .chart-card {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 14px;
            padding: 24px 26px;
            margin-bottom: 24px;
        }
        .chart-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 22px;
            flex-wrap: wrap; gap: 12px;
        }
        .chart-title {
            font-size: 16px; font-weight: 800; color: #111111;
            display: flex; align-items: center; gap: 8px;
        }
        .chart-title::before {
            content: ''; display: block;
            width: 4px; height: 18px;
            background: #8B0000; border-radius: 99px;
        }
        .chart-legend {
            display: flex; gap: 14px; flex-wrap: wrap;
            font-size: 11px; color: #6B7280;
        }
        .legend-item {
            display: inline-flex; align-items: center; gap: 5px;
        }
        .legend-dot {
            width: 10px; height: 10px; border-radius: 3px;
        }
        .legend-ok       { background: #16A34A; }
        .legend-faible   { background: #F59E0B; }
        .legend-rupture  { background: #DC2626; }

        .chart-container {
            position: relative;
            display: flex; align-items: flex-end; justify-content: space-around;
            gap: 12px;
            height: 260px;
            padding: 0 4px 36px 4px;
            border-bottom: 2px solid #E5E7EB;
        }

        .chart-bar-group {
            flex: 1;
            display: flex; flex-direction: column; align-items: center;
            height: 100%;
            position: relative;
            min-width: 0;
        }

        .chart-bar-value {
            font-size: 13px; font-weight: 800; color: #111111;
            margin-bottom: 4px;
            white-space: nowrap;
        }

        .chart-bar {
            width: 100%; max-width: 50px;
            border-radius: 8px 8px 0 0;
            transition: all 0.3s ease;
            position: relative;
            cursor: default;
            min-height: 2px;
            box-shadow: inset 0 -3px 0 rgba(0,0,0,0.1);
        }
        .chart-bar:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15), inset 0 -3px 0 rgba(0,0,0,0.1);
        }
        .bar-ok      { background: linear-gradient(180deg, #22C55E 0%, #16A34A 100%); }
        .bar-faible  { background: linear-gradient(180deg, #FBBF24 0%, #F59E0B 100%); }
        .bar-rupture { background: linear-gradient(180deg, #EF4444 0%, #DC2626 100%); }

        .chart-bar-label {
            position: absolute;
            bottom: -28px;
            font-size: 12px; font-weight: 700; color: #374151;
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            padding: 3px 8px;
            border-radius: 6px;
            white-space: nowrap;
        }

        .chart-empty {
            text-align: center; color: #9CA3AF;
            padding: 60px 20px;
            font-size: 14px;
        }

        /* Responsive : graphique compact sur mobile */
        @media (max-width: 640px) {
            .chart-container { height: 200px; gap: 6px; }
            .chart-bar { max-width: 32px; }
            .chart-bar-label { font-size: 10px; padding: 2px 5px; }
            .chart-bar-value { font-size: 11px; }
        }

        /* ── Bandeau d'alertes en haut ── */
        .alertes-stock {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 24px;
        }
        .alerte-stock {
            background: linear-gradient(to right, #FEF2F2, #FFF7ED);
            border: 1.5px solid #FCA5A5;
            border-left: 4px solid #DC2626;
            border-radius: 12px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 14px;
            color: #7F1D1D;
            font-weight: 600;
        }
        .alerte-stock .icon {
            font-size: 24px;
            flex-shrink: 0;
        }
        .alerte-stock strong { color: #DC2626; font-weight: 800; }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <!-- ══ TOP-BAR AGENT BANQUE ══ -->
    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar"><?php echo $agent_initials; ?></div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bonjour, <?php echo $agent_display; ?></div>
                <div class="top-bar-role">Agent — <?php echo htmlspecialchars($_SESSION['nom_banque'] ?? 'Banque de sang'); ?></div>
            </div>
        </div>
        <div class="top-bar-date">📅 <?php echo $date_fr; ?></div>
    </div>

    <!-- ══ TITRE PAGE ══ -->
    <div class="page-header">
        <h1>Tableau de bord</h1>
        <p>Vue d'ensemble de votre banque de sang.</p>
    </div>

    <!-- ══ ALERTES DE STOCK FAIBLE ══ -->
    <?php if (!empty($alertes)): ?>
    <div class="alertes-stock">
        <?php foreach ($alertes as $a): ?>
            <div class="alerte-stock">
                <span class="icon">⚠️</span>
                <div>
                    Stock faible pour le groupe
                    <strong><?php echo htmlspecialchars($a['libelle']); ?></strong>
                    : seulement <strong><?php echo (int)$a['quantite_disponible']; ?></strong> pochette(s) restante(s)
                    (seuil : <?php echo (int)$a['seuil_alerte']; ?>)
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ══ STATISTIQUES COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🩸</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Pochettes en stock</div>
                <div class="stat-mini-number"><?php echo (int)$nb_stock; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">💉</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total dons reçus</div>
                <div class="stat-mini-number"><?php echo $nb_dons; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">📋</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Demandes externes</div>
                <div class="stat-mini-number"><?php echo $nb_demandes; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">👤</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Groupes à confirmer</div>
                <div class="stat-mini-number <?php echo $nb_donneurs_non_verifies > 0 ? 'alert' : ''; ?>"><?php echo $nb_donneurs_non_verifies; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ ALERTES D'ACTIONS EN ATTENTE ══ -->
    <?php if ($nb_demandes_attente > 0 || $nb_dons_attente > 0): ?>
    <div class="stats-compact" style="margin-top: 16px;">
        <?php if ($nb_demandes_attente > 0): ?>
        <div class="stat-mini" style="border-color: #FCD34D; background: #FFFBEB;">
            <div class="stat-mini-icon ic-org">⏳</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Demandes à traiter</div>
                <div class="stat-mini-number alert"><?php echo $nb_demandes_attente; ?></div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($nb_dons_attente > 0): ?>
        <div class="stat-mini" style="border-color: #FCD34D; background: #FFFBEB;">
            <div class="stat-mini-icon ic-org">⏳</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Dons à valider</div>
                <div class="stat-mini-number alert"><?php echo $nb_dons_attente; ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══ GRAPHIQUE EN BARRES : Stock par groupe sanguin ══ -->
    <div class="chart-card">
        <div class="chart-header">
            <div class="chart-title">📊 Stock par groupe sanguin</div>
            <div class="chart-legend">
                <span class="legend-item"><span class="legend-dot legend-ok"></span> Stock OK</span>
                <span class="legend-item"><span class="legend-dot legend-faible"></span> Sous le seuil</span>
                <span class="legend-item"><span class="legend-dot legend-rupture"></span> En rupture</span>
            </div>
        </div>

        <?php if (!empty($stock_par_groupe)): ?>
        <div class="chart-container">
            <?php foreach ($stock_par_groupe as $sg):
                $q     = (int)$sg['quantite'];
                $seuil = (int)$sg['seuil'];

                // Hauteur en % (avec une hauteur min de 2px géré en CSS)
                $hauteur = $echelle_max > 0 ? ($q / $echelle_max * 100) : 0;
                $hauteur = max(0, min(100, $hauteur));

                // Couleur selon état
                if      ($q === 0)        $cls = 'bar-rupture';
                elseif  ($q <= $seuil)    $cls = 'bar-faible';
                else                      $cls = 'bar-ok';
            ?>
            <div class="chart-bar-group" title="Groupe <?php echo htmlspecialchars($sg['groupe']); ?> : <?php echo $q; ?> pochette(s) — Seuil : <?php echo $seuil; ?>">
                <div class="chart-bar-value"><?php echo $q; ?></div>
                <div class="chart-bar <?php echo $cls; ?>" style="height: <?php echo $hauteur; ?>%;"></div>
                <div class="chart-bar-label"><?php echo htmlspecialchars($sg['groupe']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align:center; margin-top:14px; font-size:12px; color:#6B7280;">
            <strong>Total : <?php echo (int)$nb_stock; ?> pochette<?php echo $nb_stock > 1 ? 's' : ''; ?> en stock</strong>
            &nbsp;·&nbsp;
            Survolez une barre pour plus de détails
        </div>

        <?php else: ?>
        <div class="chart-empty">📭 Aucune donnée de stock disponible.</div>
        <?php endif; ?>
    </div>

    <!-- ══ ONGLETS DEMANDES / DONS RÉCENTS ══ -->
    <div class="tab-container">
        <div class="tab-headers">
            <button class="tab-btn active" onclick="switchTab('tab-demandes', this)">📋 Dernières demandes</button>
            <button class="tab-btn" onclick="switchTab('tab-dons', this)">💉 Derniers dons</button>
        </div>

        <!-- Onglet Demandes -->
        <div id="tab-demandes" class="tab-content active">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Sous-banque</th>
                            <th>Groupe</th>
                            <th>Quantité</th>
                            <th>Date</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($demandes): ?>
                            <?php foreach ($demandes as $d): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($d['nom_sous_banque']); ?></strong></td>
                                <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                                <td><strong><?php echo (int)$d['quantite_demandee']; ?></strong> pochette(s)</td>
                                <td><?php echo date('d/m/Y', strtotime($d['date_demande'])); ?></td>
                                <td>
                                    <?php if ($d['statut'] === 'en_attente'): ?>
                                        <span class="badge badge-attente">⏳ En attente</span>
                                    <?php elseif ($d['statut'] === 'acceptée'): ?>
                                        <span class="badge badge-acceptee">✅ Acceptée</span>
                                    <?php else: ?>
                                        <span class="badge badge-refusee">❌ Refusée</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="vide">Aucune demande reçue pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="text-align: right; margin-top: 16px;">
                <a href="demandes.php" style="color:#8B0000; font-weight:700; text-decoration:none; font-size:13px;">
                    Voir toutes les demandes →
                </a>
            </div>
        </div>

        <!-- Onglet Dons -->
        <div id="tab-dons" class="tab-content">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Donneur</th>
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
                                <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                                <td><strong><?php echo (int)$d['quantite']; ?></strong> pochette(s)</td>
                                <td><?php echo date('d/m/Y', strtotime($d['date_don'])); ?></td>
                                <td>
                                    <?php if ($d['statut'] === 'en_attente'): ?>
                                        <span class="badge badge-attente">⏳ En attente</span>
                                    <?php elseif ($d['statut'] === 'accepté'): ?>
                                        <span class="badge badge-accepte">✅ Accepté</span>
                                    <?php else: ?>
                                        <span class="badge badge-refuse">❌ Refusé</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="vide">Aucun don enregistré pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="text-align: right; margin-top: 16px;">
                <a href="dons.php" style="color:#8B0000; font-weight:700; text-decoration:none; font-size:13px;">
                    Voir tous les dons →
                </a>
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