<?php
/**
 * controleur/CategorieControleur.php - GESTION DES CATÉGORIES DE TICKETS
 *
 * Les catégories permettent de classer les tickets.
 * Catégories présentes dans la base de départ (gestiticket.sql) :
 *   Reseau (urgence 4), Materiel (3), Logiciel (2), Compte et acces (2), Autre (1)
 * Seuls les administrateurs peuvent créer des catégories.
 *
 * Rôle d'un contrôleur dans le MVC :
 *   1. vérifier les DROITS de l'appelant          (via Auth)
 *   2. lire et VALIDER les données reçues          (règles métier)
 *   3. appeler le MODÈLE pour lire/écrire en base  (via DAO)
 *   4. renvoyer la RÉPONSE adaptée                 (via Reponse)
 */
class CategorieControleur
{
    /**
     * Récupère la liste de toutes les catégories de tickets.
     * Route publique : pas d'authentification requise.
     * (Utile par exemple pour remplir une liste déroulante dans le formulaire de création de ticket.)
     */
    public static function lister(): void
    {
        // Appelons directement la DAO et retournons le résultat
        // Aucune validation ni contrôle d'accès nécessaire : c'est le cas le plus simple.
        Reponse::envoyer(200, DAO::getLesCategories());
    }

    /**
     * Crée une nouvelle catégorie de ticket.
     * Route protégée : seul un admin peut le faire.
     *
     * Corps JSON attendu : { "libelle": "Imprimante", "niveau_urgence": 3 }
     */
    public static function ajouter(): void
    {
        // Vérification d'autorisation : stoppe la requête si l'utilisateur n'est pas admin
        // (401 si non connecté, 403 si connecté sans être admin)
        // On ne récupère pas la valeur de retour : ici, seul le contrôle nous intéresse.
        Auth::exigerAdmin();

        // Récupère les données du corps de la requête (JSON)
        $donnees = json_decode(file_get_contents('php://input'), true);

        /**
         * VALIDATION DES PARAMÈTRES
         * Règle d'or : toujours valider côté serveur, même si le client valide déjà.
         * Un client peut être contourné (Postman, curl…), le serveur jamais.
         */
        // Vérifie que : c'est un tableau, libelle est présent, niveau_urgence est présent
        // Pourquoi isset() et non empty() pour niveau_urgence ?
        //   empty(0) vaut true : un niveau 0 serait signalé comme "absent" au lieu de "hors plage".
        //   isset() teste seulement la présence ; la plage est contrôlée juste après.
        if (!is_array($donnees) || empty($donnees['libelle'])
            || !isset($donnees['niveau_urgence'])) {
            Reponse::erreur(400, 'libelle et niveau_urgence sont obligatoires.');
        }

        // Extrait le niveau d'urgence et valide qu'il soit dans la plage 1-4
        $niveau = $donnees['niveau_urgence'];
        // is_int() est strict : le JSON doit contenir un nombre (3), pas une chaîne ("3").
        // La colonne niveau_urgence est un TINYINT : la base accepterait 0 ou 100,
        // c'est donc au PHP de faire respecter la règle métier "entre 1 et 4".
        if (!is_int($niveau) || $niveau < 1 || $niveau > 4) {
            Reponse::erreur(400, 'niveau_urgence doit etre un entier entre 1 et 4.');
        }

        /**
         * INSERTION EN BASE DE DONNÉES AVEC GESTION D'ERREURS
         */
        try {
            // trim() : enlève les espaces avant/après pour éviter les doublons ' Bug' vs 'Bug'
            $id = DAO::ajouterCategorie(trim($donnees['libelle']), $niveau);
        } catch (PDOException $e) {
            // Vérifier le code d'erreur SQL :
            // '23000' = violation de contrainte (UNIQUE, FOREIGN KEY, etc.)
            // Ici, c'est un doublon : la table categorie a une contrainte UNIQUE KEY sur libelle.
            // ex : créer "Reseau" alors que la catégorie 1 s'appelle déjà "Reseau".
            // getCode() renvoie une CHAÎNE (code SQLSTATE), d'où la comparaison avec '23000' entre quotes.
            // Plutôt que de vérifier "le libellé existe-t-il ?" AVANT d'insérer (2 requêtes,
            // et risque qu'un autre utilisateur insère entre les deux), on laisse la base
            // faire respecter la contrainte et on intercepte l'erreur.
            if ($e->getCode() === '23000') {
                // 409 Conflict : la requête est correcte, mais entre en conflit avec l'existant.
                Reponse::erreur(409, 'Cette categorie existe deja.');
            }
            // Pour toute autre erreur SQL : la relancer
            // Le routeur (index.php) la catch et retourne une erreur 500
            throw $e;
        }

        /**
         * RÉPONSE SUCCÈS
         * Code 201 = Created (pour une création, au lieu de 200 OK)
         * Retourne les données de la catégorie créée
         * (le client récupère ainsi l'id attribué par AUTO_INCREMENT, sans refaire de requête)
         */
        Reponse::envoyer(201, [
            'id' => $id,
            'libelle' => trim($donnees['libelle']),
            'niveau_urgence' => $niveau,
        ]);
    }
}
