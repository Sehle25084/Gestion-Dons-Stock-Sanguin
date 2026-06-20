<?php
session_start();
require_once '../config/db.php';

$page_active = 'stock';
include 'sidebar.php';

$id_sb               = $_SESSION['id_sous_banque'];
$id_banque_principale = $_SESSION['id_banque_principale'];
$success = $erreur   = "";

// ══ TRAITEMENT : Modifier seuil d'alerte ══
if (isset($_POST['modifier_seuil'])) {
    $id_groupe     = (int)($_POST['id_groupe_seuil'] ?? 0);
    $nouveau_seuil = (int)($_POST['nouveau_seuil']   ?? 0);

    if ($id_groupe <= 0 || $nouveau_seuil < 0) {
        $erreur = "Données invalides.";
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_sous_banque WHERE id_sous_banque = ? AND id_groupe = ?");
        $stmt->execute([$id_sb, $id_groupe]);

        if ((int)$stmt->fetchColumn() > 0) {
            $pdo->prepare("UPDATE stock_sous_banque SET seuil_alerte = ? WHERE id_sous_banque = ? AND id_groupe = ?")
                ->execute([$nouveau_seuil, $id_sb, $id_groupe]);
        } else {
            $pdo->prepare("INSERT INTO stock_sous_banque (id_sous_banque, id_groupe, quantite_disponible, seuil_alerte, date_mise_a_jour) VALUES (?, ?, 0, ?, CURDATE())")
                ->execute([$id_sb, $id_groupe, $nouveau_seuil]);
        }

        // Tracer le changement dans l'historique
        $stmtG = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
        $stmtG->execute([$id_groupe]);
        $libelle_groupe = $stmtG->fetchColumn();

        $pdo->prepare("
            INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, date_action)
            VALUES (?, ?, 'seuil_modifie', NULL, ?, NOW())
        ")->execute([
            $id_sb,
            $id_groupe,
            "Seuil d'alerte du groupe {$libelle_groupe} fixé à {$nouveau_seuil} pochette(s)"
        ]);

        $success = "Seuil d'alerte mis à jour avec succès.";
    }
}

