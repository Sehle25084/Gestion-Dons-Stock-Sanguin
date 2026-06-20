<?php
session_start();
require_once '../config/db.php';

$page_active = 'dashboard';
include 'sidebar.php'; // Sécurité + CSS inclus ici

$id_sb = $_SESSION['id_sous_banque'];

// ── 1. Total général des pochettes ──
$stmt = $pdo->prepare("SELECT COALESCE(SUM(quantite_disponible), 0) FROM stock_sous_banque WHERE id_sous_banque = ?");
$stmt->execute([$id_sb]);
$total_stock = (int)$stmt->fetchColumn();

// ── 2. Nombre de groupes en rupture (quantite = 0) ──
$stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_sous_banque WHERE id_sous_banque = ? AND quantite_disponible = 0");
$stmt->execute([$id_sb]);
$nb_rupture = (int)$stmt->fetchColumn();

// ── 3. Nombre de groupes sous le seuil (quantite > 0 ET <= seuil) ──
$stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_sous_banque WHERE id_sous_banque = ? AND quantite_disponible > 0 AND quantite_disponible <= seuil_alerte");
$stmt->execute([$id_sb]);
$nb_faible = (int)$stmt->fetchColumn();

// ── 4. Alertes non traitées ──
$stmt = $pdo->prepare("SELECT COUNT(*) FROM alerte_stock WHERE id_sous_banque = ? AND traitee = 0");
$stmt->execute([$id_sb]);
$nb_alertes_actives = (int)$stmt->fetchColumn();

// ── 5. Lots qui expirent bientôt (≤ 7 jours), stock disponible uniquement ──
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM lot_sang_sous_banque
    WHERE id_sous_banque = ? AND statut = 'disponible'
    AND date_expiration <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
");
$stmt->execute([$id_sb]);
$nb_lots_a_risque = (int)$stmt->fetchColumn();

