<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hopital') {
    header("Location: ../index.php");
    exit;
}

$id_hopital = $_SESSION['id'];
$erreur = $success = "";

// ── Traitement du formulaire modal ──
if (isset($_POST['envoyer'])) {
    $id_banque      = $_POST['id_banque']    ?? '';
    $id_groupe      = $_POST['id_groupe']    ?? '';
    $quantite       = $_POST['quantite']     ?? '';
    $note           = $_POST['note']         ?? '';
    $urgence        = isset($_POST['urgence']) ? 1 : 0;
    $date_souhaitee = $_POST['date_souhaitee'] ?? null;
    $date_demande   = date('Y-m-d');

    if (empty($id_banque) || empty($id_groupe) || empty($quantite)) {
        $erreur = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($quantite <= 0) {
        $erreur = "La quantité doit être supérieure à 0.";
    } else {
        $pdo->prepare("
            INSERT INTO demande (id_hopital, id_banque, id_groupe, quantite_demandee, date_demande, statut, note, urgence, date_souhaitee)
            VALUES (?, ?, ?, ?, ?, 'en_attente', ?, ?, ?)
        ")->execute([$id_hopital, $id_banque, $id_groupe, $quantite, $date_demande, $note, $urgence, $date_souhaitee ?: null]);

        header("Location: dashboard.php?success=1");
        exit;
    }
}

if (isset($_GET['success'])) $success = "Votre demande a été envoyée avec succès !";

// ── Statistiques ──
$stmt = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_hopital = ?");
$stmt->execute([$id_hopital]); $nb_demandes = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_hopital = ? AND statut = 'en_attente'");
$stmt->execute([$id_hopital]); $nb_attente = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_hopital = ? AND statut = 'acceptée'");
$stmt->execute([$id_hopital]); $nb_acceptees = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_hopital = ? AND statut = 'refusée'");
$stmt->execute([$id_hopital]); $nb_refusees = $stmt->fetchColumn();

// ── Historique ──
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

// ── Listes pour le formulaire ──
$banques = $pdo->query("SELECT * FROM banque_de_sang ORDER BY nom")->fetchAll();
$groupes = $pdo->query("SELECT * FROM groupe_sanguin ORDER BY libelle")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord | Gestion des demandes de sang</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #FAFAFA;
            color: #111111;
            font-size: 15px;
            line-height: 1.5;
            display: flex;
            min-height: 100vh;
        }

        /* ══ SIDEBAR ══ */
        .sidebar {
            width: 260px; min-width: 260px;
            background: #FFFFFF;
            border-right: 1px solid #E5E7EB;
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0;
            height: 100vh; z-index: 100;
        }

        .sidebar-logo {
            display: flex; align-items: center;
            padding: 12px 20px 8px;
            border-bottom: 1px solid #E5E7EB;
        }

        .logo-sub { font-size: 11px; color: #111111; font-weight: 500; }

        .sidebar-nav {
            flex: 1; padding: 10px 12px;
            display: flex; flex-direction: column; gap: 4px;
        }

        .nav-label {
            font-size: 10px; font-weight: 700; color: #111111;
            letter-spacing: 0.08em; text-transform: uppercase;
            padding: 8px 10px 4px;
        }

        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 14px; border-radius: 10px;
            font-size: 14px; font-weight: 600; color: #111111;
            text-decoration: none;
            border: 1.5px solid #E5E7EB; background: #FFFFFF;
            width: 100%; text-align: left; cursor: pointer;
            transition: all 0.15s ease;
        }

        .nav-item:hover { background: #FEF2F2; color: #8B0000; border-color: #8B0000; }
        .nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }

        .nav-item.nouvelle {
            background: #8B0000; color: #FFFFFF;
            font-weight: 700; font-size: 15px;
            padding: 14px 18px; border-radius: 12px;
            border: none; margin-bottom: 6px;
        }

        .nav-item.nouvelle:hover { background: #6B0000; color: #FFFFFF; }
        .nav-item.nouvelle svg { width: 20px; height: 20px; }

        .sidebar-footer { padding: 16px; border-top: 1px solid #E5E7EB; }

        .hospital-info { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }

        .avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: #FEE2E2;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; color: #8B0000; flex-shrink: 0;
        }

        .hospital-name { font-size: 13px; font-weight: 700; color: #111111; }
        .hospital-type { font-size: 11px; color: #111111; font-weight: 500; }

        .btn-disconnect {
            width: 100%; padding: 9px;
            border: 1.5px solid #8B0000; border-radius: 10px;
            background: none; color: #8B0000;
            font-size: 13px; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            text-decoration: none; transition: all 0.15s ease;
        }

        .btn-disconnect:hover { background: #FEF2F2; }

        /* ══ CONTENU ══ */
        .main-content { margin-left: 260px; flex: 1; padding: 40px; min-height: 100vh; }

        .page-header { margin-bottom: 36px; }
        .page-header h1 { font-size: 32px; font-weight: 800; color: #111111; letter-spacing: -1px; }
        .page-header p  { color: #444444; font-size: 15px; margin-top: 6px; }

        /* ══ ALERTE SUCCÈS ══ */
        .alerte-success {
            background: #F0FDF4; border: 2px solid #BBF7D0;
            color: #166534; border-radius: 14px;
            padding: 14px 20px; font-size: 14px; font-weight: 600;
            margin-bottom: 28px; display: flex; align-items: center; gap: 10px;
        }

        /* ══ STATS ══ */
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 48px; }

        .stat-card {
            background: #FFFFFF; border: 2px solid #E5E7EB;
            border-radius: 20px; padding: 28px 24px;
            display: flex; flex-direction: column; gap: 14px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 12px 20px -5px rgba(0,0,0,0.08); }
        .stat-card.card-all:hover  { border-color: #111111; }
        .stat-card.card-wait:hover { border-color: #EA580C; }
        .stat-card.card-ok:hover   { border-color: #16A34A; }
        .stat-card.card-no:hover   { border-color: #8B0000; }

        .stat-card-header { display: flex; align-items: center; justify-content: space-between; }

        .stat-label { font-size: 13px; font-weight: 800; color: #111111; text-transform: uppercase; letter-spacing: 0.5px; }

        .stat-icon {
            font-size: 22px; display: flex; align-items: center; justify-content: center;
            width: 46px; height: 46px; border-radius: 12px; border: 2px solid transparent;
        }

        .stat-number { font-size: 52px; font-weight: 800; color: #111111; line-height: 1; letter-spacing: -2px; }

        .ic-all  { background: #F3F4F6; color: #111111; border-color: #D1D5DB; }
        .ic-wait { background: #FFF7ED; color: #EA580C; border-color: #FED7AA; }
        .ic-ok   { background: #F0FDF4; color: #16A34A; border-color: #BBF7D0; }
        .ic-no   { background: #FEF2F2; color: #8B0000; border-color: #FCA5A5; }

        /* ══ SECTION TABLEAU ══ */
        .section { background: #FFFFFF; border: 2px solid #E5E7EB; border-radius: 20px; padding: 30px; }

        .section-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 26px; padding-bottom: 18px; border-bottom: 2px solid #F3F4F6;
        }

        .section-title { font-size: 20px; font-weight: 800; color: #111111; display: flex; align-items: center; gap: 12px; }
        .section-title::before { content: ''; display: block; width: 5px; height: 24px; background: #8B0000; border-radius: 99px; }

        .table-wrapper { border: 2px solid #E5E7EB; border-radius: 14px; overflow: hidden; }

        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        table thead { background: #F9FAFB; }
        table th { text-align: left; color: #111111; font-weight: 800; padding: 18px 20px; text-transform: uppercase; letter-spacing: 0.5px; font-size: 12px; border-bottom: 2px solid #E5E7EB; }
        table td { padding: 18px 20px; border-bottom: 2px solid #F3F4F6; color: #111111; font-weight: 600; transition: background 0.15s ease; }
        table tr:last-child td { border-bottom: none; }
        table tbody tr:hover td { background: #F9FAFB; }

        .vide { text-align: center; color: #444444; font-weight: 600; padding: 60px !important; font-size: 15px; }

        .badge { display: inline-flex; align-items: center; font-weight: 700; padding: 6px 14px; border-radius: 999px; font-size: 12px; }
        .badge-groupe   { background: #111111; color: #FFFFFF; border: 1px solid #111111; }
        .badge-attente  { background: #FFF7ED; color: #C2410C; border: 2px solid #FED7AA; }
        .badge-acceptee { background: #F0FDF4; color: #166534; border: 2px solid #BBF7D0; }
        .badge-refusee  { background: #FEF2F2; color: #8B0000; border: 2px solid #FCA5A5; }

        /* ══ MODAL ══ */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 999;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.open { display: flex; }

        .modal {
            background: #FFFFFF;
            border-radius: 24px;
            width: 100%;
            max-width: 560px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 36px;
            position: relative;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.18);
            animation: slideUp 0.25s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 28px; padding-bottom: 20px;
            border-bottom: 2px solid #F3F4F6;
        }

        .modal-title {
            font-size: 20px; font-weight: 800; color: #111111;
            display: flex; align-items: center; gap: 12px;
        }

        .modal-title::before {
            content: ''; display: block;
            width: 5px; height: 24px;
            background: #8B0000; border-radius: 99px;
        }

        .modal-close {
            width: 36px; height: 36px; border-radius: 10px;
            background: #F3F4F6; border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: #6B7280;
            transition: all 0.15s ease;
        }

        .modal-close:hover { background: #FEF2F2; color: #8B0000; }

        .modal-erreur {
            background: #FEF2F2; border: 1.5px solid #FECACA;
            color: #8B0000; border-radius: 10px;
            padding: 12px 16px; font-size: 13px; font-weight: 600;
            margin-bottom: 20px; display: flex; align-items: center; gap: 8px;
        }

        .form-sep {
            font-size: 10px; font-weight: 800; color: #111111;
            text-transform: uppercase; letter-spacing: 0.6px;
            margin: 20px 0 14px;
            padding-bottom: 8px; border-bottom: 1.5px solid #F3F4F6;
        }

        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }

        .form-group label {
            font-size: 11px; font-weight: 800; color: #111111;
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 11px 14px;
            border: 1.5px solid #E5E7EB; border-radius: 10px;
            font-size: 14px; background: #FFFFFF;
            outline: none; font-family: inherit; color: #111111;
            transition: border-color 0.15s; width: 100%;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { border-color: #8B0000; box-shadow: 0 0 0 3px rgba(139,0,0,0.07); }

        .form-group textarea { resize: vertical; min-height: 80px; }

        .urgence-row {
            display: flex; align-items: center; gap: 10px;
            background: #FEF2F2; border: 1.5px solid #FECACA;
            border-radius: 10px; padding: 12px 16px;
            margin-bottom: 16px; cursor: pointer;
            transition: background 0.15s;
        }

        .urgence-row:hover { background: #FEE2E2; }
        .urgence-row input[type="checkbox"] { accent-color: #8B0000; width: 16px; height: 16px; }
        .urgence-row span { font-size: 13px; font-weight: 700; color: #8B0000; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        .btn-submit {
            width: 100%; padding: 14px;
            background: #8B0000; color: #FFFFFF;
            border: none; border-radius: 12px;
            font-size: 15px; font-weight: 700;
            cursor: pointer; margin-top: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 16px rgba(139,0,0,0.28);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }

        .btn-submit:hover { background: #6B0000; transform: translateY(-1px); }
    </style>
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div>
            <svg width="210" height="70" viewBox="0 0 210 70" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M22 46C31.941 46 40 37.941 40 28C40 20.5 33 11 22 2C11 11 4 20.5 4 28C4 37.941 12.059 46 22 46Z" fill="#8B0000"/>
                <rect x="18.5" y="13" width="7" height="22" rx="3" fill="white"/>
                <rect x="11" y="20.5" width="22" height="7" rx="3" fill="white"/>
                <text x="50" y="30" font-family="Arial Black, Arial, sans-serif" font-size="26" font-weight="900" fill="#1a1a1a" letter-spacing="-0.5">E-Sang</text>
                <text x="50" y="50" font-family="Arial, sans-serif" font-size="12" font-weight="500" fill="#444444">Hôpital — <?php echo htmlspecialchars($_SESSION['nom']); ?></text>
            </svg>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Navigation</div>

        <button class="nav-item nouvelle" onclick="ouvrirModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nouvelle demande
        </button>
    </nav>

    <div class="sidebar-footer">
        <div class="hospital-info">
            <div class="avatar"><?php echo strtoupper(substr($_SESSION['nom'], 0, 2)); ?></div>
            <div>
                <div class="hospital-name"><?php echo htmlspecialchars($_SESSION['nom']); ?></div>
                <div class="hospital-type">Établissement de santé</div>
            </div>
        </div>
        <a href="../logout.php" class="btn-disconnect">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Se déconnecter
        </a>
    </div>
</aside>

<!-- ══ MODAL NOUVELLE DEMANDE ══ -->
<div class="modal-overlay <?php echo $erreur ? 'open' : ''; ?>" id="modalOverlay" onclick="fermerModalOverlay(event)">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Nouvelle demande de sang</div>
            <button class="modal-close" onclick="fermerModal()">✕</button>
        </div>

        <?php if ($erreur): ?>
            <div class="modal-erreur">⚠️ <?php echo htmlspecialchars($erreur); ?></div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-sep">🏥 Informations requises</div>

            <div class="form-group">
                <label>Banque de sang *</label>
                <select name="id_banque" required>
                    <option value="">— Choisir une banque —</option>
                    <?php foreach ($banques as $b): ?>
                    <option value="<?php echo $b['id_banque']; ?>" <?php echo (($_POST['id_banque'] ?? '') == $b['id_banque']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($b['nom']); ?> — <?php echo htmlspecialchars($b['wilaya']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Groupe sanguin *</label>
                    <select name="id_groupe" required>
                        <option value="">— Choisir —</option>
                        <?php foreach ($groupes as $g): ?>
                        <option value="<?php echo $g['id_groupe']; ?>" <?php echo (($_POST['id_groupe'] ?? '') == $g['id_groupe']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($g['libelle']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantité (pochettes) *</label>
                    <input type="number" name="quantite" placeholder="Ex : 5" min="1"
                           value="<?php echo htmlspecialchars($_POST['quantite'] ?? ''); ?>" required />
                </div>
            </div>

            <div class="form-sep">📅 Options supplémentaires</div>

            <div class="form-group">
                <label>Date souhaitée</label>
                <input type="date" name="date_souhaitee" min="<?php echo date('Y-m-d'); ?>"
                       value="<?php echo htmlspecialchars($_POST['date_souhaitee'] ?? ''); ?>" />
            </div>

            <label class="urgence-row">
                <input type="checkbox" name="urgence" value="1" <?php echo isset($_POST['urgence']) ? 'checked' : ''; ?> />
                <span>🚨 Marquer comme urgente</span>
            </label>

            <div class="form-group">
                <label>Note (facultatif)</label>
                <textarea name="note" placeholder="Ex : patient en état critique, besoin sous 24h..."><?php echo htmlspecialchars($_POST['note'] ?? ''); ?></textarea>
            </div>

            <button type="submit" name="envoyer" class="btn-submit">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
                Envoyer la demande
            </button>

        </form>
    </div>
</div>

<!-- ══ CONTENU PRINCIPAL ══ -->
<div class="main-content">

    <div class="page-header">
        <h1>Bienvenue sur votre tableau de bord</h1>
        <p>Retrouvez ici le suivi et l'historique complet de vos demandes de sang en cours.</p>
    </div>

    <?php if ($success): ?>
        <div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-card card-all">
            <div class="stat-card-header">
                <span class="stat-label">Demandes enregistrées</span>
                <span class="stat-icon ic-all">📋</span>
            </div>
            <span class="stat-number"><?php echo $nb_demandes; ?></span>
        </div>
        <div class="stat-card card-wait">
            <div class="stat-card-header">
                <span class="stat-label">Demandes en attente</span>
                <span class="stat-icon ic-wait">⏳</span>
            </div>
            <span class="stat-number"><?php echo $nb_attente; ?></span>
        </div>
        <div class="stat-card card-ok">
            <div class="stat-card-header">
                <span class="stat-label">Demandes approuvées</span>
                <span class="stat-icon ic-ok">✅</span>
            </div>
            <span class="stat-number"><?php echo $nb_acceptees; ?></span>
        </div>
        <div class="stat-card card-no">
            <div class="stat-card-header">
                <span class="stat-label">Demandes refusées</span>
                <span class="stat-icon ic-no">❌</span>
            </div>
            <span class="stat-number"><?php echo $nb_refusees; ?></span>
        </div>
    </div>

    <div class="section">
        <div class="section-header">
            <div class="section-title">Historique de vos dernières demandes</div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Banque de sang</th>
                        <th>Groupe Sanguin</th>
                        <th>Quantité demandée</th>
                        <th>Date de demande</th>
                        <th>Date de réponse</th>
                        <th>Statut final</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($demandes): ?>
                        <?php foreach ($demandes as $d): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['nom_banque']); ?></td>
                            <td><span class="badge badge-groupe"><?php echo htmlspecialchars($d['groupe']); ?></span></td>
                            <td><strong><?php echo $d['quantite_demandee']; ?></strong> pochette(s)</td>
                            <td><?php echo date('d/m/Y H:i', strtotime($d['date_demande'])); ?></td>
                            <td><?php echo $d['date_reponse'] ? date('d/m/Y H:i', strtotime($d['date_reponse'])) : 'En attente de traitement'; ?></td>
                            <td>
                                <?php if ($d['statut'] === 'en_attente'): ?>
                                    <span class="badge badge-attente">En cours d'étude</span>
                                <?php elseif ($d['statut'] === 'acceptée'): ?>
                                    <span class="badge badge-acceptee">Demande Acceptée</span>
                                <?php else: ?>
                                    <span class="badge badge-refusee">Demande Refusée</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="vide">Aucune demande de sang n'est enregistrée pour votre hôpital actuellement.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    function ouvrirModal() {
        document.getElementById('modalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function fermerModal() {
        document.getElementById('modalOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }

    function fermerModalOverlay(e) {
        if (e.target === document.getElementById('modalOverlay')) fermerModal();
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') fermerModal();
    });
</script>

</body>
</html>