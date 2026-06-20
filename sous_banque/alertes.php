<?php
session_start();
require_once '../config/db.php';

$page_active = 'alertes';
include 'sidebar.php';

$id_sb   = $_SESSION['id_sous_banque'];
$success = $erreur = "";

// ══════════════════════════════════════════════════════════════
//  1. RÉCUPÉRATION DES INFOS DE LA SOUS-BANQUE (Banque & Hôpital)
// ══════════════════════════════════════════════════════════════
$id_banque_principale = null;
$id_hopital = null;

try {
    $stmt_sb = $pdo->prepare("SELECT id_banque_principale, id_hopital FROM sous_banque WHERE id_sous_banque = ?");
    $stmt_sb->execute([$id_sb]);
    $sb_info = $stmt_sb->fetch();
    if ($sb_info) {
        $id_banque_principale = $sb_info['id_banque_principale'];
        $id_hopital           = $sb_info['id_hopital'];
    }
} catch (Exception $e) {
    $erreur = "Erreur de connexion aux paramètres du dépôt.";
}

// ══════════════════════════════════════════════════════════════
//  2. TRAITEMENT : Marquer une alerte comme résolue
// ══════════════════════════════════════════════════════════════
if (isset($_GET['traiter'])) {
    $id_alerte = (int)$_GET['traiter'];
    $stmt = $pdo->prepare("
        SELECT a.id_alerte, a.id_groupe, a.quantite_actuelle, g.libelle AS groupe
        FROM alerte_stock a
        JOIN groupe_sanguin g ON g.id_groupe = a.id_groupe
        WHERE a.id_alerte = ? AND a.id_sous_banque = ?
    ");
    $stmt->execute([$id_alerte, $id_sb]);
    $alerte_a_traiter = $stmt->fetch();

    if ($alerte_a_traiter) {
        $pdo->prepare("UPDATE alerte_stock SET traitee = 1 WHERE id_alerte = ?")
            ->execute([$id_alerte]);

        $pdo->prepare("
            INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action)
            VALUES (?, ?, 'alerte_traitee', ?, ?, NOW())
        ")->execute([
            $id_sb,
            $alerte_a_traiter['id_groupe'],
            (int)$alerte_a_traiter['quantite_actuelle'],
            "Alerte résolue pour le groupe {$alerte_a_traiter['groupe']}"
        ]);

        $success = "L'alerte a été classée comme résolue avec succès.";
    } else {
        $erreur = "Alerte introuvable.";
    }
}

// ══════════════════════════════════════════════════════════════
//  3. TRAITEMENT : Tout marquer comme résolu d'un coup
// ══════════════════════════════════════════════════════════════
if (isset($_POST['tout_traiter'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM alerte_stock WHERE id_sous_banque = ? AND traitee = 0");
    $stmt->execute([$id_sb]);
    $nb_a_traiter = (int)$stmt->fetchColumn();

    $pdo->prepare("UPDATE alerte_stock SET traitee = 1 WHERE id_sous_banque = ? AND traitee = 0")
        ->execute([$id_sb]);

    if ($nb_a_traiter > 0) {
        $pdo->prepare("
            INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action)
            VALUES (?, NULL, 'alerte_traitee', ?, ?, NOW())
        ")->execute([
            $id_sb,
            $nb_a_traiter,
            "{$nb_a_traiter} alerte(s) marquée(s) comme résolues en une seule action"
        ]);
    }

    $success = "Toutes les alertes en cours ont été marquées comme résolues.";
}

