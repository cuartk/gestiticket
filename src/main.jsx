// =============================================================================
// src/main.jsx — point d'entrée de l'application (inchangé depuis le TP4)
// -----------------------------------------------------------------------------
// La chaîne de démarrage (TP4) :
//   index.html  : une <div id="root"> vide + <script src="/src/main.jsx">
//   main.jsx    : React prend le contrôle de cette div et y affiche <App />
//   App.jsx     : le composant racine, d'où part tout l'affichage
// Une seule page HTML est envoyée par le serveur : "Single Page Application".
// =============================================================================
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './App.jsx'

// createRoot : React "s'installe" dans la div root ; render : il y affiche App.
createRoot(document.getElementById('root')).render(
  // StrictMode : vérifications supplémentaires en développement uniquement
  // (React peut exécuter certains effets deux fois pour détecter les erreurs :
  //  c'est pourquoi on voit parfois deux requêtes identiques au démarrage).
  <StrictMode>
    <App />
  </StrictMode>,
)
