<?php
/**
 * api/index.php - POINT D'ENTRÉE UNIQUE ET ROUTEUR DE L'API
 *
 * Ce fichier agit comme le point d'entrée central (front controller) pour toutes les requêtes HTTP.
 * Chaque requête passe par ce fichier, qui dirige vers le bon contrôleur selon la méthode HTTP
 * et l'URI demandée.
 *
 * Comment toutes les requêtes arrivent-elles ici ?
 * Grâce au fichier .htaccess (module mod_rewrite d'Apache) :
 *   RewriteCond %{REQUEST_FILENAME} !-f   → si l'URL ne correspond PAS à un vrai fichier…
 *   RewriteRule ^ index.php [QSA,L]       → …alors Apache exécute index.php à la place.
 * Ainsi /gestiticket/api/tickets/5 n'a pas besoin d'un dossier "tickets/5" : tout arrive ici.
 *
 * ARCHITECTURE REST :
 * REST = on manipule des RESSOURCES (tickets, catégories, utilisateurs) identifiées par une URL,
 * et c'est la MÉTHODE HTTP qui indique l'action à réaliser :
 *   GET = lire   POST = créer   PUT = modifier   DELETE = supprimer
 *
 * - GET /tickets         : lister les tickets
 * - GET /tickets/1       : afficher le ticket 1
 * - POST /tickets        : créer un nouveau ticket
 * - POST /categories     : créer une catégorie (admin)
 * - PUT /utilisateurs/1  : modifier l'utilisateur 1
 * - DELETE /utilisateurs/1 : supprimer l'utilisateur 1
 *
 * Ce pattern est appelé "Routing" ou "Dispatcher", et il centralise la logique d'aiguillage.
 *
 * Exemple de test avec curl :
 *   curl -X POST http://localhost/gestiticket/api/login \
 *        -H "Content-Type: application/json" \
 *        -d '{"email":"claire.dupont@gestiticket.fr","mot_de_passe":"..."}'
 */

// require : inclut un fichier PHP. Si le fichier est introuvable, le script s'arrête (erreur fatale),
// contrairement à "include" qui n'émet qu'un avertissement.
// L'ordre compte : Config.php définit les constantes (DB_HOST, JWT_SECRET…) utilisées ensuite.
require 'config/Config.php';
require 'modele/DAO.php';           // M du MVC : accès aux données
require 'vue/Reponse.php';          // V du MVC : mise en forme de la réponse (JSON)
require 'securite/Jwt.php';         // fabrication / vérification des jetons
require 'securite/Auth.php';        // contrôle d'accès (qui est connecté ? est-il admin ?)
require 'controleur/AuthControleur.php';         // C du MVC : un contrôleur par ressource
require 'controleur/CategorieControleur.php';
require 'controleur/TicketControleur.php';
require 'controleur/UtilisateurControleur.php';

// Récupère la méthode HTTP de la requête : GET, POST, PUT, DELETE, etc.
// $_SERVER est un tableau "superglobal" rempli automatiquement par PHP avec les infos de la requête.
$methode = $_SERVER['REQUEST_METHOD'];

// Extraction et nettoyage de l'URI (le chemin demandé)
// Exemple : /gestiticket/api/tickets/5 → $route = /tickets/5
// parse_url(..., PHP_URL_PATH) garde uniquement le chemin et retire la "query string" :
//   /gestiticket/api/tickets?tri=date  →  /gestiticket/api/tickets
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = '/gestiticket/api';  // À adapter si le dossier change
// substr($uri, strlen($base)) : coupe les N premiers caractères (le préfixe /gestiticket/api)
// rtrim(..., '/') : retire le "/" final, pour que /tickets/ et /tickets soient traités pareil
$route = rtrim(substr($uri, strlen($base)), '/');  // Enlève le préfixe et les slashes finaux
if ($route === '') {
    $route = '/';  // Route racine par défaut
}
// Découpage de la route en segments pour accéder aux paramètres
// Exemple : /tickets/5 → ['tickets', '5']
//           /tickets/5/interventions → ['tickets', '5', 'interventions']
// trim($route, '/') retire les "/" au début et à la fin AVANT de découper,
// sinon on obtiendrait un premier segment vide : ['', 'tickets', '5'].
// explode() renvoie toujours au moins un élément : $segments[0] existe donc toujours.
$segments = explode('/', trim($route, '/'));

