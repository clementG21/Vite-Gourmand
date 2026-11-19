<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/mail.php';
$user = require_role(['administrateur']);
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'creer_employe') {
        $email = trim($_POST['email'] ?? ''); $mdp = $_POST['password'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse mail invalide.';
        if ($pe = password_errors($mdp)) $errors[] = 'Mot de passe : il manque ' . implode(', ', $pe) . '.';
        if (!$errors) {
            $s = $pdo->prepare('SELECT 1 FROM utilisateur WHERE email = ?'); $s->execute([$email]);
            if ($s->fetch()) {
                $errors[] = 'Un compte existe déjà avec cette adresse.';
            } else {
                // Le rôle est imposé côté serveur : impossible de créer un administrateur depuis l'application
                $roleId = $pdo->query("SELECT role_id FROM role WHERE libelle = 'employe'")->fetchColumn();
                $pdo->prepare("INSERT INTO utilisateur (email, password_hash, nom, prenom, telephone, adresse_postale, code_postal, ville, role_id)
                               VALUES (?, ?, 'Employé', 'Vite & Gourmand', '-', '-', '00000', '-', ?)")
                    ->execute([$email, password_hash($mdp, PASSWORD_DEFAULT), $roleId]);
                // Le mot de passe n'est volontairement PAS envoyé par mail
                send_mail($email, 'Votre compte Vite & Gourmand',
                    "Bonjour,\n\nUn compte employé vient d'être créé pour vous sur l'application Vite & Gourmand (identifiant : $email).\n"
                    . "Pour obtenir votre mot de passe, rapprochez-vous de l'administrateur.\n\nVite & Gourmand");
                flash('success', 'Compte employé créé. Communiquez-lui son mot de passe de vive voix.');
                header('Location: /espace-admin.php'); exit;
            }
        }
    }

    if ($action === 'activer' || $action === 'desactiver') {
        // Limité aux comptes employés : l'administrateur ne peut pas se désactiver lui-même
        $pdo->prepare("UPDATE utilisateur SET actif = ? WHERE utilisateur_id = ? AND role_id = (SELECT role_id FROM role WHERE libelle = 'employe')")
            ->execute([$action === 'activer' ? 1 : 0, (int)($_POST['utilisateur_id'] ?? 0)]);
        flash('success', $action === 'activer' ? 'Compte réactivé.' : 'Compte désactivé : il ne peut plus se connecter.');
        header('Location: /espace-admin.php'); exit;
    }
}

$employes = $pdo->query("SELECT u.utilisateur_id, u.email, u.actif FROM utilisateur u JOIN role r ON r.role_id = u.role_id WHERE r.libelle = 'employe' ORDER BY u.email")->fetchAll();
$menus = $pdo->query('SELECT menu_id, titre FROM menu ORDER BY titre')->fetchAll();

$pageTitle = 'Espace administrateur';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav_employe.php';
?>
<h1>Espace administrateur</h1>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<section aria-labelledby="titre-stats" class="mb-5">
    <h2 id="titre-stats">Statistiques</h2>
    <form id="filtres-stats" class="row g-3 mb-3" aria-label="Filtrer les statistiques">
        <div class="col-md-4"><label class="form-label" for="menu_id">Menu</label>
            <select class="form-select" id="menu_id" name="menu_id"><option value="">Tous les menus</option>
                <?php foreach ($menus as $m): ?><option value="<?= (int)$m['menu_id'] ?>"><?= e($m['titre']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label" for="du">Du</label><input class="form-control" type="date" id="du" name="du"></div>
        <div class="col-md-3"><label class="form-label" for="au">Au</label><input class="form-control" type="date" id="au" name="au"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-secondary" type="reset">Réinitialiser</button></div>
    </form>
    <p id="statut-stats" role="status" aria-live="polite" class="text-muted"></p>
    <div class="row">
        <div class="col-lg-6"><canvas id="graph-commandes" role="img" aria-label="Graphique : nombre de commandes par menu"></canvas></div>
        <div class="col-lg-6"><canvas id="graph-ca" role="img" aria-label="Graphique : chiffre d'affaires par menu"></canvas></div>
    </div>
    <!-- Tableau équivalent au graphique, utile aux lecteurs d'écran (RGAA) -->
    <table class="table mt-3">
        <caption>Commandes et chiffre d'affaires par menu</caption>
        <thead><tr><th scope="col">Menu</th><th scope="col">Commandes</th><th scope="col">Chiffre d'affaires</th></tr></thead>
        <tbody id="tableau-stats"></tbody>
        <tfoot><tr><th scope="row">Total</th><td id="total-commandes"></td><td id="total-ca"></td></tr></tfoot>
    </table>
</section>

<section aria-labelledby="titre-employes" class="mb-5">
    <h2 id="titre-employes">Comptes employés</h2>
    <form method="post" class="row g-3 mb-4" style="max-width:700px">
        <?= csrf_field() ?><input type="hidden" name="action" value="creer_employe">
        <div class="col-md-6"><label class="form-label" for="email">Mail (identifiant)</label><input class="form-control" type="email" id="email" name="email" required></div>
        <div class="col-md-6"><label class="form-label" for="password">Mot de passe</label>
            <input class="form-control" type="password" id="password" name="password" autocomplete="new-password" aria-describedby="aide-mdp" required>
            <div id="aide-mdp" class="form-text">10 caractères minimum, avec majuscule, minuscule, chiffre et caractère spécial.</div></div>
        <div class="col-12"><button class="btn btn-vg" type="submit">Créer le compte employé</button></div>
    </form>
    <ul class="list-group">
    <?php foreach ($employes as $em): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><?= e($em['email']) ?> <?= $em['actif'] ? '' : '<span class="badge bg-secondary">Désactivé</span>' ?></span>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="utilisateur_id" value="<?= (int)$em['utilisateur_id'] ?>">
                <?php if ($em['actif']): ?>
                    <button class="btn btn-sm btn-outline-danger" name="action" value="desactiver" type="submit">Désactiver<span class="visually-hidden"> <?= e($em['email']) ?></span></button>
                <?php else: ?>
                    <button class="btn btn-sm btn-outline-secondary" name="action" value="activer" type="submit">Réactiver<span class="visually-hidden"> <?= e($em['email']) ?></span></button>
                <?php endif; ?></form>
        </li>
    <?php endforeach; ?>
    </ul>
</section>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="/assets/js/admin-stats.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
