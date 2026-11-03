<?php
/**
 * modele/DAO.php - COUCHE D'ACCÈS AUX DONNÉES (DATA ACCESS OBJECT)
 *
 * Le pattern DAO centralise TOUS les accès à la base de données.
 * Cela permet de :
 * - Isoler la logique métier (contrôleurs) de la logique de persistence (DAO)
 * - Faciliter les tests unitaires
 * - Changer facilement de système de stockage (MySQL → PostgreSQL, etc.)
 *
 * Tous les appels à la base de données passent par les méthodes statiques de cette classe.
 * Aucun contrôleur n'effectue directement de requête SQL.
 *
 * ─── Où se situe le DAO dans l'architecture MVC ? ───
 *
 *   Client (navigateur, Postman…)
 *        │  requête HTTP (GET /tickets/5)
 *        ▼
 *   Contrôleur  ── vérifie les droits, valide les données reçues
 *        │  appelle DAO::getUnTicket(5)
 *        ▼
 *   DAO (ce fichier) ── construit et exécute la requête SQL
 *        │
 *        ▼
 *   Base MySQL ── renvoie les lignes
 *
 * Le contrôleur ne connaît PAS le SQL, et le DAO ne connaît PAS le HTTP.
 * Chaque couche a une seule responsabilité : c'est le principe de "séparation des préoccupations".
 *
 * ─── Méthodes statiques : pourquoi ? ───
 * Toutes les méthodes sont "static" : on les appelle directement sur la classe,
 * sans créer d'objet avec "new". Exemple : DAO::getLesTickets()
 * C'est pratique ici car le DAO n'a pas d'état propre à conserver (à part la connexion).
 *
 * ─── Conventions de nommage utilisées ───
 * - getLesXxx()   → retourne une LISTE (tableau de tableaux)
 * - getUnXxx()    → retourne UN élément (tableau) ou null
 * - ajouterXxx()  → INSERT, retourne l'id créé
 * - modifierXxx() / changerXxx() → UPDATE
 * - supprimerXxx() → DELETE
 * - xxxExiste()   → retourne un booléen
 *
 * ─── Rappel du schéma de la base "gestiticket" (voir gestiticket.sql) ───
 *
 *   categorie (id, libelle UNIQUE, niveau_urgence)
 *        ▲ 1
 *        │  ticket.id_categorie  (clé étrangère, PAS de cascade)
 *        │ N
 *   ticket (id, titre, description, statut ENUM, date_creation, id_categorie, id_auteur)
 *        ▲ 1                                                              │ N
 *        │  intervention.id_ticket (ON DELETE CASCADE)                     │ ticket.id_auteur
 *        │ N                                                               ▼ 1 (ON DELETE CASCADE)
 *   intervention (id, contenu, date_intervention,            utilisateur (id, nom, prenom, email UNIQUE,
 *                 cloture_ticket, id_ticket, id_utilisateur) ──▶          mot_de_passe, role ENUM, date_inscription)
 *                                       N        (ON DELETE CASCADE)   1
 *
 * Lecture : "1 ticket possède N interventions", "1 utilisateur écrit N tickets", etc.
 * La flèche part toujours de la table qui CONTIENT la clé étrangère (le côté N).
 */
class DAO
{
    /**
     * Stockage statique de la connexion PDO.
     * "static" signifie que cette variable appartient à la CLASSE et non à un objet :
     * elle est partagée partout et accessible via self::$cnx.
     * "?PDO" signifie qu'elle peut être null ou un objet PDO (type "nullable").
     * "private" : seule la classe DAO peut y accéder directement ; l'extérieur doit passer par getPdo().
     * On utilise une seule connexion pour toute l'application (pattern Singleton).
     */
    private static ?PDO $cnx = null;

