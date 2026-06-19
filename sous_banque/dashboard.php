<?php
session_start();
require_once '../config/db.php';

$page_active = 'dashboard';
include 'sidebar.php'; // Sécurité + CSS inclus ici

$id_sb = $_SESSION['id_sous_banque'];

// ── 1. Total général des pochettes ──
$stmt = $pdo->prepare("SELECT COALESCE(SUM(quantite_disponible), 0) FROM stock_sous_banque WHERE id_sous_banque = ?");
$stmt->execute([$id_sb]);
$total_stock = (int)$stmt->fetchColumn();

// ── 2. Nombre de groupes en rupture (quantite = 0) ──
$stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_sous_banque WHERE id_sous_banque = ? AND quantite_disponible = 0");
$stmt->execute([$id_sb]);
$nb_rupture = (int)$stmt->fetchColumn();

// ── 3. Nombre de groupes sous le seuil (quantite > 0 ET <= seuil) ──
$stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_sous_banque WHERE id_sous_banque = ? AND quantite_disponible > 0 AND quantite_disponible <= seuil_alerte");
$stmt->execute([$id_sb]);
$nb_faible = (int)$stmt->fetchColumn();

// ── 4. Alertes non traitées ──
$stmt = $pdo->prepare("SELECT COUNT(*) FROM alerte_stock WHERE id_sous_banque = ? AND traitee = 0");
$stmt->execute([$id_sb]);
$nb_alertes_actives = (int)$stmt->fetchColumn();

// ── 5. Stock par groupe sanguin (TOUS les groupes, même ceux à 0) ──
$stmt = $pdo->prepare("
    SELECT
        g.id_groupe,
        g.libelle AS groupe,
        COALESCE(s.quantite_disponible, 0) AS quantite,
        COALESCE(s.seuil_alerte, 3)        AS seuil,
        s.date_mise_a_jour
    FROM groupe_sanguin g
    LEFT JOIN stock_sous_banque s
        ON s.id_groupe = g.id_groupe
        AND s.id_sous_banque = ?
    ORDER BY g.libelle
");
$stmt->execute([$id_sb]);
$groupes_stock = $stmt->fetchAll();

// ── 6. Dernières demandes envoyées par cette sous-banque ──
$stmt = $pdo->prepare("
    SELECT d.*, b.nom AS nom_banque, g.libelle AS groupe
    FROM demande d
    JOIN banque_de_sang b  ON b.id_banque = d.id_banque
    JOIN groupe_sanguin g  ON g.id_groupe  = d.id_groupe
    WHERE d.id_sous_banque = ?
    ORDER BY d.date_demande DESC
    LIMIT 5
");
$stmt->execute([$id_sb]);
$dernieres_demandes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?php echo htmlspecialchars($_SESSION['nom_sb'] ?? 'Dépôt'); ?> | E-Sang</title>
    
    <style>
        /* Grille des cartes */
        .groupes-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)) !important;
            gap: 16px !important;
            margin-top: 15px !important;
        }

        /* Base de la carte : Fond rouge très clair, doux et reposant */
        .clean-card {
            background: #FFF5F5 !important; /* Rouge pastel ultra léger */
            border: 1px solid #FEE2E2 !important; /* Bordure rouge très fine */
            border-radius: 12px !important;
            padding: 18px !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: space-between !important;
            box-shadow: 0 2px 4px rgba(155, 0, 0, 0.02) !important;
            transition: transform 0.2s, box-shadow 0.2s !important;
            box-sizing: border-box !important;
        }

        .clean-card:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 12px rgba(155, 0, 0, 0.06) !important;
        }

        /* Haut de la carte (Groupe + Badge) */
        .card-top {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 14px !important;
        }

        /* Groupe Sanguin écrit STRICTEMENT en noir */
        .blood-group {
            font-size: 24px !important;
            font-weight: 800 !important;
            color: #000000 !important; /* Noir pur */
            background: #FFE4E6 !important; /* Fond légèrement plus rosé pour le faire ressortir */
            padding: 4px 12px !important;
            border-radius: 6px !important;
        }

        /* Badge de texte pour le statut */
        .status-badge {
            font-size: 11px !important;
            font-weight: 700 !important;
            padding: 4px 8px !important;
            border-radius: 4px !important;
            letter-spacing: 0.5px !important;
        }

        /* Corps de la carte (Quantité de pochettes) */
        .card-body {
            display: flex !important;
            align-items: baseline !important;
            gap: 6px !important;
            margin: 10px 0 !important;
        }

        .quantity-number {
            font-size: 40px !important;
            font-weight: 800 !important;
            color: #1e293b !important;
            line-height: 1 !important;
        }

        .quantity-label {
            font-size: 14px !important;
            color: #64748b !important;
            font-weight: 500 !important;
        }

        /* Bas de la carte (Seuil) */
        .card-footer {
            font-size: 12px !important;
            color: #7f8c8d !important;
            border-top: 1px dashed #FCA5A5 !important;
            padding-top: 8px !important;
            margin-top: 6px !important;
            text-align: left !important;
        }

        /* ── VARIANTES DE STATUTS (Logiques et claires sans détruire les yeux) ── */
        
        /* Carte de base */
