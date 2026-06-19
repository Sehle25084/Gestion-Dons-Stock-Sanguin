<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'donneur') {
    header("Location: ../index.php");
    exit;
}

$id_donneur = $_SESSION['id'];

$stmt = $pdo->prepare("SELECT * FROM donneur WHERE id_donneur = ?");
$stmt->execute([$id_donneur]);
$donneur = $stmt->fetch();

$stmt = $pdo_registre->prepare("SELECT * FROM citoyen WHERE NNI = ?");
$stmt->execute([$donneur['NNI']]);
$citoyen = $stmt->fetch();

$groupe = "Non confirmé";
if ($donneur['id_groupe']) {
    $stmt = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
    $stmt->execute([$donneur['id_groupe']]);
    $g = $stmt->fetch();
    if ($g) $groupe = $g['libelle'];
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_donneur = ?");
$stmt->execute([$id_donneur]);
$nb_dons = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_donneur = ? AND statut = 'accepté'");
$stmt->execute([$id_donneur]);
$nb_acceptes = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_donneur = ? AND statut = 'en_attente'");
$stmt->execute([$id_donneur]);
$nb_attente = $stmt->fetchColumn();

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

// Initiales pour l'avatar (ex: "sehle zemragui" => "SZ")
$initials = '👤';
if ($citoyen && !empty($citoyen['prenom']) && !empty($citoyen['nom'])) {
    $initials = mb_strtoupper(mb_substr($citoyen['prenom'], 0, 1) . mb_substr($citoyen['nom'], 0, 1));
}

$page_active = 'dashboard';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — E-Sang Donneur</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php include 'sidebar.php'; ?>

    <!-- ====== STYLE A : Carte profil minimaliste blanc + accent rouge ====== -->
    <style>
        .profil-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-left: 4px solid #8b1717;
            border-radius: 12px;
            padding: 20px 24px;
            margin: 20px 0 28px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .profil-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #fcebeb;
            color: #8b1717;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            flex-shrink: 0;
            letter-spacing: 0.5px;
        }

        .profil-info {
            flex: 1;
            min-width: 0;
        }

        .profil-name-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }

        .profil-name {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
        }

        .profil-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            font-size: 13px;
            color: #6b6b6b;
        }

        .profil-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .profil-meta-item svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            stroke: #8b1717;
        }

        .badge-non-verifie-profil {
            background: #fef3c7;
            color: #92400e;
            font-size: 11px;
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 600;
            border: 1px solid #fde68a;
        }

        .badge-groupe-profil {
            background: #dcfce7;
            color: #166534;
            font-size: 11px;
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 600;
            border: 1px solid #bbf7d0;
        }

        @media (max-width: 640px) {
            .profil-card {
                flex-direction: column;
                text-align: center;
                gap: 14px;
            }
            .profil-name-row,
            .profil-meta {
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="main-content">

    <div class="page-header">
        <h1>Mon espace</h1>
        <p>Bienvenue, <?php echo $citoyen ? htmlspecialchars($citoyen['prenom'] . ' ' . $citoyen['nom']) : htmlspecialchars($donneur['NNI']); ?> !</p>
    </div>

    <?php if (!$donneur['id_groupe']): ?>
    <div class="alerte-warning">
        ⚠️ Votre groupe sanguin n'est pas encore confirmé. Veuillez vous présenter à une banque de sang pour l'analyse.
    </div>
    <?php endif; ?>

    <!-- Carte profil (Style A) -->
    <div class="profil-card">
        <div class="profil-avatar"><?php echo htmlspecialchars($initials); ?></div>
        <div class="profil-info">
            <div class="profil-name-row">
                <h2 class="profil-name"><?php echo $citoyen ? htmlspecialchars($citoyen['prenom'] . ' ' . $citoyen['nom']) : '—'; ?></h2>
                <?php if ($donneur['id_groupe']): ?>
                    <span class="badge-groupe-profil">Groupe : <?php echo htmlspecialchars($groupe); ?></span>
                <?php else: ?>
                    <span class="badge-non-verifie-profil">Groupe non confirmé</span>
                <?php endif; ?>
            </div>
            <div class="profil-meta">
                <span class="profil-meta-item" title="Numéro National d'Identité">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                        <path d="M3 10h18"/><path d="M7 15h4"/>
                    </svg>
                    NNI&nbsp;<?php echo htmlspecialchars($donneur['NNI']); ?>
                </span>
                <span class="profil-meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                        <path d="M3 7l9 6 9-6"/>
                    </svg>
                    <?php echo htmlspecialchars($donneur['email'] ?: '—'); ?>
                </span>
                <span class="profil-meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 4h4l2 5-3 2a11 11 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>
                    </svg>
                    <?php echo htmlspecialchars($donneur['telephone'] ?: '—'); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats stats-3">
        <div class="stat-card c1">
            <div class="stat-card-header">
                <span class="stat-label">Total dons</span>
                <span class="stat-icon ic-red">💉</span>
            </div>
            <span class="stat-number"><?php echo $nb_dons; ?></span>
        </div>
        <div class="stat-card c2">
            <div class="stat-card-header">
                <span class="stat-label">Dons acceptés</span>
                <span class="stat-icon ic-grn">✅</span>
            </div>
            <span class="stat-number"><?php echo $nb_acceptes; ?></span>
        </div>
        <div class="stat-card c3">
            <div class="stat-card-header">
                <span class="stat-label">En attente</span>
                <span class="stat-icon ic-org">⏳</span>
            </div>
            <span class="stat-number"><?php echo $nb_attente; ?></span>
        </div>
    </div>

    <!-- Tableau derniers dons -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Mes derniers dons</div>
            <a href="dons.php" class="section-link">Voir tout →</a>
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
                            <td><?php echo htmlspecialchars($d['nom_banque']); ?></td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                            <td><strong><?php echo (int)$d['quantite']; ?></strong> pochette(s)</td>
                            <td><?php echo date('d/m/Y', strtotime($d['date_don'])); ?></td>
                            <td>
                                <?php if ($d['statut'] === 'en_attente'): ?><span class="badge badge-attente">En attente</span>
                                <?php elseif ($d['statut'] === 'accepté'): ?><span class="badge badge-accepte">Accepté</span>
                                <?php else: ?><span class="badge badge-refuse">Refusé</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="vide">💉 Vous n'avez pas encore effectué de don.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>