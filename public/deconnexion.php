<?php
require_once __DIR__ . '/../includes/security.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $_SESSION = [];
    session_destroy();
}
header('Location: /index.php');
exit;
