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
// AJOUTER un hôpital (SANS responsable — séparé maintenant)
// ════════════════════════════════════════════════════════
if (isset($_POST['ajouter_hopital'])) {
    $nom       = trim($_POST['nom']);
    $wilaya    = trim($_POST['wilaya']);
    $telephone = trim($_POST['telephone']);
    $email     = trim($_POST['email']);

    if (empty($nom) || empty($email)) {
        $erreur = "Le nom et l'email de l'hôpital sont obligatoires.";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Email de contact hôpital invalide.";
    }
    elseif (!empty($telephone) && !preg_match('/^(\+222\s?)?\d{8}$/', preg_replace('/\s+/', '', $telephone))) {
        $erreur = "Téléphone de l'hôpital invalide (8 chiffres ou +222XXXXXXXX).";
    }
    else {
        try {
            // Hash aléatoire (la connexion se fait via responsable_hopital, pas directement)
            $hash_aleatoire = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $pdo->prepare("
                INSERT INTO hopital (nom, wilaya, telephone, email, mot_de_passe)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$nom, $wilaya, $telephone ?: null, $email, $hash_aleatoire]);

            log_action($pdo, $id_admin_courant, "Création hôpital : $nom");
            $success = "Hôpital <strong>" . htmlspecialchars($nom) . "</strong> créé ! Vous pouvez maintenant ajouter ses responsables/agents.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $erreur = "Cet email d'hôpital est déjà utilisé.";
            } else {
                $erreur = "Erreur lors de la création de l'hôpital.";
            }
        }
    }
}

