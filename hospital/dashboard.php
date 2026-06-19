<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hopital') {
    header('Location: ../index.php');
    exit;
}

// Vérifier que la session utilise le nouveau format (responsable_hopital)
if (!isset($_SESSION['id_hopital']) || !isset($_SESSION['id_responsable'])) {
    // Ancienne session détectée — forcer la reconnexion
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$id_hopital = $_SESSION['id_hopital'];
$id_banque = $_SESSION['id_banque'];
$id_responsable = $_SESSION['id_responsable'];
$page_active = 'dashboard';

// ── Statistiques des demandes ──
$stmt = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_hopital = ?");
$stmt->execute([$id_hopital]);
$nb_demandes = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_hopital = ? AND statut = 'en_attente'");
$stmt->execute([$id_hopital]);
$nb_attente = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_hopital = ? AND statut = 'acceptée'");
$stmt->execute([$id_hopital]);
$nb_acceptees = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_hopital = ? AND statut = 'refusée'");
$stmt->execute([$id_hopital]);
$nb_refusees = $stmt->fetchColumn();

// ── Dernières 5 demandes ──
$stmt = $pdo->prepare("
    SELECT d.*, b.nom AS nom_banque, g.libelle AS groupe
    FROM demande d
    JOIN banque_de_sang b ON b.id_banque = d.id_banque
    JOIN groupe_sanguin g ON g.id_groupe = d.id_groupe
    WHERE d.id_hopital = ?
    ORDER BY d.date_demande DESC
    LIMIT 5
");
$stmt->execute([$id_hopital]);
$demandes = $stmt->fetchAll();

// ── Données affichage ──
$prenom = htmlspecialchars($_SESSION['prenom_responsable'] ?? '');
$nom = htmlspecialchars($_SESSION['nom_responsable'] ?? '');
$poste = htmlspecialchars($_SESSION['poste_responsable'] ?? '');
$nom_hopital = htmlspecialchars($_SESSION['nom_hopital'] ?? '');

setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'fra');
$jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$mois = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$date_affichee = $jours[date('w')] . ' ' . date('d') . ' ' . $mois[date('n')] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — <?php echo $nom_hopital; ?> | E-Sang</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main-content">

    <!-- ══ BANNIÈRE DE BIENVENUE ══ -->
    <div style="background: #FFFFFF; border: 2px solid #E5E7EB; border-radius: 20px; padding: 32px 36px; margin-bottom: 36px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h1 style="font-size: 28px; font-weight: 800; margin-bottom: 6px; letter-spacing: -0.5px; color: #111111;">Bonjour, <?php echo $prenom . ' ' . $nom; ?></h1>
            <p style="font-size: 15px; color: #6B7280; font-weight: 500;"><?php echo $poste; ?> — <?php echo $nom_hopital; ?></p>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 14px; font-weight: 600; color: #6B7280;">📅 <?php echo $date_affichee; ?></div>
        </div>
    </div>

    <!-- ══ CARTES STATISTIQUES ══ -->
    <div class="stats" style="grid-template-columns: repeat(2, 1fr);">
        <div class="stat-card c1">
            <div class="stat-card-header">
                <span class="stat-label">Demandes envoyées</span>
                <span class="stat-icon ic-red">📋</span>
            </div>
            <span class="stat-number"><?php echo $nb_demandes; ?></span>
        </div>

        <div class="stat-card c2">
            <div class="stat-card-header">
                <span class="stat-label">Demandes en attente</span>
                <span class="stat-icon ic-org">⏳</span>
            </div>
            <span class="stat-number"><?php echo $nb_attente; ?></span>
        </div>

        <div class="stat-card c3">
            <div class="stat-card-header">
                <span class="stat-label">Demandes approuvées</span>
                <span class="stat-icon ic-grn">✅</span>
            </div>
            <span class="stat-number"><?php echo $nb_acceptees; ?></span>
        </div>

        <div class="stat-card c4">
            <div class="stat-card-header">
                <span class="stat-label">Demandes refusées</span>
                <span class="stat-icon ic-blu">❌</span>
            </div>
            <span class="stat-number"><?php echo $nb_refusees; ?></span>
        </div>
    </div>

    <!-- ══ DERNIÈRES DEMANDES ══ -->
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">Dernières demandes</h2>
            <a href="demandes.php" class="section-link">Voir toutes les demandes →</a>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Groupe</th>
                        <th>Quantité</th>
                        <th>Banque</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($demandes) === 0): ?>
                        <tr><td colspan="6" class="vide">Vous n'avez encore envoyé aucune demande de sang.</td></tr>
                    <?php else: ?>
                        <?php foreach ($demandes as $d): ?>
                            <?php
                            $statut = $d['statut'];
                            switch ($statut) {
                                case 'en_attente': $badge_s = 'badge-attente'; $label_s = 'En attente'; break;
                                case 'acceptée':   $badge_s = 'badge-acceptee'; $label_s = 'Approuvée'; break;
                                case 'refusée':    $badge_s = 'badge-refusee'; $label_s = 'Refusée'; break;
                                case 'annulée':    $badge_s = 'badge-annulee'; $label_s = 'Annulée'; break;
                                default:           $badge_s = 'badge-attente'; $label_s = $statut; break;
                            }
                            ?>
                            <tr>
                                <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                                <td style="font-weight: 800;"><?php echo $d['quantite_demandee']; ?> poches</td>
                                <td><?php echo htmlspecialchars($d['nom_banque']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($d['date_demande'])); ?></td>
                                <td><span class="badge <?php echo $badge_s; ?>"><?php echo $label_s; ?></span></td>
                                <td>
                                    <?php if ($statut === 'en_attente'): ?>
                                        <a href="demandes.php?annuler=<?php echo $d['id_demande']; ?>" class="btn-del" onclick="return confirm('Êtes-vous sûr de vouloir annuler cette demande ?');">Annuler la demande</a>
                                    <?php else: ?>
                                        <span style="color: #9CA3AF; font-size: 12px;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
</body>
</html>