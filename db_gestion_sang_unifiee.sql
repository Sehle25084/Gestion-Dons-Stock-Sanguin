-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : jeu. 25 juin 2026 à 15:39
-- Version du serveur : 8.4.7
-- Version de PHP : 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `db_gestion_sang_pi2`
--

-- --------------------------------------------------------

--
-- Structure de la table `administrateur`
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
-- Déchargement des données de la table `administrateur`
--

INSERT INTO `administrateur` (`id_admin`, `nom`, `prenom`, `email`, `mot_de_passe`, `telephone`) VALUES
(1, 'Principal', 'Admin', 'admin@gestion-sang.mr', '$2y$10$fEeDSCp/TkuVYHU7tkreTuUeW8T./c7/cQJx6qCPsorzxahsMVWpe', '00000000');

-- --------------------------------------------------------

--
-- Structure de la table `alerte_stock`
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
-- Structure de la table `analyse_sang`
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
-- Structure de la table `banque_de_sang`
--

DROP TABLE IF EXISTS `banque_de_sang`;
CREATE TABLE IF NOT EXISTS `banque_de_sang` (
  `id_banque` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `wilaya` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_banque`),
  UNIQUE KEY `uq_banque_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `banque_de_sang`
--

INSERT INTO `banque_de_sang` (`id_banque`, `nom`, `wilaya`, `adresse`, `telephone`, `email`, `mot_de_passe`) VALUES
(5, 'CNTS Nouakchott', 'Nouakchott Ouest', 'Avenue Gamal Abdel Nasser', '+22245242180', 'CNTS@banque.mr', '$2y$10$tklLoABLW0RxDRPCtnRzQ.Y95cj67RwXzD5SlOk4pFM5vIP0VnKUm');

-- --------------------------------------------------------

--
-- Structure de la table `chat`
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
-- Structure de la table `demande`
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `don`
--

DROP TABLE IF EXISTS `don`;
CREATE TABLE IF NOT EXISTS `don` (
  `id_don` int NOT NULL AUTO_INCREMENT,
  `id_donneur` int NOT NULL,
  `id_banque` int NOT NULL,
  `date_don` date NOT NULL,
  `id_groupe` int NOT NULL,
  `quantite` decimal(6,2) NOT NULL,
  `statut` enum('en_attente','en_analyse','accepté','refusé') COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  PRIMARY KEY (`id_don`),
  KEY `idx_don_donneur` (`id_donneur`),
  KEY `idx_don_banque` (`id_banque`),
  KEY `fk_don_groupe` (`id_groupe`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `donneur`
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `donneur`
--

INSERT INTO `donneur` (`id_donneur`, `NNI`, `id_groupe`, `email`, `telephone`, `mot_de_passe`, `groupe_auto_declare`, `groupe_confirme`, `disponible`, `identite_verifiee`, `statut_compte`) VALUES
(5, '0000153386', NULL, 'Moussa@gmail.com', '+22242242180', '$2y$10$geeTAJuTvidxxuNRIJALbeJwU7IPomwg4KjxvsvfYRUvciieAFbam', 1, 0, 0, 1, 'actif');

-- --------------------------------------------------------

--
-- Structure de la table `groupe_sanguin`
--

DROP TABLE IF EXISTS `groupe_sanguin`;
CREATE TABLE IF NOT EXISTS `groupe_sanguin` (
  `id_groupe` int NOT NULL AUTO_INCREMENT,
  `libelle` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_groupe`),
  UNIQUE KEY `uq_groupe_libelle` (`libelle`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `groupe_sanguin`
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
-- Structure de la table `historique_sous_banque`
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal centralisé de toutes les actions de la sous-banque';

-- --------------------------------------------------------

--
-- Structure de la table `hopital`
--

DROP TABLE IF EXISTS `hopital`;
CREATE TABLE IF NOT EXISTS `hopital` (
  `id_hopital` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `wilaya` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id_hopital`),
  UNIQUE KEY `uq_hopital_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `hopital`
--

INSERT INTO `hopital` (`id_hopital`, `nom`, `wilaya`, `telephone`, `email`, `mot_de_passe`) VALUES
(3, 'Centre Hopitalier National', 'Nouakchott Ouest', '+22245257261', 'contacts@hopital.mr', '$2y$10$6qJg6c2Df2hucuWKNeQaPenQU/k9j2dyhtFMKgTY4EPFIkhweCeTW'),
(4, 'Centre Hospitalier Mère et Enfant', 'Nouakchott Ouest', '+22222 11 48 53', 'CHME@hopital.mr', '$2y$10$0VZskC2IxgHsenBorsLe6uMLUfnF6SXy4cQbcKhhFhb45CtYW0oC.');

-- --------------------------------------------------------

--
-- Structure de la table `journal_hopital`
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

-- --------------------------------------------------------

--
-- Structure de la table `log_activite`
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `log_activite`
--

INSERT INTO `log_activite` (`id_log`, `role_utilisateur`, `id_utilisateur`, `action`, `date_action`) VALUES
(1, 'admin', 1, 'Ajout sous-banque : Dépôt Cheikh Zayed (+ agent : Khadijetou Beyah)', '2026-06-22 08:27:59'),
(2, 'admin', 1, 'Ajout banque : CNTS Nouakchott (+ agent : Khadijetou Beyah)', '2026-06-23 13:57:18'),
(3, 'banque', 4, 'Ajout du don #1 (4 pochette(s))', '2026-06-23 14:19:06'),
(4, 'banque', 4, 'Mise à jour stock — groupe #8 : 5 pochettes', '2026-06-23 14:22:17'),
(5, 'banque', 4, 'Acceptation du don #1 (4.00 pochette(s) créée(s))', '2026-06-23 15:53:47'),
(6, 'admin', 1, 'Ajout banque : CNTS Nouakchott (+ agent : Massi Ahmednah)', '2026-06-23 19:11:59'),
(7, 'admin', 1, 'Création hôpital sécurisée : Centre Hopitalier National', '2026-06-23 19:18:39'),
(8, 'admin', 1, 'Ajout sous-banque : Dépôt Cheikh Zayed (+ agent : Hayati Sbai)', '2026-06-23 19:21:41'),
(9, 'admin', 1, 'Création hôpital sécurisée : Centre Hospitalier Mère et Enfant', '2026-06-23 19:31:33');

-- --------------------------------------------------------

--
-- Structure de la table `lot_sang_sous_banque`
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Gestion du stock sous-banque par lot, avec suivi de péremption';

-- --------------------------------------------------------

--
-- Structure de la table `message_groupe`
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

-- --------------------------------------------------------

--
-- Structure de la table `mouvement_stock`
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
-- Déchargement des données de la table `mouvement_stock`
--

INSERT INTO `mouvement_stock` (`id_mouvement`, `id_sous_banque`, `id_banque`, `id_hopital`, `id_groupe`, `type_mouvement`, `quantite`, `date_mouvement`, `reference_demande`, `commentaire`) VALUES
(1, NULL, NULL, NULL, 8, 'sortie', 2.00, '2026-05-07 00:00:00', 1, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `notification`
--

DROP TABLE IF EXISTS `notification`;
CREATE TABLE IF NOT EXISTS `notification` (
  `id_notification` int NOT NULL AUTO_INCREMENT,
  `type_destinataire` enum('donneur','banque','sous_banque','hopital','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_destinataire` int NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_notification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lu` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_notification`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notification_hopital`
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
-- Structure de la table `poches_dechets`
--

DROP TABLE IF EXISTS `poches_dechets`;
CREATE TABLE IF NOT EXISTS `poches_dechets` (
  `id_dechet` int NOT NULL AUTO_INCREMENT,
  `id_pochette` int NOT NULL,
  `raison_rejet` varchar(255) NOT NULL,
  `date_rejet` date NOT NULL DEFAULT (curdate()),
  PRIMARY KEY (`id_dechet`),
  KEY `id_pochette` (`id_pochette`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `pochette`
--

DROP TABLE IF EXISTS `pochette`;
CREATE TABLE IF NOT EXISTS `pochette` (
  `id_pochette` int NOT NULL AUTO_INCREMENT,
  `id_don` int NOT NULL,
  `id_groupe` int NOT NULL,
  `id_banque` int NOT NULL,
  `date_collecte` date NOT NULL,
  `date_expiration` date NOT NULL,
  `statut` enum('disponible','utilisee','expiree','detruite') DEFAULT 'disponible',
  `code_pochette` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_pochette`),
  UNIQUE KEY `code_pochette` (`code_pochette`),
  KEY `fk_pochette_don` (`id_don`),
  KEY `fk_pochette_banque` (`id_banque`),
  KEY `fk_pochette_groupe` (`id_groupe`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `responsable_hopital`
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `responsable_hopital`
--

INSERT INTO `responsable_hopital` (`id_responsable`, `id_hopital`, `nom`, `prenom`, `email`, `telephone`, `mot_de_passe`, `poste`, `date_creation`) VALUES
(2, 3, 'Mohamed Salem', 'Ahmed', 'Ahmed@hopital.mr', '+22236704490', '$2y$10$M0TdsE5uDDDybEVef7xF8eCKMiS.qHudY2Vh9OdLT3a19UdNSofcS', 'Directeur', '2026-06-23 19:18:39'),
(3, 4, 'Beyah', 'Khadijetou', 'khadijetou@hopital.mr', '+22241429395', '$2y$10$0k5xTCos5N2gxgXZOsvGl.vaqCDjo4AnwqSjwdNhOm7FEK/8HnuKi', 'Directeur', '2026-06-23 19:31:33');

-- --------------------------------------------------------

--
-- Structure de la table `sous_banque`
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `sous_banque`
--

INSERT INTO `sous_banque` (`id_sous_banque`, `nom`, `id_hopital`, `id_banque_principale`, `date_creation`, `email`, `mot_de_passe`) VALUES
(3, 'Dépôt Cheikh Zayed', 3, 5, '0000-00-00', 'cheikhzayed@sousbanque.mr', '$2y$10$pUte6dtZm9l3bJT3J/LHh.QAqnAmiiVfRGEAmEaPd6Ug2.iROlgcy');

-- --------------------------------------------------------

--
-- Structure de la table `stock`
--

DROP TABLE IF EXISTS `stock`;
CREATE TABLE IF NOT EXISTS `stock` (
  `id_stock` int NOT NULL AUTO_INCREMENT,
  `id_banque` int NOT NULL,
  `id_groupe` int NOT NULL,
  `quantite_disponible` decimal(6,2) NOT NULL DEFAULT '0.00',
  `date_mise_a_jour` date NOT NULL,
  `date_expiration` date NOT NULL COMMENT 'Date de péremption du stock sanguin',
  `seuil_alerte` decimal(6,2) NOT NULL DEFAULT '2.00' COMMENT 'Seuil minimal avant alerte',
  PRIMARY KEY (`id_stock`),
  KEY `idx_stock_banque` (`id_banque`),
  KEY `fk_stock_groupe` (`id_groupe`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `stock_sous_banque`
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
-- Structure de la table `utilisateur_banque`
--

DROP TABLE IF EXISTS `utilisateur_banque`;
CREATE TABLE IF NOT EXISTS `utilisateur_banque` (
  `id_utilisateur` int NOT NULL AUTO_INCREMENT,
  `id_banque` int NOT NULL,
  `nom_complet` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` enum('actif','inactif') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'actif',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_utilisateur`),
  UNIQUE KEY `uq_ub_email` (`email`),
  KEY `fk_ub_banque` (`id_banque`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateur_banque`
--

INSERT INTO `utilisateur_banque` (`id_utilisateur`, `id_banque`, `nom_complet`, `email`, `mot_de_passe`, `telephone`, `statut`, `date_creation`) VALUES
(2, 5, 'Massi Ahmednah', 'Massi@banque.mr', '$2y$10$JEJsH8KG8kaZEr8eOR//WeIgtH9.qkrpfpLH4I7BdWiSg4KhGNBbm', '+22243245695', 'actif', '2026-06-23 19:11:59');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur_sous_banque`
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateur_sous_banque`
--

INSERT INTO `utilisateur_sous_banque` (`id_utilisateur`, `id_sous_banque`, `nom_complet`, `login`, `mot_de_passe`, `email`, `statut`) VALUES
(2, 3, 'Hayati Sbai', 'Hayati@sousbanque.mr', '$2y$10$PvohqOMLQvFxFkbBeuDh8ul8w2Cy82pthoeObogIfeFt2RadxiXFO', 'Hayati@sousbanque.mr', 'actif');

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `alerte_stock`
--
ALTER TABLE `alerte_stock`
  ADD CONSTRAINT `fk_alerte_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_alerte_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `analyse_sang`
--
ALTER TABLE `analyse_sang`
  ADD CONSTRAINT `fk_analyse_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_analyse_don` FOREIGN KEY (`id_don`) REFERENCES `don` (`id_don`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_analyse_groupe` FOREIGN KEY (`groupe_confirme`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `chat`
--
ALTER TABLE `chat`
  ADD CONSTRAINT `fk_chat_donneur` FOREIGN KEY (`id_donneur`) REFERENCES `donneur` (`id_donneur`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `demande`
--
ALTER TABLE `demande`
  ADD CONSTRAINT `fk_demande_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_demande_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_demande_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_demande_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `don`
--
ALTER TABLE `don`
  ADD CONSTRAINT `fk_don_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_don_donneur` FOREIGN KEY (`id_donneur`) REFERENCES `donneur` (`id_donneur`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_don_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `donneur`
--
ALTER TABLE `donneur`
  ADD CONSTRAINT `fk_donneur_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_donneur_groupe_auto` FOREIGN KEY (`groupe_auto_declare`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `historique_sous_banque`
--
ALTER TABLE `historique_sous_banque`
  ADD CONSTRAINT `fk_hist_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hist_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `journal_hopital`
--
ALTER TABLE `journal_hopital`
  ADD CONSTRAINT `fk_journal_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_journal_responsable` FOREIGN KEY (`id_responsable`) REFERENCES `responsable_hopital` (`id_responsable`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `lot_sang_sous_banque`
--
ALTER TABLE `lot_sang_sous_banque`
  ADD CONSTRAINT `fk_lot_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lot_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `message_groupe`
--
ALTER TABLE `message_groupe`
  ADD CONSTRAINT `fk_msg_donneur` FOREIGN KEY (`id_donneur`) REFERENCES `donneur` (`id_donneur`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_msg_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `mouvement_stock`
--
ALTER TABLE `mouvement_stock`
  ADD CONSTRAINT `fk_mvt_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mvt_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mvt_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mvt_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `notification_hopital`
--
ALTER TABLE `notification_hopital`
  ADD CONSTRAINT `fk_notif_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notif_responsable` FOREIGN KEY (`id_responsable`) REFERENCES `responsable_hopital` (`id_responsable`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `poches_dechets`
--
ALTER TABLE `poches_dechets`
  ADD CONSTRAINT `poches_dechets_ibfk_1` FOREIGN KEY (`id_pochette`) REFERENCES `pochette` (`id_pochette`);

--
-- Contraintes pour la table `pochette`
--
ALTER TABLE `pochette`
  ADD CONSTRAINT `fk_pochette_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pochette_don` FOREIGN KEY (`id_don`) REFERENCES `don` (`id_don`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pochette_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `pochette_ibfk_1` FOREIGN KEY (`id_don`) REFERENCES `don` (`id_don`),
  ADD CONSTRAINT `pochette_ibfk_2` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`),
  ADD CONSTRAINT `pochette_ibfk_3` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`);

--
-- Contraintes pour la table `responsable_hopital`
--
ALTER TABLE `responsable_hopital`
  ADD CONSTRAINT `fk_responsable_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `sous_banque`
--
ALTER TABLE `sous_banque`
  ADD CONSTRAINT `fk_sb_banque` FOREIGN KEY (`id_banque_principale`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sb_hopital` FOREIGN KEY (`id_hopital`) REFERENCES `hopital` (`id_hopital`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `stock`
--
ALTER TABLE `stock`
  ADD CONSTRAINT `fk_stock_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_stock_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Contraintes pour la table `stock_sous_banque`
--
ALTER TABLE `stock_sous_banque`
  ADD CONSTRAINT `fk_ssb_groupe` FOREIGN KEY (`id_groupe`) REFERENCES `groupe_sanguin` (`id_groupe`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ssb_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `utilisateur_banque`
--
ALTER TABLE `utilisateur_banque`
  ADD CONSTRAINT `fk_ub_banque` FOREIGN KEY (`id_banque`) REFERENCES `banque_de_sang` (`id_banque`) ON DELETE CASCADE;

--
-- Contraintes pour la table `utilisateur_sous_banque`
--
ALTER TABLE `utilisateur_sous_banque`
  ADD CONSTRAINT `fk_usb_sous_banque` FOREIGN KEY (`id_sous_banque`) REFERENCES `sous_banque` (`id_sous_banque`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
