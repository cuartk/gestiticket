// =============================================================================
// App.jsx — Composant racine de GestiTicket (KIT DE DÉMARRAGE TP5)
// -----------------------------------------------------------------------------
// RÔLE DE CE FICHIER
//   App est le composant "racine" : main.jsx l'affiche dans la <div id="root">
//   de index.html (revoir la chaîne de démarrage du TP4). Tout ce qui apparaît
//   à l'écran part d'ici.
//
// POINT DE DÉPART COMMUN
//   Ce fichier est la solution du BONUS du TP4 : les cartes sont produites par
//   tickets.map(...), mais les données viennent encore de donnees.js, c'est-à-
//   dire qu'elles sont écrites "en dur" dans le code.
//
// OBJECTIF DU TP5
//   Que ces données viennent de VOTRE API (TP3), via fetch. Pour cela, il faut
//   trois notions nouvelles :
//     - l'ÉTAT (useState)  : une donnée qui change et redessine l'écran ;
//     - l'EFFET (useEffect): du code lancé APRÈS l'affichage (l'appel réseau) ;
//     - le SERVICE api.js  : l'endroit unique où l'on parle à l'API.
//
// COMMENT TRAVAILLER
//   Les repères « PARTIE 1 », « PARTIE 4 » et « PARTIE 5 » indiquent OÙ
//   intervenir. Le code à écrire est donné dans l'énoncé, étape par étape :
//   ne sautez pas d'étape, enregistrez (Ctrl+S) et regardez le navigateur
//   après chacune.
// =============================================================================

// --- Imports -----------------------------------------------------------------
// Un import va chercher quelque chose qu'un AUTRE fichier a exporté.
//   - entre accolades { }  : un export NOMMÉ  (export const ... / export function ...)
//   - sans accolades       : l'export PAR DÉFAUT (export default ...)

// (ce sont deux "hooks" : des fonctions fournies par React, nom en use...)
import { useState, useEffect } from "react";

// Nos composants (écrits au TP4). Le chemin './composants/...' part du
// dossier de CE fichier (src/). L'extension .jsx peut être omise.
import Header from "./composants/Header";
import TicketCard from "./composants/TicketCard";

import FiltreCategories from "./composants/FiltreCategories";

import { getTickets, getCategories } from "./services/api";

// Avec Vite, importer un fichier CSS suffit pour l'appliquer à toute la page.
import "./App.css";

