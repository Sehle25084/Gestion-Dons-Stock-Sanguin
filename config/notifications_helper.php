<?php
// ════════════════════════════════════════════════════════════════
// E-SANG — Helper de notifications hôpital
// Permet à la sous-banque (ou à toute autre entité) d'envoyer
// une notification à un hôpital.
//
// Usage :
//   require_once '../config/notifications_helper.php';
//   notifier_hopital($pdo, $id_hopital, 'Titre', 'Message', 'succes');
// ════════════════════════════════════════════════════════════════

/**
 * Envoie une notification à un hôpital.
 *
 * @param PDO    $pdo            Connexion PDO active.
 * @param int    $id_hopital     ID de l'hôpital destinataire.
 * @param string $titre          Titre court (max 200 caractères).
 * @param string $message        Corps du message (texte libre).
 * @param string $type           Type : 'info', 'succes', 'alerte', 'urgence'.
 * @param int|null $id_responsable Optionnel : cibler un responsable précis.
 *
 * @return bool true si l'insertion a réussi, false sinon (n'interrompt JAMAIS le flux principal).
 */
function notifier_hopital(PDO $pdo, int $id_hopital, string $titre, string $message, string $type = 'info', ?int $id_responsable = null): bool {

    // Sécurité : vérifier que le type est bien dans l'ENUM autorisé
    $types_valides = ['info', 'succes', 'alerte', 'urgence'];
    if (!in_array($type, $types_valides, true)) {
        $type = 'info';
    }

    // Tronquer le titre s'il dépasse 200 caractères (limite de la table)
    if (mb_strlen($titre) > 200) {
        $titre = mb_substr($titre, 0, 197) . '...';
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO notification_hopital
                (id_hopital, id_responsable, titre, message, type, lu, date_creation)
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        return $stmt->execute([$id_hopital, $id_responsable, $titre, $message, $type]);
    } catch (Exception $e) {
        // On NE bloque PAS le flux métier si la notification échoue.
        // (sinon une simple erreur de notif planterait une acceptation de demande !)
        error_log("notifier_hopital() failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Compte les notifications non lues d'un hôpital.
 * Utile pour afficher un badge dans la sidebar.
 *
 * @param PDO $pdo
 * @param int $id_hopital
 * @return int
 */
function compter_notifications_non_lues(PDO $pdo, int $id_hopital): int {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notification_hopital WHERE id_hopital = ? AND lu = 0");
        $stmt->execute([$id_hopital]);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}