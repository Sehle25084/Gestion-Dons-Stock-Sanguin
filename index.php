<?php
session_start();
require_once 'config/db.php';

$erreur = "";
// ✔ Message de succès affiché après réinitialisation du mot de passe
$succes = $_SESSION['flash_succes'] ?? '';
unset($_SESSION['flash_succes']);

// Type d'identifiant actif (NNI par défaut, conforme au document pi.docx)
$type_actif = $_POST['type_identifiant'] ?? 'nni';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['identifiant'])) {

    $identifiant  = trim($_POST['identifiant']);
    $mot_de_passe = $_POST['mot_de_passe'];

    if (empty($identifiant) || empty($mot_de_passe)) {
        $erreur = "Veuillez remplir tous les champs.";
    }
    else if ($type_actif === 'nni') {
        // ══ Connexion DONNEUR (par NNI) ══

        // ✔ CORRECTION : Validation NNI = exactement 10 chiffres
        if (!preg_match('/^\d{10}$/', $identifiant)) {
            $erreur = "Le NNI doit contenir exactement 10 chiffres.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM donneur WHERE NNI = ?");
            $stmt->execute([$identifiant]);
            $donneur = $stmt->fetch();
            if ($donneur && password_verify($mot_de_passe, $donneur['mot_de_passe'])) {
                session_regenerate_id(true);
                $_SESSION['role'] = 'donneur';
                $_SESSION['id']   = $donneur['id_donneur'];
                $_SESSION['NNI']  = $donneur['NNI'];
                header("Location: donneur/dashboard.php");
                exit;
            }
            $erreur = "Identifiant ou mot de passe incorrect.";
        }
    }
    else {
        // ══ Connexion par EMAIL — Admin / Banque / Sous-banque / Hôpital ══

        // 1. Administrateur
        $stmt = $pdo->prepare("SELECT * FROM administrateur WHERE email = ?");
        $stmt->execute([$identifiant]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($mot_de_passe, $admin['mot_de_passe'])) {
            session_regenerate_id(true);
            $_SESSION['role']   = 'admin';
            $_SESSION['id']     = $admin['id_admin'];
            $_SESSION['email']  = $admin['email'];
            $_SESSION['nom']    = $admin['nom']    ?? null;
            $_SESSION['prenom'] = $admin['prenom'] ?? null;
            header("Location: administrateure/dashboard.php");
            exit;
        }

        // 2. Banque de sang — Connexion via `utilisateur_banque` (NOUVELLE ARCHITECTURE)
        // ✔ NOUVEAU : on ne se connecte plus directement sur banque_de_sang mais via un compte utilisateur
        //   (comme responsable_hopital et utilisateur_sous_banque). Une banque peut avoir plusieurs agents.
        $stmt = $pdo->prepare("
            SELECT u.id_utilisateur, u.id_banque, u.nom_complet, u.email AS user_email,
                   u.mot_de_passe, u.telephone, u.statut,
                   b.nom AS nom_banque, b.wilaya
            FROM utilisateur_banque u
            JOIN banque_de_sang b ON b.id_banque = u.id_banque
            WHERE u.email = ?
        ");
        $stmt->execute([$identifiant]);
        $user_b = $stmt->fetch();

        if ($user_b && password_verify($mot_de_passe, $user_b['mot_de_passe'])) {

            // ✔ Vérifier que le compte est actif
            if ($user_b['statut'] !== 'actif') {
                $erreur = "Votre compte a été désactivé. Contactez votre administrateur.";
            } else {
                session_regenerate_id(true);
                $_SESSION['role']          = 'banque';
                // ID de l'utilisateur connecté (l'agent)
                $_SESSION['id_utilisateur'] = $user_b['id_utilisateur'];
                $_SESSION['nom_complet']    = $user_b['nom_complet'];
                $_SESSION['email']          = $user_b['user_email'];
                $_SESSION['telephone']      = $user_b['telephone'];
                // ID et infos de la banque rattachée
                $_SESSION['id_banque']      = $user_b['id_banque'];
                $_SESSION['nom_banque']     = $user_b['nom_banque'];
                $_SESSION['wilaya']         = $user_b['wilaya'];
                // Compatibilité avec l'ancien code (les pages banque utilisent $_SESSION['id'] et ['nom'])
                $_SESSION['id']             = $user_b['id_banque'];
                $_SESSION['nom']            = $user_b['nom_banque'];
                header("Location: banque/dashboard.php");
                exit;
            }
        }

        // 3. Sous-banque — Connexion via `utilisateur_sous_banque` (NOUVELLE ARCHITECTURE)
        // ✔ NOUVEAU : on ne se connecte plus directement sur sous_banque mais via un compte utilisateur
        //   (comme responsable_hopital pour les hôpitaux). Une sous-banque peut avoir plusieurs agents.
        $stmt = $pdo->prepare("
            SELECT u.id_utilisateur, u.id_sous_banque, u.nom_complet, u.email AS user_email,
                   u.mot_de_passe, u.statut,
                   sb.nom AS nom_sb, sb.id_hopital, sb.id_banque_principale
            FROM utilisateur_sous_banque u
            JOIN sous_banque sb ON sb.id_sous_banque = u.id_sous_banque
            WHERE u.email = ?
        ");
        $stmt->execute([$identifiant]);
        $user_sb = $stmt->fetch();

        if ($user_sb && password_verify($mot_de_passe, $user_sb['mot_de_passe'])) {

            // ✔ Vérifier que le compte est actif
            if ($user_sb['statut'] !== 'actif') {
                $erreur = "Votre compte a été désactivé. Contactez votre administrateur.";
            } else {
                session_regenerate_id(true);
                $_SESSION['role']                 = 'sous_banque';
                // ID de l'utilisateur connecté (l'agent)
                $_SESSION['id_utilisateur']       = $user_sb['id_utilisateur'];
                $_SESSION['nom_complet']          = $user_sb['nom_complet'];
                $_SESSION['email']                = $user_sb['user_email'];
                // ID et infos de la sous-banque rattachée
                $_SESSION['id_sous_banque']       = $user_sb['id_sous_banque'];
                $_SESSION['nom_sb']               = $user_sb['nom_sb'];
                $_SESSION['id_hopital']           = $user_sb['id_hopital'];
                $_SESSION['id_banque_principale'] = $user_sb['id_banque_principale'];
                // Compatibilité avec l'ancien code (au cas où certaines pages utilisent encore $_SESSION['id'])
                $_SESSION['id']                   = $user_sb['id_sous_banque'];
                $_SESSION['nom']                  = $user_sb['nom_sb'];
                $_SESSION['user_nom']             = $user_sb['nom_complet'];
                header("Location: sous_banque/dashboard.php");
                exit;
            }
        }

        // 4. Hôpital — Connexion STRICTE via responsable_hopital uniquement
        // (Le compte générique hôpital est désactivé pour des raisons de sécurité)
        $stmt = $pdo->prepare("SELECT * FROM responsable_hopital WHERE email = ?");
        $stmt->execute([$identifiant]);
        $responsable = $stmt->fetch();

        if ($responsable && password_verify($mot_de_passe, $responsable['mot_de_passe'])) {
            session_regenerate_id(true);

            // ✔ CORRECTION : récupérer les infos de l'hôpital + sa banque via sa sous-banque
            //   (avant : on lisait hopital.id_banque qui était une anomalie structurelle)
            //   Désormais, le lien vers la banque passe par sous_banque.id_banque_principale
            $stmtHop = $pdo->prepare("
                SELECT h.nom AS nom_hopital,
                       sb.id_sous_banque,
                       sb.nom AS nom_sous_banque,
                       sb.id_banque_principale AS id_banque
                FROM hopital h
                LEFT JOIN sous_banque sb ON sb.id_hopital = h.id_hopital
                WHERE h.id_hopital = ?
            ");
            $stmtHop->execute([$responsable['id_hopital']]);
            $hopital = $stmtHop->fetch();

            $_SESSION['role']               = 'hopital';
            $_SESSION['id_hopital']         = $responsable['id_hopital'];
            $_SESSION['id_responsable']     = $responsable['id_responsable'];
            $_SESSION['nom_responsable']    = $responsable['nom'];
            $_SESSION['prenom_responsable'] = $responsable['prenom'];
            $_SESSION['poste_responsable']  = $responsable['poste'];
            $_SESSION['nom_hopital']        = $hopital['nom_hopital'] ?? 'Hôpital';
            // ✔ id_banque = banque principale qui alimente la sous-banque de l'hôpital
            //   (NULL si l'hôpital n'a pas encore de sous-banque rattachée)
            $_SESSION['id_banque']          = $hopital['id_banque'] ?? null;
            $_SESSION['id_sous_banque']     = $hopital['id_sous_banque'] ?? null;
            $_SESSION['nom_sous_banque']    = $hopital['nom_sous_banque'] ?? null;
            header("Location: hospital/dashboard.php");
            exit;
        }
        

        $erreur = "Identifiant ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — E-Sang</title>
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

        /* ══════════════════════════════════════
           CÔTÉ GAUCHE — BRANDING
           ══════════════════════════════════════ */
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

        .brand-logo {
            margin-bottom: 32px;
        }

        .brand-name {
            font-size: 72px;
            font-weight: 900;
            color: #111111;
            letter-spacing: -3px;
            margin-bottom: 24px;
            line-height: 1;
        }

        .brand-name .accent {
            color: #8B0000;
        }

        .brand-tagline {
            font-size: 22px;
            font-weight: 700;
            color: #111111;
            margin-bottom: 6px;
        }

        .brand-tagline-red {
            font-size: 22px;
            font-weight: 700;
            color: #8B0000;
            margin-bottom: 48px;
        }

        .brand-cards {
            display: flex;
            gap: 32px;
            margin-top: 20px;
        }

        .brand-card {
            flex: 1;
            max-width: 200px;
        }

        .brand-card h3 {
            font-size: 22px;
            font-weight: 800;
            color: #111111;
            margin-bottom: 6px;
            line-height: 1.15;
        }

        .brand-card p {
            font-size: 14px;
            color: #444444;
            font-weight: 500;
            line-height: 1.4;
        }

        /* ══════════════════════════════════════
           CÔTÉ DROIT — FORMULAIRE
           ══════════════════════════════════════ */
        .right-side {
            width: 580px;
            flex-shrink: 0;
            background: #FAFAFA;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 50px 60px;
        }

        .form-card {
            width: 100%;
            max-width: 460px;
        }

        .form-title {
            font-size: 38px;
            font-weight: 900;
            color: #111111;
            margin-bottom: 10px;
            letter-spacing: -1px;
        }

        .form-subtitle {
            font-size: 16px;
            color: #555555;
            margin-bottom: 32px;
            font-weight: 500;
        }

        /* ══ ALERTE ERREUR ══ */
        .alerte-erreur {
            background: #FEF2F2;
            border: 2px solid #FCA5A5;
            color: #8B0000;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ══ ALERTE SUCCÈS ══ */
        .alerte-succes {
            background: #F0FDF4;
            border: 2px solid #86EFAC;
            color: #166534;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            line-height: 1.4;
        }

        /* ══ LIEN MOT DE PASSE OUBLIÉ (sur la même ligne que le label) ══ */
        .password-header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .password-header .form-label {
            margin-bottom: 0;
        }
        .lien-mdp-oublie {
            font-size: 13px;
            color: #8B0000;
            text-decoration: none;
            font-weight: 700;
            white-space: nowrap;
        }
        .lien-mdp-oublie:hover { text-decoration: underline; }

        /* ══ BOX FORMULAIRE ══ */
        .form-box {
            background: #FFF5F5;
            border: 1.5px solid #FCA5A5;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }

        /* ══ TOGGLE NNI / EMAIL ══ */
        .toggle-type {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            background: #FFFFFF;
            border-radius: 12px;
            padding: 5px;
            margin-bottom: 22px;
            border: 1.5px solid #E5E7EB;
        }

        .toggle-btn {
            padding: 12px;
            border-radius: 9px;
            border: none;
            background: transparent;
            color: #6B7280;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .toggle-btn:hover { color: #8B0000; }

        .toggle-btn.active {
            background: #8B0000;
            color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(139, 0, 0, 0.25);
        }

        /* ══ CHAMPS ══ */
        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 15px;
            font-weight: 700;
            color: #111111;
            margin-bottom: 8px;
        }

        .form-label .req {
            color: #8B0000;
        }

        .form-input {
            width: 100%;
            padding: 14px 18px;
            border: 1.5px solid #E5E7EB;
            border-radius: 12px;
            font-size: 16px;
            font-family: inherit;
            background: #FFFFFF;
            color: #111111;
            outline: none;
            transition: all 0.2s;
        }

        .form-input::placeholder { color: #9CA3AF; }

        .form-input:focus {
            border-color: #8B0000;
            box-shadow: 0 0 0 4px rgba(139, 0, 0, 0.08);
        }

        /* ══ MOT DE PASSE AVEC TOGGLE ══ */
        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-input {
            padding-right: 50px;
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9CA3AF;
            cursor: pointer;
            font-size: 18px;
            padding: 4px;
        }

        .toggle-password:hover { color: #8B0000; }

        /* ══ BOUTON CONNEXION ══ */
        .btn-connexion {
            width: 100%;
            padding: 16px;
            background: #8B0000;
            color: #FFFFFF;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: 800;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 6px 20px rgba(139, 0, 0, 0.28);
            margin-top: 6px;
        }

        .btn-connexion:hover {
            background: #6B0000;
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(139, 0, 0, 0.35);
        }

        /* ══ LIEN INSCRIPTION ══ */
        .lien-inscription {
            text-align: center;
            font-size: 15px;
            color: #555555;
            margin-top: 20px;
            font-weight: 500;
        }

        .lien-inscription a {
            color: #8B0000;
            text-decoration: none;
            font-weight: 800;
        }
        .lien-inscription a:hover { text-decoration: underline; }

        /* ══ RESPONSIVE ══ */
        @media (max-width: 1024px) {
            body { flex-direction: column; }
            .left-side {
                padding: 40px;
                border-right: none;
                border-bottom: 1px solid #F3F4F6;
                text-align: center;
                align-items: center;
            }
            .right-side { width: 100%; padding: 40px 24px; }
            .brand-name { font-size: 52px; }
            .form-title { font-size: 30px; }
        }
    </style>
</head>
<body>

<!-- ══════════════════════════════════════
     CÔTÉ GAUCHE — BRANDING
     ══════════════════════════════════════ -->
<div class="left-side">

    <!-- Logo goutte avec croix médicale -->
    <div class="brand-logo">
        <svg width="72" height="92" viewBox="0 0 72 92" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Goutte -->
            <path d="M36 90C53.673 90 68 75.673 68 58C68 43 56 26 36 4C16 26 4 43 4 58C4 75.673 18.327 90 36 90Z"
                  fill="#8B0000"/>
            <!-- Croix blanche -->
            <rect x="30" y="40" width="12" height="36" rx="3" fill="white"/>
            <rect x="18" y="52" width="36" height="12" rx="3" fill="white"/>
        </svg>
    </div>

    <h1 class="brand-name">E<span class="accent">-</span>Sang</h1>

    <p class="brand-tagline">Chaque don compte.</p>
    <p class="brand-tagline-red">Soyez le héros de quelqu'un.</p>

    <div class="brand-cards">
        <div class="brand-card">
            <h3>Don de vie</h3>
            <p>Rejoignez la communauté</p>
        </div>
        <div class="brand-card">
            <h3>Ensemble</h3>
            <p>Unis pour sauver des vies</p>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════
     CÔTÉ DROIT — FORMULAIRE
     ══════════════════════════════════════ -->
<div class="right-side">
    <div class="form-card">

        <h2 class="form-title">Connexion</h2>
        <p class="form-subtitle">Accédez à votre espace E-Sang</p>

        <?php if ($succes): ?>
            <div class="alerte-succes">
                ✅ <?php echo htmlspecialchars($succes); ?>
            </div>
        <?php endif; ?>

        <?php if ($erreur): ?>
            <div class="alerte-erreur">
                ⚠️ <?php echo htmlspecialchars($erreur); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">

            <input type="hidden" name="type_identifiant" id="type_identifiant" value="<?php echo htmlspecialchars($type_actif); ?>"/>

            <div class="form-box">

                <!-- Toggle NNI / Email — NNI en premier (conforme pi.docx) -->
                <div class="toggle-type">
                    <button type="button" class="toggle-btn <?php echo ($type_actif === 'nni') ? 'active' : ''; ?>"
                            onclick="setType('nni')">
                        📄 NNI (Donneur)
                    </button>
                    <button type="button" class="toggle-btn <?php echo ($type_actif === 'email') ? 'active' : ''; ?>"
                            onclick="setType('email')">
                        ✉️ Email
                    </button>
                </div>

                <!-- Champ identifiant -->
                <div class="form-group">
                    <label class="form-label" id="label_id">
                        <?php echo ($type_actif === 'nni') ? 'Numéro National d\'Identité (NNI)' : 'Adresse e-mail'; ?>
                        <span class="req">*</span>
                    </label>
                    <!-- ✔ CORRECTION : placeholder 10 chiffres + validation HTML (maxlength + pattern + inputmode) quand type=nni -->
                    <input type="text" name="identifiant" id="identifiant" class="form-input"
                           placeholder="<?php echo ($type_actif === 'nni') ? 'Entrez vos 10 chiffres' : 'exemple@gestion-sang.mr'; ?>"
                           value="<?php echo htmlspecialchars($_POST['identifiant'] ?? ''); ?>"
                           <?php if ($type_actif === 'nni'): ?>
                               maxlength="10" pattern="\d{10}" inputmode="numeric"
                               title="Le NNI doit contenir exactement 10 chiffres"
                           <?php endif; ?>
                           autocomplete="off" required/>
                </div>

                <!-- Mot de passe -->
                <div class="form-group" style="margin-bottom: 0;">
                    <div class="password-header">
                        <label class="form-label">Mot de passe <span class="req">*</span></label>
                        <a href="mot_de_passe_oublie.php" class="lien-mdp-oublie">Mot de passe oublié ?</a>
                    </div>
                    <div class="password-wrapper">
                        <input type="password" name="mot_de_passe" id="mot_de_passe" class="form-input"
                               placeholder="••••••••" required/>
                        <button type="button" class="toggle-password" onclick="togglePassword()" title="Afficher / masquer">
                            👁️
                        </button>
                    </div>
                </div>

            </div>

            <button type="submit" class="btn-connexion">
                Se connecter
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                </svg>
            </button>

        </form>

        <div class="lien-inscription">
            Vous êtes donneur et n'avez pas de compte ? <a href="inscription.php">Créer un compte</a>
        </div>

    </div>
</div>

<script>
    function setType(type) {
        document.getElementById('type_identifiant').value = type;

        // Mettre à jour les boutons toggle
        document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
        event.target.classList.add('active');

        // Mettre à jour le label + placeholder
        const label = document.getElementById('label_id');
        const input = document.getElementById('identifiant');

        if (type === 'nni') {
            label.innerHTML = "Numéro National d'Identité (NNI) <span class='req'>*</span>";
            input.placeholder = "Entrez vos 10 chiffres";
            input.type = "text";
            // ✔ CORRECTION : ajouter les attributs de validation au passage en mode NNI
            input.setAttribute('maxlength', '10');
            input.setAttribute('pattern', '\\d{10}');
            input.setAttribute('inputmode', 'numeric');
            input.setAttribute('title', 'Le NNI doit contenir exactement 10 chiffres');
        } else {
            label.innerHTML = "Adresse e-mail <span class='req'>*</span>";
            input.placeholder = "exemple@gestion-sang.mr";
            input.type = "email";
            // ✔ CORRECTION : retirer les attributs NNI au passage en mode email
            input.removeAttribute('maxlength');
            input.removeAttribute('pattern');
            input.removeAttribute('inputmode');
            input.removeAttribute('title');
        }

        input.value = "";
        input.focus();
    }

    function togglePassword() {
        const input = document.getElementById('mot_de_passe');
        input.type = input.type === 'password' ? 'text' : 'password';
    }
</script>

</body>
</html>