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
$page_active = 'demandes';
$success = $erreur = "";

// ════════════════════════════════════════════════════════
// ACCEPTER une demande (avec FIFO sur les pochettes)
// ════════════════════════════════════════════════════════
if (isset($_GET['accepter'])) {
    $id_demande = (int)$_GET['accepter'];

    $stmt = $pdo->prepare("SELECT * FROM demande WHERE id_demande = ? AND id_banque = ?");
    $stmt->execute([$id_demande, $id_banque]);
    $demande = $stmt->fetch();

    if ($demande) {
        if ($demande['statut'] !== 'en_attente') {
            $erreur = "Cette demande a déjà été traitée.";
        } else {
            // Vérifier le stock général
            $stmtStock = $pdo->prepare("
                SELECT quantite_disponible
                FROM stock
                WHERE id_banque = ? AND id_groupe = ?
            ");
            $stmtStock->execute([$id_banque, $demande['id_groupe']]);
            $stock = $stmtStock->fetch();

            if (!$stock || $stock['quantite_disponible'] < $demande['quantite_demandee']) {
                // Refus auto pour stock insuffisant
                $pdo->prepare("
                    UPDATE demande SET statut = 'refusée', date_reponse = CURDATE()
                    WHERE id_demande = ? AND id_banque = ?
                ")->execute([$id_demande, $id_banque]);

                enregistrerActivite($pdo, 'banque', $id_banque,
                    'Refus automatique de la demande #' . $id_demande . ' : stock insuffisant');

                $erreur = "Demande refusée : stock insuffisant (quantité disponible dans le compteur 'stock' : "
                    . (float)($stock['quantite_disponible'] ?? 0)
                    . ", demandée : " . (int)$demande['quantite_demandee'] . ").";
            } else {
                // FIFO : sortir les pochettes les plus anciennes
                $resultatFIFO = sortirPochettesFIFO($pdo, $id_banque, $demande['id_groupe'], (int)$demande['quantite_demandee']);

                if (!$resultatFIFO) {
                    $pdo->prepare("
                        UPDATE demande SET statut = 'refusée', date_reponse = CURDATE()
                        WHERE id_demande = ? AND id_banque = ?
                    ")->execute([$id_demande, $id_banque]);

                    // Compter les pochettes individuelles réellement disponibles,
                    // pour expliquer l'écart avec le compteur global "stock".
                    $stmtNbPoch = $pdo->prepare("
                        SELECT COUNT(*) FROM pochette
                        WHERE id_banque = ? AND id_groupe = ? AND statut = 'disponible'
                    ");
                    $stmtNbPoch->execute([$id_banque, $demande['id_groupe']]);
                    $nbPochDisponibles = (int)$stmtNbPoch->fetchColumn();

                    enregistrerActivite($pdo, 'banque', $id_banque,
                        'Refus automatique de la demande #' . $id_demande
                        . ' : pochettes individuelles insuffisantes (table pochette = '
                        . $nbPochDisponibles . ', table stock = ' . (float)$stock['quantite_disponible'] . ')');

                    $erreur = "Demande refusée : pochettes disponibles insuffisantes. "
                        . "Le compteur global 'stock' indique " . (float)$stock['quantite_disponible'] . " unité(s), "
                        . "mais seulement " . $nbPochDisponibles . " pochette(s) individuelle(s) sont réellement "
                        . "enregistrées avec le statut 'disponible' dans la table pochette (demande : "
                        . (int)$demande['quantite_demandee'] . "). "
                        . "Vérifiez que chaque don/pochette a bien été saisi individuellement dans pochette "
                        . "(et pas seulement le compteur stock), ou que des pochettes n'ont pas été marquées "
                        . "'utilisee'/'expiree'/'detruite' par erreur.";
                } else {
                    // Accepter après FIFO réussi
                    $pdo->prepare("
                        UPDATE demande SET statut = 'acceptée', date_reponse = CURDATE()
                        WHERE id_demande = ? AND id_banque = ?
                    ")->execute([$id_demande, $id_banque]);

                    $pdo->prepare("
                        UPDATE stock
                        SET quantite_disponible = quantite_disponible - ?,
                            date_mise_a_jour = CURDATE()
                        WHERE id_banque = ? AND id_groupe = ?
                    ")->execute([$demande['quantite_demandee'], $id_banque, $demande['id_groupe']]);

                    verifierSeuilAlerte($pdo, $id_banque, $demande['id_groupe']);

                    $pdo->prepare("
                        INSERT INTO mouvement_stock
                        (reference_demande, id_banque, id_hopital, id_sous_banque, id_groupe, quantite, date_mouvement, type_mouvement)
                        VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'sortie')
                    ")->execute([
                        $id_demande, $id_banque,
                        $demande['id_hopital'], $demande['id_sous_banque'],
                        $demande['id_groupe'], $demande['quantite_demandee']
                    ]);

                    enregistrerActivite($pdo, 'banque', $id_banque,
                        'Acceptation de la demande #' . $id_demande);

                    $success = "Demande acceptée — FIFO appliqué et stock mis à jour !";
                }
            }
        }
    }
}

// ════════════════════════════════════════════════════════
// REFUSER manuellement une demande
// ════════════════════════════════════════════════════════
if (isset($_GET['refuser'])) {
    $id_demande = (int)$_GET['refuser'];

    $stmt = $pdo->prepare("SELECT statut FROM demande WHERE id_demande = ? AND id_banque = ?");
    $stmt->execute([$id_demande, $id_banque]);
    $demande = $stmt->fetch();

    if ($demande && $demande['statut'] === 'en_attente') {
        $pdo->prepare("
            UPDATE demande SET statut = 'refusée', date_reponse = CURDATE()
            WHERE id_demande = ? AND id_banque = ?
        ")->execute([$id_demande, $id_banque]);

        enregistrerActivite($pdo, 'banque', $id_banque,
            'Refus manuel de la demande #' . $id_demande);

        $success = "Demande refusée.";
    } else {
        $erreur = "Cette demande a déjà été traitée.";
    }
}

// ════════════════════════════════════════════════════════
// FILTRE par statut
// ════════════════════════════════════════════════════════
$filtre_statut = $_GET['statut'] ?? 'tous';
$where_filtre = "";
$params = [$id_banque];

if (in_array($filtre_statut, ['en_attente', 'acceptée', 'refusée'])) {
    $where_filtre = " AND d.statut = ?";
    $params[] = $filtre_statut;
}

// ════════════════════════════════════════════════════════
// CHARGEMENT des demandes (externes uniquement : sous-banque → banque mère)
// ════════════════════════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT d.*, sb.nom AS nom_sous_banque, g.libelle AS groupe
    FROM demande d
    JOIN sous_banque sb ON sb.id_sous_banque = d.id_sous_banque
    JOIN groupe_sanguin g ON g.id_groupe = d.id_groupe
    WHERE d.id_banque = ?
      AND d.type_demande = 'externe'
      $where_filtre
    ORDER BY d.date_demande DESC
");
$stmt->execute($params);
$demandes = $stmt->fetchAll();

// ════════════════════════════════════════════════════════
// STATISTIQUES
// ════════════════════════════════════════════════════════
$nb_total = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_banque = ? AND type_demande = 'externe'");
$nb_total->execute([$id_banque]);
$nb_total = (int)$nb_total->fetchColumn();

$nb_attente = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_banque = ? AND type_demande = 'externe' AND statut = 'en_attente'");
$nb_attente->execute([$id_banque]);
$nb_attente = (int)$nb_attente->fetchColumn();

$nb_acceptees = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_banque = ? AND type_demande = 'externe' AND statut = 'acceptée'");
$nb_acceptees->execute([$id_banque]);
$nb_acceptees = (int)$nb_acceptees->fetchColumn();

$nb_refusees = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_banque = ? AND type_demande = 'externe' AND statut = 'refusée'");
$nb_refusees->execute([$id_banque]);
$nb_refusees = (int)$nb_refusees->fetchColumn();

require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demandes — <?php echo htmlspecialchars($_SESSION['nom_banque'] ?? 'Banque'); ?> | E-Sang</title>
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
        .filtre-btn:not(.active) .filtre-count {
            background: #F3F4F6;
            color: #6B7280;
        }
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

    <!-- ══ TITRE ══ -->
    <div class="page-header">
        <h1>Demandes externes</h1>
        <p>Demandes de pochettes reçues des sous-banques. Acceptez ou refusez selon votre stock.</p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur);  ?></div><?php endif; ?>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">📋</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total demandes</div>
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
                <div class="stat-mini-label">Acceptées</div>
                <div class="stat-mini-number"><?php echo $nb_acceptees; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">❌</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Refusées</div>
                <div class="stat-mini-number"><?php echo $nb_refusees; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ TABLEAU DES DEMANDES ══ -->
    <div class="section">

        <div class="section-header">
            <div class="section-title">Demandes reçues</div>
            <span class="cnt-badge"><?php echo count($demandes); ?> demande(s) affichée(s)</span>
        </div>

        <!-- Filtres -->
        <div class="filtres-statut">
            <a href="demandes.php" class="filtre-btn <?php echo ($filtre_statut === 'tous') ? 'active' : ''; ?>">
                📋 Toutes <span class="filtre-count"><?php echo $nb_total; ?></span>
            </a>
            <a href="demandes.php?statut=en_attente" class="filtre-btn <?php echo ($filtre_statut === 'en_attente') ? 'active' : ''; ?>">
                ⏳ En attente <span class="filtre-count"><?php echo $nb_attente; ?></span>
            </a>
            <a href="demandes.php?statut=acceptée" class="filtre-btn <?php echo ($filtre_statut === 'acceptée') ? 'active' : ''; ?>">
                ✅ Acceptées <span class="filtre-count"><?php echo $nb_acceptees; ?></span>
            </a>
            <a href="demandes.php?statut=refusée" class="filtre-btn <?php echo ($filtre_statut === 'refusée') ? 'active' : ''; ?>">
                ❌ Refusées <span class="filtre-count"><?php echo $nb_refusees; ?></span>
            </a>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Sous-banque</th>
                        <th>Groupe</th>
                        <th>Quantité</th>
                        <th>Date Demande</th>
                        <th>Date Réponse</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($demandes): ?>
                        <?php foreach ($demandes as $d): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($d['nom_sous_banque']); ?></strong></td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                            <td><strong><?php echo (int)$d['quantite_demandee']; ?></strong> pochette(s)</td>
                            <td><?php echo date('d/m/Y', strtotime($d['date_demande'])); ?></td>
                            <td><?php echo $d['date_reponse'] ? date('d/m/Y', strtotime($d['date_reponse'])) : '—'; ?></td>
                            <td>
                                <?php if ($d['statut'] === 'en_attente'): ?>
                                    <span class="badge badge-attente">⏳ En attente</span>
                                <?php elseif ($d['statut'] === 'acceptée'): ?>
                                    <span class="badge badge-acceptee">✅ Acceptée</span>
                                <?php else: ?>
                                    <span class="badge badge-refusee">❌ Refusée</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <?php if ($d['statut'] === 'en_attente'): ?>
                                        <a href="demandes.php?accepter=<?php echo $d['id_demande']; ?>"
                                           class="btn-accepter"
                                           onclick="return confirm('Accepter cette demande ? Le FIFO sera appliqué automatiquement.')">
                                            ✓ Accepter
                                        </a>
                                        <a href="demandes.php?refuser=<?php echo $d['id_demande']; ?>"
                                           class="btn-refuser"
                                           onclick="return confirm('Refuser cette demande ?')">
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
                        <tr><td colspan="7" class="vide">
                            <?php if ($filtre_statut !== 'tous'): ?>
                                Aucune demande avec le statut sélectionné.
                            <?php else: ?>
                                Aucune demande reçue pour le moment.
                            <?php endif; ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>