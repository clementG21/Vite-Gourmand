<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/commande.php';
require_once __DIR__ . '/../includes/stats.php';

$session = require_role(['utilisateur']);
$pdo = db();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

// Filtre sur utilisateur_id : un client ne peut jamais lire ni modifier la commande d'un autre (anti IDOR)
function charger_commande(PDO $pdo, int $id, int $uid): ?array {
    $s = $pdo->prepare('SELECT c.*, m.titre, m.nombre_personne_minimum, m.prix_minimum
                        FROM commande c JOIN menu m ON m.menu_id = c.menu_id
                        WHERE c.commande_id = ? AND c.utilisateur_id = ?');
    $s->execute([$id, $uid]);
    return $s->fetch() ?: null;
}
$c = charger_commande($pdo, $id, $session['id']);
if (!$c) { http_response_code(404); exit('Commande introuvable.'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'annuler' && $c['statut'] === 'en_attente') {
        $pdo->beginTransaction();
        $u = $pdo->prepare("UPDATE commande SET statut = 'annulee' WHERE commande_id = ? AND statut = 'en_attente'");
        $u->execute([$id]);
        if ($u->rowCount() === 1) {
            $pdo->prepare('UPDATE menu SET quantite_restante = quantite_restante + 1 WHERE menu_id = ?')->execute([$c['menu_id']]);
            $pdo->prepare('INSERT INTO commande_suivi (commande_id, statut) VALUES (?, ?)')->execute([$id, 'annulee']);
        }
        $pdo->commit();
        stats_maj($id, ['statut' => 'annulee']);
        flash('success', 'Votre commande a été annulée.');
        header("Location: /commande-detail.php?id=$id"); exit;
    }

    if ($action === 'modifier' && $c['statut'] === 'en_attente') {     // tout est modifiable sauf le choix du menu
        $adresse = trim($_POST['adresse'] ?? ''); $cp = trim($_POST['code_postal'] ?? ''); $ville = trim($_POST['ville'] ?? '');
        $heure = trim($_POST['heure'] ?? ''); $nb = (int)($_POST['nombre_personne'] ?? 0);
        $km = is_numeric($_POST['distance_km'] ?? '') ? (float)$_POST['distance_km'] : 0.0;
        $date = DateTime::createFromFormat('Y-m-d', $_POST['date_prestation'] ?? '');

        if ($adresse === '' || $ville === '') $errors[] = "Adresse et ville obligatoires.";
        if (!preg_match('/^[0-9]{5}$/', $cp)) $errors[] = "Code postal invalide.";
        if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $heure)) $errors[] = "Heure invalide.";
        if (!$date || $date <= new DateTime('today')) $errors[] = "La date de prestation doit être dans le futur.";
        if ($nb < (int)$c['nombre_personne_minimum']) $errors[] = "Minimum {$c['nombre_personne_minimum']} personnes pour ce menu.";
        if (!est_bordeaux($ville) && $km <= 0) $errors[] = "Indiquez la distance en km (livraison hors Bordeaux).";

        if (!$errors) {
            $p = calculer_prix((float)$c['prix_minimum'], (int)$c['nombre_personne_minimum'], $nb, $ville, $km);
            $pdo->prepare('UPDATE commande SET adresse_prestation=?, code_postal_prestation=?, ville_prestation=?, date_prestation=?,
                           heure_livraison=?, distance_km=?, nombre_personne=?, prix_menu=?, reduction=?, prix_livraison=?
                           WHERE commande_id=? AND utilisateur_id=? AND statut=\'en_attente\'')
                ->execute([$adresse, $cp, $ville, $date->format('Y-m-d'), $heure, est_bordeaux($ville) ? 0 : $km, $nb,
                           $p['prix_menu'], $p['reduction'], $p['prix_livraison'], $id, $session['id']]);
            stats_maj($id, ['nombre_personne' => $nb, 'montant' => $p['prix_menu']]);
            flash('success', 'Commande modifiée.');
            header("Location: /commande-detail.php?id=$id"); exit;
        }
    }

    if ($action === 'avis' && $c['statut'] === 'terminee') {
        $note = (int)($_POST['note'] ?? 0);
        $comm = trim($_POST['commentaire'] ?? '');
        if ($note < 1 || $note > 5) $errors[] = "La note doit être comprise entre 1 et 5.";
        if ($comm === '' || mb_strlen($comm) > 1000) $errors[] = "Le commentaire est obligatoire (1000 caractères maximum).";
        if (!$errors) {
            try {   // contrainte UNIQUE(commande_id) : un seul avis par commande
                $pdo->prepare('INSERT INTO avis (commande_id, utilisateur_id, note, commentaire) VALUES (?,?,?,?)')
                    ->execute([$id, $session['id'], $note, $comm]);
                flash('success', 'Merci ! Votre avis sera visible après validation par notre équipe.');
            } catch (PDOException $ex) { flash('warning', 'Vous avez déjà donné votre avis.'); }
            header("Location: /commande-detail.php?id=$id"); exit;
        }
    }
    $c = charger_commande($pdo, $id, $session['id']);
}

