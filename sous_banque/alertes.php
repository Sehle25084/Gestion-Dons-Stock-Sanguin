<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sous_banque') {
    header("Location: ../index.php");
    exit;
}

$id_sb                = $_SESSION['id_sous_banque'];

// Synchroniser les demandes externes acceptées par la banque (création de lot + mise à jour stock)
require_once '_sync_demandes.php';
$id_utilisateur_session = $_SESSION['id_utilisateur'] ?? null;
$id_banque_principale = $_SESSION['id_banque_principale'];
$success = $erreur    = "";

// ════════════════════════════════════════════════════════════════
// TRAITEMENT : Marquer UNE alerte comme traitée
// ════════════════════════════════════════════════════════════════
if (isset($_GET['traiter'])) {
    $id_alerte = (int)$_GET['traiter'];
    $stmt = $pdo->prepare("SELECT id_alerte FROM alerte_stock WHERE id_alerte = ? AND id_sous_banque = ?");
    $stmt->execute([$id_alerte, $id_sb]);
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE alerte_stock SET traitee = 1 WHERE id_alerte = ?")
            ->execute([$id_alerte]);

        $pdo->prepare("
            INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, id_utilisateur, date_action)
            VALUES (?, NULL, 'alerte_traitee', NULL, ?, ?, NOW())
        ")->execute([$id_sb, "Alerte #{$id_alerte} marquée comme traitée", $id_utilisateur_session]);

        $success = "Alerte marquée comme traitée.";
    } else {
        $erreur = "Alerte introuvable.";
    }
}

