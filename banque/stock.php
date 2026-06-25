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

// Vérifier les pochettes expirées en arrivant sur la page
verifierPochettesExpirees($pdo, $id_banque);

$page_active = 'stock';
$success = $erreur = "";

// ════════════════════════════════════════════════════════
// AJOUTER / METTRE À JOUR du stock
// ════════════════════════════════════════════════════════
if (isset($_POST['ajouter'])) {
    $id_groupe       = (int)$_POST['id_groupe'];
    $quantite        = (float)$_POST['quantite'];
    $date_expiration = $_POST['date_expiration'];
    $seuil           = (int)($_POST['seuil_alerte'] ?? 5);

    if ($id_groupe <= 0) {
        $erreur = "Veuillez choisir un groupe sanguin valide.";
    } elseif ($quantite < 0) {
        $erreur = "La quantité ne peut pas être négative.";
    } elseif (empty($date_expiration) || strtotime($date_expiration) < strtotime(date('Y-m-d'))) {
        $erreur = "La date d'expiration ne peut pas être dans le passé.";
    } else {
        // Vérifier que le groupe existe
        $stmtChk = $pdo->prepare("SELECT id_groupe FROM groupe_sanguin WHERE id_groupe = ?");
        $stmtChk->execute([$id_groupe]);
        if (!$stmtChk->fetch()) {
            $erreur = "Groupe sanguin invalide.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM stock WHERE id_banque = ? AND id_groupe = ?");
            $stmt->execute([$id_banque, $id_groupe]);
            $existe = $stmt->fetch();

            if ($existe) {
                $pdo->prepare("
                    UPDATE stock 
                    SET quantite_disponible = ?, seuil_alerte = ?, date_mise_a_jour = CURDATE(), date_expiration = ?
                    WHERE id_banque = ? AND id_groupe = ?
                ")->execute([$quantite, $seuil, $date_expiration, $id_banque, $id_groupe]);
                $success = "Stock mis à jour avec succès !";
            } else {
                $pdo->prepare("
                    INSERT INTO stock (id_banque, id_groupe, quantite_disponible, seuil_alerte, date_mise_a_jour, date_expiration)
                    VALUES (?, ?, ?, ?, CURDATE(), ?)
                ")->execute([$id_banque, $id_groupe, $quantite, $seuil, $date_expiration]);
                $success = "Stock ajouté avec succès !";
            }

            enregistrerActivite($pdo, 'banque', $id_banque,
                'Mise à jour stock — groupe #' . $id_groupe . ' : ' . $quantite . ' pochettes');
        }
    }
}

// ════════════════════════════════════════════════════════
// SUPPRESSION DIRECTE DÉSACTIVÉE
// ════════════════════════════════════════════════════════
// Raison : la banque ne peut PAS supprimer du stock arbitrairement.
// Le stock évolue UNIQUEMENT via :
//   - Acceptation de don     → augmentation
//   - Acceptation demande SB → diminution FIFO
//   - Expiration automatique → passage en déchets (table poches_dechets)
// Si une pochette est défectueuse/contaminée, elle doit être tracée
// dans `poches_dechets` avec une raison documentée, pas supprimée.

// ════════════════════════════════════════════════════════
// CHARGEMENT du stock
// ════════════════════════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT s.*, g.libelle AS groupe 
    FROM stock s 
    JOIN groupe_sanguin g ON g.id_groupe = s.id_groupe 
    WHERE s.id_banque = ? 
    ORDER BY g.libelle
");
$stmt->execute([$id_banque]);
$stocks = $stmt->fetchAll();

$groupes = $pdo->query("SELECT * FROM groupe_sanguin ORDER BY libelle")->fetchAll();

// ════════════════════════════════════════════════════════
// STATISTIQUES
// ════════════════════════════════════════════════════════
$total = 0;
$nb_vide = $nb_faible = $nb_ok = $nb_expires = 0;

foreach ($stocks as $s) {
    $q = (int)$s['quantite_disponible'];
    $seuil = (int)($s['seuil_alerte'] ?? 5);
    $expire = strtotime($s['date_expiration']) < time();

    $total += $q;
    if ($q == 0) $nb_vide++;
    elseif ($q <= $seuil) $nb_faible++;
    else $nb_ok++;
    if ($expire) $nb_expires++;
}

require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock — <?php echo htmlspecialchars($_SESSION['nom_banque'] ?? 'Banque'); ?> | E-Sang</title>
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
            display:inline-flex; align-items:center; gap:5px;
            font-weight:700; padding:4px 10px; border-radius:999px; font-size:11px;
        }
        .etat-ok       { background:#D1FAE5; color:#065F46; }
        .etat-faible   { background:#FEF3C7; color:#92400E; }
        .etat-critique { background:#FEE2E2; color:#B91C1C; }
        .etat-vide     { background:#6B0000; color:#FFFFFF; }
        .etat-expire   { background:#7C2D12; color:#FFFFFF; }
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
            <h1>Gestion du stock</h1>
            <p>Inventaire et niveaux de réserve par groupe sanguin.</p>
        </div>
        
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur);  ?></div><?php endif; ?>

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

    <!-- ══ TABLEAU DU STOCK ══ -->
    <div class="section">

        <div class="section-header">
            <div class="section-title">Inventaire par groupe sanguin</div>
            <span class="cnt-badge"><?php echo count($stocks); ?> groupe(s) en stock</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Groupe</th>
                        <th>Quantité</th>
                        <th>Niveau</th>
                        <th>Seuil d'alerte</th>
                        <th>Dernière MàJ</th>
                        <th>Expiration</th>
                        <th>État</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($stocks): ?>
                        <?php foreach ($stocks as $s):
                            $q     = (int)$s['quantite_disponible'];
                            $seuil = (int)($s['seuil_alerte'] ?? 5);
                            $expire = strtotime($s['date_expiration']) < time();
                            $pct = $seuil > 0 ? min(100, round(($q / max($seuil * 2, 1)) * 100)) : ($q > 0 ? 100 : 0);

                            if ($expire)                    { $etat_cls='etat-expire';    $etat_txt='⏰ Expiré';    $fill='fill-vide'; }
                            elseif ($q === 0)               { $etat_cls='etat-vide';      $etat_txt='🔴 Rupture';   $fill='fill-vide'; }
                            elseif ($q <= ceil($seuil / 2)) { $etat_cls='etat-critique';  $etat_txt='🟠 Critique';  $fill='fill-critique'; }
                            elseif ($q <= $seuil)           { $etat_cls='etat-faible';    $etat_txt='🟡 Faible';    $fill='fill-faible'; }
                            else                            { $etat_cls='etat-ok';        $etat_txt='🟢 OK';        $fill='fill-ok'; }
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
                            <td><?php echo date('d/m/Y', strtotime($s['date_mise_a_jour'])); ?></td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($s['date_expiration'])); ?>
                                <?php if ($expire): ?>
                                    <br><small style="color:#B91C1C; font-weight:700;">⚠️ Expiré</small>
                                <?php endif; ?>
                            </td>
                            <td><span class="etat-tag <?php echo $etat_cls; ?>"><?php echo $etat_txt; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="vide">Aucun stock enregistré. Cliquez sur "Ajouter du stock" pour commencer.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ══ MODAL : Ajouter / Modifier le stock ══ -->
<div class="modal" id="modalStock">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Ajouter / Modifier du stock</div>
            <button class="modal-close" onclick="fermerModalStock()">×</button>
        </div>
        <p style="color:#6B7280; font-size:13px; margin-bottom:20px;">
            Si une ligne existe déjà pour ce groupe sanguin, elle sera mise à jour.
            Sinon, une nouvelle entrée sera créée.
        </p>

        <form method="POST" action="stock.php">
            <div class="form-group">
                <label for="id_groupe">Groupe sanguin <span class="req">*</span></label>
                <select name="id_groupe" id="id_groupe" required>
                    <option value="">— Choisir —</option>
                    <?php foreach ($groupes as $g): ?>
                        <option value="<?php echo $g['id_groupe']; ?>"><?php echo htmlspecialchars($g['libelle']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="quantite">Quantité (pochettes) <span class="req">*</span></label>
                    <input type="number" name="quantite" id="quantite" min="0" step="1" required placeholder="Ex : 10">
                </div>

                <div class="form-group">
                    <label for="seuil_alerte">Seuil d'alerte</label>
                    <input type="number" name="seuil_alerte" id="seuil_alerte" min="0" step="1" value="5">
                    <small style="color:#9CA3AF; font-size:12px;">Alerte si stock ≤ seuil</small>
                </div>
            </div>

            <div class="form-group">
                <label for="date_expiration">Date d'expiration <span class="req">*</span></label>
                <input type="date" name="date_expiration" id="date_expiration" required
                       min="<?php echo date('Y-m-d'); ?>"
                       value="<?php echo date('Y-m-d', strtotime('+42 days')); ?>">
                <small style="color:#9CA3AF; font-size:12px;">Par défaut : +42 jours (durée de conservation standard)</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="fermerModalStock()">Annuler</button>
                <button type="submit" name="ajouter" class="btn-submit">Enregistrer</button>
            </div>
        </form>
    </div>
</div>



</body>
</html>