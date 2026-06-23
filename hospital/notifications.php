<?php
session_start();
require_once '../config/db.php';
require_once '../config/notifications_helper.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hopital') {
    header('Location: ../index.php');
    exit;
}
if (!isset($_SESSION['id_hopital']) || !isset($_SESSION['id_responsable'])) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$id_hopital     = $_SESSION['id_hopital'];
$id_responsable = $_SESSION['id_responsable'];
$page_active    = 'notifications';

// ── Marquer une notification comme lue (clic individuel) ──
if (isset($_GET['lue'])) {
    $id_notif = (int) $_GET['lue'];
    $stmt = $pdo->prepare("UPDATE notification_hopital SET lu = 1 WHERE id_notification = ? AND id_hopital = ?");
    $stmt->execute([$id_notif, $id_hopital]);
    header('Location: notifications.php');
    exit;
}

// ── Marquer TOUTES comme lues ──
if (isset($_GET['toutes_lues'])) {
    $stmt = $pdo->prepare("UPDATE notification_hopital SET lu = 1 WHERE id_hopital = ? AND lu = 0");
    $stmt->execute([$id_hopital]);
    header('Location: notifications.php?all_read=1');
    exit;
}

// ── Supprimer une notification ──
if (isset($_GET['supprimer'])) {
    $id_notif = (int) $_GET['supprimer'];
    $stmt = $pdo->prepare("DELETE FROM notification_hopital WHERE id_notification = ? AND id_hopital = ?");
    $stmt->execute([$id_notif, $id_hopital]);
    header('Location: notifications.php?supprime=1');
    exit;
}

// ── Filtres ──
$filtre = $_GET['filtre'] ?? 'tous'; // tous / non_lues / lues

$sql = "SELECT * FROM notification_hopital WHERE id_hopital = ?";
$params = [$id_hopital];
if ($filtre === 'non_lues') { $sql .= " AND lu = 0"; }
elseif ($filtre === 'lues') { $sql .= " AND lu = 1"; }
$sql .= " ORDER BY date_creation DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stmt_t = $pdo->prepare("SELECT COUNT(*) FROM notification_hopital WHERE id_hopital = ?");
$stmt_t->execute([$id_hopital]);
$nb_total = (int)$stmt_t->fetchColumn();

$nb_non_lues = compter_notifications_non_lues($pdo, $id_hopital);
$nb_lues     = $nb_total - $nb_non_lues;

