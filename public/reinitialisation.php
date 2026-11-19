<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$s = db()->prepare('SELECT utilisateur_id FROM utilisateur WHERE reset_token_hash = ? AND reset_expire_le > NOW() AND actif = 1');
$s->execute([hash('sha256', $token)]);
$uid = $token !== '' ? $s->fetchColumn() : false;

$errors = [];
if ($uid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $mdp = $_POST['password'] ?? ''; $conf = $_POST['password_confirm'] ?? '';
    if ($pe = password_errors($mdp)) $errors[] = 'Mot de passe : il manque ' . implode(', ', $pe) . '.';
    if ($mdp !== $conf) $errors[] = 'Les mots de passe ne correspondent pas.';
    if (!$errors) {
        // Le jeton est invalidé dès son utilisation : le lien ne marche qu'une fois
        db()->prepare('UPDATE utilisateur SET password_hash = ?, reset_token_hash = NULL, reset_expire_le = NULL WHERE utilisateur_id = ?')
            ->execute([password_hash($mdp, PASSWORD_DEFAULT), $uid]);
        flash('success', 'Mot de passe modifié. Vous pouvez vous connecter.');
        header('Location: /connexion.php'); exit;
    }
}

$pageTitle = 'Nouveau mot de passe';
require __DIR__ . '/../includes/header.php';
?>
<h1>Nouveau mot de passe</h1>
<?php if (!$uid): ?>
    <div class="alert alert-warning" role="alert">Ce lien est invalide ou a expiré. <a href="/mot-de-passe-oublie.php">Faire une nouvelle demande</a></div>
<?php else: ?>
    <?php if ($errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" style="max-width:420px">
        <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="mb-3"><label class="form-label" for="password">Nouveau mot de passe</label>
            <input class="form-control" type="password" id="password" name="password" autocomplete="new-password" aria-describedby="aide-mdp" required>
            <div id="aide-mdp" class="form-text">10 caractères minimum, avec une majuscule, une minuscule, un chiffre et un caractère spécial.</div></div>
        <div class="mb-3"><label class="form-label" for="password_confirm">Confirmer le mot de passe</label>
            <input class="form-control" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required></div>
        <button class="btn btn-vg" type="submit">Enregistrer</button>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
