<?php
/**
 * controleur/AuthControleur.php - AUTHENTIFICATION (LOGIN)
 *
 * Ce contrôleur gère l'authentification des utilisateurs.
 * Les utilisateurs envoient leur email et mot de passe, et reçoivent un jeton JWT
 * à utiliser pour les requêtes protégées.
 *
 * Exemple d'échange :
 *
 *   Requête  : POST /login
 *              { "email": "claire.dupont@gestiticket.fr", "mot_de_passe": "..." }
 *
 *   Réponse  : 200 OK
 *              {
 *                "jeton": "eyJhbGciOiJIUzI1NiIs...",
 *                "utilisateur": { "id": 1, "nom": "DUPONT", "prenom": "Claire",
 *                                 "email": "claire.dupont@gestiticket.fr", "role": "admin" }
 *              }
 *
 * Le client conserve ensuite le jeton et l'envoie dans l'en-tête
 * "Authorization: Bearer <jeton>" pour chaque requête protégée.
 */
class AuthControleur
{
    /**
     * Fonction login() - Authentifie un utilisateur et lui retourne un jeton JWT.
     *
     * Flux :
     * 1. Récupère email et mot_de_passe du corps de la requête (JSON)
     * 2. Cherche l'utilisateur en base de données
     * 3. Vérifie que le mot_de_passe correspond
     * 4. Si OK : génère un jeton JWT et le retourne
     * 5. Si erreur : retourne un code 401
     */
    public static function login(): void
    {
        // file_get_contents('php://input') : lit le corps brut de la requête HTTP
        // Pour une requête POST : le JSON envoyé par le client
        // (Remarque : $_POST ne fonctionne PAS ici, il ne se remplit que pour les formulaires HTML
        //  classiques, pas pour un corps de requête en JSON.)
        // json_decode(..., true) : convertit le JSON en tableau PHP associatif
        // Le paramètre "true" est crucial :
        // - true  : retourne un tableau ['email' => '...', 'mot_de_passe' => '...']
        // - false : retourne un objet stdClass (syntax $obj->email au lieu de $arr['email'])
        // Si le JSON est mal formé, json_decode() renvoie null.
        $donnees = json_decode(file_get_contents('php://input'), true);

        // VALIDATION : on ne fait JAMAIS confiance aux données envoyées par le client.
        // - is_array()  : le JSON était-il valide et représentait-il bien un objet ?
        // - empty()     : vrai si la clé est absente, ou si sa valeur est vide ('', null, 0…)
        // Code 400 Bad Request = "ta requête est incomplète ou mal formée".
        if (!is_array($donnees) || empty($donnees['email']) || empty($donnees['mot_de_passe'])) {
            Reponse::erreur(400, 'email et mot_de_passe sont obligatoires.');
        }

        // On cherche le compte par email (colonne UNIQUE en base → au plus un résultat).
        // Le résultat contient le mot de passe HACHÉ, nécessaire pour la vérification ci-dessous.
        $utilisateur = DAO::getUtilisateurParEmail($donnees['email']);

        // Meme message pour "email inconnu" et "mot de passe faux" :
        // ne pas aider un attaquant a savoir si un compte existe
        //
        // password_verify($motDePasseSaisi, $hashStocke) :
        // on ne "déchiffre" jamais le hash (c'est impossible) ; PHP hache la saisie avec
        // le même sel et le même coût que ceux contenus dans le hash, puis compare les résultats.
        // Exemple de hash bcrypt stocké : $2y$10$PPx1qJpx...
        //   $2y$ = algorithme bcrypt, 10 = coût (nombre d'itérations), suivi du sel puis du hash.
        //
        // L'opérateur "||" s'arrête dès que la première condition est vraie (évaluation "court-circuit") :
        // si $utilisateur est null, password_verify() n'est même pas appelé (pas d'erreur sur null['...']).
        if ($utilisateur === null
            || !password_verify($donnees['mot_de_passe'], $utilisateur['mot_de_passe'])) {
            Reponse::erreur(401, 'Identifiants incorrects.');
        }

        // Identifiants corrects : on fabrique le jeton.
        // On y place uniquement ce dont les autres requêtes auront besoin :
        //  - id   → savoir QUI fait la requête (ex : auteur d'un ticket)
        //  - role → savoir s'il est admin (Auth::exigerAdmin)
        //  - nom / prenom → pratique pour l'affichage côté client
        // ⚠️ Jamais le mot de passe (même haché) : le contenu d'un JWT est lisible par tous.
        // La date d'expiration "exp" est ajoutée automatiquement par Jwt::generer().
        $jeton = Jwt::generer([
            'id' => $utilisateur['id'],
            'role' => $utilisateur['role'],
            'nom' => $utilisateur['nom'],
            'prenom' => $utilisateur['prenom'],
        ]);

        // On renvoie le jeton ET un résumé du profil, en construisant le tableau à la main
        // pour choisir précisément les champs exposés (le champ mot_de_passe est volontairement exclu).
        Reponse::envoyer(200, [
            'jeton' => $jeton,
            'utilisateur' => [
                'id' => $utilisateur['id'],
                'nom' => $utilisateur['nom'],
                'prenom' => $utilisateur['prenom'],
                'email' => $utilisateur['email'],
                'role' => $utilisateur['role'],
            ],
        ]);
    }
}
