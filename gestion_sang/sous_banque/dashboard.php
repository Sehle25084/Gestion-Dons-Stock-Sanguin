<?php
session_start();
require_once '../config/db.php';

// Sécurité : Seuls les utilisateurs connectés avec le rôle sous_banque entrent
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sous_banque') {
    header("Location: ../index.php");
    exit;
}

// Correction : Utilisation d'une valeur de secours si la clé de session est absente
$id_sb = $_SESSION['id_sous_banque'] ?? 0;
$page_active = 'dashboard';

// 1. Somme totale des pochettes dans ce dépôt
$stmt = $pdo->prepare("SELECT SUM(quantite_disponible) FROM stock_sous_banque WHERE id_sous_banque = ?");
$stmt->execute([$id_sb]);
$total_stock = $stmt->fetchColumn() ?: 0;

// 2. Récupération de l'état des stocks par groupe sanguin
$stmt = $pdo->prepare("
    SELECT g.libelle AS groupe, COALESCE(s.quantite_disponible, 0) as quantite, s.date_mise_a_jour
    FROM groupe_sanguin g
    LEFT JOIN stock_sous_banque s ON s.id_groupe = g.id_groupe AND s.id_sous_banque = ?
    ORDER BY g.libelle
");
$stmt->execute([$id_sb]);
$groupes_stock = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dépôt | E-Sang</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php include 'sidebar.php'; ?>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <h1>Tableau de bord — <?php echo htmlspecialchars($_SESSION['nom_sb'] ?? 'Dépôt Sans Nom'); ?></h1>
        <p>Hôpital affilié : <strong><?php echo htmlspecialchars($_SESSION['nom_hopital'] ?? 'Non spécifié'); ?></strong> | Session de : <?php echo htmlspecialchars($_SESSION['user_nom'] ?? 'Utilisateur'); ?></p>
    </div>

    <div class="stats" style="grid-template-columns: repeat(2, 1fr); margin-bottom: 30px;">
        <div class="stat-card c1">
            <div class="stat-card-header">
                <span class="stat-label">Total Général Pochettes (Dépôt)</span>
                <span class="stat-icon" style="background: #FEE2E2; color: #A30000;">🩸</span>
            </div>
            <span class="stat-number"><?php echo (int)$total_stock; ?></span>
        </div>
        <div class="stat-card c3">
            <div class="stat-card-header">
                <span class="stat-label">Statut d'approvisionnement</span>
                <span class="stat-icon" style="background: #E0F2FE; color: #0369A1;">🏥</span>
            </div>
            <span class="stat-number" style="font-size: 24px; margin-top: 12px; font-weight: 700;">Connecté à la Banque</span>
        </div>
    </div>

    <div class="section-title" style="margin-bottom: 15px;">血 Réserve de sang par groupe (Stock d'urgence interne)</div>
    <div class="stats" style="grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 35px;">
        <?php foreach ($groupes_stock as $gs): ?>
            <?php 
                $q = $gs['quantite'];
                $card_class = 'c3'; // Vert par défaut
                if ($q == 0) $card_class = 'c1'; // Rouge si vide
                elseif ($q <= 3) $card_class = 'c2'; // Orange si faible
            ?>
            <div class="stat-card <?php echo $card_class; ?>" style="padding: 20px;">
                <div class="stat-card-header" style="margin-bottom: 5px;">
                    <span class="badge badge-groupe" style="font-size: 16px; padding: 6px 12px;"><?php echo htmlspecialchars($gs['groupe'] ?? ''); ?></span>
                    <span class="stat-icon" style="font-size: 14px; font-weight: 600;">
                        <?php echo $q > 0 ? '✔️ OK' : '⚠️ VIDE'; ?>
                    </span>
                </div>
                <span class="stat-number" style="font-size: 32px;"><?php echo (int)$q; ?> <small style="font-size: 14px; color:#666; font-weight:400;">poches</small></span>
                <div style="font-size: 11px; color: #777; margin-top: 8px;">
                    MàJ : <?php echo $gs['date_mise_a_jour'] ? date('d/m/Y', strtotime($gs['date_mise_a_jour'])) : 'Aucun flux'; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="section">
        <div class="section-header">
            <div class="section-title">Fiche de contrôle des réfrigérateurs du dépôt</div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Groupe Sanguin</th>
                        <th>Quantité en réserve</th>
                        <th>Seuil d'alerte</th>
                        <th>État critique</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groupes_stock as $gs): ?>
                    <tr>
                        <td><span class="badge badge-groupe"><?php echo htmlspecialchars($gs['groupe'] ?? ''); ?></span></td>
                        <td><strong><?php echo (int)$gs['quantite']; ?> pochette(s)</strong></td>
                        <td><span style="color: #666;">3 pochettes</span></td>
                        <td>
                            <?php if ($gs['quantite'] == 0): ?>
                                <span class="badge badge-refusee" style="background:#FEE2E2; color:#991B1B;">Rupture critique</span>
                            <?php elseif ($gs['quantite'] <= 3): ?>
                                <span class="badge badge-attente" style="background:#FEF3C7; color:#92400E;">Réapprovisionner</span>
                            <?php else: ?>
                                <span class="badge badge-acceptee" style="background:#D1FAE5; color:#065F46;">Stock Suffisant</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>