// ══════════════════════════════════════════════════════════════
//  4. TRAITEMENT : Passer une commande instantanée (Popup)
// ══════════════════════════════════════════════════════════════
if (isset($_POST['creer_demande_urgence'])) {
    $id_groupe = (int)$_POST['id_groupe'];
    $quantite  = (float)$_POST['quantite_demandee'];
    $note      = trim($_POST['note_commande'] ?? '');

    if ($quantite <= 0) {
        $erreur = "Veuillez saisir une quantité valide supérieure à 0.";
    } elseif (!$id_banque_principale || !$id_hopital) {
        $erreur = "Erreur : Impossible de lier la demande (Hôpital ou Banque principale introuvable).";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO demande (id_hopital, id_sous_banque, id_banque, id_groupe, quantite_demandee, date_demande, statut, note, urgence)
                VALUES (?, ?, ?, ?, ?, NOW(), 'en_attente', ?, 1)
            ");
            $stmt->execute([$id_hopital, $id_sb, $id_banque_principale, $id_groupe, $quantite, $note]);

            $stmtG = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
            $stmtG->execute([$id_groupe]);
            $libelle_groupe_urgence = $stmtG->fetchColumn();

            $pdo->prepare("
                INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action)
                VALUES (?, ?, 'demande_envoyee', ?, ?, NOW())
            ")->execute([
                $id_sb, $id_groupe, $quantite,
                "Commande d'urgence envoyée à la banque principale : {$quantite} pochette(s) {$libelle_groupe_urgence}"
            ]);

            $success = "📦 Demande de réapprovisionnement envoyée avec succès à la banque principale !";
        } catch (Exception $e) {
            $erreur = "Erreur lors de l'envoi de la commande : " . $e->getMessage();
        }
    }
}

// ══════════════════════════════════════════════════════════════
//  5. REQUÊTES D'AFFICHAGE (Alertes Actives et Archivées)
// ══════════════════════════════════════════════════════════════
$sql_actives = "
    SELECT a.*, g.libelle AS groupe, s.quantite_disponible, s.seuil_alerte AS seuil_actuel
    FROM alerte_stock a
    JOIN groupe_sanguin g ON g.id_groupe = a.id_groupe
    LEFT JOIN stock_sous_banque s ON s.id_sous_banque = a.id_sous_banque AND s.id_groupe = a.id_groupe
    WHERE a.id_sous_banque = ? AND a.traitee = 0
    ORDER BY CASE WHEN a.type_alerte = 'rupture' THEN 1 WHEN a.type_alerte = 'critique' THEN 2 ELSE 3 END, a.date_alerte DESC
";
$stmt = $pdo->prepare($sql_actives);
$stmt->execute([$id_sb]);
$alertes_actives = $stmt->fetchAll();

$sql_resolues = "
    SELECT a.*, g.libelle AS groupe
    FROM alerte_stock a
    JOIN groupe_sanguin g ON g.id_groupe = a.id_groupe
    WHERE a.id_sous_banque = ? AND a.traitee = 1
    ORDER BY a.date_alerte DESC LIMIT 10
";
$stmt = $pdo->prepare($sql_resolues);
$stmt->execute([$id_sb]);
$alertes_resolues = $stmt->fetchAll();

