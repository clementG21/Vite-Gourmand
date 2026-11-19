-- =====================================================================
-- Vite & Gourmand - Base de données relationnelle (MySQL 8 / MariaDB)
-- Création du schéma + jeu de données
-- =====================================================================

DROP DATABASE IF EXISTS vite_gourmand;
CREATE DATABASE vite_gourmand CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vite_gourmand;

-- ---------------------------------------------------------------------
-- 1. Tables de référence
-- ---------------------------------------------------------------------
CREATE TABLE role (
    role_id  INT AUTO_INCREMENT PRIMARY KEY,
    libelle  VARCHAR(30) NOT NULL UNIQUE          -- utilisateur, employe, administrateur
);

CREATE TABLE theme (
    theme_id INT AUTO_INCREMENT PRIMARY KEY,
    libelle  VARCHAR(50) NOT NULL UNIQUE          -- Noël, Pâques, Classique, Événement
);

CREATE TABLE regime (
    regime_id INT AUTO_INCREMENT PRIMARY KEY,
    libelle   VARCHAR(50) NOT NULL UNIQUE         -- Classique, Végétarien, Vegan, ...
);

CREATE TABLE allergene (
    allergene_id INT AUTO_INCREMENT PRIMARY KEY,
    libelle      VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE horaire (
    horaire_id     INT AUTO_INCREMENT PRIMARY KEY,
    jour           ENUM('lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche') NOT NULL UNIQUE,
    heure_ouverture TIME NULL,                    -- NULL = fermé
    heure_fermeture TIME NULL
);

-- ---------------------------------------------------------------------
-- 2. Utilisateurs
-- ---------------------------------------------------------------------
CREATE TABLE utilisateur (
    utilisateur_id INT AUTO_INCREMENT PRIMARY KEY,
    email          VARCHAR(150) NOT NULL UNIQUE,  -- = username
    password_hash  VARCHAR(255) NOT NULL,         -- hash bcrypt / argon2, jamais le mot de passe
    nom            VARCHAR(80)  NOT NULL,
    prenom         VARCHAR(80)  NOT NULL,
    telephone      VARCHAR(20)  NOT NULL,
    adresse_postale VARCHAR(255) NOT NULL,
    code_postal    VARCHAR(10)  NOT NULL,
    ville          VARCHAR(100) NOT NULL,
    pays           VARCHAR(60)  NOT NULL DEFAULT 'France',
    actif          BOOLEAN NOT NULL DEFAULT TRUE, -- FALSE = compte employé désactivé
    reset_token_hash VARCHAR(255) NULL,           -- réinitialisation du mot de passe
    reset_expire_le  DATETIME NULL,
    cree_le        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    role_id        INT NOT NULL,
    CONSTRAINT fk_utilisateur_role FOREIGN KEY (role_id) REFERENCES role(role_id)
);

-- ---------------------------------------------------------------------
-- 3. Menus, plats, allergènes
-- ---------------------------------------------------------------------
CREATE TABLE menu (
    menu_id                 INT AUTO_INCREMENT PRIMARY KEY,
    titre                   VARCHAR(150) NOT NULL,
    description             TEXT NOT NULL,
    nombre_personne_minimum INT NOT NULL CHECK (nombre_personne_minimum > 0),
    prix_minimum            DECIMAL(8,2) NOT NULL CHECK (prix_minimum >= 0), -- prix pour le nombre minimum
    conditions_menu         TEXT NOT NULL,        -- délai de commande, stockage...
    quantite_restante       INT NOT NULL DEFAULT 0 CHECK (quantite_restante >= 0),
    theme_id                INT NOT NULL,
    regime_id               INT NOT NULL,
    CONSTRAINT fk_menu_theme  FOREIGN KEY (theme_id)  REFERENCES theme(theme_id),
    CONSTRAINT fk_menu_regime FOREIGN KEY (regime_id) REFERENCES regime(regime_id)
);

CREATE TABLE menu_image (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    menu_id  INT NOT NULL,
    chemin   VARCHAR(255) NOT NULL,
    texte_alternatif VARCHAR(255) NOT NULL,       -- exigé par le RGAA
    CONSTRAINT fk_image_menu FOREIGN KEY (menu_id) REFERENCES menu(menu_id) ON DELETE CASCADE
);

CREATE TABLE plat (
    plat_id     INT AUTO_INCREMENT PRIMARY KEY,
    titre       VARCHAR(150) NOT NULL,
    type_plat   ENUM('entree','plat','dessert') NOT NULL,
    description TEXT NULL
);

-- Un plat peut être dans plusieurs menus, un menu a plusieurs plats
CREATE TABLE menu_plat (
    menu_id INT NOT NULL,
    plat_id INT NOT NULL,
    PRIMARY KEY (menu_id, plat_id),
    CONSTRAINT fk_mp_menu FOREIGN KEY (menu_id) REFERENCES menu(menu_id) ON DELETE CASCADE,
    CONSTRAINT fk_mp_plat FOREIGN KEY (plat_id) REFERENCES plat(plat_id) ON DELETE CASCADE
);

CREATE TABLE plat_allergene (
    plat_id      INT NOT NULL,
    allergene_id INT NOT NULL,
    PRIMARY KEY (plat_id, allergene_id),
    CONSTRAINT fk_pa_plat      FOREIGN KEY (plat_id)      REFERENCES plat(plat_id) ON DELETE CASCADE,
    CONSTRAINT fk_pa_allergene FOREIGN KEY (allergene_id) REFERENCES allergene(allergene_id)
);

-- ---------------------------------------------------------------------
-- 4. Commandes
-- ---------------------------------------------------------------------
CREATE TABLE commande (
    commande_id        INT AUTO_INCREMENT PRIMARY KEY,
    numero_commande    VARCHAR(20) NOT NULL UNIQUE,
    utilisateur_id     INT NOT NULL,
    menu_id            INT NOT NULL,
    date_commande      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_prestation    DATE NOT NULL,
    heure_livraison    TIME NOT NULL,
    adresse_prestation VARCHAR(255) NOT NULL,
    code_postal_prestation VARCHAR(10) NOT NULL,
    ville_prestation   VARCHAR(100) NOT NULL,
    distance_km        DECIMAL(6,2) NOT NULL DEFAULT 0,  -- 0 si Bordeaux
    nombre_personne    INT NOT NULL CHECK (nombre_personne > 0),
    prix_menu          DECIMAL(8,2) NOT NULL,            -- après réduction éventuelle
    reduction          DECIMAL(8,2) NOT NULL DEFAULT 0,  -- montant des 10 %
    prix_livraison     DECIMAL(8,2) NOT NULL DEFAULT 0,  -- 5 EUR + 0,59 EUR/km hors Bordeaux
    statut             ENUM('en_attente','accepte','en_preparation','en_cours_livraison',
                            'livre','attente_retour_materiel','terminee','annulee')
                       NOT NULL DEFAULT 'en_attente',
    pret_materiel      BOOLEAN NOT NULL DEFAULT FALSE,
    restitution_materiel BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_commande_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id),
    CONSTRAINT fk_commande_menu FOREIGN KEY (menu_id)       REFERENCES menu(menu_id)
);