    /**
     * Retourne la connexion PDO à la base de données.
     * Si elle n'existe pas encore, la crée. Si elle existe, la réutilise.
     *
     * Cela s'appelle un "lazy initialization" : on ne crée la ressource coûteuse
     * (connexion DB) que quand on en a vraiment besoin.
     *
     * PDO (PHP Data Objects) est l'interface standard de PHP pour parler aux bases
     * de données. Le même code fonctionne avec MySQL, PostgreSQL, SQLite… seul le DSN change.
     *
     * @return PDO L'objet de connexion à la base de données
     */
    public static function getPdo(): PDO
    {
        // Vérifie si la connexion existe déjà. Si oui, la réutilise (performance).
        // "===" compare la valeur ET le type (comparaison stricte), plus sûr que "==".
        if (self::$cnx === null) {
            // Crée une nouvelle connexion PDO.
            // DSN (Data Source Name) : decrit comment se connecter au serveur MySQL
            // charset=utf8mb4 : accepte tous les caractères UTF-8 (emoji, accents, etc.)
            // DB_HOST, DB_NAME, DB_USER, DB_PASS sont des CONSTANTES définies dans un fichier
            // de configuration : on ne met jamais les identifiants en dur dans le code métier.
            self::$cnx = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    // ATTR_ERRMODE => ERRMODE_EXCEPTION : les erreurs SQL lèvent une exception (plus facile à gérer)
                    // Sans cette option, PDO échouerait "silencieusement" (retour false) et le bug
                    // serait beaucoup plus difficile à repérer.
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    // ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC : les résultats sont des tableaux associatifs
                    // au lieu d'objets (plus simple pour une API)
                    // Exemple de ligne obtenue : ['id' => 3, 'libelle' => 'Bug', 'niveau_urgence' => 4]
                    // → se transforme directement en JSON avec json_encode().
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }
        // "self::" désigne la classe courante (DAO) dans un contexte statique
        // (l'équivalent de "$this->" pour un objet).
        return self::$cnx;
    }

    // ===== CATÉGORIES DE TICKETS =====
    // Méthodes pour lire et créer les catégories

    /**
     * Récupère toutes les catégories de tickets.
     * Ordonne par niveau d'urgence (décroissant), puis par libellé alphabétique.
     *
     * @return array Tableau de catégories. Chaque élément : ['id', 'libelle', 'niveau_urgence']
     */
    public static function getLesCategories(): array
    {
        // ORDER BY sur deux colonnes : on trie d'abord par urgence (DESC = du plus grand au plus petit),
        // puis, pour les catégories de même urgence, par libellé (ASC par défaut = ordre alphabétique).
        $sql = 'SELECT id, libelle, niveau_urgence
                FROM categorie
                ORDER BY niveau_urgence DESC, libelle';
        // query() : quand il n'y a pas de paramètres variables
        // (aucune donnée venant de l'utilisateur → aucun risque d'injection SQL)
        // fetchAll() : récupère tous les résultats sous forme de tableau
        // Si la table est vide, fetchAll() renvoie un tableau vide [] (jamais false).
        return self::getPdo()->query($sql)->fetchAll();
    }

    /**
     * Insère une nouvelle catégorie dans la base de données.
     *
     * Dans la base, la colonne libelle porte une contrainte UNIQUE :
     * insérer un libellé déjà existant (ex : "Reseau") lève une PDOException de code '23000',
     * que CategorieControleur transforme en réponse 409 Conflict.
     *
     * @param string $libelle Nom/description de la catégorie (ex: "Reseau", "Materiel", "Logiciel")
     * @param int $niveauUrgence Niveau d'urgence de 1 à 4 (1=basse, 4=critique)
     *                           (colonne TINYINT : entier sur 1 octet, largement suffisant pour 1..4)
     * @return int L'ID de la catégorie nouvellement créée
     */
    public static function ajouterCategorie(string $libelle, int $niveauUrgence): int
    {
        // prepare() : prépare la requête avec des paramètres nommés (":libelle", ":niveau")
        // Cela empêche les injections SQL (le langage SQL n'est pas mélangé aux données).
        //
        // ❌ À NE JAMAIS FAIRE : "INSERT ... VALUES ('" . $libelle . "', ...)"
        //    Si $libelle vaut  "x'); DROP TABLE categorie; --"  la table serait détruite !
        // ✅ Avec prepare(), la valeur est envoyée SÉPARÉMENT de la requête :
        //    MySQL la traite toujours comme une donnée, jamais comme du code SQL.
        $req = self::getPdo()->prepare(
            'INSERT INTO categorie (libelle, niveau_urgence) VALUES (:libelle, :niveau)'
        );
        // execute() : remplace les ":param" par leurs vraies valeurs, puis exécute la requête
        // Le tableau associe chaque marqueur (clé) à sa valeur.
        $req->execute([':libelle' => $libelle, ':niveau' => $niveauUrgence]);
        // lastInsertId() : récupère l'ID auto-incrémenté généré par la DB
        // Il renvoie une chaîne (string) : on la convertit en entier avec le "cast" (int)
        // pour respecter le type de retour ": int" déclaré dans la signature.
        return (int) self::getPdo()->lastInsertId();
    }

