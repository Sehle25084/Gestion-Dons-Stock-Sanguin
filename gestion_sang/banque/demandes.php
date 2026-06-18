<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'banque') {
    header("Location: ../index.php");
    exit;
}

$id_banque = $_SESSION['id'];
$page_active = 'demandes';
$success = "";
// DANS BANQUE/DEMANDES.PHP (Ligne ~15 après l'update du mouvement_stock)
// 1. On cherche si l'hôpital possède une sous-banque
$stmt_sb = $pdo->prepare("SELECT id_sous_banque FROM sous_banque WHERE id_hopital = ?");
$stmt_sb->execute([$demande['id_hopital']]);
$sb = $stmt_sb->fetch();

if ($sb) {
    $id_sb_destination = $sb['id_sous_banque'];
    
    // 2. On regarde si ce groupe existe déjà dans le stock de la sous-banque
    $stmt_check = $pdo->prepare("SELECT * FROM stock_sous_banque WHERE id_sous_banque = ? AND id_groupe = ?");
    $stmt_check->execute([$id_sb_destination, $demande['id_groupe']]);
    
    if ($stmt_check->fetch()) {
        // Update s'il existe
        $pdo->prepare("UPDATE stock_sous_banque SET quantite_disponible = quantite_disponible + ?, date_mise_a_jour = CURDATE() WHERE id_sous_banque = ? AND id_groupe = ?")
            ->execute([$demande['quantite_demandee'], $id_sb_destination, $demande['id_groupe']]);
    } else {
        // Insert s'il n'existe pas encore
        $pdo->prepare("INSERT INTO stock_sous_banque (id_sous_banque, id_groupe, quantite_disponible, date_mise_a_jour) VALUES (?, ?, ?, CURDATE())")
            ->execute([$id_sb_destination, $demande['id_groupe'], $demande['quantite_demandee']]);
    }
}
if (isset($_GET['accepter'])) {
    $id_demande = $_GET['accepter'];
    $stmt = $pdo->prepare("SELECT * FROM demande WHERE id_demande = ? AND id_banque = ?");
    $stmt->execute([$id_demande, $id_banque]);
    $demande = $stmt->fetch();
    if ($demande) {
        $pdo->prepare("UPDATE demande SET statut = 'acceptée', date_reponse = CURDATE() WHERE id_demande = ?")->execute([$id_demande]);
        $pdo->prepare("UPDATE stock SET quantite_disponible = quantite_disponible - ?, date_mise_a_jour = CURDATE() WHERE id_banque = ? AND id_groupe = ?")->execute([$demande['quantite_demandee'], $id_banque, $demande['id_groupe']]);
        $pdo->prepare("INSERT INTO mouvement_stock (id_demande, id_banque, id_hopital, id_groupe, quantite, date_mouvement, type_mouvement) VALUES (?, ?, ?, ?, ?, CURDATE(), 'sortie')")->execute([$id_demande, $id_banque, $demande['id_hopital'], $demande['id_groupe'], $demande['quantite_demandee']]);
        $success = "Demande acceptée et stock mis à jour !";
    }
}

if (isset($_GET['refuser'])) {
    $pdo->prepare("UPDATE demande SET statut = 'refusée', date_reponse = CURDATE() WHERE id_demande = ? AND id_banque = ?")->execute([$_GET['refuser'], $id_banque]);
    $success = "Demande refusée.";
}

$stmt = $pdo->prepare("
    SELECT d.*, h.nom AS nom_hopital, g.libelle AS groupe
    FROM demande d
    JOIN hopital h ON h.id_hopital = d.id_hopital
    JOIN groupe_sanguin g ON g.id_groupe = d.id_groupe
    WHERE d.id_banque = ?
    ORDER BY d.date_demande DESC
");
$stmt->execute([$id_banque]);
$demandes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demandes | E-Sang Banque</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php include 'sidebar.php'; ?>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <h1>Demandes des hôpitaux</h1>
        <p>Gérez les demandes de pochettes de sang reçues.</p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="section">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Hôpital</th><th>Groupe</th><th>Quantité</th><th>Date Demande</th><th>Date Réponse</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if ($demandes): ?>
                        <?php foreach ($demandes as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['nom_hopital']); ?></td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                            <td><?php echo (int)$d['quantite_demandee']; ?> pochette(s)</td>
                            <td><?php echo date('d/m/Y', strtotime($d['date_demande'])); ?></td>
                            <td><?php echo $d['date_reponse'] ? date('d/m/Y', strtotime($d['date_reponse'])) : '—'; ?></td>
                            <td>
                                <?php if ($d['statut'] === 'en_attente'): ?><span class="badge badge-attente">En attente</span>
                                <?php elseif ($d['statut'] === 'acceptée'): ?><span class="badge badge-acceptee">Acceptée</span>
                                <?php else: ?><span class="badge badge-refusee">Refusée</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($d['statut'] === 'en_attente'): ?>
                                    <a href="demandes.php?accepter=<?php echo $d['id_demande']; ?>" class="btn-accepter">✓ Accepter</a>
                                    <a href="demandes.php?refuser=<?php echo $d['id_demande']; ?>" class="btn-refuser" onclick="return confirm('Refuser cette demande ?')">✕ Refuser</a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="vide">Aucune demande reçue pour le moment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>