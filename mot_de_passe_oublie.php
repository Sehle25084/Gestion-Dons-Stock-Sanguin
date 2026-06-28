<?php
// ════════════════════════════════════════════════════════════════════
//  E-Sang — Réinitialisation du mot de passe (3 étapes)
//  Fonctionne pour : Donneur, Administrateur, Banque, Sous-banque, Hôpital
// ════════════════════════════════════════════════════════════════════
session_start();
require_once 'config/db.php';

// ── Réinitialiser le flux si l'utilisateur clique sur "Recommencer" ──
if (isset($_GET['reset'])) {
    unset($_SESSION['reset_account'], $_SESSION['reset_verified']);
    header('Location: mot_de_passe_oublie.php');
    exit;
}

$erreur = '';

// ── Déterminer l'étape courante en fonction de la session ──
if (!empty($_SESSION['reset_verified']) && !empty($_SESSION['reset_account'])) {
    $etape = 3;
} elseif (!empty($_SESSION['reset_account'])) {
    $etape = 2;
} else {
    $etape = 1;
}

// Type d'identifiant actif (utilisé seulement à l'étape 1)
$type_actif = $_POST['type_identifiant'] ?? 'nni';


// ════════════════════════════════════════════════════════════════════
//  TRAITEMENT DES SOUMISSIONS DE FORMULAIRE
// ════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ─────────────────────────────────────────────────────────────
    //  ÉTAPE 1 — Identifier le compte (NNI ou email)
    // ─────────────────────────────────────────────────────────────
    if (isset($_POST['etape1'])) {
        $type        = $_POST['type_identifiant'] ?? 'nni';
        $identifiant = trim($_POST['identifiant'] ?? '');

        if ($identifiant === '') {
            $erreur = "Veuillez saisir votre identifiant.";
        }
        else if ($type === 'nni') {
            // ══ Donneur : recherche par NNI ══
            if (!preg_match('/^\d{10}$/', $identifiant)) {
                $erreur = "Le NNI doit contenir exactement 10 chiffres.";
            } else {
                $stmt = $pdo->prepare("SELECT id_donneur, NNI, telephone FROM donneur WHERE NNI = ?");
                $stmt->execute([$identifiant]);
                $row = $stmt->fetch();

                if ($row) {
                    $_SESSION['reset_account'] = [
                        'role'         => 'donneur',
                        'table'        => 'donneur',
                        'id_col'       => 'id_donneur',
                        'id_val'       => $row['id_donneur'],
                        'check_field'  => 'telephone',
                        'check_label'  => 'Téléphone enregistré',
                        'check_value'  => $row['telephone'],
                        'label_compte' => "Donneur — NNI " . $row['NNI'],
                    ];
                    $etape = 2;
                } else {
                    $erreur = "Aucun compte donneur trouvé avec ce NNI.";
                }
            }
        }
        else {
            // ══ Recherche par email dans les 4 autres tables ══
            $found = false;

            // 1. Administrateur
            $stmt = $pdo->prepare("SELECT id_admin, telephone, nom, prenom FROM administrateur WHERE email = ?");
            $stmt->execute([$identifiant]);
            $row = $stmt->fetch();
            if ($row) {
                $_SESSION['reset_account'] = [
                    'role'         => 'admin',
                    'table'        => 'administrateur',
                    'id_col'       => 'id_admin',
                    'id_val'       => $row['id_admin'],
                    'check_field'  => 'telephone',
                    'check_label'  => 'Téléphone enregistré',
                    'check_value'  => $row['telephone'],
                    'label_compte' => "Administrateur — " . trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? '')),
                ];
                $found = true;
                $etape = 2;
            }

            // 2. Utilisateur Banque
            if (!$found) {
                $stmt = $pdo->prepare("SELECT id_utilisateur, telephone, nom_complet FROM utilisateur_banque WHERE email = ?");
                $stmt->execute([$identifiant]);
                $row = $stmt->fetch();
                if ($row) {
                    $_SESSION['reset_account'] = [
                        'role'         => 'banque',
                        'table'        => 'utilisateur_banque',
                        'id_col'       => 'id_utilisateur',
                        'id_val'       => $row['id_utilisateur'],
                        'check_field'  => 'telephone',
                        'check_label'  => 'Téléphone enregistré',
                        'check_value'  => $row['telephone'],
                        'label_compte' => "Banque de sang — " . $row['nom_complet'],
                    ];
                    $found = true;
                    $etape = 2;
                }
            }

            // 3. Utilisateur Sous-banque (téléphone enregistré, comme les autres rôles)
            if (!$found) {
                $stmt = $pdo->prepare("SELECT id_utilisateur, telephone, nom_complet FROM utilisateur_sous_banque WHERE email = ?");
                $stmt->execute([$identifiant]);
                $row = $stmt->fetch();
                if ($row) {
                    $_SESSION['reset_account'] = [
                        'role'         => 'sous_banque',
                        'table'        => 'utilisateur_sous_banque',
                        'id_col'       => 'id_utilisateur',
                        'id_val'       => $row['id_utilisateur'],
                        'check_field'  => 'telephone',
                        'check_label'  => 'Téléphone enregistré',
                        'check_value'  => $row['telephone'],
                        'label_compte' => "Sous-banque — " . $row['nom_complet'],
                    ];
                    $found = true;
                    $etape = 2;
                }
            }

            // 4. Responsable Hôpital
            if (!$found) {
                $stmt = $pdo->prepare("SELECT id_responsable, telephone, nom, prenom FROM responsable_hopital WHERE email = ?");
                $stmt->execute([$identifiant]);
                $row = $stmt->fetch();
                if ($row) {
                    $_SESSION['reset_account'] = [
                        'role'         => 'hopital',
                        'table'        => 'responsable_hopital',
                        'id_col'       => 'id_responsable',
                        'id_val'       => $row['id_responsable'],
                        'check_field'  => 'telephone',
                        'check_label'  => 'Téléphone enregistré',
                        'check_value'  => $row['telephone'],
                        'label_compte' => "Hôpital — " . trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? '')),
                    ];
                    $found = true;
                    $etape = 2;
                }
            }

            if (!$found) {
                $erreur = "Aucun compte trouvé avec cet email.";
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  ÉTAPE 2 — Vérifier l'identité (téléphone ou login)
    // ─────────────────────────────────────────────────────────────
    else if (isset($_POST['etape2']) && !empty($_SESSION['reset_account'])) {
        $verification = trim($_POST['verification'] ?? '');
        $acc = $_SESSION['reset_account'];

        // Normaliser (on ignore les espaces pour le téléphone)
        $saisi   = preg_replace('/\s+/', '', $verification);
        $attendu = preg_replace('/\s+/', '', (string)$acc['check_value']);

        if ($verification === '') {
            $erreur = "Veuillez saisir l'information de vérification.";
        } else if ($attendu === '') {
            $erreur = "Aucune information de vérification n'est enregistrée pour ce compte. Veuillez contacter votre administrateur.";
        } else if ($saisi !== $attendu) {
            $erreur = "L'information saisie ne correspond pas à celle enregistrée.";
        } else {
            $_SESSION['reset_verified'] = true;
            $etape = 3;
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  ÉTAPE 3 — Définir le nouveau mot de passe
    // ─────────────────────────────────────────────────────────────
    else if (isset($_POST['etape3']) && !empty($_SESSION['reset_verified']) && !empty($_SESSION['reset_account'])) {
        $mdp1 = $_POST['nouveau_mdp']   ?? '';
        $mdp2 = $_POST['confirmer_mdp'] ?? '';
        $acc  = $_SESSION['reset_account'];

        // Tables autorisées (whitelist sécurité — empêche l'injection sur le nom de table)
        $tables_autorisees = [
            'donneur'                 => 'id_donneur',
            'administrateur'          => 'id_admin',
            'utilisateur_banque'      => 'id_utilisateur',
            'utilisateur_sous_banque' => 'id_utilisateur',
            'responsable_hopital'     => 'id_responsable',
        ];

        if (strlen($mdp1) < 6) {
            $erreur = "Le mot de passe doit contenir au moins 6 caractères.";
        } else if ($mdp1 !== $mdp2) {
            $erreur = "Les deux mots de passe ne correspondent pas.";
        } else if (!isset($tables_autorisees[$acc['table']])) {
            $erreur = "Erreur interne : table non autorisée.";
        } else {
            $table = $acc['table'];
            $idCol = $tables_autorisees[$table];
            $hash  = password_hash($mdp1, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE `$table` SET mot_de_passe = ? WHERE `$idCol` = ?");
            $stmt->execute([$hash, $acc['id_val']]);

            // Nettoyage de la session de réinitialisation
            unset($_SESSION['reset_account'], $_SESSION['reset_verified']);

            // Message flash pour la page de connexion
            $_SESSION['flash_succes'] = "✅ Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.";
            header('Location: index.php');
            exit;
        }
    }
}


// ── Petite fonction utilitaire pour masquer le téléphone affiché à l'étape 2 ──
function masquer_indice($val) {
    if (!$val) return '—';
    $val = (string)$val;
    $len = strlen($val);
    if ($len <= 4) return str_repeat('*', $len);
    return str_repeat('*', $len - 4) . substr($val, -4);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié — E-Sang</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #FFFFFF;
            color: #111111;
            min-height: 100vh;
            display: flex;
        }

        /* ══ CÔTÉ GAUCHE — BRANDING ══ */
        .left-side {
            flex: 1;
            background: #FFFFFF;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px 80px;
            border-right: 2px solid #E5E7EB;
            box-shadow: 4px 0 16px -8px rgba(0, 0, 0, 0.08);
            z-index: 2;
        }
        .brand-logo { margin-bottom: 32px; }
        .brand-name {
            font-size: 72px; font-weight: 900; color: #111111;
            letter-spacing: -3px; margin-bottom: 24px; line-height: 1;
        }
        .brand-name .accent { color: #8B0000; }
        .brand-tagline {
            font-size: 22px; font-weight: 700; color: #111111; margin-bottom: 6px;
        }
        .brand-tagline-red {
            font-size: 22px; font-weight: 700; color: #8B0000; margin-bottom: 48px;
        }
        .brand-help-box {
            background: #FFF5F5;
            border: 1.5px solid #FCA5A5;
            border-radius: 16px;
            padding: 22px;
            max-width: 420px;
        }
        .brand-help-box h3 {
            font-size: 18px; font-weight: 800; color: #8B0000; margin-bottom: 8px;
        }
        .brand-help-box p {
            font-size: 14px; color: #444; line-height: 1.5; font-weight: 500;
        }

        /* ══ CÔTÉ DROIT — FORMULAIRE ══ */
        .right-side {
            width: 580px;
            flex-shrink: 0;
            background: #FAFAFA;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 50px 60px;
        }
        .form-card { width: 100%; max-width: 460px; }

        .form-title {
            font-size: 34px; font-weight: 900; color: #111111;
            margin-bottom: 10px; letter-spacing: -1px;
        }
        .form-subtitle {
            font-size: 16px; color: #555555; margin-bottom: 28px; font-weight: 500;
        }

        /* ══ STEPPER ══ */
        .stepper {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin-bottom: 28px;
        }
        .step-item {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
        }
        .step-bullet {
            width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            background: #E5E7EB; color: #6B7280;
            font-weight: 800; font-size: 14px;
            border: 2px solid #E5E7EB;
            transition: all 0.2s;
        }
        .step-item.active .step-bullet {
            background: #8B0000; color: #FFF; border-color: #8B0000;
            box-shadow: 0 4px 12px rgba(139,0,0,0.25);
        }
        .step-item.done .step-bullet {
            background: #FFF; color: #8B0000; border-color: #8B0000;
        }
        .step-label {
            font-size: 12px; font-weight: 700; color: #6B7280; text-align: center;
        }
        .step-item.active .step-label,
        .step-item.done .step-label { color: #8B0000; }

        /* ══ ALERTES ══ */
        .alerte-erreur {
            background: #FEF2F2; border: 2px solid #FCA5A5; color: #8B0000;
            border-radius: 12px; padding: 14px 18px;
            font-size: 15px; font-weight: 600; margin-bottom: 24px;
            display: flex; align-items: center; gap: 10px;
        }
        .alerte-info {
            background: #EFF6FF; border: 2px solid #93C5FD; color: #1E40AF;
            border-radius: 12px; padding: 14px 18px;
            font-size: 14px; font-weight: 600; margin-bottom: 20px;
            line-height: 1.5;
        }

        /* ══ BOX FORMULAIRE ══ */
        .form-box {
            background: #FFF5F5; border: 1.5px solid #FCA5A5;
            border-radius: 16px; padding: 24px; margin-bottom: 22px;
        }

        /* ══ TOGGLE NNI / EMAIL ══ */
        .toggle-type {
            display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
            background: #FFFFFF; border-radius: 12px; padding: 5px;
            margin-bottom: 22px; border: 1.5px solid #E5E7EB;
        }
        .toggle-btn {
            padding: 12px; border-radius: 9px; border: none; background: transparent;
            color: #6B7280; font-size: 15px; font-weight: 700; cursor: pointer;
            transition: all 0.2s; font-family: inherit;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .toggle-btn:hover { color: #8B0000; }
        .toggle-btn.active {
            background: #8B0000; color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(139, 0, 0, 0.25);
        }

        /* ══ CHAMPS ══ */
        .form-group { margin-bottom: 18px; }
        .form-label {
            display: block; font-size: 15px; font-weight: 700;
            color: #111111; margin-bottom: 8px;
        }
        .form-label .req { color: #8B0000; }
        .form-input {
            width: 100%; padding: 14px 18px;
            border: 1.5px solid #E5E7EB; border-radius: 12px;
            font-size: 16px; font-family: inherit;
            background: #FFFFFF; color: #111111;
            outline: none; transition: all 0.2s;
        }
        .form-input::placeholder { color: #9CA3AF; }
        .form-input:focus {
            border-color: #8B0000;
            box-shadow: 0 0 0 4px rgba(139, 0, 0, 0.08);
        }

        /* ══ COMPTE TROUVÉ (étape 2) ══ */
        .compte-trouve {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 18px;
        }
        .compte-trouve .label {
            font-size: 12px; font-weight: 700; color: #6B7280;
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;
        }
        .compte-trouve .value {
            font-size: 16px; font-weight: 700; color: #111;
        }
        .indice {
            font-size: 13px; color: #6B7280; margin-top: 6px; font-style: italic;
        }

        /* ══ MOT DE PASSE ══ */
        .password-wrapper { position: relative; }
        .password-wrapper .form-input { padding-right: 50px; }
        .toggle-password {
            position: absolute; right: 16px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none; color: #9CA3AF;
            cursor: pointer; font-size: 18px; padding: 4px;
        }
        .toggle-password:hover { color: #8B0000; }

        /* ══ BOUTON PRINCIPAL ══ */
        .btn-principal {
            width: 100%; padding: 16px;
            background: #8B0000; color: #FFFFFF;
            border: none; border-radius: 12px;
            font-size: 17px; font-weight: 800; cursor: pointer;
            font-family: inherit; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: 0 6px 20px rgba(139, 0, 0, 0.28);
            margin-top: 6px;
        }
        .btn-principal:hover {
            background: #6B0000; transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(139, 0, 0, 0.35);
        }

        /* ══ BOUTON SECONDAIRE ══ */
        .btn-secondaire {
            width: 100%; padding: 14px;
            background: #FFFFFF; color: #6B7280;
            border: 1.5px solid #E5E7EB; border-radius: 12px;
            font-size: 15px; font-weight: 700; cursor: pointer;
            font-family: inherit; transition: all 0.2s;
            margin-top: 10px; text-align: center; text-decoration: none;
            display: block;
        }
        .btn-secondaire:hover { color: #8B0000; border-color: #8B0000; }

        /* ══ LIENS ══ */
        .lien-retour {
            text-align: center; font-size: 15px; color: #555;
            margin-top: 20px; font-weight: 500;
        }
        .lien-retour a {
            color: #8B0000; text-decoration: none; font-weight: 800;
        }
        .lien-retour a:hover { text-decoration: underline; }

        /* ══ RESPONSIVE ══ */
        @media (max-width: 1024px) {
            body { flex-direction: column; }
            .left-side {
                padding: 40px; border-right: none;
                border-bottom: 1px solid #F3F4F6;
                text-align: center; align-items: center;
            }
            .right-side { width: 100%; padding: 40px 24px; }
            .brand-name { font-size: 52px; }
            .form-title { font-size: 26px; }
        }
    </style>
</head>
<body>

<!-- ══ CÔTÉ GAUCHE — BRANDING ══ -->
<div class="left-side">
    <div class="brand-logo">
        <svg width="72" height="92" viewBox="0 0 72 92" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M36 90C53.673 90 68 75.673 68 58C68 43 56 26 36 4C16 26 4 43 4 58C4 75.673 18.327 90 36 90Z" fill="#8B0000"/>
            <rect x="30" y="40" width="12" height="36" rx="3" fill="white"/>
            <rect x="18" y="52" width="36" height="12" rx="3" fill="white"/>
        </svg>
    </div>

    <h1 class="brand-name">E<span class="accent">-</span>Sang</h1>
    <p class="brand-tagline">Récupération de compte</p>
    <p class="brand-tagline-red">Reprenez le contrôle en 3 étapes.</p>

    <div class="brand-help-box">
        <h3>🔒 Comment ça marche ?</h3>
        <p>
            1. Identifiez votre compte (NNI pour les donneurs, email pour les autres rôles).<br>
            2. Confirmez votre identité avec une information enregistrée.<br>
            3. Choisissez un nouveau mot de passe sécurisé.
        </p>
    </div>
</div>


<!-- ══ CÔTÉ DROIT — FORMULAIRE ══ -->
<div class="right-side">
    <div class="form-card">

        <h2 class="form-title">Mot de passe oublié</h2>
        <p class="form-subtitle">
            <?php
            if     ($etape === 1) echo "Étape 1 — Identifiez votre compte.";
            elseif ($etape === 2) echo "Étape 2 — Confirmez votre identité.";
            else                  echo "Étape 3 — Définissez votre nouveau mot de passe.";
            ?>
        </p>

        <!-- ══ STEPPER ══ -->
        <div class="stepper">
            <div class="step-item <?php echo $etape === 1 ? 'active' : ($etape > 1 ? 'done' : ''); ?>">
                <div class="step-bullet"><?php echo $etape > 1 ? '✓' : '1'; ?></div>
                <div class="step-label">Identification</div>
            </div>
            <div class="step-item <?php echo $etape === 2 ? 'active' : ($etape > 2 ? 'done' : ''); ?>">
                <div class="step-bullet"><?php echo $etape > 2 ? '✓' : '2'; ?></div>
                <div class="step-label">Vérification</div>
            </div>
            <div class="step-item <?php echo $etape === 3 ? 'active' : ''; ?>">
                <div class="step-bullet">3</div>
                <div class="step-label">Nouveau MDP</div>
            </div>
        </div>


        <!-- ══ ALERTE ERREUR ══ -->
        <?php if ($erreur): ?>
            <div class="alerte-erreur">⚠️ <?php echo htmlspecialchars($erreur); ?></div>
        <?php endif; ?>


        <?php if ($etape === 1): ?>
        <!-- ════════════════════════════════════════
             ÉTAPE 1 — Identifier le compte
             ════════════════════════════════════════ -->
        <form method="POST" action="">
            <input type="hidden" name="etape1" value="1"/>
            <input type="hidden" name="type_identifiant" id="type_identifiant" value="<?php echo htmlspecialchars($type_actif); ?>"/>

            <div class="form-box">
                <div class="toggle-type">
                    <button type="button" class="toggle-btn <?php echo ($type_actif === 'nni') ? 'active' : ''; ?>" onclick="setType('nni')">
                        📄 NNI (Donneur)
                    </button>
                    <button type="button" class="toggle-btn <?php echo ($type_actif === 'email') ? 'active' : ''; ?>" onclick="setType('email')">
                        ✉️ Email
                    </button>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" id="label_id">
                        <?php echo ($type_actif === 'nni') ? 'Numéro National d\'Identité (NNI)' : 'Adresse e-mail'; ?>
                        <span class="req">*</span>
                    </label>
                    <input type="text" name="identifiant" id="identifiant" class="form-input"
                           placeholder="<?php echo ($type_actif === 'nni') ? 'Entrez vos 10 chiffres' : 'exemple@gestion-sang.mr'; ?>"
                           value="<?php echo htmlspecialchars($_POST['identifiant'] ?? ''); ?>"
                           <?php if ($type_actif === 'nni'): ?>
                               maxlength="10" pattern="\d{10}" inputmode="numeric"
                               title="Le NNI doit contenir exactement 10 chiffres"
                           <?php endif; ?>
                           autocomplete="off" required autofocus/>
                </div>
            </div>

            <button type="submit" class="btn-principal">
                Continuer
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                </svg>
            </button>
        </form>


        <?php elseif ($etape === 2):
            $acc = $_SESSION['reset_account'];
        ?>
        <!-- ════════════════════════════════════════
             ÉTAPE 2 — Vérifier l'identité
             ════════════════════════════════════════ -->
        <form method="POST" action="">
            <input type="hidden" name="etape2" value="1"/>

            <div class="compte-trouve">
                <div class="label">Compte identifié</div>
                <div class="value"><?php echo htmlspecialchars($acc['label_compte']); ?></div>
            </div>

            <div class="alerte-info">
                💡 Pour prouver que ce compte est bien le vôtre, saisissez votre
                <strong><?php echo htmlspecialchars(strtolower($acc['check_label'])); ?></strong>.
            </div>

            <div class="form-box">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">
                        <?php echo htmlspecialchars($acc['check_label']); ?>
                        <span class="req">*</span>
                    </label>
                    <input type="text" name="verification" class="form-input"
                           placeholder="Saisissez la valeur exacte"
                           autocomplete="off" required autofocus/>
                    <div class="indice">
                        Indice : <?php echo htmlspecialchars(masquer_indice($acc['check_value'])); ?>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-principal">
                Vérifier
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </button>

            <a href="?reset=1" class="btn-secondaire">← Recommencer</a>
        </form>


        <?php else:
            $acc = $_SESSION['reset_account'];
        ?>
        <!-- ════════════════════════════════════════
             ÉTAPE 3 — Nouveau mot de passe
             ════════════════════════════════════════ -->
        <form method="POST" action="">
            <input type="hidden" name="etape3" value="1"/>

            <div class="compte-trouve">
                <div class="label">Compte vérifié ✓</div>
                <div class="value"><?php echo htmlspecialchars($acc['label_compte']); ?></div>
            </div>

            <div class="form-box">

                <div class="form-group">
                    <label class="form-label">
                        Nouveau mot de passe <span class="req">*</span>
                    </label>
                    <div class="password-wrapper">
                        <input type="password" name="nouveau_mdp" id="nouveau_mdp" class="form-input"
                               placeholder="Au moins 6 caractères" required minlength="6" autofocus/>
                        <button type="button" class="toggle-password" onclick="togglePwd('nouveau_mdp')" title="Afficher / masquer">👁️</button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">
                        Confirmer le mot de passe <span class="req">*</span>
                    </label>
                    <div class="password-wrapper">
                        <input type="password" name="confirmer_mdp" id="confirmer_mdp" class="form-input"
                               placeholder="Retapez le même mot de passe" required minlength="6"/>
                        <button type="button" class="toggle-password" onclick="togglePwd('confirmer_mdp')" title="Afficher / masquer">👁️</button>
                    </div>
                </div>

            </div>

            <button type="submit" class="btn-principal">
                Réinitialiser mon mot de passe
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </button>

            <a href="?reset=1" class="btn-secondaire">← Recommencer</a>
        </form>

        <?php endif; ?>


        <div class="lien-retour">
            ← <a href="index.php">Retour à la connexion</a>
        </div>

    </div>
</div>


<script>
    function setType(type) {
        document.getElementById('type_identifiant').value = type;
        document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
        event.target.classList.add('active');

        const label = document.getElementById('label_id');
        const input = document.getElementById('identifiant');

        if (type === 'nni') {
            label.innerHTML = "Numéro National d'Identité (NNI) <span class='req'>*</span>";
            input.placeholder = "Entrez vos 10 chiffres";
            input.type = "text";
            input.setAttribute('maxlength', '10');
            input.setAttribute('pattern', '\\d{10}');
            input.setAttribute('inputmode', 'numeric');
            input.setAttribute('title', 'Le NNI doit contenir exactement 10 chiffres');
        } else {
            label.innerHTML = "Adresse e-mail <span class='req'>*</span>";
            input.placeholder = "exemple@gestion-sang.mr";
            input.type = "email";
            input.removeAttribute('maxlength');
            input.removeAttribute('pattern');
            input.removeAttribute('inputmode');
            input.removeAttribute('title');
        }

        input.value = "";
        input.focus();
    }

    function togglePwd(id) {
        const i = document.getElementById(id);
        i.type = i.type === 'password' ? 'text' : 'password';
    }
</script>

</body>
</html>