<?php
/**
 * config/Config.php - CONFIGURATION CENTRALISÉE DE L'API
 *
 * Ce fichier contient tous les paramètres sensibles et configurations de l'application.
 * En production, certains de ces paramètres devraient être lus depuis des variables d'environnement
 * pour éviter de les exposer dans le code source.
 *
 * Intérêt de centraliser : si la base change de nom ou de serveur, on modifie UNE seule ligne ici,
 * au lieu de chercher dans tous les fichiers du projet.
 *
 * define('NOM', valeur) crée une CONSTANTE :
 * - elle est accessible partout (dans toutes les classes et fonctions), sans "global" ni "$" ;
 * - sa valeur ne peut plus être modifiée une fois définie ;
 * - par convention, son nom s'écrit EN_MAJUSCULES.
 */

// ===== PARAMÈTRES DE CONNEXION À LA BASE DE DONNÉES =====
// Ces paramètres permettent à PDO de se connecter au serveur MySQL.
// Ils sont utilisés dans DAO::getPdo() pour construire le DSN :
//   'mysql:host=localhost;dbname=gestiticket;charset=utf8mb4'
define('DB_HOST', 'localhost');  // Adresse du serveur MySQL (localhost = même machine)
define('DB_NAME', 'gestiticket'); // Nom de la base de données (créée avec gestiticket.sql)
define('DB_USER', 'root');        // Utilisateur MySQL (par défaut 'root' en développement)
define('DB_PASS', '');            // Mot de passe MySQL (vide par défaut en développement)
// ⚠️ En production : jamais "root" sans mot de passe ! On crée un utilisateur MySQL dédié
// qui n'a que les droits nécessaires (SELECT, INSERT, UPDATE, DELETE sur cette seule base).
// C'est le "principe du moindre privilège".

// ===== PARAMÈTRES DE SÉCURITÉ JWT =====
// Les jetons JWT sont utilisés pour authentifier les utilisateurs sans stocker de session serveur.

// JWT_SECRET : clé secrète servant à signer les jetons JWT avec l'algorithme HS256.
// ⚠️ TRÈS IMPORTANT :
// - Cette clé doit être longue et aléatoire (au minimum 32 caractères).
// - Elle doit être conservée confidentielle (jamais dans un dépôt Git public).
// - Si elle est compromise, tous les jetons peuvent être falsifiés.
//   (un attaquant pourrait fabriquer lui-même un jeton avec "role": "admin" !)
// En production, utilisez des variables d'environnement système, lues avec getenv('JWT_SECRET').
// Exemple pour générer une bonne clé en PHP : bin2hex(random_bytes(32))  → 64 caractères hexadécimaux.
define('JWT_SECRET', 'cle-secrete-gestiticket-a-changer');

// JWT_DUREE : durée de validité d'un jeton en secondes.
// 3600 secondes = 1 heure. Après ce délai, l'utilisateur doit se reconnecter.
// Compromis sécurité / confort : une durée courte limite les dégâts si un jeton est volé,
// mais oblige l'utilisateur à se reconnecter plus souvent.
define('JWT_DUREE', 3600);