    /**
     * Vérifie qu'une catégorie existe dans la base de données.
     * Utile pour valider les clés étrangères avant une insertion.
     *
     * @param int $id L'ID de la catégorie
     * @return bool true si elle existe, false sinon
     */
    public static function categorieExiste(int $id): bool
    {
        // COUNT(*) : compte le nombre de lignes (0 ou 1 ici puisque id est unique)
        // "AS nb" donne un nom (alias) à la colonne calculée pour pouvoir la lire facilement en PHP.
        $req = self::getPdo()->prepare('SELECT COUNT(*) AS nb FROM categorie WHERE id = :id');
        $req->execute([':id' => $id]);
        // fetch() : récupère une seule ligne. fetch()['nb'] accède au champ 'nb'
        // COUNT renvoie toujours exactement une ligne (même s'il n'y a aucun résultat → nb = 0),
        // donc fetch() ne renverra jamais false ici.
        // La comparaison "> 0" produit directement un booléen (true / false).
        return $req->fetch()['nb'] > 0;
    }

    // ===== TICKETS DE SUPPORT =====
    // Méthodes pour lire, créer et modifier les tickets

    /**
     * Récupère la liste de tous les tickets avec leurs détails.
     * Effectue des JOINs pour enrichir les données :
     * - Récupère le nom de la catégorie (pas seulement son ID)
     * - Récupère le nom et prénom de l'auteur
     *
     * Ordonne par date de création décroissante (plus récents en premier).
     *
     * @return array Tableau de tickets enrichis
     */
    public static function getLesTickets(): array
    {
        // Alias de table : t = ticket, c = categorie, u = utilisateur
        // Raccourcit l'écriture de la requête
        //
        // JOIN (= INNER JOIN) : relie deux tables grâce à une clé étrangère.
        //   "JOIN categorie c ON t.id_categorie = c.id"
        //   → pour chaque ticket, on va chercher LA catégorie dont l'id correspond à t.id_categorie.
        // Un INNER JOIN ne garde que les lignes qui ont une correspondance des deux côtés.
        //
        // Alias de colonne (AS) : plusieurs tables ont une colonne "nom" ou "libelle" ;
        // on les renomme (auteur_nom, categorie…) pour obtenir des clés JSON claires et sans collision.
        //
        // Pourquoi un INNER JOIN suffit ici ? Parce que id_categorie et id_auteur sont NOT NULL
        // et protégés par des clés étrangères : chaque ticket a FORCÉMENT une catégorie et un auteur.
        // Aucun ticket ne peut donc "disparaître" du résultat à cause du JOIN.
        //
        // La description n'est pas sélectionnée ici : dans une LISTE, on évite de transférer
        // des textes longs inutiles (elle est récupérée dans getUnTicket()).
        $sql = 'SELECT t.id, t.titre, t.statut, t.date_creation,
                       c.libelle AS categorie, c.niveau_urgence,
                       u.nom AS auteur_nom, u.prenom AS auteur_prenom
                FROM ticket t
                JOIN categorie c ON t.id_categorie = c.id
                JOIN utilisateur u ON t.id_auteur = u.id
                ORDER BY t.date_creation DESC';
        return self::getPdo()->query($sql)->fetchAll();
    }

