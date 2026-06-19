<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// ════════════════════════════════════════════════════════
// Fonction utilitaire — Tracer les actions admin
// ════════════════════════════════════════════════════════
function log_action($pdo, $id_admin, $action) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO log_activite (role_utilisateur, id_utilisateur, action, date_action)
            VALUES ('admin', ?, ?, NOW())
        ");
        $stmt->execute([$id_admin, $action]);
    } catch (Exception $e) {
        // Ne pas bloquer si la table n'existe pas
    }
}

$erreur = $success = "";
$id_admin_courant = $_SESSION['id'];

// ════════════════════════════════════════════════════════
// AJOUTER une banque
// ════════════════════════════════════════════════════════
if (isset($_POST['ajouter'])) {
    $nom          = trim($_POST['nom']);
    $wilaya       = trim($_POST['wilaya']);
    $telephone    = trim($_POST['telephone']);
    $email        = trim($_POST['email']);
    $mot_de_passe = trim($_POST['mot_de_passe']);

    if (empty($nom) || empty($email) || empty($mot_de_passe)) {
        $erreur = "Nom, email et mot de passe sont obligatoires.";
    } else {
        // Email unique ?
        $check = $pdo->prepare("SELECT COUNT(*) FROM banque_de_sang WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetchColumn() > 0) {
            $erreur = "Cet email est déjà utilisé par une autre banque.";
        } else {
            $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $pdo->prepare("
                INSERT INTO banque_de_sang (nom, wilaya, telephone, email, mot_de_passe)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$nom, $wilaya, $telephone, $email, $hash]);

            log_action($pdo, $id_admin_courant, "Création banque : $nom");
            $success = "Banque ajoutée avec succès !";
        }
    }
}

