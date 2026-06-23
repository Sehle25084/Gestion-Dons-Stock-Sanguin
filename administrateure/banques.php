<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// ════════════════════════════════════════════════════════
// Fonction utilitaire — Tracer les actions admin
// ════════════════════════════════════════════════════════
function log_action($pdo, $id_admin, $action) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO log_activite (role_utilisateur, id_utilisateur, action, date_action)
            VALUES ('admin', ?, ?, NOW())
        ");
        $stmt->execute([$id_admin, $action]);
    } catch (Exception $e) {}
}

$erreur = $success = "";
$id_admin_courant = $_SESSION['id'];

// ════════════════════════════════════════════════════════
// AJOUTER une banque (SANS agent — séparé maintenant)
// ════════════════════════════════════════════════════════
if (isset($_POST['ajouter_banque'])) {
    $nom       = trim($_POST['nom']);
    $wilaya    = trim($_POST['wilaya'] ?? '');
    $adresse   = trim($_POST['adresse'] ?? '');
    $email_b   = trim($_POST['email']);
    $telephone = trim($_POST['telephone'] ?? '');

    if (empty($nom) || empty($email_b)) {
        $erreur = "Le nom et l'email de la banque sont obligatoires.";
    }
    elseif (!filter_var($email_b, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Email de contact de la banque invalide.";
    }
    elseif (!empty($telephone) && !preg_match('/^(\+222\s?)?\d{8}$/', preg_replace('/\s+/', '', $telephone))) {
        $erreur = "Téléphone de la banque invalide (8 chiffres ou +222XXXXXXXX).";
    }
    else {
        try {
            // Hash aléatoire (la connexion se fait via utilisateur_banque)
            $hash_aleatoire = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $pdo->prepare("
                INSERT INTO banque_de_sang (nom, wilaya, adresse, email, telephone, mot_de_passe)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$nom, $wilaya ?: null, $adresse ?: null, $email_b, $telephone ?: null, $hash_aleatoire]);

            log_action($pdo, $id_admin_courant, "Création banque : $nom");
            $success = "Banque <strong>" . htmlspecialchars($nom) . "</strong> créée ! Vous pouvez maintenant ajouter ses agents.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $erreur = "Cet email de banque est déjà utilisé.";
            } else {
                $erreur = "Erreur lors de la création de la banque.";
            }
        }
    }
}