$suivi = [];
if ($c['statut'] !== 'en_attente') {      // suivi visible une fois la commande acceptée
    $s = $pdo->prepare('SELECT statut, modifie_le FROM commande_suivi WHERE commande_id = ? ORDER BY modifie_le, suivi_id');
    $s->execute([$id]); $suivi = $s->fetchAll();
}
$s = $pdo->prepare('SELECT note, commentaire, statut FROM avis WHERE commande_id = ?');
$s->execute([$id]); $avis = $s->fetch();
$total = (float)$c['prix_menu'] + (float)$c['prix_livraison'];

$pageTitle = 'Commande ' . $c['numero_commande'];
require __DIR__ . '/../includes/header.php';
?>
<nav aria-label="Fil d'Ariane"><a href="/espace-utilisateur.php">&larr; Mon espace</a></nav>
<h1 class="mt-2">Commande <?= e($c['numero_commande']) ?></h1>
<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<dl class="row">
    <dt class="col-sm-3">Statut</dt><dd class="col-sm-9"><strong><?= e(statut_label($c['statut'])) ?></strong></dd>
    <dt class="col-sm-3">Menu</dt><dd class="col-sm-9"><?= e($c['titre']) ?> pour <?= (int)$c['nombre_personne'] ?> personnes</dd>
    <dt class="col-sm-3">Prestation</dt><dd class="col-sm-9"><?= e(date('d/m/Y', strtotime($c['date_prestation']))) ?> à <?= e(substr($c['heure_livraison'], 0, 5)) ?></dd>
    <dt class="col-sm-3">Adresse</dt><dd class="col-sm-9"><?= e($c['adresse_prestation']) ?>, <?= e($c['code_postal_prestation']) ?> <?= e($c['ville_prestation']) ?></dd>
    <dt class="col-sm-3">Menu</dt><dd class="col-sm-9"><?= e(eur((float)$c['prix_menu'])) ?><?php if ((float)$c['reduction'] > 0): ?> (dont réduction de <?= e(eur((float)$c['reduction'])) ?>)<?php endif; ?></dd>
    <dt class="col-sm-3">Livraison</dt><dd class="col-sm-9"><?= e(eur((float)$c['prix_livraison'])) ?></dd>
    <dt class="col-sm-3">Total</dt><dd class="col-sm-9"><strong><?= e(eur($total)) ?></strong></dd>
</dl>

<?php if ($suivi): ?>
<section aria-labelledby="titre-suivi" class="mb-4">
    <h2 class="h4" id="titre-suivi">Suivi de la commande</h2>
    <ol>
    <?php foreach ($suivi as $s): ?>
        <li><?= e(statut_label($s['statut'])) ?> - <?= e(date('d/m/Y à H:i', strtotime($s['modifie_le']))) ?></li>
    <?php endforeach; ?>
    </ol>
