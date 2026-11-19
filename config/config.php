<?php
// Configuration : les valeurs sensibles viennent des variables d'environnement
// ou de config/local.php (jamais commités). Voir config/local.example.php.
$config = [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'name' => getenv('DB_NAME') ?: 'vite_gourmand',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
    ],
    'mongo' => [
        'uri' => getenv('MONGO_URI') ?: 'mongodb://127.0.0.1:27017',
        'db'  => getenv('MONGO_DB') ?: 'vite_gourmand',
    ],
    'contact_to' => getenv('CONTACT_TO') ?: 'contact@vitegourmand.fr',   // boîte qui reçoit les messages du formulaire
    'mail_from' => getenv('MAIL_FROM') ?: 'no-reply@vitegourmand.fr',
    'app_url'   => getenv('APP_URL') ?: 'http://localhost:8000',
];

// Surcharges propres au serveur (identifiants de production) : prioritaires sur les valeurs ci-dessus.
if (is_file(__DIR__ . '/local.php')) {
    $config = array_replace_recursive($config, require __DIR__ . '/local.php');
}

return $config;