    /**
     * Récupère un ticket spécifique par son ID.
     * Retourne null si le ticket n'existe pas.
     *
     * @param int $id L'ID du ticket
     * @return array|null Les détails du ticket, ou null
     */
    public static function getUnTicket(int $id): ?array
    {
        // Même requête que getLesTickets(), avec deux différences :
        //  - on ajoute t.description (vue "détail")
        //  - on filtre sur un seul ticket avec WHERE t.id = :id
        // Comme $id vient de l'URL (donc de l'utilisateur), on utilise prepare() + execute().
        $sql = 'SELECT t.id, t.titre, t.description, t.statut, t.date_creation,
                       c.libelle AS categorie, c.niveau_urgence,
                       u.nom AS auteur_nom, u.prenom AS auteur_prenom
                FROM ticket t
                JOIN categorie c ON t.id_categorie = c.id
                JOIN utilisateur u ON t.id_auteur = u.id
                WHERE t.id = :id';
        $req = self::getPdo()->prepare($sql);
        $req->execute([':id' => $id]);
        // fetch() (et non fetchAll()) : on attend au plus UNE ligne, puisque id est la clé primaire.
        $ticket = $req->fetch();
        // fetch() retourne false si aucune ligne n'est trouvée
        // On traduit false en null pour plus de clarté (pas de ticket = null)
        // Syntaxe "condition ? siVrai : siFaux" = opérateur ternaire (un if/else en une ligne).
        // Le contrôleur pourra alors tester "if ($ticket === null)" et répondre 404 Not Found.
        return $ticket === false ? null : $ticket;
    }

    /**
     * Crée un nouveau ticket.
     * Le statut initial est défini par défaut à 'nouveau' dans la base de données
     * (colonne : statut ENUM('nouveau','en_cours','resolu') NOT NULL DEFAULT 'nouveau').
     *
     * @param string $titre Titre du ticket (ex: "Page d'accueil ne charge pas")
     * @param string $description Description détaillée du problème
     * @param int $idCategorie ID de la catégorie du ticket (doit exister)
     * @param int $idAuteur ID de l'utilisateur qui crée le ticket
     * @return int L'ID du ticket nouvellement créé
     */
    public static function ajouterTicket(string $titre, string $description, int $idCategorie, int $idAuteur): int
    {
        // On ne précise ni "id", ni "statut", ni "date_creation" dans l'INSERT :
        //  - id            → généré par AUTO_INCREMENT
        //  - statut        → valeur DEFAULT de la colonne ('nouveau')
        //  - date_creation → DEFAULT CURRENT_TIMESTAMP (date/heure courante du serveur MySQL)
        // C'est la base de données qui remplit ces champs : moins de risque d'oubli côté PHP.
        $req = self::getPdo()->prepare(
            'INSERT INTO ticket (titre, description, id_categorie, id_auteur)
             VALUES (:titre, :description, :idCategorie, :idAuteur)'
        );
        // Un marqueur par valeur : l'ordre dans le tableau n'a pas d'importance,
        // c'est le NOM du marqueur qui fait le lien avec la requête.
        $req->execute([
            ':titre' => $titre,
            ':description' => $description,
            ':idCategorie' => $idCategorie,
            ':idAuteur' => $idAuteur,
        ]);
        // On renvoie l'id créé : le contrôleur pourra le renvoyer au client (réponse 201 Created).
        return (int) self::getPdo()->lastInsertId();
    }

    /**
     * Change le statut d'un ticket (ex: 'en_cours' → 'resolu').
     *
     * Cycle de vie d'un ticket :  nouveau  →  en_cours  →  resolu
     *
     * La colonne statut est de type ENUM : MySQL n'accepte QUE les valeurs listées
     * ('nouveau', 'en_cours', 'resolu'). Une valeur inconnue (ex : 'ferme') provoque une erreur
     * (avec le mode SQL strict, activé par défaut sur les MySQL récents).
     *
     * @param int $id ID du ticket
     * @param string $statut Nouveau statut : 'nouveau', 'en_cours' ou 'resolu'
     * @return void
     */
    public static function changerStatutTicket(int $id, string $statut): void
    {
        // UPDATE : modifie les lignes existantes (contrairement à INSERT qui en crée de nouvelles)
        // ⚠️ Le WHERE est INDISPENSABLE : sans lui, TOUS les tickets de la table seraient modifiés !
        // La validation de $statut (valeur autorisée ou non) est du ressort du contrôleur, pas du DAO.
        $req = self::getPdo()->prepare('UPDATE ticket SET statut = :statut WHERE id = :id');
        $req->execute([':statut' => $statut, ':id' => $id]);
        // ": void" → la méthode ne renvoie rien (pas de "return valeur").
    }

