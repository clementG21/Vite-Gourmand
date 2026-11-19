<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT u.utilisateur_id, u.email, u.prenom, u.password_hash, u.actif, r.libelle AS role
                           FROM utilisateur u JOIN role r ON r.role_id = u.role_id WHERE u.email = ?');
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    // Message volontairement générique : on ne révèle pas si le mail existe
    if ($u && $u['actif'] && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);   // anti fixation de session
        $_SESSION['user'] = ['id' => (int)$u['utilisateur_id'], 'email' => $u['email'], 'prenom' => $u['prenom'], 'role' => $u['role']];
        header('Location: /index.php');
        exit;
    }
    $error = 'Identifiants incorrects.';
}

$pageTitle = 'Connexion';
require __DIR__ . '/../includes/header.php';
?>
<h1>Connexion</h1>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" style="max-width:420px">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="email">Adresse mail</label>
        <input class="form-control" id="email" name="email" type="email" autocomplete="username" required></div>
    <div class="mb-3"><label class="form-label" for="password">Mot de passe</label>
        <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required></div>
    <button class="btn btn-vg" type="submit">Se connecter</button>
    <p class="mt-3"><a href="/mot-de-passe-oublie.php">Mot de passe oublié ?</a></p>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
