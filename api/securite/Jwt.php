<?php
// securite/Jwt.php
// Génération et vérification de jetons JWT signés avec HS256.
//
// Un JWT possède trois parties séparées par des points :
//   header.payload.signature
//
// Exemple :
//   eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9
//   .eyJpZCI6MTIsImV4cCI6MTcwMDAwMDAwMH0
//   .signature
//
// Cette version est pédagogique : dans un vrai projet, on préférera
// généralement une bibliothèque éprouvée (par exemple firebase/php-jwt).
//
// À QUOI SERT UN JWT ?
// HTTP est "sans mémoire" (stateless) : chaque requête est indépendante.
// Après le login, le serveur remet au client un jeton qui dit en substance :
//   "je certifie que tu es l'utilisateur 1, rôle admin, jusqu'à telle heure".
// Le client renvoie ce jeton à chaque requête (en-tête Authorization: Bearer <jeton>).
// Le serveur n'a rien à stocker : il lui suffit de vérifier la signature.
//
// POURQUOI UNE SIGNATURE ?
// Le contenu d'un JWT est LISIBLE par tous (simple Base64), donc modifiable par le client.
// La signature, calculée avec une clé secrète que SEUL le serveur connaît,
// permet de détecter toute modification : c'est un "sceau" anti-falsification.
//
// Cycle complet dans GestiTicket :
//   1. POST /login            → AuthControleur appelle Jwt::generer()  → renvoie le jeton
//   2. requête protégée       → Auth::getUtilisateurConnecte() appelle Jwt::verifier()
//   3. jeton valide           → on connaît l'id et le rôle de l'utilisateur sans requête SQL

class Jwt
{
    /**
     * Encode une chaîne au format Base64 URL-safe.
     *
     * Le Base64 standard utilise les caractères "+" et "/",
     * qui peuvent être gênants dans une URL ou un jeton.
     *
     * On les remplace donc par "-" et "_".
     * Les "=" de remplissage final sont aussi supprimés, conformément
     * au format habituellement utilisé par les JWT.
     */
    private static function base64UrlEncoder(string $donnees): string
    {
        // base64_encode : encode les données en Base64 classique.
        // strtr : remplace + par - et / par _.
        // rtrim : retire les caractères = à la fin.
        return rtrim(
            strtr(base64_encode($donnees), '+/', '-_'),
            '='
        );
    }

    /**
     * Décode une chaîne Base64 URL-safe.
     *
     * On remet d'abord les caractères du Base64 classique :
     * - devient +
     * _ devient /
     *
     * Puis on applique base64_decode.
     *
     * Remarque : on ne remet pas les "=" de remplissage supprimés à l'encodage.
     * Ce n'est pas un oubli : base64_decode() de PHP accepte une chaîne sans ces "=".
     *
     * "private" : ces deux méthodes utilitaires ne servent qu'à l'intérieur de la classe Jwt.
     * L'extérieur n'a accès qu'à generer() et verifier() (principe d'encapsulation).
     */
    private static function base64UrlDecoder(string $donnees): string
    {
        return base64_decode(
            strtr($donnees, '-_', '+/')
        );
    }

    /**
     * Génère un JWT signé.
     *
     * @param array $chargeUtile Les données à stocker dans le jeton.
     *                            Exemple : ['id' => 12, 'role' => 'admin']
     *
     * @return string Le JWT au format header.payload.signature
     */
    public static function generer(array $chargeUtile): string
    {
        /*
         * 1. Création de l'en-tête (header)
         *
         * alg : algorithme de signature utilisé.
         * HS256 : HMAC utilisant SHA-256 et une clé secrète partagée.
         * typ : indique que le jeton est un JWT.
         */
        $entete = self::base64UrlEncoder(
            json_encode([
                'alg' => 'HS256',
                'typ' => 'JWT'
            ])
        );

        /*
         * 2. Ajout de la date d'expiration dans la charge utile (payload)
         *
         * time() renvoie le nombre de secondes écoulées depuis
         * le 1er janvier 1970 (timestamp Unix).
         *
         * JWT_DUREE doit être défini dans la configuration, par exemple :
         * define('JWT_DUREE', 3600);
         *
         * Ici, le jeton expirera dans une heure.
         */
        $chargeUtile['exp'] = time() + JWT_DUREE;

        /*
         * La charge utile est convertie en JSON, puis encodée en Base64 URL-safe.
         *
         * Attention : un JWT est encodé, pas chiffré.
         * Toute personne possédant le jeton peut lire son contenu.
         * Il ne faut donc jamais y placer un mot de passe ou une donnée secrète.
         */
        $charge = self::base64UrlEncoder(
            json_encode($chargeUtile)
        );

        /*
         * 3. Calcul de la signature
         *
         * On signe exactement la chaîne :
         *   header.payload
         *
         * hash_hmac() fabrique une empreinte HMAC-SHA256 avec JWT_SECRET.
         * HMAC = hachage "avec clé" : sans connaître JWT_SECRET, impossible de calculer
         * la bonne signature, même en connaissant parfaitement l'algorithme.
         *
         * "$entete.$charge" : chaîne entre guillemets doubles → PHP remplace les variables
         * par leur valeur. Le point au milieu est ici un simple caractère "." du texte
         * (et non l'opérateur de concaténation PHP).
         *
         * Le quatrième paramètre à true demande le résultat binaire brut.
         * Celui-ci est ensuite converti au format Base64 URL-safe.
         *
         * JWT_SECRET doit être une longue clé aléatoire, conservée côté serveur
         * et jamais exposée dans Git ou dans le code public.
         */
        $signature = self::base64UrlEncoder(
            hash_hmac(
                'sha256',
                "$entete.$charge",
                JWT_SECRET,
                true
            )
        );

        /*
         * 4. Assemblage final du jeton
         *
         * Les trois segments sont séparés par un point.
         */
        return "$entete.$charge.$signature";
    }

