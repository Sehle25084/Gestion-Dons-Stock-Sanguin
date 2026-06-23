<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hopital') {
    header('Location: ../index.php');
    exit;
}
if (!isset($_SESSION['id_hopital']) || !isset($_SESSION['id_responsable'])) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$id_hopital = $_SESSION['id_hopital'];
$id_banque = $_SESSION['id_banque'];
$id_responsable = $_SESSION['id_responsable'];
$page_active = 'demandes';

$erreur_demande = "";

// --- Handle POST: envoyer une nouvelle demande ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer'])) {
    $id_groupe = intval($_POST['id_groupe']);
    $quantite = intval($_POST['quantite']);
    $note = trim($_POST['note'] ?? '');
    $urgence = isset($_POST['urgence']) ? 1 : 0;
    $date_souhaitee = $_POST['date_souhaitee'] ?? null;

    // ✔ CORRECTION : validations explicites avec messages d'erreur (au lieu du silence)
    if ($id_groupe <= 0) {
        $erreur_demande = "Veuillez sélectionner un groupe sanguin valide.";
    }
    elseif ($quantite <= 0) {
        $erreur_demande = "La quantité doit être supérieure à zéro.";
    }
    elseif ($quantite > 500) {
        $erreur_demande = "La quantité maximale autorisée par demande est de 500 poches.";
    }
    else {
        // ✔ CORRECTION : vérifier que le groupe sanguin existe en DB
        $stmtChk = $pdo->prepare("SELECT id_groupe FROM groupe_sanguin WHERE id_groupe = ?");
        $stmtChk->execute([$id_groupe]);
        if (!$stmtChk->fetch()) {
            $erreur_demande = "Groupe sanguin invalide.";
        }
        // ✔ CORRECTION : vérifier que la date souhaitée n'est pas dans le passé
        elseif (!empty($date_souhaitee) && strtotime($date_souhaitee) < strtotime(date('Y-m-d'))) {
            $erreur_demande = "La date souhaitée ne peut pas être dans le passé.";
        }
        else {
            // Trouver si cet hôpital est rattaché à une sous-banque
            $stmtSB = $pdo->prepare("SELECT id_sous_banque FROM sous_banque WHERE id_hopital = ? LIMIT 1");
            $stmtSB->execute([$id_hopital]);
            $id_sous_banque = $stmtSB->fetchColumn() ?: null;

            if ($id_sous_banque) {
                // Flux normal : envoyer à la sous-banque (id_banque = NULL)
                $stmt = $pdo->prepare("INSERT INTO demande (id_hopital, id_sous_banque, id_banque, id_groupe, quantite_demandee, date_demande, statut, type_demande, note, urgence, date_souhaitee) VALUES (?, ?, NULL, ?, ?, CURDATE(), 'en_attente', 'interne', ?, ?, ?)");
                $stmt->execute([$id_hopital, $id_sous_banque, $id_groupe, $quantite, $note, $urgence, $date_souhaitee ?: null]);

                // Get groupe label for journal
                $stmtG = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
                $stmtG->execute([$id_groupe]);
                $groupe_label = $stmtG->fetchColumn();

                $stmtJ = $pdo->prepare("INSERT INTO journal_hopital (id_hopital, id_responsable, action, details, date_action) VALUES (?, ?, 'demande_envoyee', ?, NOW())");
                $stmtJ->execute([$id_hopital, $id_responsable, "Demande de $quantite poches de $groupe_label"]);

                header('Location: demandes.php?success=1');
                exit;
            } else {
                // ERREUR MÉTIER : L'hôpital ne peut pas demander s'il n'a pas de sous-banque.
                $erreur_demande = "Action impossible : Votre hôpital n'est relié à aucune sous-banque. Les demandes directes à la banque principale sont interdites.";
            }
        }
    }
}

// --- Handle GET: annuler une demande ---
if (isset($_GET['annuler'])) {
    $id_demande = intval($_GET['annuler']);
    $stmt = $pdo->prepare("UPDATE demande SET statut='annulée', date_reponse=CURDATE() WHERE id_demande=? AND id_hopital=? AND statut='en_attente'");
    $stmt->execute([$id_demande, $id_hopital]);

    if ($stmt->rowCount() > 0) {
        $stmtJ = $pdo->prepare("INSERT INTO journal_hopital (id_hopital, id_responsable, action, details, date_action) VALUES (?, ?, 'demande_annulee', ?, NOW())");
        $stmtJ->execute([$id_hopital, $id_responsable, "Demande #$id_demande annulée"]);
    }

    header('Location: demandes.php?annule=1');
    exit;
}

