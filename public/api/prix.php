<?php
// Aperçu du prix pour le formulaire de commande (le prix final est recalculé à la validation).
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/commande.php';
header('Content-Type: application/json; charset=utf-8');

$menuId = filter_input(INPUT_GET, 'menu_id', FILTER_VALIDATE_INT);
$nb     = filter_input(INPUT_GET, 'nombre_personne', FILTER_VALIDATE_INT);
$km     = isset($_GET['distance_km']) && is_numeric($_GET['distance_km']) ? (float)$_GET['distance_km'] : 0.0;
$ville  = (string)($_GET['ville'] ?? '');

$stmt = db()->prepare('SELECT nombre_personne_minimum, prix_minimum FROM menu WHERE menu_id = ?');
$stmt->execute([$menuId ?: 0]);
$m = $stmt->fetch();

if (!$m || !$nb || $nb < (int)$m['nombre_personne_minimum']) {
    http_response_code(400);
    echo json_encode(['erreur' => 'Nombre de personnes inférieur au minimum du menu.']);
    exit;
}
echo json_encode(calculer_prix((float)$m['prix_minimum'], (int)$m['nombre_personne_minimum'], $nb, $ville, $km));
