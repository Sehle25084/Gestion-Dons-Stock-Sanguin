<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'donneur') {
    header("Location: ../index.php");
    exit;
}

$id_donneur = $_SESSION['id'];

// ── Donneur courant ──
$stmt = $pdo->prepare("SELECT * FROM donneur WHERE id_donneur = ?");
$stmt->execute([$id_donneur]);
$donneur = $stmt->fetch();

// ── Citoyen courant ──
$stmt = $pdo_registre->prepare("SELECT prenom, nom FROM citoyen WHERE NNI = ?");
$stmt->execute([$donneur['NNI']]);
$citoyen = $stmt->fetch();

// ── Libellé du groupe sanguin ──
$groupe_libelle = null;
if ($donneur['id_groupe']) {
    $stmt = $pdo->prepare("SELECT libelle FROM groupe_sanguin WHERE id_groupe = ?");
    $stmt->execute([$donneur['id_groupe']]);
    $g = $stmt->fetch();
    if ($g) $groupe_libelle = $g['libelle'];
}

// ── Envoi d'un message ──
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

// ── Récupération des messages ──
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

    // N+1 query résolu : un seul appel au registre national
    $nnis = array_unique(array_column($messages, 'NNI'));
    if ($nnis) {
        $placeholders = implode(',', array_fill(0, count($nnis), '?'));
        $stmt = $pdo_registre->prepare("SELECT NNI, prenom, nom FROM citoyen WHERE NNI IN ($placeholders)");
        $stmt->execute(array_values($nnis));
        while ($row = $stmt->fetch()) {
            $citoyens_by_nni[$row['NNI']] = $row;
        }
    }

    // Nombre de membres du groupe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM donneur WHERE id_groupe = ?");
    $stmt->execute([$donneur['id_groupe']]);
    $nb_membres = (int)$stmt->fetchColumn();
}

// ── Helpers ──
function initiales($prenom, $nom) {
    if ($prenom && $nom) {
        return mb_strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));
    }
    return '?';
}