// ════════════════════════════════════════════════════════
// AJOUTER un RESPONSABLE/AGENT à un hôpital existant
// ════════════════════════════════════════════════════════
if (isset($_POST['ajouter_agent'])) {
    $id_hopital = (int)($_POST['id_hopital'] ?? 0);
    $resp_nom    = trim($_POST['agent_nom']);
    $resp_prenom = trim($_POST['agent_prenom']);
    $resp_email  = trim($_POST['agent_email']);
    $resp_tel    = trim($_POST['agent_tel']);
    $resp_pass   = $_POST['agent_pass'];
    $resp_poste  = trim($_POST['agent_poste'] ?? 'Agent');

    if ($id_hopital <= 0 || empty($resp_nom) || empty($resp_prenom) || empty($resp_email) || empty($resp_pass)) {
        $erreur = "Veuillez remplir tous les champs obligatoires pour l'agent.";
    }
    elseif (!filter_var($resp_email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Email de l'agent invalide.";
    }
    elseif (!empty($resp_tel) && !preg_match('/^(\+222\s?)?\d{8}$/', preg_replace('/\s+/', '', $resp_tel))) {
        $erreur = "Téléphone de l'agent invalide.";
    }
    elseif (strlen($resp_pass) < 6) {
        $erreur = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    else {
        // Vérifier que l'hôpital existe
        $stmt = $pdo->prepare("SELECT nom FROM hopital WHERE id_hopital = ?");
        $stmt->execute([$id_hopital]);
        $nom_hopital = $stmt->fetchColumn();
        if (!$nom_hopital) {
            $erreur = "Hôpital introuvable.";
        } else {
            try {
                $hash = password_hash($resp_pass, PASSWORD_DEFAULT);
                $pdo->prepare("
                    INSERT INTO responsable_hopital (id_hopital, nom, prenom, email, telephone, mot_de_passe, poste)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ")->execute([$id_hopital, $resp_nom, $resp_prenom, $resp_email, $resp_tel ?: null, $hash, $resp_poste]);

                log_action($pdo, $id_admin_courant, "Ajout agent à $nom_hopital : $resp_prenom $resp_nom");
                $success = "Agent <strong>$resp_prenom $resp_nom</strong> ajouté à <strong>" . htmlspecialchars($nom_hopital) . "</strong>.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $erreur = "Cet email d'agent est déjà utilisé.";
                } else {
                    $erreur = "Erreur lors de l'ajout de l'agent.";
                }
            }
        }
    }
}

// ════════════════════════════════════════════════════════
// SUPPRIMER un agent
// ════════════════════════════════════════════════════════
if (isset($_GET['supprimer_agent'])) {
    $id_resp = (int)$_GET['supprimer_agent'];

    // Vérifier qu'il reste au moins 1 autre agent pour cet hôpital
    $stmt = $pdo->prepare("
        SELECT r.id_hopital, h.nom AS nom_hopital, r.prenom, r.nom
        FROM responsable_hopital r
        JOIN hopital h ON h.id_hopital = r.id_hopital
        WHERE r.id_responsable = ?
    ");
    $stmt->execute([$id_resp]);
    $agent_info = $stmt->fetch();

    if (!$agent_info) {
        $erreur = "Agent introuvable.";
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM responsable_hopital WHERE id_hopital = ?");
        $stmt->execute([$agent_info['id_hopital']]);
        $nb_agents = (int)$stmt->fetchColumn();

        if ($nb_agents <= 1) {
            $erreur = "Impossible de supprimer le dernier agent. Un hôpital doit avoir au moins 1 agent.";
        } else {
            $pdo->prepare("DELETE FROM responsable_hopital WHERE id_responsable = ?")
                ->execute([$id_resp]);
            log_action($pdo, $id_admin_courant, "Suppression agent {$agent_info['prenom']} {$agent_info['nom']} de {$agent_info['nom_hopital']}");
            $success = "Agent supprimé avec succès.";
        }
    }
}

// ════════════════════════════════════════════════════════
// SUPPRIMER un hôpital (cascade auto sur agents)
// ════════════════════════════════════════════════════════
if (isset($_GET['supprimer_hopital'])) {
    $id = (int)$_GET['supprimer_hopital'];

    // Vérifier dépendances métier
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_hopital = ?");
    $stmt->execute([$id]);
    $nb_demandes = (int)$stmt->fetchColumn();

    if ($nb_demandes > 0) {
        $erreur = "Impossible de supprimer cet hôpital : $nb_demandes demande(s) liée(s) dans l'historique.";
    } else {
        $stmt = $pdo->prepare("SELECT nom FROM hopital WHERE id_hopital = ?");
        $stmt->execute([$id]);
        $nom_hop = $stmt->fetchColumn();

        $pdo->prepare("DELETE FROM hopital WHERE id_hopital = ?")->execute([$id]);
        log_action($pdo, $id_admin_courant, "Suppression hôpital : $nom_hop");
        $success = "Hôpital supprimé avec succès.";
    }
}

// ════════════════════════════════════════════════════════
// CHARGEMENT
// ════════════════════════════════════════════════════════
// Hôpitaux avec compteur d'agents
$hopitaux = $pdo->query("
    SELECT h.*,
           (SELECT COUNT(*) FROM responsable_hopital WHERE id_hopital = h.id_hopital) AS nb_agents,
           (SELECT COUNT(*) FROM demande WHERE id_hopital = h.id_hopital) AS nb_demandes
    FROM hopital h
    ORDER BY h.nom
")->fetchAll();

// Agents par hôpital (préparation des données pour modals)
$agents_par_hopital = [];
$stmt = $pdo->query("
    SELECT * FROM responsable_hopital
    ORDER BY id_hopital, nom, prenom
");
while ($row = $stmt->fetch()) {
    $agents_par_hopital[$row['id_hopital']][] = $row;
}

// Stats
$nb_total      = count($hopitaux);
$nb_wilayas    = count(array_unique(array_filter(array_column($hopitaux, 'wilaya'))));
$nb_demandeurs = count(array_filter($hopitaux, fn($h) => $h['nb_demandes'] > 0));

require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hôpitaux — Admin E-Sang</title>
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
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .agent-item:last-child { margin-bottom: 0; }
        .agent-info { flex: 1; min-width: 0; }
        .agent-name {
            font-weight: 700; color: #111111; font-size: 14px;
            margin-bottom: 2px;
        }
        .agent-meta {
            font-size: 12px; color: #6B7280;
        }
        .agent-poste {
            display: inline-block;
            background: #FEF2F2; color: #8B0000;
            font-size: 10px; font-weight: 700;
            padding: 2px 8px; border-radius: 999px;
            margin-left: 6px;
        }
        .btn-agents {
            background: #FFFFFF;
            border: 1.5px solid #8B0000;
            color: #8B0000;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px; font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 5px;
            transition: all 0.15s;
            margin-right: 6px;
        }
        .btn-agents:hover { background: #8B0000; color: #FFFFFF; }
        .badge-nb-agents {
            background: #FEE2E2; color: #8B0000;
            padding: 1px 7px; border-radius: 999px;
            font-size: 11px; font-weight: 800;
            margin-left: 4px;
        }
    </style>
</head>
<body>

<?php
$page_active = 'hopitaux';
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
            <h1>Hôpitaux</h1>
            <p>Gérez les structures hospitalières et leurs équipes d'agents.</p>
        </div>
        <button class="btn-submit" onclick="toggleModal('addHopitalModal', true)" style="width:auto; margin:0; padding:11px 22px;">
            + Nouvel hôpital
        </button>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo $success; ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo $erreur; ?></div><?php endif; ?>

    <div class="alerte-info">
        <span>ℹ️ <strong>Logique :</strong> Créez d'abord l'hôpital, puis cliquez sur <strong>👤 Agents</strong> pour gérer ses responsables. Un hôpital peut avoir <strong>plusieurs agents</strong> (équipes, rotations).</span>
    </div>

    <!-- Stats -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-blu">🏥</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total hôpitaux</div>
                <div class="stat-mini-number"><?php echo $nb_total; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">📍</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Wilayas couvertes</div>
                <div class="stat-mini-number"><?php echo $nb_wilayas; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-grn">📋</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Hôpitaux actifs</div>
                <div class="stat-mini-number"><?php echo $nb_demandeurs; ?></div>
            </div>
        </div>
    </div>

    <!-- Tableau -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Liste des hôpitaux</div>
            <span class="cnt-badge"><?php echo count($hopitaux); ?> établissement(s)</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Wilaya</th>
                        <th>Contact</th>
                        <th>Agents</th>
                        <th>Demandes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($hopitaux): ?>
                        <?php foreach ($hopitaux as $h): ?>
                        <tr>
                            <td><strong>🏥 <?php echo htmlspecialchars($h['nom']); ?></strong></td>
                            <td><?php echo htmlspecialchars($h['wilaya'] ?: '—'); ?></td>
                            <td>
                                <div style="font-size:13px;"><?php echo htmlspecialchars($h['email']); ?></div>
                                <small style="color:#6B7280;"><?php echo htmlspecialchars($h['telephone'] ?: '—'); ?></small>
                            </td>
                            <td>
                                <button class="btn-agents" onclick="ouvrirAgents(<?php echo $h['id_hopital']; ?>)">
                                    👤 Voir agents
                                    <span class="badge-nb-agents"><?php echo $h['nb_agents']; ?></span>
                                </button>
                            </td>
                            <td><strong><?php echo $h['nb_demandes']; ?></strong></td>
                            <td>
                                <a href="hopitaux.php?supprimer_hopital=<?php echo $h['id_hopital']; ?>"
                                   class="btn-del"
                                   onclick="return confirm('Supprimer cet hôpital ? Tous ses agents seront aussi supprimés.');">
                                    🗑️ Supprimer
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="vide">Aucun hôpital. Cliquez sur "+ Nouvel hôpital" pour commencer.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ══ MODAL : Créer un nouvel hôpital ══ -->
<div class="modal" id="addHopitalModal">
    <div class="modal-content" style="max-width: 540px;">
        <div class="modal-header">
            <div class="modal-title">Ajouter un nouvel hôpital</div>
            <button class="modal-close" onclick="toggleModal('addHopitalModal', false)">×</button>
        </div>
        <form method="POST" action="">
            <div class="alerte-info" style="margin-bottom:18px;">
                <span>ℹ️ Cette étape crée juste l'<strong>hôpital</strong>. Vous pourrez ajouter ses agents juste après.</span>
            </div>

            <div class="form-group">
                <label>Nom de l'hôpital <span class="req">*</span></label>
                <input type="text" name="nom" required placeholder="Ex : Centre Hospitalier National" />
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Wilaya</label>
                    <input type="text" name="wilaya" placeholder="Ex : Nouakchott Ouest" />
                </div>
                <div class="form-group">
                    <label>Email de contact <span class="req">*</span></label>
                    <input type="email" name="email" required placeholder="contact@hopital.mr" />
                </div>
            </div>

            <div class="form-group">
                <label>Téléphone standard</label>
                <input type="text" name="telephone" placeholder="+222 XX XX XX XX" />
            </div>

            <button type="submit" name="ajouter_hopital" class="btn-submit" style="width:100%; margin-top:14px;">
                Créer l'hôpital
            </button>
        </form>
    </div>
</div>

<!-- ══ MODAL : Gérer les agents d'un hôpital ══ -->
<div class="modal" id="agentsModal">
    <div class="modal-content" style="max-width: 640px;">
        <div class="modal-header">
            <div class="modal-title">
                👤 Agents de <span id="hop_nom_modal" style="color:#8B0000;"></span>
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
            <input type="hidden" name="id_hopital" id="agent_id_hopital" />

            <div class="form-row">
                <div class="form-group">
                    <label>Prénom <span class="req">*</span></label>
                    <input type="text" name="agent_prenom" required placeholder="Ex : Ahmed" />
                </div>
                <div class="form-group">
                    <label>Nom <span class="req">*</span></label>
                    <input type="text" name="agent_nom" required placeholder="Ex : Mohamed Salem" />
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email de connexion <span class="req">*</span></label>
                    <input type="email" name="agent_email" required placeholder="ahmed@hopital.mr" />
                </div>
                <div class="form-group">
                    <label>Poste</label>
                    <input type="text" name="agent_poste" placeholder="Ex : Directeur, Agent..." value="Agent" />
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="text" name="agent_tel" placeholder="+222 XX XX XX XX" />
                </div>
                <div class="form-group">
                    <label>Mot de passe <span class="req">*</span></label>
                    <input type="password" name="agent_pass" required minlength="6" placeholder="Min. 6 caractères" />
                </div>
            </div>

            <button type="submit" name="ajouter_agent" class="btn-submit" style="width:100%; margin-top:10px;">
                ➕ Ajouter cet agent
            </button>
        </form>
    </div>
</div>

<script>
// Modal helpers
function toggleModal(id, show) {
    document.getElementById(id).classList[show ? 'add' : 'remove']('active');
}

// Données des agents (depuis PHP)
const agentsParHopital = <?php echo json_encode($agents_par_hopital); ?>;
const hopitauxData = <?php echo json_encode(array_column($hopitaux, 'nom', 'id_hopital')); ?>;

function ouvrirAgents(idHopital) {
    document.getElementById('agent_id_hopital').value = idHopital;
    document.getElementById('hop_nom_modal').textContent = hopitauxData[idHopital] || '';

    // Construire la liste des agents
    const list = document.getElementById('agents_list');
    const agents = agentsParHopital[idHopital] || [];

    if (agents.length === 0) {
        list.innerHTML = '<div style="text-align:center; padding:18px; color:#9CA3AF; font-size:13px;">Aucun agent pour cet hôpital. Ajoutez-en un ci-dessous.</div>';
    } else {
        list.innerHTML = agents.map(a => `
            <div class="agent-item">
                <div class="agent-info">
                    <div class="agent-name">${escapeHtml(a.prenom)} ${escapeHtml(a.nom)}<span class="agent-poste">${escapeHtml(a.poste || 'Agent')}</span></div>
                    <div class="agent-meta">📧 ${escapeHtml(a.email)} · 📱 ${escapeHtml(a.telephone || '—')}</div>
                </div>
                <a href="hopitaux.php?supprimer_agent=${a.id_responsable}" 
                   class="btn-del" 
                   onclick="return confirm('Supprimer cet agent ?');">
                    🗑️
                </a>
            </div>
        `).join('');
    }

    toggleModal('agentsModal', true);
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

// Fermer modal au clic sur l'overlay
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