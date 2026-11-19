// Aperçu dynamique du prix (le calcul officiel est refait côté serveur à la validation).
const f = document.getElementById('form-commande');
const texte = document.getElementById('recap-texte');
const blocKm = document.getElementById('bloc-distance');
let minuteur = null;

const sansAccent = (s) => s.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/-/g, ' ');
const eur = (n) => Number(n).toFixed(2).replace('.', ',') + ' €';

function majDistance() {
    blocKm.hidden = sansAccent(f.ville.value) === 'bordeaux';
}

async function majPrix() {
    majDistance();
    const params = new URLSearchParams({
        menu_id: f.menu_id.value,
        nombre_personne: f.nombre_personne.value,
        ville: f.ville.value,
        distance_km: f.distance_km.value || '0',
    });
    if (!f.menu_id.value || !f.nombre_personne.value) {
        texte.textContent = 'Choisissez un menu et un nombre de personnes.';
        return;
    }
    try {
        const r = await fetch('/api/prix.php?' + params);
        const d = await r.json();
        if (!r.ok) { texte.textContent = d.erreur || 'Calcul impossible.'; return; }
        let t = 'Menu : ' + eur(d.prix_menu);
        if (d.reduction > 0) t += ' (réduction de 10 % incluse : -' + eur(d.reduction) + ')';
        t += ' · Livraison : ' + eur(d.prix_livraison) + ' · Total : ' + eur(d.total);
        texte.textContent = t;
    } catch (e) {
        texte.textContent = 'Calcul du prix indisponible.';
    }
}

// Pré-remplit le minimum de personnes quand on change de menu
f.menu_id.addEventListener('change', () => {
    const min = f.menu_id.selectedOptions[0]?.dataset.min;
    if (min && (!f.nombre_personne.value || Number(f.nombre_personne.value) < Number(min))) f.nombre_personne.value = min;
});
f.addEventListener('input', () => { clearTimeout(minuteur); minuteur = setTimeout(majPrix, 250); });
f.addEventListener('change', majPrix);
majPrix();