// ── Récupérer stock complet (tous les groupes) ──
$stmt = $pdo->prepare("
    SELECT
        g.id_groupe,
        g.libelle AS groupe,
        COALESCE(s.quantite_disponible, 0) AS quantite,
        COALESCE(s.seuil_alerte, 3)        AS seuil,
        s.date_mise_a_jour
    FROM groupe_sanguin g
    LEFT JOIN stock_sous_banque s ON s.id_groupe = g.id_groupe AND s.id_sous_banque = ?
    ORDER BY g.libelle
");
$stmt->execute([$id_sb]);
$stocks = $stmt->fetchAll();

$total     = array_sum(array_column($stocks, 'quantite'));
$nb_vide   = count(array_filter($stocks, fn($s) => (int)$s['quantite'] === 0));
$nb_faible = count(array_filter($stocks, fn($s) => (int)$s['quantite'] > 0 && (int)$s['quantite'] <= (int)$s['seuil']));
$nb_ok     = count(array_filter($stocks, fn($s) => (int)$s['quantite'] > (int)$s['seuil']));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Interne | E-Sang</title>
    <style>
        .niveau-bar  { width: 100%; height: 8px; background: #F3F4F6; border-radius: 99px; overflow: hidden; margin-top: 4px; }
        .niveau-fill { height: 100%; border-radius: 99px; }
        .fill-ok       { background: #22C55E; }
        .fill-faible   { background: #F59E0B; }
        .fill-critique { background: #EF4444; }
        .fill-vide     { background: #6B0000; }

        .etat-tag { display:inline-flex; align-items:center; gap:5px; font-weight:700; padding:4px 10px; border-radius:999px; font-size:11px; }
        .etat-ok       { background:#D1FAE5; color:#065F46; }
        .etat-faible   { background:#FEF3C7; color:#92400E; }
        .etat-critique { background:#FEE2E2; color:#B91C1C; }
        .etat-vide     { background:#6B0000; color:#FFFFFF; }

        .btn-seuil { background:none; border:1px solid #E5E7EB; border-radius:6px; padding:4px 10px; font-size:11px; font-weight:600; color:#6B7280; cursor:pointer; font-family:inherit; transition:all 0.15s; }
        .btn-seuil:hover { border-color:#6B0000; color:#6B0000; }

        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); backdrop-filter:blur(3px); z-index:999; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal { background:#FFFFFF; border-radius:20px; width:100%; max-width:420px; padding:28px; box-shadow:0 20px 50px rgba(0,0,0,0.15); animation:slideUp 0.2s ease; }
        @keyframes slideUp { from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);} }
        .modal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:22px; padding-bottom:14px; border-bottom:2px solid #F3F4F6; }
        .modal-title { font-size:17px; font-weight:800; color:#111111; display:flex; align-items:center; gap:10px; }
        .modal-title::before { content:''; display:block; width:4px; height:20px; background:#6B0000; border-radius:99px; }
        .modal-close { width:32px; height:32px; border-radius:8px; background:#F3F4F6; border:none; cursor:pointer; font-size:16px; color:#6B7280; display:flex; align-items:center; justify-content:center; }
        .modal-close:hover { background:#FEF2F2; color:#6B0000; }
    </style>
</head>
<body>
<div class="main-content">

    <div class="page-header">
        <h1>Stock Interne</h1>
        <p>Niveaux de réserve par groupe sanguin — Dépôt : <strong><?php echo htmlspecialchars($_SESSION['nom_sb']); ?></strong></p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur); ?></div><?php endif; ?>

    <!-- STATS RAPIDES -->
    <div class="stats" style="grid-template-columns:repeat(4,1fr); margin-bottom:28px;">
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-label">Total pochettes</span>
                <span class="stat-icon ic-red">🩸</span>
            </div>
            <span class="stat-number"><?php echo $total; ?></span>
        </div>
        <div class="stat-card" style="<?php echo $nb_vide > 0 ? 'border-color:#FCA5A5;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">En rupture</span>
                <span class="stat-icon ic-red">🔴</span>
            </div>
            <span class="stat-number" style="<?php echo $nb_vide > 0 ? 'color:#6B0000;' : ''; ?>"><?php echo $nb_vide; ?></span>
        </div>
        <div class="stat-card" style="<?php echo $nb_faible > 0 ? 'border-color:#FCD34D;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">Sous le seuil</span>
                <span class="stat-icon ic-org">🟡</span>
            </div>
            <span class="stat-number" style="<?php echo $nb_faible > 0 ? 'color:#92400E;' : ''; ?>"><?php echo $nb_faible; ?></span>
        </div>
        <div class="stat-card" style="<?php echo $nb_ok === count($stocks) ? 'border-color:#BBF7D0;' : ''; ?>">
            <div class="stat-card-header">
                <span class="stat-label">Groupes OK</span>
                <span class="stat-icon ic-grn">🟢</span>
            </div>
            <span class="stat-number" style="<?php echo $nb_ok === count($stocks) ? 'color:#166534;' : ''; ?>"><?php echo $nb_ok; ?></span>
        </div>
    </div>

    <!-- TABLEAU STOCK -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Inventaire par groupe sanguin</div>
            <span class="cnt-badge"><?php echo count($stocks); ?> groupes</span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Groupe</th>
                        <th>Pochettes disponibles</th>
                        <th>Niveau de stock</th>
                        <th>Seuil d'alerte</th>
                        <th>Dernière MàJ</th>
                        <th>État</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stocks as $s):
                        $q     = (int)$s['quantite'];
                        $seuil = (int)$s['seuil'];
                        $pct   = $seuil > 0 ? min(100, round(($q / max($seuil * 2, 1)) * 100)) : ($q > 0 ? 100 : 0);

                        if ($q === 0)                    { $etat_cls='etat-vide';     $etat_txt='🔴 Rupture';  $fill='fill-vide'; }
                        elseif ($q <= ceil($seuil / 2)) { $etat_cls='etat-critique'; $etat_txt='🟠 Critique'; $fill='fill-critique'; }
                        elseif ($q <= $seuil)           { $etat_cls='etat-faible';   $etat_txt='🟡 Faible';   $fill='fill-faible'; }
                        else                            { $etat_cls='etat-ok';       $etat_txt='🟢 OK';       $fill='fill-ok'; }
                    ?>
                    <tr>
                        <td><span class="badge badge-groupe"><?php echo htmlspecialchars($s['groupe']); ?></span></td>
                        <td><strong style="font-size:18px;"><?php echo $q; ?></strong> pochette<?php echo $q > 1 ? 's' : ''; ?></td>
                        <td style="min-width:130px;">
                            <div class="niveau-bar">
                                <div class="niveau-fill <?php echo $fill; ?>" style="width:<?php echo $pct; ?>%"></div>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:10px; color:#9CA3AF; margin-top:3px;">
                                <span>0</span><span><?php echo $seuil * 2; ?></span>
                            </div>
                        </td>
                        <td><strong><?php echo $seuil; ?></strong> poch.</td>
                        <td>
                            <?php echo $s['date_mise_a_jour']
                                ? date('d/m/Y', strtotime($s['date_mise_a_jour']))
                                : '<span style="color:#9CA3AF;">—</span>';
                            ?>
                        </td>
                        <td><span class="etat-tag <?php echo $etat_cls; ?>"><?php echo $etat_txt; ?></span></td>
                        <td>
                            <button class="btn-seuil" onclick="ouvrirModalSeuil(<?php echo $s['id_groupe']; ?>, '<?php echo htmlspecialchars($s['groupe'], ENT_QUOTES); ?>', <?php echo $seuil; ?>)">
                                ✏️ Modifier seuil
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- MODAL SEUIL -->
<div class="modal-overlay" id="modalSeuil" onclick="fermerModalOverlay(event)">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Modifier le seuil d'alerte</div>
            <button class="modal-close" onclick="fermerModal()">✕</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="id_groupe_seuil" id="modal_id_groupe"/>
            <div style="background:#FFFBEB; border:1.5px solid #FCD34D; border-radius:10px; padding:12px 14px; margin-bottom:18px; font-size:13px; color:#92400E; line-height:1.6;">
                ⚠️ Seuil actuel du groupe <strong id="modal_groupe_nom"></strong> :
                <strong id="modal_seuil_actuel"></strong> pochette(s).<br>
                En dessous de ce seuil, une demande automatique sera envoyée à la banque principale.
            </div>
            <div class="form-group">
                <label>Nouveau seuil (pochettes) *</label>
                <input type="number" name="nouveau_seuil" id="modal_nouveau_seuil" min="0" max="100" required style="font-size:20px; font-weight:700; text-align:center;"/>
            </div>
            <button type="submit" name="modifier_seuil" class="btn-submit btn-submit-full" style="margin-top:6px;">
                Enregistrer le seuil
            </button>
        </form>
    </div>
</div>

<script>
function ouvrirModalSeuil(id, nom, seuil) {
    document.getElementById('modal_id_groupe').value       = id;
    document.getElementById('modal_groupe_nom').textContent     = nom;
    document.getElementById('modal_seuil_actuel').textContent   = seuil;
    document.getElementById('modal_nouveau_seuil').value        = seuil;
    document.getElementById('modalSeuil').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function fermerModal() {
    document.getElementById('modalSeuil').classList.remove('open');
    document.body.style.overflow = '';
}
function fermerModalOverlay(e) {
    if (e.target === document.getElementById('modalSeuil')) fermerModal();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') fermerModal(); });
</script>
</body>
</html>