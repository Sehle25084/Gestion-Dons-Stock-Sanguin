<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sous_banque') {
    header("Location: ../index.php");
    exit;
}

$id_sb               = $_SESSION['id_sous_banque'];

// Synchroniser les demandes externes acceptées par la banque (création de lot + mise à jour stock)
require_once '_sync_demandes.php';
$id_utilisateur_session = $_SESSION['id_utilisateur'] ?? null;
$id_banque_principale = $_SESSION['id_banque_principale'];
$success = $erreur   = "";

// ════════════════════════════════════════════════════════════════
// Modifier seuil d'alerte
// ════════════════════════════════════════════════════════════════
if (isset($_POST['modifier_seuil'])) {
    $id_groupe     = (int)($_POST['id_groupe_seuil'] ?? 0);
    $nouveau_seuil = (int)($_POST['nouveau_seuil']   ?? 0);

    if ($id_groupe <= 0 || $nouveau_seuil < 0) {
        $erreur = "Données invalides.";
    } elseif ($nouveau_seuil > 100) {
        $erreur = "Le seuil ne peut pas dépasser 100.";
    } else {
        // Vérifier que le groupe existe
        $stmtG = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
        $stmtG->execute([$id_groupe]);
        $libelle_groupe = $stmtG->fetchColumn();

        if (!$libelle_groupe) {
            $erreur = "Groupe sanguin invalide.";
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_sous_banque WHERE id_sous_banque = ? AND id_groupe = ?");
            $stmt->execute([$id_sb, $id_groupe]);

            if ((int)$stmt->fetchColumn() > 0) {
                $pdo->prepare("UPDATE stock_sous_banque SET seuil_alerte = ? WHERE id_sous_banque = ? AND id_groupe = ?")
                    ->execute([$nouveau_seuil, $id_sb, $id_groupe]);
            } else {
                $pdo->prepare("INSERT INTO stock_sous_banque (id_sous_banque, id_groupe, quantite_disponible, seuil_alerte, date_mise_a_jour) VALUES (?, ?, 0, ?, CURDATE())")
                    ->execute([$id_sb, $id_groupe, $nouveau_seuil]);
            }

            // Tracer dans l'historique
            $pdo->prepare("
                INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, id_utilisateur, date_action)
                VALUES (?, ?, 'seuil_modifie', NULL, ?, ?, NOW())
            ")->execute([
                $id_sb, $id_groupe,
                "Seuil d'alerte du groupe {$libelle_groupe} fixé à {$nouveau_seuil} pochette(s)",
                $id_utilisateur_session
            ]);

            // Vérifier si le stock actuel est maintenant sous le nouveau seuil → créer alerte
            $stmtQ = $pdo->prepare("SELECT quantite_disponible FROM stock_sous_banque WHERE id_sous_banque = ? AND id_groupe = ?");
            $stmtQ->execute([$id_sb, $id_groupe]);
            $qte_actuelle = (int)($stmtQ->fetchColumn() ?? 0);

            if ($qte_actuelle <= $nouveau_seuil) {
                // Vérifier qu'aucune alerte active n'existe déjà pour ce groupe
                $stmtA = $pdo->prepare("SELECT COUNT(*) FROM alerte_stock WHERE id_sous_banque = ? AND id_groupe = ? AND traitee = 0");
                $stmtA->execute([$id_sb, $id_groupe]);
                if ((int)$stmtA->fetchColumn() === 0) {
                    $type_alerte = $qte_actuelle === 0 ? 'rupture' : ($qte_actuelle <= ceil($nouveau_seuil / 2) ? 'critique' : 'avertissement');
                    $pdo->prepare("
                        INSERT INTO alerte_stock (id_sous_banque, id_groupe, quantite_actuelle, seuil_alerte, type_alerte, date_alerte, traitee)
                        VALUES (?, ?, ?, ?, ?, NOW(), 0)
                    ")->execute([$id_sb, $id_groupe, $qte_actuelle, $nouveau_seuil, $type_alerte]);
                }
            }

            $success = "Seuil d'alerte du groupe <strong>{$libelle_groupe}</strong> mis à jour à <strong>{$nouveau_seuil}</strong> pochette(s).";
        }
    }
}

