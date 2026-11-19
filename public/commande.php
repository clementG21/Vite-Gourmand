<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/../includes/commande.php';
require_once __DIR__ . '/../includes/stats.php';

$session = require_role(['utilisateur']);
$pdo = db();

$stmt = $pdo->prepare('SELECT * FROM utilisateur WHERE utilisateur_id = ?');
$stmt->execute([$session['id']]);
$client = $stmt->fetch();

$menus = $pdo->query('SELECT menu_id, titre, nombre_personne_minimum, prix_minimum FROM menu WHERE quantite_restante > 0 ORDER BY titre')->fetchAll();
$parId = array_column($menus, null, 'menu_id');

$errors = [];
$old = [
    'menu_id'         => (int)($_GET['menu_id'] ?? 0),       // menu pré-sélectionné depuis la vue détaillée
    'adresse'         => $client['adresse_postale'],
    'code_postal'     => $client['code_postal'],
    'ville'           => $client['ville'],
    'date_prestation' => '',
    'heure'           => '',
    'distance_km'     => '',
    'nombre_personne' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (array_keys($old) as $k) { $old[$k] = trim((string)($_POST[$k] ?? '')); }
    $menu = $parId[(int)$old['menu_id']] ?? null;
    $nb   = (int)$old['nombre_personne'];
    $km   = is_numeric($old['distance_km']) ? (float)$old['distance_km'] : 0.0;

    if (!$menu) $errors[] = "Menu invalide ou épuisé.";
    if ($old['adresse'] === '' || $old['ville'] === '') $errors[] = "L'adresse de la prestation est obligatoire.";
    if (!preg_match('/^[0-9]{5}$/', $old['code_postal'])) $errors[] = "Code postal invalide.";
    $date = DateTime::createFromFormat('Y-m-d', $old['date_prestation']);
    if (!$date || $date->format('Y-m-d') !== $old['date_prestation'] || $date <= new DateTime('today')) $errors[] = "La date de prestation doit être dans le futur.";
    if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $old['heure'])) $errors[] = "Heure de livraison invalide.";
    if ($menu && $nb < (int)$menu['nombre_personne_minimum']) $errors[] = "Ce menu nécessite au minimum {$menu['nombre_personne_minimum']} personnes.";
    if ($nb > 1000) $errors[] = "Nombre de personnes invalide.";
    if (!est_bordeaux($old['ville']) && $km <= 0) $errors[] = "Indiquez la distance en km depuis Bordeaux (livraison hors Bordeaux).";

    if (!$errors) {
        $prix = calculer_prix((float)$menu['prix_minimum'], (int)$menu['nombre_personne_minimum'], $nb, $old['ville'], $km);
        $numero = 'VG-' . date('Ymd') . '-' . random_int(1000, 9999);
        try {
            $pdo->beginTransaction();
            // Décrément atomique du stock : évite de vendre plus que le stock même si deux clients commandent en même temps
            $upd = $pdo->prepare('UPDATE menu SET quantite_restante = quantite_restante - 1 WHERE menu_id = ? AND quantite_restante > 0');
            $upd->execute([$menu['menu_id']]);
            if ($upd->rowCount() !== 1) throw new RuntimeException('stock');

            $pdo->prepare('INSERT INTO commande (numero_commande, utilisateur_id, menu_id, date_prestation, heure_livraison,
                           adresse_prestation, code_postal_prestation, ville_prestation, distance_km, nombre_personne,
                           prix_menu, reduction, prix_livraison)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$numero, $session['id'], $menu['menu_id'], $old['date_prestation'], $old['heure'],
                           $old['adresse'], $old['code_postal'], $old['ville'], est_bordeaux($old['ville']) ? 0 : $km, $nb,
                           $prix['prix_menu'], $prix['reduction'], $prix['prix_livraison']]);
            $cid = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO commande_suivi (commande_id, statut) VALUES (?, ?)')->execute([$cid, 'en_attente']);
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = "Ce menu n'est plus disponible. Veuillez réessayer.";
            goto affichage;
        }

        send_mail($client['email'], "Confirmation de votre commande $numero",
            "Bonjour {$client['prenom']},\n\nNous avons bien reçu votre commande $numero ({$menu['titre']}, $nb personnes).\n"
            . "Menu : " . eur($prix['prix_menu']) . "\nLivraison : " . eur($prix['prix_livraison'])
            . "\nTotal : " . eur($prix['total']) . "\n\nNotre équipe va la valider prochainement.\nVite & Gourmand");
        stats_enregistrer($cid, (int)$menu['menu_id'], $menu['titre'], $nb, $prix['prix_menu']);
        flash('success', "Commande $numero enregistrée. Un mail de confirmation vous a été envoyé.");
        header('Location: /commande-detail.php?id=' . $cid);
        exit;
    }
}
affichage:
$pageTitle = 'Commander un menu';
require __DIR__ . '/../includes/header.php';
?>
<h1>Commander un menu</h1>
<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" id="form-commande" class="row g-3" style="max-width:800px" novalidate>
    <?= csrf_field() ?>
    <fieldset class="row g-3">
        <legend class="h5">Vos informations</legend>
        <div class="col-md-6"><label class="form-label" for="nom">Nom</label>
            <input class="form-control" id="nom" value="<?= e($client['nom']) ?>" readonly></div>
        <div class="col-md-6"><label class="form-label" for="prenom">Prénom</label>
            <input class="form-control" id="prenom" value="<?= e($client['prenom']) ?>" readonly></div>
        <div class="col-md-6"><label class="form-label" for="mail">Mail</label>
            <input class="form-control" id="mail" value="<?= e($client['email']) ?>" readonly></div>
        <div class="col-md-6"><label class="form-label" for="gsm">GSM</label>
            <input class="form-control" id="gsm" value="<?= e($client['telephone']) ?>" readonly></div>
    </fieldset>

    <fieldset class="row g-3">
        <legend class="h5">La prestation</legend>
        <div class="col-12"><label class="form-label" for="adresse">Adresse de la prestation</label>
            <input class="form-control" id="adresse" name="adresse" required value="<?= e($old['adresse']) ?>"></div>
        <div class="col-md-4"><label class="form-label" for="code_postal">Code postal</label>
            <input class="form-control" id="code_postal" name="code_postal" required value="<?= e($old['code_postal']) ?>"></div>
        <div class="col-md-8"><label class="form-label" for="ville">Ville</label>
            <input class="form-control" id="ville" name="ville" required value="<?= e($old['ville']) ?>"></div>
        <div class="col-md-6"><label class="form-label" for="date_prestation">Date de la prestation</label>
            <input class="form-control" type="date" id="date_prestation" name="date_prestation" required value="<?= e($old['date_prestation']) ?>"></div>
        <div class="col-md-6"><label class="form-label" for="heure">Heure souhaitée de livraison</label>
            <input class="form-control" type="time" id="heure" name="heure" required value="<?= e($old['heure']) ?>"></div>
        <div class="col-12" id="bloc-distance">
            <label class="form-label" for="distance_km">Distance depuis Bordeaux (km)</label>
            <input class="form-control" type="number" min="0" step="0.1" id="distance_km" name="distance_km" aria-describedby="aide-km" value="<?= e($old['distance_km']) ?>">
            <div id="aide-km" class="form-text">Livraison hors Bordeaux : 5 € + 0,59 € par km. Notre équipe vérifie la distance lors de la validation.</div>
        </div>
    </fieldset>

    <fieldset class="row g-3">
        <legend class="h5">Votre menu</legend>
        <div class="col-md-8"><label class="form-label" for="menu_id">Menu</label>
            <select class="form-select" id="menu_id" name="menu_id" required>
                <option value="">Choisir un menu</option>
                <?php foreach ($menus as $m): ?>
                    <option value="<?= (int)$m['menu_id'] ?>" <?= (int)$old['menu_id'] === (int)$m['menu_id'] ? 'selected' : '' ?>
                            data-min="<?= (int)$m['nombre_personne_minimum'] ?>">
                        <?= e($m['titre']) ?> (min. <?= (int)$m['nombre_personne_minimum'] ?> pers.)
                    </option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-4"><label class="form-label" for="nombre_personne">Nombre de personnes</label>
            <input class="form-control" type="number" min="1" id="nombre_personne" name="nombre_personne" required value="<?= e($old['nombre_personne']) ?>"></div>
    </fieldset>

    <div class="col-12">
        <div class="card"><div class="card-body" role="status" aria-live="polite" id="recap-prix">
            <h2 class="h5">Détail du prix</h2>
            <p class="mb-0 text-muted" id="recap-texte">Choisissez un menu et un nombre de personnes.</p>
        </div></div>
    </div>
    <div class="col-12"><button class="btn btn-vg btn-lg" type="submit">Valider ma commande</button></div>
</form>
<script src="/assets/js/commande.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
