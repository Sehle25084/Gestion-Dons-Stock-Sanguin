<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'donneur') {
    header("Location: ../index.php");
    exit;
}

$id_donneur = $_SESSION['id'];
$success = $erreur = "";

// ── Données du donneur ──
$stmt = $pdo->prepare("SELECT * FROM donneur WHERE id_donneur = ?");
$stmt->execute([$id_donneur]);
$donneur = $stmt->fetch();

$stmt = $pdo_registre->prepare("SELECT * FROM citoyen WHERE NNI = ?");
$stmt->execute([$donneur['NNI']]);
$citoyen = $stmt->fetch();

// ── Groupe sanguin ──
$groupe = null;
if ($donneur['id_groupe']) {
    $stmt = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
    $stmt->execute([$donneur['id_groupe']]);
    $g = $stmt->fetch();
    if ($g) $groupe = $g['libelle'];
}

// ════════════════════════════════════════════════════════
// MISE À JOUR DU PROFIL
// ════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    $email           = trim($_POST['email'] ?? '');
    $telephone       = trim($_POST['telephone'] ?? '');
    $disponible      = isset($_POST['disponible']) ? 1 : 0;
    $password_actuel = $_POST['password_actuel'] ?? '';
    $password        = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // ── Validations ──
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Format de l'email invalide.";
    }
    elseif (!empty($telephone) && !preg_match('/^(\+222\s?)?\d{8}$/', preg_replace('/\s+/', '', $telephone))) {
        $erreur = "Format du téléphone invalide (8 chiffres ou +222XXXXXXXX).";
    }
    elseif (!empty($password)) {
        // Changement de mot de passe demandé
        if (empty($password_actuel)) {
            $erreur = "Veuillez saisir votre mot de passe actuel pour pouvoir le modifier.";
        } elseif (!password_verify($password_actuel, $donneur['mot_de_passe'])) {
            $erreur = "Le mot de passe actuel est incorrect.";
        } elseif (strlen($password) < 6) {
            $erreur = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
        } elseif ($password !== $password_confirm) {
            $erreur = "La confirmation du nouveau mot de passe ne correspond pas.";
        }
    }

    // ── Si pas d'erreur : enregistrer ──
    if (empty($erreur)) {
        try {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE donneur
                    SET email = ?, telephone = ?, disponible = ?, mot_de_passe = ?
                    WHERE id_donneur = ?
                ");
                $stmt->execute([$email ?: null, $telephone ?: null, $disponible, $hash, $id_donneur]);
                $success = "Profil et mot de passe mis à jour avec succès !";
            } else {
                $stmt = $pdo->prepare("
                    UPDATE donneur
                    SET email = ?, telephone = ?, disponible = ?
                    WHERE id_donneur = ?
                ");
                $stmt->execute([$email ?: null, $telephone ?: null, $disponible, $id_donneur]);
                $success = "Profil mis à jour avec succès !";
            }

            // Recharger les données
            $stmt = $pdo->prepare("SELECT * FROM donneur WHERE id_donneur = ?");
            $stmt->execute([$id_donneur]);
            $donneur = $stmt->fetch();

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $erreur = "Cet email est déjà utilisé par un autre compte.";
            } else {
                $erreur = "Une erreur est survenue lors de la mise à jour.";
            }
        }
    }
}

