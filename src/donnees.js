// =============================================================================
// donnees.js — Jeu d'essai GestiTicket (fourni)
// -----------------------------------------------------------------------------
// Un TABLEAU d'OBJETS : chaque objet représente un ticket, avec les mêmes
// champs que les props de TicketCard (titre, categorie, urgence, statut)
// + un identifiant unique "id" (comme la clé primaire en base de données).
//
// À quoi sert ce fichier ?
//   - Partie 4 : copiez-y les valeurs de vos quatre <TicketCard /> si besoin.
//   - BONUS    : importez le tableau dans App.jsx et affichez les cartes
//                avec tickets.map(...) au lieu de les écrire à la main.
//
// Au TP5, ces données ne seront plus écrites "en dur" : elles viendront
// de votre API (fetch). Ce fichier disparaîtra alors.
// =============================================================================

const tickets = [
  {
    id: 1,
    titre: "Pas d'accès Internet en salle B12",
    categorie: "Réseau",
    urgence: 4,            // un NOMBRE (pas de guillemets) : de 1 à 4
    statut: "en_cours",    // "nouveau", "en_cours" ou "resolu"
  },
  {
    id: 2,
    titre: "Écran qui clignote poste 7",
    categorie: "Matériel",
    urgence: 3,
    statut: "nouveau",
  },
  {
    id: 3,
    titre: "VS Code ne se lance plus",
    categorie: "Logiciel",
    urgence: 2,
    statut: "resolu",
  },
  {
    id: 4,
    titre: "Demande d'installation de Figma",
    categorie: "Autre",
    urgence: 1,
    statut: "nouveau",
  },
];

// Export par défaut : dans App.jsx, on écrira  import tickets from './donnees';
export default tickets;
