<?php
$pageTitle = 'Nos menus';
require __DIR__ . '/../includes/header.php';
$themes  = db()->query('SELECT theme_id, libelle FROM theme ORDER BY libelle')->fetchAll();
$regimes = db()->query('SELECT regime_id, libelle FROM regime ORDER BY libelle')->fetchAll();
?>
<h1>Nos menus</h1>

<form id="filtres" class="row g-3 mb-4" aria-label="Filtrer les menus">
    <div class="col-md-3">
        <label class="form-label" for="prix_max">Prix maximum (€)</label>
        <input class="form-control" type="number" min="0" step="1" id="prix_max" name="prix_max">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="fourchette_min">Prix : de (€)</label>
        <input class="form-control" type="number" min="0" step="1" id="fourchette_min" name="fourchette_min">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="fourchette_max">Prix : à (€)</label>
        <input class="form-control" type="number" min="0" step="1" id="fourchette_max" name="fourchette_max">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="personnes_min">Nombre de personnes</label>
        <input class="form-control" type="number" min="1" step="1" id="personnes_min" name="personnes_min" aria-describedby="aide-pers">
        <div id="aide-pers" class="form-text">Menus adaptés à un groupe de cette taille.</div>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="theme_id">Thème</label>
        <select class="form-select" id="theme_id" name="theme_id">
            <option value="">Tous</option>
            <?php foreach ($themes as $t): ?>
                <option value="<?= (int)$t['theme_id'] ?>"><?= e($t['libelle']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="regime_id">Régime</label>
        <select class="form-select" id="regime_id" name="regime_id">
            <option value="">Tous</option>
            <?php foreach ($regimes as $r): ?>
                <option value="<?= (int)$r['regime_id'] ?>"><?= e($r['libelle']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3 d-flex align-items-end">
        <button class="btn btn-outline-secondary" type="reset" id="reinit">Réinitialiser</button>
    </div>
</form>

<!-- Zone annoncée aux lecteurs d'écran à chaque mise à jour des résultats (RGAA) -->
<p id="statut" role="status" aria-live="polite" class="text-muted"></p>
<div id="liste-menus" class="row"></div>

<script src="/assets/js/menus.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
