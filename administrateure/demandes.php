<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// ════════════════════════════════════════════════════════
// Filtres (statut + type)
// ════════════════════════════════════════════════════════
$filtre_statut = $_GET['statut'] ?? 'tous';
$filtre_type   = $_GET['type']   ?? 'tous';

$conditions = [];
$params = [];

if (in_array($filtre_statut, ['en_attente', 'acceptée', 'refusée'])) {
    $conditions[] = "d.statut = ?";
    $params[] = $filtre_statut;
}
if (in_array($filtre_type, ['interne', 'externe'])) {
    $conditions[] = "d.type_demande = ?";
    $params[] = $filtre_type;
}

$where_clause = !empty($conditions) ? " WHERE " . implode(' AND ', $conditions) : "";

// ════════════════════════════════════════════════════════
// CHARGEMENT DES DEMANDES
// (avec jointure sur sous_banque si applicable)
// ════════════════════════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT d.*,
           h.nom AS nom_hopital,
           b.nom AS nom_banque,
           g.libelle AS groupe,
           sb.nom AS nom_sous_banque
    FROM demande d
    JOIN hopital h         ON h.id_hopital = d.id_hopital
    JOIN banque_de_sang b  ON b.id_banque  = d.id_banque
    JOIN groupe_sanguin g  ON g.id_groupe  = d.id_groupe
    LEFT JOIN sous_banque sb ON sb.id_sous_banque = d.id_sous_banque
    $where_clause
    ORDER BY d.date_demande DESC
");
$stmt->execute($params);
$demandes = $stmt->fetchAll();

// ════════════════════════════════════════════════════════
// STATISTIQUES (sur le total, pas sur le filtre)
// ════════════════════════════════════════════════════════
$nb_total     = $pdo->query("SELECT COUNT(*) FROM demande")->fetchColumn();
$nb_acceptees = $pdo->query("SELECT COUNT(*) FROM demande WHERE statut = 'acceptée'")->fetchColumn();
$nb_attente   = $pdo->query("SELECT COUNT(*) FROM demande WHERE statut = 'en_attente'")->fetchColumn();
$nb_refusees  = $pdo->query("SELECT COUNT(*) FROM demande WHERE statut = 'refusée'")->fetchColumn();

