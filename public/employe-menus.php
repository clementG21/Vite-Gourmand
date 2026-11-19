<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
$user = require_role(['employe', 'administrateur']);
$pdo = db();
$errors = [];

// Upload d'image sécurisé : type MIME réel vérifié, taille limitée, nom aléatoire (jamais le nom d'origine)
function enregistrer_image(array $f): ?string {
    if ($f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 2 * 1024 * 1024) throw new RuntimeException('Image invalide ou trop lourde (2 Mo maximum).');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) throw new RuntimeException('Format non accepté (JPEG, PNG ou WebP uniquement).');
    $nom = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/uploads/' . $nom)) throw new RuntimeException("Échec de l'enregistrement de l'image.");
    return '/uploads/' . $nom;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'supprimer') {
        try {
            $pdo->prepare('DELETE FROM menu WHERE menu_id = ?')->execute([(int)($_POST['menu_id'] ?? 0)]);
            flash('success', 'Menu supprimé.');
        } catch (PDOException $ex) {
            flash('danger', 'Ce menu a déjà des commandes : il ne peut pas être supprimé (mettez son stock à 0 pour le retirer de la vente).');
        }
        header('Location: /employe-menus.php'); exit;
    }

    if ($action === 'enregistrer') {
        $id = (int)($_POST['menu_id'] ?? 0);
        $d = [
            'titre' => trim($_POST['titre'] ?? ''), 'description' => trim($_POST['description'] ?? ''),
            'min' => (int)($_POST['nombre_personne_minimum'] ?? 0), 'prix' => $_POST['prix_minimum'] ?? '',
            'cond' => trim($_POST['conditions_menu'] ?? ''), 'stock' => (int)($_POST['quantite_restante'] ?? -1),
            'theme' => (int)($_POST['theme_id'] ?? 0), 'regime' => (int)($_POST['regime_id'] ?? 0),
        ];
        $platsIds = array_map('intval', $_POST['plats'] ?? []);
        if ($d['titre'] === '' || $d['description'] === '' || $d['cond'] === '') $errors[] = 'Titre, description et conditions sont obligatoires.';
        if ($d['min'] < 1) $errors[] = 'Le nombre de personnes minimum doit être au moins 1.';
        if (!is_numeric($d['prix']) || (float)$d['prix'] < 0) $errors[] = 'Prix invalide.';
        if ($d['stock'] < 0) $errors[] = 'Stock invalide.';

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                if ($id) {
                    $pdo->prepare('UPDATE menu SET titre=?, description=?, nombre_personne_minimum=?, prix_minimum=?, conditions_menu=?, quantite_restante=?, theme_id=?, regime_id=? WHERE menu_id=?')
                        ->execute([$d['titre'], $d['description'], $d['min'], (float)$d['prix'], $d['cond'], $d['stock'], $d['theme'], $d['regime'], $id]);
                } else {
                    $pdo->prepare('INSERT INTO menu (titre, description, nombre_personne_minimum, prix_minimum, conditions_menu, quantite_restante, theme_id, regime_id) VALUES (?,?,?,?,?,?,?,?)')
                        ->execute([$d['titre'], $d['description'], $d['min'], (float)$d['prix'], $d['cond'], $d['stock'], $d['theme'], $d['regime']]);
                    $id = (int)$pdo->lastInsertId();
                }
                // Plats du menu (un plat peut appartenir à plusieurs menus)
                $pdo->prepare('DELETE FROM menu_plat WHERE menu_id = ?')->execute([$id]);
                $ins = $pdo->prepare('INSERT IGNORE INTO menu_plat (menu_id, plat_id) SELECT ?, plat_id FROM plat WHERE plat_id = ?');
                foreach ($platsIds as $pid) { $ins->execute([$id, $pid]); }
                // Images à supprimer
                foreach (array_map('intval', $_POST['suppr_images'] ?? []) as $iid) {
                    $s = $pdo->prepare('SELECT chemin FROM menu_image WHERE image_id = ? AND menu_id = ?'); $s->execute([$iid, $id]);
                    if ($chemin = $s->fetchColumn()) {
                        $pdo->prepare('DELETE FROM menu_image WHERE image_id = ?')->execute([$iid]);
                        if (str_starts_with($chemin, '/uploads/')) @unlink(__DIR__ . $chemin);
                    }
                }
                // Nouvelles images (texte alternatif obligatoire : RGAA)
                for ($i = 0; $i < 3; $i++) {
                    if (!isset($_FILES["image_$i"])) continue;
                    $alt = trim($_POST["alt_$i"] ?? '');
                    $chemin = enregistrer_image($_FILES["image_$i"]);
                    if ($chemin !== null) {
                        if ($alt === '') throw new RuntimeException("Le texte alternatif de l'image " . ($i + 1) . ' est obligatoire.');
                        $pdo->prepare('INSERT INTO menu_image (menu_id, chemin, texte_alternatif) VALUES (?,?,?)')->execute([$id, $chemin, $alt]);
                    }
                }
                $pdo->commit();
                flash('success', 'Menu enregistré.');
                header('Location: /employe-menus.php'); exit;
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : "Erreur lors de l'enregistrement du menu.";
            }
        }
    }
}

