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
// AJOUTER une sous-banque (SANS agent — séparé)
// ════════════════════════════════════════════════════════
if (isset($_POST['ajouter_sb'])) {
    $nom                  = trim($_POST['nom']);
    $id_hopital           = (int)($_POST['id_hopital'] ?? 0);
    $id_banque_principale = (int)($_POST['id_banque_principale'] ?? 0);
    $email_sb             = trim($_POST['email']);

    if (empty($nom) || $id_hopital <= 0 || $id_banque_principale <= 0 || empty($email_sb)) {
        $erreur = "Veuillez remplir tous les champs obligatoires.";
    }
    elseif (!filter_var($email_sb, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Email de la sous-banque invalide.";
    }
    else {
        // Vérifier qu'il n'y a pas déjà 1 sous-banque pour cet hôpital (règle métier : 1 SB / hôpital)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sous_banque WHERE id_hopital = ?");
        $stmt->execute([$id_hopital]);
        if ((int)$stmt->fetchColumn() > 0) {
            $erreur = "Cet hôpital a déjà une sous-banque associée. Un hôpital ne peut avoir qu'<strong>une seule sous-banque</strong>.";
        } else {
            // Vérifier hôpital + banque
            $stmtH = $pdo->prepare("SELECT nom FROM hopital WHERE id_hopital = ?");
            $stmtH->execute([$id_hopital]);
            $nom_hop = $stmtH->fetchColumn();

            $stmtB = $pdo->prepare("SELECT nom FROM banque_de_sang WHERE id_banque = ?");
            $stmtB->execute([$id_banque_principale]);
            $nom_b = $stmtB->fetchColumn();

            if (!$nom_hop) {
                $erreur = "Hôpital invalide.";
            } elseif (!$nom_b) {
                $erreur = "Banque principale invalide.";
            } else {
                try {
                    // Hash aléatoire (la connexion se fait via utilisateur_sous_banque)
                    $hash_aleatoire = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                    $pdo->prepare("
                        INSERT INTO sous_banque (nom, id_hopital, id_banque_principale, date_creation, email, mot_de_passe)
                        VALUES (?, ?, ?, CURDATE(), ?, ?)
                    ")->execute([$nom, $id_hopital, $id_banque_principale, $email_sb, $hash_aleatoire]);

                    log_action($pdo, $id_admin_courant, "Création sous-banque : $nom (hôpital $nom_hop)");
                    $success = "Sous-banque <strong>" . htmlspecialchars($nom) . "</strong> créée ! Vous pouvez maintenant ajouter ses agents.";
                } catch (PDOException $e) {
                    $erreur = "Erreur lors de la création de la sous-banque.";
                }
            }
        }
    }
}

// ════════════════════════════════════════════════════════
// AJOUTER un AGENT à une sous-banque existante
// ════════════════════════════════════════════════════════
if (isset($_POST['ajouter_agent'])) {
    $id_sous_banque = (int)($_POST['id_sous_banque'] ?? 0);
    $agent_nom      = trim($_POST['agent_nom']);
    $agent_email    = trim($_POST['agent_email']);
    $agent_pass     = $_POST['agent_pass'];

    if ($id_sous_banque <= 0 || empty($agent_nom) || empty($agent_email) || empty($agent_pass)) {
        $erreur = "Veuillez remplir tous les champs obligatoires pour l'agent.";
    }
    elseif (!filter_var($agent_email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Email de l'agent invalide.";
    }
    elseif (strlen($agent_pass) < 6) {
        $erreur = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    else {
        // Vérifier que la SB existe
        $stmt = $pdo->prepare("SELECT nom FROM sous_banque WHERE id_sous_banque = ?");
        $stmt->execute([$id_sous_banque]);
        $nom_sb = $stmt->fetchColumn();
        if (!$nom_sb) {
            $erreur = "Sous-banque introuvable.";
        } else {
            try {
                $hash = password_hash($agent_pass, PASSWORD_DEFAULT);
                // Note : 'login' = email (contrainte projet)
                $pdo->prepare("
                    INSERT INTO utilisateur_sous_banque (id_sous_banque, nom_complet, login, mot_de_passe, email, statut)
                    VALUES (?, ?, ?, ?, ?, 'actif')
                ")->execute([$id_sous_banque, $agent_nom, $agent_email, $hash, $agent_email]);

                log_action($pdo, $id_admin_courant, "Ajout agent à $nom_sb : $agent_nom");
                $success = "Agent <strong>" . htmlspecialchars($agent_nom) . "</strong> ajouté à <strong>" . htmlspecialchars($nom_sb) . "</strong>.";
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
// SUPPRIMER un agent (garde-fou minimum 1)
// ════════════════════════════════════════════════════════
if (isset($_GET['supprimer_agent'])) {
    $id_agent = (int)$_GET['supprimer_agent'];

    $stmt = $pdo->prepare("
        SELECT u.id_sous_banque, s.nom AS nom_sb, u.nom_complet
        FROM utilisateur_sous_banque u
        JOIN sous_banque s ON s.id_sous_banque = u.id_sous_banque
        WHERE u.id_utilisateur = ?
    ");
    $stmt->execute([$id_agent]);
    $agent_info = $stmt->fetch();

    if (!$agent_info) {
        $erreur = "Agent introuvable.";
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateur_sous_banque WHERE id_sous_banque = ?");
        $stmt->execute([$agent_info['id_sous_banque']]);
        $nb = (int)$stmt->fetchColumn();

        if ($nb <= 1) {
            $erreur = "Impossible de supprimer le dernier agent. Une sous-banque doit avoir au moins 1 agent.";
        } else {
            $pdo->prepare("DELETE FROM utilisateur_sous_banque WHERE id_utilisateur = ?")
                ->execute([$id_agent]);
            log_action($pdo, $id_admin_courant, "Suppression agent {$agent_info['nom_complet']} de {$agent_info['nom_sb']}");
            $success = "Agent supprimé avec succès.";
        }
    }
}

// ════════════════════════════════════════════════════════
// SUPPRIMER une sous-banque (cascade auto sur agents)
// ════════════════════════════════════════════════════════
if (isset($_GET['supprimer_sb'])) {
    $id = (int)$_GET['supprimer_sb'];

    // Vérifier dépendances
    $deps = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM demande WHERE id_sous_banque = ?");
    $stmt->execute([$id]);
    if (($n = (int)$stmt->fetchColumn()) > 0) $deps[] = "$n demande(s)";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lot_sang_sous_banque WHERE id_sous_banque = ?");
    $stmt->execute([$id]);
    if (($n = (int)$stmt->fetchColumn()) > 0) $deps[] = "$n lot(s)";

    if (!empty($deps)) {
        $erreur = "Impossible de supprimer cette sous-banque : " . implode(', ', $deps) . ".";
    } else {
        $stmt = $pdo->prepare("SELECT nom FROM sous_banque WHERE id_sous_banque = ?");
        $stmt->execute([$id]);
        $nom_sb = $stmt->fetchColumn();

        // Les agents sont supprimés en cascade (fk_usb_sous_banque ON DELETE CASCADE)
        $pdo->prepare("DELETE FROM sous_banque WHERE id_sous_banque = ?")->execute([$id]);
        log_action($pdo, $id_admin_courant, "Suppression sous-banque : $nom_sb");
        $success = "Sous-banque supprimée avec succès.";
    }
}

// ════════════════════════════════════════════════════════
// CHARGEMENT
// ════════════════════════════════════════════════════════
$sous_banques = $pdo->query("
    SELECT sb.*,
           h.nom AS nom_hopital,
           h.wilaya AS wilaya_hopital,
           b.nom AS nom_banque,
           (SELECT COUNT(*) FROM utilisateur_sous_banque WHERE id_sous_banque = sb.id_sous_banque) AS nb_agents
    FROM sous_banque sb
    LEFT JOIN hopital h        ON h.id_hopital = sb.id_hopital
    LEFT JOIN banque_de_sang b ON b.id_banque  = sb.id_banque_principale
    ORDER BY sb.nom
")->fetchAll();

// Hôpitaux SANS sous-banque (disponibles pour création)
$hopitaux_libres = $pdo->query("
    SELECT h.* FROM hopital h
    WHERE NOT EXISTS (SELECT 1 FROM sous_banque WHERE id_hopital = h.id_hopital)
    ORDER BY h.nom
")->fetchAll();

// Liste de toutes les banques
$banques = $pdo->query("SELECT * FROM banque_de_sang ORDER BY nom")->fetchAll();

// Agents par sous-banque (pour les modals)
$agents_par_sb = [];
$stmt = $pdo->query("SELECT * FROM utilisateur_sous_banque ORDER BY id_sous_banque, nom_complet");
while ($row = $stmt->fetch()) {
    $agents_par_sb[$row['id_sous_banque']][] = $row;
}

// Stats
$nb_total      = count($sous_banques);
$total_agents  = array_sum(array_column($sous_banques, 'nb_agents'));
$hopitaux_couverts = count(array_unique(array_filter(array_column($sous_banques, 'id_hopital'))));
$nb_hopitaux_libres = count($hopitaux_libres);

require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sous-banques — Admin E-Sang</title>
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
        .chain-rel {
            font-size: 12px; color: #6B7280;
            display: flex; align-items: center; gap: 6px;
        }
        .chain-rel strong { color: #111111; }
    </style>
</head>
<body>

<?php
$page_active = 'sous_banques';
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
            <h1>Sous-banques</h1>
            <p>Gérez les dépôts hospitaliers et leurs agents.</p>
        </div>
        <button class="btn-submit" onclick="toggleModal('addSbModal', true)"
                <?php echo (empty($hopitaux_libres) || empty($banques)) ? 'disabled' : ''; ?>
                style="width:auto; margin:0; padding:11px 22px;
                       <?php echo (empty($hopitaux_libres) || empty($banques)) ? 'opacity:0.5; cursor:not-allowed;' : ''; ?>">
            + Nouvelle sous-banque
        </button>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo $success; ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo $erreur; ?></div><?php endif; ?>

    <?php if (empty($banques)): ?>
    <div class="alerte-erreur">
        <span>❌ Aucune banque de sang disponible. Créez d'abord une banque dans <strong>"Banques de sang"</strong>.</span>
    </div>
    <?php elseif (empty($hopitaux_libres) && $nb_total === 0): ?>
    <div class="alerte-erreur">
        <span>❌ Aucun hôpital disponible. Créez d'abord un hôpital dans <strong>"Hôpitaux & Resp."</strong>.</span>
    </div>
    <?php elseif (empty($hopitaux_libres)): ?>
    <div class="alerte-info" style="background:#FEF3C7; border-color:#FCD34D;">
        <span>⚠️ Tous les hôpitaux ont déjà une sous-banque. Pour en créer une nouvelle, créez d'abord un nouvel hôpital.</span>
    </div>
    <?php else: ?>
    <div class="alerte-info">
        <span>ℹ️ <strong>Logique :</strong> 1 hôpital = 1 sous-banque. Créez la sous-banque, puis cliquez sur <strong>👤 Agents</strong> pour gérer son équipe (plusieurs agents possibles).</span>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-compact">
        <div class="stat-mini">
            <div class="stat-mini-icon ic-red">🏪</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Total sous-banques</div>
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
            <div class="stat-mini-icon ic-grn">🏥</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Hôpitaux couverts</div>
                <div class="stat-mini-number"><?php echo $hopitaux_couverts; ?></div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon ic-org">📋</div>
            <div class="stat-mini-content">
                <div class="stat-mini-label">Hôpitaux libres</div>
                <div class="stat-mini-number"><?php echo $nb_hopitaux_libres; ?></div>
            </div>
        </div>
    </div>

    <!-- Tableau -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Liste des sous-banques</div>
            <span class="cnt-badge"><?php echo count($sous_banques); ?> dépôt(s)</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Hôpital rattaché</th>
                        <th>Banque mère</th>
                        <th>Email</th>
                        <th>Agents</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sous_banques): ?>
                        <?php foreach ($sous_banques as $sb): ?>
                        <tr>
                            <td><strong>🏪 <?php echo htmlspecialchars($sb['nom']); ?></strong></td>
                            <td>
                                <div class="chain-rel">
                                    🏥 <strong><?php echo htmlspecialchars($sb['nom_hopital'] ?? '—'); ?></strong>
                                </div>
                                <small style="color:#9CA3AF;"><?php echo htmlspecialchars($sb['wilaya_hopital'] ?? ''); ?></small>
                            </td>
                            <td>
                                <div class="chain-rel">
                                    🏦 <strong><?php echo htmlspecialchars($sb['nom_banque'] ?? '—'); ?></strong>
                                </div>
                            </td>
                            <td><small style="font-size:12px;"><?php echo htmlspecialchars($sb['email']); ?></small></td>
                            <td>
                                <button class="btn-agents" onclick="ouvrirAgents(<?php echo $sb['id_sous_banque']; ?>)">
                                    👤 Voir agents
                                    <span class="badge-nb-agents"><?php echo $sb['nb_agents']; ?></span>
                                </button>
                            </td>
                            <td>
                                <a href="sous_banques.php?supprimer_sb=<?php echo $sb['id_sous_banque']; ?>"
                                   class="btn-del"
                                   onclick="return confirm('Supprimer cette sous-banque ? Ses agents seront aussi supprimés.');">
                                    🗑️ Supprimer
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="vide">Aucune sous-banque. Cliquez sur "+ Nouvelle sous-banque" pour commencer.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ══ MODAL : Créer une nouvelle sous-banque ══ -->
<div class="modal" id="addSbModal">
    <div class="modal-content" style="max-width: 540px;">
        <div class="modal-header">
            <div class="modal-title">Ajouter une nouvelle sous-banque</div>
            <button class="modal-close" onclick="toggleModal('addSbModal', false)">×</button>
        </div>
        <form method="POST" action="">
            <div class="alerte-info" style="margin-bottom:18px;">
                <span>ℹ️ Cette étape crée juste la <strong>sous-banque</strong>. Vous pourrez ajouter ses agents juste après.</span>
            </div>

            <div class="form-group">
                <label>Nom du dépôt <span class="req">*</span></label>
                <input type="text" name="nom" required placeholder="Ex : Dépôt Cheikh Zayed" />
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Hôpital rattaché <span class="req">*</span></label>
                    <select name="id_hopital" required>
                        <option value="">— Choisir un hôpital —</option>
                        <?php foreach ($hopitaux_libres as $h): ?>
                            <option value="<?php echo $h['id_hopital']; ?>">
                                <?php echo htmlspecialchars($h['nom']); ?>
                                <?php if (!empty($h['wilaya'])): ?> (<?php echo htmlspecialchars($h['wilaya']); ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:#9CA3AF; font-size:11px;">Seuls les hôpitaux sans SB sont listés</small>
                </div>
                <div class="form-group">
                    <label>Banque mère <span class="req">*</span></label>
                    <select name="id_banque_principale" required>
                        <option value="">— Choisir une banque —</option>
                        <?php foreach ($banques as $b): ?>
                            <option value="<?php echo $b['id_banque']; ?>">
                                <?php echo htmlspecialchars($b['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Email de contact de la sous-banque <span class="req">*</span></label>
                <input type="email" name="email" required placeholder="contact@sb.mr" />
            </div>

            <button type="submit" name="ajouter_sb" class="btn-submit" style="width:100%; margin-top:14px;">
                Créer la sous-banque
            </button>
        </form>
    </div>
</div>

<!-- ══ MODAL : Gérer les agents d'une sous-banque ══ -->
<div class="modal" id="agentsModal">
    <div class="modal-content" style="max-width: 640px;">
        <div class="modal-header">
            <div class="modal-title">
                👤 Agents de <span id="sb_nom_modal" style="color:#8B0000;"></span>
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
            <input type="hidden" name="id_sous_banque" id="agent_id_sb" />

            <div class="form-group">
                <label>Nom complet <span class="req">*</span></label>
                <input type="text" name="agent_nom" required placeholder="Ex : Khadijetou Beyah" />
            </div>

            <div class="form-group">
                <label>Email de connexion <span class="req">*</span></label>
                <input type="email" name="agent_email" required placeholder="agent@sousbanque.mr" />
                <small style="color:#9CA3AF; font-size:11px;">L'email servira aussi de login</small>
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
    const m = document.getElementById(id);
    if (m) m.classList[show ? 'add' : 'remove']('active');
}

const agentsParSb = <?php echo json_encode($agents_par_sb); ?>;
const sbData      = <?php echo json_encode(array_column($sous_banques, 'nom', 'id_sous_banque')); ?>;

function ouvrirAgents(idSb) {
    document.getElementById('agent_id_sb').value = idSb;
    document.getElementById('sb_nom_modal').textContent = sbData[idSb] || '';

    const list = document.getElementById('agents_list');
    const agents = agentsParSb[idSb] || [];

    if (agents.length === 0) {
        list.innerHTML = '<div style="text-align:center; padding:18px; color:#9CA3AF; font-size:13px;">Aucun agent pour cette sous-banque. Ajoutez-en un ci-dessous.</div>';
    } else {
        list.innerHTML = agents.map(a => {
            const statutLabel = a.statut === 'actif' ? 'Actif' : 'Inactif';
            const statutClass = a.statut === 'actif' ? 'agent-statut' : 'agent-statut inactif';
            return `
            <div class="agent-item">
                <div class="agent-info">
                    <div class="agent-name">${escapeHtml(a.nom_complet)}<span class="${statutClass}">${statutLabel}</span></div>
                    <div class="agent-meta">📧 ${escapeHtml(a.email || a.login)}</div>
                </div>
                <a href="sous_banques.php?supprimer_agent=${a.id_utilisateur}" 
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