// ════════════════════════════════════════════════════════
// AJOUTER un AGENT à une banque existante
// ════════════════════════════════════════════════════════
if (isset($_POST['ajouter_agent'])) {
    $id_banque   = (int)($_POST['id_banque'] ?? 0);
    $agent_nom   = trim($_POST['agent_nom']);
    $agent_email = trim($_POST['agent_email']);
    $agent_tel   = trim($_POST['agent_tel'] ?? '');
    $agent_pass  = $_POST['agent_pass'];

    if ($id_banque <= 0 || empty($agent_nom) || empty($agent_email) || empty($agent_pass)) {
        $erreur = "Veuillez remplir tous les champs obligatoires pour l'agent.";
    }
    elseif (!filter_var($agent_email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Email de l'agent invalide.";
    }
    elseif (!empty($agent_tel) && !preg_match('/^(\+222\s?)?\d{8}$/', preg_replace('/\s+/', '', $agent_tel))) {
        $erreur = "Téléphone de l'agent invalide.";
    }
    elseif (strlen($agent_pass) < 6) {
        $erreur = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    else {
        // Vérifier que la banque existe
        $stmt = $pdo->prepare("SELECT nom FROM banque_de_sang WHERE id_banque = ?");
        $stmt->execute([$id_banque]);
        $nom_banque = $stmt->fetchColumn();
        if (!$nom_banque) {
            $erreur = "Banque introuvable.";
        } else {
            try {
                $hash = password_hash($agent_pass, PASSWORD_DEFAULT);
                $pdo->prepare("
                    INSERT INTO utilisateur_banque (id_banque, nom_complet, email, mot_de_passe, telephone, statut)
                    VALUES (?, ?, ?, ?, ?, 'actif')
                ")->execute([$id_banque, $agent_nom, $agent_email, $hash, $agent_tel ?: null]);

                log_action($pdo, $id_admin_courant, "Ajout agent à $nom_banque : $agent_nom");
                $success = "Agent <strong>" . htmlspecialchars($agent_nom) . "</strong> ajouté à <strong>" . htmlspecialchars($nom_banque) . "</strong>.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $erreur = "Cet email d'agent est déjà utilisé.";
                } elseif (stripos($e->getMessage(), 'utilisateur_banque') !== false
                          && stripos($e->getMessage(), "doesn't exist") !== false) {
                    $erreur = "La table <code>utilisateur_banque</code> n'existe pas. Exécutez <code>migration_complete.sql</code>.";
                } else {
                    $erreur = "Erreur lors de l'ajout de l'agent.";
                }
            }
        }
    }
}

// ════════════════════════════════════════════════════════
// SUPPRIMER un agent (avec garde-fou minimum 1)
// ════════════════════════════════════════════════════════
if (isset($_GET['supprimer_agent'])) {
    $id_agent = (int)$_GET['supprimer_agent'];

    $stmt = $pdo->prepare("
        SELECT u.id_banque, b.nom AS nom_banque, u.nom_complet
        FROM utilisateur_banque u
        JOIN banque_de_sang b ON b.id_banque = u.id_banque
        WHERE u.id_utilisateur = ?
    ");
    $stmt->execute([$id_agent]);
    $agent_info = $stmt->fetch();

    if (!$agent_info) {
        $erreur = "Agent introuvable.";
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateur_banque WHERE id_banque = ?");
        $stmt->execute([$agent_info['id_banque']]);
        $nb_agents = (int)$stmt->fetchColumn();

        if ($nb_agents <= 1) {
            $erreur = "Impossible de supprimer le dernier agent. Une banque doit avoir au moins 1 agent.";
        } else {
            $pdo->prepare("DELETE FROM utilisateur_banque WHERE id_utilisateur = ?")
                ->execute([$id_agent]);
            log_action($pdo, $id_admin_courant, "Suppression agent {$agent_info['nom_complet']} de {$agent_info['nom_banque']}");
            $success = "Agent supprimé avec succès.";
        }
    }
}

// ════════════════════════════════════════════════════════
// SUPPRIMER une banque (avec vérification dépendances)
// ════════════════════════════════════════════════════════
if (isset($_GET['supprimer_banque'])) {
    $id = (int)$_GET['supprimer_banque'];

    // Vérifier dépendances critiques
    $deps = [];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sous_banque WHERE id_banque_principale = ?");
    $stmt->execute([$id]);
    if (($n = (int)$stmt->fetchColumn()) > 0) $deps[] = "$n sous-banque(s)";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE id_banque = ?");
    $stmt->execute([$id]);
    if (($n = (int)$stmt->fetchColumn()) > 0) $deps[] = "$n entrée(s) de stock";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM don WHERE id_banque = ?");
    $stmt->execute([$id]);
    if (($n = (int)$stmt->fetchColumn()) > 0) $deps[] = "$n don(s)";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_banque = ?");
    $stmt->execute([$id]);
    if (($n = (int)$stmt->fetchColumn()) > 0) $deps[] = "$n demande(s)";

    if (!empty($deps)) {
        $erreur = "Impossible de supprimer cette banque : " . implode(', ', $deps) . ".";
    } else {
        $stmt = $pdo->prepare("SELECT nom FROM banque_de_sang WHERE id_banque = ?");
        $stmt->execute([$id]);
        $nom_b = $stmt->fetchColumn();

        // Les agents sont supprimés en cascade auto (fk_ub_banque ON DELETE CASCADE)
        $pdo->prepare("DELETE FROM banque_de_sang WHERE id_banque = ?")->execute([$id]);
        log_action($pdo, $id_admin_courant, "Suppression banque : $nom_b");
        $success = "Banque supprimée avec succès.";
    }
}

// ════════════════════════════════════════════════════════
// CHARGEMENT
// ════════════════════════════════════════════════════════
$banques = $pdo->query("
    SELECT b.*,
           (SELECT COUNT(*) FROM utilisateur_banque WHERE id_banque = b.id_banque) AS nb_agents,
           (SELECT COUNT(*) FROM sous_banque WHERE id_banque_principale = b.id_banque) AS nb_sb,
           (SELECT COALESCE(SUM(quantite_disponible),0) FROM stock WHERE id_banque = b.id_banque) AS stock_total
    FROM banque_de_sang b
    ORDER BY b.nom
")->fetchAll();

// Agents par banque (pour les modals)
$agents_par_banque = [];
$stmt = $pdo->query("
    SELECT * FROM utilisateur_banque
    ORDER BY id_banque, nom_complet
");
while ($row = $stmt->fetch()) {
    $agents_par_banque[$row['id_banque']][] = $row;
}

// Stats
$nb_total   = count($banques);
$nb_wilayas = count(array_unique(array_filter(array_column($banques, 'wilaya'))));
$total_agents = array_sum(array_column($banques, 'nb_agents'));
$total_stock  = array_sum(array_column($banques, 'stock_total'));

require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banques de sang — Admin E-Sang</title>
    <style>
        <?php echo $shared_css; ?>

        .agents-list {
            background: #F9FAFB;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 16px;
            max-height: 280px;
            overflow-y: auto;
        }
        .agent-item {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 8px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px;
        }
        .agent-item:last-child { margin-bottom: 0; }
        .agent-info { flex: 1; min-width: 0; }
        .agent-name {
            font-weight: 700; color: #111111; font-size: 14px;
            margin-bottom: 2px;
        }
        .agent-meta { font-size: 12px; color: #6B7280; }
        .agent-statut {
            display: inline-block;
            background: #DCFCE7; color: #166534;
            font-size: 10px; font-weight: 700;
            padding: 2px 8px; border-radius: 999px;
            margin-left: 6px;
        }
        .agent-statut.inactif { background: #F3F4F6; color: #6B7280; }
        .btn-agents {
            background: #FFFFFF;
            border: 1.5px solid #8B0000;
            color: #8B0000;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px; font-weight: 700;
            cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 5px;
            transition: all 0.15s;
        }
        .btn-agents:hover { background: #8B0000; color: #FFFFFF; }
        .badge-nb-agents {
            background: #FEE2E2; color: #8B0000;
            padding: 1px 7px; border-radius: 999px;
            font-size: 11px; font-weight: 800;
            margin-left: 4px;
        }
        .btn-agents:hover .badge-nb-agents { background: #FFFFFF; color: #8B0000; }
    </style>
</head>
<body>

<?php
$page_active = 'banques';
require_once 'sidebar.php';
?>

<div class="main-content">

    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar">AP</div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bienvenue, Admin Principal</div>
                <div class="top-bar-role">Espace administrateur</div>
            </div>
        </div>
    </div>

    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1>Banques de sang</h1>
            <p>Gérez les banques de sang et leurs équipes d'agents.</p>
        </div>
        <button class="btn-submit" onclick="toggleModal('addBanqueModal', true)" style="width:auto; margin:0; padding:11px 22px;">
            + Nouvelle banque
        </button>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo $success; ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo $erreur; ?></div><?php endif; ?>

    <div class="alerte-info">
        <span>ℹ️ <strong>Logique :</strong> Créez d'abord la banque, puis cliquez sur <strong>👤 Agents</strong> pour gérer son équipe. Une banque peut avoir <strong>plusieurs agents</strong> (équipes, horaires de garde).</span>
    </div>

    <!-- Stats -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🏦</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total banques</div>
                <div class="stat-mini-number"><?php echo $nb_total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">👥</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total agents</div>
                <div class="stat-mini-number"><?php echo $total_agents; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">📍</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Wilayas couvertes</div>
                <div class="stat-mini-number"><?php echo $nb_wilayas; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">🩸</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Stock global</div>
                <div class="stat-mini-number"><?php echo $total_stock; ?></div>
            </div>
        </div>
    </div>

    <!-- Tableau -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Liste des banques</div>
            <span class="cnt-badge"><?php echo count($banques); ?> établissement(s)</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Wilaya</th>
                        <th>Contact</th>
                        <th>Sous-banques</th>
                        <th>Stock</th>
                        <th>Agents</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($banques): ?>
                        <?php foreach ($banques as $b): ?>
                        <tr>
                            <td><strong>🏦 <?php echo htmlspecialchars($b['nom']); ?></strong></td>
                            <td><?php echo htmlspecialchars($b['wilaya'] ?: '—'); ?></td>
                            <td>
                                <div style="font-size:13px;"><?php echo htmlspecialchars($b['email']); ?></div>
                                <small style="color:#6B7280;"><?php echo htmlspecialchars($b['telephone'] ?: '—'); ?></small>
                            </td>
                            <td><strong><?php echo (int)$b['nb_sb']; ?></strong></td>
                            <td><strong><?php echo (int)$b['stock_total']; ?></strong> poch.</td>
                            <td>
                                <button class="btn-agents" onclick="ouvrirAgents(<?php echo $b['id_banque']; ?>)">
                                    👤 Voir agents
                                    <span class="badge-nb-agents"><?php echo $b['nb_agents']; ?></span>
                                </button>
                            </td>
                            <td>
                                <a href="banques.php?supprimer_banque=<?php echo $b['id_banque']; ?>"
                                   class="btn-del"
                                   onclick="return confirm('Supprimer cette banque ? Ses agents seront aussi supprimés.');">
                                    🗑️ Supprimer
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="vide">Aucune banque. Cliquez sur "+ Nouvelle banque" pour commencer.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ══ MODAL : Créer une nouvelle banque ══ -->
<div class="modal" id="addBanqueModal">
    <div class="modal-content" style="max-width: 540px;">
        <div class="modal-header">
            <div class="modal-title">Ajouter une nouvelle banque</div>
            <button class="modal-close" onclick="toggleModal('addBanqueModal', false)">×</button>
        </div>
        <form method="POST" action="">
            <div class="alerte-info" style="margin-bottom:18px;">
                <span>ℹ️ Cette étape crée juste la <strong>banque</strong>. Vous pourrez ajouter ses agents juste après.</span>
            </div>

            <div class="form-group">
                <label>Nom de la banque <span class="req">*</span></label>
                <input type="text" name="nom" required placeholder="Ex : CNTS Nouakchott" />
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Wilaya</label>
                    <input type="text" name="wilaya" placeholder="Ex : Nouakchott Ouest" />
                </div>
                <div class="form-group">
                    <label>Email de contact <span class="req">*</span></label>
                    <input type="email" name="email" required placeholder="contact@banque.mr" />
                </div>
            </div>

            <div class="form-group">
                <label>Adresse</label>
                <input type="text" name="adresse" placeholder="Ex : Avenue Gamal Abdel Nasser" />
            </div>

            <div class="form-group">
                <label>Téléphone standard</label>
                <input type="text" name="telephone" placeholder="+222 XX XX XX XX" />
            </div>

            <button type="submit" name="ajouter_banque" class="btn-submit" style="width:100%; margin-top:14px;">
                Créer la banque
            </button>
        </form>
    </div>
</div>

<!-- ══ MODAL : Gérer les agents d'une banque ══ -->
<div class="modal" id="agentsModal">
    <div class="modal-content" style="max-width: 640px;">
        <div class="modal-header">
            <div class="modal-title">
                👤 Agents de <span id="banque_nom_modal" style="color:#8B0000;"></span>
            </div>
            <button class="modal-close" onclick="toggleModal('agentsModal', false)">×</button>
        </div>

        <!-- Liste des agents existants -->
        <div class="form-section-title">📋 Agents enregistrés</div>
        <div class="agents-list" id="agents_list">
            <!-- rempli dynamiquement -->
        </div>

        <!-- Formulaire ajout agent -->
        <div class="form-section-title">➕ Ajouter un nouvel agent</div>
        <form method="POST" action="">
            <input type="hidden" name="id_banque" id="agent_id_banque" />

            <div class="form-group">
                <label>Nom complet <span class="req">*</span></label>
                <input type="text" name="agent_nom" required placeholder="Ex : Ahmed Mohamed Salem" />
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email de connexion <span class="req">*</span></label>
                    <input type="email" name="agent_email" required placeholder="ahmed@banque.mr" />
                </div>
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="text" name="agent_tel" placeholder="+222 XX XX XX XX" />
                </div>
            </div>

            <div class="form-group">
                <label>Mot de passe <span class="req">*</span></label>
                <input type="password" name="agent_pass" required minlength="6" placeholder="Min. 6 caractères" />
            </div>

            <button type="submit" name="ajouter_agent" class="btn-submit" style="width:100%; margin-top:10px;">
                ➕ Ajouter cet agent
            </button>
        </form>
    </div>
</div>

<script>
function toggleModal(id, show) {
    document.getElementById(id).classList[show ? 'add' : 'remove']('active');
}

const agentsParBanque = <?php echo json_encode($agents_par_banque); ?>;
const banquesData     = <?php echo json_encode(array_column($banques, 'nom', 'id_banque')); ?>;

function ouvrirAgents(idBanque) {
    document.getElementById('agent_id_banque').value = idBanque;
    document.getElementById('banque_nom_modal').textContent = banquesData[idBanque] || '';

    const list = document.getElementById('agents_list');
    const agents = agentsParBanque[idBanque] || [];

    if (agents.length === 0) {
        list.innerHTML = '<div style="text-align:center; padding:18px; color:#9CA3AF; font-size:13px;">Aucun agent pour cette banque. Ajoutez-en un ci-dessous.</div>';
    } else {
        list.innerHTML = agents.map(a => {
            const statutLabel = a.statut === 'actif' ? 'Actif' : 'Inactif';
            const statutClass = a.statut === 'actif' ? 'agent-statut' : 'agent-statut inactif';
            return `
            <div class="agent-item">
                <div class="agent-info">
                    <div class="agent-name">${escapeHtml(a.nom_complet)}<span class="${statutClass}">${statutLabel}</span></div>
                    <div class="agent-meta">📧 ${escapeHtml(a.email)} · 📱 ${escapeHtml(a.telephone || '—')}</div>
                </div>
                <a href="banques.php?supprimer_agent=${a.id_utilisateur}" 
                   class="btn-del" 
                   onclick="return confirm('Supprimer cet agent ?');">
                    🗑️
                </a>
            </div>
            `;
        }).join('');
    }

    toggleModal('agentsModal', true);
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); });
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(m => m.classList.remove('active'));
    }
});
</script>

</body>
</html>