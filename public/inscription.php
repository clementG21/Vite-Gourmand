<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/mail.php';

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $fields = ['nom','prenom','telephone','email','adresse_postale','code_postal','ville'];
    foreach ($fields as $f) { $old[$f] = trim($_POST[$f] ?? ''); }
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    foreach ($fields as $f) { if ($old[$f] === '') $errors[] = "Le champ « $f » est obligatoire."; }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Adresse mail invalide.";
    if (!preg_match('/^[0-9+ ().-]{8,20}$/', $old['telephone'])) $errors[] = "Numéro de GSM invalide.";
    if (!preg_match('/^[0-9]{5}$/', $old['code_postal'])) $errors[] = "Code postal invalide (5 chiffres).";
    if ($pe = password_errors($password)) $errors[] = "Mot de passe : il manque " . implode(', ', $pe) . ".";
    if ($password !== $confirm) $errors[] = "Les mots de passe ne correspondent pas.";

    if (!$errors) {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT 1 FROM utilisateur WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) {
            $errors[] = "Impossible de créer ce compte avec ces informations.";
        } else {
            $roleId = $pdo->query("SELECT role_id FROM role WHERE libelle = 'utilisateur'")->fetchColumn();
            $pdo->prepare('INSERT INTO utilisateur (email, password_hash, nom, prenom, telephone, adresse_postale, code_postal, ville, role_id)
                           VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$old['email'], password_hash($password, PASSWORD_DEFAULT), $old['nom'], $old['prenom'],
                           $old['telephone'], $old['adresse_postale'], $old['code_postal'], $old['ville'], $roleId]);

            send_mail($old['email'], 'Bienvenue chez Vite & Gourmand',
                "Bonjour {$old['prenom']},\n\nVotre compte a bien été créé. Vous pouvez dès maintenant commander nos menus.\n\nL'équipe Vite & Gourmand");
            flash('success', 'Compte créé. Vous pouvez vous connecter.');
            header('Location: /connexion.php');
            exit;
        }
    }
}

$pageTitle = 'Créer un compte';
require __DIR__ . '/../includes/header.php';
?>
<h1>Créer un compte</h1>
<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert"><ul class="mb-0">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul></div>
<?php endif; ?>

<form method="post" novalidate class="row g-3" style="max-width:700px">
    <?= csrf_field() ?>
    <div class="col-md-6"><label class="form-label" for="nom">Nom</label>
        <input class="form-control" id="nom" name="nom" autocomplete="family-name" required value="<?= e($old['nom'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label" for="prenom">Prénom</label>
        <input class="form-control" id="prenom" name="prenom" autocomplete="given-name" required value="<?= e($old['prenom'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label" for="telephone">Numéro de GSM</label>
        <input class="form-control" id="telephone" name="telephone" type="tel" autocomplete="tel" required value="<?= e($old['telephone'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label" for="email">Adresse mail</label>
        <input class="form-control" id="email" name="email" type="email" autocomplete="email" required value="<?= e($old['email'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label" for="adresse_postale">Adresse postale</label>
        <input class="form-control" id="adresse_postale" name="adresse_postale" autocomplete="street-address" required value="<?= e($old['adresse_postale'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label" for="code_postal">Code postal</label>
        <input class="form-control" id="code_postal" name="code_postal" autocomplete="postal-code" required value="<?= e($old['code_postal'] ?? '') ?>"></div>
    <div class="col-md-8"><label class="form-label" for="ville">Ville</label>
        <input class="form-control" id="ville" name="ville" autocomplete="address-level2" required value="<?= e($old['ville'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label" for="password">Mot de passe</label>
        <input class="form-control" id="password" name="password" type="password" autocomplete="new-password" aria-describedby="aide-mdp" required>
        <div id="aide-mdp" class="form-text">10 caractères minimum, avec une majuscule, une minuscule, un chiffre et un caractère spécial.</div></div>
    <div class="col-md-6"><label class="form-label" for="password_confirm">Confirmer le mot de passe</label>
        <input class="form-control" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required></div>
    <div class="col-12"><button class="btn btn-vg" type="submit">Créer mon compte</button></div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
