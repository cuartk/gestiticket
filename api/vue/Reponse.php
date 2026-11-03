<?php
/**
 * vue/Reponse.php - CLASSE DE FORMATAGE DES RÉPONSES JSON
 *
 * Toutes les réponses HTTP de l'API passent par cette classe.
 * Elle se charge de :
 * - Définir le code HTTP correct (200, 201, 400, 401, 403, 404, 500...)
 * - Ajouter l'en-tête Content-Type: application/json
 * - Convertir les données PHP en JSON
 * - Arrêter l'exécution du script
 *
 * Centraliser les réponses permet une cohérence et facilite les évolutions.
 *
 * Pourquoi est-ce la "Vue" du MVC ?
 * Dans un site web classique, la vue produit du HTML. Dans une API, le client
 * (application mobile, front JavaScript, Postman…) attend du JSON : c'est donc ici
 * que l'on "présente" les données.
 *
 * ─── Codes HTTP utilisés dans GestiTicket ───
 *   200 OK            : lecture ou modification réussie
 *   201 Created       : une ressource a été créée (ticket, catégorie, intervention)
 *   400 Bad Request   : données envoyées incomplètes ou invalides
 *   401 Unauthorized  : non authentifié (jeton absent, invalide ou expiré)
 *   403 Forbidden     : authentifié, mais pas les droits nécessaires
 *   404 Not Found     : ressource ou route inexistante
 *   409 Conflict      : conflit avec l'existant (ex : catégorie déjà présente)
 *   500 Server Error  : erreur côté serveur (ex : base de données injoignable)
 */
class Reponse
{
    /**
     * Envoie une réponse HTTP avec un code et des données JSON.
     * Arrête l'exécution du script (exit).
     *
     * @param int $codeHttp Code HTTP (200, 201, 400, 401, 403, 404, 500...)
     * @param mixed $donnees Données à encoder en JSON (tableau, objet, string...)
     * @return void
     */
    public static function envoyer(int $codeHttp, mixed $donnees): void
    {
        // "mixed" (PHP 8) : le paramètre accepte n'importe quel type (tableau, chaîne, null…).

        // Définit le code HTTP de la réponse
        // 200 = OK, 201 = Created, 400 = Bad Request, 401 = Unauthorized, etc.
        http_response_code($codeHttp);

        // Ajoute l'en-tête Content-Type pour indiquer qu'on envoie du JSON
        // charset=utf-8 : pour supporter les caractères spéciaux (accents, emoji...)
        // ⚠️ header() doit être appelé AVANT tout affichage (echo) : une fois le corps
        // de la réponse commencé, les en-têtes sont déjà partis et ne peuvent plus être modifiés.
        header('Content-Type: application/json; charset=utf-8');

        // json_encode() : convertit les données PHP en chaîne JSON
        //   ['id' => 1, 'libelle' => 'Reseau']  →  {"id": 1, "libelle": "Reseau"}
        //   [ [...], [...] ] (liste)            →  [ {...}, {...} ]
        // JSON_UNESCAPED_UNICODE : ne pas échapper les caractères UTF-8
        //   (sans cette option, "é" deviendrait "é")
        // JSON_PRETTY_PRINT : formate lisiblement (retours à la ligne, indentation)
        // Le "|" (OU binaire) permet de combiner plusieurs options en une seule valeur.
        echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // exit : arrête l'exécution du script ici
        // Cela empêche d'autres codes de s'exécuter après l'envoi de la réponse
        // C'est ce qui permet, dans index.php et dans les contrôleurs, d'enchaîner des "if"
        // sans "else" : après un envoi, rien d'autre n'est exécuté.
        exit;
    }

    /**
     * Envoie une réponse d'erreur.
     * Wrapper pratique pour envoyer un objet {'erreur': '...'} avec le code approprié.
     *
     * Exemple de JSON reçu par le client :
     *   { "erreur": "Le ticket 42 n'existe pas." }
     * Toutes les erreurs ont ainsi la même forme : le client sait toujours où lire le message.
     *
     * @param int $codeHttp Code HTTP de l'erreur
     * @param string $message Message d'erreur à afficher au client
     * @return void
     */
    public static function erreur(int $codeHttp, string $message): void
    {
        // Appelle self::envoyer() avec une structure standard d'erreur
        // (un "wrapper" = une petite fonction qui en enveloppe une autre pour simplifier son appel)
        self::envoyer($codeHttp, ['erreur' => $message]);
    }
}
