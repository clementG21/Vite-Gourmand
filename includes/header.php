<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
$user = current_user();
$pageTitle = $pageTitle ?? 'Vite & Gourmand';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - Vite & Gourmand</title>
    <meta name="description" content="Vite & Gourmand, traiteur à Bordeaux depuis 25 ans : menus pour tous vos événements.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta name="theme-color" content="#7b1e3a">
    <link rel="icon" href="/images/favicon.svg" type="image/svg+xml">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>

<body class="d-flex flex-column min-vh-100">
    <a class="skip-link" href="#contenu">Aller au contenu principal</a>

    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-vg" aria-label="Navigation principale">
            <div class="container">
                <a class="navbar-brand" href="/index.php">Vite &amp; Gourmand</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"
                    aria-controls="nav" aria-expanded="false" aria-label="Afficher ou masquer le menu">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="nav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item"><a class="nav-link" href="/index.php">Accueil</a></li>
                        <li class="nav-item"><a class="nav-link" href="/menus.php">Nos menus</a></li>
                        <li class="nav-item"><a class="nav-link" href="/contact.php">Contact</a></li>
                    </ul>
                    <ul class="navbar-nav">
                        <?php if ($user): ?>
                            <?php if ($user['role'] === 'utilisateur'): ?>
                                <li class="nav-item"><a class="nav-link" href="/espace-utilisateur.php">Mon espace</a></li>
                            <?php elseif ($user['role'] === 'employe'): ?>
                                <li class="nav-item"><a class="nav-link" href="/espace-employe.php">Espace employé</a></li>
                            <?php else: ?>
                                <li class="nav-item"><a class="nav-link" href="/espace-admin.php">Espace administrateur</a></li>
                            <?php endif; ?>
                            <li class="nav-item">
                                <form method="post" action="/deconnexion.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-link nav-link" type="submit">Déconnexion</button>
                                </form>
                            </li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link" href="/connexion.php">Connexion</a></li>
                            <li class="nav-item"><a class="nav-link" href="/inscription.php">Créer un compte</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main id="contenu" class="container my-4 flex-grow-1">
        <?php foreach (get_flashes() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>" role="alert"><?= e($f['msg']) ?></div>
        <?php endforeach; ?>