$page_active = 'profil';
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil — E-Sang Donneur</title>
    <style>
        <?php echo $shared_css; ?>

        /* ── Carte d'infos non modifiables (lecture seule) ── */
        .infos-readonly {
            background: #F9FAFB;
            border: 1.5px solid #E5E7EB;
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 24px;
        }
        .infos-readonly-title {
            font-size: 12px; font-weight: 800; color: #6B7280;
            text-transform: uppercase; letter-spacing: 0.5px;
            margin-bottom: 14px;
            display: flex; align-items: center; gap: 8px;
        }
        .infos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .info-label {
            font-size: 11px; font-weight: 700; color: #9CA3AF;
            text-transform: uppercase; letter-spacing: 0.3px;
        }
        .info-value {
            font-size: 14px; font-weight: 700; color: #111111;
        }

        /* ── Toggle disponibilité ── */
        .toggle-card {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
        }
        .toggle-info { flex: 1; }
        .toggle-info-label {
            font-size: 14px; font-weight: 700; color: #111111;
            margin-bottom: 4px;
        }
        .toggle-info-desc {
            font-size: 12px; color: #6B7280;
        }
        .switch {
            position: relative;
            width: 50px; height: 28px;
            flex-shrink: 0;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            inset: 0;
            background: #E5E7EB;
            border-radius: 99px;
            cursor: pointer;
            transition: 0.3s;
        }
        .slider::before {
            content: '';
            position: absolute;
            height: 22px; width: 22px;
            left: 3px; bottom: 3px;
            background: #FFFFFF;
            border-radius: 50%;
            transition: 0.3s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .switch input:checked + .slider { background: #16A34A; }
        .switch input:checked + .slider::before { transform: translateX(22px); }

        /* Encadré jaune d'info mot de passe */
        .info-encadre {
            background: #FFFBEB;
            border: 1.5px solid #FCD34D;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #78350F;
            line-height: 1.5;
        }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<div class="main-content">

    <!-- ══ TOP-BAR ══ -->
    <div class="top-bar">
        <div class="top-bar-user">
            <div class="top-bar-avatar"><?php echo $donneur_initials; ?></div>
            <div class="top-bar-info">
                <div class="top-bar-name">Bonjour, <?php echo $donneur_display; ?></div>
                <div class="top-bar-role">Espace donneur</div>
            </div>
        </div>
    </div>

    <!-- ══ TITRE ══ -->
    <div class="page-header">
        <h1>Mon profil</h1>
        <p>Gérez vos informations personnelles et votre disponibilité.</p>
    </div>

    <?php if ($success): ?><div class="alerte-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($erreur):  ?><div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur);  ?></div><?php endif; ?>

    <!-- ══ INFOS NON MODIFIABLES ══ -->
    <div class="infos-readonly">
        <div class="infos-readonly-title">🔒 Informations vérifiées (non modifiables)</div>
        <div class="infos-grid">
            <div class="info-item">
                <span class="info-label">NNI</span>
                <span class="info-value"><?php echo htmlspecialchars($donneur['NNI']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Nom complet</span>
                <span class="info-value">
                    <?php echo $citoyen ? htmlspecialchars($citoyen['prenom'] . ' ' . $citoyen['nom']) : '—'; ?>
                </span>
            </div>
            <?php if ($citoyen && !empty($citoyen['date_naissance'])): ?>
            <div class="info-item">
                <span class="info-label">Date de naissance</span>
                <span class="info-value"><?php echo date('d/m/Y', strtotime($citoyen['date_naissance'])); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($citoyen && !empty($citoyen['wilaya'])): ?>
            <div class="info-item">
                <span class="info-label">Wilaya</span>
                <span class="info-value">📍 <?php echo htmlspecialchars($citoyen['wilaya']); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="info-label">Groupe sanguin</span>
                <span class="info-value">
                    <?php if ($groupe): ?>
                        <span class="badge badge-groupe"><?php echo htmlspecialchars($groupe); ?></span>
                        <small style="color:#16A34A; font-weight:600;">✓ Confirmé</small>
                    <?php else: ?>
                        <small style="color:#92400E; font-weight:600;">⏳ Non confirmé</small>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <div style="margin-top: 14px; font-size: 12px; color: #9CA3AF;">
            ℹ️ Ces informations proviennent du registre national et de la banque de sang. Pour les corriger, contactez les autorités compétentes.
        </div>
    </div>

    <!-- ══ FORMULAIRE MODIFICATION ══ -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Mes informations modifiables</div>
        </div>

        <form method="POST" action="">

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email"
                           value="<?php echo htmlspecialchars($donneur['email'] ?? ''); ?>"
                           placeholder="votre.email@exemple.mr" />
                </div>
                <div class="form-group">
                    <label for="telephone">Téléphone</label>
                    <input type="text" name="telephone" id="telephone"
                           value="<?php echo htmlspecialchars($donneur['telephone'] ?? ''); ?>"
                           placeholder="+222 XX XX XX XX" />
                </div>
            </div>

            <!-- ── Toggle disponibilité ── -->
            <div class="toggle-card">
                <div class="toggle-info">
                    <div class="toggle-info-label">🩸 Je suis disponible pour donner mon sang</div>
                    <div class="toggle-info-desc">
                        Cochez si vous êtes disponible et en bonne santé. Les banques pourront vous contacter en cas d'urgence pour votre groupe sanguin.
                    </div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="disponible" <?php echo (int)$donneur['disponible'] === 1 ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <!-- ── Changement de mot de passe ── -->
            <h3 style="font-size:14px; font-weight:800; color:#8B0000; margin: 20px 0 12px; text-transform:uppercase; letter-spacing:0.5px;">
                🔑 Changer mon mot de passe
            </h3>

            <div class="info-encadre">
                ℹ️ Pour modifier votre mot de passe, saisissez d'abord votre mot de passe actuel, puis le nouveau (deux fois pour confirmation). Laissez vide si vous ne souhaitez pas changer.
            </div>

            <div class="form-group">
                <label for="password_actuel">Mot de passe actuel</label>
                <input type="password" name="password_actuel" id="password_actuel"
                       placeholder="Requis uniquement pour changer le mot de passe" />
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Nouveau mot de passe</label>
                    <input type="password" name="password" id="password" minlength="6"
                           placeholder="Min. 6 caractères" />
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirmer le nouveau mot de passe</label>
                    <input type="password" name="password_confirm" id="password_confirm" minlength="6"
                           placeholder="Retapez le nouveau mot de passe" />
                </div>
            </div>

            <button type="submit" name="update_profil" class="btn-submit" style="width: 100%; margin-top: 14px;">
                Enregistrer les modifications
            </button>

        </form>
    </div>

</div>
</body>
</html>