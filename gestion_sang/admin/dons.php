<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$dons = $pdo->query("
    SELECT don.*, b.nom AS nom_banque, g.libelle AS groupe
    FROM don
    JOIN banque_de_sang b ON b.id_banque = don.id_banque
    JOIN groupe_sanguin g ON g.id_groupe = don.id_groupe
    ORDER BY don.date_don DESC
")->fetchAll();

$page_active = 'dons';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dons — Admin E-Sang</title>
    <style><?php echo $shared_css; ?></style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1>Liste des dons</h1>
        <p>Tous les dons de sang effectués sur la plateforme.</p>
    </div>

    <div class="section">
        <div class="section-header">
            <div class="section-title">Dons enregistrés</div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Donneur</th>
                        <th>Banque</th>
                        <th>Groupe</th>
                        <th>Quantité</th>
                        <th>Date</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dons): ?>
                        <?php foreach ($dons as $d): ?>
                        <?php
                        $stmt = $pdo_registre->prepare("SELECT nom, prenom FROM citoyen WHERE NNI = (SELECT NNI FROM donneur WHERE id_donneur = ?)");
                        $stmt->execute([$d['id_donneur']]);
                        $citoyen = $stmt->fetch();
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($d['id_donneur']); ?></strong>
                                <?php if ($citoyen): ?>
                                <br><small style="color:#666;font-weight:500;"><?php echo htmlspecialchars($citoyen['prenom'] . ' ' . $citoyen['nom']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($d['nom_banque']); ?></td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                            <td><strong><?php echo $d['quantite']; ?></strong> pochette(s)</td>
                            <td><?php echo date('d/m/Y', strtotime($d['date_don'])); ?></td>
                            <td>
                                <?php if ($d['statut'] === 'en_attente'): ?><span class="badge badge-attente">En attente</span>
                                <?php elseif ($d['statut'] === 'accepté'): ?><span class="badge badge-accepte">Accepté</span>
                                <?php else: ?><span class="badge badge-refuse">Refusé</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="vide">Aucun don enregistré</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
