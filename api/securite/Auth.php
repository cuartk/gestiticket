<?php
/**
 * securite/Auth.php - CONTRÔLE D'ACCÈS VIA JETONS JWT
 *
 * Ce fichier gère l'authentification et l'autorisation.
 * Chaque requête protégée doit inclure un jeton JWT dans l'en-tête HTTP :
 *     Authorization: Bearer <jeton>
 *
 * Les jetons JWT sont sans état (stateless) : le serveur n'a pas besoin
 * de stocker une session. Il suffit de vérifier la signature pour savoir
 * si le jeton est authentique.
 *
 * ─── Deux notions à ne pas confondre ───
 * - AUTHENTIFICATION : "QUI es-tu ?"         → échec = 401 Unauthorized
 *                      (pas de jeton, jeton falsifié ou expiré)
 * - AUTORISATION     : "AS-TU LE DROIT ?"    → échec = 403 Forbidden
 *                      (on sait qui tu es, mais ton rôle ne permet pas cette action)
 *
 * Les deux rôles possibles sont définis dans la base :
 *   utilisateur.role ENUM('admin', 'utilisateur') DEFAULT 'utilisateur'
 *
 * Utilisation typique dans un contrôleur (une seule ligne en début de méthode) :
 *   $utilisateur = Auth::exigerAuthentification();   // n'importe quel utilisateur connecté
 *   $admin       = Auth::exigerAdmin();               // uniquement un admin
 */
class Auth
{
    /**
     * Extrait le jeton JWT de l'en-tête HTTP Authorization.
     * Le vérifie et retourne sa charge utile (données d'utilisateur).
     *
     * @return array|null Charge utile du jeton (id, role, nom, prenom), ou null si absent/invalide
     */
    public static function getUtilisateurConnecte(): ?array
    {
        // getallheaders() : récupère TOUS les en-têtes HTTP de la requête
        // sous forme de tableau associatif : ['Authorization' => 'Bearer eyJ...', 'Content-Type' => ...]
        // Remarque : sous Apache, l'en-tête Authorization est parfois supprimé avant d'arriver à PHP ;
        // c'est pour cela que le .htaccess contient une règle qui le retransmet explicitement.
        $entetes = getallheaders();
        // Cherche 'Authorization' ou 'authorization' (insensible à la casse)
        // Les noms d'en-têtes HTTP ne sont pas sensibles à la casse, mais les clés d'un tableau PHP le sont :
        // on teste donc les deux écritures les plus courantes.
        // "??" imbriqués : on essaie 'Authorization', sinon 'authorization', sinon chaîne vide ''.
        $autorisation = $entetes['Authorization'] ?? ($entetes['authorization'] ?? '');

        // Vérifie que l'en-tête commence par "Bearer " (standard OAuth2/JWT)
        // "Bearer" = "porteur" : celui qui PORTE le jeton est considéré comme son propriétaire.
        // (d'où l'importance de ne jamais laisser fuiter un jeton !)
        // str_starts_with() existe depuis PHP 8.
        if (!str_starts_with($autorisation, 'Bearer ')) {
            return null;  // Pas de jeton ou format invalide
        }

        // Extrait le jeton après "Bearer " et le vérifie
        // substr(..., 7) enlève les 7 premiers caractères ("Bearer ")
        //   "Bearer eyJhbGci..."  →  "eyJhbGci..."
        // Jwt::verifier() renvoie la charge utile (tableau) si le jeton est valide, null sinon :
        // on renvoie directement son résultat.
        return Jwt::verifier(substr($autorisation, 7));
    }

    /**
     * Vérifie qu'un jeton valide a été fourni.
     * Si ce n'est pas le cas, arrête la requête avec un code 401 (Unauthorized).
     *
     * @return array La charge utile du jeton si authentifié
     */
    public static function exigerAuthentification(): array
    {
        $utilisateur = self::getUtilisateurConnecte();
        if ($utilisateur === null) {
            // Arrête la requête et renvoie une erreur 401
            // exit() est appelé dans Reponse::envoyer() (via Reponse::erreur())
            Reponse::erreur(401, 'Authentification requise (jeton absent, invalide ou expire).');
        }
        // Grâce au exit ci-dessus, si on arrive ici, $utilisateur n'est JAMAIS null :
        // le type de retour ": array" (non nullable) est donc bien respecté.
        return $utilisateur;
    }

    /**
     * Vérifie que l'utilisateur est authentifié ET administrateur.
     * Si ce n'est pas le cas, arrête la requête avec un code 403 (Forbidden).
     *
     * @return array La charge utile du jeton si admin
     */
    public static function exigerAdmin(): array
    {
        // D'abord, exige l'authentification
        // On RÉUTILISE la méthode précédente au lieu de recopier son code (principe DRY :
        // "Don't Repeat Yourself"). Si personne n'est connecté → 401 et arrêt ici.
        $utilisateur = self::exigerAuthentification();

        // Ensuite, vérifie le rôle
        // Le rôle est lu dans le JETON (et non en base) : on peut lui faire confiance
        // car la signature garantit que le client ne l'a pas modifié.
        if ($utilisateur['role'] !== 'admin') {
            Reponse::erreur(403, "Acces reserve a l'administrateur.");
        }
        return $utilisateur;
    }
}
