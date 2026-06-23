<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'banque') {
    header("Location: ../index.php");
    exit;
}

// Compatibilité ancienne/nouvelle architecture
$id_banque = $_SESSION['id_banque'] ?? $_SESSION['id'];
$page_active = 'dechets';

// ════════════════════════════════════════════════════════
// CHARGEMENT des déchets (pochettes rejetées)
// ════════════════════════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT 
        d.id_dechet,
        d.raison_rejet,
        d.date_rejet,
        p.code_pochette,
        p.date_collecte,
        p.date_expiration,
        g.libelle AS groupe
    FROM poches_dechets d
    JOIN pochette p       ON p.id_pochette = d.id_pochette
    JOIN groupe_sanguin g ON g.id_groupe   = p.id_groupe
    WHERE p.id_banque = ?
    ORDER BY d.date_rejet DESC
");
$stmt->execute([$id_banque]);
$dechets = $stmt->fetchAll();

// ════════════════════════════════════════════════════════
// STATISTIQUES
// ════════════════════════════════════════════════════════
$nb_total = count($dechets);

// Par raison
$nb_expires = $nb_autres = 0;
$dechets_par_groupe = [];
foreach ($dechets as $d) {
    if (stripos($d['raison_rejet'], 'expir') !== false) {
        $nb_expires++;
    } else {
        $nb_autres++;
    }
    $g = $d['groupe'];
    $dechets_par_groupe[$g] = ($dechets_par_groupe[$g] ?? 0) + 1;
}

// Du mois en cours
$nb_ce_mois = 0;
$debut_mois = date('Y-m-01');
foreach ($dechets as $d) {
    if ($d['date_rejet'] >= $debut_mois) $nb_ce_mois++;
}

require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Déchets — <?php echo htmlspecialchars($_SESSION['nom_banque'] ?? 'Banque'); ?> | E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        .raison-tag {
            display:inline-flex; align-items:center; gap:5px;
            font-weight:700; padding:4px 10px; border-radius:8px; font-size:11px;
        }
        .raison-expire { background:#FEE2E2; color:#B91C1C; }
        .raison-autre  { background:#FEF3C7; color:#92400E; }

        .repartition-card {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 24px;
        }
        .repartition-title {
            font-size: 13px; font-weight: 700; color: #6B7280;
            text-transform: uppercase; letter-spacing: 0.5px;
            margin-bottom: 14px;
        }
        .repartition-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 12px;
        }
        .repartition-cell {
            display: flex; flex-direction: column; align-items: center;
            padding: 12px;
            background: #FAFAFA;
            border-radius: 10px;
        }
        .repartition-cell .groupe-name {
            font-size: 13px; font-weight: 700; color: #111111;
            margin-bottom: 4px;
        }
        .repartition-cell .groupe-nb {
            font-size: 22px; font-weight: 800; color: #8B0000;
        }
        .repartition-cell .groupe-lbl {
            font-size: 10px; color: #9CA3AF; margin-top: 2px;
        }
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
                <div class="top-bar-role">Agent — <?php echo htmlspecialchars($_SESSION['nom_banque'] ?? 'Banque de sang'); ?></div>
            </div>
        </div>
    </div>

    <!-- ══ TITRE ══ -->
    <div class="page-header">
        <h1>Pochettes rejetées</h1>
        <p>Liste des pochettes expirées ou inutilisables.</p>
    </div>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🗑️</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total déchets</div>
                <div class="stat-mini-number"><?php echo $nb_total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">⏰</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Expirées</div>
                <div class="stat-mini-number"><?php echo $nb_expires; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">📅</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Ce mois-ci</div>
                <div class="stat-mini-number"><?php echo $nb_ce_mois; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ RÉPARTITION PAR GROUPE SANGUIN ══ -->
    <?php if (!empty($dechets_par_groupe)): ?>
    <div class="repartition-card">
        <div class="repartition-title">📊 Répartition par groupe sanguin</div>
        <div class="repartition-grid">
            <?php foreach ($dechets_par_groupe as $groupe => $nb): ?>
                <div class="repartition-cell">
                    <div class="groupe-name"><?php echo htmlspecialchars($groupe); ?></div>
                    <div class="groupe-nb"><?php echo $nb; ?></div>
                    <div class="groupe-lbl">pochette<?php echo $nb > 1 ? 's' : ''; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ TABLEAU DES DÉCHETS ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Historique détaillé</div>
            <span class="cnt-badge"><?php echo $nb_total; ?> pochette(s) rejetée(s)</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Code Pochette</th>
                        <th>Groupe</th>
                        <th>Date collecte</th>
                        <th>Date expiration</th>
                        <th>Raison du rejet</th>
                        <th>Date rejet</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dechets): ?>
                        <?php foreach ($dechets as $d): ?>
                            <?php $is_expire = stripos($d['raison_rejet'], 'expir') !== false; ?>
                            <tr>
                                <td>
                                    <strong style="font-family:'Courier New', monospace; font-size:12px;">
                                        <?php echo htmlspecialchars($d['code_pochette']); ?>
                                    </strong>
                                </td>
                                <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                                <td><?php echo date('d/m/Y', strtotime($d['date_collecte'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($d['date_expiration'])); ?></td>
                                <td>
                                    <span class="raison-tag <?php echo $is_expire ? 'raison-expire' : 'raison-autre'; ?>">
                                        <?php echo $is_expire ? '⏰' : '⚠️'; ?>
                                        <?php echo htmlspecialchars($d['raison_rejet']); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo date('d/m/Y', strtotime($d['date_rejet'])); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="vide">Aucune pochette rejetée pour le moment. 🎉</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>