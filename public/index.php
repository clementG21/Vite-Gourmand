<?php
$pageTitle = 'Accueil';
require __DIR__ . '/../includes/header.php';

// Seuls les avis validés par un employé sont affichés
$avis = db()->query("SELECT a.note, a.commentaire, u.prenom
                     FROM avis a JOIN utilisateur u ON u.utilisateur_id = a.utilisateur_id
                     WHERE a.statut = 'valide' ORDER BY a.cree_le DESC LIMIT 6")->fetchAll();

$menus = db()->query("SELECT m.menu_id, m.titre, m.description, m.nombre_personne_minimum, m.prix_minimum,
                             (SELECT chemin FROM menu_image i WHERE i.menu_id = m.menu_id ORDER BY i.image_id LIMIT 1) AS image,
                             (SELECT texte_alternatif FROM menu_image i WHERE i.menu_id = m.menu_id ORDER BY i.image_id LIMIT 1) AS image_alt
                      FROM menu m WHERE m.quantite_restante > 0 ORDER BY m.menu_id DESC LIMIT 3")->fetchAll();
?>
<section class="hero" aria-labelledby="titre-hero">
    <h1 id="titre-hero">Vite &amp; Gourmand</h1>
    <p class="lead">Traiteur à Bordeaux depuis 25 ans. Des menus faits maison pour vos repas de Noël, de Pâques et tous vos événements.</p>
    <div class="d-flex flex-wrap gap-2 justify-content-center">
        <a class="btn btn-clair btn-lg" href="/menus.php">Découvrir nos menus</a>
        <?php if (!$user): ?><a class="btn btn-contour-clair btn-lg" href="/inscription.php">Créer un compte</a><?php endif; ?>
    </div>
</section>

<section aria-labelledby="titre-entreprise">
    <h2 class="titre-section" id="titre-entreprise">Une maison de confiance</h2>
    <div class="row align-items-center g-4">
        <div class="col-md-8">
            <p>Julie et José vous accueillent depuis 25 ans à Bordeaux. Notre équipe prépare des menus en constante évolution, pour un simple repas de famille comme pour un grand événement.</p>
            <p class="mb-0">Pour vous simplifier la vie, vous consultez nos menus, vous commandez en ligne et vous suivez votre commande : nous nous occupons du reste, jusqu'à la livraison.</p>
        </div>
        <div class="col-md-4 text-center">
            <p class="chiffre-cle mb-0">25 ans</p>
            <p class="mb-0">d'expérience à Bordeaux</p>
        </div>
    </div>
</section>

<section aria-labelledby="titre-equipe">
    <h2 class="titre-section" id="titre-equipe">Le professionnalisme de notre équipe</h2>
    <div class="row g-3">
        <div class="col-md-4"><div class="card valeur"><span class="pastille" aria-hidden="true">1</span>
            <h3 class="h5">Cuisine maison</h3><p class="mb-0">Une équipe cuisine qui prépare chaque commande avec des produits frais et de saison.</p></div></div>
        <div class="col-md-4"><div class="card valeur"><span class="pastille" aria-hidden="true">2</span>
            <h3 class="h5">Allergènes et régimes</h3><p class="mb-0">Allergènes indiqués sur chaque plat, menus classiques, végétariens et vegan.</p></div></div>
        <div class="col-md-4"><div class="card valeur"><span class="pastille" aria-hidden="true">3</span>
            <h3 class="h5">Livraison soignée</h3><p class="mb-0">Une équipe logistique dédiée livre votre menu à l'heure choisie, avec un suivi de commande en ligne.</p></div></div>
    </div>
</section>

<?php if ($menus): ?>
<section aria-labelledby="titre-menus">
    <h2 class="titre-section" id="titre-menus">Nos menus à l'honneur</h2>
    <div class="row">
    <?php foreach ($menus as $m): ?>
        <div class="col-md-4 mb-4">
            <article class="card card-menu h-100">
                <?php if ($m['image']): ?><img class="card-img-top" src="<?= e($m['image']) ?>" alt="<?= e($m['image_alt']) ?>" loading="lazy"><?php endif; ?>
                <div class="card-body d-flex flex-column">
                    <h3 class="h5 card-title"><?= e($m['titre']) ?></h3>
                    <p class="card-text"><?= e($m['description']) ?></p>
                    <p class="prix mt-auto mb-2">À partir de <?= e(number_format((float)$m['prix_minimum'], 2, ',', ' ')) ?> € pour <?= (int)$m['nombre_personne_minimum'] ?> personnes</p>
                    <a class="btn btn-vg" href="/menu.php?id=<?= (int)$m['menu_id'] ?>">Voir le détail<span class="visually-hidden"> du menu <?= e($m['titre']) ?></span></a>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
    </div>
    <p class="text-center"><a class="btn btn-outline-vg" href="/menus.php">Voir tous les menus</a></p>
</section>
<?php endif; ?>

<section aria-labelledby="titre-avis">
    <h2 class="titre-section" id="titre-avis">Ils nous ont fait confiance</h2>
    <?php if (!$avis): ?>
        <p class="text-center">Les premiers avis de nos clients arrivent bientôt.</p>
    <?php else: ?>
        <div class="row">
        <?php foreach ($avis as $a): $n = max(0, min(5, (int)$a['note'])); ?>
            <div class="col-md-4 mb-3">
                <figure class="card p-3 h-100 mb-0">
                    <p class="avis-etoiles mb-1"><span aria-hidden="true"><?= str_repeat('★', $n) . str_repeat('☆', 5 - $n) ?></span><span class="visually-hidden">Note : <?= $n ?> sur 5</span></p>
                    <blockquote class="mb-2"><?= e($a['commentaire']) ?></blockquote>
                    <figcaption class="text-muted">- <?= e($a['prenom']) ?></figcaption>
                </figure>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
