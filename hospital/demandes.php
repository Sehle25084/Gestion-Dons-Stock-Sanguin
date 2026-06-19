<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hopital') { header('Location: ../index.php'); exit; }
if (!isset($_SESSION['id_hopital']) || !isset($_SESSION['id_responsable'])) { session_destroy(); header('Location: ../index.php'); exit; }
$id_hopital = $_SESSION['id_hopital'];
$id_banque = $_SESSION['id_banque'];
$id_responsable = $_SESSION['id_responsable'];
$page_active = 'demandes';

// --- Handle POST: envoyer une nouvelle demande ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer'])) {
    $id_groupe = intval($_POST['id_groupe']);
    $quantite = intval($_POST['quantite']);
    $note = trim($_POST['note'] ?? '');
    $urgence = isset($_POST['urgence']) ? 1 : 0;
    $date_souhaitee = $_POST['date_souhaitee'] ?? null;

    if ($id_groupe > 0 && $quantite > 0) {
        // Trouver si cet hôpital est rattaché à une sous-banque
        $stmtSB = $pdo->prepare("SELECT id_sous_banque FROM sous_banque WHERE id_hopital = ? LIMIT 1");
        $stmtSB->execute([$id_hopital]);
        $id_sous_banque = $stmtSB->fetchColumn() ?: null;

        if ($id_sous_banque) {
            // Flux normal : envoyer à la sous-banque (id_banque = NULL)
            $stmt = $pdo->prepare("INSERT INTO demande (id_hopital, id_sous_banque, id_banque, id_groupe, quantite_demandee, date_demande, statut, type_demande, note, urgence, date_souhaitee) VALUES (?, ?, NULL, ?, ?, CURDATE(), 'en_attente', 'interne', ?, ?, ?)");
            $stmt->execute([$id_hopital, $id_sous_banque, $id_groupe, $quantite, $note, $urgence, $date_souhaitee ?: null]);
        } else {
            // Cas de secours : si aucun dépôt n'est rattaché, envoyer directement à la banque principale
            $stmt = $pdo->prepare("INSERT INTO demande (id_hopital, id_sous_banque, id_banque, id_groupe, quantite_demandee, date_demande, statut, type_demande, note, urgence, date_souhaitee) VALUES (?, NULL, ?, ?, ?, CURDATE(), 'en_attente', 'interne', ?, ?, ?)");
            $stmt->execute([$id_hopital, $id_banque, $id_groupe, $quantite, $note, $urgence, $date_souhaitee ?: null]);
        }

        // Get groupe label for journal
        $stmtG = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
        $stmtG->execute([$id_groupe]);
        $groupe_label = $stmtG->fetchColumn();

        $stmtJ = $pdo->prepare("INSERT INTO journal_hopital (id_hopital, id_responsable, action, details, date_action) VALUES (?, ?, 'demande_envoyee', ?, NOW())");
        $stmtJ->execute([$id_hopital, $id_responsable, "Demande de $quantite pochettes de $groupe_label"]);

        header('Location: demandes.php?success=1');
        exit;
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
$sql = "SELECT d.*, g.libelle AS groupe FROM demande d JOIN groupe_sanguin g ON g.id_groupe = d.id_groupe WHERE d.id_hopital = ?";
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

// --- Modal open? ---
$show_modal = isset($_GET['action']) && $_GET['action'] === 'nouvelle';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demandes de sang — E-Sang</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #FFFFFF; color: #111111; font-size: 15px; display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 40px 48px; margin-left: 270px; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .page-header h1 { font-size: 28px; font-weight: 800; color: #111; }
        .page-header p { font-size: 15px; color: #6B7280; margin-top: 4px; }

        .btn-primary { background: #8B0000; color: #fff; border: none; padding: 12px 28px; border-radius: 12px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; transition: background 0.2s; }
        .btn-primary:hover { background: #6B0000; }

        .alert { padding: 14px 20px; border-radius: 12px; font-size: 14px; font-weight: 500; margin-bottom: 24px; }
        .alert-success { background: #F0FDF4; color: #166534; border: 1px solid #BBF7D0; }
        .alert-warning { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }

        .filters { display: flex; gap: 10px; margin-bottom: 28px; flex-wrap: wrap; }
        .filter-btn { padding: 9px 20px; border-radius: 10px; border: 2px solid #E5E7EB; background: #fff; color: #374151; font-size: 14px; font-weight: 500; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; }
        .filter-btn:hover { border-color: #8B0000; color: #8B0000; }
        .filter-btn.active { background: #8B0000; color: #fff; border-color: #8B0000; }

        .table-wrapper { border: 2px solid #E5E7EB; border-radius: 14px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #F9FAFB; }
        th { padding: 14px 18px; text-align: left; font-size: 13px; font-weight: 600; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #E5E7EB; }
        td { padding: 14px 18px; font-size: 14px; color: #111; border-bottom: 1px solid #F3F4F6; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #FAFAFA; }

        .badge { display: inline-block; padding: 5px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .badge-en_attente { background: #FEF3C7; color: #92400E; }
        .badge-acceptée, .badge-acceptee { background: #D1FAE5; color: #065F46; }
        .badge-refusée, .badge-refusee { background: #FEE2E2; color: #991B1B; }
        .badge-annulée, .badge-annulee { background: #F3F4F6; color: #6B7280; }
        .badge-urgence { background: #FEE2E2; color: #991B1B; }

        .btn-cancel { background: #fff; color: #DC2626; border: 2px solid #FCA5A5; padding: 7px 18px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; text-decoration: none; display: inline-block; }
        .btn-cancel:hover { background: #FEE2E2; border-color: #DC2626; }

        .empty-state { text-align: center; padding: 60px 20px; color: #9CA3AF; }
        .empty-state .icon { font-size: 48px; margin-bottom: 16px; }
        .empty-state h3 { font-size: 18px; color: #6B7280; margin-bottom: 8px; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; }
        .modal-overlay.hidden { display: none; }
        .modal { background: #fff; border-radius: 20px; padding: 36px; width: 520px; max-width: 95vw; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
        .modal h2 { font-size: 22px; font-weight: 700; color: #111; margin-bottom: 6px; }
        .modal p.subtitle { font-size: 14px; color: #6B7280; margin-bottom: 28px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 11px 14px; border: 2px solid #E5E7EB; border-radius: 10px; font-size: 15px; font-family: 'Inter', sans-serif; color: #111; transition: border-color 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #8B0000; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input[type="checkbox"] { width: 18px; height: 18px; accent-color: #8B0000; }
        .checkbox-group label { margin-bottom: 0; }
        .modal-actions { display: flex; gap: 12px; margin-top: 28px; }
        .btn-secondary { background: #fff; color: #374151; border: 2px solid #E5E7EB; padding: 12px 24px; border-radius: 12px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; }
        .btn-secondary:hover { background: #F9FAFB; }
        .modal-actions .btn-primary { flex: 1; }
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1>📋 Demandes de sang</h1>
            <p>Gérez vos demandes de poches de sang auprès de votre sous-banque</p>
        </div>
        <button class="btn-primary" onclick="openModal()">+ Nouvelle demande de sang</button>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">✅ Votre demande de sang a été envoyée avec succès.</div>
    <?php endif; ?>
    <?php if (isset($_GET['annule'])): ?>
        <div class="alert alert-warning">⚠️ La demande a été annulée avec succès.</div>
    <?php endif; ?>

    <div class="filters">
        <a href="demandes.php?filtre=tous" class="filter-btn <?= $filtre === 'tous' ? 'active' : '' ?>">Toutes</a>
        <a href="demandes.php?filtre=en_attente" class="filter-btn <?= $filtre === 'en_attente' ? 'active' : '' ?>">En attente</a>
        <a href="demandes.php?filtre=acceptée" class="filter-btn <?= $filtre === 'acceptée' ? 'active' : '' ?>">Acceptées</a>
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
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Groupe sanguin</th>
                        <th>Quantité</th>
                        <th>Date de demande</th>
                        <th>Date souhaitée</th>
                        <th>Date de réponse</th>
                        <th>Urgence</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($demandes as $d): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($d['groupe']) ?></strong></td>
                            <td><?= intval($d['quantite_demandee']) ?> pochettes</td>
                            <td><?= date('d/m/Y', strtotime($d['date_demande'])) ?></td>
                            <td><?= !empty($d['date_souhaitee']) ? date('d/m/Y', strtotime($d['date_souhaitee'])) : '—' ?></td>
                            <td><?= $d['date_reponse'] ? date('d/m/Y', strtotime($d['date_reponse'])) : '—' ?></td>
                            <td>
                                <?php if (!empty($d['urgence'])): ?>
                                    <span class="badge badge-urgence">🚨 Urgent</span>
                                <?php else: ?>
                                    <span style="color:#9CA3AF;">Normal</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $statut_class = 'badge-' . str_replace(['é','è'],['e','e'], $d['statut']);
                                    $statut_labels = [
                                        'en_attente' => 'En attente',
                                        'acceptée' => 'Acceptée',
                                        'refusée' => 'Refusée',
                                        'annulée' => 'Annulée'
                                    ];
                                    $label = $statut_labels[$d['statut']] ?? $d['statut'];
                                ?>
                                <span class="badge badge-<?= htmlspecialchars($d['statut']) ?>"><?= htmlspecialchars($label) ?></span>
                            </td>
                            <td>
                                <?php if ($d['statut'] === 'en_attente'): ?>
                                    <a href="demandes.php?annuler=<?= $d['id_demande'] ?>" class="btn-cancel" onclick="return confirm('Êtes-vous sûr de vouloir annuler cette demande ?')">Annuler cette demande</a>
                                <?php else: ?>
                                    <span style="color:#D1D5DB;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal nouvelle demande -->
<div class="modal-overlay <?= $show_modal ? '' : 'hidden' ?>" id="modalOverlay" onclick="closeModalOutside(event)">
    <div class="modal">
        <h2>🩸 Nouvelle demande de sang</h2>
        <p class="subtitle">Remplissez les informations pour envoyer une demande à votre sous-banque</p>
        <form method="POST" action="demandes.php">
            <input type="hidden" name="envoyer" value="1">

            <div class="form-group">
                <label for="id_groupe">Groupe sanguin souhaité</label>
                <select name="id_groupe" id="id_groupe" required>
                    <option value="">— Sélectionner un groupe —</option>
                    <?php foreach ($groupes as $g): ?>
                        <option value="<?= $g['id_groupe'] ?>"><?= htmlspecialchars($g['libelle']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="quantite">Quantité de pochettes</label>
                <input type="number" name="quantite" id="quantite" min="1" max="500" required placeholder="Ex: 10">
            </div>

            <div class="form-group">
                <label for="date_souhaitee">Date souhaitée de réception</label>
                <input type="date" name="date_souhaitee" id="date_souhaitee" min="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="urgence" id="urgence" value="1">
                    <label for="urgence">🚨 Demande urgente</label>
                </div>
            </div>

            <div class="form-group">
                <label for="note">Note complémentaire</label>
                <textarea name="note" id="note" placeholder="Informations supplémentaires (optionnel)..."></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal()">Fermer le formulaire</button>
                <button type="submit" class="btn-primary">Envoyer la demande de sang</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('modalOverlay').classList.remove('hidden');
    }
    function closeModal() {
        document.getElementById('modalOverlay').classList.add('hidden');
    }
    function closeModalOutside(e) {
        if (e.target === document.getElementById('modalOverlay')) closeModal();
    }
</script>
</body>
</html>
