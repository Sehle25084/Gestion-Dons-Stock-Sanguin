<?php
session_start();
require_once '../config/db.php';
require_once '../config/helpers.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'banque') {
    header("Location: ../index.php");
    exit;
}

// Compatibilité ancienne/nouvelle architecture
$id_banque = $_SESSION['id_banque'] ?? $_SESSION['id'];
$page_active = 'analyse';
$success = $erreur = "";

// ════════════════════════════════════════════════════════
// ENREGISTRER une analyse pour un don
// ════════════════════════════════════════════════════════
if (isset($_POST['enregistrer_analyse'])) {
    $id_don       = (int)$_POST['id_don'];
    $id_groupe    = (int)$_POST['groupe_confirme'];
    $hemoglobine  = trim($_POST['hemoglobine']);
    $tension      = trim($_POST['tension']);
    $poids        = trim($_POST['poids']);
    $vih          = isset($_POST['vih'])        ? 1 : 0;
    $hepatite_b   = isset($_POST['hepatite_b'])  ? 1 : 0;
    $hepatite_c   = isset($_POST['hepatite_c'])  ? 1 : 0;
    $syphilis     = isset($_POST['syphilis'])    ? 1 : 0;
    $note         = trim($_POST['note']);

    // ✔ Vérifier que le don existe, appartient à cette banque, est accepté et n'a pas déjà été analysé
    $stmt = $pdo->prepare("SELECT * FROM don WHERE id_don = ? AND id_banque = ?");
    $stmt->execute([$id_don, $id_banque]);
    $don = $stmt->fetch();

    $stmtChk = $pdo->prepare("SELECT id_analyse FROM analyse_sang WHERE id_don = ?");
    $stmtChk->execute([$id_don]);
    $deja_analyse = $stmtChk->fetch();

    $stmtGrp = $pdo->prepare("SELECT id_groupe FROM groupe_sanguin WHERE id_groupe = ?");
    $stmtGrp->execute([$id_groupe]);

    if (!$don) {
        $erreur = "Don introuvable.";
    } elseif ($don['statut'] !== 'accepté') {
        $erreur = "Ce don doit être accepté avant de pouvoir être analysé.";
    } elseif ($deja_analyse) {
        $erreur = "Ce don a déjà été analysé.";
    } elseif (!$stmtGrp->fetch()) {
        $erreur = "Groupe sanguin invalide.";
    } elseif ($hemoglobine === '' || $poids === '' || $tension === '') {
        $erreur = "Veuillez remplir tous les champs médicaux obligatoires.";
    } else {
        // Résultat global : non conforme si un seul test est positif
        $resultat_global = ($vih || $hepatite_b || $hepatite_c || $syphilis) ? 'non_conforme' : 'conforme';

        $pdo->beginTransaction();
        try {
            // 1) Insertion de l'analyse
            $stmtIns = $pdo->prepare("
                INSERT INTO analyse_sang
                (id_don, id_banque, groupe_confirme, hemoglobine, tension, poids,
                 vih, hepatite_b, hepatite_c, syphilis, resultat_global, note, date_analyse)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtIns->execute([
                $id_don, $id_banque, $id_groupe, $hemoglobine, $tension, $poids,
                $vih, $hepatite_b, $hepatite_c, $syphilis, $resultat_global,
                ($note !== '' ? $note : null)
            ]);

            if ($resultat_global === 'conforme') {
                // 2a) CONFORME → confirmer le groupe sanguin du donneur
                $pdo->prepare("
                    UPDATE donneur
                    SET id_groupe = ?, groupe_confirme = 1
                    WHERE id_donneur = ?
                ")->execute([$id_groupe, $don['id_donneur']]);

                $message_donneur = "Bonne nouvelle ! Votre don du " . date('d/m/Y', strtotime($don['date_don'])) .
                    " a été analysé et déclaré CONFORME. Merci pour votre générosité, votre don pourra être utilisé.";
            } else {
                // 2b) NON CONFORME → détruire les pochettes liées à ce don
                $pdo->prepare("
                    UPDATE pochette SET statut = 'detruite'
                    WHERE id_don = ? AND id_banque = ? AND statut = 'disponible'
                ")->execute([$id_don, $id_banque]);

                // Retirer la quantité du stock global (comme si le don n'avait jamais été accepté)
                $stmtStock = $pdo->prepare("SELECT * FROM stock WHERE id_banque = ? AND id_groupe = ?");
                $stmtStock->execute([$id_banque, $don['id_groupe']]);
                $stock = $stmtStock->fetch();

                if ($stock) {
                    $nouvelle_qte = max(0, $stock['quantite_disponible'] - $don['quantite']);
                    $pdo->prepare("
                        UPDATE stock SET quantite_disponible = ?, date_mise_a_jour = CURDATE()
                        WHERE id_stock = ?
                    ")->execute([$nouvelle_qte, $stock['id_stock']]);
                }

                $message_donneur = "Votre don du " . date('d/m/Y', strtotime($don['date_don'])) .
                    " a été analysé et déclaré NON CONFORME. Les pochettes concernées ont été détruites par mesure de sécurité. " .
                    "Veuillez vous rapprocher de votre banque de sang pour plus d'informations.";
            }

            // 3) Notification au donneur (table générique)
            $pdo->prepare("
                INSERT INTO notification (type_destinataire, id_destinataire, message, date_notification, lu)
                VALUES ('donneur', ?, ?, NOW(), 0)
            ")->execute([$don['id_donneur'], $message_donneur]);

            // 4) Journal d'activité
            enregistrerActivite($pdo, 'banque', $id_banque,
                'Analyse du don #' . $id_don . ' — résultat : ' . $resultat_global);

            $pdo->commit();

            $success = "Analyse enregistrée — résultat : " . ($resultat_global === 'conforme' ? "✅ Conforme" : "❌ Non conforme") . ".";
        } catch (Exception $e) {
            $pdo->rollBack();
            $erreur = "Une erreur est survenue lors de l'enregistrement de l'analyse.";
        }
    }
}

// ════════════════════════════════════════════════════════
// CHARGEMENT : dons acceptés EN ATTENTE d'analyse
// ════════════════════════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT don.*, g.libelle AS groupe, d.NNI
    FROM don
    JOIN groupe_sanguin g ON g.id_groupe = don.id_groupe
    LEFT JOIN donneur d ON d.id_donneur = don.id_donneur
    LEFT JOIN analyse_sang a ON a.id_don = don.id_don
    WHERE don.id_banque = ?
      AND don.statut = 'accepté'
      AND a.id_analyse IS NULL
    ORDER BY don.date_don ASC
");
$stmt->execute([$id_banque]);
$dons_en_attente = $stmt->fetchAll();

// ════════════════════════════════════════════════════════
// CHARGEMENT : historique des analyses déjà effectuées
// ════════════════════════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT a.*, don.date_don, don.quantite, don.id_donneur, g.libelle AS groupe, d.NNI
    FROM analyse_sang a
    JOIN don ON don.id_don = a.id_don
    JOIN groupe_sanguin g ON g.id_groupe = a.groupe_confirme
    LEFT JOIN donneur d ON d.id_donneur = don.id_donneur
    WHERE a.id_banque = ?
    ORDER BY a.date_analyse DESC
");
$stmt->execute([$id_banque]);
$analyses = $stmt->fetchAll();

// Récupérer les noms des donneurs en UNE seule requête (pas de N+1)
$tous_nni = array_filter(array_unique(array_merge(
    array_column($dons_en_attente, 'NNI'),
    array_column($analyses, 'NNI')
)));
$citoyens_par_nni = [];
if (!empty($tous_nni)) {
    $placeholders = implode(',', array_fill(0, count($tous_nni), '?'));
    $stmtCit = $pdo_registre->prepare("SELECT NNI, nom, prenom FROM citoyen WHERE NNI IN ($placeholders)");
    $stmtCit->execute(array_values($tous_nni));
    while ($row = $stmtCit->fetch()) {
        $citoyens_par_nni[$row['NNI']] = $row;
    }
}

$groupes = $pdo->query("SELECT * FROM groupe_sanguin ORDER BY libelle")->fetchAll();

// ════════════════════════════════════════════════════════
// STATISTIQUES
// ════════════════════════════════════════════════════════
$nb_en_attente   = count($dons_en_attente);
$nb_conformes    = 0;
$nb_non_conformes = 0;
foreach ($analyses as $a) {
    if ($a['resultat_global'] === 'conforme') $nb_conformes++;
    elseif ($a['resultat_global'] === 'non_conforme') $nb_non_conformes++;
}
$nb_total_analyses = count($analyses);

require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyses — <?php echo htmlspecialchars($_SESSION['nom_banque'] ?? 'Banque'); ?> | E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        /* ── Badges résultat analyse ── */
        .badge-conforme     { background: #F0FDF4; color: #166534; border: 2px solid #BBF7D0; }
        .badge-non-conforme { background: #FEF2F2; color: #8B0000; border: 2px solid #FCA5A5; }

        /* ── Checkbox tests sérologiques ── */
        .tests-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 4px;
        }
        .test-check {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #F9FAFB;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            padding: 12px 14px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .test-check:hover { border-color: #8B0000; background: #FEF2F2; }
        .test-check input[type="checkbox"] {
            width: 18px; height: 18px;
            accent-color: #8B0000;
            cursor: pointer;
        }
        .test-check label {
            font-size: 13px;
            font-weight: 700;
            color: #111111;
            cursor: pointer;
            margin: 0;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }
        .info-don-box {
            background: #FEF2F2;
            border: 2px solid #FCA5A5;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #8B0000;
            font-weight: 600;
        }
        .info-don-box strong { color: #111111; }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <!-- ══ TOP-BAR AGENT ══ -->
    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar"><?php echo $agent_initials; ?></div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bonjour, <?php echo $agent_display; ?></div>
                <div class="top-bar-role">Agent — <?php echo htmlspecialchars($_SESSION['nom_banque'] ?? 'Banque de sang'); ?></div>
            </div>
        </div>
    </div>

    <!-- ══ TITRE ══ -->
    <div class="page-header">
        <h1>Analyses des pochettes</h1>
        <p>Analysez les échantillons prélevés sur chaque don avant leur mise en circulation.</p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur);  ?></div><?php endif; ?>

    <!-- ══ STATS COMPACTES ══ -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">⏳</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">En attente d'analyse</div>
                <div class="stat-mini-number <?php echo $nb_en_attente > 0 ? 'alert' : ''; ?>"><?php echo $nb_en_attente; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">✅</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Conformes</div>
                <div class="stat-mini-number"><?php echo $nb_conformes; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">❌</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Non conformes</div>
                <div class="stat-mini-number"><?php echo $nb_non_conformes; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">🔬</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total analyses</div>
                <div class="stat-mini-number"><?php echo $nb_total_analyses; ?></div>
            </div>
        </div>
    </div>

    <!-- ══ TABLEAU : DONS EN ATTENTE D'ANALYSE ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Dons en attente d'analyse</div>
            <span class="cnt-badge"><?php echo count($dons_en_attente); ?> don(s)</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Donneur</th>
                        <th>Groupe déclaré</th>
                        <th>Quantité</th>
                        <th>Date du don</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dons_en_attente): ?>
                        <?php foreach ($dons_en_attente as $d): ?>
                        <?php $citoyen = !empty($d['NNI']) ? ($citoyens_par_nni[$d['NNI']] ?? null) : null; ?>
                        <tr>
                            <td>
                                <strong>Donneur #<?php echo $d['id_donneur']; ?></strong>
                                <?php if ($citoyen): ?>
                                <br><small style="color:#666;font-weight:500;">
                                    <?php echo htmlspecialchars($citoyen['prenom'] . ' ' . $citoyen['nom']); ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                            <td><strong><?php echo (int)$d['quantite']; ?></strong> pochette(s)</td>
                            <td><?php echo date('d/m/Y', strtotime($d['date_don'])); ?></td>
                            <td>
                                <button class="btn-submit" style="width:auto; margin-top:0; padding:8px 18px; font-size:13px;"
                                        onclick='ouvrirModalAnalyse(<?php echo (int)$d['id_don']; ?>, <?php echo (int)$d['id_groupe']; ?>, <?php echo (int)$d['id_donneur']; ?>, <?php echo (int)$d['quantite']; ?>, <?php echo json_encode(date('d/m/Y', strtotime($d['date_don']))); ?>)'>
                                    🔬 Analyser
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="vide">Aucun don en attente d'analyse pour le moment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ══ TABLEAU : HISTORIQUE DES ANALYSES ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Historique des analyses</div>
            <span class="cnt-badge"><?php echo count($analyses); ?> analyse(s)</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Donneur</th>
                        <th>Groupe confirmé</th>
                        <th>Hémoglobine</th>
                        <th>Tension</th>
                        <th>Date analyse</th>
                        <th>Résultat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($analyses): ?>
                        <?php foreach ($analyses as $a): ?>
                        <?php $citoyen = !empty($a['NNI']) ? ($citoyens_par_nni[$a['NNI']] ?? null) : null; ?>
                        <tr>
                            <td>
                                <strong>Donneur #<?php echo $a['id_donneur']; ?></strong>
                                <?php if ($citoyen): ?>
                                <br><small style="color:#666;font-weight:500;">
                                    <?php echo htmlspecialchars($citoyen['prenom'] . ' ' . $citoyen['nom']); ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($a['groupe']); ?></span></td>
                            <td><?php echo $a['hemoglobine'] !== null ? number_format($a['hemoglobine'], 1) . ' g/dL' : '—'; ?></td>
                            <td><?php echo htmlspecialchars($a['tension'] ?? '—'); ?> mmHg</td>
                            <td><?php echo date('d/m/Y', strtotime($a['date_analyse'])); ?></td>
                            <td>
                                <?php if ($a['resultat_global'] === 'conforme'): ?>
                                    <span class="badge badge-conforme">✅ Conforme</span>
                                <?php else: ?>
                                    <span class="badge badge-non-conforme">❌ Non conforme</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="vide">Aucune analyse enregistrée pour le moment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ══ MODAL : Effectuer une analyse ══ -->
<div class="modal" id="modalAnalyse">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Effectuer l'analyse d'un don</div>
            <button class="modal-close" onclick="fermerModalAnalyse()">×</button>
        </div>

        <div class="info-don-box" id="infoDonBox"></div>

        <form method="POST" action="analyse.php">
            <input type="hidden" name="id_don" id="modal_id_don">

            <div class="form-group">
                <label for="groupe_confirme">Groupe sanguin confirmé <span class="req">*</span></label>
                <select name="groupe_confirme" id="groupe_confirme" required>
                    <?php foreach ($groupes as $g): ?>
                        <option value="<?php echo $g['id_groupe']; ?>"><?php echo htmlspecialchars($g['libelle']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="hemoglobine">Hémoglobine (g/dL) <span class="req">*</span></label>
                    <input type="number" step="0.1" min="0" max="25" name="hemoglobine" id="hemoglobine" required placeholder="Ex : 13.5">
                </div>
                <div class="form-group">
                    <label for="tension">Tension (mmHg) <span class="req">*</span></label>
                    <input type="text" name="tension" id="tension" required placeholder="Ex : 120/80">
                </div>
                <div class="form-group">
                    <label for="poids">Poids (kg) <span class="req">*</span></label>
                    <input type="number" step="0.1" min="0" max="300" name="poids" id="poids" required placeholder="Ex : 70">
                </div>
            </div>

            <div class="form-group">
                <label>Tests sérologiques (cocher si POSITIF / détecté)</label>
                <div class="tests-grid">
                    <div class="test-check" onclick="document.getElementById('vih').click()">
                        <input type="checkbox" name="vih" id="vih" onclick="event.stopPropagation()">
                        <label for="vih" onclick="event.stopPropagation()">VIH</label>
                    </div>
                    <div class="test-check" onclick="document.getElementById('hepatite_b').click()">
                        <input type="checkbox" name="hepatite_b" id="hepatite_b" onclick="event.stopPropagation()">
                        <label for="hepatite_b" onclick="event.stopPropagation()">Hépatite B</label>
                    </div>
                    <div class="test-check" onclick="document.getElementById('hepatite_c').click()">
                        <input type="checkbox" name="hepatite_c" id="hepatite_c" onclick="event.stopPropagation()">
                        <label for="hepatite_c" onclick="event.stopPropagation()">Hépatite C</label>
                    </div>
                    <div class="test-check" onclick="document.getElementById('syphilis').click()">
                        <input type="checkbox" name="syphilis" id="syphilis" onclick="event.stopPropagation()">
                        <label for="syphilis" onclick="event.stopPropagation()">Syphilis</label>
                    </div>
                </div>
                <small style="color:#9CA3AF; font-size:12px;">⚠️ Si un seul test est positif, le don sera automatiquement déclaré <strong>non conforme</strong> et les pochettes seront détruites.</small>
            </div>

            <div class="form-group">
                <label for="note">Remarques médicales (optionnel)</label>
                <textarea name="note" id="note" placeholder="Observations du technicien..."></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="fermerModalAnalyse()">Annuler</button>
                <button type="submit" name="enregistrer_analyse" class="btn-submit" onclick="return confirm('Confirmer l\u2019enregistrement de cette analyse ? Cette action est irreversible.')">Enregistrer l'analyse</button>
            </div>
        </form>
    </div>
</div>

<script>
function ouvrirModalAnalyse(idDon, idGroupe, idDonneur, quantite, dateDon) {
    document.getElementById('modal_id_don').value = idDon;
    document.getElementById('groupe_confirme').value = idGroupe;
    document.getElementById('infoDonBox').innerHTML =
        'Don <strong>#' + idDon + '</strong> — Donneur #' + idDonneur + ' — <strong>' + quantite + '</strong> pochette(s) — Collecté le <strong>' + dateDon + '</strong>';
    document.getElementById('modalAnalyse').classList.add('active');
}
function fermerModalAnalyse() {
    document.getElementById('modalAnalyse').classList.remove('active');
}
document.getElementById('modalAnalyse').addEventListener('click', function(e) {
    if (e.target === this) fermerModalAnalyse();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') fermerModalAnalyse();
});
</script>

</body>
</html>