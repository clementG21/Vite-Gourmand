# Vite & Gourmand

Application web de présentation et de commande de menus (ECF Studi - Développeur Web et Web Mobile).

## Prérequis
- PHP 8.1+ (extensions pdo_mysql, mbstring, fileinfo, mongodb)
- Composer
- MongoDB (local ou Atlas)
- MySQL 8 ou MariaDB

## Installation en local
1. Générer un hash : `php -r "echo password_hash('MotDePasse!2026', PASSWORD_DEFAULT);"` puis remplacer `REMPLACER_PAR_HASH` dans `database/vite_gourmand.sql`
2. Créer la base : `mysql -u root -p < database/vite_gourmand.sql`
3. Installer les dépendances : `composer install`
4. (Optionnel) variables d'environnement : `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `MONGO_URI`, `MONGO_DB`, `CONTACT_TO`, `APP_URL`, `MAIL_FROM`
5. (Optionnel) données de démonstration pour les statistiques : `php database/seed_mongo.php`
6. Lancer : `php -S localhost:8000 -t public`
7. Ouvrir http://localhost:8000

## Avancement
- [x] Base de données
- [x] Accueil, inscription, connexion, déconnexion
- [x] Liste des menus + filtres dynamiques, détail
- [x] Commande, espace utilisateur
- [x] Espace employé, espace administrateur, MongoDB
- [x] Contact, mentions légales, CGV, mot de passe oublié
- [x] Thème graphique, accueil, images de remplacement (à remplacer par de vraies photos dans `public/images`)
