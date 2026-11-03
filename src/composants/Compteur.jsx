// =============================================================================
// src/composants/Compteur.jsx — composant d'entraînement (PARTIE 1, temporaire)
// -----------------------------------------------------------------------------
// Un bouton qui compte ses clics. Il sert UNIQUEMENT à comprendre useState
// avant de l'utiliser "pour de vrai" (partie 4, avec les tickets).
// Vous le retirerez de App.jsx à la fin de la partie 1 ; gardez le fichier
// comme aide-mémoire.
//
// LA QUESTION DE FOND
//   Au TP4, un composant affichait des PROPS : des données reçues, figées.
//   Comment un composant peut-il SE SOUVENIR d'une valeur qui change
//   (un nombre de clics, une liste chargée, un filtre choisi) et mettre
//   l'écran à jour tout seul ? Réponse : l'ÉTAT, avec useState.
// =============================================================================

// useState est un "hook" : une fonction fournie par React dont le nom commence
// par use. Export NOMMÉ de la bibliothèque 'react' → accolades obligatoires.
import { useState } from 'react';

function Compteur() {
  // [ valeur actuelle , fonction pour la modifier ] = useState(valeur de départ)
  //   compteur    → la valeur, en LECTURE SEULE (const !)
  //   setCompteur → la SEULE façon de la changer. Elle fait DEUX choses :
  //                 1) elle enregistre la nouvelle valeur (React s'en souvient) ;
  //                 2) elle demande à React de REDESSINER le composant.
  //   useState(0) → 0 est la valeur au tout premier affichage seulement.
  //   Les crochets : useState renvoie un tableau de 2 cases, qu'on "déstructure".
  const [compteur, setCompteur] = useState(0);

  // À chaque nouveau rendu, cette fonction Compteur est ré-exécutée EN ENTIER,
  // mais useState(0) renvoie alors la valeur MÉMORISÉE (1, 2, 3...), pas 0.

  return (
    // onClick reçoit une FONCTION (fléchée) : elle sera appelée plus tard,
    // au moment du clic. Elle appelle setCompteur avec la nouvelle valeur.
    // className="compteur" : style défini dans App.css.
    <button className="compteur" onClick={() => setCompteur(compteur + 1)}>
      Clics : {compteur}
    </button>
  );
}

// export default : App.jsx pourra écrire  import Compteur from './composants/Compteur';
export default Compteur;
