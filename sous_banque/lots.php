<?php
session_start();
require_once '../config/db.php';

$page_active = 'lots';
include 'sidebar.php';

$id_sb   = $_SESSION['id_sous_banque'];
$success = $erreur = "";

// ══ TRAITEMENT : Marquer un lot comme expiré/jeté ══
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

            // 2. Retirer la quantité du total stock_sous_banque
            $pdo->prepare("
                UPDATE stock_sous_banque
                SET quantite_disponible = GREATEST(0, quantite_disponible - ?), date_mise_a_jour = CURDATE()
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
                $id_sb,
                $lot['id_groupe'],
                $lot['quantite'],
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

// ── Filtre par groupe sanguin (optionnel) ──
$filtre_groupe = isset($_GET['groupe']) ? (int)$_GET['groupe'] : 0;

// ── Liste des groupes pour le filtre ──
$groupes_liste = $pdo->query("SELECT id_groupe, libelle FROM groupe_sanguin ORDER BY libelle")->fetchAll();

// ── Récupérer tous les lots disponibles, triés par urgence ──
$sql = "
    SELECT l.*, g.libelle AS groupe,
           DATEDIFF(l.date_expiration, CURDATE()) AS jours_restants
    FROM lot_sang_sous_banque l
    JOIN groupe_sanguin g ON g.id_groupe = l.id_groupe
    WHERE l.id_sous_banque = ? AND l.statut = 'disponible'
";
$params = [$id_sb];
if ($filtre_groupe > 0) {
    $sql .= " AND l.id_groupe = ?";
    $params[] = $filtre_groupe;
}
$sql .= " ORDER BY l.date_expiration ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lots = $stmt->fetchAll();

// ── Compteurs pour les stats ──
$nb_urgent = count(array_filter($lots, fn($l) => (int)$l['jours_restants'] <= 3));
$nb_proche = count(array_filter($lots, fn($l) => (int)$l['jours_restants'] > 3 && (int)$l['jours_restants'] <= 10));
$nb_normal = count(array_filter($lots, fn($l) => (int)$l['jours_restants'] > 10));
$total_poches = array_sum(array_column($lots, 'quantite'));

// ── Lots déjà expirés (historique récent, info seulement) ──
$stmt = $pdo->prepare("
    SELECT l.*, g.libelle AS groupe
    FROM lot_sang_sous_banque l
    JOIN groupe_sanguin g ON g.id_groupe = l.id_groupe
    WHERE l.id_sous_banque = ? AND l.statut = 'expire'
    ORDER BY l.date_expiration DESC
    LIMIT 5
");
$stmt->execute([$id_sb]);
$lots_expires_recents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi des Lots | E-Sang</title>
    <style>
        /* ══ STATS RAPIDES ══ */
        .stats-lots { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        @media (max-width: 900px) { .stats-lots { grid-template-columns: repeat(2, 1fr); } }

        /* ══ FILTRE GROUPE ══ */
        .filtre-bar {
            display: flex; align-items: center; gap: 10px; margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filtre-chip {
            padding: 7px 16px; border-radius: 999px; font-size: 13px; font-weight: 600;
            border: 1.5px solid #E5E7EB; color: #111111; text-decoration: none;
            transition: all 0.15s ease; background: #FFFFFF;
        }
        .filtre-chip:hover { border-color: #6B0000; color: #6B0000; }
        .filtre-chip.active { background: #6B0000; color: #FFFFFF; border-color: #6B0000; }

        /* ══ TABLEAU LOTS ══ */
        .urgence-tag {
            display: inline-flex; align-items: center; gap: 5px; font-weight: 700;
            padding: 5px 12px; border-radius: 999px; font-size: 12px;
        }
        .urgence-critique { background: #6B0000; color: #FFFFFF; }
        .urgence-proche   { background: #FEF3C7; color: #92400E; }
        .urgence-normal   { background: #D1FAE5; color: #065F46; }

        .countdown-bar { width: 100%; height: 7px; background: #F3F4F6; border-radius: 99px; overflow: hidden; margin-top: 4px; }
        .countdown-fill { height: 100%; border-radius: 99px; }
        .fill-critique { background: #6B0000; }
        .fill-proche   { background: #F59E0B; }
        .fill-normal   { background: #22C55E; }

        .btn-expirer {
            background: none; border: 1.5px solid #FCA5A5; color: #6B0000;
            border-radius: 8px; padding: 6px 14px; font-size: 12px; font-weight: 700;
            cursor: pointer; font-family: inherit; transition: all 0.15s ease;
        }
        .btn-expirer:hover { background: #6B0000; color: #FFFFFF; border-color: #6B0000; }

        .origine-tag {
            font-size: 11px; color: #6B7280; background: #F3F4F6;
            padding: 3px 9px; border-radius: 6px; display: inline-block;
        }

        /* ══ SECTION LOTS EXPIRÉS (historique récent) ══ */
        .expire-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 0; border-bottom: 1px solid #F3F4F6; font-size: 13px; color: #6B7280;
        }
        .expire-row:last-child { border-bottom: none; }

        /* ══ MODAL CONFIRMATION ══ */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); backdrop-filter:blur(3px); z-index:999; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal { background:#FFFFFF; border-radius:20px; width:100%; max-width:420px; padding:28px; box-shadow:0 20px 50px rgba(0,0,0,0.15); }
        .modal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; padding-bottom:14px; border-bottom:2px solid #F3F4F6; }
        .modal-title { font-size:17px; font-weight:800; color:#111111; }
        .modal-close { width:32px; height:32px; border-radius:8px; background:#F3F4F6; border:none; cursor:pointer; font-size:16px; color:#6B7280; }
        .modal-close:hover { background:#FEF2F2; color:#6B0000; }
    </style>
</head>
<body>
<div class="main-content">

    <div class="page-header">
        <h1>Suivi des Lots</h1>
        <p>Traçabilité et péremption du sang par lot — Dépôt : <strong><?php echo htmlspecialchars($_SESSION['nom_sb']); ?></strong></p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur); ?></div><?php endif; ?>

    <!-- STATS -->
    <div class="stats-lots">
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-label">Total pochettes (lots actifs)</span>
                <span class="stat-icon ic-red">🩸</span>
            </div>
            <span class="stat-number"><?php echo $total_poches; ?></span>
        </div>
        <div class="stat-card" style="<?php echo $nb_urgent > 0 ? 'border-color:#FCA5A5;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">Critiques (≤ 3j)</span>
                <span class="stat-icon ic-red">🔴</span>
            </div>
            <span class="stat-number" style="<?php echo $nb_urgent > 0 ? 'color:#6B0000;' : ''; ?>"><?php echo $nb_urgent; ?></span>
        </div>
        <div class="stat-card" style="<?php echo $nb_proche > 0 ? 'border-color:#FCD34D;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">Proches (4-10j)</span>
                <span class="stat-icon ic-org">🟡</span>
            </div>
            <span class="stat-number" style="<?php echo $nb_proche > 0 ? 'color:#92400E;' : ''; ?>"><?php echo $nb_proche; ?></span>
        </div>
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-label">Lots sereins (> 10j)</span>
                <span class="stat-icon ic-grn">🟢</span>
            </div>
            <span class="stat-number"><?php echo $nb_normal; ?></span>
        </div>
    </div>

    <!-- FILTRE PAR GROUPE -->
    <div class="filtre-bar">
        <a href="lots.php" class="filtre-chip <?php echo $filtre_groupe === 0 ? 'active' : ''; ?>">Tous les groupes</a>
        <?php foreach ($groupes_liste as $g): ?>
            <a href="lots.php?groupe=<?php echo $g['id_groupe']; ?>"
               class="filtre-chip <?php echo $filtre_groupe === (int)$g['id_groupe'] ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($g['libelle']); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- TABLEAU DES LOTS ACTIFS -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Lots actifs, triés par urgence</div>
            <span class="cnt-badge"><?php echo count($lots); ?> lot<?php echo count($lots) > 1 ? 's' : ''; ?></span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Groupe</th>
                        <th>Quantité</th>
                        <th>Date d'entrée</th>
                        <th>Date d'expiration</th>
                        <th>Compte à rebours</th>
                        <th>Origine</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($lots): ?>
                        <?php foreach ($lots as $l):
                            $j = (int)$l['jours_restants'];

                            if ($j <= 3) {
                                $cls = 'critique';
                                $txt = $j <= 0 ? '⛔ Expiré' : "🔴 $j jour" . ($j > 1 ? 's' : '');
                            } elseif ($j <= 10) {
                                $cls = 'proche';
                                $txt = "🟡 $j jours";
                            } else {
                                $cls = 'normal';
                                $txt = "🟢 $j jours";
                            }

                            // Barre de progression : on suppose une durée de vie totale de 42 jours (référentiel courant pour les globules rouges)
                            $duree_totale = 42;
                            $pct_restant  = max(0, min(100, round(($j / $duree_totale) * 100)));

                            $origine_labels = [
                                'banque_principale' => '🏦 Banque principale',
                                'ajustement_manuel' => '✏️ Ajustement manuel',
                                'autre'              => '— Autre',
                            ];
                        ?>
                        <tr>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($l['groupe']); ?></span></td>
                            <td><strong style="font-size:16px;"><?php echo (int)$l['quantite']; ?></strong> poch.</td>
                            <td><?php echo date('d/m/Y', strtotime($l['date_entree'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($l['date_expiration'])); ?></td>
                            <td style="min-width:140px;">
                                <span class="urgence-tag urgence-<?php echo $cls; ?>"><?php echo $txt; ?></span>
                                <div class="countdown-bar">
                                    <div class="countdown-fill fill-<?php echo $cls; ?>" style="width:<?php echo $pct_restant; ?>%"></div>
                                </div>
                            </td>
                            <td><span class="origine-tag"><?php echo $origine_labels[$l['origine']] ?? $l['origine']; ?></span></td>
                            <td>
                                <button class="btn-expirer" onclick="ouvrirModalExpire(<?php echo $l['id_lot']; ?>, '<?php echo htmlspecialchars($l['groupe'], ENT_QUOTES); ?>', <?php echo (int)$l['quantite']; ?>)">
                                    🗑️ Marquer expiré
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="vide">Aucun lot actif pour ce filtre.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- HISTORIQUE RÉCENT DES LOTS EXPIRÉS -->
    <?php if ($lots_expires_recents): ?>
    <div class="section">
        <div class="section-header">
            <div class="section-title">Derniers lots expirés</div>
        </div>
        <?php foreach ($lots_expires_recents as $le): ?>
        <div class="expire-row">
            <span>
                <span class="badge badge-groupe"><?php echo htmlspecialchars($le['groupe']); ?></span>
                &nbsp;<?php echo (int)$le['quantite']; ?> poch. — entré le <?php echo date('d/m/Y', strtotime($le['date_entree'])); ?>
            </span>
            <span>Expiré le <?php echo date('d/m/Y', strtotime($le['date_expiration'])); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<!-- MODAL CONFIRMATION EXPIRATION -->
<div class="modal-overlay" id="modalExpire" onclick="fermerModalOverlay(event)">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Marquer ce lot comme expiré ?</div>
            <button class="modal-close" onclick="fermerModal()">✕</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="id_lot" id="modal_id_lot"/>
            <div style="background:#FEF2F2; border:1.5px solid #FCA5A5; border-radius:10px; padding:12px 14px; margin-bottom:18px; font-size:13px; color:#6B0000; line-height:1.6;">
                ⚠️ Vous allez retirer <strong id="modal_quantite"></strong> pochette(s) de groupe
                <strong id="modal_groupe"></strong> du stock disponible. Cette action sera enregistrée dans l'historique et ne peut pas être annulée.
            </div>
            <button type="submit" name="marquer_expire" class="btn-submit btn-submit-full" style="background:#6B0000;">
                Confirmer le retrait
            </button>
        </form>
    </div>
</div>

<script>
function ouvrirModalExpire(id, groupe, quantite) {
    document.getElementById('modal_id_lot').value   = id;
    document.getElementById('modal_groupe').textContent   = groupe;
    document.getElementById('modal_quantite').textContent = quantite;
    document.getElementById('modalExpire').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function fermerModal() {
    document.getElementById('modalExpire').classList.remove('open');
    document.body.style.overflow = '';
}
function fermerModalOverlay(e) {
    if (e.target === document.getElementById('modalExpire')) fermerModal();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') fermerModal(); });
</script>
</body>
</html>