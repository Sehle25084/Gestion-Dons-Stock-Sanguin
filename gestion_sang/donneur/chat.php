<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'donneur') {
    header("Location: ../index.php");
    exit;
}

$id_donneur = $_SESSION['id'];

// Donneur courant
$stmt = $pdo->prepare("SELECT * FROM donneur WHERE id_donneur = ?");
$stmt->execute([$id_donneur]);
$donneur = $stmt->fetch();

// Libellé du groupe sanguin
$groupe_libelle = null;
if ($donneur['id_groupe']) {
    $stmt = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
    $stmt->execute([$donneur['id_groupe']]);
    $g = $stmt->fetch();
    if ($g) $groupe_libelle = $g['libelle'];
}

// Citoyen courant (pour les initiales)
$stmt = $pdo_registre->prepare("SELECT prenom, nom FROM citoyen WHERE NNI = ?");
$stmt->execute([$donneur['NNI']]);
$me = $stmt->fetch();

// ===== Envoi d'un message =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $donneur['id_groupe']) {
    $contenu = trim($_POST['contenu'] ?? '');
    if ($contenu !== '' && mb_strlen($contenu) <= 1000) {
        $stmt = $pdo->prepare("
            INSERT INTO message_groupe (id_groupe, id_donneur, contenu, date_envoi)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$donneur['id_groupe'], $id_donneur, $contenu]);
    }
    header("Location: chat.php");
    exit;
}

// ===== Récupération des messages du groupe =====
$messages = [];
$citoyens_by_nni = [];
$nb_membres = 0;

if ($donneur['id_groupe']) {
    $stmt = $pdo->prepare("
        SELECT m.id_message, m.id_donneur, m.contenu, m.date_envoi, d.NNI
        FROM message_groupe m
        JOIN donneur d ON d.id_donneur = m.id_donneur
        WHERE m.id_groupe = ?
        ORDER BY m.date_envoi ASC
        LIMIT 200
    ");
    $stmt->execute([$donneur['id_groupe']]);
    $messages = $stmt->fetchAll();

    // Récupération des noms (un seul appel au registre national)
    $nnis = array_unique(array_column($messages, 'NNI'));
    if ($nnis) {
        $placeholders = implode(',', array_fill(0, count($nnis), '?'));
        $stmt = $pdo_registre->prepare("SELECT NNI, prenom, nom FROM citoyen WHERE NNI IN ($placeholders)");
        $stmt->execute(array_values($nnis));
        while ($row = $stmt->fetch()) {
            $citoyens_by_nni[$row['NNI']] = $row;
        }
    }

    // Nombre de membres du groupe (donneurs ayant le même groupe confirmé)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM donneur WHERE id_groupe = ?");
    $stmt->execute([$donneur['id_groupe']]);
    $nb_membres = (int)$stmt->fetchColumn();
}

// ===== Helpers =====
function initiales($prenom, $nom) {
    if ($prenom && $nom) {
        return mb_strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));
    }
    return '?';
}

function couleur_avatar($nni) {
    $colors = ['#8b1717', '#1e3a8a', '#166534', '#92400e', '#581c87', '#0e7490', '#9f1239', '#3730a3'];
    return $colors[abs(crc32((string)$nni)) % count($colors)];
}

function format_date_chat($datetime) {
    $ts = strtotime($datetime);
    $today = strtotime(date('Y-m-d'));
    if ($ts >= $today)              return "Aujourd'hui " . date('H:i', $ts);
    if ($ts >= $today - 86400)      return 'Hier ' . date('H:i', $ts);
    if ($ts >= $today - 86400 * 6)  return ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'][(int)date('w', $ts)] . ' ' . date('H:i', $ts);
    return date('d/m/Y H:i', $ts);
}

