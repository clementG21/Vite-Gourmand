<?php
// API JSON : liste des menus filtrée. Accessible aux visiteurs comme aux connectés.
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$where = [];
$params = [];

// Tous les paramètres sont validés puis passés en requête préparée (jamais concaténés)
function num(string $key): ?float {
    return (isset($_GET[$key]) && is_numeric($_GET[$key])) ? (float)$_GET[$key] : null;
}
function entier(string $key): ?int {
    $v = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    return ($v === false || $v === null) ? null : $v;
}

if (($v = num('prix_max')) !== null)         { $where[] = 'm.prix_minimum <= ?'; $params[] = $v; }
if (($v = num('fourchette_min')) !== null)   { $where[] = 'm.prix_minimum >= ?'; $params[] = $v; }
if (($v = num('fourchette_max')) !== null)   { $where[] = 'm.prix_minimum <= ?'; $params[] = $v; }
if (($v = entier('theme_id')) !== null)      { $where[] = 'm.theme_id = ?';      $params[] = $v; }
if (($v = entier('regime_id')) !== null)     { $where[] = 'm.regime_id = ?';     $params[] = $v; }
// Groupe de N personnes : on garde les menus dont le minimum est inférieur ou égal à N
if (($v = entier('personnes_min')) !== null) { $where[] = 'm.nombre_personne_minimum <= ?'; $params[] = $v; }

$sql = "SELECT m.menu_id, m.titre, m.description, m.nombre_personne_minimum, m.prix_minimum,
               m.quantite_restante, t.libelle AS theme, r.libelle AS regime,
               (SELECT chemin FROM menu_image i WHERE i.menu_id = m.menu_id ORDER BY i.image_id LIMIT 1) AS image,
               (SELECT texte_alternatif FROM menu_image i WHERE i.menu_id = m.menu_id ORDER BY i.image_id LIMIT 1) AS image_alt
        FROM menu m
        JOIN theme t ON t.theme_id = m.theme_id
        JOIN regime r ON r.regime_id = m.regime_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY m.titre';

$stmt = db()->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