</section>
<?php endif; ?>

<?php if ($c['statut'] === 'en_attente'): ?>
<section aria-labelledby="titre-modif" class="mb-4">
    <h2 class="h4" id="titre-modif">Modifier ma commande</h2>
    <p class="text-muted">Le menu choisi ne peut pas être modifié : annulez la commande pour en choisir un autre.</p>
    <form method="post" class="row g-3" style="max-width:700px">
        <?= csrf_field() ?><input type="hidden" name="action" value="modifier">
        <div class="col-12"><label class="form-label" for="adresse">Adresse</label>
            <input class="form-control" id="adresse" name="adresse" required value="<?= e($c['adresse_prestation']) ?>"></div>
        <div class="col-md-4"><label class="form-label" for="code_postal">Code postal</label>
            <input class="form-control" id="code_postal" name="code_postal" required value="<?= e($c['code_postal_prestation']) ?>"></div>
        <div class="col-md-8"><label class="form-label" for="ville">Ville</label>
            <input class="form-control" id="ville" name="ville" required value="<?= e($c['ville_prestation']) ?>"></div>
        <div class="col-md-4"><label class="form-label" for="date_prestation">Date</label>
            <input class="form-control" type="date" id="date_prestation" name="date_prestation" required value="<?= e($c['date_prestation']) ?>"></div>
        <div class="col-md-4"><label class="form-label" for="heure">Heure</label>
            <input class="form-control" type="time" id="heure" name="heure" required value="<?= e(substr($c['heure_livraison'], 0, 5)) ?>"></div>
        <div class="col-md-4"><label class="form-label" for="nombre_personne">Personnes (min. <?= (int)$c['nombre_personne_minimum'] ?>)</label>
            <input class="form-control" type="number" id="nombre_personne" name="nombre_personne" min="<?= (int)$c['nombre_personne_minimum'] ?>" required value="<?= (int)$c['nombre_personne'] ?>"></div>
        <div class="col-md-6"><label class="form-label" for="distance_km">Distance depuis Bordeaux (km)</label>
            <input class="form-control" type="number" min="0" step="0.1" id="distance_km" name="distance_km" value="<?= e((string)$c['distance_km']) ?>"></div>
        <div class="col-12"><button class="btn btn-vg" type="submit">Enregistrer les modifications</button></div>
    </form>
    <form method="post" class="mt-3" onsubmit="return confirm('Annuler définitivement cette commande ?');">
        <?= csrf_field() ?><input type="hidden" name="action" value="annuler">
        <button class="btn btn-outline-danger" type="submit">Annuler la commande</button>
    </form>
</section>
<?php endif; ?>

<?php if ($c['statut'] === 'terminee'): ?>
<section aria-labelledby="titre-avis" class="mb-4">
    <h2 class="h4" id="titre-avis">Votre avis</h2>
    <?php if ($avis): ?>
        <p>Note : <?= (int)$avis['note'] ?>/5 - <?= e($avis['commentaire']) ?></p>
        <p class="text-muted">Statut : <?= $avis['statut'] === 'valide' ? 'publié' : ($avis['statut'] === 'refuse' ? 'non retenu' : 'en attente de validation') ?></p>
    <?php else: ?>
    <form method="post" style="max-width:600px">
        <?= csrf_field() ?><input type="hidden" name="action" value="avis">
        <fieldset class="mb-3"><legend class="h6">Note</legend>
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="note" id="note<?= $i ?>" value="<?= $i ?>" required>
                    <label class="form-check-label" for="note<?= $i ?>"><?= $i ?></label>
                </div>
            <?php endfor; ?>
        </fieldset>
        <div class="mb-3"><label class="form-label" for="commentaire">Commentaire</label>
            <textarea class="form-control" id="commentaire" name="commentaire" rows="4" maxlength="1000" required></textarea></div>
        <button class="btn btn-vg" type="submit">Envoyer mon avis</button>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
