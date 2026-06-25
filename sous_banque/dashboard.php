<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sous_banque') {
    header("Location: ../index.php");
    exit;
}

$id_sb = $_SESSION['id_sous_banque'];

// Synchroniser les demandes externes acceptées par la banque (création de lot + mise à jour stock)
require_once '_sync_demandes.php';

// ── 1. Total général des pochettes ──
$stmt = $pdo->prepare("SELECT COALESCE(SUM(quantite_disponible), 0) FROM stock_sous_banque WHERE id_sous_banque = ?");
$stmt->execute([$id_sb]);
$total_stock = (int)$stmt->fetchColumn();

// ── 2. Groupes en rupture (TOUS les groupes sanguins, y compris ceux jamais configurés) ──
// Un groupe jamais configuré dans stock_sous_banque a 0 pochette disponible : il EST en rupture.
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM groupe_sanguin g
    LEFT JOIN stock_sous_banque s ON s.id_groupe = g.id_groupe AND s.id_sous_banque = ?
    WHERE COALESCE(s.quantite_disponible, 0) = 0
");
$stmt->execute([$id_sb]);
$nb_rupture = (int)$stmt->fetchColumn();

// ── 3. Groupes sous le seuil (TOUS les groupes, avec du stock mais en dessous du seuil) ──
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM groupe_sanguin g
    LEFT JOIN stock_sous_banque s ON s.id_groupe = g.id_groupe AND s.id_sous_banque = ?
    WHERE COALESCE(s.quantite_disponible, 0) > 0
      AND COALESCE(s.quantite_disponible, 0) <= COALESCE(s.seuil_alerte, 5)
");
$stmt->execute([$id_sb]);
$nb_faible = (int)$stmt->fetchColumn();

// ── 4. Alertes non traitées ──
$stmt = $pdo->prepare("SELECT COUNT(*) FROM alerte_stock WHERE id_sous_banque = ? AND traitee = 0");
$stmt->execute([$id_sb]);
$nb_alertes_actives = (int)$stmt->fetchColumn();

// ── 5. Lots qui expirent bientôt (≤ 7 jours) ──
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM lot_sang_sous_banque
    WHERE id_sous_banque = ? AND statut = 'disponible'
      AND date_expiration <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
");
$stmt->execute([$id_sb]);
$nb_lots_a_risque = (int)$stmt->fetchColumn();