.clean-card {
    background: #FFEAEA !important; /* Rouge très clair */
    border: 1px solid #F8B4B4 !important;
    border-radius: 12px !important;
    padding: 18px !important;
    transition: all 0.2s ease !important;
}

/* Groupe sanguin */
.blood-group {
    font-size: 24px !important;
    font-weight: 800 !important;
    color: #000000 !important;
    background: #FFD6D6 !important;
    padding: 5px 12px !important;
    border-radius: 8px !important;
}

/* Quantité */
.quantity-number {
    font-size: 40px !important;
    font-weight: 800 !important;
    color: #000000 !important;
}

/* Badge */
.status-badge {
    font-size: 11px !important;
    font-weight: 700 !important;
    padding: 5px 10px !important;
    border-radius: 6px !important;
}

/* Stock suffisant */
.card-ok {
    background: #FFF0F0 !important;
    border-color: #F5A3A3 !important;
}

.card-ok .status-badge {
    background: #FAD4D4 !important;
    color: #7A0000 !important;
}

/* Stock faible */
.card-faible {
    background: #FFE4E4 !important;
    border-color: #E57373 !important;
}

.card-faible .status-badge {
    background: #F8BBBB !important;
    color: #8B0000 !important;
}

/* Stock critique */
.card-critique {
    background: #FFD6D6 !important;
    border-color: #D32F2F !important;
}

.card-critique .status-badge {
    background: #EF9A9A !important;
    color: #5C0000 !important;
}

/* Rupture totale */
.card-vide {
    background: #FFF5F5 !important;
    border-color: #C62828 !important;
}

.card-vide .status-badge {
    background: #C62828 !important;
    color: white !important;
}

.card-vide .blood-group {
    background: #FFCCCC !important;
    color: #000000 !important;
}
    </style>
</head>
<body>

