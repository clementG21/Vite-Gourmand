</main>
<?php
$horaires = db()->query("SELECT jour, heure_ouverture, heure_fermeture FROM horaire ORDER BY FIELD(jour,'lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche')")->fetchAll();
?>
<footer class="bg-vg text-white py-4 mt-4">
    <div class="container row mx-auto">
        <section class="col-md-6" aria-labelledby="titre-horaires">
            <h2 class="h5" id="titre-horaires">Horaires</h2>
            <ul class="list-unstyled mb-0">
            <?php foreach ($horaires as $h): ?>
                <li><?= e(ucfirst($h['jour'])) ?> :
                    <?= $h['heure_ouverture'] ? e(substr($h['heure_ouverture'],0,5)) . ' - ' . e(substr($h['heure_fermeture'],0,5)) : 'Fermé' ?>
                </li>
            <?php endforeach; ?>
            </ul>
        </section>
        <nav class="col-md-6" aria-label="Informations légales">
            <h2 class="h5">Informations</h2>
            <ul class="list-unstyled mb-0">
                <li><a class="text-white" href="/mentions-legales.php">Mentions légales</a></li>
                <li><a class="text-white" href="/cgv.php">Conditions générales de vente</a></li>
            </ul>
        </nav>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
