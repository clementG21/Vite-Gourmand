// Filtrage dynamique des menus, sans rechargement de page (fetch + DOM).
const form = document.getElementById('filtres');
const liste = document.getElementById('liste-menus');
const statut = document.getElementById('statut');
let minuteur = null;

async function charger() {
    const params = new URLSearchParams(new FormData(form));
    for (const [cle, valeur] of [...params]) {
        if (valeur === '') params.delete(cle);   // on n'envoie que les filtres renseignés
    }
    statut.textContent = 'Chargement des menus...';
    try {
        const reponse = await fetch('/api/menus.php?' + params.toString());
        if (!reponse.ok) throw new Error('HTTP ' + reponse.status);
        afficher(await reponse.json());
    } catch (erreur) {
        liste.replaceChildren();
        statut.textContent = 'Impossible de charger les menus. Veuillez réessayer.';
    }
}

function creer(tag, classes, texte) {
    const el = document.createElement(tag);
    if (classes) el.className = classes;
    if (texte !== undefined) el.textContent = texte;   // textContent : pas d'injection HTML (XSS)
    return el;
}

function afficher(menus) {
    liste.replaceChildren();
    statut.textContent = menus.length === 0
        ? 'Aucun menu ne correspond à votre recherche.'
        : menus.length + (menus.length > 1 ? ' menus trouvés.' : ' menu trouvé.');

    for (const m of menus) {
        const col = creer('div', 'col-md-6 col-lg-4 mb-4');
        const carte = creer('article', 'card card-menu h-100');

        if (m.image) {
            const img = creer('img', 'card-img-top');
            img.src = m.image;
            img.alt = m.image_alt || '';
            img.loading = 'lazy';
            carte.append(img);
        }

        const corps = creer('div', 'card-body d-flex flex-column');
        corps.append(creer('h2', 'h5 card-title', m.titre));
        corps.append(creer('p', 'card-text', m.description));
        const etiquettes = creer('p', 'mb-2');
        etiquettes.append(creer('span', 'badge badge-vg me-1', m.theme), creer('span', 'badge badge-vg-outline', m.regime));
        corps.append(etiquettes);
        corps.append(creer('p', 'prix mb-2',
            'À partir de ' + Number(m.prix_minimum).toFixed(2) + ' € pour ' + m.nombre_personne_minimum + ' personnes'));

        if (m.quantite_restante <= 0) corps.append(creer('p', 'fw-bold small', 'Menu momentanément indisponible'));
        else if (m.quantite_restante <= 3) corps.append(creer('p', 'fw-bold small', 'Plus que ' + m.quantite_restante + ' commande(s) possible(s)'));

        const lien = creer('a', 'btn btn-vg mt-auto', 'Voir le détail');
        lien.href = '/menu.php?id=' + encodeURIComponent(m.menu_id);
        lien.setAttribute('aria-label', 'Voir le détail du menu ' + m.titre);
        corps.append(lien);

        carte.append(corps);
        col.append(carte);
        liste.append(col);
    }
}

// Mise à jour à chaque changement de filtre (léger délai pour la saisie au clavier)
form.addEventListener('input', () => { clearTimeout(minuteur); minuteur = setTimeout(charger, 250); });
form.addEventListener('submit', (e) => e.preventDefault());
form.addEventListener('reset', () => setTimeout(charger, 0));
charger();
