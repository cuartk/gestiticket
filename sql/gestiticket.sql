-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mar. 15 sep. 2026 à 15:17
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
-- Base de données : `gestiticket`
--

-- --------------------------------------------------------

--
-- Structure de la table `categorie`
--

DROP TABLE IF EXISTS `categorie`;
CREATE TABLE IF NOT EXISTS `categorie` (
  `id` int NOT NULL AUTO_INCREMENT,
  `libelle` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `niveau_urgence` tinyint NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `libelle` (`libelle`)
) ;

--
-- Déchargement des données de la table `categorie`
--

INSERT INTO `categorie` (`id`, `libelle`, `niveau_urgence`) VALUES
(1, 'Réseau', 4),
(2, 'Matériel', 3),
(3, 'Logiciel', 2),
(4, 'Compte et accès', 2),
(5, 'Autre', 1);

-- --------------------------------------------------------

--
-- Structure de la table `intervention`
--

DROP TABLE IF EXISTS `intervention`;
CREATE TABLE IF NOT EXISTS `intervention` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contenu` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_intervention` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cloture_ticket` tinyint(1) NOT NULL DEFAULT '0',
  `id_ticket` int NOT NULL,
  `id_utilisateur` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_intervention_ticket` (`id_ticket`),
  KEY `fk_intervention_utilisateur` (`id_utilisateur`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `intervention`
--

INSERT INTO `intervention` (`id`, `contenu`, `date_intervention`, `cloture_ticket`, `id_ticket`, `id_utilisateur`) VALUES
(1, 'Switch de la salle B12 redemarre, tests en cours.', '2026-06-01 10:00:00', 0, 1, 1),
(2, 'Reinstallation de VS Code effectuee, le logiciel se lance correctement.', '2026-06-02 11:00:00', 1, 3, 1),
(3, 'Mot de passe reinitialise, nouvelle connexion testee avec l\'utilisateur.', '2026-06-02 11:45:00', 1, 4, 1),
(4, 'Le probleme persiste apres redemarrage du switch.', '2026-06-01 11:30:00', 0, 1, 2),
(5, 'Pilote de l\'imprimante mis a jour sur le serveur d\'impression.', '2026-06-03 10:15:00', 0, 5, 1);

-- --------------------------------------------------------

--
-- Structure de la table `ticket`
--

DROP TABLE IF EXISTS `ticket`;
CREATE TABLE IF NOT EXISTS `ticket` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('nouveau','en_cours','resolu') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nouveau',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_categorie` int NOT NULL,
  `id_auteur` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ticket_categorie` (`id_categorie`),
  KEY `fk_ticket_auteur` (`id_auteur`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ticket`
--

INSERT INTO `ticket` (`id`, `titre`, `description`, `statut`, `date_creation`, `id_categorie`, `id_auteur`) VALUES
(1, 'Pas d\'accès Internet en salle B12', 'Aucun poste de la salle B12 ne peut accéder à Internet depuis ce matin.', 'en_cours', '2026-06-01 08:15:00', 1, 2),
(2, 'Écran qui clignote poste 7', 'L\'écran du poste 7 de la salle B10 clignote puis devient noir.', 'nouveau', '2026-06-01 09:30:00', 2, 3),
(3, 'VS Code ne se lance plus', 'Message d\'erreur au démarrage de VS Code sur le poste 3 de la salle B11.', 'resolu', '2026-06-02 10:05:00', 3, 4),
(4, 'Mot de passe session oublié', 'Impossible d\'ouvrir ma session, mot de passe refusé.', 'resolu', '2026-06-02 11:20:00', 4, 5),
(5, 'Imprimante du CDI hors ligne', 'L\'imprimante réseau du CDI apparaît hors ligne sur tous les postes.', 'en_cours', '2026-06-03 08:45:00', 2, 2),
(6, 'Clavier défectueux poste 12', 'Plusieurs touches du clavier ne répondent plus (salle B12, poste 12).', 'nouveau', '2026-06-03 14:10:00', 2, 3),
(7, 'Accès refusé au dossier partagé', 'Je n\'ai plus accès au dossier partagé de la classe depuis hier.', 'nouveau', '2026-06-04 09:00:00', 4, 4),
(8, 'Demande d\'installation de Figma', 'Serait-il possible d\'installer Figma sur les postes de la salle B10 ?', 'nouveau', '2026-06-04 16:30:00', 5, 5);

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

DROP TABLE IF EXISTS `utilisateur`;
CREATE TABLE IF NOT EXISTS `utilisateur` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','utilisateur') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'utilisateur',
  `date_inscription` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id`, `nom`, `prenom`, `email`, `mot_de_passe`, `role`, `date_inscription`) VALUES
(1, 'DUPONT', 'Claire', 'claire.dupont@gestiticket.fr', '$2y$10$Hq0XhsiBoQwVtPxRkW0kQeR4fJ9pZk0uJ3kqA7w1mB5cD2eF6gH8i', 'admin', '2026-09-15 17:17:26'),
(2, 'MARTIN', 'Lucas', 'lucas.martin@gestiticket.fr', '$2y$10$Hq0XhsiBoQwVtPxRkW0kQeR4fJ9pZk0uJ3kqA7w1mB5cD2eF6gH8i', 'utilisateur', '2026-09-15 17:17:26'),
(3, 'BERNARD', 'Emma', 'emma.bernard@gestiticket.fr', '$2y$10$Hq0XhsiBoQwVtPxRkW0kQeR4fJ9pZk0uJ3kqA7w1mB5cD2eF6gH8i', 'utilisateur', '2026-09-15 17:17:26'),
(4, 'PETIT', 'Nathan', 'nathan.petit@gestiticket.fr', '$2y$10$Hq0XhsiBoQwVtPxRkW0kQeR4fJ9pZk0uJ3kqA7w1mB5cD2eF6gH8i', 'utilisateur', '2026-09-15 17:17:26'),
(5, 'RICHARD', 'Lea', 'lea.richard@gestiticket.fr', '$2y$10$Hq0XhsiBoQwVtPxRkW0kQeR4fJ9pZk0uJ3kqA7w1mB5cD2eF6gH8i', 'utilisateur', '2026-09-15 17:17:26');

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `intervention`
--
ALTER TABLE `intervention`
  ADD CONSTRAINT `fk_intervention_ticket` FOREIGN KEY (`id_ticket`) REFERENCES `ticket` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_intervention_utilisateur` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `ticket`
--
ALTER TABLE `ticket`
  ADD CONSTRAINT `fk_ticket_auteur` FOREIGN KEY (`id_auteur`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ticket_categorie` FOREIGN KEY (`id_categorie`) REFERENCES `categorie` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
