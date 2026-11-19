<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/commande.php';

$session = require_role(['utilisateur']);
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = [];
    foreach (['nom','prenom','telephone','adresse_postale','code_postal','ville'] as $k) { $d[$k] = trim($_POST[$k] ?? ''); }
    foreach ($d as $k => $v) { if ($v === '') $errors[] = "Le champ « $k » est obligatoire."; }
    if (!preg_match('/^[0-9+ ().-]{8,20}$/', $d['telephone'])) $errors[] = "Numéro de GSM invalide.";
    if (!preg_match('/^[0-9]{5}$/', $d['code_postal'])) $errors[] = "Code postal invalide.";
    if (!$errors) {
        $pdo->prepare('UPDATE utilisateur SET nom=?, prenom=?, telephone=?, adresse_postale=?, code_postal=?, ville=? WHERE utilisateur_id=?')
            ->execute([$d['nom'], $d['prenom'], $d['telephone'], $d['adresse_postale'], $d['code_postal'], $d['ville'], $session['id']]);
        $_SESSION['user']['prenom'] = $d['prenom'];
        flash('success', 'Vos informations ont été mises à jour.');
        header('Location: /espace-utilisateur.php'); exit;
    }
}

$s = $pdo->prepare('SELECT * FROM utilisateur WHERE utilisateur_id = ?'); $s->execute([$session['id']]); $u = $s->fetch();
$s = $pdo->prepare('SELECT c.commande_id, c.numero_commande, c.date_commande, c.statut, c.prix_menu, c.prix_livraison, m.titre
                    FROM commande c JOIN menu m ON m.menu_id = c.menu_id
                    WHERE c.utilisateur_id = ? ORDER BY c.date_commande DESC');
$s->execute([$session['id']]); $commandes = $s->fetchAll();

$pageTitle = 'Mon espace';
require __DIR__ . '/../includes/header.php';
?>
<h1>Mon espace</h1>

<section aria-labelledby="titre-commandes" class="mb-5">
    <h2 id="titre-commandes">Mes commandes</h2>
    <?php if (!$commandes): ?>
        <p>Vous n'avez pas encore de commande. <a href="/menus.php">Découvrir nos menus</a></p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table">
        <caption class="visually-hidden">Liste de mes commandes</caption>
        <thead><tr><th scope="col">N°</th><th scope="col">Date</th><th scope="col">Menu</th><th scope="col">Statut</th><th scope="col">Total</th><th scope="col"><span class="visually-hidden">Action</span></th></tr></thead>
        <tbody>
        <?php foreach ($commandes as $c): ?>
            <tr>
                <td><?= e($c['numero_commande']) ?></td>
                <td><?= e(date('d/m/Y', strtotime($c['date_commande']))) ?></td>
                <td><?= e($c['titre']) ?></td>
                <td><?= e(statut_label($c['statut'])) ?></td>
                <td><?= e(eur((float)$c['prix_menu'] + (float)$c['prix_livraison'])) ?></td>
                <td><a href="/commande-detail.php?id=<?= (int)$c['commande_id'] ?>">Détail<span class="visually-hidden"> de la commande <?= e($c['numero_commande']) ?></span></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<section aria-labelledby="titre-infos">
    <h2 id="titre-infos">Mes informations personnelles</h2>
    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" class="row g-3" style="max-width:700px">
        <?= csrf_field() ?>
        <div class="col-md-6"><label class="form-label" for="nom">Nom</label>
            <input class="form-control" id="nom" name="nom" required value="<?= e($u['nom']) ?>"></div>
        <div class="col-md-6"><label class="form-label" for="prenom">Prénom</label>
            <input class="form-control" id="prenom" name="prenom" required value="<?= e($u['prenom']) ?>"></div>
        <div class="col-md-6"><label class="form-label" for="email">Mail (identifiant)</label>
            <input class="form-control" id="email" value="<?= e($u['email']) ?>" readonly></div>
        <div class="col-md-6"><label class="form-label" for="telephone">GSM</label>
            <input class="form-control" id="telephone" name="telephone" type="tel" required value="<?= e($u['telephone']) ?>"></div>
        <div class="col-12"><label class="form-label" for="adresse_postale">Adresse postale</label>
            <input class="form-control" id="adresse_postale" name="adresse_postale" required value="<?= e($u['adresse_postale']) ?>"></div>
        <div class="col-md-4"><label class="form-label" for="code_postal">Code postal</label>
            <input class="form-control" id="code_postal" name="code_postal" required value="<?= e($u['code_postal']) ?>"></div>
        <div class="col-md-8"><label class="form-label" for="ville">Ville</label>
            <input class="form-control" id="ville" name="ville" required value="<?= e($u['ville']) ?>"></div>
        <div class="col-12"><button class="btn btn-vg" type="submit">Enregistrer</button></div>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
