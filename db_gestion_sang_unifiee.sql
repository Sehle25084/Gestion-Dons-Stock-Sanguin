-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 20, 2026 at 08:41 PM
-- Server version: 8.4.7
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_gestion_sang_pi2`
--

-- --------------------------------------------------------

--
-- Table structure for table `administrateur`
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
-- Dumping data for table `administrateur`
--

INSERT INTO `administrateur` (`id_admin`, `nom`, `prenom`, `email`, `mot_de_passe`, `telephone`) VALUES
(1, 'Principal', 'Admin', 'admin@gestion-sang.mr', '$2y$10$fEeDSCp/TkuVYHU7tkreTuUeW8T./c7/cQJx6qCPsorzxahsMVWpe', '00000000');

-- --------------------------------------------------------

--
-- Table structure for table `alerte_stock`
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

-- --------------------------------------------------------

--
-- Table structure for table `analyse_sang`
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

-- --------------------------------------------------------

--
-- Table structure for table `banque_de_sang`
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
-- Dumping data for table `banque_de_sang`
--

INSERT INTO `banque_de_sang` (`id_banque`, `nom`, `wilaya`, `telephone`, `email`, `mot_de_passe`) VALUES
(3, 'test', 'test', '00000000', 'test@test.mr', '$2y$10$jdjwEiSQnp0.AfYwABnDc.OQio/XmjBQ4.zK5N20hF4H518FzInIC');

-- --------------------------------------------------------

--
-- Table structure for table `chat`
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
-- Table structure for table `demande`
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `demande`
--

INSERT INTO `demande` (`id_demande`, `id_hopital`, `id_sous_banque`, `id_banque`, `id_groupe`, `quantite_demandee`, `date_demande`, `date_reponse`, `statut`, `type_demande`, `note`, `urgence`, `date_souhaitee`) VALUES
(1, 2, NULL, 3, 8, 2.00, '2026-05-07', '2026-05-07', 'acceptée', 'interne', NULL, 0, NULL),
(2, 3, 2, NULL, 5, 1.00, '2026-06-19', '2026-06-19', 'refusée', 'interne', ' | Refus automatique : stock insuffisant (0 disponible(s))', 0, NULL),
(3, 3, 2, 3, 5, 6.00, '2026-06-19', NULL, 'en_attente', 'externe', 'Demande automatique — stock insuffisant pour répondre à l\'hôpital', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `don`
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
-- Table structure for table `donneur`
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `donneur`
--

INSERT INTO `donneur` (`id_donneur`, `NNI`, `id_groupe`, `email`, `telephone`, `mot_de_passe`, `groupe_auto_declare`, `groupe_confirme`, `disponible`, `identite_verifiee`, `statut_compte`) VALUES
(1, '0123456789', 8, 'zemraguisehle@gmail.com', '36050593', '$2y$10$VXl0GItJ4ntnZBkO13TMyuob01PyIzzXqS3iXXOvqeQEDBdb5Sudq', NULL, 0, 0, 0, 'actif'),
(2, '1111111111', NULL, 'ahmed@gmail.com', '00000000', '$2y$10$xkIrREnCsNvhSF3Mg1cRqOuCti0SC92NSD9fkZyJUiKjew1VxseQe', NULL, 0, 0, 0, 'actif'),
(3, '3333333333', NULL, 'meymohamed@gmail.com', '43326369', '$2y$10$gDMMaDk/z8nbucuKSkZjd.CdvhUOv/jjzYv40QduGInXalxr9v80S', NULL, 0, 0, 0, 'actif'),
(4, '2222222222', NULL, 'FatimaMintAli@gmail.com', '22222222', '$2y$10$NJXHVwvTsK9Z6Hzxq4bJaeermttzgQVrNzRt76YN6yCSev3ZN0LVS', NULL, 0, 0, 0, 'actif'),
(5, '4444444444', NULL, '25084@supnum.mr', '00000000', '$2y$10$4/ALds/Y0x14yx5I53npnOvOsxQgEMoWt0UfBZmoAtnBfqeUESB3a', NULL, 0, 0, 0, 'actif'),
(6, '12341234', NULL, 'mariemahmd@gmai.com', '36315677', '$2y$10$u7GYinG7E9hmgyDOUgqfUebG3XGynJ8YXpeKm5B5PNumKr9/toyCW', NULL, 0, 0, 0, 'actif'),
(7, '5555555555', 8, 'aicha@donneur.com', '21346678', '$2y$10$6M4FCwpeyPc1K8Qbt1C2KufRjRiLSxFSi6FGpodD/uajtxOwMFLy.', 8, 1, 0, 0, 'actif');

-- --------------------------------------------------------

--
-- Table structure for table `groupe_sanguin`
--

DROP TABLE IF EXISTS `groupe_sanguin`;
CREATE TABLE IF NOT EXISTS `groupe_sanguin` (
  `id_groupe` int NOT NULL AUTO_INCREMENT,
  `libelle` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_groupe`),
  UNIQUE KEY `uq_groupe_libelle` (`libelle`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `groupe_sanguin`
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

-- --------------------------------------------------------

--
-- Table structure for table `historique_sous_banque`
--

DROP TABLE IF EXISTS `historique_sous_banque`;
CREATE TABLE IF NOT EXISTS `historique_sous_banque` (
  `id_historique` int NOT NULL AUTO_INCREMENT,
  `id_sous_banque` int NOT NULL,
  `id_groupe` int DEFAULT NULL COMMENT 'Groupe sanguin concerné, si applicable',
  `type_action` enum('entree_stock','sortie_stock','lot_expire','seuil_modifie','alerte_declenchee','alerte_traitee','demande_envoyee','demande_recue_traitee') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantite` int DEFAULT NULL COMMENT 'Quantité de pochettes concernée, si applicable',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Résumé lisible de l''action',
  `id_utilisateur` int DEFAULT NULL COMMENT 'Agent ayant effectué l''action manuelle, si applicable',
  `date_action` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_historique`),
  KEY `idx_hist_sb` (`id_sous_banque`),
  KEY `idx_hist_groupe` (`id_groupe`),
  KEY `idx_hist_type` (`type_action`),
  KEY `idx_hist_date` (`date_action`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal centralisé de toutes les actions de la sous-banque';

--
-- Dumping data for table `historique_sous_banque`
--

INSERT INTO `historique_sous_banque` (`id_historique`, `id_sous_banque`, `id_groupe`, `type_action`, `quantite`, `description`, `id_utilisateur`, `date_action`) VALUES
(1, 2, 1, 'entree_stock', 6, 'Réception de 6 pochettes A+ (2 lots) depuis la banque principale', NULL, '2026-06-19 23:36:39'),
(2, 2, 8, 'entree_stock', 3, 'Réception de 3 pochettes O- depuis la banque principale', NULL, '2026-06-19 23:36:39'),
(3, 2, 3, 'entree_stock', 5, 'Réception de 5 pochettes B+ depuis la banque principale', NULL, '2026-06-19 23:36:39'),
(4, 2, 5, 'demande_recue_traitee', 1, 'Demande hôpital refusée : stock insuffisant pour 1 pochette(s) AB+ (0 disponible(s))', NULL, '2026-06-19 23:54:06'),
(5, 2, 5, 'demande_envoyee', 6, 'Demande automatique envoyée à la banque principale : 6 pochette(s) AB+', NULL, '2026-06-19 23:54:06');

-- --------------------------------------------------------

--
-- Table structure for table `hopital`
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hopital`
--

INSERT INTO `hopital` (`id_hopital`, `nom`, `wilaya`, `telephone`, `email`, `mot_de_passe`, `id_banque`) VALUES
(1, 'chiffa', 'tvz', '33333333', 'opital@exemple.mr', '$2y$10$4DeB3OyhvKCPE0EH72.IQujEaj82Z0t85xZnaq43nI9hK3KP8pVbO', NULL),
(2, 'A', 'A', '00000000', 'hopit@exemple.mr', '$2y$10$olkHOIaXTf5VBntFj4SZg.f2Mj3ZKp0ytCYY5hw03zwExqcDJuSmy', NULL),
(3, 'ihssan', 'nktt', '36050593', 'ihssan@exemple.mr', '$2y$10$C6s/f3PLVW0gxjbmg2lN0e0QD.TNukdEPWb9csMzr7T8woM3RrCMe', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `journal_hopital`
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `journal_hopital`
--

INSERT INTO `journal_hopital` (`id_journal`, `id_hopital`, `id_responsable`, `action`, `details`, `date_action`) VALUES
(1, 3, 1, 'demande_envoyee', 'Demande de 1 pochettes de AB+', '2026-06-19 23:32:34');

-- --------------------------------------------------------

--
-- Table structure for table `log_activite`
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `log_activite`
--

INSERT INTO `log_activite` (`id_log`, `role_utilisateur`, `id_utilisateur`, `action`, `date_action`) VALUES
(1, 'admin', 1, 'Création hôpital : ihssan', '2026-06-19 23:30:31'),
(2, 'admin', 1, 'Création sous-banque : sbanque', '2026-06-19 23:31:21');

-- --------------------------------------------------------

--
-- Table structure for table `lot_sang_sous_banque`
--

DROP TABLE IF EXISTS `lot_sang_sous_banque`;
CREATE TABLE IF NOT EXISTS `lot_sang_sous_banque` (
  `id_lot` int NOT NULL AUTO_INCREMENT,
  `id_sous_banque` int NOT NULL,
  `id_groupe` int NOT NULL,
  `quantite` int NOT NULL DEFAULT '0' COMMENT 'Nombre de pochettes restantes dans ce lot',
  `quantite_initiale` int NOT NULL DEFAULT '0' COMMENT 'Nombre de pochettes reçues a l''origine pour ce lot',
  `date_entree` date NOT NULL COMMENT 'Date de réception du lot dans le dépôt',
  `date_expiration` date NOT NULL COMMENT 'Date de péremption du lot',
  `origine` enum('banque_principale','ajustement_manuel','autre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'banque_principale',
  `id_demande_origine` int DEFAULT NULL COMMENT 'Demande à la banque principale ayant généré ce lot, si applicable',
  `statut` enum('disponible','epuise','expire') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disponible',
  PRIMARY KEY (`id_lot`),
  KEY `idx_lot_sb` (`id_sous_banque`),
  KEY `idx_lot_groupe` (`id_groupe`),
  KEY `idx_lot_expiration` (`date_expiration`),
  KEY `idx_lot_statut` (`statut`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Gestion du stock sous-banque par lot, avec suivi de péremption';

--
-- Dumping data for table `lot_sang_sous_banque`
--

INSERT INTO `lot_sang_sous_banque` (`id_lot`, `id_sous_banque`, `id_groupe`, `quantite`, `quantite_initiale`, `date_entree`, `date_expiration`, `origine`, `id_demande_origine`, `statut`) VALUES
(1, 2, 1, 4, 4, '2026-06-19', '2026-07-24', 'banque_principale', NULL, 'disponible'),
(2, 2, 1, 2, 2, '2026-05-20', '2026-06-23', 'banque_principale', NULL, 'disponible'),
(3, 2, 8, 3, 3, '2026-05-16', '2026-06-20', 'banque_principale', NULL, 'disponible'),
(4, 2, 3, 5, 5, '2026-06-19', '2026-07-29', 'banque_principale', NULL, 'disponible');

-- --------------------------------------------------------

--
-- Table structure for table `message_groupe`
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
-- Dumping data for table `message_groupe`
--

INSERT INTO `message_groupe` (`id_message`, `id_groupe`, `id_donneur`, `contenu`, `date_envoi`) VALUES
(1, 8, 1, 'NH KHHVLH', '2026-06-15 11:03:00'),
(2, 8, 1, 'HIIJM', '2026-06-15 13:43:59');

-- --------------------------------------------------------

--
-- Table structure for table `mouvement_stock`
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
-- Dumping data for table `mouvement_stock`
--

INSERT INTO `mouvement_stock` (`id_mouvement`, `id_sous_banque`, `id_banque`, `id_hopital`, `id_groupe`, `type_mouvement`, `quantite`, `date_mouvement`, `reference_demande`, `commentaire`) VALUES
(1, NULL, 3, 2, 8, 'sortie', 2.00, '2026-05-07 00:00:00', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notification_hopital`
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
-- Table structure for table `responsable_hopital`
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `responsable_hopital`
--

INSERT INTO `responsable_hopital` (`id_responsable`, `id_hopital`, `nom`, `prenom`, `email`, `telephone`, `mot_de_passe`, `poste`, `date_creation`) VALUES
(1, 3, 'Général', 'Responsable', 'ihssan@exemple.mr', '36050593', '$2y$10$C6s/f3PLVW0gxjbmg2lN0e0QD.TNukdEPWb9csMzr7T8woM3RrCMe', 'Direction', '2026-06-19 23:32:19');

-- --------------------------------------------------------

--
-- Table structure for table `sous_banque`
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sous_banque`
--

INSERT INTO `sous_banque` (`id_sous_banque`, `nom`, `id_hopital`, `id_banque_principale`, `date_creation`, `email`, `mot_de_passe`) VALUES
(1, 'Ma Nouvelle Sous-Banque', 1, 3, '2026-06-15', 'test@sousbanque.com', '$2y$10$Mv9uAGvn1YyrKX9Av55xfu4jxnlA4nS9AJfCVWKkPas8Eki5y.A.i'),
(2, 'sbanque', 3, 3, '2026-06-19', 'sbanque@exemple.com', '$2y$10$TF6Iz7rNEeK0ZlL4rdvPluSTPazG3roosw7woubNaLiEXz1o9771G');

-- --------------------------------------------------------

--
-- Table structure for table `stock`
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
-- Table structure for table `stock_sous_banque`
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_sous_banque`
--

INSERT INTO `stock_sous_banque` (`id_stock_sb`, `id_sous_banque`, `id_groupe`, `quantite_disponible`, `seuil_alerte`, `date_mise_a_jour`) VALUES
(1, 2, 1, 6, 3, '2026-06-19 00:00:00'),
(2, 2, 8, 3, 3, '2026-06-19 00:00:00'),
(3, 2, 3, 5, 3, '2026-06-19 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `utilisateur_sous_banque`
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

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alerte_stock`
--
ALTER TABLE `alerte_stock`
  ADD CONSTRAINT `fk_alerte_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_alerte_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `analyse_sang`
--
ALTER TABLE `analyse_sang`
  ADD CONSTRAINT `fk_analyse_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_analyse_don` FOREIGN KEY (`id_don`) REFERENCES `don` (`id_don`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_analyse_groupe` FOREIGN KEY (`groupe_confirme`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `chat`
--
ALTER TABLE `chat`
  ADD CONSTRAINT `fk_chat_donneur` FOREIGN KEY (`id_donneur`) REFERENCES `donneur` (`id_donneur`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `demande`
--
ALTER TABLE `demande`
  ADD CONSTRAINT `fk_demande_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_demande_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_demande_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_demande_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `don`
--
ALTER TABLE `don`
  ADD CONSTRAINT `fk_don_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_don_donneur` FOREIGN KEY (`id_donneur`) REFERENCES `donneur` (`id_donneur`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_don_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `donneur`
--
ALTER TABLE `donneur`
  ADD CONSTRAINT `fk_donneur_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_donneur_groupe_auto` FOREIGN KEY (`groupe_auto_declare`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `historique_sous_banque`
--
ALTER TABLE `historique_sous_banque`
  ADD CONSTRAINT `fk_hist_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hist_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `hopital`
--
ALTER TABLE `hopital`
  ADD CONSTRAINT `fk_hopital_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `journal_hopital`
--
ALTER TABLE `journal_hopital`
  ADD CONSTRAINT `fk_journal_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_journal_responsable` FOREIGN KEY (`id_responsable`) REFERENCES `responsable_hopital` (`id_responsable`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `lot_sang_sous_banque`
--
ALTER TABLE `lot_sang_sous_banque`
  ADD CONSTRAINT `fk_lot_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lot_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `message_groupe`
--
ALTER TABLE `message_groupe`
  ADD CONSTRAINT `fk_msg_donneur` FOREIGN KEY (`id_donneur`) REFERENCES `donneur` (`id_donneur`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_msg_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `mouvement_stock`
--
ALTER TABLE `mouvement_stock`
  ADD CONSTRAINT `fk_mvt_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mvt_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mvt_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mvt_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `notification_hopital`
--
ALTER TABLE `notification_hopital`
  ADD CONSTRAINT `fk_notif_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notif_responsable` FOREIGN KEY (`id_responsable`) REFERENCES `responsable_hopital` (`id_responsable`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `responsable_hopital`
--
ALTER TABLE `responsable_hopital`
  ADD CONSTRAINT `fk_responsable_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sous_banque`
--
ALTER TABLE `sous_banque`
  ADD CONSTRAINT `fk_sb_banque` FOREIGN KEY (`id_banque_principale`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sb_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `stock`
--
ALTER TABLE `stock`
  ADD CONSTRAINT `fk_stock_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `stock_sous_banque`
--
ALTER TABLE `stock_sous_banque`
  ADD CONSTRAINT `fk_ssb_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ssb_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `utilisateur_sous_banque`
--
ALTER TABLE `utilisateur_sous_banque`
  ADD CONSTRAINT `fk_usb_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
