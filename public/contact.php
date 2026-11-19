<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/mail.php';

$errors = [];
$old = ['titre' => '', 'description' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $k => $_) { $old[$k] = trim($_POST[$k] ?? ''); }

    // Champ piège (honeypot) invisible pour un humain : un robot le remplit, on ignore silencieusement
    if (($_POST['site_web'] ?? '') !== '') {
        flash('success', 'Votre message a bien été envoyé.');
        header('Location: /contact.php'); exit;
    }
    if ($old['titre'] === '' || mb_strlen($old['titre']) > 150) $errors[] = 'Le titre est obligatoire (150 caractères maximum).';
    if ($old['description'] === '' || mb_strlen($old['description']) > 3000) $errors[] = 'La description est obligatoire (3000 caractères maximum).';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse mail invalide.';

    if (!$errors) {
        $ok = send_mail(config()['contact_to'], 'Contact site : ' . $old['titre'],
            "Message reçu depuis le formulaire de contact.\n\nDe : {$old['email']}\nTitre : {$old['titre']}\n\n{$old['description']}",
            $old['email']);
        if ($ok) {
            flash('success', 'Votre message a bien été envoyé. Nous vous répondrons rapidement.');
            header('Location: /contact.php'); exit;
        }
        $errors[] = "Le message n'a pas pu être envoyé. Veuillez réessayer plus tard.";
    }
}

$pageTitle = 'Contact';
require __DIR__ . '/../includes/header.php';
?>
<h1>Nous contacter</h1>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" style="max-width:650px" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="titre">Titre</label>
        <input class="form-control" id="titre" name="titre" maxlength="150" required value="<?= e($old['titre']) ?>"></div>
    <div class="mb-3"><label class="form-label" for="description">Description</label>
        <textarea class="form-control" id="description" name="description" rows="6" maxlength="3000" required><?= e($old['description']) ?></textarea></div>
    <div class="mb-3"><label class="form-label" for="email">Votre adresse mail (pour vous répondre)</label>
        <input class="form-control" type="email" id="email" name="email" autocomplete="email" required value="<?= e($old['email']) ?>"></div>
    <div style="position:absolute;left:-9999px" aria-hidden="true">
        <label for="site_web">Ne pas remplir ce champ</label>
        <input type="text" id="site_web" name="site_web" tabindex="-1" autocomplete="off">
    </div>
    <button class="btn btn-vg" type="submit">Envoyer</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
