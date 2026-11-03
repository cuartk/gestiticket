<?php
/**
 * controleur/UtilisateurControleur.php - GESTION DES COMPTES UTILISATEURS
 *
 * Ce contrôleur gère :
 * - Lister les utilisateurs (admin only)
 * - Modifier son propre profil (utilisateur authentifié)
 * - Supprimer un utilisateur (admin only, avec garde-fou)
 *
 * Table utilisateur (gestiticket.sql) :
 *   id, nom, prenom, email (UNIQUE), mot_de_passe (hash bcrypt),
 *   role ENUM('admin','utilisateur'), date_inscription
 * Dans la base de départ, Claire DUPONT (id 1) est la seule admin.
 */
class UtilisateurControleur
{
    /**
     * Récupère la liste de tous les utilisateurs.
     * Route protégée : seul un admin peut lister les utilisateurs.
     * (une liste d'emails est une donnée personnelle : elle ne doit pas être publique)
     */
    public static function lister(): void
    {
        // Vérifie que l'utilisateur est admin
        Auth::exigerAdmin();
        // Retourne la liste avec le nombre de tickets par utilisateur
        // (champ nb_tickets calculé en SQL avec LEFT JOIN + COUNT + GROUP BY, voir DAO)
        Reponse::envoyer(200, DAO::getLesUtilisateurs());
    }

    /**
     * Modifie les informations d'un utilisateur.
     * L'utilisateur ne peut modifier que SES propres données (pas celles d'un autre).
     * Cette règle s'applique à tout le monde, y compris à l'admin :
     * le code ne prévoit aucune exception pour le rôle admin.
     *
     * Corps JSON attendu :
     *   { "nom": "DUPONT", "prenom": "Claire", "email": "claire.dupont@gestiticket.fr",
     *     "mot_de_passe": "nouveau-mdp" }      ← mot_de_passe est facultatif
     *
     * @param int $id L'ID de l'utilisateur à modifier
     */
    public static function modifier(int $id): void
    {
        // Récupère l'utilisateur connecté et son jeton
        $connecte = Auth::exigerAuthentification();

        /**
         * VÉRIFICATION DE PERMISSION
         * Un utilisateur ne peut modifier que SON propre profil.
         * Sinon, quelqu'un pourrait changer les données d'un autre utilisateur !
         *
         * On compare l'id de l'URL (/utilisateurs/3) avec l'id du JETON (impossible à falsifier).
         * (int) est nécessaire car "!==" compare aussi le type : 3 (int) !== "3" (string) serait vrai.
         * Ce type de faille (accéder aux données d'un autre en changeant un id dans l'URL)
         * s'appelle une IDOR (Insecure Direct Object Reference) : c'est une faille très courante.
         */
        if ((int) $connecte['id'] !== $id) {
            Reponse::erreur(403, 'Vous ne pouvez modifier que vos propres informations.');
        }

        // Vérifie que l'utilisateur existe
        // (le compte a pu être supprimé alors que son jeton est encore valide)
        if (DAO::getUnUtilisateur($id) === null) {
            Reponse::erreur(404, "L'utilisateur $id n'existe pas.");
        }

        // Récupère les nouvelles données
        $donnees = json_decode(file_get_contents('php://input'), true);

        /**
         * VALIDATION DES PARAMÈTRES
         */
        if (!is_array($donnees) || empty($donnees['nom']) || empty($donnees['prenom'])
            || empty($donnees['email'])) {
            Reponse::erreur(400, 'nom, prenom et email sont obligatoires.');
        }

        // filter_var avec FILTER_VALIDATE_EMAIL : valide le format de l'email
        //   'claire@gestiticket.fr' → renvoie l'email (valeur "vraie")
        //   'claire@'               → renvoie false
        // Cela vérifie seulement la FORME, pas que la boîte mail existe vraiment.
        if (!filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)) {
            Reponse::erreur(400, 'L\'adresse email est invalide.');
        }

        /**
         * MOT DE PASSE OPTIONNEL
         * Si le client envoie un mot_de_passe, on le met à jour.
         * Sinon, on le laisse inchangé (conserve l'ancien hash).
         * Opérateur ternaire : condition ? valeurSiVrai : valeurSiFaux
         * null joue ici le rôle de signal "ne pas toucher au mot de passe" pour le DAO.
         * Le hachage (password_hash) est fait dans le DAO, juste avant l'enregistrement.
         */
        $motDePasse = empty($donnees['mot_de_passe']) ? null : $donnees['mot_de_passe'];

