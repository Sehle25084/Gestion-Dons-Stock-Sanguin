-- ============================================================
-- BASE DE DONNÉES UNIFIÉE — db_gestion_sang
-- Fusion de toutes les versions existantes
-- Date de génération : 2026-06-18
-- Serveur cible : MySQL 8.4+
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `db_gestion_sang`
--

-- ============================================================
-- 1. TABLE DE RÉFÉRENCE
-- ============================================================

--
-- Table `groupe_sanguin`
-- Source : Toutes les versions (identique)
--

DROP TABLE IF EXISTS `groupe_sanguin`;
CREATE TABLE IF NOT EXISTS `groupe_sanguin` (
  `id_groupe` int NOT NULL AUTO_INCREMENT,
  `libelle` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_groupe`),
  UNIQUE KEY `uq_groupe_libelle` (`libelle`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Données de `groupe_sanguin`
--

INSERT INTO `groupe_sanguin` (`id_groupe`, `libelle`) VALUES
(1, 'A+'),
(2, 'A-'),
(3, 'B+'),
(4, 'B-'),
(5, 'AB+'),
(6, 'AB-'),
(7, 'O+'),
(8, 'O-');

-- ============================================================
-- 2. ACTEURS PRINCIPAUX
-- ============================================================

--
-- Table `administrateur`
-- Source : Version (6) + gemini-code (colonnes nom, prenom ajoutées)
--

DROP TABLE IF EXISTS `administrateur`;
CREATE TABLE IF NOT EXISTS `administrateur` (
  `id_admin` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prenom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_admin`),
  UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Données de `administrateur`
--

INSERT INTO `administrateur` (`id_admin`, `nom`, `prenom`, `email`, `mot_de_passe`, `telephone`) VALUES
(1, 'Principal', 'Admin', 'admin@gestion-sang.mr', '$2y$10$fEeDSCp/TkuVYHU7tkreTuUeW8T./c7/cQJx6qCPsorzxahsMVWpe', '00000000');

-- --------------------------------------------------------

--
-- Table `banque_de_sang`
-- Source : Toutes les versions (identique)
--