function couleur_avatar($nni) {
    $colors = ['#8B0000', '#1E3A8A', '#166534', '#92400E', '#581C87', '#0E7490', '#9F1239', '#3730A3'];
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
require_once '_style.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat groupe — E-Sang Donneur</title>
    <style>
        <?php echo $shared_css; ?>

        /* ── En-tête chat ── */
        .chat-header {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-left: 4px solid #8B0000;
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .chat-header-left { display: flex; align-items: center; gap: 14px; }
        .chat-icon-wrap {
            width: 48px; height: 48px;
            border-radius: 12px;
            background: #FEF2F2;
            color: #8B0000;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            border: 1.5px solid #FCA5A5;
        }
        .chat-header h2 {
            margin: 0; font-size: 17px; font-weight: 800; color: #111111;
        }
        .chat-header p {
            margin: 2px 0 0; font-size: 12px; color: #6B7280;
        }
        .chat-badge-groupe {
            background: #8B0000; color: #FFFFFF;
            font-weight: 800; font-size: 14px;
            padding: 8px 14px; border-radius: 10px;
            letter-spacing: 0.5px;
        }

        /* ── Fenêtre de chat ── */
        .chat-window {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            height: 60vh;
            min-height: 420px;
            overflow: hidden;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px 22px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            background: #FAFAFA;
        }
        .chat-messages::-webkit-scrollbar { width: 8px; }
        .chat-messages::-webkit-scrollbar-thumb { background: #D4D4D4; border-radius: 4px; }

        .chat-msg {
            display: flex; gap: 10px; max-width: 78%;
        }
        .chat-msg.mine {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        .chat-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            color: #FFFFFF;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700;
            flex-shrink: 0;
        }
        .chat-msg-body {
            display: flex; flex-direction: column; min-width: 0;
        }
        .chat-msg.mine .chat-msg-body { align-items: flex-end; }
        .chat-msg-author {
            font-size: 11px; font-weight: 700; color: #6B7280;
            margin-bottom: 4px; padding: 0 4px;
        }
        .chat-bubble {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            color: #111111;
            padding: 10px 14px;
            border-radius: 14px;
            border-top-left-radius: 4px;
            font-size: 14px;
            line-height: 1.5;
            word-wrap: break-word;
            white-space: pre-wrap;
        }
        .chat-msg.mine .chat-bubble {
            background: #8B0000;
            border-color: #8B0000;
            color: #FFFFFF;
            border-top-left-radius: 14px;
            border-top-right-radius: 4px;
        }
        .chat-time {
            font-size: 10px; color: #9CA3AF;
            margin-top: 4px; padding: 0 4px;
        }

        .chat-empty {
            margin: auto;
            text-align: center;
            color: #9CA3AF;
            font-size: 14px;
            padding: 40px 20px;
        }
        .chat-empty-icon { font-size: 42px; margin-bottom: 12px; }

        /* ── Formulaire ── */
        .chat-form {
            border-top: 1.5px solid #E5E7EB;
            background: #FFFFFF;
            padding: 14px 16px;
            display: flex; gap: 10px; align-items: flex-end;
        }
        .chat-form textarea {
            flex: 1;
            resize: none;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            line-height: 1.5;
            padding: 10px 14px;
            border: 1.5px solid #E5E7EB;
            border-radius: 10px;
            outline: none;
            min-height: 44px;
            max-height: 140px;
            color: #111111;
            transition: border-color 0.15s;
        }
        .chat-form textarea:focus {
            border-color: #8B0000;
            box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.08);
        }
        .chat-form button {
            background: #8B0000;
            color: #FFFFFF;
            border: none;
            padding: 0 22px;
            height: 44px;
            border-radius: 10px;
            font-size: 14px; font-weight: 700;
            cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            transition: background 0.15s;
            font-family: inherit;
        }
        .chat-form button:hover { background: #6B0000; }
        .chat-form button svg   { width: 16px; height: 16px; }

        /* ── Chat verrouillé ── */
        .chat-locked {
            background: #FEF3C7;
            border: 1.5px solid #FCD34D;
            border-radius: 14px;
            padding: 30px 24px;
            text-align: center;
            color: #78350F;
        }
        .chat-locked-icon { font-size: 42px; margin-bottom: 12px; }
        .chat-locked h3 { margin: 0 0 6px; font-size: 18px; color: #92400E; font-weight: 800; }
        .chat-locked p  { margin: 0; font-size: 14px; line-height: 1.6; }

        @media (max-width: 640px) {
            .chat-msg { max-width: 90%; }
            .chat-window { height: 65vh; }
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
        <h1>Chat groupe</h1>
        <p>Échangez avec les donneurs de votre groupe sanguin.</p>
    </div>

    <?php if (!$donneur['id_groupe']): ?>
        <!-- ══ Chat verrouillé ══ -->
        <div class="chat-locked">
            <div class="chat-locked-icon">🔒</div>
            <h3>Chat verrouillé</h3>
            <p>Vous pourrez accéder au chat dès que votre groupe sanguin aura été confirmé par une banque de sang.<br>
            Veuillez vous présenter à un point de don pour effectuer l'analyse.</p>
        </div>

    <?php else: ?>
        <!-- ══ En-tête du chat ══ -->
        <div class="chat-header">
            <div class="chat-header-left">
                <div class="chat-icon-wrap">💬</div>
                <div>
                    <h2>Discussion groupe <?php echo htmlspecialchars($groupe_libelle); ?></h2>
                    <p><?php echo $nb_membres; ?> donneur<?php echo $nb_membres > 1 ? 's' : ''; ?> dans ce groupe</p>
                </div>
            </div>
            <span class="chat-badge-groupe"><?php echo htmlspecialchars($groupe_libelle); ?></span>
        </div>

        <!-- ══ Fenêtre de chat ══ -->
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
                        $couleur = $is_mine ? '#8B0000' : couleur_avatar($m['NNI']);
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

            <!-- ══ Formulaire d'envoi ══ -->
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
        // Auto-redimensionnement
        input.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 140) + 'px';
        });
    }

    // Rafraîchissement auto toutes les 8s (sauf si l'utilisateur écrit)
    setInterval(() => {
        const isTyping = input && (document.activeElement === input || input.value.trim() !== '');
        if (!isTyping) window.location.reload();
    }, 8000);
</script>

</body>
</html>