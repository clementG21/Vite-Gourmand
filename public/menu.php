<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$menu = null;
if ($id) {
    $stmt = db()->prepare('SELECT m.*, t.libelle AS theme, r.libelle AS regime
                           FROM menu m JOIN theme t ON t.theme_id = m.theme_id JOIN regime r ON r.regime_id = m.regime_id
                           WHERE m.menu_id = ?');
    $stmt->execute([$id]);
    $menu = $stmt->fetch();
}
if (!$menu) {
    http_response_code(404);
    $pageTitle = 'Menu introuvable';
    require __DIR__ . '/../includes/header.php';
    echo '<h1>Menu introuvable</h1><p><a href="/menus.php">Retour aux menus</a></p>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = db()->prepare('SELECT chemin, texte_alternatif FROM menu_image WHERE menu_id = ? ORDER BY image_id');
$stmt->execute([$id]);
$images = $stmt->fetchAll();

$stmt = db()->prepare("SELECT p.titre, p.type_plat, p.description, GROUP_CONCAT(a.libelle ORDER BY a.libelle SEPARATOR ', ') AS allergenes
                       FROM menu_plat mp
                       JOIN plat p ON p.plat_id = mp.plat_id
                       LEFT JOIN plat_allergene pa ON pa.plat_id = p.plat_id
                       LEFT JOIN allergene a ON a.allergene_id = pa.allergene_id
                       WHERE mp.menu_id = ?
                       GROUP BY p.plat_id
                       ORDER BY FIELD(p.type_plat, 'entree', 'plat', 'dessert')");
$stmt->execute([$id]);
$plats = $stmt->fetchAll();
$libelles = ['entree' => 'Entrées', 'plat' => 'Plats', 'dessert' => 'Desserts'];

$user = current_user();
$pageTitle = $menu['titre'];
require __DIR__ . '/../includes/header.php';
?>
<nav aria-label="Fil d'Ariane"><a href="/menus.php">&larr; Tous les menus</a></nav>
<h1 class="mt-2"><?= e($menu['titre']) ?></h1>
<p><span class="badge bg-secondary"><?= e($menu['theme']) ?></span> <span class="badge bg-secondary"><?= e($menu['regime']) ?></span></p>

<?php if ($images): ?>
<div class="row mb-3">
    <?php foreach ($images as $img): ?>
        <div class="col-6 col-md-4 mb-3"><img class="img-fluid rounded" src="<?= e($img['chemin']) ?>" alt="<?= e($img['texte_alternatif']) ?>"></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<p><?= nl2br(e($menu['description'])) ?></p>

<!-- Conditions bien visibles : le client ne pourra pas dire qu'il n'a pas vu l'information -->
<div class="alert alert-warning border-2 border-warning" role="note">
    <h2 class="h5">Conditions de ce menu</h2>
    <p class="mb-0"><?= nl2br(e($menu['conditions_menu'])) ?></p>
</div>

<h2 class="h4">Composition</h2>
<?php foreach ($libelles as $type => $titre): ?>
    <?php $groupe = array_filter($plats, fn($p) => $p['type_plat'] === $type); ?>
    <?php if ($groupe): ?>
        <h3 class="h5"><?= e($titre) ?></h3>
        <ul>
        <?php foreach ($groupe as $p): ?>
            <li><strong><?= e($p['titre']) ?></strong>
                <?php if ($p['description']): ?> - <?= e($p['description']) ?><?php endif; ?>
                <br><small>Allergènes : <?= $p['allergenes'] ? e($p['allergenes']) : 'aucun déclaré' ?></small>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endforeach; ?>

<p class="fs-5">
    À partir de <strong><?= number_format((float)$menu['prix_minimum'], 2, ',', ' ') ?> €</strong>
    pour <strong><?= (int)$menu['nombre_personne_minimum'] ?> personnes</strong> minimum.
</p>
<p>Stock disponible : <?= (int)$menu['quantite_restante'] ?> commande(s) possible(s).</p>

<?php if ((int)$menu['quantite_restante'] <= 0): ?>
    <p class="alert alert-secondary">Ce menu n'est plus disponible pour le moment.</p>
<?php elseif ($user && $user['role'] === 'utilisateur'): ?>
    <a class="btn btn-vg btn-lg" href="/commande.php?menu_id=<?= (int)$menu['menu_id'] ?>">Commander ce menu</a>
<?php elseif ($user): ?>
    <p class="text-muted">Seuls les comptes clients peuvent commander.</p>
<?php else: ?>
    <div class="alert alert-info">
        Pour commander, <a href="/connexion.php">connectez-vous</a> ou <a href="/inscription.php">créez un compte</a>.
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
