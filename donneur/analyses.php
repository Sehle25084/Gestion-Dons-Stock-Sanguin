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

// ── Analyses du donneur ──
$stmt = $pdo->prepare("
    SELECT
        a.id_analyse,
        a.date_analyse,
        a.hemoglobine,
        a.tension AS tension_arterielle,
        a.poids,
        a.resultat_global AS resultat,
        a.note AS commentaire_medical,
        a.id_don,
        don.date_don,
        don.quantite,
        b.nom       AS nom_banque,
        b.wilaya    AS wilaya_banque,
        g.libelle   AS groupe_libelle
    FROM analyse_sang a
    JOIN don              ON don.id_don     = a.id_don
    JOIN banque_de_sang b ON b.id_banque    = don.id_banque
    JOIN groupe_sanguin g ON g.id_groupe    = don.id_groupe
    WHERE don.id_donneur = ?
    ORDER BY a.date_analyse DESC
");
$stmt->execute([$id_donneur]);
$analyses = $stmt->fetchAll();

// ── Stats ──
$nb_total = count($analyses);
$nb_valide = 0; $nb_invalide = 0; $nb_pending = 0;
foreach ($analyses as $a) {
    if      ($a['resultat'] === 'conforme')     $nb_valide++;
    elseif  ($a['resultat'] === 'non_conforme') $nb_invalide++;
    else                                        $nb_pending++;
}

$page_active = 'analyses';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes analyses — E-Sang Donneur</title>
    <style>
        <?php echo $shared_css; ?>

        /* ── Cartes d'analyses ── */
        .analyse-list { display: flex; flex-direction: column; gap: 16px; }

        .analyse-card {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 14px;
            padding: 22px 26px;
            transition: all 0.2s;
        }
        .analyse-card:hover {
            border-color: #8B0000;
            box-shadow: 0 6px 14px -4px rgba(139,0,0,0.08);
        }
        .analyse-card.valide   { border-left: 4px solid #16A34A; }
        .analyse-card.invalide { border-left: 4px solid #DC2626; }
        .analyse-card.pending  { border-left: 4px solid #EA580C; }

        .analyse-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1.5px solid #F3F4F6;
        }
        .analyse-header-left { display: flex; align-items: center; gap: 14px; }

        .analyse-icon {
            width: 46px; height: 46px;
            border-radius: 12px;
            background: #FEF2F2;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
            border: 1.5px solid #FCA5A5;
        }

        .analyse-title { font-size: 15px; font-weight: 800; color: #111111; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .analyse-sub   { font-size: 12px; color: #6B7280; font-weight: 500; }

        .analyse-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }
        .metric-box {
            background: #F9FAFB;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            padding: 12px 14px;
        }
        .metric-label {
            font-size: 10px; font-weight: 800; color: #6B7280;
            text-transform: uppercase; letter-spacing: 0.06em;
            margin-bottom: 6px;
        }
        .metric-value {
            font-size: 19px; font-weight: 800; color: #111111;
            line-height: 1;
        }
        .metric-unit {
            font-size: 11px; font-weight: 600; color: #9CA3AF;
            margin-left: 3px;
        }
        .metric-na { font-size: 14px; font-weight: 600; color: #D1D5DB; }

        .commentaire-block {
            background: #FFFBEB;
            border: 1.5px solid #FCD34D;
            border-radius: 10px;
            padding: 12px 16px;
            margin-top: 4px;
        }
        .commentaire-label {
            font-size: 11px; font-weight: 800; color: #92400E;
            text-transform: uppercase; letter-spacing: 0.06em;
            margin-bottom: 6px;
        }
        .commentaire-text {
            font-size: 13px; color: #78350F; line-height: 1.5;
        }

        .badge-valide   { background: #DCFCE7; color: #166534; }
        .badge-invalide { background: #FEE2E2; color: #B91C1C; }
        .badge-pending  { background: #FEF3C7; color: #92400E; }

        @media (max-width: 640px) {
            .analyse-metrics { grid-template-columns: repeat(2, 1fr); }
            .analyse-header  { flex-direction: column; align-items: flex-start; }
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
        <h1>Mes analyses</h1>
        <p>Résultats médicaux après chaque don de sang.</p>
    </div>

    <?php if (!$donneur['id_groupe']): ?>
    <div class="alerte-info" style="background: #FEF3C7; border-color: #FCD34D;">
        <span>⚠️ Votre groupe sanguin n'est pas encore confirmé. Présentez-vous à une banque de sang pour votre première analyse.</span>
    </div>
    <?php endif; ?>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🔬</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total analyses</div>
                <div class="stat-mini-number"><?php echo $nb_total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">✅</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Validées</div>
                <div class="stat-mini-number"><?php echo $nb_valide; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">❌</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Invalidées</div>
                <div class="stat-mini-number"><?php echo $nb_invalide; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">⏳</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">En attente</div>
                <div class="stat-mini-number <?php echo $nb_pending > 0 ? 'alert' : ''; ?>"><?php echo $nb_pending; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ LISTE DES ANALYSES ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Historique des résultats</div>
            <span class="cnt-badge"><?php echo $nb_total; ?> analyse(s)</span>
        </div>

        <?php if ($analyses): ?>
        <div class="analyse-list">
            <?php foreach ($analyses as $a):
                $statut_class = ($a['resultat'] === 'conforme')     ? 'valide'  :
                                (($a['resultat'] === 'non_conforme') ? 'invalide' : 'pending');
                $badge_class  = ($a['resultat'] === 'conforme')     ? 'badge-valide' :
                                (($a['resultat'] === 'non_conforme') ? 'badge-invalide' : 'badge-pending');
                $label_res    = ($a['resultat'] === 'conforme')     ? '✓ Conforme'      :
                                (($a['resultat'] === 'non_conforme') ? '✘ Non conforme' : '⏳ En cours');
            ?>
            <div class="analyse-card <?php echo $statut_class; ?>">

                <div class="analyse-header">
                    <div class="analyse-header-left">
                        <div class="analyse-icon">🩸</div>
                        <div>
                            <div class="analyse-title">
                                Don du <?php echo date('d/m/Y', strtotime($a['date_don'])); ?>
                                <span class="badge badge-groupe"><?php echo htmlspecialchars($a['groupe_libelle']); ?></span>
                            </div>
                            <div class="analyse-sub">
                                📍 <?php echo htmlspecialchars($a['nom_banque']); ?><?php echo $a['wilaya_banque'] ? ', ' . htmlspecialchars($a['wilaya_banque']) : ''; ?>
                                &nbsp;·&nbsp; Analysé le <?php echo date('d/m/Y', strtotime($a['date_analyse'])); ?>
                            </div>
                        </div>
                    </div>
                    <span class="badge <?php echo $badge_class; ?>"><?php echo $label_res; ?></span>
                </div>

                <div class="analyse-metrics">
                    <div class="metric-box">
                        <div class="metric-label">Hémoglobine</div>
                        <?php if ($a['hemoglobine']): ?>
                            <div class="metric-value"><?php echo number_format($a['hemoglobine'], 1); ?><span class="metric-unit">g/dL</span></div>
                        <?php else: ?>
                            <div class="metric-na">—</div>
                        <?php endif; ?>
                    </div>

                    <div class="metric-box">
                        <div class="metric-label">Tension artérielle</div>
                        <?php if ($a['tension_arterielle']): ?>
                            <div class="metric-value" style="font-size:15px;"><?php echo htmlspecialchars($a['tension_arterielle']); ?><span class="metric-unit">mmHg</span></div>
                        <?php else: ?>
                            <div class="metric-na">—</div>
                        <?php endif; ?>
                    </div>

                    <div class="metric-box">
                        <div class="metric-label">Poids</div>
                        <?php if ($a['poids']): ?>
                            <div class="metric-value"><?php echo (int)$a['poids']; ?><span class="metric-unit">kg</span></div>
                        <?php else: ?>
                            <div class="metric-na">—</div>
                        <?php endif; ?>
                    </div>

                    <div class="metric-box">
                        <div class="metric-label">Quantité don</div>
                        <div class="metric-value"><?php echo (int)$a['quantite']; ?><span class="metric-unit">poch.</span></div>
                    </div>
                </div>

                <?php if (!empty($a['commentaire_medical'])): ?>
                <div class="commentaire-block">
                    <div class="commentaire-label">💬 Commentaire médical</div>
                    <div class="commentaire-text"><?php echo nl2br(htmlspecialchars($a['commentaire_medical'])); ?></div>
                </div>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <div class="vide" style="padding: 50px 20px; text-align: center;">
            🔬 <strong>Aucune analyse disponible</strong><br>
            <small>Vos résultats d'analyse apparaîtront ici après chaque don de sang.</small>
        </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>