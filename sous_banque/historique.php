<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sous_banque') {
    header("Location: ../index.php");
    exit;
}

$id_sb = $_SESSION['id_sous_banque'];

// ── Filtres ──
$filtre_type   = isset($_GET['type'])   ? trim($_GET['type'])   : '';
$filtre_groupe = isset($_GET['groupe']) ? (int)$_GET['groupe']  : 0;
$filtre_debut  = isset($_GET['debut'])  ? trim($_GET['debut'])  : '';
$filtre_fin    = isset($_GET['fin'])    ? trim($_GET['fin'])    : '';

// ── Types d'actions ──
$types_disponibles = [
    'entree_stock'          => ['label' => 'Entrée de stock',        'icon' => '📥', 'cls' => 'type-entree'],
    'sortie_stock'          => ['label' => 'Sortie de stock',        'icon' => '📤', 'cls' => 'type-sortie'],
    'lot_expire'            => ['label' => 'Lot expiré',             'icon' => '🗑️', 'cls' => 'type-expire'],
    'seuil_modifie'         => ['label' => 'Seuil modifié',          'icon' => '✏️', 'cls' => 'type-seuil'],
    'alerte_declenchee'     => ['label' => 'Alerte déclenchée',      'icon' => '🔔', 'cls' => 'type-alerte'],
    'alerte_traitee'        => ['label' => 'Alerte traitée',         'icon' => '✅', 'cls' => 'type-ok'],
    'demande_envoyee'       => ['label' => 'Demande envoyée',        'icon' => '📨', 'cls' => 'type-demande'],
    'demande_recue_traitee' => ['label' => 'Demande hôpital traitée','icon' => '🏥', 'cls' => 'type-demande'],
];

// ── Construction dynamique de la requête ──
$sql = "
    SELECT h.*, g.libelle AS groupe
    FROM historique_sous_banque h
    LEFT JOIN groupe_sanguin g ON g.id_groupe = h.id_groupe
    WHERE h.id_sous_banque = ?
";
$params = [$id_sb];

if ($filtre_type !== '' && isset($types_disponibles[$filtre_type])) {
    $sql .= " AND h.type_action = ?";
    $params[] = $filtre_type;
}
if ($filtre_groupe > 0) {
    $sql .= " AND h.id_groupe = ?";
    $params[] = $filtre_groupe;
}
if ($filtre_debut !== '') {
    $sql .= " AND h.date_action >= ?";
    $params[] = $filtre_debut . " 00:00:00";
}
if ($filtre_fin !== '') {
    $sql .= " AND h.date_action <= ?";
    $params[] = $filtre_fin . " 23:59:59";
}

$sql .= " ORDER BY h.date_action DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$evenements = $stmt->fetchAll();

// ── Stats globales (sans filtre) ──
$stmt = $pdo->prepare("SELECT COUNT(*) FROM historique_sous_banque WHERE id_sous_banque = ?");
$stmt->execute([$id_sb]);
$total_evenements = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM historique_sous_banque WHERE id_sous_banque = ? AND DATE(date_action) = CURDATE()");
$stmt->execute([$id_sb]);
$evenements_aujourdhui = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM historique_sous_banque WHERE id_sous_banque = ? AND type_action = 'lot_expire'");
$stmt->execute([$id_sb]);
$total_lots_expires = (int)$stmt->fetchColumn();

// ── Liste des groupes ──
$groupes_liste = $pdo->query("SELECT id_groupe, libelle FROM groupe_sanguin ORDER BY libelle")->fetchAll();

