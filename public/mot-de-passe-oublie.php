<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/mail.php';

$envoye = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $s = db()->prepare('SELECT utilisateur_id, prenom FROM utilisateur WHERE email = ? AND actif = 1');
        $s->execute([$email]);
        if ($u = $s->fetch()) {
            // Jeton aléatoire envoyé par mail ; seule son empreinte est stockée en base (une fuite de la base ne donne pas de lien valide)
            $token = bin2hex(random_bytes(32));
            db()->prepare('UPDATE utilisateur SET reset_token_hash = ?, reset_expire_le = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE utilisateur_id = ?')
                ->execute([hash('sha256', $token), $u['utilisateur_id']]);
            $lien = rtrim(config()['app_url'], '/') . '/reinitialisation.php?token=' . $token;
            send_mail($email, 'Réinitialisation de votre mot de passe',
                "Bonjour {$u['prenom']},\n\nPour choisir un nouveau mot de passe, cliquez sur ce lien (valable 1 heure) :\n$lien\n\n"
                . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.\n\nVite & Gourmand");
        }
    }
    // Même réponse que le compte existe ou non : on ne révèle pas quelles adresses sont inscrites
    $envoye = true;
}

$pageTitle = 'Mot de passe oublié';
require __DIR__ . '/../includes/header.php';
?>
<h1>Mot de passe oublié</h1>
<?php if ($envoye): ?>
    <div class="alert alert-success" role="status">Si un compte correspond à cette adresse, un mail contenant un lien de réinitialisation vient de vous être envoyé.</div>
<?php endif; ?>
<form method="post" style="max-width:420px">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="email">Votre adresse mail</label>
        <input class="form-control" type="email" id="email" name="email" autocomplete="username" required></div>
    <button class="btn btn-vg" type="submit">Recevoir le lien</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