// Quantité totale acceptée
$qte_totale = $pdo->query("
    SELECT COALESCE(SUM(quantite_demandee), 0) FROM demande WHERE statut = 'acceptée'
")->fetchColumn();

// Stats par type
$nb_internes = $pdo->query("SELECT COUNT(*) FROM demande WHERE type_demande = 'interne'")->fetchColumn();
$nb_externes = $pdo->query("SELECT COUNT(*) FROM demande WHERE type_demande = 'externe'")->fetchColumn();

$page_active = 'demandes';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demandes — Admin E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* ── Filtres ── */
        .filtres-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
        }
        .filtre-label {
            display: flex;
            align-items: center;
            font-size: 11px;
            font-weight: 800;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-right: 4px;
        }
        .filtre-btn {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            color: #6B7280;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .filtre-btn:hover {
            background: #FEF2F2;
            color: #8B0000;
            border-color: #8B0000;
        }
        .filtre-btn.active {
            background: #8B0000;
            color: #FFFFFF;
            border-color: #8B0000;
        }
        .filtre-count {
            background: rgba(255,255,255,0.25);
            padding: 1px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
        }
        .filtre-btn:not(.active) .filtre-count {
            background: #F3F4F6;
            color: #6B7280;
        }

        /* Badge type demande */
        .badge-interne {
            background: #EEF2FF;
            color: #4338CA;
            border: 1.5px solid #C7D2FE;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            white-space: nowrap;
            display: inline-block;
        }
        .badge-externe {
            background: #FDF4FF;
            color: #86198F;
            border: 1.5px solid #F5D0FE;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
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
        <h1>Liste des demandes</h1>
        <p>Toutes les demandes de sang émises par les hôpitaux et sous-banques.</p>
    </div>

    <!-- ══ STATISTIQUES COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">📋</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total demandes</div>
                <div class="stat-mini-number"><?php echo $nb_total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">✅</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Acceptées</div>
                <div class="stat-mini-number"><?php echo $nb_acceptees; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">⏳</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">En attente</div>
                <div class="stat-mini-number <?php echo $nb_attente > 0 ? 'alert' : ''; ?>"><?php echo $nb_attente; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">🩸</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Pochettes livrées</div>
                <div class="stat-mini-number"><?php echo (int)$qte_totale; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ TABLEAU DES DEMANDES ══ -->
    <div class="section">

        <div class="section-header">
            <div class="section-title">Demandes enregistrées</div>
            <span class="cnt-badge"><?php echo count($demandes); ?> demande(s) affichée(s)</span>
        </div>

        <!-- ── Filtres par STATUT ── -->
        <div class="filtres-bar">
            <span class="filtre-label">Statut :</span>
            <a href="demandes.php<?php echo $filtre_type !== 'tous' ? '?type='.$filtre_type : ''; ?>"
               class="filtre-btn <?php echo ($filtre_statut === 'tous') ? 'active' : ''; ?>">
                📋 Tous <span class="filtre-count"><?php echo $nb_total; ?></span>
            </a>
            <a href="demandes.php?statut=en_attente<?php echo $filtre_type !== 'tous' ? '&type='.$filtre_type : ''; ?>"
               class="filtre-btn <?php echo ($filtre_statut === 'en_attente') ? 'active' : ''; ?>">
                ⏳ En attente <span class="filtre-count"><?php echo $nb_attente; ?></span>
            </a>
            <a href="demandes.php?statut=acceptée<?php echo $filtre_type !== 'tous' ? '&type='.$filtre_type : ''; ?>"
               class="filtre-btn <?php echo ($filtre_statut === 'acceptée') ? 'active' : ''; ?>">
                ✅ Acceptées <span class="filtre-count"><?php echo $nb_acceptees; ?></span>
            </a>
            <a href="demandes.php?statut=refusée<?php echo $filtre_type !== 'tous' ? '&type='.$filtre_type : ''; ?>"
               class="filtre-btn <?php echo ($filtre_statut === 'refusée') ? 'active' : ''; ?>">
                ❌ Refusées <span class="filtre-count"><?php echo $nb_refusees; ?></span>
            </a>
        </div>

        <!-- ── Filtres par TYPE ── -->
        <div class="filtres-bar">
            <span class="filtre-label">Type :</span>
            <a href="demandes.php<?php echo $filtre_statut !== 'tous' ? '?statut='.$filtre_statut : ''; ?>"
               class="filtre-btn <?php echo ($filtre_type === 'tous') ? 'active' : ''; ?>">
                🔁 Tous types <span class="filtre-count"><?php echo $nb_total; ?></span>
            </a>
            <a href="demandes.php?type=interne<?php echo $filtre_statut !== 'tous' ? '&statut='.$filtre_statut : ''; ?>"
               class="filtre-btn <?php echo ($filtre_type === 'interne') ? 'active' : ''; ?>">
                🏪 Interne <span class="filtre-count"><?php echo $nb_internes; ?></span>
            </a>
            <a href="demandes.php?type=externe<?php echo $filtre_statut !== 'tous' ? '&statut='.$filtre_statut : ''; ?>"
               class="filtre-btn <?php echo ($filtre_type === 'externe') ? 'active' : ''; ?>">
                🏦 Externe <span class="filtre-count"><?php echo $nb_externes; ?></span>
            </a>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Hôpital</th>
                        <th>Sous-banque</th>
                        <th>Banque</th>
                        <th>Type</th>
                        <th>Groupe</th>
                        <th>Quantité</th>
                        <th>Date demande</th>
                        <th>Date réponse</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($demandes): ?>
                        <?php foreach ($demandes as $d): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($d['nom_hopital']); ?></strong></td>
                            <td>
                                <?php if (!empty($d['nom_sous_banque'])): ?>
                                    <span style="font-weight:600; color:#444;">🏪 <?php echo htmlspecialchars($d['nom_sous_banque']); ?></span>
                                <?php else: ?>
                                    <span style="color:#9CA3AF;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($d['nom_banque']); ?></td>
                            <td>
                                <?php if ($d['type_demande'] === 'interne'): ?>
                                    <span class="badge-interne">🏪 Interne</span>
                                <?php else: ?>
                                    <span class="badge-externe">🏦 Externe</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                            <td><strong><?php echo (int)$d['quantite_demandee']; ?></strong> pochette(s)</td>
                            <td><?php echo date('d/m/Y', strtotime($d['date_demande'])); ?></td>
                            <td><?php echo $d['date_reponse'] ? date('d/m/Y', strtotime($d['date_reponse'])) : '—'; ?></td>
                            <td>
                                <?php if ($d['statut'] === 'en_attente'): ?>
                                    <span class="badge badge-attente">⏳ En attente</span>
                                <?php elseif ($d['statut'] === 'acceptée'): ?>
                                    <span class="badge badge-acceptee">✅ Acceptée</span>
                                <?php else: ?>
                                    <span class="badge badge-refusee">❌ Refusée</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="vide">
                            <?php if ($filtre_statut !== 'tous' || $filtre_type !== 'tous'): ?>
                                Aucune demande ne correspond aux filtres sélectionnés.
                            <?php else: ?>
                                Aucune demande enregistrée pour le moment.
                            <?php endif; ?>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>