<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'banque') {
    header("Location: ../index.php");
    exit;
}

$id_banque = $_SESSION['id'];
$page_active = 'stock';
$erreur = $success = "";

if (isset($_POST['ajouter'])) {
    $id_groupe       = $_POST['id_groupe'];
    $quantite        = (float)$_POST['quantite']; // Quantité en pochettes
    $date_expiration = $_POST['date_expiration'];
    $date_maj        = date('Y-m-d');

    $stmt = $pdo->prepare("SELECT * FROM stock WHERE id_banque = ? AND id_groupe = ?");
    $stmt->execute([$id_banque, $id_groupe]);
    $existe = $stmt->fetch();

    if ($existe) {
        $pdo->prepare("UPDATE stock SET quantite_disponible = ?, date_mise_a_jour = ?, date_expiration = ? WHERE id_banque = ? AND id_groupe = ?")->execute([$quantite, $date_maj, $date_expiration, $id_banque, $id_groupe]);
        $success = "Stock mis à jour avec succès !";
    } else {
        $pdo->prepare("INSERT INTO stock (id_banque, id_groupe, quantite_disponible, date_mise_a_jour, date_expiration) VALUES (?, ?, ?, ?, ?)")->execute([$id_banque, $id_groupe, $quantite, $date_maj, $date_expiration]);
        $success = "Stock ajouté avec succès !";
    }
}

if (isset($_GET['supprimer'])) {
    $pdo->prepare("DELETE FROM stock WHERE id_stock = ? AND id_banque = ?")->execute([$_GET['supprimer'], $id_banque]);
    $success = "Stock supprimé !";
}

$stmt = $pdo->prepare("SELECT s.*, g.libelle AS groupe FROM stock s JOIN groupe_sanguin g ON g.id_groupe = s.id_groupe WHERE s.id_banque = ? ORDER BY g.libelle");
$stmt->execute([$id_banque]);
$stocks = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT SUM(quantite_disponible) FROM stock WHERE id_banque = ?");
$stmt->execute([$id_banque]);
$total = $stmt->fetchColumn() ?: 0;

$groupes = $pdo->query("SELECT * FROM groupe_sanguin ORDER BY libelle")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock | E-Sang Banque</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php include 'sidebar.php'; ?>
    <style>
        .stat-total {
            background: linear-gradient(135deg, #8B0000, #6B0000);
            border-radius: 20px; padding: 24px 28px;
            margin-bottom: 32px;
            display: flex; align-items: center; gap: 18px;
            box-shadow: 0 8px 24px rgba(139,0,0,0.25);
        }
        .stat-total .icone { font-size: 40px; }
        .stat-total .nombre { font-size: 40px; font-weight: 800; color: #fff; line-height: 1; }
        .stat-total .label  { font-size: 14px; color: rgba(255,255,255,0.8); margin-top: 4px; }

        /* Bouton Ajouter en haut à droite avec écriture blanche */
        .btn-open-modal {
            background-color: #8B0000;
            color: #ffffff;
            padding: 10px 18px;
            border-radius: 8px;
            border: none;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }
        .btn-open-modal:hover {
            background-color: #6B0000;
        }

        /* --- STYLES DE LA FENÊTRE MODALE (POPUP) --- */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            z-index: 9999;
            opacity: 0; pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.active {
            opacity: 1; pointer-events: auto;
        }
        .modal-content {
            background: #fff;
            padding: 32px;
            border-radius: 16px;
            width: 100%; max-width: 450px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
            position: relative;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }
        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }
        .modal-close {
            position: absolute;
            top: 20px; right: 20px;
            font-size: 24px; color: #666;
            cursor: pointer; border: none; background: none;
        }
        .modal-close:hover { color: #000; }
    </style>
</head>
<body>

<div class="main-content">

    <div class="page-header">
        <h1>Gestion du stock</h1>
        <p>Gérez le stock de sang de votre banque.</p>
    </div>

    <?php if ($erreur): ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="stat-total">
        <div class="icone">🩸</div>
        <div>
            <div class="nombre"><?php echo (int)$total; ?></div>
            <div class="label">Pochettes disponibles en stock</div>
        </div>
    </div>

    <div class="section">
        <div class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div class="section-title">Stock actuel</div>
                <span class="cnt-badge"><?php echo count($stocks); ?> groupe(s)</span>
            </div>
            <button class="btn-open-modal" onclick="toggleModal(true)">
                <span style="font-size: 16px;">+</span> Ajouter du stock
            </button>
        </div>
        
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Groupe</th><th>Quantité</th><th>Niveau</th><th>Mise à jour</th><th>Expiration</th></tr>
                </thead>
                <tbody>
                    <?php if ($stocks): ?>
                        <?php foreach ($stocks as $s): ?>
                        <?php
                            $expire = strtotime($s['date_expiration']) < time();
                            $niveau = $s['quantite_disponible'];
                            $pct = min(100, ($niveau / 20) * 100);
                        ?>
                        <tr>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($s['groupe']); ?></span></td>
                            <td>
                                <?php echo (int)$s['quantite_disponible']; ?> pochette(s)
                                <div class="stock-bar"><div class="stock-bar-fill" style="width:<?php echo $pct; ?>%"></div></div>
                            </td>
                            <td>
                                <?php if ($niveau == 0): ?><span class="badge badge-vide">Vide</span>
                                <?php elseif ($niveau <= ($s['seuil_alerte'] ?? 2)): ?><span class="badge badge-faible">Faible</span>
                                <?php else: ?><span class="badge badge-ok">OK</span><?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($s['date_mise_a_jour'])); ?></td>
                            <td class="<?php echo $expire ? 'expire' : ''; ?>">
                                <?php echo date('d/m/Y', strtotime($s['date_expiration'])); ?>
                                <?php if ($expire): ?> ⚠️<?php endif; ?>
                            </td>
                            
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="vide">Aucun stock enregistré pour le moment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<div class="modal-overlay" id="stockModal">
    <div class="modal-content">
        <button class="modal-close" onclick="toggleModal(false)">&times;</button>
        
        <div class="section-header" style="margin-bottom: 20px;">
            <div class="section-title">Ajouter / Modifier du stock</div>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Groupe sanguin *</label>
                <select name="id_groupe" required style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc; margin-top:5px;">
                    <option value="">— Choisir —</option>
                    <?php foreach ($groupes as $g): ?>
                    <option value="<?php echo $g['id_groupe']; ?>"><?php echo htmlspecialchars($g['libelle']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-top: 15px;">
                <label>Quantité (pochettes) *</label>
                <input type="number" name="quantite" placeholder="Ex : 10" min="0" step="1" required style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc; margin-top:5px; box-sizing: border-box;"/>
            </div>
            <div class="form-group" style="margin-top: 15px; margin-bottom: 25px;">
                <label>Date d'expiration *</label>
                <input type="date" name="date_expiration" required style="width:100%; padding:10px; border-radius:6px; border:1px solid #ccc; margin-top:5px; box-sizing: border-box;"/>
            </div>
            <button type="submit" name="ajouter" class="btn-submit" style="width: 100%;">Enregistrer le stock</button>
        </form>
    </div>
</div>

<script>
function toggleModal(show) {
    const modal = document.getElementById('stockModal');
    if (show) {
        modal.classList.add('active');
    } else {
        modal.classList.remove('active');
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('stockModal');
    if (event.target == modal) {
        modal.classList.remove('active');
    }
}
</script>

</body>
</html>