// ════════════════════════════════════════════════════════════════
// TRAITEMENT : Tout marquer comme traité
// ════════════════════════════════════════════════════════════════
if (isset($_POST['tout_traiter'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM alerte_stock WHERE id_sous_banque = ? AND traitee = 0");
    $stmt->execute([$id_sb]);
    $nb_avant = (int)$stmt->fetchColumn();

    if ($nb_avant > 0) {
        $pdo->prepare("UPDATE alerte_stock SET traitee = 1 WHERE id_sous_banque = ? AND traitee = 0")
            ->execute([$id_sb]);

        $pdo->prepare("
            INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, id_utilisateur, date_action)
            VALUES (?, NULL, 'alerte_traitee', NULL, ?, ?, NOW())
        ")->execute([$id_sb, "{$nb_avant} alerte(s) marquées comme traitées en masse", $id_utilisateur_session]);

        $success = "{$nb_avant} alerte(s) marquée(s) comme traitée(s).";
    } else {
        $erreur = "Aucune alerte à traiter.";
    }
}

// ════════════════════════════════════════════════════════════════
// TRAITEMENT : Créer une demande de réapprovisionnement
// ════════════════════════════════════════════════════════════════
if (isset($_POST['commander'])) {
    $id_groupe = (int)($_POST['id_groupe_cmd'] ?? 0);
    $quantite  = (int)($_POST['quantite_cmd'] ?? 0);

    if ($id_groupe <= 0 || $quantite <= 0) {
        $erreur = "Données invalides.";
    } elseif ($quantite > 500) {
        $erreur = "Maximum 500 pochettes par demande.";
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM demande
            WHERE id_sous_banque = ? AND id_groupe = ?
              AND type_demande = 'externe' AND statut = 'en_attente'
        ");
        $stmt->execute([$id_sb, $id_groupe]);

        if ((int)$stmt->fetchColumn() > 0) {
            $erreur = "Une demande est déjà en cours pour ce groupe sanguin.";
        } else {
            // id_hopital récupéré depuis la session ou la BDD si absent
            $id_hopital = $_SESSION['id_hopital'] ?? null;
            if (!$id_hopital) {
                $stmtSB = $pdo->prepare("SELECT id_hopital FROM sous_banque WHERE id_sous_banque = ?");
                $stmtSB->execute([$id_sb]);
                $id_hopital = (int)$stmtSB->fetchColumn() ?: null;
                if ($id_hopital) $_SESSION['id_hopital'] = $id_hopital;
            }
            if (!$id_hopital) {
                $erreur = "Erreur : impossible de déterminer l'hôpital associé à ce dépôt.";
            } else {
            $pdo->prepare("
                INSERT INTO demande (id_hopital, id_sous_banque, id_banque, id_groupe, quantite_demandee,
                                     date_demande, statut, type_demande, note)
                VALUES (?, ?, ?, ?, ?, CURDATE(), 'en_attente', 'externe',
                        'Demande manuelle depuis page alertes — stock faible')
            ")->execute([$id_hopital, $id_sb, $id_banque_principale, $id_groupe, $quantite]);

            $stmtG = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
            $stmtG->execute([$id_groupe]);
            $libelle_groupe = $stmtG->fetchColumn();

            $pdo->prepare("
                INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, id_utilisateur, date_action)
                VALUES (?, ?, 'demande_envoyee', ?, ?, ?, NOW())
            ")->execute([
                $id_sb, $id_groupe, $quantite,
                "Demande depuis alertes : {$quantite} pochette(s) {$libelle_groupe}",
                $id_utilisateur_session
            ]);

            $success = "Demande de <strong>{$quantite}</strong> pochette(s) {$libelle_groupe} envoyée à la banque principale.";
            } // fin du else id_hopital
        }
    }
}

// ════════════════════════════════════════════════════════════════
// DÉTECTION DYNAMIQUE : État réel du stock (source de vérité)
// ════════════════════════════════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT
        g.id_groupe,
        g.libelle AS groupe,
        COALESCE(s.quantite_disponible, 0) AS quantite,
        COALESCE(s.seuil_alerte, 5)        AS seuil
    FROM groupe_sanguin g
    LEFT JOIN stock_sous_banque s ON s.id_groupe = g.id_groupe AND s.id_sous_banque = ?
    ORDER BY g.libelle
");
$stmt->execute([$id_sb]);
$stocks_reels = $stmt->fetchAll();

$groupes_rupture_reel      = array_filter($stocks_reels, fn($s) => (int)$s['quantite'] === 0);
$groupes_critique_reel     = array_filter($stocks_reels, fn($s) => (int)$s['quantite'] > 0 && (int)$s['quantite'] <= (int)$s['seuil']);
$groupes_avertissement_reel= array_filter($stocks_reels, fn($s) => (int)$s['quantite'] > (int)$s['seuil'] && (int)$s['quantite'] <= (int)$s['seuil'] * 1.5);

$nb_rupture_reel       = count($groupes_rupture_reel);
$nb_critique_reel      = count($groupes_critique_reel);
$nb_avertissement_reel = count($groupes_avertissement_reel);

// ── Alertes enregistrées en base ──
$stmt = $pdo->prepare("
    SELECT
        a.*,
        g.libelle AS groupe,
        COALESCE(s.quantite_disponible, 0) AS stock_actuel,
        COALESCE(s.seuil_alerte, 5)        AS seuil_actuel,
        d.statut   AS statut_demande,
        d.quantite_demandee,
        b.nom      AS nom_banque
    FROM alerte_stock a
    JOIN groupe_sanguin g ON g.id_groupe = a.id_groupe
    LEFT JOIN stock_sous_banque s ON s.id_groupe = a.id_groupe AND s.id_sous_banque = a.id_sous_banque
    LEFT JOIN demande d ON d.id_demande = a.id_demande_auto
    LEFT JOIN banque_de_sang b ON b.id_banque = d.id_banque
    WHERE a.id_sous_banque = ?
    ORDER BY a.traitee ASC, a.date_alerte DESC
");
$stmt->execute([$id_sb]);
$alertes = $stmt->fetchAll();

// ── Lots proches de péremption (≤ 7 jours) ──
$stmt = $pdo->prepare("
    SELECT l.*, g.libelle AS groupe,
           DATEDIFF(l.date_expiration, CURDATE()) AS jours_restants
    FROM lot_sang_sous_banque l
    JOIN groupe_sanguin g ON g.id_groupe = l.id_groupe
    WHERE l.id_sous_banque = ? AND l.statut = 'disponible'
    AND DATEDIFF(l.date_expiration, CURDATE()) <= 7
    ORDER BY l.date_expiration ASC
");
$stmt->execute([$id_sb]);
$lots_peremption = $stmt->fetchAll();

// ── Compteurs alertes enregistrées ──
$nb_actives         = count(array_filter($alertes, fn($a) => !(int)$a['traitee']));
$nb_alertes_rupture = count(array_filter($alertes, fn($a) => !(int)$a['traitee'] && $a['type_alerte'] === 'rupture'));
$nb_alertes_critique= count(array_filter($alertes, fn($a) => !(int)$a['traitee'] && $a['type_alerte'] === 'critique'));
$nb_avertissement   = count(array_filter($alertes, fn($a) => !(int)$a['traitee'] && $a['type_alerte'] === 'avertissement'));
$nb_traitees        = count(array_filter($alertes, fn($a) => (int)$a['traitee']));
$nb_peremption      = count($lots_peremption);

// ── IDs avec alerte active en base ──
$ids_avec_alerte_active = [];
foreach ($alertes as $a) {
    if (!(int)$a['traitee']) {
        $ids_avec_alerte_active[$a['id_groupe']] = true;
    }
}

// ── Alertes virtuelles (stock sous seuil sans alerte en base) ──
$alertes_virtuelles = [];
foreach ($stocks_reels as $s) {
    if (isset($ids_avec_alerte_active[$s['id_groupe']])) continue;

    $qte   = (int)$s['quantite'];
    $seuil = (int)$s['seuil'];

    if ($qte === 0) {
        $type_v = 'rupture';
    } elseif ($qte <= $seuil) {
        $type_v = 'critique';
    } elseif ($qte <= $seuil * 1.5) {
        $type_v = 'avertissement';
    } else {
        continue;
    }

    $alertes_virtuelles[] = [
        'id_alerte'         => null,
        'id_groupe'         => $s['id_groupe'],
        'groupe'            => $s['groupe'],
        'type_alerte'       => $type_v,
        'quantite_actuelle' => $qte,
        'stock_actuel'      => $qte,
        'seuil_alerte'      => $seuil,
        'seuil_actuel'      => $seuil,
        'traitee'           => 0,
        'demande_auto'      => 0,
        'id_demande_auto'   => null,
        'date_alerte'       => date('Y-m-d H:i:s'),
        'statut_demande'    => null,
        'quantite_demandee' => null,
        'nom_banque'        => null,
        'virtuelle'         => true,
    ];
}

// ── Fusion ──
$alertes_non_traitees = array_filter($alertes, fn($a) => !(int)$a['traitee']);
$alertes_traitees     = array_filter($alertes, fn($a) =>  (int)$a['traitee']);
$toutes_alertes       = array_merge($alertes_virtuelles, array_values($alertes_non_traitees), array_values($alertes_traitees));

// ── Compteurs finaux (état réel) ──
$nb_rupture_total  = $nb_rupture_reel;
$nb_critique_total = $nb_critique_reel;
$nb_actives_total  = count($alertes_virtuelles) + $nb_actives;
$nb_avert_total    = $nb_avertissement + count(array_filter($alertes_virtuelles, fn($a) => $a['type_alerte'] === 'avertissement'));

$groupes = $pdo->query("SELECT * FROM groupe_sanguin ORDER BY libelle")->fetchAll();

$page_active = 'alertes';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertes — <?php echo htmlspecialchars($_SESSION['nom_sb'] ?? 'Sous-banque'); ?> | E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* ── Onglets principaux ── */
        .main-tabs {
            display: flex; gap: 0;
            border-bottom: 2px solid #E5E7EB;
            margin-bottom: 24px;
        }
        .main-tab-btn {
            padding: 12px 24px; font-size: 14px; font-weight: 600;
            color: #6B7280; background: none; border: none; cursor: pointer;
            font-family: inherit; position: relative; bottom: -2px;
            border-bottom: 2px solid transparent; transition: all 0.2s;
            display: flex; align-items: center; gap: 8px;
        }
        .main-tab-btn:hover { color: #8B0000; }
        .main-tab-btn.active { color: #8B0000; border-bottom-color: #8B0000; font-weight: 700; }
        .main-tab-content { display: none; }
        .main-tab-content.active { display: block; }
        .tab-badge {
            background: #8B0000; color: #fff; font-size: 10px;
            font-weight: 700; padding: 2px 7px; border-radius: 999px;
        }
        .tab-badge-warn {
            background: #F59E0B; color: #fff; font-size: 10px;
            font-weight: 700; padding: 2px 7px; border-radius: 999px;
        }

        /* ── Cartes alertes ── */
        .alerte-card {
            border: 2px solid #E5E7EB; border-radius: 14px;
            padding: 18px 20px; margin-bottom: 12px;
            display: flex; align-items: flex-start; gap: 16px;
            background: #FFFFFF; transition: all 0.2s;
        }
        .alerte-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .alerte-card.rupture       { border-color: #8B0000; background: #FFF8F8; }
        .alerte-card.critique      { border-color: #FCA5A5; background: #FEF2F2; }
        .alerte-card.avertissement { border-color: #FCD34D; background: #FFFBEB; }
        .alerte-card.traitee       { border-color: #E5E7EB; background: #FAFAFA; opacity: 0.65; }
        .alerte-card.virtuelle     { border-style: dashed; }

        .alerte-icon {
            width: 44px; height: 44px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .icon-rupture       { background: #8B0000; }
        .icon-critique      { background: #FEE2E2; }
        .icon-avertissement { background: #FEF3C7; }
        .icon-traitee       { background: #F0FDF4; }

        .alerte-body { flex: 1; min-width: 0; }
        .alerte-top {
            display: flex; align-items: center;
            gap: 10px; margin-bottom: 6px; flex-wrap: wrap;
        }
        .alerte-titre { font-size: 15px; font-weight: 700; color: #111111; }

        .type-badge {
            display: inline-flex; align-items: center;
            font-weight: 700; padding: 3px 10px;
            border-radius: 999px; font-size: 11px;
        }
        .type-rupture       { background: #8B0000; color: #FFFFFF; }
        .type-critique      { background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5; }
        .type-avertissement { background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; }
        .type-traitee       { background: #F0FDF4; color: #166534; border: 1px solid #BBF7D0; }

        .badge-virtuelle {
            background: #F0F9FF; color: #0369A1;
            border: 1px solid #BAE6FD;
            display: inline-flex; align-items: center;
            font-weight: 600; padding: 2px 8px;
            border-radius: 999px; font-size: 10px;
        }

        .alerte-details {
            display: flex; gap: 16px;
            font-size: 13px; color: #6B7280;
            flex-wrap: wrap; margin-bottom: 8px;
        }

        .demande-auto-info {
            display: inline-flex; align-items: center; gap: 6px;
            background: #EFF6FF; border: 1px solid #BFDBFE;
            border-radius: 8px; padding: 5px 12px;
            font-size: 12px; font-weight: 600; color: #1D4ED8; margin-top: 4px;
        }
        .demande-auto-info.acceptee { background: #F0FDF4; border-color: #BBF7D0; color: #166534; }
        .demande-auto-info.refusee  { background: #FEF2F2; border-color: #FECACA; color: #991B1B; }

        .alerte-actions { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }

        .btn-traiter-alerte {
            background: #8B0000; color: #FFFFFF;
            border: none; border-radius: 8px;
            padding: 7px 16px; font-size: 12px; font-weight: 700;
            cursor: pointer; font-family: inherit; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.15s; white-space: nowrap;
        }
        .btn-traiter-alerte:hover { background: #6B0000; }

        .btn-commander {
            background: #FFFBEB; color: #92400E;
            border: 1.5px solid #FCD34D; border-radius: 8px;
            padding: 6px 14px; font-size: 12px; font-weight: 700;
            cursor: pointer; font-family: inherit;
            display: inline-flex; align-items: center; gap: 5px;
            transition: all 0.15s; white-space: nowrap;
        }
        .btn-commander:hover { background: #FEF3C7; }

        .alerte-date { font-size: 11px; color: #9CA3AF; text-align: right; white-space: nowrap; }

        /* ── Filtres ── */
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
        .filter-btn {
            padding: 7px 16px; border-radius: 999px;
            border: 1.5px solid #E5E7EB; background: #FFFFFF;
            font-size: 13px; font-weight: 600; color: #6B7280;
            cursor: pointer; font-family: inherit; transition: all 0.15s;
            display: flex; align-items: center; gap: 6px;
        }
        .filter-btn:hover { border-color: #8B0000; color: #8B0000; }
        .filter-btn.active { background: #8B0000; color: #FFFFFF; border-color: #8B0000; }

        /* ── Modals ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.45); backdrop-filter: blur(3px);
            z-index: 999; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: #FFFFFF; border-radius: 20px;
            width: 100%; max-width: 420px; padding: 28px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            animation: slideUp 0.2s ease;
        }
        .modal-box.modal-large { max-width: 520px; }
        @keyframes slideUp { from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);} }
        .modal-hd {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 20px; padding-bottom: 14px; border-bottom: 2px solid #F3F4F6;
        }
        .modal-hd-title {
            font-size: 16px; font-weight: 800; color: #111111;
            display: flex; align-items: center; gap: 8px;
        }
        .modal-hd-title::before {
            content: ''; display: block; width: 4px; height: 18px;
            background: #8B0000; border-radius: 99px;
        }
        .modal-x {
            width: 30px; height: 30px; border-radius: 8px;
            background: #F3F4F6; border: none; cursor: pointer;
            font-size: 15px; color: #6B7280;
            display: flex; align-items: center; justify-content: center;
        }
        .modal-x:hover { background: #FEF2F2; color: #8B0000; }

        /* ── Péremption ── */
        .peremption-card {
            border: 2px solid #E5E7EB; border-radius: 14px;
            padding: 16px 18px; margin-bottom: 10px;
            display: flex; align-items: center; gap: 14px;
            background: #FFFFFF; transition: all 0.2s;
        }
        .peremption-card.urgent  { border-color: #8B0000; background: #FFF8F8; }
        .peremption-card.bientot { border-color: #FCA5A5; background: #FEF2F2; }
        .peremption-card.semaine { border-color: #FCD34D; background: #FFFBEB; }
        .peremption-badge {
            width: 50px; height: 50px; border-radius: 12px;
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; flex-shrink: 0; font-weight: 800;
        }
        .peremption-badge.urgent  { background: #8B0000; color: #fff; }
        .peremption-badge.bientot { background: #FEE2E2; color: #991B1B; }
        .peremption-badge.semaine { background: #FEF3C7; color: #92400E; }
        .peremption-badge .jours  { font-size: 20px; line-height: 1; }
        .peremption-badge .label  { font-size: 9px; font-weight: 600; opacity: 0.8; }

        /* ── Banner état stock ── */
        .stock-status-banner {
            display: flex; gap: 10px; flex-wrap: wrap;
            background: #F9FAFB; border: 1.5px solid #E5E7EB;
            border-radius: 12px; padding: 14px 16px;
            margin-bottom: 18px; align-items: center;
            font-size: 13px; color: #374151;
        }
        .stock-status-dot {
            display: inline-block; width: 8px; height: 8px;
            border-radius: 50%; margin-right: 4px;
        }

        /* ── Bouton info règles ── */
        .btn-info-regles {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border-radius: 50%;
            background: #F3F4F6; border: 1.5px solid #E5E7EB;
            color: #6B7280; font-size: 14px; font-weight: 800;
            cursor: pointer; font-family: inherit; transition: all 0.15s; flex-shrink: 0;
        }
        .btn-info-regles:hover { background: #EFF6FF; border-color: #93C5FD; color: #1D4ED8; }

        .regle-item { display: flex; gap: 12px; align-items: flex-start; padding: 12px 14px; border-radius: 10px; margin-bottom: 8px; }
        .regle-icon  { font-size: 20px; flex-shrink: 0; }
        .regle-title { font-size: 13px; font-weight: 700; color: #111111; margin-bottom: 2px; }
        .regle-desc  { font-size: 12px; color: #6B7280; line-height: 1.4; }
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
        <h1>Alertes de stock</h1>
        <p>Surveillance automatique du stock et des dates de péremption des lots.</p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo $success; ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur); ?></div><?php endif; ?>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">🔔</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Alertes actives</div>
                <div class="stat-mini-number <?php echo $nb_actives_total > 0 ? 'alert' : ''; ?>"><?php echo $nb_actives_total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🔴</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Ruptures</div>
                <div class="stat-mini-number <?php echo $nb_rupture_total > 0 ? 'alert' : ''; ?>"><?php echo $nb_rupture_total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🟠</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Critiques</div>
                <div class="stat-mini-number <?php echo $nb_critique_total > 0 ? 'alert' : ''; ?>"><?php echo $nb_critique_total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">✅</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Traitées</div>
                <div class="stat-mini-number"><?php echo $nb_traitees; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">⏰</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Péremption</div>
                <div class="stat-mini-number <?php echo $nb_peremption > 0 ? 'alert' : ''; ?>"><?php echo $nb_peremption; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ ONGLETS PRINCIPAUX ══ -->
    <div class="main-tabs">
        <button class="main-tab-btn active" onclick="switchMainTab('tab-stock', this)">
            🔔 Alertes de stock
            <?php if ($nb_actives_total > 0): ?>
                <span class="tab-badge"><?php echo $nb_actives_total; ?></span>
            <?php endif; ?>
        </button>
        <button class="main-tab-btn" onclick="switchMainTab('tab-peremption', this)">
            ⏰ Péremption des lots
            <?php if ($nb_peremption > 0): ?>
                <span class="tab-badge-warn"><?php echo $nb_peremption; ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- ══ ONGLET 1 : ALERTES DE STOCK ══ -->
    <div id="tab-stock" class="main-tab-content active">

        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:14px; font-weight:600; color:#374151;">Résumé de l'état du stock</span>
                <button class="btn-info-regles" onclick="ouvrirModal('modalRegles')" title="Voir les règles d'alerte">ℹ</button>
            </div>
            <?php if ($nb_actives > 0): ?>
            <form method="POST" style="margin:0;">
                <button type="submit" name="tout_traiter"
                        style="background:none; border:1.5px solid #8B0000; border-radius:8px; padding:8px 16px; font-size:13px; font-weight:600; color:#8B0000; cursor:pointer; font-family:inherit;"
                        onclick="return confirm('Marquer toutes les alertes enregistrées comme traitées ?')">
                    ✓ Tout marquer comme traité (<?php echo $nb_actives; ?>)
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Banner état temps réel -->
        <?php if ($nb_rupture_reel > 0 || $nb_critique_reel > 0 || $nb_avertissement_reel > 0): ?>
        <div class="stock-status-banner">
            <span>📊 État actuel du stock :</span>
            <?php if ($nb_rupture_reel > 0): ?>
                <span><span class="stock-status-dot" style="background:#8B0000;"></span>
                <strong><?php echo $nb_rupture_reel; ?></strong> groupe(s) en rupture</span>
            <?php endif; ?>
            <?php if ($nb_critique_reel > 0): ?>
                <span><span class="stock-status-dot" style="background:#EF4444;"></span>
                <strong><?php echo $nb_critique_reel; ?></strong> groupe(s) critique(s)</span>
            <?php endif; ?>
            <?php if ($nb_avertissement_reel > 0): ?>
                <span><span class="stock-status-dot" style="background:#F59E0B;"></span>
                <strong><?php echo $nb_avertissement_reel; ?></strong> groupe(s) en avertissement</span>
            <?php endif; ?>
            <a href="stock.php" style="margin-left:auto; font-size:12px; color:#8B0000; font-weight:600; text-decoration:none;">
                Voir le stock →
            </a>
        </div>
        <?php endif; ?>

        <!-- Filtres -->
        <div class="filter-tabs">
            <button class="filter-btn active" onclick="filtrer('tout', this)">
                Toutes (<?php echo count($toutes_alertes); ?>)
            </button>
            <button class="filter-btn" onclick="filtrer('rupture', this)">
                🔴 Rupture (<?php echo $nb_rupture_total; ?>)
            </button>
            <button class="filter-btn" onclick="filtrer('critique', this)">
                🟠 Critique (<?php echo $nb_critique_total; ?>)
            </button>
            <button class="filter-btn" onclick="filtrer('avertissement', this)">
                🟡 Avertissement (<?php echo $nb_avert_total; ?>)
            </button>
            <button class="filter-btn" onclick="filtrer('traitee', this)">
                ✅ Traitées (<?php echo $nb_traitees; ?>)
            </button>
        </div>

        <!-- Cartes alertes -->
        <?php if ($toutes_alertes): ?>
            <?php foreach ($toutes_alertes as $a):
                $est_traitee   = (int)$a['traitee'] === 1;
                $est_virtuelle = !empty($a['virtuelle']);
                $type          = $a['type_alerte'];
                $card_cls      = $est_traitee ? 'traitee' : $type;
                if ($est_virtuelle) $card_cls .= ' virtuelle';

                $icon_map = [
                    'rupture'       => ['🔴', 'icon-rupture'],
                    'critique'      => ['🟠', 'icon-critique'],
                    'avertissement' => ['🟡', 'icon-avertissement'],
                ];
                $icon     = $est_traitee ? '✅' : ($icon_map[$type][0] ?? '⚠️');
                $icon_cls = $est_traitee ? 'icon-traitee' : ($icon_map[$type][1] ?? '');

                $type_label = [
                    'rupture'       => 'Rupture totale',
                    'critique'      => 'Critique',
                    'avertissement' => 'Avertissement',
                ][$type] ?? $type;
                $type_css = $est_traitee ? 'type-traitee' : "type-{$type}";

                $statut_d = $a['statut_demande'] ?? 'en_attente';
                $cls_d = match($statut_d) { 'acceptée' => 'acceptee', 'refusée' => 'refusee', default => '' };
                $txt_d = match($statut_d) {
                    'acceptée' => '✓ Demande acceptée par la banque',
                    'refusée'  => '✗ Demande refusée par la banque',
                    default    => '⏳ Demande en attente auprès de la banque'
                };

                $peut_commander = !$est_traitee && $type === 'avertissement' && !(int)($a['demande_auto'] ?? 0);
            ?>
            <div class="alerte-card <?php echo $card_cls; ?>"
                 data-type="<?php echo $est_traitee ? 'traitee' : $type; ?>">

                <div class="alerte-icon <?php echo $icon_cls; ?>"><?php echo $icon; ?></div>

                <div class="alerte-body">
                    <div class="alerte-top">
                        <span class="alerte-titre">
                            Groupe <span class="badge badge-groupe" style="font-size:12px;"><?php echo htmlspecialchars($a['groupe']); ?></span>
                        </span>
                        <span class="type-badge <?php echo $type_css; ?>">
                            <?php echo $est_traitee ? '✅ Traitée' : $type_label; ?>
                        </span>
                        <?php if ($est_virtuelle): ?>
                            <span class="badge-virtuelle">📡 Détecté en temps réel</span>
                        <?php endif; ?>
                    </div>

                    <div class="alerte-details">
                        <span>📦 Stock actuel : <strong><?php echo (int)($a['stock_actuel'] ?? $a['quantite_actuelle']); ?></strong> poch.</span>
                        <?php if (!$est_virtuelle): ?>
                            <span>📊 Au moment de l'alerte : <strong><?php echo (int)$a['quantite_actuelle']; ?></strong> poch.</span>
                        <?php endif; ?>
                        <span>⚠️ Seuil : <strong><?php echo (int)$a['seuil_alerte']; ?></strong> poch.</span>
                    </div>

                    <?php if (!$est_virtuelle && ($a['demande_auto'] ?? 0) && $a['id_demande_auto']): ?>
                        <div class="demande-auto-info <?php echo $cls_d; ?>">
                            🏦 <?php echo $txt_d; ?>
                            — <?php echo (int)$a['quantite_demandee']; ?> poch.
                            <?php if ($a['nom_banque']): ?>
                                à <strong><?php echo htmlspecialchars($a['nom_banque']); ?></strong>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($peut_commander): ?>
                        <button class="btn-commander"
                                onclick="ouvrirCommander(<?php echo $a['id_groupe']; ?>, '<?php echo htmlspecialchars($a['groupe'], ENT_QUOTES); ?>', <?php echo max(1, (int)($a['seuil_actuel'] ?? $a['seuil_alerte']) * 2); ?>)">
                            ➕ Commander à la banque
                        </button>
                    <?php elseif ($est_virtuelle && in_array($type, ['rupture', 'critique'])): ?>
                        <div style="font-size:12px; color:#8B0000; margin-top:4px; font-weight:600;">
                            ⚡ Alerte non encore enregistrée — réapprovisionner en priorité.
                        </div>
                    <?php elseif (!$est_virtuelle): ?>
                        <div style="font-size:12px; color:#9CA3AF; margin-top:4px;">
                            ℹ️ Aucune demande automatique pour cette alerte.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="alerte-actions">
                    <div class="alerte-date">
                        <?php echo date('d/m/Y', strtotime($a['date_alerte'])); ?><br>
                        <span style="color:#9CA3AF;"><?php echo date('H:i', strtotime($a['date_alerte'])); ?></span>
                    </div>
                    <?php if (!$est_traitee && !$est_virtuelle): ?>
                        <a href="alertes.php?traiter=<?php echo $a['id_alerte']; ?>"
                           class="btn-traiter-alerte"
                           onclick="return confirm('Marquer cette alerte comme traitée ?')">
                            ✓ Traiter
                        </a>
                    <?php elseif ($est_virtuelle): ?>
                        <a href="demandes.php" class="btn-traiter-alerte" style="background:#374151;">
                            📤 Demander
                        </a>
                    <?php else: ?>
                        <span style="font-size:11px; color:#9CA3AF;">Traitée</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center; padding:60px; color:#9CA3AF;">
                <div style="font-size:48px; margin-bottom:16px;">✅</div>
                <div style="font-size:16px; font-weight:600; color:#374151;">Aucune alerte</div>
                <div style="font-size:14px; margin-top:6px;">Le stock de votre dépôt est sous contrôle.</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ══ ONGLET 2 : PÉREMPTION DES LOTS ══ -->
    <div id="tab-peremption" class="main-tab-content">
        <div class="section">
            <div class="section-header">
                <div class="section-title">Lots expirant dans les 7 prochains jours</div>
                <span class="cnt-badge"><?php echo $nb_peremption; ?> lot(s)</span>
            </div>

            <?php if ($lots_peremption): ?>
                <div style="background:#FFFBEB; border:1.5px solid #FCD34D; border-radius:10px; padding:12px 16px; margin-bottom:18px; font-size:13px; color:#92400E; font-weight:500;">
                    ⚠️ Ces lots arrivent à expiration. Utilisez-les en priorité ou marquez-les comme expirés dans la page
                    <a href="lots.php" style="color:#8B0000; font-weight:700;">Suivi des Lots</a>.
                </div>

                <?php foreach ($lots_peremption as $lot):
                    $jours = (int)$lot['jours_restants'];
                    if ($jours <= 0)      { $card_cls = 'urgent';  $badge_cls = 'urgent';  $jours_txt = $jours === 0 ? "Auj." : "Expiré"; }
                    elseif ($jours <= 2)  { $card_cls = 'bientot'; $badge_cls = 'bientot'; $jours_txt = $jours . "j"; }
                    else                  { $card_cls = 'semaine'; $badge_cls = 'semaine'; $jours_txt = $jours . "j"; }
                ?>
                <div class="peremption-card <?php echo $card_cls; ?>">
                    <div class="peremption-badge <?php echo $badge_cls; ?>">
                        <div class="jours"><?php echo $jours_txt; ?></div>
                        <div class="label"><?php echo $jours > 0 ? 'restants' : ''; ?></div>
                    </div>
                    <div style="flex:1;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:5px;">
                            <span class="badge badge-groupe"><?php echo htmlspecialchars($lot['groupe']); ?></span>
                            <strong style="font-size:15px;"><?php echo (int)$lot['quantite']; ?> pochette(s)</strong>
                        </div>
                        <div style="font-size:12px; color:#6B7280; display:flex; gap:16px; flex-wrap:wrap;">
                            <span>📅 Expire le : <strong><?php echo date('d/m/Y', strtotime($lot['date_expiration'])); ?></strong></span>
                        </div>
                    </div>
                    <a href="lots.php" style="background:#8B0000; color:#fff; border-radius:8px; padding:7px 14px; font-size:12px; font-weight:700; text-decoration:none; white-space:nowrap; display:flex; align-items:center; gap:5px;">
                        Gérer →
                    </a>
                </div>
                <?php endforeach; ?>

            <?php else: ?>
                <div style="text-align:center; padding:50px; color:#9CA3AF;">
                    <div style="font-size:40px; margin-bottom:14px;">✅</div>
                    <div style="font-size:15px; font-weight:600; color:#374151;">Aucun lot en péremption proche</div>
                    <div style="font-size:13px; margin-top:6px;">Tous les lots sont valables pour plus de 7 jours.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ══ MODAL COMMANDER ══ -->
<div class="modal-overlay" id="modalCommander" onclick="fermerModalOverlay(event, 'modalCommander')">
    <div class="modal-box">
        <div class="modal-hd">
            <div class="modal-hd-title">Commander à la banque</div>
            <button class="modal-x" onclick="fermerModal('modalCommander')">✕</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="id_groupe_cmd" id="modal_id_groupe"/>
            <div style="background:#FFFBEB; border:1.5px solid #FCD34D; border-radius:10px; padding:12px 14px; margin-bottom:18px; font-size:13px; color:#92400E; line-height:1.5;">
                🟡 Stock faible pour le groupe <strong id="modal_groupe_nom"></strong>.
                Envoyez une demande manuelle à la banque principale.
            </div>
            <div class="form-group">
                <label>Quantité à commander (pochettes) *</label>
                <input type="number" name="quantite_cmd" id="modal_quantite"
                       min="1" max="500" required
                       style="font-size:20px; font-weight:700; text-align:center;"/>
            </div>
            <button type="submit" name="commander" class="btn-submit">Envoyer la demande</button>
        </form>
    </div>
</div>

<!-- ══ MODAL RÈGLES ══ -->
<div class="modal-overlay" id="modalRegles" onclick="fermerModalOverlay(event, 'modalRegles')">
    <div class="modal-box modal-large">
        <div class="modal-hd">
            <div class="modal-hd-title">Règles d'alerte</div>
            <button class="modal-x" onclick="fermerModal('modalRegles')">✕</button>
        </div>
        <div style="margin-bottom:14px; font-size:13px; color:#6B7280; line-height:1.5;">
            Les alertes sont déclenchées automatiquement selon l'état du stock de chaque groupe sanguin.
        </div>
        <div class="regle-item" style="background:#FFF8F8; border:1px solid #8B0000;">
            <div class="regle-icon">🔴</div>
            <div>
                <div class="regle-title">Rupture totale</div>
                <div class="regle-desc">Stock = 0 pochette. Demande urgente automatiquement envoyée à la banque.</div>
            </div>
        </div>
        <div class="regle-item" style="background:#FEF2F2; border:1px solid #FCA5A5;">
            <div class="regle-icon">🟠</div>
            <div>
                <div class="regle-title">Critique</div>
                <div class="regle-desc">0 &lt; Stock ≤ seuil. Niveau dangereux. Demande automatique envoyée.</div>
            </div>
        </div>
        <div class="regle-item" style="background:#FFFBEB; border:1px solid #FCD34D;">
            <div class="regle-icon">🟡</div>
            <div>
                <div class="regle-title">Avertissement</div>
                <div class="regle-desc">seuil &lt; Stock ≤ seuil × 1,5. Stock bas. Bouton "Commander" disponible.</div>
            </div>
        </div>
        <div class="regle-item" style="background:#F0FDF4; border:1px solid #BBF7D0;">
            <div class="regle-icon">🟢</div>
            <div>
                <div class="regle-title">OK — Aucune alerte</div>
                <div class="regle-desc">Stock > seuil × 1,5. Le stock est suffisant.</div>
            </div>
        </div>
        <div style="margin-top:14px; font-size:12px; color:#6B7280; line-height:1.6; background:#F9FAFB; border-radius:10px; padding:12px;">
            💡 Les alertes avec le badge <span style="background:#F0F9FF; color:#0369A1; border:1px solid #BAE6FD; padding:1px 6px; border-radius:999px; font-size:11px; font-weight:600;">📡 Détecté en temps réel</span>
            signalent des groupes sous seuil sans alerte formelle enregistrée.
            Modifiez les seuils dans <a href="stock.php" style="color:#8B0000; font-weight:600;">Stock Interne</a>.
        </div>
    </div>
</div>

<script>
function filtrer(type, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.alerte-card').forEach(card => {
        card.style.display = (type === 'tout' || card.dataset.type === type) ? 'flex' : 'none';
    });
}

function switchMainTab(tabId, btn) {
    document.querySelectorAll('.main-tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.main-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btn.classList.add('active');
}

function ouvrirModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function fermerModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}
function fermerModalOverlay(e, id) {
    if (e.target === document.getElementById(id)) fermerModal(id);
}

function ouvrirCommander(idGroupe, nomGroupe, qteDefaut) {
    document.getElementById('modal_id_groupe').value        = idGroupe;
    document.getElementById('modal_groupe_nom').textContent = nomGroupe;
    document.getElementById('modal_quantite').value         = qteDefaut;
    ouvrirModal('modalCommander');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        fermerModal('modalCommander');
        fermerModal('modalRegles');
    }
});
</script>

</body>
</html>