// ════════════════════════════════════════════════════════
// MODIFIER une banque (NOUVEAU - selon pi.docx)
// ════════════════════════════════════════════════════════
if (isset($_POST['modifier'])) {
    $id           = $_POST['id_banque'];
    $nom          = trim($_POST['nom']);
    $wilaya       = trim($_POST['wilaya']);
    $telephone    = trim($_POST['telephone']);
    $email        = trim($_POST['email']);
    $mot_de_passe = trim($_POST['mot_de_passe']);

    if (empty($nom) || empty($email)) {
        $erreur = "Nom et email sont obligatoires.";
    } else {
        // Email unique (sauf pour cette banque)
        $check = $pdo->prepare("SELECT COUNT(*) FROM banque_de_sang WHERE email = ? AND id_banque != ?");
        $check->execute([$email, $id]);
        if ($check->fetchColumn() > 0) {
            $erreur = "Cet email est déjà utilisé par une autre banque.";
        } else {
            if (!empty($mot_de_passe)) {
                $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                $pdo->prepare("
                    UPDATE banque_de_sang
                    SET nom = ?, wilaya = ?, telephone = ?, email = ?, mot_de_passe = ?
                    WHERE id_banque = ?
                ")->execute([$nom, $wilaya, $telephone, $email, $hash, $id]);
            } else {
                $pdo->prepare("
                    UPDATE banque_de_sang
                    SET nom = ?, wilaya = ?, telephone = ?, email = ?
                    WHERE id_banque = ?
                ")->execute([$nom, $wilaya, $telephone, $email, $id]);
            }

            log_action($pdo, $id_admin_courant, "Modification banque : $nom");
            $success = "Banque modifiée avec succès !";
        }
    }
}

// ════════════════════════════════════════════════════════
// SUPPRIMER une banque (vérifie toutes les dépendances)
// ════════════════════════════════════════════════════════
if (isset($_GET['supprimer'])) {
    $id_banque = $_GET['supprimer'];

    // Récupérer le nom pour le log
    $stmt = $pdo->prepare("SELECT nom FROM banque_de_sang WHERE id_banque = ?");
    $stmt->execute([$id_banque]);
    $banque_nom = $stmt->fetchColumn();

    // Vérifier dons liés
    $check_dons = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_banque = ?");
    $check_dons->execute([$id_banque]);
    $nb_dons = $check_dons->fetchColumn();

    // Vérifier demandes liées
    $check_dem = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_banque = ?");
    $check_dem->execute([$id_banque]);
    $nb_dem = $check_dem->fetchColumn();

    // Vérifier sous-banques rattachées (nouvelle BD)
    $nb_sb = 0;
    try {
        $check_sb = $pdo->prepare("SELECT COUNT(*) FROM sous_banque WHERE id_banque_principale = ?");
        $check_sb->execute([$id_banque]);
        $nb_sb = $check_sb->fetchColumn();
    } catch (Exception $e) {}

    // Vérifier stock
    $check_stk = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE id_banque = ?");
    $check_stk->execute([$id_banque]);
    $nb_stk = $check_stk->fetchColumn();

    if ($nb_dons > 0 || $nb_dem > 0 || $nb_sb > 0 || $nb_stk > 0) {
        $details = [];
        if ($nb_dons > 0) $details[] = "$nb_dons don(s)";
        if ($nb_dem > 0)  $details[] = "$nb_dem demande(s)";
        if ($nb_sb > 0)   $details[] = "$nb_sb sous-banque(s) rattachée(s)";
        if ($nb_stk > 0)  $details[] = "$nb_stk ligne(s) de stock";
        $erreur = "Impossible de supprimer cette banque : " . implode(', ', $details) . " liée(s) dans le système.";
    } else {
        $pdo->prepare("DELETE FROM banque_de_sang WHERE id_banque = ?")->execute([$id_banque]);
        log_action($pdo, $id_admin_courant, "Suppression banque : $banque_nom");
        $success = "Banque supprimée avec succès !";
    }
}

// ════════════════════════════════════════════════════════
// CHARGEMENT DES DONNÉES + STATISTIQUES
// ════════════════════════════════════════════════════════
$banques = $pdo->query("SELECT * FROM banque_de_sang ORDER BY nom")->fetchAll();

// Stats
$nb_total = count($banques);
$nb_wilayas = count(array_unique(array_filter(array_column($banques, 'wilaya'))));

// Banques avec stock
$nb_avec_stock = $pdo->query("
    SELECT COUNT(DISTINCT id_banque) FROM stock WHERE quantite_disponible > 0
")->fetchColumn();

// Banques ayant des sous-banques
$nb_avec_sb = 0;
try {
    $nb_avec_sb = $pdo->query("
        SELECT COUNT(DISTINCT id_banque_principale) FROM sous_banque
    ")->fetchColumn();
} catch (Exception $e) {}

$page_active = 'banques';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banques de sang — Admin E-Sang</title>
    <style>
        <?php echo $shared_css; ?>
        .modal-modifier { max-width: 540px; }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <!-- ══ BANDEAU ADMIN CONNECTÉ ══ -->
    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar"><?php echo $admin_initials; ?></div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bienvenue, <?php echo $admin_display; ?></div>
                <div class="top-bar-role">Espace administrateur</div>
            </div>
        </div>
    </div>

    <!-- ══ TITRE + BOUTON ══ -->
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>Banques de sang</h1>
            <p>Gérez les centres de traitement et de collecte de sang partenaires.</p>
        </div>
        <button class="btn-submit" onclick="toggleModal('addModal', true)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nouvelle banque
        </button>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur);  ?></div><?php endif; ?>

    <!-- ══ STATISTIQUES COMPACTES (style harmonisé avec dashboard) ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🏦</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total banques</div>
                <div class="stat-mini-number"><?php echo $nb_total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">📍</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Wilayas couvertes</div>
                <div class="stat-mini-number"><?php echo $nb_wilayas; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">🩸</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Avec stock</div>
                <div class="stat-mini-number"><?php echo $nb_avec_stock; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">🏪</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Avec sous-banques</div>
                <div class="stat-mini-number"><?php echo $nb_avec_sb; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ TABLEAU ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Établissements enregistrés</div>
            <span class="cnt-badge"><?php echo $nb_total; ?> banque(s)</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Wilaya</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($banques): ?>
                        <?php foreach ($banques as $b): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($b['nom']); ?></strong></td>
                            <td>
                                <?php if (!empty($b['wilaya'])): ?>
                                    <span class="badge badge-wilaya">📍 <?php echo htmlspecialchars($b['wilaya']); ?></span>
                                <?php else: ?>
                                    <span style="color:#9CA3AF;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($b['telephone'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($b['email']); ?></td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-edit"
                                            onclick="ouvrirModifier(
                                                <?php echo $b['id_banque']; ?>,
                                                '<?php echo addslashes($b['nom']); ?>',
                                                '<?php echo addslashes($b['wilaya'] ?? ''); ?>',
                                                '<?php echo addslashes($b['telephone'] ?? ''); ?>',
                                                '<?php echo addslashes($b['email']); ?>'
                                            )">
                                        ✏️ Modifier
                                    </button>
                                    <a href="banques.php?supprimer=<?php echo $b['id_banque']; ?>"
                                       class="btn-del"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette banque de sang ?')">
                                        🗑️ Supprimer
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="vide">Aucune banque enregistrée pour le moment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════ -->
<!-- MODAL — AJOUTER une banque                                 -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Ajouter une banque</div>
            <button class="modal-close" onclick="toggleModal('addModal', false)">×</button>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label>Nom de la banque <span class="req">*</span></label>
                <input type="text" name="nom" placeholder="Ex: Banque Centrale" required />
            </div>
            <div class="form-group">
                <label>Wilaya</label>
                <input type="text" name="wilaya" placeholder="Ex: Nouakchott" />
            </div>
            <div class="form-group">
                <label>Téléphone</label>
                <input type="text" name="telephone" placeholder="Ex: 22000000" />
            </div>
            <div class="form-group">
                <label>Email <span class="req">*</span></label>
                <input type="email" name="email" placeholder="Ex: contact@banque.mr" required />
            </div>
            <div class="form-group">
                <label>Mot de passe <span class="req">*</span></label>
                <input type="password" name="mot_de_passe" placeholder="Minimum 6 caractères" required />
            </div>
            <button type="submit" name="ajouter" class="btn-submit" style="width: 100%; margin-top: 8px;">
                Enregistrer la banque
            </button>
        </form>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════ -->
<!-- MODAL — MODIFIER une banque (NOUVEAU - selon pi.docx)      -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="modal" id="editModal">
    <div class="modal-content modal-modifier">
        <div class="modal-header">
            <div class="modal-title">Modifier la banque</div>
            <button class="modal-close" onclick="toggleModal('editModal', false)">×</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="id_banque" id="edit_id" />

            <div class="form-group">
                <label>Nom de la banque <span class="req">*</span></label>
                <input type="text" name="nom" id="edit_nom" required />
            </div>
            <div class="form-group">
                <label>Wilaya</label>
                <input type="text" name="wilaya" id="edit_wilaya" placeholder="Ex: Nouakchott" />
            </div>
            <div class="form-group">
                <label>Téléphone</label>
                <input type="text" name="telephone" id="edit_telephone" placeholder="Ex: 22000000" />
            </div>
            <div class="form-group">
                <label>Email <span class="req">*</span></label>
                <input type="email" name="email" id="edit_email" required />
            </div>
            <div class="form-group">
                <label>
                    Nouveau mot de passe
                    <small style="color:#6B7280; font-weight:500;">(laisser vide pour ne pas changer)</small>
                </label>
                <input type="password" name="mot_de_passe" placeholder="Laisser vide = inchangé" />
            </div>
            <button type="submit" name="modifier" class="btn-submit" style="width: 100%; margin-top: 8px;">
                Enregistrer les modifications
            </button>
        </form>
    </div>
</div>

<script>
function toggleModal(id, show) {
    const modal = document.getElementById(id);
    if (show) modal.classList.add('active');
    else modal.classList.remove('active');
}

function ouvrirModifier(id, nom, wilaya, telephone, email) {
    document.getElementById('edit_id').value         = id;
    document.getElementById('edit_nom').value        = nom;
    document.getElementById('edit_wilaya').value     = wilaya;
    document.getElementById('edit_telephone').value  = telephone;
    document.getElementById('edit_email').value      = email;
    toggleModal('editModal', true);
}

// Fermer modal en cliquant à l'extérieur
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});
</script>

</body>
</html>