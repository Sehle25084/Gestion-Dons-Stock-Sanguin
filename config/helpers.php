<?php

function sortirPochettesFIFO($pdo, $id_banque, $id_groupe, $quantite)
{
    // اختيار أقدم الأكياس المتاحة حسب تاريخ الجمع
    $stmt = $pdo->prepare("
        SELECT id_pochette
        FROM pochette
        WHERE id_banque = ?
          AND id_groupe = ?
          AND statut = 'disponible'
        ORDER BY date_collecte ASC, date_expiration ASC
        LIMIT ?
    ");

    $stmt->bindValue(1, $id_banque, PDO::PARAM_INT);
    $stmt->bindValue(2, $id_groupe, PDO::PARAM_INT);
    $stmt->bindValue(3, (int)$quantite, PDO::PARAM_INT);
    $stmt->execute();

    $pochettes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // إذا عدد الأكياس المتاحة أقل من الكمية المطلوبة
    if (count($pochettes) < $quantite) {
        return false;
    }

    // تغيير حالة الأكياس إلى مستعملة
    foreach ($pochettes as $id_pochette) {
        $update = $pdo->prepare("
            UPDATE pochette
            SET statut = 'utilisee'
            WHERE id_pochette = ?
        ");
        $update->execute([$id_pochette]);
    }

    return true;
}

function verifierPochettesExpirees($pdo, $id_banque)
{
    // Récupérer les pochettes expirées encore disponibles
    $stmt = $pdo->prepare("
        SELECT id_pochette, id_groupe
        FROM pochette
        WHERE id_banque = ?
          AND statut = 'disponible'
          AND date_expiration < CURDATE()
    ");
    $stmt->execute([$id_banque]);
    $pochettes = $stmt->fetchAll();

    foreach ($pochettes as $pochette) {

        // Marquer la pochette comme expirée
        $pdo->prepare("
            UPDATE pochette
            SET statut = 'expiree'
            WHERE id_pochette = ?
        ")->execute([$pochette['id_pochette']]);

        // Ajouter la pochette dans les déchets
        $pdo->prepare("
            INSERT INTO poches_dechets
            (id_pochette, raison_rejet, date_rejet)
            VALUES (?, 'Date expiration atteinte', CURDATE())
        ")->execute([$pochette['id_pochette']]);

        // Diminuer le stock
        $pdo->prepare("
            UPDATE stock
            SET quantite_disponible = quantite_disponible - 1,
                date_mise_a_jour = CURDATE()
            WHERE id_banque = ?
              AND id_groupe = ?
              AND quantite_disponible > 0
        ")->execute([
            $id_banque,
            $pochette['id_groupe']
        ]);
    }
}

function envoyerNotification($pdo, $type_destinataire, $id_destinataire, $message)
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notification
        WHERE type_destinataire = ?
          AND id_destinataire = ?
          AND message = ?
          AND date_notification >= DATE_SUB(NOW(), INTERVAL 2 DAY)
    ");

    $stmt->execute([
        $type_destinataire,
        $id_destinataire,
        $message
    ]);

    if ($stmt->fetchColumn() > 0) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO notification
        (type_destinataire, id_destinataire, message)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $type_destinataire,
        $id_destinataire,
        $message
    ]);
}

function verifierSeuilAlerte($pdo, $id_banque, $id_groupe)
{
    $stmt = $pdo->prepare("
        SELECT quantite_disponible, seuil_alerte
        FROM stock
        WHERE id_banque = ? AND id_groupe = ?
    ");

    $stmt->execute([$id_banque, $id_groupe]);
    $stock = $stmt->fetch();

    if (!$stock) {
        return;
    }

    if ($stock['quantite_disponible'] <= $stock['seuil_alerte']) {

        // Récupérer les donneurs du même groupe sanguin
        $stmt = $pdo->prepare("
            SELECT id_donneur
            FROM donneur
            WHERE id_groupe = ?
        ");

        $stmt->execute([$id_groupe]);
        $donneurs = $stmt->fetchAll();

        foreach ($donneurs as $donneur) {

            envoyerNotification(
                $pdo,
                'donneur',
                $donneur['id_donneur'],
                'Votre groupe sanguin est actuellement en demande. Nous vous invitons à effectuer un don si possible.'
            );
        }
    }
}

function enregistrerActivite($pdo, $role_utilisateur, $id_utilisateur, $action)
{
    $stmt = $pdo->prepare("
        INSERT INTO log_activite
        (role_utilisateur, id_utilisateur, action)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $role_utilisateur,
        $id_utilisateur,
        $action
    ]);
}