    /**
     * Vérifie la structure, la signature et l'expiration d'un JWT.
     *
     * @param string $jeton Le jeton reçu, souvent via l'en-tête HTTP :
     *                      Authorization: Bearer <jeton>
     *
     * @return array|null La charge utile si le jeton est valide ;
     *                    null si le jeton est invalide, falsifié ou expiré.
     */
    public static function verifier(string $jeton): ?array
    {
        /*
         * 1. Découpage du jeton autour des points.
         *
         * Un JWT valide doit obligatoirement posséder trois parties :
         * header.payload.signature
         */
        $parties = explode('.', $jeton);

        if (count($parties) !== 3) {
            return null;
        }

        /*
         * Déstructuration : affecte directement chaque segment
         * à une variable distincte.
         */
        [$entete, $charge, $signature] = $parties;

        /*
         * 2. Recalcul de la signature attendue.
         *
         * Le serveur recalcule la signature à partir du header et du payload
         * reçus, en utilisant sa propre clé secrète JWT_SECRET.
         *
         * Si un utilisateur a changé, par exemple, "role": "user" en
         * "role": "admin", la signature recalculée sera différente.
         *
         * Point de sécurité : on recalcule TOUJOURS en HS256, sans lire le champ "alg"
         * de l'en-tête reçu. Cela protège contre l'attaque connue où un pirate envoie
         * un en-tête {"alg": "none"} pour tenter de faire accepter un jeton non signé.
         */
        $signatureAttendue = self::base64UrlEncoder(
            hash_hmac(
                'sha256',
                "$entete.$charge",
                JWT_SECRET,
                true
            )
        );

        /*
         * hash_equals() compare les deux signatures de façon sûre.
         *
         * Cette fonction limite les attaques temporelles : elle évite qu'un
         * attaquant puisse déduire progressivement une signature valide en
         * mesurant le temps de réponse du serveur.
         *
         * On évite donc d'écrire :
         *   $signatureAttendue !== $signature
         */
        if (!hash_equals($signatureAttendue, $signature)) {
            return null; // Le jeton a probablement été modifié ou falsifié.
        }

        /*
         * 3. Décodage de la charge utile.
         *
         * base64UrlDecoder() récupère le JSON original.
         * json_decode(..., true) transforme ce JSON en tableau associatif PHP.
         */
        $chargeUtile = json_decode(
            self::base64UrlDecoder($charge),
            true
        );

        /*
         * 4. Vérification de la charge utile et de l'expiration.
         *
         * Le jeton est refusé si :
         * - le JSON est invalide ou ne donne pas un tableau ;
         * - la clé exp est absente ;
         * - la date d'expiration est dépassée.
         */
        if (
            !is_array($chargeUtile)
            || !isset($chargeUtile['exp'])
            || $chargeUtile['exp'] < time()
        ) {
            return null; // Jeton illisible, incomplet ou expiré.
        }

        /*
         * Le jeton est authentique, lisible et encore valide.
         * On renvoie les informations qu'il contient.
         * Exemple : ['id' => 1, 'role' => 'admin', 'nom' => 'DUPONT', 'prenom' => 'Claire', 'exp' => 1791234567]
         *
         * Limite à connaître : un jeton reste valide jusqu'à "exp", même si l'utilisateur
         * a été supprimé ou a changé de rôle entre-temps (le serveur ne garde aucune trace
         * des jetons émis). C'est le prix à payer pour une authentification sans état.
         */
        return $chargeUtile;
    }
}