$themes = $pdo->query('SELECT * FROM theme ORDER BY libelle')->fetchAll();
$regimes = $pdo->query('SELECT * FROM regime ORDER BY libelle')->fetchAll();
$tousPlats = $pdo->query("SELECT plat_id, titre, type_plat FROM plat ORDER BY FIELD(type_plat,'entree','plat','dessert'), titre")->fetchAll();
$menus = $pdo->query('SELECT m.menu_id, m.titre, m.prix_minimum, m.nombre_personne_minimum, m.quantite_restante, t.libelle AS theme FROM menu m JOIN theme t ON t.theme_id = m.theme_id ORDER BY m.titre')->fetchAll();

$editing = isset($_GET['edit']) || isset($_GET['nouveau']) || ($errors && $_SERVER['REQUEST_METHOD'] === 'POST');
$menu = ['menu_id' => 0, 'titre' => '', 'description' => '', 'nombre_personne_minimum' => 4, 'prix_minimum' => '', 'conditions_menu' => '', 'quantite_restante' => 10, 'theme_id' => 0, 'regime_id' => 0];
$menuPlats = []; $images = [];
if (!empty($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM menu WHERE menu_id = ?'); $s->execute([(int)$_GET['edit']]);
    if ($r = $s->fetch()) {
        $menu = $r;
        $s = $pdo->prepare('SELECT plat_id FROM menu_plat WHERE menu_id = ?'); $s->execute([$r['menu_id']]); $menuPlats = array_column($s->fetchAll(), 'plat_id');
        $s = $pdo->prepare('SELECT image_id, chemin, texte_alternatif FROM menu_image WHERE menu_id = ?'); $s->execute([$r['menu_id']]); $images = $s->fetchAll();
    }
}

$pageTitle = 'Gestion des menus';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav_employe.php';
$typesLib = ['entree' => 'Entrée', 'plat' => 'Plat', 'dessert' => 'Dessert'];
?>
<h1>Menus</h1>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if (!$editing): ?>
    <p><a class="btn btn-vg" href="/employe-menus.php?nouveau=1">Ajouter un menu</a></p>
    <div class="table-responsive"><table class="table align-middle">
        <caption class="visually-hidden">Liste des menus</caption>
        <thead><tr><th scope="col">Titre</th><th scope="col">Thème</th><th scope="col">Prix min.</th><th scope="col">Stock</th><th scope="col">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($menus as $m): ?>
            <tr><td><?= e($m['titre']) ?></td><td><?= e($m['theme']) ?></td>
                <td><?= e(number_format((float)$m['prix_minimum'], 2, ',', ' ')) ?> € / <?= (int)$m['nombre_personne_minimum'] ?> pers.</td>
                <td><?= (int)$m['quantite_restante'] ?></td>
                <td class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-secondary" href="/employe-menus.php?edit=<?= (int)$m['menu_id'] ?>">Modifier<span class="visually-hidden"> <?= e($m['titre']) ?></span></a>
                    <form method="post" onsubmit="return confirm('Supprimer ce menu ?');">
                        <?= csrf_field() ?><input type="hidden" name="action" value="supprimer"><input type="hidden" name="menu_id" value="<?= (int)$m['menu_id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Supprimer<span class="visually-hidden"> <?= e($m['titre']) ?></span></button>
                    </form></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
<?php else: ?>
    <h2 class="h4"><?= $menu['menu_id'] ? 'Modifier le menu' : 'Nouveau menu' ?></h2>
    <form method="post" enctype="multipart/form-data" class="row g-3" style="max-width:800px">
        <?= csrf_field() ?><input type="hidden" name="action" value="enregistrer"><input type="hidden" name="menu_id" value="<?= (int)$menu['menu_id'] ?>">
        <div class="col-12"><label class="form-label" for="titre">Titre</label><input class="form-control" id="titre" name="titre" required value="<?= e($menu['titre']) ?>"></div>
        <div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="3" required><?= e($menu['description']) ?></textarea></div>
        <div class="col-md-4"><label class="form-label" for="theme_id">Thème</label><select class="form-select" id="theme_id" name="theme_id" required>
            <?php foreach ($themes as $t): ?><option value="<?= (int)$t['theme_id'] ?>" <?= (int)$menu['theme_id'] === (int)$t['theme_id'] ? 'selected' : '' ?>><?= e($t['libelle']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label" for="regime_id">Régime</label><select class="form-select" id="regime_id" name="regime_id" required>
            <?php foreach ($regimes as $r): ?><option value="<?= (int)$r['regime_id'] ?>" <?= (int)$menu['regime_id'] === (int)$r['regime_id'] ? 'selected' : '' ?>><?= e($r['libelle']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label" for="quantite_restante">Stock (commandes possibles)</label><input class="form-control" type="number" min="0" id="quantite_restante" name="quantite_restante" required value="<?= (int)$menu['quantite_restante'] ?>"></div>
        <div class="col-md-6"><label class="form-label" for="nombre_personne_minimum">Nombre de personnes minimum</label><input class="form-control" type="number" min="1" id="nombre_personne_minimum" name="nombre_personne_minimum" required value="<?= (int)$menu['nombre_personne_minimum'] ?>"></div>
        <div class="col-md-6"><label class="form-label" for="prix_minimum">Prix pour ce minimum (€)</label><input class="form-control" type="number" min="0" step="0.01" id="prix_minimum" name="prix_minimum" required value="<?= e((string)$menu['prix_minimum']) ?>"></div>
        <div class="col-12"><label class="form-label" for="conditions_menu">Conditions du menu</label><textarea class="form-control" id="conditions_menu" name="conditions_menu" rows="2" required><?= e($menu['conditions_menu']) ?></textarea></div>

        <fieldset class="col-12"><legend class="h6">Plats du menu</legend>
            <?php foreach ($tousPlats as $p): ?>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="p<?= (int)$p['plat_id'] ?>" name="plats[]" value="<?= (int)$p['plat_id'] ?>" <?= in_array($p['plat_id'], $menuPlats) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="p<?= (int)$p['plat_id'] ?>"><?= e($p['titre']) ?> <small class="text-muted">(<?= e($typesLib[$p['type_plat']]) ?>)</small></label></div>
            <?php endforeach; ?>
        </fieldset>

        <fieldset class="col-12"><legend class="h6">Galerie d'images</legend>
            <?php foreach ($images as $im): ?>
                <div class="d-flex align-items-center gap-3 mb-2"><img src="<?= e($im['chemin']) ?>" alt="<?= e($im['texte_alternatif']) ?>" style="height:60px">
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="si<?= (int)$im['image_id'] ?>" name="suppr_images[]" value="<?= (int)$im['image_id'] ?>">
                        <label class="form-check-label" for="si<?= (int)$im['image_id'] ?>">Supprimer « <?= e($im['texte_alternatif']) ?> »</label></div></div>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < 3; $i++): ?>
                <div class="row g-2 mb-2">
                    <div class="col-md-6"><label class="form-label" for="image_<?= $i ?>">Nouvelle image <?= $i + 1 ?> (JPEG, PNG, WebP, 2 Mo max)</label><input class="form-control" type="file" id="image_<?= $i ?>" name="image_<?= $i ?>" accept="image/jpeg,image/png,image/webp"></div>
                    <div class="col-md-6"><label class="form-label" for="alt_<?= $i ?>">Texte alternatif de l'image <?= $i + 1 ?></label><input class="form-control" id="alt_<?= $i ?>" name="alt_<?= $i ?>"></div>
                </div>
            <?php endfor; ?>
        </fieldset>
        <div class="col-12"><button class="btn btn-vg" type="submit">Enregistrer</button> <a class="btn btn-outline-secondary" href="/employe-menus.php">Annuler</a></div>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
