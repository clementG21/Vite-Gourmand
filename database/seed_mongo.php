<?php
// Jeu de données de démonstration pour les statistiques : php database/seed_mongo.php
require_once __DIR__ . '/../includes/stats.php';
$menus = [1 => 'Menu de Noël traditionnel', 2 => 'Menu Végétarien de saison', 3 => 'Menu Vegan Découverte', 4 => 'Menu de Pâques'];
$prixBase = [1 => 240, 2 => 120, 3 => 110, 4 => 210];
mongo_commandes()->deleteMany([]);
for ($i = 1; $i <= 40; $i++) {
    $m = random_int(1, 4);
    stats_enregistrer(1000 + $i, $m, $menus[$m], random_int(4, 15), $prixBase[$m] * random_int(1, 3), time() - random_int(0, 90) * 86400);
}
echo "40 commandes de démonstration insérées dans MongoDB.\n";
