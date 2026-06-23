<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'banque') {
    header("Location: ../index.php");
    exit;
}

// Compatibilité ancienne/nouvelle architecture
$id_banque = $_SESSION['id_banque'] ?? $_SESSION['id'];
$page_active = 'activites';

// ════════════════════════════════════════════════════════
// FILTRES
// ════════════════════════════════════════════════════════
$filtre_debut = $_GET['debut'] ?? '';
$filtre_fin   = $_GET['fin']   ?? '';

$sql = "
    SELECT *
    FROM log_activite
    WHERE role_utilisateur = 'banque'
      AND id_utilisateur = ?
";
$params = [$id_banque];

if ($filtre_debut !== '') {
    $sql .= " AND date_action >= ?";
    $params[] = $filtre_debut . " 00:00:00";
}
if ($filtre_fin !== '') {
    $sql .= " AND date_action <= ?";
    $params[] = $filtre_fin . " 23:59:59";
}

$sql .= " ORDER BY date_action DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$activites = $stmt->fetchAll();

// ════════════════════════════════════════════════════════
// STATISTIQUES
// ════════════════════════════════════════════════════════
$stmt = $pdo->prepare("SELECT COUNT(*) FROM log_activite WHERE role_utilisateur = 'banque' AND id_utilisateur = ?");
$stmt->execute([$id_banque]);
$total_activites = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM log_activite WHERE role_utilisateur = 'banque' AND id_utilisateur = ? AND DATE(date_action) = CURDATE()");
$stmt->execute([$id_banque]);
$activites_aujourdhui = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM log_activite WHERE role_utilisateur = 'banque' AND id_utilisateur = ? AND date_action >= ?");
$stmt->execute([$id_banque, date('Y-m-d', strtotime('-7 days'))]);
$activites_semaine = (int)$stmt->fetchColumn();

// ════════════════════════════════════════════════════════
// Détection du type d'action depuis le texte (pour l'icône)
// ════════════════════════════════════════════════════════
function detecterTypeAction($action) {
    $action_lower = strtolower($action);
    if (stripos($action_lower, 'acceptation') !== false || stripos($action_lower, 'accepté') !== false) return ['icon' => '✅', 'cls' => 'type-accept'];
    if (stripos($action_lower, 'refus') !== false)                                                       return ['icon' => '❌', 'cls' => 'type-refuse'];
    if (stripos($action_lower, 'ajout') !== false || stripos($action_lower, 'enregistr') !== false)     return ['icon' => '➕', 'cls' => 'type-add'];
    if (stripos($action_lower, 'suppression') !== false || stripos($action_lower, 'supprim') !== false) return ['icon' => '🗑️', 'cls' => 'type-del'];
    if (stripos($action_lower, 'mise à jour') !== false || stripos($action_lower, 'modific') !== false) return ['icon' => '✏️', 'cls' => 'type-edit'];
    if (stripos($action_lower, 'demande') !== false)                                                      return ['icon' => '📋', 'cls' => 'type-demande'];
    if (stripos($action_lower, 'don') !== false)                                                          return ['icon' => '💉', 'cls' => 'type-don'];
    if (stripos($action_lower, 'stock') !== false)                                                        return ['icon' => '🩸', 'cls' => 'type-stock'];
    return ['icon' => '•', 'cls' => 'type-default'];
}

