<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$nb_banques  = $pdo->query("SELECT COUNT(*) FROM banque_de_sang")->fetchColumn();
$nb_hopitaux = $pdo->query("SELECT COUNT(*) FROM hopital")->fetchColumn();
$nb_donneurs = $pdo->query("SELECT COUNT(*) FROM donneur")->fetchColumn();
$nb_dons     = $pdo->query("SELECT COUNT(*) FROM don")->fetchColumn();

$demandes = $pdo->query("
    SELECT d.*, h.nom AS nom_hopital, b.nom AS nom_banque, g.libelle AS groupe
    FROM demande d
    JOIN hopital h ON h.id_hopital = d.id_hopital
    JOIN banque_de_sang b ON b.id_banque = d.id_banque
    JOIN groupe_sanguin g ON g.id_groupe = d.id_groupe
    ORDER BY d.date_demande DESC
    LIMIT 5
")->fetchAll();

$dons = $pdo->query("
    SELECT don.*, g.libelle AS groupe
    FROM don
    JOIN groupe_sanguin g ON g.id_groupe = don.id_groupe
    ORDER BY don.date_don DESC
    LIMIT 5
")->fetchAll();

$page_active = 'dashboard';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — E-Sang</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php include 'sidebar.php'; ?>
    <style>
        .tab-container {
            background: #FFFFFF;
            border: 2px solid #E5E7EB;
            border-radius: 20px;
            padding: 28px;
            margin-top: 24px;
        }
        .tab-headers {
            display: flex;
            gap: 16px;
            border-bottom: 2px solid #F3F4F6;
            margin-bottom: 20px;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 10px 16px;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            font-weight: 600;
            color: #6B7280;
            cursor: pointer;
            position: relative;
            bottom: -2px;
            transition: all 0.2s;
        }
        .tab-btn.active {
            color: #8B0000;
            border-bottom: 2px solid #8B0000;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>

<div class="main-content">

    <div class="page-header">
        <h1>Dashboard administrateur</h1>
        <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['email']); ?> — vue d'ensemble du système.</p>
    </div>

    <div class="stats">
        <div class="stat-card c1">
            <div class="stat-card-header">
                <span class="stat-label">Banques de sang</span>
                <span class="stat-icon ic-red">🏦</span>
            </div>
            <span class="stat-number"><?php echo $nb_banques; ?></span>
        </div>
        <div class="stat-card c2">
            <div class="stat-card-header">
                <span class="stat-label">Hôpitaux</span>
                <span class="stat-icon ic-org">🏥</span>
            </div>
            <span class="stat-number"><?php echo $nb_hopitaux; ?></span>
        </div>
        <div class="stat-card c3">
            <div class="stat-card-header">
                <span class="stat-label">Donneurs</span>
                <span class="stat-icon ic-grn">👤</span>
            </div>
            <span class="stat-number"><?php echo $nb_donneurs; ?></span>
        </div>
        <div class="stat-card c4">
            <div class="stat-card-header">
                <span class="stat-label">Dons effectués</span>
                <span class="stat-icon ic-blu">💉</span>
            </div>
            <span class="stat-number"><?php echo $nb_dons; ?></span>
        </div>
    </div>

    <div class="tab-container">
        <div class="tab-headers">
            <button class="tab-btn active" onclick="switchTab('tab-demandes', this)">Dernières demandes</button>
            <button class="tab-btn" onclick="switchTab('tab-dons', this)">Derniers dons</button>
        </div>

        <!-- Tab Demandes -->
        <div id="tab-demandes" class="tab-content active">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Hôpital</th>
                            <th>Banque</th>
                            <th>Groupe</th>
                            <th>Date</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($demandes): ?>
                            <?php foreach ($demandes as $d): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($d['nom_hopital']); ?></td>
                                <td><?php echo htmlspecialchars($d['nom_banque']); ?></td>
                                <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                                <td><?php echo date('d/m/Y', strtotime($d['date_demande'])); ?></td>
                                <td>
                                    <?php if ($d['statut'] === 'en_attente'): ?>
                                        <span class="badge badge-attente">En attente</span>
                                    <?php elseif ($d['statut'] === 'acceptée'): ?>
                                        <span class="badge badge-acceptee">Acceptée</span>
                                    <?php else: ?>
                                        <span class="badge badge-refusee">Refusée</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="vide">Aucune demande enregistrée</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="text-align: right; margin-top: 15px;">
                <a href="demandes.php" style="color:#8B0000; font-weight:700; text-decoration:none; font-size:13px;">Voir toutes les demandes →</a>
            </div>
        </div>

        <!-- Tab Dons -->
        <div id="tab-dons" class="tab-content">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Donneur</th>
                            <th>Groupe</th>
                            <th>Date</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($dons): ?>
                            <?php foreach ($dons as $d): ?>
                            <tr>
                                <td>Donneur #<?php echo $d['id_donneur']; ?></td>
                                <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
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
                            <tr><td colspan="4" class="vide">Aucun don enregistré</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="text-align: right; margin-top: 15px;">
                <a href="dons.php" style="color:#8B0000; font-weight:700; text-decoration:none; font-size:13px;">Voir tous les dons →</a>
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