-- Historique des statuts (suivi de commande : état + date/heure)
CREATE TABLE commande_suivi (
    suivi_id    INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    statut      VARCHAR(30) NOT NULL,
    modifie_le  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_suivi_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id) ON DELETE CASCADE
);

-- Annulation / modification par un employé : contact client obligatoire
CREATE TABLE commande_annulation (
    annulation_id  INT AUTO_INCREMENT PRIMARY KEY,
    commande_id    INT NOT NULL,
    employe_id     INT NOT NULL,
    mode_contact   ENUM('gsm','mail') NOT NULL,
    motif          TEXT NOT NULL,
    annule_le      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_annul_commande FOREIGN KEY (commande_id) REFERENCES commande(commande_id),
    CONSTRAINT fk_annul_employe  FOREIGN KEY (employe_id)  REFERENCES utilisateur(utilisateur_id)
);

-- ---------------------------------------------------------------------
-- 5. Avis (un avis par commande terminée)
-- ---------------------------------------------------------------------
CREATE TABLE avis (
    avis_id        INT AUTO_INCREMENT PRIMARY KEY,
    commande_id    INT NOT NULL UNIQUE,
    utilisateur_id INT NOT NULL,
    note           TINYINT NOT NULL CHECK (note BETWEEN 1 AND 5),
    commentaire    TEXT NOT NULL,
    statut         ENUM('en_attente','valide','refuse') NOT NULL DEFAULT 'en_attente',
    cree_le        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_avis_commande FOREIGN KEY (commande_id)    REFERENCES commande(commande_id),
    CONSTRAINT fk_avis_user     FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id)
);

-- =====================================================================
-- JEU DE DONNÉES
-- =====================================================================
INSERT INTO role (libelle) VALUES ('utilisateur'), ('employe'), ('administrateur');

INSERT INTO theme (libelle) VALUES ('Noël'), ('Pâques'), ('Classique'), ('Événement');
INSERT INTO regime (libelle) VALUES ('Classique'), ('Végétarien'), ('Vegan');

INSERT INTO allergene (libelle) VALUES
 ('Gluten'), ('Crustacés'), ('Œufs'), ('Poisson'), ('Arachides'), ('Soja'),
 ('Lait'), ('Fruits à coque'), ('Céleri'), ('Moutarde'), ('Sésame'), ('Sulfites');

