<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$demandes = $pdo->query("
    SELECT d.*, h.nom AS nom_hopital, b.nom AS nom_banque, g.libelle AS groupe
    FROM demande d
    JOIN hopital h ON h.id_hopital = d.id_hopital
    JOIN banque_de_sang b ON b.id_banque = d.id_banque
    JOIN groupe_sanguin g ON g.id_groupe = d.id_groupe
    ORDER BY d.date_demande DESC
")->fetchAll();

$page_active = 'demandes';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demandes — Admin E-Sang</title>
    <style><?php echo $shared_css; ?></style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1>Liste des demandes</h1>
        <p>Toutes les demandes de sang émises par les hôpitaux.</p>
    </div>

    <div class="section">
        <div class="section-header">
            <div class="section-title">Demandes enregistrées</div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Hôpital</th>
                        <th>Banque</th>
                        <th>Groupe</th>
                        <th>Quantité</th>
                        <th>Date demande</th>
                        <th>Date réponse</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($demandes): ?>
                        <?php foreach ($demandes as $d): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($d['nom_hopital']); ?></strong></td>
                            <td><?php echo htmlspecialchars($d['nom_banque']); ?></td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                            <td><strong><?php echo (int)$d['quantite_demandee']; ?></strong> pochette(s)</td>
                            <td><?php echo date('d/m/Y', strtotime($d['date_demande'])); ?></td>
                            <td><?php echo $d['date_reponse'] ? date('d/m/Y', strtotime($d['date_reponse'])) : '—'; ?></td>
                            <td>
                                <?php if ($d['statut'] === 'en_attente'): ?><span class="badge badge-attente">En attente</span>
                                <?php elseif ($d['statut'] === 'acceptée'): ?><span class="badge badge-acceptee">Acceptée</span>
                                <?php else: ?><span class="badge badge-refusee">Refusée</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="vide">Aucune demande enregistrée</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>