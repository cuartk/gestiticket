GestiTicket - API REST COMPLETE (etat en fin de TP3)
=====================================================
C'est le "filet de securite" : backend complet et teste,
a distribuer aux etudiants avant la phase React (TP4 a TP7).

Installation :
1. Copier le dossier "gestiticket" dans C:\wamp64\www\
2. Creer la base avec gestiticket.sql (TP1)
3. Executer sql/maj_mots_de_passe.sql
   -> tous les comptes ont le mot de passe : azerty
   -> admin : claire.dupont@gestiticket.fr
4. Activer le module Apache rewrite_module
5. Tester : http://localhost/gestiticket/api/

Routes :
  PUBLIC
    GET    /                              etat de l'API
    POST   /login                         { email, mot_de_passe } -> jeton JWT
    GET    /categories                    liste des categories
    GET    /tickets                       liste des tickets
    GET    /tickets/{id}                  detail + interventions
  AUTHENTIFIE (en-tete Authorization: Bearer <jeton>)
    POST   /tickets/{id}/interventions    { contenu, cloture_ticket? } -> 201
    PUT    /utilisateurs/{id}             ses propres infos -> 200
  ADMIN
    POST   /tickets                       { titre, description, id_categorie } -> 201
    POST   /categories                    { libelle, niveau_urgence } -> 201
    GET    /utilisateurs                  liste (avec nb_tickets) -> 200
    DELETE /utilisateurs/{id}             -> 200 (cascade tickets/interventions)

Codes : 200, 201, 400 (donnees invalides), 401 (jeton absent/invalide),
        403 (role insuffisant), 404, 409 (categorie en doublon), 500.
