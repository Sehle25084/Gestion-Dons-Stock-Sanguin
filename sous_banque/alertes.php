<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sous_banque') {
    header("Location: ../index.php");
    exit;
}

$id_sb                = $_SESSION['id_sous_banque'];
$id_banque_principale = $_SESSION['id_banque_principale'];
$success = $erreur    = "";

// ════════════════════════════════════════════════════════════════
// TRAITEMENT : Marquer UNE alerte comme traitée
// ════════════════════════════════════════════════════════════════
if (isset($_GET['traiter'])) {
    $id_alerte = (int)$_GET['traiter'];
    $stmt = $pdo->prepare("SELECT id_alerte FROM alerte_stock WHERE id_alerte = ? AND id_sous_banque = ?");
    $stmt->execute([$id_alerte, $id_sb]);
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE alerte_stock SET traitee = 1 WHERE id_alerte = ?")
            ->execute([$id_alerte]);

        // Tracer dans l'historique
        $pdo->prepare("
            INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action)
            VALUES (?, NULL, 'alerte_traitee', NULL, ?, NOW())
        ")->execute([$id_sb, "Alerte #{$id_alerte} marquée comme traitée"]);

        $success = "Alerte marquée comme traitée.";
    } else {
        $erreur = "Alerte introuvable.";
    }
}

