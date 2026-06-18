<?php
session_start();
require_once 'config/db.php';

$erreur  = "";
$success = "";
$citoyen = null;
$etape   = 1; // 1 = vérification NNI+date, 2 = compléter profil

// ══════════════════════════════════════════
// ÉTAPE 1 — Vérification NNI + Date de naissance
// ══════════════════════════════════════════
if (isset($_POST['verifier_identite'])) {
    $nni            = trim($_POST['nni']);
    $date_naissance = trim($_POST['date_naissance']);

    if (empty($nni) || empty($date_naissance)) {
        $erreur = "Veuillez remplir le NNI ET la date de naissance.";
        $etape  = 1;
    } else {
        // Vérification CROISÉE : NNI + date_naissance doivent correspondre (confidentialité)
        $stmt = $pdo_registre->prepare("SELECT * FROM citoyen WHERE NNI = ? AND date_naissance = ?");
        $stmt->execute([$nni, $date_naissance]);
        $citoyen = $stmt->fetch();

        if (!$citoyen) {
            $erreur = "Aucune correspondance trouvée. Vérifiez votre NNI et votre date de naissance.";
            $etape  = 1;
        } else {
            // Vérifier si le donneur a déjà un compte
            $stmt = $pdo->prepare("SELECT * FROM donneur WHERE NNI = ?");
            $stmt->execute([$nni]);
            if ($stmt->fetch()) {
                $erreur = "Un compte existe déjà avec ce NNI. Veuillez vous connecter.";
                $citoyen = null;
                $etape   = 1;
            } else {
                $etape = 2; // Passage à l'étape 2
            }
        }
    }
}

