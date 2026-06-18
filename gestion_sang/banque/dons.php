<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'banque') {
    header("Location: ../index.php");
    exit;
}

$id_banque = $_SESSION['id'];
$page_active = 'dons';
$success = "";

if (isset($_GET['accepter'])) {
    $pdo->prepare("UPDATE don SET statut = 'accepté' WHERE id_don = ? AND id_banque = ?")->execute([$_GET['accepter'], $id_banque]);
    $stmt = $pdo->prepare("SELECT * FROM don WHERE id_don = ?");
    $stmt->execute([$_GET['accepter']]);
    $don = $stmt->fetch();
    if ($don) {
        $stmt = $pdo->prepare("SELECT * FROM stock WHERE id_banque = ? AND id_groupe = ?");
        $stmt->execute([$id_banque, $don['id_groupe']]);
        $stock = $stmt->fetch();
        if ($stock) {
            $pdo->prepare("UPDATE stock SET quantite_disponible = quantite_disponible + ?, date_mise_a_jour = CURDATE() WHERE id_stock = ?")->execute([$don['quantite'], $stock['id_stock']]);
        } else {
            $pdo->prepare("INSERT INTO stock (id_banque, id_groupe, quantite_disponible, date_mise_a_jour, date_expiration) VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 42 DAY))")->execute([$id_banque, $don['id_groupe'], $don['quantite']]);
        }
        $success = "Don accepté et ajouté au stock !";
    }
}

if (isset($_GET['refuser'])) {
    $pdo->prepare("UPDATE don SET statut = 'refusé' WHERE id_don = ? AND id_banque = ?")->execute([$_GET['refuser'], $id_banque]);
    $success = "Don marqué comme refusé.";
}

$stmt = $pdo->prepare("
    SELECT don.*, g.libelle AS groupe 
    FROM don 
    JOIN groupe_sanguin g ON g.id_groupe = don.id_groupe 
    WHERE don.id_banque = ? 
    ORDER BY don.date_don DESC
");
$stmt->execute([$id_banque]);
$dons = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dons | E-Sang Banque</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php include 'sidebar.php'; ?>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <h1>Historique des dons</h1>
        <p>Suivez et validez les dons de sang.</p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="section">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Donneur</th><th>Groupe</th><th>Quantité</th><th>Date du Don</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if ($dons): ?>
                        <?php foreach ($dons as $d): ?>
                        <tr>
                            <td>Donneur #<?php echo $d['id_donneur']; ?></td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                            <td><?php echo (int)$d['quantite']; ?> pochette(s)</td>
                            <td><?php echo date('d/m/Y', strtotime($d['date_don'])); ?></td>
                            <td>
                                <?php if ($d['statut'] === 'en_attente'): ?><span class="badge badge-attente">En attente</span>
                                <?php elseif ($d['statut'] === 'accepté'): ?><span class="badge badge-accepte">Accepté</span>
                                <?php else: ?><span class="badge badge-refuse">Refusé</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($d['statut'] === 'en_attente'): ?>
                                    <a href="dons.php?accepter=<?php echo $d['id_don']; ?>" class="btn-accepter">✓ Accepter</a>
                                    <a href="dons.php?refuser=<?php echo $d['id_don']; ?>" class="btn-refuser" onclick="return confirm('Refuser ce don ?')">✕ Refuser</a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="vide">Aucun don enregistré pour le moment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>