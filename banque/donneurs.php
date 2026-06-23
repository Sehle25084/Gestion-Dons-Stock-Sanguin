<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'banque') {
    header("Location: ../index.php");
    exit;
}

// Compatibilité ancienne/nouvelle architecture
$id_banque = $_SESSION['id_banque'] ?? $_SESSION['id'];
$page_active = 'donneurs';
$success = $erreur = "";

// ════════════════════════════════════════════════════════
// CONFIRMER le groupe sanguin d'un donneur
// ════════════════════════════════════════════════════════
if (isset($_POST['confirmer'])) {
    $id_donneur = (int)$_POST['id_donneur'];
    $id_groupe  = (int)$_POST['id_groupe'];

    // ✔ Validation : vérifier que le groupe existe
    $stmtChk = $pdo->prepare("SELECT id_groupe FROM groupe_sanguin WHERE id_groupe = ?");
    $stmtChk->execute([$id_groupe]);
    if (!$stmtChk->fetch()) {
        $erreur = "Groupe sanguin invalide.";
    } elseif ($id_donneur <= 0) {
        $erreur = "Donneur invalide.";
    } else {
        // Confirmer + marquer identité vérifiée (présence physique = identité ok)
        $pdo->prepare("
            UPDATE donneur 
            SET id_groupe = ?,
                groupe_auto_declare = COALESCE(groupe_auto_declare, ?),
                groupe_confirme = 1,
                identite_verifiee = 1
            WHERE id_donneur = ?
        ")->execute([$id_groupe, $id_groupe, $id_donneur]);

        $success = "Groupe sanguin confirmé avec succès !";
    }
}

// ════════════════════════════════════════════════════════
// CHARGEMENT des donneurs
// ════════════════════════════════════════════════════════
$non_verifies = $pdo->query("SELECT * FROM donneur WHERE groupe_confirme = 0 ORDER BY id_donneur DESC")->fetchAll();
$verifies     = $pdo->query("SELECT * FROM donneur WHERE groupe_confirme = 1 ORDER BY id_donneur DESC")->fetchAll();
$groupes      = $pdo->query("SELECT * FROM groupe_sanguin ORDER BY libelle")->fetchAll();

// Map id_groupe → libelle
$groupes_par_id = [];
foreach ($groupes as $g) $groupes_par_id[$g['id_groupe']] = $g['libelle'];

// ════════════════════════════════════════════════════════
// Récupérer les noms depuis le registre national (en 1 seule requête)
// ════════════════════════════════════════════════════════
$tous_donneurs = array_merge($non_verifies, $verifies);
$nnis = array_filter(array_unique(array_column($tous_donneurs, 'NNI')));

$citoyens_par_nni = [];
if (!empty($nnis)) {
    $placeholders = implode(',', array_fill(0, count($nnis), '?'));
    $stmtCit = $pdo_registre->prepare("SELECT NNI, nom, prenom, wilaya FROM citoyen WHERE NNI IN ($placeholders)");
    $stmtCit->execute(array_values($nnis));
    while ($row = $stmtCit->fetch()) {
        $citoyens_par_nni[$row['NNI']] = $row;
    }
}

// Stats
$nb_total      = count($verifies) + count($non_verifies);
$nb_confirmes  = count($verifies);
$nb_a_confirm  = count($non_verifies);

require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donneurs — <?php echo htmlspecialchars($_SESSION['nom_banque'] ?? 'Banque'); ?> | E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* Badges donneur */
        .badge-groupe-circle {
            display: inline-flex; align-items: center; justify-content: center;
            width: 32px; height: 32px; border-radius: 50%;
            font-weight: 800; font-size: 11px;
            background: #8B0000; color: #FFFFFF;
            border: 2px solid #FCA5A5;
        }

        /* Section spéciale "à confirmer" */
        .section-alert {
            border-left: 4px solid #F59E0B;
            background: linear-gradient(to right, #FFFBEB, #FFFFFF 30%);
        }
        .section-alert .section-title { color: #92400E; }
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
        <h1>Gestion des donneurs</h1>
        <p>Validez les groupes sanguins des donneurs présents à votre banque.</p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur);  ?></div><?php endif; ?>

    <div class="alerte-info">
        <span>ℹ️ Le groupe sanguin et l'identité du donneur sont confirmés <strong>physiquement</strong> par votre banque lors de sa visite.</span>
    </div>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">👤</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total donneurs</div>
                <div class="stat-mini-number"><?php echo $nb_total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">✅</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Confirmés</div>
                <div class="stat-mini-number"><?php echo $nb_confirmes; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">⏳</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">À confirmer</div>
                <div class="stat-mini-number <?php echo $nb_a_confirm > 0 ? 'alert' : ''; ?>"><?php echo $nb_a_confirm; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ SECTION : COMPTES EN ATTENTE DE CONFIRMATION ══ -->
    <?php if ($non_verifies): ?>
    <div class="section section-alert">
        <div class="section-header">
            <div class="section-title">⚠️ Donneurs en attente de confirmation du groupe sanguin</div>
            <span class="cnt-badge" style="background:#FEF3C7; color:#92400E;"><?php echo count($non_verifies); ?> à traiter</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>NNI</th>
                        <th>Identité (Registre)</th>
                        <th>Contact</th>
                        <th>Groupe auto-déclaré</th>
                        <th>Confirmer le groupe</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($non_verifies as $d): ?>
                        <?php $citoyen = $citoyens_par_nni[$d['NNI']] ?? null; ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($d['NNI']); ?></strong></td>
                            <td>
                                <?php if ($citoyen): ?>
                                    <strong><?php echo htmlspecialchars($citoyen['prenom'] . ' ' . $citoyen['nom']); ?></strong>
                                    <br><small style="color:#666;">📍 <?php echo htmlspecialchars($citoyen['wilaya']); ?></small>
                                <?php else: ?>
                                    <span style="color:#9CA3AF;">— Inconnu —</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($d['telephone'] ?: '—'); ?></div>
                                <small style="color:#666;"><?php echo htmlspecialchars($d['email'] ?: '—'); ?></small>
                            </td>
                            <td>
                                <?php if (!empty($d['groupe_auto_declare']) && isset($groupes_par_id[$d['groupe_auto_declare']])): ?>
                                    <span class="badge badge-groupe"><?php echo htmlspecialchars($groupes_par_id[$d['groupe_auto_declare']]); ?></span>
                                    <small style="color:#92400E; font-size:11px; display:block; margin-top:2px;">⏳ À vérifier</small>
                                <?php else: ?>
                                    <span style="color:#9CA3AF; font-size:12px;">Non déclaré</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="id_donneur" value="<?php echo $d['id_donneur']; ?>">
                                    <select name="id_groupe" required style="padding:7px 10px; border:1.5px solid #E5E7EB; border-radius:8px; font-family:inherit; font-size:13px;">
                                        <option value="">— Choisir le groupe —</option>
                                        <?php foreach ($groupes as $g): ?>
                                            <option value="<?php echo $g['id_groupe']; ?>"
                                                <?php echo ($d['groupe_auto_declare'] == $g['id_groupe']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($g['libelle']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                            </td>
                            <td>
                                    <button type="submit" name="confirmer" class="btn-accepter"
                                            onclick="return confirm('Confirmer le groupe sanguin de ce donneur ?');">
                                        ✓ Valider
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ SECTION : DONNEURS CONFIRMÉS ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Base des donneurs confirmés</div>
            <span class="cnt-badge"><?php echo count($verifies); ?> donneur(s)</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>NNI</th>
                        <th>Identité</th>
                        <th>Contact</th>
                        <th>Wilaya</th>
                        <th>Groupe</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($verifies): ?>
                        <?php foreach ($verifies as $d): ?>
                            <?php
                                $citoyen = $citoyens_par_nni[$d['NNI']] ?? null;
                                $groupe_libelle = $groupes_par_id[$d['id_groupe']] ?? '—';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($d['NNI']); ?></strong></td>
                                <td>
                                    <?php if ($citoyen): ?>
                                        <?php echo htmlspecialchars($citoyen['prenom'] . ' ' . $citoyen['nom']); ?>
                                    <?php else: ?>
                                        <span style="color:#9CA3AF;">Donneur #<?php echo $d['id_donneur']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size:13px;"><?php echo htmlspecialchars($d['telephone'] ?: '—'); ?></div>
                                    <small style="color:#666;"><?php echo htmlspecialchars($d['email'] ?: '—'); ?></small>
                                </td>
                                <td>
                                    <?php if ($citoyen && $citoyen['wilaya']): ?>
                                        <span class="badge badge-wilaya">📍 <?php echo htmlspecialchars($citoyen['wilaya']); ?></span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <div class="badge-groupe-circle"><?php echo htmlspecialchars($groupe_libelle); ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-acceptee">✓ Confirmé</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="vide">Aucun donneur confirmé pour le moment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>