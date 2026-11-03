// src/composants/Header.jsx - bandeau d'en-tête de l'application
// ===============================================================
// Header est le composant le plus simple possible :
//   - il ne reçoit AUCUNE prop (les parenthèses de la fonction sont vides),
//   - il affiche toujours le même contenu.
// On l'a quand même isolé dans son propre fichier : chaque composant = un
// fichier, c'est plus lisible et plus facile à réutiliser / modifier.

// Un composant React = une fonction dont le nom commence par une MAJUSCULE.
function Header() {
  // Le return renvoie le JSX à afficher.
  return (
    // <header> est une balise HTML "sémantique" : elle indique au navigateur
    // (et aux lecteurs d'écran) qu'il s'agit de l'en-tête de la page.
    // className="entete" → style défini dans App.css (fond sombre, flexbox).
    <header className="entete">
      {/* Titre principal de la page : un seul <h1> par page, c'est la règle. */}
      <h1>GestiTicket</h1>

      {/* Sous-titre ; App.css le rend légèrement transparent (.entete span). */}
      <span>Maintenance informatique du lycée</span>
    </header>
  );
}

// Export par défaut : permet "import Header from './composants/Header'" dans App.jsx
export default Header;
