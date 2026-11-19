<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
$user = require_role(['employe', 'administrateur']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $decision = $_POST['decision'] ?? '';
    if (in_array($decision, ['valide', 'refuse'], true)) {
        $pdo->prepare("UPDATE avis SET statut = ? WHERE avis_id = ? AND statut = 'en_attente'")
            ->execute([$decision, (int)($_POST['avis_id'] ?? 0)]);
        flash('success', $decision === 'valide' ? 'Avis validé : il apparaît sur la page d\'accueil.' : 'Avis refusé.');
    }
    header('Location: /employe-avis.php'); exit;
}

$avis = $pdo->query("SELECT a.avis_id, a.note, a.commentaire, a.cree_le, u.prenom, u.nom, m.titre
                     FROM avis a JOIN utilisateur u ON u.utilisateur_id = a.utilisateur_id
                     JOIN commande c ON c.commande_id = a.commande_id JOIN menu m ON m.menu_id = c.menu_id
                     WHERE a.statut = 'en_attente' ORDER BY a.cree_le")->fetchAll();

$pageTitle = 'Avis à valider';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav_employe.php';
?>
<h1>Avis à valider</h1>
<?php if (!$avis): ?><p>Aucun avis en attente.</p><?php endif; ?>
<?php foreach ($avis as $a): ?>
    <article class="card mb-3"><div class="card-body">
        <h2 class="h6"><?= e($a['prenom'] . ' ' . $a['nom']) ?> - <?= e($a['titre']) ?> - note <?= (int)$a['note'] ?>/5</h2>
        <p><?= e($a['commentaire']) ?></p>
        <form method="post" class="d-flex gap-2">
            <?= csrf_field() ?><input type="hidden" name="avis_id" value="<?= (int)$a['avis_id'] ?>">
            <button class="btn btn-vg btn-sm" name="decision" value="valide" type="submit">Valider</button>
            <button class="btn btn-outline-danger btn-sm" name="decision" value="refuse" type="submit">Refuser</button>
        </form>
    </div></article>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
