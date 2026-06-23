<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// ════════════════════════════════════════════════════════
// Filtres
// ════════════════════════════════════════════════════════
$filtre_etablissement = $_GET['etabl']  ?? 'tous';    // tous / banques / sous_banques
$filtre_alerte        = $_GET['alerte'] ?? 'tous';    // tous / critiques / expires

// ════════════════════════════════════════════════════════
// CHARGEMENT STOCK BANQUES (table `stock`)
// ════════════════════════════════════════════════════════
$stocks_banques = $pdo->query("
    SELECT s.*, b.nom AS nom_banque, g.libelle AS groupe
    FROM stock s
    JOIN banque_de_sang b ON b.id_banque = s.id_banque
    JOIN groupe_sanguin g ON g.id_groupe = s.id_groupe
    ORDER BY b.nom, g.libelle
")->fetchAll();

// ════════════════════════════════════════════════════════
// CHARGEMENT STOCK SOUS-BANQUES (table `stock_sous_banque`)
// ════════════════════════════════════════════════════════
$stocks_sb = [];
try {
    $stocks_sb = $pdo->query("
        SELECT s.*, sb.nom AS nom_sous_banque,
               h.nom AS nom_hopital, g.libelle AS groupe
        FROM stock_sous_banque s
        JOIN sous_banque sb ON sb.id_sous_banque = s.id_sous_banque
        LEFT JOIN hopital h ON h.id_hopital = sb.id_hopital
        JOIN groupe_sanguin g ON g.id_groupe = s.id_groupe
        ORDER BY sb.nom, g.libelle
    ")->fetchAll();
} catch (Exception $e) {}

// ════════════════════════════════════════════════════════
// FUSION et FILTRAGE
// ════════════════════════════════════════════════════════
$lignes = [];

if ($filtre_etablissement !== 'sous_banques') {
    foreach ($stocks_banques as $s) {
        $expire     = strtotime($s['date_expiration']) < time();
        $critique   = $s['quantite_disponible'] <= ($s['seuil_alerte'] ?? 2);
        $lignes[] = [
            'type'        => 'banque',
            'etabl_nom'   => $s['nom_banque'],
            'etabl_extra' => '',
            'groupe'      => $s['groupe'],
            'quantite'    => (float)$s['quantite_disponible'],
            'seuil'       => (float)($s['seuil_alerte'] ?? 2),
            'date_maj'    => $s['date_mise_a_jour'],
            'date_exp'    => $s['date_expiration'],
            'critique'    => $critique,
            'expire'      => $expire,
        ];
    }
}

if ($filtre_etablissement !== 'banques') {
    foreach ($stocks_sb as $s) {
        // ✔ CORRECTION : la colonne `seuil_alerte` EXISTE bien dans stock_sous_banque
        //   Avant : hardcodé à 2 → si une sous-banque avait fixé son seuil à 10, l'admin
        //           ne voyait l'alerte qu'à partir de 2 pochettes (trop tard !)
        $seuil_sb = (float)($s['seuil_alerte'] ?? 5);
        $critique = $s['quantite_disponible'] <= $seuil_sb;
        $lignes[] = [
            'type'        => 'sous_banque',
            'etabl_nom'   => $s['nom_sous_banque'],
            'etabl_extra' => $s['nom_hopital'] ?? '',
            'groupe'      => $s['groupe'],
            'quantite'    => (float)$s['quantite_disponible'],
            'seuil'       => $seuil_sb,
            'date_maj'    => $s['date_mise_a_jour'],
            'date_exp'    => null,
            'critique'    => $critique,
            'expire'      => false,
        ];
    }
}

// Filtre alerte
if ($filtre_alerte === 'critiques') {
    $lignes = array_filter($lignes, function($l) { return $l['critique']; });
} elseif ($filtre_alerte === 'expires') {
    $lignes = array_filter($lignes, function($l) { return $l['expire']; });
}

// ════════════════════════════════════════════════════════
// STATISTIQUES GLOBALES
// ════════════════════════════════════════════════════════
$nb_total_banques = count($stocks_banques);
$nb_total_sb     = count($stocks_sb);

// Totaux pochettes
$qte_banques = 0;
$qte_sb      = 0;
$nb_critiques = 0;
$nb_expires   = 0;

foreach ($stocks_banques as $s) {
    $qte_banques += (float)$s['quantite_disponible'];
    if ($s['quantite_disponible'] <= ($s['seuil_alerte'] ?? 2)) $nb_critiques++;
    if (strtotime($s['date_expiration']) < time()) $nb_expires++;
}
foreach ($stocks_sb as $s) {
    $qte_sb += (float)$s['quantite_disponible'];
    // ✔ CORRECTION : utiliser le vrai seuil_alerte de la sous-banque (au lieu de 2 hardcodé)
    if ($s['quantite_disponible'] <= ($s['seuil_alerte'] ?? 5)) $nb_critiques++;
}

$qte_totale = $qte_banques + $qte_sb;

// ════════════════════════════════════════════════════════
// STOCK PAR GROUPE SANGUIN (vue d'ensemble)
// ════════════════════════════════════════════════════════
$par_groupe = [];
foreach ($stocks_banques as $s) {
    $g = $s['groupe'];
    if (!isset($par_groupe[$g])) $par_groupe[$g] = 0;
    $par_groupe[$g] += (float)$s['quantite_disponible'];
}
foreach ($stocks_sb as $s) {
    $g = $s['groupe'];
    if (!isset($par_groupe[$g])) $par_groupe[$g] = 0;
    $par_groupe[$g] += (float)$s['quantite_disponible'];
}

// S'assurer que tous les groupes sont présents (même à 0)
$tous_groupes = $pdo->query("SELECT libelle FROM groupe_sanguin ORDER BY libelle")->fetchAll();
foreach ($tous_groupes as $tg) {
    if (!isset($par_groupe[$tg['libelle']])) $par_groupe[$tg['libelle']] = 0;
}
ksort($par_groupe);

$page_active = 'stock';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock global — Admin E-Sang</title>
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

        /* ── Vue par groupe sanguin ── */
        .groupes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-bottom: 28px;
        }
        .groupe-card {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 14px;
            padding: 16px 12px;
            text-align: center;
            transition: all 0.2s;
        }
        .groupe-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 14px -4px rgba(0,0,0,0.08);
        }
        .groupe-card.critique {
            background: #FEF2F2;
            border-color: #FCA5A5;
        }
        .groupe-card-label {
            background: #111111;
            color: #FFFFFF;
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 900;
            margin-bottom: 10px;
        }
        .groupe-card-qte {
            font-size: 30px;
            font-weight: 900;
            color: #111111;
            line-height: 1;
            margin-bottom: 4px;
        }
        .groupe-card.critique .groupe-card-qte {
            color: #8B0000;
        }
        .groupe-card-unit {
            font-size: 11px;
            color: #6B7280;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        /* Note : auto-fit s'occupe automatiquement de la responsivité,
           plus besoin de media query pour la grille des groupes */

        /* Badges */
        .badge-banque {
            background: #FEF2F2;
            color: #8B0000;
            border: 1.5px solid #FCA5A5;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            white-space: nowrap;
            display: inline-block;
        }
        .badge-sb {
            background: #FFF7ED;
            color: #C2410C;
            border: 1.5px solid #FED7AA;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            white-space: nowrap;
            display: inline-block;
        }
        .badge-critique {
            background: #FEF2F2;
            color: #8B0000;
            border: 1.5px solid #FCA5A5;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            white-space: nowrap;
        }
        .badge-ok {
            background: #F0FDF4;
            color: #166534;
            border: 1.5px solid #BBF7D0;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            white-space: nowrap;
        }
        .badge-expire {
            background: #FEF2F2;
            color: #8B0000;
            border: 1.5px solid #FCA5A5;
            font-weight: 800;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            white-space: nowrap;
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
        <h1>Stock global</h1>
        <p>Vue d'ensemble du stock de sang dans toutes les banques et sous-banques.</p>
    </div>

    <!-- ══ STATISTIQUES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🩸</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Stock total</div>
                <div class="stat-mini-number"><?php echo (int)$qte_totale; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">🏦</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Dans les banques</div>
                <div class="stat-mini-number"><?php echo (int)$qte_banques; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">🏪</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Dans les sous-banques</div>
                <div class="stat-mini-number"><?php echo (int)$qte_sb; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">⚠️</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Stocks critiques</div>
                <div class="stat-mini-number <?php echo $nb_critiques > 0 ? 'alert' : ''; ?>"><?php echo $nb_critiques; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ VUE PAR GROUPE SANGUIN ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Stock par groupe sanguin</div>
            <span class="cnt-badge">Total : <?php echo (int)$qte_totale; ?> pochettes</span>
        </div>

        <div class="groupes-grid">
            <?php foreach ($par_groupe as $groupe => $qte): ?>
            <?php $est_critique = $qte <= 5; ?>
            <div class="groupe-card <?php echo $est_critique ? 'critique' : ''; ?>">
                <div class="groupe-card-label"><?php echo htmlspecialchars($groupe); ?></div>
                <div class="groupe-card-qte"><?php echo (int)$qte; ?></div>
                <div class="groupe-card-unit">pochette(s)</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ══ TABLEAU DÉTAILLÉ ══ -->
    <div class="section">

        <div class="section-header">
            <div class="section-title">Détail par établissement</div>
            <span class="cnt-badge"><?php echo count($lignes); ?> ligne(s)</span>
        </div>

        <!-- Filtre Établissement -->
        <div class="filtres-bar">
            <span class="filtre-label">Type :</span>
            <a href="stock.php<?php echo $filtre_alerte !== 'tous' ? '?alerte='.$filtre_alerte : ''; ?>"
               class="filtre-btn <?php echo ($filtre_etablissement === 'tous') ? 'active' : ''; ?>">
                📊 Tous <span class="filtre-count"><?php echo ($nb_total_banques + $nb_total_sb); ?></span>
            </a>
            <a href="stock.php?etabl=banques<?php echo $filtre_alerte !== 'tous' ? '&alerte='.$filtre_alerte : ''; ?>"
               class="filtre-btn <?php echo ($filtre_etablissement === 'banques') ? 'active' : ''; ?>">
                🏦 Banques <span class="filtre-count"><?php echo $nb_total_banques; ?></span>
            </a>
            <a href="stock.php?etabl=sous_banques<?php echo $filtre_alerte !== 'tous' ? '&alerte='.$filtre_alerte : ''; ?>"
               class="filtre-btn <?php echo ($filtre_etablissement === 'sous_banques') ? 'active' : ''; ?>">
                🏪 Sous-banques <span class="filtre-count"><?php echo $nb_total_sb; ?></span>
            </a>
        </div>

        <!-- Filtre Alerte -->
        <div class="filtres-bar">
            <span class="filtre-label">Alerte :</span>
            <a href="stock.php<?php echo $filtre_etablissement !== 'tous' ? '?etabl='.$filtre_etablissement : ''; ?>"
               class="filtre-btn <?php echo ($filtre_alerte === 'tous') ? 'active' : ''; ?>">
                📋 Toutes <span class="filtre-count"><?php echo ($nb_total_banques + $nb_total_sb); ?></span>
            </a>
            <a href="stock.php?alerte=critiques<?php echo $filtre_etablissement !== 'tous' ? '&etabl='.$filtre_etablissement : ''; ?>"
               class="filtre-btn <?php echo ($filtre_alerte === 'critiques') ? 'active' : ''; ?>">
                ⚠️ Critiques <span class="filtre-count"><?php echo $nb_critiques; ?></span>
            </a>
            <a href="stock.php?alerte=expires<?php echo $filtre_etablissement !== 'tous' ? '&etabl='.$filtre_etablissement : ''; ?>"
               class="filtre-btn <?php echo ($filtre_alerte === 'expires') ? 'active' : ''; ?>">
                ⏰ Expirés <span class="filtre-count"><?php echo $nb_expires; ?></span>
            </a>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Établissement</th>
                        <th>Groupe</th>
                        <th>Quantité</th>
                        <th>Seuil alerte</th>
                        <th>État</th>
                        <th>Dernière MAJ</th>
                        <th>Expiration</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($lignes) > 0): ?>
                        <?php foreach ($lignes as $l): ?>
                        <tr>
                            <td>
                                <?php if ($l['type'] === 'banque'): ?>
                                    <span class="badge-banque">🏦 Banque</span>
                                <?php else: ?>
                                    <span class="badge-sb">🏪 Sous-banque</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($l['etabl_nom']); ?></strong>
                                <?php if (!empty($l['etabl_extra'])): ?>
                                <br><small style="color:#666;font-weight:500;">🏥 <?php echo htmlspecialchars($l['etabl_extra']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($l['groupe']); ?></span></td>
                            <td><strong><?php echo (int)$l['quantite']; ?></strong> pochette(s)</td>
                            <td><?php echo (int)$l['seuil']; ?></td>
                            <td>
                                <?php if ($l['critique']): ?>
                                    <span class="badge-critique">⚠️ Critique</span>
                                <?php else: ?>
                                    <span class="badge-ok">✅ OK</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $l['date_maj'] ? date('d/m/Y', strtotime($l['date_maj'])) : '—'; ?></td>
                            <td>
                                <?php if (!empty($l['date_exp'])): ?>
                                    <?php if ($l['expire']): ?>
                                        <span class="badge-expire">⏰ <?php echo date('d/m/Y', strtotime($l['date_exp'])); ?></span>
                                    <?php else: ?>
                                        <?php echo date('d/m/Y', strtotime($l['date_exp'])); ?>
                                    <?php endif; ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="vide">
                            <?php if ($filtre_etablissement !== 'tous' || $filtre_alerte !== 'tous'): ?>
                                Aucun stock ne correspond aux filtres sélectionnés.
                            <?php else: ?>
                                Aucun stock enregistré pour le moment.
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