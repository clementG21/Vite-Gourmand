<?php
// Données du graphique : réservé à l'administrateur. Les données viennent de MongoDB.
declare(strict_types=1);
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/stats.php';
header('Content-Type: application/json; charset=utf-8');

if ((current_user()['role'] ?? '') !== 'administrateur') {
    http_response_code(403);
    echo json_encode(['erreur' => 'Accès refusé.']);
    exit;
}
$menuId = filter_input(INPUT_GET, 'menu_id', FILTER_VALIDATE_INT) ?: null;
try {
    echo json_encode(stats_par_menu($menuId, $_GET['du'] ?? null, $_GET['au'] ?? null), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Stats MongoDB : ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['erreur' => 'Statistiques momentanément indisponibles.']);
}
