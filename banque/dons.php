<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'banque') {
    header("Location: ../index.php");
    exit;
}

// Compatibilité ancienne/nouvelle architecture
$id_banque = $_SESSION['id_banque'] ?? $_SESSION['id'];
$page_active = 'dons';
$success = $erreur = "";

// ════════════════════════════════════════════════════════
// AJOUTER un don manuel (via le NNI du donneur)
// ════════════════════════════════════════════════════════
if (isset($_POST['ajouter_don'])) {
    $nni = trim($_POST['nni']);
    $quantite = (int)$_POST['quantite'];

    // ✔ Validations
    if (!preg_match('/^\d{10}$/', $nni)) {
        $erreur = "Le NNI doit contenir exactement 10 chiffres.";
    } elseif ($quantite <= 0) {
        $erreur = "La quantité doit être supérieure à zéro.";
    } elseif ($quantite > 10) {
        $erreur = "La quantité maximale par don est de 10 pochettes.";
    } else {
        $stmt = $pdo->prepare("SELECT id_donneur, id_groupe, groupe_confirme FROM donneur WHERE NNI = ?");
        $stmt->execute([$nni]);
        $donneur = $stmt->fetch();

        if (!$donneur) {
            $erreur = "NNI introuvable. Le donneur doit être inscrit avant de pouvoir donner.";
        } elseif (empty($donneur['id_groupe']) || (int)$donneur['groupe_confirme'] !== 1) {
            $erreur = "Le groupe sanguin du donneur n'est pas encore confirmé. Veuillez d'abord le confirmer dans la page Donneurs.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO don (id_donneur, id_banque, date_don, id_groupe, quantite, statut)
                VALUES (?, ?, CURDATE(), ?, ?, 'en_attente')
            ");
            $stmt->execute([$donneur['id_donneur'], $id_banque, $donneur['id_groupe'], $quantite]);

            $id_don_ajoute = $pdo->lastInsertId();

            enregistrerActivite($pdo, 'banque', $id_banque,
                'Ajout du don #' . $id_don_ajoute . ' (' . $quantite . ' pochette(s))');

            $success = "Don enregistré avec succès en attente d'acceptation.";
        }
    }
}

