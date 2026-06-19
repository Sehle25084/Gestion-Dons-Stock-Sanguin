<?php
session_start();
require_once '../config/db.php'; // Connexion à ta base de données

// Sécurité : Vérification du rôle
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sous_banque') {
    header("Location: ../index.php");
    exit;
}

$id_sous_banque = $_SESSION['id_sous_banque'];
$id_banque_principale = $_SESSION['id_banque_principale'] ?? 1; // Par défaut 1 si non défini
$message_success = "";
$message_erreur = "";

// ══════════════════════════════════════
// COMMANDE MANUELLE À LA BANQUE PRINCIPALE
// ══════════════════════════════════════
if (isset($_POST['creer_commande_manuelle'])) {
    $id_groupe = (int)$_POST['id_groupe'];
    $quantite = (int)$_POST['quantite'];
    $note = trim($_POST['note'] ?? '');
    
    // Récupérer le id_hopital rattaché
    $stmtSB = $pdo->prepare("SELECT id_hopital FROM sous_banque WHERE id_sous_banque = ?");
    $stmtSB->execute([$id_sous_banque]);
    $id_hopital = $stmtSB->fetchColumn() ?: null;
    
    if ($id_groupe > 0 && $quantite > 0 && $id_hopital) {
        $pdo->prepare("
            INSERT INTO demande 
            (id_hopital, id_sous_banque, id_banque, id_groupe, quantite_demandee, date_demande, statut, type_demande, note) 
            VALUES (?, ?, ?, ?, ?, CURDATE(), 'en_attente', 'externe', ?)
        ")->execute([$id_hopital, $id_sous_banque, $id_banque_principale, $id_groupe, $quantite, $note]);
        
        $message_success = "Votre commande manuelle de réapprovisionnement a été envoyée avec succès à la Banque Principale.";
    } else {
        $message_erreur = "Veuillez remplir correctement tous les champs de la commande.";
    }
}

// Charger tous les groupes sanguins pour le formulaire
$groupes = $pdo->query("SELECT * FROM groupe_sanguin ORDER BY libelle")->fetchAll(PDO::FETCH_ASSOC);

// ══════════════════════════════════════
// TRAITEMENT AUTOMATIQUE DES DEMANDES
// ══════════════════════════════════════
if (isset($_POST['action_demande'])) {
    $id_demande = (int)$_POST['id_demande'];

    try {
        $pdo->beginTransaction();

        // 1. Récupérer la demande interne de l'hôpital rattaché
        $stmt = $pdo->prepare("SELECT * FROM demande WHERE id_demande = ? AND id_sous_banque = ? AND type_demande = 'interne' AND statut = 'en_attente'");
        $stmt->execute([$id_demande, $id_sous_banque]);
        $demande = $stmt->fetch();

        if ($demande) {
            $id_groupe = (int)$demande['id_groupe'];
            $quantite_demandee = (int)$demande['quantite_demandee'];

            // 2. Vérifier le stock disponible réel de la sous-banque pour ce groupe
            $stmt_stock = $pdo->prepare("SELECT quantite_disponible, seuil_alerte FROM stock_sous_banque WHERE id_sous_banque = ? AND id_groupe = ?");
            $stmt_stock->execute([$id_sous_banque, $id_groupe]);
            $stock = $stmt_stock->fetch();
            
            $stock_actuel = $stock ? (int)$stock['quantite_disponible'] : 0;
            $seuil_alerte = $stock ? (int)$stock['seuil_alerte'] : 3;

            if ($stock_actuel >= $quantite_demandee) {
                // ── CAS A : STOCK SUFFISANT ──> APPROBATION & SOUSTRACTION AUTO
                
                // Mettre à jour la demande
                $pdo->prepare("UPDATE demande SET statut = 'acceptee' WHERE id_demande = ?")->execute([$id_demande]);

                // Diminuer le stock automatiquement
                $pdo->prepare("UPDATE stock_sous_banque SET quantite_disponible = quantite_disponible - ?, date_mise_a_jour = NOW() WHERE id_sous_banque = ? AND id_groupe = ?")
                    ->execute([$quantite_demandee, $id_sous_banque, $id_groupe]);

                // Ajouter l'historique dans mouvement_stock
                $note = "Sortie automatique — Validation de la demande hôpital #" . $id_demande;
                $pdo->prepare("INSERT INTO mouvement_stock (id_sous_banque, id_groupe, quantite, type_mouvement, date_mouvement, note) VALUES (?, ?, ?, 'sortie', NOW(), ?)")
                    ->execute([$id_sous_banque, $id_groupe, $quantite_demandee, $note]);

                $message_success = "La demande #" . $id_demande . " a été acceptée. Le stock a diminué automatiquement de " . $quantite_demandee . " poche(s).";

            } else {
                // ── CAS B : STOCK INSUFFISANT ──> REJET AUTO (PAS DE COMMANDE AUTOMATIQUE)
                
                // Mettre la demande de l'hôpital en rejeté
                $pdo->prepare("UPDATE demande SET statut = 'refusee', note = 'Refus automatique : Stock insuffisant au dépôt' WHERE id_demande = ?")->execute([$id_demande]);

                $message_erreur = "Stock insuffisant ! La demande #" . $id_demande . " a été refusée. Vous pouvez envoyer une commande de réapprovisionnement manuelle à la banque principale.";
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message_erreur = "Une erreur est survenue : " . $e->getMessage();
    }
}

// ══════════════════════════════════════
// CHARGEMENT DES TABLEAUX
// ══════════════════════════════════════

// 1. Demandes de l'hôpital (type_demande = 'interne')
$stmt = $pdo->prepare("SELECT d.*, g.libelle as groupe_nom FROM demande d JOIN groupe_sanguin g ON d.id_groupe = g.id_groupe WHERE d.id_sous_banque = ? AND d.type_demande = 'interne' ORDER BY d.id_demande DESC");
$stmt->execute([$id_sous_banque]);
$demandes_internes = $stmt->fetchAll();

// 2. Demandes envoyées à la Banque Principale (type_demande = 'externe')
$stmt = $pdo->prepare("SELECT d.*, g.libelle as groupe_nom FROM demande d JOIN groupe_sanguin g ON d.id_groupe = g.id_groupe WHERE d.id_sous_banque = ? AND d.type_demande = 'externe' ORDER BY d.id_demande DESC");
$stmt->execute([$id_sous_banque]);
$demandes_externes = $stmt->fetchAll();

$page_active = 'demandes';
include 'sidebar.php'; // On appelle l'Inclusion unique de la barre latérale ICI !
?>

<style>
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); backdrop-filter:blur(3px); z-index:999; align-items:center; justify-content:center; }
    .modal-overlay.open { display:flex; }
    .modal { background:#FFFFFF; border-radius:20px; width:100%; max-width:420px; padding:28px; box-shadow:0 20px 50px rgba(0,0,0,0.15); animation:slideUp 0.2s ease; }
    .modal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:22px; padding-bottom:14px; border-bottom:2px solid #F3F4F6; }
    .modal-title { font-size:17px; font-weight:800; color:#111111; display:flex; align-items:center; gap:10px; }
    .modal-title::before { content:''; display:block; width:4px; height:20px; background:#6B0000; border-radius:99px; }
    .modal-close { width:32px; height:32px; border-radius:8px; background:#F3F4F6; border:none; cursor:pointer; font-size:16px; color:#6B7280; display:flex; align-items:center; justify-content:center; }
    .modal-close:hover { background:#FEF2F2; color:#6B0000; }
    .btn-nouvelle { background:#6B0000; color:white; border:none; padding:10px 18px; border-radius:8px; cursor:pointer; font-weight:700; font-size:14px; display:inline-flex; align-items:center; gap:6px; transition: background 0.15s; }
    .btn-nouvelle:hover { background:#4C0000; }
</style>

<div class="main-content">
    
    <div class="page-header" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-size: 28px; font-weight: 800; color: #111827; margin: 0 0 4px 0;">Gestion des Demandes</h1>
            <p style="color: #6B7280; margin: 0; font-size: 14px;">Suivi automatisé des flux de poches de sang entrantes et sortantes.</p>
        </div>
        <button class="btn-nouvelle" onclick="ouvrirModalCommande()">
            ➕ Nouvelle commande BP
        </button>
    </div>

    <?php if ($message_success): ?>
        <div style="background:#DEF7EC; color:#03543F; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600; font-size:14px;">✅ <?php echo $message_success; ?></div>
    <?php endif; ?>
    <?php if ($message_erreur): ?>
        <div style="background:#FDE8E8; color:#9B1C1C; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600; font-size:14px;">⚠️ <?php echo $message_erreur; ?></div>
    <?php endif; ?>

    <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 30px;">
        <h2 style="font-size: 18px; font-weight: 700; color: #111827; margin-top:0; margin-bottom:15px;">🩸 Demandes de l'Hôpital Rattaché (Internes)</h2>
        <div style="overflow-x: auto;">
            <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                <thead>
                    <tr style="background:#F9FAFB; color:#4B5563;">
                        <th style="padding:12px; border-bottom:1px solid #E5E7EB;">ID</th>
                        <th style="padding:12px; border-bottom:1px solid #E5E7EB;">Groupe</th>
                        <th style="padding:12px; border-bottom:1px solid #E5E7EB;">Quantité</th>
                        <th style="padding:12px; border-bottom:1px solid #E5E7EB;">Date Demande</th>
                        <th style="padding:12px; border-bottom:1px solid #E5E7EB;">Statut</th>
                        <th style="padding:12px; border-bottom:1px solid #E5E7EB;">Action Auto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($demandes_internes)): ?>
                        <tr><td colspan="6" style="padding:20px; text-align:center; color:#9CA3AF;">Aucune demande reçue.</td></tr>
                    <?php else: ?>
                        <?php foreach($demandes_internes as $d): ?>
                            <tr>
                                <td style="padding:12px; border-bottom:1px solid #F3F4F6;">#<?php echo $d['id_demande']; ?></td>
                                <td style="padding:12px; border-bottom:1px solid #F3F4F6;"><span style="background:#FDE8E8; color:#9B1C1C; padding:3px 8px; border-radius:4px; font-weight:700;"><?php echo htmlspecialchars($d['groupe_nom']); ?></span></td>
                                <td style="padding:12px; border-bottom:1px solid #F3F4F6; font-weight:bold;"><?php echo (int)$d['quantite_demandee']; ?> pochette(s)</td>
                                <td style="padding:12px; border-bottom:1px solid #F3F4F6;"><?php echo $d['date_demande']; ?></td>
                                <td style="padding:12px; border-bottom:1px solid #F3F4F6;">
                                    <?php if($d['statut'] == 'en_attente'): ?>
                                        <span style="background:#FEF3C7; color:#D97706; padding:4px 8px; border-radius:12px; font-weight:600; font-size:12px;">En attente</span>
                                    <?php elseif($d['statut'] == 'acceptee'): ?>
                                        <span style="background:#DEF7EC; color:#03543F; padding:4px 8px; border-radius:12px; font-weight:600; font-size:12px;">Acceptée (- Stock)</span>
                                    <?php else: ?>
                                        <span style="background:#FDE8E8; color:#9B1C1C; padding:4px 8px; border-radius:12px; font-weight:600; font-size:12px;">Refusée (Sans Stock)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px; border-bottom:1px solid #F3F4F6;">
                                    <?php if($d['statut'] == 'en_attente'): ?>
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="id_demande" value="<?php echo $d['id_demande']; ?>">
                                            <button type="submit" name="action_demande" value="traiter" style="background:#6B0000; color:white; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-weight:600; font-size:12px;">
                                                Traiter automatiquement
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:#9CA3AF; font-style:italic;">Traité</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <h2 style="font-size: 18px; font-weight: 700; color: #111827; margin-top:0; margin-bottom:15px;">🏢 Suivi des commandes à la Banque Principale (Externes)</h2>
        <div style="overflow-x: auto;">
            <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px;">
                <thead>
                    <tr style="background:#F9FAFB; color:#4B5563;">
                        <th style="padding:12px; border-bottom:1px solid #E5E7EB;">ID Commande</th>
                        <th style="padding:12px; border-bottom:1px solid #E5E7EB;">Groupe</th>
                        <th style="padding:12px; border-bottom:1px solid #E5E7EB;">Quantité Demandée</th>
                        <th style="padding:12px; border-bottom:1px solid #E5E7EB;">Date d'Envoi</th>
                        <th style="padding:12px; border-bottom:1px solid #E5E7EB;">Statut de livraison</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($demandes_externes)): ?>
                        <tr><td colspan="5" style="padding:20px; text-align:center; color:#9CA3AF;">Aucun réapprovisionnement automatique lancé.</td></tr>
                    <?php else: ?>
                        <?php foreach($demandes_externes as $e): ?>
                            <tr>
                                <td style="padding:12px; border-bottom:1px solid #F3F4F6;">#<?php echo $e['id_demande']; ?></td>
                                <td style="padding:12px; border-bottom:1px solid #F3F4F6;"><span style="background:#E0F2FE; color:#0369A1; padding:3px 8px; border-radius:4px; font-weight:700;"><?php echo htmlspecialchars($e['groupe_nom']); ?></span></td>
                                <td style="padding:12px; border-bottom:1px solid #F3F4F6; font-weight:bold;"><?php echo (int)$e['quantite_demandee']; ?> poche(s)</td>
                                <td style="padding:12px; border-bottom:1px solid #F3F4F6;"><?php echo $e['date_demande']; ?></td>
                                <td style="padding:12px; border-bottom:1px solid #F3F4F6;">
                                    <?php if($e['statut'] == 'en_attente'): ?>
                                        <span style="background:#FEF3C7; color:#D97706; padding:4px 8px; border-radius:12px; font-weight:600; font-size:11px;">En cours d'examen à la BP</span>
                                    <?php elseif($e['statut'] == 'acceptee'): ?>
                                        <span style="background:#DEF7EC; color:#03543F; padding:4px 8px; border-radius:12px; font-weight:600; font-size:11px;">Validée — Expédiée</span>
                                    <?php else: ?>
                                        <span style="background:#FDE8E8; color:#9B1C1C; padding:4px 8px; border-radius:12px; font-weight:600; font-size:11px;">Annulée</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- MODAL COMMANDE MANUELLE -->
<div class="modal-overlay" id="modalCommande" onclick="fermerModalOverlay(event)">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Nouvelle commande (Banque de Sang)</div>
            <button class="modal-close" onclick="fermerModal()">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="creer_commande_manuelle" value="1" />
            
            <div class="form-group">
                <label for="id_groupe">Groupe Sanguin</label>
                <select name="id_groupe" id="id_groupe" required style="width: 100%; padding: 10px 12px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; background:white;">
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($groupes as $g): ?>
                        <option value="<?php echo $g['id_groupe']; ?>"><?php echo htmlspecialchars($g['libelle']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-top: 14px;">
                <label for="quantite">Quantité (Pochettes)</label>
                <input type="number" name="quantite" id="quantite" min="1" max="100" required style="width: 100%; padding: 10px 12px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px;" placeholder="Ex: 5" />
            </div>

            <div class="form-group" style="margin-top: 14px;">
                <label for="note">Note ou justification</label>
                <textarea name="note" id="note" style="width: 100%; padding: 10px 12px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; font-family: inherit; height: 80px;" placeholder="Détails (optionnel)..."></textarea>
            </div>
            
            <button type="submit" style="width: 100%; background: #6B0000; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; margin-top: 20px; transition: background 0.15s;">
                Envoyer la commande
            </button>
        </form>
    </div>
</div>

<script>
function ouvrirModalCommande() {
    document.getElementById('modalCommande').classList.add('open');
}
function fermerModal() {
    document.getElementById('modalCommande').classList.remove('open');
}
function fermerModalOverlay(e) {
    if (e.target === document.getElementById('modalCommande')) fermerModal();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') fermerModal(); });
</script>

</body>
</html>