require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activités — <?php echo htmlspecialchars($_SESSION['nom_banque'] ?? 'Banque'); ?> | E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* ── Panneau filtres ── */
        .filtres-panel {
            background: #FFFFFF; border: 1.5px solid #E5E7EB; border-radius: 14px;
            padding: 18px 22px; margin-bottom: 24px;
        }
        .filtres-row { display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end; }
        .filtre-field { display: flex; flex-direction: column; gap: 6px; min-width: 160px; }
        .filtre-field label { font-size: 12px; font-weight: 700; color: #111111; text-transform: uppercase; letter-spacing: 0.4px; }
        .filtre-field input {
            padding: 9px 12px; border: 1.5px solid #E5E7EB; border-radius: 8px;
            font-size: 13px; color: #111111; font-family: inherit; background: #FFFFFF;
        }
        .filtre-field input:focus { outline: none; border-color: #8B0000; }
        .btn-filtrer {
            background: #8B0000; color: #FFFFFF; border: none; padding: 10px 22px;
            border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: inherit;
        }
        .btn-filtrer:hover { background: #6B0000; }
        .btn-reset-filtre {
            background: none; border: 1.5px solid #E5E7EB; color: #111111; padding: 10px 18px;
            border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit;
            text-decoration: none; display: inline-flex; align-items: center;
        }
        .btn-reset-filtre:hover { border-color: #8B0000; color: #8B0000; }

        /* ── Timeline d'activités ── */
        .timeline { position: relative; }
        .timeline-item {
            display: flex; gap: 16px; padding: 14px 0; border-bottom: 1px solid #F3F4F6;
        }
        .timeline-item:last-child { border-bottom: none; }
        .timeline-icon {
            width: 42px; height: 42px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .type-accept  .timeline-icon { background: #D1FAE5; }
        .type-refuse  .timeline-icon { background: #FEE2E2; }
        .type-add     .timeline-icon { background: #DBEAFE; }
        .type-del     .timeline-icon { background: #FEE2E2; }
        .type-edit    .timeline-icon { background: #E0E7FF; }
        .type-demande .timeline-icon { background: #FEF3C7; }
        .type-don     .timeline-icon { background: #FDF2F2; }
        .type-stock   .timeline-icon { background: #FFEDD5; }
        .type-default .timeline-icon { background: #F3F4F6; }

        .timeline-content { flex: 1; min-width: 0; }
        .timeline-desc {
            font-size: 14px; color: #111111; font-weight: 500;
            margin-bottom: 4px;
            word-wrap: break-word;
        }
        .timeline-meta {
            font-size: 11px; color: #9CA3AF;
        }
        .timeline-date {
            font-size: 12px; color: #6B7280; white-space: nowrap; flex-shrink: 0;
            text-align: right;
        }
        .timeline-date strong { color: #111111; display: block; }
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
        <h1>Journal des activités</h1>
        <p>Historique de toutes les actions effectuées par votre banque (audit trail).</p>
    </div>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">📋</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total activités</div>
                <div class="stat-mini-number"><?php echo $total_activites; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">📅</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Aujourd'hui</div>
                <div class="stat-mini-number"><?php echo $activites_aujourdhui; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">📆</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">7 derniers jours</div>
                <div class="stat-mini-number"><?php echo $activites_semaine; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ FILTRES PAR DATE ══ -->
    <div class="filtres-panel">
        <form method="GET" action="">
            <div class="filtres-row">
                <div class="filtre-field">
                    <label>Du</label>
                    <input type="date" name="debut" value="<?php echo htmlspecialchars($filtre_debut); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="filtre-field">
                    <label>Au</label>
                    <input type="date" name="fin" value="<?php echo htmlspecialchars($filtre_fin); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <button type="submit" class="btn-filtrer">🔍 Filtrer</button>
                <a href="activites.php" class="btn-reset-filtre">↺ Réinitialiser</a>
            </div>
        </form>
    </div>

    <!-- ══ TIMELINE ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Journal détaillé</div>
            <span class="cnt-badge"><?php echo count($activites); ?> activité<?php echo count($activites) > 1 ? 's' : ''; ?> affichée<?php echo count($activites) > 1 ? 's' : ''; ?></span>
        </div>

        <div class="timeline" style="padding: 0 4px;">
            <?php if ($activites): ?>
                <?php foreach ($activites as $a): ?>
                    <?php $type_info = detecterTypeAction($a['action']); ?>
                    <div class="timeline-item <?php echo $type_info['cls']; ?>">
                        <div class="timeline-icon"><?php echo $type_info['icon']; ?></div>
                        <div class="timeline-content">
                            <div class="timeline-desc"><?php echo htmlspecialchars($a['action']); ?></div>
                            <div class="timeline-meta">Activité #<?php echo $a['id_log'] ?? '—'; ?></div>
                        </div>
                        <div class="timeline-date">
                            <strong><?php echo date('d/m/Y', strtotime($a['date_action'])); ?></strong>
                            <?php echo date('H:i', strtotime($a['date_action'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="vide" style="padding:40px 0; text-align:center;">
                    <?php if ($filtre_debut || $filtre_fin): ?>
                        Aucune activité trouvée pour cette période.
                    <?php else: ?>
                        Aucune activité enregistrée pour le moment.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
</body>
</html>