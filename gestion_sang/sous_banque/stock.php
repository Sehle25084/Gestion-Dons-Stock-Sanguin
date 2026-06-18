<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sous_banque') {
    header("Location: ../index.php");
    exit;
}

// Correction : Utilisation d'une valeur par défaut pour l'ID
$id_sb = $_SESSION['id_sous_banque'] ?? 0;
$page_active = 'stock';
$success = $erreur = "";

// Traitement de la sortie de sang (Consommation par les services de l'hôpital)
if (isset($_POST['consommer'])) {
    $id_groupe = (int)$_POST['id_groupe'];
    $quantite_a_sortir = (int)$_POST['quantite'];

    // Vérifier si le stock disponible est suffisant
    $stmt = $pdo->prepare("SELECT quantite_disponible FROM stock_sous_banque WHERE id_sous_banque = ? AND id_groupe = ?");
    $stmt->execute([$id_sb, $id_groupe]);
    $stock_actuel = $stmt->fetchColumn();

    if ($stock_actuel !== false && $stock_actuel >= $quantite_a_sortir) {
        // Déduction du stock interne
        $stmt_update = $pdo->prepare("
            UPDATE stock_sous_banque 
            SET quantite_disponible = quantite_disponible - ?, date_mise_a_jour = CURDATE() 
            WHERE id_sous_banque = ? AND id_groupe = ?
        ");
        $stmt_update->execute([$quantite_a_sortir, $id_sb, $id_groupe]);
        $success = "Sortie de $quantite_a_sortir pochette(s) enregistrée avec succès pour les soins de l'hôpital !";
    } else {
        $erreur = "Erreur : Stock insuffisant dans le dépôt pour effectuer cette sortie.";
    }
}

// Récupérer le stock actuel pour affichage
$stmt = $pdo->prepare("
    SELECT s.*, g.libelle AS groupe 
    FROM stock_sous_banque s 
    JOIN groupe_sanguin g ON g.id_groupe = s.id_groupe 
    WHERE s.id_sous_banque = ? 
    ORDER BY g.libelle
");
$stmt->execute([$id_sb]);
$stocks = $stmt->fetchAll();

// Liste des groupes pour le formulaire de sortie
$groupes = $pdo->query("SELECT * FROM groupe_sanguin ORDER BY libelle")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion du Stock Interne | E-Sang</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php include 'sidebar.php'; ?>
    <style>
        .alert-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #10B981; }
        .alert-error { background: #FEE2E2; color: #991B1B; border: 1px solid #EF4444; }
    </style>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <h1>Gestion du Stock Interne (Frigos)</h1>
        <p>Consultez les réserves et enregistrez les prélèvements pour les transfusions sanguines au sein de l'établissement.</p>
    </div>

    <?php if ($success): ?><div class="alert-box alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur): ?><div class="alert-box alert-error">❌ <?php echo htmlspecialchars($erreur); ?></div><?php endif; ?>

    <div class="section" style="margin-bottom: 30px;">
        <div class="section-title">🩸 Enregistrer une sortie de poche (Utilisation médicale)</div>
        <form method="POST" action="" style="display: flex; gap: 20px; align-items: flex-end; background: #FFFFFF; border: 1px solid #E5E7EB; padding: 24px; border-radius: 12px; margin-top: 15px;">
            <div style="flex: 2;">
                <label style="font-weight: 600; font-size: 13px; color: #374151; display:block; margin-bottom: 8px;">GROUPE SANGUIN REQUIS</label>
                <select name="id_groupe" required style="width: 100%; height: 44px; padding: 0 12px; border: 1px solid #D1D5DB; border-radius: 6px; background:#F9FAFB;">
                    <option value="">-- Choisir le groupe prélevé --</option>
                    <?php foreach ($groupes as $g): ?>
                        <option value="<?php echo $g['id_groupe']; ?>"><?php echo htmlspecialchars($g['libelle'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="flex: 1;">
                <label style="font-weight: 600; font-size: 13px; color: #374151; display:block; margin-bottom: 8px;">NOMBRE DE POCHETTES</label>
                <input type="number" name="quantite" min="1" value="1" required style="width: 100%; height: 44px; padding: 0 12px; border: 1px solid #D1D5DB; border-radius: 6px;">
            </div>

            <button type="submit" name="consommer" class="btn-submit" style="width: auto; padding: 0 30px; height: 44px; margin-top: 0; white-space: nowrap;">
                Confirmer la sortie
            </button>
        </form>
    </div>

    <div class="section">
        <div class="section-title">Inventaire en temps réel de la sous-banque</div>
        <div class="table-wrapper" style="margin-top: 15px;">
            <table>
                <thead>
                    <tr>
                        <th>Groupe Sanguin</th>
                        <th>Pochettes disponibles au frigo</th>
                        <th>Dernier mouvement enregistré</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($stocks): ?>
                        <?php foreach ($stocks as $st): ?>
                        <tr>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($st['groupe'] ?? ''); ?></span></td>
                            <td><strong><?php echo (int)$st['quantite_disponible']; ?> pochette(s)</strong></td>
                            <td><?php echo $st['date_mise_a_jour'] ? date('d/m/Y', strtotime($st['date_mise_a_jour'])) : 'Non renseignée'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="vide">Le stock de votre dépôt est vide. Les volumes s'ajouteront automatiquement lorsque la Banque Principale validera vos demandes.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>