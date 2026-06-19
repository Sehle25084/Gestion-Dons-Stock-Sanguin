<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'banque') {
    header("Location: ../index.php");
    exit;
}

$id_banque = $_SESSION['id'];
$page_active = 'donneurs';
$success = "";

if (isset($_POST['confirmer'])) {
    $id_donneur = $_POST['id_donneur'];
    $id_groupe  = $_POST['id_groupe'];
    $pdo->prepare("UPDATE donneur SET id_groupe = ?, groupe_auto_declare = COALESCE(groupe_auto_declare, ?), groupe_confirme = 1 WHERE id_donneur = ?")->execute([$id_groupe, $id_groupe, $id_donneur]);
    $success = "Groupe sanguin confirmé avec succès !";
}

// Récupération des donneurs non vérifiés
$non_verifies = $pdo->query("SELECT * FROM donneur WHERE groupe_confirme = 0 ORDER BY id_donneur DESC")->fetchAll();

// Récupération de tous les donneurs vérifiés
$tous = $pdo->query("SELECT * FROM donneur WHERE groupe_confirme = 1 ORDER BY id_donneur DESC")->fetchAll();

// Récupération des groupes sanguins pour le sélecteur
$groupes = $pdo->query("SELECT * FROM groupe_sanguin ORDER BY libelle")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donneurs | E-Sang Banque</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php include 'sidebar.php'; ?>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <h1>Gestion des donneurs</h1>
        <p>Valisez et visualisez les profils des donneurs.</p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <?php if ($non_verifies): ?>
    <div class="section" style="border-left: 4px solid #FF9800; margin-bottom: 25px;">
        <div class="section-title" style="color: #FF9800; margin-bottom: 15px;">⚠️ Comptes en attente de groupe sanguin</div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>NNI</th>
                        <th>Nom complet</th>
                        <th>Email</th>
                        <th>Attribuer Groupe</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($non_verifies as $dv): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="id_donneur" value="<?php echo $dv['id_donneur']; ?>">
                        <tr>
                            <td><?php echo htmlspecialchars($dv['NNI'] ?? '—'); ?></td>
                            <td>
                                <?php 
                                    // Sécurité : on teste les différentes possibilités de noms dans ta base
                                    if (isset($dv['nom_complet'])) {
                                        echo htmlspecialchars($dv['nom_complet']);
                                    } elseif (isset($dv['prenom']) || isset($dv['nom'])) {
                                        echo htmlspecialchars(($dv['prenom'] ?? '') . ' ' . ($dv['nom'] ?? ''));
                                    } else {
                                        echo "Donneur #" . $dv['id_donneur'];
                                    }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($dv['email'] ?? '—'); ?></td>
                            <td>
                                <select name="id_groupe" required style="padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                    <option value="">— Choisir —</option>
                                    <?php foreach ($groupes as $g): ?>
                                    <option value="<?php echo $g['id_groupe']; ?>"><?php echo htmlspecialchars($g['libelle']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button type="submit" name="confirmer" class="btn-accepter" style="border:none; cursor:pointer; padding: 6px 12px; border-radius: 4px; background-color: #2e7d32; color: white;">✓ Valider</button>
                            </td>
                        </tr>
                    </form>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="section">
        <div class="section-title" style="margin-bottom: 15px;">Base des donneurs vérifiés</div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>NNI</th>
                        <th>Nom complet</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Groupe</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($tous): ?>
                        <?php foreach ($tous as $d): ?>
                        <?php 
                            // Trouver le libellé du groupe sanguin correspondant
                            $groupe_libelle = "—";
                            foreach ($groupes as $g) {
                                if ($g['id_groupe'] == $d['id_groupe']) {
                                    $groupe_libelle = $g['libelle'];
                                    break;
                                }
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['NNI'] ?? '—'); ?></td>
                            <td>
                                <?php 
                                    // Sécurité identique pour le second tableau
                                    if (isset($d['nom_complet'])) {
                                        echo htmlspecialchars($d['nom_complet']);
                                    } elseif (isset($d['prenom']) || isset($d['nom'])) {
                                        echo htmlspecialchars(($d['prenom'] ?? '') . ' ' . ($d['nom'] ?? ''));
                                    } else {
                                        echo "Donneur #" . $d['id_donneur'];
                                    }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($d['email'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($d['telephone'] ?? '—'); ?></td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($groupe_libelle); ?></span></td>
                            <td><span class="badge badge-verifie" style="background-color: #e8f5e9; color: #2e7d32; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">Vérifié</span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="vide">Aucun donneur validé pour le moment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>