// ══════════════════════════════════════════
// ÉTAPE 2 — Création du compte
// ══════════════════════════════════════════
if (isset($_POST['creer_compte'])) {
    $nni            = trim($_POST['nni']);
    $date_naissance = trim($_POST['date_naissance']);
    $email          = trim($_POST['email'] ?? '');
    $telephone      = trim($_POST['telephone'] ?? '');
    $id_groupe      = $_POST['id_groupe'] ?? '';
    $mot_de_passe   = $_POST['mot_de_passe'];
    $confirmation   = $_POST['confirmation'];

    // Re-vérification de l'identité (sécurité)
    $stmt = $pdo_registre->prepare("SELECT * FROM citoyen WHERE NNI = ? AND date_naissance = ?");
    $stmt->execute([$nni, $date_naissance]);
    $citoyen = $stmt->fetch();

    if (!$citoyen) {
        $erreur = "Identité non vérifiée. Veuillez recommencer.";
        $etape  = 1;
    }
    elseif (empty($email) && empty($telephone)) {
        $erreur = "Veuillez saisir au moins un email OU un numéro de téléphone.";
        $etape  = 2;
    }
    elseif (strlen($mot_de_passe) < 6) {
        $erreur = "Le mot de passe doit contenir au moins 6 caractères.";
        $etape  = 2;
    }
    elseif ($mot_de_passe !== $confirmation) {
        $erreur = "Les deux mots de passe ne correspondent pas.";
        $etape  = 2;
    }
    else {
        // Vérifier qu'aucun compte n'existe déjà
        $stmt = $pdo->prepare("SELECT * FROM donneur WHERE NNI = ?");
        $stmt->execute([$nni]);
        if ($stmt->fetch()) {
            $erreur = "Un compte existe déjà avec ce NNI.";
            $etape  = 2;
        } else {
            $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

            // Insertion : le groupe est auto-déclaré, marqué NON CONFIRMÉ
            // Selon le doc pi.docx : le groupe ne sera confirmé que par la banque/sous-banque
            // Pour l'instant, on stocke dans id_groupe MAIS ce groupe est considéré
            // comme "auto-déclaré" jusqu'à confirmation. Quand la colonne `groupe_auto_declare`
            // sera ajoutée à la table, on pourra séparer les deux.
            $stmt = $pdo->prepare("
                INSERT INTO donneur (NNI, id_groupe, email, telephone, mot_de_passe)
                VALUES (?, NULL, ?, ?, ?)
            ");
            $stmt->execute([
                $nni,
                $email ?: null,
                $telephone ?: null,
                $hash
            ]);

            // Si un groupe a été saisi, on le stocke à part dans une note temporaire
            // (à intégrer proprement quand la colonne groupe_auto_declare sera ajoutée)
            $_SESSION['groupe_auto_declare_temp'] = $id_groupe ?: null;

            $success = "Compte créé avec succès ! Vous pouvez maintenant vous connecter.";
            $etape   = 3; // Étape succès
        }
    }
}

// Récupérer la liste des groupes sanguins pour le sélecteur
$groupes = $pdo->query("SELECT * FROM groupe_sanguin ORDER BY libelle")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription — E-Sang</title>
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
           CÔTÉ GAUCHE — BRANDING (identique à index.php)
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

        .brand-logo { margin-bottom: 32px; }

        .brand-name {
            font-size: 72px;
            font-weight: 900;
            color: #111111;
            letter-spacing: -3px;
            margin-bottom: 24px;
            line-height: 1;
        }
        .brand-name .accent { color: #8B0000; }

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

        .brand-card { flex: 1; max-width: 200px; }
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
            width: 640px;
            flex-shrink: 0;
            background: #FAFAFA;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 50px;
            overflow-y: auto;
        }

        .form-card {
            width: 100%;
            max-width: 520px;
        }

        /* ══ STEPPER ══ */
        .stepper {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 32px;
        }

        .step-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            transition: all 0.3s;
            flex-shrink: 0;
        }

        .step-circle.active {
            background: #8B0000;
            color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(139, 0, 0, 0.3);
        }

        .step-circle.done {
            background: #16A34A;
            color: #FFFFFF;
        }

        .step-circle.pending {
            background: #E5E7EB;
            color: #9CA3AF;
        }

        .step-line {
            flex: 1;
            height: 4px;
            background: #E5E7EB;
            border-radius: 2px;
            transition: all 0.3s;
        }
        .step-line.done { background: #8B0000; }

        /* ══ TITRES ══ */
        .form-title {
            font-size: 34px;
            font-weight: 900;
            color: #111111;
            margin-bottom: 10px;
            letter-spacing: -1px;
        }

        .form-subtitle {
            font-size: 15px;
            color: #555555;
            margin-bottom: 28px;
            font-weight: 500;
        }

        .form-subtitle-verified {
            font-size: 15px;
            color: #16A34A;
            font-weight: 700;
            margin-bottom: 28px;
        }

        .form-subtitle-verified .reste {
            color: #555555;
            font-weight: 500;
        }

        /* ══ ALERTES ══ */
        .alerte-erreur {
            background: #FEF2F2;
            border: 2px solid #FCA5A5;
            color: #8B0000;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alerte-success {
            background: #F0FDF4;
            border: 2px solid #BBF7D0;
            color: #166534;
            border-radius: 12px;
            padding: 18px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 22px;
        }

        /* ══ BOX FORMULAIRE ══ */
        .form-box {
            background: #FFF5F5;
            border: 1.5px solid #FCA5A5;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 22px;
        }

        /* ══ CHAMPS ══ */
        .form-group { margin-bottom: 18px; }
        .form-group:last-child { margin-bottom: 0; }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #111111;
            margin-bottom: 8px;
        }
        .form-label .req { color: #8B0000; }

        .form-input,
        .form-select {
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            background: #FFFFFF;
            color: #111111;
            outline: none;
            transition: all 0.2s;
        }

        .form-input::placeholder { color: #9CA3AF; }

        .form-input:focus,
        .form-select:focus {
            border-color: #8B0000;
            box-shadow: 0 0 0 4px rgba(139, 0, 0, 0.08);
        }

        /* ══ CHAMPS VERROUILLÉS (Nom/Prénom récupérés) ══ */
        .form-input.locked {
            background: #F0FDF4;
            border-color: #BBF7D0;
            color: #166534;
            font-weight: 700;
            cursor: not-allowed;
        }

        /* ══ NOTE CONTACT OBLIGATOIRE ══ */
        .contact-note {
            font-size: 13px;
            color: #8B0000;
            font-weight: 700;
            margin-bottom: 12px;
            margin-top: -4px;
        }

        /* ══ BOUTONS ══ */
        .btn-principal {
            width: 100%;
            padding: 16px;
            background: #8B0000;
            color: #FFFFFF;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 6px 20px rgba(139, 0, 0, 0.28);
        }
        .btn-principal:hover {
            background: #6B0000;
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(139, 0, 0, 0.35);
        }

        .btn-retour {
            display: block;
            width: 100%;
            text-align: center;
            background: none;
            border: none;
            color: #6B7280;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            padding: 14px;
            margin-top: 14px;
            font-family: inherit;
            text-decoration: none;
            transition: color 0.15s;
        }
        .btn-retour:hover { color: #8B0000; }

        /* ══ LIEN CONNEXION ══ */
        .lien-connexion {
            text-align: center;
            font-size: 14px;
            color: #555555;
            margin-top: 18px;
            font-weight: 500;
        }
        .lien-connexion a {
            color: #8B0000;
            text-decoration: none;
            font-weight: 800;
        }
        .lien-connexion a:hover { text-decoration: underline; }

        /* ══ RESPONSIVE ══ */
        @media (max-width: 1024px) {
            body { flex-direction: column; }
            .left-side {
                padding: 40px;
                border-right: none;
                border-bottom: 1px solid #F3F4F6;
                box-shadow: none;
                text-align: center;
                align-items: center;
            }
            .right-side { width: 100%; padding: 30px 20px; }
            .brand-name { font-size: 52px; }
            .form-title { font-size: 28px; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ══════════════════════════════════════
     CÔTÉ GAUCHE — BRANDING
     ══════════════════════════════════════ -->
<div class="left-side">

    <div class="brand-logo">
        <svg width="72" height="92" viewBox="0 0 72 92" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M36 90C53.673 90 68 75.673 68 58C68 43 56 26 36 4C16 26 4 43 4 58C4 75.673 18.327 90 36 90Z" fill="#8B0000"/>
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

        <!-- ══ STEPPER ══ -->
        <div class="stepper">
            <div class="step-circle <?php
                if ($etape === 1) echo 'active';
                elseif ($etape >= 2) echo 'done';
            ?>">
                <?php echo ($etape >= 2) ? '✓' : '1'; ?>
            </div>
            <div class="step-line <?php if ($etape >= 2) echo 'done'; ?>"></div>
            <div class="step-circle <?php
                if ($etape === 1) echo 'pending';
                elseif ($etape === 2) echo 'active';
                else echo 'done';
            ?>">
                <?php echo ($etape >= 3) ? '✓' : '2'; ?>
            </div>
        </div>

        <?php if ($erreur): ?>
            <div class="alerte-erreur">
                ⚠️ <?php echo htmlspecialchars($erreur); ?>
            </div>
        <?php endif; ?>

        <?php // ══════════════════════════════════════════
              // ÉTAPE 3 — SUCCÈS (compte créé)
              // ══════════════════════════════════════════ ?>
        <?php if ($etape === 3 && $success): ?>

            <h2 class="form-title">Compte créé ! 🎉</h2>
            <p class="form-subtitle">Vous pouvez maintenant vous connecter.</p>

            <div class="alerte-success">
                ✅ <?php echo htmlspecialchars($success); ?>
                <br><br>
                <strong>📌 Important :</strong> Votre groupe sanguin sera confirmé par la banque de sang ou la sous-banque après analyse.
            </div>

            <a href="index.php" style="text-decoration: none;">
                <button class="btn-principal">
                    Se connecter maintenant
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </button>
            </a>

        <?php // ══════════════════════════════════════════
              // ÉTAPE 1 — VÉRIFICATION D'IDENTITÉ
              // ══════════════════════════════════════════ ?>
        <?php elseif ($etape === 1): ?>

            <h2 class="form-title">Vérification d'identité</h2>
            <p class="form-subtitle">Entrez votre NNI et date de naissance pour continuer</p>

            <form method="POST" action="">
                <div class="form-box">

                    <div class="form-group">
                        <label class="form-label">
                            NNI — Numéro National d'Identité <span class="req">*</span>
                        </label>
                        <input type="text" name="nni" class="form-input"
                               placeholder="Entrez vos 14 chiffres"
                               value="<?php echo htmlspecialchars($_POST['nni'] ?? ''); ?>"
                               autocomplete="off" required/>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">
                            Date de naissance <span class="req">*</span>
                        </label>
                        <input type="date" name="date_naissance" class="form-input"
                               value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>"
                               required/>
                    </div>

                </div>

                <button type="submit" name="verifier_identite" class="btn-principal">
                    Vérifier mon identité
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </button>
            </form>

            <div class="lien-connexion">
                Déjà inscrit ? <a href="index.php">Se connecter</a>
            </div>

        <?php // ══════════════════════════════════════════
              // ÉTAPE 2 — COMPLÉTER LE PROFIL
              // ══════════════════════════════════════════ ?>
        <?php elseif ($etape === 2 && $citoyen): ?>

            <h2 class="form-title">Complétez votre profil</h2>
            <p class="form-subtitle-verified">
                ✓ Identité vérifiée
                <span class="reste">— Remplissez les informations restantes</span>
            </p>

            <form method="POST" action="">
                <!-- Données cachées de l'étape 1 (sécurité) -->
                <input type="hidden" name="nni" value="<?php echo htmlspecialchars($citoyen['NNI']); ?>"/>
                <input type="hidden" name="date_naissance" value="<?php echo htmlspecialchars($citoyen['date_naissance']); ?>"/>

                <div class="form-box">

                    <!-- Nom + Prénom récupérés (verrouillés) -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Nom</label>
                            <input type="text" class="form-input locked"
                                   value="<?php echo htmlspecialchars(strtoupper($citoyen['nom'])); ?>" readonly/>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Prénom</label>
                            <input type="text" class="form-input locked"
                                   value="<?php echo htmlspecialchars($citoyen['prenom']); ?>" readonly/>
                        </div>
                    </div>

                    <!-- Note contact obligatoire -->
                    <div class="contact-note">⚠ Au moins un contact obligatoire</div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" name="telephone" class="form-input"
                                   placeholder="+222 ..."
                                   value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>"/>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-input"
                                   placeholder="email@exemple.com"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"/>
                        </div>
                    </div>

                    <!-- Groupe sanguin auto-déclaré -->
                    <div class="form-group">
                        <label class="form-label">
                            Groupe sanguin (auto-déclaré, sera confirmé par la banque)
                        </label>
                        <select name="id_groupe" class="form-select">
                            <option value="">— Je ne sais pas / À confirmer —</option>
                            <?php foreach ($groupes as $g): ?>
                                <option value="<?php echo $g['id_groupe']; ?>"
                                    <?php echo (($_POST['id_groupe'] ?? '') == $g['id_groupe']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g['libelle']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                Mot de passe <span class="req">*</span>
                            </label>
                            <input type="password" name="mot_de_passe" class="form-input"
                                   placeholder="Minimum 6 caractères" required/>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                Confirmation <span class="req">*</span>
                            </label>
                            <input type="password" name="confirmation" class="form-input"
                                   placeholder="Retapez le mot de passe" required/>
                        </div>
                    </div>

                </div>

                <button type="submit" name="creer_compte" class="btn-principal">
                    Finaliser l'inscription
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </button>
            </form>

            <a href="inscription.php" class="btn-retour">
                ← Retour à l'étape précédente
            </a>

        <?php endif; ?>

    </div>
</div>

</body>
</html>
