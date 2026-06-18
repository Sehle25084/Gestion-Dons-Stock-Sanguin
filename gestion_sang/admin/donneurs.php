<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$success = "";
if (isset($_GET['supprimer'])) {
    $pdo->prepare("DELETE FROM donneur WHERE id_donneur = ?")->execute([$_GET['supprimer']]);
    $success = "Donneur supprimé avec succès !";
}

$donneurs = $pdo->query("SELECT * FROM donneur ORDER BY id_donneur DESC")->fetchAll();
$page_active = 'donneurs';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donneurs — Admin E-Sang</title>
    <style><?php echo $shared_css; ?></style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <h1>Gestion des donneurs</h1>
        <p>Liste de tous les donneurs inscrits sur la plateforme.</p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="alerte-info">
        ℹ️ Le groupe sanguin est confirmé par les banques de sang après analyse en laboratoire.
    </div>

    <div class="section">
        <div class="section-header">
            <div class="section-title">Liste des donneurs</div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>NNI / Identité</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Groupe sanguin</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($donneurs): ?>
                        <?php foreach ($donneurs as $d): ?>
                        <?php
                        $stmt = $pdo_registre->prepare("SELECT nom, prenom FROM citoyen WHERE NNI = ?");
                        $stmt->execute([$d['NNI']]);
                        $citoyen = $stmt->fetch();
                        $groupe = "—";
                        if ($d['id_groupe']) {
                            $stmt2 = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
                            $stmt2->execute([$d['id_groupe']]);
                            $g = $stmt2->fetch();
                            if ($g) $groupe = $g['libelle'];
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($d['NNI']); ?></strong>
                                <?php if ($citoyen): ?>
                                <br><small style="color:#666;font-weight:500;"><?php echo htmlspecialchars($citoyen['prenom'] . ' ' . $citoyen['nom']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($d['email']) ?: '—'; ?></td>
                            <td><?php echo htmlspecialchars($d['telephone']) ?: '—'; ?></td>
                            <td>
                                <?php if ($d['id_groupe']): ?>
                                    <span class="badge badge-groupe"><?php echo htmlspecialchars($groupe); ?></span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td>
                                <?php if ($d['id_groupe']): ?>
                                    <span class="badge badge-verifie">Vérifié</span>
                                <?php else: ?>
                                    <span class="badge badge-non-verifie">En attente banque</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="donneurs.php?supprimer=<?php echo $d['id_donneur']; ?>"
                                   class="btn-del"
                                   onclick="return confirm('Supprimer ce donneur ?')">Supprimer</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="vide">Aucun donneur enregistré</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