$cnt_rupture = count(array_filter($alertes_actives, fn($a) => $a['type_alerte'] === 'rupture'));
$cnt_critique = count(array_filter($alertes_actives, fn($a) => $a['type_alerte'] === 'critique'));
$cnt_avertis = count(array_filter($alertes_actives, fn($a) => $a['type_alerte'] === 'avertissement'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centre des Alertes | E-Sang</title>
    <style>
        /* ══ ONGLETS PRINCIPAUX ══ */
        .tabs-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #E5E7EB; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .tabs-buttons { display: flex; gap: 8px; }
        .tab-btn { padding: 12px 20px; font-size: 14px; font-weight: 700; color: #6B7280; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; transition: all 0.15s ease; margin-bottom: -2px; }
        .tab-btn:hover { color: #111111; }
        .tab-btn.active { color: #6B0000; border-bottom-color: #6B0000; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* ══ FILTRES INTERNES DE GRAVITÉ ══ */
        .filter-container { display: flex; gap: 10px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        .filter-btn { padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; border: 1.5px solid #E5E7EB; background: #FFFFFF; color: #4B5563; cursor: pointer; transition: all 0.1s ease; }
        .filter-btn.active { background: #111111; color: #FFFFFF; border-color: #111111; }

        /* ══ CARTES D'ALERTE ══ */
        .alertes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; margin-bottom: 30px; }
        .alerte-card { background: #FFFFFF; border: 1.5px solid #E5E7EB; border-radius: 16px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; position: relative; transition: transform 0.2s, box-shadow 0.2s; }
        .alerte-card:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .alerte-card.border-rupture { border-color: #6B0000; background: #FFF8F8; }
        .alerte-card.border-critique { border-color: #FCA5A5; background: #FFFDFD; }
        .alerte-card.border-avertissement { border-color: #FCD34D; background: #FFFFFA; }

        .card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; }
        .group-circle { width: 46px; height: 46px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 800; }
        .circle-rupture { background: #6B0000; color: #FFFFFF; }
        .circle-critique { background: #FEE2E2; color: #6B0000; border: 1.5px solid #FCA5A5; }
        .circle-avertissement { background: #FEF3C7; color: #92400E; border: 1.5px solid #FCD34D; }

        .badge-type { font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 20px; letter-spacing: 0.02em; }
        .bg-rupture { background: #6B0000; color: #FFFFFF; }
        .bg-critique { background: #EF4444; color: #FFFFFF; }
        .bg-avertissement { background: #FFF3C7; color: #92400E; border: 1px solid #FCD34D; }

        .card-body h3 { font-size: 15px; font-weight: 700; color: #111111; margin-bottom: 4px; }
        .card-body p { font-size: 13px; color: #4B5563; margin-bottom: 12px; line-height: 1.4; }
        
        .stock-mini-info { display: flex; gap: 14px; font-size: 12px; background: #F9FAFB; padding: 8px 12px; border-radius: 8px; border: 1px solid #E5E7EB; margin-bottom: 16px; }
        .stock-mini-info strong { color: #111111; }

        /* ══ BOUTONS D'ACTIONS ══ */
        .card-actions { display: flex; gap: 8px; border-top: 1px solid #F3F4F6; padding-top: 14px; }
        .btn-act { flex: 1; padding: 8px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; text-align: center; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 1.5px solid transparent; transition: all 0.1s ease; }
        .btn-resolve { background: #FFFFFF; border-color: #D1D5DB; color: #374151; }
        .btn-resolve:hover { background: #F3F4F6; border-color: #9CA3AF; }
        .btn-order { background: #6B0000; color: #FFFFFF; }
        .btn-order:hover { background: #550000; }

        /* ══ MODALS (pattern standard .modal-overlay / .open) ══ */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); backdrop-filter: blur(3px); z-index: 999; align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.open { display: flex; }

        .modal { background: #FFFFFF; border-radius: 20px; width: 100%; max-width: 480px; padding: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); animation: slideUp 0.2s ease; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .modal h2 { font-size: 18px; font-weight: 800; color: #111111; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .modal p { font-size: 13.5px; color: #4B5563; line-height: 1.5; margin-bottom: 16px; }
        .modal-close-action { width: 100%; padding: 10px; background: #F3F4F6; border: none; border-radius: 10px; font-size: 13px; font-weight: 700; color: #374151; cursor: pointer; margin-top: 10px; font-family: inherit; }
        .modal-close-action:hover { background: #E5E7EB; }

        .info-row { display: flex; gap: 12px; padding: 12px; border-radius: 10px; margin-bottom: 10px; font-size: 13px; text-align: left; }
    </style>
</head>
<body>
<div class="main-content">

    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1>Centre des Alertes</h1>
            <p>Supervision des stocks et commandes de réapprovisionnement d'urgence.</p>
        </div>
        <button class="btn-submit" style="background: #FFFFFF; border: 1.5px solid #E5E7EB; color: #374151; padding: 8px 16px; font-size: 13px;" onclick="toggleRulesModal(true)">
            ℹ️ Comprendre les alertes
        </button>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur); ?></div><?php endif; ?>

    <div class="tabs-header">
        <div class="tabs-buttons">
            <button class="tab-btn active" onclick="switchTab('actives', this)">
                Alertes en cours (<?php echo count($alertes_actives); ?>)
            </button>
            <button class="tab-btn" onclick="switchTab('resolues', this)">
                Historique Résolu (<?php echo count($alertes_resolues); ?>)
            </button>
        </div>
        
        <?php if (count($alertes_actives) > 0): ?>
        <form method="POST" action="" onsubmit="return confirm('Voulez-vous marquer toutes les alertes en cours comme résolues ?');">
            <button type="submit" name="tout_traiter" class="btn-seuil" style="color: #6B0000; border-color: #FCA5A5;">
                ✓ Tout marquer comme résolu
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div id="tab-actives" class="tab-content active">
        <?php if (!empty($alertes_actives)): ?>
            <div class="filter-container">
                <span style="font-size: 13px; color: #6B7280; font-weight: 500;">Filtrer par gravité :</span>
                <button class="filter-btn active" onclick="filtrerGravite('tous', this)">Tous</button>
                <button class="filter-btn" onclick="filtrerGravite('rupture', this)">⚫ Ruptures (<?php echo $cnt_rupture; ?>)</button>
                <button class="filter-btn" onclick="filtrerGravite('critique', this)">🔴 Critiques (<?php echo $cnt_critique; ?>)</button>
                <button class="filter-btn" onclick="filtrerGravite('avertissement', this)">🟡 Avertissements (<?php echo $cnt_avertis; ?>)</button>
            </div>

            <div class="alertes-grid">
                <?php foreach ($alertes_actives as $a): 
                    $type = $a['type_alerte'];
                    $class_border = 'border-' . $type;
                    $class_circle = 'circle-' . $type;
                    $class_badge  = 'bg-' . $type;
                    
                    $label_type = "🟡 Avertissement";
                    if($type === 'rupture') $label_type = "⚫ Rupture";
                    if($type === 'critique') $label_type = "🔴 Critique";
                ?>
                <div class="alerte-card <?php echo $class_border; ?>" data-gravite="<?php echo $type; ?>">
                    <div>
                        <div class="card-top">
                            <div class="group-circle <?php echo $class_circle; ?>"><?php echo htmlspecialchars($a['groupe']); ?></div>
                            <span class="badge-type <?php echo $class_badge; ?>"><?php echo $label_type; ?></span>
                        </div>
                        <div class="card-body">
                            <h3>Groupe sanguin <?php echo htmlspecialchars($a['groupe']); ?></h3>
                            <p>Le stock disponible a atteint un seuil nécessitant votre attention.</p>
                            
                            <div class="stock-mini-info">
                                <span>Disponible : <strong><?php echo (int)$a['quantite_actuelle']; ?> p.</strong></span>
                                <span>Seuil réglé : <strong><?php echo (int)$a['seuil_alerte']; ?> p.</strong></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-actions">
                        <a href="alertes.php?traiter=<?php echo $a['id_alerte']; ?>" class="btn-act btn-resolve">
                            ✓ Résolu
                        </a>
                        <button class="btn-act btn-order" onclick="ouvrirCommandePopup(<?php echo $a['id_groupe']; ?>, '<?php echo htmlspecialchars($a['groupe']); ?>', <?php echo (int)$a['seuil_alerte']; ?>)">
                            📦 Commander
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="section" style="text-align: center; padding: 40px; color: #4B5563;">
                🎉 <strong>Excellent !</strong> Aucune alerte de quantité n'est active sur votre stock actuellement.
            </div>
        <?php endif; ?>
    </div>

    <div id="tab-resolues" class="tab-content">
        <div class="section">
            <div class="section-header"><div class="section-title">10 dernières alertes résolues</div></div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Groupe</th>
                            <th>Gravité</th>
                            <th>Quantité Constatée</th>
                            <th>Date Déclenchement</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($alertes_resolues)): ?>
                            <?php foreach ($alertes_resolues as $ar): ?>
                            <tr>
                                <td><span class="badge badge-groupe"><?php echo htmlspecialchars($ar['groupe']); ?></span></td>
                                <td>
                                    <span class="badge <?php echo 'bg-'.$ar['type_alerte']; ?>" style="padding: 2px 8px; border-radius: 10px; font-size: 12px; color: #fff;">
                                        <?php echo htmlspecialchars($ar['type_alerte']); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo (int)$ar['quantite_actuelle']; ?> pochettes</strong></td>
                                <td style="font-size: 13px; color: #4B5563;"><?php echo date('d/m/Y H:i', strtotime($ar['date_alerte'])); ?></td>
                                <td><span class="badge badge-ok">✓ Résolue</span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="vide">Aucune alerte archivée dans l'historique.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal-overlay" id="orderModal" onclick="if(event.target === this) toggleOrderModal(false)">
    <div class="modal">
        <h2>📦 Commande d'urgence à la Banque</h2>
        <p>Générez une demande officielle de réapprovisionnement pour le groupe <strong id="lbl_pop_groupe" style="color:#6B0000;"></strong>.</p>
        
        <form method="POST" action="">
            <input type="hidden" name="id_groupe" id="input_pop_id_groupe">
            
            <div class="form-group">
                <label for="quantite_demandee">Quantité de pochettes souhaitée :</label>
                <input type="number" name="quantite_demandee" id="quantite_demandee" min="1" required style="font-size:16px; font-weight:700; text-align:center;">
            </div>

            <div class="form-group">
                <label for="note_commande">Note ou motif (optionnel) :</label>
                <input type="text" name="note_commande" id="note_commande" placeholder="Ex: Commande suite à alerte de rupture">
            </div>

            <button type="submit" name="creer_demande_urgence" class="btn-submit btn-submit-full" style="margin-top:6px;">
                🚀 Envoyer la demande à la banque
            </button>
        </form>
        <button type="button" class="modal-close-action" onclick="toggleOrderModal(false)">Annuler</button>
    </div>
</div>

<div class="modal-overlay" id="rulesModal" onclick="if(event.target === this) toggleRulesModal(false)">
    <div class="modal">
        <h2>ℹ️ Niveaux d'Alertes</h2>
        <p>Le système surveille les volumes et génère trois états distincts :</p>
        
        <div class="info-row" style="background: #FEE2E2; color: #6B0000; border: 1px solid #FCA5A5;">
            <div><strong>⚫ Rupture :</strong> Le stock est totalement épuisé (0 pochette). Danger immédiat.</div>
        </div>
        <div class="info-row" style="background: #FFF5F5; color: #E53E3E; border: 1px solid #FEB2B2;">
            <div><strong>🔴 Critique :</strong> La quantité disponible est inférieure ou égale au seuil de sécurité défini.</div>
        </div>
        <div class="info-row" style="background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D;">
            <div><strong>🟡 Avertissement :</strong> Le stock approche de la limite. Un réapprovisionnement préventif est conseillé.</div>
        </div>
        <button type="button" class="modal-close-action" onclick="toggleRulesModal(false)">Fermer</button>
    </div>
</div>

<script>
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tabId).classList.add('active');
    btn.classList.add('active');
}

function filtrerGravite(type, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.alerte-card').forEach(card => {
        if (type === 'tous' || card.getAttribute('data-gravite') === type) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

function ouvrirCommandePopup(idGroupe, libelleGroupe, seuil) {
    document.getElementById('input_pop_id_groupe').value = idGroupe;
    document.getElementById('lbl_pop_groupe').textContent = libelleGroupe;
    document.getElementById('quantite_demandee').value = seuil > 0 ? seuil * 2 : 5;
    toggleOrderModal(true);
}

function toggleOrderModal(show) {
    const modal = document.getElementById('orderModal');
    if (show) {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    } else {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
}

function toggleRulesModal(show) {
    const modal = document.getElementById('rulesModal');
    if (show) {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    } else {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', e => { 
    if (e.key === 'Escape') {
        toggleOrderModal(false);
        toggleRulesModal(false);
    } 
});
</script>
</body>
</html>