INSERT INTO horaire (jour, heure_ouverture, heure_fermeture) VALUES
 ('lundi','09:00','18:00'), ('mardi','09:00','18:00'), ('mercredi','09:00','18:00'),
 ('jeudi','09:00','18:00'), ('vendredi','09:00','18:00'), ('samedi','10:00','16:00'),
 ('dimanche', NULL, NULL);

-- Comptes de démonstration.
-- IMPORTANT : remplacer 'REMPLACER_PAR_HASH' par le résultat de
--   php -r "echo password_hash('MotDePasse!2026', PASSWORD_DEFAULT);"
-- (les mots de passe ne sont JAMAIS stockés en clair)
INSERT INTO utilisateur (email, password_hash, nom, prenom, telephone, adresse_postale, code_postal, ville, role_id) VALUES
 ('jose@vitegourmand.fr',  'REMPLACER_PAR_HASH', 'Admin',   'José',  '0556000001', '1 rue des Chartrons', '33000', 'Bordeaux', 3),
 ('julie@vitegourmand.fr', 'REMPLACER_PAR_HASH', 'Employe', 'Julie', '0556000002', '1 rue des Chartrons', '33000', 'Bordeaux', 2),
 ('client@example.com',    'REMPLACER_PAR_HASH', 'Dupont',  'Marie', '0600000003', '12 cours Victor Hugo', '33000', 'Bordeaux', 1);

INSERT INTO plat (titre, type_plat, description) VALUES
 ('Foie gras maison et chutney de figues', 'entree',  'Foie gras mi-cuit, pain d''épices toasté'),
 ('Velouté de potimarron',                 'entree',  'Crème de châtaigne'),
 ('Chapon rôti aux marrons',               'plat',    'Chapon fermier, jus corsé, gratin dauphinois'),
 ('Risotto aux champignons',               'plat',    'Riz arborio, parmesan, huile de truffe'),
 ('Curry de légumes et lait de coco',      'plat',    'Légumes de saison, riz basmati'),
 ('Bûche chocolat-noisette',               'dessert', 'Biscuit dacquoise, crémeux chocolat'),
 ('Salade de fruits frais',                'dessert', 'Fruits de saison, menthe'),
 ('Gigot d''agneau de sept heures',        'plat',    'Agneau confit, flageolets'),
 ('Nid de Pâques au chocolat',             'dessert', 'Mousse chocolat, œufs en chocolat');

INSERT INTO plat_allergene (plat_id, allergene_id) VALUES
 (1, 1), (1, 12), (2, 7), (2, 8), (3, 7), (4, 7), (6, 1), (6, 3), (6, 7), (6, 8),
 (8, 9), (9, 3), (9, 7);

INSERT INTO menu (titre, description, nombre_personne_minimum, prix_minimum, conditions_menu, quantite_restante, theme_id, regime_id) VALUES
 ('Menu de Noël traditionnel', 'Un repas de fête généreux avec foie gras, chapon et bûche maison.', 6, 240.00,
  'Commande à passer au moins 14 jours avant la prestation. Conserver au réfrigérateur entre 0 et 4 °C et consommer sous 24 h.', 10, 1, 1),
 ('Menu Végétarien de saison', 'Un menu gourmand sans viande ni poisson, de saison.', 4, 120.00,
  'Commande à passer au moins 7 jours avant la prestation. À consommer le jour de la livraison.', 15, 3, 2),
 ('Menu Vegan Découverte', 'Une cuisine végétale et colorée pour tous vos événements.', 4, 110.00,
  'Commande à passer au moins 5 jours avant la prestation. À conserver au frais.', 12, 4, 3),
 ('Menu de Pâques', 'Gigot d''agneau et dessert chocolaté pour votre table de Pâques.', 6, 210.00,
  'Commande à passer au moins 10 jours avant la prestation. Conservation au frais, consommation sous 48 h.', 8, 2, 1);

INSERT INTO menu_plat (menu_id, plat_id) VALUES
 (1, 1), (1, 3), (1, 6),
 (2, 2), (2, 4), (2, 7),
 (3, 2), (3, 5), (3, 7),
 (4, 1), (4, 8), (4, 9);

INSERT INTO menu_image (menu_id, chemin, texte_alternatif) VALUES
 (1, '/images/menu-noel-1.svg',    'Table de Noël dressée avec chapon rôti'),
 (2, '/images/menu-vege-1.svg',    'Risotto aux champignons dans une assiette'),
 (3, '/images/menu-vegan-1.svg',   'Curry de légumes coloré'),
 (4, '/images/menu-paques-1.svg',  'Gigot d''agneau et nid de Pâques au chocolat');
