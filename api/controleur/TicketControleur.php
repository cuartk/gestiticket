<?php
/**
 * controleur/TicketControleur.php - GESTION DES TICKETS DE SUPPORT
 *
 * Les tickets permettent aux utilisateurs de signaler des problèmes.
 * Chaque ticket peut avoir plusieurs interventions (réponses) d'autres utilisateurs.
 *
 * Exemple tiré de la base de départ :
 *   Ticket 1 "Pas d'acces Internet en salle B12" (catégorie Reseau, statut en_cours)
 *     ├─ intervention de Claire DUPONT : "Switch de la salle B12 redemarre, tests en cours."
 *     └─ intervention de Lucas MARTIN  : "Le probleme persiste apres redemarrage du switch."
 *
 * Cycle de vie (colonne statut, type ENUM) :  nouveau  →  en_cours  →  resolu
 * Un ticket passe à 'resolu' quand une intervention est envoyée avec "cloture_ticket": true.
 */
class TicketControleur
{
    /**
     * Récupère la liste de tous les tickets.
     * Route publique : pas d'authentification requise.
     */
    public static function lister(): void
    {
        // Retourne tous les tickets sans détails (liste simplifiée)
        // (sans la description ni les interventions : c'est une vue "résumé",
        //  plus légère à transférer quand il y a beaucoup de tickets)
        Reponse::envoyer(200, DAO::getLesTickets());
    }

    /**
     * Récupère un ticket spécifique avec tous ses détails et ses interventions.
     *
     * @param int $id L'ID du ticket à afficher
     */
    public static function afficher(int $id): void
    {
        // Cherche le ticket en base
        $ticket = DAO::getUnTicket($id);
        if ($ticket === null) {
            // Le ticket n'existe pas : erreur 404 (Not Found)
            Reponse::erreur(404, "Le ticket $id n'existe pas.");
        }

        // Récupère toutes les interventions (commentaires/réponses) de ce ticket
        // On AJOUTE une clé 'interventions' au tableau du ticket : les données de deux requêtes SQL
        // sont ainsi regroupées dans un seul objet JSON "imbriqué" :
        //   { "id": 1, "titre": "...", ..., "interventions": [ {...}, {...} ] }
        // Le client obtient tout en un seul appel HTTP.
        $ticket['interventions'] = DAO::getLesInterventionsDuTicket($id);
        Reponse::envoyer(200, $ticket);
    }

    /**
     * Crée un nouveau ticket.
     * Route protégée : seul un admin peut créer un ticket.
     *
     * Corps JSON attendu :
     *   { "titre": "Souris HS poste 4", "description": "La souris ne repond plus.", "id_categorie": 2 }
     */
    public static function ajouter(): void
    {
        // Récupère l'admin et vérifie son authentification
        // On garde la valeur de retour (charge utile du jeton) : on a besoin de son id plus bas.
        $admin = Auth::exigerAdmin();

        // Récupère les données du corps de la requête
        $donnees = json_decode(file_get_contents('php://input'), true);

        /**
         * VALIDATION DES PARAMÈTRES
         * Les trois champs sont NOT NULL dans la table ticket : sans eux, l'INSERT échouerait.
         * Mieux vaut renvoyer un message 400 clair qu'une erreur SQL 500 incompréhensible.
         */
        if (!is_array($donnees) || empty($donnees['titre']) || empty($donnees['description'])
            || empty($donnees['id_categorie'])) {
            Reponse::erreur(400, 'titre, description et id_categorie sont obligatoires.');
        }

        // Vérifie que la catégorie existe réellement
        // (sinon il y aurait une erreur de clé étrangère en base)
        // La contrainte fk_ticket_categorie empêche en effet d'insérer un id_categorie inexistant
        // (intégrité référentielle). On anticipe pour répondre proprement par un 400.
        if (!DAO::categorieExiste((int) $donnees['id_categorie'])) {
            Reponse::erreur(400, 'La categorie indiquee n\'existe pas.');
        }

        /**
         * CRÉATION DU TICKET
         * L'auteur est automatiquement l'admin connecté
         * ⚠️ On prend l'id dans le JETON, jamais dans le corps de la requête :
         * sinon n'importe qui pourrait créer un ticket "au nom" d'un autre utilisateur.
         * Le statut ('nouveau') et la date sont remplis par les valeurs DEFAULT de la base.
         */
        $id = DAO::ajouterTicket(
            trim($donnees['titre']),
            trim($donnees['description']),
            (int) $donnees['id_categorie'],
            (int) $admin['id']  // L'auteur est l'utilisateur authentifié
        );

        /**
         * RÉPONSE SUCCÈS
         * On relit le ticket en base pour renvoyer sa version complète, telle que stockée
         * (avec l'id, le statut et la date générés par MySQL, le nom de la catégorie et de l'auteur).
         * Remarque : contrairement à afficher(), on n'ajoute pas la clé 'interventions' ici
         * (de toute façon, un ticket tout juste créé n'en a aucune).
         */
        Reponse::envoyer(201, DAO::getUnTicket($id));
    }

