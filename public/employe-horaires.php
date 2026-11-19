<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
$user = require_role(['employe', 'administrateur']);
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $jours = ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'];
    $maj = [];
    foreach ($jours as $j) {
        if (!empty($_POST['ferme'][$j])) { $maj[$j] = [null, null]; continue; }
        $o = $_POST['ouverture'][$j] ?? ''; $f = $_POST['fermeture'][$j] ?? '';
        if (!preg_match('/^[0-2][0-9]:[0-5][0-9]$/', $o) || !preg_match('/^[0-2][0-9]:[0-5][0-9]$/', $f) || $o >= $f) {
            $errors[] = "Horaires invalides pour $j (l'ouverture doit précéder la fermeture).";
        }
        $maj[$j] = [$o, $f];
    }
    if (!$errors) {
        $u = $pdo->prepare('UPDATE horaire SET heure_ouverture = ?, heure_fermeture = ? WHERE jour = ?');
        foreach ($maj as $j => [$o, $f]) { $u->execute([$o, $f, $j]); }
        flash('success', 'Horaires mis à jour.');
        header('Location: /employe-horaires.php'); exit;
    }
}
$horaires = $pdo->query("SELECT * FROM horaire ORDER BY FIELD(jour,'lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche')")->fetchAll();

$pageTitle = 'Horaires';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav_employe.php';
?>
<h1>Horaires d'ouverture</h1>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" style="max-width:700px">
    <?= csrf_field() ?>
    <table class="table align-middle">
        <caption class="visually-hidden">Horaires par jour</caption>
        <thead><tr><th scope="col">Jour</th><th scope="col">Ouverture</th><th scope="col">Fermeture</th><th scope="col">Fermé</th></tr></thead>
        <tbody>
        <?php foreach ($horaires as $h): $j = $h['jour']; ?>
            <tr>
                <th scope="row"><?= e(ucfirst($j)) ?></th>
                <td><label class="visually-hidden" for="o-<?= e($j) ?>">Ouverture <?= e($j) ?></label>
                    <input class="form-control" type="time" id="o-<?= e($j) ?>" name="ouverture[<?= e($j) ?>]" value="<?= e($h['heure_ouverture'] ? substr($h['heure_ouverture'], 0, 5) : '09:00') ?>"></td>
                <td><label class="visually-hidden" for="f-<?= e($j) ?>">Fermeture <?= e($j) ?></label>
                    <input class="form-control" type="time" id="f-<?= e($j) ?>" name="fermeture[<?= e($j) ?>]" value="<?= e($h['heure_fermeture'] ? substr($h['heure_fermeture'], 0, 5) : '18:00') ?>"></td>
                <td><input class="form-check-input" type="checkbox" id="x-<?= e($j) ?>" name="ferme[<?= e($j) ?>]" value="1" <?= $h['heure_ouverture'] === null ? 'checked' : '' ?>>
                    <label class="form-check-label" for="x-<?= e($j) ?>">Fermé le <?= e($j) ?></label></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <button class="btn btn-vg" type="submit">Enregistrer</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
