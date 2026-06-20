<?php
session_start();
require_once '../config/db.php';

$page_active = 'demandes';
include 'sidebar.php';

$id_sb                = $_SESSION['id_sous_banque'];
$id_banque_principale = $_SESSION['id_banque_principale'];
$id_hopital           = $_SESSION['id_hopital'];
$success = $erreur    = "";

// ══════════════════════════════════════════════════════════════
//  FONCTION : Vérifier seuil et déclencher alerte + demande auto
// ══════════════════════════════════════════════════════════════
function verifierSeuilEtAlerter($pdo, $id_sb, $id_groupe, $id_banque_principale, $id_hopital) {
    $stmt = $pdo->prepare("
        SELECT quantite_disponible, seuil_alerte
        FROM stock_sous_banque
        WHERE id_sous_banque = ? AND id_groupe = ?
    ");
    $stmt->execute([$id_sb, $id_groupe]);
    $stock = $stmt->fetch();
    if (!$stock) return;

    $qty   = (int)$stock['quantite_disponible'];
    $seuil = (int)$stock['seuil_alerte'];

    if ($qty === 0)               $type_alerte = 'rupture';
    elseif ($qty <= $seuil)       $type_alerte = 'critique';
    elseif ($qty <= $seuil * 1.5) $type_alerte = 'avertissement';
    else return;

    // Ne pas créer de doublon d'alerte active
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM alerte_stock WHERE id_sous_banque = ? AND id_groupe = ? AND traitee = 0");
    $stmt->execute([$id_sb, $id_groupe]);
    if ((int)$stmt->fetchColumn() > 0) return;

    $qte_a_demander = max(1, ($seuil * 2) - $qty);

    $pdo->prepare("
        INSERT INTO demande (id_hopital, id_sous_banque, id_banque, id_groupe, quantite_demandee,
                             date_demande, statut, type_demande, note)
        VALUES (?, ?, ?, ?, ?, CURDATE(), 'en_attente', 'externe',
                'Demande automatique — seuil d\'alerte atteint')
    ")->execute([$id_hopital, $id_sb, $id_banque_principale, $id_groupe, $qte_a_demander]);

    $id_demande_auto = (int)$pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO alerte_stock (id_sous_banque, id_groupe, quantite_actuelle,
                                  seuil_alerte, type_alerte, demande_auto,
                                  id_demande_auto, date_alerte, traitee)
        VALUES (?, ?, ?, ?, ?, 1, ?, NOW(), 0)
    ")->execute([$id_sb, $id_groupe, $qty, $seuil, $type_alerte, $id_demande_auto]);

    // Tracer l'alerte déclenchée et la demande auto dans l'historique centralisé
    $stmtG = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
    $stmtG->execute([$id_groupe]);
    $libelle_groupe_auto = $stmtG->fetchColumn();

    $pdo->prepare("
        INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action)
        VALUES (?, ?, 'alerte_declenchee', ?, ?, NOW())
    ")->execute([
        $id_sb, $id_groupe, $qty,
        "Alerte {$type_alerte} déclenchée pour {$libelle_groupe_auto} ({$qty} pochette(s) restante(s), seuil {$seuil})"
    ]);

    $pdo->prepare("
        INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action)
        VALUES (?, ?, 'demande_envoyee', ?, ?, NOW())
    ")->execute([
        $id_sb, $id_groupe, $qte_a_demander,
        "Demande automatique envoyée à la banque principale : {$qte_a_demander} pochette(s) {$libelle_groupe_auto} (seuil atteint)"
    ]);
}

