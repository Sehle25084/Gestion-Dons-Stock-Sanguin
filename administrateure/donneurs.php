<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// ════════════════════════════════════════════════════════
// Fonction utilitaire — Tracer les actions admin
// ════════════════════════════════════════════════════════
function log_action($pdo, $id_admin, $action) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO log_activite (role_utilisateur, id_utilisateur, action, date_action)
            VALUES ('admin', ?, ?, NOW())
        ");
        $stmt->execute([$id_admin, $action]);
    } catch (Exception $e) {}
}

$erreur = $success = "";
$id_admin_courant = $_SESSION['id'];

// ════════════════════════════════════════════════════════
// SUPPRIMER un donneur (avec vérification dépendances)
// ════════════════════════════════════════════════════════
if (isset($_GET['supprimer'])) {
    $id = $_GET['supprimer'];

    // Récupérer le NNI pour le log
    $stmt = $pdo->prepare("SELECT NNI FROM donneur WHERE id_donneur = ?");
    $stmt->execute([$id]);
    $nni = $stmt->fetchColumn();

    // Vérifier dons liés
    $check = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_donneur = ?");
    $check->execute([$id]);
    $nb_dons = $check->fetchColumn();

    if ($nb_dons > 0) {
        $erreur = "Impossible de supprimer ce donneur : $nb_dons don(s) lié(s) dans l'historique.";
    } else {
        $pdo->prepare("DELETE FROM donneur WHERE id_donneur = ?")->execute([$id]);
        log_action($pdo, $id_admin_courant, "Suppression donneur : NNI $nni");
        $success = "Donneur supprimé avec succès !";
    }
}

// ════════════════════════════════════════════════════════
// CHARGEMENT DES DONNEURS
// ════════════════════════════════════════════════════════
// On récupère TOUS les donneurs en une seule requête, puis on les classe
$donneurs = $pdo->query("SELECT * FROM donneur ORDER BY id_donneur DESC")->fetchAll();
$groupes = $pdo->query("SELECT * FROM groupe_sanguin ORDER BY libelle")->fetchAll();

// Index des groupes par id pour lookup rapide
$groupes_par_id = [];
foreach ($groupes as $g) {
    $groupes_par_id[$g['id_groupe']] = $g['libelle'];
}

// ════════════════════════════════════════════════════════
// RÉCUPÉRATION DES INFOS CITOYEN (registre national)
// + Classement par groupe sanguin auto-déclaré
// ════════════════════════════════════════════════════════
$en_attente   = []; // groupe non confirmé → classé par groupe_auto_declare
$verifies     = []; // groupe confirmé (validation banque)

foreach ($donneurs as $d) {
    // Récupérer nom/prénom depuis le registre national
    $stmt = $pdo_registre->prepare("SELECT nom, prenom, wilaya FROM citoyen WHERE NNI = ?");
    $stmt->execute([$d['NNI']]);
    $citoyen = $stmt->fetch();

    $d['nom']        = $citoyen['nom']    ?? '—';
    $d['prenom']     = $citoyen['prenom'] ?? '—';
    $d['wilaya']     = $citoyen['wilaya'] ?? '—';

    if ((int)$d['groupe_confirme'] === 1) {
        $verifies[] = $d;
    } else {
        // Groupé par groupe_auto_declare (peut être NULL)
        $key = $d['groupe_auto_declare'] ?: 'inconnu';
        if (!isset($en_attente[$key])) $en_attente[$key] = [];
        $en_attente[$key][] = $d;
    }
}

// ════════════════════════════════════════════════════════
// STATISTIQUES
// ════════════════════════════════════════════════════════
$nb_total            = count($donneurs);
$nb_verifies         = count($verifies);
$nb_en_attente       = $nb_total - $nb_verifies;
$nb_identite_ok      = 0;
$nb_disponibles      = 0;

foreach ($donneurs as $d) {
    if (!empty($d['identite_verifiee']) && (int)$d['identite_verifiee'] === 1) $nb_identite_ok++;
    if (!empty($d['disponible'])        && (int)$d['disponible']        === 1) $nb_disponibles++;
}