// ── 6. Stock par groupe sanguin (TOUS les groupes, même ceux à 0) ──
$stmt = $pdo->prepare("
    SELECT
        g.id_groupe,
        g.libelle AS groupe,
        COALESCE(s.quantite_disponible, 0) AS quantite,
        COALESCE(s.seuil_alerte, 3)        AS seuil,
        s.date_mise_a_jour
    FROM groupe_sanguin g
    LEFT JOIN stock_sous_banque s
        ON s.id_groupe = g.id_groupe
        AND s.id_sous_banque = ?
    ORDER BY g.libelle
");
$stmt->execute([$id_sb]);
$groupes_stock = $stmt->fetchAll();

// ── 7. Dernières demandes envoyées par cette sous-banque (aperçu : 3 max) ──
$stmt = $pdo->prepare("
    SELECT d.*, b.nom AS nom_banque, g.libelle AS groupe
    FROM demande d
    JOIN banque_de_sang b  ON b.id_banque = d.id_banque
    JOIN groupe_sanguin g  ON g.id_groupe  = d.id_groupe
    WHERE d.id_sous_banque = ?
    ORDER BY d.date_demande DESC
    LIMIT 3
");
$stmt->execute([$id_sb]);
$dernieres_demandes = $stmt->fetchAll();

// ── 8. Lots à surveiller (aperçu : 4 max, triés par urgence) ──
$stmt = $pdo->prepare("
    SELECT l.*, g.libelle AS groupe,
           DATEDIFF(l.date_expiration, CURDATE()) AS jours_restants
    FROM lot_sang_sous_banque l
    JOIN groupe_sanguin g ON g.id_groupe = l.id_groupe
    WHERE l.id_sous_banque = ? AND l.statut = 'disponible'
    ORDER BY l.date_expiration ASC
    LIMIT 4
");
$stmt->execute([$id_sb]);
$lots_a_surveiller = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?php echo htmlspecialchars($_SESSION['nom_sb'] ?? 'Dépôt'); ?> | E-Sang</title>

    <style>
        /* ══ RESTRUCTURATION POUR LE DÉFILEMENT VERTICAL COMPLET ══ */
        html, body {
            height: 100%;
            margin: 0;
            overflow: hidden; /* Empêche le double défilement sur la page entière */
        }

        /* On force le conteneur principal à occuper l'écran et à gérer correctement le scroll */
        .main-content {
            height: 100vh;
            overflow-y: auto;       /* Active le défilement vertical uniquement ici */
            box-sizing: border-box;
            padding-bottom: 120px !important; /* LARGE zone blanche de confort pour faire remonter le bas de la page */
        }

        /* ══ GRILLE 4 COLONNES (4 en haut, 4 en bas) ══ */
        .groupes-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }
        @media (max-width: 950px) {
            .groupes-grid { grid-template-columns: repeat(2, 1fr); }
        }

        /* Cartes condensées et esthétiques */
        .clean-card {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .clean-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }

        .clean-card.card-ok       { border-color: #BBF7D0; }
        .clean-card.card-faible   { border-color: #FCD34D; }
        .clean-card.card-critique { border-color: #FDBA74; }
        .clean-card.card-vide     { border-color: #FCA5A5; }

        .card-top { display: flex; justify-content: space-between; align-items: center; }

        .blood-group {
            font-size: 16px; font-weight: 800; color: #111111;
            background: #FDF2F2; padding: 4px 10px; border-radius: 6px;
        }

        .status-badge { font-size: 10px; font-weight: 800; padding: 3px 8px; border-radius: 999px; letter-spacing: 0.02em; }
        .card-ok       .status-badge { background: #D1FAE5; color: #065F46; }
        .card-faible   .status-badge { background: #FEF3C7; color: #92400E; }
        .card-critique .status-badge { background: #FFEDD5; color: #9A3412; }
        .card-vide     .status-badge { background: #6B0000; color: #FFFFFF; }

        .card-body { display: flex; align-items: baseline; gap: 6px; margin: 2px 0; }
        .quantity-number { font-size: 28px; font-weight: 800; color: #111111; line-height: 1; }
        .quantity-label { font-size: 12px; color: #555555; font-weight: 500; }

        .card-footer {
            font-size: 11px; color: #666666; border-top: 1px dashed #E5E7EB;
            padding-top: 6px; margin-top: 2px;
        }

        /* ══ ONGLETS ══ */
        .tabs {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #E5E7EB;
            margin-bottom: 20px;
            margin-top: 25px;
        }

        .tab-btn {
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            color: #6B7280;
            background: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
            position: relative;
            bottom: -2px;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab-btn:hover { color: #6B0000; }
        .tab-btn.active {
            color: #6B0000;
            border-bottom-color: #6B0000;
            font-weight: 700;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .badge-count {
            background: #6B0000;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 999px;
        }
        .badge-count-gray {
            background: #E5E7EB;
            color: #374151;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 999px;
        }

        .lot-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 0; border-bottom: 1px solid #F3F4F6; font-size: 13px;
        }
        .lot-row:last-child { border-bottom: none; }
        .lot-jours { font-weight: 700; padding: 3px 10px; border-radius: 999px; font-size: 11px; }
        .lot-jours.urgent   { background: #6B0000; color: #FFFFFF; }
        .lot-jours.proche   { background: #FEF3C7; color: #92400E; }
        .lot-jours.normal   { background: #D1FAE5; color: #065F46; }
    </style>
</head>
<body>

<div class="main-content">

    <div class="page-header">
        <h1>Tableau de bord — <?php echo htmlspecialchars($_SESSION['nom_sb'] ?? '—'); ?></h1>
        <p>
            🏥 Hôpital : <strong><?php echo htmlspecialchars($_SESSION['nom_hopital'] ?? '—'); ?></strong>
            &nbsp;|&nbsp;
            👤 Connecté : <?php echo htmlspecialchars($_SESSION['user_nom'] ?? '—'); ?>
        </p>
    </div>

    <?php if ($nb_rupture > 0): ?>
    <div class="alerte-warning">
        <span>🚨 <?php echo $nb_rupture; ?> groupe(s) sanguin(s) en <strong>rupture totale</strong> dans votre dépôt !</span>
        <a href="demandes.php" style="color:#92400E; font-weight:700; text-decoration:none; white-space:nowrap;">Envoyer une demande →</a>
    </div>
    <?php elseif ($nb_faible > 0): ?>
    <div class="alerte-warning">
        <span>⚠️ <?php echo $nb_faible; ?> groupe(s) sanguin(s) <strong>sous le seuil d'alerte</strong>. Pensez à réapprovisionner.</span>
        <a href="demandes.php" style="color:#92400E; font-weight:700; text-decoration:none; white-space:nowrap;">Envoyer une demande →</a>
    </div>
    <?php endif; ?>

    <?php if ($nb_lots_a_risque > 0): ?>
    <div class="alerte-warning" style="background:#FFFBEB; border-color:#FCD34D; color:#92400E;">
        <span>⏳ <strong><?php echo $nb_lots_a_risque; ?></strong> lot(s) de sang expire(nt) dans 7 jours ou moins.</span>
        <a href="lots.php" style="color:#92400E; font-weight:700; text-decoration:none; white-space:nowrap;">Voir le détail →</a>
    </div>
    <?php endif; ?>

    <div class="stats" style="grid-template-columns:repeat(5,1fr);">
        <div class="stat-card" style="<?php echo $nb_rupture > 0 ? 'border-color:#FCA5A5;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">Total Pochettes</span>
                <span class="stat-icon ic-red">🩸</span>
            </div>
            <span class="stat-number"><?php echo $total_stock; ?></span>
        </div>

        <div class="stat-card" style="<?php echo $nb_rupture > 0 ? 'border-color:#FCA5A5;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">Groupes en rupture</span>
                <span class="stat-icon ic-red">🔴</span>
            </div>
            <span class="stat-number" style="<?php echo $nb_rupture > 0 ? 'color:#6B0000;' : ''; ?>">
                <?php echo $nb_rupture; ?>
            </span>
        </div>

        <div class="stat-card" style="<?php echo $nb_faible > 0 ? 'border-color:#FCD34D;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">Groupes faibles</span>
                <span class="stat-icon ic-org">🟡</span>
            </div>
            <span class="stat-number" style="<?php echo $nb_faible > 0 ? 'color:#92400E;' : ''; ?>">
                <?php echo $nb_faible; ?>
            </span>
        </div>

        <div class="stat-card" style="<?php echo $nb_alertes_actives > 0 ? 'border-color:#FCA5A5;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">Alertes actives</span>
                <span class="stat-icon ic-red">🔔</span>
            </div>
            <span class="stat-number" style="<?php echo $nb_alertes_actives > 0 ? 'color:#6B0000;' : ''; ?>">
                <?php echo $nb_alertes_actives; ?>
            </span>
        </div>

        <div class="stat-card" style="<?php echo $nb_lots_a_risque > 0 ? 'border-color:#FCD34D;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">Lots à risque (7j)</span>
                <span class="stat-icon ic-org">⏳</span>
            </div>
            <span class="stat-number" style="<?php echo $nb_lots_a_risque > 0 ? 'color:#92400E;' : ''; ?>">
                <?php echo $nb_lots_a_risque; ?>
            </span>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <div class="section-title">Réserve par groupe sanguin</div>
            <a href="stock.php" style="font-size:13px; color:#6B0000; font-weight:600; text-decoration:none;">
                Gérer le stock →
            </a>
        </div>

        <div class="groupes-grid">
            <?php foreach ($groupes_stock as $gs):
                $q      = (int)$gs['quantite'];
                $seuil  = (int)$gs['seuil'];

                if ($q === 0) {
                    $style_class = 'card-vide';
                    $badge_label = 'VIDE';
                } elseif ($q <= $seuil) {
                    $style_class = ($q <= ceil($seuil / 2)) ? 'card-critique' : 'card-faible';
                    $badge_label = ($q <= ceil($seuil / 2)) ? 'CRITIQUE' : 'FAIBLE';
                } else {
                    $style_class = 'card-ok';
                    $badge_label = 'OK';
                }
            ?>
            <div class="clean-card <?php echo $style_class; ?>">
                <div class="card-top">
                    <span class="blood-group"><?php echo htmlspecialchars($gs['groupe']); ?></span>
                    <span class="status-badge"><?php echo $badge_label; ?></span>
                </div>
                <div class="card-body">
                    <span class="quantity-number"><?php echo $q; ?></span>
                    <span class="quantity-label">pochette<?php echo $q > 1 ? 's' : ''; ?></span>
                </div>
                <div class="card-footer">
                    Seuil minimal : <?php echo $seuil; ?> poch.
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('tab-lots', this)">
            ⏳ Lots à surveiller
            <?php if ($lots_a_surveiller): ?>
                <span class="badge-count"><?php echo count($lots_a_surveiller); ?></span>
            <?php endif; ?>
        </button>
        <button class="tab-btn" onclick="switchTab('tab-demandes', this)">
            🏦 Dernières demandes envoyées
            <span class="badge-count-gray"><?php echo count($dernieres_demandes); ?></span>
        </button>
    </div>

    <div id="tab-lots" class="tab-content active">
        <div class="section">
            <div class="section-header">
                <div class="section-title">Lots à surveiller</div>
                <a href="stock.php" style="font-size:13px; color:#6B0000; font-weight:600; text-decoration:none;">
                    Voir tout →
                </a>
            </div>
            <?php if ($lots_a_surveiller): ?>
                <?php foreach ($lots_a_surveiller as $l):
                    $j = (int)$l['jours_restants'];
                    if ($j <= 3)      { $cls = 'urgent'; $txt = $j <= 0 ? 'Expiré' : "$j j restants"; }
                    elseif ($j <= 10) { $cls = 'proche'; $txt = "$j j restants"; }
                    else              { $cls = 'normal'; $txt = "$j j restants"; }
                ?>
                <div class="lot-row">
                    <span><span class="badge badge-groupe"><?php echo htmlspecialchars($l['groupe']); ?></span>
                        &nbsp;<?php echo (int)$l['quantite']; ?> poch.
                    </span>
                    <span class="lot-jours <?php echo $cls; ?>"><?php echo $txt; ?></span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vide" style="padding:20px 0;">Aucun lot enregistré pour le moment.</div>
            <?php endif; ?>
        </div>
    </div>

    <div id="tab-demandes" class="tab-content">
        <div class="section">
            <div class="section-header">
                <div class="section-title">Dernières demandes envoyées</div>
                <a href="demandes.php" style="font-size:13px; color:#6B0000; font-weight:600; text-decoration:none;">
                    Voir tout →
                </a>
            </div>
            <?php if ($dernieres_demandes): ?>
                <?php foreach ($dernieres_demandes as $d): ?>
                <div class="lot-row">
                    <span><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span>
                        &nbsp;<?php echo (int)$d['quantite_demandee']; ?> poch. — <?php echo htmlspecialchars($d['nom_banque']); ?>
                    </span>
                    <?php if ($d['statut'] === 'en_attente'): ?>
                        <span class="badge badge-attente">En attente</span>
                    <?php elseif ($d['statut'] === 'acceptée'): ?>
                        <span class="badge badge-ok">Acceptée</span>
                    <?php else: ?>
                        <span class="badge badge-vide">Refusée</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vide" style="padding:20px 0;">Aucune demande envoyée pour le moment.</div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>