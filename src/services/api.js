// =============================================================================
// src/services/api.js — point UNIQUE d'accès à l'API GestiTicket (KIT TP5)
// -----------------------------------------------------------------------------
// POURQUOI UN "SERVICE" ?
//   Un fetch écrit directement dans un composant, ça marche... une fois.
//   Puis on en écrit un deuxième, un dixième, et le jour où l'adresse de
//   l'API change, il faut tous les retrouver. On regroupe donc TOUS les
//   appels réseau dans ce fichier :
//     - une seule adresse à maintenir (BASE) ;
//     - une seule gestion des erreurs HTTP (getJson) ;
//     - des composants plus simples : ils appellent getTickets() sans savoir
//       comment les données arrivent (URL, méthode, format...).
//   C'est la même idée que la classe DAO du backend (TP2) : séparer
//   "où sont les données" de "comment on les affiche".
// =============================================================================

// Adresse de base de l'API, vue du NAVIGATEUR.
// '/api' et non 'http://localhost/gestiticket/api' : le navigateur appelle
// le serveur Vite (même origine, port 5173), et c'est Vite qui relaie vers
// Apache grâce au PROXY configuré dans vite.config.js (partie 3).
// Un appel direct vers le port 80 serait bloqué par le navigateur (CORS).
const BASE = '/api';

// -----------------------------------------------------------------------------
// getJson(chemin) : fait un GET sur BASE + chemin et renvoie le JSON reçu.
//   async : la fonction renvoie une PROMESSE ; à l'intérieur, await "attend"
//           la réponse du serveur sans bloquer la page.
//   Exemple : getJson('/tickets')  →  GET /api/tickets
// -----------------------------------------------------------------------------
async function getJson(chemin) {
  // 1. On envoie la requête. Les accents graves ` ` forment un gabarit de
  //    chaîne (TP4) : `${BASE}${chemin}` vaut '/api/tickets'.
  const reponse = await fetch(`${BASE}${chemin}`);

  // 2. PIÈGE CLASSIQUE : fetch ne lève PAS d'exception sur un 404 ou un 500.
  //    Il n'échoue que si le RÉSEAU échoue (serveur injoignable). Une réponse
  //    d'erreur reste une réponse reçue : c'est à nous de la tester.
  //    reponse.ok vaut true seulement pour les codes 200 à 299.
  if (!reponse.ok) {
    // L'API du TP3 renvoie ses erreurs en JSON : { "erreur": "message précis" }
    // (méthode Reponse::erreur côté PHP). On essaie de lire ce message.
    // .catch(() => ({})) : si le corps n'est PAS du JSON (page d'erreur HTML
    // d'Apache, par exemple), on obtient un objet vide au lieu de planter.
    const corps = await reponse.json().catch(() => ({}));

    // throw transforme l'erreur HTTP en EXCEPTION JavaScript : le composant
    // l'attrapera dans son try / catch (App.jsx, partie 4).
    //   corps.erreur || `Erreur ...`  → le message de l'API s'il existe,
    //                                   sinon un message générique.
    throw new Error(corps.erreur || `Erreur ${reponse.status}`);
  }

  // 3. Tout va bien : on lit le corps de la réponse et on le convertit
  //    de texte JSON en objet / tableau JavaScript.
  return reponse.json();
}

// =============================================================================
// LES FONCTIONS UTILISÉES PAR LES COMPOSANTS
// export : la fonction peut être importée ailleurs, entre accolades :
//   import { getTickets } from './services/api';
//
// Fonction fléchée : () => getJson('/tickets')  est l'écriture courte de
//   function getTickets() { return getJson('/tickets'); }
// =============================================================================

// GET /tickets : la liste de tous les tickets (avec categorie et niveau_urgence)
export const getTickets = () => getJson('/tickets');

// PARTIE 2 – étape 2 : sur le modèle EXACT de getTickets, exportez ici la
// fonction getCategories qui appelle la route '/categories'.
// (Sans elle, la partie 5 affichera « getCategories is not exported ».)
export const getCategories = () => getJson('/categories');