DROP TABLE IF EXISTS `banque_de_sang`;
CREATE TABLE IF NOT EXISTS `banque_de_sang` (
  `id_banque` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `wilaya` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_banque`),
  UNIQUE KEY `uq_banque_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Données de `banque_de_sang`
--

INSERT INTO `banque_de_sang` (`id_banque`, `nom`, `wilaya`, `telephone`, `email`, `mot_de_passe`) VALUES
(3, 'test', 'test', '00000000', 'test@test.mr', '$2y$10$jdjwEiSQnp0.AfYwABnDc.OQio/XmjBQ4.zK5N20hF4H518FzInIC');

-- --------------------------------------------------------

--
-- Table `hopital`
-- Source : Fusion de toutes les versions + migration (ajout id_banque)
--

DROP TABLE IF EXISTS `hopital`;
CREATE TABLE IF NOT EXISTS `hopital` (
  `id_hopital` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `wilaya` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_banque` int DEFAULT NULL COMMENT 'Lien vers la banque de sang affiliée',
  PRIMARY KEY (`id_hopital`),
  UNIQUE KEY `uq_hopital_email` (`email`),
  KEY `fk_hopital_banque` (`id_banque`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Données de `hopital`
--

INSERT INTO `hopital` (`id_hopital`, `nom`, `wilaya`, `telephone`, `email`, `mot_de_passe`, `id_banque`) VALUES
(1, 'chiffa', 'tvz', '33333333', 'opital@exemple.mr', '$2y$10$4DeB3OyhvKCPE0EH72.IQujEaj82Z0t85xZnaq43nI9hK3KP8pVbO', NULL),
(2, 'A', 'A', '00000000', 'hopit@exemple.mr', '$2y$10$olkHOIaXTf5VBntFj4SZg.f2Mj3ZKp0ytCYY5hw03zwExqcDJuSmy', NULL);

-- --------------------------------------------------------

--
-- Table `responsable_hopital`
-- Source : migration + gemini-code (multi-utilisateurs par hôpital)
--

DROP TABLE IF EXISTS `responsable_hopital`;
CREATE TABLE IF NOT EXISTS `responsable_hopital` (
  `id_responsable` int NOT NULL AUTO_INCREMENT,
  `id_hopital` int NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `poste` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_responsable`),
  UNIQUE KEY `uq_responsable_email` (`email`),
  KEY `fk_responsable_hopital` (`id_hopital`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table `donneur`
-- Source : Version (6) — la plus complète (avec groupe_auto_declare,
--          groupe_confirme, disponible, identite_verifiee)
--        + gemini-code (statut_compte)
--

DROP TABLE IF EXISTS `donneur`;
CREATE TABLE IF NOT EXISTS `donneur` (
  `id_donneur` int NOT NULL AUTO_INCREMENT,
  `NNI` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_groupe` int DEFAULT NULL COMMENT 'Groupe sanguin confirmé par analyse',
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `groupe_auto_declare` int DEFAULT NULL COMMENT 'Groupe sanguin déclaré par le donneur lui-même',
  `groupe_confirme` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'TRUE si le groupe a été confirmé par analyse',
  `disponible` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'TRUE si le donneur est disponible pour donner',
  `identite_verifiee` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'TRUE = donneur s est présenté physiquement à la banque et son identité a été vérifiée',
  `statut_compte` enum('actif','suspendu') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'actif',
  PRIMARY KEY (`id_donneur`),
  UNIQUE KEY `uq_donneur_nni` (`NNI`),
  UNIQUE KEY `uq_donneur_email` (`email`),
  KEY `fk_donneur_groupe` (`id_groupe`),
  KEY `fk_donneur_groupe_auto` (`groupe_auto_declare`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Données de `donneur`
-- Source : Version (3) / (sang .sql) — 4 donneurs (le jeu de données le plus complet)
--        + Version (6) pour les champs supplémentaires
--

INSERT INTO `donneur` (`id_donneur`, `NNI`, `id_groupe`, `email`, `telephone`, `mot_de_passe`, `groupe_auto_declare`, `groupe_confirme`, `disponible`, `identite_verifiee`, `statut_compte`) VALUES
(1, '0123456789', 8, 'zemraguisehle@gmail.com', '36050593', '$2y$10$VXl0GItJ4ntnZBkO13TMyuob01PyIzzXqS3iXXOvqeQEDBdb5Sudq', NULL, 0, 0, 0, 'actif'),
(2, '1111111111', NULL, 'ahmed@gmail.com', '00000000', '$2y$10$xkIrREnCsNvhSF3Mg1cRqOuCti0SC92NSD9fkZyJUiKjew1VxseQe', NULL, 0, 0, 0, 'actif'),
(3, '3333333333', NULL, 'meymohamed@gmail.com', '43326369', '$2y$10$gDMMaDk/z8nbucuKSkZjd.CdvhUOv/jjzYv40QduGInXalxr9v80S', NULL, 0, 0, 0, 'actif'),
(4, '2222222222', NULL, 'FatimaMintAli@gmail.com', '22222222', '$2y$10$NJXHVwvTsK9Z6Hzxq4bJaeermttzgQVrNzRt76YN6yCSev3ZN0LVS', NULL, 0, 0, 0, 'actif');

-- ============================================================
-- 3. SOUS-BANQUES ET UTILISATEURS
-- ============================================================

--
-- Table `sous_banque`
-- Source : Version (6) — la plus complète (avec id_banque_principale,
--          id_hopital, date_creation, email, mot_de_passe)
--

DROP TABLE IF EXISTS `sous_banque`;
CREATE TABLE IF NOT EXISTS `sous_banque` (
  `id_sous_banque` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_hopital` int NOT NULL,
  `id_banque_principale` int NOT NULL,
  `date_creation` date NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_sous_banque`),
  KEY `fk_sb_banque` (`id_banque_principale`),
  KEY `fk_sb_hopital` (`id_hopital`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Données de `sous_banque`
--

INSERT INTO `sous_banque` (`id_sous_banque`, `nom`, `id_hopital`, `id_banque_principale`, `date_creation`, `email`, `mot_de_passe`) VALUES
(1, 'Ma Nouvelle Sous-Banque', 1, 3, '2026-06-15', 'test@sousbanque.com', '$2y$10$Mv9uAGvn1YyrKX9Av55xfu4jxnlA4nS9AJfCVWKkPas8Eki5y.A.i');

-- --------------------------------------------------------

--
-- Table `utilisateur_sous_banque`
-- Source : Version (6) + gemini-code
--

DROP TABLE IF EXISTS `utilisateur_sous_banque`;
CREATE TABLE IF NOT EXISTS `utilisateur_sous_banque` (
  `id_utilisateur` int NOT NULL AUTO_INCREMENT,
  `id_sous_banque` int NOT NULL,
  `nom_complet` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `login` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('actif','inactif') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'actif',
  PRIMARY KEY (`id_utilisateur`),
  UNIQUE KEY `uq_usb_login` (`login`),
  KEY `fk_usb_sous_banque` (`id_sous_banque`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. DONS ET ANALYSES MÉDICALES
-- ============================================================

--
-- Table `don`
-- Source : Toutes les versions (identique)
--

DROP TABLE IF EXISTS `don`;
CREATE TABLE IF NOT EXISTS `don` (
  `id_don` int NOT NULL AUTO_INCREMENT,
  `id_donneur` int NOT NULL,
  `id_banque` int NOT NULL,
  `date_don` date NOT NULL,
  `id_groupe` int NOT NULL,
  `quantite` decimal(6,2) NOT NULL,
  `statut` enum('accepté','refusé','en_attente') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  PRIMARY KEY (`id_don`),
  KEY `idx_don_donneur` (`id_donneur`),
  KEY `idx_don_banque` (`id_banque`),
  KEY `fk_don_groupe` (`id_groupe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table `analyse_sang`
-- Source : gemini-code — version la plus complète
--          (fusion de l'ancienne table `analyse` avec les tests sérologiques)
--

DROP TABLE IF EXISTS `analyse_sang`;
CREATE TABLE IF NOT EXISTS `analyse_sang` (
  `id_analyse` int NOT NULL AUTO_INCREMENT,
  `id_don` int NOT NULL COMMENT 'Don analysé',
  `id_banque` int NOT NULL COMMENT 'Banque ayant effectué l''analyse',
  `groupe_confirme` int DEFAULT NULL COMMENT 'Groupe sanguin confirmé après analyse',
  `hemoglobine` decimal(4,1) DEFAULT NULL,
  `tension` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `poids` decimal(5,2) DEFAULT NULL,
  `vih` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = positif, 0 = négatif',
  `hepatite_b` tinyint(1) NOT NULL DEFAULT '0',
  `hepatite_c` tinyint(1) NOT NULL DEFAULT '0',
  `syphilis` tinyint(1) NOT NULL DEFAULT '0',
  `resultat_global` enum('conforme','non_conforme','en_cours') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_cours',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Remarques et commentaires médicaux du technicien',
  `date_analyse` datetime DEFAULT CURRENT_TIMESTAMP,
  `id_technicien` int DEFAULT NULL COMMENT 'Référence vers utilisateur_sous_banque (optionnel)',
  PRIMARY KEY (`id_analyse`),
  UNIQUE KEY `uq_analyse_don` (`id_don`),
  KEY `idx_analyse_banque` (`id_banque`),
  KEY `fk_analyse_groupe` (`groupe_confirme`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. GESTION DES STOCKS ET MOUVEMENTS
-- ============================================================

--
-- Table `stock` (Banque Principale)
-- Source : Version (6) + gemini-code
--          Ajout de id_sous_banque et seuil_alerte depuis v6
--

DROP TABLE IF EXISTS `stock`;
CREATE TABLE IF NOT EXISTS `stock` (
  `id_stock` int NOT NULL AUTO_INCREMENT,
  `id_banque` int NOT NULL,
  `id_sous_banque` int DEFAULT NULL COMMENT 'NULL = stock de la banque principale',
  `id_groupe` int NOT NULL,
  `quantite_disponible` decimal(6,2) NOT NULL DEFAULT '0.00',
  `date_mise_a_jour` date NOT NULL,
  `date_expiration` date NOT NULL COMMENT 'Date de péremption du stock sanguin',
  `seuil_alerte` decimal(6,2) NOT NULL DEFAULT '2.00' COMMENT 'Seuil minimal avant alerte',
  PRIMARY KEY (`id_stock`),
  KEY `idx_stock_banque` (`id_banque`),
  KEY `fk_stock_groupe` (`id_groupe`),
  KEY `fk_stock_sous_banque` (`id_sous_banque`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table `stock_sous_banque`
-- Source : Version (6) + gemini-code (ajout seuil_alerte)
--

DROP TABLE IF EXISTS `stock_sous_banque`;
CREATE TABLE IF NOT EXISTS `stock_sous_banque` (
  `id_stock_sb` int NOT NULL AUTO_INCREMENT,
  `id_sous_banque` int NOT NULL,
  `id_groupe` int NOT NULL,
  `quantite_disponible` int NOT NULL DEFAULT '0',
  `seuil_alerte` int NOT NULL DEFAULT '5' COMMENT 'Seuil minimal avant déclenchement d''alerte',
  `date_mise_a_jour` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_stock_sb`),
  UNIQUE KEY `uq_sb_groupe` (`id_sous_banque`,`id_groupe`),
  KEY `fk_ssb_groupe` (`id_groupe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table `mouvement_stock`
-- Source : gemini-code — version la plus complète
--          (types de mouvement enrichis, commentaire)
--

DROP TABLE IF EXISTS `mouvement_stock`;
CREATE TABLE IF NOT EXISTS `mouvement_stock` (
  `id_mouvement` int NOT NULL AUTO_INCREMENT,
  `id_sous_banque` int DEFAULT NULL,
  `id_banque` int DEFAULT NULL,
  `id_hopital` int DEFAULT NULL,
  `id_groupe` int NOT NULL,
  `type_mouvement` enum('entree_don','entree_transfert','sortie_utilisation','sortie_perime','sortie_transfert','sortie','retour') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sortie/retour conservés pour rétrocompatibilité',
  `quantite` decimal(6,2) NOT NULL,
  `date_mouvement` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reference_demande` int DEFAULT NULL COMMENT 'ID de la demande liée (si transfert)',
  `commentaire` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_mouvement`),
  KEY `fk_mvt_sous_banque` (`id_sous_banque`),
  KEY `fk_mvt_banque` (`id_banque`),
  KEY `fk_mvt_hopital` (`id_hopital`),
  KEY `fk_mvt_groupe` (`id_groupe`),
  KEY `idx_mvt_demande` (`reference_demande`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Données de `mouvement_stock`
--

INSERT INTO `mouvement_stock` (`id_mouvement`, `id_sous_banque`, `id_banque`, `id_hopital`, `id_groupe`, `type_mouvement`, `quantite`, `date_mouvement`, `reference_demande`, `commentaire`) VALUES
(1, NULL, 3, 2, 8, 'sortie', 2.00, '2026-05-07', 1, NULL);

-- --------------------------------------------------------

--
-- Table `alerte_stock`
-- Source : gemini-code (nouvelle table)
--

DROP TABLE IF EXISTS `alerte_stock`;
CREATE TABLE IF NOT EXISTS `alerte_stock` (
  `id_alerte` int NOT NULL AUTO_INCREMENT,
  `id_sous_banque` int NOT NULL COMMENT 'Sous-banque concernée',
  `id_groupe` int NOT NULL COMMENT 'Groupe sanguin concerné',
  `quantite_actuelle` int NOT NULL,
  `seuil_alerte` int NOT NULL,
  `type_alerte` enum('avertissement','critique','rupture') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'avertissement',
  `demande_auto` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = demande automatique envoyée',
  `id_demande_auto` int DEFAULT NULL,
  `date_alerte` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `traitee` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_alerte`),
  KEY `idx_alerte_sb` (`id_sous_banque`),
  KEY `idx_alerte_groupe` (`id_groupe`),
  KEY `idx_alerte_date` (`date_alerte`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. DEMANDES ET SUIVI
-- ============================================================

--
-- Table `demande`
-- Source : Fusion v1 + v6 + gemini-code + migration
--          (id_sous_banque, type_demande, statut 'annulée', note, urgence, date_souhaitee)
--

DROP TABLE IF EXISTS `demande`;
CREATE TABLE IF NOT EXISTS `demande` (
  `id_demande` int NOT NULL AUTO_INCREMENT,
  `id_hopital` int NOT NULL,
  `id_sous_banque` int DEFAULT NULL,
  `id_banque` int DEFAULT NULL COMMENT 'NULL si demande interne hôpital → sous-banque',
  `id_groupe` int NOT NULL,
  `quantite_demandee` decimal(6,2) NOT NULL,
  `date_demande` date NOT NULL,
  `date_reponse` date DEFAULT NULL COMMENT 'Date à laquelle la banque a accepté ou refusé',
  `statut` enum('en_attente','acceptée','refusée','annulée') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `type_demande` enum('interne','externe') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'interne',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Commentaire ou justification',
  `urgence` tinyint(1) DEFAULT '0' COMMENT '1 = demande urgente',
  `date_souhaitee` date DEFAULT NULL COMMENT 'Date souhaitée de livraison',
  PRIMARY KEY (`id_demande`),
  KEY `idx_demande_hopital` (`id_hopital`),
  KEY `idx_demande_banque` (`id_banque`),
  KEY `fk_demande_groupe` (`id_groupe`),
  KEY `fk_demande_sous_banque` (`id_sous_banque`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Données de `demande`
--

INSERT INTO `demande` (`id_demande`, `id_hopital`, `id_sous_banque`, `id_banque`, `id_groupe`, `quantite_demandee`, `date_demande`, `date_reponse`, `statut`, `type_demande`, `note`, `urgence`, `date_souhaitee`) VALUES
(1, 2, NULL, 3, 8, 2.00, '2026-05-07', '2026-05-07', 'acceptée', 'interne', NULL, 0, NULL);

-- ============================================================
-- 7. COMMUNICATION ET MESSAGERIE
-- ============================================================

--
-- Table `chat`
-- Source : Toutes les versions (identique)
--

DROP TABLE IF EXISTS `chat`;
CREATE TABLE IF NOT EXISTS `chat` (
  `id_message` int NOT NULL AUTO_INCREMENT,
  `id_donneur` int NOT NULL,
  `id_groupe` int NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_message` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_message`),
  KEY `idx_chat_donneur` (`id_donneur`),
  KEY `idx_chat_groupe` (`id_groupe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table `message_groupe`
-- Source : Version (3) / (sang .sql) / (6)
--

DROP TABLE IF EXISTS `message_groupe`;
CREATE TABLE IF NOT EXISTS `message_groupe` (
  `id_message` int NOT NULL AUTO_INCREMENT,
  `id_groupe` int NOT NULL,
  `id_donneur` int NOT NULL,
  `contenu` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_envoi` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_message`),
  KEY `idx_groupe_date` (`id_groupe`,`date_envoi`),
  KEY `idx_donneur` (`id_donneur`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Données de `message_groupe`
--

INSERT INTO `message_groupe` (`id_message`, `id_groupe`, `id_donneur`, `contenu`, `date_envoi`) VALUES
(1, 8, 1, 'NH KHHVLH', '2026-06-15 11:03:00'),
(2, 8, 1, 'HIIJM', '2026-06-15 13:43:59');

-- ============================================================
-- 8. NOTIFICATIONS ET AUDIT
-- ============================================================

--
-- Table `notification_hopital`
-- Source : migration + gemini-code
--

DROP TABLE IF EXISTS `notification_hopital`;
CREATE TABLE IF NOT EXISTS `notification_hopital` (
  `id_notification` int NOT NULL AUTO_INCREMENT,
  `id_hopital` int NOT NULL,
  `id_responsable` int DEFAULT NULL,
  `titre` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('info','succes','alerte','urgence') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `lu` tinyint(1) DEFAULT '0',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_notification`),
  KEY `fk_notif_hopital` (`id_hopital`),
  KEY `fk_notif_responsable` (`id_responsable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table `journal_hopital`
-- Source : migration + gemini-code (audit log)
--

DROP TABLE IF EXISTS `journal_hopital`;
CREATE TABLE IF NOT EXISTS `journal_hopital` (
  `id_journal` int NOT NULL AUTO_INCREMENT,
  `id_hopital` int NOT NULL,
  `id_responsable` int NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_action` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_journal`),
  KEY `fk_journal_hopital` (`id_hopital`),
  KEY `fk_journal_responsable` (`id_responsable`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table `log_activite`
-- Source : Version (6) — log global toutes entités
--

DROP TABLE IF EXISTS `log_activite`;
CREATE TABLE IF NOT EXISTS `log_activite` (
  `id_log` int NOT NULL AUTO_INCREMENT,
  `role_utilisateur` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'admin, banque, hopital, donneur, sous_banque',
  `id_utilisateur` int NOT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_action` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_log`),
  KEY `idx_log_role` (`role_utilisateur`),
  KEY `idx_log_date` (`date_action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. CONTRAINTES DE CLÉS ÉTRANGÈRES
-- ============================================================

--
-- Contraintes pour `hopital`
--
ALTER TABLE `hopital`
  ADD CONSTRAINT `fk_hopital_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour `responsable_hopital`
--
ALTER TABLE `responsable_hopital`
  ADD CONSTRAINT `fk_responsable_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour `donneur`
--
ALTER TABLE `donneur`
  ADD CONSTRAINT `fk_donneur_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_donneur_groupe_auto` FOREIGN KEY (`groupe_auto_declare`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour `sous_banque`
--
ALTER TABLE `sous_banque`
  ADD CONSTRAINT `fk_sb_banque` FOREIGN KEY (`id_banque_principale`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sb_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour `utilisateur_sous_banque`
--
ALTER TABLE `utilisateur_sous_banque`
  ADD CONSTRAINT `fk_usb_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour `don`
--
ALTER TABLE `don`
  ADD CONSTRAINT `fk_don_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_don_donneur` FOREIGN KEY (`id_donneur`) REFERENCES `donneur` (`id_donneur`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_don_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour `analyse_sang`
--
ALTER TABLE `analyse_sang`
  ADD CONSTRAINT `fk_analyse_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_analyse_don` FOREIGN KEY (`id_don`) REFERENCES `don` (`id_don`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_analyse_groupe` FOREIGN KEY (`groupe_confirme`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour `stock`
--
ALTER TABLE `stock`
  ADD CONSTRAINT `fk_stock_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour `stock_sous_banque`
--
ALTER TABLE `stock_sous_banque`
  ADD CONSTRAINT `fk_ssb_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ssb_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour `mouvement_stock`
--
ALTER TABLE `mouvement_stock`
  ADD CONSTRAINT `fk_mvt_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mvt_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mvt_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mvt_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour `alerte_stock`
--
ALTER TABLE `alerte_stock`
  ADD CONSTRAINT `fk_alerte_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_alerte_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour `demande`
--
ALTER TABLE `demande`
  ADD CONSTRAINT `fk_demande_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_demande_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_demande_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_demande_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour `chat`
--
ALTER TABLE `chat`
  ADD CONSTRAINT `fk_chat_donneur` FOREIGN KEY (`id_donneur`) REFERENCES `donneur` (`id_donneur`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour `message_groupe`
--
ALTER TABLE `message_groupe`
  ADD CONSTRAINT `fk_msg_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_msg_donneur` FOREIGN KEY (`id_donneur`) REFERENCES `donneur` (`id_donneur`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour `notification_hopital`
--
ALTER TABLE `notification_hopital`
  ADD CONSTRAINT `fk_notif_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notif_responsable` FOREIGN KEY (`id_responsable`) REFERENCES `responsable_hopital` (`id_responsable`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour `journal_hopital`
--
ALTER TABLE `journal_hopital`
  ADD CONSTRAINT `fk_journal_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_journal_responsable` FOREIGN KEY (`id_responsable`) REFERENCES `responsable_hopital` (`id_responsable`) ON DELETE CASCADE ON UPDATE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