// ════════════════════════════════════════════════════════════════
// TRAITEMENT : Tout marquer comme traité
// ════════════════════════════════════════════════════════════════
if (isset($_POST['tout_traiter'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM alerte_stock WHERE id_sous_banque = ? AND traitee = 0");
    $stmt->execute([$id_sb]);
    $nb_avant = (int)$stmt->fetchColumn();

    if ($nb_avant > 0) {
        $pdo->prepare("UPDATE alerte_stock SET traitee = 1 WHERE id_sous_banque = ? AND traitee = 0")
            ->execute([$id_sb]);

        $pdo->prepare("
            INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action)
            VALUES (?, NULL, 'alerte_traitee', NULL, ?, NOW())
        ")->execute([$id_sb, "{$nb_avant} alerte(s) marquées comme traitées en masse"]);

        $success = "{$nb_avant} alerte(s) marquée(s) comme traitée(s).";
    } else {
        $erreur = "Aucune alerte à traiter.";
    }
}

// ════════════════════════════════════════════════════════════════
// TRAITEMENT : Créer une demande de réapprovisionnement
// ════════════════════════════════════════════════════════════════
if (isset($_POST['commander'])) {
    $id_groupe = (int)($_POST['id_groupe_cmd'] ?? 0);
    $quantite  = (int)($_POST['quantite_cmd'] ?? 0);

    if ($id_groupe <= 0 || $quantite <= 0) {
        $erreur = "Données invalides.";
    } elseif ($quantite > 500) {
        $erreur = "Maximum 500 pochettes par demande.";
    } else {
        // Vérifier si une demande externe en attente existe déjà
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM demande
            WHERE id_sous_banque = ? AND id_groupe = ?
              AND type_demande = 'externe' AND statut = 'en_attente'
        ");
        $stmt->execute([$id_sb, $id_groupe]);

        if ((int)$stmt->fetchColumn() > 0) {
            $erreur = "Une demande est déjà en cours pour ce groupe sanguin.";
        } else {
            $id_hopital = $_SESSION['id_hopital'] ?? null;
            $pdo->prepare("
                INSERT INTO demande (id_hopital, id_sous_banque, id_banque, id_groupe, quantite_demandee,
                                     date_demande, statut, type_demande, note)
                VALUES (?, ?, ?, ?, ?, CURDATE(), 'en_attente', 'externe',
                        'Demande manuelle depuis page alertes — stock faible')
            ")->execute([$id_hopital, $id_sb, $id_banque_principale, $id_groupe, $quantite]);

            $stmtG = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
            $stmtG->execute([$id_groupe]);
            $libelle_groupe = $stmtG->fetchColumn();

            $pdo->prepare("
                INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action)
                VALUES (?, ?, 'demande_envoyee', ?, ?, NOW())
            ")->execute([
                $id_sb, $id_groupe, $quantite,
                "Demande depuis alertes : {$quantite} pochette(s) {$libelle_groupe}"
            ]);

            $success = "Demande de <strong>{$quantite}</strong> pochette(s) {$libelle_groupe} envoyée à la banque principale.";
        }
    }
}

// ── Chargement des alertes ──
$stmt = $pdo->prepare("
    SELECT a.*, g.libelle AS groupe
    FROM alerte_stock a
    JOIN groupe_sanguin g ON g.id_groupe = a.id_groupe
    WHERE a.id_sous_banque = ?
    ORDER BY a.traitee ASC, a.date_alerte DESC
    LIMIT 100
");
$stmt->execute([$id_sb]);
$alertes = $stmt->fetchAll();

// ── Stats ──
$nb_actives    = count(array_filter($alertes, fn($a) => (int)$a['traitee'] === 0));
$nb_traitees   = count(array_filter($alertes, fn($a) => (int)$a['traitee'] === 1));
$nb_rupture    = count(array_filter($alertes, fn($a) => $a['type_alerte'] === 'rupture' && (int)$a['traitee'] === 0));
$nb_critiques  = count(array_filter($alertes, fn($a) => $a['type_alerte'] === 'critique' && (int)$a['traitee'] === 0));

$groupes = $pdo->query("SELECT * FROM groupe_sanguin ORDER BY libelle")->fetchAll();

$page_active = 'alertes';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertes — <?php echo htmlspecialchars($_SESSION['nom_sb'] ?? 'Sous-banque'); ?> | E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* Badges types d'alertes */
        .alerte-type {
            display:inline-flex; align-items:center; gap:5px;
            font-weight:700; padding:3px 10px; border-radius:999px; font-size:11px;
        }
        .type-rupture       { background:#7C2D12; color:#FFFFFF; }
        .type-critique      { background:#FEE2E2; color:#B91C1C; }
        .type-avertissement { background:#FEF3C7; color:#92400E; }

        .ligne-traitee { opacity: 0.55; background: #FAFAFA; }
        .ligne-active  { background: #FFFFFF; }

        .actions-tete {
            display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
        }
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

    <!-- ══ TITRE ══ -->
    <div class="page-header">
        <h1>Alertes de stock</h1>
        <p>Suivi des alertes déclenchées par les seuils d'alerte et actions à prendre.</p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo $success; ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur); ?></div><?php endif; ?>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">🔔</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Alertes actives</div>
                <div class="stat-mini-number <?php echo $nb_actives > 0 ? 'alert' : ''; ?>"><?php echo $nb_actives; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🚨</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Ruptures</div>
                <div class="stat-mini-number <?php echo $nb_rupture > 0 ? 'alert' : ''; ?>"><?php echo $nb_rupture; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">⚠️</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Critiques</div>
                <div class="stat-mini-number <?php echo $nb_critiques > 0 ? 'alert' : ''; ?>"><?php echo $nb_critiques; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">✅</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Traitées</div>
                <div class="stat-mini-number"><?php echo $nb_traitees; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ TABLEAU ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Liste des alertes</div>
            <div class="actions-tete">
                <span class="cnt-badge"><?php echo count($alertes); ?> alerte(s)</span>
                <?php if ($nb_actives > 0): ?>
                    <button onclick="ouvrirModalCmd()" class="btn-submit" style="padding:7px 14px; font-size:12px; width:auto; margin:0;">
                        📤 Commander
                    </button>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Marquer toutes les alertes actives comme traitées ?');">
                        <button type="submit" name="tout_traiter" class="btn-edit" style="padding:7px 14px;">
                            ✓ Tout traiter
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Groupe</th>
                        <th>Quantité actuelle</th>
                        <th>Seuil</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($alertes): ?>
                        <?php foreach ($alertes as $a):
                            $traitee = (int)$a['traitee'] === 1;
                            $type = $a['type_alerte'];
                            if      ($type === 'rupture')       { $type_cls='type-rupture';       $type_icon='🚨 Rupture'; }
                            elseif  ($type === 'critique')      { $type_cls='type-critique';      $type_icon='⚠️ Critique'; }
                            else                                { $type_cls='type-avertissement'; $type_icon='🟡 Avertissement'; }
                        ?>
                        <tr class="<?php echo $traitee ? 'ligne-traitee' : 'ligne-active'; ?>">
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($a['groupe']); ?></span></td>
                            <td><strong style="color:<?php echo ($a['quantite_actuelle'] == 0) ? '#B91C1C' : '#111'; ?>;"><?php echo (int)$a['quantite_actuelle']; ?></strong> poch.</td>
                            <td><?php echo (int)$a['seuil_alerte']; ?> poch.</td>
                            <td><span class="alerte-type <?php echo $type_cls; ?>"><?php echo $type_icon; ?></span></td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($a['date_alerte'])); ?>
                                <br><small style="color:#9CA3AF; font-size:11px;"><?php echo date('H:i', strtotime($a['date_alerte'])); ?></small>
                            </td>
                            <td>
                                <?php if ($traitee): ?>
                                    <span class="badge badge-acceptee">✓ Traitée</span>
                                <?php else: ?>
                                    <span class="badge badge-attente">⏳ Active</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$traitee): ?>
                                    <a href="alertes.php?traiter=<?php echo $a['id_alerte']; ?>"
                                       class="btn-edit"
                                       onclick="return confirm('Marquer cette alerte comme traitée ?');">
                                        ✓ Traiter
                                    </a>
                                <?php else: ?>
                                    <span style="color:#9CA3AF;font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="vide">🎉 Aucune alerte. Votre stock est sain !</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ══ MODAL : Commander à la banque ══ -->
<div class="modal" id="modalCmd">
    <div class="modal-content" style="max-width: 460px;">
        <div class="modal-header">
            <div class="modal-title">Commander à la banque principale</div>
            <button class="modal-close" onclick="fermerModalCmd()">×</button>
        </div>

        <p style="color:#6B7280; font-size:13px; margin-bottom:20px;">
            Envoyez une demande de réapprovisionnement à votre banque mère pour un groupe en alerte.
        </p>

        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label>Groupe sanguin <span class="req">*</span></label>
                    <select name="id_groupe_cmd" required>
                        <option value="">— Choisir —</option>
                        <?php foreach ($groupes as $g): ?>
                            <option value="<?php echo $g['id_groupe']; ?>"><?php echo htmlspecialchars($g['libelle']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantité <span class="req">*</span></label>
                    <input type="number" name="quantite_cmd" min="1" max="500" required placeholder="Ex : 20">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="fermerModalCmd()">Annuler</button>
                <button type="submit" name="commander" class="btn-submit">Envoyer la demande</button>
            </div>
        </form>
    </div>
</div>

<script>
function ouvrirModalCmd() { document.getElementById('modalCmd').classList.add('active'); }
function fermerModalCmd() { document.getElementById('modalCmd').classList.remove('active'); }
document.getElementById('modalCmd').addEventListener('click', e => { if (e.target === e.currentTarget) fermerModalCmd(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') fermerModalCmd(); });
</script>

</body>
</html>