$page_active = 'donneurs';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donneurs — Admin E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* ─────────────────────────────────────
           Section par groupe sanguin (compte-rendu)
           ───────────────────────────────────── */
        .groupe-section {
            background: #FFFFFF;
            border: 2px solid #E5E7EB;
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 16px;
            transition: all 0.2s;
        }
        .groupe-section:hover { border-color: #FCA5A5; }

        .groupe-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 1.5px solid #F3F4F6;
        }
        .groupe-titre {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 16px;
            font-weight: 800;
            color: #111111;
        }
        .groupe-circle {
            width: 44px; height: 44px; border-radius: 50%;
            background: #111111; color: #FFFFFF;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 900;
            border: 2.5px solid #FCA5A5;
            flex-shrink: 0;
            line-height: 1;
            padding: 0;
        }
        .groupe-circle.inconnu {
            background: #6B7280;
            border-color: #D1D5DB;
            font-size: 18px;
        }

        .groupe-count {
            background: #FEF2F2;
            color: #8B0000;
            padding: 5px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
        }

        /* Badge identité */
        .badge-identite-ok {
            background: #F0FDF4;
            color: #166534;
            border: 1.5px solid #BBF7D0;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            white-space: nowrap;
            display: inline-block;
        }
        .badge-identite-non {
            background: #FFF7ED;
            color: #C2410C;
            border: 1.5px solid #FED7AA;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            white-space: nowrap;
            display: inline-block;
        }

        /* Badge disponible */
        .badge-disponible {
            background: #ECFDF5;
            color: #047857;
            border: 1.5px solid #A7F3D0;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            white-space: nowrap;
            display: inline-block;
        }
        .badge-indisponible {
            background: #F3F4F6;
            color: #6B7280;
            border: 1.5px solid #E5E7EB;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            white-space: nowrap;
            display: inline-block;
        }

        /* Groupe auto-déclaré (non confirmé) */
        .badge-auto-declare {
            background: #FFFBEB;
            color: #B45309;
            border: 1.5px solid #FDE68A;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
            display: inline-block;
        }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <!-- ══ BANDEAU ADMIN ══ -->
    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar"><?php echo $admin_initials; ?></div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bienvenue, <?php echo $admin_display; ?></div>
                <div class="top-bar-role">Espace administrateur</div>
            </div>
        </div>
    </div>

    <!-- ══ TITRE ══ -->
    <div class="page-header">
        <h1>Gestion des donneurs</h1>
        <p>Liste de tous les donneurs inscrits sur la plateforme.</p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur);  ?></div><?php endif; ?>

    <div class="alerte-info">
        <span>ℹ️ Le groupe sanguin et l'identité du donneur sont confirmés <strong>uniquement</strong> par la banque ou la sous-banque lors de la présentation physique du donneur.</span>
    </div>

    <!-- ══ STATISTIQUES COMPACTES ══ -->
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
                <div class="stat-mini-label">Groupe confirmé</div>
                <div class="stat-mini-number"><?php echo $nb_verifies; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">⏳</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">En attente</div>
                <div class="stat-mini-number <?php echo $nb_en_attente > 0 ? 'alert' : ''; ?>"><?php echo $nb_en_attente; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🆔</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Identité vérifiée</div>
                <div class="stat-mini-number"><?php echo $nb_identite_ok; ?></div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════ -->
    <!-- SECTION 1 : DONNEURS EN ATTENTE PAR GROUPE     -->
    <!-- (compte-rendu : "classés par groupe sanguin")  -->
    <!-- ══════════════════════════════════════════════ -->
    <?php if ($nb_en_attente > 0): ?>
    <div class="section">
        <div class="section-header">
            <div class="section-title">Donneurs en attente de confirmation (groupés par groupe sanguin)</div>
            <span class="cnt-badge"><?php echo $nb_en_attente; ?> donneur(s)</span>
        </div>

        <?php
        // Trier : groupes connus d'abord, puis 'inconnu' à la fin
        ksort($en_attente);
        if (isset($en_attente['inconnu'])) {
            $inconnu = $en_attente['inconnu'];
            unset($en_attente['inconnu']);
            $en_attente['inconnu'] = $inconnu;
        }
        ?>

        <?php foreach ($en_attente as $id_groupe_auto => $liste): ?>
            <?php
            $is_inconnu = ($id_groupe_auto === 'inconnu');
            // Texte court pour le cercle (max 3 caractères : O-, AB+, etc.)
            $libelle_circle = $is_inconnu ? '?' : ($groupes_par_id[$id_groupe_auto] ?? '?');
            // Texte long pour le titre
            $libelle_titre  = $is_inconnu ? 'Non déclaré' : ($groupes_par_id[$id_groupe_auto] ?? '?');
            ?>
            <div class="groupe-section">

                <div class="groupe-header">
                    <div class="groupe-titre">
                        <div class="groupe-circle <?php echo $is_inconnu ? 'inconnu' : ''; ?>">
                            <?php echo htmlspecialchars($libelle_circle); ?>
                        </div>
                        <span>
                            <?php if ($is_inconnu): ?>
                                <strong>Groupe non déclaré par le donneur</strong>
                            <?php else: ?>
                                Groupe auto-déclaré : <strong><?php echo htmlspecialchars($libelle_titre); ?></strong>
                                <small style="color:#B45309;">(à confirmer par la banque)</small>
                            <?php endif; ?>
                        </span>
                    </div>
                    <span class="groupe-count"><?php echo count($liste); ?> donneur(s)</span>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>NNI / Identité</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Wilaya</th>
                                <th>Identité</th>
                                <th>Disponible</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($liste as $d): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($d['NNI']); ?></strong>
                                    <br><small style="color:#666;font-weight:500;">
                                        <?php echo htmlspecialchars($d['prenom'] . ' ' . $d['nom']); ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($d['email'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars($d['telephone'] ?: '—'); ?></td>
                                <td>
                                    <?php if ($d['wilaya'] && $d['wilaya'] !== '—'): ?>
                                        <span class="badge badge-wilaya">📍 <?php echo htmlspecialchars($d['wilaya']); ?></span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($d['identite_verifiee']) && (int)$d['identite_verifiee'] === 1): ?>
                                        <span class="badge-identite-ok">✓ Vérifiée</span>
                                    <?php else: ?>
                                        <span class="badge-identite-non">⏳ Non vérifiée</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($d['disponible']) && (int)$d['disponible'] === 1): ?>
                                        <span class="badge-disponible">🟢 Disponible</span>
                                    <?php else: ?>
                                        <span class="badge-indisponible">⚪ Indisponible</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <a href="donneurs.php?supprimer=<?php echo $d['id_donneur']; ?>"
                                           class="btn-del"
                                           onclick="return confirm('Supprimer ce donneur ?')">
                                            🗑️ Supprimer
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>

    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════ -->
    <!-- SECTION 2 : DONNEURS AVEC GROUPE CONFIRMÉ      -->
    <!-- ══════════════════════════════════════════════ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Donneurs avec groupe sanguin confirmé</div>
            <span class="cnt-badge"><?php echo $nb_verifies; ?> donneur(s)</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>NNI / Identité</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Groupe</th>
                        <th>Identité</th>
                        <th>Disponible</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($verifies): ?>
                        <?php foreach ($verifies as $d): ?>
                        <?php $groupe_libelle = $groupes_par_id[$d['id_groupe']] ?? '?'; ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($d['NNI']); ?></strong>
                                <br><small style="color:#666;font-weight:500;">
                                    <?php echo htmlspecialchars($d['prenom'] . ' ' . $d['nom']); ?>
                                </small>
                            </td>
                            <td><?php echo htmlspecialchars($d['email'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($d['telephone'] ?: '—'); ?></td>
                            <td>
                                <span class="badge badge-groupe"><?php echo htmlspecialchars($groupe_libelle); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($d['identite_verifiee']) && (int)$d['identite_verifiee'] === 1): ?>
                                    <span class="badge-identite-ok">✓ Vérifiée</span>
                                <?php else: ?>
                                    <span class="badge-identite-non">⏳ Non vérifiée</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($d['disponible']) && (int)$d['disponible'] === 1): ?>
                                    <span class="badge-disponible">🟢 Disponible</span>
                                <?php else: ?>
                                    <span class="badge-indisponible">⚪ Indisponible</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <a href="donneurs.php?supprimer=<?php echo $d['id_donneur']; ?>"
                                       class="btn-del"
                                       onclick="return confirm('Supprimer ce donneur ?')">
                                        🗑️ Supprimer
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="vide">Aucun donneur avec groupe confirmé pour le moment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>