$page_active = 'chat';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat groupe — E-Sang Donneur</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php include 'sidebar.php'; ?>

    <!-- ====== Styles spécifiques chat ====== -->
    <style>
        .chat-header {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-left: 4px solid #8b1717;
            border-radius: 12px;
            padding: 18px 24px;
            margin: 20px 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .chat-header-left {
            display: flex; align-items: center; gap: 14px;
        }
        .chat-icon {
            width: 48px; height: 48px;
            border-radius: 50%;
            background: #fcebeb;
            color: #8b1717;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
        }
        .chat-header h2 {
            margin: 0;
            font-size: 19px;
            font-weight: 700;
            color: #1a1a1a;
        }
        .chat-header p {
            margin: 2px 0 0;
            font-size: 13px;
            color: #6b6b6b;
        }
        .chat-badge-groupe {
            background: #8b1717;
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
            padding: 8px 14px;
            border-radius: 8px;
            letter-spacing: 0.5px;
        }

        .chat-window {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            height: 65vh;
            min-height: 460px;
            overflow: hidden;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px 22px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            background: #fafafa;
        }
        .chat-messages::-webkit-scrollbar { width: 8px; }
        .chat-messages::-webkit-scrollbar-thumb {
            background: #d4d4d4; border-radius: 4px;
        }

        .chat-msg {
            display: flex;
            gap: 10px;
            max-width: 78%;
        }
        .chat-msg.mine {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .chat-avatar {
            width: 38px; height: 38px;
            border-radius: 50%;
            color: #ffffff;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .chat-msg-body {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .chat-msg.mine .chat-msg-body {
            align-items: flex-end;
        }

        .chat-msg-author {
            font-size: 12px;
            font-weight: 600;
            color: #6b6b6b;
            margin-bottom: 4px;
            padding: 0 4px;
        }

        .chat-bubble {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            color: #1a1a1a;
            padding: 10px 14px;
            border-radius: 14px;
            border-top-left-radius: 4px;
            font-size: 15px;
            line-height: 1.5;
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: pre-wrap;
        }
        .chat-msg.mine .chat-bubble {
            background: #8b1717;
            border-color: #8b1717;
            color: #ffffff;
            border-top-left-radius: 14px;
            border-top-right-radius: 4px;
        }

        .chat-time {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 4px;
            padding: 0 4px;
        }

        .chat-empty {
            margin: auto;
            text-align: center;
            color: #9ca3af;
            font-size: 14px;
            padding: 40px 20px;
        }
        .chat-empty-icon {
            font-size: 42px;
            margin-bottom: 12px;
        }

        .chat-form {
            border-top: 1px solid #e5e7eb;
            background: #ffffff;
            padding: 14px 16px;
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        .chat-form textarea {
            flex: 1;
            resize: none;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            line-height: 1.5;
            padding: 10px 14px;
            border: 1px solid #d4d4d4;
            border-radius: 10px;
            outline: none;
            min-height: 44px;
            max-height: 140px;
            color: #1a1a1a;
            transition: border-color 0.15s;
        }
        .chat-form textarea:focus {
            border-color: #8b1717;
            box-shadow: 0 0 0 3px rgba(139, 23, 23, 0.1);
        }
        .chat-form button {
            background: #8b1717;
            color: #ffffff;
            border: none;
            padding: 0 22px;
            height: 44px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.15s;
        }
        .chat-form button:hover  { background: #6b1010; }
        .chat-form button:active { background: #501313; }
        .chat-form button svg    { width: 16px; height: 16px; }

        .chat-locked {
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: 26px 24px;
            text-align: center;
            color: #78350f;
        }
        .chat-locked-icon { font-size: 40px; margin-bottom: 10px; }
        .chat-locked h3 { margin: 0 0 6px; font-size: 17px; color: #92400e; }
        .chat-locked p  { margin: 0; font-size: 14px; line-height: 1.5; }

        @media (max-width: 640px) {
            .chat-msg { max-width: 90%; }
            .chat-window { height: 70vh; }
        }
    </style>
</head>
<body>

<div class="main-content">

    <div class="page-header">
        <h1>Chat groupe</h1>
        <p>Échangez avec les donneurs de votre groupe sanguin.</p>
    </div>

    <?php if (!$donneur['id_groupe']): ?>
        <!-- Groupe pas encore confirmé -->
        <div class="chat-locked">
            <div class="chat-locked-icon">🔒</div>
            <h3>Chat verrouillé</h3>
            <p>Vous pourrez accéder au chat dès que votre groupe sanguin aura été confirmé par une banque de sang.<br>
            Veuillez vous présenter à un point de don pour effectuer l'analyse.</p>
        </div>

    <?php else: ?>
        <!-- En-tête du chat -->
        <div class="chat-header">
            <div class="chat-header-left">
                <div class="chat-icon">💬</div>
                <div>
                    <h2>Discussion groupe <?php echo htmlspecialchars($groupe_libelle); ?></h2>
                    <p><?php echo $nb_membres; ?> donneur<?php echo $nb_membres > 1 ? 's' : ''; ?> dans ce groupe</p>
                </div>
            </div>
            <span class="chat-badge-groupe"><?php echo htmlspecialchars($groupe_libelle); ?></span>
        </div>

        <!-- Fenêtre de chat -->
        <div class="chat-window">
            <div class="chat-messages" id="chat-messages">
                <?php if (!$messages): ?>
                    <div class="chat-empty">
                        <div class="chat-empty-icon">💬</div>
                        Aucun message pour l'instant.<br>
                        Soyez le premier à écrire dans le groupe <strong><?php echo htmlspecialchars($groupe_libelle); ?></strong> !
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $m):
                        $is_mine = ((int)$m['id_donneur'] === (int)$id_donneur);
                        $cit     = $citoyens_by_nni[$m['NNI']] ?? null;
                        $nom_aff = $cit ? ($cit['prenom'] . ' ' . $cit['nom']) : ('Donneur ' . substr($m['NNI'], -4));
                        $init    = $cit ? initiales($cit['prenom'], $cit['nom']) : '?';
                        $couleur = $is_mine ? '#8b1717' : couleur_avatar($m['NNI']);
                    ?>
                    <div class="chat-msg <?php echo $is_mine ? 'mine' : ''; ?>">
                        <div class="chat-avatar" style="background: <?php echo $couleur; ?>;"><?php echo $init; ?></div>
                        <div class="chat-msg-body">
                            <?php if (!$is_mine): ?>
                                <span class="chat-msg-author"><?php echo htmlspecialchars($nom_aff); ?></span>
                            <?php endif; ?>
                            <div class="chat-bubble"><?php echo htmlspecialchars($m['contenu']); ?></div>
                            <span class="chat-time"><?php echo format_date_chat($m['date_envoi']); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Formulaire d'envoi -->
            <form method="POST" class="chat-form" id="chat-form">
                <textarea
                    id="msg-input"
                    name="contenu"
                    placeholder="Écrivez votre message…"
                    maxlength="1000"
                    rows="1"
                    required></textarea>
                <button type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 2L11 13"/>
                        <path d="M22 2l-7 20-4-9-9-4 20-7z"/>
                    </svg>
                    Envoyer
                </button>
            </form>
        </div>
    <?php endif; ?>

</div>

<script>
    // Défilement automatique vers le dernier message
    const msgBox = document.getElementById('chat-messages');
    if (msgBox) msgBox.scrollTop = msgBox.scrollHeight;

    // Soumettre avec Entrée (Shift+Entrée pour saut de ligne)
    const input = document.getElementById('msg-input');
    const form  = document.getElementById('chat-form');
    if (input && form) {
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (input.value.trim() !== '') form.submit();
            }
        });

        // Auto-redimensionnement de la zone de texte
        input.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 140) + 'px';
        });
    }

    // Rafraîchissement auto toutes les 8 s — UNIQUEMENT si l'utilisateur n'écrit pas
    setInterval(() => {
        const isTyping = input && (document.activeElement === input || input.value.trim() !== '');
        if (!isTyping) window.location.reload();
    }, 8000);
</script>

</body>
</html>