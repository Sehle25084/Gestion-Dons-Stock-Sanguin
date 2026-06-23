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
// SUPPRESSION DÉSACTIVÉE — L'admin ne peut PAS supprimer un donneur
// ════════════════════════════════════════════════════════
// Raison : préserver la traçabilité médicale (historique des dons).
// Un donneur peut être bloqué/désactivé, mais jamais supprimé.

// ════════════════════════════════════════════════════════
// CHARGEMENT DES DONNEURS
// ════════════════════════════════════════════════════════
$donneurs = $pdo->query("SELECT * FROM donneur ORDER BY id_donneur DESC")->fetchAll();
$groupes = $pdo->query("SELECT * FROM groupe_sanguin ORDER BY libelle")->fetchAll();

$groupes_par_id = [];
foreach ($groupes as $g) {
    $groupes_par_id[$g['id_groupe']] = $g['libelle'];
}

// ════════════════════════════════════════════════════════
// RÉCUPÉRATION DES INFOS CITOYEN (registre national)
// ════════════════════════════════════════════════════════
$nb_verifies = 0;
$nb_identite_ok = 0;
$nb_disponibles = 0;

foreach ($donneurs as &$d) {
    $stmt = $pdo_registre->prepare("SELECT nom, prenom, wilaya FROM citoyen WHERE NNI = ?");
    $stmt->execute([$d['NNI']]);
    $citoyen = $stmt->fetch();

    $d['nom']        = $citoyen['nom']    ?? '—';
    $d['prenom']     = $citoyen['prenom'] ?? '—';
    $d['wilaya']     = $citoyen['wilaya'] ?? '—';

    if ((int)$d['groupe_confirme'] === 1) $nb_verifies++;
    if (!empty($d['identite_verifiee']) && (int)$d['identite_verifiee'] === 1) $nb_identite_ok++;
    if (!empty($d['disponible'])        && (int)$d['disponible']        === 1) $nb_disponibles++;
}
unset($d);

$nb_total      = count($donneurs);
$nb_en_attente = $nb_total - $nb_verifies;

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

        /* Badges de groupe sanguin */
        .badge-groupe {
            display: inline-flex; align-items: center; justify-content: center;
            width: 32px; height: 32px; border-radius: 50%;
            font-weight: 800; font-size: 11px; color: #FFFFFF;
        }
        .bg-red { background: #8B0000; border: 2px solid #FCA5A5; }
        .bg-gray { background: #6B7280; border: 2px solid #D1D5DB; }

        /* Badges de statut */
        .badge-status {
            padding: 4px 10px; border-radius: 999px;
            font-size: 11px; font-weight: 700; white-space: nowrap; display: inline-block;
        }
        .status-ok { background: #F0FDF4; color: #166534; border: 1.5px solid #BBF7D0; }
        .status-wait { background: #FFF7ED; color: #C2410C; border: 1.5px solid #FED7AA; }
        .status-disp { background: #ECFDF5; color: #047857; border: 1.5px solid #A7F3D0; }
        .status-indisp { background: #F3F4F6; color: #6B7280; border: 1.5px solid #E5E7EB; }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar"><?php echo $admin_initials; ?></div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bienvenue, <?php echo $admin_display; ?></div>
                <div class="top-bar-role">Espace administrateur</div>
            </div>
        </div>
    </div>

    <div class="page-header">
        <h1>Gestion des donneurs</h1>
        <p>Liste de tous les donneurs inscrits sur la plateforme.</p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur);  ?></div><?php endif; ?>

    <div class="alerte-info">
        <span>ℹ️ Le groupe sanguin du donneur est confirmé <strong>uniquement par la banque de sang</strong> lors de sa présentation physique pour effectuer un don.</span>
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

    <!-- ══ TABLEAU UNIQUE DES DONNEURS ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Liste globale des donneurs</div>
            <span class="cnt-badge"><?php echo $nb_total; ?> donneur(s)</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Identité / NNI</th>
                        <th>Groupe Sanguin</th>
                        <th>Contact</th>
                        <th>Wilaya</th>
                        <th>Identité</th>
                        <th>Disponibilité</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($donneurs): ?>
                        <?php foreach ($donneurs as $d): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($d['prenom'] . ' ' . $d['nom']); ?></strong>
                                <br><small style="color:#666;font-weight:500;">NNI: <?php echo htmlspecialchars($d['NNI']); ?></small>
                            </td>
                            <td>
                                <?php
                                // ✔ CORRECTION : suppression du label `if_confirme:` (goto inutile)
                                $is_confirme = ((int)$d['groupe_confirme'] === 1);
                                if ($is_confirme && $d['id_groupe']) {
                                    $lib = $groupes_par_id[$d['id_groupe']] ?? '?';
                                    echo '<div style="display:flex; align-items:center; gap:8px;">';
                                    echo '<div class="badge-groupe bg-red">'.$lib.'</div>';
                                    echo '<span style="color:#166534; font-weight:700; font-size:12px;">✅ Confirmé</span>';
                                    echo '</div>';
                                } else {
                                    $g_auto = $d['groupe_auto_declare'] ?: 'inconnu';
                                    $lib = ($g_auto === 'inconnu') ? '?' : ($groupes_par_id[$g_auto] ?? '?');
                                    echo '<div style="display:flex; align-items:center; gap:8px;">';
                                    echo '<div class="badge-groupe '.($lib === '?' ? 'bg-gray' : 'bg-red').'">'.$lib.'</div>';
                                    echo '<span style="color:#B45309; font-weight:600; font-size:12px;">⏳ Auto-déclaré</span>';
                                    echo '</div>';
                                }
                                ?>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($d['telephone'] ?: '—'); ?></div>
                                <small style="color:#666;"><?php echo htmlspecialchars($d['email'] ?: '—'); ?></small>
                            </td>
                            <td>
                                <?php if ($d['wilaya'] && $d['wilaya'] !== '—'): ?>
                                    <span class="badge badge-wilaya">📍 <?php echo htmlspecialchars($d['wilaya']); ?></span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($d['identite_verifiee']) && (int)$d['identite_verifiee'] === 1): ?>
                                    <span class="badge-status status-ok">✓ Vérifiée</span>
                                <?php else: ?>
                                    <span class="badge-status status-wait">⏳ Non vérifiée</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($d['disponible']) && (int)$d['disponible'] === 1): ?>
                                    <span class="badge-status status-disp">🟢 Disponible</span>
                                <?php else: ?>
                                    <span class="badge-status status-indisp">⚪ Indisponible</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="vide">Aucun donneur enregistré.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>