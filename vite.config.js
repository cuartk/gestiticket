// =============================================================================
// vite.config.js — configuration de Vite (CORRECTION TP5)
// -----------------------------------------------------------------------------
// Ce fichier se place à la RACINE du projet (à côté de package.json),
// PAS dans src/. Il remplace celui créé par Vite au TP4.
//
// ATTENTION : il n'est lu qu'au DÉMARRAGE de Vite. Après toute modification,
// arrêtez le serveur (Ctrl+C dans son terminal) et relancez  npm run dev.
// Sinon vous testez une configuration qui n'est pas chargée.
//
// LE PROBLÈME QUE RÉSOUT LE PROXY : le CORS
//   Le frontend est servi par Vite  : http://localhost:5173
//   L'API est servie par Apache     : http://localhost       (port 80)
//   Deux ports différents = deux ORIGINES différentes. Par sécurité, le
//   navigateur bloque un fetch d'une origine vers une autre si le serveur
//   ne l'autorise pas explicitement (erreur "blocked by CORS policy").
//
// LA SOLUTION : faire passer les appels par Vite
//   navigateur → /api/tickets (même origine : 5173, aucun blocage)
//   Vite       → relaie à Apache : http://localhost/gestiticket/api/tickets
//   Le CORS est une règle DU NAVIGATEUR : entre deux serveurs (Vite et
//   Apache), elle ne s'applique pas. Ce proxy ne sert qu'en développement.
// =============================================================================
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';   // le support de React (JSX, rechargement à chaud)

export default defineConfig({
  plugins: [react()],

  // server : réglages du serveur de développement (npm run dev)
  server: {
    proxy: {
      // Toute requête dont l'adresse commence par /api ...
      '/api': {
        // ... est relayée par Vite vers le serveur Apache (WampServer, port 80)
        target: 'http://localhost',
        // changeOrigin : Vite se présente à Apache comme venant de "localhost"
        changeOrigin: true,
        // ... en réécrivant le début de l'adresse (expression régulière :
        // ^ = "commence par", \/ = le caractère /) :
        //   /api/tickets  →  http://localhost/gestiticket/api/tickets
        // Si votre dossier dans www porte un autre nom, c'est ICI qu'on l'adapte.
        rewrite: (chemin) => chemin.replace(/^\/api/, '/gestiticket/api'),
      },
    },
  },
});
