<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hopital') {
    header('Location: ../index.php');
    exit;
}
if (!isset($_SESSION['id_hopital']) || !isset($_SESSION['id_responsable'])) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$id_hopital = $_SESSION['id_hopital'];
$id_responsable = $_SESSION['id_responsable'];
$page_active = 'profil';

$message_success = "";
$message_erreur = "";

// ── Traitement du formulaire de mise à jour ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profil'])) {
    $nom              = trim($_POST['nom']);
    $prenom           = trim($_POST['prenom']);
    $poste            = trim($_POST['poste']);
    $email            = trim($_POST['email']);
    $telephone        = trim($_POST['telephone']);
    $password_actuel  = $_POST['password_actuel']  ?? '';
    $password         = $_POST['password']         ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // ✔ CORRECTION : récupérer le hash actuel pour vérifier le mot de passe actuel
    $stmt = $pdo->prepare("SELECT mot_de_passe FROM responsable_hopital WHERE id_responsable = ?");
    $stmt->execute([$id_responsable]);
    $hash_actuel = $stmt->fetchColumn();

    // ── Validations ──
    if (empty($nom) || empty($prenom) || empty($email)) {
        $message_erreur = "Les champs Nom, Prénom et Email sont obligatoires.";
    }
    // ✔ CORRECTION : validation email serveur (filter_var)
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message_erreur = "Adresse email invalide.";
    }
    // ✔ CORRECTION : validation téléphone si fourni (8 chiffres ou +222XXXXXXXX)
    elseif (!empty($telephone) && !preg_match('/^(\+222\s?)?\d{8}$/', preg_replace('/\s+/', '', $telephone))) {
        $message_erreur = "Numéro de téléphone invalide (format attendu : 8 chiffres ou +222XXXXXXXX).";
    }
    // ✔ CORRECTION : si l'utilisateur veut changer son mot de passe, il doit prouver son identité
    elseif (!empty($password) && empty($password_actuel)) {
        $message_erreur = "Veuillez saisir votre mot de passe actuel pour le modifier.";
    }
    // ✔ CORRECTION : vérification du mot de passe actuel
    elseif (!empty($password) && !password_verify($password_actuel, $hash_actuel)) {
        $message_erreur = "Mot de passe actuel incorrect.";
    }
    // ✔ CORRECTION : longueur minimum 6 caractères sur le nouveau mot de passe
    elseif (!empty($password) && strlen($password) < 6) {
        $message_erreur = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
    }
    // ✔ CORRECTION : confirmation du nouveau mot de passe (anti-typo)
    elseif (!empty($password) && $password !== $password_confirm) {
        $message_erreur = "La confirmation du nouveau mot de passe ne correspond pas.";
    }
    else {
        try {
            if (!empty($password)) {
                // Mise à jour AVEC mot de passe
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE responsable_hopital SET nom=?, prenom=?, poste=?, email=?, telephone=?, mot_de_passe=? WHERE id_responsable=?");
                $stmt->execute([$nom, $prenom, $poste, $email, $telephone, $hash, $id_responsable]);
            } else {
                // Mise à jour SANS modifier le mot de passe
                $stmt = $pdo->prepare("UPDATE responsable_hopital SET nom=?, prenom=?, poste=?, email=?, telephone=? WHERE id_responsable=?");
                $stmt->execute([$nom, $prenom, $poste, $email, $telephone, $id_responsable]);
            }

            // Mettre à jour la session
            $_SESSION['nom_responsable']    = $nom;
            $_SESSION['prenom_responsable'] = $prenom;
            $_SESSION['poste_responsable']  = $poste;

            $message_success = !empty($password)
                ? "Votre profil et votre mot de passe ont été mis à jour avec succès."
                : "Votre profil a été mis à jour avec succès.";
        } catch (PDOException $e) {
            // Si l'email est déjà utilisé (contrainte UNIQUE)
            if ($e->getCode() == 23000) {
                $message_erreur = "Cet email est déjà utilisé par un autre compte.";
            } else {
                $message_erreur = "Une erreur est survenue lors de la mise à jour.";
            }
        }
    }
}

// ── Récupérer les infos actuelles du responsable ──
$stmt = $pdo->prepare("SELECT * FROM responsable_hopital WHERE id_responsable=?");
$stmt->execute([$id_responsable]);
$resp = $stmt->fetch(PDO::FETCH_ASSOC);