    // ===== INTERVENTIONS SUR LES TICKETS =====
    // Méthodes pour lire et créer les interventions (réponses/actions sur les tickets)

    /**
     * Récupère toutes les interventions (actions/commentaires) sur un ticket.
     * Ordonne chronologiquement (anciennes → récentes).
     *
     * @param int $idTicket ID du ticket
     * @return array Tableau des interventions
     */
    public static function getLesInterventionsDuTicket(int $idTicket): array
    {
        // Relation "un ticket → plusieurs interventions" (relation 1,N) :
        // la table intervention contient une clé étrangère id_ticket qui pointe vers ticket.id.
        // Pour obtenir les interventions d'UN ticket, on filtre donc sur cette clé étrangère.
        //
        // Le JOIN avec utilisateur permet d'afficher QUI a écrit l'intervention (nom, prénom)
        // au lieu d'un simple numéro id_utilisateur.
        //
        // ":idTicket" est un paramètre nommé : sa valeur sera fournie dans execute().
        // ORDER BY sans DESC = ordre croissant → du plus ancien au plus récent,
        // comme un fil de discussion qu'on lit de haut en bas.
        $sql = 'SELECT i.id, i.contenu, i.date_intervention, i.cloture_ticket,
                       u.nom, u.prenom
                FROM intervention i
                JOIN utilisateur u ON i.id_utilisateur = u.id
                WHERE i.id_ticket = :idTicket
                ORDER BY i.date_intervention';
        $req = self::getPdo()->prepare($sql);
        // La clé ':idTicket' doit correspondre EXACTEMENT au marqueur écrit dans la requête
        // (même orthographe, même casse), sinon PDO lève une exception.
        $req->execute([':idTicket' => $idTicket]);
        // Un ticket sans intervention donne simplement un tableau vide [].
        return $req->fetchAll();
    }

    /**
     * Ajoute une intervention (réponse) à un ticket.
     * Une intervention peut optionnellement clôturer le ticket.
     *
     * @param string $contenu Texte de l'intervention
     * @param bool $clotureTicket Si true, le ticket sera marqué comme résolu
     * @param int $idTicket ID du ticket
     * @param int $idUtilisateur ID de l'utilisateur qui intervient
     * @return int L'ID de l'intervention créée
     */
    public static function ajouterIntervention(string $contenu, bool $clotureTicket, int $idTicket, int $idUtilisateur): int
    {
        // Cette méthode enregistre seulement l'intervention.
        // Le changement de statut du ticket (si $clotureTicket vaut true) se fait par un appel
        // séparé à changerStatutTicket() : chaque méthode du DAO fait UNE seule chose.
        $req = self::getPdo()->prepare(
            'INSERT INTO intervention (contenu, cloture_ticket, id_ticket, id_utilisateur)
             VALUES (:contenu, :cloture, :idTicket, :idUtilisateur)'
        );
        // (int) $clotureTicket : convertit le booléen en entier (true → 1, false → 0)
        // car en MySQL le type BOOLEAN n'est qu'un alias de TINYINT(1) : la colonne stocke 0 ou 1.
        $req->execute([
            ':contenu' => $contenu,
            ':cloture' => (int) $clotureTicket,
            ':idTicket' => $idTicket,
            ':idUtilisateur' => $idUtilisateur,
        ]);
        return (int) self::getPdo()->lastInsertId();
    }

    // ===== UTILISATEURS =====
    // Méthodes pour lire, créer et modifier les comptes utilisateurs