// ══════════════════════════════════════════════════════════════
//  FONCTION : Déduire une quantité des lots disponibles (FIFO)
//  Consomme en priorité les lots qui expirent le plus tôt, pour
//  éviter de garder du sang périssable pendant qu'on distribue
//  du stock plus frais. Marque un lot 'epuise' s'il atteint 0.
//  Retourne true si la déduction a pu être faite intégralement.
// ══════════════════════════════════════════════════════════════
function deduireLotsFIFO($pdo, $id_sb, $id_groupe, $quantite_a_deduire) {
    $stmt = $pdo->prepare("
        SELECT id_lot, quantite
        FROM lot_sang_sous_banque
        WHERE id_sous_banque = ? AND id_groupe = ? AND statut = 'disponible' AND quantite > 0
        ORDER BY date_expiration ASC
        FOR UPDATE
    ");
    $stmt->execute([$id_sb, $id_groupe]);
    $lots = $stmt->fetchAll();

    $restant = $quantite_a_deduire;

    foreach ($lots as $lot) {
        if ($restant <= 0) break;

        $pris = min($restant, (int)$lot['quantite']);
        $nouvelle_quantite = (int)$lot['quantite'] - $pris;

        if ($nouvelle_quantite <= 0) {
            $pdo->prepare("UPDATE lot_sang_sous_banque SET quantite = 0, statut = 'epuise' WHERE id_lot = ?")
                ->execute([$lot['id_lot']]);
        } else {
            $pdo->prepare("UPDATE lot_sang_sous_banque SET quantite = ? WHERE id_lot = ?")
                ->execute([$nouvelle_quantite, $lot['id_lot']]);
        }

        $restant -= $pris;
    }

    // $restant > 0 signifie que les lots connus ne couvraient pas tout
    // (incohérence possible avec stock_sous_banque, mais on ne bloque pas
    // l'opération principale pour autant — on le signale simplement).
    return $restant <= 0;
}

// ══════════════════════════════════════════════════════════════
//  TRAITEMENT : Répondre à une demande de l'hôpital
//  - Si stock suffisant  → accepter + déduire stock
//  - Si stock insuffisant → refuser + demande auto à la banque
// ══════════════════════════════════════════════════════════════
if (isset($_GET['traiter'])) {
    $id_demande = (int)$_GET['traiter'];

    // Récupérer la demande
    $stmt = $pdo->prepare("
        SELECT d.*, g.libelle AS groupe
        FROM demande d
        JOIN groupe_sanguin g ON g.id_groupe = d.id_groupe
        WHERE d.id_demande = ? AND d.id_sous_banque = ? AND d.type_demande = 'interne' AND d.statut = 'en_attente'
    ");
    $stmt->execute([$id_demande, $id_sb]);
    $demande = $stmt->fetch();

    if (!$demande) {
        $erreur = "Demande introuvable ou déjà traitée.";
    } else {
        $id_groupe = (int)$demande['id_groupe'];
        $quantite  = (int)$demande['quantite_demandee'];

        // Vérifier stock disponible
        $stmt = $pdo->prepare("
            SELECT COALESCE(quantite_disponible, 0) FROM stock_sous_banque
            WHERE id_sous_banque = ? AND id_groupe = ?
        ");
        $stmt->execute([$id_sb, $id_groupe]);
        $stock_dispo = (int)$stmt->fetchColumn();

        if ($stock_dispo >= $quantite) {
            // ── Stock suffisant → ACCEPTER automatiquement ──
            $pdo->beginTransaction();
            try {
                // 1. Mettre à jour statut demande
                $pdo->prepare("
                    UPDATE demande SET statut = 'acceptée', date_reponse = CURDATE()
                    WHERE id_demande = ?
                ")->execute([$id_demande]);

                // 2. Déduire du stock sous-banque (total agrégé)
                $pdo->prepare("
                    UPDATE stock_sous_banque
                    SET quantite_disponible = quantite_disponible - ?,
                        date_mise_a_jour = CURDATE()
                    WHERE id_sous_banque = ? AND id_groupe = ?
                ")->execute([$quantite, $id_sb, $id_groupe]);

                // 3. Déduire des lots individuels (FIFO : lot qui expire le plus tôt en premier)
                $lots_suffisants = deduireLotsFIFO($pdo, $id_sb, $id_groupe, $quantite);

                // 4. Tracer mouvement
                $pdo->prepare("
                    INSERT INTO mouvement_stock (id_sous_banque, id_groupe, quantite, type_mouvement, date_mouvement, note)
                    VALUES (?, ?, ?, 'sortie', NOW(), 'Demande hôpital acceptée — automatique')
                ")->execute([$id_sb, $id_groupe, $quantite]);

                // 5. Tracer dans l'historique centralisé
                $note_lots = $lots_suffisants ? '' : ' [Attention : lots insuffisants pour couvrir cette sortie — vérifier la cohérence du stock]';
                $pdo->prepare("
                    INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action)
                    VALUES (?, ?, 'demande_recue_traitee', ?, ?, NOW())
                ")->execute([
                    $id_sb, $id_groupe, $quantite,
                    "Demande hôpital acceptée : {$quantite} pochette(s) {$demande['groupe']} accordées" . $note_lots
                ]);

                $pdo->commit();

                // 6. Vérifier seuil après déduction (hors transaction : lecture + écritures indépendantes)
                verifierSeuilEtAlerter($pdo, $id_sb, $id_groupe, $id_banque_principale, $id_hopital);

                $success = "Demande acceptée — <strong>$quantite</strong> pochette(s) de <strong>" . htmlspecialchars($demande['groupe']) . "</strong> accordées à l'hôpital.";
                if (!$lots_suffisants) {
                    $success .= " <em>(Avertissement : le détail par lot était insuffisant pour couvrir cette quantité — vérifiez la page Suivi des Lots.)</em>";
                }

            } catch (Exception $e) {
                $pdo->rollBack();
                $erreur = "Erreur lors du traitement de la demande. Aucune modification n'a été appliquée.";
            }

        } else {
            // ── Stock insuffisant → REFUSER + demande auto à la banque ──

            // 1. Refuser la demande hôpital
            $pdo->prepare("
                UPDATE demande SET statut = 'refusée', date_reponse = CURDATE(),
                note = CONCAT(COALESCE(note,''), ' | Refus automatique : stock insuffisant (', ?, ' disponible(s))')
                WHERE id_demande = ?
            ")->execute([$stock_dispo, $id_demande]);

            // 2. Tracer le refus dans l'historique centralisé
            $pdo->prepare("
                INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action)
                VALUES (?, ?, 'demande_recue_traitee', ?, ?, NOW())
            ")->execute([
                $id_sb, $id_groupe, $quantite,
                "Demande hôpital refusée : stock insuffisant pour {$quantite} pochette(s) {$demande['groupe']} ({$stock_dispo} disponible(s))"
            ]);

            // 3. Vérifier si une demande externe existe déjà en attente pour ce groupe
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM demande
                WHERE id_sous_banque = ? AND id_groupe = ?
                AND type_demande = 'externe' AND statut = 'en_attente'
            ");
            $stmt->execute([$id_sb, $id_groupe]);
            $demande_externe_existe = (int)$stmt->fetchColumn() > 0;

            if (!$demande_externe_existe) {
                // 4. Créer demande automatique à la banque principale
                $qte_a_demander = max($quantite, ($demande['seuil_alerte'] ?? 3) * 2);
                $pdo->prepare("
                    INSERT INTO demande (id_hopital, id_sous_banque, id_banque, id_groupe, quantite_demandee,
                                         date_demande, statut, type_demande, note)
                    VALUES (?, ?, ?, ?, ?, CURDATE(), 'en_attente', 'externe',
                            'Demande automatique — stock insuffisant pour répondre à l\'hôpital')
                ")->execute([$id_hopital, $id_sb, $id_banque_principale, $id_groupe, $qte_a_demander]);

                // 5. Tracer la demande automatique dans l'historique centralisé
                $pdo->prepare("
                    INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action)
                    VALUES (?, ?, 'demande_envoyee', ?, ?, NOW())
                ")->execute([
                    $id_sb, $id_groupe, $qte_a_demander,
                    "Demande automatique envoyée à la banque principale : {$qte_a_demander} pochette(s) {$demande['groupe']}"
                ]);

                $success = "Stock insuffisant (<strong>$stock_dispo</strong> disponible(s)). Demande refusée à l'hôpital et une demande de <strong>$qte_a_demander</strong> pochette(s) a été envoyée automatiquement à la banque principale.";
            } else {
                $success = "Stock insuffisant (<strong>$stock_dispo</strong> disponible(s)). Demande refusée à l'hôpital. Une demande est déjà en cours auprès de la banque principale.";
            }
        }
    }
}

// ── Demandes internes reçues de l'hôpital (en attente) ──
$stmt = $pdo->prepare("
    SELECT d.*, g.libelle AS groupe,
           COALESCE(s.quantite_disponible, 0) AS stock_dispo
    FROM demande d
    JOIN groupe_sanguin g ON g.id_groupe = d.id_groupe
    LEFT JOIN stock_sous_banque s ON s.id_groupe = d.id_groupe AND s.id_sous_banque = d.id_sous_banque
    WHERE d.id_sous_banque = ? AND d.type_demande = 'interne'
    ORDER BY
        CASE d.statut WHEN 'en_attente' THEN 0 ELSE 1 END,
        d.date_demande DESC
");
$stmt->execute([$id_sb]);
$demandes_hopital = $stmt->fetchAll();

// ── Demandes externes envoyées à la banque principale ──
$stmt = $pdo->prepare("
    SELECT d.*, g.libelle AS groupe, b.nom AS nom_banque
    FROM demande d
    JOIN groupe_sanguin g ON g.id_groupe = d.id_groupe
    JOIN banque_de_sang b ON b.id_banque = d.id_banque
    WHERE d.id_sous_banque = ? AND d.type_demande = 'externe'
    ORDER BY d.date_demande DESC
    LIMIT 20
");
$stmt->execute([$id_sb]);
$demandes_banque = $stmt->fetchAll();

// Compteurs
$nb_attente   = count(array_filter($demandes_hopital, fn($d) => $d['statut'] === 'en_attente'));
$nb_acceptees = count(array_filter($demandes_hopital, fn($d) => $d['statut'] === 'acceptée'));
$nb_refusees  = count(array_filter($demandes_hopital, fn($d) => $d['statut'] === 'refusée'));
$nb_ext_attente = count(array_filter($demandes_banque, fn($d) => $d['statut'] === 'en_attente'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demandes | E-Sang</title>
    <style>
        .tabs {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #E5E7EB;
            margin-bottom: 24px;
        }

        .tab-btn {
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            color: #6B7280;
            background: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
            position: relative;
            bottom: -2px;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover { color: #6B0000; }

        .tab-btn.active {
            color: #6B0000;
            border-bottom-color: #6B0000;
            font-weight: 700;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .badge-count {
            background: #6B0000;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 999px;
        }

        .badge-count-gray {
            background: #E5E7EB;
            color: #374151;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 999px;
        }

        /* Bouton traiter */
        .btn-traiter {
            background: #6B0000;
            color: #FFFFFF;
            border: none;
            border-radius: 7px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s;
        }
        .btn-traiter:hover { background: #4B0000; }

        /* Stock dispo inline */
        .stock-inline {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 6px;
        }
        .stock-ok      { background: #D1FAE5; color: #065F46; }
        .stock-insuffisant { background: #FEF2F2; color: #6B0000; }
    </style>
</head>
<body>
<div class="main-content">

    <div class="page-header">
        <h1>Demandes</h1>
        <p>Gérez les demandes reçues de l'hôpital et suivez les demandes envoyées à la banque principale.</p>
    </div>

    <?php if ($success): ?>
        <div class="alerte-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur); ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats" style="grid-template-columns:repeat(4,1fr); margin-bottom:28px;">
        <div class="stat-card" style="<?php echo $nb_attente > 0 ? 'border-color:#FCD34D;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">En attente</span>
                <span class="stat-icon ic-org">⏳</span>
            </div>
            <span class="stat-number" style="<?php echo $nb_attente > 0 ? 'color:#92400E;' : ''; ?>"><?php echo $nb_attente; ?></span>
        </div>
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-label">Acceptées</span>
                <span class="stat-icon ic-grn">✅</span>
            </div>
            <span class="stat-number" style="color:#166534;"><?php echo $nb_acceptees; ?></span>
        </div>
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-label">Refusées</span>
                <span class="stat-icon ic-red">❌</span>
            </div>
            <span class="stat-number" style="color:#6B0000;"><?php echo $nb_refusees; ?></span>
        </div>
        <div class="stat-card" style="<?php echo $nb_ext_attente > 0 ? 'border-color:#BFDBFE;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">Vers banque</span>
                <span class="stat-icon ic-blu">🏦</span>
            </div>
            <span class="stat-number" style="color:#1D4ED8;"><?php echo $nb_ext_attente; ?></span>
        </div>
    </div>

    <!-- ONGLETS -->
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('tab-hopital', this)">
            🏥 Demandes de l'hôpital
            <?php if ($nb_attente > 0): ?>
                <span class="badge-count"><?php echo $nb_attente; ?></span>
            <?php endif; ?>
        </button>
        <button class="tab-btn" onclick="switchTab('tab-banque', this)">
            🏦 Envoyées à la banque
            <span class="badge-count-gray"><?php echo count($demandes_banque); ?></span>
        </button>
    </div>

    <!-- ONGLET 1 : Demandes de l'hôpital -->
    <div id="tab-hopital" class="tab-content active">
        <div class="section">
            <div class="section-header">
                <div class="section-title">Demandes reçues de l'hôpital</div>
                <span class="cnt-badge"><?php echo count($demandes_hopital); ?> demande(s)</span>
            </div>

            <?php if ($nb_attente > 0): ?>
            <div class="alerte-warning" style="margin-bottom:16px;">
                ⏳ <strong><?php echo $nb_attente; ?></strong> demande(s) en attente de traitement.
                Le système va vérifier automatiquement le stock et répondre.
            </div>
            <?php endif; ?>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Groupe</th>
                            <th>Quantité demandée</th>
                            <th>Stock disponible</th>
                            <th>Date demande</th>
                            <th>Date réponse</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($demandes_hopital): ?>
                            <?php foreach ($demandes_hopital as $d):
                                $stock_ok = (int)$d['stock_dispo'] >= (int)$d['quantite_demandee'];
                            ?>
                            <tr>
                                <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                                <td><strong><?php echo (int)$d['quantite_demandee']; ?></strong> pochette(s)</td>
                                <td>
                                    <span class="stock-inline <?php echo $stock_ok ? 'stock-ok' : 'stock-insuffisant'; ?>">
                                        <?php echo (int)$d['stock_dispo']; ?> dispo
                                        <?php echo $stock_ok ? '✓' : '✗'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($d['date_demande'])); ?></td>
                                <td>
                                    <?php echo $d['date_reponse']
                                        ? date('d/m/Y H:i', strtotime($d['date_reponse']))
                                        : '<span style="color:#9CA3AF;">—</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($d['statut'] === 'en_attente'): ?>
                                        <span class="badge badge-attente">En attente</span>
                                    <?php elseif ($d['statut'] === 'acceptée'): ?>
                                        <span class="badge badge-ok">✓ Acceptée</span>
                                    <?php else: ?>
                                        <span class="badge badge-vide">✗ Refusée</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($d['statut'] === 'en_attente'): ?>
                                        <a href="?traiter=<?php echo $d['id_demande']; ?>"
                                           class="btn-traiter"
                                           onclick="return confirm('Traiter cette demande automatiquement ?')">
                                            ⚡ Traiter
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#9CA3AF; font-size:12px;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="vide">Aucune demande reçue de l'hôpital pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ONGLET 2 : Demandes envoyées à la banque -->
    <div id="tab-banque" class="tab-content">
        <div class="section">
            <div class="section-header">
                <div class="section-title">Demandes envoyées à la banque principale</div>
                <span class="cnt-badge"><?php echo count($demandes_banque); ?> demande(s)</span>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Banque</th>
                            <th>Groupe</th>
                            <th>Quantité</th>
                            <th>Date demande</th>
                            <th>Date réponse</th>
                            <th>Note</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($demandes_banque): ?>
                            <?php foreach ($demandes_banque as $d): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($d['nom_banque']); ?></td>
                                <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                                <td><strong><?php echo (int)$d['quantite_demandee']; ?></strong> pochette(s)</td>
                                <td><?php echo date('d/m/Y', strtotime($d['date_demande'])); ?></td>
                                <td>
                                    <?php echo $d['date_reponse']
                                        ? date('d/m/Y', strtotime($d['date_reponse']))
                                        : '<span style="color:#9CA3AF;">—</span>';
                                    ?>
                                </td>
                                <td style="max-width:180px; font-size:12px; color:#6B7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo htmlspecialchars($d['note'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($d['note'] ?? '—'); ?>
                                </td>
                                <td>
                                    <?php if ($d['statut'] === 'en_attente'): ?>
                                        <span class="badge badge-attente">En attente</span>
                                    <?php elseif ($d['statut'] === 'acceptée'): ?>
                                        <span class="badge badge-ok">✓ Acceptée</span>
                                    <?php else: ?>
                                        <span class="badge badge-vide">✗ Refusée</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="vide">Aucune demande envoyée à la banque principale pour le moment.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>