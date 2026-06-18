<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$erreur = $success = "";

if (isset($_POST['ajouter'])) {
    $nom = trim($_POST['nom']); 
    $wilaya = trim($_POST['wilaya']);
    $telephone = trim($_POST['telephone']); 
    $email = trim($_POST['email']);
    $mot_de_passe = trim($_POST['mot_de_passe']);
    
    if (empty($nom) || empty($email) || empty($mot_de_passe)) {
        $erreur = "Nom, email et mot de passe sont obligatoires.";
    } else {
        $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO hopital (nom, wilaya, telephone, email, mot_de_passe) VALUES (?,?,?,?,?)")->execute([$nom,$wilaya,$telephone,$email,$hash]);
        $success = "Hôpital ajouté avec succès !";
    }
}

// 🔒 Suppression sécurisée : Vérification des contraintes d'intégrité
if (isset($_GET['supprimer'])) {
    $id_hopital = $_GET['supprimer'];
    
    $check_demandes = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_hopital = ?");
    $check_demandes->execute([$id_hopital]);
    $nb_demandes = $check_demandes->fetchColumn();
    
    if ($nb_demandes > 0) {
        $erreur = "Impossible de supprimer cet hôpital car il a émis des demandes enregistrées dans l'historique.";
    } else {
        $pdo->prepare("DELETE FROM hopital WHERE id_hopital = ?")->execute([$id_hopital]);
        $success = "Hôpital supprimé avec succès !";
    }
}

$hopitaux = $pdo->query("SELECT * FROM hopital ORDER BY nom")->fetchAll();
$page_active = 'hopitaux';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hôpitaux — Admin E-Sang</title>
    <style>
        <?php echo $shared_css; ?>
        /* Styles spécifiques pour le fonctionnement de la Modal */
        .modal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.4); display: flex; align-items: center;
            justify-content: center; z-index: 2000; opacity: 0; pointer-events: none;
            transition: opacity 0.2s ease;
        }
        .modal.active { opacity: 1; pointer-events: auto; }
        .modal-content {
            background: #fff; padding: 30px; border-radius: 16px; width: 100%;
            max-width: 480px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            position: relative; transform: translateY(-20px); transition: transform 0.2s ease;
        }
        .modal.active .modal-content { transform: translateY(0); }
        .modal-close {
            position: absolute; top: 20px; right: 20px; background: none; border: none;
            font-size: 22px; color: #6B7280; cursor: pointer; font-weight: bold;
        }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>Hôpitaux partenaires</h1>
            <p>Gérez les structures hospitalières habilitées à demander des pochettes de sang.</p>
        </div>
        <button class="btn-submit" onclick="toggleModal(true)" style="width: auto; padding: 12px 20px; margin: 0;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nouvel hôpital
        </button>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur): ?><div style="padding:14px; margin-bottom:24px; border-radius:10px; background:#FEF2F2; border: 1px solid #FCA5A5; color:#B91C1C; font-weight:600; font-size:14px;">⚠️ <?php echo htmlspecialchars($erreur); ?></div><?php endif; ?>

    <div class="section">
        <div class="section-header">
            <div class="section-title">Hôpitaux enregistrés</div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Wilaya</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($hopitaux): ?>
                        <?php foreach ($hopitaux as $h): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($h['nom']); ?></strong></td>
                            <td><?php echo htmlspecialchars($h['wilaya'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($h['telephone'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($h['email']); ?></td>
                            <td>
                                <a href="hopitaux.php?supprimer=<?php echo $h['id_hopital']; ?>" class="btn-del" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet hôpital ?')">Supprimer</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="vide">Aucun hôpital enregistré pour le moment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal" id="addModal">
    <div class="modal-content">
        <button class="modal-close" onclick="toggleModal(false)">×</button>
        <h2 style="margin-bottom: 20px; font-size: 20px; font-weight: 700;">Ajouter un hôpital</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label>Nom de l'hôpital *</label>
                <input type="text" name="nom" placeholder="Ex: Hôpital National" required />
            </div>
            <div class="form-group">
                <label>Wilaya</label>
                <input type="text" name="wilaya" placeholder="Ex: Nouakchott" />
            </div>
            <div class="form-group">
                <label>Téléphone</label>
                <input type="text" name="telephone" placeholder="Ex: 22111111" />
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" placeholder="Ex: contact@hopital.mr" required />
            </div>
            <div class="form-group">
                <label>Mot de passe *</label>
                <input type="password" name="mot_de_passe" required />
            </div>
            <button type="submit" name="ajouter" class="btn-submit" style="width: 100%; margin-top: 10px;">Enregistrer l'hôpital</button>
        </form>
    </div>
</div>

<script>
function toggleModal(show) {
    const modal = document.getElementById('addModal');
    if (show) modal.classList.add('active');
    else modal.classList.remove('active');
}
</script>
</body>
</html>