    /**
     * Cherche un utilisateur par son adresse email.
     * Utilisé pour l'authentification (vérifier l'identité).
     *
     * @param string $email L'adresse email
     * @return array|null Les données de l'utilisateur (y compris le mot_de_passe haché), ou null
     */
    public static function getUtilisateurParEmail(string $email): ?array
    {
        // Ici, exceptionnellement, "SELECT *" récupère AUSSI le mot de passe haché :
        // on en a besoin pour la connexion, afin de le comparer avec password_verify().
        // ⚠️ Ce résultat est réservé à un usage INTERNE : il ne doit jamais être renvoyé tel quel au client.
        // La colonne email est UNIQUE en base : on obtient donc 0 ou 1 ligne, jamais plus.
        // Le hash est stocké dans un VARCHAR(255) : un hash bcrypt fait 60 caractères,
        // mais 255 laisse de la place si PHP passe un jour à un algorithme plus long.
        $req = self::getPdo()->prepare('SELECT * FROM utilisateur WHERE email = :email');
        $req->execute([':email' => $email]);
        $utilisateur = $req->fetch();
        // Même logique que getUnTicket() : false (aucune ligne) → null.
        return $utilisateur === false ? null : $utilisateur;
    }

    /**
     * Récupère les données publiques d'un utilisateur par son ID.
     * Note: le mot_de_passe n'est pas renvoyé (sécurité).
     *
     * @param int $id L'ID de l'utilisateur
     * @return array|null Les données publiques, ou null si absent
     */
    public static function getUnUtilisateur(int $id): ?array
    {
        // SELECT * FROM utilisateur : retournerait le mot_de_passe!
        // On sélectionne explicitement les champs publics.
        // Bonne pratique générale : lister les colonnes voulues plutôt que "*".
        // Si on ajoute plus tard une colonne sensible à la table, elle ne fuitera pas par accident.
        $req = self::getPdo()->prepare(
            'SELECT id, nom, prenom, email, role, date_inscription
             FROM utilisateur WHERE id = :id'
        );
        $req->execute([':id' => $id]);
        $utilisateur = $req->fetch();
        return $utilisateur === false ? null : $utilisateur;
    }

    /**
     * Liste tous les utilisateurs avec statistiques.
     * ⚠️ JAMAIS de mot_de_passe dans une réponse d'API, même haché !
     * Ajoute un champ nb_tickets : nombre de tickets créés par chaque utilisateur.
     *
     * @return array Tableau des utilisateurs avec leurs stats
     */
    public static function getLesUtilisateurs(): array
    {
        // LEFT JOIN permet de compter les tickets même pour les utilisateurs qui n'en ont 0
        //   → avec un JOIN simple (INNER), un utilisateur sans ticket disparaîtrait du résultat.
        //   → avec LEFT JOIN, on garde TOUS les utilisateurs (table de gauche) ;
        //     les colonnes de ticket valent NULL quand il n'y a pas de correspondance.
        // GROUP BY : agrège les données par utilisateur
        //   (toutes les lignes d'un même utilisateur sont "fusionnées" en une seule)
        // COUNT(t.id) : compte le nombre de tickets (lignes) pour chaque utilisateur
        //   COUNT(colonne) ignore les NULL → un utilisateur sans ticket obtient bien 0.
        //   (COUNT(*) compterait la ligne NULL et donnerait 1 : piège classique !)
        $sql = 'SELECT u.id, u.nom, u.prenom, u.email, u.role, u.date_inscription,
                       COUNT(t.id) AS nb_tickets
                FROM utilisateur u
                LEFT JOIN ticket t ON t.id_auteur = u.id
                GROUP BY u.id, u.nom, u.prenom, u.email, u.role, u.date_inscription
                ORDER BY u.nom, u.prenom';
        return self::getPdo()->query($sql)->fetchAll();
    }

