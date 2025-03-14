# Jeu de Dames en Ligne

Un jeu de dames en ligne avec des fonctionnalités de matchmaking, gestion de tour, IA et interface utilisateur moderne.

## 📌 Présentation du Projet

Ce projet est un **jeu de dames en ligne** complet développé avec PHP et MySQL, doté d'une interface utilisateur moderne utilisant Tailwind CSS. Le jeu respecte toutes les règles officielles du jeu de dames international et offre plusieurs modes de jeu.

### Fonctionnalités principales
- **Système de matchmaking** pour trouver des adversaires en ligne
- **Mode contre l'IA** pour s'entraîner à tout moment
- **Plateau interactif** avec visualisation des mouvements possibles
- **Suivi des parties en cours et terminées**
- **Promotion en dames** lorsqu'un pion atteint la dernière rangée
- **Prises obligatoires** selon les règles officielles
- **Authentification sécurisée** des utilisateurs

## 🛠️ Technologies Utilisées

| **Technologie**      | **Utilisation** |
|----------------------|------------------------------------------------------|
| **PHP**              | Gestion du jeu, serveur et base de données           |
| **MySQL**            | Stockage des parties, joueurs et mouvements          |
| **Tailwind CSS**     | Interface utilisateur moderne et responsive          |
| **JavaScript**       | Interactivité du plateau et validation côté client   |
| **Fetch API**        | Communication asynchrone avec le serveur             |
| **PDO**              | Connexion sécurisée à la base de données             |

## 🚀 Installation

### Prérequis
- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Un serveur web (Apache, Nginx)

### Installation manuelle
1. Clonez ce dépôt sur votre serveur web
   ```bash
   git clone https://github.com/votre-utilisateur/jeu-dames.git
   ```

2. Importez le fichier SQL dans votre base de données
   ```bash
   mysql -u username -p database_name < backend/db/db.sql
   ```

3. Configurez les informations de connexion dans `.env` ou `backend/includes/config.php`

4. Démarrez le serveur PHP
   ```bash
   php -S localhost:8000 -t public
   ```

5. Accédez au jeu via `http://localhost:8000`

## 📂 Structure du Projet

```
/
├── backend/                   # Code serveur (non accessible par le web)
│   ├── controllers/           # Contrôleurs (logique métier)
│   │   ├── AuthController.php # Gestion de l'authentification
│   │   ├── GameController.php # Gestion des parties et mouvements
│   │   └── MatchmakingController.php # Gestion de la file d'attente
│   ├── db/                    # Accès à la base de données
│   │   ├── Database.php       # Classe de connexion à la base de données
│   │   └── db.sql             # Script de création de la base de données
│   ├── includes/              # Fichiers inclus communs
│   │   ├── config.php         # Configuration globale
│   │   ├── header.php         # En-tête HTML commun
│   │   ├── footer.php         # Pied de page HTML commun
│   │   └── session.php        # Gestion des sessions
│   ├── logs/                  # Journaux d'erreurs et de débogage
│   │   └── php_errors.log     # Journal des erreurs PHP
│   └── models/                # Modèles de données
│       ├── Game.php           # Modèle pour les parties
│       └── User.php           # Modèle pour les utilisateurs
├── public/                    # Fichiers accessibles par le web
│   ├── api/                   # Points d'entrée API
│   │   └── game/              # API pour le jeu
│   │       ├── create_bot_game.php # Création d'une partie contre l'IA
│   │       ├── move.php       # Gestion des mouvements de pièces
│   │       ├── queue.php      # Gestion de la file d'attente
│   │       └── status.php     # Vérification du statut de la partie
│   ├── assets/                # Ressources statiques
│   │   ├── css/               # Feuilles de style CSS
│   │   │   └── style.css      # Styles personnalisés
│   │   ├── js/                # Scripts JavaScript
│   │   └── images/            # Images et icônes
│   ├── auth/                  # Pages d'authentification
│   │   ├── login.php          # Connexion
│   │   ├── logout.php         # Déconnexion
│   │   └── register.php       # Inscription
│   ├── errors/                # Pages d'erreur
│   │   └── 404.php            # Page non trouvée
│   ├── game/                  # Pages de jeu
│   │   ├── board.php          # Plateau de jeu
│   │   └── play.php           # Liste des parties et options de jeu
│   ├── pages/                 # Pages informatives
│   │   ├── about.php          # À propos
│   │   ├── faq.php            # FAQ
│   │   └── help.php           # Aide
│   ├── .htaccess              # Configuration du serveur Apache
│   └── index.php              # Point d'entrée principal
└── README.md                  # Documentation du projet
```

## 🎮 Guide de Jeu

### 1. Création de compte et connexion
- Rendez-vous sur la page d'accueil et cliquez sur "Inscription"
- Remplissez le formulaire avec un nom d'utilisateur, un email et un mot de passe
- Connectez-vous avec vos identifiants

### 2. Jouer une partie
- **Contre l'IA** : Cliquez sur "Commencer une partie contre l'IA" pour jouer immédiatement
- **Contre un joueur** : Cliquez sur "Rejoindre la file d'attente" pour être jumelé avec un autre joueur
- **Parties en cours** : Consultez la liste de vos parties actives et cliquez sur une partie pour la continuer

### 3. Règles du jeu
- Les pions se déplacent en diagonale vers l'avant
- Les dames (pions couronnés) peuvent se déplacer en diagonale dans toutes les directions
- La prise est obligatoire
- Un pion qui atteint la dernière rangée est promu en dame
- Le joueur qui capture toutes les pièces adverses ou bloque tous ses mouvements gagne la partie

## 🔄 Évolution du Projet

### Fonctionnalités implémentées
- ✅ Système d'authentification complet
- ✅ Création et gestion de parties
- ✅ Mode de jeu contre l'IA
- ✅ Plateau de jeu interactif
- ✅ Visualisation des mouvements possibles
- ✅ Validation des règles du jeu
- ✅ Promotion en dame
- ✅ Prises obligatoires
- ✅ Suivi des parties en cours et historique
- ✅ Possibilité d'abandonner une partie

### Améliorations récentes
- ✅ Correction des problèmes de déplacement des pièces
- ✅ Amélioration de l'interface utilisateur
- ✅ Optimisation des requêtes à la base de données
- ✅ Implémentation d'indicateurs visuels pour les mouvements possibles
- ✅ Ajout de logs détaillés pour le débogage

### Fonctionnalités à venir
- [ ] Chat en jeu entre les joueurs
- [ ] Système de classement ELO
- [ ] Mode spectateur pour observer des parties
- [ ] Replay des parties terminées
- [ ] Notifications en temps réel
- [ ] PWA (Progressive Web App) pour utilisation hors ligne
- [ ] Différents niveaux de difficulté pour l'IA
- [ ] Mode tournoi

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à :
1. Forker le projet
2. Créer une branche pour votre fonctionnalité (`git checkout -b feature/ma-fonctionnalite`)
3. Committer vos changements (`git commit -m 'Ajout de ma nouvelle fonctionnalité'`)
4. Pousser sur la branche (`git push origin feature/ma-fonctionnalite`)
5. Ouvrir une Pull Request

## 📜 Licence

Ce projet est sous licence MIT. Voir le fichier LICENSE pour plus de détails.

## 📞 Contact

Pour toute question ou suggestion, n'hésitez pas à ouvrir une issue sur GitHub.
