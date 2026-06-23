<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hopital') {
    header('Location: ../index.php');
    exit;
}

// Vérifier que la session utilise le nouveau format (responsable_hopital)
if (!isset($_SESSION['id_hopital']) || !isset($_SESSION['id_responsable'])) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$id_hopital = $_SESSION['id_hopital'];
$id_banque = $_SESSION['id_banque'];
$id_responsable = $_SESSION['id_responsable'];

// ── Statistiques des demandes ──
$nb_demandes = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_hopital = ?");
$nb_demandes->execute([$id_hopital]);
$nb_demandes = $nb_demandes->fetchColumn();

$nb_attente = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_hopital = ? AND statut = 'en_attente'");
$nb_attente->execute([$id_hopital]);
$nb_attente = $nb_attente->fetchColumn();

$nb_acceptees = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_hopital = ? AND statut = 'acceptée'");
$nb_acceptees->execute([$id_hopital]);
$nb_acceptees = $nb_acceptees->fetchColumn();

$nb_refusees = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_hopital = ? AND statut = 'refusée'");
$nb_refusees->execute([$id_hopital]);
$nb_refusees = $nb_refusees->fetchColumn();

// ── Dernières 5 demandes (Jointure avec SOUS-BANQUE) ──
// ✔ CORRECTION : alias renommé en `nom_sous_banque` pour refléter la réalité
//   (avant : alias trompeur `nom_banque` qui faisait croire à une banque principale)
$stmt = $pdo->prepare("
    SELECT d.*, s.nom AS nom_sous_banque, g.libelle AS groupe
    FROM demande d
    LEFT JOIN sous_banque s ON s.id_sous_banque = d.id_sous_banque
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

$jours_fr  = ['Sunday'=>'dimanche', 'Monday'=>'lundi', 'Tuesday'=>'mardi', 'Wednesday'=>'mercredi', 'Thursday'=>'jeudi', 'Friday'=>'vendredi', 'Saturday'=>'samedi'];
$mois_fr   = ['January'=>'janvier', 'February'=>'février', 'March'=>'mars', 'April'=>'avril', 'May'=>'mai', 'June'=>'juin', 'July'=>'juillet', 'August'=>'août', 'September'=>'septembre', 'October'=>'octobre', 'November'=>'novembre', 'December'=>'décembre'];
$date_fr   = $jours_fr[date('l')] . ' ' . date('j') . ' ' . $mois_fr[date('F')] . ' ' . date('Y');

$page_active = 'dashboard';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — <?php echo $nom_hopital; ?> | E-Sang</title>
    <style>
        <?php echo $shared_css; ?>
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<main class="main-content">

    <!-- ── Bandeau "Responsable" en haut de page ── -->
    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar">
                <?php echo strtoupper(substr($prenom, 0, 1) . substr($nom, 0, 1)); ?>
            </div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bonjour, <?php echo $prenom . ' ' . $nom; ?></div>
                <div class="top-bar-role"><?php echo $poste; ?> — <?php echo $nom_hopital; ?></div>
            </div>
        </div>
        <div class="top-bar-date">
            📅 <?php echo $date_fr; ?>
        </div>
    </div>

    <div class="page-header">
        <h1>Tableau de bord</h1>
        <p>Aperçu de vos demandes de sang</p>
    </div>

    <!-- ══ CARTES STATISTIQUES ══ -->
    <div class="stats" style="grid-template-columns: repeat(4, 1fr);">
        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-label">Total Demandes</span>
                <span class="stat-icon ic-red">📋</span>
            </div>
            <span class="stat-number"><?php echo $nb_demandes; ?></span>
        </div>

        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-label">En attente</span>
                <span class="stat-icon ic-org">⏳</span>
            </div>
            <span class="stat-number"><?php echo $nb_attente; ?></span>
        </div>

        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-label">Approuvées</span>
                <span class="stat-icon ic-grn">✅</span>
            </div>
            <span class="stat-number"><?php echo $nb_acceptees; ?></span>
        </div>

        <div class="stat-card">
            <div class="stat-card-header">
                <span class="stat-label">Refusées</span>
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
                        <th>Sous-banque</th>
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
                                <td><?php echo htmlspecialchars($d['nom_sous_banque'] ?? 'Non assignée'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($d['date_demande'])); ?></td>
                                <td><span class="badge <?php echo $badge_s; ?>"><?php echo $label_s; ?></span></td>
                                <td>
                                    <div class="actions-cell">
                                        <?php if ($statut === 'en_attente'): ?>
                                            <a href="demandes.php?annuler=<?php echo $d['id_demande']; ?>" class="btn-del" onclick="return confirm('Êtes-vous sûr de vouloir annuler cette demande ?');">Annuler</a>
                                        <?php else: ?>
                                            <span style="color: #9CA3AF; font-size: 12px;">—</span>
                                        <?php endif; ?>
                                    </div>
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