require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil — E-Sang</title>
    <style>
        <?php echo $shared_css; ?>
        
        .profil-container {
            max-width: 600px;
            background: #FFFFFF;
            border: 2px solid #E5E7EB;
            border-radius: 16px;
            padding: 28px 36px;
            margin-top: 10px;
        }
        
        .profil-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid #F3F4F6;
        }
        
        .profil-avatar {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: #FEF2F2;
            border: 3px solid #FCA5A5;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; font-weight: 800; color: #8B0000;
        }
        
        .profil-title h2 { font-size: 20px; font-weight: 800; color: #111111; margin-bottom: 2px; }
        .profil-title p { color: #6B7280; font-size: 13px; font-weight: 500; }
        
        .password-hint {
            font-size: 12px;
            color: #6B7280;
            margin-top: 4px;
        }
    </style>
</head>
<body>

<?php require_once 'sidebar.php'; ?>

<main class="main-content">
    <div class="page-header">
        <h1>Mon Profil</h1>
        <p>Gérez vos informations personnelles et identifiants de connexion</p>
    </div>

    <?php if ($message_success): ?>
        <div class="alerte-success">✅ <?php echo $message_success; ?></div>
    <?php endif; ?>
    
    <?php if ($message_erreur): ?>
        <div class="alerte-erreur">❌ <?php echo $message_erreur; ?></div>
    <?php endif; ?>

    <div class="profil-container">
        <div class="profil-header">
            <div class="profil-avatar">
                <?php echo strtoupper(substr($resp['prenom'], 0, 1) . substr($resp['nom'], 0, 1)); ?>
            </div>
            <div class="profil-title">
                <h2><?php echo htmlspecialchars($resp['prenom'] . ' ' . $resp['nom']); ?></h2>
                <p>Responsable — <?php echo htmlspecialchars($_SESSION['nom_hopital']); ?></p>
            </div>
        </div>

        <form method="POST" action="profil.php">
            <div class="form-row">
                <div class="form-group">
                    <label for="prenom">Prénom <span class="req">*</span></label>
                    <input type="text" name="prenom" id="prenom" required value="<?php echo htmlspecialchars($resp['prenom']); ?>">
                </div>
                <div class="form-group">
                    <label for="nom">Nom <span class="req">*</span></label>
                    <input type="text" name="nom" id="nom" required value="<?php echo htmlspecialchars($resp['nom']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="poste">Poste occupé</label>
                <input type="text" name="poste" id="poste" placeholder="Ex: Chef de service, Infirmier coordonnateur..." value="<?php echo htmlspecialchars($resp['poste'] ?? ''); ?>">
            </div>

            <div class="form-sep">Identifiants de connexion</div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Adresse Email <span class="req">*</span></label>
                    <input type="email" name="email" id="email" required value="<?php echo htmlspecialchars($resp['email']); ?>">
                </div>
                <div class="form-group">
                    <label for="telephone">Téléphone</label>
                    <input type="text" name="telephone" id="telephone" value="<?php echo htmlspecialchars($resp['telephone'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-sep">Sécurité — Modifier le mot de passe</div>

            <div class="password-info" style="background:#FEF3C7; border:1.5px solid #FDE68A; border-radius:10px; padding:12px 16px; margin-bottom:18px; font-size:13px; color:#92400E;">
                🔒 Pour modifier votre mot de passe, vous devez saisir votre <strong>mot de passe actuel</strong> ainsi qu'un nouveau mot de passe (et le confirmer).
                <br>Laissez les 3 champs vides si vous ne souhaitez pas changer de mot de passe.
            </div>

            <div class="form-group">
                <label for="password_actuel">Mot de passe actuel</label>
                <input type="password" name="password_actuel" id="password_actuel" placeholder="Saisissez votre mot de passe actuel" autocomplete="current-password">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Nouveau mot de passe</label>
                    <input type="password" name="password" id="password" placeholder="Minimum 6 caractères" minlength="6" autocomplete="new-password">
                    <div class="password-hint">Au moins 6 caractères.</div>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirmer le nouveau mot de passe</label>
                    <input type="password" name="password_confirm" id="password_confirm" placeholder="Retapez le nouveau mot de passe" minlength="6" autocomplete="new-password">
                </div>
            </div>

            <div style="margin-top: 32px; display: flex; justify-content: flex-end;">
                <button type="submit" name="update_profil" class="btn-submit" style="width: auto; padding: 14px 32px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:18px; height:18px; margin-right:8px;">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</main>

</body>
</html>
