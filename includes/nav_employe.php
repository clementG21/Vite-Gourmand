<?php $roleNav = current_user()['role'] ?? ''; ?>
<nav aria-label="Gestion" class="mb-4">
    <ul class="nav nav-pills flex-wrap">
        <li class="nav-item"><a class="nav-link" href="/espace-employe.php">Commandes</a></li>
        <li class="nav-item"><a class="nav-link" href="/employe-menus.php">Menus</a></li>
        <li class="nav-item"><a class="nav-link" href="/employe-plats.php">Plats</a></li>
        <li class="nav-item"><a class="nav-link" href="/employe-horaires.php">Horaires</a></li>
        <li class="nav-item"><a class="nav-link" href="/employe-avis.php">Avis</a></li>
        <?php if ($roleNav === 'administrateur'): ?>
            <li class="nav-item"><a class="nav-link" href="/espace-admin.php">Administration</a></li>
        <?php endif; ?>
    </ul>
</nav>
