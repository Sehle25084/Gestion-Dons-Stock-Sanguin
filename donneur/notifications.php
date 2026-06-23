<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'donneur') {
    header("Location: ../index.php");
    exit;
}

$id_donneur = $_SESSION['id'];

// ── Données du donneur ──
$stmt = $pdo->prepare("SELECT * FROM donneur WHERE id_donneur = ?");
$stmt->execute([$id_donneur]);
$donneur = $stmt->fetch();

$stmt = $pdo_registre->prepare("SELECT * FROM citoyen WHERE NNI = ?");
$stmt->execute([$donneur['NNI']]);
$citoyen = $stmt->fetch();

// ── Notifications ──
$stmt = $pdo->prepare("
    SELECT *
    FROM notification
    WHERE type_destinataire = 'donneur'
      AND id_destinataire = ?
    ORDER BY date_notification DESC
");
$stmt->execute([$id_donneur]);
$notifications = $stmt->fetchAll();

$nb_notifs = count($notifications);

$page_active = 'notifications';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes notifications — E-Sang Donneur</title>
    <style>
        <?php echo $shared_css; ?>

        /* ── Cartes de notifications ── */
        .notif-list { display: flex; flex-direction: column; gap: 14px; }

        .notif-card {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-left: 4px solid #8B0000;
            border-radius: 14px;
            padding: 18px 22px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            transition: all 0.2s;
        }
        .notif-card:hover {
            border-color: #8B0000;
            box-shadow: 0 4px 12px -4px rgba(139,0,0,0.08);
        }

        .notif-icon {
            width: 42px; height: 42px;
            border-radius: 10px;
            background: #FEF2F2;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
            border: 1.5px solid #FCA5A5;
        }

        .notif-body { flex: 1; min-width: 0; }
        .notif-message {
            font-size: 14px; color: #111111; font-weight: 500;
            line-height: 1.5; margin-bottom: 8px;
            word-wrap: break-word;
        }
        .notif-date {
            font-size: 12px; color: #9CA3AF;
            display: inline-flex; align-items: center; gap: 4px;
        }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <!-- ══ TOP-BAR ══ -->
    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar"><?php echo $donneur_initials; ?></div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bonjour, <?php echo $donneur_display; ?></div>
                <div class="top-bar-role">Espace donneur</div>
            </div>
        </div>
    </div>

    <!-- ══ TITRE ══ -->
    <div class="page-header">
        <h1>Mes notifications</h1>
        <p>Consultez les messages importants liés à votre activité de donneur.</p>
    </div>

    <!-- ══ STATS ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🔔</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total notifications</div>
                <div class="stat-mini-number"><?php echo $nb_notifs; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ LISTE ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Notifications reçues</div>
            <span class="cnt-badge"><?php echo $nb_notifs; ?> message(s)</span>
        </div>

        <?php if ($notifications): ?>
            <div class="notif-list">
                <?php foreach ($notifications as $n): ?>
                    <div class="notif-card">
                        <div class="notif-icon">🔔</div>
                        <div class="notif-body">
                            <div class="notif-message"><?php echo nl2br(htmlspecialchars($n['message'])); ?></div>
                            <div class="notif-date">
                                📅 <?php echo date('d/m/Y à H:i', strtotime($n['date_notification'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="vide" style="padding: 50px 20px; text-align: center;">
                🔔 <strong>Aucune notification</strong><br>
                <small>Vos notifications apparaîtront ici.</small>
            </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>