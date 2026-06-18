<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'donneur') {
    header("Location: ../index.php");
    exit;
}

$id_donneur = $_SESSION['id'];

$stmt = $pdo->prepare("SELECT * FROM donneur WHERE id_donneur = ?");
$stmt->execute([$id_donneur]);
$donneur = $stmt->fetch();

$stmt = $pdo_registre->prepare("SELECT * FROM citoyen WHERE NNI = ?");
$stmt->execute([$donneur['NNI']]);
$citoyen = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT don.*, b.nom AS nom_banque, g.libelle AS groupe
    FROM don
    JOIN banque_de_sang b ON b.id_banque = don.id_banque
    JOIN groupe_sanguin g ON g.id_groupe = don.id_groupe
    WHERE don.id_donneur = ?
    ORDER BY don.date_don DESC
");
$stmt->execute([$id_donneur]);
$dons = $stmt->fetchAll();

$nb_total    = count($dons);
$nb_acceptes = 0; $nb_attente = 0; $nb_refuses = 0;
foreach ($dons as $d) {
    if ($d['statut'] === 'accepté')    $nb_acceptes++;
    if ($d['statut'] === 'en_attente') $nb_attente++;
    if ($d['statut'] === 'refusé')     $nb_refuses++;
}

$page_active = 'dons';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes dons — E-Sang Donneur</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php include 'sidebar.php'; ?>
</head>
<body>

<div class="main-content">

    <div class="page-header">
        <h1>Mes dons</h1>
        <p>Historique complet de tous vos dons de sang.</p>
    </div>

    <!-- Stats -->
    <div class="stats stats-4">
        <div class="stat-card c1">
            <div class="stat-card-header">
                <span class="stat-label">Total dons</span>
                <span class="stat-icon ic-red">💉</span>
            </div>
            <span class="stat-number"><?php echo $nb_total; ?></span>
        </div>
        <div class="stat-card c2">
            <div class="stat-card-header">
                <span class="stat-label">Mes dons acceptés</span>
                <span class="stat-icon ic-grn">✅</span>
            </div>
            <span class="stat-number"><?php echo $nb_acceptes; ?></span>
        </div>
        <div class="stat-card c3">
            <div class="stat-card-header">
                <span class="stat-label">Mes dons en attente</span>
                <span class="stat-icon ic-org">⏳</span>
            </div>
            <span class="stat-number"><?php echo $nb_attente; ?></span>
        </div>
        <div class="stat-card c4">
            <div class="stat-card-header">
                <span class="stat-label">Mes dons refusés</span>
                <span class="stat-icon ic-blu">❌</span>
            </div>
            <span class="stat-number"><?php echo $nb_refuses; ?></span>
        </div>
    </div>

    <!-- Tableau -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Historique des dons</div>
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
                            <td><?php echo htmlspecialchars($d['nom_banque']); ?></td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                            <td><strong><?php echo (int)$d['quantite']; ?></strong> pochette(s)</td>
                            <td><?php echo date('d/m/Y', strtotime($d['date_don'])); ?></td>
                            <td>
                                <?php if ($d['statut'] === 'en_attente'): ?><span class="badge badge-attente">En attente</span>
                                <?php elseif ($d['statut'] === 'accepté'): ?><span class="badge badge-accepte">Accepté</span>
                                <?php else: ?><span class="badge badge-refuse">Refusé</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="vide">💉 Vous n'avez pas encore effectué de don.<br><small>Présentez-vous à une banque de sang pour commencer !</small></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
