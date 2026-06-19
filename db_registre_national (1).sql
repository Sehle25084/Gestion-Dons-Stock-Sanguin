-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : jeu. 18 juin 2026 à 10:26
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
-- Base de données : `db_registre_national`
--

-- --------------------------------------------------------

--
-- Structure de la table `citoyen`
--

DROP TABLE IF EXISTS `citoyen`;
CREATE TABLE IF NOT EXISTS `citoyen` (
  `NNI` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_naissance` date NOT NULL,
  `sexe` enum('M','F') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `wilaya` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`NNI`),
  KEY `idx_citoyen_wilaya` (`wilaya`),
  KEY `idx_citoyen_date_naissance` (`date_naissance`) COMMENT 'Utile pour filtrer les donneurs éligibles par âge'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `citoyen`
--

INSERT INTO `citoyen` (`NNI`, `nom`, `prenom`, `date_naissance`, `sexe`, `wilaya`) VALUES
('0123456789', 'zemragui', 'sehle ', '2009-04-02', 'F', 'Nouakchott'),
('1111111111', 'Ould Mohamed', 'Ahmed', '1990-05-15', 'M', 'Nouakchott'),
('2222222222', 'Mint Ali', 'Fatima', '1995-08-20', 'F', 'Nouakchott'),
('3333333333', 'mey', 'Mohamed', '2000-01-15', 'F', 'Nouakchott');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