// --- Filtre ---
$filtre = $_GET['filtre'] ?? 'tous';
$filtres_valides = ['tous', 'en_attente', 'acceptée', 'refusée', 'annulée'];
if (!in_array($filtre, $filtres_valides)) $filtre = 'tous';

// --- Fetch demandes ---
// ✔ CORRECTION : alias renommé en `nom_sous_banque` (avant : alias trompeur `nom_banque`)
$sql = "SELECT d.*, g.libelle AS groupe, s.nom AS nom_sous_banque 
        FROM demande d 
        JOIN groupe_sanguin g ON g.id_groupe = d.id_groupe 
        LEFT JOIN sous_banque s ON s.id_sous_banque = d.id_sous_banque 
        WHERE d.id_hopital = ?";
$params = [$id_hopital];
if ($filtre !== 'tous') {
    $sql .= " AND d.statut = ?";
    $params[] = $filtre;
}
$sql .= " ORDER BY d.date_demande DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch groupes for the form ---
$groupes = $pdo->query("SELECT * FROM groupe_sanguin ORDER BY libelle")->fetchAll(PDO::FETCH_ASSOC);

$show_modal = isset($_GET['action']) && $_GET['action'] === 'nouvelle';

require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Demandes — E-Sang</title>
    <style>
        <?php echo $shared_css; ?>
        
        .filters { display: flex; gap: 10px; margin-bottom: 28px; flex-wrap: wrap; }
        .filter-btn { padding: 9px 20px; border-radius: 10px; border: 2px solid #E5E7EB; background: #fff; color: #374151; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .filter-btn:hover { border-color: #8B0000; color: #8B0000; }
        .filter-btn.active { background: #8B0000; color: #fff; border-color: #8B0000; }

        .btn-cancel { background: #fff; color: #DC2626; border: 2px solid #FCA5A5; padding: 7px 18px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; transition: all 0.2s; }
        .btn-cancel:hover { background: #FEE2E2; border-color: #DC2626; }

        .empty-state { text-align: center; padding: 60px 20px; color: #9CA3AF; }
        .empty-state .icon { font-size: 48px; margin-bottom: 16px; }
        .empty-state h3 { font-size: 18px; color: #6B7280; margin-bottom: 8px; }

        .btn-secondary { background: #fff; color: #374151; border: 2px solid #E5E7EB; padding: 12px 24px; border-radius: 12px; font-size: 15px; font-weight: 600; cursor: pointer; }
        .btn-secondary:hover { background: #F9FAFB; }
        .modal-actions { display: flex; gap: 12px; margin-top: 28px; }
        .modal-actions .btn-submit { flex: 1; margin-top: 0; }
        
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input[type="checkbox"] { width: 18px; height: 18px; accent-color: #8B0000; }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<main class="main-content">
    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1>Mes demandes</h1>
            <p>Gérez vos demandes de poches de sang auprès de votre sous-banque</p>
        </div>
        <button class="btn-submit" onclick="openModal()" style="width:auto; margin-top:0;">+ Nouvelle demande</button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alerte-success">✅ Votre demande de sang a été envoyée avec succès à la sous-banque.</div>
    <?php endif; ?>
    <?php if (isset($_GET['annule'])): ?>
        <div class="alerte-info">⚠️ La demande a été annulée avec succès.</div>
    <?php endif; ?>
    <?php if ($erreur_demande): ?>
        <div class="alerte-erreur">❌ <?php echo $erreur_demande; ?></div>
    <?php endif; ?>

    <div class="filters">
        <a href="demandes.php?filtre=tous" class="filter-btn <?= $filtre === 'tous' ? 'active' : '' ?>">Toutes</a>
        <a href="demandes.php?filtre=en_attente" class="filter-btn <?= $filtre === 'en_attente' ? 'active' : '' ?>">En attente</a>
        <a href="demandes.php?filtre=acceptée" class="filter-btn <?= $filtre === 'acceptée' ? 'active' : '' ?>">Approuvées</a>
        <a href="demandes.php?filtre=refusée" class="filter-btn <?= $filtre === 'refusée' ? 'active' : '' ?>">Refusées</a>
        <a href="demandes.php?filtre=annulée" class="filter-btn <?= $filtre === 'annulée' ? 'active' : '' ?>">Annulées</a>
    </div>

    <?php if (empty($demandes)): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <h3>Aucune demande trouvée</h3>
            <p>Il n'y a aucune demande correspondant à ce filtre.</p>
        </div>
    <?php else: ?>
        <div class="section">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Groupe</th>
                            <th>Quantité</th>
                            <th>Sous-banque</th>
                            <th>Date Demande</th>
                            <th>Date Souhaitée</th>
                            <th>Urgence</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($demandes as $d): ?>
                            <?php
                            $statut = $d['statut'];
                            switch ($statut) {
                                case 'en_attente': $badge_s = 'badge-attente'; $label_s = 'En attente'; break;
                                case 'acceptée':   $badge_s = 'badge-acceptee'; $label_s = 'Approuvée'; break;
                                case 'refusée':    $badge_s = 'badge-refusee'; $label_s = 'Refusée'; break;
                                case 'annulée':    $badge_s = 'badge-annulee'; $label_s = 'Annulée'; break;
                                default:           $badge_s = 'badge-attente'; $label_s = $statut; break;
                            }
                            ?>
                            <tr>
                                <td><span class="badge badge-groupe"><?= htmlspecialchars($d['groupe']) ?></span></td>
                                <td style="font-weight: 800;"><?= intval($d['quantite_demandee']) ?> poches</td>
                                <td><?= htmlspecialchars($d['nom_sous_banque'] ?? 'Non assignée') ?></td>
                                <td><?= date('d/m/Y', strtotime($d['date_demande'])) ?></td>
                                <td><?= !empty($d['date_souhaitee']) ? date('d/m/Y', strtotime($d['date_souhaitee'])) : '—' ?></td>
                                <td>
                                    <?php if (!empty($d['urgence'])): ?>
                                        <span class="badge" style="background:#FEF2F2; color:#991B1B;">🚨 Urgent</span>
                                    <?php else: ?>
                                        <span style="color:#9CA3AF; font-size:12px;">Normal</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $badge_s ?>"><?= $label_s ?></span></td>
                                <td>
                                    <div class="actions-cell">
                                        <?php if ($statut === 'en_attente'): ?>
                                            <a href="demandes.php?annuler=<?= $d['id_demande'] ?>" class="btn-cancel" onclick="return confirm('Êtes-vous sûr de vouloir annuler cette demande ?')">Annuler</a>
                                        <?php else: ?>
                                            <span style="color:#D1D5DB; font-size:12px;">—</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</main>

<!-- Modal nouvelle demande -->
<div class="modal <?= $show_modal ? 'active' : '' ?>" id="modalOverlay">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Nouvelle demande</div>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <p style="color:#6B7280; font-size:14px; margin-bottom:24px;">Remplissez les informations pour envoyer une demande à votre sous-banque affiliée.</p>
        
        <form method="POST" action="demandes.php">
            <input type="hidden" name="envoyer" value="1">

            <div class="form-row">
                <div class="form-group">
                    <label for="id_groupe">Groupe sanguin <span class="req">*</span></label>
                    <select name="id_groupe" id="id_groupe" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($groupes as $g): ?>
                            <option value="<?= $g['id_groupe'] ?>"><?= htmlspecialchars($g['libelle']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="quantite">Quantité (poches) <span class="req">*</span></label>
                    <input type="number" name="quantite" id="quantite" min="1" max="500" required placeholder="Ex: 10">
                </div>
            </div>

            <div class="form-group">
                <label for="date_souhaitee">Date souhaitée de réception</label>
                <input type="date" name="date_souhaitee" id="date_souhaitee" min="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group" style="margin-top:8px; margin-bottom:18px;">
                <div class="checkbox-group">
                    <input type="checkbox" name="urgence" id="urgence" value="1">
                    <label for="urgence" style="font-size:14px; font-weight:700; color:#8B0000; margin:0;">🚨 Marquer cette demande comme urgente</label>
                </div>
            </div>

            <div class="form-group">
                <label for="note">Note complémentaire</label>
                <textarea name="note" id="note" placeholder="Informations supplémentaires (optionnel)..."></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal()">Annuler</button>
                <button type="submit" class="btn-submit">Envoyer la demande</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('modalOverlay').classList.add('active');
    }
    function closeModal() {
        document.getElementById('modalOverlay').classList.remove('active');
    }
    // Fermer en cliquant à l'extérieur
    document.getElementById('modalOverlay').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
</script>
</body>
</html>