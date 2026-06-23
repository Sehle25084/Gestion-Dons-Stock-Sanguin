<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// ════════════════════════════════════════════════════════
// Filtre par statut (optionnel via URL ?statut=...)
// ════════════════════════════════════════════════════════
$filtre_statut = $_GET['statut'] ?? 'tous';
$where_clause = "";
$params = [];

if (in_array($filtre_statut, ['en_attente', 'accepté', 'refusé'])) {
    $where_clause = " WHERE don.statut = ?";
    $params[] = $filtre_statut;
}

// ════════════════════════════════════════════════════════
// CHARGEMENT DES DONS
// ════════════════════════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT don.*, b.nom AS nom_banque, g.libelle AS groupe, d.NNI
    FROM don
    JOIN banque_de_sang b ON b.id_banque = don.id_banque
    JOIN groupe_sanguin g ON g.id_groupe = don.id_groupe
    LEFT JOIN donneur d ON d.id_donneur = don.id_donneur
    $where_clause
    ORDER BY don.date_don DESC
");
$stmt->execute($params);
$dons = $stmt->fetchAll();

// ✔ CORRECTION : N+1 query supprimée
//   Avant : pour chaque don, une requête au registre national (1000 dons = 1000 requêtes)
//   Maintenant : on récupère TOUS les noms en UNE seule requête `WHERE NNI IN (...)`
$citoyens_par_nni = [];
$nnis = array_filter(array_unique(array_column($dons, 'NNI')));
if (!empty($nnis)) {
    $placeholders = implode(',', array_fill(0, count($nnis), '?'));
    $stmtCit = $pdo_registre->prepare("SELECT NNI, nom, prenom FROM citoyen WHERE NNI IN ($placeholders)");
    $stmtCit->execute(array_values($nnis));
    while ($row = $stmtCit->fetch()) {
        $citoyens_par_nni[$row['NNI']] = $row;
    }
}

// ════════════════════════════════════════════════════════
// STATISTIQUES (toujours sur le total, pas sur le filtre)
// ════════════════════════════════════════════════════════
$nb_total     = $pdo->query("SELECT COUNT(*) FROM don")->fetchColumn();
$nb_acceptes  = $pdo->query("SELECT COUNT(*) FROM don WHERE statut = 'accepté'")->fetchColumn();
$nb_attente   = $pdo->query("SELECT COUNT(*) FROM don WHERE statut = 'en_attente'")->fetchColumn();
$nb_refuses   = $pdo->query("SELECT COUNT(*) FROM don WHERE statut = 'refusé'")->fetchColumn();

// Quantité totale acceptée (pochettes vraiment ajoutées au stock)
$qte_totale = $pdo->query("
    SELECT COALESCE(SUM(quantite), 0) FROM don WHERE statut = 'accepté'
")->fetchColumn();

$page_active = 'dons';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dons — Admin E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* ── Filtres par statut ── */
        .filtres-statut {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .filtre-btn {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            color: #6B7280;
            padding: 8px 16px;
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
        <h1>Liste des dons</h1>
        <p>Tous les dons de sang effectués sur la plateforme.</p>
    </div>

    <!-- ══ STATISTIQUES COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">💉</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total dons</div>
                <div class="stat-mini-number"><?php echo $nb_total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">✅</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Acceptés</div>
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
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">🩸</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Pochettes collectées</div>
                <div class="stat-mini-number"><?php echo (int)$qte_totale; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ TABLEAU DES DONS ══ -->
    <div class="section">

        <div class="section-header">
            <div class="section-title">Dons enregistrés</div>
            <span class="cnt-badge"><?php echo count($dons); ?> don(s) affiché(s)</span>
        </div>

        <!-- ── Filtres par statut ── -->
        <div class="filtres-statut">
            <a href="dons.php" class="filtre-btn <?php echo ($filtre_statut === 'tous') ? 'active' : ''; ?>">
                📋 Tous <span class="filtre-count"><?php echo $nb_total; ?></span>
            </a>
            <a href="dons.php?statut=en_attente" class="filtre-btn <?php echo ($filtre_statut === 'en_attente') ? 'active' : ''; ?>">
                ⏳ En attente <span class="filtre-count"><?php echo $nb_attente; ?></span>
            </a>
            <a href="dons.php?statut=accepté" class="filtre-btn <?php echo ($filtre_statut === 'accepté') ? 'active' : ''; ?>">
                ✅ Acceptés <span class="filtre-count"><?php echo $nb_acceptes; ?></span>
            </a>
            <a href="dons.php?statut=refusé" class="filtre-btn <?php echo ($filtre_statut === 'refusé') ? 'active' : ''; ?>">
                ❌ Refusés <span class="filtre-count"><?php echo $nb_refuses; ?></span>
            </a>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Donneur</th>
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
                        <?php
                        // ✔ CORRECTION : on lit depuis le tableau pré-chargé (plus de requête dans la boucle)
                        $citoyen = !empty($d['NNI']) ? ($citoyens_par_nni[$d['NNI']] ?? null) : null;
                        ?>
                        <tr>
                            <td>
                                <strong>Donneur #<?php echo $d['id_donneur']; ?></strong>
                                <?php if ($citoyen): ?>
                                <br><small style="color:#666;font-weight:500;">
                                    <?php echo htmlspecialchars($citoyen['prenom'] . ' ' . $citoyen['nom']); ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($d['nom_banque']); ?></td>
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
                        <tr><td colspan="6" class="vide">
                            <?php if ($filtre_statut !== 'tous'): ?>
                                Aucun don avec le statut « <?php echo htmlspecialchars($filtre_statut); ?> » pour le moment.
                            <?php else: ?>
                                Aucun don enregistré pour le moment.
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