        /**
         * MODIFICATION EN BASE DE DONNÉES AVEC GESTION D'ERREURS
         * email est UNIQUE en base : si l'adresse est déjà prise par un autre compte,
         * le DAO lève une PDOException de code '23000' (violation de contrainte).
         * On l'intercepte pour renvoyer une erreur claire (409) au lieu d'une erreur 500.
         * Même technique que dans CategorieControleur::ajouter().
         *
         * Remarque : garder son PROPRE email ne pose pas de problème, car la ligne modifiée
         * est celle qui possède déjà cet email (MySQL ne la compte pas comme un doublon).
         */
        try {
            DAO::modifierUtilisateur(
                $id,
                trim($donnees['nom']),
                trim($donnees['prenom']),
                trim($donnees['email']),
                $motDePasse  // null si pas de changement, sinon le nouveau mot de passe
            );
        } catch (PDOException $e) {
            // '23000' = violation de contrainte : ici, la seule possible est le doublon d'email
            // (les autres colonnes modifiées n'ont ni contrainte UNIQUE ni clé étrangère).
            if ($e->getCode() === '23000') {
                // 409 Conflict : la requête est valide, mais entre en conflit avec un compte existant.
                Reponse::erreur(409, 'Cette adresse email est deja utilisee par un autre compte.');
            }
            // Toute autre erreur SQL est relancée : index.php la transformera en erreur 500.
            throw $e;
        }

        /**
         * RÉPONSE SUCCÈS
         * Retourne les données mises à jour de l'utilisateur
         * getUnUtilisateur() ne sélectionne PAS la colonne mot_de_passe : rien de sensible n'est renvoyé.
         */
        Reponse::envoyer(200, DAO::getUnUtilisateur($id));
    }

    /**
     * Supprime un utilisateur.
     * Route protégée : seul un admin peut supprimer des utilisateurs.
     *
     * @param int $id L'ID de l'utilisateur à supprimer
     */
    public static function supprimer(int $id): void
    {
        // Récupère l'admin et vérifie son authentification
        $admin = Auth::exigerAdmin();

        /**
         * GARDE-FOU : L'ADMIN NE PEUT PAS SE SUPPRIMER LUI-MÊME
         * Cela évite une situation où on ne pourrait plus accéder à l'admin.
         * (dans la base de départ, il n'y a qu'un seul admin : se supprimer bloquerait
         *  toutes les routes réservées à l'administrateur)
         */
        if ((int) $admin['id'] === $id) {
            Reponse::erreur(400, 'Impossible de supprimer son propre compte.');
        }

        /**
         * SUPPRESSION
         * DAO::supprimerUtilisateur retourne true si la suppression a réussi,
         * false si l'utilisateur n'existait pas.
         * Astuce : on fait la suppression ET le test d'existence en une seule requête
         * (grâce à rowCount() dans le DAO), au lieu d'un SELECT suivi d'un DELETE.
         */
        if (!DAO::supprimerUtilisateur($id)) {
            Reponse::erreur(404, "L'utilisateur $id n'existe pas.");
        }

        /**
         * CASCADE DE SUPPRESSION
         * Grace aux contraintes ON DELETE CASCADE définies dans le schéma (gestiticket.sql) :
         * - Tous les tickets créés par cet utilisateur sont supprimés      (fk_ticket_auteur)
         * - Toutes les interventions de cet utilisateur sont supprimées    (fk_intervention_utilisateur)
         * - Toutes les interventions posées SUR ses tickets sont supprimées aussi,
         *   même si elles ont été écrites par quelqu'un d'autre             (fk_intervention_ticket)
         *
         * Exemple : supprimer Lucas MARTIN (id 2) supprime ses tickets 1 et 5,
         * donc aussi les interventions 1, 4 et 5 (dont deux écrites par Claire DUPONT).
         *
         * La base de données gère cette cascade automatiquement.
         * Nous n'avons rien à faire de plus.
         *
         * Remarque : on aurait aussi pu répondre 204 No Content (succès, sans corps de réponse) ;
         * on préfère ici 200 avec un message de confirmation lisible.
         */
        Reponse::envoyer(200, ['message' => "Utilisateur $id supprime."]);
    }
}
