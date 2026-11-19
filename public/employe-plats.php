<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
$user = require_role(['employe', 'administrateur']);
$pdo = db();
$errors = [];
$types = ['entree' => 'Entrée', 'plat' => 'Plat', 'dessert' => 'Dessert'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'supprimer') {
        $pdo->prepare('DELETE FROM plat WHERE plat_id = ?')->execute([(int)($_POST['plat_id'] ?? 0)]);   // cascade : retiré des menus
        flash('success', 'Plat supprimé.');
        header('Location: /employe-plats.php'); exit;
    }
    if ($action === 'enregistrer') {
        $id = (int)($_POST['plat_id'] ?? 0);
        $titre = trim($_POST['titre'] ?? ''); $type = $_POST['type_plat'] ?? ''; $desc = trim($_POST['description'] ?? '');
        $alls = array_map('intval', $_POST['allergenes'] ?? []);
        if ($titre === '') $errors[] = 'Le titre est obligatoire.';
        if (!isset($types[$type])) $errors[] = 'Type de plat invalide.';
        if (!$errors) {
            $pdo->beginTransaction();
            if ($id) {
                $pdo->prepare('UPDATE plat SET titre=?, type_plat=?, description=? WHERE plat_id=?')->execute([$titre, $type, $desc ?: null, $id]);
            } else {
                $pdo->prepare('INSERT INTO plat (titre, type_plat, description) VALUES (?,?,?)')->execute([$titre, $type, $desc ?: null]);
                $id = (int)$pdo->lastInsertId();
            }
            $pdo->prepare('DELETE FROM plat_allergene WHERE plat_id = ?')->execute([$id]);
            $ins = $pdo->prepare('INSERT IGNORE INTO plat_allergene (plat_id, allergene_id) SELECT ?, allergene_id FROM allergene WHERE allergene_id = ?');
            foreach ($alls as $a) { $ins->execute([$id, $a]); }
            $pdo->commit();
            flash('success', 'Plat enregistré.');
            header('Location: /employe-plats.php'); exit;
        }
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$plat = ['plat_id' => 0, 'titre' => '', 'type_plat' => 'plat', 'description' => ''];
$platAll = [];
if ($editId) {
    $s = $pdo->prepare('SELECT * FROM plat WHERE plat_id = ?'); $s->execute([$editId]);
    if ($r = $s->fetch()) {
        $plat = $r;
        $s = $pdo->prepare('SELECT allergene_id FROM plat_allergene WHERE plat_id = ?'); $s->execute([$editId]);
        $platAll = array_column($s->fetchAll(), 'allergene_id');
    }
}
$allergenes = $pdo->query('SELECT allergene_id, libelle FROM allergene ORDER BY libelle')->fetchAll();
$plats = $pdo->query("SELECT plat_id, titre, type_plat FROM plat ORDER BY FIELD(type_plat,'entree','plat','dessert'), titre")->fetchAll();

$pageTitle = 'Plats';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav_employe.php';
?>
<h1>Plats</h1>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row">
<div class="col-lg-6">
    <h2 class="h4"><?= $plat['plat_id'] ? 'Modifier le plat' : 'Nouveau plat' ?></h2>
    <form method="post" class="mb-4">
        <?= csrf_field() ?><input type="hidden" name="action" value="enregistrer"><input type="hidden" name="plat_id" value="<?= (int)$plat['plat_id'] ?>">
        <div class="mb-3"><label class="form-label" for="titre">Titre</label><input class="form-control" id="titre" name="titre" required value="<?= e($plat['titre']) ?>"></div>
        <div class="mb-3"><label class="form-label" for="type_plat">Type</label>
            <select class="form-select" id="type_plat" name="type_plat">
                <?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= $plat['type_plat'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select></div>
        <div class="mb-3"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="2"><?= e($plat['description']) ?></textarea></div>
        <fieldset class="mb-3"><legend class="h6">Allergènes</legend>
            <?php foreach ($allergenes as $a): ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="all<?= (int)$a['allergene_id'] ?>" name="allergenes[]" value="<?= (int)$a['allergene_id'] ?>" <?= in_array($a['allergene_id'], $platAll) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="all<?= (int)$a['allergene_id'] ?>"><?= e($a['libelle']) ?></label>
                </div>
            <?php endforeach; ?>
        </fieldset>
        <button class="btn btn-vg" type="submit">Enregistrer</button>
        <?php if ($plat['plat_id']): ?><a class="btn btn-outline-secondary" href="/employe-plats.php">Annuler</a><?php endif; ?>
    </form>
</div>
<div class="col-lg-6">
    <h2 class="h4">Liste des plats</h2>
    <ul class="list-group">
    <?php foreach ($plats as $p): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><?= e($p['titre']) ?> <small class="text-muted">(<?= e($types[$p['type_plat']]) ?>)</small></span>
            <span class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="/employe-plats.php?edit=<?= (int)$p['plat_id'] ?>">Modifier<span class="visually-hidden"> <?= e($p['titre']) ?></span></a>
                <form method="post" onsubmit="return confirm('Supprimer ce plat ?');">
                    <?= csrf_field() ?><input type="hidden" name="action" value="supprimer"><input type="hidden" name="plat_id" value="<?= (int)$p['plat_id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Supprimer<span class="visually-hidden"> <?= e($p['titre']) ?></span></button>
                </form>
            </span>
        </li>
    <?php endforeach; ?>
    </ul>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