function App() {
  // ===========================================================================
  // 1) LA LOGIQUE : du JavaScript classique, exécuté AVANT le return.
  //    ATTENTION : tout ce bloc est ré-exécuté à CHAQUE rendu (chaque fois
  //    que React redessine le composant). C'est pour cela qu'on ne peut pas
  //    y mettre directement un fetch (boucle infinie).
  // ===========================================================================

  // COMMENT LIRE  const [valeur, setValeur] = useState(départ);
  //   useState renvoie un tableau de DEUX cases, qu'on "déstructure" :
  //     - valeur    : la donnée actuelle (à LIRE, jamais à modifier à la main) ;
  //     - setValeur : la fonction pour la CHANGER → React redessine l'écran.
  //   Les noms sont libres, mais la convention est  truc / setTruc.
  //   Le paramètre "départ" n'est utilisé qu'au tout premier rendu.

  // tickets (départ : []), chargement (départ : true), erreur (départ : null).
  
  // [] et surtout pas null : au 1er rendu les données ne sont pas encore
  // arrivées, et un tableau vide s'affiche sans erreur (length vaut 0).
  const [tickets, setTickets] = useState([]);

  // true : la requête part dès le démarrage, on est donc "en train de charger"
  const [chargement, setChargement] = useState(true);

  // null : aucune erreur pour l'instant (sinon : le message à afficher)
  const [erreur, setErreur] = useState(null);

  // La liste des catégories : [] au départ, comme les tickets
  const [categories, setCategories] = useState([]);

  // La catégorie choisie : null = "toutes" (aucun filtre)
  const [filtreActif, setFiltreActif] = useState(null);

  // useEffect(fonction, dépendances) : la fonction s'exécute APRÈS l'affichage.
  useEffect(() => {
    // (1) La fonction de useEffect ne peut PAS être "async" elle-même :
    //     on déclare donc une fonction async À L'INTÉRIEUR...
    async function charger() {
      try {
        // On TENTE l'appel réseau (api.js → proxy → Apache → PHP → MySQL)
        // Promise.all lance les deux requêtes EN MÊME TEMPS et attend que les
        // deux soient finies. Elle renvoie un tableau de résultats, dans le
        // MÊME ORDRE que les appels : on le déstructure comme avec useState.
        // Si UNE seule requête échoue, on saute directement dans le catch.
        const [lesTickets, lesCategories] = await Promise.all([
          getTickets(), // résultat n°1 → lesTickets
          getCategories(), // résultat n°2 → lesCategories
        ]);
        // succès : on range les données → React redessine
        setTickets(lesTickets);
        setCategories(lesCategories);
      } catch (err) {
        // échec : message lancé par api.js (throw)
        setErreur(err.message);
      } finally {
        // dans TOUS les cas : le chargement est terminé
        setChargement(false);
      }
    }
    // (2) ...puis on l'appelle tout de suite (avec les parenthèses !)
    charger();

    // (3) [] = "une seule fois, après le premier affichage".
    //     SANS ce tableau : boucle infinie (rendu → effet → set → rendu...)
  }, []);

  // La liste affichée est DÉRIVÉE de l'état : ce n'est PAS un état de plus
  // (elle est recalculée à chaque rendu).
  // Écriture "ternaire" :  condition ? valeurSiVrai : valeurSiFaux
  //   C'est un if/else en une seule expression, qui PRODUIT une valeur
  //   (ici rangée dans ticketsAffiches).
  const ticketsAffiches =
    filtreActif === null
      ? // aucun filtre : tout
        tickets
      : // sinon : la catégorie choisie
        tickets.filter((ticket) => ticket.categorie === filtreActif);
  // filter fabrique un NOUVEAU tableau avec les éléments qui passent le test.

  // ===========================================================================
  // 2) L'AFFICHAGE : le JSX renvoyé par le composant.
  //    Rappels du TP4 : une seule racine (ici le fragment <> </>), className
  //    au lieu de class, et des accolades { } pour repasser en JavaScript.
  //
  //    LES DEUX "MODES" DU JSX (à bien comprendre !)
  //      < ... >  MODE BALISE : "affiche cet élément / ce composant".
  //               C'est le mode PAR DÉFAUT dans le return : on écrit les
  //               balises directement, SANS accolades autour
  //               (<Header />, <main>, <FiltreCategories />...).
  //      { ... }  MODE JAVASCRIPT : "calcule cette valeur et mets-la ici".
  //               Seulement quand on a besoin de JS : une variable, une
  //               condition (&&), une boucle (.map), la valeur d'une prop.
  //    On peut alterner : dans { } on peut réécrire des balises < >, et dans
  //    une balise on peut rouvrir des { } (voir le .map plus bas).
  //
  //    LES PROPS (les "attributs" d'un composant)
  //      nom="texte"     → une valeur FIXE (du simple texte) : guillemets ;
  //      nom={variable}  → une valeur JAVASCRIPT : accolades.
  //
  //    LES COMMENTAIRES
  //      entre les balises            → {/* ... */}  (il faut passer en JS)
  //      à l'intérieur d'une balise   → // ...        (entre les props)
  // ===========================================================================
  return (
    // Les parenthèses après return permettent d'écrire le JSX sur plusieurs
    // lignes. <> </> est un "fragment" : une racine invisible (pas de <div>
    // en plus dans la page), car un composant ne renvoie qu'UN seul élément.
    <>
      {/* L'en-tête (TP4) : toujours affiché, même pendant le chargement.
          Pas d'accolades autour : c'est une balise, on est en mode balise.
          Le "/" final (<Header />) ferme la balise : elle n'a pas d'enfants. */}
      <Header />

      {/* <main className="contenu"> : la zone centrale, centrée par App.css
          className="contenu" : du texte fixe → des guillemets suffisent. */}
      <main className="contenu">
        {/* {tickets.length} : on repasse en JS pour afficher un nombre.
            Sans les accolades, on verrait le texte "tickets.length" ! */}
        <h2>Tickets ({tickets.length})</h2>

        {/* Les messages, avec l'écriture   {condition && <élément>}
              condition vraie  → l'élément est affiché
              condition fausse → rien n'est affiché
            On ne peut PAS écrire de "if" dans le JSX (le if n'est pas une
            expression qui produit une valeur) : && le remplace. */}
        {chargement && <p className="info">Chargement des tickets…</p>}

        {/* Même principe. erreur vaut null (faux) tant que tout va bien ;
            sinon elle contient le message, donc elle est "vraie".
            Les parenthèses ( ) servent juste à écrire sur plusieurs lignes.
            {erreur} dans le <p> : on réaffiche le contenu de la variable. */}
        {erreur && (
          <p className="erreur">Impossible de charger les tickets : {erreur}</p>
        )}

        {/* La liste : une carte TicketCard par ticket du tableau.
            Le PING-PONG des modes :
              {           → on entre en JS pour appeler .map
              <TicketCard → la fonction fléchée renvoie une balise
              ={ticket.x} → dans la balise, on rouvre du JS pour chaque prop
            .map transforme un tableau de DONNÉES en tableau de BALISES. */}
        {ticketsAffiches.map((ticket) => (
          <TicketCard
            // key : obligatoire dans une liste, React s'en sert pour savoir
            // quelle carte est laquelle quand la liste change.
            // l'id de la base : unique et stable
            key={ticket.id}
            titre={ticket.titre}
            categorie={ticket.categorie}
            // LE PONT : nom du champ CÔTÉ API → nom de la prop
            urgence={ticket.niveau_urgence}
            statut={ticket.statut}
          />
        ))}

        {/* Le composant de filtre. Comme <Header />, c'est une simple balise :
            pas d'accolades AUTOUR. Ce sont ses PROPS qui en ont, parce que
            leurs valeurs sont des variables JavaScript.
            Les commentaires // ci-dessous sont autorisés car ils sont
            À L'INTÉRIEUR de la balise, entre les props. */}
        <FiltreCategories
          categories={categories} // DONNÉES qui descendent
          // la catégorie choisie, pour que l'enfant sache quel bouton surligner
          filtreActif={filtreActif}
          // ÉVÉNEMENT qui remonte : l'enfant appellera cette fonction
          // ATTENTION : setFiltreActif SANS parenthèses ! On DONNE la fonction
          // à l'enfant ; avec (), on l'exécuterait tout de suite.
          onChangerFiltre={setFiltreActif}
        />
      </main>
    </>
  );
}

// export default : main.jsx peut écrire  import App from './App.jsx';
export default App;