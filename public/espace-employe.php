<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/../includes/commande.php';
require_once __DIR__ . '/../includes/stats.php';

$user = require_role(['employe', 'administrateur']);   // l'administrateur peut tout faire comme un employé
$pdo = db();

const STATUTS = ['en_attente','accepte','en_preparation','en_cours_livraison','livre','attente_retour_materiel','terminee','annulee'];
// Cycle de vie d'une commande : seules ces transitions sont autorisées
const SUITES = [
    'en_attente'              => ['accepte'],
    'accepte'                 => ['en_preparation'],
    'en_preparation'          => ['en_cours_livraison'],
    'en_cours_livraison'      => ['livre'],
    'livre'                   => ['terminee', 'attente_retour_materiel'],   // matériel prêté ou non
    'attente_retour_materiel' => ['terminee'],
];

function commande_complete(PDO $pdo, int $id): ?array {
    $s = $pdo->prepare('SELECT c.*, u.email, u.prenom, u.nom, m.titre
                        FROM commande c JOIN utilisateur u ON u.utilisateur_id = c.utilisateur_id JOIN menu m ON m.menu_id = c.menu_id
                        WHERE c.commande_id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $cid = (int)($_POST['commande_id'] ?? 0);
    $c = commande_complete($pdo, $cid);

    if (!$c) {
        flash('danger', 'Commande introuvable.');
    } elseif ($action === 'statut') {
        $nouveau = $_POST['nouveau'] ?? '';
        if (!in_array($nouveau, SUITES[$c['statut']] ?? [], true)) {
            flash('danger', 'Changement de statut impossible.');
        } else {
            $extra = '';   // construit uniquement à partir de constantes : pas de donnée utilisateur dans le SQL
            if ($nouveau === 'attente_retour_materiel') $extra = ', pret_materiel = 1';
            if ($nouveau === 'terminee' && $c['statut'] === 'attente_retour_materiel') $extra = ', restitution_materiel = 1';

            $pdo->beginTransaction();
            $u = $pdo->prepare("UPDATE commande SET statut = ? $extra WHERE commande_id = ? AND statut = ?");
            $u->execute([$nouveau, $cid, $c['statut']]);
            if ($u->rowCount() === 1) {
                $pdo->prepare('INSERT INTO commande_suivi (commande_id, statut) VALUES (?, ?)')->execute([$cid, $nouveau]);
            }
            $pdo->commit();

            if ($nouveau === 'attente_retour_materiel') {
                send_mail($c['email'], "Retour du matériel - commande {$c['numero_commande']}",
                    "Bonjour {$c['prenom']},\n\nDu matériel vous a été prêté pour votre commande {$c['numero_commande']}.\n"
                    . "Si ce matériel n'est pas restitué sous 10 jours ouvrés, des frais de 600 € vous seront facturés (voir nos conditions générales de vente).\n"
                    . "Pour le rendre, merci de prendre contact avec Vite & Gourmand.\n\nL'équipe Vite & Gourmand");
            } elseif ($nouveau === 'terminee') {
                send_mail($c['email'], "Votre commande {$c['numero_commande']} est terminée",
                    "Bonjour {$c['prenom']},\n\nVotre commande est terminée. Connectez-vous à votre compte pour donner votre avis depuis la commande.\n\nL'équipe Vite & Gourmand");
            }
            flash('success', 'Statut mis à jour : ' . statut_label($nouveau) . '.');
        }
    } elseif ($action === 'annuler') {
        $mode  = $_POST['mode_contact'] ?? '';
        $motif = trim($_POST['motif'] ?? '');
        if (!in_array($mode, ['gsm', 'mail'], true) || $motif === '') {
            flash('danger', "Pour annuler, indiquez le mode de contact utilisé (GSM ou mail) et le motif.");
        } elseif (in_array($c['statut'], ['terminee', 'annulee'], true)) {
            flash('danger', 'Cette commande ne peut plus être annulée.');
        } else {
            $pdo->beginTransaction();
            $u = $pdo->prepare("UPDATE commande SET statut = 'annulee' WHERE commande_id = ? AND statut NOT IN ('terminee','annulee')");
            $u->execute([$cid]);
            if ($u->rowCount() === 1) {
                $pdo->prepare('UPDATE menu SET quantite_restante = quantite_restante + 1 WHERE menu_id = ?')->execute([$c['menu_id']]);
                $pdo->prepare('INSERT INTO commande_annulation (commande_id, employe_id, mode_contact, motif) VALUES (?,?,?,?)')
                    ->execute([$cid, $user['id'], $mode, $motif]);
                $pdo->prepare('INSERT INTO commande_suivi (commande_id, statut) VALUES (?, ?)')->execute([$cid, 'annulee']);
            }
            $pdo->commit();
            stats_maj($cid, ['statut' => 'annulee']);
            send_mail($c['email'], "Annulation de votre commande {$c['numero_commande']}",
                "Bonjour {$c['prenom']},\n\nVotre commande {$c['numero_commande']} a dû être annulée.\nMotif : $motif\n\nL'équipe Vite & Gourmand");
            flash('success', 'Commande annulée.');
        }
    }
    header('Location: /espace-employe.php'); exit;
}

// Filtres : par statut et/ou par client
$where = []; $params = [];
$fs = $_GET['statut'] ?? '';
if (in_array($fs, STATUTS, true)) { $where[] = 'c.statut = ?'; $params[] = $fs; }
$fc = trim($_GET['client'] ?? '');
if ($fc !== '') {
    $like = '%' . addcslashes($fc, '%_\\') . '%';
    $where[] = '(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)';
    array_push($params, $like, $like, $like);
}
$sql = 'SELECT c.commande_id, c.numero_commande, c.statut, c.date_prestation, c.heure_livraison, c.nombre_personne, c.pret_materiel,
               u.nom, u.prenom, u.email, u.telephone, m.titre
        FROM commande c JOIN utilisateur u ON u.utilisateur_id = c.utilisateur_id JOIN menu m ON m.menu_id = c.menu_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY c.date_prestation, c.heure_livraison LIMIT 200';
$s = $pdo->prepare($sql); $s->execute($params); $commandes = $s->fetchAll();

$pageTitle = 'Espace employé';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav_employe.php';
?>
<h1>Commandes</h1>
<form method="get" class="row g-3 mb-4" aria-label="Filtrer les commandes">
    <div class="col-md-4"><label class="form-label" for="statut">Statut</label>
        <select class="form-select" id="statut" name="statut">
            <option value="">Tous</option>
            <?php foreach (STATUTS as $st): ?><option value="<?= e($st) ?>" <?= $fs === $st ? 'selected' : '' ?>><?= e(statut_label($st)) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-5"><label class="form-label" for="client">Client (nom, prénom ou mail)</label>
        <input class="form-control" id="client" name="client" value="<?= e($fc) ?>"></div>
    <div class="col-md-3 d-flex align-items-end gap-2"><button class="btn btn-vg" type="submit">Filtrer</button>
        <a class="btn btn-outline-secondary" href="/espace-employe.php">Réinitialiser</a></div>
</form>

<?php if (!$commandes): ?><p>Aucune commande.</p><?php else: ?>
<div class="table-responsive">
<table class="table align-middle">
    <caption class="visually-hidden">Liste des commandes</caption>
    <thead><tr><th scope="col">N°</th><th scope="col">Client</th><th scope="col">Menu</th><th scope="col">Prestation</th><th scope="col">Statut</th><th scope="col">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($commandes as $c): ?>
        <tr>
            <td><?= e($c['numero_commande']) ?></td>
            <td><?= e($c['prenom'] . ' ' . $c['nom']) ?><br><small><?= e($c['email']) ?> · <?= e($c['telephone']) ?></small></td>
            <td><?= e($c['titre']) ?> (<?= (int)$c['nombre_personne'] ?> pers.)</td>
            <td><?= e(date('d/m/Y', strtotime($c['date_prestation']))) ?> à <?= e(substr($c['heure_livraison'], 0, 5)) ?></td>
            <td><?= e(statut_label($c['statut'])) ?></td>
            <td>
                <?php foreach (SUITES[$c['statut']] ?? [] as $suite): ?>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?><input type="hidden" name="action" value="statut">
                        <input type="hidden" name="commande_id" value="<?= (int)$c['commande_id'] ?>">
                        <input type="hidden" name="nouveau" value="<?= e($suite) ?>">
                        <button class="btn btn-sm btn-vg mb-1" type="submit">→ <?= e(statut_label($suite)) ?></button>
                    </form>
                <?php endforeach; ?>
                <?php if (!in_array($c['statut'], ['terminee', 'annulee'], true)): ?>
                <details class="mt-1">
                    <summary>Annuler</summary>
                    <form method="post" class="mt-2">
                        <?= csrf_field() ?><input type="hidden" name="action" value="annuler">
                        <input type="hidden" name="commande_id" value="<?= (int)$c['commande_id'] ?>">
                        <p class="small mb-1">Le client doit avoir été contacté avant l'annulation.</p>
                        <label class="form-label" for="mode<?= (int)$c['commande_id'] ?>">Mode de contact</label>
                        <select class="form-select mb-2" id="mode<?= (int)$c['commande_id'] ?>" name="mode_contact" required>
                            <option value="">Choisir</option><option value="gsm">Appel GSM</option><option value="mail">Mail</option>
                        </select>
                        <label class="form-label" for="motif<?= (int)$c['commande_id'] ?>">Motif</label>
                        <textarea class="form-control mb-2" id="motif<?= (int)$c['commande_id'] ?>" name="motif" rows="2" required></textarea>
                        <button class="btn btn-sm btn-outline-danger" type="submit">Confirmer l'annulation</button>
                    </form>
                </details>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