$page_active = 'historique';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique — <?php echo htmlspecialchars($_SESSION['nom_sb'] ?? 'Sous-banque'); ?> | E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* ── Barre de filtres ── */
        .filtres-panel {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 24px;
        }
        .filtres-row {
            display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end;
        }
        .filtre-field {
            display: flex; flex-direction: column; gap: 6px;
            min-width: 160px;
        }
        .filtre-field label {
            font-size: 12px; font-weight: 700; color: #111111;
        }
        .filtre-field select, .filtre-field input {
            padding: 9px 12px;
            border: 1.5px solid #E5E7EB;
            border-radius: 8px;
            font-size: 13px; color: #111111;
            font-family: inherit; background: #FFFFFF;
        }
        .filtre-field select:focus, .filtre-field input:focus {
            outline: none; border-color: #8B0000;
        }

        /* ── Timeline ── */
        .timeline { position: relative; }
        .timeline-item {
            display: flex; gap: 16px;
            padding: 14px 0;
            border-bottom: 1px solid #F3F4F6;
        }
        .timeline-item:last-child { border-bottom: none; }

        .timeline-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
            border: 1.5px solid transparent;
        }
        .type-entree  .timeline-icon { background: #D1FAE5; border-color: #6EE7B7; }
        .type-sortie  .timeline-icon { background: #FEF3C7; border-color: #FCD34D; }
        .type-expire  .timeline-icon { background: #FEE2E2; border-color: #FCA5A5; }
        .type-seuil   .timeline-icon { background: #E0E7FF; border-color: #A5B4FC; }
        .type-alerte  .timeline-icon { background: #FFEDD5; border-color: #FED7AA; }
        .type-ok      .timeline-icon { background: #D1FAE5; border-color: #6EE7B7; }
        .type-demande .timeline-icon { background: #FEF2F2; border-color: #FCA5A5; }

        .timeline-content { flex: 1; min-width: 0; }
        .timeline-desc {
            font-size: 14px; color: #111111; font-weight: 500;
            margin-bottom: 4px;
            word-wrap: break-word;
        }
        .timeline-meta {
            font-size: 12px; color: #6B7280;
            display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
        }
        .timeline-date {
            font-size: 12px; color: #9CA3AF;
            white-space: nowrap; flex-shrink: 0;
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
        <h1>Historique des actions</h1>
        <p>Traçabilité complète de toutes les opérations effectuées dans votre dépôt.</p>
    </div>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">📋</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total événements</div>
                <div class="stat-mini-number"><?php echo $total_evenements; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">📅</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Aujourd'hui</div>
                <div class="stat-mini-number"><?php echo $evenements_aujourdhui; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">🗑️</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Lots expirés (total)</div>
                <div class="stat-mini-number"><?php echo $total_lots_expires; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ FILTRES ══ -->
    <div class="filtres-panel">
        <form method="GET" action="">
            <div class="filtres-row">
                <div class="filtre-field">
                    <label>Type d'action</label>
                    <select name="type">
                        <option value="">Tous les types</option>
                        <?php foreach ($types_disponibles as $key => $t): ?>
                            <option value="<?php echo $key; ?>" <?php echo $filtre_type === $key ? 'selected' : ''; ?>>
                                <?php echo $t['icon'] . ' ' . $t['label']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtre-field">
                    <label>Groupe sanguin</label>
                    <select name="groupe">
                        <option value="0">Tous les groupes</option>
                        <?php foreach ($groupes_liste as $g): ?>
                            <option value="<?php echo $g['id_groupe']; ?>" <?php echo $filtre_groupe === (int)$g['id_groupe'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($g['libelle']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtre-field">
                    <label>Du</label>
                    <input type="date" name="debut" value="<?php echo htmlspecialchars($filtre_debut); ?>">
                </div>

                <div class="filtre-field">
                    <label>Au</label>
                    <input type="date" name="fin" value="<?php echo htmlspecialchars($filtre_fin); ?>">
                </div>

                <button type="submit" class="btn-submit" style="padding:10px 22px; width:auto; margin:0;">
                    🔍 Filtrer
                </button>
                <a href="historique.php" class="btn-edit" style="text-decoration:none; padding:10px 18px;">
                    Réinitialiser
                </a>
            </div>
        </form>
    </div>

    <!-- ══ TIMELINE ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Journal des événements</div>
            <span class="cnt-badge"><?php echo count($evenements); ?> résultat<?php echo count($evenements) > 1 ? 's' : ''; ?></span>
        </div>

        <div class="timeline">
            <?php if ($evenements): ?>
                <?php foreach ($evenements as $e):
                    $type_info = $types_disponibles[$e['type_action']] ?? ['label' => $e['type_action'], 'icon' => '•', 'cls' => ''];
                ?>
                <div class="timeline-item <?php echo $type_info['cls']; ?>">
                    <div class="timeline-icon"><?php echo $type_info['icon']; ?></div>
                    <div class="timeline-content">
                        <div class="timeline-desc"><?php echo htmlspecialchars($e['description']); ?></div>
                        <div class="timeline-meta">
                            <strong style="color:#374151;"><?php echo $type_info['label']; ?></strong>
                            <?php if ($e['groupe']): ?>
                                <span>•</span>
                                <span class="badge badge-groupe" style="font-size:11px; padding:2px 8px;"><?php echo htmlspecialchars($e['groupe']); ?></span>
                            <?php endif; ?>
                            <?php if ($e['quantite'] !== null): ?>
                                <span>•</span>
                                <span>📦 <?php echo (int)$e['quantite']; ?> poch.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="timeline-date">
                        <strong><?php echo date('d/m/Y', strtotime($e['date_action'])); ?></strong>
                        <br><small style="color:#9CA3AF;">à <?php echo date('H:i', strtotime($e['date_action'])); ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vide" style="padding:40px 0;">Aucun événement trouvé pour ces filtres.</div>
            <?php endif; ?>
        </div>

        <?php if (count($evenements) === 200): ?>
        <div style="text-align:center; padding:14px; color:#9CA3AF; font-size:12px;">
            ⚠️ Affichage limité aux 200 derniers événements. Affinez vos filtres pour voir plus.
        </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>