// ════════════════════════════════════════════════════════
// ACCEPTER un don (ajout au stock + création des pochettes)
// ════════════════════════════════════════════════════════
if (isset($_GET['accepter'])) {
    $id_don = (int)$_GET['accepter'];

    $stmt = $pdo->prepare("SELECT * FROM don WHERE id_don = ? AND id_banque = ?");
    $stmt->execute([$id_don, $id_banque]);
    $don = $stmt->fetch();

    if ($don) {
        if ($don['statut'] !== 'en_attente') {
            $erreur = "Ce don a déjà été traité.";
        } else {
            // Marquer le don accepté
            $pdo->prepare("UPDATE don SET statut = 'accepté' WHERE id_don = ? AND id_banque = ?")
                ->execute([$id_don, $id_banque]);

            // Mettre à jour le stock
            $stmt = $pdo->prepare("SELECT * FROM stock WHERE id_banque = ? AND id_groupe = ?");
            $stmt->execute([$id_banque, $don['id_groupe']]);
            $stock = $stmt->fetch();

            if ($stock) {
                $pdo->prepare("
                    UPDATE stock
                    SET quantite_disponible = quantite_disponible + ?,
                        date_mise_a_jour = CURDATE()
                    WHERE id_stock = ?
                ")->execute([$don['quantite'], $stock['id_stock']]);
            } else {
                $pdo->prepare("
                    INSERT INTO stock (id_banque, id_groupe, quantite_disponible, date_mise_a_jour, date_expiration)
                    VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 42 DAY))
                ")->execute([$id_banque, $don['id_groupe'], $don['quantite']]);
            }

            // Créer les pochettes individuelles (42 jours d'expiration)
            $date_collecte   = $don['date_don'];
            $date_expiration = date('Y-m-d', strtotime($date_collecte . ' +42 days'));

            $stmtPochette = $pdo->prepare("
                INSERT INTO pochette
                (id_don, id_groupe, id_banque, date_collecte, date_expiration, statut, code_pochette)
                VALUES (?, ?, ?, ?, ?, 'disponible', ?)
            ");

            for ($i = 1; $i <= (int)$don['quantite']; $i++) {
                $code_pochette = 'POCH-' . $id_don . '-' . $i . '-' . time();
                $stmtPochette->execute([
                    $id_don, $don['id_groupe'], $id_banque,
                    $date_collecte, $date_expiration, $code_pochette
                ]);
            }

            enregistrerActivite($pdo, 'banque', $id_banque,
                'Acceptation du don #' . $id_don . ' (' . $don['quantite'] . ' pochette(s) créée(s))');

            $success = "Don accepté — " . (int)$don['quantite'] . " pochette(s) ajoutée(s) au stock !";
        }
    }
}

// ════════════════════════════════════════════════════════
// REFUSER un don
// ════════════════════════════════════════════════════════
if (isset($_GET['refuser'])) {
    $id_don = (int)$_GET['refuser'];

    $stmt = $pdo->prepare("SELECT statut FROM don WHERE id_don = ? AND id_banque = ?");
    $stmt->execute([$id_don, $id_banque]);
    $don = $stmt->fetch();

    if ($don && $don['statut'] === 'en_attente') {
        $pdo->prepare("UPDATE don SET statut = 'refusé' WHERE id_don = ? AND id_banque = ?")
            ->execute([$id_don, $id_banque]);

        enregistrerActivite($pdo, 'banque', $id_banque,
            'Refus du don #' . $id_don);

        $success = "Don refusé.";
    } else {
        $erreur = "Ce don a déjà été traité.";
    }
}

// ════════════════════════════════════════════════════════
// FILTRE par statut
// ════════════════════════════════════════════════════════
$filtre_statut = $_GET['statut'] ?? 'tous';
$where_filtre = "";
$params = [$id_banque];

if (in_array($filtre_statut, ['en_attente', 'accepté', 'refusé'])) {
    $where_filtre = " AND don.statut = ?";
    $params[] = $filtre_statut;
}

// ════════════════════════════════════════════════════════
// CHARGEMENT des dons
// ════════════════════════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT don.*, g.libelle AS groupe, d.NNI
    FROM don
    JOIN groupe_sanguin g ON g.id_groupe = don.id_groupe
    LEFT JOIN donneur d ON d.id_donneur = don.id_donneur
    WHERE don.id_banque = ?
      $where_filtre
    ORDER BY don.date_don DESC
");
$stmt->execute($params);
$dons = $stmt->fetchAll();

// Récupérer les noms des donneurs en UNE seule requête (pas de N+1)
$citoyens_par_nni = [];
$nnis = array_filter(array_unique(array_column($dons, 'NNI')));
if (!empty($nnis)) {
    $placeholders = implode(',', array_fill(0, count($nnis), '?'));
    $stmtCit = $pdo_registre->prepare("SELECT NNI, nom, prenom FROM citoyen WHERE NNI IN ($placeholders)");
    $stmtCit->execute(array_values($nnis));
    while ($row = $stmtCit->fetch()) {
        $citoyens_par_nni[$row['NNI']] = $row;
    }
}

// ════════════════════════════════════════════════════════
// STATISTIQUES
// ════════════════════════════════════════════════════════
$nb_total = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_banque = ?");
$nb_total->execute([$id_banque]);
$nb_total = (int)$nb_total->fetchColumn();

$nb_attente = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_banque = ? AND statut = 'en_attente'");
$nb_attente->execute([$id_banque]);
$nb_attente = (int)$nb_attente->fetchColumn();

$nb_acceptes = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_banque = ? AND statut = 'accepté'");
$nb_acceptes->execute([$id_banque]);
$nb_acceptes = (int)$nb_acceptes->fetchColumn();

$nb_refuses = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_banque = ? AND statut = 'refusé'");
$nb_refuses->execute([$id_banque]);
$nb_refuses = (int)$nb_refuses->fetchColumn();

// Quantité totale acceptée (pochettes vraiment collectées)
$qte_totale = $pdo->prepare("SELECT COALESCE(SUM(quantite), 0) FROM don WHERE id_banque = ? AND statut = 'accepté'");
$qte_totale->execute([$id_banque]);
$qte_totale = (int)$qte_totale->fetchColumn();

require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dons — <?php echo htmlspecialchars($_SESSION['nom_banque'] ?? 'Banque'); ?> | E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* ── Filtres par statut ── */
        .filtres-statut {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .filtre-btn {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            color: #6B7280;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .filtre-btn:hover { background: #FEF2F2; color: #8B0000; border-color: #8B0000; }
        .filtre-btn.active { background: #8B0000; color: #FFFFFF; border-color: #8B0000; }
        .filtre-count {
            background: rgba(255,255,255,0.25);
            padding: 1px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
        }
        .filtre-btn:not(.active) .filtre-count { background: #F3F4F6; color: #6B7280; }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <!-- ══ TOP-BAR AGENT ══ -->
    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar"><?php echo $agent_initials; ?></div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bonjour, <?php echo $agent_display; ?></div>
                <div class="top-bar-role">Agent — <?php echo htmlspecialchars($_SESSION['nom_banque'] ?? 'Banque de sang'); ?></div>
            </div>
        </div>
    </div>

    <!-- ══ TITRE + BOUTON AJOUTER ══ -->
    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1>Historique des dons</h1>
            <p>Suivez et validez les dons de sang collectés par votre banque.</p>
        </div>
        <button class="btn-submit" onclick="ouvrirModalDon()" style="width:auto; margin-top:0; padding:11px 22px;">
            + Enregistrer un don
        </button>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur);  ?></div><?php endif; ?>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">💉</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total dons</div>
                <div class="stat-mini-number"><?php echo $nb_total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">⏳</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">En attente</div>
                <div class="stat-mini-number <?php echo $nb_attente > 0 ? 'alert' : ''; ?>"><?php echo $nb_attente; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">✅</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Acceptés</div>
                <div class="stat-mini-number"><?php echo $nb_acceptes; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">🩸</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Pochettes collectées</div>
                <div class="stat-mini-number"><?php echo $qte_totale; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ TABLEAU DES DONS ══ -->
    <div class="section">

        <div class="section-header">
            <div class="section-title">Dons enregistrés</div>
            <span class="cnt-badge"><?php echo count($dons); ?> don(s) affiché(s)</span>
        </div>

        <!-- Filtres -->
        <div class="filtres-statut">
            <a href="dons.php" class="filtre-btn <?php echo ($filtre_statut === 'tous') ? 'active' : ''; ?>">
                📋 Tous <span class="filtre-count"><?php echo $nb_total; ?></span>
            </a>
            <a href="dons.php?statut=en_attente" class="filtre-btn <?php echo ($filtre_statut === 'en_attente') ? 'active' : ''; ?>">
                ⏳ En attente <span class="filtre-count"><?php echo $nb_attente; ?></span>
            </a>
            <a href="dons.php?statut=accepté" class="filtre-btn <?php echo ($filtre_statut === 'accepté') ? 'active' : ''; ?>">
                ✅ Acceptés <span class="filtre-count"><?php echo $nb_acceptes; ?></span>
            </a>
            <a href="dons.php?statut=refusé" class="filtre-btn <?php echo ($filtre_statut === 'refusé') ? 'active' : ''; ?>">
                ❌ Refusés <span class="filtre-count"><?php echo $nb_refuses; ?></span>
            </a>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Donneur</th>
                        <th>Groupe</th>
                        <th>Quantité</th>
                        <th>Date du Don</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dons): ?>
                        <?php foreach ($dons as $d): ?>
                        <?php $citoyen = !empty($d['NNI']) ? ($citoyens_par_nni[$d['NNI']] ?? null) : null; ?>
                        <tr>
                            <td>
                                <strong>Donneur #<?php echo $d['id_donneur']; ?></strong>
                                <?php if ($citoyen): ?>
                                <br><small style="color:#666;font-weight:500;">
                                    <?php echo htmlspecialchars($citoyen['prenom'] . ' ' . $citoyen['nom']); ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                            <td><strong><?php echo (int)$d['quantite']; ?></strong> pochette(s)</td>
                            <td><?php echo date('d/m/Y', strtotime($d['date_don'])); ?></td>
                            <td>
                                <?php if ($d['statut'] === 'en_attente'): ?>
                                    <span class="badge badge-attente">⏳ En attente</span>
                                <?php elseif ($d['statut'] === 'accepté'): ?>
                                    <span class="badge badge-accepte">✅ Accepté</span>
                                <?php else: ?>
                                    <span class="badge badge-refuse">❌ Refusé</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <?php if ($d['statut'] === 'en_attente'): ?>
                                        <a href="dons.php?accepter=<?php echo $d['id_don']; ?>"
                                           class="btn-accepter"
                                           onclick="return confirm('Accepter ce don ? Les pochettes seront créées et ajoutées au stock.')">
                                            ✓ Accepter
                                        </a>
                                        <a href="dons.php?refuser=<?php echo $d['id_don']; ?>"
                                           class="btn-refuser"
                                           onclick="return confirm('Refuser ce don ?')">
                                            ✕ Refuser
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#9CA3AF; font-size:12px;">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="vide">
                            <?php if ($filtre_statut !== 'tous'): ?>
                                Aucun don avec ce statut.
                            <?php else: ?>
                                Aucun don enregistré pour le moment.
                            <?php endif; ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ══ MODAL : Enregistrer un don ══ -->
<div class="modal" id="modalDon">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Enregistrer un don manuel</div>
            <button class="modal-close" onclick="fermerModalDon()">×</button>
        </div>
        <p style="color:#6B7280; font-size:13px; margin-bottom:20px;">
            Saisissez le NNI du donneur présent à votre banque. Le groupe sanguin sera récupéré automatiquement (le donneur doit avoir son groupe confirmé).
        </p>

        <form method="POST" action="dons.php">
            <div class="form-group">
                <label for="nni">NNI du donneur <span class="req">*</span></label>
                <input type="text" name="nni" id="nni" required
                       maxlength="10" pattern="\d{10}" inputmode="numeric"
                       placeholder="10 chiffres" title="Le NNI doit contenir exactement 10 chiffres">
            </div>

            <div class="form-group">
                <label for="quantite">Quantité (pochettes) <span class="req">*</span></label>
                <input type="number" name="quantite" id="quantite" min="1" max="10" required
                       placeholder="Ex : 1 pochette">
                <small style="color:#9CA3AF; font-size:12px;">Maximum 10 pochettes par don</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="fermerModalDon()">Annuler</button>
                <button type="submit" name="ajouter_don" class="btn-submit">Enregistrer le don</button>
            </div>
        </form>
    </div>
</div>

<script>
function ouvrirModalDon() {
    document.getElementById('modalDon').classList.add('active');
}
function fermerModalDon() {
    document.getElementById('modalDon').classList.remove('active');
}
document.getElementById('modalDon').addEventListener('click', function(e) {
    if (e.target === this) fermerModalDon();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') fermerModalDon();
});
</script>

</body>
</html>