<div class="main-content">

    <div class="page-header">
        <h1>Tableau de bord</h1>
        <p>
           <h3> 🏥 Hôpital : <strong><?php echo htmlspecialchars($_SESSION['nom_hopital'] ?? '—'); ?></strong>
            &nbsp;|&nbsp;
            👤 Connecté : <b><?php echo htmlspecialchars($_SESSION['user_nom'] ?? '—'); ?></b>
           </h3>
        </p>
    </div>

    <?php if ($nb_rupture > 0): ?>
    <div class="alerte-warning">
        <span>🚨 <?php echo $nb_rupture; ?> groupe(s) sanguin(s) en <strong>rupture totale</strong> dans votre dépôt !</span>
        <a href="demandes.php" style="color:#92400E; font-weight:700; text-decoration:none; white-space:nowrap;">Envoyer une demande →</a>
    </div>
    <?php elseif ($nb_faible > 0): ?>
    <div class="alerte-warning">
        <span>⚠️ <?php echo $nb_faible; ?> groupe(s) sanguin(s) <strong>sous le seuil d'alerte</strong>. Pensez à réapprovisionner.</span>
        <a href="demandes.php" style="color:#92400E; font-weight:700; text-decoration:none; white-space:nowrap;">Envoyer une demande →</a>
    </div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-card" style="<?php echo $nb_rupture > 0 ? 'border-color:#FCA5A5;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">Total Pochettes</span>
                <span class="stat-icon ic-red">🩸</span>
            </div>
            <span class="stat-number"><?php echo $total_stock; ?></span>
        </div>

        <div class="stat-card" style="<?php echo $nb_rupture > 0 ? 'border-color:#FCA5A5;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">Groupes en rupture</span>
                <span class="stat-icon ic-red">🔴</span>
            </div>
            <span class="stat-number" style="<?php echo $nb_rupture > 0 ? 'color:#6B0000;' : ''; ?>">
                <?php echo $nb_rupture; ?>
            </span>
        </div>

        <div class="stat-card" style="<?php echo $nb_faible > 0 ? 'border-color:#FCD34D;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">Groupes faibles</span>
                <span class="stat-icon ic-org">🟡</span>
            </div>
            <span class="stat-number" style="<?php echo $nb_faible > 0 ? 'color:#92400E;' : ''; ?>">
                <?php echo $nb_faible; ?>
            </span>
        </div>

        <div class="stat-card" style="<?php echo $nb_alertes_actives > 0 ? 'border-color:#FCA5A5;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">Alertes actives</span>
                <span class="stat-icon ic-red">🔔</span>
            </div>
            <span class="stat-number" style="<?php echo $nb_alertes_actives > 0 ? 'color:#6B0000;' : ''; ?>">
                <?php echo $nb_alertes_actives; ?>
            </span>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <div class="section-title">Réserve par groupe sanguin</div>
            <span class="cnt-badge"><?php echo count($groupes_stock); ?> groupes</span>
        </div>

        <div class="groupes-grid">
            <?php foreach ($groupes_stock as $gs):
                $q      = (int)$gs['quantite'];
                $seuil  = (int)$gs['seuil'];

                // Logique d'affichage des états
                if ($q === 0) {
                    $style_class = 'card-vide';
                    $badge_label = 'VIDE';
                } elseif ($q <= $seuil) {
                    $style_class = ($q <= ceil($seuil / 2)) ? 'card-critique' : 'card-faible';
                    $badge_label = ($q <= ceil($seuil / 2)) ? 'CRITIQUE' : 'FAIBLE';
                } else {
                    $style_class = 'card-ok';
                    $badge_label = 'OK';
                }
            ?>
            <div class="clean-card <?php echo $style_class; ?>">
                <div class="card-top">
                    <span class="blood-group"><?php echo htmlspecialchars($gs['groupe']); ?></span>
                    <span class="status-badge"><?php echo $badge_label; ?></span>
                </div>
                
                <div class="card-body">
                    <span class="quantity-number"><?php echo $q; ?></span>
                    <span class="quantity-label">pochette<?php echo $q > 1 ? 's' : ''; ?></span>
                </div>

                <div class="card-footer">
                    Seuil minimal : <?php echo $seuil; ?> poch.
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <div class="section-title">Fiche de contrôle des réfrigérateurs</div>
            <a href="stock.php" style="font-size:13px; color:#6B0000; font-weight:600; text-decoration:none;">
                Gérer le stock →
            </a>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Groupe Sanguin</th>
                        <th>Quantité en réserve</th>
                        <th>Seuil d'alerte</th>
                        <th>Dernière mise à jour</th>
                        <th>État</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groupes_stock as $gs):
                        $q     = (int)$gs['quantite'];
                        $seuil = (int)$gs['seuil'];

                        if ($q === 0) {
                            $badge = '<span class="badge badge-vide">🔴 Rupture totale</span>';
                        } elseif ($q <= ceil($seuil / 2)) {
                            $badge = '<span class="badge badge-critique">🟠 Critique</span>';
                        } elseif ($q <= $seuil) {
                            $badge = '<span class="badge badge-faible">🟡 Faible</span>';
                        } else {
                            $badge = '<span class="badge badge-ok">🟢 Suffisant</span>';
                        }
                    ?>
                    <tr>
                        <td><span class="badge badge-groupe"><?php echo htmlspecialchars($gs['groupe']); ?></span></td>
                        <td><strong><?php echo $q; ?></strong> pochette<?php echo $q > 1 ? 's' : ''; ?></td>
                        <td><?php echo $seuil; ?> pochette<?php echo $seuil > 1 ? 's' : ''; ?></td>
                        <td>
                            <?php echo $gs['date_mise_a_jour']
                                ? date('d/m/Y', strtotime($gs['date_mise_a_jour']))
                                : '<span style="color:#9CA3AF;">Aucun mouvement</span>';
                            ?>
                        </td>
                        <td><?php echo $badge; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <div class="section-title">Dernières demandes envoyées</div>
            <a href="demandes.php" style="font-size:13px; color:#6B0000; font-weight:600; text-decoration:none;">
                Voir tout →
            </a>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Banque principale</th>
                        <th>Groupe</th>
                        <th>Quantité</th>
                        <th>Date</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dernieres_demandes): ?>
                        <?php foreach ($dernieres_demandes as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['nom_banque']); ?></td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                            <td><?php echo (int)$d['quantite_demandee']; ?> pochette(s)</td>
                            <td><?php echo date('d/m/Y', strtotime($d['date_demande'])); ?></td>
                            <td>
                                <?php if ($d['statut'] === 'en_attente'): ?>
                                    <span class="badge badge-attente">En attente</span>
                                <?php elseif ($d['statut'] === 'acceptée'): ?>
                                    <span class="badge badge-ok">Acceptée</span>
                                <?php else: ?>
                                    <span class="badge badge-vide">Refusée</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="vide">
                                Aucune demande envoyée pour le moment.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>