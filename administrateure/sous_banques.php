<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// ════════════════════════════════════════════════════════
// Fonction utilitaire — Tracer les actions admin
// (utilise la nouvelle table log_activite)
// ════════════════════════════════════════════════════════
function log_action($pdo, $id_admin, $action) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO log_activite (role_utilisateur, id_utilisateur, action, date_action)
            VALUES ('admin', ?, ?, NOW())
        ");
        $stmt->execute([$id_admin, $action]);
    } catch (Exception $e) {
        // Ne pas bloquer si la table log_activite n'existe pas encore
    }
}

$erreur = $success = "";
$id_admin_courant = $_SESSION['id'];

// ════════════════════════════════════════════════════════
// AJOUTER une sous-banque
// (structure BD : nom, id_hopital, id_banque_principale,
//                 date_creation, email, mot_de_passe)
// ════════════════════════════════════════════════════════
if (isset($_POST['ajouter'])) {
    $nom                  = trim($_POST['nom']);
    $id_hopital           = $_POST['id_hopital']           ?? '';
    $id_banque_principale = $_POST['id_banque_principale'] ?? '';
    $email                = trim($_POST['email']);
    $mot_de_passe         = trim($_POST['mot_de_passe']);

    if (empty($nom) || empty($id_hopital) || empty($id_banque_principale)
        || empty($email) || empty($mot_de_passe)) {
        $erreur = "Tous les champs marqués d'un * sont obligatoires.";
    }
    else {
        // Email unique ?
        $check = $pdo->prepare("SELECT COUNT(*) FROM sous_banque WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetchColumn() > 0) {
            $erreur = "Cet email est déjà utilisé par une autre sous-banque.";
        }
        else {
            $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $pdo->prepare("
                INSERT INTO sous_banque
                    (nom, id_hopital, id_banque_principale, date_creation, email, mot_de_passe)
                VALUES
                    (?, ?, ?, CURDATE(), ?, ?)
            ")->execute([$nom, $id_hopital, $id_banque_principale, $email, $hash]);

            log_action($pdo, $id_admin_courant, "Création sous-banque : $nom");
            $success = "Sous-banque ajoutée avec succès !";
        }
    }
}

// ════════════════════════════════════════════════════════
// MODIFIER une sous-banque
// (date_creation reste inchangée)
// ════════════════════════════════════════════════════════
if (isset($_POST['modifier'])) {
    $id                   = $_POST['id_sous_banque'];
    $nom                  = trim($_POST['nom']);
    $id_hopital           = $_POST['id_hopital']           ?? '';
    $id_banque_principale = $_POST['id_banque_principale'] ?? '';
    $email                = trim($_POST['email']);
    $mot_de_passe         = trim($_POST['mot_de_passe']);

    if (empty($nom) || empty($id_hopital) || empty($id_banque_principale) || empty($email)) {
        $erreur = "Tous les champs marqués d'un * sont obligatoires.";
    }
    else {
        // Email unique (sauf pour cette sous-banque)
        $check = $pdo->prepare("SELECT COUNT(*) FROM sous_banque WHERE email = ? AND id_sous_banque != ?");
        $check->execute([$email, $id]);
        if ($check->fetchColumn() > 0) {
            $erreur = "Cet email est déjà utilisé par une autre sous-banque.";
        }
        else {
            if (!empty($mot_de_passe)) {
                $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                $pdo->prepare("
                    UPDATE sous_banque
                    SET nom = ?, id_hopital = ?, id_banque_principale = ?, email = ?, mot_de_passe = ?
                    WHERE id_sous_banque = ?
                ")->execute([$nom, $id_hopital, $id_banque_principale, $email, $hash, $id]);
            } else {
                $pdo->prepare("
                    UPDATE sous_banque
                    SET nom = ?, id_hopital = ?, id_banque_principale = ?, email = ?
                    WHERE id_sous_banque = ?
                ")->execute([$nom, $id_hopital, $id_banque_principale, $email, $id]);
            }

            log_action($pdo, $id_admin_courant, "Modification sous-banque : $nom");
            $success = "Sous-banque modifiée avec succès !";
        }
    }
}

// ════════════════════════════════════════════════════════
// SUPPRIMER une sous-banque
// (vérifie les dépendances : demandes + stock)
// ════════════════════════════════════════════════════════
if (isset($_GET['supprimer'])) {
    $id = $_GET['supprimer'];

    // Demandes liées ?
    $check_dem = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_sous_banque = ?");
    $check_dem->execute([$id]);
    $nb_dem = $check_dem->fetchColumn();

    // Stock lié ? (nouvelle table stock_sous_banque)
    $nb_stock = 0;
    try {
        $check_stk = $pdo->prepare("SELECT COUNT(*) FROM stock_sous_banque WHERE id_sous_banque = ?");
        $check_stk->execute([$id]);
        $nb_stock = $check_stk->fetchColumn();
    } catch (Exception $e) {
        // Si la table n'existe pas, ignorer
    }

    if ($nb_dem > 0) {
        $erreur = "Impossible de supprimer : cette sous-banque a $nb_dem demande(s) liée(s).";
    } elseif ($nb_stock > 0) {
        $erreur = "Impossible de supprimer : cette sous-banque a $nb_stock ligne(s) de stock enregistrée(s).";
    } else {
        // Récupérer le nom pour le log
        $stmt = $pdo->prepare("SELECT nom FROM sous_banque WHERE id_sous_banque = ?");
        $stmt->execute([$id]);
        $sb_nom = $stmt->fetchColumn();

        $pdo->prepare("DELETE FROM sous_banque WHERE id_sous_banque = ?")->execute([$id]);

        log_action($pdo, $id_admin_courant, "Suppression sous-banque : $sb_nom");
        $success = "Sous-banque supprimée avec succès !";
    }
}

// ════════════════════════════════════════════════════════
// CHARGEMENT DES DONNÉES
// ════════════════════════════════════════════════════════
$sous_banques = $pdo->query("
    SELECT sb.*,
           h.nom AS nom_hopital,
           b.nom AS nom_banque_principale
    FROM sous_banque sb
    LEFT JOIN hopital h         ON h.id_hopital = sb.id_hopital
    LEFT JOIN banque_de_sang b  ON b.id_banque  = sb.id_banque_principale
    ORDER BY h.nom, sb.nom
")->fetchAll();

$hopitaux = $pdo->query("SELECT id_hopital, nom FROM hopital ORDER BY nom")->fetchAll();
$banques  = $pdo->query("SELECT id_banque, nom FROM banque_de_sang ORDER BY nom")->fetchAll();

// ── Stats ──
$nb_total          = count($sous_banques);
$nb_hopitaux_couv  = count(array_unique(array_column($sous_banques, 'id_hopital')));
$nb_banques_reli   = count(array_unique(array_column($sous_banques, 'id_banque_principale')));
$nb_hopitaux_total = count($hopitaux);
$nb_sans_sb        = max(0, $nb_hopitaux_total - $nb_hopitaux_couv);

$page_active = 'sous_banques';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sous-banques — Admin E-Sang</title>
    <style>
        <?php echo $shared_css; ?>
        .modal-modifier { max-width: 540px; }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <!-- ══ BANDEAU ADMIN ══ -->
    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar"><?php echo $admin_initials; ?></div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bienvenue, <?php echo $admin_display; ?></div>
                <div class="top-bar-role">Espace administrateur</div>
            </div>
        </div>
    </div>

    <!-- ══ TITRE ══ -->
    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1>Sous-banques</h1>
            <p>Gérez les sous-banques rattachées aux hôpitaux et aux banques principales.</p>
        </div>
        <button class="btn-submit" onclick="toggleModal('addModal', true)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nouvelle sous-banque
        </button>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur);  ?></div><?php endif; ?>

    <!-- ══ STATISTIQUES COMPACTES (classes partagées dans _style.php) ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🏪</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total sous-banques</div>
                <div class="stat-mini-number"><?php echo $nb_total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">🏥</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Hôpitaux couverts</div>
                <div class="stat-mini-number"><?php echo $nb_hopitaux_couv; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">🏦</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Banques reliées</div>
                <div class="stat-mini-number"><?php echo $nb_banques_reli; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">⚠️</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Hôpitaux sans sous-banque</div>
                <div class="stat-mini-number <?php echo $nb_sans_sb > 0 ? 'alert' : ''; ?>"><?php echo $nb_sans_sb; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ TABLEAU ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Sous-banques enregistrées</div>
            <span class="cnt-badge"><?php echo $nb_total; ?> sous-banque(s)</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Hôpital rattaché</th>
                        <th>Banque principale</th>
                        <th>Email</th>
                        <th>Date création</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sous_banques): ?>
                        <?php foreach ($sous_banques as $sb): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($sb['nom']); ?></strong></td>
                            <td>
                                <span class="badge badge-wilaya">🏥 <?php echo htmlspecialchars($sb['nom_hopital'] ?? '—'); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($sb['nom_banque_principale'])): ?>
                                    <span class="badge" style="background:#F0FDF4;color:#166534;border:1.5px solid #BBF7D0;">
                                        🏦 <?php echo htmlspecialchars($sb['nom_banque_principale']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#9CA3AF;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($sb['email']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($sb['date_creation'])); ?></td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-edit"
                                            onclick="ouvrirModifier(
                                                <?php echo $sb['id_sous_banque']; ?>,
                                                '<?php echo addslashes($sb['nom']); ?>',
                                                <?php echo (int)$sb['id_hopital']; ?>,
                                                <?php echo (int)$sb['id_banque_principale']; ?>,
                                                '<?php echo addslashes($sb['email']); ?>'
                                            )">
                                        ✏️ Modifier
                                    </button>
                                    <a href="sous_banques.php?supprimer=<?php echo $sb['id_sous_banque']; ?>"
                                       class="btn-del"
                                       onclick="return confirm('Supprimer cette sous-banque ?')">
                                        🗑️ Supprimer
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="vide">Aucune sous-banque enregistrée pour le moment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ════════════════════════════════════════════════════════ -->
<!-- MODAL — AJOUTER une sous-banque                            -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Ajouter une sous-banque</div>
            <button class="modal-close" onclick="toggleModal('addModal', false)">×</button>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label>Nom de la sous-banque <span class="req">*</span></label>
                <input type="text" name="nom" placeholder="Ex: Sous-banque Nord" required />
            </div>
            <div class="form-group">
                <label>Hôpital rattaché <span class="req">*</span></label>
                <select name="id_hopital" required>
                    <option value="">— Choisir un hôpital —</option>
                    <?php foreach ($hopitaux as $h): ?>
                    <option value="<?php echo $h['id_hopital']; ?>"><?php echo htmlspecialchars($h['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Banque principale (parent) <span class="req">*</span></label>
                <select name="id_banque_principale" required>
                    <option value="">— Choisir une banque principale —</option>
                    <?php foreach ($banques as $b): ?>
                    <option value="<?php echo $b['id_banque']; ?>"><?php echo htmlspecialchars($b['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Email <span class="req">*</span></label>
                <input type="email" name="email" placeholder="Ex: sousbanque@hopital.mr" required />
            </div>
            <div class="form-group">
                <label>Mot de passe <span class="req">*</span></label>
                <input type="password" name="mot_de_passe" placeholder="Minimum 6 caractères" required />
            </div>
            <button type="submit" name="ajouter" class="btn-submit" style="width:100%; margin-top:8px;">
                Enregistrer la sous-banque
            </button>
        </form>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════ -->
<!-- MODAL — MODIFIER une sous-banque                           -->
<!-- ════════════════════════════════════════════════════════ -->
<div class="modal" id="editModal">
    <div class="modal-content modal-modifier">
        <div class="modal-header">
            <div class="modal-title">Modifier la sous-banque</div>
            <button class="modal-close" onclick="toggleModal('editModal', false)">×</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="id_sous_banque" id="edit_id" />

            <div class="form-group">
                <label>Nom de la sous-banque <span class="req">*</span></label>
                <input type="text" name="nom" id="edit_nom" required />
            </div>
            <div class="form-group">
                <label>Hôpital rattaché <span class="req">*</span></label>
                <select name="id_hopital" id="edit_hopital" required>
                    <option value="">— Choisir un hôpital —</option>
                    <?php foreach ($hopitaux as $h): ?>
                    <option value="<?php echo $h['id_hopital']; ?>"><?php echo htmlspecialchars($h['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Banque principale (parent) <span class="req">*</span></label>
                <select name="id_banque_principale" id="edit_banque_principale" required>
                    <option value="">— Choisir une banque principale —</option>
                    <?php foreach ($banques as $b): ?>
                    <option value="<?php echo $b['id_banque']; ?>"><?php echo htmlspecialchars($b['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
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
            <button type="submit" name="modifier" class="btn-submit" style="width:100%; margin-top:8px;">
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

function ouvrirModifier(id, nom, id_hopital, id_banque_principale, email) {
    document.getElementById('edit_id').value                    = id;
    document.getElementById('edit_nom').value                   = nom;
    document.getElementById('edit_hopital').value               = id_hopital;
    document.getElementById('edit_banque_principale').value     = id_banque_principale;
    document.getElementById('edit_email').value                 = email;
    toggleModal('editModal', true);
}

// Fermer en cliquant dehors
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});
</script>

</body>
</html>