    /**
     * Ajoute une intervention (réponse/action) sur un ticket.
     * L'utilisateur authentifié peut commenter ou résoudre le problème.
     *
     * Corps JSON attendu :
     *   { "contenu": "Cable reseau remplace.", "cloture_ticket": true }   (cloture_ticket est facultatif)
     *
     * @param int $idTicket L'ID du ticket auquel intervenir
     */
    public static function intervenir(int $idTicket): void
    {
        // Vérifie que l'utilisateur est authentifié (jeton valide)
        // Ici, pas besoin d'être admin : tout utilisateur connecté peut intervenir.
        $utilisateur = Auth::exigerAuthentification();

        /**
         * VÉRIFICATION QUE LE TICKET EXISTE
         * Ordre des vérifications : 401 (qui es-tu ?) → 404 (la ressource existe ?) → 400 (données ?)
         */
        if (DAO::getUnTicket($idTicket) === null) {
            Reponse::erreur(404, "Le ticket $idTicket n'existe pas.");
        }

        // Récupère les données du corps de la requête
        $donnees = json_decode(file_get_contents('php://input'), true);
        if (!is_array($donnees) || empty($donnees['contenu'])) {
            Reponse::erreur(400, 'contenu est obligatoire.');
        }

        // Le flag 'cloture_ticket' est optionnel (par défaut false)
        // C'est un booléen indiquant si cette intervention résout le ticket
        // "?? false" : si la clé est absente → false.
        // (bool) force la conversion en booléen : true, 1 ou "1" → true ; false, 0 ou "" → false.
        $cloture = (bool) ($donnees['cloture_ticket'] ?? false);

        /**
         * CRÉATION DE L'INTERVENTION
         * La date_intervention est remplie automatiquement (DEFAULT CURRENT_TIMESTAMP).
         * Comme pour le ticket, l'auteur est pris dans le jeton, pas dans le JSON reçu.
         */
        DAO::ajouterIntervention(
            trim($donnees['contenu']),
            $cloture,
            $idTicket,
            (int) $utilisateur['id']  // L'auteur est l'utilisateur authentifié
        );

        /**
         * MISE À JOUR DU STATUT DU TICKET
         * Si l'intervention clôture le ticket, on le passe à 'resolu'
         * ('resolu' fait bien partie des valeurs autorisées par l'ENUM de la colonne statut)
         *
         * Pour aller plus loin : ces deux écritures (INSERT intervention + UPDATE ticket)
         * pourraient être regroupées dans une TRANSACTION (beginTransaction / commit),
         * afin qu'elles réussissent ou échouent ensemble.
         */
        if ($cloture) {
            DAO::changerStatutTicket($idTicket, 'resolu');
        }

        /**
         * RÉPONSE SUCCÈS
         * Retourne le ticket mis à jour avec toutes ses interventions
         * Code 201 = Created (intervention créée)
         * Même assemblage que dans afficher() : ticket + clé 'interventions'.
         */
        $ticket = DAO::getUnTicket($idTicket);
        $ticket['interventions'] = DAO::getLesInterventionsDuTicket($idTicket);
        Reponse::envoyer(201, $ticket);
    }
}