// ── Stock complet (tous les groupes) ──
$stmt = $pdo->prepare("
    SELECT
        g.id_groupe,
        g.libelle AS groupe,
        COALESCE(s.quantite_disponible, 0) AS quantite,
        COALESCE(s.seuil_alerte, 5)        AS seuil,
        s.date_mise_a_jour
    FROM groupe_sanguin g
    LEFT JOIN stock_sous_banque s ON s.id_groupe = g.id_groupe AND s.id_sous_banque = ?
    ORDER BY g.libelle
");
$stmt->execute([$id_sb]);
$stocks = $stmt->fetchAll();

// ── Stats ──
$total     = array_sum(array_column($stocks, 'quantite'));
$nb_vide   = count(array_filter($stocks, fn($s) => (int)$s['quantite'] === 0));
$nb_faible = count(array_filter($stocks, fn($s) => (int)$s['quantite'] > 0 && (int)$s['quantite'] <= (int)$s['seuil']));
$nb_ok     = count(array_filter($stocks, fn($s) => (int)$s['quantite'] > (int)$s['seuil']));

$page_active = 'stock';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock interne — <?php echo htmlspecialchars($_SESSION['nom_sb'] ?? 'Sous-banque'); ?> | E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* Barres de niveau */
        .niveau-bar  { width: 100%; height: 8px; background: #F3F4F6; border-radius: 99px; overflow: hidden; margin-top: 4px; }
        .niveau-fill { height: 100%; border-radius: 99px; transition: width 0.3s; }
        .fill-ok       { background: linear-gradient(90deg, #22C55E, #16A34A); }
        .fill-faible   { background: linear-gradient(90deg, #F59E0B, #D97706); }
        .fill-critique { background: linear-gradient(90deg, #EF4444, #DC2626); }
        .fill-vide     { background: #6B0000; }

        /* Tags d'état */
        .etat-tag {
            display: inline-flex; align-items: center; gap: 5px;
            font-weight: 700; padding: 4px 10px; border-radius: 999px; font-size: 11px;
        }
        .etat-ok       { background: #D1FAE5; color: #065F46; }
        .etat-faible   { background: #FEF3C7; color: #92400E; }
        .etat-critique { background: #FEE2E2; color: #B91C1C; }
        .etat-vide     { background: #6B0000; color: #FFFFFF; }
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
        <h1>Stock interne</h1>
        <p>Niveaux de réserve par groupe sanguin et gestion des seuils d'alerte.</p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo $success; ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur); ?></div><?php endif; ?>

    <div class="alerte-info">
        <span>ℹ️ Le stock est alimenté <strong>uniquement</strong> par les acceptations de votre banque mère. Vous ne pouvez pas ajouter de stock manuellement, seulement modifier les seuils d'alerte.</span>
    </div>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🩸</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total pochettes</div>
                <div class="stat-mini-number"><?php echo $total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">🟢</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Groupes OK</div>
                <div class="stat-mini-number"><?php echo $nb_ok; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">🟡</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Sous le seuil</div>
                <div class="stat-mini-number <?php echo $nb_faible > 0 ? 'alert' : ''; ?>"><?php echo $nb_faible; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🔴</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">En rupture</div>
                <div class="stat-mini-number <?php echo $nb_vide > 0 ? 'alert' : ''; ?>"><?php echo $nb_vide; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ TABLEAU ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Inventaire par groupe sanguin</div>
            <span class="cnt-badge"><?php echo count($stocks); ?> groupes</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Groupe</th>
                        <th>Pochettes</th>
                        <th>Niveau</th>
                        <th>Seuil d'alerte</th>
                        <th>Dernière MàJ</th>
                        <th>État</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stocks as $s):
                        $q     = (int)$s['quantite'];
                        $seuil = (int)$s['seuil'];
                        $pct   = $seuil > 0 ? min(100, round(($q / max($seuil * 2, 1)) * 100)) : ($q > 0 ? 100 : 0);

                        if ($q === 0)                    { $etat_cls='etat-vide';     $etat_txt='🔴 Rupture';  $fill='fill-vide'; }
                        elseif ($q <= ceil($seuil / 2)) { $etat_cls='etat-critique'; $etat_txt='🟠 Critique'; $fill='fill-critique'; }
                        elseif ($q <= $seuil)           { $etat_cls='etat-faible';   $etat_txt='🟡 Faible';   $fill='fill-faible'; }
                        else                            { $etat_cls='etat-ok';       $etat_txt='🟢 OK';       $fill='fill-ok'; }
                    ?>
                    <tr>
                        <td><span class="badge badge-groupe"><?php echo htmlspecialchars($s['groupe']); ?></span></td>
                        <td><strong style="font-size:17px;"><?php echo $q; ?></strong> pochette<?php echo $q > 1 ? 's' : ''; ?></td>
                        <td style="min-width:130px;">
                            <div class="niveau-bar">
                                <div class="niveau-fill <?php echo $fill; ?>" style="width:<?php echo $pct; ?>%"></div>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:10px; color:#9CA3AF; margin-top:3px;">
                                <span>0</span><span><?php echo $seuil * 2; ?></span>
                            </div>
                        </td>
                        <td><strong><?php echo $seuil; ?></strong> poch.</td>
                        <td>
                            <?php echo $s['date_mise_a_jour']
                                ? date('d/m/Y', strtotime($s['date_mise_a_jour']))
                                : '<span style="color:#9CA3AF;">—</span>';
                            ?>
                        </td>
                        <td><span class="etat-tag <?php echo $etat_cls; ?>"><?php echo $etat_txt; ?></span></td>
                        <td>
                            <button class="btn-edit"
                                    onclick="ouvrirModalSeuil(<?php echo $s['id_groupe']; ?>, '<?php echo htmlspecialchars($s['groupe'], ENT_QUOTES); ?>', <?php echo $seuil; ?>)">
                                ✏️ Modifier seuil
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ══ MODAL : Modifier le seuil ══ -->
<div class="modal" id="modalSeuil">
    <div class="modal-content" style="max-width: 460px;">
        <div class="modal-header">
            <div class="modal-title">Modifier le seuil d'alerte</div>
            <button class="modal-close" onclick="fermerModal()">×</button>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="id_groupe_seuil" id="modal_id_groupe"/>

            <div class="alerte-info" style="margin-bottom: 18px; background:#FFFBEB; border-color:#FCD34D; color:#92400E;">
                <span>⚠️ Seuil actuel du groupe <strong id="modal_groupe_nom"></strong> : <strong id="modal_seuil_actuel"></strong> pochette(s).<br>
                <small>En dessous de ce seuil, une demande automatique sera envoyée à la banque principale.</small></span>
            </div>

            <div class="form-group">
                <label>Nouveau seuil (pochettes) <span class="req">*</span></label>
                <input type="number" name="nouveau_seuil" id="modal_nouveau_seuil"
                       min="0" max="100" required
                       style="font-size:20px; font-weight:700; text-align:center;"/>
                <small style="color:#9CA3AF; font-size:12px;">Recommandé : entre 5 et 10 pochettes</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="fermerModal()">Annuler</button>
                <button type="submit" name="modifier_seuil" class="btn-submit">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function ouvrirModalSeuil(id, nom, seuil) {
    document.getElementById('modal_id_groupe').value           = id;
    document.getElementById('modal_groupe_nom').textContent    = nom;
    document.getElementById('modal_seuil_actuel').textContent  = seuil;
    document.getElementById('modal_nouveau_seuil').value       = seuil;
    document.getElementById('modalSeuil').classList.add('active');
}
function fermerModal() {
    document.getElementById('modalSeuil').classList.remove('active');
}
document.getElementById('modalSeuil').addEventListener('click', e => { if (e.target === e.currentTarget) fermerModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') fermerModal(); });
</script>

</body>
</html>