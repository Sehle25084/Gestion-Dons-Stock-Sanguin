<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'banque') {
    header("Location: ../index.php");
    exit;
}

$id_banque = $_SESSION['id'];
$page_active = 'dashboard';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_banque = ?");
$stmt->execute([$id_banque]);
$nb_dons = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_banque = ?");
$stmt->execute([$id_banque]);
$nb_demandes = $stmt->fetchColumn();

$nb_donneurs_non_verifies = $pdo->query("SELECT COUNT(*) FROM donneur WHERE id_groupe IS NULL")->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(quantite_disponible) FROM stock WHERE id_banque = ?");
$stmt->execute([$id_banque]);
$nb_stock = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("
    SELECT d.*, h.nom AS nom_hopital, g.libelle AS groupe
    FROM demande d
    JOIN hopital h ON h.id_hopital = d.id_hopital
    JOIN groupe_sanguin g ON g.id_groupe = d.id_groupe
    WHERE d.id_banque = ?
    ORDER BY d.date_demande DESC
    LIMIT 5
");
$stmt->execute([$id_banque]);
$demandes = $stmt->fetchAll();

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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | E-Sang Banque</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php include 'sidebar.php'; ?>
    <style>
        .tab-container { background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 12px; padding: 24px; margin-top: 24px; }
        .tab-headers { display: flex; gap: 16px; border-bottom: 2px solid #F3F4F6; margin-bottom: 20px; }
        .tab-btn { background: none; border: none; padding: 10px 16px; font-family: 'Inter', sans-serif; font-size: 15px; font-weight: 600; color: #6B7280; cursor: pointer; position: relative; bottom: -2px; transition: all 0.2s; }
        .tab-btn.active { color: #8B0000; border-bottom: 2px solid #8B0000; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['nom']); ?> — vue d'ensemble de votre banque de sang.</p>
    </div>

    <div class="stats">
        <div class="stat-card c1">
            <div class="stat-card-header">
                <span class="stat-label">Pochettes en stock</span>
                <span class="stat-icon ic-red">🩸</span>
            </div>
            <span class="stat-number"><?php echo (int)$nb_stock; ?></span>
        </div>
        <div class="stat-card c2">
            <div class="stat-card-header">
                <span class="stat-label">Dons reçus</span>
                <span class="stat-icon ic-org">📅</span>
            </div>
            <span class="stat-number"><?php echo $nb_dons; ?></span>
        </div>
        <div class="stat-card c3">
            <div class="stat-card-header">
                <span class="stat-label">Demandes reçues</span>
                <span class="stat-icon ic-grn">📋</span>
            </div>
            <span class="stat-number"><?php echo $nb_demandes; ?></span>
        </div>
        <div class="stat-card c4">
            <div class="stat-card-header">
                <span class="stat-label">Groupes à confirmer</span>
                <span class="stat-icon ic-blu">👤</span>
            </div>
            <span class="stat-number"><?php echo $nb_donneurs_non_verifies; ?></span>
        </div>
    </div>

    <div class="tab-container">
        <div class="tab-headers">
            <button class="tab-btn active" onclick="switchTab('tab-demandes', this)">Dernières demandes</button>
            <button class="tab-btn" onclick="switchTab('tab-dons', this)">Derniers dons</button>
        </div>

        <div id="tab-demandes" class="tab-content active">
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Hôpital</th><th>Groupe</th><th>Quantité</th><th>Statut</th></tr></thead>
                    <tbody>
                        <?php if ($demandes): ?>
                            <?php foreach ($demandes as $d): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($d['nom_hopital']); ?></td>
                                <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                                <td><?php echo (int)$d['quantite_demandee']; ?> pochette(s)</td>
                                <td>
                                    <?php if ($d['statut'] === 'en_attente'): ?><span class="badge badge-attente">En attente</span>
                                    <?php elseif ($d['statut'] === 'acceptée'): ?><span class="badge badge-acceptee">Acceptée</span>
                                    <?php else: ?><span class="badge badge-refusee">Refusée</span><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="vide">Aucune demande reçue</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="text-align: right; margin-top: 15px;"><a href="demandes.php" style="color:#8B0000; font-weight:600; text-decoration:none;">Voir toutes les demandes →</a></div>
        </div>

        <div id="tab-dons" class="tab-content">
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>Donneur</th><th>Groupe</th><th>Quantité</th><th>Statut</th></tr></thead>
                    <tbody>
                        <?php if ($dons): ?>
                            <?php foreach ($dons as $d): ?>
                            <tr>
                                <td>Donneur #<?php echo $d['id_donneur']; ?></td>
                                <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                                <td><?php echo (int)$d['quantite']; ?> pochette(s)</td>
                                <td>
                                    <?php if ($d['statut'] === 'en_attente'): ?><span class="badge badge-attente">En attente</span>
                                    <?php elseif ($d['statut'] === 'accepté'): ?><span class="badge badge-accepte">Accepté</span>
                                    <?php else: ?><span class="badge badge-refuse">Refusé</span><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="vide">Aucun don enregistré</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="text-align: right; margin-top: 15px;"><a href="dons.php" style="color:#8B0000; font-weight:600; text-decoration:none;">Voir tous les dons →</a></div>
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