// ── 6. Stock par groupe sanguin (tous les groupes) ──
$stmt = $pdo->prepare("
    SELECT
        g.id_groupe,
        g.libelle AS groupe,
        COALESCE(s.quantite_disponible, 0) AS quantite,
        COALESCE(s.seuil_alerte, 5)        AS seuil
    FROM groupe_sanguin g
    LEFT JOIN stock_sous_banque s ON s.id_groupe = g.id_groupe AND s.id_sous_banque = ?
    ORDER BY g.libelle
");
$stmt->execute([$id_sb]);
$groupes_stock = $stmt->fetchAll();

// ── 7. Dernières demandes envoyées ──
$stmt = $pdo->prepare("
    SELECT d.*, b.nom AS nom_banque, g.libelle AS groupe
    FROM demande d
    JOIN banque_de_sang b ON b.id_banque = d.id_banque
    JOIN groupe_sanguin g ON g.id_groupe = d.id_groupe
    WHERE d.id_sous_banque = ?
      AND d.type_demande = 'externe'
    ORDER BY d.date_demande DESC
    LIMIT 5
");
$stmt->execute([$id_sb]);
$dernieres_demandes = $stmt->fetchAll();

// ── 8. Lots à surveiller (les plus proches de l'expiration) ──
$stmt = $pdo->prepare("
    SELECT l.*, g.libelle AS groupe,
           DATEDIFF(l.date_expiration, CURDATE()) AS jours_restants
    FROM lot_sang_sous_banque l
    JOIN groupe_sanguin g ON g.id_groupe = l.id_groupe
    WHERE l.id_sous_banque = ? AND l.statut = 'disponible'
    ORDER BY l.date_expiration ASC
    LIMIT 5
");
$stmt->execute([$id_sb]);
$lots_a_surveiller = $stmt->fetchAll();

// ── Date FR ──
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
    <title>Tableau de bord — <?php echo htmlspecialchars($_SESSION['nom_sb'] ?? 'Sous-banque'); ?> | E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* ── Onglets ── */
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

        /* ── Stock par groupe (cartes) ── */
        .groupes-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 10px;
            margin-top: 14px;
        }
        @media (max-width: 1200px) { .groupes-grid { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 700px)  { .groupes-grid { grid-template-columns: repeat(2, 1fr); } }

        .groupe-card {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 16px;
            padding: 18px 12px 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
        }
        .groupe-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .groupe-card.critique { background: #FFF0F0; border-color: #FECACA; }
        .groupe-card.faible   { background: #FFFBEB; border-color: #FDE68A; }
        .groupe-card.ok       { background: #FFFFFF; border-color: #E5E7EB; }

        .groupe-card-badge {
            width: 44px; height: 44px;
            background: #111111;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 800; color: #FFFFFF;
            letter-spacing: -0.3px;
            flex-shrink: 0;
        }
        .groupe-card-qte {
            font-size: 34px;
            font-weight: 900;
            line-height: 1;
            color: #111111;
        }
        .groupe-card.critique .groupe-card-qte { color: #8B0000; }
        .groupe-card.faible   .groupe-card-qte { color: #8B0000; }

        .groupe-card-unit {
            font-size: 10px;
            font-weight: 700;
            color: #9CA3AF;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: -6px;
        }
        .groupe-card-seuil {
            font-size: 10px; color: #9CA3AF;
            background: #F3F4F6; border-radius: 999px;
            padding: 2px 8px; font-weight: 600;
        }

        /* ── Bandeau d'alertes ── */
        .alertes-stock {
            display: flex; flex-direction: column; gap: 10px;
            margin-bottom: 24px;
        }
        .alerte-stock {
            background: linear-gradient(to right, #FEF2F2, #FFF7ED);
            border: 1.5px solid #FCA5A5;
            border-left: 4px solid #DC2626;
            border-radius: 12px;
            padding: 14px 20px;
            display: flex; align-items: center; gap: 14px;
            font-size: 14px; color: #7F1D1D; font-weight: 600;
        }
        .alerte-stock .icon { font-size: 24px; flex-shrink: 0; }
        .alerte-stock strong { color: #DC2626; font-weight: 800; }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <!-- ══ TOP-BAR AGENT ══ -->
    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar"><?php echo $agent_initials; ?></div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bonjour, <?php echo $agent_display; ?></div>
                <div class="top-bar-role">Agent — <?php echo htmlspecialchars($_SESSION['nom_sb'] ?? 'Sous-banque'); ?></div>
            </div>
        </div>
        <div class="top-bar-date">📅 <?php echo $date_fr; ?></div>
    </div>

    <!-- ══ TITRE ══ -->
    <div class="page-header">
        <h1>Tableau de bord</h1>
        <p>Vue d'ensemble de votre dépôt et de votre stock interne.</p>
    </div>

    <!-- ══ ALERTES DE STOCK ══ -->
    <?php if ($nb_rupture > 0 || $nb_faible > 0 || $nb_lots_a_risque > 0): ?>
    <div class="alertes-stock">
        <?php if ($nb_rupture > 0): ?>
        <div class="alerte-stock">
            <span class="icon">🚨</span>
            <div><strong><?php echo $nb_rupture; ?></strong> groupe(s) sanguin(s) en <strong>rupture totale</strong> — demandez du stock !</div>
        </div>
        <?php endif; ?>
        <?php if ($nb_faible > 0): ?>
        <div class="alerte-stock" style="border-left-color: #F59E0B; background: linear-gradient(to right, #FFFBEB, #FFFFFF);">
            <span class="icon">⚠️</span>
            <div><strong><?php echo $nb_faible; ?></strong> groupe(s) sous le seuil d'alerte — surveillez de près.</div>
        </div>
        <?php endif; ?>
        <?php if ($nb_lots_a_risque > 0): ?>
        <div class="alerte-stock" style="border-left-color: #EA580C; background: linear-gradient(to right, #FFF7ED, #FFFFFF);">
            <span class="icon">⏰</span>
            <div><strong><?php echo $nb_lots_a_risque; ?></strong> lot(s) expirent dans les <strong>7 jours</strong> — utilisez-les en priorité.</div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🩸</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Pochettes en stock</div>
                <div class="stat-mini-number"><?php echo $total_stock; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">🚨</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">En rupture</div>
                <div class="stat-mini-number <?php echo $nb_rupture > 0 ? 'alert' : ''; ?>"><?php echo $nb_rupture; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">⚠️</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Sous seuil</div>
                <div class="stat-mini-number <?php echo $nb_faible > 0 ? 'alert' : ''; ?>"><?php echo $nb_faible; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">🔔</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Alertes actives</div>
                <div class="stat-mini-number <?php echo $nb_alertes_actives > 0 ? 'alert' : ''; ?>"><?php echo $nb_alertes_actives; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ STOCK PAR GROUPE SANGUIN ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Stock par groupe sanguin</div>
            <div style="display:flex; align-items:center; gap:12px;">
                <span style="background:#F3F4F6; border:1.5px solid #E5E7EB; border-radius:999px; padding:4px 14px; font-size:13px; font-weight:700; color:#374151;">
                    Total : <?php echo $total_stock; ?> pochette<?php echo $total_stock > 1 ? 's' : ''; ?>
                </span>
                <a href="stock.php" style="color:#8B0000; font-weight:700; text-decoration:none; font-size:13px;">Voir tout →</a>
            </div>
        </div>

        <div class="groupes-grid">
            <?php foreach ($groupes_stock as $g):
                $q     = (int)$g['quantite'];
                $seuil = (int)$g['seuil'];
                if      ($q === 0)           $cls = 'critique';
                elseif  ($q <= $seuil)       $cls = 'faible';
                else                         $cls = 'ok';
            ?>
            <div class="groupe-card <?php echo $cls; ?>">
                <div class="groupe-card-badge"><?php echo htmlspecialchars($g['groupe']); ?></div>
                <div class="groupe-card-qte"><?php echo $q; ?></div>
                <div class="groupe-card-unit">Pochette(s)</div>
                <div class="groupe-card-seuil">Seuil : <?php echo $seuil; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ══ ONGLETS : DEMANDES + LOTS À SURVEILLER ══ -->
    <div class="tab-container">
        <div class="tab-headers">
            <button class="tab-btn active" onclick="switchTab('tab-demandes', this)">📋 Mes dernières demandes</button>
            <button class="tab-btn" onclick="switchTab('tab-lots', this)">⏰ Lots à surveiller</button>
        </div>

        <!-- Onglet demandes -->
        <div id="tab-demandes" class="tab-content active">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Banque mère</th>
                            <th>Groupe</th>
                            <th>Quantité</th>
                            <th>Date</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($dernieres_demandes): ?>
                            <?php foreach ($dernieres_demandes as $d): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($d['nom_banque']); ?></strong></td>
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
                            <tr><td colspan="5" class="vide">Aucune demande envoyée pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Onglet lots à surveiller -->
        <div id="tab-lots" class="tab-content">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Code Lot</th>
                            <th>Groupe</th>
                            <th>Quantité</th>
                            <th>Expiration</th>
                            <th>Jours restants</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($lots_a_surveiller): ?>
                            <?php foreach ($lots_a_surveiller as $l):
                                $j = (int)$l['jours_restants'];
                                $urgence = $j < 0 ? 'expire' : ($j <= 3 ? 'critique' : ($j <= 7 ? 'attention' : 'ok'));
                            ?>
                            <tr>
                                <td><strong style="font-family:'Courier New', monospace; font-size:12px;"><?php echo htmlspecialchars('LOT-' . $l['id_lot']); ?></strong></td>
                                <td><span class="badge badge-groupe"><?php echo htmlspecialchars($l['groupe']); ?></span></td>
                                <td><strong><?php echo (int)$l['quantite']; ?></strong> poch.</td>
                                <td><?php echo date('d/m/Y', strtotime($l['date_expiration'])); ?></td>
                                <td>
                                    <?php if ($j < 0): ?>
                                        <span class="badge badge-refusee">⏰ Expiré (<?php echo abs($j); ?>j)</span>
                                    <?php elseif ($j <= 3): ?>
                                        <span class="badge badge-attente" style="background:#FEE2E2; color:#B91C1C;">🚨 <?php echo $j; ?> jour(s)</span>
                                    <?php elseif ($j <= 7): ?>
                                        <span class="badge badge-attente">⚠️ <?php echo $j; ?> jours</span>
                                    <?php else: ?>
                                        <span class="badge badge-acceptee"><?php echo $j; ?> jours</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="vide">Aucun lot en stock pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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