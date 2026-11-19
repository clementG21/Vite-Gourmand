<?php
// Modèle : copier ce fichier en config/local.php sur le serveur et remplir les valeurs.
// config/local.php est ignoré par git : les mots de passe ne sont jamais publiés.
return [
    'db' => [
        'host' => 'mysql-MONCOMPTE.alwaysdata.net',
        'name' => 'MONCOMPTE_vitegourmand',
        'user' => 'MONCOMPTE',
        'pass' => 'MOT_DE_PASSE_MYSQL',
    ],
    'mongo' => [
        // Chaîne de connexion MongoDB Atlas (Connect > Drivers)
        'uri' => 'mongodb+srv://UTILISATEUR:MOT_DE_PASSE@cluster0.xxxxx.mongodb.net/',
        'db'  => 'vite_gourmand',
    ],
    'contact_to' => 'votre.adresse@exemple.fr',
    'mail_from'  => 'no-reply@MONCOMPTE.alwaysdata.net',
    'app_url'    => 'https://MONCOMPTE.alwaysdata.net',
];
