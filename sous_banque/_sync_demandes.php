<?php
// ════════════════════════════════════════════════════════════════
// SYNCHRONISATION COMMUNE — Demandes externes acceptées par la banque
//
// Quand la banque principale accepte une demande externe envoyée par
// la sous-banque, ce script crée le lot correspondant, met à jour
// stock_sous_banque, trace le mouvement, et résout les alertes
// devenues obsolètes.
//
// IMPORTANT : ce fichier doit être inclus (require_once) dans TOUTES
// les pages de sous_banque/ (dashboard, stock, lots, demandes,
// alertes, historique), pas seulement demandes.php — sinon le stock
// reste obsolète tant que l'agent n'a pas visité la page qui contient
// cette synchronisation.
//
// Pré-requis : $pdo et $id_sb doivent déjà être définis avant l'include.
// ════════════════════════════════════════════════════════════════

function synchroniserDemandesExternesAcceptees($pdo, $id_sb) {
    $stmt = $pdo->prepare("
        SELECT d.id_demande, d.id_groupe, d.quantite_demandee, g.libelle AS groupe
        FROM demande d
        JOIN groupe_sanguin g ON g.id_groupe = d.id_groupe
        WHERE d.id_sous_banque = ?
          AND d.type_demande = 'externe'
          AND d.statut = 'acceptée'
          AND NOT EXISTS (
              SELECT 1 FROM lot_sang_sous_banque l
              WHERE l.id_demande_origine = d.id_demande
          )
        FOR UPDATE
    ");
    $stmt->execute([$id_sb]);
    $demandes_a_integrer = $stmt->fetchAll();

    foreach ($demandes_a_integrer as $d_ext) {
        $pdo->beginTransaction();
        try {
            $qte    = (int)$d_ext['quantite_demandee'];
            $id_grp = (int)$d_ext['id_groupe'];
            $id_dem = (int)$d_ext['id_demande'];

            // 1. Créer le lot dans lot_sang_sous_banque
            $date_entree     = date('Y-m-d');
            $date_expiration = date('Y-m-d', strtotime('+42 days')); // 6 semaines standard
            $pdo->prepare("
                INSERT INTO lot_sang_sous_banque
                    (id_sous_banque, id_groupe, quantite, quantite_initiale,
                     date_entree, date_expiration, origine, id_demande_origine, statut)
                VALUES (?, ?, ?, ?, ?, ?, 'banque_principale', ?, 'disponible')
            ")->execute([$id_sb, $id_grp, $qte, $qte, $date_entree, $date_expiration, $id_dem]);

            // 2. Mettre à jour stock_sous_banque (upsert)
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM stock_sous_banque WHERE id_sous_banque = ? AND id_groupe = ?");
            $stmtCheck->execute([$id_sb, $id_grp]);
            if ((int)$stmtCheck->fetchColumn() > 0) {
                $pdo->prepare("
                    UPDATE stock_sous_banque
                    SET quantite_disponible = quantite_disponible + ?,
                        date_mise_a_jour = NOW()
                    WHERE id_sous_banque = ? AND id_groupe = ?
                ")->execute([$qte, $id_sb, $id_grp]);
            } else {
                $pdo->prepare("
                    INSERT INTO stock_sous_banque (id_sous_banque, id_groupe, quantite_disponible, seuil_alerte, date_mise_a_jour)
                    VALUES (?, ?, ?, 5, NOW())
                ")->execute([$id_sb, $id_grp, $qte]);
            }

            // 3. Mouvement de stock
            $pdo->prepare("
                INSERT INTO mouvement_stock (id_sous_banque, id_groupe, type_mouvement, quantite, date_mouvement, reference_demande, commentaire)
                VALUES (?, ?, 'entree_transfert', ?, NOW(), ?, 'Transfert banque principale — demande acceptée')
            ")->execute([$id_sb, $id_grp, $qte, $id_dem]);

            // 4. Historique
            $pdo->prepare("
                INSERT INTO historique_sous_banque (id_sous_banque, id_groupe, type_action, quantite, description, id_utilisateur, date_action)
                VALUES (?, ?, 'entree_stock', ?, ?, ?, NOW())
            ")->execute([
                $id_sb, $id_grp, $qte,
                "Réception de {$qte} pochette(s) {$d_ext['groupe']} — demande #{$id_dem} acceptée par la banque principale",
                null
            ]);

            // 5. Marquer les alertes comme traitées si le stock est repassé au-dessus du seuil
            $stmtSeuil = $pdo->prepare("SELECT quantite_disponible, seuil_alerte FROM stock_sous_banque WHERE id_sous_banque = ? AND id_groupe = ?");
            $stmtSeuil->execute([$id_sb, $id_grp]);
            $rowSeuil = $stmtSeuil->fetch();
            if ($rowSeuil && (int)$rowSeuil['quantite_disponible'] > (int)$rowSeuil['seuil_alerte']) {
                $pdo->prepare("UPDATE alerte_stock SET traitee = 1 WHERE id_sous_banque = ? AND id_groupe = ? AND traitee = 0")
                    ->execute([$id_sb, $id_grp]);
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

// Exécution automatique dès l'inclusion de ce fichier
if (isset($pdo, $id_sb)) {
    synchroniserDemandesExternesAcceptees($pdo, $id_sb);
}