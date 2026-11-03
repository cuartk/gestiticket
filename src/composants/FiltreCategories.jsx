// =============================================================================
// src/composants/FiltreCategories.jsx — barre de filtres (KIT TP5, PARTIE 5)
// -----------------------------------------------------------------------------
// CE QUE FAIT CE COMPOSANT
//   Il affiche un bouton « Toutes » puis un bouton par catégorie. Le bouton
//   de la catégorie choisie est mis en valeur (classe CSS "actif").
//
// IL NE STOCKE RIEN (aucun useState) : il reçoit tout par ses PROPS.
//   - categories      : le tableau des catégories, chargé par App depuis l'API
//                       ex : [ { id: 1, libelle: "Réseau", niveau_urgence: 4 }, ... ]
//   - filtreActif     : le libellé de la catégorie choisie, ou null pour "Toutes"
//   - onChangerFiltre : une FONCTION fournie par App, à appeler lors d'un clic
//
// LE PRINCIPE À RETENIR : "les données descendent, les événements remontent"
//   App (parent)  --- props : categories, filtreActif --->  FiltreCategories
//   App (parent)  <-- appel : onChangerFiltre('Réseau') ---  FiltreCategories
//   L'enfant ne modifie JAMAIS l'état du parent directement : il appelle la
//   fonction reçue, et c'est le parent (propriétaire de l'état) qui agit.
//
// Par convention, une prop qui contient une fonction "à appeler quand il se
// passe quelque chose" commence par on... (comme onClick, onChange).
// =============================================================================

// Les props arrivent dans UN objet ; on les "destructure" entre accolades (TP4).
function FiltreCategories({ categories, filtreActif, onChangerFiltre }) {
  return (
    // className="filtres" : les boutons côte à côte, avec retour à la ligne (App.css)
    <div className="filtres">

      {/* MODÈLE : le bouton "Toutes" remet le filtre à null.
          - className : un TERNAIRE (TP4)  condition ? siVrai : siFaux
              'filtre actif' si aucun filtre n'est choisi, sinon 'filtre'
              (deux classes séparées par un espace : le CSS .filtre.actif
               cible les éléments qui portent les DEUX classes)
          - onClick : une FONCTION FLÉCHÉE. Elle n'est PAS exécutée maintenant,
              mais plus tard, au clic. Elle appelle alors onChangerFiltre(null). */}
      <button
        className={filtreActif === null ? 'filtre actif' : 'filtre'}
        onClick={() => onChangerFiltre(null)}
      >
        Toutes
      </button>

      {/* Un bouton par catégorie, sur le modèle du bouton "Toutes" :
          map transforme chaque objet catégorie en un <button>. */}
      {categories.map((categorie) => (
        <button
          // TODO 1 : un identifiant UNIQUE (pas 0 pour tout le monde !)
          key={categorie.id}
          // TODO 2 : 'filtre actif' pour la catégorie choisie, 'filtre' sinon
          className={filtreActif === categorie.libelle ? 'filtre actif' : 'filtre'}
          // TODO 3 : une fonction qui appelle onChangerFiltre(...)
          onClick={() => onChangerFiltre(categorie.libelle)}
        >
          {categorie.libelle}
        </button>
      ))}
    </div>
  );
}

export default FiltreCategories;