require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        .notif-list { display: flex; flex-direction: column; gap: 10px; margin-top: 16px; }

        .notif-card {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            gap: 14px;
            align-items: flex-start;
            transition: all 0.15s;
        }
        .notif-card.non-lue {
            background: #FFFBEB;
            border-left: 4px solid #8B0000;
        }
        .notif-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

        .notif-icon {
            width: 42px; height: 42px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .notif-icon.info    { background: #DBEAFE; color: #1E40AF; }
        .notif-icon.succes  { background: #DCFCE7; color: #166534; }
        .notif-icon.alerte  { background: #FEF3C7; color: #92400E; }
        .notif-icon.urgence { background: #FEE2E2; color: #991B1B; }

        .notif-body { flex: 1; min-width: 0; }
        .notif-titre {
            font-size: 15px; font-weight: 700; color: #111111;
            margin-bottom: 4px;
        }
        .notif-message {
            font-size: 13px; color: #4B5563;
            line-height: 1.55;
            white-space: pre-wrap;
        }
        .notif-meta {
            display: flex; gap: 12px;
            font-size: 11px; color: #9CA3AF;
            margin-top: 8px;
            align-items: center;
        }
        .notif-actions {
            display: flex; gap: 8px;
            flex-shrink: 0;
        }
        .btn-mini {
            font-size: 11px; font-weight: 700;
            padding: 6px 12px; border-radius: 8px;
            border: 1.5px solid #E5E7EB;
            background: #FFFFFF;
            color: #6B7280;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-mini:hover { border-color: #8B0000; color: #8B0000; }
        .btn-mini.danger:hover { border-color: #DC2626; color: #DC2626; }

        .badge-non-lu {
            background: #8B0000; color: #FFFFFF;
            font-size: 10px; font-weight: 800;
            padding: 2px 8px; border-radius: 999px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9CA3AF;
        }
        .empty-state .icon { font-size: 48px; margin-bottom: 16px; }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<main class="main-content">

    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1>Mes notifications 🔔</h1>
            <p>Toutes les réponses de votre sous-banque et les alertes du système</p>
        </div>
        <?php if ($nb_non_lues > 0): ?>
        <a href="notifications.php?toutes_lues=1" class="btn-submit" style="width:auto; margin-top:0;">
            ✓ Marquer toutes comme lues
        </a>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['all_read'])): ?>
        <div class="alerte-success">✅ Toutes les notifications ont été marquées comme lues.</div>
    <?php endif; ?>
    <?php if (isset($_GET['supprime'])): ?>
        <div class="alerte-info">🗑️ Notification supprimée.</div>
    <?php endif; ?>

    <!-- Stats compactes -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">🔔</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total</div>
                <div class="stat-mini-number"><?php echo $nb_total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">📬</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Non lues</div>
                <div class="stat-mini-number <?php echo $nb_non_lues > 0 ? 'alert' : ''; ?>"><?php echo $nb_non_lues; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">✓</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Lues</div>
                <div class="stat-mini-number"><?php echo $nb_lues; ?></div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="filters" style="display:flex; gap:10px; margin: 24px 0 0;">
        <a href="notifications.php?filtre=tous" class="filter-btn <?= $filtre === 'tous' ? 'active' : '' ?>" style="padding: 9px 20px; border-radius: 10px; border: 2px solid #E5E7EB; background: #fff; color: #374151; text-decoration: none; font-weight: 500; <?= $filtre === 'tous' ? 'background:#8B0000;color:#fff;border-color:#8B0000;' : '' ?>">Toutes (<?php echo $nb_total; ?>)</a>
        <a href="notifications.php?filtre=non_lues" class="filter-btn <?= $filtre === 'non_lues' ? 'active' : '' ?>" style="padding: 9px 20px; border-radius: 10px; border: 2px solid #E5E7EB; background: #fff; color: #374151; text-decoration: none; font-weight: 500; <?= $filtre === 'non_lues' ? 'background:#8B0000;color:#fff;border-color:#8B0000;' : '' ?>">Non lues (<?php echo $nb_non_lues; ?>)</a>
        <a href="notifications.php?filtre=lues" class="filter-btn <?= $filtre === 'lues' ? 'active' : '' ?>" style="padding: 9px 20px; border-radius: 10px; border: 2px solid #E5E7EB; background: #fff; color: #374151; text-decoration: none; font-weight: 500; <?= $filtre === 'lues' ? 'background:#8B0000;color:#fff;border-color:#8B0000;' : '' ?>">Lues (<?php echo $nb_lues; ?>)</a>
    </div>

    <!-- Liste -->
    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <h3 style="color:#6B7280; margin-bottom:8px;">Aucune notification</h3>
            <p>Vous serez prévenu ici dès que votre sous-banque répondra à une demande.</p>
        </div>
    <?php else: ?>
        <div class="notif-list">
            <?php foreach ($notifications as $n):
                $non_lue = (int)$n['lu'] === 0;
                $icon_map = [
                    'info'    => 'ℹ️',
                    'succes'  => '✅',
                    'alerte'  => '⚠️',
                    'urgence' => '🚨',
                ];
                $icon = $icon_map[$n['type']] ?? 'ℹ️';
                $ts = strtotime($n['date_creation']);
                $diff = time() - $ts;
                if ($diff < 60)         $when = "à l'instant";
                elseif ($diff < 3600)   $when = "il y a " . intdiv($diff, 60) . " min";
                elseif ($diff < 86400)  $when = "il y a " . intdiv($diff, 3600) . " h";
                elseif ($diff < 604800) $when = "il y a " . intdiv($diff, 86400) . " j";
                else                    $when = date('d/m/Y H:i', $ts);
            ?>
            <div class="notif-card <?php echo $non_lue ? 'non-lue' : ''; ?>">
                <div class="notif-icon <?php echo htmlspecialchars($n['type']); ?>"><?php echo $icon; ?></div>
                <div class="notif-body">
                    <div class="notif-titre">
                        <?php echo htmlspecialchars($n['titre']); ?>
                        <?php if ($non_lue): ?><span class="badge-non-lu">NOUVEAU</span><?php endif; ?>
                    </div>
                    <div class="notif-message"><?php echo nl2br(htmlspecialchars($n['message'])); ?></div>
                    <div class="notif-meta">
                        <span>📅 <?php echo $when; ?></span>
                        <span>•</span>
                        <span>Type : <strong><?php echo htmlspecialchars($n['type']); ?></strong></span>
                    </div>
                </div>
                <div class="notif-actions">
                    <?php if ($non_lue): ?>
                        <a href="notifications.php?lue=<?php echo $n['id_notification']; ?>" class="btn-mini">✓ Lue</a>
                    <?php endif; ?>
                    <a href="notifications.php?supprimer=<?php echo $n['id_notification']; ?>"
                       class="btn-mini danger"
                       onclick="return confirm('Supprimer cette notification ?')">🗑️</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</main>
</body>
</html>