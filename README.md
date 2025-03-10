# Projet-UI-Developement
Le module UF Développement permet de créer un jeu de dames en ligne en appliquant les compétences en développement et bases de données. Il inclut un matchmaking, une gestion de file d’attente, un plateau interactif et un suivi des parties et scores.

# 🎮 Jeu de Dames en Ligne

## 📌 Présentation du Projet
Le module UF Développement permet de créer un **jeu de dames en ligne** en appliquant des compétences en **développement logiciel** et **gestion de bases de données**.  
Ce projet inclut :
- **Un système de matchmaking** pour trouver des adversaires en ligne.
- **Une file d’attente** pour gérer les connexions des joueurs.
- **Un plateau interactif** pour jouer en ligne.
- **Un suivi des parties et des scores** via une base de données MySQL.
- **Une authentification sécurisée** pour les utilisateurs.

---

## 🛠️ Technologies Utilisées
| **Technologie**      | **Utilisation** |
|----------------------|------------------------------------------------------|
| **PHP**              | Gestion du jeu, serveur et base de données           |
| **MySQL**            | Stockage des parties, joueurs et scores              |
| **HTML**             | Structure des pages du jeu                           |
| **Tailwind CSS**     | Mise en page rapide et moderne                       |
| **JavaScript**       | Interaction du jeu (mouvements des pions, affichage) |
| **Canvas API**       | Affichage du plateau de jeu et des pions             |
| **AJAX (Fetch API)** | Communication entre PHP et le frontend               |
| **Sessions PHP**     | Gestion des connexions des joueurs                   |

---

## 📂 Structure du Projet
`````js

/jeu-dames
│── backend/
│   │── controllers/
│   │   │── AuthController.php        # Gestion de l'authentification (login, logout, inscription)
│   │   │── GameController.php        # Gestion des parties et des coups
│   │   │── MatchmakingController.php # Gestion du matchmaking
│   │── db/
│   │   │── Database.php              # Connexion à la base de données via PDO
│   │   │── schema.sql                # Structure de la base de données
│   │── includes/
│   │   │── config.php                # Configuration générale du projet
│   │   │── session.php               # Gestion des sessions utilisateurs
│   │   │── header.php                # En-tête commun
│   │   │── footer.php                # Pied de page commun
│   │── models/
│   │   │── Game.php                  # Modèle partie (mouvements, historique)
│   │   │── User.php                  # Modèle utilisateur (login, inscription)
│   │── routes.php                     # Gestion des routes (MVC)
│── public/
│   │── assets/
│   │   │── css/
│   │   │   │── tailwind.css          # Feuille de style Tailwind
│   │   │── js/
│   │   │   │── script.js             # Gestion des interactions du jeu
│   │   │── images/                    # Dossier pour stocker les images
│   │── index.php                      # Page principale (Accueil)
│── views/
│   │── errors/
│   │   │── 404.php                    # Page d'erreur 404
│   │── game/
│   │   │── board.php                   # Affichage du plateau de jeu
│   │   │── play.php                    # Interface principale de la partie
│   │── auth/
│   │   │── login.php                   # Page de connexion
│   │   │── register.php                 # Page d'inscription
│   │── pages/
│   │   │── about.php                    # À propos
│   │   │── faq.php                      # Foire aux questions
│   │   │── help.php                     # Aide
│── .env                                 # Variables d'environnement (DB, clés secrètes)
│── .gitignore                           # Fichiers à ignorer dans Git
│── README.md                            # Documentation du projet

`````
### Démarrer le serveur PHP
- Lancer un serveur local avec :
- php -S localhost:8000 -t public

Accéder au projet dans le navigateur :
🔗 http://localhost:8000/


### 🎮 Fonctionnalités du Jeu
- ✅ Gestion des Joueurs
- Inscription et connexion sécurisées avec hashage des mots de passe.
- Sessions PHP pour garder les utilisateurs connectés.
- ✅ Matchmaking Automatique
- Gestion d’une file d’attente pour trouver un adversaire disponible.
- Lancement automatique d'une partie dès que deux joueurs sont connectés.
- ✅ Plateau de Jeu Dynamique
- Affichage du plateau de dames interactif.
- Mouvements des pions gérés avec JavaScript et AJAX.
- Système de tour par tour.
- ✅ Suivi des Parties et Scores
- Stockage des parties et mise à jour des scores en base de données.
- Possibilité de revoir l’historique des parties.
- ✅ Pages Informatives

### À propos, FAQ, Aide pour les joueurs.
🛠️ Développement & Évolutions Possibles
🔹 Ajout d’un système de classement ELO 📈
🔹 Mode spectateur pour observer les parties 🕵️
🔹 Personnalisation des pions et du plateau 🎨
🔹 Intelligence artificielle pour jouer contre un bot 🤖

### 🤝 Contributeurs
👨‍💻 Etudiant(s) Développeur(s) 
📧 Contact : .....

### 📜 Licence
Ce projet est sous licence MIT. Vous pouvez l'utiliser, le modifier et le partager librement.

### 📢 Remarque Finale
Merci d’avoir consulté ce projet ! 🎉
Si vous avez des questions ou des suggestions, n’hésitez pas à me contacter.