// try / catch : on "essaie" d'exécuter tout le routage ; si une exception PDOException est levée
// n'importe où (DAO, contrôleur…), l'exécution saute directement dans le bloc catch en bas.
try {
    /**
     * FONCTIONNEMENT DES "if" SUCCESSIFS
     * Il n'y a pas de "else" entre les routes : c'est normal !
     * Chaque contrôleur termine en appelant Reponse::envoyer() ou Reponse::erreur(),
     * qui font un "exit". Dès qu'une route correspond, le script s'arrête donc là,
     * et les "if" suivants ne sont jamais évalués.
     */

    /**
     * BLOC DES ROUTES PUBLIQUES
     * Ces routes sont accessibles sans authentification.
     */

    // Route racine : simple message de bienvenue
    // Pratique pour vérifier rapidement que l'API répond (test de "santé").
    if ($methode === 'GET' && $route === '/') {
        Reponse::envoyer(200, ['api' => 'GestiTicket', 'etape' => 'TP3']);
    }
    // POST /login : authentification (envoie email + mot_de_passe, reçoit un jeton JWT)
    // POST et non GET : un GET mettrait les identifiants dans l'URL (historique, logs serveur…).
    if ($methode === 'POST' && $route === '/login') {
        AuthControleur::login();
    }

    // GET /categories : lister toutes les catégories de tickets
    if ($methode === 'GET' && $route === '/categories') {
        CategorieControleur::lister();
    }

    // GET /tickets : lister tous les tickets
    if ($methode === 'GET' && $route === '/tickets') {
        TicketControleur::lister();
    }

    // GET /tickets/:id : afficher un ticket spécifique avec ses interventions
    // ctype_digit() vérifie que le segment est bien un nombre
    //   ctype_digit('5')   → true
    //   ctype_digit('abc') → false  (→ aucune route ne correspondra → 404)
    //   ctype_digit('-1')  → false  (le signe "-" n'est pas un chiffre)
    // !isset($segments[2]) : refuse /tickets/5/autre-chose (3 segments) pour cette route.
    // (int) convertit la chaîne '5' en entier 5, car afficher() attend un paramètre int.
    if ($methode === 'GET' && $segments[0] === 'tickets'
        && isset($segments[1]) && ctype_digit($segments[1]) && !isset($segments[2])) {
        TicketControleur::afficher((int) $segments[1]);
    }

    /**
     * BLOC DES ROUTES PROTÉGÉES
     * Ces routes nécessitent un jeton JWT valide dans l'en-tête Authorization.
     * Chaque contrôleur effectue lui-même les vérifications de permissions.
     * (le routeur se contente d'aiguiller : il ne sait pas qui a le droit de faire quoi)
     */

    // POST /tickets : créer un nouveau ticket (admin uniquement)
    // Même URL que GET /tickets, mais méthode différente → action différente (principe REST).
    if ($methode === 'POST' && $route === '/tickets') {
        TicketControleur::ajouter();
    }

    // POST /categories : créer une nouvelle catégorie (admin uniquement)
    if ($methode === 'POST' && $route === '/categories') {
        CategorieControleur::ajouter();
    }

    // POST /tickets/:id/interventions : ajouter une intervention à un ticket (authentifié)
    // URL "imbriquée" : les interventions sont une sous-ressource d'un ticket donné.
    // $segments[2] ?? '' : récupère le 3e élément, ou vide string s'il n'existe pas
    // "??" = opérateur de fusion null : renvoie la valeur de gauche si elle existe et n'est pas null,
    // sinon la valeur de droite. Cela évite un avertissement "Undefined array key 2".
    if ($methode === 'POST' && $segments[0] === 'tickets'
        && isset($segments[1]) && ctype_digit($segments[1])
        && ($segments[2] ?? '') === 'interventions') {
        TicketControleur::intervenir((int) $segments[1]);
    }

    // GET /utilisateurs : lister tous les utilisateurs (admin uniquement)
    if ($methode === 'GET' && $route === '/utilisateurs') {
        UtilisateurControleur::lister();
    }

    // PUT /utilisateurs/:id : modifier un utilisateur (l'utilisateur modifie ses propres données)
    // PUT = remplacer les données d'une ressource existante (ici : nom, prénom, email, mot de passe).
    if ($methode === 'PUT' && $segments[0] === 'utilisateurs'
        && isset($segments[1]) && ctype_digit($segments[1])) {
        UtilisateurControleur::modifier((int) $segments[1]);
    }

    // DELETE /utilisateurs/:id : supprimer un utilisateur (admin uniquement)
    if ($methode === 'DELETE' && $segments[0] === 'utilisateurs'
        && isset($segments[1]) && ctype_digit($segments[1])) {
        UtilisateurControleur::supprimer((int) $segments[1]);
    }

    /**
     * Si aucune route ne correspond, on retourne une erreur 404.
     * Cela signifie que le client a tenté une requête sur une URL inexistante.
     * On n'arrive sur cette ligne QUE si aucun "if" précédent n'a déclenché de exit.
     * Les guillemets doubles "..." permettent d'insérer directement $methode et $route
     * dans la chaîne (interpolation de variables), ce que les guillemets simples ne font pas.
     */
    Reponse::erreur(404, "Route inconnue : $methode $route");

} catch (PDOException $e) {
    /**
     * Bloc de gestion des exceptions de base de données.
     * PDOException est levée quand la connexion à la DB échoue ou une requête SQL échoue.
     * (c'est possible car DAO::getPdo() active PDO::ERRMODE_EXCEPTION)
     * On retourne une erreur 500 (Internal Server Error).
     *
     * Codes HTTP : 2xx = succès, 4xx = erreur du CLIENT (mauvaise requête),
     *              5xx = erreur du SERVEUR (ce n'est pas la faute du client).
     *
     * ⚠️ En production, on éviterait d'envoyer $e->getMessage() au client :
     * le message peut révéler des détails internes (noms de tables, de colonnes…).
     * On l'écrirait plutôt dans un fichier de log, et on renverrait un message générique.
     */
    Reponse::erreur(500, 'Erreur serveur : ' . $e->getMessage());
}
