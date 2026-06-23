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

// ── Groupe sanguin ──
$groupe = "Non confirmé";
if ($donneur['id_groupe']) {
    $stmt = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
    $stmt->execute([$donneur['id_groupe']]);
    $g = $stmt->fetch();
    if ($g) $groupe = $g['libelle'];
}

// ── Statistiques ──
$stmt = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_donneur = ?");
$stmt->execute([$id_donneur]);
$nb_dons = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_donneur = ? AND statut = 'accepté'");
$stmt->execute([$id_donneur]);
$nb_acceptes = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_donneur = ? AND statut = 'en_attente'");
$stmt->execute([$id_donneur]);
$nb_attente = (int)$stmt->fetchColumn();

// ── 5 derniers dons ──
$stmt = $pdo->prepare("
    SELECT don.*, b.nom AS nom_banque, g.libelle AS groupe
    FROM don
    JOIN banque_de_sang b ON b.id_banque = don.id_banque
    JOIN groupe_sanguin g ON g.id_groupe = don.id_groupe
    WHERE don.id_donneur = ?
    ORDER BY don.date_don DESC
    LIMIT 5
");
$stmt->execute([$id_donneur]);
$dons = $stmt->fetchAll();

// ── Date en français ──
$jours_fr = ['Sunday'=>'dimanche', 'Monday'=>'lundi', 'Tuesday'=>'mardi', 'Wednesday'=>'mercredi', 'Thursday'=>'jeudi', 'Friday'=>'vendredi', 'Saturday'=>'samedi'];
$mois_fr  = ['January'=>'janvier', 'February'=>'février', 'March'=>'mars', 'April'=>'avril', 'May'=>'mai', 'June'=>'juin', 'July'=>'juillet', 'August'=>'août', 'September'=>'septembre', 'October'=>'octobre', 'November'=>'novembre', 'December'=>'décembre'];
$date_fr  = $jours_fr[date('l')] . ' ' . date('j') . ' ' . $mois_fr[date('F')] . ' ' . date('Y');

$page_active = 'dashboard';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon espace — E-Sang Donneur</title>
    <style>
        <?php echo $shared_css; ?>

        /* ── Carte profil ── */
        .profil-card {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-left: 4px solid #8B0000;
            border-radius: 14px;
            padding: 22px 26px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 22px;
        }
        .profil-avatar {
            width: 68px; height: 68px;
            border-radius: 50%;
            background: #FEF2F2;
            color: #8B0000;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; font-weight: 800;
            flex-shrink: 0;
            border: 2px solid #FCA5A5;
        }
        .profil-info { flex: 1; min-width: 0; }
        .profil-name-row {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 8px; flex-wrap: wrap;
        }
        .profil-name {
            font-size: 18px; font-weight: 800; color: #111111;
            margin: 0;
        }
        .profil-meta {
            display: flex; flex-wrap: wrap; gap: 18px;
            font-size: 13px; color: #6B7280;
        }
        .profil-meta-item {
            display: inline-flex; align-items: center; gap: 6px;
        }
        .badge-groupe-confirme {
            background: #DCFCE7; color: #166534;
            font-size: 11px; padding: 4px 12px; border-radius: 999px;
            font-weight: 700; border: 1px solid #BBF7D0;
        }
        .badge-groupe-pending {
            background: #FEF3C7; color: #92400E;
            font-size: 11px; padding: 4px 12px; border-radius: 999px;
            font-weight: 700; border: 1px solid #FDE68A;
        }
        @media (max-width: 640px) {
            .profil-card { flex-direction: column; text-align: center; }
            .profil-name-row, .profil-meta { justify-content: center; }
        }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <!-- ══ TOP-BAR DONNEUR ══ -->
    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar"><?php echo $donneur_initials; ?></div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bonjour, <?php echo $donneur_display; ?></div>
                <div class="top-bar-role">Espace donneur</div>
            </div>
        </div>
        <div class="top-bar-date">📅 <?php echo $date_fr; ?></div>
    </div>

    <!-- ══ TITRE ══ -->
    <div class="page-header">
        <h1>Mon espace</h1>
        <p>Bienvenue sur votre espace personnel E-Sang.</p>
    </div>

    <!-- ══ ALERTE GROUPE NON CONFIRMÉ ══ -->
    <?php if (!$donneur['id_groupe']): ?>
    <div class="alerte-info" style="background: #FEF3C7; border-color: #FCD34D;">
        <span>⚠️ Votre groupe sanguin n'est pas encore confirmé. Veuillez vous présenter à une <strong>banque de sang</strong> pour effectuer l'analyse.</span>
    </div>
    <?php endif; ?>

    <!-- ══ CARTE PROFIL ══ -->
    <div class="profil-card">
        <div class="profil-avatar"><?php echo $donneur_initials; ?></div>
        <div class="profil-info">
            <div class="profil-name-row">
                <h2 class="profil-name"><?php echo $citoyen ? htmlspecialchars($citoyen['prenom'] . ' ' . $citoyen['nom']) : '—'; ?></h2>
                <?php if ($donneur['id_groupe']): ?>
                    <span class="badge-groupe-confirme">✓ Groupe : <?php echo htmlspecialchars($groupe); ?></span>
                <?php else: ?>
                    <span class="badge-groupe-pending">⏳ Groupe non confirmé</span>
                <?php endif; ?>
            </div>
            <div class="profil-meta">
                <span class="profil-meta-item" title="NNI">
                    🆔 <strong><?php echo htmlspecialchars($donneur['NNI']); ?></strong>
                </span>
                <span class="profil-meta-item">
                    ✉️ <?php echo htmlspecialchars($donneur['email'] ?: '—'); ?>
                </span>
                <span class="profil-meta-item">
                    📱 <?php echo htmlspecialchars($donneur['telephone'] ?: '—'); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">💉</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total dons</div>
                <div class="stat-mini-number"><?php echo $nb_dons; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">✅</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Dons acceptés</div>
                <div class="stat-mini-number"><?php echo $nb_acceptes; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">⏳</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">En attente</div>
                <div class="stat-mini-number <?php echo $nb_attente > 0 ? 'alert' : ''; ?>"><?php echo $nb_attente; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ DERNIERS DONS ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Mes derniers dons</div>
            <a href="dons.php" style="color:#8B0000; font-weight:700; text-decoration:none; font-size:13px;">
                Voir tout →
            </a>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Banque</th>
                        <th>Groupe</th>
                        <th>Quantité</th>
                        <th>Date</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dons): ?>
                        <?php foreach ($dons as $d): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($d['nom_banque']); ?></strong></td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                            <td><strong><?php echo (int)$d['quantite']; ?></strong> pochette(s)</td>
                            <td><?php echo date('d/m/Y', strtotime($d['date_don'])); ?></td>
                            <td>
                                <?php if ($d['statut'] === 'en_attente'): ?>
                                    <span class="badge badge-attente">⏳ En attente</span>
                                <?php elseif ($d['statut'] === 'accepté'): ?>
                                    <span class="badge badge-accepte">✅ Accepté</span>
                                <?php else: ?>
                                    <span class="badge badge-refuse">❌ Refusé</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="vide">💉 Vous n'avez pas encore effectué de don.<br><small>Présentez-vous à une banque de sang pour commencer !</small></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>