    /**
     * Modifie les informations d'un utilisateur.
     * Si motDePasse n'est pas null, le mot_de_passe est mis à jour (et re-haché).
     *
     * @param int $id ID de l'utilisateur
     * @param string $nom Nouveau nom
     * @param string $prenom Nouveau prénom
     * @param string $email Nouveau email
     * @param string|null $motDePasse Nouveau mot de passe (optionnel, null = ne pas modifier)
     * @return void
     */
    public static function modifierUtilisateur(int $id, string $nom, string $prenom, string $email, ?string $motDePasse): void
    {
        // ⚠️ email est UNIQUE en base : si l'email choisi appartient déjà à un autre utilisateur,
        // execute() lève une PDOException de code '23000' (violation de contrainte).
        // Le DAO ne la gère pas lui-même : c'est UtilisateurControleur::modifier() qui l'intercepte
        // et répond 409 Conflict (le DAO ne connaît pas le HTTP, rappel de la séparation des couches).
        //
        // Deux requêtes différentes selon que le mot de passe est modifié ou non
        // "?string" : le paramètre accepte une chaîne OU null.
        // null signifie "l'utilisateur n'a pas demandé à changer son mot de passe".
        if ($motDePasse !== null) {
            // Si mot de passe : UPDATE avec colonne mot_de_passe
            $req = self::getPdo()->prepare(
                'UPDATE utilisateur
                 SET nom = :nom, prenom = :prenom, email = :email, mot_de_passe = :mdp
                 WHERE id = :id'
            );
            // password_hash() : calcule le hash du mot de passe avec algo PASSWORD_DEFAULT (bcrypt)
            // Jamais stocker un mot de passe en clair!
            // Un hash est à sens unique : on ne peut pas retrouver le mot de passe d'origine.
            // Pour vérifier une connexion, on utilisera password_verify($saisie, $hashStocke).
            // password_hash ajoute aussi un "sel" aléatoire : deux mots de passe identiques
            // donnent deux hash différents.
            $req->execute([
                ':nom' => $nom, ':prenom' => $prenom, ':email' => $email,
                ':mdp' => password_hash($motDePasse, PASSWORD_DEFAULT), ':id' => $id,
            ]);
        } else {
            // Si pas de mot de passe : UPDATE sans modifier la colonne mot_de_passe
            // (une colonne absente du SET garde sa valeur actuelle)
            $req = self::getPdo()->prepare(
                'UPDATE utilisateur
                 SET nom = :nom, prenom = :prenom, email = :email
                 WHERE id = :id'
            );
            $req->execute([':nom' => $nom, ':prenom' => $prenom, ':email' => $email, ':id' => $id]);
        }
    }

    /**
     * Supprime un utilisateur de la base de données.
     * Les tickets et interventions de cet utilisateur sont supprimés automatiquement
     * grâce aux contraintes ON DELETE CASCADE.
     *
     * Effet "domino" défini dans gestiticket.sql :
     *   DELETE utilisateur 2
     *     ├─▶ fk_ticket_auteur (CASCADE)            : supprime les tickets dont id_auteur = 2
     *     │     └─▶ fk_intervention_ticket (CASCADE) : supprime TOUTES les interventions de ces tickets
     *     │                                            (même celles écrites par d'autres utilisateurs !)
     *     └─▶ fk_intervention_utilisateur (CASCADE) : supprime les interventions écrites par l'utilisateur 2
     *
     * À l'inverse, fk_ticket_categorie n'a PAS de ON DELETE CASCADE :
     * MySQL refuserait de supprimer une catégorie encore utilisée par un ticket (comportement RESTRICT).
     *
     * @param int $id ID de l'utilisateur à supprimer
     * @return bool true si un utilisateur a été supprimé, false s'il n'existait pas
     */
    public static function supprimerUtilisateur(int $id): bool
    {
        // DELETE : supprime les lignes (inverse de INSERT)
        // ⚠️ Comme pour UPDATE, ne jamais oublier le WHERE (sinon : table entièrement vidée).
        // ON DELETE CASCADE (défini sur les clés étrangères) : quand une ligne "parente" est supprimée,
        // les lignes "enfants" qui la référencent sont supprimées automatiquement par MySQL.
        $req = self::getPdo()->prepare('DELETE FROM utilisateur WHERE id = :id');
        $req->execute([':id' => $id]);
        // rowCount() : nombre de lignes affectées par la dernière requête
        // Si > 0 : une ligne a été supprimée. Si 0 : l'ID n'existait pas.
        // Le contrôleur peut s'en servir pour répondre 204 No Content ou 404 Not Found.
        return $req->rowCount() > 0;
    }
}
