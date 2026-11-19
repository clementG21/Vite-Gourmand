// Graphiques de comparaison des menus (données issues de MongoDB via /api/stats.php).
const form = document.getElementById('filtres-stats');
const statut = document.getElementById('statut-stats');
const tbody = document.getElementById('tableau-stats');
let graphCommandes = null, graphCA = null, minuteur = null;

const eur = (n) => Number(n).toFixed(2).replace('.', ',') + ' €';

function dessiner(canvasId, ancien, libelle, labels, valeurs) {
    if (ancien) ancien.destroy();
    return new Chart(document.getElementById(canvasId), {
        type: 'bar',
        data: { labels, datasets: [{ label: libelle, data: valeurs, backgroundColor: '#7b1e3a' }] },
        options: { responsive: true, scales: { y: { beginAtZero: true } } },
    });
}

async function charger() {
    const params = new URLSearchParams(new FormData(form));
    for (const [k, v] of [...params]) if (v === '') params.delete(k);
    statut.textContent = 'Chargement des statistiques...';
    try {
        const r = await fetch('/api/stats.php?' + params);
        const donnees = await r.json();
        if (!r.ok) throw new Error(donnees.erreur || 'Erreur');

        tbody.replaceChildren();
        let totalCmd = 0, totalCA = 0;
        for (const l of donnees) {
            const tr = document.createElement('tr');
            for (const [i, txt] of [l.titre, String(l.nombre_commandes), eur(l.chiffre_affaires)].entries()) {
                const cell = document.createElement(i === 0 ? 'th' : 'td');
                if (i === 0) cell.scope = 'row';
                cell.textContent = txt;
                tr.append(cell);
            }
            tbody.append(tr);
            totalCmd += l.nombre_commandes; totalCA += l.chiffre_affaires;
        }
        document.getElementById('total-commandes').textContent = String(totalCmd);
        document.getElementById('total-ca').textContent = eur(totalCA);

        graphCommandes = dessiner('graph-commandes', graphCommandes, 'Nombre de commandes', donnees.map(d => d.titre), donnees.map(d => d.nombre_commandes));
        graphCA = dessiner('graph-ca', graphCA, "Chiffre d'affaires (€)", donnees.map(d => d.titre), donnees.map(d => d.chiffre_affaires));
        statut.textContent = donnees.length ? 'Statistiques mises à jour.' : 'Aucune commande pour ces critères.';
    } catch (e) {
        statut.textContent = 'Statistiques indisponibles : ' + e.message;
    }
}
form.addEventListener('input', () => { clearTimeout(minuteur); minuteur = setTimeout(charger, 250); });
form.addEventListener('reset', () => setTimeout(charger, 0));
charger();
