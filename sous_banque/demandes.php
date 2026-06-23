<?php
session_start();
require_once '../config/db.php';
require_once '../config/notifications_helper.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sous_banque') {
    header("Location: ../index.php");
    exit;
}

$id_sb                = $_SESSION['id_sous_banque'];
$id_banque_principale = $_SESSION['id_banque_principale'];
$id_hopital           = $_SESSION['id_hopital'];
$success = $erreur    = "";

// ════════════════════════════════════════════════════════════════
// HELPER : Déduction FIFO des lots
// ════════════════════════════════════════════════════════════════
function deduireLotsFIFO($pdo, $id_sb, $id_groupe, $quantite_a_deduire) {
    $stmt = $pdo->prepare("
        SELECT id_lot, quantite, date_expiration
        FROM lot_sang_sous_banque
        WHERE id_sous_banque = ? AND id_groupe = ? AND statut = 'disponible' AND quantite > 0
        ORDER BY date_expiration ASC, id_lot ASC
    ");
    $stmt->execute([$id_sb, $id_groupe]);
    $lots = $stmt->fetchAll();

    $reste = $quantite_a_deduire;
    foreach ($lots as $l) {
        if ($reste <= 0) break;
        $a_prendre = min($reste, (int)$l['quantite']);
        $nouvelle_qte = (int)$l['quantite'] - $a_prendre;

        if ($nouvelle_qte == 0) {
            $pdo->prepare("UPDATE lot_sang_sous_banque SET quantite = 0, statut = 'epuise' WHERE id_lot = ?")
                ->execute([$l['id_lot']]);
        } else {
            $pdo->prepare("UPDATE lot_sang_sous_banque SET quantite = ? WHERE id_lot = ?")
                ->execute([$nouvelle_qte, $l['id_lot']]);
        }
        $reste -= $a_prendre;
    }
    return $reste === 0;
}

// ════════════════════════════════════════════════════════════════
// HELPER : Vérifier seuil et créer alerte
// ════════════════════════════════════════════════════════════════
function verifierSeuilEtAlerter($pdo, $id_sb, $id_groupe, $id_banque, $id_hopital) {
    $stmt = $pdo->prepare("
        SELECT s.quantite_disponible, s.seuil_alerte, g.libelle
        FROM stock_sous_banque s
        JOIN groupe_sanguin g ON g.id_groupe = s.id_groupe
        WHERE s.id_sous_banque = ? AND s.id_groupe = ?
    ");
    $stmt->execute([$id_sb, $id_groupe]);
    $row = $stmt->fetch();
    if (!$row) return;

    $q = (int)$row['quantite_disponible'];
    $seuil = (int)$row['seuil_alerte'];

    if ($q <= $seuil) {
        $type = $q === 0 ? 'rupture' : ($q <= ceil($seuil / 2) ? 'critique' : 'avertissement');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM alerte_stock
            WHERE id_sous_banque = ? AND id_groupe = ? AND traitee = 0
        ");
        $stmt->execute([$id_sb, $id_groupe]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->prepare("
                INSERT INTO alerte_stock
                (id_sous_banque, id_groupe, quantite_actuelle, seuil_alerte, type_alerte, date_alerte, traitee)
                VALUES (?, ?, ?, ?, ?, NOW(), 0)
            ")->execute([$id_sb, $id_groupe, $q, $seuil, $type]);
        }
    }
}

// ════════════════════════════════════════════════════════════════
// TRAITEMENT : Créer une nouvelle demande manuelle vers la banque
// ════════════════════════════════════════════════════════════════
if (isset($_POST['nouvelle_demande_banque'])) {
    $id_groupe = (int)($_POST['id_groupe'] ?? 0);
    $quantite  = (int)($_POST['quantite_demandee'] ?? 0);
    $note      = trim($_POST['note'] ?? '');

    if ($id_groupe <= 0 || $quantite <= 0) {
        $erreur = "Veuillez choisir un groupe et une quantité valide.";
    } elseif ($quantite > 500) {
        $erreur = "La quantité maximale par demande est de 500 pochettes.";
    } elseif (!$id_banque_principale) {
        $erreur = "Erreur : aucune banque principale associée à ce dépôt.";
    } else {
        $pdo->prepare("
            INSERT INTO demande (id_hopital, id_sous_banque, id_banque, id_groupe, quantite_demandee,
                                 date_demande, statut, type_demande, note)
            VALUES (?, ?, ?, ?, ?, CURDATE(), 'en_attente', 'externe', ?)
        ")->execute([$id_hopital, $id_sb, $id_banque_principale, $id_groupe, $quantite, $note ?: 'Demande manuelle']);

        $stmtG = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
        $stmtG->execute([$id_groupe]);
        $libelle_groupe = $stmtG->fetchColumn();

        $pdo->prepare("
            INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action)
            VALUES (?, ?, 'demande_envoyee', ?, ?, NOW())
        ")->execute([
            $id_sb, $id_groupe, $quantite,
            "Demande manuelle envoyée à la banque principale : {$quantite} pochette(s) {$libelle_groupe}"
        ]);

        $success = "Demande envoyée avec succès à la banque principale ({$quantite} pochette(s) de {$libelle_groupe}).";
    }
}

// ════════════════════════════════════════════════════════════════
// TRAITEMENT : Répondre à une demande hôpital
//   - Stock suffisant  → accepter + déduire (FIFO)
//   - Stock insuffisant → refuser + demande auto à la banque
// ════════════════════════════════════════════════════════════════
if (isset($_GET['traiter'])) {
    $id_demande = (int)$_GET['traiter'];

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

        $stmt = $pdo->prepare("SELECT COALESCE(quantite_disponible, 0) FROM stock_sous_banque WHERE id_sous_banque = ? AND id_groupe = ?");
        $stmt->execute([$id_sb, $id_groupe]);
        $stock_dispo = (int)$stmt->fetchColumn();

        if ($stock_dispo >= $quantite) {
            // ── Acceptation ──
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE demande SET statut = 'acceptée', date_reponse = CURDATE() WHERE id_demande = ?")->execute([$id_demande]);
                $pdo->prepare("UPDATE stock_sous_banque SET quantite_disponible = quantite_disponible - ?, date_mise_a_jour = CURDATE() WHERE id_sous_banque = ? AND id_groupe = ?")->execute([$quantite, $id_sb, $id_groupe]);
                $lots_suffisants = deduireLotsFIFO($pdo, $id_sb, $id_groupe, $quantite);

                $pdo->prepare("INSERT INTO mouvement_stock (id_sous_banque, id_groupe, quantite, type_mouvement, date_mouvement, note) VALUES (?, ?, ?, 'sortie', NOW(), 'Demande hôpital acceptée')")->execute([$id_sb, $id_groupe, $quantite]);

                $pdo->prepare("INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action) VALUES (?, ?, 'demande_recue_traitee', ?, ?, NOW())")
                    ->execute([$id_sb, $id_groupe, $quantite, "Demande hôpital acceptée : {$quantite} pochette(s) {$demande['groupe']}"]);

                $pdo->commit();
                verifierSeuilEtAlerter($pdo, $id_sb, $id_groupe, $id_banque_principale, $id_hopital);

                // Notifier l'hôpital
                notifier_hopital($pdo, $id_hopital,
                    "Demande acceptée",
                    "Votre demande de {$quantite} pochette(s) de {$demande['groupe']} a été acceptée par la sous-banque.",
                    'succes');

                $success = "Demande acceptée — <strong>$quantite</strong> pochette(s) de <strong>" . htmlspecialchars($demande['groupe']) . "</strong> accordées à l'hôpital.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $erreur = "Erreur lors du traitement de la demande.";
            }

        } else {
            // ── Refus + demande auto ──
            $pdo->prepare("
                UPDATE demande SET statut = 'refusée', date_reponse = CURDATE(),
                       note = CONCAT(COALESCE(note,''), ' | Stock insuffisant (', ?, ' disponible(s))')
                WHERE id_demande = ?
            ")->execute([$stock_dispo, $id_demande]);

            $pdo->prepare("INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action) VALUES (?, ?, 'demande_recue_traitee', ?, ?, NOW())")
                ->execute([$id_sb, $id_groupe, $quantite, "Demande hôpital refusée : stock insuffisant ({$stock_dispo}/{$quantite})"]);

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_sous_banque = ? AND id_groupe = ? AND type_demande = 'externe' AND statut = 'en_attente'");
            $stmt->execute([$id_sb, $id_groupe]);
            $demande_externe_existe = (int)$stmt->fetchColumn() > 0;

            if (!$demande_externe_existe) {
                $qte_a_demander = max($quantite, ($demande['seuil_alerte'] ?? 5) * 2);
                $pdo->prepare("INSERT INTO demande (id_hopital, id_sous_banque, id_banque, id_groupe, quantite_demandee, date_demande, statut, type_demande, note) VALUES (?, ?, ?, ?, ?, CURDATE(), 'en_attente', 'externe', 'Demande automatique - stock insuffisant')")
                    ->execute([$id_hopital, $id_sb, $id_banque_principale, $id_groupe, $qte_a_demander]);

                $pdo->prepare("INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action) VALUES (?, ?, 'demande_envoyee', ?, ?, NOW())")
                    ->execute([$id_sb, $id_groupe, $qte_a_demander, "Demande automatique envoyée : {$qte_a_demander} pochette(s) {$demande['groupe']}"]);

                notifier_hopital($pdo, $id_hopital,
                    "Demande refusée — stock insuffisant",
                    "Votre demande de {$quantite} pochette(s) de {$demande['groupe']} a été refusée (stock : {$stock_dispo}). Une demande automatique de {$qte_a_demander} pochette(s) a été envoyée à la banque principale.",
                    'alerte');

                $success = "Stock insuffisant. Demande refusée et demande auto de <strong>$qte_a_demander</strong> pochette(s) envoyée à la banque principale.";
            } else {
                notifier_hopital($pdo, $id_hopital,
                    "Demande refusée — stock insuffisant",
                    "Votre demande de {$quantite} pochette(s) de {$demande['groupe']} a été refusée (stock : {$stock_dispo}). Une demande auprès de la banque principale est déjà en cours.",
                    'alerte');

                $success = "Stock insuffisant. Demande refusée. Une demande est déjà en cours auprès de la banque principale.";
            }
        }
    }
}

// ── Demandes internes reçues de l'hôpital ──
$stmt = $pdo->prepare("
    SELECT d.*, g.libelle AS groupe,
           COALESCE(s.quantite_disponible, 0) AS stock_dispo
    FROM demande d
    JOIN groupe_sanguin g ON g.id_groupe = d.id_groupe
    LEFT JOIN stock_sous_banque s ON s.id_groupe = d.id_groupe AND s.id_sous_banque = d.id_sous_banque
    WHERE d.id_sous_banque = ? AND d.type_demande = 'interne'
    ORDER BY CASE d.statut WHEN 'en_attente' THEN 0 ELSE 1 END, d.date_demande DESC
");
$stmt->execute([$id_sb]);
$demandes_hopital = $stmt->fetchAll();

// ── Demandes externes envoyées à la banque mère ──
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

// ── Stats ──
$nb_attente     = count(array_filter($demandes_hopital, fn($d) => $d['statut'] === 'en_attente'));
$nb_acceptees   = count(array_filter($demandes_hopital, fn($d) => $d['statut'] === 'acceptée'));
$nb_refusees    = count(array_filter($demandes_hopital, fn($d) => $d['statut'] === 'refusée'));
$nb_ext_attente = count(array_filter($demandes_banque, fn($d) => $d['statut'] === 'en_attente'));

$groupes = $pdo->query("SELECT * FROM groupe_sanguin ORDER BY libelle")->fetchAll();

$page_active = 'demandes';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demandes — <?php echo htmlspecialchars($_SESSION['nom_sb'] ?? 'Sous-banque'); ?> | E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* Onglets */
        .tab-container {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 14px;
            padding: 24px;
            margin-top: 16px;
        }
        .tab-headers {
            display: flex; gap: 8px;
            border-bottom: 2px solid #F3F4F6;
            margin-bottom: 20px;
        }
        .tab-btn {
            background: none; border: none;
            padding: 11px 18px;
            font-family: 'Inter', sans-serif;
            font-size: 14px; font-weight: 700;
            color: #6B7280;
            cursor: pointer;
            position: relative; bottom: -2px;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }
        .tab-btn:hover { color: #8B0000; }
        .tab-btn.active { color: #8B0000; border-bottom-color: #8B0000; }
        .tab-badge {
            background: #FEE2E2; color: #B91C1C;
            padding: 1px 8px; border-radius: 999px;
            font-size: 11px; font-weight: 800; margin-left: 4px;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <!-- ══ TOP-BAR ══ -->
    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar"><?php echo $agent_initials; ?></div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bonjour, <?php echo $agent_display; ?></div>
                <div class="top-bar-role">Agent — <?php echo htmlspecialchars($_SESSION['nom_sb'] ?? 'Sous-banque'); ?></div>
            </div>
        </div>
    </div>

    <!-- ══ TITRE + BOUTON ══ -->
    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1>Demandes</h1>
            <p>Traitez les demandes des hôpitaux et faites vos demandes à la banque principale.</p>
        </div>
        <button class="btn-submit" onclick="ouvrirModal()" style="width:auto; margin-top:0; padding:11px 22px;">
            + Nouvelle demande à la banque
        </button>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo $success; ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur); ?></div><?php endif; ?>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">⏳</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Hôpital — en attente</div>
                <div class="stat-mini-number <?php echo $nb_attente > 0 ? 'alert' : ''; ?>"><?php echo $nb_attente; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">✅</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Hôpital — acceptées</div>
                <div class="stat-mini-number"><?php echo $nb_acceptees; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">❌</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Hôpital — refusées</div>
                <div class="stat-mini-number"><?php echo $nb_refusees; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">📤</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Banque — en attente</div>
                <div class="stat-mini-number <?php echo $nb_ext_attente > 0 ? 'alert' : ''; ?>"><?php echo $nb_ext_attente; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ ONGLETS ══ -->
    <div class="tab-container">
        <div class="tab-headers">
            <button class="tab-btn active" onclick="switchTab('tab-recues', this)">
                📥 Demandes reçues (Hôpital)
                <?php if ($nb_attente > 0): ?><span class="tab-badge"><?php echo $nb_attente; ?></span><?php endif; ?>
            </button>
            <button class="tab-btn" onclick="switchTab('tab-envoyees', this)">
                📤 Demandes envoyées (Banque)
            </button>
        </div>

        <!-- Onglet : Demandes reçues -->
        <div id="tab-recues" class="tab-content active">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Groupe</th>
                            <th>Quantité demandée</th>
                            <th>Stock dispo.</th>
                            <th>Date demande</th>
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
                                <td><strong><?php echo (int)$d['quantite_demandee']; ?></strong> poch.</td>
                                <td>
                                    <strong style="color:<?php echo $stock_ok ? '#16A34A' : '#DC2626'; ?>;">
                                        <?php echo (int)$d['stock_dispo']; ?>
                                    </strong> poch.
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($d['date_demande'])); ?></td>
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
                                    <?php if ($d['statut'] === 'en_attente'): ?>
                                        <a href="demandes.php?traiter=<?php echo $d['id_demande']; ?>"
                                           class="<?php echo $stock_ok ? 'btn-accepter' : 'btn-refuser'; ?>"
                                           onclick="return confirm('<?php echo $stock_ok ? 'Accepter la demande ? Le stock sera déduit (FIFO).' : 'Stock insuffisant ! La demande sera refusée et une demande auto envoyée à la banque mère.'; ?>');">
                                            <?php echo $stock_ok ? '✓ Traiter (accepter)' : '✗ Traiter (refuser + auto)'; ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#9CA3AF;font-size:12px;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="vide">Aucune demande reçue de l'hôpital.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Onglet : Demandes envoyées -->
        <div id="tab-envoyees" class="tab-content">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Banque mère</th>
                            <th>Groupe</th>
                            <th>Quantité</th>
                            <th>Date envoi</th>
                            <th>Date réponse</th>
                            <th>Statut</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($demandes_banque): ?>
                            <?php foreach ($demandes_banque as $d): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($d['nom_banque']); ?></strong></td>
                                <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                                <td><strong><?php echo (int)$d['quantite_demandee']; ?></strong> poch.</td>
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
                                <td><small style="color:#6B7280;"><?php echo htmlspecialchars(mb_substr($d['note'] ?? '', 0, 60)); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="vide">Aucune demande envoyée à la banque principale.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ══ MODAL : Nouvelle demande ══ -->
<div class="modal" id="modalDemande">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Nouvelle demande à la banque principale</div>
            <button class="modal-close" onclick="fermerModal()">×</button>
        </div>

        <p style="color:#6B7280; font-size:13px; margin-bottom:20px;">
            Envoyez une demande manuelle de pochettes à votre banque mère.
        </p>

        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label>Groupe sanguin <span class="req">*</span></label>
                    <select name="id_groupe" required>
                        <option value="">— Choisir —</option>
                        <?php foreach ($groupes as $g): ?>
                            <option value="<?php echo $g['id_groupe']; ?>"><?php echo htmlspecialchars($g['libelle']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantité (pochettes) <span class="req">*</span></label>
                    <input type="number" name="quantite_demandee" min="1" max="500" required placeholder="Ex : 10">
                </div>
            </div>

            <div class="form-group">
                <label>Note (optionnelle)</label>
                <textarea name="note" rows="3" placeholder="Précisez l'urgence ou la raison de la demande..."
                          style="width:100%; padding:10px 14px; border:1.5px solid #E5E7EB; border-radius:8px; font-family:inherit; font-size:14px; resize:vertical;"></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="fermerModal()">Annuler</button>
                <button type="submit" name="nouvelle_demande_banque" class="btn-submit">Envoyer la demande</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(id, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    btn.classList.add('active');
}
function ouvrirModal() { document.getElementById('modalDemande').classList.add('active'); }
function fermerModal() { document.getElementById('modalDemande').classList.remove('active'); }
document.getElementById('modalDemande').addEventListener('click', e => { if (e.target === e.currentTarget) fermerModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') fermerModal(); });
</script>

</body>
</html>