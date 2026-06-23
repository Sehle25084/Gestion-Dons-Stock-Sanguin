<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'donneur') {
    header("Location: ../index.php");
    exit;
}

$id_donneur = $_SESSION['id'];

// ── Données du donneur ──
$stmt = $pdo->prepare("SELECT * FROM donneur WHERE id_donneur = ?");
$stmt->execute([$id_donneur]);
$donneur = $stmt->fetch();

$stmt = $pdo_registre->prepare("SELECT * FROM citoyen WHERE NNI = ?");
$stmt->execute([$donneur['NNI']]);
$citoyen = $stmt->fetch();

// ── Filtre par statut ──
$filtre_statut = $_GET['statut'] ?? 'tous';
$where_filtre = "";
$params = [$id_donneur];

if (in_array($filtre_statut, ['en_attente', 'accepté', 'refusé'])) {
    $where_filtre = " AND don.statut = ?";
    $params[] = $filtre_statut;
}

// ── Tous les dons (avec filtre) ──
$stmt = $pdo->prepare("
    SELECT don.*, b.nom AS nom_banque, g.libelle AS groupe
    FROM don
    JOIN banque_de_sang b ON b.id_banque = don.id_banque
    JOIN groupe_sanguin g ON g.id_groupe = don.id_groupe
    WHERE don.id_donneur = ?
      $where_filtre
    ORDER BY don.date_don DESC
");
$stmt->execute($params);
$dons = $stmt->fetchAll();

// ── Statistiques globales ──
$stmt = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_donneur = ?");
$stmt->execute([$id_donneur]);
$nb_total = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_donneur = ? AND statut = 'accepté'");
$stmt->execute([$id_donneur]);
$nb_acceptes = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_donneur = ? AND statut = 'en_attente'");
$stmt->execute([$id_donneur]);
$nb_attente = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_donneur = ? AND statut = 'refusé'");
$stmt->execute([$id_donneur]);
$nb_refuses = (int)$stmt->fetchColumn();

$page_active = 'dons';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes dons — E-Sang Donneur</title>
    <style>
        <?php echo $shared_css; ?>

        /* ── Filtres ── */
        .filtres-statut {
            display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px;
        }
        .filtre-btn {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            color: #6B7280;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px; font-weight: 700;
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.15s;
        }
        .filtre-btn:hover { background: #FEF2F2; color: #8B0000; border-color: #8B0000; }
        .filtre-btn.active { background: #8B0000; color: #FFFFFF; border-color: #8B0000; }
        .filtre-count {
            background: rgba(255,255,255,0.25);
            padding: 1px 8px; border-radius: 999px;
            font-size: 11px; font-weight: 800;
        }
        .filtre-btn:not(.active) .filtre-count { background: #F3F4F6; color: #6B7280; }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <!-- ══ TOP-BAR ══ -->
    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar"><?php echo $donneur_initials; ?></div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bonjour, <?php echo $donneur_display; ?></div>
                <div class="top-bar-role">Espace donneur</div>
            </div>
        </div>
    </div>

    <!-- ══ TITRE ══ -->
    <div class="page-header">
        <h1>Mes dons</h1>
        <p>Historique complet de tous vos dons de sang.</p>
    </div>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">💉</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total dons</div>
                <div class="stat-mini-number"><?php echo $nb_total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">✅</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Acceptés</div>
                <div class="stat-mini-number"><?php echo $nb_acceptes; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">⏳</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">En attente</div>
                <div class="stat-mini-number <?php echo $nb_attente > 0 ? 'alert' : ''; ?>"><?php echo $nb_attente; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">❌</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Refusés</div>
                <div class="stat-mini-number"><?php echo $nb_refuses; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ TABLEAU AVEC FILTRES ══ -->
    <div class="section">

        <div class="section-header">
            <div class="section-title">Historique détaillé</div>
            <span class="cnt-badge"><?php echo count($dons); ?> don(s) affiché(s)</span>
        </div>

        <div class="filtres-statut">
            <a href="dons.php" class="filtre-btn <?php echo ($filtre_statut === 'tous') ? 'active' : ''; ?>">
                💉 Tous <span class="filtre-count"><?php echo $nb_total; ?></span>
            </a>
            <a href="dons.php?statut=en_attente" class="filtre-btn <?php echo ($filtre_statut === 'en_attente') ? 'active' : ''; ?>">
                ⏳ En attente <span class="filtre-count"><?php echo $nb_attente; ?></span>
            </a>
            <a href="dons.php?statut=accepté" class="filtre-btn <?php echo ($filtre_statut === 'accepté') ? 'active' : ''; ?>">
                ✅ Acceptés <span class="filtre-count"><?php echo $nb_acceptes; ?></span>
            </a>
            <a href="dons.php?statut=refusé" class="filtre-btn <?php echo ($filtre_statut === 'refusé') ? 'active' : ''; ?>">
                ❌ Refusés <span class="filtre-count"><?php echo $nb_refuses; ?></span>
            </a>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Banque</th>
                        <th>Groupe sanguin</th>
                        <th>Quantité</th>
                        <th>Date</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dons): ?>
                        <?php foreach ($dons as $d): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($d['nom_banque']); ?></strong></td>
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
                        <tr><td colspan="5" class="vide">
                            <?php if ($filtre_statut !== 'tous'): ?>
                                Aucun don avec ce statut.
                            <?php else: ?>
                                💉 Vous n'avez pas encore effectué de don.<br>
                                <small>Présentez-vous à une banque de sang pour commencer !</small>
                            <?php endif; ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>