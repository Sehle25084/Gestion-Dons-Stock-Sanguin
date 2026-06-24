<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sous_banque') {
    header("Location: ../index.php");
    exit;
}

$id_sb   = $_SESSION['id_sous_banque'];
$success = $erreur = "";

// ════════════════════════════════════════════════════════════════
// TRAITEMENT : Marquer un lot comme expiré
// ════════════════════════════════════════════════════════════════
if (isset($_POST['marquer_expire'])) {
    $id_lot = (int)($_POST['id_lot'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM lot_sang_sous_banque WHERE id_lot = ? AND id_sous_banque = ?");
    $stmt->execute([$id_lot, $id_sb]);
    $lot = $stmt->fetch();

    if (!$lot) {
        $erreur = "Lot introuvable.";
    } elseif ($lot['statut'] !== 'disponible') {
        $erreur = "Ce lot a déjà été traité.";
    } else {
        $pdo->beginTransaction();
        try {
            // 1. Marquer le lot comme expiré
            $pdo->prepare("UPDATE lot_sang_sous_banque SET statut = 'expire' WHERE id_lot = ?")
                ->execute([$id_lot]);

            // 2. Retirer la quantité du stock_sous_banque
            $pdo->prepare("
                UPDATE stock_sous_banque
                SET quantite_disponible = GREATEST(0, quantite_disponible - ?),
                    date_mise_a_jour = CURDATE()
                WHERE id_sous_banque = ? AND id_groupe = ?
            ")->execute([$lot['quantite'], $id_sb, $lot['id_groupe']]);

            // 3. Tracer dans l'historique
            $stmtG = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
            $stmtG->execute([$lot['id_groupe']]);
            $libelle_groupe = $stmtG->fetchColumn();

            $pdo->prepare("
                INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action)
                VALUES (?, ?, 'lot_expire', ?, ?, NOW())
            ")->execute([
                $id_sb, $lot['id_groupe'], $lot['quantite'],
                "Lot de {$lot['quantite']} pochette(s) {$libelle_groupe} retiré du stock (expiré)"
            ]);

            $pdo->commit();
            $success = "Lot marqué comme expiré et retiré du stock.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $erreur = "Erreur lors du traitement du lot.";
        }
    }
}

// ── Filtre par statut ──
$filtre_statut = $_GET['statut'] ?? 'disponible';
$where_filtre = "";
$params = [$id_sb];

if (in_array($filtre_statut, ['disponible', 'expire', 'epuise'])) {
    $where_filtre = " AND l.statut = ?";
    $params[] = $filtre_statut;
}

// ── Chargement des lots ──
$stmt = $pdo->prepare("
    SELECT l.*, g.libelle AS groupe,
           DATEDIFF(l.date_expiration, CURDATE()) AS jours_restants
    FROM lot_sang_sous_banque l
    JOIN groupe_sanguin g ON g.id_groupe = l.id_groupe
    WHERE l.id_sous_banque = ?
      $where_filtre
    ORDER BY
        CASE l.statut WHEN 'disponible' THEN 0 WHEN 'epuise' THEN 1 ELSE 2 END,
        l.date_expiration ASC
");
$stmt->execute($params);
$lots = $stmt->fetchAll();

// ── Stats globales ──
$nb_dispo  = $pdo->prepare("SELECT COUNT(*) FROM lot_sang_sous_banque WHERE id_sous_banque = ? AND statut = 'disponible'");
$nb_dispo->execute([$id_sb]);
$nb_dispo = (int)$nb_dispo->fetchColumn();

$nb_epuise = $pdo->prepare("SELECT COUNT(*) FROM lot_sang_sous_banque WHERE id_sous_banque = ? AND statut = 'epuise'");
$nb_epuise->execute([$id_sb]);
$nb_epuise = (int)$nb_epuise->fetchColumn();

$nb_expire = $pdo->prepare("SELECT COUNT(*) FROM lot_sang_sous_banque WHERE id_sous_banque = ? AND statut = 'expire'");
$nb_expire->execute([$id_sb]);
$nb_expire = (int)$nb_expire->fetchColumn();

$nb_a_risque = $pdo->prepare("
    SELECT COUNT(*) FROM lot_sang_sous_banque
    WHERE id_sous_banque = ? AND statut = 'disponible'
      AND date_expiration <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
");
$nb_a_risque->execute([$id_sb]);
$nb_a_risque = (int)$nb_a_risque->fetchColumn();

$page_active = 'lots';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lots — <?php echo htmlspecialchars($_SESSION['nom_sb'] ?? 'Sous-banque'); ?> | E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* Filtres */
        .filtres-statut { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
        .filtre-btn {
            background: #FFFFFF; border: 1.5px solid #E5E7EB;
            color: #6B7280; padding: 8px 16px; border-radius: 10px;
            font-size: 13px; font-weight: 700; text-decoration: none;
            transition: all 0.15s;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .filtre-btn:hover { background: #FEF2F2; color: #8B0000; border-color: #8B0000; }
        .filtre-btn.active { background: #8B0000; color: #FFFFFF; border-color: #8B0000; }
        .filtre-count {
            background: rgba(255,255,255,0.25);
            padding: 1px 8px; border-radius: 999px;
            font-size: 11px; font-weight: 800;
        }
        .filtre-btn:not(.active) .filtre-count { background: #F3F4F6; color: #6B7280; }

        /* Badge urgence */
        .urgence-tag {
            display: inline-flex; align-items: center;
            font-weight: 700; padding: 4px 10px;
            border-radius: 999px; font-size: 11px;
        }
        .urgence-ok        { background:#D1FAE5; color:#065F46; }
        .urgence-attention { background:#FEF3C7; color:#92400E; }
        .urgence-critique  { background:#FEE2E2; color:#B91C1C; }
        .urgence-expire    { background:#6B0000; color:#FFFFFF; }

        .badge-statut-dispo  { background:#D1FAE5; color:#065F46; }
        .badge-statut-epuise { background:#F3F4F6; color:#6B7280; }
        .badge-statut-expire { background:#7C2D12; color:#FFFFFF; }
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
        <h1>Suivi des lots</h1>
        <p>Gestion individuelle des lots de sang reçus et de leurs dates d'expiration.</p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur);  ?></div><?php endif; ?>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">📦</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Lots disponibles</div>
                <div class="stat-mini-number"><?php echo $nb_dispo; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">⏰</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Expirent ≤ 7 jours</div>
                <div class="stat-mini-number <?php echo $nb_a_risque > 0 ? 'alert' : ''; ?>"><?php echo $nb_a_risque; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">✓</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Lots épuisés</div>
                <div class="stat-mini-number"><?php echo $nb_epuise; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🗑️</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Lots expirés</div>
                <div class="stat-mini-number"><?php echo $nb_expire; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ TABLEAU + FILTRES ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Liste des lots</div>
            <span class="cnt-badge"><?php echo count($lots); ?> lot(s) affiché(s)</span>
        </div>

        <div class="filtres-statut">
            <a href="lots.php?statut=disponible" class="filtre-btn <?php echo ($filtre_statut === 'disponible') ? 'active' : ''; ?>">
                📦 Disponibles <span class="filtre-count"><?php echo $nb_dispo; ?></span>
            </a>
            <a href="lots.php?statut=epuise" class="filtre-btn <?php echo ($filtre_statut === 'epuise') ? 'active' : ''; ?>">
                ✓ Épuisés <span class="filtre-count"><?php echo $nb_epuise; ?></span>
            </a>
            <a href="lots.php?statut=expire" class="filtre-btn <?php echo ($filtre_statut === 'expire') ? 'active' : ''; ?>">
                🗑️ Expirés <span class="filtre-count"><?php echo $nb_expire; ?></span>
            </a>
            <a href="lots.php?statut=tous" class="filtre-btn <?php echo ($filtre_statut === 'tous') ? 'active' : ''; ?>">
                📋 Tous
            </a>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Code Lot</th>
                        <th>Groupe</th>
                        <th>Quantité</th>
                        <th>Date réception</th>
                        <th>Date expiration</th>
                        <th>Urgence</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($lots): ?>
                        <?php foreach ($lots as $l):
                            $j = (int)$l['jours_restants'];
                            if      ($l['statut'] !== 'disponible')  { $urg_cls='urgence-ok'; $urg_txt='—'; }
                            elseif  ($j < 0)                          { $urg_cls='urgence-expire';   $urg_txt='⏰ Expiré'; }
                            elseif  ($j <= 3)                         { $urg_cls='urgence-critique'; $urg_txt='🚨 ' . $j . ' jour(s)'; }
                            elseif  ($j <= 7)                         { $urg_cls='urgence-attention'; $urg_txt='⚠️ ' . $j . ' jours'; }
                            else                                       { $urg_cls='urgence-ok';      $urg_txt='✓ ' . $j . ' jours'; }
                        ?>
                        <tr>
                            <td>
                                <strong style="font-family:'Courier New', monospace; font-size:12px;">
                                    <?php echo htmlspecialchars($l['code_lot'] ?? 'LOT-' . $l['id_lot']); ?>
                                </strong>
                            </td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($l['groupe']); ?></span></td>
                            <td><strong><?php echo (int)$l['quantite']; ?></strong> poch.</td>
                            <td><?php echo $l['date_reception'] ? date('d/m/Y', strtotime($l['date_reception'])) : '—'; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($l['date_expiration'])); ?></td>
                            <td><span class="urgence-tag <?php echo $urg_cls; ?>"><?php echo $urg_txt; ?></span></td>
                            <td>
                                <?php if ($l['statut'] === 'disponible'): ?>
                                    <span class="badge badge-statut-dispo">📦 Disponible</span>
                                <?php elseif ($l['statut'] === 'epuise'): ?>
                                    <span class="badge badge-statut-epuise">✓ Épuisé</span>
                                <?php else: ?>
                                    <span class="badge badge-statut-expire">🗑️ Expiré</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($l['statut'] === 'disponible' && $j < 0): ?>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Marquer ce lot comme expiré et le retirer du stock ?');">
                                        <input type="hidden" name="id_lot" value="<?php echo $l['id_lot']; ?>">
                                        <button type="submit" name="marquer_expire" class="btn-del">
                                            🗑️ Retirer du stock
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:#9CA3AF;font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="vide">Aucun lot avec ce statut.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>