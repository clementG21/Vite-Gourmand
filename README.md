# Vite & Gourmand

Application web d'un traiteur-restaurant bordelais, réalisée pour l'ECF Studi « Développeur Web et Web Mobile » (19 novembre 2026).

**Vite & Gourmand** est un restaurant traditionnel situé à Bordeaux depuis 25 ans, tenu par Julie et José. Il propose une cuisine maison, pour un simple repas comme pour un événement (Noël, Pâques…), avec la possibilité de se faire livrer.

## Liens du rendu

- Application déployée : _à compléter_
- Gestion de projet (Trello / Notion / Jira) : _à compléter_
- Manuel d'utilisation, charte graphique et documentation technique : dossier `docs/` _(à compléter)_

## Fonctionnalités

### Pages publiques
- **Accueil** : courte présentation du restaurant, menus à l'honneur, puis avis clients. Un avis n'apparaît qu'après validation par un employé ou l'administrateur.
- **Nos menus** : tous les menus disponibles, avec les allergènes de chaque plat. Filtres dynamiques (prix, thème, régime, nombre de personnes) sans rechargement de page, et page de détail avec conditions du menu.
- **Contact** : formulaire qui envoie un mail au restaurant.
- **Connexion / Créer un compte**, mot de passe oublié.
- Mentions légales et conditions générales de vente.

### Compte client
- Commander un menu, avec calcul du prix (réduction selon le nombre de personnes, frais de livraison hors Bordeaux).
- Suivre sa commande (préparation, livraison…), la modifier ou l'annuler tant qu'elle est en attente.
- Donner un avis une fois la commande terminée.

### Compte employé
- Créer et gérer les menus et les plats.
- Gérer les commandes des clients et leur statut.
- Valider ou refuser les avis clients avant publication en page d'accueil.

### Compte administrateur
- Tout ce que fait un employé, plus :
- modifier les horaires du restaurant (affichés dans le pied de page) ;
- consulter les statistiques (commandes et chiffre d'affaires par menu, stockées dans MongoDB) ;
- créer les comptes employés (adresse e-mail et mot de passe).

## Technologies et choix

| Couche | Technologie | Raison |
|---|---|---|
| Front | HTML5, CSS (Bootstrap 5), JavaScript | Responsive rapidement, accessibilité de base fournie par Bootstrap |
| Back | PHP 8.1+ avec PDO | Requêtes préparées contre l'injection SQL |
| Base relationnelle | MySQL / MariaDB | Utilisateurs, menus, plats, commandes, avis |
| Base NoSQL | MongoDB | Statistiques de l'espace administrateur |

Le code back-end le plus complexe a été écrit avec l'aide d'une IA, puis relu et testé.

**Charte graphique** : bordeaux `#7b1e3a`, crème `#fff8f0` et or, pour un site épuré et lisible. Titres en Georgia, texte en police système (aucun service tiers, donc RGPD respecté). Contrastes vérifiés pour le RGAA.

## Sécurité

- Mots de passe hachés (`password_hash`), jamais stockés en clair ; 10 caractères minimum avec majuscule, minuscule, chiffre et caractère spécial.
- Requêtes SQL préparées (PDO).
- Jeton CSRF sur les formulaires.
- Sortie échappée (`e()`, `textContent`) contre le XSS.
- Contrôle d'accès par rôle ; un client ne peut lire que ses propres commandes.
- En-têtes `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`.
- Secrets lus dans les variables d'environnement, jamais commités (`.env` ignoré par git).

## Installation en local

### Prérequis
- PHP 8.1 ou plus, avec les extensions `pdo_mysql`, `mbstring`, `fileinfo`, `mongodb`
- Composer
- MySQL 8 ou MariaDB
- MongoDB (local ou Atlas)

### Étapes

1. **Récupérer le projet**
   ```bash
   git clone <url-du-depot>
   cd <dossier-du-projet>
   git checkout develop
   ```

2. **Générer le hash du mot de passe des comptes de démonstration**
   ```bash
   php -r "echo password_hash('MotDePasse!2026', PASSWORD_DEFAULT);"
   ```
   Remplacer chaque `REMPLACER_PAR_HASH` dans `database/vite_gourmand.sql` par le résultat.

3. **Créer la base et insérer les données**
   ```bash
   mysql -u root -p < database/vite_gourmand.sql
   ```
   Ce script supprime puis recrée la base `vite_gourmand`, avec ses tables et ses données de départ.

4. **Installer les dépendances PHP**
   ```bash
   composer install
   ```

5. **Variables d'environnement** (optionnel, des valeurs par défaut existent dans `config/config.php`)

   | Variable | Défaut | Rôle |
   |---|---|---|
   | `DB_HOST` | `127.0.0.1` | Hôte MySQL |
   | `DB_NAME` | `vite_gourmand` | Nom de la base |
   | `DB_USER` | `root` | Utilisateur MySQL |
   | `DB_PASS` | _(vide)_ | Mot de passe MySQL |
   | `MONGO_URI` | `mongodb://127.0.0.1:27017` | Connexion MongoDB |
   | `MONGO_DB` | `vite_gourmand` | Base MongoDB |
   | `CONTACT_TO` | `contact@vitegourmand.fr` | Boîte qui reçoit le formulaire de contact |
   | `MAIL_FROM` | `no-reply@vitegourmand.fr` | Expéditeur des mails |
   | `APP_URL` | `http://localhost:8000` | URL de l'application |

6. **Données de démonstration pour les statistiques** (optionnel)
   ```bash
   php database/seed_mongo.php
   ```

7. **Lancer le serveur depuis la racine du projet**
   ```bash
   php -S localhost:8000 -t public
   ```
   L'option `-t public` est indispensable : les chemins du site (`/assets/css/style.css`, `/images/...`) supposent que `public/` est la racine du serveur. Sinon le CSS et les images ne se chargent pas.

8. Ouvrir <http://localhost:8000>

### Comptes de démonstration

Mot de passe commun : celui utilisé à l'étape 2 (`MotDePasse!2026` dans l'exemple).

| Rôle | E-mail |
|---|---|
| Administrateur | `jose@vitegourmand.fr` |
| Employé | `julie@vitegourmand.fr` |
| Client | `client@example.com` |

> Ces comptes sont réservés au développement. À remplacer par des identifiants forts en production.

## Structure du projet

```
config/          configuration (variables d'environnement)
database/        schéma + données SQL, jeu de données MongoDB
includes/        code partagé : base de données, sécurité, mails, prix, statistiques, en-tête et pied de page
public/          racine du serveur web
  api/           points d'accès JSON (menus filtrés, prix, statistiques)
  assets/        CSS et JavaScript
  images/        images des menus et favicon
  uploads/       images envoyées par les employés (non versionnées)
```

## Organisation git

- `main` : version stable, livrable.
- `develop` : intégration, testée avant fusion dans `main`.
- `feature/<nom>` : une branche par fonctionnalité, créée depuis `develop` et fusionnée dans `develop` après test.

## Avancement

- [x] Base de données
- [x] Accueil, inscription, connexion, déconnexion
- [x] Liste des menus, filtres dynamiques, détail
- [x] Commande, espace utilisateur
- [x] Espace employé, espace administrateur, MongoDB
- [x] Contact, mentions légales, CGV, mot de passe oublié
- [x] Thème graphique et accueil
- [ ] Remplacer les images de remplacement par de vraies photos dans `public/images`
- [ ] Déploiement en ligne
- [ ] Manuel d'utilisation, charte graphique et documentation technique en PDF
